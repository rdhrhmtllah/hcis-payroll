"""Sisir statis, versi yang bisa dipercaya.

Versi pertama membaca KOMENTAR sebagai kode: seluruh 8 temuan non-sargable di
ShiftApprovalController ternyata blok yang sudah dimatikan. Angka pemindai yang
memuat positif palsu lebih buruk daripada tidak ada angka sama sekali — ia
membuat orang mengejar hantu dengan yakin.

Dua perbaikan:
  1. Komentar (// dan /* */) dibuang sebelum apa pun dicocokkan.
  2. Cakupan transaksi dilacak dengan HITUNGAN KURUNG, bukan jendela sekian
     karakter. Panggilan jaringan sesudah commit/rollBack bukan masalah — dan
     versi pertama menuduh lima berkas yang semuanya sudah benar.
"""
import io
import os
import re
import sys
from collections import defaultdict

AKAR = 'app'


def buang_komentar(src: str) -> str:
    """Ganti isi komentar dengan spasi, panjang baris tetap agar nomornya benar."""
    keluar = []
    i, n = 0, len(src)
    while i < n:
        c = src[i]
        if c == '/' and i + 1 < n and src[i + 1] == '/':
            j = src.find('\n', i)
            j = n if j < 0 else j
            keluar.append(' ' * (j - i))
            i = j
        elif c == '#' and (i + 1 >= n or src[i + 1] != '['):
            j = src.find('\n', i)
            j = n if j < 0 else j
            keluar.append(' ' * (j - i))
            i = j
        elif c == '/' and i + 1 < n and src[i + 1] == '*':
            j = src.find('*/', i + 2)
            j = n if j < 0 else j + 2
            keluar.append(re.sub(r'[^\n]', ' ', src[i:j]))
            i = j
        else:
            keluar.append(c)
            i += 1
    return ''.join(keluar)


EKSTERNAL = re.compile(
    r'(Http::|GuzzleHttp|curl_exec|file_get_contents\s*\(\s*[\'"]https?|'
    r'Mail::|Notification::send|->notify\(|Storage::disk\()', re.I)


def transaksi_berjaringan(src: str):
    """Panggilan jaringan yang benar-benar BERADA DI DALAM transaksi."""
    hasil = []
    for m in re.finditer(r'DB::(?:beginTransaction\s*\(\s*\)|transaction\s*\()', src):
        mulai = m.end()
        if 'beginTransaction' in m.group(0):
            # Cakupannya sampai commit / rollBack pertama.
            akhir = len(src)
            for tanda in ('DB::commit(', 'DB::rollBack(', 'DB::rollback('):
                p = src.find(tanda, mulai)
                if p != -1:
                    akhir = min(akhir, p)
        else:
            # Cakupannya sampai kurung penutup closure-nya.
            depth, akhir = 1, len(src)
            for k in range(mulai, len(src)):
                if src[k] == '(':
                    depth += 1
                elif src[k] == ')':
                    depth -= 1
                    if depth == 0:
                        akhir = k
                        break
        potong = src[mulai:akhir]
        e = EKSTERNAL.search(potong)
        if e:
            hasil.append((src.count('\n', 0, mulai) + 1, e.group(0)[:28]))
    return hasil


POLA = [
    ('non-sargable', re.compile(
        r'->(whereMonth|whereYear|whereDay)\(|'
        r'whereRaw\([\'"][^\'"]*?(CAST\(|CONVERT\(|LEFT\(|RIGHT\(|SUBSTRING\()', re.I)),
    ('kueri-dalam-foreach', re.compile(
        r'foreach\s*\([^)]*\)\s*\{[^{}]{0,300}?(DB::table\(|->firstOrCreate\(|->updateOrCreate\(|::find\()', re.S)),
    ('get-tanpa-plafon', re.compile(r'->get\(\)')),
    ('lockForUpdate', re.compile(r'->lockForUpdate\(')),
]


def modul(path: str) -> str:
    b = path.replace('\\', '/').split('/')
    for m in ('ESS', 'KPI', 'LMS', 'VISITOR', 'SYSTEM'):
        if m in b:
            return m
    return '(lain)'


hasil = defaultdict(lambda: defaultdict(list))

for dirpath, _d, files in os.walk(AKAR):
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(dirpath, f)
        try:
            mentah = io.open(p, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        src = buang_komentar(mentah)
        m = modul(p)

        for baris, apa in transaksi_berjaringan(src):
            hasil[m]['jaringan-dalam-transaksi'].append((p, baris, apa))

        for nama, rx in POLA:
            for c in rx.finditer(src):
                hasil[m][nama].append((p, src.count('\n', 0, c.start()) + 1, ''))

kolom = ['jaringan-dalam-transaksi'] + [n for n, _ in POLA]
print(f"{'modul':<10} " + '  '.join(f'{k[:22]:>24}' for k in kolom))
print('-' * 122)
tot = defaultdict(int)
for m in sorted(hasil):
    print(f'{m:<10} ' + '  '.join(f'{len(hasil[m][k]):>24}' for k in kolom))
    for k in kolom:
        tot[k] += len(hasil[m][k])
print('-' * 122)
print(f"{'TOTAL':<10} " + '  '.join(f'{tot[k]:>24}' for k in kolom))

for nama in ('jaringan-dalam-transaksi', 'non-sargable'):
    print(f'\n=== {nama} ===')
    n = 0
    for m in sorted(hasil):
        for p, b, extra in hasil[m][nama]:
            n += 1
            if n > 20:
                break
            print(f'  [{m}] {p}:{b}' + (f'  -> {extra}' if extra else ''))
    if n == 0:
        print('  (tidak ada)')
