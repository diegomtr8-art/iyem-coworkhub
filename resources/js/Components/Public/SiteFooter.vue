<script setup lang="ts">
import Boton from '@/Components/Public/Boton.vue'
import Marquee from '@/Components/Public/Marquee.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowUp, Clock, Facebook, Instagram, Linkedin, Mail, MapPin, Phone } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)
const anio = new Date().getFullYear()

const enlaces = [
  { label: 'Inicio', ruta: 'home' },
  { label: 'Nosotros', ruta: 'nosotros' },
  { label: 'Membresías', ruta: 'membresias' },
  { label: 'Eventos', ruta: 'eventos' },
  { label: 'Actividades', ruta: 'actividades' },
]

const redes = computed(() => [
  { label: 'Instagram', href: nodico.value.redes?.instagram, icono: Instagram },
  { label: 'Facebook',  href: nodico.value.redes?.facebook,  icono: Facebook },
  { label: 'LinkedIn',  href: nodico.value.redes?.linkedin,  icono: Linkedin },
].filter((r) => r.href))

const aliados = [
  { nombre: 'Instituto Yucateco de Emprendedores', logo: '/img/nodico/logo-iyem.png', ancho: 452, alto: 75 },
  { nombre: 'Herencia Viva', logo: '/img/nodico/logo-herencia-viva.png', ancho: 418, alto: 63 },
  { nombre: 'CANIETI', logo: '/img/nodico/logo-canieti.png', ancho: 255, alto: 99 },
]

const palabras = ['Emprendimiento', 'Creatividad', 'Comunidad', 'Innovación', 'Coworking', 'Mérida', 'Yucatán']

function volverArriba() {
  const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  window.scrollTo({ top: 0, behavior: sinMovimiento ? 'auto' : 'smooth' })
}
</script>

