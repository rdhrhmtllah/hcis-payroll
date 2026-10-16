<template>
    <div class="roda">
        <svg
            class="roda-svg"
            :viewBox="`0 0 ${LEBAR} ${TINGGI}`"
            role="img"
            :aria-label="ringkasanAria"
            preserveAspectRatio="xMidYMid meet"
        >
            <desc>{{ ringkasanAria }}</desc>

            <!-- JARI-JARI. Digambar lebih dulu supaya selalu berada DI BAWAH lingkaran;
                 garis yang menimpa lingkaran membuat tepinya terbaca patah. -->
            <g class="roda-jari">
                <line
                    v-for="s in simpul"
                    :key="`jari-${s.kunci}`"
                    :x1="s.pangkalX"
                    :y1="s.pangkalY"
                    :x2="s.ujungX"
                    :y2="s.ujungY"
                    :class="{ 'is-self': s.tipe === 'self' }"
                />
            </g>

            <!-- PUSAT: orang yang sedang diinspeksi. -->
            <g class="roda-pusat">
                <circle :cx="CX" :cy="CY" :r="R_PUSAT" />
                <text
                    v-for="(baris, i) in barisPusat"
                    :key="`pusat-nama-${i}`"
                    :x="CX"
                    :y="CY - (barisPusat.length - 1) * (fontPusat * 0.55) + i * (fontPusat * 1.1) - fontPusat * 0.3"
                    class="roda-pusat-nama"
                    :style="{ fontSize: `${fontPusat}px` }"
                >{{ baris }}</text>
                <text
                    v-if="profil?.nama_level"
                    :x="CX"
                    :y="CY + (barisPusat.length - 1) * (fontPusat * 0.55) + fontPusat * 1.05"
                    class="roda-pusat-level"
                    :style="{ fontSize: `${fontPusat * 0.72}px` }"
                >{{ potong(profil.nama_level, 20) }}</text>
            </g>

            <!-- SIMPUL DI LINGKAR. -->
            <g
                v-for="s in simpul"
                :key="s.kunci"
                class="roda-simpul"
                :class="[`is-${s.gaya}`, { 'is-bisa-klik': s.bisaKlik, 'is-nonaktif': s.nonaktif }]"
                :tabindex="s.bisaKlik ? 0 : -1"
                :role="s.bisaKlik ? 'button' : undefined"
                :aria-label="s.judul"
                @click="klikSimpul(s)"
                @keydown.enter.prevent="klikSimpul(s)"
                @keydown.space.prevent="klikSimpul(s)"
            >
                <title>{{ s.judul }}</title>

                <circle :cx="s.x" :cy="s.y" :r="jari" />

                <text
                    :x="s.x"
                    :y="s.y - jari * 0.44"
                    class="roda-peran"
                    :style="{ fontSize: `${fontPeran}px` }"
                >{{ s.peran }}</text>

                <text
                    v-for="(baris, i) in s.barisUtama"
                    :key="`${s.kunci}-u-${i}`"
                    :x="s.x"
                    :y="s.y - (s.barisUtama.length - 1) * (fontUtama * 0.55) + i * (fontUtama * 1.1) + fontUtama * 0.35"
                    class="roda-utama"
                    :style="{ fontSize: `${s.tipe === 'lain' ? fontUtama * 1.25 : fontUtama}px` }"
                >{{ baris }}</text>

                <text
                    v-if="s.sub && s.barisUtama.length === 1 && jari >= 32"
                    :x="s.x"
                    :y="s.y + jari * 0.5"
                    class="roda-sub"
                    :style="{ fontSize: `${fontPeran}px` }"
                >{{ s.sub }}</text>
            </g>
        </svg>

        <!-- KETERANGAN WARNA. Warna saja tidak cukup menyampaikan kategori: ia tidak
             terbaca oleh sebagian orang, dan tidak terbaca sama sekali saat dicetak
             hitam-putih. -->
        <ul class="roda-legenda">
            <li v-for="k in legenda" :key="k.kode" class="roda-legenda-item">
                <span class="roda-legenda-titik" :style="{ background: k.warna, borderColor: k.warna }" />
                <span>{{ k.label }}</span>
                <strong>{{ k.jumlah }}</strong>
            </li>
            <li class="roda-legenda-item is-self">
                <span class="roda-legenda-titik is-putus" />
                <span>Self — kategori penilaian PA, bukan relasi tersimpan</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';

