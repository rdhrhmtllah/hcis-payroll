/**
 * Aturan layar Balancing — murni, tanpa HTTP. Ambang dan rentang SAMA dengan backend
 * (PaBalancingKalkulator::berubah, PaBalancingSesiService): layar menahan lebih dulu supaya
 * admin tidak menunggu penolakan server untuk kesalahan yang sudah terlihat.
 */

export const AMBANG = 0.005;

export function berubah(a, b) {
    return Math.abs(Number(a) - Number(b)) >= AMBANG;
}

export function diLuarRentang(k) {
    if (k.sesudah === '' || k.sesudah === null || k.sesudah === undefined) return true;
    const n = Number(k.sesudah);
    return !Number.isFinite(n) || n < 0 || n > 100;
}

export function butuhAlasan(k) {
    return berubah(k.sebelum, k.sesudah) && !String(k.alasan ?? '').trim();
}

export function adaPerubahan(p) {
    return (p.kompetensi || []).some((k) => berubah(k.sebelum, k.sesudah))
        || (p.kualitatif || []).some((q) => (q.sebelum ?? '') !== (q.sesudah ?? ''));
}

/** Alasan dicatat per orang: wajib bila ada nilai atau catatan kualitatif yang diubah. */
export function butuhAlasanPeserta(p) {
    return adaPerubahan(p) && !String(p.alasan ?? '').trim();
}

export function galatPeserta(p) {
    const galat = [];
    for (const k of p.kompetensi || []) {
        if (diLuarRentang(k)) galat.push(`${k.nama}: nilai After harus 0 sampai 100.`);
    }
    if (butuhAlasanPeserta(p)) galat.push(`${p.nama || 'Peserta'}: alasan perubahan wajib diisi karena ada nilai yang diubah.`);
    return galat;
}

export function payloadSimpan(peserta) {
    return peserta.map((p) => ({
        participant_id: p.participant_id,
        // DIUBAH diturunkan server dari isian; yang dikirim hanya pilihan admin.
        status_tinjau: ['BELUM', 'TANPA_PERUBAHAN'].includes(p.status_tinjau) ? p.status_tinjau : null,
        alasan: p.alasan || null,
        kompetensi: (p.kompetensi || []).map((k) => ({
            competency_id: k.competency_id, nilai_sesudah: Number(k.sesudah), alasan: String(k.alasan ?? '').trim() || null,
        })),
        kualitatif: (p.kualitatif || []).map((q) => ({ competency_id: q.competency_id, isi_sesudah: q.sesudah ?? '' })),
    }));
}

export function payloadSimulasi(peserta) {
    return peserta.map((p) => ({
        participant_id: p.participant_id,
        kompetensi: (p.kompetensi || []).map((k) => ({ competency_id: k.competency_id, nilai_sesudah: Number(k.sesudah) })),
    }));
}

/** Urutan grid 3×3 yang sama dengan halaman HAV Map (baris atas = kompetensi tinggi). */
export const URUTAN_KOTAK = [7, 3, 1, 8, 4, 2, 9, 6, 5];

export function susunKotak(kuadran) {
    const peta = new Map((kuadran || []).map((k) => [Number(k.Nomor_Box), k]));
    return URUTAN_KOTAK.map((n) => peta.get(n)).filter(Boolean);
}

export function hitungKotak(baris, sisi) {
    return (baris || []).reduce((acc, b) => {
        const kode = b?.[sisi]?.kode_kuadran;
        if (kode) acc[kode] = (acc[kode] || 0) + 1;
        return acc;
    }, {});
}

export function pindahKotak(baris) {
    return (baris || [])
        .filter((b) => b.sebelum?.kode_kuadran !== b.sesudah?.kode_kuadran)
        .map((b) => ({ nama: b.nama, dari: b.sebelum?.kode_kuadran ?? null, ke: b.sesudah?.kode_kuadran ?? null }));
}

export const LABEL_SEBAB = { BALANCING: 'Penyesuaian', KURVA: 'Kurva', KEDUANYA: 'Penyesuaian + kurva', TANPA_PERUBAHAN: 'Tanpa perubahan' };
export const LABEL_STATUS = { TERBUKA: 'Terbuka', DRAFT: 'Draft', FINAL: 'Final', DIBATALKAN: 'Dibatalkan' };
export const VARIAN_STATUS = { TERBUKA: 'info', DRAFT: 'warning', FINAL: 'success', DIBATALKAN: 'neutral' };
export const LABEL_TINJAU = { BELUM: 'Belum ditinjau', TANPA_PERUBAHAN: 'Tanpa perubahan', DIUBAH: 'Diubah' };

/** Kode grade untuk dibaca orang: B_PLUS → B+, C_MINUS → C-. */
export function labelGrade(kode) {
    if (kode === null || kode === undefined || kode === '') return '–';
    return String(kode).replace(/_PLUS/gi, '+').replace(/_MINUS/gi, '-').trim();
}

/** Kotak HAV untuk dibaca orang: nama kuadran + nomor kotak, bukan kode database. */
export function labelKuadran(k) {
    if (!k) return '–';
    const nama = k.nama_kuadran || (k.kode_kuadran ? String(k.kode_kuadran).toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '');
    if (!nama) return '–';
    return k.nomor_box ? `${k.nomor_box} · ${nama}` : nama;
}

