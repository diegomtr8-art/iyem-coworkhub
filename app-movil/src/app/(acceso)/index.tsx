import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi, mensajeDeEspera } from '@/lib/api';
import { abrirWeb, enExpoGo, useEstadoServidor } from '@/lib/ganchos';
import { useSesion } from '@/lib/sesion';
import type { RespuestaAcceso } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

export default function Acceso() {
  const { p, esOscuro } = useTema();
  const { entrar, datosDeDispositivo, avisoDeSalida, olvidarAviso } = useSesion();
  const estado = useEstadoServidor();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<ErrorApi | null>(null);
  const [enviando, setEnviando] = useState(false);
  const refPassword = useRef<TextInput>(null);

  const enviar = async () => {
    setEnviando(true);
    setError(null);
    olvidarAviso();
    try {
      const respuesta = await api<RespuestaAcceso>('/auth/token', {
        metodo: 'POST',
        anonima: true,
        cuerpo: { email: email.trim(), password, ...(await datosDeDispositivo()) },
      });
      if ('requiere_dos_factores' in respuesta) {
        router.push({ pathname: '/dos-factores', params: { desafio: respuesta.desafio } });
      } else {
        await entrar(respuesta);
      }
    } catch (e) {
      setError(e instanceof ErrorApi ? e : new ErrorApi(0, 'No pudimos entrar. Inténtalo de nuevo.'));
    } finally {
      setEnviando(false);
    }
  };

  const mensajeGeneral =
    error && !error.campo('email') && !error.campo('password') ? (error.estado === 429 ? mensajeDeEspera(error) : error.message) : null;

  const google = estado.data?.acceso.google && !enExpoGo;
  const enlace = estado.data?.acceso.enlace_magico;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.fondo }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Pantalla estilo={{ gap: espacio.xl, flexGrow: 1 }}>
        <View style={estilos.marca}>
          <Image
            source={esOscuro ? require('../../../assets/images/logo-blanco.png') : require('../../../assets/images/logo-oscuro.png')}
            style={{ width: 150, height: 52 }}
            contentFit="contain"
          />
        </View>

        <View style={{ gap: espacio.s }}>
          <Texto variante="titulo" style={{ fontSize: 36, lineHeight: 40 }}>
            Tu espacio,{'\n'}
            <Texto variante="titulo" tono="acento" style={{ fontSize: 36, lineHeight: 40 }}>
              en tu bolsillo.
            </Texto>
          </Texto>
          <Texto tono="suave">Reserva salas, revisa tus horas y entra con tu credencial.</Texto>
        </View>

        {avisoDeSalida ? (
          <View style={[estilos.aviso, { backgroundColor: p.superficie, borderColor: p.borde }]}>
            <Texto variante="pequeno" tono="suave">
              {avisoDeSalida}
            </Texto>
          </View>
        ) : null}

        <View style={{ gap: espacio.l }}>
          <Campo
            etiqueta="Correo"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            textContentType="emailAddress"
            returnKeyType="next"
            onSubmitEditing={() => refPassword.current?.focus()}
            placeholder="tu@correo.com"
            error={error?.campo('email')}
          />
          <Campo
            ref={refPassword}
            etiqueta="Contraseña"
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            autoComplete="password"
            textContentType="password"
            returnKeyType="go"
            onSubmitEditing={enviar}
            error={error?.campo('password')}
          />
          {mensajeGeneral ? (
            <Texto variante="pequeno" tono="problema">
              {mensajeGeneral}
            </Texto>
          ) : null}
          <Boton titulo="Entrar" alPulsar={enviar} ocupado={enviando} deshabilitado={!email || !password} />
          <Pressable onPress={() => void abrirWeb('/forgot-password')} accessibilityRole="link" style={{ alignSelf: 'center' }}>
            <Texto variante="pequeno" tono="suave">
              Olvidé mi contraseña
            </Texto>
          </Pressable>
        </View>

        {enlace || google ? (
          <View style={{ gap: espacio.m }}>
            <View style={estilos.divisor}>
              <View style={[estilos.linea, { backgroundColor: p.borde }]} />
              <Texto variante="pequeno" tono="tenue">
                o
              </Texto>
              <View style={[estilos.linea, { backgroundColor: p.borde }]} />
            </View>
            {enlace ? (
              <Boton
                titulo="Recibir un enlace por correo"
                icono="correo"
                variante="secundario"
                alPulsar={() => router.push({ pathname: '/enlace-magico', params: { email } })}
              />
            ) : null}
          </View>
        ) : null}

        <View style={{ flex: 1 }} />

        <View style={{ gap: espacio.m, alignItems: 'center' }}>
          <Pressable onPress={() => router.push('/planes')} accessibilityRole="link">
            <Texto variante="cuerpoFuerte" tono="acento">
              Conoce los planes
            </Texto>
          </Pressable>
          <Pressable onPress={() => void abrirWeb('/register')} accessibilityRole="link">
            <Texto variante="pequeno" tono="suave">
              ¿Aún no tienes cuenta?{' '}
              <Texto variante="pequeno" style={{ textDecorationLine: 'underline' }}>
                Créala aquí
              </Texto>
            </Texto>
          </Pressable>
        </View>
      </Pantalla>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  marca: { paddingTop: espacio.l },
  aviso: { padding: espacio.l, borderRadius: radio.m, borderWidth: StyleSheet.hairlineWidth },
  divisor: { flexDirection: 'row', alignItems: 'center', gap: espacio.m },
  linea: { flex: 1, height: StyleSheet.hairlineWidth },
});
