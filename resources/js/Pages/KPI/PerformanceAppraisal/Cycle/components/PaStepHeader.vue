<template>
    <div class="pa-step-header">
        <div class="pa-step-header-identity">
            <span class="pa-step-kicker">{{ kicker }}</span>

            <h2 class="pa-step-title">
                <HcisIcon :name="icon" size="sm" color="brand" />
                <span>{{ title }}</span>
            </h2>

            <p v-if="description" class="pa-step-description">{{ description }}</p>
        </div>

        <div class="pa-step-header-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
/**
 * Kepala satu langkah wizard penyiapan.
 *
 * KENAPA JADI KOMPONEN SENDIRI. Sebelum 6 September 2026 kepala langkah ditulis dua cara:
 * tiga langkah menyusun markupnya sendiri, dua langkah memakai `HcisCard` dengan atribut
 * `kicker`. `HcisCard` tidak punya prop itu — Vue membuangnya diam-diam, sehingga langkah 1
 * dan 4 kehilangan penanda "LANGKAH n DARI 5" tanpa satu pun galat, dan tiga salinan CSS
 * yang sama hidup di tiga berkas berbeda.
 *
 * Satu komponen menutup keduanya: penandanya tidak bisa hilang lagi karena ia bagian dari
 * markup, dan gayanya tinggal satu tempat.
 */
import { HcisIcon } from '@/components/ui';

defineProps({
    /** Penanda posisi, mis. "LANGKAH 2 DARI 5 · BANK SOAL". */
    kicker: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'circle' },
});
</script>

<style scoped>
.pa-step-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-step-header-identity {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 42rem;
}

.pa-step-kicker {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #4f46e5;
}

.pa-step-title {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-step-description {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .pa-step-header {
        flex-direction: column;
    }

    .pa-step-header-actions {
        width: 100%;
    }
}
</style>
