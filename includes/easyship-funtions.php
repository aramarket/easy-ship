<?php

add_action('admin_enqueue_scripts', 'es_admin_ship_scripts');
function es_admin_ship_scripts() {
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
		wp_enqueue_style ('es-popup-style',  EASYSHIP_DIR . 'assets/css/es-popup-style.css', [], 1.0);
		wp_enqueue_script('es-popup-script', EASYSHIP_DIR . 'assets/js/es-popup-script.js', array('jquery'), '1.0', true );
	}
}

add_action( 'admin_footer', 'show_popup_window' ); 
function show_popup_window() {
	?>
	<div id="es-popup" class="popup-background">
		<div id="loader" class="lds-dual-ring hidden overlay"></div>
		<div class="popup-article-ajax">
			<!-- 	ajax responce display here	 -->
		</div>
	</div>
    <?php
}
// handel ajax popup for both single and bulk orders
add_action( 'wp_ajax_es_handel_popup', 'es_handel_popup_ajax' );
function es_handel_popup_ajax() {
    $order_ids = $_POST['order_ids'];
	$shipBy    = $_POST['shipBy'];
	if($shipBy == 'NP'){
		echo nimbuspost_show_single_rate_popup($order_ids);
	}elseif($shipBy == 'NPB'){
		echo nimbuspost_show_bulk_rate_popup($order_ids);
	}elseif($shipBy == 'DL'){
		echo delhivery_show_single_rate_popup($order_ids);
	}elseif($shipBy == 'DLB'){
		echo delhivery_show_bulk_rate_popup($order_ids);
	}elseif($shipBy == 'SR'){
		echo shiprocket_show_single_rate_popup($order_ids);
	}elseif($shipBy == 'SRB'){
		echo shiprocket_show_bulk_rate_popup($order_ids);
	}
    wp_die(); // Always include this line to terminate the AJAX request
}

// handel ajax to create for single shipment
add_action( 'wp_ajax_es_ship_single_order', 'es_ship_single_order_ajax' );
function es_ship_single_order_ajax() {
	$order_ID         = $_POST['order_id'];
	$ship_company_ID  = $_POST['ship_company_ID'];
	$shipBy    		  = $_POST['ship_by'];
	$order_ID = (int)preg_replace('/[^0-9]/', '', $order_ID);
	$ship_company_ID = (int)preg_replace('/[^0-9]/', '', $ship_company_ID);
	if($shipBy == 'NP'){
		echo es_nimbusPost_create_shipments($order_ID, $ship_company_ID);
	}elseif($shipBy == 'DL'){
		$order_ID = array($order_ID);
		echo es_delhivery_create_shipments($order_ID);
	}elseif($shipBy == 'SR'){
		echo es_shiprocket_create_shipments($order_ID, $ship_company_ID);
	}
	wp_die(); // Always include this line to terminate the AJAX request
}
// handel ajax to create for bulk shipment
add_action( 'wp_ajax_es_ship_bulk_order', 'es_ship_bulk_order_ajax' );
function es_ship_bulk_order_ajax() {
	$order_IDs = $_POST['order_ids'];
	$shipBy    = $_POST['ship_by'];
	$results = []; // Empty array to store the results
	if($shipBy == 'DL'){
		$results = es_delhivery_create_shipments($order_IDs);
	}else{
		foreach ($order_IDs as $order_ID) {
			$orderID 		 = $order_ID['orderID'];
			$ship_company_ID = $order_ID['shippingID'];
			if($shipBy == 'NPB'){
				$result = es_nimbusPost_create_shipments($orderID, $ship_company_ID);
			}elseif($shipBy == 'SR'){
				$result = es_shiprocket_create_shipments($orderID, $ship_company_ID);
			}
			$results[$orderID] = $result; // Push the result into the array
		}
	}
	wp_send_json(json_encode($results));
	wp_die(); // Always include this line to terminate the AJAX request
}

