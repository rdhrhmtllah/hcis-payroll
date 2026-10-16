<template>
    <Head title="Peta Migrasi Legacy — HCIS Design System" />

    <div class="hcis-page hcis-stack ds-migration-root">
        <!-- Top Portal Navigation -->
        <DsPortalNav />

        <!-- Header -->
        <HcisHeader
            title="Peta & Panduan Migrasi Komponen Legacy"
            subtitle="Daftar penggantian 1-ke-1 dari komponen lama ke komponen native HCIS Design System 2.0. Seluruh komponen legacy di @/components/legacy telah DIBEKUKAN."
        >
            <template #actions>
                <div class="ds-migration-badges">
                    <HcisBadge label="Legacy Status: FROZEN" variant="danger" dot />
                    <HcisBadge label="Target: 100% Native" variant="primary" />
                </div>
            </template>
        </HcisHeader>

        <!-- Migration Strategy Summary Cards -->
        <HcisStatGroup>
            <HcisStatCard label="Komponen Legacy Beku" value="9" caption="di components/legacy/" sentiment="neutral" />
            <HcisStatCard label="Padanan Resmi Hcis*" value="100%" caption="seluruhnya tersedia di components/ui/" sentiment="good" />
            <HcisStatCard label="Strategi Migrasi" value="Bertahap" caption="saat fitur disentuh, bukan big-bang rewrite" />
        </HcisStatGroup>

        <!-- 4 Langkah Protokol Migrasi Aman -->
        <HcisCard title="Protokol 4 Langkah Migrasi Aman (Safe Migration Playbook)" description="Ikuti langkah ini untuk mencegah regresi visual atau kebocoran gaya." divider>
            <div class="ds-steps-grid">
                <div class="ds-step-box">
                    <div class="ds-step-num">1</div>
                    <h4 class="ds-step-title">Cek Gaya Global Bocor</h4>
                    <p class="ds-step-desc">
                        Komponen lama sering memakai <code>&lt;style&gt;</code> tanpa <code>scoped</code>. Periksa apakah ada halaman lain yang menumpang gaya kelas tersebut sebelum menghapusnya.
                    </p>
                </div>

                <div class="ds-step-box">
                    <div class="ds-step-num">2</div>
                    <h4 class="ds-step-title">Cocokkan Kontrak API</h4>
                    <p class="ds-step-desc">
                        Bila komponen lama mengulang isinya sendiri (mis. 5 baris skeleton) sedangkan komponen baru merender 1, pindahkan pengulangan ke <code>v-for</code> di pemanggil.
                    </p>
                </div>

                <div class="ds-step-box">
                    <div class="ds-step-num">3</div>
                    <h4 class="ds-step-title">Pisahkan v-if & v-for</h4>
                    <p class="ds-step-desc">
                        Hindari menaruh <code>v-if</code> dan <code>v-for</code> pada satu elemen yang sama. Bungkus dengan <code>&lt;template v-if="..."&gt;</code>.
                    </p>
                </div>

                <div class="ds-step-box">
                    <div class="ds-step-num">4</div>
                    <h4 class="ds-step-title">Jalankan Gerbang Otomatis</h4>
                    <p class="ds-step-desc">
                        Wajib lolos <code>verify-frontend-imports.php</code>, <code>vitest</code>, dan <code>npm run build</code> sebelum diajukan ke review.
                    </p>
                </div>
            </div>
        </HcisCard>

        <!-- CHEATSHEET TABEL MIGRASI -->
        <HcisCard title="Tabel Padanan Komponen (Legacy Cheatsheet)" description="Cari komponen lama untuk melihat padanan resmi Hcis* dan contoh kodenya." divider>
            <div style="margin-bottom: var(--hcis-space-4);">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="ds-search-input"
                    placeholder="Cari komponen legacy, misal: modalV1, table-noir, .btn, modernFilter…"
                />
            </div>

            <HcisTable
                :columns="[
                    { key: 'legacy', label: 'Komponen / Tag Lama' },
                    { key: 'replacement', label: 'Padanan Resmi Hcis*' },
                    { key: 'category', label: 'Kategori' },
                    { key: 'action', label: 'Contoh Transformasi', align: 'right' },
                ]"
                :rows="filteredMappings"
                row-key="legacy"
            >
                <template #cell-legacy="{ row }">
                    <code class="ds-legacy-tag">{{ row.legacy }}</code>
                </template>
                <template #cell-replacement="{ row }">
                    <div style="display: flex; align-items: center; gap: var(--hcis-space-2);">
                        <code class="ds-hcis-tag">{{ row.replacement }}</code>
                        <HcisBadge label="RESMI" variant="success" size="xs" />
                    </div>
                </template>
                <template #cell-category="{ row }">
                    <span style="font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">{{ row.category }}</span>
                </template>
                <template #cell-action="{ row }">
                    <HcisButton
                        label="Lihat Diff Kode"
                        variant="ghost"
                        size="sm"
                        icon="code"
                        @click="selectedItem = row"
                    />
                </template>
            </HcisTable>
        </HcisCard>

        <!-- Modal Code Diff Viewer -->
        <HcisDialog
            v-if="selectedItem"
            v-model="isModalOpen"
            :title="`Transformasi: ${selectedItem.legacy} → ${selectedItem.replacement}`"
            width="750px"
        >
            <div class="hcis-stack hcis-stack--md">
                <p style="margin: 0; font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">
                    {{ selectedItem.notes }}
                </p>

                <div class="hcis-grid hcis-grid--2" style="gap: var(--hcis-space-4);">
                    <div>
                        <div class="ds-diff-badge ds-diff-badge--before">SEBELUM (Legacy)</div>
                        <pre class="ds-diff-code ds-diff-code--before"><code>{{ selectedItem.beforeCode }}</code></pre>
                    </div>
                    <div>
                        <div class="ds-diff-badge ds-diff-badge--after">SESUDAH (Design System)</div>
                        <pre class="ds-diff-code ds-diff-code--after"><code>{{ selectedItem.afterCode }}</code></pre>
                    </div>
                </div>
            </div>

            <template #footer>
                <div style="display: flex; justify-content: flex-end;">
                    <HcisButton label="Tutup" variant="primary" size="sm" @click="selectedItem = null" />
                </div>
            </template>
        </HcisDialog>

        <DsMotionBanner />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    HcisHeader,
    HcisCard,
    HcisBadge,
    HcisButton,
    HcisStatGroup,
    HcisStatCard,
    HcisTable,
    HcisDialog,
    HcisIcon,
} from '@/components/ui';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';

