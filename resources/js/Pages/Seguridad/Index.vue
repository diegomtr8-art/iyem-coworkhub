<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { Monitor, ShieldCheck, ShieldAlert, LogOut, Link2, KeyRound, Smartphone, Mail, Clock } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import ListaResponsiva, { type Columna } from '@/Components/Panel/ListaResponsiva.vue'

interface Sesion {
  /** Referencia opaca; el identificador de sesion nunca sale del servidor. */
  ref: string
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

interface DosFactores {
  activo: boolean
  obligatorio: boolean
  desde: string | null
  codigosSinUsar: number
}

interface Dispositivo {
  id: number
  ip: string | null
  caduca: string
  desde: string | null
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

interface Correo {
  actual: string
  verificado: boolean
  pendiente: string | null
}

const props = defineProps<{
  correo: Correo
  sesiones: Sesion[]
  sesionesLegibles: boolean
  eventos: Evento[]
  identidades: Identidad[]
  tieneContrasena: boolean
  metodosDeAcceso: number
  dosFactores: DosFactores
  dispositivosConfiables: Dispositivo[]
}>()

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)
const flashStatus = computed(() => (page.props.flash as any)?.status ?? page.props.status ?? null)

// ── Cambio de correo ────────────────────────────────────────────────────────
const editandoCorreo = ref(false)
const formCorreo = useForm({ email: '' })

function solicitarCorreo() {
  formCorreo.post(route('seguridad.correo.solicitar'), {
    preserveScroll: true,
    onSuccess: () => {
      formCorreo.reset()
      editandoCorreo.value = false
    },
  })
}

function cancelarCorreo() {
  router.delete(route('seguridad.correo.cancelar'), { preserveScroll: true })
}

// La pantalla es la misma para el equipo y para los miembros; solo cambia el
// armazón de navegación que la envuelve.
const Layout = computed(() => (usuario.value?.esOperativo ? AuthenticatedLayout : PortalLayout))

const otras = computed(() => props.sesiones.filter((s) => !s.esActual))

function cerrarOtras() {
  router.delete(route('seguridad.cerrar-otras'), { preserveScroll: true })
}

function cerrarUna(ref: string) {
  router.delete(route('seguridad.cerrar-una', { sesion: ref }), { preserveScroll: true })
}

// El servidor vuelve a comprobarlo: esto solo evita ofrecer un botón que va a
// ser rechazado.
const puedeDesvincular = computed(() => props.metodosDeAcceso > 1)

function desvincular(id: number) {
  router.delete(route('seguridad.desvincular', { identidad: id }), { preserveScroll: true })
}

function apagarDosFactores() {
  router.delete(route('dos-factores.destruir'), { preserveScroll: true })
}

function olvidarDispositivo(id: number) {
  router.delete(route('seguridad.olvidar-dispositivo', { dispositivo: id }), { preserveScroll: true })
}

/**
 * Bitácora propia: en pantalla ancha, tabla; en móvil, tarjetas apiladas (nada
 * de scroll horizontal). «Qué pasó» encabeza; «Cuándo» se ve siempre; la IP
 * («Desde») queda tras «Ver detalle».
 */
const columnasBitacora: Columna[] = [
  { clave: 'que', etiqueta: 'Qué pasó', rol: 'identidad' },
  { clave: 'cuando', etiqueta: 'Cuándo', rol: 'resumen' },
  { clave: 'ip', etiqueta: 'Desde', rol: 'detalle' },
]
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

      <!-- Correo electrónico -->
      <section id="correo" class="rounded-2xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 p-5">
          <div class="flex items-center gap-2">
            <Mail :size="18" class="text-gray-500" aria-hidden="true" />
            <h2 class="font-semibold text-gray-900">Correo electrónico</h2>
          </div>
        </div>

        <div class="p-5">
          <!-- Avisos de resultado -->
          <p
            v-if="flashStatus === 'cambio-correo-enviado'"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
          >
            Te enviamos un enlace a tu dirección nueva para confirmarla. Tu correo
            actual sigue funcionando hasta entonces.
          </p>
          <p
            v-else-if="flashStatus === 'correo-cambiado'"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
          >
            Tu correo se cambió correctamente.
          </p>
          <p
            v-else-if="flashStatus === 'cambio-correo-cancelado'"
            class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
            role="status"
          >
            Cancelamos la solicitud de cambio de correo.
          </p>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="font-medium text-gray-900">{{ correo.actual }}</p>
              <p class="mt-0.5 text-sm" :class="correo.verificado ? 'text-emerald-600' : 'text-amber-600'">
                {{ correo.verificado ? 'Verificado' : 'Sin verificar' }}
              </p>
            </div>
            <button
              v-if="!editandoCorreo && !correo.pendiente"
              type="button"
              @click="editandoCorreo = true"
              class="inline-flex min-h-[44px] items-center rounded-xl border-2 border-dark px-4 text-sm font-semibold text-dark transition hover:bg-dark hover:text-white"
            >
              Cambiar correo
            </button>
          </div>

          <!-- Solicitud pendiente -->
          <div
            v-if="correo.pendiente"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
          >
            <div class="flex items-start gap-2">
              <Clock :size="16" class="mt-0.5 shrink-0 text-amber-600" aria-hidden="true" />
              <p class="text-sm text-amber-900">
                Pendiente de confirmar: <strong>{{ correo.pendiente }}</strong>.
                Revisa esa bandeja y pulsa el enlace que te enviamos.
              </p>
            </div>
            <button
              type="button"
              @click="cancelarCorreo"
              class="inline-flex min-h-[44px] shrink-0 items-center text-sm font-semibold text-amber-800 underline hover:text-amber-900"
            >
              Cancelar
            </button>
          </div>

