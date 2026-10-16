<template>
    <!-- PRATINJAU SEBELUM TERBIT, dipakai bersama oleh modal versi dan panel
         Master Dokumen. Sengaja satu komponen: dua tempat yang sama-sama bisa
         menerbitkan, dengan dua salinan konfirmasi, pasti menyimpang — dan yang
         menyimpang diam-diam adalah yang jarang dibuka. -->
    <SopModal
        :show="!!versi"
        :title="judul"
        :subtitle="subjudul"
        :icon="rollback ? 'bi-arrow-counterclockwise' : 'bi-broadcast'"
        size="md"
        @close="$emit('close')"
    >
        <div v-if="memuat" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else-if="dampak">
            <p v-if="!dampak.rincian.length" class="tp-hint mb-0">
                Belum ada paket yang memakai dokumen ini. Versi ini akan berlaku, tapi belum ada karyawan yang menerimanya.
            </p>

            <table v-else class="tp-tabel">
                <thead>
                    <tr>
                        <th>Paket</th>
                        <th>Versi</th>
                        <th class="tnum">Karyawan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in dampak.rincian" :key="i">
                        <td class="text-truncate">{{ p.Nama_Paket }}</td>
                        <td class="tnum tp-naik">
                            v{{ p.Versi_Sekarang ?? '—' }} <i class="bi bi-arrow-right"></i> v{{ p.Versi_Baru }}
                        </td>
                        <td class="tnum">{{ p.Jumlah_Karyawan }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- SIFAT REVISI. Ditanyakan DI SINI, bukan saat versinya dibuat:
                 admin baru bisa menimbang "perlu dibaca ulang atau tidak"
                 setelah melihat berapa orang terdampak. -->
            <!-- Pilihan pertama menyebut "/ baru" karena penerbitan PERTAMA
                 sebuah dokumen juga lewat sini. Bila hanya tertulis "revisi",
                 admin yang menerbitkan dokumen baru mencari pilihan yang tak
                 ada — tak ada yang ia revisi. -->
            <!-- Sifat revisi tak ditanyakan saat mengembalikan: rollback SELALU
                 membuka ulang kewajiban (keputusan produk 2026-08-09), dan
                 menawarkan "minor" di sini akan menjanjikan sesuatu yang tak
                 dilakukan jalurnya. -->
            <div v-if="!rollback" class="tp-sifat">
                <label class="tp-opsi" :class="{ on: sifat === 'Y' }">
                    <input v-model="sifat" type="radio" value="Y" />
                    <span>
                        <b>Revisi mayor / baru</b>
                        <em>
                            Isinya berubah, atau dokumen ini baru pertama kali terbit — semua wajib
                            membaca ulang.
                        </em>
                    </span>
                </label>
                <label class="tp-opsi" :class="{ on: sifat === 'T' }">
                    <input v-model="sifat" type="radio" value="T" />
                    <span>
                        <b>Revisi minor</b>
                        <em>Perbaikan ketik atau format — yang sudah selesai tetap terhitung selesai.</em>
                    </span>
                </label>
            </div>

            <p v-if="rollback" class="tp-awas">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Karyawan yang sudah selesai akan diminta membaca ulang di semua paket di atas.
            </p>
        </div>

        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    :disabled="mengirim"
                    @click="$emit('close')"
                />
                <HcisButton
                    :variant="rollback ? 'danger' : 'primary'"
                    :leading-icon="rollback ? 'arrow-counterclockwise' : 'broadcast'"
                    :loading="mengirim"
                    :disabled="mengirim || memuat || !dampak"
                    :label="
                        mengirim
                            ? rollback
                                ? 'Mengembalikan…'
                                : 'Menerbitkan…'
                            : rollback
                              ? 'Ya, Kembalikan Versi'
                              : 'Ya, Terbitkan Versi'
                    "
                    @click="terbitkan"
                />
            </div>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import SopModal from './SopModal.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import { hcisMessage } from '@/components/ui';

const API = '/api/v1/sop/admin/dokumen';

export default {
    name: 'SopTerbitPratinjau',
    components: { SopModal, HcisButton, HcisBadge },
    props: {
        /** Baris versi yang hendak diterbitkan; null = tertutup. */
        versi: { type: Object, default: null },
        /* "terbit" memajukan revisi, "kembali" memundurkannya. Satu
           komponen karena keduanya menampilkan dampak yang sama persis;
           yang berbeda hanya arah dan kalimatnya. */
        mode: { type: String, default: 'terbit' },
    },
    emits: ['close', 'berhasil'],
    computed: {
        rollback() {
            return this.mode === 'kembali';
        },
        judul() {
            if (!this.versi) return '';
            const v = this.$vlabel(this.versi.Versi_Ke);

            return this.rollback ? `Kembalikan ke ${v}?` : `Terbitkan ${v}?`;
        },
        /* Versi 00 adalah penerbitan PERTAMA dokumen — nomorVersiBerikut()
           memulai dari 0 bila dokumennya belum punya versi apa pun. Nomor
           manual boleh melompat, jadi penanda ini sengaja hanya mengenali
           jalur otomatis dan jatuh kembali ke kalimat "revisi" bila ragu:
           menyebut sebuah revisi sebagai "dokumen baru" lebih menyesatkan
           daripada menyebut dokumen baru sebagai revisi. */
        terbitPertama() {
            if (this.rollback) return false;
            const n = this.dampak?.Versi_Ke ?? this.versi?.Versi_Ke;

            return Number(n) === 0;
        },
        subjudul() {
            if (this.rollback) return 'Versi lama diberlakukan lagi di semua paket pemakainya';

            return this.terbitPertama
                ? 'Penerbitan pertama — dokumen mulai berlaku di semua paket pemakainya'
                : 'Revisi berlaku serentak di semua paket pemakainya';
        },
    },
    data() {
        return {
            dampak: null,
            memuat: false,
            mengirim: false,
            /* Default MAYOR, selalu. Admin yang tak menyentuh pilihan ini tak
               boleh diam-diam menghasilkan "tak usah baca". */
            sifat: 'Y',
        };
    },
    watch: {
        versi: {
            immediate: true,
            handler(v) {
                this.dampak = null;
                this.sifat = 'Y'; // tiap pratinjau mulai dari yang aman
                if (v) this.muat();
            },
        },
    },
    methods: {
        async muat() {
            this.memuat = true;
            try {
                const r = await axios.get(`${API}/versi/${this.versi.Id_SOP_Item_Versi}/dampak`);
                this.dampak = r.data?.result || {};
                this.dampak.rincian = this.dampak.rincian || [];
            } catch (e) {
                /* Gagal menghitung dampak TIDAK boleh jatuh ke penerbitan
                   langsung. Menerbitkan tanpa memperlihatkan akibatnya persis
                   keadaan yang konfirmasi ini ada untuk mencegah — jadi
                   modalnya ditutup, bukan dilanjutkan dengan tombol aktif. */
                hcisMessage.error(e?.response?.data?.message || 'Gagal menghitung dampak penerbitan.');
                this.$emit('close');
            } finally {
                this.memuat = false;
            }
        },
        async terbitkan() {
            this.mengirim = true;
            try {
                /* Rute BERBEDA, bukan satu rute dengan bendera: jejak auditnya
                   harus menyebut pengembalian sebagai pengembalian, bukan
                   sebagai penerbitan versi baru. */
                if (this.rollback) {
                    await axios.post(`${API}/versi/${this.versi.Id_SOP_Item_Versi}/kembalikan`);
                    hcisMessage.success('Versi lama diberlakukan kembali di semua paket pemakai.');
                    this.$emit('berhasil');
                    this.$emit('close');

                    return;
                }

                await axios.post(`${API}/versi/${this.versi.Id_SOP_Item_Versi}/terbitkan`, {
                    Wajib_Baca_Ulang: this.sifat,
                });
                hcisMessage.success(
                    this.sifat === 'T'
                        ? 'Revisi minor diterbitkan — karyawan yang sudah selesai tetap terhitung selesai.'
                        : 'Versi diterbitkan dan disebarkan ke semua paket pemakai.',
                );
                this.$emit('berhasil');
                this.$emit('close');
            } catch (e) {
                hcisMessage.error(
                    e?.response?.data?.message ||
                        (this.rollback ? 'Gagal mengembalikan versi.' : 'Gagal menerbitkan versi.'),
                );
            } finally {
                this.mengirim = false;
            }
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.tp-hint {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
}
.tp-tabel {
    width: 100%;
    font-size: 0.82rem;
    border-collapse: collapse;
}
.tp-tabel th {
    text-align: left;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400);
    padding-bottom: 0.4rem;
    border-bottom: 1px solid var(--bs-border-color, #e5e7eb);
}
.tp-tabel td {
    padding: 0.45rem 0.5rem 0.45rem 0;
    color: var(--hcis-slate-900);
    max-width: 0;
    border-bottom: 1px solid var(--hcis-slate-100);
}
.tp-tabel tr:last-child td {
    border-bottom: 0;
}
.tp-tabel th:last-child,
.tp-tabel td:last-child {
    text-align: right;
    padding-right: 0;
}
/* Panah versi lama -> baru: inti dari seluruh pratinjau ini, jadi ia yang
   paling terbaca di barisnya. */
.tp-naik {
    font-weight: 700;
    white-space: nowrap;
    color: var(--hcis-emerald-700);
}
.tp-awas {
    display: flex;
    align-items: flex-start;
    margin: 0.8rem 0 0;
    font-size: 0.75rem;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-300);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.5rem 0.6rem;
}

.tp-awas i {
    margin-right: 0.45rem;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.tp-sifat {
    display: flex;
    flex-direction: column;
    margin-top: 0.9rem;
}

.tp-opsi {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.6rem 0.75rem;
    margin-bottom: 0.5rem;
    transition: all 0.15s ease;
}

.tp-opsi:last-child {
    margin-bottom: 0;
}

.tp-opsi.on {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: #eff6ff;
    box-shadow: 0 0 0 1px var(--hcis-brand-600, var(--hcis-indigo-600));
}

.tp-opsi input {
    margin-top: 0.2rem;
    margin-right: 0.65rem;
    flex-shrink: 0;
}

.tp-opsi b {
    display: block;
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
}

.tp-opsi em {
    display: block;
    font-style: normal;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    line-height: 1.35;
}
</style>
