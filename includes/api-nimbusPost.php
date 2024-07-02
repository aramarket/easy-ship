<?php

// woocommerce pincode should be filled
// delete_transient('es_nimbuspost_token');

function es_wp_get_request_nimbuspost($url){
	$token = es_nimbuspost_genrate_token();
	if (strpos($token, 'Error') !== false) { 
		return 'Error token: '.$token;
	}
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Token '.$token
	);
	$response = wp_remote_get($url, array(
		'headers' => $header_data,
		'timeout' => 10, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error api NB: '. $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return $body;
}

function es_wp_post_request_nimbuspost($url, $body){
	$token = es_nimbuspost_genrate_token();
	if (strpos($token, 'Error') !== false) { 
		// echo '<script>alert("Error in shiproket token generation: ' . $token . '");</script>';
		return 'Error token: '.$token;
	}
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Token '.$token
	);
	$response = wp_remote_post($url, array(
		'headers' => $header_data,
		'body'    => $body,
		'timeout' => 10, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error api NB: '. $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return $body;
}
// Genrate token
function es_nimbuspost_genrate_token(){
	$transient_name = 'es_nimbuspost_token';
	$token = get_transient($transient_name);
	if (false === $token) {
		$url = 'https://api.nimbuspost.com/v1/users/login';
		$username = get_option( 'nimbusPost_username' );
		$password = get_option( 'nimbusPost_password' );
		$header_data = array(
			'Content-Type'  => 'application/json',
		);
		$body = json_encode(array(
			'email'    => $username,
			'password' => $password
		), true);

		$response = wp_remote_post($url, array(
			'headers' => $header_data,
			'body'    => $body,
			'timeout' => 10, // Increase the timeout to 20 seconds
		));
		if (is_wp_error($response)) {
			return 'Error api NB: '. $response->get_error_message();
		}
		$res_body = wp_remote_retrieve_body($response);
		$token_data = json_decode($res_body);
		if ($token_data->status) {
			$token = $token_data->data;
			// Storing the token using Transients API
			$expiration = 3 * HOUR_IN_SECONDS; // Set expiration time as 3 hours (in seconds)
			set_transient($transient_name, $token, $expiration);
		} else {
			return 'Error -'. $token_data->message;
		}
	}
	return $token;
}

function nimbusPost_tracking_api($order_ID){
	$awb = read_db_data($order_ID,'awb_number');
	$url   = 'https://api.nimbuspost.com/v1/shipments/track/'.$awb;
	$response = es_wp_get_request_nimbuspost($url);
	$response_json = json_decode($response);
	if(!$response_json->status){
		return 'Error -'. $response_json->message;
	}
	return $response_json;
}

function es_nimuspost_get_rate($order_ID, $order_weight){
	$order = wc_get_order( $order_ID );
	$payment_mode = es_check_payment_mode($order->get_payment_method_title(), 'NP');
	$product_dimensions = es_get_product_dimensions($order_ID);
	
	$url   = 'https://api.nimbuspost.com/v1/courier/serviceability';
	
	$body = json_encode(array(
		"origin" 		=> get_option( 'nimbusPost_pincode' ),
		"destination" 	=> $order->get_billing_postcode(),
		"payment_type" 	=> $payment_mode,
		"order_amount" 	=> $order->get_total(),
		"weight" 		=> $order_weight,
		"length" 		=> $product_dimensions['length'],
		"breadth" 		=> $product_dimensions['width'],
		"height" 		=> $product_dimensions['height'],
	), true);	
	$response = es_wp_post_request_nimbuspost($url, $body);
	$response_decode = json_decode($response, true);
	if(!($response_decode['status'])){
		return 'Error : '.$response;
	}
	return $response;
}

function nimbuspost_manifest($awb){
	$url   = 'https://api.nimbuspost.com/v1/shipments/manifest';
	
	$body = json_encode(array(
		"awbs" 	=> ["4152911775885", "NMBC0001789312" ]
	), true);	
	$response = es_wp_post_request_nimbuspost($url, $body);
	$response_decode = json_decode($response, true);
	if(!($response_decode['status'])){
		return 'Error : '.$response;
	}
	return $response;
}

function es_nimbusPost_get_items($order_ID){
	$order = wc_get_order( $order_ID );	
	$items = $order->get_items();
	$order_items = array();
	foreach ( $items as $item ) {
		$product = $item->get_product();
		$product_name = $item->get_name();
		$product_quantity = $item->get_quantity();
		$product_total = $item->get_total();
		$product_weight = $product->get_weight()*$item->get_quantity();	
		$product_sku = $product->get_sku();
		$order_item = array(
			'name' 		=> $product_name,
			'qty' 		=> $product_quantity,
			'price' 	=> $product_total / $product_quantity, //price per item
			'sku' 		=> $product_sku,
			'weight' 	=> $product_weight,
		);
		$order_items[] = $order_item;
	}
	// Output order data as JSON
	$order_array = json_encode( $order_items );
	return $order_array;
}

// Insert/update the awbNumber into wp db table is ets
function nimbusPost_insert_data_db($order_ID, $response){

	$order 					= wc_get_order( $order_ID );
	$order_weight	   	 	= es_order_weight($order_ID);

	$awb_data 				= new ES_db_data_format();
	$awb_data->order_number = $order_ID;
	$awb_data->order_price 	= $order->get_total();
	$awb_data->order_weight	= $order_weight;
	$awb_data->caurier 		= $response->data->courier_name;
	$awb_data->courier_id 	= $response->data->courier_id;
	$awb_data->awb 			= $response->data->awb_number;
	$awb_data->tp_company 	= 'NB';
	$awb_data->label 		= $response->data->label;

	return es_insert_order_data_db($awb_data);
}


function nimbuspost_show_single_rate_popup($order_ID) {
	$shipping_responce = es_nimuspost_get_rate($order_ID, es_order_weight($order_ID));
	$shipping_rate = json_decode($shipping_responce, true)
		?>
<div class="eashyship-popup-body">
	<header class="eashyship-popup-header">
		<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
		<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
	</header>
	<article class="eashyship-popup-article">
		<a href="https://ship.nimbuspost.com/dash" target="_blank"> <p class="eashyship-wallet">NimbusPost Recharge</p></a>
		<?php echo es_product_tabel($order_ID); ?>		<br>
		<div class="eashyship-shipping-price">
			<table class="eashyship-table eashyship-shipping-table">
				<tr>
					<th>ID</th>
					<th>Courier Name</th>
					<th>Forward</th>
					<th>COD</th>
					<th>Total</th>
				</tr>
				<form id="es_create_single_shipment">
					<?php 
						if (strpos($shipping_responce, 'Error') !== false) { 
							exit( $shipping_responce );
						}
						// Define a comparison function to sort the array
						function compareByTotalCharges($a, $b) {
						return $a['total_charges'] - $b['total_charges'];
						}
						usort($shipping_rate['data'], 'compareByTotalCharges');
						foreach ($shipping_rate['data'] as $carrier) : ?>
					<tr>
						<td><input type="radio" id="shipping_id" name="shipping_id" value="'<?php echo $carrier['id'] ?>'"></td>
						<td><?php echo $carrier['name']; ?></td>
						<td><?php echo '₹'.$carrier['freight_charges']; ?></td>
						<td><?php echo '₹'.$carrier['cod_charges']; ?></td>
						<td><?php echo '₹'.$carrier['total_charges']; ?></td>
					</tr>
					<?php endforeach; ?>
					<input type="hidden" id="OrderId" name="OrderId" value="'<?php echo $order_ID ?>'">
					<input type="hidden" id="ship_by" name="ship_by" value="NP">
				</form>	
			</table>
			<div id="richList"></div>
			<div id="loader" class="lds-dual-ring hidden overlay"></div>
		</div>
	</article>
	<footer class="eashyship-popup-footer">
		<button class="eashyship-close-js eashyship-close-footer">Close</button>
		<button class="eashyship-submit-btn" type="submit" form="es_create_single_shipment">Ship Now</button>
	</footer>
</div>
<?php
}

function nimbuspost_show_bulk_rate_popup($order_IDs) {
	?>
<div class="eashyship-popup-body">

<header class="eashyship-popup-header">
	<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
	<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
</header>
<article class="eashyship-popup-article">
	<a href="https://app.shiprocket.in/dashboard/" target="_blank"> <p class="eashyship-wallet">Shiprocket( ₹<?php echo shiprocket_wallet_ballence() ?> )</p></a>
	<div class="eashyship-product-disc">
		<form id="es_create_bulk_shipment">
			<?php echo es_bulk_template($order_IDs, 'NPB'); ?>
			<input type="hidden" id="ship_by" name="ship_by" value="NPB">
		</form>
	</div>
</article>
<footer class="eashyship-popup-footer">
	<button class="eashyship-close-js eashyship-close-footer">Close</button>
	<button class="eashyship-submit-btn" type="submit" form="es_create_bulk_shipment">Ship Now</button>
</footer>
</div>
<?php
}

function es_nimbuspost_bulk_ratelist($order_ID){
	$shipping_responce = es_nimuspost_get_rate($order_ID, es_order_weight($order_ID));
	$shipping_rate = json_decode($shipping_responce, true);
	?>
		<select id="shipping_id" name="shipping_id[]" >
			<?php 
				// Define a comparison function to sort the array
				// function compareByTotalChargess($a, $b) {
				// 	return $a['total_charges'] - $b['total_charges'];
				// }
				// usort($shipping_rate['data'], 'compareByTotalChargess');
				foreach ($shipping_rate['data'] as $carrier) : ?>
					<option value="<?php  echo $carrier['id']; ?>">
						<?php echo '₹' . intval($carrier['total_charges']) . ' - ' .$carrier['name'] ?>
					</option>
			<?php endforeach; ?>
		</select>
	<?php
}

function es_prepare_data_nimbusPost($order_ID, $ship_by){

	$order 				= wc_get_order( $order_ID );
	$order_date_created = $order->get_date_created();
	$order_date 		= date('Y-m-d H:i', strtotime($order_date_created));
	$product_weight	   	= es_order_weight($order_ID);
	$product_dimensions = es_get_product_dimensions($order_ID);
	$item_data 			= es_nimbusPost_get_items($order_ID);
	$payment_mode 		= es_check_payment_mode($order->get_payment_method_title(),'NP');
	$order_items 		= json_decode($item_data);

	$order_data = array(
					"order_number" 			=> $order_ID,
					"shipping_charges" 		=> $order->get_shipping_total(),
					"discount" 				=> $order->get_discount_total(),
					"cod_charges" 			=> 0,
					"payment_type" 			=> $payment_mode,
					"order_amount" 			=> $order->get_total(),
					"package_weight" 		=> $product_weight,   
					"package_length"  		=> $product_dimensions['length'],
					"package_breadth" 		=> $product_dimensions['width'],
					"package_height"  		=> $product_dimensions['height'],
					"request_auto_pickup" 	=> "yes", // no for not auto request
					"courier_id" 			=> $ship_by,
					"consignee" 			=> array(
												"name" 		=> $order->get_billing_first_name().' '.$order->get_billing_last_name(),
												"address" 	=> $order->get_billing_address_1(),
												"address_2" => $order->get_billing_address_2(),
												"city" 		=> $order->get_billing_city(),
												"state" 	=> $order->get_billing_state(),
												"pincode" 	=> $order->get_billing_postcode(),
												"phone" 	=> extract_phone_number($order->get_billing_phone()),
												),
					"pickup" 				=> array(
												"warehouse_name" => get_option( 'nimbusPost_warehouse_name' ),
												"name" 			 => get_option( 'nimbusPost_name' ),
												"address"		 => get_option( 'nimbusPost_address' ),
												"address_2"		 => get_option( 'nimbusPost_address_2' ),
												"city"			 => get_option( 'nimbusPost_city' ),
												"state"			 => get_option( 'nimbusPost_state' ),
												"pincode"		 => get_option( 'nimbusPost_pincode' ),
												"phone"			 => get_option( 'nimbusPost_phone' ),
												"gst_umber"      => get_option( 'nimbusPost_gst_umber' ),
												),							
					"order_items" 			=> $order_items,
				);
	return json_encode($order_data, true);
}
function es_nimbusPost_create_shipments($order_ID, $ship_by){

	$url   = 'https://api.nimbuspost.com/v1/shipments';
	$shipment_data = es_prepare_data_nimbusPost($order_ID, $ship_by);
	$response = es_wp_post_request_nimbuspost($url, $shipment_data);
	return es_nimbusPost_created_shipment_response($order_ID, $response);
}

function es_nimbusPost_created_shipment_response($order_ID, $response){
	$response_json 	= json_decode($response);
	$status 		= $response_json->status;
	if(!$status){
		return 'Error :- '. $response;
	}
	$db_responce = nimbusPost_insert_data_db($order_ID, $response_json);
	if($db_responce == 'success'){
		$order 				= wc_get_order( $order_ID );
		$order_massage = 'https://aramarket.in/tracking/?order-id='.$order_ID;
		$selected_status_wc = get_option('after_ship_status');
		$selected_status 	= str_replace('wc-', '', $selected_status_wc);
		$order->update_status( $selected_status, 'EasyShip Change -');
		$order->add_order_note('Tracking Link - <a target="_blank" href="'.$order_massage.'">' .$order_massage. '</a>', true);
		$order->save();
		return 'shipped';
	}else{
		return $db_responce;
	}
}	

?>