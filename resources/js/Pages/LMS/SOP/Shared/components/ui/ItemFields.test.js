import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ItemFields from './ItemFields.vue';

vi.mock('axios');

/**
 * Setiap layar wajib menyediakan cara memenuhi syarat yang ditagih server.
 *
 * Sejak Fase 6, field Judul dihapus untuk tipe ber-versi karena identitasnya
 * milik Dokumen SOP. Tapi ada satu jalur yang TIDAK bisa menautkan dokumen:
 * item baru pada paket yang sudah terbit lahir di N_SOP_Paket_Draft_Item, dan
 * tabel itu tak punya kolom Id_SOP_Dokumen sama sekali. Di sana judul masih
 * wajib — dan selama field-nya ikut disembunyikan, penyimpanan ditolak 422
 * tanpa ada satu pun kotak di layar yang bisa diisi admin.
 *
 * Yang dijaga di sini persis itu: judul dan pemilih dokumen tak pernah hilang
 * berbarengan.
 */

const STUBS = {
    SopRichText: { template: '<div />' },
    // props dideklarasikan supaya nilainya bisa DIPERIKSA; stub tanpa props
    // menelan apa pun yang dikirim dan setiap pemeriksaan berbunyi `undefined`.
    SopDokumenPickerModal: {
        name: 'SopDokumenPickerModal',
        props: ['show', 'tipe', 'dipakai', 'sekarang'],
        template: '<div />',
    },
    SopKonfirmasi: {
        name: 'SopKonfirmasi',
        props: ['show', 'pesan', 'statistik', 'catatan', 'memuatStatistik'],
        template: '<div />',
    },
    'el-select': { template: '<div class="stub-select"><slot /></div>' },
    'el-option': { template: '<div />' },
    // props & name dideklarasikan supaya keadaan terkuncinya bisa DIPERIKSA;
    // stub polos menelan :disabled dan setiap pemeriksaan berbunyi undefined.
    'el-switch': {
        name: 'ElSwitch',
        props: ['modelValue', 'disabled'],
        template: '<div class="stub-switch" />',
    },
};

function pasang(form = {}, props = {}) {
    return mount(ItemFields, {
        props: {
            form: { Tipe_Item: 'DOKUMEN', Judul_Item: '', ...form },
            isNew: true,
            ...props,
        },
        global: { stubs: STUBS },
    });
}

const adaJudul = (w) => w.findAll('input').some((i) => i.attributes('placeholder') === 'Judul item');
const adaPemilihDokumen = (w) => w.find('.dok-pilih').exists() || w.find('.dok-card').exists();
/* Dicari lewat labelnya, bukan lewat el-select mana pun: blok KUIS juga punya
   pemilih "Jenis", dan menghitung select membuat ujinya lulus/gagal karena
   field yang sama sekali lain. */
const adaPemilihUjian = (w) => w.findAll('label').some((l) => /Ujian \(HC Learn\)/.test(l.text()));

