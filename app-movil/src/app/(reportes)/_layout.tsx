import * as Haptics from 'expo-haptics';
import { Tabs } from 'expo-router';
import { StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Icono, type NombreIcono } from '@/componentes/Icono';
import { ProveedorRango } from '@/lib/rango';
import { fuentes, useTema } from '@/tema';

const PESTANAS: { nombre: string; titulo: string; icono: NombreIcono }[] = [
  { nombre: 'index', titulo: 'Resumen', icono: 'grafica' },
  { nombre: 'ocupacion', titulo: 'Ocupación', icono: 'reservar' },
  { nombre: 'ingresos', titulo: 'Ingresos', icono: 'dinero' },
  { nombre: 'miembros', titulo: 'Miembros', icono: 'personas' },
];

/**
 * La cara de administración: solo reportes, solo lectura. Toda la operación se
 * queda en el panel web.
 */
export default function LayoutReportes() {
  const { p } = useTema();
  const margen = useSafeAreaInsets();
  return (
    <ProveedorRango>
      <Tabs
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: p.acentoTexto,
          tabBarInactiveTintColor: p.textoTenue,
          tabBarStyle: {
            backgroundColor: p.fondo,
            borderTopColor: p.borde,
            borderTopWidth: StyleSheet.hairlineWidth,
            // Misma barra que la del miembro: altura explícita para que la
            // etiqueta no quede cortada.
            height: 64 + margen.bottom,
            paddingBottom: margen.bottom,
          },
          tabBarLabelPosition: 'below-icon',
          tabBarLabelStyle: { fontFamily: fuentes.semi, fontSize: 11, lineHeight: 15 },
          tabBarItemStyle: { paddingTop: 8, paddingBottom: 6, height: 64 },
          tabBarIconStyle: { height: 26 },
          tabBarAllowFontScaling: false,
          sceneStyle: { backgroundColor: p.fondo },
        }}
        screenListeners={{ tabPress: () => void Haptics.selectionAsync().catch(() => {}) }}>
        {PESTANAS.map((t) => (
          <Tabs.Screen
            key={t.nombre}
            name={t.nombre}
            options={{
              title: t.titulo,
              tabBarIcon: ({ color, focused }) => (
                <Icono nombre={t.icono} color={color as string} tamano={24} grosor={focused ? 2.2 : 1.7} />
              ),
            }}
          />
        ))}
      </Tabs>
    </ProveedorRango>
  );
}
