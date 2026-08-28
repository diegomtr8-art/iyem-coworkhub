<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { Clock, Facebook, Instagram, Linkedin, Loader2, Mail, MapPin, Phone } from 'lucide-vue-next'
import { computed, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'

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
  if (!ok) return
  enviado.value = true
  toast.success('¡Gracias! Recibimos tu mensaje.')
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
    onError: () => toast.error('Revisa los campos marcados e inténtalo de nuevo.'),
  })
}

const redes = computed(() => [
  { label: 'Instagram', href: nodico.value.redes?.instagram, icono: Instagram },
  { label: 'Facebook',  href: nodico.value.redes?.facebook,  icono: Facebook },
  { label: 'LinkedIn',  href: nodico.value.redes?.linkedin,  icono: Linkedin },
].filter((r) => r.href))
</script>

<template>
  <section id="hablemos" class="relative bg-cream py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="grid gap-12 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-16">
        <!-- Columna de datos -->
        <div>
          <div class="mb-6 flex items-center gap-4">
            <p class="etiqueta-tecnica shrink-0 text-dark/55">Contacto</p>
            <span class="h-px flex-1 bg-dark/15" aria-hidden="true" />
          </div>

          <h2 class="font-display text-display-lg font-extrabold text-dark">Hablemos</h2>

          <p class="mt-6 max-w-md font-body text-cuerpo-lg text-dark/70">
            ¿Quieres conocer el espacio, cotizar un salón o resolver una duda sobre las
            membresías? Escríbenos y te respondemos a la brevedad.
          </p>

          <dl class="mt-10 space-y-6">
            <div v-if="nodico.email" class="flex gap-4">
              <Mail class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <dt class="etiqueta-tecnica text-dark/50">Correo</dt>
                <dd class="mt-1.5">
                  <a :href="`mailto:${nodico.email}`" class="font-body text-dark underline-offset-4 hover:underline">
                    {{ nodico.email }}
                  </a>
                </dd>
              </div>
            </div>

            <div v-if="nodico.telefono" class="flex gap-4">
              <Phone class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <dt class="etiqueta-tecnica text-dark/50">Teléfono</dt>
                <dd class="mt-1.5">
                  <a :href="`tel:${nodico.telefono.replace(/\s/g, '')}`" class="font-body text-dark underline-offset-4 hover:underline">
                    {{ nodico.telefono }}
                  </a>
                </dd>
              </div>
            </div>

            <div v-if="nodico.direccion" class="flex gap-4">
              <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <dt class="etiqueta-tecnica text-dark/50">Dirección</dt>
                <dd class="mt-1.5 font-body text-dark">{{ nodico.direccion }}</dd>
                <dd v-if="nodico.mapsUrl" class="mt-3">
                  <a
                    :href="nodico.mapsUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-[44px] items-center gap-2 border-2 border-dark px-4 py-2
                           font-display text-sm font-bold text-dark transition hover:bg-dark hover:text-nodo-400"
                  >
                    Ver en Google Maps
                  </a>
                </dd>
              </div>
            </div>

            <div v-if="nodico.horarios" class="flex gap-4">
              <Clock class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
              <div>
                <dt class="etiqueta-tecnica text-dark/50">Horarios</dt>
                <dd class="mt-1.5 font-body text-dark">{{ nodico.horarios }}</dd>
              </div>
            </div>
          </dl>

          <ul v-if="redes.length" class="mt-10 flex gap-3">
            <li v-for="red in redes" :key="red.label">
              <a
                :href="red.href"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="`Nódico en ${red.label}`"
                class="flex h-12 w-12 items-center justify-center border-2 border-dark text-dark
                       transition duration-200 ease-salida hover:bg-dark hover:text-nodo-400"
              >
                <component :is="red.icono" class="h-5 w-5" aria-hidden="true" />
              </a>
            </li>
          </ul>
        </div>

        <!-- Tarjeta elevada del formulario -->
        <div class="border-2 border-dark bg-white p-6 shadow-dura-lg sm:p-9">
          <form novalidate @submit.prevent="enviar">
            <!-- Trampa antibots: fuera de pantalla, nunca enfocable -->
            <div class="absolute left-[-9999px]" aria-hidden="true">
              <label for="contacto-sitio-web">No llenar</label>
              <input
                id="contacto-sitio-web"
                v-model="form.sitio_web"
                type="text"
                tabindex="-1"
                autocomplete="off"
              />
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
                  class="peer min-h-[60px] w-full border-2 bg-white px-4 pb-2.5 pt-7 font-body text-dark
                         transition-colors duration-200 focus:outline-none focus:ring-0"
                  :class="errorDe(campo.name)
                    ? 'border-red-500 focus:border-red-500'
                    : 'border-dark/25 focus:border-nodo-500'"
                  @blur="tocado[campo.name] = true"
                />
                <label
                  :for="`contacto-${campo.name}`"
                  class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-body text-dark/50
                         transition-all duration-200 ease-salida
                         peer-focus:top-3 peer-focus:translate-y-0 peer-focus:text-xs peer-focus:text-dark/60
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

              <!-- Comentarios ocupa el ancho completo -->
              <div class="relative sm:col-span-2">
                <textarea
                  id="contacto-comentarios"
                  v-model="form.comentarios"
                  rows="5"
                  placeholder=" "
                  :aria-invalid="errorDe('comentarios') ? 'true' : undefined"
                  :aria-describedby="errorDe('comentarios') ? 'error-comentarios' : undefined"
                  class="peer w-full resize-y border-2 bg-white px-4 pb-3 pt-7 font-body text-dark
                         transition-colors duration-200 focus:outline-none focus:ring-0"
                  :class="errorDe('comentarios')
                    ? 'border-red-500 focus:border-red-500'
                    : 'border-dark/25 focus:border-nodo-500'"
                  @blur="tocado.comentarios = true"
                />
                <label
                  for="contacto-comentarios"
                  class="pointer-events-none absolute left-4 top-6 -translate-y-1/2 font-body text-dark/50
                         transition-all duration-200 ease-salida
                         peer-focus:top-4 peer-focus:text-xs peer-focus:text-dark/60
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
                class="group inline-flex min-h-[56px] items-center justify-center gap-2.5 border-2 border-dark
                       bg-nodo-400 px-9 py-4 font-display text-base font-bold text-dark shadow-dura-sm
                       transition-all duration-200 ease-salida
                       hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none
                       disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-x-0 disabled:hover:translate-y-0"
              >
                <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                {{ form.processing ? 'Enviando…' : 'Enviar mensaje' }}
                <span
                  v-if="!form.processing"
                  class="transition-transform duration-200 group-hover:translate-x-1"
                  aria-hidden="true"
                >→</span>
              </button>

              <p v-if="enviado && !form.processing" role="status" class="font-body text-sm font-medium text-dark">
                ✓ Mensaje enviado. Te contactamos pronto.
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</template>
