/**
 * Wompi Panamá — Block Checkout Integration
 */
( function () {
    'use strict';

    const registry = window.wc && window.wc.wcBlocksRegistry;
    const getSetting = window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting;
    const { createElement, useEffect } = window.wp.element;
    const { __ }                    = window.wp.i18n;
    const { decodeEntities }        = window.wp.htmlEntities;

    if ( ! registry || ! registry.registerPaymentMethod || typeof getSetting !== 'function' ) {
        return;
    }

    const { registerPaymentMethod } = registry;
    const settings = getSetting( 'wompi_panama_data', {} );

    const defaultTitle       = __( 'Paga con Wompi (Visa, Mastercard, Clave)', 'wompi-panama' );
    const defaultDescription = __( 'Paga de forma segura con tu tarjeta de crédito, débito o Clave a través de Wompi Panamá, respaldado por Banistmo.', 'wompi-panama' );

    const gatewayTitle       = decodeEntities( settings.title       || defaultTitle );
    const gatewayDescription = decodeEntities( settings.description || defaultDescription );
    const isSandbox          = !! settings.sandbox;

    // ── Logo SVG inline — no depende de archivos externos ───────────────
    const WompiLogo = () => createElement(
        'span',
        {
            style: {
                display:        'inline-flex',
                alignItems:     'center',
                justifyContent: 'center',
                background:     '#10b981',
                color:          '#fff',
                fontWeight:     '800',
                fontSize:       '11px',
                letterSpacing:  '-0.3px',
                padding:        '2px 8px',
                borderRadius:   '4px',
                marginLeft:     '8px',
                verticalAlign:  'middle',
                lineHeight:     '1.6',
            }
        },
        'Wompi'
    );

    // ── Contenido del bloque ─────────────────────────────────────────────
    const WompiContent = ( { eventRegistration, emitResponse } ) => {
        const { onPaymentSetup } = eventRegistration;

        useEffect( () => {
            const unsubscribe = onPaymentSetup( async () => {
                return { type: emitResponse.responseTypes.SUCCESS };
            } );
            return () => unsubscribe();
        }, [ onPaymentSetup, emitResponse.responseTypes.SUCCESS ] );

        return createElement(
            'div',
            { className: 'wompi-block-content' },

            createElement( 'p', { className: 'wompi-block-description' }, gatewayDescription ),

            isSandbox && createElement(
                'p',
                { className: 'wompi-sandbox-badge' },
                '⚠️ ' + __( 'Modo Sandbox activo — no se realizarán cargos reales.', 'wompi-panama' )
            ),

            createElement(
                'div',
                { className: 'wompi-block-cards' },
                createElement( 'span', { className: 'wompi-card-badge wompi-visa' },       'Visa' ),
                createElement( 'span', { className: 'wompi-card-badge wompi-mastercard' }, 'Mastercard' ),
                createElement( 'span', { className: 'wompi-card-badge wompi-clave' },      'Clave' )
            ),

            createElement(
                'p',
                { className: 'wompi-block-security' },
                '🔒 ' + __( 'Pago seguro respaldado por Banistmo', 'wompi-panama' )
            )
        );
    };

    // ── Label con logo inline ────────────────────────────────────────────
    const WompiLabel = ( { components } ) => {
        const { PaymentMethodLabel } = components;
        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center' } },
            createElement( PaymentMethodLabel, { text: gatewayTitle } ),
            createElement( WompiLogo )
        );
    };

    // ── Registro ─────────────────────────────────────────────────────────
    registerPaymentMethod( {
        name:           'wompi_panama',
        label:          createElement( WompiLabel ),
        content:        createElement( WompiContent ),
        edit:           createElement( WompiContent ),
        canMakePayment: () => true,
        ariaLabel:      gatewayTitle,
        supports: {
            features: [ 'products' ],
        },
    } );

} )();
