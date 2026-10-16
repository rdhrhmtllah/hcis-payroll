<template>
    <div class="pa-rp" data-uji="riwayat-perhitungan">
        <!-- Loading State -->
        <div v-if="memuat" class="pa-rp__loading">
            <HcisIcon name="arrow-repeat" size="sm" class="pa-rp__spin" />
            <span>Memuat riwayat perhitungan…</span>
        </div>

        <!-- Error State -->
        <div v-else-if="galat" class="pa-rp__alert is-galat">
            <HcisIcon name="exclamation-triangle-fill" size="xs" />
            <span>{{ galat }}</span>
        </div>

        <!-- Global Empty State -->
        <div v-else-if="!peristiwa.length" class="pa-rp__empty">
            <div class="pa-rp__empty-icon">
                <HcisIcon name="clock-history" size="md" />
            </div>
            <strong>Belum Ada Riwayat Perhitungan</strong>
            <span>Hasil penilaian peserta ini belum pernah dihitung atau diubah.</span>
        </div>

        <template v-else>
            <!-- 1. Executive Summary / Cockpit Stats -->
            <div class="pa-rp__ringkas" data-uji="riwayat-ringkas">
                <div class="pa-rp__stat-card pa-rp__stat-card--primary">
                    <div class="pa-rp__stat-icon is-brand">
                        <HcisIcon name="award-fill" size="xs" />
                    </div>
                    <div class="pa-rp__stat-content">
                        <span class="pa-rp__stat-label">Versi Perhitungan</span>
                        <div class="pa-rp__stat-val-row">
                            <strong class="pa-rp__stat-val">Versi {{ versiKini }}</strong>
                            <span class="pa-rp__stat-badge">berlaku</span>
                        </div>
                        <span class="pa-rp__stat-sub">{{ jumlahHitung }}× dihitung</span>
                    </div>
                </div>

                <div v-if="terakhir" class="pa-rp__stat-card">
                    <div class="pa-rp__stat-icon is-indigo">
                        <HcisIcon name="clock-history" size="xs" />
                    </div>
                    <div class="pa-rp__stat-content">
                        <span class="pa-rp__stat-label">Perhitungan Terakhir</span>
                        <div class="pa-rp__stat-val-row">
                            <span class="pa-rp__stat-val-text">Terakhir {{ waktu(terakhir.pada) }}</span>
                        </div>
                        <span class="pa-rp__stat-sub">oleh <b>{{ terakhir.oleh }}</b></span>
                    </div>
                </div>

                <div v-if="jumlahCatatan" class="pa-rp__stat-card">
                    <div class="pa-rp__stat-icon is-purple">
                        <HcisIcon name="chat-left-quote-fill" size="xs" />
                    </div>
                    <div class="pa-rp__stat-content">
                        <span class="pa-rp__stat-label">Catatan Kualitatif</span>
                        <div class="pa-rp__stat-val-row">
                            <strong class="pa-rp__stat-val">{{ jumlahCatatan }}× catatan</strong>
                        </div>
                        <span class="pa-rp__stat-sub">kualitatif diubah</span>
                    </div>
                </div>
            </div>

            <!-- 2. Filter Segmented Chips -->
            <div class="pa-rp__saring-bar">
                <div class="pa-rp__saring" role="group" aria-label="Saring riwayat" data-uji="riwayat-saring">
                    <button
                        v-for="s in SARINGAN"
                        :key="s.kunci"
                        type="button"
                        class="pa-rp__chip"
                        :class="{ 'is-aktif': saring === s.kunci }"
                        :aria-pressed="saring === s.kunci ? 'true' : 'false'"
                        :data-uji="`saring-${s.kunci}`"
                        @click="saring = s.kunci"
                    >
                        <HcisIcon :name="s.ikon" size="2xs" />
                        <span>{{ s.label }}</span>
                        <span class="pa-rp__chip-badge">{{ hitungSaringan(s.kunci) }}</span>
                    </button>
                </div>
            </div>

            <!-- Filtered Empty State -->
            <div v-if="!tampil.length" class="pa-rp__filter-empty">
                <HcisIcon name="info-circle" size="xs" />
                <span>Tidak ada peristiwa untuk saringan ini.</span>
            </div>

            <!-- 3. Timeline Events List -->
            <ol v-else class="pa-rp__lini">
                <li
                    v-for="(e, i) in tampil"
                    :key="`${e.jenis}-${e.pada}-${i}`"
                    class="pa-rp__item"
                    :class="`is-${nada(e)}`"
                    :data-uji="`riwayat-${e.jenis}`"
                >
                    <!-- Timeline Node Icon Jewel -->
                    <div class="pa-rp__titik">
                        <HcisIcon :name="ikon(e)" size="xs" />
                    </div>

                    <!-- Event Card Container -->
                    <div class="pa-rp__card">
                        <!-- Card Header -->
                        <div class="pa-rp__card-header">
                            <div class="pa-rp__kepala">
                                <strong class="pa-rp__event-title">{{ label(e) }}</strong>
                                <span v-if="e.versi" class="pa-rp__versi">v{{ e.versi }}</span>
                            </div>
                            <div class="pa-rp__meta">
                                <span class="pa-rp__meta-item">
                                    <HcisIcon name="clock" size="2xs" />
                                    {{ waktu(e.pada) }}
                                </span>
                                <span class="pa-rp__meta-dot">•</span>
                                <span class="pa-rp__meta-item pa-rp__meta-author">
                                    <HcisIcon name="person" size="2xs" />
                                    {{ e.oleh }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="pa-rp__card-body">
                            <!-- A. Qualitative Notes Revisions -->
                            <ul v-if="e.jenis === 'CATATAN'" class="pa-rp__catatan" data-uji="riwayat-catatan">
                                <li v-for="u in e.perubahan" :key="u.kode" class="pa-rp__catatan-card">
                                    <div class="pa-rp__catatan-head-row">
                                        <span class="pa-rp__catatan-kat">
                                            <HcisIcon name="journal-bookmark-fill" size="2xs" />
                                            {{ u.nama }}
                                            <em v-if="u.sebelum === null" class="is-baru">baru</em>
                                            <em v-else-if="u.sesudah === null" class="is-hapus">dikosongkan</em>
                                        </span>
                                        <code v-if="u.kode" class="pa-rp__catatan-code">{{ u.kode }}</code>
                                    </div>
                                    <div class="pa-rp__catatan-diff-box">
                                        <div v-if="u.sebelum !== null" class="pa-rp__diff-block is-old">
                                            <span class="pa-rp__diff-tag">Sebelumnya:</span>
                                            <p class="pa-rp__catatan-lama" :class="{ 'is-ringkas': !terbuka[i] }">{{ u.sebelum }}</p>
                                        </div>
                                        <div v-if="u.sesudah !== null" class="pa-rp__diff-block is-new">
                                            <span class="pa-rp__diff-tag">Revisi Baru:</span>
                                            <p class="pa-rp__catatan-baru" :class="{ 'is-ringkas': !terbuka[i] }">{{ u.sesudah }}</p>
                                        </div>
                                    </div>
                                </li>
                                <li v-if="catatanPanjang(e)" class="pa-rp__catatan-aksi">
                                    <button type="button" class="pa-rp__toggle" @click="terbuka[i] = !terbuka[i]">
                                        <HcisIcon :name="terbuka[i] ? 'chevron-up' : 'chevron-down'" size="2xs" />
                                        <span>{{ terbuka[i] ? 'Ringkas' : 'Lihat selengkapnya' }}</span>
                                    </button>
                                </li>
                            </ul>

                            <!-- B. Numeric Metric Diffs -->
                            <ul v-else-if="perubahanTampil(e).length" class="pa-rp__ubah" data-uji="riwayat-perubahan">
                                <li v-for="u in perubahanTampil(e)" :key="u.kolom" class="pa-rp__ubah-pill">
                                    <span class="pa-rp__ubah-name">{{ LABEL_KOLOM[u.kolom] || u.kolom }}</span>
                                    <s>{{ nilai(u.kolom, u.sebelum) }}</s>
                                    <HcisIcon name="arrow-right" size="2xs" class="pa-rp__arrow-icon" />
                                    <b>{{ nilai(u.kolom, u.sesudah) }}</b>
                                    <em v-if="selisih(u) !== null" :class="selisih(u) > 0 ? 'is-naik' : 'is-turun'">
                                        {{ tanda(selisih(u)) }}
                                    </em>
                                </li>
                            </ul>

                            <!-- C. Initial Calculation Score Banner -->
                            <div v-else-if="e.angka" class="pa-rp__angka-wrap" data-uji="riwayat-angka">
                                <p class="pa-rp__angka">
                                    KPI {{ angka(e.angka.kpi) }} · Kompetensi {{ angka(e.angka.kompetensi) }} · Final <b>{{ angka(e.angka.final) }}</b>
                                    <template v-if="!modeVerifikasi"> · Grade {{ grade(e.angka.grade_final || e.angka.grade_sistem) }}</template>
                                    · {{ e.angka.status }}
                                </p>
                            </div>

                            <p v-else-if="SEBAB_TANPA_ANGKA.includes(e.jenis)" class="pa-rp__angka is-redup">
                                <HcisIcon name="info-circle" size="2xs" /> Tanpa perubahan angka.
                            </p>

                            <!-- Remarks & Hold Notes -->
                            <p v-if="e.keterangan" class="pa-rp__ket">
                                <HcisIcon name="info-circle" size="2xs" />
                                <span>{{ e.keterangan }}</span>
                            </p>
                            <p v-if="e.angka?.alasan_tertahan" class="pa-rp__ket is-tahan">
                                <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                <span><strong>Tertahan:</strong> {{ e.angka.alasan_tertahan }}</span>
                            </p>

                            <!-- D. Competency Score Breakdown Accordion -->
                            <div v-if="e.kompetensi?.length" class="pa-rp__komp-wrap">
                                <button
                                    type="button"
                                    class="pa-rp__toggle pa-rp__komp-toggle"
                                    :aria-expanded="terbuka[i] ? 'true' : 'false'"
                                    @click="terbuka[i] = !terbuka[i]"
                                >
                                    <HcisIcon name="bar-chart-steps" size="2xs" />
                                    <span>Nilai per kompetensi ({{ e.kompetensi.length }})<template v-if="jumlahBerubah(e)"> · {{ jumlahBerubah(e) }} berubah</template></span>
                                    <HcisIcon :name="terbuka[i] ? 'chevron-up' : 'chevron-down'" size="2xs" class="pa-rp__toggle-chevron" />
                                </button>
                                <table v-if="terbuka[i]" class="pa-rp__komp" data-uji="riwayat-kompetensi">
                                    <tr v-for="k in e.kompetensi" :key="k.kode" :class="{ 'is-berubah': berubah(k) }">
                                        <td class="pa-rp__komp-nama">{{ k.nama }}</td>
                                        <td class="pa-rp__komp-sebelum">{{ k.sebelum === null || k.sebelum === undefined ? '' : angka(k.sebelum) }}</td>
                                        <td class="pa-rp__komp-sesudah"><b>{{ angka(k.nilai) }}</b></td>
                                        <td class="pa-rp__komp-selisih">
                                            <em v-if="berubah(k)" :class="k.nilai > k.sebelum ? 'is-naik' : 'is-turun'">{{ tanda(k.nilai - k.sebelum) }}</em>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </li>
            </ol>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { HcisIcon } from '@/components/ui';
import paApi from '../../api/paApi.js';

/* Riwayat angka hasil satu peserta (DDL-2026-168): kapan dihitung, oleh siapa, dari berapa ke
   berapa. Dimuat saat tab dibuka (`aktif`), dan dimuat ulang bila peserta berganti. */
const props = defineProps({
    participantId: { type: [Number, String], default: null },
    aktif: { type: Boolean, default: false },
    modeVerifikasi: { type: Boolean, default: false },
});

const LABEL = {
    HITUNG: 'Dihitung',
    HITUNG_ULANG: 'Dihitung ulang',
    BALANCING: 'Dihitung ulang (Balancing)',
    HITUNG_LAMA: 'Dihitung',
    KURVA_DIKUNCI: 'Kurva dikunci',
    KURVA_DIBUKA: 'Kurva dibuka',
    KALIBRASI: 'Kalibrasi grade',
    BALANCING_LAMA: 'Hitung ulang Balancing',
    TERBIT: 'Diterbitkan ke karyawan',
    RILIS_LEADER: 'Dirilis ke leader',
    CATATAN: 'Catatan kualitatif diubah',
};

const IKON = {
    HITUNG: 'calculator',
    HITUNG_ULANG: 'arrow-repeat',
    BALANCING: 'sliders',
    HITUNG_LAMA: 'calculator',
    KURVA_DIKUNCI: 'lock-fill',
    KURVA_DIBUKA: 'unlock',
    KALIBRASI: 'pencil-square',
    BALANCING_LAMA: 'sliders',
    TERBIT: 'send-check',
    RILIS_LEADER: 'person-badge',
    CATATAN: 'chat-left-quote',
};

const SARINGAN = [
    { kunci: 'semua', label: 'Semua', ikon: 'grid-fill' },
    { kunci: 'angka', label: 'Angka', ikon: 'calculator-fill' },
    { kunci: 'catatan', label: 'Catatan', ikon: 'chat-left-quote-fill' },
];

/* Teks yang lebih panjang dari ini diringkas dulu; tombol "Lihat selengkapnya" membukanya. */
const PANJANG_RINGKAS = 140;

const LABEL_KOLOM = {
    status: 'Status',
    kpi: 'KPI',
    kompetensi: 'Kompetensi',
    final: 'Final',
    grade_sistem: 'Grade sistem',
    grade_final: 'Grade final',
    kuadran: 'Kuadran HAV',
};

const KOLOM_GRADE = ['grade_sistem', 'grade_final', 'kuadran'];
const KOLOM_ANGKA = ['kpi', 'kompetensi', 'final'];
const SEBAB_TANPA_ANGKA = ['BALANCING_LAMA'];
const JENIS_HITUNG = ['HITUNG', 'HITUNG_ULANG', 'BALANCING', 'HITUNG_LAMA'];

/* Dalam mode verifikasi grade belum boleh terlihat; peristiwa yang isinya hanya grade ikut disembunyikan. */
const JENIS_GRADE = ['KURVA_DIKUNCI', 'KURVA_DIBUKA', 'KALIBRASI'];

const memuat = ref(false);
const galat = ref('');
const peristiwa = ref([]);
const versiKini = ref(null);
const terbuka = reactive({});
const saring = ref('semua');
let dimuatUntuk = null;
let urutan = 0;

const tampil = computed(() =>
    peristiwa.value
        .filter((e) => !(props.modeVerifikasi && JENIS_GRADE.includes(e.jenis)))
        .filter((e) => saring.value === 'semua' || (saring.value === 'catatan') === (e.jenis === 'CATATAN')),
);

const jumlahCatatan = computed(() => peristiwa.value.filter((e) => e.jenis === 'CATATAN').length);
const jumlahHitung = computed(() => peristiwa.value.filter((e) => JENIS_HITUNG.includes(e.jenis)).length);
const terakhir = computed(() => peristiwa.value.find((e) => JENIS_HITUNG.includes(e.jenis)) || null);

function hitungSaringan(kunci) {
    const list = peristiwa.value.filter((e) => !(props.modeVerifikasi && JENIS_GRADE.includes(e.jenis)));
    if (kunci === 'semua') return list.length;
    if (kunci === 'catatan') return list.filter((e) => e.jenis === 'CATATAN').length;
    if (kunci === 'angka') return list.filter((e) => e.jenis !== 'CATATAN').length;
    return 0;
}

const angka = (v) =>
    v === null || v === undefined
        ? '–'
        : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const tanda = (v) => `${v > 0 ? '+' : '−'}${angka(Math.abs(v))}`;
const grade = (k) => (k ? String(k).replace(/_PLUS/g, '+').replace(/_MINUS/g, '−') : '–');
const nilai = (kolom, v) =>
    KOLOM_ANGKA.includes(kolom)
        ? angka(v)
        : KOLOM_GRADE.includes(kolom) && kolom !== 'kuadran'
          ? grade(v)
          : (v ?? '–');
const selisih = (u) =>
    KOLOM_ANGKA.includes(u.kolom) && u.sebelum !== null && u.sesudah !== null
        ? Math.round((u.sesudah - u.sebelum) * 100) / 100
        : null;
const label = (e) => LABEL[e.jenis] || e.jenis;
const ikon = (e) => IKON[e.jenis] || 'dot';
const nada = (e) =>
    ['TERBIT', 'RILIS_LEADER'].includes(e.jenis)
        ? 'terbit'
        : JENIS_GRADE.includes(e.jenis)
          ? 'grade'
          : e.jenis === 'CATATAN'
            ? 'catatan'
            : 'hitung';
const catatanPanjang = (e) =>
    (e.perubahan || []).some(
        (u) => String(u.sebelum ?? '').length > PANJANG_RINGKAS || String(u.sesudah ?? '').length > PANJANG_RINGKAS,
    );
const perubahanTampil = (e) => (e.perubahan || []).filter((u) => !(props.modeVerifikasi && KOLOM_GRADE.includes(u.kolom)));
const berubah = (k) =>
    k.sebelum !== null && k.sebelum !== undefined && k.nilai !== null && Math.abs(k.nilai - k.sebelum) >= 0.005;
const jumlahBerubah = (e) => (e.kompetensi || []).filter(berubah).length;
const waktu = (v) =>
    v
        ? new Date(String(v).replace(' ', 'T')).toLocaleString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '–';

async function muat() {
    const pid = props.participantId;
    if (!pid || dimuatUntuk === pid) return;

    const nomor = ++urutan;
    memuat.value = true;
    galat.value = '';

    try {
        const r = await paApi.riwayatHasil(pid);
        if (nomor !== urutan) return;
        peristiwa.value = r?.peristiwa || [];
        versiKini.value = r?.versi_kini ?? null;
        dimuatUntuk = pid;
    } catch (e) {
        if (nomor !== urutan) return;
        galat.value =
            e?.status === 403
                ? 'Riwayat perhitungan hanya untuk pemegang fase Hasil.'
                : (e?.message || 'Gagal memuat riwayat perhitungan.');
    } finally {
        if (nomor === urutan) memuat.value = false;
    }
}

watch(
    () => props.participantId,
    () => {
        urutan++;
        dimuatUntuk = null;
        peristiwa.value = [];
        versiKini.value = null;
        galat.value = '';
        memuat.value = false;
        Object.keys(terbuka).forEach((k) => delete terbuka[k]);
        saring.value = 'semua';
        if (props.aktif) muat();
    },
);

watch(
    () => props.aktif,
    (v) => {
        if (v) muat();
    },
    { immediate: true },
);

/* Sesudah hitung ulang dari dialog yang sama, induk memanggil ini supaya versi baru ikut tampil. */
function segarkan() {
    dimuatUntuk = null;
    if (props.aktif) muat();
}

defineExpose({ segarkan });
</script>

<style scoped>
.pa-rp {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Loading, Alert & Empty States */
.pa-rp__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 3rem 1.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.pa-rp__spin {
    animation: pa-spin 1s linear infinite;
    color: #4f46e5;
}

@keyframes pa-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.pa-rp__alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.82rem;
    font-weight: 600;
}

.pa-rp__alert.is-galat {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.pa-rp__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3.5rem 1.5rem;
    gap: 0.5rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-rp__empty-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 14px;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.35rem;
}

.pa-rp__empty strong {
    font-size: 0.95rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-rp__empty span {
    font-size: 0.8rem;
    max-width: 320px;
}

/* 1. Cockpit Stats Strip */
.pa-rp__ringkas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
}

.pa-rp__stat-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 140ms ease;
}

