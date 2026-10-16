<template>
    <main class="pa-setup-page">
        <!-- Unified HCIS Header -->
        <HcisHeader
            :title="keadaan?.cycle?.nama || 'Cycle Setup Wizard'"
            kicker="PERFORMANCE APPRAISAL · CYCLE SETUP WIZARD"
            :subtitle="subjudul"
            icon="clipboard2-check-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-4"
        >
            <template #actions>
                <div class="pa-setup-header-actions">
                    <HcisButton
                        href="/performance-appraisal"
                        label="Assessment Cycle"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                    />
                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        @click="muat"
                    />
                </div>
            </template>

            <template #console>
                <div class="pa-setup-console">
                    <div class="pa-setup-console__status">
                        <HcisBadge
                            v-if="keadaan?.cycle"
                            :label="keadaan.cycle.status || 'DRAFT'"
                            :status="keadaan.cycle.status"
                            variant="brand"
                            appearance="glass"
                            size="sm"
                            dot
                        />
                        <span class="pa-setup-console__cycle-info">
                            Code: <strong>{{ keadaan?.cycle?.kode || '—' }}</strong> &bull;
                            Year: <strong>{{ keadaan?.cycle?.tahun || '—' }}</strong>
                        </span>
                    </div>

                    <div v-if="keadaan" class="pa-setup-console__progress">
                        <div class="pa-setup-progress-text">
                            <span>Setup Progress</span>
                            <strong>{{ keadaan.selesai }} / {{ keadaan.total }} Steps Completed</strong>
                        </div>
                        <div class="pa-setup-progress-track">
                            <div
                                class="pa-setup-progress-fill"
                                :style="{ width: `${Math.round((keadaan.selesai / (keadaan.total || 1)) * 100)}%` }"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </HcisHeader>

        <!-- Error State -->
        <HcisResultState
            v-if="errorMuat && !memuat"
            status="danger"
            variant="glass"
            layout="compact"
            title="Setup cycle tidak dapat dimuat"
            :description="errorMuat"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <!-- Loaded State -->
        <template v-else-if="keadaan">
            <!-- Stepper Progress Cards -->
            <section class="pa-setup-stepper-section" aria-label="Tahapan Setup Cycle">
                <HcisSteps
                    :steps="langkahUntukPenunjuk"
                    :current="indeksAktif"
                    variant="cards"
                    size="sm"
                    clickable
                    sentiment="brand"
                    @change="onStepChange"
                    @click-step="onStepChange"
                />
            </section>

            <!-- Global Blockers / Readiness Banner -->
            <transition name="fade">
                <div v-if="keadaan.penghalang && keadaan.penghalang.length" class="pa-setup-blockers-wrap">
                    <HcisAlert
                        variant="warning"
                        appearance="glass"
                        :title="`Setup Cycle Belum Siap Dijalankan (${keadaan.penghalang.length} Hal Perlu Diselesaikan)`"
                        icon="exclamation-triangle-fill"
                    >
                        <div class="pa-setup-blockers-content">
                            <p class="pa-setup-blockers-desc">
                                Lengkapi langkah-langkah di bawah ini sebelum mengeksekusi pembentukan peserta pada Langkah 4:
                            </p>
                            <ul class="pa-setup-blockers-list">
                                <li v-for="(p, i) in keadaan.penghalang" :key="i" class="pa-setup-blocker-item">
                                    <HcisIcon name="dash-circle-fill" size="2xs" color="warning" />
                                    <span>{{ p }}</span>
                                    <button
                                        v-if="resolveBlockerStep(p)"
                                        type="button"
                                        class="pa-setup-blocker-link"
                                        @click="pilihLangkah(resolveBlockerStep(p))"
                                    >
                                        Buka Langkah Ini &rarr;
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </HcisAlert>
                </div>

                <!--
                 | SPANDUK "SUDAH LENGKAP & VALID" DICABUT 6 September 2026.
                 |
                 | Ia muncul di SETIAP langkah dan mengulang apa yang sudah dikatakan kartu
                 | stepper di atasnya: lima langkah bercentang hijau. Yang tersisa di sini
                 | hanya daftar penghalang — itu memang tidak ada di tempat lain, dan tiap
                 | butirnya pekerjaan yang harus dikerjakan.
                 -->
            </transition>

            <!--
             | SATU-SATUNYA MODE: satu langkah aktif.
             |
             | Mode "Tampilkan Semua Sekaligus" dicabut 6 September 2026 bersama deret tab
             | bernomor di atasnya. Menampilkan lima langkah sekaligus meniadakan gunanya
             | urutan yang baru saja ditegakkan: admin menggulir ke langkah 4 dan mengisinya
             | sebelum langkah 2 ada isinya, dan penjaga urutan di server hanya menahan
             | PERPINDAHAN posisi, bukan guliran layar.
             -->
            <section class="pa-setup-body" aria-label="Konten Langkah Penyiapan">
                <PaStepBobotPilar
                    v-if="langkahAktif === 'BOBOT_PILAR'"
                    :key="`step-bobot-${cycleId}`"
                    :cycle-id="cycleId"
                    :dapat-disunting="bolehSunting('BOBOT_PILAR')"
                    :alasan-terkunci="alasanTerkunci('BOBOT_PILAR')"
                    @tersimpan="muat"
                    @lanjut="pilihLangkah('PAKET_SOAL')"
                />

                <PaStepPaketSoalMatriks
                    v-else-if="langkahAktif === 'PAKET_SOAL'"
                    :key="`step-paket-${cycleId}`"
                    :cycle-id="cycleId"
                    :dapat-disunting="bolehSunting('PAKET_SOAL')"
                    :alasan-terkunci="alasanTerkunci('PAKET_SOAL')"
                    @tersimpan="muat"
                    @kembali="pilihLangkah('BOBOT_PILAR')"
                    @lanjut="pilihLangkah('ATURAN_PENILAI')"
                />

                <PaStepAturanPenilai
                    v-else-if="langkahAktif === 'ATURAN_PENILAI'"
                    :key="`step-aturan-${cycleId}`"
                    :cycle-id="cycleId"
                    :dapat-disunting="bolehSunting('ATURAN_PENILAI')"
                    :alasan-terkunci="alasanTerkunci('ATURAN_PENILAI')"
                    @tersimpan="muat"
                    @kembali="pilihLangkah('PAKET_SOAL')"
                    @lanjut="pilihLangkah('PESERTA')"
                />

                <PaStepPeserta
                    :bisa-kelola-relasi="bisaKelolaRelasi"
                    v-else-if="langkahAktif === 'PESERTA'"
                    :key="`step-peserta-${cycleId}`"
                    :cycle-id="cycleId"
                    :dapat-disunting="bolehSunting('PESERTA')"
                    :boleh-kecualikan="kemampuan('PESERTA', 'kecualikan_peserta')"
                    :alasan-terkunci="alasanTerkunci('PESERTA')"
                    :siap-dijalankan="keadaan.siap_dijalankan"
                    :penghalang="keadaan.penghalang || []"
                    @tersimpan="muat"
                    @kembali="pilihLangkah('ATURAN_PENILAI')"
                    @selesai="onPenyiapanSelesai"
                    @lanjut="pilihLangkah('PENUGASAN')"
                />

                <!--
                    Langkah 5 TERPISAH dari Peserta, dan itu bukan pilihan tata letak: baris
                    penugasan baru ada sesudah penyiapan berjalan. Digabung, separuh isinya
                    kosong sampai tombolnya ditekan lalu berubah mendadak — dan admin tak
                    punya cara tahu masih ada pekerjaan sesudahnya.
                -->
                <PaStepPenugasan
                    :bisa-kelola-relasi="bisaKelolaRelasi"
                    v-else-if="langkahAktif === 'PENUGASAN'"
                    :cycle-id="cycleId"
                    :dapat-disunting="bolehSunting('PENUGASAN')"
                    :boleh-ubah-penilai="kemampuan('PENUGASAN', 'ubah_penilai')"
                    :alasan-terkunci="alasanTerkunci('PENUGASAN')"
                    @tersimpan="muat"
                    @kembali="pilihLangkah('PESERTA')"
                    @selesai="keDaftarSiklus"
                    @kesiapan="(v) => (siapDikunciPenugasan = v)"
                />
            </section>

        </template>

        <!-- Floating Toast Notification -->
        <transition name="toast">
            <div
                v-if="notifikasi.message"
                class="pa-setup-toast"
                :class="`pa-setup-toast--${notifikasi.type}`"
                role="status"
                aria-live="polite"
            >
                <HcisIcon
                    :name="notifikasi.type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'"
                    size="xs"
                />
                <div class="pa-setup-toast__text">
                    <strong>{{ notifikasi.type === 'success' ? 'Sukses' : 'Pemberitahuan' }}</strong>
                    <span>{{ notifikasi.message }}</span>
                </div>
                <button
                    type="button"
                    class="pa-setup-toast__close"
                    aria-label="Tutup pemberitahuan"
                    @click="notifikasi.message = ''"
                >
                    <HcisIcon name="x-lg" size="2xs" />
                </button>
            </div>
        </transition>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSteps,
    hcisMessage,
} from '@/components/ui';
import paApi from '../api/paApi.js';
import PaStepAturanPenilai from './components/PaStepAturanPenilai.vue';
import PaStepBobotPilar from './components/PaStepBobotPilar.vue';
import PaStepPaketSoalMatriks from './components/PaStepPaketSoalMatriks.vue';
import PaStepPenugasan from './components/PaStepPenugasan.vue';
import PaStepPeserta from './components/PaStepPeserta.vue';

