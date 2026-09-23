/**
 * Vista previa web: no hay SDK de Stripe. El pago con tarjeta se prueba en el
 * teléfono; aquí cada llamada devuelve un error legible en vez de romper.
 */
const noDisponible = { error: { code: 'Failed', message: 'El pago con tarjeta solo funciona en el teléfono.' } };

export async function initStripe(_opciones: unknown): Promise<void> {}
export async function initPaymentSheet(_opciones: unknown) {
  return noDisponible;
}
export async function presentPaymentSheet() {
  return noDisponible;
}
export async function handleNextAction(_secreto: string, _retorno?: string) {
  return noDisponible;
}