function es_product_tabel($order_ID){
	$order = wc_get_order( $order_ID );	
	$items = $order->get_items();
	$order_weight = es_order_weight($order_ID);
	?>
			<div class="eashyship-product-disc">
			<h3 class="eashyship-order-info">Order <a href="<?php echo get_edit_post_link($order_ID);?>" target="_blank"> <?php echo '#'.$order_ID; ?></a> <?php echo ' (' .$order->get_billing_first_name().' '.$order->get_billing_last_name().', '.$order->get_billing_state().'-'.$order->get_billing_postcode().')'?></h3>
			<table class="eashyship-table">
				<tr>
					<th>Img</th>
					<th>Name</th>
					<th>SKU</th>
					<th>QTYxPrice</th>
					<th>Total</th>
					<th>Weight</th>
				</tr>
				<?php $counter = 0; foreach ($items as $item) : $counter++; ?>
				<tr>
<td><img class="es-img" alt="img" src="<?php echo get_the_post_thumbnail_url($item->get_product_id(), 'thumbnail'); ?>"></td>
<td><a href="<?php echo admin_url().'post.php?post='.$item->get_product_id().'&action=edit' ?>" target="_blank" ><?php echo $item->get_name(); ?></a></td>
					<td><?php echo $item->get_product()->get_sku(); ?></td>
					<td><?php echo $item->get_quantity().'x₹'.$item->get_total()/$item->get_quantity(); ?></td>
					<td><?php echo '₹'.$item->get_total(); ?></td>
					<td><?php echo $item->get_quantity(). 'x' .$item->get_product()->get_weight().get_option('woocommerce_weight_unit'); ?></td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<th></th>
					<th>Total</th>
					<th><?php echo $counter.'(item)'; ?></th>
					<th></th>
					<th><?php echo '₹'.$order->get_total() ?></th>
					<th><?php echo $order_weight.'g' ?></th>
				</tr>
			</table>
		</div>
<?php
}
function es_bulk_template($order_IDs, $shipBy){
	?>
		<table class="eashyship-table">
			<tr>
				<th>SR.</th>
				<th>Odr No.</th>
				<th>Product</th>
				<th>Add</th>
				<th>Total</th>
				<th>Weight(g)</th>
				<th>Shipping Cost</th>
			</tr>
				<?php 
					$counter = 0;
					$grosh_total;
					foreach ($order_IDs as $order_ID) : 
						$counter ++;
						$order = wc_get_order( $order_ID );	
						$order_weight = es_order_weight($order_ID);
						$grosh_total += $order->get_total(); 
				?>
				<tr class='es-bulk-table-row'>
					<td><input type="hidden" name="order_IDs[]" value="<?php echo $order_ID; ?>"><?php echo $counter; ?></td>
					<td><a href="<?php echo get_edit_post_link($order_ID);?>" target="_blank"> <?php echo '#'.$order_ID; ?></a></td>
					<td>
						<?php $items = $order->get_items(); foreach ($items as $item) : ?>
							<a href="<?php echo admin_url().'post.php?post='.$item->get_product_id().'&action=edit' ?>" target="_blank" >
								<img class="es-img" alt="img" src="<?php echo get_the_post_thumbnail_url($item->get_product_id(), 'thumbnail'); ?>">
							</a>
						<?php endforeach; ?>
					</td>
					<td><?php echo $order->get_billing_first_name().' '.$order->get_billing_last_name().',</br>'.$order->get_billing_state().'-'.$order->get_billing_postcode(); ?></td>
					<td class="es-row-amount"><?php echo '₹'.$order->get_total().'(' . es_check_payment_mode( $order->get_payment_method_title(), 'ES' ) . ')' ?></td>
					<td><?php echo $order_weight.'g'; ?></td>
					<td>
						<?php
						    switch ($shipBy) {
								case 'SRB':
									echo es_shiprocket_bulk_ratelist($order_ID);
									break;
								case 'NPB':
									echo es_nimbuspost_bulk_ratelist($order_ID);
									break;
								case 'DLB':
									echo es_delhivery_bulk_ratelist($order_ID);
									break;
								default:
									echo 'Unknown Status';
							}	
					 	?>
					 </td>
					<td><?php echo '<span class="es-remove-row">&times;</span>' ?></td>
				</tr>
				<?php endforeach; ?>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th>Total</th>
				<th id="es-grosh-total"><?php echo '₹'.$grosh_total; ?></th>
				<th></th>
				<th id="es-shipp-total"><?php echo '₹'; ?></th>
			</tr>
		</table>
	<?php	
}

class ES_db_data_format {
    public $order_number;
    public $order_price;
	public $order_weight;
	public $caurier;
	public $courier_id;
	public $awb;
	public $tp_company;
	public $label;
}

