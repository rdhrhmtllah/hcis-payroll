<template>
    <HcisDialog
        :model-value="modelValue"
        :title="`Versi: ${panduan?.judul || ''}`"
        description="Kelola siklus riwayat dokumen. Berkas versi lama tetap tersimpan dan dapat diaktifkan kembali kapan saja."
        icon="clock-history"
        sentiment="brand"
        size="lg"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <HcisAlert
            v-if="!tersedia"
            variant="warning"
            appearance="subtle"
            title="Versioning belum tersedia"
            description="DDL-2026-149 belum dijalankan di basis data ini."
        />

        <template v-else>
            <HcisAlert
                v-if="pengingatHalaman"
                variant="warning"
                appearance="glass"
                class="mb-3"
                title="Cek Halaman Mulai PDF"
                description="Versi aktif telah berganti. Penomoran halaman PDF mungkin bergeser, pastikan kolom 'Buka di hal. PDF' pada tiap halaman masih tepat."
                data-testid="versi-pengingat"
            >
                <template #actions>
                    <HcisButton variant="secondary" size="sm" leading-icon="diagram-3" label="Cek halaman" @click="emit('ubah-halaman')" />
                </template>
            </HcisAlert>

            <!-- Unggah versi baru -->
            <div class="versi-unggah mb-4" data-testid="versi-unggah">
                <div class="versi-unggah__header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="versi-jewel-icon">
                            <i class="bi bi-cloud-arrow-up-fill" aria-hidden="true" />
                        </div>
                        <div>
                            <div class="versi-unggah__title">Unggah Versi Dokumen Baru</div>
                            <div class="versi-sub">Pilih berkas PDF maksimal {{ maksMb }} MB. Simpan sebagai draf atau terbitkan langsung.</div>
                        </div>
                    </div>
                </div>

                <div class="versi-unggah__body">
                    <HcisUpload v-model="unggah.berkas" accept=".pdf" :limit="1" :max-size="maksMb" layout="bar" />
                    <p v-if="galat.berkas" class="versi-galat">{{ galat.berkas }}</p>

                    <div class="versi-unggah__isian mt-2">
                        <HcisField
                            id="versi-label"
                            v-model="unggah.label"
                            label="Label Versi"
                            placeholder="Contoh: v3 - Okt 2026"
                            :error="galat.label"
                        />
                        <HcisField
                            id="versi-catatan"
                            v-model="unggah.catatan"
                            type="textarea"
                            rows="2"
                            label="Catatan Perubahan"
                            placeholder="Ringkasan pembaruan yang tampil ke pengguna saat panduan diperbarui..."
                            :error="galat.catatan"
                        />
                    </div>
                </div>

                <div class="versi-unggah__aksi">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="file-earmark"
                        label="Simpan draf"
                        :loading="unggah.menyimpan === 'draf'"
                        :disabled="!!unggah.menyimpan"
                        @click="kirim(false)"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="send-fill"
                        label="Unggah & terbitkan"
                        :loading="unggah.menyimpan === 'terbit'"
                        :disabled="!!unggah.menyimpan"
                        @click="kirim(true)"
                    />
                </div>
            </div>

            <!-- Riwayat -->
            <div class="versi-riwayat" data-testid="versi-riwayat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-slate-500" aria-hidden="true" />
                        <span class="fw-bold text-slate-800 fs-6">Riwayat Versi Dokumen</span>
                    </div>
                    <HcisBadge
                        v-if="daftar.length"
                        :label="`${daftar.length} Versi Tersimpan`"
                        variant="neutral"
                        appearance="subtle"
                        size="xs"
                    />
                </div>

                <div v-if="memuat" class="py-4 text-center">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status" aria-hidden="true" />
                    <p class="versi-sub">Memuat riwayat versi…</p>
                </div>

                <div v-else-if="!daftar.length" class="text-center py-4 text-muted">
                    <i class="bi bi-journal-x fs-3 text-slate-300 d-block mb-1" />
                    <span>Belum ada versi dokumen terdaftar.</span>
                </div>

                <div v-else class="versi-timeline">
                    <div
                        v-for="v in daftar"
                        :key="v.id"
                        class="versi-baris"
                        :class="{ 'is-aktif': v.aktif, 'is-draf': v.status === 'DRAF' }"
                        :data-versi="v.no"
                    >
                        <div class="versi-baris__top">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <span class="versi-baris__no-badge" :class="{ 'is-aktif': v.aktif, 'is-draf': v.status === 'DRAF' }">
                                    <span class="versi-baris__no">v{{ v.no }}</span>
                                </span>
                                <div class="min-w-0">
                                    <div class="versi-baris__label text-truncate" :title="v.label || v.nama_file || 'Tanpa label'">
                                        {{ v.label || v.nama_file || 'Tanpa label' }}
                                    </div>
                                    <div class="versi-sub">
                                        <i class="bi bi-calendar3 me-1" aria-hidden="true" />
                                        {{ v.status === 'DRAF' ? 'Diunggah' : 'Terbit' }} {{ tanggal(v.status === 'DRAF' ? v.dibuat_at : v.diterbitkan_at) }}
                                        <template v-if="v.status === 'DRAF' ? v.dibuat_by : v.diterbitkan_by">
                                            · oleh <span class="fw-semibold text-slate-700">{{ v.status === 'DRAF' ? v.dibuat_by : v.diterbitkan_by }}</span>
                                        </template>
                                        <template v-if="v.ukuran_byte">
                                            · <span class="font-tabular">{{ ukuran(v.ukuran_byte) }}</span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <HcisBadge v-if="v.aktif" label="Aktif" variant="success" appearance="glass" size="xs" dot />
                                <HcisBadge v-else-if="v.status === 'DRAF'" label="Draf" variant="warning" appearance="subtle" size="xs" />
                                <HcisBadge v-else label="Arsip" variant="neutral" appearance="subtle" size="xs" />
                            </div>
                        </div>

                        <div v-if="v.catatan" class="versi-baris__catatan">
                            <i class="bi bi-quote text-slate-400 me-1" />
                            <span>{{ v.catatan }}</span>
                        </div>

                        <div class="versi-baris__aksi">
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="eye"
                                label="Lihat"
                                @click="emit('lihat', berkasVersi(v))"
                            />
                            <template v-if="v.status === 'DRAF'">
                                <HcisButton
                                    variant="primary-subtle"
                                    size="xs"
                                    leading-icon="send"
                                    label="Terbitkan"
                                    @click="aktifkan(v)"
                                />
                                <HcisButton
                                    variant="danger-quiet"
                                    size="xs"
                                    leading-icon="trash"
                                    label="Hapus draf"
                                    @click="hapus(v)"
                                />
                            </template>
                            <HcisButton
                                v-else-if="!v.aktif"
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-counterclockwise"
                                label="Jadikan aktif"
                                @click="aktifkan(v)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <HcisButton variant="secondary" size="md" label="Tutup" @click="emit('update:modelValue', false)" />
        </template>
    </HcisDialog>
