import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';

import PaWizardLembarList from './PaWizardLembarList.vue';
import PaWizardPratinjauKalibrasi from './PaWizardPratinjauKalibrasi.vue';
import PaWizardRingkasanKirim from './PaWizardRingkasanKirim.vue';
import PaWizardStepper from './PaWizardStepper.vue';

const kompetensi = [
    {
        competency_id: 11,
        kode: 'CV01',
        nama: 'Integritas',
        jenis_kode: 'CORE_VALUE',
        jenis_nama: 'Core Value',
        rata_rata: 80,
        jumlah_dinilai: 2,
    },
    {
        competency_id: 12,
        kode: 'CV02',
        nama: 'Kolaborasi',
        jenis_kode: 'CORE_VALUE',
        jenis_nama: 'Core Value',
        rata_rata: 70,
        jumlah_dinilai: 1,
    },
];

const orang = (
    id,
    boleh = true,
    sel = [{ competency_id: 11, skor_asli: 90, skor_efektif: 90, skor_maksimal: 100, terkalibrasi: false }],
) => ({
    assignment_id: id,
    peserta: { kode_karyawan: `K${id}`, nama: `Orang ${id}`, jabatan: 'Staff', divisi: 'Ops' },
    diri_sendiri: false,
    status_penugasan: 'TERKUMPUL',
    boleh_diubah: boleh,
    kalibrasi_terakhir: null,
    sel,
    naratif: [],
});

const pratinjau = (o, boleh = true) => ({
    siklus: { cycle_id: 7 },
    kategori: { kode: 'ATASAN', nama: 'Atasan', boleh_kalibrasi: boleh, status: 'TUNTAS' },
    kompetensi,
    orang: o,
    dipangkas: false,
});

describe('PaWizardStepper', () => {
    it('langkah terkunci tidak bisa dipilih dan menyebut sebabnya', () => {
        const w = mount(PaWizardStepper, {
            props: {
                langkah: [
                    {
                        kode_kategori: 'SELF',
                        nama_kategori: 'Diri Sendiri',
                        status: 'AKTIF',
                        jumlah_lembar: 1,
                        jumlah_terisi: 0,
                        sebab_terkunci: null,
                    },
                    {
                        kode_kategori: 'ATASAN',
                        nama_kategori: 'Atasan',
                        status: 'TERKUNCI',
                        jumlah_lembar: 2,
                        jumlah_terisi: 0,
                        sebab_terkunci: 'Selesaikan penilaian Diri Sendiri lebih dulu.',
                    },
                ],
                kodeDilihat: 'SELF',
            },
        });

        expect(w.text()).toContain('Selesaikan penilaian Diri Sendiri lebih dulu.');
        // Sejak redesign stepper, pilihan lewat kode langkah (`pilihKode`), bukan indeks.
        const tombol = w.findAll('button.pa-compact-item');
        expect(tombol[1].attributes('disabled')).toBeDefined();
        w.vm.pilihKode('ATASAN');
        expect(w.emitted('pilih')).toBeUndefined();
        // Ringkasan tertutup selama ada langkah yang belum tuntas.
        expect(tombol[2].attributes('disabled')).toBeDefined();
        w.vm.pilihKode('RINGKASAN');
        expect(w.emitted('pilih')).toBeUndefined();
        w.vm.pilihKode('SELF');
        expect(w.emitted('pilih')[0]).toEqual(['SELF']);
    });
});

