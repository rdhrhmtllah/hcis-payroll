import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrgRoda360 from './OrgRoda360.vue';

/*
 * Roda 360°.
 *
 * Yang diuji di sini sebagian besar GEOMETRI, dan geometri adalah jenis kesalahan yang
 * paling gampang lolos dari mata: dua lingkaran yang bertindih sedikit tetap terlihat
 * seperti gambar yang benar, dan atasan yang jatuh ke sisi bawah roda tetap terlihat
 * seperti roda. Karena itu posisinya dihitung dari atribut SVG yang benar-benar
 * dirender, bukan dari fungsi bantu yang dipanggil ulang oleh ujinya sendiri.
 *
 * Garis tengah roda dibaca dari lingkaran PUSAT yang dirender, bukan dari angka tetap:
 * kanvasnya memuai mengikuti kepadatan simpul, jadi angka tetap di sini akan berubah
 * dari penjaga menjadi kebohongan pada saat roda pertama kali perlu melebar.
 */

function orang(jumlah, awalan) {
    return Array.from({ length: jumlah }, (_, i) => ({
        id: `${awalan}${i}`,
        kode_perusahaan: '001',
        kode_karyawan: `${awalan}${i}`,
        nama: `Nama ${awalan}${i}`,
        aktif: true,
        nama_level: 'SUPERVISOR',
    }));
}

function pasang(atasan = 0, peers = 0, bawahan = 0, props = {}) {
    return mount(OrgRoda360, {
        props: {
            profil: {
                nama: 'ADI ANGGRAINI',
                kode_karyawan: 'UJI0271',
                kode_perusahaan: '001',
                nama_level: 'GENERAL MANAGER',
            },
            atasan: orang(atasan, 'A'),
            peers: orang(peers, 'P'),
            bawahan: orang(bawahan, 'B'),
            ...props,
        },
    });
}

/** Menegaskan tidak ada sepasang lingkaran pun yang bersinggungan atau bertindih. */
function harusTerpisah(lingkaran) {
    for (let i = 0; i < lingkaran.length; i += 1) {
        for (let j = i + 1; j < lingkaran.length; j += 1) {
            const jarak = Math.hypot(lingkaran[i].x - lingkaran[j].x, lingkaran[i].y - lingkaran[j].y);

            expect(jarak).toBeGreaterThan(lingkaran[i].r + lingkaran[j].r);
        }
    }
}

/** Titik pusat roda, dibaca dari lingkaran yang benar-benar dirender. */
function pusat(w) {
    const c = w.find('.roda-pusat circle');

    return { x: Number(c.attributes('cx')), y: Number(c.attributes('cy')), r: Number(c.attributes('r')) };
}

/** Lingkaran di lingkar, beserta kategori dan pusatnya. Pusat roda tidak ikut. */
function simpulLingkar(w) {
    return w.findAll('.roda-simpul').map((g) => {
        const c = g.find('circle');

        return {
            gaya: (g.classes().find((k) => k.startsWith('is-')) ?? '').replace('is-', ''),
            kelas: g.classes(),
            x: Number(c.attributes('cx')),
            y: Number(c.attributes('cy')),
            r: Number(c.attributes('r')),
            teks: g.findAll('text').map((t) => t.text()).join(' '),
        };
    });
}

