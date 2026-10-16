<template>
    <HcisDialog
        :model-value="modelValue"
        :title="mode === 'create' ? 'Buat Cycle Baru' : 'Detail & Sunting Cycle'"
        kicker="ADMINISTRATOR PA · SETUP CYCLE"
        :description="mode === 'create' ? 'Tetapkan identitas dan rentang kerja assessment cycle baru.' : 'Periksa detail server sebelum menyunting konfigurasi cycle.'"
        :badge="mode === 'create' ? 'DRAFT BARU' : cycle?.Status_Cycle || ''"
        icon="calendar2-plus-fill"
        sentiment="brand"
        variant="glass"
        size="xl"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-cycle-setup">
            <div v-if="mode === 'edit' && detailLoading" class="pa-cycle-setup__loading" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="brand" />
                <div>
                    <strong>Memuat detail cycle</strong>
                    <span>Menunggu aturan penyuntingan dari server.</span>
                </div>
            </div>

            <HcisResultState
                v-else-if="mode === 'edit' && detailError"
                :status="detailErrorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="detailErrorStatus === 403 ? 'Akses detail tidak tersedia' : 'Detail cycle gagal dimuat'"
                :description="detailError"
                :error-code="detailErrorCode"
                :request-id="detailRequestId"
                :show-retry="false"
            />

            <template v-else>
                <section v-if="mode === 'edit' && detail" class="pa-cycle-setup__snapshot" aria-label="Ringkasan detail cycle">
                    <div class="pa-cycle-setup__snapshot-main">
                        <div class="pa-cycle-setup__snapshot-icon"><HcisIcon name="calendar-check-fill" size="lg" color="brand" /></div>
                        <div>
                            <span class="pa-eyebrow">DETAIL SERVER</span>
                            <h3>{{ detail.siklus?.Nama_Cycle || cycle?.Nama_Cycle }}</h3>
                            <p>{{ detail.siklus?.Kode_Cycle || cycle?.Kode_Cycle }} · {{ detail.siklus?.Tahun || cycle?.Tahun }}</p>
                        </div>
                    </div>
                    <div class="pa-cycle-setup__snapshot-meta">
                        <HcisStatusBadge :status="detail.siklus?.Status_Cycle || cycle?.Status_Cycle" :label="detail.siklus?.Status_Cycle || cycle?.Status_Cycle" size="sm" dot />
                        <span>{{ detail.peserta?.total ?? 0 }} peserta tercatat</span>
                    </div>
                </section>

                <form class="pa-cycle-setup__form" @submit.prevent="submit">
                    <section class="pa-cycle-setup__section">
                        <div class="pa-cycle-setup__section-heading">
                            <div>
                                <span class="pa-eyebrow">IDENTITAS</span>
                                <h3>Kenali cycle ini</h3>
                                <p>Kode dibuat otomatis oleh server dan cycle baru selalu lahir sebagai DRAFT.</p>
                            </div>
                            <div class="pa-cycle-setup__section-icon pa-cycle-setup__section-icon--brand">
                                <HcisIcon name="fingerprint" size="lg" color="brand" />
                            </div>
                        </div>
                        <div class="pa-cycle-setup__grid pa-cycle-setup__grid--identity">
                            <HcisField
                                id="pa-cycle-kode"
                                :model-value="draft.kode_cycle || ''"
                                label="Kode cycle"
                                placeholder="Dibuat otomatis saat disimpan"
                                leading-icon="hash"
                                readonly
                                disabled
                                :hint="mode === 'edit'
                                    ? 'Dibuat server dan tidak bisa diubah — perintah dan laporan menyebut cycle lewat kode ini.'
                                    : 'Nomor urutnya menyusul tahun cycle, mis. PA-2026-01.'"
                            />
                            <HcisField
                                id="pa-cycle-nama"
                                v-model="draft.nama_cycle"
                                label="Nama cycle"
                                placeholder="Semester 1 2026"
                                leading-icon="card-heading"
                                required
                            />
                            <HcisField
                                id="pa-cycle-tahun"
                                v-model="draft.tahun"
                                label="Tahun"
                                type="number"
                                placeholder="2026"
                                leading-icon="calendar3"
                                :disabled="periodDisabled"
                                required
                                hint="Tahun harus selaras dengan periode yang dikirim ke server."
                            />
                            <HcisTextarea
                                id="pa-cycle-deskripsi"
                                v-model="draft.deskripsi"
                                label="Deskripsi"
                                placeholder="Catatan singkat untuk admin dan reviewer…"
                                :disabled="false"
                                :rows="3"
                                class="pa-cycle-setup__wide"
                            />
                        </div>
                    </section>

                    <section class="pa-cycle-setup__section">
                        <div class="pa-cycle-setup__section-heading">
                            <div>
                                <span class="pa-eyebrow">RENTANG KERJA</span>
                                <h3>Atur periode penilaian</h3>
                                <p>Rentang yang menentukan KPI dan waktu pengisian. Validasi akhir tetap milik server.</p>
                            </div>
                            <div class="pa-cycle-setup__section-icon pa-cycle-setup__section-icon--purple">
                                <HcisIcon name="calendar-range-fill" size="lg" color="purple" />
                            </div>
                        </div>

                        <!-- Sub-grup 1: Periode Dinilai (Month-Year Picker) -->
                        <div class="pa-cycle-setup__subgroup">
                            <div class="pa-cycle-setup__subgroup-title">
                                <HcisIcon name="calendar-check" size="xs" color="brand" />
                                <span>Periode dinilai (Siklus evaluasi KPI)</span>
                            </div>
                            <div class="pa-cycle-setup__grid">
                                <HcisField
                                    id="pa-cycle-period-start"
                                    label="Periode dinilai dari"
                                    :disabled="periodDisabled"
                                    :hint="draft.periode_dinilai_dari
                                        ? `Awal bulan: ${formatTanggalSingkat(draft.periode_dinilai_dari)} (${draft.periode_dinilai_dari})`
                                        : 'Pilih bulan & tahun (otomatis diset ke tanggal 1)'"
                                >
                                    <HcisDatePicker
                                        v-model="periodeDinilaiDariBulan"
                                        type="month"
                                        format="MMMM YYYY"
                                        placeholder="Pilih Bulan & Tahun"
                                        :disabled="periodDisabled"
                                        :show-presets="false"
                                        :clearable="!periodDisabled"
                                        aria-label="Periode dinilai dari"
                                    />
                                </HcisField>
                                <HcisField
                                    id="pa-cycle-period-end"
                                    label="Periode dinilai sampai"
                                    :disabled="periodDisabled"
                                    :hint="draft.periode_dinilai_sampai
                                        ? `Akhir bulan: ${formatTanggalSingkat(draft.periode_dinilai_sampai)} (${draft.periode_dinilai_sampai})`
                                        : 'Pilih bulan & tahun (otomatis diset ke akhir bulan)'"
                                >
                                    <HcisDatePicker
                                        v-model="periodeDinilaiSampaiBulan"
                                        type="month"
                                        format="MMMM YYYY"
                                        placeholder="Pilih Bulan & Tahun"
                                        :disabled="periodDisabled"
                                        :show-presets="false"
                                        :clearable="!periodDisabled"
                                        aria-label="Periode dinilai sampai"
                                    />
                                </HcisField>
                            </div>

                            <div v-if="rentangEvaluasiText" class="pa-cycle-setup__period-summary">
                                <HcisIcon name="check-circle-fill" size="xs" color="brand" />
                                <span>Rentang evaluasi KPI efektif: <strong>{{ rentangEvaluasiText }}</strong></span>
                            </div>
                        </div>

                        <!-- Sub-grup 2: Jendela Pengisian (Opsional) -->
                        <div class="pa-cycle-setup__subgroup">
                            <div class="pa-cycle-setup__subgroup-title">
                                <HcisIcon name="pencil-square" size="xs" />
                                <span>Jendela pengisian form</span>
                                <span class="pa-cycle-setup__subgroup-badge">Opsional</span>
                            </div>
                            <div class="pa-cycle-setup__grid">
                                <HcisField
                                    id="pa-cycle-filling-start"
                                    label="Pengisian dari"
                                    :disabled="jendelaDisabled"
                                    hint="Tanggal awal akses form penilaian dibuka"
                                >
                                    <HcisDatePicker
                                        v-model="draft.pengisian_dari"
                                        type="date"
                                        placeholder="YYYY-MM-DD"
                                        :disabled="jendelaDisabled"
                                        :show-presets="false"
                                        :clearable="!jendelaDisabled"
                                        aria-label="Pengisian dari"
                                    />
                                </HcisField>
                                <HcisField
                                    id="pa-cycle-filling-end"
                                    label="Pengisian sampai"
                                    :disabled="jendelaDisabled"
                                    hint="Batas akhir submit penilaian"
                                >
                                    <HcisDatePicker
                                        v-model="draft.pengisian_sampai"
                                        type="date"
                                        placeholder="YYYY-MM-DD"
                                        :disabled="jendelaDisabled"
                                        :show-presets="false"
                                        :clearable="!jendelaDisabled"
                                        aria-label="Pengisian sampai"
                                    />
                                </HcisField>
                            </div>
                        </div>
                    </section>

                    <div v-if="jendelaDisabled && detail?.alasan_jendela_terkunci" class="pa-cycle-setup__lock-note" role="note">
                        <HcisIcon name="lock-fill" size="2xs" />
                        <span>{{ detail.alasan_jendela_terkunci }}</span>
                    </div>

                    <div v-if="periodDisabled && detail?.alasan_periode_terkunci" class="pa-cycle-setup__lock-note" role="note">
                        <HcisIcon name="lock-fill" size="sm" color="warning" />
                        <div>
                            <strong>Periode dikunci oleh server</strong>
                            <p>{{ detail.alasan_periode_terkunci }}</p>
                            <small>Nama dan deskripsi tetap dapat disunting sesuai kontrak.</small>
                        </div>
                    </div>

                    <HcisResultState
                        v-if="validationError"
                        status="warning"
                        variant="flat"
                        layout="inline"
                        title="Form belum lengkap"
                        :description="validationError"
                        :show-retry="false"
                    />
                    <HcisResultState
                        v-if="saveError"
                        :status="saveErrorStatus === 403 ? 'warning' : 'danger'"
                        variant="flat"
                        layout="inline"
                        :title="saveErrorStatus === 403 ? 'Aksi tidak tersedia' : 'Perubahan ditolak'"
                        :description="saveError"
                        :error-code="saveErrorCode"
                        :request-id="saveRequestId"
                        :show-retry="false"
                    />
                    <HcisResultState
                        v-if="saveSuccess"
                        status="success"
                        variant="flat"
                        layout="inline"
                        title="Cycle tersimpan"
                        :description="saveSuccess"
                        :show-retry="false"
                    />
                    <div v-if="saveResult?.perlu_siapkan_ulang" class="pa-cycle-setup__reprepare" role="alert">
                        <HcisIcon name="arrow-repeat" size="sm" color="warning" />
                        <div>
                            <strong>Perlu siapkan ulang</strong>
                            <p>Salinan peserta masih memakai tanggal acuan lama. Jalankan setup cycle dari kartu cycle setelah meninjau perubahan ini.</p>
                        </div>
                    </div>
                </form>
            </template>
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint"><HcisIcon name="database-check" size="2xs" /> Keputusan periode dan validasi akhir berasal dari server</span>
        </template>
        <template #footer>
            <HcisButton variant="secondary" label="Tutup" size="sm" :disabled="saving" @click="$emit('update:modelValue', false)" />
            <HcisButton
                v-if="!(mode === 'edit' && (detailLoading || detailError))"
                variant="primary"
                :label="mode === 'create' ? 'Buat cycle' : 'Simpan perubahan'"
                size="sm"
                leading-icon="check2-circle"
                :loading="saving"
                :disabled="saving"
                @click="submit"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import {
    HcisButton,
    HcisDatePicker,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisStatusBadge,
    HcisTextarea,
} from '@/components/ui';

