<template>
    <HcisDialog
        :model-value="modelValue"
        :title="dialogTitle"
        kicker="BALANCING · KUALITATIF"
        description="Tinjau masukan multi-penilai 360° dan rumuskan catatan kualitatif hasil kesepakatan komite."
        size="2xl"
        sentiment="brand"
        icon="chat-square-quote"
        header-divider
        sticky-footer
        @update:model-value="(v) => $emit('update:modelValue', v)"
    >
        <div v-if="peserta" class="pa-bal-kq">
            <!-- ═══════════════════════════════════════════════════════════════
                 1. PARTICIPANT CONTEXT & SCORE OVERVIEW BANNER
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pa-bal-kq-identity-card">
                <div class="pa-bal-kq-identity-main">
                    <HcisAvatar :name="peserta.nama" size="lg" />
                    <div class="pa-bal-kq-identity-details">
                        <div class="pa-bal-kq-identity-name-row">
                            <h3 class="pa-bal-kq-identity-name">{{ peserta.nama }}</h3>
                            <HcisBadge
                                v-if="statusTinjauBadge"
                                :label="statusTinjauBadge.label"
                                :variant="statusTinjauBadge.variant"
                                appearance="subtle"
                                size="xs"
                                :dot="statusTinjauBadge.dot"
                            />
                        </div>
                        <div class="pa-bal-kq-identity-meta">
                            <span v-if="peserta.kode_karyawan">NIK: <strong>{{ peserta.kode_karyawan }}</strong></span>
                            <span v-if="peserta.kode_karyawan && (peserta.level || peserta.divisi)" class="meta-dot">&bull;</span>
                            <span v-if="peserta.level">{{ peserta.level }}</span>
                            <span v-if="peserta.level && peserta.divisi" class="meta-dot">&bull;</span>
                            <span v-if="peserta.divisi">{{ peserta.divisi }}</span>
                            <span v-if="peserta.sub_divisi" class="text-muted">({{ peserta.sub_divisi }})</span>
                        </div>
                    </div>
                </div>

                <!-- Final Score & Grade Capsule -->
                <div class="pa-bal-kq-score-capsule">
                    <div class="score-capsule-item">
                        <span class="capsule-label">Skor Akhir</span>
                        <div class="capsule-values">
                            <span v-if="adaPerubahanSkor" class="capsule-val-orig">{{ formatAngka(peserta.sebelum?.final) }}</span>
                            <span class="capsule-val-curr">{{ formatAngka(skorAktif) }}</span>
                        </div>
                    </div>
                    <div class="score-capsule-sep" />
                    <div class="score-capsule-item">
                        <span class="capsule-label">Grade</span>
                        <div class="capsule-grade">
                            <HcisBadge
                                :label="gradeAktif || '—'"
                                variant="brand"
                                appearance="solid"
                                size="sm"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 2. SEGMENTED ASPECT NAVIGATION TABS (Strength, Development, etc.)
                 ═══════════════════════════════════════════════════════════════ -->
            <div v-if="(peserta.kualitatif || []).length > 1" class="pa-bal-kq-aspect-nav" role="tablist" aria-label="Pilih aspek kualitatif">
                <div class="pa-bal-kq-aspect-segmented">
                    <button
                        v-for="q in peserta.kualitatif"
                        :key="q.competency_id"
                        type="button"
                        role="tab"
                        :aria-selected="q.competency_id === aktifId"
                        class="pa-bal-kq-aspect-btn"
                        :class="{
                            'is-active': q.competency_id === aktifId,
                            'is-modified': aspekDiubah(q),
                        }"
                        @click="pindah(q.competency_id)"
                    >
                        <HcisIcon :name="q.nama?.toLowerCase().includes('strength') ? 'star-fill' : 'lightning-charge-fill'" size="2xs" />
                        <span class="aspect-label">{{ q.nama }}</span>
                        <span v-if="aspekDiubah(q)" class="aspect-dot" title="Catatan kualitatif telah disesuaikan" />
                    </button>
                </div>

                <div class="pa-bal-kq-aspect-tip">
                    <HcisIcon name="info-circle" size="2xs" />
                    <span>Pilih aspek di atas untuk beralih pertanyaan penilai &amp; rangkuman</span>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 3. ACTION TOAST BANNER (When quoting or using admin base)
                 ═══════════════════════════════════════════════════════════════ -->
            <Transition name="pa-bal-toast-fade">
                <div v-if="notifikasiAksi" class="pa-bal-kq-action-toast" role="status">
                    <HcisIcon name="check-circle-fill" size="xs" class="text-success" />
                    <span>{{ notifikasiAksi }}</span>
                </div>
            </Transition>

            <!-- ═══════════════════════════════════════════════════════════════
                 4. TWO-COLUMN SPLIT COCKPIT
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pa-bal-kq-grid">
                <!-- ── KIRI: Masukan Penilai 360° ───────────────────────── -->
                <section class="pa-bal-kq-panel is-kiri" aria-labelledby="kq-panel-kiri-judul">
                    <header class="pa-bal-kq-panel-header">
                        <div class="panel-header-title-wrap">
                            <div class="panel-icon-jewel">
                                <HcisIcon name="chat-square-quote" size="xs" />
                            </div>
                            <div>
                                <h4 id="kq-panel-kiri-judul" class="panel-title">Masukan Penilai 360°</h4>
                                <span class="panel-subtitle">Hasil survei kualitatif sebelum balancing</span>
                            </div>
                        </div>

                        <HcisBadge
                            v-if="dataAktif"
                            :label="`${dataAktif.penjawab} / ${dataAktif.diminta} Penilai Menjawab`"
                            variant="neutral"
                            appearance="subtle"
                            size="xs"
                        />
                    </header>

                    <!-- Filter Kategori Penilai -->
                    <div v-if="kategoriAda.length > 1" class="pa-bal-kq-cat-pills" role="group" aria-label="Saring kategori penilai">
                        <button
                            type="button"
                            class="cat-pill-btn"
                            :class="{ 'is-active': !kategori }"
                            @click="kategori = ''"
                        >
                            <span>Semua</span>
                            <span class="cat-pill-count">{{ hitungTotalJawaban }}</span>
                        </button>
                        <button
                            v-for="c in kategoriAda"
                            :key="c"
                            type="button"
                            class="cat-pill-btn"
                            :class="{ 'is-active': kategori === c }"
                            @click="kategori = c"
                        >
                            <span>{{ c }}</span>
                            <span class="cat-pill-count">{{ hitungJawabanKategori(c) }}</span>
                        </button>
                    </div>

                    <!-- Area Konten Masukan Penilai (Scrollable) -->
                    <div class="pa-bal-kq-soal-scroll">
                        <HcisSkeleton v-if="memuat" :rows="6" />

                        <div v-else-if="galat" class="pa-bal-kq-status-box is-error">
                            <HcisIcon name="exclamation-circle-fill" size="sm" />
                            <p>{{ galat }}</p>
                        </div>

                        <div v-else-if="!dataAktif || !dataAktif.soal.length" class="pa-bal-kq-status-box is-empty">
                            <HcisIcon name="inbox" size="lg" />
                            <p>{{ dataAktif ? 'Belum ada penilai yang menjawab soal pada aspek ini.' : 'Soal kualitatif ini tidak diajukan kepada penilai peserta ini.' }}</p>
                        </div>

                        <div v-else class="pa-bal-kq-soal-list">
                            <article
                                v-for="(s, i) in dataAktif.soal"
                                :key="s.pertanyaan"
                                class="pa-bal-kq-soal-card"
                            >
                                <div class="soal-card-prompt">
                                    <span class="soal-no-chip">{{ i + 1 }}</span>
                                    <p class="soal-text">{{ s.pertanyaan }}</p>
                                </div>

                                <div class="soal-answers-stack">
                                    <div
                                        v-for="(j, x) in saring(s.jawaban)"
                                        :key="x"
                                        class="answer-bubble"
                                    >
                                        <div class="answer-bubble-header">
                                            <div class="answer-author-wrap">
                                                <HcisBadge
                                                    :label="j.kategori || 'Penilai'"
                                                    :variant="kategoriBadgeVariant(j.kode_kategori || j.kategori)"
                                                    appearance="subtle"
                                                    size="2xs"
                                                />
                                                <span class="answer-author-name">{{ j.penilai || 'Anonim' }}</span>
                                            </div>

                                            <button
                                                v-if="bisaUbah"
                                                type="button"
                                                class="answer-quote-btn"
                                                title="Kutip masukan ini ke catatan After"
                                                @click="kutipJawaban(j)"
                                            >
                                                <HcisIcon name="quote" size="2xs" />
                                                <span>Kutip ke After</span>
                                            </button>
                                        </div>

                                        <p class="answer-text">{{ j.teks }}</p>
                                    </div>

                                    <div v-if="!saring(s.jawaban).length" class="answer-category-empty">
                                        <HcisIcon name="info-circle" size="2xs" />
                                        <span>Tidak ada jawaban dari kategori <strong>{{ kategori }}</strong> untuk pertanyaan ini.</span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- ── KANAN: Rangkuman Admin (Before) & Hasil Balancing (After) ── -->
                <section class="pa-bal-kq-panel is-kanan" aria-labelledby="kq-panel-kanan-judul">
                    <!-- Kartu 1: Rangkuman Admin (Before) -->
                    <div class="pa-bal-kq-subcard before-subcard">
                        <header class="subcard-header">
                            <div class="subcard-title-wrap">
                                <div class="subcard-icon-box">
                                    <HcisIcon name="journal-text" size="xs" />
                                </div>
                                <div>
                                    <h5 id="kq-panel-kanan-judul" class="subcard-title">
                                        Rangkuman Admin <span class="badge-tag">(Before)</span>
                                    </h5>
                                    <span class="subcard-subtitle">Rumusan awal sebelum proses balancing</span>
                                </div>
                            </div>

                            <button
                                v-if="bisaUbah && aktif?.sebelum"
                                type="button"
                                class="subcard-action-btn"
                                title="Salin isi Before ini ke editor After di bawah"
                                @click="gunakanSebelum"
                            >
                                <HcisIcon name="arrow-down-left" size="2xs" />
                                <span>Jadikan Dasar After</span>
                            </button>
                        </header>

                        <div class="subcard-body">
                            <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                            <div v-if="aktif?.sebelum" class="pa-bal-kq-html-surface" v-html="htmlAman(aktif.sebelum)" />
                            <div v-else class="pa-bal-kq-empty-surface">
                                <HcisIcon name="journal-x" size="sm" />
                                <span>Admin belum menuliskan rangkuman sebelum balancing untuk aspek ini.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kartu 2: Hasil Kesepakatan Balancing (After) -->
                    <div class="pa-bal-kq-subcard after-subcard">
                        <header class="subcard-header">
                            <div class="subcard-title-wrap">
                                <div class="subcard-icon-box is-brand">
                                    <HcisIcon name="pencil-square" size="xs" />
                                </div>
                                <div>
                                    <div class="title-with-badge">
                                        <h5 class="subcard-title">Hasil Kesepakatan Balancing <span class="badge-tag is-brand">(After)</span></h5>
                                        <HcisBadge
                                            v-if="aktif && beda(aktif, isian)"
                                            label="Diubah"
                                            variant="brand"
                                            appearance="glass"
                                            size="2xs"
                                            dot
                                        />
                                    </div>
                                    <span class="subcard-subtitle">Rumusan akhir yang disepakati oleh komite</span>
                                </div>
                            </div>

                            <button
                                v-if="bisaUbah && aktif && beda(aktif, isian)"
                                type="button"
                                class="subcard-action-btn is-danger"
                                title="Batalkan perubahan dan kembalikan ke catatan awal Before"
                                @click="kembalikan"
                            >
                                <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                <span>Kembalikan ke Before</span>
                            </button>
                        </header>

                        <div class="subcard-body">
                            <template v-if="bisaUbah && aktif">
                                <HcisRichText
                                    :id="`pa-bal-kq-${peserta.participant_id}-${aktifId}`"
                                    :key="`${peserta.participant_id}-${aktifId}-${versiEditor}`"
                                    v-model="isian"
                                    placeholder="Tuliskan rangkuman catatan kualitatif hasil kesepakatan forum balancing..."
                                    min-height="185px"
                                    max-height="320px"
                                />

                                <div class="editor-micro-hint">
                                    <HcisIcon name="lightbulb" size="2xs" />
                                    <span>Gunakan tombol <strong>Kutip ke After</strong> pada jawaban penilai di kiri atau <strong>Jadikan Dasar After</strong> di atas untuk menyusun catatan dengan cepat.</span>
                                </div>
                            </template>

                            <template v-else>
                                <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                                <div v-if="aktif?.sesudah" class="pa-bal-kq-html-surface" v-html="htmlAman(aktif.sesudah)" />
                                <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                                <div v-else-if="aktif?.sebelum" class="pa-bal-kq-html-surface" v-html="htmlAman(aktif.sebelum)" />
                                <div v-else class="pa-bal-kq-empty-surface">
                                    <HcisIcon name="file-earmark-x" size="sm" />
                                    <span>Tidak ada catatan kualitatif yang disimpan.</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             5. DIALOG FOOTER
             ═══════════════════════════════════════════════════════════════ -->
        <template #footer>
            <div class="pa-bal-kq-footer">
                <div class="footer-note-capsule">
                    <div class="footer-note-icon">
                        <HcisIcon name="info-circle-fill" size="2xs" />
                    </div>
                    <span class="footer-note-text">
                        Perubahan masuk ke <strong>draf sesi</strong>. Tekan <strong>Simpan draft</strong> di board untuk menyimpan ke server.
                    </span>
                </div>
                <div class="footer-buttons">
                    <HcisButton
                        :label="bisaUbah ? 'Batal' : 'Tutup'"
                        variant="glass"
                        size="sm"
                        @click="$emit('update:modelValue', false)"
                    />
                    <HcisButton
                        v-if="bisaUbah"
                        label="Terapkan Hasil Kualitatif"
                        variant="primary"
                        size="sm"
                        leading-icon="check-lg"
                        @click="terapkan"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisIcon,
    HcisRichText,
    HcisSkeleton,
} from '@/components/ui';
import paApi from '../../api/paApi.js';
import { htmlAman } from '../../utils/htmlAman.js';

