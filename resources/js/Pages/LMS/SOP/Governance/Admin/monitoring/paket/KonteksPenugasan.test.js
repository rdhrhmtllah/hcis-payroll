import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import KonteksPenugasan from './KonteksPenugasan.vue';

const PENUGASAN = {
    Tipe_Tenggat: 'DEADLINE',
    Tenggat: '2026-07-26',
    Is_Terlambat: true,
    Hari_Tersisa: -10,
    Tanggal_Assign: '2026-07-14',
    Tanggal_Selesai: null,
    Status_Paket: 'PROGRESS',
    Sumber: 'MANUAL',
};

const BUKTI = {
    tanggal_assign: '2026-07-14',
    tanggal_selesai: null,
    pertama_dibuka: '2026-07-17 14:23',
    konfirmasi_terakhir: '2026-07-25 11:34',
    total_wajib: 3,
    total_selesai: 1,
};

const PAKET_RESMI = {
    dokumen_total: 3,
    dokumen_disahkan: 0,
    dokumen_dengan_publisher: 0,
    dokumen_dengan_validitas: 0,
    metadata_warnings: ['KODE_PAKET_KOSONG', 'OWNER_BELUM_TERCATAT'],
};

function pasang(props = {}) {
    return mount(KonteksPenugasan, {
        props: { penugasan: PENUGASAN, bukti: BUKTI, paketResmi: PAKET_RESMI, ...props },
    });
}

describe('KonteksPenugasan — konteks tenggat', () => {
    it('menyatakan tenggat dan seberapa jauh terlewat', () => {
        const teks = pasang().find('.kp-tenggat').text();

        expect(teks).toContain('26 Jul 2026');
        expect(teks).toContain('10');
    });

    it('menandai keterlambatan sebagai keadaan, bukan sekadar teks abu-abu', () => {
        expect(pasang().find('.kp-tenggat').classes()).toContain('telat');
    });

    it('menyebut sisa hari saat belum lewat tenggat', () => {
        const wrapper = pasang({
            penugasan: { ...PENUGASAN, Is_Terlambat: false, Hari_Tersisa: 5 },
        });

        expect(wrapper.find('.kp-tenggat').text()).toContain('5');
        expect(wrapper.find('.kp-tenggat').classes()).not.toContain('telat');
    });

    it('menyatakan tanpa tenggat saat penugasan memang sampai dicabut', () => {
        const wrapper = pasang({
            penugasan: {
                ...PENUGASAN,
                Tipe_Tenggat: 'SAMPAI_DICABUT',
                Tenggat: null,
                Is_Terlambat: false,
                Hari_Tersisa: null,
            },
        });

        expect(wrapper.find('.kp-tenggat').text()).toContain('Berlaku Tanpa Batas Tenggat');
    });
});

describe('KonteksPenugasan — jejak waktu', () => {
    it('menampilkan urutan ditugaskan, dibuka, dan konfirmasi terakhir', () => {
        const teks = pasang().find('.kp-jejak').text();

        expect(teks).toContain('14 Jul 2026'); // ditugaskan
        expect(teks).toContain('17 Jul 2026'); // pertama dibuka
        expect(teks).toContain('25 Jul 2026'); // konfirmasi terakhir
    });

    it('menyatakan belum pernah dibuka alih-alih tanda hubung kosong', () => {
        const wrapper = pasang({ bukti: { ...BUKTI, pertama_dibuka: null } });

        expect(wrapper.find('.kp-jejak').text()).toContain('Belum pernah dibuka');
    });

    it('menyebut sumber penugasan', () => {
        expect(pasang().text()).toContain('MANUAL');
    });
});

describe('KonteksPenugasan — kelengkapan dokumen paket', () => {
    it('menyajikan hitungan dokumen apa adanya', () => {
        const teks = pasang().find('.kp-dok').text();

        expect(teks).toContain('3');
        expect(teks).toContain('Disahkan');
        expect(teks).toContain('Tanggal Berlaku Valid');
    });

    it('mendaftar catatan kelengkapan yang belum terisi', () => {
        const teks = pasang().find('.kp-catatan').text();

        expect(teks).toContain('Kode paket');
        expect(teks).toContain('Pemilik');
    });

    it('menyembunyikan catatan saat metadata paket sudah lengkap', () => {
        const wrapper = pasang({ paketResmi: { ...PAKET_RESMI, metadata_warnings: [] } });

        expect(wrapper.find('.kp-catatan').exists()).toBe(false);
    });

    it('tidak menyimpulkan apa pun tentang karyawannya', () => {
        const teks = pasang().text().toLowerCase();

        for (const vonis of ['mencurigakan', 'lalai', 'buruk', 'melanggar']) {
            expect(teks).not.toContain(vonis);
        }
    });
});
