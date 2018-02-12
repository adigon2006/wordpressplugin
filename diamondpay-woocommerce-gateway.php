<?php
/**
Plugin Name: Diamondpay Woocommerce Plugin
Description: This plugin help merchant to use diamond pay integration on their website by just entering the necessary parameters. It offers options for payment of local card on your woocommerce site.

version: 1.0
Author: Adigun Adekunle
Author URL: http://linkedin.com/adekunle

***/

if (!defined('ABSPATH')) {
    exit;
}
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}

add_action('plugins_loaded', 'woocommerce_diamondpay_init', 0);

function woocommerce_diamondpay_init()
{
 if (!class_exists('WC_Payment_Gateway'))
        return;

class WC_Diamondpay extends WC_Payment_Gateway
{

public function __construct()
{
// initialization of the important variable for the payment plugin
 $this->id = 'diamondpay';
 $this->method_title = 'DiamondPay Payment Gateway';
 $this->icon = apply_filters('woocommerce_diamondpay_icon', plugins_url('assets/images/logo.png', __FILE__));
 $this->has_fields = false;	
 
 
  $this->init_form_fields();
  $this->init_settings();
 //$this->saved_payment_methods();
 // $this->save_payment_method_checkbox();
  
 			 $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->mercId = $this->settings['mercId'];
            $this->currCode = $this->settings['currCode'];
         //   $this->hashkey = $this->settings['hashkey'];

            $this->sms = $this->settings['sms'];
            $this->sms_url = $this->settings['sms_url'];
            $this->sms_message = $this->settings['sms_message'];

            $this->posturl = 'https://cipg.diamondbank.com/cipg/MerchantServices/MakePayment.aspx';
            $this->geturl = 'https://cipg.diamondbank.com/cipg/MerchantServices/UpayTransactionStatus.ashx';

            $this->msg['message'] = "";
            $this->msg['class'] = "";
			 if (isset($_GET["OrderID"])) {
                $this->check_diamondpay_response();
            }
			
   if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_diamondpay', array(&$this, 'receipt_page'));
			
			
 }// end of construct

 function receipt_page($order) {
            global $woocommerce;
            $items = $woocommerce->cart->get_cart();
            $item_rows = "";
            $currency = get_woocommerce_currency_symbol();
            $css = "<style>
                    .label-info{
                        background-color: green;
                        color: #f4f4f4;
                        padding: 5px;
                    }
                    tbody tr td { border-bottom: 1px solid; }
                    tbody tr{
                        font-size:14px;
                    }
                    thead{
                        font-size:16px;
                    }
                    tbody tr td{
                        padding: 10px 0px;
                    }tr:nth-child(even) {
                        background-color: #f4f4f4;
                    }
                    tfoot tr td:nth-child(1){
                        font-weight: bold;
                        font-size: 22px;
                        padding: 10px 0px 0px 10px;
                    }
                    </style>";
            echo $css;
            $price_total = 0;

            $order_data = new WC_Order($order);
            $shipping = $order_data->get_shipping_method();
            $shipping_rate = $order_data->get_total_shipping();
            ;
            foreach ($items as $item => $values) {
                $_product = $values['data']->post;
                $price = get_post_meta($values['product_id'], '_price', true) * $values['quantity'];
                $price_total += $price;
                $item_rows .= '<tr><td>' . $_product->post_title . '</td><td>' . $values['quantity'] . '</td><td>' . $price . '</td> </tr>';
            }
            $item_rows .= "<tr><td><b><i>Sub Total</i></b></td><td></td><td>" . $price_total . "</td></tr>";
            if ($shipping && $shipping <> "") {
                $item_rows .= "<tr><td><b><i>Shipping(" . $shipping
                        . ")</i></b></td><td></td><td>" . $shipping_rate
                        . "</td></tr>";
            }
            if ($shipping_rate > 0) {
                $price_total += $shipping_rate;
            }
            $confirmation_table = '<p><span class="label-info">Please review your order then click on "Pay via Diamond Pay" button</span></p>
                                    <br><h2>Your Order</h2>
                                    <table><thead><tr><th>Product</th><th>Qty</th><th>Amount(' . trim($currency) . ')</th></tr><thead>
                                    <tbody>' . $item_rows . '</tbody>
                                    <tfoot><tr><td><b>Grand Total</b></td><td></td><td><b>' . $currency . $price_total .
                    '</b></td></tr></tfoot></table>';

            echo $confirmation_table;
            echo $this->generate_diamondpay_form($order);
        }
		
public function generate_diamondpay_form($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);
            $txnid = $order_id . '_' . strtotime(date("ymdhis"));

            $redirect_url = $woocommerce->cart->get_checkout_url();
            $gtpay_cust_id = $order->billing_email;

           // $gtpay_hash = $this->gtpay_mert_id . $txnid . $order->order_total * 100 . $this->gtpay_tranx_curr
            //        . $gtpay_cust_id . $redirect_url . $this->hashkey;
           // $hash = hash('sha512', $gtpay_hash);

            $gtpay_args = array(
                'mercId' => $this->mercId,
               // 'gtpay_tranx_id' => $txnid,
                'amt' => $order->order_total,
                'currCode' => $this->currCode,
				'email' => $order->billing_email,
				'prod' => 'Autoparts',
				'orderId' => $txnid,
                
                //'gtpay_cust_name' => trim($order->billing_last_name . ' ' . $order->billing_first_name),
                //'gtpay_tranx_noti_url' => $redirect_url,
                //'gtpay_echo_data' => $order_id . ";" . $hash
            );

            $gtpay_args_array = array();
            foreach ($gtpay_args as $key => $value) {
                $gtpay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            return '<form action="' . $this->posturl . '" method="post" id="diamondpay_payment_form">
            ' . implode('', $gtpay_args_array) . '
            <input type="submit" class="button-alt" id="submit_diamondpay_payment_form" value="' . __('Pay via DiamondPay', 'diamondpay') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'diamondpay') . '</a>
            <script type="text/javascript">
function processDiamondPayJSPayment(){
jQuery("body").block(
        {
            message: "<img src=\"' . plugins_url('assets/images/ajax-loader.gif', __FILE__) . '\" alt=\"redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'diamondpay') . '",
                overlayCSS:
        {
            background: "#fff",
                opacity: 0.6
    },
    css: {
        padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:"32px"
    }
    });
    jQuery("#diamondpay_payment_form").submit();
    }
    jQuery("#submit_diamondpay_payment_form").click(function (e) {
        e.preventDefault();
        processDiamondPayJSPayment();
    });
</script>
            </form>';
        }
		
		 function sendsms($number, $message) {
            $url = $this->sms_url;
            $url = str_replace("{NUMBER}", urlencode($number), $url);
            $url = str_replace("{MESSAGE}", urlencode($message), $url);
            $url = str_replace("amp;", "&", $url);
            if (trim($url) <> "") {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $url
                ));
                curl_exec($curl);
                curl_close($curl);
            }
        }
				