describe('PaWizardLembarList', () => {
    it('tanpa tautan, tombol Mulai Menilai tidak muncul', () => {
        const w = mount(PaWizardLembarList, {
            props: {
                langkah: {
                    kode_kategori: 'ATASAN',
                    nama_kategori: 'Atasan',
                    status: 'TERKUNCI',
                    sebab_terkunci: 'Selesaikan penilaian Diri Sendiri lebih dulu.',
                },
                lembar: [
                    {
                        assignment_id: 1,
                        status_penugasan: 'BELUM_MULAI',
                        peserta: { nama: 'Andre', jabatan: 'Staff', divisi: 'Ops' },
                        diri_sendiri: false,
                        tenggat: null,
                        terlambat: false,
                        url_ujian: null,
                        short_token: null,
                        akses_otp: null,
                    },
                ],
                total: 1,
                dipangkas: false,
            },
        });

        expect(w.text()).not.toContain('Mulai Menilai');
        expect(w.text()).toContain('Selesaikan penilaian Diri Sendiri lebih dulu.');
    });

    it('tautan yang tidak aman tidak dijadikan tombol', () => {
        const w = mount(PaWizardLembarList, {
            props: {
                langkah: { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'AKTIF' },
                lembar: [
                    {
                        assignment_id: 1,
                        status_penugasan: 'BELUM_MULAI',
                        peserta: { nama: 'Andre', jabatan: 'Staff', divisi: 'Ops' },
                        diri_sendiri: false,
                        tenggat: null,
                        terlambat: false,
                        url_ujian: 'javascript:alert(1)',
                        short_token: 'K7Q2P',
                        akses_otp: null,
                    },
                ],
                total: 1,
                dipangkas: false,
            },
        });

        expect(w.text()).not.toContain('Mulai Menilai');
        expect(w.find('a[href^="javascript"]').exists()).toBe(false);
    });

    it('kiriman tertahan tidak menawarkan pengisian ulang', () => {
        const baris = (tertahan) => ({
            assignment_id: 1,
            status_penugasan: 'BELUM_MULAI',
            kiriman_tertahan: tertahan,
            peserta: { nama: 'Ridho', jabatan: 'Staff', divisi: 'Ops' },
            diri_sendiri: true,
            tenggat: null,
            terlambat: false,
            url_ujian: 'https://hclearn.example/ujian/1',
            short_token: 'K7Q2P',
            akses_otp: null,
        });
        const pasang = (tertahan) => mount(PaWizardLembarList, {
            props: {
                langkah: { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'AKTIF' },
                lembar: [baris(tertahan)],
                total: 1,
                dipangkas: false,
            },
        });

        const w = pasang(true);
        expect(w.find('[data-uji="kiriman-tertahan"]').exists()).toBe(true);
        expect(w.text()).toContain('Diterima, menunggu admin');
        expect(w.text()).not.toContain('Mulai Menilai');
        expect(w.text()).not.toContain('K7Q2P');

        // Arah sebaliknya: lembar yang sama tanpa kiriman tetap menawarkan pengisian.
        const biasa = pasang(false);
        expect(biasa.find('[data-uji="kiriman-tertahan"]').exists()).toBe(false);
        expect(biasa.text()).toContain('Mulai Menilai');
    });

    it('ringkasan tidak menawarkan Tinjau Kalibrasi untuk kategori tanpa kalibrasi', () => {
        const w = mount(PaWizardRingkasanKirim, {
            props: {
                langkah: [
                    { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'TUNTAS', boleh_kalibrasi: false, jumlah_lembar: 1, jumlah_terisi: 1, jumlah_terkalibrasi: 0 },
                    { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TUNTAS', boleh_kalibrasi: true, jumlah_lembar: 1, jumlah_terisi: 1, jumlah_terkalibrasi: 0 },
                ],
                pengiriman: { total: 2, terkumpul: 2, terkirim: 0, sudah_terkirim_semua: false },
                bolehKirim: true,
                mengirim: false,
                galatKirim: null,
            },
        });

        const tombol = w.findAll('[data-uji="buka-langkah"]');
        expect(tombol).toHaveLength(1);
        expect(tombol[0].attributes('aria-label')).toContain('Atasan');
    });

    it('ringkasan menyebut kenapa Kirim belum bisa ditekan', () => {
        const pasang = (pengiriman, bolehKirim) => mount(PaWizardRingkasanKirim, {
            props: {
                langkah: [{ kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'TUNTAS', boleh_kalibrasi: false, jumlah_lembar: 2, jumlah_terisi: 2, jumlah_terkalibrasi: 0 }],
                pengiriman,
                bolehKirim,
                mengirim: false,
                galatKirim: null,
            },
        });

        const tertahan = pasang({ total: 2, terkumpul: 1, terkirim: 0, sisa: 1, menunggu_verifikasi: 1, belum_diisi: 0, sudah_terkirim_semua: false }, false);
        expect(tertahan.find('[data-uji="alasan-belum-kirim"]').text()).toContain('1 lembar masih menunggu verifikasi nilai di HC Learn');

        const siap = pasang({ total: 2, terkumpul: 2, terkirim: 0, sisa: 0, menunggu_verifikasi: 0, belum_diisi: 0, sudah_terkirim_semua: false }, true);
        expect(siap.find('[data-uji="alasan-belum-kirim"]').exists()).toBe(false);
    });

    it('dialog kirim merinci penilaian per kategori dengan bahasa sehari-hari', async () => {
        const w = mount(PaWizardRingkasanKirim, {
            props: {
                langkah: [
                    { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'TUNTAS', boleh_kalibrasi: false, jumlah_lembar: 1, jumlah_terisi: 1, jumlah_terkalibrasi: 0 },
                    { kode_kategori: 'PEER', nama_kategori: 'Peer', status: 'TUNTAS', boleh_kalibrasi: true, jumlah_lembar: 2, jumlah_terisi: 2, jumlah_terkalibrasi: 0 },
                ],
                pengiriman: { total: 3, terkumpul: 3, terkirim: 0, sudah_terkirim_semua: false },
                bolehKirim: true,
                mengirim: false,
                galatKirim: null,
            },
            global: { stubs: { HcisDialog: { template: '<div><slot /><slot name="footer" /></div>' } } },
        });

        const isi = w.find('[data-uji="konfirmasi-kirim"]').text();
        expect(isi).toContain('3 penilaian');
        expect(isi).toContain('Diri Sendiri');
        expect(isi).toContain('Peer');
        expect(isi).toContain('tidak bisa diubah lagi');
        expect(isi).not.toContain('Satu Pintu');

        await w.find('[data-uji="konfirmasi-kirim-ya"]').trigger('click');
        expect(w.emitted('kirim')).toHaveLength(1);
    });

    it('Mulai Menilai membuka lembar di tab yang sama dengan layar tunggu', async () => {
        const w = mount(PaWizardLembarList, {
            props: {
                langkah: { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'AKTIF' },
                lembar: [1, 2].map((id) => ({
                    assignment_id: id,
                    status_penugasan: 'BELUM_MULAI',
                    peserta: { nama: `Orang ${id}`, jabatan: 'Staff', divisi: 'Ops' },
                    diri_sendiri: false,
                    tenggat: null,
                    terlambat: false,
                    url_ujian: `https://hclearn.example/ujian/${id}`,
                    short_token: null,
                    akses_otp: null,
                })),
                total: 2,
                dipangkas: false,
            },
            global: { stubs: { teleport: true } },
        });

        // Bukan tautan tab baru: tab baru tampil kosong selama HC Learn dimuat.
        expect(w.find('a[target="_blank"]').exists()).toBe(false);
        expect(w.find('[data-uji="membuka-lembar"]').exists()).toBe(false);

        const tombol = w.findAll('[data-uji="mulai-menilai"]');
        await tombol[0].trigger('click');

        expect(w.find('[data-uji="membuka-lembar"]').text()).toContain('Orang 1');
        expect(tombol[0].text()).toContain('Membuka lembar');
        // Lembar lain dikunci supaya tidak ada dua pembukaan sekaligus.
        expect(tombol[1].attributes('disabled')).toBeDefined();
    });

    it('langkah tuntas menawarkan Kalibrasi Penilai hanya bila boleh dikalibrasi', async () => {
        const pasang = (boleh) => mount(PaWizardLembarList, {
            props: {
                langkah: { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TUNTAS', boleh_kalibrasi: boleh },
                lembar: [],
                total: 0,
                dipangkas: false,
                labelLanjut: 'Lanjut ke Rekan Kerja',
            },
        });

        const w = pasang(true);
        await w.find('[data-uji="ke-pratinjau"]').trigger('click');
        expect(w.emitted('ke-pratinjau')).toHaveLength(1);
        expect(w.find('[data-uji="lanjut"]').exists()).toBe(false);

        // Tanpa kalibrasi tidak ada Matriks: tombolnya langsung ke langkah berikutnya.
        const tanpa = pasang(false);
        expect(tanpa.find('[data-uji="ke-pratinjau"]').exists()).toBe(false);
        expect(tanpa.text()).toContain('Lanjut ke Rekan Kerja');
        await tanpa.find('[data-uji="lanjut"]').trigger('click');
        expect(tanpa.emitted('lanjut')).toHaveLength(1);
    });
});

const esai = (jawaban = 'Jawaban asli', extra = {}) => ({
    kode_soal: 'Q07',
    competency_id: 21,
    nama: 'Strength',
    jawaban,
    jawaban_asli: jawaban,
    terkalibrasi: false,
    ...extra,
});

/* Quill tidak berjalan di jsdom; kontrak yang diuji di sini adalah v-model HTML-nya. */
const stubRichText = {
    HcisRichText: {
        props: ['modelValue', 'error'],
        emits: ['update:modelValue'],
        template: `<div class="rich-stub"><textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" /><small>{{ error }}</small></div>`,
    },
};

describe('PaWizardPratinjauKalibrasi — esai', () => {
    const orangBeresai = (boleh = true, n = esai()) => ({ ...orang(1, boleh), naratif: [n] });

    it('esai bisa diubah bila boleh, dan terkirim sebagai perubahanJawaban', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: pratinjau([orangBeresai()]) },
            global: { stubs: stubRichText },
        });
        const o = w.props('pratinjau').orang[0];

        expect(w.find('[data-uji="esai-1-Q07"]').exists()).toBe(true);

        w.vm.ubahEsai(o, o.naratif[0], '<p>Jawaban yang <strong>diperjelas</strong></p>');
        await w.vm.$nextTick();
        expect(w.emitted('kotor').at(-1)).toEqual([true]);

        w.vm.bukaAlasan(o);
        w.vm.alasan = 'Diperjelas';
        w.vm.kirimSimpan();

        expect(w.emitted('simpan')[0][0]).toEqual({
            assignmentId: 1,
            perubahan: [],
            perubahanJawaban: [{ kode_soal: 'Q07', jawaban: '<p>Jawaban yang <strong>diperjelas</strong></p>' }],
            alasan: 'Diperjelas',
        });
    });

    it('esai yang dikosongkan diblok sebelum sampai server', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: pratinjau([orangBeresai()]) },
            global: { stubs: stubRichText },
        });
        const o = w.props('pratinjau').orang[0];

        // Editor teks kaya yang dikosongkan mengirim paragraf kosong, bukan string kosong.
        w.vm.ubahEsai(o, o.naratif[0], '<p><br></p>');
        w.vm.bukaAlasan(o);
        w.vm.alasan = 'x';
        w.vm.kirimSimpan();

        expect(w.emitted('simpan')).toBeUndefined();
        expect(w.vm.galatEsai).toContain('dikosongkan');
    });

    /* Format per soal (26 Sep 2026): HC Learn mengirim pertanyaan tiap soal esai, dan satu
       bagian bisa memuat beberapa soal ber-kode_soal sama. */
    const soal = (id, pertanyaan, jawaban) =>
        esai(jawaban, { id_master_soal: id, pertanyaan, per_soal: true });

    it('per soal: pertanyaan jadi judul, dan tiap soal direvisi terpisah', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: {
                pratinjau: pratinjau([
                    {
                        ...orang(1, true),
                        naratif: [
                            soal(101, 'Ceritakan pekerjaan yang menumpuk.', 'Saya urutkan menurut tenggat.'),
                            soal(102, 'Bagaimana bila rekan tidak kooperatif?', 'Saya ajak bicara.'),
                        ],
                    },
                ]),
            },
            global: { stubs: stubRichText },
        });
        const o = w.props('pratinjau').orang[0];
        await w.find('[data-uji="naratif-toggle-semua"]').trigger('click');

        const judul = w.findAll('.pa-naratif-question').map((d) => d.text());
        expect(judul[0]).toContain('Ceritakan pekerjaan yang menumpuk.');
        expect(judul[0]).toContain('Strength');
        expect(judul[1]).toContain('Bagaimana bila rekan tidak kooperatif?');
        expect(w.find('[data-uji="esai-1-Q07-101"]').exists()).toBe(true);
        expect(w.find('[data-uji="esai-1-Q07-102"]').exists()).toBe(true);

        w.vm.ubahEsai(o, o.naratif[1], '<p>Saya dengarkan alasannya dulu.</p>');
        await w.vm.$nextTick();

        expect(w.vm.teksEsai(o, o.naratif[0])).toBe('Saya urutkan menurut tenggat.');

        w.vm.bukaAlasan(o);
        w.vm.alasan = 'Diperjelas';
        w.vm.kirimSimpan();

        expect(w.emitted('simpan')[0][0].perubahanJawaban).toEqual([
            { kode_soal: 'Q07', id_master_soal: 102, jawaban: '<p>Saya dengarkan alasannya dulu.</p>' },
        ]);
    });

    it('per soal tanpa id tidak bisa direvisi, dan pertanyaan yang hilang diberi keterangan', () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: pratinjau([{ ...orang(1, true), naratif: [soal(null, null, 'Jawaban lama')] }]) },
            global: { stubs: stubRichText },
        });

        expect(w.find('.rich-stub').exists()).toBe(false);
        expect(w.text()).toContain('Pertanyaan tidak tersedia');
        expect(w.text()).toContain('Jawaban lama');
    });

    it('uraian dijajarkan per pertanyaan: satu baris memuat jawaban semua orang, dengan tingkat level & jumlah kata', async () => {
        const data = pratinjau([
            { ...orang(1, false), naratif: [esai('Teliti dan cepat menyelesaikan laporan bulanan untuk seluruh tim produksi.')] },
            { ...orang(2, false), naratif: [esai('Rajin.'), esai('Perlu pelatihan Excel.', { kode_soal: 'Q08', competency_id: 22, nama: 'Development Area' })] },
        ], false);
        data.level = [{ id_level: 0, nama_level: 'OFFICER', jumlah: 2, rata_rata: [], perilaku: [{ competency_id: 21, tingkat: 1, nama_tingkat: 'Basic', expected_behaviour: 'Menyebut kekuatan' }] }];
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: data } });

        const baris = w.findAll('[data-uji="naratif-baris"]');
        expect(baris).toHaveLength(2);
        expect(baris[0].find('.pa-naratif-question').text()).toContain('Strength');
        expect(baris[0].text()).toContain('L1 · Basic');
        // Perilaku dipisah dari soal dan dilipat: tertutup dulu, terbuka bila diklik.
        const perilaku = baris[0].find('[data-uji="naratif-perilaku"]');
        expect(perilaku.text()).not.toContain('Menyebut kekuatan');
        await perilaku.find('[data-uji="penjelasan-toggle-perilaku"]').trigger('click');
        expect(perilaku.text()).toContain('Menyebut kekuatan');
        expect(baris[0].find('[data-uji="naratif-sel-1"]').text()).toContain('Teliti dan cepat');
        expect(baris[0].find('[data-uji="naratif-sel-2"]').text()).toContain('Rajin.');

        // Jumlah kata selalu tampil; badge "Singkat" hanya untuk uraian di bawah ambang.
        expect(baris[0].find('[data-uji="naratif-sel-1"] .pa-naratif-char-info').text()).toBe('10 kata');
        expect(baris[0].find('[data-uji="naratif-sel-2"] .pa-naratif-char-info').text()).toBe('1 kata');
        expect(baris[0].find('[data-uji="naratif-sel-1"] [data-uji="naratif-kata"]').exists()).toBe(false);
        expect(baris[0].find('[data-uji="naratif-sel-2"] [data-uji="naratif-kata"]').text()).toBe('Singkat');

        // Pertanyaan kedua tertutup secara bawaan; dibuka dulu.
        await baris[1].find('[data-uji="naratif-toggle"]').trigger('click');
        // Orang yang tidak menjawab pertanyaan itu tetap punya sel, berisi keterangan.
        expect(baris[1].find('[data-uji="naratif-sel-1"]').text()).toContain('Tidak ada uraian');
        expect(baris[1].find('[data-uji="naratif-sel-2"]').text()).toContain('Perlu pelatihan Excel.');
    });

    it('tiap pertanyaan berupa accordion: hanya yang pertama terbuka, revisi di soal tertutup tetap terlihat', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: pratinjau([{ ...orang(1, true), naratif: [soal(101, 'Soal satu?', 'Jawab satu'), soal(102, 'Soal dua?', 'Jawab dua')] }]) },
            global: { stubs: stubRichText },
        });
        const o = w.props('pratinjau').orang[0];
        const [b1, b2] = w.findAll('[data-uji="naratif-baris"]');

        expect(b1.find('[data-uji="naratif-toggle"]').attributes('aria-expanded')).toBe('true');
        expect(b2.find('[data-uji="naratif-toggle"]').attributes('aria-expanded')).toBe('false');
        expect(b2.find('[data-uji="naratif-pertanyaan"]').classes()).toContain('is-terpotong');
        expect(w.find('[data-uji="esai-1-Q07-102"]').exists()).toBe(false);
        expect(b2.text()).toContain('1 jawaban');

        // Revisi di soal yang tertutup tetap ditandai di kepalanya.
        w.vm.ubahEsai(o, o.naratif[1], '<p>Revisi dua</p>');
        await w.vm.$nextTick();
        expect(b2.find('[data-uji="naratif-direvisi"]').text()).toBe('1 direvisi');

        await w.find('[data-uji="naratif-toggle-semua"]').trigger('click');
        expect(w.find('[data-uji="esai-1-Q07-102"]').exists()).toBe(true);
        expect(w.find('[data-uji="naratif-toggle-semua"]').text()).toBe('Tutup semua');

        await w.find('[data-uji="naratif-toggle-semua"]').trigger('click');
        expect(w.findAll('[data-uji="naratif-toggle"]').map((t) => t.attributes('aria-expanded'))).toEqual(['false', 'false']);
    });

    it('hanya-baca tanpa kotak isian, dan jawaban asli tampil bila sudah direvisi', () => {
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: {
                pratinjau: pratinjau(
                    [orangBeresai(false, esai('Revisi', { jawaban_asli: 'Teks asli', terkalibrasi: true }))],
                    false,
                ),
            },
        });

        expect(w.find('[data-uji="esai-1-Q07"]').exists()).toBe(false);
        expect(w.text()).toContain('Revisi');
        expect(w.text()).toContain('Teks asli');
    });
});

