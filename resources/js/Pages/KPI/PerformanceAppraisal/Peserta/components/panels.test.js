import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaSayaAssignmentPanel from './PaSayaAssignmentPanel.vue';
import PaSayaHistoryPanel from './PaSayaHistoryPanel.vue';
import PaSayaResultPanel from './PaSayaResultPanel.vue';
import PaSayaSummaryPanel from './PaSayaSummaryPanel.vue';

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisCard: {
        props: ['title', 'description', 'loading'],
        template: '<article class="card"><h2>{{ title }}</h2><p>{{ description }}</p><slot name="header" /><slot name="actions" /><slot /><slot name="footer" /></article>',
    },
    HcisChart: { props: ['title', 'description'], template: '<div class="chart">{{ title }}</div>' },
    HcisDataTable: {
        props: ['data', 'loading', 'total'],
        template: '<div class="data-table"><slot name="grid" :data="data" /></div>',
    },
    HcisDialog: {
        props: ['modelValue', 'title', 'description'],
        template: '<div v-if="modelValue" class="hcis-dialog"><h3>{{ title }}</h3><slot /><slot name="footer" /></div>',
    },
    HcisIcon: { props: ['name'], template: '<i :data-icon="name" />' },
    HcisProgress: { props: ['value'], template: '<div class="progress">{{ value }}</div>' },
    HcisResultState: {
        props: ['status', 'title', 'description', 'errorCode', 'requestId'],
        template: '<div class="result-state">{{ status }} {{ title }} {{ description }} {{ errorCode }} {{ requestId }}</div>',
    },
    HcisStatCard: { props: ['label', 'value'], template: '<div class="stat">{{ label }} {{ value }}</div>' },
    HcisStatusBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisTable: {
        props: ['rows'],
        template: '<div class="table"><div v-for="row in rows" :key="row.Id_PA_Cycle">{{ row.Kode_Cycle }}<slot name="cell-cycle" :row="row" /><slot name="cell-final" :row="row" /></div></div>',
    },
};

