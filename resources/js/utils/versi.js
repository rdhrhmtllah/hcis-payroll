function padat(v, lebar) {
    if (v === null || v === undefined || v === '') return '—';

    const n = Number(v);
    if (!Number.isFinite(n)) return String(v);

    return String(Math.trunc(n)).padStart(lebar, '0');
}

export const LEBAR_NOMOR_DOKUMEN = 3;

export function nomorVersi(v) {
    return padat(v, 2);
}

export function nomorDokumen(v) {
    return padat(v, LEBAR_NOMOR_DOKUMEN);
}

export function labelVersi(v) {
    const n = nomorVersi(v);

    return n === '—' ? n : `v${n}`;
}

export function nomorDokumenTampil(dokumen) {
    const d = dokumen || {};

    return d.Nomor_Lengkap || d.Nomor_Pratinjau || d.Nomor_SOP_Resmi || null;
}
