@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

title Nodico - Respaldo a GitHub

set "REPO=C:\xampp\htdocs\coworkhub"

REM ============================================================
REM  Rama excluida a proposito.
REM
REM  "respaldo-antes-de-limpiar" contiene el commit 3f06f9d
REM  (Baseline snapshot), y dentro va el archivo de Illustrator
REM  "nodico/PAGINA WEB NODICO/Pagina Web - Nodico V2.ai", de
REM  233 MB. GitHub no acepta archivos de mas de 100 MB, y
REM  rechaza el ENVIO COMPLETO si una sola rama lo trae.
REM
REM  Ninguna otra rama lo contiene, asi que dejandola fuera
REM  se sube todo lo demas sin tocar el historial.
REM ============================================================
set "EXCLUIR=respaldo-antes-de-limpiar"

echo.
echo ==========================================================
echo    RESPALDO DEL PROYECTO NODICO A GITHUB
echo ==========================================================
echo.

REM --- Comprobaciones antes de tocar nada ---------------------
cd /d "%REPO%" 2>nul
if errorlevel 1 (
    echo [ERROR] No encuentro la carpeta del proyecto:
    echo         %REPO%
    goto :fin
)

where git >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Git no esta instalado o no esta en el PATH de Windows.
    goto :fin
)

git rev-parse --is-inside-work-tree >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Esa carpeta existe pero no es un repositorio de git.
    goto :fin
)

git remote get-url origin >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Este repositorio no tiene configurado el remoto "origin".
    goto :fin
)

echo Carpeta   : %CD%
for /f "delims=" %%r in ('git remote get-url origin') do echo Remoto    : %%r
for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do echo Rama      : %%b
echo.

REM --- Armar la lista de ramas, saltando la excluida -----------
set "REFS="
set /a CUANTAS=0
for /f "delims=" %%b in ('git for-each-ref --format^="%%(refname:short)" refs/heads') do (
    if /i not "%%b"=="%EXCLUIR%" (
        set "REFS=!REFS! %%b"
        set /a CUANTAS+=1
    )
)

if "!REFS!"=="" (
    echo [ERROR] No hay ramas que subir.
    goto :fin
)

echo --- Se van a subir !CUANTAS! ramas ------------------------
for %%b in (!REFS!) do echo    %%b
echo.

git show-ref --verify --quiet "refs/heads/%EXCLUIR%"
if not errorlevel 1 (
    echo --- Queda fuera a proposito ------------------------------
    echo    %EXCLUIR%
    echo.
    echo    Contiene un archivo de Illustrator de 233 MB y GitHub
    echo    no acepta archivos de mas de 100 MB. Esa rama se queda
    echo    solo en esta computadora. Ver la nota al final.
    echo.
)

echo --- Commits que todavia no estan en GitHub ---------------
set PENDIENTES=0
for /f %%n in ('git log --oneline --branches --not --remotes 2^>nul ^| find /c /v ""') do set PENDIENTES=%%n
echo %PENDIENTES% commits sin subir.
echo.

echo ==========================================================
echo  Esto NO puede borrar ni sobrescribir nada en GitHub:
echo  el script nunca usa --force.
echo ==========================================================
echo.
echo Pulsa una tecla para continuar, o cierra la ventana para cancelar.
pause >nul

REM --- La subida ---------------------------------------------
echo.
echo --- Subiendo ramas ---------------------------------------
git push origin !REFS!
if errorlevel 1 goto :fallo

echo.
echo --- Subiendo etiquetas -----------------------------------
git push origin --tags
if errorlevel 1 (
    echo    [aviso] Las etiquetas no se subieron, pero las ramas si.
)

REM --- Verificacion ------------------------------------------
echo.
echo --- Ramas que ya estan en GitHub -------------------------
git branch -r
echo.

echo ==========================================================
echo    LISTO. El proyecto esta respaldado en GitHub.
echo ==========================================================
echo.
echo Nota sobre "%EXCLUIR%":
echo.
echo  Esa rama guarda una foto del proyecto de antes del
echo  rediseno, y los archivos de diseno originales.
echo  Los archivos de diseno NO deben vivir en git: muevelos
echo  a Drive o Dropbox, tal como dice el propio .gitignore.
echo.
echo  La carpeta a mover es:  %REPO%\nodico\
goto :fin

:fallo
echo.
echo ==========================================================
echo    LA SUBIDA NO SE COMPLETO
echo ==========================================================
echo.
echo Causas mas comunes:
echo.
echo  - Pidio usuario y contrasena: GitHub ya no acepta la
echo    contrasena de la cuenta. Hace falta un token personal
echo    o iniciar sesion con Git Credential Manager.
echo.
echo  - Sin conexion a internet.
echo.
echo  - Aparece "exceeds GitHub's file size limit": hay otra
echo    rama con un archivo de mas de 100 MB. Anota cual dice
echo    el mensaje y avisame para excluirla tambien.
echo.
echo  - "non-fast-forward": el remoto tiene commits que tu no
echo    tienes. Haz "git pull" antes. NO uses --force.
echo.
echo Lee el mensaje de error de arriba: casi siempre lo dice.

:fin
echo.
echo Pulsa una tecla para cerrar esta ventana.
pause >nul
endlocal
