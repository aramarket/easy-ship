<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('ESShippingPopupTemplate')) {
    class ESShippingPopupTemplate {
		
		public function template_of_bulk_rate_order_list($order_IDs, $shipBy) {
			$easyshipLogo = EASYSHIP_URL . 'common/assets/img/easyship.png';
			$active_couriers = ESCommonFunctions::active_courier_list();
            ?>

				<div class="eashyship-popup-body">

					<header class="eashyship-popup-header">
						<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo $easyshipLogo ?>" alt="easyship"></a>
						<div class="easyship-header-right">
							<select id="shipping_id" name="shipping_id" >
							<?php 
								foreach ($active_couriers as $courier) {
									echo '<option value="' . $courier . '">' . $courier . '</option>';
								}
							?>
							</select>
							<span class="eashyship-clickable-icon eashyship-close-btn">&times;</span>
						</div>
					</header>

					<article class="eashyship-popup-article">
						<a href="https://ucp.delhivery.com/home" target="_blank"> <p class="eashyship-wallet">Delhivery Recharge</p></a>
						<a href="https://app.shiprocket.in/dashboard/" target="_blank"> <p class="eashyship-wallet">Shiprocket( ₹<?php 
							$shiprocket = new ShiprocketAPI();
							$responce = $shiprocket->shiprocketWalletBallence();
							if($responce['success']){
								echo $responce['result'];
							}
							?> )</p></a>

						<div class="eashyship-product-disc">
							<form id="es_create_bulk_shipment">
								<?php echo $this->handel_order_table($order_IDs, $shipBy); ?>
								<input type="hidden" id="ship_by" name="ship_by" value="<?php echo $shipBy; ?>">
							</form>
						</div>
					</article>

					<footer class="eashyship-popup-footer">
						<button class="eashyship-close-btn eashyship-close-footer">Close</button>
						<button class="eashyship-submit-btn" type="submit" form="es_create_bulk_shipment">Ship Now</button>
					</footer>

				</div>
			<?php
        }
		
		public function handel_order_table($order_IDs, $shipBy){
            ?>
                <table class="eashyship-table">
                    <tr>
                        <th>SR.</th>
                        <th style="width: 200px;">Product</th>
                        <th style="width: 100px;">Add</th>
                        <th style="width: 50px;">Total</th>
                        <th style="width: 50px;">Weight(g)</th>
                        <th>Shipping Cost</th>
                    </tr>
                        <?php 
                            $counter = 0;
                            $grosh_total;
							$order_weight = 0;
							$list_empty_item_weight = [];
                            foreach ($order_IDs as $order_ID) : 
                                $counter ++;
                                $order = wc_get_order( $order_ID );	
                             	$order_weight_details = ESShippingFunction::get_order_weight($order_ID);
								if ($order_weight_details['success']) {
									$order_weight = $order_weight_details['result'];
									$list_empty_item_weight = $order_weight_details['list_empty_item_weight'];
								} else{
									$order_weight = $order_weight_details['message'];
								}
                                $grosh_total += $order->get_total(); 
                        ?>
                        <tr class='es-bulk-table-row'>
                            <td><input type="hidden" name="order_ID" value="<?php echo $order_ID; ?>"><?php echo $counter; ?></td>
                            <td>
                                <?php 
									$items = $order->get_items(); 
					foreach ($items as $item) {
						$product_id = $item->get_product_id();
						$class = in_array($product_id, $list_empty_item_weight) ? "es-img add-error-border" : "es-img";
						$thumbnail_url = get_the_post_thumbnail_url($product_id, 'thumbnail');
						$admin_url = admin_url().'post.php?post='.$product_id.'&action=edit';
						$product_name = ESCommonFunctions::make_string_ellipsis($item->get_name(), 3);

						echo <<<HTML
						<div class="es-prodcut-img-title">
							<a href="$admin_url" target="_blank">
								<img class="$class" alt="img" src="$thumbnail_url">
							</a>&nbsp;
							<a href="$admin_url" target="_blank">$product_name</a>
							<br>
						</div>
						HTML;
					}
                             	?>
                            </td>
                            <td> <a href="<?php echo get_edit_post_link($order_ID);?>" target="_blank"> 
								<?php echo '#'.$order_ID; ?></a><br> 
								<?php echo  $order->get_billing_first_name() . ' '. $order->get_billing_last_name() . ',
								</br>' . $order->get_billing_state() . '-'. $order->get_billing_postcode(); ?></td>
                            <td class="es-row-amount"><?php echo '₹'.$order->get_total().'(' . ESShippingFunction::check_payment_mode( $order->get_payment_method_title()) . ')' ?></td>
                            <td>
								<?php echo $order_weight.'g'; ?>
							</td>
                            <td>
                                <?php echo $this->handel_courier_price($order_ID, $shipBy, $order_weight); ?>
                             </td>
                            <td><?php echo '<span class="eashyship-clickable-icon es-remove-row">&times;</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <tr>
                        <th id="es-order-count"><?php echo count($order_IDs); ?></th>
                        <th></th>
                        <th>Total</th>
                        <th id="es-grosh-total"><?php echo '₹'.$grosh_total; ?></th>
                        <th></th>
                        <th id="es-shipp-total"><?php echo '₹'; ?></th>
                    </tr>
                </table>
            <?php	
        }

        public function handel_courier_price($order_ID, $shipBy, $order_weight){
			if($shipBy == 'shiprocket'){
				$shipping_responce = (new ShiprocketAPI)->getShippingRate($order_ID, $order_weight);
			} else if($shipBy == 'delhivery'){
            	$shipping_responce = (new DelhiveryAPI)->getShippingRate($order_ID, $order_weight);
			} else if($shipBy == 'nimbuspost'){
				$shipping_responce = (new NimbuspostAPI)->getShippingRate($order_ID, $order_weight);
			}

			if($shipping_responce['success']){
				$list_of_couriers = $shipping_responce['result'];
				?>
					<select id="selected_courier" name="selected_courier" >
						<?php foreach ($list_of_couriers as $courier): ?>
							<option value="<?php echo  $courier['courier_id']; ?>">
								<?php echo '₹' . intval($courier['courier_price']).' - '. $courier['courier_name'];
; ?>
							</option>
						<?php endforeach; ?>
					</select>
            	<?php
			} else{
				$error_msg = $shipping_responce['message'];
				?>
					<select id="selected_courier" name="selected_courier" >
							<option value="">
								<?php echo 'Not Serviceable'; //echo $error_msg; ?>
							</option>
					</select>
            	<?php
			}
        }
	}
}