<script setup lang="ts">
import SiteFooter from '@/Components/Public/SiteFooter.vue'
import SiteHeader from '@/Components/Public/SiteHeader.vue'
import StagingBanner from '@/Components/Public/StagingBanner.vue'
import Lenis from 'lenis'
import { onBeforeUnmount, onMounted } from 'vue'
import { Toaster } from 'vue-sonner'
import 'vue-sonner/style.css'

let lenis: Lenis | undefined
let raf: number | undefined

onMounted(() => {
  // El scroll suave se omite si el usuario pidió menos movimiento.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  lenis = new Lenis({ duration: 1.1, smoothWheel: true })

  const tick = (time: number) => {
    lenis?.raf(time)
    raf = requestAnimationFrame(tick)
  }
  raf = requestAnimationFrame(tick)
})

onBeforeUnmount(() => {
  if (raf !== undefined) cancelAnimationFrame(raf)
  lenis?.destroy()
  lenis = undefined
})
</script>

<template>
  <div class="min-h-screen bg-cream font-body text-dark">
    <a
      href="#contenido"
      class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[70] focus:rounded-lg
             focus:bg-nodo-400 focus:px-4 focus:py-2 focus:font-display focus:text-sm focus:font-bold focus:text-dark"
    >
      Saltar al contenido
    </a>

    <SiteHeader />

    <main id="contenido">
      <slot />
    </main>

    <SiteFooter />
    <StagingBanner />
    <Toaster position="bottom-right" rich-colors />
  </div>
</template>
