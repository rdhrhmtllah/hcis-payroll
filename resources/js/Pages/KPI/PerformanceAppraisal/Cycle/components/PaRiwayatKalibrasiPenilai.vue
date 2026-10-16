<template>
    <section v-if="riwayat.length" class="pa-riwayat-kp" aria-labelledby="pa-riwayat-kp-judul">
        <!-- Header & Info Banner -->
        <div class="pa-riwayat-kp__banner">
            <div class="pa-riwayat-kp__banner-left">
                <div class="pa-riwayat-kp__banner-icon">
                    <HcisIcon name="pencil-square" size="sm" />
                </div>
                <div class="pa-riwayat-kp__banner-text">
                    <h4 id="pa-riwayat-kp-judul">Kalibrasi Penilai</h4>
                    <p class="pa-riwayat-kp__uraian">
                        Skor dan uraian yang direvisi penilai sendiri sebelum mengirim. Berbeda dari Kalibrasi Forum atas grade
                        akhir.
                    </p>
                </div>
            </div>
            <div class="pa-riwayat-kp__banner-badge">
                <HcisBadge
                    :label="`${riwayat.length} Catatan Revisi`"
                    variant="purple"
                    appearance="subtle"
                    size="sm"
                    dot
                />
            </div>
        </div>

        <!-- Filter Pills by Rater (if multiple raters) -->
        <div v-if="daftarPenilaiFilter.length > 2" class="pa-riwayat-kp__filters">
            <span class="pa-riwayat-kp__filter-lbl">
                <HcisIcon name="funnel" size="2xs" />
                Filter Penilai:
            </span>
            <div class="pa-riwayat-kp__filter-pills">
                <button
                    v-for="p in daftarPenilaiFilter"
                    :key="p.key"
                    type="button"
                    class="pa-riwayat-kp__filter-pill"
                    :class="{ 'is-active': selectedRaterKey === p.key }"
                    @click="selectedRaterKey = p.key"
                >
                    <HcisIcon v-if="selectedRaterKey === p.key" name="check" size="2xs" />
                    <span>{{ p.label }}</span>
                    <span class="pa-riwayat-kp__filter-count">{{ p.count }}</span>
                </button>
            </div>
        </div>

        <!-- List of Calibration Cards -->
        <ol class="pa-riwayat-kp__daftar">
            <li v-for="r in filteredRiwayat" :key="r.kalibrasi_id" class="pa-riwayat-kp__item">
                <div class="pa-riwayat-kp__kepala">
                    <div class="pa-riwayat-kp__penilai-meta">
                        <HcisAvatar
                            :name="r.penilai.nama"
                            size="sm"
                            variant="gradient"
                            shape="circle"
                        />
                        <div class="pa-riwayat-kp__penilai-names">
                            <div class="pa-riwayat-kp__penilai-line">
                                <strong>{{ r.penilai.nama }}</strong>
                                <span v-if="r.penilai.kode_karyawan" class="pa-nik-badge">{{ r.penilai.kode_karyawan }}</span>
                            </div>
                            <span class="pa-riwayat-kp__cat-meta">
                                <HcisBadge :label="r.kategori.nama || r.kategori.kode" variant="brand" appearance="subtle" size="2xs" />
                                <span class="pa-sep">&bull;</span>
                                <span class="pa-riwayat-kp__time">
                                    <HcisIcon name="clock-history" size="2xs" />
                                    {{ formatWaktu(r.pada) }}
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="pa-riwayat-kp__action-badge">
                        <HcisBadge label="Revisi Mandiri" variant="purple" appearance="subtle" size="xs" />
                    </div>
                </div>

                <!-- Reason Callout Quote -->
                <div class="pa-riwayat-kp__alasan-container">
                    <div class="pa-riwayat-kp__alasan-icon">
                        <HcisIcon name="chat-quote-fill" size="xs" />
                    </div>
                    <div class="pa-riwayat-kp__alasan-body">
                        <span class="pa-riwayat-kp__alasan-title">Alasan Penyesuaian:</span>
                        <p class="pa-riwayat-kp__alasan">“{{ r.alasan }}”</p>
                    </div>
                </div>

                <!-- Competency Score Revisions -->
                <div v-if="r.perubahan?.length" class="pa-riwayat-kp__scores-section">
                    <div class="pa-riwayat-kp__section-title">
                        <HcisIcon name="award-fill" size="2xs" color="brand" />
                        <span>Perubahan Skor Kompetensi ({{ r.perubahan.length }})</span>
                    </div>
                    <ul class="pa-riwayat-kp__score-list">
                        <li v-for="p in r.perubahan" :key="p.competency_id" class="pa-riwayat-kp__score-row">
                            <div class="pa-riwayat-kp__score-label">
                                <HcisIcon name="dot" size="sm" color="brand" />
                                <strong>{{ p.nama }}</strong>
                                <span v-if="p.kode" class="pa-code-mini">{{ p.kode }}</span>
                            </div>
                            <div class="pa-riwayat-kp__score-values">
                                <span class="pa-val-before">{{ angka(p.skor_sebelum) }}</span>
                                <span class="pa-val-arrow">→</span>
                                <strong class="pa-val-after">{{ angka(p.skor_sesudah) }}</strong>
                                <small class="pa-val-max">/ {{ angka(p.skor_maksimal) }}</small>
                                <span
                                    class="pa-delta-tag"
                                    :class="hitungDelta(p.skor_sebelum, p.skor_sesudah).isUp ? 'is-up' : hitungDelta(p.skor_sebelum, p.skor_sesudah).isDown ? 'is-down' : 'is-same'"
                                >
                                    {{ hitungDelta(p.skor_sebelum, p.skor_sesudah).formatted }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Essay / Qualitative Question Revisions -->
                <div v-if="r.perubahan_jawaban?.length" class="pa-riwayat-kp__essay-section">
                    <div class="pa-riwayat-kp__section-title">
                        <HcisIcon name="chat-left-text-fill" size="2xs" color="purple" />
                        <span>Perubahan Jawaban Naratif & Esai ({{ r.perubahan_jawaban.length }})</span>
                    </div>
                    <dl class="pa-riwayat-kp__esai">
                        <div v-for="j in r.perubahan_jawaban" :key="j.kode_soal || j.id_master_soal" class="pa-riwayat-kp__essay-item">
                            <dt class="pa-riwayat-kp__essay-dt">
                                <HcisIcon name="patch-question-fill" size="2xs" color="purple" />
                                <span>{{ j.nama || 'Pertanyaan uraian' }}</span>
                                <span v-if="j.kode_soal" class="pa-code-mini">{{ j.kode_soal }}</span>
                            </dt>
                            <div class="pa-riwayat-kp__diff-grid">
                                <div class="pa-diff-card is-before">
                                    <div class="pa-diff-card__head">
                                        <span class="pa-diff-badge is-before">Sebelum Revisi</span>
                                    </div>
                                    <dd class="pa-riwayat-kp__sebelum" v-html="htmlAman(j.jawaban_sebelum)" />
                                </div>
                                <div class="pa-diff-card is-after">
                                    <div class="pa-diff-card__head">
                                        <span class="pa-diff-badge is-after">Sesudah Revisi</span>
                                    </div>
                                    <dd class="pa-riwayat-kp__sesudah" v-html="htmlAman(j.jawaban_sesudah)" />
                                </div>
                            </div>
                        </div>
                    </dl>
                </div>
            </li>
        </ol>
    </section>

    <HcisEmptyState
        v-else-if="showEmpty"
        title="Belum Ada Riwayat Kalibrasi Penilai"
        description="Seluruh penilai menyelesaikan evaluasi tanpa melakukan revisi skor atau jawaban kualitatif setelah pengisian awal."
        icon="shield-check"
        size="md"
        variant="flat"
        sentiment="neutral"
    />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { htmlAman } from '../../utils/htmlAman.js';
import { angka } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaRiwayatKalibrasiPenilai' });

const props = defineProps({
    riwayat: { type: Array, default: () => [] },
    filterAssignmentId: { type: [Number, String], default: null },
    showEmpty: { type: Boolean, default: false },
});

const selectedRaterKey = ref('ALL');

watch(
    () => props.filterAssignmentId,
    (val) => {
        if (val) {
            selectedRaterKey.value = String(val);
        } else {
            selectedRaterKey.value = 'ALL';
        }
    },
    { immediate: true },
);

const daftarPenilaiFilter = computed(() => {
    const list = [{ key: 'ALL', label: 'Semua Penilai', count: props.riwayat.length }];
    const map = new Map();

    props.riwayat.forEach((r) => {
        const key = String(r.assignment_id || r.penilai?.nama || r.kalibrasi_id);
        const nama = r.penilai?.nama || 'Penilai';
        const kategori = r.kategori?.nama || r.kategori?.kode || '';
        const label = kategori ? `${nama} (${kategori})` : nama;

        if (!map.has(key)) {
            map.set(key, { key, label, count: 0 });
        }
        map.get(key).count += 1;
    });

    map.forEach((item) => list.push(item));
    return list;
});

const filteredRiwayat = computed(() => {
    if (selectedRaterKey.value === 'ALL') return props.riwayat;
    return props.riwayat.filter(
        (r) => String(r.assignment_id || r.penilai?.nama || r.kalibrasi_id) === selectedRaterKey.value,
    );
});

function formatWaktu(v) {
    if (!v) return '—';
    try {
        return new Date(String(v).replace(' ', 'T')).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(v);
    }
}

function hitungDelta(sebelum, sesudah) {
    const s1 = Number(sebelum) || 0;
    const s2 = Number(sesudah) || 0;
    const diff = Math.round((s2 - s1) * 100) / 100;
    return {
        diff,
        formatted: (diff > 0 ? '+' : '') + angka(diff),
        isUp: diff > 0,
        isDown: diff < 0,
        isSame: diff === 0,
    };
}
</script>

<style scoped>
.pa-riwayat-kp {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Info Banner */
.pa-riwayat-kp__banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.9), rgba(238, 242, 255, 0.8));
    border: 1px solid rgba(196, 181, 253, 0.4);
    box-shadow: 0 1px 3px rgba(109, 40, 217, 0.04);
    flex-wrap: wrap;
}

