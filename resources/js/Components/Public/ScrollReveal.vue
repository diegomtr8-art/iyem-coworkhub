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
let observer: IntersectionObserver | undefined

const desplazamientos = {
  bottom: 'translateY(36px)',
  left: 'translateX(-44px)',
  right: 'translateX(44px)',
  scale: 'scale(0.94)',
}

/** Prepara los hijos para el escalonado antes de que se vean. */
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

function mostrarHijos() {
  if (!props.stagger || !el.value) return
  Array.from(el.value.children).forEach((hijo) => {
    const h = hijo as HTMLElement
    h.style.opacity = '1'
    h.style.transform = 'none'
  })
}

onMounted(() => {
  const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (sinMovimiento || typeof IntersectionObserver === 'undefined') {
    visible.value = true
    return
  }

  prepararHijos()

  observer = new IntersectionObserver(
    (entradas) => {
      entradas.forEach((entrada) => {
        if (!entrada.isIntersecting) return
        window.setTimeout(() => {
          visible.value = true
          mostrarHijos()
        }, props.delay)
        observer?.unobserve(entrada.target)
      })
    },
    { threshold: 0.1, rootMargin: '0px 0px -60px 0px' },
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
      opacity: visible || stagger ? 1 : 0,
      transform: visible || stagger ? 'none' : desplazamientos[from],
      transition: 'opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1)',
    }"
  >
    <slot />
  </component>
</template>
