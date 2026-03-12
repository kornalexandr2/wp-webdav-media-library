<?php
/**
 * Plugin Name:       WP WebDav Media Library
 * Description:       Independent plugin for using WebDav in WordPress Media Library.
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Version:           1.0.0
 * Author:            KiSa
 * Author URI:        https://github.com/kornalexandr2
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-webdav-media-library
 *
 * @package wp-webdav-media-library
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// do nothing if PHP-version is not 8.1 or newer.
if ( PHP_VERSION_ID < 80100 ) {
	return;
}

use KiSa\WebDavMediaLibrary\Core\Init;

/**
 * Custom PSR-4 Autoloader for Standalone Version.
 */
spl_autoload_register( function ( $class ) {
	$prefixes = array(
		'KiSa\\WebDavMediaLibrary\\' => __DIR__ . '/app/',
		'Sabre\\'                    => __DIR__ . '/vendor/sabre/dav/lib/',
		'Sabre\\HTTP\\'              => __DIR__ . '/vendor/sabre/http/lib/',
		'Sabre\\Uri\\'               => __DIR__ . '/vendor/sabre/uri/lib/',
		'Sabre\\Xml\\'               => __DIR__ . '/vendor/sabre/xml/lib/',
		'Sabre\\Event\\'             => __DIR__ . '/vendor/sabre/event/lib/',
		'Sabre\\VObject\\'           => __DIR__ . '/vendor/sabre/vobject/lib/',
	);

	foreach ( $prefixes as $prefix => $base_dir ) {
		$len = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			continue;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
} );

// Check if the library is working.
if ( ! class_exists( 'Sabre\DAV\Client' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error"><p>' . esc_html__( 'WP WebDav Media Library error: Failed to load SabreDAV classes via custom loader.', 'wp-webdav-media-library' ) . '</p></div>';
	} );
	return;
}

// get plugin-path.
const WWML_PLUGIN = __FILE__;

// set the version.
const WWML_PLUGIN_VERSION = '1.0.0';

// initialize plugin.
Init::get_instance()->init();

// Flush rewrite rules on activation.
register_activation_hook( __FILE__, function() {
	$proxy = new \KiSa\WebDavMediaLibrary\Media\Proxy();
	$proxy->add_rewrite_rules();
	flush_rewrite_rules();
} );

