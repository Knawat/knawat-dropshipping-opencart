<?php
/**
 * Knawat Dropshipping Importer class.
 *
 * @link       http://knawat.com/
 * @since      1.0.0
 * @category   Class
 * @author     Dharmesh Patel
 */

class KnawatImporter{

    private $registry;
    private $codename = 'knawat_dropshipping';
    private $route = 'extension/module/knawat_dropshipping';

    private $languages = array();
    private $default_language_id = 1;
    private $default_language = 'en';
    private $all_stores;
    private $is_admin = false;

    /**
     * Import Type
     *
     * @var string
     */
    protected $import_type = 'full';

    /**
     * API Wrapper Class instance
     *
     * @var string
     */
    protected $mp_api;

    /**
     * Parameters which contains information regarding import.
     *
     * @var array
     */
    public $params;

    /**
     * Knawat MP API Constructor
     */
    public function __construct( $registry, $import_args = array(), $import_type = 'full' ){
        
        $this->registry = $registry;
        $this->load->language($this->route);
        $this->load->model('localisation/language');

        if( false !== stripos( DIR_APPLICATION, 'admin' ) ){
            $this->is_admin = true;
        }

        $this->all_stores = $this->config->get('module_knawat_dropshipping_store');

        $this->languages = $this->model_localisation_language->getLanguages();
        $config_language = $this->config->get('config_admin_language');
        $language = explode( '-', $config_language );
        $this->default_language = $language[0];
        if( isset( $this->languages[$config_language] ) ){
            $this->default_language_id = (int)$this->languages[$config_language]['language_id'];
        }

        if( $this->is_admin ){
            $this->load->model( $this->route );
        }else{
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/extension/module/knawat_dropshipping.php";
            $this->model_extension_module_knawat_dropshipping = new ModelExtensionModuleKnawatDropshipping( $registry );
        }

        $this->import_type = $import_type;

        // Load API Wrapper.
        require_once( DIR_SYSTEM . 'library/knawat_dropshipping/knawatmpapi.php' );
        $this->mp_api = new KnawatMPAPI( $this->registry );

        $default_args = array(
            'limit'             => 10,   // Limit for Fetch Products
            'page'              => 1,    // Page Number for API
            'force_update'      => false, // Whether to update existing items.
            'prevent_timeouts'  => true, // Check memory and time usage and abort if reaching limit.
            'is_complete'       => false,
            'imported'          => 0,
            'failed'            => 0,
            'updated'           => 0,
            'batch_done'        => false
        );

        if( is_array( $import_args ) ){
            $default_args = array_merge( $default_args, $import_args );
        }
        $this->params = $default_args;
    }

    public function __get($name) {
        return $this->registry->get($name);
    }