export function formatNilai(v) {
    if (v === null || v === undefined || v === '') return '–';
    return Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function hitungDelta(sebelum, sesudah) {
    if (sesudah === '' || sesudah === null || sesudah === undefined) return null;
    const diff = Number(sesudah) - Number(sebelum);
    if (Math.abs(diff) < AMBANG) return null;
    const formatted = Math.abs(diff).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return diff > 0 ? `+${formatted}` : `-${formatted}`;
}

export function varianDelta(sebelum, sesudah) {
    if (sesudah === '' || sesudah === null || sesudah === undefined) return 'neutral';
    const diff = Number(sesudah) - Number(sebelum);
    if (Math.abs(diff) < AMBANG) return 'neutral';
    return diff > 0 ? 'success' : 'danger';
}

/**
 * Nilai Kompetensi After = Before + Σ bobotₖ × (Afterₖ − Beforeₖ), dibatasi 0–100.
 * Sama dengan PaBalancingKalkulator::nilaiKompetensi; bobot = porsi kompetensi itu di nilai kompetensi.
 */
export function hitungKompSesudah(p) {
    const awal = p?.sebelum?.nilai_kompetensi;
    if (awal === null || awal === undefined) return null;
    let geser = 0;
    for (const k of p.kompetensi || []) {
        const sesudah = k.sesudah === '' || k.sesudah === null || k.sesudah === undefined ? k.sebelum : k.sesudah;
        geser += (Number(k.bobot) || 0) * (Number(sesudah) - Number(k.sebelum));
    }
    return Math.round(Math.max(0, Math.min(100, Number(awal) + geser)) * 100) / 100;
}

/** Final After = KPI × bobot KPI + Kompetensi After × bobot kompetensi (PaBalancingKalkulator::nilaiAkhir). */
export function hitungFinalSesudah(p) {
    const s = p?.sebelum || {};
    const komp = hitungKompSesudah(p);
    if (s.nilai_kpi === null || s.nilai_kpi === undefined || komp === null || s.bobot_kpi === null || s.bobot_kpi === undefined || s.bobot_kompetensi === null || s.bobot_kompetensi === undefined) {
        return s.final ?? null;
    }
    return Math.round((Number(s.nilai_kpi) * Number(s.bobot_kpi) / 100 + komp * Number(s.bobot_kompetensi) / 100) * 100) / 100;
}

/** 75 → "75%", 82.5 → "82,5%"; kosong → "–". */
export function formatPersen(v) {
    if (v === null || v === undefined || v === '') return '–';
    return `${Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

/** Salinan dalam peserta dari detail() — board menyunting salinan, bukan respons server. */
export function salinPeserta(p) {
    return {
        ...p,
        kompetensi: (p.kompetensi || []).map((k) => ({ ...k })),
        kualitatif: (p.kualitatif || []).map((q) => ({ ...q })),
    };
}


/** Potret bagian yang bisa disunting dari seluruh peserta — bahan undo/redo board. */
export function potretSuntingan(peserta) {
    return JSON.stringify((peserta || []).map((p) => ({
        id: p.participant_id,
        status_tinjau: p.status_tinjau,
        alasan: p.alasan ?? null,
        kompetensi: (p.kompetensi || []).map((k) => [k.competency_id, k.sesudah, k.alasan ?? null]),
        kualitatif: (p.kualitatif || []).map((q) => [q.competency_id, q.sesudah ?? null]),
    })));
}

/** Kembalikan potret ke objek peserta yang sama (identitas objek dijaga supaya tampilan tidak dibangun ulang). */
export function terapkanPotret(peserta, potret) {
    const peta = new Map(JSON.parse(potret).map((x) => [x.id, x]));
    for (const p of peserta || []) {
        const x = peta.get(p.participant_id);
        if (!x) continue;
        p.status_tinjau = x.status_tinjau;
        p.alasan = x.alasan;
        const komp = new Map(x.kompetensi.map(([id, sesudah, alasan]) => [id, { sesudah, alasan }]));
        for (const k of p.kompetensi || []) {
            const v = komp.get(k.competency_id);
            if (v) { k.sesudah = v.sesudah; k.alasan = v.alasan; }
        }
        const kual = new Map(x.kualitatif);
        for (const q of p.kualitatif || []) {
            if (kual.has(q.competency_id)) q.sesudah = kual.get(q.competency_id);
        }
    }
}

/** Kesimpulan kualitatif yang tidak ditimpa hitung ulang karena diubah admin sesudah sesi dibuat. */
export function pesanKualitatifDilewati(hasil) {
    const daftar = hasil?.kualitatif_dilewati || [];
    if (!daftar.length) return '';
    const contoh = daftar.slice(0, 3).map((d) => `${d.nama} (${d.kompetensi})`).join(', ');
    return `${daftar.length} kesimpulan kualitatif TIDAK ditimpa karena diubah admin sesudah sesi dibuat: ${contoh}${daftar.length > 3 ? ', …' : ''}. Putuskan manual di tahap Kesimpulan Kualitatif.`;
}
