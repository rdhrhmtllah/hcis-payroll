<template>
    <Head title="Fondasi — Design System HCIS" />

    <div ref="akar" class="ds-fondasi" :data-hcis-theme="tema === 'baku' ? null : tema">
        <DsPortalNav />

        <header class="ds-fondasi__kepala">
            <h1 class="hcis-type-display">Fondasi & Desain Token</h1>
            <p class="hcis-type-body-lg ds-fondasi__intro">
                Warna, huruf, jarak, bentuk, dan gerak yang dipakai seluruh antarmuka.
                Semuanya lahir di <code>resources/css/design-system.css</code> dan tidak
                boleh didefinisikan ulang di tempat lain.
            </p>
            <p class="hcis-type-caption ds-fondasi__catatan">
                Nilai di halaman ini dibaca langsung dari CSS yang sedang berjalan, bukan
                disalin tangan — jadi ia tidak bisa ketinggalan zaman tanpa ketahuan.
            </p>
        </header>

        <!-- ── Saklar tema ─────────────────────────────────────────────── -->
        <section class="ds-blok">
            <h2 class="hcis-type-title">Tiga lapis, satu saklar</h2>
            <p class="hcis-type-body ds-blok__pengantar">
                Primitif menyimpan nilai mentah. Peran menyebut arti. Tema menimpa peran.
                Tekan salah satu tombol di bawah — seluruh halaman ini ikut berpindah,
                termasuk contoh komponennya, tanpa satu berkas komponen pun disentuh.
            </p>

            <div class="ds-tema">
                <button
                    v-for="pilihan in DAFTAR_TEMA"
                    :key="pilihan.kunci"
                    type="button"
                    class="ds-tema__tombol"
                    :class="{ 'is-aktif': tema === pilihan.kunci }"
                    @click="gantiTema(pilihan.kunci)"
                >
                    <span class="ds-tema__titik" :style="{ background: pilihan.contoh }" />
                    {{ pilihan.nama }}
                </button>
            </div>

            <div class="ds-tema__panggung">
                <button type="button" class="hcis-btn hcis-btn--primary">Simpan</button>
                <button type="button" class="hcis-btn hcis-btn--ghost">Batal</button>
                <span class="hcis-badge hcis-badge--brand hcis-badge--sm">
                    <span class="hcis-badge__text">Terkini</span>
                </span>
                <span class="ds-tema__kotak" />
            </div>

            <!--
                Kontras dihitung hidup, bukan diklaim. Tema yang membuat label
                tombol primer turun di bawah 4.5:1 akan langsung ketahuan di sini
                alih-alih ditemukan pengguna berbulan-bulan kemudian.
            -->
            <p class="hcis-type-caption ds-tema__kontras" :class="{ 'is-gagal': !kontrasLolos }">
                Teks putih di atas <code>--hcis-brand-600</code>:
                <strong>{{ kontrasBrand.toFixed(2) }}:1</strong>
                — {{ kontrasLolos ? 'lolos' : 'GAGAL' }} ambang WCAG 1.4.3 (4,5:1)
            </p>
        </section>

        <!-- ── Warna ───────────────────────────────────────────────────── -->
        <section class="ds-blok">
            <h2 class="hcis-type-title">Warna</h2>

            <h3 class="hcis-type-heading ds-blok__sub">Peran — inilah yang dipakai kode</h3>
            <p class="hcis-type-body ds-blok__pengantar">
                Namanya menyebut arti, bukan rona, supaya kalimatnya tetap benar setelah
                tema berganti. <code>--hcis-brand-600</code> tetap berarti "warna aksi
                utama" walau isinya berubah dari indigo jadi teal.
            </p>
            <div v-for="ramp in RAMP_PERAN" :key="ramp.nama" class="ds-ramp">
                <div class="hcis-type-overline ds-ramp__nama">{{ ramp.nama }}</div>
                <div class="ds-ramp__deret">
                    <div v-for="langkah in ramp.langkah" :key="langkah" class="ds-swatch">
                        <div class="ds-swatch__warna" :style="{ background: `var(--hcis-${ramp.awalan}-${langkah})` }" />
                        <div class="ds-swatch__label">{{ langkah }}</div>
                        <div class="ds-swatch__nilai">{{ nilai(`--hcis-${ramp.awalan}-${langkah}`) }}</div>
                    </div>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Peran semantik</h3>
            <div class="ds-peran">
                <div v-for="token in PERAN_TUNGGAL" :key="token.nama" class="ds-peran__baris">
                    <span class="ds-peran__contoh" :style="{ background: `var(${token.nama})` }" />
                    <code class="ds-peran__token">{{ token.nama }}</code>
                    <span class="ds-peran__nilai">{{ nilai(token.nama) }}</span>
                    <span class="hcis-type-caption ds-peran__guna">{{ token.guna }}</span>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Primitif — jangan dipakai langsung</h3>
            <p class="hcis-type-body ds-blok__pengantar">
                Menulis <code>var(--hcis-indigo-500)</code> di komponen sama bekunya dengan
                menulis <code>#6366f1</code>: keduanya tidak ikut berpindah saat tema
                berganti. Primitif hanya boleh disentuh oleh lapis peran.
            </p>
            <div v-for="ramp in RAMP_PRIMITIF" :key="ramp.awalan" class="ds-ramp">
                <div class="hcis-type-overline ds-ramp__nama">{{ ramp.nama }}</div>
                <div class="ds-ramp__deret">
                    <div v-for="langkah in ramp.langkah" :key="langkah" class="ds-swatch">
                        <div class="ds-swatch__warna" :style="{ background: `var(--hcis-${ramp.awalan}-${langkah})` }" />
                        <div class="ds-swatch__label">{{ langkah }}</div>
                    </div>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Warna beralfa</h3>
            <p class="hcis-type-body ds-blok__pengantar">
                Halo fokus, bayangan, dan tirai harus ikut berpindah saat tema berganti.
                Karena itu setiap warna juga punya bentuk triple, dan alfa ditulis begini:
            </p>
            <pre class="ds-kode"><code>/* salah — membeku pada indigo selamanya */
