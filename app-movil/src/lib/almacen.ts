import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Todo lo sensible vive en el almacén seguro del sistema (Keychain en iOS,
 * Keystore en Android). **Nunca** en AsyncStorage: el token es una llave de la
 * cuenta y la credencial identifica a la persona en recepción.
 */

const CLAVES = {
  token: 'nodico.token',
  dispositivo: 'nodico.dispositivo_id',
  credencial: 'nodico.credencial',
  secretoEnlace: 'nodico.enlace_secreto',
  biometria: 'nodico.biometria',
  pushRegistrado: 'nodico.push_registrado',
} as const;

// La vista previa web (solo desarrollo) no tiene almacén seguro: usa el del
// navegador. En iOS y Android esto no se usa nunca.
const enWeb = Platform.OS === 'web' && typeof localStorage !== 'undefined';

async function leer(clave: string): Promise<string | null> {
  if (enWeb) return localStorage.getItem(clave);
  try {
    return await SecureStore.getItemAsync(clave);
  } catch {
    return null;
  }
}

async function escribir(clave: string, valor: string | null): Promise<void> {
  if (enWeb) {
    if (valor === null) localStorage.removeItem(clave);
    else localStorage.setItem(clave, valor);
    return;
  }
  try {
    if (valor === null) {
      await SecureStore.deleteItemAsync(clave);
    } else {
      await SecureStore.setItemAsync(clave, valor);
    }
  } catch {
    // Si el almacén falla, la app sigue: simplemente no recordará.
  }
}

// El token se mantiene también en memoria para no pagar una lectura del
// Keychain en cada petición.
let tokenEnMemoria: string | null | undefined;

export const almacen = {
  async token(): Promise<string | null> {
    if (tokenEnMemoria === undefined) {
      tokenEnMemoria = await leer(CLAVES.token);
    }
    return tokenEnMemoria;
  },

  async guardarToken(token: string | null): Promise<void> {
    tokenEnMemoria = token;
    await escribir(CLAVES.token, token);
  },

  /** Identificador estable del teléfono: se genera una vez y no cambia. */
  async dispositivoId(): Promise<string> {
    let id = await leer(CLAVES.dispositivo);
    if (!id) {
      id = Crypto.randomUUID();
      await escribir(CLAVES.dispositivo, id);
    }
    return id;
  },

  async credencial<T>(): Promise<T | null> {
    const bruto = await leer(CLAVES.credencial);
    if (!bruto) return null;
    try {
      return JSON.parse(bruto) as T;
    } catch {
      return null;
    }
  },

  async guardarCredencial(valor: unknown | null): Promise<void> {
    await escribir(CLAVES.credencial, valor === null ? null : JSON.stringify(valor));
  },

  async secretoEnlace(): Promise<string | null> {
    return leer(CLAVES.secretoEnlace);
  },

  async guardarSecretoEnlace(secreto: string | null): Promise<void> {
    await escribir(CLAVES.secretoEnlace, secreto);
  },

  async biometria(): Promise<boolean> {
    return (await leer(CLAVES.biometria)) === '1';
  },

  async guardarBiometria(activa: boolean): Promise<void> {
    await escribir(CLAVES.biometria, activa ? '1' : null);
  },

  async pushRegistrado(): Promise<boolean> {
    return (await leer(CLAVES.pushRegistrado)) === '1';
  },

  async guardarPushRegistrado(si: boolean): Promise<void> {
    await escribir(CLAVES.pushRegistrado, si ? '1' : null);
  },

  /** Al cerrar sesión se borra todo lo de la cuenta; el id del teléfono se queda. */
  async limpiarCuenta(): Promise<void> {
    tokenEnMemoria = null;
    await Promise.all([
      escribir(CLAVES.token, null),
      escribir(CLAVES.credencial, null),
      escribir(CLAVES.secretoEnlace, null),
      escribir(CLAVES.pushRegistrado, null),
    ]);
  },
};
