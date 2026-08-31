<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { Clock, Loader2, Mail, MapPin, Phone } from 'lucide-vue-next'
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue'

const page = usePage()
const nodico = computed(() => (page.props.nodico ?? {}) as any)

const form = useForm({
  nombre: '',
  telefono: '',
  email: '',
  empresa: '',
  asunto: '',
  comentarios: '',
  // Trampa antibots: si viene con contenido, el servidor descarta el envío.
  sitio_web: '',
})

const campos = [
  { name: 'nombre',   label: 'Nombre',     type: 'text',  autocomplete: 'name',         inputmode: 'text',  requerido: true },
  { name: 'telefono', label: 'Teléfono',   type: 'tel',   autocomplete: 'tel',          inputmode: 'tel',   requerido: false },
  { name: 'email',    label: 'E-mail',     type: 'email', autocomplete: 'email',        inputmode: 'email', requerido: true },
  { name: 'empresa',  label: 'Su empresa', type: 'text',  autocomplete: 'organization', inputmode: 'text',  requerido: false },
  { name: 'asunto',   label: 'Asunto',     type: 'text',  autocomplete: 'off',          inputmode: 'text',  requerido: false },
] as const

// G.1 — el iframe de Google se monta cuando la sección de contacto **se acerca**
// a la pantalla (IntersectionObserver), no en el primer pintado: como esta
// sección va al final de cada página, en la práctica el mapa se ve siempre que
// alguien llega aquí, pero nunca compite con la carga inicial. La altura queda
// reservada abajo para que el layout no salte al montarlo.
const mapaActivo = ref(false)
const mapaRef = ref<HTMLElement | null>(null)
let observador: IntersectionObserver | null = null

onMounted(() => {
  if (!mapaRef.value) return
  if (typeof IntersectionObserver === 'undefined') {
    mapaActivo.value = true // navegador viejo: se carga sin más
    return
  }
  observador = new IntersectionObserver((entradas) => {
    if (entradas.some((e) => e.isIntersecting)) {
      mapaActivo.value = true
      observador?.disconnect()
    }
  }, { rootMargin: '300px' }) // se adelanta para que ya esté al llegar
  observador.observe(mapaRef.value)
})

onBeforeUnmount(() => observador?.disconnect())

const tocado = reactive<Record<string, boolean>>({})
const enviado = ref(false)

/** Validación en vivo; el servidor sigue siendo la fuente de verdad. */
function validar(campo: string): string | null {
  const valor = (form as any)[campo]?.trim() ?? ''

  if (campo === 'nombre') {
    if (!valor) return 'Escribe tu nombre.'
    if (valor.length > 100) return 'Máximo 100 caracteres.'
  }
  if (campo === 'email') {
    if (!valor) return 'Escribe tu correo.'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor)) return 'Ese correo no parece válido.'
  }
  if (campo === 'telefono' && valor && valor.replace(/\D/g, '').length < 7) {
    return 'El teléfono parece incompleto.'
  }
  if (campo === 'comentarios') {
    if (!valor) return 'Cuéntanos qué necesitas.'
    if (valor.length > 2000) return 'Máximo 2000 caracteres.'
  }
  return null
}

/** Muestra el error del servidor, o el local solo si el campo ya se tocó. */
function errorDe(campo: string): string | null {
  if ((form.errors as any)[campo]) return (form.errors as any)[campo]
  return tocado[campo] ? validar(campo) : null
}

const hayErrores = computed(() =>
  ['nombre', 'email', 'comentarios'].some((c) => validar(c) !== null),
)

const contactoOk = computed(() => (page.props.flash as any)?.contacto_ok)

watch(contactoOk, (ok) => {
  if (ok) enviado.value = true
})

function enviar() {
  ;['nombre', 'email', 'comentarios'].forEach((c) => (tocado[c] = true))
  if (hayErrores.value) return

  form.post(route('contacto.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      Object.keys(tocado).forEach((k) => (tocado[k] = false))
    },
  })
}

const telefonoHref = computed(
  () => `tel:${nodico.value.telefonoE164 ?? (nodico.value.telefono ?? '').replace(/\s/g, '')}`,
)
</script>

