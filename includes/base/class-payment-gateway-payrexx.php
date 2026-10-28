<?php

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

if (!class_exists('WPBS_Payment_Method')) {
    return;
}

/**
 * The main class for the Payment Method
 *
 */
class WPBS_Payment_Method_Payrexx extends WPBS_Payment_Method
{

    public function init()
    {

        $this->slug = 'payrexx';

        $this->name = 'Payrexx';

        $this->display_name = 'Payrexx';

        $this->description = 'Pay with TWINT, credit card or PostFinance via Payrexx.';

        // Enable support for deposit to be paid on the Secondary Payment page
        add_filter('wpbs_payment_method_supports_manual_deposit_payrexx', '__return_true');

        // Show a notice when the customer comes back from a failed or cancelled payment
        add_filter('wpbs_form_outputter_form', array($this, 'payment_cancelled_notice'), 5, 4);
    }

    /**
     * Translate the default labels lazily, the textdomain is not loaded yet when init() runs.
     *
     */
    public function defaults_labels()
    {
        return array(
            'display_name' => __('Payrexx', 'wp-booking-system-payrexx'),
            'description' => __('Pay with TWINT, credit card or PostFinance via Payrexx.', 'wp-booking-system-payrexx'),
        );
    }

    /**
     * Get the API Details
     *
     */
    public function api()
    {
        $api = WPBS_Payrexx_API::keys();

        // Check for API Keys
        if (empty($api['instance']) || empty($api['api_secret'])) {
            return false;
        }

        return $api;
    }

    /**
     * The payment screen in the booking form
     *
     * @param array $data
     *
     * @return array ['error' => bool, 'output' => string]
     *
     */
    public function payment_screen($data)
    {

        extract($data);

        /**
         * Get API Details
         *
         */
        $api = $this->api();

        // Check if api keys are present.
        if (!$api) {
            return [
                'error' => true,
                'output' => __("Please add your Payrexx instance name and API secret in the plugin's Settings Page.", 'wp-booking-system-payrexx')
            ];
        }

        /**
         * Add metadata
         *
         */
        $metadata = array_merge($metadata, $this->get_form_metadata($form_fields));
        $metadata = apply_filters('wpbs_payrexx_metadata', $metadata, $payment, $form_fields, $language);

        /**
         * Create the Gateway on Payrexx
         *
         */
        $currency = $payment->get_currency() ?: 'CHF';

        $payrexx = new WPBS_Payrexx();
        $response = $payrexx->createGateway($total, $currency, $invoice_item_description, $return_url, $booking_data_transient_id, $booking_page_url, $metadata, $language);

        if ($response['success'] === false) {
            return [
                'error' => true,
                'output' => $response['error']
            ];
        }

        /**
         * Remember the gateway so the confirmation page can check its status without the webhook.
         *
         */
        $booking_data = get_option($booking_data_transient_id);
        if (is_array($booking_data)) {
            $booking_data['payrexx_gateway_id'] = $response['gateway_id'];
            update_option($booking_data_transient_id, $booking_data, false);
        }

        /**
         * Prepare Response
         *
         */
        ob_start();

        $dir_path = plugin_dir_path(__FILE__);

        if (file_exists($dir_path . 'views/payrexx-payment-form.php')) {
            include $dir_path . 'views/payrexx-payment-form.php';
        }

        $payrexx_output = ob_get_contents();
        ob_end_clean();

        return ['output' => $payrexx_output];
    }

    /**
     * Collect form data as metadata
     *
     */
    public function get_form_metadata($form_fields)
    {
        $metadata = [];
        $client_email = false;
        foreach ($form_fields as $field) {
            if (!isset($field['user_value'])) {
                continue;
            }

            $value = wpbs_get_field_display_user_value($field['user_value']);
            $value = substr($value, 0, 500);

            if ($field['type'] == 'email' && !$client_email && is_email($value)) {
                $client_email = $value;
                $metadata['_payrexx_email'] = $client_email;
            }

            $metadata['field_' . $field['id']] = $value;
        }

        $metadata = apply_filters('wpbs_form_form_metadata', $metadata, $form_fields);

        return $metadata;
    }

