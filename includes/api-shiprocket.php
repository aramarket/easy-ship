<?php

// woocommerce pincode should be filled

function es_wp_get_request_shiprocket($url){
	$token = es_shiprocket_genrate_token();
	if (strpos($token, 'Error') !== false) { 
		return 'Error token: '.$token;
	}
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer '.$token,
	);
	$response = wp_remote_get($url, array(
		'headers' => $header_data,
		'timeout' => 10, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error api SR: '. $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return $body;
}

function es_wp_post_request_shiprocket($url, $body){
	$token = es_shiprocket_genrate_token();
	if (strpos($token, 'Error') !== false) { 
		// echo '<script>alert("Error in shiproket token generation: ' . $token . '");</script>';
		return 'Error token: '.$token;
	}
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer '.$token
	);
	$response = wp_remote_post($url, array(
		'headers' => $header_data,
		'body'    => $body,
		'timeout' => 10, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error api SR: '. $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return $body;
}
// Genrate token
function es_shiprocket_genrate_token(){
	$transient_name = 'es_shiprocket_token';
	$token = get_transient($transient_name);
	if (false === $token) {
		$url = 'https://apiv2.shiprocket.in/v1/external/auth/login';
		$username = get_option( 'shiprocket_username' );
		$password = get_option( 'shiprocket_password' );
		
		$body = json_encode(array(
			"email"    => $username,
			"password" => $password
		), true);
		$header_data = array(
			'Content-Type'  => 'application/json',
		);
		$response = wp_remote_post($url, array(
			'headers' => $header_data,
			'body'    => $body,
			'timeout' => 10, // Increase the timeout to 20 seconds
		));
		if (is_wp_error($response)) {
			return 'Error: '. $response->get_error_message();
		}
		$res_body = wp_remote_retrieve_body($response);
		$token_data = json_decode($res_body);
		if ($token_data && property_exists($token_data, 'token')) {
			$token = $token_data->token;
			// Storing the token using Transients API
			$expiration = 9 * DAY_IN_SECONDS; // Set expiration time as 10 days (in seconds)
			set_transient($transient_name, $token, $expiration);
		} else {
			return 'Error -'. $token_data->message;
		}
	}
	return $token;
}

function shiprocket_tracking_api($order_ID){
	$awb = read_db_data($order_ID,'awb_number');
	$url   = 'https://apiv2.shiprocket.in/v1/external/courier/track/awb/'.$awb;
	$response = es_wp_get_request_shiprocket($url);
	$response_json = json_decode($response);
	if(empty($response_json->tracking_data->track_status)){
		return 'Error -'. $response_json->tracking_data->error;
	}
	return $response_json;
}

function es_shiprocket_get_rate($order_ID, $order_weight){

	$order 				= wc_get_order( $order_ID );	
	$pickup_postcod 	= WC()->countries->get_base_postcode();
	$delivery_postcode 	= $order->get_billing_postcode();
	$payment_mode 		= intval(es_check_payment_mode($order->get_payment_method_title(), 'SR1'));
	$weight 			= $order_weight/1000; //weight should be in kgs
	$cod_value  		= $order->get_total();
	$mode 				= 'Surface';  //Surface or Air
	
	$url   = 'https://apiv2.shiprocket.in/v1/external/courier/serviceability/?pickup_postcode='.$pickup_postcod.'&delivery_postcode='.$delivery_postcode. '&cod='.$payment_mode.'&weight='.$weight.'&declared_value='.$cod_value;
	
	$response = es_wp_get_request_shiprocket($url);
	$response_decode = json_decode($response, true);
	if(!($response_decode['status'] == 200)){
		return 'Error : '.$response;
	}
	return $response;
}
function shiprocket_wallet_ballence(){
	$url   = 'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance';
	$response = es_wp_get_request_shiprocket($url);
	$responsejson = json_decode($response);
	return $responsejson->data->balance_amount;
}

function shiprocket_generate_label($shipment_id){
	$url   = 'https://apiv2.shiprocket.in/v1/external/courier/generate/label';
	$body = json_encode(array(
		"shipment_id" => [$shipment_id],
	), true);
	$response = es_wp_post_request_shiprocket($url, $body);
	$response_json = json_decode($response);
	if(empty($response_json->label_created)){
		return 'Error : label - '.json_encode($response_json);
	}
	$removebackslash = stripslashes($response_json->label_url);
	return $removebackslash;
}

function shiprocket_generate_AWB($shipment_id, $ship_by){
	$url   = 'https://apiv2.shiprocket.in/v1/external/courier/assign/awb';
	$body = json_encode(array(
		"shipment_id" => $shipment_id,
		"courier_id"  => $ship_by,
	), true);
	$response = es_wp_post_request_shiprocket($url, $body);
	$res_json = json_decode($response, true);
	if(empty($res_json['awb_assign_status'])){
		return 'Error : awb - '.json_encode($response);
	}
	return $res_json;
}
function es_shiprocket_get_items($order_ID){
	$order = wc_get_order( $order_ID );
	$items = $order->get_items();
	$order_items = array();
	foreach ( $items as $item ) {
		$product = $item->get_product();
		$product_name = $item->get_name();
		$product_quantity = $item->get_quantity();
		$product_total = $item->get_total();
		$product_sku = $product->get_sku();
		if(empty($product_sku)){ $product_sku = mt_rand(100000, 999999); }
		$order_item = array(
			"name" 			=> $product_name,
			"sku" 			=> $product_sku,
			"units" 		=> $product_quantity,
			"selling_price" => $product_total / $product_quantity, //price per item,
			"discount" 		=> "",
			"tax" 			=> "",
			"hsn" 			=> 8240
		);
		$order_items[] = $order_item;
	}
	return $order_items;
}
// Insert/update the awbNumber into wp db table is ets
function shiprocket_insert_data_db($order_ID, $awb_respons, $label_url){

	
	$order 					= wc_get_order( $order_ID );
	$order_weight	   	 	= es_order_weight($order_ID);

	$awb_data 				= new ES_db_data_format();
	$awb_data->order_number = $order_ID;
	$awb_data->order_price 	= $order->get_total();
	$awb_data->order_weight	= $order_weight;
	$awb_data->caurier 		= $awb_respons['response']['data']['courier_name'];
	$awb_data->courier_id 	= $awb_respons['response']['data']['courier_company_id'];
	$awb_data->awb 			= $awb_respons['response']['data']['awb_code'];
	$awb_data->tp_company 	= 'SR';
	$awb_data->label 		= $label_url;

	return es_insert_order_data_db($awb_data);
}
function shiprocket_show_single_rate_popup($order_ID){
	$shipping_responce = es_shiprocket_get_rate($order_ID, es_order_weight($order_ID));
    $shipping_rate = json_decode($shipping_responce, true);
		?>
<div class="eashyship-popup-body">
	<header class="eashyship-popup-header">
		<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
		<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
	</header>
	<article class="eashyship-popup-article">
		<a href="https://app.shiprocket.in/dashboard/" target="_blank"> <p class="eashyship-wallet">Shiprocket( ₹<?php echo shiprocket_wallet_ballence() ?> )</p></a>
		<?php echo es_product_tabel($order_ID); ?>
		<br>
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
						echo '<tr>'.$shipping_responce.'</tr>';
					}
					foreach ($shipping_rate['data']['available_courier_companies'] as $carrier) : ?>
					<tr class="es-shipping-option">
						<td><input type="radio" id="shipping_id" name="shipping_id" value="'<?php echo $carrier['courier_company_id'] ?>'"></td>
						<td><?php echo $carrier['courier_name']; ?></td>
						<td><?php echo '₹'.$carrier['freight_charge']; ?></td>
						<td><?php echo '₹'.$carrier['cod_charges']; ?></td>
						<td><?php echo '₹'.$carrier['rate']; ?></td>
					</tr>
					<?php endforeach; ?>
					<input type="hidden" id="OrderId" name="OrderId" value="'<?php echo $order_ID ?>'">
					<input type="hidden" id="ship_by" name="ship_by" value="SR">
				</form>	
			</table>
		</div>
	</article>
	<footer class="eashyship-popup-footer">
		<button class="eashyship-close-js eashyship-close-footer">Close</button>
		<button class="eashyship-submit-btn" type="submit" form="es_create_single_shipment">Ship Now</button>
	</footer>
</div>
<?php
}

function shiprocket_show_bulk_rate_popup($order_IDs) {
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
			<?php echo es_bulk_template($order_IDs, 'SRB'); ?>
			<input type="hidden" id="ship_by" name="ship_by" value="SR">
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

function es_shiprocket_bulk_ratelist($order_ID){
	$order_weight = es_order_weight($order_ID);
	$shipping_responce = es_shiprocket_get_rate($order_ID, $order_weight);
	$shipping_rate = json_decode($shipping_responce, true);
	?>
		<select id="shipping_id" name="shipping_id[]" >
			<?php foreach ($shipping_rate['data']['available_courier_companies'] as $carrier) : ?>
				<option value="<?php echo $carrier['courier_company_id']; ?>">
					<?php echo '₹' . intval($carrier['rate']) . ' - ' .$carrier['courier_name'] ?>
				</option>
			<?php endforeach; ?>
		</select>
	<?php
}

function es_prepare_data_shiprocket($order_ID){
	
	$order 				= wc_get_order( $order_ID );
	$order_date_created = $order->get_date_created();
	$order_date 		= date('Y-m-d H:i', strtotime($order_date_created));
	
	$order_weight	    = es_order_weight($order_ID);
	$weight 			= $order_weight/1000; //weight should be in kgs	
	$product_dimensions = es_get_product_dimensions($order_ID);
	$item_data 			= es_shiprocket_get_items($order_ID);
	$payment_mode 		= es_check_payment_mode($order->get_payment_method_title(),'SR2');

	$order_data = array(
					"order_id" 					=> $order_ID,
					"order_date" 				=> $order_date,
					"pickup_location" 			=> get_option( 'shiprocket_pickup_location' ),
					"channel_id" 				=> get_option( 'shiprocket_channel_id' ),
					"comment" 					=> "",
					"billing_customer_name"		=> $order->get_billing_first_name(),
					"billing_last_name"			=> $order->get_billing_last_name(),
					"billing_address" 			=> $order->get_billing_address_1(),
					"billing_address_2" 		=> $order->get_billing_address_2(),
					"billing_city" 				=> $order->get_billing_city(),
					"billing_pincode" 			=> $order->get_billing_postcode(),
					"billing_state" 			=> $order->get_billing_state(),
					"billing_country" 			=> "India",
					"billing_email" 			=> $order->get_billing_email(),
					"billing_phone" 			=> $order->get_billing_phone(),
					"shipping_is_billing" 		=> true,
					"shipping_customer_name" 	=> "",
					"shipping_last_name" 		=> "",
					"shipping_address" 			=> "",
					"shipping_address_2" 		=> "",
					"shipping_city" 			=> "",
					"shipping_pincode" 			=> "",
					"shipping_country" 			=> "",
					"shipping_state" 			=> "",
					"shipping_email" 			=> "",
					"shipping_phone" 			=> "",
					"order_items" 				=> $item_data,
					"payment_method" 			=> $payment_mode,
					"shipping_charges" 			=> 0,
					"giftwrap_charges" 			=> 0,
					"transaction_charges" 		=> 0,
					"total_discount" 			=> $order->get_discount_total(),
					"sub_total" 				=> $order->get_total(),
					"length" 					=> $product_dimensions['length'],
					"breadth" 					=> $product_dimensions['width'],
					"height" 					=> $product_dimensions['height'],
					"weight" 					=> $weight
				);
	return json_encode($order_data, true);
}
function es_shiprocket_create_shipments($order_ID, $ship_by){
	$url   = 'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc';	
	$shipment_data = es_prepare_data_shiprocket($order_ID);
	$response = es_wp_post_request_shiprocket($url, $shipment_data);
	return es_shiprocket_created_shipment_response($order_ID, $response, $ship_by);
}

function es_shiprocket_created_shipment_response($order_ID, $response, $ship_by){
	$response_json = json_decode($response);
	$shipment_id = $response_json->shipment_id;
	if(!$shipment_id){
		return 'Error : '. json_encode($response_json);
	}
	$awb_respons = shiprocket_generate_AWB($shipment_id, $ship_by);
	if (!(is_array($awb_respons))) { 
		return $awb_respons;
	}
	$awb = $awb_respons['response']['data']['awb_code'];
	$label_url  = shiprocket_generate_label($shipment_id);
	if (strpos($label_url, 'Error') !== false) { 
		return $label_url;
	}
	$db_responce = shiprocket_insert_data_db($order_ID, $awb_respons, $label_url);
	if($db_responce == 'success'){
		$order = wc_get_order( $order_ID );
		// Change order status to "completed" and save
		$order_massage = 'https://aramarket.in/tracking/?order-id='.$order_ID;
		$direct_massage = 'https://shiprocket.co/tracking/'.$awb;
		$selected_status_wc = get_option('after_ship_status');
		$selected_status = str_replace('wc-', '', $selected_status_wc);
		$order->update_status( $selected_status, 'EasyShip Change -');
		$order->add_order_note('Tracking Link - <a target="_blank" href="'.$order_massage.'">'. $order_massage. '</a>',true);
		$order->add_order_note('Direct Link - <a target="_blank" href="'.$direct_massage.'">' .$direct_massage. '</a>');
		$order->save();
		return 'shipped';
	}else{
		return $db_responce;
	}
}

?>