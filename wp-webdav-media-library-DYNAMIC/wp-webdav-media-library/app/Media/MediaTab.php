<?php
/**
 * Media Library Backbone Tab Integration.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Media;

defined( 'ABSPATH' ) || exit;

class MediaTab {

	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );
	}

	public function enqueue_media_scripts(): void {
		if ( ! did_action( 'wp_enqueue_media' ) ) {
			return;
		}

		wp_enqueue_script(
			'wwml-media-tab',
			plugin_dir_url( WWML_PLUGIN ) . 'assets/js/media-webdav.js',
			array( 'jquery', 'media-views', 'media-models' ),
			WWML_PLUGIN_VERSION,
			true
		);

		wp_enqueue_style(
			'wwml-media-style',
			plugin_dir_url( WWML_PLUGIN ) . 'assets/css/media-webdav.css',
			array(),
			WWML_PLUGIN_VERSION
		);

		wp_localize_script( 'wwml-media-tab', 'wwml_media', array(
			'nonce'        => wp_create_nonce( 'wwml_media_nonce' ),
			'tab_title'    => __( 'WebDAV Library', 'wp-webdav-media-library' ),
			'loading'      => __( 'Loading...', 'wp-webdav-media-library' ),
			'importing'    => __( 'Importing...', 'wp-webdav-media-library' ),
			'error'        => __( 'An error occurred.', 'wp-webdav-media-library' ),
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
		) );
	}
}
