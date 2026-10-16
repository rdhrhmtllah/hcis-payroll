<template>
    <div class="pp">
        <div v-if="!peran" class="pp-kosong">
            <HcisIcon name="layout-sidebar-reverse" size="lg" />
            <div>
                Pilih <b>Lihat isi peran</b> pada kartu untuk melihat fungsi kerja dan wewenang yang didapat
                pemegangnya.
            </div>
        </div>
        <template v-else>
            <!-- Header Kartu Pratinjau -->
            <div v-if="!tanpaAksi" class="pp-kepala">
                <div class="min-w-0">
                    <div class="pp-nama">{{ peran.nama }}</div>
                    <div class="pp-meta">
                        <span class="font-monospace">{{ peran.kode }}</span>
                        <span v-if="peran.modul"> · {{ peran.modul }}</span>
                    </div>
                </div>
                <HcisBadge :label="badge.label" :variant="badge.variant" appearance="subtle" size="xs" />
            </div>

            <!-- ══ KARTU WEWENANG BISNIS OPERASIONAL ══ -->
            <div v-if="wb" class="pp-wewenang-box" :class="`pp-wewenang-box--${wb.tipe.toLowerCase()}`">
                <div class="d-flex align-items-center gap-2 mb-1.5">
                    <HcisIcon
                        :name="
                            wb.tipe === 'POOL_APPROVAL'
                                ? 'diagram-2-fill'
                                : wb.tipe === 'SUPER_ADMIN'
                                  ? 'shield-lock-fill'
                                  : wb.tipe === 'ADMIN'
                                    ? 'shield-fill-check'
                                    : 'window-stack'
                        "
                        size="xs"
                    />
                    <span class="pp-wewenang-title">{{ wb.judul }}</span>
                </div>
                <p class="pp-wewenang-desc mb-2">{{ wb.deskripsi }}</p>

                <!-- Jalur & Rantai Alur Persetujuan (Menu & Approval Matrix) -->
                <div v-if="wb.jalur_approval && wb.jalur_approval.length" class="mb-2.5">
                    <span class="ap-kicker mb-1 d-block text-warning-emphasis"> ALUR PERSETUJUAN TRANSAKSI: </span>
                    <div class="d-flex flex-column gap-1.5">
                        <div
                            v-for="j in wb.jalur_approval"
                            :key="j.kode_jalur"
                            class="pp-jalur-item p-2 rounded-2 bg-white border border-warning-subtle"
                        >
                            <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                <span class="fw-bold ap-kecil text-dark text-truncate">{{ j.nama_jalur }}</span>
                                <HcisBadge
                                    :label="`Tahap ${j.tahap_ke} / ${j.total_tahap}`"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>

                            <!-- Rantai Alur Step -->
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <template v-for="(lk, idx) in j.langkah" :key="lk.urutan">
                                    <span class="pp-step-pill" :class="{ 'pp-step-pill--target': lk.adalah_peran_ini }">
                                        <HcisIcon v-if="lk.adalah_peran_ini" name="check-circle-fill" size="2xs" />
                                        <span>{{ lk.urutan }}. {{ lk.nama }}</span>
                                    </span>
                                    <HcisIcon
                                        v-if="idx < j.langkah.length - 1"
                                        name="arrow-right"
                                        size="2xs"
                                        class="text-muted opacity-60"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kategori Tamu Khusus Visitor Dept -->
                <div v-if="wb.kategori_tamu && wb.kategori_tamu.length" class="mb-2.5">
                    <span class="ap-kicker mb-1 d-block text-warning-emphasis">KATEGORI TAMU YANG DITANGANI:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <div
                            v-for="kt in wb.kategori_tamu"
                            :key="kt.kategori || kt"
                            class="pp-tamu-item d-inline-flex align-items-center gap-1.5 p-1 px-2.5 rounded-2 bg-white border border-warning-subtle shadow-2xs"
                        >
                            <HcisIcon :name="kt.bisa_ajukan ? 'pencil-square' : 'eye'" size="2xs" color="warning" />
                            <span class="ap-kecil fw-semibold text-dark">{{
                                kt.kategori_label || kt.label || kt
                            }}</span>
                            <span
                                class="badge bg-warning-subtle text-warning-emphasis rounded-pill ms-0.5"
                                style="font-size: 0.65rem"
                            >
                                {{ kt.aksi_label || (kt.bisa_ajukan ? 'Lihat & Ajukan' : 'Lihat Jadwal') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Poin-poin Kuasa / Wewenang Kerja -->
                <div
                    v-if="wb.poin && wb.poin.length"
                    class="pp-wewenang-poin-wrap pt-1.5 border-top border-light-subtle"
                >
                    <span class="ap-kicker mb-1 d-block">LINGKUP KUASA KERJA:</span>
                    <ul class="pp-wewenang-list mb-0 ps-3">
                        <li v-for="(pt, idx) in wb.poin" :key="idx">{{ pt }}</li>
                    </ul>
                </div>
            </div>

            <!-- Status Alerts (Peran lama / Kembar) -->
            <div v-if="info.lama" class="pp-catatan pp-catatan--warning">
                <HcisIcon name="exclamation-triangle-fill" size="xs" class="text-amber-600" />
                <span>Peran lama — akan digabungkan ke Admin modul pada rilis berikutnya.</span>
            </div>
            <div v-if="info.kembar?.length" class="pp-catatan">
                <HcisIcon name="info-circle" size="xs" class="text-muted" />
                <span
                    >Hak akses identik dengan: <b>{{ info.kembar.map((k) => k.nama).join(', ') }}</b></span
                >
            </div>

            <!-- ══ KEMAMPUAN FITUR BISNIS TERDAFTAR ══ -->
            <div
                v-if="kemampuanList && kemampuanList.length"
                class="pp-kemampuan-box mt-2.5 p-2 rounded-2 bg-white border border-light-subtle"
            >
                <span class="ap-kicker mb-1 d-block text-brand"
                    >KEMAMPUAN FITUR BISNIS ({{ kemampuanList.length }}):</span
                >
                <div class="d-flex flex-wrap gap-1">
                    <HcisBadge
                        v-for="k in kemampuanList"
                        :key="k.kode_kemampuan"
                        :label="k.nama_kemampuan"
                        variant="brand"
                        appearance="subtle"
                        size="xs"
                    />
                </div>
            </div>

            <!-- ══ SEKSI DAFTAR MENU & HALAMAN NAVIGASI ══ -->
            <div class="pp-nav-section mt-2.5">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <span class="ap-kicker text-muted">
                        {{
                            peran.jenis === 'POOL_APPROVAL'
                                ? 'HALAMAN PENDUKUNG PROSES'
                                : peran.tingkat === 'SUPER_ADMIN'
                                  ? 'SELURUH MENU MODUL'
                                  : 'MENU & FITUR OPERASIONAL'
                        }}
                    </span>
                    <span v-if="halaman" class="pp-nav-count badge bg-light text-secondary border">
                        {{ halaman.length }} Menu
                    </span>
                </div>

                <div class="pp-isi">
                    <HcisSkeleton v-if="!halaman" :lines="4" />
                    <DaftarIsiPeran v-else :halaman="halaman" />
                </div>
            </div>

            <template v-if="!tanpaAksi">
                <HcisButton
                    class="mt-3"
                    block
                    size="sm"
                    :variant="dipilih ? 'secondary' : 'primary'"
                    :leading-icon="dipilih ? 'x-lg' : 'check-lg'"
                    :label="dipilih ? 'Lepas peran ini' : 'Pilih peran ini'"
                    :disabled="!bisa"
                    @click="$emit('pilih')"
                />
                <div v-if="!bisa && alasan" class="pp-alasan">{{ alasan }}</div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisButton, HcisIcon, HcisSkeleton } from '@/components/ui';
import DaftarIsiPeran from './DaftarIsiPeran.vue';

/** Panel isi peran (master–detail di langkah Pilih Peran wizard). */
const props = defineProps({
    peran: { type: Object, default: null },
    /** null = masih dimuat */
    halaman: { type: Array, default: null },
    /** wewenang bisnis terstruktur dari backend */
    wewenangBisnis: { type: Object, default: null },
    daftarKemampuan: { type: Array, default: () => [] },
    /** { lama, kembar } dari daftar peran */
    info: { type: Object, default: () => ({}) },
    badge: { type: Object, default: () => ({ label: 'Peran', variant: 'neutral' }) },
    dipilih: { type: Boolean, default: false },
    bisa: { type: Boolean, default: true },
    alasan: { type: String, default: '' },
    tanpaAksi: { type: Boolean, default: false },
});
defineEmits(['pilih']);

const wb = computed(() => props.wewenangBisnis || props.peran?.wewenang_bisnis || null);
const kemampuanList = computed(() =>
    props.daftarKemampuan?.length ? props.daftarKemampuan : props.peran?.daftar_kemampuan || [],
);
</script>

<style scoped>
.pp {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.pp-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    padding: var(--hcis-space-6, 1.5rem) var(--hcis-space-3, 0.75rem);
    text-align: center;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted);
}
.pp-kepala {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--hcis-space-2, 0.5rem);
}
.pp-nama {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
    line-height: 1.3;
}
.pp-meta {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

/* ══ KARTU WEWENANG BISNIS ══ */
.pp-jalur-item {
    font-size: 0.6875rem;
}
.pp-step-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.4rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: #f1f5f9;
    color: #475569;
    font-size: 0.625rem;
    font-weight: 600;
}
.pp-step-pill--target {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}
.pp-wewenang-box {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    background: #f8fafc;
}
.pp-wewenang-box--pool_approval {
    background: #fffdf5;
    border-color: #fde68a;
    color: #78350f;
}
.pp-wewenang-box--super_admin {
    background: #faf5ff;
    border-color: #e9d5ff;
    color: #581c87;
}
.pp-wewenang-box--admin {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0369a1;
}
.pp-wewenang-box--akses {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #334155;
}
.pp-wewenang-title {
    font-weight: 700;
    font-size: 0.8125rem;
    letter-spacing: -0.01em;
}
.pp-wewenang-desc {
    font-size: 0.75rem;
    line-height: 1.45;
    margin-bottom: 0.5rem;
}
.pp-wewenang-list {
    font-size: 0.6875rem;
    line-height: 1.4;
    color: inherit;
    opacity: 0.9;
}
.pp-wewenang-list li {
    margin-bottom: 0.25rem;
}

.pp-catatan {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    font-size: 0.75rem;
    color: var(--hcis-text, #334155);
}
.pp-catatan--warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}
.pp-nav-count {
    font-size: 0.6875rem;
    font-weight: 600;
}
.pp-isi {
    min-height: 0;
}
.pp-alasan {
    margin-top: var(--hcis-space-1, 0.25rem);
    font-size: 0.6875rem;
    color: var(--hcis-text-muted);
    text-align: center;
}
.min-w-0 {
    min-width: 0;
}
</style>
