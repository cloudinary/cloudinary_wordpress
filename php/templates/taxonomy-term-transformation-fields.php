<?php
/**
 * Edit term, global transformations template.
 *
 * @package Cloudinary
 */

use Cloudinary\Utils;

$taxonomy_slug = Utils::get_sanitized_text( 'taxonomy' );
$tax_object    = get_taxonomy( $taxonomy_slug );

// We should be in the context of a term edit screen.
if ( ! $tax_object instanceof WP_Taxonomy ) {
	return;
}

$label = $tax_object->labels->singular_name;
?>
<?php foreach ( $this->taxonomy_fields as $context => $set ) : ?>
	<?php
	$collapse_id = 'cld-collapse-' . sanitize_html_class( $context );
	$heading     = sprintf(
		// translators: The taxonomy label and the context.
		__( 'Cloudinary %1$s %2$s Transformations', 'cloudinary' ),
		$label,
		ucwords( $context )
	);
	// translators: The transformation context (e.g. Image, Video).
	$toggle_label = sprintf( __( 'Toggle %s transformations', 'cloudinary' ), ucwords( $context ) );
	?>
	<tbody class="cloudinary-term-transformations">
		<tr>
			<td colspan="2">
				<div class="cloudinary-collapsible__toggle" data-collapsible-target="<?php echo esc_attr( $collapse_id ); ?>">
					<h3><?php echo esc_html( $heading ); ?></h3>
					<button
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $collapse_id ); ?>"
						aria-label="<?php echo esc_attr( $toggle_label ); ?>"
					><i class="dashicons dashicons-arrow-down-alt2"></i></button>
				</div>
			</td>
		</tr>
	</tbody>
	<tbody id="<?php echo esc_attr( $collapse_id ); ?>" hidden class="cloudinary-term-transformations">
		<?php foreach ( $set as $setting ) : ?>
			<tr class="form-field term-<?php echo esc_attr( $setting->get_slug() ); ?>-wrap">
				<th scope="row">
					<label for="cloudinary_<?php echo esc_attr( $setting->get_slug() ); ?>"><?php echo esc_html( $setting->get_param( 'title' ) ); ?></label>
				</th>
				<td>
					<?php $setting->set_param( 'title', null ); ?>
					<?php $setting->set_param( 'tooltip_text', null ); ?>
					<?php $setting->get_component()->render( true ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
<?php endforeach; ?>
