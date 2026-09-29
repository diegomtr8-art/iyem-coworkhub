<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'
import { Lock } from 'lucide-vue-next'

/**
 * La tarjeta se teclea **en Nódico**, pero nunca llega a Nódico.
 *
 * openpay.js (la librería de la plataforma sobre la que corre BBVA) lee estos
 * campos en el navegador y los manda **directo a BBVA**, que devuelve un token
 * de un solo uso. Al servidor de Nódico solo viajan el token y el
 * `device_session_id` del antifraude (openpay-data.js). Por eso los campos no
 * llevan `name` ni viven en un `<form>` que se envíe: no hay manera de que el
 * número, la fecha o el CVV salgan hacia nuestro servidor.
 *
 * Si el banco pide 3-D Secure, el servidor manda a la persona a la página de
 * su banco (esa autenticación es siempre del emisor) y la regresa.
 */
const props = defineProps<{
  planId: number
  importe: string
  openpay: { merchant_id: string; llave_publica: string; sandbox: boolean }
}>()

const listo = ref(false)
const procesando = ref(false)
const error = ref<string | null>(null)
const tarjeta = reactive({ nombre: '', numero: '', vencimiento: '', cvv: '' })
const errores = reactive<Record<string, string | null>>({ nombre: null, numero: null, vencimiento: null, cvv: null })

let OpenPay: any = null
let deviceSessionId = ''

function cargarScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve()
    const s = document.createElement('script')
    s.src = src
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('No se pudo cargar el pago seguro.'))
    document.head.appendChild(s)
  })
}

onMounted(async () => {
  try {
    // El orden importa: openpay-data.js depende de openpay.js, y el modo
    // sandbox se fija antes de generar el identificador del dispositivo.
    await cargarScript('https://js.openpay.mx/openpay.v1.min.js')
    await cargarScript('https://js.openpay.mx/openpay-data.v1.min.js')
    OpenPay = (window as any).OpenPay
    OpenPay.setId(props.openpay.merchant_id)
    OpenPay.setApiKey(props.openpay.llave_publica)
    OpenPay.setSandboxMode(props.openpay.sandbox)
    deviceSessionId = OpenPay.deviceData.setup()
    listo.value = true
  } catch (e: any) {
    error.value = e?.message ?? 'No se pudo cargar el pago seguro. Recarga la página.'
  }
})

const soloDigitos = (v: string) => v.replace(/\D/g, '')

/** 4111111111111111 → «4111 1111 1111 1111» (Amex: 4-6-5). */
function formatearNumero(e: Event) {
  const d = soloDigitos((e.target as HTMLInputElement).value).slice(0, 19)
  const amex = /^3[47]/.test(d)
  tarjeta.numero = amex
    ? [d.slice(0, 4), d.slice(4, 10), d.slice(10, 15)].filter(Boolean).join(' ')
    : d.replace(/(\d{4})(?=\d)/g, '$1 ')
}

/** «1228» → «12/28». */
function formatearVencimiento(e: Event) {
  const d = soloDigitos((e.target as HTMLInputElement).value).slice(0, 4)
  tarjeta.vencimiento = d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d
}

function formatearCvv(e: Event) {
  tarjeta.cvv = soloDigitos((e.target as HTMLInputElement).value).slice(0, 4)
}

const partesVencimiento = computed(() => {
  const [mes = '', anio = ''] = tarjeta.vencimiento.split('/')
  return { mes, anio }
})

function validar(): boolean {
  const numero = soloDigitos(tarjeta.numero)
  const { mes, anio } = partesVencimiento.value
  errores.nombre = tarjeta.nombre.trim().length < 3 ? 'Escribe el nombre como aparece en la tarjeta.' : null
  errores.numero = !OpenPay.card.validateCardNumber(numero) ? 'Revisa el número de la tarjeta.' : null
  errores.vencimiento = mes.length !== 2 || anio.length !== 2 || !OpenPay.card.validateExpiry(mes, `20${anio}`)
    ? 'Revisa la fecha de vencimiento (MM/AA).' : null
  errores.cvv = !OpenPay.card.validateCVC(tarjeta.cvv, numero) ? 'Revisa el código de seguridad.' : null
  return !Object.values(errores).some(Boolean)
}

/** Errores de openpay.js al crear el token, en español. */
function mensajeDelToken(respuesta: any): string {
  const codigo = respuesta?.data?.error_code
  const porCodigo: Record<number, string> = {
    2004: 'El número de tarjeta no es válido. Revísalo.',
    2005: 'La fecha de vencimiento ya pasó. Revísala o usa otra tarjeta.',
    2006: 'Falta el código de seguridad de la tarjeta.',
    2009: 'El código de seguridad no es válido. Revísalo.',
    3001: 'Tu banco rechazó la tarjeta. Prueba con otra.',
    3002: 'Tu tarjeta está vencida. Usa otra.',
  }
  if (codigo && porCodigo[codigo]) return porCodigo[codigo]
  if (respuesta?.status === 0) return 'No pudimos conectar con el banco. Revisa tu conexión y vuelve a intentarlo.'
  return 'No pudimos validar la tarjeta. Revisa los datos o usa otra.'
}

