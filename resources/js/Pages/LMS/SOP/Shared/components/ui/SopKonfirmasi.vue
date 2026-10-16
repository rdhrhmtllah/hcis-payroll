<template>
    <SopModal :show="show" :title="judul" :subtitle="subjudul" :icon="ikon" size="sm" @close="tutup">
        <div class="kf-isi">
            <p class="kf-pesan">{{ pesan }}</p>

            <!-- Angka dampak berdiri sendiri, tidak dilebur ke kalimat.
                 "2 paket, 2 item, 5 penugasan" di tengah paragraf terbaca
                 sebagai ekor kalimat dan terlewat; yang justru harus dilihat
                 sebelum menekan adalah angkanya. -->
            <!-- Angkanya diambil SESUDAH modal muncul, bukan sebelumnya.
                 Menunggu di belakang tombol membuat klik terasa tak terdaftar;
                 menunggu di dalam modal terlihat sebagai apa adanya — sedang
                 dihitung. -->
            <div v-if="memuatStatistik" class="kf-stat kf-stat-muat">
                <span class="sop-skel kf-skel"></span>
                <span class="sop-skel kf-skel"></span>
            </div>

            <ul v-else-if="statistik.length" class="kf-stat">
                <li v-for="s in statistik" :key="s.label">
                    <span class="kf-stat-n tnum">{{ s.nilai }}</span>
                    <span class="kf-stat-l">{{ s.label }}</span>
                </li>
            </ul>

            <p v-if="catatan" class="kf-catat"><i class="bi bi-info-circle-fill"></i> {{ catatan }}</p>
        </div>

        <template #footer>
            <!-- Pemberitahuan (mis. "data ini baru saja diubah admin lain")
                 tak punya pilihan untuk dibatalkan: tombol Batal di sana
                 menjanjikan jalan keluar yang tak ada. -->
            <button v-if="!tanpaBatal" class="stitch-btn secondary" :disabled="sibuk" @click="tutup">
                {{ teksBatal }}
            </button>
            <!-- Terkunci selama angkanya dihitung: seluruh guna modal ini
                 adalah memperlihatkan dampaknya sebelum diputuskan, dan tombol
                 yang bisa ditekan lebih dulu meniadakan gunanya. -->
            <button class="stitch-btn" :class="ragamTombol" :disabled="sibuk || memuatStatistik" @click="setuju">
                <span v-if="sibuk" class="spinner-border spinner-border-sm"></span>
                <i v-else-if="ikonTombol" class="bi" :class="ikonTombol"></i>
                {{ sibuk ? teksSibuk || 'Memproses…' : teksSetuju }}
            </button>
        </template>
    </SopModal>
</template>

<script>
import SopModal from './SopModal.vue';

/**
 * SopKonfirmasi — kotak konfirmasi modul DMS, di atas SopModal.
 *
 * Menggantikan ElMessageBox pada keputusan yang akibatnya nyata. Alasannya
 * bukan selera: kotak Element Plus punya bahasa visualnya sendiri (sudut,
 * bayangan, jarak, tombol) yang tak sama dengan modal modul ini, dan ia muncul
 * tepat pada tindakan yang paling perlu terasa serius. Layar yang berganti
 * dialek di detik terakhir membuat orang ragu apakah ia masih di aplikasi yang
 * sama.
 *
 * Isinya juga tak lagi satu paragraf: angka dampak dipisah jadi daftar
 * tersendiri supaya benar-benar terbaca sebelum tombolnya ditekan.
 *
 * Dipakai lewat v-model:show, dan `sibuk` dikendalikan pemanggil supaya
 * tombolnya tetap berputar selama permintaannya berjalan.
 */
export default {
    name: 'SopKonfirmasi',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        judul: { type: String, default: 'Konfirmasi' },
        subjudul: { type: String, default: '' },
        pesan: { type: String, default: '' },
        /** [{ nilai, label }] — angka dampak, ditampilkan menonjol. */
        statistik: { type: Array, default: () => [] },
        catatan: { type: String, default: '' },
        teksSetuju: { type: String, default: 'Ya' },
        teksSibuk: { type: String, default: '' },
        sibuk: { type: Boolean, default: false },
        /** 'primary' | 'danger' — menyetir warna tombol DAN ikon judulnya. */
        ragam: { type: String, default: 'primary' },
        ikonTombol: { type: String, default: '' },
        teksBatal: { type: String, default: 'Batal' },
        /** Pemberitahuan satu tombol — tanpa pilihan membatalkan. */
        tanpaBatal: { type: Boolean, default: false },
        /** Angka dampak sedang dihitung — modal sudah tampil, isinya menyusul. */
        memuatStatistik: { type: Boolean, default: false },
    },
    emits: ['update:show', 'setuju'],
    computed: {
        ragamTombol() {
            return this.ragam === 'danger' ? 'danger' : 'primary';
        },
        ikon() {
            return this.ragam === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-patch-question-fill';
        },
    },
    methods: {
        tutup() {
            // Ditutup selagi bekerja akan meninggalkan permintaan tanpa layar
            // yang menampung hasilnya — termasuk galatnya.
            if (this.sibuk) return;
            this.$emit('update:show', false);
        },
        setuju() {
            this.$emit('setuju');
        },
    },
};
</script>

<style scoped>
.kf-isi {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.kf-pesan {
    margin: 0;
    font-size: 0.86rem;
    line-height: 1.6;
    color: var(--hcis-slate-700);
}

.kf-stat {
    list-style: none;
    margin: 0;
    padding: 0.6rem 0.7rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--border-soft, #e7ecf3);
    border-radius: 10px;
}
.kf-stat li {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
}
/* Tingginya menyamai kotak angka yang akan menggantikannya, jadi isinya tak
   melompat saat perhitungannya tiba. */
.kf-stat-muat {
    display: flex;
    gap: 1.25rem;
    align-items: center;
}
.kf-skel {
    display: block;
    height: 1.05rem;
    width: 7rem;
    border-radius: 6px;
}
.kf-stat-n {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1;
}
.kf-stat-l {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}

.kf-catat {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
}
.kf-catat i {
    margin-top: 0.1rem;
    flex: none;
}
</style>
