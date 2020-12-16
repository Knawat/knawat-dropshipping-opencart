<?php
class ControllerExtensionModuleKnawatDropshipping extends Controller { 
	
	private $error = array();
	public $params = array();
	private $route = 'extension/module/knawat_dropshipping';

	public function __construct($registry) {
		parent::__construct($registry);
	}

	public function install(){
		if(version_compare(VERSION, '3.0.0','<') ) {
			$this->load->model('extension/event' );
			$this->load->model( $this->route );
			$this->model_extension_module_knawat_dropshipping->install();

			/**
		 	* Add Events
		 	*/
			$this->model_extension_event->addEvent(
				'knawat_dropshipping_add_to_cart',
				'catalog/controller/checkout/cart/add/before',
				'extension/module/knawat_dropshipping/before_add_to_cart'
			);

			$this->model_extension_event->addEvent(
				'knawat_dropshipping_single_product',
				'catalog/controller/product/product/after',
				'extension/module/knawat_dropshipping/after_single_product'
			);

			$this->model_extension_event->addEvent(
            	'knawat_dropshipping_order_changed',
            	'catalog/model/checkout/order/addOrderHistory/after',
            	'extension/module/knawat_dropshipping/order_changed'
        	);
		}else{
			$this->load->model('setting/event' );
			$this->load->model( $this->route );
			$this->model_extension_module_knawat_dropshipping->install();

			/**
		 	* Add Events
		 	*/
			$this->model_setting_event->addEvent(
				'knawat_dropshipping_add_to_cart',
				'catalog/controller/checkout/cart/add/before',
				'extension/module/knawat_dropshipping/before_add_to_cart'
			);

			$this->model_setting_event->addEvent(
				'knawat_dropshipping_single_product',
				'catalog/controller/product/product/after',
				'extension/module/knawat_dropshipping/after_single_product'
			);

			$this->model_setting_event->addEvent(
            	'knawat_dropshipping_order_changed',
            	'catalog/model/checkout/order/addOrderHistory/after',
            	'extension/module/knawat_dropshipping/order_changed'
        	);
		}
	}

	public function uninstall(){
		if(version_compare(VERSION, '3.0.0','<') ) {
			$this->load->model('extension/event' );
			$this->load->model('setting/setting');
			$events = $this->model_extension_event->getEvents();
			$data = array(
				'knawat_dropshipping_add_to_cart',
				'knawat_dropshipping_single_product',
				'knawat_dropshipping_order_changed'
			);

			// Delete events
			foreach ($events as $event) {
				if ( in_array($event['code'], $data ) ) {
					$this->model_extension_event->deleteEvent( $event['event_id'] );
				}
			}
			// Delete settings
			$this->model_setting_setting->deleteSetting('module_knawat_dropshipping');
		}else{
			$this->load->model('setting/event' );
			$this->load->model('setting/setting');
			$events = $this->model_setting_event->getEvents();
			$data = array(
				'knawat_dropshipping_add_to_cart',
				'knawat_dropshipping_single_product',
				'knawat_dropshipping_order_changed'
			);

			// Delete events
			foreach ($events as $event) {
				if ( in_array($event['code'], $data ) ) {
					$this->model_setting_event->deleteEvent( $event['event_id'] );
				}
			}

			// Delete settings
			$this->model_setting_setting->deleteSetting('module_knawat_dropshipping');
		}
	}

	// Enabled & Disable ignore for now.
	/* public function enabled() {
		$this->load->model('setting/event');
		$events = $this->model_extension_event->getEvents();

		$data = array(
			'knawat_dropshipping_add_to_cart',
			'knawat_dropshipping_single_product',
			'knawat_dropshipping_order_changed'
		);

		foreach ( $events as $event ) {
			if ( in_array($event['code'], $data) ) {
				$this->model_extension_event->enableEvent( $event['event_id'] );
			}
		}
	}

	public function disabled() {
		$this->load->model('setting/event');
		$events = $this->model_extension_event->getEvents();

		$data = array(
			'knawat_dropshipping_add_to_cart',
			'knawat_dropshipping_single_product',
			'knawat_dropshipping_order_changed'
		);

		foreach ( $events as $event ) {
			if ( in_array($event['code'], $data) ) {
				$this->model_extension_event->disableEvent( $event['event_id'] );
			}
		}
	} */

