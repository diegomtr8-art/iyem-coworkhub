<script setup lang="ts">
/**
 * F — Armazón común de las once pantallas de acceso.
 *
 * Composición partida: a la izquierda un panel de marca a sangre —foto del
 * espacio con velo— y a la derecha el formulario sobre fondo claro, con ancho
 * cómodo de lectura.
 *
 * En iPhone el panel se reduce a una banda superior con el logo y el formulario
 * ocupa el resto: dos columnas apretadas en 375 px no son responsive, son un
 * castigo.
 *
 * Nota sobre el sistema de diseño: `SKILL.md` decía que el «editorial técnico»
 * no aplicaba a las pantallas de autenticación. Esa exclusión ya no vale —
 * Nódico las quiere dentro— y el skill está actualizado en consecuencia.
 */
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { computed } from 'vue'

withDefaults(
  defineProps<{
    titulo: string
    /** Aparece en la pestaña; por defecto, el mismo título. */
    tituloPestana?: string
    /** Etiqueta mono numerada, al estilo de las fichas técnicas del sitio. */
    etiqueta: string
    numero?: string
    subtitulo?: string
    /** Frase grande del panel de marca. */
    frase?: string
    /** Un formulario largo (registro) necesita más aire que uno de un campo. */
    ancho?: 'normal' | 'amplio'
  }>(),
  { ancho: 'normal' },
)

const page = usePage()
const marca = computed(() => (page.props.marca ?? {}) as any)
const datos = computed<Array<{ valor: string; texto: string }>>(() => marca.value.datos ?? [])
</script>

<template>
  <Head :title="tituloPestana ?? titulo" />

  <div class="min-h-svh bg-cream lg:grid lg:min-h-screen lg:grid-cols-[minmax(0,42%)_minmax(0,58%)]">
    <!-- ── Panel de marca ─────────────────────────────────────────────── -->
    <aside class="relative isolate overflow-hidden bg-tinta lg:flex lg:flex-col lg:justify-between">
      <!--
        Decorativa: no lleva texto alternativo porque no aporta información que
        no esté ya escrita al lado. `loading="eager"` porque está en el primer
        pliegue; el tamaño explícito evita el salto de maquetación.
      -->
      <img
        src="/img/nodico/comunidad-fondo.webp"
        alt=""
        aria-hidden="true"
        width="1920"
        height="960"
        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-45"
      />
      <div class="absolute inset-0 -z-10 bg-tinta/55" aria-hidden="true" />

      <!-- Cabecera: logo y vuelta al sitio -->
      <div class="flex items-center justify-between gap-4 px-6 py-5 sm:px-8 lg:px-10 lg:py-8">
        <Link :href="route('home')" class="inline-flex min-h-[44px] items-center">
          <img
            src="/img/nodico/logo-nodico-blanco.png"
            alt="Nódico — inicio"
            width="480"
            height="159"
            class="h-7 w-auto lg:h-8"
          />
        </Link>

        <!-- El enlace de vuelta está siempre, y con etiqueta legible, no una «x». -->
        <Link
          :href="route('home')"
          class="inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 font-body text-sm
                 text-white/75 transition hover:text-nodo-400 focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
        >
          <ArrowLeft class="h-4 w-4" aria-hidden="true" />
          <span class="hidden sm:inline">Volver al sitio</span>
          <span class="sm:hidden">Sitio</span>
        </Link>
      </div>

      <!-- Frase de marca: solo en pantallas grandes; en móvil roba el sitio al formulario -->
      <div class="hidden px-10 lg:block">
        <p class="etiqueta-tecnica text-nodo-400">Nódico · Mérida</p>
        <p class="mt-5 max-w-md font-display text-display-md font-extrabold leading-[0.95] text-cream">
          {{ frase ?? 'Donde el trabajo es un pretexto para crear' }}
        </p>
      </div>

      <!-- Prueba social: sale de la base de datos, nunca de una cifra inventada -->
      <div v-if="datos.length" class="hidden px-10 pb-10 lg:block">
        <div class="h-px w-full bg-white/20" />
        <dl class="mt-6 flex flex-wrap gap-x-10 gap-y-4">
          <div v-for="dato in datos" :key="dato.texto">
            <dt class="font-display text-2xl font-extrabold text-nodo-400">{{ dato.valor }}</dt>
            <dd class="mt-0.5 font-body text-sm text-white/75">{{ dato.texto }}</dd>
          </div>
        </dl>
      </div>
    </aside>

    <!-- ── Formulario ─────────────────────────────────────────────────── -->
    <main class="flex justify-center px-5 py-10 sm:px-8 lg:items-center lg:px-12 lg:py-14">
      <div class="w-full" :class="ancho === 'amplio' ? 'max-w-lg' : 'max-w-md'">
        <p class="etiqueta-tecnica flex items-center gap-3 text-dark/70">
          <span v-if="numero" class="text-dark">{{ numero }}</span>
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          {{ etiqueta }}
        </p>

        <div class="mt-4 h-px w-full bg-dark/15" />

        <h1 class="mt-7 font-display text-display-sm font-extrabold text-dark">
          {{ titulo }}
        </h1>

        <p v-if="subtitulo" class="mt-3 font-body text-cuerpo text-dark/70">
          {{ subtitulo }}
        </p>

        <div class="mt-8">
          <slot />
        </div>

        <div v-if="$slots.pie" class="pb-segura mt-10 border-t border-dark/10 pt-6">
          <slot name="pie" />
        </div>
      </div>
    </main>
  </div>
</template>
