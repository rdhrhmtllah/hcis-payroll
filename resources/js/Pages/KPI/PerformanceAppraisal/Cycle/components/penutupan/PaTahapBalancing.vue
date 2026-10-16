<template>
    <div class="pa-tbl">
        <!-- 1. Executive Stat Cards Row -->
        <HcisStatGroup :cols="4" gap="sm" class="pa-tbl__stats">
            <HcisStatCard
                layout="compact"
                label="Total Sesi"
                :value="totalSesi"
                caption="Sesi balancing dibuat"
                icon="collection-fill"
                sentiment="brand"
            />
            <HcisStatCard
                layout="compact"
                label="Sesi Terbuka"
                :value="totalTerbuka"
                caption="Draf &amp; dalam pembahasan"
                icon="hourglass-split"
                :sentiment="totalTerbuka > 0 ? 'info' : 'neutral'"
            />
            <HcisStatCard
                layout="compact"
                label="Sesi Final"
                :value="totalFinalSemua"
                caption="Skor telah disepakati"
                icon="check-circle-fill"
                sentiment="success"
            />
            <HcisStatCard
                layout="compact"
                label="Perlu Hitung Ulang"
                :value="totalMenunggu"
                caption="Menunggu kalkulasi kurva"
                icon="arrow-repeat"
                :sentiment="totalMenunggu > 0 ? 'warning' : 'neutral'"
                :interactive="bisaHitungUlang"
                @click="bisaHitungUlang ? (dialogHitung = true) : null"
            />
        </HcisStatGroup>

        <!-- 2. Warning Alert Banner (When Sessions Wait for Recalculation) -->
        <HcisAlert
            v-if="ringkas.menunggu_hitung_ulang > 0"
            variant="warning"
            appearance="soft"
            glow
            :title="`${ringkas.menunggu_hitung_ulang} Sesi Final Menunggu Hitung Ulang`"
            description="Penyesuaian skor kompetensi dari sesi final belum diaplikasikan ke kurva nilai akhir. Jalankan hitung ulang agar distribusi grade seluruh peserta diperbarui sebelum siklus ditutup."
            class="pa-tbl__alert"
        >
            <template #actions>
                <div class="pa-tbl__alert-actions">
                    <HcisButton
                        label="Hitung Ulang Sekarang"
                        variant="warning"
                        size="sm"
                        leading-icon="arrow-repeat"
                        :disabled="ringkas.terbuka > 0"
                        data-uji="hitung-ulang"
                        @click="dialogHitung = true"
                    />
                    <span v-if="ringkas.terbuka > 0" class="pa-tbl__alert-hint">
                        <HcisIcon name="exclamation-circle" size="2xs" />
                        Finalkan atau batalkan {{ ringkas.terbuka }} sesi terbuka lebih dulu sebelum menjalankan hitung ulang.
                    </span>
                </div>
            </template>
        </HcisAlert>

        <!-- 3. Guidance Accordion Card -->
        <div class="pa-tbl__guidance-card">
            <div
                class="pa-tbl__guidance-header"
                role="button"
                tabindex="0"
                @click="bukaPanduan = !bukaPanduan"
                @keydown.enter="bukaPanduan = !bukaPanduan"
            >
                <div class="pa-tbl__guidance-header-left">
                    <div class="pa-tbl__guidance-icon-box">
                        <HcisIcon name="lightbulb-fill" size="xs" />
                    </div>
                    <div>
                        <span class="pa-tbl__guidance-title">Panduan Alur &amp; Prinsip Sesi Balancing</span>
                        <p class="pa-tbl__guidance-sub">Pahami kalibrasi nilai kompetensi, finalisasi forum, hingga kalkulasi kurva grade</p>
                    </div>
                </div>
                <div class="pa-tbl__guidance-toggle">
                    <span>{{ bukaPanduan ? 'Sembunyikan' : 'Pelajari Aturan Balancing' }}</span>
                    <HcisIcon :name="bukaPanduan ? 'chevron-up' : 'chevron-down'" size="2xs" />
                </div>
            </div>

            <div v-show="bukaPanduan" class="pa-tbl__guidance-body">
                <div class="pa-tbl__steps-grid">
                    <div class="pa-tbl__step-item">
                        <span class="pa-tbl__step-badge">1</span>
                        <div class="pa-tbl__step-text">
                            <strong>Opsional Sesuai Kebijakan</strong>
                            <p>Tahap ini bersifat opsional. Jika skor hasil penilaian sudah disepakati tanpa forum penyeimbangan komite, tahap ini dapat langsung dilewati.</p>
                        </div>
                    </div>
                    <div class="pa-tbl__step-item">
                        <span class="pa-tbl__step-badge">2</span>
                        <div class="pa-tbl__step-text">
                            <strong>Forum Kalibrasi &amp; Finalisasi</strong>
                            <p>Sesi balancing mempertemukan penilai untuk menyelaraskan skor kompetensi. Setelah skor disepakati, kunci status sesi menjadi <b>FINAL</b>.</p>
                        </div>
                    </div>
                    <div class="pa-tbl__step-item">
                        <span class="pa-tbl__step-badge">3</span>
                        <div class="pa-tbl__step-text">
                            <strong>Hitung Ulang &amp; Audit Trail</strong>
                            <p>Jalankan <b>Hitung Ulang</b> agar skor after masuk ke kalkulasi kurva nilai akhir. Seluruh riwayat perubahan tersimpan aman di jejak audit.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Action & Filter Toolbar -->
        <div class="pa-tbl__toolbar-card">
            <div class="pa-tbl__toolbar-left">
                <!-- Search Box -->
                <div class="pa-tbl__search-box">
                    <HcisIcon name="search" size="xs" class="pa-tbl__search-icon" />
                    <input
                        v-model="kataCari"
                        type="text"
                        placeholder="Cari nama sesi atau PIC..."
                        class="pa-tbl__search-input"
                    />
                    <button
                        v-if="kataCari"
                        type="button"
                        class="pa-tbl__search-clear"
                        title="Hapus pencarian"
                        @click="kataCari = ''"
                    >
                        <HcisIcon name="x" size="xs" />
                    </button>
                </div>

                <!-- Status Filter Chips -->
                <div class="pa-tbl__filter-chips" role="tablist">
                    <button
                        type="button"
                        class="pa-tbl__chip"
                        :class="{ 'is-active': filterStatus === 'SEMUA' }"
                        @click="filterStatus = 'SEMUA'"
                    >
                        Semua
                        <span class="pa-tbl__chip-count">{{ sesi.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-tbl__chip"
                        :class="{
                            'is-active': filterStatus === 'MENUNGGU',
                            'has-warning': ringkas.menunggu_hitung_ulang > 0
                        }"
                        @click="filterStatus = 'MENUNGGU'"
                    >
                        Perlu Hitung Ulang
                        <span class="pa-tbl__chip-count">{{ ringkas.menunggu_hitung_ulang }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-tbl__chip"
                        :class="{ 'is-active': filterStatus === 'TERBUKA' }"
                        @click="filterStatus = 'TERBUKA'"
                    >
                        Terbuka
                        <span class="pa-tbl__chip-count">{{ ringkas.terbuka }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-tbl__chip"
                        :class="{ 'is-active': filterStatus === 'FINAL' }"
                        @click="filterStatus = 'FINAL'"
                    >
                        Final
                        <span class="pa-tbl__chip-count">{{ totalFinalSemua }}</span>
                    </button>
                </div>
            </div>

            <div class="pa-tbl__toolbar-actions">
                <HcisButton
                    label="Halaman Balancing Lengkap"
                    variant="secondary"
                    size="sm"
                    leading-icon="box-arrow-up-right"
                    title="Buka halaman manajemen balancing lengkap"
                    @click="router.visit(`/performance-appraisal/balancing?cycle=${cycleId}`)"
                />
                <HcisButton
                    label="Hitung Ulang"
                    variant="warning"
                    size="sm"
                    leading-icon="arrow-repeat"
                    :disabled="!ringkas.menunggu_hitung_ulang || ringkas.terbuka > 0"
                    :title="ringkas.terbuka > 0 ? 'Finalkan atau batalkan sesi terbuka lebih dulu' : !ringkas.menunggu_hitung_ulang ? 'Tidak ada sesi FINAL yang menunggu hitung ulang' : ''"
                    data-uji="hitung-ulang"
                    @click="dialogHitung = true"
                />
                <HcisButton
                    label="Buat Sesi"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    data-uji="buat-sesi"
                    :disabled="!ringkas.boleh_buat"
                    :title="ringkas.alasan_tidak_boleh_buat || ''"
                    @click="dialogBuat = true"
                />
            </div>
        </div>

        <!-- Sebab sesi baru tidak bisa dibuat — disebut SEBELUM admin menempuh wizard-nya. -->
        <p v-if="!memuat && !ringkas.boleh_buat && ringkas.alasan_tidak_boleh_buat" class="pa-tbl__tak-boleh" data-uji="alasan-tak-boleh-buat">
            <HcisIcon name="lock-fill" size="2xs" /> Sesi baru belum bisa dibuat: {{ ringkas.alasan_tidak_boleh_buat }}
        </p>

        <!-- 5. Content View -->
        <HcisSkeleton v-if="memuat" :rows="4" />
        <p v-else-if="galat" class="pa-tbl__galat">{{ galat }}</p>

        <!-- Empty state: When no sessions in cycle -->
        <HcisEmptyState
            v-else-if="!sesi.length"
            title="Belum Ada Sesi Balancing di Siklus Ini"
            description="Tahap ini bersifat opsional. Bila seluruh skor hasil penilaian sudah disepakati tanpa forum kalibrasi komite, Anda dapat langsung melanjutkan ke tahap penutupan siklus."
            icon="sliders"
            variant="card"
            sentiment="neutral"
            size="md"
            class="pa-tbl__empty-box"
        >
            <template #actions>
                <HcisButton
                    label="Buat Sesi Pertama"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    data-uji="buat-sesi"
                    :disabled="!ringkas.boleh_buat"
                    :title="ringkas.alasan_tidak_boleh_buat || ''"
                    @click="dialogBuat = true"
                />
            </template>
        </HcisEmptyState>

        <!-- Empty state: When search/filter returns zero -->
        <HcisEmptyState
            v-else-if="!sesiTersaring.length"
            title="Tidak Ada Sesi yang Cocok"
            description="Tidak ditemukan sesi balancing yang sesuai dengan kata kunci atau filter status terpilih."
            icon="search"
            variant="card"
            sentiment="neutral"
            size="sm"
            class="pa-tbl__empty-box"
        >
            <template #actions>
                <HcisButton
                    label="Reset Filter &amp; Pencarian"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="resetFilter"
                />
            </template>
        </HcisEmptyState>

        <!-- Session List Cards -->
        <div v-else class="pa-tbl__daftar-cards">
            <div
                v-for="s in sesiTersaring"
                :key="s.id"
                class="pa-tbl__sesi-card"
                role="button"
                tabindex="0"
                @click="buka(s.id)"
                @keydown.enter="buka(s.id)"
            >
                <div
                    class="pa-tbl__sesi-strip"
                    :class="`is-${(VARIAN_STATUS[s.status] || 'neutral')}`"
                />

                <div class="pa-tbl__sesi-main">
                    <div class="pa-tbl__sesi-header">
                        <div class="pa-tbl__sesi-title-wrap">
                            <h4 class="pa-tbl__sesi-judul">{{ s.nama }}</h4>
                            <span class="pa-tbl__sesi-id">#{{ s.id }}</span>
                        </div>
                        <div class="pa-tbl__sesi-badges">
                            <HcisBadge
                                :label="LABEL_STATUS[s.status] || s.status"
                                :variant="VARIAN_STATUS[s.status] || 'neutral'"
                                appearance="soft"
                                size="xs"
                                dot
                            />
                            <span
                                v-if="s.status === 'FINAL'"
                                class="pa-tbl__sesi-recalc-pill"
                                :class="s.hitung_ulang_at ? 'is-selesai' : 'is-menunggu'"
                            >
                                <HcisIcon :name="s.hitung_ulang_at ? 'check-circle-fill' : 'exclamation-circle-fill'" size="2xs" />
                                <span>{{ s.hitung_ulang_at ? `Dihitung Ulang #${s.nomor_hitung_ulang || '–'}` : 'Belum Dihitung Ulang' }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="pa-tbl__sesi-meta">
                        <span class="pa-tbl__meta-item" title="Penanggung Jawab Sesi">
                            <HcisIcon name="person-badge" size="2xs" />
                            <span>PIC: <b>{{ s.pic_nama || '–' }}</b></span>
                        </span>
                        <span class="pa-tbl__meta-sep">&bull;</span>
                        <span class="pa-tbl__meta-item" title="Jumlah Peserta yang Ditinjau">
                            <HcisIcon name="people-fill" size="2xs" />
                            <span><b>{{ s.jumlah_peserta }}</b> Peserta</span>
                        </span>
                        <template v-if="s.jumlah_forum !== undefined && s.jumlah_forum > 0">
                            <span class="pa-tbl__meta-sep">&bull;</span>
                            <span class="pa-tbl__meta-item" title="Anggota Forum Penilai">
                                <HcisIcon name="person-check-fill" size="2xs" />
                                <span><b>{{ s.jumlah_forum }}</b> Penilai</span>
                            </span>
                        </template>
                        <span class="pa-tbl__meta-sep">&bull;</span>
                        <span class="pa-tbl__meta-item" title="Waktu Pembuatan">
                            <HcisIcon name="calendar3" size="2xs" />
                            <span>Dibuat {{ tanggal(s.created_at) }}</span>
                        </span>
                    </div>
                </div>

                <div class="pa-tbl__sesi-actions">
                    <HcisButton
                        label="Kelola Sesi"
                        variant="secondary"
                        size="xs"
                        trailing-icon="chevron-right"
                        class="pa-tbl__btn-kelola"
                        @click.stop="buka(s.id)"
                    />
                </div>
            </div>
        </div>

        <!-- Create Session Dialog -->
        <PaBalancingBuatSesiDialog
            v-model="dialogBuat"
            :cycle-id="cycleId"
            @dibuat="(d) => buka(d.id)"
        />

        <!-- Confirmation Dialog for Recalculation -->
        <HcisDialog
            v-model="dialogHitung"
            title="Hitung Ulang Balancing?"
            size="md"
            sentiment="warning"
        >
            <div class="pa-tbl__konfirmasi-wrap">
                <div class="pa-tbl__konfirmasi-lead">
                    Skor kompetensi baru dari <b>{{ ringkas.menunggu_hitung_ulang }} sesi FINAL</b> akan diaplikasikan ke dalam siklus penilaian.
                </div>
                <div class="pa-tbl__konfirmasi-dampak">
                    <div class="pa-tbl__dampak-item">
                        <div class="pa-tbl__dampak-icon is-warning">
                            <HcisIcon name="arrow-repeat" size="xs" />
                        </div>
                        <div class="pa-tbl__dampak-info">
                            <strong>Kalkulasi Ulang Nilai Akhir &amp; Kurva</strong>
                            <p>Nilai akhir dihitung ulang berdasarkan skor penyesuaian terbaru, dan batas kurva grade akan dikunci kembali secara otomatis.</p>
                        </div>
                    </div>
                    <div class="pa-tbl__dampak-item">
                        <div class="pa-tbl__dampak-icon is-info">
                            <HcisIcon name="people-fill" size="xs" />
                        </div>
                        <div class="pa-tbl__dampak-info">
                            <strong>Pergeseran Batas Grade Dinamis</strong>
                            <p>Karena rata-rata dan simpangan baku grup level bergeser, grade karyawan lain dalam grup kurva yang sama berpotensi ikut bergeser.</p>
                        </div>
                    </div>
                    <div class="pa-tbl__dampak-item">
                        <div class="pa-tbl__dampak-icon is-success">
                            <HcisIcon name="clock-history" size="xs" />
                        </div>
                        <div class="pa-tbl__dampak-info">
                            <strong>Audit Trail Lengkap Tersimpan</strong>
                            <p>Seluruh riwayat nilai sebelum vs sesudah balancing beserta alasan perubahannya tercatat permanen di jejak audit hasil.</p>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <HcisButton
                    label="Batal"
                    variant="secondary"
                    size="sm"
                    :disabled="menghitung"
                    @click="dialogHitung = false"
                />
                <HcisButton
                    label="Ya, Hitung Ulang Sekarang"
                    variant="warning"
                    size="sm"
                    leading-icon="arrow-repeat"
                    :loading="menghitung"
                    data-uji="konfirmasi-hitung-ulang"
                    @click="hitungUlang"
                />
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    hcisMessage,
} from '@/components/ui';
import paApi from '../../../api/paApi.js';
import PaBalancingBuatSesiDialog from '../../../Balancing/components/PaBalancingBuatSesiDialog.vue';
import { LABEL_STATUS, VARIAN_STATUS, pesanKualitatifDilewati } from '../../../Balancing/utils/balancing.js';

/* Tahap Balancing di Penutupan Siklus: kontrol alur eksekutif (buat, buka, hitung ulang).
   Daftar lengkap dengan filter lanjutan tetap di halaman Balancing. */
const props = defineProps({
    cycleId: { type: Number, required: true },
});
const emit = defineEmits(['berubah']);

const sesi = ref([]);
const ringkas = ref({ menunggu_hitung_ulang: 0, terbuka: 0, boleh_buat: true, alasan_tidak_boleh_buat: null });
// Kartu "Perlu Hitung Ulang" mengikuti aturan tombolnya: tidak selagi ada sesi terbuka.
const bisaHitungUlang = computed(() => ringkas.value.menunggu_hitung_ulang > 0 && ringkas.value.terbuka === 0);
const memuat = ref(false);
const galat = ref('');
const dialogBuat = ref(false);
const dialogHitung = ref(false);
const menghitung = ref(false);
const bukaPanduan = ref(false);

const filterStatus = ref('SEMUA');
const kataCari = ref('');

const totalSesi = computed(() => sesi.value.length);
const totalTerbuka = computed(() => ringkas.value.terbuka);
const totalMenunggu = computed(() => ringkas.value.menunggu_hitung_ulang);
const totalFinalSemua = computed(() => sesi.value.filter((s) => s.status === 'FINAL').length);

const sesiTersaring = computed(() => {
    return sesi.value.filter((s) => {
        if (filterStatus.value === 'MENUNGGU') {
            if (s.status !== 'FINAL' || s.hitung_ulang_at) return false;
        } else if (filterStatus.value === 'TERBUKA') {
            if (s.status !== 'TERBUKA' && s.status !== 'DRAFT') return false;
        } else if (filterStatus.value === 'FINAL') {
            if (s.status !== 'FINAL') return false;
        }

        if (kataCari.value.trim()) {
            const q = kataCari.value.toLowerCase().trim();
            const nama = (s.nama || '').toLowerCase();
            const pic = (s.pic_nama || '').toLowerCase();
            if (!nama.includes(q) && !pic.includes(q)) return false;
        }
        return true;
    });
});

function resetFilter() {
    filterStatus.value = 'SEMUA';
    kataCari.value = '';
}

async function muat() {
    if (!props.cycleId) return;
    memuat.value = true;
    galat.value = '';
    try {
        const r = await paApi.daftarSesiBalancing(props.cycleId);
        sesi.value = (r?.sesi || []).filter((s) => s.status !== 'DIBATALKAN');
        ringkas.value = {
            menunggu_hitung_ulang: r?.menunggu_hitung_ulang || 0,
            terbuka: r?.terbuka || 0,
            boleh_buat: r?.boleh_buat !== false,
            alasan_tidak_boleh_buat: r?.alasan_tidak_boleh_buat || null,
        };
    } catch (e) {
        galat.value = e?.message || 'Daftar sesi Balancing gagal dimuat.';
    } finally {
        memuat.value = false;
    }
}

async function hitungUlang() {
    menghitung.value = true;
    try {
        const r = await paApi.hitungUlangBalancing(props.cycleId);
        hcisMessage.success(`Hitung ulang #${r?.nomor ?? '–'} selesai: ${r?.dihitung ?? 0} peserta dihitung, ${r?.berubah ?? 0} baris jejak berubah.`, 'Balancing Diterapkan');
        const dilewati = pesanKualitatifDilewati(r);
        if (dilewati) hcisMessage.warning(dilewati, 'Kesimpulan Tidak Ditimpa');
        dialogHitung.value = false;
        await muat();
        emit('berubah');
    } catch (e) {
        hcisMessage.error(e?.message || 'Hitung ulang ditolak.', 'Hitung Ulang Gagal');
    } finally {
        menghitung.value = false;
    }
}

function buka(id) {
    router.visit(`/performance-appraisal/balancing/sesi/${id}`);
}

function tanggal(v) {
    return v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–';
}

watch(() => props.cycleId, muat);
onMounted(muat);
defineExpose({ muat });
</script>

<style scoped>
.pa-tbl {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* 1. Stat Cards */
.pa-tbl__stats {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

/* 2. Alert Banner */
.pa-tbl__alert {
    box-shadow: 0 4px 16px -2px rgba(245, 158, 11, 0.12);
}
.pa-tbl__alert-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.25rem;
}
.pa-tbl__alert-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 550;
    color: #92400e;
}

/* 3. Guidance Accordion Card */
.pa-tbl__guidance-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(248, 250, 252, 0.98) 100%);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}
.pa-tbl__guidance-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.95rem;
    cursor: pointer;
    background: transparent;
    user-select: none;
    transition: background 160ms ease;
}
.pa-tbl__guidance-header:hover {
    background: rgba(241, 245, 249, 0.6);
}
.pa-tbl__guidance-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.pa-tbl__guidance-icon-box {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #b45309;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.12);
}
.pa-tbl__guidance-title {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    display: block;
}
.pa-tbl__guidance-sub {
    margin: 0.1rem 0 0;
    font-size: 0.70rem;
    color: var(--hcis-color-text-muted, #64748b);
}
.pa-tbl__guidance-toggle {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.70rem;
    font-weight: 600;
    color: var(--hcis-color-primary, #3b82f6);
    flex-shrink: 0;
}
.pa-tbl__guidance-body {
    padding: 0.75rem 0.95rem 0.95rem;
    border-top: 1px solid var(--hcis-color-border-subtle, #f1f5f9);
    background: rgba(248, 250, 252, 0.5);
}
.pa-tbl__steps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
}
.pa-tbl__step-item {
    display: flex;
    gap: 0.55rem;
    align-items: flex-start;
    background: #fff;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.pa-tbl__step-badge {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #fff;
    font-size: 0.66rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.25);
}
.pa-tbl__step-text strong {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.15rem;
}
.pa-tbl__step-text p {
    margin: 0;
    font-size: 0.69rem;
    color: #64748b;
    line-height: 1.38;
}

/* 4. Toolbar & Filter Section */
.pa-tbl__toolbar-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    background: #fff;
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.pa-tbl__toolbar-left {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
    flex: 1 1 500px;
}
.pa-tbl__search-box {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 220px;
    flex: 1 1 240px;
    max-width: 320px;
}
.pa-tbl__search-icon {
    position: absolute;
    left: 0.75rem;
    color: #94a3b8;
    pointer-events: none;
}
.pa-tbl__search-input {
    width: 100%;
    padding: 0.42rem 2rem 0.42rem 2.2rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.82rem;
    color: #1e293b;
    background: #f8fafc;
    transition: all 160ms ease;
}
.pa-tbl__search-input:focus {
    outline: none;
    border-color: var(--hcis-color-primary, #3b82f6);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}
.pa-tbl__search-clear {
    position: absolute;
    right: 0.5rem;
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 0.2rem;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pa-tbl__search-clear:hover {
    color: #475569;
}
.pa-tbl__filter-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    padding-bottom: 2px;
}
.pa-tbl__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 140ms ease;
    white-space: nowrap;
}
.pa-tbl__chip:hover {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}
.pa-tbl__chip.is-active {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.08);
}
.pa-tbl__chip.has-warning:not(.is-active) {
    color: #b45309;
    background: #fef3c7;
    border-color: #fde68a;
}
.pa-tbl__chip-count {
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.06);
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
}
.pa-tbl__chip.is-active .pa-tbl__chip-count {
    background: #dbeafe;
    color: #1e40af;
}
.pa-tbl__toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
}

