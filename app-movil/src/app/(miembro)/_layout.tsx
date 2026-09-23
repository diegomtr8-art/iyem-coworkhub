import { Stack } from 'expo-router';

import { fuentes, useTema } from '@/tema';

/**
 * Pila del miembro: las pestañas abajo y, encima, las pantallas secundarias
 * (con gesto de regresar) y las hojas deslizantes nativas para los detalles.
 */
export default function LayoutMiembro() {
  const { p } = useTema();

  const hoja = {
    presentation: 'formSheet' as const,
    sheetGrabberVisible: true,
    sheetCornerRadius: 28,
    headerShown: false,
    contentStyle: { backgroundColor: p.superficie },
  };

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: p.fondo },
        headerTintColor: p.texto,
        headerTitleStyle: { fontFamily: fuentes.titulo, color: p.texto },
        headerShadowVisible: false,
        headerBackButtonDisplayMode: 'minimal',
        contentStyle: { backgroundColor: p.fondo },
      }}>
      <Stack.Screen name="(pestanas)" options={{ headerShown: false }} />
      <Stack.Screen name="reservas" options={{ title: 'Mis reservas' }} />
      <Stack.Screen name="asesoria" options={{ title: 'Asesoría IYEM' }} />
      <Stack.Screen name="pagos" options={{ title: 'Pagos y facturas' }} />
      <Stack.Screen name="pago/[id]" options={{ title: 'Referencia de pago' }} />
      <Stack.Screen name="contratar" options={{ title: 'Elige tu plan' }} />
      <Stack.Screen name="datos-fiscales" options={{ title: 'Datos fiscales' }} />
      <Stack.Screen name="avisos" options={{ title: 'Avisos' }} />
      <Stack.Screen name="dispositivos" options={{ title: 'Mis dispositivos' }} />
      <Stack.Screen name="editar-perfil" options={{ title: 'Mis datos' }} />
      <Stack.Screen name="reserva/[id]" options={{ ...hoja, sheetAllowedDetents: [0.6, 0.95] }} />
      <Stack.Screen name="confirmar-reserva" options={{ ...hoja, sheetAllowedDetents: [0.75, 0.95] }} />
      <Stack.Screen name="solicitar-asesoria" options={{ ...hoja, sheetAllowedDetents: [0.9] }} />
      <Stack.Screen name="pagar" options={{ ...hoja, sheetAllowedDetents: [0.7, 0.95] }} />
    </Stack>
  );
}
