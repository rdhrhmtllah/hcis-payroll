/**
 * Pohon model kendaraan per jenis — daftar model lebih dari seratus baris,
 * dan dropdown datar memaksa verifikator menggulir campuran motor/mobil/truk.
 * Dipakai modal review dan dialog koreksi supaya keduanya identik.
 */
export function pohonModel(models = [], vehicleTypes = []) {
    const namaJenis = new Map(
        vehicleTypes.map((t) => [String(t.Id_Vehicle_Type), t.Nama_Tipe_Kendaraan || t.Nama_Vehicle_Type]),
    );
    const kelompok = new Map();

    models.forEach((m) => {
        const kunci = String(m.Id_Master_Tipe ?? 'lainnya');
        if (!kelompok.has(kunci)) kelompok.set(kunci, []);
        kelompok.get(kunci).push({ value: String(m.Id_Vehicle_Model), label: m.Nama_Model_Kendaraan });
    });

    return [...kelompok.entries()].map(([kunci, anak]) => ({
        value: `jenis-${kunci}`,
        label: namaJenis.get(kunci) || 'Lainnya',
        selectable: false,
        children: anak.sort((a, b) => a.label.localeCompare(b.label)),
    }));
}

export function daftarTahun(maks = new Date().getFullYear() + 1, min = 1970) {
    const list = [];
    for (let y = maks; y >= min; y--) list.push(y);
    return list;
}
