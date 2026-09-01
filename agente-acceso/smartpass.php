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
        'token'  => (string) ($j['data']['token'] ?? ''),
        'siteId' => (string) ($j['data']['siteId'] ?? ($j['data']['site_id'] ?? '')),
        'data'   => $j['data'] ?? [],
    ];
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

    default:
        echo <<<AYUDA
        Cliente Smart Pass del agente de Nódico.

          php smartpass.php login          comprueba la sesión (usuario/contraseña del .env)
          php smartpass.php dispositivos   lista los device_id de la instalación
          php smartpass.php probar         login + dispositivos, SIN tocar la puerta
          php smartpass.php abrir <id>     ABRE la puerta del dispositivo <id> (efecto físico)

        Requiere en el .env: SMARTPASS_USER, SMARTPASS_PASS (del panel de Smart Pass),
        y las credenciales de la BD (SMARTPASS_DB_*). Nada de eso va al repositorio.

        AYUDA;
        break;
}

@unlink($COOKIE);
