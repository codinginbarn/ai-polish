<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AI_POLISH_OPTION_KEY = 'ai_polish_settings';

function ai_polish_get_settings(): array {
	$defaults = array(
		'api_key'        => '',
		'model'          => '',
		'default_action' => 'polish', // 'rewrite' | 'polish'
		'auto_replace'   => 0,
	);

	$stored = get_option( AI_POLISH_OPTION_KEY, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	return array_merge( $defaults, $stored );
}

function ai_polish_get_api_key(): string {
	$settings = ai_polish_get_settings();
	$key      = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';

	return trim( $key );
}

function ai_polish_get_model(): string {
	$settings = ai_polish_get_settings();
	$model    = isset( $settings['model'] ) ? (string) $settings['model'] : '';

	return trim( $model );
}

function ai_polish_get_default_action(): string {
	$settings = ai_polish_get_settings();
	$action   = isset( $settings['default_action'] ) ? (string) $settings['default_action'] : 'polish';
	$action   = strtolower( trim( $action ) );

	return in_array( $action, array( 'rewrite', 'polish' ), true ) ? $action : 'polish';
}

function ai_polish_get_auto_replace(): bool {
	$settings = ai_polish_get_settings();
	return ! empty( $settings['auto_replace'] );
}

function ai_polish_admin_nonce(): string {
	return wp_create_nonce( 'ai_polish_admin' );
}

