import { StyleSheet, View } from 'react-native';

import { dinero } from '@/lib/formato';
import type { PlanDescrito } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

import { Chip } from './Base';
import { Icono } from './Icono';
import { Texto } from './Texto';

/** Un plan con lo que incluye, tal como lo describe el servidor. */
export function TarjetaPlan({ plan, pie }: { plan: PlanDescrito; pie?: React.ReactNode }) {
  const { p, esOscuro } = useTema();
  const color = plan.color || p.acento;
  const periodo = plan.periodo_label ?? plan.periodo_etiqueta ?? '';

  return (
    <View
      style={[
        estilos.tarjeta,
        { backgroundColor: p.superficie },
        // Solo el plan actual lleva borde; los demás, el mismo filo discreto del resto de la app.
        plan.es_el_actual
          ? { borderWidth: 2, borderColor: p.acentoTexto }
          : !esOscuro && { borderWidth: StyleSheet.hairlineWidth, borderColor: p.borde },
      ]}>
      <View style={{ gap: espacio.s }}>
        {plan.es_el_actual ? <Chip texto="Tu plan" color={p.acento} relleno /> : null}
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: espacio.s }}>
          <View style={[estilos.punto, { backgroundColor: color }]} />
          <Texto variante="subtitulo" accessibilityRole="header">
            {plan.nombre}
          </Texto>
        </View>
        {plan.subtitulo ? (
          <Texto variante="pequeno" tono="suave">
            {plan.subtitulo}
          </Texto>
        ) : null}
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 6 }}>
        <Texto variante="numero">{dinero(plan.precio)}</Texto>
        {periodo ? (
          <Texto tono="suave" variante="pequeno">
            {periodo}
          </Texto>
        ) : null}
      </View>
      <View style={{ gap: espacio.s }}>
        {plan.incluye.map((linea) => (
          <View key={linea} style={estilos.linea}>
            <Icono nombre="check" color={p.textoSuave} tamano={18} />
            <Texto variante="pequeno" style={{ flex: 1 }}>
              {linea}
            </Texto>
          </View>
        ))}
      </View>
      {pie}
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    borderRadius: radio.l,
    padding: espacio.xl,
    gap: espacio.l,
    overflow: 'hidden',
  },
  punto: { width: 12, height: 12, borderRadius: 6 },
  linea: { flexDirection: 'row', gap: espacio.s, alignItems: 'flex-start' },
});
