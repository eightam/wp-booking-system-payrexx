<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_filter('wpbs_payment_details_refunds_available', function ($status, $payment) {
    if ($payment->get_payment_gateway() == 'payrexx') {
        return true;
    }
    return $status;
}, 10, 2);

/**
 * List the refundable Payrexx transactions of a payment.
 *
 * Refunds are made on the transaction, not on the gateway.
 *
 */
add_filter('wpbs_payment_details_refund_charges', function ($charges, $payment) {

    if ($payment->get_payment_gateway() != 'payrexx') {
        return $charges;
    }

    $payment_details = $payment->get('details');

    $charge_ids = [];

    if (!$payment->is_part_payment()) {
        if (!empty($payment_details['transaction_id'])) {
            $charge_ids[] = [
                'id' => $payment_details['transaction_id'],
                'name' => __('Charge', 'wp-booking-system-payrexx'),
                'amount' => isset($payment_details['initial_amount_received']) ? $payment_details['initial_amount_received'] : 0,
            ];
        }
    } else {
        if ($payment->is_deposit_paid() && !empty($payment_details['transaction_id'])) {
            $charge_ids[] = [
                'id' => $payment_details['transaction_id'],
                'name' => __('Deposit', 'wp-booking-system-payrexx'),
                'amount' => isset($payment_details['initial_amount_received']) ? $payment_details['initial_amount_received'] : 0,
            ];
        }
        if ($payment->is_final_payment_paid() && !empty($payment_details['final_transaction_id'])) {
            $charge_ids[] = [
                'id' => $payment_details['final_transaction_id'],
                'name' => __('Final Payment', 'wp-booking-system-payrexx'),
                'amount' => isset($payment_details['final_amount_received']) ? $payment_details['final_amount_received'] : 0,
            ];
        }
    }

    $refunds = [];

    if (isset($payment_details['refunds'])) {
        foreach ($payment_details['refunds'] as $refund) {
            if (!isset($refunds[$refund['charge']])) {
                $refunds[$refund['charge']] = 0;
            }

            $refunds[$refund['charge']] += $refund['amount'];
        }
    }

    foreach ($charge_ids as $charge_id) {

        $amount = floatval($charge_id['amount']);
        $refunded = isset($refunds[$charge_id['id']]) ? $refunds[$charge_id['id']] : 0;

        $charges[] = [
            'name' => $charge_id['name'],
            'id' => $charge_id['id'],
            'amount' => $amount,
            'refunded' => $refunded,
            'available' => max(0, round($amount - $refunded, 2)),
        ];
    }

    return $charges;
}, 10, 3);

/**
 * Refund a Payrexx transaction
 *
 */
function wpbs_process_refund_payrexx($charge_id, $amount, $payment)
{

    if (empty($charge_id)) {
        return ['error' => true, 'error_message' => 'Missing Transaction ID'];
    }

    if (empty($amount)) {
        return ['error' => true, 'error_message' => 'Missing Amount'];
    }

    $amount = floatval($amount);

    $payrexx = new WPBS_Payrexx();

    $refund = $payrexx->refundTransaction($charge_id, $amount, $payment);

    if (is_array($refund) && isset($refund['error'])) {
        return $refund;
    }

    return ['success' => true, 'response' => $refund, 'amount' => $amount];
}
