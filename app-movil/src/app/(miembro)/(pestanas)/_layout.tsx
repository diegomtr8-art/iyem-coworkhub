import * as Haptics from 'expo-haptics';
import { Tabs } from 'expo-router';
import { StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Icono, type NombreIcono } from '@/componentes/Icono';
import { fuentes, useTema } from '@/tema';

const PESTANAS: { nombre: string; titulo: string; icono: NombreIcono }[] = [
  { nombre: 'index', titulo: 'Inicio', icono: 'inicio' },
  { nombre: 'reservar', titulo: 'Reservar', icono: 'reservar' },
  { nombre: 'credencial', titulo: 'Credencial', icono: 'credencial' },
  { nombre: 'membresia', titulo: 'Membresía', icono: 'membresia' },
  { nombre: 'perfil', titulo: 'Perfil', icono: 'perfil' },
];

/** Cinco destinos, ni uno más: lo que alguien abre tres veces por semana. */
export default function Pestanas() {
  const { p } = useTema();
  const margen = useSafeAreaInsets();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: p.acentoTexto,
        tabBarInactiveTintColor: p.textoTenue,
        tabBarStyle: {
          backgroundColor: p.fondo,
          borderTopColor: p.borde,
          borderTopWidth: StyleSheet.hairlineWidth,
          // Altura explícita con sitio para icono + etiqueta + área segura. La
          // anterior (por defecto + relleno arriba) dejaba la etiqueta cortada.
          height: 64 + margen.bottom,
          paddingBottom: margen.bottom,
        },
        // Etiqueta siempre visible debajo del icono: un icono solo no se entiende.
        tabBarLabelPosition: 'below-icon',
        tabBarLabelStyle: { fontFamily: fuentes.semi, fontSize: 11, lineHeight: 15 },
        tabBarItemStyle: { paddingTop: 8, paddingBottom: 6, height: 64 },
        tabBarIconStyle: { height: 26 },
        tabBarAllowFontScaling: false,
        sceneStyle: { backgroundColor: p.fondo },
      }}
      screenListeners={{
        tabPress: () => {
          Haptics.selectionAsync().catch(() => {});
        },
      }}>
      {PESTANAS.map((t) => (
        <Tabs.Screen
          key={t.nombre}
          name={t.nombre}
          options={{
            title: t.titulo,
            tabBarIcon: ({ color, focused }) => <Icono nombre={t.icono} color={color as string} tamano={24} grosor={focused ? 2.2 : 1.7} />,
          }}
        />
      ))}
    </Tabs>
  );
}
