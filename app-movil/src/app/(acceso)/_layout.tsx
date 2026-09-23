import { Stack } from 'expo-router';

import { fuentes, useTema } from '@/tema';

export default function LayoutAcceso() {
  const { p } = useTema();
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: p.fondo },
        headerStyle: { backgroundColor: p.fondo },
        headerTintColor: p.texto,
        headerTitleStyle: { fontFamily: fuentes.titulo },
        headerShadowVisible: false,
        headerBackButtonDisplayMode: 'minimal',
      }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="dos-factores" options={{ headerShown: true, title: '' }} />
      <Stack.Screen name="enlace-magico" options={{ headerShown: true, title: '' }} />
      <Stack.Screen name="planes" options={{ headerShown: true, title: 'Planes' }} />
      <Stack.Screen name="auth/enlace" options={{ headerShown: false }} />
    </Stack>
  );
}
