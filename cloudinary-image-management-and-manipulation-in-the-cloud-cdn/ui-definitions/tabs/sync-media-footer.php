<?php
/**
 * HTML footer for the Sync Media tab.
 *
 * @package Cloudinary
 */

// Reset if stopped to maintain current state.
if ( ! $this->plugin->components['sync']->managers['queue']->is_running() ) {
	// Rebuild on load.
	$this->plugin->components['sync']->managers['queue']->build_queue();
} else {
	// Check and confirm queue is in fact valid.
	$this->plugin->components['sync']->managers['queue']->validate_queue();
}


?>
<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'cloudinary' ); ?></button>
<hr/>
<div class="sync settings-tab-section">
	<h2><?php esc_html_e( 'Bulk-Sync WordPress Media with Cloudinary (optional)', 'cloudinary' ); ?></h2>

	<?php if ( $this->plugin->config['connect'] ) : ?>

		<p>
			<?php esc_html_e( "Bulk Sync is a one-time operation that ensures that the Cloudinary Media Library is up-to-date with the WordPress Media Library by manually pushing all media that was stored in your WordPress Media Library prior to activation of the Cloudinary plugin. Please note that there is a limit of 1000 images at a time so your server doesn't get overloaded.", 'cloudinary' ); ?>
		</p>

		<button type="button" class="button button-hero stop-sync" id="stop-sync"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Stop Sync', 'cloudinary' ); ?></button>
		<button type="submit" name="submit" id="submit" class="button button-hero start-sync">
			<span class="dashicons dashicons-update-alt"></span> <?php esc_html_e( 'Sync all media to Cloudinary', 'cloudinary' ); ?>
		</button>
		<span id="progress-wrapper" class="sync-media-progress">
			<span class="progress-text">
				<img src="<?php echo esc_url( CLDN_URL . 'css/loading.svg' ); ?>" alt="<?php esc_attr_e( 'Syncing…', 'cloudinary' ); ?>">
				<?php esc_html_e( 'Syncing…', 'cloudinary' ); ?>
			</span>
		</span>
		<div class="settings-tab-section-card completed" id="completed-notice">
			<div class="settings-tab-section-fields-dashboard-success">
				<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'All assets are synced', 'cloudinary' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div id="setting-error-connection" class="settings-error render-trigger" data-event="sync.error">
			<p>
				<strong><?php esc_html_e( 'You must set up your Cloudinary connection before running Bulk Sync.', 'cloudinary' ); ?></strong>
			</p>
		</div>
	<?php endif; ?>
</div>
