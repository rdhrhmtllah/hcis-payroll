import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeAll, describe, expect, it } from 'vitest';
import { pohonModel } from './masterKendaraan';

/**
 * Potong blok <template> saja.
 *
 * Penjaga berbasis pemindaian sumber wajib menyebut BAGIAN mana yang ia jaga;
 * tanpa itu ia menangkap kemiripan teks di tempat yang tidak ia maksudkan.
 */
const bagianTemplate = (sumber) => {
    const awal = sumber.indexOf('<template>');
    const akhir = sumber.lastIndexOf('</template>');

    return awal === -1 || akhir === -1 ? sumber : sumber.slice(awal, akhir);
};

const berkas = (nama) =>
    readFileSync(
        path.resolve(
            process.cwd(),
            'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/',
            nama,
        ),
        'utf8',
    );

/**
 * Penanda pengajuan mitra B2B di meja verifikator.
 *
 * Pengajuan mitra tidak punya foto kendaraan, STNK, maupun SIM — dan itu memang
 * benar, bukan kelalaian pemohon. Tanpa penanda, verifikator membuka pengajuan
 * kosong dokumen dan menolaknya. Itu kegagalan yang paling mungkin terjadi di
 * hari pertama, dan tidak satu pun uji backend akan menangkapnya.
 */
describe('Meja verifikator — penanda pengajuan mitra B2B', () => {
    it('modal review menambahkan panel penjelas asal pengajuannya', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('dariMitra');
        expect(source).toContain("Sumber_Pengajuan === 'API'");
        expect(source).toContain('verifikator yang mencatatnya saat pemeriksaan');
    });

    it('kartu dokumen TIDAK lagi disembunyikan untuk izin sehari', () => {
        // Dulu disembunyikan karena mitra tidak mengirim dokumen. Sejak
        // verifikator yang mengunggahnya di pos, menyembunyikannya berarti
        // bukti pemeriksaan tidak punya tempat untuk disimpan.
        const source = berkas('PermitReviewModal.vue');

        expect(source).not.toContain('v-if="!permitTamu"');
        expect(source).not.toContain('v-if="!dariMitra"');
        expect(source).toContain('Diunggah verifikator saat pemeriksaan di pos');
    });

    it('panel penjelas menampilkan identitas tamu, jendela, dan referensi mitra', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('permit?.Nama_Tamu');
        expect(source).toContain('permit?.Instansi_Tamu');
        expect(source).toContain('jendelaPermit');
        expect(source).toContain('permit?.Referensi_Mitra');
    });

    it('membedakan pengajuan mitra lewat Sumber_Pengajuan, bukan kategori permit', () => {
        // Kategori COKLAT juga dipakai permit tamu HCIS yang bukan dari API.
        // Mencabangkan lewat kategori akan menyembunyikan dokumen pada permit
        // yang seharusnya punya dokumen.
        const source = berkas('PermitReviewModal.vue');

        expect(source).not.toContain("Nama_Kategori === 'COKLAT'");
        expect(source).not.toContain("Snapshot_Kategori_Permit === 'COKLAT'");
    });

    it('daftar antrean menandai baris pengajuan mitra dengan nama mitranya', () => {
        const source = berkas('VerifikatorPermitPanel.vue');

        expect(source).toContain('vp-mitra-badge');
        expect(source).toContain("row.Sumber_Pengajuan === 'API'");
        expect(source).toContain('row.Nama_Mitra');
    });

    it('lencana mitra ada di tampilan tabel maupun kartu', () => {
        const source = berkas('VerifikatorPermitPanel.vue');
        const jumlah = (source.match(/vp-mitra-badge/g) || []).length;

        // Empat tempat identitas pemohon (antrean/riwayat x tabel/kartu) plus
        // satu definisi gaya. Tampilan kartu yang terlewat berarti penanda ini
        // hilang di layar sempit — dan verifikator sering memakai ponsel.
        expect(jumlah).toBeGreaterThanOrEqual(5);
    });
});

/**
 * Permit tamu tidak memilih durasi bulanan.
 *
 * Permit mitra dan permit turunan kunjungan tamu sama-sama tidak memakai master
 * durasi, dan server memang mengabaikan durasi yang dipilih verifikator. Selama
 * panel tetap menuntutnya, tombol Setujui tidak pernah menyala — kebuntuan
 * tanpa satu pun pesan yang menjelaskan sebabnya, dan tidak satu pun uji
 * backend akan menangkapnya.
 */
