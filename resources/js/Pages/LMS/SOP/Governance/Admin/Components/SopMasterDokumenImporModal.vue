<template>
    <HcisDialog
        :model-value="show"
        title="Impor Register Dokumen"
        kicker="Identitas & versi dari Excel · berkas dilampirkan belakangan"
        icon="file-earmark-spreadsheet"
        size="xl"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div v-if="show">
            <div class="mdi-pilih">
                <input ref="input" type="file" accept=".xlsx" hidden @change="pilihBerkas" />
                <HcisButton
                    variant="secondary"
                    leading-icon="upload"
                    :label="berkas ? 'Ganti berkas' : 'Pilih berkas .xlsx'"
                    :disabled="sibuk"
                    @click="$refs.input.click()"
                />
                <span v-if="berkas" class="mdi-nama text-truncate">{{ berkas.name }}</span>
                <span v-else class="mdi-bantu">Sheet pertama, baris judul memuat kolom "Judul Dokumen".</span>
            </div>

            <!-- Hasil TIDAK pernah dipakai ulang untuk menulis: tombol Jalankan
                 mengirim berkas yang sama dan server menyusun rencananya lagi. -->
            <template v-if="hasil">
                <HcisAlert
                    :variant="sudahDijalankan ? 'success' : 'info'"
                    size="sm"
                    appearance="subtle"
                    class="mt-3"
                    :title="sudahDijalankan ? 'Impor selesai' : 'Dry run — belum ada yang ditulis'"
                >
                    <p class="mb-1 small">
                        <strong class="tnum">{{ r.total }}</strong> baris ·
                        <strong class="tnum">{{ r.siap }}</strong> siap ·
                        <strong class="tnum">{{ r.sudah_ada }}</strong> sudah ada (dilewati) ·
                        <strong class="tnum">{{ r.galat }}</strong> galat
                        <template v-if="sudahDijalankan">
                            · <strong class="tnum">{{ r.dibuat }}</strong> dibuat
                        </template>
                        <template v-else>
                            · <strong class="tnum">{{ r.versi_akan_dibuat }}</strong> versi akan dibuat
                        </template>
                    </p>
                    <p class="mb-1 small">
                        Versi berhenti di tahap <strong>Pengesahan</strong>. Setelah pengesah diisi dan berkas
                        dilampirkan, lanjutkan ke Pemberkasan lalu Filing &amp; Registered dari Master Dokumen.
                    </p>
                    <p v-if="kolomTakDisimpan.length" class="mb-0 small mdi-redup">
                        Tidak ikut tersimpan (belum ada tempatnya):
                        {{ kolomTakDisimpan.join(' · ') }}
                    </p>
                </HcisAlert>

                <div class="mdi-saring">
                    <button
                        v-for="s in saringan"
                        :key="s.kode"
                        type="button"
                        class="mdi-chip"
                        :class="{ aktif: tapis === s.kode }"
                        @click="tapis = s.kode"
                    >
                        {{ s.label }} <span class="tnum">{{ s.jumlah }}</span>
                    </button>
                </div>

                <div class="mdi-tabel-wrap">
                    <table class="mdi-tabel">
                        <thead>
                            <tr>
                                <th class="tnum">Baris</th>
                                <th>Status</th>
                                <th>Dokumen</th>
                                <th>Tipe</th>
                                <th>Versi</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in barisTampil" :key="b.baris">
                                <td class="tnum">{{ b.baris }}</td>
                                <td><span class="mdi-status" :class="`s-${b.status}`">{{ labelStatus(b.status) }}</span></td>
                                <td>
                                    <div class="mdi-judul">{{ b.judul }}</div>
                                    <div class="mdi-nomor tnum">{{ b.nomor_excel }}</div>
                                </td>
                                <td>{{ b.tipe || '—' }}</td>
                                <td class="tnum">{{ (b.versi || []).map((v) => labelVersi(v.versi_ke)).join(', ') || '—' }}</td>
                                <td>
                                    <div v-for="(g, i) in b.galat" :key="`g${i}`" class="mdi-galat">{{ g }}</div>
                                    <div v-for="(p, i) in b.peringatan" :key="`p${i}`" class="mdi-peringatan">{{ p }}</div>
                                </td>
                            </tr>
                            <tr v-if="!barisTampil.length">
                                <td colspan="6" class="text-center mdi-redup py-3">Tidak ada baris.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100 gap-2">
                <HcisButton variant="secondary" label="Tutup" :disabled="sibuk" @click="tutup" />
                <div class="d-flex gap-2">
                    <HcisButton
                        variant="secondary"
                        leading-icon="search"
                        :label="mode === 'dry_run' ? 'Memeriksa…' : 'Dry run'"
                        :loading="mode === 'dry_run'"
                        :disabled="!berkas || sibuk"
                        @click="kirim('dry_run')"
                    />
                    <HcisButton
                        variant="primary"
                        leading-icon="cloud-upload"
                        :label="mode === 'jalankan' ? `Mengimpor ${progres ? progres.selesai : 0}/${progres ? progres.total : 0}…` : `Jalankan impor (${r.siap || 0})`"
                        :loading="mode === 'jalankan'"
                        :disabled="!bolehJalankan"
                        @click="kirim('jalankan')"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { HcisDialog, HcisAlert, HcisButton, hcisMessage } from '@/components/ui';
