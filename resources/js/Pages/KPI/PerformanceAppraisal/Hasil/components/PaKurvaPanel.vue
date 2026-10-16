<template>
    <section class="pa-kurva" aria-labelledby="pa-kurva-judul">
        <header class="pa-kurva__kepala">
            <div class="pa-kurva__kepala-info">
                <div class="pa-kurva__badge-row">
                    <span class="pa-kurva__kicker">KURVA DISTRIBUSI GRADE</span>
                    <HcisBadge
                        :label="data?.terkunci ? `Terkunci ${data.kunci?.pada ?? ''}` : 'Kurva Masih Sementara'"
                        :variant="data?.terkunci ? 'success' : 'warning'"
                        :icon="data?.terkunci ? 'lock-fill' : 'hourglass-split'"
                        size="xs"
                        appearance="subtle"
                        dot
                    />
                </div>
                <h2 id="pa-kurva-judul" class="pa-kurva__judul">Sebaran Nilai &amp; Batas Grade Normal per Grup Level</h2>
                <p class="pa-kurva__uraian">
                    Batas grade dihitung otomatis dari rata-rata (&mu;) dan simpangan baku (&sigma;) grup level. Mengunci kurva akan membekukan batas grade untuk tahap Balancing dan Penerbitan Hasil.
                </p>
            </div>
            <div v-if="data && data.metode === 'KURVA'" class="pa-kurva__aksi">
                <HcisButton
                    v-if="!data.terkunci"
                    label="Atur Grup Level"
                    variant="secondary"
                    size="sm"
                    leading-icon="diagram-2"
                    data-uji="atur-grup"
                    @click="bukaAturGrup"
                />
                <HcisButton
                    v-if="!data.terkunci"
                    label="Kunci Kurva Grade"
                    variant="primary"
                    size="sm"
                    leading-icon="lock-fill"
                    data-uji="kunci"
                    :disabled="!grupAktif.length || !bisaKunci"
                    :title="data.alasan_kunci || ''"
                    @click="bukaDialogKunci"
                />
                <HcisButton
                    v-else
                    label="Buka Kunci Kurva"
                    variant="danger"
                    size="sm"
                    leading-icon="unlock-fill"
                    data-uji="buka"
                    :disabled="data.sudah_terbit > 0 || data.sudah_dirilis > 0"
                    :title="data.sudah_terbit > 0 ? 'Sudah ada hasil yang terbit — kurva tidak bisa dibuka.' : data.sudah_dirilis > 0 ? `${data.sudah_dirilis} hasil sudah dirilis ke leader — cabut rilisnya dulu` : ''"
                    @click="bukaDialogBuka"
                />
            </div>
        </header>

        <HcisSkeleton v-if="memuat && !data" :lines="5" />

        <HcisResultState
            v-else-if="galat"
            status="error"
            title="Kurva grade gagal dimuat"
            :description="galat.pesan"
            :error-code="galat.kode"
            :request-id="galat.requestId"
            show-retry
            @retry="muat"
        />

        <template v-else-if="data">
            <!-- Siklus bermetode PITA: jalur lama, bisa dipindah selama belum ada yang terbit. -->
            <div v-if="data.metode === 'PITA'" class="pa-kurva__pita-lama">
                <HcisAlert
                    variant="info"
                    title="Siklus ini memakai pita nilai tetap"
                    description="Grade ditentukan pita master yang disalin saat penyiapan. Pindahkan ke kurva supaya grade mengikuti sebaran siklus ini."
                />
                <HcisButton
                    label="Pindah ke kurva"
                    variant="primary"
                    size="sm"
                    data-uji="pindah-kurva"
                    :disabled="data.sudah_terbit > 0"
                    @click="dialogPindah = true"
                />
            </div>

            <template v-else>
                <HcisAlert v-for="(p, i) in data.peringatan" :key="`global-${i}`" variant="warning" :title="p" />
                <HcisAlert
                    v-if="data.hasil_sesudah_kunci > 0"
                    variant="info"
                    :title="`${data.hasil_sesudah_kunci} hasil dihitung sesudah kurva dikunci dan memakai pita beku.`"
                />

                <HcisEmptyState
                    v-if="!data.grup.length"
                    icon="bar-chart"
                    title="Belum ada grup kurva"
                    description="Atur grup level siklus ini lebih dulu."
                />

                <HcisEmptyState
                    v-else-if="!grupAktif.length"
                    icon="people"
                    title="Belum ada peserta pada kurva"
                    description="Grup level sudah terkonfigurasi, namun seluruh grup saat ini memiliki 0 peserta."
                />

                <div v-else class="pa-kurva__grid">
                    <article
                        v-for="g in grupAktif"
                        :key="g.id"
                        class="pa-kurva__kartu is-klik"
                        :data-uji="`grup-${g.kode}`"
                        role="button"
                        tabindex="0"
                        :title="`Lihat detail perhitungan kurva ${g.nama}`"
                        @click="bukaDetail(g)"
                        @keydown.enter="bukaDetail(g)"
                    >
                        <header class="pa-kurva__kartu-kepala">
                            <div class="pa-kurva__kartu-title-box">
                                <div class="pa-kurva__kartu-title-row">
                                    <strong class="pa-kurva__kartu-nama">{{ g.nama }}</strong>
                                    <HcisBadge
                                        :label="g.kode"
                                        variant="brand"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <span class="pa-kurva__level-tag">{{ g.level.map((l) => l.nama).join(', ') }}</span>
                            </div>
                            <div class="pa-kurva__kartu-aksi">
                                <HcisBadge
                                    :label="`${g.n} Peserta`"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                />
                                <button type="button" class="pa-kurva__detail-btn" :data-uji="`detail-${g.kode}`" @click.stop="bukaDetail(g)">
                                    <HcisIcon name="calculator" size="2xs" /> Lihat perhitungan
                                </button>
                            </div>
                        </header>

                        <div class="pa-kurva__stat-grid">
                            <div class="pa-stat-card-mini">
                                <span class="pa-stat-card-mini__label">Peserta</span>
                                <strong class="pa-stat-card-mini__val">{{ g.n }}</strong>
                            </div>
                            <div class="pa-stat-card-mini">
                                <span class="pa-stat-card-mini__label">Rata-rata (&mu;)</span>
                                <strong class="pa-stat-card-mini__val text-indigo-700" data-uji="rata">{{ angka(g.rata) }}</strong>
                            </div>
                            <div class="pa-stat-card-mini">
                                <span class="pa-stat-card-mini__label">Simpangan Baku (&sigma;)</span>
                                <strong class="pa-stat-card-mini__val text-sky-700" data-uji="sd">{{ angka(g.sd) }}</strong>
                            </div>
                        </div>

                        <HcisAlert v-for="(p, i) in g.peringatan" :key="`${g.id}-${i}`" variant="warning" size="sm" :title="p" />

                        <div class="pa-kurva__tabel-wrap">
                            <table v-if="g.pita.length" class="pa-kurva__tabel">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 75px;">Grade</th>
                                        <th scope="col" style="width: 105px;">Batas z</th>
                                        <th scope="col" style="min-width: 125px;">Rentang Nilai</th>
                                        <th scope="col" style="min-width: 140px;">Realisasi Sebaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in g.pita" :key="p.kode">
                                        <th scope="row">
                                            <span class="pa-grade-pill" :class="getGradeTierClass(p.kode || p.nama)">
                                                {{ p.nama }}
                                            </span>
                                        </th>
                                        <td>
                                            <code class="pa-z-code">{{ p.batas_z === null ? 'Terendah' : `≥ ${angka(p.batas_z)} σ` }}</code>
                                        </td>
                                        <td>
                                            <span class="pa-rentang-text">{{ rentang(p) }}</span>
                                        </td>
                                        <td>
                                            <div class="pa-sebaran-cell">
                                                <div class="pa-sebaran-val">
                                                    <strong>{{ g.sebaran[p.kode] ?? 0 }} org</strong>
                                                    <small>({{ g.n > 0 ? (((g.sebaran[p.kode] ?? 0) / g.n) * 100).toFixed(1) : 0 }}%)</small>
                                                </div>
                                                <div class="pa-sebaran-track">
                                                    <div
                                                        class="pa-sebaran-fill"
                                                        :class="getGradeTierClass(p.kode || p.nama)"
                                                        :style="{ width: `${g.n > 0 ? Math.min(100, (((g.sebaran[p.kode] ?? 0) / g.n) * 100)) : 0}%` }"
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </template>
        </template>

        <!-- Detail perhitungan satu grup -->
        <PaKurvaDetailGrupDialog v-model="detail.buka" :cycle-id="cycleId" :grup-id="detail.grupId" />

        <!-- Konfirmasi kunci -->
        <HcisDialog v-model="dialogKunci" title="Kunci kurva grade?" size="sm">
            <p class="pa-kurva__teks">
                {{ grupAktif.length }} grup aktif, {{ totalPeserta }} peserta. Rata-rata, simpangan baku, dan batas
                grade tiap grup akan dibekukan, lalu setiap hasil mendapat grade resmi.
            </p>
            <p v-if="grupKosong.length" class="pa-kurva__teks" data-uji="grup-kosong">
                Grup tanpa hasil ({{ grupKosong.join(', ') }}) tidak ikut dikunci. Hasil yang menyusul di grup itu
                akan TERTAHAN sampai kurva dibuka lalu dikunci ulang.
            </p>
            <ul v-if="semuaPeringatan.length" class="pa-kurva__daftar-peringatan">
                <li v-for="(p, i) in semuaPeringatan" :key="i">{{ p }}</li>
            </ul>
            <!-- Hasil belum lengkap: boleh dikunci, tetapi hanya dengan alasan tertulis. -->
            <template v-if="perluAlasanKunci">
                <HcisAlert variant="warning" :title="data.alasan_kunci" data-uji="kunci-belum-lengkap" />
                <HcisTextarea
                    id="pa-kurva-alasan-kunci"
                    v-model="alasanKunci"
                    label="Alasan tetap mengunci"
                    :maxlength="500"
                    :rows="3"
                    required
                    :error="galatAlasanKunci"
                />
            </template>
            <template #footer>
                <div class="pa-kurva__aksi-dialog">
                    <HcisButton label="Batal" variant="secondary" @click="dialogKunci = false" />
                    <HcisButton
                        label="Kunci"
                        variant="primary"
                        data-uji="konfirmasi-kunci"
                        :loading="bekerja"
                        @click="kunci"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Buka kunci -->
        <HcisDialog v-model="dialogBuka" title="Buka kunci kurva" size="sm">
            <p class="pa-kurva__teks">
                Grade resmi seluruh hasil dikosongkan sampai kurva dikunci lagi, dan rata-rata serta simpangan
                bakunya dihitung ulang dari hasil saat itu. Sesi Balancing yang dihitung ulang kelak juga mengunci
                ulang kurva ini.
            </p>
            <HcisTextarea
                id="pa-kurva-alasan"
                v-model="alasan"
                label="Alasan membuka kunci"
                :maxlength="500"
                :rows="3"
                required
                :error="galatAlasan"
            />
            <template #footer>
                <div class="pa-kurva__aksi-dialog">
                    <HcisButton label="Batal" variant="secondary" @click="dialogBuka = false" />
                    <HcisButton
                        label="Buka kunci"
                        variant="danger"
                        data-uji="konfirmasi-buka"
                        :loading="bekerja"
                        @click="buka"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Pindah metode -->
        <HcisDialog v-model="dialogPindah" title="Pindah ke kurva grade?" size="sm">
            <p class="pa-kurva__teks">
                Grade pita lama siklus ini dikosongkan dan diganti grade kurva sesudah kurva dikunci. Batas z diambil
                dari master saat ini.
            </p>
            <template #footer>
                <div class="pa-kurva__aksi-dialog">
                    <HcisButton label="Batal" variant="secondary" @click="dialogPindah = false" />
                    <HcisButton
                        label="Pindah"
                        variant="primary"
                        data-uji="konfirmasi-pindah"
                        :loading="bekerja"
                        @click="pindah"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Atur grup siklus -->
        <HcisDialog v-model="dialogGrup" title="Atur grup kurva siklus ini" size="lg">
            <p class="pa-kurva__teks">
                Level dalam satu grup dibandingkan dalam satu kurva. Gabungkan level kecil supaya kurvanya bermakna.
            </p>
            <div class="pa-kurva__grup-baru">
                <HcisField
                    id="pa-kurva-grup-kode"
                    v-model="grupBaru.kode"
                    label="Kode grup baru"
                    placeholder="mis. STAF"
                />
                <HcisField
                    id="pa-kurva-grup-nama"
                    v-model="grupBaru.nama"
                    label="Nama grup baru"
                    placeholder="mis. Staff & Senior Staff"
                />
                <HcisButton label="Tambah grup" size="sm" variant="secondary" icon="plus" @click="tambahGrup" />
            </div>
            <ul class="pa-kurva__peta">
                <li v-for="l in levelDraf" :key="l.id">
                    <span>{{ l.nama }}</span>
                    <HcisSelect v-model="l.grup" size="sm" :clearable="false" :aria-label="`Grup untuk ${l.nama}`">
                        <HcisSelectOption v-for="g in grupDraf" :key="g.kode" :value="g.kode" :label="g.nama" />
                    </HcisSelect>
                </li>
            </ul>
            <HcisAlert v-if="galatGrup" variant="danger" :title="galatGrup" />
            <template #footer>
                <div class="pa-kurva__aksi-dialog">
                    <HcisButton label="Batal" variant="secondary" @click="dialogGrup = false" />
                    <HcisButton label="Simpan grup" variant="primary" :loading="bekerja" @click="simpanGrup" />
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';

