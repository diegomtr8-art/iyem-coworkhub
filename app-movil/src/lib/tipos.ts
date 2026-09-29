/**
 * Formas de las respuestas de `/api/v1`, tal como las define
 * `docs/API-MOVIL.md`. Si el contrato cambia, cambia aquí primero.
 */

export type Cara = 'miembro' | 'reportes';

export type Usuario = {
  id: number;
  nombre: string;
  email: string;
  correo_verificado: boolean;
  avatar_url: string | null;
  telefono: string | null;
  empresa: string | null;
  ocupacion: string | null;
  face_id_ok: boolean;
  dos_factores_activo: boolean;
  preferencias: { reservas: boolean; membresia: boolean; comunidad: boolean };
  /** Supuesto: el servidor dice qué cara de la app le toca a este token. */
  cara?: Cara;
  contacto_emergencia?: { nombre: string | null; telefono: string | null; parentesco: string | null } | null;
};

export type RespuestaAcceso =
  | { token: string; caduca_por_inactividad_en_dias: number; usuario: Usuario }
  | { requiere_dos_factores: true; desafio: string; caduca_en: string };

export type EstadoServidor = {
  version_minima: { ios: string; android: string };
  version_recomendada?: string;
  mantenimiento: boolean | { activo: boolean; mensaje?: string | null };
  acceso: { google: boolean; enlace_magico: boolean };
  pagos?: { tarjeta: boolean; pasarela?: 'stripe' | 'bbva'; llave_publica?: string | null; referencia?: boolean };
  /** URL base de la web de Nódico (registro, contraseña, seguridad). */
  web?: string;
};

export type EstadoMembresia = {
  tiene: boolean;
  tono: 'bien' | 'atencion' | 'problema';
  titulo: string;
  detalle: string;
  accion: string | null;
  accion_url?: string | null;
};

export type Sugerencia = {
  plan_id: number;
  plan: string;
  precio: number;
  periodo?: string;
  incluye: number | null;
};

export type Bolsa = {
  bolsa: 'sala' | 'contenido' | 'asesoria' | 'dias' | string;
  etiqueta: string;
  unidad: string;
  incluida: boolean;
  ilimitada: boolean;
  cupo: number | null;
  usado: number;
  restante: number | null;
  porcentaje_usado: number;
  tope_diario: number | null;
  reinicia_el: string | null;
  reinicia_texto: string | null;
  casi_agotada: boolean;
  agotada: boolean;
  sugerencia?: Sugerencia | null;
};

export type Inicio = {
  estado_membresia: EstadoMembresia;
  membresia: {
    id: number;
    plan: { id: number; nombre: string; color: string | null };
    rol_en_membresia?: 'titular' | 'acompanante';
    fecha_fin: string;
    dias_restantes: number;
    ciclo_inicio: string;
    ciclo_fin: string;
  } | null;
  bolsas: Bolsa[];
  /** La misma forma que cualquier reserva (`ReservaMovil` en el servidor). */
  proxima_reserva: Reserva | null;
  asesorias_pendientes: number;
  comunicados_sin_leer?: number;
  avisos: Aviso[];
  face_id_pendiente: boolean;
};

export type Aviso = {
  id: number;
  titulo: string;
  contenido: string;
  tipo: string;
  desde: string;
};

export type Comunicado = {
  id: number;
  titulo: string;
  mensaje: string;
  tipo: string;
  leido: boolean;
  fecha?: string;
  creado?: string;
  created_at?: string;
};

export type Espacio = {
  id: number;
  nombre: string;
  tipo: string;
  tipo_etiqueta: string;
  capacidad: number | null;
  descripcion: string | null;
  imagen_url: string | null;
  amenidades: string[];
  bolsa: string;
  bolsa_etiqueta: string;
};

export type Espacios = {
  espacios: Espacio[];
  horizonte: { desde: string; hasta: string } | null;
  operacion: { granularidad_minutos: number; horas_para_cancelar_sin_penalizacion: number };
};

export type DiaDisponible = {
  fecha: string;
  abierto: boolean;
  libres: number;
  total: number;
  motivo_cierre: string | null;
};

/** Un hueco de la franja del día. `hora` es el inicio (HH:mm); dura lo que la granularidad. */
export type Bloque = {
  indice: number;
  hora: string;
  libre: boolean;
  motivo: string | null;
};

export type DisponibilidadDia = {
  fecha: string;
  abierto: boolean;
  motivo_cierre: string | null;
  apertura: string | null;
  cierre: string | null;
  bloques: Bloque[];
  bolsa: string;
  saldo_ciclo: number | null;
  tope_diario: number | null;
  usado_ese_dia: number;
};