/* Modal kualitatif: soal + jawaban penilai di kiri, rangkuman admin (Before) dan editor
   After di kanan. Isian disimpan sebagai draf per kualitatif selama modal terbuka, lalu
   "Terapkan" menulisnya ke objek peserta yang sama dengan board (undo/simpan ikut jalan). */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    peserta: { type: Object, default: null },
    competencyId: { type: Number, default: null },
    bisaUbah: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'ubah']);

// Jawaban penilai tidak berubah selama sesi, jadi cukup diambil sekali per orang.
const cache = new Map();
const data = ref([]);
const memuat = ref(false);
const galat = ref('');
const aktifId = ref(null);
const kategori = ref('');
const isian = ref('');
const versiEditor = ref(0);
const draf = reactive({});
const notifikasiAksi = ref('');
let timerNotif = null;

const aktif = computed(() => (props.peserta?.kualitatif || []).find((q) => q.competency_id === aktifId.value) || null);
const dataAktif = computed(() => data.value.find((d) => d.nama === aktif.value?.nama) || null);
const kategoriAda = computed(() => [...new Set((dataAktif.value?.soal || []).flatMap((s) => s.jawaban.map((j) => j.kategori || 'Penilai')))]);

const dialogTitle = computed(() => {
    if (!props.peserta) return 'Kualitatif Balancing';
    return `${aktif.value?.nama || 'Uraian Kualitatif'} · ${props.peserta.nama}`;
});

