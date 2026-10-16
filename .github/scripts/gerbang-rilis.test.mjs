import { describe, expect, it } from 'vitest';

import {
    BRANCH_DIATUR,
    bacaBarisDitambah,
    bacaBerkasBerubah,
    cocokSalahSatu,
    evaluasiGerbang,
    globKeRegex,
    rakitLaporan,
    validasiManifes,
} from './gerbang-rilis.mjs';

/**
 * Manifes contoh. Sengaja TIDAK membaca .github/gerbang-rilis.json yang asli:
 * uji yang ikut berubah setiap kali manifes disunting berhenti menguji perilaku
 * gerbangnya dan mulai menguji isi manifes hari itu.
 */
const manifes = {
    fitur: [
        {
            kode: 'DEMO',
            nama: 'Fitur Contoh',
            alasan: 'Masih dikembangkan.',
            boleh_di: ['staging'],
            paths: ['app/**/FiturContoh/**', 'resources/js/Pages/FiturContoh/**'],
            penanda: ['FiturContoh', 'N_DEMO_'],
            penanda_kecuali: ['.github/gerbang-rilis.json', 'docs/**'],
        },
    ],
};

const barisDari = (berkas, isi, nomor = 1) => ({ berkas, nomor, isi });

describe('globKeRegex', () => {
    it('membiarkan ** melintasi nol segmen', () => {
        // Kalau ** menuntut minimal satu segmen, berkas yang persis di bawah akar
        // fitur lolos diam-diam — dan itu justru berkas yang paling sering ada.
        expect(globKeRegex('app/**/X/**').test('app/X/a.php')).toBe(true);
        expect(globKeRegex('app/**/X/**').test('app/Services/KPI/X/Sub/a.php')).toBe(true);
    });

    it('menolak yang di luar akar pola', () => {
        expect(globKeRegex('app/**/X/**').test('app/Services/Y/a.php')).toBe(false);
        expect(globKeRegex('app/**/X/**').test('resources/X/a.php')).toBe(false);
    });

    it('membuat * berhenti di batas segmen', () => {
        expect(globKeRegex('config/*.php').test('config/exports.php')).toBe(true);
        expect(globKeRegex('config/*.php').test('config/dev/exports.php')).toBe(false);
    });

    it('memperlakukan titik sebagai huruf biasa, bukan wildcard', () => {
        expect(cocokSalahSatu('configxphp', ['config.php'])).toBe(false);
        expect(cocokSalahSatu('config.php', ['config.php'])).toBe(true);
    });
});

describe('validasiManifes', () => {
    it('menolak boleh_di yang menyebut branch tak dikenal', () => {
        // Salah ketik nama branch tidak boleh berubah jadi izin. Kalau ini lolos,
        // "boleh_di: [prod]" akan menahan fitur dari SEMUA branch yang diatur
        // dan orang akan menyimpulkan gerbangnya rusak, bukan manifesnya.
        expect(() => validasiManifes({
            fitur: [{ kode: 'X', nama: 'X', boleh_di: ['prod'], paths: ['a/**'] }],
        })).toThrow(/tidak diatur/i);
    });

    it('menolak fitur yang tidak menahan apa pun', () => {
        expect(() => validasiManifes({
            fitur: [{ kode: 'X', nama: 'X', boleh_di: ['staging'], paths: [], penanda: [] }],
        })).toThrow(/tidak menahan apa pun/i);
    });

    it('menolak manifes tanpa array fitur', () => {
        expect(() => validasiManifes({})).toThrow(/wajib berupa array/i);
    });
});