describe('Meja verifikator — permit tamu tanpa durasi bulanan', () => {
    it('kartu durasi bulanan disembunyikan untuk permit tamu', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('permitTamu()');
        expect(source).toContain('v-if="!permitTamu && ((isPending && isFinalApproval) || isValidityRequired)"');
    });

    it('tombol setujui tidak lagi menunggu durasi bulanan untuk permit tamu', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('isFinalApproval && !permitTamu && !selectedDurationId');
        expect(source).not.toContain('(isFinalApproval && !selectedDurationId)');
    });

    it('permit turunan kunjungan dikenali dari tautannya, bukan dari kategori', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('dariKunjungan()');
        expect(source).toContain('permit?.No_Transaksi_Tamu');
    });
});

/**
 * Jendela berlaku permit tamu DIPILIH verifikator.
 *
 * Permit mitra datang tanpa tanggal sama sekali — dan selama jendelanya dipaksa
 * turunan sehari, ia MUSTAHIL disetujui siapa pun. Kartu ini yang membongkar
 * kebuntuan itu, jadi hilangnya tidak boleh senyap: tanpa kartunya, tombol
 * Setujui akan terkunci selamanya oleh penjaga server yang tidak punya cara
 * dipenuhi dari layar.
 */
describe('Meja verifikator — jendela berlaku permit tamu', () => {
    it('kartu jendela berlaku muncul untuk permit tamu yang masih menunggu', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('v-if="permitTamu && isPending"');
        expect(source).toContain('masaBerlaku.mulai');
        expect(source).toContain('masaBerlaku.akhir');
        expect(source).toContain('type="datetime"');
    });

    it('jendela ikut terkirim bersama keputusan, bukan lewat permintaan terpisah', () => {
        // Permintaan kedua yang gagal meninggalkan permit yang sudah disetujui
        // tanpa masa berlaku — izin yang tidak pernah kedaluwarsa.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('waktu_mulai_berlaku: this.masaBerlaku.mulai');
        expect(source).toContain('waktu_berakhir: this.masaBerlaku.akhir');
    });

    it('tombol setujui terkunci selama jendelanya belum sah', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('masaBerlakuBelumLengkap');
        expect(source).toContain('|| masaBerlakuBelumLengkap ||');
    });

    it('nilai yang sudah ada ikut terisi kembali, bukan dikosongkan', () => {
        // Approval bertahap membuka modal ini berkali-kali, dan permit turunan
        // kunjungan membawa usulan jendelanya sendiri. Formulir yang selalu
        // kosong membuang keduanya.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('waktuUntukPemilih(this.permit.Waktu_Mulai_Berlaku)');
        expect(source).toContain('waktuUntukPemilih(this.permit.Waktu_Berakhir)');
    });

    it('tidak ada lagi kartu ringkasan kunjungan di panel review', () => {
        // Dicabut atas permintaan user: isinya sudah terbaca di catatan
        // pengajuan, dan kartunya mendorong checklist inspeksi turun jauh ke
        // bawah layar.
        const source = berkas('PermitReviewModal.vue');

        expect(source).not.toContain('Izin sehari dari kunjungan tamu');
    });
});

/**
 * Inspeksi kendaraan tamu dicatat verifikator.
 *
 * Permit COKLAT datang tanpa merek, tahun, dan warna kendaraan — pemohonnya
 * memang tidak punya data itu. Tanpa formulirnya di meja verifikator, permit
 * terbit ke gerbang dengan ketiga kolom berisi '-' dan tidak ada satu pun galat
 * yang menyalakannya.
 */