/* 5. Session List Cards */
.pa-tbl__daftar-cards {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.pa-tbl__sesi-card {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.95rem 1.15rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    cursor: pointer;
    overflow: hidden;
    transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}
.pa-tbl__sesi-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}
.pa-tbl__sesi-strip {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #cbd5e1;
}
.pa-tbl__sesi-strip.is-info { background: #0284c7; }
.pa-tbl__sesi-strip.is-warning { background: #f59e0b; }
.pa-tbl__sesi-strip.is-success { background: #10b981; }
.pa-tbl__sesi-strip.is-neutral { background: #94a3b8; }

.pa-tbl__sesi-main {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    flex: 1 1 auto;
    min-width: 0;
}
.pa-tbl__sesi-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}
.pa-tbl__sesi-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.pa-tbl__sesi-judul {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 750;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.pa-tbl__sesi-id {
    font-size: 0.72rem;
    font-family: var(--hcis-font-mono, monospace);
    color: #94a3b8;
    font-weight: 600;
}
.pa-tbl__sesi-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.pa-tbl__sesi-recalc-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 650;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
}
.pa-tbl__sesi-recalc-pill.is-selesai {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.pa-tbl__sesi-recalc-pill.is-menunggu {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.1);
}

.pa-tbl__sesi-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    color: #64748b;
}
.pa-tbl__meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.pa-tbl__meta-item b {
    color: #334155;
    font-weight: 600;
}
.pa-tbl__meta-sep {
    color: #cbd5e1;
    font-size: 0.75rem;
}

.pa-tbl__sesi-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}
.pa-tbl__btn-kelola {
    transition: transform 160ms ease;
}
.pa-tbl__sesi-card:hover .pa-tbl__btn-kelola {
    transform: translateX(2px);
}

/* Empty States */
.pa-tbl__empty-box {
    margin: 0.5rem 0;
}
.pa-tbl__galat {
    margin: 0;
    color: #dc2626;
    font-size: 0.84rem;
    padding: 0.75rem;
    background: #fef2f2;
    border-radius: 8px;
    border: 1px solid #fecaca;
}

/* 6. Hitung Ulang Confirmation Dialog */
.pa-tbl__konfirmasi-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    font-size: 0.85rem;
}
.pa-tbl__konfirmasi-lead {
    color: #1e293b;
    line-height: 1.5;
    font-size: 0.88rem;
}
.pa-tbl__konfirmasi-dampak {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    background: #f8fafc;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.pa-tbl__dampak-item {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}
.pa-tbl__dampak-icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pa-tbl__dampak-icon.is-warning { background: #fef3c7; color: #b45309; }
.pa-tbl__dampak-icon.is-info { background: #e0f2fe; color: #0369a1; }
.pa-tbl__dampak-icon.is-success { background: #dcfce7; color: #15803d; }
.pa-tbl__dampak-info strong {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.15rem;
}
.pa-tbl__dampak-info p {
    margin: 0;
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.4;
}

/* Responsive Breakpoints */
@media (max-width: 900px) {
    .pa-tbl__steps-grid {
        grid-template-columns: 1fr;
    }
    .pa-tbl__toolbar-card {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-tbl__toolbar-left {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-tbl__search-box {
        max-width: 100%;
    }
    .pa-tbl__toolbar-actions {
        justify-content: flex-end;
    }
}

@media (max-width: 640px) {
    .pa-tbl {
        gap: 0.75rem;
    }

    /* 1. Stat cards 2-col grid */
    .pa-tbl__stats {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
        width: 100% !important;
        min-width: 0 !important;
    }
    .pa-tbl__stats :deep(.hcis-stat-card) {
        min-width: 0 !important;
        padding: 0.65rem 0.75rem !important;
        border-radius: 10px !important;
    }
    .pa-tbl__stats :deep(.hcis-stat-card__value) {
        font-size: 1.25rem !important;
    }
    .pa-tbl__stats :deep(.hcis-stat-card__caption) {
        display: none !important;
    }

    /* 2. Alert banner */
    .pa-tbl__alert :deep(.hcis-alert__content) {
        gap: 0.5rem !important;
    }
    .pa-tbl__alert-actions {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0.45rem;
    }
    .pa-tbl__alert-actions :deep(.hcis-btn) {
        width: 100% !important;
        justify-content: center !important;
    }

    /* 3. Guidance card */
    .pa-tbl__guidance-header {
        padding: 0.55rem 0.75rem;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
    .pa-tbl__guidance-header-left {
        gap: 0.5rem;
        min-width: 0;
        flex: 1 1 auto;
    }
    .pa-tbl__guidance-title {
        font-size: 0.75rem;
        line-height: 1.25;
    }
    .pa-tbl__guidance-sub {
        display: none;
    }
    .pa-tbl__guidance-toggle {
        font-size: 0.68rem;
        flex-shrink: 0;
    }
    .pa-tbl__guidance-body {
        padding: 0.55rem 0.75rem 0.75rem;
    }
    .pa-tbl__steps-grid {
        gap: 0.45rem;
    }
    .pa-tbl__step-item {
        padding: 0.5rem 0.65rem;
        gap: 0.45rem;
    }
    .pa-tbl__step-text strong {
        font-size: 0.72rem;
    }
    .pa-tbl__step-text p {
        font-size: 0.67rem;
    }

    /* 4. Toolbar & Filters */
    .pa-tbl__toolbar-card {
        padding: 0.65rem 0.75rem;
        gap: 0.65rem;
        border-radius: 10px;
    }
    .pa-tbl__toolbar-left {
        gap: 0.5rem;
        width: 100%;
    }
    .pa-tbl__search-box {
        min-width: 0;
        width: 100%;
        flex: 1 1 100%;
        max-width: 100%;
    }
    .pa-tbl__search-input {
        font-size: 0.78rem;
        padding: 0.38rem 1.8rem 0.38rem 2rem;
    }
    .pa-tbl__filter-chips {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
        gap: 0.3rem;
        flex-wrap: nowrap;
    }
    .pa-tbl__filter-chips::-webkit-scrollbar {
        display: none;
    }
    .pa-tbl__chip {
        font-size: 0.72rem;
        padding: 0.28rem 0.55rem;
        flex-shrink: 0;
    }
    .pa-tbl__toolbar-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.45rem;
        width: 100%;
    }
    .pa-tbl__toolbar-actions > :first-child {
        grid-column: 1 / -1;
        order: 3;
    }
    .pa-tbl__toolbar-actions > :nth-child(2) {
        order: 2;
    }
    .pa-tbl__toolbar-actions > :nth-child(3) {
        order: 1;
    }
    .pa-tbl__toolbar-actions :deep(.hcis-btn) {
        width: 100% !important;
        justify-content: center !important;
    }

    /* 5. Session cards */
    .pa-tbl__sesi-card {
        padding: 0.75rem 0.85rem;
        gap: 0.55rem;
        border-radius: 10px;
        flex-direction: column;
        align-items: stretch;
    }
    .pa-tbl__sesi-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
        width: 100%;
    }
    .pa-tbl__sesi-title-wrap {
        width: 100%;
        justify-content: space-between;
        align-items: flex-start;
    }
    .pa-tbl__sesi-judul {
        font-size: 0.86rem;
        word-break: break-word;
    }
    .pa-tbl__sesi-badges {
        flex-wrap: wrap;
        gap: 0.35rem;
        width: 100%;
    }
    .pa-tbl__sesi-recalc-pill {
        font-size: 0.68rem;
        padding: 0.12rem 0.45rem;
    }
    .pa-tbl__sesi-meta {
        gap: 0.35rem 0.55rem;
        font-size: 0.72rem;
    }
    .pa-tbl__meta-sep {
        display: none;
    }
    .pa-tbl__sesi-actions {
        width: 100%;
        margin-top: 0.2rem;
    }
    .pa-tbl__btn-kelola {
        width: 100% !important;
        justify-content: center !important;
    }

    /* 6. Recalculation dialog */
    .pa-tbl__konfirmasi-wrap {
        gap: 0.65rem;
    }
    .pa-tbl__konfirmasi-lead {
        font-size: 0.8rem;
    }
    .pa-tbl__konfirmasi-dampak {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }
    .pa-tbl__dampak-info strong {
        font-size: 0.78rem;
    }
    .pa-tbl__dampak-info p {
        font-size: 0.72rem;
    }
}
.pa-tbl__tak-boleh { display: flex; align-items: center; gap: 0.4rem; margin: 0; padding: 0.55rem 0.8rem; border: 1px solid #fde68a; border-radius: 10px; background: #fffbeb; font-size: 0.8rem; color: #92400e; }
</style>
