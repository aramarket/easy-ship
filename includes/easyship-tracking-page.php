<?php 
// Define the shortcode function
function easyship_shortcode() {
	ob_start();	
// 	echo json_encode(delhivery_tracking_api(17351));
// 	$order = wc_get_order( 15189 );
// 	echo $order->get_payment_method_title();
    wp_enqueue_style('es-tracking-style', EASYSHIP_DIR .'assets/css/es-tracking.css', [], '1.0', 'all', 1);
	if (isset($_GET['order-id'])) {
		$order_ID = htmlspecialchars($_GET['order-id'], ENT_QUOTES, 'UTF-8');
		if (is_wc_order_id_exists($order_ID)) {
			es_tracking_html($order_ID);
		} else {
			$message = "Please Enter Correct Order ID.";
			echo '<script>alert("' . $message . '");</script>';
			$current_url = remove_query_arg(array_keys($_GET), $_SERVER['REQUEST_URI']);
 			$url = site_url().$current_url;
			echo '<script> window.location.href = "' . $url . '";</script>';
		    exit;
		}
	}else {
		echo plase_enter_oderId();
	}
	return ob_get_clean();
}

function plase_enter_oderId(){
?>
	    <div class="easyship-pretrack-body" >
        <i class="fa-regular fa-paper-plane esyship-icon"></i>
        <h2>To track your order please enter your Order ID</h2>
        <form action="<?php get_permalink(get_option( 'selected_page' )) ?>" method="GET" id="es-form">
        <input class="easyship-form-input" type="text" id="order_id" name="order-id" placeholder="Enter Order ID" required>
            <br>
            <button type="submit" form="es-form" class="easyship-order-btn button btn">TRACK YOUR ORDER</button>
        </form>
        <script src="https://kit.fontawesome.com/aaa59cda47.js" crossorigin="anonymous"></script>
    </div>

	<?php
}


