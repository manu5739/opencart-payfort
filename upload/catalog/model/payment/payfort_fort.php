<?php 
class ModelPaymentPayfortFort extends Model {
	public function getMethod($address, $total) {
                $this->language->load('payment/payfort_fort');
                $credit_card_enabled = $this->config->get('payfort_fort_credit_card');
               
                $status = true;
                
                if(!$credit_card_enabled) {
                    $status = false;
                }
                
                $method_data = array();
                
                if($status) {
                    $method_data = array(
                            'code'       => 'payfort_fort',
                            'title'      => $this->language->get('text_title'),
                            'sort_order' => $this->config->get('payfort_fort_sort_order')
                    );
                }
                
                return $method_data;
	}
}
?>