<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Search, Plus, Pencil, Trash2, Link2, Unlink, X, ScanFace, Camera, Upload, Monitor, RefreshCw, Check } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Panel from '@/Components/Panel/Panel.vue'
import Paginacion from '@/Components/Panel/Paginacion.vue'
import Estado from '@/Components/Panel/Estado.vue'

/** Los tres listados de personas con acceso (miembros / empleados / servicio social). */
const props = defineProps<{
  miembros: any
  buscar: string
  empleados: any[]
  servicioSocial: any[]
  dispositivos: { id: number; clave: string }[]
}>()

type Tab = 'miembros' | 'empleado' | 'servicio_social'
const tab = ref<Tab>('miembros')
const tabs: { clave: Tab; texto: string }[] = [
  { clave: 'miembros', texto: 'Miembros' },
  { clave: 'empleado', texto: 'Empleados' },
  { clave: 'servicio_social', texto: 'Servicio social' },
]
const esFicha = computed(() => tab.value !== 'miembros')
const listaFichas = computed(() => tab.value === 'empleado' ? props.empleados : props.servicioSocial)

// Búsqueda de miembros (server-side).
const buscar = ref(props.buscar ?? '')
let t: number | undefined
watch(buscar, () => {
  if (t) clearTimeout(t)
  t = window.setTimeout(() => router.get(route('personas.index'), { buscar: buscar.value || undefined },
    { preserveState: true, replace: true, only: ['miembros', 'buscar'] }), 350)
})

// ── Alta/edición de fichas (empleado / servicio social) ──────────────────────
const form = useForm<any>({
  id: null, categoria: 'empleado', nombre: '', correo: '', telefono: '',
  identificador: '', puesto: '', inicio: '', fin: '', activo: true, notas: '',
})
const modalFicha = ref(false)
function nueva() {
  form.reset(); form.clearErrors()
  form.categoria = tab.value === 'servicio_social' ? 'servicio_social' : 'empleado'
  form.id = null; modalFicha.value = true
}
function editar(p: any) {
  form.clearErrors()
  Object.assign(form, {
    id: p.id, categoria: tab.value, nombre: p.nombre, correo: p.correo ?? '',
    telefono: p.telefono ?? '', identificador: p.identificador ?? '', puesto: p.puesto ?? '',
    inicio: p.inicio ?? '', fin: p.fin ?? '', activo: !!p.activo, notas: p.notas ?? '',
  })
  modalFicha.value = true
}
function guardar() {
  const opciones = { preserveScroll: true, onSuccess: () => { modalFicha.value = false } }
  if (form.id) form.transform(d => d).patch(route('personas.update', form.id), opciones)
  else form.post(route('personas.store'), opciones)
}
function eliminar(p: any) {
  if (!confirm(`¿Eliminar a ${p.nombre} del listado?`)) return
  router.delete(route('personas.destroy', p.id), { preserveScroll: true })
}

// ── Vincular / desvincular FaceID ────────────────────────────────────────────
const face = useForm<any>({ tipo: 'persona', id: null, person_id: null })
const modalFace = ref(false)
const faceNombre = ref('')
function abrirFace(tipo: 'miembro' | 'persona', id: number, nombre: string) {
  face.reset(); face.clearErrors()
  face.tipo = tipo; face.id = id; faceNombre.value = nombre; modalFace.value = true
}
function vincularFace() {
  face.post(route('personas.vincular'), { preserveScroll: true, onSuccess: () => { modalFace.value = false } })
}
function desvincular(tipo: 'miembro' | 'persona', id: number) {
  if (!confirm('¿Quitar el rostro vinculado?')) return
  router.post(route('personas.desvincular'), { tipo, id }, { preserveScroll: true })
}

// ── Enrolar rostro: crear en Smart Pass y vincular al perfil ─────────────────
const enrolAbierto = ref(false)
const enrolSujeto = ref<{ tipo: 'miembro' | 'persona'; id: number; nombre: string } | null>(null)
const captura = ref<'archivo' | 'camara' | 'dispositivo'>('camara')
const fotoBase64 = ref('')            // dataURL: sirve de vista previa y de envío
const deviceId = ref<number | null>(props.dispositivos[0]?.id ?? null)
const enviando = ref(false)
const errorEnrol = ref('')
const camaraOn = ref(false)
let stream: MediaStream | null = null
const video = ref<HTMLVideoElement | null>(null)

