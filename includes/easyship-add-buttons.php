<?php



// Add cod and prepaid to order_total column
add_action( 'manage_shop_order_posts_custom_column', 'add_cod_to_price_table', 11, 2 );
function add_cod_to_price_table( $column, $order_ID ) {
	$order        = wc_get_order( $order_ID );
	$payment_mode = es_check_payment_mode( $order->get_payment_method_title(), 'ES' );
	if ( $column == 'order_total' ) {
		$button_text = '(' . $payment_mode . ')';
		echo $button_text;
	}
}



// Add Ship Order column to the orders table
add_filter( 'manage_edit-shop_order_columns', 'add_ship_order_column_to_orders_table' );
function add_ship_order_column_to_orders_table( $columns ) {
    // Add custom column to the end of the columns array
    $columns['ship_order'] = __( 'Ship Order', 'textdomain' );
    return $columns;
}

// Add Ship Now and download label button to each column in the orders table
add_action( 'manage_shop_order_posts_custom_column', 'add_ship_now_to_orders_table', 10, 2 );
function add_ship_now_to_orders_table( $column, $order_ID ) {
	if(get_option('plugin_status') == 'Activated'){
		$order = wc_get_order( $order_ID );
		$orderstatus = 'wc-'.$order->get_status();
		if($orderstatus === get_option( 'before_ship_status' )){
			// Check if current column is the actions column
			if ( $column == 'ship_order' ) {
				if(get_option( 'shiprocket_enable' )){
		echo '<button class="button ship-order-button" ship-by="SR" data-order-id="' . $order_ID . '">Ship-Shiprocket</button>';
				}if(get_option( 'delhivery_enable' )){
		echo '<button class="button ship-order-button" ship-by="DL" data-order-id="' . $order_ID . '">Ship-Delhivery</button>';
				}if(get_option( 'nimbuspost_enable' )){
		echo '<button class="button ship-order-button" ship-by="NP" data-order-id="' . $order_ID . '">Ship-NimbusPost</button>';
				}
			}
		}elseif($orderstatus === get_option( 'after_ship_status' )){
			// Check if current column is the actions column
			if (( $column == 'ship_order' ) && ((read_db_data($order_ID, 'label')) != 'NA')) {
				// Define the button text and URL
				$button_text = __( 'Print label', 'textdomain' );
				$button_url = esc_url( read_db_data($order_ID,'label') );
				// Output the button HTML
				printf( '<a href="%s" class="button">%s</a>', $button_url, $button_text );
			}elseif ( $column == 'ship_order' ) {
				// Define the button text and URL
				$button_text = __( 'No Label Found', 'textdomain' );
				echo $button_text;
			}
		}
	}else{
		if ($column == 'ship_order') {
			$button_text = __( 'Plugin Not activated', 'textdomain' );
			echo $button_text;
		}
	}
}