const searchQuery = ref('');
const selectedItem = ref(null);

const isModalOpen = computed({
    get: () => selectedItem.value !== null,
    set: (val) => {
        if (!val) selectedItem.value = null;
    },
});

const mappings = [
    {
        legacy: 'modalV1 / modalV2-noir',
        replacement: '<HcisDialog>',
        category: 'Dialog & Modal',
        notes: 'HcisDialog membawa focus trap bawaan, penguncian scroll body, dan penataan z-index terstandarisasi.',
        beforeCode: `<modalV2-noir
  :is-open="showModal"
  title="Edit Data"
  @close="showModal = false"
>
  <p>Konten modal...</p>
</modalV2-noir>`,
        afterCode: `<HcisDialog
  v-model="showModal"
  title="Edit Data"
  width="600px"
>
  <p>Konten modal...</p>
</HcisDialog>`,
    },
    {
        legacy: 'table-noir / .table (Bootstrap)',
        replacement: '<HcisTable> / <HcisDataTable>',
        category: 'Data & Tabel',
        notes: 'HcisTable otomatis menangani responsivitas kolom, styling hover zebra token, dan sticky header.',
        beforeCode: `<table class="table table-hover">
  <thead>
    <tr><th>Nama</th><th>Aksi</th></tr>
  </thead>
  <tbody>
    <tr v-for="i in items">
      <td>{{ i.name }}</td>
    </tr>
  </tbody>
</table>`,
        afterCode: `<HcisTable
  :columns="[{ key: 'name', label: 'Nama' }, { key: 'action', label: 'Aksi' }]"
  :rows="items"
  row-key="id"
>
  <template #cell-name="{ value }">
    <strong>{{ value }}</strong>
  </template>
</HcisTable>`,
    },
    {
        legacy: 'Filter/modernFilter',
        replacement: '<HcisFilterBar>',
        category: 'Filter & Pencarian',
        notes: 'HcisFilterBar menyatukan input pencarian, saringan multi-kolom lipat, dan pil status cepat.',
        beforeCode: `<modernFilter
  v-model:search="search"
  :filters="filterConfig"
/>`,
        afterCode: `<HcisFilterBar
  v-model:search="search"
  variant="glass"
  :quick-chips="statusChips"
  :active-chip-value="activeStatus"
  @chip-click="onStatusChange"
/>`,
    },
    {
        legacy: 'toggleSectionV2 / listAccordionUiV1',
        replacement: '<HcisCollapse> + <HcisCollapseItem>',
        category: 'Wadah & Akordeon',
        notes: 'Animasi buka-tutup halus dengan kurva gerak token dan dukungan multi-panel.',
        beforeCode: `<toggleSectionV2 title="Detail Gaji">
  <p>Konten rincian...</p>
</toggleSectionV2>`,
        afterCode: `<HcisCollapse v-model="activePanels">
  <HcisCollapseItem name="gaji" title="Detail Gaji">
    <p>Konten rincian...</p>
  </HcisCollapseItem>
</HcisCollapse>`,
    },
    {
        legacy: 'quickStat',
        replacement: '<HcisStatGroup> + <HcisStatCard>',
        category: 'Metrik & Statistik',
        notes: 'Memberikan jarak dan ritme grid metrik otomatis di atas halaman.',
        beforeCode: `<div class="row">
  <div class="col-md-3">
    <quickStat label="Total" value="100" />
  </div>
</div>`,
        afterCode: `<HcisStatGroup>
  <HcisStatCard label="Total" value="100" />
  <HcisStatCard label="Aktif" value="95" sentiment="good" />
</HcisStatGroup>`,
    },
    {
        legacy: 'skeletonList',
        replacement: '<HcisSkeleton>',
        category: 'Loading State',
        notes: 'HcisSkeleton fleksibel meniru baris, lingkaran avatar, atau kotak kartu.',
        beforeCode: `<skeletonList :count="5" />`,
        afterCode: `<div class="hcis-stack hcis-stack--sm">
  <HcisSkeleton v-for="i in 5" :key="i" height="2.5rem" />
</div>`,
    },
    {
        legacy: '.btn.btn-primary / .btn-secondary (Bootstrap)',
        replacement: '<HcisButton variant="primary | secondary | ghost">',
        category: 'Aksi & Tombol',
        notes: 'Mendukung prop loading otomatis, pencegahan salah kirim form (type="button"), dan token sentimen.',
        beforeCode: `<button class="btn btn-primary" :disabled="loading">
  <span v-if="loading" class="spinner-border"></span> Simpan
</button>`,
        afterCode: `<HcisButton
  label="Simpan"
  variant="primary"
  :loading="loading"
/>`,
    },
    {
        legacy: '.badge.bg-success / .stitch-badge',
        replacement: '<HcisBadge status="..." | variant="...">',
        category: 'Lencana Status',
        notes: 'Otomatis memetakan kode status otorisasi HCIS (APPROVED, PENDING, REJECTED) ke warna & ikon.',
        beforeCode: `<span class="badge bg-success">APPROVED</span>`,
        afterCode: `<HcisBadge status="APPROVED" />`,
    },
];

