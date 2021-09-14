<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\Assets;
use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;
use Cloudinary\Settings\Setting;

/**
 * HTML Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Asset extends Panel {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|header/|tbody|rows/|/tbody|/wrap';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	protected static $capture = true;

	/**
	 * Holds the assets instance.
	 *
	 * @var Assets
	 */
	protected $assets;

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {
		$struct['element']             = 'table';
		$struct['attributes']['class'] = array(
			'widefat',
			'striped',
			'cld-table',
		);

		return $struct;
	}

	/**
	 * Filter the header parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function header( $struct ) {
		$struct['element']                       = 'thead';
		$struct['children']['item']              = $this->get_part( 'th' );
		$struct['children']['item']['content']   = $this->setting->get_param( 'title' );
		$struct['children']['action']            = $this->get_part( 'th' );
		$struct['children']['action']['content'] = $this->setting->get_param( 'title' );

		return $struct;
	}

	/**
	 * Filter the row parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function rows( $struct ) {

		foreach ( $this->setting->get_settings() as $child ) {
			$struct['children'][ $child->get_slug() ] = $this->get_item_row( $child );
		}

		return $struct;
	}

	/**
	 * Get an item row.
	 *
	 * @param Setting $item The setting.
	 *
	 * @return array
	 */
	protected function get_item_row( $item ) {
		$row                                  = $this->get_part( 'tr' );
		$row['children']['item']              = $this->get_part( 'td' );
		$row['children']['item']['content']   = $item->get_param( 'title' );
		$row['children']['action']            = $this->get_part( 'td' );
		$row['children']['action']['content'] = $item->get_slug();

		return $row;
	}

	/**
	 * Register table structures as components.
	 */
	public function setup() {
		$this->assets = get_plugin_instance()->get_component( 'assets' );
		$this->setting->set_param( 'collapse', 'closed' );
		parent::setup();
	}

	/**
	 * Setup action before rendering.
	 */
	public function srender() {
		ob_start();
		?>
		<table class="widefat striped cld-table">
			<thead>
			<tr>
				<th class="cld-table-th">
			<span class="cld-on-off">
				<div class="cld-input cld-input-on-off">
					<label class="cld-input-on-off-control">
						<input type="hidden" name="cloudinary_main_cache_page[plugin_files_title]" value="off">
						<input type="checkbox" class="cld-ui-input cld-ui-input" name="cloudinary_main_cache_page[plugin_files_title]" id="plugin_files_title" value="on" data-controller="plugin_files_title" checked="checked" data-main="[&quot;cache_all_plugins&quot;]" data-disabled="false">
						<span class="cld-input-on-off-control-slider" style="">
							<i class="icon-on dashicons ">

							</i>
							<i class="icon-off dashicons ">

							</i>
						</span>
					</label>
					<label class="cld-ui-description description" for="plugin_files_title">Plugin</label>
				</div>
			</span>
				</th>
				<th style="text-align:right;">

				</th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td colspan="1">
			<span class="cld-on-off">
				<div class="cld-input cld-input-on-off">
					<label class="cld-input-on-off-control">
						<input type="hidden" name="cloudinary_main_cache_page[query-monitorquery-monitor.php]" value="off">
						<input type="checkbox" class="cld-ui-input cld-ui-input" data-bind-trigger="query-monitorquery-monitor.php" name="cloudinary_main_cache_page[query-monitorquery-monitor.php]" id="query-monitorquery-monitor.php" value="on" data-controller="query-monitorquery-monitor.php" checked="checked" data-main="[&quot;plugin_files_title&quot;]" data-disabled="false">
						<span class="cld-input-on-off-control-slider" style="">
							<i class="icon-on dashicons ">

							</i>
							<i class="icon-off dashicons ">

							</i>
						</span>
					</label>
				</div>
			</span>
					<span class="cld-icon-toggle cld-ui-conditional open" data-condition="{&quot;query-monitorquery-monitor.php&quot;:true}">
				<div class="cld-input cld-input-icon-toggle">
					<label class="description left" for="toggle_query-monitorquery-monitor.php">Query Monitor</label>
					<label class="cld-input-icon-toggle-control">
						<input type="hidden" name="cloudinary_main_cache_page[toggle_query-monitorquery-monitor.php]" value="off">
						<input type="checkbox" class="cld-ui-input cld-ui-input" name="cloudinary_main_cache_page[toggle_query-monitorquery-monitor.php]" id="toggle_query-monitorquery-monitor.php" value="on" data-controller="toggle_query-monitorquery-monitor.php" data-disabled="false">
						<i class="cld-input-icon-toggle-control-slider" style="">
							<i class="icon-on dashicons dashicons-arrow-up">

							</i>
							<i class="icon-off dashicons dashicons-arrow-down">

							</i>
						</i>
					</label>
				</div>
			</span>
					<span class="cld-icon-toggle cld-ui-conditional closed" data-condition="{&quot;query-monitorquery-monitor.php&quot;:false}">
				<div class="cld-input cld-input-icon-toggle">
					<label class="description left" for="off_query-monitorquery-monitor.php">Query Monitor</label>
					<label class="cld-input-icon-toggle-control">
						<input type="hidden" name="cloudinary_main_cache_page[off_query-monitorquery-monitor.php]" value="off">
						<input type="checkbox" class="cld-ui-input cld-ui-input" name="cloudinary_main_cache_page[off_query-monitorquery-monitor.php]" id="off_query-monitorquery-monitor.php" value="on" data-controller="off_query-monitorquery-monitor.php" data-disabled="false">
						<i class="cld-input-icon-toggle-control-slider" style="">
							<i class="icon-on dashicons  "></i>
							<i class="icon-off dashicons  "></i>
						</i>
					</label>
				</div>
			</span>
					<span id="name_query-monitorquery-monitor.php_size_wrapper" class="file-size small">

			</span>
				</td>
				<td style="text-align:right;height:26px;" colspan="1">
					<div class="cld-ui-wrap cld-button">
						<button type="button" id="apply_query-monitorquery-monitor.php" class="button button-primary closed" style="float: right; margin-left: 6px;">Apply changes</button>
					</div>
				</td>
			</tr>
			</tbody>
		</table>

		<?php
		return ob_get_clean();
	}
}
