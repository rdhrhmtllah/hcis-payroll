"""Peta lockForUpdate: berapa lama kunci itu dipegang, dan apa yang terjadi
di antaranya.

Yang berbahaya dari lockForUpdate bukan kuncinya, melainkan JARAK antara kunci
diambil dan transaksi ditutup. Pada basis data tanpa RCSI, setiap pembaca baris
yang sama menunggu selama itu. Jarak yang diisi panggilan jaringan (HTTP, GCS,
WhatsApp) adalah bentuk terburuknya — lamanya ditentukan pihak lain.

Murni membaca berkas; tidak menyentuh basis data.

BATASNYA HARUS DIBACA SEBELUM MEMERCAYAI KELUARANNYA
----------------------------------------------------
1. `->exists()` juga metode Builder dan Collection, bukan hanya Storage. Sebagian
   besar baris ber-"exists" di keluaran adalah kueri biasa, BUKAN jaringan. Yang
   benar-benar menandakan jaringan hanya `CloudTask`, `Storage::disk`,
   `->temporaryUrl`, `Http::`, `Mail::`, `Notification::`.

2. Kolom "jarak" dihitung sampai `DB::commit` BERIKUTNYA di dalam berkas, dan itu
   belum tentu commit yang sepasang. Untuk `DB::transaction(closure)` tak ada
   commit tekstual sama sekali, sehingga jaraknya melar sampai akhir berkas.

3. Panggilan jaringan yang bersarang DUA LAPIS ke dalam tidak akan terlihat.
   Temuan #18 (Cloud Task di dalam transaksi terkunci) hanya bisa dipastikan
   dengan MEMBACA rantainya: transaction -> dispatchExecutionTaskById ->
   cloudTaskService->dispatch -> client()->createTask().

Perlakukan keluarannya sebagai DAFTAR PENDEK untuk dibaca satu per satu, bukan
sebagai temuan. Aturan 1 di docs/audit/audit-performa-modul.md berlaku penuh.
"""
import io
import os
import re
import sys

GARIS = chr(92)

POLA_JARINGAN = re.compile(
    r'Http::|Storage::disk|->temporaryUrl\(|->exists\(\)|GuzzleHttp|file_get_contents\(\s*[\'"]http'
    r'|curl_exec|Mail::|Notification::|CloudTask|dispatchSync'
)
POLA_LAMBAT = re.compile(r'sleep\(|usleep\(|->chunk\(|foreach|while\s*\(')


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


akar = sys.argv[1] if len(sys.argv) > 1 else 'app'
temuan = []

for dirpath, _d, files in os.walk(akar):
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(dirpath, f)
        src = buang_komentar(io.open(p, encoding='utf-8', errors='ignore').read())

        for m in re.finditer(r'->lockForUpdate\(\)|->sharedLock\(\)', src):
            baris = src.count('\n', 0, m.start()) + 1

            metode = '?'
            for mm in re.finditer(r'(?:public|private|protected)\s+function\s+(\w+)', src[:m.start()]):
                metode = mm.group(1)

            # Transaksi pembungkus: cari ke belakang beginTransaction / DB::transaction.
            hulu = src[:m.start()]
            mulaiTrx = max(hulu.rfind('DB::beginTransaction'), hulu.rfind('DB::transaction'))
            dalamTrx = mulaiTrx != -1

            # Jarak sampai commit berikutnya.
            hilir = src[m.end():]
            posCommit = hilir.find('DB::commit')
            if posCommit == -1:
                # DB::transaction(closure) — pakai akhir metode berikutnya sebagai pendekatan.
                posCommit = len(hilir)
            antara = hilir[:posCommit]

            jaringan = sorted(set(x.rstrip('(') for x in POLA_JARINGAN.findall(antara)))
            perulangan = len(POLA_LAMBAT.findall(antara))
            barisAntara = antara.count('\n')

            temuan.append({
                'berkas': p.replace(GARIS, '/'),
                'baris': baris,
                'metode': metode,
                'trx': dalamTrx,
                'baris_antara': barisAntara,
                'perulangan': perulangan,
                'jaringan': jaringan,
            })

# Yang paling berbahaya lebih dulu: ada jaringan, lalu jarak terpanjang.
temuan.sort(key=lambda t: (-len(t['jaringan']), -t['baris_antara']))

print('%-56s %6s %-24s %4s %6s %5s %s' % (
    'BERKAS', 'baris', 'metode', 'trx', 'jarak', 'loop', 'jaringan di antara kunci & commit'))
print('-' * 140)
for t in temuan:
    print('%-56s %6d %-24s %4s %6d %5d %s' % (
        t['berkas'][-56:], t['baris'], t['metode'][:24],
        'ya' if t['trx'] else 'TIDAK', t['baris_antara'], t['perulangan'],
        ', '.join(t['jaringan']) if t['jaringan'] else '-'))

print('\ntotal titik kunci: %d' % len(temuan))
print('di antaranya ADA panggilan jaringan: %d' % sum(1 for t in temuan if t['jaringan']))
print('TIDAK dalam transaksi (kunci tak berguna): %d' % sum(1 for t in temuan if not t['trx']))
