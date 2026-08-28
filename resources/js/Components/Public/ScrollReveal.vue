<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  /** Dirección de entrada. */
  from?: 'bottom' | 'left' | 'right' | 'scale'
  /** Retraso propio, en ms. */
  delay?: number
  /** Si es > 0, escalona los hijos directos con este intervalo en ms. */
  stagger?: number
  as?: string
}>(), {
  from: 'bottom',
  delay: 0,
  stagger: 0,
  as: 'div',
})

const el = ref<HTMLElement | null>(null)
const visible = ref(false)

const desplazamientos = {
  bottom: 'translateY(36px)',
  left: 'translateX(-44px)',
  right: 'translateX(44px)',
  scale: 'scale(0.94)',
}

/** Prepara los hijos para el escalonado antes de que entren en pantalla. */
function prepararHijos() {
  if (!props.stagger || !el.value) return
  Array.from(el.value.children).forEach((hijo, i) => {
    const h = hijo as HTMLElement
    h.style.opacity = '0'
    h.style.transform = 'translateY(24px)'
    h.style.transition = `opacity .6s cubic-bezier(.22,1,.36,1) ${i * props.stagger}ms,
                          transform .6s cubic-bezier(.22,1,.36,1) ${i * props.stagger}ms`
  })
}

function revelar() {
  visible.value = true

  if (props.stagger && el.value) {
    Array.from(el.value.children).forEach((hijo) => {
      const h = hijo as HTMLElement
      h.style.opacity = '1'
      h.style.transform = 'none'
    })
  }

  quitarEscuchas()
}

/**
 * Comprobación por scroll en lugar de IntersectionObserver: el observador no
 * dispara de forma fiable dentro de iframes ni tras un salto de scroll
 * programático, y el contenido se quedaba invisible para siempre.
 */
function comprobar() {
  if (visible.value || !el.value) return
  const caja = el.value.getBoundingClientRect()
  if (caja.top < window.innerHeight * 0.92 && caja.bottom > 0) {
    if (props.delay) window.setTimeout(revelar, props.delay)
    else revelar()
  }
}

function quitarEscuchas() {
  window.removeEventListener('scroll', comprobar)
  window.removeEventListener('resize', comprobar)
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    visible.value = true
    return
  }

  prepararHijos()
  comprobar()

  if (visible.value) return

  window.addEventListener('scroll', comprobar, { passive: true })
  window.addEventListener('resize', comprobar, { passive: true })
})

onBeforeUnmount(quitarEscuchas)
</script>

<template>
  <component
    :is="as"
    ref="el"
    :style="{
      opacity: visible || stagger ? 1 : 0,
      transform: visible || stagger ? 'none' : desplazamientos[from],
      transition: 'opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1)',
    }"
  >
    <slot />
  </component>
</template>
