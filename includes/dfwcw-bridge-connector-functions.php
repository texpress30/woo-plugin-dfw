<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'DFWCWBC_Dependencies' ) ) {
	include_once 'class-dfwcwbridge-connector-dependencies.php';
}

/*
 * WC Detection
 */
if ( ! function_exists( 'dfwcwbc_is_required_plugins_active' ) ) {

	function dfwcwbc_is_required_plugins_active() {
		return DFWCWBC_Dependencies::required_plugins_active_check();
	}
}

/**
 * DFWCW_woocommerce_version_error
 */
function DFWCW_woocommerce_version_error() {
	?>
		<div class="error notice">
				<p><?php printf( esc_html( 'Requires WooCommerce version %s or later or WP-E-Commerce.' ), esc_html( DFWCWBC_MIN_WOO_VERSION ) ); ?></p>
		</div>
	<?php
}
