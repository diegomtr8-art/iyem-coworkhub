import { useQuery } from '@tanstack/react-query';
import { Alert, Pressable, StyleSheet, View } from 'react-native';

import { EsqueletoLista } from '@/componentes/Esqueleto';
import { Icono } from '@/componentes/Icono';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Cifra, SelectorRango } from '@/componentes/Reportes';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { dinero, primerNombre } from '@/lib/formato';
import { useRango } from '@/lib/rango';
import { useSesion } from '@/lib/sesion';
import type { ReporteResumen } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

/** Portada de reportes: las cifras grandes del periodo. */
export default function Resumen() {
  const { p } = useTema();
  const { usuario, salir } = useSesion();
  const { desde, hasta } = useRango();
  const consulta = useQuery({
    queryKey: claves.reporte('resumen', desde, hasta),
    queryFn: () => api<ReporteResumen>('/reportes/resumen', { consulta: { desde, hasta } }),
    meta: { persistir: false },
  });
  const d = consulta.data;
  const num = (clave: string) => (typeof d?.[clave] === 'number' ? (d[clave] as number) : null);

  return (
    <Pantalla
      titulo="Reportes"
      subtitulo={`Hola, ${primerNombre(usuario?.nombre)}. ${d?.rango?.etiqueta ?? ''}`}
      alRefrescar={() => consulta.refetch()}
      derecha={
        <Pressable
          accessibilityLabel="Cerrar sesión"
          hitSlop={10}
          onPress={() =>
            Alert.alert('¿Cerrar sesión?', undefined, [
              { text: 'Cancelar', style: 'cancel' },
              { text: 'Cerrar sesión', style: 'destructive', onPress: () => void salir() },
            ])
          }>
          <Icono nombre="salir" color={p.textoSuave} />
        </Pressable>
      }>
      <SelectorRango />
      {consulta.isPending ? (
        <EsqueletoLista filas={2} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar el resumen." alReintentar={() => void consulta.refetch()} />
      ) : (
        <View style={estilos.rejilla}>
          <Cifra
            etiqueta="Ocupación media"
            valor={num('ocupacion_media_pct') !== null ? `${num('ocupacion_media_pct')}%` : '—'}
            color={p.acentoTexto}
            detalle="De salas y estudios"
          />
          <Cifra
            etiqueta="Ingresos"
            valor={num('ingresos') !== null ? dinero(num('ingresos')) : '—'}
            color={p.lima}
            detalle="Membresías y salones"
          />
          <Cifra
            etiqueta="No-show"
            valor={num('tasa_no_show_pct') !== null ? `${num('tasa_no_show_pct')}%` : '—'}
            color={p.coral}
            detalle="Reservas sin asistir"
          />
          <Cifra
            etiqueta="En riesgo"
            valor={num('miembros_en_riesgo') !== null ? String(num('miembros_en_riesgo')) : '—'}
            color={p.morado}
            detalle="Miembros que dejaron de venir"
          />
        </View>
      )}
      <Texto variante="pequeno" tono="tenue" style={{ marginTop: espacio.xl }}>
        Solo lectura. La operación diaria se hace en el panel web.
      </Texto>
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  rejilla: { flexDirection: 'row', flexWrap: 'wrap', gap: espacio.m },
});
