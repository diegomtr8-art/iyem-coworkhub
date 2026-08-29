<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\DirectorioEmprendedor;
use App\Models\Espacio;
use App\Models\Evento;
use App\Models\Plane;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // BE-01: la prop `eventos` se enviaba en cada carga y Welcome.vue nunca
        // la declaraba ni la usaba. Retirada.
        return Inertia::render('Welcome', [
            ...$this->authProps(),
            'planes'         => Plane::publicos()->get(),
            'salon'          => Espacio::salonesPublicados()->first(),
            'instagramPosts' => Ajuste::obtener('instagram_posts', []),
        ]);
    }

    public function nosotros()
    {
        return Inertia::render('Nosotros', $this->authProps());
    }

    public function membresias()
    {
        return Inertia::render('Membresias', [
            ...$this->authProps(),
            'planes' => Plane::publicos()->get(),
        ]);
    }

    /** `/eventos` — salones para eventos (equivalente a /salones en Odoo). */
    public function salones()
    {
        return Inertia::render('Salones', [
            ...$this->authProps(),
            'salones' => Espacio::salonesPublicados()->get(),
        ]);
    }

    /** `/actividades` — comunidad y talleres (equivalente a /comunidad en Odoo). */
    public function comunidad()
    {
        return Inertia::render('Comunidad', [
            ...$this->authProps(),
            'proximos'       => Evento::activos()->proximos()->limit(6)->get(),
            'pasados'        => Evento::activos()->pasados()->limit(6)->get(),
            'salon'          => Espacio::salonesPublicados()->first(),
            'lumaEmbed'      => config('nodico.luma_embed'),
            'instagramPosts' => Ajuste::obtener('instagram_posts', []),
            // CNT-02: directorio y destacado salen de la BD, no del componente.
            'directorio'     => DirectorioEmprendedor::publicos()->where('destacado_semana', false)->get(),
            'destacado'      => DirectorioEmprendedor::deLaSemana()->first(),
        ]);
    }

    public function privacidad()
    {
        return $this->documentoLegal('aviso-de-privacidad');
    }

    public function terminos()
    {
        return $this->documentoLegal('terminos');
    }

    /**
     * BE-04 — los textos legales viven en resources/legal/*.md, no incrustados
     * en el controlador, para que el área jurídica pueda revisarlos y editarlos
     * sin tocar código.
     */
    private function documentoLegal(string $nombre)
    {
        // E — El parseo y la version viven en `DocumentosLegales`, para que no
        // haya dos lecturas distintas del mismo archivo.
        $clave = collect(\App\Support\DocumentosLegales::DOCUMENTOS)
            ->search(fn ($d) => $d['archivo'] === $nombre);

        if ($clave !== false) {
            $doc = app(\App\Support\DocumentosLegales::class)->leer($clave);

            return Inertia::render('Legal/Documento', [
                ...$this->authProps(),
                'titulo'      => $doc['titulo'],
                'descripcion' => $doc['descripcion'],
                'version'     => $doc['version'],
                'provisional' => $doc['provisional'],
                'contenido'   => $doc['contenido'],
            ]);
        }

        $ruta = resource_path("legal/{$nombre}.md");
        abort_unless(is_file($ruta), 404);

        $crudo = file_get_contents($ruta);

        // Cabecera sencilla clave: valor al inicio del archivo.
        $meta = [];
        if (preg_match('/^---\R(.*?)\R---\R(.*)$/s', $crudo, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $linea) {
                if (str_contains($linea, ':')) {
                    [$clave, $valor] = explode(':', $linea, 2);
                    $meta[trim($clave)] = trim($valor);
                }
            }
            $crudo = $m[2];
        }

        return Inertia::render('Legal/Documento', [
            ...$this->authProps(),
            'titulo'      => $meta['titulo'] ?? Str::headline($nombre),
            'descripcion' => $meta['descripcion'] ?? '',
            'provisional' => ($meta['provisional'] ?? 'false') === 'true',
            'contenido'   => Str::markdown($crudo),
        ]);
    }

    private function authProps(): array
    {
        return [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
        ];
    }
}