defineOptions({ name: 'OrgRoda360' });

/*
 * RODA 360°.
 *
 * Dibuat terpisah dari `HcisOrgChart`: bagan itu menggambar HIRARKI — pohon dengan atas
 * dan bawah — sementara yang perlu terlihat di sini adalah SATU orang dengan seluruh
 * pihak yang mengelilinginya, termasuk yang setara. Memaksakan peer ke dalam pohon
 * berarti menggambar mereka sebagai anak atau induk, dan keduanya salah.
 *
 * Letak simpul MEMBAWA ARTI, bukan sebaran hias: Self di jam 12, atasan memeluk puncak
 * di kedua sisinya, rekan sejawat di kiri dan kanan, bawahan di dasar. Jadi bentuk
 * rodanya sendiri sudah menjawab "siapa di atas saya" sebelum satu label pun dibaca.
 */

const props = defineProps({
    profil: { type: Object, default: () => ({}) },
    atasan: { type: Array, default: () => [] },
    peers: { type: Array, default: () => [] },
    bawahan: { type: Array, default: () => [] },

    /* Ada orang dengan 17 bawahan di data nyata. Roda berisi 17 lingkaran bukan lagi
       gambar, ia noda — jadi sisanya diringkas menjadi satu simpul "+N" yang bisa
       dimuaikan. Angkanya tetap disebut; yang diringkas tanpa angka terbaca sebagai
       tidak ada. */
    batas: { type: Number, default: 6 },
});

const emit = defineEmits(['pilih']);

/* Radius acuan saat roda masih lengang. Kalau simpulnya terlalu rapat untuk radius ini,
   yang MEMUAI adalah rodanya, bukan lingkarannya yang mengecil tanpa batas: mengecilkan
   terus menghasilkan lingkaran yang saling menindih pada saat admin menekan "+N" —
   tepat ketika ia sedang ingin melihat lebih banyak, bukan lebih sedikit. */
const R_DASAR = 250;
const JARI_MIN = 26;
const JARI_MAKS = 46;
const CELAH = 12;

const KATEGORI = {
    ATASAN: { kode: 'ATASAN', label: 'Atasan', gaya: 'atasan', warna: '#0284c7' },
    PEER: { kode: 'PEER', label: 'Rekan Sejawat', gaya: 'peer', warna: '#9333ea' },
    BAWAHAN: { kode: 'BAWAHAN', label: 'Bawahan', gaya: 'bawahan', warna: '#16a34a' },
};

const muai = reactive({ ATASAN: false, PEER: false, BAWAHAN: false });

function potong(teks, n) {
    const t = String(teks ?? '').trim();

    return t.length > n ? `${t.slice(0, Math.max(1, n - 1))}…` : t;
}

/* Nama dipecah ke DUA baris kalau tidak muat, bukan dipotong begitu saja: "ADI
   ANGGRAINI" yang menjadi "ADI ANGG…" menghapus justru bagian yang membedakannya dari
   rekan senamanya. */
function pecahNama(nama, maksPerBaris) {
    const bersih = String(nama ?? '').trim().replace(/\s+/g, ' ');

    if (bersih === '') return ['—'];
    if (bersih.length <= maksPerBaris) return [bersih];

    const kata = bersih.split(' ');

    if (kata.length === 1) return [potong(bersih, maksPerBaris)];

    let atas = '';
    let i = 0;

    while (i < kata.length && (atas === '' || `${atas} ${kata[i]}`.length <= maksPerBaris)) {
        atas = atas === '' ? kata[i] : `${atas} ${kata[i]}`;
        i += 1;
    }

    const bawah = kata.slice(i).join(' ');

    return bawah === ''
        ? [potong(atas, maksPerBaris)]
        : [potong(atas, maksPerBaris), potong(bawah, maksPerBaris)];
}

function simpulKategori(daftar, kat) {
    const semua = Array.isArray(daftar) ? daftar : [];
    const tampil = muai[kat.kode] ? semua : semua.slice(0, props.batas);
    const sisa = semua.length - tampil.length;

    const hasil = tampil.map((orang, i) => ({
        tipe: 'orang',
        kat,
        orang,
        urutan: i + 1,
        kunci: `${kat.kode}-${orang.kode_perusahaan}-${orang.kode_karyawan}`,
    }));

    if (sisa > 0) {
        hasil.push({ tipe: 'lain', kat, sisa, kunci: `${kat.kode}-lain` });
    }

    /* Simpul peringkas tetap ada saat sudah dimuaikan — ia jalan pulangnya. Tanpa itu
       daftar yang terlanjur memuai tidak punya cara diringkas kembali. */
    if (sisa === 0 && muai[kat.kode] && semua.length > props.batas) {
        hasil.push({ tipe: 'ringkas', kat, kunci: `${kat.kode}-ringkas` });
    }

    return hasil;
}

