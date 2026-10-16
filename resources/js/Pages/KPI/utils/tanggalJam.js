/**
 * Tanggal-jam KPI -> teks "YYYY-MM-DD HH:mm:ss".
 *
 * Bentuk itulah yang dibaca HcisDatePicker dan yang diurai Carbon tanpa
 * ragu. Yang perlu diterima ada tiga:
 *   - objek Date (form lama);
 *   - teks dari date picker / SQL Server: "2026-10-12 08:30:00(.000)",
 *     "2026-10-12T08:30", atau tanggal saja;
 *   - teks TAMPILAN dari FormatTanggalHelper: "12 Okt 2026 08:00" — bulan
 *     Indonesia yang tak dikenal Carbon maupun `new Date()`.
 *
 * Sengaja tanpa `new Date(teks)`: Safari menolak "YYYY-MM-DD HH:mm:ss".
 * Yang tak terbaca menjadi teks kosong, bukan nilai setengah benar.
 */
const BULAN = {
    jan: 1,
    feb: 2,
    mar: 3,
    apr: 4,
    mei: 5,
    may: 5,
    jun: 6,
    jul: 7,
    agu: 8,
    agt: 8,
    aug: 8,
    sep: 9,
    okt: 10,
    oct: 10,
    nov: 11,
    des: 12,
    dec: 12,
};

const dua = (n) => String(n).padStart(2, '0');

export function keTeksTanggalJam(v) {
    if (!v) return '';

    if (v instanceof Date) {
        if (isNaN(v.getTime())) return '';
        return (
            `${v.getFullYear()}-${dua(v.getMonth() + 1)}-${dua(v.getDate())} ` +
            `${dua(v.getHours())}:${dua(v.getMinutes())}:${dua(v.getSeconds())}`
        );
    }

    const teks = String(v).trim();

    const iso = teks.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (iso) {
        return `${iso[1]}-${iso[2]}-${iso[3]} ${iso[4] || '00'}:${iso[5] || '00'}:${iso[6] || '00'}`;
    }

    const tampil = teks.match(/^(\d{1,2})\s+([A-Za-z]{3})[A-Za-z]*\s+(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/);
    const bulan = tampil && BULAN[tampil[2].toLowerCase()];
    if (bulan) {
        return `${tampil[3]}-${dua(bulan)}-${dua(tampil[1])} ${dua(tampil[4] || 0)}:${tampil[5] || '00'}:00`;
    }

    return '';
}
