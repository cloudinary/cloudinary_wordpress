<?php
/**
 * The Media Library page template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

?>
<div class="cld-ui-wrap cld-page cld-settings" id="cloudinary-settings-page">
	<?php require CLDN_PATH . 'ui-definitions/components/header.php'; ?>
	<div id="cloudinary-dam" class="cloudinary-widget-wrapper media-library">
		<div id="import-status" class="cld-import">
			<h4><?php esc_html_e( 'Importing', 'cloudinary' ); ?></h4>
		</div>
	</div>
</div>