import paApi from '../../api/paApi.js';
import PaKurvaDetailGrupDialog from './PaKurvaDetailGrupDialog.vue';

defineOptions({ name: 'PaKurvaPanel' });

const props = defineProps({
    cycleId: { type: Number, required: true },
});

const emit = defineEmits(['berubah']);

const data = ref(null);
const detail = reactive({ buka: false, grupId: null });

function bukaDetail(g) {
    detail.grupId = g.id;
    detail.buka = true;
}
const memuat = ref(false);
const galat = ref(null);
const bekerja = ref(false);

const dialogKunci = ref(false);
const dialogBuka = ref(false);
const dialogPindah = ref(false);
const dialogGrup = ref(false);
const alasan = ref('');
const galatAlasan = ref('');
const alasanKunci = ref('');
const galatAlasanKunci = ref('');
const galatGrup = ref('');
const grupDraf = ref([]);
const levelDraf = ref([]);
const grupBaru = reactive({ kode: '', nama: '' });

/* `alasan_kunci` dari server menyebut sebab yang sebenarnya (status siklus, batas z, level
   tanpa grup, hasil belum lengkap) — dulu tooltip selalu berkata "harus CLOSED". Hasil yang
   belum lengkap tidak mematikan tombol; ia menuntut alasan tertulis di dialog. */