box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.5);

/* benar — ikut berpindah */
box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.5);</code></pre>

            <h3 class="hcis-type-heading ds-blok__sub">Utang warna</h3>
            <p class="hcis-type-body ds-blok__pengantar">
                Rona-rona ini terlanjur dipakai sebelum palet resmi ada. Mereka diberi nama
                supaya utangnya <em>terhitung</em>, bukan supaya dilestarikan. Yang paling
                merepotkan: hijau — <code>--hcis-green-600</code> dan
                <code>--hcis-success-500</code> sama-sama berarti "berhasil" tapi berbeda
                rona. Meruntuhkannya jadi satu mengubah tampilan, jadi ia menunggu
                persetujuan.
            </p>
            <div class="ds-utang">
                <span v-for="token in UTANG" :key="token" class="ds-utang__butir">
                    <span class="ds-utang__contoh" :style="{ background: `var(${token})` }" />
                    <code>{{ token.replace('--hcis-', '') }}</code>
                </span>
            </div>
        </section>

        <!-- ── Huruf ───────────────────────────────────────────────────── -->
        <section class="ds-blok">
            <h2 class="hcis-type-title">Huruf</h2>
            <p class="hcis-type-body ds-blok__pengantar">
                Satu keluarga huruf: <strong>Nunito</strong>, dimuat sendiri di
                <code>app.css</code> pada bobot 400/500/600/700/800. Hierarki dibangun dari
                bobot dan ukuran, bukan dari dua keluarga huruf.
            </p>
            <p class="hcis-type-caption ds-blok__pengantar">
                Sebelumnya token ini menyebut 'Plus Jakarta Sans' untuk judul dan
                'JetBrains Mono' untuk kode padahal tidak ada berkasnya di proyek ini —
                keduanya diam-diam jatuh ke Nunito. Dan bobot 500 tidak pernah
                dideklarasikan <code>@font-face</code>-nya walau berkasnya ada, sehingga
                "medium" jatuh ke 400 dan tangga bobot ini cuma punya tiga anak tangga.
            </p>

            <div class="ds-tipe">
                <div v-for="peran in PERAN_TIPOGRAFI" :key="peran.kelas" class="ds-tipe__baris">
                    <div :class="peran.kelas">{{ peran.contoh }}</div>
                    <div class="ds-tipe__meta">
                        <code>.{{ peran.kelas }}</code>
                        <span class="hcis-type-caption">{{ peran.guna }}</span>
                    </div>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Bobot</h3>
            <div class="ds-bobot">
                <div v-for="bobot in BOBOT" :key="bobot.token" class="ds-bobot__butir">
                    <span :style="{ fontWeight: `var(${bobot.token})` }" class="ds-bobot__contoh">Aa</span>
                    <code>{{ bobot.token.replace('--hcis-weight-', '') }}</code>
                    <span class="hcis-type-caption">{{ nilai(bobot.token) }} · {{ bobot.guna }}</span>
                </div>
            </div>
        </section>

        <!-- ── Bentuk & jarak ──────────────────────────────────────────── -->
        <section class="ds-blok">
            <h2 class="hcis-type-title">Bentuk dan jarak</h2>

            <h3 class="hcis-type-heading ds-blok__sub">Radius</h3>
            <div class="ds-bentuk">
                <div v-for="r in RADIUS" :key="r.token" class="ds-bentuk__butir">
                    <div class="ds-bentuk__kotak" :style="{ borderRadius: `var(${r.token})` }" />
                    <code>{{ r.token.replace('--hcis-radius-', '') }}</code>
                    <span class="hcis-type-caption">{{ nilai(r.token) }} · {{ r.guna }}</span>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Elevasi & Bayangan (Multi-Layer Shadows)</h3>
            <div class="ds-bentuk">
                <div v-for="b in BAYANGAN" :key="b.token" class="ds-bentuk__butir">
                    <div class="ds-bentuk__kotak ds-bentuk__kotak--polos" :style="{ boxShadow: `var(${b.token})` }" />
                    <code>{{ b.token.replace('--hcis-shadow-', '') }}</code>
                    <span class="hcis-type-caption">{{ b.guna }}</span>
                </div>
            </div>

            <h3 class="hcis-type-heading ds-blok__sub">Jarak</h3>
            <div class="ds-jarak">
                <div v-for="j in JARAK" :key="j" class="ds-jarak__baris">
                    <span class="ds-jarak__batang" :style="{ width: `var(${j})` }" />
                    <code>{{ j.replace('--hcis-space-', '') }}</code>
                    <span class="hcis-type-caption">{{ nilai(j) }}</span>
                </div>
            </div>
        </section>

        <!-- ── Gerak ───────────────────────────────────────────────────── -->
        <section class="ds-blok">
            <h2 class="hcis-type-title">Gerak</h2>
            <p class="hcis-type-body ds-blok__pengantar">
                Setiap keadaan interaktif punya transisi; tidak ada perubahan rupa yang
                loncat. <code>transition: all</code> dilarang — ia ikut menganimasikan
                properti yang memicu tata letak ulang.
            </p>
            <div class="ds-gerak">
                <div v-for="d in DURASI" :key="d.token" class="ds-gerak__baris">
                    <code>{{ d.token.replace('--hcis-', '') }}</code>
                    <span class="hcis-type-caption">{{ nilai(d.token) }} · {{ d.guna }}</span>
                </div>
            </div>
            <p class="hcis-type-caption ds-blok__pengantar">
                Pengguna yang menyalakan "kurangi animasi" tetap mendapat pudar, tapi
                geseran dimatikan. Satu pengecualian sengaja: kickback tombol saat ditekan —
                ia umpan balik, bukan hiasan, dan terjadi tepat di bawah jari pengguna.
            </p>
        </section>

        <DsMotionBanner />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { HcisIcon } from '@/components/ui';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';

