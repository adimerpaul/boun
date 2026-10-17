<template>
  <div class="producto-rapido">
    <!-- Paso 1: leer o escribir el código -->
    <template v-if="!form">
      <EscanerCamara v-if="camara" class="q-mb-sm" @detectado="buscar"/>
      <q-input ref="codigoInput" v-model="codigo" dense outlined clearable autofocus label="Código o código de barras" @keydown.enter.prevent="buscar(codigo)">
        <template #prepend><q-icon name="qr_code_scanner"/></template>
        <template #append><q-btn dense flat round :icon="camara?'videocam_off':'photo_camera'" color="positive" @click="camara=!camara"><q-tooltip>{{camara?'Apagar cámara':'Leer con la cámara'}}</q-tooltip></q-btn></template>
      </q-input>
      <q-btn class="full-width q-mt-sm" unelevated no-caps color="positive" icon="search" label="Buscar" :loading="buscando" :disable="!String(codigo||'').trim()" @click="buscar(codigo)"/>
      <div class="text-caption text-grey-7 q-mt-sm">Si el producto existe se puede actualizar y sumarle stock; si no, se crea con ese código.</div>
    </template>

    <!-- Paso 2: crear / actualizar y cargar stock -->
    <q-form v-else class="row q-col-gutter-sm" @submit="guardar">
      <div class="col-12 row items-center no-wrap">
        <q-badge :color="form.id?'primary':'positive'" :label="form.id?'EXISTENTE':'NUEVO'" class="q-mr-sm"/>
        <div class="ellipsis"><b>{{form.codigo}}</b><span v-if="form.id" class="text-grey-7"> · stock actual {{cantidadTxt(form.stock_inicial)}} {{form.unidad}}</span></div>
        <q-space/><q-btn dense flat no-caps icon="arrow_back" label="Otro código" @click="reiniciar"/>
      </div>
      <q-input v-model="form.nombre" v-uppercase dense outlined autofocus label="Nombre *" class="col-12" :rules="[v=>!!String(v||'').trim()||'Requerido']" hide-bottom-space :readonly="soloLectura"/>
      <q-select v-model="form.categoria_id" :options="categorias" option-label="nombre" option-value="id" emit-value map-options dense outlined clearable label="Categoría" class="col-7" :readonly="soloLectura"/>
      <q-select v-model="form.unidad" :options="unidades" dense outlined label="Unidad" class="col-5" :readonly="soloLectura"/>
      <q-input v-model.number="form.precio_compra" type="number" min="0" step="0.01" dense outlined label="Costo" prefix="Bs" class="col-6" @update:model-value="seguirMargen"/>
      <q-input v-model.number="form.precio_venta" type="number" min="0" step="0.01" dense outlined label="Precio venta *" prefix="Bs" class="col-6" :readonly="soloLectura" :hint="margen!==null?`Margen ${margen}%`:''">
        <template v-if="margen!==null&&!soloLectura" #append><q-btn dense flat round size="sm" icon="percent" color="positive" :disable="!(form.precio_compra>0)" @click="aplicarMargen"><q-tooltip>Venta = costo + {{margen}}%</q-tooltip></q-btn></template>
      </q-input>

      <template v-if="puedeComprar">
        <div class="col-12"><q-separator/><div class="text-caption text-weight-bold text-positive q-mt-xs"><q-icon name="add_shopping_cart"/> Ingreso de stock (compra rápida)</div></div>
        <q-input ref="cantidadInput" v-model.number="form.cantidad" type="number" min="0" :step="form.unidad==='KG'?0.001:1" dense outlined :label="form.unidad==='KG'?'Cantidad (kg)':'Cantidad'" class="col-6" hint="0 = sólo guardar el producto"/>
        <q-select v-model="proveedor" :options="proveedores" option-label="nombre" dense outlined label="Proveedor" class="col-6" hint="Se recuerda para la próxima"/>
        <q-input v-model="form.lote" dense outlined label="Lote" class="col-6"/>
        <q-input v-model="form.fecha_vencimiento" type="date" dense outlined stack-label label="Vence" class="col-6"/>
      </template>

      <div class="col-12 q-mt-sm"><q-btn class="full-width" unelevated no-caps color="positive" icon="save" type="submit" :loading="guardando" :label="etiquetaGuardar"/></div>
    </q-form>
  </div>
</template>

<script setup>
// Carga rápida: con el código (cámara o teclado) crea el producto si no existe o lo actualiza,
// y opcionalmente le suma stock registrando una compra de una línea. Sirve para el inventario inicial.
import { computed, getCurrentInstance, nextTick, onMounted, ref } from 'vue'
import EscanerCamara from './EscanerCamara.vue'

const props = defineProps({ conCamara: { type: Boolean, default: false } })
const emit = defineEmits(['guardado'])
const { proxy } = getCurrentInstance()
const can = p => proxy.$store.hasPermission(p)
const CLAVE_PROVEEDOR = 'proveedorRapidoBuonPrezzo'

const camara = ref(props.conCamara), codigo = ref(''), buscando = ref(false), guardando = ref(false), form = ref(null), original = ref('')
const categorias = ref([]), unidadesCatalogo = ref([]), proveedores = ref([]), proveedor = ref(null), margenGeneral = ref(null)
const codigoInput = ref(null), cantidadInput = ref(null)

