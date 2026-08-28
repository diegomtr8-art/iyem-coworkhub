<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Facebook, Instagram, Linkedin } from 'lucide-vue-next'

const enlaces = [
  { label: 'Inicio', ruta: 'home' },
  { label: 'Nosotros', ruta: 'nosotros' },
  { label: 'Membresías', ruta: 'membresias' },
  { label: 'Eventos', ruta: 'eventos' },
  { label: 'Actividades', ruta: 'actividades' },
]

const redes = [
  { label: 'Instagram', href: 'https://www.instagram.com/nodicomx', icono: Instagram },
  { label: 'Facebook', href: 'https://www.facebook.com/nodicomx', icono: Facebook },
  { label: 'LinkedIn', href: 'https://www.linkedin.com/in/nodico-club-de-emprendedores-316513377/', icono: Linkedin },
]

const anio = new Date().getFullYear()
</script>

<template>
  <footer class="bg-dark text-white">
    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:py-16">
      <div class="grid gap-12 lg:grid-cols-3">
        <div>
          <img
            src="/img/nodico/logo-nodico-blanco.png"
            alt="Nódico"
            width="180"
            height="58"
            loading="lazy"
            class="h-12 w-auto"
          />
        </div>

        <nav aria-labelledby="footer-nav">
          <h2 id="footer-nav" class="font-display text-sm font-bold uppercase tracking-widest text-nodo-400">
            Navegación
          </h2>
          <ul class="mt-5 space-y-3">
            <li v-for="enlace in enlaces" :key="enlace.ruta">
              <Link
                :href="route(enlace.ruta)"
                class="font-body text-sm text-white/80 transition hover:text-nodo-400
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-nodo-400"
              >
                {{ enlace.label }}
              </Link>
            </li>
          </ul>
        </nav>

        <div>
          <h2 class="font-display text-sm font-bold uppercase tracking-widest text-nodo-400">Síganos</h2>
          <ul class="mt-5 flex gap-4">
            <li v-for="red in redes" :key="red.label">
              <a
                :href="red.href"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="`Nódico en ${red.label}`"
                class="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 transition
                       hover:border-nodo-400 hover:text-nodo-400
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
              >
                <component :is="red.icono" class="h-5 w-5" aria-hidden="true" />
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-14 space-y-4 border-t border-white/15 pt-8 font-body text-xs leading-relaxed text-white/65">
        <p>
          En caso de requerir la emisión de su factura, le solicitamos enviar un correo electrónico a
          <a
            href="mailto:contacto@nodico.com.mx?subject=Solicitud%20de%20factura"
            class="text-nodo-400 underline underline-offset-2 hover:text-nodo-300
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nodo-400"
          >contacto@nodico.com.mx</a>
          con el asunto “Solicitud de factura”, incluyendo sus datos fiscales completos para su correcta elaboración.
        </p>
        <p>
          Nódico es una marca registrada del Instituto Yucateco de Emprendedores.
          Todos los derechos reservados. © {{ anio }}
        </p>
      </div>
    </div>
  </footer>
</template>