defineOptions({ name: 'PerformanceAppraisalPenyiapanPage' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },

    /* Apakah admin ini boleh membuka master relasi organisasi. Diputuskan di SERVER, bukan
       ditebak di sini: layar yang menebak akan menampilkan tautan yang berujung 403, dan itu
       lebih buruk daripada tidak ada tautan — ia menyalahkan penggunanya atas hak yang memang
       tidak pernah ia punya. */
    bisaKelolaRelasi: { type: Boolean, default: false },
});

const cycleId = computed(() => Number(props.cycleId));

const keadaan = ref(null);
/*
    KOSONG, bukan 'BOBOT_PILAR'.

    Nilai awal yang terisi membuat rantai `||` di bawah tidak pernah sampai ke
    `keadaan.langkah_aktif` — nilai lokal selalu truthy, jadi posisi yang DIKIRIM SERVER
    diabaikan selamanya. Akibatnya halaman ini selalu membuka Langkah 1, walau admin
    terakhir berada di Langkah 3 dan posisinya tersimpan benar di database.

    Penjaga penyelarasan di `muat()` pun mati karena alasan yang sama: `!langkahAktifLocal`
    tidak pernah benar.

    Kosong berarti: server menentukan sampai admin benar-benar berpindah sendiri.
*/
const langkahAktifLocal = ref('');
const memuat = ref(false);
const errorMuat = ref('');
const notifikasi = reactive({ type: 'success', message: '' });

