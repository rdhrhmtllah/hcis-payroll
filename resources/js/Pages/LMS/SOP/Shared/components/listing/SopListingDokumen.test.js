import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopListingDokumen from './SopListingDokumen.vue';

/*
 * Komponen ini adalah SATU-SATUNYA tempat daftar paket & dokumen digambar, dan
 * dua halaman yang memakainya bekerja sangat berbeda: Functional Document
 * mengelompokkan di memori tanpa paginasi, Public Document mengelompokkan di
 * server dengan paginasi. Yang dijaga di sini adalah bahwa perbedaan itu tidak
 * bocor ke tampilannya.
 */

const GRUP = [
    { nama: 'HSE', warna: '#16a34a', ikon: 'shield', total: 3, persen: 40, kosong: false },
    { nama: 'Tanpa pemilik', warna: '#64748b', ikon: 'folder2-open', total: 1, persen: 0, kosong: true },
];

const PAKET = (n) =>
    Array.from({ length: n }, (_, i) => ({
        Id_SOP_Paket: `p${i}`,
        Nama_Paket: `Paket ${i}`,
        Departemen: [],
        Total_Item: 2,
        Item_Selesai: 0,
        Persen: 0,
    }));

const STUB = {
    HcisCard: { template: '<div><slot /></div>' },
    HcisButton: { props: ['label', 'disabled'], template: '<button :disabled="disabled">{{ label }}</button>' },
    HcisIcon: true,
    HcisSkeleton: true,
    HcisEmptyState: { template: '<div class="kosong"><slot name="actions" /></div>' },
    RailSumbu: true,
    BarisPaket: true,
    BarisDokumen: true,
    SopTugasCard: { props: ['tugas'], template: '<article class="kartu"><slot name="aksi" :tugas="tugas" /></article>' },
};

function pasang(props = {}, slots = {}) {
    return mount(SopListingDokumen, {
        props: { items: PAKET(3), grup: GRUP, ...props },
        slots,
        global: { stubs: STUB },
    });
}

describe('SopListingDokumen — paginasi milik pemanggil', () => {
    it('tidak merender bilah paginasi bila pemanggil tak mengirimnya', () => {
        expect(pasang().find('.mk-paging').exists()).toBe(false);
    });

    it('tidak merender bilah paginasi bila himpunannya cuma satu halaman', () => {
        const w = pasang({ paginasi: { halaman: 1, halamanTotal: 1, total: 3 } });
        expect(w.find('.mk-paging').exists()).toBe(false);
    });

    it('merender bilah paginasi bila halamannya lebih dari satu', () => {
        const w = pasang({ paginasi: { halaman: 2, halamanTotal: 5, total: 60 } });
        expect(w.find('.mk-paging').exists()).toBe(true);
        expect(w.find('.mk-paging-info').text()).toContain('Halaman 2 dari 5');
    });

    it('meminta halaman berikutnya lewat emit, bukan memuat sendiri', async () => {
        const w = pasang({ paginasi: { halaman: 2, halamanTotal: 5, total: 60 } });
        await w.findAll('.mk-paging button')[1].trigger('click');
        expect(w.emitted('update:halaman')[0]).toEqual([3]);
    });
});

describe('SopListingDokumen — angka yang disebut toolbar', () => {
    /*
     * Saat daftar dipotong di server, yang layak disebut adalah UKURAN
     * HIMPUNAN. Menyebut panjang larik yang kebetulan termuat membuat rak
     * berisi 60 paket tertulis "12 paket" tepat di sebelah rail yang menghitung
     * 60 — dua angka yang bertentangan pada layar yang sama.
     */
    it('memakai total server saat berpaginasi, bukan panjang halaman', () => {
        const w = pasang({ items: PAKET(12), paginasi: { halaman: 1, halamanTotal: 5, total: 60 } });
        expect(w.find('.tk-info').text()).toContain('60');
    });

    it('memakai panjang larik saat pemanggil memuat semuanya', () => {
        expect(pasang({ items: PAKET(7) }).find('.tk-info').text()).toContain('7');
    });

    it('menghitung dokumen, bukan paket, pada bentuk tampilan Dokumen', () => {
        const w = pasang({
            tampilan: 'dokumen',
            dokumen: [{ Id_SOP_Paket_Item: 'i1' }, { Id_SOP_Paket_Item: 'i2' }],
            paginasi: { halaman: 1, halamanTotal: 5, total: 60 },
        });
        expect(w.find('.tk-info').text()).toContain('2');
        expect(w.find('.tk-info').text()).toContain('dokumen');
    });
});

describe('SopListingDokumen — kendali tetap di pemanggil', () => {
    it('meneruskan pilihan sumbu & kelompok tanpa mengubah keadaannya sendiri', async () => {
        const w = pasang({
            sumbuTersedia: [{ key: 'jenis', label: 'Jenis Dokumen', ikon: 'journals' }],
            sumbuAktif: 'departemen',
        });
        /* Pemindah sumbu bukan lagi deretan tombol .sm-pindah-btn melainkan
           HcisSelect, jadi yang dipicu emit-nya, bukan klik. Bentuknya berubah;
           kontraknya tidak. */
        const pemilih = w.findComponent({ name: 'HcisSelect' });

        expect(pemilih.exists(), 'pemilih sumbu tak ditemukan').toBe(true);
        await pemilih.vm.$emit('update:model-value', 'jenis');

        expect(w.emitted('update:sumbu')[0]).toEqual(['jenis']);

        /* Tidak ikut menandai dirinya aktif: kebenaran keadaan ada di pemanggil,
           dan komponen yang menebak sendiri akan menyorot kelompok yang gagal
           dimuat. Sekarang terbaca dari model-value yang MASIH mengikuti prop. */
        expect(pemilih.props('modelValue')).toBe('departemen');
    });

    it('mencabut pil saringan lewat emit', async () => {
        const w = pasang({ saringanAktif: [{ kunci: 'lensa', label: 'Lensa', nilai: 'Rak Saya' }] });
        await w.find('.tk-chip-del').trigger('click');
        expect(w.emitted('hapus-saringan')[0]).toEqual(['lensa']);
    });

    it('menyediakan slot aksi pada kartu untuk halaman yang memerlukannya', () => {
        const w = pasang({}, { 'aksi-kartu': '<button class="fav">fav</button>' });
        expect(w.findAll('.kartu .fav')).toHaveLength(3);
    });

    it('tidak menyisipkan apa pun ke kartu bila halaman tak mengisi slotnya', () => {
        expect(pasang().find('.kartu .fav').exists()).toBe(false);
    });
});

describe('SopListingDokumen — keadaan kosong & memuat', () => {
    it('menampilkan kerangka saat memuat, bukan keadaan kosong', () => {
        const w = pasang({ items: [], loading: true });
        expect(w.find('.tk-skel').exists()).toBe(true);
        expect(w.find('.kosong').exists()).toBe(false);
    });

    it('menampilkan keadaan kosong beserta aksinya saat tak ada isi', () => {
        const w = pasang({ items: [] }, { 'kosong-aksi': '<button class="reset">reset</button>' });
        expect(w.find('.kosong .reset').exists()).toBe(true);
    });

    it('menyembunyikan meter kelompok saat rak tak punya progres', () => {
        const w = pasang({ grupTerpilih: 'HSE', bar: false });
        expect(w.find('.mk-meter').exists()).toBe(false);
    });

    it('menampilkan meter kelompok saat progres memang ada', () => {
        const w = pasang({ grupTerpilih: 'HSE' });
        expect(w.find('.mk-pct').text()).toBe('40%');
    });
});
