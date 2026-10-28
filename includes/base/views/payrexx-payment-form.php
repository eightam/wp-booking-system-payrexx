<div class="wpbs-payment-confirmation-payrexx-form">

    <?php if (wpbs_part_payments_enabled() == true && $payment->is_part_payment()): ?>
        <div class="wpbs-amount-billed">
            <h4 class="wpbs-amount-billed-heading"><?php echo wpbs_get_payment_default_string('amount_billed', $language); ?></h4>
            <h2 class="wpbs-amount-billed-amount"><?php echo wpbs_get_formatted_price($total, $payment->get_currency()); ?></h2>
        </div>
    <?php endif; ?>

    <div id="payrexx-payment-button">
        <a href="<?php echo esc_url($response['checkout_url']); ?>">
            <span><?php echo __('Pay now with Payrexx', 'wp-booking-system-payrexx'); ?></span>
        </a>
        <p class="wpbs-payrexx-payment-methods-note"><?php echo __('You will be redirected to the secure Payrexx payment page.', 'wp-booking-system-payrexx'); ?></p>
    </div>

</div>
