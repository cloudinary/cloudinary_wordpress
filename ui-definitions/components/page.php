<?php
/**
 * The Admin page template.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

$cloudinary = get_plugin_instance();
?>
<div class="cld-ui-wrap cld-page cld-settings" id="cloudinary-settings-page">
	<?php require CLDN_PATH . 'ui-definitions/components/header.php'; ?>
	<?php require CLDN_PATH . 'ui-definitions/components/settings.php'; ?>
</div>
