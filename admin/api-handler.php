<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ai_polish_register_ajax_endpoints(): void {
	add_action( 'wp_ajax_ai_polish_test_connection', 'ai_polish_ajax_test_connection' );
	add_action( 'wp_ajax_ai_polish_fetch_models', 'ai_polish_ajax_fetch_models' );
	add_action( 'wp_ajax_ai_polish_rewrite', 'ai_polish_ajax_rewrite' );
}

function ai_polish_openai_request( string $method, string $path, ?array $body = null ) {
	$api_key = ai_polish_get_api_key();
	if ( '' === $api_key ) {
		return new WP_Error( 'ai_polish_missing_key', 'Missing API key.' );
	}

	$url = 'https://api.openai.com' . $path;

	$args = array(
		'method'  => $method,
		'timeout' => 120,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
	);

	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return ai_polish_normalize_transport_error( $response );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$raw    = (string) wp_remote_retrieve_body( $response );
	$data   = json_decode( $raw, true );

	if ( $status < 200 || $status >= 300 ) {
		$message = 'OpenAI request failed.';
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			$message = (string) $data['error']['message'];
		}
		return new WP_Error( 'ai_polish_openai_error', $message, array( 'status' => $status, 'raw' => $raw ) );
	}

	if ( null === $data ) {
		return new WP_Error( 'ai_polish_bad_json', 'Invalid JSON returned from OpenAI.' );
	}

	return $data;
}

function ai_polish_normalize_transport_error( WP_Error $error ): WP_Error {
	$message = (string) $error->get_error_message();

	if ( false !== stripos( $message, 'cURL error 28' ) ) {
		return new WP_Error(
			'ai_polish_openai_timeout',
			'Connection to OpenAI timed out. The server could not reach api.openai.com in time. Check outbound HTTPS (port 443), DNS, firewall allowlist, or try again with shorter content.'
		);
	}

	return $error;
}

function ai_polish_verify_ajax_request( ?string $capability = null ): void {
	check_ajax_referer( 'ai_polish_admin', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
	}
	if ( null !== $capability && ! current_user_can( $capability ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
	}
}

function ai_polish_ajax_test_connection(): void {
	ai_polish_verify_ajax_request( 'manage_options' );

	$data = ai_polish_openai_request( 'GET', '/v1/models' );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error(
			array(
				'message' => $data->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success( array( 'ok' => true ) );
}

function ai_polish_ajax_fetch_models(): void {
	ai_polish_verify_ajax_request( 'manage_options' );

	$data = ai_polish_openai_request( 'GET', '/v1/models' );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error(
			array(
				'message' => $data->get_error_message(),
			),
			400
		);
	}

	$models = array();
	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		foreach ( $data['data'] as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$models[] = (string) $item['id'];
			}
		}
	}

	sort( $models );

	wp_send_json_success(
		array(
			'models' => $models,
		)
	);
}

function ai_polish_build_system_instructions( string $action, bool $include_title, bool $include_excerpt ): string {
	$action = strtolower( trim( $action ) );
	if ( 'rewrite' === $action ) {
		$instructions = array(
			'You are a senior human editor rewriting users WordPress content.',
			'Goal: produce a stronger, more natural version with a clear human voice.',
			'',
			'Rewrite mode rules:',
			'- Preserve core meaning, factual claims, and intent.',
			'- Improve structure, pacing, and readability; moderate reorganization is allowed.',
			'- Sound human: vary sentence length and rhythm, use concrete wording, avoid generic filler.',
			'- Avoid cliches and AI-sounding phrasing (for example: "in today\'s fast-paced world", "unlock", "delve", "elevate").',
			'- Keep tone confident and practical, not hype-heavy.',
			'- Keep the same language as the input.',
			'- Preserve HTML tags/structure in content where present.',
			'- Keep roughly similar length unless clarity clearly benefits from shorter text.',
			'- Do not invent facts, numbers, quotes, or guarantees.',
			'- Return plain strings only (no Markdown fences, no commentary).',
		);
	} else {
		$instructions = array(
			'You are a senior human copy editor polishing users WordPress content.',
			'Goal: make the writing read like a capable person wrote it naturally.',
			'',
			'Polish mode rules:',
			'- Preserve meaning and structure; do not materially rewrite the argument.',
			'- Improve grammar, clarity, flow, and word choice.',
			'- Keep a natural human tone with subtle rhythm variation.',
			'- Remove robotic wording, repetition, and stiff transitions.',
			'- Avoid cliches and AI-sounding phrasing (for example: "in today\'s fast-paced world", "unlock", "delve", "elevate").',
			'- Keep the same language as the input.',
			'- Preserve HTML tags/structure in content where present.',
			'- Keep approximately the same length.',
			'- Do not add new claims or information.',
			'- Return plain strings only (no Markdown fences, no commentary).',
		);
	}

	if ( $include_title ) {
		$instructions[] = '- Title: concise, specific, and natural (not clickbait).';
	}
	if ( $include_excerpt ) {
		$instructions[] = '- Excerpt: clear 1-2 sentence summary suitable for WordPress excerpts.';
	}

	return implode( "\n", $instructions );
}

function ai_polish_build_response_schema( bool $include_title, bool $include_excerpt ): array {
	$properties = array(
		'content' => array(
			'type'        => 'string',
			'description' => 'Rewritten/polished post content.',
		),
	);
	$required   = array( 'content' );

	if ( $include_title ) {
		$properties['title'] = array(
			'type'        => 'string',
			'description' => 'Rewritten/polished post title.',
		);
		$required[]          = 'title';
	}

	if ( $include_excerpt ) {
		$properties['excerpt'] = array(
			'type'        => 'string',
			'description' => 'Rewritten/polished post excerpt.',
		);
		$required[]            = 'excerpt';
	}

	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => $properties,
		'required'             => $required,
	);
}

