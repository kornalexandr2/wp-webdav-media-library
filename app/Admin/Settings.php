<?php
/**
 * Admin settings page.
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
		// Flush rules on next load
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
		echo '<input type="text" id="wwml_login" id="wwml_login" name="wwml_login" value="' . esc_attr( $val ) . '" class="regular-text" />';
	}

	public function render_password_field(): void {
		$val = get_option( 'wwml_password', '' );
		echo '<input type="password" id="wwml_password" id="wwml_password" name="wwml_password" value="' . esc_attr( $val ) . '" class="regular-text" />';
	}

	public function render_path_field(): void {
		$val = get_option( 'wwml_path', '/remote.php/dav/files/' );
		echo '<input type="text" id="wwml_path" id="wwml_path" name="wwml_path" value="' . esc_attr( $val ) . '" class="regular-text" />';
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
			<button type="button" class="button" id="wwml-test-connection"><?php esc_html_e( 'Test Connection', 'wp-webdav-media-library' ); ?></button>
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

		if ( empty( $server ) || empty( $login ) || empty( $password ) ) {
			wp_send_json_error( __( 'Please fill all fields', 'wp-webdav-media-library' ) );
		}

		$domain   = str_starts_with( $server, 'http' ) ? $server : 'https://' . $server;
		$domain   = rtrim( $domain, '/' );
		$provider = sanitize_text_field( $_POST['provider'] ?? 'custom' );

		// Adjust path logic to match WebDavClient
		if ( 'yandex' !== $provider && ! empty( $login ) ) {
			$path = trailingslashit( $path ) . $login . '/';
		} else {
			$path = trailingslashit( $path );
		}

		$settings = array(
			'baseUri'  => $domain . '/',
			'userName' => $login,
			'password' => $password,
		);

		try {
			$client = new Client( $settings );
			// Perform a minimal check on the calculated path
			$client->propFind( ltrim( $path, '/' ), array( '{DAV:}displayname' ), 0 );
			wp_send_json_success();
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}

