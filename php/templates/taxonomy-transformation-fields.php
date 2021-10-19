<?php
/**
 * Add new taxonomy, global transformations template.
 *
 * @package Cloudinary
 */

use function Cloudinary\get_plugin_instance;

wp_enqueue_style( 'cld-player' );
wp_enqueue_script( 'cld-player' );

wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );

$tax_slug   = filter_input( INPUT_GET, 'taxonomy', FILTER_SANITIZE_STRING );
$tax_name   = get_taxonomy_labels( get_taxonomy( $tax_slug ) )->name;
$cloudinary = get_plugin_instance();
?>
<div class="cloudinary-collapsible">
	<div class="cloudinary-collapsible__toggle">
		<img class="cld-ui-icon" src="<?php echo esc_url( $cloudinary->dir_url . '/css/images/logo-icon.svg' ); ?>">
		<h2>
			<?php
			// translators: The taxonomy label.
			echo esc_html( sprintf( __( '%s Transformations Appending', 'cloudinary' ), $tax_name ) );
			?>
		</h2>
		<button type="button"><i class="dashicons dashicons-arrow-down-alt2"></i></button>
	</div>
	<div class="cloudinary-collapsible__content" style="display:none;">
		<?php foreach ( $this->taxonomy_fields as $context => $set ) : ?>
			<?php foreach ( $set as $setting ) : ?>
				<?php $setting->get_component()->render( true ); ?>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
</div>