function ai_polish_responses_output_text( array $data ): string {
	if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
		return $data['output_text'];
	}

	$text = '';
	if ( ! isset( $data['output'] ) || ! is_array( $data['output'] ) ) {
		return $text;
	}

	foreach ( $data['output'] as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['content'] ) || ! is_array( $item['content'] ) ) {
			continue;
		}
		foreach ( $item['content'] as $content_item ) {
			if ( ! is_array( $content_item ) ) {
				continue;
			}
			if ( isset( $content_item['type'] ) && 'output_text' === $content_item['type'] && isset( $content_item['text'] ) ) {
				$text .= (string) $content_item['text'];
			}
		}
	}

	return $text;
}

function ai_polish_decode_json_object( string $text ): ?array {
	$text = trim( $text );
	if ( '' === $text ) {
		return null;
	}

	$data = json_decode( $text, true );
	if ( is_array( $data ) ) {
		return $data;
	}

	$start = strpos( $text, '{' );
	$end   = strrpos( $text, '}' );
	if ( false === $start || false === $end || $end <= $start ) {
		return null;
	}

	$maybe = substr( $text, $start, $end - $start + 1 );
	$data  = json_decode( $maybe, true );

	return is_array( $data ) ? $data : null;
}

function ai_polish_ajax_rewrite(): void {
	ai_polish_verify_ajax_request();

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
	}

	$action  = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'polish';
	$action  = strtolower( trim( $action ) );
	$action  = in_array( $action, array( 'rewrite', 'polish' ), true ) ? $action : 'polish';
	$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
	$content = is_string( $content ) ? $content : '';

	$include_title   = ! empty( $_POST['include_title'] );
	$include_excerpt = ! empty( $_POST['include_excerpt'] );
	$title           = isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '';
	$title           = is_string( $title ) ? $title : '';
	$excerpt         = isset( $_POST['excerpt'] ) ? wp_unslash( $_POST['excerpt'] ) : '';
	$excerpt         = is_string( $excerpt ) ? $excerpt : '';

	// Classic Editor integrations can hide or break live editor access.
	// Fall back to the saved post fields when the browser cannot send content.
	if ( '' === trim( $content ) ) {
		$saved_content = get_post_field( 'post_content', $post_id, 'raw' );
		$content       = is_string( $saved_content ) ? $saved_content : '';
	}
	if ( $include_title && '' === trim( $title ) ) {
		$saved_title = get_post_field( 'post_title', $post_id, 'raw' );
		$title       = is_string( $saved_title ) ? $saved_title : '';
	}
	if ( $include_excerpt && '' === trim( $excerpt ) ) {
		$saved_excerpt = get_post_field( 'post_excerpt', $post_id, 'raw' );
		$excerpt       = is_string( $saved_excerpt ) ? $saved_excerpt : '';
	}
	if ( '' === trim( $content ) ) {
		wp_send_json_error( array( 'message' => 'No content to process. Save/update the post once, then retry.' ), 400 );
	}

	$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
	$model = '' !== trim( $model ) ? $model : ai_polish_get_model();
	if ( '' === $model ) {
		$model = 'gpt-4o-mini';
	}
	$temperature = ( 'rewrite' === $action ) ? 0.55 : 0.25;

	$input_fields = array(
		'content' => $content,
	);
	if ( $include_title ) {
		$input_fields['title'] = $title;
	}
	if ( $include_excerpt ) {
		$input_fields['excerpt'] = $excerpt;
	}

	$payload = array(
		'model'  => $model,
		'store'  => false,
		'input'  => array(
			array(
				'role'    => 'system',
				'content' => ai_polish_build_system_instructions( $action, $include_title, $include_excerpt ),
			),
			array(
				'role'    => 'user',
				'content' => 'Here are the current post fields as JSON: ' . wp_json_encode( $input_fields ) . "\nReturn the rewritten/polished fields as JSON matching the provided schema.",
			),
		),
		'text'  => array(
			'format' => array(
				'type'   => 'json_schema',
				'name'   => 'ai_polish_result',
				'strict' => true,
				'schema' => ai_polish_build_response_schema( $include_title, $include_excerpt ),
			),
		),
		'temperature' => $temperature,
	);

	$data = ai_polish_openai_request( 'POST', '/v1/responses', $payload );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error(
			array(
				'message' => $data->get_error_message(),
			),
			400
		);
	}

	$output_text = trim( ai_polish_responses_output_text( $data ) );
	$result      = ai_polish_decode_json_object( $output_text );
	if ( ! is_array( $result ) ) {
		wp_send_json_error( array( 'message' => 'Unexpected response format from OpenAI.' ), 400 );
	}

	$out_content = isset( $result['content'] ) ? (string) $result['content'] : '';
	$out_content = trim( $out_content );
	if ( '' === $out_content ) {
		wp_send_json_error( array( 'message' => 'No content returned from OpenAI.' ), 400 );
	}

	$response = array(
		'content' => $out_content,
		'model'   => $model,
	);

	if ( $include_title && isset( $result['title'] ) ) {
		$response['title'] = trim( (string) $result['title'] );
	}

	if ( $include_excerpt && isset( $result['excerpt'] ) ) {
		$response['excerpt'] = trim( (string) $result['excerpt'] );
	}

	wp_send_json_success(
		$response
	);
}