// Insert/update the awbNumber into wp db table is ets
function es_insert_order_data_db($shipmet_data){
	global $wpdb;
	$table_name = $wpdb->prefix . 'easyship_db';
	$data = array( 
		'order_number' 	  => $shipmet_data->order_number,
		'order_price'     => $shipmet_data->order_price,
		'order_weight'     => $shipmet_data->order_weight,
		'date_created'	  => current_time('mysql'), // Store current timestamp,
		'awb_number' 	  => $shipmet_data->awb,
		'courier_id' 	  => $shipmet_data->courier_id,
		'courier_name' 	  => $shipmet_data->caurier,
		'shipped_through' => $shipmet_data->tp_company,
		'label'       	  => $shipmet_data->label,  
		'states'       	  => 'false'  
	);
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT order_number FROM $table_name WHERE order_number = %s", $shipmet_data->order_number ) );
	if ( $exists ) {
		$wpdb->update( $table_name, $data, array( 'order_number' => $shipmet_data->order_number ) );
		// Check if there was an error inserting the data
		if ( $wpdb->last_error ) {
			return 'Error : In Update db -'.$wpdb->last_error;
		} else {
			return 'success';
		}
	} else {
		$wpdb->insert( $table_name, $data );
			if ( $wpdb->last_error ) {
				return 'Error : In insert db -'.$wpdb->last_error;
			} else {
				return 'success';
			}
	}
}

// check payment method for get rate
function es_check_payment_mode($Given_pay_Mehtod, $checkFor){
	$cod_string 		= array("Cash on delivery", "COD (Cash on delivery)");
	$es_map_cod_orders  = get_option('es_map_cod_orders');
	if (!empty($es_map_cod_orders)) {
		$cod_string = array_merge($cod_string, explode(',', $es_map_cod_orders));
	}
	$prepaid_string = array("Paytm Payment Gateway", "Razorpay Payment Gateway", "UPI/QR/Card/NetBanking");
	$es_map_prepaid_orders = get_option('es_map_prepaid_orders');
	if (!empty($es_map_prepaid_orders)) {
		$prepaid_string = array_merge($prepaid_string, explode(',', $es_map_prepaid_orders));
	}

	if(in_array($Given_pay_Mehtod, $cod_string)){
		switch ($checkFor) {
			case 'SR1':
				return 1;
				break;
			case 'SR2':
			case 'DL':
				return 'COD';
				break;
			case 'NP':
				return 'cod';
				break;
			case 'ES':
				return 'C';
				break;
			default:
				return 'COD';
		}
	}elseif(in_array($Given_pay_Mehtod, $prepaid_string)){
		switch ($checkFor) {
			case 'SR1':
				return 0;
				break;
			case 'SR2':
				return 'Prepaid';
				break;
			case 'DL':
				return 'Pre-paid';
				break;
			case 'NP':
				return 'prepaid';
				break;
			case 'ES':
				return 'P';
				break;
			default:
				return 'Prepaid';
		}
	}else{
		return 'COD';
	}
}

function es_order_weight($order_ID){
	$order = wc_get_order( $order_ID );
	$items = $order->get_items();
	$weight = 0;
	foreach ( $items as $item ) {
		$product = $item->get_product();
		$one_item_weight = $product->get_weight();
		if($one_item_weight == null) {
			$one_item_weight = 0.1;
		}
		$product_weight = $one_item_weight*$item->get_quantity();
		$weight = $product_weight + $weight;
	}
	$product_weight_unit = get_option('woocommerce_weight_unit');
	if($product_weight_unit == 'kg'){
		$weight = $weight*1000;
	}
	if($weight < 500){
		$weight = 500;
	}
	return (intval($weight));
}

function es_nimbusPost_getStatus($input) {
    switch ($input) {
        case 'PP':
            return 'Pending Pickup';
        case 'IT':
            return 'In Transit';
        case 'EX':
            return 'Exception';
        case 'OFD':
            return 'Out For Delivery';
        case 'DL':
            return 'Delivered';
        case 'RT':
            return 'RTO';
        case 'RT-IT':
            return 'RTO In Transit';
        case 'RT-DL':
            return 'RTO Delivered';
        default:
            return 'Unknown Status';
    }
}

function replaceSpecialChars($string, $replacement = '') {
    $specialChars = array(
        '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/',
        ':', ';', '<', '=', '>', '?', '@', '[', '\\', ']', '^', '_', '`', '{', '|', '}', '~',
        '’', '“', '”', '‘'
    );
	
    $string = str_replace($specialChars, $replacement, $string);
    return $string;
}

function es_make_string_shorter($string, $number){
	$words = explode(' ', $string); // Split the string into an array of words
	$firstFiveWords = array_slice($words, 0, $number); // Take the first five words
	return implode(' ', $firstFiveWords); // Output the first five words separated by spaces
}
function is_wc_order_id_exists($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        return true; // Order ID exists
    } else {
        return false; // Order ID does not exist
    }
}
function extract_phone_number($input) {
  // Remove all non-digit characters and space from the input string
  $input = preg_replace("/[^0-9]/", "", $input);

  // Remove any leading zeros and the country code "+91"
  if (strlen($input) > 10) {
    $input = substr($input, -10);
  }
  return $input;
}

