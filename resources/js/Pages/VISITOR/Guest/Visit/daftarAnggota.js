/**
 * Baca manifes rombongan dari kolom JSON.
 *
 * Registrasi lama bernilai null karena daftarnya memang tidak pernah
 * dikumpulkan. Teks rusak atau objek JSON diperlakukan sama: daftar kosong,
 * bukan layar yang meledak.
 */
export function bacaDaftarAnggota(json) {
    if (!json) return [];

    try {
        const isi = JSON.parse(json);

        return Array.isArray(isi) ? isi : [];
    } catch {
        return [];
    }
}
