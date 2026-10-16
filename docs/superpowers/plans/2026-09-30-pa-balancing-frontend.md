# PA Balancing — Rencana Implementasi Bagian 2: Frontend

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Layar Balancing untuk admin Talent Analyst — daftar sesi, buat sesi, board rapat (matriks Before/After, kualitatif, simulasi), riwayat di detail peserta — di atas API Bagian 1, plus menu dan pelepasan Kalibrasi Forum di UI.

**Architecture:** Dua halaman Inertia baru di `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/` (daftar sesi dan board sesi), tiga komponen fitur, satu modul util murni. HTTP tetap satu pintu lewat `paApi.js`. Menu memakai butir `PaKalibrasiPage` yang sudah ada (tersembunyi) yang dialihkan menjadi "Balancing".

**Tech Stack:** Vue 3 `<script setup>`, Inertia, komponen `@/components/ui` (Hcis*), Vitest + @vue/test-utils, Laravel route/controller, SQL Server DML.

**Spec:** `docs/superpowers/specs/2026-09-30-pa-balancing-design.md` (§7 Layar). Kontrak API: `docs/superpowers/plans/2026-09-30-pa-balancing-backend.md` Task 11.

## Global Constraints

- Branch `feat/kpi-pa/balancing` di worktree dev2 `hcis-w-6` (HMR dev2 langsung menampilkan). Tidak merge/push tanpa permintaan pemilik proyek.
- Stage per path; tidak pernah `git add -A` / `git add .`.
- Semua HTTP lewat `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js` (fungsi bernama + entri di `export default`).
- Teks layar Bahasa Indonesia. HTML dari server dirender hanya lewat `htmlAman()` (`utils/htmlAman.js`).
- Responsif: tanpa scroll horizontal di HP; matriks menjadi kartu per kompetensi ≤ 720px; dialog penuh layar di HP (perilaku bawaan `HcisDialog`).
- Label simulasi persis: **"SIMULASI — belum tersimpan"**.
- Skala After 0–100; alasan wajib bila |After − Before| ≥ 0,005 (sama dengan backend).
- DML hanya ke `Web_HRIS @ instance-20240809-031237`, satu baris `N_HRIS_Menus` (UPDATE), dengan guard; lalu `InertiaShellService::flushMenuCache()`.
- Verifikasi frontend: `npx vitest run <berkas>`, `NODE_OPTIONS=--max-old-space-size=6144 npm run build`, `git diff --check`, `php scripts/verify-inertia-pages.php`.

## Review Focus

1. **Admin mengetik After lalu menekan Simulasikan tanpa menyimpan.** Simulasi harus memakai isian layar, bukan yang tersimpan. Diuji di Task 4 (`mengirim isian layar yang belum disimpan ke simulasi`).
2. **Simpan kena 409 (`PA_BAL_VERSI_BERUBAH`).** Isian tidak boleh hilang diam-diam; layar menampilkan pesan dan tombol muat ulang. Diuji di Task 4 (`409 menampilkan pesan muat ulang tanpa membuang isian`).
3. **After diubah tanpa alasan.** Tombol simpan/finalkan ditahan dan barisnya ditandai sebelum ke server. Diuji di Task 1 (`galatPeserta`) dan Task 3.
4. **Sesi FINAL/DIBATALKAN dibuka.** Semua input baca-saja, tombol aksi hilang. Diuji di Task 4 (`sesi FINAL baca-saja`).
5. **Kandidat tidak layak.** Tampil abu-abu dengan alasan dan tidak bisa dicentang. Diuji di Task 5 (`kandidat tidak layak tidak bisa dipilih`).

---

## Peta Berkas

| Berkas | Tanggung jawab |
|---|---|
| `…/PerformanceAppraisal/api/paApi.js` | +10 fungsi Balancing |
| `…/PerformanceAppraisal/Balancing/utils/balancing.js` | Aturan layar murni: perubahan, alasan wajib, payload, susunan kotak HAV |
| `…/Balancing/components/PaBalancingPesertaKartu.vue` | Satu peserta: ringkasan, matriks kompetensi, kualitatif |
| `…/Balancing/components/PaBalancingSimulasiPanel.vue` | Hasil simulasi: tabel, HAV sebelum/sesudah, terdampak |
| `…/Balancing/components/PaBalancingBuatSesiDialog.vue` | Buat sesi: nama, forum, kandidat bersaring |
| `…/Balancing/BalancingPage.vue` | Daftar sesi per siklus + banner + hitung ulang |
| `…/Balancing/BalancingSesiPage.vue` | Board sesi |
| `…/Cycle/components/PaResultDialog.vue` | Tab "Balancing"; tombol & dialog Kalibrasi Forum dilepas |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/PaBalancingController.php` | +2 metode halaman |
| `routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php` | +2 rute halaman |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php` | `/kalibrasi` dialihkan ke Balancing |
| `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKandidatService.php` | `kode_perusahaan` ikut dikirim (untuk memilih forum) |
| `database/dml/DML-2026-159-menu-pa-balancing/data-change.sql` | Butir menu |

---

### Task 1: API dan util murni

**Files:**
- Modify: `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js`
- Create: `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/utils/balancing.js`
- Test: `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/utils/balancing.test.js`
- Test: `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.balancing.test.js`

**Interfaces — Produces:**
- paApi: `daftarSesiBalancing(cycleId)`, `kandidatBalancing(cycleId, saringan)`, `buatSesiBalancing(cycleId, data)`, `detailSesiBalancing(sesiId)`, `simpanSesiBalancing(sesiId, data)`, `finalkanSesiBalancing(sesiId, versiData)`, `batalkanSesiBalancing(sesiId, alasan)`, `simulasiBalancing(sesiId, peserta)`, `hitungUlangBalancing(cycleId)`, `riwayatBalancing(participantId)` — semuanya mengembalikan `result` (lewat `panggil`), melempar `PaApiError(message, status, errorCode, requestId)`.
- balancing.js: `AMBANG`, `berubah(a,b)`, `butuhAlasan(k)`, `diLuarRentang(k)`, `adaPerubahan(p)`, `galatPeserta(p) → list<string>`, `payloadSimpan(peserta)`, `payloadSimulasi(peserta)`, `URUTAN_KOTAK`, `susunKotak(kuadran)`, `hitungKotak(baris, sisi)`, `pindahKotak(baris)`, `LABEL_SEBAB`, `LABEL_STATUS`, `VARIAN_STATUS`, `formatNilai(v)`, `salinPeserta(p)`.

- [ ] **Step 1: Tulis uji util**

`balancing.test.js`:

```js
import { describe, expect, it } from 'vitest';
import {
    adaPerubahan, berubah, butuhAlasan, diLuarRentang, formatNilai, galatPeserta, hitungKotak,
    payloadSimpan, payloadSimulasi, pindahKotak, susunKotak,
} from './balancing.js';

const peserta = (ubah = {}) => ({
    participant_id: 7,
    nama: 'Andi',
    status_tinjau: 'BELUM',
    alasan: null,
    kompetensi: [
        { competency_id: 1, nama: 'Integritas', sebelum: 70, sesudah: 70, alasan: null, ...ubah.k1 },
        { competency_id: 2, nama: 'Kerja sama', sebelum: 85, sesudah: 85, alasan: null },
    ],
    kualitatif: [{ competency_id: 21, nama: 'Strength', sebelum: null, sesudah: null, ...ubah.q }],
});

describe('balancing util', () => {
    it('perubahan memakai ambang 0,005 seperti backend', () => {
        expect(berubah(70, 70.004)).toBe(false);
        expect(berubah(70, 70.005)).toBe(true);
    });

    it('alasan wajib hanya bila nilainya berubah', () => {
        expect(butuhAlasan({ sebelum: 70, sesudah: 70, alasan: '' })).toBe(false);
        expect(butuhAlasan({ sebelum: 70, sesudah: 80, alasan: '  ' })).toBe(true);
        expect(butuhAlasan({ sebelum: 70, sesudah: 80, alasan: 'Konsisten' })).toBe(false);
    });

    it('rentang 0–100', () => {
        expect(diLuarRentang({ sesudah: 101 })).toBe(true);
        expect(diLuarRentang({ sesudah: -1 })).toBe(true);
        expect(diLuarRentang({ sesudah: '' })).toBe(true);
        expect(diLuarRentang({ sesudah: 100 })).toBe(false);
    });

    it('galatPeserta menyebut kompetensinya', () => {
        expect(galatPeserta(peserta({ k1: { sesudah: 80 } }))).toEqual(['Integritas: alasan wajib diisi karena nilainya diubah.']);
        expect(galatPeserta(peserta({ k1: { sesudah: 120, alasan: 'x' } }))).toEqual(['Integritas: nilai After harus 0 sampai 100.']);
        expect(galatPeserta(peserta())).toEqual([]);
    });

    it('adaPerubahan melihat kompetensi dan kualitatif', () => {
        expect(adaPerubahan(peserta())).toBe(false);
        expect(adaPerubahan(peserta({ k1: { sesudah: 71 } }))).toBe(true);
        expect(adaPerubahan(peserta({ q: { sesudah: '<p>Rapi</p>' } }))).toBe(true);
    });

    it('payloadSimpan dan payloadSimulasi mengikuti kontrak API', () => {
        const p = peserta({ k1: { sesudah: '80', alasan: 'Konsisten' } });
        p.status_tinjau = 'TANPA_PERUBAHAN';
        expect(payloadSimpan([p])).toEqual([{
            participant_id: 7, status_tinjau: 'TANPA_PERUBAHAN', alasan: null,
            kompetensi: [
                { competency_id: 1, nilai_sesudah: 80, alasan: 'Konsisten' },
                { competency_id: 2, nilai_sesudah: 85, alasan: null },
            ],
            kualitatif: [{ competency_id: 21, isi_sesudah: '' }],
        }]);
        expect(payloadSimulasi([p])).toEqual([{
            participant_id: 7,
            kompetensi: [{ competency_id: 1, nilai_sesudah: 80 }, { competency_id: 2, nilai_sesudah: 85 }],
        }]);
    });

    it('status DIUBAH tidak dikirim; server yang menurunkannya', () => {
        const p = peserta();
        p.status_tinjau = 'DIUBAH';
        expect(payloadSimpan([p])[0].status_tinjau).toBeNull();
    });

    it('kotak HAV disusun dalam urutan grid standar dan dihitung per sisi', () => {
        const kuadran = [1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => ({ Nomor_Box: n, Kode_Kuadran: `K${n}` }));
        expect(susunKotak(kuadran).map((k) => k.Nomor_Box)).toEqual([7, 3, 1, 8, 4, 2, 9, 6, 5]);
        const baris = [
            { nama: 'A', sebelum: { kode_kuadran: 'K4' }, sesudah: { kode_kuadran: 'K1' } },
            { nama: 'B', sebelum: { kode_kuadran: 'K4' }, sesudah: { kode_kuadran: 'K4' } },
        ];
        expect(hitungKotak(baris, 'sebelum')).toEqual({ K4: 2 });
        expect(hitungKotak(baris, 'sesudah')).toEqual({ K1: 1, K4: 1 });
        expect(pindahKotak(baris)).toEqual([{ nama: 'A', dari: 'K4', ke: 'K1' }]);
    });

    it('formatNilai dua desimal gaya Indonesia', () => {
        expect(formatNilai(82.5)).toBe('82,50');
        expect(formatNilai(null)).toBe('–');
    });
});
```

- [ ] **Step 2: Tulis uji API**

