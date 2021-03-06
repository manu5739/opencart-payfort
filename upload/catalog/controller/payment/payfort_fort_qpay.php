<?php

class ControllerPaymentPayfortFortQpay extends Controller {

    private $_gatewayHost        = 'https://checkout.payfort.com/';
    private $_gatewaySandboxHost = 'https://sbcheckout.payfort.com/';
    public function index() {
        $this->language->load('payment/payfort_fort');
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        $this->data['text_general_error']  = $this->language->get('text_general_error');
        $this->data['text_error_card_decline'] = $this->language->get('text_error_card_decline');
        
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payfort_fort_qpay.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/payfort_fort_qpay.tpl';
        } else {
            $this->template = 'default/template/payment/payfort_fort_qpay.tpl';
        }
        $this->render();
    }
    
    public function response(){
        $fortParams = array_merge($_GET,$_POST); // never include PUT and other restful actions
        if (isset($fortParams['response_code']) && isset($fortParams['merchant_reference'])){
            $this->language->load('payment/payfort_fort');
            $this->load->model('checkout/order');
            $order_id = $fortParams['merchant_reference'];
            $order_info = $this->model_checkout_order->getOrder($order_id);
            $success = false;
            $params = $fortParams;
            $hashString = '';
            $signature = $fortParams['signature'];
            
            unset($params['signature']);
            unset($params['route']);
            $trueSignature = $this->_calculateSignature($params, 'response');
            if ($trueSignature != $signature){
                $success = false;
            }
            else{
                $response_code      = $params['response_code'];
                $response_message   = $params['response_message'];
                $status             = $params['status'];
                
                if (substr($response_code, 2) != '000'){

                }
                else{
                    $success = true;
                    $this->model_checkout_order->confirm($order_id, $this->config->get('config_order_status_id'));
                    $this->model_checkout_order->update($order_id, $this->config->get('payfort_fort_order_status_id'), 'Paid: ' . $order_id, false);
                    header('location:'.$this->url->link('payment/payfort_fort/success'));
                }
            }
            
            if (!$success){
                //$this->model_checkout_order->confirm($order_id, 10, 'Payment Error', false);
                $this->model_checkout_order->update($order_id, 10, 'Payment Failed', false);
                header('location:'.$this->url->link('payment/payfort_fort/error'));
            }
            
        }
    }

    public function send() {

        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $postData = array(
            'amount'                => round($order_info['total'] * $order_info['currency_value'],2) * 100,
            'currency'              => strtoupper($order_info['currency_code']),
            'merchant_identifier'   => $this->config->get('payfort_fort_entry_merchant_identifier'),
            'access_code'           => $this->config->get('payfort_fort_entry_access_code'),
            'merchant_reference'    => $order_id,
            'customer_email'        => $order_info['email'],
            'command'               => $this->config->get('payfort_fort_entry_command'),
            'language'              => $this->config->get('payfort_fort_entry_language'),
            'return_url'            => $this->url->link('payment/payfort_fort/response', '', 'SSL'),
        );
        $postData['payment_option'] = 'NAPS';
        $postData['order_description'] = $order_id;  
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET payment_method = 'Credit / Debit Card', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

        //calculate request signature
        $signature = $this->_calculateSignature($postData, 'request');
        $postData['signature'] = $signature;
        
        if ($this->config->get('payfort_fort_entry_sandbox_mode')){
            $gatewayUrl = $this->_gatewaySandboxHost.'FortAPI/paymentPage';
        }
        else{
            $gatewayUrl = $this->_gatewayHost.'FortAPI/paymentPage';
        }
        
        $form =  '<form style="display:none" name="payfortpaymentform" id="payfortpaymentform" method="post" action="'.$gatewayUrl.'" id="form1" name="form1">';
        
        foreach ($postData as $k => $v){
            $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
        }
        
        $form .= '<input type="submit" value="" id="submit" name="submit2">';
        
        $json = array();
        
        $json['form'] = $form;
        
        //$this->model_checkout_order->confirm($order_id, 1, 'Pending Payment', false);

        $this->response->setOutput(json_encode($json));
    }
    
    public function success() { 	
		if (isset($this->session->data['order_id'])) {
			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);	
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
		}	
									   
        $this->language->load('payment/payfort_fort');
		$this->language->load('checkout/success');
		
		$this->document->setTitle($this->language->get('heading_success_title'));
		
		$this->data['breadcrumbs'] = array(); 

      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('common/home'),
        	'text'      => $this->language->get('text_home'),
        	'separator' => false
      	); 
		
      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('checkout/cart'),
        	'text'      => $this->language->get('text_basket'),
        	'separator' => $this->language->get('text_separator')
      	);
				
		$this->data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/checkout', '', 'SSL'),
			'text'      => $this->language->get('text_checkout'),
			'separator' => $this->language->get('text_separator')
		);	
					
      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('payment/payfort_fort/success'),
        	'text'      => $this->language->get('text_p_success'),
        	'separator' => $this->language->get('text_separator')
      	);

		$this->data['heading_title'] = $this->language->get('heading_success_title');
		
		if ($this->customer->isLogged()) {
    		$this->data['text_message'] = sprintf($this->language->get('text_success_customer'), $this->url->link('account/account', '', 'SSL'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/download', '', 'SSL'), $this->url->link('information/contact'));
		} else {
    		$this->data['text_message'] = sprintf($this->language->get('text_success_guest'), $this->url->link('information/contact'));
		}
		
    	$this->data['button_continue'] = $this->language->get('button_continue');

    	$this->data['continue'] = $this->url->link('common/home');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/success.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/common/success.tpl';
		} else {
			$this->template = 'default/template/common/success.tpl';
		}
		
		$this->children = array(
			'common/column_left',
			'common/column_right',
			'common/content_top',
			'common/content_bottom',
			'common/footer',
			'common/header'			
		);
				
		$this->response->setOutput($this->render());
  	}
    
    public function error() { 	
			   
		$this->language->load('payment/payfort_fort');
		$this->language->load('checkout/success');
		
		$this->document->setTitle($this->language->get('heading_failed_title'));
		
		$this->data['breadcrumbs'] = array(); 

      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('common/home'),
        	'text'      => $this->language->get('text_home'),
        	'separator' => false
      	); 
		
      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('checkout/cart'),
        	'text'      => $this->language->get('text_basket'),
        	'separator' => $this->language->get('text_separator')
      	);
				
		$this->data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/checkout', '', 'SSL'),
			'text'      => $this->language->get('text_checkout'),
			'separator' => $this->language->get('text_separator')
		);	
					
      	$this->data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('payment/payfort_fort/error'),
        	'text'      => $this->language->get('text_failed'),
        	'separator' => $this->language->get('text_separator')
      	);

		$this->data['heading_title'] = $this->language->get('heading_failed_title');
		
		if ($this->customer->isLogged()) {
    		$this->data['text_message'] = sprintf($this->language->get('text_failed_customer'), $this->url->link('account/account', '', 'SSL'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/download', '', 'SSL'), $this->url->link('information/contact'));
		} else {
    		$this->data['text_message'] = sprintf($this->language->get('text_failed_guest'), $this->url->link('information/contact'));
		}
		
    	$this->data['button_continue'] = $this->language->get('button_continue');

    	$this->data['continue'] = $this->url->link('common/home');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/success.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/common/success.tpl';
		} else {
			$this->template = 'default/template/common/success.tpl';
		}
		
		$this->children = array(
			'common/column_left',
			'common/column_right',
			'common/content_top',
			'common/content_bottom',
			'common/footer',
			'common/header'			
		);
				
		$this->response->setOutput($this->render());
    }
    
    /**
     * calculate fort signature
     * @param array $arr_data
     * @param sting $sign_type request or response
     * @return string fort signature
     */
    private function _calculateSignature($arr_data, $sign_type = 'request') {

        $shaString = '';

        ksort($arr_data);
        foreach ($arr_data as $k=>$v){
            $shaString .= "$k=$v";
        }

        if($sign_type == 'request') {
            $shaString = $this->config->get('payfort_fort_entry_request_sha_phrase') . $shaString . $this->config->get('payfort_fort_entry_request_sha_phrase');
        }
        else{
            $shaString = $this->config->get('payfort_fort_entry_response_sha_phrase') . $shaString . $this->config->get('payfort_fort_entry_response_sha_phrase');
        }
        $signature = hash($this->config->get('payfort_fort_entry_hash_algorithm') ,$shaString);

        return $signature;
    }

    /**
     * Convert Amount with dicemal points
     * @param decimal $amount
     * @param decimal $currency_value
     * @param string  $currency_code
     * @return decimal
     */
    private function _convertFortAmount($amount, $currency_value, $currency_code) {
        $new_amount = 0;
        //$decimal_points = $this->currency->getDecimalPlace();
        $decimal_points = $this->getCurrencyDecimalPoints($currency_code);
        $new_amount = round($amount * $currency_value, $decimal_points) * (pow(10, $decimal_points));
        return $new_amount;
    }
    
    /**
     * 
     * @param string $currency
     * @param integer 
     */
    private function getCurrencyDecimalPoints($currency) {
        $decimalPoint  = 2;
        $arrCurrencies = array(
            'JOD' => 3,
            'KWD' => 3,
            'OMR' => 3,
            'TND' => 3,
            'BHD' => 3,
            'LYD' => 3,
            'IQD' => 3,
        );
        if (isset($arrCurrencies[$currency])) {
            $decimalPoint = $arrCurrencies[$currency];
        }
        return $decimalPoint;
    }
}

