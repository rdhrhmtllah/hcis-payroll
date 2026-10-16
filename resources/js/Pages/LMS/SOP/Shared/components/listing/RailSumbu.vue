<template>
    <nav class="rl" :aria-label="`${labelSemua} dikelompokkan per ${judul}`">
        <div class="rl-judul">
            <span>{{ judul }}</span>

            <!--
                SELISIH TUGAS vs PENEMPATAN DISEBUT, BUKAN DISEMBUNYIKAN.

                Pada sumbu bernilai jamak (Departemen, Jenis Dokumen) satu paket
                muncul di beberapa kelompok sekaligus, sehingga angka-angka di
                bawah TIDAK menjumlah ke jumlah tugas. Itu benar dan disengaja —
                tapi pembaca yang menjumlahkannya sendiri lalu menemukan selisih
                akan menyimpulkan salah satunya salah. Menyebutkannya di sini
                jauh lebih murah daripada menjelaskannya belakangan.
            -->
            <span v-if="jamak && penempatan !== totalSemua" class="rl-selisih tnum">
                {{ totalSemua }} tugas · {{ penempatan }} penempatan
            </span>
        </div>

        <template v-if="loading">
            <div class="rl-item pe-none" style="opacity: 0.85;">
                <span class="rl-tanda semua"><i class="bi bi-grid-1x2-fill opacity-25"></i></span>
                <span class="rl-teks">
                    <HcisSkeleton width="65%" height="0.85rem" rounded="xs" />
                </span>
                <HcisSkeleton width="22px" height="14px" rounded="xs" />
            </div>
            <div class="rl-pisah"></div>
            <div v-for="n in 5" :key="n" class="rl-item pe-none" style="opacity: 0.85;">
                <span class="rl-tanda" style="background: rgba(148, 163, 184, 0.15);">
                    <i class="bi bi-circle-fill opacity-10"></i>
                </span>
                <span class="rl-teks">
                    <HcisSkeleton :width="`${50 + (n * 9)}%`" height="0.85rem" rounded="xs" />
                </span>
                <HcisSkeleton width="20px" height="14px" rounded="xs" />
            </div>
        </template>

        <template v-else>
            <button class="rl-item" :class="{ on: !aktif }" @click="$emit('pilih', '')">
                <span class="rl-tanda semua"><i class="bi bi-grid-1x2-fill"></i></span>
                <span class="rl-teks">
                    <span class="rl-nama">{{ labelSemua }}</span>
                    <span v-if="bar" class="rl-bar"><span :style="{ width: persenSemua + '%' }"></span></span>
                </span>
                <span class="rl-n tnum">{{ totalSemua }}</span>
            </button>

            <div class="rl-pisah"></div>

            <button
                v-for="g in grup"
                :key="kunci(g)"
                class="rl-item"
                :class="{ on: aktif === kunci(g) }"
                :style="{ '--kc': g.warna }"
                @click="$emit('pilih', kunci(g))"
            >
                <span class="rl-tanda" :class="nada(g)">
                    <i class="bi" :class="ikon(g)"></i>
                </span>
                <span class="rl-teks">
                    <span class="rl-nama">{{ g.nama }}</span>
                    <!-- Bar setipis 3px: cukup untuk membandingkan bidang sekilas,
                         tak cukup untuk bersaing dengan isi panel di sebelahnya. -->
                    <span v-if="bar" class="rl-bar"><span :class="nada(g)" :style="{ width: g.persen + '%' }"></span></span>
                </span>
                <span class="rl-n tnum">{{ g.total }}</span>
            </button>
        </template>
    </nav>
</template>

<script>
/**
 * Rail pengelompokan (master dari master-detail).
 *
 * Menggantikan tumpukan accordion. Dengan sebaran nyata — sering hanya 1-2 dokumen
 * per kelompok — accordion membuat bingkai jadi lebih berat daripada isinya:
 * lima kotak, lima kepala penuh atribut, masing-masing memuat satu kartu.
 * Rail memindahkan seluruh beban navigasi ke satu kolom sempit dan menyisakan
 * panel kanan murni untuk isi.
 *
 * Rail ini juga menggantikan toggle "Per Kategori / Fokus": entri "Semua tugas"
 * SUDAH merupakan mode fokus (daftar datar terurut urgensi), jadi dua kontrol
 * yang mengerjakan hal sama dilebur jadi satu.
 *
 * DULU BERNAMA RailKategori DAN HANYA TAHU KATEGORI. Sejak kepemilikan dokumen
 * (departemen, jenis, proses) bisa dipakai mengelompokkan, komponen ini tak
 * lagi tahu sumbu apa yang sedang ditampilkan — dan memang tak perlu tahu.
 * Yang diterimanya sudah berupa kelompok jadi; satu-satunya hal baru yang harus
 * dipahaminya adalah bahwa kelompok BOLEH tumpang tindih (`jamak`), sehingga
 * angkanya tak selalu menjumlah ke jumlah tugas.
 */
import { HcisSkeleton } from '@/components/ui';