	public function index() {
		if(version_compare(VERSION, '3.0.0','<') ) {
			$tokenField = 'token';
		}else{
			$tokenField = 'user_token';
		}
		if( !session_id() ){
			session_start();
		}

		$this->load->language( $this->route );
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model( $this->route );

		// Validate and Submit Posts 
		if ( ($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate() ) {
			$this->model_setting_setting->editSetting('module_knawat_dropshipping', $this->request->post );

			require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatmpapi.php' );
			$knawatapi = new KnawatMPAPI( $this->registry );

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link( $this->route, $tokenField.'=' . $this->session->data[$tokenField] . '&type=module', true));
		}

		// Load Language strings
		$data = array(
			'entry_consumer_key' 			=> $this->language->get('entry_consumer_key'),
			'consumer_key_placeholder' 		=> $this->language->get('consumer_key_placeholder'),
			'entry_consumer_secret' 		=> $this->language->get('entry_consumer_secret'),
			'consumer_secret_placeholder' 	=> $this->language->get('consumer_secret_placeholder'),
		);

		// Check and set warning.
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		// Check for order sync Warning.
		$sync_failed_orders = $this->model_extension_module_knawat_dropshipping->get_sync_failed_orders();
		if( !empty( $sync_failed_orders ) ){
			$data['ordersync_warning'] = 1;
		}
		//check for product cron time 
		$oldTime = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8162', 'time', 'cron_time' );
		$test = time();
		$difference = $test - (int)$oldTime;
		if($difference > 10800){
			$data['cronsync_warning'] = 1;	
		}
		// Status Error
		if (isset($this->error['error_status'])) {
            $data['error_status'] = $this->error['error_status'];
        } else {
            $data['error_status'] = '';
		}

		// Set Success.
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Token Error.
		if (isset($this->session->data['token_error'])) {
			$data['token_error'] = $this->session->data['token_error'];

			unset($this->session->data['token_error']);
		} else {
			$data['token_error'] = '';
		}

		$data['token_valid'] = false;
		if( $this->is_valid_token() ){
			$data['token_valid'] = true;
		}

		// Setup Breadcrumbs Data.

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $tokenField.'=' . $this->session->data[$tokenField], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $tokenField.'=' . $this->session->data[$tokenField] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link( $this->route, $tokenField.'=' . $this->session->data[$tokenField], true)
		);

		$data['action'] = $this->url->link( $this->route, $tokenField.'=' . $this->session->data[$tokenField], true);

		$data['cancel'] = $this->url->link('marketplace/extension', $tokenField.'=' . $this->session->data[$tokenField] . '&type=module', true);

		// Set module status
		if (isset($this->request->post['module_knawat_dropshipping_status'])) {
			$data['module_knawat_dropshipping_status'] = $this->request->post['module_knawat_dropshipping_status'];
		} else {
			$data['module_knawat_dropshipping_status'] = $this->config->get('module_knawat_dropshipping_status');
		}

		// Set Consumer Key
		if (isset($this->request->post['module_knawat_dropshipping_consumer_key'])) {
			$data['module_knawat_dropshipping_consumer_key'] = $this->request->post['module_knawat_dropshipping_consumer_key'];
		} else {
			$data['module_knawat_dropshipping_consumer_key'] = $this->config->get('module_knawat_dropshipping_consumer_key');
		}

