import { onMounted, onUnmounted } from 'vue'

export function useRevealOnScroll() {
  let observer: IntersectionObserver

  onMounted(() => {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible')
          }
        })
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    )

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .stagger').forEach(el => {
      observer.observe(el)
    })
  })

  onUnmounted(() => observer?.disconnect())
}

export function useCursor() {
  let outer: HTMLElement | null = null
  let inner: HTMLElement | null = null
  let mouseX = 0, mouseY = 0
  let outerX = 0, outerY = 0
  let raf: number

  function lerp(a: number, b: number, t: number) { return a + (b - a) * t }

  function tick() {
    outerX = lerp(outerX, mouseX, 0.12)
    outerY = lerp(outerY, mouseY, 0.12)
    if (outer) { outer.style.left = outerX + 'px'; outer.style.top = outerY + 'px' }
    if (inner) { inner.style.left = mouseX + 'px'; inner.style.top = mouseY + 'px' }
    raf = requestAnimationFrame(tick)
  }

  function onMove(e: MouseEvent) { mouseX = e.clientX; mouseY = e.clientY }
  function onEnterLink() { document.body.classList.add('cursor-hover') }
  function onLeaveLink() { document.body.classList.remove('cursor-hover') }
  function onMouseDown() { document.body.classList.add('cursor-click') }
  function onMouseUp() { document.body.classList.remove('cursor-click') }

  onMounted(() => {
    outer = document.getElementById('cursor-outer')
    inner = document.getElementById('cursor-inner')
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mousedown', onMouseDown)
    document.addEventListener('mouseup', onMouseUp)
    document.querySelectorAll('a, button, [data-cursor-hover]').forEach(el => {
      el.addEventListener('mouseenter', onEnterLink)
      el.addEventListener('mouseleave', onLeaveLink)
    })
    raf = requestAnimationFrame(tick)
  })

  onUnmounted(() => {
    cancelAnimationFrame(raf)
    document.body.classList.remove('cursor-hover', 'cursor-click')
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mousedown', onMouseDown)
    document.removeEventListener('mouseup', onMouseUp)
  })
}

export function useCounter(el: HTMLElement, target: number, duration = 1800) {
  const start = performance.now()
  function step(now: number) {
    const progress = Math.min((now - start) / duration, 1)
    const ease = 1 - Math.pow(1 - progress, 3)
    el.textContent = Math.floor(ease * target).toString()
    if (progress < 1) requestAnimationFrame(step)
    else el.textContent = target.toString()
  }
  requestAnimationFrame(step)
}

export function useTilt(el: HTMLElement) {
  const onMove = (e: MouseEvent) => {
    const rect = el.getBoundingClientRect()
    const x = ((e.clientX - rect.left) / rect.width - 0.5) * 14
    const y = ((e.clientY - rect.top) / rect.height - 0.5) * 14
    el.style.transform = `perspective(800px) rotateX(${-y}deg) rotateY(${x}deg) scale(1.02)`
  }
  const onLeave = () => { el.style.transform = 'perspective(800px) rotateX(0) rotateY(0) scale(1)' }
  el.addEventListener('mousemove', onMove)
  el.addEventListener('mouseleave', onLeave)
  return () => { el.removeEventListener('mousemove', onMove); el.removeEventListener('mouseleave', onLeave) }
}