describe('PaWizardPratinjauKalibrasi — bar aksi', () => {
    it('satu bar: tanpa draf menawarkan Lanjut; ada draf menggantinya dengan Batalkan & Simpan', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1), orang(2)]), labelLanjut: 'Lanjut ke Ringkasan & Kirim' } });
        const bar = () => w.find('[data-uji="bar-aksi"]');

        expect(w.findAll('[data-uji="bar-aksi"]')).toHaveLength(1);
        expect(bar().find('[data-uji="lanjut"]').exists()).toBe(true);
        expect(bar().find('[data-uji="bar-simpan"]').exists()).toBe(false);

        const o = w.props('pratinjau').orang[0];
        w.vm.ubah(o, w.props('pratinjau').kompetensi[0], 70);
        await w.vm.$nextTick();

        expect(w.findAll('[data-uji="bar-aksi"]')).toHaveLength(1);
        expect(bar().text()).toContain('1 peserta belum disimpan');
        expect(bar().find('[data-uji="lanjut"]').exists()).toBe(false);
        expect(bar().find('[data-uji="bar-simpan"]').text()).toBe('Simpan Kalibrasi');

        await bar().find('[data-uji="bar-batal-draf"]').trigger('click');
        expect(bar().find('[data-uji="lanjut"]').exists()).toBe(true);
    });

    it('banyak draf: satu tombol Simpan Kalibrasi (n), satu alasan, dikirim sebagai simpan-banyak', async () => {
        document.body.innerHTML = ''; // sisa dialog ter-teleport dari uji lain
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1), orang(2)]) }, attachTo: document.body });
        const [a, b] = w.props('pratinjau').orang;
        const k = w.props('pratinjau').kompetensi[0];
        w.vm.ubah(a, k, 70);
        w.vm.ubah(b, k, 60);
        await w.vm.$nextTick();

        const tombol = w.findAll('[data-uji="bar-simpan"]');
        expect(tombol).toHaveLength(1);
        expect(tombol[0].text()).toBe('Simpan Kalibrasi (2)');

        w.vm.bukaAlasanSemua();
        await w.vm.$nextTick();
        // Dialog di-teleport ke body.
        expect(document.querySelectorAll('[data-uji="dialog-orang"]')).toHaveLength(2);

        w.vm.alasan = 'Disejajarkan antar rekan';
        w.vm.kirimSimpan();
        const [daftar, saatTersimpan] = w.emitted('simpan-banyak')[0];
        expect(w.emitted('simpan')).toBeUndefined();
        expect(daftar.map((d) => [d.assignmentId, d.nama, d.alasan, d.perubahan])).toEqual([
            [1, 'Orang 1', 'Disejajarkan antar rekan', [{ competency_id: 11, skor: 70 }]],
            [2, 'Orang 2', 'Disejajarkan antar rekan', [{ competency_id: 11, skor: 60 }]],
        ]);

        // Orang yang sudah tersimpan keluar dari draf.
        saatTersimpan(1);
        await w.vm.$nextTick();
        expect(w.find('[data-uji="bar-simpan"]').text()).toBe('Simpan Kalibrasi');
        w.unmount();
    });
});

