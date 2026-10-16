/**
 * Gerbang rilis: menahan fitur yang belum boleh muncul di branch tertentu.
 *
 * Skrip ini TIDAK PERNAH menyebut nama fitur. Seluruh pengetahuannya datang dari
 * `.github/gerbang-rilis.json`, sehingga menahan fitur berikutnya cukup menambah
 * satu blok di manifes itu — workflow dan skrip ini tidak disentuh lagi.
 *
 * Dua saringan bekerja bersama, dan yang kedua yang menutup celah sebenarnya:
 *
 *   1. `paths`   — berkas milik fitur. Menangkap kode yang jelas-jelas miliknya.
 *   2. `penanda` — potongan teks pada baris yang DITAMBAH di berkas mana pun.
 *                  Ini yang menangkap fitur menyusup lewat berkas BERSAMA: satu
 *                  baris provider di config/app.php, satu require di routes/web.php,
 *                  satu baris modul di config/exports.php. Berkas-berkas itu bukan
 *                  milik fitur mana pun sehingga lolos saringan path — padahal
 *                  justru baris itulah yang membuat produksi meledak.
 *
 * Hanya baris yang ditambah yang diperiksa. Menghapus jejak fitur dari berkas
 * bersama adalah persis yang kita inginkan, jadi tak boleh ikut tertahan.
 *
 * Zero dependency, dijalankan Node polos. Penjaga yang butuh `npm ci` bisa mati
 * gara-gara paket yang tak terpasang, dan penjaga yang mati diam-diam lebih
 * buruk daripada tidak ada penjaga sama sekali.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/**
 * Branch yang alurnya diatur — hanya dua, dan itu disengaja.
 *
 * Branch kerja (feat/*, fix/*, chore/*) di-merge dua kali: ke `staging` untuk
 * diuji di Cloud Run, lalu ke `master` kalau sudah terbukti. Fitur yang ditahan
 * sederhananya tidak pernah melakukan merge yang kedua.
 *
 * Tidak ada branch `development`: ia tak punya pekerjaan yang tak bisa dilakukan
 * branch kerja, dan hanya menambah satu salinan yang harus terus disinkronkan.
 *
 * Branch di luar daftar ini tidak pernah ditahan — menahan branch kerja hanya
 * akan membuat orang bekerja di luar git.
 */
export const BRANCH_DIATUR = ['staging', 'master'];

/**
 * Glob → RegExp. Hanya tiga bentuk yang didukung, dan itu disengaja: pola yang
 * lebih pintar membuat orang menebak-nebak apa yang tercakup.
 *
 *   `** /`  lintas nol atau lebih segmen        (ditulis tanpa spasi di manifes)
 *   `**`   di akhir: sisa path apa pun
 *   `*`    di dalam satu segmen, tidak melewati '/'
 *   `?`    satu karakter, tidak melewati '/'
 */
export function globKeRegex(pola) {
    let out = '^';

    for (let i = 0; i < pola.length; i += 1) {
        const c = pola[i];

        if (c === '*' && pola[i + 1] === '*') {
            if (pola[i + 2] === '/') {
                // Nol segmen ikut cocok: "app/**/X/**" harus menangkap "app/X/a.php".
                out += '(?:[^/]+/)*';
                i += 2;
            } else {
                out += '.*';
                i += 1;
            }
        } else if (c === '*') {
            out += '[^/]*';
        } else if (c === '?') {
            out += '[^/]';
        } else {
            out += c.replace(/[.+^${}()|[\]\\]/g, '\\$&');
        }
    }

    return new RegExp(`${out}$`);
}

export function cocokSalahSatu(berkas, polaList) {
    return (polaList ?? []).some((pola) => globKeRegex(pola).test(berkas));
}

/**
 * Manifes yang cacat harus GAGAL, bukan diam-diam melewatkan fiturnya. Manifes
 * rusak yang ditafsirkan "tidak ada fitur yang ditahan" adalah cara paling halus
 * bagi gerbang ini untuk berhenti bekerja tanpa ada yang menyadarinya.
 */
