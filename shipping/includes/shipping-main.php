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
			add_action( 'wp_ajax_es_print_label', array($this, 'handle_print_label_ajax'));

			add_action( 'admin_footer', array($this, 'show_popup_window'));
			$this->popup_template = new ESShippingPopupTemplate();
			$this->delhiveryAPI = new DelhiveryAPI();
			$this->shiprocketAPI = new ShiprocketAPI();
			$this->nimbuspostAPI = new NimbuspostAPI();
        }
        
        function es_admin_ship_scripts() {
			if (!wp_style_is('es-spinner-style', 'enqueued')) {
				wp_enqueue_style('es-spinner-style', EASYSHIP_URL . 'shipping/assets/css/es-spinner.css', [], '1.2', 'all');
			}
            if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
                if (!wp_style_is('es-popup-order-rate-list-style', 'enqueued')) {
                    wp_enqueue_style('es-popup-order-rate-list-style', EASYSHIP_URL . 'shipping/assets/css/es-popup-order-rate-list-style.css', [], '2.4', 'all');
                }
                if (!wp_script_is('es-popup-order-rate-list-script', 'enqueued')) {
                    wp_enqueue_script('es-popup-order-rate-list-script', EASYSHIP_URL . 'shipping/assets/js/es-popup-order-rate-list-script.js', array('jquery'), '7.0', true);
                }
            }            
        }
		
		// handel ajax popup for both single and bulk orders
        public function handle_print_label_ajax() {
            $orderID = $_POST['orderID'];
			$response = $this->handle_print_label_request($orderID);
			if(!$response['success']) {
				$result = [
					'success' => false,
					'message' => 'Error - ' . $response['message'] . 'Order id - ' . $orderID,
				];
			} else {
				$result = [
					'success' => true,
					'message' => 'Genrate label Url successfully',
					'result'  => $response['result']
				];
			}
			wp_send_json($result);
			wp_die(); // Always include this line to terminate the AJAX request
        }
		
		// handel ajax popup for both single and bulk orders
        public function handel_popup_rate_list_ajax() {
            $order_ids = $_POST['order_ids'];
            $shipBy    = $_POST['shipBy'];
			echo $this->popup_template->template_of_bulk_rate_order_list($order_ids, $shipBy);
            wp_die(); // Always include this line to terminate the AJAX request
        }
		
		// handel ajax popup for both single and bulk orders
        public function handel_ship_order_ajax() {
            $orderData = $_POST['orderData'];
            $shipBy    = $_POST['shipBy'];
			
			$counter = 0;
			$shipErrors = []; // Empty array to store the results
			foreach ($orderData as $order) {
				$orderID = $order['orderID'];
				$courierID = $order['courierID'];
				if($shipBy == 'shiprocket'){
					$response = $this->shiprocketAPI->createShipments($orderID, $courierID);
				} else if($shipBy == 'delhivery'){
					$response = $this->delhiveryAPI->createShipments([$orderID]);
				} else if($shipBy == 'nimbuspost'){
					$response = $this->nimbuspostAPI->createShipments($orderID, $courierID);
				}
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

		
		public function handle_print_label_request($orderID) {
			
			$trackingResponce = ESCommonFunctions::get_tracking_details($orderID);
			
			if (!$trackingResponce['success']) {
				return [
					'success' => false,
					'message' => $trackingResponce['message'],
				];
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
			return $result;
		}
		
    }
}
