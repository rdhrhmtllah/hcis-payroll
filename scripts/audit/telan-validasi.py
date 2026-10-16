"""Cari method yang memvalidasi DI DALAM try lalu menangkap Exception generik.

ValidationException adalah turunan Exception, jadi `catch (\\Exception $e)`
menelannya dan mengubah kesalahan PEMANGGIL (422, "kode_karyawan wajib diisi")
menjadi kegagalan SISTEM (500, "Gagal mengambil data").

Dua kerugiannya nyata:
  - pemanggil tak pernah tahu apa yang kurang, hanya "sistem gagal"
  - tiap salah ketik parameter tercatat sebagai Log::error, membanjiri log
    dengan kegagalan yang bukan kegagalan
"""
import io
import os
import re
from collections import defaultdict

GARIS_MIRING = chr(92)

# Disusun lewat re.escape supaya garis miring PHP tidak pernah masuk ke dalam
# kelas karakter regex — percobaan pertama menyusunnya lewat sambungan string
# dan menghasilkan "unterminated character set".
POLA_CATCH = re.compile(
    'catch' + r'\s*' + re.escape('(') + r'\s*' + re.escape(GARIS_MIRING) + '?(Exception|Throwable)' + r'\b'
)


def buang_komentar(s):
    out = []
    i, n = 0, len(s)
    while i < n:
        c = s[i]
        if c == '/' and i + 1 < n and s[i + 1] == '/':
            j = s.find('\n', i)
            j = n if j < 0 else j
            out.append(' ' * (j - i))
            i = j
        elif c == '/' and i + 1 < n and s[i + 1] == '*':
            j = s.find('*/', i + 2)
            j = n if j < 0 else j + 2
            out.append(re.sub(r'[^\n]', ' ', s[i:j]))
            i = j
        else:
            out.append(c)
            i += 1
    return ''.join(out)


def modul(p):
    bagian = p.replace(GARIS_MIRING, '/').split('/')
    for m in ('ESS', 'KPI', 'LMS', 'VISITOR', 'SYSTEM'):
        if m in bagian:
            return m
    return '(lain)'


per = defaultdict(list)
for dirpath, _d, files in os.walk('app/Http/Controllers'):
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(dirpath, f)
        src = buang_komentar(io.open(p, encoding='utf-8', errors='ignore').read())

        for m in re.finditer(r'try\s*\{', src):
            mulai = m.end()
            depth, akhir = 1, len(src)
            for k in range(mulai, len(src)):
                if src[k] == '{':
                    depth += 1
                elif src[k] == '}':
                    depth -= 1
                    if depth == 0:
                        akhir = k
                        break
            blok = src[mulai:akhir]
            if not re.search(r'->validate\(|Validator::make\(', blok):
                continue

            ekor = src[akhir:akhir + 400]
            if not POLA_CATCH.search(ekor):
                continue
            # Aman bila ValidationException ditangani/dilempar ulang di dekatnya.
            if 'ValidationException' in ekor or 'ValidationException' in blok:
                continue

            per[modul(p)].append((p.replace(GARIS_MIRING, '/'), src.count('\n', 0, mulai) + 1))

tot = 0
print('%-10s %5s' % ('MODUL', 'titik'))
print('-' * 18)
for m in sorted(per):
    print('%-10s %5d' % (m, len(per[m])))
    tot += len(per[m])
print('-' * 18)
print('%-10s %5d' % ('TOTAL', tot))

for m in sorted(per):
    print('\n=== %s ===' % m)
    for p, b in per[m]:
        print('  %s:%d' % (p, b))
