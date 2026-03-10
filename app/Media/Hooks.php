<?php
/**
 * Hooks to replace local URLs with remote ones.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Media;

defined( 'ABSPATH' ) || exit;

class Hooks {

	public function init(): void {
		add_filter( 'wp_get_attachment_url', array( $this, 'replace_attachment_url' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'prevent_image_resizing' ), 10, 3 );
	}

	/**
	 * Replace the local URL with our proxied URL for WebDAV files.
	 */
	public function replace_attachment_url( string $url, int $post_id ): string {
		$remote_url = get_post_meta( $post_id, '_wwml_remote_url', true );
		if ( empty( $remote_url ) ) {
			return $url;
		}

		$slug = get_option( 'wwml_proxy_slug', 'remote-media' );
		$parse = wp_parse_url( $remote_url );
		$path = $parse['path'] ?? '';
		
		// We want to serve it via our site URL
		return home_url( '/' . $slug . $path );
	}

	/**
	 * Prevent WordPress from trying to find local thumbnails for remote images.
	 */
	public function prevent_image_resizing( $out, int $id, $size ) {
		$remote_url = get_post_meta( $id, '_wwml_remote_url', true );
		if ( empty( $remote_url ) ) {
			return $out;
		}

		// Return the full proxied URL instead of thumbnails
		return array(
			$this->replace_attachment_url( '', $id ),
			0, // width (unknown)
			0, // height (unknown)
			false
		);
	}
}
