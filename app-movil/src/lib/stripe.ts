/**
 * La hoja de pago nativa de Stripe. Se importa desde aquí y no directo del
 * paquete para que la vista previa web (`stripe.web.ts`) no lo cargue: el SDK
 * de Stripe solo existe en iOS y Android.
 */
export { handleNextAction, initPaymentSheet, initStripe, presentPaymentSheet } from '@stripe/stripe-react-native';