export default {
    name: 'RailSumbu',
    components: {
        HcisSkeleton,
    },
    props: {
        loading: { type: Boolean, default: false },
        grup: { type: Array, default: () => [] },
        aktif: { type: String, default: '' },
        totalSemua: { type: Number, default: 0 },
        persenSemua: { type: Number, default: 0 },
        /** Nama sumbu yang sedang dipakai; tampil sebagai kepala rail. */
        judul: { type: String, default: 'Kategori' },
        /** Sumbu ini boleh menempatkan satu tugas di lebih dari satu kelompok. */
        jamak: { type: Boolean, default: false },
        /** Jumlah penempatan; berbeda dari totalSemua hanya pada sumbu jamak. */
        penempatan: { type: Number, default: 0 },
        /**
         * Tampilkan bar kemajuan di tiap entri.
         *
         * Dimatikan oleh pemanggil yang isinya tak punya dimensi kemajuan sama
         * sekali — Perpustakaan berisi RUJUKAN, bukan tugas, dan bar yang
         * selamanya 0% di sana bukan sekadar hiasan kosong: ia membacakan
         * "belum dikerjakan" pada sesuatu yang memang tak untuk dikerjakan.
         */
        bar: { type: Boolean, default: true },
        /** Label entri pertama; "tugas" tak berlaku di luar Functional Document. */
        labelSemua: { type: String, default: 'Semua tugas' },
    },
    emits: ['pilih'],
    methods: {
        /**
         * Identitas kelompok yang dipakai untuk menyorot dan untuk dipancarkan.
         *
         * Bentuk lama memakai NAMA untuk keduanya, dan itu cukup selama
         * pemanggilnya menyaring berdasarkan nama juga. SOP Builder menyaring
         * berdasarkan id (kategori) dan kode (status, prioritas, distribusi),
         * jadi kelompok boleh membawa `nilai` sendiri. Tanpa `nilai` perilakunya
         * persis seperti sebelumnya — SopSaya tidak perlu diubah.
         */
        kunci(g) {
            return g.nilai ?? g.nama;
        },
        nada(g) {
            if (g.persen >= 100) return 'ok';

            return g.terlambat > 0 ? 'bad' : '';
        },
        ikon(g) {
            if (g.persen >= 100) return 'bi-check-lg';

            return g.terlambat > 0 ? 'bi-exclamation-triangle-fill' : g.ikon;
        },
    },
};
</script>

<style scoped>
.rl {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.rl-judul {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
    padding: 0 0.5rem 0.4rem;
}
/* Keterangan selisih sengaja TIDAK uppercase dan tidak ber-letter-spacing:
   ia kalimat yang dibaca, bukan label kolom yang dipindai. */
.rl-selisih {
    font-size: 0.66rem;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
    color: var(--hcis-slate-500);
}
.rl-pisah {
    height: 1px;
    background: var(--border, #dbe1ea);
    margin: 0.35rem 0.5rem;
}

.rl-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    text-align: left;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    border-radius: 10px;
    padding: 0.42rem 0.5rem;
    transition:
        background 0.14s ease,
        border-color 0.14s ease;
}
.rl-item:hover {
    background: var(--hcis-slate-100);
}
.rl-item.on {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.28);
}

.rl-tanda {
    width: 24px;
    height: 24px;
    border-radius: 7px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 0.72rem;
    color: var(--kc, var(--hcis-slate-500));
    background: color-mix(in srgb, var(--kc, var(--hcis-slate-400)) 14%, transparent);
}
.rl-tanda.semua {
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.12);
}
.rl-tanda.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
}
.rl-tanda.bad {
    color: var(--hcis-red-700);
    background: rgba(220, 38, 38, 0.13);
}

.rl-teks {
    min-width: 0;
    flex: 1;
}
.rl-nama {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rl-item.on .rl-nama {
    color: var(--hcis-indigo-800);
    font-weight: 800;
}
.rl-bar {
    display: block;
    height: 3px;
    border-radius: 999px;
    background: #eef2f7;
    overflow: hidden;
    margin-top: 0.28rem;
}
.rl-bar span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--kc, var(--hcis-indigo-500));
    transition: width 0.35s ease;
}
.rl-bar span.ok {
    background: var(--hcis-emerald-500);
}
.rl-bar span.bad {
    background: var(--hcis-amber-500);
}

.rl-n {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
}
.rl-item.on .rl-n {
    color: var(--hcis-indigo-600);
}

/* Layar sempit: rail jadi deretan chip yang MEMBUNGKUS, bukan menggulir
   mendatar — isi yang tersembunyi di luar layar kanan tak pernah ditemukan.
   Ambangnya HARUS sama dengan .ly di SopSaya (991.98px); kalau berbeda, ada
   rentang lebar di mana kolom sudah menumpuk tapi rail masih berbentuk daftar
   vertikal setinggi layar. */
@media (max-width: 991.98px) {
    .rl {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .rl-judul,
    .rl-pisah {
        display: none;
    }
    .rl-item {
        width: auto;
        border-color: var(--border, #dbe1ea);
        border-radius: 999px;
        padding: 0.3rem 0.6rem;
    }
    .rl-bar {
        display: none;
    }
    .rl-nama {
        font-size: 0.74rem;
    }
}
</style>
