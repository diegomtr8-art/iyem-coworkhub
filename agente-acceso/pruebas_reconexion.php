<?php

/**
 * Pruebas de la lógica de reconexión (php pruebas_reconexion.php).
 * Sin framework: es el agente, PHP puro. Cubren los casos del encargo (Fase 6).
 */

require __DIR__ . '/reconexion.php';

$fallos = 0;
$ok = 0;
function comprobar(string $nombre, bool $cond): void
{
    global $fallos, $ok;
    if ($cond) { $ok++; echo "  ✓ $nombre\n"; }
    else { $fallos++; echo "  ✗ $nombre  <-- FALLA\n"; }
}

$HOY = date('Y-m-d');
$AHORA = 1_000_000;   // epoch fijo para reproducibilidad

/** Contexto base: en horario, motor encendido, Smart Pass OK, sin eventos recientes. */
function ctx(array $over = []): array
{
    global $AHORA, $HOY;
    return array_merge([
        'ahora'               => $AHORA,
        'hoy'                 => $HOY,
        'online'              => false,
        'smartpass_ok'        => true,
        'evento_reciente_seg' => null,
        'en_horario'          => true,
        'auto'                => true,
        'confirmar_seg'       => 60,
        'silencio_seg'        => 30,
        'tope_hora'           => 6,
        'escalones'           => [60, 300, 900, 1800],
    ], $over);
}

echo "1) Una sola lectura caída NO reconecta; confirmada tras un minuto, sí\n";
$st = reconexionInicial($HOY);
$r = decidirReconexion($st, ctx());                 // primer read offline
comprobar('un blip no dispara reconexión', $r['accion'] === 'nada' && $r['motivo'] === 'sin_confirmar');
$st = $r['estado'];
// pasa el minuto: el offline_desde quedó en AHORA; avanzamos el reloj +61s
$r = decidirReconexion($st, ctx(['ahora' => $AHORA + 61]));
comprobar('confirmada la caída, reconecta', $r['accion'] === 'reconectar');
comprobar('cuenta una caída', $r['estado']['caidas_hoy'] === 1);

echo "2) Tras un intento, respeta la espera creciente (60 -> 300)\n";
$st = $r['estado'];
$base = $AHORA + 61;
$r2 = decidirReconexion($st, ctx(['ahora' => $base + 10]));   // 10s después
comprobar('dentro de la espera, no reintenta', $r2['accion'] === 'nada' && $r2['motivo'] === 'esperando');
$r3 = decidirReconexion($st, ctx(['ahora' => $base + 61]));   // pasó el primer escalón (60s)
comprobar('pasado el escalón, reintenta', $r3['accion'] === 'reconectar');
comprobar('el próximo escalón crece a 300s', $r3['estado']['proximo_intento'] === ($base + 61 + 300));

echo "3) Al tope por hora, deja de intentar y avisa una vez\n";
$st = reconexionInicial($HOY);
$st['caida_confirmada'] = true;
$st['caida_desde'] = $AHORA - 100;
$st['online'] = false;
$st['offline_desde'] = $AHORA - 100;
$st['intentos'] = array_fill(0, 6, $AHORA - 10);   // 6 intentos en la última hora
$r = decidirReconexion($st, ctx());
comprobar('al tope no reintenta', $r['accion'] === 'nada' && $r['motivo'] === 'tope');
comprobar('avisa del tope', is_string($r['alerta']));
$r2 = decidirReconexion($r['estado'], ctx());       // segunda vez: ya no re-avisa
comprobar('no re-avisa del tope', $r2['alerta'] === null);

echo "4) Con un reconocimiento reciente, pospone (ventana de silencio)\n";
$st = reconexionInicial($HOY);
$st['caida_confirmada'] = true; $st['online'] = false;
$st['offline_desde'] = $AHORA - 100; $st['caida_desde'] = $AHORA - 100;
$r = decidirReconexion($st, ctx(['evento_reciente_seg' => 5]));   // paso hace 5s (<30)
comprobar('pospone por silencio', $r['accion'] === 'nada' && $r['motivo'] === 'silencio');
comprobar('deja próximo intento en el futuro', $r['estado']['proximo_intento'] > $AHORA);

echo "5) Si Smart Pass no responde, no intenta nada\n";
$st = reconexionInicial($HOY);
$st['caida_confirmada'] = true; $st['online'] = false;
$st['offline_desde'] = $AHORA - 100;
$r = decidirReconexion($st, ctx(['smartpass_ok' => false]));
comprobar('smartpass caído: no actúa', $r['accion'] === 'nada' && $r['motivo'] === 'smartpass_caido');

echo "6) Motor apagado: detecta y cuenta, pero no reconecta\n";
$st = reconexionInicial($HOY);
$st['online'] = false; $st['offline_desde'] = $AHORA - 100;
$r = decidirReconexion($st, ctx(['auto' => false]));
comprobar('sin auto no reconecta', $r['accion'] === 'nada' && $r['motivo'] === 'detectar_sin_auto');
comprobar('pero sí cuenta la caída', $r['estado']['caidas_hoy'] === 1);

echo "7) Fuera de horario: detecta pero deja descansar al equipo\n";
$st = reconexionInicial($HOY);
$st['online'] = false; $st['offline_desde'] = $AHORA - 100;
$r = decidirReconexion($st, ctx(['en_horario' => false]));
comprobar('fuera de horario no reconecta', $r['accion'] === 'nada' && $r['motivo'] === 'fuera_horario');

echo "8) Recuperación: cuenta la reconexión y suma el downtime\n";
$st = reconexionInicial($HOY);
$st['caida_confirmada'] = true; $st['online'] = false;
$st['caida_desde'] = $AHORA - 120;   // llevaba 120s caído
$r = decidirReconexion($st, ctx(['online' => true]));
comprobar('vuelve en línea', $r['motivo'] === 'en_linea' && $r['estado']['online'] === true);
comprobar('cuenta la reconexión', $r['estado']['reconexiones_hoy'] === 1);
comprobar('suma el downtime (120s)', $r['estado']['downtime_seg_hoy'] === 120);
comprobar('marca cambio de estado (latido urgente)', $r['cambio'] === true);

echo "9) No se solapan órdenes: el escalón bloquea el reintento inmediato\n";
$st = reconexionInicial($HOY);
$st['caida_confirmada'] = true; $st['online'] = false;
$st['offline_desde'] = $AHORA - 100;
$st['intentos'] = [$AHORA - 5];            // acaba de intentar hace 5s
$st['proximo_intento'] = $AHORA + 55;      // con la espera puesta
$r = decidirReconexion($st, ctx());
comprobar('con un intento en curso, no dispara otro', $r['accion'] === 'nada' && $r['motivo'] === 'esperando');

echo "\n" . ($fallos === 0 ? "TODO EN VERDE" : "HUBO FALLOS") . ": $ok ok, $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