`paApi.balancing.test.js`:

```js
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import paApi from './paApi.js';

vi.mock('axios');

describe('paApi Balancing', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.mockResolvedValue({ data: { result: { ok: true } } });
    });

    const dipanggil = () => axios.mock.calls[0][0];

    it.each([
        ['daftarSesiBalancing', [5], 'get', '/api/v1/performance-appraisal/cycles/5/balancing/sesi', undefined],
        ['buatSesiBalancing', [5, { nama: 'S' }], 'post', '/api/v1/performance-appraisal/cycles/5/balancing/sesi', { nama: 'S' }],
        ['detailSesiBalancing', [9], 'get', '/api/v1/performance-appraisal/balancing/sesi/9', undefined],
        ['simpanSesiBalancing', [9, { versi_data: 2 }], 'put', '/api/v1/performance-appraisal/balancing/sesi/9', { versi_data: 2 }],
        ['finalkanSesiBalancing', [9, 3], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/final', { versi_data: 3 }],
        ['batalkanSesiBalancing', [9, 'Ditunda'], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/batal', { alasan: 'Ditunda' }],
        ['simulasiBalancing', [9, []], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/simulasi', { peserta: [] }],
        ['hitungUlangBalancing', [5], 'post', '/api/v1/performance-appraisal/cycles/5/balancing/hitung-ulang', undefined],
        ['riwayatBalancing', [77], 'get', '/api/v1/performance-appraisal/participants/77/balancing', undefined],
    ])('%s', async (nama, argumen, metode, url, data) => {
        await expect(paApi[nama](...argumen)).resolves.toEqual({ ok: true });
        expect(dipanggil()).toEqual({ method: metode, url, data });
    });

    it('kandidat mengirim saringan larik sebagai query', async () => {
        await paApi.kandidatBalancing(5, { level_ids: [1, 2], cari: 'an', grade: [], jumlah_sesi: null });
        expect(dipanggil().url).toBe('/api/v1/performance-appraisal/cycles/5/balancing/kandidat?level_ids%5B%5D=1&level_ids%5B%5D=2&cari=an');
    });

    it('galat membawa error_code', async () => {
        axios.mockRejectedValue({ response: { status: 409, data: { message: 'Data sesi sudah diubah', error_code: 'PA_BAL_VERSI_BERUBAH', request_id: 'r1' } } });
        await expect(paApi.simpanSesiBalancing(9, {})).rejects.toMatchObject({ status: 409, errorCode: 'PA_BAL_VERSI_BERUBAH' });
    });
});
```

Periksa nama properti `PaApiError` (baris ~59 `paApi.js`): bila bukan `status`/`errorCode`, sesuaikan harapan uji dengan nama aslinya.

- [ ] **Step 3: Jalankan, pastikan gagal** — `npx vitest run resources/js/Pages/KPI/PerformanceAppraisal/Balancing/utils/balancing.test.js resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.balancing.test.js` → FAIL (modul/fungsi belum ada).

- [ ] **Step 4: Tulis `balancing.js`**

```js
/**
 * Aturan layar Balancing — murni, tanpa HTTP. Ambang dan rentang SAMA dengan backend
 * (PaBalancingKalkulator::berubah, PaBalancingSesiService): layar menahan lebih dulu supaya
 * admin tidak menunggu penolakan server untuk kesalahan yang sudah terlihat.
 */

export const AMBANG = 0.005;

export function berubah(a, b) {
    return Math.abs(Number(a) - Number(b)) >= AMBANG;
}

export function diLuarRentang(k) {
    if (k.sesudah === '' || k.sesudah === null || k.sesudah === undefined) return true;
    const n = Number(k.sesudah);
    return !Number.isFinite(n) || n < 0 || n > 100;
}

export function butuhAlasan(k) {
    return berubah(k.sebelum, k.sesudah) && !String(k.alasan ?? '').trim();
}

export function adaPerubahan(p) {
    return (p.kompetensi || []).some((k) => berubah(k.sebelum, k.sesudah))
        || (p.kualitatif || []).some((q) => (q.sebelum ?? '') !== (q.sesudah ?? ''));
}

export function galatPeserta(p) {
    const galat = [];
    for (const k of p.kompetensi || []) {
        if (diLuarRentang(k)) galat.push(`${k.nama}: nilai After harus 0 sampai 100.`);
        else if (butuhAlasan(k)) galat.push(`${k.nama}: alasan wajib diisi karena nilainya diubah.`);
    }
    return galat;
}

export function payloadSimpan(peserta) {
    return peserta.map((p) => ({
        participant_id: p.participant_id,
        // DIUBAH diturunkan server dari isian; yang dikirim hanya pilihan admin.
        status_tinjau: ['BELUM', 'TANPA_PERUBAHAN'].includes(p.status_tinjau) ? p.status_tinjau : null,
        alasan: p.alasan || null,
        kompetensi: (p.kompetensi || []).map((k) => ({
            competency_id: k.competency_id, nilai_sesudah: Number(k.sesudah), alasan: String(k.alasan ?? '').trim() || null,
        })),
        kualitatif: (p.kualitatif || []).map((q) => ({ competency_id: q.competency_id, isi_sesudah: q.sesudah ?? '' })),
    }));
}

export function payloadSimulasi(peserta) {
    return peserta.map((p) => ({
        participant_id: p.participant_id,
        kompetensi: (p.kompetensi || []).map((k) => ({ competency_id: k.competency_id, nilai_sesudah: Number(k.sesudah) })),
    }));
}

/** Urutan grid 3×3 yang sama dengan halaman HAV Map (baris atas = kompetensi tinggi). */
export const URUTAN_KOTAK = [7, 3, 1, 8, 4, 2, 9, 6, 5];

export function susunKotak(kuadran) {
    const peta = new Map((kuadran || []).map((k) => [Number(k.Nomor_Box), k]));
    return URUTAN_KOTAK.map((n) => peta.get(n)).filter(Boolean);
}

export function hitungKotak(baris, sisi) {
    return (baris || []).reduce((acc, b) => {
        const kode = b?.[sisi]?.kode_kuadran;
        if (kode) acc[kode] = (acc[kode] || 0) + 1;
        return acc;
    }, {});
}

export function pindahKotak(baris) {
    return (baris || [])
        .filter((b) => b.sebelum?.kode_kuadran !== b.sesudah?.kode_kuadran)
        .map((b) => ({ nama: b.nama, dari: b.sebelum?.kode_kuadran ?? null, ke: b.sesudah?.kode_kuadran ?? null }));
}

export const LABEL_SEBAB = { BALANCING: 'Penyesuaian', KURVA: 'Kurva', KEDUANYA: 'Penyesuaian + kurva', TANPA_PERUBAHAN: 'Tanpa perubahan' };
export const LABEL_STATUS = { TERBUKA: 'Terbuka', DRAFT: 'Draft', FINAL: 'Final', DIBATALKAN: 'Dibatalkan' };
export const VARIAN_STATUS = { TERBUKA: 'info', DRAFT: 'warning', FINAL: 'success', DIBATALKAN: 'neutral' };
export const LABEL_TINJAU = { BELUM: 'Belum ditinjau', TANPA_PERUBAHAN: 'Tanpa perubahan', DIUBAH: 'Diubah' };

export function formatNilai(v) {
    if (v === null || v === undefined || v === '') return '–';
    return Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Salinan dalam peserta dari detail() — board menyunting salinan, bukan respons server. */
export function salinPeserta(p) {
    return {
        ...p,
        kompetensi: (p.kompetensi || []).map((k) => ({ ...k })),
        kualitatif: (p.kualitatif || []).map((q) => ({ ...q })),
    };
}
```

- [ ] **Step 5: Tambah fungsi di `paApi.js`** (sebelum `export default`, lalu daftarkan semuanya di objek default)

```js
/* ------------------------------------------------------------------ *
 * Balancing (spec 2026-09-30)                                         *
 * ------------------------------------------------------------------ */

function queryBalancing(saringan = {}) {
    const q = new URLSearchParams();
    for (const [kunci, nilai] of Object.entries(saringan || {})) {
        if (Array.isArray(nilai)) nilai.forEach((v) => q.append(`${kunci}[]`, v));
        else if (nilai !== null && nilai !== undefined && nilai !== '') q.append(kunci, nilai);
    }
    const s = q.toString();
    return s ? `?${s}` : '';
}

/** Sesi Balancing satu siklus: `{ sesi[], menunggu_hitung_ulang, terbuka }`. */
export function daftarSesiBalancing(cycleId) {
    return panggil('get', `/cycles/${cycleId}/balancing/sesi`);
}

/** Kandidat peserta; yang tidak layak TETAP dikirim beserta `alasan`. */
export function kandidatBalancing(cycleId, saringan = {}) {
    return panggil('get', `/cycles/${cycleId}/balancing/kandidat${queryBalancing(saringan)}`);
}

export function buatSesiBalancing(cycleId, data) {
    return panggil('post', `/cycles/${cycleId}/balancing/sesi`, data);
}

export function detailSesiBalancing(sesiId) {
    return panggil('get', `/balancing/sesi/${sesiId}`);
}

/** `data = { versi_data, peserta[], catatan_rapat? }`; 409 `PA_BAL_VERSI_BERUBAH` bila basi. */
export function simpanSesiBalancing(sesiId, data) {
    return panggil('put', `/balancing/sesi/${sesiId}`, data);
}

export function finalkanSesiBalancing(sesiId, versiData) {
    return panggil('post', `/balancing/sesi/${sesiId}/final`, { versi_data: versiData });
}

export function batalkanSesiBalancing(sesiId, alasan) {
    return panggil('post', `/balancing/sesi/${sesiId}/batal`, { alasan });
}

/** Simulasi tanpa menyimpan; `peserta` = isian layar yang belum disimpan. */
export function simulasiBalancing(sesiId, peserta) {
    return panggil('post', `/balancing/sesi/${sesiId}/simulasi`, { peserta });
}

export function hitungUlangBalancing(cycleId) {
    return panggil('post', `/cycles/${cycleId}/balancing/hitung-ulang`);
}

export function riwayatBalancing(participantId) {
    return panggil('get', `/participants/${participantId}/balancing`);
}
```

Tambahkan kesepuluh nama itu ke objek `export default { … }` di akhir berkas.

- [ ] **Step 6: Jalankan uji, pastikan lulus** — perintah Step 3 → PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.balancing.test.js \
  resources/js/Pages/KPI/PerformanceAppraisal/Balancing/utils/balancing.js resources/js/Pages/KPI/PerformanceAppraisal/Balancing/utils/balancing.test.js
