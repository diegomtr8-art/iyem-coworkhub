<?php

/**
 * Cliente de Smart Pass para el agente de Nódico — Fases 3 y 4.
 *
 * Habla con la API web local de Smart Pass (Spring Boot, 127.0.0.1:9000) para lo
 * que la lectura de la base no cubre: **iniciar sesión**, **abrir la puerta** y
 * (más adelante) **enrolar un rostro**. Es una herramienta de línea de comandos,
 * separada del daemon a propósito: abrir una puerta tiene efecto físico y no
 * debe esconderse dentro de un bucle; se dispara con un comando explícito.
 *
 * El login replica exactamente lo que hace la SPA: cifra
 *   JSON.stringify({pStr: "<contraseña>"})
 * con 3DES (CBC/Pkcs7), clave y IV fijos que vienen incrustados en el front, y
 * lo manda como campo `desStr` junto a `identity` (el usuario). La respuesta
 * trae `data.token`, que se reenvía como cabecera `Owl-Auth-Token`.
 *
 * NADA de credenciales vive aquí: el usuario y la contraseña del panel de Smart
 * Pass van en el .env del agente (SMARTPASS_USER / SMARTPASS_PASS), nunca al repo.
 *
 * Uso:
 *   php smartpass.php login          → comprueba que la sesión funciona
 *   php smartpass.php dispositivos   → lista los device_id/clave (de la BD)
 *   php smartpass.php probar         → login + dispositivos (sin tocar la puerta)
 *   php smartpass.php abrir <id>     → ABRE la puerta del dispositivo <id> (físico)
 */

declare(strict_types=1);

error_reporting(E_ALL);
date_default_timezone_set('UTC');

// ─── Configuración ───────────────────────────────────────────────────────────

$RAIZ = __DIR__;
$cfg  = cargarEnv($RAIZ . DIRECTORY_SEPARATOR . '.env');

$SP_URL = rtrim($cfg['SMARTPASS_URL'] ?? 'http://127.0.0.1:9000', '/');
$SP_USER = $cfg['SMARTPASS_USER'] ?? '';
$SP_PASS = $cfg['SMARTPASS_PASS'] ?? '';
// Clave/IV 3DES: incrustados en la SPA (app.js). Se dejan configurables por si en
// otra versión del firmware cambian, pero estos son los valores de esta instalación.
$SP_KEY = $cfg['SMARTPASS_3DES_KEY'] ?? 'WfJTKO9S4eLkrPz2JKrAnzdb';
$SP_IV  = $cfg['SMARTPASS_3DES_IV'] ?? 'D076D35C';
$TIMEOUT = max(5, (int) ($cfg['HTTP_TIMEOUT'] ?? 15));

$COOKIE = tempnam(sys_get_temp_dir(), 'sp_ck_');

// Smart Pass registra el User-Agent de cada petición (LogResponseBodyAdvice →
// UserAgentUtils.getBrowserInfo) y revienta con NullPointerException si no lo
// reconoce. Por eso nos presentamos como un navegador real.
const SP_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// ─── Utilidades ──────────────────────────────────────────────────────────────

