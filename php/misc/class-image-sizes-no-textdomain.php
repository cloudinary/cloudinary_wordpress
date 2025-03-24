<?php
/**
 * Returns image sizes used in `class-utils.php`.
 *
 * These sizes are defined in a separate file, because the `addtextdomain`
 * task in `gruntfile.js` would otherwise try to add our textdomain to
 * them, which we don't want.
 *
 * We need these size names to use the default WordPress translations.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Misc;

/**
 * Class Image_Sizes_No_Textdomain
 */
class Image_Sizes_No_Textdomain {
	/**
	 * Get image sizes.
	 *
	 * @return array
	 */
	public static function get_image_sizes() {
		return array(
			// phpcs:disable WordPress.WP.I18n.MissingArgDomain
			'thumbnail'    => __( 'Thumbnail' ),
			'medium'       => __( 'Medium' ),
			'medium_large' => __( 'Medium Large' ),
			'large'        => __( 'Large' ),
			'full'         => __( 'Full Size' ),
			// phpcs:enable WordPress.WP.I18n.MissingArgDomain
		);
	}
}