describe('PaWizardPratinjauKalibrasi', () => {
    /* Keputusan pemilik proyek 28 Sep 2026: rata-rata skor yang diberikan penilai tidak
       ditampilkan, baik di matriks desktop maupun kartu ponsel. */
    it('tidak menampilkan rata-rata penilai di matriks maupun kartu', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1), orang(2)]) } });

        expect(w.text()).not.toMatch(/rata-rata/i);
        expect(w.find('.pa-footer-avg').exists()).toBe(false);
        expect(w.find('.pa-card-comp-avg').exists()).toBe(false);

        // Tanpa kolom rata-rata, setiap baris punya kolom yang sama banyak: kompetensi + orang.
        const lebar = 1 + 2;
        expect(w.findAll('thead th').length).toBe(lebar);
        w.findAll('.pa-kalibrasi__matriks tbody tr, .pa-kalibrasi__matriks tfoot tr').forEach((tr) => {
            expect(tr.findAll('th, td').length).toBe(lebar);
        });
    });

    it('memisahkan orang per level ke tab, dengan perilaku tingkat level itu', async () => {
        const a = orang(1);
        const b = orang(2);
        a.peserta = { ...a.peserta, id_level: 4, nama_level: 'OFFICER' };
        b.peserta = { ...b.peserta, id_level: 5, nama_level: 'SENIOR OFFICER' };
        const data = pratinjau([a, b]);
        const kid = data.kompetensi[0].competency_id;
        data.level = [
            { id_level: 4, nama_level: 'OFFICER', jumlah: 1, rata_rata: [], perilaku: [{ competency_id: kid, tingkat: 2, nama_tingkat: 'Intermediate', expected_behaviour: 'Perilaku L2' }] },
            { id_level: 5, nama_level: 'SENIOR OFFICER', jumlah: 1, rata_rata: [], perilaku: [] },
        ];
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: data } });

        const tab = w.findAll('[data-uji="kalibrasi-tab-level"] button');
        expect(tab.map((t) => t.text()).join(' ')).toContain('OFFICER');
        expect(w.find('thead').text()).toContain('Orang 1');
        expect(w.find('thead').text()).not.toContain('Orang 2');
        expect(w.text()).toContain('L2 · Intermediate');

        await tab[1].trigger('click');
        expect(w.find('thead').text()).toContain('Orang 2');
        expect(w.find('thead').text()).not.toContain('Orang 1');
    });

    it('matriks bersumbu kompetensi di baris dan orang di kolom', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1), orang(2)]) } });

        const kepala = w.findAll('thead th').map((t) => t.text());
        const barisKepala = w.findAll('tbody th').map((t) => t.text());

        expect(kepala[0]).toContain('Kompetensi');
        expect(kepala.join(' ')).toContain('Orang 1');
        expect(kepala.join(' ')).toContain('Orang 2');
        expect(kepala.join(' ')).not.toContain('Integritas');

        expect(barisKepala.join(' ')).toContain('Integritas');
        expect(barisKepala.join(' ')).toContain('Kolaborasi');
        expect(barisKepala.join(' ')).not.toContain('Orang 1');
    });

    /* DDL-2026-166: penjelasan kompetensi dibuka lewat tombol "Detail" di samping namanya
       (dialog), supaya matriks tetap rapat. Kompetensi tanpa penjelasan tidak punya tombolnya. */
    it('tombol Detail membuka deskripsi & expected behaviour kompetensi', async () => {
        document.body.innerHTML = '';
        const berpenjelasan = [
            { ...kompetensi[0], deskripsi: 'Bertindak jujur dan konsisten.', expected_behaviour: '- Menepati janji\n- Melaporkan apa adanya' },
            { ...kompetensi[1], deskripsi: null, expected_behaviour: null },
        ];
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: { ...pratinjau([orang(1)]), kompetensi: berpenjelasan } },
            attachTo: document.body,
        });

        const baris = w.findAll('tbody th');
        expect(baris[1].find('.pa-btn-comp-info').exists()).toBe(false);

        await baris[0].find('.pa-btn-comp-info').trigger('click');
        await nextTick();
        expect(document.body.textContent).toContain('Bertindak jujur dan konsisten.');
        expect([...document.querySelectorAll('[data-uji="daftar-perilaku"] li')].map((l) => l.textContent.trim())).toEqual([
            'Menepati janji',
            'Melaporkan apa adanya',
        ]);
        w.unmount();
    });

    it('tanpa penjelasan sama sekali, tombol "Tampilkan semua" tidak muncul', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });
        expect(w.find('[data-uji="toggle-semua-penjelasan"]').exists()).toBe(false);
        expect(w.find('[data-uji="penjelasan-kompetensi"]').exists()).toBe(false);
    });

    it('hanya-baca bila kategori tidak boleh dikalibrasi', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1, false)], false) } });

        // Keterangan "mode tinjau" kini di kepala langkah (PaSayaWizardPenilaian); di sini yang dijaga: tanpa isian.
        expect(w.findAll('input').length).toBe(0);
    });

    it('sel yang tidak dimiliki seseorang tampil strip, bukan nol', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });

        expect(w.find('[data-uji="sel-1-12"]').text()).toBe('—');
    });

    it('kotak isian punya nama aksesibel', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });
        const label = w.find('[data-uji="sel-1-11"] label');

        expect(label.exists()).toBe(true);
        expect(label.text()).toContain('Skor Integritas untuk Orang 1');
        expect(label.find('input').exists()).toBe(true);
    });

    it('simpan menuntut alasan dan memancarkan perubahan saja', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });

        w.vm.ubah(w.props('pratinjau').orang[0], kompetensi[0], 75);
        await w.vm.$nextTick();
        expect(w.emitted('kotor').at(-1)).toEqual([true]);

        w.vm.bukaAlasan(w.props('pratinjau').orang[0]);
        w.vm.kirimSimpan();
        expect(w.emitted('simpan')).toBeUndefined();
        expect(w.vm.galatAlasan).toBe('Alasan wajib diisi.');

        w.vm.alasan = 'Terlalu tinggi';
        w.vm.kirimSimpan();
        expect(w.emitted('simpan')[0][0]).toEqual({
            assignmentId: 1,
            perubahan: [{ competency_id: 11, skor: 75 }],
            perubahanJawaban: [],
            alasan: 'Terlalu tinggi',
        });
    });

    it('sesudah satu orang tersimpan, draf orang lain tetap dilaporkan kotor', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1), orang(2)]) } });
        const [satu, dua] = w.props('pratinjau').orang;

        w.vm.ubah(satu, kompetensi[0], 75);
        w.vm.ubah(dua, kompetensi[0], 60);
        await w.vm.$nextTick();

        w.vm.bukaAlasan(satu);
        w.vm.alasan = 'Selaras';
        w.vm.kirimSimpan();

        // Server membalas pratinjau baru; orang 1 kini berskor 75.
        await w.setProps({
            pratinjau: pratinjau([
                orang(1, true, [
                    { competency_id: 11, skor_asli: 90, skor_efektif: 75, skor_maksimal: 100, terkalibrasi: true },
                ]),
                orang(2),
            ]),
        });

        expect(w.emitted('kotor').at(-1)).toEqual([true]);
    });
});

