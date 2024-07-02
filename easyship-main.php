<?php
/*
 * Plugin Name:       EasyShip
 * Plugin URI:        https://easy-ship.in
 * Description:       Most Affordable tracking and Shipping solution Spacial for India, Also Made in India.
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
if(!defined('EASYSHIP_VERSTION')){
	define('EASYSHIP_VERSTION', '1.0.0');
}
if(!defined('EASYSHIP_DIR')){
	define('EASYSHIP_DIR', plugin_dir_url(__FILE__));
}


register_activation_hook( __FILE__, 'es_plugin_activation' );
function es_plugin_activation() {
	global $wpdb;
    $table_name = $wpdb->prefix . 'easyship_db';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
		order_number mediumint(9) NOT NULL,
        order_price DECIMAL(10,2) NOT NULL,
        order_weight FLOAT NOT NULL,
		date_created timestamp NOT NULL,
        awb_number varchar(50) NOT NULL,
		courier_id mediumint(9) NOT NULL,
		courier_name varchar(50) NOT NULL,
		shipped_through varchar(50) NOT NULL,
        label varchar(2500) NOT NULL,
        states varchar(50) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

require_once __DIR__.'/includes/eashyship-for-aramarket.php';
require_once __DIR__.'/includes/easyship-add-buttons.php';
require_once __DIR__.'/includes/easyship-tracking-page.php';
require_once __DIR__.'/includes/easyship-wp-setting.php';
require_once __DIR__.'/includes/easyship-funtions.php';

require_once __DIR__.'/includes/api-delhivery.php';
require_once __DIR__.'/includes/api-nimbusPost.php';
require_once __DIR__.'/includes/api-shiprocket.php';

// Register the shortcode
add_shortcode( 'EASYSHIP-TRACK', 'easyship_shortcode' );


require_once __DIR__ . '/libs/tcpdf/vendor/autoload.php';
use setasign\Fpdi\Tcpdf\Fpdi;
function es_mergePDF($filePaths, $outputFilePath)
{
    $pdf = new Fpdi();   
    foreach ($filePaths as $fileIndex => $filePath) {
        $tempFilePath = __DIR__ . '/temp' . $fileIndex . '.pdf';
        file_put_contents($tempFilePath, file_get_contents($filePath));
        $pageCount = $pdf->setSourceFile($tempFilePath);
        for ($page = 1; $page <= $pageCount; $page++) {
            $pdf->SetPrintHeader(false);
            $templateId = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
        unlink($tempFilePath);
    }
    $pdf->Output($outputFilePath, 'I');
    echo "<script>window.open($outputFilePath, '_blank');</script>";

// 'I': Send the PDF to the browser, displaying it inline.
// 'D': Force the PDF to download.
// 'F': Save the PDF to a local file.
// 'S': Return the PDF as a string.
}



?>