describe('Meja verifikator — hasil inspeksi kendaraan', () => {
    it('kartu inspeksi muncul hanya bila ada kendaraan untuk diperiksa', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('Hasil Inspeksi Kendaraan');
        expect(source).toContain('v-if="butuhInspeksi && isPending"');
    });

    it('kebutuhan inspeksi dibaca dari server, tidak disimpulkan ulang di layar', () => {
        // Penjaga yang menolak approval memakai ekspresi SQL yang sama persis.
        // Definisi kedua di sini akan membuat tombol Setujui menyala sementara
        // servernya menolak, tanpa pesan yang menjelaskan sebabnya.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("permit?.Wajib_Inspeksi_Kendaraan === 'Y'");
    });

    it('merek/model dipilih dari pohon bercari, bukan dropdown datar', () => {
        // Daftarnya lebih dari seratus baris yang bercampur motor, mobil, dan
        // truk. Dropdown datar memaksa verifikator menggulir mencari satu nama.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('HcisTreeSelect');
        expect(source).toContain('pohonModelKendaraan()');
        expect(source).toContain(':data="pohonModelKendaraan"');
    });

    it('simpul jenis kendaraan tidak bisa dipilih sebagai model', () => {
        // 'Mobil Penumpang' bukan jawaban sah untuk merek/model; tanpa penanda
        // ini, id KELOMPOK tersimpan sebagai id model.
        expect(berkas('PermitReviewModal.vue')).toContain('selectable: false');
    });

    it('model yang jenisnya tak dikenal tidak hilang dari pilihan', () => {
        // Daftar jenis dari master disaring per hak akses; model yang jenisnya
        // tidak lolos saringan akan lenyap diam-diam kalau dibuang.
        // Logikanya kini di masterKendaraan.js (dipakai modal & dialog koreksi);
        // dijaga lewat perilakunya, bukan teks sumbernya.
        expect(berkas('PermitReviewModal.vue')).toContain('return pohonModel(this.masterModels || [], this.masterVehicleTypes || []);');
        const pohon = pohonModel([{ Id_Vehicle_Model: 9, Id_Master_Tipe: 99, Nama_Model_Kendaraan: 'Model Asing' }], []);
        expect(pohon).toEqual([
            { value: 'jenis-99', label: 'Lainnya', selectable: false, children: [{ value: '9', label: 'Model Asing' }] },
        ]);
    });

    it('tombol setujui menunggu ketiga isian inspeksi', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('inspeksiBelumLengkap');
        expect(source).toContain('!this.inspeksi.id_master_model');
        expect(source).toContain('!this.inspeksi.id_master_warna');
        expect(source).toContain('!this.inspeksi.tahun_kendaraan');
    });

    it('isian inspeksi ikut terkirim saat menyetujui', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('id_master_model: this.inspeksi.id_master_model');
        expect(source).toContain('id_master_warna: this.inspeksi.id_master_warna');
        expect(source).toContain('tahun_kendaraan: this.inspeksi.tahun_kendaraan');
    });

    it('formulir terisi kembali dari nilai yang sudah pernah dicatat', () => {
        // Approval bertahap membuka modal ini berkali-kali; formulir yang selalu
        // kosong akan menghapus catatan approver sebelumnya.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('this.permit.Id_Master_Model');
        expect(source).toContain('this.permit.Id_Master_Warna');
    });
});

/**
 * Verifikator melampirkan dokumennya sendiri.
 *
 * Sebelum ini satu-satunya cara menambah berkas adalah tombol "Ganti" pada
 * dokumen yang SUDAH ada. Permit COKLAT selalu lahir tanpa dokumen, sehingga
 * verifikator tidak punya satu pun jalan untuk melampirkan foto kendaraan,
 * STNK, atau SIM hasil pemeriksaannya.
 */
describe('Meja verifikator — lampiran dokumen oleh verifikator', () => {
    it('menyediakan slot unggah per jenis dokumen saat permit masih ditinjau', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('permit-docs-slots');
        expect(source).toContain('pilihBerkas');
        expect(source).toContain('v-if="isPending && !readOnly"');
    });

    it('berkas DITAHAN di layar, tidak diunggah saat dipilih', () => {
        // Satu unggahan per gambar berarti satu round-trip dan satu kotak
        // dialog per gambar di tengah pemeriksaan.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('berkasTertunda');
        expect(source).toContain('this.berkasTertunda = { ...this.berkasTertunda, [jenis]: file }');
        expect(source).not.toContain('triggerUnggahDokumen');
    });

    it('seluruh berkas dikirim sekali jalan lewat dokumen[JENIS]', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('async unggahBerkasTertunda()');
        expect(source).toContain('formData.append(`dokumen[${slot.jenis}]`, berkas)');
    });

    it('pemicunya tombol setujui, dan kegagalannya membatalkan persetujuan', () => {
        // Menyetujui permit sementara lampirannya gagal tersimpan berarti izin
        // terbit tanpa bukti pemeriksaan, dan tidak ada galat yang menyalakannya.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("if (action === 'approve' && !(await this.unggahBerkasTertunda()))");
    });

    it('memakai kosakata jenis dokumen yang benar-benar tersimpan', () => {
        // 'FOTO_STNK' adalah ejaan yang hanya pernah hidup di validasi server
        // dan membuat penggantian dokumen STNK dibalas 422 tanpa penjelasan.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("jenis: 'FOTO_KENDARAAN'");
        expect(source).toContain("jenis: 'STNK'");
        expect(source).toContain("jenis: 'SIM'");
        expect(source).not.toContain("jenis: 'FOTO_STNK'");
    });

    it('checklist pemeriksaan fisik tetap dirender untuk semua permit', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('Checklist Pemeriksaan Fisik');
        // Kartu tidak lagi hilang diam-diam saat pertanyaannya kosong.
        expect(source).toContain('v-if="!checklists || !checklists.length"');
        expect(source).toContain('Tidak ada pertanyaan pemeriksaan untuk kombinasi jenis permit');
    });
});

