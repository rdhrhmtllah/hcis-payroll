/**
 * Pesan untuk penyimpanan BERTAHAP (satu permintaan per level/sel) yang gagal di tengah.
 *
 * Tiap permintaan atomik di server, jadi yang sudah tersimpan TETAP tersimpan. Pesan
 * "Gagal menyimpan semua…" membuat admin mengira tak satu pun masuk lalu mengulang dari
 * awal, atau sebaliknya mengira semuanya masuk. Pesan ini menyebut berapa yang sudah,
 * di mana berhentinya, dan sebabnya.
 *
 * @param {{ tersimpan: number, total: number, gagalDi?: string, galat?: unknown, satuan?: string }} p
 * @returns {string}
 */
export function pesanGagalSebagian({ tersimpan, total, gagalDi = '', galat = null, satuan = 'level' }) {
    const sebab = (galat && typeof galat === 'object' && 'message' in galat ? galat.message : galat) || 'galat tidak dikenal';
    const posisi = gagalDi ? ` di ${gagalDi}` : '';

    if (tersimpan <= 0) {
        return `Tidak ada ${satuan} yang tersimpan — gagal${posisi}: ${sebab}`;
    }

    return `${tersimpan} dari ${total} ${satuan} sudah tersimpan; berhenti${posisi}: ${sebab}. `
        + `Sisanya belum disimpan — perbaiki lalu simpan lagi.`;
}