defineOptions({ name: 'PaCycleSetupDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    cycle: { type: Object, default: null },
    detail: { type: Object, default: null },
    form: { type: Object, default: () => ({}) },
    detailLoading: { type: Boolean, default: false },
    detailError: { type: String, default: '' },
    detailErrorStatus: { type: Number, default: 0 },
    detailErrorCode: { type: String, default: '' },
    detailRequestId: { type: String, default: '' },
    saving: { type: Boolean, default: false },
    saveError: { type: String, default: '' },
    saveErrorStatus: { type: Number, default: 0 },
    saveErrorCode: { type: String, default: '' },
    saveRequestId: { type: String, default: '' },
    saveSuccess: { type: String, default: '' },
    saveResult: { type: Object, default: null },
    validationError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'save']);
const draft = reactive({});

const periodDisabled = computed(() => props.mode === 'edit' && props.detail && props.detail.boleh_ubah_periode === false);

/*
 | JENDELA PENGISIAN PUNYA ATURANNYA SENDIRI.
 |
 | Sebelumnya satu penanda `periodDisabled` mematikan kelima kolom tanggal sekaligus, termasuk
 | "Pengisian dari" dan "Pengisian sampai". Padahal server memisahkan keduanya: periode
 | penilaian beku sesudah PREPARED karena ia menentukan baris KPI yang ditarik, sementara
 | jendela pengisian masih boleh diisi saat LOCKED karena ia hanya menentukan kapan penilai
 | boleh mengisi.
 |
 | Menyamakan keduanya di layar menutup satu-satunya jalan menuju pembukaan pengisian: membuka
 | pengisian MENUNTUT jendela terisi, dan siklus yang dikunci sebelum jendelanya diisi tidak
 | punya cara mengisinya lagi. Layar dan server harus memakai aturan yang sama, dan aturannya
 | milik server.
 |
 | Bawaannya IKUT `boleh_ubah_periode` bila server belum mengirim penanda barunya — dengan
 | begitu layar lama yang bertemu server lama tidak tiba-tiba membuka kolom yang akan ditolak.
 */