describe('Meja verifikator — data pengajuan tidak hilang diam-diam', () => {
    it('kartu data pengajuan selalu dirender dan menandai field kosong', () => {
        const template = bagianTemplate(berkas('PermitReviewModal.vue'));

        expect(template).toContain('title="Data Pengajuan"');
        expect(template).not.toContain('v-if="hasVehicleSpecs"\n                        title="Data Pengajuan"');
        expect(template).toContain('{{ labelDataKosong }}');
        expect(template).toContain('permit.Deskripsi_Kategori');
    });

    it('jadwal inspeksi pilihan pemohon ditampilkan dari respons server', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('title="Jadwal Inspeksi"');
        expect(source).toContain('json.result.jadwal_inspeksi');
    });

    it('permit tamu menampilkan estimasi masuk dan keluar kunjungannya di kartu jadwal', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('json.result.kunjungan_tamu');
        expect(source).toContain('v-else-if="permitTamu && kunjunganTamu"');
        expect(source).toContain('Estimasi Masuk');
        expect(source).toContain('Estimasi Keluar');
    });

    it('slot dokumen menyebut pengunggahnya dan bisa dilihat', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('Diunggah pemohon saat pengajuan');
        expect(source).toContain('Tidak diunggah pemohon');
        expect(source).toContain('openFileViewer(dokumenSlot(slot.jenis))');
    });
});

/**
 * Pemohon yang bukan orang.
 *
 * Permit mitra tanpa PIC berpemohon 'API#233' - kunci internal yang sengaja
 * tidak menyerupai kode karyawan mana pun supaya tidak bertabrakan. Yang
 * membacanya di layar adalah admin, bukan integrator: menampilkan kunci itu
 * apa adanya membuatnya mencari-cari karyawan yang tidak pernah ada.
 */
describe('Meja verifikator - identitas pada permit tamu', () => {
    it('yang tampil adalah orang yang akan datang, bukan nama aplikasi mitra', () => {
        // Nama aplikasi ('B2B') dan kunci internalnya ('API#233') tidak pernah
        // muncul di pos jaga; yang dicari admin permit adalah drivernya.
        for (const f of ['PermitReviewModal.vue', 'VerifikatorPermitPanel.vue']) {
            expect(berkas(f)).toContain('Nama_Pemohon_Tampil');
        }
    });

    it('lencana di sebelah nama adalah instansi tamunya, bukan kode kosong', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("permit.Permit_Untuk_Tamu === 'Y'");
        expect(source).toContain("permit.Instansi_Tamu || 'Tamu'");
        // Dibatasi ke blok <template>, BUKAN seluruh berkas.
        //
        // Yang dilarang adalah lencana di sebelah nama berbunyi "Aplikasi
        // Mitra". Pemindaian seluruh berkas juga menangkap baris jejak
        // asal-usul di blok <script> — sebuah baris yang memang SENGAJA ada
        // dan justru berguna bagi admin, karena ia menjawab "pengajuan ini
        // datang dari mana", bukan "siapa yang akan datang". Penjaga yang
        // merah atas fitur yang benar akan dibungkam orang, lalu berhenti
        // menjaga apa pun.
        expect(bagianTemplate(source)).not.toContain('Aplikasi Mitra');
        expect(source).not.toContain('Permit Tamu B2B');
    });

    it('modal membawa jejak asal-usulnya, bukan hanya nama pemohon', () => {
        // Identitas tamu dan referensi mitra sudah tersimpan sejak pengajuan
        // tetapi tidak pernah terbaca, sehingga verifikator memutuskan tanpa
        // tahu surat apa yang dibawa tamunya.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('asalPengajuan()');
        expect(source).toContain('Identitas Tamu');
        expect(source).toContain('Referensi Mitra');
        expect(source).toContain('No. Registrasi Tamu');
        expect(source).toContain('Kontak Tamu');
    });

    it('baris yang datanya memang tidak ada tidak ikut dirender', () => {
        // Baris berlabel '-' membuat admin menduga datanya hilang, padahal
        // pengajuan lewat /permit-tamu memang tidak mengirim nomor telepon.
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('if (p.No_Identitas_Tamu)');
        expect(source).toContain('if (p.No_HP_Tamu)');
        expect(source).toContain('if (p.Referensi_Mitra)');
        expect(source).toContain('if (p.No_Transaksi_Tamu)');
    });

    it('penanggung jawab izinnya dibaca di modal, bukan di barisnya', () => {
        // Nama di depan adalah tamunya; keputusan approval melekat pada
        // pemohonnya. Yang memindai daftar mencari SIAPA YANG DATANG, jadi
        // penanggung jawabnya cukup terbaca saat permitnya dibuka.
        expect(berkas('PermitReviewModal.vue')).toContain("label: 'Diajukan oleh'");

        const panel = berkas('VerifikatorPermitPanel.vue');

        expect(panel).not.toContain('PIC:');
        expect(panel).not.toContain('diajukan oleh');
        // Keterangan di baris disembunyikan seluruhnya untuk permit tamu -
        // termasuk titik pemisahnya, yang kalau tertinggal menggantung sendiri.
        expect(panel.split(`v-if=\"row.Permit_Untuk_Tamu !== 'Y'\"`).length - 1).toBeGreaterThanOrEqual(4);
    });

    it('antrean dan riwayat memakai aturan yang sama, di tabel maupun kartu', () => {
        const source = berkas('VerifikatorPermitPanel.vue');

        // Empat tampilan: antrean dan riwayat, masing-masing tabel dan kartu.
        // Yang hanya diperbaiki di sebagian menyisakan 'API#233' di sisanya.
        expect(source.split('Label_Kode_Pemohon').length - 1).toBeGreaterThanOrEqual(4);
        expect(source.split('Nama_Pemohon_Tampil').length - 1).toBeGreaterThanOrEqual(4);
    });

    it('aturannya diputuskan server, bukan disimpulkan ulang di layar', () => {
        for (const f of ['PermitReviewModal.vue', 'VerifikatorPermitPanel.vue']) {
            expect(berkas(f)).not.toContain("startsWith('API#')");
        }
    });
});


