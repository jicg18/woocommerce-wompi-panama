<?php
/**
 * Plugin Name: Wompi Panamá para WooCommerce
 * Plugin URI:  https://docs.wompi.co/docs/panama/
 * Description: Pasarela de pago Wompi Panamá para WooCommerce. Compatible con Block Checkout. Acepta Visa, Mastercard y Clave.
 * Version:     1.5.0
 * Author:      Jose Calvo
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

define( 'WOMPI_PA_VERSION',     '1.5.0' );
define( 'WOMPI_PA_PLUGIN_FILE', __FILE__ );
define( 'WOMPI_PA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WOMPI_PA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ── Compatibilidad con HPOS y Blocks de WooCommerce ─────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

// ── Inicializar plugin ───────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wompi_pa_init' );

// Se registra fuera de plugins_loaded: WooCommerce dispara este hook dentro de
// plugins_loaded (prioridad 10), así que registrarlo desde wompi_pa_init haría
// que dependa del orden alfabético de carga de los plugins.
add_action( 'woocommerce_blocks_loaded', 'wompi_pa_register_blocks_support' );

/**
 * Inicializa el plugin una vez que WordPress y WooCommerce están listos.
 */
function wompi_pa_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>Wompi Panamá</strong> requiere WooCommerce instalado y activo.</p></div>';
        } );
        return;
    }

    require_once WOMPI_PA_PLUGIN_DIR . 'includes/class-wc-gateway-wompi-panama.php';

    add_filter( 'woocommerce_payment_gateways', 'wompi_pa_add_gateway' );
    add_action( 'rest_api_init', 'wompi_pa_register_rest_routes' );

    load_plugin_textdomain( 'wompi-panama', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Registra la ruta REST para recibir webhooks de Wompi.
 */
function wompi_pa_register_rest_routes() {
    if ( ! function_exists( 'register_rest_route' ) ) {
        return;
    }

    register_rest_route(
        'wompi-panama/v1',
        '/webhook',
        [
            'methods'             => 'POST',
            'callback'            => 'wompi_pa_rest_webhook',
            'permission_callback' => '__return_true',
        ]
    );
}

/**
 * Maneja el webhook recibido por REST API.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 *
 * @return WP_Error|WP_REST_Response
 */
function wompi_pa_rest_webhook( $request ) {
    $gateway = wompi_pa_get_gateway_instance();
    if ( ! $gateway ) {
        return new WP_Error(
            'gateway_unavailable',
            __( 'Gateway no disponible', 'wompi-panama' ),
            [ 'status' => 503 ]
        );
    }

    if ( ! method_exists( $gateway, 'handle_rest_webhook' ) ) {
        return new WP_Error(
            'unsupported',
            __( 'Webhook no soportado', 'wompi-panama' ),
            [ 'status' => 501 ]
        );
    }

    return $gateway->handle_rest_webhook( $request );
}

/**
 * Obtiene la instancia del gateway de Wompi.
 *
 * @return WC_Gateway_Wompi_Panama|null
 */
function wompi_pa_get_gateway_instance() {
    static $gateway = null;

    if ( null !== $gateway ) {
        return $gateway;
    }

    if ( function_exists( 'WC' ) && method_exists( WC(), 'payment_gateways' ) ) {
        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        $gateway          = $payment_gateways['wompi_panama'] ?? null;
        if ( $gateway ) {
            return $gateway;
        }
    }

    if ( class_exists( 'WC_Gateway_Wompi_Panama' ) ) {
        $gateway = new WC_Gateway_Wompi_Panama();
    }

    return $gateway;
}

/**
 * Registra el gateway clásico de WooCommerce.
 *
 * @param array $gateways Gateways existentes.
 *
 * @return array
 */
function wompi_pa_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Wompi_Panama';
    return $gateways;
}

/**
 * Registra la integración con WooCommerce Blocks.
 */
function wompi_pa_register_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once WOMPI_PA_PLUGIN_DIR . 'includes/class-wompi-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ( $registry ) {
                $registry->register( new WC_Wompi_Panama_Blocks_Support() );
            }
        );
    }
}

// ── Enlace de configuración en la lista de plugins ──────────────────────
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url     = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wompi_panama' );
    $links[] = '<a href="' . esc_url( $url ) . '">Configuración</a>';
    return $links;
} );