describe('ItemFields — identitas item ber-versi', () => {
    it('paket draft: menautkan dokumen, tanpa kotak judul', () => {
        const w = pasang({}, { paketPublished: false });

        expect(adaPemilihDokumen(w)).toBe(true);
        expect(adaJudul(w)).toBe(false);
        // Berkasnya ikut dokumen; meminta konten di sini = dua sumber untuk satu berkas.
        expect(w.find('.konten-pick').exists()).toBe(false);
    });

    it('paket terbit SEBELUM DDL 33: menyebut kenapa item ber-versi belum bisa ditambahkan', () => {
        /* Overlay draft belum punya kolom Id_SOP_Dokumen, jadi tautan apa pun
           yang dipilih tak punya tempat untuk disimpan.

           Jalur lamanya — ketik judul, lampirkan berkas, dokumennya dibuat
           belakangan saat promosi lewat ensureForItem() — DICABUT 27 Agu 2026.
           Ia adalah pintu kedua tempat Dokumen SOP lahir, dan yang paling tak
           terlihat: dokumennya muncul berhari-hari setelah admin menekan
           Simpan, dengan Nomor_SOP_Resmi salinan yang melewati penjaga nomor
           kembar.

           Yang wajib ada sekarang bukan formulir, melainkan ALASAN. Server
           menolaknya; layar yang diam membuat penolakan itu tak bisa dimengerti. */
        const w = pasang({}, { paketPublished: true, dukungDokumenDraft: false });

        expect(adaJudul(w)).toBe(false);
        expect(adaPemilihDokumen(w)).toBe(false);
        expect(w.text()).toContain('DDL-2026-033');
    });

    it('paket terbit SESUDAH DDL 33: item baru boleh memakai dokumen yang sudah ada', () => {
        /* Ini langkah kedua dari alur pembetulan yang benar — "hapus item
           ber-SOP salah, tambahkan item ber-SOP benar". Sebelumnya langkah itu
           mustahil: item baru di paket terbit selalu melahirkan dokumen BARU,
           jadi SOP yang benar tak pernah bisa ditugaskan. */
        const w = pasang({}, { paketPublished: true, dukungDokumenDraft: true });

        expect(adaPemilihDokumen(w)).toBe(true);
        expect(adaJudul(w)).toBe(false);
        // Berkasnya ikut dokumennya, sama seperti di paket draft.
        expect(w.find('.konten-pick').exists()).toBe(false);
    });

    it('tak pernah menghilangkan judul DAN pemilih dokumen sekaligus', () => {
        // Invarian yang runtuh dan menyebabkan 422 tanpa jalan keluar.
        for (const tipe of ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS', 'PARAGRAF', 'TAUTAN']) {
            for (const terbit of [false, true]) {
                for (const baru of [false, true]) {
                    // Kedua keadaan DDL 33 disapu: sebelum dan sesudahnya.
                    for (const ddl of [false, true]) {
                        const w = pasang(
                            { Tipe_Item: tipe },
                            { paketPublished: terbit, isNew: baru, dukungDokumenDraft: ddl },
                        );

                        /* Satu keadaan sengaja tak punya formulir: item baru
                           ber-versi di paket terbit sebelum DDL 33. Di sana yang
                           wajib ada ALASANNYA — layar yang kosong tanpa
                           penjelasan sama buruknya dengan 422 tanpa pesan. */
                        const adaAlasan = /DDL-2026-033/.test(w.text());

                        expect(
                            adaJudul(w) || adaPemilihDokumen(w) || adaAlasan,
                            `${tipe} (terbit=${terbit}, baru=${baru}, ddl=${ddl}) tak punya cara mengisi identitasnya, dan tak menjelaskan kenapa`,
                        ).toBe(true);
                    }
                }
            }
        }
    });

    /* SEMBILAN uji "buat dokumen baru" dihapus 27 Agu 2026 bersama jalurnya:
       penyusun dokumen, perpindahan tab, "Pakai ini" pada berkas terpakai,
       pemilih konten dari dua arah, pemisahan deskripsi dokumen vs catatan
       versi, dan pemilih pengesah di Builder.

       Semuanya menjaga satu kemampuan yang kini tak ada: Builder melahirkan
       Dokumen SOP dan menerbitkan versi pertamanya dalam satu permintaan,
       melompati Pengajuan → Review → Pengesahan. Uji yang menjaga jalur yang
       dicabut bukan cuma tak berguna — ia menahan pencabutannya.

       Yang menggantikannya ada di bawah: layar hanya menawarkan MEMILIH. */

    it('memperingatkan item yang dokumennya belum punya versi terbit', () => {
        /* Kartunya terlihat lengkap — judul benar, nomor SOP benar — dan
           karyawan yang membukanya tak menerima apa pun. Sebelum ini satu-satunya
           petunjuk cuma "Belum ada versi" di panel bawah, yang terbaca seperti
           panelnya yang belum jalan, bukan dokumennya yang belum siap. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Id_Item_Versi_Aktif: null },
            { isNew: false },
        );

        expect(w.text()).toContain('belum punya versi berlaku');
    });

    it('tidak berisik pada item yang dokumennya sudah punya versi', () => {
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Id_Item_Versi_Aktif: 'v-1' },
            { isNew: false },
        );

        expect(w.text()).not.toContain('belum punya versi berlaku');
    });

    it('peringatan hilang begitu dokumen sehat dipilih, tanpa menunggu simpan', async () => {
        /* Pointer item baru bergeser di server saat disimpan. Selama peringatan
           dibaca dari pointer itu saja, mengganti dokumen rusak dengan dokumen
           yang sehat tetap memperlihatkan peringatan merah — dan admin
           menyimpulkan gantinya tak berhasil, lalu mencoba lagi. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-rusak', Id_Item_Versi_Aktif: null },
            { isNew: false },
        );
        expect(w.text()).toContain('belum punya versi berlaku');

        w.vm.onDokumenDipilih({
            Id_SOP_Dokumen: 'dok-sehat',
            Judul: 'SOP Perjalanan Dinas',
            Nomor_SOP_Resmi: 'PERDIN-001',
            Id_Versi_Aktif: 'v9',
        });
        await w.vm.$nextTick();

        expect(w.text()).not.toContain('belum punya versi berlaku');
        expect(w.props('form').Judul_Item).toBe('SOP Perjalanan Dinas');
    });

    it('mengganti dokumen item tersimpan ditahan konfirmasi dulu', async () => {
        /* Yang berubah bukan nama di kartu, melainkan SOP mana yang wajib
           dibaca orang. Sebelum ini penggantiannya langsung menempel begitu
           dipilih — tanpa satu pun kesempatan meninjau ulang. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', id: 'item-1', Id_SOP_Dokumen: 'dok-lama', Judul_Item: 'SOP Lama' },
            { isNew: false },
        );

        w.vm.onDokumenDipilih({ Id_SOP_Dokumen: 'dok-baru', Judul: 'Dokumen Baru', Id_Versi_Aktif: 'v1' });
        await w.vm.$nextTick();

        // Formulirnya BELUM berubah — penggantiannya masih ditahan.
        expect(w.props('form').Id_SOP_Dokumen).toBe('dok-lama');
        expect(w.findComponent({ name: 'SopKonfirmasi' }).props('show')).toBe(true);

        w.vm.terapkanGantiDokumen();
        await w.vm.$nextTick();

        expect(w.props('form').Id_SOP_Dokumen).toBe('dok-baru');
        expect(w.props('form').Judul_Item).toBe('Dokumen Baru');
    });

    it('membatalkan konfirmasi meninggalkan dokumen lama apa adanya', async () => {
        // Penggantian ditahan, bukan diterapkan lalu dipulihkan: pemulihan yang
        // meleset meninggalkan item menunjuk dokumen yang tak pernah dipilih.
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', id: 'item-1', Id_SOP_Dokumen: 'dok-lama', Judul_Item: 'SOP Lama' },
            { isNew: false },
        );

        w.vm.onDokumenDipilih({ Id_SOP_Dokumen: 'dok-baru', Judul: 'Dokumen Baru', Id_Versi_Aktif: 'v1' });
        await w.vm.$nextTick();

        w.vm.gantiKonf.buka = false; // ditutup lewat Batal
        await w.vm.$nextTick();

        expect(w.props('form').Id_SOP_Dokumen).toBe('dok-lama');
        expect(w.props('form').Judul_Item).toBe('SOP Lama');
    });

    it('pemilihan pertama tidak ditanya', async () => {
        // Belum ada yang berubah — bertanya di sana cuma kebisingan.
        const w = pasang({ Tipe_Item: 'DOKUMEN', id: 'item-1', Id_SOP_Dokumen: null }, { isNew: false });

        w.vm.onDokumenDipilih({ Id_SOP_Dokumen: 'dok-baru', Judul: 'Dokumen Baru', Id_Versi_Aktif: 'v1' });
        await w.vm.$nextTick();

        expect(w.vm.gantiKonf.buka).toBe(false);
        expect(w.props('form').Id_SOP_Dokumen).toBe('dok-baru');
    });

    it('peringatan tetap muncul kalau dokumen penggantinya juga kosong', async () => {
        // Sisi lain: penanda optimis tak boleh jadi cara memadamkan peringatan.
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-a', Id_Item_Versi_Aktif: 'v1' },
            { isNew: false },
        );

        w.vm.onDokumenDipilih({ Id_SOP_Dokumen: 'dok-kosong', Judul: 'SOP Kosong', Id_Versi_Aktif: null });
        await w.vm.$nextTick();

        expect(w.text()).toContain('belum punya versi berlaku');
    });

    it('tidak menawarkan Ganti dokumen pada paket yang sudah terbit', () => {
        /* Revisinya ditulis ke N_SOP_Paket_Draft_Item, dan tabel itu TAK PUNYA
           kolom Id_SOP_Dokumen sama sekali. Dulu tombolnya tetap ada,
           penggantiannya dijawab "Item tersimpan", dan itemnya tetap menunjuk
           dokumen lama — gagal tanpa satu pun tanda. Seluruh catatan baca
           karyawan justru menempel pada item di paket terbit, jadi di sinilah
           kesalahpahamannya paling mahal. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Id_Item_Versi_Aktif: 'v1' },
            { isNew: false, paketPublished: true },
        );

        expect(w.findAll('button').some((b) => /^Ganti$/.test(b.text()))).toBe(false);
        /* Alasannya kini di TOOLTIP pil "Terkunci", bukan kalimat di badan
           kartu — w.text() memang takkan pernah memuatnya. Yang dijaga tetap
           sama: penguncian harus BERSEBAB, bukan tombol yang diam-diam hilang. */
        const kunci = w.find('.dok-kunci');

        expect(kunci.exists(), 'pil terkunci tak ditampilkan').toBe(true);
        expect(kunci.attributes('title')).toContain('Tak bisa diganti pada paket yang sudah terbit');
    });

    it('tidak menawarkan pemilih dokumen pada paket terbit, bahkan saat tautannya tak terbaca', () => {
        /* Item pada paket terbit dibaca dari overlay draft, dan barisnya lama
           tak membawa Id_SOP_Dokumen sama sekali — sehingga item yang JELAS
           punya dokumen tampil seolah belum, lengkap dengan tawaran
           memilihnya. Apa pun yang dipilih di sana tak punya tempat untuk
           disimpan: kolomnya tidak ada. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: null, Judul_Item: 'Kebijakan Umum' },
            { isNew: false, paketPublished: true },
        );

        expect(
            w.findAll('button').some((b) => /Pilih Dokumen/.test(b.text())),
            'masih menawarkan pemilih yang hasilnya tak bisa disimpan',
        ).toBe(false);
        // Yang ditampilkan judul itemnya, supaya kartunya tak kosong melompong.
        expect(w.find('.dok-terkunci').text()).toContain('Kebijakan Umum');
    });

    it('tetap menawarkan Ganti untuk item yang baru ditambahkan ke revisi paket terbit', () => {
        /* Dua item bisa duduk berdampingan di paket terbit dengan aturan yang
           berlawanan. Yang ini lahir di revisi yang belum dirilis: belum ada
           satu pun karyawan yang membacanya, jadi salah pilih dokumen masih
           boleh diperbaiki di tempat. Mengunci semuanya memaksa admin menghapus
           lalu menambah ulang untuk kekeliruan yang belum berakibat apa pun. */
        const w = pasang(
            {
                Tipe_Item: 'DOKUMEN',
                Id_SOP_Dokumen: 'dok-1',
                Id_Item_Versi_Aktif: 'v1',
                Sudah_Pernah_Terbit: false,
            },
            { isNew: false, paketPublished: true },
        );

        expect(w.findAll('button').some((b) => /^Ganti$/.test(b.text()))).toBe(true);
        expect(w.find('.dok-kunci').exists(), 'pil terkunci muncul padahal masih boleh diganti').toBe(false);
    });

    it('mengunci saat penanda asal-usulnya tak ada', () => {
        /* Bakunya menutup, bukan membuka. Salah kunci cuma merepotkan admin;
           salah buka menyentuh catatan kepatuhan yang tak bisa dibetulkan. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Id_Item_Versi_Aktif: 'v1' },
            { isNew: false, paketPublished: true },
        );

        expect(w.findAll('button').some((b) => /^Ganti$/.test(b.text()))).toBe(false);
    });

    it('tetap menawarkan Ganti pada paket yang belum terbit', () => {
        // Sisi lain: di sana penggantiannya memang tersimpan dan sah.
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Id_Item_Versi_Aktif: 'v1' },
            { isNew: false, paketPublished: false },
        );

        expect(w.findAll('button').some((b) => /^Ganti$/.test(b.text()))).toBe(true);
    });

    it('meneruskan daftar dokumen terpakai ke pemilih', () => {
        /* Diuji lewat PROP komponennya, bukan lewat tampilan pemilih: pemilih
           sudah punya ujinya sendiri, dan yang gampang hilang justru
           penerusannya di komponen tengah — persis cacat yang pernah terjadi
           pada prop `admin` di SopKontenViewer. */
        const w = pasang(
            { Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1' },
            { isNew: false, dokumenDipakai: ['dok-a', 'dok-b'] },
        );

        const picker = w.findComponent({ name: 'SopDokumenPickerModal' });
        expect(picker.exists(), 'pemilih dokumen tak dirender').toBe(true);
        expect(picker.props('dipakai')).toEqual(['dok-a', 'dok-b']);
        /* Dokumen item ini sendiri diteruskan terpisah: ia dikecualikan dari
           `dipakai` supaya tak terbaca "bermasalah", tapi tetap tak boleh
           dipilih ulang — memilihnya tak mengubah apa pun. */
        expect(picker.props('sekarang')).toBe('dok-1');
    });

    it('menyebut ANGKA paket lain, bukan andaian', () => {
        /* Kalimat lamanya berandai-andai: "memilih dokumen yang sudah dipakai
           paket lain membuat item ini ikut bergerak". Dua kiasan sekaligus, dan
           jumlahnya tak disebut padahal sudah diketahui — satu paket dan sebelas
           paket dibaca sama persis. */
        const w = pasang({ Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Paket_Lain_Pemakai: 3 }, { isNew: false });

        expect(w.text()).toContain('3 paket lain');
        expect(w.text()).not.toContain('ikut bergerak');
    });

    it('menyebut apa adanya kalau belum dipakai paket lain', () => {
        // "0 paket lain" tak berarti apa-apa; yang berarti adalah "baru paket ini".
        const w = pasang({ Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1', Paket_Lain_Pemakai: 0 }, { isNew: false });

        expect(w.text()).toContain('Baru dipakai paket ini');
    });

    it('menjelaskan KENAPA judul tak bisa diketik, bukan sekadar bahwa ia mengikuti', () => {
        // Pertanyaan admin yang sebenarnya: "kenapa saya tak bisa mengubahnya
        // di sini, dan di mana saya mengubahnya?"
        const w = pasang({ Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1' }, { isNew: false });
        const teks = w.text();

        /* Kalimat panjangnya diringkas jadi lencana "Dikelola di Master Dokumen"
           pada 19b8fc39 — dan sekalian digantung pada isNew, sehingga item LAMA
           kehilangan penjelasannya sama sekali. Gerbang itu dilepas kembali di
           komponen; uji ini yang membuktikannya tak boleh hilang lagi. */
        expect(teks).toContain('Dikelola di Master Dokumen');
        expect(teks, 'tak menyebut ke mana harus pergi').toContain('Master Dokumen');
        /* Bagian ketiga kalimat lama menunjuk panel "Kendali Versi" — panel itu
           sudah tak ada di SELURUH modul SOP (siklus versi pindah ke halaman
           wizard), jadi menuntutnya berarti menuntut sesuatu yang memang tak
           boleh ada lagi. Dua bagian pertamanya tetap dijaga di atas. */
    });

    it('tidak memperingatkan item yang belum tersimpan', () => {
        // Item baru memang belum punya pointer; peringatan di sana cuma kebisingan.
        const w = pasang({ Tipe_Item: 'DOKUMEN', Id_SOP_Dokumen: 'dok-1' }, { isNew: true });

        expect(w.text()).not.toContain('belum punya versi berlaku');
    });

    it('menulis pilihan ujian ke item yang sedang disunting', async () => {
        /* Satu-satunya sasaran yang tersisa. Dulu ada dua — item, dan "dokumen
           baru" yang sedang disusun — dan menulis ke sasaran yang keliru membuat
           versinya lahir tanpa ujian, lalu penerbitannya ditolak padahal admin
           jelas sudah memilih. Sasaran kedua ikut hilang bersama jalurnya. */
        const w = pasang({ Tipe_Item: 'KUIS' }, { paketPublished: false, isNew: false });

        w.vm.ujianList = [{ value: 'UJIAN-1', label: 'Ujian Keselamatan' }];
        w.vm.onPilihUjian('UJIAN-1');

        expect(w.props('form').Nama_Ujian).toBe('Ujian Keselamatan');
    });

    it('tidak menawarkan pemilih ujian yang hasilnya akan dibuang', () => {
        // Ujian milik versi dokumennya; pilihan di sini ditimpa saat disimpan.
        const w = pasang({ Tipe_Item: 'KUIS' }, { paketPublished: false });

        expect(adaPemilihUjian(w)).toBe(false);
    });

    it('tetap menawarkan pemilih ujian saat menyunting item yang sudah ada', () => {
        // Ganti ujian pada item hidup masih jalur yang sah (versi living).
        const w = pasang({ Tipe_Item: 'KUIS' }, { paketPublished: false, isNew: false });

        expect(adaPemilihUjian(w)).toBe(true);
    });
});

describe('ItemFields — watermark vs izin unduh', () => {
    /* Watermark disematkan pembaca aman, halaman demi halaman. Begitu berkas
       mentahnya boleh diunduh, pembaca itu bisa dilewati sepenuhnya — jadi
       keduanya menyala bersamaan adalah janji yang tak ditepati siapa pun.

       Server sudah menormalkannya saat menyimpan. Yang dijaga di sini adalah
       layarnya: tanpa ini admin menyalakan watermark, menekan simpan, dan
       setelannya lenyap tanpa satu pun keterangan. */

    const sakelar = (w) => w.findComponent({ name: 'ElSwitch' });

    it('mengunci sakelar watermark ketika berkasnya boleh diunduh', () => {
        const w = pasang({ Mode_Akses: 'VIEW_UNDUH', Flag_Watermark: false });

        expect(sakelar(w).props('disabled')).toBe(true);
    });

    it('membiarkan sakelar watermark ketika berkasnya hanya boleh dilihat', () => {
        const w = pasang({ Mode_Akses: 'VIEW_ONLY', Flag_Watermark: true });

        expect(sakelar(w).props('disabled')).toBe(false);
    });

    it('memadamkan watermark saat izin unduh dipilih', async () => {
        // Bukan sekadar mengunci sakelarnya: nilai yang tertinggal menyala
        // membuat layar menampilkan "Aktif" yang tak bisa diubah, lalu server
        // menyimpannya mati. Yang tampil harus sama dengan yang tersimpan.
        const w = pasang({ Mode_Akses: 'VIEW_ONLY', Flag_Watermark: true });
        const tombol = w.findAll('.acc-seg-btn').find((b) => b.text().includes('Boleh Unduh'));

        await tombol.trigger('click');

        expect(w.props('form').Mode_Akses).toBe('VIEW_UNDUH');
        expect(w.props('form').Flag_Watermark).toBe(false);
    });

    it('menyebutkan alasannya, bukan cuma mematikan sakelarnya', () => {
        // Sakelar mati tanpa sebab terbaca sebagai kerusakan.
        const w = pasang({ Mode_Akses: 'VIEW_UNDUH', Flag_Watermark: false });

        expect(w.text()).toContain('Tidak tersedia untuk berkas yang boleh diunduh');
    });
});

describe('ItemFields — izin contoh pengisian FORM', () => {
    /* Sejak 10 Okt 2026 izin unduh contoh pengisian FORM (PDF) dipilih per
       paket. Sebelumnya pemilihnya hanya untuk DOKUMEN, sehingga setiap item
       FORM lahir VIEW_ONLY tanpa pernah ditanya — dan server mengabaikannya. */
    const tombolIzin = (w, label) => w.findAll('.acc-seg-btn').find((b) => b.text().includes(label));

    it('menawarkan pemilih izin untuk item FORM, berlabel contoh pengisian', () => {
        const w = pasang({ Tipe_Item: 'FORM', Mode_Akses: 'VIEW_ONLY' });

        expect(tombolIzin(w, 'Hanya Lihat')).toBeTruthy();
        expect(tombolIzin(w, 'Boleh Unduh')).toBeTruthy();
        expect(w.text()).toContain('Izin Contoh Pengisian');
        expect(w.text()).toContain('Form kosong (lampiran) selalu bisa diunduh');
    });

    it('menulis pilihan izin FORM ke item', async () => {
        const w = pasang({ Tipe_Item: 'FORM', Mode_Akses: 'VIEW_ONLY', Flag_Watermark: true });

        await tombolIzin(w, 'Boleh Unduh').trigger('click');

        expect(w.props('form').Mode_Akses).toBe('VIEW_UNDUH');
        expect(w.props('form').Flag_Watermark).toBe(false);
    });

    it('tidak menawarkan pemilih izin untuk tipe tanpa berkas', () => {
        for (const tipe of ['VIDEO', 'KUIS', 'PARAGRAF', 'TAUTAN']) {
            const w = pasang({ Tipe_Item: tipe });

            expect(tombolIzin(w, 'Boleh Unduh'), `tipe ${tipe} menampilkan pemilih izin`).toBeFalsy();
        }
    });
});
