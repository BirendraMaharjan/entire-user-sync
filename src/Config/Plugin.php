<?php
/**
 * Plugin configuration and metadata.
 *
 * @package EntireUserSync\Config
 * @since   1.0.0
 */

namespace EntireUserSync\Config;

use EntireUserSync\Common\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for plugin metadata and runtime paths.
 *
 * @package EntireUserSync\Config
 * @since   1.0.0
 */
final class Plugin {

	use Singleton;

	/**
	 * Cached plugin data. Null until first call to data().
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Build and return the full plugin dataset (cached after first call).
	 *
	 * @since  1.0.0
	 * @return array<string, mixed>
	 */
	public function data(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$plugin_file = ENTIRE_USER_SYNC_FILE;

		$runtime = apply_filters(
			'entireus_plugin_data',
			array(
				'plugin_file'            => $plugin_file,
				'plugin_path'            => untrailingslashit( plugin_dir_path( $plugin_file ) ),
				'plugin_url'             => untrailingslashit( plugin_dir_url( $plugin_file ) ),
				'plugin_basename'        => plugin_basename( $plugin_file ),
				'slug'                   => 'entire-user-sync',
				'prefix'                 => 'entireus',
				'plugin_template_folder' => 'templates',
			)
		);

		$meta = apply_filters(
			'entireus_plugin_meta_data',
			get_file_data(
				$plugin_file,
				array(
					'name'         => 'Plugin Name',
					'uri'          => 'Plugin URI',
					'description'  => 'Description',
					'version'      => 'Version',
					'author'       => 'Author',
					'author_uri'   => 'Author URI',
					'text_domain'  => 'Text Domain',
					'domain_path'  => 'Domain Path',
					'required_php' => 'Requires PHP',
					'required_wp'  => 'Requires at least',
					'namespace'    => 'Namespace',
				),
				'plugin'
			)
		);

		// Runtime values take precedence over header values on key collision.
		$this->cache = array_merge( (array) $meta, (array) $runtime );

		return $this->cache;
	}

	/** Absolute path to the plugin root (no trailing slash). */
	public function plugin_path(): string {
		return $this->data()['plugin_path'];
	}

	/** Absolute path to the templates directory (no trailing slash). */
	public function template_path(): string {
		return $this->data()['plugin_path'] . '/' . $this->data()['plugin_template_folder'];
	}

	/** Template folder name relative to the plugin root. */
	public function template_folder(): string {
		return $this->data()['plugin_template_folder'];
	}

	/** Public URL to the plugin root (no trailing slash). */
	public function url(): string {
		return $this->data()['plugin_url'];
	}

	/** Plugin basename (folder/file.php). */
	public function plugin_basename(): string {
		return $this->data()['plugin_basename'];
	}

	/** Relative path to the languages' directory. */
	public function domain_path(): string {
		return $this->data()['domain_path'];
	}

	/** Plugin name from the file header. */
	public function name(): string {
		return $this->data()['name'];
	}

	/** Translated plugin title for use in UI strings. */
	public function title(): string {
		return esc_html__( 'Entire User Sync', 'entire-user-sync' );
	}

	/** Plugin slug for option names, handles, and CSS classes. */
	public function slug(): string {
		return $this->data()['slug'];
	}

	/** Short prefix for hook names and option keys. */
	public function prefix(): string {
		return $this->data()['prefix'];
	}

	/** Plugin URI from the file header. */
	public function uri(): string {
		return $this->data()['uri'];
	}

	/** Plugin description from the file header. */
	public function description(): string {
		return $this->data()['description'];
	}

	/** Plugin version string (e.g. "1.0.0"). */
	public function version(): string {
		return $this->data()['version'];
	}

	/** Minimum required PHP version (e.g. "8.1"). */
	public function required_php(): string {
		return $this->data()['required_php'];
	}

	/** Minimum required WordPress version (e.g. "6.4"). */
	public function required_wp(): string {
		return $this->data()['required_wp'];
	}

	/** Root PHP namespace declared in the file header. */
	public function plugin_namespace(): string {
		return $this->data()['namespace'];
	}

	/** Author name from the file header. */
	public function author(): string {
		return $this->data()['author'];
	}

	/** Author URI from the file header. */
	public function author_uri(): string {
		return $this->data()['author_uri'];
	}

	/** Text domain from the file header. */
	public function text_domain(): string {
		return $this->data()['text_domain'];
	}

	/**
	 * Plugin settings read live from the database (never cached).
	 *
	 * @return mixed Array of settings, or false if not yet saved.
	 */
	public function settings(): mixed {
		return get_option( $this->data()['prefix'] );
	}
}