const bisaKunci = computed(() => Boolean(data.value?.boleh_kunci || data.value?.boleh_kunci_dengan_alasan));
const perluAlasanKunci = computed(() => !data.value?.boleh_kunci && Boolean(data.value?.boleh_kunci_dengan_alasan));
const totalPeserta = computed(() => (data.value?.grup ?? []).reduce((t, g) => t + g.n, 0));
// Hanya grup yang memiliki peserta (> 0) yang ditampilkan di kurva normal
const grupAktif = computed(() => (data.value?.grup ?? []).filter((g) => g.n > 0));
// Server tidak membekukan kurva grup kosong (kurva nol orang memberi grade tengah otomatis).
const grupKosong = computed(() => (data.value?.grup ?? []).filter((g) => g.n === 0).map((g) => g.kode));
const semuaPeringatan = computed(() => [
    ...(data.value?.peringatan ?? []),
    ...grupAktif.value.flatMap((g) => g.peringatan),
]);

function metaGalat(e) {
    return {
        pesan: e?.message || 'Permintaan tidak bisa diselesaikan.',
        kode: e?.error_code || '',
        requestId: e?.request_id || '',
    };
}

async function muat() {
    memuat.value = true;
    galat.value = null;

    try {
        data.value = await paApi.kurvaSiklus(props.cycleId);
    } catch (e) {
        galat.value = metaGalat(e);
    } finally {
        memuat.value = false;
    }
}

