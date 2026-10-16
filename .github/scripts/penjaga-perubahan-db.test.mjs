import { describe, expect, it } from 'vitest';

import {
    evaluasiPerubahanDb,
    idPerubahanTersentuh,
    rakitLaporanDb,
    sudahBeku,
} from './penjaga-perubahan-db.mjs';

const status = {
    changes: [
        {
            change_id: 'DDL-2026-100-contoh',
            sql_tracker: { status: 'PENDING', reference: null },
            production: { status: 'PENDING' },
        },
        {
            change_id: 'DDL-2026-050-sudah-tercatat',
            sql_tracker: { status: 'TRACKER_PENDING', reference: 'TRK-771' },
            production: { status: 'PENDING' },
        },
        {
            change_id: 'DML-2026-020-sudah-produksi',
            sql_tracker: { status: 'PENDING', reference: null },
            production: { status: 'DONE' },
        },
    ],
};

const repoLengkap = [
    'database/ddl/DDL-2026-100-contoh/forward.sql',
    'database/ddl/DDL-2026-100-contoh/drop.sql',
    'database/ddl/DDL-2026-050-sudah-tercatat/forward.sql',
    'database/ddl/DDL-2026-050-sudah-tercatat/drop.sql',
    'database/dml/DML-2026-020-sudah-produksi/data-change.sql',
];

describe('idPerubahanTersentuh', () => {
    it('mengelompokkan berkas per change id dan mengenali jenisnya', () => {
        expect(idPerubahanTersentuh([
            'database/ddl/DDL-2026-100-contoh/forward.sql',
            'database/ddl/DDL-2026-100-contoh/drop.sql',
            'database/dml/DML-2026-020-sudah-produksi/data-change.sql',
            'app/Http/Kernel.php',
        ])).toEqual([
            {
                id: 'DDL-2026-100-contoh',
                jenis: 'ddl',
                berkas: ['database/ddl/DDL-2026-100-contoh/forward.sql', 'database/ddl/DDL-2026-100-contoh/drop.sql'],
            },
            {
                id: 'DML-2026-020-sudah-produksi',
                jenis: 'dml',
                berkas: ['database/dml/DML-2026-020-sudah-produksi/data-change.sql'],
            },
        ]);
    });

    it('mengabaikan status.json yang tidak berada di dalam folder perubahan', () => {
        expect(idPerubahanTersentuh(['database/ddl/status.json'])).toEqual([]);
    });
});

describe('sudahBeku', () => {
    it('beku begitu punya referensi SQL Tracker', () => {
        expect(sudahBeku(status.changes[1])).toBe(true);
    });

    it('beku begitu sudah dijalankan di produksi', () => {
        expect(sudahBeku(status.changes[2])).toBe(true);
    });

    it('belum beku selama masih PENDING di dua-duanya', () => {
        // Sebelum garis itu, menyunting di tempat justru yang dianjurkan.
        expect(sudahBeku(status.changes[0])).toBe(false);
    });
});

describe('evaluasiPerubahanDb', () => {
    it('MENOLAK migration Laravel yang baru ditambahkan', () => {
        const hasil = evaluasiPerubahanDb({
            berkasDitambah: ['database/migrations/2026_09_08_000000_create_sesuatu.php'],
            berkasBerubah: ['database/migrations/2026_09_08_000000_create_sesuatu.php'],
            berkasRepo: repoLengkap,
            status,
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran[0].jenis).toBe('migration-baru');
    });

    it('membiarkan migration bawaan yang sudah ada dan hanya disunting', () => {
        // Berkas jobs/failed_jobs bawaan framework sudah ada sejak lama. Menahan
        // penyuntingannya akan membuat penjaga ini merah pada PR yang tak ada
        // hubungannya, dan penjaga yang merah tanpa sebab akan dimatikan orang.
        const hasil = evaluasiPerubahanDb({
            berkasDitambah: [],
            berkasBerubah: ['database/migrations/2025_06_23_083942_create_jobs_table.php'],
            berkasRepo: repoLengkap,
            status,
        });

        expect(hasil.lolos).toBe(true);
    });

    it('MENOLAK perubahan yang tidak punya entri di status.json', () => {
        const hasil = evaluasiPerubahanDb({
            berkasBerubah: ['database/ddl/DDL-2026-999-liar/forward.sql'],
            berkasRepo: [...repoLengkap, 'database/ddl/DDL-2026-999-liar/forward.sql', 'database/ddl/DDL-2026-999-liar/drop.sql'],
            status,
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran.map((p) => p.jenis)).toContain('tanpa-entri-status');
    });

    it('MENOLAK perubahan DDL tanpa drop.sql', () => {
        const hasil = evaluasiPerubahanDb({
            berkasBerubah: ['database/ddl/DDL-2026-100-contoh/forward.sql'],
            berkasRepo: ['database/ddl/DDL-2026-100-contoh/forward.sql'],
            status,
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran.map((p) => p.jenis)).toContain('berkas-wajib-hilang');
    });

    it('MENOLAK penyuntingan perubahan yang sudah masuk SQL Tracker', () => {
        const hasil = evaluasiPerubahanDb({
            berkasBerubah: ['database/ddl/DDL-2026-050-sudah-tercatat/forward.sql'],
            berkasRepo: repoLengkap,
            status,
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran.map((p) => p.jenis)).toContain('sudah-beku');
    });

    it('meloloskan perubahan baru yang lengkap dan tercatat', () => {
        const hasil = evaluasiPerubahanDb({
            berkasBerubah: ['database/ddl/DDL-2026-100-contoh/forward.sql', 'database/ddl/DDL-2026-100-contoh/drop.sql'],
            berkasRepo: repoLengkap,
            status,
        });

        expect(hasil.lolos).toBe(true);
    });

    it('meloloskan PR yang tidak menyentuh basis data sama sekali', () => {
        const hasil = evaluasiPerubahanDb({
            berkasBerubah: ['resources/js/Pages/Beranda.vue'],
            berkasRepo: repoLengkap,
            status,
        });

        expect(hasil.lolos).toBe(true);
    });
});

describe('rakitLaporanDb', () => {
    it('menyebut jenis pelanggaran dan berkasnya', () => {
        const hasil = evaluasiPerubahanDb({
            berkasDitambah: ['database/migrations/2026_09_08_000000_x.php'],
            berkasBerubah: ['database/migrations/2026_09_08_000000_x.php'],
            berkasRepo: repoLengkap,
            status,
        });

        const laporan = rakitLaporanDb(hasil);

        expect(laporan).toContain('MENOLAK');
        expect(laporan).toContain('migration-baru');
        expect(laporan).toContain('database/migrations/2026_09_08_000000_x.php');
    });
});
