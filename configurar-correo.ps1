# ============================================================================
#  Nodico - Configurar el correo saliente (Gmail)
#
#  Pide la contrasena de aplicacion SIN mostrarla en pantalla, la escribe en
#  .env, limpia la cache de configuracion y manda un correo de prueba.
#
#  No se lanza directo: usa configurar-correo.bat
# ============================================================================

$ErrorActionPreference = 'Stop'
$repo    = Split-Path -Parent $MyInvocation.MyCommand.Path
$envFile = Join-Path $repo '.env'
$cuenta  = 'Diegomtr8@gmail.com'

function Titulo($t) {
    Write-Host ''
    Write-Host ('=' * 62) -ForegroundColor DarkGray
    Write-Host "   $t" -ForegroundColor Yellow
    Write-Host ('=' * 62) -ForegroundColor DarkGray
    Write-Host ''
}

Titulo 'CONFIGURAR EL CORREO DE NODICO'

# --- Comprobaciones ---------------------------------------------------------
if (-not (Test-Path $envFile)) {
    Write-Host "[ERROR] No encuentro el archivo .env en:" -ForegroundColor Red
    Write-Host "        $repo"
    Write-Host ''
    Write-Host 'Si es una instalacion nueva, copia .env.example a .env primero.'
    exit 1
}

# Localizar PHP (XAMPP no siempre esta en el PATH)
$php = $null
$cmd = Get-Command php -ErrorAction SilentlyContinue
if ($cmd) { $php = $cmd.Source }
elseif (Test-Path 'C:\xampp\php\php.exe') { $php = 'C:\xampp\php\php.exe' }

if (-not $php) {
    Write-Host '[ERROR] No encuentro PHP.' -ForegroundColor Red
    Write-Host 'Buscado en el PATH y en C:\xampp\php\php.exe'
    exit 1
}

Write-Host "Proyecto : $repo"
Write-Host "PHP      : $php"
Write-Host "Cuenta   : $cuenta"

# --- Explicacion ------------------------------------------------------------
Write-Host ''
Write-Host '--- Lo que necesitas antes de seguir ----------------------' -ForegroundColor Cyan
Write-Host ''
Write-Host ' Gmail no acepta la contrasena normal de la cuenta desde 2022.'
Write-Host ' Hace falta una CONTRASENA DE APLICACION de 16 caracteres.'
Write-Host ''
Write-Host ' Como se obtiene:'
Write-Host ''
Write-Host "   1. Entra a Gmail con  $cuenta"
Write-Host '   2. Activa la verificacion en dos pasos si no la tienes:'
Write-Host '        https://myaccount.google.com/signinoptions/twosv'
Write-Host '      (sin esto, el paso 3 sencillamente no aparece)'
Write-Host '   3. Ve a:'
Write-Host '        https://myaccount.google.com/apppasswords'
Write-Host '   4. Ponle un nombre, por ejemplo "Nodico", y crea.'
Write-Host '   5. Google muestra 16 letras en 4 grupos. Copialas.'
Write-Host ''
Write-Host ' Los espacios NO son parte de la contrasena: este script los quita.' -ForegroundColor DarkGray
Write-Host ' Google solo la ensena UNA VEZ. Si la pierdes, creas otra.' -ForegroundColor DarkGray
Write-Host ''

# --- Pedir la contrasena ----------------------------------------------------
Write-Host '--- Pega la contrasena de aplicacion ----------------------' -ForegroundColor Cyan
Write-Host 'No se vera mientras la escribes. Eso es normal.' -ForegroundColor DarkGray
Write-Host ''
$segura = Read-Host '  Contrasena de aplicacion' -AsSecureString

$bstr  = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
$clave = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)

$clave = ($clave -replace '\s', '')

if ([string]::IsNullOrWhiteSpace($clave)) {
    Write-Host ''
    Write-Host '[CANCELADO] No escribiste nada. No se toco el archivo .env.' -ForegroundColor Yellow
    exit 1
}

if ($clave.Length -ne 16) {
    Write-Host ''
    Write-Host "[ALTO] Esa contrasena tiene $($clave.Length) caracteres, no 16." -ForegroundColor Red
    Write-Host ''
    Write-Host ' Las contrasenas de aplicacion de Google son siempre de 16 letras.'
    Write-Host ' Si pegaste la contrasena normal de tu cuenta, no va a funcionar:'
    Write-Host ' Google la rechaza y el registro de Nodico se queda sin correos.'
    Write-Host ''
    Write-Host ' No se toco el archivo .env. Vuelve a ejecutarlo cuando la tengas.'
    exit 1
}

# --- Respaldo y escritura ---------------------------------------------------
$respaldo = "$envFile.respaldo"
Copy-Item $envFile $respaldo -Force
Write-Host ''
Write-Host "Respaldo del .env anterior: $respaldo" -ForegroundColor DarkGray

