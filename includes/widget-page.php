<?php
/**
 * Página intermedia de pago — soporta Widget y Web Checkout.
 */
defined( 'ABSPATH' ) || exit;

$pub_key      = esc_attr( $data['pub_key'] );
$amount       = (int) $data['amount'];
$reference    = esc_attr( $data['reference'] );
$signature    = esc_attr( $data['signature'] );
$expiration   = esc_attr( $data['expiration'] );
$redirect_url = esc_url( $data['redirect_url'] );
$checkout_url = esc_url( $data['checkout_url'] );
$method       = $data['integration_method'] ?? 'widget'; // 'widget' o 'checkout'
$email        = esc_attr( $data['customer_email'] );
$name         = esc_attr( $data['customer_name'] );
$phone        = esc_attr( preg_replace( '/\D/', '', $data['customer_phone'] ?? '' ) );
$is_sandbox   = ! empty( $data['sandbox'] );
$cache_bust   = time();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Completar pago — Wompi Panamá</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f6f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 40px 36px;
            max-width: 440px;
            width: 100%;
            text-align: center;
        }
        .logo-text { font-size: 1.6rem; font-weight: 800; color: #10b981; margin-bottom: 4px; letter-spacing: -1px; }
        .powered   { font-size: .75rem; color: #9ca3af; margin-bottom: 28px; }
        .heading   { font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 20px; }
        .amount-box {
            background: #f0fdf4; border: 2px solid #bbf7d0;
            border-radius: 10px; padding: 14px 20px; margin-bottom: 28px;
        }
        .amount-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 2px; }
        .amount-value { font-size: 2rem; font-weight: 800; color: #059669; }
        .amount-cur   { font-size: .95rem; color: #6b7280; margin-left: 4px; }
        .wompi-wrap   { margin-bottom: 20px; display: flex; justify-content: center; }
        /* Botón Web Checkout */
        .wompi-btn {
            background: #10b981; color: #fff; border: none;
            border-radius: 10px; padding: 14px 36px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            width: 100%; max-width: 320px; transition: background .2s;
        }
        .wompi-btn:hover { background: #059669; }
        .sandbox-badge {
            display: inline-block; background: #fef3c7; color: #92400e;
            border: 1px solid #fde68a; border-radius: 6px;
            padding: 4px 12px; font-size: .78rem; font-weight: 600; margin-bottom: 20px;
        }
        .back   { font-size: .83rem; color: #9ca3af; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .back:hover { color: #374151; }
        .secure { font-size: .75rem; color: #d1d5db; }
        .debug-box { margin-top: 20px; text-align: left; border: 1px dashed #d1fae5; border-radius: 8px; font-size: .73rem; font-family: monospace; }
        .debug-box summary { padding: 8px 12px; cursor: pointer; font-weight: 700; color: #059669; user-select: none; }
        .debug-box table { width: 100%; border-collapse: collapse; padding: 0 12px 12px; display: block; }
        .debug-box td { padding: 3px 4px 3px 0; vertical-align: top; word-break: break-all; }
        .debug-box td:first-child { white-space: nowrap; color: #6b7280; padding-right: 8px; }
    </style>
</head>
<body>
<div class="card">

    <div class="logo-text">Wompi Panamá</div>
    <div class="powered">Respaldado por Banistmo</div>
    <div class="heading">Completa tu pago</div>

    <?php if ( $is_sandbox ) : ?>
        <div class="sandbox-badge">⚠️ Modo Sandbox — sin cargos reales</div>
    <?php endif; ?>

    <div class="amount-box">
        <div class="amount-label">Total a pagar</div>
        <div>
            <span class="amount-value">$<?php echo number_format( $amount / 100, 2 ); ?></span>
            <span class="amount-cur">USD</span>
        </div>
    </div>

    <div class="wompi-wrap">
        <?php if ( $method === 'widget' ) : ?>
        <!-- ── Widget: popup de pago ───────────────────────────────── -->
        <form id="wompi-form-<?php echo $cache_bust; ?>">
            <script
                src="https://checkout.wompi.pa/widget.js?v=<?php echo $cache_bust; ?>"
                data-render="button"
                data-public-key="<?php echo $pub_key; ?>"
                data-currency="USD"
                data-amount-in-cents="<?php echo $amount; ?>"
                data-reference="<?php echo $reference; ?>"
                data-signature:integrity="<?php echo $signature; ?>"
                data-expiration-time="<?php echo $expiration; ?>"
                data-redirect-url="<?php echo $redirect_url; ?>"
                data-customer-data:email="<?php echo $email; ?>"
                data-customer-data:full-name="<?php echo $name; ?>"
                <?php if ( $phone ) : ?>
                data-customer-data:phone-number="<?php echo $phone; ?>"
                data-customer-data:phone-number-prefix="+507"
                <?php endif; ?>
                data-default-language="es"
            ></script>
        </form>

        <?php else : ?>
        <!-- ── Web Checkout: formulario HTML estándar ─────────────── -->
        <form action="<?php echo $checkout_url; ?>" method="GET">
            <input type="hidden" name="public-key"          value="<?php echo $pub_key; ?>">
            <input type="hidden" name="currency"            value="USD">
            <input type="hidden" name="amount-in-cents"     value="<?php echo $amount; ?>">
            <input type="hidden" name="reference"           value="<?php echo $reference; ?>">
            <input type="hidden" name="signature:integrity" value="<?php echo $signature; ?>">
            <input type="hidden" name="redirect-url"        value="<?php echo $redirect_url; ?>">
            <input type="hidden" name="expiration-time"     value="<?php echo $expiration; ?>">
            <input type="hidden" name="default-language"    value="es">
            <?php if ( $email ) : ?>
            <input type="hidden" name="customer-data:email"     value="<?php echo $email; ?>">
            <?php endif; ?>
            <?php if ( $name ) : ?>
            <input type="hidden" name="customer-data:full-name" value="<?php echo $name; ?>">
            <?php endif; ?>
            <?php if ( $phone ) : ?>
            <input type="hidden" name="customer-data:phone-number"        value="<?php echo $phone; ?>">
            <input type="hidden" name="customer-data:phone-number-prefix" value="+507">
            <?php endif; ?>
            <button type="submit" class="wompi-btn">
                Pagar con Wompi →
            </button>
        </form>
        <?php endif; ?>
    </div>

    <a class="back" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">← Volver al checkout</a><br><br>
    <div class="secure">🔒 Pago seguro · Banistmo</div>

    <?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
    <details class="debug-box">
        <summary>🛠 Debug (solo admins)</summary>
        <table>
            <?php
            $rows = [
                'Método'         => $method === 'widget' ? '🪟 Widget (popup)' : '🌐 Web Checkout (form)',
                'Ambiente'       => $is_sandbox ? '🧪 Sandbox' : '🚀 Producción',
                'Pub key'        => $pub_key   ?: '⚠️ VACÍA',
                'Referencia'     => $reference ?: '⚠️ VACÍA',
                'Monto cents'    => $amount    ?: '⚠️ 0',
                'Cadena firmada' => ( $data['sig_string'] ?? '' ) ?: '(no disponible)',
                'Firma SHA256'   => $signature ?: '⚠️ VACÍA',
                'Expiración'     => $expiration,
                'Redirect URL'   => $redirect_url ?: '(vacía)',
                'Email'          => $email ?: '(vacío)',
                'Nombre'         => $name  ?: '(vacío)',
                'Teléfono'       => $phone ?: '(vacío)',
            ];
            foreach ( $rows as $k => $v ) :
            ?>
                <tr><td><?php echo esc_html( $k ); ?></td><td><?php echo esc_html( $v ); ?></td></tr>
            <?php endforeach; ?>
        </table>
    </details>
    <?php endif; ?>

</div>

<script>
// Limpiar storage de Wompi para evitar cache de transacciones anteriores
(function () {
    try {
        ['localStorage','sessionStorage'].forEach(function(store) {
            var s = window[store];
            var toRemove = [];
            for (var i = 0; i < s.length; i++) {
                var k = s.key(i);
                if (k && (k.indexOf('wompi') !== -1 || k.indexOf('widget') !== -1)) {
                    toRemove.push(k);
                }
            }
            toRemove.forEach(function(k) { s.removeItem(k); });
        });
    } catch(e) {}
})();
</script>

</body>
</html>
