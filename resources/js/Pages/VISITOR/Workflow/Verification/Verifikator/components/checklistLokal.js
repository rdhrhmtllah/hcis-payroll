/**
 * Jawaban checklist hanya tersimpan saat approve. Memuat ulang detail (mis.
 * setelah koreksi data) tidak boleh menghapus isian verifikator yang belum
 * disimpan: baris yang masih ada membawa isian lamanya, baris baru kosong.
 */
export function gabungJawabanLokal(barisBaru = [], barisLama = []) {
    const lama = new Map(barisLama.map((b) => [b.Id_Permit_Checklist, b]));

    return barisBaru.map((b) => {
        const isian = lama.get(b.Id_Permit_Checklist);
        if (!isian) return b;

        return {
            ...b,
            localJawaban: isian.localJawaban,
            localCatatan: isian.localCatatan,
            tampilCatatan: isian.tampilCatatan,
        };
    });
}
