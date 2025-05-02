<?php
/**
 * Settings template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

$cloudinary  = get_plugin_instance();
$admin       = $cloudinary->get_component( 'admin' );
$component   = $admin->get_component();
$connected   = $cloudinary->settings->get_param( 'connected' );
$active_slug = $admin->get_param( 'active_slug' );
?>
<form method="post" novalidate="novalidate">
	<?php $admin->render_notices(); ?>
	<div class="cld-ui-wrap cld-row">
		<?php wp_nonce_field( 'cloudinary-settings', '_cld_nonce' ); ?>
		<input type="hidden" name="cloudinary-active-slug" value="<?php echo esc_attr( $active_slug ); ?>"/>
		<div class="cld-column <?php echo esc_html( 'column-' . $active_slug ); ?>">
			<?php
			$component->render( true );
			?>
		</div>
		<?php if ( ! empty( $connected ) && ! empty( $page['sidebar'] ) ) : ?>
			<div class="cld-column cld-ui-sidebar">
				<?php
				$def     = $cloudinary->settings->get_param( 'sidebar' );
				$sidebar = $this->init_components( $def, 'sidebar' );
				$sidebar->get_component()->render( true );
				?>
			</div>
		<?php endif; ?>
	</div>
</form>