/*
    Urutannya MENGIKAT: pilihan lokal admin dulu (perpindahan yang baru ia lakukan), lalu
    posisi yang dikirim server, baru langkah pertama sebagai jaring terakhir.

    Jangan menyimpulkan posisi dari kelengkapan isi. Layar yang menebak akan melempar admin
    ke langkah kosong paling depan setiap kali ia menyimpan — termasuk saat ia sengaja
    melewati satu langkah untuk kembali nanti.
*/
const langkahAktif = computed(
    () => langkahAktifLocal.value || keadaan.value?.langkah_aktif || 'BOBOT_PILAR',
);

const STEP_META = {
    BOBOT_PILAR: {
        nama: 'Pillar Weights',
        ringkas: 'Distribusi bobot KPI vs Kompetensi per level jabatan.',
        icon: 'sliders2',
    },
    PAKET_SOAL: {
        nama: 'Question Packages',
        ringkas: 'Pemetaan paket soal evaluasi per level & kategori rater.',
        icon: 'collection',
    },
    ATURAN_PENILAI: {
        nama: 'Rater Rules',
        ringkas: 'Matriks aturan rater 360 multi-rater per level jabatan.',
        icon: 'diagram-3',
    },
    PESERTA: {
        nama: 'Participants',
        ringkas: 'Simulasi daftar peserta, pengecualian, dan eksekusi resmi.',
        icon: 'people-fill',
    },
    PENUGASAN: {
        nama: 'Rater Assignment',
        ringkas: 'Verifikasi kesehatan penugasan 360 dan penguncian cycle.',
        icon: 'person-check',
    },
};

