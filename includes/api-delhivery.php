<?php
//wordpress get request
function es_wp_get_request_delhivery($url){
	$token = get_option('delhivery_token');
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Token ' . $token
	);
	$response = wp_remote_get($url, array(
		'headers' => $header_data,
		'timeout' => 10, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error: '. $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return $body;
}
// Delhivery get tracking data 
function delhivery_tracking_api($order_ID){
	$awb = read_db_data($order_ID,'awb_number');
	$url   = 'https://track.delhivery.com/api/v1/packages/json/?waybill='.$awb;
	$body = es_wp_get_request_delhivery($url);
	return json_decode($body);
}
// Delhivery get awbs 
function delhivery_fetch_awbs($count) {
	$url   = 'https://track.delhivery.com/waybill/api/bulk/json/?count='.$count;
	$body = es_wp_get_request_delhivery($url);
	return $body;
}
// Delhivery get genrate label
function delhvery_generate_label($awb){
	$url   = 'https://track.delhivery.com/api/p/packing_slip?pdf=true&wbns='.$awb;
	$body = es_wp_get_request_delhivery($url);
	$label = json_decode($body);
	if(!$label->packages_found){
		return 'Error: Wrong awb - '.$awb;
	}
	return $label->packages[0]->pdf_download_link;
}
// Delhivery get shipping rates
function delhivery_get_rate($order_ID, $order_weight){
	$order = wc_get_order( $order_ID );
	$payment_mode = es_check_payment_mode($order->get_payment_method_title(), 'DL');
	$mode 		= 'E';
	$o_pincode 	= WC()->countries->get_base_postcode();
	$d_pincode 	= $order->get_billing_postcode();
	$weight 	= $order_weight;
	$cod_value  = $order->get_total();
	$url = 'https://track.delhivery.com/api/kinko/v1/invoice/charges/.json?md='.$mode.'&ss=Delivered&d_pin='.$d_pincode.'&o_pin='.$o_pincode.'&cgm='.$weight.'&pt='.$payment_mode.'&cod='.$cod_value;
	
	$body = es_wp_get_request_delhivery($url);
	return $body;
}
// function for combine items titel
function es_delhivery_get_items($order_ID){
	$order = wc_get_order( $order_ID );	
	$items = $order->get_items();
	$counter = 0;
	$get_order_title ='';
	foreach ( $items as $item ) {
		$counter++;
		$get_order_title = $get_order_title.$counter.' '.$item->get_product()->get_name().' ';
	}
	$get_order_title = replaceSpecialChars($get_order_title, '');
	return $get_order_title;
}

// Insert/update the awbNumber into wp db table is ets
function delhivery_insert_data_db($order_ID, $s_waybill){
	
	$order 					= wc_get_order( $order_ID );
	$order_weight	   	 	= es_order_weight($order_ID);

	$awb_data 				= new ES_db_data_format();
	$awb_data->order_number = $order_ID;
	$awb_data->order_price 	= $order->get_total();
	$awb_data->order_weight	= $order_weight;
	$awb_data->caurier 		= 'Delhivery';
	$awb_data->courier_id 	= 'NA';
	$awb_data->awb 			= $s_waybill;
	$awb_data->tp_company 	= 'DL';
	$awb_data->label 		= 'NA';

	return es_insert_order_data_db($awb_data);
}
	

function delhivery_show_single_rate_popup($order_ID) {
	$shipping_responce = delhivery_get_rate($order_ID, es_order_weight($order_ID));
    $shipping_rate = json_decode($shipping_responce, true);
	$gst = $shipping_rate['0']['tax_data']['SGST']+$shipping_rate['0']['tax_data']['IGST']+$shipping_rate['0']['tax_data']['CGST'];
	$order_IDs = array($order_ID); 
	?>
<div class="eashyship-popup-body">
	<header class="eashyship-popup-header">
		<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
		<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
	</header>
	<article class="eashyship-popup-article">
		<a href="https://ucp.delhivery.com/home" target="_blank"> <p class="eashyship-wallet">Delhivery Recharge</p></a>
		<?php echo es_product_tabel($order_ID); ?>
		<br>
		<div class="eashyship-shipping-price">
			<table class="eashyship-table eashyship-shipping-table">
				<tr>
					<th>Courier Name</th>
					<th>Forward</th>
					<th>COD</th>
					<th>GST</th>
					<th>Total</th>
				</tr>
				<tr>
					<td>Delhivery</td>
					<td><?php echo '₹'.$shipping_rate[0]['charge_DL']?></td>
					<td><?php echo '₹'.$shipping_rate[0]['charge_COD']?></td>
					<td><?php echo '₹'.$gst ?></td>
					<td><?php echo '₹'.$shipping_rate[0]['total_amount'] ?></td>
				</tr>
			</table>
		</div>
	</article>
	<footer class="eashyship-popup-footer">
		<form id="es_create_single_shipment">
			<?php foreach ($order_IDs as $order_ID): ?>
				<input type="hidden" id="OrderId" name="OrderId" value="<?php echo $order_ID; ?>">
				<input type="hidden" id="ship_by" name="ship_by" value="DL">
			<?php endforeach; ?>
		</form>
		<button class="eashyship-close-js eashyship-close-footer">Close</button>
		<button class="eashyship-submit-btn" type="submit" form="es_create_single_shipment">Ship Now</button>
	</footer>
</div>
<?php
}

function delhivery_show_bulk_rate_popup($order_IDs) {
		?>
<div class="eashyship-popup-body">

<header class="eashyship-popup-header">
		<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
		<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
	</header>
	<article class="eashyship-popup-article">
		<a href="https://ucp.delhivery.com/home" target="_blank"> <p class="eashyship-wallet">Delhivery Recharge</p></a>
		<div class="eashyship-product-disc">
			<form id="es_create_bulk_shipment">
				<?php echo es_bulk_template($order_IDs, 'DLB'); ?>
				<input type="hidden" id="ship_by" name="ship_by" value="DL">
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

function es_delhivery_bulk_ratelist($order_ID){
	$order_weight = es_order_weight($order_ID);
	$shipping_responce = delhivery_get_rate($order_ID, $order_weight);
	$shipping_rate = json_decode($shipping_responce, true);
	?>
		<select id="shipping_id" name="shipping_id[]" >
				<option value="">
					<?php echo '₹' . intval($shipping_rate[0]['total_amount']) . ' - ' . 'Delhivery' ?>
				</option>
		</select>
	<?php
}

function es_prepare_data_delhivery($order_IDs){
	$genrate_awbs_string = delhivery_fetch_awbs(count($order_IDs));
	$awbs = explode(",", $genrate_awbs_string);
	// Remove double quotes from each element in the array
	$awbs = array_map(function ($value) {
		return str_replace('"', '', $value);
	}, $awbs);
	$awb_index = 0;
	$shipment_data = [
		"shipments" => [],
		"pickup_location" => ["name" => get_option( 'delhivery_pickup_location' )]
	];
	foreach ($order_IDs as $order_ID) {	
		$awb = $awbs[$awb_index];
		$awb_index++;
		$order = wc_get_order( $order_ID );
		$order_date_created = $order->get_date_created();
		$order_date = date('Y-m-d H:i', strtotime($order_date_created));
		$payment_mode = es_check_payment_mode($order->get_payment_method_title(), 'DL');
		$item_list = es_delhivery_get_items($order_ID);
		$order_weight = es_order_weight($order_ID);
		$shipping_mode = "Express";
		$product_dimensions = es_get_product_dimensions($order_ID);

		$order_data = 
			array(
				"name" 				=> $order->get_billing_first_name().' '.$order->get_billing_last_name(),
				"add" 				=> replaceSpecialChars($order->get_billing_address_1()).' '.replaceSpecialChars($order->get_billing_address_2()),
				"pin" 				=> $order->get_billing_postcode(),
				"city" 				=> $order->get_billing_city(),
				"state" 			=> $order->get_billing_state(),
				"country" 			=> "India",
				"phone" 			=> extract_phone_number($order->get_billing_phone()),
				"order" 			=> $order_ID,
				"payment_mode" 		=> $payment_mode,
				"return_pin" 		=> "",
				"return_city" 		=> "",
				"return_phone" 		=> "",
				"return_add" 		=> "",
				"return_state" 		=> "",
				"return_country" 	=> "",
				"products_desc" 	=> $item_list,
				"hsn_code" 			=> "",
				"cod_amount" 		=> $order->get_total(),
				"order_date" 		=> $order_date,
				"total_amount" 		=> $order->get_total(),
				"seller_add" 		=> "",
				"seller_name" 		=> "",
				"seller_inv" 		=> "",
				"quantity" 			=> $order->get_item_count(),
				"shipment_length" 	=> $product_dimensions['length'],
				"shipment_width" 	=> $product_dimensions['width'],
				"shipment_height" 	=> $product_dimensions['height'],
				"weight" 			=> $order_weight,
				"seller_gst_tin" 	=> "",
				"shipping_mode" 	=> $shipping_mode,
				"address_type" 		=> "home",
				"waybill" 			=> $awb,
				"source" 			=> "Woocommerce"
			);
		$shipment_data["shipments"][] = $order_data;
	}
	return json_encode($shipment_data, true);
}

function es_delhivery_create_shipments($orderIDs){
	$token = get_option('delhivery_token');
	$url   = 'https://track.delhivery.com/api/cmu/create.json';
	
	// $orderIDs = [1796, 1802];
	$shipment_data = es_prepare_data_delhivery($orderIDs);
	$header_data = array(
		'Content-Type'  => 'application/json',
		'Accept'        => 'application/json',
		'Authorization' => 'Token ' . $token
	);
	$response = wp_remote_post($url, array(
		'headers' 	=> $header_data,
		'body' 		=> 'format=json&data='.$shipment_data,
		'timeout'	=> count($orderIDs)*5, // Increase the timeout to 20 seconds
	));
	if (is_wp_error($response)) {
		return 'Error: While Creating Shipment API-' . $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	return es_hadel_created_shipment_response($body);
}

function es_hadel_created_shipment_response($response){
	$response_json = json_decode($response);
	if(!$response_json->package_count){
		return 'Error: '. json_encode($response_json);
// 		return 'Error: '. json_encode($response_json->packages[0]->remarks);
	}
	$ship_error = [];
	$countrer = 0;
	foreach ($response_json->packages as $package) {
		$order_ID = $package->refnum;
		$awb = $package->waybill;
		$order = wc_get_order( $order_ID );
		if($package->status == "Success"){
			$db_responce = delhivery_insert_data_db($order_ID, $awb);
			if($db_responce == 'success'){
				$countrer ++;
				$order_massage = 'https://aramarket.in/tracking/?order-id='.$order_ID;
				$direct_massage = 'https://www.delhivery.com/track/package/'.$awb;
				$selected_status_wc = get_option('after_ship_status');
				$selected_status = str_replace('wc-', '', $selected_status_wc);
				$order->update_status( $selected_status, 'EasyShip Change -');
				$order->add_order_note('Tracking Link - <a target="_blank" href="'.$order_massage.'">' .$order_massage. '</a>', true); 
				$order->add_order_note('Direct Link - <a target="_blank" href="'.$direct_massage.'">' .$direct_massage. '</a>');
				$order->save();
				if(count($response_json->packages) == 1){
					return 'shipped';
				}
				$ship_error[] = $order_ID.' - shipped';
				continue;
			}else{
				$ship_error[] = $order_ID.' - Error db - '.$db_responce;
				continue;
			}
		}else{		
			$ship_error[] = $order_ID.' - Error - '.json_encode($package->remarks);
			continue;
		}
	}
	if(count($response_json->packages) == 1){
		return json_encode($ship_error);
	}else{
		$total_success = "Shipped ".$countrer." out of ".$response_json->package_count;
		array_unshift( $ship_error, $total_success );
		return $ship_error;
	}
}

?>
