<!--
 | KESIAPAN DATA KARYAWAN DI HC LEARN — satu panel, dipakai DUA langkah.
 |
 | KENAPA SATU KOMPONEN, BUKAN DUA SALINAN
 |
 | Panel ini berdiri di Langkah 4 (sesudah peserta dibentuk) DAN di Langkah 5 (sebelum
 | penjadwalan dikirim). Dua salinan yang sama-sama memanggil `checking/karyawan` akan
 | bercabang pada perbaikan pertama — aturan "yang terdaftar tetapi nonaktif TIDAK bisa
 | dibereskan tombol sinkron" persisnya jenis aturan yang di repo ini pernah hidup hanya di
 | satu dari dua jalur setara, dan yang kedua diam-diam menjanjikan sebaliknya.
 |
 | KENAPA DI LANGKAH 5 JUGA, BUKAN CUKUP DI LANGKAH 4
 |
 | Di Langkah 4 penugasan belum terbentuk, jadi penilainya masih PROYEKSI: dugaan dari relasi
 | organisasi. Populasinya masih bisa berubah sesudah pembentukan penugasan dijalankan —
 | karena itu "siap" di Langkah 4 tidak pernah bisa berarti "siap dijadwalkan". Di Langkah 5
 | penugasannya sudah ada dan yang diperiksa adalah orang yang SUNGGUH akan dikirimi pintu,
 | dan di situlah jawabannya mengikat. `ringkasan.penilai_proyeksi` dipakai menyebut perbedaan
 | itu apa adanya, bukan disembunyikan di balik satu angka "terlibat".
 -->
