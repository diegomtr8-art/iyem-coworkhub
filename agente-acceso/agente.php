<?php

/**
 * Agente de acceso de Nódico (Fase 1).
 *
 * Vive en la computadora de la oficina, junto a Smart Pass. Es el puente entre
 * la base de Smart Pass (red privada) y Nódico (Laravel, en internet). **Solo
 * hace conexiones salientes**: no abre puertos a internet; el único socket que
 * escucha es el de salud, atado a 127.0.0.1.
 *
 * Lo que hace, en bucle:
 *   1. Lee `tdx_pass_record` por id incremental (id > cursor). Nunca escribe en
 *      Smart Pass.
 *   2. Vuelca lo leído a una **cola local durable** y avanza el cursor: aunque
 *      Laravel esté caído, nada se pierde ni se re-lee de MySQL.
 *   3. Drena la cola hacia Nódico, firmando cada envío (HMAC-SHA256). Reintenta
 *      con espera creciente. Laravel deduplica por el id de origen.
 *   4. Si el id de Smart Pass **retrocede** (reinstalación/reset), se detiene y
 *      avisa: no re-procesa desde 1 ni salta eventos.
 *
 * Programa pequeño y aburrido: PHP puro, sin dependencias (PDO + cURL).
 */

declare(strict_types=1);

date_default_timezone_set('UTC');
error_reporting(E_ALL);

require __DIR__ . '/reconexion.php';   // motor de reconexión (lógica pura)

// ─── Configuración ───────────────────────────────────────────────────────────

$RAIZ = __DIR__;
$cfg = cargarEnv($RAIZ . DIRECTORY_SEPARATOR . '.env');

$SMARTPASS_TZ  = $cfg['SMARTPASS_TZ'] ?? 'America/Merida';
$NODICO_URL    = rtrim($cfg['NODICO_URL'] ?? '', '/');
$SECRETO       = $cfg['NODICO_SECRETO'] ?? '';
$SONDEO        = max(2, (int) ($cfg['SONDEO_SEGUNDOS'] ?? 5));
$LOTE          = max(1, (int) ($cfg['LOTE_MAXIMO'] ?? 200));
$SALUD_PUERTO  = (int) ($cfg['SALUD_PUERTO'] ?? 9099);
$HTTP_TIMEOUT  = max(5, (int) ($cfg['HTTP_TIMEOUT'] ?? 15));
$ESTADO_DIR    = $cfg['ESTADO_DIR'] ?? ($RAIZ . DIRECTORY_SEPARATOR . 'estado');
$TORNO_ID      = (int) ($cfg['TORNO_DEVICE_ID'] ?? 1);   // el FR07 en la BD de Smart Pass
$TORNO_TIMEOUT = max(90, (int) ($cfg['TORNO_TIMEOUT_SEG'] ?? 120));  // sin latido → caído

// Motor de reconexión automática (Fases 1-3). APAGADO por defecto: se enciende
// con RECONEXION_AUTO=1 en el .env cuando esté validado.
$RECON_AUTO      = filter_var($cfg['RECONEXION_AUTO'] ?? false, FILTER_VALIDATE_BOOLEAN);
$RECON_CONFIRMAR = max(10, (int) ($cfg['RECONEXION_CONFIRMAR_SEG'] ?? 60));  // sin latido → caído de verdad
$RECON_SILENCIO  = max(0, (int) ($cfg['RECONEXION_SILENCIO_SEG'] ?? 30));    // no reconfigurar si hubo paso reciente
$RECON_TOPE_HORA = max(1, (int) ($cfg['RECONEXION_TOPE_HORA'] ?? 6));        // máximo de intentos por hora
$RECON_ESCALONES = array_values(array_filter(array_map(
    'intval',
    explode(',', $cfg['RECONEXION_ESCALONES'] ?? '60,300,900,1800')
), fn ($n) => $n > 0)) ?: [60, 300, 900, 1800];
$OPER_HORA_INI   = (int) ($cfg['OPERACION_HORA_INICIO'] ?? 9);
$OPER_HORA_FIN   = (int) ($cfg['OPERACION_HORA_FIN'] ?? 19);

