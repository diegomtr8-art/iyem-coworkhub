<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  from?: 'bottom' | 'left' | 'right' | 'scale'
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

let observador: IntersectionObserver | undefined
let temporizadorSeguridad: number | undefined
let quitarInertia: (() => void) | undefined

const desplazamientos = {
  bottom: 'translateY(36px)',
  left: 'translateX(-44px)',
  right: 'translateX(44px)',
  scale: 'scale(0.94)',
}

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
  if (visible.value) return
  visible.value = true

  if (props.stagger && el.value) {
    Array.from(el.value.children).forEach((hijo) => {
      const h = hijo as HTMLElement
      h.style.opacity = '1'
      h.style.transform = 'none'
    })
  }

  desconectar()
}

function desconectar() {
  observador?.disconnect()
  observador = undefined
  if (temporizadorSeguridad) window.clearTimeout(temporizadorSeguridad)
  temporizadorSeguridad = undefined
  window.removeEventListener('load', recomprobar)
  window.removeEventListener('orientationchange', recomprobar)
  quitarInertia?.()
  quitarInertia = undefined
}

/**
 * Reengancha el observador. IntersectionObserver es la herramienta correcta,
 * pero puede no disparar si el elemento ya estaba en pantalla cuando se montó
 * en una navegación de Inertia, o si el layout cambia al cargar imágenes.
 */
function recomprobar() {
  if (visible.value || !el.value) return

  const caja = el.value.getBoundingClientRect()
  if (caja.top < window.innerHeight && caja.bottom > 0) {
    revelar()
    return
  }

  observador?.unobserve(el.value)
  observador?.observe(el.value)
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    visible.value = true
    return
  }

  prepararHijos()

  if (typeof IntersectionObserver === 'undefined') {
    revelar()
    return
  }

  observador = new IntersectionObserver(
    (entradas) => {
      entradas.forEach((entrada) => {
        if (!entrada.isIntersecting) return
        if (props.delay) window.setTimeout(revelar, props.delay)
        else revelar()
      })
    },
    { threshold: 0.05, rootMargin: '0px 0px -40px 0px' },
  )

  if (el.value) observador.observe(el.value)

  // Redes de seguridad. Nunca debe quedar contenido invisible.
  window.addEventListener('load', recomprobar)
  window.addEventListener('orientationchange', recomprobar)
  quitarInertia = router.on('navigate', () => window.setTimeout(recomprobar, 50))

  // Último recurso: pase lo que pase, a los 3 s se revela.
  temporizadorSeguridad = window.setTimeout(revelar, 3000)
})

onBeforeUnmount(desconectar)
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
