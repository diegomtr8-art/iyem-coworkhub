import { useQuery, useQueryClient } from '@tanstack/react-query';
import * as Crypto from 'expo-crypto';
import * as Haptics from 'expo-haptics';
import { router, useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { hoyYmd, nombreDiaCorto, nombreMesCorto, sumarDias } from '@/lib/formato';
import type { Asesorias } from '@/lib/tipos';
import { espacio, radio, useTema } from '@/tema';

/** Hoja para pedir una asesoría sobre un tema del catálogo. */
export default function SolicitarAsesoria() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const { tema_id } = useLocalSearchParams<{ tema_id: string }>();
  const { data } = useQuery({ queryKey: claves.asesorias, queryFn: () => api<Asesorias>('/asesorias') });
  const tema = data?.oferta.flatMap((g) => g.temas).find((t) => String(t.id) === tema_id);

  const dias = Array.from({ length: 21 }, (_, i) => sumarDias(hoyYmd(), i + 1)).filter((d) => nombreDiaCorto(d) !== 'dom');
  const [dia, setDia] = useState(dias[0]);
  const [franja, setFranja] = useState<string | null>(null);
  const [asesor, setAsesor] = useState<number | null>(null);
  const [detalle, setDetalle] = useState('');
  const [error, setError] = useState<ErrorApi | null>(null);
  const [enviando, setEnviando] = useState(false);
  const clave = useRef(Crypto.randomUUID());

  const enviar = async () => {
    setEnviando(true);
    setError(null);
    try {
      await api('/asesorias', {
        metodo: 'POST',
        idempotencia: clave.current,
        cuerpo: {
          tema_id: Number(tema_id),
          detalle: detalle.trim() || null,
          asesor_preferido_id: asesor,
          dia_preferido: dia,
          horario_preferido: franja,
          horas: 1,
        },
      });
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      void cliente.invalidateQueries({ queryKey: claves.asesorias });
      void cliente.invalidateQueries({ queryKey: claves.inicio });
      router.back();
    } catch (e) {
      setError(e instanceof ErrorApi ? e : null);
      if (e instanceof ErrorApi && !e.sinConexion) clave.current = Crypto.randomUUID();
    } finally {
      setEnviando(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={estilos.hoja} keyboardShouldPersistTaps="handled">
        <Texto variante="etiqueta" tono="tenue">
          Solicitar asesoría
        </Texto>
        <Texto variante="titulo">{tema?.nombre ?? 'Asesoría'}</Texto>
        {tema?.descripcion_corta ? <Texto tono="suave">{tema.descripcion_corta}</Texto> : null}

        <Texto variante="seccion" style={{ marginTop: espacio.m }}>
          ¿Qué día te viene bien?
        </Texto>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: espacio.s }}>
          {dias.map((d) => {
            const activo = d === dia;
            return (
              <Pressable
                key={d}
                onPress={() => setDia(d)}
                style={[estilos.dia, { backgroundColor: activo ? p.acento : p.fondo, borderColor: activo ? p.acentoTexto : p.borde }]}>
                <Texto variante="etiqueta" color={activo ? p.sobreAcento : p.textoTenue} style={{ letterSpacing: 0.3 }}>
                  {nombreDiaCorto(d)}
                </Texto>
                <Texto variante="seccion" color={activo ? p.sobreAcento : p.texto}>
                  {Number(d.slice(8, 10))}
                </Texto>
                <Texto variante="pequeno" color={activo ? p.sobreAcento : p.textoTenue} style={{ fontSize: 11 }}>
                  {nombreMesCorto(d)}
                </Texto>
              </Pressable>
            );
          })}
        </ScrollView>
        {error?.campo('dia_preferido') ? (
          <Texto variante="pequeno" tono="problema">
            {error.campo('dia_preferido')}
          </Texto>
        ) : null}

        <Texto variante="seccion" style={{ marginTop: espacio.m }}>
          ¿En qué horario?
        </Texto>
        <View style={{ gap: espacio.s }}>
          {(data?.franjas ?? []).map((f) => (
            <Pressable
              key={f}
              onPress={() => setFranja(f)}
              style={[estilos.opcion, { backgroundColor: p.fondo, borderColor: franja === f ? p.acentoTexto : p.borde }]}>
              <View style={[estilos.radio, { borderColor: franja === f ? p.acentoTexto : p.textoTenue }]}>
                {franja === f ? <View style={[estilos.radioPunto, { backgroundColor: p.acento }]} /> : null}
              </View>
              <Texto>{f}</Texto>
            </Pressable>
          ))}
        </View>

        {tema?.asesores.length ? (
          <>
            <Texto variante="seccion" style={{ marginTop: espacio.m }}>
              ¿Con alguien en especial? <Texto tono="tenue">(opcional)</Texto>
            </Texto>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: espacio.s }}>
              {tema.asesores.map((a) => (
                <Pressable
                  key={a.id}
                  onPress={() => setAsesor(asesor === a.id ? null : a.id)}
                  style={[
                    estilos.chip,
                    {
                      backgroundColor: asesor === a.id ? p.morado : p.fondo,
                      borderColor: asesor === a.id ? p.morado : p.borde,
                    },
                  ]}>
                  <Texto variante="pequeno" color={asesor === a.id ? '#fff' : p.texto}>
                    {a.nombre}
                  </Texto>
                </Pressable>
              ))}
            </View>
          </>
        ) : null}

        <Campo
          etiqueta="Cuéntanos más (opcional)"
          value={detalle}
          onChangeText={setDetalle}
          multiline
          maxLength={500}
          placeholder="Por ejemplo: quiero revisar mi plan de negocios antes de pedir un crédito."
          error={error?.campo('detalle')}
        />

        {error && (error.campo('horas') || error.campo('general') || !Object.keys(error.errores).length) ? (
          <Texto variante="pequeno" tono="problema">
            {error.campo('horas') ?? error.campo('general') ?? error.message}
          </Texto>
        ) : null}

        <Boton titulo="Enviar solicitud" alPulsar={enviar} ocupado={enviando} deshabilitado={!franja} haptica={false} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  hoja: { padding: espacio.xl, paddingTop: espacio.xxl, gap: espacio.m, paddingBottom: 60 },
  dia: { width: 56, height: 76, borderRadius: radio.m, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  opcion: { flexDirection: 'row', alignItems: 'center', gap: espacio.m, padding: espacio.l, borderRadius: radio.m, borderWidth: 1.5 },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  radioPunto: { width: 10, height: 10, borderRadius: 5 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radio.total, borderWidth: 1 },
});
