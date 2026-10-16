<template>
    <SopModal
        :show="show"
        title="Export Laporan Ringkasan Dokumen"
        subtitle="Unduh laporan eksekutif berstandar presentasi & rekap data sesuai filter periode aktif."
        icon="bi-download"
        size="lg"
        @close="$emit('close')"
    >
        <div class="rk-export-wrap">
            <!-- Informasi Filter Periode Aktif -->
            <div class="rk-export-banner">
                <div class="d-flex align-items-center gap-2">
                    <HcisIcon name="calendar-range" size="sm" class="text-primary" />
                    <div>
                        <div class="rk-export-banner__title">Cakupan Saringan Periode:</div>
                        <div class="rk-export-banner__desc">
                            <strong>{{ labelRentang || 'Semua Waktu (Seluruh Data Riwayat)' }}</strong>
                        </div>
                    </div>
                </div>
                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill">
                    Khusus Tab Ringkasan
                </span>
            </div>

            <!-- Loading State -->
            <div v-if="memuat" class="rk-export-loading">
                <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                <div class="text-muted fw-semibold">Menyiapkan seluruh data bab analitik & matriks ringkasan…</div>
            </div>

            <template v-else>
                <!-- Opsi Format Export -->
                <div class="row g-3 mb-4">
                    <!-- Opsi 1: PDF / Cetak Laporan Monitoring DMS -->
                    <div class="col-12 col-md-6">
                        <div
                            class="rk-export-card"
                            :class="{ 'rk-export-card--active': formatExport === 'pdf' }"
                            @click="formatExport = 'pdf'"
                        >
                            <div class="rk-export-card__icon text-danger">
                                <HcisIcon name="file-earmark-pdf-fill" size="md" />
                            </div>
                            <div class="rk-export-card__body">
                                <h6 class="rk-export-card__title">Laporan Monitoring DMS PDF (Multi-Page / Per Bab)</h6>
                                <p class="rk-export-card__desc">
                                    Cetakan lima bab, mengikuti persis urutan kartu di tab Ringkasan: Keadaan Umum, Kesehatan Dokumen, Titik Macet Unit, Kualitas Penyerapan, dan Jangkauan Publik.
                                </p>
                            </div>
                            <div class="rk-export-card__check">
                                <i class="bi" :class="formatExport === 'pdf' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-muted'"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Opsi 2: Spreadsheet Excel / CSV -->
                    <div class="col-12 col-md-6">
                        <div
                            class="rk-export-card"
                            :class="{ 'rk-export-card--active': formatExport === 'csv' }"
                            @click="formatExport = 'csv'"
                        >
                            <div class="rk-export-card__icon text-success">
                                <HcisIcon name="file-earmark-spreadsheet-fill" size="md" />
                            </div>
                            <div class="rk-export-card__body">
                                <h6 class="rk-export-card__title">Rekap Spreadsheet (CSV / Excel)</h6>
                                <p class="rk-export-card__desc">
                                    File spreadsheet terstruktur memuat metadata, KPI utama, tabel peringkat kepatuhan unit, rekap aliran mingguan, dan sebaran kualitas.
                                </p>
                            </div>
                            <div class="rk-export-card__check">
                                <i class="bi" :class="formatExport === 'csv' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-muted'"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Ringkasan Data -->
                <div class="rk-export-preview">
                    <h6 class="rk-export-preview__title">
                        <HcisIcon name="eye" size="xs" /> Ringkasan Data yang Akan Diekspor
                    </h6>
                    <div class="row g-2">
                        <!-- Pratinjau ini menyebut angka yang SAMA dengan strip
                             KPI di tab Ringkasan dan dengan Bab 1 laporannya.
                             Sebelumnya ia memakai lima angka lain, sehingga satu
                             layar memuat dua papan angka untuk satu hal. -->
                        <div class="col-6 col-sm-4 col-md-2-4">
                            <div class="rk-export-stat">
                                <div class="rk-export-stat__num text-primary">{{ angka(kpiLayar.penyelesaian) }}%</div>
                                <div class="rk-export-stat__lbl">Reading Completion</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2-4">
                            <div class="rk-export-stat">
                                <div class="rk-export-stat__num text-dark">{{ angka(kpiLayar.userDitugaskan) }}</div>
                                <div class="rk-export-stat__lbl">Total User</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2-4">
                            <div class="rk-export-stat">
                                <div class="rk-export-stat__num text-primary">{{ angka(kpiLayar.paketAktif) }}</div>
                                <div class="rk-export-stat__lbl">Paket Aktif</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2-4">
                            <div class="rk-export-stat">
                                <div class="rk-export-stat__num text-dark">{{ angka(kpiLayar.dokTotal) }}</div>
                                <div class="rk-export-stat__lbl">Dokumen</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2-4">
                            <div class="rk-export-stat">
                                <div class="rk-export-stat__num text-success">{{ angka(kpiLayar.leadRerata) }}</div>
                                <div class="rk-export-stat__lbl">Lead Time (hari)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template #footer>
            <div class="d-flex justify-content-between align-items-center w-100">
                <HcisButton
                    label="Tutup"
                    variant="ghost"
                    size="sm"
                    @click="$emit('close')"
                />

                <!-- Penyiapan grafik memakan beberapa detik: lima kanvas
                     seukuran halaman dilukis satu per satu. Tanpa menyebut
                     tahapnya, tombol yang berputar diam terbaca seperti macet. -->
                <HcisButton
                    :label="tahapPanen || (formatExport === 'pdf' ? 'Buka & Cetak Laporan Monitoring DMS PDF' : 'Unduh File Spreadsheet (CSV)')"
                    :icon="formatExport === 'pdf' ? 'printer' : 'file-earmark-arrow-down'"
                    variant="primary"
                    size="sm"
                    :loading="sedangUnduh"
                    :disabled="memuat"
                    @click="jalankanExport"
                />
            </div>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import { HcisButton, HcisIcon } from '@/components/ui';
import SopModal from '@/Pages/LMS/SOP/Shared/components/ui/SopModal.vue';
import { API, ambil, angka, durasi, formatLabelRentang } from '../agregat.js';
import { panenBanyak } from '../panenGrafik.js';
import {
    dataBaca,
    dataHeatmap,
    kategoriBaca,
    kategoriMomentum,
    kategoriWaktu,
    seriMomentum,
    seriWaktu,
    tinggiHeatmap,
} from '../grafikSeri.js';

