/**
 * FE-12 — el sitio público tipaba todo como `any`, justo donde más ayudaba:
 * los nombres de campo del seeder. Un `periodo_label` mal escrito no daba
 * error, simplemente desaparecía de la pantalla.
 *
 * Estas interfaces reflejan las columnas reales de los modelos.
 */

export interface Plan {
  id?: number
  nombre: string
  subtitulo?: string | null
  tipo?: string
  precio: number
  periodo_label?: string | null
  cta_label?: string | null
  descripcion_corta?: string | null
  descripcion_larga?: string | null
  beneficios?: string[] | null
  stripe_url?: string | null
  color?: string | null
  imagen?: string | null
  personas?: number | null
  destacado?: boolean
  activo?: boolean
  orden?: number
}

export interface Salon {
  id?: number
  nombre: string
  descripcion?: string | null
  medidas?: string | null
  precio_hora?: number | string | null
  capacidad?: number | null
  cap_herradura?: number | null
  cap_mesas?: number | null
  cap_escuela?: number | null
  cap_auditorio?: number | null
  incluye?: string[] | null
  imagen?: string | null
  publicado?: boolean
  orden?: number
}

export interface Evento {
  id?: number
  titulo: string
  descripcion?: string | null
  fecha?: string | null
  hora_inicio?: string | null
  hora_fin?: string | null
  lugar?: string | null
  imagen?: string | null
  cupo_maximo?: number | null
  precio?: number | null
  solo_miembros?: boolean
  activo?: boolean
}

export interface Emprendedor {
  id?: number
  nombre: string
  instagram?: string | null
  foto?: string | null
  descripcion?: string | null
  url_destino?: string | null
  /** Accesor del modelo: url_destino, o el Instagram del negocio. */
  enlace?: string | null
  destacado_semana?: boolean
  activo?: boolean
  orden?: number
}

/** Datos de contacto y redes compartidos por el middleware de Inertia. */
export interface DatosNodico {
  email?: string
  telefono?: string
  telefonoE164?: string
  direccion?: string
  direccionCorta?: string
  mapsUrl?: string
  mapsEmbed?: string
  horarios?: string
  horariosDetalle?: string
  redes?: { instagram?: string; facebook?: string; linkedin?: string }
  instagram?: string
}
