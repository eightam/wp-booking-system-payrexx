<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WPBS_Payrexx_API
{
    public static function keys()
    {
        $payrexx_api = get_option('wpbs_payrexx_api', array());

        return array(
            'instance' => isset($payrexx_api['payment_payrexx_instance']) ? self::sanitize_instance($payrexx_api['payment_payrexx_instance']) : '',
            'api_secret' => isset($payrexx_api['payment_payrexx_api_secret']) ? trim($payrexx_api['payment_payrexx_api_secret']) : '',
        );
    }

    /**
     * Accepts "meinhotel", "meinhotel.payrexx.com" or "https://meinhotel.payrexx.com/" and returns "meinhotel".
     *
     */
    public static function sanitize_instance($instance)
    {
        $instance = strtolower(trim($instance));
        $instance = preg_replace('#^https?://#', '', $instance);
        $instance = preg_replace('#\.payrexx\.com.*$#', '', $instance);
        $instance = trim($instance, '/');

        return preg_replace('/[^a-z0-9\-_]/', '', $instance);
    }
}

class WPBS_Payrexx
{

    protected $instance;

    protected $api_secret;

    public function __construct()
    {
        $api = WPBS_Payrexx_API::keys();

        $this->instance = $api['instance'];
        $this->api_secret = $api['api_secret'];
    }

    /**
     * Payrexx expects amounts in the smallest currency unit (Rappen / cents).
     *
     */
    public static function to_cents($amount)
    {
        return (int) round(floatval($amount) * 100);
    }

    public static function from_cents($amount)
    {
        return round(intval($amount) / 100, 2);
    }

