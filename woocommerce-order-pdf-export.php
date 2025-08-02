<?php
/*
Plugin Name: WooCommerce Order PDF Exporter
Description: Export WooCommerce orders as PDF with comprehensive order details and professional formatting.
Version: 1.0.0
Author: Your Name
Text Domain: wc-order-pdf-export
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_ORDER_PDF_EXPORT_VERSION', '1.0.0');
define('WC_ORDER_PDF_EXPORT_PLUGIN_FILE', __FILE__);
define('WC_ORDER_PDF_EXPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_ORDER_PDF_EXPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'Hrrarya\\WoocommerceOrderPdfExport\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
add_action('plugins_loaded', function() {
    \Hrrarya\WoocommerceOrderPdfExport\Plugin::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce Order PDF Export requires WooCommerce to be installed and active.', 'wc-order-pdf-export'));
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});