import { labelVersi } from '../../../Shared/support/versi.js';

const API = '/api/v1/sop/admin/dokumen/impor-register';

/**
 * Dokumen per permintaan saat menjalankan. Satu dokumen ±70 kueri; jendela kecil
 * menjaga tiap permintaan jauh di bawah batas waktu web, dan kemajuan terlihat.
 */
const PER_BATCH = 5;

const LABEL_STATUS = {
    SIAP: 'Siap',
    SUDAH_ADA: 'Sudah ada',
    GALAT: 'Galat',
    DIBUAT: 'Dibuat',
};

/**
 * Impor register dokumen dari Excel, dua langkah: dry run lalu jalankan.
 *
 * Jalankan hanya terbuka setelah dry run atas BERKAS YANG SAMA berhasil dan
 * menyisakan baris siap. Mengganti berkas mengosongkan hasil, supaya angka yang
 * terlihat tak pernah milik berkas lain.
 */
export default {
    name: 'SopMasterDokumenImporModal',
    components: { HcisDialog, HcisAlert, HcisButton },
    props: {
        show: { type: Boolean, default: false },
    },
    emits: ['close', 'berhasil'],
    data() {
        return { berkas: null, hasil: null, mode: null, sudahDijalankan: false, tapis: 'SEMUA', progres: null };
    },
    computed: {
        r() {
            return this.hasil?.ringkasan || {};
        },
        sibuk() {
            return this.mode !== null;
        },
        bolehJalankan() {
            return !!this.berkas && !this.sibuk && !!this.hasil && !this.sudahDijalankan && (this.r.siap || 0) > 0;
        },
        kolomTakDisimpan() {
            return Object.entries(this.r.kolom_tak_disimpan || {}).map(([k, n]) => `${k} (${n})`);
        },
        saringan() {
            const baris = this.hasil?.baris || [];
            const hitung = (s) => baris.filter((b) => b.status === s).length;
            const daftar = [{ kode: 'SEMUA', label: 'Semua', jumlah: baris.length }];
            ['GALAT', 'SIAP', 'DIBUAT', 'SUDAH_ADA'].forEach((s) => {
                const n = hitung(s);
                if (n) daftar.push({ kode: s, label: LABEL_STATUS[s], jumlah: n });
            });
            const berperingatan = baris.filter((b) => (b.peringatan || []).length).length;
            if (berperingatan) daftar.push({ kode: 'PERINGATAN', label: 'Ada peringatan', jumlah: berperingatan });
            return daftar;
        },
        barisTampil() {
            const baris = this.hasil?.baris || [];
            if (this.tapis === 'SEMUA') return baris;
            if (this.tapis === 'PERINGATAN') return baris.filter((b) => (b.peringatan || []).length);
            return baris.filter((b) => b.status === this.tapis);
        },
    },
    watch: {
        show(v) {
            if (v) this.reset();
        },
    },
    methods: {
        labelVersi,
        labelStatus(s) {
            return LABEL_STATUS[s] || s;
        },
        reset() {
            this.berkas = null;
            this.hasil = null;
            this.mode = null;
            this.sudahDijalankan = false;
            this.tapis = 'SEMUA';
            this.progres = null;
        },
        pilihBerkas(e) {
            const f = e.target.files?.[0] || null;
            e.target.value = '';
            if (!f) return;
            this.berkas = f;
            this.hasil = null;
            this.sudahDijalankan = false;
            this.tapis = 'SEMUA';
        },
        async kirim(mode) {
            if (!this.berkas || this.sibuk) return;
            if (mode === 'jalankan') return this.jalankan();

            this.mode = mode;
            try {
                const res = await axios.post(API, this.form({ mode }));
                this.hasil = res.data?.result || null;
                this.tapis = (this.r.galat || 0) > 0 ? 'GALAT' : 'SEMUA';
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Dry run gagal diproses.');
            } finally {
                this.mode = null;
            }
        },
        /**
         * Jalankan per jendela sampai server menyatakan tak ada lagi. Server
         * menyusun rencana ulang tiap jendela; baris yang ditulis jendela
         * sebelumnya menjadi SUDAH_ADA di sana, jadi berhenti di tengah lalu
         * menjalankan ulang tak menggandakan apa pun.
         */
        async jalankan() {
            const baris = new Map((this.hasil?.baris || []).map((b) => [b.baris, b]));
            const ringkasan = { ...this.r, dibuat: 0, gagal_ditulis: 0 };
            let mulai = 0;
            this.mode = 'jalankan';
            this.progres = { selesai: 0, total: this.r.total || 0 };
            try {
                while (mulai !== null) {
                    const res = await axios.post(API, this.form({ mode: 'jalankan', mulai, jumlah: PER_BATCH }));
                    const hasil = res.data?.result || {};
                    (hasil.baris || []).forEach((b) => baris.set(b.baris, b));
                    ringkasan.dibuat += hasil.ringkasan?.dibuat || 0;
                    ringkasan.gagal_ditulis += hasil.ringkasan?.gagal_ditulis || 0;
                    this.progres = { selesai: hasil.batch?.sampai ?? 0, total: hasil.batch?.total ?? this.progres.total };
                    mulai = hasil.batch?.berikutnya ?? null;
                }
                this.sudahDijalankan = true;
                hcisMessage.success(`${ringkasan.dibuat} dokumen diimpor.`);
            } catch (e) {
                hcisMessage.error(
                    `${e?.response?.data?.message || 'Impor terhenti.'} ${ringkasan.dibuat} dokumen sudah tersimpan — `
                    + 'jalankan dry run lagi untuk melanjutkan; yang sudah ada akan dilewati.',
                );
            } finally {
                const semua = [...baris.values()].sort((a, b) => a.baris - b.baris);
                ringkasan.galat = semua.filter((b) => b.status === 'GALAT').length;
                this.hasil = { ringkasan, baris: semua };
                this.tapis = ringkasan.galat > 0 ? 'GALAT' : 'SEMUA';
                this.mode = null;
                if (ringkasan.dibuat > 0) this.$emit('berhasil');
            }
        },
        form(isian) {
            const form = new FormData();
            form.append('berkas', this.berkas);
            Object.entries(isian).forEach(([k, v]) => form.append(k, String(v)));
            return form;
        },
        onDialogUpdate(v) {
            if (!v) this.tutup();
        },
        tutup() {
            if (this.sibuk) return;
            this.$emit('close');
        },
    },
};
</script>

