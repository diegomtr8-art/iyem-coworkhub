import { forwardRef, useState } from 'react';
import { StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { espacio, fuentes, radio, useTema } from '@/tema';

import { Texto } from './Texto';

type Props = TextInputProps & { etiqueta: string; error?: string | null; ayuda?: string };

/**
 * Campo de formulario. El error del servidor (`errors.campo[0]`) se pinta justo
 * debajo del campo al que se refiere, no en un aviso genérico arriba.
 * Tamaño de letra 16: por debajo, iOS hace zoom al enfocar.
 */
export const Campo = forwardRef<TextInput, Props>(function Campo({ etiqueta, error, ayuda, style, ...resto }, ref) {
  const { p } = useTema();
  const [enfocado, setEnfocado] = useState(false);

  return (
    <View style={{ gap: 6 }}>
      <Texto variante="etiqueta" tono="suave">
        {etiqueta}
      </Texto>
      <TextInput
        ref={ref}
        placeholderTextColor={p.textoTenue}
        selectionColor={p.acento}
        {...resto}
        onFocus={(e) => {
          setEnfocado(true);
          resto.onFocus?.(e);
        }}
        onBlur={(e) => {
          setEnfocado(false);
          resto.onBlur?.(e);
        }}
        style={[
          estilos.entrada,
          {
            color: p.texto,
            backgroundColor: p.superficie,
            borderColor: error ? p.problema : enfocado ? p.acentoTexto : p.borde,
          },
          resto.multiline && { minHeight: 96, textAlignVertical: 'top', paddingTop: 14 },
          style,
        ]}
      />
      {error ? (
        <Texto variante="pequeno" tono="problema">
          {error}
        </Texto>
      ) : ayuda ? (
        <Texto variante="pequeno" tono="tenue">
          {ayuda}
        </Texto>
      ) : null}
    </View>
  );
});

const estilos = StyleSheet.create({
  entrada: {
    minHeight: 54,
    borderRadius: radio.m,
    borderWidth: 1.5,
    paddingHorizontal: espacio.l,
    fontFamily: fuentes.texto,
    fontSize: 16,
  },
});
