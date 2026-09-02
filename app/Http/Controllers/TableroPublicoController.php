<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\TableroEnlace;
use App\Servicios\Tablero\EstadoDeEspacios;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero público de ocupación (Fases 3-6).
 *
 * Se abre sin sesión, con un token largo en la URL, de SOLO LECTURA. El JSON que
 * sirve pasa por `EstadoDeEspacios`, que solo emite datos del espacio: aquí no
 * viaja ningún nombre, correo ni user_id, ni «por si acaso».
 */
class TableroPublicoController extends Controller
{
    public function __construct(private readonly EstadoDeEspacios $motor)
    {
    }

    /** La página del tablero (Inertia, pública). */
    public function mostrar(Request $request, string $token): Response
    {
        $enlace = $this->enlaceVigente($token);
        $enlace->forceFill(['ultimo_uso_en' => now()])->save();

        return Inertia::render('Tablero/Publico', [
            'token'    => $token,
            'inicial'  => $this->instantanea(),
        ]);
    }

    /** El JSON que la pantalla sondea cada 30 s. */
    public function datos(Request $request, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);

        // No escribir en cada sondeo: basta con marcar uso cada pocos minutos.
        if ($enlace->ultimo_uso_en === null || $enlace->ultimo_uso_en->lt(now()->subMinutes(5))) {
            $enlace->forceFill(['ultimo_uso_en' => now()])->save();
        }

        return response()->json($this->instantanea())
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** La agenda del día de un espacio (para el toque). Sin nombres. */
    public function agenda(Request $request, string $token, Espacio $espacio): JsonResponse
    {
        $this->enlaceVigente($token);

        return response()->json($this->motor->agendaDe($espacio))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    // ── Interno ────────────────────────────────────────────────────────────────

    private function instantanea(): array
    {
        $espacios = $this->motor->paraTablero();

        return [
            'espacios' => $espacios,
            'resumen'  => $this->motor->resumen($espacios),
            'hora'     => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /** Encuentra el enlace o corta con 404 (sin revelar por qué). */
    private function enlaceVigente(string $token): TableroEnlace
    {
        $enlace = TableroEnlace::where('token', $token)->whereNull('revocado_en')->first();

        abort_if($enlace === null, 404);

        return $enlace;
    }
}
