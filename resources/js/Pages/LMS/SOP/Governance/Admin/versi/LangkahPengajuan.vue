<template>
    <div class="d-flex flex-column gap-3">
        <!-- ══ 1. BANNER SUKSESI VERSI (HCIS ALERT) ══ -->
        <HcisAlert
            v-if="versiSebelumnya"
            variant="brand"
            appearance="soft"
            icon="arrow-repeat"
            size="sm"
            glow
            pattern="dots"
            class="mb-0"
        >
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                <div class="small">
                    <strong class="text-indigo-900">Penerusan Versi:</strong>
                    Melanjutkan dari <strong>v{{ String(versiSebelumnya.Versi_Ke).padStart(2, '0') }}</strong>.
                    <span class="text-indigo-700 d-none d-md-inline ms-1">Nomor versi rilis dan berkas acuan telah disiapkan otomatis.</span>
                </div>
                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                    <HcisBadge :label="`Versi Sebelumnya: v${String(versiSebelumnya.Versi_Ke).padStart(2, '0')}`" variant="neutral" size="xs" />
                    <i class="bi bi-arrow-right text-muted text-xs"></i>
                    <HcisBadge :label="`Target Rilis: v${String(nomorVersiAktif).padStart(2, '0')}`" variant="brand" size="xs" />
                </div>
            </div>
        </HcisAlert>

        <HcisAlert
            v-else
            variant="info"
            appearance="soft"
            icon="info-circle"
            title="Penerbitan Versi Perdana"
            badge="Versi Perdana"
            size="sm"
            class="mb-0"
        >
            Dokumen ini belum memiliki riwayat versi sebelumnya. Nomor versi awal akan dimulai secara otomatis dari urutan master.
        </HcisAlert>

        <!-- ══ 2. CARD SEGMEN 1: IDENTITAS & PENOMORAN VERSI ══ -->
        <HcisCard
            title="Identitas &amp; Mode Penomoran Versi"
            description="Tentukan skema penomoran rilis untuk versi dokumen ini."
            icon="hash"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Versi_Ke')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <!-- 2-Card Selectable Mode Grid (50/50) -->
            <div class="row g-2.5">
                <!-- Option 1: Otomatis -->
                <div class="col-12 col-md-6">
                    <div
                        class="sop-mode-card"
                        :class="{ 'is-active': !manualNomor, 'is-disabled': mati('Versi_Ke') }"
                        role="button"
                        :tabindex="terkunci ? -1 : 0"
                        @click="pilihMode(false)"
                        @keydown.enter="pilihMode(false)"
                    >
                        <div class="sop-mode-card__header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sop-radio-circle" :class="{ 'is-checked': !manualNomor }">
                                    <i v-if="!manualNomor" class="bi bi-check-lg"></i>
                                </div>
                                <span class="sop-mode-card__title">Penomoran Otomatis</span>
                            </div>
                            <HcisBadge label="Rekomendasi" variant="brand" size="xs" />
                        </div>

                        <!-- Hero Number Display -->
                        <div class="sop-mode-card__display">
                            <div class="sop-number-badge font-monospace">
                                <i class="bi bi-hash"></i>
                                <span>v{{ String(nextVersiKe != null ? nextVersiKe : 0).padStart(2, '0') }}</span>
                            </div>
                            <span class="sop-number-sub">
                                <i class="bi bi-shield-check text-success me-1"></i>Urutan Terverifikasi
                            </span>
                        </div>

                        <p class="sop-mode-card__desc">
                            Melanjutkan urutan master
                            <template v-if="versiSebelumnya">
                                (<strong>v{{ String(versiSebelumnya.Versi_Ke).padStart(2, '0') }}</strong> &rarr; <strong>v{{ String(nextVersiKe).padStart(2, '0') }}</strong>)
                            </template>
                            secara kronologis dan bebas bentrok nomor.
                        </p>
                    </div>
                </div>

                <!-- Option 2: Manual -->
                <div class="col-12 col-md-6">
                    <div
                        class="sop-mode-card"
                        :class="{ 'is-active': manualNomor, 'is-disabled': mati('Versi_Ke') }"
                        role="button"
                        :tabindex="terkunci ? -1 : 0"
                        @click="pilihMode(true)"
                        @keydown.enter="pilihMode(true)"
                    >
                        <div class="sop-mode-card__header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sop-radio-circle" :class="{ 'is-checked': manualNomor }">
                                    <i v-if="manualNomor" class="bi bi-check-lg"></i>
                                </div>
                                <span class="sop-mode-card__title">Penomoran Manual</span>
                            </div>
                            <HcisBadge label="Kustom" variant="warning" size="xs" />
                        </div>

                        <!-- Manual Input Box -->
                        <div v-if="manualNomor" class="sop-mode-card__manual-input" @click.stop>
                            <label class="form-label text-xs fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                                <span>Ketik Nomor Versi:</span>
                                <span class="text-muted text-xs">Rentang: 0 - 999</span>
                            </label>
                            <div class="d-flex align-items-center gap-2">
                                <div class="sop-v-pill font-monospace">v</div>
                                <HcisInputNumber
                                    :model-value="nilai.Versi_Ke"
                                    :min="0"
                                    :max="999"
                                    :step="1"
                                    size="sm"
                                    :disabled="mati('Versi_Ke')"
                                    class="flex-grow-1"
                                    placeholder="Contoh: 2"
                                    @update:model-value="ubah('Versi_Ke', $event)"
                                />
                            </div>
                        </div>

                        <div v-else class="sop-mode-card__display is-inactive">
                            <div class="sop-number-badge font-monospace is-muted">
                                <i class="bi bi-hash"></i>
                                <span>v--</span>
                            </div>
                            <span class="sop-number-sub text-muted">Kustom Mandiri</span>
                        </div>

                        <p class="sop-mode-card__desc">
                            Gunakan jika terdapat kebutuhan penomoran khusus, revisi mayor berskala besar, atau lompatan nomor versi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Summary Strip -->
            <template #footer>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-xs">
                    <div class="d-flex align-items-center gap-1.5 text-muted">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>
                            Status Penomoran:
                            <strong class="text-dark">
                                {{ manualNomor ? 'Mode Manual Aktif' : 'Otomatis mengikuti urutan master' }}
                            </strong>
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-1.5">
                        <span class="text-muted">Target Terdaftar:</span>
                        <span class="badge bg-dark text-white font-monospace px-2 py-1">
                            # v{{ String(nomorVersiAktif).padStart(2, '0') }}
                        </span>
                    </div>
                </div>
            </template>
        </HcisCard>

        <!-- ══ 3. CARD SEGMEN 2: RINGKASAN PERUBAHAN PROSEDUR ══ -->
        <HcisCard
            title="Ringkasan Perubahan &amp; Catatan Revisi"
            description="Tuliskan latar belakang, urgensi, dan poin-poin perubahan pada prosedur operasional ini."
            icon="journal-text"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Ringkasan_Perubahan')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <!-- Quick Template Tag Chips -->
            <div v-if="!terkunci" class="d-flex align-items-center gap-1.5 flex-wrap mb-2">
                <span class="text-muted small fw-medium me-1" style="font-size: 0.75rem;">
                    <i class="bi bi-tag-fill me-1"></i>Pintasan Tag:
                </span>
                <button
                    v-for="tag in tagPintasan"
                    :key="tag"
                    type="button"
                    class="sop-tag-chip"
                    @click="tambahTag(tag)"
                >
                    + {{ tag }}
                </button>
            </div>

            <HcisTextarea
                id="sop-ringkasan-perubahan"
                :model-value="nilai.Ringkasan_Perubahan"
                placeholder="Contoh: Pembaruan alur persetujuan lembur pada poin 4.2 sesuai dengan kebijakan HR terkini No. HR/POL/2026/08..."
                :rows="4"
                :maxlength="1000"
                :disabled="terkunci"
                clearable
                auto-resize
                @update:model-value="ubah('Ringkasan_Perubahan', $event)"
            />

            <template #footer>
                <div class="d-flex align-items-center justify-content-between text-muted small" style="font-size: 0.75rem;">
                    <span>
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Ringkasan ini akan tercatat pada lembar riwayat perubahan dokumen (Audit Trail).
                    </span>
                    <span>{{ (nilai.Ringkasan_Perubahan || '').length }} / 1000 karakter</span>
                </div>
            </template>
        </HcisCard>

        <!-- ══ 4. CARD SEGMEN 3: LINIMASA & TANGGAL PROSES ══ -->
        <HcisCard
            title="Linimasa Jadwal Pengajuan"
            description="Pencatatan tanggal pengajuan draft dan target respon evaluasi review mutu."
            icon="calendar3"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Tanggal_Permintaan', 'Tanggal_Respon')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <div class="row g-3">
                <div v-for="f in tanggalProses" :key="f.kunci" class="col-12 col-md-6">
                    <HcisField
                        :label="f.label"
                        :description="`Tercap saat memasuki tahap ${f.tahap}. Dapat diatur manual.`"
                        size="sm"
                    >
                        <HcisDatePicker
                            :model-value="nilai[f.kunci]"
                            :placeholder="f.petunjuk"
                            size="sm"
                            clearable
                            :disabled="terkunci"
                            class="w-100"
                            @update:model-value="ubah(f.kunci, $event)"
                        />
                    </HcisField>
                </div>
            </div>
        </HcisCard>
    </div>
