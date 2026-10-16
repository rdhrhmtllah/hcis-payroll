<template>
    <div v-if="butir.length" class="pa-perilaku" :class="{ 'is-ringkas': ringkas }" data-uji="daftar-perilaku">
        <span v-if="judul" class="pa-perilaku__judul">
            <HcisIcon name="person-check" size="2xs" /> {{ judul }}
        </span>
        <ul role="list">
            <li v-for="(b, i) in butir" :key="i">{{ b }}</li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisIcon } from '@/components/ui';
import { pecahPerilaku } from '../utils/perilaku.js';

/* Daftar expected behaviour sebuah kompetensi. Kosong = tidak merender apa pun, supaya
   kompetensi yang belum diisi HC tidak meninggalkan judul tanpa isi. */
const props = defineProps({
    teks: { type: String, default: '' },
    judul: { type: String, default: 'Expected behaviour' },
    ringkas: { type: Boolean, default: false },
});

const butir = computed(() => pecahPerilaku(props.teks));
</script>

<style scoped>
.pa-perilaku {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-perilaku__judul {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #475569);
}

.pa-perilaku ul {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-perilaku li {
    position: relative;
    padding-left: 1rem;
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--hcis-color-text-main, #334155);
}

.pa-perilaku li::before {
    content: '';
    position: absolute;
    left: 0.2rem;
    top: 0.55em;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--hcis-color-text-muted, #94a3b8);
}

.pa-perilaku.is-ringkas li {
    font-size: 0.74rem;
}
</style>
