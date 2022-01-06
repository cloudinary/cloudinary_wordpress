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
	<span class="cld-page-header-logo">
		<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/logo.svg' ); ?>" alt="<?php esc_attr_e( "Cloudinary's logo", 'cloudinary' ); ?>"/>
		<span class="version"><?php echo esc_html( $cloudinary->version ); ?></span>
	</span>
	<p>
		<a href="<?php echo esc_url( add_query_arg( 'page', 'cloudinary_help' ) ); ?>" class="cld-page-header-button">
			<?php esc_html_e( 'Need help?', 'cloudinary' ); ?>
		</a>
		<a href="https://wordpress.org/support/plugin/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="cld-page-header-button">
			<?php esc_html_e( 'Rate our plugin', 'cloudinary' ); ?>
		</a>
	</p>
</header>