if ($NODICO_URL === '' || $SECRETO === '') {
    fwrite(STDERR, "Falta NODICO_URL o NODICO_SECRETO en el .env. Abortando.\n");
    exit(1);
}

@mkdir($ESTADO_DIR, 0700, true);
@mkdir($ESTADO_DIR . '/cola', 0700, true);
$LOG_DIR = $ESTADO_DIR . '/logs';
@mkdir($LOG_DIR, 0700, true);

// ─── Estado en memoria (para el endpoint de salud) ───────────────────────────

$SALUD = [
    'arranque'          => gmdate('c'),
    'ultimo_ciclo'      => null,
    'cursor'            => null,
    'cola_pendiente'    => 0,
    'ultimo_ok_nodico'  => null,
    'ultimo_error'      => null,
    'id_retrocedido'    => false,
    'detenido'          => false,
    'torno'             => null,   // estado del FR07: {online, antiguedad_seg, ultimo}
    'reconexion'        => null,   // resumen del día: {caidas_hoy, reconexiones_hoy, ...}
];

$backoffHasta = 0;   // epoch hasta el que no se reintenta el envío
$backoffNivel = 0;

// ─── Utilidades ──────────────────────────────────────────────────────────────

function cargarEnv(string $ruta): array
{
    $out = [];
    if (! is_file($ruta)) {
        fwrite(STDERR, "No existe el archivo de configuración: $ruta\n");
        exit(1);
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || ! str_contains($linea, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $linea, 2);
        $out[trim($k)] = trim($v);
    }
    return $out;
}

function bitacora(string $nivel, string $msg): void
{
    global $LOG_DIR;
    $linea = gmdate('c') . " [$nivel] $msg" . PHP_EOL;
    $archivo = $LOG_DIR . '/agente-' . gmdate('Y-m-d') . '.log';
    // Rotación simple por tamaño (5 MB): renombra y empieza de nuevo.
    if (is_file($archivo) && filesize($archivo) > 5 * 1024 * 1024) {
        @rename($archivo, $archivo . '.' . time());
    }
    file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
    // También a stdout, para nssm/consola.
    echo $linea;
}

function leerCursor(string $dir): int
{
    $f = $dir . '/cursor.json';
    if (is_file($f)) {
        $d = json_decode((string) file_get_contents($f), true);
        return (int) ($d['cursor'] ?? 0);
    }
    return 0;
}

function guardarCursor(string $dir, int $cursor): void
{
    file_put_contents($dir . '/cursor.json', json_encode(['cursor' => $cursor]), LOCK_EX);
}

