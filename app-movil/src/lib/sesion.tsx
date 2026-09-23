import { useIsRestoring, useQueryClient } from '@tanstack/react-query';
import * as Device from 'expo-device';
import * as LocalAuthentication from 'expo-local-authentication';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState, Platform } from 'react-native';

import { almacen } from './almacen';
import { api, ErrorApi, escucharErroresGlobales, peticion } from './api';
import { claves } from './consultas';
import { cancelarTodosLosRecordatorios } from './nativo';
import type { Cara, RespuestaAcceso, Usuario } from './tipos';

/**
 * Estado de la sesión y de todo lo que puede frenar a la persona antes de
 * usar la app: cuenta suspendida, correo sin verificar, consentimiento
 * pendiente o versión vieja. Cada caso tiene su pantalla explicada; aquí solo
 * se decide cuál toca.
 */

export type Bloqueo = {
  codigo: 'cuenta_suspendida' | 'correo_sin_verificar' | 'consentimiento_pendiente' | 'cuenta_operativa';
  mensaje: string;
  detalle?: string | null;
  cuerpo: Record<string, unknown>;
};

type Fase = 'arrancando' | 'fuera' | 'dentro';

type Contexto = {
  fase: Fase;
  usuario: Usuario | null;
  cara: Cara;
  bloqueo: Bloqueo | null;
  versionObsoleta: boolean;
  bloqueadaPorBiometria: boolean;
  avisoDeSalida: string | null;
  entrar: (respuesta: Extract<RespuestaAcceso, { token: string }>) => Promise<void>;
  salir: (motivo?: string) => Promise<void>;
  refrescarUsuario: () => Promise<Usuario | null>;
  limpiarBloqueo: () => void;
  desbloquear: () => Promise<boolean>;
  datosDeDispositivo: () => Promise<{ dispositivo: string; dispositivo_id: string }>;
  olvidarAviso: () => void;
};

const ContextoSesion = createContext<Contexto | null>(null);

/** Si la app pasa más de esto en segundo plano, se vuelve a pedir la biometría. */
const MS_PARA_REBLOQUEAR = 60_000;

