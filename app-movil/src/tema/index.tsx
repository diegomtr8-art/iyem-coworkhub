import { createContext, useContext, type ReactNode } from 'react';

import { oscuro, type Paleta } from './tokens';

export * from './tokens';

type Tema = { p: Paleta; esOscuro: boolean };

const ContextoTema = createContext<Tema>({ p: oscuro, esOscuro: true });

/**
 * La app de Nódico es negra siempre, con la paleta de la marca (decisión de
 * Diego, 23/09/2026): no sigue el modo claro del sistema. `app.json` fija
 * además `userInterfaceStyle: "dark"` para la barra de estado y los diálogos.
 */
export function ProveedorTema({ children }: { children: ReactNode }) {
  return <ContextoTema.Provider value={{ p: oscuro, esOscuro: true }}>{children}</ContextoTema.Provider>;
}

export function useTema(): Tema {
  return useContext(ContextoTema);
}
