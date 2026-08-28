<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { toast } from 'vue-sonner'

const form = useForm({
  nombre: '',
  telefono: '',
  email: '',
  empresa: '',
  asunto: '',
  comentarios: '',
})

const campos = [
  { name: 'nombre', label: 'Nombre', type: 'text', autocomplete: 'name', requerido: true },
  { name: 'telefono', label: 'Teléfono', type: 'tel', autocomplete: 'tel', requerido: false },
  { name: 'email', label: 'E-mail', type: 'email', autocomplete: 'email', requerido: true },
  { name: 'empresa', label: 'Su empresa', type: 'text', autocomplete: 'organization', requerido: false },
  { name: 'asunto', label: 'Asunto', type: 'text', autocomplete: 'off', requerido: false },
] as const

const page = usePage()
const contactoOk = computed(() => (page.props.flash as any)?.contacto_ok)

watch(contactoOk, (ok) => {
  if (ok) toast.success('¡Gracias! Tu mensaje fue enviado. Te contactaremos pronto.')
})

const enviar = () => {
  form.post(route('contacto.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => toast.error('Revisa los campos marcados e inténtalo de nuevo.'),
  })
}
</script>

<template>
  <section id="hablemos" class="bg-dark py-20 lg:py-28">
    <div class="mx-auto max-w-3xl px-5 sm:px-8">
      <h2 class="text-center font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
        Hablemos
      </h2>
      <p class="mt-4 text-center font-body text-base text-white/70">
        Cuéntanos qué necesitas y te respondemos a la brevedad.
      </p>

      <form class="mt-12 space-y-7" novalidate @submit.prevent="enviar">
        <div v-for="campo in campos" :key="campo.name">
          <label :for="`contacto-${campo.name}`" class="block font-body text-sm text-white/70">
            {{ campo.label }}
            <span v-if="campo.requerido" class="text-nodo-400" aria-hidden="true">*</span>
          </label>
          <input
            :id="`contacto-${campo.name}`"
            v-model="form[campo.name]"
            :type="campo.type"
            :autocomplete="campo.autocomplete"
            :required="campo.requerido"
            :aria-invalid="form.errors[campo.name] ? 'true' : undefined"
            :aria-describedby="form.errors[campo.name] ? `error-${campo.name}` : undefined"
            class="mt-2 w-full rounded-lg border bg-white/5 px-4 py-3 font-body text-white placeholder-white/35
                   transition focus:border-nodo-400 focus:outline-none focus:ring-2 focus:ring-nodo-400/40"
            :class="form.errors[campo.name] ? 'border-red-400' : 'border-white/20'"
          />
          <p v-if="form.errors[campo.name]" :id="`error-${campo.name}`" class="mt-2 font-body text-sm text-red-400">
            {{ form.errors[campo.name] }}
          </p>
        </div>

        <div>
          <label for="contacto-comentarios" class="block font-body text-sm text-white/70">
            Comentarios <span class="text-nodo-400" aria-hidden="true">*</span>
          </label>
          <textarea
            id="contacto-comentarios"
            v-model="form.comentarios"
            rows="5"
            required
            :aria-invalid="form.errors.comentarios ? 'true' : undefined"
            :aria-describedby="form.errors.comentarios ? 'error-comentarios' : undefined"
            class="mt-2 w-full resize-y rounded-lg border bg-white/5 px-4 py-3 font-body text-white placeholder-white/35
                   transition focus:border-nodo-400 focus:outline-none focus:ring-2 focus:ring-nodo-400/40"
            :class="form.errors.comentarios ? 'border-red-400' : 'border-white/20'"
          />
          <p v-if="form.errors.comentarios" id="error-comentarios" class="mt-2 font-body text-sm text-red-400">
            {{ form.errors.comentarios }}
          </p>
        </div>

        <div class="pt-2">
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg border-2 border-dark bg-nodo-400 px-9 py-3 font-display text-sm font-bold text-dark
                   transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60
                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
          >
            {{ form.processing ? 'Enviando…' : 'Enviar' }}
          </button>
        </div>

        <!-- Confirmación accesible para lectores de pantalla. -->
        <p v-if="contactoOk" role="status" class="font-body text-sm text-nodo-400">
          ¡Gracias! Tu mensaje fue enviado. Te contactaremos pronto.
        </p>
      </form>
    </div>
  </section>
</template>
