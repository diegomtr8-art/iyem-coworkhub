<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `titulo` es obligatorio en SectionHeading, pero `npm run build` no revisa
 * tipos: un encabezado sin título compila y se pinta como un <h2> vacío.
 *
 * Así se perdieron 13 títulos de sección en 8b983b7 (29-ago-2026), al quitar
 * `titulo=` de los <Meta>, y el sitio estuvo un mes con secciones sin titular
 * sin que nada fallara. Esta prueba es lo que ese día habría fallado.
 */
class EncabezadosDeSeccionTest extends TestCase
{
    public function test_ningun_section_heading_se_queda_sin_titulo(): void
    {
        $sinTitulo = [];

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'vue' || $archivo->getFilename() === 'SectionHeading.vue') {
                continue;
            }

            $codigo = file_get_contents($archivo->getPathname());

            // La etiqueta completa, que suele ocupar varias líneas.
            preg_match_all('/<SectionHeading\b[^>]*>/s', $codigo, $etiquetas, PREG_OFFSET_CAPTURE);

            foreach ($etiquetas[0] as [$etiqueta, $posicion]) {
                if (! preg_match('/(^|\s)(:|v-bind:)?titulo=/', $etiqueta)) {
                    $linea = substr_count(substr($codigo, 0, $posicion), "\n") + 1;
                    $sinTitulo[] = str_replace(resource_path() . DIRECTORY_SEPARATOR, '', $archivo->getPathname()) . ":{$linea}";
                }
            }
        }

        $this->assertSame([], $sinTitulo, "SectionHeading sin titulo en:\n" . implode("\n", $sinTitulo));
    }
}
