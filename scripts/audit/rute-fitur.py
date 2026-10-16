"""Daftar rute milik satu fitur, dipisah aman (GET) vs mengubah data.

Dipakai untuk menyisir sebuah fitur secara sistematis tanpa ada yang terlewat.

    php artisan route:list --json | python scripts/audit/rute-fitur.py ESS Leave

Pemisahan GET vs non-GET bukan kerapian, melainkan pagar: menyapu rute POST /
PUT / DELETE di basis data dev berarti mengubah data orang lain yang sedang
memakainya. Penyisiran otomatis HANYA boleh menyentuh yang GET.
"""
import json
import sys

if len(sys.argv) < 3:
    print('pakai: route:list --json | python rute-fitur.py <MODUL> <FITUR>')
    raise SystemExit(1)

modul, fitur = sys.argv[1], sys.argv[2]
data = json.load(sys.stdin)

aman, mengubah = [], []
for r in data:
    act = r.get('action') or ''
    if modul not in act or fitur not in act:
        continue
    metode = (r.get('method') or '').split('|')
    kelas = act.split('\\')[-1]
    baris = (r.get('uri', ''), kelas)
    if 'GET' in metode and not any(m in metode for m in ('POST', 'PUT', 'PATCH', 'DELETE')):
        aman.append(baris)
    else:
        mengubah.append(baris)

print('=== %s / %s ===' % (modul, fitur))
print('\n-- GET (aman disapu otomatis): %d --' % len(aman))
for uri, kelas in sorted(aman):
    print('  %-62s %s' % (uri[:62], kelas[:40]))

print('\n-- mengubah data (JANGAN disapu otomatis): %d --' % len(mengubah))
for uri, kelas in sorted(mengubah):
    print('  %-62s %s' % (uri[:62], kelas[:40]))

print('\ntotal: %d rute' % (len(aman) + len(mengubah)))
