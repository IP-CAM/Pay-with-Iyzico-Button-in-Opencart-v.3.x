<?php 

class ControllerExtensionModulePaywithiyzicobutton extends Controller {

    private $module_version      = VERSION;
    private $module_product_name = 'eleven-1.0';

    private $customerModel;
    private $paywithiyzicobutton_json;

    private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly) {

        if (PHP_VERSION_ID < 70300) {

            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        }
        else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly,
            ]);


        }
    }

    public function index($setting = null)
    {
        //var_dump($this->customer->isLogged());exit;
        if($this->customer->isLogged()){
            $product_id = $this->request->get['product_id'];
            $this->load->model('catalog/product');
            $this->load->model('account/customer');
            $this->load->model('account/address');

            $address = $this->model_account_address->getAddress($this->customer->getAddressId());
            if(!$address){
                return ;
            }
            $customer = $this->model_account_customer->getCustomer($this->customer->getId());
            $product_info = $this->model_catalog_product->getProduct($product_id);
            $categories = $this->model_catalog_product->getCategories($product_id);


            foreach ($categories as $key => $value){
                $product_info['category_'.$key] = isset($value['category_id']) ? $value['category_id'] : "";
            }
            //var_dump($customer);exit;
            $data = array_merge($address,$customer,$product_info);
            return $this->load->view('extension/module/paywithiyzicobutton', $data);
        }
    }

    public function addOrder($array_data)
    {

        $address_id = $this->customer->getAddressId();

        $this->load->model('account/address');
        $this->load->model('checkout/order');
        $this->load->model('catalog/product');
        $this->load->model('localisation/currency');

        $currency = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));
        $address = $this->model_account_address->getAddress($address_id);

        $quantity = count($array_data['basketItems']);

        $product = $this->model_catalog_product->getProduct($array_data['basketItems'][0]['id']);

        $product['quantity'] = $quantity;
        $product['total'] = $this->priceParser($array_data['price']) * $currency['value'];
        $product['reward'] = $product['total'];
        $product['tax'] = 0.0000;
        $product['option'] = array();

        $insert_data = array(
            'invoice_no'              => 0,
            'invoice_prefix'          => "INV-2020-00",
            'store_id'                => 0,
            'store_name'              => $this->config->get('config_name'),
            'store_url'               => "http://iyzicostudy.test/",
            'customer_id'             => $this->customer->getId(),
            'customer_group_id'       => 1,
            'firstname'               => $array_data['buyer']['name'],
            'lastname'                => $array_data['buyer']['surname'],
            'email'                   => $this->customer->getEmail(),
            'telephone'               => $this->customer->getTelephone(),
            'custom_field'            => json_decode("", true),
            'payment_firstname'       => $array_data['buyer']['name'],
            'payment_lastname'        => $array_data['buyer']['surname'],
            'payment_company'         => $address['company'],
            'payment_address_1'       => $address['address_1'],
            'payment_address_2'       => $address['address_2'],
            'payment_postcode'        => $address['postcode'],
            'payment_city'            => $address['city'],
            'payment_zone_id'         => $address['zone_id'],
            'payment_zone'            => $address['zone'],
            'payment_zone_code'       => $address['zone_code'],
            'payment_country_id'      => $address['country_id'],
            'payment_country'         => $address['country'],
            'payment_iso_code_2'      => $address['iso_code_2'],
            'payment_iso_code_3'      => $address['iso_code_3'],
            'payment_address_format'  => $address['address_format'],
            'payment_custom_field'    => json_decode("", true),
            'payment_method'          => "Pay with Iyzico + Button",
            'payment_code'            => "paywithiyzicobutton",
            'shipping_firstname'      => $array_data['buyer']['name'],
            'shipping_lastname'       => $array_data['buyer']['surname'],
            'shipping_company'        => $address['company'],
            'shipping_address_1'      => $address['address_1'],
            'shipping_address_2'      => $address['address_2'],
            'shipping_postcode'       => $address['postcode'],
            'shipping_city'           => $address['city'],
            'shipping_zone_id'        => $address['zone_id'],
            'shipping_zone'           => $address['zone'],
            'shipping_zone_code'      => $address['zone_code'],
            'shipping_country_id'     => $address['country_id'],
            'shipping_country'        => $address['country'],
            'shipping_iso_code_2'     => $address['iso_code_2'],
            'shipping_iso_code_3'     => $address['iso_code_3'],
            'shipping_address_format' => $address['address_format'],
            'shipping_custom_field'   => json_decode("", true),
            'shipping_method'         => "Flat Shipping Rate",
            'shipping_code'           => "flat.flat",
            'comment'                 => "",
            'total'                   => $array_data['price'],
            'order_status_id'         => 0,
            'affiliate_id'            => 0,
            'commission'              => 0.0000,
            'language_id'             => $this->language->get('id'),
            'language_code'           => $this->language->get('code'),
            'currency_id'             => $currency['currency_id'],
            'currency_code'           => $currency['code'],
            'currency_value'          => $currency['value'],
            'ip'                      => $array_data['buyer']['ip'],
            'forwarded_ip'            => "",
            'user_agent'              => $_SERVER['HTTP_USER_AGENT'],
            'accept_language'         => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            'date_added'              => date("Y/m/d h:i:s"),
            'date_modified'           => date("Y/m/d h:i:s"),
            'marketing_id'            => 0,
            'tracking'                => '',
            'products'                => [$product]
        );

        return $this->model_checkout_order->addOrder($insert_data);
    }

    public function payservice() {


        $cookieControl = false;

        if(isset($_COOKIE['PHPSESSID'])) {
            $sessionKey = "PHPSESSID";
            $sessionValue = $_COOKIE['PHPSESSID'];
            $cookieControl = true;
        }

        if(isset($_COOKIE['OCSESSID'])) {

            $sessionKey = "OCSESSID";
            $sessionValue = $_COOKIE['OCSESSID'];
            $cookieControl = true;
        }

        if($cookieControl) {
            $setCookie = $this->setcookieSameSite($sessionKey,$sessionValue, time() + 86400, "/", $_SERVER['SERVER_NAME'],true, true);
        }

        $paywithiyzicoData = $this->request->post;
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('extension/module/paywithiyzicobutton');

        $module_attribute                      = false;
        $products                              = $this->request->post["basketItems"];

        $order_id                              = (int) $this->addOrder($this->request->post);
        $customer_id                           = (int) isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
        $user_id                               = (int) isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
        $order_info                            = $this->model_checkout_order->getOrder($order_id);
        $products                              = $this->request->post["basketItems"];

        $api_key                               = $this->config->get('module_paywithiyzicobutton_apiKey');
        $secret_key                            = $this->config->get('module_paywithiyzicobutton_secretKey');
        //$payment_source                        = "OPENCART-".$this->module_version."|".$this->module_product_name."|".$this->config->get('payment_iyzico_design');
        $payment_source                        = "OPENCART-".$this->module_version."|".$this->module_product_name."|responsive";

        $user_create_date                      = $this->model_extension_module_paywithiyzicobutton->getUserCreateDate($user_id);

        $this->session->data['conversation_id'] = $order_id;


        $order_info['payment_address']         = $order_info['payment_address_1']." ".$order_info['payment_address_2'];
        $order_info['shipping_address']        = $order_info['shipping_address_1']." ".$order_info['shipping_address_2'];


        /* Order Detail */
        $paywithiyzico = new stdClass;
        $paywithiyzico->locale                    = $this->language->get('code');
        $paywithiyzico->conversationId            = (string) $order_id;
        $paywithiyzico->price                        = $this->priceParser($paywithiyzicoData['price'] * $order_info['currency_value']);
        $paywithiyzico->paidPrice                    = $this->priceParser($paywithiyzicoData['paidPrice'] * $order_info['currency_value']);
        //$paywithiyzico->currency                     = 'TRY';
        $paywithiyzico->currency                     = $order_info['currency_code'];
        $paywithiyzico->basketId                     = $order_id;
        $paywithiyzico->paymentGroup                 = "PRODUCT";
        //$paywithiyzico->callbackUrl                  = "https://webhook.site/bff28c3c-95d2-429b-847d-c4b5c6f407e6";
        $paywithiyzico->callbackUrl                  = $this->url->link('extension/module/paywithiyzicobutton/getcallback', '', true);
        $paywithiyzico->cancelUrl                  = "https://www.google.com";
        $paywithiyzico->paymentSource                = $payment_source;

        if ($paywithiyzico->paidPrice === 0) {
            return false;
        }

        $paywithiyzico->buyer = new stdClass;
        $paywithiyzico->buyer->id                          = $order_info['customer_id'];
        $paywithiyzico->buyer->name                        = $this->dataCheck($order_info['firstname']);
        $paywithiyzico->buyer->surname                     = $this->dataCheck($order_info['lastname']);
        $paywithiyzico->buyer->identityNumber              = '11111111111';
        $paywithiyzico->buyer->email                       = $this->dataCheck($order_info['email']);
        $paywithiyzico->buyer->gsmNumber                   = $this->dataCheck($order_info['telephone']);
        $paywithiyzico->buyer->registrationDate            = $user_create_date;
        $paywithiyzico->buyer->lastLoginDate               = date('Y-m-d H:i:s');
        $paywithiyzico->buyer->registrationAddress         = $this->dataCheck($order_info['payment_address']);
        $paywithiyzico->buyer->city                        = $this->dataCheck($order_info['payment_zone']);
        $paywithiyzico->buyer->country                     = $this->dataCheck($order_info['payment_country']);
        $paywithiyzico->buyer->zipCode                     = $this->dataCheck($order_info['payment_postcode']);
        $paywithiyzico->buyer->ip                          = $this->dataCheck($this->getIpAdress());

        $paywithiyzico->shippingAddress = new stdClass;
        $paywithiyzico->shippingAddress->address          = $this->dataCheck($order_info['shipping_address']);
        $paywithiyzico->shippingAddress->zipCode          = $this->dataCheck($order_info['shipping_postcode']);
        $paywithiyzico->shippingAddress->contactName      = $this->dataCheck($order_info['shipping_firstname']);
        $paywithiyzico->shippingAddress->city             = $this->dataCheck($order_info['shipping_zone']);
        $paywithiyzico->shippingAddress->country          = $this->dataCheck($order_info['shipping_country']);


        $paywithiyzico->billingAddress = new stdClass;
        $paywithiyzico->billingAddress->address          = $this->dataCheck($order_info['payment_address']);
        $paywithiyzico->billingAddress->zipCode          = $this->dataCheck($order_info['payment_postcode']);
        $paywithiyzico->billingAddress->contactName      = $this->dataCheck($order_info['payment_firstname']);
        $paywithiyzico->billingAddress->city             = $this->dataCheck($order_info['payment_zone']);
        $paywithiyzico->billingAddress->country          = $this->dataCheck($order_info['payment_country']);

        foreach ($products as $key => $product) {
            $price = $product['price'] * $order_info['currency_value'];

            if($price) {
                $paywithiyzico->basketItems[$key] = new stdClass();

                $paywithiyzico->basketItems[$key]->id                = $product['id'];
                $paywithiyzico->basketItems[$key]->price             = $this->priceParser($price);
                $paywithiyzico->basketItems[$key]->name              = $product['name'];
                $paywithiyzico->basketItems[$key]->category1         = $product['category1'];
                $paywithiyzico->basketItems[$key]->itemType          = "PHYSICAL";
            }
        }
        // bug
        $shipping = $this->shippingInfo();

        if(!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {

            $shippigKey = count($paywithiyzico->basketItems);

            $paywithiyzico->basketItems[$shippigKey] = new stdClass();

            $paywithiyzico->basketItems[$shippigKey]->id            = 'Kargo';
            $paywithiyzico->basketItems[$shippigKey]->price         = $this->priceParser($shipping['cost'] * $order_info['currency_value']);
            $paywithiyzico->basketItems[$shippigKey]->name          = $shipping['title'];
            $paywithiyzico->basketItems[$shippigKey]->category1     = "Kargo";
            $paywithiyzico->basketItems[$shippigKey]->itemType      = "VIRTUAL";
        }


        $rand_value             = rand(100000,99999999);
        $order_object           = $this->model_extension_module_paywithiyzicobutton->createFormInitializObjectSort($paywithiyzico);
        $pki_generate           = $this->model_extension_module_paywithiyzicobutton->pkiStringGenerate($order_object);
        $authorization_data     = $this->model_extension_module_paywithiyzicobutton->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

        $paywithiyzico_json = json_encode($paywithiyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);


        //$this->session->data['patwithiyzicobutton'] = $paywithiyzico_json;
        //$this->session->data['customer'] = json_encode($this->customer);
        //$this->customerModel = $this->customer;
        //$this->paywithiyzicobutton_json = $paywithiyzico_json;

        $form_response = $this->model_extension_module_paywithiyzicobutton->createFormInitializeRequest($paywithiyzico_json,$authorization_data);

        $data['pwi_redirect'] = $form_response->payWithIyzicoPageUrl;
        return $this->response->setOutput(json_encode($data));


    }


    public function getCallBack()  {

        try {

            $this->load->language('extension/module/paywithiyzicobutton');

            if(!isset($this->request->post['token']) || empty($this->request->post['token'])) {

                $errorMessage = 'invalid token';
                throw new \Exception($errorMessage);

            }

            $this->load->model('checkout/order');
            $this->load->model('extension/module/paywithiyzicobutton');

            $api_key                               = $this->config->get('module_paywithiyzicobutton_apiKey');
            $secret_key                            = $this->config->get('module_paywithiyzicobutton_secretKey');
            $conversation_id                       = (int) $this->session->data['conversation_id'];
            //$order_id                              = (int) $this->session->data['order_id'];
            //$customer_id                           = isset($this->session->data['customer_id']) ? (int) $this->session->data['customer_id'] : 0;

            $detail_object = new stdClass();

            $detail_object->locale         = $this->language->get('code');
            $detail_object->conversationId = $conversation_id;
            $detail_object->token          = $this->db->escape($this->request->post['token']);

            $rand_value             = rand(100000,99999999);
            $pki_generate           = $this->model_extension_module_paywithiyzicobutton->pkiStringGenerate($detail_object);
            $authorization_data     = $this->model_extension_module_paywithiyzicobutton->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

            $paywithiyzico_json = json_encode($detail_object);
            $request_response = $this->model_extension_module_paywithiyzicobutton->createFormInitializeDetailRequest($paywithiyzico_json,$authorization_data);



            $paywithiyzico_local_order = new stdClass;
            $paywithiyzico_local_order->payment_id         = !empty($request_response->paymentId) ? (int) $request_response->paymentId : '';
            $paywithiyzico_local_order->order_id           = (int) $request_response->basketId;
            $this->session->data['order_id'] = (int) $request_response->basketId;
            $paywithiyzico_local_order->total_amount       = !empty($request_response->paidPrice) ? (float) $request_response->paidPrice : '';
            $paywithiyzico_local_order->status             = $request_response->paymentStatus;

            $paywithiyzico_order_insert  = $this->model_extension_module_paywithiyzicobutton->insertIyzicoOrder($paywithiyzico_local_order);


            if($request_response->paymentStatus != 'SUCCESS' || $request_response->status != 'success' ) {

                /* Redirect Error */
                $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $this->language->get('payment_failed');
                throw new \Exception($errorMessage);
            }


            /* Save Card
            if(isset($request_response->cardUserKey)) {

                if($customer_id) {

                    $cardUserKey = $this->model_extension_module_paywithiyzicobutton->findUserCardKey($customer_id,$api_key);

                    if($request_response->cardUserKey != $cardUserKey) {

                        $this->model_extension_module_paywithiyzicobutton->insertCardUserKey($customer_id,$request_response->cardUserKey,$api_key);

                    }
                }

            }
            */

            $payment_id            = $this->db->escape($request_response->paymentId);


            $payment_field_desc    = $this->language->get('payment_field_desc');
            if (!empty($payment_id)) {
                $message = $payment_field_desc.$payment_id . "\n";
            }

            $installment = $request_response->installment;

            if ($installment > 1) {
                $installement_field_desc = $this->language->get('installement_field_desc');
                $this->model_extension_module_paywithiyzicobutton->orderUpdateByInstallement($paywithiyzico_local_order->order_id,$request_response->paidPrice);
                $this->model_checkout_order->addOrderHistory($paywithiyzico_local_order->order_id, $this->config->get('module_paywithiyzicobutton_order_status'), $message);
                $messageInstallement = $request_response->cardFamily . ' - ' . $request_response->installment .$installement_field_desc;
                $this->model_checkout_order->addOrderHistory($paywithiyzico_local_order->order_id, $this->config->get('module_paywithiyzicobutton_order_status'), $messageInstallement);
            } else {
                $this->model_checkout_order->addOrderHistory($paywithiyzico_local_order->order_id, $this->config->get('module_paywithiyzicobutton_order_status'), $message);
            }


            return $this->response->redirect($this->url->link('extension/module/paywithiyzicobutton/successpage'));

        } catch (Exception $e) {

            $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $e->getMessage();

            $this->session->data['paywithiyzico_error_message'] = $errorMessage;

            return $this->response->redirect($this->url->link('extension/module/paywithiyzicobutton/errorpage'));

        }


    }

    public function test()
    {
        var_dump($this->request->post);exit;
    }


    private function dataCheck($data) {

        if(!$data || $data == ' ') {

            $data = "NOT PROVIDED";
        }

        return $data;

    }

    private function shippingInfo() {

        if(isset($this->session->data['shipping_method'])) {

            $shipping_info      = $this->session->data['shipping_method'];

        } else {

            $shipping_info = false;
        }

        if($shipping_info) {

            if ($shipping_info['tax_class_id']) {

                $shipping_info['tax'] = $this->tax->getRates($shipping_info['cost'], $shipping_info['tax_class_id']);

            } else {

                $shipping_info['tax'] = false;
            }

        }

        return $shipping_info;
    }

    private function itemPriceSubTotal($products) {

        $price = 0;

        foreach ($products as $key => $product) {

            $price+= (float) $product['total'];
        }


        $shippingInfo = $this->shippingInfo();

        if(is_object($shippingInfo) || is_array($shippingInfo)) {

            $price+= (float) $shippingInfo['cost'];

        }

        return $price;

    }

    private function priceParser($price) {

        if (strpos($price, ".") === false) {
            return $price . ".0";
        }
        $subStrIndex = 0;
        $priceReversed = strrev($price);
        for ($i = 0; $i < strlen($priceReversed); $i++) {
            if (strcmp($priceReversed[$i], "0") == 0) {
                $subStrIndex = $i + 1;
            } else if (strcmp($priceReversed[$i], ".") == 0) {
                $priceReversed = "0" . $priceReversed;
                break;
            } else {
                break;
            }
        }

        return strrev(substr($priceReversed, $subStrIndex));
    }


    private function getIpAdress() {

        $ip_address = $_SERVER['REMOTE_ADDR'];

        return $ip_address;
    }

    public function errorPage() {

        $data['continue'] = $this->url->link('common/home');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['error_title']    = 'Ödemeniz Alınamadı.';
        $data['error_message']  = $this->session->data['paywithiyzico_error_message'];
        $data['error_icon']     = 'catalog/view/theme/default/image/payment/paywithiyzico_error_icon.png';

        return $this->response->setOutput($this->load->view('extension/payment/paywithiyzico_error', $data));

    }

    public function successPage() {

        if(!isset($this->session->data['order_id'])) {
            return $this->response->redirect($this->url->link('common/home'));
        }

        $this->load->language('account/order');

        $order_id = $this->session->data['order_id'];


        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();

            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }

        $this->load->model('account/order');
        $this->load->model('catalog/product');
        $this->load->model('checkout/order');
        $this->load->model('tool/upload');

        $order_info = $this->model_checkout_order->getOrder($order_id);

        // Products
        $data['products'] = array();

        $products = $this->model_account_order->getOrderProducts($order_id);

        foreach ($products as $product) {
            $option_data = array();

            $options = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

            foreach ($options as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }

                $option_data[] = array(
                    'name'  => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                );
            }

            $product_info = $this->model_catalog_product->getProduct($product['product_id']);


            if ($product_info) {
                $reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
            } else {
                $reorder = '';
            }


            $data['products'][] = array(
                'name'     => $product['name'],
                'model'    => $product['model'],
                'option'   => $option_data,
                'quantity' => $product['quantity'],
                'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
                'reorder'  => $reorder,
                'return'   => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
            );
        }

        // Voucher
        $data['vouchers'] = array();

        $vouchers = $this->model_account_order->getOrderVouchers($order_id);

        foreach ($vouchers as $voucher) {
            $data['vouchers'][] = array(
                'description' => $voucher['description'],
                'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
            );
        }

        // Totals
        $data['totals'] = array();

        $totals = $this->model_account_order->getOrderTotals($order_id);

        foreach ($totals as $total) {
            $data['totals'][] = array(
                'title' => $total['title'],
                'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
            );
        }

        $data['comment'] = nl2br($order_info['comment']);

        // History
        $data['histories'] = array();

        $results = $this->model_account_order->getOrderHistories($order_id);

        foreach ($results as $result) {
            $data['histories'][] = array(
                'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'status'     => $result['status'],
                'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
            );
        }

        $this->document->addStyle('catalog/view/javascript/paywithiyzico/paywithiyzico_success.css');

        $data['continue'] = $this->url->link('account/order', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['success_icon']     = 'catalog/view/theme/default/image/payment/paywithiyzico_success_icon.png';

        /* Remove Order */
        unset($this->session->data['order_id']);

        return $this->response->setOutput($this->load->view('extension/payment/paywithiyzico_success', $data));
    }

}
