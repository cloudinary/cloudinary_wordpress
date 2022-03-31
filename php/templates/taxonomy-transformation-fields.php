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

$tax_slug   = Utils::get_sanitized_text( 'taxonomy' );
$tax_labels = get_taxonomy_labels( get_taxonomy( $tax_slug ) );
$cloudinary = get_plugin_instance();
?>
<div class="cloudinary-collapsible">
	<div class="cloudinary-collapsible__toggle">
		<h2>
			<?php
			// translators: The taxonomy label.
			echo esc_html( sprintf( __( 'Cloudinary %s transformations', 'cloudinary' ), strtolower( $tax_labels->singular_name ) ) );
			?>
		</h2>
		<button type="button"><i class="dashicons dashicons-arrow-down-alt2"></i></button>
	</div>
	<div class="cloudinary-collapsible__content" style="display:none;">
		<div class="cld-more-details">
			<?php
			echo esc_html(
				sprintf(
					// translators: The taxonomy label.
					__( 'Additional transformations for this %s that will be appended to the globally defined Cloudinary transformations.', 'cloudinary' ),
					$tax_labels->singular_name
				)
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