const jendelaDisabled = computed(() => {
    if (props.mode !== 'edit' || ! props.detail) {
        return false;
    }

    if (props.detail.boleh_ubah_jendela === undefined) {
        return props.detail.boleh_ubah_periode === false;
    }

    return props.detail.boleh_ubah_jendela === false;
});

const BULAN_LABEL_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function formatTanggalSingkat(tglStr) {
    if (!tglStr || typeof tglStr !== 'string') return '';
    const parts = tglStr.split('-');
    if (parts.length !== 3) return tglStr;
    const bIdx = parseInt(parts[1], 10) - 1;
    return `${parseInt(parts[2], 10)} ${BULAN_LABEL_PENDEK[bIdx] || parts[1]} ${parts[0]}`;
}

function awalBulanDariYm(ym) {
    if (!ym) return '';
    const [tahun, bulan] = ym.split('-').map(Number);
    if (!tahun || !bulan) return '';
    return `${tahun}-${String(bulan).padStart(2, '0')}-01`;
}

function akhirBulanDariYm(ym) {
    if (!ym) return '';
    const [tahun, bulan] = ym.split('-').map(Number);
    if (!tahun || !bulan) return '';
    const hariTerakhir = new Date(tahun, bulan, 0).getDate();
    return `${tahun}-${String(bulan).padStart(2, '0')}-${String(hariTerakhir).padStart(2, '0')}`;
}

