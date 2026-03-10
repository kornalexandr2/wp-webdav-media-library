<?php
/**
 * Tasks to run during uninstallation of this plugin.
 *
 * @package wp-webdav-media-library
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Clean up options.
delete_option( 'wwml_provider' );
delete_option( 'wwml_server' );
delete_option( 'wwml_login' );
delete_option( 'wwml_password' );
delete_option( 'wwml_path' );

// Also delete old options if they exist.
delete_option( 'eml_webdav_provider' );
delete_option( 'eml_webdav_server' );
delete_option( 'eml_webdav_login' );
delete_option( 'eml_webdav_password' );
delete_option( 'eml_webdav_path' );