function init_form_fields() {
	 
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'diamondpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable Diamond Pay Payment Module.', 'diamondpay'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'diamondpay'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'diamondpay'),
                    'default' => __('Diamondpay Payment', 'diamondpay')),
                'description' => array(
                    'title' => __('Description:', 'diamondpay'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'diamondpay'),
                    'default' => __('This plugin help merchant to use diamond pay integration on their website by just entering the necessary parameters. It offers options for payment of local card on your woocommerce site.', 'diamondpay')),
                'mercId' => array(
                    'title' => __('Diamondpay Merchant ID', 'diamondpay'),
                    'type' => 'text',
                    'description' => __('This is the diamondpay-wide unique identifier of merchant, assigned by diamondpay and communicated to merchant by Diamond Bank after merchant registration.')),
                    'currCode' => array(
                    'title' => __('Currency', 'diamondpay'),
                    'type' => 'text',
                    'description' => __('This parameter represents the ISO currency code representing the currency in which the transaction is been carried out. Acceptable values Naira  - (566)."')),
                'sms' => array(
                    'title' => __('SMS Notification', 'diamondpay'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'description' => __('Enable SMS notification after sucessful payment on GTPay', 'gtpay')),
                'sms_url' => array(
                    'title' => __('Send SMS REST API URL'),
                    'type' => 'text',
                    'description' => __('Use {NUMBER} for the customers number, {MESSAGE} should be in place of the message')),
                'sms_message' => array(
                    'title' => __('SMS Response'),
                    'type' => 'textarea',
                    'description' => __('Use {ORDER-ID} for the order id, {AMOUNT} for amount, {CUSTOMER} for customer name.'))
            );
        }
		
		public function admin_options() {
            echo '<h3>' . __('DiamondPay Payment Gateway', 'gtpay') . '</h3>';
            echo '<p>' . __('DiamondPay is most popular payment gateway for online shopping in Nigeria') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
            wp_enqueue_script('diamondpay_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
        }

 function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }
 function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                        'order', $order->id, add_query_arg(
                                'key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))
                        )
                )
            );
        }

        function showMessage($content) {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }

        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
		
		
		// diamond pay response
		 function check_diamondpay_response() {
            global $woocommerce;
            $orderstring = $_GET["OrderID"];
			$merchid = $this->mercId;
			
            $data = explode("_", $orderstring);
            $wc_order_id = $data[0];
            $order = new WC_Order($wc_order_id);
			
// verify the transaction details			
$urlloc = 'https://cipg.diamondbank.com/cipg/MerchantServices/UpayTransactionStatus.ashx?MERCHANT_ID='.$merchid.'&ORDER_ID='.$orderstring;
$xml = simplexml_load_file($urlloc);	
$Amount = $xml->Amount;
$date = $xml->Date;
$MerchantRef =  $xml->MerchantReference;
 //echo $xml->MertID;
$statuscode = $xml->StatusCode;
$status = $xml->Status;
$realorderid = $xml->OrderID;
$ResponseCode =  $xml->ResponseCode;
$ResponseDescription = $xml->ResponseDescription;
$currencycode = $xml->CurrencyCode;
$transref = $xml->TransactionRef;
			
$total_amount = $Amount;

/*
status code and explanation
Successful 00
Failed 01
Pending 02
Cancelled 03
Not Processed 04
Invalid Merchant 05
Inactive Merchant 06
Invalid Order ID 07
Duplicate Order ID 08
Invalid Amount 09

*/
			
            //$tranxid = $_POST["gtpay_tranx_id"];

            try {
                if ($statuscode === "03") {
                    #payment cancelled
                    $respond_desc = $ResponseDescription;
                    $message_resp = "Your transaction was not successful." .
                            "<br>Reason: " . $respond_desc .
                            "<br>Transaction Reference: " . $transref;
                    $message_type = "error";
                    $order->add_order_note('DiamondPay payment failed: ' . $respond_desc);
                    $order->update_status('cancelled');
                    $redirect_url = $order->get_cancel_order_url();
                    wc_add_notice($message_resp, "error");
                } else {
                    wc_print_notices();
         /*           $mert_id = $this->gtpay_mert_id;
                    $hashkey = $this->hashkey;
                    $my_hash = hash("sha512", $mert_id . $tranxid . $hashkey);
                    $ch = curl_init();
                    $url = "https://ibank.gtbank.com/GTPayService/gettransactionstatus.json?"
                            . "mertid={$mert_id}&amount={$total_amount}&tranxid={$tranxid}&hash={$my_hash}";
                    curl_setopt_array($ch, array(
                        CURLOPT_URL => $url,
                        CURLOPT_NOBODY => false,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false
                    ));
                    $response = curl_exec($ch);
                    $response_decoded = json_decode($response);
                    $respond_code = $response_decoded->ResponseCode;*/
                    if ($statuscode == "00") {
                        #payment successful
                        $respond_desc = $response_decoded->ResponseDescription;
                        $message_resp = "Approved Successful." .
                            "<br>" . $respond_desc .
                            "<br>Transaction Reference: " . $transref;
                        $message_type = "success";
                        $order->payment_complete();
                        $order->update_status('completed');
                        $order->add_order_note('DiamondPay payment successful: ' . $message_resp);
                        $woocommerce->cart->empty_cart();
                        $redirect_url = $this->get_return_url($order);
                        if ($this->sms == "yes") {
      $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                            $phone_no = get_user_meta(get_current_user_id(), 'billing_phone', true);
                            $sms = $this->sms_message;
                            $sms = str_replace("{ORDER-ID}", $wc_order_id, $sms);
                            $sms = str_replace("{AMOUNT}", $total_amount, $sms);
                            $sms = str_replace("{CUSTOMER}", $customer, $sms);
                            $this->sendsms($phone_no, $sms);
                        }
                        wc_add_notice($message_resp, "success");
                    } else {
                        #payment failed
                        $respond_desc = $ResponseDescription;
                        $message_resp = "Your transaction was not successful." .
                            "<br>Reason: " . $respond_desc .
                            "<br>Transaction Reference: " . $transref;
                        $message_type = "error";
                        $order->add_order_note('DiamondPay payment failed: ' . $message_resp);
                        $order->update_status('cancelled');
                        $redirect_url = $order->get_cancel_order_url();
                        wc_add_notice($message_resp, "error");
                    }
                }

                $notification_message = array(
                    'message' => $message_resp,
                    'message_type' => $message_type
                );

                wp_redirect(html_entity_decode($redirect_url));
                exit;
            } catch (Exception $e) {
                $order->add_order_note('Error: ' . $e->getMessage());

                wc_add_notice($e->getMessage(), "error");
                $redirect_url = $order->get_cancel_order_url();
                wp_redirect(html_entity_decode($redirect_url));
                exit;
            }
        }

