import Constants from 'expo-constants';
import { Platform } from 'react-native';

import { almacen } from './almacen';

/**
 * Cliente de `/api/v1`.
 *
 * Una sola puerta de salida para que las cabeceras del contrato (§4.1) y el
 * tratamiento de errores (§4.3) vivan en un sitio: ninguna pantalla arma un
 * `fetch` por su cuenta.
 */

export const URL_API = (process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.10.6:8010/api/v1').replace(/\/+$/, '');

/** La web de Nódico: la misma base sin `/api/v1`. Para lo que se hace en el navegador. */
export const URL_WEB = URL_API.replace(/\/api\/v\d+$/, '');

export const VERSION_APP = Constants.expoConfig?.version ?? '1.0.0';

const PLATAFORMA = Platform.OS === 'ios' ? 'ios' : 'android';

/** Códigos que cambian lo que la app hace, no solo el mensaje. */
export type CodigoGlobal =
  'no_autenticado' | 'cuenta_suspendida' | 'correo_sin_verificar' | 'consentimiento_pendiente' | 'cuenta_operativa' | 'version_obsoleta';

const CODIGOS_GLOBALES: ReadonlySet<string> = new Set<CodigoGlobal>([
  'no_autenticado',
  'cuenta_suspendida',
  'correo_sin_verificar',
  'consentimiento_pendiente',
  'version_obsoleta',
]);

export class ErrorApi extends Error {
  constructor(
    public readonly estado: number,
    mensaje: string,
    public readonly codigo: string | null = null,
    public readonly errores: Record<string, string[]> = {},
    public readonly cuerpo: Record<string, unknown> = {},
  ) {
    super(mensaje);
    this.name = 'ErrorApi';
  }

  get sinConexion(): boolean {
    return this.estado === 0;
  }

  /** El primer error de un campo, listo para pintarlo debajo de él. */
  campo(nombre: string): string | undefined {
    return this.errores[nombre]?.[0];
  }

  /** El primer error de cualquier campo, o el mensaje general. */
  get primero(): string {
    const primero = Object.values(this.errores)[0]?.[0];
    return primero ?? this.message;
  }
}

type Oyente = (error: ErrorApi) => void;
let oyenteGlobal: Oyente | null = null;

/** El proveedor de sesión se suscribe aquí para reaccionar a 401, suspensión, etc. */
export function escucharErroresGlobales(oyente: Oyente): () => void {
  oyenteGlobal = oyente;
  return () => {
    if (oyenteGlobal === oyente) oyenteGlobal = null;
  };
}

type Opciones = {
  metodo?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  cuerpo?: unknown;
  consulta?: Record<string, string | number | boolean | null | undefined>;
  /** Obligatoria en las escrituras que no pueden duplicarse (§4.1). */
  idempotencia?: string;
  /** Sin token, aunque haya uno guardado (rutas de acceso). */
  anonima?: boolean;
  /** No avisar al proveedor de sesión (p. ej. el propio cierre de sesión). */
  silenciosa?: boolean;
  tiempoLimiteMs?: number;
};

export async function cabecerasBase(): Promise<Record<string, string>> {
  const cabeceras: Record<string, string> = {
    Accept: 'application/json',
    'X-App-Version': VERSION_APP,
    'X-App-Plataforma': PLATAFORMA,
  };
  const token = await almacen.token();
  if (token) cabeceras.Authorization = `Bearer ${token}`;
  return cabeceras;
}

function construirUrl(ruta: string, consulta?: Opciones['consulta']): string {
  const url = `${URL_API}${ruta.startsWith('/') ? ruta : `/${ruta}`}`;
  if (!consulta) return url;
  const partes = Object.entries(consulta)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
  return partes.length ? `${url}?${partes.join('&')}` : url;
}

export function urlDe(ruta: string, consulta?: Opciones['consulta']): string {
  return construirUrl(ruta, consulta);
}

/**
 * Hace la petición y devuelve el cuerpo **completo** (`{ data, meta, message }`).
 * Para lo habitual usa `api()`, que ya desenvuelve `data`.
 */
export async function peticion<T = unknown>(ruta: string, opciones: Opciones = {}): Promise<T> {
  const { metodo = 'GET', cuerpo, consulta, idempotencia, anonima, silenciosa, tiempoLimiteMs = 20000 } = opciones;

  const cabeceras = await cabecerasBase();
  if (anonima) delete cabeceras.Authorization;
  if (idempotencia) cabeceras['Idempotency-Key'] = idempotencia;

  let body: BodyInit | undefined;
  if (cuerpo instanceof FormData) {
    body = cuerpo;
  } else if (cuerpo !== undefined) {
    cabeceras['Content-Type'] = 'application/json';
    body = JSON.stringify(cuerpo);
  }

  const control = new AbortController();
  const reloj = setTimeout(() => control.abort(), tiempoLimiteMs);

  let respuesta: Response;
  try {
    respuesta = await fetch(construirUrl(ruta, consulta), {
      method: metodo,
      headers: cabeceras,
      body,
      signal: control.signal,
    });
  } catch {
    throw new ErrorApi(0, 'Sin conexión. Revisa tu internet y vuelve a intentarlo.', 'sin_conexion');
  } finally {
    clearTimeout(reloj);
  }

  const texto = await respuesta.text();
  let json: Record<string, unknown> = {};
  if (texto) {
    try {
      json = JSON.parse(texto);
    } catch {
      json = {};
    }
  }

  if (respuesta.ok) {
    return json as T;
  }

  const error = new ErrorApi(
    respuesta.status,
    typeof json.message === 'string' && json.message ? json.message : mensajePorEstado(respuesta.status),
    typeof json.codigo === 'string' ? json.codigo : codigoPorEstado(respuesta.status),
    (json.errors as Record<string, string[]>) ?? {},
    json,
  );

  if (!silenciosa && oyenteGlobal && error.codigo && CODIGOS_GLOBALES.has(error.codigo)) {
    oyenteGlobal(error);
  }

  throw error;
}

/** Desenvuelve `data`. Es lo que usan casi todas las pantallas. */
export async function api<T = unknown>(ruta: string, opciones: Opciones = {}): Promise<T> {
  const cuerpo = await peticion<{ data?: T } & Record<string, unknown>>(ruta, opciones);
  return (cuerpo && 'data' in cuerpo ? cuerpo.data : cuerpo) as T;
}

function codigoPorEstado(estado: number): string | null {
  if (estado === 401) return 'no_autenticado';
  if (estado === 429) return 'demasiadas_peticiones';
  return null;
}

function mensajePorEstado(estado: number): string {
  switch (estado) {
    case 401:
      return 'Tu sesión terminó. Vuelve a entrar.';
    case 403:
      return 'No tienes permiso para hacer esto.';
    case 404:
      return 'No encontramos eso. Puede que ya no exista.';
    case 409:
      return 'Eso cambió mientras tanto. Actualiza y vuelve a intentarlo.';
    case 422:
      return 'Revisa los datos.';
    case 429:
      return 'Vas muy rápido. Espera un momento y vuelve a intentarlo.';
    default:
      return 'Algo salió mal de nuestro lado. Inténtalo en un momento.';
  }
}

/** Texto para un 429: dice cuánto esperar si el servidor lo mandó. */
export function mensajeDeEspera(error: ErrorApi): string {
  const segundos = Number(error.cuerpo.reintentar_en);
  if (error.estado === 429 && Number.isFinite(segundos) && segundos > 0) {
    return segundos < 60
      ? `Vas muy rápido. Vuelve a intentarlo en ${segundos} segundos.`
      : `Vas muy rápido. Vuelve a intentarlo en ${Math.ceil(segundos / 60)} minutos.`;
  }
  return error.message;
}
