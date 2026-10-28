<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Includes the Base files
 *
 */
function wpbs_payrexx_include_files_base()
{
    // Get dir path
    $dir_path = plugin_dir_path(__FILE__);

    // Include Payrexx API client (plain HTTP, no external dependencies)
    include_once WPBS_PAYREXX_PLUGIN_DIR . 'includes/libs/payrexx-api/payrexx-api.php';

    // Include Payment Class 
    if (file_exists($dir_path . 'class-payment-gateway-payrexx.php')) {
        include $dir_path . 'class-payment-gateway-payrexx.php';
    }

    // Include Refunds Functions
    if (file_exists($dir_path . 'functions-refunds.php')) {
        include $dir_path . 'functions-refunds.php';
    }
}
add_action('wpbs_payrexx_include_files', 'wpbs_payrexx_include_files_base');