const periodeDinilaiDariBulan = computed({
    get() {
        if (!draft.periode_dinilai_dari) return '';
        return String(draft.periode_dinilai_dari).slice(0, 7);
    },
    set(val) {
        if (!val) {
            draft.periode_dinilai_dari = '';
            return;
        }
        draft.periode_dinilai_dari = awalBulanDariYm(val);
        const [tahun] = val.split('-');
        if (tahun && !draft.tahun) {
            draft.tahun = tahun;
        }
    },
});

const periodeDinilaiSampaiBulan = computed({
    get() {
        if (!draft.periode_dinilai_sampai) return '';
        return String(draft.periode_dinilai_sampai).slice(0, 7);
    },
    set(val) {
        if (!val) {
            draft.periode_dinilai_sampai = '';
            return;
        }
        draft.periode_dinilai_sampai = akhirBulanDariYm(val);
    },
});

const rentangEvaluasiText = computed(() => {
    if (!draft.periode_dinilai_dari || !draft.periode_dinilai_sampai) return '';
    const dari = formatTanggalSingkat(draft.periode_dinilai_dari);
    const sampai = formatTanggalSingkat(draft.periode_dinilai_sampai);
    return `${dari} s/d ${sampai}`;
});

function syncDraft() {
    Object.keys(draft).forEach((key) => delete draft[key]);
    Object.assign(draft, props.form || {});
}

function submit() {
    emit('save', { ...draft });
}

watch(() => [props.modelValue, props.mode, props.form], syncDraft, { deep: true, immediate: true });
</script>

