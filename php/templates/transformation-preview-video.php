<?php
/**
 * Video transformations preview template.
 *
 * @package Cloudinary
 */

$url         = CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO;
$src         = $url . '/dog.mp4';
$preview_src = $url . 'w_600/';
$sample      = '/dog.mp4';
$script_data = array(
	'url'         => $url,
	'preview_url' => $preview_src,
	'file'        => $sample,
	'error'       => esc_html__( 'Invalid transformations or error loading preview.', 'cloudinary' ),
	'valid_types' => \Cloudinary\Connect\Api::$transformation_index['video'],
);

wp_add_inline_script( 'cloudinary', 'CLD_GLOBAL_TRANSFORMATIONS.video = ' . wp_json_encode( $script_data ), 'before' );

$player   = array();
$player[] = 'var cld = cloudinary.Cloudinary.new({ cloud_name: \'demo\' });';
$player[] = 'var samplePlayer = cld.videoPlayer(\'sample-video\', { fluid : true } );';
wp_add_inline_script( 'cld-player', implode( $player ) );
?>

<div class="global-transformations" id="sample-code-video">
	<div class="global-transformations-preview">
		<video id="sample-video" width="427" height="240" controls="controls"></video>
		<button type="button" class="button-primary global-transformations-button" id="refresh-video-preview"><?php esc_html_e( 'Refresh Preview', 'cloudinary' ); ?></button>
		<span class="spinner global-transformations-spinner" id="video-loader"></span>
	</div>

	<div class="global-transformations-url">
		<a class="global-transformations-url-link" href="<?php echo esc_url( $src ); ?>" target="_blank">../<span class="global-transformations-url-resource">video/upload</span><span class="global-transformations-url-transformation" id="transformation-sample-video"></span><span class="global-transformations-url-file"><?php echo esc_html( $sample ); ?></span></a>
	</div>
</div>
