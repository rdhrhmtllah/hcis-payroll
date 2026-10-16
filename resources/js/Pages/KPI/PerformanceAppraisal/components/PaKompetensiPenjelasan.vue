<template>
    <div v-if="adaIsi" class="pa-kp" data-uji="penjelasan-kompetensi">
        <div v-if="deskripsi || butir.length" class="pa-kp__baris-info">
            <span
                v-if="deskripsi"
                class="pa-kp__deskripsi"
                :class="{ 'is-terpotong': !deskripsiPenuh && deskripsiPanjang }"
                data-uji="penjelasan-deskripsi"
            >
                {{ deskripsi }}
            </span>
            <button
                v-if="deskripsi && deskripsiPanjang"
                type="button"
                class="pa-kp__tautan"
                @click="deskripsiPenuh = !deskripsiPenuh"
            >
                {{ deskripsiPenuh ? 'Ringkas' : 'Selengkapnya' }}
            </button>
            <button
                v-if="butir.length"
                type="button"
                class="pa-kp__toggle"
                :aria-expanded="bukaPerilaku ? 'true' : 'false'"
                data-uji="penjelasan-toggle-perilaku"
                title="Lihat standar perilaku yang diharapkan"
                @click="bukaPerilaku = !bukaPerilaku"
            >
                <HcisIcon name="person-check" size="2xs" />
                <span>Perilaku ({{ butir.length }})</span>
                <HcisIcon :name="bukaPerilaku ? 'chevron-up' : 'chevron-down'" size="2xs" class="pa-kp__chevron" />
            </button>
        </div>
        <PaDaftarPerilaku v-if="bukaPerilaku" :teks="perilaku" judul="" ringkas class="pa-kp__daftar" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisIcon } from '@/components/ui';
import PaDaftarPerilaku from './PaDaftarPerilaku.vue';
import { pecahPerilaku } from '../utils/perilaku.js';

/* Penjelasan satu kompetensi untuk penilai: arti (deskripsi) dan perilaku yang
   diharapkan. Ringkas secara bawaan supaya matriks kalibrasi tetap rapat; `terbuka`
   dipakai tombol "Tampilkan semua penjelasan" di induknya. */
const props = defineProps({
    deskripsi: { type: String, default: '' },
    perilaku: { type: String, default: '' },
    terbuka: { type: Boolean, default: false },
});

const butir = computed(() => pecahPerilaku(props.perilaku));
const adaIsi = computed(() => Boolean(props.deskripsi) || butir.value.length > 0);
const deskripsiPanjang = computed(() => String(props.deskripsi || '').length > 110);

const bukaPerilaku = ref(props.terbuka);
const deskripsiPenuh = ref(props.terbuka);
watch(() => props.terbuka, (v) => {
    bukaPerilaku.value = v;
    deskripsiPenuh.value = v;
});
</script>

<style scoped>
.pa-kp {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
    margin-top: 0.25rem;
    max-width: 100%;
    text-align: left;
}

.pa-kp__baris-info {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.35rem 0.5rem;
    line-height: 1.4;
}

.pa-kp__deskripsi {
    margin: 0;
    font-size: 0.735rem;
    font-weight: 400;
    line-height: 1.4;
    color: var(--hcis-color-text-muted, #64748b);
    white-space: normal;
}

.pa-kp__deskripsi.is-terpotong {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.pa-kp__tautan {
    padding: 0;
    border: 0;
    background: none;
    font-size: 0.69rem;
    font-weight: 600;
    color: var(--hcis-color-brand-600, #4f46e5);
    cursor: pointer;
    line-height: 1.2;
}

.pa-kp__tautan:hover {
    text-decoration: underline;
}

.pa-kp__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-color-text-main, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.3;
    white-space: nowrap;
    align-self: center;
}

.pa-kp__toggle:hover,
.pa-kp__toggle:focus-visible {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: var(--hcis-color-text-main, #0f172a);
    outline: none;
}

.pa-kp__chevron {
    color: var(--hcis-color-text-muted, #94a3b8);
    transition: transform 0.15s ease;
}

.pa-kp__daftar {
    width: 100%;
    margin-top: 0.25rem;
    padding: 0.45rem 0.6rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    box-sizing: border-box;
}
</style>
