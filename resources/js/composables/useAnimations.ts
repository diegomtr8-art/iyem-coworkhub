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

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .stagger').forEach(el => {
      observer.observe(el)
    })
  })

  onUnmounted(() => observer?.disconnect())
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
