/**
 * WooCommerce Order PDF Export Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize PDF download functionality
        initPdfDownload();
        
        // Initialize search functionality
        initSearch();
    });

    /**
     * Initialize PDF download functionality
     */
    function initPdfDownload() {
        $(document).on('click', '.download-pdf', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var orderId = $button.data('order-id');
            
            if (!orderId) {
                showNotice('error', wcOrderPdfExport.strings.error);
                return;
            }
            
            // Disable button and show loading state
            $button.prop('disabled', true).addClass('loading');
            
            // Create a form and submit it to trigger download
            var $form = $('<form>', {
                method: 'POST',
                action: wcOrderPdfExport.ajaxUrl,
                style: 'display: none;'
            });
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'wc_order_pdf_download'
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'order_id',
                value: orderId
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: wcOrderPdfExport.nonce
            }));
            
            $('body').append($form);
            $form.submit();
            
            // Re-enable button after a delay
            setTimeout(function() {
                $button.prop('disabled', false).removeClass('loading');
                $form.remove();
                showNotice('success', 'PDF download started successfully!');
            }, 2000);
        });
    }

    /**
     * Initialize search functionality
     */
    function initSearch() {
        var $searchInput = $('input[name="search"]');
        var searchTimeout;
        
        // Auto-submit search after typing stops
        $searchInput.on('input', function() {
            clearTimeout(searchTimeout);
            var $form = $(this).closest('form');
            
            searchTimeout = setTimeout(function() {
                if ($searchInput.val().length >= 3 || $searchInput.val().length === 0) {
                    $form.submit();
                }
            }, 500);
        });
        
        // Clear search
        $('.clear-search').on('click', function(e) {
            e.preventDefault();
            $searchInput.val('');
            $('select[name="status"]').val('');
            $(this).closest('form').submit();
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var $notice = $('<div>', {
            class: 'notice notice-' + type + ' is-dismissible',
            html: '<p>' + message + '</p>'
        });
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Handle AJAX errors
     */
    $(document).ajaxError(function(event, xhr, settings) {
        if (settings.url === wcOrderPdfExport.ajaxUrl && 
            settings.data && settings.data.indexOf('wc_order_pdf_download') !== -1) {
            
            $('.download-pdf').prop('disabled', false).removeClass('loading');
            showNotice('error', wcOrderPdfExport.strings.error);
        }
    });

})(jQuery);
