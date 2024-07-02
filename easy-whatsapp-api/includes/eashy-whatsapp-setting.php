<?php

add_action( 'init', 'easy_whatsapp_register_settings' );
function easyship_register_settings() {
	register_setting( 'easy-whatsapp-plugin-status', 'plugin_status' );

	//General setting
	register_setting( 'easy-whatsapp-settings-group', 'user_access_token' );
	register_setting( 'easy-whatsapp-settings-group', 'whatsapp_api_version' );
	register_setting( 'easy-whatsapp-settings-group', 'phone_number_id' );
	register_setting( 'easy-whatsapp-settings-group', 'whatsapp_bussiness_id' );
	register_setting( 'easy-whatsapp-settings-group', 'test_phone_number' );

	register_setting( 'easy-whatsapp-settings-group', 'order_cancel_status' );
	register_setting( 'easy-whatsapp-settings-group', 'order_cancel_template' );

	register_setting( 'easy-whatsapp-settings-group', 'order_received_status' );
	register_setting( 'easy-whatsapp-settings-group', 'order_received_template' );

	register_setting( 'easy-whatsapp-settings-group', 'order_shipped_status' );
	register_setting( 'easy-whatsapp-settings-group', 'order_shipped_template' );

	register_setting( 'easy-whatsapp-settings-group', 'order_delivered_status' );
	register_setting( 'easy-whatsapp-settings-group', 'order_delivered_template' );
}

//wp add menu
add_action('admin_menu','easy_whatsapp_resigster_menu_page');
function easy_whatsapp_resigster_menu_page(){
	// Base64 encoded SVG icon for WhatsApp
	$whatsapp_icon = 'data:image/svg+xml;base64,' . base64_encode('
        <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4CAF50" d="M12 0C5.372 0 0 5.372 0 12c0 2.072.53 4.048 1.527 5.823L0 24l6.26-1.647C7.951 23.47 9.94 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.768c-1.765 0-3.484-.466-5.017-1.343l-.36-.208-3.72.975.995-3.633-.235-.375C2.713 15.592 2.252 13.836 2.252 12c0-5.38 4.368-9.75 9.748-9.75s9.748 4.37 9.748 9.75c0 5.381-4.367 9.75-9.748 9.75z"/>
            <path fill="#FFF" d="M17.545 14.192c-.292-.146-1.729-.855-1.996-.955-.266-.1-.461-.146-.656.147-.195.293-.752.954-.92 1.15-.167.196-.34.22-.632.073-.292-.146-1.231-.453-2.344-1.45-.867-.77-1.453-1.722-1.624-2.014-.167-.293-.017-.451.128-.596.132-.132.292-.341.439-.512.146-.171.195-.293.293-.487.097-.195.048-.366-.024-.512-.073-.146-.656-1.585-.899-2.158-.237-.57-.48-.493-.656-.493h-.561c-.171 0-.44.049-.671.244-.232.195-.878.857-.878 2.088 0 1.231.899 2.422 1.025 2.59.126.171 1.77 2.697 4.292 3.784.6.26 1.07.414 1.437.536.604.192 1.15.165 1.585.1.483-.073 1.729-.706 1.973-1.393.244-.683.244-1.27.171-1.393-.072-.122-.268-.195-.56-.341z"/>
        </svg>
    ');

	add_menu_page('Easy Whatsapp Dashboard','Easy WhatsApp API','manage_options','easy-whatsapp-main','easy_whatsapp_dashboard_page',$whatsapp_icon, 7);
}

// Add sections and fields to the settings page
function easy_whatsapp_dashboard_page() {
?>
    <div class="wrap">
        <h1>Easy WhatsApp API Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'easy-whatsapp-settings-group' ); ?>
            <?php do_settings_sections( 'easy-whatsapp-settings-group' ); ?>
            <table class="form-table">

				<!-- User access token -->
				<tr valign="top">
					<th scope="row">User access token</th>
					<td><input type="text" name="user_access_token" placeholder="EAAP8ns3eE4A..." value="<?php echo esc_attr( get_option( 'user_access_token' ) ); ?>" /></td>
				</tr>

				<!-- WhatsApp api version -->
				<tr valign="top">
					<th scope="row">WhatsApp api version</th>
					<td><input type="text" name="whatsapp_api_version" placeholder="v19.0" value="<?php echo esc_attr( get_option( 'whatsapp_api_version' ) ); ?>" /></td>
				</tr>

				<!-- Phone number id -->
				<tr valign="top">
					<th scope="row">Phone number id</th>
					<td><input type="text" name="phone_number_id" placeholder="339026xxxxx3544" value="<?php echo esc_attr( get_option( 'phone_number_id' ) ); ?>" /></td>
				</tr>

				<!-- Whatsapp bussiness id -->
				<tr valign="top">
					<th scope="row">Whatsapp bussiness id</th>
					<td><input type="text" name="whatsapp_bussiness_id" placeholder="2779xxxxx748812" value="<?php echo esc_attr( get_option( 'whatsapp_bussiness_id' ) ); ?>" /></td>
				</tr>

				<!-- Test phone number -->
				<tr valign="top">
					<th scope="row">Test phone number</th>
					<td><input type="text" name="test_phone_number" placeholder="9182xxxx9298" value="<?php echo esc_attr( get_option( 'test_phone_number' ) ); ?>" /></td>
				</tr>

				<!-- Cancel Order Status and template -->
				<tr valign="top">
					<th scope="row">Cancel order status and template</th>
					<td>
						<select name="order_cancel_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'order_cancel_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
									echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
						<input type="text" name="order_cancel_template" placeholder="hello_world" value="<?php echo esc_attr( get_option( 'order_cancel_template' ) ); ?>" />
					</td>
				</tr>

				<!-- Order received status and template -->
				<tr valign="top">
					<th scope="row">Order received status and template</th>
					<td>
						<select name="order_received_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'order_received_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
									echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
						<input type="text" name="order_received_template" placeholder="hello_world" value="<?php echo esc_attr( get_option( 'order_received_template' ) ); ?>" />
					</td>
				</tr>

				<!-- Order shipped status and template -->
				<tr valign="top">
					<th scope="row">Order shipped status and template</th>
					<td>
						<select name="order_shipped_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'order_shipped_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
									echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
						<input type="text" name="order_shipped_template" placeholder="hello_world" value="<?php echo esc_attr( get_option( 'order_shipped_template' ) ); ?>" />
					</td>
				</tr>

				<!-- Order delivered status and template -->
				<tr valign="top">
					<th scope="row">Order delivered status and template</th>
					<td>
						<select name="order_delivered_status">
							<option value="">-- Select Status --</option>
							<?php
								$order_statuses = wc_get_order_statuses();
								$selected_status = get_option( 'order_delivered_status' );
								foreach ( $order_statuses as $status => $status_label ) {
									$selected = selected( $status, $selected_status, false );
									echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status_label ) . '</option>';
								}
							?>
						</select>
						<input type="text" name="order_delivered_template" placeholder="hello_world" value="<?php echo esc_attr( get_option( 'order_delivered_template' ) ); ?>" />
					</td>
				</tr>
				
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php								
}


?>