<template>
    <div v-if="kesiapan" class="pa-hcl-card">
        <!-- Header Panel & Summary -->
        <div class="pa-hcl-head">
            <div class="pa-hcl-head-info">
                <span class="pa-eyebrow">
                    <HcisIcon name="shield-check" size="2xs" color="brand" />
                    <span>KESIAPAN DATA KARYAWAN DI HC LEARN</span>
                </span>
                <h4 class="pa-hcl-title">
                    {{ formatNumber(kesiapan.ringkasan?.terlibat || 0) }} orang terlibat siklus ini
                </h4>
                <div class="pa-hcl-summary-pills">
                    <span v-if="rincianAsal" class="pa-hcl-stat-pill">
                        <HcisIcon name="people" size="2xs" />
                        <span>{{ rincianAsal }}</span>
                    </span>
                </div>
            </div>

            <div class="pa-hcl-actions">
                <HcisBadge :label="labelStatus" :variant="nadaStatus" size="xs" dot />
                <HcisBadge
                    v-if="ringkasPenghalang"
                    :label="ringkasPenghalang"
                    :variant="jumlahMenghalangi > 0 ? 'danger' : 'neutral'"
                    appearance="subtle"
                    size="xs"
                />
                <HcisButton
                    label="Periksa Ulang"
                    variant="ghost"
                    size="xs"
                    leading-icon="arrow-clockwise"
                    :loading="memeriksa"
                    @click="muat"
                />
                <HcisButton
                    v-if="jumlahDapatDisinkron > 0"
                    :label="`Sinkronkan ke HC Learn (${jumlahDapatDisinkron})`"
                    variant="primary"
                    size="xs"
                    leading-icon="cloud-upload"
                    :disabled="!dapatDisunting"
                    :loading="menyinkron"
                    @click="sinkronkan"
                />
            </div>
        </div>

        <!-- Alert Ringkasan Catatan Kesiapan -->
        <HcisAlert
            v-if="kesiapan.catatan"
            :variant="jumlahMenghalangi > 0 ? 'warning' : 'info'"
            appearance="subtle"
            size="sm"
            :icon="jumlahMenghalangi > 0 ? 'exclamation-triangle-fill' : 'info-circle-fill'"
            class="pa-hcl-note-alert"
        >
            {{ kesiapan.catatan }}
        </HcisAlert>

        <!--
         | PROYEKSI DI LANGKAH 5 ADALAH PERINGATAN, bukan keterangan.
         |
         | Kalau di sini masih ada penilai proyeksi, penugasannya belum terbentuk seluruhnya:
         | pemeriksaan ini belum menyentuh sebagian orang yang kelak dikirimi pintu.
         -->
        <HcisAlert
            v-if="konteks === 'PENUGASAN' && (kesiapan.ringkasan?.penilai_proyeksi || 0) > 0"
            variant="warning"
            appearance="subtle"
            :title="`${formatNumber(kesiapan.ringkasan.penilai_proyeksi)} penilai masih proyeksi, belum punya penugasan`"
            icon="exclamation-triangle-fill"
        >
            Sebagian peserta belum punya penugasan. Lengkapi di bagian 2 (Pembentukan penugasan),
            lalu periksa ulang — sampai itu, pemeriksaan ini belum mencakup semua orang yang akan
            dikirimi pintu pengisian.
        </HcisAlert>

        <!--
         | DUA DAFTAR, BUKAN SATU. Sampai 13 Okt semua yang ditolak HC Learn berada di bawah satu
         | judul "menahan penguncian siklus" — termasuk baris yang lencananya sendiri berkata
         | "tidak menghalangi". Admin membaca dua kalimat yang saling membantah dan tidak tahu
         | harus berbuat apa. Yang menahan dibuka; yang sekadar tercatat dilipat.
         -->
        <div v-if="barisMenghalangi.length" class="pa-hcl-issues-section" data-uji="hcl-menahan">
            <div class="pa-hcl-issues-header">
                <div class="pa-hcl-issues-title">
                    <HcisIcon name="exclamation-octagon-fill" size="xs" color="danger" />
                    <h5>{{ judulMenahan }}</h5>
                </div>
                <span class="pa-hcl-issues-hint">{{ petunjukMenahan }}</span>
            </div>

            <div class="pa-hcl-issue-cards">
                <div
                    v-for="k in barisMenghalangi"
                    :key="k.kode_karyawan"
                    class="pa-hcl-problem-card is-blocking"
                >
                    <!-- Header Kartu: Karyawan yang Bermasalah di HC Learn -->
                    <div class="pa-hcl-culprit-row">
                        <div class="pa-hcl-culprit-person">
                            <div class="pa-hcl-culprit-avatar">
                                <HcisIcon name="person-x-fill" size="sm" />
                            </div>
                            <div class="pa-hcl-culprit-info">
                                <div class="pa-hcl-culprit-name-line">
                                    <strong class="pa-hcl-culprit-name">{{ k.nama || k.kode_karyawan }}</strong>
                                    <span class="pa-code-pill">{{ k.kode_karyawan }}</span>
                                    <span class="pa-role-tag">{{ (k.peran || []).join(' & ') }}</span>
                                </div>
                                <div class="pa-hcl-culprit-reason-line">
                                    <span class="pa-hcl-reason-text">{{ k.keterangan }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pa-hcl-culprit-badges">
                            <HcisBadge
                                :label="k.alasan"
                                :variant="k.dapat_disinkron ? 'warning' : 'danger'"
                                size="xs"
                            />
                        </div>
                    </div>

                    <!-- Sub-Panel: Dampak Pada Peserta Evaluasi -->
                    <div v-if="(k.tanggungan || []).length" class="pa-hcl-impact-container">
                        <div class="pa-hcl-impact-header">
                            <HcisIcon name="arrow-return-right" size="2xs" />
                            <span>Dampak Penugasan: Peserta yang Kehilangan Penilai Ini</span>
                        </div>

                        <div class="pa-hcl-impact-list">
                            <div
                                v-for="t in k.tanggungan"
                                :key="t.assignment_id"
                                class="pa-hcl-impact-item"
                                :class="{ 'is-must-replace': !t.cukup_dibatalkan }"
                            >
                                <div class="pa-hcl-impact-person">
                                    <div class="pa-hcl-impact-target">
                                        <span class="pa-hcl-impact-target-label">Peserta:</span>
                                        <strong class="pa-hcl-impact-name">{{ t.nama }}</strong>
                                        <span class="pa-code-pill">{{ t.kode_karyawan }}</span>
                                    </div>
                                    <div class="pa-hcl-impact-cat">
                                        <span class="pa-hcl-cat-label">Sebagai Penilai:</span>
                                        <span class="pa-hcl-cat-tag">{{ t.nama_kategori || t.kategori }}</span>
                                    </div>
                                </div>

                                <div class="pa-hcl-impact-action-side">
                                    <div class="pa-hcl-impact-advice">
                                        <span v-if="!t.cukup_dibatalkan" class="pa-advice-badge is-danger">
                                            <HcisIcon name="exclamation-circle-fill" size="2xs" />
                                            <span>wajib DIGANTI — tinggal {{ t.penilai_sekarang - 1 }} dari minimal {{ t.min_rater }} bila hanya dibatalkan</span>
                                        </span>
                                        <span v-else class="pa-advice-badge is-warning">
                                            <HcisIcon name="info-circle-fill" size="2xs" />
                                            <span>boleh dibatalkan — sisa {{ t.penilai_sekarang - 1 }} masih memenuhi minimal {{ t.min_rater }}</span>
                                        </span>
                                    </div>

                                    <HcisButton
                                        v-if="konteks === 'PENUGASAN'"
                                        label="Kelola Rater"
                                        variant="primary"
                                        size="xs"
                                        leading-icon="people"
                                        :disabled="!dapatDisunting"
                                        @click="emit('kelola-penilai', t)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!--
         | TIDAK MENGHALANGI DIKATAKAN TERANG-TERANGAN — dan dilipat. Mereka tetap didaftar supaya
         | bisa dibereskan di data induk kelak, tetapi baris merah yang tidak menahan apa pun
         | membuat admin mencari pekerjaan yang tidak ada.
         -->
        <HcisCollapse v-if="barisTercatat.length" variant="bordered" class="pa-hcl-tercatat" data-uji="hcl-tercatat">
            <HcisCollapseItem
                name="tercatat"
                :title="`Tidak menghalangi — bereskan di data induk (${barisTercatat.length})`"
                description="Ditolak HC Learn, tetapi tidak ada penugasan aktif yang menunjuk mereka; penjadwalan tidak akan mengirimkan namanya."
            >
                <ul class="pa-hcl-tercatat-list">
                    <li v-for="k in barisTercatat" :key="k.kode_karyawan">
                        <div class="pa-hcl-tercatat-orang">
                            <strong>{{ k.nama || k.kode_karyawan }}</strong>
                            <span class="pa-code-pill">{{ k.kode_karyawan }}</span>
                            <span class="pa-role-tag">{{ (k.peran || []).join(' & ') }}</span>
                        </div>
                        <span class="pa-hcl-tercatat-sebab">{{ k.keterangan }}</span>
                        <HcisBadge :label="k.alasan" variant="neutral" appearance="subtle" size="xs" />
                    </li>
                </ul>
            </HcisCollapseItem>
        </HcisCollapse>

        <!--
         | GAGAL PER BARIS DISEBUT, bukan diringkas jadi satu angka.
         |
         | `add-new` bisa menjawab 207 — sebagian tersimpan, sebagian ditolak. Kegagalan
         | sebagian yang lolos dari perhatian adalah bentuk kegagalan yang paling mahal di
         | sini: penjadwalannya tetap akan ditolak seluruhnya.
         -->
        <HcisAlert
            v-if="gagalSinkron.length"
            variant="danger"
            appearance="subtle"
            :title="`${gagalSinkron.length} karyawan ditolak HC Learn saat didaftarkan`"
            icon="exclamation-triangle-fill"
        >
            <ul class="pa-hcl-gagal">
                <li v-for="(g, i) in gagalSinkron" :key="`gagal-${i}`">
                    <strong>{{ g.kode || '(tanpa kode)' }}</strong> — {{ g.alasan }}: {{ g.keterangan }}
                </li>
            </ul>
        </HcisAlert>
    </div>

    <HcisAlert
        v-else-if="error"
        variant="warning"
        appearance="subtle"
        title="Kesiapan karyawan di HC Learn tidak dapat dibaca"
        icon="exclamation-triangle-fill"
    >
        {{ error }}
    </HcisAlert>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisCollapse, HcisCollapseItem, HcisIcon } from '@/components/ui';
