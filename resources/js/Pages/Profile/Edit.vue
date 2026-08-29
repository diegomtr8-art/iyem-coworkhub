<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import MisDatosLegales from './Partials/MisDatosLegales.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps<{
  mustVerifyEmail?: boolean
  status?: string
  consentimientos: Array<{ etiqueta: string; version: string; aceptadoEn: string; ip: string | null }>
  legalProvisional: boolean
}>()

const page = usePage()
const usuario = computed(() => (page.props.auth as any)?.user ?? null)

// Esta pantalla usaba siempre `AuthenticatedLayout`, así que un miembro que
// abría su perfil veía el armazón del panel operativo —con Planes, Reportes y
// Bitácora en el menú— sobre una cuenta que no puede entrar a ninguno de ellos.
const Layout = computed(() => (usuario.value?.esOperativo ? AuthenticatedLayout : PortalLayout))
</script>

<template>
  <Head title="Mi perfil" />

  <component :is="Layout">
    <div class="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Mi perfil</h1>
        <p class="mt-1 text-sm text-gray-500">Tus datos, tu contraseña y tus derechos sobre ellos.</p>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <UpdateProfileInformationForm
          :must-verify-email="mustVerifyEmail"
          :status="status"
          class="max-w-xl"
        />
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <UpdatePasswordForm class="max-w-xl" />
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <MisDatosLegales
          :consentimientos="consentimientos"
          :legal-provisional="legalProvisional"
        />
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <DeleteUserForm class="max-w-xl" />
      </div>
    </div>
  </component>
</template>
