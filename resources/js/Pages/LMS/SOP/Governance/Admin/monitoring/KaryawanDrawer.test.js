import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import KaryawanDrawer from './KaryawanDrawer.vue';

vi.mock('axios');

/**
 * Drawer detail karyawan — RINGKASAN, bukan riwayat.
 *
 * Berkas ini pernah menguji tiga hal yang tak lagi dimiliki drawer: tab, tab
 * "Bukti Konfirmasi", dan linimasa riwayat. Sejak monitoring karyawan menjadi
 * HALAMAN sendiri, linimasanya pindah ke SopMonitoringKaryawan dan drawer ini
 * tinggal memuat satu ringkasan. Akibatnya lima ujinya merah berminggu-minggu
 * sambil tak menjaga apa pun.
 *
 * Ujinya TIDAK dihapus begitu saja. Tiga penjaga yang isinya masih berlaku
 * dipindahkan ke SopMonitoringKaryawan.test.js — tempat perilakunya sekarang
 * benar-benar berjalan:
 *   - alasan mesin sebagai cadangan saat sebab manusia kosong
 *   - "Tercatat sistem" saat pelakunya bukan manusia
 *   - nota entri tersembunyi, termasuk sisi negatifnya
 *
 * Yang tinggal di sini hanya yang memang milik drawer: ia memuat SATU
 * ringkasan, dan tak menyentuh endpoint yang sudah dilepas.
 */
function pasang(hasil = {}) {
    axios.get = vi.fn(() => Promise.resolve({
        data: {
            success: true,
            result: {
                ringkas: { Nama: 'Budi Santoso', Kode_Karyawan: 'A1', Jabatan: 'Operator' },
                paket: [{ Id_SOP_Paket: 1, Nama_Paket: 'K3 Dasar', Persen: 40 }],
                ...hasil,
            },
        },
    }));

    /* Dipasang TERTUTUP lalu dibuka: pemuatnya digantung pada watcher `show`,
       yang tak pernah menyala pada komponen yang lahir dalam keadaan terbuka.
       Mount langsung dengan show:true menghasilkan drawer yang tak pernah
       memanggil apa pun — dan uji yang menegaskan "nol permintaan" akan lulus
       karena alasan yang sepenuhnya salah. */
    const w = mount(KaryawanDrawer, {
        props: { show: false, kode: 'A1', token: 'tok', nama: 'Budi Santoso' },
        attachTo: document.body,
    });

    return w;
}

async function buka(w) {
    await w.setProps({ show: true });
    await flushPromises();

    return w;
}

describe('KaryawanDrawer', () => {
    /* Isi yang diteleportasi bertahan di <body> setelah uji selesai; tanpa
       dibersihkan, uji berikutnya membaca sisa drawer sebelumnya dan lulus
       karena data tetangganya. */
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('memuat satu ringkasan saja — bukan riwayat, bukan atestasi', async () => {
        /* Dua endpoint yang dulu ikut dipanggil sudah dilepas. Kalau salah
           satunya kembali diam-diam, drawer membayar permintaan untuk data
           yang tak pernah ia tampilkan — dan pada halaman monitoring, drawer
           ini dibuka berkali-kali per sesi. */
        const w = await buka(pasang());

        const url = axios.get.mock.calls.map((c) => String(c[0]));

        expect(url).toHaveLength(1);
        expect(url[0]).toContain('/karyawan/');
        expect(url.some((u) => u.includes('/atestasi')), 'endpoint atestasi dipanggil lagi').toBe(false);
        expect(url.some((u) => u.includes('/riwayat')), 'riwayat diambil di dua tempat').toBe(false);
    });

    it('menyebut identitas orangnya, bukan cuma namanya', async () => {
        /* Nama saja tak cukup membedakan orang pada perusahaan dengan nama
           berulang; NIK dan jabatannya ikut ditampilkan. */
        await buka(pasang());

        /* Ditanyakan ke DOKUMEN, bukan ke wrapper: HcisDrawer memindahkan
           isinya ke <body>, sehingga w.text() mengembalikan string kosong dan
           setiap assertion "tak memuat X" akan lulus tanpa memeriksa apa pun. */
        const teks = document.body.textContent;

        expect(teks).toContain('Budi Santoso');
        expect(teks).toContain('A1');
        expect(teks).toContain('Operator');
    });

    it('menerima paket sebagai daftar maupun sebagai objek berisi items', async () => {
        /* Dua bentuk yang benar-benar datang dari server. Bentuk kedua pernah
           membuat daftarnya kosong tanpa galat apa pun. */
        const langsung = await buka(pasang());
        expect(langsung.vm.paket).toHaveLength(1);

        const terbungkus = await buka(pasang({ paket: { items: [{ Id_SOP_Paket: 9, Nama_Paket: 'Mutu' }] } }));
        expect(terbungkus.vm.paket).toHaveLength(1);
        expect(terbungkus.vm.paket[0].Nama_Paket).toBe('Mutu');
    });
});
