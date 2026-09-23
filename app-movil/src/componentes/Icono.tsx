import Svg, { Circle, Path, Rect } from 'react-native-svg';

/**
 * Iconos propios en SVG, de trazo, a 24 px. Se dibujan aquí en vez de traer
 * una fuente de iconos: pesan nada, se tiñen con el tema y no dependen de un
 * módulo nativo que Expo Go pudiera no traer.
 */

export type NombreIcono =
  | 'inicio'
  | 'reservar'
  | 'credencial'
  | 'membresia'
  | 'perfil'
  | 'flecha'
  | 'atras'
  | 'cerrar'
  | 'reloj'
  | 'check'
  | 'alerta'
  | 'salir'
  | 'camara'
  | 'documento'
  | 'descarga'
  | 'compartir'
  | 'calendario'
  | 'campana'
  | 'candado'
  | 'grafica'
  | 'dinero'
  | 'personas'
  | 'mas'
  | 'sinSenal'
  | 'huella'
  | 'foco'
  | 'lista'
  | 'telefono'
  | 'correo'
  | 'tarjeta'
  | 'banco'
  | 'ubicacion'
  | 'papelera'
  | 'recargar'
  | 'sol';

type Props = { nombre: NombreIcono; color: string; tamano?: number; grosor?: number };

export function Icono({ nombre, color, tamano = 24, grosor = 1.8 }: Props) {
  const trazo = { stroke: color, strokeWidth: grosor, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: 'none' };

  return (
    <Svg width={tamano} height={tamano} viewBox="0 0 24 24">
      {dibujo(nombre, trazo, color)}
    </Svg>
  );
}