</template>

<script>
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisTextarea from '@/components/ui/HcisTextarea.vue';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import HcisInputNumber from '@/components/ui/HcisInputNumber.vue';
import HcisField from '@/components/ui/HcisField.vue';

/**
 * Langkah 1 — Pengajuan (Modular Card Structure).
 */
export default {
    name: 'LangkahPengajuan',
    components: {
        HcisAlert,
        HcisCard,
        HcisBadge,
        HcisTextarea,
        HcisDatePicker,
        HcisInputNumber,
        HcisField,
    },
    props: {
        modelValue: { type: Object, required: true },
        terkunci: { type: Boolean, default: false },
        /* Izin per field dari server. Baku "boleh" supaya langkah yang belum
           menerima muatannya tak mengunci apa pun — mengunci saat ragu membuat
           halaman yang lambat memuat terlihat rusak. */
        bolehUbah: { type: Function, default: () => true },
        berubah: { type: Array, default: () => [] },
        versiSebelumnya: { type: Object, default: null },
        nextVersiKe: { type: Number, default: 0 },
    },
    emits: ['update:modelValue'],
    data() {
        const v = this.modelValue || {};
        const isCustom = v.Versi_Ke !== null && v.Versi_Ke !== undefined && v.Versi_Ke !== this.nextVersiKe;

        return {
            manualNomor: isCustom,
            tagPintasan: [
                '[Pembaruan Alur]',
                '[Penyesuaian Kebijakan]',
                '[Koreksi Redaksional]',
                '[Temuan Audit Mutu]',
                '[Perubahan Struktur]',
            ],
            tanggalProses: [
                {
                    kunci: 'Tanggal_Permintaan',
                    label: 'Tanggal Pengajuan Review',
                    tahap: 'Review',
                    petunjuk: 'Pilih tanggal pengajuan draft',
                },
                {
                    kunci: 'Tanggal_Respon',
                    label: 'Target / Tanggal Respon',
                    tahap: 'Pengesahan',
                    petunjuk: 'Pilih tanggal respon evaluasi',
                },
            ],
        };
    },
    computed: {
        nilai() {
            return this.modelValue || {};
        },
        nomorVersiAktif() {
            if (this.manualNomor) {
                return this.nilai.Versi_Ke != null ? this.nilai.Versi_Ke : this.nextVersiKe;
            }
            return this.nextVersiKe != null ? this.nextVersiKe : 0;
        },
    },
    watch: {
        'modelValue.Versi_Ke': {
            handler(val) {
                if (val !== null && val !== undefined && val !== this.nextVersiKe) {
                    this.manualNomor = true;
                } else if (val === this.nextVersiKe) {
                    this.manualNomor = false;
                }
            },
            immediate: true,
        },
    },
    methods: {
        /** Terkunci karena tahapnya, ATAU karena keadaan versi tak mengizinkan field ini. */
        mati(field) {
            return this.terkunci || !this.bolehUbah(field);
        },
        ditandai(...kunci) {
            return kunci.some((k) => this.berubah.includes(k));
        },
        ubah(kunci, v) {
            this.$emit('update:modelValue', { ...this.nilai, [kunci]: v });
        },
        pilihMode(manual) {
            if (this.terkunci) return;
            this.manualNomor = manual;
            if (!manual) {
                this.ubah('Versi_Ke', this.nextVersiKe);
            }
        },
        tambahTag(tag) {
            const kini = this.nilai.Ringkasan_Perubahan || '';
            const spasi = kini && !kini.endsWith(' ') ? ' ' : '';
            this.ubah('Ringkasan_Perubahan', `${kini}${spasi}${tag} `);
        },
    },
};
</script>

