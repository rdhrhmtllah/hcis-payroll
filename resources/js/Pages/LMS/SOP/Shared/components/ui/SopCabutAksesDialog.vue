<template>
    <SopModal :show="show" title="Hapus Akses Dokumen" :subtitle="subjudul" icon="bi-shield-slash" size="md" @close="tutup">
        <p class="cad-akibat">
            Penugasan ini <strong>dikeluarkan dari perhitungan kepatuhan</strong> — bukan dianggap selesai, melainkan
            tidak lagi diwajibkan. Token kuisnya ikut dimatikan, dan tindakan ini tercatat di riwayat kepatuhan
            karyawan.
        </p>

        <label class="sop-label mt-3">Sebab penghapusan akses <span class="cad-wajib">wajib</span></label>
        <div class="cad-list">
            <button
                v-for="(teks, kode) in SEBAB_CABUT"
                :key="kode"
                type="button"
                class="cad-opt"
                :class="{ on: sebab === kode }"
                @click="pilih(kode)"
            >
                <span class="cad-radio"><span v-if="sebab === kode" class="cad-dot"></span></span>
                <span>{{ teks }}</span>
            </button>
        </div>

        <label class="sop-label mt-3">
            Catatan
            <span v-if="catatanWajib" class="cad-wajib">wajib untuk sebab ini</span>
            <span v-else class="cad-opsional">opsional</span>
        </label>
        <textarea
            v-model="catatan"
            class="form-control cad-catatan"
            rows="3"
            maxlength="500"
            :placeholder="contohCatatan"
        ></textarea>
        <div class="cad-hitung">{{ catatan.length }}/500</div>

        <p v-if="galat" class="cad-galat"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ galat }}</p>

        <template #footer>
            <button type="button" class="stitch-btn" :disabled="sibuk" @click="tutup">Batal</button>
            <button type="button" class="stitch-btn is-danger" :disabled="sibuk" @click="kirim">
                <span v-if="sibuk" class="spinner-border spinner-border-sm me-1"></span>
                Ya, Hapus Akses
            </button>
        </template>
    </SopModal>
</template>

<script>
import SopModal from './SopModal.vue';
import { SEBAB_CABUT, SEBAB_WAJIB_CATATAN } from '../../support/constants.js';

/**
 * Dialog pencabutan akses dokumen — satu bentuk untuk SEMUA pintu pencabutan.
 *
 * Modul ini punya riwayat panjang aturan yang hanya terpasang di satu dari dua
 * jalur setara. Pencabutan punya dua pintu (Monitoring per orang, dan Batch
 * secara massal); kalau masing-masing punya dialognya sendiri, cepat atau lambat
 * salah satunya lupa menanyakan sebab — dan barisnya masuk ledger tanpa
 * penjelasan, persis keadaan yang membuat komponen ini dibuat.
 *
 * Penjaga "sebab wajib" tetap dipasang ULANG di server. Yang di sini hanya
 * supaya admin tak perlu menunggu permintaan gagal untuk tahu.
 */
export default {
    name: 'SopCabutAksesDialog',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        /** Kalimat penjelas siapa/apa yang dicabut, dirakit pemanggil. */
        subjudul: { type: String, default: '' },
        sibuk: { type: Boolean, default: false },
    },
    emits: ['close', 'konfirmasi'],
    data() {
        return { SEBAB_CABUT, sebab: '', catatan: '', galat: '' };
    },
    computed: {
        catatanWajib() {
            return SEBAB_WAJIB_CATATAN.includes(this.sebab);
        },
        contohCatatan() {
            return this.catatanWajib
                ? 'Jelaskan sebabnya — ini yang akan dibaca orang lain saat mengaudit.'
                : 'Mis. nomor tiket, nama pemberi instruksi, atau konteks lain.';
        },
    },
    watch: {
        show(v) {
            // Direset saat DIBUKA, bukan saat ditutup: dialog yang ditutup lalu
            // dibuka lagi untuk orang lain tak boleh membawa sebab orang sebelumnya.
            if (v) {
                this.sebab = '';
                this.catatan = '';
                this.galat = '';
            }
        },
    },
    methods: {
        pilih(kode) {
            this.sebab = kode;
            this.galat = '';
        },
        tutup() {
            if (!this.sibuk) this.$emit('close');
        },
        kirim() {
            if (!this.sebab) {
                this.galat = 'Pilih dulu sebab pencabutannya.';

                return;
            }
            if (this.catatanWajib && !this.catatan.trim()) {
                this.galat = 'Sebab "Lainnya" harus dijelaskan di catatan.';

                return;
            }
            this.$emit('konfirmasi', { sebab: this.sebab, catatan: this.catatan.trim() || null });
        },
    },
};
</script>

<style scoped>
.cad-akibat {
    font-size: 0.82rem;
    line-height: 1.55;
    color: var(--hcis-slate-500);
    background: rgba(244, 63, 94, 0.06);
    border: 1px solid rgba(244, 63, 94, 0.16);
    border-radius: 12px;
    padding: 0.7rem 0.8rem;
    margin: 0;
}
.cad-wajib {
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--hcis-red-700);
    background: rgba(185, 28, 28, 0.08);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    margin-left: 0.35rem;
}
.cad-opsional {
    font-size: 0.66rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
    margin-left: 0.35rem;
}
.cad-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.cad-opt {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    text-align: left;
    font-size: 0.82rem;
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    border: 1px solid var(--border-soft, #eef2f7);
    background: #fff;
    color: var(--hcis-slate-700);
    transition:
        border-color 0.15s,
        background 0.15s;
}
.cad-opt:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}
.cad-opt.on {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.06);
    font-weight: 600;
}
.cad-radio {
    flex: 0 0 auto;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 1.5px solid var(--hcis-slate-300);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cad-opt.on .cad-radio {
    border-color: var(--hcis-indigo-500);
}
.cad-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--hcis-indigo-500);
}
.cad-catatan {
    font-size: 0.82rem;
    border-radius: 10px;
}
.cad-hitung {
    text-align: right;
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    margin-top: 0.2rem;
}
.cad-galat {
    margin: 0.6rem 0 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-red-700);
}
</style>
