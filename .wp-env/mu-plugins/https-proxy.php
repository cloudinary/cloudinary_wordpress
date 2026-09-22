<?php
/**
 * HTTPS proxy awareness — local dev helper mu-plugin.
 *
 * The local environment terminates TLS in an nginx container that forwards to
 * wp-env over plain HTTP (see .wp-env/proxy/). Apache therefore sees an HTTP
 * request, and without this mu-plugin `is_ssl()` returns false even though the
 * browser is on HTTPS. That mismatch causes an admin redirect loop with
 * FORCE_SSL_ADMIN enabled, drops the `secure` flag from auth cookies, and makes
 * the plugin's own scheme checks take the wrong branch:
 *
 *   - php/class-delivery.php builds delivery URLs from `is_ssl()`.
 *   - php/class-media.php sets the cookie `secure` flag from `is_ssl()`.
 *
 * The fix is the standard reverse-proxy one: promote the forwarded scheme onto
 * $_SERVER before WordPress reads it. On a real host this would live in
 * wp-config.php, but wp-env generates that file and rewrites it on every start,
 * so it goes here instead. mu-plugins load from wp-settings.php before
 * wp_cookie_constants() and wp_ssl_constants() run, which is early enough for
 * both to see the corrected value.
 *
 * Only trusted because the proxy is the sole route into these containers in
 * local development. Never ship this pattern to production without pinning the
 * trusted proxy address.
 *
 * @package Cloudinary
 */

defined( 'ABSPATH' ) || exit;

if (
	isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
	'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) )
) {
	$_SERVER['HTTPS'] = 'on';

	// Keep SERVER_PORT consistent with the scheme. WordPress appends a non
	// standard port to generated URLs, and the unforwarded 80 here would
	// produce links such as https://example.test:80/.
	if ( isset( $_SERVER['HTTP_X_FORWARDED_PORT'] ) ) {
		$_SERVER['SERVER_PORT'] = absint( $_SERVER['HTTP_X_FORWARDED_PORT'] );
	}
}

/**
 * Removes the wp-env port from a site URL.
 *
 * The wp-env tool appends its published port to WP_HOME and WP_SITEURL when it
 * writes wp-config.php, and there is no configuration option to prevent it.
 * See postProcessConfig() in @wordpress/env/lib/config/post-process-config.js.
 *
 * The proxy serves the site on the standard HTTPS port, so the appended 8888 or
 * 8889 is wrong: it leaks into redirects, canonical URLs, asset URLs and the
 * cookie path. Stripping it here is the last chance to correct the value,
 * because the constants are already defined by the time mu-plugins load.
 *
 * The port to remove is not hard-coded, because wp-env lets developers change
 * it through WP_ENV_PORT or a "port" key in the config files. The origin is
 * taken from WP_CONTENT_URL instead, which .wp-env.json defines as the intended
 * public URL and which wp-env never rewrites. Any port on the incoming URL is
 * then replaced with whatever that constant says, so a custom wp-env port is
 * handled without this file knowing about it.
 *
 * This filter cannot fix asset URLs. wp_plugin_directory_constants() defines
 * WP_CONTENT_URL and WP_PLUGIN_URL from get_option( 'siteurl' ) at
 * wp-settings.php line 497, ten lines before mu-plugins load, so plugin CSS and
 * JS would keep the port. Those two constants are therefore set explicitly in
 * .wp-env.json, where wp-env leaves them alone because it only rewrites
 * WP_HOME, WP_SITEURL and WP_TESTS_DOMAIN.
 *
 * @param string $url The home or site URL.
 * @return string The URL with the wp-env port replaced by the public origin.
 */
function cld_strip_wp_env_port( $url ) {
	if ( ! is_string( $url ) || ! defined( 'WP_CONTENT_URL' ) ) {
		return $url;
	}

	$origin = wp_parse_url( WP_CONTENT_URL );

	if ( empty( $origin['scheme'] ) || empty( $origin['host'] ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );

	// Only rewrite URLs that point at the same host, so an unrelated URL
	// passing through these filters is left alone.
	if ( empty( $parts['host'] ) || $parts['host'] !== $origin['host'] ) {
		return $url;
	}

	$public = $origin['scheme'] . '://' . $origin['host'];

	if ( ! empty( $origin['port'] ) ) {
		$public .= ':' . $origin['port'];
	}

	$path = isset( $parts['path'] ) ? $parts['path'] : '';

	return $public . $path;
}

add_filter( 'option_home', 'cld_strip_wp_env_port', 20 );
add_filter( 'option_siteurl', 'cld_strip_wp_env_port', 20 );
