import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RekamBacaKaryawan from './RekamBacaKaryawan.vue';

const DATA = {
    ringkas: {
        total_rekam: 3,
        total_detik: 5040,
        item_unik: 3,
        versi_unik: 3,
        pernah_dibuka: 2,
        dikonfirmasi: 1,
    },
    komposisi: {
        status: [
            { label: 'BELUM', jumlah: 2 },
            { label: 'DIKONFIRMASI', jumlah: 1 },
        ],
        tipe: [
            { label: 'PARAGRAF', jumlah: 2 },
            { label: 'DOKUMEN', jumlah: 1 },
        ],
    },
    item: [
        {
            Judul_Item: 'Bagian 1 — ketentuan umum',
            Id_SOP_Paket: 'pk1',
            Nama_Paket: 'APD',
            Tipe_Item: 'PARAGRAF',
            Status: 'DIKONFIRMASI',
            Versi_Ke: 1,
            Waktu_Buka: '2026-07-06 17:29',
            Waktu_Konfirmasi: '2026-07-06 17:41',
            Durasi_Aktif_Detik: 1315,
            Halaman_Terakhir: null,
            Persen_Selesai: null,
            Skor_Quiz: null,
            Jumlah_Percobaan: 0,
            Status_Lulus: null,
            Konfirmasi_IP: null,
            Konfirmasi_User_Agent: null,
        },
        {
            Judul_Item: 'Prosedur APD',
            Id_SOP_Paket: 'pk1',
            Nama_Paket: 'APD',
            Tipe_Item: 'DOKUMEN',
            Status: 'BELUM',
            Versi_Ke: 2,
            Waktu_Buka: null,
            Waktu_Konfirmasi: null,
            Durasi_Aktif_Detik: 0,
            Halaman_Terakhir: 3,
            Persen_Selesai: 40,
            Skor_Quiz: null,
            Jumlah_Percobaan: 0,
            Status_Lulus: null,
            Konfirmasi_IP: null,
            Konfirmasi_User_Agent: null,
        },
        {
            Judul_Item: 'Kebijakan Umum',
            Id_SOP_Paket: 'pk2',
            Nama_Paket: 'Builder',
            Tipe_Item: 'PARAGRAF',
            Status: 'BELUM',
            Versi_Ke: 6,
            Waktu_Buka: null,
            Waktu_Konfirmasi: null,
            Durasi_Aktif_Detik: 0,
            Halaman_Terakhir: null,
            Persen_Selesai: null,
            Skor_Quiz: null,
            Jumlah_Percobaan: 0,
            Status_Lulus: null,
            Konfirmasi_IP: null,
            Konfirmasi_User_Agent: null,
        },
    ],
    jejak_versi: [
        {
            Judul_Item: 'Kebijakan Umum',
            Nama_Paket: 'Builder',
            versi: [
                { Versi_Ke: 2, Status: 'DIGANTIKAN', Waktu: null },
                { Versi_Ke: 6, Status: 'BELUM', Waktu: null },
            ],
        },
    ],
};

function pasang(props = {}) {
    return mount(RekamBacaKaryawan, {
        props: { data: DATA, loading: false, ...props },
        global: {
            stubs: {
                HcisCard: { template: '<div class="s-card"><slot /><slot name="actions" /></div>' },
                HcisBadge: true,
                HcisButton: true,
                HcisEmptyState: true,
                HcisSkeleton: true,
            },
        },
    });
}

describe('RekamBacaKaryawan — tabel rekam', () => {
    it('merender satu baris per rekam tersimpan', () => {
        expect(pasang().findAll('tbody tr')).toHaveLength(3);
    });

    it('menyajikan durasi aktif dalam satuan yang terbaca', () => {
        const teks = pasang().text();

        expect(teks).toContain('22m'); // 1315 detik pada baris item
        expect(teks).toContain('1j 24m'); // 5040 detik pada ringkasan
    });

    it('menampilkan versi yang dihadapi pada tiap baris', () => {
        expect(pasang().find('tbody tr').text()).toContain('v01');
    });
});

