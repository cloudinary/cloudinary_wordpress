<?php
/**
 * Edit term, global transformations template.
 *
 * @package Cloudinary
 */

wp_enqueue_style( 'cld-player' );
wp_enqueue_script( 'cld-player' );

$transformations = array(
	'image' => $this->get_transformations( 'image' ),
	'video' => $this->get_transformations( 'video' ),
);
wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );
$show_desc = true;
?>
<tr>
	<td colspan="2"><h2><?php esc_html_e( 'Global Transformations', 'cloudinary' ); ?></h2></td>
</tr>
<?php foreach ( $this->fields as $field_slug => $field ) : ?>
	<tr class="form-field term-<?php echo esc_attr( $field_slug ); ?>-wrap">
		<th scope="row">
			<label for="cloudinary_<?php echo esc_attr( $field_slug ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
		</th>
		<td>
			<?php
			$field['slug']      = $field_slug;
			$field['label_for'] = 'field-' . $field['slug'];
			// Ensure the index exists in the transformation array.
			if ( ! isset( $transformations[ $field['context'] ][ $field_slug ] ) ) {
				$transformations[ $field['context'] ][ $field_slug ] = null;
			}
			// Render Field.
			$this->media->plugin->components['settings']->render_field( $field, $transformations[ $field['context'] ][ $field_slug ], $show_desc );
			$show_desc = false;
			?>
		</td>
	</tr>
<?php endforeach; ?>
