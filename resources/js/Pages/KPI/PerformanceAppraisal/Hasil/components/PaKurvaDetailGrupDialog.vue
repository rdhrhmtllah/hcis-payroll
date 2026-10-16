<template>
    <HcisDialog
        :model-value="modelValue"
        :title="data ? `Perhitungan Kurva · ${data.grup.nama}` : 'Perhitungan Kurva'"
        kicker="KURVA NORMAL · DETAIL GRUP"
        size="2xl"
        sentiment="brand"
        @update:model-value="(v) => $emit('update:modelValue', v)"
    >
        <HcisSkeleton v-if="memuat" :rows="8" />
        <p v-else-if="galat" class="pa-kdg-galat">{{ galat }}</p>

        <div v-else-if="data" class="pa-kdg">
            <!-- 1. Executive Identity & Mini Stats Hero Card -->
            <div class="pa-kdg__hero">
                <div class="pa-kdg__hero-info">
                    <div class="pa-kdg__hero-title-row">
                        <h3 class="pa-kdg__hero-nama">{{ data.grup.nama }}</h3>
                        <HcisBadge
                            :label="data.grup.kode"
                            variant="brand"
                            appearance="subtle"
                            size="xs"
                        />
                        <HcisBadge
                            :label="data.terkunci ? 'Kurva Terkunci (Grade Resmi)' : 'Kurva Sementara (Belum Dikunci)'"
                            :variant="data.terkunci ? 'success' : 'warning'"
                            :icon="data.terkunci ? 'lock-fill' : 'hourglass-split'"
                            appearance="soft"
                            size="xs"
                            dot
                        />
                    </div>
                    <span class="pa-kdg__hero-level">Tingkat Jabatan: <b>{{ data.grup.level.join(', ') || '–' }}</b></span>
                </div>

                <!-- 3 Mini Stat KPI Cards -->
                <div class="pa-kdg__stat-grid">
                    <div class="pa-kdg-stat-mini">
                        <span class="pa-kdg-stat-mini__label">Total Peserta (n)</span>
                        <strong class="pa-kdg-stat-mini__val">{{ angka(data.langkah.n, 0) }}</strong>
                    </div>
                    <div class="pa-kdg-stat-mini">
                        <span class="pa-kdg-stat-mini__label">Rata-rata (&mu;)</span>
                        <strong class="pa-kdg-stat-mini__val text-indigo-700">{{ angka(data.langkah.rata) }}</strong>
                    </div>
                    <div class="pa-kdg-stat-mini">
                        <span class="pa-kdg-stat-mini__label">Simpangan Baku (&sigma;)</span>
                        <strong class="pa-kdg-stat-mini__val text-sky-700">{{ angka(data.langkah.sd) }}</strong>
                    </div>
                </div>
            </div>

            <!-- 2. Unified Accordion: Rumus, Simbol & Langkah Perhitungan -->
            <div class="pa-kdg__accordion-card">
                <div
                    class="pa-kdg__accordion-header"
                    role="button"
                    tabindex="0"
                    @click="bukaRumus = !bukaRumus"
                    @keydown.enter="bukaRumus = !bukaRumus"
                >
                    <div class="pa-kdg__accordion-header-left">
                        <div class="pa-kdg__accordion-icon-box">
                            <HcisIcon name="calculator" size="xs" />
                        </div>
                        <div>
                            <span class="pa-kdg__accordion-title">Panduan Rumus, Simbol &amp; Langkah Perhitungan Statistik</span>
                            <p class="pa-kdg__accordion-sub">
                                Pelajari notasi matematika dan 6 tahapan kalkulasi rata-rata (&mu;), simpangan baku (&sigma;), hingga batas grade
                            </p>
                        </div>
                    </div>
                    <div class="pa-kdg__accordion-toggle">
                        <span>{{ bukaRumus ? 'Sembunyikan Rumus & Simbol' : 'Lihat Rumus & Simbol' }}</span>
                        <HcisIcon :name="bukaRumus ? 'chevron-up' : 'chevron-down'" size="2xs" />
                    </div>
                </div>

                <div v-show="bukaRumus" class="pa-kdg__accordion-body">
                    <div class="pa-kdg__calc-grid">
                        <!-- Kolom 1: Keterangan Simbol -->
                        <div class="pa-kdg__calc-col">
                            <h5 class="pa-kdg__col-title">
                                <HcisIcon name="info-circle" size="2xs" />
                                Keterangan Simbol &amp; Notasi
                            </h5>
                            <div class="pa-kdg__simbol-grid">
                                <div v-for="s in SIMBOL" :key="s.simbol" class="pa-kdg__simbol-row">
                                    <code class="pa-kdg__simbol-badge">{{ s.simbol }}</code>
                                    <div class="pa-kdg__simbol-desc">
                                        <strong>{{ s.nama }}</strong>
                                        <span>{{ s.arti }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom 2: 6 Tahapan Perhitungan -->
                        <div class="pa-kdg__calc-col">
                            <h5 class="pa-kdg__col-title">
                                <HcisIcon name="list-ol" size="2xs" />
                                6 Tahapan Kalkulasi Nilai Aktual
                            </h5>
                            <div class="pa-kdg__langkah-list">
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">1</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Jumlah Anggota Grup (n)</span>
                                        <code class="pa-kdg__step-formula">n = {{ angka(data.langkah.n, 0) }} orang</code>
                                        <small>Peserta berhasil DIHITUNG/TERBIT pada level grup ini.</small>
                                    </div>
                                </div>
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">2</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Total Seluruh Nilai Final (&Sigma;x)</span>
                                        <code class="pa-kdg__step-formula">&Sigma;x = {{ angka(data.langkah.jumlah) }}</code>
                                        <small>Penjumlahan seluruh skor akhir peserta dalam grup ini.</small>
                                    </div>
                                </div>
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">3</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Rata-rata Kelompok (&mu;)</span>
                                        <code class="pa-kdg__step-formula">&mu; = &Sigma;x &divide; n = {{ angka(data.langkah.jumlah) }} &divide; {{ angka(data.langkah.n, 0) }} = <b>{{ angka(data.langkah.rata) }}</b></code>
                                        <small>Dibulatkan 4 desimal; acuan titik tengah kurva normal.</small>
                                    </div>
                                </div>
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">4</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Jumlah Kuadrat Deviasi (&Sigma;(x &minus; &mu;)&sup2;)</span>
                                        <code class="pa-kdg__step-formula">&Sigma;(x &minus; &mu;)&sup2; = {{ angka(data.langkah.jumlah_kuadrat) }}</code>
                                        <small>Kuadrat selisih nilai tiap individu terhadap rata-rata.</small>
                                    </div>
                                </div>
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">5</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Simpangan Baku Sampel (&sigma;)</span>
                                        <code v-if="data.langkah.varians !== null" class="pa-kdg__step-formula">&sigma; = &radic;( &Sigma;(x &minus; &mu;)&sup2; &divide; (n &minus; 1) ) = &radic;{{ angka(data.langkah.varians) }} = <b>{{ angka(data.langkah.sd) }}</b></code>
                                        <code v-else class="pa-kdg__step-formula">&sigma; belum dapat dihitung (minimal 2 anggota)</code>
                                        <small>Dibagi n &minus; 1 (sampel), setara rumus STDEV di Excel.</small>
                                    </div>
                                </div>
                                <div class="pa-kdg__langkah-card">
                                    <span class="pa-kdg__step-num">6</span>
                                    <div class="pa-kdg__step-content">
                                        <span class="pa-kdg__step-label">Formula Batas Bawah Grade</span>
                                        <code class="pa-kdg__step-formula">Batas Bawah = &mu; + (z &times; &sigma;)</code>
                                        <small>Setiap grade mengacu pada nilai z yang telah ditentukan di master kurva.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Peringatan / Kunci Beku -->
                    <div v-if="bedaBeku" class="pa-kdg__catatan is-info">
                        <HcisIcon name="lock-fill" size="xs" />
                        <div>
                            <strong>Kurva Memakai Nilai Beku</strong>
                            <p>Kurva dikunci dengan &mu; = {{ angka(data.langkah.rata) }} dan &sigma; = {{ angka(data.langkah.sd) }} (n = {{ data.langkah.n_kurva }}). Nilai anggota saat ini menghasilkan &mu; = {{ angka(data.langkah.rata_mentah) }}. Selama kurva terkunci, grade resmi tetap memakai angka beku.</p>
                        </div>
                    </div>
                    <div v-if="data.langkah.n < data.min_grup" class="pa-kdg__catatan is-warning">
                        <HcisIcon name="exclamation-triangle-fill" size="xs" />
                        <div>
                            <strong>Jumlah Anggota Terbatas (n = {{ data.langkah.n }} orang)</strong>
                            <p>Jumlah anggota berada di bawah batas ideal ({{ data.min_grup }} orang); kurva kurang representatif. Anda dapat menggabungkan level ini dengan level lain pada menu Atur Grup Kurva.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Tabel Batas & Sebaran Grade Grup -->
            <section class="pa-kdg__section">
                <div class="pa-kdg__section-header">
                    <div>
                        <h4 class="pa-kdg__section-title">
                            <HcisIcon name="bar-chart-steps" size="xs" />
                            Batas &amp; Sebaran Grade Grup
                        </h4>
                        <p class="pa-kdg__section-sub">
                            Batas nilai akhir dihitung dari &mu; = {{ angka(data.langkah.rata) }} dan &sigma; = {{ angka(data.langkah.sd) }}
                        </p>
                    </div>
                </div>

                <div class="pa-kdg__table-wrap">
                    <table class="pa-kdg__table">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Grade</th>
                                <th style="width: 100px;">Batas z</th>
                                <th style="min-width: 220px;">Perhitungan Batas Bawah</th>
                                <th style="min-width: 130px;" class="is-kanan">Rentang Nilai</th>
                                <th style="min-width: 160px;" class="is-kanan">Realisasi Sebaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in data.pita" :key="p.kode">
                                <td>
                                    <div class="pa-kdg__grade-cell">
                                        <span class="pa-grade-pill" :class="getGradeTierClass(p.kode || p.nama)">
                                            {{ labelGrade(p.kode) }}
                                        </span>
                                        <span class="pa-kdg__grade-name">{{ p.nama }}</span>
                                    </div>
                                </td>
                                <td>
                                    <code class="pa-z-code">{{ p.batas_z === null ? 'Terendah' : `z = ${angka(p.batas_z, 2)}` }}</code>
                                </td>
                                <td>
                                    <code v-if="p.batas_z !== null" class="pa-kdg__calc-code">
                                        {{ angka(data.langkah.rata) }} {{ p.batas_z < 0 ? '−' : '+' }} {{ angka(Math.abs(p.batas_z), 2) }} &times; {{ angka(data.langkah.sd) }} = <b>{{ angka(p.batas_mentah) }}</b>
                                    </code>
                                    <span v-else class="pa-kdg__calc-base">Grade terendah: nilai di bawah batas atas grade C</span>
                                    <small v-if="p.batas_mentah !== null && p.batas_mentah > 100" class="pa-kdg__calc-warn"> (di atas 100 — tidak tercapai)</small>
                                </td>
                                <td class="is-kanan">
                                    <span class="pa-rentang-text">{{ angka(p.nilai_dari) }} – {{ p.nilai_sampai >= 100 ? '100' : `< ${angka(p.nilai_sampai)}` }}</span>
                                </td>
                                <td class="is-kanan">
                                    <div class="pa-sebaran-cell">
                                        <div class="pa-sebaran-val">
                                            <strong>{{ p.jumlah }} org</strong>
                                            <small>({{ persen(p.jumlah) }})</small>
                                        </div>
                                        <div class="pa-sebaran-track">
                                            <div
                                                class="pa-sebaran-fill"
                                                :class="getGradeTierClass(p.kode || p.nama)"
                                                :style="{ width: `${totalPita > 0 ? Math.min(100, (p.jumlah / totalPita) * 100) : 0}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="font-bold">Total Populasi Dinilai</td>
                                <td class="is-kanan"><b>{{ totalPita }} org</b> (100%)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- 4. Tabel Daftar Anggota Grup -->
            <section class="pa-kdg__section">
                <div class="pa-kdg__section-header">
                    <div>
                        <h4 class="pa-kdg__section-title">
                            <HcisIcon name="people-fill" size="xs" />
                            Daftar Anggota &amp; Posisi Nilai Terhadap Rata-rata
                        </h4>
                        <p class="pa-kdg__section-sub">
                            Menampilkan {{ anggotaTampil.length }} dari {{ data.anggota.length }} karyawan dalam grup ini
                        </p>
                    </div>
                    <div class="pa-kdg__search-box">
                        <HcisIcon name="search" size="2xs" class="pa-kdg__search-icon" />
                        <input
                            v-model="cari"
                            type="text"
                            placeholder="Cari nama atau NIP..."
                            class="pa-kdg__search-input"
                            aria-label="Cari anggota grup"
                        />
                        <button
                            v-if="cari"
                            type="button"
                            class="pa-kdg__search-clear"
                            title="Hapus pencarian"
                            @click="cari = ''"
                        >
                            <HcisIcon name="x" size="xs" />
                        </button>
                    </div>
                </div>

                <div class="pa-kdg__table-wrap is-scrollable">
                    <table class="pa-kdg__table">
                        <thead>
                            <tr>
                                <th style="width: 48px;" class="text-center">No</th>
                                <th style="min-width: 170px;">Karyawan</th>
                                <th style="min-width: 110px;">Level</th>
                                <th style="width: 100px;" class="is-kanan">Nilai Final (x)</th>
                                <th style="width: 115px;" class="is-kanan">Deviasi (x &minus; &mu;)</th>
                                <th style="width: 110px;" class="is-kanan">Skor z</th>
                                <th style="width: 90px;" class="text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(a, i) in anggotaTampil" :key="a.participant_id">
                                <td class="text-center text-muted font-num">{{ i + 1 }}</td>
                                <td>
                                    <div class="pa-kdg__karyawan-cell">
                                        <strong class="pa-kdg__karyawan-nama">{{ a.nama }}</strong>
                                        <small class="pa-kdg__karyawan-nip">{{ a.kode_karyawan }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="pa-kdg__level-chip">{{ a.level || '–' }}</span>
                                </td>
                                <td class="is-kanan font-num font-bold">{{ angka(a.final, 2) }}</td>
                                <td class="is-kanan font-num" :class="a.selisih >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-700'">
                                    {{ tanda(a.selisih) }}
                                </td>
                                <td class="is-kanan font-num">
                                    <code class="pa-z-code">{{ tanda(a.z, 2) }}</code>
                                </td>
                                <td class="text-center">
                                    <!-- Grade berlaku (kalibrasi forum bila ada); grade menurut pita disebut bila berbeda. -->
                                    <span class="pa-grade-pill" :class="getGradeTierClass(a.grade_final || a.grade)">
                                        {{ labelGrade(a.grade_final || a.grade) }}
                                    </span>
                                    <small
                                        v-if="a.grade_sistem && a.grade_final && a.grade_sistem !== a.grade_final"
                                        class="pa-kdg__grade-sistem"
                                        data-uji="grade-sistem"
                                        :title="`Menurut pita kurva: ${labelGrade(a.grade_sistem)}; grade berlaku hasil kalibrasi.`"
                                    >
                                        pita: {{ labelGrade(a.grade_sistem) }}
                                    </small>
                                </td>
                            </tr>
                            <tr v-if="!anggotaTampil.length">
                                <td colspan="7" class="pa-kdg__empty-cell">
                                    <HcisIcon name="search" size="sm" class="text-muted mb-1" />
                                    <p class="mb-0">Tidak ditemukan karyawan yang sesuai dengan kata kunci pencarian.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <template #footer>
            <HcisButton label="Tutup" variant="secondary" size="sm" @click="$emit('update:modelValue', false)" />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisSkeleton } from '@/components/ui';
import paApi from '../../api/paApi.js';

/* Detail perhitungan kurva normal satu grup: simbol, langkah μ dan σ dengan angka sebenarnya,
   turunan batas tiap grade, dan anggota beserta posisinya terhadap rata-rata. Semua angka
   dari server (PaKurvaService::detailGrup) — layar tidak menghitung ulang. */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycleId: { type: Number, required: true },
    grupId: { type: Number, default: null },
});
defineEmits(['update:modelValue']);