export function validasiManifes(manifes) {
    if (!manifes || !Array.isArray(manifes.fitur)) {
        throw new Error('Manifes gerbang rilis tidak sah: kunci "fitur" wajib berupa array.');
    }

    manifes.fitur.forEach((fitur, i) => {
        const di = `fitur[${i}]`;

        if (!fitur.kode || !fitur.nama) {
            throw new Error(`Manifes tidak sah: ${di} wajib punya "kode" dan "nama".`);
        }

        if (!Array.isArray(fitur.boleh_di) || fitur.boleh_di.length === 0) {
            throw new Error(`Manifes tidak sah: ${di} (${fitur.kode}) wajib punya "boleh_di" berisi minimal satu branch.`);
        }

        const asing = fitur.boleh_di.filter((b) => !BRANCH_DIATUR.includes(b));

        if (asing.length > 0) {
            throw new Error(
                `Manifes tidak sah: ${di} (${fitur.kode}) menyebut branch yang tidak diatur: ${asing.join(', ')}. `
                + `Yang dikenal: ${BRANCH_DIATUR.join(', ')}.`,
            );
        }

        const punyaPath = Array.isArray(fitur.paths) && fitur.paths.length > 0;
        const punyaPenanda = Array.isArray(fitur.penanda) && fitur.penanda.length > 0;

        if (!punyaPath && !punyaPenanda) {
            throw new Error(`Manifes tidak sah: ${di} (${fitur.kode}) tidak menahan apa pun — "paths" dan "penanda" dua-duanya kosong.`);
        }
    });

    return manifes;
}

export function labelPintuKeluar(kode) {
    return `izinkan-${String(kode).toLowerCase()}`;
}

/**
 * @param {object} arg
 * @param {object} arg.manifes
 * @param {string} arg.branchTujuan            branch tujuan PR
 * @param {string[]} arg.berkasBerubah         path berkas yang berubah
 * @param {{berkas:string, nomor:number, isi:string}[]} arg.barisDitambah
 * @param {string[]} arg.label                 label pada PR
 */
export function evaluasiGerbang({ manifes, branchTujuan, berkasBerubah = [], barisDitambah = [], label = [] }) {
    validasiManifes(manifes);

    if (!BRANCH_DIATUR.includes(branchTujuan)) {
        return {
            lolos: true,
            diatur: false,
            pelanggaran: [],
            dilewati: [],
        };
    }

    const pelanggaran = [];
    const dilewati = [];

    for (const fitur of manifes.fitur) {
        if (fitur.boleh_di.includes(branchTujuan)) {
            continue;
        }

        const pintu = labelPintuKeluar(fitur.kode);

        if (label.includes(pintu)) {
            dilewati.push({ kode: fitur.kode, nama: fitur.nama, label: pintu });
            continue;
        }

        const berkasMilikFitur = berkasBerubah.filter((berkas) => cocokSalahSatu(berkas, fitur.paths));

        const penandaTersusup = barisDitambah
            .filter((baris) => !berkasMilikFitur.includes(baris.berkas))
            .filter((baris) => !cocokSalahSatu(baris.berkas, fitur.penanda_kecuali))
            .map((baris) => {
                const kena = (fitur.penanda ?? []).find((p) => baris.isi.includes(p));

                return kena ? { ...baris, penanda: kena } : null;
            })
            .filter(Boolean);

        if (berkasMilikFitur.length > 0 || penandaTersusup.length > 0) {
            pelanggaran.push({
                kode: fitur.kode,
                nama: fitur.nama,
                alasan: fitur.alasan ?? null,
                boleh_di: fitur.boleh_di,
                pintuKeluar: pintu,
                berkas: berkasMilikFitur,
                penanda: penandaTersusup,
            });
        }
    }

    return {
        lolos: pelanggaran.length === 0,
        diatur: true,
        pelanggaran,
        dilewati,
    };
}

/** Nama berkas dari `git diff --name-only`, satu per baris. */
export function bacaBerkasBerubah(keluaran) {
    return keluaran
        .split('\n')
        .map((b) => b.trim())
        .filter((b) => b.length > 0);
}

/**
 * Baris yang ditambah, lengkap dengan nomor barisnya, dari `git diff --unified=0`.
 *
 * Berkas biner dan rename tanpa isi lewat begitu saja: keduanya tidak punya baris
 * '+' sehingga tidak pernah masuk hasil.
 */
