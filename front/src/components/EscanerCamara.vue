<template>
  <div class="escaner">
    <div :id="elementId" class="escaner-video"/>
    <div v-if="error" class="escaner-error text-center q-pa-md"><q-icon name="no_photography" size="38px" color="grey-5"/><div class="text-caption q-mt-xs">{{error}}</div></div>
    <div v-else-if="!activo" class="escaner-error flex flex-center"><q-spinner color="positive" size="34px"/></div>
  </div>
</template>

<script setup>
// Lector de QR y códigos de barras con la cámara trasera del celular (html5-qrcode).
// La cámara sólo funciona en HTTPS o en localhost: por IP de la red local el navegador la bloquea.
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats as F } from 'html5-qrcode'

const emit = defineEmits(['detectado'])
const elementId = 'escaner-' + Math.random().toString(36).slice(2, 9)
const activo = ref(false), error = ref('')
let lector = null, ultimo = '', ultimoEn = 0

function leido (texto) {
  const codigo = String(texto || '').trim()
  // La cámara lee el mismo código varias veces por segundo: se ignora la repetición por 2 s.
  if (!codigo || (codigo === ultimo && Date.now() - ultimoEn < 2000)) return
  ultimo = codigo; ultimoEn = Date.now()
  if (navigator.vibrate) navigator.vibrate(80)
  emit('detectado', codigo)
}

onMounted(async () => {
  if (!window.isSecureContext) { error.value = 'La cámara necesita HTTPS (o localhost). Escriba el código a mano.'; return }
  try {
    lector = new Html5Qrcode(elementId, { verbose: false, formatsToSupport: [F.QR_CODE, F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.ITF] })
    await lector.start({ facingMode: 'environment' }, { fps: 10, qrbox: (w, h) => ({ width: Math.min(w * 0.85, 320), height: Math.min(h * 0.6, 180) }) }, leido, () => {})
    activo.value = true
  } catch (e) {
    error.value = 'No se pudo abrir la cámara: ' + (e?.message || e || 'permiso denegado')
  }
})

onBeforeUnmount(async () => {
  try { if (lector?.isScanning) await lector.stop(); lector?.clear() } catch { /* ya detenido */ }
})
</script>

<style scoped>
.escaner{position:relative;min-height:220px;background:#111111;border-radius:8px;overflow:hidden}.escaner-video{width:100%}.escaner-error{position:absolute;inset:0;color:#cfd8dc}
</style>
