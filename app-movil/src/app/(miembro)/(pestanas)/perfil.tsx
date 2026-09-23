import { useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import * as LocalAuthentication from 'expo-local-authentication';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, KeyboardAvoidingView, Modal, Platform, Pressable, StyleSheet, Switch, View } from 'react-native';

import { Chip, Fila, Grupo, TituloSeccion } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Icono } from '@/componentes/Icono';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { almacen } from '@/lib/almacen';
import { api, ErrorApi, VERSION_APP } from '@/lib/api';
import { primerNombre } from '@/lib/formato';
import { abrirWeb } from '@/lib/ganchos';
import { useSesion } from '@/lib/sesion';
import type { Usuario } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

export default function Perfil() {
  const { p } = useTema();
  const { usuario, salir, refrescarUsuario } = useSesion();
  const cliente = useQueryClient();
  const [subiendo, setSubiendo] = useState(false);
  const [biometria, setBiometria] = useState(false);
  const [hayBiometria, setHayBiometria] = useState(false);
  const [borrando, setBorrando] = useState(false);

  useEffect(() => {
    (async () => {
      setBiometria(await almacen.biometria());
      setHayBiometria((await LocalAuthentication.hasHardwareAsync()) && (await LocalAuthentication.isEnrolledAsync()));
    })();
  }, []);

  const cambiarBiometria = async (activa: boolean) => {
    if (activa) {
      const r = await LocalAuthentication.authenticateAsync({ promptMessage: 'Confirma para activar el bloqueo' });
      if (!r.success) return;
    }
    await almacen.guardarBiometria(activa);
    setBiometria(activa);
  };

  const subirFoto = async (origen: 'camara' | 'galeria') => {
    const permiso =
      origen === 'camara' ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permiso.granted) {
      Alert.alert(
        'Sin permiso',
        origen === 'camara' ? 'Permite el uso de la cámara en Ajustes.' : 'Permite el acceso a tus fotos en Ajustes.',
      );
      return;
    }
    const opciones: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.7 };
    const r = origen === 'camara' ? await ImagePicker.launchCameraAsync(opciones) : await ImagePicker.launchImageLibraryAsync(opciones);
    if (r.canceled || !r.assets[0]) return;

    const foto = r.assets[0];
    const datos = new FormData();
    // React Native acepta { uri, name, type } como archivo en FormData.
    datos.append('foto', { uri: foto.uri, name: foto.fileName ?? 'foto.jpg', type: foto.mimeType ?? 'image/jpeg' } as unknown as Blob);

    setSubiendo(true);
    try {
      await api('/yo/foto', { metodo: 'POST', cuerpo: datos, tiempoLimiteMs: 60000 });
      await refrescarUsuario();
      void cliente.invalidateQueries({ queryKey: ['inicio'] });
    } catch (e) {
      Alert.alert('No se pudo subir', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.');
    } finally {
      setSubiendo(false);
    }
  };

  const elegirFoto = () => {
    const botones = [
      { text: 'Tomar foto', onPress: () => void subirFoto('camara') },
      { text: 'Elegir de mis fotos', onPress: () => void subirFoto('galeria') },
      ...(usuario?.avatar_url
        ? [
            {
              text: 'Quitar foto',
              style: 'destructive' as const,
              onPress: async () => {
                try {
                  await api('/yo/foto', { metodo: 'DELETE' });
                  await refrescarUsuario();
                } catch (e) {
                  Alert.alert('No se pudo', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.');
                }
              },
            },
          ]
        : []),
      { text: 'Cancelar', style: 'cancel' as const },
    ];
    Alert.alert('Foto de perfil', undefined, botones);
  };

  return (
    <Pantalla titulo="Perfil" alRefrescar={refrescarUsuario}>
      <View style={estilos.cabecera}>
        <Pressable onPress={elegirFoto} accessibilityRole="button" accessibilityLabel="Cambiar foto de perfil">
          {usuario?.avatar_url ? (
            <Image
              source={{ uri: usuario.avatar_url }}
              style={[estilos.avatar, { opacity: subiendo ? 0.5 : 1 }]}
              contentFit="cover"
              transition={200}
            />
          ) : (
            <View
              style={[
                estilos.avatar,
                { backgroundColor: p.superficieAlta, alignItems: 'center', justifyContent: 'center', opacity: subiendo ? 0.5 : 1 },
              ]}>
              <Texto variante="titulo">
                {primerNombre(usuario?.nombre).charAt(0).toUpperCase() || 'N'}
              </Texto>
            </View>
          )}
          <View style={[estilos.camara, { backgroundColor: p.superficieAlta, borderColor: p.fondo }]}>
            <Icono nombre="camara" color={p.texto} tamano={16} />
          </View>
        </Pressable>
        <View style={{ flex: 1, gap: 4 }}>
          <Texto variante="subtitulo">{usuario?.nombre}</Texto>
          <Texto variante="pequeno" tono="suave">
            {usuario?.email}
          </Texto>
          {usuario && !usuario.face_id_ok ? <Chip texto="Rostro pendiente en recepción" color={p.atencion} /> : null}
        </View>
      </View>

      <TituloSeccion>Cuenta</TituloSeccion>
      <Grupo>
        <Fila
          icono="perfil"
          titulo="Mis datos"
          detalle="Teléfono, empresa, emergencia y avisos"
          alPulsar={() => router.push('/editar-perfil')}
        />
        <Fila icono="documento" titulo="Datos fiscales" detalle="Para pedir factura" alPulsar={() => router.push('/datos-fiscales')} />
        <Fila icono="dinero" titulo="Pagos y facturas" alPulsar={() => router.push('/pagos')} />
        <Fila icono="campana" titulo="Avisos" alPulsar={() => router.push('/avisos')} />
      </Grupo>

      <TituloSeccion>Seguridad</TituloSeccion>
      <Grupo>
        {hayBiometria ? (
          <>
            <Fila
              icono="huella"
              titulo={Platform.OS === 'ios' ? 'Bloqueo con Face ID' : 'Bloqueo con huella'}
              detalle="Pedirlo al volver a abrir la app"
              derecha={
                <Switch
                  value={biometria}
                  onValueChange={(v) => void cambiarBiometria(v)}
                  trackColor={{ true: p.acento, false: p.pista }}
                  thumbColor={Platform.OS === 'android' ? p.texto : undefined}
                />
              }
            />
          </>
        ) : null}
        <Fila
          icono="telefono"
          titulo="Mis dispositivos"
          detalle="Cierra la sesión de un teléfono perdido"
          alPulsar={() => router.push('/dispositivos')}
        />
        <Fila
          icono="candado"
          titulo="Contraseña y verificación en dos pasos"
          detalle="Se abre en la web"
          alPulsar={() => void abrirWeb('/seguridad')}
        />
      </Grupo>

      <View style={{ gap: espacio.m, marginTop: espacio.xxl }}>
        <Boton
          titulo="Cerrar sesión"
          icono="salir"
          variante="secundario"
          alPulsar={() =>
            Alert.alert('¿Cerrar sesión?', 'Tendrás que volver a entrar en este teléfono.', [
              { text: 'Cancelar', style: 'cancel' },
              { text: 'Cerrar sesión', style: 'destructive', onPress: () => void salir() },
            ])
          }
        />
        <Pressable onPress={() => setBorrando(true)} style={{ alignSelf: 'center', padding: espacio.m }}>
          <Texto variante="pequeno" tono="problema">
            Borrar mi cuenta
          </Texto>
        </Pressable>
        <Texto variante="pequeno" tono="tenue" centrado>
          Nódico {VERSION_APP}
        </Texto>
      </View>

      <BorrarCuenta visible={borrando} alCerrar={() => setBorrando(false)} usuario={usuario} />
    </Pantalla>
  );
}

