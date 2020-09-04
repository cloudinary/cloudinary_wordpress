<?php
/**
 * HTML content for the Sync Media tab.
 *
 * @package Cloudinary
 */

add_thickbox();
$autosync = $this->plugin->components['sync']->is_auto_sync_enabled();
?>
<?php if ( ! empty( $this->plugin->config['connect'] ) ) : ?>
	<div id="auto-sync-alert" style="display:none;">
		<p>
			<?php esc_html_e( 'Enabling Auto Sync will result in slower loading times, but only once if the asset was not synced to Cloudinary yet. A way to avoid this one-time latency is by initiating a Bulk-Sync by pressing the button at the bottom of this page.', 'cloudinary' ) ?>
		</p>
	</div>
	<a href="#TB_inline?&inlineId=auto-sync-alert&height=90" title="<?php esc_attr_e( 'Warning', 'cloudinary' ) ?>" id="auto-sync-alert-btn" class="thickbox"></a>
	<div class="settings-tab-section-card">
		<div class="settings-tab-section-fields-dashboard-success">
			<?php if ( true === $autosync ) : ?>
				<span class="sync-status-enabled"><?php esc_html_e( 'Auto Sync is on', 'cloudinary' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'All of your assets will be kept in-sync with Cloudinary automatically when you connect your account. Existing WordPress assets will be uploaded to the Cloudinary folder specified below and any assets that exist in the Cloudinary folder will sync back to local storage on WordPress. Auto-syncing allows all assets to be available for delivery from your WordPress Media Library in case the plugin is disabled.', 'cloudinary' ); ?><br>
				</p>
			<?php else: ?>
				<span class="sync-status-disabled"><?php esc_html_e( 'Auto Sync is off', 'cloudinary' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Only selected assets will be kept in-sync with Cloudinary when you connect your account. Selected assets will be uploaded to the Cloudinary folder specified below. Selected assets to be available for delivery from your WordPress Media Library in case the plugin is disabled.', 'cloudinary' ); ?><br>
				</p>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>
