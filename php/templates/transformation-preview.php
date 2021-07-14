<?php
/**
 * Transformations preview template.
 *
 * @package Cloudinary
 */

$url         = CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE;
$src         = $url . '/sample.jpg';
$preview_src = $url . 'w_600/';
$sample      = '/sample.jpg';
$script_data = array(
	'url'         => $url,
	'preview_url' => $preview_src,
	'file'        => $sample,
	'error'       => esc_html__( 'Invalid transformations or error loading preview.', 'cloudinary' ),
	/* translators: %s is the transformation that breaks the preview. */
	'warning'     => esc_html__( 'Preview cannot be generated because %s transformation was used.', 'cloudinary' ),
	'valid_types' => \Cloudinary\Connect\Api::$transformation_index['image'],
);

wp_add_inline_script( 'cloudinary', 'CLD_GLOBAL_TRANSFORMATIONS.image = ' . wp_json_encode( $script_data ), 'before' );

?>

<div class="global-transformations" id="sample-code-image">
	<div id="cld-preview-error" class="settings-alert settings-alert-error" style="display: none; margin-bottom: 10px;"></div>

	<div class="global-transformations-preview">
		<img id="sample-image" style="max-width: 100%;" src="<?php echo esc_url( $preview_src . $sample ); ?>">
		<button type="button" class="button-primary global-transformations-button" id="refresh-image-preview"><?php esc_html_e( 'Refresh Preview', 'cloudinary' ); ?></button>
		<span class="spinner global-transformations-spinner" id="image-loader"></span>
	</div>

	<div class="global-transformations-url">
		<a class="global-transformations-url-link" href="<?php echo esc_url( $src ); ?>" target="_blank">../<span class="global-transformations-url-resource">image/upload</span><span class="global-transformations-url-transformation" id="transformation-sample-image"></span><span class="global-transformations-url-file"><?php echo esc_html( $sample ); ?></span></a>
	</div>
</div>


