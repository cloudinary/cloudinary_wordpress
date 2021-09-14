<?php
/**
 * The Admin page template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

?>
<div class="cld-ui-wrap cld-page cld-settings" id="cloudinary-settings-page">
	<header class="cld-ui-wrap cld-page-header">
		<img src="https://xwpdev.local/wp-content/plugins/cloudinary/css/images/logo.svg" alt="<?php esc_html_e( "Cloudinary's logo", 'cloudinary' ); ?>" />
		<p>
			<a href="https://cloudinary.com/documentation/wordpress_integration" target="_blank" rel="noreferrer" class="cld-page-header-button">
				<?php esc_html_e( 'Need help?', 'cloudinary' ); ?>
			</a>
			<a href="https://cloudinary.com/documentation/wordpress_integration" target="_blank" rel="noreferrer" class="cld-page-header-button">
				<?php esc_html_e( 'Rate our plugin', 'cloudinary' ); ?>
			</a>
		</p>
	</header>
	<?php require CLDN_PATH . 'ui-definitions/components/settings.php'; ?>
</div>
