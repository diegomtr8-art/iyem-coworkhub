<?php

/**
 * Motor de reconexión del torno FR07 — lógica PURA y probable (Fases 1-3).
 *
 * No toca red ni disco: recibe el estado persistido + un contexto (qué ve el
 * agente ahora) y devuelve el estado nuevo y qué hacer. El agente se encarga de
 * los efectos (ejecutar la reconexión, avisar, latir). Así la parte delicada
 * —cuándo reconectar y cuándo NO— se prueba sin equipo (ver pruebas_reconexion.php).
 *
 * Principio del encargo: LEER seguido (barato), ESCRIBIR poco (desgasta flash).
 * Por eso el reconectar tiene freno: confirmación de caída, espera creciente,
 * tope por hora, ventana de silencio y respeto del horario.
 */

/** Estado inicial del motor (se guarda en estado/reconexion.json). */
function reconexionInicial(string $hoy): array
{
    return [
        'fecha'            => $hoy,
        'online'           => null,   // último estado conocido (true/false/null)
        'offline_desde'    => null,   // epoch del primer read offline (para confirmar)
        'caida_confirmada' => false,  // ya confirmamos la caída actual
        'caida_desde'      => null,   // epoch de la caída confirmada (para el downtime)
        'proximo_intento'  => 0,      // epoch; 0 = puede intentar ya
        'nivel'            => 0,      // índice del escalón de espera
        'intentos'         => [],     // epochs de intentos (se podan a la última hora)
        'cap_avisado'      => false,  // ya avisamos que se alcanzó el tope
        'caidas_hoy'       => 0,
        'reconexiones_hoy' => 0,
        'downtime_seg_hoy' => 0,
    ];
}

/** Resumen para el panel (va en el latido). */
function reconexionResumen(array $st): array
{
    return [
        'caidas_hoy'       => (int) ($st['caidas_hoy'] ?? 0),
        'reconexiones_hoy' => (int) ($st['reconexiones_hoy'] ?? 0),
        'downtime_seg_hoy' => (int) ($st['downtime_seg_hoy'] ?? 0),
        'cap_alcanzado'    => (bool) ($st['cap_avisado'] ?? false),
    ];
}

/**
 * El corazón. Decide, sin efectos, qué hacer con el torno.
 *
 * $ctx: ahora(int), hoy(string), online(bool), smartpass_ok(bool),
 *       evento_reciente_seg(int|null), en_horario(bool), auto(bool),
 *       confirmar_seg(int), silencio_seg(int), tope_hora(int), escalones(int[]).
 *
 * Devuelve: estado(array nuevo), accion('reconectar'|'nada'), motivo(string),
 *           alerta(string|null), evento(string|null), cambio(bool).
 */
