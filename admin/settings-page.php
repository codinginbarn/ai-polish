<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ai_polish_register_settings_page(): void {
	add_action( 'admin_menu', 'ai_polish_add_settings_page' );
	add_action( 'admin_init', 'ai_polish_register_settings' );
}

function ai_polish_add_settings_page(): void {
	add_options_page(
		'AI Polish',
		'AI Polish',
		'manage_options',
		'ai-polish',
		'ai_polish_render_settings_page'
	);
}

function ai_polish_register_settings(): void {
	register_setting(
		'ai_polish',
		AI_POLISH_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ai_polish_sanitize_settings',
			'default'           => ai_polish_get_settings(),
		)
	);

	add_settings_section(
		'ai_polish_main',
		'OpenAI Settings',
		'__return_null',
		'ai-polish'
	);

	add_settings_field(
		'ai_polish_api_key',
		'OpenAI API Key',
		'ai_polish_field_api_key',
		'ai-polish',
		'ai_polish_main'
	);

	add_settings_field(
		'ai_polish_model',
		'Model',
		'ai_polish_field_model',
		'ai-polish',
		'ai_polish_main'
	);

	add_settings_field(
		'ai_polish_default_action',
		'Default Action',
		'ai_polish_field_default_action',
		'ai-polish',
		'ai_polish_main'
	);

	add_settings_field(
		'ai_polish_auto_replace',
		'Auto Replace',
		'ai_polish_field_auto_replace',
		'ai-polish',
		'ai_polish_main'
	);
}

function ai_polish_sanitize_settings( $value ): array {
	$value = is_array( $value ) ? $value : array();

	$existing = ai_polish_get_settings();
	$api_key  = isset( $value['api_key'] ) ? sanitize_text_field( $value['api_key'] ) : '';
	$api_key  = trim( $api_key );
	if ( '' === $api_key ) {
		$api_key = isset( $existing['api_key'] ) ? (string) $existing['api_key'] : '';
	}

	$model = isset( $value['model'] ) ? sanitize_text_field( $value['model'] ) : '';
	$model = trim( $model );

	$default_action = isset( $value['default_action'] ) ? sanitize_text_field( $value['default_action'] ) : 'polish';
	$default_action = strtolower( trim( $default_action ) );
	if ( ! in_array( $default_action, array( 'rewrite', 'polish' ), true ) ) {
		$default_action = 'polish';
	}

	$auto_replace = ! empty( $value['auto_replace'] ) ? 1 : 0;

	return array(
		'api_key'        => $api_key,
		'model'          => $model,
		'default_action' => $default_action,
		'auto_replace'   => $auto_replace,
	);
}

function ai_polish_admin_enqueue( string $hook_suffix ): void {
	$is_settings = 'settings_page_ai-polish' === $hook_suffix;
	$is_editor   = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true );
	$is_meta_box_loader = isset( $_GET['meta-box-loader'] ) && '1' === (string) wp_unslash( $_GET['meta-box-loader'] );
	if ( $is_meta_box_loader ) {
		$is_editor = true;
	}
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && isset( $screen->base ) && 'post' === $screen->base ) {
			$is_editor = true;
		}
	}

	// Do not gate enqueue on `edit_posts`. CPT roles may have `edit_post` without `edit_posts`,
	// which would break the editor sidebar UI.
	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! $is_settings && ! $is_editor ) {
		return;
	}

	wp_enqueue_style(
		'ai-polish-admin',
		AI_POLISH_PLUGIN_URL . 'assets/admin.css',
		array(),
		(string) filemtime( AI_POLISH_PLUGIN_DIR . 'assets/admin.css' )
	);

	// Avoid multiple competing click handlers on the post editor screen.
	// The editor uses a dedicated vanilla script for maximum compatibility.
	if ( $is_settings ) {
		wp_enqueue_script(
			'ai-polish-admin',
			AI_POLISH_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			(string) filemtime( AI_POLISH_PLUGIN_DIR . 'assets/admin.js' ),
			true
		);
	}

	if ( $is_editor ) {
		wp_enqueue_script(
			'ai-polish-editor',
			AI_POLISH_PLUGIN_URL . 'assets/editor.js',
			array(),
			(string) filemtime( AI_POLISH_PLUGIN_DIR . 'assets/editor.js' ),
			true
		);
	}

	if ( $is_editor ) {
		wp_localize_script(
			'ai-polish-editor',
			'aiPolish',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => ai_polish_admin_nonce(),
				'settings'   => ai_polish_get_settings(),
				'isSettings' => $is_settings,
				'isEditor'   => $is_editor,
				'strings'    => array(
					'testing'      => 'Testing...',
					'loading'      => 'Loading...',
					'running'      => 'Running...',
					'replacing'    => 'Replacing...',
					'testOk'       => 'Connection OK.',
					'testFail'     => 'Connection failed.',
					'modelsLoaded' => 'Models loaded.',
				),
			)
		);
	}

	if ( $is_settings ) {
		wp_localize_script(
			'ai-polish-admin',
			'aiPolish',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => ai_polish_admin_nonce(),
				'settings'   => ai_polish_get_settings(),
				'isSettings' => $is_settings,
				'isEditor'   => $is_editor,
				'strings'    => array(
					'testing'      => 'Testing...',
					'loading'      => 'Loading...',
					'running'      => 'Running...',
					'replacing'    => 'Replacing...',
					'testOk'       => 'Connection OK.',
					'testFail'     => 'Connection failed.',
					'modelsLoaded' => 'Models loaded.',
				),
			)
		);
	}
}

