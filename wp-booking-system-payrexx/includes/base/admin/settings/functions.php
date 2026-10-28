<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save Payrexx API credentials in a separate option field.
 *
 * Hooked into pre_update_option so the credentials are also saved when
 * nothing else in wpbs_settings changed (updated_option would not fire then).
 *
 */
function wpbs_payrexx_save_api_keys($value, $old_value)
{
    // If isset payrexx api post data
    if (!isset($_POST['wpbs_payrexx_api']) || !is_array($_POST['wpbs_payrexx_api'])) {
        return $value;
    }

    if (!current_user_can(apply_filters('wpbs_submenu_page_capability_settings', 'manage_options'))) {
        return $value;
    }

    $post = wp_unslash($_POST['wpbs_payrexx_api']);

    // Do the update
    update_option('wpbs_payrexx_api', array(
        'payment_payrexx_instance' => isset($post['payment_payrexx_instance']) ? WPBS_Payrexx_API::sanitize_instance($post['payment_payrexx_instance']) : '',
        'payment_payrexx_api_secret' => isset($post['payment_payrexx_api_secret']) ? sanitize_text_field($post['payment_payrexx_api_secret']) : '',
    ));

    return $value;
}
add_filter('pre_update_option_wpbs_settings', 'wpbs_payrexx_save_api_keys', 10, 2);
