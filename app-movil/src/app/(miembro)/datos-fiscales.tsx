import { useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, View } from 'react-native';

import { Chip, Tarjeta } from '@/componentes/Base';
import { Boton } from '@/componentes/Boton';
import { Campo } from '@/componentes/Campo';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Selector } from '@/componentes/Selector';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import type { DatosFiscales as Datos } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

/**
 * Datos fiscales. Dos cosas que la pantalla tiene que decir: Nódico no emite la
 * factura —la emite contabilidad del IYEM— y cuánto tarda. No se guardan en la
 * caché del teléfono: son datos personales sensibles.
 */
export default function DatosFiscales() {
  const consulta = useQuery({
    queryKey: claves.datosFiscales,
    queryFn: () => api<Datos>('/yo/datos-fiscales'),
    meta: { persistir: false },
    staleTime: 0,
  });
  const d = consulta.data;

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
      <Pantalla conMargenSuperior={false}>
        {consulta.isPending ? (
          <EsqueletoLista filas={3} />
        ) : !d ? (
          <ErrorDeCarga mensaje="No pudimos cargar tus datos fiscales." alReintentar={() => void consulta.refetch()} />
        ) : (
          <Formulario d={d} />
        )}
      </Pantalla>
    </KeyboardAvoidingView>
  );
}

function Formulario({ d }: { d: Datos }) {
  const { p } = useTema();
  const cliente = useQueryClient();
  const [f, setF] = useState({
    rfc: d.datos?.rfc ?? '',
    razon_social: d.datos?.razon_social ?? '',
    regimen_fiscal: d.datos?.regimen_fiscal ?? '',
    uso_cfdi: d.datos?.uso_cfdi ?? d.uso_predeterminado ?? '',
    codigo_postal: d.datos?.codigo_postal ?? '',
    email_facturacion: d.datos?.email_facturacion ?? '',
  });
  const [error, setError] = useState<ErrorApi | null>(null);
  const [guardando, setGuardando] = useState(false);

  const guardar = async () => {
    setGuardando(true);
    setError(null);
    try {
      await api('/yo/datos-fiscales', { metodo: 'PUT', cuerpo: { ...f, rfc: f.rfc.toUpperCase().trim() } });
      void cliente.invalidateQueries({ queryKey: claves.datosFiscales });
      void cliente.invalidateQueries({ queryKey: claves.contratables });
      router.back();
    } catch (e) {
      setError(e instanceof ErrorApi ? e : null);
    } finally {
      setGuardando(false);
    }
  };

  return (
    <View style={{ gap: espacio.l }}>
      <Tarjeta>
        <Chip texto={d.completos ? 'Completos: ya puedes pedir factura' : 'Incompletos'} color={d.completos ? p.bien : p.atencion} />
        <Texto variante="pequeno" tono="suave">
          La factura la emite {d.proceso.emisor}, no Nódico, en un plazo de {d.proceso.dias_habiles} días hábiles.
          {d.proceso.contacto ? ` Dudas: ${d.proceso.contacto}.` : ''}
        </Texto>
      </Tarjeta>
      <Campo
        etiqueta="RFC"
        value={f.rfc}
        onChangeText={(v) => setF({ ...f, rfc: v.toUpperCase() })}
        autoCapitalize="characters"
        maxLength={13}
        error={error?.campo('rfc')}
      />
      <Campo
        etiqueta="Razón social o nombre"
        value={f.razon_social}
        onChangeText={(v) => setF({ ...f, razon_social: v })}
        error={error?.campo('razon_social')}
        ayuda="Tal cual aparece en tu constancia de situación fiscal."
      />
      <Selector
        etiqueta="Régimen fiscal"
        valor={f.regimen_fiscal}
        opciones={d.regimenes.map((r) => ({ valor: r.clave, etiqueta: `${r.clave} · ${r.nombre}` }))}
        alCambiar={(v) => setF({ ...f, regimen_fiscal: v })}
        error={error?.campo('regimen_fiscal')}
      />
      <Selector
        etiqueta="Uso del CFDI"
        valor={f.uso_cfdi}
        opciones={d.usos_cfdi.map((u) => ({ valor: u.clave, etiqueta: `${u.clave} · ${u.nombre}` }))}
        alCambiar={(v) => setF({ ...f, uso_cfdi: v })}
        error={error?.campo('uso_cfdi')}
      />
      <Campo
        etiqueta="Código postal fiscal"
        value={f.codigo_postal}
        onChangeText={(v) => setF({ ...f, codigo_postal: v })}
        keyboardType="number-pad"
        maxLength={5}
        error={error?.campo('codigo_postal')}
      />
      <Campo
        etiqueta="Correo para la factura"
        value={f.email_facturacion}
        onChangeText={(v) => setF({ ...f, email_facturacion: v })}
        keyboardType="email-address"
        autoCapitalize="none"
        error={error?.campo('email_facturacion')}
        ayuda="Opcional. Si lo dejas vacío, se usa el de tu cuenta."
      />
      {error && !Object.keys(error.errores).length ? (
        <Texto variante="pequeno" tono="problema">
          {error.message}
        </Texto>
      ) : null}
      <Boton titulo="Guardar" alPulsar={guardar} ocupado={guardando} />
    </View>
  );
}
