<template>
    <div v-if="section === 'anatomy'" style="max-width: 24rem" class="hcis-stack">
        <label class="hcis-field">
            <span class="hcis-field__label">Unit Organisasi</span>
            <el-select v-model="unit" placeholder="Pilih unit kerja" filterable clearable popper-class="ds-preview">
                <el-option v-for="o in opsi" :key="o.value" :label="o.label" :value="o.value" />
            </el-select>
        </label>
        <p class="ds-hint">
            Dibungkus <code>.hcis-field</code> agar label & pesan error senada dengan <code>HcisField</code>.
            Dropdown menggunakan <code>popper-class="ds-preview"</code> agar mengikuti token warna dan shadow HCIS.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div class="hcis-grid hcis-grid--2">
            <div>
                <p class="ds-hint"><strong>1. Multiple + Ringkasan Tag (max-collapse-tags="1")</strong></p>
                <el-select
                    v-model="banyak"
                    placeholder="Pilih beberapa unit"
                    multiple
                    clearable
                    collapse-tags
                    :max-collapse-tags="1"
                    collapse-tags-tooltip
                    popper-class="ds-preview"
                >
                    <el-option v-for="o in opsi" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <p class="ds-hint" style="margin-top: 6px;">Menampilkan 1 item pertama + lencana <code>+N</code> saat disorot kursor.</p>
            </div>
            <div>
                <p class="ds-hint"><strong>2. Multiple + 2 Tag (max-collapse-tags="2")</strong></p>
                <el-select
                    v-model="banyakGrup"
                    placeholder="Pilih beberapa divisi"
                    multiple
                    clearable
                    collapse-tags
                    :max-collapse-tags="2"
                    collapse-tags-tooltip
                    popper-class="ds-preview"
                >
                    <el-option-group v-for="g in grupOpsi" :key="g.label" :label="g.label">
                        <el-option v-for="o in g.options" :key="o.value" :label="o.label" :value="o.value" />
                    </el-option-group>
                </el-select>
                <p class="ds-hint" style="margin-top: 6px;">Menampilkan hingga 2 item + lencana ringkasan <code>+N</code>.</p>
            </div>
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-stack">
        <p class="ds-hint">Ukuran & Keadaan: Default (2.25rem), Small (2rem), Loading, & Disabled.</p>
        <div class="hcis-grid hcis-grid--3">
            <div>
                <p class="ds-hint">Standar (md)</p>
                <el-select v-model="unit" placeholder="Pilih unit" popper-class="ds-preview">
                    <el-option v-for="o in opsi" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
            </div>
            <div>
                <p class="ds-hint">Ukuran Kecil (sm)</p>
                <el-select v-model="unit" size="small" placeholder="Pilih unit" popper-class="ds-preview">
                    <el-option v-for="o in opsi" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
            </div>
            <div>
                <p class="ds-hint">Loading</p>
                <el-select v-model="kosong" placeholder="Memuat data..." loading popper-class="ds-preview" />
            </div>
        </div>
        <div class="hcis-grid hcis-grid--2">
            <div>
                <p class="ds-hint">Ternonaktifkan (Disabled)</p>
                <el-select v-model="unit" disabled popper-class="ds-preview">
                    <el-option label="Produksi" value="produksi" />
                </el-select>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ section: { type: String, required: true } });

const unit = ref('produksi');
const banyak = ref(['produksi', 'gudang', 'qc']);
const banyakGrup = ref(['op_pabrik', 'op_gudang', 'hc_talent']);
const pilihanGrup = ref('hc_talent');
const kosong = ref('');

const opsi = [
    { label: 'Produksi', value: 'produksi' },
    { label: 'Gudang & Logistik', value: 'gudang' },
    { label: 'Quality Control', value: 'qc' },
    { label: 'Human Capital', value: 'hc' },
];

const grupOpsi = [
    {
        label: 'Operasional',
        options: [
            { label: 'Manufaktur & Pabrik', value: 'op_pabrik' },
            { label: 'Gudang & Rantai Pasok', value: 'op_gudang' },
        ],
    },
    {
        label: 'Human Capital',
        options: [
            { label: 'Talent Acquisition', value: 'hc_talent' },
            { label: 'Industrial Relations', value: 'hc_ir' },
        ],
    },
];
</script>

<style scoped>
.ds-hint { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
code { background: var(--hcis-surface-soft); border-radius: var(--hcis-space-1); padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; }
</style>
