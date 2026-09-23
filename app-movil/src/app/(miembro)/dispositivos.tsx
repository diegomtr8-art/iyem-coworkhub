import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, View } from 'react-native';

import { Chip, Fila, Separador, Tarjeta } from '@/componentes/Base';
import { EsqueletoLista } from '@/componentes/Esqueleto';
import { ErrorDeCarga, Pantalla } from '@/componentes/Pantalla';
import { Texto } from '@/componentes/Texto';
import { api, ErrorApi } from '@/lib/api';
import { claves } from '@/lib/consultas';
import { fechaCorta, haceCuanto } from '@/lib/formato';
import type { Dispositivo } from '@/lib/tipos';
import { espacio, useTema } from '@/tema';

/** Teléfonos con sesión abierta. Si pierdes uno, lo desconectas desde otro. */
export default function Dispositivos() {
  const { p } = useTema();
  const cliente = useQueryClient();
  const consulta = useQuery({
    queryKey: claves.dispositivos,
    queryFn: () => api<Dispositivo[]>('/yo/dispositivos'),
    meta: { persistir: false },
  });

  const revocar = useMutation({
    mutationFn: (id: number) => api(`/yo/dispositivos/${id}`, { metodo: 'DELETE' }),
    onSuccess: () => void cliente.invalidateQueries({ queryKey: claves.dispositivos }),
    onError: (e) => Alert.alert('No se pudo', e instanceof ErrorApi ? e.primero : 'Inténtalo de nuevo.'),
  });

  const lista = consulta.data ?? [];

  return (
    <Pantalla conMargenSuperior={false} alRefrescar={() => consulta.refetch()}>
      <Texto tono="suave" style={{ marginBottom: espacio.xl }}>
        Cada teléfono donde entraste a la app. Cerrar la sesión de uno lo desconecta al instante.
      </Texto>
      {consulta.isPending ? (
        <EsqueletoLista filas={2} />
      ) : consulta.isError ? (
        <ErrorDeCarga mensaje="No pudimos cargar tus dispositivos." alReintentar={() => void consulta.refetch()} />
      ) : (
        <Tarjeta estilo={{ paddingVertical: espacio.s }}>
          {lista.map((d, i) => (
            <View key={d.id}>
              {i > 0 ? <Separador /> : null}
              <Fila
                icono="telefono"
                titulo={d.nombre}
                detalle={`${d.ultimo_uso ? `Usado ${haceCuanto(new Date(d.ultimo_uso).getTime())}` : 'Sin uso'} · desde ${fechaCorta(d.creado)}`}
                derecha={
                  d.es_este ? (
                    <Chip texto="Este" color={p.bien} />
                  ) : (
                    <Texto
                      variante="pequeno"
                      tono="problema"
                      onPress={() =>
                        Alert.alert('¿Cerrar sesión en este teléfono?', d.nombre, [
                          { text: 'No', style: 'cancel' },
                          { text: 'Cerrar sesión', style: 'destructive', onPress: () => revocar.mutate(d.id) },
                        ])
                      }>
                      Cerrar sesión
                    </Texto>
                  )
                }
              />
            </View>
          ))}
        </Tarjeta>
      )}
    </Pantalla>
  );
}