import paApi from '../../api/paApi.js';

const props = defineProps({
    cycleId: { type: [Number, String], required: true },

    /* Tombol sinkron menulis ke sistem luar, jadi ia ikut terkunci ketika siklusnya
       terkunci — panel tetap BOLEH diperiksa, karena membaca tidak mengubah apa pun. */
    dapatDisunting: { type: Boolean, default: true },

    /** 'PESERTA' (Langkah 4) atau 'PENUGASAN' (Langkah 5). */
    konteks: { type: String, default: 'PESERTA' },
});

const emit = defineEmits(['pesan', 'kelola-penilai', 'kesiapan']);

const kesiapan = ref(null);
const memeriksa = ref(false);
const menyinkron = ref(false);
const error = ref('');
const gagalSinkron = ref([]);

const LABEL_STATUS = {
    SIAP: 'Siap dijadwalkan',
    PERLU_SINKRON: 'Perlu sinkronisasi',
    PERLU_TINDAKAN: 'Perlu ditinjau',
    BELUM_ADA_PESERTA: 'Menunggu penyiapan',
};

const labelStatus = computed(() => LABEL_STATUS[kesiapan.value?.status] ?? 'Belum diperiksa');

/* Lencana kecil yang menjawab pertanyaan yang paling sering ditanyakan admin di layar ini:
   "ini menahan saya atau tidak?" */