describe('Meja verifikator — koreksi data permit', () => {
    it('tombol koreksi hanya dirender saat server mengizinkan', () => {
        const template = bagianTemplate(berkas('PermitReviewModal.vue'));
        expect(template).toContain('v-if="bolehKoreksi"');
        expect(template).toContain('<PermitKoreksiDialog');
        expect(template).toContain('@tersimpan="loadDetail"');
    });

    it('izin koreksi dibaca dari respons server, bukan dihitung sendiri', () => {
        const source = berkas('PermitReviewModal.vue');
        expect(source).toContain("json.result.boleh_koreksi === true");
    });
});

describe('Meja verifikator — jawaban checklist', () => {
    it('jawaban dipilih lewat kontrol segmen berbasis radio asli, bukan select', () => {
        const template = bagianTemplate(berkas('PermitReviewModal.vue'));

        expect(template).toContain('class="chk-seg"');
        expect(template).toContain('role="radiogroup"');
        expect(template).toContain('type="radio"');
        expect(template).not.toContain('placeholder="Pilih Jawaban"');
    });

    it('memilih Tidak membuka kolom catatan', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("if (nilai === 'TIDAK') item.tampilCatatan = true;");
    });

    it('tandai semua Ya hanya mengisi pertanyaan yang belum dijawab', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain("if (!c.localJawaban) c.localJawaban = 'YA';");
    });
});

describe('Meja verifikator — koreksi tidak menghapus isian checklist', () => {
    it('muat ulang detail permit yang sama menggabungkan isian lokal', () => {
        const source = berkas('PermitReviewModal.vue');

        expect(source).toContain('const noSebelumnya = this.permit?.No_Transaksi;');
        expect(source).toContain('gabungJawabanLokal(checklistServer, this.checklists)');
    });
});

describe('Meja verifikator — riwayat jadwal inspeksi', () => {
    it('pemindahan oleh petugas disebut terpisah dari jadwal ulang pemohon', () => {
        const template = bagianTemplate(berkas('PermitReviewModal.vue'));
        expect(template).toContain('jadwalInspeksi.dipindah_petugas');
    });
});

describe('Meja verifikator — status booking inspeksi', () => {
    it.each(['DIPINDAH', 'DIPINDAH_PETUGAS', 'KEDALUWARSA'])('%s tidak jatuh ke label "Terjadwal"', (status) => {
        expect(berkas('PermitReviewModal.vue')).toContain(`status === '${status}'`);
    });
});