const SIMBOL = [
    { simbol: 'n', nama: 'Jumlah Anggota', arti: 'Banyaknya peserta grup yang hasilnya sudah dihitung.' },
    { simbol: 'x', nama: 'Nilai Final', arti: 'Nilai akhir satu peserta (KPI × bobot KPI + Kompetensi × bobot kompetensi).' },
    { simbol: 'Σx', nama: 'Total Nilai (Sigma)', arti: 'Penjumlahan nilai Final seluruh anggota grup.' },
    { simbol: 'μ', nama: 'Rata-rata (Mean)', arti: 'Titik tengah kurva distribusi normal: Σx ÷ n.' },
    { simbol: 'x − μ', nama: 'Deviasi / Selisih', arti: 'Jarak nilai seseorang terhadap rata-rata (+ di atas, − di bawah).' },
    { simbol: 'σ', nama: 'Simpangan Baku (Std Dev)', arti: 'Tingkat dispersi/persebaran nilai; makin besar, makin bervariasi.' },
    { simbol: 'z', nama: 'Skor z (Z-Score)', arti: 'Jarak nilai dari rata-rata dalam satuan standar deviasi: (x − μ) ÷ σ.' },
    { simbol: 'μ + z × σ', nama: 'Batas Bawah Grade', arti: 'Nilai Final minimum untuk memperoleh grade tertentu.' },
];

