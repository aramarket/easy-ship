<?php
/*
 * Plugin Name:       Easy WhatsApp API
 * Plugin URI:        https://easy-ship.in
 * Description:       Seamlessly integrate WhatsApp messaging with your WooCommerce store for better customer communication and support.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            AKASH
 * Update URI:        https://easy-ship.in
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if(!defined( 'WPINC' )){
	die;
}
if(!defined('EASY_WHATSAPP_VERSTION')){
	define('EASY_WHATSAPP_VERSTION', '1.0.0');
}
if(!defined('EASY_WHATSAPP_DIR')){
	define('EASY_WHATSAPP_DIR', plugin_dir_url(__FILE__));
}

require_once __DIR__.'/includes/eashy-whatsapp-function.php';
require_once __DIR__.'/includes/eashy-whatsapp-setting.php';


?>
