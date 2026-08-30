<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Camera, Trash2, ShieldCheck, ScanFace, ChevronRight, Phone } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import EncabezadoPortal from '@/Components/Portal/EncabezadoPortal.vue'
import TarjetaPortal from '@/Components/Portal/TarjetaPortal.vue'

/**
 * Mi perfil (Fase 2.2).
 *
 * La seguridad —contraseña, segundo factor, sesiones abiertas— **se enlaza**,
 * no se duplica: ya existe su pantalla, y dos formularios de contraseña en dos
 * sitios distintos es la receta para que uno se quede sin la validación del otro.
 */
const props = defineProps<{
  perfil: Record<string, any>
  faceIdOk: boolean
  seguridad: { url: string; dos_factores_activo: boolean; correo_verificado: boolean }
}>()

const form = useForm({
  name: props.perfil.name ?? '',
  telefono: props.perfil.telefono ?? '',
  empresa: props.perfil.empresa ?? '',
  ocupacion: props.perfil.ocupacion ?? '',
  contacto_emergencia_nombre: props.perfil.contacto_emergencia_nombre ?? '',
  contacto_emergencia_telefono: props.perfil.contacto_emergencia_telefono ?? '',
  contacto_emergencia_parentesco: props.perfil.contacto_emergencia_parentesco ?? '',
  notif_reservas: props.perfil.notif_reservas,
  notif_membresia: props.perfil.notif_membresia,
  notif_comunidad: props.perfil.notif_comunidad,
})

const entradaFoto = ref<HTMLInputElement | null>(null)
const subiendoFoto = ref(false)

function subirFoto(evento: Event) {
  const archivo = (evento.target as HTMLInputElement).files?.[0]
  if (!archivo) return

  subiendoFoto.value = true
  router.post(route('portal.perfil.avatar'), { avatar: archivo }, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      subiendoFoto.value = false
      if (entradaFoto.value) entradaFoto.value.value = ''
    },
  })
}

const campos = [
  { clave: 'name', etiqueta: 'Nombre completo', tipo: 'text', requerido: true, auto: 'name' },
  { clave: 'telefono', etiqueta: 'Teléfono', tipo: 'tel', requerido: false, auto: 'tel' },
  { clave: 'empresa', etiqueta: 'Empresa o proyecto', tipo: 'text', requerido: false, auto: 'organization' },
  { clave: 'ocupacion', etiqueta: 'A qué te dedicas', tipo: 'text', requerido: false, auto: 'off' },
] as const

const avisos = [
  { clave: 'notif_reservas', titulo: 'Mis reservas', detalle: 'Confirmaciones, recordatorios y cambios.' },
  { clave: 'notif_membresia', titulo: 'Mi membresía', detalle: 'Avisos de renovación y de vencimiento.' },
  { clave: 'notif_comunidad', titulo: 'Comunidad', detalle: 'Talleres, eventos y novedades de Nódico.' },
] as const
</script>

