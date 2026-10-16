import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import SopDokumenPickerModal from './SopDokumenPickerModal.vue';

/* Dibaca dari berkasnya, bukan lewat `import ... from '*.css'`: vitest
   menyetubkan impor CSS jadi string kosong, sehingga setiap pemeriksaan
   "kelas ini ada" akan lulus/gagal karena alasan yang salah. */
const tema = readFileSync(resolve(process.cwd(), 'resources/js/Pages/LMS/SOP/Shared/support/sopTheme.css'), 'utf8');

vi.mock('axios');

/**
 * Modal "Pilih Dokumen SOP" — tampilannya, bukan datanya.
 *
 * Tombol footernya dulu memakai `stitch-btn-primary` dan `stitch-btn-ghost`,
 * dua nama yang TIDAK ADA di mana pun: tema modul ini memakai `.stitch-btn`
 * dengan pengubah `.primary` / `.secondary`. Akibatnya kedua tombol tampil
 * sebagai tombol peramban polos di tengah modal yang selebihnya rapi — dan
 * karena tak ada yang error, tak ada yang menangkapnya.
 */

/* Tiga bentuk yang harus dibedakan, dan sengaja TERPISAH: "belum dipakai paket
   mana pun" bukan hal yang sama dengan "belum punya versi berlaku". Dulu satu
   baris memikul keduanya, sehingga uji dampak bisa hijau hanya karena barisnya
   kebetulan tak bisa diklik. */
const ROWS = [
    {
        Id_SOP_Dokumen: 'dok-a',
        Judul: 'SOP Penanganan Keluhan',
        Nomor_SOP_Resmi: 'SOP-QA-001',
        Id_Versi_Aktif: 'v1',
        Jumlah_Paket_Pemakai: 3,
    },
    {
        Id_SOP_Dokumen: 'dok-b',
        Judul: 'SOP Cuci Tangan',
        Nomor_SOP_Resmi: null,
        Id_Versi_Aktif: 'v1',
        Jumlah_Paket_Pemakai: 0,
    },
    {
        Id_SOP_Dokumen: 'dok-c',
        Judul: 'SOP Belum Selesai',
        Nomor_SOP_Resmi: 'SOP-XX-001',
        Id_Versi_Aktif: null,
        Jumlah_Paket_Pemakai: 0,
    },
];

/* teleport di-stub supaya isinya tetap berada di dalam pohon komponen dan bisa
   ditanyai lewat wrapper. Kontrak teleportasinya sendiri — bahwa modal memang
   mendarat di <body> dan membawa .sop-scope — dijaga SopModal.test.js, bukan
   di sini; menstub tanpa penjaga terpisah berarti menguji sesuatu yang tak
   pernah benar-benar berjalan. */
async function pasang(rows = ROWS, props = {}) {
    axios.get = vi.fn(() => Promise.resolve({ data: { result: { rows, total: rows.length, dipotong: false } } }));

    // Dipasang tertutup lalu dibuka: watcher `show` yang memuat datanya tak
    // pernah menyala pada komponen yang lahir dalam keadaan terbuka.
    const w = mount(SopDokumenPickerModal, {
        props: { show: false, tipe: 'DOKUMEN', ...props },
        global: { stubs: { teleport: true } },
    });
    await w.setProps({ show: true });
    await flushPromises();

    return w;
}

/* Baris data saja. Kartu skeleton memakai kelas dasar yang sama (`dp-card`),
   jadi tanpa penyaring ini indeks baris bergeser saat komponen masih memuat —
   uji akan menuding baris yang salah dan pesannya menyesatkan. */
const kartu = (w) => w.findAll('.dp-card').filter((c) => !c.classes().includes('dp-card-skel'));

const tombol = (w, teks) => w.findAll('button').find((b) => new RegExp(teks, 'i').test(b.text()));