function cargarEnv(string $ruta): array
{
    if (! is_file($ruta)) {
        fwrite(STDERR, "No existe el archivo de configuración: $ruta\n");
        exit(1);
    }
    $out = [];
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

/** Cifra como la SPA: 3DES-CBC-Pkcs7, salida base64. */
function des3(string $plano, string $clave, string $iv): string
{
    $raw = openssl_encrypt($plano, 'des-ede3-cbc', $clave, OPENSSL_RAW_DATA, $iv);
    if ($raw === false) {
        fwrite(STDERR, "No se pudo cifrar (¿openssl con 3DES?).\n");
        exit(1);
    }
    return base64_encode($raw);
}

/**
 * Inicia sesión en Smart Pass. Devuelve el token (`Owl-Auth-Token`) y el siteId.
 * Aborta con un mensaje claro si las credenciales no sirven.
 */
function iniciarSesion(): array
{
    global $SP_URL, $SP_USER, $SP_PASS, $SP_KEY, $SP_IV, $TIMEOUT, $COOKIE;

    if ($SP_USER === '' || $SP_PASS === '') {
        fwrite(STDERR, "Falta SMARTPASS_USER o SMARTPASS_PASS en el .env.\n");
        exit(1);
    }

    // La contraseña viaja cifrada, envuelta en {"pStr": "..."} como hace la SPA.
    $desStr = des3(json_encode(['pStr' => $SP_PASS], JSON_UNESCAPED_UNICODE), $SP_KEY, $SP_IV);

    $campos = http_build_query([
        'identity'  => $SP_USER,
        'desStr'    => $desStr,
        'captcha'   => '',
        'sessionId' => '',
    ]);

    $ch = curl_init($SP_URL . '/admin/login');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $campos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $TIMEOUT,
        CURLOPT_COOKIEJAR      => $COOKIE,
        CURLOPT_COOKIEFILE     => $COOKIE,
        CURLOPT_USERAGENT      => SP_USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        fwrite(STDERR, "No se pudo contactar Smart Pass en $SP_URL: $err\n");
        exit(1);
    }

    $j = json_decode((string) $resp, true);
    if (! is_array($j)) {
        fwrite(STDERR, "Respuesta de login no reconocida: " . substr((string) $resp, 0, 300) . "\n");
        exit(1);
    }

    if (($j['code'] ?? null) !== 200) {
        $msg = $j['message'] ?? 'sin mensaje';
        if (! empty($j['data']['nextNeedCaptcha'])) {
            fwrite(STDERR, "Smart Pass pide captcha (demasiados intentos). Espera unos minutos o entra una vez por el navegador.\n");
        }
        fwrite(STDERR, "Login rechazado (code {$j['code']}): {$msg}\n");
        exit(1);
    }

    return [
        // El token viene como data['Owl-Auth-Token'] (no data.token); se reenvía
        // en la cabecera Owl-Auth-Token en las llamadas que crean/borran.
        'token'  => (string) ($j['data']['Owl-Auth-Token'] ?? $j['data']['token'] ?? ''),
        'siteId' => (string) ($j['data']['siteId'] ?? ($j['data']['site_id'] ?? '')),
        'data'   => $j['data'] ?? [],
    ];
}

