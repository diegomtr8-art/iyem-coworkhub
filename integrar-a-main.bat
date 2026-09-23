@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

title Nodico - Integrar a main

set "REPO=C:\xampp\htdocs\coworkhub"
set "ORIGEN=feature/reconexion-terminal"

REM ============================================================
REM  Lleva a main todo el trabajo acumulado.
REM
REM  "feature/reconexion-terminal" contiene a TODAS las demas
REM  ramas (integracion-staging, tablero, control-acceso,
REM  pagos-referencia, responsive, fase4 y auth son ancestros
REM  suyos), y main no tiene ni un commit que ella no tenga.
REM
REM  Por eso esto es un AVANCE RAPIDO: no hay conflictos
REM  posibles y no se crea commit de fusion. Se usa --ff-only
REM  para que git se niegue si algo cambiara esa condicion.
REM ============================================================

echo.
echo ==========================================================
echo    INTEGRAR EL TRABAJO A main
echo ==========================================================
echo.

REM --- Comprobaciones ----------------------------------------
cd /d "%REPO%" 2>nul
if errorlevel 1 (
    echo [ERROR] No encuentro la carpeta del proyecto: %REPO%
    goto :fin
)

where git >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Git no esta instalado o no esta en el PATH.
    goto :fin
)

git rev-parse --is-inside-work-tree >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Esa carpeta no es un repositorio de git.
    goto :fin
)

REM Guardar la rama actual para volver al final
for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set "RAMA_ORIGINAL=%%b"

REM --- El arbol tiene que estar limpio ------------------------
set SUCIO=0
for /f %%n in ('git status --porcelain 2^>nul ^| find /c /v ""') do set SUCIO=%%n

if not "%SUCIO%"=="0" (
    echo [ALTO] Hay %SUCIO% archivos con cambios sin confirmar.
    echo.
    git status --short
    echo.
    echo Cambiar de rama con cambios pendientes puede perderlos.
    echo Confirmalos o guardalos con "git stash" antes de seguir.
    goto :fin
)

echo Carpeta        : %CD%
echo Rama actual    : %RAMA_ORIGINAL%
echo Se integrara   : %ORIGEN%  ->  main
echo.

REM --- Confirmar que de verdad es avance rapido ---------------
set ATRAS=0
for /f %%n in ('git rev-list --count %ORIGEN%..main 2^>nul') do set ATRAS=%%n

set ADELANTE=0
for /f %%n in ('git rev-list --count main..%ORIGEN% 2^>nul') do set ADELANTE=%%n

echo --- Comprobacion previa ----------------------------------
echo    main recibira        : %ADELANTE% commits
echo    main tiene aparte    : %ATRAS% commits

if not "%ATRAS%"=="0" (
    echo.
    echo [ALTO] main tiene %ATRAS% commits que %ORIGEN% no tiene.
    echo Ya NO es un avance rapido y hace falta una fusion real,
    echo que puede traer conflictos. Para aqui y avisa a Claude.
    goto :fin
)

if "%ADELANTE%"=="0" (
    echo.
    echo main ya esta al dia. No hay nada que integrar.
    goto :fin
)

echo.
echo --- Lo que llegara a main (los 10 mas recientes) ---------
git log --oneline main..%ORIGEN% -10
echo.

echo ==========================================================
echo  Es un avance rapido: sin conflictos, sin commit de fusion.
echo  Si algo no cuadrara, git se niega en vez de improvisar.
echo ==========================================================
echo.
echo Pulsa una tecla para continuar, o cierra la ventana para cancelar.
pause >nul

REM --- La integracion ----------------------------------------
echo.
echo --- Cambiando a main -------------------------------------
git checkout main
if errorlevel 1 goto :fallo

echo.
echo --- Integrando -------------------------------------------
git merge --ff-only %ORIGEN%
if errorlevel 1 goto :fallo_merge

echo.
echo --- Subiendo main a GitHub -------------------------------
git push origin main
if errorlevel 1 goto :fallo

REM --- Volver donde estabas ----------------------------------
echo.
echo --- Regresando a %RAMA_ORIGINAL% -------------------------
git checkout "%RAMA_ORIGINAL%"

REM --- Verificacion ------------------------------------------
echo.
echo --- Verificacion -----------------------------------------
for /f "delims=" %%c in ('git rev-parse --short main') do echo    main local  : %%c
for /f "delims=" %%c in ('git rev-parse --short origin/main') do echo    main remoto : %%c
echo.

echo ==========================================================
echo    LISTO. main esta al dia y subido a GitHub.
echo ==========================================================
echo.
echo Siguiente paso hacia produccion:
echo   1. Decidir con que nombre se publica (raiz, www o subdominio)
echo   2. Crear la base de datos y el .env de produccion
echo   3. Mover el document root en hPanel a public_html/public/
echo   4. Que juridico valide el aviso de privacidad y los terminos
goto :fin

:fallo_merge
echo.
echo ==========================================================
echo    LA INTEGRACION NO SE PUDO HACER COMO AVANCE RAPIDO
echo ==========================================================
echo.
echo git se nego, que es exactamente lo que queriamos que hiciera.
echo Significa que main y %ORIGEN% divergieron.
echo.
echo NO fuerces nada. Regresando a tu rama y avisa a Claude.
git checkout "%RAMA_ORIGINAL%"
goto :fin

:fallo
echo.
echo ==========================================================
echo    ALGO FALLO
echo ==========================================================
echo.
echo Lee el mensaje de arriba. Si fue al subir, puede ser
echo autenticacion de GitHub (hace falta token, no contrasena).
echo.
echo Intentando regresar a %RAMA_ORIGINAL%...
git checkout "%RAMA_ORIGINAL%"

:fin
echo.
echo Pulsa una tecla para cerrar esta ventana.
pause >nul
endlocal