		// Set Consumer Secret
		if (isset($this->request->post['module_knawat_dropshipping_consumer_secret'])) {
			$data['module_knawat_dropshipping_consumer_secret'] = $this->request->post['module_knawat_dropshipping_consumer_secret'];
		} else {
			$data['module_knawat_dropshipping_consumer_secret'] = $this->config->get('module_knawat_dropshipping_consumer_secret');
		}

		// Setup Stores.
		$this->load->model('setting/store');
		$data['stores'] = array();
		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);

		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		if (isset($this->request->post['module_knawat_dropshipping_store'])) {
			$data['module_knawat_dropshipping_store'] = $this->request->post['module_knawat_dropshipping_store'];
		} else {
			$kstores = $this->config->get('module_knawat_dropshipping_store');
			if( !empty( $kstores ) ){
				$data['module_knawat_dropshipping_store'] = $kstores;
			}else{
				$data['module_knawat_dropshipping_store'] = array(0);
			}
		}

		$data['knawat_ajax_url'] = $this->url->link( $this->route .'/ajax_import', $tokenField.'=' . $this->session->data[$tokenField], true);

		$_SESSION['csrf_token'] = base64_encode(openssl_random_pseudo_bytes(32));
		$data['csrf_token'] = $_SESSION['csrf_token'];
		$data['knawat_ordersync_url'] = HTTP_CATALOG . 'index.php?route='. $this->route .'/sync_failed_order';

		if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] ) {
			$data['knawat_ajax_loader']  = HTTPS_SERVER .'view/image/knawat_ajax_loader.gif';
		}else{
			$data['knawat_ajax_loader']  = HTTP_SERVER .'view/image/knawat_ajax_loader.gif';
		}
		/*cron url*/
		$randomNumber = $this->generateRandomString();
		$data['cron_url'] = dirname(HTTP_SERVER)."/?route=extension/module/knawat_cron/importProducts&access_token=$randomNumber";
		/*cron url*/
		/*added for lower version*/
		if(version_compare(VERSION, '3.0.0','<') ) {
			$data['text_edit'] = $this->language->get('text_edit');
			$data['text_enabled'] = $this->language->get('text_enabled');
			$data['text_disabled'] = $this->language->get('text_disabled');
			$data['entry_status'] = $this->language->get('entry_status');
			$data['text_notconnected'] = $this->language->get('text_notconnected');
			$data['text_notconnected_desc'] = $this->language->get('text_notconnected_desc');
			$data['entry_connection'] = $this->language->get('entry_connection');
			$data['entry_store'] = $this->language->get('entry_store');
			$data['success_ajaximport'] = $this->language->get('success_ajaximport');
			$data['text_import_stats'] = $this->language->get('text_import_stats');
			$data['text_imported'] = $this->language->get('text_imported');
			$data['text_products'] = $this->language->get('text_products');
			$data['text_updated'] = $this->language->get('text_updated');
			$data['text_failed'] = $this->language->get('text_failed');
			$data['text_syncing'] = $this->language->get('text_syncing');
			$data['warning_ajaximport'] = $this->language->get('warning_ajaximport');
			$data['success_ordersync'] = $this->language->get('success_ordersync');
			$data['error_wrong'] = $this->language->get('error_wrong');
			$data['error_ajaximport'] = $this->language->get('error_ajaximport');
			$data['text_connected'] = $this->language->get('text_connected');
			$data['text_connected_desc'] = $this->language->get('text_connected_desc');
			$data['text_run_import'] = $this->language->get('text_run_import');
			$data['text_import_products'] = $this->language->get('text_import_products');
			$data['heading_title'] = $this->language->get('heading_title');
			$data['text_import_inprogress'] = $this->language->get('text_import_inprogress');
			$data['cron_url_info'] = $this->language->get('cron_url_info');
			$data['text_import_note'] = $this->language->get('text_import_note');
			$data['text_cron_sync_error'] = $this->language->get('text_cron_sync_error');
			$data['trigger'] = $this->language->get('trigger');
			$data['cronjob'] = $this->language->get('cronjob');
			$data['text_sync'] = $this->language->get('text_sync');
			$data['text_sync_product1'] = $this->language->get('text_sync_product1');
			$data['text_sync_product2'] = $this->language->get('text_sync_product2');
			$data['text_sync_product3'] = $this->language->get('text_sync_product3');
			$data['text_reset_sync'] = $this->language->get('text_reset_sync');
		}
		/*added for language*/
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		/*added call for function reset_sync()*/
		if (isset($this->request->get['reset-sync']) && $this->request->get['reset-sync'] == true){
			$this->reset_sync();
		}

		/*added last importing time*/
		$knawat_time = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8159', 'time','knawat_last_imported');
		$data['knawat_last_imported_time'] = !empty($knawat_time)? $knawat_time : 0;
		if ($data['token_valid'] === true){
			$data['products_synced'] = $this->get_products_count( $data['knawat_last_imported_time'] );
			$data['products_count'] = $this->get_products_count( 1483218000000 );
		}

		$this->response->setOutput($this->load->view( $this->route, $data) );
	}

	protected function validate() {
		if (!$this->user->hasPermission( 'modify', $this->route ) ) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }

		if(!isset( $this->request->post['module_knawat_dropshipping_status'])){
			$this->error['error_status'] = $this->language->get('error_status');
			return false;
		}
		return true;
	}

	public function reset_sync(){
		$reset_time = 1483218000000;
		$this->model_extension_module_knawat_dropshipping->update_knawat_meta('8159', 'time', $reset_time , 'knawat_last_imported' );
	}

	public function get_products_count( $timestamp ){
		require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatmpapi.php' );
        $knawatapi = new KnawatMPAPI( $this->registry );
		$data =  $knawatapi->get('catalog/products?limit=10&page=1&hideOutOfStock=1&lastupdate=' . $timestamp );
		$product_count = $data->total;
		return $product_count;
	}
	
	public function ajax_import(){
		require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatimporter.php');
		set_time_limit(0);
		$item = array();
		if( isset( $this->request->post['process_data'] ) && !empty( $this->request->post['process_data'] ) ){
			$process_data = $this->request->post['process_data'];
			$item = array();
			if( isset( $process_data['limit'] ) ){
				$item['limit'] = (int)$process_data['limit'];
			}
			if( isset( $process_data['page'] ) ){
				$item['page'] = (int)$process_data['page'];
			}
			if( isset( $process_data['force_update'] ) ){
				$item['force_update'] = (boolean)$process_data['force_update'];
			}
			if( isset( $process_data['prevent_timeouts'] ) ){
				$item['prevent_timeouts'] = (boolean)$process_data['prevent_timeouts'];
			}
			if( isset( $process_data['is_complete'] ) ){
				$item['is_complete'] = (boolean)$process_data['is_complete'];
			}
			if( isset( $process_data['imported'] ) ){
				$item['imported'] = (int)$process_data['imported'];
			}
			if( isset( $process_data['failed'] ) ){
				$item['failed'] = (int)$process_data['failed'];
			}
			if( isset( $process_data['updated'] ) ){
				$item['updated'] = (int)$process_data['updated'];
			}
			if( isset( $process_data['batch_done'] ) ){
				$item['batch_done'] = (boolean)$process_data['batch_done'];
			}
		}

		$knawatimporter = new KnawatImporter( $this->registry, $item );

		$import_data = $knawatimporter->import(true);
		
		$params = $knawatimporter->get_import_params();

		$item = $params;

		if( true == $params['batch_done'] ){
			$item['page']  = $params['page'] + 1;
		}else{
			$item['page']  = $params['page'];
		}
		/* add last updated time*/
		$start_time = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8158', 'time','start_time');
		if($item['is_complete'] === true){
			if( empty($start_time) ){
				$start_time = time();
			}
			$this->model_extension_module_knawat_dropshipping->update_knawat_meta('8159', 'time',$start_time, 'knawat_last_imported' );
		}
		/* add last updated time*/
		$item['imported'] += count( $import_data['imported'] );
		$item['failed']   += count( $import_data['failed'] );
		$item['updated']  += count( $import_data['updated'] );
		echo $import_data = json_encode( $item );
		exit();
	}

	/**
	 * Check if site has valid Access token
	 */
	public function is_valid_token(){
		$is_valid = $this->config->get('module_knawat_dropshipping_valid_token');
		if( $is_valid == '1' ){
			return true;
		}
		return false;
	}

	/**
	 * Generate random string for token
	 */
	public function generateRandomString($length = 10){
       		$accessToken = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8161', 'token','access_token');
       		if(empty($accessToken)){
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
           	$randomString = '';
            for ($i = 0; $i < $length; $i++) {
               $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
          $this->model_extension_module_knawat_dropshipping->update_knawat_meta('8161', 'token',$randomString, 'access_token' );
          return $randomString;
     	 }
      	return $accessToken;
     }

   	/**
	 * Pull orders from Knawat
	 */
   	public function pullOrder(){
		$pull_results = $this->knawatPullOrders();
		if (!empty($pull_results)) {
			while (isset($pull_results['is_complete']) && $pull_results['is_complete'] != true) {
				$pull_results = $this->knawatPullOrders($pull_results);
			}
		}
	}

	public function knawatPullOrders($item = [])
	{
		$this->loadAdminModel();
		$default_args = [
            'limit'             => 2, // Limit for Fetch Orders
            'page'              => 1,  // Page Number
        ];
        $this->params = $default_args;
        if (empty($item)) {
        	$item = $this->params;
        }
        require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatmpapi.php' );
        $knawatapi = new KnawatMPAPI( $this->registry );
        $knawat_orders =  $knawatapi->get('orders/?page='.$item['page'].'&limit='.$item['limit']);

        if (empty($knawat_orders)) {
        	return false;
        }
        foreach ($knawat_orders as $knawat_order) {
        	if ($knawat_order->id) {
        		$knawatId = $knawat_order->id;
        		$order_id = $this->model_extension_module_knawat_dropshipping->get_knawat_meta_from_value('order', $knawatId);
        		if(!empty($order_id)){
        			$knawat_status = isset($knawat_order->knawat_order_status) ? $knawat_order->knawat_order_status : '';
        			$shipment_provider_name = isset($knawat_order->shipment_provider_name) ? $knawat_order->shipment_provider_name : '';
        			$shipment_tracking_number = isset($knawat_order->shipment_tracking_number) ? $knawat_order->shipment_tracking_number : '';
        			if(!empty($knawat_status)){
        				$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'knawat_order_status', $knawat_status, 'order' );
        			}
        			if(!empty($shipment_provider_name)){
        				$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'shipment_provider_name', $shipment_provider_name, 'order' );
        			}
        			if(!empty($shipment_tracking_number)){
        				$this->model_extension_module_knawat_dropshipping->update_knawat_meta( $order_id, 'shipment_tracking_number', $shipment_tracking_number, 'order' );
        			}
        		}
        	}
        }

        $item['orders_total'] = count($knawat_orders);
        if ($item['orders_total'] < $item['limit']) {
        	$item['is_complete'] = true;
        	return $item;
        } else {
        	$item['page'] = $item['page'] + 1;
        	$this->params['page'] = $item['page'];
        	$item['is_complete'] = false;
        	return $item;
        }
        return false;
    }

    /**
	 * Load knawat dropshipping model
	 */
    public function loadAdminModel(){
    	if( $this->is_admin ){
    		$this->load->model( $this->route );
    	}else{
    		$admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
    		require_once $admin_dir . "model/extension/module/knawat_dropshipping.php";
    		$this->model_extension_module_knawat_dropshipping = new ModelExtensionModuleKnawatDropshipping( $this->registry );
    	}
    }
}
?>