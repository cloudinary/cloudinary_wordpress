<?php
/**
 * Add new taxonomy, global transformations template.
 *
 * @package Cloudinary
 */

use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

wp_enqueue_style( 'cld-player' );
wp_enqueue_script( 'cld-player' );

wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );

$tax_slug = Utils::get_sanitized_text( 'taxonomy' );

if ( empty( $tax_slug ) ) {
	return;
}

$current_taxonomy = get_taxonomy( $tax_slug );

if ( ! $current_taxonomy instanceof WP_Taxonomy ) {
	return;
}

$tax_labels = get_taxonomy_labels( $current_taxonomy );

if ( empty( $tax_labels ) ) {
	return;
}

$cloudinary = get_plugin_instance();
?>
<div class="cloudinary-collapsible cloudinary-collapsible--card">
	<div class="cloudinary-collapsible__toggle" data-collapsible-target="cld-collapse-taxonomy-transformations">
		<h2>
			<?php
			// translators: The taxonomy label.
			echo esc_html( sprintf( __( 'Cloudinary %s transformations', 'cloudinary' ), $tax_labels->singular_name ) );
			?>
		</h2>
		<?php
		// translators: The taxonomy singular label (e.g. Category, Tag).
		$toggle_label = sprintf( __( 'Toggle %s transformations', 'cloudinary' ), $tax_labels->singular_name );
		?>
		<button
			type="button"
			aria-expanded="false"
			aria-controls="cld-collapse-taxonomy-transformations"
			aria-label="<?php echo esc_attr( $toggle_label ); ?>"
		><i class="dashicons dashicons-arrow-down-alt2"></i></button>
	</div>
	<div id="cld-collapse-taxonomy-transformations" class="cloudinary-collapsible__content" hidden>
		<div class="cld-more-details">
			<?php
			printf(
				// translators: %1$s is the taxonomy label, %2$s is the image settings link, %4$s is the video settings link. The %3$s is the closing tags for the links.
				esc_html__( 'Add these %1$s-specific transformations to the global Cloudinary transformations in the plugin\'s %2$simage%3$s and %4$svideo%3$s settings.', 'cloudinary' ),
				esc_html( $tax_labels->singular_name ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=cloudinary_image_settings#text-image-settings.image-freeform' ) ) . '">',
				'</a>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=cloudinary_video_settings#text-video-settings.video-freeform' ) ) . '">'
			)
			?>
		</div>
		<?php foreach ( $this->taxonomy_fields as $context => $set ) : ?>
			<?php foreach ( $set as $setting ) : ?>
				<?php $setting->get_component()->render( true ); ?>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
</div>