          <!-- Formulario -->
          <form v-if="editandoCorreo && !correo.pendiente" @submit.prevent="solicitarCorreo" class="mt-4 space-y-3">
            <p class="text-sm text-gray-600">
              Te pediremos tu contraseña y enviaremos un enlace de confirmación a la
              dirección nueva. El cambio no se aplica hasta que la confirmes.
            </p>
            <div>
              <label for="email-nuevo" class="block text-sm font-medium text-gray-700">Nuevo correo</label>
              <input
                id="email-nuevo"
                v-model="formCorreo.email"
                type="email"
                autocomplete="email"
                required
                class="mt-1 block w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-nodo-400 focus:ring-nodo-400"
                placeholder="tu-nuevo-correo@ejemplo.com"
              />
              <p v-if="formCorreo.errors.email" class="mt-1 text-sm text-red-600">{{ formCorreo.errors.email }}</p>
            </div>
            <div class="flex items-center gap-3">
              <button
                type="submit"
                :disabled="formCorreo.processing"
                class="inline-flex min-h-[44px] items-center rounded-xl bg-dark px-4 text-sm font-semibold text-white transition hover:bg-dark/90 disabled:opacity-50"
              >
                Enviar confirmación
              </button>
              <button
                type="button"
                @click="editandoCorreo = false; formCorreo.reset()"
                class="inline-flex min-h-[44px] items-center px-2 text-sm font-medium text-gray-500 hover:text-gray-700"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>
      </section>

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
            :key="sesion.ref"
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
              @click="cerrarUna(sesion.ref)"
            >
              Cerrar
            </button>
          </li>

          <li v-if="!sesiones.length" class="px-5 py-6 text-sm text-gray-500">
            No hay sesiones registradas.
          </li>
        </ul>
      </section>

      <!-- Segundo factor -->
      <section class="rounded-2xl border border-gray-200 bg-white">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
          <div class="flex items-center gap-2">
            <KeyRound class="h-5 w-5 text-gray-400" aria-hidden="true" />
            <h2 class="font-semibold text-gray-900">Segundo factor</h2>
          </div>

          <span
            v-if="dosFactores.activo"
            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
          >Activo desde {{ dosFactores.desde }}</span>
        </header>

        <div class="px-5 py-4">
          <template v-if="dosFactores.activo">
            <p class="text-sm text-gray-500">
              Al entrar te pedimos un código de tu app además de la contraseña. Te quedan
              <strong class="text-gray-900">{{ dosFactores.codigosSinUsar }}</strong>
              códigos de recuperación sin usar.
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
              <Link
                :href="route('dos-factores.codigos')"
                method="post"
                as="button"
                class="inline-flex min-h-[44px] items-center rounded-xl border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:border-gray-400"
              >
                Generar códigos nuevos
              </Link>

              <button
                v-if="!dosFactores.obligatorio"
                type="button"
                class="inline-flex min-h-[44px] items-center px-3 text-sm font-semibold text-red-600 transition hover:text-red-800"
                @click="apagarDosFactores"
              >
                Desactivar
              </button>

              <span
                v-else
                class="inline-flex min-h-[44px] items-center text-sm text-gray-500"
              >Tu perfil lo exige, así que no se puede desactivar.</span>
            </div>
          </template>

          <template v-else>
            <!--
              Se recomienda, no se impone: Nódico lo decidió opcional. El aviso
              queda visible mientras no esté activo, que es lo que pedía la
              fase G para el panel operativo.
            -->
            <p class="text-sm text-gray-500">
              Con el segundo factor activo, saber tu contraseña deja de ser suficiente para
              entrar a tu cuenta. Funciona con Google Authenticator, Authy o 1Password.
            </p>

            <Link
              :href="route('dos-factores.crear')"
              class="mt-4 inline-flex min-h-[44px] items-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-gray-700"
            >
              Activar el segundo factor
            </Link>
          </template>
        </div>

        <!-- Equipos de confianza -->
        <div v-if="dispositivosConfiables.length" class="border-t border-gray-100">
          <p class="flex items-center gap-2 px-5 pt-4 text-sm font-semibold text-gray-900">
            <Smartphone class="h-4 w-4 text-gray-400" aria-hidden="true" />
            Equipos que no vuelven a pedir el código
          </p>

          <ul class="mt-2 divide-y divide-gray-100">
            <li
              v-for="equipo in dispositivosConfiables"
              :key="equipo.id"
              class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
            >
              <p class="text-sm text-gray-500">
                {{ equipo.ip || 'Sin IP' }} · desde {{ equipo.desde }} · caduca el {{ equipo.caduca }}
              </p>

              <button
                type="button"
                class="min-h-[44px] px-3 text-sm font-semibold text-red-600 transition hover:text-red-800"
                @click="olvidarDispositivo(equipo.id)"
              >
                Quitar confianza
              </button>
            </li>
          </ul>
        </div>
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

        <ListaResponsiva
          :columnas="columnasBitacora"
          :filas="eventos"
          vacio="Todavía no hay actividad registrada."
        >
          <template #que="{ fila: e }">
            <span class="flex items-center gap-2 font-medium text-gray-900">
              <ShieldAlert v-if="e.delicado" class="h-4 w-4 shrink-0 text-amber-500" aria-hidden="true" />
              {{ e.etiqueta }}
            </span>
          </template>
          <template #cuando="{ fila: e }"><span class="whitespace-nowrap text-gray-500">{{ e.cuando }}</span></template>
          <template #ip="{ fila: e }"><span class="text-gray-500">{{ e.ip || '—' }}</span></template>
        </ListaResponsiva>
      </section>
    </div>
  </component>
</template>
