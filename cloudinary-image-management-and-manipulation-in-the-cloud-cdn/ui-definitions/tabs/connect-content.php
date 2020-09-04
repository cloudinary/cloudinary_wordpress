<?php
/**
 * Connect html content for the connect tab.
 *
 * @package Cloudinary
 */

?>
<?php if ( $this->plugin->get_component( 'connect' )->is_connected() ) : ?>
	<div class="settings-tab-section-card">
		<div class="settings-tab-section-fields-dashboard-success">
			<span class="dashicons dashicons-yes"></span> <strong><?php esc_html_e( 'Connected to Cloudinary', 'cloudinary' ); ?></strong>
		</div>
	</div>
<?php endif; ?>
<div class="settings-tab-section-card pull-right">
	<h4><?php esc_html_e( 'Where do I find this environment variable URL?', 'cloudinary' ); ?></h4>
	<?php
	echo wp_kses_post(
		sprintf(
			// translators: Placeholders are URLS.
			__(
				'After creating your Cloudinary account, enter your account details as they appear in your <a href="%s" target="_blank">Cloudinary Dashboard</a>',
				'cloudinary'
			),
			'https://cloudinary.com/console'
		)
	);
	?>
	<img class="settings-image" src="https://res.cloudinary.com/demo/image/upload/c_crop,w_800,g_north_west/wp-account-env-variable-example.png">
</div>
<div class="settings-tab-section">
	<h3><?php esc_html_e( 'Connect to Cloudinary', 'cloudinary' ); ?></h3>
	<p>
		<?php

		echo wp_kses_post(
			__( 'To use the Cloudinary plugin, you need to connect your Cloudinary account to WordPress by adding your Environment variable URL.', 'cloudinary' )
		);
		?>

	</p>
</div>

