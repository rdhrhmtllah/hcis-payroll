import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaRiwayatKalibrasiPenilai from './PaRiwayatKalibrasiPenilai.vue';

describe('PaRiwayatKalibrasiPenilai', () => {
    it('menampilkan siapa, alasan, dan perubahan dari berapa ke berapa', () => {
        const w = mount(PaRiwayatKalibrasiPenilai, {
            props: {
                riwayat: [
                    {
                        kalibrasi_id: 1,
                        assignment_id: 2,
                        penilai: { kode_karyawan: 'A4', nama: 'Aji' },
                        kategori: { kode: 'ATASAN', nama: 'Atasan' },
                        alasan: 'Diselaraskan',
                        pada: '2026-09-18 10:15:00',
                        oleh: 1,
                        perubahan: [
                            {
                                competency_id: 11,
                                kode: 'CV01',
                                nama: 'Integritas',
                                skor_sebelum: 90,
                                skor_sesudah: 82,
                                skor_maksimal: 100,
                            },
                        ],
                    },
                ],
            },
        });

        expect(w.text()).toContain('Aji');
        expect(w.text()).toContain('Diselaraskan');
        expect(w.text()).toContain('Integritas');
        expect(w.text()).toMatch(/90\s*→\s*82/);
    });

    it('tindakan yang hanya mengubah esai tetap tampil dengan teks sebelum dan sesudah', () => {
        const w = mount(PaRiwayatKalibrasiPenilai, {
            props: {
                riwayat: [
                    {
                        kalibrasi_id: 2,
                        assignment_id: 3,
                        penilai: { kode_karyawan: 'A4', nama: 'Aji' },
                        kategori: { kode: 'PEER', nama: 'Rekan Sejawat' },
                        alasan: 'Diperjelas',
                        pada: '2026-09-18 11:00:00',
                        oleh: 1,
                        perubahan: [],
                        perubahan_jawaban: [
                            {
                                kode_soal: 'Q07',
                                competency_id: 21,
                                nama: 'Strength',
                                jawaban_sebelum: 'Teks lama',
                                jawaban_sesudah: 'Teks baru',
                            },
                        ],
                    },
                ],
            },
        });

        expect(w.find('ul').exists()).toBe(false);
        expect(w.text()).toContain('Strength');
        expect(w.find('.pa-riwayat-kp__sebelum').text()).toBe('Teks lama');
        expect(w.text()).toContain('Teks baru');
    });

    it('kosong berarti tidak dirender', () => {
        expect(mount(PaRiwayatKalibrasiPenilai, { props: { riwayat: [] } }).html()).toBe('<!--v-if-->');
    });
});

describe('PaRiwayatKalibrasiPenilai — HTML', () => {
    it('skrip di teks esai tidak pernah sampai ke DOM', () => {
        const w = mount(PaRiwayatKalibrasiPenilai, {
            props: {
                riwayat: [
                    {
                        kalibrasi_id: 3,
                        assignment_id: 4,
                        penilai: { kode_karyawan: 'A4', nama: 'Aji' },
                        kategori: { kode: 'PEER', nama: 'Rekan' },
                        alasan: 'x',
                        pada: '2026-09-18 12:00:00',
                        oleh: 1,
                        perubahan: [],
                        perubahan_jawaban: [
                            {
                                kode_soal: 'Q07',
                                competency_id: null,
                                nama: null,
                                jawaban_sebelum: '<p>Lama</p>',
                                jawaban_sesudah: '<p>Baru <strong>tebal</strong><img src="x" onerror="alert(1)"></p>',
                            },
                        ],
                    },
                ],
            },
        });

        expect(w.find('.pa-riwayat-kp__sesudah strong').text()).toBe('tebal');
        expect(w.html()).not.toContain('onerror');
    });
});
