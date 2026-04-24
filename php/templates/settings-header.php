<?php
/**
 * Settings Page header template.
 *
 * @package Cloudinary
 */

$current_tab = $this->active_tab();

$setting_slug = $this->setting_slug();

// Push notices if found. ye I know, errors.. sigh.
settings_errors( $setting_slug );
?>
<div class="wrap">
	<h1><?php echo esc_html( $this->ui['page_title'] ); ?></h1>
	<?php if ( ! empty( $current_tab ) ) : ?>
	<form method="post" action="options.php" novalidate="novalidate" class="render-trigger cld-settings-form" data-event="tabs.init" data-cld-settings-form="true">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>"/>
		<?php settings_fields( $setting_slug ); ?>
		<?php endif; ?>
