import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';

import { aYmd } from './formato';

export type Preajuste = 'mes' | 'mesPasado' | '30' | 'anio';

export const PREAJUSTES: { clave: Preajuste; etiqueta: string }[] = [
  { clave: 'mes', etiqueta: 'Este mes' },
  { clave: 'mesPasado', etiqueta: 'Mes pasado' },
  { clave: '30', etiqueta: '30 días' },
  { clave: 'anio', etiqueta: 'Este año' },
];

export function rangoDe(clave: Preajuste, hoy: Date = new Date()): { desde: string; hasta: string } {
  const a = hoy.getFullYear();
  const m = hoy.getMonth();
  switch (clave) {
    case 'mesPasado':
      return { desde: aYmd(new Date(a, m - 1, 1)), hasta: aYmd(new Date(a, m, 0)) };
    case '30': {
      const desde = new Date(hoy);
      desde.setDate(desde.getDate() - 29);
      return { desde: aYmd(desde), hasta: aYmd(hoy) };
    }
    case 'anio':
      return { desde: aYmd(new Date(a, 0, 1)), hasta: aYmd(hoy) };
    default:
      return { desde: aYmd(new Date(a, m, 1)), hasta: aYmd(hoy) };
  }
}

type Contexto = { clave: Preajuste; desde: string; hasta: string; cambiar: (c: Preajuste) => void };

const ContextoRango = createContext<Contexto | null>(null);

/** El rango se comparte entre las pestañas de reportes: cambiarlo en una lo cambia en todas. */
export function ProveedorRango({ children }: { children: ReactNode }) {
  const [clave, setClave] = useState<Preajuste>('mes');
  const valor = useMemo(() => ({ clave, ...rangoDe(clave), cambiar: setClave }), [clave]);
  return <ContextoRango.Provider value={valor}>{children}</ContextoRango.Provider>;
}

export function useRango(): Contexto {
  const c = useContext(ContextoRango);
  if (!c) throw new Error('useRango fuera de ProveedorRango');
  return c;
}
