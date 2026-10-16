"""Verifikasi kandidat lock DENGAN BATAS METODE, bukan jendela tekstual.

peta-lock.py memakai "jarak sampai DB::commit berikutnya di berkas", dan itu
bisa melewati batas metode sehingga token dari metode LAIN ikut terjaring.
handleHeader() di UpdatePerformanceController terjaring begitu: ia memang punya
lockForUpdate, tapi NOL Storage/temporaryUrl di dalam dirinya.

Di sini cakupannya dipotong tepat pada metode yang memuat kuncinya.
"""
import io
import os
import re
import sys

GARIS = chr(92)
JARINGAN = ['CloudTask', 'Storage::disk', 'temporaryUrl', 'Http::', 'Mail::', 'Notification::',
            'GuzzleHttp', 'curl_exec', 'dispatchSync']


def buang_komentar(s):
    out = []
    i, n = 0, len(s)
    while i < n:
        c = s[i]
        if c in ('"', "'"):
            q = c
            j = i + 1
            while j < n:
                if s[j] == GARIS:
                    j += 2
                    continue
                if s[j] == q:
                    j += 1
                    break
                j += 1
            out.append(s[i:j])
            i = j
        elif c == '/' and i + 1 < n and s[i + 1] == '/':
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


def metode_yang_memuat(baris, idx):
    """Batas metode yang memuat baris idx (0-based)."""
    awal = None
    for i in range(idx, -1, -1):
        if re.search(r'(public|private|protected)\s+function\s+\w+', baris[i]):
            awal = i
            break
    if awal is None:
        return None
    d = 0
    mulai = False
    for j in range(awal, len(baris)):
        d += baris[j].count('{') - baris[j].count('}')
        if '{' in baris[j]:
            mulai = True
        if mulai and d == 0:
            return (awal, j)
    return (awal, len(baris) - 1)


akar = sys.argv[1] if len(sys.argv) > 1 else 'app'
langsung, bersih = [], []

for dirpath, _d, files in os.walk(akar):
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(dirpath, f)
        src = buang_komentar(io.open(p, encoding='utf-8', errors='ignore').read())
        baris = src.split('\n')

        for m in re.finditer(r'->lockForUpdate\(\)|->sharedLock\(\)', src):
            idx = src.count('\n', 0, m.start())
            batas = metode_yang_memuat(baris, idx)
            if not batas:
                continue
            a, b = batas
            isi = '\n'.join(baris[a:b + 1])
            nama = re.search(r'function\s+(\w+)', baris[a]).group(1)
            hit = [t for t in JARINGAN if t in isi]
            rec = (p.replace(GARIS, '/'), idx + 1, nama, hit)
            (langsung if hit else bersih).append(rec)

print('=== KUNCI DENGAN PANGGILAN JARINGAN DI METODE YANG SAMA ===')
if not langsung:
    print('  (tidak ada)')
for p, ln, nama, hit in sorted(langsung, key=lambda x: x[0]):
    print('  %s:%d  %s()  -> %s' % (p[-62:], ln, nama, ', '.join(hit)))

print('\ntitik kunci total   : %d' % (len(langsung) + len(bersih)))
print('LANGSUNG ada jaringan: %d' % len(langsung))
print('bersih di metodenya  : %d' % len(bersih))
print('\nCATATAN: "bersih" hanya berarti tak ada token jaringan di METODE ITU.')
print('Panggilan bersarang seperti temuan #18 tetap tak terlihat dari sini.')
