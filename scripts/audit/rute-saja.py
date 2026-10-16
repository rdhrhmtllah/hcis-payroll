import glob, io, json, re
from collections import defaultdict

def rapi(s, n): return ' '.join(str(s).split())[:n]

baris = []
for f in glob.glob('storage/app/diagnostik/rekaman-*.jsonl'):
    for g in io.open(f, encoding='utf-8', errors='ignore'):
        try: b = json.loads(g)
        except ValueError: continue
        if isinstance(b, dict) and 'rute' in b and not b['rute'].startswith('uji:'):
            baris.append(b)

print('baris ber-RUTE HTTP (kode produksi): %d\n' % len(baris))

per = defaultdict(lambda: {'n':0,'kueri':0,'ms':0.0,'puncak':0,'sql':defaultdict(lambda:{'n':0,'ms':0.0})})
for b in baris:
    d = per[b['rute']]
    d['n'] += 1; d['kueri'] += int(b.get('kueri',0)); d['ms'] += float(b.get('ms',0))
    d['puncak'] = max(d['puncak'], int(b.get('kueri',0)))
    for sql, x in (b.get('sql') or {}).items():
        d['sql'][sql]['n'] += int(x.get('n',0)); d['sql'][sql]['ms'] += float(x.get('ms',0))

print('=== rute menurut kueri per permintaan ===')
for k, d in sorted(per.items(), key=lambda x: -x[1]['puncak']):
    print('  puncak %3d kueri · %2d permintaan · %7.0f ms   %s' % (d['puncak'], d['n'], d['ms'], rapi(k,60)))

print('\n=== rincian rute terberat ===')
for k, d in sorted(per.items(), key=lambda x: -x[1]['puncak'])[:3]:
    print('\n  %s  (puncak %d kueri)' % (rapi(k,70), d['puncak']))
    for sql, x in sorted(d['sql'].items(), key=lambda y: -y[1]['n'])[:8]:
        tanda = '  <-- BERULANG' if x['n'] / max(1,d['n']) >= 3 else ''
        print('     %3dx %7.1f ms  %s%s' % (x['n'], x['ms'], rapi(sql,74), tanda))