// Add Shipping Details column to wp-orders
add_filter( 'manage_edit-shop_order_columns', 'add_awb_number_column_to_orders_table' );
function add_awb_number_column_to_orders_table( $columns ) {

    // Add custom column to the end of the columns array
    $columns['awb_number'] = __( 'Shipping Details', 'textdomain' );
    return $columns;
}
function es_is_valid_license() {
	$domain = parse_url(get_site_url(), PHP_URL_HOST);
    $data = array(
		'domain' => $domain,
		'purchase_token' => get_option( 'purchase_token' )
    );
    $response = wp_remote_post('https://easy-ship.in/wp-json/es-token-auth/v1/token/', array(
        'body' => $data,
    ));
    if (is_wp_error($response)) {
        return FALSE;
    }
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

add_action( 'init', 'handle_plugin_activation' );
function handle_plugin_activation() {
    if ( isset( $_POST['es_activate_plugin'] ) ) {
		if(!empty(get_option( 'purchase_token' ))){
			$respomce_api = es_is_valid_license();
			if(!empty($respomce_api)){
				if($respomce_api['message'] == 'Token verify successfully'){
					update_option('plugin_status', 'Activated');
					add_settings_error('es_button_notice', 'es_button_error', $respomce_api['message'], 'updated');
				}else{
					add_settings_error('es_button_notice', 'es_button_error', $respomce_api['message'], 'error');
				}
			}else{
				add_settings_error('es_button_notice', 'es_button_error', 'Error Duering Api Call', 'error');
			}
		}else{
			delete_option('plugin_status');
			add_settings_error('es_button_notice', 'es_button_error', 'Purchase Token can not be Empty', 'error');
		}
    }
}

add_action('admin_notices', 'display_warning_notice');
function display_warning_notice() {
    settings_errors('es_button_notice');
}

// Show awb & shiping company details to wp-orders
add_action( 'manage_shop_order_posts_custom_column', 'add_track_now_to_orders_table', 10, 2 );
function add_track_now_to_orders_table( $column, $order_ID ) {
 
	// Check if current column is the actions column
	if ( $column == 'awb_number' ) {
		echo '<a target="_blank" href="'.get_permalink(get_option( 'selected_page' )).'?order-id='.$order_ID.'">' .read_db_data($order_ID,'awb_number').' - '.read_db_data($order_ID,'shipped_through').'-' .read_db_data($order_ID,'courier_name').'</a></br>';
		echo 'Date - '.read_db_data($order_ID,'date_created');
	}
}

// Show Tracking Button to Custer My Order Section
add_filter( 'woocommerce_my_account_my_orders_actions', 'add_tracking_button_ui', 10, 2 );
function add_tracking_button_ui( $actions, $order) {
	$order = wc_get_order( $order->ID );
	if(!($order->get_status() == 'cancelled')){
		// Check if tracking information is available
		if((read_db_data($order->ID,'awb_number')) != 'No Result') {
			$button_text = __( 'Track Order', 'textdomain' );
			$button_url = esc_url( add_query_arg( 'order-id', $order->ID, get_permalink(get_option( 'selected_page' )) ) );
			$new_action = array( 'custom_button' => array(
				'url'  => $button_url,
				'name' => $button_text
			) );
			$actions = array_merge( $new_action, $actions );
		}else {
			$button_text = __( 'Cancel Order', 'textdomain' );
			$button_url = esc_url( add_query_arg( 'cancel-order-id', $order->ID, ) );
			$new_action = array( 'custom_button' => array(
				'url'  => $button_url,
				'name' => $button_text
			) );
			$actions = array_merge( $new_action, $actions );
		}
	}
    return $actions;
}

add_action( 'init', 'handle_cancel_order_action' );
function handle_cancel_order_action() {
    if ( isset( $_GET['cancel-order-id'] ) ) {
        $order_id = absint( $_GET['cancel-order-id'] );
        $order = wc_get_order( $order_id );
        if ( $order ) {
			$order->add_order_note('Custmer Request to Cancel Order', true);
            $order->update_status( 'cancelled' );
			$order->save();
        }
    }
}

// Add custom bulk action name
add_filter( 'bulk_actions-edit-shop_order', 'es_print_bulk_label' );
function es_print_bulk_label( $actions ) {
    $actions['es_bulk_label_print'] = __( 'Bulk Print Label', 'textdomain' );
    return $actions;
}

// Handle custom bulk action
add_filter( 'handle_bulk_actions-edit-shop_order', 'es_handle_bulk_label', 10, 3 );
add_filter( 'handle_network_bulk_actions-edit-shop_order', 'es_handle_bulk_label', 10, 3 );
function es_handle_bulk_label( $redirect_to, $action, $post_ids ) {
    if ( $action === 'es_bulk_label_print' ) {
		$labelsPaths = es_extract_db_url($post_ids);
		$outputFilePath = __DIR__ . '/output.pdf';
        es_mergePDF($labelsPaths, $outputFilePath);
		exit;
    }
    return $redirect_to;
}

function es_extract_db_url($order_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'easyship_db';
    $placeholders = implode(', ', array_fill(0, count($order_ids), '%d'));
    $query = $wpdb->prepare("SELECT label FROM $table_name WHERE order_number IN ($placeholders)", $order_ids);
    $results = $wpdb->get_col($query);
	$cleaned_results = array_map('stripslashes', $results);
    return $results;
}




?>