const data = ref(null);
const memuat = ref(false);
const galat = ref('');
const cari = ref('');
const bukaRumus = ref(false);

const angka = (v, d = 4) => (v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d === 0 ? 0 : Math.min(d, 2), maximumFractionDigits: d }));
const tanda = (v, d = 4) => (v === null || v === undefined ? '–' : `${v > 0 ? '+' : v < 0 ? '−' : ''}${angka(Math.abs(v), d)}`);
const labelGrade = (k) => (k ? String(k).replace(/_PLUS/gi, '+').replace(/_MINUS/gi, '-') : '–');
const totalPita = computed(() => (data.value?.pita || []).reduce((t, p) => t + p.jumlah, 0));
const persen = (j) => (totalPita.value ? `${Math.round((j / totalPita.value) * 1000) / 10}%`.replace('.', ',') : '0%');
const bedaBeku = computed(() => {
    const l = data.value?.langkah;
    return data.value?.terkunci && l && l.rata_mentah !== null && l.rata !== null && Math.abs(l.rata_mentah - l.rata) > 0.0001;
});
const anggotaTampil = computed(() => {
    const q = cari.value.trim().toLowerCase();
    const a = data.value?.anggota || [];
    return q ? a.filter((x) => `${x.nama} ${x.kode_karyawan}`.toLowerCase().includes(q)) : a;
});

