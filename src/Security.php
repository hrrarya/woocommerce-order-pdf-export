<?php

namespace Hrrarya\WoocommerceOrderPdfExport;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security utility class
 */
class Security
{
    /**
     * Verify user can access order
     */
    public static function can_user_access_order($order_id, $user_id = null)
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Check if user has manage_woocommerce capability
        if (user_can($user_id, 'manage_woocommerce')) {
            return true;
        }

        // Check if user has edit_shop_orders capability
        if (user_can($user_id, 'edit_shop_orders')) {
            return true;
        }

        // For customers, check if they own the order
        $order = wc_get_order($order_id);
        if ($order && $order->get_customer_id() === $user_id) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize order search input
     */
    public static function sanitize_order_search($search)
    {
        $search = sanitize_text_field($search);
        $search = trim($search);
        
        // Remove potentially dangerous characters
        $search = preg_replace('/[<>"\']/', '', $search);
        
        // Limit length
        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
        }

        return $search;
    }

    /**
     * Validate order status filter
     */
    public static function validate_order_status($status)
    {
        if (empty($status)) {
            return '';
        }

        $valid_statuses = array_keys(wc_get_order_statuses());
        
        if (in_array($status, $valid_statuses, true)) {
            return $status;
        }

        return '';
    }

    /**
     * Rate limiting for PDF downloads
     */
    public static function check_rate_limit($user_id = null)
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $transient_key = 'wc_pdf_export_rate_limit_' . $user_id;
        $current_count = get_transient($transient_key);

        if ($current_count === false) {
            // First request in this time window
            set_transient($transient_key, 1, MINUTE_IN_SECONDS);
            return true;
        }

        // Check if user has exceeded rate limit (10 downloads per minute)
        if ($current_count >= 10) {
            return false;
        }

        // Increment counter
        set_transient($transient_key, $current_count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Log security events
     */
    public static function log_security_event($event, $details = [])
    {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_user_ip(),
            'event' => $event,
            'details' => $details
        ];

        error_log('WC Order PDF Export Security Event: ' . wp_json_encode($log_entry));
    }

    /**
     * Get user IP address
     */
    private static function get_user_ip()
    {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Validate nonce with additional checks
     */
    public static function verify_nonce($nonce, $action)
    {
        if (empty($nonce)) {
            self::log_security_event('nonce_missing', ['action' => $action]);
            return false;
        }

        if (!wp_verify_nonce($nonce, $action)) {
            self::log_security_event('nonce_invalid', ['action' => $action, 'nonce' => $nonce]);
            return false;
        }

        return true;
    }

    /**
     * Check if request is from admin area
     */
    public static function is_admin_request()
    {
        return is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }

    /**
     * Sanitize filename for PDF download
     */
    public static function sanitize_pdf_filename($filename)
    {
        // Remove any path traversal attempts
        $filename = basename($filename);
        
        // Sanitize using WordPress function
        $filename = sanitize_file_name($filename);
        
        // Ensure it ends with .pdf
        if (!preg_match('/\.pdf$/i', $filename)) {
            $filename .= '.pdf';
        }

        return $filename;
    }
}
