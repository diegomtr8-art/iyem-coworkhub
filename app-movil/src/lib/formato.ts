/**
 * Fechas, dinero y horas en español de México.
 *
 * Se escribe a mano en vez de fiarse de `Intl`: el soporte de locales cambia
 * entre motores y versiones del sistema, y una fecha en inglés en medio de la
 * app se nota enseguida.
 */

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const DIAS_CORTOS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

/**
 * `"2026-09-23"` a `Date` **local**. `new Date("2026-09-23")` la interpreta en
 * UTC y en Mérida se corre al día anterior.
 */
export function fechaLocal(ymd: string): Date {
  const [a, m, d] = ymd.slice(0, 10).split('-').map(Number);
  return new Date(a, (m ?? 1) - 1, d ?? 1);
}

export function aYmd(fecha: Date): string {
  const m = String(fecha.getMonth() + 1).padStart(2, '0');
  const d = String(fecha.getDate()).padStart(2, '0');
  return `${fecha.getFullYear()}-${m}-${d}`;
}

export function hoyYmd(): string {
  return aYmd(new Date());
}

export function sumarDias(ymd: string, dias: number): string {
  const f = fechaLocal(ymd);
  f.setDate(f.getDate() + dias);
  return aYmd(f);
}

function comoFecha(valor: string | Date): Date {
  if (valor instanceof Date) return valor;
  return /^\d{4}-\d{2}-\d{2}$/.test(valor) ? fechaLocal(valor) : new Date(valor);
}

/** «23 de septiembre» */
export function diaMes(valor: string | Date): string {
  const f = comoFecha(valor);
  return `${f.getDate()} de ${MESES[f.getMonth()]}`;
}

/** «martes 23 de septiembre» */
export function diaLargo(valor: string | Date): string {
  const f = comoFecha(valor);
  return `${DIAS[f.getDay()]} ${f.getDate()} de ${MESES[f.getMonth()]}`;
}

/** «23 sep 2026» */
export function fechaCorta(valor: string | Date | null | undefined): string {
  if (!valor) return '—';
  const f = comoFecha(valor);
  return `${f.getDate()} ${MESES_CORTOS[f.getMonth()]} ${f.getFullYear()}`;
}

export function nombreDiaCorto(ymd: string): string {
  return DIAS_CORTOS[fechaLocal(ymd).getDay()];
}

export function nombreMesCorto(ymd: string): string {
  return MESES_CORTOS[fechaLocal(ymd).getMonth()];
}

export function nombreMes(ymd: string): string {
  return MESES[fechaLocal(ymd).getMonth()];
}

/** «Hoy», «Mañana» o «martes 23 de septiembre». */
export function diaRelativo(ymd: string): string {
  const hoy = hoyYmd();
  if (ymd === hoy) return 'Hoy';
  if (ymd === sumarDias(hoy, 1)) return 'Mañana';
  const texto = diaLargo(ymd);
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** «10:00» desde «10:00:00» o un ISO. */
export function hora(valor: string): string {
  if (/^\d{2}:\d{2}/.test(valor)) return valor.slice(0, 5);
  const f = new Date(valor);
  return `${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;
}

export function dinero(valor: number | null | undefined): string {
  const n = Number(valor ?? 0);
  const [entero, dec] = n.toFixed(2).split('.');
  const conMiles = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `$${conMiles}${dec === '00' ? '' : `.${dec}`}`;
}

/** Horas sin ceros de sobra: 2 → «2», 1.5 → «1.5». */
export function horas(valor: number | null | undefined): string {
  const n = Number(valor ?? 0);
  return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

export function plural(n: number, singular: string, pluralTexto?: string): string {
  return `${n} ${n === 1 ? singular : (pluralTexto ?? `${singular}s`)}`;
}

/** Cuenta regresiva humana hasta un instante: «en 2 h 15 min», «en 3 días». */
export function cuentaRegresiva(destino: string | Date, ahora: Date = new Date()): string {
  const ms = comoFecha(destino).getTime() - ahora.getTime();
  if (ms <= 0) return 'ahora';
  const min = Math.floor(ms / 60000);
  if (min < 60) return `en ${plural(Math.max(1, min), 'minuto')}`;
  const h = Math.floor(min / 60);
  if (h < 24) {
    const resto = min % 60;
    return resto ? `en ${h} h ${resto} min` : `en ${plural(h, 'hora')}`;
  }
  const d = Math.round(h / 24);
  return `en ${plural(d, 'día')}`;
}

/** «hace 5 minutos» — para el aviso de datos sin conexión. */
export function haceCuanto(ms: number, ahora: number = Date.now()): string {
  const min = Math.floor((ahora - ms) / 60000);
  if (min < 1) return 'hace un momento';
  if (min < 60) return `hace ${plural(min, 'minuto')}`;
  const h = Math.floor(min / 60);
  if (h < 24) return `hace ${plural(h, 'hora')}`;
  return `hace ${plural(Math.floor(h / 24), 'día')}`;
}

export function primerNombre(nombre: string | null | undefined): string {
  return (nombre ?? '').trim().split(/\s+/)[0] || '';
}

export function saludo(ahora: Date = new Date()): string {
  const h = ahora.getHours();
  if (h < 12) return 'Buenos días';
  if (h < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

/** Minutos desde medianoche de «HH:MM». */
export function aMinutos(hhmm: string): number {
  const [h, m] = hhmm.slice(0, 5).split(':').map(Number);
  return (h ?? 0) * 60 + (m ?? 0);
}

export function deMinutos(min: number): string {
  return `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
}