describe('Performance Appraisal Saya panels', () => {
    it('menampilkan progres server tanpa menghitung ulang progres', () => {
        const wrapper = mount(PaSayaSummaryPanel, {
            props: {
                data: {
                    karyawan: { nama: 'Aji', kode_karyawan: 'A4' },
                    total_assignment: '8',
                    sudah_selesai: '4',
                    belum_dimulai: '3',
                    deadline_dekat: '2',
                    terlambat: '1',
                    progres_persen: 51,
                    ambang_dekat_hari: 3,
                },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('51%');
        expect(wrapper.text()).toContain('2');
        expect(wrapper.text()).toContain('terlambat');
    });

    it('ringkasan memakai bobot, grade, dan urutan riwayat yang benar', () => {
        const periode = (id, final, grade, nama) => ({
            Id_PA_Cycle: id, Kode_Cycle: `C${id}`, Nama_Cycle: `Cycle ${id}`, Tahun: '2026',
            Nilai_KPI: '80.00', Nilai_Kompetensi: '70.00', Final_Score: final,
            Kode_Grade_Final: grade, Nama_Grade_Final: nama,
            Bobot_KPI_Terpakai: '75.00', Bobot_Kompetensi_Terpakai: '25.00',
        });
        const wrapper = mount(PaSayaSummaryPanel, {
            props: {
                data: {
                    karyawan: { nama: 'Ridho' }, total_assignment: 3, sudah_selesai: 3, sudah_terkirim: 1,
                    belum_dikirim: 2, belum_dimulai: 0, sedang_dikerjakan: 0, deadline_dekat: 0, terlambat: 0,
                    progres_persen: 100,
                    di_luar_jendela: { total: 4, per_status: { LOCKED: 4 } },
                },
                // Server mengirim TERLAMA lebih dulu.
                history: {
                    periode: [periode(1, '90.61', 'C_PLUS', 'Cukup'), periode(2, '81.76', 'B', 'Baik')],
                    jumlah_periode: 2, rata_rata: 86.19, terbaik: 90.61, perubahan_terakhir: -8.85,
                },
            },
            global: { stubs: uiStubs },
        });
        const teks = wrapper.text();

        // 1. Bobot dari data, bukan 60/40.
        expect(teks).toContain('Bobot 75%');
        expect(teks).toContain('Bobot 25%');
        expect(teks).not.toContain('60%');
        // 2 + 3. Kartu hasil memakai periode TERBARU dan nama grade dari server.
        expect(teks).toContain('81.76');
        expect(teks).toContain('Baik');
        expect(teks).not.toContain('Sangat Baik');
        const baris = wrapper.findAll('.pa-mini-table-row');
        expect(baris[0].text()).toContain('Cycle 2');
        expect(baris[0].text()).toContain('Cycle Terbaru');
        // 4. Terisi dan terkirim dipisah.
        expect(wrapper.find('[data-uji="belum-dikirim"]').text()).toContain('2 lembar terisi belum dikirim');
        // 6. Tugas di cycle yang tidak dibuka disebut.
        expect(wrapper.find('[data-uji="di-luar-jendela"]').text()).toContain('cycle belum dibuka');
    });

    it('menampilkan score di atas 100 dan hanya detail agregat kompetensi', () => {
        const wrapper = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: {
                        Nama_Cycle: 'PA H1',
                        Kode_Cycle: 'PA-2026-H1',
                        Tahun: '2026',
                        Nama_Level_Saat_Cycle: 'STAFF',
                        Nilai_KPI: '125.00',
                        Nilai_Kompetensi: '88.50',
                        Final_Score: '114.20',
                        Kode_Grade_Final: 'A',
                        Published_At: '2026-09-01 09:00:00',
                        dikalibrasi: true,
                    },
                    kompetensi: [{ Kode_Competency: 'INT', Nama_Competency: 'Integritas', Kode_Type: 'CORE', Nama_Type: 'Core', Nilai: '125.00' }],
                    catatan: [],
                },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('114,2');
        expect(wrapper.text()).toContain('125');
        expect(wrapper.text()).toContain('Dikalibrasi');
        expect(wrapper.text()).not.toContain('nilai per penilai');
    });

    /*
     | CATATAN DIKELOMPOKKAN PER PERTANYAAN, HANYA SATU KESIMPULAN RESMI (DARI PENILAI DIABAIKAN).
     |
     | Setiap dimensi kualitatif hanya menampilkan satu kesimpulan resmi.
     */
    it('mengelompokkan catatan per dimensi dan hanya menampilkan satu kesimpulan resmi', () => {
        const wrapper = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: { Final_Score: '80.00', Published_At: '2026-09-01 09:00:00' },
                    kompetensi: [],
                    catatan: [
                        { Kode_Type: 'QL01', Nama_Type: 'Strength', Isi: 'Kesimpulan HR', dari_penilai: false },
                        { Kode_Type: 'QL01', Nama_Type: 'Strength', Isi: 'Kata penilai satu', dari_penilai: true },
                        { Kode_Type: 'QL01', Nama_Type: 'Strength', Isi: 'Kata penilai dua', dari_penilai: true },
                        { Kode_Type: 'QL02', Nama_Type: 'Development Area', Isi: 'Kata penilai tiga', dari_penilai: true },
                    ],
                },
            },
            global: { stubs: uiStubs },
        });

        const teks = wrapper.text();
        expect(teks.match(/Strength/g)).toHaveLength(1);
        expect(teks).toContain('Kesimpulan HR');
        expect(teks).not.toContain('Kata penilai satu');
        expect(teks).not.toContain('Kata penilai dua');
        expect(teks).not.toContain('Kata penilai tiga');
        expect(teks).not.toContain('Masukan penilai');

        // Hanya dimensi dengan kesimpulan resmi yang dirender
        expect(wrapper.findAll('.pa-saya-result__note-group')).toHaveLength(1);
        expect(wrapper.find('.pa-saya-result__note-line--kesimpulan').text()).toBe('Kesimpulan HR');
    });

    /* Balasan tanpa `dari_penilai` - bentuk lama - tidak boleh menuduh: kalimat yang tidak
       diketahui asalnya tidak ditaruh di bawah judul "Masukan penilai". */
    it('memperlakukan catatan tanpa penanda asal sebagai kesimpulan', () => {
        const wrapper = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: { Final_Score: '80.00', Published_At: '2026-09-01 09:00:00' },
                    kompetensi: [],
                    catatan: [{ Kode_Type: 'QL01', Nama_Type: 'Strength', Isi: 'Tanpa penanda' }],
                },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('.pa-saya-result__note-line--kesimpulan').text()).toBe('Tanpa penanda');
        expect(wrapper.text()).not.toContain('Masukan penilai');
    });

    it('kartu Strength/Development Area hanya untuk pertanyaan yang ada di hasil ini', () => {
        const pasang = (catatan) => mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: { Final_Score: '85.00', Published_At: '2026-09-01 09:00:00' },
                    kompetensi: [],
                    catatan,
                },
            },
            global: { stubs: uiStubs },
        });

        // Siklus tanpa pertanyaan kualitatif: tidak ada kartu "belum ada kesimpulan".
        expect(pasang([]).findAll('.pa-qualitative-card')).toHaveLength(0);

        // Strength belum disimpulkan, Development Area sudah.
        const w = pasang([
            { Kode_Type: 'QL01', Nama_Type: 'Strength', Isi: null, dari_penilai: false },
            { Kode_Type: 'QL02', Nama_Type: 'Development Area', Isi: '<p>Perlu delegasi.</p>', dari_penilai: false },
        ]);
        expect(w.findAll('.pa-qualitative-card')).toHaveLength(2);
        expect(w.findAll('.pa-qualitative-card__empty')).toHaveLength(1);
        expect(w.text()).toContain('Perlu delegasi.');
    });

    it('hasil tanpa batas 100, dengan bobot nyata, nama grade server, dan ambang yang sungguh ada', async () => {
        const w = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: {
                        Final_Score: '116.58', Nilai_KPI: '124.67', Nilai_Kompetensi: '80.00',
                        Kode_Grade_Final: 'C', Nama_Grade_Final: 'Kurang',
                        Bobot_KPI_Terpakai: '75.00', Bobot_Kompetensi_Terpakai: '25.00',
                        Published_At: '2026-09-01 09:00:00',
                    },
                    kompetensi: [{ Kode_Competency: 'X1', Nama_Competency: 'Tanpa Ambang', Nilai: '60.00', Nilai_Ambang: null }],
                    catatan: [],
                },
            },
            global: { stubs: uiStubs },
        });
        const teks = w.text();

        expect(teks).not.toContain('Maks. 100');
        expect(teks).toContain('bobot 75%');
        expect(teks).toContain('Kurang');
        expect(teks).not.toContain('Cukup');

        await w.find('.pa-competency-item').trigger('click');
        expect(w.find('[data-uji="status-ambang"]').exists()).toBe(false);
    });

    it('riwayat memakai nama grade server, tanpa /100, dan jujur soal cycle pertama', () => {
        const satu = mount(PaSayaHistoryPanel, {
            props: {
                data: {
                    periode: [{ Id_PA_Cycle: '1', Kode_Cycle: 'PA-1', Tahun: 2026, Final_Score: '116.58', Kode_Grade_Final: 'C', Nama_Grade_Final: 'Kurang' }],
                    jumlah_periode: 1, rata_rata: 116.58, terbaik: 116.58, perubahan_terakhir: null,
                },
            },
            global: { stubs: uiStubs },
        });
        const teks = satu.text();

        expect(teks).toContain('Kurang');
        expect(teks).not.toContain('Perlu Peningkatan');
        expect(teks).not.toContain('/ 100');
        expect(teks).not.toContain('Stabil');
        expect(teks).toContain('Tren muncul setelah ada dua cycle terbit');
    });

    it('mempertahankan urutan periode terlama ke terbaru pada tren', () => {
        const wrapper = mount(PaSayaHistoryPanel, {
            props: {
                data: {
                    periode: [
                        { Id_PA_Cycle: '1', Kode_Cycle: 'PA-2025-H2', Tahun: 2025, Final_Score: '82.50', Kode_Grade_Final: 'B' },
                        { Id_PA_Cycle: '2', Kode_Cycle: 'PA-2026-H1', Tahun: 2026, Final_Score: '114.20', Kode_Grade_Final: 'A' },
                    ],
                    jumlah_periode: 2,
                    rata_rata: 98.35,
                    terbaik: '114.20',
                    perubahan_terakhir: '31.70',
                },
            },
            global: { stubs: uiStubs },
        });

        const labels = wrapper.findAll('.pa-saya-history__chart-labels span');
        expect(labels[0].text()).toContain('PA-2025-H2');
        expect(labels[1].text()).toContain('PA-2026-H1');
        expect(wrapper.find('polyline').attributes('points')).toContain('0,');
    });

    it('membedakan error assignment dari daftar kosong', () => {
        const wrapper = mount(PaSayaAssignmentPanel, {
            props: { error: 'Tugas tidak dapat dimuat.', errorStatus: 403, rows: [] },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('.result-state').text()).toContain('Tugas tidak dapat dimuat.');
        expect(wrapper.find('.data-table').exists()).toBe(false);
    });

    it('menampilkan multi-cycle selector saat ada lebih dari 1 siklus dan memancarkan cycle-change', async () => {
        const wrapper = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: {
                        Id_PA_Cycle: '2',
                        Nama_Cycle: 'PA 2026 H1',
                        Kode_Cycle: 'PA-2026-H1',
                        Tahun: '2026',
                        Final_Score: '114.20',
                        Published_At: '2026-09-01 09:00:00',
                    },
                    kompetensi: [],
                    catatan: [],
                },
                history: {
                    periode: [
                        { Id_PA_Cycle: '1', Kode_Cycle: 'PA-2025-H2', Nama_Cycle: 'PA 2025 H2', Tahun: '2025', Final_Score: '82.50', Kode_Grade_Final: 'B' },
                        { Id_PA_Cycle: '2', Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'PA 2026 H1', Tahun: '2026', Final_Score: '114.20', Kode_Grade_Final: 'A' },
                    ],
                },
            },
            global: { stubs: uiStubs },
        });

        const pills = wrapper.findAll('.pa-result-cycle-pill');
        expect(pills).toHaveLength(2);
        expect(pills[1].classes()).toContain('is-active');

        await pills[0].trigger('click');
        expect(wrapper.emitted('cycle-change')).toBeTruthy();
        expect(wrapper.emitted('cycle-change')[0]).toEqual(['1']);
    });

    it('pilar komposisi nilai tampil, tanpa posisi 9-box HAV', async () => {
        const wrapper = mount(PaSayaResultPanel, {
            props: {
                data: {
                    tersedia: true,
                    hasil: {
                        Nilai_KPI: '125.00',
                        Nilai_Kompetensi: '88.50',
                        Final_Score: '114.20',
                        Kode_Grade_Final: 'A',
                        Published_At: '2026-09-01 09:00:00',
                    },
                    kompetensi: [],
                    catatan: [],
                },
            },
            global: { stubs: uiStubs },
        });

        const pillar = wrapper.find('.pa-hero-pillar--composition');
        expect(pillar.exists()).toBe(true);
        expect(pillar.text()).toContain('KOMPOSISI NILAI AKHIR');
        expect(pillar.text()).toContain('125');
        expect(pillar.text()).toContain('88,5');
        // Kuadran HAV bukan bagian hasil yang dibaca karyawan (sejak 6 Oktober 2026).
        expect(wrapper.text()).not.toMatch(/9-Box|HAV|Kuadran/i);
    });

    it('riwayat tidak menampilkan kuadran 9-box HAV', () => {
        const wrapper = mount(PaSayaHistoryPanel, {
            props: {
                data: {
                    periode: [
                        { Id_PA_Cycle: '1', Kode_Cycle: 'PA-2025-H2', Tahun: 2025, Final_Score: '82.50', Kode_Grade_Final: 'B', Nomor_Box: 4, Nama_Kuadran: 'Potential Candidate' },
                        { Id_PA_Cycle: '2', Kode_Cycle: 'PA-2026-H1', Tahun: 2026, Final_Score: '90.00', Kode_Grade_Final: 'A', Nomor_Box: 1, Nama_Kuadran: 'Striving Star' },
                    ],
                    jumlah_periode: 2,
                    rata_rata: 86.25,
                    terbaik: '90.00',
                    perubahan_terakhir: '7.50',
                },
            },
            global: { stubs: uiStubs },
        });

        // Walau data lama masih membawa kotaknya, layar tidak menampilkannya.
        expect(wrapper.text()).not.toMatch(/9-Box|HAV|Kuadran|Striving Star|Potential Candidate|Box [0-9]/i);
    });
});