describe('Meja verifikator — tombol yang bergantung pada induk & hak akses', () => {
    it('"Hapus akses" hanya tampil bila induknya menangani minta-revoke', () => {
        expect(berkas('PermitReviewModal.vue')).toContain('return this.revokeTersedia && this.bolehRevoke');
        const monitoring = readFileSync(
            path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Workflow/Reporting/Monitoring/components/PermitAllMonitoring.vue'),
            'utf8',
        );
        expect(monitoring).toMatch(/revoke-tersedia[\s\S]*?@minta-revoke="bukaRevoke"/);
    });

    it('"Lihat Detail Permit" di modal tamu dijaga hak buka permit', () => {
        const tamu = bagianTemplate(berkas('TamuReviewModal.vue'));
        const tombol = tamu.split('label="Lihat Detail Permit"').length - 1;
        expect(tombol).toBe(2);
        expect(tamu).toContain('v-if="bolehBukaPermit"');
        expect(tamu).toContain('<template v-if="bolehBukaPermit" #actions>');
    });

    it('modal tamu dari meja permit membaca endpoint permit-tamu, baca saja', () => {
        const panel = bagianTemplate(berkas('VerifikatorPermitPanel.vue'));
        expect(panel).toMatch(/<TamuReviewModal[\s\S]*?detail-endpoint="\/laporanvisitor-permit-tamu"[\s\S]*?:read-only="true"/);
    });
});