export type Reserva = {
  id: number;
  estatus: string;
  espacio: { id: number; nombre: string; tipo: string };
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  horas: number;
  bolsa: string | null;
  bolsa_etiqueta: string | null;
  empieza_en: string;
  termina_en: string;
  cancelar_devuelve: boolean;
  limite_cancelacion: string;
};

/** Respuesta de `POST /reservas`: la reserva va anidada, no en la raíz. */
export type ReservaCreada = {
  reserva: Reserva;
  bolsa_despues?: { bolsa?: string; restante: number | null; usado: number } | null;
  message?: string;
};

export type PlanDescrito = {
  id: number;
  nombre: string;
  subtitulo: string | null;
  precio: number;
  periodo_label?: string | null;
  periodo_etiqueta?: string | null;
  color: string | null;
  personas: number;
  incluye: string[];
  beneficios: string[];
  es_el_actual?: boolean;
  /** Solo en `/planes/contratables`. */
  recurrente?: boolean;
  tarjeta_disponible?: boolean;
  /** Se cobra solo cada periodo. Con BBVA, no: se paga el periodo y se renueva desde el aviso. */
  renueva_sola?: boolean;
};

export type Membresia = {
  estado: EstadoMembresia;
  rol_en_membresia?: 'titular' | 'acompanante';
  titular?: { nombre: string } | null;
  vigente: {
    id: number;
    fecha_inicio: string;
    fecha_fin: string;
    dias_restantes: number;
    precio_pagado: number;
    ciclo_inicio: string;
    ciclo_fin: string;
    proximo_reinicio: string | null;
    plan: PlanDescrito | null;
  } | null;
  bolsas: Bolsa[];
  acompanante: {
    admitido: boolean;
    usuario?: { id: number; nombre: string; email: string } | null;
    face_id_ok?: boolean;
  };
  renovacion?: {
    cobro_en_linea: boolean;
    metodo_pago: { marca: string; ultimos4: string } | null;
    tiene_recurrente: boolean;
    renovacion_activa: boolean;
    en_periodo_de_gracia: boolean;
  } | null;
  historial: {
    id: number;
    plan: string | null;
    color: string | null;
    fecha_inicio: string;
    fecha_fin: string;
    estatus: string;
    precio_pagado: number;
  }[];
};

export type SolicitudAsesoria = {
  id: number;
  tema: string;
  dia_preferido: string;
  horario_preferido: string;
  horas: number;
  estado: string;
  estado_etiqueta: string;
  tono: string;
  pendiente: boolean;
  final: boolean;
  asesor: string | null;
  fecha_confirmada: string | null;
  notas: string | null;
  solicitada_el: string;
};

export type Asesorias = {
  incluida: boolean;
  bolsa: {
    cupo: number | null;
    usado: number;
    restante: number | null;
    tope_diario: number | null;
    reinicia_el: string | null;
  } | null;
  plan_que_la_incluye: { id: number; nombre: string; precio: number; periodo_label?: string } | null;
  oferta: {
    categoria: string;
    etiqueta: string;
    temas: {
      id: number;
      nombre: string;
      descripcion_corta: string | null;
      duracion_min: number | null;
      asesores: { id: number; nombre: string; foto_url: string | null }[];
    }[];
  }[];
  franjas: string[];
  solicitudes: SolicitudAsesoria[];
};

export type OrdenPago = {
  id: number;
  referencia: string;
  plan: string | null;
  monto: number;
  metodo: string;
  metodo_etiqueta: string;
  estado_pago: string;
  estado_pago_etiqueta: string;
  estado_factura: string;
  estado_factura_etiqueta: string;
  pide_factura: boolean;
  vence_el: string | null;
  reportado: boolean;
  tiene_pdf: boolean;
  tiene_xml: boolean;
  creada: string | null;
  datos_bancarios?: { banco: string; clabe: string; beneficiario: string; cuenta: string } | null;
  instrucciones?: string[] | string | null;
};

export type Cobro = {
  id: number;
  folio: string | null;
  concepto: string | null;
  total: number;
  estatus: string;
  metodo_pago: string | null;
  fecha: string | null;
  fecha_pago: string | null;
};

export type Factura = {
  id: number;
  referencia: string;
  plan: string | null;
  monto: number;
  folio_fiscal: string | null;
  emitida_en: string | null;
  tiene_xml: boolean;
};