# OJO: este archivo se mantiene en ASCII puro a proposito.
# Windows PowerShell 5.1 lee los scripts sin BOM como Windows-1252, asi que una
# "o" con acento aqui dentro se convertiria en mojibake al escribirla en el .env.
# Por eso MAIL_FROM_NAME no se toca: ya esta bien acentuado en el .env
# y reescribirlo desde aqui solo podria estropearlo.
#
# MAIL_SCHEME tampoco se toca: con el puerto 587 debe quedar vacio para que
# Symfony negocie STARTTLS. (MAIL_ENCRYPTION ya no existe en Laravel 12.)
$ajustes = [ordered]@{
    'MAIL_MAILER'            = 'smtp'
    'MAIL_HOST'              = 'smtp.gmail.com'
    'MAIL_PORT'              = '587'
    'MAIL_USERNAME'          = $cuenta
    'MAIL_PASSWORD'          = $clave
    'MAIL_FROM_ADDRESS'      = "`"$cuenta`""
    'NODICO_CONTACTO_EMAIL'  = $cuenta
}

# -Encoding UTF8 al LEER: sin esto, PS 5.1 interpreta el .env como ANSI y
# vuelve a escribir los acentos de los comentarios doblemente codificados,
# empeorandolos un poco mas en cada ejecucion.
$lineas = Get-Content $envFile -Encoding UTF8

foreach ($llave in $ajustes.Keys) {
    $valor  = $ajustes[$llave]
    $patron = "^\s*#?\s*$llave\s*="
    if ($lineas | Where-Object { $_ -match $patron }) {
        $lineas = $lineas | ForEach-Object {
            if ($_ -match $patron) { "$llave=$valor" } else { $_ }
        }
    } else {
        $lineas += "$llave=$valor"
    }
}

# UTF-8 SIN BOM a proposito. Windows PowerShell 5.1 pone BOM con
# "Set-Content -Encoding UTF8", y esos tres bytes invisibles al principio del
# .env rompen la primera variable del archivo: Laravel deja de leer APP_NAME
# y el error que sale despues no se parece en nada a la causa.
$sinBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($envFile, $lineas, $sinBom)
Write-Host 'Archivo .env actualizado.' -ForegroundColor Green

# --- Limpiar cache ----------------------------------------------------------
Write-Host ''
Write-Host '--- Limpiando la cache de configuracion -------------------' -ForegroundColor Cyan
Push-Location $repo
& $php artisan config:clear
& $php artisan cache:clear
Pop-Location

# --- Correo de prueba -------------------------------------------------------
Write-Host ''
Write-Host '--- Mandando un correo de prueba --------------------------' -ForegroundColor Cyan
Write-Host ''

Push-Location $repo
& $php artisan nodico:probar-correo $cuenta
$exito = ($LASTEXITCODE -eq 0)
Pop-Location

if ($exito) {
    Titulo 'LISTO - EL CORREO FUNCIONA'
    Write-Host " Revisa la bandeja de $cuenta." -ForegroundColor Green
    Write-Host ' Si no esta, mira en Spam: el primer correo suele caer ahi.'
    Write-Host ''
    Write-Host ' Esto configuro TU COMPUTADORA (XAMPP).' -ForegroundColor Yellow
    Write-Host ' El sitio de pruebas prueba.nodico.com.mx tiene su PROPIO .env'
    Write-Host ' en el hosting, y ahi hay que poner lo mismo a mano.'
    Write-Host ''
    Write-Host ' Bloque para pegar en el .env de Hostinger:' -ForegroundColor Cyan
    Write-Host ''
    Write-Host '   MAIL_MAILER=smtp'
    Write-Host '   MAIL_HOST=smtp.gmail.com'
    Write-Host '   MAIL_PORT=587'
    Write-Host '   MAIL_SCHEME=null'
    Write-Host "   MAIL_USERNAME=$cuenta"
    Write-Host '   MAIL_PASSWORD=<la misma de 16 letras>'
    Write-Host "   MAIL_FROM_ADDRESS=`"$cuenta`""
    Write-Host "   NODICO_CONTACTO_EMAIL=$cuenta"
    Write-Host ''
    Write-Host ' Y despues, en el hosting:'
    Write-Host '   php artisan config:clear' -ForegroundColor Cyan
    Write-Host '   php artisan nodico:probar-correo' -ForegroundColor Cyan
} else {
    Titulo 'NO SE PUDO ENVIAR'
    Write-Host ' El comando de arriba ya dice que significa el error' -ForegroundColor Yellow
    Write-Host ' y que hacer. Corrigelo y vuelve a ejecutar este archivo.'
    Write-Host ''
    Write-Host ' Para reintentar solo el envio, sin volver a pedir la clave:'
    Write-Host '    php artisan nodico:probar-correo' -ForegroundColor Cyan
    Write-Host ''
    Write-Host " Tu .env anterior quedo guardado en: $respaldo" -ForegroundColor DarkGray
}

Write-Host ''