const ringkasPenghalang = computed(() => {
    if (!barisBermasalah.value.length) return '';

    const menahan = jumlahMenghalangi.value;

    return menahan > 0
        ? `${menahan} menahan penguncian`
        : 'tidak ada yang menahan penguncian';
});

/* HIJAU HANYA UNTUK `SIAP`. Populasi kosong bukan keadaan baik maupun buruk — ia keadaan
   yang belum bisa dinilai, dan menandainya hijau membuat lampu itu berhenti berarti. */
const nadaStatus = computed(() => {
    const s = kesiapan.value?.status;

    if (s === 'SIAP') return 'success';

    return s === 'BELUM_ADA_PESERTA' ? 'neutral' : 'warning';
});

/* Asal populasi disebut dengan kata, bukan hanya angka: "dugaan" dan "kenyataan" adalah
   perbedaan yang menentukan boleh-tidaknya admin menganggap pemeriksaan ini selesai. */
const rincianAsal = computed(() => {
    const r = kesiapan.value?.ringkasan;

    if (!r || !r.terlibat) return '';

    const bagian = [`${formatNumber(r.dinilai || 0)} dinilai`];

    if ((r.penilai_terpasang || 0) > 0) {
        bagian.push(`${formatNumber(r.penilai_terpasang)} penilai dari penugasan yang sudah terbentuk`);
    }

    if ((r.penilai_proyeksi || 0) > 0) {
        bagian.push(`${formatNumber(r.penilai_proyeksi)} penilai masih proyeksi`);
    }

    return bagian.join(' · ');
});

/* Hanya yang MENGHALANGI yang didaftar. Menampilkan seluruh orang yang terlibat membuat
   satu baris merah tenggelam di antara ratusan baris hijau — dan yang merah itulah
   satu-satunya alasan panel ini dibuka. */
const barisBermasalah = computed(() => (kesiapan.value?.baris ?? []).filter((b) => !b.siap));

/* Yang MENGHALANGI dipisahkan dari yang sekadar tercatat: keduanya tetap tampil — daftar yang
   menyembunyikan orang yang ditolak akan membuatnya lolos ke pembentukan penugasan berikutnya
   tanpa peringatan — tetapi hanya yang menghalangi yang boleh menahan pekerjaan. */
/* Di Langkah 4 penugasan belum ada, jadi server menandai penilai proyeksi yang ditolak "tidak
   menghalangi" — padahal begitu penugasannya terbentuk mereka menahan penguncian. Di sana
   semuanya SATU daftar yang terbuka; pemisahan hanya berarti di Langkah 5. */
