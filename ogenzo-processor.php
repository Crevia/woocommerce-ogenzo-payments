<?php

/**
 * Plugin Name: WooCommerce Ogenzo Payments
 * Plugin URI: https://ogenzo.com/payments#woocommerce
 * Description: Receive mobile money payments directly on your woocommerce shop using Ogenzo Payments.
 * Author: ogenzo
 * Author URI: https://ogenzo.com/
 * Version: 1.0.0
 * WC requires at least: 3.5.0
 * WC tested up to: 4.3.3
 */

if (!defined('ABSPATH'))
    exit;
require_once('ogenzo-payment.php');
add_action('init', 'ogenzo_init');

register_activation_hook(__FILE__, function () {
    $dir = plugin_dir_path(__FILE__) . "ogenzo.txt";
    $open =  fopen($dir, "w");
    fclose($open);
});

register_deactivation_hook(__FILE__, 'ogenzo_woo_wp_deactivate');

function ogenzo_woo_wp_deactivate()
{

    $dir = plugin_dir_path(__FILE__) . "ogenzo.txt";
    unlink($dir);
}

add_action('woocommerce_thankyou_order_received_text', 'woocommerce_comfirm_payment', 10, 2);

function woocommerce_comfirm_payment($message, $order)
{
    $payment_method = $order->get_payment_method();
    if ($payment_method == 'ogenzo_payments') {
        $phone = $order->get_billing_phone();

        echo $message . '<br><br><p style="color:red; font-weight:bold;">' . __('Note: Payment prompt has been sent to your "' . $phone . '". Please enter your pin to make payment for your payment. Your order cannot be delivered until you complete the payment on your phone.', 'woocommerce') . '</p>';
    }
}