function ai_polish_enqueue_block_editor_assets(): void {
	ai_polish_admin_enqueue( 'post.php' );
}

function ai_polish_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
	<div class="wrap ai-polish-settings">
		<h1>AI Polish</h1>
		<p>Use Openai Polish to improve your content. User Openai rewrite to make your content more engaging and professional.</p>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'ai_polish' );
			do_settings_sections( 'ai-polish' );
			submit_button();
			?>
		</form>

		<hr />

		<h2>Tools</h2>
		<p>
			<button type="button" class="button" id="ai-polish-test-connection">Test API Connection</button>
			<button type="button" class="button" id="ai-polish-load-models">Load Models</button>
			<span class="ai-polish-settings-status" id="ai-polish-settings-status" aria-live="polite"></span>
		</p>
	</div>
	<?php
}

function ai_polish_field_api_key(): void {
	$settings = ai_polish_get_settings();
	$api_key  = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	$masked   = '' !== $api_key ? str_repeat( '•', 20 ) : '';

	?>
	<input
		type="password"
		name="<?php echo esc_attr( AI_POLISH_OPTION_KEY ); ?>[api_key]"
		value=""
		placeholder="<?php echo esc_attr( $masked ); ?>"
		autocomplete="off"
		class="regular-text"
	/>
	<p class="description">
		For security, the stored key is not shown. Enter a new key to replace it.
	</p>
	<?php
}

function ai_polish_field_model(): void {
	$settings = ai_polish_get_settings();
	$model    = isset( $settings['model'] ) ? (string) $settings['model'] : '';
	?>
	<p style="margin: 0 0 6px 0;">
		<input
			type="text"
			id="ai-polish-model-filter"
			class="regular-text"
			placeholder="Type to filter models…"
			autocomplete="off"
		/>
	</p>
	<select name="<?php echo esc_attr( AI_POLISH_OPTION_KEY ); ?>[model]" id="ai-polish-model-select">
		<option value="">(Select a model)</option>
		<?php if ( '' !== $model ) : ?>
			<option value="<?php echo esc_attr( $model ); ?>" selected><?php echo esc_html( $model ); ?></option>
		<?php endif; ?>
	</select>
	<p class="description">Use “Load Models” (below) to fetch available models for your API key.</p>
	<?php
}

function ai_polish_field_default_action(): void {
	$action = ai_polish_get_default_action();
	?>
	<select name="<?php echo esc_attr( AI_POLISH_OPTION_KEY ); ?>[default_action]">
		<option value="polish" <?php selected( 'polish', $action ); ?>>Polish</option>
		<option value="rewrite" <?php selected( 'rewrite', $action ); ?>>Rewrite</option>
	</select>
	<p class="description">Can be overridden in the editor for each post, page and custom post type.</p>
	<?php
}

function ai_polish_field_auto_replace(): void {
	$enabled = ai_polish_get_auto_replace();
	?>
	<label>
		<input
			type="checkbox"
			name="<?php echo esc_attr( AI_POLISH_OPTION_KEY ); ?>[auto_replace]"
			value="1"
			<?php checked( $enabled ); ?>
		/>
		Replace content in editor automatically after a successful run. Keep in mind this will replace the content without an additional confirmation step, so use with caution.
	</label>
	<?php
}