<style scoped>
/* ══ 2-Card Selectable Mode Grid (High Craft) ══ */
.sop-mode-card {
    position: relative;
    height: 100%;
    padding: 1.15rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-white);
    border: 2px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sop-mode-card:hover:not(.is-disabled) {
    border-color: #93c5fd;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -4px rgba(37, 99, 235, 0.08);
}

.sop-mode-card.is-active {
    border-color: var(--hcis-blue-600);
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    box-shadow: 0 0 0 1px var(--hcis-blue-600), 0 8px 20px -4px rgba(37, 99, 235, 0.12);
}

.sop-mode-card.is-disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.sop-mode-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.sop-radio-circle {
    display: grid;
    place-items: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 2px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    color: var(--hcis-white);
    font-size: 0.8rem;
    font-weight: 800;
    transition: all 0.15s ease;
}

.sop-radio-circle.is-checked {
    border-color: var(--hcis-blue-600);
    background: var(--hcis-blue-600);
}

.sop-mode-card__title {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
}

.sop-mode-card__display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #eff6ff;
    border: 1px solid #dbeafe;
}

.sop-mode-card__display.is-inactive {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
}

.sop-number-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.65rem;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: var(--hcis-slate-900);
    letter-spacing: 0.03em;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
}

.sop-number-badge.is-muted {
    background: var(--hcis-slate-400);
    color: var(--hcis-white);
    box-shadow: none;
}

.sop-number-sub {
    font-size: 0.75rem;
    font-weight: 650;
    color: #1e40af;
}

.sop-mode-card__desc {
    margin: 0;
    font-size: 0.775rem;
    color: var(--hcis-slate-500);
    line-height: 1.45;
    flex-grow: 1;
}

.sop-mode-card__manual-input {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
}

.sop-v-pill {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    font-weight: 800;
    font-size: 0.85rem;
}

/* ══ Tag Pintasan ══ */
.sop-tag-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.725rem;
    font-weight: 650;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    cursor: pointer;
    transition: all 0.15s ease;
}

.sop-tag-chip:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
    transform: translateY(-1px);
}
</style>
