<?php

// Add custom field to product general settings
add_action( 'woocommerce_product_options_pricing', 'add_cogs_field' );
function add_cogs_field() {
    global $product_object;

    woocommerce_wp_text_input(
        array(
            'id' => '_cogs',
            'label' => __( 'COGS', 'text-domain' ),
            'desc_tip' => true,
            'description' => __( 'Enter the Cost of Goods Sold', 'text-domain' ),
            'value' => $product_object->get_meta( '_cogs', true ),
            'type' => 'text',
        )
    );
}
// Save custom field data
add_action( 'woocommerce_process_product_meta', 'save_cogs_field' );
function save_cogs_field( $product_id ) {
    $cogs = $_POST['_cogs'];
    if ( ! empty( $cogs ) ) {
        update_post_meta( $product_id, '_cogs', sanitize_text_field( $cogs ) );
    }
}

//start update status within portal
add_action('wp', 'es_check_status_schedule_daily');
function es_check_status_schedule_daily() {
    if (!wp_next_scheduled('es_hook_for_status_within_portal')) {
        wp_schedule_event(time(), 'twice_daily', 'es_hook_for_status_within_portal');
    }
}

add_action('es_hook_for_status_within_portal', 'es_function_for_update_status_within_portal');
function es_function_for_update_status_within_portal() {
	    file_put_contents(ABSPATH . 'wp-content/my_custom_cron_log.txt', 'Cron job update stutus within executed at ' . current_time('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array('wc-pending-pickup', 'wc-intransit'),
		'posts_per_page' => -1,
	);
	$orders = new WP_Query($args);
	$order_IDs = wp_list_pluck($orders->posts, 'ID');
	foreach ($order_IDs as $order_ID) {
		$order = wc_get_order( $order_ID );	
		$order_status = $order->get_status(); 
		$status = es_check_api_status($order_ID);
		if($status != 'NA'){
			if(!($order_status == $status)) {
				$order->update_status( $status, 'EasyShip API Change -');
				$order->save();
			}
		}
	}
}

function es_check_api_status($order_ID) {
	read_db_data($order_ID, 'shipped_through');
	$shipped_through = read_db_data($order_ID, 'shipped_through');
	if($shipped_through == 'DL'){ //Delhivery
		$delhivery_response = delhivery_tracking_api($order_ID);
		$status  = $delhivery_response->ShipmentData[0]->Shipment->Status->Status;
	}elseif($shipped_through == 'NB'){ //NimbusPost
		$nimbusPost_response = nimbusPost_tracking_api($order_ID);
		if (is_string($nimbusPost_response)) { 
			$shipment = $nimbusPost_response;
		}else{
			$status  = $nimbusPost_response->data->status;
		}
	}elseif($shipped_through == 'SR'){ //Shiprocket
		$shiprocket_response = shiprocket_tracking_api($order_ID);
		if (is_string($shiprocket_response)) { 
			$shipment = $shiprocket_response;
		}else{
			$status  = $shiprocket_response->tracking_data->shipment_track[0]->current_status;
		}
	}
	$cancelled 		= array("cancelled");
	$pending_pickup = array("Pickup Generated", "Manifested", "booked", "pending pickup", "Label Generated");
	$in_Transit 	= array("In Transit", "in transit", "Out For Delivery", "out for delivery", "Dispatched", "Pending");
	$delivered 		= array("Delivered", "delivered", "orange");
	$return 		= array("RTO", "return");
	
	if(in_array($status, $pending_pickup))		{ $tracking_status = 'pending-pickup'; }
	else if(in_array($status, $in_Transit))		{ $tracking_status = 'intransit'; }
	else if(in_array($status, $delivered))		{ $tracking_status = 'completed'; }
	else if(in_array($status, $return))			{ $tracking_status = 'returnintransit'; }
	else    									{ $tracking_status = 'NA'; }
	return $tracking_status;
}

//this code google merchant center checkout but where remove 'gla_' and get product id
// Get the current URL
$current_url = $_SERVER['REQUEST_URI'];

// Check if the URL matches the specific pattern
if (strpos($current_url, '/cart/?add-to-cart=') !== false) {
    // Check if 'gla_' is present in the URL
    if (strpos($current_url, 'gla_') !== false) {
        // Remove the 'gla_' part from the URL
        $new_url = str_replace('gla_', '', $current_url);
        
        // Redirect to the new URL
        header('Location: ' . $new_url);
        exit();
    } else {
        // 'gla_' is not present, do nothing
    }
} else {
    // URL doesn't match the specific pattern, do nothing
}




// // Register REST API endpoint to get product reviews in XML format for Google Merchant Center
add_action( 'rest_api_init', 'register_product_reviews_xml_endpoint' );
function register_product_reviews_xml_endpoint() {
    register_rest_route( 'flutter-app/v1', '/product-reviews-xml/', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'get_product_reviews_xml_endpoint',
    ) );
}