/** Menjalankan aksi tulis; hasilnya adalah bentuk kurva terbaru dari server. */
async function jalankan(kerja, pesanSukses) {
    bekerja.value = true;

    try {
        data.value = await kerja();
        hcisMessage.success(pesanSukses);
        emit('berubah');
        return true;
    } catch (e) {
        hcisMessage.error(e?.message || 'Permintaan ditolak.');
        return false;
    } finally {
        bekerja.value = false;
    }
}

function bukaDialogKunci() {
    alasanKunci.value = '';
    galatAlasanKunci.value = '';
    dialogKunci.value = true;
}

async function kunci() {
    const teks = alasanKunci.value.trim();

    if (perluAlasanKunci.value && teks.length < 5) {
        galatAlasanKunci.value = 'Alasan wajib diisi, paling sedikit 5 karakter.';
        return;
    }

    const kirim = () => (perluAlasanKunci.value ? paApi.kunciKurva(props.cycleId, teks) : paApi.kunciKurva(props.cycleId));
    if (await jalankan(kirim, 'Kurva terkunci.')) dialogKunci.value = false;
}

function bukaDialogBuka() {
    alasan.value = '';
    galatAlasan.value = '';
    dialogBuka.value = true;
}

async function buka() {
    const teks = alasan.value.trim();

    if (teks.length < 5) {
        galatAlasan.value = 'Alasan wajib diisi, paling sedikit 5 karakter.';
        return;
    }

    if (await jalankan(() => paApi.bukaKurva(props.cycleId, teks), 'Kunci kurva dibuka.')) dialogBuka.value = false;
}

