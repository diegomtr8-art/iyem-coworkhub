import { router } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { useTema } from '@/tema';

/**
 * A donde regresa la app desde el formulario de BBVA (`nodico://regreso-banco`).
 *
 * Normalmente el navegador del pago captura esta dirección y la pantalla de
 * pagar sigue sola, preguntando por el cargo. Pero en Android el enlace también
 * puede abrir esta ruta: aquí no hay nada que hacer más que volver a donde se
 * estaba. **No confirma nada**: eso lo hace el servidor con la API del banco.
 */
export default function RegresoBanco() {
  const { p } = useTema();

  useEffect(() => {
    if (router.canGoBack()) router.back();
    else router.replace('/');
  }, []);

  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: p.fondo }}>
      <ActivityIndicator color={p.acento} />
    </View>
  );
}
