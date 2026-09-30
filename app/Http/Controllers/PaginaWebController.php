<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Models\EntradaBitacora;
use App\Servicios\Sitio\CatalogoDelSitio;
use App\Servicios\Sitio\ComprobadorDeEnlaces;
use App\Servicios\Sitio\ContenidoDelSitio;
use App\Servicios\Sitio\FormatosDeImagen;
use App\Servicios\Sitio\ProcesadorDeImagenes;
use Illuminate\Http\JsonResponse;
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

    public function guardar(Request $request, string $seccion, ComprobadorDeEnlaces $enlaces): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        $antes = $this->sitio->seccion($seccion);

        if (! $this->sitio->guardar($seccion, $this->valores($request, $seccion), $request->user())) {
            return back()->with('info', 'No había cambios que guardar.');
        }

        $despues = $this->sitio->seccion($seccion);
        $this->registrar(AccionOperativa::EdicionSitio, $seccion, $antes, $despues, $request);

        $titulo = CatalogoDelSitio::secciones()[$seccion]['titulo'];
        $rotos = $this->enlacesQueNoResponden($seccion, $antes, $despues, $enlaces);

        // Se guarda igual: un sitio caído un rato no debe impedir el cambio. Pero
        // quien lo puso tiene que saberlo, porque en el pie está roto para todos.
        if ($rotos) {
            return back()->with('warning', "«{$titulo}» guardado, pero estos enlaces no respondieron: "
                . implode(', ', $rotos) . '. Revisa que estén bien escritos.');
        }

        return back()->with('success', "«{$titulo}» guardado. Ya se ve en el sitio.");
    }

    public function deshacer(Request $request, string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        $antes = $this->sitio->seccion($seccion);

        if (! $this->sitio->deshacer($seccion)) {
            return back()->with('info', 'No hay cambios anteriores que deshacer.');
        }

        $this->registrar(AccionOperativa::DeshacerSitio, $seccion, $antes, $this->sitio->seccion($seccion), $request);

        return back()->with('success', 'Cambio deshecho. El sitio ya muestra la versión anterior.');
    }

    public function restablecer(Request $request, string $seccion): RedirectResponse
    {
        CatalogoDelSitio::existe($seccion) || abort(404);

        $antes = $this->sitio->seccion($seccion);

        if (! $this->sitio->restablecer($seccion, $request->user())) {
            return back()->with('info', 'Esta sección ya tiene el texto original.');
        }

        $this->registrar(AccionOperativa::RestablecerSitio, $seccion, $antes, $this->sitio->seccion($seccion), $request);

        return back()->with('success', 'Se volvió al texto original. Puedes deshacerlo si fue un error.');
    }

    /**
     * Una línea en la bitácora por cambio: quién, qué sección, qué campos, y el
     * valor anterior y el nuevo de cada uno. Es lo que permite contestar
     * «¿quién cambió el teléfono del pie y cuándo?» sin adivinar.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     */
    private function registrar(AccionOperativa $accion, string $seccion, array $antes, array $despues, Request $request): void
    {
        $campos = CatalogoDelSitio::campos($seccion);
        $cambios = [];

        foreach ($campos as $nombre => $campo) {
            if (($antes[$nombre] ?? null) !== ($despues[$nombre] ?? null)) {
                $cambios[$nombre] = [
                    'antes'   => $this->resumir($antes[$nombre] ?? null),
                    'despues' => $this->resumir($despues[$nombre] ?? null),
                ];
            }
        }

        $nombres = implode(', ', array_map(fn (string $n) => mb_strtolower($campos[$n]['etiqueta']), array_keys($cambios)));
        $titulo = CatalogoDelSitio::secciones()[$seccion]['titulo'];
        $pagina = CatalogoDelSitio::paginas()[CatalogoDelSitio::secciones()[$seccion]['pagina']]['titulo'];

        EntradaBitacora::registrar(
            $accion,
            "{$pagina} · «{$titulo}»" . ($nombres !== '' ? ": {$nombres}" : ''),
            $request->user(),
            contexto: ['seccion' => $seccion, 'cambios' => $cambios],
        );
    }

    /** Lo bastante para reconocer el valor, sin llenar la bitácora con listas enteras. */
    private function resumir(mixed $valor): mixed
    {
        if (is_array($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_string($valor) ? mb_strimwidth($valor, 0, 500, '…') : $valor;
    }

    /**
     * Los enlaces de la sección que cambiaron y no responden, por su etiqueta.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return array<int, string>
     */
    private function enlacesQueNoResponden(string $seccion, array $antes, array $despues, ComprobadorDeEnlaces $enlaces): array
    {
        $rotos = [];

        foreach (CatalogoDelSitio::campos($seccion) as $nombre => $campo) {
            $url = $despues[$nombre] ?? null;

            if ($campo['tipo'] === 'url' && is_string($url) && $url !== '' && $url !== ($antes[$nombre] ?? null) && ! $enlaces->responde($url)) {
                $rotos[] = "«{$campo['etiqueta']}»";
            }
        }

        return $rotos;
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

    /**
     * Sube una foto para un hueco del formato dado y devuelve cómo se ve. No
     * la publica: el panel la pone en el formulario y se publica al guardar la
     * sección, igual que un texto.
     */
    public function subirImagen(Request $request, string $formato, ProcesadorDeImagenes $procesador): JsonResponse
    {
        FormatosDeImagen::existe($formato) || abort(404);

        $imagen = $procesador->subir($request->file('imagen') ?? abort(422, 'Falta la foto.'), $formato, $request->user());

        return response()->json(['id' => $imagen->id] + $imagen->presentar(''));
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
