<?php
/**
 * The Admin page header template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

$cloudinary = get_plugin_instance();
?>
<header class="cld-ui-wrap cld-page-header" id="cloudinary-header">
	<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/logo.svg' ); ?>" alt="<?php esc_html_e( "Cloudinary's logo", 'cloudinary' ); ?>"/>
	<p>
		<a href="https://cloudinary.com/documentation/wordpress_integration" target="_blank" rel="noreferrer" class="cld-page-header-button">
			<?php esc_html_e( 'Need help?', 'cloudinary' ); ?>
		</a>
		<a href="https://cloudinary.com/documentation/wordpress_integration" target="_blank" rel="noreferrer" class="cld-page-header-button">
			<?php esc_html_e( 'Rate our plugin', 'cloudinary' ); ?>
		</a>
	</p>
</header>
