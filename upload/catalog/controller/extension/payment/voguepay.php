<?php
class ControllerExtensionPaymentVoguepay extends Controller {

	public function index() {

		// $this->load->language('extention/payment/voguepay');
		$data['button_confirm'] = $this->language->get('button_confirm');
		$this->load->model('checkout/order');

		$voguepay_merchant_id = $this->config->get('payment_voguepay_merchant_id');
		$order_id=$this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$amount = $order_info['total'];
		
		if($this->session->data['shipping_method']['code'] == 'flat.flat'){
			$memo = $this->session->data['shipping_method']['title'].' : '.$this->session->data['shipping_method']['text'];
		}else{ $memo = ''; }

		//$success_url = $this->url->link('checkout/success');
		//$fail_url = $this->url->link('checkout/checkout', '', 'SSL');
		$success_url = $this->url->link('extension/payment/voguepay/success');
		$fail_url = $this->url->link('extension/payment/voguepay/success');
		$notify_url = $this->url->link('extension/payment/voguepay/callback');
		
		$i = 1;
		foreach ($this->cart->getProducts() as $product) {
			$voguepay_items[] = array(
				'item_'.$i   => $product['name'],
				'description_'.$i   => $product['name'],
				'price_'.$i  => $product['total']
				); 
		 $i++; 
		 $j = $i;
		}
		if(!empty($this->session->data['shipping_method'])){
			$voguepay_items[] = array(
				'item_'.$j   => $this->session->data['shipping_method']['title'],
				'price_'.$j  => $this->session->data['shipping_method']['cost']
				); 
		}
				
		$voguepay_args_array = array();
		$voguepay_args = array(
			'v_merchant_id'   => $voguepay_merchant_id,
			'merchant_ref'   => $order_id,
			'total'         => $amount,
			'success_url'   => $success_url,
			'notify_url'    => $notify_url,
			'fail_url'      => $fail_url, 
			'developer_code' => '5a91e8e44b4bc', 
			'memo' => $this->session->data['shipping_method']['title']
			);
		foreach($voguepay_items as $item){
			$voguepay_args = array_merge($voguepay_args,$item);
		}
		foreach($voguepay_args as $key => $value){
				$voguepay_args_array[] = "<input type='hidden' name='".$key."' value='".$value."'/>";
		}
		
		$data['action'] = 'https://voguepay.com/pay/';
		$data['voguepay_hidden_args'] = implode('', $voguepay_args_array);

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/payment/voguepay.twig')) {
			return $this->load->view($this->config->get('config_template') . 'extension/payment/voguepay', $data);
		} else {
			return $this->load->view('extension/payment/voguepay', $data);
		}
	}
	
	public function callback() {
		if ( $_REQUEST['transaction_id'] != '' ){
			$xml = file_get_contents('https://voguepay.com/?v_transaction_id='.$_REQUEST['transaction_id']);
			$xml_elements = new SimpleXMLElement($xml);
			$transaction = array();
			foreach($xml_elements as $key => $value) 
			{
				$transaction[$key]=$value;
			}
			
			$email = $transaction['email'];
			$total = $transaction['total'];
			$date = $transaction['date'];
			$order_id = $transaction['merchant_ref'];
			$status = $transaction['status'];
			$transaction_id = $transaction['transaction_id'];
			
			if ($order_id !== ''){
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($order_id);
				if ($order_info) {
					if (trim(strtolower($status)) == 'approved'){

						//$this->model_checkout_order->confirm($order_id, $this->config->get('voguepay_order_status_id'));
						$message = 'Payment Status : - '.$status.' - Transaction Id: '.$transaction_id;
						$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_voguepay_order_status_id'), $message , false);
						//$this->model_checkout_order->update($order_id, $this->config->get('voguepay_order_status_id'), $message, false);
					}else{
						
						//$this->model_checkout_order->confirm($order_id, $this->config->get('config_order_status_id'));
						$message = 'Payment Status : - '.$status.' - Transaction Id: '.$transaction_id;
						$this->model_checkout_order->addOrderHistory($order_id, $order_id, $message, false);	
						//$this->model_checkout_order->update($order_id, $this->config->get('config_order_status_id'), $message, false);						
					}
				}
			}
		}
	}
	public function success() {
		if ( $_REQUEST['transaction_id'] != '' ){
			$xml = file_get_contents('https://voguepay.com/?v_transaction_id='.$_REQUEST['transaction_id']);
			$xml_elements = new SimpleXMLElement($xml);
			$transaction = array();
			foreach($xml_elements as $key => $value) 
			{
				$transaction[$key]=$value;
			}
			
			$data['email'] = $transaction['email'];
			$data['total'] = $transaction['total'];
			$data['date'] = $transaction['date'];
			$data['order_id'] = $transaction['merchant_ref'];
			$data['status'] = $transaction['status'];
			$data['transaction_id'] = $transaction['transaction_id'];
			 	
			if(trim(strtolower($data['status'])) == 'approved' || trim(strtolower($data['status'])) == 'pending'){
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
			}	
									   
		$this->language->load('checkout/success');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$data['breadcrumbs'] = array(); 

      	$data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('common/home'),
        	'text'      => $this->language->get('text_home'),
        	'separator' => false
      	); 
		
      	$data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('checkout/cart'),
        	'text'      => $this->language->get('text_basket'),
        	'separator' => $this->language->get('text_separator')
      	);
				
		$data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/checkout', '', 'SSL'),
			'text'      => $this->language->get('text_checkout'),
			'separator' => $this->language->get('text_separator')
		);	
					
      	$data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('checkout/success'),
        	'text'      => $this->language->get('text_success'),
        	'separator' => $this->language->get('text_separator')
      	);

		$data['heading_title'] = $this->language->get('heading_title');
		
    	$data['button_continue'] = $this->language->get('button_continue');
    	$data['fail_continue'] = $this->url->link('checkout/checkout', '', 'SSL');
		$data['continue'] = $this->url->link('account/order/info', 'order_id=' . $data['order_id'], 'SSL');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['heading_title'] = $this->load->controller('common/heading_title');
		$data['footer'] = $this->load->controller('common/footer');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/payment/voguepay_success.twig')) {
			return $this->response->setOutput($this->load->view($this->config->get('config_template') . 'extension/payment/voguepay_success', $data));
		} else {
			return $this->response->setOutput($this->load->view('extension/payment/voguepay_success', $data));
		}
		
		}
	}
	public function confirm() {
		$this->load->model('checkout/order');
		$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('payment_voguepay_order_status_id'));
	}
}
