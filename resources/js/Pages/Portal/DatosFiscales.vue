<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { CheckCircle2, AlertCircle, Info, Mail, Lock } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Datos fiscales (Fase 2.3).
 *
 * Lo que esta pantalla **tiene que dejar claro**: Nódico no emite la factura,
 * la emite contabilidad del IYEM, y tarda unos días. Sin decirlo, quien llena
 * el formulario espera un PDF al instante y escribe a recepción en veinte
 * minutos.
 */
const props = defineProps<{
  datos: Record<string, string> | null
  completos: boolean
  actualizados_el: string | null
  regimenes: { clave: string; nombre: string; personas: string[] }[]
  usosCfdi: { clave: string; nombre: string }[]
  usoPredeterminado: string
  proceso: { emisor: string; dias_habiles: number; contacto: string }
  correoCuenta: string
}>()

const form = useForm({
  rfc: props.datos?.rfc ?? '',
  razon_social: props.datos?.razon_social ?? '',
  regimen_fiscal: props.datos?.regimen_fiscal ?? '',
  uso_cfdi: props.datos?.uso_cfdi ?? props.usoPredeterminado,
  codigo_postal: props.datos?.codigo_postal ?? '',
  email_facturacion: props.datos?.email_facturacion ?? props.correoCuenta,
})

/**
 * Régimenes que encajan con el RFC escrito, en vivo.
 *
 * Un RFC de 12 posiciones es persona moral y no puede acogerse a un régimen de
 * persona física. Filtrarlo aquí evita el error más común, que contabilidad
 * devuelve días después. El servidor lo vuelve a comprobar: esto es comodidad.
 */
const tipoPersona = computed(() => {
  const limpio = form.rfc.replace(/[^A-Za-z0-9Ññ&]/g, '')
  if (limpio.length === 12) return 'moral'
  if (limpio.length === 13) return 'fisica'
  return null
})

const regimenesPosibles = computed(() =>
  tipoPersona.value
    ? props.regimenes.filter((r) => r.personas.includes(tipoPersona.value!))
    : props.regimenes,
)

