<?php

namespace Hrrarya\WoocommerceOrderPdfExport;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF Generator Class
 */
class PdfGenerator
{
    /**
     * Generate PDF for a specific order
     */
    public function generate_order_pdf($order_id)
    {
        try {
            // Validate order ID
            $order_id = intval($order_id);
            if ($order_id <= 0) {
                throw new \InvalidArgumentException(__('Invalid order ID provided', 'wc-order-pdf-export'));
            }

            $order = wc_get_order($order_id);

            if (!$order) {
                throw new \Exception(__('Order not found', 'wc-order-pdf-export'));
            }

            // Check if order has required data
            if (!$order->get_id()) {
                throw new \Exception(__('Order data is incomplete', 'wc-order-pdf-export'));
            }

            $html = $this->generate_order_html($order);

            if (empty($html)) {
                throw new \Exception(__('Failed to generate PDF content', 'wc-order-pdf-export'));
            }

            // Configure Dompdf with security settings
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false); // Disable remote content for security
            $options->set('isHtml5ParserEnabled', true);
            $options->set('debugKeepTemp', false);
            $options->set('debugCss', false);
            $options->set('debugLayout', false);
            $options->set('debugLayoutLines', false);
            $options->set('debugLayoutBlocks', false);
            $options->set('debugLayoutInline', false);
            $options->set('debugLayoutPaddingBox', false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Sanitize filename
            $filename = 'order-' . $order_id . '.pdf';
            $filename = Security::sanitize_pdf_filename($filename);

            $dompdf->stream($filename, ['Attachment' => true]);
            exit;

        } catch (\Exception $e) {
            error_log('WC Order PDF Export - PDF Generation Error: ' . $e->getMessage());
            wp_die(__('Failed to generate PDF. Please try again or contact support.', 'wc-order-pdf-export'), 500);
        }
    }

