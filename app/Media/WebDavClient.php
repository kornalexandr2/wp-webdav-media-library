<?php
/**
 * WebDAV Client Wrapper.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Media;

use Sabre\DAV\Client;

defined( 'ABSPATH' ) || exit;

class WebDavClient {

	private ?Client $client = null;
	private string $base_path = '';
	private string $domain = '';
	private string $provider = '';

	public function __construct() {
		$server   = get_option( 'wwml_server', '' );
		$login    = get_option( 'wwml_login', '' );
		$password = get_option( 'wwml_password', '' );
		$path     = get_option( 'wwml_path', '' );
		$provider = get_option( 'wwml_provider', 'custom' );

		if ( empty( $server ) || empty( $login ) || empty( $password ) ) {
			return;
		}

		$this->domain = rtrim( str_starts_with( $server, 'http' ) ? $server : 'https://' . $server, '/' );
		$this->provider = $provider;

		$settings = array(
			'baseUri'  => $this->domain,
			'userName' => $login,
			'password' => $password,
		);

		// Format path based on provider.
		if ( in_array( $provider, array( 'nextcloud', 'owncloud' ), true ) && ! empty( $login ) ) {
			$path = trailingslashit( $path ) . $login . '/';
		} else {
			$path = trailingslashit( $path );
		}
		$this->base_path = $path;

		$this->client = new Client( $settings );
	}

	public function is_configured(): bool {
		return null !== $this->client;
	}

	public function get_client(): ?Client {
		return $this->client;
	}

	public function get_base_path(): string {
		return $this->base_path;
	}

	public function get_domain(): string {
		return $this->domain;
	}

	public function get_provider(): string {
		return $this->provider;
	}

	public function get_remote_url( string $remote_path ): string {
		return rtrim( $this->domain, '/' ) . '/' . $this->encode_path( $remote_path );
	}

	public function is_path_allowed( string $remote_path ): bool {
		$base_path   = $this->normalize_path( $this->base_path );
		$remote_path = $this->normalize_path( rawurldecode( $remote_path ) );

		return str_starts_with( $remote_path, $base_path );
	}

	public function is_url_allowed( string $url ): bool {
		$parsed_url    = wp_parse_url( $url );
		$parsed_domain = wp_parse_url( $this->domain );

		if ( empty( $parsed_url['host'] ) || empty( $parsed_domain['host'] ) ) {
			return false;
		}

		if ( strtolower( $parsed_url['host'] ) !== strtolower( $parsed_domain['host'] ) ) {
			return false;
		}

		$remote_path = $parsed_url['path'] ?? '/';

		return $this->is_path_allowed( $remote_path );
	}

	public function list_directory( string $sub_path = '' ): array {
		if ( ! $this->client ) {
			return array( 'files' => array(), 'dirs' => array() );
		}

		$request_path = trailingslashit( $this->base_path ) . ltrim( $sub_path, '/' );

		try {
			$directory_list = $this->client->propFind(
				$request_path,
				array(
					'{DAV:}resourcetype',
					'{DAV:}getcontentlength',
					'{DAV:}getlastmodified',
					'{DAV:}getcontenttype',
					'{DAV:}getetag',
				),
				1
			);
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}

		$listing = array(
			'files' => array(),
			'dirs'  => array(),
			'current_path' => $sub_path,
		);

		foreach ( $directory_list as $file_url => $props ) {
			// Normalize path for comparison. WebDAV might return full URL or absolute path.
			$decoded_url = rawurldecode( $file_url );

			// If it's a full URL, strip the domain.
			$normalized_path = str_replace( $this->domain, '', $decoded_url );

			// Calculate relative path by removing only the leading base WebDAV path.
			$relative_path = $this->strip_base_path( rtrim( $normalized_path, '/' ) );
			$relative_path = '/' . ltrim( $relative_path, '/' );

			if ( empty( $relative_path ) || '/' === $relative_path || $relative_path === rtrim( $sub_path, '/' ) ) {
				continue; // Skip the directory itself.
			}

			// Robust directory detection: check SabreDAV object OR trailing slash in URL
			$is_dir = false;
			if ( isset( $props['{DAV:}resourcetype'] ) && $props['{DAV:}resourcetype']->is( '{DAV:}collection' ) ) {
				$is_dir = true;
			} elseif ( str_ends_with( $decoded_url, '/' ) ) {
				$is_dir = true;
			}

			$basename = basename( rtrim( $decoded_url, '/' ) );

			if ( $is_dir ) {
				$listing['dirs'][] = array(
					'name' => $basename,
					'path' => trailingslashit( $relative_path ),
				);
			} else {
				$mime_type = wp_check_filetype( $basename );
				$remote_mime_type = $props['{DAV:}getcontenttype'] ?? '';

				$listing['files'][] = array(
					'name'      => $basename,
					'path'      => $relative_path,
					'url'       => $this->get_remote_url( $normalized_path ),
					'size'      => (int) ( $props['{DAV:}getcontentlength'] ?? 0 ),
					'mime_type' => $remote_mime_type ?: ( $mime_type['type'] ?? 'application/octet-stream' ),
					'modified'  => gmdate( 'Y-m-d H:i:s', strtotime( $props['{DAV:}getlastmodified'] ?? 'now' ) ),
				);
			}
		}

		return $listing;
	}

	private function strip_base_path( string $path ): string {
		$base_path = rtrim( $this->normalize_path( $this->base_path ), '/' );
		$path      = $this->normalize_path( $path );

		if ( str_starts_with( $path, $base_path ) ) {
			return substr( $path, strlen( $base_path ) );
		}

		return $path;
	}

	private function normalize_path( string $path ): string {
		$path = '/' . ltrim( rawurldecode( $path ), '/' );

		return preg_replace( '#/+#', '/', $path ) ?: '/';
	}

	private function encode_path( string $path ): string {
		$path_parts = explode( '/', ltrim( $this->normalize_path( $path ), '/' ) );

		return implode( '/', array_map( 'rawurlencode', $path_parts ) );
	}
}
