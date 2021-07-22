<?php
/**
 * Get Connection String html content for the connect tab.
 *
 * @package Cloudinary
 */

$dir_url = Cloudinary\get_plugin_instance()->dir_url;
?>

<p>
	<?php
	printf(
		wp_kses_post( 'After creating your %s:', 'cloudinary' ),
		sprintf(
			// translators: Link to create a Cloudinary account.
			esc_html__( '%1$sCloudinary account%2$s', 'cloudinary' ),
			sprintf(
				'<a href="https://cloudinary.com/users/register/free" target="_blank" title="%s">',
				esc_attr__( 'Create here a free Cloudinary account', 'cloudinary' )
			),
			'</a>'
		)
	);
	?>
</p>
<ol>
	<li><?php esc_html_e( 'Open your Cloudinary Dashboard', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'At the top of the dashboard you will find the Account Details section', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'Copy the API Environment variable', 'cloudinary' ); ?></li>
</ol>
<img src="<?php echo esc_url( $dir_url ); ?>css/images/connection-string.png" alt="<?php esc_attr_e( 'Where the connection string can be found on the cloudinary.com console.', 'cloudinary' ); ?>" class="img-connection-string"/>
