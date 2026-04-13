/**
 * Registry: um componente por gateway e método de pagamento.
 * Facilita manutenção: cada gateway tem sua pasta (ex: gateways/spacepag/) com Pix.vue, Card.vue, Boleto.vue.
 * Novos gateways: criar pasta gateways/<slug>/ e registrar abaixo.
 */
import DefaultMethodCard from './DefaultMethodCard.vue';

import SpacepagPix from './spacepag/Pix.vue';
import SpacepagCard from './spacepag/Card.vue';
import SpacepagBoleto from './spacepag/Boleto.vue';

import SapcepagPix from './sapcepag/Pix.vue';
import SapcepagCard from './sapcepag/Card.vue';
import SapcepagBoleto from './sapcepag/Boleto.vue';

import StripeCard from './stripe/Card.vue';

import MercadopagoPix from './mercadopago/Pix.vue';
import MercadopagoCard from './mercadopago/Card.vue';
import MercadopagoBoleto from './mercadopago/Boleto.vue';

/** @type {Record<string, Record<string, import('vue').Component>>} */
export const gatewayMethodComponents = {
    spacepag: {
        pix: SpacepagPix,
        card: SpacepagCard,
        boleto: SpacepagBoleto,
    },
    sapcepag: {
        pix: SapcepagPix,
        card: SapcepagCard,
        boleto: SapcepagBoleto,
    },
    stripe: {
        card: StripeCard,
        pix: DefaultMethodCard,
        boleto: DefaultMethodCard,
    },
    mercadopago: {
        pix: MercadopagoPix,
        card: MercadopagoCard,
        boleto: MercadopagoBoleto,
    },
    pushinpay: {
        pix: DefaultMethodCard,
        card: DefaultMethodCard,
        boleto: DefaultMethodCard,
        pix_auto: DefaultMethodCard,
    },
    asaas: {
        pix: DefaultMethodCard,
        card: DefaultMethodCard,
        boleto: DefaultMethodCard,
    },
    pagarme: {
        pix: DefaultMethodCard,
        card: DefaultMethodCard,
        boleto: DefaultMethodCard,
    },
};

/**
 * Retorna o componente para exibir o card do método no checkout.
 * @param {{ id: string, gateway_slug?: string }} method
 * @returns {import('vue').Component}
 */
export function getMethodCardComponent(method) {
    const slug = (method?.gateway_slug || '').toLowerCase();
    const methodId = method?.id || 'pix';
    const gateway = gatewayMethodComponents[slug];
    const component = gateway?.[methodId];
    return component || DefaultMethodCard;
}
