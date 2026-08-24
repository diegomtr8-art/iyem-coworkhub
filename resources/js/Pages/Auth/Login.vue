<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Eye, EyeOff, X } from 'lucide-vue-next'
import { ref } from 'vue'
import { useCursor } from '@/composables/useAnimations'

defineProps<{ canResetPassword?: boolean; status?: string }>()

useCursor()

const showPassword = ref(false)
const form = useForm({ email: '', password: '', remember: false })
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') })
</script>

<template>
  <Head title="Iniciar sesión — Nódico" />

  <div id="cursor-outer" class="hidden lg:block" />
  <div id="cursor-inner" class="hidden lg:block" />

  <div class="cursor-zone min-h-screen bg-cream flex items-center justify-center p-6" style="font-family: 'Carmen Sans', sans-serif;">

    <!-- Card split -->
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

        <p class="relative text-dark/70 text-sm leading-relaxed mb-8 max-w-xs">
          Regístrate y aprovecha todos los beneficios de ser parte de <strong>Nódico.</strong> Conéctate con la comunidad, reserva espacios, revisa tus talleres y mantente al día con lo que tenemos para ti.
        </p>

        <Link :href="route('register')" data-cursor-hover
          class="magnetic relative inline-flex items-center justify-center bg-dark text-white font-black text-sm px-8 py-3 rounded-full hover:bg-dark/80 transition w-full max-w-xs hover:scale-105">
          ¡Regístrate!
        </Link>
      </div>

      <!-- RIGHT: Login form -->
      <div class="bg-cream p-10 lg:p-12 flex flex-col justify-center">
        <h2 class="text-3xl font-black text-dark leading-tight mb-8 text-center">
          Tu comunidad<br>
          <strong>te espera</strong>
        </h2>

        <div v-if="status" class="mb-6 text-sm text-green-700 bg-green-100 px-4 py-3 rounded-xl">{{ status }}</div>

        <form @submit.prevent="submit" class="space-y-8">
          <!-- Email -->
          <div class="relative">
            <label class="absolute -top-5 left-0 text-xs text-gray-500 font-medium">email</label>
            <input v-model="form.email" type="email" autocomplete="email" required
              class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark placeholder-transparent pb-2 text-sm focus:outline-none focus:border-nodo-400 transition"
              placeholder="email" />
            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
          </div>

          <!-- Password -->
          <div class="relative">
            <label class="absolute -top-5 left-0 text-xs text-gray-500 font-medium">contraseña</label>
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required
                class="w-full bg-transparent border-0 border-b-2 border-dark/30 text-dark placeholder-transparent pb-2 text-sm focus:outline-none focus:border-nodo-400 transition pr-8"
                placeholder="contraseña" />
              <button type="button" @click="showPassword = !showPassword" data-cursor-hover
                class="absolute right-0 bottom-2 text-gray-400 hover:text-dark transition">
                <Eye v-if="!showPassword" :size="16" />
                <EyeOff v-else :size="16" />
              </button>
            </div>
            <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
          </div>

          <!-- Remember + Forgot -->
          <div class="flex items-center justify-between text-xs text-gray-500">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.remember" class="rounded border-gray-300 text-nodo-500 w-3 h-3" />
              Remember me
            </label>
            <Link v-if="canResetPassword" :href="route('password.request')" data-cursor-hover
              class="hover:text-dark transition">Forgot your password?</Link>
          </div>

          <!-- Submit -->
          <button type="submit" :disabled="form.processing" data-cursor-hover
            class="magnetic w-full bg-nodo-400 hover:bg-nodo-500 disabled:opacity-60 text-dark font-black py-3 rounded-full transition text-sm tracking-wide hover:scale-[1.02]">
            {{ form.processing ? 'iniciando sesión...' : 'iniciar sesión' }}
          </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">
          ¿No tienes cuenta?
          <Link :href="route('register')" data-cursor-hover class="font-bold text-dark hover:text-nodo-500 transition ml-1">Regístrate aquí</Link>
        </p>
      </div>
    </div>
  </div>
</template>
