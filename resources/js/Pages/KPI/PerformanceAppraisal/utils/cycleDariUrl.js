/**
 * Membaca siklus yang diminta lewat `?cycle=` pada alamat halaman.
 *
 * Dipakai halaman-halaman yang punya pemilih siklusnya SENDIRI (HAV Map, Pemantauan,
 * Hasil, Penilai, Kalibrasi, Distribusi, Riwayat). Tanpa ini, admin yang menekan
 * "HAV Map" pada baris siklus X mendarat di halaman yang memilih siklusnya sendiri —
 * dan diam-diam melihat siklus Y.
 *
 * Nilainya DIVALIDASI terhadap daftar yang sudah dimuat, dan itu bukan kehati-hatian
 * berlebihan: kalau id yang tak dikenal diterima begitu saja, penjaga `!selectedCycleId`
 * di setiap halaman menganggap pilihan sudah ada, pilihan bawaannya tidak pernah jalan,
 * dan halamannya berhenti pada pemilih kosong tanpa pesan apa pun. Alamat yang salah
 * ketik berubah menjadi halaman yang tampak rusak.
 *
 * @param {Array<{Id_PA_Cycle: number|string}>} daftar siklus yang sudah dimuat
 * @returns {string} id siklus yang sah, atau '' bila tidak diminta / tidak dikenal
 */
export function cycleDariUrl(daftar) {
    if (typeof window === 'undefined' || !Array.isArray(daftar) || daftar.length === 0) {
        return '';
    }

    let diminta = '';

    try {
        diminta = new URLSearchParams(window.location.search).get('cycle') || '';
    } catch {
        return '';
    }

    if (diminta === '') {
        return '';
    }

    const cocok = daftar.find((c) => Number(c?.Id_PA_Cycle) === Number(diminta));

    return cocok ? String(cocok.Id_PA_Cycle) : '';
}

export default cycleDariUrl;