// Get product dimention only if one product in cart
function es_get_product_dimensions($order_ID) {
    $order = wc_get_order($order_ID);
    $items = $order->get_items();
    if (count($items) === 1) {
        $item = reset($items);
        $product = $item->get_product();
        $length = $product->get_length();
        $width = $product->get_width();
        $height = $product->get_height();
		if($length){
			return array(
				'length' => $length,
				'width'  => $width,
				'height' => $height
			);
		}else{
			return array(
				'length' => 11,
				'width'  => 11,
				'height' => 11
			);
		}

    } else {
        return array(
            'length' => 10,
            'width'  => 10,
            'height' => 10
        );
    }
}


function read_db_data($order_ID, $value){
	global $wpdb;
	$table_name = $wpdb->prefix . 'easyship_db';
	$result = $wpdb->get_var( $wpdb->prepare( "SELECT $value FROM $table_name WHERE order_number = %d", $order_ID ) );
	// Check if a Value was found
	if ( !$result ) {
		return 'No Result';
	} else {
		return $result;
	}
}


//start Add custom postbox in edit order post
add_action( 'add_meta_boxes', 'es_add_tracking_detail_postbox' );
function es_add_tracking_detail_postbox(){
    add_meta_box( 'custom_order_box', __( 'Add Tracking Details' ), 'es_tracking_box_content', 'shop_order', 'side', 'high' );
}

function es_tracking_box_content($post){
    // Get order ID
    $order_id = $post->ID;

    // Get custom field values
    $awb_field_name = get_post_meta( $order_id, '_custom_awb_number', true );
    $custom_field_options = array('Select Courier Company', 'Shiprocket', 'Delhivery', 'NimbusPost');
    $selected_option = get_post_meta( $order_id, '_selected_option', true );
    // Output HTML for custom postbox
    ?>
    <div class="custom-order-box">
        <p>
            <label for="custom_awb_number"><?php _e( 'Enter AWB Number' ); ?></label>
            <input type="text" class="" name="custom_awb_number" id="custom_awb_number" value="<?php echo $awb_field_name; ?>">
        </p>
        <p>
            <label for="selected_option"><?php _e( 'Select Courier Company' ); ?></label>
            <select name="selected_option" id="selected_option">
                <?php foreach( $custom_field_options as $option ) : ?>
                    <option value="<?php echo $option; ?>" <?php selected( $selected_option, $option ); ?>><?php echo $option; ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php wp_nonce_field( 'custom_order_box', 'custom_order_box_nonce' ); ?>
        <input type="hidden" name="order_id" id="order_id" value="<?php echo $order_id; ?>">
        <p>
            <button type="submit" class="button button-primary" name="custom_order_submit"><?php _e( 'Save' ); ?></button>
        </p>
    </div>
    <?php
}

// Save custom field values
add_action( 'save_post', 'es_save_tracking_values', 10, 2 );
function es_save_tracking_values( $order_ID, $post ) {
    // Check if nonce is set
    if ( ! isset( $_POST['custom_order_box_nonce'] ) ) {
        return;
    }
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['custom_order_box_nonce'], 'custom_order_box' ) ) {
        return;
    }
    // Check if user has permissions to save
    if ( ! current_user_can( 'edit_post', $order_ID ) ) {
        return;
    }
    // Save custom field values
    if (isset($_POST['custom_order_submit'])) {
		if ((isset( $_POST['custom_awb_number']))&&(isset( $_POST['selected_option']))) {
			$awb = sanitize_text_field( $_POST['custom_awb_number'] );
			$company = sanitize_text_field( $_POST['selected_option'] );
			$shipping_cost = sanitize_text_field( $_POST['custom_shipping_cost'] );
			update_post_meta($order_ID, '_custom_awb_number', $awb );
			update_post_meta( $order_ID, '_selected_option', $company );
			if($company == 'Delhivery'){ $company_initial = 'DL'; }
			if($company == 'NimbusPost'){ $company_initial = 'NB'; }
			if($company == 'Shiprocket'){ $company_initial = 'SR'; }
			es_custom_box_created_handle($order_ID, $awb, $company_initial, $company);
		}else{
		   // Assuming you have detected an error and need to display a message
			$error_msg = 'There was an error updating the order. Please try again.';
			// Add an error notice to the session
			wc_add_notice( $error_msg, 'error' );
		}
	}
}