export function ProveedorSesion({ children }: { children: ReactNode }) {
  const cliente = useQueryClient();
  const restaurando = useIsRestoring();
  const [fase, setFase] = useState<Fase>('arrancando');
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [bloqueo, setBloqueo] = useState<Bloqueo | null>(null);
  const [versionObsoleta, setVersionObsoleta] = useState(false);
  const [bloqueadaPorBiometria, setBloqueadaPorBiometria] = useState(false);
  const [avisoDeSalida, setAvisoDeSalida] = useState<string | null>(null);
  const salioAlFondo = useRef<number | null>(null);

  const cerrarLocal = useCallback(
    async (motivo?: string) => {
      await cancelarTodosLosRecordatorios();
      await almacen.limpiarCuenta();
      cliente.clear();
      setUsuario(null);
      setBloqueo(null);
      setBloqueadaPorBiometria(false);
      setAvisoDeSalida(motivo ?? null);
      setFase('fuera');
    },
    [cliente],
  );

  // Errores que cambian el rumbo de toda la app, vengan de la pantalla que vengan.
  useEffect(
    () =>
      escucharErroresGlobales((error) => {
        switch (error.codigo) {
          case 'no_autenticado':
            void cerrarLocal('Tu sesión terminó. Vuelve a entrar.');
            break;
          case 'version_obsoleta':
            setVersionObsoleta(true);
            break;
          case 'cuenta_suspendida':
          case 'correo_sin_verificar':
          case 'consentimiento_pendiente':
            setBloqueo({
              codigo: error.codigo,
              mensaje: error.message,
              detalle: typeof error.cuerpo.detalle === 'string' ? error.cuerpo.detalle : null,
              cuerpo: error.cuerpo,
            });
            break;
        }
      }),
    [cerrarLocal],
  );

  const refrescarUsuario = useCallback(async (): Promise<Usuario | null> => {
    try {
      const yo = await api<Usuario>('/yo');
      setUsuario(yo);
      cliente.setQueryData(claves.yo, yo);
      setBloqueo(null);
      return yo;
    } catch (e) {
      // Sin red se sigue con lo último que se sabía: la app funciona sin conexión.
      if (e instanceof ErrorApi && e.sinConexion) {
        const guardado = cliente.getQueryData<Usuario>(claves.yo);
        if (guardado) setUsuario(guardado);
        return guardado ?? null;
      }
      return null;
    }
  }, [cliente]);

  // Arranque: ¿hay token? ¿sigue valiendo?
  useEffect(() => {
    // Primero se recupera la caché del disco: con ella se arranca aunque no haya red.
    if (restaurando) return;
    let vivo = true;
    (async () => {
      const token = await almacen.token();
      if (!token) {
        if (vivo) setFase('fuera');
        return;
      }
      const guardado = cliente.getQueryData<Usuario>(claves.yo);
      if (guardado) setUsuario(guardado);
      if (await almacen.biometria()) setBloqueadaPorBiometria(true);
      if (vivo) setFase('dentro');
      void refrescarUsuario();
    })();
    return () => {
      vivo = false;
    };
    // Solo al arrancar: después, la fase la mueven entrar() y salir().
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [restaurando]);

  // Volver a abrir tras un rato en segundo plano vuelve a pedir la biometría.
  useEffect(() => {
    const sub = AppState.addEventListener('change', async (estado) => {
      if (estado === 'background') {
        salioAlFondo.current = Date.now();
      } else if (estado === 'active' && salioAlFondo.current !== null) {
        const fuera = Date.now() - salioAlFondo.current;
        salioAlFondo.current = null;
        if (fase === 'dentro' && fuera > MS_PARA_REBLOQUEAR && (await almacen.biometria())) {
          setBloqueadaPorBiometria(true);
        }
      }
    });
    return () => sub.remove();
  }, [fase]);

  const entrar = useCallback<Contexto['entrar']>(
    async (respuesta) => {
      await almacen.guardarToken(respuesta.token);
      cliente.clear();
      setUsuario(respuesta.usuario);
      cliente.setQueryData(claves.yo, respuesta.usuario);
      setBloqueo(null);
      setAvisoDeSalida(null);
      setFase('dentro');
    },
    [cliente],
  );

  const salir = useCallback<Contexto['salir']>(
    async (motivo) => {
      try {
        await peticion('/auth/salir', { metodo: 'POST', silenciosa: true, tiempoLimiteMs: 6000 });
      } catch {
        // Aunque el servidor no conteste, en este teléfono la sesión se cierra.
      }
      await cerrarLocal(motivo);
    },
    [cerrarLocal],
  );

  const desbloquear = useCallback(async () => {
    const hay = await LocalAuthentication.hasHardwareAsync();
    const inscrita = await LocalAuthentication.isEnrolledAsync();
    // Sin biometría configurada no se bloquea: sería dejar a la persona fuera de su app.
    if (!hay || !inscrita) {
      setBloqueadaPorBiometria(false);
      return true;
    }
    const resultado = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Desbloquea Nódico',
      cancelLabel: 'Cancelar',
      fallbackLabel: 'Usar código',
    });
    if (resultado.success) setBloqueadaPorBiometria(false);
    return resultado.success;
  }, []);

  const datosDeDispositivo = useCallback(async () => {
    const modelo = Device.modelName ?? (Platform.OS === 'ios' ? 'iPhone' : 'Android');
    const sistema = `${Platform.OS === 'ios' ? 'iOS' : 'Android'} ${Device.osVersion ?? ''}`.trim();
    return { dispositivo: `${modelo} · ${sistema}`.slice(0, 120), dispositivo_id: await almacen.dispositivoId() };
  }, []);

  const valor = useMemo<Contexto>(
    () => ({
      fase,
      usuario,
      cara: usuario?.cara ?? 'miembro',
      bloqueo,
      versionObsoleta,
      bloqueadaPorBiometria,
      avisoDeSalida,
      entrar,
      salir,
      refrescarUsuario,
      limpiarBloqueo: () => setBloqueo(null),
      desbloquear,
      datosDeDispositivo,
      olvidarAviso: () => setAvisoDeSalida(null),
    }),
    [
      fase,
      usuario,
      bloqueo,
      versionObsoleta,
      bloqueadaPorBiometria,
      avisoDeSalida,
      entrar,
      salir,
      refrescarUsuario,
      desbloquear,
      datosDeDispositivo,
    ],
  );

  return <ContextoSesion.Provider value={valor}>{children}</ContextoSesion.Provider>;
}

export function useSesion(): Contexto {
  const c = useContext(ContextoSesion);
  if (!c) throw new Error('useSesion fuera de ProveedorSesion');
  return c;
}
