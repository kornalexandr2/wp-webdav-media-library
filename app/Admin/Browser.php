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

			<div id="wwml-browser-debug-wrap" style="margin-top: 20px;">
				<h3><?php esc_html_e( 'Browser Debug Log:', 'wp-webdav-media-library' ); ?></h3>
				<pre id="wwml-browser-debug-log" style="background: #f0f0f1; padding: 15px; border: 1px solid #c3c4c7; overflow: auto; max-height: 300px; font-family: monospace; white-space: pre-wrap; font-size: 12px;"></pre>
			</div>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				function log(msg) {
					var $log = $('#wwml-browser-debug-log');
					$log.append('[' + new Date().toLocaleTimeString() + '] ' + msg + "\n");
				}

				log("Starting Browser initialization...");

				if ($('#wwml-browser-container').length) {
					var attempts = 0;
					var checkInterval = setInterval(function() {
						attempts++;
						if (window.WWML_Browser_View) {
							clearInterval(checkInterval);
							log("WWML_Browser_View found. Creating instance...");
							try {
								var view = new window.WWML_Browser_View({
									el: $('#wwml-browser-container'),
									debug_log: log
								});
								log("Browser instance created.");
							} catch(e) {
								log("ERROR creating Browser instance: " + e.message);
							}
						} else {
							if (attempts % 10 === 0) log("Waiting for WWML_Browser_View script... (attempt " + attempts + ")");
							if (attempts > 100) {
								clearInterval(checkInterval);
								log("FATAL ERROR: WWML_Browser_View script failed to load after 10 seconds.");
							}
						}
					}, 100);
				}
			});
		</script>
		<?php
	}
}

