/**
 * Arti dan perilaku yang diharapkan dari sebuah kompetensi — SATU sumber: Master Kompetensi
 * (N_PA_Competency.Deskripsi & Expected_Behaviour, DDL-2026-166).
 *
 * Sampai 6 Oktober 2026 berkas ini memuat kamus teks "standar" yang ditebak dari nama
 * kompetensi bila master kosong. Kamus itu dibuang atas keputusan pemilik proyek: teks
 * tebakan bisa berselisih dengan yang ditulis HC, dan layar yang berbeda lalu menampilkan
 * arti berbeda untuk kompetensi yang sama. Kini yang kosong dinyatakan kosong.
 * Draf awal master di development diisi lewat DML-2026-167.
 */
import { pecahPerilaku } from './perilaku.js';

export const DESKRIPSI_BELUM_DITULIS = 'Deskripsi belum ditulis HC di Master Kompetensi.';

/**
 * Deskripsi dari master; bila kosong, kalimat yang menyatakannya — bukan tebakan.
 */
export function dapatkanDeskripsiKompetensi(item) {
    const teks = String(item?.Deskripsi || item?.deskripsi || '').trim();
    return teks || DESKRIPSI_BELUM_DITULIS;
}

/**
 * Butir expected behaviour dari master; kosong bila HC belum menulisnya.
 */
export function dapatkanPerilakuKompetensi(item) {
    return pecahPerilaku(item?.Expected_Behaviour || item?.expected_behaviour || '');
}

/**
 * Tingkat yang DITUNTUT untuk level orang ini menurut Matriks Kompetensi (DDL-2026-175),
 * mis. "Level 2 · Intermediate". Kosong bila kompetensinya belum punya tingkat — yang tampil
 * lalu Expected Behaviour umum. Server mengirim `tingkat_dituntut`/`nama_tingkat_dituntut`
 * (laporan peserta, detail hasil, radar leader) atau `tingkat`/`nama_tingkat` (Balancing).
 */
export function labelTingkatDituntut(item) {
    const no = item?.tingkat_dituntut ?? item?.tingkat ?? null;
    const nama = item?.nama_tingkat_dituntut ?? item?.nama_tingkat ?? '';
    if (no === null || no === undefined || no === '') return '';
    return `Level ${no}${nama ? ` · ${nama}` : ''}`;
}

/**
 * Expected behaviour sebagai teks satu butir per baris (untuk PaDaftarPerilaku).
 */
export function dapatkanTeksPerilaku(item) {
    return dapatkanPerilakuKompetensi(item).join('\n');
}
