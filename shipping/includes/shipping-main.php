<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('ESOrderShipping')) {
    class ESOrderShipping {
		
		private $popup_template;
		private $delhiveryAPI;
		private $shiprocketAPI;
		private $nimbuspostAPI;
		
        public function __construct() {
            add_action('admin_enqueue_scripts', array($this, 'es_admin_ship_scripts'));
            add_action( 'wp_ajax_es_popup_rate_list', array($this, 'handel_popup_rate_list_ajax'));
			add_action( 'wp_ajax_es_handel_ship_order', array($this, 'handel_ship_order_ajax'));
			add_action( 'admin_footer', array($this, 'show_popup_window'));
			$this->popup_template = new ESShippingPopupTemplate();
			$this->delhiveryAPI = new DelhiveryAPI();
			$this->shiprocketAPI = new ShiprocketAPI();
			$this->nimbuspostAPI = new NimbuspostAPI();
        }
        
        function es_admin_ship_scripts() {
            if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
                if (!wp_style_is('es-popup-order-rate-list-style', 'enqueued')) {
                    wp_enqueue_style('es-popup-order-rate-list-style', EASYSHIP_URL . 'shipping/assets/css/es-popup-order-rate-list-style.css', [], '2.1', 'all');
                }
                if (!wp_script_is('es-popup-order-rate-list-script', 'enqueued')) {
                    wp_enqueue_script('es-popup-order-rate-list-script', EASYSHIP_URL . 'shipping/assets/js/es-popup-order-rate-list-script.js', array('jquery'), '5.1', true);
                }
            }            
        }
		
		// handel ajax popup for both single and bulk orders
        public function handel_popup_rate_list_ajax() {
            $order_ids = $_POST['order_ids'];
            $shipBy    = $_POST['shipBy'];
			echo $this->popup_template->template_of_bulk_rate_order_list($order_ids);
            wp_die(); // Always include this line to terminate the AJAX request
        }
		
		// handel ajax popup for both single and bulk orders
        public function handel_ship_order_ajax() {
            $orderData = $_POST['orderData'];
            $shipBy    = $_POST['ship_by'];
			
			$counter = 0;
			$shipErrors = []; // Empty array to store the results
			foreach ($orderData as $order) {
				$orderID = $order['orderID'];
				$courierID = $order['courierID'];
				$response = $this->delhiveryAPI->createShipments([$orderID]);
// 				$response = $this->shiprocketAPI->createShipments($orderID, $courierID);
// 				$response = $this->nimbuspostAPI->createShipments($orderID, $courierID);
				if( $response['success']) {
					$counter ++;
				} else {
					$shipErrors[$orderID] = json_encode($response['message']) . "\n \n";
				}
			}
			
			$totalSuccess = "Shipped " . $counter . " out of " . count($orderData) . "\n \n";
			array_unshift($shipErrors, $totalSuccess);
			
			$result = [
				'success' => true,
				'message' => 'Ship successfully',
				'result'  => $shipErrors
			];
			wp_send_json($result);
			wp_die(); // Always include this line to terminate the AJAX request
        }
		
		public function show_popup_window() {
            ?>
            <div id="es-popup" class="popup-background">
                <div id="loader" class="lds-dual-ring hidden overlay"></div>
                <div class="popup-article-ajax">
                    <!-- 	ajax responce display here	 -->
                </div>
            </div>
            <?php
        }

    }
}
