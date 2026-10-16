<template>
    <!--
        Muncul HANYA saat mode gerak tenang menyala.

        Sebelumnya saklarnya cuma ada di satu demo, dan pilihannya permanen —
        jadi orang bisa menyalakannya, berpindah halaman, lalu menghabiskan
        waktu lama menyimpulkan "animasinya rusak". Penanda ini menutup jarak
        antara tempat keadaan diubah dan tempat akibatnya terasa.
    -->
    <Transition name="hcis-fade">
        <div v-if="tenang" class="ds-motion-banner" role="status">
            <span class="ds-motion-banner__dot" aria-hidden="true" />

            <span class="ds-motion-banner__teks">
                <strong>Mode gerak tenang</strong>
                <span class="ds-motion-banner__ket">
                    Animasi sengaja diredam — ini pilihanmu, bukan kerusakan.
                </span>
            </span>

            <button type="button" class="ds-motion-banner__aksi" @click="kembalikan">
                Kembalikan gerak penuh
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';
import { MODE_PENUH, MODE_TENANG, modeGerakAktif, setelModeGerak } from '@/motionMode';

const tenang = computed(() => modeGerakAktif.value === MODE_TENANG);

function kembalikan() {
    setelModeGerak(MODE_PENUH);
}
</script>

<style scoped>
.ds-motion-banner {
    position: fixed;
    right: var(--hcis-space-4);
    bottom: var(--hcis-space-4);
    /* Penanda keadaan, bukan lapisan modal: ia harus terlihat di atas isi
       halaman, tapi tetap di bawah dialog dan pesan sistem. */
    z-index: var(--hcis-z-sticky);
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    max-width: min(26rem, calc(100vw - var(--hcis-space-8)));
    padding: var(--hcis-space-3) var(--hcis-space-4);
    border: 1px solid var(--hcis-warning-200);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-warning-50);
    box-shadow: 0 10px 30px -8px rgb(var(--hcis-shadow-rgb) / 0.18);
}

/* Titik diam, bukan berdenyut: memberi tahu keadaan, bukan menuntut perhatian —
   dan menaruh denyut abadi di penanda mode tenang jelas bertentangan sendiri. */
.ds-motion-banner__dot {
    flex-shrink: 0;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-warning-500);
}

.ds-motion-banner__teks {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: var(--hcis-warning-700);
}

.ds-motion-banner__teks strong {
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-bold);
}

.ds-motion-banner__ket {
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
}

.ds-motion-banner__aksi {
    flex-shrink: 0;
    padding: var(--hcis-space-2) var(--hcis-space-3);
    border: 1px solid var(--hcis-warning-500);
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface);
    color: var(--hcis-warning-700);
    font-family: inherit;
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.ds-motion-banner__aksi:hover {
    background: var(--hcis-warning-500);
    color: var(--hcis-text-inverse);
}

.ds-motion-banner__aksi:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgb(var(--hcis-warning-500-rgb) / 0.4);
}

@media (max-width: 767.98px) {
    .ds-motion-banner {
        left: var(--hcis-space-4);
        flex-wrap: wrap;
    }
}
</style>
