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
		add_action( 'admin_init', array( $this, 'maybe_flush_rules' ) );
	}

	public function maybe_flush_rules(): void {
		if ( get_option( 'wwml_flush_rewrite' ) ) {
			$this->add_rewrite_rules();
			flush_rewrite_rules();
			delete_option( 'wwml_flush_rewrite' );
		}
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

		$remote_path = '/' . ltrim( rawurldecode( (string) $remote_path ), '/' );
		if ( ! $client_wrapper->is_path_allowed( $remote_path ) ) {
			status_header( 404 );
			wp_die( 'File not found' );
		}

		$this->stream_remote_file( $client_wrapper->get_remote_url( $remote_path ) );
		exit;
	}

	private function stream_remote_file( string $remote_url ): void {
		if ( ! function_exists( 'curl_init' ) ) {
			status_header( 500 );
			wp_die( 'cURL is required for remote file streaming' );
		}

		$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			status_header( 405 );
			header( 'Allow: GET, HEAD' );
			wp_die( 'Method not allowed' );
		}

		$login    = get_option( 'wwml_login', '' );
		$password = get_option( 'wwml_password', '' );
		if ( empty( $login ) || empty( $password ) ) {
			status_header( 500 );
			wp_die( 'Plugin not configured' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$request_headers = array();
		$range_header    = $this->get_valid_range_header();
		if ( ! empty( $range_header ) ) {
			$request_headers[] = 'Range: ' . $range_header;
		}

		if ( ! empty( $_SERVER['HTTP_IF_RANGE'] ) ) {
			$request_headers[] = 'If-Range: ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_RANGE'] ) );
		}

		$response_state = array(
			'status'  => 0,
			'headers' => array(),
			'sent'    => false,
		);

		$send_headers = function() use ( &$response_state ): void {
			if ( $response_state['sent'] ) {
				return;
			}

			$status = $response_state['status'] ?: 200;
			status_header( $status );

			foreach ( $this->get_forwarded_headers() as $header_name ) {
				if ( empty( $response_state['headers'][ $header_name ] ) ) {
					continue;
				}

				foreach ( $response_state['headers'][ $header_name ] as $header_value ) {
					header( $this->format_header_name( $header_name ) . ': ' . $header_value, false );
				}
			}

			if ( empty( $response_state['headers']['accept-ranges'] ) ) {
				header( 'Accept-Ranges: bytes' );
			}

			header( 'X-Content-Type-Options: nosniff' );
			$response_state['sent'] = true;
			$this->end_output_buffers();
		};

		$curl = curl_init( $remote_url );
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_HEADER         => false,
				CURLOPT_HEADERFUNCTION => function( $curl_handle, string $header_line ) use ( &$response_state ): int {
					$length = strlen( $header_line );
					$line   = trim( $header_line );

					if ( '' === $line ) {
						return $length;
					}

					if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $line, $matches ) ) {
						$response_state['status']  = (int) $matches[1];
						$response_state['headers'] = array();

						return $length;
					}

					if ( str_contains( $line, ':' ) ) {
						list( $name, $value ) = explode( ':', $line, 2 );
						$name = strtolower( trim( $name ) );

						$response_state['headers'][ $name ][] = trim( $value );
					}

					return $length;
				},
				CURLOPT_HTTPAUTH       => CURLAUTH_BASIC | CURLAUTH_DIGEST,
				CURLOPT_HTTPHEADER     => $request_headers,
				CURLOPT_NOBODY         => 'HEAD' === $method,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_SSL_VERIFYHOST => (bool) apply_filters( 'wwml_sslverify', true ) ? 2 : 0,
				CURLOPT_SSL_VERIFYPEER => (bool) apply_filters( 'wwml_sslverify', true ),
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_USERPWD        => $login . ':' . $password,
				CURLOPT_WRITEFUNCTION  => function( $curl_handle, string $chunk ) use ( &$response_state, $send_headers ): int {
					$send_headers();

					if ( $response_state['status'] >= 400 ) {
						return strlen( $chunk );
					}

					echo $chunk;
					flush();

					return strlen( $chunk );
				},
			)
		);

		$result = curl_exec( $curl );
		$error  = curl_error( $curl );
		curl_close( $curl );

		if ( false === $result ) {
			if ( ! $response_state['sent'] ) {
				status_header( 502 );
				wp_die( 'Remote file request failed: ' . esc_html( $error ) );
			}

			exit;
		}

		if ( ! $response_state['sent'] ) {
			$send_headers();
		}
	}

	private function get_valid_range_header(): string {
		$range = $_SERVER['HTTP_RANGE'] ?? '';
		if ( empty( $range ) ) {
			return '';
		}

		$range = trim( wp_unslash( $range ) );

		if ( preg_match( '/^bytes=(?:\d+-\d*|\d*-\d+)$/', $range ) ) {
			return $range;
		}

		return '';
	}

	private function get_forwarded_headers(): array {
		return array(
			'content-type',
			'content-length',
			'content-range',
			'accept-ranges',
			'etag',
			'last-modified',
			'cache-control',
			'expires',
			'content-disposition',
		);
	}

	private function format_header_name( string $header_name ): string {
		return implode( '-', array_map( 'ucfirst', explode( '-', $header_name ) ) );
	}

	private function end_output_buffers(): void {
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}
	}
}
