"""Peta modul ESS: submodul -> fitur -> berkas, plus cakupan rekaman.

Tujuannya supaya tak ada fitur yang terlewat saat investigasi. Yang dihitung
bukan cuma "ada berkasnya", tetapi juga UKURAN-nya (baris) dan apakah fitur itu
pernah benar-benar dijalankan dalam rekaman — berkas besar yang tak pernah
tersentuh adalah tempat paling mungkin ada masalah yang belum ketahuan.
"""
import glob
import io
import json
import os
import re
from collections import defaultdict


def baris_berkas(p):
    try:
        return sum(1 for _ in io.open(p, encoding='utf-8', errors='ignore'))
    except OSError:
        return 0


# ── 1. Kumpulkan berkas ESS per submodul/fitur ──────────────────────────────
pohon = defaultdict(lambda: defaultdict(lambda: {'controller': [], 'service': [], 'job': [], 'command': []}))

for akar, jenis in (('app/Http/Controllers/ESS', 'controller'),
                    ('app/Services/ESS', 'service'),
                    ('app/Jobs/ESS', 'job'),
                    ('app/Console/Commands/ESS', 'command')):
    for dirpath, _d, files in os.walk(akar):
        for f in files:
            if not f.endswith('.php'):
                continue
            p = os.path.join(dirpath, f).replace('\\', '/')
            sisa = p[len(akar) + 1:].split('/')
            sub = sisa[0] if len(sisa) > 1 else '(akar)'
            fitur = sisa[1] if len(sisa) > 2 else '(langsung)'
            pohon[sub][fitur][jenis].append((f, baris_berkas(p)))

# ── 2. Rute ESS ─────────────────────────────────────────────────────────────
rute_per_sub = defaultdict(set)
for p in glob.glob('routes/ESS/**/*.php', recursive=True):
    sisa = p.replace('\\', '/').split('/')
    sub = sisa[2] if len(sisa) > 3 else '(akar)'
    for g in io.open(p, encoding='utf-8', errors='ignore'):
        m = re.search(r"Route::(get|post|put|patch|delete)\(\s*['\"]([^'\"]+)", g)
        if m:
            rute_per_sub[sub].add('/' + m.group(2).lstrip('/'))

# ── 3. Rute yang BENAR-BENAR dijalankan dalam rekaman ───────────────────────
dijalankan = set()
for f in glob.glob('storage/app/diagnostik/rekaman-*.jsonl'):
    for g in io.open(f, encoding='utf-8', errors='ignore'):
        try:
            b = json.loads(g)
        except ValueError:
            continue
        if isinstance(b, dict) and not str(b.get('rute', '')).startswith('uji:'):
            dijalankan.add(str(b.get('rute', '')).split(' ', 1)[-1])


def cocok(pola, nyata):
    """Rute terdaftar mengandung {param}; rekaman berisi nilainya."""
    rx = re.sub(r'\{[^}]+\}', '[^/]+', re.escape(pola).replace(r'\{', '{').replace(r'\}', '}'))
    rx = re.sub(r'\\\{[^}]+\\\}', '[^/]+', rx)
    try:
        return re.fullmatch(rx, nyata) is not None
    except re.error:
        return False


print('%-16s %-26s %5s %5s %5s %6s' % ('SUBMODUL', 'FITUR', 'ctrl', 'svc', 'lain', 'baris'))
print('-' * 78)
ringkas = []
for sub in sorted(pohon):
    for fitur in sorted(pohon[sub]):
        d = pohon[sub][fitur]
        nb = sum(n for _, n in d['controller'] + d['service'] + d['job'] + d['command'])
        ringkas.append((sub, fitur, len(d['controller']), len(d['service']),
                        len(d['job']) + len(d['command']), nb))

for sub, fitur, c, s, l, nb in sorted(ringkas, key=lambda x: (-x[5])):
    print('%-16s %-26s %5d %5d %5d %6s' % (sub[:16], fitur[:26], c, s, l, f'{nb:,}'))

print('\n%-16s %8s %8s   %s' % ('SUBMODUL', 'rute', 'tersentuh', 'cakupan'))
print('-' * 60)
tot_r = tot_t = 0
for sub in sorted(rute_per_sub):
    rutes = rute_per_sub[sub]
    kena = sum(1 for r in rutes if any(cocok(r, n) for n in dijalankan))
    tot_r += len(rutes)
    tot_t += kena
    pct = kena / len(rutes) * 100 if rutes else 0
    bar = '#' * int(pct / 5)
    print('%-16s %8d %8d   %-20s %.0f%%' % (sub[:16], len(rutes), kena, bar, pct))
print('-' * 60)
print('%-16s %8d %8d   %.1f%%' % ('TOTAL', tot_r, tot_t, tot_t / max(1, tot_r) * 100))