</template>

<script setup>
/**
 * Riwayat & unggah versi berkas satu panduan (DDL-2026-149): simpan draf atau
 * langsung terbit, terbitkan draf, kembalikan versi lama, hapus draf.
 */
import axios from 'axios';
import { reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisUpload,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    panduan: { type: Object, default: null },
    maksMb: { type: Number, default: 30 },
});
const emit = defineEmits(['update:modelValue', 'berubah', 'lihat', 'ubah-halaman']);

const memuat = ref(false);
const tersedia = ref(true);
const daftar = ref([]);
const pengingatHalaman = ref(false);
const unggah = reactive({ berkas: [], label: '', catatan: '', menyimpan: '' });
const galat = reactive({ berkas: '', label: '', catatan: '' });

const dasar = () => `/system/panduan/${props.panduan?.id}/versi`;

function pesanGalat(e, cadangan) {
    return e?.response?.data?.message || cadangan;
}

function terima(hasil) {
    tersedia.value = hasil?.tersedia !== false;
    daftar.value = hasil?.versi || [];
}

async function muat() {
    if (!props.panduan?.id) return;
    memuat.value = true;
    try {
        const { data } = await axios.get(dasar());
        terima(data?.result);
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Riwayat versi gagal dimuat.'));
    } finally {
        memuat.value = false;
    }
}

watch(
    () => [props.modelValue, props.panduan?.id],
    ([buka]) => {
        if (!buka) return;
        Object.assign(unggah, { berkas: [], label: '', catatan: '', menyimpan: '' });
        Object.keys(galat).forEach((k) => (galat[k] = ''));
        pengingatHalaman.value = false;
        muat();
    },
    { immediate: true },
);

function selesai(hasil, gantiAktif) {
    terima(hasil);
    if (gantiAktif) pengingatHalaman.value = true;
    emit('berubah');
}

async function kirim(terbitkan) {
    Object.keys(galat).forEach((k) => (galat[k] = ''));
    const berkas = unggah.berkas?.[0]?.raw;
    if (!berkas) {
        galat.berkas = 'Pilih berkas PDF versi baru.';
        return;
    }
    if (terbitkan) {
        const ok = await hcisConfirm({
            title: 'Terbitkan versi baru?',
            description: 'Pengguna langsung melihat versi ini. Versi sekarang tetap tersimpan di riwayat.',
            confirmText: 'Terbitkan',
        }).catch(() => false);
        if (!ok) return;
    }

    const isi = new FormData();
    isi.append('berkas', berkas);
    isi.append('label', unggah.label || '');
    isi.append('catatan', unggah.catatan || '');
    isi.append('terbitkan', terbitkan ? '1' : '0');

    unggah.menyimpan = terbitkan ? 'terbit' : 'draf';
    try {
        const { data } = await axios.post(dasar(), isi);
        hcisMessage.success(terbitkan ? 'Versi baru diterbitkan.' : 'Draf disimpan.');
        Object.assign(unggah, { berkas: [], label: '', catatan: '' });
        selesai(data?.result, terbitkan);
    } catch (e) {
        const errors = e?.response?.data?.errors || {};
        Object.keys(galat).forEach((k) => (galat[k] = errors[k]?.[0] || ''));
        if (!Object.keys(errors).length) hcisMessage.error(pesanGalat(e, 'Versi gagal diunggah.'));
    } finally {
        unggah.menyimpan = '';
    }
}

