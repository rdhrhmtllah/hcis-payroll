import { describe, expect, it } from 'vitest';
import { namaOpsiUjian, petakanOpsiUjian } from './ujian';

/**
 * KONTRAK BENTUK DATA HC LEARN, dikunci di satu tempat.
 *
 * Baris di bawah disalin apa adanya dari balasan
 * GET api/v1/penjadwalan/training/options pada 10 Sep 2026 — bukan karangan.
 * Kalau HC Learn mengubah nama kolomnya, berkas inilah yang merah lebih dulu,
 * bukan dropdown yang diam-diam terisi opsi kosong.
 *
 * Cacat yang ditutupnya: Wizard Versi menyimpan balasan MENTAH, lalu
 * komponennya membaca `u.value`/`u.label` yang tak pernah ada. Dua paket ujian
 * yang benar-benar dikirim server tergambar sebagai dua baris kosong yang tak
 * bisa diklik — tanpa satu pun pesan galat, karena API-nya memang membalas 200.
 */
const BARIS_ASLI = [
    {
        Id_Master_Ujian: 'voZA4N1G13LgM2bPXowrmenp7Qjd4xR0pR5YyvZWz6qOlK9Ba0kNVAGDd7q2LBlg',
        Nama_Ujian: 'PRE TEST - WEB DEVELOPEMENT',
        Kode_Ujian: 'EVO-PTWB-TNG',
        Jumlah_Soal: 10,
        Nama_Paket: 'PRE TEST - TRAINING WEB DEVELOPEMENT',
        detail: [],
    },
];

describe('petakanOpsiUjian', () => {
    it('memetakan balasan HC Learn apa adanya menjadi value/label', () => {
        const [opsi] = petakanOpsiUjian(BARIS_ASLI);

        expect(opsi.value).toBe(BARIS_ASLI[0].Id_Master_Ujian);
        expect(opsi.label).toBe('PRE TEST - WEB DEVELOPEMENT');
        expect(opsi.kode).toBe('EVO-PTWB-TNG');
    });

    /* UJI PENOLAKAN. Tanpa ini, pemeta yang mengembalikan `rows` apa adanya
       pun lulus — dan itu PERSIS cacat yang berkas ini ada untuk menangkapnya. */
    it('TIDAK meloloskan baris mentah: kunci lama tak lagi terbaca', () => {
        const [opsi] = petakanOpsiUjian(BARIS_ASLI);

        expect(opsi.Id_Master_Ujian).toBeUndefined();
        expect(opsi.Nama_Ujian).toBeUndefined();
    });

    it('nama kosong jatuh ke tokennya, bukan jadi opsi tanpa tulisan', () => {
        const [opsi] = petakanOpsiUjian([{ Id_Master_Ujian: 'tok1', Nama_Ujian: '' }]);

        expect(opsi.label).toBe('Ujian tok1');
    });

    it('baris tanpa token dibuang', () => {
        const hasil = petakanOpsiUjian([
            { Id_Master_Ujian: '', Nama_Ujian: 'Tanpa token' },
            { Nama_Ujian: 'Juga tanpa token' },
            { Id_Master_Ujian: 'tok2', Nama_Ujian: 'Sah' },
        ]);

        expect(hasil).toHaveLength(1);
        expect(hasil[0].value).toBe('tok2');
    });

    it('balasan yang bukan larik tak merobohkan layar', () => {
        expect(petakanOpsiUjian(null)).toEqual([]);
        expect(petakanOpsiUjian(undefined)).toEqual([]);
        expect(petakanOpsiUjian({ result: [] })).toEqual([]);
    });
});

describe('namaOpsiUjian', () => {
    it('mengembalikan label untuk token yang dikenali', () => {
        const opsi = petakanOpsiUjian(BARIS_ASLI);

        expect(namaOpsiUjian(opsi, BARIS_ASLI[0].Id_Master_Ujian)).toBe('PRE TEST - WEB DEVELOPEMENT');
    });

    /* Jalur simpan Wizard dulu mencari `u.nama` — nama kunci KETIGA yang tak
       ada di API maupun di komponennya — sehingga Nama_Ujian SELALU null
       walaupun ujiannya jelas terpilih. */
    it('token yang tak dikenali menghasilkan null, bukan undefined diam-diam', () => {
        expect(namaOpsiUjian(petakanOpsiUjian(BARIS_ASLI), 'token-asing')).toBeNull();
        expect(namaOpsiUjian([], 'apa pun')).toBeNull();
        expect(namaOpsiUjian(null, null)).toBeNull();
    });
});
