<template>
    <section class="ds-seksi">
        <header class="ds-seksi__kepala">
            <HcisIcon :name="ikon" size="xs" class="ds-seksi__ikon" />
            <h4 class="ds-seksi__judul">{{ judul }}</h4>
            <slot name="aksi" />
        </header>

        <dl v-if="baris.length" class="ds-seksi__grid">
            <div v-for="b in baris" :key="b.label" class="ds-seksi__item">
                <dt class="ds-seksi__label">{{ b.label }}</dt>
                <dd class="ds-seksi__nilai" :class="{ 'ds-seksi__nilai--tabular': b.tabular }">
                    {{ b.nilai === null || b.nilai === undefined || b.nilai === '' ? '-' : b.nilai }}
                </dd>
            </div>
        </dl>

        <slot />
    </section>
</template>

<script>
import { HcisIcon } from '@/components/ui';

/**
 * Satu blok "judul + pasangan label/nilai" pada dialog detail.
 *
 * Nilai kosong dirender '-' DI SATU TEMPAT. Sebelumnya tiap pemanggil menulis
 * `|| '-'` sendiri, dan yang lupa menghasilkan sel kosong yang terbaca seperti
 * galat pemuatan padahal datanya memang tidak ada.
 */
export default {
    name: 'DetailSeksi',
    components: { HcisIcon },
    props: {
        judul: { type: String, required: true },
        ikon: { type: String, default: 'info-circle' },
        /** @type {{label: string, nilai: unknown, tabular?: boolean}[]} */
        baris: { type: Array, default: () => [] },
    },
};
</script>

<style scoped>
.ds-seksi__kepala {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    margin-bottom: var(--hcis-space-2, 0.5rem);
}

.ds-seksi__ikon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.ds-seksi__judul {
    margin: 0;
    font-size: var(--hcis-font-size-sm, 0.8125rem);
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    flex: 1 1 auto;
}

.ds-seksi__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--hcis-space-2, 0.5rem) var(--hcis-space-4, 1rem);
    margin: 0;
}

.ds-seksi__item {
    min-width: 0;
}

.ds-seksi__label {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin: 0;
}

.ds-seksi__nilai {
    margin: 0.1rem 0 0;
    font-size: var(--hcis-font-size-sm, 0.8125rem);
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    overflow-wrap: anywhere;
}

.ds-seksi__nilai--tabular {
    font-variant-numeric: tabular-nums;
}
</style>