function getGradeTierClass(code) {
    if (!code) return 'is-grade-c';
    const c = String(code).toUpperCase().trim();
    if (c.startsWith('A')) return 'is-grade-a';
    if (c.startsWith('B')) return 'is-grade-b';
    if (c.startsWith('C')) return 'is-grade-c';
    if (c.startsWith('D')) return 'is-grade-d';
    if (c.startsWith('E')) return 'is-grade-e';
    return 'is-grade-c';
}

async function muat() {
    if (!props.grupId) return;
    memuat.value = true;
    galat.value = '';
    cari.value = '';
    try {
        data.value = await paApi.kurvaGrupDetail(props.cycleId, props.grupId);
    } catch (e) {
        data.value = null;
        galat.value = e?.message || 'Rincian perhitungan kurva gagal dimuat.';
    } finally {
        memuat.value = false;
    }
}

watch(() => [props.modelValue, props.grupId], ([buka]) => { if (buka) muat(); }, { immediate: true });
</script>

<style scoped>
.pa-kdg {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    width: 100%;
}

.pa-kdg-galat {
    color: #dc2626;
    font-size: 0.85rem;
    margin: 0;
    padding: 0.75rem 1rem;
    background: #fef2f2;
    border-radius: 8px;
    border: 1px solid #fecaca;
}