defineOptions({ name: 'DesignSystemFoundations' });

const akar = ref(null);
const tema = ref('baku');
/*
 * Nilai token dibaca lewat getComputedStyle, yang bukan sumber reaktif. Penanda
 * ini yang memaksa render ulang setelah tema berganti — tanpanya, angka-angka
 * di halaman akan tetap menampilkan tema sebelumnya sementara warnanya sudah
 * berpindah, dan itu justru lebih menyesatkan daripada tidak menampilkannya.
 */
const versi = ref(0);

const DAFTAR_TEMA = [
    { kunci: 'baku', nama: 'Indigo (baku)', contoh: '#6366f1' },
    { kunci: 'laut', nama: 'Laut', contoh: '#2563eb' },
    { kunci: 'hutan', nama: 'Hutan', contoh: '#0f766e' },
];

const RAMP_PERAN = [
    { nama: 'Merek', awalan: 'brand', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] },
    { nama: 'Berhasil', awalan: 'success', langkah: [50, 100, 200, 300, 500, 600, 700, 800, 900] },
    { nama: 'Peringatan', awalan: 'warning', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Bahaya', awalan: 'danger', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Informasi', awalan: 'info', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Netral', awalan: 'neutral', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
];

const RAMP_PRIMITIF = [
    { nama: 'Indigo', awalan: 'indigo', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] },
    { nama: 'Slate', awalan: 'slate', langkah: [50, 100, 200, 300, 400, 450, 500, 600, 700, 800, 900] },
    { nama: 'Emerald', awalan: 'emerald', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Amber', awalan: 'amber', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Red', awalan: 'red', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
    { nama: 'Cyan', awalan: 'cyan', langkah: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] },
];

