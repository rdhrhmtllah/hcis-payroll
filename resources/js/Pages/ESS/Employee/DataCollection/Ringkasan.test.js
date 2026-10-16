import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Ringkasan from './Ringkasan.vue';

const skema = {
    versi: 1,
    langkah: [{ kunci: 'identitas', judul: 'Identitas', field: ['nama_lengkap', 'jenis_kelamin'] }],
    field: {
        nama_lengkap: { label: 'Nama lengkap', tipe: 'teks' },
        jenis_kelamin: {
            label: 'Jenis kelamin',
            tipe: 'pilihan',
            label_pilihan: { L: 'Laki-laki', P: 'Perempuan' },
        },
    },
};

const buat = (props = {}) =>
    mount(Ringkasan, {
        props: {
            skema,
            jawaban: { nama_lengkap: 'Budi', jenis_kelamin: 'L' },
            dikirimPada: '2026-08-14 09:30:00',
            ...props,
        },
    });

describe('Ringkasan pengumpulan data', () => {
    it('menampilkan jawaban dengan label pilihan yang terbaca manusia', () => {
        const w = buat();

        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('Laki-laki');
        expect(w.text()).not.toContain('>L<');
    });

    it('menandai field kosong dengan em dash, bukan dibiarkan hampa', () => {
        const w = buat({ jawaban: { nama_lengkap: 'Budi' } });

        expect(w.text()).toContain('—');
    });

    it('menampilkan tanggal kirim dalam bahasa Indonesia', () => {
        const w = buat();

        expect(w.text()).toContain('Agustus');
    });

    it('tidak menghasilkan Invalid Date saat tanggal tak terbaca', () => {
        const w = buat({ dikirimPada: 'entah-apa' });

        expect(w.text()).not.toContain('Invalid Date');
        expect(w.text()).toContain('entah-apa');
    });
});
