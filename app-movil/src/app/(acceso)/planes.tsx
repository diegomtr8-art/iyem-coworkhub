import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';

import { EsqueletoLista } from '@/componentes/Esqueleto';
import { AvisoSinConexion, ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { TarjetaPlan } from '@/componentes/TarjetaPlan';
import { Texto } from '@/componentes/Texto';
import { Boton } from '@/componentes/Boton';
import { api } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { abrirWeb } from '@/lib/ganchos';
import type { PlanDescrito } from '@/lib/tipos';
import { espacio } from '@/tema';

/** Vista previa de los planes para quien todavía no tiene cuenta. */
export default function Planes() {
  const consulta = useQuery({
    queryKey: claves.planes,
    queryFn: () => api<PlanDescrito[]>('/planes', { anonima: true }),
  });

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <Texto tono="suave" style={{ marginBottom: espacio.xl }}>
        Todas incluyen internet, café y una comunidad de gente que emprende en Mérida.
      </Texto>
      <AvisoSinConexion consulta={consulta} />
      {consulta.isPending ? (
        <EsqueletoLista filas={3} />
      ) : consulta.data ? (
        <View style={{ gap: espacio.l }}>
          {consulta.data.map((plan) => (
            <TarjetaPlan key={plan.id} plan={plan} />
          ))}
          <Boton titulo="Crear mi cuenta" alPulsar={() => void abrirWeb('/register')} estilo={{ marginTop: espacio.l }} />
        </View>
      ) : (
        <ErrorDeCarga mensaje="No pudimos cargar los planes." alReintentar={() => void consulta.refetch()} />
      )}
    </Pantalla>
  );
}
