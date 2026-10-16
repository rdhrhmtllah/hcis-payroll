/**
 * Tampilan sembilan kotak HAV — warna, pola, ikon, dan urutan grid — sama dengan halaman
 * HAV Map (Hasil/hav-map.vue), supaya kotak yang sama terlihat sama di semua layar.
 */

/** Urutan grid 3×3: baris atas = kompetensi tinggi, kolom kanan = kinerja tinggi. */
export const URUTAN_KOTAK_HAV = [7, 3, 1, 8, 4, 2, 9, 6, 5];

const SENTIMEN = { 1: 'success', 2: 'purple', 3: 'brand', 4: 'teal', 5: 'warning', 6: 'info', 7: 'purple', 8: 'warning', 9: 'danger' };
const POLA = { 1: 'rings', 2: 'hex', 3: 'grid', 4: 'dots', 5: 'stripes', 6: 'crosshair', 7: 'dots', 8: 'grid', 9: 'crosshair' };
const IKON = {
    1: 'star-fill', 2: 'lightning-charge-fill', 3: 'award-fill', 4: 'person-check-fill', 5: 'trophy-fill',
    6: 'hand-thumbs-up-fill', 7: 'gem', 8: 'exclamation-triangle-fill', 9: 'x-octagon-fill',
};

const nomor = (kotak) => Number(kotak?.Nomor_Box ?? kotak);

export const sentimenKotak = (kotak) => SENTIMEN[nomor(kotak)] || 'brand';
export const polaKotak = (kotak) => POLA[nomor(kotak)] || 'dots';
export const ikonKotak = (kotak) => IKON[nomor(kotak)] || 'grid-3x3';
