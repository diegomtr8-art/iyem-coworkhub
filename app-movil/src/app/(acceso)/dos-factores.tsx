import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { useSesion } from '@/lib/sesion';
import type { RespuestaAcceso } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

/** El segundo factor: ningún camino de acceso lo rodea, tampoco en la app. */
export default function DosFactores() {
  const { p } = useTema();
  const { desafio } = useLocalSearchParams<{ desafio: string }>();
  const { entrar } = useSesion();
  const [codigo, setCodigo] = useState('');
  const [recuperacion, setRecuperacion] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    setEnviando(true);
    setError(null);
    try {
      const respuesta = await api<RespuestaAcceso>('/auth/dos-factores', {
        metodo: 'POST',
        anonima: true,
        cuerpo: { desafio, codigo: codigo.replace(/\s+/g, '') },
      });
      if ('token' in respuesta) await entrar(respuesta);
    } catch (e) {
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos comprobar el código.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.fondo }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Pantalla conMargenSuperior={false} titulo="Un paso más" subtitulo="Tu cuenta tiene verificación en dos pasos.">
        <View style={{ gap: espacio.l }}>
          <Campo
            etiqueta={recuperacion ? 'Código de recuperación' : 'Código de tu app de autenticación'}
            value={codigo}
            onChangeText={setCodigo}
            keyboardType={recuperacion ? 'default' : 'number-pad'}
            autoCapitalize="none"
            textContentType="oneTimeCode"
            autoComplete="one-time-code"
            autoFocus
            maxLength={recuperacion ? 32 : 6}
            placeholder={recuperacion ? 'xxxxx-xxxxx' : '123456'}
            error={error}
            style={recuperacion ? undefined : { fontSize: 28, letterSpacing: 8, textAlign: 'center' }}
          />
          <Boton titulo="Verificar" alPulsar={enviar} ocupado={enviando} deshabilitado={codigo.length < 6} />
          <Pressable
            onPress={() => {
              setRecuperacion(!recuperacion);
              setCodigo('');
              setError(null);
            }}
            style={{ alignSelf: 'center' }}>
            <Texto variante="pequeno" tono="suave">
              {recuperacion ? 'Usar el código de mi app' : 'No tengo mi teléfono: usar un código de recuperación'}
            </Texto>
          </Pressable>
        </View>
      </Pantalla>
    </KeyboardAvoidingView>
  );
}