// Insert/update the awbNumber into wp db table is ets
function es_custom_insert_data_db($order_ID, $awb, $company_initial, $company){

	$order 					= wc_get_order( $order_ID );
	$order_weight	   	 	= es_order_weight($order_ID);

	$awb_data 				= new ES_db_data_format();
	$awb_data->order_number = $order_ID;
	$awb_data->order_price 	= $order->get_total();
	$awb_data->order_weight	= $order_weight;
	$awb_data->caurier 		= $company;
	$awb_data->courier_id 	= '';
	$awb_data->awb 			= $awb;
	$awb_data->tp_company 	= $company_initial;
	$awb_data->label 		= '';

	return es_insert_order_data_db($awb_data);

}

function es_custom_box_created_handle($order_ID, $awb, $company_initial, $company){

	$db_responce = es_custom_insert_data_db($order_ID, $awb, $company_initial, $company);
	if($db_responce == 'success'){
		$order = wc_get_order( $order_ID );
		// Change order status to "completed" and save
		$order_massage = 'https://aramarket.in/tracking/?order-id='.$order_ID;
		$selected_status_wc = get_option('after_ship_status');
		$selected_status = str_replace('wc-', '', $selected_status_wc);
		$order->update_status( $selected_status, 'EasyShip Change -');
		$order->add_order_note('Tracking Link - <a target="_blank" href="'.$order_massage.'">'. $order_massage. '</a>',true);
		$order->save();
		return 'shipped';
	}else{
		return $db_responce;
	}
}




//end Add custom postbox in edit order post


//start update status optrations
add_filter('cron_schedules', 'es_add_cron_interval');
function es_add_cron_interval($schedules) {
    $schedules['twice_daily'] = array(
        'interval' => 43200, // 12 hours in seconds
        'display' => esc_html__('Twice Daily'),
    );
    $schedules['once_daily'] = array(
        'interval' => 86400, // 24 hours in seconds
        'display' => esc_html__('Once Daily'),
    );
    return $schedules;
}

add_action('wp', 'es_check_daily_schedule_function');
function es_check_daily_schedule_function() {
    if (!wp_next_scheduled('es_hook_for_update_statuss')) {
        wp_schedule_event(time(), 'once_daily', 'es_hook_for_update_statuss');
    }
}

add_action('es_hook_for_update_statuss', 'es_function_for_update_statuss');
function es_function_for_update_statuss() {
    file_put_contents(ABSPATH . 'wp-content/my_custom_cron_log.txt', 'Cron job update stutus api executed at ' . current_time('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
	$data = es_send_read_data_db();
	if(!($data == false)){
		$header_data = array(
			'Content-Type'  => 'application/json',
		);
		$response = wp_remote_post('https://easy-ship.in/wp-json/es-get-details/v1/shipments/', array(
			'headers' => $header_data,
			'body' 	  => $data,
		));
		if (is_wp_error($response)) {
			return FALSE;
		}
		$body = wp_remote_retrieve_body($response);
		es_send_update_data_db($body);
	}
	else{
		// error_log('es_function_for_update_status : no data found');
	}
}

function es_send_read_data_db() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'easyship_db';
	$query = $wpdb->prepare("SELECT * FROM $table_name WHERE states != 'uploded'");
	// Execute the query
	$shipments = $wpdb->get_results($query, ARRAY_A);
	
	$data = [];
	$domain = parse_url(get_site_url(), PHP_URL_HOST);
	// Iterate over the rows
	foreach ($shipments as $shipment) {
		$data[] = array(
			"order"        => $shipment['order_number'],
			"oder_price"   => $shipment['order_price'],
			"order_weight" => $shipment['order_weight'],
			"domain"       => $domain,
			"caurier"      => $shipment['courier_name'],
			"awb"          => $shipment['awb_number'],
			"tp_company"   => $shipment['shipped_through'],
			"date"         => $shipment['date_created']
		);
	}
	if (empty($data)) {
		return false;
	} else {
		$output = array("data" => $data);
		return json_encode($output);
	}
}

function es_send_update_data_db($response) {
	$response_data = json_decode($response, true);
	if ($response_data['status']) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'easyship_db';
		foreach ($response_data['message'] as $order_ID => $status) {
			if ($status === 'success') {
				$wpdb->update( $table_name, array('states' => 'uploded'), array( 'order_number' => $order_ID ) );
			}
		}
	}	
}
//end update status optrations


?>