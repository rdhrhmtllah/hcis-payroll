import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import KompetensiTim from './PaLeaderKompetensiTim.vue';

const stubs = {
    HcisIcon: { template: '<i />' },
    HcisButton: { props: ['label'], emits: ['click'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
    HcisCard: { props: ['title', 'description'], template: '<section><h3>{{ title }}</h3><slot /></section>' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><h2>{{ title }}</h2><slot /><slot name="footer" /></div>' },
};

const komp = (kode, nama, rata, kkm, extra = {}) => ({
    kode_kompetensi: kode, nama_kompetensi: nama, nama_type: 'Core Value', rata_rata: rata, nilai_ambang: kkm,
    jumlah_orang: 2, deskripsi: null, expected_behaviour: null, anggota: [], ...extra,
});

const data = [
    komp('K1', 'Integritas', 92, 80),
    komp('K2', 'Kolaborasi', 85, 80),
    komp('K3', 'Analisis', 72, 80, {
        deskripsi: 'Kemampuan mengurai masalah.',
        expected_behaviour: '- Memeriksa data dulu\n- Mencari akar masalah',
        anggota: [{ participant_id: 7, nama: 'Andi', nilai: 76 }, { participant_id: 8, nama: 'Budi', nilai: 68 }],
    }),
    komp('K4', 'Inovasi', 60, null),
];

const pasang = () => mount(KompetensiTim, { props: { kompetensi: data }, global: { stubs } });

describe('PaLeaderKompetensiTim', () => {
    it('diurutkan dari tertinggi, dengan selisih terhadap KKM', () => {
        const w = pasang();
        const baris = w.findAll('.pa-lkt__baris').map((b) => b.text());
        expect(baris[0]).toContain('Integritas');
        expect(baris[0]).toContain('+12,00 vs KKM');
        expect(baris[2]).toContain('−8,00 vs KKM');
        expect(baris[3]).toContain('KKM belum ada');
    });

    it('saringan Kekuatan / Perlu dikembangkan', async () => {
        const w = pasang();
        expect(w.find('[data-uji="saring-atas"]').text()).toContain('2');
        expect(w.find('[data-uji="saring-bawah"]').text()).toContain('1');
        await w.find('[data-uji="saring-bawah"]').trigger('click');
        const baris = w.findAll('.pa-lkt__baris');
        expect(baris).toHaveLength(1);
        expect(baris[0].text()).toContain('Analisis');
    });

    it('klik kompetensi membuka detail: arti, perilaku, dan nilai anggota', async () => {
        const w = pasang();
        await w.findAll('.pa-lkt__baris')[2].trigger('click');
        const d = w.find('[data-uji="detail-kompetensi"]');
        expect(d.exists()).toBe(true);
        expect(d.text()).toContain('Kemampuan mengurai masalah.');
        expect(d.findAll('[data-uji="daftar-perilaku"] li').map((l) => l.text())).toEqual(['Memeriksa data dulu', 'Mencari akar masalah']);
        expect(d.text()).toContain('di bawah KKM');
        expect(d.text()).toContain('Andi');

        await d.findAll('.pa-lkt-detail__orang')[1].trigger('click');
        expect(w.emitted('detail')[0][0]).toEqual({ participant_id: 8, nama: 'Budi', nilai: 68 });
        expect(w.find('[data-uji="detail-kompetensi"]').exists()).toBe(false);
    });

    it('kompetensi tanpa deskripsi/perilaku menjelaskan bahwa HC belum menulisnya', async () => {
        const w = pasang();
        await w.findAll('.pa-lkt__baris')[0].trigger('click');
        expect(w.find('[data-uji="detail-kompetensi"]').text()).toContain('belum ditulis HC');
    });

    it('lebih dari 6 kompetensi dipotong dengan tombol tampilkan semua', async () => {
        const banyak = Array.from({ length: 8 }, (_, i) => komp(`K${i}`, `Komp ${i}`, 90 - i, 80));
        const w = mount(KompetensiTim, { props: { kompetensi: banyak }, global: { stubs } });
        expect(w.findAll('.pa-lkt__baris')).toHaveLength(6);
        await w.find('[data-uji="tampilkan-semua-kompetensi"]').trigger('click');
        expect(w.findAll('.pa-lkt__baris')).toHaveLength(8);
    });
});
