<?php
/**
 * Klarna Payments v3 Payment Module
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2010 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version GIT: $Id: Author: Taras  Tue Jan 22 03:36:04 2013 -0500 Modified in v1.5.2 $
 */
  class klarna_payments {
    var $code, $title, $description, $enabled,
        $api_url, $merchant_id, $password, $client_token, $payment_methods, $order_comments, $klarna_order_id;

// class constructor
    //function klarna_payments() {
    function __construct() {
      global $order;

      $this->code = 'klarna_payments';
      $this->title = MODULE_PAYMENT_KLARNA_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_KLARNA_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_KLARNA_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_KLARNA_STATUS == 'True') ? true : false);

      $this->order_status = (int)DEFAULT_ORDERS_STATUS_ID;
      if ((int)MODULE_PAYMENT_KLARNA_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_KLARNA_ORDER_STATUS_ID;
      }
      
      if (MODULE_PAYMENT_KLARNA_TEST_MODE == 'True') {
        $this->title = MODULE_PAYMENT_KLARNA_TEXT_TITLE.' (TEST MODE)';
        $this->api_url = 'https://api.playground.klarna.com/';
      } else {
        $this->api_url = 'https://api.klarna.com/';
      }
      
      $this->merchant_id = MODULE_PAYMENT_KLARNA_MERCHANT_ID;
      $this->password = MODULE_PAYMENT_KLARNA_PASSWORD;

      if (is_object($order)) $this->update_status();
    }

