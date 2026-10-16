import { describe, expect, it } from 'vitest';
import {
    alasanTakBisa,
    bagianKembar,
    bisaDipilih,
    kesepadanan,
    kunciBagian,
    perAlatTes,
} from './kelayakanPaket.js';

/*
 | KENAPA UJI INI ADA.
 |
 | Sampai 9 September 2026 berkas ini menjawab kelayakan dari `paket.Dimensi` dan
 | `paket.Detail[].Kode_Kompetensi` — dua field yang TIDAK LAGI DIKIRIM sejak HC Learn
 | mengganti kontraknya menjadi ujian → alat tes → bagian tanpa label.
 |
 | Akibatnya `cocokDimensi()` memulangkan false untuk SELURUH 27 ujian di katalog: setiap
 | kartu mati, tombol "Gunakan Paket Ini" tidak pernah dirender, dan tab "Rekomendasi"
 | menghitung nol. Layarnya tidak bisa dipakai sama sekali — dan seluruh suite Vue tetap
 | hijau, karena tidak ada satu pun uji yang menyentuh aturan kelayakannya.
 |
 | Uji ini memakai BENTUK BALASAN HC LEARN YANG SEBENARNYA (diambil dari
 | `api/v1/pa/nama-tes` pada 9 September 2026), supaya kalau kontraknya bergeser lagi,
 | yang memerah adalah berkas ini — bukan admin yang menatap daftar kosong.
 */

/** Kompetensi wajib dalam bentuk yang dikirim `matriks()`: dikelompokkan per dimensi. */
const wajib3 = [
    { dimensi_kode: 'BEHAVIOR', dimensi_nama: 'Behavior', kompetensi: [{ kode: 'B01' }, { kode: 'B02' }] },
    { dimensi_kode: 'CORE_VALUE', dimensi_nama: 'Core Value', kompetensi: [{ kode: 'CV01' }] },
];

function bagian(alat, soal, nama = 'Bagian') {
    return {
        id_jenis_indikator: String(alat),
        id_jenis_soal: String(soal),
        nama_alat_tes: `Alat ${alat}`,
        nama,
        kode_soal: `K${soal}`,
    };
}

function ujian(daftarBagian) {
    return {
        Id_Master_Ujian: '100162',
        Nama_Ujian: 'Employee Assessment 360 - OFFICER',
        Id_Master_Paket_Ujian: '100149',
        Nama_Paket: 'Employee Assessment 360 - OFFICER',
        Bagian: daftarBagian,
        jumlah_bagian: daftarBagian.length,
        bagian_tersedia: daftarBagian.length > 0,
    };
}

describe('kelayakanPaket — identitas bagian', () => {
    it('mengunci identitas pada PASANGAN (alat tes, bank soal), bukan bank soalnya saja', () => {
        /* Satu bank soal yang sama muncul di DUA alat tes berbeda adalah kasus yang sah dan
           memang terjadi di katalog HC Learn. Kalau identitasnya cuma id_jenis_soal, kedua
           bagian ini terbaca kembar dan ujiannya ditolak tanpa sebab. */
        const a = bagian(100174, 100158);
        const b = bagian(100175, 100158);

        expect(kunciBagian(a)).not.toBe(kunciBagian(b));
        expect(bagianKembar(ujian([a, b]))).toEqual([]);
    });

    it('melaporkan bagian yang benar-benar kembar', () => {
        const kembar = bagianKembar(ujian([
            bagian(100174, 100158),
            bagian(100174, 100158),
            bagian(100174, 100159),
        ]));

        expect(kembar).toEqual(['100174|100158']);
    });

    it('mengelompokkan bagian per alat tes sesuai urutan kiriman', () => {
        const kelompok = perAlatTes(ujian([
            bagian(100174, 1, 'Analytical Thinking'),
            bagian(100175, 2, 'Analytical Thinking'),
            bagian(100174, 3, 'Integrity'),
        ]));

        expect(kelompok.map((a) => a.id)).toEqual(['100174', '100175']);
        expect(kelompok[0].bagian).toHaveLength(2);
        expect(kelompok[1].bagian).toHaveLength(1);
    });
});

describe('kelayakanPaket — kesepadanan cacah', () => {
    it('menyatakan sepadan ketika cacah bagian sama dengan cacah kompetensi wajib', () => {
        const u = ujian([bagian(1, 1), bagian(1, 2), bagian(2, 3)]);

        expect(kesepadanan(u, wajib3)).toEqual({ bagian: 3, wajib: 3, sepadan: true, selisih: 0 });
        expect(bisaDipilih(u, wajib3)).toBe(true);
        expect(alasanTakBisa(u, wajib3)).toBe('');
    });

    it('menolak ketika bagiannya kurang, dan menyebut kedua angkanya', () => {
        const u = ujian([bagian(1, 1), bagian(1, 2)]);

        expect(kesepadanan(u, wajib3).selisih).toBe(-1);
        expect(bisaDipilih(u, wajib3)).toBe(false);
        expect(alasanTakBisa(u, wajib3)).toContain('2 bagian');
        expect(alasanTakBisa(u, wajib3)).toContain('3 kompetensi');
    });

    it('menolak ketika bagiannya berlebih — bukan hanya ketika kurang', () => {
        /* Bagian berlebih tidak bisa dibiarkan lewat: satu bagian mewakili tepat satu
           kompetensi, jadi kelebihannya PASTI tidak punya kompetensi untuk dipegang, dan
           gerbang simpan di server akan menolaknya dengan JUMLAH_TAK_SEPADAN. Kartu yang
           tetap hidup di sini hanya memindahkan penolakannya ke tempat yang lebih jauh. */
        const u = ujian([bagian(1, 1), bagian(1, 2), bagian(1, 3), bagian(2, 4)]);

        expect(bisaDipilih(u, wajib3)).toBe(false);
        expect(alasanTakBisa(u, wajib3)).toContain('4 bagian');
    });
});

describe('kelayakanPaket — penghalang yang disebut lebih dulu', () => {
    it('menyebut ujian tanpa bagian, bukan menyebutnya tidak sepadan', () => {
        const u = ujian([]);

        expect(bisaDipilih(u, wajib3)).toBe(false);
        expect(alasanTakBisa(u, wajib3)).toContain('tidak punya bagian');
    });

    it('menyebut kekembaran lebih dulu daripada cacah', () => {
        /* Urutannya cermin `PaBijeksiLabel`: selama identitasnya ambigu, angka cacah apa pun
           menyesatkan admin ke arah yang salah. */
        const u = ujian([bagian(1, 1), bagian(1, 1), bagian(2, 2)]);

        expect(alasanTakBisa(u, wajib3)).toContain('kembar');
        expect(alasanTakBisa(u, wajib3)).not.toContain('3 kompetensi');
    });

    it('menyebut level yang belum punya syarat kompetensi', () => {
        const u = ujian([bagian(1, 1)]);

        expect(bisaDipilih(u, [])).toBe(false);
        expect(alasanTakBisa(u, [])).toContain('Syarat Kompetensi');
    });
});
