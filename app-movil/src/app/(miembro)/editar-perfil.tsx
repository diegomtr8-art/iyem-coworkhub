import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Switch, View } from 'react-native';

import { Fila, Grupo, TituloSeccion } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { useSesion } from '@/lib/sesion';
import { espacio, useTema } from '@/tema';

/** Mis datos: lo mismo que «Mi perfil» del portal, con sus mismas reglas en el servidor. */
export default function EditarPerfil() {
  const { p } = useTema();
  const { usuario, refrescarUsuario } = useSesion();
  const [f, setF] = useState({
    nombre: usuario?.nombre ?? '',
    telefono: usuario?.telefono ?? '',
    empresa: usuario?.empresa ?? '',
    ocupacion: usuario?.ocupacion ?? '',
    contacto_emergencia_nombre: usuario?.contacto_emergencia?.nombre ?? '',
    contacto_emergencia_telefono: usuario?.contacto_emergencia?.telefono ?? '',
    contacto_emergencia_parentesco: usuario?.contacto_emergencia?.parentesco ?? '',
    notif_reservas: usuario?.preferencias.reservas ?? true,
    notif_membresia: usuario?.preferencias.membresia ?? true,
    notif_comunidad: usuario?.preferencias.comunidad ?? true,
  });
  const [error, setError] = useState<ErrorApi | null>(null);
  const [guardando, setGuardando] = useState(false);

  const campo = (clave: keyof typeof f) => (valor: string | boolean) => setF((a) => ({ ...a, [clave]: valor }));

  const guardar = async () => {
    setGuardando(true);
    setError(null);
    try {
      await api('/yo', {
        metodo: 'PATCH',
        cuerpo: {
          nombre: f.nombre,
          telefono: f.telefono || null,
          empresa: f.empresa || null,
          ocupacion: f.ocupacion || null,
          contacto_emergencia: {
            nombre: f.contacto_emergencia_nombre || null,
            telefono: f.contacto_emergencia_telefono || null,
            parentesco: f.contacto_emergencia_parentesco || null,
          },
          preferencias: { reservas: f.notif_reservas, membresia: f.notif_membresia, comunidad: f.notif_comunidad },
        },
      });
      await refrescarUsuario();
      router.back();
    } catch (e) {
      setError(e instanceof ErrorApi ? e : null);
    } finally {
      setGuardando(false);
    }
  };

  const interruptor = (clave: 'notif_reservas' | 'notif_membresia' | 'notif_comunidad') => (
    <Switch value={f[clave]} onValueChange={campo(clave)} trackColor={{ true: p.acento, false: p.pista }} />
  );

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
      <Pantalla conMargenSuperior={false}>
        <View style={{ gap: espacio.l }}>
          <Campo etiqueta="Nombre" value={f.nombre} onChangeText={campo('nombre')} error={error?.campo('nombre')} autoComplete="name" />
          <Campo
            etiqueta="Teléfono"
            value={f.telefono}
            onChangeText={campo('telefono')}
            keyboardType="phone-pad"
            error={error?.campo('telefono')}
          />
          <Campo etiqueta="Empresa o proyecto" value={f.empresa} onChangeText={campo('empresa')} error={error?.campo('empresa')} />
          <Campo etiqueta="Ocupación" value={f.ocupacion} onChangeText={campo('ocupacion')} error={error?.campo('ocupacion')} />
        </View>

        <TituloSeccion>Contacto de emergencia</TituloSeccion>
        <View style={{ gap: espacio.l }}>
          <Campo
            etiqueta="Nombre"
            value={f.contacto_emergencia_nombre}
            onChangeText={campo('contacto_emergencia_nombre')}
            error={error?.campo('contacto_emergencia.nombre')}
          />
          <Campo
            etiqueta="Teléfono"
            value={f.contacto_emergencia_telefono}
            onChangeText={campo('contacto_emergencia_telefono')}
            keyboardType="phone-pad"
            error={error?.campo('contacto_emergencia.telefono')}
          />
          <Campo
            etiqueta="Parentesco"
            value={f.contacto_emergencia_parentesco}
            onChangeText={campo('contacto_emergencia_parentesco')}
            error={error?.campo('contacto_emergencia.parentesco')}
          />
        </View>

        <TituloSeccion>Te avisamos de</TituloSeccion>
        <Grupo>
          <Fila titulo="Mis reservas" detalle="Recordatorios y cambios" derecha={interruptor('notif_reservas')} />
          <Fila titulo="Mi membresía" detalle="Vencimientos, pagos y facturas" derecha={interruptor('notif_membresia')} />
          <Fila titulo="Comunidad" detalle="Eventos y novedades de Nódico" derecha={interruptor('notif_comunidad')} />
        </Grupo>

        {error && !Object.keys(error.errores).length ? (
          <Texto variante="pequeno" tono="problema" style={{ marginTop: espacio.l }}>
            {error.message}
          </Texto>
        ) : null}
        <Boton titulo="Guardar" alPulsar={guardar} ocupado={guardando} estilo={{ marginTop: espacio.xl }} />
      </Pantalla>
    </KeyboardAvoidingView>
  );
}
