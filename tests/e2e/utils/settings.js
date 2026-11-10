/**
 * Read and write plugin settings from e2e specs.
 *
 * Goes through the plugin's settings API rather than `wp option patch`, so
 * the values land in the same place the settings screen saves them.
 */

const { wpEvalFile } = require( './wizard' );

/**
 * Encode a value as a PHP expression that decodes it back.
 *
 * The JSON is base64-encoded, so quotes and backslashes in the values
 * cannot break out of the PHP string.
 *
 * @param {unknown} value Any JSON-serializable value.
 * @return {string} PHP expression.
 */
function phpJson( value ) {
	const encoded = Buffer.from( JSON.stringify( value ) ).toString( 'base64' );
	return `json_decode( base64_decode( '${ encoded }' ), true )`;
}

/**
 * Save plugin settings and return their previous values.
 *
 * @param {Record<string, string|number>} values Setting slug to value.
 * @return {Record<string, string|number>} Setting slug to the value before the write.
 */
function saveSettings( values ) {
	return JSON.parse(
		wpEvalFile( `
			$settings = get_plugin_instance()->settings;
			$previous = array();
			foreach ( ${ phpJson( values ) } as $slug => $value ) {
				$setting           = $settings->get_setting( $slug );
				$previous[ $slug ] = $setting->get_value();
				$setting->save_value( $value );
			}
			echo wp_json_encode( $previous );
		` )
	);
}

/**
 * Read the current value of plugin settings.
 *
 * @param {string[]} slugs Setting slugs.
 * @return {Record<string, string|number>} Setting slug to value.
 */
function readSettings( slugs ) {
	return JSON.parse(
		wpEvalFile( `
			$settings = get_plugin_instance()->settings;
			$values   = array();
			foreach ( ${ phpJson( slugs ) } as $slug ) {
				$values[ $slug ] = $settings->get_setting( $slug )->get_value();
			}
			echo wp_json_encode( $values );
		` )
	);
}

module.exports = {
	phpJson,
	readSettings,
	saveSettings,
};
