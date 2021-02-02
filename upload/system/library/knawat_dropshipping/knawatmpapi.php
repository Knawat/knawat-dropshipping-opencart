<?php
/**
 * Knawat MP API Wrapper class.
 *
 * @link       http://knawat.com/
 * @since      1.0.0
 * @category   Class
 * @author 	   Dharmesh Patel
 */

 class KnawatMPAPI{

    private $registry;
    /**
     * Contain Knawta API URL
     * @access private
     */
    private $api_url = 'https://mp.knawat.io/api/';

    /**
     * Contain Consumer key
     * @access private
     */
    private $consumer_key = '';

    /**
     * Contain Consumer Secret
     * @access private
     */
    private $consumer_secret = '';

    /**
     * Contain Consumer Channel
     * @access private
     */
    private $channel = '';

    /**
     * Contain Access Token
     * @access private
     */
    private $access_token = '';

    /**
     * Contain cURL instance
     */
    private $ch;
    private $is_admin = false;

    /**
     * Knawat MP API Constructor
     */
    public function __construct( $registry ){
        
        $this->registry = $registry;

        if( false !== stripos( DIR_APPLICATION, 'admin' ) ){
            $this->is_admin = true;
        }

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_knawat_dropshipping');

        if( !isset( $this->model_extension_module_knawat_dropshipping ) || empty( $this->model_extension_module_knawat_dropshipping ) ){
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/extension/module/knawat_dropshipping.php";
            $this->model_extension_module_knawat_dropshipping = new ModelExtensionModuleKnawatDropshipping( $this->registry );
        }

        if ($settings) {
            $this->init(
                $settings['module_knawat_dropshipping_consumer_key'],
                $settings['module_knawat_dropshipping_consumer_secret']
            );
        }
    }

    /**
     * Knawat MP API init
     * 
     * @param string $consumer_key Knawat Consumer Key
     * @param string $consumer_secret Knawat Consumer Secret
     * 
     */
    public function init( $consumer_key, $consumer_secret ){

        $this->consumer_key     = $consumer_key;
        $this->consumer_secret  = $consumer_secret;

        if( !empty( $this->consumer_key ) && !empty( $this->consumer_key ) ){
            $token = $this->setToken();
        }else{
            $this->log->write( $this->language->get('warning_apikey_needed') );
        }
    }

    public function __get($name) {
        return $this->registry->get($name);
    }

    /**
     * Set Access Token
     * 
     * @return void
     */
    public function setToken(){

        $settings = $this->model_setting_setting->getSetting('module_knawat_dropshipping');

        if ($settings) {
            $valid_token = isset( $settings['module_knawat_dropshipping_valid_token'] ) ? $settings['module_knawat_dropshipping_valid_token'] : '0';
            $access_token = isset( $settings['module_knawat_dropshipping_access_token'] ) ? $settings['module_knawat_dropshipping_access_token'] : '';
            $token_expiry = isset( $settings['module_knawat_dropshipping_token_expiry'] ) ? $settings['module_knawat_dropshipping_token_expiry'] : time();

            if( '1' === $valid_token && $access_token !='' && $token_expiry > time() ){
                $this->access_token = $access_token;
            }else{
                try{
                    $access_token = $this->getToken();
                    if( !empty ( $access_token ) ){
                        $this->access_token = $access_token;
                        $settings['module_knawat_dropshipping_valid_token']  = '1';
                        $settings['module_knawat_dropshipping_access_token'] = $access_token;
                        $settings['module_knawat_dropshipping_token_expiry'] = strtotime('+24 hours');

                        // Update latest Settings.
                        $this->model_extension_module_knawat_dropshipping->edit_setting( 'module_knawat_dropshipping', $settings );
                    }else{
                        // @TODO: Failed to get access token handle error here.
                        $this->session->data['token_error'] = 'Something went wrong during get token from MP API';
                        $this->log->write( 'Something went wrong during get token from MP API' );
                    }
                }catch( Exception $e ){
                    $error_message = $e->getMessage();
                    $this->log->write( $error_message );
                    $this->session->data['token_error'] = $error_message;
                }
            }
        
        }
    }

    /**
     * Get Token from knawat for API operations.
     * 
     * @return string token
     * @throws Exception
     */
    public function getToken(){
        
        if( empty( $this->consumer_key ) || empty( $this->consumer_secret ) ){
            $keyerror = $this->language->get('warning_apikey_needed');
            $this->log->write( $keyerror );
            throw new Exception( $keyerror );
        }

        $data = array(
            'consumerKey'   => $this->consumer_key,
            'consumerSecret'=> $this->consumer_secret,
        );
        $data = json_encode( $data );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $this->api_url . 'token' );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
        if (stripos( $this->api_url, 'https') !== false) {
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        }
        // curl_setopt( $ch, CURLOPT_PROXY, "192.168.10.5:8080" ); // for local USE only remove it please
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));

        // Execute the request and decode the response to JSON
        $resource_data = json_decode( curl_exec( $ch ) );
        $response_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        // Close cURL Connection.
        curl_close( $ch );

        if( $response_code > 299 ) {
            if( isset( $resource_data->message) ) {
                throw new Exception( $resource_data->message, $response_code );
            } else {
                throw new Exception( (string) json_encode($resource_data), $response_code );
            }
        }

        if( isset( $resource_data->channel ) ){
            $access_token = $resource_data->channel->token;
            return $access_token;
        }

        return false;
    }

    /**
     * Get Knawat MP Access Token
     * 
     * @return string $access_token
     */
    public function getAccessToken(){
        return $this->access_token;
    }
    /**
     * Get Knawat MP API URL
     * 
     * @return string $api_url
     */
    public function getApiUrl(){
        return $this->api_url;
    }

    /**
    * get function.
    *
    * Performs an API GET request
    *
    * @access public
    * @return object
    */    
    public function get( $path, $return_array = false ) {
    	// Instantiate a new instance
        $this->remoteInstance();

        // Set the request params
        $this->setUrl( $path );

        // Start the request and return the response
        return $this->execute('GET', $return_array );
    }
 

    /**
    * post function.
    *
    * Performs an API POST request
    *
    * @access public
    * @return object
    */    
    public function post( $path, $data = array(), $return_array = false ) {
    	// Instantiate a new instance
        $this->remoteInstance();

        // Set the request params
        $this->setUrl( $path );

        // Start the request and return the response
        return $this->execute('POST', $data, $return_array );
    }	


    /**
    * put function.
    *
    * Performs an API PUT request
    *
    * @access public
    * @return object
    */    
    public function put( $path, $data = array(), $return_array = false ) {
    	// Instantiate a new instance
        $this->remoteInstance();

        // Set the request params
        $this->setUrl( $path );

        // Start the request and return the response
        return $this->execute('PUT', $data, $return_array );
    }


    /**
    * execute function.
    *
    * Executes the API request
    *
    * @access public
    * @param  string $request_type
    * @param  array  $data
    * @param  boolean $return_array - if we want to retrieve an array with additional information 
    * @return object
    * @throws Exception
    */   	
    public function execute( $request_type, $data = array(), $return_array = false ) {
        // Set the HTTP request type
        curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $request_type );

        // Prepare to post the data
        if( is_array( $data ) && !empty( $data ) ) {
            $data = json_encode( $data );
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $data );
        }

        // Execute the request and decode the response to JSON
        $resource_data = json_decode( curl_exec( $this->ch ) );
        //print_r(  $resource_data );

        // Retrieve the HTTP response code
        $response_code = (int) curl_getinfo( $this->ch, CURLINFO_HTTP_CODE );
        if( $return_array ) {
            $response_data = json_encode( $resource_data );
            $curl_request_url = curl_getinfo( $this->ch, CURLINFO_EFFECTIVE_URL);
            $curl_info = curl_getinfo( $this->ch );
        }
        // Close cURL Connection.
        curl_close( $this->ch );

        // If the HTTP response code is higher than 299, the request failed.
        // Throw an exception to handle the error
        if( $response_code > 299 ) {
            if( isset( $resource_data->message) ) {
                throw new Exception( $resource_data->message, $response_code ); 
            } else {
                throw new Exception( (string) json_encode($resource_data), $response_code );
            }
        }

        // Everything went well, return the resource data object.
        if( $return_array ) {
            return array( 
                $resource_data, 
                $curl_request_url,
                $request_form_data,
                $response_data,
                $curl_info
            ); 
        }

        return $resource_data;
    }


    /**
    * setUrl function.
    *
    * Takes an API request string and appends it to the API url
    *
    * @access public
    * @return void
    */   
    public function setUrl( $params ) {
        curl_setopt( $this->ch, CURLOPT_URL, $this->api_url . trim( $params, '/' ) );
    }
    
 
    /**
    * remoteInstance function.
    *
    * Create a cURL instance if none exists already
    *
    * @access public
    * @return cURL object
    */
    protected function remoteInstance( $post_id = NULL ) {
        if( $this->ch === NULL ) {
            if( empty( $this->access_token ) ){
                $this->setToken();
            }

            $this->ch = curl_init();
            curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $this->ch, CURLOPT_TIMEOUT, 30 );
            curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 10 );
            curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 30 );
            if (stripos( $this->api_url, 'https') !== false) {
                curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, 0 );
                curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, 0 );
            }
            // curl_setopt( $this->ch, CURLOPT_PROXY, "192.168.10.5:8080" ); // for local USE only remove it please
            curl_setopt( $this->ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $this->access_token,
                'Content-Type: application/json'
            ));
        }
        return $this->ch;
    }

 }
