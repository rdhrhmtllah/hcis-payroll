<template>
    <div class="pd-ringkasan">
        <HcisResultState
            status="success"
            variant="glass"
            size="lg"
            animated
            loop-animation
            title="Terima kasih — data Anda telah tersimpan"
            class="pd-ringkasan__hasil"
        >
            <template #description>
                <div class="d-flex flex-column align-items-center gap-2 mt-1">
                    <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill bg-emerald-50 text-emerald-800 border border-emerald-200 small fw-semibold">
                        <i class="bi bi-clock-history text-emerald-600"></i>
                        <span>Dikirim pada <strong>{{ tanggalKirim }}</strong><template v-if="jamKirim"> pukul <strong>{{ jamKirim }} WIB</strong></template></span>
                    </div>
                    <p class="text-muted small mb-0 text-center" style="max-width: 480px; line-height: 1.55;">
                        Jawaban ini telah tersimpan dan terkunci di sistem. Apabila terdapat kekeliruan atau perubahan data mendesak, silakan hubungi tim <strong>HR / HCIS</strong> untuk membuka kunci formulir.
                    </p>
                </div>
            </template>
        </HcisResultState>

        <div class="pd-ringkasan__grid">
            <HcisCard
                v-for="l in skema.langkah"
                :key="l.kunci"
                :title="l.judul"
                icon="bi bi-folder-check"
                variant="glass"
                size="sm"
                divider
                class="pd-ringkasan__grup"
            >
                <dl class="pd-ringkasan__daftar">
                    <div v-for="kunci in fieldTampil(l)" :key="kunci" class="pd-ringkasan__baris">
                        <dt class="pd-ringkasan__label">{{ skema.field[kunci]?.label ?? kunci }}</dt>
                        <dd class="pd-ringkasan__nilai" :class="{ 'is-kosong': kosong(kunci) }">
                            {{ tampilkan(kunci) }}
                        </dd>
                    </div>
                </dl>
            </HcisCard>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisAlert, HcisCard, HcisResultState } from '@/components/ui';
import { kunciTampil } from '@/composables/ESS/Employee/DataCollection/formatBidang.js';

const props = defineProps({
    kiriman: { type: Object, default: null },
    dikirimPada: { type: String, default: null },
    jawaban: { type: Object, default: () => ({}) },
    skema: { type: Object, required: true },
    namaKaryawan: { type: Object, default: () => ({}) },
});

const isiJawaban = computed(() => props.kiriman?.jawaban ?? props.jawaban ?? {});
const mentahDikirimPada = computed(() => props.kiriman?.dikirim_pada ?? props.dikirimPada);

/**
 * Pertanyaan yang tidak pernah ditanyakan kepada orang ini tidak ikut
 * ditampilkan — '—' di sebelahnya akan terbaca seperti ada yang terlewat.
 */
const fieldTampil = (langkah) => kunciTampil(langkah.field, props.skema.field, isiJawaban.value);

/** Jawaban yang tidak terisi ditandai lebih redup, bukan dibiarkan hampa. */
const kosong = (kunci) => {
    const v = isiJawaban.value[kunci];

    return v === undefined || v === null || String(v).trim() === '';
};

const tampilkan = (kunci) => {
    const v = isiJawaban.value[kunci];
    if (v === undefined || v === null || String(v).trim() === '') return '—';

    const def = props.skema.field[kunci] ?? {};
    if (def.tipe === 'cari_karyawan' && props.namaKaryawan?.[v]) {
        const { nama, keterangan } = props.namaKaryawan[v];
        return keterangan ? `${nama} — ${keterangan}` : nama;
    }

    return def.label_pilihan?.[v] ?? v;
};

const tanggalKirim = computed(() => {
    const tgl = mentahDikirimPada.value;
    if (!tgl) return 'Baru saja';

    const t = new Date(tgl.replace(' ', 'T'));
    if (Number.isNaN(t.getTime())) return tgl;

    return t.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const jamKirim = computed(() => {
    const tgl = mentahDikirimPada.value;
    if (!tgl) return '';

    const t = new Date(tgl.replace(' ', 'T'));
    if (Number.isNaN(t.getTime())) return '';

    return t.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).replace('.', ':');
});

const waktuTampil = computed(() => {
    if (!jamKirim.value) return tanggalKirim.value;
    return `${tanggalKirim.value} pukul ${jamKirim.value} WIB`;
});
</script>

<style scoped>
.pd-ringkasan {
    max-width: 48rem;
    margin: 0 auto;
    padding: var(--hcis-space-6) var(--hcis-space-4);
}

.pd-ringkasan__hasil,
.pd-ringkasan__catatan {
    margin-bottom: var(--hcis-space-4);
}

.pd-ringkasan__grid {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.pd-ringkasan__grup {
    margin-bottom: 0;
}

.pd-ringkasan__daftar {
    display: grid;
    gap: var(--hcis-space-3);
    margin: 0;
}

.pd-ringkasan__baris {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.8fr);
    gap: var(--hcis-space-3);
    align-items: baseline;
    padding-bottom: var(--hcis-space-2);
    border-bottom: 1px dashed var(--hcis-border-subtle);
}

.pd-ringkasan__baris:last-child {
    padding-bottom: 0;
    border-bottom: none;
}

.pd-ringkasan__label {
    font-size: var(--hcis-text-xs, 0.75rem);
    font-weight: 600;
    color: var(--hcis-text-muted);
}

.pd-ringkasan__nilai {
    margin: 0;
    font-size: var(--hcis-text-sm, 0.875rem);
    font-weight: 700;
    color: var(--hcis-text-strong);
    overflow-wrap: anywhere;
    text-align: right;
}

.pd-ringkasan__nilai.is-kosong {
    font-weight: 500;
    color: var(--hcis-text-muted);
}

@media (max-width: 640px) {
    .pd-ringkasan {
        padding: var(--hcis-space-4) var(--hcis-space-3);
    }

    .pd-ringkasan__baris {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--hcis-space-1);
    }

    .pd-ringkasan__nilai {
        text-align: left;
    }
}
</style>