    /**
     * Save the payment when creating the booking
     *
     */
    public function save_payment($booking_id, $post_data, $form, $form_args, $form_fields)
    {

        // Check if we got a gateway ID
        if (!isset($post_data['wpbs-payrexx-payment-id'])) {
            return false;
        }

        // Get price
        $payment = new WPBS_Payment;
        $details['price'] = $payment->calculate_prices($post_data, $form, $form_args, $form_fields);

        if (wpbs_part_payments_enabled() == true && $payment->is_part_payment()) {
            $details['part_payments'] = array('deposit' => false, 'final_payment' => false);
        }

        $payrexx = new WPBS_Payrexx();
        $gateway = $payrexx->getGateway($post_data['wpbs-payrexx-payment-id']);
        $gateway_status = $gateway ? WPBS_Payrexx::getGatewayStatus($gateway) : array('status' => 'pending', 'reason' => 'Gateway not found');

        $status = $gateway_status['status'];

        $details['raw'] = $gateway ? $gateway : array('id' => $post_data['wpbs-payrexx-payment-id']);
        $details['status_reason'] = $gateway_status['reason'];
        $details['transaction_id'] = $this->get_transaction_id($gateway);
        $details['initial_amount_received'] = 0;

        if ($status == 'completed') {
            $details['initial_amount_received'] = WPBS_Payrexx::from_cents($gateway['amount']);

            if (isset($details['part_payments']['deposit'])) {
                $details['part_payments']['deposit'] = true;
            }
        }

        if ($status == 'error') {
            $details['status_reason'] = $this->get_payment_failed_reason($gateway);
            do_action('wpbs_payment_failed_action', $booking_id);
        }

        // Save Order
        wpbs_insert_payment(array(
            'booking_id'    => $booking_id,
            'gateway'       => 'payrexx',
            'order_id'      => $gateway ? $gateway['id'] : $post_data['wpbs-payrexx-payment-id'],
            'order_status'  => $status,
            'details'       => $details,
            'date_created'  => current_time('Y-m-d H:i:s'),
        ));
    }

    /**
     * When the customer is redirected back, check the gateway ourselves.
     *
     * This makes the add-on work without the webhook (e.g. on localhost) and speeds up the confirmation.
     *
     */
    public function maybe_create_booking_on_thank_you_page()
    {
        if (!isset($_GET['wpbs-transient-id'])) {
            return false;
        }

        $this->maybe_process_transient(sanitize_text_field(wp_unslash($_GET['wpbs-transient-id'])));
    }

    /**
     * Process the gateway stored on a booking data transient, at most every few seconds.
     *
     */
    protected function maybe_process_transient($transient_id)
    {
        if (strpos($transient_id, 'wpbs-payrexx-') !== 0) {
            return false;
        }

        $confirmation = get_option('booking-confirmation-' . $transient_id);
        if ($confirmation && empty($confirmation['error'])) {
            return false;
        }

        $booking_data = get_option($transient_id);

        if (empty($booking_data['payrexx_gateway_id'])) {
            return false;
        }

        $throttle = 'wpbs_payrexx_check_' . md5($transient_id);
        if (get_transient($throttle)) {
            return false;
        }
        set_transient($throttle, 1, 3);

        $this->process_gateway($booking_data['payrexx_gateway_id']);
    }

    /**
     * Webhook
     *
     * Payrexx posts the transaction (form encoded or JSON). We never trust the payload:
     * the gateway is always read again from the Payrexx API before anything is changed.
     *
     */
    public function webhook()
    {
        $payload = $_POST;

        if (empty($payload)) {
            $payload = json_decode(file_get_contents('php://input'), true);
        } else {
            $payload = wp_unslash($payload);
        }

        if (!is_array($payload) || empty($payload['transaction']) || !is_array($payload['transaction'])) {
            echo ('WPBS_Payrexx: Missing transaction.');
            http_response_code(200);
            exit;
        }

        if (!$this->api()) {
            echo ('WPBS_Payrexx: Missing API credentials.');
            http_response_code(500);
            exit;
        }

        $transaction = $payload['transaction'];
        $gateway_id = isset($transaction['invoice']['paymentRequestId']) ? intval($transaction['invoice']['paymentRequestId']) : 0;

        // Fall back to reading the transaction from the API
        if (!$gateway_id && !empty($transaction['id'])) {
            $payrexx = new WPBS_Payrexx();
            $api_transaction = $payrexx->getTransaction($transaction['id']);
            $gateway_id = isset($api_transaction['invoice']['paymentRequestId']) ? intval($api_transaction['invoice']['paymentRequestId']) : 0;
        }

        if (!$gateway_id) {
            echo ('WPBS_Payrexx: Transaction does not belong to a gateway.');
            http_response_code(200);
            exit;
        }

        echo ('WPBS_Payrexx: ' . $this->process_gateway($gateway_id));

        http_response_code(200);
        exit;
    }

