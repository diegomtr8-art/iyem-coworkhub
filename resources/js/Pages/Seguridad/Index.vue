<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Monitor, ShieldCheck, ShieldAlert, LogOut, Link2 } from 'lucide-vue-next'
import { computed } from 'vue'

interface Sesion {
  id: string
  esActual: boolean
  ip: string | null
  dispositivo: string
  agente: string
  ultimaVez: string
}

interface Identidad {
  id: number
  etiqueta: string
  correo: string | null
  vinculadaEn: string | null
}

interface Evento {
  id: number
  etiqueta: string
  exito: boolean
  delicado: boolean
  ip: string | null
  agente: string | null
  cuando: string | null
}

const props = defineProps<{
  sesiones: Sesion[]
  sesionesLegibles: boolean
  eventos: Evento[]
  identidades: Identidad[]
  tieneContrasena: boolean
  metodosDeAcceso: number
}>()

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)

// La pantalla es la misma para el equipo y para los miembros; solo cambia el
// armazón de navegación que la envuelve.
const Layout = computed(() => (usuario.value?.esOperativo ? AuthenticatedLayout : PortalLayout))

const otras = computed(() => props.sesiones.filter((s) => !s.esActual))

function cerrarOtras() {
  router.delete(route('seguridad.cerrar-otras'), { preserveScroll: true })
}

function cerrarUna(id: string) {
  router.delete(route('seguridad.cerrar-una', { sesion: id }), { preserveScroll: true })
}

// El servidor vuelve a comprobarlo: esto solo evita ofrecer un botón que va a
// ser rechazado.
const puedeDesvincular = computed(() => props.metodosDeAcceso > 1)

function desvincular(id: number) {
  router.delete(route('seguridad.desvincular', { identidad: id }), { preserveScroll: true })
}
</script>

<template>
  <Head title="Mi seguridad" />

  <component :is="Layout">
    <div class="mx-auto max-w-3xl space-y-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Mi seguridad</h1>
        <p class="mt-1 text-sm text-gray-500">
          Dónde está abierta tu cuenta y qué ha pasado con ella.
        </p>
      </div>

      <!-- Sesiones abiertas -->
      <section class="rounded-2xl border border-gray-200 bg-white">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
          <div class="flex items-center gap-2">
            <Monitor class="h-5 w-5 text-gray-400" aria-hidden="true" />
            <h2 class="font-semibold text-gray-900">Sesiones abiertas</h2>
          </div>

          <button
            v-if="otras.length"
            type="button"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-gray-700"
            @click="cerrarOtras"
          >
            <LogOut class="h-4 w-4" aria-hidden="true" />
            Cerrar las demás
          </button>
        </header>

        <p v-if="!sesionesLegibles" class="px-5 py-6 text-sm text-gray-500">
          No podemos mostrar tus sesiones porque el sistema no las está guardando en la
          base de datos. Avísanos y lo revisamos.
        </p>

        <ul v-else class="divide-y divide-gray-100">
          <li
            v-for="sesion in sesiones"
            :key="sesion.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
          >
            <div class="min-w-0">
              <p class="flex items-center gap-2 font-medium text-gray-900">
                {{ sesion.dispositivo }}
                <span
                  v-if="sesion.esActual"
                  class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                >Este dispositivo</span>
              </p>
              <p class="mt-0.5 truncate text-sm text-gray-500">
                {{ sesion.ip || 'Sin IP' }} · {{ sesion.ultimaVez }}
              </p>
            </div>

            <button
              v-if="!sesion.esActual"
              type="button"
              class="min-h-[44px] px-3 text-sm font-semibold text-red-600 transition hover:text-red-800"
              @click="cerrarUna(sesion.id)"
            >
              Cerrar
            </button>
          </li>

          <li v-if="!sesiones.length" class="px-5 py-6 text-sm text-gray-500">
            No hay sesiones registradas.
          </li>
        </ul>
      </section>

      <!-- Cuentas externas vinculadas -->
      <section class="rounded-2xl border border-gray-200 bg-white">
        <header class="flex items-center gap-2 border-b border-gray-100 px-5 py-4">
          <Link2 class="h-5 w-5 text-gray-400" aria-hidden="true" />
          <h2 class="font-semibold text-gray-900">Cómo entras a tu cuenta</h2>
        </header>

        <ul class="divide-y divide-gray-100">
          <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div>
              <p class="font-medium text-gray-900">Correo y contraseña</p>
              <p class="mt-0.5 text-sm text-gray-500">
                {{ tieneContrasena ? 'Activo' : 'Sin contraseña: entras solo con tu cuenta externa' }}
              </p>
            </div>
            <span
              v-if="!tieneContrasena"
              class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700"
            >Puedes poner una desde tu perfil</span>
          </li>

          <li
            v-for="identidad in identidades"
            :key="identidad.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
          >
            <div class="min-w-0">
              <p class="font-medium text-gray-900">{{ identidad.etiqueta }}</p>
              <p class="mt-0.5 truncate text-sm text-gray-500">
                {{ identidad.correo }}
                <span v-if="identidad.vinculadaEn"> · desde {{ identidad.vinculadaEn }}</span>
              </p>
            </div>

            <button
              type="button"
              class="min-h-[44px] px-3 text-sm font-semibold transition disabled:cursor-not-allowed"
              :class="puedeDesvincular ? 'text-red-600 hover:text-red-800' : 'text-gray-400'"
              :disabled="!puedeDesvincular"
              :title="puedeDesvincular ? undefined : 'Es tu única forma de entrar'"
              @click="desvincular(identidad.id)"
            >
              Desvincular
            </button>
          </li>
        </ul>

        <p v-if="!puedeDesvincular" class="border-t border-gray-100 px-5 py-3 text-sm text-gray-500">
          Solo tienes una forma de entrar, así que no se puede quitar. Añade otra antes.
        </p>
      </section>

      <!-- Bitácora propia -->
      <section class="rounded-2xl border border-gray-200 bg-white">
        <header class="flex items-center gap-2 border-b border-gray-100 px-5 py-4">
          <ShieldCheck class="h-5 w-5 text-gray-400" aria-hidden="true" />
          <h2 class="font-semibold text-gray-900">Actividad de tu cuenta</h2>
        </header>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
              <tr>
                <th scope="col" class="px-5 py-3 font-semibold">Qué pasó</th>
                <th scope="col" class="px-5 py-3 font-semibold">Cuándo</th>
                <th scope="col" class="px-5 py-3 font-semibold">Desde</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="evento in eventos" :key="evento.id">
                <td class="px-5 py-3">
                  <span class="flex items-center gap-2 font-medium text-gray-900">
                    <ShieldAlert
                      v-if="evento.delicado"
                      class="h-4 w-4 shrink-0 text-amber-500"
                      aria-hidden="true"
                    />
                    {{ evento.etiqueta }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ evento.cuando }}</td>
                <td class="px-5 py-3 text-gray-500">{{ evento.ip || '—' }}</td>
              </tr>

              <tr v-if="!eventos.length">
                <td colspan="3" class="px-5 py-6 text-gray-500">Todavía no hay actividad registrada.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </component>
</template>
