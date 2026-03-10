<?php
/**
 * URL Rewriting and Proxy Streaming.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Media;

defined( 'ABSPATH' ) || exit;

class Proxy {

	public function init(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_proxy_request' ) );
	}

	public function add_rewrite_rules(): void {
		$slug = get_option( 'wwml_proxy_slug', 'remote-media' );
		add_rewrite_rule(
			'^' . preg_quote( $slug ) . '/(.+)',
			'index.php?wwml_remote_file=$matches[1]',
			'top'
		);
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'wwml_remote_file';
		return $vars;
	}

	public function handle_proxy_request(): void {
		$remote_path = get_query_var( 'wwml_remote_file' );
		if ( empty( $remote_path ) ) {
			return;
		}

		$client_wrapper = new WebDavClient();
		if ( ! $client_wrapper->is_configured() ) {
			wp_die( 'Plugin not configured' );
		}

		// Reconstruct original URL from path
		$domain = $client_wrapper->get_domain();
		$full_url = $domain . '/' . ltrim( $remote_path, '/' );
		
		try {
			$client = $client_wrapper->get_client();
			$response = $client->request( 'GET', '/' . ltrim( $remote_path, '/' ) );

			if ( 200 !== $response['statusCode'] ) {
				status_header( 404 );
				wp_die( 'File not found on remote server' );
			}

			// Stream headers
			foreach ( $response['headers'] as $name => $values ) {
				foreach ( $values as $value ) {
					header( "$name: $value" );
				}
			}

			// Stream content
			echo $response['body'];
			exit;

		} catch ( \Exception $e ) {
			wp_die( $e->getMessage() );
		}
	}
}
