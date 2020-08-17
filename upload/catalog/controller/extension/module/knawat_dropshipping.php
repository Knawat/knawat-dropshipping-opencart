<?php
class ControllerExtensionModuleKnawatDropshipping extends Controller { 
	
	private $error = array();
	private $route = 'extension/module/knawat_dropshipping';

	public function __construct($registry) {
        parent::__construct($registry);
	}
	
	public function index() {
		// Do nothing here.
	}

	public function order_changed( $route, $data ){

		$order_id = $data[0];
		$order_status_id = $data[1];
		if( empty( $order_id ) || empty( $order_status_id ) ){
			return false;
		}
		$is_update = false;

		$this->load->model("checkout/order");
		$this->load->model("account/order");

		/**
		 * Load admin model.
		 */
		$admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
		require_once $admin_dir . "model/extension/module/knawat_dropshipping.php";
		$this->model_extension_module_knawat_dropshipping = new ModelExtensionModuleKnawatDropshipping( $this->registry );

		$order = $this->model_checkout_order->getOrder($order_id);
		$order_products = $this->model_account_order->getOrderProducts($order_id);

		if (!$order) {
			$this->log->write("Failed to load Order at send order to Knawat.com, getOrder() failed. order_id:" . $order_id);
			return false;
		}

		if (!$order_products) {
			$this->log->write("Failed to load Order Products at send order to Knawat.com, getOrderProducts() failed. order_id:" . $order_id);
			return false;
		}

		// Check Order if already created at MP or not.
		$knawat_order_id = $this->model_extension_module_knawat_dropshipping->get_knawat_meta( $order_id, 'knawat_order_id', 'order' );
		if( $knawat_order_id != '' ){
			$is_update = true;
		}

		/**
		 * Check is order contain Knawat Products.
		 */
		$knawat_order = $this->order_has_knawat_product( $order_products );
		if( !$knawat_order ){
			$this->model_extension_module_knawat_dropshipping->delete_knawat_meta( $order_id, 'knawat_sync_failed', 'order' );
			return true;
		}

		/**
		 * @TODO: Check here token is valid or not.
		 */

		$order_status = $this->model_extension_module_knawat_dropshipping->get_order_status_name( $order_status_id );
		if( $order_status != '' ){
			if($order_status == 'Canceled'){
				$order_status = 'Cancelled';
			}
			$order['order_status'] = $order_status;
		}
		
		$mp_order = $this->format_order( $order, $order_products, $is_update );
		if( empty( $mp_order ) ){
			$this->log->write("Failed to format Order as per MP API at send order to Knawat.com, format_order() failed. order_id:" . $order_id);
			return false;
		}

		require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatmpapi.php' );
		$knawatapi = new KnawatMPAPI( $this->registry );

		if( $is_update ){
			$failed = false;
			$failed_message = 'Something went wrong during order update to Knawat.com';
			$whilelisted_status = [ 'Pending', 'Processing', 'Cancelled' ];
			if (!in_array($order_status, $whilelisted_status)) {
                    return false;
                }
			try{
				$result = $knawatapi->put( 'orders/'.$knawat_order_id, $mp_order );
				if( !empty( $result ) && isset( $result->status ) && 'success' === $result->status ){
					$korder_id = $result->data->id;
					$this->model_extension_module_knawat_dropshipping->delete_knawat_meta( $order_id, 'knawat_sync_failed', 'order' );
					}
				else{
					$failed = true;
				}
			}catch( Exception $e ){
				$failed = true;
				$failed_message = $e->getMessage();
				$this->log->write( $failed_message . " => order_id:" . $order_id);
			}
			if( $failed ){
				$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'knawat_sync_failed', $failed_message, 'order' );
				return false;
			}
		}else{
			$failed = false;
			$failed_message = 'Something went wrong during order create on Knawat.com';
			$push_status = 'Processing';
			if ($push_status != $order_status) {
				return false;
			}
			try{
				$result = $knawatapi->post( 'orders', $mp_order );
				if( !empty( $result ) && isset( $result->status ) && 'success' === $result->status ){
					$korder_id = $result->data->id;
					$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'knawat_order','1', 'order' );
					$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'knawat_order_id', $korder_id, 'order' );

					$this->model_extension_module_knawat_dropshipping->delete_knawat_meta( $order_id, 'knawat_sync_failed', 'order' );
					}
				else{
					$failed = true;
				}
			}catch( Exception $e ){
				$failed = true;
				$failed_message = $e->getMessage();
				$this->log->write( $failed_message . " => order_id:" . $order_id);
			}
			if( $failed ){
				$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'knawat_sync_failed', $failed_message, 'order' );
				return false;
			}
		}
		return true;
	}

	/**
	 * Format Order for MP API
	 */
	private function format_order( $order, $order_products, $is_update = false ){
		$this->load->model("account/order");

		if( !$is_update ){
			// Load Product options
			$temp_products = array();
			foreach ($order_products as $op) {
				$order_options = $this->model_account_order->getOrderOptions( $order['order_id'], $op['order_product_id']);
				$op['order_options'] = $order_options;
				$temp_products[] = $op;
			}
			$order_products = $temp_products;
		}

		$korder_products = $this->get_knawat_order_product( $order_products, $is_update );

		$mp_order = array(
			'id'	=> (string)$order['order_id'],
			'status'=> strtolower( $order['order_status'] ),
			'items'	=> $korder_products,
		);

		$billing = array(
			'first_name'=> isset( $order['payment_firstname'] ) ? $order['payment_firstname'] : '',
			'last_name' => isset( $order['payment_lastname'] ) ? $order['payment_lastname'] : '',
			'company' 	=> isset( $order['payment_company'] ) ? $order['payment_company'] : '',
			'address_1' => isset( $order['payment_address_1'] ) ? $order['payment_address_1'] : '',
			'address_2' => isset( $order['payment_address_2'] ) ? $order['payment_address_2'] : '',
			'city' 		=> isset( $order['payment_city'] ) && !empty($order['payment_city']) ? $order['payment_city'] : $order['payment_zone'],
			'state' 	=> isset( $order['payment_zone'] ) && !empty($order['payment_zone']) ? $order['payment_zone'] : $order['payment_city'],
			'postcode' 	=> isset( $order['payment_postcode'] ) ? $order['payment_postcode'] : '',
			'country' 	=> isset( $order['payment_iso_code_2'] ) ? $order['payment_iso_code_2'] : $order['payment_country'],
			'email' 	=> isset( $order['email'] ) ? $order['email'] : '',
			'phone' 	=> isset( $order['telephone'] ) ? $order['telephone'] : ''
		);

		$shipping = array(
			'first_name'=> isset( $order['shipping_firstname'] ) ? $order['shipping_firstname'] : '',
			'last_name' => isset( $order['shipping_lastname'] ) ? $order['shipping_lastname'] : '',
			'company' 	=> isset( $order['shipping_company'] ) ? $order['shipping_company'] : '',
			'address_1' => isset( $order['shipping_address_1'] ) ? $order['shipping_address_1'] : '',
			'address_2' => isset( $order['shipping_address_2'] ) ? $order['shipping_address_2'] : '',
			'city' 		=> isset( $order['shipping_city'] ) && !empty($order['shipping_city']) ? $order['shipping_city'] : $order['shipping_zone'],
			'state' 	=> isset( $order['shipping_zone'] ) && !empty($order['shipping_zone']) ? $order['shipping_zone'] : $order['shipping_city'],
			'postcode' 	=> isset( $order['shipping_postcode'] ) ? $order['shipping_postcode'] : '',
			'country' 	=> isset( $order['shipping_iso_code_2'] ) ? $order['shipping_iso_code_2'] : $order['shipping_country'],
			'email' 	=> isset( $order['email'] ) ? $order['email'] : '',
			'phone' 	=> isset( $order['telephone'] ) ? $order['telephone'] : ''
		);

		$mp_order['billing']	= $billing;
		$mp_order['shipping']	= $shipping;

		// Setup invoice URL
		if ($this->request->server['HTTPS']) {
			$site_url = HTTPS_SERVER;
		} else {
			$site_url = HTTP_SERVER;
		}
		$order_string = base64_encode( $order['order_id'] .'___'. $order['customer_id'] .'___'. $order['date_added'] );
		$mp_order['invoice_url'] = $site_url . 'index.php?route=' .$this->route . '/invoice&order_id='.$order_string;

		// Setup Payment Method.
		$payment_code = isset( $order['payment_code'] ) ? $order['payment_code'] : '';
		$payment_method = isset( $order['payment_method'] ) ? $order['payment_method'] : '';
		if( $payment_code != ''){
			$payment_method = $payment_code . ' ('.$payment_method.')';
		}
		$mp_order['payment_method'] = $payment_method;

		return $mp_order;
	}

	/**
	 * format product into Knawat's order product
	 */
	private function get_knawat_order_product( $order_products, $is_update = false ){

		$knawat_products = array();
		foreach( $order_products as $product ){
			$is_knawat = $this->is_knawat_product( $product );
			if( $is_knawat ){
				if( $is_update ){
					$order_product_sku = $this->model_extension_module_knawat_dropshipping->get_knawat_meta( $product['order_product_id'], 'sku', 'order_product' );
					if( !$order_product_sku ){
						// Get SKU based on product ID.
						$order_product_sku = isset( $product['model'] ) ? $product['model'] : '';
					}
					$temp = array(
						'quantity'	=> $product['quantity'],
						'sku'		=> $order_product_sku
					);
					$knawat_products[] = $temp;
				}else{
					$product_options = $product['order_options'];
					foreach( $product_options as $option ){
						$product_option_sku = $this->model_extension_module_knawat_dropshipping->get_knawat_meta( $option['product_option_value_id'], 'sku', 'product_option_value' );
						if( $product_option_sku != '' ){
							$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $product['order_product_id'], 'sku', $product_option_sku, 'order_product' );
							break;
						}
					}
					if( !$product_option_sku ){
						// Get SKU based on product ID (if its not there for options )
						$product_option_sku = isset( $product['model'] ) ? $product['model'] : '';
					}
					$temp = array(
						'quantity'	=> $product['quantity'],
						'sku'		=> $product_option_sku
					);
					$knawat_products[] = $temp;
				}
			}
		}
		return $knawat_products;
	}

	/**
	 * Load Knawat dropshipping admin model
	 */
	private function load_admin_model(){
		// Load.
		$admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
		require_once $admin_dir . "model/extension/module/knawat_dropshipping.php";
		$this->model_extension_module_knawat_dropshipping = new ModelExtensionModuleKnawatDropshipping( $this->registry );
	}

	/**
	 * is it Knawat's Product?
	 */
	private function is_knawat_product( $order_product ){
		if( !empty( $order_product ) ){
			if( is_array( $order_product ) ){
				$product_id = $order_product['product_id'];
			}else{
				$product_id = $order_product;
			}
			if( !$product_id ){
				return false;
			}
			if( !isset( $this->model_extension_module_knawat_dropshipping ) ){
				$this->load_admin_model();
			}
			$is_knawat = $this->model_extension_module_knawat_dropshipping->get_knawat_meta( $product_id, 'is_knawat', 'product' );
			if( $is_knawat == '1' ){
				return true;
			}
		}
		return false;
	}

	/**
	 * order has Knawat's products?
	 */
	private function order_has_knawat_product( $order_products ){
		if( empty( $order_products ) ){
			return false;
		}
		foreach( $order_products as $product ){
			$is_knawat = $this->is_knawat_product( $product );
			if( $is_knawat ){
				return true;
			}
		}
		return false;
	}

	/**
	 * Action trigger after called single product page. 
	 */
	public function after_single_product(){
		if( isset( $this->request->get['product_id'] ) && !empty( $this->request->get['product_id'] ) ){
			if( $this->is_knawat_product( $this->request->get['product_id'] ) ){
				$action_url = $this->url->link( $this->route.'/sync_product_by_id/&product_id='.(int)$this->request->get['product_id'] );
				$this->curl_async( $action_url );
			}
		}
	}

	/**
	 * Action trigger Just before add to cart.
	 */
	public function before_add_to_cart(){
		if( isset( $this->request->post['product_id'] ) && !empty( $this->request->post['product_id'] ) ){
			if( $this->is_knawat_product( (int)$this->request->post['product_id'] ) ){
				$action_url = $this->url->link( $this->route.'/sync_product_by_id/&product_id='.(int)$this->request->post['product_id'] );
				$this->curl_async( $action_url );
			}
		}
	}

	private function curl_async( $url, $data = array() ){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url );
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000 );
		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * update product stock and price from Knawat.com
	 */
	public function sync_product_by_id(){
		/** 
		 * @TODO: Add a token security for this call
		 */
		if( isset( $this->request->get['product_id'] ) && !empty( $this->request->get['product_id'] ) ){
			$product_id = $this->request->get['product_id'];
			if( !empty( $product_id ) ){
				// import product
				require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatimporter.php' );
				$knawatimporter = new KnawatImporter( $this->registry, array( 'product_id'=> $product_id ), 'single' );
				$import_results = $knawatimporter->import();
			}
		}
	}

	/**
	 * Order Invoice
	 */
	public function invoice(){

		$this->load->language( $this->route );
		if( !isset( $this->request->get['order_id'] ) ){
			echo $this->language->get( 'error_order_id' );
			exit;
		}

		$temporder = $this->request->get['order_id'];
		$temporder = base64_decode( $temporder );
		$token_array = explode( '___', $temporder );

		$order_id = $token_array[0];
		$customer_id = $token_array[1];
		$order_add = $token_array[2];

		if( empty( $order_id ) || (empty( $customer_id ) && $customer_id != 0) || empty( $order_add ) ){
			echo $this->language->get( 'error_wrong' );
			exit;
		}

		$data['title'] = $this->language->get('text_invoice');
		if ($this->request->server['HTTPS']) {
			$data['base'] = HTTPS_SERVER;
		} else {
			$data['base'] = HTTP_SERVER;
		}

		$data['direction'] = $this->language->get('direction');
		$data['lang'] = $this->language->get('code');

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->model('checkout/order');
		$this->load->model('setting/setting');

		$data['orders'] = array();

		$order_info = $this->model_checkout_order->getOrder($order_id);

		// Validate Order.
		$order_added = $order_info['date_added'];
		$order_customer_id = $order_info['customer_id'];
		if( empty( $order_info ) || $order_added != $order_add || $order_customer_id != $customer_id ){
			echo $this->language->get( 'error_wrong' );
			exit;
		}

		// Load invoice in Order language.
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		$order_lang = $order_info['language_code'];
		$sort_lang_code = explode( '-', $order_lang );
		$sort_lang_code = $sort_lang_code[0];
		$current_lang = $this->language->get('code');
		if( !empty( $current_lang ) && !empty( $order_lang ) && isset( $languages[$order_lang]) && ( $order_lang != $current_lang && $sort_lang_code != $current_lang ) ){
			$this->session->data['language'] = $order_lang;
			$current_url = $data['base'].'index.php?'. urldecode( http_build_query( $this->request->get) );
			header( "Location: ". $current_url );
		}

		if ($order_info) {
			$store_info = $this->model_setting_setting->getSetting('config', $order_info['store_id']);

			if ($store_info) {
				$store_address = $store_info['config_address'];
				$store_email = $store_info['config_email'];
				$store_telephone = $store_info['config_telephone'];
				$store_fax = $store_info['config_fax'];
			} else {
				$store_address = $this->config->get('config_address');
				$store_email = $this->config->get('config_email');
				$store_telephone = $this->config->get('config_telephone');
				$store_fax = $this->config->get('config_fax');
			}

			if ($order_info['invoice_no']) {
				$invoice_no = $order_info['invoice_prefix'] . $order_info['invoice_no'];
			} else {
				$invoice_no = '';
			}

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $order_info['payment_country']
			);

			$payment_address = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $order_info['shipping_country']
			);

			$shipping_address = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$this->load->model('tool/upload');

			$product_data = array();

			$products = $this->model_checkout_order->getOrderProducts($order_id);

			$deduction = 0;
			$tax_deduction = 0;
			foreach ($products as $product) {
				$option_data = array();

				$is_knawat_product = $this->is_knawat_product( $product );
				if( !$is_knawat_product ){
					$deduction += $product['total'];
					$tax_deduction += $product['tax'];
					continue;
				}

				$options = $this->model_checkout_order->getOrderOptions($order_id, $product['order_product_id']);

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
						'value' => $value
					);
				}

				$product_data[] = array(
					'name'     => $product['name'],
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			$voucher_data = array();

			$vouchers = $this->model_checkout_order->getOrderVouchers($order_id);

			foreach ($vouchers as $voucher) {
				$voucher_data[] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			$total_data = array();

			$totals = $this->model_checkout_order->getOrderTotals($order_id);

			if( $deduction > 0 ){
				$tax_total = array();
				foreach ($totals as $key => $total) {
					if( $total['code'] == 'sub_total' ){
						$totals[$key]['value'] -= $deduction;
					}
					if( $total['code'] == 'total' ){
						$totals[$key]['value'] -= $deduction;
						$totals[$key]['value'] -= $tax_deduction;
					}
					if( $total['code'] == 'tax' && $tax_deduction > 0 ){
						if( isset( $tax_total['title'] ) ){
							$tax_total['title'] .= ', ' . $total['title'];
						}else{
							$tax_total['title'] = $total['title'];
						}

						if( isset( $tax_total['value'] ) ){
							$tax_total['value'] += $total['value'];
						}else{
							$tax_total['value'] = $total['value'];
						}
						unset( $totals[$key]);
					}
				}
				if( $tax_deduction > 0 && !empty( $tax_total ) ){
					$tax_total['title'] = $this->language->get('text_taxes').'('.$tax_total['title'].')';
					$tax_total['value'] -= $tax_deduction;
					$total = array_pop($totals);
					$totals[] = $tax_total;
					$totals[] = $total;
				}
			}

			foreach ($totals as $key => $total) {
				$total_data[] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			$data['orders'][] = array(
				'order_id'	       => $order_id,
				'invoice_no'       => $invoice_no,
				'date_added'       => date($this->language->get('date_format_short'), strtotime($order_info['date_added'])),
				'store_name'       => $order_info['store_name'],
				'store_url'        => rtrim($order_info['store_url'], '/'),
				'store_address'    => nl2br($store_address),
				'store_email'      => $store_email,
				'store_telephone'  => $store_telephone,
				'store_fax'        => $store_fax,
				'email'            => $order_info['email'],
				'telephone'        => $order_info['telephone'],
				'shipping_address' => $shipping_address,
				'shipping_method'  => $order_info['shipping_method'],
				'payment_address'  => $payment_address,
				'payment_method'   => $order_info['payment_method'],
				'product'          => $product_data,
				'voucher'          => $voucher_data,
				'total'            => $total_data,
				'comment'          => nl2br($order_info['comment'])
			);
		}

		$this->response->setOutput($this->load->view('knawat_dropshipping/order_invoice', $data));
	}

	/**
	 * Sync Orders
	 */
	public function sync_failed_order(){
		header('Content-Type: application/json');

		if( !session_id() ){
			session_start();
		}
		if (! isset($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = base64_encode(openssl_random_pseudo_bytes(32));
		}
		$csrf_token = $_SESSION['csrf_token'];
		unset( $_SESSION['csrf_token'] );

        if( !function_exists('apache_request_headers') ) {
            $headers = apache_request_headers();
            if( !isset( $headers['CsrfToken'] ) || $headers['CsrfToken'] != $csrf_token ){
                exit( json_encode( array( 'error' => 'invalid_CSRF_token' ) ) );
            }
        }
		/* if (isset($_SERVER['HTTP_REFERER'])) {
			$server_name = $_SERVER['SERVER_NAME'];
			if ( false !== stripos( $_SERVER['HTTP_REFERER'], $server_name ) ) {
				exit( json_encode( array( 'error' => 'Invalid Reffer' ) ) );
			}
		} else {
			exit(json_encode( array( 'error' => 'NO Reffer' ) ) );
		} */

		$this->load_admin_model();
		$orders = $this->model_extension_module_knawat_dropshipping->get_sync_failed_orders();
		if( !empty( $orders ) ){
			$this->load->model("checkout/order");
			try{
				$failed = false;
				foreach( $orders as $order ){
					$order = $this->model_checkout_order->getOrder($order['resource_id'] );
					$orderdata = array( $order['order_id'], $order['order_status_id'] );
					$result = $this->order_changed( '', $orderdata );
					if( !$result ){
						$failed = true;
					}
				}
				if( $failed ){
					exit(json_encode( array( 'error' => '1' ) ) );
				}
			}catch( Exception $e ){
				exit(json_encode( array( 'error' => $e->getMessage() ) ) );
			}

		}
		exit( json_encode( array( "status" => "success" ) ) );
	}
}
