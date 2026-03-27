<?php
/*
Plugin Name: DataFeedWatch Connector for WooCommerce
Description: DataFeedWatch enables merchants to optimize & manage product feeds on 2,000+ channels & marketplaces worldwide.
Version: 2.0.6
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*
DataFeedWatch Connector for WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

DataFeedWatch Connector for WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with DataFeedWatch Connector for WooCommerce. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

defined( 'ABSPATH' ) || die( 'Cannot access pages directly.' );
define( 'DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME', 'DFWCW_woocommerce_bridge_connector_is_custom' );
define( 'DFWCWBC_BRIDGE_IS_INSTALLED', 'DFWCW_woocommerce_bridge_connector_is_installed' );
define( 'DFWCWBC_STORE_KEY', 'DFWCW_store_key' );

if ( ! defined( 'DFWCWBC_STORE_BASE_DIR' ) ) {
	define( 'DFWCWBC_STORE_BASE_DIR', ABSPATH );
}

if ( ! defined( 'DFWCWBC_MIN_WOO_VERSION' ) ) {
	define( 'DFWCWBC_MIN_WOO_VERSION', '2.8.1' );
}

if ( ! function_exists( 'dfwcwbc_is_required_plugins_active' ) ) {
	include_once 'includes/dfwcw-bridge-connector-functions.php';
}

if ( ! dfwcwbc_is_required_plugins_active() ) {
	add_action( 'admin_notices', 'DFWCW_woocommerce_version_error' );

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( plugin_basename( __FILE__ ), false, false );
	}

	return;
}

require 'worker.php';
$DFWCWworker = new DFWCWBridgeConnector();
$storeKey  = $DFWCWworker->getStoreKey();

require_once $DFWCWworker->bridgePath . $DFWCWworker->configFilePath;

$isCustom  = get_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME );
$bridgeUrl = $DFWCWworker->getBridgeUrl();

add_action( 'wp_ajax_DFWCWbridge_action',
	function () use ( $DFWCWworker, $storeKey ) {
	DFWCWbridge_action( $DFWCWworker, $storeKey );
	} );

/**
 * DFWCWbridge_action
 *
 * @param DFWCWBridgeConnector $DFWCWworker Worker
 * @param string             $storeKey  Store Key
 *
 * @throws Exception
 */
function DFWCWbridge_action( DFWCWBridgeConnector $DFWCWworker, $storeKey ) {
	check_ajax_referer('DFWCW-connector-nonce', 'security');

	if ( isset( $_REQUEST['connector_action'] ) ) {
		$action = sanitize_text_field( $_REQUEST['connector_action'] );
		$warning = false;

		switch ( $action ) {
			case 'installBridge':
				$data = [];
				update_option( DFWCWBC_BRIDGE_IS_INSTALLED, true );
				$status = $DFWCWworker->updateToken( $storeKey , false);

				if ( ! $status['success'] ) {
					break;
				}

				$status = $DFWCWworker->installBridge();
				$data   = [
					'storeKey'  => $storeKey,
					'bridgeUrl' => $DFWCWworker->getBridgeUrl(),
				];

				$warning = $status['warning'] ?? false;

				if ( $status['success'] || $warning ) {
					update_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME, isset( $status['custom'] ) ? $status['custom'] : false );
					update_option( DFWCWBC_BRIDGE_IS_INSTALLED, true );
				}
				break;
			case 'removeBridge':
				update_option( DFWCWBC_BRIDGE_IS_INSTALLED, false );
				$status = [
					'success' => true,
					'message' => 'Bridge deleted',
				];
				$data   = [];
				delete_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME );
				delete_option( DFWCWBC_BRIDGE_IS_INSTALLED );
				$callbackRes = $DFWCWworker->sendRequestToCallback( $storeKey, $DFWCWworker::EVENT_DISCONNECT );

				if ( is_wp_error( $callbackRes ) ) {
					$status = [
						'success' => false,
						'message' => 'Unable to send a request to the callback: ' . $callbackRes->get_error_message(),
					];

					$warning = true;
				}
				break;
			case 'updateToken':
				$storeKey = $DFWCWworker->updateStoreKey();
				$status   = $DFWCWworker->updateToken( $storeKey );
				$data     = [ 'storeKey' => $storeKey ];
				$warning  = $status['warning'] ?? false;
		}//end switch

		echo wp_json_encode( [ 'status' => $status, 'data' => $data, 'warning' => $warning ] );
		wp_die();
	}
}

/**
 * DFWCW_connector_plugin_action_links
 *
 * @param array  $links Links
 * @param string $file  File
 *
 * @return array
 */