    /**
     * Read a gateway from Payrexx and create or update the booking and payment.
     *
     * Used by the webhook, the thank you page and the confirmation polling.
     *
     * @return string Log message
     *
     */
    public function process_gateway($gateway_id)
    {
        $payrexx = new WPBS_Payrexx();
        $gateway = $payrexx->getGateway($gateway_id);

        if (!$gateway) {
            return 'Gateway does not exist for ID ' . intval($gateway_id);
        }

        $transient_id = isset($gateway['referenceId']) ? $gateway['referenceId'] : '';

        // Ignore gateways created by other shops on the same Payrexx instance
        if (strpos($transient_id, 'wpbs-payrexx-') !== 0) {
            return 'Gateway does not belong to WP Booking System';
        }

        $lock_name = 'wpbs_payrexx_lock_' . md5($transient_id);
        $lock = get_option($lock_name);
        if ($lock && $lock < time() - MINUTE_IN_SECONDS) {
            delete_option($lock_name);
        }
        if (false === add_option($lock_name, time(), '', 'no')) {
            return 'Gateway is being processed';
        }

        try {
            return $this->process_gateway_locked($gateway, $transient_id);
        } finally {
            delete_option($lock_name);
        }
    }

    protected function process_gateway_locked($gateway, $transient_id)
    {
        $gateway_id = $gateway['id'];
        $gateway_status = WPBS_Payrexx::getGatewayStatus($gateway);

        if ($gateway_status['status'] == 'pending') {
            return 'Payment ' . $gateway_status['reason'];
        }

        if ($gateway_status['reason'] == 'Refunded') {
            // We track refunds differently.
            return 'Refund webhook';
        }

        // Get booking data from transient
        $booking_data = get_option($transient_id);

        if ($gateway_status['status'] == 'error') {
            if (!get_option('booking-confirmation-' . $transient_id)) {
                add_option('booking-confirmation-' . $transient_id, array('error' => true, 'error_message' => $this->get_payment_failed_reason($gateway), 'booking_id' => false, 'payrexx_payment_id' => $gateway_id), '', 'no');
            }

            if (!empty($booking_data['booking_id'])) {
                do_action('wpbs_payment_failed_action', $booking_data['booking_id']);
            }

            return 'Payment ' . $gateway_status['reason'];
        }

        // A declined attempt can be followed by a successful one on the same Payrexx page.
        $previous_confirmation = get_option('booking-confirmation-' . $transient_id);
        if (!empty($previous_confirmation['error'])) {
            delete_option('booking-confirmation-' . $transient_id);
        }

        $amount_received = WPBS_Payrexx::from_cents($gateway['amount']);

        /**
         * Secondary payment (final payment of a part payment)
         *
         */
        if (!empty($booking_data['is_secondary_payment'])) {

            if (empty($booking_data['booking_id'])) {
                return 'Secondary Payment Invalid Booking ID';
            }

            $booking_id = $booking_data['booking_id'];

            $payment = wpbs_get_payment_by_booking_id($booking_id);

            if (!$payment) {
                return 'Secondary Payment Invalid Payment';
            }

            if ($payment->is_final_payment_paid()) {
                return 'Secondary Payment Already Fulfilled';
            }

            $details = $payment->get('details');
            $details['final_payment'] = $gateway;
            $details['final_transaction_id'] = $this->get_transaction_id($gateway);
            $details['part_payments']['final_payment'] = true;
            $details['final_amount_received'] = $amount_received;
            $details['status_reason'] = $gateway_status['reason'];

            wpbs_update_payment($payment->get('id'), array(
                'order_status' => 'completed',
                'details' => $details,
            ));

            if (!get_option('booking-confirmation-' . $transient_id)) {
                add_option('booking-confirmation-' . $transient_id, array('booking_id' => $booking_id, 'confirmation' => $this->part_payment_confirmation($booking_data['language']), 'payrexx_payment_id' => $gateway_id), '', 'no');
                do_action('wpbs_save_final_payment', $payment);
            }

            delete_option($transient_id);

            return 'Secondary Payment Saved';
        }

        /**
         * Deposit of a manually created booking, paid on the secondary payment page.
         *
         */
        if (!empty($booking_data['booking_id'])) {

            $payment = wpbs_get_payment_by_booking_id($booking_data['booking_id']);

            if ($payment) {
                $this->update_payment_from_gateway($payment, $gateway);
            }

            $this->create_booking($transient_id, $gateway_id);
            delete_option($transient_id);

            return 'Deposit Saved';
        }

        /**
         * New booking: create it from the transient data.
         *
         */
        if ($booking_data) {
            $this->create_booking($transient_id, $gateway_id);
            return 'Booking Created';
        }

        /**
         * The booking already exists, update the payment status.
         *
         */
        $confirmation = get_option('booking-confirmation-' . $transient_id);

        if (empty($confirmation['booking_id'])) {
            return 'No Booking ID';
        }

        $payment = wpbs_get_payment_by_booking_id($confirmation['booking_id']);

        if ($payment === false) {
            return 'No Payment';
        }

        if ($payment->get('order_status') == 'completed') {
            return 'Payment Already Completed';
        }

        $this->update_payment_from_gateway($payment, $gateway);

        return 'Payment Updated';
    }