/* Setengah-lebar sektor, dalam derajat.
 *
 * Inilah yang menjaga arti letaknya. Sebaran merata sepanjang lingkaran terlihat rapi
 * tetapi bohong ketika simpulnya sedikit: satu atasan tanpa peer dan tanpa bawahan
 * akan jatuh tepat di DASAR roda, di tempat yang sepanjang halaman ini berarti
 * "bawahan". Batas ini membuat atasan tidak pernah turun melewati garis tengah dan
 * bawahan tidak pernah naik melewatinya. */
const H_ATASAN = 75;
const H_BAWAHAN = 75;
const H_PEER = 42;

/* Cincinnya dibagi menjadi S slot, S kelipatan empat supaya jam 12, 3, 6, dan 9 selalu
   jatuh tepat pada slot — di situlah jangkar Self, peer kanan, bawahan, dan peer kiri.
   S dicari sekecil mungkin yang masih memenuhi seluruh syarat; roda yang lengang tidak
   perlu dibuat renggang. */
function cariSlot(ka, kpr, kpl, kb) {
    const tepiA = ka > 0 ? Math.ceil(ka / 2) : 0;
    const tepiPR = kpr > 0 ? (kpr - 1) / 2 : 0;
    const tepiPL = kpl > 0 ? (kpl - 1) / 2 : 0;
    const tepiB = kb > 0 ? (kb - 1) / 2 : 0;

    /* Lantai mutlak: tiap simpul butuh slotnya sendiri, ditambah Self dan sedikit
       pemisah antar blok. Syarat ini WAJIB diperiksa lebih dulu, karena syarat tabrakan
       antar-blok di bawah hanya menyala bila ADA dua kategori berisi — satu kategori
       raksasa sendirian melewatinya tanpa tersentuh, lalu dua ratus lingkaran dijejalkan
       ke delapan slot. */
    const lantai = 1 + 2 * tepiA + kpr + kpl + kb + 4;
    const batasAtas = Math.max(240, Math.ceil(lantai / 4) * 4);

    const cari = (ketat) => {
        for (let s = 8; s <= batasAtas; s += 4) {
            if (s < lantai) continue;

            const langkah = 360 / s;
            const perempat = s / 4;

            if (ketat) {
                if (ka > 0 && tepiA * langkah > H_ATASAN) continue;
                if (kb > 1 && tepiB * langkah > H_BAWAHAN) continue;
                if (kpr > 1 && tepiPR * langkah > H_PEER) continue;
                if (kpl > 1 && tepiPL * langkah > H_PEER) continue;
            }

            // Antar blok wajib bersisa sedikitnya satu slot kosong.
            if (ka > 0 && kpr > 0 && tepiA + tepiPR + 1 > perempat) continue;
            if (kpr > 0 && kb > 0 && tepiPR + tepiB + 1 > perempat) continue;
            if (kb > 0 && kpl > 0 && tepiB + tepiPL + 1 > perempat) continue;
            if (kpl > 0 && ka > 0 && tepiPL + tepiA + 1 > perempat) continue;
            if (ka > 0 && kpr === 0 && kb > 0 && tepiA + tepiB + 1 > 2 * perempat) continue;
            if (kpr === 0 && kpl === 0 && ka > 0 && kb > 0 && tepiA + tepiB + 1 > 2 * perempat) continue;

            return s;
        }

        return null;
    };

    /* Dua lapis, dan lapis keduanya bukan hiasan.
     *
     * Lapis pertama menuntut setiap kategori tetap berada di dalam sektornya — itulah yang
     * membuat letak simpul berarti. Tetapi satu kategori yang sangat besar (bayangkan
     * empat puluh bawahan yang dimuaikan admin) tidak akan pernah muat di sektor selebar
     * 150°, dan tuntutan yang tidak bisa dipenuhi berakhir sebagai blok yang menabrak
     * tetangganya — persis kerusakan yang aturannya ada untuk mencegah.
     *
     * Jadi ketika sektornya benar-benar tidak cukup, yang dilepaskan adalah sektornya,
     * bukan jaminan tak-bertindihnya. Rodanya melebar melewati garis tengah dan artinya
     * memudar; ia tidak pecah. */
    return cari(true) ?? cari(false) ?? batasAtas;
}