.pa-rp__stat-card--primary {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), rgba(245, 243, 255, 0.5));
    border-color: rgba(199, 210, 254, 0.9);
}

.pa-rp__stat-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rp__stat-icon.is-brand {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25);
}

.pa-rp__stat-icon.is-indigo {
    background: #eef2ff;
    color: #4f46e5;
}

.pa-rp__stat-icon.is-purple {
    background: #f5f3ff;
    color: #7c3aed;
}

.pa-rp__stat-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-rp__stat-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-rp__stat-val-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-rp__stat-val {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-rp__stat-badge {
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #3730a3;
}

.pa-rp__stat-val-text {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-rp__stat-sub {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-rp__stat-sub b {
    color: var(--hcis-text-strong, #334155);
}

/* 2. Filter Segmented Bar */
.pa-rp__saring-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-rp__saring {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.35rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-rp__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border: 1px solid transparent;
    border-radius: 999px;
    background: transparent;
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 140ms ease;
    user-select: none;
}

.pa-rp__chip:hover {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.pa-rp__chip.is-aktif {
    background: #ffffff;
    border-color: #c7d2fe;
    color: #4338ca;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.12);
}

.pa-rp__chip-badge {
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
}

.pa-rp__chip.is-aktif .pa-rp__chip-badge {
    background: #e0e7ff;
    color: #4338ca;
}

.pa-rp__filter-empty {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 1.5rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    font-size: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
    justify-content: center;
}

/* 3. Timeline Structure */
.pa-rp__lini {
    position: relative;
    margin: 0;
    padding: 0 0 0 0.5rem;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.pa-rp__lini::before {
    content: '';
    position: absolute;
    top: 14px;
    bottom: 14px;
    left: 23px;
    width: 2px;
    background: linear-gradient(180deg, #c7d2fe 0%, #e2e8f0 100%);
}

.pa-rp__item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

/* Node Jewel */
.pa-rp__titik {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: #ffffff;
    border: 2px solid #6366f1;
    color: #4f46e5;
    box-shadow: 0 0 0 4px #ffffff, 0 2px 6px rgba(79, 70, 229, 0.15);
    flex-shrink: 0;
    margin-top: 0.25rem;
}

.is-grade .pa-rp__titik {
    border-color: #f59e0b;
    color: #d97706;
    box-shadow: 0 0 0 4px #ffffff, 0 2px 6px rgba(245, 158, 11, 0.2);
}

.is-terbit .pa-rp__titik {
    border-color: #10b981;
    color: #059669;
    box-shadow: 0 0 0 4px #ffffff, 0 2px 6px rgba(16, 185, 129, 0.2);
}

.is-catatan .pa-rp__titik {
    border-color: #8b5cf6;
    color: #7c3aed;
    box-shadow: 0 0 0 4px #ffffff, 0 2px 6px rgba(139, 92, 246, 0.2);
}

/* Card Outer */
.pa-rp__card {
    flex: 1;
    min-width: 0;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    transition: all 150ms ease;
}

.pa-rp__card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.05);
}

/* Card Header */
.pa-rp__card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f7;
    flex-wrap: wrap;
}

.pa-rp__kepala {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-rp__event-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-rp__versi {
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
    background: #e0e7ff;
    font-size: 0.68rem;
    font-weight: 800;
    color: #3730a3;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-rp__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-rp__meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-rp__meta-dot {
    color: #cbd5e1;
}

.pa-rp__meta-author {
    font-weight: 600;
    color: var(--hcis-text-strong, #334155);
}

/* Card Body */
.pa-rp__card-body {
    padding: 0.9rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

/* A. Qualitative Notes Cards */
.pa-rp__catatan {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-rp__catatan-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem;
    border-left: 3px solid #8b5cf6;
    border-radius: 8px;
    background: #faf5ff;
    border-top: 1px solid #f3e8ff;
    border-right: 1px solid #f3e8ff;
    border-bottom: 1px solid #f3e8ff;
}

.pa-rp__catatan-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-rp__catatan-kat {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 800;
    color: #6b21a8;
}

.pa-rp__catatan-kat em {
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 750;
    font-style: normal;
}

.pa-rp__catatan-kat .is-baru {
    background: #dcfce7;
    color: #15803d;
}

.pa-rp__catatan-kat .is-hapus {
    background: #fee2e2;
    color: #b91c1c;
}

.pa-rp__catatan-code {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.65rem;
    font-weight: 700;
    color: #7c3aed;
    background: #ede9fe;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.pa-rp__catatan-diff-box {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-rp__diff-block {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
}

.pa-rp__diff-block.is-old {
    background: rgba(241, 245, 249, 0.7);
    border: 1px solid #e2e8f0;
}

.pa-rp__diff-block.is-new {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(199, 210, 254, 0.8);
}

.pa-rp__diff-tag {
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #64748b;
}

.pa-rp__diff-block.is-new .pa-rp__diff-tag {
    color: #4f46e5;
}

.pa-rp__catatan-lama,
.pa-rp__catatan-baru {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.5;
    white-space: pre-line;
}

.pa-rp__catatan-lama {
    color: #94a3b8;
    text-decoration: line-through;
}

.pa-rp__catatan-baru {
    color: #1e293b;
    font-weight: 500;
}

.is-ringkas {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.pa-rp__catatan-aksi {
    display: flex;
    justify-content: flex-end;
}

/* B. Numeric Diffs */
.pa-rp__ubah {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-rp__ubah-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    font-size: 0.76rem;
    color: #334155;
    font-variant-numeric: tabular-nums;
}

.pa-rp__ubah-name {
    font-weight: 700;
    color: #64748b;
}

.pa-rp__arrow-icon {
    color: #94a3b8;
}

.pa-rp__ubah s {
    color: #94a3b8;
}

.pa-rp__ubah b {
    font-weight: 750;
    color: #0f172a;
}

.pa-rp__ubah em,
.pa-rp__komp em {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-style: normal;
    font-weight: 800;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.is-naik {
    color: #15803d;
    background: #dcfce7;
}

.is-turun {
    color: #b91c1c;
    background: #fee2e2;
}

/* C. Score Banner */
.pa-rp__angka-wrap {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.pa-rp__angka {
    margin: 0;
    font-size: 0.8rem;
    color: #334155;
    font-variant-numeric: tabular-nums;
    line-height: 1.5;
}

.pa-rp__angka b {
    color: #0f172a;
}

.pa-rp__angka.is-redup {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.76rem;
    color: #94a3b8;
    font-style: italic;
}

/* Remarks & Hold Alert */
.pa-rp__ket {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: #64748b;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
}

.pa-rp__ket.is-tahan {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

/* D. Competency Breakdown */
.pa-rp__komp-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.2rem;
}

.pa-rp__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    background: #f1f5f9;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    color: #4f46e5;
    cursor: pointer;
    transition: all 140ms ease;
    align-self: flex-start;
}

.pa-rp__toggle:hover {
    background: #e0e7ff;
    color: #3730a3;
}

.pa-rp__toggle-chevron {
    color: #6366f1;
}

.pa-rp__komp {
    width: 100%;
    max-width: 600px;
    margin-top: 0.35rem;
    border-collapse: collapse;
    font-size: 0.76rem;
    font-variant-numeric: tabular-nums;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.pa-rp__komp td {
    padding: 0.45rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.pa-rp__komp tr:last-child td {
    border-bottom: none;
}

.pa-rp__komp-nama {
    font-weight: 550;
}

.pa-rp__komp-sebelum {
    width: 70px;
    text-align: right;
    color: #94a3b8;
}

.pa-rp__komp-sesudah {
    width: 70px;
    text-align: right;
    color: #0f172a;
}

.pa-rp__komp-selisih {
    width: 80px;
    text-align: right;
}

.pa-rp__komp tr.is-berubah {
    background: #fcfaff;
}

.pa-rp__komp tr.is-berubah .pa-rp__komp-nama {
    font-weight: 750;
    color: #4338ca;
}

@media (max-width: 640px) {
    .pa-rp__card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }
    .pa-rp__meta {
        width: 100%;
    }
    .pa-rp__ringkas {
        grid-template-columns: 1fr;
    }
}
</style>
