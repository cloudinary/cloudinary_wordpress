<?php
/**
 * Cloudinary CLI for VIP.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Traits\CLI_Trait;

/**
 * CLI VIP class.
 *
 * @since   2.5.1
 */
class CLI_VIP extends \WPCOM_VIP_CLI_Command {

	use CLI_Trait;

	/**
	 * Output the Intro.
	 *
	 * @since   2.5.1
	 * @link    http://patorjk.com/software/taag/#p=display&c=echo&f=Calvin%20S&t=Cloudinary%20CLI
	 */
	protected function do_intro() {
		static $intro;
		if ( ! $intro ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( '╔═╗┬  ┌─┐┬ ┬┌┬┐┬┌┐┌┌─┐┬─┐┬ ┬  ╔═╗╦  ╦  ╦  ╦╦╔═╗' );
			\WP_CLI::log( '║  │  │ ││ │ ││││││├─┤├┬┘└┬┘  ║  ║  ║  ╚╗╔╝║╠═╝' );
			\WP_CLI::log( '╚═╝┴─┘└─┘└─┘─┴┘┴┘└┘┴ ┴┴└─ ┴   ╚═╝╩═╝╩   ╚╝ ╩╩  ' );
			$intro = true;
		}
	}
}
