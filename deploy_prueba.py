#!/usr/bin/env python3
"""
Despliegue de CoworkHub a Hostinger (hosting compartido).

    1. npm run build
    2. python deploy_prueba.py                              -> prueba.nodico.com.mx
       python deploy_prueba.py --dominio X --si-produccion  -> cualquier otro

El destino por defecto es el de prueba a proposito: este script sube codigo
encima de lo que haya en el document root, sin respaldo previo. Apuntarlo a un
sitio con miembros reales tiene que costar una bandera extra y una lectura.

El servidor NO tiene Node, así que los assets se compilan en local y se suben ya
construidos. El layout del servidor es plano: `public_html/` es el document root
y contiene la aplicación completa (app/, vendor/, .env, index.php propio).

Los assets de build viven en dos rutas y ambas deben quedar sincronizadas:
  - public_html/build/         -> lo que se sirve por HTTP (/build/assets/...)
  - public_html/public/build/  -> lo que lee public_path('build/manifest.json')
"""

import argparse
import datetime
import hashlib
import json
import os
import posixpath
import stat
import subprocess
import sys
import urllib.error
import urllib.request

import paramiko

SSH_HOST = "195.35.38.222"
SSH_PORT = 65002
SSH_USER = "u489236361"
KEY_PATH = os.path.expanduser(r"~\.ssh\id_deploy")

LOCAL_ROOT = os.path.dirname(os.path.abspath(__file__))

# Destino por defecto. `--dominio` lo cambia; ver la guarda en main(). Se deja
# como global porque media docena de funciones la leen, y pasarla por parametro
# a todas solo para esto habria tocado mas codigo del que arregla.
DOMINIO_PRUEBA = "prueba.nodico.com.mx"
REMOTE_ROOT = f"/home/{SSH_USER}/domains/{DOMINIO_PRUEBA}/public_html"

