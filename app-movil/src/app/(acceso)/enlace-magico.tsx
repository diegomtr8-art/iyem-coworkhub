import * as Crypto from 'expo-crypto';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Icono } from '@/componentes/Icono';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { almacen } from '@/lib/almacen';
import { api, ErrorApi, mensajeDeEspera } from '@/lib/api';
import { espacio, useTema } from '@/tema';

const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

/** Secreto de 48 caracteres; solo su SHA-256 sale del teléfono. */
function generarSecreto(): string {
  const bytes = Crypto.getRandomBytes(48);
  return Array.from(bytes, (b) => ALFABETO[b % ALFABETO.length]).join('');
}

/**
 * Pedir el enlace mágico. El enlace queda atado a **este** teléfono: aquí se
 * guarda un secreto y al servidor solo va su huella. Un enlace reenviado o
 * abierto en otro teléfono no sirve, porque ese teléfono no tiene el secreto.
 */
export default function EnlaceMagico() {
  const { p } = useTema();
  const params = useLocalSearchParams<{ email?: string }>();
  const [email, setEmail] = useState(params.email ?? '');
  const [enviado, setEnviado] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    setEnviando(true);
    setError(null);
    try {
      const secreto = generarSecreto();
      const verificador = await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, secreto, {
        encoding: Crypto.CryptoEncoding.HEX,
      });
      await almacen.guardarSecretoEnlace(secreto);
      await api('/auth/enlace-magico', { metodo: 'POST', anonima: true, cuerpo: { email: email.trim(), verificador } });
      setEnviado(true);
    } catch (e) {
      setError(e instanceof ErrorApi ? (e.estado === 429 ? mensajeDeEspera(e) : e.primero) : 'No pudimos enviarlo.');
    } finally {
      setEnviando(false);
    }
  };

  if (enviado) {
    return (
      <Pantalla conMargenSuperior={false} estilo={{ alignItems: 'center', gap: espacio.l, paddingTop: espacio.xxxl }}>
        <View style={[estilos.circulo, { backgroundColor: p.acento }]}>
          <Icono nombre="correo" color={p.sobreAcento} tamano={36} />
        </View>
        <Texto variante="titulo" centrado>
          Revisa tu correo
        </Texto>
        <Texto tono="suave" centrado>
          Si hay una cuenta con {email.trim()}, te llegó un enlace para entrar. Ábrelo{' '}
          <Texto variante="cuerpoFuerte">desde este teléfono</Texto>: dura 15 minutos y solo sirve una vez.
        </Texto>
        <Boton
          titulo="Enviar otro"
          variante="secundario"
          alPulsar={() => setEnviado(false)}
          estilo={{ alignSelf: 'stretch', marginTop: espacio.xl }}
        />
      </Pantalla>
    );
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.fondo }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Pantalla conMargenSuperior={false} titulo="Entrar sin contraseña" subtitulo="Te mandamos un enlace de un solo uso a tu correo.">
        <View style={{ gap: espacio.l }}>
          <Campo
            etiqueta="Correo"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            keyboardType="email-address"
            autoComplete="email"
            autoFocus
            error={error}
            onSubmitEditing={enviar}
          />
          <Boton titulo="Enviar enlace" alPulsar={enviar} ocupado={enviando} deshabilitado={!email.includes('@')} />
        </View>
      </Pantalla>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  circulo: { width: 84, height: 84, borderRadius: 42, alignItems: 'center', justifyContent: 'center' },
});
