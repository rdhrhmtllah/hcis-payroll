import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BarisDokumen from './BarisDokumen.vue';
import { bangunGrup, terapkanSaringan } from './sumbu.js';

const DOK = {
    Id_SOP_Paket_Item: 'itm1',
    Id_SOP_Paket: 'pkt1',
    Nama_Paket: 'Tata Tertib & Kepegawaian',
    Judul: 'Kebijakan Perlindungan Data Pribadi',
    Nomor_SOP: 'PLC/EHG/ACC/03',
    No_Dokumen: 3,
    Tipe_Item: 'DOKUMEN',
    Wajib: true,
    Section: 'Pembuka',
    Urutan: 1,
    Tanpa_Versi: false,
    Status_Item: 'BELUM',
    Departemen: [{ id: 5, nama: 'Accounting', kode: null, level: null }],
    Jenis_Dokumen: [{ id: 8, nama: 'Kebijakan', kode: null, level: 1 }],
    Apqc: [],
    Grup: 'perlu_dibaca',
    Tenggat: '2026-09-16',
    Is_Terlambat: false,
    Sumber: 'MANUAL',
    Prioritas: 'NORMAL',
    Estimasi: 29,
    Total_Item: 1,
    Item_Selesai: 0,
    Persen: 0,
    Item_Perlu_Ulang: 0,
};

const pasang = (over = {}) => mount(BarisDokumen, { props: { d: { ...DOK, ...over } } });

describe('BarisDokumen — tautan', () => {
    /*
     * Inti fitur ini. Tautan yang kehilangan ?item= tetap membuka halaman yang
     * benar, jadi tak ada yang tampak rusak — orang hanya mendarat di puncak
     * paket berisi belasan item dan harus mencari sendiri dokumen yang baru saja
     * ia klik. Persis pekerjaan yang tautan ini dimaksudkan untuk menghapus.
     */
    it('menuju PAKET induknya, membawa item yang diklik', () => {
        expect(pasang().find('a').attributes('href')).toBe('/sop/pkt1?item=itm1');
    });

    it('tidak menuju pembaca dokumen langsung', () => {
        // Melompat ke /baca/ melewati gerbang presensi, urutan section, dan
        // prasyarat antar-item — semuanya hidup di halaman paket.
        expect(pasang().find('a').attributes('href')).not.toContain('/baca/');
    });
});

describe('BarisDokumen — identitas', () => {
    it('menampilkan nomor resmi dan judul dokumen', () => {
        const t = pasang().text();

        expect(t).toContain('PLC/EHG/ACC/03');
        expect(t).toContain('Kebijakan Perlindungan Data Pribadi');
    });

    it('menyebut jenis, departemen, dan paket induknya', () => {
        const t = pasang().text();

        expect(t).toContain('Kebijakan');
        expect(t).toContain('Accounting');
        expect(t).toContain('Tata Tertib & Kepegawaian');
    });

    it('dokumen tak bertuan disebut, bukan dikosongkan', () => {
        const w = pasang({ Departemen: [{ id: null, nama: null }] });

        expect(w.text()).toContain('Tanpa pemilik');
    });

    it('item tanpa versi aktif ditandai — bacaan yang tak berdiri di atas versi sah', () => {
        expect(pasang({ Tanpa_Versi: true }).text()).toContain('Tanpa versi aktif');
    });

    it('item opsional ditandai supaya tak dikira wajib', () => {
        expect(pasang({ Wajib: false }).text()).toContain('opsional');
    });
});

describe('BarisDokumen — status', () => {
    it.each([
        [{ Status_Item: 'BELUM' }, 'Belum dibaca'],
        [{ Status_Item: 'DIBUKA' }, 'Sedang dibaca'],
        [{ Status_Item: 'DIKONFIRMASI' }, 'Tuntas'],
        [{ Status_Item: 'SELESAI' }, 'Tuntas'],
    ])('%o → %s', (over, teks) => {
        expect(pasang(over).find('.bd-status').text()).toContain(teks);
    });

    it('"perlu dibaca ulang" menang atas "belum dibaca"', () => {
        // Keduanya sama-sama belum tuntas, tapi yang satu berarti versinya
        // berganti SESUDAH orang ini mengonfirmasinya — keterangan yang hilang
        // bila keduanya ditulis sama.
        const w = pasang({ Status_Item: 'BELUM', Item_Perlu_Ulang: 1 });

        expect(w.find('.bd-status').text()).toContain('Perlu dibaca ulang');
    });
});

/*
 * Baris dokumen sengaja dibentuk MENYERUPAI kartu paket supaya seluruh
 * penyaringan & pengelompokan yang sudah ada bekerja tanpa cabang khusus.
 * Uji di bawah menuntut kesamaan bentuk itu benar-benar berlaku — kalau tidak,
 * tampilan Dokumen akan diam-diam mengabaikan saringan yang sedang aktif.
 */
describe('baris dokumen memakai mesin saringan & sumbu yang sama', () => {
    const tema = () => ({ color: '#111', icon: 'x' });
    const dok = (over) => ({ ...DOK, ...over });

    it('pengelompokan per departemen jadi EKSAK — tak ada penempatan ganda', () => {
        const r = bangunGrup(
            [
                dok({ Departemen: [{ id: 1, nama: 'HSE' }] }),
                dok({ Departemen: [{ id: 2, nama: 'HR' }] }),
                dok({ Departemen: [{ id: 1, nama: 'HSE' }] }),
            ],
            'departemen',
            tema,
        );

        // Inilah keuntungan tampilan ini: satu dokumen satu departemen, jadi
        // jumlah kelompok kembali menjumlah tepat ke jumlah baris.
        expect(r.tugas).toBe(3);
        expect(r.penempatan).toBe(3);
        expect(r.grup).toHaveLength(2);
    });

    it('saringan departemen bekerja pada baris dokumen', () => {
        const data = [dok({ Departemen: [{ id: 1, nama: 'HSE' }] }), dok({ Departemen: [{ id: 2, nama: 'HR' }] })];

        expect(terapkanSaringan(data, { departemen: ['1'] }, () => 9)).toHaveLength(1);
    });

    it('saringan tenggat & sumber yang diwarisi dari paket induk ikut bekerja', () => {
        const data = [
            dok({ Sumber: 'MANDIRI', Tenggat: null }),
            dok({ Sumber: 'MANUAL', Tenggat: '2026-09-16' }),
        ];

        expect(terapkanSaringan(data, { sumber: 'MANDIRI' }, () => 9)).toHaveLength(1);
        expect(terapkanSaringan(data, { tenggat: 'tanpa' }, () => 9)).toHaveLength(1);
    });

    it('persen kelompok terbaca "berapa dokumen tuntas dari berapa dokumen"', () => {
        const r = bangunGrup(
            [
                dok({ Departemen: [{ id: 1, nama: 'HSE' }], Persen: 100, Item_Selesai: 1 }),
                dok({ Departemen: [{ id: 1, nama: 'HSE' }] }),
            ],
            'departemen',
            tema,
        );

        expect(r.grup[0].persen).toBe(50);
    });
});