# Código de la aplicación: origen local -> destino remoto.
DIRS_APP = [
    ("app", "app"),
    ("bootstrap", "bootstrap"),
    ("config", "config"),
    ("database", "database"),
    ("routes", "routes"),
    ("resources", "resources"),
    # Las traducciones al espanol viven en la raiz del proyecto, no dentro de
    # `resources/`. Sin esta linea, el servidor se queda con los textos de
    # Laravel y cada error de acceso sale en ingles.
    ("lang", "lang"),
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

# Sólo lo que el servidor necesita de verdad. `package.json` y
# `tailwind.config.js` no hacen falta (este host no tiene Node) y se servían
# tal cual por HTTP, asi que dejan de subirse y `deploy.sh` los borra.
FILES = ["composer.json", "composer.lock"]

# Carpetas de aplicacion que quedan dentro del document root y que Apache
# serviria tal cual. Se les deja un .htaccess que niega todo.
DIRS_A_BLINDAR = [
    "app", "bootstrap", "config", "database", "lang", "resources", "routes",
    "storage", "vendor", "tests", "tools", "deploy",
]

# Sueltos de public/ que van a la RAIZ del document root. Se subian las
# carpetas (build, img, fonts, icons) pero no estos, asi que los favicons y el
# manifiesto generados en IMG-06 nunca llegaron al servidor: comprobado el
# 2026-08-29, /favicon-32.png, /favicon-96.png, /apple-touch-icon.png y
# /site.webmanifest daban 404 mientras existian en local.
#
# `index.php` y `.htaccess` NO se tocan: este host tiene los suyos, adaptados
# al layout plano, y sobreescribirlos tumba el sitio.
PUBLIC_SUELTOS_OMITIR = {"index.php", ".htaccess"}

# Rutas que NO deben responder 200 despues de desplegar.
COMPROBAR_CERRADAS = [
    "/storage/logs/laravel.log",
    "/composer.json",
    "/composer.lock",
    "/vendor/composer/installed.json",
    "/app/Http/Controllers/WelcomeController.php",
    "/config/nodico.php",
    "/database/seeders/NodicoWebSeeder.php",
    "/lang/es/auth.php",
    "/package.json",
]

# Rutas que SI deben responder 200 despues de desplegar.
COMPROBAR_ABIERTAS = [
    "/",
    "/nosotros",
    "/membresias",
    "/eventos",
    "/actividades",
    "/aviso-de-privacidad",
    "/terminos",
    "/robots.txt",
    "/sitemap.xml",
    "/build/manifest.json",
    "/favicon-32.png",
    "/apple-touch-icon.png",
    "/site.webmanifest",
]

# Ojo: sólo nombres que no puedan colisionar con directorios legítimos de la app.
# 'views' o 'cache' aquí excluirían resources/views/ y bootstrap/cache/.
# storage/ no se sube nunca, así que no hace falta filtrar sus subcarpetas.
EXCLUDE_DIRS = {"node_modules", ".git", "__pycache__", "vendor"}
EXCLUDE_EXT = {".log", ".map"}

# Manifiestos compilados de bootstrap/cache. Se REGENERAN en el servidor con
# `composer install --no-dev` + `package:discover`; subirlos desde local mete
# los paquetes de **dev** (p. ej. Pail) que en el servidor no existen, y el
# sitio arranca con «Class Laravel\Pail\PailServiceProvider not found» → 500.
# Comprobado el 2026-08-30. Estos nombres solo aparecen en bootstrap/cache/.
EXCLUDE_FILES = {
    "packages.php", "services.php", "compiled.php",
    "config.php", "routes-v7.php", "events.scanned.php",
}


def conectar() -> paramiko.SSHClient:
    """
    Con `AutoAddPolicy` se aceptaba en silencio cualquier llave de host, en
    cada ejecución. La llave del host es lo único que autentica al servidor:
    sin comprobarla, quien se interponga en la red recibe el código completo
    de la aplicación y la secuencia de despliegue.

    Ahora se confía en `~/.ssh/known_hosts`. La primera vez hay que apuntar el
    servidor a mano, con la huella verificada en hPanel:

        ssh-keyscan -p 65002 195.35.38.222 >> ~/.ssh/known_hosts
    """
    if not os.path.exists(KEY_PATH):
        sys.exit(f"ERROR: no se encontró la llave SSH en {KEY_PATH}")

    cliente = paramiko.SSHClient()
    cliente.load_system_host_keys()
    cliente.set_missing_host_key_policy(paramiko.RejectPolicy())

    try:
        cliente.connect(SSH_HOST, port=SSH_PORT, username=SSH_USER,
                        key_filename=KEY_PATH, timeout=40)
    except paramiko.SSHException as exc:
        sys.exit(
            f"ERROR: no se pudo verificar la llave del host ({exc}).\n"
            f"Si es la primera vez desde esta máquina, comprueba la huella en\n"
            f"hPanel y luego:\n"
            f"    ssh-keyscan -p {SSH_PORT} {SSH_HOST} >> ~/.ssh/known_hosts"
        )

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
    if partes[-1] in EXCLUDE_FILES:
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


def git(*args) -> str:
    """Salida de un comando git, sin ruido."""
    return subprocess.run(
        ["git", *args], cwd=LOCAL_ROOT, capture_output=True, text=True, check=True
    ).stdout.strip()


def estado_del_repo(permitir_sucio: bool) -> dict:
    """
    OPS-01 — desplegar con el arbol sucio deja el servidor en un estado que no
    corresponde a ningun commit, y despues no hay forma de saber que se publico
    ni de reproducirlo.

    `--sucio` existe para una urgencia, pero no lo esconde: el sello lleva la
    marca y la lista de archivos sin commitear, y se ve en /build/version.json.
    """
    try:
        sucio = git("status", "--porcelain")
        commit = git("rev-parse", "HEAD")
        rama = git("rev-parse", "--abbrev-ref", "HEAD")
    except (subprocess.CalledProcessError, FileNotFoundError) as exc:
        sys.exit(f"ERROR: no se pudo leer el estado de git ({exc}).")

    if sucio and not permitir_sucio:
        archivos = "\n    ".join(sucio.splitlines()[:20])
        sys.exit(
            "ERROR: el arbol de git esta sucio. Commitea o descarta antes de "
            "desplegar,\nporque si no lo que quede en el servidor no "
            "correspondera a ningun commit:\n\n    "
            + archivos
            + "\n\nSi es una urgencia: python deploy_prueba.py --sucio"
        )

    return {
        "commit": commit,
        "commit_corto": commit[:12],
        "rama": rama,
        "sucio": bool(sucio),
        "sin_commitear": sucio.splitlines() if sucio else [],
    }


def sellar_version(repo: dict) -> dict:
    """
    OPS-01 — escribe el sello dentro del build, para poder preguntarle al
    servidor que esta publicado en vez de suponerlo:

        curl -s https://prueba.nodico.com.mx/build/version.json
    """
    sello = dict(repo)
    sello["desplegado"] = datetime.datetime.now().astimezone().isoformat(timespec="seconds")

    destino = os.path.join(LOCAL_ROOT, "public", "build", "version.json")
    with open(destino, "w", encoding="utf-8") as fh:
        json.dump(sello, fh, ensure_ascii=False, indent=2)

    marca = sello["commit_corto"] + (" (SUCIO)" if sello["sucio"] else "")
    print(f"    {repo['rama']} @ {marca}")
    return sello


def md5_local(ruta: str) -> str:
    with open(ruta, "rb") as fh:
        return hashlib.md5(fh.read()).hexdigest()


def pedir(url: str, binario=False):
    """Devuelve (estado, cuerpo). No lanza en 4xx/5xx."""
    try:
        with urllib.request.urlopen(url, timeout=25) as resp:
            datos = resp.read()
            return resp.status, datos if binario else datos.decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, None
    except Exception:
        return None, None


def comprobar_manifiestos(cliente, sello: dict) -> bool:
    """
    OPS-02 — este host guarda los assets en dos sitios: `public_html/build/`
    es lo que se sirve por HTTP y `public_html/public/build/` es lo que lee
    `public_path('build/manifest.json')`. Si se desincronizan, Laravel apunta a
    archivos que no existen y el sitio se queda sin estilos sin dar ningun
    error. Ademas de compararlos, se pide un asset de verdad.
    """
    print("\n--> Comprobando los dos manifiestos y un asset publicado")
    todo_bien = True

    local = os.path.join(LOCAL_ROOT, "public", "build", "manifest.json")
    esperado = md5_local(local)
    print(f"    local                       {esperado}")

    _, salida, _ = correr(
        cliente,
        f"md5sum {REMOTE_ROOT}/build/manifest.json {REMOTE_ROOT}/public/build/manifest.json",
        mostrar=False,
    )

    for linea in salida.splitlines():
        suma, _, ruta = linea.partition(" ")
        etiqueta = ruta.strip().replace(REMOTE_ROOT + "/", "")
        if suma == esperado:
            print(f"    ok  {etiqueta:<26} {suma}")
        else:
            print(f"    MAL {etiqueta:<26} {suma}")
            todo_bien = False

    if len(salida.splitlines()) != 2:
        print("    MAL falta alguno de los dos manifiestos en el servidor")
        todo_bien = False

    # El manifiesto puede estar bien y el asset no haberse subido.
    with open(local, encoding="utf-8") as fh:
        manifiesto = json.load(fh)
    archivo = manifiesto["resources/js/app.js"]["file"]

    estado, cuerpo = pedir(f"https://prueba.nodico.com.mx/build/{archivo}", binario=True)
    if estado == 200 and cuerpo:
        print(f"    ok  /build/{archivo} -> 200, {len(cuerpo) // 1024} KB")
    else:
        print(f"    MAL /build/{archivo} -> {estado}")
        todo_bien = False

    # Y el sello debe ser el de este despliegue, no el de uno anterior.
    estado, cuerpo = pedir("https://prueba.nodico.com.mx/build/version.json")
    if estado == 200 and cuerpo:
        publicado = json.loads(cuerpo)
        if publicado.get("commit") == sello["commit"]:
            print(f"    ok  /build/version.json -> {publicado['commit_corto']}")
        else:
            print(
                f"    MAL /build/version.json -> {publicado.get('commit_corto')}, "
                f"se esperaba {sello['commit_corto']}"
            )
            todo_bien = False
    else:
        print(f"    MAL /build/version.json -> {estado}")
        todo_bien = False

    return todo_bien


def main():
    parser = argparse.ArgumentParser(description="Despliegue de CoworkHub a Hostinger")
    parser.add_argument(
        "--sucio",
        action="store_true",
        help="desplegar con cambios sin commitear (queda marcado en version.json)",
    )
    parser.add_argument(
        "--dominio",
        default=DOMINIO_PRUEBA,
        help=f"dominio destino en Hostinger (por defecto {DOMINIO_PRUEBA})",
    )
    parser.add_argument(
        "--si-produccion",
        action="store_true",
        help="confirma un destino distinto del de prueba",
    )
    args = parser.parse_args()

    global REMOTE_ROOT
    REMOTE_ROOT = f"/home/{SSH_USER}/domains/{args.dominio}/public_html"

    if args.dominio != DOMINIO_PRUEBA and not args.si_produccion:
        print(f"ERROR: --dominio {args.dominio} no es el destino de prueba.")
        print("Este script sobrescribe el document root sin respaldo previo.")
        print("Si de verdad es lo que quieres, repite con --si-produccion.")
        print("Antes: respalda la base de datos del destino y comprueba")
        print("`php artisan nodico:sembrar-libro-horas --simular`.")
        sys.exit(1)

    if not os.path.isdir(os.path.join(LOCAL_ROOT, "public", "build")):
        sys.exit("ERROR: falta public/build. Corre `npm run build` primero.")

    print("")
    print(f"=== Deploy CoworkHub -> {args.dominio} ===")
    print("")

    print("--> Estado del repositorio")
    sello = sellar_version(estado_del_repo(args.sucio))

    cliente = conectar()

    # Sin `.env` Laravel no arranca, y subir el codigo primero deja el sitio
    # roto hasta que alguien lo cree a mano. Se comprueba antes de tocar nada.
    codigo_env, _, _ = correr(cliente, f"test -f {REMOTE_ROOT}/.env")
    if codigo_env != 0:
        cliente.close()
        print(f"ERROR: {args.dominio} no tiene .env en el document root.")
        print("El destino no esta configurado todavia: hacen falta la base de")
        print("datos, el .env y `composer install` antes del primer despliegue.")
        print("Ver «Primer despliegue a un destino nuevo» en docs/DEPLOY.md.")
        sys.exit(1)

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

    print("\n--> Sueltos de public/ a la raíz")
    local_public = os.path.join(LOCAL_ROOT, "public")
    for nombre in sorted(os.listdir(local_public)):
        if nombre in PUBLIC_SUELTOS_OMITIR:
            continue
        origen = os.path.join(local_public, nombre)
        if not os.path.isfile(origen):
            continue
        sftp.put(origen, f"{REMOTE_ROOT}/{nombre}")
        total += 1
        print(f"    {nombre}")

    print("\n--> Blindando las carpetas de aplicación")
    blindaje = os.path.join(LOCAL_ROOT, "deploy", "htaccess-negar-todo")
    for carpeta in DIRS_A_BLINDAR:
        destino = f"{REMOTE_ROOT}/{carpeta}"
        try:
            sftp.stat(destino)
        except FileNotFoundError:
            continue
        sftp.put(blindaje, f"{destino}/.htaccess")
        total += 1
        print(f"    {carpeta}/.htaccess")

    sftp.close()
    print(f"\n--> {total} archivos subidos. Ejecutando pasos de servidor...\n")

    with open(os.path.join(LOCAL_ROOT, "deploy.sh"), encoding="utf-8") as fh:
        script = fh.read()

    codigo, _, _ = correr(cliente, f"bash -s <<'FIN_DEPLOY'\n{script}\nFIN_DEPLOY")

    if codigo != 0:
        sys.exit(f"\nERROR: los pasos de servidor terminaron con código {codigo}")

    problemas = []

    if not comprobar_cierre():
        problemas.append("hay archivos internos accesibles por HTTP")

    if not comprobar_manifiestos(cliente, sello):
        problemas.append("los assets publicados no cuadran")

    cliente.close()

    if problemas:
        sys.exit("\nERROR: " + "; ".join(problemas) + ".")

    print(f"\n=== Deploy completado: {sello['rama']} @ {sello['commit_corto']} ===")


def comprobar_cierre() -> bool:
    """
    El /security-review del 2026-08-29 encontró la aplicación entera legible
    por HTTP. Se comprueba en cada despliegue para que no pueda volver.
    """
    print("\n--> Comprobando que nada interno responde por HTTP")
    todo_bien = True

    for ruta in COMPROBAR_CERRADAS:
        url = f"https://prueba.nodico.com.mx{ruta}"
        peticion = urllib.request.Request(url, method="HEAD")
        try:
            with urllib.request.urlopen(peticion, timeout=20) as resp:
                estado = resp.status
        except urllib.error.HTTPError as exc:
            estado = exc.code
        except Exception as exc:
            print(f"    ?   {ruta} -> no se pudo comprobar ({exc})")
            continue

        if estado == 200:
            print(f"    MAL {ruta} -> 200, sigue siendo público")
            todo_bien = False
        else:
            print(f"    ok  {ruta} -> {estado}")

    # Blindar de mas es tan roto como blindar de menos.
    print("\n--> Comprobando que el sitio sigue en pie")
    for ruta in COMPROBAR_ABIERTAS:
        url = f"https://prueba.nodico.com.mx{ruta}"
        peticion = urllib.request.Request(url, method="HEAD")
        try:
            with urllib.request.urlopen(peticion, timeout=20) as resp:
                estado = resp.status
        except urllib.error.HTTPError as exc:
            estado = exc.code
        except Exception as exc:
            print(f"    ?   {ruta} -> no se pudo comprobar ({exc})")
            continue

        if estado == 200:
            print(f"    ok  {ruta} -> 200")
        else:
            print(f"    MAL {ruta} -> {estado}, deberia responder 200")
            todo_bien = False

    return todo_bien


if __name__ == "__main__":
    main()
