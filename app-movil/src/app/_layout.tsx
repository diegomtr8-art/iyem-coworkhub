import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { useFonts } from 'expo-font';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { BloqueoBiometrico } from '@/componentes/BloqueoBiometrico';
import { VersionObsoleta } from '@/componentes/VersionObsoleta';
import { clienteConsultas, opcionesDePersistencia } from '@/lib/consultas';
import { ProveedorSesion, useSesion } from '@/lib/sesion';
import { archivosDeFuentes, fuentes, ProveedorTema, useTema } from '@/tema';

SplashScreen.preventAutoHideAsync().catch(() => {});
SplashScreen.setOptions({ duration: 300, fade: true });

export default function Raiz() {
  const [fuentesListas, errorFuentes] = useFonts(archivosDeFuentes);

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <ProveedorTema>
          <PersistQueryClientProvider client={clienteConsultas} persistOptions={opcionesDePersistencia}>
            <ProveedorSesion>{fuentesListas || errorFuentes ? <Navegacion /> : null}</ProveedorSesion>
          </PersistQueryClientProvider>
        </ProveedorTema>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

function Navegacion() {
  const { p, esOscuro } = useTema();
  const { fase, bloqueo, cara, versionObsoleta, bloqueadaPorBiometria } = useSesion();

  useEffect(() => {
    if (fase !== 'arrancando') SplashScreen.hideAsync().catch(() => {});
  }, [fase]);

  if (fase === 'arrancando') return null;

  const base = esOscuro ? DarkTheme : DefaultTheme;
  const temaNavegacion = {
    ...base,
    colors: { ...base.colors, background: p.fondo, card: p.fondo, text: p.texto, border: p.borde, primary: p.acentoTexto },
  };

  const dentro = fase === 'dentro';

  return (
    <ThemeProvider value={temaNavegacion}>
      <StatusBar style={esOscuro ? 'light' : 'dark'} />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: p.fondo },
          headerTitleStyle: { fontFamily: fuentes.titulo, color: p.texto },
        }}>
        <Stack.Protected guard={!dentro}>
          <Stack.Screen name="(acceso)" />
        </Stack.Protected>
        <Stack.Protected guard={dentro && !!bloqueo}>
          <Stack.Screen name="bloqueo" />
        </Stack.Protected>
        <Stack.Protected guard={dentro && !bloqueo && cara === 'miembro'}>
          <Stack.Screen name="(miembro)" />
        </Stack.Protected>
        <Stack.Protected guard={dentro && !bloqueo && cara === 'reportes'}>
          <Stack.Screen name="(reportes)" />
        </Stack.Protected>
      </Stack>
      {dentro && bloqueadaPorBiometria ? <BloqueoBiometrico /> : null}
      {versionObsoleta ? <VersionObsoleta /> : null}
    </ThemeProvider>
  );
}