describe('PaWizardRingkasanKirim', () => {
    it('kirim meminta konfirmasi lebih dulu', async () => {
        const w = mount(PaWizardRingkasanKirim, {
            props: {
                langkah: [
                    {
                        kode_kategori: 'ATASAN',
                        nama_kategori: 'Atasan',
                        status: 'TUNTAS',
                        jumlah_lembar: 2,
                        jumlah_terisi: 2,
                        jumlah_terkalibrasi: 1,
                    },
                ],
                pengiriman: { total: 2, terkumpul: 2, terkirim: 0, sisa: 0, sudah_terkirim_semua: false },
                bolehKirim: true,
            },
        });

        await w.find('[data-uji="kirim"]').trigger('click');
        expect(w.emitted('kirim')).toBeUndefined();
        w.vm.konfirmasiKirim();
        expect(w.emitted('kirim')).toHaveLength(1);
    });
});

describe('PaWizardPratinjauKalibrasi — pengingat uraian', () => {
    const beresai = (id) => ({
        ...orang(id),
        naratif: [esai(`Jawaban ${id} satu`, { kode_soal: 'Q07' }), esai(`Jawaban ${id} dua`, { kode_soal: 'Q08' })],
    });

    it('menyebut jumlah pertanyaan uraian dan menahan Lanjut selama ada yang belum dilihat', async () => {
        document.body.innerHTML = '';
        const w = mount(PaWizardPratinjauKalibrasi, {
            props: { pratinjau: pratinjau([beresai(1), beresai(2)]) },
            global: { stubs: stubRichText },
            attachTo: document.body,
        });

        expect(w.find('[data-uji="pengingat-uraian"]').text()).toContain('Tersedia 2 pertanyaan uraian');
        expect(w.find('[data-uji="pengingat-uraian"]').text()).toContain('sudah dilihat 0 dari 2');
        expect(w.find('[data-uji="bar-uraian"]').text()).toBe('Uraian dilihat 0/2');
        expect(w.findAll('[data-uji="naratif-belum-dilihat"]')).toHaveLength(2);

        // Lanjut ditahan dengan dialog; "Tetap lanjut" meneruskan.
        await w.find('[data-uji="lanjut"]').trigger('click');
        expect(w.emitted('lanjut')).toBeUndefined();
        expect(document.querySelector('[data-uji="dialog-uraian"]').textContent).toContain('2 pertanyaan uraian');
        document.querySelector('[data-uji="uraian-tetap-lanjut"]').click();
        expect(w.emitted('lanjut')).toHaveLength(1);

        // Membuka pertanyaan = melihatnya. Pertanyaan pertama terbuka bawaan: tutup lalu buka lagi.
        const toggle = () => w.findAll('[data-uji="naratif-toggle"]');
        await toggle()[1].trigger('click');
        await toggle()[0].trigger('click');
        await toggle()[0].trigger('click');
        expect(w.find('[data-uji="bar-uraian"]').text()).toBe('Uraian dilihat 2/2');
        expect(w.findAll('[data-uji="naratif-belum-dilihat"]')).toHaveLength(0);

        await w.find('[data-uji="lanjut"]').trigger('click');
        expect(w.emitted('lanjut')).toHaveLength(2);
        w.unmount();
    });

    it('tanpa uraian tidak ada pengingat dan Lanjut langsung jalan', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });
        expect(w.find('[data-uji="pengingat-uraian"]').exists()).toBe(false);
        expect(w.find('[data-uji="bar-uraian"]').exists()).toBe(false);
        await w.find('[data-uji="lanjut"]').trigger('click');
        expect(w.emitted('lanjut')).toHaveLength(1);
    });
});