describe('SopDokumenPickerModal — tampilan', () => {
    it('memakai kelas tombol yang benar-benar ada di tema', async () => {
        const w = await pasang();

        for (const teks of ['Batal', 'Tautkan Dokumen']) {
            const b = tombol(w, teks);
            expect(b, `tombol "${teks}" tak ditemukan`).toBeTruthy();

            const kelas = b.classes().filter((c) => c !== 'text-truncate');
            expect(kelas, `tombol "${teks}" tanpa kelas tombol`).toContain('stitch-btn');

            /* Setiap pengubahnya harus benar-benar terdefinisi. Inilah yang
               dulu bocor: nama kelas yang tak cocok apa pun tak menghasilkan
               galat, hanya tombol tanpa gaya. */
            for (const c of kelas.filter((x) => x !== 'stitch-btn')) {
                expect(tema.includes(`.stitch-btn.${c}`), `.stitch-btn.${c} tak ada di sopTheme.css`).toBe(true);
            }
        }
    });

    it('tidak membiarkan dokumen tanpa versi berlaku dipilih', async () => {
        /* Item yang menunjuk dokumen kosong sah menurut skema, judulnya benar,
           kartunya terlihat lengkap — dan karyawan yang membukanya tak menerima
           apa pun. Peringatan saja tak cukup: sebelumnya barisnya tetap bisa
           diklik, dan admin yang buru-buru memilihnya tanpa membaca. */
        const w = await pasang();
        const baris = kartu(w);

        // Baris 1 belum dipakai paket mana pun TAPI punya versi — tetap boleh dipilih.
        expect(baris[1].classes()).not.toContain('is-disabled');

        expect(baris[2].classes()).toContain('is-disabled');
        expect(baris[2].attributes('disabled')).toBeDefined();

        await baris[2].trigger('click');
        expect(w.vm.terpilih, 'baris mati masih bisa dipilih').toBe(null);
    });

    it('menyebut berapa yang tak bisa dipilih, bukan menyembunyikannya', async () => {
        // Dokumen yang hilang sepenuhnya membuat admin mencari sesuatu yang ia
        // tahu ada, lalu menyimpulkan daftarnya yang salah.
        const w = await pasang();

        /* Hitungan agregat di kaki modal diganti PIL PER KARTU saat picker
           didesain ulang. Yang dijaga tetap sama: dokumen bermasalah tidak
           disembunyikan — tetap tampil, tetap terbaca, sebabnya di kartunya. */
        const tanpaVersi = kartu(w).filter((c) => c.find('.dp-status-pill.muted').exists());

        expect(tanpaVersi, 'dokumen tanpa versi disembunyikan dari daftar').toHaveLength(1);
        expect(tanpaVersi[0].text()).toContain('SOP Belum Selesai');
        expect(tanpaVersi[0].text()).toContain('Belum Ada Versi');
    });

    it('dokumen yang sudah ada di paket ini tak bisa dipilih lagi', async () => {
        /* Server memang menolaknya — satu SOP hanya boleh muncul sekali per
           paket, karena rollup kepatuhan berkunci (paket, versi) tanpa item —
           tapi penolakan yang baru datang setelah admin memilih,
           mengonfirmasi, dan menyimpan datang tiga langkah terlambat. */
        const w = await pasang(ROWS, { dipakai: ['dok-b'] });
        const baris = kartu(w);

        expect(baris[1].classes()).toContain('is-disabled');
        expect(baris[1].text()).toContain('Sudah Ada di Paket');

        await baris[1].trigger('click');
        expect(w.vm.terpilih).toBe(null);

        // Yang lain tetap bisa dipilih.
        expect(baris[0].classes()).not.toContain('is-disabled');
    });

    it('membedakan SEBAB matinya, bukan menyamakannya', async () => {
        /* "Sudah ada di paket ini" dan "belum punya versi berlaku" punya saran
           perbaikan yang berlawanan: yang satu tak boleh sama sekali di paket
           ini, yang satu lagi tinggal dilengkapi di Master. Menyamakannya jadi
           "tak bisa dipilih" mengirim admin ke arah yang salah. */
        const w = await pasang(ROWS, { dipakai: ['dok-b'] });

        /* Dua sebab, dua PIL berbeda — bukan satu kata "tak bisa dipilih"
           untuk keduanya. Yang diperiksa justru bahwa keduanya TIDAK sama. */
        const sudahAda = kartu(w).filter((c) => c.find('.dp-status-pill.warn').exists());
        const tanpaVersi = kartu(w).filter((c) => c.find('.dp-status-pill.muted').exists());

        expect(sudahAda, 'sebab "sudah ada di paket" tak ditandai').toHaveLength(1);
        expect(tanpaVersi, 'sebab "belum ada versi" tak ditandai').toHaveLength(1);
        expect(sudahAda[0].text()).toContain('Sudah Ada di Paket');
        expect(tanpaVersi[0].text()).toContain('Belum Ada Versi');
    });

    it('dokumen yang SEDANG dipakai item ini tak bisa dipilih ulang', async () => {
        /* Memilihnya tak mengubah apa pun — dan kendali yang tak melakukan
           apa-apa membuat orang mengira sistemnya yang tak merespons. Ia juga
           bukan peringatan: tak ada yang salah dengan dokumen yang justru
           sedang dipakai dengan benar. */
        const w = await pasang(ROWS, { sekarang: 'dok-a' });
        const baris = kartu(w);

        expect(baris[0].classes()).toContain('is-current');
        expect(baris[0].attributes('disabled')).toBeDefined();
        expect(baris[0].text()).toContain('Terpasang Saat Ini');

        // Bukan amber: penandanya netral, bukan tanda bahaya.
        expect(baris[0].find('.dp-status-pill.warn').exists()).toBe(false);
        expect(baris[0].find('.dp-status-pill.current').exists()).toBe(true);

        /* Sejak didesain ulang, dokumen yang sedang terpasang DIPILIH DI AWAL
           supaya strip bawah menunjukkan apa yang kini tertaut. Jadi yang
           dijaga bukan lagi "terpilih tetap null", melainkan yang sebenarnya
           penting: mengklik kartunya tidak MENGUBAH apa pun. */
        const sebelum = w.vm.terpilih;
        await baris[0].trigger('click');

        expect(w.vm.terpilih, 'kartu terpasang masih bisa mengubah pilihan').toBe(sebelum);
    });

    it('tidak ikut terhitung sebagai dokumen bermasalah', async () => {
        // Kalau ikut terhitung, catatan bawah akan menyebut angka yang lebih
        // besar daripada yang benar-benar bermasalah.
        const w = await pasang(ROWS, { sekarang: 'dok-a' });

        /* Dokumen yang justru sedang dipakai dengan BENAR tak boleh memakai
           penanda bahaya. Ia dapat pil netral 'current'; amber hanya untuk
           dokumen yang memang bentrok. */
        const sekarangKartu = kartu(w)[0];

        expect(sekarangKartu.find('.dp-status-pill.current').exists()).toBe(true);
        expect(sekarangKartu.find('.dp-status-pill.warn').exists()).toBe(false);
        expect(kartu(w).filter((c) => c.find('.dp-status-pill.warn').exists())).toHaveLength(0);
        expect(kartu(w).filter((c) => c.find('.dp-status-pill.muted').exists())).toHaveLength(1);
    });

    it('mengunci tombol pakai sampai ada yang dipilih', async () => {
        const w = await pasang();

        expect(tombol(w, 'Tautkan Dokumen').attributes('disabled')).toBeDefined();

        await kartu(w)[0].trigger('click');
        expect(tombol(w, 'Tautkan Dokumen').attributes('disabled')).toBeUndefined();
    });

    it('menyebut dokumen mana yang sedang dipilih', async () => {
        // Tombolnya berbunyi "Gunakan dokumen"; tanpa ini, kata "dokumen"
        // menunjuk sesuatu yang tak terlihat begitu daftarnya tergulir.
        const w = await pasang();
        await kartu(w)[0].trigger('click');

        expect(w.find('.dp-selected-name').text()).toContain('SOP Penanganan Keluhan');
    });

    it('memperingatkan dampak hanya bila dokumennya memang dipakai paket lain', async () => {
        const w = await pasang();

        await kartu(w)[1].trigger('click'); // 0 paket
        /* Stripnya kini selalu tampil begitu ada yang dipilih; TEKS-nya yang
           membedakan. Dokumen mandiri disebut "Mandiri" — bukan diam tanpa
           keterangan, yang dulu tak terbedakan dari "belum sempat dihitung". */
        expect(w.find('.dp-selected-sub').text()).toContain('Mandiri');
        expect(w.find('.dp-selected-sub').text()).not.toContain('Terhubung ke');

        await kartu(w)[0].trigger('click'); // 3 paket
        expect(w.find('.dp-selected-sub').text()).toContain('Terhubung ke 3 paket lain');
    });

    it('memuat dengan skeleton yang meniru bentuk barisnya', async () => {
        axios.get = vi.fn(() => new Promise(() => {})); // menggantung: tetap di keadaan memuat
        const w = mount(SopDokumenPickerModal, {
            props: { show: false, tipe: 'DOKUMEN' },
            global: { stubs: { teleport: true } },
        });
        await w.setProps({ show: true });

        // Bukan spinner di tengah kosong: tinggi daftarnya tak berubah saat data
        // datang, jadi tak ada lompatan tata letak tepat sebelum orang mengklik.
        expect(w.findAll('.dp-card.dp-card-skel').length).toBeGreaterThan(0);
        expect(w.find('.spinner-border').exists()).toBe(false);
    });

    it('klik ganda memilih sekaligus memakai', async () => {
        const w = await pasang();

        await kartu(w)[0].trigger('dblclick');

        expect(w.emitted('pilih')[0][0].Judul).toBe('SOP Penanganan Keluhan');
        expect(w.emitted('close')).toHaveLength(1);
    });
});
