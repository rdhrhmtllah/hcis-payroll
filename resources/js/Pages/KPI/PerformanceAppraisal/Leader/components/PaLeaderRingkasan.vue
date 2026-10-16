<template>
    <div class="pa-lr" data-uji="leader-ringkasan">
        <!-- Non-destructive Frosted Glass Floating Sync Badge -->
        <transition name="hcis-loading-pop">
            <div v-if="loading" class="pa-lr-syncing-badge" aria-live="polite">
                <div class="pa-lr-spinner-box" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="pa-lr-spinner-svg">
                        <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-lr-spinner-track" />
                        <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-lr-spinner-head" />
                    </svg>
                </div>
                <span class="pa-lr-syncing-label">Menyinkronkan data periode…</span>
            </div>
        </transition>

        <!-- 1. High-Impact Executive Bento KPI Highlight Cards -->
        <section class="pa-lr-kpi-grid" :class="{ 'is-loading-dimmed': loading }" aria-label="Angka kunci tim">
            <!-- Card 1: Rata-rata Nilai Tim -->
            <div
                class="pa-lr-kpi-card is-teal"
                :class="{ 'is-loading-card': loading }"
                role="button"
                tabindex="0"
                title="Lihat sebaran grade & nilai tim"
                @click="fokusKeGrade"
                @keydown.enter="fokusKeGrade"
                @keydown.space.prevent="fokusKeGrade"
            >
                <div class="pa-lr-kpi-card__head">
                    <div class="pa-lr-kpi-card__badge-wrap">
                        <div class="pa-lr-kpi-card__icon is-teal">
                            <HcisIcon name="award-fill" size="2xs" />
                        </div>
                        <span class="pa-lr-kpi-card__kicker">RATA-RATA NILAI TIM</span>
                    </div>
                    <span class="pa-lr-kpi-card__arrow" title="Buka Sebaran Grade">
                        <HcisIcon name="arrow-up-right" size="2xs" />
                    </span>
                </div>

                <div class="pa-lr-kpi-card__body">
                    <strong class="pa-lr-kpi-val text-teal" data-uji="rata-tim">{{ rataTim === null ? '–' : desimal(rataTim) }}</strong>
                    <div class="pa-lr-kpi-card__sub-wrap">
                        <span v-if="selisihRata !== null" class="pa-trend-badge" :class="kelasDelta(selisihRata)" data-uji="delta-rata">
                            <HcisIcon :name="ikonDelta(selisihRata)" size="2xs" />
                            {{ tandaDelta(selisihRata) }} vs {{ tren?.cycle?.kode_cycle || 'siklus lalu' }}
                        </span>
                        <span v-else class="pa-text-subtle">
                            {{ terlihat.length ? `dari ${terlihat.length} hasil yang terlihat` : 'Belum ada hasil yang dirilis' }}
                        </span>
                    </div>
                </div>

                <div class="pa-lr-kpi-card__foot">
                    <span>Lihat Sebaran Grade</span>
                    <HcisIcon name="chevron-right" size="2xs" />
                </div>
            </div>

            <!-- Card 2: Komposisi KPI vs Kompetensi (hanya hasil terlihat) -->
            <div
                class="pa-lr-kpi-card is-brand"
                :class="{ 'is-loading-card': loading }"
                role="button"
                tabindex="0"
                title="Lihat kekuatan & area pengembangan kompetensi tim"
                @click="fokusKeKompetensi"
                @keydown.enter="fokusKeKompetensi"
                @keydown.space.prevent="fokusKeKompetensi"
            >
                <div class="pa-lr-kpi-card__head">
                    <div class="pa-lr-kpi-card__badge-wrap">
                        <div class="pa-lr-kpi-card__icon is-brand">
                            <HcisIcon name="bullseye" size="2xs" />
                        </div>
                        <span class="pa-lr-kpi-card__kicker">KPI VS KOMPETENSI</span>
                    </div>
                    <span class="pa-lr-kpi-card__arrow" title="Buka Kompetensi Tim">
                        <HcisIcon name="arrow-up-right" size="2xs" />
                    </span>
                </div>

                <div class="pa-lr-kpi-card__body">
                    <div class="pa-lr-pilar" data-uji="pilar-tim">
                        <span class="pa-lr-pilar__item">
                            <small>KPI</small>
                            <strong class="text-brand" data-uji="rata-kpi">{{ rataKpi === null ? '–' : desimal(rataKpi) }}</strong>
                        </span>
                        <span class="pa-lr-pilar__sekat" aria-hidden="true" />
                        <span class="pa-lr-pilar__item">
                            <small>Kompetensi</small>
                            <strong class="text-purple" data-uji="rata-kompetensi">{{ rataKompetensi === null ? '–' : desimal(rataKompetensi) }}</strong>
                        </span>
                    </div>
                    <span class="pa-text-subtle">
                        {{ terlihat.length ? `Rata-rata ${terlihat.length} hasil yang terlihat` : 'Belum ada hasil yang dirilis' }}
                    </span>
                </div>

                <div class="pa-lr-kpi-card__foot">
                    <span>Lihat Kompetensi Tim</span>
                    <HcisIcon name="chevron-right" size="2xs" />
                </div>
            </div>

            <!-- Card 3: Hasil Terlihat -->
            <div
                class="pa-lr-kpi-card is-purple"
                :class="{ 'is-loading-card': loading }"
                role="button"
                tabindex="0"
                title="Buka daftar hasil anggota tim"
                @click="$emit('lihat-anggota', 'ALL')"
                @keydown.enter="$emit('lihat-anggota', 'ALL')"
                @keydown.space.prevent="$emit('lihat-anggota', 'ALL')"
            >
                <div class="pa-lr-kpi-card__head">
                    <div class="pa-lr-kpi-card__badge-wrap">
                        <div class="pa-lr-kpi-card__icon is-purple">
                            <HcisIcon name="eye-fill" size="2xs" />
                        </div>
                        <span class="pa-lr-kpi-card__kicker">HASIL TERLIHAT</span>
                    </div>
                    <span class="pa-lr-kpi-card__arrow" title="Buka Daftar Anggota">
                        <HcisIcon name="arrow-up-right" size="2xs" />
                    </span>
                </div>

                <div class="pa-lr-kpi-card__body">
                    <strong class="pa-lr-kpi-val text-purple">{{ terlihat.length }} <small class="pa-kpi-sub-total">/ {{ anggota.length }}</small></strong>
                    <span class="pa-text-subtle">
                        {{ jumlahTerbit }} terbit ke karyawan · {{ jumlahHanyaLeader }} baru dirilis ke Anda
                    </span>
                </div>

                <div class="pa-lr-kpi-card__foot">
                    <span>Buka Daftar Anggota</span>
                    <HcisIcon name="chevron-right" size="2xs" />
                </div>
            </div>

            <!-- Card 4: Kompetensi di bawah KKM -->
            <div
                class="pa-lr-kpi-card"
                :class="[kompetensiDiBawahKkm.length ? 'is-amber' : 'is-success', { 'is-loading-card': loading }]"
                role="button"
                tabindex="0"
                title="Lihat kompetensi tim yang di bawah KKM"
                data-uji="kartu-kkm"
                @click="fokusKeKompetensi"
                @keydown.enter="fokusKeKompetensi"
                @keydown.space.prevent="fokusKeKompetensi"
            >
                <div class="pa-lr-kpi-card__head">
                    <div class="pa-lr-kpi-card__badge-wrap">
                        <div class="pa-lr-kpi-card__icon" :class="kompetensiDiBawahKkm.length ? 'is-amber' : 'is-success'">
                            <HcisIcon :name="kompetensiDiBawahKkm.length ? 'graph-up-arrow' : 'check-circle-fill'" size="2xs" />
                        </div>
                        <span class="pa-lr-kpi-card__kicker">DI BAWAH KKM</span>
                    </div>
                    <span class="pa-lr-kpi-card__arrow" title="Buka Kompetensi Tim">
                        <HcisIcon name="arrow-up-right" size="2xs" />
                    </span>
                </div>

                <div class="pa-lr-kpi-card__body">
                    <strong class="pa-lr-kpi-val" :class="kompetensiDiBawahKkm.length ? 'text-amber' : 'text-success'" data-uji="jumlah-bawah-kkm">
                        {{ kompetensiDiBawahKkm.length }} <small class="pa-kpi-sub-total">/ {{ kompetensiTim.length }}</small>
                    </strong>
                    <span class="pa-text-subtle">
                        {{ !kompetensiTim.length ? 'Belum ada nilai kompetensi yang terlihat' : kompetensiDiBawahKkm.length ? 'kompetensi perlu dikembangkan' : 'Semua kompetensi memenuhi KKM' }}
                    </span>
                </div>

                <div class="pa-lr-kpi-card__foot">
                    <span>Lihat Kompetensi Tim</span>
                    <HcisIcon name="chevron-right" size="2xs" />
                </div>
            </div>
        </section>

        <!-- 2. Middle Section Grid: Perhatian & Kemajuan & Sebaran & 9-Box -->
        <div class="pa-lr-grid" :class="{ 'is-loading-dimmed': loading }">
            <!-- 2.1 Kekuatan & Area Pengembangan Kompetensi Tim (selebar penuh) -->
            <PaLeaderKompetensiTim
                :kompetensi="kompetensiTim"
                :ringkas="isMobile"
                class="pa-lr-card pa-lr-card--lebar"
                @detail="(a) => $emit('detail', a)"
            />

            <!-- 2.3 Sebaran Grade Tim -->
            <HcisCard
                id="pa-lr-section-grade"
                title="Sebaran Grade Tim"
                :description="`${terlihat.length} hasil evaluasi resmi terlihat`"
                icon="award-fill"
                sentiment="teal"
                variant="glass"
                pattern="rings"
                :size="isMobile ? 'sm' : 'md'"
                class="pa-lr-card"
            >
                <template v-if="terlihat.length">
                    <ul class="pa-lr-grade" role="list">
                        <li v-for="g in sebaranGrade" :key="g.kode">
                            <span class="pa-lr-grade__kode" :class="kelasGrade(g.kode)">{{ labelGrade(g.kode) }}</span>
                            <span class="pa-lr-grade__bar">
                                <i :class="kelasGrade(g.kode)" :style="{ width: `${persenDari(g.jumlah, terlihat.length)}%` }" />
                            </span>
                            <span class="pa-lr-grade__jumlah">
                                {{ g.jumlah }} <small>({{ persenDari(g.jumlah, terlihat.length) }}%)</small>
                            </span>
                        </li>
                    </ul>

                    <div class="pa-lr-ekstrem">
                        <button
                            v-if="teratas"
                            type="button"
                            class="pa-lr-ekstrem__item is-atas"
                            :title="`Buka detail ${teratas.nama}`"
                            @click="$emit('detail', teratas)"
                        >
                            <div class="pa-lr-ekstrem__icon" aria-hidden="true">
                                <HcisIcon name="trophy-fill" size="xs" />
                            </div>
                            <span class="pa-lr-ekstrem__text">
                                <small>Nilai tertinggi</small>
                                <b>{{ teratas.nama }}</b>
                            </span>
                            <strong class="text-success">{{ desimal(teratas.nilai_akhir) }}</strong>
                        </button>
                        <button
                            v-if="terbawah && terbawah !== teratas"
                            type="button"
                            class="pa-lr-ekstrem__item is-bawah"
                            :title="`Buka detail ${terbawah.nama}`"
                            @click="$emit('detail', terbawah)"
                        >
                            <div class="pa-lr-ekstrem__icon" aria-hidden="true">
                                <HcisIcon name="life-preserver" size="xs" />
                            </div>
                            <span class="pa-lr-ekstrem__text">
                                <small>Perlu pendampingan</small>
                                <b>{{ terbawah.nama }}</b>
                            </span>
                            <strong class="text-amber">{{ desimal(terbawah.nilai_akhir) }}</strong>
                        </button>
                    </div>
                </template>

                <div v-else class="pa-lr-kosong">
                    <div class="pa-lr-kosong__icon is-neutral" aria-hidden="true">
                        <HcisIcon name="eye-slash" size="md" />
                    </div>
                    <strong>Belum ada hasil yang terlihat</strong>
                    <p>Nilai dan grade muncul setelah admin merilis hasil ke leader atau menerbitkannya ke karyawan.</p>
                </div>

                <div v-if="belumTerlihat" class="pa-lr-catatan-wrap">
                    <p class="pa-lr-catatan">
                        <HcisIcon name="info-circle" size="2xs" />
                        {{ belumTerlihat }} anggota lain belum dirilis ke Anda — tidak dihitung di sini.
                    </p>
                </div>
            </HcisCard>

            <!-- 2.4 Posisi 9-Box HAV -->
            <HcisCard
                id="pa-lr-section-hav"
                title="Posisi 9-Box HAV"
                description="↑ Sumbu Y: Potensi &amp; Kompetensi · → Sumbu X: Kinerja"
                icon="grid-3x3-gap-fill"
                sentiment="purple"
                variant="glass"
                pattern="dots"
                :size="isMobile ? 'sm' : 'md'"
                class="pa-lr-card"
            >
                <div class="pa-lr-hav-grid" role="grid" aria-label="Sebaran anggota pada 9-box HAV">
                    <div
                        v-for="k in kotakHav"
                        :key="k.nomor"
                        class="pa-lr-hav__kotak"
                        :class="[`is-${k.sentimen}`, { 'is-kosong': !k.anggota.length }]"
                        role="gridcell"
                        :title="k.anggota.length ? `${k.nama}: ${k.anggota.map((a) => a.nama).join(', ')}` : k.nama"
                        :data-uji="`hav-${k.nomor}`"
                    >
                        <span class="pa-lr-hav__nama">{{ k.nama }}</span>
                        <strong>{{ k.anggota.length }}</strong>
                    </div>
                </div>
            </HcisCard>
        </div>

        <!-- 3. Bottom Section: Tren vs Siklus Sebelumnya -->
        <HcisCard
            id="pa-lr-section-tren"
            title="Tren vs Siklus Sebelumnya"
            :description="tren?.cycle ? `Pembanding: ${tren.cycle.nama_cycle || tren.cycle.kode_cycle}` : 'Perbandingan performa tim lintas siklus evaluasi'"
            icon="graph-up-arrow"
            sentiment="teal"
            variant="glass"
            pattern="grid"
            :size="isMobile ? 'sm' : 'md'"
            class="pa-lr-card"
            :class="{ 'is-loading-dimmed': loading }"
        >
            <template #actions>
                <div v-if="tren?.cycle && dibandingkan.length" class="pa-lr-tren__ringkas">
                    <span class="is-naik"><HcisIcon name="arrow-up-right" size="2xs" /> {{ jumlahArah.naik }} naik</span>
                    <span class="is-turun"><HcisIcon name="arrow-down-right" size="2xs" /> {{ jumlahArah.turun }} turun</span>
                    <span class="is-tetap"><HcisIcon name="dash" size="2xs" /> {{ jumlahArah.tetap }} tetap</span>
                </div>
            </template>

            <template v-if="tren?.cycle && dibandingkan.length">
                <ul class="pa-lr-tren-grid" role="list">
                    <li v-for="a in dibandingkan" :key="a.participant_id">
                        <button
                            type="button"
                            class="pa-lr-tren__baris"
                            :title="`Buka detail ${a.nama}`"
                            @click="$emit('detail', a)"
                        >
                            <div class="pa-lr-tren__identity">
                                <span class="pa-lr-tren__avatar" aria-hidden="true">{{ inisial(a.nama) }}</span>
                                <b>{{ a.nama }}</b>
                            </div>
                            <span class="pa-lr-tren__nilai">
                                {{ desimal(a.nilai_sebelum) }} <small>{{ labelGrade(a.grade_sebelum) }}</small>
                                <HcisIcon name="arrow-right" size="2xs" />
                                {{ desimal(a.nilai_akhir) }} <small>{{ labelGrade(a.kode_grade) }}</small>
                            </span>
                            <span class="pa-trend-badge" :class="kelasDelta(a.selisih)">
                                <HcisIcon :name="ikonDelta(a.selisih)" size="2xs" />
                                {{ tandaDelta(a.selisih) }}
                            </span>
                        </button>
                    </li>
                </ul>
            </template>

            <div v-else class="pa-lr-kosong">
                <div class="pa-lr-kosong__icon is-neutral" aria-hidden="true">
                    <HcisIcon name="clock-history" size="md" />
                </div>
                <strong>{{ tren?.cycle ? 'Belum ada pembanding yang terlihat' : 'Belum ada siklus sebelumnya' }}</strong>
                <p>{{ tren?.cycle ? 'Tren muncul bila hasil siklus ini dan siklus sebelumnya sama-sama sudah dirilis ke Anda.' : 'Ini siklus pertama tim Anda; tren akan tersedia pada siklus berikutnya.' }}</p>
            </div>
        </HcisCard>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisIcon,
} from '@/components/ui';
import { URUTAN_KOTAK_HAV, sentimenKotak } from '../../utils/havKotak.js';
import PaLeaderKompetensiTim from './PaLeaderKompetensiTim.vue';

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mql = null;
const updateIsMobile = (e) => {
    isMobile.value = e.matches;
};
onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        mql = window.matchMedia('(max-width: 768px)');
        isMobile.value = mql.matches;
        mql.addEventListener?.('change', updateIsMobile);
    }
});
onUnmounted(() => {
    if (mql && mql.removeEventListener) {
        mql.removeEventListener('change', updateIsMobile);
    }
});

