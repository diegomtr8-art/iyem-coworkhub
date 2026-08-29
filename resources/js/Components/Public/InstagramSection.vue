<script setup lang="ts">
import { Instagram } from 'lucide-vue-next'
import { computed, reactive } from 'vue'

const props = withDefaults(defineProps<{
  handle?: string
  /** Permalinks de publicaciones; vienen de la tabla `ajustes`. */
  publicaciones?: string[]
  tono?: 'claro' | 'oscuro'
}>(), {
  handle: 'nodicomx',
  tono: 'claro',
})

const perfilUrl = computed(() => `https://www.instagram.com/${props.handle}`)

/**
 * `instagram.com/{cuenta}/embed` no está documentado por Meta y devuelve muro
 * de inicio de sesión a los visitantes sin sesión (FE-01). El endpoint público
 * soportado es el de publicación suelta, así que se embebe una por celda.
 */
function shortcode(permalink: string): string | null {
  return permalink.match(/instagram\.com\/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/)?.[1] ?? null
}

const publicacionesValidas = computed(() =>
  (props.publicaciones ?? [])
    .map((url) => ({ url, code: shortcode(url) }))
    .filter((p): p is { url: string; code: string } => p.code !== null),
)

/** Celdas cuyo iframe no llegó a cargar: muestran tarjeta de respaldo propia. */
const fallidas = reactive<Record<string, boolean>>({})
const cargadas = reactive<Record<string, boolean>>({})
/** Celdas que ya se acercaron a pantalla: solo entonces se crea su iframe. */
const cercanas = reactive<Record<string, boolean>>({})

function alCargar(code: string) {
  cargadas[code] = true
}

function alFallar(code: string) {
  fallidas[code] = true
}

/**
 * Instagram cuesta ~1.2 MB de terceros por los cuatro embeds, y la sección va
 * muy por debajo del pliegue. `loading="lazy"` no bastaba: el iframe existe
 * desde el primer render y el navegador lo pide igual en cuanto puede, así que
 * quien nunca baja hasta aquí pagaba el peso completo.
 *
 * Ahora el iframe no se crea hasta que la celda se acerca a pantalla. El
 * vigilante del respaldo arranca en ese mismo momento, no al montar: antes
 * expiraba el plazo sin que el iframe hubiera empezado siquiera a cargar y
 * tres de cuatro caían al respaldo sin motivo.
 */
function vigilar(el: Element | null, code: string) {
  if (! el) return

  // Sin IntersectionObserver no hay carga diferida posible: se muestra directo.
  if (typeof IntersectionObserver === 'undefined') {
    cercanas[code] = true
    return
  }

  const observador = new IntersectionObserver((entradas) => {
    entradas.forEach((entrada) => {
      if (! entrada.isIntersecting) return
      observador.disconnect()
      cercanas[code] = true
      window.setTimeout(() => {
        if (! cargadas[code]) fallidas[code] = true
      }, 10000)
    })
  }, { rootMargin: '400px' })

  observador.observe(el)
}
</script>

<template>
  <section
    class="py-20 lg:py-28"
    :class="tono === 'oscuro' ? 'bg-tinta' : 'bg-cream'"
  >
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
      <!-- Cabecera -->
      <div class="mb-12 text-center">
        <p
          class="etiqueta-tecnica mb-5 flex items-center justify-center gap-3"
          :class="tono === 'oscuro' ? 'text-nodo-400' : 'text-dark/70'"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Instagram
        </p>

        <h2
          class="font-display text-display-md font-extrabold"
          :class="tono === 'oscuro' ? 'text-white' : 'text-dark'"
        >
          Lo que pasa en Nódico
        </h2>
      </div>

      <!-- Reja: 1 columna en iPhone, 2 en iPad, 4 en desktop -->
      <ul
        v-if="publicacionesValidas.length"
        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
      >
        <li
          v-for="post in publicacionesValidas"
          :key="post.code"
          class="overflow-hidden rounded-2xl bg-white shadow-sombra-sm ring-1 ring-dark/[.07]"
          @vue:mounted="(n: any) => vigilar(n.el, post.code)"
        >
          <iframe
            v-if="cercanas[post.code] && !fallidas[post.code]"
            :src="`https://www.instagram.com/p/${post.code}/embed/`"
            :title="`Publicación de @${handle} en Instagram`"
            scrolling="no"
            loading="lazy"
            class="h-[420px] w-full border-0"
            @load="alCargar(post.code)"
            @error="alFallar(post.code)"
          />

          <!-- Respaldo por celda: nunca un hueco en blanco -->
          <a
            v-else-if="fallidas[post.code]"
            :href="post.url"
            target="_blank"
            rel="noopener noreferrer"
            class="flex h-[420px] flex-col items-center justify-center gap-5 bg-tinta p-6 text-center
                   transition hover:bg-dark"
          >
            <img
              src="/img/nodico/logo-nodico-blanco.png"
              alt=""
              aria-hidden="true"
              width="480"
              height="159"
              loading="lazy"
              class="h-8 w-auto"
            />
            <p class="font-body text-sm text-white/70">
              No pudimos cargar esta publicación.
            </p>
            <span class="inline-flex items-center gap-2 font-display text-sm font-bold text-nodo-400">
              <Instagram class="h-4 w-4" aria-hidden="true" />
              Verla en Instagram
            </span>
          </a>

          <!-- Aún lejos de pantalla: se reserva el alto para no mover la reja -->
          <div v-else class="h-[420px] w-full bg-cream-50" aria-hidden="true" />
        </li>
      </ul>

      <!-- Sin permalinks configurados -->
      <div
        v-else
        class="rounded-3xl p-10 text-center"
        :class="tono === 'oscuro' ? 'bg-white/[.05] ring-1 ring-white/10' : 'bg-white shadow-sombra ring-1 ring-dark/[.07]'"
      >
        <p class="font-body" :class="tono === 'oscuro' ? 'text-white/70' : 'text-dark/70'">
          Todavía no hay publicaciones configuradas.
        </p>
        <a
          :href="perfilUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-6 inline-flex min-h-[48px] items-center gap-2.5 rounded-xl bg-nodo-400 px-6 py-3
                 font-display text-sm font-bold text-dark transition hover:-translate-y-0.5 hover:shadow-sombra"
        >
          <Instagram class="h-4 w-4" aria-hidden="true" />
          Ver el perfil
        </a>
      </div>
    </div>
  </section>
</template>