.pa-riwayat-kp__banner-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-riwayat-kp__banner-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(139, 92, 246, 0.15);
    color: #7c3aed;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-riwayat-kp__banner-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-riwayat-kp h4,
.pa-riwayat-kp__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #4c1d95;
}

.pa-riwayat-kp__uraian {
    margin: 0;
    color: #6d28d9;
    font-size: 0.78rem;
    line-height: 1.4;
    opacity: 0.85;
}

/* Filter Bar */
.pa-riwayat-kp__filters {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.75rem;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    flex-wrap: wrap;
}

.pa-riwayat-kp__filter-lbl {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-riwayat-kp__filter-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-riwayat-kp__filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-riwayat-kp__filter-pill:hover {
    color: var(--hcis-brand, #3b82f6);
    border-color: var(--hcis-brand, #3b82f6);
    background: rgba(59, 130, 246, 0.05);
}

.pa-riwayat-kp__filter-pill.is-active {
    color: #ffffff;
    background: #7c3aed;
    border-color: #7c3aed;
    font-weight: 600;
}

.pa-riwayat-kp__filter-count {
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.06);
}

.pa-riwayat-kp__filter-pill.is-active .pa-riwayat-kp__filter-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Card List */
.pa-riwayat-kp__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-riwayat-kp__item {
    padding: 1rem 1.15rem;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* Card Header */
.pa-riwayat-kp__kepala {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-riwayat-kp__penilai-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-riwayat-kp__penilai-names {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-riwayat-kp__penilai-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-riwayat-kp__penilai-line strong {
    font-size: 0.9rem;
    color: var(--hcis-text-main, #0f172a);
}

.pa-nik-badge {
    font-family: monospace;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    background: rgba(241, 245, 249, 0.8);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-riwayat-kp__cat-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-sep {
    color: #cbd5e1;
}

.pa-riwayat-kp__time {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Reason Callout */
.pa-riwayat-kp__alasan-container {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(245, 243, 255, 0.7);
    border-left: 3px solid #8b5cf6;
}

.pa-riwayat-kp__alasan-icon {
    color: #8b5cf6;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.pa-riwayat-kp__alasan-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-riwayat-kp__alasan-title {
    font-size: 0.72rem;
    font-weight: 600;
    color: #6d28d9;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-riwayat-kp__alasan {
    margin: 0;
    font-size: 0.82rem;
    line-height: 1.45;
    color: var(--hcis-text-main, #334155);
    font-style: italic;
    overflow-wrap: anywhere;
}

/* Sections for Score and Essay */
.pa-riwayat-kp__scores-section,
.pa-riwayat-kp__essay-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-riwayat-kp__section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-text-main, #1e293b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Score list */
.pa-riwayat-kp ul,
.pa-riwayat-kp__score-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-riwayat-kp__score-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex-wrap: wrap;
}

.pa-riwayat-kp__score-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    color: var(--hcis-text-main, #1e293b);
}

.pa-code-mini {
    font-family: monospace;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    background: rgba(226, 232, 240, 0.6);
    padding: 0.05rem 0.3rem;
    border-radius: 3px;
}

.pa-riwayat-kp__score-values {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-family: monospace;
}

.pa-val-before {
    color: var(--hcis-text-muted, #94a3b8);
    text-decoration: line-through;
}

.pa-val-arrow {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-val-after {
    font-weight: 700;
    color: var(--hcis-text-main, #0f172a);
}

.pa-val-max {
    color: var(--hcis-text-muted, #94a3b8);
    font-size: 0.74rem;
}

.pa-delta-tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.pa-delta-tag.is-up {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.pa-delta-tag.is-down {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.pa-delta-tag.is-same {
    background: rgba(148, 163, 184, 0.12);
    color: #475569;
}

/* Essay diff */
.pa-riwayat-kp__esai {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin: 0;
}

.pa-riwayat-kp__essay-item {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-riwayat-kp__esai dt,
.pa-riwayat-kp__essay-dt {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-text-main, #1e293b);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-riwayat-kp__diff-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

.pa-diff-card {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-sm, 8px);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.8rem;
    line-height: 1.45;
}

.pa-diff-card.is-before {
    background: rgba(254, 242, 242, 0.6);
    border: 1px dashed rgba(248, 113, 113, 0.4);
}

.pa-diff-card.is-after {
    background: rgba(240, 253, 244, 0.7);
    border: 1px solid rgba(134, 239, 172, 0.5);
}

.pa-diff-badge {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.pa-diff-badge.is-before {
    color: #b91c1c;
    background: rgba(239, 68, 68, 0.12);
}

.pa-diff-badge.is-after {
    color: #15803d;
    background: rgba(34, 197, 94, 0.15);
}

.pa-riwayat-kp__sebelum {
    margin: 0;
    color: #991b1b;
    text-decoration: line-through;
    opacity: 0.85;
    overflow-wrap: anywhere;
}

.pa-riwayat-kp__sesudah {
    margin: 0;
    color: #14532d;
    font-weight: 500;
    overflow-wrap: anywhere;
}

.pa-riwayat-kp__esai dd :deep(p) {
    margin: 0;
}

@media (max-width: 768px) {
    .pa-riwayat-kp__diff-grid {
        grid-template-columns: 1fr;
    }
    .pa-riwayat-kp__score-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
    }
    .pa-riwayat-kp__kepala {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
