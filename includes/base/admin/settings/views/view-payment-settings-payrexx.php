<?php
$settings = get_option('wpbs_settings', array());
$active_languages = (!empty($settings['active_languages']) ? $settings['active_languages'] : array());
$languages = wpbs_get_languages();
$payrexx_api = get_option('wpbs_payrexx_api', array());
?>

<div class="wpbs-tab-inner">

    <h2>
        <?php echo __('Payrexx', 'wp-booking-system-payrexx') ?>
        <small class="wpbs-heading-description"><?php echo __("Give the customer the option to pay with TWINT, credit cards, PostFinance and the other payment methods supported by Payrexx.", 'wp-booking-system-payrexx'); ?></small>
    </h2>

    <!-- Enable Payrexx -->
    <div class="wpbs-form-field">
        <label class="wpbs-label" for="user_notification_enable">
            <?php echo __('Enable Payrexx', 'wp-booking-system-payrexx'); ?>
        </label>

        <div class="wpbs-input">
            <label for="payment_payrexx_enable" class="wpbs-checkbox-switch">
                <input type="hidden" name="wpbs_settings[payment_payrexx_enable]" value="0">
                <input data-target="#wpbs-payment-payrexx" name="wpbs_settings[payment_payrexx_enable]" type="checkbox" id="payment_payrexx_enable" class="wpbs-settings-toggle wpbs-settings-wrap-toggle" <?php echo (!empty($settings['payment_payrexx_enable'])) ? 'checked' : ''; ?>>
                <div class="wpbs-checkbox-slider"></div>
            </label>
        </div>
    </div>

    <div id="wpbs-payment-payrexx" class="wpbs-payment-on-arrival-wrapper wpbs-settings-wrapper <?php echo (!empty($settings['payment_payrexx_enable'])) ? 'wpbs-settings-wrapper-show' : ''; ?>">

        <!-- Payment Method Name -->

        <div class="wpbs-form-field">
            <label class="wpbs-label" for="payment_payrexx_name">
                <?php echo __('Display name', 'wp-booking-system-payrexx'); ?>

            </label>

            <div class="wpbs-input">
                <input name="wpbs_settings[payment_payrexx_name]" type="text" id="payment_payrexx_name"  value="<?php echo (!empty($settings['payment_payrexx_name'])) ? $settings['payment_payrexx_name'] : $defaults['display_name']; ?>">
            </div>


            <div class="wpbs-label-description"><?php echo __("The payment method name that appears on the booking form.", 'wp-booking-system-payrexx'); ?></div>

            <?php if (wpbs_translations_active()): ?><a href="#" class="wpbs-link-button wpbs-form-translations-toggle"><?php echo __('Translations', 'wp-booking-system'); ?> <i class="wpbs-icon-angle-down"></i></a><?php endif ?>

            <?php if (wpbs_translations_active()): ?>
                <!-- Required Field Translations -->
                <div class="wpbs-form-translations wpbs-form-fields-indent">
                    <?php foreach ($active_languages as $language): ?>
                        <div class="wpbs-form-field wpbs-small-margin">
                            <div class="wpbs-input">
                                <div class="wpbs-input-wrapper">
                                    <span class="wpbs-input-symbol wpbs-input-symbol-left wpbs-flag-symbol">
                                        <span><img src="<?php echo WPBS_PLUGIN_DIR_URL; ?>/assets/img/flags/<?php echo $language; ?>.png" /> <?php echo strtoupper($language); ?></span>
                                    </span>
                                    <input name="wpbs_settings[payment_payrexx_name_translation_<?php echo $language; ?>]" type="text" id="payment_payrexx_name_translation_<?php echo $language; ?>" value="<?php echo (!empty($settings['payment_payrexx_name_translation_' . $language])) ? esc_attr($settings['payment_payrexx_name_translation_' . $language]) : ''; ?>" >
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Method Name -->

        <div class="wpbs-form-field">
            <label class="wpbs-label" for="payment_payrexx_description">
                <?php echo __('Description', 'wp-booking-system-payrexx'); ?>

            </label>

            <div class="wpbs-input">
                <input name="wpbs_settings[payment_payrexx_description]" type="text" id="payment_payrexx_description"  value="<?php echo (!empty($settings['payment_payrexx_description'])) ? $settings['payment_payrexx_description'] : $defaults['description']; ?>">
            </div>
            <div class="wpbs-label-description"><?php echo __("The payment method description that appears on the booking form.", 'wp-booking-system-payrexx'); ?></div>

            <?php if (wpbs_translations_active()): ?><a href="#" class="wpbs-link-button wpbs-form-translations-toggle"><?php echo __('Translations', 'wp-booking-system'); ?> <i class="wpbs-icon-angle-down"></i></a><?php endif ?>

            <?php if (wpbs_translations_active()): ?>
                <!-- Required Field Translations -->
                <div class="wpbs-form-translations wpbs-form-fields-indent">
                    <?php foreach ($active_languages as $language): ?>
                        <div class="wpbs-form-field wpbs-small-margin">
                            <div class="wpbs-input">
                                <div class="wpbs-input-wrapper">
                                    <span class="wpbs-input-symbol wpbs-input-symbol-left wpbs-flag-symbol">
                                        <span><img src="<?php echo WPBS_PLUGIN_DIR_URL; ?>/assets/img/flags/<?php echo $language; ?>.png" /> <?php echo strtoupper($language); ?></span>
                                    </span>
                                    <input name="wpbs_settings[payment_payrexx_description_translation_<?php echo $language; ?>]" type="text" id="payment_payrexx_description_translation_<?php echo $language; ?>" value="<?php echo (!empty($settings['payment_payrexx_description_translation_' . $language])) ? esc_attr($settings['payment_payrexx_description_translation_' . $language]) : ''; ?>" >
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invoice Item Name (key without underscores, as read by WPBS_Payment_Method) -->

        <div class="wpbs-form-field">
            <label class="wpbs-label" for="paymentpayrexxinvoice_name">
                <?php echo __('Invoice Item Name', 'wp-booking-system-payrexx'); ?>

            </label>

            <div class="wpbs-input">
                <input name="wpbs_settings[paymentpayrexxinvoice_name]" type="text" id="paymentpayrexxinvoice_name"  value="<?php echo (!empty($settings['paymentpayrexxinvoice_name'])) ? $settings['paymentpayrexxinvoice_name'] : ''; ?>">
            </div>
            <div class="wpbs-label-description"><?php echo __('The name of the product that appears on the Payrexx invoice. Eg. "Booking at Hotel Alpenblick."', 'wp-booking-system-payrexx'); ?></div>

            <?php if (wpbs_translations_active()): ?><a href="#" class="wpbs-link-button wpbs-form-translations-toggle"><?php echo __('Translations', 'wp-booking-system'); ?> <i class="wpbs-icon-angle-down"></i></a><?php endif ?>

            <?php if (wpbs_translations_active()): ?>
                <!-- Required Field Translations -->
                <div class="wpbs-form-translations wpbs-form-fields-indent">
                    <?php foreach ($active_languages as $language): ?>
                        <div class="wpbs-form-field wpbs-small-margin">
                            <div class="wpbs-input">
                                <div class="wpbs-input-wrapper">
                                    <span class="wpbs-input-symbol wpbs-input-symbol-left wpbs-flag-symbol">
                                        <span><img src="<?php echo WPBS_PLUGIN_DIR_URL; ?>/assets/img/flags/<?php echo $language; ?>.png" /> <?php echo strtoupper($language); ?></span>
                                    </span>
                                    <input name="wpbs_settings[paymentpayrexxinvoice_name_translation_<?php echo $language; ?>]" type="text" id="paymentpayrexxinvoice_name_translation_<?php echo $language; ?>" value="<?php echo (!empty($settings['paymentpayrexxinvoice_name_translation_' . $language])) ? esc_attr($settings['paymentpayrexxinvoice_name_translation_' . $language]) : ''; ?>" >
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- API Settings -->
        <div class="wpbs-divider"></div>
        <h2><?php echo __('API Credentials', 'wp-booking-system-payrexx') ?></h2>

        <!-- Documentation -->
        <div class="wpbs-page-notice notice-info wpbs-form-changed-notice">
            <p><?php echo __('You find the API secret in your Payrexx admin under <strong>Integrations &rarr; API &amp; Plugins</strong>. Payrexx test mode is switched on per payment provider in the Payrexx admin.', 'wp-booking-system-payrexx'); ?></p>
        </div>

        <!-- Instance -->
        <div class="wpbs-form-field">
            <label class="wpbs-label" for="payment_payrexx_instance">
                <?php echo __('Instance name', 'wp-booking-system-payrexx'); ?>
            </label>

            <div class="wpbs-input">
                <input name="wpbs_payrexx_api[payment_payrexx_instance]" type="text" id="payment_payrexx_instance" value="<?php echo (!empty($payrexx_api['payment_payrexx_instance'])) ? esc_attr($payrexx_api['payment_payrexx_instance']) : ''; ?>" placeholder="meinhotel">
            </div>
            <div class="wpbs-label-description"><?php echo __('The part before .payrexx.com, e.g. "meinhotel" for meinhotel.payrexx.com.', 'wp-booking-system-payrexx'); ?></div>
        </div>

        <!-- API Secret -->
        <div class="wpbs-form-field">
            <label class="wpbs-label" for="payment_payrexx_api_secret">
                <?php echo __('API Secret', 'wp-booking-system-payrexx'); ?>
            </label>

            <div class="wpbs-input">
                <input name="wpbs_payrexx_api[payment_payrexx_api_secret]" type="password" autocomplete="new-password" id="payment_payrexx_api_secret" value="<?php echo (!empty($payrexx_api['payment_payrexx_api_secret'])) ? esc_attr($payrexx_api['payment_payrexx_api_secret']) : ''; ?>">
            </div>
        </div>

        <!-- Webhook URL -->
        <div class="wpbs-form-field">
            <label class="wpbs-label" for="payment_payrexx_webhook_url">
                <?php echo __('Webhook URL', 'wp-booking-system-payrexx'); ?>
            </label>

            <div class="wpbs-input">
                <input type="text" id="payment_payrexx_webhook_url" readonly onclick="this.select()" value="<?php echo esc_attr(add_query_arg('wpbs-payrexx-webhook', '', trailingslashit(get_bloginfo('url')))); ?>">
            </div>
            <div class="wpbs-label-description"><?php echo __('Add this URL in your Payrexx admin under Webhooks so bookings are confirmed even if the customer closes the browser after paying.', 'wp-booking-system-payrexx'); ?></div>
        </div>

        <?php if (wpbs_get_currency() != 'CHF'): ?>
            <div class="wpbs-page-notice notice-warning">
                <p><?php echo sprintf(__('The booking currency is set to %s. Make sure your Payrexx instance accepts this currency, or switch the currency to CHF.', 'wp-booking-system-payrexx'), esc_html(wpbs_get_currency())); ?></p>
            </div>
        <?php endif; ?>

    </div>

</div>