/** Llamada JSON autenticada a Smart Pass. Devuelve [http, json|null, raw]. */
function llamarSp(array $sesion, string $metodo, string $ruta, array $cuerpo): array
{
    global $SP_URL, $TIMEOUT, $COOKIE;
    $ch = curl_init($SP_URL . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max($TIMEOUT, 40),
        CURLOPT_COOKIEJAR      => $COOKIE,
        CURLOPT_COOKIEFILE     => $COOKIE,
        CURLOPT_USERAGENT      => SP_USER_AGENT,
        CURLOPT_HTTPHEADER     => cabecerasAuth($sesion),
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $j = json_decode((string) $resp, true);
    return [$http, is_array($j) ? $j : null, (string) $resp];
}

/** Sube una foto en base64. Devuelve ['id'=>.., 'url'=>..] o lanza. */
function subirFoto(array $sesion, string $base64): array
{
    [$http, $j] = llamarSp($sesion, 'POST', '/admin/person/base64_photo/upload', ['base64' => $base64]);
    if (($j['code'] ?? null) !== 200 || empty($j['data']['id'])) {
        throw new RuntimeException('No se pudo subir la foto: ' . ($j['message'] ?? "http $http"));
    }
    return ['id' => (int) $j['data']['id'], 'url' => (string) ($j['data']['url'] ?? $j['data']['fileUrl'] ?? '')];
}

/**
 * Crea la persona en Smart Pass. `$foto` es ['id'=>.., 'url'=>..] o null.
 * Devuelve el person_id (tdx_person.id).
 */
function crearPersona(array $sesion, string $nombre, string $personNo, ?array $foto): int
{
    $cuerpo = [
        'name'             => $nombre,
        'personNo'         => $personNo,
        'gender'           => 1,
        'attendanceFlag'   => false,
        'temperatureAlarm' => false,
        'vaccination'      => '-1',
        'groupId'          => '',
    ];
    if ($foto) {
        $cuerpo['personPhotoId1']  = $foto['id'];
        $cuerpo['personPhotoUrl1'] = $foto['url'];
    }
    [$http, $j] = llamarSp($sesion, 'POST', '/admin/person/employees', $cuerpo);

    $id = (int) ($j['data']['id'] ?? 0);
    if ($id > 0) {
        return $id;
    }

    // Con un rostro real, Smart Pass a veces responde «Operación exitosa» SIN el
    // id en el cuerpo (y si la persona ya existía, tampoco lo devuelve). Se
    // resuelve leyendo el person_id por su person_no en la base.
    $id = personIdPorNo($personNo);
    if ($id > 0) {
        return $id;
    }

    throw new RuntimeException('No se pudo crear la persona: ' . ($j['message'] ?? "http $http"));
}

/** Busca el person_id (tdx_person.id) más reciente para un person_no. 0 si no hay. */
function personIdPorNo(string $personNo): int
{
    global $cfg;
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['SMARTPASS_DB_HOST'] ?? '127.0.0.1', $cfg['SMARTPASS_DB_PORT'] ?? '3307',
            $cfg['SMARTPASS_DB_NAME'] ?? 'tdx_face_owl');
        $pdo = new PDO($dsn, $cfg['SMARTPASS_DB_USER'] ?? '', $cfg['SMARTPASS_DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $st = $pdo->prepare('SELECT id FROM tdx_person WHERE person_no = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$personNo]);
        return (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Pide al terminal que tome la foto de una persona (modo FR07). Best-effort. */
/** URL del recurso de la foto (más reciente) de una persona, leída de la BD. '' si no hay. */
function fotoUrlDePersona(int $personId): string
{
    global $cfg;
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['SMARTPASS_DB_HOST'] ?? '127.0.0.1', $cfg['SMARTPASS_DB_PORT'] ?? '3307',
            $cfg['SMARTPASS_DB_NAME'] ?? 'tdx_face_owl');
        $pdo = new PDO($dsn, $cfg['SMARTPASS_DB_USER'] ?? '', $cfg['SMARTPASS_DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $st = $pdo->prepare(
            'SELECT r.resource_url FROM tdx_person_photo p
             JOIN tdx_resource_data r ON r.id = p.resource_id
             WHERE p.person_id = ? AND p.deleted_flag = 0
             ORDER BY p.id DESC LIMIT 1'
        );
        $st->execute([$personId]);
        return (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return '';
    }
}

/** Descarga una imagen de Smart Pass (recurso local) y la devuelve como data URL base64. */
function imagenBase64(string $url): string
{
    global $SP_URL, $COOKIE;
    $ch = curl_init($SP_URL . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $COOKIE,
        CURLOPT_COOKIEJAR      => $COOKIE,
        CURLOPT_USERAGENT      => SP_USER_AGENT,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $bytes = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ct    = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
    curl_close($ch);
    if ($http !== 200 || $bytes === false || $bytes === '') {
        return '';
    }
    return 'data:' . $ct . ';base64,' . base64_encode((string) $bytes);
}

function tomarFoto(array $sesion, int $personId, int $deviceId): void
{
    llamarSp($sesion, 'POST', '/admin/person/employees/take_photo', ['ids' => [$personId], 'deviceIds' => [$deviceId]]);
}

/** Borra una persona de Smart Pass. */
function borrarPersona(array $sesion, int $personId): void
{
    llamarSp($sesion, 'DELETE', '/admin/person/employees', ['ids' => [$personId]]);
}

/** Cabeceras autenticadas para las llamadas posteriores al login. */
function cabecerasAuth(array $sesion, string $contentType = 'application/json'): array
{
    $h = ["Content-Type: {$contentType}", 'Accept: application/json'];
    if ($sesion['token'] !== '') {
        $h[] = 'Owl-Auth-Token: ' . $sesion['token'];
    }
    if ($sesion['siteId'] !== '') {
        $h[] = 'siteId: ' . $sesion['siteId'];
    }
    return $h;
}

/** Abre la puerta de uno o varios dispositivos. PUT /admin/devices/remote/opendoor {ids:[...]}. */
function abrirPuerta(array $sesion, array $ids): array
{
    global $SP_URL, $TIMEOUT, $COOKIE;

    $cuerpo = json_encode(['ids' => array_values(array_map('intval', $ids))]);
    $ch = curl_init($SP_URL . '/admin/devices/remote/opendoor');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $cuerpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $TIMEOUT,
        CURLOPT_COOKIEJAR      => $COOKIE,
        CURLOPT_COOKIEFILE     => $COOKIE,
        CURLOPT_USERAGENT      => SP_USER_AGENT,
        CURLOPT_HTTPHEADER     => cabecerasAuth($sesion),
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $j = json_decode((string) $resp, true);
    return ['http' => $http, 'json' => is_array($j) ? $j : null, 'raw' => (string) $resp];
}

/** Lista los dispositivos vistos en los eventos (de la BD, sin tocar la API). */
function dispositivosDesdeBD(array $cfg): array
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['SMARTPASS_DB_HOST'] ?? '127.0.0.1',
        $cfg['SMARTPASS_DB_PORT'] ?? '3307',
        $cfg['SMARTPASS_DB_NAME'] ?? 'tdx_face_owl',
    );
    try {
        $pdo = new PDO($dsn, $cfg['SMARTPASS_DB_USER'] ?? '', $cfg['SMARTPASS_DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, "No se pudo leer la BD de Smart Pass: {$e->getMessage()}\n");
        return [];
    }
    $sql = 'SELECT device_id, device_key, COUNT(*) AS eventos, MAX(create_time) AS ultimo
            FROM tdx_pass_record GROUP BY device_id, device_key ORDER BY ultimo DESC';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// ─── Línea de comandos ───────────────────────────────────────────────────────

$comando = $argv[1] ?? 'ayuda';

switch ($comando) {
    case 'login':
        $s = iniciarSesion();
        $tk = $s['token'] !== '' ? substr($s['token'], 0, 8) . '…' : '(sin token en el cuerpo; sesión por cookie)';
        echo "✔ Sesión iniciada en Smart Pass como «{$GLOBALS['SP_USER']}».\n";
        echo "  token: {$tk}   siteId: " . ($s['siteId'] !== '' ? $s['siteId'] : '(ninguno)') . "\n";
        break;

    case 'dispositivos':
        $ds = dispositivosDesdeBD($cfg);
        if (! $ds) {
            echo "No hay dispositivos en tdx_pass_record (o no se pudo leer la BD).\n";
            break;
        }
        echo "Dispositivos vistos en los eventos:\n";
        foreach ($ds as $d) {
            printf("  id=%-4s clave=%-20s eventos=%-6s último=%s\n",
                $d['device_id'], $d['device_key'], $d['eventos'], $d['ultimo']);
        }
        echo "\nPara abrir uno:  php smartpass.php abrir <id>\n";
        break;

    case 'probar':
        $s = iniciarSesion();
        echo "✔ Login OK. Ahora los dispositivos:\n\n";
        $ds = dispositivosDesdeBD($cfg);
        foreach ($ds as $d) {
            printf("  id=%-4s clave=%-20s eventos=%-6s último=%s\n",
                $d['device_id'], $d['device_key'], $d['eventos'], $d['ultimo']);
        }
        echo "\nTodo listo. La puerta NO se tocó. Para abrirla: php smartpass.php abrir <id>\n";
        break;

    case 'abrir':
        $id = $argv[2] ?? '';
        if ($id === '' || ! ctype_digit((string) $id)) {
            fwrite(STDERR, "Uso: php smartpass.php abrir <deviceId>   (un número; míralos con «dispositivos»)\n");
            exit(1);
        }
        echo "Abriendo la puerta del dispositivo {$id}…\n";
        $s = iniciarSesion();
        $r = abrirPuerta($s, [(int) $id]);
        $code = $r['json']['code'] ?? $r['http'];
        if (($r['json']['code'] ?? null) === 200) {
            echo "✔ Smart Pass aceptó la orden (code 200). La puerta debió abrir.\n";
        } else {
            $msg = $r['json']['message'] ?? '(sin mensaje)';
            echo "✖ Smart Pass respondió code {$code}: {$msg}\n";
            echo "  crudo: " . substr($r['raw'], 0, 300) . "\n";
            @unlink($COOKIE);
            exit(2);   // que quien lo invoque (el agente) sepa que no abrió
        }
        break;

    case 'enrolar':
        // Lee un JSON {modo, nombre, person_no, foto_base64?, device_id?} de un
        // archivo (el agente lo escribe, porque el base64 no cabe en un argumento).
        $archivo = $argv[2] ?? '';
        if ($archivo === '' || ! is_file($archivo)) {
            fwrite(STDERR, "Uso: php smartpass.php enrolar <archivo.json>\n");
            exit(1);
        }
        $d = json_decode((string) file_get_contents($archivo), true);
        if (! is_array($d) || empty($d['nombre']) || empty($d['person_no'])) {
            fwrite(STDERR, "El JSON de enrolado necesita al menos nombre y person_no.\n");
            exit(1);
        }
        try {
            $s = iniciarSesion();
            $foto = null;
            if (($d['modo'] ?? 'foto') === 'foto') {
                if (empty($d['foto_base64'])) {
                    throw new RuntimeException('Modo foto sin foto_base64.');
                }
                $foto = subirFoto($s, (string) $d['foto_base64']);
            }
            $personId = crearPersona($s, (string) $d['nombre'], (string) $d['person_no'], $foto);
            if (($d['modo'] ?? '') === 'dispositivo' && ! empty($d['device_id'])) {
                tomarFoto($s, $personId, (int) $d['device_id']);
            }
            echo "PERSONID:{$personId}\n";
        } catch (Throwable $e) {
            fwrite(STDERR, '✖ ' . $e->getMessage() . "\n");
            @unlink($COOKIE);
            exit(2);
        }
        break;

    case 'borrar':
        $id = $argv[2] ?? '';
        if (! ctype_digit((string) $id)) {
            fwrite(STDERR, "Uso: php smartpass.php borrar <person_id>\n");
            exit(1);
        }
        borrarPersona(iniciarSesion(), (int) $id);
        echo "Persona {$id} borrada.\n";
        break;

    case 'crearpersona':
        // Crea la persona SIN foto (enrolado por FR07: la foto la captura el torno
        // con «tomarfoto»). Uso: crearpersona <nombre> <person_no>
        $nombre = $argv[2] ?? '';
        $pno    = $argv[3] ?? '';
        if ($nombre === '' || $pno === '') {
            fwrite(STDERR, "Uso: php smartpass.php crearpersona <nombre> <person_no>
");
            exit(1);
        }
        $pid = crearPersona(iniciarSesion(), $nombre, $pno, null);
        echo "PERSONID:{$pid}
";
        break;

    case 'tomarfoto':
        // Ordena al torno capturar el rostro de la persona AHORA (debe estar frente
        // al lector). Uso: tomarfoto <person_id> <device_id>
        $pid = $argv[2] ?? '';
        $dev = $argv[3] ?? '';
        if (! ctype_digit((string) $pid) || ! ctype_digit((string) $dev)) {
            fwrite(STDERR, "Uso: php smartpass.php tomarfoto <person_id> <device_id>
");
            exit(1);
        }
        [$http, $j, $raw] = llamarSp(iniciarSesion(), 'POST', '/admin/person/employees/take_photo', ['ids' => [(int) $pid], 'deviceIds' => [(int) $dev]]);
        echo "HTTP {$http} · code=" . ($j['code'] ?? '?') . " · msg=" . ($j['message'] ?? '') . "
";
        echo "RESP: " . mb_substr($raw, 0, 300) . "
";
        break;

    case 'reconectar':
        // Fuerza el re-registro del torno reescribiendo SU MISMA contraseña LAN: es
        // el truco del «Grabar» de Dispositivo→Detalle→Red, sin cambiar nada. Se
        // escribe en el servidor, así que reconecta aunque el equipo esté caído.
        // Uso: reconectar <device_id>
        $dev = $argv[2] ?? '';
        if (! ctype_digit((string) $dev)) {
            fwrite(STDERR, "Uso: php smartpass.php reconectar <device_id>\n");
            exit(1);
        }
        $s = iniciarSesion();
        [$h1, $j1] = llamarSp($s, 'GET', '/admin/devices/network/' . (int) $dev, []);
        $lan = $j1['data']['lanPwd'] ?? null;
        if ($lan === null || $lan === '') {
            fwrite(STDERR, "No se pudo leer la contraseña LAN actual (http {$h1}).\n");
            exit(2);
        }
        [$h2, $j2] = llamarSp($s, 'PUT', '/admin/devices/network/set_password', [
            'oldPwd'   => $lan,
            'lanPwd'   => $lan,
            'deviceId' => (int) $dev,
        ]);
        echo 'HTTP ' . $h2 . ' · code=' . ($j2['code'] ?? '?') . ' · msg=' . ($j2['message'] ?? '') . "\n";
        if ((int) ($j2['code'] ?? 0) === 200) {
            echo "RECONECTADO: re-registro forzado (se reescribió la misma contraseña LAN).\n";
        } else {
            exit(2);
        }
        break;

    case 'capturarfoto':
        // Ordena la captura al torno, baja la foto a Smart Pass y la devuelve en
        // base64 (para la vista previa en Nódico). La persona debe estar de frente.
        // Uso: capturarfoto <person_id> <device_id>
        $pid = $argv[2] ?? '';
        $dev = $argv[3] ?? '';
        if (! ctype_digit((string) $pid) || ! ctype_digit((string) $dev)) {
            fwrite(STDERR, "Uso: php smartpass.php capturarfoto <person_id> <device_id>\n");
            exit(1);
        }
        $s = iniciarSesion();
        // 1) Ordenar la captura en el terminal.
        llamarSp($s, 'POST', '/admin/person/employees/take_photo', ['ids' => [(int) $pid], 'deviceIds' => [(int) $dev]]);
        // 2) Esperar a que la foto llegue: se baja del dispositivo y se busca en la BD.
        $url = '';
        for ($i = 0; $i < 12; $i++) {
            usleep(1_500_000);
            llamarSp($s, 'POST', '/admin/person/employees/download_from_device', ['ids' => [(int) $pid]]);
            $url = fotoUrlDePersona((int) $pid);
            if ($url !== '') {
                break;
            }
        }
        if ($url === '') {
            fwrite(STDERR, "No se recibió la foto del torno (¿la persona estaba de frente?).\n");
            exit(2);
        }
        $b64 = imagenBase64($url);
        if ($b64 === '') {
            fwrite(STDERR, "Se capturó pero no se pudo leer la imagen.\n");
            exit(2);
        }
        echo 'FOTO:' . $b64 . "\n";
        break;

    default:
        echo <<<AYUDA
        Cliente Smart Pass del agente de Nódico.

          php smartpass.php login          comprueba la sesión (usuario/contraseña del .env)
          php smartpass.php dispositivos   lista los device_id de la instalación
          php smartpass.php probar         login + dispositivos, SIN tocar la puerta
          php smartpass.php abrir <id>     ABRE la puerta del dispositivo <id> (efecto físico)
          php smartpass.php enrolar <json> crea la persona con su rostro (devuelve PERSONID:n)
          php smartpass.php borrar <id>    borra a la persona <person_id> de Smart Pass

        Requiere en el .env: SMARTPASS_USER, SMARTPASS_PASS (del panel de Smart Pass),
        y las credenciales de la BD (SMARTPASS_DB_*). Nada de eso va al repositorio.

        AYUDA;
        break;
}

@unlink($COOKIE);