const skorAktif = computed(() => {
    if (props.peserta?.sesudah?.final !== undefined && props.peserta?.sesudah?.final !== null) {
        return props.peserta.sesudah.final;
    }
    return props.peserta?.sebelum?.final;
});

const gradeAktif = computed(() => {
    return props.peserta?.sesudah?.grade || props.peserta?.sebelum?.grade || '—';
});

const adaPerubahanSkor = computed(() => {
    if (props.peserta?.sesudah?.final === undefined || props.peserta?.sebelum?.final === undefined) return false;
    return Math.abs(Number(props.peserta.sesudah.final) - Number(props.peserta.sebelum.final)) >= 0.005;
});

const statusTinjauBadge = computed(() => {
    const st = props.peserta?.status_tinjau;
    if (st === 'DIUBAH') return { label: 'Disesuaikan', variant: 'brand', dot: true };
    if (st === 'TANPA_PERUBAHAN') return { label: 'Tanpa Perubahan', variant: 'neutral', dot: false };
    if (st === 'BELUM') return { label: 'Belum Ditinjau', variant: 'warning', dot: false };
    return null;
});

const hitungTotalJawaban = computed(() => {
    if (!dataAktif.value?.soal) return 0;
    return dataAktif.value.soal.reduce((acc, s) => acc + (s.jawaban?.length || 0), 0);
});

