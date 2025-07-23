<?php
/**
 * Lock_File class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Cron;

use Cloudinary\Cron;

/**
 * Class Lock_File
 */
class Lock_File {

	/**
	 * Holds the folder in uploads where lock files are kept.
	 */
	const CRON_LOCK_LOCATION = 'cloudinary';

	/**
	 * Holds the meta key for the cron lock file.
	 */
	const CRON_LOCK_KEY = 'cloudinary_cron_lock_file';

	/**
	 * Get a lock data.
	 *
	 * @param string|null $file The lock name.
	 *
	 * @return false|mixed|string
	 */
	public function get_lock_file( $file = null ) {
		$lock_file = $this->get_lock_file_name( $file );

		if ( ! file_exists( $lock_file ) ) {
			return '';
		}

		$data = file_get_contents( $lock_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

		if ( false !== strpos( $data, '[' ) ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}

	/**
	 * Get the lock name.
	 *
	 * @param string|null $file_name The name of the transient.
	 *
	 * @return mixed|string|null
	 */
	public function get_lock_file_name( $file_name = null ) {
		if ( ! $file_name ) {
			$file_name = 'cron-run';
		}
		$base = wp_upload_dir( 'cron' )['path']; // This will create a folder called 'cron'.

		return path_join( $base, $file_name . '.txt' );
	}

	/**
	 * Check if a lock is in place.
	 *
	 * @param string|null $file The lock name.
	 *
	 * @return bool
	 */
	public function has_lock_file( $file = null ) {
		$file   = $this->get_lock_file_name( $file );
		$return = file_exists( $file );
		if ( $return ) {
			$filedate = filemtime( $file );
			$compare  = time() - $filedate;
			if ( $compare > Cron::$daemon_watcher_interval ) {
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Set a lock.
	 *
	 * @param string|null $file The name to set.
	 * @param mixed       $data The data to set.
	 *
	 * @return mixed|string
	 */
	public function set_lock_file( $file = null, $data = null ) {
		$time = time();
		$bits = $data ? $data : uniqid( $time );
		if ( ! $this->has_lock_file( $file ) ) {
			$file_path = $this->get_lock_file_name( $file );
			file_put_contents( $file_path, $bits ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		}

		return $bits;
	}

	/**
	 * Delete a lock.
	 *
	 * @param string|null $file The name to set.
	 */
	public function delete_lock_file( $file = null ) {
		$file = $this->get_lock_file_name( $file );
		wp_delete_file( $file );
	}
}
