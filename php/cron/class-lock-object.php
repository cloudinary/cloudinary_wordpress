<?php
/**
 * Lock_Object class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Cron;

use Cloudinary\Cron;

/**
 * Class Lock_Object
 */
class Lock_Object extends Lock_File {

	/**
	 * Holds the prefix of the transient.
	 */
	const PREFIX = 'cld_';

	/**
	 * Get a lock data.
	 *
	 * @param string|null $file The lock name.
	 *
	 * @return false|mixed|string
	 */
	public function get_lock_file( $file = null ) {

		return get_transient( self::PREFIX . $this->get_lock_file_name( $file ) );
	}

	/**
	 * Get the lock name.
	 *
	 * @param string|null $file_name The name of the transient.
	 *
	 * @return mixed|string|null
	 */
	public function get_lock_file_name( $file_name = null ) {
		if ( null === $file_name ) {
			$file_name = 'cron-run';
		}

		return $file_name;
	}

	/**
	 * Check if a lock is in place.
	 *
	 * @param string|null $file The lock name.
	 *
	 * @return bool
	 */
	public function has_lock_file( $file = null ) {
		return ! empty( $this->get_lock_file( $file ) );
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
		$bits = $data ? json_decode( $data, true ) : uniqid( $time );
		if ( ! $this->has_lock_file( $file ) ) {
			set_transient( self::PREFIX . $this->get_lock_file_name( $file ), $bits, Cron::$daemon_watcher_interval );
		}

		return $bits;
	}

	/**
	 * Delete a lock.
	 *
	 * @param string|null $file The name to set.
	 */
	public function delete_lock_file( $file = null ) {
		delete_transient( self::PREFIX . $this->get_lock_file_name( $file ) );
	}
}
