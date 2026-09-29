@echo off
chcp 65001 >nul
title Nodico - Configurar correo

REM ============================================================
REM  Deja a Nodico mandando correos por Gmail.
REM
REM  Pide la contrasena de aplicacion de Google sin mostrarla en
REM  pantalla, la guarda en .env, limpia la cache y manda un
REM  correo de prueba para comprobar que de verdad sale.
REM
REM  La contrasena NUNCA se escribe en esta ventana ni queda en
REM  el historial de comandos.
REM ============================================================

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0configurar-correo.ps1"

echo.
echo Pulsa una tecla para cerrar esta ventana.
pause >nul