async function aktifkan(v) {
    const draf = v.status === 'DRAF';
    const ok = await hcisConfirm({
        title: draf ? `Terbitkan v${v.no}?` : `Kembalikan ke v${v.no}?`,
        description: draf
            ? 'Pengguna langsung melihat versi ini. Versi sekarang tetap tersimpan di riwayat.'
            : 'Versi ini kembali tampil ke pengguna. Versi sekarang tetap tersimpan di riwayat dan bisa diaktifkan lagi.',
        confirmText: draf ? 'Terbitkan' : 'Jadikan aktif',
    }).catch(() => false);
    if (!ok) return;

    try {
        const { data } = await axios.post(`${dasar()}/${v.id}/aktifkan`);
        hcisMessage.success(draf ? `v${v.no} diterbitkan.` : `v${v.no} aktif kembali.`);
        selesai(data?.result, true);
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Versi gagal diaktifkan.'));
    }
}

async function hapus(v) {
    const ok = await hcisConfirm({
        title: `Hapus draf v${v.no}?`,
        description: 'Berkas draf ikut dihapus dan tidak bisa dikembalikan.',
        confirmText: 'Hapus',
        sentiment: 'danger',
    }).catch(() => false);
    if (!ok) return;

    try {
        const { data } = await axios.delete(`${dasar()}/${v.id}`);
        selesai(data?.result, false);
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Draf gagal dihapus.'));
    }
}

function berkasVersi(v) {
    const judul = `${props.panduan?.judul || 'Panduan'} — v${v.no}${v.label ? ` (${v.label})` : ''}`;
    return { url: `${dasar()}/${v.id}/berkas`, name: v.nama_file || `${judul}.pdf`, title: judul, ext: 'pdf' };
}

function tanggal(nilai) {
    if (!nilai) return '-';
    const d = new Date(String(nilai).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? String(nilai) : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function ukuran(byte) {
    return byte >= 1048576 ? `${(byte / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(byte / 1024))} KB`;
}
</script>

<style scoped>
.versi-sub {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

.versi-galat {
    margin: 0.35rem 0 0;
    font-size: 0.8rem;
    color: var(--hcis-danger-600, #dc2626);
}

.versi-unggah {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px dashed var(--hcis-brand-300, #a5b4fc);
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.5) 0%, rgba(255, 255, 255, 0.8) 100%);
    box-shadow: 0 2px 6px -1px rgba(99, 102, 241, 0.06);
}

.versi-jewel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #4338ca;
    font-size: 0.95rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.versi-unggah__title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    letter-spacing: -0.01em;
}

.versi-unggah__body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.versi-unggah__isian {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    gap: 0.75rem;
}

.versi-unggah__isian > * {
    min-width: 0;
}

.versi-unggah__aksi {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.12);
}

.versi-riwayat {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.versi-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.versi-baris {
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 10px;
    background: #ffffff;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.versi-baris:hover {
    border-color: var(--hcis-slate-300, #cbd5e1);
    box-shadow: 0 3px 8px -2px rgba(0, 0, 0, 0.05);
}

.versi-baris.is-aktif {
    border-color: rgba(16, 185, 129, 0.45);
    border-left: 4.5px solid var(--hcis-success-500, #10b981);
    background: linear-gradient(225deg, rgba(236, 253, 245, 0.6) 0%, rgba(255, 255, 255, 0.95) 100%);
    box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.12);
}

.versi-baris.is-draf {
    border-style: dashed;
    border-color: var(--hcis-warning-400, #fbbf24);
    background: rgba(254, 243, 199, 0.25);
}

.versi-baris__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
}

.versi-baris__no-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-700, #334155);
    flex-shrink: 0;
}

.versi-baris__no-badge.is-aktif {
    background: #d1fae5;
    color: #065f46;
}

.versi-baris__no-badge.is-draf {
    background: #fef3c7;
    color: #92400e;
}

.versi-baris__no {
    font-weight: 800;
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
}

.versi-baris__label {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--hcis-slate-800, #1e293b);
}

.versi-baris__catatan {
    margin: 0.45rem 0 0;
    padding: 0.35rem 0.6rem;
    font-size: 0.81rem;
    color: var(--hcis-slate-600, #475569);
    background: rgba(241, 245, 249, 0.6);
    border-left: 2px solid var(--hcis-slate-300, #cbd5e1);
    border-radius: 4px;
    white-space: pre-line;
}

.versi-baris__aksi {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px solid var(--hcis-slate-100, #f1f5f9);
}

@media (max-width: 640px) {
    .versi-unggah__isian {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
