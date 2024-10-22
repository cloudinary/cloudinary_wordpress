<?php
/**
 * Settings template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;

$cloudinary = get_plugin_instance();
$admin      = $cloudinary->get_component( 'admin' );

// Defaults.
$autosync       = true;
$nonmedia       = true;
$advanced       = true;
$current_tab    = 1;
$cloudinary_url = $cloudinary->settings->get_value( 'cloudinary_url' );
if ( ! empty( $cloudinary_url ) ) {
	$autosync = 'on' === $cloudinary->settings->get_value( 'auto_sync' );
	$nonmedia = 'on' === $cloudinary->settings->get_value( 'cache.enable' );
	$advanced = 'on' === $cloudinary->settings->get_value( 'use_lazy_load' ) && 'on' === $cloudinary->settings->get_value( 'enable_breakpoints' );
}

// Export settings.
$export_data = array(
	'testURL'   => Utils::rest_url( REST_API::BASE . '/test_connection' ),
	'saveURL'   => Utils::rest_url( REST_API::BASE . '/save_wizard' ),
	'saveNonce' => wp_create_nonce( 'wp_rest' ),
	'config'    => array(
		'tab'          => $current_tab,
		'cldString'    => ! empty( $cloudinary_url ),
		'mediaLibrary' => $autosync,
		'nonMedia'     => $nonmedia,
		'advanced'     => $advanced,
	),
);

$cloudinary->add_script_data( 'wizard', $export_data );

$url            = self_admin_url( add_query_arg( 'page', $cloudinary->slug, 'admin.php' ) );
$media_args     = array(
	'type'         => 'on_off',
	'description'  => __( 'Optimize media library items', 'cloudinary' ),
	'tooltip_text' => __(
		'Your media assets will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size.',
		'cloudinary'
	),
	'default'      => 'on',
	'slug'         => 'media_library',
	'disabled'     => true,
);
$non_media_args = array(
	'type'         => 'on_off',
	'description'  => __( 'Optimize themes, plugins, WordPress core and uploads folder items', 'cloudinary' ),
	'tooltip_text' => __(
		'Additional assets relating to themes, plugins, WordPress core and your uploads folder will be optimized and delivered by Cloudinary, giving you an additional performance boost.',
		'cloudinary'
	),
	'default'      => 'on',
	'slug'         => 'non_media',
	'disabled'     => true,
);
$advanced_args  = array(
	'type'         => 'on_off',
	'description'  => __( 'Activate advanced features (lazy loading and responsive breakpoints)', 'cloudinary' ),
	'tooltip_text' => __(
		"Cloudinary's lazy loading and responsive image functionality will be applied to your media assets as part of delivery to further optimize your assets.",
		'cloudinary'
	),
	'default'      => 'on',
	'slug'         => 'advanced',
	'disabled'     => true,
);
$media_library  = new Setting( 'media_library', null, $media_args );
$non_media      = new Setting( 'non_media', null, $non_media_args );
$advanced       = new Setting( 'advanced', null, $advanced_args );
$media_library->set_value( 'on' );
$non_media->set_value( 'on' );
$advanced->set_value( 'on' );
?>
<div class="wrap cld-ui-wrap cld-wizard" id="cloudinary-settings-page">
	<h1><?php esc_html_e( 'Cloudinary', 'cloudinary' ); ?></h1>
	<?php $admin->render_notices(); ?>
	<div class="cld-ui-header cld-panel-heading cld-panel">
		<span class="cld-icon">
			<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/logo-icon.svg' ); ?>" width="56px"/>
		</span>
		<div class="cld-wizard-tabs" id="wizard-tabs">
			<div class="cld-wizard-tabs-tab active" data-tab="1" id="tab-icon-1">
				<span class="cld-wizard-tabs-tab-count">1</span>
				<?php esc_html_e( 'Welcome to Cloudinary', 'cloudinary' ); ?>
			</div>
			<div class="cld-wizard-tabs-tab" data-tab="2" data-focus="connect.cloudinary_url" data-disable="next" id="tab-icon-2">
				<span class="cld-wizard-tabs-tab-count">2</span>
				<?php esc_html_e( 'Connect Plugin', 'cloudinary' ); ?>
			</div>
			<div class="cld-wizard-tabs-tab" data-tab="3" id="tab-icon-3">
				<span class="cld-wizard-tabs-tab-count">3</span>
				<?php esc_html_e( 'Recommended Settings', 'cloudinary' ); ?>
			</div>
		</div>
		<div></div>
	</div>
	<div class="cld-ui-wrap has-heading cld-panel">
		<div class="cld-wizard-content cld-wizard-intro" id="tab-1">
			<div class="cld-ui-title">
				<h2><?php esc_html_e( 'Welcome and thank you for installing the Cloudinary Plugin', 'cloudinary' ); ?></h2>
			</div>
			<p>
				<?php esc_html_e( 'For more control over the quality of the media that your site delivers, you can specify different levels of quality.', 'cloudinary' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'If pristine quality is more important to you than file size, you can choose high quality settings.', 'cloudinary' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Alternatively, choose lower quality settings to save on bandwidth and speed up delivery times.', 'cloudinary' ); ?>
			</p>
			<a href="https://cloudinary.com/signup" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Signup', 'cloudinary' ); ?></a>
			<div class="cld-wizard-intro-welcome">
				<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/wizard-welcome.jpg' ); ?>" width="650px"/>
			</div>
		</div>
		<div class="cld-wizard-content cld-wizard-connect hidden" id="tab-2">
			<div class="cld-ui-title">
				<h2><?php esc_html_e( 'Connect to Cloudinary!', 'cloudinary' ); ?></h2>
				<p>
					<?php esc_html_e( 'You need to connect your Cloudinary account to WordPress by adding your unique connection string.', 'cloudinary' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'See below for where to find this.', 'cloudinary' ); ?>
				</p>
				<div class="cld-wizard-connect-connection">
					<div class="cld-wizard-connect-connection-input cld-input-text">
						<div class="cld-text">
							<label class="cld-input-label" for="connect.cloudinary_url">
								<div class="cld-ui-title"><?php esc_html_e( 'Connection string', 'cloudinary' ); ?></div>
							</label>
						</div>
						<input type="text" class="connection-string cld-ui-input regular-text" name="connect[cloudinary_url]" id="connect.cloudinary_url" value="" placeholder="cloudinary://API_KEY:API_SECRET@CLOUD_NAME">
					</div>
					<button id="update-connection" class="button button-primary hidden"><?php esc_html_e( 'Update the connection string', 'cloudinary' ); ?></button>
					<button id="cancel-update-connection" class="button button-secondary hidden"><?php esc_html_e( 'Cancel the update', 'cloudinary' ); ?></button>
					<span id="connection-success" class="cld-wizard-connect-status success">
						<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected!', 'cloudinary' ); ?>
					</span>
					<span id="connection-error" class="cld-wizard-connect-status error">
						<span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Incorrect connection string', 'cloudinary' ); ?>
					</span>
					<span id="connection-working" class="cld-wizard-connect-status working">
						<span class="spinner"></span>
					</span>
				</div>
				<?php require $cloudinary->dir_path . 'php/templates/connection-string.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable ?>
			</div>
		</div>
		<div class="cld-wizard-content cld-wizard-optimize hidden" id="tab-3">
			<div class="cld-ui-title">
				<h2><?php esc_html_e( 'We collected some basic settings for you', 'cloudinary' ); ?></h2>
			</div>
			<p>
				<?php
				esc_html_e(
					'The options below help to optimize all of your assets, we recommend leaving them on for the most optimal site performance. Click next to complete your setup, the plugin will begin syncing your assets to Cloudinary and your website will start being fast, optimized and responsive.',
					'cloudinary'
				);
				?>
			</p>
			<div class="cld-wizard-optimize-settings disabled" id="optimize">
				<?php $media_library->get_component()->render( true ); ?>
				<?php $non_media->get_component()->render( true ); ?>
				<?php $advanced->get_component()->render( true ); ?>
			</div>
		</div>
		<div class="cld-wizard-content cld-wizard-complete hidden" id="tab-4" data-tab="4">
			<div class="cld-wizard-complete-icons">
				<span class="dashicons dashicons-wordpress"></span>
				<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/arrow.svg' ); ?>"/>
				<span class="dashicons dashicons-cloudinary"></span>
			</div>
			<div class="cld-ui-title">
				<h3><?php esc_html_e( "All set! We're starting to sync your files to Cloudinary", 'cloudinary' ); ?></h3>
			</div>
			<p>
				<?php
				esc_html_e(
					'You have successfully set up the Cloudinary plugin for your site, your media assets will now be optimized and delivered via fast CDN. To further configure the plugin, take a look at the various settings available.',
					'cloudinary'
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Once your assets have finished syncing, the plugin dashboard will show the impact on performance for your site.', 'cloudinary' ); ?>
			</p>
			<a id="complete-wizard" class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Go to plugin dashboard', 'cloudinary' ); ?></a>
		</div>
	</div>
	<div class="cld-ui-wrap cld-submit">
		<div class="cld-wizard-lock hidden" id="pad-lock">
			<span class="dashicons dashicons-lock" id="lock-icon"></span>
			<div> <?php esc_html_e( 'Click the lock to make changes', 'cloudinary' ); ?></div>
		</div>
		<span></span>
		<div class="cld-wizard-buttons">
			<button class="button button-primary hidden" data-navigate="back" type="button"><?php esc_html_e( 'Back', 'cloudinary' ); ?></button>
			<button class="button button-primary" data-navigate="next" type="button"><?php esc_html_e( 'Next', 'cloudinary' ); ?></button>
		</div>
	</div>
</div>