describe('RekamBacaKaryawan — komposisi', () => {
    it('menampilkan chip komposisi status beserta jumlahnya', () => {
        const teks = pasang().find('.rb-komposisi').text();

        expect(teks).toContain('BELUM');
        expect(teks).toContain('2');
        expect(teks).toContain('DIKONFIRMASI');
    });

    it('menyaring tabel saat chip status diklik', async () => {
        const wrapper = pasang();

        const chip = wrapper.findAll('.rb-chip').find((c) => c.text().includes('DIKONFIRMASI'));
        await chip.trigger('click');

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    });

    it('melepas saringan saat chip yang sama diklik lagi', async () => {
        const wrapper = pasang();
        const chip = wrapper.findAll('.rb-chip').find((c) => c.text().includes('DIKONFIRMASI'));

        await chip.trigger('click');
        await chip.trigger('click');

        expect(wrapper.findAll('tbody tr')).toHaveLength(3);
    });
});

describe('RekamBacaKaryawan — dikelompokkan per paket', () => {
    it('membuat satu kelompok per paket, bukan satu tabel datar', () => {
        const grup = pasang().findAll('.rb-grup');

        expect(grup).toHaveLength(2);
        expect(grup[0].text()).toContain('APD');
        expect(grup[1].text()).toContain('Builder');
    });

    it('memisahkan paket yang kebetulan bernama sama', () => {
        const senama = DATA.item.map((r) => ({ ...r, Nama_Paket: 'Nama Sama' }));
        const wrapper = pasang({ data: { ...DATA, item: senama } });

        // pk1 dan pk2 tetap dua kelompok meski namanya identik.
        expect(wrapper.findAll('.rb-grup')).toHaveLength(2);
    });

    it('menyertakan rekap faktual per paket di kepala kelompok', () => {
        const kepala = pasang().findAll('.rb-grup-kepala')[0].text();

        expect(kepala).toContain('APD');
        expect(kepala).toContain('2'); // dua rekam di paket ini
        expect(kepala).toContain('22m'); // total durasi aktif paket ini
    });

    it('tidak mengulang nama paket di tiap baris', () => {
        const barisPertama = pasang().find('.rb-grup tbody tr').text();

        expect(barisPertama).not.toContain('APD');
    });

    it('saringan tetap bekerja lintas paket', async () => {
        const wrapper = pasang();
        const chip = wrapper.findAll('.rb-chip').find((c) => c.text().includes('BELUM'));

        await chip.trigger('click');

        // BELUM ada di kedua paket, jadi kedua kelompok bertahan.
        expect(wrapper.findAll('.rb-grup')).toHaveLength(2);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('menyembunyikan kelompok yang tak menyisakan baris setelah disaring', async () => {
        const wrapper = pasang();
        const chip = wrapper.findAll('.rb-chip').find((c) => c.text().includes('DIKONFIRMASI'));

        await chip.trigger('click');

        expect(wrapper.findAll('.rb-grup')).toHaveLength(1);
        expect(wrapper.find('.rb-grup').text()).toContain('APD');
    });

    it('bisa dilipat per kelompok', async () => {
        const wrapper = pasang();

        await wrapper.findAll('.rb-grup-kepala')[0].trigger('click');

        expect(wrapper.findAll('.rb-grup')[0].find('tbody').exists()).toBe(false);
        expect(wrapper.findAll('.rb-grup')[1].find('tbody').exists()).toBe(true);
    });
});

describe('RekamBacaKaryawan — jejak versi', () => {
    it('menampilkan item yang versinya lebih dari satu', () => {
        const teks = pasang().find('.rb-jejak').text();

        expect(teks).toContain('Kebijakan Umum');
        // Dua digit sejak penomoran versi dimulai dari nol.
        expect(teks).toContain('v02');
        expect(teks).toContain('v06');
    });

    it('menyembunyikan blok jejak saat tak ada item yang berganti versi', () => {
        const wrapper = pasang({ data: { ...DATA, jejak_versi: [] } });

        expect(wrapper.find('.rb-jejak').exists()).toBe(false);
    });
});

describe('RekamBacaKaryawan — keadaan kosong & memuat', () => {
    it('menyatakan tak ada rekam alih-alih tabel kosong', () => {
        const kosong = {
            ringkas: { total_rekam: 0, total_detik: 0, item_unik: 0, versi_unik: 0, pernah_dibuka: 0, dikonfirmasi: 0 },
            komposisi: { status: [], tipe: [] },
            item: [],
            jejak_versi: [],
        };
        const wrapper = pasang({ data: kosong });

        expect(wrapper.find('.rb-kosong').exists()).toBe(true);
        expect(wrapper.find('tbody').exists()).toBe(false);
    });

    it('menampilkan penanda muat tanpa merender tabel', () => {
        const wrapper = pasang({ loading: true });

        expect(wrapper.find('.rb-loading').exists()).toBe(true);
        expect(wrapper.find('tbody').exists()).toBe(false);
    });
});
