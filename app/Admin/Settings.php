<?php
/**
 * Admin settings page with detailed debugging.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Admin;

use Sabre\DAV\Client;

defined( 'ABSPATH' ) || exit;

class Settings {

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_ajax_wwml_test_webdav_connection', array( $this, 'ajax_test_connection' ) );
	}

	public function add_settings_page(): void {
		add_options_page(
			__( 'WebDAV Media Library', 'wp-webdav-media-library' ),
			__( 'WebDAV Media', 'wp-webdav-media-library' ),
			'manage_options',
			'wwml-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting( 'wwml_settings_group', 'wwml_provider' );
		register_setting( 'wwml_settings_group', 'wwml_server' );
		register_setting( 'wwml_settings_group', 'wwml_login' );
		register_setting( 'wwml_settings_group', 'wwml_password' );
		register_setting( 'wwml_settings_group', 'wwml_path' );
		register_setting( 'wwml_settings_group', 'wwml_proxy_slug', array(
			'sanitize_callback' => array( $this, 'sanitize_proxy_slug' )
		) );

		add_settings_section(
			'wwml_main_section',
			__( 'WebDAV Connection Settings', 'wp-webdav-media-library' ),
			null,
			'wwml-settings'
		);

		add_settings_field( 'wwml_provider', __( 'Provider Preset', 'wp-webdav-media-library' ), array( $this, 'render_provider_field' ), 'wwml-settings', 'wwml_main_section' );
		add_settings_field( 'wwml_server', __( 'WebDAV Server', 'wp-webdav-media-library' ), array( $this, 'render_server_field' ), 'wwml-settings', 'wwml_main_section' );
		add_settings_field( 'wwml_login', __( 'Login', 'wp-webdav-media-library' ), array( $this, 'render_login_field' ), 'wwml-settings', 'wwml_main_section' );
		add_settings_field( 'wwml_password', __( 'Password', 'wp-webdav-media-library' ), array( $this, 'render_password_field' ), 'wwml-settings', 'wwml_main_section' );
		add_settings_field( 'wwml_path', __( 'Path', 'wp-webdav-media-library' ), array( $this, 'render_path_field' ), 'wwml-settings', 'wwml_main_section' );
		add_settings_field( 'wwml_proxy_slug', __( 'Proxy URL Slug', 'wp-webdav-media-library' ), array( $this, 'render_proxy_slug_field' ), 'wwml-settings', 'wwml_main_section' );
	}

	public function sanitize_proxy_slug( $slug ) {
		$slug = sanitize_title( $slug );
		update_option( 'wwml_flush_rewrite', 1 );
		return $slug;
	}

	public function render_proxy_slug_field(): void {
		$val = get_option( 'wwml_proxy_slug', 'remote-media' );
		echo '<input type="text" name="wwml_proxy_slug" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="remote-media" />';
		echo '<p class="description">' . esc_html__( 'Slug used for external file URLs (e.g. yoursite.com/remote-media/file.jpg)', 'wp-webdav-media-library' ) . '</p>';
	}

	public function render_provider_field(): void {
		$val = get_option( 'wwml_provider', 'custom' );
		?>
		<select name="wwml_provider" id="wwml_provider">
			<option value="custom" <?php selected( $val, 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-webdav-media-library' ); ?></option>
			<option value="nextcloud" <?php selected( $val, 'nextcloud' ); ?>>Nextcloud</option>
			<option value="owncloud" <?php selected( $val, 'owncloud' ); ?>>ownCloud</option>
			<option value="yandex" <?php selected( $val, 'yandex' ); ?>><?php esc_html_e( 'Yandex.Disk', 'wp-webdav-media-library' ); ?></option>
		</select>
		<?php
	}

	public function render_server_field(): void {
		$val = get_option( 'wwml_server', '' );
		echo '<input type="url" id="wwml_server" name="wwml_server" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="https://example.com" />';
	}

	public function render_login_field(): void {
		$val = get_option( 'wwml_login', '' );
		echo '<input type="text" id="wwml_login" name="wwml_login" value="' . esc_attr( $val ) . '" class="regular-text" />';
	}

	public function render_password_field(): void {
		$val = get_option( 'wwml_password', '' );
		echo '<input type="password" id="wwml_password" name="wwml_password" value="' . esc_attr( $val ) . '" class="regular-text" />';
	}

	public function render_path_field(): void {
		$val = get_option( 'wwml_path', '/' );
		echo '<input type="text" id="wwml_path" name="wwml_path" value="' . esc_attr( $val ) . '" class="regular-text" />';
	}

	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WebDAV Media Library', 'wp-webdav-media-library' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wwml_settings_group' );
				do_settings_sections( 'wwml-settings' );
				submit_button();
				?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Test Connection', 'wp-webdav-media-library' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Check your settings and see detailed server response below.', 'wp-webdav-media-library' ); ?></p>
			<button type="button" class="button" id="wwml-test-connection"><?php esc_html_e( 'Test Connection', 'wp-webdav-media-library' ); ?></button>

			<div id="wwml-debug-log-wrap" style="margin-top: 20px; display: none;">
				<h3><?php esc_html_e( 'Connection Debug Log:', 'wp-webdav-media-library' ); ?></h3>
				<pre id="wwml-debug-log" style="background: #f0f0f1; padding: 15px; border: 1px solid #c3c4c7; overflow: auto; max-height: 400px; font-family: monospace; white-space: pre-wrap;"></pre>
			</div>
		</div>
		<?php
	}

	public function admin_scripts( string $hook ): void {
		if ( 'settings_page_wwml-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wwml-admin-settings',
			plugin_dir_url( WWML_PLUGIN ) . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			WWML_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'wwml-admin-settings', 'wwml_admin', array(
			'nonce'        => wp_create_nonce( 'wwml_test_conn' ),
			'text_testing' => __( 'Testing...', 'wp-webdav-media-library' ),
			'text_success' => __( 'Connection successful!', 'wp-webdav-media-library' ),
			'text_error'   => __( 'Connection failed', 'wp-webdav-media-library' ),
			'text_test_btn'=> __( 'Test Connection', 'wp-webdav-media-library' ),
		) );
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( 'wwml_test_conn', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'No permission', 'wp-webdav-media-library' ) );
		}

		$server   = sanitize_text_field( $_POST['server'] ?? '' );
		$login    = sanitize_text_field( $_POST['login'] ?? '' );
		$password = $_POST['password'] ?? '';
		$path     = sanitize_text_field( $_POST['path'] ?? '' );
		$provider = sanitize_text_field( $_POST['provider'] ?? 'custom' );

		if ( empty( $server ) || empty( $login ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill all fields', 'wp-webdav-media-library' ) ) );
		}

		$domain   = str_starts_with( $server, 'http' ) ? $server : 'https://' . $server;
		$domain   = rtrim( $domain, '/' );

		if ( in_array( $provider, array( 'nextcloud', 'owncloud' ), true ) && ! empty( $login ) ) {
			$full_path = trailingslashit( $path ) . $login . '/';
		} else {
			$full_path = trailingslashit( $path );
		}

		$target_url = $domain . '/' . ltrim( $full_path, '/' );

		$debug = "--- DEBUG LOG START ---\n";
		$debug .= "Target URL: " . $target_url . "\n";
		$debug .= "Provider: " . $provider . "\n";

		$auth_base64 = base64_encode( "$login:$password" );
		$headers = array(
			'Authorization' => 'Basic ' . $auth_base64,
			'Content-Type'  => 'text/xml; charset=utf-8',
			'Depth'         => '0',
		);

		$body = '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:prop><D:displayname/></D:prop></D:propfind>';

		$debug_headers = $headers;
		$debug_headers['Authorization'] = 'Basic [redacted]';

		$debug .= "Request Method: PROPFIND\n";
		$debug .= "Request Headers:\n" . print_r( $debug_headers, true ) . "\n";
		$debug .= "Request Body:\n" . $body . "\n\n";

		$response = wp_remote_request( $target_url, array(
			'method'    => 'PROPFIND',
			'headers'   => $headers,
			'body'      => $body,
			'timeout'   => 20,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) {
			$debug .= "WP_Error: " . $response->get_error_message() . "\n";
			wp_send_json_error( array( 'message' => $response->get_error_message(), 'debug' => $debug ) );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );
		$res_headers = wp_remote_retrieve_headers( $response );
		$res_body    = wp_remote_retrieve_body( $response );

		$debug .= "--- SERVER RESPONSE ---\n";
		$debug .= "Status: $code $message\n";

		$debug_response_headers = "";
		foreach($res_headers->getAll() as $k => $v) {
			$debug_response_headers .= "$k: " . (is_array($v) ? implode(', ', $v) : $v) . "\n";
		}

		$debug .= "Response Headers:\n" . $debug_response_headers . "\n";
		$debug .= "Response Body:\n" . ( !empty($res_body) ? htmlspecialchars($res_body) : '(empty)' ) . "\n";
		$debug .= "--- DEBUG LOG END ---";

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( array( 'debug' => $debug ) );
		} else {
			wp_send_json_error( array( 'message' => "Server returned HTTP $code", 'debug' => $debug ) );
		}
	}
}
