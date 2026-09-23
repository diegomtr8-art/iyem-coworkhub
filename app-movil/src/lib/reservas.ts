import { useMutation, useQueryClient } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { Alert } from 'react-native';

import { api, ErrorApi } from './api';
import { horas } from './formato';
import { cancelarRecordatorioLocal } from './nativo';
import type { Reserva } from './tipos';

/**
 * ¿Cancelar ahora devuelve las horas? Se recalcula con `limite_cancelacion`
 * para que el texto cambie en el momento exacto aunque la pantalla lleve rato
 * abierta. Es solo el aviso: al cancelar manda la respuesta del servidor.
 */
export function devuelveSiCancelaAhora(r: Reserva, ahora: Date = new Date()): boolean {
  return r.cancelar_devuelve && new Date(r.limite_cancelacion).getTime() > ahora.getTime();
}

export function textoDeCancelacion(r: Reserva): string {
  return devuelveSiCancelaAhora(r)
    ? `Las ${horas(r.horas)} h vuelven a tu bolsa.`
    : 'Faltan menos de 2 horas: la reserva se cancela, pero las horas se consumen igual.';
}

/** Cancelar con confirmación que dice, antes de confirmar, si devuelve las horas. */
export function useCancelarReserva(alTerminar?: (mensaje: string) => void) {
  const cliente = useQueryClient();

  const mutacion = useMutation({
    mutationFn: (r: Reserva) => api<{ devolvio_horas: boolean; message?: string }>(`/reservas/${r.id}/cancelar`, { metodo: 'POST' }),
    onSuccess: (respuesta, r) => {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      void cancelarRecordatorioLocal(r.id);
      void cliente.invalidateQueries({ queryKey: ['reservas'] });
      void cliente.invalidateQueries({ queryKey: ['inicio'] });
      void cliente.invalidateQueries({ queryKey: ['disponibilidad'] });
      void cliente.invalidateQueries({ queryKey: ['membresia'] });
      alTerminar?.(
        respuesta?.message ??
          (respuesta?.devolvio_horas
            ? 'Reserva cancelada. Las horas vuelven a tu cuenta.'
            : 'Reserva cancelada. Las horas se consumen igual.'),
      );
    },
    onError: (e) => {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error).catch(() => {});
      Alert.alert('No se pudo cancelar', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.');
    },
  });

  const pedir = (r: Reserva, alDescartar?: () => void) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    Alert.alert(
      '¿Cancelar esta reserva?',
      `${r.espacio.nombre}, ${r.hora_inicio.slice(0, 5)}–${r.hora_fin.slice(0, 5)}.\n\n${textoDeCancelacion(r)}`,
      [
        { text: 'Conservarla', style: 'cancel', onPress: alDescartar },
        { text: 'Cancelar reserva', style: 'destructive', onPress: () => mutacion.mutate(r) },
      ],
    );
  };

  return { pedir, cancelando: mutacion.isPending };
}