function abrirEnrolar(tipo: 'miembro' | 'persona', id: number, nombre: string) {
  enrolSujeto.value = { tipo, id, nombre }
  captura.value = 'camara'; fotoBase64.value = ''; errorEnrol.value = ''
  deviceId.value = props.dispositivos[0]?.id ?? null
  enrolAbierto.value = true
}
function cerrarEnrolar() { detenerCamara(); enrolAbierto.value = false }

function onArchivo(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  if (f.size > 900_000) { errorEnrol.value = 'La imagen pesa demasiado (máx ~900 KB).'; return }
  const r = new FileReader()
  r.onload = () => { fotoBase64.value = String(r.result); errorEnrol.value = '' }
  r.readAsDataURL(f)
}

async function iniciarCamara() {
  errorEnrol.value = ''; fotoBase64.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 640 } })
    camaraOn.value = true
    await new Promise(r => setTimeout(r, 60))
    if (video.value) { video.value.srcObject = stream; await video.value.play() }
  } catch { errorEnrol.value = 'No se pudo abrir la cámara (permiso denegado o sin cámara).' }
}
function capturar() {
  if (!video.value) return
  const c = document.createElement('canvas')
  c.width = video.value.videoWidth || 480; c.height = video.value.videoHeight || 640
  c.getContext('2d')!.drawImage(video.value, 0, 0, c.width, c.height)
  fotoBase64.value = c.toDataURL('image/jpeg', 0.85)
  detenerCamara()
}
function detenerCamara() { stream?.getTracks().forEach(t => t.stop()); stream = null; camaraOn.value = false }
onUnmounted(detenerCamara)

// Descartar la foto y volver a capturar (cámara: reabre; archivo: limpia).
function volverATomar() {
  fotoBase64.value = ''; errorEnrol.value = ''
  if (captura.value === 'camara') iniciarCamara()
}

function enviarEnrolar() {
  if (!enrolSujeto.value) return
  const modo = captura.value === 'dispositivo' ? 'dispositivo' : 'foto'
  if (modo === 'foto' && !fotoBase64.value) { errorEnrol.value = 'Toma o sube una foto primero.'; return }
  enviando.value = true
  router.post(route('personas.enrolar'), {
    tipo: enrolSujeto.value.tipo, id: enrolSujeto.value.id, modo,
    foto_base64: modo === 'foto' ? fotoBase64.value : null,
    device_id: modo === 'dispositivo' ? deviceId.value : null,
  }, { preserveScroll: true, onFinish: () => { enviando.value = false }, onSuccess: cerrarEnrolar })
}

function elegirCaptura(c: 'archivo' | 'camara' | 'dispositivo') {
  detenerCamara(); fotoBase64.value = ''; errorEnrol.value = ''; captura.value = c
}
</script>

