<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import { CalendarDays, Clock, CreditCard, Megaphone, LogIn, LogOut, Mic, Camera, Building2, AlertCircle, CheckCircle } from 'lucide-vue-next'

const props = defineProps<{
  suscripcion: any
  checkinActual: any
  proximasReservas: any[]
  comunicados: any[]
  anuncios: any[]
  resumen: any
}>()

const page = usePage()
const user = page.props.auth.user as any

const formEntrada = useForm({})
const formSalida  = useForm({})

const entrada = () => formEntrada.post(route('portal.checkin.entrada'))
const salida  = () => formSalida.post(route('portal.checkin.salida'))

const diasRestantes = props.suscripcion
  ? Math.max(0, Math.ceil((new Date(props.suscripcion.fecha_fin).getTime() - Date.now()) / 86400000))
  : 0

const porcentaje = (usado: number, max: number) => max > 0 ? Math.min(100, (usado / max) * 100) : 0

function hora(dt: string) {
  return new Date(dt).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

const tipoBadge: Record<string, string> = {
  info: 'bg-blue-50 text-blue-700', alerta: 'bg-red-50 text-red-600',
  pago: 'bg-nodo-50 text-nodo-700', reserva: 'bg-dark/5 text-dark',
  bienvenida: 'bg-emerald-50 text-emerald-700'
}
</script>

<template>
  <Head title="Mi Portal — NODICO" />
  <PortalLayout>
    <div class="space-y-6 max-w-5xl">

      <!-- Bienvenida -->
      <div>
        <h1 class="text-2xl font-black text-dark">Hola, {{ user.name.split(' ')[0] }} 👋</h1>
        <p class="text-gray-500 text-sm mt-1">Bienvenido a tu portal de Nodico</p>
      </div>

      <!-- Alerta Face ID -->
      <div v-if="!user.face_id_ok" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
        <AlertCircle :size="20" class="text-amber-500 flex-shrink-0 mt-0.5" />
        <div>
          <p class="font-semibold text-amber-800 text-sm">Registro de Face ID pendiente</p>
          <p class="text-amber-700 text-xs mt-0.5">Visita las instalaciones de Nodico para registrar tu Face ID y activar tu acceso completo.</p>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Col principal -->
        <div class="lg:col-span-2 space-y-5">

          <!-- Plan activo -->
          <div v-if="suscripcion" class="bg-gradient-to-br from-nodo-400 to-amber-300 text-dark rounded-2xl p-6 shadow-sm">
            <div class="flex items-start justify-between mb-5">
              <div>
                <p class="text-dark/60 text-xs font-semibold uppercase tracking-wide">Tu membresía activa</p>
                <h2 class="text-2xl font-black mt-1">{{ suscripcion.plan?.nombre }}</h2>
                <p class="text-dark/70 text-sm mt-0.5">{{ suscripcion.plan?.subtitulo }}</p>
              </div>
              <span class="bg-dark text-nodo-400 text-xs font-bold px-3 py-1.5 rounded-full">Activa</span>
            </div>

            <!-- Días hasta vencimiento -->
            <div class="bg-dark/10 rounded-xl p-4 mb-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-dark/70">Días hasta vencimiento</span>
                <span class="text-2xl font-black text-dark">{{ diasRestantes }}</span>
              </div>
              <div class="h-2 bg-dark/20 rounded-full overflow-hidden">
                <div class="h-full bg-dark rounded-full transition-all"
                  :style="`width: ${Math.min(100, (diasRestantes / 30) * 100)}%`" />
              </div>
            </div>

            <!-- Días de cowork para Flex/DayPass -->
            <div v-if="resumen?.dias_cowork_max" class="bg-dark/10 rounded-xl p-4 mb-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-dark/70">Días de coworking este mes</span>
                <span class="font-black text-dark">{{ resumen.dias_usados }} / {{ resumen.dias_cowork_max }}</span>
              </div>
              <div class="h-2 bg-dark/20 rounded-full overflow-hidden">
                <div class="h-full bg-dark rounded-full transition-all"
                  :style="`width: ${porcentaje(resumen.dias_usados, resumen.dias_cowork_max)}%`" />
              </div>
              <p class="text-xs text-dark/60 mt-1">{{ resumen.dias_cowork_max - resumen.dias_usados }} días restantes</p>
            </div>

            <!-- Horas sala -->
            <div v-if="resumen?.horas_sala_max" class="bg-dark/10 rounded-xl p-4 mb-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-dark/70 flex items-center gap-1.5">
                  <Building2 :size="12" /> Horas sala privada / juntas
                </span>
                <span class="font-black text-dark">{{ resumen.horas_sala_usadas.toFixed(1) }} / {{ resumen.horas_sala_max }}h</span>
              </div>
              <div class="h-2 bg-dark/20 rounded-full overflow-hidden">
                <div class="h-full bg-dark rounded-full transition-all"
                  :style="`width: ${porcentaje(resumen.horas_sala_usadas, resumen.horas_sala_max)}%`" />
              </div>
              <p class="text-xs text-dark/60 mt-1">{{ resumen.horas_sala_restantes.toFixed(1) }}h restantes · máx {{ resumen.max_horas_sala_dia }}h por día</p>
            </div>

            <!-- Horas contenido -->
            <div v-if="resumen?.horas_contenido_max" class="bg-dark/10 rounded-xl p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-dark/70 flex items-center gap-1.5">
                  <Mic :size="12" /> Horas estudio (podcast / foto)
                </span>
                <span class="font-black text-dark">{{ resumen.horas_contenido_usadas.toFixed(1) }} / {{ resumen.horas_contenido_max }}h</span>
              </div>
              <div class="h-2 bg-dark/20 rounded-full overflow-hidden">
                <div class="h-full bg-dark rounded-full transition-all"
                  :style="`width: ${porcentaje(resumen.horas_contenido_usadas, resumen.horas_contenido_max)}%`" />
              </div>
              <p class="text-xs text-dark/60 mt-1">{{ resumen.horas_contenido_restantes.toFixed(1) }}h restantes este mes</p>
            </div>
          </div>

          <!-- Sin membresía -->
          <div v-else class="bg-nodo-50 border-2 border-nodo-200 rounded-2xl p-6 text-center">
            <div class="w-14 h-14 bg-nodo-200 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <CreditCard :size="24" class="text-nodo-600" />
            </div>
            <p class="text-nodo-800 font-bold mb-1">Sin membresía activa</p>
            <p class="text-nodo-600 text-sm mb-4">Contacta a recepción de Nodico para activar tu plan.</p>
            <a href="mailto:contacto@nodico.com.mx" class="inline-block bg-nodo-400 hover:bg-nodo-500 text-dark font-bold px-5 py-2.5 rounded-xl text-sm transition">
              Contactar a Nodico
            </a>
          </div>

          <!-- Check-in rápido -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-bold text-dark mb-4 flex items-center gap-2">
              <Clock :size="18" class="text-nodo-500" /> Check-in rápido
            </h2>
            <div v-if="checkinActual">
              <div class="flex items-center gap-3 mb-4">
                <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse" />
                <p class="text-sm text-gray-600">Check-in activo desde <strong>{{ hora(checkinActual.hora_entrada) }}</strong></p>
              </div>
              <button @click="salida" :disabled="formSalida.processing"
                class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2.5 rounded-xl text-sm font-bold transition-all">
                <LogOut :size="15" /> Registrar salida
              </button>
            </div>
            <div v-else>
              <p class="text-sm text-gray-400 mb-4">No tienes check-in activo hoy.</p>
              <button @click="entrada" :disabled="formEntrada.processing || !suscripcion"
                class="flex items-center gap-2 bg-nodo-400 hover:bg-nodo-500 disabled:opacity-50 text-dark px-4 py-2.5 rounded-xl text-sm font-bold transition-all">
                <LogIn :size="15" /> Hacer check-in
              </button>
            </div>
          </div>

          <!-- Próximas reservas -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="font-bold text-dark flex items-center gap-2">
                <CalendarDays :size="18" class="text-nodo-500" /> Próximas reservas
              </h2>
              <Link :href="route('portal.reservar')" class="text-xs text-nodo-600 hover:text-nodo-700 font-semibold bg-nodo-50 px-3 py-1.5 rounded-lg">
                + Reservar
              </Link>
            </div>
            <div class="divide-y divide-gray-50">
              <div v-if="!proximasReservas.length" class="p-6 text-sm text-gray-400 text-center">
                Sin reservas próximas.
                <Link :href="route('portal.reservar')" class="text-nodo-600 font-semibold hover:underline ml-1">Hacer una reserva →</Link>
              </div>
              <div v-for="r in proximasReservas" :key="r.id" class="px-5 py-3.5 flex items-center gap-4">
                <div class="w-10 h-10 bg-nodo-50 rounded-xl flex items-center justify-center flex-shrink-0">
                  <span class="text-lg">
                    {{ r.espacio?.tipo === 'contenido' ? '🎙️' : r.espacio?.tipo === 'fotografia' ? '📸' : r.espacio?.tipo === 'sala_juntas' ? '👥' : r.espacio?.tipo === 'privado' ? '🏢' : '🖥️' }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-dark text-sm">{{ r.espacio?.nombre }}</p>
                  <p class="text-xs text-gray-400">{{ new Date(r.fecha).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }) }}</p>
                </div>
                <div class="text-xs text-right text-gray-400 flex-shrink-0">
                  {{ r.hora_inicio?.slice(0,5) }} – {{ r.hora_fin?.slice(0,5) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
          <!-- Companion (para Match) -->
          <div v-if="resumen?.personas === 2" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <h2 class="font-bold text-dark text-sm mb-3 flex items-center gap-2">
              <span class="text-nodo-500">👥</span> Tu compañero (Match)
            </h2>
            <div v-if="resumen.companion">
              <p class="text-sm font-semibold text-dark">{{ resumen.companion.name }}</p>
              <div class="flex items-center gap-2 mt-2">
                <CheckCircle v-if="resumen.companion_face_id_ok" :size="14" class="text-emerald-500" />
                <AlertCircle v-else :size="14" class="text-amber-500" />
                <span class="text-xs text-gray-500">
                  Face ID {{ resumen.companion_face_id_ok ? 'registrado' : 'pendiente' }}
                </span>
              </div>
            </div>
            <div v-else>
              <p class="text-xs text-gray-400">Acompañante no registrado aún. Contacta a Nodico.</p>
            </div>
          </div>

          <!-- Comunicados -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="p-4 border-b border-gray-100 flex items-center gap-2">
              <div class="w-2 h-2 bg-nodo-400 rounded-full" />
              <h2 class="font-bold text-dark text-sm">Comunicados</h2>
            </div>
            <div class="divide-y divide-gray-50">
              <div v-if="!comunicados.length" class="p-4 text-sm text-gray-400 text-center">Sin comunicados</div>
              <div v-for="c in comunicados" :key="c.id" class="p-4">
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', tipoBadge[c.tipo] ?? 'bg-gray-100 text-gray-600']">
                  {{ c.tipo.charAt(0).toUpperCase() + c.tipo.slice(1) }}
                </span>
                <p class="text-sm font-semibold text-dark mt-1.5">{{ c.titulo }}</p>
                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ c.mensaje }}</p>
              </div>
            </div>
          </div>

          <!-- Anuncios -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="p-4 border-b border-gray-100 flex items-center gap-2">
              <Megaphone :size="15" class="text-nodo-500" />
              <h2 class="font-bold text-dark text-sm">Anuncios Nodico</h2>
            </div>
            <div class="divide-y divide-gray-50">
              <div v-if="!anuncios.length" class="p-4 text-sm text-gray-400 text-center">Sin anuncios</div>
              <div v-for="a in anuncios" :key="a.id" class="p-4">
                <p class="text-sm font-semibold text-dark">{{ a.titulo }}</p>
                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ a.contenido }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