/* Ringkasan untuk leader: membaca HASIL timnya — nilai, grade, kekuatan dan area
   pengembangan kompetensi, tren — HANYA dari hasil yang boleh ia lihat (terbit atau
   dirilis). Hasil lain dihitung sebagai "belum terlihat", tidak pernah ditebak. Progres
   pengisian sengaja tidak di sini; itu ada di tab Daftar Anggota. */
const props = defineProps({
    tim: { type: Object, required: true },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['detail', 'lihat-anggota']);

const URUTAN_GRADE = ['A', 'B_PLUS', 'B', 'C_PLUS', 'C', 'D'];

const anggota = computed(() => (props.tim?.anggota || []).filter((a) => a.status_peserta !== 'DIKECUALIKAN'));
const tren = computed(() => props.tim?.tren || null);
const terlihat = computed(() => anggota.value.filter((a) => a.nilai_akhir !== null && a.nilai_akhir !== undefined));
const belumTerlihat = computed(() => anggota.value.length - terlihat.value.length);
const jumlahTerbit = computed(() => anggota.value.filter((a) => a.terbit).length);
const jumlahHanyaLeader = computed(() => anggota.value.filter((a) => a.dirilis_ke_leader && !a.terbit).length);

const desimal = (v) => (v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const labelGrade = (k) => (k ? String(k).replace(/_PLUS/g, '+').replace(/_MINUS/g, '−') : '–');
const persenDari = (n, total) => (total ? Math.round((n / total) * 100) : 0);
const tandaDelta = (d) => (d > 0 ? `+${desimal(d)}` : d < 0 ? `−${desimal(Math.abs(d))}` : '±0,00');
const kelasDelta = (d) => (d > 0 ? 'is-naik' : d < 0 ? 'is-turun' : 'is-tetap');
const ikonDelta = (d) => (d > 0 ? 'arrow-up-right' : d < 0 ? 'arrow-down-right' : 'dash');
const kelasGrade = (k) => `is-g-${String(k || '').charAt(0).toLowerCase() || 'x'}`;

const inisial = (value) => String(value || 'PA')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

const rataTim = computed(() => (terlihat.value.length
    ? Math.round((terlihat.value.reduce((s, a) => s + Number(a.nilai_akhir), 0) / terlihat.value.length) * 100) / 100
    : null));
const selisihRata = computed(() => (rataTim.value !== null && tren.value?.rata_rata_sebelum !== null && tren.value?.rata_rata_sebelum !== undefined
    ? Math.round((rataTim.value - Number(tren.value.rata_rata_sebelum)) * 100) / 100
    : null));

const rataDari = (kunci) => {
    const ada = terlihat.value.filter((a) => a[kunci] !== null && a[kunci] !== undefined);
    return ada.length ? Math.round((ada.reduce((t, a) => t + Number(a[kunci]), 0) / ada.length) * 100) / 100 : null;
};
const rataKpi = computed(() => rataDari('nilai_kpi'));
const rataKompetensi = computed(() => rataDari('nilai_kompetensi'));

const kompetensiTim = computed(() => props.tim?.kompetensi_tim || []);
const dibawahKkm = (k) => k.nilai_ambang !== null && k.nilai_ambang !== undefined && Number(k.rata_rata) < Number(k.nilai_ambang);
const kompetensiDiBawahKkm = computed(() => kompetensiTim.value.filter(dibawahKkm));

const sebaranGrade = computed(() => {
    const hitung = {};
    terlihat.value.forEach((a) => { const k = a.kode_grade || 'TANPA'; hitung[k] = (hitung[k] || 0) + 1; });
    const urut = [...URUTAN_GRADE, ...Object.keys(hitung).filter((k) => !URUTAN_GRADE.includes(k))];
    return urut.filter((k) => hitung[k]).map((kode) => ({ kode, jumlah: hitung[kode] }));
});
const urutNilai = computed(() => [...terlihat.value].sort((x, y) => Number(y.nilai_akhir) - Number(x.nilai_akhir)));
const teratas = computed(() => urutNilai.value[0] || null);
const terbawah = computed(() => (urutNilai.value.length > 1 ? urutNilai.value[urutNilai.value.length - 1] : null));

const kotakHav = computed(() => {
    const kamus = props.tim?.kamus_kuadran || [];
    const perKode = {};
    terlihat.value.forEach((a) => { if (a.kode_kuadran) (perKode[a.kode_kuadran] ||= []).push(a); });
    return URUTAN_KOTAK_HAV.map((nomor) => {
        const q = kamus.find((x) => Number(x.nomor_box) === nomor);
        return { nomor, nama: q?.nama || `Kotak ${nomor}`, sentimen: sentimenKotak(nomor), anggota: q ? perKode[q.kode] || [] : [] };
    });
});

const dibandingkan = computed(() => terlihat.value
    .filter((a) => a.nilai_sebelum !== null && a.nilai_sebelum !== undefined)
    .map((a) => ({ ...a, selisih: Math.round((Number(a.nilai_akhir) - Number(a.nilai_sebelum)) * 100) / 100 }))
    .sort((x, y) => x.selisih - y.selisih));

const jumlahArah = computed(() => ({
    naik: dibandingkan.value.filter((a) => a.selisih > 0).length,
    turun: dibandingkan.value.filter((a) => a.selisih < 0).length,
    tetap: dibandingkan.value.filter((a) => a.selisih === 0).length,
}));

/* Scroll navigation helpers */
function scrollToSection(id) {
    if (typeof document !== 'undefined') {
        const el = document.getElementById(id);
        if (el && typeof el.scrollIntoView === 'function') {
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch {
                // Safe fallback
            }
        }
    }
}

function fokusKeGrade() {
    scrollToSection('pa-lr-section-grade');
}

function fokusKeKompetensi() {
    scrollToSection('pa-lr-section-kompetensi');
}
</script>

<style scoped>
/* KPI vs Kompetensi */
.pa-lr-pilar { display: flex; align-items: center; gap: 0.85rem; }
.pa-lr-pilar__item { display: flex; flex-direction: column; gap: 0.1rem; }
.pa-lr-pilar__item small { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
.pa-lr-pilar__item strong { font-size: 1.35rem; line-height: 1.1; font-variant-numeric: tabular-nums; }
.pa-lr-pilar__sekat { width: 1px; align-self: stretch; background: #e2e8f0; }

.pa-lr {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
    max-width: 100%;
}

/* Syncing Badge & Loading Animations */
.pa-lr-syncing-badge {
    position: sticky;
    top: 4.25rem;
    z-index: 30;
    align-self: center;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.45rem 1rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(13, 148, 136, 0.25);
    box-shadow: 0 8px 24px -4px rgba(13, 148, 136, 0.2), 0 4px 8px -2px rgba(0, 0, 0, 0.06);
    pointer-events: none;
    margin-bottom: -0.5rem;
}

.pa-lr-spinner-box {
    width: 1rem;
    height: 1rem;
    position: relative;
    flex-shrink: 0;
}

.pa-lr-spinner-svg {
    width: 100%;
    height: 100%;
    animation: pa-spin 0.8s linear infinite;
}

.pa-lr-spinner-track {
    stroke: rgba(13, 148, 136, 0.2);
}

.pa-lr-spinner-head {
    stroke: var(--hcis-teal-600, #0d9488);
    stroke-linecap: round;
    stroke-dasharray: 28 60;
}

.pa-lr-syncing-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-teal-800, #115e59);
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.is-loading-dimmed {
    opacity: 0.52;
    filter: blur(0.35px);
    pointer-events: none;
    transition: opacity 220ms ease, filter 220ms ease;
}

.pa-lr-kpi-card.is-loading-card {
    position: relative;
    overflow: hidden;
}

.pa-lr-kpi-card.is-loading-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.65) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    transform: translateX(-100%);
    animation: pa-shimmer 1.4s infinite;
    pointer-events: none;
}

@keyframes pa-spin {
    100% { transform: rotate(360deg); }
}

@keyframes pa-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Loading Pop Transition */
.hcis-loading-pop-enter-active,
.hcis-loading-pop-leave-active {
    transition: opacity 200ms cubic-bezier(0.16, 1, 0.3, 1), transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-loading-pop-enter-from,
.hcis-loading-pop-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.94);
}

/* 1. Bento High-Impact KPI Highlight Grid */
.pa-lr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.pa-lr-kpi-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.15rem 1.25rem;
    min-height: 10.75rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.02);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    user-select: none;
}