// Custom endpoint to retrieve product reviews in XML format for Google Merchant Center
function get_product_reviews_xml_endpoint( $request ) {
   // Fetch product reviews from your database or another source
    $reviews = get_product_reviews(); // You need to implement this function
//     return new WP_REST_Response( $reviews, 200 );

	
    // Check if reviews exist
    if ( empty( $reviews ) ) {
        return new WP_Error( 'no_reviews', 'No reviews found', array( 'status' => 404 ) );
    }
	
    // Initialize XML string
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
	$xml .= '<feed xmlns:vc="http://www.w3.org/2007/XMLSchema-versioning" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.google.com/shopping/reviews/schema/product/2.3/product_reviews.xsd">';
    $xml .= '<version>2.3</version>';
	$xml .= '<publisher><name>ARAMARKET</name><favicon>https://aramarket.in/wp-content/uploads/cropped-4.png</favicon></publisher>';
	$xml .= '<reviews>';

    // Loop through product reviews and add them to XML
    foreach ( $reviews as $review ) {
        $xml .= '<review>';
        $xml .= '<review_id>' . htmlspecialchars( $review['id'], ENT_XML1, 'UTF-8' ) . '</review_id>';
        $xml .= '<reviewer><name>' . htmlspecialchars( $review['reviewer'], ENT_XML1, 'UTF-8' ) . '</name></reviewer>';
		$xml .= '<review_timestamp>' . htmlspecialchars( $review['date'], ENT_XML1, 'UTF-8' ) . '</review_timestamp>';
		$xml .= '<content>' . htmlspecialchars( $review['comment'], ENT_XML1, 'UTF-8' ) . '</content>';
		$xml .= '<review_url type="singleton">' . htmlspecialchars( $review['review_url'], ENT_XML1, 'UTF-8' ) . '</review_url>';
		$xml .= '<ratings> <overall min="1" max="5">'. htmlspecialchars( $review['rating'], ENT_XML1, 'UTF-8' ) . '</overall> </ratings>';
		$xml .= '<products><product><product_url>'. htmlspecialchars( $review['product_url'], ENT_XML1, 'UTF-8' ) . '</product_url></product></products>';
        $xml .= '</review>';
    }

    $xml .= '</reviews>';
	$xml .= '</feed>';
    // Set headers to return XML content
    $response = new WP_REST_Response( $xml, 200 );
    $response->header( 'Content-Type', 'application/xml; charset=utf-8' );
	
	
	// Echo the XML directly
    echo $xml;

    // Return a response with the appropriate status
    return new WP_REST_Response( null, 200 );
}

function get_product_reviews() {
    // Arguments for get_comments() to fetch WooCommerce reviews
    $args = array(
        'post_type' => 'product',
        'status'    => 'approve',
        'number'    => 0, // Retrieve all reviews (you can set a specific number)
    );

    // Get the reviews
    $comments = get_comments($args);

    // Prepare reviews in JSON format
    $reviews_json = array();
	
    foreach ($comments as $comment) {
        $reviews_json[] = array(
            'id' => $comment->comment_ID,
            'reviewer' => $comment->comment_author,
            'date' => $comment->comment_date,
            'comment' => $comment->comment_content,
            'review_url' => get_comment_link($comment),
            'rating' => get_comment_meta($comment->comment_ID, 'rating', true),
            'product_url' => get_permalink($comment->comment_post_ID)
        );
    }

    return $reviews_json;
}


?>