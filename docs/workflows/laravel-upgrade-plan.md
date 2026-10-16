# Rencana Upgrade Laravel 10 ke Laravel 11/12

Status: REVIEW_REQUIRED
Tanggal review: 2026-08-16
Branch: `feat/optimize-workflow`

## Tujuan

Mengurangi advisory Laravel Framework tanpa melakukan upgrade major secara massal atau mengubah database. Upgrade harus dilakukan bertahap, dengan checkpoint Git dan quality gate setelah setiap batch.

## Baseline

- Laravel Framework: `10.48.28`
- PHP: `8.2.33`
- Inertia Laravel: `2.0.0`
- Sanctum: `3.3.3`
- Collision: `7.11.0`
- Vinkla Hashids: `11.0.0`
- PHPUnit: `10.5.64`
- Struktur aplikasi masih menggunakan `app/Http/Kernel.php` dan binding kernel di `bootstrap/app.php`.
- SQL Server eksternal tetap dipertahankan; migration dan perubahan schema tidak termasuk scope upgrade.

## Blocker kompatibilitas

| Package | Versi saat ini | Constraint | Dampak |
|---|---:|---|---|
| `laravel/sanctum` | `3.3.3` | Illuminate 9/10 | Perlu Sanctum 4 untuk Laravel 11/12 |
| `nunomaduro/collision` | `7.11.0` | Konflik Laravel >=11 | Perlu Collision 8 pada `require-dev` |
| `vinkla/hashids` | `11.0.0` | Illuminate 10 | Perlu Hashids 12+ dan review API |
| `symfony/*` | 6.4.x | Laravel 10 | Laravel 11 memerlukan Symfony 7.x |
| `laravel/framework` | `10.48.28` | Advisory tersisa | Upgrade major diperlukan untuk menghilangkan advisory Laravel 10 |

Package yang sudah kompatibel lintas Laravel 10/11 berdasarkan constraint saat ini antara lain Inertia Laravel, Real Rashid Sweet Alert, Spatie Laravel Ignition, dan Opcode Log Viewer; tetap perlu diuji setelah framework berubah.

## Temuan struktur aplikasi

- `bootstrap/app.php` memakai pola Laravel 10 untuk binding HTTP kernel, Console kernel, dan Exception Handler.
- `app/Http/Kernel.php` memiliki middleware global, group, dan alias custom yang cukup banyak.
- Middleware custom tidak boleh dipindahkan atau dihapus otomatis saat migrasi.
- `AGENTS.md` melarang Laravel migration untuk workflow database project.
- Test Laravel harus dijalankan dengan `env -u DATABASE_URL`.

## Tahapan yang disetujui untuk investigasi

### Tahap A — Persiapan

1. Buat checkpoint Git sebelum perubahan dependency.
2. Catat hasil quality gate baseline.
3. Review release notes dan upgrade guide Laravel 11/12.
4. Review package custom/provider yang bergantung pada kernel atau middleware.

### Tahap B — Upgrade kompatibilitas Laravel 11

Dry-run awal memakai target berikut:

```text
laravel/framework:^11.0
laravel/sanctum:^4.0
nunomaduro/collision:^8.0
vinkla/hashids:^12.0
```

Dry-run belum diterapkan. Composer menolak kandidat Laravel karena advisory policy dan perubahan ini dikembalikan ke kondisi awal.

Upgrade yang sebenarnya memerlukan pengujian di branch/checkpoint terpisah dan kemungkinan pembaruan paket Symfony 7.

### Tahap C — Verifikasi

Setelah perubahan disetujui dan diterapkan di branch kerja:

```bash
composer validate --no-check-publish
env -u DATABASE_URL php artisan about
env -u DATABASE_URL php artisan test --testsuite=Unit
composer audit --no-interaction
```

Quality gate tambahan:

```bash
npm run test:unit
NODE_OPTIONS=--max-old-space-size=4096 npm run build
git diff --check
```

## Risiko dan rollback

- Risiko utama: perubahan middleware/kernel, perubahan Symfony 7, perubahan behavior validation/routing, dan incompatibility package third-party.
- Jangan menjalankan migration atau DDL sebagai bagian dari upgrade.
- Rollback teknis dilakukan dengan mengembalikan commit dependency pada branch kerja; jangan menjalankan SQL rollback otomatis.
- Production tidak disentuh. Deployment production tetap tugas user setelah review dan verifikasi.

## Keputusan saat ini

- Upgrade Laravel major **belum disetujui untuk eksekusi**.
- Tidak ada perubahan `composer.json` atau `composer.lock` dari dry-run.
- Browser test tetap ditunda.
- Langkah berikutnya adalah review release notes dan source usage package yang terpengaruh, kemudian meminta persetujuan eksplisit sebelum menerapkan upgrade Laravel 11.