<template>
  <Head title="Personas con acceso" />

  <AuthenticatedLayout>
    <template #breadcrumb><span class="font-display font-bold text-dark">Personas con acceso</span></template>

    <!-- Pestañas -->
    <div class="mb-5 flex flex-wrap gap-1 border-b-2 border-dark/15">
      <button
        v-for="x in tabs" :key="x.clave" type="button" @click="tab = x.clave"
        class="min-h-[44px] border-b-2 px-4 font-display text-sm font-bold -mb-0.5"
        :class="tab === x.clave ? 'border-dark text-dark' : 'border-transparent text-dark/50 hover:text-dark'"
      >{{ x.texto }}</button>
    </div>

    <!-- MIEMBROS -->
    <Panel v-if="tab === 'miembros'" titulo="Miembros" :contador="miembros.total" padding="none">
      <div class="border-b border-dark/15 bg-cream-50 p-3">
        <div class="relative max-w-sm">
          <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true" />
          <input v-model="buscar" type="search" placeholder="Nombre o correo…" aria-label="Buscar" class="w-full border border-dark/25 bg-white py-2 pl-8 pr-3 text-sm focus:border-dark" />
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-cream-50 text-left font-display text-xs uppercase tracking-wide text-dark/60">
            <tr><th class="px-4 py-2">Nombre</th><th class="px-4 py-2">Correo</th><th class="px-4 py-2">Rostro (FaceID)</th><th class="px-4 py-2"></th></tr>
          </thead>
          <tbody class="divide-y divide-dark/10">
            <tr v-for="m in miembros.data" :key="m.id">
              <td class="px-4 py-2 font-display font-bold text-dark">{{ m.nombre }}</td>
              <td class="px-4 py-2 text-dark/70">{{ m.correo }}</td>
              <td class="px-4 py-2">
                <Estado v-if="m.person_id" tono="bien" :texto="'rostro ' + m.person_id" :punto="false" tamano="sm" />
                <span v-else class="text-dark/40">sin vincular</span>
              </td>
              <td class="px-4 py-2 text-right">
                <template v-if="!m.person_id">
                  <button type="button" @click="abrirEnrolar('miembro', m.id, m.nombre)" class="mr-3 inline-flex items-center gap-1 text-xs font-bold text-dark hover:underline"><Camera :size="14" /> Registrar rostro</button>
                  <button type="button" @click="abrirFace('miembro', m.id, m.nombre)" class="inline-flex items-center gap-1 text-xs text-dark/60 hover:text-dark" title="Vincular un rostro que ya existe en Smart Pass"><Link2 :size="14" /> Vincular</button>
                </template>
                <button v-else type="button" @click="desvincular('miembro', m.id)" class="inline-flex items-center gap-1 text-xs text-dark/60 hover:text-red-700"><Unlink :size="14" /> Quitar</button>
              </td>
            </tr>
            <tr v-if="!miembros.data.length"><td colspan="4" class="px-4 py-6 text-center text-dark/50">Sin miembros con ese filtro.</td></tr>
          </tbody>
        </table>
      </div>
      <Paginacion v-if="miembros.last_page > 1" :links="miembros.links" />
    </Panel>

    <!-- EMPLEADOS / SERVICIO SOCIAL (fichas) -->
    <Panel v-else :titulo="tab === 'empleado' ? 'Empleados' : 'Servicio social'" :contador="listaFichas.length" padding="none">
      <template #acciones>
        <button type="button" @click="nueva" class="flex min-h-[36px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-3 font-display text-xs font-bold text-dark">
          <Plus :size="14" aria-hidden="true" /> Nuevo
        </button>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-cream-50 text-left font-display text-xs uppercase tracking-wide text-dark/60">
            <tr>
              <th class="px-4 py-2">Nombre</th>
              <th class="px-4 py-2">{{ tab === 'empleado' ? 'Puesto' : 'Institución / carrera' }}</th>
              <th class="px-4 py-2">{{ tab === 'empleado' ? 'No. empleado' : 'Matrícula' }}</th>
              <th v-if="tab === 'servicio_social'" class="px-4 py-2">Periodo</th>
              <th class="px-4 py-2">Estado</th>
              <th class="px-4 py-2">Rostro</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-dark/10">
            <tr v-for="p in listaFichas" :key="p.id" :class="{ 'opacity-50': !p.activo }">
              <td class="px-4 py-2 font-display font-bold text-dark">{{ p.nombre }}<span v-if="p.correo" class="block text-xs font-normal text-dark/50">{{ p.correo }}</span></td>
              <td class="px-4 py-2 text-dark/70">{{ p.puesto || '—' }}</td>
              <td class="px-4 py-2 text-dark/70">{{ p.identificador || '—' }}</td>
              <td v-if="tab === 'servicio_social'" class="px-4 py-2 text-xs text-dark/70">{{ p.inicio || '—' }} → {{ p.fin || '—' }}</td>
              <td class="px-4 py-2"><Estado :tono="p.activo ? 'bien' : 'neutro'" :texto="p.activo ? 'activo' : 'baja'" :punto="false" tamano="sm" /></td>
              <td class="px-4 py-2">
                <Estado v-if="p.person_id" tono="bien" :texto="'rostro ' + p.person_id" :punto="false" tamano="sm" />
                <span v-else class="text-dark/40">—</span>
              </td>
              <td class="px-4 py-2">
                <div class="flex items-center justify-end gap-2">
                  <button v-if="!p.person_id" type="button" @click="abrirEnrolar('persona', p.id, p.nombre)" aria-label="Registrar rostro" title="Registrar rostro (crea en Smart Pass)" class="text-dark hover:text-nodo-600"><Camera :size="16" /></button>
                  <button v-if="!p.person_id" type="button" @click="abrirFace('persona', p.id, p.nombre)" aria-label="Vincular rostro existente" title="Vincular un rostro que ya existe" class="text-dark/60 hover:text-dark"><ScanFace :size="16" /></button>
                  <button v-else type="button" @click="desvincular('persona', p.id)" aria-label="Quitar rostro" class="text-dark/60 hover:text-red-700"><Unlink :size="16" /></button>
                  <button type="button" @click="editar(p)" aria-label="Editar" class="text-dark/60 hover:text-dark"><Pencil :size="16" /></button>
                  <button type="button" @click="eliminar(p)" aria-label="Eliminar" class="text-dark/60 hover:text-red-700"><Trash2 :size="16" /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!listaFichas.length"><td colspan="7" class="px-4 py-6 text-center text-dark/50">Aún no hay registros. Usa «Nuevo».</td></tr>
          </tbody>
        </table>
      </div>
    </Panel>

    <!-- Modal ficha -->
    <Teleport to="body">
      <div v-if="modalFicha" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="modalFicha = false">
        <form class="max-h-[92vh] w-full max-w-lg overflow-y-auto border-2 border-dark bg-white" @submit.prevent="guardar">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">{{ form.id ? 'Editar' : 'Nuevo' }} · {{ form.categoria === 'empleado' ? 'empleado' : 'servicio social' }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="modalFicha = false"><X :size="18" /></button>
          </div>
          <div class="grid gap-3 p-4 sm:grid-cols-2">
            <label class="sm:col-span-2 block text-xs font-bold text-dark">Nombre completo
              <input v-model="form.nombre" type="text" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
              <span v-if="form.errors.nombre" class="text-red-700">{{ form.errors.nombre }}</span>
            </label>
            <label class="block text-xs font-bold text-dark">Correo
              <input v-model="form.correo" type="email" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
              <span v-if="form.errors.correo" class="text-red-700">{{ form.errors.correo }}</span>
            </label>
            <label class="block text-xs font-bold text-dark">Teléfono
              <input v-model="form.telefono" type="text" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
            </label>
            <label class="block text-xs font-bold text-dark">{{ form.categoria === 'empleado' ? 'No. de empleado' : 'Matrícula' }}
              <input v-model="form.identificador" type="text" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
            </label>
            <label class="block text-xs font-bold text-dark">{{ form.categoria === 'empleado' ? 'Puesto' : 'Institución / carrera' }}
              <input v-model="form.puesto" type="text" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
            </label>
            <label class="block text-xs font-bold text-dark">{{ form.categoria === 'empleado' ? 'Fecha de alta' : 'Inicio del servicio' }}
              <input v-model="form.inicio" type="date" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
            </label>
            <label v-if="form.categoria === 'servicio_social'" class="block text-xs font-bold text-dark">Fin del servicio
              <input v-model="form.fin" type="date" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
              <span v-if="form.errors.fin" class="text-red-700">{{ form.errors.fin }}</span>
            </label>
            <label class="sm:col-span-2 block text-xs font-bold text-dark">Notas
              <textarea v-model="form.notas" rows="2" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark"></textarea>
            </label>
            <label class="sm:col-span-2 flex items-center gap-2 text-xs font-bold text-dark">
              <input v-model="form.activo" type="checkbox" class="h-4 w-4" /> Activo (cuenta con acceso)
            </label>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[40px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="modalFicha = false">Cancelar</button>
            <button type="submit" :disabled="form.processing" class="min-h-[40px] border-2 border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark disabled:opacity-50">Guardar</button>
          </div>
        </form>
      </div>
    </Teleport>

    <!-- Modal FaceID -->
    <Teleport to="body">
      <div v-if="modalFace" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="modalFace = false">
        <form class="w-full max-w-md border-2 border-dark bg-white" @submit.prevent="vincularFace">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">Vincular rostro · {{ faceNombre }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="modalFace = false"><X :size="18" /></button>
          </div>
          <div class="space-y-3 p-4">
            <p class="font-body text-xs text-dark/60">Escribe el número de persona (person_id) que Smart Pass le asignó. Lo ves en el registro de accesos como «Persona N».</p>
            <label class="block text-xs font-bold text-dark">person_id de Smart Pass
              <input v-model.number="face.person_id" type="number" min="1" class="mt-1 w-full border border-dark/25 px-2.5 py-2 text-sm font-normal focus:border-dark" />
              <span v-if="face.errors.person_id" class="text-red-700">{{ face.errors.person_id }}</span>
            </label>
          </div>
          <div class="flex justify-end gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[40px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="modalFace = false">Cancelar</button>
            <button type="submit" :disabled="face.processing" class="flex min-h-[40px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark disabled:opacity-50"><Link2 :size="14" /> Vincular</button>
          </div>
        </form>
      </div>
    </Teleport>
    <!-- Modal ENROLAR rostro -->
    <Teleport to="body">
      <div v-if="enrolAbierto" class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="cerrarEnrolar">
        <div class="max-h-[94vh] w-full max-w-lg overflow-y-auto border-2 border-dark bg-white">
          <div class="flex items-center justify-between border-b border-dark/15 bg-cream-50 px-4 py-3">
            <h2 class="font-display text-sm font-bold text-dark">Registrar rostro · {{ enrolSujeto?.nombre }}</h2>
            <button type="button" aria-label="Cerrar" class="text-dark/50 hover:text-dark" @click="cerrarEnrolar"><X :size="18" /></button>
          </div>

          <!-- Elegir modo de captura -->
          <div class="grid grid-cols-3 gap-2 p-4 pb-0">
            <button type="button" @click="elegirCaptura('archivo')" class="flex min-h-[60px] flex-col items-center justify-center gap-1 border-2 text-xs font-bold" :class="captura === 'archivo' ? 'border-dark bg-nodo-400' : 'border-dark/20 text-dark/70'"><Upload :size="18" /> Subir foto</button>
            <button type="button" @click="elegirCaptura('camara')" class="flex min-h-[60px] flex-col items-center justify-center gap-1 border-2 text-xs font-bold" :class="captura === 'camara' ? 'border-dark bg-nodo-400' : 'border-dark/20 text-dark/70'"><Camera :size="18" /> Cámara web</button>
            <button type="button" @click="elegirCaptura('dispositivo')" class="flex min-h-[60px] flex-col items-center justify-center gap-1 border-2 text-xs font-bold" :class="captura === 'dispositivo' ? 'border-dark bg-nodo-400' : 'border-dark/20 text-dark/70'"><Monitor :size="18" /> Desde el FR07</button>
          </div>

          <div class="p-4">
            <!-- FR07: el terminal captura; aquí no hay vista previa -->
            <div v-if="captura === 'dispositivo'" class="space-y-2">
              <div class="flex items-start gap-2 border-2 border-amber-500/50 bg-amber-50 p-3">
                <Monitor :size="18" class="mt-0.5 shrink-0 text-amber-700" aria-hidden="true" />
                <p class="font-body text-xs text-amber-900">La foto la toma el <b>terminal FR07</b>, así que <b>no se puede ver ni confirmar aquí</b>. Para revisar la foto antes de vincular, usa <b>Cámara web</b>.</p>
              </div>
              <p class="font-body text-xs text-dark/60">La persona se para frente al terminal. Elige el dispositivo:</p>
              <select v-model="deviceId" aria-label="Dispositivo" class="w-full border border-dark/25 bg-white px-2.5 py-2 text-sm focus:border-dark">
                <option v-for="d in dispositivos" :key="d.id" :value="d.id">{{ d.clave || ('Dispositivo ' + d.id) }}</option>
                <option v-if="!dispositivos.length" :value="null">— sin dispositivos vistos —</option>
              </select>
            </div>

            <!-- Archivo / cámara: capturar → ver la foto → confirmar o volver a tomar -->
            <template v-else>
              <!-- Paso 2 · vista previa + confirmar / volver a tomar -->
              <div v-if="fotoBase64" class="text-center">
                <p class="mb-2 font-display text-sm font-bold text-dark">¿Se ve bien la foto?</p>
                <div class="mb-2 flex justify-center">
                  <img :src="fotoBase64" alt="Foto capturada" class="max-h-64 border-2 border-dark object-cover" />
                </div>
                <p class="mb-3 font-body text-xs text-dark/55">Cara de frente, centrada y con buena luz. Si no, vuelve a tomarla.</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                  <button type="button" @click="volverATomar" class="flex min-h-[44px] items-center justify-center gap-1.5 border-2 border-dark bg-white px-4 font-display text-sm font-bold text-dark hover:bg-cream-50">
                    <RefreshCw :size="16" aria-hidden="true" /> Volver a tomar
                  </button>
                  <button type="button" :disabled="enviando" @click="enviarEnrolar" class="flex min-h-[44px] items-center justify-center gap-1.5 border-2 border-dark bg-nodo-400 px-4 font-display text-sm font-bold text-dark hover:bg-nodo-400/80 disabled:opacity-50">
                    <Check :size="16" aria-hidden="true" /> {{ enviando ? 'Vinculando…' : 'Confirmar y vincular' }}
                  </button>
                </div>
              </div>

              <!-- Paso 1 · capturar -->
              <div v-else>
                <div v-if="captura === 'archivo'">
                  <label class="flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed border-dark/40 px-4 py-6 font-display text-sm font-bold text-dark hover:border-dark">
                    <Upload :size="22" aria-hidden="true" /> Elegir imagen (JPG/PNG)
                    <input type="file" accept="image/jpeg,image/png" class="hidden" @change="onArchivo" />
                  </label>
                </div>
                <div v-else class="text-center">
                  <div v-show="camaraOn" class="mb-3 flex justify-center">
                    <video ref="video" class="max-h-64 border-2 border-dark" playsinline muted></video>
                  </div>
                  <button v-if="!camaraOn" type="button" @click="iniciarCamara" class="min-h-[44px] border-2 border-dark bg-white px-4 font-display text-sm font-bold text-dark hover:bg-nodo-400">Encender cámara</button>
                  <button v-else type="button" @click="capturar" class="mx-auto flex min-h-[44px] items-center justify-center gap-1.5 border-2 border-dark bg-nodo-400 px-6 font-display text-sm font-bold text-dark"><Camera :size="16" aria-hidden="true" /> Capturar foto</button>
                </div>
              </div>
            </template>

            <p v-if="errorEnrol" class="mt-3 text-center text-xs text-red-700">{{ errorEnrol }}</p>
          </div>

          <div class="flex items-center justify-between gap-2 border-t border-dark/15 bg-cream-50 px-4 py-3">
            <button type="button" class="min-h-[40px] border border-dark/25 px-4 font-display text-xs font-bold text-dark" @click="cerrarEnrolar">Cancelar</button>
            <button v-if="captura === 'dispositivo'" type="button" :disabled="enviando || !deviceId" @click="enviarEnrolar" class="flex min-h-[40px] items-center gap-1.5 border-2 border-dark bg-nodo-400 px-4 font-display text-xs font-bold text-dark disabled:opacity-50">
              <ScanFace :size="14" aria-hidden="true" /> {{ enviando ? 'Enviando…' : 'Tomar en el FR07' }}
            </button>
            <span v-else class="font-body text-xs text-dark/45">Al confirmar, el rostro queda vinculado en unos segundos.</span>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
