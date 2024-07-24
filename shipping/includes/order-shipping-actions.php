<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('ESOrderShippingActions')) {
	class ESOrderShippingActions {
        private $es_function;
		private $delhiveryAPI;
		private $shiprocketAPI;
		private $nimbuspostAPI;
		
        public function __construct() {
            $this->es_function = new ESShippingFunction();
            add_action( 'manage_shop_order_posts_custom_column', array($this, 'add_payment_mode_in_price_table'), 11, 2 );
            add_filter( 'manage_edit-shop_order_columns', array($this, 'add_ship_order_column_to_orders_table'));
            add_action( 'manage_shop_order_posts_custom_column', array($this, 'add_ship_now_button_to_orders_table'), 10, 2 );
            add_filter( 'woocommerce_my_account_my_orders_actions', array($this, 'add_tracking_button_user_side'), 10, 2 );
            add_action( 'init', array($this, 'handle_cancel_order_button'));
			add_action('init', array($this, 'handle_print_label_button'));

			$this->delhiveryAPI = new DelhiveryAPI();
			$this->shiprocketAPI = new ShiprocketAPI();
			$this->nimbuspostAPI = new NimbuspostAPI();
        }

        // Add cod and prepaid label to order_total column
        public function add_payment_mode_in_price_table( $column, $order_ID ) {
            $order = wc_get_order( $order_ID );
            $payment_mode = ESShippingFunction::check_payment_mode( $order->get_payment_method_title());
            if ( $column == 'order_total' ) {
                echo '(' . $payment_mode . ')';
            }
        }

        // Add Ship Order button column to the orders table
        public function add_ship_order_column_to_orders_table( $columns ) {
            // Add custom column to the end of the columns array
            $columns['ship_order'] = __( 'Ship Order', 'textdomain' );
            return $columns;
        }

        // Add Ship Now and download label button to each column in the orders table
        public function add_ship_now_button_to_orders_table( $column, $order_ID ) {
            $order = wc_get_order( $order_ID );
            $status = $order->get_status();
			$before_ship_status = ESCommonFunctions::es_wa_simplify_order_status(get_option( 'before_ship_status' ));
			$after_ship_status = ESCommonFunctions::es_wa_simplify_order_status(get_option( 'after_ship_status' ));
			$active_couriers = ESCommonFunctions::active_courier_list();
			if($status === $before_ship_status) {
				// Check if current column is the actions column
				if ( $column == 'ship_order' ) {
					echo '<div style="display: flex; gap: 10px;">'; // Add this line
					
					// Generate buttons based on the active couriers
					foreach ($active_couriers as $courier) {
						echo '<button class="button ship-order-button" ship-by="'.$courier.'" data-order-id="'.$order_ID.'"> '.$courier.'</button>';
					}
					echo '</div>'; // Add this line
				}
			} elseif($status === $after_ship_status) {
                // Check if current column is the actions column
                if ( $column == 'ship_order' ) {
					    $button_text = __('Print label', 'textdomain');
						?>
						<form action="" method="post" style="display:inline;">
							<input type="hidden" name="order_id" value="<?php echo esc_attr($order_ID); ?>">
							<button type="submit" name="print_label" class="button"><?php echo esc_html($button_text); ?></button>
						</form>
						<?php
                }
            } elseif($tracking_details['success']){
				if ( $column == 'ship_order' ) {
                    $button_text = __( 'Track Now', 'textdomain' );
					$url = ESCommonFunctions::get_tracking_url($order_ID);
                    printf( '<a href="%s" target="_blank" class="button">%s</a>', $url, $button_text );
                }
			}
        }

        // Show Tracking Button to customer side in My Order Section
        public function add_tracking_button_user_side( $actions, $order) {
            //add cancel button
            if ($order->get_status() == 'processing') {
                $button_text = __( 'Cancel Order', 'textdomain' );
                $button_url = esc_url( add_query_arg( 'cancel-order-id', $order->ID, ) );
                $new_action = array( 'custom_button' => array(
                    'url'  => $button_url,
                    'name' => $button_text
                ) );
                $actions = array_merge( $new_action, $actions );
            } elseif(!($order->get_status() == 'cancelled')) {            //Add tracking button
                $button_text = __( 'Track Order', 'textdomain' );
                $tracking_url = ESCommonFunctions::get_tracking_url($order->ID);
                $new_action = array( 'custom_button' => array(
                    'url'  => $tracking_url,
                    'name' => $button_text
                ));
                $actions = array_merge( $new_action, $actions );
            }
            return $actions;
        }


		public function handle_print_label_button() {
			if (isset($_POST['print_label'])) {
				$orderID = sanitize_text_field($_POST['order_id']); //26941, 26933
				
				// Get AWB
				$trackingResponce = ESCommonFunctions::get_tracking_details($orderID);
				if (!$trackingResponce['success']) {
					wc_add_notice($trackingResponce['message'], 'error');
					return;
				}

				$trackingData = $trackingResponce['result'];
				$awb = $trackingData['es_awb_no'];
				$courierName = $trackingData['es_courier_name'];

				switch ($courierName) {
					case 'delhivery':
						$result = $this->delhiveryAPI->generateLabel($awb);
						break;
					case 'shiprocket':
						$result = $this->shiprocketAPI->generateLabel($awb);
						break;
					case 'nimbuspost':
						$result = [
							'success' => false,
							'message' => 'Cant generate label for Nimbuspost',
						];
						break;
					default:
						$result = [
							'success' => false,
							'message' => 'No Courier Match',
						];
				}

				if (!$result['success']) {
					wc_add_notice($result['message'], 'error');
					return;
				}

				$pdfUrl = $result['result'];
				$url = esc_url($pdfUrl);

				// Redirect to the URL to initiate download
				wp_redirect($url);
				exit;
			}
		}
		
        public function handle_cancel_order_button() {
            if ( isset( $_GET['cancel-order-id'] ) ) {
                $order_id = absint( $_GET['cancel-order-id'] );
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->add_order_note('Customer canceled order.');
                    $order->update_status( 'cancelled' );
                    $order->save();
                }
            }
        }

    }
}