const filteredMappings = computed(() => {
    if (!searchQuery.value) return mappings;
    const q = searchQuery.value.toLowerCase();
    return mappings.filter(
        (m) =>
            m.legacy.toLowerCase().includes(q) ||
            m.replacement.toLowerCase().includes(q) ||
            m.category.toLowerCase().includes(q)
    );
});
</script>

<style scoped>
.ds-migration-root {
    padding-bottom: var(--hcis-space-12);
}

.ds-migration-badges {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.ds-steps-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--hcis-space-4);
    margin-top: var(--hcis-space-2);
}

@media (max-width: 900px) {
    .ds-steps-grid {
        grid-template-columns: 1fr;
    }
}

.ds-step-box {
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    padding: var(--hcis-space-4);
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2);
}

.ds-step-num {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: var(--hcis-brand-600);
    color: var(--hcis-white);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-black);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ds-step-title {
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
}

.ds-step-desc {
    margin: 0;
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-muted);
    line-height: 1.4;
}

.ds-search-input {
    width: 100%;
    height: 2.5rem;
    padding: 0 var(--hcis-space-4);
    border-radius: var(--hcis-radius-lg);
    border: 1px solid var(--hcis-border-strong);
    background: var(--hcis-surface);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-xs);
    outline: none;
}

.ds-search-input:focus {
    border-color: var(--hcis-brand-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.15);
}

.ds-legacy-tag {
    background: var(--hcis-danger-50);
    color: var(--hcis-danger-700);
    border: 1px solid var(--hcis-danger-200);
    padding: 2px 6px;
    border-radius: var(--hcis-radius-sm);
    font-size: var(--hcis-font-size-2xs);
    font-family: var(--hcis-font-mono);
}

.ds-hcis-tag {
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    border: 1px solid var(--hcis-brand-200);
    padding: 2px 6px;
    border-radius: var(--hcis-radius-sm);
    font-size: var(--hcis-font-size-2xs);
    font-family: var(--hcis-font-mono);
    font-weight: 700;
}

.ds-diff-badge {
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-black);
    padding: 4px 8px;
    border-radius: var(--hcis-radius-md) var(--hcis-radius-md) 0 0;
    text-transform: uppercase;
}

.ds-diff-badge--before {
    background: var(--hcis-danger-100);
    color: var(--hcis-danger-700);
}

.ds-diff-badge--after {
    background: var(--hcis-success-100);
    color: var(--hcis-success-700);
}

.ds-diff-code {
    margin: 0;
    padding: var(--hcis-space-3);
    border-radius: 0 0 var(--hcis-radius-md) var(--hcis-radius-md);
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-2xs);
    line-height: 1.4;
    overflow-x: auto;
}

.ds-diff-code--before {
    background: var(--hcis-slate-900);
    color: var(--hcis-danger-400);
    border: 1px solid var(--hcis-danger-200);
}

.ds-diff-code--after {
    background: var(--hcis-slate-900);
    color: var(--hcis-emerald-400);
    border: 1px solid var(--hcis-success-200);
}
</style>