git commit -m "feat(kpi-pa): API dan aturan layar Balancing"
```

---

### Task 2: Rute halaman, kandidat membawa kode perusahaan

**Files:**
- Modify: `app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/PaBalancingController.php` (+`halamanDaftar`, `halamanSesi`)
- Modify: `routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php`
- Modify: `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php` (`halamanKalibrasi`)
- Modify: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKandidatService.php` (+`kode_perusahaan`)
- Create (placeholder minimal agar gerbang halaman hijau; diisi Task 3–5): `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/BalancingPage.vue`, `BalancingSesiPage.vue`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingHalamanTest.php`

**Interfaces — Produces:** `GET /performance-appraisal/balancing` (nama `performance-appraisal.balancing`, Inertia `KPI/PerformanceAppraisal/Balancing/BalancingPage`), `GET /performance-appraisal/balancing/sesi/{sesiId}` (nama `performance-appraisal.balancing.sesi`, Inertia `…/BalancingSesiPage`, prop `sesiId:int`), keduanya `check.jabatan:PaHasilPage`; `/performance-appraisal/kalibrasi` → redirect ke `performance-appraisal.balancing` (query `cycle` diteruskan). Kandidat: tambah kunci `kode_perusahaan`.

- [ ] **Step 1: Uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingHalamanTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_halaman_daftar_dan_board(): void
    {
        $this->actingAs($this->penggunaHasil());

        $this->get('/performance-appraisal/balancing')->assertOk()
            ->assertInertia(fn ($p) => $p->component('KPI/PerformanceAppraisal/Balancing/BalancingPage'));
        $this->get('/performance-appraisal/balancing/sesi/12')->assertOk()
            ->assertInertia(fn ($p) => $p->component('KPI/PerformanceAppraisal/Balancing/BalancingSesiPage')->where('sesiId', 12));
    }

    public function test_alamat_kalibrasi_lama_dialihkan_ke_balancing(): void
    {
        $this->actingAs($this->penggunaHasil());

        $this->get('/performance-appraisal/kalibrasi?cycle=8781')->assertRedirect('/performance-appraisal/balancing?cycle=8781');
    }

    public function test_kandidat_membawa_kode_perusahaan(): void
    {
        $p = $this->pesertaDihitung();
        $k = app(\App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService::class)->daftar($p['cycle_id']);

        $this->assertNotEmpty($k[0]['kode_perusahaan']);
    }
}
```

Bila `assertInertia` tidak tersedia di versi ini, pakai `->assertSee('BalancingPage', false)` pada `data-page`.

- [ ] **Step 2: Jalankan, pastikan gagal** (404/redirect lama, kunci hilang).

- [ ] **Step 3: Implementasi**

Controller Balancing — tambahkan:

```php
    public function halamanDaftar()
    {
        return inertia('KPI/PerformanceAppraisal/Balancing/BalancingPage');
    }

    public function halamanSesi(int $sesiId)
    {
        return inertia('KPI/PerformanceAppraisal/Balancing/BalancingSesiPage', ['sesiId' => $sesiId]);
    }
```

Berkas rute — tambahkan di atas grup API:

```php
Route::middleware(['auth', 'check.jabatan:PaHasilPage'])->group(function () {
    Route::get('/performance-appraisal/balancing', [PaBalancingController::class, 'halamanDaftar'])
        ->name('performance-appraisal.balancing');
    Route::get('/performance-appraisal/balancing/sesi/{sesiId}', [PaBalancingController::class, 'halamanSesi'])
        ->whereNumber('sesiId')->name('performance-appraisal.balancing.sesi');
});
```

`PaCycleAdminController::halamanKalibrasi` menjadi:

```php
    /** Kalibrasi Forum digantikan Balancing (B7); alamat lama tetap hidup sebagai pengalih. */
    public function halamanKalibrasi(Request $request)
    {
        $cycleId = $request->query('cycle') ?? $request->query('cycle_id');

        return redirect()->route('performance-appraisal.balancing', $cycleId ? ['cycle' => $cycleId] : []);
    }
```

Kandidat: di `kueri()` tambah `'p.Kode_Perusahaan'` ke select dan di `daftar()` tambah `'kode_perusahaan' => (string) $r->Kode_Perusahaan,`.

Halaman placeholder (diganti Task 3–5):

```vue
<template><main /></template>
<script setup>
defineOptions({ name: 'PerformanceAppraisalBalancingPage' });
</script>
```

(`BalancingSesiPage.vue` sama dengan nama `PerformanceAppraisalBalancingSesiPage` dan `defineProps({ sesiId: { type: Number, required: true } })`.)

- [ ] **Step 4: Jalankan uji + gerbang halaman** — `php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingHalamanTest.php` PASS; `php scripts/verify-inertia-pages.php` tanpa temuan.

- [ ] **Step 5: Commit** (berkas di atas per path) — `feat(kpi-pa): rute halaman Balancing, kalibrasi dialihkan`.

---

### Task 3: Kartu peserta (matriks + kualitatif)

**Files:**
- Create: `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/components/PaBalancingPesertaKartu.vue`
- Test: `…/Balancing/components/PaBalancingPesertaKartu.test.js`

**Interfaces:**
- Props: `peserta` (objek dari `salinPeserta`, disunting di tempat — reaktif milik halaman), `bisaUbah: Boolean`, `terbuka: Boolean`.
- Emits: `toggle`, `ubah` (tanpa argumen; memberi tahu halaman ada isian berubah).
- Memakai `galatPeserta`, `butuhAlasan`, `diLuarRentang`, `berubah`, `formatNilai`, `LABEL_TINJAU`, `htmlAman`.

- [ ] **Step 1: Uji yang gagal**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Kartu from './PaBalancingPesertaKartu.vue';