const tataLetak = computed(() => {
    const a = simpulKategori(props.atasan, KATEGORI.ATASAN);
    const p = simpulKategori(props.peers, KATEGORI.PEER);
    const b = simpulKategori(props.bawahan, KATEGORI.BAWAHAN);

    /* Rekan sejawat dibelah ke kanan dan kiri supaya roda tetap seimbang dan supaya
       "setara" terbaca sebagai mendatar, bukan menumpuk di satu sisi. */
    const pisah = Math.ceil(p.length / 2);
    const pKanan = p.slice(0, pisah);
    const pKiri = p.slice(pisah);

    const slot = cariSlot(a.length, pKanan.length, pKiri.length, b.length);
    const langkah = 360 / slot;

    /* Jari-jari lingkaran DIHITUNG dari kerapatan slotnya, tidak dipatok. Angka tetap
       berarti roda yang penuh saling menindih dan roda yang lengang penuh rongga. */
    const busur = (langkah * Math.PI) / 180 * R_DASAR;
    const jari = Math.max(JARI_MIN, Math.min(JARI_MAKS, (busur - CELAH) / 2));

    /* Dan kalau pada radius acuan lingkaran sekecil batas pun masih tidak muat, RADIUSNYA
       yang ditambah sampai muat. Kelilingnya harus sanggup memuat semua slot beserta
       celahnya; itu satu-satunya syarat yang menjamin tidak ada yang bertindih. */
    const R = Math.max(R_DASAR, (slot * (2 * jari + CELAH)) / (2 * Math.PI));

    const R_PUSAT = Math.max(62, Math.min(110, R * 0.26));
    const LEBAR = Math.round(2 * (R + jari + 24));
    const TINGGI = LEBAR;
    const CX = LEBAR / 2;
    const CY = TINGGI / 2;

    const kasar = [{
        tipe: 'self',
        kunci: 'self',
        kat: { kode: 'SELF', label: 'Self', gaya: 'self' },
        slot: 0,
    }];

    /* Atasan MEMELUK puncak dari dua sisi — kanan, kiri, kanan, kiri — sehingga Self
       yang di jam 12 tetap punya ruang dan atasan tetap terbaca "di atas". */
    a.forEach((s, i) => {
        const jarak = Math.floor(i / 2) + 1;
        kasar.push({ ...s, slot: i % 2 === 0 ? jarak : -jarak });
    });

    pKanan.forEach((s, i) => kasar.push({ ...s, slot: slot / 4 + (i - (pKanan.length - 1) / 2) }));
    b.forEach((s, i) => kasar.push({ ...s, slot: slot / 2 + (i - (b.length - 1) / 2) }));
    pKiri.forEach((s, i) => kasar.push({ ...s, slot: (3 * slot) / 4 + (i - (pKiri.length - 1) / 2) }));

    const simpul = kasar.map((s) => {
        const sudut = (-90 + s.slot * langkah) * Math.PI / 180;
        const kos = Math.cos(sudut);
        const sin = Math.sin(sudut);

        return {
            ...s,
            x: CX + R * kos,
            y: CY + R * sin,
            pangkalX: CX + (R_PUSAT + 4) * kos,
            pangkalY: CY + (R_PUSAT + 4) * sin,
            ujungX: CX + (R - jari - 4) * kos,
            ujungY: CY + (R - jari - 4) * sin,
        };
    });

    return { simpul, jari, LEBAR, TINGGI, CX, CY, R_PUSAT };
});

const jari = computed(() => tataLetak.value.jari);
const LEBAR = computed(() => tataLetak.value.LEBAR);
const TINGGI = computed(() => tataLetak.value.TINGGI);
const CX = computed(() => tataLetak.value.CX);
const CY = computed(() => tataLetak.value.CY);
const R_PUSAT = computed(() => tataLetak.value.R_PUSAT);
const fontPusat = computed(() => Math.max(12, Math.min(18, R_PUSAT.value * 0.23)));
const fontUtama = computed(() => Math.max(8.5, Math.min(13, jari.value * 0.3)));
const fontPeran = computed(() => Math.max(7, Math.min(9.5, jari.value * 0.22)));