<style scoped>
.mdi-pilih { display: flex; align-items: center; gap: .75rem; min-width: 0; }
.mdi-nama { font-weight: 600; min-width: 0; }
.mdi-bantu, .mdi-redup { color: var(--hcis-text-muted, #64748b); font-size: .8125rem; }
.mdi-saring { display: flex; flex-wrap: wrap; gap: .375rem; margin: .75rem 0 .5rem; }
.mdi-chip {
    border: 1px solid var(--hcis-border, #e2e8f0); background: transparent; border-radius: 999px;
    padding: .125rem .625rem; font-size: .75rem; color: inherit;
}
.mdi-chip.aktif { border-color: var(--hcis-brand, #2563eb); color: var(--hcis-brand, #2563eb); font-weight: 600; }
.mdi-tabel-wrap { max-height: 52vh; overflow: auto; border: 1px solid var(--hcis-border, #e2e8f0); border-radius: .5rem; }
.mdi-tabel { width: 100%; font-size: .8125rem; border-collapse: collapse; }
.mdi-tabel th {
    position: sticky; top: 0; background: var(--hcis-surface, #fff); z-index: 1;
    text-align: left; font-weight: 600; padding: .5rem; border-bottom: 1px solid var(--hcis-border, #e2e8f0);
}
.mdi-tabel td { padding: .5rem; vertical-align: top; border-bottom: 1px solid var(--hcis-border-subtle, #f1f5f9); }
.mdi-judul { font-weight: 600; }
.mdi-nomor { color: var(--hcis-text-muted, #64748b); font-size: .75rem; }
.mdi-status { font-size: .6875rem; font-weight: 700; padding: .0625rem .5rem; border-radius: 999px; white-space: nowrap; }
.s-SIAP { background: #eff6ff; color: #1d4ed8; }
.s-DIBUAT { background: #ecfdf5; color: #047857; }
.s-SUDAH_ADA { background: #f1f5f9; color: #475569; }
.s-GALAT { background: #fef2f2; color: #b91c1c; }
.mdi-galat { color: #b91c1c; }
.mdi-peringatan { color: #92400e; }
.mdi-galat + .mdi-peringatan, .mdi-peringatan + .mdi-peringatan, .mdi-galat + .mdi-galat { margin-top: .125rem; }
</style>
