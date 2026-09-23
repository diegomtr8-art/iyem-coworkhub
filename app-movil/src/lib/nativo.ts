import { File, Paths } from 'expo-file-system';
import * as Notifications from 'expo-notifications';
import * as Sharing from 'expo-sharing';
import { Platform, Share } from 'react-native';

import { almacen } from './almacen';
import { api, cabecerasBase, urlDe } from './api';
import { fechaLocal } from './formato';
import { enExpoGo } from './ganchos';

/**
 * Lo nativo en un solo sitio: calendario, compartir, descargas y push. Cada
 * función falla en silencio o con un mensaje claro, nunca tumba la pantalla.
 */

/**
 * `expo-calendar` no viene dentro de Expo Go (SDK 57). Solo existe en una
 * compilación propia; en Expo Go el botón no se muestra.
 */
export const calendarioDisponible = !enExpoGo;

/**
 * Añade una reserva al calendario con el formulario del propio sistema: la
 * persona ve el evento, elige calendario y confirma. El módulo se carga aquí
 * dentro y no arriba del archivo: importarlo en Expo Go, donde no existe,
 * tumbaría toda pantalla que use este archivo.
 */
export async function anadirAlCalendario(r: { titulo: string; fecha: string; inicio: string; fin: string; notas?: string }) {
  if (!calendarioDisponible) return null;
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const CalendarioLegacy = require('expo-calendar/legacy') as typeof import('expo-calendar/legacy');
  const [hi, mi] = r.inicio.split(':').map(Number);
  const [hf, mf] = r.fin.split(':').map(Number);
  const inicio = fechaLocal(r.fecha);
  inicio.setHours(hi, mi, 0, 0);
  const fin = fechaLocal(r.fecha);
  fin.setHours(hf, mf, 0, 0);

  return CalendarioLegacy.createEventInCalendarAsync({
    title: r.titulo,
    startDate: inicio,
    endDate: fin,
    location: 'Nódico · Instituto Yucateco de Emprendedores, Mérida',
    notes: r.notas,
  });
}

export async function compartirTexto(mensaje: string) {
  try {
    await Share.share({ message: mensaje });
  } catch {
    // Cancelar la hoja de compartir no es un error.
  }
}

/**
 * Descarga un archivo protegido de la API (factura, CSV) con el token y abre
 * la hoja de compartir del sistema para guardarlo o mandarlo.
 */
export async function descargarYCompartir(ruta: string, nombre: string, tipo: string, consulta?: Record<string, string>) {
  const destino = new File(Paths.cache, nombre);
  const archivo = await File.downloadFileAsync(urlDe(ruta, consulta), destino, {
    headers: await cabecerasBase(),
    idempotent: true,
  });
  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(archivo.uri, {
      mimeType: tipo,
      dialogTitle: nombre,
      UTI: tipo === 'application/pdf' ? 'com.adobe.pdf' : undefined,
    });
  }
  return archivo.uri;
}

/**
 * Pide permiso de notificaciones **cuando tiene sentido** —después de la
 * primera reserva— y registra el token en el servidor. Nunca al abrir la app
 * por primera vez.
 *
 * En Expo Go en Android no hay push remoto (desde SDK 53), así que ahí se
 * omite sin molestar.
 */
export async function registrarPushTrasPrimeraReserva(): Promise<void> {
  if (await almacen.pushRegistrado()) return;
  if (enExpoGo && Platform.OS === 'android') return;

  try {
    const actual = await Notifications.getPermissionsAsync();
    let concedido = actual.granted;
    if (!concedido && actual.canAskAgain) {
      concedido = (await Notifications.requestPermissionsAsync()).granted;
    }
    if (!concedido) {
      await almacen.guardarPushRegistrado(true); // No se vuelve a insistir.
      return;
    }
    const token = await Notifications.getExpoPushTokenAsync();
    await api('/yo/push', { metodo: 'PUT', cuerpo: { expo_push_token: token.data, plataforma: Platform.OS } });
    await almacen.guardarPushRegistrado(true);
  } catch {
    // Sin proyecto de EAS configurado (Expo Go) no hay token: no pasa nada.
  }
}

/** Recordatorio **local** de una reserva (funciona también en Expo Go). */
export async function programarRecordatorioLocal(r: { id: number; espacio: string; fecha: string; inicio: string }) {
  try {
    // Si este teléfono ya recibe los avisos del servidor, el recordatorio de una
    // hora antes llega por ahí: programarlo también aquí serían dos avisos.
    if (await almacen.pushRegistrado()) return;
    const permiso = await Notifications.getPermissionsAsync();
    if (!permiso.granted) return;
    const [h, m] = r.inicio.split(':').map(Number);
    const cuando = fechaLocal(r.fecha);
    cuando.setHours(h, m, 0, 0);
    cuando.setMinutes(cuando.getMinutes() - 60);
    if (cuando.getTime() <= Date.now()) return;
    await Notifications.scheduleNotificationAsync({
      identifier: `reserva-${r.id}`,
      content: { title: 'Tu reserva es en una hora', body: `${r.espacio} a las ${r.inicio}. Te esperamos en Nódico.` },
      trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: cuando },
    });
  } catch {
    // Opcional: si el sistema no deja, la reserva sigue igual.
  }
}

export async function cancelarRecordatorioLocal(id: number) {
  try {
    await Notifications.cancelScheduledNotificationAsync(`reserva-${id}`);
  } catch {
    // Nada que cancelar.
  }
}

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

/**
 * Al cerrar sesión: fuera los recordatorios que programó esta cuenta, para que
 * en un teléfono compartido la siguiente persona no reciba los de la anterior.
 */
export async function cancelarTodosLosRecordatorios() {
  try {
    await Notifications.cancelAllScheduledNotificationsAsync();
  } catch {
    // Sin permiso o sin módulo (web): no hay nada que cancelar.
  }
}
