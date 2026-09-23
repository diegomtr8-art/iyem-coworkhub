import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { View } from 'react-native';

import { Boton } from '@/componentes/Boton';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { TarjetaPlan } from '@/componentes/TarjetaPlan';
import { Texto } from '@/componentes/Texto';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import type { Contratables } from '@/lib/tipos';
import { espacio } from '@/tema';

/** Contratar o renovar, dentro de la app. */
export default function Contratar() {
  const consulta = useQuery({ queryKey: claves.contratables, queryFn: () => api<Contratables>('/planes/contratables') });
  const d = consulta.data;

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <Texto tono="suave" style={{ marginBottom: espacio.xl }}>
        Paga con tarjeta o con referencia (transferencia o efectivo). Tu membresía se activa en cuanto se confirma el pago.
      </Texto>
      <AvisoSinConexion consulta={consulta} />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : !d ? (
        <ErrorDeCarga mensaje="No pudimos cargar los planes." alReintentar={() => void consulta.refetch()} />
      ) : (
        <View style={{ gap: espacio.l }}>
          {d.planes.map((plan) => (
            <TarjetaPlan
              key={plan.id}
              plan={plan}
              pie={
                <Boton
                  titulo={plan.es_el_actual ? 'Renovar' : 'Elegir este plan'}
                  variante={plan.es_el_actual ? 'principal' : 'secundario'}
                  alPulsar={() => router.push({ pathname: '/pagar', params: { plan_id: String(plan.id) } })}
                />
              }
            />
          ))}
        </View>
      )}
    </Pantalla>
  );
}
