<?php
/**
 * Paymentsense Gateway for WooCommerce.
 *
 * Plugin Name:          Paymentsense Gateway for WooCommerce
 * Description:          Extends WooCommerce by taking payments via Paymentsense. Provides integration with Paymentsense Hosted and Direct.
 * Version:              3.0.39
 * Author:               Paymentsense
 * Author URI:           http://www.paymentsense.co.uk/
 * License:              GNU General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          woocommerce-paymentsense
 * Requires at least:    4.4
 * Tested up to:         5.9
 * WC requires at least: 3.7.0
 * WC tested up to:      6.1.1
 *
 * @package WooCommerce_Paymentsense_Gateway
 * @wordpress-plugin
 * @author Paymentsense
 * @link http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

load_plugin_textdomain(
	'woocommerce-paymentsense',
	false,
	basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages'
);

add_action( 'admin_init', 'paymentsense_check_compatibility' );

/**
 * Checks for incompatible plugins and in the case of incompatibility disables the Paymentsense plugin
 */
function paymentsense_check_compatibility() {
	$incompatible_plugins = get_incompatible_plugins();
	if ( ! empty( $incompatible_plugins ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		$message = sprintf(
			// Translators: %s - plugins list.
			__(
				'Warning: Paymentsense Gateway for WooCommerce plugin is incompatible with the following plugin(s): %s.',
				'woocommerce-paymentsense'
			),
			$incompatible_plugins
		) .
		'<br/><br/>' .
		__(
			'As a result of that Paymentsense Gateway for WooCommerce is deactivated.',
			'woocommerce-paymentsense'
		);

		$message = "<strong>{$message}</strong>";

		$message .= '<br/><br/><a href="' . admin_url( 'plugins.php' ) . '">' .
			__( '&laquo; Go Back', 'woocommerce-paymentsense' ) . '</a>';

		// @codingStandardsIgnoreLine
		wp_die( $message );
	}
}

/**
 * Gets a list of the the confirmed incompatible plugins
 *
 * @return  string
 */
function get_incompatible_plugins() {
	$incompatible_plugins = array(
		'woocommerce-sequential-order-numbers' => 'WooCommerce Sequential Order Numbers',
	);

	$incompatible_plugins_found = '';
	$active_plugins             = get_option( 'active_plugins' );
	foreach ( $active_plugins as $plugin_path ) {
		$parts       = explode( '/', $plugin_path );
		$plugin_slug = $parts[0];
		if ( array_key_exists( $plugin_slug, $incompatible_plugins ) ) {
			$plugin_text = '"' . $incompatible_plugins[ $plugin_slug ] . '"';
			if ( empty( $incompatible_plugins_found ) ) {
				$incompatible_plugins_found = $plugin_text;
			} else {
				$incompatible_plugins_found .= ', ' . $plugin_text;
			}
		}
	}
	return $incompatible_plugins_found;
}

/**
 * Shows a notice on the plugins page at the admin area, if applicable
 */
function paymentsense_show_admin_notice() {
	global $pagenow;
	if ( 'plugins.php' === $pagenow ) {
		$warning = Paymentsense_Base::get_warning_message();
		if ( ! empty( $warning ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo wp_kses(
				sprintf( '<strong>%s</strong>', $warning ),
				array(
					'strong' => array(),
					'br'     => array(),
				)
			);
			echo '</p></div>';
		}
	}
}

/**
 * Hooks Paymentsense on the plugins_loaded action if WooCommerce is active
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

	if ( ! function_exists( 'woocommerce_paymentsense_init' ) ) {
		/**
		 * Paymentsense Init function
		 */
		function woocommerce_paymentsense_init() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			require_once plugin_dir_path( __FILE__ ) . 'includes/helper-iso-codes.php';
			require_once plugin_dir_path( __FILE__ ) . 'includes/paymentsense-defines.php';
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-paymentsense-base.php';
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-paymentsense-hosted.php';
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-paymentsense-direct.php';

			if ( ! function_exists( 'woocommerce_add_paymentsense_gateways' ) ) {
				/**
				 * Adds Paymentsense payments into the WooCommerce payment gateways
				 *
				 * @param array $methods WooCommerce payment gateways.
				 * @return array WooCommerce payment gateways
				 */
				function woocommerce_add_paymentsense_gateways( $methods ) {
					$methods[] = 'WC_Paymentsense_Hosted';
					$methods[] = 'WC_Paymentsense_Direct';
					return $methods;
				}
				add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_paymentsense_gateways' );
			}
		}
	}
	add_action( 'plugins_loaded', 'woocommerce_paymentsense_init', 0 );
	add_action( 'admin_notices', 'paymentsense_show_admin_notice' );
}
