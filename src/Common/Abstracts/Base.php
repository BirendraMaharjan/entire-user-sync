<?php
/**
 * Base abstract class for plugin modules.
 *
 * @package EntireUserSync\Common\Abstracts
 * @since   1.0.0
 */

namespace EntireUserSync\Common\Abstracts;

use EntireUserSync\Config\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Base
 *
 * Injects the Plugin config instance into every module that extends it.
 * All plugin classes should extend this to gain access to plugin metadata
 * and configuration via $this->plugin.
 *
 * @package EntireUserSync\Common\Abstracts
 * @since   1.0.0
 */
abstract class Base {

	/**
	 * Plugin configuration and metadata instance.
	 *
	 * Provides access to plugin slug, version, paths, URLs, and other
	 * compile-time constants defined in the Config\Plugin class.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * Resolves and stores the Plugin singleton so every extending class
	 * has immediate access to plugin config without needing to call
	 * Plugin::get_instance() themselves.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin = Plugin::get_instance();
	}
}