const maksHuruf = computed(() => Math.max(6, Math.floor((jari.value * 1.62) / (fontUtama.value * 0.56))));

const namaPusat = computed(() => props.profil?.nama || props.profil?.kode_karyawan || '—');
const barisPusat = computed(() => pecahNama(namaPusat.value, 16));

const simpul = computed(() => tataLetak.value.simpul.map((s) => {
    if (s.tipe === 'self') {
        return {
            ...s,
            gaya: 'self',
            peran: 'SELF',
            barisUtama: pecahNama(namaPusat.value, maksHuruf.value),
            sub: 'diri sendiri',
            bisaKlik: false,
            nonaktif: false,
            judul: `Self — ${namaPusat.value} menilai dirinya sendiri. Kategori penilaian Performance Appraisal; tidak tersimpan sebagai baris relasi organisasi.`,
        };
    }

    if (s.tipe === 'lain') {
        return {
            ...s,
            gaya: s.kat.gaya,
            peran: s.kat.label.toUpperCase(),
            barisUtama: [`+${s.sisa}`],
            sub: 'lainnya',
            bisaKlik: true,
            nonaktif: false,
            judul: `${s.sisa} ${s.kat.label} lainnya belum ditampilkan. Klik untuk memuaikan.`,
        };
    }

    if (s.tipe === 'ringkas') {
        return {
            ...s,
            gaya: s.kat.gaya,
            peran: s.kat.label.toUpperCase(),
            barisUtama: ['−'],
            sub: 'ringkas',
            bisaKlik: true,
            nonaktif: false,
            judul: `Ringkas kembali daftar ${s.kat.label}.`,
        };
    }

    const nama = s.orang?.nama || s.orang?.kode_karyawan || '—';
    const nonaktif = s.orang?.aktif === false;

    return {
        ...s,
        gaya: s.kat.gaya,
        peran: s.kat.kode === 'PEER' ? `PEER ${s.urutan}` : s.kat.label.toUpperCase(),
        barisUtama: pecahNama(nama, maksHuruf.value),
        sub: s.orang?.nama_level && s.orang.nama_level !== '—' ? potong(s.orang.nama_level, maksHuruf.value + 2) : null,
        bisaKlik: true,
        nonaktif,
        /* Arahnya dieja, bukan diserahkan pada tafsiran letak. */
        judul: s.kat.kode === 'PEER'
            ? `${nama} adalah rekan sejawat dari ${namaPusat.value} — dan sebaliknya.${nonaktif ? ' Karyawan sudah tidak aktif.' : ''}`
            : `${nama} adalah ${s.kat.label} dari ${namaPusat.value}.${nonaktif ? ' Karyawan sudah tidak aktif.' : ''}`,
    };
}));

const legenda = computed(() => [
    { kode: 'ATASAN', label: 'Atasan', warna: KATEGORI.ATASAN.warna, jumlah: props.atasan?.length ?? 0 },
    { kode: 'PEER', label: 'Rekan Sejawat', warna: KATEGORI.PEER.warna, jumlah: props.peers?.length ?? 0 },
    { kode: 'BAWAHAN', label: 'Bawahan', warna: KATEGORI.BAWAHAN.warna, jumlah: props.bawahan?.length ?? 0 },
]);

const ringkasanAria = computed(() => (
    `Roda relasi 360 derajat ${namaPusat.value}: `
    + `${props.atasan?.length ?? 0} atasan, `
    + `${props.peers?.length ?? 0} rekan sejawat, `
    + `${props.bawahan?.length ?? 0} bawahan, ditambah penilaian diri sendiri.`
));

function klikSimpul(s) {
    if (!s.bisaKlik) return;

    if (s.tipe === 'lain') {
        muai[s.kat.kode] = true;

        return;
    }

    if (s.tipe === 'ringkas') {
        muai[s.kat.kode] = false;

        return;
    }

    emit('pilih', { kategori: s.kat.kode, orang: s.orang });
}
</script>

