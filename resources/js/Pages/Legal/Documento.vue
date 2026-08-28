<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { AlertTriangle } from 'lucide-vue-next'

defineProps<{
  titulo: string
  descripcion: string
  /** Secciones del documento: título + párrafos. */
  secciones: { titulo: string; parrafos: string[] }[]
  provisional?: boolean
}>()
</script>

<template>
  <Head>
    <title>{{ titulo }} — Nódico</title>
    <meta name="description" :content="descripcion" />
  </Head>

  <PublicLayout>
    <section class="bg-tinta pb-16 pt-32 lg:pb-20 lg:pt-40">
      <div class="mx-auto max-w-3xl px-5 sm:px-8">
        <p class="etiqueta-tecnica mb-6 text-nodo-400">Legal</p>
        <h1 class="font-display text-display-lg font-extrabold text-white">{{ titulo }}</h1>
      </div>
    </section>

    <section class="bg-cream py-16 lg:py-24">
      <div class="mx-auto max-w-3xl px-5 sm:px-8">
        <div
          v-if="provisional"
          class="mb-12 flex gap-4 border-2 border-dark bg-nodo-400 p-5 shadow-dura-sm"
          role="note"
        >
          <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-dark" aria-hidden="true" />
          <p class="font-body text-sm leading-relaxed text-dark">
            <strong class="font-bold">Contenido provisional.</strong>
            Este documento es un marcador de posición y todavía no ha sido revisado por el área
            jurídica del Instituto Yucateco de Emprendedores. No debe considerarse el texto
            definitivo ni tiene validez legal en su estado actual.
          </p>
        </div>

        <div class="space-y-12">
          <div v-for="seccion in secciones" :key="seccion.titulo">
            <h2 class="font-display text-display-sm font-extrabold text-dark">{{ seccion.titulo }}</h2>
            <div class="mt-4 space-y-4">
              <p v-for="(parrafo, i) in seccion.parrafos" :key="i" class="font-body text-cuerpo leading-relaxed text-dark/75">
                {{ parrafo }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