function ogenzo_init()
{
    class Ogenzo_Pay_GW extends WC_Payment_Gateway
    {
        public $allowed_currency = array(
            'UGX',
        );



        public function __construct()
        {

            $this->id = 'ogenzo_payments';
            add_option('page_id', $this->id);
            wp_cache_add('page_id', $this->id);
            $this->has_fields = true;


            $this->init_form_fields();
            $this->init_settings();
            $this->icon               = plugin_dir_url(__FILE__) . 'logo.png';
            $this->title = "Ogenzo Mobile Money";
            $this->method_description = __('Enable customers to make payments using mobile money', 'woocommerce');
            $this->description = 'Use Airtel or Mtn mobile money to make payment for this order. Powered by <a target="_blank" href="https://payments.ogenzo.com">Ogenzo Payments</a>';


            $this->instructions     = $this->get_option('instructions', $this->description);

            $this->mtn_slug              = $this->get_option('mtn_slug');

            $this->airtel_slug              = $this->get_option('airtel_slug');

            $this->api_password              = $this->get_option('api_password');
            $this->user_name              = $this->get_option('user_name');
            $this->shop_name = $this->get_option('shop_name', 'Ogenzo');

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }

            add_action('admin_notices', array($this, 'beyonic_admin_notices'));
        }



        public function init_form_fields()
        {
            $this->form_fields = array(

                'enabled' => array(
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Ogenzo Payments Gateway', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'   => __('Title', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('This is the title seen by the user on check-out', 'woocommerce'),
                    'default' => 'Mobile Money', 'desc_tip'    => true,
                ),
                'description' => array(
                    'title'   => __('Description', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Payment description seen by the user on check-out', 'woocommerce'),
                    'default' => 'Place an order using Mobile Money by Ogenzo',
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'   => __('Instructions', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Instructions seen by the user on check-out', 'woocommerce'),
                    'default' => 'Enter your pin when prompted to complete the transaction',
                    'desc_tip'    => true,
                ),
                'shop_name' => array(
                    'title'   => __('Shop Name', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Your shop name that will be seen in the user\'s transaction message', 'woocommerce'),
                    'default' => '',
                    'desc_tip'    => true,
                ),
                'user_name' => array(
                    'title'   => __('User Name', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Ogenzo Payments registered username', 'woocommerce'),
                    'default' => '',
                    'desc_tip'    => true,
                ),
                'api_password' => array(
                    'title'   => __('Api Password', 'woocommerce'),
                    'type'    => 'password',
                    'label'   => __('Api password set up on Ogenzo payments', 'woocommerce'),
                    'default' => '',
                    'desc_tip'    => true,
                ),
                'mtn_slug' => array(
                    'title'   => __('Mtn Slug', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Mtn wallet slug', 'woocommerce'),
                    'default' => '',
                    'desc_tip'    => true,
                ),
                'airtel_slug' => array(
                    'title'   => __('Airtel Slug', 'woocommerce'),
                    'type'    => 'text',
                    'label'   => __('Airtel wallet slug', 'woocommerce'),
                    'default' => '',
                    'desc_tip'    => true,
                ),
            );
        }

        public function admin_options()
        {
            $store_currency = get_option('woocommerce_currency');
            if (in_array($store_currency, $this->allowed_currency)) {
                ?>
<h3><?php _e('Ogenzo Payments', 'woocommerce'); ?></h3>
<p><?php _e('Please fill in the section below to start accepting payments on your wordpress site. You must first sign up for an Ogenzo Payments account at <a target="_blank" href = "https://payments.beyonic.com/register/" > https://payments.ogenzo.com/register </a>. After that, you will get all the information during registration and after registration.', 'woocommerce'); ?> </p>
<table class="form-table">
    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
</table>
<!--/.form-table-->

<?php
            } else {
                ?>
<div class="inline error below-h2">
    <p><strong>Ogenzo Payments is not available. </strong>: Ogenzo does not support your store currency.</p>
</div>
<?php
            }
        }


        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            $trx_id = rand(11111, 99999) . '-' . $order_id;
            $order->set_transaction_id($trx_id);
            $order->set_payment_method($this->get_option('mtn_slug'));
            $order->save();

            $order->update_status('pending-payment');

            $data = array();
            $data['phone'] = $order->get_billing_phone();
            $data['amount'] = $order->get_total();
            $data['txn_id'] = $trx_id;
            $data['msg'] = 'Order No ' . $order_id;
            $data['user_name'] = $this->get_option('user_name');
            $data['api_password'] = $this->get_option('api_password');
            $data['mtn'] = $this->get_option('mtn_slug');
            $data['airtel'] = $this->get_option('airtel_slug');
            $ogenzo = new OgenzoPayments($data);
            $res =    $ogenzo->Collect($data)->status;


            if ($res) {
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                return array(
                    'result' => 'failed',
                    'redirect' => $this->get_return_url($order)
                );
            }
        }
    }



    /**
     * Add the gateway to WooCommerce
     * */
    function add_ogenzo_pay_gw($methods)
    {
        $methods[] = 'Ogenzo_Pay_Gw';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ogenzo_pay_gw');

    if (!empty($_GET['ogenzo_ipn']) && $_GET['ogenzo_ipn'] == 1) {

        global  $wpdb;
        $data['user_name'] = 'user_name';
        $data['api_password'] = 'api_password';
        $data['mtn'] = 'mtn_slug';
        $data['airtel'] = 'airtel_slug';
        $ogenzo = new OgenzoPayments($data);
        $table_name = $wpdb->prefix . 'wc_order_stats';

        $trx_count = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'wc-pending'", OBJECT);

        foreach ($trx_count as $key) {

            $order = new WC_Order($key->order_id);

            $data = array('txn_id' => $order->get_transaction_id(), "slug" => $order->get_payment_method());

            $state = $ogenzo->DepositStatus($data);
            if ($state == 'success') {
                $order->update_status('processing', 'Payment has been comfirmed');
            } elseif ($state == 'failed') {
                $order->update_status('failed', 'Payment was not received');
            } elseif ($state == 'pending') { } else {
                $order->update_status('on-hold', 'Payment needs to be manually verified');
            }
        }
        exit;
    }
}