/**
 * Borrar la cuenta desde la app (Apple lo exige a las apps que permiten crear
 * cuenta; entrar con Google crea cuentas). Pide la contraseña: es irreversible.
 */
function BorrarCuenta({ visible, alCerrar, usuario }: { visible: boolean; alCerrar: () => void; usuario: Usuario | null }) {
  const { p } = useTema();
  const { salir } = useSesion();
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [ocupado, setOcupado] = useState(false);

  const borrar = async () => {
    setOcupado(true);
    setError(null);
    try {
      await api('/yo', { metodo: 'DELETE', cuerpo: { password } });
      alCerrar();
      await salir('Borramos tu cuenta. Gracias por haber sido parte de Nódico.');
    } catch (e) {
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos borrarla.');
    } finally {
      setOcupado(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={alCerrar}>
      <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.superficie }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={{ padding: espacio.xl, paddingTop: espacio.xxl, gap: espacio.l }}>
          <Texto variante="titulo">Borrar mi cuenta</Texto>
          <Texto tono="suave">
            Se borran tus datos, tu historial y tu acceso, y no se puede deshacer. Si tienes una membresía vigente, la pierdes.
          </Texto>
          <Campo
            etiqueta={usuario ? `Contraseña de ${usuario.email}` : 'Contraseña'}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            error={error}
            ayuda="Si entraste con Google y no tienes contraseña, pide la baja en recepción."
          />
          <Boton titulo="Borrar definitivamente" variante="peligro" ocupado={ocupado} deshabilitado={!password} alPulsar={borrar} />
          <Boton titulo="No, conservarla" variante="fantasma" alPulsar={alCerrar} />
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const estilos = StyleSheet.create({
  cabecera: { flexDirection: 'row', alignItems: 'center', gap: espacio.l },
  avatar: { width: 72, height: 72, borderRadius: 36 },
  camara: {
    position: 'absolute',
    right: -2,
    bottom: -2,
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
  },
});
