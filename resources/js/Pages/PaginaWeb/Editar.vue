<script setup lang="ts">
import SeccionDelSitio from '@/Components/Panel/SeccionDelSitio.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, reactive } from 'vue'

defineProps<{
  pagina: { clave: string; titulo: string; descripcion: string }
  secciones: Array<any>
}>()

/** Secciones con cambios sin guardar, para no dejar salir sin avisar. */
const sucias = reactive(new Set<string>())
const haySinGuardar = computed(() => sucias.size > 0)

function marcar(clave: string, sucia: boolean) {
  sucia ? sucias.add(clave) : sucias.delete(clave)
}

const aviso = 'Hay cambios sin guardar. Si sales, se pierden.'

function alSalirDelNavegador(e: BeforeUnloadEvent) {
  if (!haySinGuardar.value) return
  e.preventDefault()
  e.returnValue = aviso
}

// Solo navegaciones a otra página: guardar, deshacer o la vista previa son
// peticiones de esta misma pantalla y no deben preguntar.
const quitarGuardia = router.on('before', (evento) => {
  const visita = evento.detail.visit
  if (visita.method !== 'get' || !haySinGuardar.value) return
  if (!confirm(aviso)) evento.preventDefault()
})

onMounted(() => window.addEventListener('beforeunload', alSalirDelNavegador))
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', alSalirDelNavegador)
  quitarGuardia()
})
</script>

<template>
  <Head :title="`${pagina.titulo} — Página Web`" />
  <AuthenticatedLayout>
    <template #breadcrumb>
      <Link :href="route('pagina-web.index')" class="hover:text-dark">Página Web</Link>
      <span class="mx-1" aria-hidden="true">/</span>
      <span class="font-display font-bold text-dark">{{ pagina.titulo }}</span>
    </template>

    <div class="max-w-3xl space-y-5">
      <div class="flex items-start gap-3">
        <Link
          :href="route('pagina-web.index')"
          class="flex h-11 w-11 shrink-0 items-center justify-center border border-dark/25 text-dark hover:border-dark"
          aria-label="Volver a Página Web"
        >
          <ArrowLeft :size="18" aria-hidden="true" />
        </Link>
        <div>
          <h1 class="font-display text-2xl font-extrabold text-dark">{{ pagina.titulo }}</h1>
          <p class="text-sm text-dark/70">{{ pagina.descripcion }}</p>
        </div>
      </div>

      <SeccionDelSitio
        v-for="seccion in secciones"
        :key="seccion.clave"
        :seccion="seccion"
        @sucia="marcar"
      />
    </div>
  </AuthenticatedLayout>
</template>