describe('OrgRoda360 — letak simpul membawa arti', () => {
    it('menaruh Self tepat di jam 12 dan tidak membuatnya bisa diklik', () => {
        const w = pasang(2, 4, 3);
        const p = pusat(w);
        const self = simpulLingkar(w).find((s) => s.gaya === 'self');

        expect(self).toBeTruthy();
        expect(self.y).toBeLessThan(p.y);
        // Tepat di puncak: sesumbu dengan pusat roda.
        expect(Math.round(self.x)).toBe(Math.round(p.x));
        expect(self.kelas).not.toContain('is-bisa-klik');
    });

    it('Self tidak memancarkan peristiwa saat diklik', async () => {
        const w = pasang(2, 4, 3);
        const self = w.findAll('.roda-simpul').find((g) => g.classes().includes('is-self'));

        await self.trigger('click');

        expect(w.emitted('pilih')).toBeUndefined();
    });

    /* Kombinasi di bawah adalah yang benar-benar ada di data: rata-rata 1,7 atasan,
       3,9 peer, 3,5 bawahan, dengan puncak 17 bawahan. Bentuk paling sepi (satu atasan,
       tanpa peer dan bawahan) ikut karena justru DI SITU sebaran merata gagal — atasan
       tunggalnya mendarat di dasar roda. */
    it.each([
        [1, 4, 3],
        [2, 4, 5],
        [4, 6, 17],
        [1, 0, 0],
        [3, 0, 0],
        [0, 6, 0],
        [0, 0, 7],
        [4, 6, 0],
    ])('a=%i p=%i b=%i: atasan selalu di atas garis tengah, bawahan selalu di bawah', (a, p, b) => {
        const w = pasang(a, p, b);
        const tengah = pusat(w).y;
        const simpul = simpulLingkar(w);

        const atasan = simpul.filter((s) => s.gaya === 'atasan');
        const bawahan = simpul.filter((s) => s.gaya === 'bawahan');

        /* Jumlahnya ditegaskan lebih dulu. Tanpa ini, kueri yang salah mengembalikan
           daftar kosong dan `forEach` di bawahnya lolos tanpa memeriksa apa pun —
           uji hijau yang tidak menguji apa pun. */
        expect(atasan).toHaveLength(a);
        expect(bawahan).toHaveLength(b > 6 ? 7 : b);

        atasan.forEach((s) => expect(s.y).toBeLessThan(tengah - 30));
        bawahan.forEach((s) => expect(s.y).toBeGreaterThan(tengah + 30));
    });

    it.each([
        [1, 4, 3],
        [2, 4, 5],
        [4, 6, 17],
        [3, 0, 0],
        [0, 0, 7],
    ])('a=%i p=%i b=%i: tidak ada dua lingkaran yang bertindih', (a, p, b) => {
        const w = pasang(a, p, b);

        // Pusat roda ikut diperiksa: simpul yang menempel pusat sama rusaknya.
        const semua = [pusat(w), ...simpulLingkar(w)];

        expect(semua.length).toBeGreaterThan(1);
        harusTerpisah(semua);
    });
});

describe('OrgRoda360 — peringkasan', () => {
    it('meringkas kelebihan menjadi satu simpul "+N" yang menyebut jumlahnya', () => {
        const simpul = simpulLingkar(pasang(0, 0, 17));
        const ringkas = simpul.find((s) => s.teks.includes('+'));

        // 17 bawahan, batas 6 -> 6 tampil, 11 diringkas.
        expect(ringkas.teks).toContain('+11');
        expect(simpul.filter((s) => s.gaya === 'bawahan')).toHaveLength(7);
    });

    it('tidak memunculkan simpul "+N" ketika semuanya muat', () => {
        const simpul = simpulLingkar(pasang(2, 4, 5));

        expect(simpul.some((s) => /\+\d/.test(s.teks))).toBe(false);
    });

    it('memuaikan saat "+N" diklik, lalu bisa diringkas kembali', async () => {
        const w = pasang(0, 0, 17);

        const lain = w.findAll('.roda-simpul').find((g) => g.text().includes('+11'));
        await lain.trigger('click');

        expect(simpulLingkar(w).filter((s) => s.gaya === 'bawahan')).toHaveLength(18);
        expect(w.emitted('pilih')).toBeUndefined();

        const ringkas = w.findAll('.roda-simpul').find((g) => g.text().includes('ringkas'));
        await ringkas.trigger('click');

        expect(simpulLingkar(w).filter((s) => s.gaya === 'bawahan')).toHaveLength(7);
    });

    it('tetap tidak bertindih setelah dimuaikan penuh', async () => {
        const w = pasang(4, 6, 17);
        const lain = w.findAll('.roda-simpul').find((g) => g.text().includes('+11'));
        await lain.trigger('click');

        expect(simpulLingkar(w)).toHaveLength(1 + 4 + 6 + 18);
        harusTerpisah([pusat(w), ...simpulLingkar(w)]);
    });

    /* Sektor semantik tidak akan pernah muat untuk kategori sebesar ini, dan ketika
       tuntutan itu tidak bisa dipenuhi yang dilepaskan adalah sektornya — BUKAN jaminan
       tak-bertindihnya. Angka 60 jauh di atas populasi nyata (puncaknya 17); ia ada
       justru supaya batasnya diuji pada nilai yang benar-benar mengikat. */
    it('melepas sektor, bukan jaminan tak-bertindih, ketika satu kategori terlalu besar', () => {
        const w = pasang(0, 0, 60, { batas: 999 });

        expect(simpulLingkar(w)).toHaveLength(61);
        harusTerpisah([pusat(w), ...simpulLingkar(w)]);
    });
});

