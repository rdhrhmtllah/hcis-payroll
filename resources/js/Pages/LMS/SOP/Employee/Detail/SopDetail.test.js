import { describe, expect, it } from 'vitest';
import SopDetail from './SopDetail.vue';

/**
 * Formatter yang dipanggil template tapi tak pernah didefinisikan.
 *
 * Baris ukuran berkas ada di dua tempat dalam template ini, keduanya di balik
 * `v-if="it.Ukuran_File"`. Selama payload-nya tak membawa Ukuran_File, barisnya
 * tak pernah dirender dan tak ada yang tahu fungsinya tak ada. Begitu backend
 * mulai mengirimkannya — pada dokumen yang baru diperbarui — halaman DETAIL SOP
 * yang dibuka karyawan mati total: memanggil undefined melempar, bukan sekadar
 * memperingatkan seperti interpolasi biasa.
 *
 * Diuji sebagai fungsi lepas, bukan lewat mount: yang rusak adalah keberadaan
 * dan perilaku formatter-nya, dan me-mount seluruh halaman menuntut selusin stub
 * yang tak satu pun ikut membuktikannya.
 */
const fmt = (b) => SopDetail.methods.fmtFileSize.call(SopDetail.methods, b);

/**
 * Kapan kuis dianggap selesai urusannya.
 *
 * Aturan ini WAJIB mencerminkan penjagaan di SopKuisDispatchService::launch(),
 * yang menolak dengan 409 bila item sudah SELESAI/DIKONFIRMASI atau jatah
 * percobaan habis. Layar yang menjawab lain menawarkan tombol yang server pasti
 * tolak — dan penolakan yang tak bisa dijelaskan lebih merusak kepercayaan
 * daripada tombol yang memang tak ada.
 */
const kuisFinal = (it) => SopDetail.methods.kuisFinal.call({ isDone: SopDetail.methods.isDone }, it);

describe('SopDetail — kuisFinal', () => {
    it('peserta yang LULUS tak lagi ditawari mengulang', () => {
        /* Gejala yang dilaporkan: skor 80, batas 80, LULUS, Dikonfirmasi — dan
           tombol "Ulangi Ujian" masih bisa ditekan, lalu dibalas 409. Aturan
           lamanya berbunyi "final bila jatah habis DAN tidak lulus", sehingga
           kelulusan justru membatalkan kefinalan. */
        expect(
            kuisFinal({
                Status: 'SELESAI',
                Status_Lulus: 'Y',
                Skor_Quiz: 80,
                Jumlah_Percobaan: 1,
                Maks_Percobaan: 1,
            }),
        ).toBe(true);
    });

    it('status DIKONFIRMASI juga menutup', () => {
        expect(kuisFinal({ Status: 'DIKONFIRMASI', Jumlah_Percobaan: 1, Maks_Percobaan: 1 })).toBe(true);
    });

    it('gagal dengan jatah habis tetap final', () => {
        // Perilaku lama yang memang benar — jangan ikut hilang saat diperbaiki.
        expect(
            kuisFinal({
                Status: 'DIBUKA',
                Status_Lulus: 'T',
                Jumlah_Percobaan: 1,
                Maks_Percobaan: 1,
            }),
        ).toBe(true);
    });

    it('masih ada jatah dan belum tuntas — boleh dicoba lagi', () => {
        expect(
            kuisFinal({
                Status: 'DIBUKA',
                Status_Lulus: 'T',
                Jumlah_Percobaan: 1,
                Maks_Percobaan: 2,
            }),
        ).toBe(false);
    });

    it('belum pernah dicoba sama sekali bukan final', () => {
        expect(kuisFinal({ Status: 'BELUM', Jumlah_Percobaan: 0, Maks_Percobaan: 1 })).toBe(false);
    });

    it('tanpa plafon percobaan, yang menutup hanya penyelesaiannya', () => {
        // Maks_Percobaan 0/null = tak berbatas; jangan mengunci karena angka nol.
        expect(kuisFinal({ Status: 'DIBUKA', Jumlah_Percobaan: 9, Maks_Percobaan: 0 })).toBe(false);
        expect(kuisFinal({ Status: 'SELESAI', Jumlah_Percobaan: 9, Maks_Percobaan: 0 })).toBe(true);
    });
});

describe('SopDetail — fmtFileSize', () => {
    it('ada sebagai method, bukan cuma dipanggil template', () => {
        expect(typeof SopDetail.methods.fmtFileSize).toBe('function');
    });

    it('byte mentah tetap byte', () => {
        expect(fmt(512)).toBe('512 B');
    });

    it('naik satuan pada kelipatan 1024', () => {
        expect(fmt(242768)).toBe('237 KB');
        expect(fmt(1024 * 1024 * 3)).toBe('3.0 MB');
    });

    it('nilai tak masuk akal jadi kosong, bukan "0 B"', () => {
        // Barisnya sudah ber-v-if; menulis "0 B" untuk data rusak justru
        // menampilkan angka yang tak pernah benar.
        expect(fmt(null)).toBe('');
        expect(fmt(0)).toBe('');
        expect(fmt('bukan angka')).toBe('');
    });
});