const daftarLangkahLengkap = computed(() => {
    return keadaan.value?.langkah ?? [
        { kode: 'BOBOT_PILAR', nama: 'Pillar Weights', status: 'BELUM', boleh_disunting: true, ringkas: 'Distribusi bobot KPI vs Kompetensi per level jabatan.' },
        { kode: 'PAKET_SOAL', nama: 'Question Packages', status: 'BELUM', boleh_disunting: true, ringkas: 'Pemetaan paket soal evaluasi per level & kategori rater.' },
        { kode: 'ATURAN_PENILAI', nama: 'Rater Rules', status: 'BELUM', boleh_disunting: true, ringkas: 'Matriks aturan rater 360 multi-rater per level jabatan.' },
        { kode: 'PESERTA', nama: 'Participants', status: 'BELUM', boleh_disunting: true, ringkas: 'Simulasi daftar peserta, pengecualian, dan eksekusi resmi.' },
    ];
});

const indeksAktif = computed(() => {
    const i = daftarLangkahLengkap.value.findIndex((l) => l.kode === langkahAktif.value);
    return i < 0 ? 0 : i;
});

const subjudul = computed(() => {
    if (!keadaan.value) return 'Memuat status wizard penyiapan…';
    const { selesai, total, cycle } = keadaan.value;
    return `Cycle ${cycle.nama || cycle.kode} (${cycle.tahun}) · ${selesai} of ${total} configuration steps completed.`;
});

/**
 * Petakan langkah untuk HcisSteps cards variant.
 */
const langkahUntukPenunjuk = computed(() => {
    return daftarLangkahLengkap.value.map((l) => {
        const isCurrent = l.kode === langkahAktif.value;
        const isDone = l.status === 'SELESAI';
        const isDraft = l.status === 'DRAFT';
        const meta = STEP_META[l.kode] || {};

        return {
            kode: l.kode,
            label: meta.nama || l.nama,
            title: meta.nama || l.nama,
            description: meta.ringkas || l.ringkasan || l.ringkas,
            icon: meta.icon || l.ikon || getStepDefaultIcon(l.kode),
            status: labelStatus(l),
            state: isCurrent ? 'current' : (perluPenyesuaian(l) ? 'warning' : (isDone ? 'done' : (isDraft ? 'warning' : 'todo'))),

            /* Langkah yang gilirannya belum tiba ditampilkan MATI, bukan sekadar ditolak
               saat diklik. Kartu yang terlihat bisa diklik lalu memunculkan peringatan
               mengajari orang bahwa peringatan itu wajar — dan mereka berhenti membacanya. */
            disabled: l.dapat_dibuka === false,
        };
    });
});

function getStepDefaultIcon(kode) {
    if (kode === 'BOBOT_PILAR') return 'sliders2';
    if (kode === 'PAKET_SOAL') return 'collection';
    if (kode === 'ATURAN_PENILAI') return 'diagram-3';
    if (kode === 'PESERTA') return 'people-fill';
    return 'check2-circle';
}

/* Langkah 5 "selesai" di server berarti semua peserta sudah berpenilai — belum tentu siap
   dikunci (kuota, penilai resign, HC Learn). Kesiapannya dilaporkan langkah itu sendiri;
   selama belum siap, kartunya tidak boleh berbunyi "Completed". */
const siapDikunciPenugasan = ref(null);

function perluPenyesuaian(l) {
    return l.kode === 'PENUGASAN' && l.status === 'SELESAI' && siapDikunciPenugasan.value === false;
}

function labelStatus(l) {
    if (perluPenyesuaian(l)) return 'Perlu Penyesuaian';
    if (l.status === 'SELESAI') return 'Completed';
    if (l.status === 'DRAFT') return 'Draft';

    /* "Belum Giliran" DIBEDAKAN dari "Terkunci", karena sebab dan jalan keluarnya berbeda:
       yang pertama menunggu langkah sebelumnya selesai, yang kedua menunggu status siklus.
       Satu kata untuk dua keadaan membuat admin mencari jalan keluar yang salah. */
    if (l.dapat_dibuka === false) return 'Pending Step';

    return l.boleh_disunting ? 'In Progress' : 'Locked';
}

function resolveBlockerStep(blockerText) {
    const text = String(blockerText || '').toLowerCase();
    if (text.includes('bobot')) return 'BOBOT_PILAR';
    if (text.includes('paket') || text.includes('soal')) return 'PAKET_SOAL';
    if (text.includes('aturan') || text.includes('penilai') || text.includes('rater')) return 'ATURAN_PENILAI';
    if (text.includes('peserta') || text.includes('tertunda')) return 'PESERTA';
    return null;
}

function cari(kode) {
    return (keadaan.value?.langkah ?? []).find((l) => l.kode === kode) ?? null;
}