const dipisah = computed(() => props.konteks === 'PENUGASAN');
const barisMenghalangi = computed(() => (dipisah.value
    ? barisBermasalah.value.filter((b) => b.menghalangi ?? true)
    : barisBermasalah.value));
const barisTercatat = computed(() => (dipisah.value
    ? barisBermasalah.value.filter((b) => !(b.menghalangi ?? true))
    : []));
const jumlahMenghalangi = computed(
    () => barisBermasalah.value.filter((b) => b.menghalangi ?? true).length,
);

const judulMenahan = computed(() => (dipisah.value
    ? `Menahan penguncian (${barisMenghalangi.value.length})`
    : `Ditolak HC Learn (${barisMenghalangi.value.length})`));

/* Di Langkah 4 penugasannya belum ada: yang ditolak BELUM menahan apa pun, tetapi akan
   menahan begitu penugasan yang menunjuk mereka terbentuk. Kalimatnya mengikuti langkahnya. */
const petunjukMenahan = computed(() => (props.konteks === 'PENUGASAN'
    ? 'Ditolak HC Learn dan masih ditunjuk penugasan aktif. Ganti atau batalkan penugasan peserta di bawahnya lewat Kelola Rater sebelum mengunci.'
    : 'Ditolak HC Learn. Bereskan di data induk atau HC Learn sebelum penugasan dibentuk — penugasan yang menunjuk mereka akan menahan penguncian di Langkah 5.'));

const jumlahDapatDisinkron = computed(
    () => (kesiapan.value?.baris ?? []).filter((b) => b.dapat_disinkron).length,
);

async function muat() {
    memeriksa.value = true;
    error.value = '';

    try {
        const response = await paApi.kesiapanKaryawanHcLearn(props.cycleId);
        kesiapan.value = response?.result ?? response?.data ?? response;

        /* DINAIKKAN ke langkah induk, bukan disimpan sendiri. Langkah 5 punya spanduk "siap
           dikunci" dan penghitung "Perlu Perhatian" yang seluruhnya lahir dari data KITA;
           tanpa kabar ini keduanya melapor hijau dan nol pada siklus yang justru sedang
           tertahan satu orang di HC Learn — dua layar bersebelahan yang saling membantah. */
        emit('kesiapan', kesiapan.value);
    } catch (e) {
        /* Kegagalannya TIDAK menjatuhkan layar. Sambungan ke HC Learn boleh putus tanpa
           membuat isi langkahnya — yang seluruhnya data kita sendiri — ikut hilang. */
        kesiapan.value = null;
        error.value = e?.message || 'Gagal menghubungi HC Learn.';

        /* Kegagalan DIKABARKAN juga, dengan null. Membiarkan langkah induk memegang hasil
           pemeriksaan LAMA berarti spanduknya bicara atas data yang sudah tidak diketahui
           masih benar atau tidak. */
        emit('kesiapan', null);
    } finally {
        memeriksa.value = false;
    }
}

async function sinkronkan() {
    menyinkron.value = true;
    error.value = '';
    gagalSinkron.value = [];

    try {
        const response = await paApi.sinkronkanKaryawanHcLearn(props.cycleId);
        const data = response?.result ?? response?.data ?? response;

        /* Keadaan terbaru dibaca dari BALASANNYA, bukan dengan memanggil ulang: server
           sudah memeriksa ulang sesudah mendaftarkan, dan menanyakannya lagi berarti satu
           perjalanan tambahan ke HC Learn untuk jawaban yang sudah di tangan. */
        kesiapan.value = data?.kesiapan ?? kesiapan.value;
        gagalSinkron.value = data?.sinkron?.gagal ?? [];
        emit('kesiapan', kesiapan.value);

        const r = data?.sinkron?.ringkasan ?? {};
        emit('pesan', {
            teks: `${r.tersimpan ?? 0} karyawan terdaftar di HC Learn`
                + ((r.sudah_ada ?? 0) > 0 ? `, ${r.sudah_ada} memang sudah ada` : '')
                + ((r.gagal ?? 0) > 0 ? `, ${r.gagal} ditolak` : '') + '.',
            error: (r.gagal ?? 0) > 0,
        });
    } catch (e) {
        emit('pesan', {
            teks: e?.message || 'Gagal menyinkronkan karyawan ke HC Learn.',
            error: true,
        });
    } finally {
        menyinkron.value = false;
    }
}

