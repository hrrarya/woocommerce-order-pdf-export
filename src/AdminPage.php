<?php

namespace Hrrarya\WoocommerceOrderPdfExport;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Page Class
 */
class AdminPage
{
    /**
     * Orders per page
     */
    const ORDERS_PER_PAGE = 20;

    /**
     * Render admin notices
     */
    private function render_notices()
    {
        // Check if WooCommerce has orders
        $order_count = wp_count_posts('shop_order');
        $total_orders = 0;

        if ($order_count) {
            foreach ($order_count as $status => $count) {
                $total_orders += $count;
            }
        }

        if ($total_orders === 0) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php _e('No orders found. Orders will appear here once customers start placing orders.', 'wc-order-pdf-export'); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-secondary">
                        <?php _e('View Orders', 'wc-order-pdf-export'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // Show success message if PDF was downloaded
        if (isset($_GET['pdf_downloaded']) && $_GET['pdf_downloaded'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('PDF downloaded successfully!', 'wc-order-pdf-export'); ?></p>
            </div>
            <?php
        }

        // Show error message if there was an issue
        if (isset($_GET['pdf_error']) && $_GET['pdf_error'] === '1') {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('There was an error generating the PDF. Please try again.', 'wc-order-pdf-export'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render the admin page
     */
    public function render()
    {
        // Verify user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-order-pdf-export'));
        }

        // Sanitize and validate input parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['search']) ? Security::sanitize_order_search($_GET['search']) : '';
        $status_filter = isset($_GET['status']) ? Security::validate_order_status($_GET['status']) : '';

        $orders_data = $this->get_orders($current_page, $search, $status_filter);
        $orders = $orders_data['orders'];
        $total_orders = $orders_data['total'];
        $total_pages = ceil($total_orders / self::ORDERS_PER_PAGE);

        ?>
        <div class="wrap wc-order-pdf-export">
            <h1><?php _e('WooCommerce Order PDF Export', 'wc-order-pdf-export'); ?></h1>

            <?php $this->render_notices(); ?>
            <?php $this->render_filters($search, $status_filter); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_orders, 'wc-order-pdf-export'), number_format_i18n($total_orders)); ?>
                    </span>
                </div>
                <?php $this->render_pagination($current_page, $total_pages); ?>
            </div>

            <table class="wp-list-table widefat fixed striped orders">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-order-id">
                            <?php _e('Order ID', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-date">
                            <?php _e('Date', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-customer">
                            <?php _e('Customer', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <?php _e('Status', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-total">
                            <?php _e('Total', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-items">
                            <?php _e('Items', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-payment">
                            <?php _e('Payment Method', 'wc-order-pdf-export'); ?>
                        </th>
                        <th scope="col" class="manage-column column-actions">
                            <?php _e('Actions', 'wc-order-pdf-export'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)) : ?>
                        <tr>
                            <td colspan="8" class="no-orders">
                                <?php _e('No orders found.', 'wc-order-pdf-export'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($orders as $order) : ?>
                            <?php $this->render_order_row($order); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <?php $this->render_pagination($current_page, $total_pages); ?>
            </div>

            <?php $this->render_help_section(); ?>
        </div>
        <?php
    }

    /**
     * Render filters
     */
    private function render_filters($search, $status_filter)
    {
        $order_statuses = wc_get_order_statuses();
        ?>
        <div class="wc-order-filters">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                
                <input type="search" 
                       name="search" 
                       value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php _e('Search orders...', 'wc-order-pdf-export'); ?>">
                
                <select name="status">
                    <option value=""><?php _e('All statuses', 'wc-order-pdf-export'); ?></option>
                    <?php foreach ($order_statuses as $status => $label) : ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button" value="<?php _e('Filter', 'wc-order-pdf-export'); ?>">
                
                <?php if ($search || $status_filter) : ?>
                    <a href="<?php echo admin_url('admin.php?page=' . $_GET['page']); ?>" class="button">
                        <?php _e('Clear', 'wc-order-pdf-export'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render pagination
     */
    private function render_pagination($current_page, $total_pages)
    {
        if ($total_pages <= 1) {
            return;
        }

        $page_links = paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
            'type' => 'array'
        ]);

        if ($page_links) {
            echo '<div class="tablenav-pages">';
            echo '<span class="pagination-links">';
            echo implode('', $page_links);
            echo '</span>';
            echo '</div>';
        }
    }

    /**
     * Render order row
     */
    private function render_order_row($order)
    {
        $order_id = $order->get_id();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email();
        }
        
        ?>
        <tr>
            <td class="order-id">
                <strong>#<?php echo esc_html($order_id); ?></strong>
                <div class="row-actions">
                    <span class="view">
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); ?>">
                            <?php _e('View', 'wc-order-pdf-export'); ?>
                        </a>
                    </span>
                </div>
            </td>
            <td class="date">
                <?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
            </td>
            <td class="customer">
                <?php echo esc_html($customer_name); ?>
                <?php if ($order->get_billing_email()) : ?>
                    <br><small><?php echo esc_html($order->get_billing_email()); ?></small>
                <?php endif; ?>
            </td>
            <td class="status">
                <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                </span>
            </td>
            <td class="total">
                <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
            </td>
            <td class="items">
                <?php echo esc_html($order->get_item_count()); ?>
            </td>
            <td class="payment">
                <?php echo esc_html($order->get_payment_method_title()); ?>
            </td>
            <td class="actions">
                <button type="button" 
                        class="button button-primary download-pdf" 
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                    <?php _e('Download PDF', 'wc-order-pdf-export'); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    /**
     * Get orders with pagination and filtering
     */
    private function get_orders($page = 1, $search = '', $status_filter = '')
    {
        $args = [
            'limit' => self::ORDERS_PER_PAGE,
            'offset' => ($page - 1) * self::ORDERS_PER_PAGE,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        ];

        if (!empty($status_filter)) {
            $args['status'] = $status_filter;
        }

        if (!empty($search)) {
            // Search in order ID, customer name, or email
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_billing_first_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => '_billing_last_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => '_billing_email',
                    'value' => $search,
                    'compare' => 'LIKE'
                ]
            ];
        }

        $orders = wc_get_orders($args);

        // Get total count for pagination
        $count_args = $args;
        unset($count_args['limit'], $count_args['offset']);
        $total_orders = count(wc_get_orders($count_args));

        return [
            'orders' => $orders,
            'total' => $total_orders
        ];
    }

    /**
     * Render help section
     */
    private function render_help_section()
    {
        ?>
        <div class="wc-order-pdf-help" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 3px;">
            <h3><?php _e('How to Use', 'wc-order-pdf-export'); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Use the search box to find specific orders by customer name or email', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Filter orders by status using the dropdown menu', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Click "Download PDF" next to any order to generate and download a detailed PDF invoice', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('PDFs include complete order details, customer information, and itemized products', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('You can download up to 10 PDFs per minute for security reasons', 'wc-order-pdf-export'); ?></li>
            </ul>

            <h4><?php _e('PDF Contents', 'wc-order-pdf-export'); ?></h4>
            <p><?php _e('Each PDF includes:', 'wc-order-pdf-export'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Order details (ID, date, status)', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Customer billing and shipping addresses', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Itemized list of products with quantities and prices', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Order totals including taxes, shipping, and discounts', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Payment method and transaction information', 'wc-order-pdf-export'); ?></li>
                <li><?php _e('Customer notes (if any)', 'wc-order-pdf-export'); ?></li>
            </ul>
        </div>
        <?php
    }
}
