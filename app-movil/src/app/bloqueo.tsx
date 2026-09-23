import { useQuery } from '@tanstack/react-query';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Linking, Pressable, StyleSheet, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Hueso } from '@/componentes/Esqueleto';
import { Icono, type NombreIcono } from '@/componentes/Icono';
import { Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi, mensajeDeEspera, URL_WEB } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { useSesion } from '@/lib/sesion';
import type { DocumentoPendiente } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

/**
 * Lo que frena a la persona antes de usar la app, cada caso con su pantalla y
 * su salida. Nunca un error genérico: quien no sabe por qué no puede entrar,
 * escribe a recepción.
 */
export default function Bloqueo() {
  const { bloqueo } = useSesion();
  if (!bloqueo) return null;

  switch (bloqueo.codigo) {
    case 'cuenta_suspendida':
      return <Suspendida />;
    case 'correo_sin_verificar':
      return <SinVerificar />;
    case 'consentimiento_pendiente':
      return <Consentimiento />;
    default:
      return <Suspendida />;
  }
}

function Encabezado({ icono, color, titulo, texto }: { icono: NombreIcono; color: string; titulo: string; texto: string }) {
  const { p } = useTema();
  return (
    <View style={{ gap: espacio.l, marginBottom: espacio.xl }}>
      <View style={[estilos.circulo, { backgroundColor: color }]}>
        <Icono nombre={icono} color={p.sobreAcento} tamano={34} />
      </View>
      <Texto variante="titulo">{titulo}</Texto>
      <Texto tono="suave">{texto}</Texto>
    </View>
  );
}

function Suspendida() {
  const { p } = useTema();
  const { bloqueo, salir } = useSesion();
  return (
    <Pantalla estilo={{ paddingTop: 96 }}>
      <Encabezado
        icono="candado"
        color={p.problema}
        titulo="Tu cuenta está en pausa"
        texto={bloqueo?.detalle || bloqueo?.mensaje || 'Tu cuenta está suspendida por ahora.'}
      />
      <Texto tono="suave" style={{ marginBottom: espacio.xl }}>
        Mientras tanto no puedes reservar ni usar tu credencial. Escríbenos y lo resolvemos contigo.
      </Texto>
      <View style={{ gap: espacio.m }}>
        <Boton titulo="Escribir a Nódico" icono="correo" alPulsar={() => void Linking.openURL('mailto:contacto@nodico.com.mx')} />
        <Boton titulo="Cerrar sesión" variante="fantasma" alPulsar={() => void salir()} />
      </View>
    </Pantalla>
  );
}

function SinVerificar() {
  const { p } = useTema();
  const { usuario, refrescarUsuario, salir } = useSesion();
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [ocupado, setOcupado] = useState<'reenviar' | 'comprobar' | null>(null);

  const reenviar = async () => {
    setOcupado('reenviar');
    try {
      const r = await api<{ message?: string } | null>('/auth/verificacion/reenviar', { metodo: 'POST', silenciosa: true });
      setMensaje((r && typeof r === 'object' && 'message' in r && r.message) || 'Te mandamos un correo nuevo.');
    } catch (e) {
      setMensaje(e instanceof ErrorApi ? mensajeDeEspera(e) : 'No pudimos reenviarlo.');
    } finally {
      setOcupado(null);
    }
  };

  const comprobar = async () => {
    setOcupado('comprobar');
    const yo = await refrescarUsuario();
    if (yo && !yo.correo_verificado) setMensaje('Todavía no aparece verificado. Abre el enlace del correo y vuelve a intentarlo.');
    setOcupado(null);
  };

  return (
    <Pantalla estilo={{ paddingTop: 96 }}>
      <Encabezado
        icono="correo"
        color={p.acentoTexto}
        titulo="Verifica tu correo"
        texto={`Te enviamos un enlace a ${usuario?.email ?? 'tu correo'}. Ábrelo y vuelve aquí.`}
      />
      {mensaje ? (
        <View style={[estilos.nota, { backgroundColor: p.superficie }]}>
          <Texto variante="pequeno" tono="suave">
            {mensaje}
          </Texto>
        </View>
      ) : null}
      <View style={{ gap: espacio.m }}>
        <Boton titulo="Ya lo verifiqué" alPulsar={comprobar} ocupado={ocupado === 'comprobar'} />
        <Boton titulo="Reenviar el correo" variante="secundario" alPulsar={reenviar} ocupado={ocupado === 'reenviar'} />
        <Boton titulo="Cerrar sesión" variante="fantasma" alPulsar={() => void salir()} />
      </View>
    </Pantalla>
  );
}

