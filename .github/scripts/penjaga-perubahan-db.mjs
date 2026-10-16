/**
 * Penjaga perubahan basis data.
 *
 * Tiga hal yang berulang kali salah dilakukan agent yang kehilangan konteks, dan
 * ketiganya tidak pernah gagal saat uji berjalan — baru terasa di produksi:
 *
 *   1. Membuat migration Laravel baru. Proyek ini SENGAJA tidak memakai migration
 *      untuk perubahan skema; semuanya lewat berkas DDL yang dijalankan manual dan
 *      dicatat SQL Tracker. Migration baru akan berjalan otomatis saat deploy dan
 *      mengubah skema produksi tanpa ada yang memutuskan.
 *   2. Menaruh berkas perubahan tanpa pasangannya (forward tanpa drop) atau tanpa
 *      entri di status.json. Perubahan yang tak tercatat tidak akan pernah
 *      dijalankan di produksi, dan ketiadaannya baru ketahuan saat fiturnya mati.
 *   3. Menyunting perubahan yang SUDAH masuk SQL Tracker. Berkasnya sudah menjadi
 *      catatan tentang apa yang dijalankan di produksi; menyuntingnya membuat
 *      catatan itu berbohong. Perbaikan wajib jadi perubahan baru.
 *
 * Berkas migration yang SUDAH ada (jobs, failed_jobs, dan kawan-kawan bawaan
 * framework) tidak diusik: yang ditahan hanya berkas yang BARU ditambahkan.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const POLA_MIGRATION = /^database\/migrations\//;
const POLA_PERUBAHAN = /^database\/(ddl|dml)\/([^/]+)\//;

/** Berkas wajib per jenis perubahan. */
const BERKAS_WAJIB = {
    ddl: ['forward.sql', 'drop.sql'],
    dml: ['data-change.sql'],
};

export function idPerubahanTersentuh(berkasBerubah) {
    const hasil = new Map();

    for (const berkas of berkasBerubah) {
        const cocok = berkas.match(POLA_PERUBAHAN);

        if (!cocok) {
            continue;
        }

        const [, jenis, id] = cocok;

        if (!hasil.has(id)) {
            hasil.set(id, { id, jenis, berkas: [] });
        }

        hasil.get(id).berkas.push(berkas);
    }

    return [...hasil.values()];
}

/**
 * Perubahan yang sudah punya jejak di SQL Tracker atau sudah dijalankan di
 * produksi tidak boleh disunting lagi. Sebelum garis itu, menyunting di tempat
 * justru dianjurkan.
 */
export function sudahBeku(entri) {
    if (!entri) {
        return false;
    }

    const referensi = entri.sql_tracker?.reference ?? null;

    return Boolean(referensi) || entri.production?.status === 'DONE';
}

/**
 * @param {object} arg
 * @param {string[]} arg.berkasDitambah  berkas yang BARU ditambahkan pada PR
 * @param {string[]} arg.berkasBerubah   seluruh berkas yang berubah pada PR
 * @param {string[]} arg.berkasRepo      seluruh berkas yang ada di repo setelah PR
 * @param {{changes: object[]}} arg.status  isi database/ddl/status.json setelah PR
 */
export function evaluasiPerubahanDb({ berkasDitambah = [], berkasBerubah = [], berkasRepo = [], status = { changes: [] } }) {
    const pelanggaran = [];

    const migrationBaru = berkasDitambah.filter((b) => POLA_MIGRATION.test(b));

    if (migrationBaru.length > 0) {
        pelanggaran.push({
            jenis: 'migration-baru',
            pesan: 'Migration Laravel baru tidak boleh ditambahkan. Perubahan skema proyek ini ditulis '
                + 'sebagai berkas DDL di database/ddl/<change-id>/ dan dijalankan manual lewat SQL Tracker.',
            berkas: migrationBaru,
        });
    }

    const daftarStatus = Array.isArray(status?.changes) ? status.changes : [];
    const indeksStatus = new Map(daftarStatus.map((e) => [e.change_id, e]));

    for (const perubahan of idPerubahanTersentuh(berkasBerubah)) {
        const entri = indeksStatus.get(perubahan.id);

        if (!entri) {
            pelanggaran.push({
                jenis: 'tanpa-entri-status',
                pesan: `Perubahan "${perubahan.id}" tidak punya entri di database/ddl/status.json. `
                    + 'Perubahan yang tak tercatat tidak akan pernah dijalankan di produksi.',
                berkas: perubahan.berkas,
            });

            continue;
        }

        if (sudahBeku(entri)) {
            pelanggaran.push({
                jenis: 'sudah-beku',
                pesan: `Perubahan "${perubahan.id}" sudah masuk SQL Tracker atau sudah dijalankan di produksi, `
                    + 'sehingga berkasnya adalah catatan tentang apa yang benar-benar dijalankan. '
                    + 'Perbaikan wajib ditulis sebagai perubahan BARU, bukan dengan menyunting yang ini.',
                berkas: perubahan.berkas,
            });
        }

        const wajib = BERKAS_WAJIB[perubahan.jenis] ?? [];
        const hilang = wajib.filter((nama) => !berkasRepo.includes(`database/${perubahan.jenis}/${perubahan.id}/${nama}`));

        if (hilang.length > 0) {
            pelanggaran.push({
                jenis: 'berkas-wajib-hilang',
                pesan: `Perubahan "${perubahan.id}" belum lengkap. Berkas yang hilang: ${hilang.join(', ')}.`,
                berkas: perubahan.berkas,
            });
        }
    }

    return { lolos: pelanggaran.length === 0, pelanggaran };
}

export function rakitLaporanDb(hasil) {
    if (hasil.lolos) {
        return 'OK. Tidak ada masalah pada perubahan basis data.';
    }

    const baris = ['PENJAGA PERUBAHAN BASIS DATA MENOLAK PR INI.', ''];

    for (const p of hasil.pelanggaran) {
        baris.push(`[${p.jenis}] ${p.pesan}`);
        p.berkas.slice(0, 20).forEach((b) => baris.push(`    - ${b}`));

        if (p.berkas.length > 20) {
            baris.push(`    ... dan ${p.berkas.length - 20} berkas lain.`);
        }

        baris.push('');
    }

    return baris.join('\n');
}

function ambilArg(nama, fallback = '') {
    const i = process.argv.indexOf(`--${nama}`);

    return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback;
}

function git(args) {
    return execFileSync('git', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
}

function baris(keluaran) {
    return keluaran.split('\n').map((b) => b.trim()).filter((b) => b.length > 0);
}

function utama() {
    const base = ambilArg('base');
    const head = ambilArg('head', 'HEAD');

    if (!base) {
        console.error('Pemakaian: node .github/scripts/penjaga-perubahan-db.mjs --base <ref> [--head <ref>]');
        process.exit(2);
    }

    const rentang = `${base}...${head}`;

    const hasil = evaluasiPerubahanDb({
        berkasDitambah: baris(git(['diff', '--name-only', '--diff-filter=A', rentang])),
        berkasBerubah: baris(git(['diff', '--name-only', rentang])),
        berkasRepo: baris(git(['ls-tree', '-r', '--name-only', head])),
        status: JSON.parse(readFileSync('database/ddl/status.json', 'utf8')),
    });

    console.log(rakitLaporanDb(hasil));

    process.exit(hasil.lolos ? 0 : 1);
}

if (process.argv[1] && process.argv[1].endsWith('penjaga-perubahan-db.mjs')) {
    utama();
}
