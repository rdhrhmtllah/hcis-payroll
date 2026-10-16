import { describe, it, expect } from 'vitest';
import FormKPITeam from './FormKPITeam.vue';

/**
 * Cakupan sel yang boleh diisi ditentukan KPI YANG DISUNTING, bukan tab terbuka.
 *
 * Nilai pada template bersama dipakai SEMUA anggota tercentang, jadi kolomnya
 * harus membentang sampai bulan mulai paling awal di antara mereka. Kalau
 * cakupannya mengikuti tab yang kebetulan terbuka, menyunting template dari tab
 * anggota yang mulai Mei mematikan Feb–Apr, dan anggota yang mulai Februari
 * kehilangan tiga bulan yang tak pernah bisa diisi lalu tersimpan kosong.
 *
 * Diuji lewat opsi komponen langsung: FormKPITeam.vue lebih dari lima ribu baris
 * dan me-mount-nya menuntut puluhan stub yang tidak ada hubungannya dengan
 * aturan ini — ujinya akan lebih sering rusak karena stub daripada karena
 * aturannya.
 */
const { sedangMenyuntingTemplate, anggotaBreakdown, cakupanBreakdown } = FormKPITeam.computed;

function konteks({ tab = null, tercentang = [] } = {}) {
    const dasar = {
        activeMemberTab: tab,
        sedangMenyuntingTemplate: sedangMenyuntingTemplate.call({ activeMemberTab: tab }),
        getSelectedMembersObjects: () => tercentang,
    };

    return { ...dasar, anggotaBreakdown: anggotaBreakdown.call(dasar) };
}

const ANITA = { id: 2, employeeId: 'B8', useCustomKpi: false, kpis: null };
const ANITA_CUSTOM = { id: 2, employeeId: 'B8', useCustomKpi: true, kpis: [{ target: 1 }] };
const TIGA = [{ employeeId: 'A5' }, { employeeId: 'B8' }, { employeeId: 'E14' }];

describe('sedangMenyuntingTemplate', () => {
    it('tanpa tab anggota berarti template bersama', () => {
        expect(sedangMenyuntingTemplate.call({ activeMemberTab: null })).toBe(true);
    });

    it('tab anggota yang MEMAKAI template tetap dihitung template', () => {
        // Inilah kasus yang dulu salah: tab terbuka, tetapi yang disunting
        // adalah template bersama.
        expect(sedangMenyuntingTemplate.call({ activeMemberTab: ANITA })).toBe(true);
    });

    it('tab anggota dengan KPI custom bukan template', () => {
        expect(sedangMenyuntingTemplate.call({ activeMemberTab: ANITA_CUSTOM })).toBe(false);
    });

    it('anggota yang menyalakan custom tetapi belum punya KPI masih memakai template', () => {
        // Cerminan activeKpiContainer: ia baru memakai kpis milik anggota bila
        // daftarnya benar-benar ada. Kalau dua tempat ini berselisih, sel yang
        // mati tidak lagi cocok dengan nilai yang tersimpan.
        expect(sedangMenyuntingTemplate.call({
            activeMemberTab: { ...ANITA_CUSTOM, kpis: null },
        })).toBe(true);
    });
});

describe('cakupanBreakdown', () => {
    it('template dari tab Anita tetap mencakup SEMUA yang dicentang', () => {
        expect(cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: TIGA })))
            .toBe('template:A5,B8,E14');
    });

    it('KPI custom hanya mencakup pemiliknya', () => {
        expect(cakupanBreakdown.call(konteks({ tab: ANITA_CUSTOM, tercentang: TIGA })))
            .toBe('custom:B8');
    });

    it('berubah ketika anggota dicentang atau dilepas', () => {
        // Pemantau memakai nilai ini; kalau ia tidak berubah, modal tetap
        // memakai batas bulan milik keadaan sebelumnya tanpa galat apa pun.
        const sebelum = cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: TIGA }));
        const sesudah = cakupanBreakdown.call(konteks({
            tab: ANITA,
            tercentang: TIGA.filter((m) => m.employeeId !== 'A5'),
        }));

        expect(sebelum).not.toBe(sesudah);
    });

    it('berubah ketika beralih template <-> custom pada anggota yang sama', () => {
        const template = cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: TIGA }));
        const custom = cakupanBreakdown.call(konteks({ tab: ANITA_CUSTOM, tercentang: TIGA }));

        expect(template).not.toBe(custom);
    });

    it('urutan centang tidak mengubah cakupan', () => {
        const a = cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: TIGA }));
        const b = cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: [...TIGA].reverse() }));

        expect(a).toBe(b);
    });

    it('template tanpa satu pun centang jatuh ke anggota tab agar modal tidak kosong', () => {
        // Sidik jari WAJIB mencerminkan cakupan yang benar-benar dikirim,
        // termasuk cadangan ini — kalau tidak, pemantau bisa diam padahal
        // permintaannya berubah.
        expect(konteks({ tab: ANITA, tercentang: [] }).anggotaBreakdown).toEqual(['B8']);
        expect(cakupanBreakdown.call(konteks({ tab: ANITA, tercentang: [] })))
            .toBe('template:B8');
    });

    it('sidik jari selalu berasal dari daftar NIK yang sama dengan permintaannya', () => {
        const k = konteks({ tab: ANITA, tercentang: TIGA });

        expect(cakupanBreakdown.call(k)).toBe('template:' + k.anggotaBreakdown.slice().sort().join(','));
    });
});