function DFWCW_connector_plugin_action_links( array $links, $file ) {
	plugin_basename( dirname( __FILE__ ) . '/connectorMain.php' ) == $file;

	if ( $file ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=DFWCW_connector-config' ) . '">' . esc_html__( 'Settings', 'datafeedwatch-connector-for-woocommerce' ) . '</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'DFWCW_connector_plugin_action_links', 10, 2 );

/**
 * Register routes.
 *
 * @since 1.5.0
 */
function DFWCW_rest_api_register_routes() {
	if ( isset( $GLOBALS['woocommerce'] ) || isset( $GLOBALS['wpec'] ) ) {
		include_once 'includes/class-dfwcw-bridge-connector-rest-api-controller.php';

		// v1
		$restApiController = new DFWCW_Bridge_Connector_V1_REST_API_Controller();
		$restApiController->register_routes();
	}
}

add_action( 'rest_api_init', 'DFWCW_rest_api_register_routes' );

/**
 * DFWCW_connector_config
 *
 * @return bool
 * @throws Exception
 */
function DFWCW_connector_config() {
	WP_Filesystem();
	global $wp_filesystem;
	global $DFWCWworker;
	include_once $DFWCWworker->bridgePath . $DFWCWworker->configFilePath;
	$storeKey  = $DFWCWworker->getStoreKey();
	$isCustom  = get_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME );
	$bridgeUrl = $DFWCWworker->getBridgeUrl();
	preg_match( "/define\(\\s?'(\w+)',\s*'([^']*)'\\s?\);/", $wp_filesystem->get_contents( $DFWCWworker->bridgePath . '/bridge.php' ), $matches );
	$bridgeVersion = $matches[2];
	$theme_version = wp_get_theme()->get( 'Version' );

	preg_match( "/define\(\\s?'(\w+)',\s*'([^']*)'\\s?\);/", $wp_filesystem->get_contents( $DFWCWworker->bridgePath . $DFWCWworker->configFilePath ), $matches );

	if ( empty( $matches[2] ) ) {
		$DFWCWworker->updateToken( $storeKey, false );
	}

	wp_enqueue_style( 'connector-css', plugins_url( 'css/style.css', __FILE__ ) , [], $theme_version );
	wp_enqueue_style( 'connector-css-dfw', plugins_url( 'css/dfw-style.css', __FILE__ ) , [], $theme_version );
	wp_enqueue_script( 'connector-js', plugins_url( 'js/scripts.js', __FILE__ ), [ 'jquery' ], $theme_version );
	wp_enqueue_script( 'connector-js', plugins_url( 'js/scripts.js', __FILE__ ), [], $theme_version );
	wp_enqueue_script( 'connector-js-dfw', plugins_url( 'js/dfw-scripts.js', __FILE__ ), [], $theme_version );
	wp_localize_script(
		'connector-js',
		'DFWCWAjax',
		array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce('DFWCW-connector-nonce'))
	);

	$showButton = 'install';
	if ( get_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME ) ) {
		$showButton = 'uninstall';
	}

	$cartName       = 'WooCommerce';
	$sourceCartName = 'WooCommerce';
	$sourceCartName = strtolower( str_replace( ' ', '-', trim( $sourceCartName ) ) );
	$referertext    = 'Connector: ' . $sourceCartName . ' to ' . $cartName . ' module';

	include 'settings.phtml';

	return true;
}

/**
 * DFWCW_connector_uninstall
 */
function DFWCW_connector_uninstall() {
	delete_option( DFWCWBC_BRIDGE_IS_CUSTOM_OPTION_NAME );
	delete_option( DFWCWBC_BRIDGE_IS_INSTALLED );
	function_exists( 'delete_site_meta' ) ? delete_site_meta( 1, DFWCWBC_STORE_KEY ) : delete_option( DFWCWBC_STORE_KEY );
}

/**
 * DFWCW_connector_activate
 */
function DFWCW_connector_activate() {
	update_option( DFWCWBC_BRIDGE_IS_INSTALLED, true );
}

/**
 * DFWCW_connector_deactivate
 */
function DFWCW_connector_deactivate() {
	update_option( DFWCWBC_BRIDGE_IS_INSTALLED, false );
}

/**
 * DFWCW_connector_load_menu
 */
function DFWCW_connector_load_menu() {
	add_submenu_page( 'plugins.php',
		esc_html__( 'DataFeedWatch Connector for WooCommerce', 'datafeedwatch-connector-for-woocommerce' ),
		esc_html__( 'DataFeedWatch Connector for WooCommerce', 'datafeedwatch-connector-for-woocommerce' ),
		'manage_options',
		'DFWCW_connector-config',
		'DFWCW_connector_config' );
}

register_activation_hook( __FILE__, 'DFWCW_connector_activate' );
register_uninstall_hook( __FILE__, 'DFWCW_connector_uninstall' );
register_deactivation_hook( __FILE__, 'DFWCW_connector_deactivate' );

add_action( 'admin_menu', 'DFWCW_connector_load_menu' );