const fechaLarga = (iso: string) =>
  new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <Head title="Datos fiscales" />

  <PortalLayout>
    <EncabezadoPortal
      titulo="Datos fiscales"
      etiqueta="Facturación"
      numero="01"
      descripcion="Los guardamos para que contabilidad pueda emitir tus facturas."
    />

    <!-- Estado: el miembro necesita saber si ya puede pedir factura. -->
    <div
      class="mb-6 flex items-start gap-3 border-2 p-5 shadow-dura-sm"
      :class="completos ? 'border-dark bg-white' : 'border-dark bg-nodo-400'"
    >
      <component
        :is="completos ? CheckCircle2 : AlertCircle"
        :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true"
      />
      <div>
        <p class="font-display text-sm font-bold text-dark">
          {{ completos ? 'Tus datos están completos' : 'Te faltan datos' }}
        </p>
        <p class="mt-1 font-body text-sm text-dark/80">
          {{ completos
            ? 'Ya puedes pedir factura de cualquier pago.'
            : 'Llena el formulario para poder pedir factura.' }}
          <template v-if="actualizados_el">
            Última actualización: {{ fechaLarga(actualizados_el) }}.
          </template>
        </p>
      </div>
    </div>

    <!-- Cómo funciona de verdad. Va antes del formulario, no después. -->
    <TarjetaPortal fondo="oscuro" padding="md" class="mb-6">
      <div class="flex items-start gap-3">
        <Info :size="20" class="mt-0.5 shrink-0 text-nodo-400" aria-hidden="true" />
        <div class="space-y-2">
          <p class="font-display text-sm font-bold text-white">Cómo funciona la facturación</p>
          <p class="font-body text-sm text-cream/85">
            Nódico <strong class="text-white">no emite la factura</strong>: guardamos tus datos y se los
            entregamos a {{ proceso.emisor }}, que es quien la timbra ante el SAT.
          </p>
          <p class="font-body text-sm text-cream/85">
            Suele llegar en unos <strong class="text-white">{{ proceso.dias_habiles }} días hábiles</strong>
            al correo que pongas abajo. Si pasan más, escribe a
            <a
              :href="`mailto:${proceso.contacto}`"
              class="text-nodo-400 underline decoration-2 underline-offset-4 hover:text-white
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-nodo-400"
            >{{ proceso.contacto }}</a>.
          </p>
        </div>
      </div>
    </TarjetaPortal>

    <form @submit.prevent="form.put(route('portal.datos-fiscales.update'), { preserveScroll: true })">
      <TarjetaPortal etiqueta="Tus datos ante el SAT" numero="02">
        <div class="grid gap-5 sm:grid-cols-2">
          <!-- RFC -->
          <div>
            <label for="rfc" class="mb-1.5 block font-display text-sm font-bold text-dark">RFC</label>
            <input
              id="rfc" v-model="form.rfc" type="text" required
              maxlength="13" autocapitalize="characters" spellcheck="false"
              placeholder="XAXX010101000"
              :aria-invalid="!!form.errors.rfc"
              :aria-describedby="form.errors.rfc ? 'rfc-error' : 'rfc-ayuda'"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 font-mono text-base uppercase text-dark
                     transition-colors focus:bg-white focus:outline focus:outline-2
                     focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.rfc ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors.rfc" id="rfc-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.rfc }}
            </p>
            <p v-else id="rfc-ayuda" class="mt-1.5 font-body text-xs text-dark/70">
              <template v-if="tipoPersona === 'moral'">12 posiciones: eres persona moral.</template>
              <template v-else-if="tipoPersona === 'fisica'">13 posiciones: eres persona física.</template>
              <template v-else>12 posiciones si eres persona moral, 13 si eres persona física.</template>
            </p>
          </div>

          <!-- Razón social -->
          <div>
            <label for="razon" class="mb-1.5 block font-display text-sm font-bold text-dark">
              Nombre o razón social
            </label>
            <input
              id="razon" v-model="form.razon_social" type="text" required maxlength="255"
              :aria-invalid="!!form.errors.razon_social"
              :aria-describedby="form.errors.razon_social ? 'razon-error' : 'razon-ayuda'"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                     focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.razon_social ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors.razon_social" id="razon-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.razon_social }}
            </p>
            <p v-else id="razon-ayuda" class="mt-1.5 font-body text-xs text-dark/70">
              Tal como aparece en tu constancia de situación fiscal.
            </p>
          </div>

          <!-- Régimen fiscal -->
          <div>
            <label for="regimen" class="mb-1.5 block font-display text-sm font-bold text-dark">
              Régimen fiscal
            </label>
            <select
              id="regimen" v-model="form.regimen_fiscal" required
              :aria-invalid="!!form.errors.regimen_fiscal"
              :aria-describedby="form.errors.regimen_fiscal ? 'regimen-error' : undefined"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                     focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.regimen_fiscal ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            >
              <option value="" disabled>Elige tu régimen</option>
              <option v-for="r in regimenesPosibles" :key="r.clave" :value="r.clave">
                {{ r.clave }} — {{ r.nombre }}
              </option>
            </select>
            <p v-if="form.errors.regimen_fiscal" id="regimen-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.regimen_fiscal }}
            </p>
            <p v-else-if="tipoPersona" class="mt-1.5 font-body text-xs text-dark/70">
              Solo se muestran los que aplican a persona {{ tipoPersona === 'moral' ? 'moral' : 'física' }}.
            </p>
          </div>

          <!-- Uso de CFDI -->
          <div>
            <label for="uso" class="mb-1.5 block font-display text-sm font-bold text-dark">Uso de CFDI</label>
            <select
              id="uso" v-model="form.uso_cfdi" required
              :aria-invalid="!!form.errors.uso_cfdi"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                     focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.uso_cfdi ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            >
              <option v-for="u in usosCfdi" :key="u.clave" :value="u.clave">
                {{ u.clave }} — {{ u.nombre }}
              </option>
            </select>
            <p class="mt-1.5 font-body text-xs text-dark/70">
              Para una membresía de coworking lo normal es «G03 — Gastos en general».
            </p>
          </div>

          <!-- Código postal -->
          <div>
            <label for="cp" class="mb-1.5 block font-display text-sm font-bold text-dark">
              Código postal fiscal
            </label>
            <input
              id="cp" v-model="form.codigo_postal" type="text" inputmode="numeric"
              required maxlength="5" pattern="\d{5}" placeholder="97110"
              :aria-invalid="!!form.errors.codigo_postal"
              :aria-describedby="form.errors.codigo_postal ? 'cp-error' : undefined"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 font-mono text-base text-dark
                     transition-colors focus:bg-white focus:outline focus:outline-2
                     focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.codigo_postal ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors.codigo_postal" id="cp-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.codigo_postal }}
            </p>
          </div>

          <!-- Correo de facturación -->
          <div>
            <label for="email-fac" class="mb-1.5 block font-display text-sm font-bold text-dark">
              Correo para las facturas
            </label>
            <input
              id="email-fac" v-model="form.email_facturacion" type="email" required
              autocomplete="email"
              :aria-invalid="!!form.errors.email_facturacion"
              :aria-describedby="form.errors.email_facturacion ? 'email-error' : 'email-ayuda'"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                     focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.email_facturacion ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors.email_facturacion" id="email-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.email_facturacion }}
            </p>
            <p v-else id="email-ayuda" class="mt-1.5 flex items-start gap-1.5 font-body text-xs text-dark/70">
              <Mail :size="13" class="mt-0.5 shrink-0" aria-hidden="true" />
              Puede ser distinto al de tu cuenta, por ejemplo el de tu contador.
            </p>
          </div>
        </div>

        <p class="mt-6 flex items-start gap-2 border-t-2 border-dark/10 pt-5 font-body text-xs text-dark/70">
          <Lock :size="14" class="mt-0.5 shrink-0" aria-hidden="true" />
          Estos datos solo los ve administración de Nódico y contabilidad del IYEM.
          Cada consulta y cada cambio quedan registrados.
        </p>
      </TarjetaPortal>

      <button
        type="submit" :disabled="form.processing"
        class="mt-6 inline-flex min-h-[52px] items-center border-2 border-dark bg-nodo-400 px-6
               font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
               hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm disabled:opacity-60
               focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
               focus-visible:outline-dark"
      >{{ form.processing ? 'Guardando…' : 'Guardar datos fiscales' }}</button>
    </form>
  </PortalLayout>
</template>
