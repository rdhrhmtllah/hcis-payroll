<template>
    <div class="pak">
        <p v-if="!baris.length" class="pak-bersih">
            <HcisIcon name="check-circle-fill" size="xs" class="text-emerald-500" />
            <span>Tata kelola master dokumen bersih (tidak ada antrean tertahan).</span>
        </p>

        <template v-else>
            <ul class="pak-daftar">
                <li
                    v-for="(b, i) in baris"
                    :key="`${b.Jenis}-${b.Id_Dokumen || b.Nomor || i}`"
                    class="pak-baris"
                    :class="`is-${b.Jenis.toLowerCase()}`"
                >
                    <span class="pak-tanda" :title="b.Nama_Jenis">
                        <HcisIcon :name="ikon(b.Jenis)" size="xs" />
                    </span>

                    <span class="pak-isi">
                        <span class="pak-judul" :title="b.Judul">{{ b.Judul }}</span>
                        <span class="pak-ket">{{ b.Keterangan }}</span>
                    </span>

                    <span class="pak-kanan">
                        <span v-if="b.Umur_Hari !== null" class="pak-umur" :class="{ 'is-tua': b.Umur_Hari >= 30 }">
                            {{ b.Umur_Hari }} hari
                        </span>
                        <button
                            v-if="b.Id_Dokumen"
                            type="button"
                            class="pak-buka"
                            title="Buka dokumen ini"
                            @click="buka(b)"
                        >
                            <span>Buka</span>
                            <HcisIcon name="arrow-right-short" size="sm" />
                        </button>
                    </span>
                </li>
            </ul>

            <p v-if="tersembunyi > 0" class="pak-sisa">
                <HcisIcon name="three-dots" size="xs" />
                <span>+{{ tersembunyi }} temuan lainnya</span>
            </p>
        </template>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { HcisIcon } from '@/components/ui';

const IKON = {
    MENUNGGU_SAH: 'pen-fill',
    TERBIT_TAK_BEREDAR: 'box-seam',
    MACET_REVIEW: 'chat-square-text-fill',
    PROSES_KOSONG: 'diagram-2',
    MACET_DRAFTING: 'pencil-fill',
    TANPA_PEMILIK: 'person-dash',
    TANPA_PROSES: 'signpost',
};

/**
 * Antrean kerja dokumen — daftar yang menyebut namanya.
 */
export default {
    name: 'PanelAntreanKerja',
    components: { HcisIcon },
    props: {
        antrean: { type: Object, default: () => ({ Baris: [], Total: 0 }) },
        tersaring: { type: Object, default: () => ({ Penugasan: 0, Paket: 0 }) },
    },
    computed: {
        baris() {
            return this.antrean?.Baris || [];
        },
        tersembunyi() {
            return Math.max(0, (this.antrean?.Total || 0) - this.baris.length);
        },
    },
    methods: {
        ikon(jenis) {
            return IKON[jenis] || 'dot';
        },
        buka(b) {
            if (!b.Id_Dokumen) return;
            router.visit(b.Id_Versi
                ? `/sop/admin/dokumen/${b.Id_Dokumen}/versi/${b.Id_Versi}`
                : `/sop/admin/dokumen/${b.Id_Dokumen}/versi`);
        },
    },
};
</script>

<style scoped>
.pak {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pak-daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 22rem;
    overflow-y: auto;
}

.pak-baris {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-left: 3.5px solid var(--pak-c, var(--hcis-slate-400));
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.85);
    transition: all 0.16s ease;
}

.pak-baris:hover {
    background: var(--hcis-white);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.08);
}

.pak-baris.is-menunggu_sah { --pak-c: var(--hcis-red-500); }
.pak-baris.is-terbit_tak_beredar { --pak-c: var(--hcis-amber-500); }
.pak-baris.is-macet_review { --pak-c: var(--hcis-indigo-600); }
.pak-baris.is-proses_kosong { --pak-c: var(--hcis-amber-600); }
.pak-baris.is-macet_drafting { --pak-c: var(--hcis-slate-500); }
.pak-baris.is-tanpa_pemilik,
.pak-baris.is-tanpa_proses { --pak-c: var(--hcis-slate-400); }

.pak-tanda {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.85);
    color: var(--pak-c);
}

.pak-isi {
    display: flex;
    flex-direction: column;
    line-height: 1.35;
    min-width: 0;
}

.pak-judul {
    font-size: 0.78125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pak-ket {
    font-size: 0.7125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pak-kanan {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pak-umur {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    white-space: nowrap;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: rgba(241, 245, 249, 0.8);
    font-variant-numeric: tabular-nums;
}

.pak-umur.is-tua {
    color: var(--hcis-red-700);
    background: var(--hcis-red-100);
    font-weight: 800;
}

.pak-buka {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.2rem 0.55rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    background: rgba(238, 242, 255, 0.8);
    color: var(--hcis-indigo-700);
    font-size: 0.725rem;
    font-weight: 750;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.16s ease;
}

.pak-buka:hover {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-800);
}

.pak-bersih {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    padding: 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(236, 253, 245, 0.8);
    border: 1px solid rgba(167, 243, 208, 0.8);
    font-size: 0.78125rem;
    font-weight: 600;
    color: var(--hcis-emerald-800);
}

.pak-sisa {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.735rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.pak-gerbang {
    margin: 0.25rem 0 0;
}
</style>
