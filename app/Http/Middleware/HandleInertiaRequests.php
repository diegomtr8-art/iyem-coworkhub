<?php

namespace App\Http\Middleware;

use App\Providers\AuthServiceProvider;
use App\Servicios\Sitio\CatalogoDelSitio;
use App\Servicios\Sitio\ContenidoDelSitio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user'    => $this->usuarioCompartido($request),
                'permisos' => $this->permisosCompartidos($request),
            ],
            'flash' => [
                'success'     => $request->session()->get('success'),
                'error'       => $request->session()->get('error'),
                'info'        => $request->session()->get('info'),
                'warning'     => $request->session()->get('warning'),
                'contacto_ok' => $request->session()->get('contacto_ok'),
                // «Página Web»: sección cuyo borrador se acaba de preparar.
                'vistaPrevia' => $request->session()->get('vistaPrevia'),
            ],
            'isStaging' => app()->environment('staging'),

            // La página pública está mostrando borradores del módulo «Página
            // Web» a quien los edita: el sitio lo avisa con una franja.
            'vistaPrevia' => app(ContenidoDelSitio::class)->enVistaPrevia(),

            // «Página Web»: lo que se repite en todas las páginas públicas
            // (aliados, pie, «Hablemos»). Sale de la caché del contenido.
            'comun' => fn () => app(ContenidoDelSitio::class)->pagina('comun'),

            // Fase 3.1 - Contadores del menu del panel operativo. Es lo que
            // pone el punto de aviso junto a "Asesorias" sin obligar a entrar
            // para descubrir que hay algo pendiente.
            //
            // Cerrado (`Closure`) para que Inertia solo lo evalue cuando la
            // pagina lo pide: en el sitio publico y en el portal del miembro
            // esta consulta no tiene por que correr.
            'avisosPanel' => fn () => $this->avisosDelPanel($request),

            // F — Que botones de acceso externo dibujar. La ruta de cada
            // proveedor comprueba lo mismo por su cuenta: esconder el boton no
            // es control de acceso. Google pide interruptor y credenciales.
            'proveedores' => [
                'google'       => \App\Support\AccesoConGoogle::disponible(),
                'enlaceMagico' => (bool) config('nodico.acceso.enlace_magico'),
            ],

            // Datos de contacto y redes que consumen el footer y "Hablemos".
            // Salen del módulo «Página Web» con respaldo en config/nodico.php;
            // la forma de la prop no cambia, porque hay componentes que ya la
            // leen. El embed del mapa es configuración, no contenido.
            'nodico' => $this->datosDeContacto(),

            // SEO-01..04 — todo lo que consume <Meta>.
            'seo' => [
                'origen'   => $this->origenCanonico(),
                'canonica' => $this->origenCanonico() . $request->getPathInfo(),
                'negocio'  => $this->fichaNegocio(),
                'pagina'   => $this->metadatosDePagina($request),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function datosDeContacto(): array
    {
        $sitio = app(ContenidoDelSitio::class);
        $contacto = $sitio->seccion('contacto');
        $redes = $sitio->seccion('redes');

        return [
            'email'           => $contacto['email'],
            'telefono'        => $contacto['telefono'],
            'telefonoE164'    => CatalogoDelSitio::telefonoE164((string) $contacto['telefono']),
            'direccion'       => $contacto['direccion'],
            'direccionCorta'  => $contacto['direccion_corta'],
            'mapsUrl'         => $contacto['maps_url'],
            'mapsEmbed'       => config('nodico.maps_embed'),
            'horarios'        => $contacto['horarios'],
            'horariosDetalle' => $contacto['horarios_detalle'],
            'redes'           => [
                'instagram' => $redes['instagram'],
                'facebook'  => $redes['facebook'],
                'linkedin'  => $redes['linkedin'],
            ],
            'instagram'       => $redes['instagram_usuario'],
        ];
    }

    /**
     * Que puede hacer esta persona, segun los Gates.
     *
     * Sirve para no ensenar en el menu secciones que van a responder 403.
     * **No es control de acceso**: cada ruta se autoriza en el servidor con su
     * propio `can:`; esconder un boton no protege nada.
     *
     * @return array<string, bool>
     */
    private function permisosCompartidos(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $permisos = [];

        foreach (AuthServiceProvider::todos() as $permiso) {
            $permisos[$permiso] = Gate::allows($permiso);
        }

        return $permisos;
    }

    /**
     * Usuario que se comparte con el front, campo por campo.
     *
     * Antes se enviaba `$request->user()` entero, o sea el modelo completo:
     * entre otras cosas `notas_admin`, que son apuntes internos del equipo
     * sobre esa persona y que le llegaban a su propio navegador en cada
     * respuesta de Inertia.
     *
     * `rol` y `portalRuta` salen de aqui para que el navbar publico y los
     * layouts no tengan que adivinar el destino comparando cadenas: con un rol
     * desconocido `portalRuta` es `null` y la interfaz simplemente no ofrece
     * ningun portal, en vez de mandar a nadie a rebotar (A.1).
     *
     * @return array<string, mixed>|null
     */
    private function usuarioCompartido(Request $request): ?array
    {
        $usuario = $request->user();

        if (! $usuario) {
            return null;
        }

        return [
            'id'             => $usuario->id,
            'name'           => $usuario->name,
            'email'          => $usuario->email,
            'avatar'         => $usuario->avatar,
            'telefono'       => $usuario->telefono,
            'empresa'        => $usuario->empresa,
            'ocupacion'      => $usuario->ocupacion,
            'face_id_ok'     => $usuario->face_id_ok,
            'email_verified_at' => $usuario->email_verified_at,
            'verificado'     => $usuario->hasVerifiedEmail(),
            'rol'            => $usuario->rol?->value,
            'rolEtiqueta'    => $usuario->rol?->etiqueta(),
            'esOperativo'    => $usuario->esOperativo(),
            'portalRuta'     => $usuario->rutaInicio(),
            'dosFactores'    => $usuario->tieneDosFactores(),
            'estado'         => $usuario->estado->value,
            'estadoEtiqueta' => $usuario->estado->etiqueta(),
            'cuentaActiva'   => $usuario->cuentaActiva(),
        ];
    }

    /**
     * SEO-02 — origen canónico. **El sitio vive sin www** (decisión de Diego del
     * 29/08/2026), así que un `www.` al principio del host se quita siempre:
     * quien llegue por www sigue apuntando a la misma URL canónica.
     *
     * Ya no se cae al host de la petición. Con `trustProxies(at: '*')`,
     * `getSchemeAndHttpHost()` respeta la cabecera `X-Forwarded-Host`, de modo
     * que cualquiera podía elegir el host que aparecía en la canónica, en
     * `og:url` y en el JSON-LD. `app.url` ya apunta al host correcto en cada
     * entorno y no depende de la petición.
     */
    private function origenCanonico(): string
    {
        $origen = rtrim(config('nodico.host_canonico') ?: config('app.url'), '/');

        return preg_replace('#^(https?://)www\\.#i', '$1', $origen);
    }

    /**
     * SEO-01 — título, descripción e imagen social de la página actual.
     *
     * Se resuelve por nombre de ruta para que `app.blade.php` pueda emitirlos
     * en el HTML: los scrapers de enlaces no ejecutan JavaScript.
     */
    private function metadatosDePagina(Request $request): array
    {
        $paginas = config('nodico.seo_paginas', []);
        $ruta = $request->route()?->getName();
        $ruta = isset($paginas[$ruta]) ? $ruta : 'home';

        // Título, descripción e imagen social se editan en «Página Web» →
        // Buscadores, con config/nodico.php como respaldo.
        $seo = app(ContenidoDelSitio::class)->presentar("buscadores.{$ruta}");

        return [
            'titulo'      => $seo['titulo'] ?? config('nodico.sufijo_titulo'),
            'descripcion' => $seo['descripcion'] ?? '',
            // URL absoluta: un scraper no resuelve rutas relativas.
            'imagen'      => $this->origenCanonico() . $seo['imagen']['src'],
        ];
    }

    /** SEO-03 — LocalBusiness con los datos que ya están en config/nodico.php. */
    private function fichaNegocio(): array
    {
        $origen = $this->origenCanonico();
        $contacto = app(ContenidoDelSitio::class)->seccion('contacto');
        $redes = app(ContenidoDelSitio::class)->seccion('redes');
        $negocio = app(ContenidoDelSitio::class)->seccion('negocio');
        $operacion = config('nodico.operacion');
        $dias = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday', 0 => 'Sunday'];

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => 'Nódico',
            'description' => $negocio['descripcion'],
            'url'         => $origen,
            'image'       => $origen . app(ContenidoDelSitio::class)->presentar('buscadores.home')['imagen']['src'],
            'logo'        => $origen . '/img/nodico/logo-nodico-blanco.png',
            'email'       => $contacto['email'],
            'telephone'   => CatalogoDelSitio::telefonoE164((string) $contacto['telefono']),
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $negocio['calle'],
                'addressLocality' => $negocio['localidad'],
                'addressRegion'   => $negocio['region'],
                'postalCode'      => $negocio['codigo_postal'],
                'addressCountry'  => 'MX',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $negocio['latitud'],
                'longitude' => (float) $negocio['longitud'],
            ],
            // Sale de la regla del motor de reservas: un solo horario.
            'openingHoursSpecification' => [[
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values(array_map(fn (int $dia) => $dias[$dia], $operacion['dias_habiles'])),
                'opens'     => $operacion['apertura'],
                'closes'    => $operacion['cierre'],
            ]],
            'sameAs' => array_values(array_filter([$redes['instagram'], $redes['facebook'], $redes['linkedin']])),
        ];
    }

    /**
     * Pendientes que el panel ensena como aviso en el menu.
     *
     * @return array<string, int>
     */
    private function avisosDelPanel(Request $request): array
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->esOperativo()) {
            return [];
        }

        return [
            'asesorias_pendientes' => \App\Models\SolicitudAsesoria::pendientes()->count(),
        ];
    }
}
