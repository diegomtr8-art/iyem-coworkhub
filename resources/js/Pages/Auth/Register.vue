<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Eye, EyeOff, X } from 'lucide-vue-next'
import { ref } from 'vue'
import { useCursor } from '@/composables/useAnimations'

useCursor()

const showPassword = ref(false)
const showConfirm  = ref(false)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  telefono: '',
  empresa: '',
  ocupacion: '',
})

const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') })

const beneficios = [
  'Coworking profesional en Mérida',
  'Estudios de foto y podcast',
  'Sala de juntas equipada',
  'Red de emprendedores IYEM',
]
</script>

<template>
  <Head title="Regístrate — Nódico" />
  <div id="cursor-outer" class="hidden lg:block" />
  <div id="cursor-inner" class="hidden lg:block" />

  <div class="cursor-zone min-h-screen bg-cream flex items-center justify-center p-6 py-10" style="font-family: 'Carmen Sans', sans-serif;">

    <div class="w-full max-w-3xl rounded-3xl overflow-hidden shadow-2xl grid lg:grid-cols-2 relative reveal-scale visible">

      <!-- X button -->
      <Link href="/" data-cursor-hover class="absolute top-4 right-4 z-10 w-8 h-8 bg-white/20 hover:bg-white/40 rounded-full flex items-center justify-center text-dark transition">
        <X :size="16" />
      </Link>

      <!-- LEFT: Yellow panel -->
      <div class="noise relative bg-nodo-400 p-10 lg:p-12 flex flex-col items-center justify-center text-center overflow-hidden">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full border-2 border-dark/10" style="transform: translate(35%, -35%);" />
        <!-- Logo circular -->
        <div class="relative w-28 h-28 rounded-full border-2 border-dark/20 flex items-center justify-center mb-8">
          <img src="/logo-nodico-blanco.png" alt="Nódico" class="w-20 h-20 object-contain" style="filter: brightness(0);" />
        </div>

        <h1 class="relative text-3xl font-black text-dark leading-tight mb-3">
          Tu comunidad<br>
          <span class="font-black">te espera</span>
        </h1>

        <p class="relative text-dark/70 text-sm leading-relaxed mb-6 max-w-xs">
          Accede a espacios profesionales, estudios creativos y una comunidad activa de emprendedores en Yucatán.
        </p>

        <div class="relative text-left space-y-2 mb-8 w-full max-w-xs">
          <div v-for="b in beneficios" :key="b" class="flex items-center gap-2 text-xs text-dark/80 font-medium">
            <span class="w-5 h-5 rounded-full bg-dark/15 flex items-center justify-center text-dark flex-shrink-0">✓</span>
            {{ b }}
          </div>
        </div>

        <Link :href="route('login')" data-cursor-hover
          class="magnetic relative inline-flex items-center justify-center bg-dark text-white font-black text-sm px-8 py-3 rounded-full hover:bg-dark/80 transition w-full max-w-xs hover:scale-105">
          Ya tengo cuenta
        </Link>
      </div>

      <!-- RIGHT: Register form -->
      <div class="bg-cream p-8 lg:p-10 flex flex-col justify-center overflow-y-auto">
        <h2 class="text-2xl font-black text-dark mb-6 text-center">Crea tu cuenta</h2>

        <!-- Aviso Face ID -->
        <div class="bg-nodo-400/10 border border-nodo-400/30 rounded-2xl p-4 mb-6">
          <p class="font-black text-dark text-xs mb-1">📋 Proceso de activación</p>
          <p class="text-dark/70 text-xs leading-relaxed">Después de registrarte, visita nuestras instalaciones para registrar tu Face ID y activar tu acceso completo.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <!-- Nombre -->
          <div class="relative pt-5">
            <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Nombre completo</label>
            <input v-model="form.name" type="text" required autocomplete="name"
              class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400"
              placeholder="Tu nombre completo" />
            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
          </div>

          <!-- Email -->
          <div class="relative pt-5">
            <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Correo electrónico</label>
            <input v-model="form.email" type="email" required autocomplete="email"
              class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400"
              placeholder="tu@correo.com" />
            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
          </div>

          <!-- Teléfono + Empresa -->
          <div class="grid grid-cols-2 gap-4">
            <div class="relative pt-5">
              <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Teléfono</label>
              <input v-model="form.telefono" type="tel"
                class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400"
                placeholder="999 000 0000" />
            </div>
            <div class="relative pt-5">
              <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Empresa / Proyecto</label>
              <input v-model="form.empresa" type="text"
                class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400"
                placeholder="Nombre del proyecto" />
            </div>
          </div>

          <!-- Contraseña -->
          <div class="relative pt-5">
            <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Contraseña</label>
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password"
                class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400 pr-8"
                placeholder="Mínimo 8 caracteres" />
              <button type="button" @click="showPassword = !showPassword" data-cursor-hover
                class="absolute right-0 bottom-2 text-gray-400 hover:text-dark transition">
                <Eye v-if="!showPassword" :size="14" /><EyeOff v-else :size="14" />
              </button>
            </div>
            <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
          </div>

          <!-- Confirmar contraseña -->
          <div class="relative pt-5">
            <label class="absolute top-0 left-0 text-xs text-gray-500 font-medium">Confirmar contraseña</label>
            <div class="relative">
              <input v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" required
                class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark pb-2 text-sm focus:outline-none focus:border-nodo-400 transition placeholder-gray-400 pr-8"
                placeholder="Repite tu contraseña" />
              <button type="button" @click="showConfirm = !showConfirm" data-cursor-hover
                class="absolute right-0 bottom-2 text-gray-400 hover:text-dark transition">
                <Eye v-if="!showConfirm" :size="14" /><EyeOff v-else :size="14" />
              </button>
            </div>
          </div>

          <button type="submit" :disabled="form.processing" data-cursor-hover
            class="magnetic w-full bg-nodo-400 hover:bg-nodo-500 disabled:opacity-60 text-dark font-black py-3 rounded-full transition text-sm tracking-wide mt-2 hover:scale-[1.02]">
            {{ form.processing ? 'Creando cuenta...' : 'Crear cuenta' }}
          </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-4">
          Al registrarte aceptas nuestros <a href="#" class="underline">términos</a> y <a href="#" class="underline">política de privacidad</a>.
        </p>
      </div>
    </div>
  </div>
</template>