    /** 
     *  Main import function.
     */
    public function import($manual_import = false){
        //error_reporting(0);
        $this->start_time = time();
        $data             = array(
            'imported' => array(),
            'failed'   => array(),
            'updated'  => array(),
        );
        /*add start time */
        $start_time = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8158', 'time','start_time');
        if(empty($start_time)){
            $this->model_extension_module_knawat_dropshipping->add_knawat_meta('8158', 'time',time(), 'start_time' );
        }else{
            $this->model_extension_module_knawat_dropshipping->update_knawat_meta('8158', 'time',time(), 'start_time' );
        }
        /*add start time*/
        if( $this->is_admin ){
            $this->load->model('catalog/product');
        }else{
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/catalog/product.php";
            $this->model_catalog_product = new ModelCatalogProduct( $this->registry );
        }

        switch ( $this->import_type ) {
            case 'full':
                $lastUpdated = $this->model_extension_module_knawat_dropshipping->get_knawat_meta('8159', 'time','knawat_last_imported');
                if (empty($lastUpdated) || $manual_import) {  // Manual import = 1 -- (from import button)
                    $lastUpdated = 0;
                }
                $productdata = $this->mp_api->get( 'catalog/products/?limit='.$this->params['limit'].'&page='.$this->params['page']. '&lastupdate='.$lastUpdated );
                break;

            case 'single':
                $product_sku = '';
                $product_id = $this->params['product_id'];
                if( $product_id != '' ){
                    $tempproduct = $this->model_catalog_product->getProduct( $product_id );
                    $product_sku = isset( $tempproduct['sku'] ) ? $tempproduct['sku'] : '';
                    if( empty( $product_sku ) ){
                        $product_sku = isset( $tempproduct['model'] ) ? $tempproduct['model'] : '';
                    }
                }
                if( empty( $product_sku ) ){
                    return array( 'status' => 'fail', 'message' => 'Please provide product sku.' );
                }
                    // API Wrapper class here.
                $productdata = $this->mp_api->get( 'catalog/products/'. $product_sku );
                break;

            default:
            break;
        }

        if( !empty( $productdata ) && ( isset( $productdata->products ) || isset( $productdata->product ) ) ){

            $products = array();
            if ( 'single' === $this->import_type ) {
                if( isset( $productdata->product->status ) && 'failed' == $productdata->product->status ){
                    $error_message = isset( $productdata->product->message ) ? $productdata->product->message : 'Something went wrong during get data from Knawat MP API. Please try again later.';
                    return array( 'status' => 'fail', 'message' => $error_message );
                }
                $products[] = $productdata->product;
            }else{
                $products = $productdata->products;
            }

            // Handle errors
            if( isset( $products->code ) || !is_array( $products ) ){
                return array( 'status' => 'fail', 'message' => 'Something went wrong during get data from Knawat MP API. Please try again later.' );
            }

            // Update Product totals.
            if( empty( $products ) ){
                $this->params['is_complete'] = true;
                return $data;
            }

            foreach( $products as $index => $product ){

                $result = $this->import_product( $product, $this->params['force_update'] );

                if ( !$result ) {
                    $data['failed'][] = $product->sku;
                } else{
                    if ( isset( $result['updated'] ) ) {
                        $data['updated'][] = $result['updated'];
                        $product_id = $result['updated'];
                    } else {
                        $data['imported'][] = $result['imported'];
                        $product_id = $result['imported'];
                    }

                }
                /*if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
                    break;
                }*/
            }

            $products_total = count( $products );
            if( $products_total === 0 ){
                $this->params['is_complete'] = true;
            }else{
                $this->params['is_complete'] = false;
            }

            $this->params['batch_done'] = true;
            if(isset($data['failed'])){
                $this->log->write("Failed Products : ".count($data['failed']));
            }
            if(!empty($data['imported'])){
                $this->log->write("Imported Products : ".count($data['imported']));
            }
            if(!empty($data['updated'])){
               $this->log->write("Updated Products : ".count($data['updated']));
            }
            $this->log->write(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>Batch completed<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<");
            return $data;

        }else{
            error_log( $this->data );
            return array( 'status' => 'fail', 'message' => 'Something went wrong during get data from Knawat MP API. Please try again later.' );
        }
    }

    /**
     * Import Products
     */
    public function import_product( $product, $force_update = false ){

        if( empty( $product ) || !isset( $product->sku ) || !isset($product->variations[0]->sale_price) ){
            if(isset($product->sku)){
                $errorSku = $product->sku;
            }
            if(empty($product) && isset($product->sku)){
                $this->log->write("Product Failed , Product data is empty , Sku : ".$errorSku);
            }
            if(empty($product) && !isset($product->sku)) {
                $this->log->write("Product Failed , Product data is empty and sku not found");
            }
            if(!isset($product->sku)){
                $this->log->write("Product Failed , It's sku not found");   
            }
            if(!isset($product->variations[0]->sale_price) && isset($product->sku)){
                $this->log->write("Product Failed , It's sale price is not available , Sku : ".$errorSku);   
            }
            return false;
        }

        if( $this->is_admin ){
            $this->load->model('catalog/product');
        }else{
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/catalog/product.php";
            $this->model_catalog_product = new ModelCatalogProduct( $this->registry );
        }
        
        /* Check for Existing Product */
        $product_id = $this->model_extension_module_knawat_dropshipping->get_product_id_by_model( $product->sku );

        if( $product_id && $product_id > 0 ){
            $product_data = $this->format_product( $product, true, $force_update );
            if( !empty( $product_data ) ){
                if( $force_update ){
                    $product_data['product_category'] = $this->model_catalog_product->getProductCategories($product_id);
                    $this->model_catalog_product->editProduct( $product_id, $product_data );
                }else{
                    $product_id = $this->model_extension_module_knawat_dropshipping->partial_update_product( $product_id, $product_data );
                }
            }

            // Update product options SKU
            $productOptions = $this->model_catalog_product->getProductOptions( $product_id );
            $this->model_extension_module_knawat_dropshipping->update_product_options_sku( $product_id, $product_data, $productOptions );
            // update custom meta
            $this->model_extension_module_knawat_dropshipping->update_knawat_meta( $product_id, 'is_knawat','1', 'product' );
            return array( 'updated' => $product_id );
        }else{
            $product_data = $this->format_product( $product );

            if( !empty( $product_data ) ){
                $product_id = $this->model_catalog_product->addProduct( $product_data );

                // Update product options SKU
                $productOptions = $this->model_catalog_product->getProductOptions( $product_id );
                $this->model_extension_module_knawat_dropshipping->update_product_options_sku( $product_id, $product_data, $productOptions );
                // update custom meta
                $this->model_extension_module_knawat_dropshipping->update_knawat_meta( $product_id, 'is_knawat','1', 'product' );
                return array( 'imported' => $product_id );
            }
        }
        return false;
    }


    /**
     * Format product as per Opencart Syntax.
     */
    /**
     * Format product as per Opencart Syntax.
     */
    public function format_product( $product, $update = false, $force_update = false ){
        if( empty( $product ) ){
            return $product;
        }

        if( !$update || $force_update ){

            $temp = array(
                'product_description' => array(),
                'model'             => isset( $product->sku ) ? $product->sku : '',
                'sku'               => isset( $product->sku ) ? $product->sku : '',
                'upc'               => '',
                'ean'               => '',
                'jan'               => '',
                'isbn'              => '',
                'mpn'               => '',
                'location'          => '',
                'price'             => '0',
                'points'            => '',
                'tax_class_id'      => '0',
                'quantity'          => '0',
                'minimum'           => '1',
                'subtract'          => '1',
                'stock_status_id'   => '5',
                'shipping'          => '1',
                'date_available'    => date( 'Y-m-d', strtotime( '-1 day') ),
                'length'            => '',
                'width'             => '',
                'height'            => '',
                'length_class_id'   => '1',
                'weight'            => '',
                'weight_class_id'   => '1',
                'status'            => '1',
                'sort_order'        => '0',
                'manufacturer'      => '',
                'manufacturer_id'   => '0',
                'product_store'     => array(0),
                'product_category'  => array(),
                'product_option'    => array(),
                'image'             => ''  // This is Pending.
            );

            //  Add to selected stores.
            if ( !empty( $this->all_stores ) ) {
                $temp['product_store'] = $this->all_stores;
            }

            $languageCodes = array();
            foreach ( $this->languages as $key => $lng ) {
                // Check for name in current language.
                $lng_code = explode( '-', $lng['code'] );
                $lng_code = $lng_code[0];

                $name = $product->name;
                $description = empty($product->description)? "" : $product->description;

                /*$product_name = array_key_exists( $lng_code, $name ) ? $name[$lng_code] : $name['en'];
                $product_desc = array_key_exists( $lng_code, $description ) ? $description[$lng_code] : $description['en'];*/
                $product_name = isset( $name->$lng_code ) ? $name->$lng_code : '';
                if( empty( $product_name )){
                  $product_name = isset( $name->en ) ? $name->en : '';
              }
              /*set url*/
             if(version_compare(VERSION, '3.0.0','<') ) {
                if(isset($name->en)){
                    $urlData = $name->en;
                }elseif (isset($name->$lng_code)) {
                    $urlData = $name->en;
                }
                if(!empty($urlData)){
                    $url = strtolower($urlData);
                    $urlKey = str_replace(' ', '-', $url); 
                    $temp['keyword'] = $urlKey;
                }
              }
              /*set url*/
              $product_desc = isset( $description->$lng_code ) ? $description->$lng_code : '';
              if( empty( $product_desc )){
                  $product_desc = isset( $description->en ) ? $description->en : '';
              }
              $temp['product_description'][$lng['language_id']] = array(
                'name'              => $product_name,
                'description'       => $product_desc,
                'meta_title'        => $product_name,
                'meta_description'  => '',
                'meta_keyword'      => '',
                'tag'               => '',
            );
              $languageCodes[] = $lng_code;
              $languageIds[$lng_code] = $lng['language_id'];

          }
          /*attribute group code*/
          if(!empty($languageCodes)){
            $language_code = $languageCodes[0];
            $attributeGrouplanguageId = $languageIds[$language_code];
        }
        /*load model*/
        if( $this->is_admin ){
            $this->load->model('catalog/attribute_group');
        }else{
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/catalog/attribute_group.php";
            $this->model_catalog_attribute_group = new ModelCatalogAttributeGroup( $this->registry );
        }
        /*load model*/
        $attribute_group_id = $this->model_extension_module_knawat_dropshipping->getAttributeGroup('knawat');
        if(!$attribute_group_id){
            /*add attribute set*/
            $attributeGroupArray = array();
            $attributeGroupArray['sort_order'] = 2;
            $attributeGroupArray['attribute_group_description'][$attributeGrouplanguageId] = array(
                'name'              => 'Knawat'
            );
            $attribute_group_id = $this->model_catalog_attribute_group->addAttributeGroup($attributeGroupArray); 
        }
        /*add attribute set*/
        /*attribute group code*/
        /*load model*/
        if( $this->is_admin ){
            $this->load->model('catalog/attribute');
        }else{
            $admin_dir = str_replace( 'system/', 'admin/', DIR_SYSTEM );
            require_once $admin_dir . "model/catalog/attribute.php";
            $this->model_catalog_attribute = new ModelCatalogAttribute( $this->registry );
        }
        /*load model*/
        /*variation array*/
        if (isset($product->variations[0]->attributes) && !empty($product->variations[0]->attributes)) {
            $attribute = $product->variations[0]->attributes;
            if($attribute[0]){
                if (isset($attribute[0]->name) || !empty($attribute[0]->name) ) {
                    $variationdata = (array)$attribute[0]->name;
                }
            }
        }
        /*variation array*/
        /*attribute code*/
        if (isset($product->attributes) && !empty($product->attributes)) {
            $subArray = array();
            foreach ($product->attributes as $key => $attribute) {
                $attributeNames =  (array)$attribute->name;
                    foreach ($attributeNames as $key => $value) {
                    if(!empty($variationdata[$key]) && !in_array($variationdata[$key], $attributeNames)){
                        if(in_array($key, $languageCodes)){
                            $attributeId = $this->model_extension_module_knawat_dropshipping->getAttributeData($value);
                            if(!$attributeId){
                                $languageId = $languageIds[$key];
                                $newArray[$languageId] = array(
                                    'language_id' => $languageId,
                                    'name'  => $value
                                );
                            }               
                        } 
                    }
                                                  
                    }
                    if(!empty($newArray)){
                        $subArray['attribute_group_id'] = $attribute_group_id;
                        $subArray['sort_order'] = 2;
                        $subArray['attribute_description'] = $newArray; 
                        $this->model_catalog_attribute->addAttribute($subArray);         
                    }
            }
        }
        /*attribute code*/
        /*product code*/
       if(!empty($product->attributes)){
        foreach ($product->attributes as $key => $attribute) {
            $attributeNames =  (array)$attribute->name;
            if($variationdata != $attributeNames){
                $productAttributes = array();
                foreach ($attributeNames as $key => $value) {
                    if(in_array($key, $languageCodes)){
                        $languageId = $languageIds[$key];
                        if(isset($attribute->options[0]->$key)){
                            $options = $attribute->options[0]->$key;
                        }else{
                            if(isset($attribute->options[0]->en)){
                                $options = $attribute->options[0]->en;
                            }else if (isset($attribute->options[0]->ar)) {
                                $options = $attribute->options[0]->ar;
                            }else if (isset($attribute->options[0]->tr)){
                                $options = $attribute->options[0]->tr;
                            }
                        }
                        $attributeId = $this->model_extension_module_knawat_dropshipping->getAttributeData($value);
                        $productAttributes[$languageId] = array(
                            'text' => $options
                        );
                        $attributeId = (int) $attributeId;
                        $temp['product_attribute'][] = array(
                            'attribute_id'  => $attributeId,
                            'product_attribute_description' => $productAttributes
                        );
                    }
                }
            }
        }
    }
        /*product code end*/

            /**
             * Setup Product Category.
             */
            //make products get uncategorized in both import and update.

            // if( isset( $product->categories ) && !empty( $product->categories ) ) {
            //     $new_cats = array();
            //     foreach ( $product->categories as $category ) {
            //         if( isset( $category->name ) && !empty( $category->name ) ){
            //             $new_cats[] = (array)$category->name;
            //         }
            //     }
            //     $temp['product_category'] = $this->model_extension_module_knawat_dropshipping->parse_categories( $new_cats );
            // }

            /**
             * Setup Product Images.
             */
            if( isset( $product->images ) && !empty( $product->images ) ) {
                $images = (array)$product->images;
                $product_sku = isset( $product->sku ) ? $product->sku : '';

                $product_images = $this->parse_product_images( $images, $product_sku );
                if( !empty( $product_images ) ){
                    $temp['image'] = $product_images[0];
                    unset( $product_images[0] );
                    if( count( $product_images ) > 0 ){
                        foreach ($product_images as $pimage ) {
                            $temp_image['image'] = $pimage;
                            $temp_image['sort_order'] = '0';
                            $temp['product_image'][] = $temp_image;
                        }
                    }
                }
            }

        }else{
            $temp = array();
        }

        if( isset( $product->variations ) && !empty( $product->variations ) ){
            $quantity = 0;
            $price = $product->variations[0]->sale_price;
            if( isset( $product->variations[0]->market_price ) ){
                $market_price = $product->variations[0]->market_price;
            }
            if( empty( $market_price ) ){
                $market_price = $price;
            }
            foreach ( $product->variations as $vvalue ) {
                $quantity += $vvalue->quantity;
            }
            if(isset($product->variations[0]->weight)){
                $weight = $product->variations[0]->weight;
                $temp['weight']     = $weight;
            }
            $temp['price']      = $market_price;
            $temp['quantity']   = $quantity;
            if( $quantity > 0 ){
                $temp['stock_status_id'] = '7';
            }else{
                $temp['stock_status_id'] = '5';
            }
            if(!empty($price)){
                if($price < $market_price){
                    $temp['product_special'][] = array(
                        'customer_group_id' => 1,
                        'price'  => $price,
                        'priority' => 1,
                        'date_start' => 0000-00-00,
                        'date_end' => 0000-00-00
                    );    
                }
            }
            $temp['product_option'] = $this->model_extension_module_knawat_dropshipping->parse_product_options( $product->variations, $price,$update );
        }
        if(!empty($temp['product_option'])){
            foreach ($temp['product_option'] as $key) {
                $i = 0;
                    if(isset($key['product_option_value'])){
                        $j = count($key['product_option_value']);
                    }else{
                        $j = 0;
                    }
                    foreach ($key['product_option_value'] as $value) {
                        if($value['quantity'] == 0){
                            $i++;
                        }
                    }
                }
                if($i == $j){
                    $temp['stock_status_id'] = '5';
                }
        }
        if(empty($temp['product_option']) && !$update){
             $this->log->write("Product Failed , It's variations not available or available with zero quantity, Sku : ".$temp['sku']);
                return false;
        }
        ///////////////////////////////////
        /////// @TODO Custom Fields ///////
        ///////////////////////////////////
        return $temp;
    }

    /**
     * Parse Product images.
     * download all images and return local path of images.
     *
     * @return array 
     */
    public function parse_product_images( $images = array(), $product_sku = '' ){
        if( empty( $images ) || !is_array( $images ) ){
            return array();
        }
        $parsed_images = array();

        foreach ( $images as $image ) {
            $parsed_image = $this->save_image( $image, $product_sku );
            if( $parsed_image && $parsed_image != '' ){
                $parsed_images[] = $parsed_image;
            }
        }

        return $parsed_images;
    }

    /**
     * Save image from URL and return relative path from catalog folder
     * 
     * @return string|boolean relative path from catalog folder
     */
    public function save_image( $image_url, $prefix = '' ){
        if( !empty( $image_url ) ){
            // Set variables for storage, fix file filename for query strings.
            preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $image_url, $matches );
            if ( ! $matches ) {
                return false;
            }

            $directory = DIR_IMAGE . 'catalog/';
            if (!is_dir($directory) ){
                return false;
            }

            $image_name = $prefix . '_image_'.basename( $matches[0] );

            $full_image_path = $directory.$image_name;
            $catalog_path = 'catalog/' . $image_name;

            if( file_exists( $full_image_path ) ){

                return $catalog_path;

            }else{

                // Download image.
                $ch = curl_init ( $image_url );
                curl_setopt( $ch, CURLOPT_HEADER, 0 );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $ch, CURLOPT_BINARYTRANSFER,1 );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                // curl_setopt( $ch, CURLOPT_PROXY, "192.168.10.5:8080" ); // for local USE only remove it please
                $raw_image_data = curl_exec( $ch );
                curl_close ( $ch );

                try{
                    $newFileName = strtok($full_image_path, "?");
                    $image = fopen( $newFileName,'w' );
                    fwrite( $image, $raw_image_data );
                    fclose( $image );

                    return $catalog_path;
                }catch( Exception $e ){ 
                    return false;
                }

            }
        }
        return false;
    }

    /**
     * Get Import Parameters
     *
     * @return array()
     */
    public function get_import_params(){
        return $this->params;
    }
}