function conectarSmartpass(array $cfg): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['SMARTPASS_DB_HOST'] ?? '127.0.0.1',
        $cfg['SMARTPASS_DB_PORT'] ?? '3307',
        $cfg['SMARTPASS_DB_NAME'] ?? 'tdx_face_owl',
    );
    return new PDO($dsn, $cfg['SMARTPASS_DB_USER'] ?? '', $cfg['SMARTPASS_DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
}

/**
 * Estado del torno leído de la BD de Smart Pass. La antigüedad se calcula con el
 * reloj de la propia BD (TIMESTAMPDIFF) para no depender de zonas horarias: el
 * torno guarda su hora local y el flag `is_online` a veces miente (queda en 1 sin
 * latido). Un torno «en línea de verdad» es el que latió hace poco.
 */
function leerEstadoTorno(PDO $pdo, int $deviceId, int $timeout): array
{
    $st = $pdo->prepare(
        'SELECT is_online, last_active_time,
                TIMESTAMPDIFF(SECOND, last_active_time, NOW()) AS antiguedad
         FROM tdx_device_base_info WHERE id = ?'
    );
    $st->execute([$deviceId]);
    $r = $st->fetch();
    if (! $r) {
        return ['existe' => false, 'online' => false, 'antiguedad_seg' => null, 'ultimo' => null];
    }

    // El reloj del torno puede ir adelantado del de la BD (antigüedad negativa):
    // eso es un latido recientísimo, no una caída. Se trata como 0.
    $ant = (int) $r['antiguedad'];
    $antReal = $ant < 0 ? 0 : $ant;
    $online = ((int) $r['is_online'] === 1) && ($antReal <= $timeout);

    return [
        'existe'         => true,
        'online'         => $online,
        'flag'           => (int) $r['is_online'],
        'antiguedad_seg' => $antReal,
        'ultimo'         => $r['last_active_time'],
    ];
}

/** Lee el estado del motor de reconexión (o arranca uno nuevo). */
function leerReconexion(string $dir): array
{
    $ruta = $dir . '/reconexion.json';
    if (is_file($ruta)) {
        $j = json_decode((string) file_get_contents($ruta), true);
        if (is_array($j)) {
            return $j;
        }
    }
    return reconexionInicial(date('Y-m-d'));
}

/** Guarda el estado del motor de reconexión. */
function guardarReconexion(string $dir, array $st): void
{
    @file_put_contents($dir . '/reconexion.json', json_encode($st, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** ¿Estamos en horario de operación (lun-vie, [ini,fin) en hora de Mérida)? */
function enHorarioMerida(string $tz, int $ini, int $fin): bool
{
    $ahora = new DateTime('now', new DateTimeZone($tz));
    $dia = (int) $ahora->format('N');   // 1=lunes ... 7=domingo
    $hora = (int) $ahora->format('G');
    return $dia >= 1 && $dia <= 5 && $hora >= $ini && $hora < $fin;
}

/** Antigüedad (s) del último paso registrado, según el reloj de la BD; null si no hay. */
function eventoRecienteSeg(PDO $pdo): ?int
{
    $v = $pdo->query(
        'SELECT TIMESTAMPDIFF(SECOND, MAX(create_time), NOW()) FROM tdx_pass_record'
    )->fetchColumn();
    return $v === null ? null : max(0, (int) $v);
}

/** Convierte un evento de Smart Pass al contrato de Nódico. */
function mapearEvento(array $r, DateTimeZone $tzOrigen): array
{
    // create_time es hora local (naive) de Mérida → se emite con offset explícito.
    $cuando = new DateTimeImmutable($r['create_time'], $tzOrigen);

    $personType = (int) $r['person_type'];

    return [
        'origen_id'    => (int) $r['id'],
        'ocurrido_en'  => $cuando->format('c'),          // ISO 8601 con offset -06:00
        'person_id'    => (int) $r['person_id'],
        'person_type'  => $personType,
        // person_type = -1 (o person_id = -1) es un «extraño»: se guarda, pero
        // Laravel jamás lo convierte en check-in ni consume día.
        'reconocido'   => $personType === 1 && (int) $r['person_id'] > 0,
        'pass_type'    => (string) $r['pass_type'],
        'sub_pass_type' => $r['sub_pass_type'],
        'direction'    => (int) $r['direction'],
        'device_id'    => (int) $r['device_id'],
        'device_key'   => (string) $r['device_key'],
    ];
}

/** Firma y envía un lote a Nódico. Devuelve true si Laravel lo aceptó (200). */
function enviarANodico(array $eventos): array
{
    global $NODICO_URL, $SECRETO, $HTTP_TIMEOUT;

    $cuerpo = json_encode(['eventos' => $eventos], JSON_UNESCAPED_UNICODE);
    $ts     = (string) time();
    $firma  = hash_hmac('sha256', $ts . '.' . $cuerpo, $SECRETO);

    $ch = curl_init($NODICO_URL . '/api/acceso/eventos');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $cuerpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Agente-Timestamp: ' . $ts,
            'X-Agente-Firma: ' . $firma,
        ],
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['ok' => $http === 200, 'http' => $http, 'error' => $err, 'resp' => $resp];
}

/** Avisa a Nódico de una anomalía del agente (id retrocedido, etc.). Best-effort. */
function alertarANodico(string $tipo, string $detalle): void
{
    global $NODICO_URL, $SECRETO, $HTTP_TIMEOUT;
    $cuerpo = json_encode(['tipo' => $tipo, 'detalle' => $detalle, 'cuando' => gmdate('c')]);
    $ts = (string) time();
    $ch = curl_init($NODICO_URL . '/api/acceso/alerta');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Agente-Timestamp: ' . $ts,
            'X-Agente-Firma: ' . hash_hmac('sha256', $ts . '.' . $cuerpo, $SECRETO),
        ],
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

/** Latido: le dice a Nódico «sigo vivo» aunque no haya eventos. Best-effort. */
function latidoANodico(): void
{
    global $NODICO_URL, $SECRETO, $HTTP_TIMEOUT, $SALUD;
    $cuerpo = json_encode(['visto' => gmdate('c'), 'torno' => $SALUD['torno'], 'reconexion' => $SALUD['reconexion']]);
    $ts = (string) time();
    $ch = curl_init($NODICO_URL . '/api/acceso/latido');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Agente-Timestamp: ' . $ts,
            'X-Agente-Firma: ' . hash_hmac('sha256', $ts . '.' . $cuerpo, $SECRETO),
        ],
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

/** Pregunta a Nódico por órdenes pendientes. Devuelve el arreglo de comandos. */
function sondearComandos(): array
{
    global $NODICO_URL, $SECRETO, $HTTP_TIMEOUT;
    $cuerpo = json_encode(['agente' => gmdate('c')]);
    $ts = (string) time();
    $ch = curl_init($NODICO_URL . '/api/acceso/comandos');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Agente-Timestamp: ' . $ts,
            'X-Agente-Firma: ' . hash_hmac('sha256', $ts . '.' . $cuerpo, $SECRETO),
        ],
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($http !== 200) {
        return [];
    }
    $j = json_decode((string) $resp, true);
    return is_array($j) && is_array($j['comandos'] ?? null) ? $j['comandos'] : [];
}

/** Reporta a Nódico si un comando se ejecutó o falló. Best-effort. */
function reportarComando(int $id, bool $ok, string $detalle, ?int $personId = null): void
{
    global $NODICO_URL, $SECRETO, $HTTP_TIMEOUT;
    $datos = ['ok' => $ok, 'detalle' => mb_substr($detalle, 0, 2000)];
    if ($personId !== null) {
        $datos['person_id'] = $personId;
    }
    $cuerpo = json_encode($datos);
    $ts = (string) time();
    $ch = curl_init($NODICO_URL . '/api/acceso/comandos/' . $id . '/resultado');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Agente-Timestamp: ' . $ts,
            'X-Agente-Firma: ' . hash_hmac('sha256', $ts . '.' . $cuerpo, $SECRETO),
        ],
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

/**
 * Ejecuta un comando localmente vía smartpass.php. Devuelve
 * [ok(bool), detalle(string), person_id(int|null)].
 */
function ejecutarComando(array $c): array
{
    global $RAIZ, $ESTADO_DIR;
    $php  = escapeshellarg(PHP_BINARY);
    $cli  = escapeshellarg($RAIZ . '/smartpass.php');

    switch ($c['tipo'] ?? '') {
        case 'abrir_puerta':
            $device = (int) ($c['device_id'] ?? 0);
            if ($device <= 0) {
                return [false, 'Sin dispositivo indicado.', null];
            }
            exec("$php $cli abrir $device 2>&1", $salida, $rc);
            $texto = trim(implode(' ', $salida));
            return [$rc === 0, $texto !== '' ? $texto : "código $rc", null];

        case 'enrolar_rostro':
            // El payload (con la foto en base64) va por archivo, no por argumento.
            $tmp = tempnam($ESTADO_DIR, 'enrol_');
            file_put_contents($tmp, json_encode($c['payload'] ?? [], JSON_UNESCAPED_UNICODE), LOCK_EX);
            exec("$php $cli enrolar " . escapeshellarg($tmp) . ' 2>&1', $salida, $rc);
            @unlink($tmp);
            $texto = trim(implode(' ', $salida));
            $personId = null;
            if (preg_match('/PERSONID:(\d+)/', $texto, $m)) {
                $personId = (int) $m[1];
                $texto = 'Rostro enrolado (persona ' . $personId . ').';
            }
            return [$rc === 0 && $personId !== null, $texto !== '' ? $texto : "código $rc", $personId];

        case 'borrar_rostro':
            $pid = (int) ($c['person_id'] ?? 0);
            if ($pid <= 0) {
                return [false, 'Sin person_id que borrar.', null];
            }
            exec("$php $cli borrar $pid 2>&1", $salida, $rc);
            return [$rc === 0, trim(implode(' ', $salida)) ?: "código $rc", null];

        case 'reconectar_torno':
            // Reescribe la misma contraseña LAN del torno → lo obliga a re-registrarse.
            $device = (int) ($c['device_id'] ?? 0);
            if ($device <= 0) {
                return [false, 'Sin dispositivo indicado.', null];
            }
            exec("$php $cli reconectar $device 2>&1", $salida, $rc);
            $texto = trim(implode(' ', $salida));
            return [$rc === 0, $texto !== '' ? $texto : "código $rc", null];

        default:
            return [false, 'Comando desconocido: ' . ($c['tipo'] ?? '?'), null];
    }
}

// ─── Endpoint de salud (solo 127.0.0.1) ──────────────────────────────────────

function abrirSalud(int $puerto)
{
    $srv = @stream_socket_server("tcp://127.0.0.1:$puerto", $errno, $errstr);
    if (! $srv) {
        bitacora('WARN', "No se pudo abrir el endpoint de salud en $puerto: $errstr");
        return null;
    }
    stream_set_blocking($srv, false);
    return $srv;
}

function atenderSalud($srv, float $espera): void
{
    global $SALUD;
    if (! $srv) {
        usleep((int) ($espera * 1_000_000));
        return;
    }
    $lectura = [$srv];
    $w = $e = null;
    if (@stream_select($lectura, $w, $e, 0, (int) ($espera * 1_000_000)) > 0) {
        $cli = @stream_socket_accept($srv, 0);
        if ($cli) {
            @fgets($cli); // se ignora la petición; cualquier GET devuelve el estado
            $json = json_encode($SALUD, JSON_PRETTY_PRINT);
            $sano = ! $SALUD['detenido'] && ! $SALUD['id_retrocedido'];
            $estado = $sano ? '200 OK' : '503 Service Unavailable';
            fwrite($cli, "HTTP/1.1 $estado\r\nContent-Type: application/json\r\n"
                . 'Content-Length: ' . strlen($json) . "\r\nConnection: close\r\n\r\n" . $json);
            fclose($cli);
        }
    }
}

// ─── Bucle principal ─────────────────────────────────────────────────────────

bitacora('INFO', 'Agente de acceso de Nódico iniciando.');
$tzOrigen = new DateTimeZone($SMARTPASS_TZ);
$salud = abrirSalud($SALUD_PUERTO);
$cursor = leerCursor($ESTADO_DIR);
$SALUD['cursor'] = $cursor;
bitacora('INFO', "Cursor inicial: $cursor. Sondeo cada {$SONDEO}s. Salud en 127.0.0.1:$SALUD_PUERTO.");

$ultimoSondeo = 0;
$ultimoLatido = 0;
$LATIDO_SEGUNDOS = 60;   // «sigo vivo» a Nódico, aunque no haya eventos

$estadoRecon = leerReconexion($ESTADO_DIR);   // motor de reconexión (persistido)
$SALUD['reconexion'] = reconexionResumen($estadoRecon);
if ($RECON_AUTO) {
    bitacora('INFO', 'Reconexión automática ENCENDIDA (tope ' . $RECON_TOPE_HORA . '/h, escalones ' . implode(',', $RECON_ESCALONES) . 's).');
} else {
    bitacora('INFO', 'Reconexión automática apagada: solo se detecta y cuenta. Enciéndela con RECONEXION_AUTO=1.');
}

while (true) {
    atenderSalud($salud, 0.5);

    // Latido periódico: Nódico marca al agente como caído si deja de llegar.
    if (time() - $ultimoLatido >= $LATIDO_SEGUNDOS) {
        latidoANodico();
        $ultimoLatido = time();
    }

    if (time() - $ultimoSondeo < $SONDEO) {
        continue;
    }
    $ultimoSondeo = time();
    $SALUD['ultimo_ciclo'] = gmdate('c');

    // 1 · Leer de Smart Pass y volcar a la cola durable.
    if (! $SALUD['detenido']) {
        try {
            $pdo = conectarSmartpass($cfg);

            // Estado del torno para el panel (se manda en el próximo latido).
            try {
                $SALUD['torno'] = leerEstadoTorno($pdo, $TORNO_ID, $TORNO_TIMEOUT);
            } catch (Throwable $e) {
                // Un fallo leyendo el estado no debe frenar la ingesta de eventos.
            }

            // Motor de reconexión (Fases 1-3): detecta la caída y —si está
            // encendido— la reconecta con freno. La decisión es pura y probada;
            // aquí solo se ejecutan los efectos.
            if (is_array($SALUD['torno'])) {
                try {
                    $dec = decidirReconexion($estadoRecon, [
                        'ahora'               => time(),
                        'hoy'                 => (new DateTime('now', new DateTimeZone($SMARTPASS_TZ)))->format('Y-m-d'),
                        'online'              => (bool) ($SALUD['torno']['online'] ?? false),
                        'smartpass_ok'        => (bool) ($SALUD['torno']['existe'] ?? true),
                        'evento_reciente_seg' => eventoRecienteSeg($pdo),
                        'en_horario'          => enHorarioMerida($SMARTPASS_TZ, $OPER_HORA_INI, $OPER_HORA_FIN),
                        'auto'                => $RECON_AUTO,
                        'confirmar_seg'       => $RECON_CONFIRMAR,
                        'silencio_seg'        => $RECON_SILENCIO,
                        'tope_hora'           => $RECON_TOPE_HORA,
                        'escalones'           => $RECON_ESCALONES,
                    ]);
                    $estadoRecon = $dec['estado'];
                    guardarReconexion($ESTADO_DIR, $estadoRecon);
                    $SALUD['reconexion'] = reconexionResumen($estadoRecon);

                    if (! empty($dec['evento'])) {
                        bitacora('INFO', 'Torno: ' . $dec['evento']);
                    }
                    if (! empty($dec['alerta'])) {
                        bitacora('ALERTA', $dec['alerta']);
                        alertarANodico('reconexion', $dec['alerta']);
                    }
                    if ($dec['accion'] === 'reconectar') {
                        $phpR = escapeshellarg(PHP_BINARY);
                        $cliR = escapeshellarg($RAIZ . '/smartpass.php');
                        $salidaR = [];
                        exec("$phpR $cliR reconectar " . (int) $TORNO_ID . ' 2>&1', $salidaR, $rcR);
                        $detR = trim(implode(' ', $salidaR));
                        bitacora($rcR === 0 ? 'INFO' : 'WARN', 'Torno: reconexión ' . ($rcR === 0 ? 'lanzada' : 'falló') . ' — ' . $detR);
                    }
                    if (! empty($dec['cambio'])) {
                        latidoANodico();       // el cambio de estado se avisa YA, sin esperar el latido periódico
                        $ultimoLatido = time();
                    }
                } catch (Throwable $e) {
                    bitacora('WARN', 'Motor de reconexión: ' . $e->getMessage());
                }
            }

            // Detección de id que retrocede: el MAX de origen no puede ser menor
            // que nuestro cursor. Si lo es, la BD se reinició → parar y avisar.
            $maxId = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM tdx_pass_record')->fetchColumn();
            if ($maxId < $cursor) {
                $SALUD['id_retrocedido'] = true;
                $SALUD['detenido'] = true;
                $msg = "El id de Smart Pass ($maxId) es menor que el cursor ($cursor): la base pudo reinstalarse. El agente se detiene para no re-procesar ni saltar eventos.";
                bitacora('ALERTA', $msg);
                alertarANodico('id_retrocedido', $msg);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, person_id, person_type, pass_type, sub_pass_type, direction,
                            device_id, device_key, create_time
                     FROM tdx_pass_record
                     WHERE id > :cursor AND deleted_flag = 0
                     ORDER BY id ASC LIMIT :lote'
                );
                $stmt->bindValue(':cursor', $cursor, PDO::PARAM_INT);
                $stmt->bindValue(':lote', $LOTE, PDO::PARAM_INT);
                $stmt->execute();
                $filas = $stmt->fetchAll();

                if ($filas) {
                    $eventos = array_map(fn ($r) => mapearEvento($r, $tzOrigen), $filas);
                    $maxLeido = (int) end($filas)['id'];
                    // A la cola durable ANTES de avanzar el cursor: si el proceso
                    // muere aquí, en el próximo arranque se re-leen desde el cursor.
                    $nombre = sprintf('%s/cola/%020d-%020d.json', $ESTADO_DIR, (int) $filas[0]['id'], $maxLeido);
                    file_put_contents($nombre, json_encode($eventos, JSON_UNESCAPED_UNICODE), LOCK_EX);
                    $cursor = $maxLeido;
                    guardarCursor($ESTADO_DIR, $cursor);
                    $SALUD['cursor'] = $cursor;
                    bitacora('INFO', count($eventos) . " evento(s) encolado(s). Cursor: $cursor.");
                }
            }
        } catch (Throwable $t) {
            $SALUD['ultimo_error'] = 'Smart Pass: ' . $t->getMessage();
            bitacora('ERROR', 'Leyendo Smart Pass: ' . $t->getMessage());
        }
    }

    // 2 · Drenar la cola hacia Nódico (con espera creciente si falla).
    if (time() >= $backoffHasta) {
        $pend = glob($ESTADO_DIR . '/cola/*.json') ?: [];
        sort($pend);
        $SALUD['cola_pendiente'] = count($pend);
        foreach ($pend as $archivo) {
            $eventos = json_decode((string) file_get_contents($archivo), true);
            if (! is_array($eventos)) { @unlink($archivo); continue; }

            $res = enviarANodico($eventos);
            if ($res['ok']) {
                @unlink($archivo);
                $backoffNivel = 0;
                $SALUD['ultimo_ok_nodico'] = gmdate('c');
                $SALUD['ultimo_error'] = null;
                $SALUD['cola_pendiente'] = max(0, $SALUD['cola_pendiente'] - 1);
                bitacora('INFO', 'Lote entregado a Nódico: ' . basename($archivo));
            } else {
                // Falló: espera creciente (5s, 10s, 20s… hasta 5 min) y se corta el
                // drenado de este ciclo. La cola queda intacta.
                $backoffNivel = min($backoffNivel + 1, 6);
                $backoffHasta = time() + min(300, 5 * (2 ** ($backoffNivel - 1)));
                $SALUD['ultimo_error'] = "Nódico HTTP {$res['http']} {$res['error']}";
                bitacora('WARN', "Envío falló ({$res['http']} {$res['error']}). Reintento en "
                    . ($backoffHasta - time()) . 's.');
                break;
            }
        }
    }

    // 3 · Recoger y ejecutar órdenes de Nódico (abrir puerta, etc.).
    foreach (sondearComandos() as $c) {
        $id = (int) ($c['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        [$ok, $detalle, $personId] = ejecutarComando($c);
        reportarComando($id, $ok, $detalle, $personId);
        bitacora($ok ? 'INFO' : 'WARN', "Comando #$id ({$c['tipo']}): " . ($ok ? 'OK' : 'FALLÓ') . " — $detalle");
    }
}