function hitungJawabanKategori(c) {
    if (!dataAktif.value?.soal) return 0;
    return dataAktif.value.soal.reduce((acc, s) => {
        return acc + (s.jawaban?.filter((j) => (j.kategori || 'Penilai') === c).length || 0);
    }, 0);
}

function formatAngka(v) {
    if (v === null || v === undefined || isNaN(Number(v))) return '—';
    return Number(v).toFixed(2);
}

function kategoriBadgeVariant(kategori) {
    const k = String(kategori || '').toLowerCase();
    if (k.includes('atasan') || k === 'atasan') return 'brand';
    if (k.includes('peer') || k.includes('rekan')) return 'info';
    if (k.includes('bawahan') || k.includes('subordinat')) return 'accent';
    if (k.includes('self') || k.includes('diri')) return 'warning';
    return 'neutral';
}

function saring(daftar) {
    return kategori.value ? (daftar || []).filter((j) => (j.kategori || 'Penilai') === kategori.value) : (daftar || []);
}

function polos(html) {
    return String(html ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
}

function teksBerubah(q) {
    return (q.sesudah ?? '') !== (q.sebelum ?? '');
}

// Beda = isinya (bukan markup editor) tidak sama dengan Before.
function beda(q, nilai) {
    return polos(nilai) !== polos(q.sebelum);
}

function aspekDiubah(q) {
    if (!q) return false;
    if (draf[q.competency_id] !== undefined) {
        return beda(q, draf[q.competency_id]);
    }
    return teksBerubah(q);
}

function simpanDrafAktif() {
    if (aktifId.value !== null) draf[aktifId.value] = isian.value;
}

function pindah(id) {
    simpanDrafAktif();
    aktifId.value = id;
    kategori.value = '';
    const q = aktif.value;
    isian.value = draf[id] !== undefined ? draf[id] : (q?.sesudah ?? q?.sebelum ?? '');
    versiEditor.value += 1;
}

function kembalikan() {
    isian.value = aktif.value?.sebelum ?? '';
    versiEditor.value += 1;
    tampilkanNotifikasi('Catatan dikembalikan ke draf awal Sebelum Balancing.');
}

function gunakanSebelum() {
    if (!aktif.value?.sebelum) return;
    isian.value = aktif.value.sebelum;
    versiEditor.value += 1;
    tampilkanNotifikasi('Catatan admin (Before) berhasil disalin ke editor After!');
}

function kutipJawaban(j) {
    const pengutip = j.kategori ? `${j.kategori} (${j.penilai || 'Anonim'})` : (j.penilai || 'Penilai');
    const kutipan = `<p><strong>${pengutip}:</strong> &ldquo;${j.teks}&rdquo;</p>`;

    if (!isian.value || polos(isian.value) === '') {
        isian.value = kutipan;
    } else {
        isian.value = `${isian.value}${kutipan}`;
    }
    versiEditor.value += 1;
    tampilkanNotifikasi(`Kutipan dari ${pengutip} disematkan ke After!`);
}

function tampilkanNotifikasi(pesan) {
    notifikasiAksi.value = pesan;
    clearTimeout(timerNotif);
    timerNotif = setTimeout(() => {
        notifikasiAksi.value = '';
    }, 3200);
}

function terapkan() {
    simpanDrafAktif();
    let ada = false;
    for (const q of props.peserta?.kualitatif || []) {
        if (draf[q.competency_id] === undefined) continue;
        const baru = beda(q, draf[q.competency_id]) ? draf[q.competency_id] : q.sebelum;
        if ((baru ?? '') !== (q.sesudah ?? '')) {
            q.sesudah = baru;
            ada = true;
        }
    }
    if (ada) emit('ubah');
    emit('update:modelValue', false);
}

async function muat(id) {
    if (cache.has(id)) {
        data.value = cache.get(id);
        return;
    }
    memuat.value = true;
    galat.value = '';
    try {
        const hasil = await paApi.kualitatifBalancing(id);
        cache.set(id, hasil || []);
        data.value = hasil || [];
    } catch (e) {
        galat.value = e?.message || 'Soal dan jawaban penilai gagal dimuat.';
        data.value = [];
    } finally {
        memuat.value = false;
    }
}

watch(() => [props.modelValue, props.peserta?.participant_id, props.competencyId], ([buka]) => {
    if (!buka || !props.peserta) return;
    for (const k of Object.keys(draf)) delete draf[k];
    aktifId.value = null;
    pindah(props.competencyId ?? props.peserta.kualitatif?.[0]?.competency_id ?? null);
    muat(props.peserta.participant_id);
}, { immediate: true });
</script>

<style scoped>
.pa-bal-kq {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ══════════════════════════════════════════════════════════════════════
   1. PARTICIPANT IDENTITY CARD
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-identity-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.875rem 1.125rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-bal-kq-identity-main {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
}

.pa-bal-kq-identity-details {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-bal-kq-identity-name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-bal-kq-identity-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-bal-kq-identity-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: #64748b;
}

.meta-dot {
    color: #cbd5e1;
    font-size: 0.85rem;
}

.pa-bal-kq-score-capsule {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.45rem 0.875rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.score-capsule-item {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.1rem;
}

.capsule-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.capsule-values {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.capsule-val-orig {
    font-size: 0.75rem;
    font-weight: 500;
    color: #94a3b8;
    text-decoration: line-through;
}

.capsule-val-curr {
    font-size: 0.925rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.score-capsule-sep {
    width: 1px;
    height: 24px;
    background: #e2e8f0;
}

/* ══════════════════════════════════════════════════════════════════════
   2. ASPECT NAVIGATION TABS
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-aspect-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-bal-kq-aspect-segmented {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 11px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.pa-bal-kq-aspect-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-bal-kq-aspect-btn:hover {
    color: #1e293b;
    background: rgba(255, 255, 255, 0.6);
}

.pa-bal-kq-aspect-btn.is-active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.aspect-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #6366f1;
}

.pa-bal-kq-aspect-tip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #64748b;
}

/* ══════════════════════════════════════════════════════════════════════
   3. ACTION TOAST BANNER
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-action-toast {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border-radius: 8px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    font-size: 0.75rem;
    font-weight: 600;
}

.pa-bal-toast-fade-enter-active,
.pa-bal-toast-fade-leave-active {
    transition: all 0.25s ease;
}

.pa-bal-toast-fade-enter-from,
.pa-bal-toast-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* ══════════════════════════════════════════════════════════════════════
   4. TWO-COLUMN SPLIT GRID
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 1.125rem;
    align-items: start;
}

.pa-bal-kq-panel {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    min-width: 0;
    padding: 1rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-bal-kq-panel.is-kanan {
    background: #fafafa;
    border-color: #e2e8f0;
}

/* Panel Header */
.pa-bal-kq-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.panel-header-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.panel-icon-jewel {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4338ca;
}

.panel-title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.panel-subtitle {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
}

/* Category Filter Pills */
.pa-bal-kq-cat-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.cat-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.225rem 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #ffffff;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.cat-pill-btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.cat-pill-btn.is-active {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
}

.cat-pill-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 0.25rem;
    border-radius: 999px;
    background: rgba(100, 116, 139, 0.12);
    font-size: 0.65rem;
    font-weight: 700;
}

.cat-pill-btn.is-active .cat-pill-count {
    background: rgba(99, 102, 241, 0.18);
    color: #4338ca;
}

/* Scroll Area for Questions */
.pa-bal-kq-soal-scroll {
    max-height: 58vh;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.pa-bal-kq-soal-scroll::-webkit-scrollbar {
    width: 6px;
}

.pa-bal-kq-soal-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.pa-bal-kq-soal-list {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* Question Card — STRICTLY NO border-left per User Rule */
.pa-bal-kq-soal-card {
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.soal-card-prompt {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
}

.soal-no-chip {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #4338ca;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 800;
}

.soal-text {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.45;
}

.soal-answers-stack {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-left: 0.5rem;
}

/* Answer Bubble Card */
.answer-bubble {
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    padding: 0.6rem 0.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.answer-bubble-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.answer-author-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.answer-author-name {
    font-size: 0.735rem;
    font-weight: 600;
    color: #475569;
}

.answer-quote-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #ffffff;
    color: #4338ca;
    font-size: 0.6875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.answer-quote-btn:hover {
    border-color: #6366f1;
    background: #eef2ff;
}

.answer-text {
    margin: 0;
    font-size: 0.8rem;
    color: #334155;
    line-height: 1.5;
}

.answer-category-empty {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    font-size: 0.75rem;
    color: #64748b;
    font-style: italic;
}

/* Status Box */
.pa-bal-kq-status-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2.5rem 1rem;
    text-align: center;
    color: #64748b;
}

.pa-bal-kq-status-box p {
    margin: 0;
    font-size: 0.8125rem;
}

.pa-bal-kq-status-box.is-error {
    color: #dc2626;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
}

/* ══════════════════════════════════════════════════════════════════════
   5. RIGHT PANEL SUBCARDS (Before & After)
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-subcard {
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.subcard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 0.875rem;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}

.subcard-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.subcard-icon-box {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
}

.subcard-icon-box.is-brand {
    background: #eef2ff;
    color: #4338ca;
}

.title-with-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.subcard-title {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
}

.badge-tag {
    font-weight: 500;
    color: #64748b;
    font-size: 0.75rem;
}

.badge-tag.is-brand {
    color: #4338ca;
}

.subcard-subtitle {
    display: block;
    font-size: 0.6875rem;
    color: #64748b;
}

.subcard-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    color: #334155;
    font-size: 0.7125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.subcard-action-btn:hover {
    border-color: #6366f1;
    color: #4338ca;
    background: #eef2ff;
}

.subcard-action-btn.is-danger:hover {
    border-color: #fca5a5;
    color: #dc2626;
    background: #fef2f2;
}

.subcard-body {
    padding: 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-bal-kq-html-surface {
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    font-size: 0.8125rem;
    color: #1e293b;
    line-height: 1.55;
}

.pa-bal-kq-html-surface :deep(p) {
    margin: 0 0 0.35rem;
}

.pa-bal-kq-html-surface :deep(p:last-child) {
    margin-bottom: 0;
}

.pa-bal-kq-html-surface :deep(ul),
.pa-bal-kq-html-surface :deep(ol) {
    margin: 0;
    padding-left: 1.2rem;
}

.pa-bal-kq-empty-surface {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.85rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px dashed #e2e8f0;
    color: #94a3b8;
    font-size: 0.75rem;
    font-style: italic;
}

.editor-micro-hint {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: #f8fafc;
    font-size: 0.7rem;
    color: #64748b;
    line-height: 1.4;
}

.editor-micro-hint strong {
    color: #334155;
}

/* ══════════════════════════════════════════════════════════════════════
   6. DIALOG FOOTER
   ══════════════════════════════════════════════════════════════════════ */
.pa-bal-kq-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.footer-note-capsule {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    max-width: 520px;
}

.footer-note-icon {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 5px;
    background-color: #eef2ff;
    color: #4338ca;
    flex-shrink: 0;
}

.footer-note-text {
    font-size: 0.735rem;
    color: #475569;
    line-height: 1.35;
}

.footer-note-text strong {
    color: #0f172a;
    font-weight: 700;
}

.footer-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

/* Responsive Breakpoints */
@media (max-width: 960px) {
    .pa-bal-kq-grid {
        grid-template-columns: 1fr;
    }

    .pa-bal-kq-soal-scroll {
        max-height: none;
    }

    .pa-bal-kq-identity-card {
        flex-direction: column;
        align-items: flex-start;
    }

    .pa-bal-kq-score-capsule {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
