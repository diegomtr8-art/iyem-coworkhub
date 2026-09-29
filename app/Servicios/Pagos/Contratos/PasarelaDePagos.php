<?php

namespace App\Servicios\Pagos\Contratos;

use App\Models\Plane;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Lo que Nódico necesita de una pasarela de cobro con tarjeta.
 *
 * Está sacado de cómo se usa el cobro desde el portal (`CheckoutController`,
 * `SuscripcionController`) y desde la app (`PagosController`,
 * `MembresiaController`), no de lo que ofrece un proveedor. La que se inyecta
 * la decide `config('pagos.pasarela')`.
 *
 * Regla que ninguna implementación rompe: **la pasarela no activa
 * membresías.** Cobra y consulta; la activación la hace `ActivadorDeMembresia`,
 * llamado solo cuando el proveedor confirma el pago (webhook de Stripe, o la
 * consulta del cargo a la API de BBVA).
 */
interface PasarelaDePagos
{
    /** `stripe` | `bbva`. */
    public function nombre(): string;

    /** Cómo se le nombra a la persona («Stripe», «BBVA»). */
    public function etiqueta(): string;

    /** Hay llaves configuradas para cobrar. */
    public function disponible(): bool;

    /** Se puede cobrar este plan con tarjeta (llaves y, si aplica, su precio en la pasarela). */
    public function disponiblePara(Plane $plan): bool;

    /** El plan se cobra solo cada periodo (suscripción) en esta pasarela. */
    public function renuevaSola(Plane $plan): bool;

    /**
     * Prepara el cobro del plan. Lo que devuelve depende de la pasarela, pero
     * siempre trae `modo`:
     *
     *  - `suscripcion` / `pago_unico` (Stripe): la pantalla monta el campo de
     *    tarjeta con `client_secret`.
     *  - `redireccion` (BBVA): la pantalla manda a la persona a `url`, el
     *    formulario del banco, y regresa con el id del cargo.
     *
     * @param  string  $origen  `web` | `app`: a dónde regresa la persona.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function preparar(User $usuario, Plane $plan, bool $exigirConfiguracion = true, string $origen = 'web'): array;

    /**
     * Segundo paso de una suscripción con la tarjeta ya recogida. Devuelve
     * `null` si quedó creada, o lo que la pantalla necesita para terminar la
     * autenticación del banco.
     *
     * @throws ValidationException si la pasarela no cobra solo cada periodo.
     */
    public function suscribir(User $usuario, Plane $plan, string $metodoDePago): ?string;

    /**
     * Protección contra suscripciones duplicadas: `false` si se puede crear;
     * lo que haga falta para retomar la que ya está a medias; excepción si ya
     * hay una que se cobra sola.
     *
     * @throws ValidationException
     */
    public function suscripcionEnCurso(User $usuario): string|false;

    /**
     * Lo que la pantalla «confirmando tu pago» pregunta hasta tener respuesta.
     * Siempre trae `activa`; BBVA añade el estado del cargo y qué hacer.
     *
     * @return array<string, mixed>
     */
    public function estadoDelCobro(User $usuario, ?int $cargoId = null): array;

    /**
     * La renovación automática de esta persona, para «Mi membresía».
     *
     * @return array{metodo_pago: ?array, tiene_recurrente: bool, renovacion_activa: bool, en_periodo_de_gracia: bool}
     */
    public function estadoDeRenovacion(User $usuario): array;

    /** Deja de cobrar solo al final del periodo pagado. `false` si no había nada que cancelar. */
    public function cancelarRenovacion(User $usuario): bool;

    /** Deshace la cancelación mientras siga el periodo pagado. `false` si ya no se puede. */
    public function reactivarRenovacion(User $usuario): bool;

    /** Lo que puede ver el navegador o la app: nunca una llave privada. */
    public function llavePublica(): ?string;
}
