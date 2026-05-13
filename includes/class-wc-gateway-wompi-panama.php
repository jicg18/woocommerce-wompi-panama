<?php
/**
 * Clase principal de la pasarela Wompi Panamá.
 * Maneja configuración, procesamiento de pedidos y webhooks.
 *
 * @package Wompi_Panama
 */

defined( 'ABSPATH' ) || exit;

class WC_Gateway_Wompi_Panama extends WC_Payment_Gateway {

    // ── URLs ─────────────────────────────────────────────────────────────
    const API_PROD            = 'https://api.wompi.pa/v1';
    const API_SANDBOX         = 'https://api-sandbox.wompi.pa/v1';
    const WIDGET_JS           = 'https://checkout.wompi.pa/widget.js';
    const REDIRECT_PROD       = 'https://transaction-redirect.wompi.pa/check';
    const REDIRECT_SANDBOX    = 'https://transaction-redirect-sandbox.wompi.pa/check';
    const CHECKOUT_PROD       = 'https://checkout.wompi.pa/p/';
    const CHECKOUT_SANDBOX    = 'https://checkout.wompi.pa/p/';

    /** @var bool */
    public $sandbox;

    // Llaves
    private $pub_key_live;
    private $prv_key_live;
    private $integrity_key_live;
    private $events_key_live;
    private $pub_key_test;
    private $prv_key_test;
    private $integrity_key_test;
    private $events_key_test;

    // ── Constructor ──────────────────────────────────────────────────────
    public function __construct() {
        $this->id                 = 'wompi_panama';
        // Logo como data URI base64 — no depende de archivos externos ni rutas
        $this->icon               = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMjgiIHdpZHRoPSIxMDAiIGhlaWdodD0iMjgiPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMjgiIHJ4PSI1IiBmaWxsPSIjMTBiOTgxIi8+PHRleHQgeD0iNTAiIHk9IjE5IiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTMiIGZvbnQtd2VpZ2h0PSJib2xkIiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+V29tcGk8L3RleHQ+PC9zdmc+';
        $this->has_fields         = false;
        $this->method_title       = __( 'Wompi Panamá', 'wompi-panama' );
        $this->method_description = __( 'Acepta pagos con Visa, Mastercard y Clave a través del Widget de Wompi Panamá (Banistmo).', 'wompi-panama' );
        $this->supports           = [ 'products' ];

        $this->init_form_fields();
        $this->init_settings();
        $this->load_settings();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_api_wompi_panama', [ $this, 'handle_webhook' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_widget_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Eliminar el link [?] que WooCommerce envuelve alrededor del ícono
        add_filter( 'woocommerce_gateway_icon', [ $this, 'filter_icon_html' ], 10, 2 );
    }

    // ── Filtrar HTML del ícono — quitar link [?] de WooCommerce ─────────
    public function filter_icon_html( string $icon_html, string $gateway_id ): string {
        if ( $gateway_id !== $this->id ) {
            return $icon_html;
        }
        // Devolver solo la imagen sin el link <a> que agrega WooCommerce
        return '<img src="' . esc_attr( $this->icon ) . '" alt="Wompi Panamá" style="height:24px;vertical-align:middle;margin-left:8px;border-radius:4px;">';
    }

    // ── Configuración ────────────────────────────────────────────────────
    private function load_settings() {
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->sandbox     = 'yes' === $this->get_option( 'sandbox' );

        $this->pub_key_live       = $this->get_option( 'pub_key_live' );
        $this->prv_key_live       = $this->get_option( 'prv_key_live' );
        $this->integrity_key_live = $this->get_option( 'integrity_key_live' );
        $this->events_key_live    = $this->get_option( 'events_key_live' );

        $this->pub_key_test       = $this->get_option( 'pub_key_test' );
        $this->prv_key_test       = $this->get_option( 'prv_key_test' );
        $this->integrity_key_test = $this->get_option( 'integrity_key_test' );
        $this->events_key_test    = $this->get_option( 'events_key_test' );
    }