<style scoped>
.roda {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.roda-svg {
    display: block;
    width: 100%;
    height: auto;
    max-height: 30rem;
    overflow: visible;
}

/* Jari-jari sengaja sangat pucat: ia penghubung, bukan informasi. */
.roda-jari line {
    stroke: #e2e8f0;
    stroke-width: 2;
}

.roda-jari line.is-self {
    stroke: #c7d2fe;
    stroke-dasharray: 4 4;
}

.roda-pusat circle {
    fill: #2563eb;
    stroke: #1d4ed8;
    stroke-width: 3;
}

.roda-pusat-nama {
    fill: #ffffff;
    font-weight: 800;
    text-anchor: middle;
    dominant-baseline: middle;
}

.roda-pusat-level {
    fill: rgba(255, 255, 255, 0.82);
    font-weight: 600;
    text-anchor: middle;
    dominant-baseline: middle;
}

.roda-simpul circle {
    fill: #ffffff;
    stroke-width: 2.5;
    transition: transform 160ms ease, filter 160ms ease;
    transform-box: fill-box;
    transform-origin: center;
}

.roda-simpul text {
    text-anchor: middle;
    dominant-baseline: middle;
    pointer-events: none;
}

.roda-peran {
    font-weight: 700;
    letter-spacing: 0.06em;
}

.roda-utama {
    font-weight: 800;
}

.roda-sub {
    font-weight: 600;
    opacity: 0.75;
}

.roda-simpul.is-bisa-klik {
    cursor: pointer;
}

.roda-simpul.is-bisa-klik:hover circle,
.roda-simpul.is-bisa-klik:focus-visible circle {
    filter: drop-shadow(0 4px 10px rgba(15, 23, 42, 0.18));
    transform: scale(1.06);
}

.roda-simpul:focus {
    outline: none;
}

.roda-simpul:focus-visible circle {
    stroke-width: 4;
}

/* Gerak mengikuti penanda proyek, BUKAN prefers-reduced-motion: mesin pengembangan
   di sini menyalakan pengurangan gerak sistem, jadi memakainya berarti animasinya
   tidak pernah teruji. */
:global([data-hcis-motion='reduced']) .roda-simpul circle {
    transition: none;
}

:global([data-hcis-motion='reduced']) .roda-simpul.is-bisa-klik:hover circle,
:global([data-hcis-motion='reduced']) .roda-simpul.is-bisa-klik:focus-visible circle {
    transform: none;
}

.roda-simpul.is-atasan circle { stroke: #0284c7; fill: #f0f9ff; }
.roda-simpul.is-atasan .roda-utama { fill: #075985; }
.roda-simpul.is-atasan .roda-peran,
.roda-simpul.is-atasan .roda-sub { fill: #0284c7; }

.roda-simpul.is-peer circle { stroke: #9333ea; fill: #faf5ff; }
.roda-simpul.is-peer .roda-utama { fill: #6b21a8; }
.roda-simpul.is-peer .roda-peran,
.roda-simpul.is-peer .roda-sub { fill: #9333ea; }

.roda-simpul.is-bawahan circle { stroke: #16a34a; fill: #f0fdf4; }
.roda-simpul.is-bawahan .roda-utama { fill: #15803d; }
.roda-simpul.is-bawahan .roda-peran,
.roda-simpul.is-bawahan .roda-sub { fill: #16a34a; }

.roda-simpul.is-self circle {
    stroke: #6366f1;
    fill: #eef2ff;
    stroke-dasharray: 5 4;
}

.roda-simpul.is-self .roda-utama { fill: #3730a3; }
.roda-simpul.is-self .roda-peran,
.roda-simpul.is-self .roda-sub { fill: #6366f1; }

/* Relasi yang menunjuk orang nonaktif tetap digambar — ia ada di data — tetapi tidak
   boleh terbaca seperti relasi yang hidup. */
.roda-simpul.is-nonaktif circle {
    stroke-dasharray: 3 3;
    opacity: 0.65;
}

.roda-simpul.is-nonaktif text {
    opacity: 0.7;
}

.roda-legenda {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.35rem 0.9rem;
    margin: 0;
    padding: 0.5rem 0.25rem 0;
    list-style: none;
    border-top: 1px dashed #e2e8f0;
}

.roda-legenda-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #475569;
}

.roda-legenda-item strong {
    font-variant-numeric: tabular-nums;
    color: #0f172a;
}

.roda-legenda-item.is-self {
    font-style: italic;
    opacity: 0.85;
}

.roda-legenda-titik {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 999px;
    border: 2px solid transparent;
}

.roda-legenda-titik.is-putus {
    background: #eef2ff;
    border: 2px dashed #6366f1;
}

@media (max-width: 640px) {
    .roda-svg {
        max-height: 22rem;
    }

    .roda-legenda-item {
        font-size: 0.68rem;
    }
}
</style>
