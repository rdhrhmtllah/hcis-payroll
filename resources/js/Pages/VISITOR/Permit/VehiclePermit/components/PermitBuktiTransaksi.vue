<!--
    Bukti Pengajuan Permit Kendaraan — dokumen CETAK.

    Komponen ini tidak pernah terlihat di layar. Ia dirender ke <body> lewat
    Teleport, disembunyikan dengan display:none, dan hanya muncul di dalam
    @media print. Alasannya: laporan butuh tata letak kertas (kop, tabel penuh,
    pemenggalan halaman) yang tidak ada hubungannya dengan tampilan modal, dan
    menempelkannya ke PermitStatusList.vue — yang sudah 2.700 baris — hanya
    membuat berkas itu makin sulit dibaca.

    Sengaja TIDAK memakai komponen kit HCIS maupun ikon: glif icon-font sering
    tidak ikut tercetak, dan gaya layar (bayangan, kaca, gradasi) hanya
    memboroskan tinta. Semuanya HTML polos dengan gaya cetak sendiri.

    Aturan @media print-nya WAJIB non-scoped karena harus menyembunyikan seluruh
    isi <body> milik aplikasi. Seluruh aturan itu terkurung di dalam
    @media print dan dinamai di bawah .permit-bukti-print, jadi tidak ada satu
    pun yang berlaku di layar.