function es_tracking_html($order_ID){
// 	wp_enqueue_style('ets-css', EASYSHIP_DIR . '/assets/css/ets-tracking.css');
	$status  = 'Not Shipped'; $date = 'NA'; $courier = 'NA'; $awb = 'NA'; $shipment = 'NA'; $tracking_status = 1;
	$order = wc_get_order($order_ID);
	$items = $order->get_items();
	$order_date_raw = $order->get_date_created();
	$order_date = $order_date_raw->date_i18n('d, F Y');

	$shipped_through = read_db_data($order_ID,'shipped_through');
	if($shipped_through == 'DL'){ //Delhivery
		$delhivery_response = delhivery_tracking_api($order_ID);
		$status  = $delhivery_response->ShipmentData[0]->Shipment->Status->Status;
		$date    = $delhivery_response->ShipmentData[0]->Shipment->PickUpDate;
		$courier = 'Delhivery';
		$awb     = $delhivery_response->ShipmentData[0]->Shipment->AWB;
		$shipment= $delhivery_response->ShipmentData[0]->Shipment->Scans;
		$delivery_date = date('d, M Y', strtotime($delhivery_response->ShipmentData[0]->Shipment->ExpectedDeliveryDate));
	}elseif($shipped_through == 'NB'){ //NimbusPost
		$nimbusPost_response = nimbusPost_tracking_api($order_ID);
		if (is_string($nimbusPost_response)) { 
			$shipment = $nimbusPost_response;
		}else{
			$status  = $nimbusPost_response->data->status;
			$date    = $nimbusPost_response->data->created;
			$courier = read_db_data($order_ID,'courier_name');
			$awb     = $nimbusPost_response->data->awb_number;
			$shipment= $nimbusPost_response->data->history;
			$delivery_date = 1;
		}
	}elseif($shipped_through == 'SR'){ //Shiprocket
		$shiprocket_response = shiprocket_tracking_api($order_ID);
		if (is_string($shiprocket_response)) { 
			$shipment = $shiprocket_response;
		}else{
			$status  = $shiprocket_response->tracking_data->shipment_track[0]->current_status;
			$date    = read_db_data($order_ID,'date_created ');
			$courier = $shiprocket_response->tracking_data->shipment_track[0]->courier_name;
			$awb     = $shiprocket_response->tracking_data->shipment_track[0]->awb_code;
			$shipment= $shiprocket_response->tracking_data->shipment_track_activities;
			$delivery_date = date('d, M Y', strtotime($shiprocket_response->tracking_data->etd));
		}
	}
	if (!is_string($shipment)){ 
		$date = new DateTime($date);
		$date = $date->format('d, F Y');
	}
	$cancelled 		= array("cancelled");
	$pending_pickup = array("Pickup Generated", "Manifested", "booked", "pending pickup", "Label Generated");
	$in_Transit 	= array("In Transit", "in transit", "Out For Delivery", "out for delivery", "Dispatched", "Pending");
	$delivered 		= array("Delivered", "delivered", "orange");
	$return 		= array("RTO", "return");
	
		 if(in_array($status, $cancelled))		{ $tracking_status = 0; }
	else if(in_array($status, $pending_pickup))	{ $tracking_status = 2; }
	else if(in_array($status, $in_Transit))		{ $tracking_status = 3; }
	else if(in_array($status, $delivered))		{ $tracking_status = 4; }
	else if(in_array($status, $return))			{ $tracking_status = 5; }
?>

<div class="easyship-tracking-page">
	<div class="es-tracking-container">
		<section class="es-order-details">
			<!-- Order details  -->
			<header class="es-header es-od-header">
				<p class="es-view-order">Order #<?php echo $order_ID ?></p>
				<p class="es-bold"><span class="header-icon"><i class="fa-solid fa-boxes-stacked"></i></span> Order Details</p>
			</header>
			<article class="es-article es-od-article">
				<p> Place On <span class="es-bold"><?php echo $order_date ?></span></p>
				<table class="eashyship-table">
					<?php foreach ($items as $item) :  
						$product = wc_get_product($item->get_product_id());
					?>
					<tr>
						<td><img class="es-img" alt="img" src="<?php echo wp_get_attachment_url( $product->get_image_id() ); ?>"></td>
						<td><a class="es-anchor" href="<?php echo get_permalink($item->get_product_id()) ?>" target= "_blank" ><?php echo es_make_string_shorter($item->get_name(), 4).'..'; ?></a></td>
						<td><?php echo $item->get_quantity().'x₹'.$item->get_total()/$item->get_quantity(); ?></td>
						<td><?php echo '₹'.$item->get_total(); ?></td>
					</tr>
					<?php endforeach; ?>
					<tr>
						<th></th>
						<th></th>
						<th>Total</th>
						<th><?php echo '₹'.$order->get_total() ?></th>
					</tr>
				</table>
			</article>
			<footer class="es-od-footer">
				<a href="<?php echo wc_get_account_endpoint_url('orders'); ?>"><button type="submit" class="easyship-submit-btn">Back to Orders</button></a>
			</footer>
		</section>
		<div class="es-track-prog">
			<section class="es-track-details">
				<!-- tracking prograssbar -->
				<header class="es-header es-od-header">
					<p class="es-bold"><span class="header-icon"><i class="fa-solid fa-map-location-dot"></i></span> Shipment Status</p>
				</header>
				<article class="es-article es-td-article">
					<p class="es-small">Expected Delivery Date</p>
					<p class="es-big" style="color:#FF5722;"><span class="es-bold"><?php echo $delivery_date ?></span></p>
					<p class="es-big">Shipment Status - <span class="es-bold"><?php echo $status ?></span></p>
					<p class="es-small">by <span class="es-bold"><?php echo $courier ?></span> on <span class="es-bold"><?php echo $date ?></span></p>
					<p class="es-small">AWB #<?php echo $awb ?></p>
					<!-- shipment tracking track -->
					<div class="es-track">
<div class="es-step <?php if ($tracking_status >= 1) { echo 'active'; } else { echo ''; } ?>"><span class="es-icon"><i class="fa fa-check"></i></span><span class="es-text">Booked</span></div>
<div class="es-step <?php if ($tracking_status >= 2) { echo 'active'; } else { echo ''; } ?>"><span class="es-icon"><i class="fa fa-user"></i></span><span class="es-text">Pending Pickup </span></div>
<div class="es-step <?php if ($tracking_status >= 3) { echo 'active'; } else { echo ''; } ?>"><span class="es-icon"><i class="fa fa-truck-fast"></i></span><span class="es-text">In-transit</span></div>
<div class="es-step <?php if ($tracking_status >= 4) { echo 'active'; } else { echo ''; } ?>"><span class="es-icon"> <i class="fa fa-box"></i> </span><span class="es-text">Delivered</span></div>
					</div>
					
				</article>
				<footer>
				</footer>
			</section>
			<section class="es-track-progress">
				<header class="es-header">
					<p class="es-bold"><span class="header-icon"><i class="fa-solid fa-bars-progress"></i></span> Shipment Progress</p>
				</header>
				<article class="es-article es-shipment-progress">
					<ul class="progress-bar">
						<?php
						if($shipped_through == 'DL'){ //Delhivery
							$scans = array_reverse($shipment);
							foreach ($scans as $scan) :
							$scanDetail = $scan->ScanDetail;
							$date_converted = new DateTime($scanDetail->StatusDateTime);
							$formattedDate = $date_converted->format('F d, Y H:i');
						?>
						<li>
							<P>					<?php echo $formattedDate; ?></P>
<P><span class="es-bold"><?php echo $scanDetail->Scan; ?></span><span class="es-small"> (<?php echo $scanDetail->Instructions; ?>)</span></P>
							<P>					<?php echo $scanDetail->ScannedLocation; ?></P>
						</li>
						<?php endforeach; }?>
						
						<?php
						if($shipped_through == 'NB'){ //Nimbuspost
							foreach ($shipment as $scan) : 
							$date_converted = new DateTime($scan->event_time);
							$formattedDate = $date_converted->format('F d, Y H:i');
						?>
						<li>
							<P>					<?php echo $formattedDate; ?></P>
<P><span class="es-bold"><?php echo es_nimbusPost_getStatus($scan->status_code); ?></span><span class="es-small"> (<?php echo $scan->message; ?>)</span></P>
							<P>					<?php echo $scan->location; ?></P>
						</li>
						<?php endforeach; }?>
						
						<?php
						if($shipped_through == 'SR'){ //Shiprokcet
							if (is_string($shipment)){ 
								echo $shipment;
							}else{
								foreach ($shipment as $scan) :
								$date_converted = new DateTime($scan->date);
								$formattedDate = $date_converted->format('F d, Y H:i');
							?>
							<li>
								<P>	<?php echo $formattedDate; ?></P>
	                            <P><span class="es-bold"><?php echo $scan->{'sr-status-label'}; ?></span><span class="es-small"> (<?php echo $scan->activity; ?>)</span></P>
								<P><?php echo $scan->location; ?></P>
							</li>
							<?php endforeach; }}?>
					</ul>
				</article>
				<footer class="es-tracking-footer-logo">
					<div class="es-footer-logo-div">
						<p>Powered By </P>
						<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo-footer" src="<?php echo EASYSHIP_DIR.'/assets/img/easyship.png'?>" alt="easyship"></a>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<script src="https://kit.fontawesome.com/aaa59cda47.js" crossorigin="anonymous"></script>
</div>


<?php
}
?>