function bolehSunting(kode) {
    const l = cari(kode);

    if (!l) return true;

    /* Langkah yang belum tiba gilirannya juga tidak boleh disunting. Aturannya milik
       server — di sini keduanya hanya digabung supaya tiap panel tidak perlu memeriksa dua
       medan dan lupa salah satunya. */
    return (l.boleh_disunting ?? true) && (l.dapat_dibuka ?? true);
}

/* Mengganti SATU penilai tetap boleh sesudah siklus dikunci, dan itu keputusan server —
   `TAMBAH_PENILAI` memang mengizinkan LOCKED dan OPEN. Layar yang menyamakannya dengan
   `boleh_disunting` mengubah siklus terkunci jadi jalan buntu: kesiapan HC Learn menyebut
   penilai yang sudah resign, dan tak ada tombol yang bisa ditekan untuk membereskannya. */
function kemampuan(kode, nama) {
    const l = cari(kode);

    if (!l) return false;

    /* Kunci yang TIDAK ADA jatuh ke `boleh_disunting` — perilaku lama, supaya langkah yang
       memang tidak punya gerbang tambahan tidak berubah diam-diam. */
    const nilai = l.kemampuan?.[nama];

    return (nilai ?? l.boleh_disunting ?? true) && (l.dapat_dibuka ?? true);
}

function alasanTerkunci(kode) {
    const l = cari(kode);

    if (!l) return '';

    // Sebab urutan didahulukan: itu yang bisa segera dikerjakan admin.
    return l.dapat_dibuka === false
        ? (l.alasan_urutan || '')
        : (l.alasan_terkunci || '');
}

function onStepChange(payload) {
    const kode = payload?.step?.kode || payload?.step?.code || payload?.kode || (payload?.index !== undefined ? daftarLangkahLengkap.value[payload.index]?.kode : null);
    if (kode) pilihLangkah(kode);
}

function pilihLangkah(kode) {
    if (!kode) return;

    /* Lompatan ke depan ditahan di sini SUPAYA TIDAK MENGEJUTKAN, bukan supaya aman:
       penjaga sungguhannya ada di server (`pastikanGilirannyaTiba`), dan layar yang
       menahannya sendiri tetap bisa dilewati permintaan langsung. */
    const tujuan = cari(kode);

    if (tujuan && tujuan.dapat_dibuka === false) {
        hcisMessage.warning(tujuan.alasan_urutan || 'Langkah ini belum tiba gilirannya.');

        return;
    }
    pindah(kode);
}

/** Keluar dari wizard, balik ke daftar siklus. */
function keDaftarSiklus() {
    window.location.href = '/performance-appraisal';
}

async function muat() {
    memuat.value = true;
    errorMuat.value = '';

    try {
        const response = await paApi.keadaanSetup(cycleId.value);
        keadaan.value = response?.result ?? response?.data ?? response;
        /* Posisi server TIDAK disalin ke state lokal.

           Menyalinnya membuat `langkahAktifLocal` terisi, dan sejak itu setiap pemuatan
           ulang mengabaikan posisi server — kembali ke cacat yang sama, hanya satu putaran
           lebih lambat. `langkahAktif` sudah membaca keduanya dengan urutan yang benar;
           lokal cukup dibiarkan kosong sampai admin berpindah sendiri. */
    } catch (e) {
        errorMuat.value = e?.message || 'Gagal memuat keadaan penyiapan.';
    } finally {
        memuat.value = false;
    }
}

async function pindah(kode) {
    if (!kode) return;
    langkahAktifLocal.value = kode;

    try {
        const response = await paApi.pindahLangkahSetup(cycleId.value, kode);
        const data = response?.result ?? response?.data ?? response;
        if (data && data.langkah) {
            keadaan.value = data;
        }
    } catch {
        // Optimistic switch tetap berlaku di frontend
    }
}

async function onPenyiapanSelesai(info = {}) {
    const diperbarui = Number(info?.levelDiperbarui || 0);
    notifikasi.type = 'success';
    notifikasi.message = 'Penyiapan berhasil dijalankan. Peserta sudah terbentuk; lanjutkan dengan membentuk penugasan penilai.'
        + (diperbarui > 0 ? ` ${diperbarui} peserta disegarkan ke Level PA terbaru.` : '');

    /* PINDAH SENDIRI KE LANGKAH 5.
     |
     | Sesudah penyiapan berjalan, langkah 4 tidak menyisakan pekerjaan apa pun: peserta
     | sudah terbentuk, dan yang berikutnya adalah MEMERIKSA hasil penerapan aturannya per
     | orang. Membiarkan admin berdiri di layar yang sudah selesai membuatnya menebak apakah
     | masih ada yang harus ditekan di sini.
     |
     | `muat()` ditunggu lebih dulu, dan itu bukan kerapian: penjaga urutan menolak
     | perpindahan ke langkah yang pendahulunya belum SELESAI, dan status PESERTA baru
     | berubah menjadi SELESAI di kiriman berikutnya. Memindah lebih dulu berarti ditolak
     | penjaga yang justru sedang bekerja dengan benar.
     */
    await muat();

    pilihLangkah('PENUGASAN');
}