    /**
     * Mark an existing payment as paid from a confirmed gateway.
     *
     */
    protected function update_payment_from_gateway($payment, $gateway)
    {
        $details = $payment->get('details');
        $details['raw'] = $gateway;
        $details['transaction_id'] = $this->get_transaction_id($gateway);
        $details['status_reason'] = 'Paid';
        $details['initial_amount_received'] = WPBS_Payrexx::from_cents($gateway['amount']);

        if ($payment->is_part_payment()) {
            $details['part_payments']['deposit'] = true;
        } else {
            $details['price']['paid'] = true;
        }

        wpbs_update_payment($payment->get('id'), array(
            'order_id' => $gateway['id'],
            'order_status' => 'completed',
            'details' => $details,
        ));
    }

    /**
     * Confirmation message after a part payment
     *
     */
    protected function part_payment_confirmation($language)
    {
        return [
            'type' => 'message',
            'message' => isset($this->settings['payment_part_payments_confirmation_translation_' . $language]) && $this->settings['payment_part_payments_confirmation_translation_' . $language] ? $this->settings['payment_part_payments_confirmation_translation_' . $language] : (isset($this->settings['payment_part_payments_confirmation']) && $this->settings['payment_part_payments_confirmation'] ? $this->settings['payment_part_payments_confirmation'] : __('The form was successfully submitted.', 'wp-booking-system'))
        ];
    }