const PERAN_TUNGGAL = [
    { nama: '--hcis-surface', guna: 'permukaan kartu dan panel (#ffffff)' },
    { nama: '--hcis-surface-soft', guna: 'latar halaman (Slate-50 #f8fafc)' },
    { nama: '--hcis-surface-brand', guna: 'latar sorot bernuansa merek' },
    { nama: '--hcis-text', guna: 'teks isi ramah mata (Slate-700 #334155)' },
    { nama: '--hcis-text-strong', guna: 'judul & penekanan utama (Slate-900 #0f172a)' },
    { nama: '--hcis-text-muted', guna: 'keterangan pendukung (Slate-500 #64748b)' },
    { nama: '--hcis-text-subtle', guna: 'teks mikro/caption (Slate-400 #94a3b8)' },
    { nama: '--hcis-border', guna: 'pemisah struktur & kartu (lembut, Slate-200 #e2e8f0)' },
    { nama: '--hcis-border-strong', guna: 'batas kontrol form & input (rapi, Slate-300 #cbd5e1)' },
    { nama: '--hcis-ring', guna: 'garis fokus halo (Brand-500 / alpha)' },
    { nama: '--hcis-scrim', guna: 'tirai di belakang dialog' },
];

const UTANG = [
    '--hcis-green-600', '--hcis-green-700', '--hcis-yellow-200', '--hcis-yellow-700',
    '--hcis-rose-500', '--hcis-rose-700', '--hcis-sky-500', '--hcis-sky-600',
    '--hcis-purple-500', '--hcis-purple-600', '--hcis-violet-400', '--hcis-violet-600',
    '--hcis-orange-600', '--hcis-pink-500', '--hcis-blue-500', '--hcis-blue-600',
    '--hcis-zinc-50',
];

const PERAN_TIPOGRAFI = [
    { kelas: 'hcis-type-display', contoh: 'Judul halaman', guna: '32px / 700 — satu per halaman' },
    { kelas: 'hcis-type-title', contoh: 'Judul bagian', guna: '24px / 700' },
    { kelas: 'hcis-type-heading', contoh: 'Judul kartu', guna: '18px / 600' },
    { kelas: 'hcis-type-subheading', contoh: 'Sub-judul', guna: '16px / 600' },
    { kelas: 'hcis-type-body', contoh: 'Teks isi — ukuran baku antarmuka ini.', guna: '14px / 400' },
    { kelas: 'hcis-type-body-lg', contoh: 'Teks isi lapang untuk paragraf panjang.', guna: '16px / 400' },
    { kelas: 'hcis-type-caption', contoh: 'Keterangan pendukung', guna: '12px / 400' },
    { kelas: 'hcis-type-overline', contoh: 'Label kelompok', guna: '11px / 600 kapital' },
];

const BOBOT = [
    { token: '--hcis-weight-regular', guna: 'teks isi' },
    { token: '--hcis-weight-medium', guna: 'tombol, label isian' },
    { token: '--hcis-weight-bold', guna: 'kepala tabel, judul kartu' },
    { token: '--hcis-weight-black', guna: 'judul halaman saja' },
];

const RADIUS = [
    { token: '--hcis-radius-xs', guna: '4px — checkbox, mini tags' },
    { token: '--hcis-radius-sm', guna: '6px — lencana kecil, tooltip' },
    { token: '--hcis-radius-md', guna: '10px — tombol, input, dropdown' },
    { token: '--hcis-radius-lg', guna: '14px — inner panel, filter bar, stat card' },
    { token: '--hcis-radius-xl', guna: '18px — kartu utama, dialog' },
    { token: '--hcis-radius-2xl', guna: '24px — hero banner, modal besar' },
    { token: '--hcis-radius-pill', guna: '9999px — lencana, pil status' },
];

