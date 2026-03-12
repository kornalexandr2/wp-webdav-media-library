<?php
/**
 * Main initialization object for the plugin.
 *
 * @package wp-webdav-media-library
 */

namespace KiSa\WebDavMediaLibrary\Core;

use KiSa\WebDavMediaLibrary\Admin\Settings;
use KiSa\WebDavMediaLibrary\Admin\Browser;
use KiSa\WebDavMediaLibrary\Media\Ajax;
use KiSa\WebDavMediaLibrary\Media\MediaTab;
use KiSa\WebDavMediaLibrary\Media\Hooks;
use KiSa\WebDavMediaLibrary\Media\Proxy;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class Init
 */
class Init {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Init
	 */
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize Settings.
		$settings = new Settings();
		$settings->init();

		// Initialize Browser.
		$browser = new Browser();
		$browser->init();

		// Initialize Media Tab Integration.
		$media_tab = new MediaTab();
		$media_tab->init();

		// Initialize AJAX endpoints.
		$ajax = new Ajax();
		$ajax->init();

		// Initialize Hooks.
		$hooks = new Hooks();
		$hooks->init();

		// Initialize Proxy.
		$proxy = new Proxy();
		$proxy->init();
	}
}
