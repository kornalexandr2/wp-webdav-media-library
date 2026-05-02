<?php
/**
 * AJAX Handlers for WebDAV Media Library.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Media;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public function init(): void {
		add_action( 'wp_ajax_wwml_get_files', array( $this, 'ajax_get_files' ) );
		add_action( 'wp_ajax_wwml_import_file', array( $this, 'ajax_import_file' ) );
		add_action( 'wp_ajax_wwml_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_wwml_preview_debug', array( $this, 'ajax_preview_debug' ) );
	}

	public function ajax_get_files(): void {
		check_ajax_referer( 'wwml_media_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission', 'wp-webdav-media-library' ) ) );
		}

		$path = sanitize_text_field( $_POST['path'] ?? '/' );

		try {
			$client = new WebDavClient();
			if ( ! $client->is_configured() ) {
				wp_send_json_error( array( 'message' => __( 'WebDAV is not configured', 'wp-webdav-media-library' ) ) );
			}

			$listing = $client->list_directory( $path );
			if ( isset( $listing['error'] ) ) {
				wp_send_json_error( array( 'message' => $listing['error'] ) );
			}

			wp_send_json_success( $listing );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		} catch ( \Error $e ) {
			wp_send_json_error( array( 'message' => 'PHP Error: ' . $e->getMessage() ) );
		}
	}

	public function ajax_import_file(): void {
		check_ajax_referer( 'wwml_media_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'No permission', 'wp-webdav-media-library' ) );
		}

		$file_url = esc_url_raw( $_POST['file_url'] ?? '' );
		$file_name = sanitize_file_name( basename( parse_url( $file_url, PHP_URL_PATH ) ) );
		$file_size = isset( $_POST['file_size'] ) ? max( 0, (int) $_POST['file_size'] ) : 0;
		$mime_type = sanitize_mime_type( $_POST['mime_type'] ?? '' );
		$modified  = sanitize_text_field( $_POST['modified'] ?? '' );

		if ( empty( $file_url ) || empty( $file_name ) ) {
			wp_send_json_error( __( 'Invalid file', 'wp-webdav-media-library' ) );
		}

		$client = new WebDavClient();
		if ( ! $client->is_configured() || ! $client->is_url_allowed( $file_url ) ) {
			wp_send_json_error( __( 'Invalid WebDAV file URL', 'wp-webdav-media-library' ) );
		}

		if ( empty( $mime_type ) ) {
			$mime_type = wp_check_filetype( $file_name )['type'] ?? 'application/octet-stream';
		}

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $file_url,
		);

		$id = wp_insert_attachment( $attachment, $file_name );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wwml_remote_url', $file_url );
			update_post_meta( $id, '_wwml_remote_size', $file_size );
			update_post_meta( $id, '_wwml_remote_modified', $modified );
			update_post_meta( $id, '_wp_attached_file', 'wwml-remote/' . $file_name );

			if ( $file_size > 0 ) {
				wp_update_attachment_metadata( $id, array( 'filesize' => $file_size ) );
			}

			$attachment_data = wp_prepare_attachment_for_js( $id );
			wp_send_json_success( $attachment_data );
		} else {
			wp_send_json_error( $id->get_error_message() );
		}
	}

	public function ajax_preview_debug(): void {
		check_ajax_referer( 'wwml_media_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'No permission' ) );
		}

		$file_url = esc_url_raw( $_POST['file_url'] ?? '' );

		$log = "--- PREVIEW DEBUG START ---\n";
		$log .= "File: $file_url\n";

		if ( ! $this->is_allowed_remote_url( $file_url ) ) {
			wp_send_json_error( array( 'message' => 'Invalid WebDAV file URL', 'debug' => $log ) );
		}

		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/wwml-cache';
		$cache_key  = md5( $file_url ) . '.jpg';
		$cache_path = $cache_dir . '/' . $cache_key;

		$log .= "Cache Path: $cache_path\n";

		$login    = get_option( 'wwml_login', '' );
		$password = get_option( 'wwml_password', '' );

		if ( empty( $login ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => 'Credentials missing', 'debug' => $log ) );
		}

		try {
			$tmp_file = wp_tempnam( $file_url );
			if ( ! $tmp_file ) {
				wp_send_json_error( array( 'message' => 'Unable to create temp file', 'debug' => $log ) );
			}

			$log .= "Attempting streamed download via wp_remote_get...\n";
			$response = $this->download_preview_source( $file_url, $tmp_file, 30 );

			if ( is_wp_error( $response ) ) {
				$log .= "DOWNLOAD FAILED: " . $response->get_error_message() . "\n";
				@unlink( $tmp_file );
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$log .= "Server Response: $code\n";

				if ( 200 === $code ) {
					$log .= "Saved to temp file (" . filesize( $tmp_file ) . " bytes)\n";

					if ( ! function_exists( 'wp_get_image_editor' ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
					}

					$editor = wp_get_image_editor( $tmp_file );
					if ( ! is_wp_error( $editor ) ) {
						$log .= "Image editor loaded: " . get_class($editor) . "\n";
						$editor->resize( 250, 250, true );
						$save_res = $editor->save( $cache_path, 'image/jpeg' );
						if ( is_wp_error( $save_res ) ) {
							$log .= "SAVE FAILED: " . $save_res->get_error_message() . "\n";
						} else {
							$log .= "SUCCESS: Preview saved.\n";
						}
						unlink( $tmp_file );
					} else {
						$log .= "EDITOR ERROR: " . $editor->get_error_message() . "\n";
						unlink( $tmp_file );
					}
				} else {
					@unlink( $tmp_file );
				}
			}
		} catch ( \Exception $e ) {
			$log .= "EXCEPTION: " . $e->getMessage() . "\n";
		}

		$log .= "--- PREVIEW DEBUG END ---";
		wp_send_json_success( array( 'debug' => $log ) );
	}

	public function ajax_preview(): void {
		check_ajax_referer( 'wwml_media_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'No permission' );
		}

		$file_url = esc_url_raw( $_GET['file'] ?? '' );
		if ( empty( $file_url ) ) {
			wp_die();
		}

		if ( ! $this->is_allowed_remote_url( $file_url ) ) {
			wp_die();
		}

		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/wwml-cache';
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$cache_key  = md5( $file_url ) . '.jpg';
		$cache_path = $cache_dir . '/' . $cache_key;
		$cache_url  = $upload_dir['baseurl'] . '/wwml-cache/' . $cache_key;

		if ( file_exists( $cache_path ) ) {
			wp_redirect( $cache_url );
			exit;
		}

		$tmp_file = wp_tempnam( $file_url );
		if ( ! $tmp_file ) {
			wp_die();
		}

		$response = $this->download_preview_source( $file_url, $tmp_file, 30 );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			if ( ! function_exists( 'wp_get_image_editor' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$editor = wp_get_image_editor( $tmp_file );
			if ( ! is_wp_error( $editor ) ) {
				$editor->resize( 250, 250, true );
				$editor->save( $cache_path, 'image/jpeg' );
				unlink( $tmp_file );
				wp_redirect( $cache_url );
				exit;
			}
			unlink( $tmp_file );
		} else {
			@unlink( $tmp_file );
		}

		wp_die();
	}

	private function is_allowed_remote_url( string $file_url ): bool {
		$client = new WebDavClient();

		return $client->is_configured() && $client->is_url_allowed( $file_url );
	}

	private function download_preview_source( string $file_url, string $tmp_file, int $timeout ) {
		$login    = get_option( 'wwml_login', '' );
		$password = get_option( 'wwml_password', '' );

		if ( empty( $login ) || empty( $password ) ) {
			return new \WP_Error( 'wwml_credentials_missing', __( 'Credentials missing', 'wp-webdav-media-library' ) );
		}

		return wp_remote_get(
			$file_url,
			array(
				'headers'     => array(
					'Authorization' => 'Basic ' . base64_encode( "$login:$password" ),
				),
				'timeout'     => $timeout,
				'sslverify'   => (bool) apply_filters( 'wwml_sslverify', false ),
				'stream'      => true,
				'filename'    => $tmp_file,
				'redirection' => 0,
			)
		);
	}
}