    public function init_form_fields() {
        $webhook_url = add_query_arg( 'wc-api', 'wompi_panama', home_url( '/' ) );

        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Activar / Desactivar', 'wompi-panama' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar Wompi Panamá', 'wompi-panama' ),
                'default' => 'no',
            ],
            'sandbox' => [
                'title'       => __( 'Modo de pruebas (Sandbox)', 'wompi-panama' ),
                'type'        => 'checkbox',
                'label'       => __( 'Habilitar modo Sandbox', 'wompi-panama' ),
                'default'     => 'yes',
                'description' => __( 'Usa las llaves de prueba mientras desarrollas. Desactiva para procesar pagos reales.', 'wompi-panama' ),
            ],
            'integration_method' => [
                'title'       => __( 'Método de integración', 'wompi-panama' ),
                'type'        => 'select',
                'options'     => [
                    'widget'   => __( 'Widget — Popup de pago dentro de tu sitio', 'wompi-panama' ),
                    'checkout' => __( 'Checkout Web — Redirige a la página de Wompi', 'wompi-panama' ),
                ],
                'default'     => 'widget',
                'description' => __( '<strong>Widget:</strong> El cliente paga en un popup sin salir de tu tienda.<br><strong>Checkout Web:</strong> El cliente es redirigido a wompi.pa y regresa al finalizar. Más simple y confiable.', 'wompi-panama' ),
            ],
            'title' => [
                'title'   => __( 'Título', 'wompi-panama' ),
                'type'    => 'text',
                'default' => __( 'Paga con Wompi (Visa, Mastercard, Clave)', 'wompi-panama' ),
            ],
            'description' => [
                'title'   => __( 'Descripción', 'wompi-panama' ),
                'type'    => 'textarea',
                'default' => __( 'Paga de forma segura con tu tarjeta de crédito, débito o Clave a través de Wompi Panamá, respaldado por Banistmo.', 'wompi-panama' ),
            ],

            // ── Producción ──────────────────────────────────────────
            'production_keys_title' => [
                'title' => __( '🔑 Llaves de Producción', 'wompi-panama' ),
                'type'  => 'title',
            ],
            'pub_key_live' => [
                'title'       => __( 'Llave pública (pub_prod_...)', 'wompi-panama' ),
                'type'        => 'text',
                'description' => __( 'Obtenla en comercios.wompi.pa → Desarrolladores', 'wompi-panama' ),
            ],
            'prv_key_live' => [
                'title'       => __( 'Llave privada (prv_prod_...)', 'wompi-panama' ),
                'type'        => 'password_toggle',
                'description' => __( 'Solo se usa en el servidor. Nunca la expongas en el frontend.', 'wompi-panama' ),
            ],
            'integrity_key_live' => [
                'title'       => __( 'Secreto de Integridad (prod_integrity_...)', 'wompi-panama' ),
                'type'        => 'password_toggle',
                'description' => __( 'Usado para generar la firma de integridad del Widget (SHA256).', 'wompi-panama' ),
            ],
            'events_key_live' => [
                'title'       => __( 'Llave de Eventos (prod_events_...)', 'wompi-panama' ),
                'type'        => 'password_toggle',
                'description' => __( 'Usada para validar la autenticidad de los webhooks entrantes.', 'wompi-panama' ),
            ],

            // ── Sandbox ─────────────────────────────────────────────
            'sandbox_keys_title' => [
                'title' => __( '🧪 Llaves de Sandbox (Pruebas)', 'wompi-panama' ),
                'type'  => 'title',
            ],
            'pub_key_test' => [
                'title' => __( 'Llave pública de prueba (pub_test_...)', 'wompi-panama' ),
                'type'  => 'text',
            ],
            'prv_key_test' => [
                'title' => __( 'Llave privada de prueba (prv_test_...)', 'wompi-panama' ),
                'type'  => 'password_toggle',
            ],
            'integrity_key_test' => [
                'title' => __( 'Secreto de Integridad de prueba (test_integrity_...)', 'wompi-panama' ),
                'type'  => 'password_toggle',
            ],
            'events_key_test' => [
                'title' => __( 'Llave de Eventos de prueba (test_events_...)', 'wompi-panama' ),
                'type'  => 'password_toggle',
            ],

            // ── Webhook ─────────────────────────────────────────────
            'webhook_title' => [
                'title'       => __( '🔔 URL de Eventos (Webhook)', 'wompi-panama' ),
                'type'        => 'title',
                'description' => sprintf(
                    __( 'Copia y pega esta URL en tu Dashboard de Wompi → <strong>Mi cuenta → URL de eventos</strong>:<br><code style="background:#f4f4f4;padding:4px 8px;border-radius:4px;user-select:all;">%s</code>', 'wompi-panama' ),
                    esc_url( $webhook_url )
                ),
            ],
        ];
    }

    // ── Campo personalizado: password con botón mostrar/ocultar ──────────

    /**
     * Genera el HTML de un campo tipo password con botón de ojo.
     * WooCommerce llama generate_{type}_html() para tipos personalizados.
     */
    public function generate_password_toggle_html( string $key, array $data ): string {
        $field_key = $this->get_field_key( $key );
        $defaults  = [
            'title'       => '',
            'description' => '',
            'desc_tip'    => false,
            'css'         => '',
        ];
        $data  = wp_parse_args( $data, $defaults );
        $value = $this->get_option( $key );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>">
                    <?php echo wp_kses_post( $data['title'] ); ?>
                </label>
            </th>
            <td class="forminp">
                <div style="display:flex;align-items:center;gap:6px;max-width:400px;">
                    <input
                        type="password"
                        id="<?php echo esc_attr( $field_key ); ?>"
                        name="<?php echo esc_attr( $field_key ); ?>"
                        value="<?php echo esc_attr( $value ); ?>"
                        style="flex:1;font-family:monospace;font-size:.85rem;<?php echo esc_attr( $data['css'] ); ?>"
                        autocomplete="new-password"
                        spellcheck="false"
                    >
                    <button
                        type="button"
                        title="Mostrar / Ocultar"
                        onclick="
                            var inp = this.previousElementSibling;
                            var showing = inp.type === 'text';
                            inp.type = showing ? 'password' : 'text';
                            this.innerHTML = showing
                                ? '<?php echo esc_js( $this->eye_icon_open() ); ?>'
                                : '<?php echo esc_js( $this->eye_icon_closed() ); ?>';
                        "
                        style="
                            background:none;border:1px solid #ddd;border-radius:6px;
                            padding:5px 8px;cursor:pointer;line-height:1;color:#555;
                            flex-shrink:0;display:flex;align-items:center;
                        "
                    ><?php echo $this->eye_icon_open(); // phpcs:ignore ?></button>
                </div>
                <?php if ( $data['description'] ) : ?>
                <p class="description"><?php echo wp_kses_post( $data['description'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function eye_icon_open(): string {
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }

    private function eye_icon_closed(): string {
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    public function get_pub_key()            { return $this->sandbox ? $this->pub_key_test       : $this->pub_key_live; }
    public function get_prv_key()            { return $this->sandbox ? $this->prv_key_test       : $this->prv_key_live; }
    public function get_integrity_key()      { return $this->sandbox ? $this->integrity_key_test : $this->integrity_key_live; }
    public function get_events_key()         { return $this->sandbox ? $this->events_key_test    : $this->events_key_live; }
    public function get_api_url()            { return $this->sandbox ? self::API_SANDBOX         : self::API_PROD; }
    public function get_redirect_url()       { return $this->sandbox ? self::REDIRECT_SANDBOX    : self::REDIRECT_PROD; }
    public function get_checkout_url()       { return $this->sandbox ? self::CHECKOUT_SANDBOX    : self::CHECKOUT_PROD; }
    public function is_sandbox()             { return $this->sandbox; }
    public function get_integration_method() { return $this->get_option( 'integration_method', 'widget' ); }
    public function use_widget()             { return $this->get_integration_method() === 'widget'; }

    /**
     * Genera la firma de integridad SHA256 según documentación oficial de Wompi Panamá.
     * Orden: reference + amount_in_cents + currency + expiration_time + integrity_secret
     * El expiration_time se incluye cuando se usa el parámetro expiration-time.
     */
    public function generate_integrity_signature( string $reference, int $amount_in_cents, string $currency = 'USD', string $expiration_time = '' ): array {
        $string = $reference . $amount_in_cents . $currency;
        if ( $expiration_time ) {
            $string .= $expiration_time;
        }
        $string .= $this->get_integrity_key();
        return [
            'hash'   => hash( 'sha256', $string ),
            'string' => $reference . $amount_in_cents . $currency . ( $expiration_time ?: '' ) . '[SECRETO]',
        ];
    }

    // ── CSS del checkout ─────────────────────────────────────────────────
    public function enqueue_scripts() {
        if ( ! is_checkout() || ! $this->is_available() ) {
            return;
        }
        wp_enqueue_style(
            'wompi-panama-checkout',
            WOMPI_PA_PLUGIN_URL . 'assets/css/wompi-checkout.css',
            [],
            WOMPI_PA_VERSION
        );
    }

    // ── Procesamiento del pedido (ambos checkouts llaman esto) ───────────
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [ 'result' => 'failure' ];
        }

        // Referencia única — solo alfanumérica y guiones, sin caracteres especiales
        $reference = 'WC-' . $order_id . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 8 ) );
        $order->update_meta_data( '_wompi_reference', $reference );

        // Monto en centavos
        $amount_in_cents = (int) round( $order->get_total() * 100 );

        // Expiración: 7 minutos
        $expiration = gmdate( 'Y-m-d\TH:i:s.000\Z', time() + 7 * 60 );

        // URL de redirección — debe ser nuestro propio dominio según la documentación oficial
        // Wompi agrega ?id=TX_ID al final para que podamos consultar el resultado
        $redirect_url = add_query_arg(
            [
                'wompi_return' => $order_id,
                'wompi_nonce'  => wp_create_nonce( 'wompi_pa_return_' . $order_id ),
            ],
            home_url( '/' )
        );

        // Firma de integridad según docs oficiales:
        // reference + amount_in_cents + currency + expiration_time + integrity_secret
        $sig_data  = $this->generate_integrity_signature( $reference, $amount_in_cents, 'USD', $expiration );
        $signature = $sig_data['hash'];

        // Guardar en meta para usarlos en la página del Widget
        $order->update_meta_data( '_wompi_amount_in_cents', $amount_in_cents );
        $order->update_meta_data( '_wompi_expiration',      $expiration );
        $order->update_meta_data( '_wompi_signature',       $signature );
        $order->update_meta_data( '_wompi_redirect_url',    $redirect_url );
        $order->update_meta_data( '_wompi_sig_string',      $sig_data['string'] );
        $order->update_status( 'pending', __( 'Esperando confirmación de pago Wompi.', 'wompi-panama' ) );
        $order->save();

        // No vaciamos el carrito aquí — lo hacemos solo cuando el pago es APPROVED
        // Así el cliente puede volver al checkout si cancela o hay un error

        // Widget: página intermedia con el popup de Wompi
        // Checkout Web: página con formulario HTML que envía a Wompi
        return [
            'result'   => 'success',
            'redirect' => add_query_arg(
                [
                    'wompi_pay'   => $order_id,
                    'wompi_nonce' => wp_create_nonce( 'wompi_pa_pay_' . $order_id ),
                ],
                home_url( '/' )
            ),
        ];
    }

    // ── Página intermedia del Widget / Checkout Web ──────────────────────
    public function maybe_render_widget_page() {

        // ── Retorno de Wompi después del pago ────────────────────────────
        // Wompi agrega ?id=TX_ID a nuestra redirect-url
        if ( isset( $_GET['wompi_return'], $_GET['wompi_nonce'] ) ) {
            $order_id = absint( $_GET['wompi_return'] );
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wompi_nonce'] ) ), 'wompi_pa_return_' . $order_id ) ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    // Si Wompi pasó el ID de transacción en la URL, consultamos el estado
                    $tx_id = sanitize_text_field( $_GET['id'] ?? '' );
                    if ( $tx_id ) {
                        $tx = $this->get_transaction( $tx_id );
                        if ( $tx ) {
                            $this->process_transaction_event( $tx );
                            $order->update_meta_data( '_wompi_transaction_id', $tx_id );
                            $order->save();
                        }
                    }
                    wp_safe_redirect( $this->get_return_url( $order ) );
                    exit;
                }
            }
            wp_safe_redirect( home_url() );
            exit;
        }

        // ── Página del Widget o Checkout Web ─────────────────────────────
        if ( ! isset( $_GET['wompi_pay'], $_GET['wompi_nonce'] ) ) {
            return;
        }

        $order_id = absint( $_GET['wompi_pay'] );
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wompi_nonce'] ) ), 'wompi_pa_pay_' . $order_id ) ) {
            wp_safe_redirect( home_url() );
            exit;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_safe_redirect( home_url() );
            exit;
        }

        // Recuperar datos guardados en meta
        $data = [
            'order_id'           => $order_id,
            'reference'          => $order->get_meta( '_wompi_reference' ),
            'amount'             => (int) $order->get_meta( '_wompi_amount_in_cents' ),
            'expiration'         => $order->get_meta( '_wompi_expiration' ),
            'signature'          => $order->get_meta( '_wompi_signature' ),
            'sig_string'         => $order->get_meta( '_wompi_sig_string' ),
            'redirect_url'       => $order->get_meta( '_wompi_redirect_url' ),
            'pub_key'            => $this->get_pub_key(),
            'checkout_url'       => $this->get_checkout_url(),
            'integration_method' => $this->get_integration_method(),
            'customer_email'     => $order->get_billing_email(),
            'customer_name'      => $order->get_formatted_billing_full_name(),
            'customer_phone'     => preg_replace( '/\D/', '', $order->get_billing_phone() ),
            'sandbox'            => $this->sandbox,
        ];

        include WOMPI_PA_PLUGIN_DIR . 'includes/widget-page.php';
        exit;
    }

    // ── Webhook ──────────────────────────────────────────────────────────
    public function handle_webhook() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        $body    = file_get_contents( 'php://input' );
        $payload = json_decode( $body, true );

        if ( empty( $payload ) || ! is_array( $payload ) ) {
            http_response_code( 400 );
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => 'payload vacío' ] );
            exit;
        }

        // Verificar firma — rechazar si events_key no está configurada (fail-closed)
        $events_key = $this->get_events_key();
        if ( ! $events_key ) {
            error_log( 'Wompi Panama: webhook rechazado — events_key no configurada.' );
            http_response_code( 401 );
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => 'events_key no configurada' ] );
            exit;
        }

        if ( ! $this->verify_webhook_signature( $body ) ) {
            http_response_code( 401 );
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => 'firma inválida' ] );
            exit;
        }

        $event = $payload['event'] ?? '';
        if ( $event === 'transaction.updated' ) {
            $this->process_transaction_event( $payload['data']['transaction'] ?? [] );
        }

        http_response_code( 200 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [ 'status' => 'ok' ] );
        exit;
    }

    /**
     * Verifica la firma del webhook de Wompi Panamá.
     *
     * Fórmula: SHA256( tx.id + tx.status + tx.amount_in_cents + timestamp + events_key )
     * El checksum llega tanto en el header X-Event-Checksum como en payload.signature.checksum
     * Usamos el del body JSON ya que LiteSpeed puede modificar nombres de headers.
     */
    private function verify_webhook_signature( string $raw_body ): bool {
        $events_key = $this->get_events_key();
        if ( ! $events_key ) {
            return false;
        }

        $payload = json_decode( $raw_body, true );
        if ( ! is_array( $payload ) ) {
            return false;
        }

        $signature  = $payload['signature'] ?? [];
        $properties = $signature['properties'] ?? [];
        $timestamp  = (string) ( $payload['timestamp'] ?? '' );

        // Obtener checksum — del body JSON (más confiable) o del header como fallback
        $checksum = $signature['checksum'] ?? '';
        if ( empty( $checksum ) ) {
            $checksum = $_SERVER['HTTP_X_EVENT_CHECKSUM'] ?? '';
        }

        if ( empty( $checksum ) ) {
            return false;
        }

        // Concatenar valores de las properties + timestamp + events_key
        $concat = '';
        foreach ( $properties as $prop ) {
            $concat .= $this->get_nested_value( $payload, $prop );
        }
        $concat .= $timestamp;
        $concat .= $events_key;

        return hash_equals( hash( 'sha256', $concat ), $checksum );
    }

    private function get_nested_value( array $array, string $key ): string {
        $parts = explode( '.', $key );
        $value = $array;
        foreach ( $parts as $part ) {
            if ( ! array_key_exists( $part, $value ) ) {
                return '';
            }
            $value = $value[ $part ];
        }
        return (string) $value;
    }

    private function process_transaction_event( array $tx ) {
        $reference = $tx['reference'] ?? '';
        $status    = $tx['status'] ?? '';
        $tx_id     = $tx['id'] ?? '';

        if ( ! $reference ) {
            return;
        }

        $orders = wc_get_orders( [
            'meta_key'   => '_wompi_reference',
            'meta_value' => $reference,
            'limit'      => 1,
        ] );

        if ( empty( $orders ) ) {
            return;
        }

        /** @var WC_Order $order */
        $order = $orders[0];

        $order->update_meta_data( '_wompi_transaction_id',     $tx_id );
        $order->update_meta_data( '_wompi_transaction_status', $status );

        switch ( $status ) {
            case 'APPROVED':
                $order->payment_complete( $tx_id );
                $order->add_order_note( sprintf( __( '✅ Pago aprobado por Wompi. ID de transacción: %s', 'wompi-panama' ), $tx_id ) );
                // Vaciar el carrito solo cuando el pago es confirmado
                if ( WC()->cart ) {
                    WC()->cart->empty_cart();
                }
                break;
            case 'DECLINED':
                $order->update_status( 'failed', sprintf( __( '❌ Pago rechazado por Wompi. ID: %s', 'wompi-panama' ), $tx_id ) );
                break;
            case 'VOIDED':
                $order->update_status( 'cancelled', sprintf( __( '🚫 Transacción anulada en Wompi. ID: %s', 'wompi-panama' ), $tx_id ) );
                break;
            case 'ERROR':
                $order->update_status( 'failed', sprintf( __( '⚠️ Error en la transacción Wompi. ID: %s', 'wompi-panama' ), $tx_id ) );
                break;
        }

        $order->save();
    }

    // ── Consultar transacción en la API ──────────────────────────────────
    public function get_transaction( string $tx_id ): ?array {
        $response = wp_remote_get( $this->get_api_url() . '/transactions/' . $tx_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_prv_key(),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['data'] ?? null;
    }

    // ── Datos públicos para el bloque JS ─────────────────────────────────
    /**
     * Retorna los datos necesarios para que el bloque JS pueda
     * mostrar información y solicitar al servidor la firma de integridad.
     */
    public function get_payment_method_data(): array {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'sandbox'     => $this->sandbox,
            'logo_url'    => WOMPI_PA_PLUGIN_URL . 'assets/img/wompi-logo.png',
            'nonce'       => wp_create_nonce( 'wompi_pa_block_data' ),
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
        ];
    }
}