/* LANGKAH BISA DITUNJUK DARI LUAR lewat `?langkah=`.
 |
 | Tanpa ini, tiap tautan "perbaiki di Langkah 5" mendarat di langkah mana pun yang terakhir
 | dibuka admin, dan ia harus menebak sendiri harus ke mana — persis keluhan yang melahirkan
 | tautan itu. Nilainya DISARING terhadap daftar langkah yang sungguh ada, supaya alamat yang
 | dikarang tidak menaruh wizard pada langkah yang tidak pernah dirancang.
 |
 | Perpindahannya lewat `pilihLangkah()` yang sama dengan penunjuk di layar, jadi penjaga
 | urutan di server tetap berlaku: langkah yang belum tiba gilirannya tetap ditolak. */
function langkahDariAlamat() {
    const diminta = new URLSearchParams(window.location.search).get('langkah');

    if (!diminta) return '';

    return daftarLangkahLengkap.value.some((l) => l.kode === diminta) ? diminta : '';
}

onMounted(async () => {
    await muat();

    const diminta = langkahDariAlamat();

    if (diminta && diminta !== langkahAktif.value) {
        pilihLangkah(diminta);
    }
});
</script>

<style scoped>
.pa-setup-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
}

.pa-setup-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-setup-console {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    width: 100%;
}

.pa-setup-console__status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-setup-console__cycle-info {
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-setup-console__cycle-info strong {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-setup-console__progress {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 14rem;
}

.pa-setup-progress-text {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-setup-progress-text strong {
    color: var(--hcis-brand-700, #4338ca);
    font-weight: 700;
}

.pa-setup-progress-track {
    height: 6px;
    background: rgba(226, 232, 240, 0.8);
    border-radius: 9999px;
    overflow: hidden;
}

.pa-setup-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #06b6d4);
    border-radius: 9999px;
    transition: width 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-setup-stepper-section {
    min-width: 0;
}

.pa-setup-blockers-wrap {
    margin-top: -0.25rem;
}

.pa-setup-blockers-content {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.35rem;
}

.pa-setup-blockers-desc {
    margin: 0;
    font-size: 0.8rem;
    color: #92400e;
}

.pa-setup-blockers-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-setup-blocker-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: #78350f;
    font-weight: 550;
}

.pa-setup-blocker-link {
    margin-left: 0.5rem;
    background: none;
    border: none;
    color: #b45309;
    font-weight: 700;
    font-size: 0.75rem;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
    transition: color 150ms ease;
}

.pa-setup-blocker-link:hover {
    color: #78350f;
}

.pa-setup-body {
    min-width: 0;
}

.pa-setup-toast {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 100;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    max-width: min(28rem, calc(100vw - 3rem));
}

.pa-setup-toast--success {
    border-left: 4px solid var(--hcis-success, #10b981);
    color: var(--hcis-success, #059669);
}

.pa-setup-toast--error {
    border-left: 4px solid var(--hcis-danger, #ef4444);
    color: var(--hcis-danger, #dc2626);
}

.pa-setup-toast__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-setup-toast__text strong {
    font-size: 0.84rem;
}

.pa-setup-toast__close {
    margin-left: auto;
    background: none;
    border: none;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    padding: 0.1rem;
    display: inline-flex;
}

.pa-setup-toast__close:hover {
    color: var(--hcis-text-strong, #0f172a);
}

/* Animations */
.step-fade-enter-active,
.step-fade-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}

.step-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.step-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.toast-enter-active,
.toast-leave-active {
    transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.96);
}

@media (max-width: 640px) {
    .pa-setup-page {
        padding: 0 0 4rem 0;
        gap: 0.85rem;
    }

    .pa-setup-console {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .pa-setup-console__progress {
        width: 100%;
    }
}
</style>
