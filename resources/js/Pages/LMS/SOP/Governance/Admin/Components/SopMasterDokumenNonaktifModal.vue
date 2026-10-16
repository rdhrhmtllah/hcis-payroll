<template>
    <HcisDialog
        :model-value="show"
        title="Nonaktifkan Dokumen"
        kicker="Menutup dokumen dari pemakaian baru"
        icon="archive"
        size="md"
        sentiment="danger"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div v-if="show">
            <p class="mdn-judul">{{ dokumen?.Judul || '(tanpa judul)' }}</p>
            <p v-if="dokumen?.Nomor_Lengkap" class="mdn-nomor tnum">{{ dokumen.Nomor_Lengkap }}</p>

            <div v-if="memuat" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>

            <template v-else-if="sebaran">
                <!-- APA YANG TERJADI, dan yang tak kalah penting: apa yang TIDAK.
                     Kalimat pertama admin butuhkan sebelum melihat angka, karena
                     angkanya baru punya arti setelah ia tahu angka itu bukan
                     daftar yang akan ditarik. -->
                <HcisAlert
                    v-if="sebaran.paket > 0"
                    variant="warning"
                    size="sm"
                    appearance="subtle"
                    class="mt-3"
                    title="Dokumen ini masih beredar"
                >
                    <p class="mb-2 small">
                        <strong class="tnum">{{ sebaran.paket }} paket</strong> dan
                        <strong class="tnum">{{ sebaran.orang }} karyawan</strong> masih memegang dokumen ini, dan
                        <strong>semuanya tetap berjalan</strong> setelah dinonaktifkan. Penonaktifan hanya menutup
                        pemakaian baru — dokumen berhenti ditawarkan di Document Builder.
                    </p>
                    <p v-if="sebaran.penugasan !== sebaran.orang" class="mb-2 small mdn-selisih">
                        {{ sebaran.orang }} karyawan tersebut memegangnya lewat
                        <strong class="tnum">{{ sebaran.penugasan }} penugasan</strong> — sebagian orang menerimanya
                        dari lebih dari satu paket.
                    </p>
                    <p class="mb-0 small">
                        Untuk benar-benar menariknya dari mereka, lepaskan itemnya dari paket masing-masing.
                    </p>
                </HcisAlert>

                <p v-else class="mdn-tenang mt-3">
                    Belum ada paket yang memakai dokumen ini, jadi tak ada karyawan yang terdampak.
                </p>

                <table v-if="sebaran.rincian.length" class="mdn-tabel">
                    <thead>
                        <tr>
                            <th>Paket yang masih memakainya</th>
                            <th class="tnum">Karyawan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in rincianTampil" :key="i">
                            <td class="text-truncate">{{ p.Nama_Paket }}</td>
                            <td class="tnum">{{ p.Jumlah_Karyawan }}</td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pembatasan daftar DINYATAKAN, tak pernah diam-diam. Daftar yang
                     terpotong tanpa keterangan terbaca sebagai daftar yang lengkap. -->
                <p v-if="sisaPaket > 0" class="mdn-sisa">
                    …dan {{ sisaPaket }} paket lain.
                </p>

                <HcisTextarea
                    id="mdn-catatan"
                    v-model="catatan"
                    class="mt-3"
                    label="Catatan penonaktifan"
                    placeholder="Kenapa dokumen ini dipensiunkan? Contoh: digantikan SOP-QA-014 revisi 03."
                    :rows="3"
                    :maxlength="MAKS"
                />
                <!-- Alasan catatan diwajibkan disebutkan, bukan sekadar dipaksakan:
                     kewajiban yang tak dijelaskan diisi seadanya. -->
                <div class="mdn-bantu">
                    <span :class="{ 'mdn-kurang': catatanKurang }">
                        {{ catatan.trim().length }}/{{ MAKS }}
                        <template v-if="catatanKurang">· minimal {{ MIN }} karakter</template>
                    </span>
                    <span class="mdn-kenapa">
                        Penonaktifan tak bisa dibatalkan lewat layar — catatan ini satu-satunya yang menjelaskan
                        sebabnya nanti.
                    </span>
                </div>

                <p v-if="galat" class="mdn-galat">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ galat }}
                </p>
            </template>
        </div>

        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100">
                <HcisButton variant="secondary" label="Batal" :disabled="mengirim" @click="tutup" />
                <HcisButton
                    variant="danger"
                    leading-icon="archive"
                    :loading="mengirim"
                    :disabled="!bolehKirim"
                    :label="mengirim ? 'Menonaktifkan…' : 'Ya, Nonaktifkan'"
                    @click="kirim"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { HcisDialog, HcisAlert, HcisButton, HcisTextarea, hcisMessage } from '@/components/ui';

const API = '/api/v1/sop/admin/dokumen';

/** Sepadan dengan SopDokumenNonaktifService::CATATAN_MIN / CATATAN_MAKS. */
const MIN = 10;
const MAKS = 500;

