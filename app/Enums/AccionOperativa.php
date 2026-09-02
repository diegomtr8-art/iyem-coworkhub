<?php

namespace App\Enums;

/**
 * Acciones sensibles del panel operativo (Fase 3.11).
 *
 * La bitácora de autenticación responde a «¿alguien entró a esta cuenta?». Esta
 * responde a otra pregunta: **«¿quién le tocó las horas a este miembro, cuándo
 * y por qué?»**. Son registros distintos porque se consultan por motivos
 * distintos y se conservan por razones distintas.
 *
 * Enum y no cadenas sueltas por lo mismo de siempre: un valor mal escrito en un
 * controlador es una fila que nadie vuelve a encontrar el día que hace falta.
 */
enum AccionOperativa: string
{
    // Datos personales sensibles: se registra incluso la lectura.
    case ConsultaDatosFiscales = 'consulta_datos_fiscales';
    case CambioDatosFiscales   = 'cambio_datos_fiscales';

    // Bolsas de horas.
    case AjusteHoras = 'ajuste_horas';
    case Sobrecupo   = 'sobrecupo';

    // Membresías.
    case AltaMembresia     = 'alta_membresia';
    case CambioPlan        = 'cambio_plan';
    case SuspensionCuenta  = 'suspension_cuenta';
    case ReactivacionCuenta = 'reactivacion_cuenta';
    case RenovacionMembresia = 'renovacion_membresia';

    // Agenda.
    case ReservaOperativa    = 'reserva_operativa';
    case CancelacionOperativa = 'cancelacion_operativa';
    case BloqueoEspacio      = 'bloqueo_espacio';

    // Asesorías.
    case ConfirmacionAsesoria = 'confirmacion_asesoria';
    case RechazoAsesoria      = 'rechazo_asesoria';

    // Facturación.
    case SolicitudFactura = 'solicitud_factura';
    case ExportacionFiscal = 'exportacion_fiscal';

    // Control de acceso.
    case VinculacionRostro = 'vinculacion_rostro';
    case AperturaPuerta    = 'apertura_puerta';
    case ReconexionTorno   = 'reconexion_torno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConsultaDatosFiscales => 'Consulta de datos fiscales',
            self::CambioDatosFiscales   => 'Cambio de datos fiscales',
            self::AjusteHoras           => 'Ajuste manual de horas',
            self::Sobrecupo             => 'Reserva por encima del cupo',
            self::AltaMembresia         => 'Alta de membresía',
            self::CambioPlan            => 'Cambio de plan',
            self::SuspensionCuenta      => 'Suspensión de cuenta',
            self::ReactivacionCuenta    => 'Reactivación de cuenta',
            self::RenovacionMembresia   => 'Renovación de membresía',
            self::ReservaOperativa      => 'Reserva hecha por recepción',
            self::CancelacionOperativa  => 'Cancelación hecha por recepción',
            self::BloqueoEspacio        => 'Bloqueo de espacio',
            self::ConfirmacionAsesoria  => 'Asesoría confirmada',
            self::RechazoAsesoria       => 'Asesoría rechazada',
            self::SolicitudFactura      => 'Solicitud de factura',
            self::ExportacionFiscal     => 'Exportación de datos fiscales',
            self::VinculacionRostro     => 'Vinculación de rostro con miembro',
            self::AperturaPuerta        => 'Apertura de puerta desde el panel',
            self::ReconexionTorno       => 'Reconexión manual del torno',
        };
    }

    /**
     * Acciones que exigen un motivo escrito.
     *
     * No es burocracia: son las que mueven dinero o saldo y las que alguien
     * tendrá que justificar. Un ajuste de horas sin motivo es indistinguible de
     * un error, y a los seis meses nadie recuerda cuál era cuál.
     */
    public function exigeMotivo(): bool
    {
        return in_array($this, [
            self::AjusteHoras,
            self::Sobrecupo,
            self::SuspensionCuenta,
            self::CambioPlan,
            self::RechazoAsesoria,
        ], true);
    }

    /** Las que se resaltan en la bitácora por tocar datos personales. */
    public function tocaDatosPersonales(): bool
    {
        return in_array($this, [
            self::ConsultaDatosFiscales,
            self::CambioDatosFiscales,
            self::ExportacionFiscal,
        ], true);
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