// add the currency text
 static function add_diamondpay_currency($currencies) {
            $currencies['Naira'] = __('Naira', 'woocommerce');
           // $currencies['USD'] = __('US Dollar', 'woocommerce');
            return $currencies;
        }

// add the currency symbol
        static function add_diamondpay_currency_symbol($currency_symbol, $currency) {
            switch ($currency) {
                case 'Naira':
                    $currency_symbol = '₦ ';
                    break;
                /*case 'USD':
                    $currency_symbol = '$ ';
                    break;*/
            }
            return $currency_symbol;
        }

// this method add the payment gateway
static function woocommerce_add_diamondpay_gateway($methods) {
            $methods[] = 'WC_Diamondpay';
            return $methods;
        }

// Add settings link on plugin page
        static function woocommerce_add_diamondpay_settings_link($links) {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_diamondpay">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        }	



}// end of class

 $plugin = plugin_basename(__FILE__);

    add_filter('woocommerce_currencies', array('WC_Diamondpay', 'add_diamondpay_currency'));
    add_filter('woocommerce_currency_symbol', array('WC_Diamondpay', 'add_diamondpay_currency_symbol'), 10, 2);
    add_filter("plugin_action_links_$plugin", array('WC_Diamondpay', 'woocommerce_add_diamondpay_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Diamondpay', 'woocommerce_add_diamondpay_gateway'));		


	
}
 // end of function 










?>