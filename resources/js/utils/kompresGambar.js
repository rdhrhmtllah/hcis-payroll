/**
 * Perkecil foto di perangkat SEBELUM diunggah.
 *
 * Foto kamera HP 3–5 MB (kadang lebih) membuat pengajuan lambat di jaringan
 * seluler dan bisa menabrak batas unggah server. Sisi terpanjang dibatasi
 * (bukan dipotong persentase: kamera 12 MP dan 108 MP akan menghasilkan ukuran
 * yang sangat berbeda), lalu disimpan ulang sebagai JPEG. 1600 px masih tajam
 * untuk membaca plat, STNK, dan SIM; hasilnya biasanya 300–600 KB.
 *
 * Aman gagal: berkas non-gambar (PDF), GIF, peramban yang tidak bisa
 * membaca gambarnya, atau hasil yang justru lebih besar — semuanya
 * mengembalikan berkas ASLI apa adanya.
 */

export const SISI_MAKS_PX = 1600;
export const KUALITAS_JPEG = 0.8;
/** Gambar yang sudah kecil dan tidak perlu diperkecil dibiarkan. */
export const LEWATI_DI_BAWAH_BYTE = 700 * 1024;

async function bacaGambar(file) {
    // createImageBitmap menghormati orientasi EXIF: foto HP yang diambil
    // tegak tidak berubah jadi miring setelah diperkecil.
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            // jatuh ke <img>
        }
    }

    const url = URL.createObjectURL(file);
    try {
        return await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Gambar tidak bisa dibaca.'));
            img.src = url;
        });
    } finally {
        URL.revokeObjectURL(url);
    }
}

function keBlob(kanvas, kualitas) {
    return new Promise((resolve) => kanvas.toBlob((blob) => resolve(blob), 'image/jpeg', kualitas));
}

/**
 * @param {File} file
 * @param {{ sisiMaks?: number, kualitas?: number }} [opsi]
 * @returns {Promise<File>} berkas hasil, atau berkas asli bila tidak perlu/tidak bisa
 */
export async function kompresGambar(file, { sisiMaks = SISI_MAKS_PX, kualitas = KUALITAS_JPEG } = {}) {
    if (!file || !String(file.type || '').startsWith('image/') || file.type === 'image/gif') {
        return file;
    }

    try {
        const gambar = await bacaGambar(file);
        const lebar = gambar.width || gambar.naturalWidth;
        const tinggi = gambar.height || gambar.naturalHeight;
        if (!lebar || !tinggi) return file;

        const skala = Math.min(1, sisiMaks / Math.max(lebar, tinggi));
        if (skala === 1 && file.size <= LEWATI_DI_BAWAH_BYTE) {
            gambar.close?.();
            return file;
        }

        const kanvas = document.createElement('canvas');
        kanvas.width = Math.round(lebar * skala);
        kanvas.height = Math.round(tinggi * skala);
        const ctx = kanvas.getContext('2d');
        if (!ctx) return file;

        // Latar putih: PNG transparan (tangkapan layar STNK) tidak jadi hitam di JPEG.
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, kanvas.width, kanvas.height);
        ctx.drawImage(gambar, 0, 0, kanvas.width, kanvas.height);
        gambar.close?.();

        const blob = await keBlob(kanvas, kualitas);
        if (!blob || blob.size >= file.size) return file;

        const nama = String(file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], nama, { type: 'image/jpeg', lastModified: file.lastModified || Date.now() });
    } catch {
        return file;
    }
}