function Consentimiento() {
  const { p } = useTema();
  const { limpiarBloqueo, refrescarUsuario, salir } = useSesion();
  const consulta = useQuery({
    queryKey: claves.consentimiento,
    queryFn: () => api<{ pendientes: DocumentoPendiente[] }>('/yo/consentimiento', { silenciosa: true }),
    meta: { persistir: false },
  });
  const [aceptados, setAceptados] = useState<Set<string>>(new Set());
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const pendientes = consulta.data?.pendientes ?? [];
  const todos = pendientes.length > 0 && pendientes.every((d) => aceptados.has(d.documento));

  const alternar = (documento: string) => {
    const nuevo = new Set(aceptados);
    if (nuevo.has(documento)) nuevo.delete(documento);
    else nuevo.add(documento);
    setAceptados(nuevo);
  };

  const aceptar = async () => {
    setEnviando(true);
    setError(null);
    try {
      await api('/yo/consentimiento', { metodo: 'POST', cuerpo: { acepta: true, documentos: [...aceptados] }, silenciosa: true });
      limpiarBloqueo();
      await refrescarUsuario();
    } catch (e) {
      setError(e instanceof ErrorApi ? e.primero : 'No pudimos guardarlo.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Pantalla estilo={{ paddingTop: 96 }}>
      <Encabezado
        icono="documento"
        color={p.acentoTexto}
        titulo="Antes de seguir"
        texto="Actualizamos nuestros documentos legales. Léelos y acéptalos para seguir usando Nódico."
      />
      <View style={{ gap: espacio.m, marginBottom: espacio.xl }}>
        {consulta.isPending ? (
          <>
            <Hueso alto={56} redondo={radio.m} />
            <Hueso alto={56} redondo={radio.m} />
          </>
        ) : (
          pendientes.map((d) => {
            const marcado = aceptados.has(d.documento);
            return (
              <View
                key={d.documento}
                style={[estilos.documento, { backgroundColor: p.superficie, borderColor: marcado ? p.acentoTexto : p.borde }]}>
                <Pressable
                  accessibilityRole="checkbox"
                  accessibilityState={{ checked: marcado }}
                  onPress={() => alternar(d.documento)}
                  style={[
                    estilos.casilla,
                    {
                      borderColor: marcado ? p.acentoTexto : p.textoTenue,
                      backgroundColor: marcado ? p.acento : 'transparent',
                    },
                  ]}>
                  {marcado ? <Icono nombre="check" color={p.sobreAcento} tamano={16} grosor={2.6} /> : null}
                </Pressable>
                <View style={{ flex: 1 }}>
                  <Texto variante="cuerpoFuerte">{d.titulo}</Texto>
                  <Texto variante="pequeno" tono="tenue">
                    Versión {d.version}
                  </Texto>
                </View>
                {d.url ? (
                  <Pressable onPress={() => void WebBrowser.openBrowserAsync(d.url!.startsWith('http') ? d.url! : `${URL_WEB}${d.url}`)}>
                    <Texto variante="pequeno" tono="acento">
                      Leer
                    </Texto>
                  </Pressable>
                ) : null}
              </View>
            );
          })
        )}
      </View>
      {error ? (
        <Texto variante="pequeno" tono="problema" style={{ marginBottom: espacio.m }}>
          {error}
        </Texto>
      ) : null}
      <View style={{ gap: espacio.m }}>
        <Boton titulo="Aceptar y continuar" alPulsar={aceptar} ocupado={enviando} deshabilitado={!todos} />
        <Boton titulo="Cerrar sesión" variante="fantasma" alPulsar={() => void salir()} />
      </View>
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  circulo: { width: 72, height: 72, borderRadius: 36, alignItems: 'center', justifyContent: 'center' },
  nota: { padding: espacio.l, borderRadius: radio.m, marginBottom: espacio.l },
  documento: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacio.m,
    padding: espacio.l,
    borderRadius: radio.m,
    borderWidth: 1,
  },
  casilla: { width: 26, height: 26, borderRadius: 8, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
});
