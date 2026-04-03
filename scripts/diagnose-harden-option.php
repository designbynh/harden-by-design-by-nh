<?php
/**
 * Print `harden_by_nh_options` using WordPress (uses wp-config DB settings).
 *
 * Usage (from anywhere):
 *   php /path/to/wp-content/plugins/designbynh/scripts/diagnose-harden-option.php
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script is CLI-only.\n" );
	exit( 1 );
}

// Skip theme bootstrap; still loads plugins and options.
define( 'WP_USE_THEMES', false );

$plugin_dir = dirname( __DIR__ );
$root       = dirname( $plugin_dir, 3 );

$wp_load = $root . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "Cannot read WordPress bootstrap: {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;

if ( ! function_exists( 'get_option' ) ) {
	fwrite( STDERR, "WordPress did not load.\n" );
	exit( 1 );
}

echo "=== harden_by_nh_options (get_option) ===\n";
$value = get_option( 'harden_by_nh_options', null );
var_export( $value );
echo "\n";

global $wpdb;
if ( isset( $wpdb ) && is_object( $wpdb ) ) {
	echo "\n=== DB row ({$wpdb->options}) ===\n";
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT option_id, autoload, option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			'harden_by_nh_options'
		),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "No row found (option has never been saved, or different table prefix).\n";
	} else {
		echo 'option_id: ' . (int) $row['option_id'] . "\n";
		echo 'autoload: ' . (string) $row['autoload'] . "\n";
		echo "option_value (serialized):\n" . $row['option_value'] . "\n";
	}
}