export function bacaBarisDitambah(diff) {
    const hasil = [];
    let berkas = null;
    let nomor = 0;

    for (const baris of diff.split('\n')) {
        if (baris.startsWith('+++ b/')) {
            berkas = baris.slice('+++ b/'.length).trim();
            continue;
        }

        if (baris.startsWith('+++ /dev/null')) {
            // Berkas dihapus. Tidak ada baris tambahan yang perlu diperiksa.
            berkas = null;
            continue;
        }

        if (baris.startsWith('@@')) {
            const cocok = baris.match(/@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
            nomor = cocok ? Number(cocok[1]) : 0;
            continue;
        }

        if (berkas && baris.startsWith('+')) {
            hasil.push({ berkas, nomor, isi: baris.slice(1) });
            nomor += 1;
        }
    }

    return hasil;
}

export function rakitLaporan(hasil, branchTujuan) {
    const baris = [];

    if (!hasil.diatur) {
        return `Branch tujuan "${branchTujuan}" tidak diatur gerbang rilis. Lewat.`;
    }

    for (const d of hasil.dilewati) {
        baris.push(`~ ${d.kode} (${d.nama}) dilewati karena PR berlabel "${d.label}".`);
    }

    if (hasil.lolos) {
        baris.push(`OK. Tidak ada fitur yang ditahan tersentuh pada PR ke "${branchTujuan}".`);

        return baris.join('\n');
    }

    baris.push(`GERBANG RILIS MENOLAK PR INI KE BRANCH "${branchTujuan}".`);
    baris.push('');

    for (const p of hasil.pelanggaran) {
        baris.push(`Fitur ${p.kode} — ${p.nama}`);

        if (p.alasan) {
            baris.push(`  Alasan ditahan : ${p.alasan}`);
        }

        baris.push(`  Boleh muncul di: ${p.boleh_di.join(', ')}`);

        if (p.berkas.length > 0) {
            baris.push(`  Berkas milik fitur ini yang berubah (${p.berkas.length}):`);
            p.berkas.slice(0, 20).forEach((b) => baris.push(`    - ${b}`));

            if (p.berkas.length > 20) {
                baris.push(`    ... dan ${p.berkas.length - 20} berkas lain.`);
            }
        }

        if (p.penanda.length > 0) {
            baris.push(`  Baris baru berpenanda fitur ini di berkas bersama (${p.penanda.length}):`);
            p.penanda.slice(0, 20).forEach((t) => baris.push(`    - ${t.berkas}:${t.nomor}  memuat "${t.penanda}"`));

            if (p.penanda.length > 20) {
                baris.push(`    ... dan ${p.penanda.length - 20} baris lain.`);
            }
        }

        baris.push('');
        baris.push('  Yang harus dilakukan:');
        baris.push(`    - Kalau perubahannya memang milik fitur ini, arahkan PR ke branch yang diizinkan (${p.boleh_di.join(', ')}).`);
        baris.push('    - Kalau ada perubahan pada kode BERSAMA yang ikut terbawa, pecah jadi PR tersendiri');
        baris.push('      yang berdiri sendiri tanpa menyebut fitur ini. PR itu boleh mendarat lebih dulu.');
        baris.push(`    - Kalau fitur ini memang sudah dinyatakan siap, tambahkan "${branchTujuan}" ke "boleh_di"`);
        baris.push('      di .github/gerbang-rilis.json lewat PR tersendiri — persetujuannya adalah keputusan rilisnya.');
        baris.push(`    - Pengecualian sekali jalan: beri PR ini label "${p.pintuKeluar}".`);
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

function utama() {
    const branchTujuan = ambilArg('target');
    const base = ambilArg('base');
    const head = ambilArg('head', 'HEAD');
    const manifesPath = ambilArg('manifes', '.github/gerbang-rilis.json');

    if (!branchTujuan || !base) {
        console.error('Pemakaian: node .github/scripts/gerbang-rilis.mjs --target <branch> --base <ref> [--head <ref>] [--manifes <path>]');
        process.exit(2);
    }

    const label = (process.env.LABEL_PR ?? '')
        .split(',')
        .map((l) => l.trim())
        .filter((l) => l.length > 0);

    const manifes = JSON.parse(readFileSync(manifesPath, 'utf8'));
    const rentang = `${base}...${head}`;

    const hasil = evaluasiGerbang({
        manifes,
        branchTujuan,
        berkasBerubah: bacaBerkasBerubah(git(['diff', '--name-only', rentang])),
        barisDitambah: bacaBarisDitambah(git(['diff', '--unified=0', rentang])),
        label,
    });

    console.log(rakitLaporan(hasil, branchTujuan));

    process.exit(hasil.lolos ? 0 : 1);
}

// Hanya jalan sebagai CLI; saat diimpor uji, tidak ada efek samping.
if (process.argv[1] && process.argv[1].endsWith('gerbang-rilis.mjs')) {
    utama();
}