.pa-lr-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    background: rgba(255, 255, 255, 0.98);
}

.pa-lr-kpi-card.is-teal:hover { border-color: rgba(13, 148, 136, 0.45); }
.pa-lr-kpi-card.is-brand:hover { border-color: rgba(99, 102, 241, 0.45); }
.pa-lr-kpi-card.is-purple:hover { border-color: rgba(168, 85, 247, 0.45); }
.pa-lr-kpi-card.is-amber:hover { border-color: rgba(245, 158, 11, 0.45); }
.pa-lr-kpi-card.is-success:hover { border-color: rgba(34, 197, 94, 0.45); }

.pa-lr-kpi-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    height: 1.85rem;
}

.pa-lr-kpi-card__badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.pa-lr-kpi-card__icon {
    display: grid;
    place-items: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 8px;
    flex-shrink: 0;
}

.pa-lr-kpi-card__icon.is-teal { background: rgba(13, 148, 136, 0.12); color: #0d9488; }
.pa-lr-kpi-card__icon.is-brand { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.pa-lr-kpi-card__icon.is-purple { background: rgba(168, 85, 247, 0.12); color: #a855f7; }
.pa-lr-kpi-card__icon.is-amber { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-lr-kpi-card__icon.is-success { background: rgba(34, 197, 94, 0.12); color: #16a34a; }

.pa-lr-kpi-card__kicker {
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-lr-kpi-card__arrow {
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    color: var(--hcis-text-muted, #94a3b8);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.pa-lr-kpi-card:hover .pa-lr-kpi-card__arrow {
    transform: translate(2px, -2px);
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(241, 245, 249, 0.85);
}

.pa-lr-kpi-card__body {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    min-height: 4.6rem;
    padding: 0.35rem 0;
}

.pa-lr-kpi-val {
    font-size: 1.75rem;
    font-weight: 850;
    line-height: 1.15;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.pa-lr-kpi-val.text-teal { color: #0d9488; }
.pa-lr-kpi-val.text-brand { color: #6366f1; }
.pa-lr-kpi-val.text-purple { color: #a855f7; }
.pa-lr-kpi-val.text-amber { color: #d97706; }
.pa-lr-kpi-val.text-success { color: #16a34a; }

.pa-kpi-sub-total {
    font-size: 0.95rem;
    font-weight: 600;
    color: #94a3b8;
}

.pa-lr-kpi-card__sub-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-lr-bar {
    height: 6px;
    border-radius: 999px;
    background: #eef2ff;
    overflow: hidden;
}

.pa-lr-bar i {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #818cf8, #4f46e5);
    transition: width 0.4s ease;
}

.pa-trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    width: fit-content;
    padding: 0.12rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
}
.pa-trend-badge.is-naik { background: #dcfce7; color: #15803d; }
.pa-trend-badge.is-turun { background: #fee2e2; color: #b91c1c; }
.pa-trend-badge.is-tetap { background: #f1f5f9; color: #475569; }

.pa-text-subtle {
    font-size: 0.74rem;
    color: #64748b;
    line-height: 1.35;
}

.pa-lr-kpi-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-teal-700, #0f766e);
    height: 1.75rem;
    transition: color 0.15s ease;
}

.pa-lr-kpi-card:hover .pa-lr-kpi-card__foot {
    color: var(--hcis-teal-600, #0d9488);
}

/* 2. Middle Cards Grid */
.pa-lr-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
}

.pa-lr-card--lebar {
    grid-column: 1 / -1;
}

.pa-lr-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

/* Sebaran Grade List */
.pa-lr-grade {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-lr-grade li {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) 78px;
    align-items: center;
    gap: 0.75rem;
}

.pa-lr-grade__kode {
    display: grid;
    place-items: center;
    height: 26px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 800;
}

.pa-lr-grade__bar {
    height: 10px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.pa-lr-grade__bar i {
    display: block;
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

.pa-lr-grade__jumlah {
    font-size: 0.8rem;
    font-weight: 750;
    text-align: right;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-lr-grade__jumlah small {
    font-weight: 500;
    color: #94a3b8;
}

.is-g-a { background: #dcfce7; color: #15803d; } .pa-lr-grade__bar i.is-g-a { background: #22c55e; }
.is-g-b { background: #e0e7ff; color: #4338ca; } .pa-lr-grade__bar i.is-g-b { background: #6366f1; }
.is-g-c { background: #fef3c7; color: #b45309; } .pa-lr-grade__bar i.is-g-c { background: #f59e0b; }
.is-g-d { background: #fee2e2; color: #b91c1c; } .pa-lr-grade__bar i.is-g-d { background: #ef4444; }
.is-g-t, .is-g-x { background: #f1f5f9; color: #475569; } .pa-lr-grade__bar i.is-g-t { background: #94a3b8; }

/* Grade Extrema Cards */
.pa-lr-ekstrem {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.pa-lr-ekstrem__item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 12px);
    background: rgba(248, 250, 252, 0.85);
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;
}

.pa-lr-ekstrem__item:hover {
    transform: translateY(-1px);
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.pa-lr-ekstrem__icon {
    display: grid;
    place-items: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 9px;
    flex-shrink: 0;
}

.pa-lr-ekstrem__item.is-atas .pa-lr-ekstrem__icon {
    background: rgba(34, 197, 94, 0.12);
    color: #16a34a;
}

.pa-lr-ekstrem__item.is-bawah .pa-lr-ekstrem__icon {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-lr-ekstrem__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.pa-lr-ekstrem__text small {
    font-size: 0.66rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.pa-lr-ekstrem__text b {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.82rem;
    color: #0f172a;
}

.pa-lr-ekstrem__item strong {
    font-size: 0.95rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
}

.pa-lr-catatan-wrap {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #e2e8f0;
}

.pa-lr-catatan {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.74rem;
    color: #64748b;
}

/* 9-Box HAV Grid */
.pa-lr-hav-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
}

.pa-lr-hav__kotak {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.35rem;
    min-height: 4.75rem;
    padding: 0.6rem 0.65rem;
    border-radius: var(--hcis-radius-md, 12px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: var(--bg, #f8fafc);
    color: var(--fg, #334155);
    transition: all 0.18s ease;
}

.pa-lr-hav__kotak:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.pa-lr-hav__kotak strong {
    font-size: 1.35rem;
    line-height: 1;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
}

.pa-lr-hav__nama {
    font-size: 0.68rem;
    font-weight: 750;
    line-height: 1.25;
}

.pa-lr-hav__kotak.is-kosong {
    opacity: 0.45;
}

.pa-lr-hav__kotak.is-success { --bg: #dcfce7; --fg: #166534; }
.pa-lr-hav__kotak.is-purple { --bg: #f3e8ff; --fg: #6b21a8; }
.pa-lr-hav__kotak.is-brand { --bg: #e0e7ff; --fg: #3730a3; }
.pa-lr-hav__kotak.is-teal { --bg: #ccfbf1; --fg: #115e59; }
.pa-lr-hav__kotak.is-warning { --bg: #fef3c7; --fg: #92400e; }
.pa-lr-hav__kotak.is-info { --bg: #e0f2fe; --fg: #075985; }
.pa-lr-hav__kotak.is-danger { --bg: #fee2e2; --fg: #991b1b; }

/* 3. Bottom Section: Tren */
.pa-lr-tren__ringkas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.pa-lr-tren__ringkas span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 750;
}

.pa-lr-tren__ringkas .is-naik { background: #dcfce7; color: #15803d; }
.pa-lr-tren__ringkas .is-turun { background: #fee2e2; color: #b91c1c; }
.pa-lr-tren__ringkas .is-tetap { background: #f1f5f9; color: #475569; }

.pa-lr-tren-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem 1.25rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-lr-tren__baris {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) auto auto;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.55rem 0.65rem;
    border: 1px solid transparent;
    border-bottom: 1px dashed #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: all 0.16s ease;
}

.pa-lr-tren__baris:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.pa-lr-tren__identity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.pa-lr-tren__avatar {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.65rem;
    font-weight: 800;
    flex-shrink: 0;
}

.pa-lr-tren__baris b {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.82rem;
    color: #0f172a;
}

.pa-lr-tren__nilai {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: #334155;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.pa-lr-tren__nilai small {
    color: #94a3b8;
    font-weight: 750;
}

/* ==========================================================================
   RESPONSIVE OPTIMIZATION (Ultra-Compact & Comfortable Mobile Layout)
   ========================================================================== */

@media (max-width: 1200px) {
    .pa-lr-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .pa-lr-grid {
        grid-template-columns: 1fr;
    }
    .pa-lr-tren-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .pa-lr {
        gap: 0.85rem;
    }

    /* 2x2 Bento KPI Grid on Mobile (Saves ~60% vertical screen space) */
    .pa-lr-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .pa-lr-kpi-card {
        min-height: auto;
        padding: 0.75rem 0.85rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-lr-kpi-card:active {
        transform: scale(0.98);
    }

    .pa-lr-kpi-card__head {
        height: 1.45rem;
        margin-bottom: 0.15rem;
    }

    .pa-lr-kpi-card__badge-wrap {
        gap: 0.35rem;
        min-width: 0;
    }

    .pa-lr-kpi-card__icon {
        width: 1.4rem;
        height: 1.4rem;
        border-radius: 6px;
    }

    .pa-lr-kpi-card__kicker {
        font-size: 0.58rem;
        font-weight: 800;
        letter-spacing: 0.03em;
    }

    .pa-lr-kpi-card__arrow {
        width: 1.25rem;
        height: 1.25rem;
    }

    .pa-lr-kpi-card__body {
        min-height: auto;
        padding: 0.15rem 0;
        gap: 0.25rem;
    }

    .pa-lr-kpi-val {
        font-size: 1.35rem;
        font-weight: 850;
        line-height: 1.15;
    }

    .pa-kpi-sub-total {
        font-size: 0.8rem;
    }

    .pa-lr-bar {
        height: 5px;
        margin: 0.15rem 0;
    }

    .pa-trend-badge {
        font-size: 0.65rem;
        padding: 0.1rem 0.45rem;
    }

    .pa-text-subtle {
        font-size: 0.66rem;
        line-height: 1.3;
        color: #64748b;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Hide foot prompt on mobile for extreme compactness (card is clickable) */
    .pa-lr-kpi-card__foot {
        display: none;
    }

    /* Snug Card Headers & Bodies */
    .pa-lr-card :deep(.hcis-card__header) {
        padding: 0.75rem 0.85rem 0.45rem 0.85rem;
    }

    .pa-lr-card :deep(.hcis-card__body) {
        padding: 0.45rem 0.85rem 0.85rem 0.85rem;
    }

    .pa-lr-card :deep(.hcis-card__title) {
        font-size: 0.9rem;
    }

    .pa-lr-card :deep(.hcis-card__description) {
        font-size: 0.7rem;
        line-height: 1.35;
        margin-top: 0.1rem;
    }

    .pa-lr-card :deep(.hcis-card__icon-box) {
        width: 1.75rem;
        height: 1.75rem;
        font-size: 0.8rem;
        border-radius: 8px;
    }

    .pa-lr-grid {
        gap: 0.85rem;
    }

    /* Grade distribution on Mobile */
    .pa-lr-grade {
        gap: 0.4rem;
    }

    .pa-lr-grade li {
        grid-template-columns: 36px minmax(0, 1fr) 62px;
        gap: 0.55rem;
    }

    .pa-lr-grade__kode {
        height: 24px;
        font-size: 0.7rem;
        font-weight: 800;
        border-radius: 6px;
    }

    .pa-lr-grade__bar {
        height: 8px;
        border-radius: 999px;
    }

    .pa-lr-grade__jumlah {
        font-size: 0.76rem;
        font-weight: 750;
    }

    /* Extrema cards: stacked cleanly on mobile with no name clipping */
    .pa-lr-ekstrem {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        margin-top: 0.6rem;
    }

    .pa-lr-ekstrem__item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.6rem 0.75rem;
        border-radius: 10px;
    }

    .pa-lr-ekstrem__item:active {
        transform: scale(0.985);
    }

    .pa-lr-ekstrem__icon {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .pa-lr-ekstrem__text {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
    }

    .pa-lr-ekstrem__text small {
        font-size: 0.6rem;
        font-weight: 750;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #64748b;
    }

    .pa-lr-ekstrem__text b {
        font-size: 0.78rem;
        font-weight: 750;
        color: #0f172a;
        white-space: normal;
        word-break: break-word;
    }

    .pa-lr-ekstrem__item strong {
        font-size: 0.95rem;
        font-weight: 850;
        margin-left: auto;
        flex-shrink: 0;
    }

    /* 9-Box HAV Matrix on Mobile */
    .pa-lr-hav-grid {
        gap: 0.35rem;
    }

    .pa-lr-hav__kotak {
        min-height: 3.85rem;
        padding: 0.45rem 0.45rem;
        border-radius: 8px;
    }

    .pa-lr-hav__kotak:active {
        transform: scale(0.97);
    }

    .pa-lr-hav__nama {
        font-size: 0.6rem;
        font-weight: 750;
        line-height: 1.2;
        word-break: break-word;
    }

    .pa-lr-hav__kotak strong {
        font-size: 1.25rem;
        font-weight: 850;
        line-height: 1;
    }

    /* Tren on Mobile */
    .pa-lr-tren__ringkas {
        gap: 0.35rem;
    }

    .pa-lr-tren__ringkas span {
        font-size: 0.68rem;
        padding: 0.12rem 0.45rem;
        font-weight: 750;
        border-radius: 999px;
    }

    .pa-lr-tren-grid {
        gap: 0.35rem;
    }

    .pa-lr-tren__baris {
        grid-template-columns: 1fr auto;
        grid-template-areas:
            "identity delta"
            "nilai delta";
        row-gap: 0.2rem;
        padding: 0.5rem 0.55rem;
        border-radius: 8px;
    }

    .pa-lr-tren__baris:active {
        transform: scale(0.99);
        background: #f8fafc;
    }

    .pa-lr-tren__identity {
        grid-area: identity;
        gap: 0.45rem;
    }

    .pa-lr-tren__avatar {
        width: 1.6rem;
        height: 1.6rem;
        font-size: 0.62rem;
        font-weight: 800;
        border-radius: 6px;
    }

    .pa-lr-tren__baris b {
        font-size: 0.78rem;
        font-weight: 750;
        color: #0f172a;
    }

    .pa-lr-tren__nilai {
        grid-area: nilai;
        font-size: 0.72rem;
        padding-left: 2.05rem;
    }

    .pa-lr-tren__baris .pa-trend-badge {
        grid-area: delta;
        align-self: center;
        font-size: 0.7rem;
        padding: 0.12rem 0.45rem;
    }
}

@media (max-width: 440px) {
    .pa-lr-kpi-grid {
        gap: 0.45rem;
    }

    .pa-lr-kpi-card {
        padding: 0.7rem 0.75rem;
    }

    .pa-lr-kpi-val {
        font-size: 1.3rem;
    }

    .pa-lr-hav__kotak {
        min-height: 3.5rem;
        padding: 0.35rem 0.4rem;
    }

    .pa-lr-hav__nama {
        font-size: 0.56rem;
    }

    .pa-lr-hav__kotak strong {
        font-size: 1.15rem;
    }
}

/* Keadaan kosong (dipakai Sebaran Grade & Tren) */
/* Empty State Styling */
.pa-lr-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 2rem 1rem;
    text-align: center;
    color: #64748b;
}
.pa-lr-kosong__icon {
    display: grid;
    place-items: center;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    margin-bottom: 0.25rem;
}
.pa-lr-kosong__icon.is-success {
    background: rgba(34, 197, 94, 0.12);
    color: #16a34a;
}
.pa-lr-kosong__icon.is-neutral {
    background: rgba(226, 232, 240, 0.6);
    color: #64748b;
}
.pa-lr-kosong strong {
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 750;
}
.pa-lr-kosong p {
    margin: 0;
    max-width: 36ch;
    font-size: 0.8rem;
    line-height: 1.5;
    color: #64748b;
}
</style>