describe('evaluasiGerbang', () => {
    it('MENOLAK PR ke master yang menyentuh berkas milik fitur', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran[0].kode).toBe('DEMO');
        expect(hasil.pelanggaran[0].berkas).toEqual(['app/Services/KPI/FiturContoh/Layanan.php']);
    });

    it('meloloskan PR ke staging', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'staging',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
        });

        expect(hasil.lolos).toBe(true);
    });

    it('MENOLAK baris baru berpenanda di berkas BERSAMA', () => {
        // Inti gerbang ini. Berkas config/app.php bukan milik fitur mana pun, jadi
        // saringan path meloloskannya — padahal satu baris provider di sini persis
        // yang membuat produksi meledak.
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['config/app.php'],
            barisDitambah: [barisDari('config/app.php', '            App\\Services\\FiturContoh\\Provider::class,', 171)],
        });

        expect(hasil.lolos).toBe(false);
        expect(hasil.pelanggaran[0].penanda[0]).toMatchObject({
            berkas: 'config/app.php',
            nomor: 171,
            penanda: 'FiturContoh',
        });
    });

    it('meloloskan perubahan pada berkas bersama yang tidak menyebut fitur', () => {
        // Refactor generik pada kode bersama HARUS bisa mendarat lebih dulu. Kalau
        // ini tertahan, orang akan berhenti memecah PR-nya.
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['app/Services/SYSTEM/Application/Reports/ReportRunner.php'],
            barisDitambah: [barisDari('app/Services/SYSTEM/Application/Reports/ReportRunner.php', 'abstract public function moduleKey(): string;')],
        });

        expect(hasil.lolos).toBe(true);
    });

    it('meloloskan baris yang DIHAPUS meski mengandung penanda', () => {
        // Mencabut jejak fitur dari berkas bersama justru yang kita inginkan.
        // bacaBarisDitambah tidak pernah menghasilkan baris '-', jadi diuji lewat
        // masukan kosong: yang dihapus tak sampai ke evaluator sama sekali.
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['config/app.php'],
            barisDitambah: [],
        });

        expect(hasil.lolos).toBe(true);
    });

    it('meloloskan penanda di berkas yang dikecualikan', () => {
        // Tanpa ini, PR yang MEMPROMOSIKAN fitur ditolak oleh penandanya sendiri
        // dan gerbangnya terkunci selamanya dari dalam.
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['.github/gerbang-rilis.json', 'docs/rilis.md'],
            barisDitambah: [
                barisDari('.github/gerbang-rilis.json', '  "penanda": ["FiturContoh"]'),
                barisDari('docs/rilis.md', 'FiturContoh sudah siap rilis.'),
            ],
        });

        expect(hasil.lolos).toBe(true);
    });

    it('meloloskan lewat label pintu keluar, dan mencatatnya', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
            label: ['izinkan-demo'],
        });

        expect(hasil.lolos).toBe(true);
        expect(hasil.dilewati[0]).toMatchObject({ kode: 'DEMO', label: 'izinkan-demo' });
    });

    it('hanya mengatur staging dan master', () => {
        // Alurnya sengaja dua branch. Kalau suatu saat "development" dihidupkan
        // lagi tanpa memperbarui manifes, tiap fitur yang ditahan akan bocor ke
        // sana tanpa satu pun galat — uji ini yang membuat perubahan itu berisik.
        expect(BRANCH_DIATUR).toEqual(['staging', 'master']);
    });

    it('tidak menahan apa pun pada branch di luar alur', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'feat/apa-saja',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
        });

        expect(hasil.lolos).toBe(true);
        expect(hasil.diatur).toBe(false);
    });

    it('tidak melaporkan penanda dua kali untuk berkas yang sudah kena saringan path', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
            barisDitambah: [barisDari('app/Services/KPI/FiturContoh/Layanan.php', 'class FiturContoh {}')],
        });

        expect(hasil.pelanggaran[0].penanda).toEqual([]);
    });
});

describe('bacaBarisDitambah', () => {
    const diff = [
        'diff --git a/config/app.php b/config/app.php',
        'index 1111111..2222222 100644',
        '--- a/config/app.php',
        '+++ b/config/app.php',
        '@@ -170,0 +171,2 @@',
        '+            App\\Services\\FiturContoh\\Provider::class,',
        '+            App\\Services\\Lain\\Provider::class,',
        'diff --git a/lama.txt b/lama.txt',
        '--- a/lama.txt',
        '+++ /dev/null',
        '@@ -1 +0,0 @@',
        '-isi lama yang memuat FiturContoh',
    ].join('\n');

    it('mengambil baris tambahan beserta nomor barisnya', () => {
        expect(bacaBarisDitambah(diff)).toEqual([
            { berkas: 'config/app.php', nomor: 171, isi: '            App\\Services\\FiturContoh\\Provider::class,' },
            { berkas: 'config/app.php', nomor: 172, isi: '            App\\Services\\Lain\\Provider::class,' },
        ]);
    });

    it('tidak memperlakukan header +++ sebagai baris tambahan', () => {
        // Header '+++ b/...' juga diawali '+'. Terbaca sebagai isi, setiap berkas
        // yang namanya memuat penanda akan menuduh dirinya sendiri.
        expect(bacaBarisDitambah(diff).some((b) => b.isi.startsWith('++'))).toBe(false);
    });

    it('mengabaikan berkas yang dihapus', () => {
        expect(bacaBarisDitambah(diff).some((b) => b.berkas === 'lama.txt')).toBe(false);
    });
});

describe('bacaBerkasBerubah', () => {
    it('membuang baris kosong', () => {
        expect(bacaBerkasBerubah('a.php\n\nb.php\n')).toEqual(['a.php', 'b.php']);
    });
});

describe('rakitLaporan', () => {
    it('menyebut alasan, branch yang diizinkan, dan cara melanjutkan', () => {
        const hasil = evaluasiGerbang({
            manifes,
            branchTujuan: 'master',
            berkasBerubah: ['app/Services/KPI/FiturContoh/Layanan.php'],
        });

        const laporan = rakitLaporan(hasil, 'master');

        expect(laporan).toContain('MENOLAK');
        expect(laporan).toContain('Masih dikembangkan.');
        expect(laporan).toContain('staging');
        expect(laporan).toContain('izinkan-demo');
        expect(laporan).toContain('.github/gerbang-rilis.json');
    });
});