function pagar() {
  if (!listo.value || procesando.value) return
  error.value = null
  if (!validar()) return
  procesando.value = true

  const { mes, anio } = partesVencimiento.value

  OpenPay.token.create(
    {
      holder_name: tarjeta.nombre.trim(),
      card_number: soloDigitos(tarjeta.numero),
      expiration_month: mes,
      expiration_year: anio,
      cvv2: tarjeta.cvv,
    },
    (respuesta: any) => {
      // El CVV no se guarda más de lo necesario.
      tarjeta.cvv = ''
      // Solo el token y el identificador del dispositivo viajan a Nódico.
      router.post(route('portal.contratar.tarjeta.token', { plan: props.planId }), {
        token_id: respuesta.data.id,
        device_session_id: deviceSessionId,
      }, {
        onError: (e) => { error.value = Object.values(e)[0] as string; procesando.value = false },
      })
    },
    (respuesta: any) => {
      error.value = mensajeDelToken(respuesta)
      procesando.value = false
    },
  )
}

const claseCampo = (conError: string | null, mono = true) => [
  'w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors',
  mono ? 'font-mono' : 'font-body',
  'focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark',
  conError ? 'border-coral' : 'border-dark/25 focus:border-nodo-500',
]
</script>

<template>
  <div>
    <div v-if="error" class="mb-4 border-2 border-coral bg-coral/10 px-4 py-3 font-body text-sm text-dark" role="alert">
      {{ error }}
    </div>

    <p v-if="!listo && !error" class="py-8 text-center font-body text-sm text-dark/50">Cargando el pago seguro…</p>

    <!-- Sin <form> ni `name`: los datos de la tarjeta no se pueden enviar a Nódico. -->
    <div v-show="listo" class="space-y-4" @keydown.enter.prevent="pagar">
      <div>
        <label for="tarjeta-nombre" class="mb-1.5 block font-display text-sm font-bold text-dark">Nombre en la tarjeta</label>
        <input
          id="tarjeta-nombre" v-model="tarjeta.nombre" type="text" autocomplete="cc-name" spellcheck="false"
          :aria-invalid="!!errores.nombre" :aria-describedby="errores.nombre ? 'tarjeta-nombre-error' : undefined"
          :class="claseCampo(errores.nombre, false)"
        />
        <p v-if="errores.nombre" id="tarjeta-nombre-error" class="mt-1.5 font-body text-xs text-coral">{{ errores.nombre }}</p>
      </div>

      <div>
        <label for="tarjeta-numero" class="mb-1.5 block font-display text-sm font-bold text-dark">Número de tarjeta</label>
        <input
          id="tarjeta-numero" :value="tarjeta.numero" @input="formatearNumero" type="text" inputmode="numeric"
          autocomplete="cc-number" placeholder="1234 1234 1234 1234" maxlength="23"
          :aria-invalid="!!errores.numero" :aria-describedby="errores.numero ? 'tarjeta-numero-error' : undefined"
          :class="claseCampo(errores.numero)"
        />
        <p v-if="errores.numero" id="tarjeta-numero-error" class="mt-1.5 font-body text-xs text-coral">{{ errores.numero }}</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="tarjeta-vencimiento" class="mb-1.5 block font-display text-sm font-bold text-dark">Vencimiento</label>
          <input
            id="tarjeta-vencimiento" :value="tarjeta.vencimiento" @input="formatearVencimiento" type="text"
            inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA" maxlength="5"
            :aria-invalid="!!errores.vencimiento" :aria-describedby="errores.vencimiento ? 'tarjeta-vencimiento-error' : undefined"
            :class="claseCampo(errores.vencimiento)"
          />
          <p v-if="errores.vencimiento" id="tarjeta-vencimiento-error" class="mt-1.5 font-body text-xs text-coral">{{ errores.vencimiento }}</p>
        </div>
        <div>
          <label for="tarjeta-cvv" class="mb-1.5 block font-display text-sm font-bold text-dark">Código de seguridad</label>
          <input
            id="tarjeta-cvv" :value="tarjeta.cvv" @input="formatearCvv" type="password" inputmode="numeric"
            autocomplete="cc-csc" placeholder="CVV" maxlength="4"
            :aria-invalid="!!errores.cvv" :aria-describedby="errores.cvv ? 'tarjeta-cvv-error' : undefined"
            :class="claseCampo(errores.cvv)"
          />
          <p v-if="errores.cvv" id="tarjeta-cvv-error" class="mt-1.5 font-body text-xs text-coral">{{ errores.cvv }}</p>
        </div>
      </div>

      <button
        type="button"
        @click="pagar"
        :disabled="procesando"
        class="mt-2 flex min-h-[48px] w-full items-center justify-center gap-2 bg-dark px-6
               font-display text-base font-bold text-white transition hover:bg-dark/90 disabled:opacity-50"
      >
        <Lock :size="18" aria-hidden="true" />
        {{ procesando ? 'Procesando…' : 'Pagar ' + importe }}
      </button>

      <p class="font-body text-xs text-dark/60">
        Si tu banco lo pide, te llevaremos un momento a su página para confirmar el pago y regresarás aquí.
      </p>
    </div>
  </div>
</template>