/** Paket yang ditampilkan sebelum daftarnya dipendekkan. */
const TAMPIL_MAKS = 8;

/**
 * Konfirmasi penonaktifan dokumen.
 *
 * Sebaran DIAMBIL DARI SERVER setiap kali dialog dibuka, bukan diwariskan dari
 * kartu detail. Angka di kartu itu penjumlahan hitungan per-paket, sehingga
 * seseorang yang memegang dokumen lewat dua paket terhitung dua kali; yang
 * ditanyakan di sini adalah berapa KEPALA, dan dua angka berbeda untuk satu
 * pertanyaan pada satu layar adalah persis kebingungan yang dialog ini ada
 * untuk mencegahnya.
 */
export default {
    name: 'SopMasterDokumenNonaktifModal',
    components: { HcisDialog, HcisAlert, HcisButton, HcisTextarea },
    props: {
        show: { type: Boolean, default: false },
        /** Hashid dokumen; wajib saat show bernilai true. */
        id: { type: String, default: null },
        /** Baris dokumen, semata untuk judul dan nomornya. */
        dokumen: { type: Object, default: null },
    },
    emits: ['close', 'berhasil'],
    data() {
        return { MIN, MAKS, sebaran: null, catatan: '', memuat: false, mengirim: false, galat: '' };
    },
    computed: {
        rincianTampil() {
            return (this.sebaran?.rincian || []).slice(0, TAMPIL_MAKS);
        },
        sisaPaket() {
            return Math.max(0, (this.sebaran?.rincian || []).length - TAMPIL_MAKS);
        },
        catatanKurang() {
            return this.catatan.trim().length < MIN;
        },
        bolehKirim() {
            return !this.mengirim && !this.memuat && !!this.sebaran && !this.catatanKurang;
        },
    },
    watch: {
        show: {
            immediate: true,
            handler(v) {
                /* Tiap pembukaan mulai dari nol. Catatan yang tertinggal dari
                   dokumen sebelumnya akan tersimpan pada dokumen yang salah,
                   dan jejak yang salah lebih buruk daripada jejak yang kosong. */
                this.sebaran = null;
                this.catatan = '';
                this.galat = '';
                if (v && this.id) this.muat();
            },
        },
    },
    methods: {
        async muat() {
            this.memuat = true;
            try {
                const r = await axios.get(`${API}/${this.id}/sebaran-nonaktif`);
                this.sebaran = r.data?.result || { paket: 0, orang: 0, penugasan: 0, rincian: [] };
                this.sebaran.rincian = this.sebaran.rincian || [];
            } catch (e) {
                /* Gagal menghitung sebaran TIDAK boleh jatuh ke penonaktifan
                   langsung. Menonaktifkan tanpa memperlihatkan siapa yang masih
                   memegangnya persis keadaan yang konfirmasi ini ada untuk
                   mencegah. */
                hcisMessage.error(e?.response?.data?.message || 'Gagal menghitung sebaran dokumen.');
                this.$emit('close');
            } finally {
                this.memuat = false;
            }
        },
        async kirim() {
            if (!this.bolehKirim) return;
            this.mengirim = true;
            this.galat = '';
            try {
                const r = await axios.delete(`${API}/delete/${this.id}`, {
                    data: { catatan: this.catatan.trim() },
                });
                hcisMessage.success(r.data?.message || 'Dokumen dinonaktifkan.');
                this.$emit('berhasil');
                this.$emit('close');
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Gagal menonaktifkan dokumen.';
            } finally {
                this.mengirim = false;
            }
        },
        tutup() {
            if (this.mengirim) return;
            this.$emit('close');
        },
        onDialogUpdate(val) {
            if (!val) this.tutup();
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.mdn-judul {
    margin: 0;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--hcis-slate-900);
}

.mdn-nomor {
    margin: 0.1rem 0 0;
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
}

.mdn-tenang {
    margin: 0;
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
}

.mdn-selisih {
    color: #7c2d12;
}

.mdn-tabel {
    width: 100%;
    margin-top: 0.75rem;
    font-size: 0.82rem;
    border-collapse: collapse;
}

.mdn-tabel th {
    text-align: left;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400);
    padding-bottom: 0.4rem;
    border-bottom: 1px solid var(--bs-border-color, #e5e7eb);
}

.mdn-tabel td {
    padding: 0.4rem 0.5rem 0.4rem 0;
    color: var(--hcis-slate-900);
    max-width: 0;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.mdn-tabel tr:last-child td {
    border-bottom: 0;
}

.mdn-tabel th:last-child,
.mdn-tabel td:last-child {
    text-align: right;
    padding-right: 0;
}

.mdn-sisa {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}

.mdn-bantu {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    margin-top: 0.35rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.mdn-kurang {
    color: var(--hcis-amber-700);
    font-weight: 600;
}

.mdn-kenapa {
    line-height: 1.35;
}

.mdn-galat {
    margin: 0.7rem 0 0;
    font-size: 0.78rem;
    color: var(--hcis-red-700);
}
</style>