export type Contratables = {
  planes: PlanDescrito[];
  metodos: { tarjeta: boolean; pasarela?: 'stripe' | 'bbva'; referencia: { valor: string; etiqueta: string }[] };
  tiene_datos_fiscales: boolean;
};

/**
 * Lo que devuelve `POST /pagos/tarjeta`, según la pasarela del servidor:
 * la hoja de pago de Stripe, o el formulario del banco (BBVA).
 */
export type PreparacionTarjeta =
  | {
      modo: 'suscripcion' | 'pago_unico';
      pasarela?: 'stripe';
      /** `setup` en planes recurrentes (se guarda la tarjeta y luego se suscribe); `payment` en pago único. */
      tipo_intent: 'setup' | 'payment';
      client_secret: string;
      llave_publica: string;
      nombre_comercio: string;
    }
  | {
      modo: 'redireccion';
      pasarela: 'bbva';
      /** El formulario de BBVA: ahí se teclea la tarjeta y se pasa el 3-D Secure. */
      url: string;
      cargo_id: number;
      nombre_comercio: string;
    };

/** `GET /pagos/tarjeta/estado`. Con BBVA trae el estado del cargo. */
export type EstadoCobroTarjeta = {
  activa: boolean;
  estado?: 'creando' | 'pendiente' | 'completado' | 'fallido' | 'cancelado' | 'abandonado' | 'devuelto' | 'en_revision' | 'desconocido';
  mensaje?: string;
  url_pago?: string | null;
};

export type ResultadoSuscripcion = { requiere_accion: boolean; client_secret: string | null };

export type Credencial = {
  nombre: string;
  avatar_url: string | null;
  plan: string | null;
  color_plan: string | null;
  vigente_hasta: string | null;
  face_id_ok: boolean;
  codigo: string | null;
  emitida_en: string | null;
  valida_hasta: string | null;
};

export type Dispositivo = {
  id: number;
  nombre: string;
  plataforma: string | null;
  ultimo_uso: string | null;
  creado: string;
  es_este: boolean;
};

export type DatosFiscales = {
  datos: {
    rfc: string | null;
    razon_social: string | null;
    regimen_fiscal: string | null;
    uso_cfdi: string | null;
    codigo_postal: string | null;
    email_facturacion: string | null;
  } | null;
  completos: boolean;
  regimenes: { clave: string; nombre: string; personas?: string[] | string }[];
  usos_cfdi: { clave: string; nombre: string }[];
  uso_predeterminado?: string | null;
  proceso: { emisor: string; dias_habiles: number; contacto: string | null };
};

export type DocumentoPendiente = {
  documento: string;
  version: string;
  titulo: string;
  url: string | null;
};

export type Paginado<T> = { data: T[]; meta?: { siguiente: string | null } };

// ── Reportes ─────────────────────────────────────────────────────────────

export type Rango = { desde: string; hasta: string; etiqueta: string; dias: number };

export type ReporteResumen = {
  rango: Rango;
  ocupacion_media_pct?: number;
  ingresos?: number;
  tasa_no_show_pct?: number;
  miembros_en_riesgo?: number;
  [clave: string]: unknown;
};

export type ReporteOcupacion = {
  rango?: Rango;
  por_espacio: {
    espacio: string;
    tipo: string;
    reservas: number;
    horas: number;
    capacidad_horas: number;
    ocupacion_pct: number;
  }[];
  por_franja: { hora: string; reservas: number; pct: number }[];
};

export type ReporteIngresos = {
  rango?: Rango;
  por_plan: { plan: string; membresias: number; ingreso: number }[];
  salones: { eventos: number; ingreso: number; cobrado: number };
  total_membresias: number;
  facturado_pagado: number;
};

export type ReporteNoShow = {
  rango?: Rango;
  total_reservas: number;
  no_show: number;
  tasa_pct: number;
  horas_perdidas: number;
  por_miembro: { miembro: string; faltas: number; horas: number }[];
};

export type ReporteConsumo = {
  rango?: Rango;
  filas: FilaConsumo[];
};

export type FilaConsumo = {
  plan: string;
  bolsa: string;
  miembros: number;
  incluidas_por_miembro: number;
  usadas_total: number;
  promedio_por_miembro: number;
  aprovechamiento_pct: number;
  al_tope: number;
};

export type MiembroEnRiesgo = {
  miembro: string;
  email: string | null;
  telefono: string | null;
  plan: string | null;
  vence: string | null;
  ultimo_acceso: string | null;
  dias_sin_venir: number | null;
};
