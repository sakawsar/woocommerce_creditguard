<?php
/**
 * Plugin Name: CreditGuard for WooCommerce by TalPress
 * Description: Accept CreditGuard payments on WooCommerce
 * Version: 2.0
 * Author: TalPress
 * Author URI: http://www.TalPress.co.il
 * Requires at least: 3.8.1
 * Tested up to: 5.7.2
 * Requires PHP: 7.2
 * WC requires at least: 2.6.14
 * WC tested up to: 5.4.0 * Text Domain: talpress-woocommerce-creditguard * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
include 'WC_Gateway_creditguard.php';
include 'creditguard_user_fields.php';
include 'creditguard_meta_box.php';
include 'tp_WC_creditguard_refund.php';
function creditguard_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=creditguard">'.__('Settings','talpress-woocommerce-creditguard').'</a>';
  	array_push( $links, $settings_link );
  	return $links;
	}
	
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'creditguard_add_settings_link' );

add_action( 'admin_enqueue_scripts', 'creditguard_add_scripts');

function creditguard_add_scripts($hook) {
	if (isset($_GET['section'])==false){ return; }
	if ( 'creditguard' != $_GET['section'] && 'wc_gateway_creditguard' != $_GET['section'] ) {
        return;
    }
	wp_enqueue_style('style',plugin_dir_url(__FILE__). 'assets/css/style.css');
	wp_enqueue_script('admin_js', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js' );
}

register_activation_hook( __FILE__, 'tp_creditguard_activation' );
function tp_creditguard_activation() {
	$iconv_missing = false;
	$simplexml_load_string_missing = false;
	if (!function_exists('iconv')) {
		$iconv_missing = true;
	}
	if (!function_exists('simplexml_load_string')) {
		$simplexml_load_string_missing = true;
	}

	if ($iconv_missing || $simplexml_load_string_missing){
		die('talpress Creditguard Payment Gateway NOT activated, either "iconv()" or "simplexml_load_string()" are missing on server');
	}
}