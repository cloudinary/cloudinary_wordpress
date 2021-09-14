<?php
/**
 * Settings template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

$cloudinary = get_plugin_instance();
?>
<div class="wrap cld-ui-wrap cld-wizard" id="cloudinary-settings-page">
	<h1><?php esc_html_e( 'Cloudinary', 'cloudinary' ); ?></h1>
	<div class="cld-ui-header cld-panel-heading cld-panel">
		<span class="cld-icon">
			<img src="<?php echo esc_url( $cloudinary->dir_url . 'css/images/logo-icon.svg' ); ?>" width="56px"/>
		</span>
		<div class="cld-wizard-tabs">
			<div class="cld-wizard-tabs-tab complete">
				<span class="cld-wizard-tabs-tab-count">1</span>
				<?php esc_html_e( 'Welcome to Cloudinary', 'cloudinary' ); ?>
			</div>
			<div class="cld-wizard-tabs-tab active">
				<span class="cld-wizard-tabs-tab-count">2</span>
				<?php esc_html_e( 'Connect Plugin', 'cloudinary' ); ?>
			</div>
			<div class="cld-wizard-tabs-tab">
				<span class="cld-wizard-tabs-tab-count">3</span>
				<?php esc_html_e( 'Recommended Settings', 'cloudinary' ); ?>
			</div>
		</div>
		<div></div>
	</div>
	<div class="cld-ui-wrap has-heading cld-panel">

	</div>
	<div class="cld-ui-wrap cld-submit">
		<button class="button button-primary" type="button"><?php esc_html_e( 'Next', 'cloudinary' ); ?></button>
	</div>
</div>
