<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ai_polish_register_metabox(): void {
	add_action( 'add_meta_boxes', 'ai_polish_add_metabox' );
}

function ai_polish_add_metabox(): void {
	$post_types = get_post_types(
		array(
			'public' => true,
		),
		'names'
	);

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'ai-polish-metabox',
			'AI Polish',
			'ai_polish_render_metabox',
			$post_type,
			'side',
			'high'
		);
	}
}

function ai_polish_render_metabox( WP_Post $post ): void {
	$default_action = ai_polish_get_default_action();
	$auto_replace   = ai_polish_get_auto_replace();
	$model          = ai_polish_get_model();
	$ajax_url       = admin_url( 'admin-ajax.php' );
	$nonce          = ai_polish_admin_nonce();
	$build_id       = (string) filemtime( __FILE__ );

	?>
	<div
		class="ai-polish-metabox"
		data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
		data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-build="<?php echo esc_attr( $build_id ); ?>"
		data-model="<?php echo esc_attr( $model ); ?>"
		data-auto-replace="<?php echo esc_attr( $auto_replace ? '1' : '0' ); ?>"
	>
		<p class="description" style="margin-top:0;">
			Build: <code><?php echo esc_html( $build_id ); ?></code> |
			Editor JS: <code id="ai-polish-script-status">not-loaded</code>
		</p>

		<p>
			<label for="ai-polish-action"><strong>Action</strong></label><br />
			<select id="ai-polish-action">
				<option value="polish" <?php selected( 'polish', $default_action ); ?>>Polish</option>
				<option value="rewrite" <?php selected( 'rewrite', $default_action ); ?>>Rewrite</option>
			</select>
		</p>

		<p class="ai-polish-flags">
			<label style="display:block; margin: 0 0 4px 0;">
				<input type="checkbox" id="ai-polish-include-title" />
				Update title
			</label>
			<label style="display:block; margin: 0;">
				<input type="checkbox" id="ai-polish-include-excerpt" />
				Update excerpt
			</label>
		</p>

		<?php if ( '' !== $model ) : ?>
			<p class="description">Model: <code><?php echo esc_html( $model ); ?></code></p>
		<?php else : ?>
			<p class="description">No model selected (will default to <code>gpt-4o-mini</code>).</p>
		<?php endif; ?>

		<p>
			<button type="button" class="button button-primary" id="ai-polish-run" onclick="if (window.aiPolishFallbackRun) { window.aiPolishFallbackRun(this); } return false;">Run</button>
			<span class="ai-polish-clicks" id="ai-polish-clicks" aria-hidden="true">0</span>
			<span class="spinner" id="ai-polish-spinner"></span>
		</p>

		<p>
			<label for="ai-polish-output-title"><strong>Title</strong></label>
			<input id="ai-polish-output-title" type="text" style="width: 100%;" placeholder="Title result…" />
		</p>

		<p>
			<label for="ai-polish-output-excerpt"><strong>Excerpt</strong></label>
			<textarea id="ai-polish-output-excerpt" rows="3" style="width: 100%;" placeholder="Excerpt result…"></textarea>
		</p>

		<p>
			<label for="ai-polish-output"><strong>Content</strong></label>
			<textarea id="ai-polish-output" rows="8" style="width: 100%;" placeholder="Content result…"></textarea>
		</p>

		<p>
			<button type="button" class="button" id="ai-polish-replace" onclick="if (window.aiPolishFallbackReplace) { window.aiPolishFallbackReplace(this); } return false;">Replace Selected</button>
			<?php if ( $auto_replace ) : ?>
				<span class="description">Auto-replace enabled.</span>
			<?php endif; ?>
		</p>

		<div class="ai-polish-status" id="ai-polish-status" aria-live="polite">Waiting for AI Polish script...</div>
	</div>
	<?php
}
