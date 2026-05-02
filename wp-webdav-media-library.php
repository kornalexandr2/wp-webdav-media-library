<?php
/**
 * Plugin Name:       WP WebDav Media Library
 * Description:       Independent plugin for using WebDav in WordPress Media Library.
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Version:           1.0.2
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
 * Autoloader for Standalone Version.
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Manually load function files that are sometimes missed by simple autoloaders if they are not in vendor/autoload.php (though they should be).
// For SabreDAV, these are already handled by Composer autoloader, but we keep the check for safety if needed.
// However, with vendor/autoload.php, they are definitely handled.

// Check if the library is working.
if ( ! class_exists( 'Sabre\DAV\Client' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error"><p>' . esc_html__( 'WP WebDav Media Library error: Failed to load SabreDAV classes. Please ensure the "vendor" directory exists and is complete.', 'wp-webdav-media-library' ) . '</p></div>';
	} );
	return;
}

// get plugin-path.
const WWML_PLUGIN = __FILE__;

// set the version.
const WWML_PLUGIN_VERSION = '1.0.2';

// initialize plugin.
Init::get_instance()->init();

// Flush rewrite rules on activation.
register_activation_hook( __FILE__, function() {
	$proxy = new \KiSa\WebDavMediaLibrary\Media\Proxy();
	$proxy->add_rewrite_rules();
	flush_rewrite_rules();
} );