describe('OrgRoda360 — arah dan keadaan', () => {
    it('mengeja arah relasi pada judul simpul, bukan menyerahkannya pada letak', () => {
        const w = pasang(1, 1, 1);
        const judul = w.findAll('.roda-simpul title').map((t) => t.text());

        expect(judul.some((t) => t === 'Nama A0 adalah Atasan dari ADI ANGGRAINI.')).toBe(true);
        expect(judul.some((t) => t === 'Nama B0 adalah Bawahan dari ADI ANGGRAINI.')).toBe(true);
        expect(judul.some((t) => t.includes('rekan sejawat dari ADI ANGGRAINI — dan sebaliknya'))).toBe(true);
    });

    it('menyatakan Self sebagai kategori PA, bukan relasi tersimpan', () => {
        const w = pasang(1, 0, 0);
        const judul = w.findAll('.roda-simpul title').map((t) => t.text());

        expect(judul.some((t) => t.includes('tidak tersimpan sebagai baris relasi organisasi'))).toBe(true);
    });

    it('menandai relasi yang menunjuk karyawan nonaktif', () => {
        const w = mount(OrgRoda360, {
            props: {
                profil: { nama: 'ADI', kode_karyawan: 'X1', kode_perusahaan: '001' },
                atasan: [{ id: 1, kode_perusahaan: '001', kode_karyawan: 'K1', nama: 'Keluar', aktif: false }],
                peers: [],
                bawahan: [],
            },
        });

        const mati = simpulLingkar(w).find((s) => s.gaya === 'atasan');

        expect(mati.kelas).toContain('is-nonaktif');
        expect(w.findAll('.roda-simpul title').map((t) => t.text()).join(' '))
            .toContain('Karyawan sudah tidak aktif');
    });

    it('memancarkan pilih berisi kategori dan orangnya saat simpul diklik', async () => {
        const w = pasang(0, 0, 2);
        const b0 = w.findAll('.roda-simpul').find((g) => g.text().includes('Nama B0'));

        await b0.trigger('click');

        expect(w.emitted('pilih')).toHaveLength(1);
        expect(w.emitted('pilih')[0][0].kategori).toBe('BAWAHAN');
        expect(w.emitted('pilih')[0][0].orang.kode_karyawan).toBe('B0');
    });

    it('tetap menggambar pusat dan Self ketika belum ada satu pun relasi', () => {
        const w = pasang(0, 0, 0);

        expect(w.find('.roda-pusat circle').exists()).toBe(true);
        expect(simpulLingkar(w)).toHaveLength(1);
        expect(simpulLingkar(w)[0].gaya).toBe('self');
    });

    it('menyebut jumlah tiap kategori pada keterangan warna', () => {
        const w = pasang(2, 4, 5);
        const legenda = w.find('.roda-legenda').text();

        expect(legenda).toContain('Atasan');
        expect(legenda).toContain('Rekan Sejawat');
        expect(legenda).toContain('Bawahan');
        // Jumlah dibaca dari data, bukan dari simpul yang kebetulan tampil.
        expect(w.findAll('.roda-legenda-item strong').map((s) => s.text())).toEqual(['2', '4', '5']);
    });

    it('memakai jumlah utuh pada keterangan meski simpulnya diringkas', () => {
        const w = pasang(0, 0, 17);

        expect(w.findAll('.roda-legenda-item strong').map((s) => s.text())).toEqual(['0', '0', '17']);
    });
});
