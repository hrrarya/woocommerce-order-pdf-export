<?php

namespace Hrrarya\WoocommerceOrderPdfExport;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class Plugin
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Plugin slug
     */
    const SLUG = 'wc-order-pdf-export';

    /**
     * Get plugin instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_wc_order_pdf_download', [$this, 'handle_pdf_download']);
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Load text domain for translations
        load_plugin_textdomain('wc-order-pdf-export', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Order PDF Export', 'wc-order-pdf-export'),
            __('Order PDF Export', 'wc-order-pdf-export'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, self::SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'wc-order-pdf-export-admin',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'wc-order-pdf-export-admin',
            plugin_dir_url(__FILE__) . '../assets/js/admin.js',
            ['jquery'],
            self::VERSION,
            true
        );

        wp_localize_script('wc-order-pdf-export-admin', 'wcOrderPdfExport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_order_pdf_export_nonce'),
            'strings' => [
                'downloading' => __('Downloading...', 'wc-order-pdf-export'),
                'error' => __('Error downloading PDF', 'wc-order-pdf-export'),
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        $admin_page = new AdminPage();
        $admin_page->render();
    }

    /**
     * Handle PDF download via AJAX
     */
    public function handle_pdf_download()
    {
        try {
            // Verify request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                Security::log_security_event('invalid_request_method', ['method' => $_SERVER['REQUEST_METHOD']]);
                wp_die(__('Invalid request method', 'wc-order-pdf-export'), 405);
            }

            // Verify nonce with enhanced security
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!Security::verify_nonce($nonce, 'wc_order_pdf_export_nonce')) {
                wp_die(__('Security check failed', 'wc-order-pdf-export'), 403);
            }

            // Check rate limiting
            if (!Security::check_rate_limit()) {
                Security::log_security_event('rate_limit_exceeded');
                wp_die(__('Too many requests. Please wait a moment before trying again.', 'wc-order-pdf-export'), 429);
            }

            // Validate and sanitize order ID
            $order_id = intval($_POST['order_id'] ?? 0);
            if (!$order_id || $order_id <= 0) {
                Security::log_security_event('invalid_order_id', ['order_id' => $_POST['order_id'] ?? 'missing']);
                wp_die(__('Invalid order ID', 'wc-order-pdf-export'), 400);
            }

            // Check if user can access this order
            if (!Security::can_user_access_order($order_id)) {
                Security::log_security_event('unauthorized_order_access', ['order_id' => $order_id]);
                wp_die(__('You do not have permission to access this order', 'wc-order-pdf-export'), 403);
            }

            // Verify order exists
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_die(__('Order not found', 'wc-order-pdf-export'), 404);
            }

            // Log successful PDF generation request
            Security::log_security_event('pdf_download_requested', ['order_id' => $order_id]);

            // Generate and download PDF
            $pdf_generator = new PdfGenerator();
            $pdf_generator->generate_order_pdf($order_id);

        } catch (Exception $e) {
            Security::log_security_event('pdf_generation_error', ['error' => $e->getMessage()]);
            error_log('WC Order PDF Export Error: ' . $e->getMessage());
            wp_die(__('An error occurred while generating the PDF. Please try again.', 'wc-order-pdf-export'), 500);
        }
    }

    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo __('WooCommerce Order PDF Export requires WooCommerce to be installed and active.', 'wc-order-pdf-export');
        echo '</p></div>';
    }

    /**
     * Get plugin URL
     */
    public static function get_plugin_url()
    {
        return plugin_dir_url(__FILE__);
    }

    /**
     * Get plugin path
     */
    public static function get_plugin_path()
    {
        return plugin_dir_path(__FILE__);
    }
}