const BAYANGAN = [
    { token: '--hcis-shadow-inset', nama: 'inset', guna: 'Level -1: Isian form & kotak input' },
    { token: '--hcis-shadow-xs', nama: 'xs', guna: 'Level 0: Lencana, chip, tombol resting' },
    { token: '--hcis-shadow-sm', nama: 'sm', guna: 'Level 1: Tombol sekunder, item toolbar' },
    { token: '--hcis-shadow-md', nama: 'md', guna: 'Level 2: Panel mengapung, filter bar' },
    { token: '--hcis-shadow-card', nama: 'card', guna: 'Level 2: Kartu dashboard & metrik' },
    { token: '--hcis-shadow-card-hover', nama: 'card-hover', guna: 'Level 3: Kartu saat disorot kursor' },
    { token: '--hcis-shadow-lg', nama: 'lg', guna: 'Level 3: Floating bar & popover kecil' },
    { token: '--hcis-shadow-popover', nama: 'popover', guna: 'Level 4: Dropdown menu & DatePicker' },
    { token: '--hcis-shadow-xl', nama: 'xl', guna: 'Level 5: Side drawer & panel samping' },
    { token: '--hcis-shadow-2xl', nama: '2xl', guna: 'Level 6: Modal dialog & alert window' },
];

const JARAK = [
    '--hcis-space-1', '--hcis-space-2', '--hcis-space-3', '--hcis-space-4',
    '--hcis-space-5', '--hcis-space-6', '--hcis-space-8', '--hcis-space-10',
    '--hcis-space-12', '--hcis-space-16',
];

const DURASI = [
    { token: '--hcis-duration-press', guna: 'perubahan di bawah jari — :active, centang' },
    { token: '--hcis-duration-fast', guna: 'sifat berubah di tempat — hover, fokus, warna, batas' },
    { token: '--hcis-duration-panel', guna: 'sesuatu muncul atau lenyap — dropdown, tooltip, toast' },
    { token: '--hcis-duration-move', guna: 'sesuatu menempuh jarak — penanda tab, laci, angkat kartu' },
    { token: '--hcis-ease', guna: 'kurva baku' },
    { token: '--hcis-ease-spring', guna: 'hanya untuk lepasan tombol' },
];

function nilai(nama) {
    versi.value;
    if (!akar.value) return '';
    return getComputedStyle(akar.value).getPropertyValue(nama).trim();
}

async function gantiTema(kunci) {
    tema.value = kunci;
    await nextTick();
    versi.value += 1;
}

/** Luminans relatif menurut WCAG 2.x. */
function luminans(r, g, b) {
    const kanal = [r, g, b].map((v) => {
        const n = v / 255;
        return n <= 0.03928 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * kanal[0] + 0.7152 * kanal[1] + 0.0722 * kanal[2];
}

function uraikan(warna) {
    const m = warna.match(/(\d{1,3})\D+(\d{1,3})\D+(\d{1,3})/);
    return m ? [+m[1], +m[2], +m[3]] : null;
}

const kontrasBrand = computed(() => {
    versi.value;
    const rgb = uraikan(nilai('--hcis-brand-600'));
    if (!rgb) return 0;
    const l = luminans(...rgb);
    return (1.05) / (l + 0.05);
});

const kontrasLolos = computed(() => kontrasBrand.value >= 4.5);

onMounted(() => {
    versi.value += 1;
});
</script>

<style scoped>
.ds-fondasi {
    /* Lebar penuh — plafon 1100px dibuang. Paragraf panjang di halaman ini
       tetap dibatasi 68ch/72ch di aturannya masing-masing; itu ukuran BARIS
       BACA, bukan lebar halaman, dan cuma berlaku pada teks yang memang
       dibaca berurutan. */
    width: 100%;
    padding: var(--hcis-space-8) var(--hcis-space-4) var(--hcis-space-16);
    color: var(--hcis-text);
    font-family: var(--hcis-font-body);
    background: var(--hcis-surface-soft);
}

.ds-fondasi__kepala {
    margin-bottom: var(--hcis-space-10);
}

.ds-fondasi__kembali {
    display: inline-block;
    margin-bottom: var(--hcis-space-4);
    color: var(--hcis-text-brand);
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-medium);
    text-decoration: none;
    transition: color var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-fondasi__kembali:hover {
    color: var(--hcis-brand-700);
}

.ds-fondasi__intro {
    max-width: 68ch;
    margin-top: var(--hcis-space-3);
}

.ds-fondasi__catatan {
    max-width: 68ch;
    margin-top: var(--hcis-space-2);
}

.ds-blok {
    margin-bottom: var(--hcis-space-12);
    padding: var(--hcis-space-6);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    box-shadow: var(--hcis-shadow-xs);
}

.ds-blok__sub {
    margin-top: var(--hcis-space-8);
    margin-bottom: var(--hcis-space-2);
}

.ds-blok__pengantar {
    max-width: 72ch;
    margin-top: var(--hcis-space-2);
    margin-bottom: var(--hcis-space-4);
}

/* ── Tema ────────────────────────────────────────────────────────────── */
.ds-tema {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
    margin-bottom: var(--hcis-space-4);
}

.ds-tema__tombol {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-2) var(--hcis-space-4);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-strong);
    border-radius: var(--hcis-radius-md);
    color: var(--hcis-text);
    font-family: inherit;
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-medium);
    cursor: pointer;
    transition:
        background var(--hcis-duration-fast) var(--hcis-ease),
        border-color var(--hcis-duration-fast) var(--hcis-ease),
        color var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-tema__tombol:hover {
    background: var(--hcis-surface-brand);
}