async function pindah() {
    if (await jalankan(() => paApi.ubahMetodeGrade(props.cycleId, 'KURVA'), 'Siklus memakai kurva grade.')) {
        dialogPindah.value = false;
    }
}

function bukaAturGrup() {
    grupDraf.value = (data.value?.grup ?? []).map((g) => ({ kode: g.kode, nama: g.nama }));
    levelDraf.value = (data.value?.grup ?? []).flatMap((g) =>
        g.level.map((l) => ({ id: l.id, nama: l.nama, grup: g.kode })),
    );
    galatGrup.value = '';
    dialogGrup.value = true;
}

function tambahGrup() {
    const kode = grupBaru.kode.trim().toUpperCase();
    const nama = grupBaru.nama.trim();

    if (!kode || !nama || grupDraf.value.some((g) => g.kode === kode)) {
        galatGrup.value = 'Grup baru butuh kode unik dan nama.';
        return;
    }

    grupDraf.value.push({ kode, nama });
    grupBaru.kode = '';
    grupBaru.nama = '';
    galatGrup.value = '';
}

async function simpanGrup() {
    // Grup tanpa level dibuang di sini; server menolaknya dengan pesan yang sama.
    const grup = grupDraf.value
        .map((g) => ({ ...g, level_ids: levelDraf.value.filter((l) => l.grup === g.kode).map((l) => l.id) }))
        .filter((g) => g.level_ids.length > 0);

    if (await jalankan(() => paApi.aturGrupKurvaSiklus(props.cycleId, grup), 'Grup kurva disimpan.')) {
        dialogGrup.value = false;
    }
}

