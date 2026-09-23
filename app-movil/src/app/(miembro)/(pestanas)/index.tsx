import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, { FadeIn, ReduceMotion } from 'react-native-reanimated';

import { FilaBolsa } from '@/componentes/Anillo';
import { BannerEstado } from '@/componentes/BannerEstado';
import { Fila, Grupo, Tarjeta, TituloSeccion } from '@/componentes/Base';
import { EsqueletoAnillos, EsqueletoTarjeta } from '@/componentes/Esqueleto';
import { Icono } from '@/componentes/Icono';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { cuentaRegresiva, diaRelativo, dinero, horas, primerNombre, saludo } from '@/lib/formato';
import { useSesion } from '@/lib/sesion';
import type { Bolsa, Inicio as DatosInicio } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

/**
 * Inicio. La regla de oro: la persona responde sin pensar «¿cuánto me queda y
 * hasta cuándo?». Por eso lo primero son sus horas, después la próxima reserva,
 * y lo demás es accesorio y va en listas discretas.
 */
export default function Inicio() {
  const { usuario } = useSesion();
  const consulta = useQuery({ queryKey: claves.inicio, queryFn: () => api<DatosInicio>('/inicio') });
  const d = consulta.data;

  return (
    <Pantalla alRefrescar={() => consulta.refetch()}>
      <Cabecera nombre={usuario?.nombre} avatar={usuario?.avatar_url ?? null} />

      <AvisoSinConexion consulta={consulta} />

      {consulta.isPending ? (
        <View style={{ gap: espacio.xl }}>
          <Tarjeta>
            <EsqueletoAnillos />
          </Tarjeta>
          <EsqueletoTarjeta lineas={2} />
        </View>
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar tu inicio." alReintentar={() => void consulta.refetch()} />
      ) : (
        <Animated.View entering={FadeIn.duration(250).reduceMotion(ReduceMotion.System)}>
          <BannerEstado
            estado={d.estado_membresia}
            alAccion={d.membresia?.rol_en_membresia === 'acompanante' ? undefined : () => router.push('/contratar')}
          />

          {d.membresia ? (
            <Horas bolsas={d.bolsas} plan={d.membresia.plan.nombre} diasRestantes={d.membresia.dias_restantes} />
          ) : (
            <SinMembresia />
          )}

          <TituloSeccion
            accion={
              <Texto
                variante="cuerpoFuerte"
                tono="acento"
                onPress={() => router.push('/reservas')}
                accessibilityRole="link"
                suppressHighlighting>
                Ver todas
              </Texto>
            }>
            Próxima reserva
          </TituloSeccion>
          {d.proxima_reserva ? (
            <ProximaReserva reserva={d.proxima_reserva} />
          ) : (
            <Tarjeta alPulsar={d.membresia ? () => router.push('/reservar') : undefined}>
              <Texto variante="cuerpoFuerte">No tienes reservas próximas</Texto>
              <Texto tono="tenue">
                {d.membresia ? 'Aparta una sala o un estudio en menos de un minuto.' : 'Con una membresía podrás apartar salas y estudios.'}
              </Texto>
            </Tarjeta>
          )}

          <TituloSeccion>Atajos</TituloSeccion>
          <Grupo>
            <Fila icono="lista" titulo="Mis reservas" alPulsar={() => router.push('/reservas')} />
            <Fila
              icono="personas"
              titulo="Asesoría IYEM"
              detalle={d.asesorias_pendientes ? `${d.asesorias_pendientes} en curso` : null}
              alPulsar={() => router.push('/asesoria')}
            />
            <Fila
              icono="campana"
              titulo="Avisos"
              detalle={d.comunicados_sin_leer ? `${d.comunicados_sin_leer} sin leer` : null}
              derecha={d.comunicados_sin_leer ? <Insignia n={d.comunicados_sin_leer} /> : undefined}
              alPulsar={() => router.push('/avisos')}
            />
            {d.face_id_pendiente ? (
              <Fila icono="foco" titulo="Registra tu rostro" detalle="Pasa a recepción; toma un minuto y ya entras con la cara." />
            ) : null}
          </Grupo>

          {d.avisos.length > 0 ? (
            <>
              <TituloSeccion>Del coworking</TituloSeccion>
              <Grupo>
                {d.avisos.slice(0, 2).map((a) => (
                  <Fila key={a.id} titulo={a.titulo} detalle={a.contenido} alPulsar={() => router.push('/avisos')} />
                ))}
              </Grupo>
            </>
          ) : null}

          <Sugerencia bolsas={d.bolsas} />
        </Animated.View>
      )}
    </Pantalla>
  );
}

function Cabecera({ nombre, avatar }: { nombre?: string | null; avatar: string | null }) {
  const { p } = useTema();
  const primero = primerNombre(nombre);

  return (
    <View style={estilos.cabecera}>
      <View style={{ flex: 1 }}>
        <Texto tono="tenue">{saludo()}</Texto>
        <Texto variante="titulo" numberOfLines={1} accessibilityRole="header">
          {primero || 'Hola'}
        </Texto>
      </View>
      <Pressable
        onPress={() => router.push('/perfil')}
        accessibilityRole="button"
        accessibilityLabel="Ir a mi perfil"
        hitSlop={8}>
        {avatar ? (
          <Image source={{ uri: avatar }} style={estilos.avatar} contentFit="cover" />
        ) : (
          <View style={[estilos.avatar, { backgroundColor: p.superficieAlta }]}>
            <Texto variante="seccion">{primero.charAt(0).toUpperCase() || 'N'}</Texto>
          </View>
        )}
      </Pressable>
    </View>
  );
}

