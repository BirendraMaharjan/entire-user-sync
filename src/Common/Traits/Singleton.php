<?php
/**
 * Singleton trait.
 *
 * @package EntireUserSync\Common\Traits
 */

namespace EntireUserSync\Common\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Singleton
 *
 * Enforces a single shared instance per class.
 * Use `get_instance()` to retrieve or create the instance.
 */
trait Singleton {

	/**
	 * Shared instance store.
	 *
	 * @var static|null
	 */
	private static ?self $instance = null;

	/**
	 * Prevent direct instantiation outside of get_instance().
	 */
	final private function __construct() {}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @return void
	 */
	private function __clone(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Cloning a singleton is not allowed.', 'entire-user-sync' ),
			'1.0.0'
		);
	}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @return void
	 */
	public function __wakeup(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing a singleton is not allowed.', 'entire-user-sync' ),
			'1.0.0'
		);
	}

	/**
	 * Return the single instance of the class, creating it on first call.
	 *
	 * Using `static` (late static binding) instead of `self` ensures that
	 * subclasses each get their own independent instance rather than sharing
	 * the parent's.
	 *
	 * @return static
	 */
	final public static function get_instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}
}