function angka(v) {
    if (v === null || v === undefined || Number.isNaN(Number(v))) return '—';
    return Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function rentang(p) {
    if (Number(p.nilai_sampai) >= 999.99) return `≥ ${angka(p.nilai_dari)}`;
    return `${angka(p.nilai_dari)} – < ${angka(p.nilai_sampai)}`;
}

/* Φ(z) lewat pendekatan Abramowitz–Stegun untuk erf — cukup untuk label persentase. */
function phi(z) {
    if (z === Infinity) return 1;
    if (z === -Infinity) return 0;
    const t = 1 / (1 + 0.3275911 * Math.abs(z / Math.SQRT2));
    const y =
        1 -
        ((((1.061405429 * t - 1.453152027) * t + 1.421413741) * t - 0.284496736) * t + 0.254829592) *
            t *
            Math.exp(-((z / Math.SQRT2) ** 2));
    return 0.5 * (1 + (z >= 0 ? y : -y));
}

function teoretis(pita, i) {
    const bawah = pita[i].batas_z === null ? -Infinity : Number(pita[i].batas_z);
    const atas = i === 0 ? Infinity : Number(pita[i - 1].batas_z);
    return phi(atas) - phi(bawah);
}

function persen(v) {
    return `${(v * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
}

function getGradeTierClass(grade) {
    const g = String(grade || '').toUpperCase().trim();
    if (g.startsWith('A')) return 'is-grade-a';
    if (g.startsWith('B')) return 'is-grade-b';
    if (g.startsWith('C')) return 'is-grade-c';
    if (g.startsWith('D')) return 'is-grade-d';
    return 'is-grade-e';
}

watch(() => props.cycleId, muat);
onMounted(muat);

defineExpose({ muat, kunci, buka, alasan, galatAlasan, alasanKunci, galatAlasanKunci, teoretis });
</script>

<style scoped>
.pa-kurva {
    display: grid;
    gap: var(--hcis-space-4, 1.25rem);
    margin-bottom: var(--hcis-space-4, 1.5rem);
    padding: 1.25rem 1.5rem;
    border: 1px solid var(--hcis-border-subtle, rgba(226, 232, 240, 0.9));
    border-radius: var(--hcis-radius-xl, 16px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-kurva__kepala {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    padding-bottom: 1.15rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-kurva__kepala-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    max-width: 54rem;
}

.pa-kurva__badge-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-kurva__kicker {
    margin: 0;
    color: #4f46e5;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.pa-kurva__judul {
    margin: 0;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.90rem;
    font-weight: 750;
    letter-spacing: -0.01em;
}

.pa-kurva__uraian {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    line-height: 1.45;
}

.pa-kurva__aksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    align-self: center;
}

.pa-kurva__pita-lama {
    display: grid;
    gap: var(--hcis-space-2);
    justify-items: start;
}

.pa-kurva__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 400px), 1fr));
    gap: 0.85rem;
}

.pa-kurva__kartu {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.9rem 1.05rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    min-width: 0;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-kurva__kartu:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}

.pa-kurva__kartu-kepala {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.65rem;
}

.pa-kurva__kartu-title-box {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-kurva__kartu-title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-kurva__kartu-nama {
    font-size: 0.88rem;
    font-weight: 750;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-kurva__level-tag {
    font-size: 0.70rem;
    color: #64748b;
    line-height: 1.35;
}

.pa-kurva__stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
}

.pa-stat-card-mini {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.4rem 0.55rem;
    border-radius: 7px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}

.pa-stat-card-mini__label {
    font-size: 0.62rem;
    font-weight: 650;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-stat-card-mini__val {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-kurva__tabel-wrap {
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    overflow-x: auto;
    background: #ffffff;
}

.pa-kurva__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.72rem;
}

.pa-kurva__tabel th,
.pa-kurva__tabel td {
    padding: 0.45rem 0.65rem;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
}

.pa-kurva__tabel thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pa-grade-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.65rem;
    height: 1.3rem;
    padding: 0 0.35rem;
    border-radius: 5px;
    font-weight: 800;
    font-size: 0.72rem;
    font-variant-numeric: tabular-nums;
}

.pa-grade-pill.is-grade-a {
    background: #ede9fe;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
}

.pa-grade-pill.is-grade-b {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.pa-grade-pill.is-grade-c {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}

.pa-grade-pill.is-grade-d {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-grade-pill.is-grade-e {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.pa-z-code {
    font-family: ui-monospace, monospace;
    font-size: 0.66rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #334155;
    font-weight: 600;
    white-space: nowrap;
}

.pa-rentang-text {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.70rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pa-sebaran-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 100px;
}

.pa-sebaran-val {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    font-size: 0.70rem;
}

.pa-sebaran-val strong {
    color: #0f172a;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: 0.72rem;
}

.pa-sebaran-val small {
    color: #64748b;
    font-size: 0.65rem;
    font-variant-numeric: tabular-nums;
}

.pa-sebaran-track {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-sebaran-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-sebaran-fill.is-grade-a { background: #7c3aed; }
.pa-sebaran-fill.is-grade-b { background: #16a34a; }
.pa-sebaran-fill.is-grade-c { background: #0284c7; }
.pa-sebaran-fill.is-grade-d { background: #d97706; }
.pa-sebaran-fill.is-grade-e { background: #dc2626; }

.pa-teoretis-val {
    font-size: 0.72rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}
.pa-kurva__daftar-peringatan {
    margin: var(--hcis-space-2) 0 0;
    padding-left: var(--hcis-space-4);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-sm);
}
.pa-kurva__aksi-dialog {
    display: flex;
    justify-content: flex-end;
    gap: var(--hcis-space-2);
    width: 100%;
}
.pa-kurva__grup-baru {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    align-items: end;
    gap: var(--hcis-space-2);
    margin: var(--hcis-space-3) 0;
}
.pa-kurva__peta {
    display: grid;
    gap: var(--hcis-space-2);
    margin: 0;
    padding: 0;
    list-style: none;
}
.pa-kurva__peta li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    gap: var(--hcis-space-2);
    color: var(--hcis-text);
}

.pa-kurva__kartu.is-klik { cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
.pa-kurva__kartu.is-klik:hover { border-color: #a5b4fc; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.12); }
.pa-kurva__kartu-aksi { display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem; }
.pa-kurva__detail-btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border: 1px solid #c7d2fe; border-radius: 8px; background: #fff; color: #4338ca; font-size: 0.72rem; font-weight: 700; cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent; }
.pa-kurva__detail-btn:hover { background: #eef2ff; }

@media (max-width: 767px) {
    .pa-kurva__stat,
    .pa-kurva__grup-baru,
    .pa-kurva__peta li {
        grid-template-columns: 1fr;
    }
    .pa-kurva__aksi-dialog {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .pa-kurva {
        padding: 0.75rem 0.85rem;
        border-radius: var(--hcis-radius-md, 12px);
        gap: 0.75rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow-x: hidden;
    }
    .pa-kurva__kepala {
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
        padding-bottom: 0.65rem;
    }
    .pa-kurva__kicker {
        font-size: 0.62rem;
    }
    .pa-kurva__judul {
        font-size: 0.86rem;
        line-height: 1.3;
    }
    .pa-kurva__uraian {
        font-size: 0.68rem;
        line-height: 1.35;
    }
    .pa-kurva__aksi {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }
    .pa-kurva__aksi :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }
    .pa-kurva__grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-kurva__kartu {
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow: hidden;
    }
    .pa-kurva__kartu-kepala {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
    }
    .pa-kurva__kartu-title-box {
        min-width: 0;
        flex: 1;
        overflow: hidden;
    }
    .pa-kurva__kartu-nama {
        font-size: 0.82rem;
        font-weight: 750;
        word-break: break-word;
    }
    .pa-kurva__level-tag {
        font-size: 0.64rem;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pa-kurva__kartu-aksi {
        flex-direction: row;
        align-items: center;
        gap: 0.35rem;
        flex-shrink: 0;
    }
    .pa-kurva__detail-btn {
        padding: 0.2rem 0.45rem;
        font-size: 0.68rem;
        min-height: 28px;
    }
    .pa-kurva__stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.3rem;
    }
    .pa-stat-card-mini {
        padding: 0.3rem 0.45rem;
        border-radius: 6px;
    }
    .pa-stat-card-mini__label {
        font-size: 0.58rem;
    }
    .pa-stat-card-mini__val {
        font-size: 0.95rem;
        font-weight: 800;
    }
    .pa-kurva__tabel-wrap {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        box-sizing: border-box;
        border-radius: 6px;
    }
    .pa-kurva__tabel th,
    .pa-kurva__tabel td {
        padding: 0.35rem 0.45rem;
        font-size: 0.68rem;
    }
    .pa-grade-pill {
        min-width: 1.45rem;
        height: 1.2rem;
        font-size: 0.68rem;
    }
    .pa-z-code {
        font-size: 0.62rem;
    }
    .pa-rentang-text {
        font-size: 0.66rem;
    }
    .pa-sebaran-cell {
        min-width: 90px;
        gap: 0.15rem;
    }
}

@media (max-width: 420px) {
    .pa-kurva {
        padding: 0.6rem 0.65rem;
        gap: 0.6rem;
    }
    .pa-kurva__kartu {
        padding: 0.55rem 0.65rem;
        gap: 0.4rem;
    }
    .pa-stat-card-mini {
        padding: 0.25rem 0.35rem;
    }
    .pa-stat-card-mini__val {
        font-size: 0.88rem;
    }
}
</style>
