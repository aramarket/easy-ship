<?php

add_action( 'init', 'easyship_register_settings' );
function easyship_register_settings() {
	register_setting( 'easyship-plugin-status', 'plugin_status' );

	//General setting
	register_setting( 'easyship-settings-group', 'purchase_token' );
	register_setting( 'easyship-settings-group', 'selected_page' );
	register_setting( 'easyship-settings-group', 'before_ship_status' );
	register_setting( 'easyship-settings-group', 'after_ship_status' );
	register_setting( 'easyship-settings-group', 'es_map_prepaid_orders' );
	
	// Nimbuspost settings
	register_setting( 'easyship-nimbuspost-group', 'nimbuspost_enable' );
    register_setting( 'easyship-nimbuspost-group', 'nimbusPost_username' );
    register_setting( 'easyship-nimbuspost-group', 'nimbusPost_password' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_warehouse_name' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_name' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_address' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_address_2' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_city' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_state' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_pincode' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_phone' );
	register_setting( 'easyship-nimbuspost-group', 'nimbusPost_gst_umber' );
	
	// Delhivery settings
	register_setting( 'easyship-delhivery-group', 'delhivery_enable' );
	register_setting( 'easyship-delhivery-group', 'delhivery_token' );
	register_setting( 'easyship-delhivery-group', 'delhivery_pickup_location' );
	
	//shiprocket settings
	register_setting( 'easyship-shiprocket-group', 'shiprocket_enable' );
	register_setting( 'easyship-shiprocket-group', 'shiprocket_username' );
    register_setting( 'easyship-shiprocket-group', 'shiprocket_password' );
	register_setting( 'easyship-shiprocket-group', 'shiprocket_pickup_location' );
	register_setting( 'easyship-shiprocket-group', 'shiprocket_channel_id' );
	
}

////wp add menu
add_action('admin_menu','ets_resigster_menu_page');
function ets_resigster_menu_page(){
	add_menu_page('EasyShip Dashboard','EasyShip','manage_options','easyship-main','easyship_dashboard_page','dashicons-airplane', 6);
	add_submenu_page('easyship-main','Delhivery settings','Delhivery API','manage_options','delhivery-nimbuspost', 'delhivery_settings_page_creation' );
	add_submenu_page('easyship-main','Shiprocket settings','Shiprocket API','manage_options','easyship-shiprocket', 'shiprocket_settings_page_creation' );
	add_submenu_page('easyship-main','NimbusPost settings','NimbusPost API','manage_options','easyship-nimbuspost', 'nimbuspost_settings_page_creation' );

}
// Add sections and fields to the settings page
function easyship_dashboard_page() {
?>
    <div class="wrap">
        <h1>easyship Tracking Settings</h1>
		<p>
			<?php
				global $wpdb;
				$table_name = $wpdb->prefix . 'easyship_db'; 
				$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
				echo "Number of Shipments: " . $row_count;
			?>
		</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'easyship-settings-group' ); ?>
            <?php do_settings_sections( 'easyship-settings-group' ); ?>
            <table class="form-table">
				<tr valign="top">
					<form method="post" >
						<th scope="row">Purchase Token</th>
		 <td><input type="text" name="purchase_token" value="<?php echo esc_attr( get_option( 'purchase_token' ) ); ?>" />
                    	<button class="button btn" type="submit" name="es_activate_plugin">Activate Plugin</button>
					</form>
				</tr>
				<tr valign="top">
					<th scope="row">Plugin Status</th>
					<td><input type="text" name="plugin_status" value="<?php 
						$plugin_status = get_option('plugin_status');
						if (empty($plugin_status)) {
							$plugin_status = 'Not Activated';
						}			
						echo esc_attr($plugin_status); 
						?> " disabled />
				</tr>
				<tr valign="top">
					<th scope="row">Select a Tracking Page</th>
					<td>
					  <select name="selected_page" id="myplugin_page">
						<option value="">-- Select a page --</option>
						<?php
						$pages = get_pages();
						foreach ( $pages as $page ) {
						  $option = '<option value="' . $page->ID . '"';
						  if ( get_option( 'selected_page' ) == $page->ID ) {
							$option .= ' selected="selected"';
						  }
						  $option .= '>';
						  $option .= $page->post_title;
						  $option .= '</option>';
						  echo $option;
						}
						?>
					  </select>
					</td>
					<td>Add this <strong>[EASYSHIP-TRACK]</strong> Shortcode to selected page</td>
                </tr>
			
				<tr valign="top">
					<th scope="row">Shipping Button Show</th>
					<td>
						<select name="before_ship_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'before_ship_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
		echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Updated Status After Ship</th>
					<td>
						<select name="after_ship_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'after_ship_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
		echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Map Prepaid Orders</th>
					<td><input type="text" name="es_map_prepaid_orders" value="<?php echo esc_attr(get_option('es_map_prepaid_orders')); ?> " />
					<td>Paste here Payment Gateway titel like - <strong>'Paytm Payment Gateway',</strong> seprated by ' , '</td>
				</tr>
				<tr valign="top">
					<th scope="row">Map COD Orders</th>
					<td><input type="text" name="es_map_cod_orders" value="<?php echo esc_attr(get_option('es_map_cod_orders')); ?> " />
					<td>Paste here COD payment titel like - <strong>'Cash on delivery',</strong> seprated by ' , '</td>
				</tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php								
}

