<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('AramarketCustom')) {
    class AramarketCustom {
        
		private $orderTracking;

        public function __construct() {
			$this->orderTracking = new ESOrderTracking();
            // You can add your hooks here
			add_action('wp', [$this, 'checkStatusScheduleDaily']);
			add_action('easyship_hook_auto_order_status_update', [$this, 'autoCheckAndUpdateOrderStatus']);
			
            add_action('template_redirect', array($this, 'check_and_redirect_url'));
        }

		//start update status within portal
		public function checkStatusScheduleDaily() {
			if (!wp_next_scheduled('es_hook_for_status_within_portal')) {
				wp_schedule_event(time(), 'twice_daily', 'easyship_hook_auto_order_status_update');
// 				wp_schedule_event(time(), 'daily', 'easyship_hook_auto_order_status_update');
			}
		}
		
		public function autoCheckAndUpdateOrderStatus() {
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
// 				$oldStatus = $this->orderTracking->getDeliveryStatusCode($order_status); //vs given below functions

				if($order_status == 'pending-pickup'){
					$oldStatus = 2;  // 2 for pending pickup
				} else if($order_status == 'intransit'){
					$oldStatus = 3; // 3 for intransit
				} else {
					$oldStatus = 1; // 1 do nothing
				}
				$newStatus = $this->orderTracking->es_tracking_page($order_ID, true);
				if($newStatus != 1){
					if($oldStatus != $newStatus) {
						switch ($newStatus) {
// 							case 0: // No need for cancelled order as of now
// 								$order->update_status( 'cancelled', 'ES: ');
// 								$order->save();
// 								break;
// 							case 2: // No need for pending pickup order as of now
// 								$order->update_status( 'pending-pickup', 'ES: ');
// 								$order->save();
// 								break;
							case 3:
								$order->update_status( 'intransit', 'ES: ');
								$order->save();
								break;
							case 4:
								$order->update_status( 'completed', 'ES: ');
								$order->save();
								break;
							case 5:
								$order->update_status( 'returnintransit', 'ES: ');
								$order->save();
								break;
						}
					}
				}
			}
		}
		
        public function check_and_redirect_url() {
            // Get the current URL
            $current_url = $_SERVER['REQUEST_URI'];

            // Check if the URL matches the specific pattern
            if (strpos($current_url, '/cart/?add-to-cart=') !== false) {
                // Check if 'gla_' is present in the URL
                if (strpos($current_url, 'gla_') !== false) {
                    // Remove the 'gla_' part from the URL
                    $new_url = str_replace('gla_', '', $current_url);

                    // Redirect to the new URL
                    wp_redirect($new_url);
                    exit();
                } else {
                    // 'gla_' is not present, do nothing
                }
            } else {
                // URL doesn't match the specific pattern, do nothing
            }
        }
    }
}
// Instantiate the class
new AramarketCustom();
?>
