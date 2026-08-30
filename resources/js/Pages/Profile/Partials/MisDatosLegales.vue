<script setup lang="ts">
/**
 * E — Qué aceptó cada persona, y sus derechos ARCO.
 *
 * La LFPDPPP reconoce acceso, rectificación, cancelación y oposición. Aquí
 * están los cuatro sin tener que escribirle a nadie: rectificar es el
 * formulario de arriba, acceder es la descarga, y cancelar es borrar la cuenta.
 */
import { Link } from '@inertiajs/vue3'
import { Download, FileCheck2, TriangleAlert } from 'lucide-vue-next'

defineProps<{
  consentimientos: Array<{
    etiqueta: string
    version: string
    aceptadoEn: string
    ip: string | null
  }>
  legalProvisional: boolean
}>()
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900">Tus datos y lo que aceptaste</h2>
      <p class="mt-1 text-sm text-gray-600">
        Qué documentos aceptaste, cuándo, y cómo llevarte una copia de tus datos.
      </p>
    </header>

    <!--
      BE-04 — Mientras el area juridica del IYEM no valide los textos, se dice.
      Recoger constancias sobre un documento sin validar es un mecanismo que
      funciona, pero no convierte el texto en valido: callarlo seria dar a
      entender lo contrario.
    -->
    <div
      v-if="legalProvisional"
      class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
      role="note"
    >
      <TriangleAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
      <p class="text-sm text-amber-900">
        Los textos legales están publicados en versión provisional, a la espera de la
        revisión del área jurídica del IYEM.
      </p>
    </div>

    <ul v-if="consentimientos.length" class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-200">
      <li
        v-for="(c, i) in consentimientos"
        :key="i"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
      >
        <div class="flex items-center gap-3">
          <FileCheck2 class="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
          <div>
            <p class="font-medium text-gray-900">{{ c.etiqueta }}</p>
            <p class="mt-0.5 text-sm text-gray-500">
              Versión {{ c.version }} · aceptado el {{ c.aceptadoEn }}
              <span v-if="c.ip"> · desde {{ c.ip }}</span>
            </p>
          </div>
        </div>
      </li>
    </ul>

    <p v-else class="mt-6 text-sm text-gray-500">
      Todavía no hay constancias registradas para tu cuenta.
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-4">
      <!--
        Descarga directa del navegador, no una petición de Inertia: por eso es
        un enlace normal y no un `<Link>`.
      -->
      <a
        :href="route('datos.descargar')"
        class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-gray-700"
      >
        <Download class="h-4 w-4" aria-hidden="true" />
        Descargar mis datos
      </a>

      <a
        :href="route('privacidad')"
        target="_blank"
        rel="noopener"
        class="text-sm font-medium text-gray-600 underline underline-offset-4 hover:text-gray-900"
      >Ver el aviso de privacidad</a>

      <a
        :href="route('terminos')"
        target="_blank"
        rel="noopener"
        class="text-sm font-medium text-gray-600 underline underline-offset-4 hover:text-gray-900"
      >Ver los términos</a>
    </div>

    <p class="mt-4 text-sm text-gray-500">
      Para dar de baja tu cuenta y tus datos, usa «Borrar la cuenta» más abajo. Si
      prefieres que lo hagamos nosotros, escríbenos y lo tramitamos.
    </p>
  </section>
</template>
