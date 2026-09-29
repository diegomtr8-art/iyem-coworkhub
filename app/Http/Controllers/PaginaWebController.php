<?php

namespace App\Http\Controllers;

use App\Servicios\Sitio\CatalogoDelSitio;
use App\Servicios\Sitio\ContenidoDelSitio;
use App\Servicios\Sitio\FormatosDeImagen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo «Página Web»: la coordinación cambia textos, enlaces y datos de
 * contacto del sitio público sin tocar código. Qué se puede editar lo decide
 * `CatalogoDelSitio`; esta clase solo lo presenta y lo guarda.
 *
 * Organizado como el sitio, no como la base: una pantalla por página pública y,
 * dentro, las secciones en el orden en que salen. Ver docs/CMS-PAGINA-WEB.md.
 */
class PaginaWebController extends Controller
{
    public function __construct(private readonly ContenidoDelSitio $sitio) {}

    public function index(): Response
    {
        $secciones = collect(CatalogoDelSitio::secciones());

        return Inertia::render('PaginaWeb/Index', [
            'paginas' => collect(CatalogoDelSitio::paginas())
                ->map(fn (array $pagina, string $clave) => [
                    'clave'          => $clave,
                    'titulo'         => $pagina['titulo'],
                    'descripcion'    => $pagina['descripcion'],
                    'secciones'      => $secciones->where('pagina', $clave)->count(),
                    'personalizadas' => $secciones->where('pagina', $clave)->keys()
                        ->filter(fn (string $seccion) => $this->sitio->personalizada($seccion))->count(),
                ])
                ->filter(fn (array $pagina) => $pagina['secciones'] > 0)
                ->values(),
        ]);
    }

    public function editar(string $pagina): Response
    {
        $datos = CatalogoDelSitio::paginas()[$pagina] ?? abort(404);

        $secciones = collect(CatalogoDelSitio::secciones())
            ->filter(fn (array $seccion) => $seccion['pagina'] === $pagina)
            ->map(fn (array $seccion, string $clave) => $this->presentarSeccion($clave, $seccion, $seccion['ruta'] ?? $datos['ruta']))
            ->values();

        abort_if($secciones->isEmpty(), 404);

        return Inertia::render('PaginaWeb/Editar', [
            'pagina'    => ['clave' => $pagina, 'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion']],
            'secciones' => $secciones,
        ]);
    }

    public function guardar(Request $request, string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        $cambio = $this->sitio->guardar($seccion, $this->valores($request, $seccion), $request->user());

        return back()->with($cambio ? 'success' : 'info', $cambio
            ? '«' . CatalogoDelSitio::secciones()[$seccion]['titulo'] . '» guardado. Ya se ve en el sitio.'
            : 'No había cambios que guardar.');
    }

    public function deshacer(string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        return $this->sitio->deshacer($seccion)
            ? back()->with('success', 'Cambio deshecho. El sitio ya muestra la versión anterior.')
            : back()->with('info', 'No hay cambios anteriores que deshacer.');
    }

    public function restablecer(Request $request, string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        return $this->sitio->restablecer($seccion, $request->user())
            ? back()->with('success', 'Se volvió al texto original. Puedes deshacerlo si fue un error.')
            : back()->with('info', 'Esta sección ya tiene el texto original.');
    }

    /**
     * Valida el borrador y devuelve a la pantalla; el front abre la página
     * pública con `?vista_previa=1`. Nada llega al público.
     */
    public function vistaPrevia(Request $request, string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        $this->sitio->prepararVistaPrevia($seccion, $this->valores($request, $seccion));

        return back()->with('vistaPrevia', $seccion);
    }

    /** @return array<string, mixed> */
    private function valores(Request $request, string $seccion): array
    {
        // Solo los campos del catálogo; y como texto, nunca como arreglo u
        // objeto aunque alguien arme la petición a mano.
        return collect(CatalogoDelSitio::campos($seccion))
            ->keys()
            ->filter(fn (string $campo) => $request->exists($campo))
            ->mapWithKeys(fn (string $campo) => [$campo => $request->input($campo)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function presentarSeccion(string $clave, array $seccion, string $ruta): array
    {
        $ultimo = $this->sitio->ultimoCambio($clave);
        $publica = route($ruta) . '?vista_previa=1' . ($seccion['ancla'] ?? '');

        return [
            'clave'         => $clave,
            'titulo'        => $seccion['titulo'],
            'aparece'       => $seccion['aparece'],
            'enSitio'       => route($ruta) . ($seccion['ancla'] ?? ''),
            'vistaPrevia'   => $publica,
            'personalizada' => $this->sitio->personalizada($clave),
            'valores'       => $this->sitio->seccion($clave),
            // Lo mismo que ve el sitio: de aquí sale la miniatura de cada foto.
            'presentada'    => $this->sitio->presentar($clave),
            'ultimoCambio'  => $ultimo ? [
                'por'    => $ultimo->autor?->name ?? 'Sistema',
                'cuando' => $ultimo->created_at?->toIso8601String(),
            ] : null,
            'puedeDeshacer' => $ultimo !== null,
            'campos'        => $this->describirCampos($seccion['campos']),
        ];
    }

    /**
     * Lo que el panel necesita para pintar cada control. En las listas, lo
     * mismo para los campos de cada elemento; en las fotos, la proporción y el
     * ancho mínimo que se le enseñan al administrador antes de subir.
     *
     * @param  array<string, array>  $campos
     * @return array<int, array<string, mixed>>
     */
    private function describirCampos(array $campos): array
    {
        return collect($campos)->map(function (array $campo, string $nombre) {
            $descripcion = [
                'nombre'    => $nombre,
                'etiqueta'  => $campo['etiqueta'],
                'tipo'      => $campo['tipo'],
                'ayuda'     => $campo['ayuda'] ?? null,
                'maximo'    => CatalogoDelSitio::maximo($campo),
                'requerido' => in_array('required', $campo['reglas'], true),
            ];

            // array_merge y no `+=`: `maximo` ya existe (el tope de caracteres,
            // nulo en una lista) y aquí tiene que pasar a ser el de elementos.
            if ($campo['tipo'] === 'lista') {
                $descripcion = array_merge($descripcion, [
                    'elemento'  => $campo['elemento'],
                    'minimo'    => $campo['minimo'],
                    'maximo'    => $campo['maximo'],
                    'multiplo'  => $campo['multiplo'] ?? 1,
                    'campos'    => $this->describirCampos($campo['campos']),
                ]);
            }

            if ($campo['tipo'] === 'imagen') {
                $formato = FormatosDeImagen::de($campo['formato']);
                $descripcion += [
                    'formato'     => $campo['formato'],
                    'formatoNombre' => $formato['nombre'],
                    'proporcion'  => $formato['proporcion'],
                    'anchoMinimo' => $formato['minimo'],
                    'decorativa'  => $campo['decorativa'],
                ];
            }

            return $descripcion;
        })->values()->all();
    }
}