.ds-tema__tombol.is-aktif {
    background: var(--hcis-brand-600);
    border-color: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
}

.ds-tema__titik {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: var(--hcis-radius-pill);
}

.ds-tema__panggung {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-5);
    background: var(--hcis-surface-brand);
    border-radius: var(--hcis-radius-lg);
}

.ds-tema__kotak {
    width: 3rem;
    height: 3rem;
    background: var(--hcis-gradient-brand);
    border-radius: var(--hcis-radius-md);
    box-shadow: var(--hcis-shadow-glow);
}

.ds-tema__kontras {
    margin-top: var(--hcis-space-3);
}

.ds-tema__kontras.is-gagal {
    color: var(--hcis-danger-600);
    font-weight: var(--hcis-weight-bold);
}

/* ── Ramp warna ──────────────────────────────────────────────────────── */
.ds-ramp {
    margin-bottom: var(--hcis-space-5);
}

.ds-ramp__nama {
    margin-bottom: var(--hcis-space-2);
}

.ds-ramp__deret {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
}

.ds-swatch {
    min-width: 4.5rem;
}

.ds-swatch__warna {
    height: 3rem;
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-sm);
    transition: background var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-swatch__label {
    margin-top: var(--hcis-space-1);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-text);
}

.ds-swatch__nilai {
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-muted);
}

/* ── Peran tunggal ───────────────────────────────────────────────────── */
.ds-peran {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
}

.ds-peran__baris {
    display: grid;
    grid-template-columns: 1.5rem 15rem 9rem 1fr;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-2);
    border-radius: var(--hcis-radius-sm);
    transition: background var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-peran__baris:hover {
    background: var(--hcis-surface-soft);
}

.ds-peran__contoh {
    width: 1.5rem;
    height: 1.5rem;
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-sm);
}

.ds-peran__token,
.ds-peran__nilai {
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
}

.ds-peran__nilai {
    color: var(--hcis-text-muted);
}

/* ── Utang ───────────────────────────────────────────────────────────── */
.ds-utang {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
}

.ds-utang__butir {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-1) var(--hcis-space-3);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-pill);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-2xs);
}

.ds-utang__contoh {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: var(--hcis-radius-pill);
}

/* ── Tipografi ───────────────────────────────────────────────────────── */
.ds-tipe__baris {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-3) 0;
    border-bottom: 1px solid var(--hcis-border);
}

.ds-tipe__baris:last-child {
    border-bottom: none;
}

.ds-tipe__meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--hcis-space-1);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
}

.ds-bobot {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-6);
}

.ds-bobot__butir {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
}

.ds-bobot__contoh {
    font-family: var(--hcis-font-body);
    font-size: var(--hcis-font-size-xl);
    color: var(--hcis-text-strong);
}

/* ── Bentuk ──────────────────────────────────────────────────────────── */
.ds-bentuk {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-6);
}

.ds-bentuk__butir {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
}

.ds-bentuk__kotak {
    width: 4rem;
    height: 4rem;
    background: var(--hcis-brand-100);
    border: 1px solid var(--hcis-brand-300);
}

.ds-bentuk__kotak--polos {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-md);
}

.ds-jarak__baris {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-1) 0;
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
}

.ds-jarak__batang {
    height: 0.75rem;
    background: var(--hcis-brand-400);
    border-radius: var(--hcis-radius-sm);
}

/* ── Gerak ───────────────────────────────────────────────────────────── */
.ds-gerak__baris {
    display: flex;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-1) 0;
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
}

.ds-kode {
    padding: var(--hcis-space-4);
    background: var(--hcis-slate-900);
    border-radius: var(--hcis-radius-md);
    color: var(--hcis-slate-100);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
    line-height: var(--hcis-line-normal);
    overflow-x: auto;
}

code {
    font-family: var(--hcis-font-mono);
    font-size: 0.95em;
}
</style>
