/**
 * Jejak audit Akses & Peran → kalimat yang bisa dibaca orang, plus "chip"
 * perubahan (+ ditambah / − dibuang). JSON mentah tetap tersedia di detail.
 */

export const labelAksi = {
    TUGASKAN_PERAN: 'Tugaskan peran',
    CABUT_PERAN: 'Cabut peran',
    UBAH_ROLE: 'Ubah peran (layar lama)',
    UBAH_BATAS: 'Ubah batas waktu',
    BERI_GRANT: 'Beri akses halaman',
    CABUT_GRANT: 'Cabut akses halaman',
    UBAH_HAK: 'Ubah hak peran',
    UBAH_TINGKAT: 'Ubah tingkat peran',
    UBAH_KEMAMPUAN: 'Ubah kemampuan tingkat',
    UBAH_MODE: 'Ubah mode modul',
    TAMBAH_HAK_KATALOG: 'Hak dari katalog',
    SINKRON_HAK: 'Sinkron hak',
    BUAT_TEMPLATE: 'Buat template cakupan',
    UBAH_TEMPLATE: 'Ubah template cakupan',
    NONAKTIF_TEMPLATE: 'Nonaktifkan template cakupan',
};

function json(nilai) {
    if (nilai === null || nilai === undefined || nilai === '') return null;
    if (typeof nilai !== 'string') return nilai;
    try {
        return JSON.parse(nilai);
    } catch {
        return nilai;
    }
}

const daftar = (v) => (Array.isArray(v) ? v : Array.isArray(v?.peran) ? v.peran : null);

/** @param {Record<number|string, string>} namaPeran id → nama peran (untuk subjek ROLE) */
export function formatAudit(baris, namaPeran = {}) {
    const oleh = baris.oleh || (baris.id_oleh ? `User #${baris.id_oleh}` : 'Sistem');
    const sasaran = baris.target || (baris.id_target ? `User #${baris.id_target}` : '');
    const sebelum = json(baris.sebelum);
    const sesudah = json(baris.sesudah);
    const peran = namaPeran[baris.subjek_id] || `peran #${baris.subjek_id}`;
    const chip = [];
    let kalimat;

    const lama = daftar(sebelum);
    const baru = daftar(sesudah);
    if (lama && baru) {
        baru.filter((k) => !lama.includes(k)).forEach((k) => chip.push({ label: k, jenis: 'tambah' }));
        lama.filter((k) => !baru.includes(k)).forEach((k) => chip.push({ label: k, jenis: 'buang' }));
    }

    switch (baris.aksi) {
        case 'TUGASKAN_PERAN':
            kalimat = `${oleh} menugaskan ${sesudah?.penugasan?.kode || chip.find((c) => c.jenis === 'tambah')?.label || 'peran'} ke ${sasaran}`;
            break;
        case 'CABUT_PERAN':
            kalimat = `${oleh} mencabut ${chip.find((c) => c.jenis === 'buang')?.label || 'peran'} dari ${sasaran}`;
            break;
        case 'UBAH_ROLE':
            kalimat = `${oleh} mengubah peran ${sasaran} lewat layar lama`;
            break;
        case 'UBAH_BATAS':
            kalimat = `${oleh} mengubah batas waktu ${sesudah?.kode || 'peran'} milik ${sasaran}`;
            chip.push({
                label: sesudah?.berlaku_sampai ? `s.d. ${String(sesudah.berlaku_sampai).slice(0, 10)}` : 'tanpa batas',
                jenis: 'info',
            });
            break;
        case 'BERI_GRANT':
            kalimat = `${oleh} memberi akses halaman ${baris.subjek_id} ke ${sasaran}`;
            break;
        case 'CABUT_GRANT':
            kalimat = `${oleh} mencabut akses halaman ${baris.subjek_id} dari ${sasaran}`;
            break;
        case 'UBAH_HAK': {
            kalimat = `${oleh} mengubah hak ${peran}`;
            const halaman = (v) =>
                new Map((Array.isArray(v) ? v : []).map((h) => [h.jenis_page, (h.aksi || []).join('/')]));
            const a = halaman(sebelum);
            const b = halaman(sesudah);
            b.forEach((aksi, p) => {
                if (!a.has(p)) chip.push({ label: `${p} (${aksi})`, jenis: 'tambah' });
                else if (a.get(p) !== aksi) chip.push({ label: `${p}: ${a.get(p)} → ${aksi}`, jenis: 'info' });
            });
            a.forEach((_, p) => !b.has(p) && chip.push({ label: p, jenis: 'buang' }));
            break;
        }
        case 'UBAH_TINGKAT':
            kalimat = `${oleh} mengubah tingkat ${peran}`;
            chip.push({ label: `${sebelum?.tingkat || '—'} → ${sesudah?.tingkat || '—'}`, jenis: 'info' });
            break;
        case 'UBAH_KEMAMPUAN':
            kalimat = `${oleh} mengubah kemampuan tingkat ${baris.subjek_id}`;
            break;
        case 'UBAH_MODE':
            kalimat = `${oleh} mengubah mode modul ${baris.subjek_label || `#${baris.subjek_id}`}`;
            chip.push({ label: `${sebelum ?? '—'} → ${sesudah ?? '—'}`, jenis: 'info' });
            break;
        case 'BUAT_TEMPLATE':
        case 'UBAH_TEMPLATE':
        case 'NONAKTIF_TEMPLATE': {
            const kata = { BUAT_TEMPLATE: 'membuat', UBAH_TEMPLATE: 'mengubah', NONAKTIF_TEMPLATE: 'menonaktifkan' };
            kalimat = `${oleh} ${kata[baris.aksi]} template cakupan "${sesudah?.nama || sebelum?.nama || `#${baris.subjek_id}`}"`;
            (sesudah?.syarat || []).forEach((c) =>
                chip.push({ label: c.nilai ? `${c.jenis}: ${c.nilai}` : c.jenis, jenis: 'info' }),
            );
            break;
        }
        default:
            kalimat = `${oleh}: ${labelAksi[baris.aksi] || baris.aksi}${sasaran ? ` · ${sasaran}` : ''}`;
    }

    return {
        kalimat,
        chip: chip.slice(0, 8),
        lebih: Math.max(0, chip.length - 8),
        label: labelAksi[baris.aksi] || baris.aksi,
    };
}
