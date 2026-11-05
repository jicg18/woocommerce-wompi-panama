=== Wompi Panamá para WooCommerce ===
Tags: woocommerce, payment, wompi, panama, clave, visa, mastercard, banistmo, blocks, block checkout
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.5.0
WC requires at least: 7.0
WC tested up to: 9.0
License: GPL-2.0+

Pasarela de pago Wompi Panamá compatible con Block Checkout y Classic Checkout de WooCommerce.

== Descripción ==

Integra Wompi Panamá (respaldado por Banistmo) con WooCommerce. Acepta:

* 💳 Visa y Mastercard (crédito / débito)
* 🔑 Clave (Telered / Banistmo)

=== Compatibilidad ===

* ✅ Block Checkout (Gutenberg / bloques)
* ✅ Classic Checkout
* ✅ WooCommerce HPOS
* ✅ Modo Sandbox y Producción

=== Flujo de pago ===

1. El cliente selecciona Wompi en el checkout.
2. Confirma el pedido → WooCommerce llama a `process_payment()`.
3. El cliente es redirigido a una página segura con el Widget de Wompi.
4. El cliente completa el pago (tarjeta / Clave).
5. Wompi notifica el resultado vía webhook (firma SHA256).
6. El pedido se actualiza automáticamente en WooCommerce.

== Instalación ==

1. Sube la carpeta `wompi-panama-woocommerce` a `/wp-content/plugins/`
2. Activa el plugin desde **Plugins → Plugins instalados**
3. Ve a **WooCommerce → Ajustes → Pagos → Wompi Panamá**
4. Ingresa tus llaves (obtenidas en comercios.wompi.pa)
5. Copia la **URL de Eventos** y pégala en el Dashboard de Wompi

== Llaves necesarias ==

Desde comercios.wompi.pa → Desarrolladores → Secretos para integración:

| Llave | Prefijo Producción | Prefijo Sandbox |
|---|---|---|
| Pública | pub_prod_ | pub_test_ |
| Privada | prv_prod_ | prv_test_ |
| Integridad | prod_integrity_ | test_integrity_ |
| Eventos | prod_events_ | test_events_ |

== URLs de la API ==

* Producción: https://api.wompi.pa/v1
* Sandbox: https://api-sandbox.wompi.pa/v1
* Widget JS: https://checkout.wompi.pa/widget.js
* REST Webhook: `https://tudominio.com/wp-json/wompi-panama/v1/webhook`
* Webhook legado: `https://tudominio.com/?wc-api=wompi_panama`

== Estados de transacción ==

| Wompi | WooCommerce |
|---|---|
| APPROVED | processing |
| DECLINED | failed |
| VOIDED | cancelled |
| ERROR | failed |

== Changelog ==

= 1.0.0 =
* Versión inicial
* Soporte completo para Block Checkout (Gutenberg)
* Soporte para Classic Checkout
* Verificación de webhooks SHA256
* Firma de integridad automática
* HPOS compatible
