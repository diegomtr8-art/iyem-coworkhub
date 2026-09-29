<script setup lang="ts">
import Estado from '@/Components/Panel/Estado.vue'
import Panel from '@/Components/Panel/Panel.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ChevronRight, Globe } from 'lucide-vue-next'

defineProps<{
  paginas: Array<{ clave: string; titulo: string; descripcion: string; secciones: number; personalizadas: number }>
}>()
</script>

<template>
  <Head title="Página Web" />
  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Página Web</span></template>

    <div class="mb-6 flex items-start gap-3 border-2 border-dark bg-cream-50 p-4">
      <Globe :size="22" class="mt-0.5 shrink-0 text-dark" aria-hidden="true" />
      <div>
        <p class="font-display text-sm font-bold text-dark">Lo que se lee en nodico.com.mx</p>
        <p class="text-xs text-dark/70">
          Cambia textos, enlaces y datos de contacto sin esperar a nadie. Cada sección se guarda por separado,
          tiene vista previa antes de publicar y se puede deshacer. El diseño, los colores y el orden no se tocan desde aquí.
        </p>
      </div>
    </div>

    <Panel titulo="Páginas" :contador="paginas.length" padding="none">
      <ul class="divide-y divide-dark/10">
        <li v-for="p in paginas" :key="p.clave">
          <Link
            :href="route('pagina-web.editar', p.clave)"
            class="flex min-h-[64px] items-center gap-4 px-4 py-3 hover:bg-cream-50"
          >
            <div class="min-w-0 flex-1">
              <p class="font-display text-sm font-bold text-dark">{{ p.titulo }}</p>
              <p class="text-xs text-dark/70">{{ p.descripcion }}</p>
            </div>
            <Estado
              :tono="p.personalizadas ? 'bien' : 'neutro'"
              :texto="p.personalizadas ? `${p.personalizadas} de ${p.secciones} editadas` : `${p.secciones} secciones`"
              class="hidden sm:inline-flex"
            />
            <ChevronRight :size="18" class="shrink-0 text-dark/50" aria-hidden="true" />
          </Link>
        </li>
      </ul>
    </Panel>
  </AuthenticatedLayout>
</template>
