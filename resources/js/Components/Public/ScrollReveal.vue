<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  /** Dirección de entrada de la animación. */
  from?: 'bottom' | 'left' | 'right' | 'scale'
  /** Retraso en milisegundos, útil para escalonar tarjetas de un grid. */
  delay?: number
  /** Etiqueta HTML del contenedor. */
  as?: string
}>(), {
  from: 'bottom',
  delay: 0,
  as: 'div',
})

const el = ref<HTMLElement | null>(null)
const visible = ref(false)
let observer: IntersectionObserver | undefined

const transforms = {
  bottom: 'translateY(32px)',
  left: 'translateX(-40px)',
  right: 'translateX(40px)',
  scale: 'scale(0.94)',
}

onMounted(() => {
  // Sin animación si el usuario pidió menos movimiento, o si no hay soporte.
  const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (sinMovimiento || typeof IntersectionObserver === 'undefined') {
    visible.value = true
    return
  }

  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return
        window.setTimeout(() => (visible.value = true), props.delay)
        observer?.unobserve(entry.target)
      })
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
  )

  if (el.value) observer.observe(el.value)
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <component
    :is="as"
    ref="el"
    :style="{
      opacity: visible ? 1 : 0,
      transform: visible ? 'none' : transforms[from],
      transition: 'opacity .7s ease, transform .7s ease',
    }"
  >
    <slot />
  </component>
</template>