-->
<template>
    <Teleport to="body">
        <div class="permit-bukti-print">
            <header class="bukti-kop">
                <img v-if="logoUrl" class="bukti-kop__logo" :src="logoUrl" alt="" >
                <div class="bukti-kop__identitas">
                    <div class="bukti-kop__perusahaan">{{ namaPerusahaan }}</div>
                    <div class="bukti-kop__sistem">Human Capital Information System</div>
                </div>
            </header>

            <div class="bukti-judul-blok">
                <h1 class="bukti-judul">BUKTI PENGAJUAN PERMIT KENDARAAN</h1>
                <table class="bukti-judul-meta">
                    <tbody>
                        <tr>
                            <th scope="row">No. Transaksi</th>
                            <td class="bukti-tabular">{{ nilai(permit.No_Transaksi) }}</td>
                            <th scope="row">Tanggal Pengajuan</th>
                            <td>{{ tanggalJam(permit.Tanggal_Pengajuan) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Status Pengajuan</th>
                            <td>{{ labelStatus }}</td>
                            <th scope="row">Tanggal Cetak</th>
                            <td>{{ dicetakPada }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!--
                Pencabutan tidak mengubah Flag_Approval, jadi permit yang sudah
                dicabut tetap terbaca "disetujui" di sebagian besar kolom. Bukti
                yang mencetaknya tanpa peringatan ini akan dipakai orang untuk
                masuk gerbang dengan akses yang sudah dihentikan.
            -->
            <section v-if="dicabut" class="bukti-bagian bukti-bagian--cabut">
                <h2 class="bukti-bagian__judul">Akses Telah Dicabut</h2>
                <table class="bukti-tabel-data">
                    <tbody>
                        <tr>
                            <th scope="row">Alasan Pencabutan</th>
                            <td>{{ nilai(permit.Alasan_Pencabutan) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Dicabut Oleh</th>
                            <td>{{ nilai(permit.Dicabut_Oleh) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Dicabut Pada</th>
                            <td>{{ tanggalJam(permit.Dicabut_Pada) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">A. Data Pemohon</h2>
                <table class="bukti-tabel-data">
                    <tbody>
                        <tr>
                            <th scope="row">Nama Pemohon</th>
                            <td>{{ nilai(permit.Nama_Pemohon) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Nomor Induk Karyawan</th>
                            <td class="bukti-tabular">{{ nilai(permit.NIK) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Kode Karyawan</th>
                            <td class="bukti-tabular">{{ nilai(permit.Kode_Karyawan_Pemohon) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Departemen</th>
                            <td>{{ nilai(permit.Departemen) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Jabatan</th>
                            <td>{{ nilai(permit.Jabatan) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">B. Data Kendaraan</h2>
                <table class="bukti-tabel-data">
                    <tbody>
                        <tr>
                            <th scope="row">Nomor Plat</th>
                            <td class="bukti-tabular bukti-tegas">{{ nilai(permit.No_Plat_Kendaraan) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Jenis Kendaraan</th>
                            <td>{{ nilai(permit.Nama_Vehicle_Type) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Model</th>
                            <td>{{ nilai(permit.Nama_Model_Manual) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Warna</th>
                            <td>{{ nilai(permit.Warna_Manual) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Tahun</th>
                            <td class="bukti-tabular">{{ nilai(permit.Tahun_Kendaraan) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">C. Izin &amp; Masa Berlaku</h2>
                <table class="bukti-tabel-data">
                    <tbody>
                        <tr>
                            <th scope="row">Zona / Kategori</th>
                            <td>{{ nilai(permit.Nama_Kategori || permit.Snapshot_Kategori_Permit) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Cakupan Akses</th>
                            <td>{{ nilai(permit.Deskripsi_Kategori) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Durasi</th>
                            <td>{{ nilai(permit.Snapshot_Nama_Durasi || permit.Nama_Durasi) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Berlaku Mulai</th>
                            <td>{{ tanggal(permit.Tanggal_Mulai_Berlaku || permit.Tanggal_Berlaku) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Berlaku Sampai</th>
                            <td>{{ tanggal(permit.Tanggal_Kadaluarsa) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Keperluan</th>
                            <td>{{ nilai(permit.Keterangan_Pengajuan || permit.Keterangan) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">D. Riwayat Persetujuan</h2>
                <p v-if="!barisApproval.length" class="bukti-kosong">Tidak ada tahap persetujuan yang tercatat.</p>
                <table v-else class="bukti-tabel-daftar">
                    <thead>
                        <tr>
                            <th scope="col" class="bukti-kolom-urut">No.</th>
                            <th scope="col">Tahap</th>
                            <th scope="col">Approver</th>
                            <th scope="col">Keputusan</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="baris in barisApproval" :key="baris.kunci">
                            <td class="bukti-kolom-urut bukti-tabular">{{ baris.urutan }}</td>
                            <td>{{ baris.tahap }}</td>
                            <td>{{ baris.approver }}</td>
                            <td>{{ baris.keputusan }}</td>
                            <td>{{ baris.tanggal }}</td>
                            <td>{{ baris.catatan }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">E. Checklist Pemeriksaan</h2>
                <p v-if="!barisChecklist.length" class="bukti-kosong">Tidak ada checklist pemeriksaan untuk pengajuan ini.</p>
                <table v-else class="bukti-tabel-daftar">
                    <thead>
                        <tr>
                            <th scope="col" class="bukti-kolom-urut">No.</th>
                            <th scope="col">Pertanyaan</th>
                            <th scope="col">Jawaban</th>
                            <th scope="col">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="baris in barisChecklist" :key="baris.kunci">
                            <td class="bukti-kolom-urut bukti-tabular">{{ baris.urutan }}</td>
                            <td>{{ baris.pertanyaan }}</td>
                            <td>{{ baris.jawaban }}</td>
                            <td>{{ baris.catatan }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="bukti-bagian">
                <h2 class="bukti-bagian__judul">F. Dokumen Terlampir</h2>
                <p v-if="!barisDokumen.length" class="bukti-kosong">Tidak ada berkas dokumen terlampir.</p>
                <table v-else class="bukti-tabel-daftar">
                    <thead>
                        <tr>
                            <th scope="col" class="bukti-kolom-urut">No.</th>
                            <th scope="col">Jenis Dokumen</th>
                            <th scope="col">Nama Berkas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="baris in barisDokumen" :key="baris.kunci">
                            <td class="bukti-kolom-urut bukti-tabular">{{ baris.urutan }}</td>
                            <td>{{ baris.jenis }}</td>
                            <td>{{ baris.berkas }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <footer class="bukti-footer">
                <p>
                    Dicetak dari HCIS pada {{ dicetakPada }}<template v-if="dicetakOleh"> oleh {{ dicetakOleh }}</template>.
                </p>
                <p>
                    Dokumen ini dihasilkan otomatis oleh sistem. Keabsahan persetujuan mengacu pada riwayat
                    persetujuan yang tercantum di bagian D, bukan pada tanda tangan pada lembar ini.
                </p>
            </footer>
        </div>
    </Teleport>
</template>

<script>
const KOSONG = '-';

export default {
    name: 'PermitBuktiTransaksi',
    props: {
        permit: {
            type: Object,
            required: true,
        },
        approvals: {
            type: Array,
            default: () => [],
        },
        checklists: {
            type: Array,
            default: () => [],
        },
        dokumens: {
            type: Array,
            default: () => [],
        },
        /** Nama yang dicetak di baris "Dicetak ... oleh". Boleh kosong. */
        dicetakOleh: {
            type: String,
            default: '',
        },
        /**
         * Nama perusahaan pada kop. Diletakkan sebagai prop dengan nilai baku
         * supaya penggantiannya satu baris di satu tempat, bukan berburu string
         * di dalam markup.
         */
        namaPerusahaan: {
            type: String,
            default: 'PT Evonusa Bersaudara',
        },
        logoUrl: {
            type: String,
            default: '/logo/logo.png',
        },
    },
    computed: {
        labelStatus() {
            const peta = {
                APPROVED: 'Disetujui',
                PENDING: 'Menunggu Persetujuan',
                REJECTED: 'Ditolak',
                REVOKED: 'Akses Dicabut',
                EXPIRED: 'Kedaluwarsa',
                VALIDITY_REQUIRED: 'Perlu Validitas',
                NO_FLOW: 'Tanpa Alur Approval',
            };

            return peta[this.permit.Status_Permit] || this.nilai(this.permit.Status_Permit);
        },
        dicabut() {
            return this.permit.Status_Permit === 'REVOKED' || this.permit.Status_Efektif === 'DIBATALKAN';
        },
        dicetakPada() {
            return this.tanggalJam(new Date());
        },
        barisApproval() {
            return (this.approvals || []).map((step, idx) => ({
                kunci: step.Id_Approval_Request ?? idx,
                urutan: step.Request_Order_Flow ?? idx + 1,
                tahap: this.nilai(step.Nama_Approval),
                approver: this.nilai(step.Nama_Approver),
                keputusan: this.keputusan(step),
                tanggal: step.Flag_Approval ? this.tanggalJam(step.Tanggal_Approval || step.Updated_At) : KOSONG,
                catatan: this.nilai(step.Catatan),
            }));
        },
        barisChecklist() {
            return (this.checklists || []).map((item, idx) => ({
                kunci: item.Id_Permit_Checklist ?? idx,
                urutan: idx + 1,
                pertanyaan: this.nilai(item.Pertanyaan || item.Snapshot_Pertanyaan),
                jawaban: this.nilai(item.Jawaban || item.Jawaban_Admin),
                catatan: this.nilai(item.Catatan || item.Catatan_Admin),
            }));
        },
        barisDokumen() {
            return (this.dokumens || []).map((doc, idx) => ({
                kunci: doc.Id_Permit_Dokumen ?? idx,
                urutan: idx + 1,
                jenis: this.nilai(doc.Jenis_Dokumen),
                berkas: this.nilai(doc.File_Name || this.namaBerkas(doc.Path_File_URL)),
            }));
        },
    },
    methods: {
        /**
         * Satu-satunya jalan mencetak. Judul dokumen ditukar sementara supaya
         * nama berkas PDF hasil "Save as PDF" terbaca, lalu dikembalikan apa pun
         * yang terjadi — judul tab yang tertinggal berubah adalah jejak yang
         * tidak akan pernah dibersihkan siapa pun.
         */
        cetak() {
            const judulAsli = document.title;
            const nomor = this.permit.No_Transaksi || 'Permit';

            document.title = `Bukti Permit ${nomor}`;

            try {
                window.print();
            } finally {
                document.title = judulAsli;
            }
        },
        nilai(v) {
            if (v === null || v === undefined) return KOSONG;

            const teks = String(v).trim();

            return teks === '' ? KOSONG : teks;
        },
        keputusan(step) {
            if (step.Flag_Approval === 'Y') return 'Disetujui';
            if (step.Flag_Approval === 'T') return 'Ditolak';

            const kedaluwarsa = step.Is_Expired === 1
                || (step.Expired_At && new Date(step.Expired_At) < new Date());

            return kedaluwarsa ? 'Kedaluwarsa' : 'Menunggu';
        },
        namaBerkas(path) {
            if (!path) return '';

            return String(path).split('/').pop();
        },
        tanggal(v) {
            if (!v) return KOSONG;

            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return this.nilai(v);

            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        },
        tanggalJam(v) {
            if (!v) return KOSONG;

            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return this.nilai(v);

            return `${d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
        },
    },
};
</script>

<!--
    NON-SCOPED dengan sengaja: aturan di bawah harus menyembunyikan seluruh anak
    <body> milik aplikasi, yang mustahil dijangkau selektor ber-scope. Seluruh
    isinya terkurung di dalam @media print, jadi nol pengaruh di layar.
-->
<style>
.permit-bukti-print {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 14mm 12mm;
    }

    /* Hanya lembar bukti yang tercetak. Modal, shell, dan overlay Element Plus
       ikut tersingkir karena semuanya anak langsung <body>. */
    body > *:not(.permit-bukti-print) {
        display: none !important;
    }

    body {
        background: var(--hcis-white) !important;
    }

    .permit-bukti-print {
        display: block !important;
        position: static !important;
        width: 100%;
        color: var(--hcis-black);
        font-family: "Times New Roman", Times, serif;
        font-size: 10.5pt;
        line-height: 1.45;
    }

    .permit-bukti-print table {
        width: 100%;
        border-collapse: collapse;
    }

    /* Kepala tabel diulang di tiap halaman; daftar approval yang panjang tidak
       boleh menyisakan halaman berisi angka tanpa nama kolom. */
    .permit-bukti-print thead {
        display: table-header-group;
    }

    .permit-bukti-print tr {
        break-inside: avoid;
    }

    .bukti-kop {
        display: flex;
        align-items: center;
        gap: 10mm;
        padding-bottom: 3mm;
        border-bottom: 2px solid var(--hcis-black);
    }

    .bukti-kop__logo {
        height: 16mm;
        width: auto;
    }

    .bukti-kop__perusahaan {
        font-size: 15pt;
        font-weight: bold;
        letter-spacing: 0.02em;
    }

    .bukti-kop__sistem {
        font-size: 9.5pt;
    }

    .bukti-judul-blok {
        margin-top: 6mm;
    }

    .bukti-judul {
        margin: 0 0 3mm;
        font-size: 12.5pt;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.04em;
    }

    .bukti-judul-meta th,
    .bukti-judul-meta td {
        border: 1px solid var(--hcis-black);
        padding: 1.5mm 2mm;
        text-align: left;
        vertical-align: top;
    }

    .bukti-judul-meta th {
        width: 26mm;
        font-weight: bold;
        background: #f0f0f0;
    }

    .bukti-bagian {
        margin-top: 5mm;
        break-inside: avoid;
    }

    .bukti-bagian__judul {
        margin: 0 0 2mm;
        font-size: 10.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 1px solid var(--hcis-black);
        padding-bottom: 1mm;
    }

    /* Blok pencabutan sengaja diberi bingkai tebal: ini satu-satunya bagian yang
       membatalkan arti seluruh lembar. */
    .bukti-bagian--cabut {
        border: 2px solid var(--hcis-black);
        padding: 3mm;
    }

    .bukti-tabel-data th,
    .bukti-tabel-data td {
        border: 1px solid var(--hcis-black);
        padding: 1.5mm 2mm;
        text-align: left;
        vertical-align: top;
    }

    .bukti-tabel-data th {
        width: 46mm;
        font-weight: normal;
        background: #f7f7f7;
    }

    .bukti-tabel-daftar th,
    .bukti-tabel-daftar td {
        border: 1px solid var(--hcis-black);
        padding: 1.5mm 2mm;
        text-align: left;
        vertical-align: top;
    }

    .bukti-tabel-daftar thead th {
        font-weight: bold;
        background: #f0f0f0;
    }

    .bukti-kolom-urut {
        width: 10mm;
        text-align: center !important;
    }

    .bukti-tabular {
        font-variant-numeric: tabular-nums;
    }

    .bukti-tegas {
        font-weight: bold;
    }

    .bukti-kosong {
        margin: 0;
        padding: 2mm;
        border: 1px dashed var(--hcis-black);
        font-style: italic;
    }

    .bukti-footer {
        margin-top: 8mm;
        padding-top: 2mm;
        border-top: 1px solid var(--hcis-black);
        font-size: 8.5pt;
    }

    .bukti-footer p {
        margin: 0 0 1mm;
    }
}
</style>