// class methods
    function update_status() {
      global $order, $db;

      if ($this->enabled && (int)MODULE_PAYMENT_KLARNA_ZONE > 0 && isset($order->billing['country']['id'])) {
        $check_flag = false;
        $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_KLARNA_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }

// disable the module if the order only contains virtual products
      if ($this->enabled == true) {
        if ($order->content_type != 'physical') {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title
                   );
    }

    function pre_confirmation_check() {
        
        global $db, $order, $messageStack, $klarna_methods;
        //echo '<pre>'; print_r($order); echo '</pre>'; exit();
        
        $coupon_discount = 0;
        $klarna_coupon = new ot_coupon;
        $coupon_array = $klarna_coupon->calculate_deductions();
        if (isset($coupon_array['total'])) {
            $coupon_discount = (float)$coupon_array['total'];
        }
        
        $gv_discount = 0;
        $klarna_gv = new ot_gv;
        $gv_array = $klarna_gv->calculate_deductions($order->info['total']);
        if (isset($gv_array['total'])) {
            $gv_discount = (float)$gv_array['total'];
        }

        $lines = array();
        foreach ($order->products as $product) {
            
            //  get product info from database
            $sql = "SELECT products_image, master_categories_id
                    FROM ".TABLE_PRODUCTS."
                    WHERE products_id = ".(int)$product['id'];
            $prod_info = $db->execute($sql);
            
            $lines[] = array(
                "type" => "physical",
                "reference" => $product['model'],
                "name" => $product['name'],
                "quantity" => $product['qty'],
                "unit_price" => round((float)$product['final_price']*100, 0),
                //"tax_rate" => (float)$product['tax'],
                "total_amount" => round((float)$product['final_price'] * $product['qty']*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($product['final_price'] * $product['qty'] * $product['tax'] / 100, 2),
                "image_url" => HTTPS_SERVER . '/' . DIR_WS_IMAGES . $prod_info->fields['products_image'],
                "product_url" => zen_href_link(zen_get_info_page($product['id']), 'cPath=' . zen_get_generated_category_path_rev($prod_info->fields['master_categories_id']) . '&products_id=' . $product['id'])
            );
        }
        
        //  add shipping
        $lines[] = array(
                "type" => "shipping_fee",
                "reference" => $order->info['shipping_module_code'],
                "name" => $order->info['shipping_method'],
                "quantity" => 1,
                "unit_price" => round((float)$order->info['shipping_cost']*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => round((float)$order->info['shipping_cost']*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
            
        //  add ot_coupon
        if ($coupon_discount > 0) {
            $lines[] = array(
                "type" => "discount",
                "reference" => 'Discount Coupon',
                "name" => 'Discount Coupon',
                "quantity" => 1,
                "unit_price" => -round((float)$coupon_discount*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => -round((float)$coupon_discount*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
        }
        
        //  add ot_gv
        if ($gv_discount > 0) {
            $lines[] = array(
                "type" => "discount",
                "reference" => 'Gift Certificate',
                "name" => 'Gift Certificate',
                "quantity" => 1,
                "unit_price" => -round((float)$gv_discount*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => -round((float)$gv_discount*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
        }
            
        //  add tax
        if ($order->info['tax'] > 0) {
            $lines[] = array(
                "type" => "sales_tax",
                "name" => "Tax",
                "quantity" => 1,
                "unit_price" => round((float)$order->info['tax']*100, 0),
                "total_amount" => round((float)$order->info['tax']*100, 0)
            );
        }
        
        $vars = array(
            //"purchase_country" => $order->customer['country']['iso_code_2'],
            //"purchase_currency" => $order->info['currency'],
            "purchase_country" => 'GB',
            "purchase_currency" => 'GBP',
            "locale" => "en-GB",
            "order_amount" => round((float)($order->info['total'] - $coupon_discount - $gv_discount)*100, 0),
            "order_tax_amount" => round((float)$order->info['tax']*100, 0),
            "order_lines" => $lines
        );
//echo '<pre>'; print_r($vars); echo '</pre>'; exit();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url.'payments/v1/sessions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array(
            'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
            'Content-Type: application/json'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $server_output = curl_exec ($ch);
        //echo '<pre>'; print_r($server_output); echo '</pre>'; exit();
        curl_close($ch);
        
        $decoded = json_decode($server_output);
        //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
        if (!is_object($decoded)) {
            
            $messageStack->add('checkout_confirmation', 'Something goes wrong. Please contact site admin', 'error');
            
        } elseif (isset($decoded->client_token)) {
            
            $this->client_token = $_SESSION['klarna_client_token'] = $decoded->client_token;
        
            $this->payment_methods = array();
            
            if (isset($decoded->payment_method_categories)) {
                foreach ($decoded->payment_method_categories as $value) {
                    $this->payment_methods[$value->identifier] = $value->name;
                }
            }
            
            $klarna_methods = $this->payment_methods;
            
        } else {
            
            $messageStack->add('checkout_confirmation', $decoded->error_messages[0], 'error');
            
        }
        
        return false;
    }

    function confirmation() { 
                
        $options_html = '<ul>';
        $containers = '';
           
        if (!empty($this->payment_methods)) {
            foreach ($this->payment_methods as $key => $value) {
                $options_html .= '<li id="klarna-'.$key.'-box">
                        <input id="klarna-'.$key.'-option" type="radio" name="klarna_option" value="'.$key.'" />
                        <label for="klarna-'.$key.'-option">'.$value.'</label>
                        <div class="klarna-option-desc" id="klarna-'.$key.'-desc"></div>
                    </li>';
                //$containers .= '<div id="klarna-box-'.$key.'"></div>';
                $containers .= '<div id="klarna-box-"></div>';
            }
            $options_html .= '</ul>';
        }
        //$options_html = '';
        $containers = '';
                
        $confirmation = array('fields' => array(array('title' => '',
                                                      'field' => '<input type="hidden" id="klarna_client_token" value="'.$this->client_token.'" />
                                                                  <div id="klarnaOptions">'.$options_html.'</div>
                                                                  <div id="klarna-payment-container"></div>'.
                                                                  $containers)
                                        )
                            );
    return $confirmation;
        
      //return false;
    }

    function process_button() {
        
        $process_button_string = zen_draw_hidden_field('klarna_authorization_token', '', 'id="klarna_authorization_token"');
        $process_button_string .= zen_draw_hidden_field(zen_session_name(), zen_session_id());

        return $process_button_string;
    }

    function before_process() {

        global $db, $order, $messageStack, $order_totals;

        $coupon_discount = 0;
        $gv_discount = 0;
        foreach ($order_totals as $value) {
            switch ($value['code']) {
                case 'ot_coupon':
                    $coupon_discount = $value['value'];
                break;
                
                case 'ot_gv':
                    $gv_discount = $value['value'];
                break;
            }
        }
        
        $klarna_subtotal = 0;
        $lines = array();
        foreach ($order->products as $product) {
            
            $klarna_subtotal += round((float)$product['final_price']*100, 0)  * $product['qty'];
            
            //  get product info from database
            $sql = "SELECT products_image, master_categories_id
                    FROM ".TABLE_PRODUCTS."
                    WHERE products_id = ".(int)$product['id'];
            $prod_info = $db->execute($sql);
            
            $lines[] = array(
                "type" => "physical",
                "reference" => $product['model'],
                "name" => $product['name'],
                "quantity" => $product['qty'],
                "unit_price" => round((float)$product['final_price']*100, 0),
                //"tax_rate" => (float)$product['tax'],
                "total_amount" => round((float)$product['final_price'] * $product['qty']*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($product['final_price'] * $product['qty'] * $product['tax'] / 100, 2),
                "image_url" => HTTPS_SERVER . '/' . DIR_WS_IMAGES . $prod_info->fields['products_image'],
                "product_url" => zen_href_link(zen_get_info_page($product['id']), 'cPath=' . zen_get_generated_category_path_rev($prod_info->fields['master_categories_id']) . '&products_id=' . $product['id'])
            );
        }
        
        //  add shipping
        $lines[] = array(
                "type" => "shipping_fee",
                "reference" => $order->info['shipping_module_code'],
                "name" => $order->info['shipping_method'],
                "quantity" => 1,
                "unit_price" => round((float)$order->info['shipping_cost']*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => round((float)$order->info['shipping_cost']*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
            
        //  add ot_coupon
        if (!empty($coupon_discount > 0)) {
            $lines[] = array(
                "type" => "discount",
                "reference" => 'Discount Coupon',
                "name" => 'Discount Coupon',
                "quantity" => 1,
                "unit_price" => -round((float)$coupon_discount*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => -round((float)$coupon_discount*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
        }
        
        //  add ot_gv
        if ($gv_discount > 0) {
            $lines[] = array(
                "type" => "discount",
                "reference" => 'Gift Certificate',
                "name" => 'Gift Certificate',
                "quantity" => 1,
                "unit_price" => -round((float)$gv_discount*100, 0),
                //"tax_rate" => (float)$order->info['shipping_tax'],
                "total_amount" => -round((float)$gv_discount*100, 0),
                "total_discount_amount" => 0,
                //"total_tax_amount" => (float)round($order->info['shipping_cost'] * $order->info['shipping_tax'] / 100, 2),
                //"image_url" => "https://www.exampleobjects.com/logo.png",
                //"product_url" => "https://www.estore.com/products/f2a8d7e34"
            );
        }
            
        //  add tax
        if ($order->info['tax'] > 0) {
            $lines[] = array(
                "type" => "sales_tax",
                "name" => "Tax",
                "quantity" => 1,
                //"unit_price" => round($order->info['tax']*100, 0),
                //"total_amount" => round($order->info['tax']*100, 0)
                "unit_price" => round((float)$klarna_subtotal*0.2, 0),
                "total_amount" => round((float)$klarna_subtotal*0.2, 0)
            );
        }
        
        if (MODULE_PAYMENT_KLARNA_TEST_MODE == 'True') { 
              $billing_address = array(
                'given_name' => "Test",
                'family_name' => "Person-uk",
                'email' => $order->customer['email_address'],
                //email: "customer@email.uk",
                //title: "",
                'street_address' => "New Burlington St, 10, Apt 214",
                'street_address2' => "",
                'postal_code' => "W13 3BG",
                'city' => "London",
                //region: "",
                'phone' => $order->customer['telephone'],
                //phone: "01895081461",
                'country' => "GB"
              );
              
              $shipping_address = array(
                'given_name' => "Test",
                'family_name' => "Person-uk",
                'email' => $order->customer['email_address'],
                //email: "customer@email.uk",
                //title: "",
                'street_address' => "New Burlington St, 10, Apt 214",
                'street_address2' => "",
                'postal_code' => "W13 3BG",
                'city' => "London",
                //region: "",
                'phone' => $order->customer['telephone'],
                //phone: "01895081461",
                'country' => "GB"
              );
            
            } else {

              $billing_address = array(
                'given_name' => $order->billing['firstname'],
                'family_name' => $order->billing['lastname'],
                'email' => $order->customer['email_address'],
                //title: "",
                'street_address' => $order->billing['street_address'],
                'street_address2' => $order->billing['suburb'],
                'postal_code' => $order->billing['postcode'],
                'city' => $order->billing['city'],
                //region: "",
                'phone' => $order->customer['telephone'],
                'country' => $order->billing['country']['iso_code_2']
              );
              
              $shipping_address = array(
                'given_name' => $order->delivery['firstname'],
                'family_name' => $order->delivery['lastname'],
                'email' => $order->customer['email_address'],
                //title: "",
                'street_address' => $order->delivery['street_address'],
                'street_address2' => $order->delivery['suburb'],
                'postal_code' => $order->delivery['postcode'],
                'city' => $order->delivery['city'],
                'phone' => $order->customer['telephone'],
                'country' => $order->delivery['country']['iso_code_2']
              );
              
            }
        
        $vars = array(
            //"purchase_country" => $order->customer['country']['iso_code_2'],
            //"purchase_currency" => $order->info['currency'],
            "purchase_country" => 'GB',
            "purchase_currency" => 'GBP',
            "locale" => "en-GB",
            "order_amount" => round((float)$order->info['total']*100, 0),
            "order_tax_amount" => round((float)$klarna_subtotal*0.2, 0),
            "order_lines" => $lines,
            "shipping_address" => $shipping_address,
            "billing_address" => $billing_address,
            "merchant_urls" => array(
                "confirmation" => zen_href_link(FILENAME_CHECKOUT_CONFIRMATION)
            ),
            "attachment" => array(
                "content_type" => "application/vnd.klarna.internal.emd-v2+json",
                "body" => "{\"other_delivery_address\":[{\"shipping_method\":\"".$order->info['shipping_method']."\",\"shipping_type\":\"normal\"}]}"
            )
        );
//echo '<pre>'; print_r($vars); echo '</pre>'; exit();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url.'/payments/v1/authorizations/'.$_POST['klarna_authorization_token'].'/order');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array(
            'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
            'Content-Type: application/json'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $server_output = curl_exec ($ch);
        //echo '<pre>'; print_r($server_output); echo '</pre>'; exit();
        curl_close($ch);
        
        $decoded = json_decode($server_output);
        //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
        
        if (isset($decoded->fraud_status)) {
            
            $this->order_comments = "Authorization token: ".$_POST['klarna_authorization_token']."\nFraud Status: ".$decoded->fraud_status."\nOrder ID: ".$decoded->order_id."";
            
            $this->klarna_order_id = $decoded->order_id;
        
            return true;
            
        } else {
            
            $messageStack->add_session('checkout_payment', "Error code: ".$decoded->error_code."\nError message: ".$decoded->error_messages[0], 'error');
            
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT));
            
        }

        return false;

    }

    function after_process() {
        
        global $insert_id, $db, $order;
        
        $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values ('".$this->order_comments."', ".$insert_id.", ".$this->order_status.", -1, now() )";
        $db->Execute($sql);
            
            //  save Klarna order id in ORDERS table
            $sql = "UPDATE ".TABLE_ORDERS."
                    SET klarna_order_id = '".$this->klarna_order_id."'
                    WHERE orders_id = ".$insert_id;
            $db->Execute($sql);
            
        //  get order info
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url.'/ordermanagement/v1/orders/'.$this->klarna_order_id);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array(
            'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
            'Content-Type: application/json'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $server_output = curl_exec ($ch);
        //echo '<pre>'; print_r($server_output); echo '</pre>'; exit();
        curl_close($ch);
        
        $decoded = json_decode($server_output);
        //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
        
        //  capture order
        $order_lines = array();
        foreach ($decoded->order_lines as $key => $value) {
            $order_lines[] = array(
                'reference' => $value->reference,
                'type' => $value->type,
                'quantity' => $value->quantity,
                'quantity_unit' => $value->quantity_unit,
                'name' => $value->name,
                'total_amount' => $value->total_amount,
                'unit_price' => $value->unit_price,
                'total_discount_amount' => $value->total_discount_amount,
                'product_url' => $value->product_url,
                'image_url' => $value->image_url
            );
        }
        
        $vars = array(
            "captured_amount" => $decoded->order_amount,
            "description" => "Playwell order # ".$insert_id,
            "order_lines" => $order_lines
        );        
        //echo '<pre>'; print_r($vars); echo '</pre>'; //exit();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url.'/ordermanagement/v1/orders/'.$this->klarna_order_id.'/captures');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array(
            'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
            'Content-Type: application/json'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $server_output = curl_exec ($ch);
        //echo '<pre>'; print_r($server_output); echo '</pre>'; //exit();
        curl_close($ch);
        
        $decoded = json_decode($server_output);
        
        if (isset($decoded->error_code)) {
            
            $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values ('Capture error: ".$decoded->error_code."\nError message: ".$decoded->error_messages[0]."', ".$insert_id.", ".$this->order_status.", -1, now() )";
            $db->Execute($sql);
            
        } else {
            
            $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values ('Order captured successfully', ".$insert_id.", ".$this->order_status.", -1, now() )";
            $db->Execute($sql);
            
        }
        

    }
    
    
      function admin_notification($zf_order_id) {
        global $db;
        $output = '';
        $sql = "SELECT klarna_order_id
                FROM " . TABLE_ORDERS . " 
                WHERE orders_id = '" . (int)$zf_order_id . "'";
        $res = $db->Execute($sql);
        if ($res->RecordCount() > 0 && $res->fields['klarna_order_id'] != '' && file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/klarna/klarna_admin_notification.php')) require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/klarna/klarna_admin_notification.php');
        return $output;
      }
      
    
    function _doRefund($oID) {
        
        global $db, $messageStack;
        
        $sql = "SELECT klarna_order_id
                FROM " . TABLE_ORDERS . " 
                WHERE orders_id = '" . (int)$oID . "'";
        $res = $db->Execute($sql);
        
        if ($res->RecordCount() > 0 && $res->fields['klarna_order_id'] != '') {
        
            $vars = array(
                "description" => $_POST['klarnaRefundDesc'],
                "refunded_amount" => (float)$_POST['klarnaRefundAmount'] * 100
            );
        
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->api_url.'/ordermanagement/v1/orders/'.$res->fields['klarna_order_id'].'/refunds');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));  //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $headers = array(
                'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
                'Content-Type: application/json'
            );
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $server_output = curl_exec ($ch);
            //echo '<pre>'; print_r($server_output); echo '</pre>'; exit();
            curl_close($ch);
            
            $decoded = json_decode($server_output);
            //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
            
            if (isset($decoded->error_code)) {
                $messageStack->add_session("Refund error: ".$decoded->error_code."\nError message: ".$decoded->error_messages[0], 'error');
            
            } else {

                $comments = $_POST['klarnaRefundDesc'];
                zen_update_orders_history($oID, $comments, null, 7, 0);
            
            }
        }
        
    }
    
    
    function _doVoid($oID) {
        
        global $db, $messageStack;
        
        $sql = "SELECT klarna_order_id
                FROM " . TABLE_ORDERS . " 
                WHERE orders_id = '" . (int)$oID . "'";
        $res = $db->Execute($sql);
        
        if ($res->RecordCount() > 0 && $res->fields['klarna_order_id'] != '') {
        
            $vars = array(
                "description" => $_POST['klarnaVoidDesc']
            );
        
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->api_url.'/ordermanagement/v1/orders/'.$res->fields['klarna_order_id'].'/cancel');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));  //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $headers = array(
                'Authorization: Basic '.base64_encode($this->merchant_id.':'.htmlspecialchars_decode($this->password)),
                'Content-Type: application/json'
            );
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $server_output = curl_exec ($ch);
            //echo '<pre>'; print_r($server_output); echo '</pre>'; exit();
            curl_close($ch);
            
            $decoded = json_decode($server_output);
            //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
            
            if (isset($decoded->error_code)) {
                $messageStack->add_session("Cancel order error: ".$decoded->error_code."\nError message: ".$decoded->error_messages[0], 'error');
            
            } else {

                $comments = $_POST['klarnaVoidDesc'];
                zen_update_orders_history($oID, $comments, null, 6, 0);
            
            }
        }
        
    }
        

    function get_error() {
      return false;
    }

    function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_KLARNA_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

    function install() {
      global $db, $messageStack;
      if (defined('MODULE_PAYMENT_KLARNA_STATUS')) {
        $messageStack->add_session('Klarna module already installed.', 'error');
        zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=klarna', 'NONSSL'));
        return 'failed';
      }
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Klarna Module', 'MODULE_PAYMENT_KLARNA_STATUS', 'True', 'Do you want to accept Klarna payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_KLARNA_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())"); 
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_KLARNA_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_KLARNA_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
      
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Klarna Test Mode', 'MODULE_PAYMENT_KLARNA_TEST_MODE', 'True', 'Do you want to enable Klarna Test Mode?', '6', '5', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Klarna Merchant ID', 'MODULE_PAYMENT_KLARNA_MERCHANT_ID', '', 'Klarna Merchant ID', '6', '10', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_PAYMENT_KLARNA_PASSWORD', '', 'Password', '6', '12', now())");
      
   }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_KLARNA_STATUS', 'MODULE_PAYMENT_KLARNA_ZONE', 'MODULE_PAYMENT_KLARNA_ORDER_STATUS_ID', 'MODULE_PAYMENT_KLARNA_SORT_ORDER',
      'MODULE_PAYMENT_KLARNA_TEST_MODE', 'MODULE_PAYMENT_KLARNA_MERCHANT_ID', 'MODULE_PAYMENT_KLARNA_PASSWORD');
    }
  }
