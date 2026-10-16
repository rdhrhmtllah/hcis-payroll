import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SlotInspeksiPicker from './SlotInspeksiPicker.vue';

const SLOT = [
    { id_pos: 1, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-22 08:30', selesai: '2026-09-22 09:00', sisa: 1 },
    { id_pos: 1, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:30', selesai: '2026-09-21 09:00', sisa: 2 },
    { id_pos: 1, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30', sisa: 2 },
    { id_pos: 2, pos: 'Pos Belakang', lokasi: '', mulai: '2026-09-21 13:00', selesai: '2026-09-21 13:30', sisa: 1 },
];

describe('SlotInspeksiPicker', () => {
    it('mengelompokkan per tanggal berurutan, lalu per pos dengan jam berurutan', () => {
        const wrapper = shallowMount(SlotInspeksiPicker, { props: { slots: SLOT } });

        expect(wrapper.vm.hari.map((h) => h.tanggal)).toEqual(['2026-09-21', '2026-09-22']);
        expect(wrapper.vm.hari[0]).toMatchObject({ namaHari: 'Sen', tanggalPendek: '21 Sep', jumlah: 3 });
        expect(wrapper.vm.tanggalAktif).toBe('2026-09-21');
        expect(wrapper.vm.posAktif.map((p) => p.pos)).toEqual(['Pos Utama', 'Pos Belakang']);
        expect(wrapper.vm.posAktif[0]).toMatchObject({ idPos: 1, lokasi: 'Gerbang depan' });
        expect(wrapper.vm.posAktif[0].slot.map((s) => s.mulai)).toEqual(['2026-09-21 08:00', '2026-09-21 08:30']);
    });

    /**
     * Satu pos yang melayani beberapa shift harus tampil sebagai beberapa kartu.
     *
     * Dulu pengelompokannya hanya per pos dan labelnya diambil dari slot
     * PERTAMA, sehingga pos yang melayani Shift 1, 2, dan 3 muncul sebagai satu
     * kartu berlabel "Shift 1" — dua shift lainnya lenyap dari pandangan admin
     * yang sudah membuka slotnya. Diukur pada data dev 24 Sep 2026: 8 dari 15
     * pasangan (tanggal, pos) memuat lebih dari satu shift.
     */
    it('memisahkan kartu per shift, bukan melebur satu pos jadi satu kartu', () => {
        const TIGA_SHIFT = [
            { id_pos: 1, pos: 'Klinik SHE', lokasi: '', shift: 'Shift 1', mulai: '2026-09-25 08:00', selesai: '2026-09-25 08:15', sisa: 1 },
            { id_pos: 1, pos: 'Klinik SHE', lokasi: '', shift: 'Shift 2', mulai: '2026-09-25 16:00', selesai: '2026-09-25 16:15', sisa: 1 },
            { id_pos: 1, pos: 'Klinik SHE', lokasi: '', shift: 'Shift 3', mulai: '2026-09-25 01:00', selesai: '2026-09-25 01:15', sisa: 1 },
            { id_pos: 1, pos: 'Klinik SHE', lokasi: '', shift: 'Shift 1', mulai: '2026-09-25 08:15', selesai: '2026-09-25 08:30', sisa: 1 },
        ];

        const wrapper = shallowMount(SlotInspeksiPicker, { props: { slots: TIGA_SHIFT } });
        const kartu = wrapper.vm.posAktif;

        expect(kartu.map((k) => k.shift)).toEqual(['Shift 3', 'Shift 1', 'Shift 2']);
        expect(kartu.map((k) => k.slot.length)).toEqual([1, 2, 1]);

        /* Kuncinya harus unik: dua kartu berbagi idPos yang sama, dan v-for
           yang memakai idPos sebagai key membuat Vue memakai ulang simpul yang
           salah. */
        expect(new Set(kartu.map((k) => k.kunci)).size).toBe(kartu.length);

        /* Label tiap kartu benar-benar milik slotnya, bukan milik slot pertama
           pos itu. */
        kartu.forEach((k) => k.slot.forEach((s) => expect(s.shift).toBe(k.shift)));
    });

    it('membuka tanggal slot yang sudah dipilih, bukan selalu tanggal pertama', () => {
        const wrapper = shallowMount(SlotInspeksiPicker, {
            props: { slots: SLOT, modelValue: { id_pos: 1, pos: 'Pos Utama', mulai: '2026-09-22 08:30' } },
        });

        expect(wrapper.vm.tanggalAktif).toBe('2026-09-22');
    });

    it('memancarkan pos, mulai, dan selesai slot yang diklik', () => {
        const wrapper = shallowMount(SlotInspeksiPicker, { props: { slots: SLOT } });
        wrapper.vm.pilih(SLOT[3]);

        expect(wrapper.emitted('update:modelValue')[0][0]).toEqual({
            id_pos: 2,
            pos: 'Pos Belakang',
            lokasi: '',
            mulai: '2026-09-21 13:00',
            selesai: '2026-09-21 13:30',
        });
    });

    it('tidak memancarkan apa pun saat dinonaktifkan', () => {
        const wrapper = shallowMount(SlotInspeksiPicker, { props: { slots: SLOT, disabled: true } });
        wrapper.vm.pilih(SLOT[0]);

        expect(wrapper.emitted('update:modelValue')).toBeFalsy();
    });

    it('menampilkan keadaan kosong bila SHE belum membuka slot', () => {
        const wrapper = shallowMount(SlotInspeksiPicker, { props: { slots: [] } });

        expect(wrapper.text()).toContain('Belum ada slot inspeksi');
    });

    it('membedakan pos lewat id_pos, bukan nama — dua pos bernama mirip tetap dua kelompok', () => {
        const slots = [
            { id_pos: 1, pos: 'Pos Utama', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30', sisa: 1 },
            { id_pos: 3, pos: 'Pos Utama', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30', sisa: 1 },
        ];
        const wrapper = shallowMount(SlotInspeksiPicker, {
            props: { slots, modelValue: { id_pos: 3, mulai: '2026-09-21 08:00' } },
        });

        expect(wrapper.vm.posAktif.map((p) => p.idPos)).toEqual([1, 3]);
        expect(wrapper.vm.dipilih(slots[0])).toBe(false);
        expect(wrapper.vm.dipilih(slots[1])).toBe(true);
    });
});
