<template>
    <!-- Section: Anatomy -->
    <div v-if="section === 'anatomy'" class="ds-input-number-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <i class="bi bi-hash" /> HcisInputNumber (Komponent Standar HCIS)
                </span>
                <span class="ds-card__badge">Hold-to-Repeat & Wheel Support</span>
            </div>

            <div class="ds-card__body">
                <label class="hcis-field">
                    <span class="hcis-field__label">Jumlah Hari Cuti Tahunan</span>
                    <HcisInputNumber v-model="hari" :min="1" :max="12" suffix="Hari" />
                </label>

                <div class="ds-value-preview">
                    <span class="ds-value-preview__label">Nilai Terikat (v-model):</span>
                    <code class="ds-value-preview__code">{{ hari !== null ? hari : 'null' }}</code>
                </div>
            </div>

            <div class="ds-card__footer">
                <ul class="ds-features-list">
                    <li><i class="bi bi-check-circle-fill" /> <b>Hold-to-Repeat Stepper</b>: Tahan tombol + / - untuk menambah/mengurangi nilai secara cepat.</li>
                    <li><i class="bi bi-check-circle-fill" /> <b>Keyboard Navigasi & Mouse Wheel</b>: Gunakan panah <code>[↑]</code> / <code>[↓]</code> atau scroll mouse.</li>
                    <li><i class="bi bi-check-circle-fill" /> <b>Unit Prefix & Suffix</b>: Memberikan konteks seperti <code>"Hari"</code>, <code>"%"</code>, atau <code>"Rp"</code>.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <!-- Variant 1: Controls Position Default -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-sliders" /> Stepper Kiri & Kanan (Default)</span>
                <span class="ds-card__tag">controls-position="default"</span>
            </div>
            <div class="ds-card__body">
                <label class="hcis-field">
                    <span class="hcis-field__label">Jumlah Karyawan Approval Limit</span>
                    <HcisInputNumber v-model="hari" :min="1" :max="20" suffix="Orang" />
                </label>
            </div>
        </div>

        <!-- Variant 2: Controls Position Right -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-arrow-down-up" /> Stepper Tumpuk Kanan (Right)</span>
                <span class="ds-card__tag">controls-position="right"</span>
            </div>
            <div class="ds-card__body">
                <label class="hcis-field">
                    <span class="hcis-field__label">Batas Pengingat Kebijakan</span>
                    <HcisInputNumber v-model="hari" :min="1" :max="365" controls-position="right" suffix="Hari" />
                </label>
            </div>
        </div>

        <!-- Variant 3: Precision & Step -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-percent" /> Koefisien Lembur (Step & Precision)</span>
                <span class="ds-card__tag">:step="0.5" :precision="1"</span>
            </div>
            <div class="ds-card__body">
                <label class="hcis-field">
                    <span class="hcis-field__label">Pengali Koefisien Jam Kerja</span>
                    <HcisInputNumber v-model="koefisien" :min="0" :max="4" :step="0.5" :precision="1" suffix="x" />
                </label>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-stack-demo">
        <div class="ds-grid-2">
            <div class="ds-card">
                <div class="ds-card__header">
                    <span class="ds-card__title">Di Batas Maksimum (Disabled Button)</span>
                </div>
                <div class="ds-card__body">
                    <HcisInputNumber :model-value="12" :min="1" :max="12" suffix="Hari" />
                </div>
            </div>

            <div class="ds-card">
                <div class="ds-card__header">
                    <span class="ds-card__title">Disabled</span>
                </div>
                <div class="ds-card__body">
                    <HcisInputNumber :model-value="4" :min="1" :max="12" disabled suffix="Hari" />
                </div>
            </div>
        </div>

        <HcisAlert
            variant="warning"
            title="Bukan Untuk Nomor Identitas"
            description="NIK, No. HP, dan No. Transaksi adalah deretan angka tekstual, bukan besaran numerik. Menggunakan tombol stepper naik-turun pada NIK tidak diperbolehkan; gunakan HcisField."
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAlert, HcisInputNumber } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const hari = ref(4);
const koefisien = ref(1.5);
</script>

<style scoped>
.ds-input-number-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-width: 32rem;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-card__title i {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__badge,
.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
}

.ds-card__badge {
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__tag {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-family: monospace;
}

.ds-card__body {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ds-card__footer {
    padding: 0.75rem 1rem;
    background: var(--hcis-zinc-50);
    border-top: 1px solid var(--hcis-slate-100);
}

.ds-value-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.775rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ds-value-preview__code {
    background: var(--hcis-slate-100);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    font-weight: 700;
}

.ds-features-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    font-size: 0.75rem;
    color: var(--hcis-text, var(--hcis-slate-700));
}

.ds-features-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-features-list i {
    color: var(--hcis-success-500);
}

.ds-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 640px) {
    .ds-grid-2 {
        grid-template-columns: 1fr;
    }
}
</style>