function Horas({ bolsas, plan, diasRestantes }: { bolsas: Bolsa[]; plan: string; diasRestantes: number }) {
  const incluidas = bolsas.filter((b) => b.incluida && !(b.bolsa === 'dias' && b.ilimitada));
  const coworkingIlimitado = bolsas.some((b) => b.bolsa === 'dias' && b.incluida && b.ilimitada);
  const reinicio = incluidas.find((b) => b.reinicia_texto)?.reinicia_texto;

  return (
    <Tarjeta estilo={{ gap: 0, paddingVertical: espacio.l }}>
      <View style={estilos.planFila}>
        <Texto variante="seccion">{plan}</Texto>
        <Texto variante="pequeno" tono="tenue">
          {diasRestantes === 1 ? 'Queda 1 día' : `Quedan ${diasRestantes} días`}
        </Texto>
      </View>

      {incluidas.length === 0 ? (
        <Texto tono="suave" style={{ paddingVertical: espacio.m }}>
          Tu plan incluye el coworking, sin horas de sala ni estudio.
        </Texto>
      ) : (
        incluidas.map((b, i) => <FilaBolsa key={b.bolsa} bolsa={b} retraso={i * 120} />)
      )}

      {reinicio || coworkingIlimitado ? (
        <Texto variante="pequeno" tono="tenue" style={{ marginTop: espacio.s }}>
          {[coworkingIlimitado ? 'Coworking ilimitado' : null, reinicio].filter(Boolean).join(' · ')}
        </Texto>
      ) : null}
    </Tarjeta>
  );
}

function ProximaReserva({ reserva }: { reserva: NonNullable<DatosInicio['proxima_reserva']> }) {
  const { p } = useTema();
  const [ahora, setAhora] = useState(() => new Date());

  // La cuenta regresiva se mueve sola mientras la pantalla está abierta.
  useEffect(() => {
    const t = setInterval(() => setAhora(new Date()), 30_000);
    return () => clearInterval(t);
  }, []);

  const nombre = reserva.espacio?.nombre ?? 'Tu reserva';
  const cuando = `${diaRelativo(reserva.fecha)}, ${reserva.hora_inicio} a ${reserva.hora_fin}`;
  const falta = cuentaRegresiva(reserva.empieza_en, ahora);

  return (
    <Tarjeta
      alPulsar={() => router.push({ pathname: '/reserva/[id]', params: { id: String(reserva.id) } })}
      etiquetaAccesible={`${nombre}. ${cuando}. Empieza ${falta}. Toca para ver el detalle.`}>
      <Texto variante="subtitulo">{nombre}</Texto>
      <Texto tono="suave">{cuando}</Texto>
      <View style={[estilos.cuenta, { backgroundColor: p.superficieAlta }]}>
        <Icono nombre="reloj" color={p.acentoTexto} tamano={16} />
        <Texto variante="pequeno">Empieza {falta}</Texto>
      </View>
    </Tarjeta>
  );
}

function SinMembresia() {
  const { p } = useTema();
  return (
    <Tarjeta>
      <Texto variante="subtitulo">Elige tu plan</Texto>
      <Texto tono="suave">Con una membresía reservas salas, estudios y asesoría desde aquí.</Texto>
      <Pressable
        onPress={() => router.push('/contratar')}
        accessibilityRole="button"
        style={[estilos.cta, { backgroundColor: p.acento }]}>
        <Texto variante="boton" tono="sobreAcento">
          Ver planes
        </Texto>
      </Pressable>
    </Tarjeta>
  );
}

/** Una sola invitación, discreta, si al plan le falta alguna bolsa. */
function Sugerencia({ bolsas }: { bolsas: Bolsa[] }) {
  const ausente = bolsas.find((b) => !b.incluida && b.sugerencia);
  if (!ausente?.sugerencia) return null;
  const s = ausente.sugerencia;

  return (
    <>
      <TituloSeccion>¿Necesitas más?</TituloSeccion>
      <Grupo>
        <Fila
          icono="mas"
          titulo={`${s.plan} incluye ${horas(s.incluye)} h de ${ausente.etiqueta.toLowerCase()}`}
          detalle={`Desde ${dinero(s.precio)}`}
          alPulsar={() => router.push('/contratar')}
        />
      </Grupo>
    </>
  );
}

function Insignia({ n }: { n: number }) {
  const { p } = useTema();
  return (
    <View style={[estilos.insignia, { backgroundColor: p.acento }]}>
      <Texto variante="etiqueta" tono="sobreAcento">
        {n}
      </Texto>
    </View>
  );
}

const estilos = StyleSheet.create({
  cabecera: { flexDirection: 'row', alignItems: 'center', gap: espacio.l, marginBottom: espacio.xl },
  avatar: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  planFila: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    marginBottom: espacio.xs,
  },
  cuenta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: radio.total,
    marginTop: espacio.xs,
  },
  insignia: {
    minWidth: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 7,
  },
  cta: {
    alignSelf: 'flex-start',
    borderRadius: radio.total,
    paddingHorizontal: 20,
    paddingVertical: 12,
    marginTop: espacio.s,
    minHeight: 48,
    justifyContent: 'center',
  },
});