export default {
    name: 'ModalExportRingkasan',
    components: {
        HcisButton,
        HcisIcon,
        SopModal,
    },
    props: {
        show: { type: Boolean, default: false },
        saringan: { type: Object, default: () => ({}) },
        propOrang: { type: [Number, String], default: 0 },
    },
    emits: ['close'],
    data() {
        return {
            memuat: false,
            sedangUnduh: false,
            formatExport: 'pdf',
            rawSummary: {},
            rawPeople: {},
            rawMomentum: { deret: [], sisa: {}, laju: {}, proyeksi: {} },
            rawPeringkat: [],
            rawPemahaman: { baca: {}, kuis: {} },
            rawWaktu: { kelompok: [], total: 0 },
            rawHeatmap: { dept: [], kategori: [], seri: [] },
            /* Seri tren TIDAK pernah diambil modal ini sebelumnya: laporan lama
               menggambar aliran mingguan saja. Padahal garis kepatuhan dari
               waktu ke waktu adalah grafik yang paling dicari pembaca laporan —
               satu-satunya yang menjawab "membaik atau memburuk". */
            /* Dua bab yang sebelumnya TAK PERNAH ikut tercetak walau tampil di
               tab Ringkasan. Bentuknya menyalin KOSONG milik BabKesehatanDokumen
               dan BabJangkauanPublik supaya laporan menggambar bidang yang sama
               dengan layarnya. */
            rawKesehatan: {
                Komposisi: { Total: 0, Baris: [] },
                Sebaran: { Total: 0, Sumbu: [], Data: {} },
                Antrean: { Total: 0, Baris: [] },
                Lead_Time: { Total_Dokumen_Terbit: 0, Rerata_Hari: 0, Tercepat_Hari: 0, Terlama_Hari: 0 },
                Tersaring_Gerbang_Publik: { Penugasan: 0, Paket: 0 },
            },
            rawJangkauan: {
                Jangkauan: { Karyawan_Aktif: 0, Tersentuh: 0, Membuka: 0, Persen: 0, Dokumen_Publik: 0 },
                Terpopuler: [],
                Unit: [],
            },
            /* Gambar hasil panen grafik layar, per kunci. */
            gambarGrafik: {},
            tahapPanen: '',
        };
    },
    computed: {
        labelRentang() {
            return formatLabelRentang(this.saringan);
        },
        /**
         * Lima angka strip KPI tab Ringkasan, dari sumber yang sama dengannya.
         *
         * SENGAJA TERPISAH DARI dataRingkasan(). Yang terakhir itu menurunkan
         * ulang angkanya lewat rantai cadangan (people || peringkat || hitung
         * sendiri) untuk keperluan tabel komposisi; memakainya di sini membuat
         * kartu bernama sama menyebut angka berbeda dari layarnya, dan itulah
         * persis keluhan yang melahirkan perubahan ini.
         *
         * Bentuknya meniru BabKeadaan.kpi(), termasuk keputusan bahwa angka
         * utama "Total User" adalah yang DIBERI PENUGASAN — bukan seluruh
         * karyawan, yang akan membuatnya menghitung populasi HR.
         */
        kpiLayar() {
            const sum = this.rawSummary || {};
            const kes = this.rawKesehatan || {};
            const baris = kes.Komposisi?.Baris || [];
            const jumlahKelompok = (kunci) => Number(baris.find((b) => b.Kelompok === kunci)?.Jumlah || 0);
            const userTotal = Number(sum.user_total) || 0;
            const userDitugaskan = Number(sum.user_ditugaskan) || 0;

            return {
                penyelesaian: Number(sum.coverage) || 0,
                userTotal,
                userDitugaskan,
                bagianUser: userTotal > 0 ? Math.round((userDitugaskan * 100) / userTotal) : 0,
                paketAktif: Number(sum.paket_aktif) || 0,
                dokTotal: Number(kes.Komposisi?.Total) || 0,
                dokDitugaskan: jumlahKelompok('DITUGASKAN'),
                dokBeredar: jumlahKelompok('BEREDAR'),
                dokBelumBeredar: jumlahKelompok('BELUM_BEREDAR'),
                leadRerata: Number(kes.Lead_Time?.Rerata_Hari) || 0,
                leadTerbit: Number(kes.Lead_Time?.Total_Dokumen_Terbit) || 0,
            };
        },
        dataRingkasan() {
            const sum = this.rawSummary || {};
            const people = this.rawPeople || {};
            const peringkat = this.rawPeringkat || [];

            // Hitung fallback dari peringkat unit
            const sumSelesaiUnit = peringkat.reduce((acc, u) => acc + (Number(u.selesai ?? u.tuntas) || 0), 0);
            const sumTargetUnit = peringkat.reduce((acc, u) => acc + (Number(u.orang ?? u.total) || 0), 0);
            const sumTerlambatUnit = peringkat.reduce((acc, u) => acc + (Number(u.terlambat) || 0), 0);

            const targetKaryawan = Number(people.total_karyawan) || Number(this.propOrang) || sumTargetUnit || 0;
            const totalPenugasan = Number(people.total_penugasan) || targetKaryawan;
            
            // Nilai Selesai / Tuntas
            let selesai = Number(people.total_selesai) || sumSelesaiUnit;
            
            // Nilai Tertunda
            let tertunda = (Number(people.total_progress) || 0) + (Number(people.total_belum) || 0);
            if (!tertunda && Number(sum.tertunda)) {
                tertunda = Number(sum.tertunda);
            }
            if (!tertunda && totalPenugasan > selesai) {
                tertunda = totalPenugasan - selesai;
            }

            // Nilai Terlambat
            const terlambat = Number(people.total_terlambat) || Number(sum.terlambat) || sumTerlambatUnit || 0;

            // Persentase Coverage / Selesai
            let coverage = Number(sum.coverage) || Number(people.coverage) || 0;
            if ((!coverage || coverage === 0) && totalPenugasan > 0 && selesai > 0) {
                coverage = Math.round((selesai / totalPenugasan) * 100);
            }

            const paketAktif = Number(sum.paket_aktif) || 0;

            return {
                penyelesaian_persen: coverage,
                total_target: targetKaryawan || totalPenugasan,
                total_penugasan: totalPenugasan,
                total_selesai: selesai,
                total_tertunda: tertunda,
                total_terlambat: terlambat,
                total_paket: paketAktif,
            };
        },
    },
    watch: {
        show(val) {
            if (val) {
                this.muatDataExport();
            }
        },
    },
    methods: {
        angka(n) {
            return angka(n);
        },
        async muatDataExport() {
            this.memuat = true;
            try {
                const s = { ...this.saringan };
                const [rSummary, rKaryawanDirect, rMomentum, rPeringkat, rPemahaman, rWaktu, rHeatmap,
                    rKesehatan, rJangkauan] = await Promise.allSettled([
                    ambil('/summary', s),
                    axios.get(`${API}/karyawan`, { params: { limit: 1, ...s } }),
                    ambil('/analitik/momentum', s),
                    ambil('/analitik/peringkat-unit', { dimensi: 'divisi', limit: 50, ...s }),
                    ambil('/analitik/pemahaman', s),
                    ambil('/analitik/waktu-selesai', s),
                    ambil('/analitik/heatmap', s),
                    ambil('/dokumen/kesehatan', s),
                    ambil('/dokumen/jangkauan-publik', s),
                ]);

                if (rSummary.status === 'fulfilled') this.rawSummary = rSummary.value || {};
                if (rKaryawanDirect.status === 'fulfilled') {
                    const data = rKaryawanDirect.value?.data;
                    this.rawPeople = data?.summary || {};
                }
                if (rMomentum.status === 'fulfilled') this.rawMomentum = rMomentum.value || { deret: [], sisa: {}, laju: {}, proyeksi: {} };
                if (rPeringkat.status === 'fulfilled') {
                    const val = rPeringkat.value;
                    if (Array.isArray(val)) {
                        this.rawPeringkat = val;
                    } else if (val && typeof val === 'object' && Array.isArray(val.unit)) {
                        this.rawPeringkat = val.unit;
                    } else {
                        this.rawPeringkat = [];
                    }
                }
                if (rPemahaman.status === 'fulfilled') this.rawPemahaman = rPemahaman.value || { baca: {}, kuis: {} };
                if (rWaktu.status === 'fulfilled') this.rawWaktu = rWaktu.value || { kelompok: [], total: 0 };
                if (rHeatmap.status === 'fulfilled') this.rawHeatmap = rHeatmap.value || { dept: [], kategori: [], seri: [] };
                /* Digabung ke bentuk kosong, bukan ditimpa: endpoint yang gagal
                   sebagian akan meninggalkan bidang yang hilang, dan template
                   cetak membaca bidang itu tanpa penjaga. */
                if (rKesehatan.status === 'fulfilled') this.rawKesehatan = { ...this.rawKesehatan, ...(rKesehatan.value || {}) };
                if (rJangkauan.status === 'fulfilled') this.rawJangkauan = { ...this.rawJangkauan, ...(rJangkauan.value || {}) };
            } catch (e) {
                /* silent */
            } finally {
                this.memuat = false;
            }
        },
        jalankanExport() {
            if (this.formatExport === 'pdf') {
                this.cetakLaporanPdf();
            } else {
                this.unduhCsv();
            }
        },

        /**
         * Panen grafik LAYAR pada ukuran halaman.
         *
         * Yang di-mount adalah HcisChart yang sama dengan panel-panelnya, jadi
         * grafik di kertas bukan gambar tandingan yang harus dijaga sejalan —
         * ia komponen yang sama. Serinya pun dibentuk `grafikSeri.js`, satu
         * berkas yang juga dibaca panel di layar.
         */
        async panenGrafikLaporan() {
            const depts = (this.rawHeatmap?.dept || []);

            this.tahapPanen = 'Menyiapkan grafik laporan…';
            this.gambarGrafik = await panenBanyak([
                {
                    kunci: 'waktu',
                    tinggi: 360,
                    props: {
                        type: 'bar-horizontal',
                        categories: kategoriWaktu(this.rawWaktu),
                        series: seriWaktu(this.rawWaktu),
                        sentiment: 'violet',
                    },
                },
                {
                    kunci: 'baca',
                    tinggi: 340,
                    props: {
                        type: 'bar',
                        categories: kategoriBaca(this.rawPemahaman),
                        data: dataBaca(this.rawPemahaman),
                        sentiment: 'brand',
                    },
                },
                {
                    kunci: 'heatmap',
                    /* Laporan memakai SELURUH departemen, bukan delapan per
                       halaman seperti di layar — kertas tak punya tombol
                       "halaman berikutnya". */
                    tinggi: Math.min(560, tinggiHeatmap(depts.length)),
                    props: {
                        type: 'heatmap',
                        categories: this.rawHeatmap?.kategori || [],
                        series: [{ categories: depts }],
                        data: dataHeatmap(this.rawHeatmap, depts),
                    },
                },
            ]);
            this.tahapPanen = '';
        },

        /**
         * Sisipkan gambar grafik, atau kalimat yang menyebut ketiadaannya.
         *
         * Kotak kosong pada laporan cetak tak bisa ditanyai. Bila panen gagal
         * atau datanya memang belum cukup, tempatnya diisi kalimat — bukan
         * dibiarkan sebagai ruang putih yang pembacanya kira salah cetak.
         */
        gambarAtauCatatan(kunci, kalimatKosong) {
            const src = this.gambarGrafik?.[kunci];
            if (src) {
                return `<img class="rpt-figure" src="${src}" alt="" />`;
            }

            return `<div class="rpt-figure-kosong">${kalimatKosong}</div>`;
        },
        /**
         * Nama dan divisi orang yang menekan cetak.
         *
         * Dibaca dari prop Inertia `auth.user`, yang sudah dirakit
         * HandleInertiaRequests dari relasi karyawan → divisi. Divisinya bisa
         * kosong untuk akun yang tak tertaut karyawan; blok tanda tangan
         * MENGHILANGKAN barisnya dalam keadaan itu, bukan mencetak tanda hubung
         * — baris kosong berlabel "divisi" membuat pembaca berkas mengira
         * datanya hilang, padahal memang tak ada.
         */
        identitasPencetak() {
            const u = this.$page?.props?.auth?.user || {};

            return {
                nama: this.lolosHtml(u.name || u.username || 'Tidak diketahui'),
                divisi: this.lolosHtml(u.department || ''),
            };
        },

        /** Nilai dari basis data ditanam apa adanya ke HTML cetak; lolos dulu. */
        lolosHtml(nilai) {
            return String(nilai ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        async cetakLaporanPdf() {
            this.sedangUnduh = true;
            try {
                /* Grafik dipanen LEBIH DULU. Menyusun HTML-nya duluan lalu
                   menyisipkan gambar belakangan berarti jendela cetak sudah
                   terbuka saat gambarnya belum ada — dan `window.print()` di
                   dalamnya menyala pada halaman yang masih kosong. */
                await this.panenGrafikLaporan();
                const periode = this.labelRentang || 'Semua Waktu (Seluruh Riwayat)';
                const waktuCetak = new Date().toLocaleString('id-ID', {
                    dateStyle: 'full',
                    timeStyle: 'medium',
                });

                /* SIAPA yang mencetak, bukan "Sistem".
                   Laporan ini beredar sebagai berkas; tanpa nama dan divisi
                   pencetaknya, penerima berkas tak punya cara menanyakan
                   angkanya kepada siapa pun. Dilolos-karakterkan karena nama
                   dan divisi datang dari basis data dan ditanam apa adanya ke
                   dalam HTML. */
                const pencetak = this.identitasPencetak();

                const ringkasan = this.dataRingkasan;
                const peringkat = this.rawPeringkat;
                const pemahaman = this.rawPemahaman;
                const waktu = this.rawWaktu;
                const heatmap = this.rawHeatmap;
                const kesehatan = this.rawKesehatan;
                const jangkauan = this.rawJangkauan;

                /* Judul dan nomor dokumen datang dari basis data dan ditanam
                   apa adanya ke HTML cetak. Diberi nama pendek karena dipakai
                   puluhan kali di dalam template di bawah. */
                const esc = (v) => this.lolosHtml(v);

                /* Satu baris batang. `segmen` boleh satu nilai (batang tunggal)
                   atau tiga (bertumpuk: ditugaskan / beredar / belum beredar).
                   Lebarnya relatif terhadap baris TERBESAR, bukan terhadap
                   totalnya sendiri — kalau tiap baris dinormalkan ke 100%,
                   seluruh batang jadi sama panjang dan grafiknya berhenti
                   memberi tahu apa pun. */
                const barisBatang = (label, sub, segmen, total, maks) => {
                    const skala = maks > 0 ? 100 / maks : 0;
                    const potong = segmen
                        .filter((g) => g.nilai > 0)
                        .map((g) => `<div class="hbar-seg ${g.kelas}" style="width:${(g.nilai * skala).toFixed(2)}%"></div>`)
                        .join('');

                    return `
                        <div class="hbar">
                            <div class="hbar-lbl">${esc(label)}${sub ? `<small>${esc(sub)}</small>` : ''}</div>
                            <div class="hbar-track">${potong}</div>
                            <div class="hbar-val">${angka(total)}</div>
                        </div>`;
                };

                const legendaSebaran = `
                    <div class="hbar-legend">
                        <span><i style="background:#4f46e5"></i>Ditugaskan</span>
                        <span><i style="background:#0ea5e9"></i>Beredar</span>
                        <span><i style="background:#cbd5e1"></i>Belum beredar</span>
                    </div>`;

                // Satu sumber untuk pratinjau di layar DAN untuk Bab 1 laporan.
                const kpiLayar = this.kpiLayar;


                // Baris Tabel Peringkat Unit
                const barisPeringkat = peringkat.map((u, i) => {
                    const unitName = u.unit || u.nama || '—';
                    const target = u.orang ?? u.total ?? u.total_target ?? u.target ?? 0;
                    const selesai = u.selesai ?? u.tuntas ?? u.total_selesai ?? 0;
                    const terlambat = u.terlambat ?? u.total_terlambat ?? 0;
                    const persen = Number(u.persen ?? u.persen_selesai ?? (target > 0 ? Math.round((selesai / target) * 100) : 0));

                    let badgeClass = 'bg-danger-subtle text-danger';
                    let progressColor = '#ef4444';
                    if (persen >= 80) {
                        badgeClass = 'bg-success-subtle text-success';
                        progressColor = '#10b981';
                    } else if (persen >= 50) {
                        badgeClass = 'bg-warning-subtle text-warning';
                        progressColor = '#f59e0b';
                    }

                    const rankMedal = i === 0 ? '🥇 #1' : (i === 1 ? '🥈 #2' : (i === 2 ? '🥉 #3' : `#${i + 1}`));

                    return `
                        <tr>
                            <td class="text-center font-bold text-slate">${rankMedal}</td>
                            <td class="font-bold text-dark">${unitName}</td>
                            <td class="text-end">${angka(target)}</td>
                            <td class="text-end text-success font-bold">${angka(selesai)}</td>
                            <td class="text-end text-danger font-bold">${angka(terlambat)}</td>
                            <td class="text-end">
                                <div class="prog-cell">
                                    <div class="prog-track">
                                        <div class="prog-fill" style="width: ${persen}%; background: ${progressColor};"></div>
                                    </div>
                                    <span class="badge ${badgeClass} font-bold">${persen}%</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');


                // Baris Distribusi Waktu Baca
                const barisWaktu = (waktu.kelompok || []).map((k) => `
                    <tr>
                        <td class="font-bold">${k.label} ${k.kilat ? '<span class="badge bg-danger-subtle text-danger ms-1">Konfirmasi Kilat</span>' : ''}</td>
                        <td class="text-end font-bold font-mono">${angka(k.jumlah || 0)} Penugasan</td>
                        <td class="text-end text-muted font-mono">${waktu.total > 0 ? Math.round(((k.jumlah || 0) / waktu.total) * 100) : 0}%</td>
                    </tr>
                `).join('');

                // Header Heatmap Matriks
                const headerHeatmap = (heatmap.kategori || []).map((k) => `<th class="text-center font-bold" style="font-size: 10px;">${k}</th>`).join('');

                // Baris Heatmap Matriks
                const barisHeatmap = (heatmap.dept || []).slice(0, 10).map((dept) => {
                    const s = (heatmap.seri || []).find((x) => x.name === dept);
                    const cells = (heatmap.kategori || []).map((kat) => {
                        const item = (s?.data || []).find((x) => x.x === kat);
                        const val = item?.y !== null && item?.y !== undefined ? Number(item.y) : null;
                        if (val === null) return '<td class="text-center text-muted" style="font-size: 10px;">—</td>';
                        let c = 'pill-danger';
                        if (val >= 80) c = 'pill-success';
                        else if (val >= 50) c = 'pill-warning';
                        return `<td class="text-center"><span class="matrix-pill ${c}">${val}%</span></td>`;
                    }).join('');

                    return `
                        <tr>
                            <td class="font-bold text-dark" style="font-size: 10.5px;">${dept}</td>
                            ${cells}
                        </tr>
                    `;
                }).join('');

                /* KEPALA DAN KAKI DITULIS SEKALI.
                   Sebelumnya keduanya disalin utuh di setiap halaman, dan
                   nomor halamannya diketik tangan ("Halaman 4 dari 4").
                   Menambah satu bab berarti menyunting delapan tempat, dan
                   yang terlewat memberi tahu pembaca bahwa laporannya berakhir
                   padahal masih ada halaman sesudahnya. */
                const TOTAL_HAL = 9;
                const kepalaLari = `
            <div class="running-header">
                <div class="rh-brand">
                    <div class="rh-brand-logo">DMS</div>
                    <div>
                        <div class="rh-brand-title">HCIS &mdash; DOCUMENT MANAGEMENT SYSTEM</div>
                        <div class="rh-brand-sub">DMS Summary Report</div>
                    </div>
                </div>
                <div class="rh-meta">
                    <div class="rh-meta-time">Dicetak: <strong>${waktuCetak}</strong></div>
                    <div class="rh-meta-badge">Periode: ${periode}</div>
                </div>
            </div>`;

                const kakiLari = (n) => `
        <div class="running-footer">
            <div>HCIS — Document Management System</div>
            <div class="rf-confidential">Dokumen Rahasia Perusahaan</div>
            <div><strong>Halaman ${n} dari ${TOTAL_HAL}</strong></div>
        </div>`;

                const html = `
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Monitoring DMS — ${periode}</title>
    <style>
        :root {
            /* ── Core Absolute Colors ── */
            --hcis-white: #ffffff;
            --hcis-black: #000000;
            --hcis-white-rgb: 255 255 255;
            --hcis-black-rgb: 0 0 0;

            /* ── Slate Palette ── */
            --hcis-slate-50: #f8fafc;
            --hcis-slate-100: #f1f5f9;
            --hcis-slate-200: #e2e8f0;
            --hcis-slate-300: #cbd5e1;
            --hcis-slate-400: #94a3b8;
            --hcis-slate-450: #7b8ea5;
            --hcis-slate-500: #64748b;
            --hcis-slate-600: #475569;
            --hcis-slate-700: #334155;
            --hcis-slate-800: #1e293b;
            --hcis-slate-900: #0f172a;
            --hcis-slate-950: #020617;
            --hcis-slate-50-rgb: 248 250 252;
            --hcis-slate-100-rgb: 241 245 249;
            --hcis-slate-200-rgb: 226 232 240;
            --hcis-slate-300-rgb: 203 213 225;
            --hcis-slate-400-rgb: 148 163 184;
            --hcis-slate-500-rgb: 100 116 139;
            --hcis-slate-600-rgb: 71 85 105;
            --hcis-slate-700-rgb: 51 65 85;
            --hcis-slate-800-rgb: 30 41 59;
            --hcis-slate-900-rgb: 15 23 42;

            /* ── Indigo Palette ── */
            --hcis-indigo-50: #eef2ff;
            --hcis-indigo-100: #e0e7ff;
            --hcis-indigo-200: #c7d2fe;
            --hcis-indigo-300: #a5b4fc;
            --hcis-indigo-400: #818cf8;
            --hcis-indigo-500: #6366f1;
            --hcis-indigo-600: #4f46e5;
            --hcis-indigo-700: #4338ca;
            --hcis-indigo-800: #3730a3;
            --hcis-indigo-900: #312e81;
            --hcis-indigo-950: #1e1b4b;
            --hcis-indigo-50-rgb: 238 242 255;
            --hcis-indigo-100-rgb: 224 231 255;
            --hcis-indigo-200-rgb: 199 210 254;
            --hcis-indigo-300-rgb: 165 180 252;
            --hcis-indigo-400-rgb: 129 140 248;
            --hcis-indigo-500-rgb: 99 102 241;
            --hcis-indigo-600-rgb: 79 70 229;
            --hcis-indigo-700-rgb: 67 56 202;
            --hcis-indigo-800-rgb: 55 48 163;
            --hcis-indigo-900-rgb: 49 46 129;
            --hcis-indigo-950-rgb: 30 27 75;

            /* ── Sky Palette ── */
            --hcis-sky-50: #f0f9ff;
            --hcis-sky-100: #e0f2fe;
            --hcis-sky-200: #bae6fd;
            --hcis-sky-300: #7dd3fc;
            --hcis-sky-400: #38bdf8;
            --hcis-sky-500: #0ea5e9;
            --hcis-sky-600: #0284c7;
            --hcis-sky-700: #0369a1;
            --hcis-sky-800: #075985;
            --hcis-sky-900: #0c4a6e;

            /* ── Emerald Palette ── */
            --hcis-emerald-50: #ecfdf5;
            --hcis-emerald-100: #d1fae5;
            --hcis-emerald-200: #a7f3d0;
            --hcis-emerald-300: #6ee7b7;
            --hcis-emerald-400: #34d399;
            --hcis-emerald-500: #10b981;
            --hcis-emerald-600: #059669;
            --hcis-emerald-700: #047857;
            --hcis-emerald-800: #065f46;
            --hcis-emerald-900: #064e3b;

            /* ── Amber Palette ── */
            --hcis-amber-50: #fffbeb;
            --hcis-amber-100: #fef3c7;
            --hcis-amber-200: #fde68a;
            --hcis-amber-300: #fcd34d;
            --hcis-amber-400: #fbbf24;
            --hcis-amber-500: #f59e0b;
            --hcis-amber-600: #d97706;
            --hcis-amber-700: #b45309;
            --hcis-amber-800: #92400e;
            --hcis-amber-900: #78350f;

            /* ── Red Palette ── */
            --hcis-red-50: #fef2f2;
            --hcis-red-100: #fee2e2;
            --hcis-red-200: #fecaca;
            --hcis-red-300: #fca5a5;
            --hcis-red-400: #f87171;
            --hcis-red-500: #ef4444;
            --hcis-red-600: #dc2626;
            --hcis-red-700: #b91c1c;
            --hcis-red-800: #991b1b;
            --hcis-red-900: #7f1d1d;

            /* ── Violet Palette ── */
            --hcis-violet-50: #f5f3ff;
            --hcis-violet-100: #ede9fe;
            --hcis-violet-200: #ddd6fe;
            --hcis-violet-300: #c4b5fd;
            --hcis-violet-400: #a78bfa;
            --hcis-violet-500: #8b5cf6;
            --hcis-violet-600: #7c3aed;
            --hcis-violet-700: #6d28d9;
            --hcis-violet-800: #5b21b6;
            --hcis-violet-900: #4c1d95;
        }

        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        html, body { margin: 0; padding: 0; background: #e2e8f0; color: var(--hcis-slate-900); line-height: 1.45; font-size: 12px; }
        
        /* ══════════════════════════════════════════════════════════════
           PAGE SETUP & SLIDE DECK A4 STRUCTURE
           ══════════════════════════════════════════════════════════════ */
        .pdf-page {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 15px auto;
            padding: 18mm 18mm 16mm 18mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-after: always;
            break-after: page;
            position: relative;
        }
        .pdf-page:last-child {
            page-break-after: auto;
            break-after: auto;
            margin-bottom: 20px;
        }

        /* ══════════════════════════════════════════════════════════════
           FIGUR GRAFIK — hasil panen dari grafik layar
           ══════════════════════════════════════════════════════════════ */
        .rpt-figure {
            display: block;
            width: 100%;
            height: auto;
            border: 1px solid var(--hcis-slate-200);
            border-radius: 10px;
            background: var(--hcis-white);
            /* Grafik TAK BOLEH terbelah dua halaman: separuh sumbu di kaki
               halaman dan separuhnya di kepala berikutnya tak bisa dibaca. */
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .rpt-figure-kosong {
            padding: 28px 18px;
            border: 1px dashed var(--hcis-slate-300);
            border-radius: 10px;
            background: var(--hcis-slate-50);
            color: var(--hcis-slate-500);
            font-size: 11px;
            text-align: center;
            font-style: italic;
        }
        .rpt-fig-cap {
            margin-top: 6px;
            font-size: 10px;
            color: var(--hcis-slate-500);
            line-height: 1.5;
        }
        .rpt-fig-cap strong { color: var(--hcis-slate-700); }

        /* ══════════════════════════════════════════════════════════════
           SAMPUL & DAFTAR ISI
           ══════════════════════════════════════════════════════════════ */
        .cover {
            background: linear-gradient(145deg, #0b1120 0%, #1e1b4b 45%, #312e81 100%) !important;
            color: #ffffff;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .cover::before {
            content: "";
            position: absolute;
            top: -120px;
            right: -120px;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .cover-mark {
            width: 64px; height: 64px; border-radius: 18px;
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border: 1.5px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 22px; letter-spacing: 1.5px; color: #ffffff;
        }
        .cover-kicker {
            margin-top: 36px; font-size: 11px; letter-spacing: 2.5px;
            text-transform: uppercase; color: #a5b4fc; font-weight: 750;
        }
        .cover-title {
            margin: 12px 0 0; font-size: 38px; line-height: 1.18;
            font-weight: 800; color: #ffffff !important; letter-spacing: -0.02em;
        }
        .cover-sub {
            margin-top: 14px; font-size: 13.5px; color: #c7d2fe;
            max-width: 140mm; line-height: 1.6;
        }
        .cover-rule {
            height: 4px; width: 88px; border-radius: 999px;
            background: linear-gradient(90deg, #6366f1, #38bdf8);
            margin: 24px 0 20px;
        }
        .cover-meta {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 12px 18px; margin-top: 28px;
        }
        .cover-meta-item {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-left: 3.5px solid #6366f1;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .cover-meta-lbl {
            font-size: 9.5px; letter-spacing: 1.2px;
            text-transform: uppercase; color: #94a3b8; font-weight: 750;
        }
        .cover-meta-val {
            font-size: 13.5px; color: #ffffff; font-weight: 700; margin-top: 3px;
        }
        .cover-foot {
            font-size: 10px; color: #94a3b8;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 14px; line-height: 1.5;
        }

        .toc-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border: 1px solid var(--hcis-slate-200);
            border-radius: 9px; margin-bottom: 8px; background: var(--hcis-white);
        }
        .toc-num {
            width: 32px; height: 32px; border-radius: 8px; flex: none;
            background: var(--hcis-indigo-50); color: var(--hcis-indigo-700);
            border: 1px solid var(--hcis-indigo-200);
            font-weight: 800; font-size: 13px;
            display: flex; align-items: center; justify-content: center;
        }
        .toc-txt { flex: 1; }
        .toc-title { font-weight: 750; font-size: 12.5px; color: var(--hcis-slate-900); }
        .toc-desc { font-size: 10.5px; color: var(--hcis-slate-500); margin-top: 1px; }
        .toc-page {
            font-weight: 800; color: var(--hcis-indigo-600); font-size: 11.5px;
            background: var(--hcis-slate-100); padding: 4px 10px; border-radius: 6px;
        }

        /* ══════════════════════════════════════════════════════════════
           RUNNING HEADER & RUNNING FOOTER
           ══════════════════════════════════════════════════════════════ */
        .running-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--hcis-slate-200);
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .rh-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rh-brand-logo {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-indigo-800));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
        }
        .rh-brand-title {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--hcis-slate-900);
            letter-spacing: -0.01em;
        }
        .rh-brand-sub {
            font-size: 9.5px;
            font-weight: 600;
            color: var(--hcis-slate-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .rh-meta {
            text-align: right;
        }
        .rh-meta-time {
            font-size: 9.5px;
            color: var(--hcis-slate-500);
        }
        .rh-meta-badge {
            display: inline-block;
            background: var(--hcis-indigo-50);
            color: var(--hcis-indigo-700);
            border: 1px solid var(--hcis-indigo-200);
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
            margin-top: 2px;
        }

        .running-footer {
            border-top: 1px solid var(--hcis-slate-200);
            padding-top: 8px;
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9.5px;
            color: var(--hcis-slate-500);
        }
        .rf-confidential {
            font-weight: 750;
            color: var(--hcis-slate-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ══════════════════════════════════════════════════════════════
           CHAPTER HERO BANNER & CARDS
           ══════════════════════════════════════════════════════════════ */
        .chapter-hero {
            background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
            border: 1px solid var(--hcis-slate-200);
            border-left: 5px solid var(--hcis-indigo-600);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .ch-kicker {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--hcis-indigo-600);
            letter-spacing: 0.08em;
            margin-bottom: 3px;
        }
        .ch-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--hcis-slate-900);
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
        }
        .ch-desc {
            font-size: 11px;
            color: var(--hcis-slate-600);
            margin: 0;
            line-height: 1.5;
        }

        /* ══════════════════════════════════════════════════════════════
           KPI STAT CARDS GRID (BAB 1)
           ══════════════════════════════════════════════════════════════ */
        /* FLEX, BUKAN GRID BERKOLOM TETAP. Sebelumnya repeat(5, 1fr): baris
           berisi tiga atau empat kartu menyisakan kolom kosong di kanan, dan
           kartunya menyempit tanpa alasan. Dengan flex:1 tiap kartu, berapa pun
           jumlahnya selalu memenuhi lebar halaman. */
        .kpi-row {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }
        .kpi-row > .kpi-box {
            flex: 1 1 0;
            min-width: 0;
        }

        /* ── Batang horizontal ────────────────────────────────────────────
           Dipakai menggantikan tabel angka yang panjang. Murni CSS: tak ada
           gambar yang perlu dipanen lebih dulu, tak ada skrip yang harus jalan
           di jendela cetak, dan ia tetap terbaca bila pencetakan mematikan
           warna latar (angkanya selalu tertulis di kanan). */
        .hbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3px 0;
        }
        .hbar + .hbar {
            border-top: 1px solid var(--hcis-slate-100);
        }
        .hbar-lbl {
            width: 38%;
            font-size: 10px;
            color: var(--hcis-slate-700);
            line-height: 1.25;
            overflow: hidden;
        }
        .hbar-lbl small {
            display: block;
            color: var(--hcis-slate-400);
            font-size: 8.5px;
        }
        .hbar-track {
            flex: 1;
            display: flex;
            height: 13px;
            background: var(--hcis-slate-100);
            border-radius: 3px;
            overflow: hidden;
        }
        .hbar-seg {
            height: 100%;
        }
        .hbar-seg.is-tugas { background: var(--hcis-indigo-600); }
        .hbar-seg.is-edar { background: var(--hcis-sky-500); }
        .hbar-seg.is-belum { background: var(--hcis-slate-300); }
        .hbar-seg.is-tunggal { background: var(--hcis-violet-600); }
        .hbar-val {
            width: 46px;
            text-align: right;
            font-size: 10.5px;
            font-weight: 800;
            color: var(--hcis-slate-900);
        }
        .hbar-legend {
            display: flex;
            gap: 12px;
            margin: 2px 0 6px;
            font-size: 9px;
            color: var(--hcis-slate-500);
        }
        .hbar-legend i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 2px;
            margin-right: 4px;
        }
        .kpi-box {
            background: var(--hcis-white);
            border: 1px solid var(--hcis-slate-200);
            border-radius: 10px;
            padding: 12px 10px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .kpi-box-num {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 3px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .kpi-box-lbl {
            font-size: 9.5px;
            font-weight: 750;
            text-transform: uppercase;
            color: var(--hcis-slate-500);
            letter-spacing: 0.02em;
        }
        .kpi-box-sub {
            font-size: 9px;
            color: var(--hcis-slate-400);
            margin-top: 4px;
        }

        /* ══════════════════════════════════════════════════════════════
           DATA CARDS & PANELS
           ══════════════════════════════════════════════════════════════ */
        .pdf-card {
            background: var(--hcis-white);
            border: 1px solid var(--hcis-slate-200);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        }
        .pdf-card-title {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--hcis-slate-800);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--hcis-slate-100);
            padding-bottom: 6px;
        }

        /* ══════════════════════════════════════════════════════════════
           TABLES
           ══════════════════════════════════════════════════════════════ */
        table.pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 6px;
        }
        table.pdf-table th {
            background: var(--hcis-slate-50);
            color: var(--hcis-slate-600);
            font-weight: 750;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid var(--hcis-slate-200);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        table.pdf-table td {
            padding: 7px 10px;
            border: 1px solid var(--hcis-slate-200);
            color: var(--hcis-slate-700);
            vertical-align: middle;
        }
        table.pdf-table tr:nth-child(even) td {
            background: #fafbfd;
        }

        /* ══════════════════════════════════════════════════════════════
           VISUAL ELEMENTS (BARS, PILLS, MEDALS)
           ══════════════════════════════════════════════════════════════ */
        .prog-cell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        .prog-track {
            flex: 1;
            max-width: 90px;
            height: 7px;
            background: var(--hcis-slate-200);
            border-radius: 999px;
            overflow: hidden;
        }
        .prog-fill {
            height: 100%;
            border-radius: 999px;
        }

        .bar-compare {
            display: flex;
            flex-direction: column;
            gap: 3px;
            width: 100%;
            max-width: 140px;
        }
        .bar-in {
            height: 5px;
            background: var(--hcis-indigo-600);
            border-radius: 3px;
        }
        .bar-out {
            height: 5px;
            background: var(--hcis-emerald-500);
            border-radius: 3px;
        }

        .matrix-pill {
            display: inline-block;
            font-weight: 800;
            font-size: 9.5px;
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 42px;
            text-align: center;
        }
        .pill-success { background: var(--hcis-emerald-100); color: var(--hcis-emerald-800); }
        .pill-warning { background: var(--hcis-amber-100); color: var(--hcis-amber-800); }
        .pill-danger { background: var(--hcis-red-100); color: var(--hcis-red-800); }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 9.5px;
            font-weight: 750;
        }
        .bg-success-subtle { background: var(--hcis-emerald-100); }
        .text-success { color: var(--hcis-emerald-600) !important; }
        .bg-warning-subtle { background: var(--hcis-amber-100); }
        .text-warning { color: var(--hcis-amber-600) !important; }
        .bg-danger-subtle { background: var(--hcis-red-100); }
        .text-danger { color: var(--hcis-red-600) !important; }
        .text-primary { color: var(--hcis-indigo-600) !important; }
        .text-dark { color: var(--hcis-slate-900) !important; }
        .text-slate { color: var(--hcis-slate-600) !important; }
        .text-muted { color: var(--hcis-slate-400) !important; }
        .font-bold { font-weight: 750; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }

        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* ══════════════════════════════════════════════════════════════
           PRINT RULES
           ══════════════════════════════════════════════════════════════ */
        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body {
                background: #ffffff !important;
            }
            .pdf-page {
                margin: 0 !important;
                box-shadow: none !important;
                padding: 12mm 15mm !important;
                min-height: 275mm !important;
                page-break-after: always !important;
                break-after: page !important;
            }
            .pdf-page.cover {
                background: linear-gradient(145deg, #0b1120 0%, #1e1b4b 45%, #312e81 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            button { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- ══════════════════════════════════════════════════════════════
         SAMPUL
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page cover">
        <div>
            <div class="cover-mark">DMS</div>
            <div class="cover-kicker">HCIS &mdash; Document Management System</div>
            <h1 class="cover-title">Laporan Monitoring DMS<br>Kepatuhan Dokumen</h1>
            <div class="cover-rule"></div>
            <p class="cover-sub">
                Tinjauan menyeluruh atas penyelesaian penugasan dokumen, momentum kerja,
                sebaran kepatuhan antar unit, dan kualitas penyerapan bacaan karyawan.
            </p>

            <div class="cover-meta">
                <div class="cover-meta-item">
                    <div class="cover-meta-lbl">Periode</div>
                    <div class="cover-meta-val">${periode}</div>
                </div>
                <div class="cover-meta-item">
                    <div class="cover-meta-lbl">Tingkat Penyelesaian</div>
                    <div class="cover-meta-val">${ringkasan.penyelesaian_persen}%</div>
                </div>
                <div class="cover-meta-item">
                    <div class="cover-meta-lbl">Karyawan Ditugaskan</div>
                    <div class="cover-meta-val">${angka(ringkasan.total_target)} orang</div>
                </div>
                <div class="cover-meta-item">
                    <div class="cover-meta-lbl">Disusun</div>
                    <div class="cover-meta-val">${waktuCetak}</div>
                </div>
            </div>
        </div>

        <div class="cover-foot">
            Dihasilkan otomatis oleh HCIS Document Management System.
            Angka pada laporan ini adalah cerminan basis data pada saat pencetakan —
            bukan salinan yang diperbarui sendiri.
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         DAFTAR ISI
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}
            <div class="chapter-hero">
                <div class="ch-kicker">Isi Laporan</div>
                <h1 class="ch-title">Daftar Bab</h1>
                <p class="ch-desc">Lima bab, mengikuti persis urutan kartu di tab Ringkasan Monitoring.</p>
            </div>

            ${[
                ['Keadaan Umum', 'Lima angka pokok dan komposisi penyelesaian.', 3],
                ['Kesehatan Dokumen', 'Antrean tata kelola, lead time penerbitan, dan sebaran master.', 4],
                ['Titik Macet &amp; Peringkat Unit Kerja', 'Unit mana yang tertinggal, dan seberapa jauh.', 6],
                ['Kualitas Penyerapan Bacaan', 'Berapa lama dokumen dibaca, dan sebarannya per departemen.', 7],
                ['Jangkauan Dokumen Publik', 'Berapa karyawan benar-benar membuka dokumen yang terbuka bagi semua.', 9],
            ].map(([judul, ket, hal], i) => `
                <div class="toc-item">
                    <div class="toc-num">${i + 1}</div>
                    <div class="toc-txt">
                        <div class="toc-title">${judul}</div>
                        <div class="toc-desc">${ket}</div>
                    </div>
                    <div class="toc-page">Hal. ${hal}</div>
                </div>
            `).join('')}

            <div style="margin-top: 18px; padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 10.5px; color: #475569; line-height: 1.6;">
                <strong>Cara membaca laporan ini.</strong> Grafik pada bab 2 sampai 6 adalah
                grafik yang sama dengan yang tampil di layar Monitoring — dipanen langsung
                dari sana, bukan digambar ulang, sehingga angka di kertas dan di layar tak
                mungkin berbeda. Sel atau batang yang kosong berarti belum ada penugasan
                pada irisan itu, bukan nol persen kepatuhan.
            </div>
        </div>

        ${kakiLari(2)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 3: BAB 1 — KEADAAN UMUM
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <!-- Chapter 1 Hero -->
            <div class="chapter-hero">
                <div class="ch-kicker">BAB 1 — KEADAAN UMUM PERUSAHAAN</div>
                <h1 class="ch-title">Ringkasan Kepatuhan & Penyelesaian Dokumen</h1>
                <p class="ch-desc">
                    Tinjauan makro pencapaian penugasan dokumen, keterlibatan karyawan, dan status penyelesaian pada cakupan <strong>${periode}</strong>.
                </p>
            </div>

            <!-- 5 Hero KPI Metric Strip -->
            <!-- LIMA KARTU YANG SAMA PERSIS DENGAN STRIP KPI DI TAB RINGKASAN.

                 Sebelumnya bab ini mencetak lima angka yang BERBEDA sama sekali
                 (Target Karyawan, Penugasan Tuntas, Masih Tertunda, Terlambat),
                 dan hanya satu di antaranya beririsan dengan layar. Pembaca yang
                 memegang berkas ini di samping layarnya melihat dua papan angka
                 untuk satu hal yang sama.

                 Sumbernya pun disamakan: dibaca LANGSUNG dari rawSummary dan
                 rawKesehatan, bukan dari dataRingkasan. Yang terakhir itu
                 menurunkan ulang angkanya lewat rantai cadangan (people ||
                 peringkat || hitung sendiri), sehingga kartu bernama sama masih
                 bisa menyebut angka berbeda dari layarnya. -->
            <div class="kpi-row">
                <div class="kpi-box" style="border-top: 4px solid #4f46e5;">
                    <div class="kpi-box-lbl">Reading Completion Rate</div>
                    <div class="kpi-box-num text-primary">${angka(kpiLayar.penyelesaian)}%</div>
                    <div class="kpi-box-sub">Dari seluruh penugasan aktif</div>
                </div>
                <div class="kpi-box" style="border-top: 4px solid #0ea5e9;">
                    <div class="kpi-box-lbl">Total User</div>
                    <div class="kpi-box-num text-dark">${angka(kpiLayar.userDitugaskan)}</div>
                    <div class="kpi-box-sub">${angka(kpiLayar.bagianUser)}% dari ${angka(kpiLayar.userTotal)} user aktif</div>
                </div>
                <div class="kpi-box" style="border-top: 4px solid #7c3aed;">
                    <div class="kpi-box-lbl">Active Document Packages</div>
                    <div class="kpi-box-num text-slate">${angka(kpiLayar.paketAktif)}</div>
                    <div class="kpi-box-sub">Terbit &amp; berlaku</div>
                </div>
                <div class="kpi-box" style="border-top: 4px solid #0f172a;">
                    <div class="kpi-box-lbl">Uploaded Documents</div>
                    <div class="kpi-box-num text-dark">${angka(kpiLayar.dokTotal)} <span style="font-size:11px;font-weight:600;">dok</span></div>
                    <div class="kpi-box-sub">${angka(kpiLayar.dokDitugaskan)} ditugaskan &middot; ${angka(kpiLayar.dokBeredar)} beredar &middot; ${angka(kpiLayar.dokBelumBeredar)} belum beredar</div>
                </div>
                <div class="kpi-box" style="border-top: 4px solid #10b981;">
                    <div class="kpi-box-lbl">Average Lead Time</div>
                    <div class="kpi-box-num text-success">${angka(kpiLayar.leadRerata)} <span style="font-size:11px;font-weight:600;">hari</span></div>
                    <div class="kpi-box-sub">Respon &rarr; terbit &middot; ${angka(kpiLayar.leadTerbit)} dari ${angka(kpiLayar.dokTotal)} dokumen sudah terbit</div>
                </div>
            </div>

            <!-- Rincian Distribusi Beban Penugasan -->
            <div class="pdf-card">
                <div class="pdf-card-title">
                    <span>Komposisi Status Penugasan Dokumen</span>
                    <span class="text-muted" style="font-weight: 500; font-size: 10px;">Total ${angka(ringkasan.total_penugasan || ringkasan.total_target)} Penugasan</span>
                </div>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Status Pelaksanaan Penugasan</th>
                            <th class="text-end">Jumlah Penugasan</th>
                            <th class="text-end">Porsi (%)</th>
                            <th>Status Kepatuhan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-bold text-success">✓ Selesai & Dikonfirmasi Lengkap (Tuntas)</td>
                            <td class="text-end font-bold text-success font-mono">${angka(ringkasan.total_selesai)}</td>
                            <td class="text-end font-bold font-mono">${ringkasan.penyelesaian_persen}%</td>
                            <td><span class="badge bg-success-subtle text-success">Sesuai Standar</span></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-warning">⏳ Sedang Berjalan & Belum Selesai (Tertunda)</td>
                            <td class="text-end font-bold text-warning font-mono">${angka(ringkasan.total_tertunda)}</td>
                            <td class="text-end font-bold font-mono">${(ringkasan.total_penugasan || ringkasan.total_target) > 0 ? Math.round((ringkasan.total_tertunda / (ringkasan.total_penugasan || ringkasan.total_target)) * 100) : 0}%</td>
                            <td><span class="badge bg-warning-subtle text-warning">Dalam Pengerjaan</span></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-danger">⚠️ Melewati Batas Waktu Deadline (Terlambat)</td>
                            <td class="text-end font-bold text-danger font-mono">${angka(ringkasan.total_terlambat)}</td>
                            <td class="text-end font-bold font-mono">${(ringkasan.total_penugasan || ringkasan.total_target) > 0 ? Math.round((ringkasan.total_terlambat / (ringkasan.total_penugasan || ringkasan.total_target)) * 100) : 0}%</td>
                            <td><span class="badge bg-danger-subtle text-danger">Perlu Tindak Lanjut</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Executive Note -->
            <div class="pdf-card" style="background: #fafbfd; border-color: #cbd5e1;">
                <div class="pdf-card-title">Catatan Tata Kelola</div>
                <p style="margin: 0; font-size: 11px; color: #475569; line-height: 1.5;">
                    Dari total <strong>${angka(ringkasan.total_target)}</strong> penugasan yang tercakup pada rentang <strong>${periode}</strong>, sebanyak <strong>${ringkasan.penyelesaian_persen}%</strong> telah diselesaikan tepat waktu. Penugasan yang mengalami keterlambatan berjumlah <strong>${angka(ringkasan.total_terlambat)}</strong> item dan memerlukan eskalasi pengingat kepada kepala unit masing-masing.
                </p>
            </div>
        </div>

        ${kakiLari(3)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 4: BAB 2 — KESEHATAN DOKUMEN (ANTREAN & LEAD TIME)
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <div class="chapter-hero" style="border-left-color: #0ea5e9;">
                <div class="ch-kicker" style="color: #0ea5e9;">BAB 2 — KESEHATAN DOKUMEN</div>
                <h1 class="ch-title">Keadaan Master Dokumen</h1>
                <p class="ch-desc">
                    Bab ini menilai DOKUMENNYA, bukan orang yang membacanya: berapa yang
                    menunggu dikerjakan, berapa lama sebuah dokumen menempuh jalan dari
                    pengajuan sampai resmi terbit, dan bagaimana sebarannya di master.
                </p>
            </div>

            <div class="kpi-row">
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Dokumen Master Aktif</div>
                    <div class="kpi-box-num text-dark">${angka(kesehatan.Komposisi?.Total || 0)}</div>
                    <div class="kpi-box-sub">Seluruh dokumen hidup di master</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Antrean Tata Kelola</div>
                    <div class="kpi-box-num text-warning">${angka(kesehatan.Antrean?.Total || 0)}</div>
                    <div class="kpi-box-sub">Temuan yang menunggu tindakan</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Rerata Lead Time</div>
                    <div class="kpi-box-num text-primary">${angka(kesehatan.Lead_Time?.Rerata_Hari || 0)}</div>
                    <div class="kpi-box-sub">hari, pengajuan sampai terbit</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Dokumen Terbit</div>
                    <div class="kpi-box-num text-success">${angka(kesehatan.Lead_Time?.Total_Dokumen_Terbit || 0)}</div>
                    <div class="kpi-box-sub">Dasar penghitungan lead time</div>
                </div>
            </div>

            <div class="pdf-card">
                <div class="pdf-card-title">Antrean Kerja Dokumen</div>
                ${(kesehatan.Antrean?.Baris || []).length
                    ? `<table>
                        <thead>
                            <tr><th>Dokumen</th><th>Keadaan</th><th class="text-end">Umur</th></tr>
                        </thead>
                        <tbody>
                            ${(kesehatan.Antrean.Baris || []).slice(0, 12).map((r) => `
                            <tr>
                                <td>
                                    <strong>${esc(r.Judul || '-')}</strong>
                                    ${r.Nomor ? `<br><span class="font-mono text-muted">${esc(r.Nomor)}</span>` : ''}
                                </td>
                                <td>
                                    ${esc(r.Nama_Jenis || r.Jenis || '-')}
                                    <br><span class="text-muted">${esc(r.Keterangan || '')}</span>
                                </td>
                                <td class="text-end font-bold">${angka(r.Umur_Hari || 0)} hr</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                    ${(kesehatan.Antrean.Baris || []).length > 12
                        ? `<p class="ch-desc" style="margin-top:6px;">Ditampilkan 12 teratas dari <strong>${angka(kesehatan.Antrean.Total || 0)}</strong> temuan; daftar lengkapnya ada di layar Monitoring.</p>`
                        : ''}`
                    : '<p class="ch-desc">Tak ada temuan tata kelola yang menunggu tindakan pada rentang ini.</p>'}
            </div>

            <div class="pdf-card">
                <div class="pdf-card-title">Lead Time Penerbitan Dokumen</div>
                <div class="kpi-row">
                    <div class="kpi-box">
                        <div class="kpi-box-lbl">Tercepat</div>
                        <div class="kpi-box-num text-success">${angka(kesehatan.Lead_Time?.Tercepat_Hari || 0)}</div>
                        <div class="kpi-box-sub">hari</div>
                    </div>
                    <div class="kpi-box">
                        <div class="kpi-box-lbl">Rerata</div>
                        <div class="kpi-box-num text-primary">${angka(kesehatan.Lead_Time?.Rerata_Hari || 0)}</div>
                        <div class="kpi-box-sub">hari</div>
                    </div>
                    <div class="kpi-box">
                        <div class="kpi-box-lbl">Terlama</div>
                        <div class="kpi-box-num text-danger">${angka(kesehatan.Lead_Time?.Terlama_Hari || 0)}</div>
                        <div class="kpi-box-sub">hari</div>
                    </div>
                </div>
                <p class="ch-desc" style="margin-top:6px;">
                    Dihitung dari tanggal pengajuan sampai dokumen resmi terbit, atas
                    <strong>${angka(kesehatan.Lead_Time?.Total_Dokumen_Terbit || 0)}</strong> dokumen yang sudah terbit.
                </p>
            </div>
        </div>

        ${kakiLari(4)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 5: BAB 2 (LANJUTAN) — SEBARAN DOKUMEN MASTER
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <div class="chapter-hero" style="border-left-color: #0ea5e9;">
                <div class="ch-kicker" style="color: #0ea5e9;">BAB 2 (LANJUTAN) — SEBARAN DOKUMEN MASTER</div>
                <h1 class="ch-title">Sebaran Dokumen Master</h1>
                <p class="ch-desc">
                    Lima sumbu yang sama dengan pemilih di layar. Di kertas seluruhnya
                    dicetak sekaligus, karena pembaca berkas tak bisa menekan pemilihnya.
                </p>
            </div>

            ${(kesehatan.Sebaran?.Sumbu || []).map((sumbu) => {
                const isi = kesehatan.Sebaran?.Data?.[sumbu.Kunci];
                const barisSumbu = (isi?.Baris || []).slice(0, 10);
                if (!barisSumbu.length) return '';

                const maksSumbu = Math.max(...barisSumbu.map((r) => Number(r.Total) || 0), 1);

                return `
                <div class="pdf-card">
                    <div class="pdf-card-title">${esc(sumbu.Nama || sumbu.Kunci)}</div>
                    ${legendaSebaran}
                    ${barisSumbu.map((r) => barisBatang(
                        r.Nama || '-',
                        r.Label || '',
                        [
                            { kelas: 'is-tugas', nilai: Number(r.Fungsional) || 0 },
                            { kelas: 'is-edar', nilai: Number(r.Publik) || 0 },
                            { kelas: 'is-belum', nilai: Number(r.Belum_Beredar) || 0 },
                        ],
                        Number(r.Total) || 0,
                        maksSumbu,
                    )).join('')}
                    ${(isi?.Baris || []).length > 10
                        ? `<p class="ch-desc" style="margin-top:4px;">Sepuluh teratas dari ${angka((isi?.Baris || []).length)} baris.</p>`
                        : ''}
                </div>`;
            }).join('')}
        </div>

        ${kakiLari(5)}
    </div>



    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 6: BAB 3 — TITIK MACET & PERINGKAT UNIT KERJA
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <!-- Chapter 3 Hero -->
            <div class="chapter-hero" style="border-left-color: #f59e0b;">
                <div class="ch-kicker" style="color: #f59e0b;">BAB 3 — TITIK MACET & PERINGKAT UNIT KERJA</div>
                <h1 class="ch-title">Peringkat Kepatuhan Unit Kerja & Departemen</h1>
                <p class="ch-desc">
                    Evaluasi komparatif tingkat kepatuhan antar divisi/departemen untuk mengidentifikasi unit teladan serta unit yang membutuhkan intervensi.
                </p>
            </div>

            <!-- Unit Ranking Table -->
            <div class="pdf-card">
                <div class="pdf-card-title">
                    <span>Tabel Peringkat Kepatuhan Unit Kerja</span>
                    <span class="text-muted" style="font-size: 10px;">Diurutkan Berdasarkan Persentase Kepatuhan & Keterlambatan</span>
                </div>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">Rank</th>
                            <th>Nama Unit / Divisi</th>
                            <th class="text-end">Target</th>
                            <th class="text-end text-success">Tuntas</th>
                            <th class="text-end text-danger">Terlambat</th>
                            <th class="text-end" style="min-width: 140px;">Kepatuhan (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${barisPeringkat || '<tr><td colspan="6" class="text-center text-muted">Tidak ada data peringkat unit pada rentang ini</td></tr>'}
                    </tbody>
                </table>
            </div>

            <!-- Recommendations Card -->
            <div class="pdf-card" style="background: #fffbeb; border-color: #fde68a;">
                <div class="pdf-card-title" style="color: #92400e; border-bottom-color: #fef3c7;">Rekomendasi Tindak Lanjut</div>
                <p style="margin: 0; font-size: 11px; color: #78350f; line-height: 1.5;">
                    Unit kerja dengan tingkat kepatuhan di bawah <strong>60%</strong> atau memiliki rasio keterlambatan signifikan disarankan menerima notifikasi eskalasi penugasan langsung kepada Kepala Divisi/Manajer terkait guna mencegah backlog kepatuhan dokumen operasional.
                </p>
            </div>
        </div>

        ${kakiLari(6)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 7: BAB 4 — KUALITAS PENYERAPAN BACAAN
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <!-- Chapter 5 Hero -->
            <div class="chapter-hero" style="border-left-color: #10b981;">
                <div class="ch-kicker" style="color: #10b981;">BAB 4 — KUALITAS PENYERAPAN BACAAN</div>
                <h1 class="ch-title">Dibaca, atau Sekadar Dikonfirmasi?</h1>
                <p class="ch-desc">
                    Memastikan dokumen bukan sekadar diklik selesai sebagai formalitas, melainkan benar-benar dibaca dan diserap.
                </p>
            </div>

            <div class="pdf-card">
                <div class="pdf-card-title"><span>1. Berapa Lama Dokumen Benar-benar Dibaca</span></div>
                ${this.gambarAtauCatatan('baca', 'Belum ada durasi baca yang terekam pada rentang ini.')}
                <div class="rpt-fig-cap">
                    <strong>Cara membacanya.</strong> Sumbu mendatar adalah kelompok lama membaca; batang yang menumpuk
                    di kelompok terpendek berarti dokumen dikonfirmasi lebih cepat daripada mungkin dibaca.
                    Rata-rata tercatat <strong>${durasi(pemahaman?.baca?.rerata_detik || 0)}</strong> per materi.
                </div>
            </div>

            <div class="pdf-card">
                <div class="pdf-card-title"><span>2. Jarak Dibuka hingga Dikonfirmasi</span></div>
                ${this.gambarAtauCatatan('waktu', 'Belum ada konfirmasi tuntas yang durasinya terekam.')}
                <div class="rpt-fig-cap">
                    <strong>Perhatikan batang merah.</strong> Itu penugasan yang dikonfirmasi kurang dari satu jam sejak
                    dibuka. Bukan prestasi — pada dokumen sepanjang SOP, itu pertanda konfirmasi ditekan tanpa dibaca.
                </div>
            </div>

            <!-- 2 Columns: Reading Quality & Quiz Performance -->
            <div class="grid-2col mb-3">
                <!-- Kualitas Waktu Baca -->
                <div class="pdf-card">
                    <div class="pdf-card-title">
                        <span>1. Kualitas Waktu Membaca</span>
                        <span class="text-primary font-bold font-mono">${durasi(pemahaman?.baca?.rerata_detik || 0)}</span>
                    </div>
                    <p style="font-size: 10.5px; color: #64748b; margin-bottom: 8px;">
                        Rata-rata waktu membaca dokumen tercatat <strong>${durasi(pemahaman?.baca?.rerata_detik || 0)}</strong> per materi penugasan.
                    </p>
                    <table class="pdf-table">
                        <thead>
                            <tr>
                                <th>Distribusi Durasi</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-end">Porsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${barisWaktu || '<tr><td colspan="3" class="text-center text-muted">Belum ada data durasi</td></tr>'}
                        </tbody>
                    </table>
                </div>

                <!-- Evaluasi Pemahaman Kuis -->
                <div class="pdf-card">
                    <div class="pdf-card-title">
                        <span>2. Evaluasi Pemahaman Kuis</span>
                        <span class="text-success font-bold font-mono">${pemahaman?.kuis?.rerata_nilai ?? '—'} / 100</span>
                    </div>
                    <p style="font-size: 10.5px; color: #64748b; margin-bottom: 8px;">
                        Hasil pengujian pemahaman karyawan melalui modul kuis interaktif dokumen.
                    </p>
                    <table class="pdf-table">
                        <tbody>
                            <tr>
                                <td class="font-bold">Rata-rata Skor Kuis Karyawan</td>
                                <td class="text-end font-bold text-success font-mono">${pemahaman?.kuis?.rerata_nilai ?? '—'} / 100</td>
                            </tr>
                            <tr>
                                <td class="font-bold">Tingkat Kelulusan Pertama Kali</td>
                                <td class="text-end font-bold text-primary font-mono">${pemahaman?.kuis?.lulus_pertama ?? 0}%</td>
                            </tr>
                            <tr>
                                <td class="font-bold">Karyawan Perlu Remediasi</td>
                                <td class="text-end font-bold text-danger font-mono">${angka(pemahaman?.kuis?.butuh_remedi || 0)} orang</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        ${kakiLari(7)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 8: BAB 4 (LANJUTAN) — SEBARAN KEPATUHAN PER DEPARTEMEN
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <!-- Chapter 6 Hero -->
            <div class="chapter-hero" style="border-left-color: #0d9488;">
                <div class="ch-kicker" style="color: #0d9488;">BAB 4 (LANJUTAN) — SEBARAN KEPATUHAN PER DEPARTEMEN</div>
                <h1 class="ch-title">Sebaran Kepatuhan per Departemen</h1>
                <p class="ch-desc">
                    Matriks kepatuhan lintas unit dan kategori. Sel kosong berarti belum ada penugasan pada irisan itu — bukan nol persen.
                </p>
            </div>

            <div class="pdf-card">
                <div class="pdf-card-title"><span>1. Matriks Penyelesaian per Departemen</span></div>
                ${this.gambarAtauCatatan('heatmap', 'Belum ada penugasan yang bisa dipetakan pada rentang ini.')}
                <div class="rpt-fig-cap">
                    <strong>Cara membacanya.</strong> Semakin hijau, semakin tuntas. Baris yang merah dari ujung ke ujung
                    adalah masalah unit; kolom yang merah dari atas ke bawah adalah masalah kategori dokumennya — dan
                    keduanya menuntut tindakan yang sama sekali berbeda.
                </div>
            </div>

            <!-- Matriks Sebaran Departemen x Sumbu Terpilih -->
            <div class="pdf-card">
                <div class="pdf-card-title">
                    <span>2. Rincian Angka Matriks</span>
                    <span class="text-muted" style="font-size: 10px;">Sebaran Kepatuhan Lintas Unit</span>
                </div>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Departemen</th>
                            ${headerHeatmap || '<th class="text-center">Kolom</th>'}
                        </tr>
                    </thead>
                    <tbody>
                        ${barisHeatmap || '<tr><td colspan="4" class="text-center text-muted">Tidak ada data matriks pada rentang ini</td></tr>'}
                    </tbody>
                </table>
            </div>

            <!-- Sign-off Sheet -->
            <div style="margin-top: 10px; padding: 10px 14px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 10.5px; color: #475569;">
                <div>
                    <strong>Disiapkan Oleh:</strong><br>
                    ${pencetak.nama}${pencetak.divisi ? `<br><span style="color:#64748b;">${pencetak.divisi}</span>` : ''}
                </div>
                <div style="text-align: right;">
                    <strong>Status Dokumen:</strong><br>
                    <span class="badge bg-success-subtle text-success">Terverifikasi Sistem</span>
                </div>
            </div>
        </div>

        ${kakiLari(8)}
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         HALAMAN 9: BAB 5 — JANGKAUAN DOKUMEN PUBLIK
         ══════════════════════════════════════════════════════════════ -->
    <div class="pdf-page">
        <div>
            ${kepalaLari}

            <div class="chapter-hero" style="border-left-color: #7c3aed;">
                <div class="ch-kicker" style="color: #7c3aed;">BAB 5 — JANGKAUAN DOKUMEN PUBLIK</div>
                <h1 class="ch-title">Jangkauan Dokumen Publik</h1>
                <p class="ch-desc">
                    Dokumen publik terbuka bagi semua karyawan tanpa penugasan. Bab ini
                    mengukur MINAT, bukan kepatuhan: berapa orang yang benar-benar
                    membukanya. Angka rendah di sini bukan pelanggaran — ia pertanda
                    dokumennya belum dikenal.
                </p>
            </div>

            ${(jangkauan.Jangkauan?.Dokumen_Publik || 0) === 0
                ? '<div class="pdf-card"><p class="ch-desc">Belum ada dokumen publik yang beredar pada rentang ini, jadi tak ada jangkauan yang bisa diukur.</p></div>'
                : `
            <div class="kpi-row">
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Dokumen Publik</div>
                    <div class="kpi-box-num text-dark">${angka(jangkauan.Jangkauan?.Dokumen_Publik || 0)}</div>
                    <div class="kpi-box-sub">Terbuka bagi semua karyawan</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Karyawan Aktif</div>
                    <div class="kpi-box-num text-slate">${angka(jangkauan.Jangkauan?.Karyawan_Aktif || 0)}</div>
                    <div class="kpi-box-sub">Populasi yang bisa membuka</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Pernah Membuka</div>
                    <div class="kpi-box-num text-primary">${angka(jangkauan.Jangkauan?.Membuka || 0)}</div>
                    <div class="kpi-box-sub">Orang, bukan jumlah bukaan</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-box-lbl">Jangkauan</div>
                    <div class="kpi-box-num text-success">${angka(jangkauan.Jangkauan?.Persen || 0)}%</div>
                    <div class="kpi-box-sub">Dari seluruh karyawan aktif</div>
                </div>
            </div>

            ${(jangkauan.Terpopuler || []).length
                ? `<div class="pdf-card">
                    <div class="pdf-card-title">Dokumen Publik Paling Banyak Dibuka</div>
                    ${(() => {
                        const daftar = (jangkauan.Terpopuler || []).slice(0, 10);
                        const maks = Math.max(...daftar.map((d) => Number(d.Pembuka) || 0), 1);

                        return daftar.map((d) => barisBatang(
                            d.Judul || '-',
                            d.Nomor || '',
                            [{ kelas: 'is-tunggal', nilai: Number(d.Pembuka) || 0 }],
                            Number(d.Pembuka) || 0,
                            maks,
                        )).join('');
                    })()}
                    <p class="ch-desc" style="margin-top:4px;">Angka di kanan adalah jumlah ORANG yang membuka, bukan jumlah bukaan.</p>
                </div>`
                : ''}

            ${(jangkauan.Unit || []).length
                ? `<div class="pdf-card">
                    <div class="pdf-card-title">Unit Kerja dengan Pembaca Terbanyak</div>
                    ${(() => {
                        const daftar = (jangkauan.Unit || []).slice(0, 10);
                        const maks = Math.max(...daftar.map((u) => Number(u.Orang) || 0), 1);

                        return daftar.map((u) => barisBatang(
                            u.Unit || '-',
                            '',
                            [{ kelas: 'is-tunggal', nilai: Number(u.Orang) || 0 }],
                            Number(u.Orang) || 0,
                            maks,
                        )).join('');
                    })()}
                </div>`
                : ''}`}
        </div>

        ${kakiLari(9)}
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 350);
        };
    <\/script>
</body>
</html>
                `;

                const win = window.open('', '_blank');
                if (win) {
                    win.document.open();
                    win.document.write(this.denganTokenDesain(html));
                    win.document.close();
                }
            } finally {
                this.sedangUnduh = false;
            }
        },
        /**
         * Sisipkan definisi token desain ke dokumen laporan yang berdiri sendiri.
         *
         * KENAPA PERLU. Laporan ini ditulis ke jendela BARU lewat
         * document.write, sehingga ia tidak mewarisi satu baris CSS pun dari
         * aplikasi — termasuk :root milik design-system.css. Padahal gayanya
         * memakai token itu 97 kali pada 33 nama berbeda. Setiap
         * `background: var(--hcis-white)` yang tokennya tak terdefinisi menjadi
         * deklarasi BATAL, dan latar yang batal berarti transparan: laporan
         * tercetak nyaris tanpa warna sementara teksnya tetap muncul, sehingga
         * gejalanya terbaca seperti "style-nya hilang" alih-alih "tokennya tak
         * ikut". Setelan cetaknya sendiri tidak bersalah — print-color-adjust
         * sudah exact.
         *
         * DIPUNGUT, BUKAN DISALIN. Nilainya dibaca dari dokumen yang sedang
         * hidup, jadi ia otomatis mengikuti tema yang sedang berlaku dan tak
         * pernah menyimpang dari design-system. Menyalin 33 nilai ke sini akan
         * melahirkan daftar kedua — dan daftar kedua yang menyimpang di sini
         * berarti laporan resmi tercetak dengan warna yang bukan warna sistem.
         *
         * Nama tokennya pun tidak diketik ulang: ia dipanen dari CSS laporan
         * itu sendiri, sehingga token yang kelak ditambahkan ke templat ikut
         * terbawa tanpa ada yang perlu ingat menyusulkannya di sini.
         */
        denganTokenDesain(html) {
            const namaSet = new Set((html.match(/var\(\s*(--[a-z0-9-]+)/gi) || [])
                .map((m) => m.replace(/var\(\s*/i, '')));
            if (!namaSet.size) return html;

            const gaya = getComputedStyle(document.documentElement);
            const hasilToken = new Map();

            const prosesToken = (n) => {
                if (hasilToken.has(n)) return;
                const v = (gaya.getPropertyValue(n) || '').trim();
                if (v !== '') {
                    hasilToken.set(n, v);
                    // Periksa apakah nilainya mereferensikan variabel CSS lain (misal: rgb(var(--hcis-slate-900-rgb)))
                    const subTokens = (v.match(/var\(\s*(--[a-z0-9-]+)/gi) || [])
                        .map((m) => m.replace(/var\(\s*/i, ''));
                    for (const sub of subTokens) {
                        prosesToken(sub);
                    }
                    // Sertakan pula token pendamping -rgb jika tersedia pada dokumen
                    if (!n.endsWith('-rgb')) {
                        const rgbName = `${n}-rgb`;
                        const rgbVal = (gaya.getPropertyValue(rgbName) || '').trim();
                        if (rgbVal !== '') {
                            hasilToken.set(rgbName, rgbVal);
                        }
                    }
                }
            };

            for (const n of namaSet) {
                prosesToken(n);
            }

            if (!hasilToken.size) return html;

            const baris = Array.from(hasilToken.entries()).map(([n, v]) => `${n}: ${v};`);
            const blok = `<style id="hcis-token-laporan">:root{${baris.join('')}}</style>`;

            /* Disisipkan tepat sesudah <head> supaya ia mendahului seluruh
               aturan laporan — token harus sudah ada sebelum yang memakainya. */
            return html.includes('<head>')
                ? html.replace('<head>', `<head>${blok}`)
                : blok + html;
        },
        unduhCsv() {
            this.sedangUnduh = true;
            try {
                const periode = this.labelRentang || 'Semua Waktu (Seluruh Riwayat)';
                const waktuCetak = new Date().toLocaleString('id-ID');
                const ringkasan = this.dataRingkasan;
                const peringkat = this.rawPeringkat;
                const aliran = Array.isArray(this.rawMomentum?.deret) ? this.rawMomentum.deret : [];
                const pemahaman = this.rawPemahaman;

                const lines = [];
                // Metadata
                lines.push(['HCIS - DOCUMENT MANAGEMENT SYSTEM - LAPORAN RINGKASAN MONITORING DOKUMEN']);
                lines.push(['Waktu Generate', `"${waktuCetak}"`]);
                lines.push(['Filter Rentang Periode', `"${periode}"`]);
                lines.push([]);

                // Section 1: KPI Summary
                lines.push(['1. RINGKASAN METRIK KPI UTAMA (BAB 1)']);
                lines.push(['Metrik', 'Nilai', 'Satuan']);
                lines.push(['Tingkat Penyelesaian', ringkasan.penyelesaian_persen, '%']);
                lines.push(['Total Target Karyawan', ringkasan.total_target, 'Karyawan']);
                lines.push(['Total Penugasan Selesai (Tuntas)', ringkasan.total_selesai, 'Penugasan']);
                lines.push(['Total Penugasan Tertunda', ringkasan.total_tertunda, 'Penugasan']);
                lines.push(['Total Penugasan Terlambat', ringkasan.total_terlambat, 'Penugasan']);
                lines.push(['Total Paket Dokumen Aktif', ringkasan.total_paket, 'Paket']);
                lines.push([]);

                // Section 2: Weekly Inflow & Completion
                lines.push(['2. ALIRAN MASUK VS TUNTAS PER MINGGU (BAB 2)']);
                lines.push(['Periode Minggu', 'Penugasan Masuk', 'Penugasan Tuntas', 'Netto/Selisih']);
                aliran.forEach((a) => {
                    const label = a.label || a.minggu || '';
                    const masuk = a.masuk || 0;
                    const selesai = a.selesai || 0;
                    const selisih = a.selisih ?? (selesai - masuk);
                    lines.push([
                        `"${label.replace(/"/g, '""')}"`,
                        masuk,
                        selesai,
                        selisih,
                    ]);
                });
                lines.push([]);

                // Section 3: Unit Compliance Rankings
                lines.push(['3. PERINGKAT KEPATUHAN UNIT KERJA (BAB 3)']);
                lines.push(['No', 'Unit / Divisi', 'Total Target', 'Tuntas', 'Terlambat', 'Kepatuhan (%)']);
                peringkat.forEach((u, idx) => {
                    const unitName = u.unit || u.nama || '';
                    const target = u.orang ?? u.total ?? u.total_target ?? u.target ?? 0;
                    const selesai = u.selesai ?? u.tuntas ?? u.total_selesai ?? 0;
                    const terlambat = u.terlambat ?? u.total_terlambat ?? 0;
                    const persen = u.persen ?? u.persen_selesai ?? (target > 0 ? Math.round((selesai / target) * 100) : 0);

                    lines.push([
                        idx + 1,
                        `"${unitName.replace(/"/g, '""')}"`,
                        target,
                        selesai,
                        terlambat,
                        `${persen}%`,
                    ]);
                });
                lines.push([]);

                // Section 4: Reading & Quiz Quality
                lines.push(['4. EVALUASI KUALITAS BACA & PEMAHAMAN KUIS (BAB 4)']);
                lines.push(['Indikator Kualitas', 'Hasil']);
                lines.push(['Rata-rata Durasi Membaca Dokumen', `"${durasi(pemahaman?.baca?.rerata_detik || 0)}"`]);
                lines.push(['Rata-rata Skor Kuis', `${pemahaman?.kuis?.rerata_nilai ?? '—'} / 100`]);
                lines.push(['Tingkat Kelulusan Pertama Kali', `${pemahaman?.kuis?.lulus_pertama ?? 0}%`]);
                lines.push(['Karyawan Perlu Remediasi', `${pemahaman?.kuis?.butuh_remedi || 0} orang`]);

                const csvContent = '\uFEFF' + lines.map((e) => e.join(',')).join('\r\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const tglFile = new Date().toISOString().slice(0, 10);
                a.href = url;
                a.download = `rekap-ringkasan-sop-${tglFile}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } finally {
                this.sedangUnduh = false;
            }
        },
    },
};
</script>

<style scoped>
.rk-export-wrap {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 16px);
}

.rk-export-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
}

.rk-export-banner__title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.rk-export-banner__desc {
    font-size: 0.9375rem;
    color: var(--hcis-slate-900);
}

.rk-export-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    text-align: center;
}

.rk-export-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    border-radius: 12px;
    border: 2px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
}

.rk-export-card:hover {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.rk-export-card--active {
    border-color: var(--hcis-indigo-600);
    background: #f5f7ff;
}

.rk-export-card__icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.rk-export-card__body {
    flex: 1;
}

.rk-export-card__title {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    margin-bottom: 4px;
}

.rk-export-card__desc {
    font-size: 0.8125rem;
    color: var(--hcis-slate-500);
    margin-bottom: 0;
    line-height: 1.4;
}

.rk-export-card__check {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.rk-export-preview {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 14px;
}

.rk-export-preview__title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-700);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.rk-export-stat {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 8px 10px;
    text-align: center;
}

.rk-export-stat__num {
    font-size: 1.125rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 2px;
}

.rk-export-stat__lbl {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
}

@media (min-width: 768px) {
    .col-md-2-4 {
        flex: 0 0 auto;
        width: 20%;
    }
}
</style>