function formatNumber(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '0';

    return Number(val).toLocaleString('id-ID');
}

/* Dipanggil SENDIRI, terpisah dari pemuatan langkahnya. Menumpangkannya pada `muat()` milik
   langkah membuat isi langkah — yang seluruhnya data kita — menunggu jaringan HC Learn. */
onMounted(muat);

defineExpose({ muat });
</script>

<style scoped>
.pa-hcl-tercatat-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-hcl-tercatat-list li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.pa-hcl-tercatat-list li:last-child {
    border-bottom: 0;
}

.pa-hcl-tercatat-orang {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem;
}

.pa-hcl-tercatat-sebab {
    flex: 1 1 14rem;
    color: #64748b;
    font-size: 0.8125rem;
}

.pa-hcl-card {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 1.35rem 1.5rem;
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: #ffffff;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pa-hcl-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.pa-hcl-head-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    max-width: 680px;
}

.pa-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.7rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #4f46e5;
}

.pa-hcl-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-hcl-summary-pills {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.pa-hcl-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-variant-numeric: tabular-nums;
}

.pa-hcl-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-hcl-note-alert {
    margin-top: 0.25rem;
}

/* Issues Section */
.pa-hcl-issues-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    margin-top: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.pa-hcl-issues-header {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-hcl-issues-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-hcl-issues-title h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: #991b1b;
}

.pa-hcl-issues-hint {
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.4;
}

.pa-hcl-issue-cards {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* Problem Card */
.pa-hcl-problem-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.1rem 1.25rem;
    background: #ffffff;
    border: 1px solid #fed7aa;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.pa-hcl-problem-card.is-blocking {
    border-color: #fca5a5;
    background: #fffafa;
    border-left: 4px solid #ef4444;
}

/* Culprit Row */
.pa-hcl-culprit-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-hcl-culprit-person {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.pa-hcl-culprit-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: #fee2e2;
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-hcl-culprit-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-hcl-culprit-name-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-hcl-culprit-name {
    font-size: 0.96rem;
    color: #0f172a;
    font-weight: 750;
}

.pa-code-pill {
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-role-tag {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-hcl-culprit-reason-line {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.4;
}

.pa-hcl-reason-text {
    color: #991b1b;
    font-weight: 600;
}

.pa-hcl-culprit-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

/* Impact Container */
.pa-hcl-impact-container {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.95rem 1.1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.pa-hcl-impact-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-hcl-impact-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-hcl-impact-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.95rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    flex-wrap: wrap;
    transition: all 0.15s ease;
}

.pa-hcl-impact-item.is-must-replace {
    border-color: #fed7aa;
    background: #fffdfa;
}

.pa-hcl-impact-person {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.pa-hcl-impact-target {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-hcl-impact-target-label {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-hcl-impact-name {
    font-size: 0.9rem;
    color: #0f172a;
    font-weight: 750;
}

.pa-hcl-impact-cat {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-hcl-cat-label {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-hcl-cat-tag {
    font-size: 0.73rem;
    font-weight: 650;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
}

.pa-hcl-impact-action-side {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-advice-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    padding: 0.22rem 0.6rem;
    border-radius: 6px;
    line-height: 1.35;
}

.pa-advice-badge.is-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.pa-advice-badge.is-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.pa-hcl-no-impact {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    background: #f8fafc;
    border-radius: 6px;
    font-size: 0.76rem;
    color: #64748b;
}

.pa-hcl-gagal {
    margin: 0.5rem 0 0;
    padding-left: 1.25rem;
    font-size: 0.8rem;
    line-height: 1.45;
}

@media (max-width: 768px) {
    .pa-hcl-head {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-hcl-actions {
        width: 100%;
        justify-content: flex-start;
    }
    .pa-hcl-culprit-row {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-hcl-culprit-badges {
        width: 100%;
        justify-content: flex-start;
    }
    .pa-hcl-impact-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .pa-hcl-impact-action-side {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