const stubs = {
    HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisIcon: { template: '<i />' },
    HcisRichText: { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<textarea class="rt" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
};

const peserta = () => ({
    participant_id: 7, nama: 'Andi', kode_karyawan: 'K7', level: 'Staff', status_tinjau: 'BELUM', alasan: null,
    sebelum: { nilai_kpi: 90, nilai_kompetensi: 77.5, final: 83.75, grade: 'B', kuadran_id: 4 },
    kompetensi: [
        { competency_id: 1, nama: 'Integritas', deskripsi: 'Jujur', bobot: 0.5, sebelum: 70, sesudah: 70, alasan: null },
        { competency_id: 2, nama: 'Kerja sama', deskripsi: '', bobot: 0.5, sebelum: 85, sesudah: 85, alasan: null },
    ],
    kualitatif: [{ competency_id: 21, nama: 'Strength', sebelum: '<p>Teliti</p>', sesudah: '<p>Teliti</p>' }],
});

describe('PaBalancingPesertaKartu', () => {
    it('menampilkan bobot sebagai persen dan Before', () => {
        const w = mount(Kartu, { props: { peserta: peserta(), bisaUbah: true, terbuka: true }, global: { stubs } });
        expect(w.text()).toContain('Integritas');
        expect(w.text()).toContain('50%');
        expect(w.text()).toContain('70,00');
    });

    it('mengubah After menandai alasan wajib dan memancarkan ubah', async () => {
        const p = peserta();
        const w = mount(Kartu, { props: { peserta: p, bisaUbah: true, terbuka: true }, global: { stubs } });
        await w.find('[data-uji="after-1"]').setValue('80');
        expect(p.kompetensi[0].sesudah).toBe(80);
        expect(w.find('[data-uji="alasan-1"]').classes()).toContain('is-wajib');
        expect(w.text()).toContain('alasan wajib diisi');
        expect(w.emitted('ubah')).toBeTruthy();
    });

    it('tanpa perubahan bisa ditandai "Tanpa perubahan"', async () => {
        const p = peserta();
        const w = mount(Kartu, { props: { peserta: p, bisaUbah: true, terbuka: true }, global: { stubs } });
        await w.find('[data-uji="tanpa-perubahan"]').setValue(true);
        expect(p.status_tinjau).toBe('TANPA_PERUBAHAN');
    });

    it('baca-saja tanpa input', () => {
        const w = mount(Kartu, { props: { peserta: peserta(), bisaUbah: false, terbuka: true }, global: { stubs } });
        expect(w.find('input[type="number"]').exists()).toBe(false);
        expect(w.find('.rt').exists()).toBe(false);
    });
});
```

- [ ] **Step 2: Jalankan → FAIL** (komponen belum ada).

- [ ] **Step 3: Tulis komponen**

```vue
<template>
    <article class="pa-bal-kartu" :class="{ 'is-terbuka': terbuka, 'is-galat': galat.length }">
        <button type="button" class="pa-bal-kartu__kepala" :aria-expanded="terbuka" @click="$emit('toggle')">
            <span class="pa-bal-kartu__nama">
                <strong>{{ peserta.nama }}</strong>
                <small>{{ peserta.kode_karyawan }} · {{ peserta.level || '–' }}</small>
            </span>
            <span class="pa-bal-kartu__angka">
                <span>Final <b>{{ formatNilai(peserta.sebelum?.final) }}</b></span>
                <span>Komp <b>{{ formatNilai(peserta.sebelum?.nilai_kompetensi) }}</b></span>
                <span>KPI <b>{{ formatNilai(peserta.sebelum?.nilai_kpi) }}</b></span>
                <span>Grade <b>{{ peserta.sebelum?.grade || '–' }}</b></span>
            </span>
            <HcisBadge :label="LABEL_TINJAU[statusTampil] || statusTampil" :variant="statusTampil === 'BELUM' ? 'warning' : 'success'" appearance="subtle" size="xs" />
            <HcisIcon :name="terbuka ? 'chevron-up' : 'chevron-down'" size="xs" />
        </button>

        <div v-if="terbuka" class="pa-bal-kartu__isi">
            <table class="pa-bal-matriks">
                <thead>
                    <tr>
                        <th scope="col">Kompetensi</th>
                        <th scope="col">Bobot</th>
                        <th scope="col">Before</th>
                        <th scope="col">After</th>
                        <th scope="col">Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="k in peserta.kompetensi" :key="k.competency_id" :class="{ 'is-berubah': berubah(k.sebelum, k.sesudah) }">
                        <td data-label="Kompetensi">
                            <strong>{{ k.nama }}</strong>
                            <small v-if="k.deskripsi">{{ k.deskripsi }}</small>
                        </td>
                        <td data-label="Bobot">{{ persen(k.bobot) }}</td>
                        <td data-label="Before">{{ formatNilai(k.sebelum) }}</td>
                        <td data-label="After">
                            <input
                                v-if="bisaUbah"
                                type="number" min="0" max="100" step="0.01"
                                class="pa-bal-input" :class="{ 'is-galat': diLuarRentang(k) }"
                                :aria-label="`After ${k.nama}`" :data-uji="`after-${k.competency_id}`"
                                :value="k.sesudah"
                                @input="ubahNilai(k, $event.target.value)"
                            >
                            <span v-else>{{ formatNilai(k.sesudah) }}</span>
                        </td>
                        <td data-label="Alasan">
                            <input
                                v-if="bisaUbah"
                                type="text" maxlength="1000"
                                class="pa-bal-input pa-bal-input--teks" :class="{ 'is-wajib': butuhAlasan(k) }"
                                :aria-label="`Alasan ${k.nama}`" :data-uji="`alasan-${k.competency_id}`"
                                :placeholder="berubah(k.sebelum, k.sesudah) ? 'Wajib diisi' : 'Opsional'"
                                :value="k.alasan || ''"
                                @input="ubahAlasan(k, $event.target.value)"
                            >
                            <span v-else>{{ k.alasan || '–' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <ul v-if="galat.length" class="pa-bal-kartu__galat" role="alert">
                <li v-for="g in galat" :key="g">{{ g }}</li>
            </ul>

            <section class="pa-bal-kualitatif" aria-label="Kualitatif">
                <div v-for="q in peserta.kualitatif" :key="q.competency_id" class="pa-bal-kualitatif__item">
                    <h4>{{ q.nama }}</h4>
                    <div class="pa-bal-kualitatif__sebelum">
                        <small>Before</small>
                        <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                        <div v-if="q.sebelum" v-html="htmlAman(q.sebelum)" />
                        <em v-else>Belum ada ringkasan admin.</em>
                    </div>
                    <HcisRichText
                        v-if="bisaUbah"
                        :id="`pa-bal-q-${peserta.participant_id}-${q.competency_id}`"
                        :label="`After — ${q.nama}`"
                        :model-value="q.sesudah || ''"
                        :toolbar="TOOLBAR"
                        min-height="80px" max-height="220px"
                        @update:model-value="(v) => ubahKualitatif(q, v)"
                    />
                    <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                    <div v-else-if="q.sesudah" class="pa-bal-kualitatif__sesudah" v-html="htmlAman(q.sesudah)" />
                </div>
            </section>

            <label v-if="bisaUbah && !adaPerubahan(peserta)" class="pa-bal-kartu__tanpa">
                <input type="checkbox" data-uji="tanpa-perubahan" :checked="peserta.status_tinjau === 'TANPA_PERUBAHAN'" @change="tandaiTanpaPerubahan($event.target.checked)">
                Sudah dibahas, tanpa perubahan
            </label>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisIcon, HcisRichText } from '@/components/ui';
import { htmlAman } from '../../utils/htmlAman.js';
import { LABEL_TINJAU, adaPerubahan, berubah, butuhAlasan, diLuarRentang, formatNilai, galatPeserta } from '../utils/balancing.js';

defineOptions({ name: 'PaBalancingPesertaKartu' });

const props = defineProps({
    peserta: { type: Object, required: true },
    bisaUbah: { type: Boolean, default: false },
    terbuka: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle', 'ubah']);

const TOOLBAR = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']];

const galat = computed(() => galatPeserta(props.peserta));
const statusTampil = computed(() => (adaPerubahan(props.peserta) ? 'DIUBAH' : props.peserta.status_tinjau === 'DIUBAH' ? 'BELUM' : props.peserta.status_tinjau));

function persen(b) {
    return `${Math.round(Number(b || 0) * 1000) / 10}%`.replace('.', ',');
}

function ubahNilai(k, v) {
    k.sesudah = v === '' ? '' : Number(v);
    emit('ubah');
}

function ubahAlasan(k, v) {
    k.alasan = v;
    emit('ubah');
}

function ubahKualitatif(q, v) {
    q.sesudah = v;
    emit('ubah');
}

function tandaiTanpaPerubahan(ya) {
    props.peserta.status_tinjau = ya ? 'TANPA_PERUBAHAN' : 'BELUM';
    emit('ubah');
}
</script>

<style scoped>
.pa-bal-kartu { border: 1px solid var(--hcis-border, #e5e7eb); border-radius: 14px; background: var(--hcis-surface, #fff); min-width: 0; }
.pa-bal-kartu.is-galat { border-color: #f59e0b; }
.pa-bal-kartu__kepala { width: 100%; display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: none; border: 0; text-align: left; cursor: pointer; min-width: 0; }
.pa-bal-kartu__nama { display: flex; flex-direction: column; min-width: 0; flex: 1 1 180px; }
.pa-bal-kartu__nama strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pa-bal-kartu__nama small { color: #64748b; }
.pa-bal-kartu__angka { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: #475569; }
.pa-bal-kartu__isi { padding: 0 14px 14px; }
.pa-bal-matriks { width: 100%; border-collapse: collapse; font-size: 13px; }
.pa-bal-matriks th, .pa-bal-matriks td { padding: 8px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.pa-bal-matriks td small { display: block; color: #64748b; }
.pa-bal-matriks tr.is-berubah td { background: #fffbeb; }
.pa-bal-input { width: 90px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 8px; }
.pa-bal-input--teks { width: 100%; min-width: 140px; }
.pa-bal-input.is-galat, .pa-bal-input.is-wajib { border-color: #dc2626; }
.pa-bal-kartu__galat { margin: 8px 0; padding-left: 18px; color: #b91c1c; font-size: 12px; }
.pa-bal-kualitatif { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 12px; }
.pa-bal-kualitatif h4 { margin: 0 0 6px; font-size: 13px; }
.pa-bal-kualitatif__sebelum { font-size: 12px; color: #475569; margin-bottom: 6px; }
.pa-bal-kartu__tanpa { display: flex; gap: 8px; align-items: center; margin-top: 12px; font-size: 13px; }
@media (max-width: 720px) {
    .pa-bal-kartu__kepala { flex-wrap: wrap; }
    .pa-bal-matriks thead { display: none; }
    .pa-bal-matriks, .pa-bal-matriks tbody, .pa-bal-matriks tr, .pa-bal-matriks td { display: block; width: 100%; }
    .pa-bal-matriks tr { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; padding: 4px 0; }
    .pa-bal-matriks td { border: 0; display: flex; gap: 8px; justify-content: space-between; }
    .pa-bal-matriks td::before { content: attr(data-label); font-weight: 600; color: #64748b; flex: 0 0 72px; }
    .pa-bal-input--teks { min-width: 0; }
}
</style>
```

- [ ] **Step 4: Jalankan → PASS.**
- [ ] **Step 5: Commit** — `feat(kpi-pa): kartu peserta Balancing`.

---

### Task 4: Panel simulasi dan halaman board

**Files:**
- Create: `…/Balancing/components/PaBalancingSimulasiPanel.vue` (+ `.test.js`)
- Replace: `…/Balancing/BalancingSesiPage.vue` (+ `BalancingSesiPage.test.js`)

**Interfaces:**
- Panel props: `hasil` (respons simulasi: `metode, peserta[], terdampak[]`), `kuadran` (daftar dari `paApi.havMap(cycleId).kuadran`), `memuat: Boolean`. Tanpa emit.
- Page props: `sesiId:Number`. Memakai `paApi.{detailSesiBalancing, simpanSesiBalancing, finalkanSesiBalancing, batalkanSesiBalancing, simulasiBalancing, havMap}`.

- [ ] **Step 1: Uji panel**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Panel from './PaBalancingSimulasiPanel.vue';

const stubs = { HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' }, HcisSkeleton: { template: '<div class="sk" />' }, HcisIcon: { template: '<i />' } };
const baris = (nama, gradeLama, gradeBaru, sebab, kLama = 'K4', kBaru = 'K4') => ({
    participant_id: nama.length, nama,
    sebelum: { final: 80, grade: gradeLama, kode_kuadran: kLama, nilai_kompetensi: 77.5 },
    sesudah: { final: 85, grade: gradeBaru, kode_kuadran: kBaru, nilai_kompetensi: 82.5 }, sebab,
});

describe('PaBalancingSimulasiPanel', () => {
    it('berlabel simulasi, menampilkan perubahan, kotak, dan yang terdampak', () => {
        const w = mount(Panel, {
            props: {
                hasil: { metode: 'KURVA', peserta: [baris('Andi', 'B', 'B_PLUS', 'BALANCING', 'K4', 'K1')], terdampak: [baris('Budi', 'B', 'C_PLUS', 'KURVA')] },
                kuadran: [1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => ({ Nomor_Box: n, Kode_Kuadran: `K${n}`, Nama_Kuadran: `Kotak ${n}` })),
                memuat: false,
            },
            global: { stubs },
        });
        expect(w.text()).toContain('SIMULASI — belum tersimpan');
        expect(w.text()).toContain('Andi');
        expect(w.text()).toContain('Penyesuaian');
        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('Kotak 4 → Kotak 1');
        expect(w.findAll('.pa-bal-hav__sel')).toHaveLength(18);
    });
});
```

- [ ] **Step 2: Uji halaman board**

```js
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Page from './BalancingSesiPage.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        detailSesiBalancing: vi.fn(), simpanSesiBalancing: vi.fn(), finalkanSesiBalancing: vi.fn(),
        batalkanSesiBalancing: vi.fn(), simulasiBalancing: vi.fn(), havMap: vi.fn(),
    },
}));
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() }, Link: { template: '<a><slot /></a>' } }));

const stubs = {
    HcisHeader: { props: ['title'], template: '<header><h1>{{ title }}</h1><slot name="badge" /><slot name="actions" /></header>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert">{{ title }} {{ description }}<slot /></div>' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result">{{ title }} {{ description }}</div>' },
    HcisSkeleton: { template: '<div />' },
    HcisIcon: { template: '<i />' },
    HcisRichText: { props: ['modelValue'], template: '<div class="rt" />' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisTextarea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    PaBalancingSimulasiPanel: { props: ['hasil'], template: '<div class="panel-sim">{{ hasil ? "ada" : "" }}</div>' },
};

const detail = (status = 'DRAFT') => ({
    id: 3, cycle_id: 5, nama: 'Sesi Staff', status, versi_data: 4, catatan_rapat: null, pic_nama: 'Ridho', forum: [],
    peserta: [{
        participant_id: 7, nama: 'Andi', kode_karyawan: 'K7', level: 'Staff', status_tinjau: 'BELUM', alasan: null,
        sebelum: { final: 83.75, nilai_kompetensi: 77.5, nilai_kpi: 90, grade: 'B' },
        kompetensi: [{ competency_id: 1, nama: 'Integritas', bobot: 0.5, sebelum: 70, sesudah: 70, alasan: null }],
        kualitatif: [],
    }],
});

async function pasang(status = 'DRAFT') {
    paApi.detailSesiBalancing.mockResolvedValue(detail(status));
    paApi.havMap.mockResolvedValue({ kuadran: [] });
    const w = mount(Page, { props: { sesiId: 3 }, global: { stubs } });
    await flushPromises();
    return w;
}

const tombol = (w, teks) => w.findAll('button').find((b) => b.text().includes(teks));

describe('BalancingSesiPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('mengirim isian layar yang belum disimpan ke simulasi', async () => {
        paApi.simulasiBalancing.mockResolvedValue({ metode: 'PITA', peserta: [], terdampak: [] });
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        await tombol(w, 'Simulasikan').trigger('click');
        await flushPromises();
        expect(paApi.simulasiBalancing).toHaveBeenCalledWith(3, [{ participant_id: 7, kompetensi: [{ competency_id: 1, nilai_sesudah: 90 }] }]);
        expect(w.find('.panel-sim').text()).toBe('ada');
    });

    it('simpan ditahan bila ada alasan kosong', async () => {
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        expect(tombol(w, 'Simpan draft').attributes('disabled')).toBeDefined();
    });

    it('409 menampilkan pesan muat ulang tanpa membuang isian', async () => {
        paApi.simpanSesiBalancing.mockRejectedValue(Object.assign(new Error('Data sesi sudah diubah orang lain.'), { status: 409, errorCode: 'PA_BAL_VERSI_BERUBAH' }));
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        await w.find('[data-uji="alasan-1"]').setValue('Konsisten');
        await tombol(w, 'Simpan draft').trigger('click');
        await flushPromises();
        expect(w.text()).toContain('Data sesi sudah diubah orang lain.');
        expect(tombol(w, 'Muat ulang')).toBeTruthy();
        expect(w.find('[data-uji="after-1"]').element.value).toBe('90');
    });

    it('sesi FINAL baca-saja', async () => {
        const w = await pasang('FINAL');
        await tombol(w, 'Andi').trigger('click');
        expect(w.find('[data-uji="after-1"]').exists()).toBe(false);
        expect(tombol(w, 'Simpan draft')).toBeUndefined();
        expect(tombol(w, 'Finalkan')).toBeUndefined();
    });
});
```

- [ ] **Step 3: Jalankan → FAIL.**

- [ ] **Step 4: Tulis panel**

```vue
<template>
    <section class="pa-bal-sim" aria-live="polite">
        <div class="pa-bal-sim__pita">SIMULASI — belum tersimpan</div>
        <HcisSkeleton v-if="memuat" :rows="4" />
        <template v-else-if="hasil">
            <p class="pa-bal-sim__catatan">
                {{ hasil.metode === 'KURVA' ? 'Kurva grup dihitung ulang di memori; grade orang di luar sesi bisa ikut bergeser.' : 'Grade memakai pita nilai tetap (PITA).' }}
            </p>
            <div class="pa-bal-sim__tabel-bungkus">
                <table class="pa-bal-sim__tabel">
                    <thead><tr><th>Nama</th><th>Final</th><th>Grade</th><th>HAV</th><th>Sebab</th></tr></thead>
                    <tbody>
                        <tr v-for="b in hasil.peserta" :key="b.participant_id">
                            <td>{{ b.nama }}</td>
                            <td>{{ formatNilai(b.sebelum.final) }} → <b>{{ formatNilai(b.sesudah.final) }}</b></td>
                            <td>{{ b.sebelum.grade || '–' }} → <b>{{ b.sesudah.grade || '–' }}</b></td>
                            <td>{{ namaKotak(b.sebelum.kode_kuadran) }} → <b>{{ namaKotak(b.sesudah.kode_kuadran) }}</b></td>
                            <td><HcisBadge v-if="b.sebab" :label="LABEL_SEBAB[b.sebab] || b.sebab" size="xs" appearance="subtle" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="kotak.length" class="pa-bal-hav">
                <div v-for="sisi in ['sebelum', 'sesudah']" :key="sisi" class="pa-bal-hav__peta">
                    <h4>HAV {{ sisi === 'sebelum' ? 'sebelum' : 'sesudah' }}</h4>
                    <div class="pa-bal-hav__grid">
                        <div v-for="k in kotak" :key="`${sisi}-${k.Nomor_Box}`" class="pa-bal-hav__sel">
                            <small>{{ k.Nomor_Box }}</small>
                            <b>{{ (sisi === 'sebelum' ? cacahSebelum : cacahSesudah)[k.Kode_Kuadran] || 0 }}</b>
                        </div>
                    </div>
                </div>
            </div>
            <ul v-if="pindah.length" class="pa-bal-sim__pindah">
                <li v-for="p in pindah" :key="p.nama">{{ p.nama }}: {{ namaKotak(p.dari) }} → {{ namaKotak(p.ke) }}</li>
            </ul>

            <div v-if="hasil.terdampak?.length" class="pa-bal-sim__terdampak">
                <h4>Di luar sesi, ikut bergeser ({{ hasil.terdampak.length }})</h4>
                <ul>
                    <li v-for="b in hasil.terdampak" :key="b.participant_id">
                        {{ b.nama }} — {{ b.sebelum.grade || '–' }} → <b>{{ b.sesudah.grade || '–' }}</b>
                        <HcisBadge :label="LABEL_SEBAB[b.sebab] || b.sebab" size="xs" appearance="subtle" />
                    </li>
                </ul>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisSkeleton } from '@/components/ui';
import { LABEL_SEBAB, formatNilai, hitungKotak, pindahKotak, susunKotak } from '../utils/balancing.js';

defineOptions({ name: 'PaBalancingSimulasiPanel' });

const props = defineProps({
    hasil: { type: Object, default: null },
    kuadran: { type: Array, default: () => [] },
    memuat: { type: Boolean, default: false },
});

const kotak = computed(() => susunKotak(props.kuadran));
const semua = computed(() => [...(props.hasil?.peserta || []), ...(props.hasil?.terdampak || [])]);
const cacahSebelum = computed(() => hitungKotak(props.hasil?.peserta || [], 'sebelum'));
const cacahSesudah = computed(() => hitungKotak(props.hasil?.peserta || [], 'sesudah'));
const pindah = computed(() => pindahKotak(semua.value));

function namaKotak(kode) {
    if (!kode) return '–';
    return props.kuadran.find((k) => k.Kode_Kuadran === kode)?.Nama_Kuadran || kode;
}
</script>

<style scoped>
.pa-bal-sim { border: 2px dashed #6366f1; border-radius: 14px; padding: 12px; background: #eef2ff; min-width: 0; }
.pa-bal-sim__pita { font-weight: 800; letter-spacing: .04em; color: #4338ca; margin-bottom: 8px; }
.pa-bal-sim__catatan { font-size: 12px; color: #475569; margin: 0 0 8px; }
.pa-bal-sim__tabel-bungkus { overflow-x: auto; }
.pa-bal-sim__tabel { width: 100%; border-collapse: collapse; font-size: 12px; }
.pa-bal-sim__tabel th, .pa-bal-sim__tabel td { padding: 6px; border-bottom: 1px solid #c7d2fe; text-align: left; white-space: nowrap; }
.pa-bal-hav { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 12px; }
.pa-bal-hav h4 { margin: 0 0 6px; font-size: 12px; }
.pa-bal-hav__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.pa-bal-hav__sel { background: #fff; border-radius: 8px; padding: 6px; text-align: center; }
.pa-bal-hav__sel small { display: block; color: #64748b; font-size: 10px; }
.pa-bal-sim__pindah, .pa-bal-sim__terdampak ul { font-size: 12px; padding-left: 18px; }
.pa-bal-sim__terdampak h4 { font-size: 12px; margin: 12px 0 4px; }
</style>
```

- [ ] **Step 5: Tulis `BalancingSesiPage.vue`**

```vue
<template>
    <main class="pa-bal-board">
        <HcisHeader
            :title="sesi ? sesi.nama : 'Sesi Balancing'"
            kicker="PERFORMANCE APPRAISAL · BALANCING"
            :description="sesi ? `PIC ${sesi.pic_nama || '–'} · ${sesi.peserta.length} peserta` : ''"
            icon="sliders" sentiment="brand" variant="glass" size="md"
        >
            <template #badge>
                <HcisBadge v-if="sesi" :label="LABEL_STATUS[sesi.status] || sesi.status" :variant="VARIAN_STATUS[sesi.status]" appearance="glass" size="sm" dot />
            </template>
            <template #actions>
                <div class="pa-bal-board__aksi">
                    <HcisButton label="Kembali" variant="secondary" size="sm" leading-icon="arrow-left" @click="kembali" />
                    <template v-if="bisaUbah">
                        <HcisButton label="Simulasikan" variant="secondary" size="sm" leading-icon="magic" :loading="menyimulasikan" :disabled="adaGalat" @click="simulasikan" />
                        <HcisButton label="Simpan draft" variant="primary" size="sm" leading-icon="save" :loading="menyimpan" :disabled="adaGalat || !kotor" @click="simpan" />
                        <HcisButton label="Finalkan" variant="success" size="sm" leading-icon="check2-circle" :disabled="adaGalat || kotor || belumDitinjau > 0" @click="dialogFinal = true" />
                        <HcisButton label="Batalkan sesi" variant="danger" size="sm" leading-icon="x-circle" @click="dialogBatal = true" />
                    </template>
                </div>
            </template>
        </HcisHeader>

        <HcisSkeleton v-if="memuat" :rows="6" />
        <HcisResultState v-else-if="galatMuat" status="error" title="Sesi tidak bisa dimuat" :description="galatMuat" />

        <template v-else-if="sesi">
            <HcisAlert v-if="galatAksi" variant="danger" :title="galatAksi">
                <HcisButton v-if="versiBasi" label="Muat ulang" size="sm" variant="secondary" @click="muat" />
            </HcisAlert>
            <p v-if="bisaUbah && belumDitinjau > 0" class="pa-bal-board__info">
                {{ belumDitinjau }} peserta belum ditinjau. Finalkan aktif setelah semua ditinjau dan draft tersimpan.
            </p>

            <section class="pa-bal-board__forum">
                <b>Forum:</b>
                <span v-if="!sesi.forum.length">–</span>
                <span v-for="f in sesi.forum" :key="f.kode_karyawan" class="pa-bal-board__chip">{{ f.nama }} · {{ f.peran }}</span>
            </section>

            <div class="pa-bal-board__tata" :class="{ 'ada-simulasi': simulasi || menyimulasikan }">
                <div class="pa-bal-board__daftar">
                    <PaBalancingPesertaKartu
                        v-for="p in peserta" :key="p.participant_id"
                        :peserta="p" :bisa-ubah="bisaUbah" :terbuka="terbuka === p.participant_id"
                        @toggle="terbuka = terbuka === p.participant_id ? null : p.participant_id"
                        @ubah="kotor = true"
                    />
                    <section class="pa-bal-board__catatan">
                        <h3>Catatan rapat</h3>
                        <HcisRichText v-if="bisaUbah" id="pa-bal-catatan" label="Catatan rapat" :model-value="catatan" @update:model-value="(v) => { catatan = v; kotor = true; }" />
                        <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                        <div v-else-if="sesi.catatan_rapat" v-html="htmlAman(sesi.catatan_rapat)" />
                        <em v-else>Tidak ada catatan.</em>
                    </section>
                </div>
                <PaBalancingSimulasiPanel v-if="simulasi || menyimulasikan" :hasil="simulasi" :kuadran="kuadran" :memuat="menyimulasikan" class="pa-bal-board__sim" />
            </div>
        </template>

        <HcisDialog v-model="dialogFinal" title="Finalkan sesi?" size="sm" sentiment="success">
            <p>Isian sesi dikunci dan tidak bisa diubah lagi. Koreksi sesudahnya lewat sesi baru. Hasil baru berlaku sesudah hitung ulang Balancing.</p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogFinal = false" />
                <HcisButton label="Ya, finalkan" variant="success" size="sm" :loading="menyimpan" @click="finalkan" />
            </template>
        </HcisDialog>

        <HcisDialog v-model="dialogBatal" title="Batalkan sesi?" size="sm" sentiment="danger">
            <HcisTextarea id="pa-bal-alasan-batal" v-model="alasanBatal" label="Alasan membatalkan" :rows="3" :maxlength="500" />
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="dialogBatal = false" />
                <HcisButton label="Batalkan sesi" variant="danger" size="sm" :disabled="alasanBatal.trim().length < 5" :loading="menyimpan" @click="batalkan" />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisHeader, HcisResultState, HcisRichText, HcisSkeleton, HcisTextarea } from '@/components/ui';
import paApi from '../api/paApi.js';
import { htmlAman } from '../utils/htmlAman.js';
import PaBalancingPesertaKartu from './components/PaBalancingPesertaKartu.vue';
import PaBalancingSimulasiPanel from './components/PaBalancingSimulasiPanel.vue';
import { LABEL_STATUS, VARIAN_STATUS, adaPerubahan, galatPeserta, payloadSimpan, payloadSimulasi, salinPeserta } from './utils/balancing.js';

defineOptions({ name: 'PerformanceAppraisalBalancingSesiPage' });

const props = defineProps({ sesiId: { type: Number, required: true } });

const sesi = ref(null);
const peserta = ref([]);
const catatan = ref('');
const kuadran = ref([]);
const memuat = ref(true);
const galatMuat = ref('');
const galatAksi = ref('');
const versiBasi = ref(false);
const menyimpan = ref(false);
const menyimulasikan = ref(false);
const simulasi = ref(null);
const terbuka = ref(null);
const kotor = ref(false);
const dialogFinal = ref(false);
const dialogBatal = ref(false);
const alasanBatal = ref('');

const bisaUbah = computed(() => ['TERBUKA', 'DRAFT'].includes(sesi.value?.status));
const adaGalat = computed(() => peserta.value.some((p) => galatPeserta(p).length > 0));
const belumDitinjau = computed(() => peserta.value.filter((p) => !adaPerubahan(p) && p.status_tinjau !== 'TANPA_PERUBAHAN').length);

function terapkan(d) {
    sesi.value = d;
    peserta.value = (d.peserta || []).map(salinPeserta);
    catatan.value = d.catatan_rapat || '';
    kotor.value = false;
}

async function muat() {
    memuat.value = true;
    galatMuat.value = '';
    galatAksi.value = '';
    versiBasi.value = false;
    try {
        terapkan(await paApi.detailSesiBalancing(props.sesiId));
        const hav = await paApi.havMap(sesi.value.cycle_id).catch(() => null);
        kuadran.value = hav?.kuadran || [];
    } catch (e) {
        galatMuat.value = e?.message || 'Gagal memuat sesi.';
    } finally {
        memuat.value = false;
    }
}

function tanganiGalat(e) {
    galatAksi.value = e?.message || 'Permintaan ditolak.';
    versiBasi.value = e?.errorCode === 'PA_BAL_VERSI_BERUBAH';
}

async function simpan() {
    menyimpan.value = true;
    galatAksi.value = '';
    try {
        terapkan(await paApi.simpanSesiBalancing(props.sesiId, {
            versi_data: sesi.value.versi_data,
            peserta: payloadSimpan(peserta.value),
            catatan_rapat: catatan.value || null,
        }));
    } catch (e) {
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

async function simulasikan() {
    menyimulasikan.value = true;
    galatAksi.value = '';
    try {
        simulasi.value = await paApi.simulasiBalancing(props.sesiId, payloadSimulasi(peserta.value));
    } catch (e) {
        tanganiGalat(e);
    } finally {
        menyimulasikan.value = false;
    }
}

async function finalkan() {
    menyimpan.value = true;
    try {
        terapkan(await paApi.finalkanSesiBalancing(props.sesiId, sesi.value.versi_data));
        dialogFinal.value = false;
    } catch (e) {
        dialogFinal.value = false;
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

async function batalkan() {
    menyimpan.value = true;
    try {
        terapkan(await paApi.batalkanSesiBalancing(props.sesiId, alasanBatal.value.trim()));
        dialogBatal.value = false;
    } catch (e) {
        dialogBatal.value = false;
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

function kembali() {
    router.visit(`/performance-appraisal/balancing${sesi.value ? `?cycle=${sesi.value.cycle_id}` : ''}`);
}

onMounted(muat);
</script>

<style scoped>
.pa-bal-board { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.pa-bal-board__aksi { display: flex; flex-wrap: wrap; gap: 8px; }
.pa-bal-board__info { font-size: 13px; color: #92400e; margin: 0; }
.pa-bal-board__forum { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: 13px; }
.pa-bal-board__chip { background: #f1f5f9; border-radius: 999px; padding: 2px 10px; }
.pa-bal-board__tata { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
.pa-bal-board__tata.ada-simulasi { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
.pa-bal-board__daftar { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.pa-bal-board__sim { position: sticky; top: 12px; align-self: start; }
.pa-bal-board__catatan h3 { font-size: 14px; margin: 8px 0; }
@media (max-width: 1024px) {
    .pa-bal-board__tata.ada-simulasi { grid-template-columns: minmax(0, 1fr); }
    .pa-bal-board__sim { position: static; order: -1; }
}
</style>
```

- [ ] **Step 6: Jalankan kedua uji → PASS** (sesuaikan stub bila komponen UI memerlukan prop lain; jangan melemahkan harapan).
- [ ] **Step 7: Commit** — `feat(kpi-pa): board sesi Balancing dengan simulasi`.

---

### Task 5: Halaman daftar sesi dan dialog buat sesi

**Files:**
- Create: `…/Balancing/components/PaBalancingBuatSesiDialog.vue` (+ `.test.js`)
- Replace: `…/Balancing/BalancingPage.vue` (+ `BalancingPage.test.js`)

**Interfaces:**
- Dialog props: `modelValue:Boolean`, `cycleId:[Number,String]`. Emits: `update:modelValue`, `dibuat(detail)`. Memakai `paApi.kandidatBalancing`, `paApi.buatSesiBalancing`, `usePage().props.auth.user.id` sebagai PIC bawaan.
- Page: memakai `paApi.daftarSiklus`, `paApi.daftarSesiBalancing`, `paApi.hitungUlangBalancing`, `cycleDariUrl`.

- [ ] **Step 1: Uji dialog**

```js
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dialog from './PaBalancingBuatSesiDialog.vue';
import paApi from '../../api/paApi.js';

vi.mock('../../api/paApi.js', () => ({ default: { kandidatBalancing: vi.fn(), buatSesiBalancing: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { id: 622 } } } }) }));

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title'], template: '<div class="alert">{{ title }}</div>' },
    HcisSkeleton: { template: '<div />' },
};

const kandidat = [
    { participant_id: 1, nama: 'Andi', kode_karyawan: 'K1', kode_perusahaan: '001', level: 'Staff', divisi: 'Ops', grade: 'B', layak: true, alasan: null, jumlah_sesi: 0 },
    { participant_id: 2, nama: 'Budi', kode_karyawan: 'K2', kode_perusahaan: '001', level: 'Staff', divisi: 'Ops', grade: 'A', layak: false, alasan: 'Hasil sudah terbit ke karyawan', jumlah_sesi: 0 },
];

describe('PaBalancingBuatSesiDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.kandidatBalancing.mockResolvedValue(kandidat);
    });

    it('kandidat tidak layak tidak bisa dipilih dan alasannya tampil', async () => {
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5 }, global: { stubs } });
        await flushPromises();
        expect(w.find('[data-uji="pilih-2"]').attributes('disabled')).toBeDefined();
        expect(w.text()).toContain('Hasil sudah terbit ke karyawan');
    });

    it('membuat sesi dengan PIC pengguna, forum, dan peserta terpilih', async () => {
        paApi.buatSesiBalancing.mockResolvedValue({ id: 9 });
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5 }, global: { stubs } });
        await flushPromises();
        await w.find('[data-uji="nama-sesi"]').setValue('Balancing Staff');
        await w.find('[data-uji="pilih-1"]').setValue(true);
        await w.find('[data-uji="forum-1"]').setValue(true);
        await w.find('[data-uji="peran-1"]').setValue('Dept Head');
        await w.findAll('button').find((b) => b.text() === 'Buat sesi').trigger('click');
        await flushPromises();
        expect(paApi.buatSesiBalancing).toHaveBeenCalledWith(5, {
            nama: 'Balancing Staff', pic_user_id: 622,
            forum: [{ kode_perusahaan: '001', kode_karyawan: 'K1', peran: 'Dept Head' }],
            peserta_ids: [1],
        });
        expect(w.emitted('dibuat')[0][0]).toEqual({ id: 9 });
    });

    it('saringan diteruskan ke API', async () => {
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5 }, global: { stubs } });
        await flushPromises();
        await w.find('[data-uji="cari"]').setValue('an');
        await w.find('[data-uji="terapkan-saring"]').trigger('click');
        expect(paApi.kandidatBalancing).toHaveBeenLastCalledWith(5, expect.objectContaining({ cari: 'an' }));
    });
});
```

- [ ] **Step 2: Uji halaman daftar**

```js
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Page from './BalancingPage.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({ default: { daftarSiklus: vi.fn(), daftarSesiBalancing: vi.fn(), hitungUlangBalancing: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

const stubs = {
    HcisHeader: { props: ['title'], template: '<header><h1>{{ title }}</h1><slot name="actions" /></header>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert">{{ title }} {{ description }}<slot /></div>' },
    HcisEmptyState: { props: ['title'], template: '<div class="empty">{{ title }}</div>' },
    HcisResultState: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}</div>' },
    HcisSkeleton: { template: '<div />' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    PaBalancingBuatSesiDialog: { template: '<div />' },
};

describe('BalancingPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({ siklus: [{ Id_PA_Cycle: 5, Kode_Cycle: 'PA-1', Nama_Cycle: 'PA 1', Status_Cycle: 'CLOSED' }] });
        paApi.daftarSesiBalancing.mockResolvedValue({
            sesi: [{ id: 3, nama: 'Sesi Staff', status: 'FINAL', pic_nama: 'Ridho', jumlah_peserta: 4, jumlah_forum: 2, created_at: '2026-09-30', hitung_ulang_at: null }],
            menunggu_hitung_ulang: 1, terbuka: 0,
        });
    });

    it('menampilkan sesi dan banner menunggu hitung ulang', async () => {
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        expect(paApi.daftarSesiBalancing).toHaveBeenCalledWith(5);
        expect(w.text()).toContain('Sesi Staff');
        expect(w.text()).toContain('1 sesi FINAL belum dihitung ulang');
    });

    it('hitung ulang lewat konfirmasi lalu memuat ulang daftar', async () => {
        paApi.hitungUlangBalancing.mockResolvedValue({ nomor: 1, dihitung: 4, berubah: 3, kalibrasi_dilepas: 0 });
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        await w.findAll('button').find((b) => b.text() === 'Hitung ulang').trigger('click');
        await w.findAll('button').find((b) => b.text() === 'Ya, hitung ulang').trigger('click');
        await flushPromises();
        expect(paApi.hitungUlangBalancing).toHaveBeenCalledWith(5);
        expect(paApi.daftarSesiBalancing).toHaveBeenCalledTimes(2);
        expect(w.text()).toContain('Hitung ulang #1 selesai');
    });
});
```

- [ ] **Step 3: Jalankan → FAIL.**

- [ ] **Step 4: Tulis dialog**

```vue
<template>
    <HcisDialog :model-value="modelValue" title="Buat sesi Balancing" kicker="BALANCING" size="xl" sentiment="brand" @update:model-value="(v) => $emit('update:modelValue', v)">
        <div class="pa-bal-buat">
            <label class="pa-bal-buat__baris">
                <span>Nama sesi</span>
                <input v-model="nama" data-uji="nama-sesi" type="text" maxlength="200" placeholder="mis. Balancing Staff–SPV">
            </label>

            <fieldset class="pa-bal-buat__saring">
                <legend>Saring kandidat</legend>
                <input v-model="saring.cari" data-uji="cari" type="search" placeholder="Nama atau kode karyawan">
                <input v-model="saring.grade" type="text" placeholder="Grade, pisah koma (mis. A,B)">
                <input v-model.number="saring.jumlah_sesi" type="number" min="0" placeholder="Sudah lewat n sesi">
                <HcisButton label="Terapkan" size="sm" variant="secondary" data-uji="terapkan-saring" @click="muatKandidat" />
            </fieldset>

            <HcisSkeleton v-if="memuat" :rows="5" />
            <div v-else class="pa-bal-buat__tabel">
                <table>
                    <thead><tr><th>Peserta</th><th>Forum</th><th>Nama</th><th>Level · Divisi</th><th>Grade</th><th>Sesi</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr v-for="k in kandidat" :key="k.participant_id" :class="{ 'is-mati': !k.layak }">
                            <td><input type="checkbox" :data-uji="`pilih-${k.participant_id}`" :disabled="!k.layak" :checked="dipilih.has(k.participant_id)" :aria-label="`Pilih ${k.nama}`" @change="alih(dipilih, k.participant_id, $event.target.checked)"></td>
                            <td><input type="checkbox" :data-uji="`forum-${k.participant_id}`" :checked="forum.has(k.participant_id)" :aria-label="`${k.nama} sebagai forum`" @change="alihForum(k, $event.target.checked)"></td>
                            <td>{{ k.nama }} <small>{{ k.kode_karyawan }}</small></td>
                            <td>{{ k.level || '–' }} · {{ k.divisi || '–' }}</td>
                            <td>{{ k.grade || '–' }}</td>
                            <td>{{ k.jumlah_sesi }}</td>
                            <td>
                                <span v-if="!k.layak" class="pa-bal-buat__alasan">{{ k.alasan }}</span>
                                <input v-if="forum.has(k.participant_id)" v-model="forum.get(k.participant_id).peran" :data-uji="`peran-${k.participant_id}`" type="text" maxlength="100" placeholder="Peran di forum">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <HcisAlert v-if="galat" variant="danger" :title="galat" />
        </div>
        <template #footer>
            <HcisButton label="Batal" variant="secondary" size="sm" @click="$emit('update:modelValue', false)" />
            <HcisButton label="Buat sesi" variant="primary" size="sm" :loading="menyimpan" :disabled="!bisaBuat" @click="buat" />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { HcisAlert, HcisButton, HcisDialog, HcisSkeleton } from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaBalancingBuatSesiDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycleId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue', 'dibuat']);

const halaman = usePage();
const nama = ref('');
const kandidat = ref([]);
const dipilih = reactive(new Set());
const forum = reactive(new Map());
const saring = reactive({ cari: '', grade: '', jumlah_sesi: null });
const memuat = ref(false);
const menyimpan = ref(false);
const galat = ref('');

const bisaBuat = computed(() => nama.value.trim() !== '' && dipilih.size > 0
    && [...forum.values()].every((f) => f.peran.trim() !== ''));

function alih(himpunan, id, ya) {
    if (ya) himpunan.add(id);
    else himpunan.delete(id);
}

function alihForum(k, ya) {
    if (ya) forum.set(k.participant_id, { kode_perusahaan: k.kode_perusahaan, kode_karyawan: k.kode_karyawan, peran: '' });
    else forum.delete(k.participant_id);
}

async function muatKandidat() {
    if (!props.cycleId) return;
    memuat.value = true;
    galat.value = '';
    try {
        const grade = saring.grade.split(',').map((g) => g.trim().toUpperCase()).filter(Boolean);
        kandidat.value = await paApi.kandidatBalancing(Number(props.cycleId), {
            cari: saring.cari, grade, jumlah_sesi: saring.jumlah_sesi === '' ? null : saring.jumlah_sesi,
        });
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat kandidat.';
    } finally {
        memuat.value = false;
    }
}

async function buat() {
    menyimpan.value = true;
    galat.value = '';
    try {
        const hasil = await paApi.buatSesiBalancing(Number(props.cycleId), {
            nama: nama.value.trim(),
            pic_user_id: halaman.props.auth?.user?.id,
            forum: [...forum.values()].map((f) => ({ ...f, peran: f.peran.trim() })),
            peserta_ids: [...dipilih],
        });
        emit('dibuat', hasil);
    } catch (e) {
        galat.value = e?.message || 'Sesi tidak bisa dibuat.';
    } finally {
        menyimpan.value = false;
    }
}

watch(() => [props.modelValue, props.cycleId], ([buka]) => {
    if (buka) muatKandidat();
}, { immediate: true });
</script>

<style scoped>
.pa-bal-buat { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.pa-bal-buat__baris { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
.pa-bal-buat input[type="text"], .pa-bal-buat input[type="search"], .pa-bal-buat input[type="number"] { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 8px; min-width: 0; }
.pa-bal-buat__saring { display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; }
.pa-bal-buat__tabel { overflow-x: auto; max-height: 50vh; }
.pa-bal-buat__tabel table { width: 100%; border-collapse: collapse; font-size: 12px; }
.pa-bal-buat__tabel th, .pa-bal-buat__tabel td { padding: 6px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.pa-bal-buat__tabel td small { color: #64748b; }
.pa-bal-buat__tabel tr.is-mati { color: #94a3b8; }
.pa-bal-buat__alasan { display: block; font-size: 11px; }
</style>
```

- [ ] **Step 5: Tulis `BalancingPage.vue`**

```vue
<template>
    <main class="pa-bal-daftar">
        <HcisHeader title="Balancing" kicker="PERFORMANCE APPRAISAL · BALANCING" description="Sesi forum penyeimbangan skor kompetensi sesudah Calculate. Setiap sesi FINAL dihitung ulang sebelum publish." icon="sliders" sentiment="brand" variant="glass" size="md">
            <template #actions>
                <div class="pa-bal-daftar__aksi">
                    <label class="sr-only" for="pa-bal-siklus">Siklus</label>
                    <select id="pa-bal-siklus" v-model="cycleId" class="pa-bal-daftar__siklus" @change="muatSesi">
                        <option v-for="c in siklus" :key="c.Id_PA_Cycle" :value="String(c.Id_PA_Cycle)">{{ c.Nama_Cycle || c.Kode_Cycle }} ({{ c.Status_Cycle }})</option>
                    </select>
                    <HcisButton label="Buat sesi" variant="primary" size="sm" leading-icon="plus-lg" :disabled="!cycleId" @click="dialogBuat = true" />
                </div>
            </template>
        </HcisHeader>

        <HcisAlert v-if="pesanHasil" variant="success" :title="pesanHasil" />
        <HcisAlert v-if="galat" variant="danger" :title="galat" />
        <HcisAlert v-if="ringkas.menunggu_hitung_ulang > 0" variant="warning" :title="`${ringkas.menunggu_hitung_ulang} sesi FINAL belum dihitung ulang`" description="Publish ditahan sampai hitung ulang dijalankan.">
            <HcisButton label="Hitung ulang" variant="warning" size="sm" :disabled="ringkas.terbuka > 0" @click="dialogHitung = true" />
            <small v-if="ringkas.terbuka > 0">Finalkan atau batalkan {{ ringkas.terbuka }} sesi terbuka lebih dulu.</small>
        </HcisAlert>

        <HcisSkeleton v-if="memuat" :rows="5" />
        <HcisEmptyState v-else-if="!sesi.length" title="Belum ada sesi Balancing di siklus ini" />
        <ul v-else class="pa-bal-daftar__kartu">
            <li v-for="s in sesi" :key="s.id">
                <button type="button" class="pa-bal-daftar__sesi" @click="buka(s.id)">
                    <span class="pa-bal-daftar__judul"><strong>{{ s.nama }}</strong><small>PIC {{ s.pic_nama || '–' }} · {{ tanggal(s.created_at) }}</small></span>
                    <span class="pa-bal-daftar__meta">{{ s.jumlah_peserta }} peserta · {{ s.jumlah_forum }} forum</span>
                    <HcisBadge :label="LABEL_STATUS[s.status] || s.status" :variant="VARIAN_STATUS[s.status]" size="xs" appearance="subtle" dot />
                    <HcisBadge v-if="s.status === 'FINAL'" :label="s.hitung_ulang_at ? `Dihitung ulang #${s.nomor_hitung_ulang}` : 'Menunggu hitung ulang'" :variant="s.hitung_ulang_at ? 'success' : 'warning'" size="xs" appearance="subtle" />
                </button>
            </li>
        </ul>

        <PaBalancingBuatSesiDialog v-model="dialogBuat" :cycle-id="cycleId" @dibuat="(d) => buka(d.id)" />

        <HcisDialog v-model="dialogHitung" title="Hitung ulang Balancing?" size="sm" sentiment="warning">
            <p>Skor kompetensi dari sesi FINAL diterapkan, kurva grade dihitung ulang dan dikunci lagi. Grade orang di luar sesi bisa ikut bergeser; semuanya tercatat di jejak.</p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogHitung = false" />
                <HcisButton label="Ya, hitung ulang" variant="warning" size="sm" :loading="menghitung" @click="hitungUlang" />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisEmptyState, HcisHeader, HcisSkeleton } from '@/components/ui';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';
import PaBalancingBuatSesiDialog from './components/PaBalancingBuatSesiDialog.vue';
import { LABEL_STATUS, VARIAN_STATUS } from './utils/balancing.js';

defineOptions({ name: 'PerformanceAppraisalBalancingPage' });

const siklus = ref([]);
const cycleId = ref('');
const sesi = ref([]);
const ringkas = ref({ menunggu_hitung_ulang: 0, terbuka: 0 });
const memuat = ref(false);
const galat = ref('');
const pesanHasil = ref('');
const dialogBuat = ref(false);
const dialogHitung = ref(false);
const menghitung = ref(false);

async function muatSiklus() {
    try {
        const r = await paApi.daftarSiklus();
        siklus.value = r?.siklus || [];
        const pilihan = cycleDariUrl(siklus.value)
            || String((siklus.value.find((c) => c.Status_Cycle === 'CLOSED') || siklus.value[0])?.Id_PA_Cycle || '');
        cycleId.value = pilihan;
        await muatSesi();
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat siklus.';
    }
}

async function muatSesi() {
    if (!cycleId.value) return;
    memuat.value = true;
    galat.value = '';
    try {
        const r = await paApi.daftarSesiBalancing(Number(cycleId.value));
        sesi.value = r?.sesi || [];
        ringkas.value = { menunggu_hitung_ulang: r?.menunggu_hitung_ulang || 0, terbuka: r?.terbuka || 0 };
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat sesi.';
    } finally {
        memuat.value = false;
    }
}

async function hitungUlang() {
    menghitung.value = true;
    galat.value = '';
    try {
        const r = await paApi.hitungUlangBalancing(Number(cycleId.value));
        pesanHasil.value = `Hitung ulang #${r.nomor} selesai: ${r.dihitung} peserta dihitung, ${r.berubah} baris jejak berubah.`;
        await muatSesi();
    } catch (e) {
        galat.value = e?.message || 'Hitung ulang ditolak.';
    } finally {
        menghitung.value = false;
        dialogHitung.value = false;
    }
}

function buka(id) {
    router.visit(`/performance-appraisal/balancing/sesi/${id}`);
}

function tanggal(v) {
    return v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–';
}

onMounted(muatSiklus);
</script>

<style scoped>
.pa-bal-daftar { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.pa-bal-daftar__aksi { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.pa-bal-daftar__siklus { padding: 6px 8px; border-radius: 8px; border: 1px solid #cbd5e1; max-width: 100%; }
.pa-bal-daftar__kartu { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
.pa-bal-daftar__sesi { width: 100%; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; text-align: left; cursor: pointer; }
.pa-bal-daftar__judul { display: flex; flex-direction: column; flex: 1 1 200px; min-width: 0; }
.pa-bal-daftar__judul small, .pa-bal-daftar__meta { color: #64748b; font-size: 12px; }
</style>
```

- [ ] **Step 6: Jalankan → PASS.** Jika `HcisAlert` tidak menerima slot bawaan, pindahkan tombol hitung ulang ke bawah alert (tetap satu blok).
- [ ] **Step 7: Commit** — `feat(kpi-pa): daftar sesi dan dialog buat sesi Balancing`.

---

### Task 6: Detail peserta — tab Balancing, Kalibrasi Forum dilepas

**Files:**
- Modify: `resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaResultDialog.vue`
- Modify: `…/Cycle/components/PaResultDialog.test.js`

**Interfaces:** memakai `paApi.riwayatBalancing(participantId)`; tab `balancing` di nav; tombol "Kalibrasi" dan subdialog "Kalibrasi Forum" dihapus bersama state/fungsi yang hanya dipakai keduanya.

- [ ] **Step 1: Uji (ubah berkas uji yang ada)**
  - Hapus/ubah tiga uji yang membuka dialog kalibrasi (baris ~254–300 dan ~390) menjadi satu uji: `tidak ada tombol Kalibrasi Forum lagi (B7)` → `expect(buttons.find(b => b.text() === 'Kalibrasi')).toBeUndefined()`.
  - Tambah ke `paApiMock`: `riwayatBalancing: vi.fn()`; uji baru:

```js
    it('tab Balancing memuat riwayat sesi dan jejak peserta', async () => {
        paApiMock.riwayatBalancing.mockResolvedValue({
            sesi: [{ id: 3, nama: 'Sesi Staff', status: 'FINAL', status_tinjau: 'DIUBAH', final_at: '2026-09-30', kompetensi: [{ nama: 'Integritas', sebelum: 70, sesudah: 80, alasan: 'Konsisten' }] }],
            jejak: [{ Nomor_Hitung_Ulang: 1, Final_Score_Sebelum: 83.75, Final_Score_Sesudah: 86.25, Kode_Grade_Sebelum: 'B', Kode_Grade_Sesudah: 'B_PLUS', Sebab: 'BALANCING' }],
        });
        const wrapper = pasangDialog(); // helper pemasang yang sudah dipakai uji lain di berkas ini
        await wrapper.findAll('button').find((b) => b.text().includes('Balancing')).trigger('click');
        await flushPromises();
        expect(paApiMock.riwayatBalancing).toHaveBeenCalled();
        expect(wrapper.text()).toContain('Sesi Staff');
        expect(wrapper.text()).toContain('Integritas');
        expect(wrapper.text()).toContain('B_PLUS');
    });
```

(Gunakan cara memasang yang dipakai uji lain di berkas itu; nama `pasangDialog` hanya contoh — samakan dengan helper/props yang ada.)

- [ ] **Step 2: Jalankan → FAIL.**

- [ ] **Step 3: Implementasi**
  - Nav: tambah tombol sebelum "Semua Bagian":

```vue
                    <button
                        type="button"
                        class="pa-result-nav__item"
                        :class="{ 'pa-result-nav__item--active': activeTab === 'balancing' }"
                        @click="bukaTabBalancing"
                    >
                        <HcisIcon name="sliders" size="2xs" />
                        <span>Balancing</span>
                    </button>
```

  - Panel (sesudah panel `catatan`, sebelum footer):

```vue
                <div v-show="activeTab === 'balancing'" class="pa-section-pane">
                    <HcisSkeleton v-if="balancing.memuat" :rows="3" />
                    <p v-else-if="balancing.galat" class="pa-bal-riwayat__galat">{{ balancing.galat }}</p>
                    <p v-else-if="!balancing.sesi.length" class="pa-bal-riwayat__kosong">Belum pernah masuk sesi Balancing.</p>
                    <template v-else>
                        <article v-for="s in balancing.sesi" :key="s.id" class="pa-bal-riwayat__sesi">
                            <h4>{{ s.nama }} <small>· {{ s.status }}{{ s.hitung_ulang_at ? ' · dihitung ulang' : '' }}</small></h4>
                            <ul>
                                <li v-for="k in s.kompetensi.filter((x) => Math.abs(Number(x.sesudah) - Number(x.sebelum)) >= 0.005)" :key="k.nama">
                                    {{ k.nama }}: {{ k.sebelum }} → <b>{{ k.sesudah }}</b><span v-if="k.alasan"> — {{ k.alasan }}</span>
                                </li>
                            </ul>
                        </article>
                        <h4 v-if="balancing.jejak.length">Jejak hitung ulang</h4>
                        <ul class="pa-bal-riwayat__jejak">
                            <li v-for="j in balancing.jejak" :key="`${j.Nomor_Hitung_Ulang}`">
                                #{{ j.Nomor_Hitung_Ulang }}: Final {{ j.Final_Score_Sebelum }} → <b>{{ j.Final_Score_Sesudah }}</b>,
                                grade {{ j.Kode_Grade_Sebelum || '–' }} → <b>{{ j.Kode_Grade_Sesudah || '–' }}</b> ({{ j.Sebab }})
                            </li>
                        </ul>
                    </template>
                </div>
```

  - Script:

```js
const balancing = reactive({ dimuat: false, memuat: false, galat: '', sesi: [], jejak: [] });

async function bukaTabBalancing() {
    activeTab.value = 'balancing';
    const pid = props.participant?.Id_PA_Participant ?? props.participant?.id;
    if (balancing.dimuat || !pid) return;
    balancing.memuat = true;
    balancing.galat = '';
    try {
        const r = await paApi.riwayatBalancing(pid);
        balancing.sesi = r?.sesi || [];
        balancing.jejak = r?.jejak || [];
        balancing.dimuat = true;
    } catch (e) {
        balancing.galat = e?.status === 403 ? 'Riwayat Balancing hanya untuk pemegang fase Hasil.' : (e?.message || 'Gagal memuat riwayat Balancing.');
    } finally {
        balancing.memuat = false;
    }
}
```

  Reset `balancing` (dimuat=false, sesi/jejak kosong) di watcher yang sudah mereset state ketika `participant` berganti (cari watcher `props.participant` yang ada; bila tidak ada, tambahkan `watch(() => props.participant, () => Object.assign(balancing, { dimuat: false, sesi: [], jejak: [], galat: '' }))`). Pastikan `reactive`, `HcisSkeleton` diimpor.
  - Hapus tombol `v-if="canShowKalibrasi"` (label "Kalibrasi") dan blok `<!-- Subdialog 2: Kalibrasi Hasil --> <HcisDialog v-model="dialogKalibrasi" …>…</HcisDialog>`; lalu hapus state/fungsi/emit yang hanya dirujuk keduanya (cek dengan `grep -n` tiap nama sebelum menghapus: `dialogKalibrasi`, `canShowKalibrasi`, `validCalibration`, `pilihanGrade`, `pilihanKuadran`, fungsi simpan kalibrasi dan form-nya). Kondisi `!dialogKalibrasi` di baris ~41/54 disederhanakan. Prop `canCalibrate` dan emit `calibrate` dibiarkan bila masih dirujuk halaman induk.

- [ ] **Step 4: Jalankan uji dialog + semua uji PA frontend** — `npx vitest run resources/js/Pages/KPI/PerformanceAppraisal` → PASS.
- [ ] **Step 5: Commit** — `feat(kpi-pa): tab riwayat Balancing di detail hasil, Kalibrasi Forum dilepas`.

---

### Task 7: Menu (DML), build, dan uji asap

**Files:**
- Create: `database/dml/DML-2026-159-menu-pa-balancing/data-change.sql`
- Create: `docs/changes/DML-2026-159-menu-pa-balancing/README.md`
- Modify: `database/ddl/status.json` (entri DML)
- Modify: `docs/architecture/module-structure-inventory.md` bila memuat daftar halaman PA (tambah baris Balancing, tanggal verifikasi 2026-09-30)

- [ ] **Step 1: Periksa nomor bebas** (sama seperti Bagian 1 Task 1 Step 1); pakai 159 bila bebas.

- [ ] **Step 2: Tulis DML** (idempoten, satu UPDATE, guard, tanpa `GO` di dalam transaksi)

```sql
/* Change ID: DML-2026-159-menu-pa-balancing
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-165-pa-balancing; kode halaman /performance-appraisal/balancing
                 (route performance-appraisal.balancing, check.jabatan:PaHasilPage).
   Scope: N_HRIS_Menus — SATU baris (Jenis_page = 'PaKalibrasiPage').

   Butir menu "Kalibrasi Grade" (tersembunyi sejak halamannya dialihkan) dialihkan menjadi
   "Balancing" dan ditampilkan. Jenis_page tetap PaKalibrasiPage: pemetaan grup fitur (KPI) dan
   pemegang HRIS_Page_Access-nya (UserID_Web 4 dan 622 di dev, 30 Sep 2026) sudah ada, dan sama
   persis dengan pemegang PaHasilPage — kunci rute halaman Balancing.
   Sort_Menu 11: nomor tampil yang kosong di header administrator_pa (12 dipakai HAV Map).
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50591, 'DML-2026-159 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaKalibrasiPage')
    THROW 50592, 'Butir menu PaKalibrasiPage tidak ada; tidak ada yang bisa dialihkan.', 1;

/* Pemegang PaKalibrasiPage harus juga pemegang PaHasilPage; kalau tidak, menunya tampil
   tetapi halamannya menolak (403). */
IF EXISTS (
    SELECT 1 FROM HRIS_Page_Access k
    WHERE k.Jenis_page = 'PaKalibrasiPage' AND k.Flag_Aktif = 'Y' AND k.UserID_Web IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access h WHERE h.Jenis_page = 'PaHasilPage' AND h.Flag_Aktif = 'Y' AND h.UserID_Web = k.UserID_Web)
)
    THROW 50593, 'Ada pemegang PaKalibrasiPage yang tidak memegang PaHasilPage.', 1;

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Balancing',
    Url_Menu = '/performance-appraisal/balancing',
    Route_Name = 'performance-appraisal.balancing',
    Is_Show_Sidebar = 'Y',
    Sort_Menu = '11',
    Menu_Subtitle = 'Sesi forum penyeimbangan skor kompetensi sesudah Calculate'
WHERE Jenis_page = 'PaKalibrasiPage';

IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa' AND Is_Show_Sidebar = 'Y'
    GROUP BY Kode_Perusahaan, Sort_Menu HAVING COUNT(*) > 1
)
    THROW 50594, 'Ada urutan menu kembar di antara menu yang tampil pada header Administrator PA.', 1;

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Url_Menu, Route_Name, Sort_Menu, Is_Show_Sidebar FROM N_HRIS_Menus WHERE Jenis_page = 'PaKalibrasiPage';

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
```

- [ ] **Step 3: Dokumentasi + ledger** — README perubahan (change ID, target, objek `N_HRIS_Menus`, baris terdampak 1, predikat, path, status, pekerjaan agen/pengguna, cara membatalkan: UPDATE balik ke `Kalibrasi Grade`/`/performance-appraisal/kalibrasi`/`Is_Show_Sidebar='T'`/`Sort_Menu='12'`/`Route_Name=NULL`/subtitle lama). Entri `status.json` bertipe DML seperti DML-2026-116, `status: REVIEW_REQUIRED`.

- [ ] **Step 4: Eksekusi dev** — cakupan jelas (1 tabel, 1 baris, predikat `Jenis_page='PaKalibrasiPage'`, target dev terverifikasi). Jalankan per batch lewat tinker `DB::unprepared`, verifikasi SELECT, lalu `InertiaShellService::flushMenuCache()` dan `php artisan route:clear`. Ledger → `development: DONE`, `status: TRACKER_PENDING`.

- [ ] **Step 5: Gerbang dan build**

```bash
npx vitest run resources/js/Pages/KPI/PerformanceAppraisal
NODE_OPTIONS=--max-old-space-size=6144 npm run build
php scripts/verify-inertia-pages.php
php scripts/verify-frontend-imports.php
git diff --check
php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/
```

- [ ] **Step 6: Uji asap dev2** — `curl` halaman `/performance-appraisal/balancing` (302 ke login tanpa sesi = rute hidup) dan, bila browser tersedia, buka halaman sebagai admin: siklus #8781 menampilkan daftar kosong; tombol "Buat sesi" membuka dialog; kandidat #8781 semuanya abu-abu dengan alasan. Alur penuh dua putaran menunggu siklus uji CLOSED tanpa hasil terbit (laporkan kebutuhan ini ke pemilik proyek).

- [ ] **Step 7: Commit** — `feat(kpi-pa): menu Balancing (DML-2026-159)`.
