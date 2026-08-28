#!/usr/bin/env python3
"""
Despliegue de CoworkHub a prueba.nodico.com.mx (Hostinger, hosting compartido).

    1. npm run build
    2. python deploy_prueba.py

El servidor NO tiene Node, así que los assets se compilan en local y se suben ya
construidos. El layout del servidor es plano: `public_html/` es el document root
y contiene la aplicación completa (app/, vendor/, .env, index.php propio).

Los assets de build viven en dos rutas y ambas deben quedar sincronizadas:
  - public_html/build/         -> lo que se sirve por HTTP (/build/assets/...)
  - public_html/public/build/  -> lo que lee public_path('build/manifest.json')
"""

import os
import posixpath
import stat
import sys

import paramiko

SSH_HOST = "195.35.38.222"
SSH_PORT = 65002
SSH_USER = "u489236361"
KEY_PATH = os.path.expanduser(r"~\.ssh\id_deploy")

LOCAL_ROOT = os.path.dirname(os.path.abspath(__file__))
REMOTE_ROOT = f"/home/{SSH_USER}/domains/prueba.nodico.com.mx/public_html"

# Código de la aplicación: origen local -> destino remoto.
DIRS_APP = [
    ("app", "app"),
    ("bootstrap", "bootstrap"),
    ("config", "config"),
    ("database", "database"),
    ("routes", "routes"),
    ("resources", "resources"),
]

# Assets estáticos. Ojo: en este servidor van a la RAÍZ de public_html,
# no dentro de public_html/public/.
DIRS_ASSETS = [
    ("public/build", "build"),
    ("public/build", "public/build"),
    ("public/img", "img"),
    ("public/fonts", "fonts"),
    ("public/icons", "icons"),
]

FILES = ["composer.json", "composer.lock", "package.json", "tailwind.config.js"]

EXCLUDE_DIRS = {
    "node_modules", ".git", "__pycache__", "vendor",
    "logs", "sessions", "views", "cache",
}
EXCLUDE_EXT = {".log", ".map"}


def conectar() -> paramiko.SSHClient:
    if not os.path.exists(KEY_PATH):
        sys.exit(f"ERROR: no se encontró la llave SSH en {KEY_PATH}")
    cliente = paramiko.SSHClient()
    cliente.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cliente.connect(SSH_HOST, port=SSH_PORT, username=SSH_USER,
                    key_filename=KEY_PATH, timeout=40)
    return cliente


def correr(cliente, cmd, mostrar=True):
    _, stdout, stderr = cliente.exec_command(cmd)
    salida = stdout.read().decode(errors="replace").strip()
    error = stderr.read().decode(errors="replace").strip()
    codigo = stdout.channel.recv_exit_status()
    if mostrar:
        if salida:
            print("    " + salida.replace("\n", "\n    "))
        if error:
            print("    [err] " + error.replace("\n", "\n    "))
    return codigo, salida, error


def mkdir_p(sftp, ruta):
    faltantes = []
    actual = ruta
    while True:
        try:
            sftp.stat(actual)
            break
        except FileNotFoundError:
            faltantes.append(actual)
            actual = posixpath.dirname(actual)
    for d in reversed(faltantes):
        sftp.mkdir(d)


def omitir(rel):
    partes = rel.replace("\\", "/").split("/")
    if any(p in EXCLUDE_DIRS for p in partes):
        return True
    return os.path.splitext(rel)[1] in EXCLUDE_EXT


def subir_dir(sftp, local_dir, remote_dir, etiqueta):
    if not os.path.isdir(local_dir):
        print(f"    (omitido, no existe: {local_dir})")
        return 0

    subidos = 0
    for dirpath, dirnames, filenames in os.walk(local_dir):
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIRS]
        rel_dir = os.path.relpath(dirpath, local_dir)

        if rel_dir == ".":
            destino = remote_dir
        else:
            if omitir(rel_dir):
                continue
            destino = remote_dir + "/" + rel_dir.replace("\\", "/")

        try:
            sftp.stat(destino)
        except FileNotFoundError:
            mkdir_p(sftp, destino)

        for nombre in filenames:
            rel = os.path.join(rel_dir, nombre) if rel_dir != "." else nombre
            if omitir(rel):
                continue
            try:
                sftp.put(os.path.join(dirpath, nombre), destino + "/" + nombre)
                subidos += 1
                if subidos % 100 == 0:
                    print(f"    {etiqueta}: {subidos} archivos...")
            except Exception as exc:
                print(f"    AVISO: no se pudo subir {rel}: {exc}")

    print(f"    {etiqueta}: {subidos} archivos.")
    return subidos


def main():
    if not os.path.isdir(os.path.join(LOCAL_ROOT, "public", "build")):
        sys.exit("ERROR: falta public/build. Corre `npm run build` primero.")

    print("\n=== Deploy CoworkHub -> prueba.nodico.com.mx ===\n")
    cliente = conectar()
    sftp = cliente.open_sftp()
    total = 0

    print("--> Código de la aplicación")
    for local, remoto in DIRS_APP:
        total += subir_dir(sftp, os.path.join(LOCAL_ROOT, local),
                           f"{REMOTE_ROOT}/{remoto}", local)

    print("\n--> Archivos sueltos")
    for f in FILES:
        origen = os.path.join(LOCAL_ROOT, f)
        if os.path.exists(origen):
            sftp.put(origen, f"{REMOTE_ROOT}/{f}")
            total += 1
            print(f"    {f}")

    print("\n--> Assets compilados y estáticos")
    for local, remoto in DIRS_ASSETS:
        total += subir_dir(sftp, os.path.join(LOCAL_ROOT, local),
                           f"{REMOTE_ROOT}/{remoto}", remoto)

    sftp.close()
    print(f"\n--> {total} archivos subidos. Ejecutando pasos de servidor...\n")

    with open(os.path.join(LOCAL_ROOT, "deploy.sh"), encoding="utf-8") as fh:
        script = fh.read()

    codigo, _, _ = correr(cliente, f"bash -s <<'FIN_DEPLOY'\n{script}\nFIN_DEPLOY")
    cliente.close()

    if codigo != 0:
        sys.exit(f"\nERROR: los pasos de servidor terminaron con código {codigo}")
    print("\n=== Deploy completado ===")


if __name__ == "__main__":
    main()