/* 1. Executive Hero Card */
.pa-kdg__hero {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.85rem 1.1rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.95) 100%);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-kdg__hero-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pa-kdg__hero-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-kdg__hero-nama {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-kdg__hero-level {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-kdg__hero-level b {
    color: #334155;
    font-weight: 600;
}

.pa-kdg__stat-grid {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-kdg-stat-mini {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    min-width: 95px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-kdg-stat-mini__label {
    font-size: 0.64rem;
    font-weight: 650;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.pa-kdg-stat-mini__val {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

/* 2. Unified Accordion */
.pa-kdg__accordion-card {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.pa-kdg__accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.95rem;
    cursor: pointer;
    user-select: none;
    transition: background 150ms ease;
}

.pa-kdg__accordion-header:hover {
    background: rgba(241, 245, 249, 0.7);
}

.pa-kdg__accordion-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-kdg__accordion-icon-box {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-kdg__accordion-title {
    font-size: 0.78rem;
    font-weight: 750;
    color: #312e81;
}

.pa-kdg__accordion-sub {
    margin: 0.1rem 0 0;
    font-size: 0.70rem;
    color: #64748b;
}

.pa-kdg__accordion-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.70rem;
    font-weight: 600;
    color: #4f46e5;
    flex-shrink: 0;
}

.pa-kdg__accordion-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.85rem 1rem 1rem;
    border-top: 1px dashed #cbd5e1;
    background: rgba(248, 250, 252, 0.6);
}

.pa-kdg__calc-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.pa-kdg__calc-col {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-kdg__col-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.74rem;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-kdg__simbol-grid {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-kdg__simbol-row {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
    padding: 0.4rem 0.55rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
}

.pa-kdg__simbol-badge {
    flex: 0 0 68px;
    font-family: ui-monospace, monospace;
    font-size: 0.70rem;
    font-weight: 800;
    color: #4338ca;
    text-align: center;
    padding: 0.1rem 0;
    border-radius: 4px;
    background: #eef2ff;
}

.pa-kdg__simbol-desc {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.pa-kdg__simbol-desc strong {
    font-size: 0.72rem;
    color: #1e293b;
}

.pa-kdg__simbol-desc span {
    font-size: 0.68rem;
    color: #64748b;
    line-height: 1.35;
}

.pa-kdg__langkah-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-kdg__langkah-card {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
}

.pa-kdg__step-num {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.66rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-kdg__step-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-kdg__step-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-kdg__step-formula {
    font-family: ui-monospace, monospace;
    font-size: 0.72rem;
    color: #0f172a;
    word-break: break-word;
}

.pa-kdg__step-content small {
    font-size: 0.66rem;
    color: #94a3b8;
}

.pa-kdg__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    font-size: 0.74rem;
    line-height: 1.4;
}

.pa-kdg__catatan strong {
    display: block;
    font-size: 0.74rem;
    margin-bottom: 0.1rem;
}

.pa-kdg__catatan p {
    margin: 0;
    font-size: 0.70rem;
}

.pa-kdg__catatan.is-info {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.pa-kdg__catatan.is-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

/* 3. Section and Table Styles */
.pa-kdg__section {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-kdg__section-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
}

.pa-kdg__section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.85rem;
    font-weight: 750;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-kdg__section-sub {
    margin: 0.1rem 0 0;
    font-size: 0.71rem;
    color: #64748b;
}

.pa-kdg__search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.pa-kdg__search-icon {
    position: absolute;
    left: 0.65rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-kdg__search-input {
    padding: 0.35rem 1.8rem 0.35rem 2rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.76rem;
    color: #0f172a;
    background: #ffffff;
    min-width: 200px;
    transition: all 150ms ease;
}

.pa-kdg__search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.pa-kdg__search-clear {
    position: absolute;
    right: 0.45rem;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem;
}

.pa-kdg__search-clear:hover {
    color: #475569;
}

.pa-kdg__table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 10px);
    overflow-x: auto;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-kdg__table-wrap.is-scrollable {
    max-height: 42vh;
    overflow-y: auto;
}

.pa-kdg__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.72rem;
}

.pa-kdg__table th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: 0.45rem 0.65rem;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pa-kdg__table td {
    padding: 0.45rem 0.65rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.pa-kdg__table tfoot td {
    font-weight: 700;
    background: #f8fafc;
    border-bottom: 0;
    color: #334155;
    padding: 0.5rem 0.65rem;
}

.pa-kdg__table .is-kanan {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.pa-kdg__grade-cell {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-kdg__grade-name {
    font-size: 0.70rem;
    color: #64748b;
}

.pa-kdg__calc-code {
    font-family: ui-monospace, monospace;
    font-size: 0.68rem;
    color: #0f172a;
}

.pa-kdg__calc-base {
    color: #94a3b8;
    font-size: 0.68rem;
    font-style: italic;
}

.pa-kdg__calc-warn {
    color: #b45309;
    font-size: 0.66rem;
}

.pa-kdg__karyawan-cell {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-kdg__karyawan-nama {
    font-size: 0.76rem;
    color: #0f172a;
    font-weight: 700;
}

.pa-kdg__karyawan-nip {
    font-size: 0.66rem;
    color: #94a3b8;
    font-family: ui-monospace, monospace;
}

.pa-kdg__grade-sistem {
    display: block;
    margin-top: 2px;
    font-size: 0.66rem;
    color: var(--hcis-text-muted);
}

.pa-kdg__level-chip {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.66rem;
    font-weight: 600;
}

.pa-kdg__empty-cell {
    text-align: center;
    padding: 2rem !important;
    color: #64748b;
    font-size: 0.74rem;
}

/* Common Grade Pills & Metrics */
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

.pa-grade-pill.is-grade-a { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
.pa-grade-pill.is-grade-b { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.pa-grade-pill.is-grade-c { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.pa-grade-pill.is-grade-d { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.pa-grade-pill.is-grade-e { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

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

/* Responsive */
@media (max-width: 840px) {
    .pa-kdg__calc-grid {
        grid-template-columns: 1fr;
    }
    .pa-kdg__hero {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-kdg__stat-grid {
        width: 100%;
    }
    .pa-kdg-stat-mini {
        flex: 1 1 calc(33.333% - 0.5rem);
    }
}

@media (max-width: 640px) {
    .pa-kdg {
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-kdg__hero {
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
        gap: 0.45rem;
    }
    .pa-kdg__hero-nama {
        font-size: 0.92rem;
    }
    .pa-kdg__hero-level {
        font-size: 0.66rem;
    }
    .pa-kdg__stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.3rem;
        width: 100%;
    }
    .pa-kdg-stat-mini {
        padding: 0.3rem 0.45rem;
        border-radius: 6px;
    }
    .pa-kdg-stat-mini__label {
        font-size: 0.58rem;
    }
    .pa-kdg-stat-mini__val {
        font-size: 0.95rem;
    }
    .pa-kdg__accordion-card {
        border-radius: var(--hcis-radius-md, 10px);
    }
    .pa-kdg__accordion-header {
        padding: 0.55rem 0.75rem;
        gap: 0.4rem;
    }
    .pa-kdg__accordion-title {
        font-size: 0.74rem;
    }
    .pa-kdg__accordion-sub {
        display: none;
    }
    .pa-kdg__accordion-toggle {
        font-size: 0.66rem;
    }
    .pa-kdg__accordion-body {
        padding: 0.65rem 0.75rem;
        gap: 0.65rem;
    }
    .pa-kdg__simbol-badge {
        flex: 0 0 54px;
        font-size: 0.64rem;
    }
    .pa-kdg__step-content {
        min-width: 0;
    }
    .pa-kdg__step-formula {
        font-size: 0.66rem;
        word-break: break-word;
        white-space: normal;
    }
    .pa-kdg__tabel-wrap {
        border-radius: var(--hcis-radius-sm, 6px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        box-sizing: border-box;
    }
    .pa-kdg__tabel th,
    .pa-kdg__tabel td {
        padding: 0.35rem 0.45rem;
        font-size: 0.68rem;
    }
}
</style>