function dibujo(nombre: NombreIcono, t: Record<string, unknown>, color: string) {
  switch (nombre) {
    case 'inicio':
      return (
        <>
          <Path d="M3.5 10.5 12 3.5l8.5 7" {...t} />
          <Path d="M5.5 9v10.5h13V9" {...t} />
          <Path d="M10 19.5v-5h4v5" {...t} />
        </>
      );
    case 'reservar':
      return (
        <>
          <Rect x={3.5} y={5} width={17} height={15.5} rx={3} {...t} />
          <Path d="M3.5 10h17M8 3v4M16 3v4M12 13v5M9.5 15.5h5" {...t} />
        </>
      );
    case 'credencial':
      return (
        <>
          <Rect x={3.5} y={3.5} width={7} height={7} rx={1.5} {...t} />
          <Rect x={13.5} y={3.5} width={7} height={7} rx={1.5} {...t} />
          <Rect x={3.5} y={13.5} width={7} height={7} rx={1.5} {...t} />
          <Path d="M13.5 13.5h3v3h-3zM17.5 17.5h3v3h-3zM20.5 13.5v1.5M13.5 20.5h1.5" {...t} />
        </>
      );
    case 'membresia':
      return (
        <>
          {/* Tarjeta de socio: se entiende como «mi membresía», la estrella no. */}
          <Rect x={3} y={5.5} width={18} height={13} rx={2.5} {...t} />
          <Circle cx={8.5} cy={11} r={2} {...t} />
          <Path d="M5.8 15.5c.6-1.3 1.6-2 2.7-2s2.1.7 2.7 2M14 10h4M14 13.5h3" {...t} />
        </>
      );
    case 'perfil':
      return (
        <>
          <Circle cx={12} cy={8.5} r={4} {...t} />
          <Path d="M4.5 20.5c1.2-3.8 4.1-5.5 7.5-5.5s6.3 1.7 7.5 5.5" {...t} />
        </>
      );
    case 'flecha':
      return <Path d="M9 5l7 7-7 7" {...t} />;
    case 'atras':
      return <Path d="M15 5l-7 7 7 7" {...t} />;
    case 'cerrar':
      return <Path d="M6 6l12 12M18 6 6 18" {...t} />;
    case 'reloj':
      return (
        <>
          <Circle cx={12} cy={12} r={8.5} {...t} />
          <Path d="M12 7.5V12l3 2" {...t} />
        </>
      );
    case 'check':
      return <Path d="M5 12.5l4.5 4.5L19 7.5" {...t} />;
    case 'alerta':
      return (
        <>
          <Path d="M12 4 21 19.5H3z" {...t} />
          <Path d="M12 10v4" {...t} />
          <Circle cx={12} cy={16.8} r={0.6} fill={color} stroke={color} />
        </>
      );
    case 'salir':
      return (
        <>
          <Path d="M14 4.5H6.5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2H14" {...t} />
          <Path d="M10 12h10M16.5 8.5 20 12l-3.5 3.5" {...t} />
        </>
      );
    case 'camara':
      return (
        <>
          <Path d="M4 8.5a2 2 0 0 1 2-2h2l1.5-2h5L16 6.5h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" {...t} />
          <Circle cx={12} cy={12.5} r={3.5} {...t} />
        </>
      );
    case 'documento':
      return (
        <>
          <Path d="M6.5 3.5h7l4 4v13h-11z" {...t} />
          <Path d="M13.5 3.5v4h4M9 12h6M9 15.5h6" {...t} />
        </>
      );
    case 'descarga':
      return <Path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14" {...t} />;
    case 'compartir':
      return (
        <>
          <Path d="M12 3.5v11M8 7.5l4-4 4 4" {...t} />
          <Path d="M7 11H5.5v9.5h13V11H17" {...t} />
        </>
      );
    case 'calendario':
      return (
        <>
          <Rect x={3.5} y={5} width={17} height={15.5} rx={3} {...t} />
          <Path d="M3.5 10h17M8 3v4M16 3v4" {...t} />
        </>
      );
    case 'campana':
      return (
        <>
          <Path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15z" {...t} />
          <Path d="M10 20.5a2 2 0 0 0 4 0" {...t} />
        </>
      );
    case 'candado':
      return (
        <>
          <Rect x={4.5} y={10.5} width={15} height={10} rx={2.5} {...t} />
          <Path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" {...t} />
        </>
      );
    case 'grafica':
      return <Path d="M4 20h16M7 16.5V11M12 16.5V6.5M17 16.5v-8" {...t} />;
    case 'dinero':
      return (
        <>
          <Rect x={3} y={6} width={18} height={12} rx={2.5} {...t} />
          <Circle cx={12} cy={12} r={2.5} {...t} />
        </>
      );
    case 'personas':
      return (
        <>
          <Circle cx={9} cy={8.5} r={3.5} {...t} />
          <Path d="M3 19.5c.9-3.2 3.2-4.5 6-4.5s5.1 1.3 6 4.5M15.5 5.3a3.5 3.5 0 0 1 0 6.4M17.5 15.3c1.7.5 2.9 1.8 3.5 4.2" {...t} />
        </>
      );
    case 'mas':
      return <Path d="M12 5v14M5 12h14" {...t} />;
    case 'sinSenal':
      return (
        <>
          <Path
            d="M3 3l18 18M8.5 16a5 5 0 0 1 7 0M5.5 12.5a9 9 0 0 1 4-2.3M18.5 12.5a9 9 0 0 0-2.2-1.6M2.5 9a13 13 0 0 1 4.3-2.8M21.5 9a13 13 0 0 0-8.3-3.4"
            {...t}
          />
          <Circle cx={12} cy={19} r={0.8} fill={color} stroke={color} />
        </>
      );
    case 'huella':
      return (
        <Path
          d="M7 19.5c1.3-2 2-4.3 2-7a3 3 0 0 1 6 0c0 1.6-.2 3.2-.6 4.7M12 12.5c0 3.2-.8 6.1-2.3 8.5M17.8 15.5c.2-1 .2-2 .2-3a6 6 0 0 0-11.1-3.2M4.5 15.5c.3-1 .5-2 .5-3a7 7 0 0 1 .7-3M16 20c.4-.8.8-1.6 1-2.5"
          {...t}
        />
      );
    case 'foco':
      return (
        <>
          <Circle cx={12} cy={12} r={3} {...t} />
          <Path d="M3.5 8V5.5a2 2 0 0 1 2-2H8M16 3.5h2.5a2 2 0 0 1 2 2V8M20.5 16v2.5a2 2 0 0 1-2 2H16M8 20.5H5.5a2 2 0 0 1-2-2V16" {...t} />
        </>
      );
    case 'lista':
      return <Path d="M9 6.5h11M9 12h11M9 17.5h11M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01" {...t} />;
    case 'telefono':
      return (
        <Path
          d="M5 4.5h3.5l1.5 4-2 1.5a10 10 0 0 0 6 6l1.5-2 4 1.5V19a1.5 1.5 0 0 1-1.5 1.5A15.5 15.5 0 0 1 3.5 6 1.5 1.5 0 0 1 5 4.5z"
          {...t}
        />
      );
    case 'correo':
      return (
        <>
          <Rect x={3.5} y={5.5} width={17} height={13} rx={2.5} {...t} />
          <Path d="M4 7l8 6 8-6" {...t} />
        </>
      );
    case 'tarjeta':
      return (
        <>
          <Rect x={3} y={5.5} width={18} height={13} rx={2.5} {...t} />
          <Path d="M3 10h18M7 15h3" {...t} />
        </>
      );
    case 'banco':
      return <Path d="M3.5 9.5 12 4.5l8.5 5M5 9.5v8M9.5 9.5v8M14.5 9.5v8M19 9.5v8M3.5 19.5h17" {...t} />;
    case 'ubicacion':
      return (
        <>
          <Path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z" {...t} />
          <Circle cx={12} cy={10} r={2.3} {...t} />
        </>
      );
    case 'papelera':
      return <Path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10.5 11v5.5M13.5 11v5.5" {...t} />;
    case 'recargar':
      return <Path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5v4h-4" {...t} />;
    case 'sol':
      return (
        <>
          <Circle cx={12} cy={12} r={4} {...t} />
          <Path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" {...t} />
        </>
      );
    default:
      return null;
  }
}
