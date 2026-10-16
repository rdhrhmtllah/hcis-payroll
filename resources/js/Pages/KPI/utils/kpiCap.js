/**
 * Penjepitan achievement per parameter di sisi klien.
 *
 * Satu-satunya tempat aturan ini ditulis untuk frontend. Lima halaman KPI
 * menghitung ulang rumus parameter di browser; kalau masing-masing menjepit
 * dengan caranya sendiri, layar dan server akan menampilkan angka berbeda untuk
 * data yang sama — dan sejak penjepitan mengubah nilai akhir, selisih itu
 * terlihat oleh setiap pengisi.
 *
 * Batas dinyatakan dalam satuan achievement (persen), sama seperti di backend.
 * Batas bernilai null berarti sisi itu tidak dijepit.
 *
 * Padanan backend: App\Services\KPI\KpiScoringService::scoreFromRatio().
 */

const BATAS_KOSONG = { min: null, max: null };

/**
 * Batas baku, cermin dari config/services.php.
 *
 * Ini nilai AWAL yang benar untuk setiap halaman, bukan { null, null }. Batas
 * kosong berarti "tidak menjepit", dan memakainya sebagai nilai awal membuat
 * halaman gagal ke arah yang salah: bila permintaan kpi-cap-config gagal
 * (timeout, 500, 403), layar menghitung TANPA penjepitan sepanjang sesi
 * sementara server tetap menjepit — persis selisih layar-vs-server yang jadi
 * alasan berkas ini ada.
 */
export const BATAS_BAKU = { min: 50, max: 125 };

/**
 * Ambil batas per-parameter dari payload endpoint kpi-cap-config.
 *
 * Tiga keadaan yang dibedakan, sejajar dengan sisi server:
 *   - payload/kunci tidak ada  -> baku (server belum mengenal medan ini)
 *   - kunci bernilai null      -> MATI, penjepitan sisi itu memang dimatikan
 *   - kunci berupa angka       -> angka itu
 */
export function batasDariConfig(payload) {
    if (!payload) {
        return { ...BATAS_BAKU };
    }

    return {
        min: sisiBatas(payload, 'param_min_achievement_score', BATAS_BAKU.min),
        max: sisiBatas(payload, 'param_max_achievement_score', BATAS_BAKU.max),
    };
}

function sisiBatas(payload, kunci, baku) {
    if (!(kunci in payload) || payload[kunci] === undefined) {
        return baku;
    }

    if (payload[kunci] === null) {
        return null;
    }

    // Number('') adalah 0, bukan NaN — jebakan yang sama dengan `(float) ''`
    // di PHP. Tanpa penjaga ini, medan kosong menjadi plafon nol dan menolkan
    // achievement setiap parameter.
    const teks = typeof payload[kunci] === 'string' ? payload[kunci].trim() : payload[kunci];

    if (teks === '') {
        return baku;
    }

    const angka = Number(teks);

    return Number.isFinite(angka) ? angka : baku;
}

/**
 * Jepit rasio hasil rumus ke batas per-parameter.
 * Rasio yang bukan angka berhingga diperlakukan sebagai nol lebih dulu,
 * sama seperti perilaku lama, baru kemudian dijepit.
 */
export function jepitRasio(rasio, batas = BATAS_KOSONG) {
    let nilai = typeof rasio === 'number' && Number.isFinite(rasio) ? rasio : 0;

    if (batas.max !== null && batas.max !== undefined) {
        nilai = Math.min(nilai, batas.max / 100);
    }

    if (batas.min !== null && batas.min !== undefined) {
        nilai = Math.max(nilai, batas.min / 100);
    }

    return nilai;
}

/**
 * Turunkan achievement dan skor dari rasio yang sudah dijepit, beserta angka
 * mentahnya. Menjepit achievement sendirian lalu menghitung skor dari rasio
 * mentah membuat total berhenti sama dengan rata-rata baris yang tampil.
 */
export function hitungMetrikParameter(rasioMentah, bobot, batas = BATAS_KOSONG) {
    const mentah = typeof rasioMentah === 'number' && Number.isFinite(rasioMentah) ? rasioMentah : 0;
    const rasio = jepitRasio(mentah, batas);
    const berat = Number(bobot) || 0;

    return {
        achievement: rasio * 100,
        score: berat * rasio,
        achievementRaw: mentah * 100,
        scoreRaw: berat * mentah,
        terjepit: rasio !== mentah,
    };
}
