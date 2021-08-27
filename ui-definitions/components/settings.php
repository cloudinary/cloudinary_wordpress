<?php
/**
 * Settings template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

$cloudinary = get_plugin_instance();
$admin      = $cloudinary->get_component( 'admin' );
$component  = $admin::$component;

?>
<form method="post" novalidate="novalidate">
	<div class="cld-ui-wrap cld-row">
		<?php wp_nonce_field( 'cloudinary-settings', '_wpnonce' ); ?>
		<div class="cld-column">
			<?php
			$component->render( true );
			?>
		</div>
		<?php if ( ! empty( $page['sidebar'] ) ) : ?>
			<div class="cld-column cld-ui-accordion">
				<?php
				$settings = get_plugin_instance()->settings;
				$def      = $settings->get_param( 'sidebar' );
				$sidebar  = $this->init_settings( $def, 'sidebar' );
				$sidebar->get_component()->render( true );
				?>
			</div>
		<?php endif; ?>
	</div>
</form>
