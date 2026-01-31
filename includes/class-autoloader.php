<?php
/**
 * PSR-4 Autoloader for DataSignals namespace.
 *
 * @package DataSignals
 */

namespace DataSignals;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 */
class Autoloader {
	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'DataSignals\\';

	/**
	 * Base directory for namespace.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::$base_dir = DATA_SIGNALS_PLUGIN_DIR . 'includes/';
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class The fully-qualified class name.
	 * @return void
	 */
	private static function autoload( string $class ): void {
		// Check if class uses our namespace.
		if ( strpos( $class, self::NAMESPACE_PREFIX ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );

		// Convert namespace separators to directory separators.
		$relative_class = str_replace( '\\', '/', $relative_class );

		// Convert to WordPress file naming convention (class-*.php).
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		// Build the full file path.
		$file = self::$base_dir . $file_name;

		// If file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
