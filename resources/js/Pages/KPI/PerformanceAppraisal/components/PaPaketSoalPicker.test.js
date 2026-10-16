import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaPaketSoalPicker from './PaPaketSoalPicker.vue';

/*
 | Uji RENDER, bukan aturan — aturannya sudah dijaga utils/kelayakanPaket.test.js.
 |
 | Yang dijaga di sini satu hal saja, dan hal itu pernah rusak tanpa satu pun uji memerah:
 | kartu yang dirender dari BENTUK BALASAN HC LEARN YANG SEBENARNYA harus benar-benar bisa
 | dipilih. Ketika komponen ini masih membaca `Dimensi` dan `Detail` yang sudah tidak
 | dikirim, seluruh kartu mati, tombol "Gunakan Paket Ini" tidak pernah ada di DOM, dan
 | pencacah "Rekomendasi" menunjukkan nol — sementara suite Vue tetap hijau.
 */

const uiStubs = {
    HcisAlert: { template: '<div class="alert-stub"><slot /></div>' },
    HcisBadge: { props: ['label'], template: '<span class="badge-stub">{{ label }}</span>' },
    HcisButton: {
        props: ['label', 'disabled'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
    },
    HcisEmptyState: {
        props: ['title', 'description'],
        template: '<div class="empty-stub">{{ title }} {{ description }}<slot name="actions" /></div>',
    },
    HcisField: { props: ['modelValue'], template: '<input class="field-stub" />' },
    HcisIcon: { template: '<i />' },
    HcisSkeleton: { template: '<div class="skeleton-stub" />' },
};

const kompetensiWajib = [
    { dimensi_kode: 'BEHAVIOR', dimensi_nama: 'Behavior', kompetensi: [{ kode: 'B01' }, { kode: 'B02' }] },
    { dimensi_kode: 'CORE_VALUE', dimensi_nama: 'Core Value', kompetensi: [{ kode: 'CV01' }] },
];

/* Bentuknya mengikuti `rapikanBalasanHcLearn()`: tiga bagian dalam dua alat tes, dan satu
   bank soal (900) sengaja dipakai ulang di kedua alat — kasus sah yang identitas
   berpasangannya harus tangani tanpa menuduhnya kembar. */
const ujianSepadan = {
    Id_Master_Ujian: '100162',
    Nama_Ujian: 'Employee Assessment 360 - OFFICER',
    Id_Master_Paket_Ujian: '100149',
    Nama_Paket: 'Paket Employee Assessment 360',
    Kode_Ujian: 'EVO-ACXWER-ASM',
    Bagian: [
        { id_jenis_indikator: '174', id_jenis_soal: '900', nama_alat_tes: 'Alat Utama', nama: 'Analytical Thinking', kode_soal: 'K01' },
        { id_jenis_indikator: '174', id_jenis_soal: '901', nama_alat_tes: 'Alat Utama', nama: 'Integrity', kode_soal: 'K02' },
        { id_jenis_indikator: '175', id_jenis_soal: '900', nama_alat_tes: 'Alat Pendukung', nama: 'Analytical Thinking', kode_soal: 'K01' },
    ],
    jumlah_bagian: 3,
    bagian_tersedia: true,
};

const ujianTakSepadan = {
    ...ujianSepadan,
    Id_Master_Ujian: '100159',
    Nama_Ujian: 'TIU + KRAEPLIN SEASON 7',
    Bagian: ujianSepadan.Bagian.slice(0, 2),
    jumlah_bagian: 2,
};

function pasang(paket) {
    return mount(PaPaketSoalPicker, {
        props: { paket, kompetensiWajib, namaSasaran: 'Staff x Atasan Langsung' },
        global: { stubs: uiStubs },
    });
}

describe('PaPaketSoalPicker', () => {
    it('merender ujian dari bentuk HC Learn dan membiarkannya dipilih', async () => {
        const wrapper = pasang([ujianSepadan]);

        expect(wrapper.text()).toContain('Employee Assessment 360 - OFFICER');
        expect(wrapper.text()).toContain('Paket Employee Assessment 360');
        expect(wrapper.text()).toContain('3 bagian untuk 3 kompetensi wajib level');

        const tombol = wrapper.findAll('button').find((b) => b.text().includes('Gunakan Paket Ini'));

        expect(tombol).toBeTruthy();

        await tombol.trigger('click');

        expect(wrapper.emitted('pilih')?.[0]?.[0]?.Id_Master_Ujian).toBe('100162');
    });

    it('mematikan ujian yang cacah bagiannya tidak sepadan, beserta sebabnya', () => {
        const wrapper = pasang([ujianTakSepadan]);

        expect(wrapper.findAll('button').some((b) => b.text().includes('Gunakan Paket Ini'))).toBe(false);
        expect(wrapper.text()).toContain('2 bagian');
        expect(wrapper.text()).toContain('3 kompetensi');
    });

    it('membuka pratinjau bagian yang dikelompokkan per alat tes', async () => {
        const wrapper = pasang([ujianSepadan]);

        const pratinjau = wrapper.findAll('button').find((b) => b.text().includes('Preview'));

        expect(pratinjau).toBeTruthy();

        await pratinjau.trigger('click');

        expect(wrapper.text()).toContain('Alat Utama');
        expect(wrapper.text()).toContain('Alat Pendukung');
        expect(wrapper.text()).toContain('Integrity');

        /* Bahwa kompetensinya BELUM ditetapkan harus terbaca di layar. Pratinjau yang diam
           soal itu membuat admin mengira pemetaannya sudah datang dari HC Learn. */
        expect(wrapper.text()).toContain('Belum berlabel');
    });

    /* Sel yang SUDAH dilabeli menampilkan mapping tersimpannya, bukan "Belum berlabel" (UAT 13 Okt). */
    it('menampilkan mapping kompetensi yang tersimpan untuk sel ini', async () => {
        const wrapper = mount(PaPaketSoalPicker, {
            props: {
                paket: [ujianSepadan],
                kompetensiWajib,
                labelBagian: { id_master_ujian: '100162', peta: { '174|900': { kode: 'B01', nama: 'Berpikir Analitis' }, '174|901': { kode: 'CV01', nama: 'Integritas' } } },
            },
            global: { stubs: uiStubs },
        });
        await wrapper.findAll('button').find((b) => b.text().includes('Preview')).trigger('click');

        const label = wrapper.findAll('[data-uji="picker-label"]').map((x) => x.text());
        expect(label).toEqual(['→ B01 Berpikir Analitis', '→ CV01 Integritas']);
        /* Bagian ketiga belum dilabeli untuk sel ini — disebut, dengan arah perbaikannya. */
        expect(wrapper.text()).toContain('Belum berlabel untuk sel ini');
    });
});
