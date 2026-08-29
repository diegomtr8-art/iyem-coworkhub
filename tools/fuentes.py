#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
IMG-02 — convierte las fuentes de marca a WOFF2 subseteadas a español.

Los OTF/TTF originales pesan 4.2 MB y son el 78 % del peso de la portada.
Se subsetean al latín con acentos y signos del español y se comprimen a WOFF2.

    python tools/fuentes.py

Los originales se conservan fuera de public/ (en recursos/fuentes-originales/).
"""

import shutil
import sys
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont

RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / 'public' / 'fonts'
RESPALDO = RAIZ / 'recursos' / 'fuentes-originales'

# Latín básico + todo lo que necesita el español + signos usados en la interfaz.
CARACTERES = (
    ''.join(chr(c) for c in range(0x20, 0x7F))          # ASCII imprimible
    + 'áéíóúÁÉÍÓÚ'
    + 'àèìòùÀÈÌÒÙ'
    + 'äëïöüÄËÏÖÜ'
    + 'ñÑçÇ'
    + '¿¡ºª'
    + '€$£'
    + '«»""\'\''
    + '–—…·•◆●⭐→←↑↓'
    + '©®™°'
    + 'ĀāŌō'                                             # el macrón de NŌDICO
)

# Solo los pesos que el sitio usa de verdad.
PESOS_USADOS = {
    'Carmen Sans Regular.otf',
    'Carmen Sans Medium.otf',
    'Carmen Sans SemiBold.otf',
    'Carmen Sans Bold.otf',
    'Carmen Sans ExtraBold.otf',
    # font-black (900) se usa 53 veces en dashboard, portal y auth.
    'Carmen Sans Heavy.otf',
    'GTEestiProDisplay-Regular.ttf',
    'GTEestiProDisplay-Light.ttf',
}


def nombre_salida(archivo: Path) -> str:
    return archivo.stem.replace(' ', '-').lower() + '.woff2'


def main() -> int:
    if not ORIGEN.is_dir():
        print(f'ERROR: no existe {ORIGEN}')
        return 1

    RESPALDO.mkdir(parents=True, exist_ok=True)

    originales = sorted(p for p in ORIGEN.iterdir() if p.suffix.lower() in {'.otf', '.ttf'})
    if not originales:
        print('No hay OTF/TTF que convertir (¿ya se ejecutó?).')
        return 0

    total_antes = 0
    total_despues = 0

    print()
    for archivo in originales:
        antes = archivo.stat().st_size
        total_antes += antes

        # El original se guarda fuera de public/ para no volver a subirlo por SFTP.
        destino_respaldo = RESPALDO / archivo.name
        if not destino_respaldo.exists():
            shutil.copy2(archivo, destino_respaldo)

        if archivo.name not in PESOS_USADOS:
            archivo.unlink()
            print(f'  {archivo.name:<34} peso sin usar, retirado de public/')
            continue

        fuente = TTFont(archivo)
        opciones = subset.Options()
        opciones.layout_features = ['kern', 'liga', 'calt', 'ccmp', 'locl']
        opciones.desubroutinize = True
        opciones.drop_tables += ['DSIG']
        opciones.notdef_outline = False
        opciones.recalc_bounds = True

        subsetter = subset.Subsetter(options=opciones)
        subsetter.populate(text=CARACTERES)
        subsetter.subset(fuente)

        fuente.flavor = 'woff2'
        salida = ORIGEN / nombre_salida(archivo)
        fuente.save(salida)
        fuente.close()

        despues = salida.stat().st_size
        total_despues += despues
        archivo.unlink()

        print(f'  {archivo.name:<34} {antes / 1024:8.1f} KB -> {despues / 1024:7.1f} KB'
              f'  ({100 - despues * 100 / antes:.0f} % menos)')

    print()
    print(f'  TOTAL  {total_antes / 1024:.1f} KB -> {total_despues / 1024:.1f} KB'
          f'  ({100 - total_despues * 100 / total_antes:.0f} % menos)')
    print(f'  Originales conservados en {RESPALDO.relative_to(RAIZ)}')
    print()
    return 0


if __name__ == '__main__':
    sys.exit(main())
