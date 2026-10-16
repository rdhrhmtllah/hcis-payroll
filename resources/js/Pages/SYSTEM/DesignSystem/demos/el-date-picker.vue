<template>
    <div v-if="section === 'anatomy'" style="max-width: 28rem">
        <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-3 text-amber-900 text-xs">
            <strong>⚠️ Komponen Usang / Dipensiunkan:</strong> <code>el-date-picker</code> sudah dipensiunkan di seluruh HCIS. Harap gunakan <code>&lt;HcisDatePicker&gt;</code> untuk tanggal tunggal dan <code>&lt;HcisDatePicker type="daterange"&gt;</code> untuk rentang tanggal.
        </div>
        <label class="hcis-field">
            <span class="hcis-field__label">Tanggal mulai (Legacy)</span>
            <el-date-picker
                v-model="tanggal"
                type="date"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
                placeholder="Pilih tanggal"
                popper-class="ds-preview"
            />
        </label>
        <p class="ds-hint">
            <code>format</code> mengatur yang dilihat orang (DD/MM/YYYY), <code>value-format</code> mengatur
            yang dikirim ke server (ISO). Keduanya wajib ditetapkan — bawaan Element tidak dipakai.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div>
            <p class="ds-hint">type="date"</p>
            <el-date-picker v-model="tanggal" type="date" format="DD/MM/YYYY" value-format="YYYY-MM-DD" popper-class="ds-preview" />
        </div>
        <div>
            <p class="ds-hint">type="daterange"</p>
            <el-date-picker
                v-model="rentang" type="daterange" format="DD/MM/YYYY" value-format="YYYY-MM-DD"
                start-placeholder="Mulai" end-placeholder="Selesai" popper-class="ds-preview"
            />
        </div>
        <div>
            <p class="ds-hint">type="month"</p>
            <el-date-picker v-model="bulan" type="month" format="MM/YYYY" value-format="YYYY-MM" popper-class="ds-preview" />
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-grid hcis-grid--2">
        <div><p class="ds-hint">clearable</p><el-date-picker v-model="tanggal" type="date" format="DD/MM/YYYY" clearable popper-class="ds-preview" /></div>
        <div><p class="ds-hint">disabled</p><el-date-picker v-model="tanggal" type="date" format="DD/MM/YYYY" disabled popper-class="ds-preview" /></div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ section: { type: String, required: true } });

const tanggal = ref('2026-08-18');
const rentang = ref(['2026-08-18', '2026-08-21']);
const bulan = ref('2026-08');
</script>

<style scoped>
.ds-hint { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
code { background: var(--hcis-surface-soft); border-radius: var(--hcis-space-1); padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; }
</style>
