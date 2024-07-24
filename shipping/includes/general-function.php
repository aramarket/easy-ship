<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('ESShippingFunction')) {
	class ESShippingFunction {
        // check payment method for get rate
        public static function check_payment_mode($given_payment_title){
            $list_of_prepaid_title = array("Paytm Payment Gateway", "Razorpay Payment Gateway", "UPI/QR/Card/NetBanking");
            $list_of_prepaid_title_provided_by_user = get_option('es_map_prepaid_orders');
            if (!empty($list_of_prepaid_title_provided_by_user)) {
                $list_of_prepaid_title = array_merge($list_of_prepaid_title, explode(',', $list_of_prepaid_title_provided_by_user));
            }
            if(in_array($given_payment_title, $list_of_prepaid_title)){
                return 'prepaid';
            } else {
                return 'cod';
            }
        }
		
		public static function get_order_weight($order_ID) {
			$order = wc_get_order($order_ID);
			$list_empty_item_weight = [];

			$items = $order->get_items();
			$weight = 0;

			foreach ($items as $item) {
				$product = $item->get_product();
				$product_id = $product->get_id(); // Get the product ID
				if ($product) {
					$one_item_weight = $product->get_weight();
					if ($one_item_weight == null) {
						$one_item_weight = 0.01;
						$list_empty_item_weight[] = $product_id;
					}
					$product_weight = $one_item_weight * $item->get_quantity();
					$weight += $product_weight;
				}
			}

			$product_weight_unit = get_option('woocommerce_weight_unit');
			if ($product_weight_unit === 'kg') {
				$weight *= 1000;
			}

			if ($weight < 500) {
				$weight = 500;
			}

			return [
				'success' => true,
				'message' => 'Order weight calculated successfully',
				'result'  => intval($weight),
				'list_empty_item_weight' => $list_empty_item_weight
			];
		}
		
		// Get product dimention only if one product in cart
		public static function get_product_dimensions($order_ID) {
			$order = wc_get_order($order_ID);
			$items = $order->get_items();
			if (count($items) === 1) {
				$item = reset($items);
				$product = $item->get_product();
				$length = $product->get_length();
				$width = $product->get_width();
				$height = $product->get_height();
				if($length){
					return array(
						'length' => $length,
						'width'  => $width,
						'height' => $height
					);
				}else{
					return array(
						'length' => 11,
						'width'  => 11,
						'height' => 11
					);
				}

			} else {
				return array(
					'length' => 10,
					'width'  => 10,
					'height' => 10
				);
			}
		}
    }
}