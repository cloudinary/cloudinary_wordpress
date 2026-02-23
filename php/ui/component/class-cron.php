<?php
/**
 * Cron control
 * UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Cron extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|input/|/wrap';

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct['element']           = 'div';
		$table                       = $this->make_table();
		$struct['children']['table'] = $table;

		return $struct;
	}

	/**
	 * Make the Table that holds the tasks.
	 *
	 * @return array
	 */
	protected function make_table() {
		$table                        = $this->get_part( 'table' );
		$table['attributes']['class'] = array(
			'widefat',
			'striped',
			'cld-table',
		);
		// Add Headers.
		$head     = $this->get_part( 'thead' );
		$head_row = $this->get_part( 'tr' );
		// task head.
		$task_head            = $this->get_part( 'th' );
		$task_head['content'] = __( 'Task', 'cloudinary' );
		// Last run.
		$last_run            = $this->get_part( 'th' );
		$last_run['content'] = __( 'Last run', 'cloudinary' );
		// Next run.
		$next_run            = $this->get_part( 'th' );
		$next_run['content'] = __( 'Next run', 'cloudinary' );

		// Add columns.
		$head_row['children']['task'] = $task_head;
		$head_row['children']['last'] = $last_run;
		$head_row['children']['next'] = $next_run;

		// Add row to header.
		$head['children']['row'] = $head_row;

		// Add header to table.
		$table['children']['head'] = $head;

		// Add task rows.
		$table['children']['task_list'] = $this->make_rows();

		return $table;
	}

	/**
	 * Make the Rows to populate the table.
	 *
	 * @return array
	 */
	protected function make_rows() {

		$body     = $this->get_part( 'tbody' );
		$cron     = $this->setting->get_param( 'cron' );
		$schedule = $cron->get_schedule();
		foreach ( $schedule as $name => $item ) {
			$slug                      = sanitize_title( $name );
			$child                     = $this->make_row( $name, $item );
			$body['children'][ $slug ] = $child;
		}

		return $body;
	}

	/**
	 * Make a single task row.
	 *
	 * @param string $name Task name.
	 * @param array  $item Task details.
	 *
	 * @return array
	 */
	protected function make_row( $name, $item ) {

		// Add Task control.
		$tr        = $this->get_part( 'tr' );
		$task      = $this->get_part( 'td' );
		$slug      = sanitize_title( $name );
		$setting   = $this->setting->get_setting( $slug );
		$main_slug = $this->setting->get_root_setting()->get_setting( 'enable_cron' )->get_slug();
		if ( null === $setting->get_value() ) {
			$setting->set_value( 'on' );
		}
		$setting->set_param( 'description', $name );
		$setting->set_param( 'main', array( $main_slug ) );
		$field                  = new On_Off( $setting );
		$task['content']        = $field->render();
		$tr['children']['task'] = $task;

		// Add last run.
		$last_run            = $this->get_part( 'td' );
		$last_run['content'] = __( 'Waiting to start', 'cloudinary' );
		if ( $item['last_run'] ) {
			$last_run['content'] = sprintf( '%s ago', human_time_diff( $item['last_run'], time() ) );
		}
		$tr['children']['last_run'] = $last_run;

		// Add next run.
		$next_run                   = $this->get_part( 'td' );
		$next_run['content']        = 'on' === $field->get_value() ? sprintf( 'in %s', human_time_diff( $item['next_run'], time() ) ) : '';
		$tr['children']['next_run'] = $next_run;

		return $tr;
	}

	/**
	 * Sanitize the value.
	 *
	 * @param array $value The value to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_value( $value ) {

		return array_map( 'sanitize_text_field', (array) $value );
	}
}