    /**
     * Do a signed request against the Payrexx REST API.
     *
     * @return array ['success' => bool, 'data' => array|null, 'error' => string]
     *
     */
    protected function request($method, $endpoint, $params = array())
    {
        $base_url = apply_filters('wpbs_payrexx_api_base_url', 'https://api.payrexx.com/v1.0/');

        // The signature is a base64 encoded HMAC-SHA256 of the url encoded parameters, without the instance.
        $query = http_build_query($params, '', '&');
        $params['ApiSignature'] = base64_encode(hash_hmac('sha256', $query, $this->api_secret, true));

        $url = trailingslashit($base_url . trim($endpoint, '/')) . '?' . http_build_query(array('instance' => $this->instance), '', '&');

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        if ($method == 'GET') {
            $url .= '&' . http_build_query(array('ApiSignature' => $params['ApiSignature']), '', '&');
        } else {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query($params, '', '&');
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array('success' => false, 'data' => null, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || !isset($body['status']) || $body['status'] != 'success') {
            $message = (is_array($body) && !empty($body['message'])) ? $body['message'] : 'HTTP ' . wp_remote_retrieve_response_code($response);
            return array('success' => false, 'data' => null, 'error' => $message);
        }

        $data = isset($body['data']) ? $body['data'] : array();

        // Single objects are returned as a list with one element.
        if (is_array($data) && isset($data[0]) && count($data) == 1) {
            $data = $data[0];
        }

        return array('success' => true, 'data' => $data, 'error' => '');
    }

    /**
     * Create a Payrexx Gateway (hosted payment page)
     *
     */
    public function createGateway($amount, $currency, $description, $return_url, $booking_data, $cancel_url = false, $metadata = [], $language = '')
    {

        $anchor = apply_filters('wpbs_payrexx_payment_scroll_to_anchor', '#wpbs-payment-confirmation-message');

        $cancel_url = $cancel_url ?: $return_url;

        $gateway_args = [
            'amount' => self::to_cents($amount),
            'currency' => strtoupper($currency),
            'purpose' => substr($description, 0, 250),
            'referenceId' => $booking_data,
            'successRedirectUrl' => $return_url . $anchor,
            'failedRedirectUrl' => add_query_arg('wpbs_payrexx_message', 'failed', $cancel_url),
            'cancelRedirectUrl' => add_query_arg('wpbs_payrexx_message', 'cancelled', $cancel_url),
            'skipResultPage' => 1,
        ];

        if ($language && preg_match('/^[a-z]{2}$/', $language)) {
            $gateway_args['language'] = $language;
        }

        if (!empty($metadata['_payrexx_email'])) {
            $gateway_args['fields']['email']['value'] = $metadata['_payrexx_email'];
        }

        /**
         * Field Mapping for Customer Data
         *
         * $field_mapping = [
         *   1 => [            // form id
         *       1 => 'forename', // field id => Payrexx contact field
         *       2 => 'surname',
         *       3 => 'street',
         *       4 => 'postcode',
         *       5 => 'place',
         *       6 => 'country',
         *       7 => 'phone',
         *   ]
         * ];
         *
         */
        $field_mapping = apply_filters('wpbs_payrexx_customer_data_mapping', []);

        if (isset($metadata['form_id']) && isset($field_mapping[$metadata['form_id']])) {
            foreach ($field_mapping[$metadata['form_id']] as $field_id => $key) {
                if (isset($metadata['field_' . $field_id])) {
                    $gateway_args['fields'][$key]['value'] = $metadata['field_' . $field_id];
                }
            }
        }

        $gateway_args = apply_filters('wpbs_payrexx_gateway_args', $gateway_args, $metadata);

        $response = $this->request('POST', 'Gateway', $gateway_args);

        if ($response['success'] === false || empty($response['data']['link'])) {
            return array(
                'success' => false,
                'error' => 'Payrexx: ' . esc_html($response['error'] ?: 'Missing payment link.'),
            );
        }

        return array(
            'success' => true,
            'gateway_id' => $response['data']['id'],
            'checkout_url' => $response['data']['link'],
        );
    }

    /**
     * Retrieve a Gateway. Returns the gateway as an array or false.
     *
     */
    public function getGateway($gateway_id)
    {
        $response = $this->request('GET', 'Gateway/' . intval($gateway_id));

        if ($response['success'] === false || empty($response['data']['id'])) {
            return false;
        }

        return $response['data'];
    }

    /**
     * Retrieve a Transaction. Returns the transaction as an array or false.
     *
     */
    public function getTransaction($transaction_id)
    {
        $response = $this->request('GET', 'Transaction/' . intval($transaction_id));

        if ($response['success'] === false || empty($response['data']['id'])) {
            return false;
        }

        return $response['data'];
    }

    /**
     * Refund (part of) a transaction
     *
     */
    public function refundTransaction($transaction_id, $amount, $payment)
    {
        $transaction = $this->getTransaction($transaction_id);

        if (!$transaction) {
            return ['error' => true, 'error_message' => 'Transaction not found.'];
        }

        if (isset($transaction['currency']) && strtoupper($transaction['currency']) !== strtoupper($payment->get_currency())) {
            return ['error' => true, 'error_message' => 'Invalid currency.'];
        }

        $response = $this->request('POST', 'Transaction/' . intval($transaction_id) . '/refund', [
            'amount' => self::to_cents($amount),
        ]);

        if ($response['success'] === false) {
            return ['error' => true, 'error_message' => $response['error']];
        }

        return $response['data'];
    }

    /**
     * Get the transactions attached to a gateway, newest first.
     *
     */
    public static function getGatewayTransactions($gateway)
    {
        $transactions = [];

        if (empty($gateway['invoices']) || !is_array($gateway['invoices'])) {
            return $transactions;
        }

        foreach ($gateway['invoices'] as $invoice) {
            if (empty($invoice['transactions']) || !is_array($invoice['transactions'])) {
                continue;
            }
            foreach ($invoice['transactions'] as $transaction) {
                $transactions[] = $transaction;
            }
        }

        usort($transactions, function ($a, $b) {
            return intval(isset($b['id']) ? $b['id'] : 0) - intval(isset($a['id']) ? $a['id'] : 0);
        });

        return $transactions;
    }

    /**
     * The confirmed transaction of a gateway, if any.
     *
     */
    public static function getConfirmedTransaction($gateway)
    {
        foreach (self::getGatewayTransactions($gateway) as $transaction) {
            if (isset($transaction['status']) && in_array($transaction['status'], ['confirmed', 'refunded', 'partially-refunded', 'refund_pending'])) {
                return $transaction;
            }
        }
        return false;
    }

    /**
     * Map the gateway status to a WP Booking System payment status.
     *
     * @return array ['status' => completed|pending|error, 'reason' => string]
     *
     */
    public static function getGatewayStatus($gateway)
    {
        $transactions = self::getGatewayTransactions($gateway);
        $latest = isset($transactions[0]['status']) ? $transactions[0]['status'] : '';

        if (in_array($latest, ['refunded', 'partially-refunded', 'refund_pending'])) {
            return array('status' => 'completed', 'reason' => 'Refunded');
        }

        if (isset($gateway['status']) && $gateway['status'] == 'confirmed') {
            return array('status' => 'completed', 'reason' => 'Paid');
        }

        if (in_array($latest, ['confirmed'])) {
            return array('status' => 'completed', 'reason' => 'Paid');
        }

        if (in_array($latest, ['declined', 'cancelled', 'error', 'expired', 'chargeback'])) {
            return array('status' => 'error', 'reason' => ucfirst($latest));
        }

        if (isset($gateway['status']) && in_array($gateway['status'], ['authorized', 'reserved'])) {
            return array('status' => 'pending', 'reason' => ucfirst($gateway['status']));
        }

        return array('status' => 'pending', 'reason' => 'Waiting');
    }
}