<template>
  <section id="hablemos" class="relative bg-cream py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="max-w-2xl">
        <p class="etiqueta-tecnica mb-5 flex items-center gap-3 text-dark/70">
          <span class="h-1.5 w-1.5 rounded-full bg-nodo-400" aria-hidden="true" />
          Contacto
        </p>

        <h2 class="font-display text-display-lg font-extrabold text-dark">Hablemos</h2>

        <p class="mt-6 font-body text-cuerpo-lg text-dark/70">
          ¿Quieres conocer el espacio, cotizar un salón o resolver una duda sobre las
          membresías? Escríbenos y te respondemos a la brevedad.
        </p>
      </div>

      <!-- 1 · Franja de datos: cuatro columnas iguales, 2x2 en iPhone -->
      <dl class="mt-12 grid grid-cols-2 gap-px overflow-hidden rounded-3xl bg-dark/10 lg:grid-cols-4">
        <div v-if="nodico.email" class="flex flex-col gap-2 bg-cream p-6">
          <dt class="etiqueta-tecnica flex items-center gap-2 text-dark/70">
            <Mail class="h-4 w-4" aria-hidden="true" /> Correo
          </dt>
          <dd>
            <a :href="`mailto:${nodico.email}`" class="font-body text-sm text-dark underline-offset-4 hover:underline">
              {{ nodico.email }}
            </a>
          </dd>
        </div>

        <div v-if="nodico.telefono" class="flex flex-col gap-2 bg-cream p-6">
          <dt class="etiqueta-tecnica flex items-center gap-2 text-dark/70">
            <Phone class="h-4 w-4" aria-hidden="true" /> Teléfono
          </dt>
          <dd>
            <a :href="telefonoHref" class="font-body text-sm text-dark underline-offset-4 hover:underline">
              {{ nodico.telefono }}
            </a>
          </dd>
        </div>

        <div v-if="nodico.direccion" class="flex flex-col gap-2 bg-cream p-6">
          <dt class="etiqueta-tecnica flex items-center gap-2 text-dark/70">
            <MapPin class="h-4 w-4" aria-hidden="true" /> Dirección
          </dt>
          <dd class="font-body text-sm leading-relaxed text-dark">
            {{ nodico.direccionCorta ?? nodico.direccion }}
            <a
              v-if="nodico.mapsUrl"
              :href="nodico.mapsUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-1 block font-medium text-dark underline underline-offset-4"
            >Cómo llegar</a>
          </dd>
        </div>

        <div v-if="nodico.horarios" class="flex flex-col gap-2 bg-cream p-6">
          <dt class="etiqueta-tecnica flex items-center gap-2 text-dark/70">
            <Clock class="h-4 w-4" aria-hidden="true" /> Horarios
          </dt>
          <dd class="font-body text-sm text-dark">
            {{ nodico.horarios }}
            <span v-if="nodico.horariosDetalle" class="mt-0.5 block text-dark/70">
              {{ nodico.horariosDetalle }}
            </span>
          </dd>
        </div>
      </dl>

      <!-- 2 · Formulario y mapa a la misma altura -->
      <div class="mt-6 grid items-stretch gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="rounded-3xl bg-white p-6 shadow-sombra-lg ring-1 ring-dark/[.07] sm:p-9">
          <!-- A11Y-06: `relative` para que el honeypot en left:-9999px se
               posicione contra el formulario y no contra un ancestro incierto. -->
          <form class="relative" novalidate @submit.prevent="enviar">
            <div class="absolute left-[-9999px]" aria-hidden="true">
              <label for="contacto-sitio-web">No llenar</label>
              <input id="contacto-sitio-web" v-model="form.sitio_web" type="text" tabindex="-1" autocomplete="off" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
              <div v-for="campo in campos" :key="campo.name" class="relative">
                <input
                  :id="`contacto-${campo.name}`"
                  v-model="(form as any)[campo.name]"
                  :type="campo.type"
                  :inputmode="campo.inputmode"
                  :autocomplete="campo.autocomplete"
                  :aria-invalid="errorDe(campo.name) ? 'true' : undefined"
                  :aria-describedby="errorDe(campo.name) ? `error-${campo.name}` : undefined"
                  placeholder=" "
                  class="peer min-h-[60px] w-full rounded-xl border bg-cream-50 px-4 pb-2.5 pt-7 font-body text-dark
                         transition-colors duration-200 focus:bg-white focus:outline-none focus:ring-0"
                  :class="errorDe(campo.name) ? 'border-red-500 focus:border-red-500' : 'border-dark/15 focus:border-nodo-500'"
                  @blur="tocado[campo.name] = true"
                />
                <label
                  :for="`contacto-${campo.name}`"
                  class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-body text-dark/70
                         transition-all duration-200 ease-salida
                         peer-focus:top-3 peer-focus:translate-y-0 peer-focus:text-xs peer-focus:text-dark/70
                         peer-[:not(:placeholder-shown)]:top-3
                         peer-[:not(:placeholder-shown)]:translate-y-0
                         peer-[:not(:placeholder-shown)]:text-xs"
                >
                  {{ campo.label }}<span v-if="campo.requerido" aria-hidden="true"> *</span>
                </label>

                <p v-if="errorDe(campo.name)" :id="`error-${campo.name}`" class="mt-1.5 font-body text-sm text-red-600">
                  {{ errorDe(campo.name) }}
                </p>
              </div>

              <div class="relative sm:col-span-2">
                <textarea
                  id="contacto-comentarios"
                  v-model="form.comentarios"
                  rows="5"
                  placeholder=" "
                  :aria-invalid="errorDe('comentarios') ? 'true' : undefined"
                  :aria-describedby="errorDe('comentarios') ? 'error-comentarios' : undefined"
                  class="peer w-full resize-y rounded-xl border bg-cream-50 px-4 pb-3 pt-7 font-body text-dark
                         transition-colors duration-200 focus:bg-white focus:outline-none focus:ring-0"
                  :class="errorDe('comentarios') ? 'border-red-500 focus:border-red-500' : 'border-dark/15 focus:border-nodo-500'"
                  @blur="tocado.comentarios = true"
                />
                <label
                  for="contacto-comentarios"
                  class="pointer-events-none absolute left-4 top-6 -translate-y-1/2 font-body text-dark/70
                         transition-all duration-200 ease-salida
                         peer-focus:top-4 peer-focus:text-xs peer-focus:text-dark/70
                         peer-[:not(:placeholder-shown)]:top-4
                         peer-[:not(:placeholder-shown)]:text-xs"
                >
                  Comentarios<span aria-hidden="true"> *</span>
                </label>

                <p v-if="errorDe('comentarios')" id="error-comentarios" class="mt-1.5 font-body text-sm text-red-600">
                  {{ errorDe('comentarios') }}
                </p>
              </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center gap-5">
              <button
                type="submit"
                :disabled="form.processing"
                class="group inline-flex min-h-[56px] items-center justify-center gap-2.5 rounded-xl
                       bg-nodo-400 px-9 py-4 font-display text-base font-bold text-dark shadow-sombra-sm
                       transition-all duration-300 ease-salida hover:-translate-y-0.5 hover:shadow-sombra
                       disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
              >
                <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                {{ form.processing ? 'Enviando…' : 'Enviar mensaje' }}
                <span v-if="!form.processing" class="transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">→</span>
              </button>

              <p v-if="enviado && !form.processing" role="status" class="font-body text-sm font-medium text-dark">
                ✓ Mensaje enviado. Te contactamos pronto.
              </p>
            </div>
          </form>
        </div>

        <!-- G.1 — el mapa se carga solo al acercarse (ver el observer arriba). La
             altura queda reservada para que no salte el layout. -->
        <div
          v-if="nodico.mapsEmbed"
          ref="mapaRef"
          class="min-h-[320px] overflow-hidden rounded-3xl shadow-sombra ring-1 ring-dark/[.07]"
        >
          <iframe
            v-if="mapaActivo"
            :src="nodico.mapsEmbed"
            title="Ubicación de Nódico en Google Maps"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            class="h-full min-h-[320px] w-full border-0"
          />

          <!-- Marcador de posición mientras el mapa entra en pantalla. -->
          <div
            v-else
            class="flex h-full min-h-[320px] w-full flex-col items-center justify-center gap-3 bg-cream-200"
            aria-hidden="true"
          >
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-nodo-400">
              <MapPin class="h-6 w-6 text-dark" />
            </span>
            <span class="font-body text-sm text-dark/60">Cargando el mapa…</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