    /**
     * AJAX function to get the payment status while we're on the confirmation page.
     *
     * If we get a positive response, we show the confirmation message saved when the form was submitted.
     * Otherwise we show an error.
     *
     */
    public function payment_confirmation_get_payment_status()
    {

        $data = json_decode(stripslashes($_POST['data']), true);

        if (!isset($data['wpbs-transient-id'])) {
            exit;
        }

        $transient_id = sanitize_text_field($data['wpbs-transient-id']);

        $booking_data = get_option('booking-confirmation-' . $transient_id);

        // No webhook yet? Ask Payrexx ourselves.
        if (!$booking_data) {
            $this->maybe_process_transient($transient_id);
            $booking_data = get_option('booking-confirmation-' . $transient_id);
        }

        if (isset($booking_data['error'])) {
            echo json_encode([
                'type' => 'message',
                'message' => '<p class="wpbs-form-general-error">' . __('Payment failed: ', 'wp-booking-system-payrexx') . (isset($booking_data['error_message']) ? esc_html($booking_data['error_message']) : __('No message provided.', 'wp-booking-system-payrexx')) . '</p>'
            ]);
            exit;
        }

        if (!isset($booking_data['booking_id'])) {
            echo 'refresh';
            exit;
        }

        // Do now show the confirmation message twice.
        if (isset($booking_data['confirmation_displayed'])) {
            echo json_encode([
                'type' => 'already_displayed',
                'message' => ''
            ]);
            exit;
        }

        $booking = wpbs_get_booking($booking_data['booking_id']);

        if (!$booking) {
            echo json_encode([
                'type' => 'message',
                'message' => '<p class="wpbs-form-general-error">' . __('Payment failed: ', 'wp-booking-system-payrexx') . __('No booking found.', 'wp-booking-system-payrexx') . '</p>'
            ]);
            exit;
        }

        $payment = wpbs_get_payment_by_booking_id($booking->get('id'));

        if (!$payment) {
            echo json_encode([
                'type' => 'message',
                'message' => '<p class="wpbs-form-general-error">' . __('Payment failed: ', 'wp-booking-system-payrexx') . __('Payment not found in the database.', 'wp-booking-system-payrexx') . '</p>'
            ]);
            exit;
        }

        $gateway_id = !empty($booking_data['payrexx_payment_id']) ? $booking_data['payrexx_payment_id'] : $payment->get('order_id');

        $payrexx = new WPBS_Payrexx();
        $gateway = $payrexx->getGateway($gateway_id);

        if (!$gateway) {
            echo 'refresh';
            exit;
        }

        $gateway_status = WPBS_Payrexx::getGatewayStatus($gateway);

        if ($gateway_status['status'] != 'completed') {
            echo json_encode([
                'type' => 'message',
                'message' => '<p class="wpbs-form-general-error">' . __('Payment failed: ', 'wp-booking-system-payrexx') . esc_html($this->get_payment_failed_reason($gateway)) . '</p>'
            ]);
            exit;
        }

        $booking_data['confirmation']['form_id'] = $booking->get('form_id');
        $booking_data['confirmation']['calendar_id'] = $booking->get('calendar_id');

        // Return the stored confirmation data
        echo json_encode($booking_data['confirmation']);

        // Mark the confirmation as displayed so we don't show it twice
        $booking_data['confirmation_displayed'] = true;

        update_option('booking-confirmation-' . $transient_id, $booking_data);

        exit;
    }

    /**
     * Show a notice above the form when the customer cancelled the payment or it failed.
     *
     */
    public function payment_cancelled_notice($output, $form, $args, $calendar_id)
    {
        if (empty($_GET['wpbs_payrexx_message'])) {
            return $output;
        }

        if ($_GET['wpbs_payrexx_message'] == 'cancelled') {
            $message = __('The payment was cancelled. Your booking has not been made, please try again.', 'wp-booking-system-payrexx');
        } else {
            $message = __('The payment failed. Your booking has not been made, please try again.', 'wp-booking-system-payrexx');
        }

        return '<p class="wpbs-form-general-error">' . esc_html($message) . '</p>' . $output;
    }

    /**
     * The Settings page file content
     *
     */
    public function settings_page_content($defaults)
    {
        include_once WPBS_PAYREXX_PLUGIN_DIR . 'includes/base/admin/settings/views/view-payment-settings-payrexx.php';
    }

    /**
     * The id of the confirmed transaction of a gateway
     *
     */
    public function get_transaction_id($gateway)
    {
        if (!$gateway) {
            return '';
        }

        $transaction = WPBS_Payrexx::getConfirmedTransaction($gateway);

        return isset($transaction['id']) ? $transaction['id'] : '';
    }

    public function get_payment_failed_reason($gateway, $default_reason = "No reason provided")
    {
        $transactions = WPBS_Payrexx::getGatewayTransactions($gateway);

        if (!empty($transactions[0]['status'])) {
            switch ($transactions[0]['status']) {
                case 'declined':
                    return __('The payment was declined.', 'wp-booking-system-payrexx');
                case 'cancelled':
                    return __('The payment was cancelled.', 'wp-booking-system-payrexx');
                case 'expired':
                    return __('The payment expired.', 'wp-booking-system-payrexx');
                case 'error':
                    return __('An error occurred during the payment.', 'wp-booking-system-payrexx');
            }
            return ucfirst($transactions[0]['status']);
        }

        return $default_reason;
    }
}

$payrexx = new WPBS_Payment_Method_Payrexx();