<template>
  <Head title="Mi perfil" />

  <PortalLayout>
    <EncabezadoPortal titulo="Mi perfil" etiqueta="Perfil" numero="01" />

    <form class="space-y-6" @submit.prevent="form.patch(route('portal.perfil.update'), { preserveScroll: true })">
      <!-- Foto e identidad. -->
      <TarjetaPortal etiqueta="Tus datos" numero="02">
        <div class="flex flex-wrap items-center gap-5">
          <img
            v-if="perfil.avatar"
            :src="perfil.avatar" alt="Tu foto de perfil"
            width="80" height="80"
            class="h-20 w-20 border-2 border-dark object-cover"
          />
          <span
            v-else
            class="flex h-20 w-20 items-center justify-center border-2 border-dark bg-nodo-400
                   font-display text-2xl font-extrabold text-dark"
            aria-hidden="true"
          >{{ (perfil.name || '?').charAt(0).toUpperCase() }}</span>

          <div class="flex flex-wrap gap-2">
            <button
              type="button" :disabled="subiendoFoto"
              class="inline-flex min-h-[44px] items-center gap-2 border-2 border-dark px-4
                     font-display text-sm font-bold text-dark transition-colors hover:bg-dark
                     hover:text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2
                     focus-visible:outline-offset-2 focus-visible:outline-dark"
              @click="entradaFoto?.click()"
            >
              <Camera :size="16" aria-hidden="true" />
              {{ subiendoFoto ? 'Subiendo…' : perfil.avatar ? 'Cambiar foto' : 'Subir foto' }}
            </button>

            <button
              v-if="perfil.avatar" type="button"
              class="inline-flex min-h-[44px] items-center gap-2 border-2 border-dark/30 px-4
                     font-display text-sm font-bold text-dark transition-colors hover:border-coral
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                     focus-visible:outline-dark"
              @click="router.delete(route('portal.perfil.avatar.destroy'), { preserveScroll: true })"
            >
              <Trash2 :size="16" aria-hidden="true" /> Quitar
            </button>

            <input
              ref="entradaFoto" type="file" accept="image/jpeg,image/png,image/webp"
              class="sr-only" @change="subirFoto"
            />
          </div>
        </div>

        <div class="mt-6 grid gap-5 sm:grid-cols-2">
          <div v-for="campo in campos" :key="campo.clave">
            <label :for="campo.clave" class="mb-1.5 block font-display text-sm font-bold text-dark">
              {{ campo.etiqueta }}
              <span v-if="!campo.requerido" class="font-body font-normal text-dark/60">(opcional)</span>
            </label>
            <input
              :id="campo.clave" v-model="form[campo.clave]" :type="campo.tipo"
              :autocomplete="campo.auto" :required="campo.requerido"
              :aria-describedby="form.errors[campo.clave] ? `${campo.clave}-error` : undefined"
              :aria-invalid="!!form.errors[campo.clave]"
              class="w-full border-2 bg-cream-50 px-3.5 py-3 text-base text-dark transition-colors
                     focus:bg-white focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors[campo.clave] ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors[campo.clave]" :id="`${campo.clave}-error`" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors[campo.clave] }}
            </p>
          </div>

          <!-- El correo se cambia en «Mi seguridad», dentro del portal, con el
               flujo seguro (verifica la dirección nueva). Antes este enlace iba a
               la pantalla de Breeze y sacaba al miembro del portal. -->
          <div class="sm:col-span-2">
            <label class="mb-1.5 block font-display text-sm font-bold text-dark">Correo</label>
            <div class="flex flex-wrap items-center gap-3">
              <p class="font-body text-base text-dark/70">{{ perfil.email }}</p>
              <Link
                :href="route('seguridad') + '#correo'"
                class="font-display text-sm font-bold text-dark underline decoration-nodo-500
                       decoration-2 underline-offset-4 hover:decoration-dark focus-visible:outline
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dark"
              >Cambiar</Link>
            </div>
          </div>
        </div>
      </TarjetaPortal>

      <!-- Contacto de emergencia. -->
      <TarjetaPortal etiqueta="Contacto de emergencia" numero="03" fondo="crema">
        <p class="mb-5 flex items-start gap-2 font-body text-sm text-dark/70">
          <Phone :size="15" class="mt-0.5 shrink-0" aria-hidden="true" />
          A quién llamamos si te pasa algo mientras estás en Nódico. No lo usamos para nada más.
        </p>

        <div class="grid gap-5 sm:grid-cols-3">
          <div>
            <label for="ce-nombre" class="mb-1.5 block font-display text-sm font-bold text-dark">Nombre</label>
            <input
              id="ce-nombre" v-model="form.contacto_emergencia_nombre" type="text"
              class="w-full border-2 border-dark/25 bg-white px-3.5 py-3 text-base text-dark
                     focus:border-nodo-500 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-dark"
            />
          </div>
          <div>
            <label for="ce-tel" class="mb-1.5 block font-display text-sm font-bold text-dark">Teléfono</label>
            <input
              id="ce-tel" v-model="form.contacto_emergencia_telefono" type="tel"
              :aria-invalid="!!form.errors.contacto_emergencia_telefono"
              :aria-describedby="form.errors.contacto_emergencia_telefono ? 'ce-tel-error' : undefined"
              class="w-full border-2 bg-white px-3.5 py-3 text-base text-dark focus:outline
                     focus:outline-2 focus:outline-offset-2 focus:outline-dark"
              :class="form.errors.contacto_emergencia_telefono ? 'border-coral' : 'border-dark/25 focus:border-nodo-500'"
            />
            <p v-if="form.errors.contacto_emergencia_telefono" id="ce-tel-error" class="mt-1.5 font-body text-xs text-coral">
              {{ form.errors.contacto_emergencia_telefono }}
            </p>
          </div>
          <div>
            <label for="ce-par" class="mb-1.5 block font-display text-sm font-bold text-dark">Parentesco</label>
            <input
              id="ce-par" v-model="form.contacto_emergencia_parentesco" type="text"
              placeholder="Mamá, pareja, socio…"
              class="w-full border-2 border-dark/25 bg-white px-3.5 py-3 text-base text-dark
                     placeholder:text-dark/40 focus:border-nodo-500 focus:outline focus:outline-2
                     focus:outline-offset-2 focus:outline-dark"
            />
          </div>
        </div>
      </TarjetaPortal>

      <!-- Avisos. -->
      <TarjetaPortal etiqueta="Qué quieres que te avisemos" numero="04">
        <ul class="space-y-3">
          <li v-for="aviso in avisos" :key="aviso.clave">
            <label
              class="flex min-h-[56px] cursor-pointer items-center justify-between gap-4 border-2
                     border-dark/20 bg-cream-50 px-4 py-3 transition-colors hover:border-dark/40
                     has-[:focus-visible]:outline has-[:focus-visible]:outline-2
                     has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-dark"
            >
              <span>
                <span class="block font-display text-sm font-bold text-dark">{{ aviso.titulo }}</span>
                <span class="mt-0.5 block font-body text-xs text-dark/70">{{ aviso.detalle }}</span>
              </span>
              <input
                v-model="form[aviso.clave]" type="checkbox"
                class="h-6 w-6 shrink-0 cursor-pointer rounded-none border-2 border-dark
                       text-nodo-400 focus:ring-0 focus:ring-offset-0"
              />
            </label>
          </li>
        </ul>
      </TarjetaPortal>

      <div class="flex flex-wrap items-center gap-4">
        <button
          type="submit" :disabled="form.processing"
          class="inline-flex min-h-[52px] items-center border-2 border-dark bg-nodo-400 px-6
                 font-display text-sm font-bold text-dark transition-all duration-200 ease-salida
                 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-dura-sm
                 disabled:opacity-60 focus-visible:outline focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-dark"
        >{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</button>

        <p v-if="form.recentlySuccessful" class="font-body text-sm text-dark/70">Guardado.</p>
      </div>
    </form>

    <!-- Seguridad y Face ID: enlaces, no duplicados. -->
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <Link
        :href="seguridad.url"
        class="group flex items-center justify-between gap-4 border-2 border-dark bg-white p-5
               shadow-dura-sm transition-all duration-200 ease-salida hover:-translate-x-1
               hover:-translate-y-1 hover:shadow-dura focus-visible:outline focus-visible:outline-2
               focus-visible:outline-offset-2 focus-visible:outline-dark"
      >
        <span class="flex items-start gap-3">
          <ShieldCheck :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
          <span>
            <span class="block font-display text-sm font-bold text-dark">Mi seguridad</span>
            <span class="mt-1 block font-body text-xs text-dark/70">
              Contraseña, segundo factor
              <template v-if="seguridad.dos_factores_activo">(activo)</template>
              y sesiones abiertas.
            </span>
          </span>
        </span>
        <ChevronRight :size="18" class="shrink-0 text-dark transition-transform duration-200 ease-salida group-hover:translate-x-1" aria-hidden="true" />
      </Link>

      <div class="flex items-start gap-3 border-2 p-5" :class="faceIdOk ? 'border-dark/20 bg-white' : 'border-dark bg-nodo-400'">
        <ScanFace :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
        <div>
          <p class="font-display text-sm font-bold text-dark">
            {{ faceIdOk ? 'Face ID registrado' : 'Face ID pendiente' }}
          </p>
          <p class="mt-1 font-body text-xs" :class="faceIdOk ? 'text-dark/70' : 'text-dark/80'">
            {{ faceIdOk
              ? 'Puedes entrar sin pasar por recepción.'
              : 'Pásate por recepción la próxima vez que vengas para registrarlo.' }}
          </p>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
