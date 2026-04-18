<?php
/**
 * Integración de Wompi Panamá con WooCommerce Block Checkout.
 *
 * Esta clase registra la pasarela con el sistema de bloques de WooCommerce,
 * exponiendo los datos necesarios al script React del frontend.
 *
 * @package Wompi_Panama
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Wompi_Panama_Blocks_Support extends AbstractPaymentMethodType {

    /** @var string ID de la pasarela */
    protected $name = 'wompi_panama';

    /** @var WC_Gateway_Wompi_Panama */
    private $gateway;

    // ── Inicialización ───────────────────────────────────────────────────
    public function initialize() {
        $this->settings = get_option( 'woocommerce_wompi_panama_settings', [] );

        $gateways      = WC()->payment_gateways()->payment_gateways();
        $this->gateway = $gateways['wompi_panama'] ?? null;
    }

    // ── ¿Está activo? ────────────────────────────────────────────────────
    public function is_active(): bool {
        return $this->gateway && $this->gateway->is_available();
    }

    // ── Scripts del bloque ───────────────────────────────────────────────
    public function get_payment_method_script_handles(): array {
        $script_path = WOMPI_PA_PLUGIN_DIR . 'assets/js/wompi-blocks.js';
        $script_url  = WOMPI_PA_PLUGIN_URL . 'assets/js/wompi-blocks.js';
        $version     = file_exists( $script_path )
            ? filemtime( $script_path )
            : WOMPI_PA_VERSION;

        wp_register_script(
            'wompi-panama-blocks',
            $script_url,
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-i18n',
                'wp-html-entities',
            ],
            $version,
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wompi-panama-blocks', 'wompi-panama', WOMPI_PA_PLUGIN_DIR . 'languages' );
        }

        return [ 'wompi-panama-blocks' ];
    }

    // ── Datos del bloque ─────────────────────────────────────────────────
    public function get_payment_method_data(): array {
        if ( ! $this->gateway ) {
            return [];
        }
        return $this->gateway->get_payment_method_data();
    }
}