function decidirReconexion(array $st, array $ctx): array
{
    $ahora = $ctx['ahora'];
    $sal = ['accion' => 'nada', 'motivo' => '', 'alerta' => null, 'evento' => null, 'cambio' => false];

    // Corte de día: reinicia los contadores diarios (el resto del estado sigue).
    if (($st['fecha'] ?? null) !== $ctx['hoy']) {
        $st['fecha'] = $ctx['hoy'];
        $st['caidas_hoy'] = 0;
        $st['reconexiones_hoy'] = 0;
        $st['downtime_seg_hoy'] = 0;
    }

    // Poda de intentos: solo cuentan los de la última hora (para el tope/hora).
    $st['intentos'] = array_values(array_filter(
        $st['intentos'] ?? [],
        fn ($t) => ($ahora - $t) < 3600
    ));

    // Si el propio Smart Pass no responde, el problema está antes: no intentamos
    // nada contra el equipo y no cambiamos la lectura de estado.
    if (! $ctx['smartpass_ok']) {
        $sal['motivo'] = 'smartpass_caido';
        $sal['estado'] = $st;
        return $sal;
    }

    // ── Torno EN LÍNEA ────────────────────────────────────────────────────────
    if ($ctx['online']) {
        if ($st['caida_confirmada']) {
            // Se recuperó (por nuestro intento o por sí solo): cuenta y suma downtime.
            $st['reconexiones_hoy'] = (int) $st['reconexiones_hoy'] + 1;
            if ($st['caida_desde']) {
                $st['downtime_seg_hoy'] = (int) $st['downtime_seg_hoy'] + max(0, $ahora - (int) $st['caida_desde']);
            }
            $dur = $st['caida_desde'] ? ($ahora - (int) $st['caida_desde']) : 0;
            $sal['evento'] = "Torno reconectado (estuvo caído {$dur}s).";
        }
        if ($st['online'] !== true) {
            $sal['cambio'] = true;
        }
        $st['online'] = true;
        $st['offline_desde'] = null;
        $st['caida_confirmada'] = false;
        $st['caida_desde'] = null;
        $st['nivel'] = 0;
        $st['proximo_intento'] = 0;
        $st['cap_avisado'] = false;
        $sal['motivo'] = 'en_linea';
        $sal['estado'] = $st;
        return $sal;
    }

    // ── Torno SIN CONEXIÓN ──────────────────────────────────────────────────────
    if ($st['offline_desde'] === null) {
        $st['offline_desde'] = $ahora;   // primer read caído: arranca el reloj
    }
    if ($st['online'] !== false) {
        $sal['cambio'] = true;           // primer indicio de caída (informativo)
    }
    $st['online'] = false;

    // Un solo read caído puede ser un paquete perdido. Se confirma solo tras
    // `confirmar_seg` sin latido (el encargo: dos lecturas / un minuto).
    if (($ahora - (int) $st['offline_desde']) < $ctx['confirmar_seg']) {
        $sal['motivo'] = 'sin_confirmar';
        $sal['estado'] = $st;
        return $sal;
    }

    if (! $st['caida_confirmada']) {
        $st['caida_confirmada'] = true;
        $st['caida_desde'] = $st['offline_desde'];
        $st['caidas_hoy'] = (int) $st['caidas_hoy'] + 1;
        $st['nivel'] = 0;
        $st['proximo_intento'] = 0;
        $st['cap_avisado'] = false;
        $sal['evento'] = 'Caída del torno confirmada.';
        $sal['cambio'] = true;
    }

    // El motor apagado solo detecta y cuenta; no reconecta.
    if (! $ctx['auto']) {
        $sal['motivo'] = 'detectar_sin_auto';
        $sal['estado'] = $st;
        return $sal;
    }

    // Fuera del horario de operación nadie sufre la caída: se deja descansar al
    // equipo (no se le escribe) y se retoma en horario.
    if (! $ctx['en_horario']) {
        $sal['motivo'] = 'fuera_horario';
        $sal['estado'] = $st;
        return $sal;
    }

    // Ventana de silencio: si hubo un reconocimiento/apertura hace nada, no se
    // reconfigura ahora (podría cortar la apertura). Se pospone.
    if ($ctx['evento_reciente_seg'] !== null && $ctx['evento_reciente_seg'] < $ctx['silencio_seg']) {
        $st['proximo_intento'] = $ahora + max(10, $ctx['silencio_seg']);
        $sal['motivo'] = 'silencio';
        $sal['estado'] = $st;
        return $sal;
    }

    // Tope por hora: si se alcanzó, el problema no se arregla con más escrituras.
    // Se deja de intentar y se avisa (una vez).
    if (count($st['intentos']) >= $ctx['tope_hora']) {
        if (! $st['cap_avisado']) {
            $st['cap_avisado'] = true;
            $sal['alerta'] = 'El torno no reconecta: ' . $ctx['tope_hora']
                . ' intentos en la última hora sin éxito. Revisar el enlace físico.';
        }
        $sal['motivo'] = 'tope';
        $sal['estado'] = $st;
        return $sal;
    }

    // Espera creciente entre intentos.
    if ($ahora < (int) $st['proximo_intento']) {
        $sal['motivo'] = 'esperando';
        $sal['estado'] = $st;
        return $sal;
    }

    // ── Reconectar ──────────────────────────────────────────────────────────────
    $escalones = $ctx['escalones'] ?: [60, 300, 900, 1800];
    $idx = min((int) $st['nivel'], count($escalones) - 1);
    $espera = (int) $escalones[$idx];

    $st['intentos'][] = $ahora;
    $st['proximo_intento'] = $ahora + $espera;
    $st['nivel'] = min((int) $st['nivel'] + 1, count($escalones) - 1);

    $sal['accion'] = 'reconectar';
    $sal['motivo'] = 'reconectar';
    $sal['evento'] = 'Intento de reconexión #' . count($st['intentos'])
        . ' de la hora (si falla, el siguiente en ' . $espera . 's).';
    $sal['estado'] = $st;
    return $sal;
}