const unidades = computed(() => [...new Set(['UNIDAD', 'KG', 'PZS', ...unidadesCatalogo.value])])
const puedeComprar = computed(() => can('Crear Compras'))
const soloLectura = computed(() => !!form.value && (form.value.id ? !can('Editar Productos') : !can('Crear Productos')))
const margen = computed(() => { const c = categorias.value.find(x => x.id === form.value?.categoria_id); return c?.margen_activo ? Number(c.margen_porcentaje) : margenGeneral.value })
const cambioProducto = computed(() => !!form.value && JSON.stringify(datosProducto(form.value)) !== original.value)
const etiquetaGuardar = computed(() => { const n = Number(form.value?.cantidad) || 0; return n > 0 ? `Guardar e ingresar ${cantidadTxt(n)} ${form.value.unidad}` : (form.value?.id ? 'Guardar cambios' : 'Crear producto') })
const cantidadTxt = v => Number(v || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
const datosProducto = f => ({ codigo: f.codigo, codigo_barras: f.codigo_barras || null, nombre: String(f.nombre || '').trim().toUpperCase(), categoria_id: f.categoria_id || null, unidad: f.unidad, precio_compra: Number(f.precio_compra) || 0, precio_venta: Number(f.precio_venta) || 0 })
const errorApi = (e, def) => proxy.$alert.error(Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || def)

function aplicarMargen () { if (margen.value !== null && form.value.precio_compra > 0) form.value.precio_venta = Math.round(form.value.precio_compra * (1 + margen.value / 100) * 100) / 100 }
// En un producto nuevo la venta sigue al costo mientras haya margen; en uno existente se aplica con el botón.
function seguirMargen () { if (!form.value.id && !soloLectura.value) aplicarMargen() }

async function buscar (texto) {
  const q = String(texto || '').trim().toUpperCase()
  if (!q || buscando.value) return
  codigo.value = q; buscando.value = true
  try {
    const { data } = await proxy.$axios.get('/productos', { params: { q, per_page: 20 } })
    const p = (data.data || []).find(x => [x.codigo, x.codigo_barras].some(c => String(c || '').trim().toUpperCase() === q))
    if (!p && !can('Crear Productos')) return proxy.$alert.warning(`No existe un producto con código ${q}`)
    form.value = p
      ? { ...p, precio_compra: Number(p.precio_compra), precio_venta: Number(p.precio_venta), cantidad: null, lote: '', fecha_vencimiento: '' }
      : { id: null, codigo: q, codigo_barras: /^\d{8,14}$/.test(q) ? q : '', nombre: '', categoria_id: null, unidad: 'UNIDAD', precio_compra: 0, precio_venta: 0, stock_inicial: 0, cantidad: null, lote: '', fecha_vencimiento: '' }
    original.value = JSON.stringify(datosProducto(form.value))
    if (p) nextTick(() => cantidadInput.value?.focus())
  } catch (e) { errorApi(e, 'No se pudo buscar el producto') } finally { buscando.value = false }
}

function reiniciar () { form.value = null; codigo.value = ''; nextTick(() => codigoInput.value?.focus()) }

async function proveedorParaCompra () {
  if (proveedor.value) return proveedor.value
  // Sin proveedores cargados se crea uno genérico para no frenar la carga.
  const { data } = await proxy.$axios.post('/proveedores', { nombre: 'PROVEEDOR GENERAL' })
  proveedores.value.push(data); proveedor.value = data
  return data
}

async function guardar () {
  const f = form.value, cantidad = Number(f.cantidad) || 0
  if (cantidad < 0) return proxy.$alert.error('La cantidad no puede ser negativa')
  if (cantidad > 0 && !(Number(f.precio_compra) >= 0)) return proxy.$alert.error('Ingrese el costo')
  guardando.value = true
  try {
    let producto = f
    if (!f.id) producto = (await proxy.$axios.post('/productos', { ...datosProducto(f), stock_inicial: 0 })).data
    else if (cambioProducto.value && !soloLectura.value) producto = (await proxy.$axios.put(`/productos/${f.id}`, { ...datosProducto(f), stock_inicial: f.stock_inicial })).data
    let mensaje = f.id ? `${producto.nombre} actualizado` : `${producto.nombre} creado`
    if (cantidad > 0 && puedeComprar.value) {
      const prov = await proveedorParaCompra()
      const { data } = await proxy.$axios.post('/compras', { proveedor_id: prov.id, tipo_pago: 'EFECTIVO', comentario: 'Compra rápida', detalles: [{ producto_id: producto.id, cantidad, precio_unitario: Number(f.precio_compra) || 0, precio_venta: Number(f.precio_venta) || null, lote: f.lote || null, fecha_vencimiento: f.fecha_vencimiento || null }] })
      try { localStorage.setItem(CLAVE_PROVEEDOR, String(prov.id)) } catch { /* sin almacenamiento */ }
      mensaje = `${producto.nombre}: +${cantidadTxt(cantidad)} ${f.unidad} (compra ${data.numero})`
    }
    proxy.$alert.success(mensaje)
    emit('guardado', producto)
    reiniciar()
  } catch (e) { errorApi(e, 'No se pudo guardar') } finally { guardando.value = false }
}

onMounted(() => {
  proxy.$axios.get('/productos-catalogos').then(({ data }) => { categorias.value = data.categorias; unidadesCatalogo.value = data.unidades || [] }).catch(() => {})
  proxy.$axios.get('/configuracion').then(({ data }) => { margenGeneral.value = data.margen_activo ? Number(data.margen_porcentaje ?? 30) : null }).catch(() => {})
  if (can('Ver Compras')) proxy.$axios.get('/proveedores').then(({ data }) => {
    proveedores.value = data
    let guardado = null; try { guardado = Number(localStorage.getItem(CLAVE_PROVEEDOR)) } catch { /* sin almacenamiento */ }
    proveedor.value = data.find(p => p.id === guardado) || data[0] || null
  }).catch(() => {})
})
</script>

<style scoped>
.producto-rapido{max-width:520px;margin:0 auto}
</style>
