/**
 * Label level peserta: level PA, dan level HRIS aslinya bila berbeda karena koreksi Level PA
 * (DDL-2026-177), mis. "OFFICER (HRIS: SENIOR STAFF)".
 */
export function labelLevelPa(row) {
    const pa = row?.Nama_Level_Saat_Cycle || (row?.ID_Level_Saat_Cycle ? `Level ${row.ID_Level_Saat_Cycle}` : '');
    const hris = row?.Nama_Level_HRIS_Saat_Cycle || '';
    return row?.PA_Karyawan_Level_PA_Id && hris && hris !== pa ? `${pa} (HRIS: ${hris})` : pa;
}
