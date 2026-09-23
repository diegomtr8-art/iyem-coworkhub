import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { focusManager, onlineManager, QueryClient } from '@tanstack/react-query';
import { AppState, type AppStateStatus } from 'react-native';

import { ErrorApi } from './api';

/**
 * Caché de datos para que la app **no se ponga en blanco sin señal**: lo último
 * que se cargó se conserva en disco y se muestra con un aviso honesto.
 *
 * Solo se persiste lo que no es sensible. Las consultas con `meta.persistir =
 * false` (datos fiscales, reportes, dispositivos) viven solo en memoria; la
 * credencial tiene su propio guardado en el almacén seguro.
 */

onlineManager.setEventListener((setOnline) =>
  NetInfo.addEventListener((estado) => {
    setOnline(estado.isConnected !== false && estado.isInternetReachable !== false);
  }),
);

function alCambiarApp(estado: AppStateStatus) {
  focusManager.setFocused(estado === 'active');
}
AppState.addEventListener('change', alCambiarApp);

export const clienteConsultas = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      gcTime: 1000 * 60 * 60 * 24 * 7,
      retry: (intentos, error) => {
        // No se reintenta lo que el servidor rechazó con razón.
        if (error instanceof ErrorApi && error.estado >= 400 && error.estado < 500) return false;
        return intentos < 2;
      },
      networkMode: 'offlineFirst',
    },
    mutations: {
      networkMode: 'always',
      retry: false,
    },
  },
});

export const persistidor = createAsyncStoragePersister({
  storage: AsyncStorage,
  key: 'nodico.cache.v1',
  throttleTime: 1500,
});

export const opcionesDePersistencia = {
  persister: persistidor,
  maxAge: 1000 * 60 * 60 * 24 * 7,
  buster: 'v1',
  dehydrateOptions: {
    shouldDehydrateQuery: (consulta: { state: { status: string }; meta?: Record<string, unknown> }) =>
      consulta.state.status === 'success' && consulta.meta?.persistir !== false,
  },
};

/** Claves de consulta en un solo sitio, para invalidar sin adivinar. */
export const claves = {
  estado: ['estado'] as const,
  yo: ['yo'] as const,
  inicio: ['inicio'] as const,
  espacios: ['espacios'] as const,
  periodo: (espacio: number, desde: string, hasta: string) => ['disponibilidad', espacio, desde, hasta] as const,
  dia: (espacio: number, fecha: string) => ['disponibilidad', espacio, 'dia', fecha] as const,
  reservas: (tipo: 'proximas' | 'pasadas') => ['reservas', tipo] as const,
  membresia: ['membresia'] as const,
  asesorias: ['asesorias'] as const,
  pagos: ['pagos'] as const,
  pago: (id: number) => ['pagos', id] as const,
  cobros: ['cobros'] as const,
  facturas: ['facturas'] as const,
  contratables: ['planes', 'contratables'] as const,
  planes: ['planes', 'publicos'] as const,
  avisos: ['avisos'] as const,
  datosFiscales: ['datos-fiscales'] as const,
  dispositivos: ['dispositivos'] as const,
  consentimiento: ['consentimiento'] as const,
  reporte: (nombre: string, desde: string, hasta: string) => ['reportes', nombre, desde, hasta] as const,
};
