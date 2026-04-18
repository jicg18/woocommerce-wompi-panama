<?php
/**
 * Plugin Name: Wompi Panamá para WooCommerce
 * Plugin URI:  https://docs.wompi.co/docs/panama/
 * Description: Pasarela de pago Wompi Panamá para WooCommerce. Compatible con Block Checkout. Acepta Visa, Mastercard y Clave.
 * Version:     1.0.0
 * Author:      Tu Nombre
 * Author URI:  https://tudominio.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wompi-panama
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WOMPI_PA_VERSION',     '1.0.0' );
define( 'WOMPI_PA_PLUGIN_FILE', __FILE__ );
define( 'WOMPI_PA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WOMPI_PA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ── Compatibilidad con HPOS y Blocks de WooCommerce ─────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

// ── Inicializar plugin ───────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wompi_pa_init' );

function wompi_pa_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>Wompi Panamá</strong> requiere WooCommerce instalado y activo.</p></div>';
        } );
        return;
    }

    require_once WOMPI_PA_PLUGIN_DIR . 'includes/class-wc-gateway-wompi-panama.php';
    require_once WOMPI_PA_PLUGIN_DIR . 'includes/class-wompi-blocks-support.php';

    // Registrar pasarela clásica
    add_filter( 'woocommerce_payment_gateways', 'wompi_pa_add_gateway' );

    // Registrar integración con Blocks
    add_action( 'woocommerce_blocks_loaded', 'wompi_pa_register_blocks_support' );

    load_plugin_textdomain( 'wompi-panama', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function wompi_pa_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Wompi_Panama';
    return $gateways;
}

function wompi_pa_register_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
            $registry->register( new WC_Wompi_Panama_Blocks_Support() );
        } );
    }
}

// ── Enlace de configuración en la lista de plugins ──────────────────────
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url     = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wompi_panama' );
    $links[] = '<a href="' . esc_url( $url ) . '">Configuración</a>';
    return $links;
} );
