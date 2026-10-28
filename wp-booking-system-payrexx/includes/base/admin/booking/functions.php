<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add payment details to the Booking Modal
 *
 * @param WPBS_Booking
 *
 */
function wpbs_payrexx_booking_modal_payment_tab_content($booking)
{

    $payment = wpbs_get_payment_by_booking_id($booking->get('id'));

    // Check if there is an order for this booking
    if (empty($payment)) {
        return false;
    }
    // Check if it's a Payrexx order
    if ($payment->get('gateway') != 'payrexx') {
        return false;
    }

    $order_details = $payment->get('details');
    $payrexx_data = isset($order_details['raw']) ? $order_details['raw'] : array();

    // Payment Information
    $payment_information = array(
        array('label' => 'Order Status', 'value' => ucwords($payment->get('order_status'))),
        array('label' => 'Payment Gateway', 'value' => 'Payrexx'),
        array('label' => 'Date', 'value' => date('j F Y, H:i:s', strtotime($payment->get('date_created')))),
        array('label' => 'ID', 'value' => '#' . $payment->get('id')),
    );

    if ($payment->get('order_status') == 'error') {
        $payment_information[] = array('label' => 'Error', 'value' => $order_details['status_reason']);
    }

    $payrexx_ids = array();

    if (isset($payrexx_data['id'])) {
        $payrexx_ids[] = __('Gateway', 'wp-booking-system-payrexx') . ' ' . $payrexx_data['id'] . (!empty($order_details['transaction_id']) ? ' / ' . __('Transaction', 'wp-booking-system-payrexx') . ' ' . $order_details['transaction_id'] : '');
    }

    if (isset($order_details['final_payment']['id'])) {
        $payrexx_ids[] = __('Gateway', 'wp-booking-system-payrexx') . ' ' . $order_details['final_payment']['id'] . (!empty($order_details['final_transaction_id']) ? ' / ' . __('Transaction', 'wp-booking-system-payrexx') . ' ' . $order_details['final_transaction_id'] : '');
    }

    if ($payrexx_ids) {
        $payment_information[] = array('label' => 'Transaction ID', 'value' => implode('<br>', array_map('esc_html', $payrexx_ids)));
    }

    $transaction = WPBS_Payrexx::getConfirmedTransaction($payrexx_data);
    if (!empty($transaction['payment']['brand'])) {
        $payment_information[] = array('label' => 'Payment Instrument', 'value' => esc_html(ucwords(str_replace(array('_', '-'), ' ', $transaction['payment']['brand']))));
    }

    // Order Information

    $order_information = $payment->get_line_items();

    $order_information = apply_filters('wpbs_booking_details_order_information', $order_information, $payment);

    $initial_amount_received = (isset($order_details['initial_amount_received'])) ? $order_details['initial_amount_received'] : 0;

    $final_amount_received = (isset($order_details['final_amount_received'])) ? $order_details['final_amount_received'] : 0;

    $amount_received = min($initial_amount_received + $final_amount_received, $payment->get_total());

    $order_information[] = array('label' => 'Amount Received', 'value' => wpbs_get_formatted_price($amount_received, strtoupper($payment->get_currency())));

    // Include view file
    include WPBS_PLUGIN_DIR . '/includes/modules/pricing/booking/views/view-modal-payment-details-content.php';
}
add_action('wpbs_booking_modal_tab_content_payment', 'wpbs_payrexx_booking_modal_payment_tab_content', 10, 1);
