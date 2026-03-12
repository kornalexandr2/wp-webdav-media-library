<?php
/**
 * WebDAV Browser Page.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Admin;

defined( 'ABSPATH' ) || exit;

class Browser {

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_browser_page' ) );
	}

	public function add_browser_page(): void {
		add_media_page(
			__( 'WebDAV Browser', 'wp-webdav-media-library' ),
			__( 'WebDAV Browser', 'wp-webdav-media-library' ),
			'upload_files',
			'wwml-browser',
			array( $this, 'render_browser_page' )
		);
	}

	public function render_browser_page(): void {
		// Enqueue media scripts because we reuse the same logic
		wp_enqueue_media();
		
		// The scripts are already enqueued via MediaTab class if wp_enqueue_media is called
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WebDAV File Browser', 'wp-webdav-media-library' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Browse your WebDAV storage and import files into WordPress Media Library as virtual attachments.', 'wp-webdav-media-library' ); ?></p>
			
			<div id="wwml-browser-container" style="background: #fff; border: 1px solid #c3c4c7; margin-top: 20px; min-height: 500px; position: relative;">
				<div class="wwml-browser-wrap">
					<div class="wwml-loading"><?php esc_html_e( 'Initializing...', 'wp-webdav-media-library' ); ?></div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// If we are on this page, we manually trigger the view
				if ($('#wwml-browser-container').length) {
					// We need to wait a bit for media-webdav.js to load
					var checkInterval = setInterval(function() {
						if (window.WWML_Browser_View) {
							clearInterval(checkInterval);
							var view = new window.WWML_Browser_View({
								el: $('#wwml-browser-container')
							});
						}
					}, 100);
				}
			});
		</script>
		<?php
	}
}
