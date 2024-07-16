<?php
/**
 * Get Connection String html content for the connect tab.
 *
 * @package Cloudinary
 */

$dir_url = Cloudinary\get_plugin_instance()->dir_url;
?>

<div class="cld-wizard-connect-help">
	<div class="cld-wizard-connect-help-text">
		<h2><?php esc_html_e( 'Where to find my Connection string?', 'cloudinary' ); ?></h2>
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
			<li><?php esc_html_e( 'Open your Cloudinary Console.', 'cloudinary' ); ?></li>
			<li>
				<?php
				printf(
					// translators: %1$s and %2$s are the opening and close the anchor tag for the API Keys page.
					esc_html__( 'Copy the API environment variable format from the %1$sAPI Keys page%2$s.', 'cloudinary' ),
					'<a href="https://console.cloudinary.com/settings/api-keys" target=_blank" title="' . esc_attr__( 'Get your API Keys', 'cloudinary' ) . '">',
					'</a>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					// translators: %1$s and %2$s are placeholders for code tags. %3$s is a line break.
					esc_html__( 'Replace %1$s<your_api_key>%2$s and %1$s<your_api_secret>%2$s with your actual values.%3$sYour cloud name is already correctly included in the format.', 'cloudinary' ),
					'<code>',
					'</code>',
					'<br>'
				);
				?>
			</li>
		</ol>
	</div>
	<div class="cld-wizard-connect-help-image">
		<img src="<?php echo esc_url( $dir_url ); ?>css/images/connection-string.png" alt="<?php esc_attr_e( 'Where the connection string can be found on the cloudinary.com console.', 'cloudinary' ); ?>" class="img-connection-string" />
	</div>
</div>
