import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SopAturanPaketList from './SopAturanPaketList.vue';

/*
 * Komponen ini memegang satu sifat yang gampang rusak tanpa gejala: setiap
 * penyuntingan harus memancarkan ARRAY BARU, bukan memutasi prop. Kalau ia
 * memutasi, layar tetap terlihat benar sementara nilai yang dikirim ke server
 * sudah berubah sebelum admin menekan Simpan.
 */

const stubs = {
    HcisBadge: true,
    HcisButton: { template: '<button v-bind="$attrs" @click="$emit(\'click\')"></button>' },
    HcisCard: { template: '<div><slot name="header" /><slot /></div>' },
    HcisDatePicker: true,
    HcisEmptyState: { template: '<div class="kosong"><slot /></div>' },
    HcisField: { template: '<div><slot /></div>' },
    HcisInputNumber: true,
    HcisSelect: true,
    HcisSelectOption: true,
    HcisSwitch: true,
};

const paket = (id, nama) => ({
    Id_SOP_Paket: id,
    Nama_Paket: nama,
    Mode_Distribusi: 'PUBLIK',
    Urutan: 1,
    override: false,
    schedule: null,
});

const pasang = (modelValue, options = []) =>
    mount(SopAturanPaketList, { props: { modelValue, options }, global: { stubs } });

describe('SopAturanPaketList', () => {
    it('menampilkan setiap paket yang dipilih', () => {
        const w = pasang([paket('a1', 'Paket A'), paket('b2', 'Paket B')]);

        expect(w.text()).toContain('Paket A');
        expect(w.text()).toContain('Paket B');
    });

    it('menampilkan keadaan kosong ketika belum ada paket', () => {
        const w = pasang([]);

        expect(w.find('.kosong').exists()).toBe(true);
    });

    it('menyembunyikan setelan tenggat selama override mati', () => {
        const w = pasang([paket('a1', 'Paket A')]);

        expect(w.find('[data-uji="setelan-override"]').exists()).toBe(false);
    });

    it('memunculkan setelan tenggat ketika override dinyalakan', () => {
        const baris = {
            ...paket('a1', 'Paket A'),
            override: true,
            schedule: {
                deadline_mode: 'RELATIVE',
                deadline_days: 30,
                deadline_date: null,
                reminder_enabled: false,
                reminder_interval_days: null,
            },
        };
        const w = pasang([baris]);

        expect(w.find('[data-uji="setelan-override"]').exists()).toBe(true);
    });

    it('membuang satu paket memancarkan daftar tanpa paket itu', async () => {
        const w = pasang([paket('a1', 'Paket A'), paket('b2', 'Paket B')]);

        await w.find('[data-uji="buang-a1"]').trigger('click');

        const emitted = w.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(emitted.at(-1)[0].map((p) => p.Id_SOP_Paket)).toEqual(['b2']);
    });

    it('tidak memutasi prop yang diterimanya', async () => {
        const asal = [paket('a1', 'Paket A'), paket('b2', 'Paket B')];
        const w = pasang(asal);

        await w.find('[data-uji="buang-a1"]').trigger('click');

        expect(asal.map((p) => p.Id_SOP_Paket)).toEqual(['a1', 'b2']);
    });

    it('menulis ulang Urutan dari posisi baris setelah penyuntingan', async () => {
        const w = pasang([paket('a1', 'Paket A'), paket('b2', 'Paket B'), paket('c3', 'Paket C')]);

        await w.find('[data-uji="buang-a1"]').trigger('click');

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil.map((p) => p.Urutan)).toEqual([1, 2]);
    });

    it('menyalakan override mengisi jadwal baku, bukan membiarkannya null', async () => {
        const w = pasang([paket('a1', 'Paket A')]);

        w.vm.setOverride(0, true);
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0][0];
        expect(hasil.override).toBe(true);
        expect(hasil.schedule).toBeTruthy();
        expect(hasil.schedule.deadline_mode).toBe('NONE');
    });

    it('mematikan override mengosongkan jadwalnya', async () => {
        const baris = {
            ...paket('a1', 'Paket A'),
            override: true,
            schedule: { deadline_mode: 'RELATIVE', deadline_days: 30 },
        };
        const w = pasang([baris]);

        w.vm.setOverride(0, false);
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0][0];
        expect(hasil.override).toBe(false);
        expect(hasil.schedule).toBeNull();
    });

    it('jadwal dua paket tidak saling terhubung', async () => {
        const w = pasang([paket('a1', 'Paket A'), paket('b2', 'Paket B')]);

        w.vm.setOverride(0, true);
        await w.vm.$nextTick();
        const setelahA = w.emitted('update:modelValue').at(-1)[0];

        const w2 = pasang(setelahA);
        w2.vm.setOverride(1, true);
        await w2.vm.$nextTick();
        const setelahB = w2.emitted('update:modelValue').at(-1)[0];

        w2.vm.ubahJadwal(1, 'deadline_days', 99);
        await w2.vm.$nextTick();
        const setelahUbah = w2.emitted('update:modelValue').at(-1)[0];

        expect(setelahUbah[1].schedule.deadline_days).toBe(99);
        expect(setelahB[0].schedule.deadline_days).not.toBe(99);
    });

    it('menggeser paket menukar posisinya', async () => {
        const w = pasang([paket('a1', 'Paket A'), paket('b2', 'Paket B')]);

        w.vm.geser(1, -1);
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil.map((p) => p.Id_SOP_Paket)).toEqual(['b2', 'a1']);
        expect(hasil.map((p) => p.Urutan)).toEqual([1, 2]);
    });

    it('menambah paket dari pilihan menyalin identitasnya', async () => {
        const w = pasang([], [{ Id_SOP_Paket: 'z9', Nama_Paket: 'Paket Z', Mode_Distribusi: 'TERBATAS' }]);

        w.vm.tambah('z9');
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil).toHaveLength(1);
        expect(hasil[0].Nama_Paket).toBe('Paket Z');
        expect(hasil[0].Mode_Distribusi).toBe('TERBATAS');
        expect(hasil[0].override).toBe(false);
    });

    it('menambah id yang tak ada di pilihan tidak memancarkan apa pun', async () => {
        const w = pasang([], [{ Id_SOP_Paket: 'z9', Nama_Paket: 'Paket Z', Mode_Distribusi: 'PUBLIK' }]);

        w.vm.tambah('tidak-ada');
        await w.vm.$nextTick();

        expect(w.emitted('update:modelValue')).toBeFalsy();
    });
});