    /**
     * Generate HTML content for order PDF
     */
    private function generate_order_html($order)
    {
        $order_id = $order->get_id();
        $order_date = $order->get_date_created();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php printf(__('Order #%s', 'wc-order-pdf-export'), $order_id); ?></title>
            <style>
                <?php echo $this->get_pdf_styles(); ?>
            </style>
        </head>
        <body>
            <div class="pdf-container">
                <!-- Header -->
                <div class="header">
                    <div class="company-info">
                        <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                        <p><?php echo esc_html(get_bloginfo('description')); ?></p>
                    </div>
                    <div class="order-info">
                        <h2><?php printf(__('Order #%s', 'wc-order-pdf-export'), $order_id); ?></h2>
                        <p><strong><?php _e('Date:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html($order_date->date_i18n(get_option('date_format'))); ?></p>
                        <p><strong><?php _e('Status:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></p>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="customer-section">
                    <div class="billing-address">
                        <h3><?php _e('Billing Address', 'wc-order-pdf-export'); ?></h3>
                        <div class="address">
                            <?php echo wp_kses_post($order->get_formatted_billing_address()); ?>
                        </div>
                        <?php if ($order->get_billing_email()) : ?>
                            <p><strong><?php _e('Email:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html($order->get_billing_email()); ?></p>
                        <?php endif; ?>
                        <?php if ($order->get_billing_phone()) : ?>
                            <p><strong><?php _e('Phone:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html($order->get_billing_phone()); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($order->get_formatted_shipping_address()) : ?>
                    <div class="shipping-address">
                        <h3><?php _e('Shipping Address', 'wc-order-pdf-export'); ?></h3>
                        <div class="address">
                            <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Order Items -->
                <div class="order-items">
                    <h3><?php _e('Order Items', 'wc-order-pdf-export'); ?></h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th class="product-name"><?php _e('Product', 'wc-order-pdf-export'); ?></th>
                                <th class="product-quantity"><?php _e('Qty', 'wc-order-pdf-export'); ?></th>
                                <th class="product-price"><?php _e('Price', 'wc-order-pdf-export'); ?></th>
                                <th class="product-total"><?php _e('Total', 'wc-order-pdf-export'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order->get_items() as $item_id => $item) : ?>
                                <?php $product = $item->get_product(); ?>
                                <tr>
                                    <td class="product-name">
                                        <strong><?php echo esc_html($item->get_name()); ?></strong>
                                        <?php if ($product && $product->get_sku()) : ?>
                                            <br><small><?php printf(__('SKU: %s', 'wc-order-pdf-export'), esc_html($product->get_sku())); ?></small>
                                        <?php endif; ?>
                                        <?php
                                        $item_meta = $item->get_formatted_meta_data();
                                        if ($item_meta) :
                                            foreach ($item_meta as $meta) :
                                                echo '<br><small>' . esc_html($meta->display_key) . ': ' . esc_html($meta->display_value) . '</small>';
                                            endforeach;
                                        endif;
                                        ?>
                                    </td>
                                    <td class="product-quantity"><?php echo esc_html($item->get_quantity()); ?></td>
                                    <td class="product-price"><?php echo wp_kses_post(wc_price($item->get_total() / $item->get_quantity())); ?></td>
                                    <td class="product-total"><?php echo wp_kses_post(wc_price($item->get_total())); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Order Totals -->
                <div class="order-totals">
                    <table class="totals-table">
                        <tbody>
                            <tr class="subtotal">
                                <td class="label"><?php _e('Subtotal:', 'wc-order-pdf-export'); ?></td>
                                <td class="value"><?php echo wp_kses_post(wc_price($order->get_subtotal())); ?></td>
                            </tr>
                            
                            <?php if ($order->get_total_shipping() > 0) : ?>
                            <tr class="shipping">
                                <td class="label"><?php _e('Shipping:', 'wc-order-pdf-export'); ?></td>
                                <td class="value"><?php echo wp_kses_post(wc_price($order->get_total_shipping())); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($order->get_total_tax() > 0) : ?>
                            <tr class="tax">
                                <td class="label"><?php _e('Tax:', 'wc-order-pdf-export'); ?></td>
                                <td class="value"><?php echo wp_kses_post(wc_price($order->get_total_tax())); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($order->get_total_discount() > 0) : ?>
                            <tr class="discount">
                                <td class="label"><?php _e('Discount:', 'wc-order-pdf-export'); ?></td>
                                <td class="value">-<?php echo wp_kses_post(wc_price($order->get_total_discount())); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="total">
                                <td class="label"><strong><?php _e('Total:', 'wc-order-pdf-export'); ?></strong></td>
                                <td class="value"><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Payment Information -->
                <?php if ($order->get_payment_method_title()) : ?>
                <div class="payment-info">
                    <h3><?php _e('Payment Information', 'wc-order-pdf-export'); ?></h3>
                    <p><strong><?php _e('Payment Method:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html($order->get_payment_method_title()); ?></p>
                    <?php if ($order->get_transaction_id()) : ?>
                        <p><strong><?php _e('Transaction ID:', 'wc-order-pdf-export'); ?></strong> <?php echo esc_html($order->get_transaction_id()); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Order Notes -->
                <?php if ($order->get_customer_note()) : ?>
                <div class="order-notes">
                    <h3><?php _e('Order Notes', 'wc-order-pdf-export'); ?></h3>
                    <p><?php echo esc_html($order->get_customer_note()); ?></p>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="footer">
                    <p><?php printf(__('Generated on %s', 'wc-order-pdf-export'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Get PDF styles
     */
    private function get_pdf_styles()
    {
        return '
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }
            
            .pdf-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                display: table;
                width: 100%;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            
            .company-info {
                display: table-cell;
                width: 50%;
                vertical-align: top;
            }
            
            .order-info {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                text-align: right;
            }
            
            .company-info h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
                color: #333;
            }
            
            .order-info h2 {
                margin: 0 0 10px 0;
                font-size: 20px;
                color: #333;
            }
            
            .customer-section {
                display: table;
                width: 100%;
                margin-bottom: 30px;
            }
            
            .billing-address,
            .shipping-address {
                display: table-cell;
                width: 48%;
                vertical-align: top;
                padding: 15px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
            }
            
            .shipping-address {
                margin-left: 4%;
            }
            
            .customer-section h3 {
                margin: 0 0 15px 0;
                font-size: 16px;
                color: #333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
            }
            
            .address {
                margin-bottom: 10px;
            }
            
            .order-items {
                margin-bottom: 30px;
            }
            
            .order-items h3 {
                margin: 0 0 15px 0;
                font-size: 16px;
                color: #333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .items-table th,
            .items-table td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }
            
            .items-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            
            .product-quantity,
            .product-price,
            .product-total {
                text-align: right;
                width: 15%;
            }
            
            .product-name {
                width: 55%;
            }
            
            .order-totals {
                margin-bottom: 30px;
            }
            
            .totals-table {
                width: 300px;
                margin-left: auto;
                border-collapse: collapse;
            }
            
            .totals-table td {
                padding: 8px 15px;
                border-bottom: 1px solid #ddd;
            }
            
            .totals-table .label {
                text-align: right;
                width: 60%;
            }
            
            .totals-table .value {
                text-align: right;
                width: 40%;
            }
            
            .totals-table .total td {
                border-top: 2px solid #333;
                border-bottom: 2px solid #333;
                font-size: 14px;
            }
            
            .payment-info,
            .order-notes {
                margin-bottom: 20px;
            }
            
            .payment-info h3,
            .order-notes h3 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #333;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        ';
    }
}
