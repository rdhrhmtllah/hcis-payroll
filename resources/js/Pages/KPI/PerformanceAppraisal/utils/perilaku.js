/**
 * Expected behaviour kompetensi disimpan sebagai teks, SATU PERILAKU PER BARIS
 * (lihat DDL-2026-166). Pemecahnya satu di sini supaya pratinjau di form master dan
 * tampilan di layar hasil membaca butir yang sama. Penanda butir yang terketik
 * (-, *, •, "1.") dibuang — sama dengan normalisasi server saat menyimpan.
 */
export function pecahPerilaku(teks) {
    if (!teks || typeof teks !== 'string') return [];
    return teks
        .split(/\r?\n/)
        .map((b) => b.replace(/^\s*(?:[-*•▪◦]|\d+[.)])\s*/u, '').trim())
        .filter(Boolean);
}
