<template>
    <div class="plt">
        <!-- Empat angka yang semuanya bermakna "ada pekerjaan di sini".
             Dikumpulkan jadi satu panel karena keempatnya dijawab tindakan yang
             sama: seseorang harus melengkapi sesuatu. -->
        <div class="plt-grid">
            <div v-for="k in kartu" :key="k.kunci" class="plt-kartu" :class="{ 'is-nol': !k.nilai }">
                <span class="plt-angka">{{ k.nilai }}</span>
                <span class="plt-judul">{{ k.judul }}</span>
                <span class="plt-ket">{{ k.ket }}</span>
            </div>
        </div>

        <!-- Versi yang berhenti sebelum terbit, per tahap. Umur tertua ikut
             disebut: dua draft yang berhenti kemarin dan dua yang berhenti tiga
             bulan lalu adalah dua keadaan yang sama sekali berbeda. -->
        <div v-if="menggantung.Total" class="plt-tahap">
            <div class="plt-tahap-judul">
                <i class="bi bi-hourglass-split"></i>
                {{ menggantung.Total }} versi berhenti sebelum terbit
            </div>
            <div class="plt-tahap-baris">
                <span
                    v-for="t in menggantung.Baris"
                    :key="t.Kode_Tahap"
                    class="plt-pil"
                    :style="{ '--tc': t.Warna || 'var(--hcis-text-muted)' }"
                >
                    <strong>{{ t.Jumlah }}</strong> {{ t.Nama_Tahap }}
                    <em v-if="t.Umur_Hari !== null">· tertua {{ t.Umur_Hari }} hari</em>
                </span>
            </div>
        </div>

        <!-- Gerbang paket publik. Angka ini ada supaya penyaringnya berhenti
             bekerja diam-diam: selama tak pernah disebut, penugasan pada paket
             publik yang belum dibuka lenyap dari seluruh laporan tanpa satu pun
             tanda, dan seseorang suatu hari menyimpulkan penugasannya tak
             pernah dibuat. -->
        <HcisAlert
            v-if="tersaring.Penugasan"
            variant="warning"
            appearance="subtle"
            class="plt-gerbang"
            :description="`${tersaring.Penugasan} penugasan pada ${tersaring.Paket} paket publik belum dihitung di mana pun — `
                + 'penugasan paket publik baru masuk hitungan setelah karyawannya pernah membukanya.'"
        />
    </div>
</template>

<script>
import HcisAlert from '@/components/ui/HcisAlert.vue';

/**
 * Lubang tata kelola — hal-hal yang menuntut tindakan, dikumpulkan.
 *
 * Panel ini sengaja tak menampilkan apa pun yang "baik-baik saja". Dashboard
 * yang penuh angka hijau mengajari orang berhenti membacanya.
 */
export default {
    name: 'PanelLubangTataKelola',
    components: { HcisAlert },
    props: {
        komposisi: { type: Object, default: () => ({ Baris: [] }) },
        apqc: { type: Object, default: () => ({}) },
        kepemilikan: { type: Object, default: () => ({}) },
        menggantung: { type: Object, default: () => ({ Total: 0, Baris: [] }) },
        tersaring: { type: Object, default: () => ({ Penugasan: 0, Paket: 0 }) },
    },
    computed: {
        belumBeredar() {
            return this.komposisi?.Baris?.find((b) => b.Kelompok === 'BELUM_BEREDAR')?.Jumlah || 0;
        },
        kartu() {
            return [
                {
                    kunci: 'beredar',
                    nilai: this.belumBeredar,
                    judul: 'Belum beredar',
                    ket: 'Sah di master, tak masuk paket mana pun',
                },
                {
                    kunci: 'apqc',
                    nilai: this.apqc?.Proses_Kosong ?? 0,
                    judul: `Proses tanpa dokumen`,
                    ket: `dari ${this.apqc?.Total_Proses ?? 0} proses APQC`,
                },
                {
                    kunci: 'pemilik',
                    nilai: this.kepemilikan?.Tanpa_Pemilik ?? 0,
                    judul: 'Tanpa pemilik unit',
                    ket: 'Tak ada yang bertanggung jawab merevisinya',
                },
                {
                    kunci: 'jenis',
                    nilai: this.apqc?.Tanpa_Proses ?? 0,
                    judul: 'Tanpa proses APQC',
                    ket: 'Belum dipetakan ke proses bisnis mana pun',
                },
            ];
        },
    },
};
</script>

<style scoped>
.plt {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.plt-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.625rem;
}

.plt-kartu {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.75rem 0.875rem;
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface);
}

/* Nol BUKAN abu-abu pucat sampai tak terbaca: "nol lubang" adalah kabar baik
   yang layak terlihat, bukan baris yang seolah gagal dimuat. */
.plt-kartu.is-nol .plt-angka { color: var(--hcis-success-500); }

.plt-angka {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--hcis-amber-600);
}

.plt-judul {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.plt-ket {
    font-size: 0.7rem;
    line-height: 1.35;
    color: var(--hcis-text-muted);
}

.plt-tahap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 0.875rem;
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface-soft);
}

.plt-tahap-judul {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.plt-tahap-baris {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.plt-pil {
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border: 1px solid var(--tc);
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-surface);
    color: var(--tc);
    font-size: 0.72rem;
}

.plt-pil em {
    font-style: normal;
    color: var(--hcis-text-muted);
}

.plt-gerbang { margin: 0; }
</style>
