<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import QRCode from 'qrcode'

/**
 * Tablero público de ocupación (Fases 3-5). Va en zona pública: NO muestra
 * nombres, solo el estado del espacio y la hora. Se actualiza solo, sobrevive a
 * caídas de red y cuida la pantalla.
 */
const props = defineProps<{
  token: string
  inicial: { espacios: any[]; resumen: any; hora: string }
}>()

const datos = reactive<{ espacios: any[]; resumen: any }>({
  espacios: props.inicial.espacios ?? [],
  resumen: props.inicial.resumen ?? {},
})
const conectado = ref(true)
const ultimoOk = ref<number>(Date.now())
const ahora = ref(new Date())

// ── Reloj ──────────────────────────────────────────────────────────────────
let relojT: number | undefined
const hora = computed(() => ahora.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }))
const fecha = computed(() => ahora.value.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' }))
const haceCuanto = computed(() => {
  const s = Math.max(0, Math.round((ahora.value.getTime() - ultimoOk.value) / 1000))
  if (s < 60) return `hace ${s} s`
  return `hace ${Math.round(s / 60)} min`
})

// ── Sondeo con reconexión (Fase 5) ───────────────────────────────────────────
let sondeoT: number | undefined
let backoff = 0
async function traerDatos() {
  try {
    const r = await fetch(`/tablero/${props.token}/datos`, { headers: { Accept: 'application/json' }, cache: 'no-store' })
    if (!r.ok) throw new Error(String(r.status))
    const j = await r.json()
    datos.espacios = j.espacios
    datos.resumen = j.resumen
    conectado.value = true
    ultimoOk.value = Date.now()
    backoff = 0
  } catch {
    conectado.value = false          // se avisa en pantalla; no se muestran datos viejos como buenos
    backoff = Math.min(backoff + 1, 6)
  }
}
function programarSondeo() {
  if (sondeoT) clearTimeout(sondeoT)
  const espera = conectado.value ? 30000 : Math.min(30000, 3000 * 2 ** (backoff - 1))
  sondeoT = window.setTimeout(async () => { await traerDatos(); programarSondeo() }, espera)
}

// Al despertar la tableta o volver la pestaña: refrescar de inmediato (Fase 5).
function alDespertar() { if (document.visibilityState === 'visible') { traerDatos(); programarSondeo() } }

onMounted(() => {
  relojT = window.setInterval(() => { ahora.value = new Date() }, 1000)
  programarSondeo()
  document.addEventListener('visibilitychange', alDespertar)
})
onUnmounted(() => {
  if (relojT) clearInterval(relojT)
  if (sondeoT) clearTimeout(sondeoT)
  document.removeEventListener('visibilitychange', alDespertar)
})

// ── Atenuar fuera de horario + anti-quemado (Fase 5) ─────────────────────────
const salas = computed(() => datos.espacios.filter((e: any) => e.tipo !== 'coworking'))
const cerradoTodo = computed(() => salas.value.length > 0 && salas.value.every((e: any) => e.estado === 'fuera_horario'))
// Desplazamiento sutil cada 5 min (respeta prefers-reduced-motion vía CSS).
const nudge = ref(0)
let nudgeT: number | undefined
onMounted(() => { nudgeT = window.setInterval(() => { nudge.value = (nudge.value + 1) % 4 }, 5 * 60 * 1000) })
onUnmounted(() => { if (nudgeT) clearInterval(nudgeT) })
const desplazado = computed(() => `translate(${(nudge.value % 2) * 3}px, ${Math.floor(nudge.value / 2) * 3}px)`)

// ── Mapa de espacios → regiones del plano ─────────────────────────────────────
const porTipo = (t: string) => datos.espacios.filter((e: any) => e.tipo === t)
const region = computed(() => {
  const priv = porTipo('privado')
  return {
    cowork:  porTipo('coworking')[0],
    priv1: priv[0], priv2: priv[1], priv3: priv[2], priv4: priv[3],
    juntas1: porTipo('sala_juntas')[0],
    podcast: porTipo('contenido')[0],
    foto: porTipo('fotografia')[0],
  } as Record<string, any>
})

const FILL: Record<string, string> = {
  libre: '#35C08A', ocupada: 'url(#hatch)', aparta_pronto: 'url(#dots)',
  bloqueada: 'url(#cross)', fuera_horario: '#3a3735',
}
const TXT: Record<string, string> = {
  libre: '#12201a', ocupada: '#fff', aparta_pronto: '#2a1e05',
  bloqueada: '#0d141f', fuera_horario: 'rgba(244,241,234,.5)',
}
const fill = (e: any) => e ? (FILL[e.estado] ?? '#3a3735') : '#3a3735'
const txt = (e: any) => e ? (TXT[e.estado] ?? '#fff') : 'rgba(244,241,234,.5)'
function dato(e: any): string {
  if (!e) return '—'
  if (e.estado === 'libre') return 'LIBRE'
  if (e.estado === 'ocupada') return `hasta ${e.hasta}`
  if (e.estado === 'aparta_pronto') return `▸ ${e.desde}`
  if (e.estado === 'bloqueada') return 'BLOQUEADA'
  if (e.estado === 'fuera_horario') return 'CERRADO'
  return ''
}

// ── Toque: agenda del día (Fase 4) ───────────────────────────────────────────
const sel = ref<any>(null)
const agenda = ref<any>(null)
const qr = ref<string>('')
let cerrarT: number | undefined
async function abrir(e: any) {
  if (!e) return
  sel.value = e
  agenda.value = null; qr.value = ''
  reprogramarCierre()
  try {
    const r = await fetch(`/tablero/${props.token}/espacio/${e.id}/agenda`, { headers: { Accept: 'application/json' } })
    agenda.value = await r.json()
  } catch { agenda.value = { abierto: false, ocupados: [] } }
  const url = `${location.origin}/portal/reservar`
  qr.value = await QRCode.toDataURL(url, { margin: 1, width: 320, color: { dark: '#1A1918', light: '#F4F1EA' } })
}
function cerrar() { sel.value = null; if (cerrarT) clearTimeout(cerrarT) }
// Vuelve al plano solo si nadie interactúa en 45 s.
function reprogramarCierre() { if (cerrarT) clearTimeout(cerrarT); cerrarT = window.setTimeout(cerrar, 45000) }

// Huecos del día = franja menos ocupados (lo que la persona busca).
const timeline = computed(() => {
  const a = agenda.value
  if (!a || !a.abierto) return { min: 0, span: 1, bloques: [], huecos: [] }
  const m = (s: string) => { const [h, mm] = s.split(':').map(Number); return h * 60 + mm }
  const ini = m(a.apertura), fin = m(a.cierre), span = Math.max(1, fin - ini)
  const ocup = [...a.ocupados].map((o: any) => ({ ...o, a: m(o.inicio), b: m(o.fin) })).sort((x, y) => x.a - y.a)
  const bloques = ocup.map((o: any) => ({ ...o, izq: (o.a - ini) / span * 100, w: (o.b - o.a) / span * 100 }))
  const huecos: any[] = []; let cur = ini
  for (const o of ocup) { if (o.a > cur) huecos.push({ ini: cur, fin: o.a }); cur = Math.max(cur, o.b) }
  if (cur < fin) huecos.push({ ini: cur, fin })
  const hh = (x: number) => `${String(Math.floor(x / 60)).padStart(2, '0')}:${String(x % 60).padStart(2, '0')}`
  return { min: ini, span, bloques, huecos: huecos.map(h => ({ izq: (h.ini - ini) / span * 100, w: (h.fin - h.ini) / span * 100, txt: `${hh(h.ini)}–${hh(h.fin)}` })) }
})
</script>

<template>
  <Head title="Tablero" />
  <div class="tv" :class="{ cerrado: cerradoTodo }" @pointerdown="reprogramarCierre">
    <!-- Banner de desconexión -->
    <div v-if="!conectado" class="offline">Sin conexión — reintentando. Los datos pueden no estar al día.</div>

    <div class="top">
      <div class="marca">Nódico · <b>En vivo</b></div>
      <div class="kpis">
        <div class="kpi"><span class="n">{{ datos.resumen.salas_libres ?? '–' }}/{{ datos.resumen.salas_totales ?? '–' }}</span><span class="l">Salas libres</span></div>
        <div class="kpi" v-if="datos.resumen.coworking_pct != null"><span class="n cw">{{ datos.resumen.coworking_pct }}%</span><span class="l">Coworking · {{ datos.resumen.coworking_dentro }} dentro</span></div>
      </div>
      <div class="reloj"><div class="h">{{ hora }}</div><div class="f">{{ fecha }}</div></div>
    </div>

    <div class="cuerpo">
      <div class="plano-caja">
        <svg viewBox="0 0 1600 1000" role="img" aria-label="Plano de Nódico en vivo" :style="{ transform: desplazado }">
          <defs>
            <pattern id="hatch" width="10" height="10" patternTransform="rotate(45)" patternUnits="userSpaceOnUse"><rect width="10" height="10" fill="#F0575F"/><line x1="0" y1="0" x2="0" y2="10" stroke="rgba(0,0,0,.28)" stroke-width="5"/></pattern>
            <pattern id="dots" width="12" height="12" patternUnits="userSpaceOnUse"><rect width="12" height="12" fill="#F5B740"/><circle cx="6" cy="6" r="2.4" fill="rgba(0,0,0,.30)"/></pattern>
            <pattern id="cross" width="12" height="12" patternUnits="userSpaceOnUse"><rect width="12" height="12" fill="#8193AC"/><path d="M0 0L12 12M12 0L0 12" stroke="rgba(0,0,0,.32)" stroke-width="2.5"/></pattern>
          </defs>

          <rect x="56" y="66" width="928" height="884" fill="none" stroke="rgba(244,241,234,.28)" stroke-width="3"/>

          <!-- Contexto inerte -->
          <g class="ctx">
            <rect x="72" y="80" width="196" height="212"/><text x="170" y="188" font-size="22" text-anchor="middle">Zona de eventos</text>
            <rect x="72" y="462" width="162" height="88"/><text x="153" y="510" font-size="15" text-anchor="middle">Servicio</text>
            <rect x="628" y="80" width="342" height="118"/><text x="770" y="145" font-size="22" text-anchor="middle">Entrada</text>
            <rect x="426" y="80" width="26" height="118"/>
            <rect x="420" y="212" width="286" height="108"/><text x="563" y="272" font-size="20" text-anchor="middle">Recepción</text>
            <rect x="404" y="336" width="326" height="226"/><text x="560" y="440" font-size="20" text-anchor="middle">Área de descanso</text><text x="560" y="466" font-size="20" text-anchor="middle">y comedor</text>
            <rect x="736" y="366" width="96" height="196"/><text x="784" y="470" font-size="17" text-anchor="middle" transform="rotate(-90 784 470)">Cafetería</text>
            <rect x="330" y="958" width="118" height="22"/><rect x="500" y="958" width="118" height="22"/><rect x="670" y="958" width="118" height="22"/>
          </g>

          <!-- Cubículos privados -->
          <g class="reg" @click="abrir(region.priv4)"><rect x="72" y="560" width="162" height="94" :fill="fill(region.priv4)"/><text x="153" y="600" font-size="19" text-anchor="middle" :fill="txt(region.priv4)">Cubículo 4</text><text x="153" y="628" font-size="17" font-weight="900" text-anchor="middle" :fill="txt(region.priv4)">{{ dato(region.priv4) }}</text></g>
          <g class="reg" @click="abrir(region.priv3)"><rect x="72" y="662" width="162" height="94" :fill="fill(region.priv3)"/><text x="153" y="702" font-size="19" text-anchor="middle" :fill="txt(region.priv3)">Cubículo 3</text><text x="153" y="730" font-size="17" font-weight="900" text-anchor="middle" :fill="txt(region.priv3)">{{ dato(region.priv3) }}</text></g>
          <g class="reg" @click="abrir(region.priv2)"><rect x="72" y="764" width="162" height="94" :fill="fill(region.priv2)"/><text x="153" y="804" font-size="19" text-anchor="middle" :fill="txt(region.priv2)">Cubículo 2</text><text x="153" y="832" font-size="17" font-weight="900" text-anchor="middle" :fill="txt(region.priv2)">{{ dato(region.priv2) }}</text></g>
          <g class="reg" @click="abrir(region.priv1)"><rect x="72" y="866" width="162" height="80" :fill="fill(region.priv1)"/><text x="153" y="900" font-size="19" text-anchor="middle" :fill="txt(region.priv1)">Cubículo 1</text><text x="153" y="926" font-size="17" font-weight="900" text-anchor="middle" :fill="txt(region.priv1)">{{ dato(region.priv1) }}</text></g>

          <!-- Sala de Juntas (única) -->
          <g class="reg" @click="abrir(region.juntas1)"><rect x="72" y="300" width="196" height="150" :fill="fill(region.juntas1)"/><text x="170" y="358" font-size="21" text-anchor="middle" :fill="txt(region.juntas1)">Sala de Juntas</text><text x="170" y="394" font-size="24" font-weight="900" text-anchor="middle" :fill="txt(region.juntas1)">{{ dato(region.juntas1) }}</text></g>

          <!-- Coworking (3 mesas = una zona) -->
          <g class="reg" @click="abrir(region.cowork)">
            <rect x="300" y="606" width="470" height="286" rx="4" fill="#2b3a3a" stroke="#34C4C4" stroke-width="2.5"/>
            <rect x="316" y="626" width="438" height="52" fill="#20302f"/><rect x="316" y="626" :width="4.38 * (region.cowork?.porcentaje ?? 0)" height="52" fill="#34C4C4"/>
            <rect x="316" y="722" width="438" height="52" fill="#20302f"/><rect x="316" y="722" :width="4.38 * (region.cowork?.porcentaje ?? 0)" height="52" fill="#34C4C4"/>
            <rect x="316" y="818" width="438" height="52" fill="#20302f"/><rect x="316" y="818" :width="4.38 * (region.cowork?.porcentaje ?? 0)" height="52" fill="#34C4C4"/>
            <text x="535" y="756" font-size="40" font-weight="900" text-anchor="middle" fill="#34C4C4">{{ region.cowork?.porcentaje ?? 0 }}%</text>
            <text x="535" y="786" font-size="18" text-anchor="middle" fill="#34C4C4" font-family="'Space Mono',monospace">COWORKING · {{ region.cowork?.personas_dentro ?? 0 }} DENTRO</text>
          </g>

          <!-- Islas (fuera del piso principal): Podcast y Fotografía -->
          <rect x="1044" y="120" width="504" height="470" fill="none" stroke="rgba(244,241,234,.20)" stroke-width="2" stroke-dasharray="6 6"/>
          <g class="reg" @click="abrir(region.podcast)"><rect x="1076" y="152" width="440" height="190" :fill="fill(region.podcast)"/><text x="1296" y="234" font-size="22" text-anchor="middle" :fill="txt(region.podcast)">Sala de Podcast</text><text x="1296" y="270" font-size="18" font-weight="900" text-anchor="middle" :fill="txt(region.podcast)">{{ dato(region.podcast) }}</text></g>
          <g class="reg" @click="abrir(region.foto)"><rect x="1076" y="372" width="440" height="190" :fill="fill(region.foto)"/><text x="1296" y="454" font-size="22" text-anchor="middle" :fill="txt(region.foto)">Sala de Fotografía</text><text x="1296" y="490" font-size="18" font-weight="900" text-anchor="middle" :fill="txt(region.foto)">{{ dato(region.foto) }}</text></g>
          <text x="1296" y="620" font-size="15" text-anchor="middle" fill="rgba(244,241,234,.38)" font-family="'Space Mono',monospace">FUERA DEL PISO PRINCIPAL</text>
        </svg>
      </div>

      <aside class="leyenda">
        <h3>Estados</h3>
        <div class="li"><span class="sw" style="background:#35C08A"></span>Libre</div>
        <div class="li"><span class="sw" style="background:#F0575F"></span>Ocupada</div>
        <div class="li"><span class="sw" style="background:#F5B740"></span>Aparta pronto</div>
        <div class="li"><span class="sw" style="background:#8193AC"></span>Bloqueada</div>
        <div class="li"><span class="sw" style="background:#3a3735"></span>Fuera de horario</div>
        <div class="li"><span class="sw" style="background:#34C4C4"></span>Coworking (% dentro)</div>
        <div class="actu"><span class="pt" :class="{ mal: !conectado }"></span> Actualizado {{ haceCuanto }}</div>
      </aside>
    </div>

    <!-- Detalle: agenda del día -->
    <div v-if="sel" class="modal" @click.self="cerrar">
      <div class="tarjeta">
        <button class="x" @click="cerrar" aria-label="Cerrar">×</button>
        <h2>{{ sel.nombre }}</h2>
        <p class="meta">Capacidad {{ sel.capacidad }} · <span v-if="agenda?.abierto">abierto {{ agenda.apertura }}–{{ agenda.cierre }}</span><span v-else>cerrado hoy</span></p>

        <div class="ag" v-if="agenda?.abierto">
          <div class="barra">
            <div v-for="(h, i) in timeline.huecos" :key="'h'+i" class="hueco" :style="{ left: h.izq + '%', width: h.w + '%' }" :title="h.txt"></div>
            <div v-for="(b, i) in timeline.bloques" :key="'b'+i" class="ocup" :class="b.tipo" :style="{ left: b.izq + '%', width: b.w + '%' }">{{ b.inicio }}</div>
          </div>
          <p class="huecos-txt"><b>Libre:</b> <span v-if="timeline.huecos.length" v-for="(h, i) in timeline.huecos" :key="i" class="chip">{{ h.txt }}</span><span v-else>hoy no queda hueco</span></p>
        </div>
        <p v-else class="cerrado-hoy">Hoy no abre. No hay agenda que mostrar.</p>

        <div class="qr" v-if="qr"><img :src="qr" alt="QR para reservar" /><span>Escanea para reservar en el portal</span></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;900&family=Space+Mono:wght@700&display=swap');
.tv{position:fixed;inset:0;background:#1A1918;color:#F4F1EA;font-family:'Archivo',system-ui,sans-serif;
  display:flex;flex-direction:column;padding:2.2vh 2vw;gap:1.6vh;overflow:hidden;transition:opacity 1s;}
.tv.cerrado{opacity:.42;}
.offline{position:absolute;top:0;left:0;right:0;background:#F0575F;color:#2a0d0f;text-align:center;
  font-weight:900;padding:1vh;font-size:2vh;z-index:5;}
.top{display:flex;align-items:center;gap:2.2vw;flex-shrink:0;}
.marca{font-weight:900;font-size:2.6vh;letter-spacing:-.02em;} .marca b{color:#FFE124;}
.kpis{display:flex;gap:2.4vw;margin-left:auto;} .kpi{display:flex;flex-direction:column;line-height:1;}
.kpi .n{font-weight:900;font-size:3.4vh;} .kpi .n.cw{color:#34C4C4;}
.kpi .l{font-size:1.35vh;letter-spacing:.14em;text-transform:uppercase;color:rgba(244,241,234,.6);font-family:'Space Mono',monospace;margin-top:.5vh;}
.reloj{text-align:right;line-height:1;} .reloj .h{font-weight:900;font-size:3.4vh;font-variant-numeric:tabular-nums;}
.reloj .f{font-size:1.35vh;color:rgba(244,241,234,.6);text-transform:uppercase;letter-spacing:.1em;font-family:'Space Mono',monospace;margin-top:.5vh;}
.cuerpo{flex:1;display:flex;gap:1.4vw;min-height:0;}
.plano-caja{flex:1;background:#232120;border:2px solid rgba(244,241,234,.2);min-height:0;display:flex;align-items:center;justify-content:center;padding:1.4vh 1vw;}
svg{width:100%;height:100%;transition:transform 2s ease;}
.reg{cursor:pointer;} .reg rect{stroke:rgba(0,0,0,.4);stroke-width:2.5;} .reg text{font-family:'Archivo',sans-serif;font-weight:700;pointer-events:none;}
.ctx rect{fill:#332f2d;stroke:rgba(244,241,234,.14);stroke-width:2;} .ctx text{fill:rgba(244,241,234,.4);font-family:'Public Sans',sans-serif;font-weight:600;}
.leyenda{width:15vw;min-width:190px;background:#232120;border:2px solid rgba(244,241,234,.2);padding:1.8vh 1.2vw;display:flex;flex-direction:column;gap:1.5vh;}
.leyenda h3{margin:0;font-size:1.5vh;letter-spacing:.16em;text-transform:uppercase;color:rgba(244,241,234,.6);font-family:'Space Mono',monospace;}
.li{display:flex;align-items:center;gap:.8vw;font-size:1.9vh;font-weight:600;}
.sw{width:2.4vh;height:2.4vh;flex-shrink:0;border:2px solid rgba(0,0,0,.35);}
.actu{margin-top:auto;font-family:'Space Mono',monospace;font-size:1.3vh;color:rgba(244,241,234,.4);display:flex;align-items:center;gap:.5vw;}
.pt{width:1vh;height:1vh;border-radius:50%;background:#35C08A;} .pt.mal{background:#F0575F;}
.modal{position:fixed;inset:0;background:rgba(15,14,13,.72);display:flex;align-items:center;justify-content:center;z-index:10;padding:3vh;}
.tarjeta{position:relative;background:#232120;border:2px solid #F4F1EA;max-width:720px;width:100%;padding:3.2vh 3vw;}
.tarjeta h2{margin:0;font-size:3.4vh;font-weight:900;} .meta{color:rgba(244,241,234,.65);margin:.6vh 0 2.4vh;font-size:2vh;}
.x{position:absolute;top:1.6vh;right:1.6vw;background:none;border:none;color:#F4F1EA;font-size:5vh;line-height:1;cursor:pointer;}
.barra{position:relative;height:6vh;background:#1A1918;border:2px solid rgba(244,241,234,.2);margin-bottom:1.6vh;}
.hueco{position:absolute;top:0;bottom:0;background:rgba(53,192,138,.28);border-left:2px solid #35C08A;}
.ocup{position:absolute;top:0;bottom:0;background:#F0575F;color:#2a0d0f;font-weight:900;font-size:1.5vh;display:flex;align-items:center;padding-left:.4vw;overflow:hidden;}
.ocup.bloqueo{background:#8193AC;}
.huecos-txt{font-size:2vh;} .chip{display:inline-block;background:rgba(53,192,138,.2);border:1.5px solid #35C08A;color:#7ee0b6;padding:.3vh .7vw;margin:.3vh;font-family:'Space Mono',monospace;font-size:1.7vh;}
.cerrado-hoy{color:rgba(244,241,234,.6);font-size:2.2vh;}
.qr{display:flex;flex-direction:column;align-items:center;gap:1vh;margin-top:2.4vh;}
.qr img{width:22vh;height:22vh;image-rendering:pixelated;border:6px solid #F4F1EA;} .qr span{font-size:1.7vh;color:rgba(244,241,234,.6);font-family:'Space Mono',monospace;}
@media (prefers-reduced-motion: reduce){ svg{transition:none;} .tv{transition:none;} }
</style>
