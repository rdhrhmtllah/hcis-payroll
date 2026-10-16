<template>
    <div class="pd-tinjau">
        <HcisAlert
            variant="info"
            appearance="soft"
            icon="bi bi-shield-lock-fill"
            title="Periksa sekali lagi"
            class="pd-tinjau__catatan"
        >
            Pastikan seluruh data yang Anda masukkan sudah lengkap dan akurat. Setelah dikirim, data akan tersimpan di sistem dan formulir akan terkunci.
        </HcisAlert>

        <div class="pd-tinjau__grid">
            <HcisCard
                v-for="(l, i) in skema.langkah"
                :key="l.kunci"
                :title="`${i + 1}. ${l.judul}`"
                :icon="ikonLangkah(l.kunci)"
                variant="glass"
                size="sm"
                divider
                class="pd-tinjau__grup"
            >
                <template #actions>
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        label="Ubah"
                        leading-icon="bi bi-pencil-square"
                        :data-uji="`ubah-${i}`"
                        class="pd-tinjau__btn-ubah"
                        @click="$emit('lompat', i)"
                    />
                </template>

                <div class="pd-tinjau__daftar">
                    <div
                        v-for="kunci in fieldTampil(l)"
                        :key="kunci"
                        class="pd-tinjau__baris"
                    >
                        <!-- Label Group -->
                        <div class="pd-tinjau__label-group">
                            <span class="pd-tinjau__label-text">{{ skema.field[kunci]?.label ?? kunci }}</span>
                        </div>

                        <!-- Value & Provenance Badge Group -->
                        <div class="pd-tinjau__nilai-group" :class="{ 'is-kosong': kosong(kunci) }">
                            <span v-if="kosong(kunci)" class="pd-tinjau__kosong">Belum diisi</span>
                            <div v-else class="pd-tinjau__nilai-box">
                                <div class="pd-tinjau__nilai-main">
                                    <span class="pd-tinjau__teks">{{ tampilkan(kunci) }}</span>
                                    <span
                                        v-if="statusUbah(kunci) === 'DIUBAH'"
                                        class="pd-tinjau-pill pd-tinjau-pill--diubah"
                                        title="Data ini telah Anda sesuaikan dari data sebelumnya"
                                    >
                                        <i class="bi bi-pencil-fill"></i>
                                        <span>Diperbarui</span>
                                    </span>
                                    <span
                                        v-else-if="statusUbah(kunci) === 'ARSIP'"
                                        class="pd-tinjau-pill pd-tinjau-pill--terdaftar"
                                        title="Sesuai data kepegawaian Anda di HCIS"
                                    >
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Data Terdaftar</span>
                                    </span>
                                </div>
                                <span
                                    v-if="statusUbah(kunci) === 'DIUBAH' && dataMaster[kunci]"
                                    class="pd-tinjau__nilai-lama"
                                >
                                    <span class="pd-tinjau__nilai-lama-tag">Sebelumnya:</span> {{ dataMaster[kunci] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </HcisCard>
        </div>

        <!-- Verification Agreement Card -->
        <div
            class="pd-tinjau__konfirmasi"
            :class="{ 'is-checked': konfirmasi }"
        >
            <HcisCheckbox
                :model-value="konfirmasi"
                size="md"
                color="brand"
                data-uji="konfirmasi-data"
                class="pd-tinjau__checkbox"
                @update:model-value="(v) => $emit('update:konfirmasi', v)"
            >
                <span class="pd-tinjau__konfirmasi-label">
                    Saya menyatakan bahwa seluruh data yang saya isikan sudah benar dan sesuai.
                </span>
                <template #description>
                    <span class="pd-tinjau__konfirmasi-desc">
                        Seluruh informasi di atas telah saya periksa dengan teliti dan dapat dipertanggungjawabkan keabsahannya sesuai dokumen resmi.
                    </span>
                </template>
            </HcisCheckbox>
        </div>
    </div>
</template>

<script setup>
import { HcisAlert, HcisButton, HcisCard, HcisCheckbox } from '@/components/ui';
import { kunciTampil } from '@/composables/ESS/Employee/DataCollection/formatBidang.js';

const props = defineProps({
    skema: { type: Object, required: true },
    nilai: { type: Object, required: true },
    dataMaster: { type: Object, default: () => ({}) },
    konfirmasi: { type: Boolean, default: false },
    namaKaryawan: { type: Object, default: () => ({}) },
});

defineEmits(['lompat', 'update:konfirmasi']);

/**
 * Pertanyaan bersyarat yang syaratnya tidak terpenuhi tidak ikut ditinjau.
 * Menampilkannya sebagai 'Belum diisi' akan membaca seperti ada yang terlewat,
 * padahal orangnya memang tidak pernah ditanyai.
 */
const fieldTampil = (langkah) => kunciTampil(langkah.field, props.skema.field, props.nilai);

const adaMaster = (kunci) => {
    const m = props.dataMaster?.[kunci];
    if (m === undefined || m === null) return false;
    const str = String(m).trim();
    return str !== '' && !/^[\s\-–—]+$/.test(str);
};

const statusUbah = (kunci) => {
    if (!adaMaster(kunci)) return 'BARU';
    const v = String(props.nilai?.[kunci] ?? '').trim();
    const m = String(props.dataMaster?.[kunci] ?? '').trim();
    return v === m ? 'ARSIP' : 'DIUBAH';
};

const ikonLangkah = (kunci) => {
    const peta = {
        identitas: 'bi bi-person-vcard',
        data_diri: 'bi bi-person-vcard',
        kontak: 'bi bi-telephone-fill',
        pekerjaan: 'bi bi-briefcase-fill',
        karir: 'bi bi-briefcase-fill',
        darurat: 'bi bi-heart-pulse-fill',
        keluarga: 'bi bi-people-fill',
    };

    return peta[kunci] ?? 'bi bi-folder-check';
};

const kosong = (kunci) => {
    const v = props.nilai[kunci];
    if (v === undefined || v === null) return true;
    const str = String(v).trim();
    return str === '' || /^[\s\-–—]+$/.test(str);
};

const tampilkan = (kunci) => {
    if (kosong(kunci)) return 'Belum diisi';

    const v = props.nilai[kunci];
    const def = props.skema.field?.[kunci] ?? {};

    if (def.label_pilihan?.[v]) {
        return def.label_pilihan[v];
    }

    if (def.tipe === 'cari_karyawan' && props.namaKaryawan?.[v]) {
        const { nama, keterangan } = props.namaKaryawan[v];
        return keterangan ? `${nama} — ${keterangan}` : nama;
    }

    if (def.tipe === 'tanggal' && typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) {
        const parts = v.split('-');
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const d = parseInt(parts[2], 10);
        const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return `${d} ${bulan[m]} ${y}`;
    }

    return v;
};
</script>

<style scoped>
.pd-tinjau {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.pd-tinjau__catatan {
    margin-bottom: var(--hcis-space-1);
}

.pd-tinjau__grid {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.pd-tinjau__grup {
    margin-bottom: 0;
    border-radius: var(--hcis-radius-xl, 14px);
    border: 1px solid var(--hcis-border-subtle, rgba(226, 232, 240, 0.85));
    box-shadow: var(--hcis-shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.04));
    transition: all 0.2s ease;
}

.pd-tinjau__grup :deep(.hcis-card__header) {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0.75rem 1rem !important;
    gap: 0.75rem !important;
}

.pd-tinjau__grup :deep(.hcis-card__header-main) {
    display: flex !important;
    align-items: center !important;
    gap: 0.625rem !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
}

.pd-tinjau__grup :deep(.hcis-card__title-wrap) {
    display: flex !important;
    flex-direction: column !important;
    min-width: 0 !important;
}

.pd-tinjau__grup :deep(.hcis-card__title) {
    font-size: 0.9375rem !important;
    font-weight: 750 !important;
    color: var(--hcis-slate-900, #0f172a) !important;
    margin: 0 !important;
    text-align: left !important;
}

.pd-tinjau__grup :deep(.hcis-card__actions) {
    margin-left: auto !important;
    flex-shrink: 0 !important;
}

.pd-tinjau__btn-ubah {
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 650;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-slate-200, rgba(203, 213, 225, 0.8));
    background: var(--hcis-white, #ffffff);
    color: var(--hcis-slate-700, #334155);
}

.pd-tinjau__btn-ubah:hover {
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-brand-600, #4f46e5);
    border-color: var(--hcis-brand-300, #a5b4fc);
}

.pd-tinjau__daftar {
    display: flex;
    flex-direction: column;
    gap: 0;
    margin: 0;
}

.pd-tinjau__baris {
    display: grid;
    grid-template-columns: minmax(180px, 1.1fr) minmax(0, 1.9fr);
    gap: var(--hcis-space-3);
    align-items: baseline;
    padding: 0.7rem 0.35rem;
    border-bottom: 1px dashed var(--hcis-border-subtle, rgba(226, 232, 240, 0.9));
}

.pd-tinjau__baris:last-child {
    padding-bottom: 0;
    border-bottom: none;
}

.pd-tinjau__label-group {
    display: flex;
    align-items: center;
    min-width: 0;
}

.pd-tinjau__label-text {
    font-size: var(--hcis-text-xs, 0.78125rem);
    font-weight: 600;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.35;
    text-align: left;
}

.pd-tinjau__nilai-group {
    margin: 0;
    width: 100%;
}

.pd-tinjau__nilai-box {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    gap: 0.25rem;
    width: 100%;
}

.pd-tinjau__nilai-main {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    width: 100%;
    justify-content: flex-start;
    text-align: left;
}

.pd-tinjau__kosong {
    font-weight: 500;
    font-style: italic;
    font-size: var(--hcis-text-sm, 0.875rem);
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    text-align: left;
}

.pd-tinjau__teks {
    font-size: var(--hcis-text-sm, 0.875rem);
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.4;
    word-break: break-word;
    text-align: left;
}

.pd-tinjau-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 650;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    line-height: 1.25;
    white-space: nowrap;
    flex-shrink: 0;
}

.pd-tinjau-pill--terdaftar {
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.pd-tinjau-pill--terdaftar i {
    font-size: 0.625rem;
    color: var(--hcis-indigo-500);
}

.pd-tinjau-pill--diubah {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(245, 158, 11, 0.28);
}

.pd-tinjau-pill--diubah i {
    font-size: 0.625rem;
    color: var(--hcis-amber-600);
}

.pd-tinjau__nilai-lama {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.725rem;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-weight: 500;
    text-align: left;
}

.pd-tinjau__nilai-lama-tag {
    color: var(--hcis-slate-400);
    font-weight: 600;
}

/* Agreement / Confirmation Card */
.pd-tinjau__konfirmasi {
    margin-top: var(--hcis-space-2);
    background: var(--hcis-white, #ffffff);
    border: 1.5px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 1rem 1.25rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pd-tinjau__konfirmasi:hover {
    border-color: var(--hcis-brand-300, #a5b4fc);
    background: rgba(99, 102, 241, 0.02);
}

.pd-tinjau__konfirmasi.is-checked {
    border-color: var(--hcis-brand-500, #6366f1);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(16, 185, 129, 0.04) 100%);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

.pd-tinjau__checkbox {
    width: 100%;
    margin-bottom: 0;
}

.pd-tinjau__checkbox :deep(.hcis-choice) {
    width: 100%;
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    margin-bottom: 0;
}

.pd-tinjau__konfirmasi-label {
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-size: var(--hcis-text-sm, 0.875rem);
    line-height: 1.4;
    cursor: pointer;
    text-align: left;
}

.pd-tinjau__konfirmasi-desc {
    font-size: 0.78125rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.45;
    display: block;
    margin-top: 0.2rem;
    cursor: pointer;
    text-align: left;
}

@media (max-width: 640px) {
    .pd-tinjau {
        gap: var(--hcis-space-3);
    }

    .pd-tinjau__grup {
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pd-tinjau__grup :deep(.hcis-card__header) {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0.65rem 0.75rem !important;
    }

    .pd-tinjau__grup :deep(.hcis-card__header-main) {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    .pd-tinjau__grup :deep(.hcis-card__title-wrap) {
        display: flex !important;
        flex-direction: column !important;
        min-width: 0 !important;
    }

    .pd-tinjau__grup :deep(.hcis-card__title) {
        font-size: 0.875rem !important;
        font-weight: 750 !important;
        text-align: left !important;
    }

    .pd-tinjau__grup :deep(.hcis-card__actions) {
        margin-left: auto !important;
        flex-shrink: 0 !important;
    }

    .pd-tinjau__grup :deep(.hcis-card__body) {
        padding: 0.5rem 0.75rem !important;
    }

    .pd-tinjau__btn-ubah {
        min-height: 28px;
        padding: 0.2rem 0.55rem;
        font-size: 0.71875rem;
    }

    .pd-tinjau__baris {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.15rem !important;
        padding: 0.55rem 0.15rem !important;
        text-align: left !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
    }

    .pd-tinjau__label-group {
        display: block !important;
        width: 100% !important;
        text-align: left !important;
    }

    .pd-tinjau__label-text {
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        color: var(--hcis-slate-500) !important;
        text-align: left !important;
        display: block !important;
    }

    .pd-tinjau__nilai-group {
        width: 100% !important;
        text-align: left !important;
    }

    .pd-tinjau__nilai-box {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        text-align: left !important;
        margin-top: 0.05rem !important;
        width: 100% !important;
        gap: 0.2rem !important;
    }

    .pd-tinjau__nilai-main {
        display: flex !important;
        align-items: baseline !important;
        justify-content: flex-start !important;
        flex-wrap: wrap !important;
        gap: 0.35rem 0.45rem !important;
        width: 100% !important;
        text-align: left !important;
    }

    .pd-tinjau__teks {
        font-size: 0.9375rem !important;
        font-weight: 700 !important;
        color: var(--hcis-slate-900) !important;
        text-align: left !important;
        line-height: 1.4 !important;
    }

    .pd-tinjau__kosong {
        text-align: left !important;
    }

    .pd-tinjau__konfirmasi {
        padding: 0.875rem 1rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }
}
</style>

