<template>
    <nav class="pa-tp-stepper" aria-label="Tahap penutupan siklus">
        <button
            type="button"
            class="pa-tp-ikhtisar"
            :class="{ 'is-aktif': aktif === 'ikhtisar' }"
            :aria-current="aktif === 'ikhtisar' ? 'step' : undefined"
            data-uji="tahap-ikhtisar"
            @click="$emit('pilih', 'ikhtisar')"
        >
            <HcisIcon name="speedometer2" size="xs" />
            <span>Ringkasan</span>
        </button>

        <ol class="pa-tp-langkah">
            <li v-for="(t, i) in daftar" :key="t.kode" class="pa-tp-langkah__item">
                <button
                    type="button"
                    class="pa-tp-langkah__tombol"
                    :class="[`is-${String(t.keadaan || 'MENUNGGU').toLowerCase()}`, { 'is-aktif': aktif === t.kode, 'is-saran': saran === t.kode }]"
                    :aria-current="aktif === t.kode ? 'step' : undefined"
                    :data-uji="`tahap-${t.kode}`"
                    :title="t.ringkas"
                    @click="$emit('pilih', t.kode)"
                >
                    <span class="pa-tp-langkah__no">
                        <HcisIcon v-if="t.keadaan === 'SELESAI'" name="check-lg" size="2xs" />
                        <HcisIcon v-else-if="t.keadaan === 'DILEWATI'" name="skip-forward-fill" size="2xs" />
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span class="pa-tp-langkah__teks">
                        <strong>{{ t.nama }}</strong>
                        <small>{{ LABEL_KEADAAN[t.keadaan] || 'Menunggu' }}<template v-if="t.penghambat?.length"> · {{ t.penghambat.length }} perlu dibereskan</template></small>
                    </span>
                    <span v-if="saran === t.kode && aktif !== t.kode" class="pa-tp-langkah__saran">Berikutnya</span>
                </button>
                <span v-if="i < daftar.length - 1" class="pa-tp-langkah__garis" :class="{ 'is-lewat': ['SELESAI', 'DILEWATI'].includes(t.keadaan) }" />
            </li>
        </ol>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { HcisIcon } from '@/components/ui';

import { LABEL_KEADAAN, TAHAP_PENUTUPAN } from './tahapPenutupan.js';

/* Stepper penutupan siklus. Keadaan tiap tahap datang dari server (PaPenutupanSiklusService);
   bila alurnya belum termuat, kelima tahap tetap tampil supaya admin bisa berpindah. */
const props = defineProps({
    alur: { type: Object, default: null },
    aktif: { type: String, default: 'ikhtisar' },
});
defineEmits(['pilih']);

const daftar = computed(() => TAHAP_PENUTUPAN.map((t) => ({ ...t, ...(props.alur?.tahap || []).find((x) => x.kode === t.kode) })));
const saran = computed(() => props.alur?.tahap_disarankan || null);
</script>

<style scoped>
.pa-tp-stepper {
    display: flex;
    align-items: stretch;
    gap: 0.6rem;
    width: 100%;
    overflow-x: auto;
    padding: 0.25rem 0.1rem;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.pa-tp-stepper::-webkit-scrollbar {
    display: none;
}
.pa-tp-ikhtisar {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    flex: 0 0 auto;
    min-width: 84px;
    min-height: 44px;
    padding: 0.45rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 150ms ease;
    user-select: none;
}
.pa-tp-ikhtisar:hover {
    border-color: #a5b4fc;
    color: #4338ca;
}
.pa-tp-ikhtisar.is-aktif {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}
.pa-tp-langkah {
    display: flex;
    align-items: center;
    flex: 1 1 auto;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    min-width: 720px;
}
.pa-tp-langkah__item {
    display: flex;
    align-items: center;
    flex: 1 1 0;
    min-width: 0;
}
.pa-tp-langkah__tombol {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex: 1 1 auto;
    min-width: 0;
    min-height: 44px;
    padding: 0.45rem 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s, transform 0.15s;
    user-select: none;
}
.pa-tp-langkah__tombol:hover {
    border-color: #a5b4fc;
    transform: translateY(-1px);
}
.pa-tp-langkah__tombol.is-aktif {
    border-color: #6366f1;
    background: #eef2ff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.pa-tp-langkah__no {
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 800;
}
.pa-tp-langkah__teks {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.pa-tp-langkah__teks strong {
    font-size: 0.8rem;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pa-tp-langkah__teks small {
    font-size: 0.68rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pa-tp-langkah__saran {
    position: absolute;
    top: -8px;
    right: 8px;
    padding: 0 0.4rem;
    border-radius: 999px;
    background: #4f46e5;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.03em;
}
.pa-tp-langkah__garis {
    flex: 0 0 14px;
    height: 2px;
    background: #e2e8f0;
}
.pa-tp-langkah__garis.is-lewat {
    background: #22c55e;
}

.is-selesai .pa-tp-langkah__no {
    background: #dcfce7;
    color: #15803d;
}
.is-berjalan .pa-tp-langkah__no {
    background: #4f46e5;
    color: #fff;
}
.is-dilewati .pa-tp-langkah__no {
    background: #f1f5f9;
    color: #94a3b8;
}
.is-dilewati .pa-tp-langkah__teks strong {
    color: #64748b;
}
.is-menunggu .pa-tp-langkah__teks strong {
    color: #64748b;
}
</style>
