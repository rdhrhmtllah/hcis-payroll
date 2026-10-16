/**
 * Kelompokkan grup modul per "bagian" (tingkat opsional di atas grup).
 *
 * Modul tanpa `sections` menghasilkan satu kelompok tanpa judul berisi semua
 * grupnya — tampilan sama persis dengan sebelum fitur bagian ada. Urutan grup
 * mengikuti `module.groups` dari server; bagian yang semua grupnya tersaring
 * pencarian ikut hilang.
 */
export function bagianModul(module) {
    const groups = module?.groups || [];
    const sections = module?.sections || [];
    if (!sections.length) {
        return [{ id: null, title: '', icon: '', groups }];
    }

    const kelompok = [{ id: null, title: '', icon: '', groups: groups.filter((g) => !g.sectionId) }];
    sections.forEach((s) => {
        kelompok.push({ id: s.id, title: s.title, icon: s.icon, groups: groups.filter((g) => g.sectionId === s.id) });
    });

    return kelompok.filter((k) => k.groups.length);
}