function delhivery_settings_page_creation() {
?>
    <div class="wrap">
        <h1>Delhivery Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'easyship-delhivery-group' ); ?>
            <?php do_settings_sections( 'easyship-delhivery-group' ); ?>
            <table class="form-table">
				<tr valign="top">
                    <th scope="row">Delhivery Enable/Disable</th>
					<td><input type="checkbox" name="delhivery_enable" value="1" <?php echo (get_option( 'delhivery_enable' )) ? 'checked' : ''; ?> /> Enable Delhivery</td>
                </tr>
				<tr valign="top">
                    <th scope="row">Delhivery Token</th>
   <td><input type="text" name="delhivery_token" value="<?php echo esc_attr( get_option( 'delhivery_token' ) ); ?>" /></td>
                </tr>
				<tr valign="top">
                    <th scope="row">Pickup Location Name</th>
    <td><input type="text" name="delhivery_pickup_location" value="<?php echo esc_attr( get_option( 'delhivery_pickup_location' ) ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}
function shiprocket_settings_page_creation(){
?>
    <div class="shiprocket-settings-wrap">
        <h1>Shiprocket Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'easyship-shiprocket-group' ); ?>
            <?php do_settings_sections( 'easyship-shiprocket-group' ); ?>
            <table class="form-table">
				<tr valign="top">
                    <th scope="row">Shiprocket Enable/Disable</th>
					<td><input type="checkbox" name="shiprocket_enable" value="1" <?php echo (get_option( 'shiprocket_enable' )) ? 'checked' : ''; ?> /> Enable Shiprocket</td>
                </tr>
				 <tr valign="top">
                    <th scope="row">Shiprocket Username*</th>
					<td><input type="text" name="shiprocket_username" value="<?php echo esc_attr( get_option( 'shiprocket_username' ) ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Shiprocket Password*</th>
                    <td><input type="password" name="shiprocket_password" value="<?php echo esc_attr( get_option( 'shiprocket_password' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
                    <th scope="row">Pickup Location Name*</th>
                    <td><input type="text" name="shiprocket_pickup_location" value="<?php echo esc_attr( get_option( 'shiprocket_pickup_location' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
                    <th scope="row">Channel ID</th>
                    <td><input type="text" name="shiprocket_channel_id" value="<?php echo esc_attr( get_option( 'shiprocket_channel_id' ) ); ?>" /></td>
				</tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php

}

// Add sections and fields to the settings page
function nimbuspost_settings_page_creation() {
?>
    <div class="wrap">
        <h1>Nimbuspost Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'easyship-nimbuspost-group' ); ?>
            <?php do_settings_sections( 'easyship-nimbuspost-group' ); ?>
            <table class="form-table">
				<tr valign="top">
                    <th scope="row">Nimbuspost Enable/Disable</th>
					<td><input type="checkbox" name="nimbuspost_enable" value="1" <?php echo (get_option( 'nimbuspost_enable' )) ? 'checked' : ''; ?> /> Enable Nimbuspost</td>
                </tr>
                <tr valign="top">
                    <th scope="row">NimbusPost Username</th>
                    <td><input type="text" name="nimbusPost_username" value="<?php echo esc_attr( get_option( 'nimbusPost_username' ) ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">NimbusPost Password</th>
                    <td><input type="password" name="nimbusPost_password" value="<?php echo esc_attr( get_option( 'nimbusPost_password' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><h2>Wharehouse Details</h2></th>
				</tr>
				 <tr valign="top">
                    <th scope="row">Warehouse Name</th>
                    <td><input type="text" name="nimbusPost_warehouse_name" value="<?php echo esc_attr( get_option( 'nimbusPost_warehouse_name' ) ); ?>" /></td>
				</tr>
				 <tr valign="top">
                    <th scope="row">Name</th>
                    <td><input type="text" name="nimbusPost_name" value="<?php echo esc_attr( get_option( 'nimbusPost_name' ) ); ?>" /></td>
				</tr>
				 <tr valign="top">
                    <th scope="row">Address Line 1</th>
                    <td><input type="text" name="nimbusPost_address" value="<?php echo esc_attr( get_option( 'nimbusPost_address' ) ); ?>" /></td>
				</tr>
				 <tr valign="top">
                    <th scope="row">Address Line 2</th>
                    <td><input type="text" name="nimbusPost_address_2" value="<?php echo esc_attr( get_option( 'nimbusPost_address_2' ) ); ?>" /></td>
				</tr>
				 <tr valign="top">
                    <th scope="row">City</th>
                    <td><input type="text" name="nimbusPost_city" value="<?php echo esc_attr( get_option( 'nimbusPost_city' ) ); ?>" /></td>
				</tr>
				 <tr valign="top">
                    <th scope="row">State</th>
                    <td><input type="text" name="nimbusPost_state" value="<?php echo esc_attr( get_option( 'nimbusPost_state' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
                    <th scope="row">Pincode</th>
                    <td><input type="text" name="nimbusPost_pincode" value="<?php echo esc_attr( get_option( 'nimbusPost_pincode' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
                    <th scope="row">Phone</th>
                    <td><input type="text" name="nimbusPost_phone" value="<?php echo esc_attr( get_option( 'nimbusPost_phone' ) ); ?>" /></td>
				</tr>
				<tr valign="top">
                    <th scope="row">GST Number</th>
                    <td><input type="text" name="nimbusPost_gst_umber" value="<?php echo esc_attr( get_option( 'nimbusPost_gst_umber' ) ); ?>" /></td>
				</tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}


?>