describe('Meja verifikator — cetak kartu dari detail permit', () => {
    it('tombol Cetak Kartu hanya untuk permit APPROVED yang belum dicabut, dan hanya bila induknya menyalakan', () => {
        const isi = berkas('PermitReviewModal.vue');
        expect(isi).toContain('v-if="bisaCetakKartu"');
        expect(isi).toMatch(/bisaCetakKartu\(\) \{[\s\S]*?this\.cetakTersedia[\s\S]*?!this\.dicabut[\s\S]*?Status_Permit === 'APPROVED'/);
        expect(isi).toContain("cetakTersedia: { type: Boolean, default: false }");
    });

    it('memakai rute kartu ADMIN dan menampilkannya di file viewer', () => {
        const isi = berkas('PermitReviewModal.vue');
        expect(isi).toContain('/laporanvisitor-permit/${id}/kartu-pdf');
        expect(isi).toMatch(/this\.showFileViewer = true;[\s\S]*?Kartu permit gagal dibuat/);
    });

    it('dinyalakan di Monitoring, Verifikator, dan Papan Harian (dijaga hak buka permit)', () => {
        const baca = (rel) => readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR', rel), 'utf8');
        expect(baca('Workflow/Reporting/Monitoring/components/PermitAllMonitoring.vue')).toMatch(/<PermitReviewModal[\s\S]*?cetak-tersedia/);
        expect(berkas('VerifikatorPermitPanel.vue')).toMatch(/<PermitReviewModal[\s\S]*?cetak-tersedia/);
        expect(baca('Workflow/Reporting/Monitoring/components/PapanHarian.vue')).toContain(':cetak-tersedia="bolehBukaPermit"');
    });
});

/**
 * Foto pemeriksaan permit COKLAT wajib dan tidak boleh dilewati (permintaan
 * user 29 Sep 2026). Diuji lewat computed-nya langsung — perilakunya, bukan
 * teksnya — dengan konteks tiruan.
 */
describe('Meja verifikator — masa berlaku izin menampilkan jamnya', () => {
    it('jendela berjam didahulukan dari kolom tanggal', () => {
        const source = berkas('PermitReviewModal.vue');
        const berjam = source.indexOf('v-if="permit.Waktu_Mulai_Berlaku && permit.Waktu_Berakhir"');
        const tanggal = source.indexOf('v-else-if="permit.Tanggal_Mulai_Berlaku"');
        expect(berjam).toBeGreaterThan(-1);
        expect(tanggal).toBeGreaterThan(berjam);
    });
});

describe('Meja verifikator — pil sisa masa berlaku di daftar permit', () => {
    let Panel;
    beforeAll(async () => {
        ({ default: Panel } = await import('./VerifikatorPermitPanel.vue'));
    }, 60000);

    const pil = (row) => Panel.methods.sisaMasaBerlaku(row);

    it('hari terakhir berlaku (Sisa_Hari 0) BUKAN expired, menyebut jam berakhirnya', () => {
        expect(pil({ Sisa_Hari: 0, Waktu_Berakhir: '2026-09-29 23:59:00' })).toEqual({ label: 'Berakhir hari ini 23:59', nada: 'today' });
        expect(pil({ Sisa_Hari: 0 })).toEqual({ label: 'Berakhir hari ini', nada: 'today' });
    });

    it('masih beberapa hari atau sudah lewat', () => {
        expect(pil({ Sisa_Hari: 3 })).toEqual({ label: 'Sisa 3 hari', nada: 'active' });
        expect(pil({ Sisa_Hari: -1 })).toEqual({ label: 'Expired', nada: 'expired' });
    });

    it('template memakai satu penentu, bukan ternary Sisa_Hari > 0 lagi', () => {
        const source = berkas('VerifikatorPermitPanel.vue');
        expect(source).not.toContain(": 'Expired' }}");
        expect(source.match(/sisaMasaBerlaku\(row\)\.label/g)).toHaveLength(2);
    });
});

describe('Meja verifikator — setujui sebelum jadwal inspeksi', () => {
    let Modal;
    beforeAll(async () => {
        ({ default: Modal } = await import('./PermitReviewModal.vue'));
    }, 60000);

    const belumTiba = (jadwalInspeksi) =>
        Modal.computed.jadwalInspeksiBelumTiba.call({
            jadwalInspeksi,
            formatFullDateTime: (v) => v,
        });

    it('jadwal TERJADWAL di masa depan ditandai belum tiba, lengkap dengan pos', () => {
        const hasil = belumTiba({ status: 'TERJADWAL', mulai: '2999-01-01 08:00', pos: 'Pos Utama' });
        expect(hasil).toEqual({ waktu: '2999-01-01T08:00', pos: 'Pos Utama' });
    });

    it('jadwal yang sudah lewat, selesai, atau tidak ada tidak memberi peringatan', () => {
        expect(belumTiba({ status: 'TERJADWAL', mulai: '2000-01-01 08:00', pos: 'Pos Utama' })).toBeNull();
        expect(belumTiba({ status: 'SELESAI', mulai: '2999-01-01 08:00' })).toBeNull();
        expect(belumTiba(null)).toBeNull();
    });

    it('konfirmasi Setujui memperingatkan, BUKAN menolak', () => {
        const source = berkas('PermitReviewModal.vue');
        const approve = source.slice(source.indexOf('async approveAlert()'), source.indexOf('async rejectAlert()'));
        expect(approve).toContain('this.jadwalInspeksiBelumTiba');
        expect(approve).toContain('Setujui sebelum jadwal inspeksi?');
    });
});

describe('Meja verifikator — foto pemeriksaan permit tamu wajib', () => {
    const SLOT = [
        { jenis: 'FOTO_KENDARAAN', label: 'Foto Kendaraan' },
        { jenis: 'STNK', label: 'STNK' },
        { jenis: 'SIM', label: 'SIM' },
    ];

    let Modal;
    // Modal ini besar; kompilasinya saja bisa melewati batas 5 detik bawaan.
    beforeAll(async () => {
        ({ default: Modal } = await import('./PermitReviewModal.vue'));
    }, 60000);

    async function komputasi() {
        return Modal.computed;
    }

    function konteks(c, { butuhInspeksi = true, isPending = true, readOnly = false, tertunda = {}, terlampir = [] } = {}) {
        const ctx = {
            butuhInspeksi,
            isPending,
            readOnly,
            slotDokumen: SLOT,
            berkasTertunda: { FOTO_KENDARAAN: null, STNK: null, SIM: null, ...tertunda },
            dokumenTerlampir: (jenis) => terlampir.includes(jenis),
        };
        ctx.lampiranWajib = c.lampiranWajib.call(ctx);
        return ctx;
    }

    it('permit tamu berkendaraan: ketiga foto wajib, yang kurang disebut satu per satu', async () => {
        const c = await komputasi();
        const ctx = konteks(c, { terlampir: ['STNK'], tertunda: { FOTO_KENDARAAN: new Blob(['x']) } });

        expect(ctx.lampiranWajib).toBe(true);
        expect(c.lampiranWajibKurang.call(ctx).map((s) => s.label)).toEqual(['SIM']);
    });

    it('lengkap bila setiap slot punya berkas tersimpan ATAU siap diunggah', async () => {
        const c = await komputasi();
        const ctx = konteks(c, { terlampir: ['STNK', 'SIM'], tertunda: { FOTO_KENDARAAN: new Blob(['x']) } });
        expect(c.lampiranWajibKurang.call(ctx)).toEqual([]);
    });

    it('tamu pejalan kaki, permit sudah diproses, atau mode baca: tidak diwajibkan', async () => {
        const c = await komputasi();
        for (const opsi of [{ butuhInspeksi: false }, { isPending: false }, { readOnly: true }]) {
            const ctx = konteks(c, opsi);
            expect(ctx.lampiranWajib).toBe(false);
            expect(c.lampiranWajibKurang.call(ctx)).toEqual([]);
        }
    });

    it('tombol Setujui memeriksa kekurangan foto sebelum mengirim', () => {
        const source = berkas('PermitReviewModal.vue');
        const approve = source.slice(source.indexOf('async approveAlert()'), source.indexOf('async rejectAlert()'));
        expect(approve).toContain('this.lampiranWajibKurang.length');
        expect(approve.indexOf('lampiranWajibKurang')).toBeLessThan(approve.indexOf("this.submitAction('approve')"));
    });

    it('tombol Setujui NONAKTIF selama ada foto wajib yang kosong, dan menyebut alasannya', async () => {
        const source = berkas('PermitReviewModal.vue');
        const tombol = source.slice(source.indexOf(": 'Setujui Permit'"), source.indexOf('@click="approveAlert"'));
        expect(tombol).toContain('lampiranWajibKurang.length > 0');

        const c = await komputasi();
        const ctx = { hasDocErrors: false, hasUnansweredRequired: false, inspeksiBelumLengkap: false, lampiranWajibKurang: [SLOT[2]] };
        expect(c.judulTombolSetujui.call(ctx)).toBe('Unggah foto pemeriksaan: SIM');
    });
});

describe('Meja verifikator — jadwal inspeksi terlewat / tidak hadir mengunci tombol Setujui', () => {
    let Modal;
    beforeAll(async () => {
        ({ default: Modal } = await import('./PermitReviewModal.vue'));
    }, 60000);

    it('jadwal inspeksi terlewat hari atau tidak hadir terdeteksi bermasalah', () => {
        const c = Modal.computed;
        // 1. Jadwal terlewat hari (kemarin)
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'TERJADWAL', mulai: '2020-01-01 08:00:00' },
        })).toContain('terlewat');

        // 2. Jadwal tidak hadir
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'TIDAK_HADIR', mulai: '2026-10-12 08:00:00' },
        })).toContain('tidak hadir');

        // 3. Jadwal wajib pilih ulang
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'TERJADWAL', wajib_pilih_ulang: true, mulai: '2026-10-12 08:00:00' },
        })).toContain('tidak hadir');

        // 4. Jadwal kedaluwarsa
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'KEDALUWARSA', mulai: '2026-10-10 08:00:00' },
        })).toContain('kedaluwarsa');

        // 5. Jadwal gugur
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'TERJADWAL', gugur: true, mulai: '2026-10-10 08:00:00' },
        })).toContain('gugur');

        // 6. Jadwal yang sudah selesai inspeksi TIDAK bermasalah
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'SELESAI', mulai: '2020-01-01 08:00:00' },
        })).toBeNull();

        // 7. Jadwal masa depan TIDAK bermasalah
        expect(c.jadwalInspeksiBermasalah.call({
            permitTamu: false,
            jadwalInspeksi: { status: 'TERJADWAL', mulai: '2999-01-01 08:00:00' },
        })).toBeNull();
    });

    it('tombol Setujui NONAKTIF bila jadwal inspeksi bermasalah dan judulTombolSetujui menyebut sebabnya', () => {
        const source = berkas('PermitReviewModal.vue');
        const tombol = source.slice(source.indexOf(": 'Setujui Permit'"), source.indexOf('@click="approveAlert"'));
        expect(tombol).toContain('jadwalInspeksiBermasalah');

        const c = Modal.computed;
        const ctx = {
            hasDocErrors: false,
            hasUnansweredRequired: false,
            inspeksiBelumLengkap: false,
            jadwalInspeksiBermasalah: 'Jadwal inspeksi telah terlewat tanggal tanpa hasil pemeriksaan dari SHE.',
            lampiranWajibKurang: [],
        };
        expect(c.judulTombolSetujui.call(ctx)).toBe('Jadwal inspeksi telah terlewat tanggal tanpa hasil pemeriksaan dari SHE.');
    });

    it('peringatan jadwal inspeksi bermasalah dirender di kartu jadwal', () => {
        const source = berkas('PermitReviewModal.vue');
        expect(source).toContain('jadwalInspeksiBermasalah');
        expect(source).toContain('permit-jadwal-alert');
    });
});