describe('PaWizardPratinjauKalibrasi — pemadatan matriks', () => {
    const tigaKompetensi = [...kompetensi, { competency_id: 13, kode: 'MG01', nama: 'Analisis', jenis_kode: 'MANAGERIAL', jenis_nama: 'Managerial' }];
    const skor = (pasangan) =>
        pasangan.map(([competency_id, s]) => ({ competency_id, skor_asli: s, skor_efektif: s, skor_maksimal: 100, terkalibrasi: false }));
    const pasang = () =>
        mount(PaWizardPratinjauKalibrasi, {
            props: {
                pratinjau: {
                    ...pratinjau([
                        orang(1, true, skor([[11, 90], [12, 80], [13, 70]])),
                        orang(2, true, skor([[11, 90], [12, 60], [13, 70]])),
                    ]),
                    kompetensi: tigaKompetensi,
                },
            },
        });

    it('mengelompokkan per jenis dan tiap kelompok bisa dilipat', async () => {
        const w = pasang();
        const kelompok = w.findAll('[data-uji="kelompok-kompetensi"]');
        expect(kelompok.map((k) => k.text())).toEqual(['Core Value2 kompetensi', 'Managerial1 kompetensi']);
        expect(w.findAll('[data-uji="baris-kompetensi"]')).toHaveLength(3);

        await kelompok[0].trigger('click');
        expect(w.findAll('[data-uji="baris-kompetensi"]').map((b) => b.find('th').text())).toEqual([expect.stringContaining('Analisis')]);
    });

    it('sakelar "Hanya yang skornya berbeda" menyisakan yang berbeda, plus yang punya draf', async () => {
        const w = pasang();
        expect(w.find('[data-uji="matriks-alat"]').text()).toContain('1 dari 3 kompetensi skornya berbeda');

        await w.find('[data-uji="saring-berbeda"] [role="switch"]').trigger('click');
        const nama = () => w.findAll('[data-uji="baris-kompetensi"] th').map((t) => t.text());
        expect(nama()).toHaveLength(1);
        expect(nama()[0]).toContain('Kolaborasi');

        // Baris yang sedang disunting tidak ikut tersembunyi.
        const [a] = w.props('pratinjau').orang;
        w.vm.ubah(a, tigaKompetensi[0], 50);
        await nextTick();
        expect(nama()).toHaveLength(2);
    });

    it('satu orang saja: tanpa sakelar saring', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });
        expect(w.find('[data-uji="matriks-alat"]').exists()).toBe(false);
        expect(w.find('[data-uji="kelompok-kompetensi"]').exists()).toBe(false);
    });
});