<style scoped>
.pa-cycle-setup {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
}
.pa-cycle-setup__form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
}
.pa-cycle-setup__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 18rem;
    color: var(--hcis-text-muted, #64748b);
}
.pa-cycle-setup__loading div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.pa-cycle-setup__loading strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.86rem;
}
.pa-cycle-setup__loading span {
    font-size: 0.74rem;
}
.pa-cycle-setup__snapshot,
.pa-cycle-setup__section,
.pa-cycle-setup__lock-note,
.pa-cycle-setup__reprepare {
    min-width: 0;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-lg, 14px);
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.75));
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.pa-cycle-setup__snapshot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem;
}
.pa-cycle-setup__snapshot-main,
.pa-cycle-setup__snapshot-meta,
.pa-cycle-setup__lock-note,
.pa-cycle-setup__reprepare {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.pa-cycle-setup__snapshot-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(99, 102, 241, 0.12);
}
.pa-cycle-setup__snapshot h3,
.pa-cycle-setup__section-heading h3 {
    margin: 0.2rem 0 0.18rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.98rem;
    font-weight: 700;
}
.pa-cycle-setup__snapshot p,
.pa-cycle-setup__section-heading p {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    line-height: 1.45;
}
.pa-cycle-setup__snapshot-meta {
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}
.pa-cycle-setup__section {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}
.pa-cycle-setup__section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0;
}
.pa-cycle-setup__section-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.6rem;
    height: 2.6rem;
    place-items: center;
    border-radius: var(--hcis-radius-md, 10px);
}
.pa-cycle-setup__section-icon--brand {
    background: rgba(79, 70, 229, 0.1);
}
.pa-cycle-setup__section-icon--purple {
    background: rgba(168, 85, 247, 0.12);
}
.pa-cycle-setup__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem 1.25rem;
}
.pa-cycle-setup__grid--identity {
    grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
}
.pa-cycle-setup__wide {
    grid-column: 1 / -1;
}
.pa-cycle-setup__subgroup {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding-top: 1rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}
.pa-cycle-setup__subgroup:first-of-type {
    padding-top: 0;
    border-top: none;
}
.pa-cycle-setup__subgroup-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}
.pa-cycle-setup__subgroup-badge {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.15);
    color: var(--hcis-text-muted, #64748b);
    margin-left: auto;
}
.pa-cycle-setup__period-summary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(99, 102, 241, 0.04));
    border: 1px solid rgba(79, 70, 229, 0.2);
    color: var(--hcis-brand-700, #4338ca);
    font-size: 0.75rem;
}
.pa-cycle-setup__period-summary strong {
    color: var(--hcis-brand-800, #3730a3);
    font-weight: 700;
}
.pa-cycle-setup__lock-note,
.pa-cycle-setup__reprepare {
    padding: 0.85rem 1rem;
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.92), rgba(255, 255, 255, 0.78));
}
.pa-cycle-setup__lock-note strong,
.pa-cycle-setup__reprepare strong {
    display: block;
    margin-bottom: 0.18rem;
    color: #92400e;
    font-size: 0.78rem;
}
.pa-cycle-setup__lock-note p,
.pa-cycle-setup__reprepare p {
    margin: 0;
    font-size: 0.74rem;
    line-height: 1.5;
}
.pa-cycle-setup__lock-note small {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.68rem;
}
.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}
.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}
@media (max-width: 720px) {
    .pa-cycle-setup {
        gap: 0.85rem;
    }
    .pa-cycle-setup__form {
        gap: 0.85rem;
    }
    .pa-cycle-setup__snapshot {
        align-items: flex-start;
        flex-direction: column;
        padding: 0.75rem;
        gap: 0.65rem;
    }
    .pa-cycle-setup__snapshot-icon {
        width: 2.25rem;
        height: 2.25rem;
    }
    .pa-cycle-setup__snapshot-meta {
        width: 100%;
        justify-content: space-between;
        padding-top: 0.5rem;
        border-top: 1px dashed rgba(203, 213, 225, 0.7);
    }
    .pa-cycle-setup__section {
        padding: 0.85rem;
        gap: 0.85rem;
    }
    .pa-cycle-setup__section-heading {
        margin-bottom: 0;
    }
    .pa-cycle-setup__section-heading h3 {
        font-size: 0.9rem;
    }
    .pa-cycle-setup__section-heading p {
        font-size: 0.7rem;
    }
    .pa-cycle-setup__section-icon {
        width: 2.25rem;
        height: 2.25rem;
    }
    .pa-cycle-setup__subgroup {
        gap: 0.75rem;
        padding-top: 0.75rem;
    }
    .pa-cycle-setup__grid,
    .pa-cycle-setup__grid--identity {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .pa-cycle-setup__wide {
        grid-column: auto;
    }
    .pa-cycle-setup__period-summary {
        font-size: 0.7rem;
        padding: 0.5rem 0.65rem;
    }
    .pa-dialog-hint {
        font-size: 0.65rem;
    }
}
</style>
