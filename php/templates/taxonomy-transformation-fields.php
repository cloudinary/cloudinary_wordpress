<?php
/**
 * Add new taxonomy, global transformations template.
 *
 * @package Cloudinary
 */
wp_enqueue_style( 'cld-player' );
wp_enqueue_script( 'cld-player' );

wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );
$show_desc = true;
?>
<div class="cloudinary-collapsible">
	<div class="cloudinary-collapsible__toggle">
		<h2><?php esc_html_e( 'Cloudinary Global Transformations', 'cloudinary' ); ?></h2>
		<button type="button"><i class="dashicons dashicons-arrow-down-alt2"></i></button>
	</div>
	<div class="cloudinary-collapsible__content" style="display: none;">
		<?php foreach ( $this->fields as $field_slug => $field ) : ?>
			<div class="form-field term-<?php echo esc_attr( $field_slug ); ?>-wrap">
				<label for="cloudinary_<?php echo esc_attr( $field_slug ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php
				$field['slug']      = $field_slug;
				$field['label_for'] = 'field-' . $field['slug'];
				$this->media->plugin->components['settings']->render_field( $field, null, $show_desc );
				$show_desc = false;
				?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