<template>
  <footer>
    <!-- 1 · Cierre con energía sobre amarillo -->
    <section class="bg-nodo-400 py-16 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="flex flex-col gap-10 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="etiqueta-tecnica mb-6 text-dark/60">Da el paso</p>
            <p class="max-w-2xl font-display text-display-lg font-extrabold text-dark">
              ¿Listo para empezar?
            </p>
            <p class="mt-5 max-w-lg font-body text-cuerpo-lg text-dark/75">
              Elige tu membresía y trabaja desde el primer día en la comunidad emprendedora de Yucatán.
            </p>
          </div>

          <Boton :href="route('membresias')" variante="oscuro" tamano="lg" flecha class="shrink-0">
            Ver membresías
          </Boton>
        </div>
      </div>
    </section>

    <!-- 2 · Cinta de marca como separador -->
    <Marquee :palabras="palabras" tono="oscuro" compacto />

    <!-- 3 · Cuerpo oscuro -->
    <div class="bg-tinta">
      <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-20">
        <div class="grid gap-12 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Marca -->
          <div>
            <img
              src="/img/nodico/logo-nodico-blanco.png"
              alt="Nódico"
              width="180"
              height="58"
              loading="lazy"
              class="h-11 w-auto"
            />
            <p class="mt-6 max-w-xs font-body text-sm leading-relaxed text-white/60">
              El coworking del Instituto Yucateco de Emprendedores en Mérida: espacio, comunidad
              y contenido para quienes están construyendo algo propio.
            </p>

            <ul v-if="redes.length" class="mt-7 flex gap-3">
              <li v-for="red in redes" :key="red.label">
                <a
                  :href="red.href"
                  target="_blank"
                  rel="noopener noreferrer"
                  :aria-label="`Nódico en ${red.label}`"
                  class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 text-white
                         transition duration-300 ease-salida hover:-translate-y-1 hover:border-nodo-400
                         hover:bg-nodo-400 hover:text-dark"
                >
                  <component :is="red.icono" class="h-5 w-5" aria-hidden="true" />
                </a>
              </li>
            </ul>
          </div>

          <!-- Navegación -->
          <nav aria-labelledby="footer-nav">
            <h2 id="footer-nav" class="etiqueta-tecnica text-nodo-400">Navegación</h2>
            <ul class="mt-6 space-y-1">
              <li v-for="enlace in enlaces" :key="enlace.ruta">
                <Link
                  :href="route(enlace.ruta)"
                  class="flex min-h-[44px] items-center font-body text-sm text-white/70 transition hover:text-nodo-400"
                >
                  {{ enlace.label }}
                </Link>
              </li>
            </ul>
          </nav>

          <!-- Contacto -->
          <div>
            <h2 class="etiqueta-tecnica text-nodo-400">Contacto</h2>
            <ul class="mt-6 space-y-4">
              <li v-if="nodico.email" class="flex gap-3">
                <Mail class="mt-0.5 h-4 w-4 shrink-0 text-white/40" aria-hidden="true" />
                <a :href="`mailto:${nodico.email}`" class="font-body text-sm text-white/70 transition hover:text-nodo-400">
                  {{ nodico.email }}
                </a>
              </li>
              <li v-if="nodico.telefono" class="flex gap-3">
                <Phone class="mt-0.5 h-4 w-4 shrink-0 text-white/40" aria-hidden="true" />
                <a :href="`tel:${nodico.telefono.replace(/\s/g, '')}`" class="font-body text-sm text-white/70 transition hover:text-nodo-400">
                  {{ nodico.telefono }}
                </a>
              </li>
              <li v-if="nodico.direccion" class="flex gap-3">
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-white/40" aria-hidden="true" />
                <div>
                  <p class="font-body text-sm text-white/70">{{ nodico.direccion }}</p>
                  <a
                    v-if="nodico.mapsUrl"
                    :href="nodico.mapsUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-1 inline-flex min-h-[44px] items-center font-body text-sm text-nodo-400 underline-offset-4 hover:underline"
                  >
                    Ver en el mapa
                  </a>
                </div>
              </li>
              <li v-if="nodico.horarios" class="flex gap-3">
                <Clock class="mt-0.5 h-4 w-4 shrink-0 text-white/40" aria-hidden="true" />
                <p class="font-body text-sm text-white/70">{{ nodico.horarios }}</p>
              </li>
            </ul>
          </div>

          <!-- Facturación y aliados -->
          <div>
            <h2 class="etiqueta-tecnica text-nodo-400">Facturación</h2>
            <p class="mt-6 font-body text-sm leading-relaxed text-white/60">
              Para solicitar su factura, escriba a
              <a
                :href="`mailto:${nodico.email}?subject=Solicitud%20de%20factura`"
                class="text-nodo-400 underline underline-offset-2 hover:text-nodo-300"
              >{{ nodico.email }}</a>
              con el asunto “Solicitud de factura”, incluyendo sus datos fiscales completos.
            </p>

            <h2 class="etiqueta-tecnica mt-9 text-nodo-400">Aliados</h2>
            <ul class="mt-5 flex flex-wrap items-center gap-5">
              <li v-for="aliado in aliados" :key="aliado.nombre">
                <img
                  :src="aliado.logo"
                  :alt="aliado.nombre"
                  :width="aliado.ancho"
                  :height="aliado.alto"
                  loading="lazy"
                  class="h-7 w-auto object-contain opacity-45 brightness-0 invert transition hover:opacity-80"
                />
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- 4 · Barra inferior -->
      <div class="border-t border-white/10">
        <div class="pb-segura mx-auto flex max-w-7xl flex-col gap-6 px-5 pt-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
          <p class="max-w-2xl font-body text-xs leading-relaxed text-white/45">
            Nódico es una marca registrada del Instituto Yucateco de Emprendedores.
            Todos los derechos reservados. © {{ anio }}
          </p>

          <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <Link
              :href="route('privacidad')"
              class="flex min-h-[44px] items-center font-body text-xs text-white/55 transition hover:text-nodo-400"
            >
              Aviso de privacidad
            </Link>
            <Link
              :href="route('terminos')"
              class="flex min-h-[44px] items-center font-body text-xs text-white/55 transition hover:text-nodo-400"
            >
              Términos y condiciones
            </Link>

            <button
              type="button"
              class="group flex min-h-[44px] items-center gap-2 font-body text-xs text-white/55 transition hover:text-nodo-400"
              @click="volverArriba"
            >
              Volver arriba
              <ArrowUp
                class="h-4 w-4 transition-transform duration-200 ease-salida group-hover:-translate-y-1"
                aria-hidden="true"
              />
            </button>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
