<template>
    <div class="ly">
        <!-- Rail Pengelompokan Kiri (Desktop Mode) -->
        <aside class="ly-rail">
            <div class="ly-kartu">
                <!-- Dual-Mode Switcher: Metrik vs Folder -->
                <div class="ly-rail-sticky-top">
                    <div class="sm-mode-tabs" role="tablist" aria-label="Mode Pengelompokan">
                        <button
                            type="button"
                            role="tab"
                            class="sm-mode-tab"
                            :class="{ active: currentRailMode === 'sumbu' }"
                            :aria-selected="currentRailMode === 'sumbu'"
                            title="Tampilan kelompok berdasarkan sumbu metrik"
                            @click="setRailMode('sumbu')"
                        >
                            <HcisIcon name="sliders2" size="2xs" />
                            <span>Metrik</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            class="sm-mode-tab"
                            :class="{ active: currentRailMode === 'tree' }"
                            :aria-selected="currentRailMode === 'tree'"
                            title="Tampilan hierarki pohon folder departemen dan jenis dokumen"
                            @click="setRailMode('tree')"
                        >
                            <HcisIcon name="folder2" size="2xs" />
                            <span>Folder</span>
                        </button>
                    </div>
                </div>

                <div class="ly-rail-content">
                    <template v-if="currentRailMode === 'tree'">
                        <PohonFolderSop
                            :loading="loading"
                            :tree="tree"
                            :active-dept="activeTreeDept"
                            :active-jenis="activeTreeJenis"
                            :total-semua="totalSemua"
                            @pilih-dept="$emit('pilih-tree-dept', $event)"
                            @pilih-sub="$emit('pilih-tree-sub', $event)"
                            @pilih-semua="$emit('pilih-tree-semua')"
                            @select-dept="$emit('pilih-tree-dept', $event)"
                            @select-sub="$emit('pilih-tree-sub', $event)"
                            @select-all="$emit('pilih-tree-semua')"
                        />
                    </template>
                    <template v-else>
                        <!-- Pemindah sumbu -->
                        <div class="sm-pindah" role="group" aria-label="Kelompokkan berdasarkan">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="sm-pindah-label">
                                    <HcisIcon name="diagram-3" size="2xs" class="me-1 opacity-75" />
                                    Kelompokkan per
                                </span>
                            </div>
                            <HcisSelect
                                :model-value="sumbuAktif"
                                size="sm"
                                :leading-icon="(sumbuTersedia.find((s) => s.key === sumbuAktif) || {}).ikon || 'diagram-3-fill'"
                                placeholder="Pilih sumbu kelompok…"
                                :clearable="false"
                                class="w-100"
                                @update:model-value="$emit('update:sumbu', $event)"
                            >
                                <HcisSelectOption
                                    v-for="s in sumbuTersedia"
                                    :key="s.key"
                                    :value="s.key"
                                    :label="s.label"
                                >
                                    <div class="d-flex align-items-center gap-2">
                                        <HcisIcon :name="s.ikon" size="xs" />
                                        <span>{{ s.label }}</span>
                                    </div>
                                </HcisSelectOption>
                            </HcisSelect>
                        </div>

                        <RailSumbu
                            :loading="loading"
                            :grup="grup"
                            :aktif="grupTerpilih"
                            :judul="sumbuMeta.label"
                            :jamak="sumbuMeta.jamak"
                            :penempatan="penempatan"
                            :total-semua="totalSemua"
                            :persen-semua="persenSemua"
                            :bar="bar"
                            :label-semua="labelSemua"
                            @pilih="$emit('update:grup', $event)"
                        />
                    </template>
                </div>
            </div>
        </aside>

        <!-- Panel Konten Utama Kanan -->
        <div class="ly-main-wrapper">
            <HcisCard
                variant="glass"
                :sentiment="sentiment"
                pattern="grid"
                size="md"
                class="ly-main-card"
            >
                <div class="ly-main-inner">
                    <!-- Mobile Rail Grouping Trigger Bar -->
                    <div v-if="isMobile" class="sm-rail-trigger-wrap">
                        <button
                            type="button"
                            class="sm-rail-trigger-btn"
                            :class="{ 'has-active-group': isGrupActive }"
                            @click="drawerRailOpen = true"
                        >
                            <div class="sm-rail-trigger-left">
                                <span class="sm-rail-trigger-icon">
                                    <HcisIcon :name="currentRailMode === 'tree' ? 'folder2-open' : 'diagram-3-fill'" size="2xs" />
                                </span>
                                <div class="sm-rail-trigger-texts">
                                    <span class="sm-rail-trigger-type">
                                        {{ currentRailMode === 'tree' ? 'Hierarki Folder' : `Kelompok ${sumbuMeta.label || ''}` }}
                                    </span>
                                    <strong class="sm-rail-trigger-val text-truncate">{{ labelKelompokAktif }}</strong>
                                </div>
                            </div>
                            <div class="sm-rail-trigger-right">
                                <span class="sm-rail-trigger-count tnum">{{ jumlahTampil }} {{ satuanTampil }}</span>
                                <HcisIcon name="chevron-expand" size="2xs" class="sm-rail-trigger-caret" />
                            </div>
                        </button>
                    </div>

                    <!-- Bilah Saringan Aktif Modern -->
                    <transition name="el-fade-in-linear">
                        <div v-if="saringanAktif && saringanAktif.length" class="tk-bar">
                            <div class="tk-bar-left">
                                <span class="tk-indicator">
                                    <HcisIcon name="funnel-fill" size="2xs" class="tk-indicator-icon" />
                                    <span>Filter Aktif:</span>
                                </span>
                                <div class="tk-chips">
                                    <div
                                        v-for="s in saringanAktif"
                                        :key="s.kunci"
                                        class="tk-chip"
                                        :class="s.kelas"
                                    >
                                        <span class="tk-chip-badge">
                                            <HcisIcon :name="s.ikon || 'funnel'" size="2xs" class="tk-chip-icon" />
                                            <span class="tk-chip-label">{{ s.label }}</span>
                                        </span>
                                        <span class="tk-chip-val" :title="s.nilai">{{ s.nilai }}</span>
                                        <button
                                            type="button"
                                            class="tk-chip-del"
                                            :title="`Hapus filter ${s.label}`"
                                            :aria-label="`Hapus filter ${s.label}`"
                                            @click="$emit('hapus-saringan', s.kunci)"
                                        >
                                            <HcisIcon name="x" size="xs" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="tk-bar-right">
                                <button
                                    type="button"
                                    class="tk-clear-all"
                                    title="Reset semua filter"
                                    aria-label="Reset semua filter"
                                    @click="$emit('reset-saringan')"
                                >
                                    <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                    <span>Reset Filter</span>
                                </button>
                            </div>
                        </div>
                    </transition>

                    <!-- Info Controls Baris Atas -->
                    <div v-if="tampilkanViewToggle || tampilkanInfoJumlah" class="tk-controls-row">
                        <div v-if="tampilkanViewToggle" class="tk-tampilan" role="group" aria-label="Bentuk tampilan daftar">
                            <button
                                v-for="t in daftarTampilan"
                                :key="t.key"
                                type="button"
                                class="tk-tampilan-btn"
                                :class="{ on: tampilan === t.key }"
                                :title="t.judul"
                                :aria-pressed="tampilan === t.key"
                                @click="$emit('update:tampilan', t.key)"
                            >
                                <HcisIcon :name="t.ikon" size="2xs" />
                                <span class="tk-tampilan-teks">{{ t.label }}</span>
                            </button>
                        </div>

                        <span v-if="tampilkanInfoJumlah" class="tk-info tnum ms-auto">
                            <HcisIcon name="layers-fill" size="2xs" />
                            <strong>{{ jumlahTampil }}</strong> {{ satuanTampil }}
                        </span>
                    </div>

                    <!-- Skeleton Loading Sesuai Mode Tampilan -->
                    <div v-if="loading" class="tk-skel">
                        <!-- Mode Kartu Grid -->
                        <div v-if="tampilan === 'kartu'" class="pk-grid">
                            <div
                                v-for="n in 6"
                                :key="n"
                                class="tg-card pe-none"
                                style="opacity: 0.88; pointer-events: none;"
                            >
                                <div class="tg-cover d-flex align-items-center justify-content-center" style="background: rgba(241, 245, 249, 0.85);">
                                    <HcisSkeleton width="38px" height="38px" rounded="md" />
                                    <div class="tg-cover-badges">
                                        <HcisSkeleton width="70px" height="18px" rounded="full" />
                                        <HcisSkeleton width="50px" height="18px" rounded="full" />
                                    </div>
                                </div>
                                <div class="tg-body">
                                    <div class="d-flex gap-1.5 mb-2">
                                        <HcisSkeleton width="80px" height="18px" rounded="xs" />
                                        <HcisSkeleton width="65px" height="18px" rounded="xs" />
                                    </div>
                                    <HcisSkeleton width="90%" height="1.15rem" rounded="xs" class="mb-1" />
                                    <HcisSkeleton width="55%" height="1.15rem" rounded="xs" class="mb-3" />
                                    <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                                        <HcisSkeleton width="85px" height="13px" rounded="xs" />
                                        <HcisSkeleton width="45px" height="13px" rounded="xs" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mode Baris Dokumen List -->
                        <div v-else class="sop-list">
                            <div
                                v-for="n in 6"
                                :key="n"
                                class="d-flex align-items-center gap-3 p-3 mb-2 bg-white border rounded-3 pe-none"
                                style="opacity: 0.88; pointer-events: none;"
                            >
                                <HcisSkeleton width="36px" height="36px" rounded="md" />
                                <div class="flex-grow-1 min-w-0 d-flex flex-column gap-1.5">
                                    <div class="d-flex align-items-center gap-2">
                                        <HcisSkeleton width="90px" height="14px" rounded="xs" />
                                        <HcisSkeleton width="45%" height="1rem" rounded="xs" />
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <HcisSkeleton width="70px" height="16px" rounded="xs" />
                                        <HcisSkeleton width="80px" height="16px" rounded="xs" />
                                        <HcisSkeleton width="110px" height="16px" rounded="xs" />
                                    </div>
                                </div>
                                <HcisSkeleton width="24px" height="24px" rounded="full" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!adaIsi" class="py-4">
                        <HcisEmptyState
                            :icon="kosong.icon"
                            :title="kosong.title"
                            :description="kosong.description"
                            variant="dashed"
                            sentiment="neutral"
                        >
                            <template #actions>
                                <slot name="kosong-aksi" />
                            </template>
                        </HcisEmptyState>
                    </div>

                    <!-- Panel Konten Kelompok Aktif -->
                    <section v-else class="mk-pane">
                        <header class="mk-head" :style="grupAktif ? { '--kc': grupAktif.warna } : {}">
                            <span class="mk-ico">
                                <HcisIcon :name="grupAktif ? grupAktif.ikon : ikonSemua" size="sm" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="mk-judul">{{ grupAktif ? grupAktif.nama : judulSemua }}</h2>
                                <p class="mk-sub tnum">{{ sub }}</p>
                            </div>
                            <div v-if="grupAktif && bar" class="mk-meter">
                                <span class="mk-bar"><span :style="{ width: grupAktif.persen + '%' }"></span></span>
                                <span class="mk-pct tnum">{{ grupAktif.persen }}%</span>
                            </div>
                        </header>

                        <!-- Daftar Dokumen — satu baris satu item -->
                        <div v-if="tampilan === 'dokumen'" class="sop-list sop-stagger">
                            <BarisDokumen
                                v-for="d in dokumen"
                                :key="d.Id_SOP_Paket_Item"
                                :d="d"
                                :rujukan="rujukan"
                            />
                        </div>

                        <!-- Grid Kartu View -->
                        <div v-else-if="tampilan === 'kartu'" class="pk-grid sop-stagger">
                            <div v-for="k in items" :key="k.Id_SOP_Paket" class="d-flex flex-column">
                                <SopTugasCard
                                    :tugas="k"
                                    :rujukan="rujukan"
                                    :busy="busyId === k.Id_SOP_Paket"
                                    @open="$emit('open', $event)"
                                >
                                    <template v-if="$slots['aksi-kartu']" #aksi="slotProps">
                                        <slot name="aksi-kartu" v-bind="slotProps" />
                                    </template>
                                </SopTugasCard>
                                <p v-if="k.Cocok_Pada && k.Cocok_Pada.length" class="mk-cocok">
                                    <HcisIcon name="arrow-return-right" size="2xs" /> Cocok pada:
                                    {{ k.Cocok_Pada.join(' · ') }}
                                </p>
                            </div>
                        </div>

                        <!-- List / Table Baris View -->
                        <div v-else class="sop-list sop-stagger">
                            <BarisPaket
                                v-for="k in items"
                                :key="k.Id_SOP_Paket"
                                :k="k"
                                :rujukan="rujukan"
                                @open="$emit('open', $event)"
                            />
                        </div>

                        <!--
                            PAGINASI HANYA MUNCUL BILA PEMANGGIL MEMBERIKANNYA.

                            Functional Document memuat seluruh tugasnya sekaligus
                            dan memotong di memori, jadi ia tak mengirim prop ini
                            dan bilahnya tak pernah lahir. Public Document memotong
                            di server dan mengirimkannya. Satu `v-if`, bukan dua
                            jalur logika — bentuk daftarnya tetap satu.
                        -->
                        <div v-if="paginasi && paginasi.halamanTotal > 1" class="mk-paging">
                            <HcisButton
                                label="Sebelumnya"
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-left"
                                :disabled="paginasi.halaman <= 1"
                                @click="$emit('update:halaman', paginasi.halaman - 1)"
                            />
                            <span class="mk-paging-info tnum">
                                Halaman {{ paginasi.halaman }} dari {{ paginasi.halamanTotal }} · {{ paginasi.total }} {{ satuanTampil }}
                            </span>
                            <HcisButton
                                label="Berikutnya"
                                variant="secondary"
                                size="sm"
                                trailing-icon="arrow-right"
                                :disabled="paginasi.halaman >= paginasi.halamanTotal"
                                @click="$emit('update:halaman', paginasi.halaman + 1)"
                            />
                        </div>
                    </section>
                </div>
            </HcisCard>
        </div>

        <!-- Mobile Bottom Sheet Drawer for Grouping & Folders -->
        <HcisDrawer
            v-if="isMobile"
            v-model="drawerRailOpen"
            direction="btt"
            size="82vh"
            title="Kelompokkan Dokumen"
            :description="currentRailMode === 'tree' ? 'Pilih folder & departemen' : `Pilih kelompok berdasarkan ${sumbuMeta.label || 'kategori'}`"
            icon="diagram-3-fill"
            :sentiment="sentiment || 'purple'"
        >
            <div class="sm-drawer-rail-body">
                <!-- Dual-Mode Switcher -->
                <div class="sm-mode-tabs mb-3" role="tablist" aria-label="Mode Pengelompokan">
                    <button
                        type="button"
                        role="tab"
                        class="sm-mode-tab"
                        :class="{ active: currentRailMode === 'sumbu' }"
                        :aria-selected="currentRailMode === 'sumbu'"
                        @click="setRailMode('sumbu')"
                    >
                        <HcisIcon name="sliders2" size="2xs" />
                        <span>Metrik</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="sm-mode-tab"
                        :class="{ active: currentRailMode === 'tree' }"
                        :aria-selected="currentRailMode === 'tree'"
                        @click="setRailMode('tree')"
                    >
                        <HcisIcon name="folder2" size="2xs" />
                        <span>Folder</span>
                    </button>
                </div>

                <template v-if="currentRailMode === 'tree'">
                    <PohonFolderSop
                        :loading="loading"
                        :tree="tree"
                        :active-dept="activeTreeDept"
                        :active-jenis="activeTreeJenis"
                        :total-semua="totalSemua"
                        @pilih-dept="onSelectTreeDept"
                        @pilih-sub="onSelectTreeSub"
                        @pilih-semua="onSelectTreeSemua"
                        @select-dept="onSelectTreeDept"
                        @select-sub="onSelectTreeSub"
                        @select-all="onSelectTreeSemua"
                    />
                </template>
                <template v-else>
                    <div class="sm-pindah mb-2" role="group">
                        <span class="sm-pindah-label mb-1">
                            <HcisIcon name="diagram-3" size="2xs" class="me-1 opacity-75" />
                            Kelompokkan per
                        </span>
                        <HcisSelect
                            :model-value="sumbuAktif"
                            size="sm"
                            :leading-icon="(sumbuTersedia.find((s) => s.key === sumbuAktif) || {}).ikon || 'diagram-3-fill'"
                            placeholder="Pilih sumbu kelompok…"
                            :clearable="false"
                            class="w-100"
                            @update:model-value="$emit('update:sumbu', $event)"
                        >
                            <HcisSelectOption
                                v-for="s in sumbuTersedia"
                                :key="s.key"
                                :value="s.key"
                                :label="s.label"
                            >
                                <div class="d-flex align-items-center gap-2">
                                    <HcisIcon :name="s.ikon" size="xs" />
                                    <span>{{ s.label }}</span>
                                </div>
                            </HcisSelectOption>
                        </HcisSelect>
                    </div>

                    <RailSumbu
                        :loading="loading"
                        :grup="grup"
                        :aktif="grupTerpilih"
                        :judul="sumbuMeta.label"
                        :jamak="sumbuMeta.jamak"
                        :penempatan="penempatan"
                        :total-semua="totalSemua"
                        :persen-semua="persenSemua"
                        :bar="bar"
                        :label-semua="labelSemua"
                        @pilih="onSelectGrup"
                    />
                </template>
            </div>

            <template #footer>
                <div class="sm-drawer-actions d-flex align-items-center justify-content-between w-100 gap-2">
                    <HcisButton
                        v-if="isGrupActive"
                        label="Reset Kelompok"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-counterclockwise"
                        @click="resetKelompok"
                    />
                    <HcisButton
                        label="Tutup"
                        variant="primary"
                        size="sm"
                        class="ms-auto"
                        @click="drawerRailOpen = false"
                    />
                </div>
            </template>
        </HcisDrawer>
    </div>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisSkeleton from '@/components/ui/HcisSkeleton.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';
import HcisDrawer from '@/components/ui/HcisDrawer.vue';
import RailSumbu from './RailSumbu.vue';
import PohonFolderSop from './PohonFolderSop.vue';
import BarisPaket from './BarisPaket.vue';
import BarisDokumen from './BarisDokumen.vue';
import SopTugasCard from '../ui/SopTugasCard.vue';

/**
 * SopListingDokumen — SATU-SATUNYA tempat daftar paket & dokumen digambar.
 *
 * Dipakai oleh Functional Document dan Public Document.
 */
export default {
    name: 'SopListingDokumen',
    components: {
        HcisCard,
        HcisButton,
        HcisIcon,
        HcisSkeleton,
        HcisEmptyState,
        HcisSelect,
        HcisSelectOption,
        HcisDrawer,
        RailSumbu,
        PohonFolderSop,
        BarisPaket,
        BarisDokumen,
        SopTugasCard,
    },
    props: {
        /* ── Mode Rail Dual ── */
        railMode: { type: String, default: 'sumbu' },
        tree: { type: Array, default: () => [] },
        activeTreeDept: { type: [Array, String], default: () => [] },
        activeTreeJenis: { type: [Array, String], default: () => [] },

        /* ── Rail ── */
        sumbuTersedia: { type: Array, default: () => [] },
        sumbuAktif: { type: String, default: '' },
        sumbuMeta: { type: Object, default: () => ({ label: 'Departemen', jamak: 'departemen' }) },
        grup: { type: Array, default: () => [] },
        grupTerpilih: { type: String, default: '' },
        penempatan: { type: Number, default: 0 },
        totalSemua: { type: Number, default: 0 },
        persenSemua: { type: Number, default: 0 },
        /** Rail menampilkan bar progres. Mati di rak rujukan: tak ada progres. */
        bar: { type: Boolean, default: true },
        labelSemua: { type: String, default: '' },

        /* ── Isi ── */
        items: { type: Array, default: () => [] },
        dokumen: { type: Array, default: () => [] },
        tampilan: { type: String, default: 'kartu' },
        daftarTampilan: {
            type: Array,
            default: () => [
                { key: 'kartu', label: 'Kartu', ikon: 'grid-3x3-gap-fill', judul: 'Tampilkan sebagai kartu' },
                { key: 'tabel', label: 'Tabel', ikon: 'list-ul', judul: 'Tampilkan sebagai baris' },
                { key: 'dokumen', label: 'Dokumen', ikon: 'file-earmark-text-fill', judul: 'Tampilkan per dokumen' },
            ],
        },
        loading: { type: Boolean, default: false },
        busyId: { type: [String, Number], default: null },
        /** Rak RUJUKAN (Public Document): tanpa tenggat & status kepatuhan. */
        rujukan: { type: Boolean, default: false },

        /* ── Keterangan ── */
        sub: { type: String, default: '' },
        satuan: { type: String, default: '' },
        judulSemua: { type: String, default: 'Semua' },
        ikonSemua: { type: String, default: 'grid-1x2-fill' },
        sentiment: { type: String, default: 'purple' },
        /** [{ kunci, label, nilai, kelas }] — pil saringan aktif yang bisa dicabut. */
        saringanAktif: { type: Array, default: () => [] },
        kosong: {
            type: Object,
            default: () => ({ icon: 'inboxes', title: 'Belum ada yang bisa ditampilkan', description: '' }),
        },

        /**
         * `{ halaman, halamanTotal, total }` bila daftar dipotong di server;
         * `null` bila pemanggil memuat semuanya. Lihat catatan di template.
         */
        paginasi: { type: Object, default: null },
        tampilkanViewToggle: { type: Boolean, default: true },
        tampilkanInfoJumlah: { type: Boolean, default: true },
    },
    emits: [
        'update:rail-mode',
        'update:sumbu',
        'update:grup',
        'pilih-tree-dept',
        'pilih-tree-sub',
        'pilih-tree-semua',
        'update:tampilan',
        'update:halaman',
        'hapus-saringan',
        'reset-saringan',
        'open',
    ],
    data() {
        return {
            currentRailMode: this.railMode || 'sumbu',
            isMobile: typeof window !== 'undefined' ? window.innerWidth < 900 : false,
            drawerRailOpen: false,
        };
    },
    mounted() {
        this.handleResize = () => {
            this.isMobile = window.innerWidth < 900;
            if (!this.isMobile) {
                this.drawerRailOpen = false;
            }
        };
        window.addEventListener('resize', this.handleResize, { passive: true });
    },
    beforeUnmount() {
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
    },
    watch: {
        railMode(val) {
            this.currentRailMode = val || 'sumbu';
        },
    },
    computed: {
        isGrupActive() {
            if (this.currentRailMode === 'tree') {
                return Array.isArray(this.activeTreeDept)
                    ? this.activeTreeDept.length > 0
                    : !!this.activeTreeDept;
            }
            return !!this.grupTerpilih;
        },
        labelKelompokAktif() {
            if (this.currentRailMode === 'tree') {
                if (this.activeTreeDept && (this.activeTreeDept.length > 0 || typeof this.activeTreeDept === 'string')) {
                    const deptKey = Array.isArray(this.activeTreeDept) ? this.activeTreeDept[0] : this.activeTreeDept;
                    const node = (this.tree || []).find((t) => t.id === deptKey || t.key === deptKey || t.nama === deptKey);
                    return node ? (node.nama || node.label || deptKey) : deptKey;
                }
                return 'Semua Folder';
            }
            if (this.grupTerpilih) {
                const found = (this.grup || []).find((g) => {
                    const k = g.kunci !== undefined ? g.kunci : (g.id !== undefined ? g.id : (g.nama || ''));
                    return k === this.grupTerpilih;
                });
                return found ? found.nama : this.grupTerpilih;
            }
            return this.labelSemua || (this.sumbuMeta?.label ? `Semua ${this.sumbuMeta.label}` : 'Semua');
        },
        grupAktif() {
            return this.grup.find((g) => g.nama === this.grupTerpilih) || null;
        },
        isiTampil() {
            return this.tampilan === 'dokumen' ? this.dokumen : this.items;
        },
        adaIsi() {
            return this.isiTampil.length > 0;
        },
        jumlahTampil() {
            /* Saat daftar dipotong di server, yang layak disebut adalah UKURAN
               HIMPUNAN, bukan berapa baris yang kebetulan termuat di halaman
               ini. Angka halaman sudah disebut sendiri di bilah paginasi. */
            if (this.paginasi && this.tampilan !== 'dokumen') {
                return this.paginasi.total;
            }

            return this.isiTampil.length;
        },
        satuanTampil() {
            if (this.satuan) return this.satuan;

            return this.tampilan === 'dokumen' ? 'dokumen' : 'paket';
        },
    },
    methods: {
        setRailMode(mode) {
            if (this.currentRailMode === mode) return;
            this.currentRailMode = mode;
            this.$emit('update:rail-mode', mode);
        },
        onSelectGrup(g) {
            this.$emit('update:grup', g);
            if (this.isMobile) {
                this.drawerRailOpen = false;
            }
        },
        onSelectTreeDept(dept) {
            this.$emit('pilih-tree-dept', dept);
            if (this.isMobile) {
                this.drawerRailOpen = false;
            }
        },
        onSelectTreeSub(sub) {
            this.$emit('pilih-tree-sub', sub);
            if (this.isMobile) {
                this.drawerRailOpen = false;
            }
        },
        onSelectTreeSemua() {
            this.$emit('pilih-tree-semua');
            if (this.isMobile) {
                this.drawerRailOpen = false;
            }
        },
        resetKelompok() {
            if (this.currentRailMode === 'tree') {
                this.onSelectTreeSemua();
            } else {
                this.onSelectGrup('');
            }
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

/* ══ Mode Switcher (Metrik vs Folder) ══ */
.ly-rail-sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--hcis-white);
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px dashed var(--border-soft, var(--hcis-slate-200));
}

.sm-mode-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px;
    background: var(--surface-soft, var(--hcis-slate-100));
    padding: 3px;
    border-radius: 9px;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
}

.sm-mode-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.35rem 0.5rem;
    border: 0;
    background: transparent;
    border-radius: 7px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition: all 140ms ease;
    user-select: none;
}

.sm-mode-tab:hover {
    color: var(--text-main, var(--hcis-slate-800));
}

.sm-mode-tab.active {
    background: var(--hcis-white);
    color: var(--primary-dark, var(--hcis-indigo-700));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.ly-rail-content {
    display: flex;
    flex-direction: column;
}

.tk-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.tk-tampilan {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.2rem;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
}

.tk-tampilan-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.7rem;
    border: 0;
    background: transparent;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: all 0.18s ease;
}

.tk-tampilan-btn:hover {
    color: var(--hcis-slate-700);
}

.tk-tampilan-btn.on {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
}

@media (max-width: 640px) {
    .tk-tampilan-teks {
        display: none;
    }
}

.sm-pindah {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.2rem 0.25rem 0.75rem;
    margin-bottom: 0.65rem;
    border-bottom: 1px dashed var(--border-soft, var(--hcis-slate-200));
}

.sm-pindah-label {
    display: flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-faint, var(--hcis-slate-500));
}

.mk-cocok {
    margin: 0.35rem 0 0;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    line-height: 1.4;
}

.mk-paging {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1.1rem;
    padding-top: 0.9rem;
    border-top: 1px dashed var(--hcis-slate-200);
}

.mk-paging-info {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
}

.ly {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}

.ly-rail {
    width: 275px;
    flex-shrink: 0;
    position: sticky;
    top: 1rem;
    z-index: 10;
}

.ly-kartu {
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid var(--border-soft, var(--hcis-slate-300));
    border-radius: 16px;
    padding: 0.85rem;
    max-height: calc(100vh - 5rem);
    overflow-y: auto;
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 4px 12px -2px rgba(15, 23, 42, 0.06);
}

.ly-main-wrapper {
    flex: 1;
    min-width: 0;
}

.ly-main-card {
    border: 1px solid var(--hcis-slate-300) !important;
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 4px 6px -1px rgba(15, 23, 42, 0.05),
        0 12px 28px -4px rgba(15, 23, 42, 0.08) !important;
}

.ly-main-inner {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

/* ══ Bilah Saringan Aktif Modern ══ */
.tk-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--hcis-white);
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-left: 3.5px solid var(--primary, var(--hcis-indigo-500));
    border-radius: 12px;
    padding: 0.45rem 0.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 160ms ease;
}

.tk-bar-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
}

.tk-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted, var(--hcis-slate-500));
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

.tk-indicator-icon {
    color: var(--primary, var(--hcis-indigo-500));
}

.tk-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.tk-chip {
    display: inline-flex;
    align-items: center;
    background: var(--surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 7px;
    padding: 2px 4px 2px 7px;
    font-size: 0.73rem;
    transition: all 140ms ease;
    max-width: 100%;
}

.tk-chip:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-white);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.tk-chip-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
    color: var(--text-muted, var(--hcis-slate-500));
    margin-right: 0.35rem;
    flex-shrink: 0;
}

.tk-chip-icon {
    color: var(--primary, var(--hcis-indigo-500));
}

.tk-chip-val {
    font-weight: 700;
    color: var(--primary-dark, var(--hcis-indigo-800));
    margin-right: 0.35rem;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tk-chip-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border: none;
    background: transparent;
    border-radius: 4px;
    color: var(--text-faint, var(--hcis-slate-400));
    cursor: pointer;
    padding: 0;
    transition: all 120ms ease;
    flex-shrink: 0;
}

.tk-chip-del:hover {
    background: var(--hcis-red-100);
    color: var(--hcis-red-500);
}

.tk-bar-right {
    flex-shrink: 0;
}

.tk-clear-all {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid transparent;
    background: transparent;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-red-600);
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 140ms ease;
}

.tk-clear-all:hover {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-700);
}

.tk-info {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
}

.tk-info strong {
    font-size: 0.92rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
}

/* Skeleton Rows */
.tk-skel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tk-skel-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

/* Pane & Header */
.mk-pane {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mk-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 14px;
    padding: 0.75rem 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.mk-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-600);
    border: 1px solid #ddd6fe;
    flex-shrink: 0;
}

.mk-judul {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    margin: 0;
    line-height: 1.2;
}

.mk-sub {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    margin: 0.15rem 0 0;
}

.mk-meter {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-left: auto;
    flex-shrink: 0;
}

.mk-bar {
    width: 90px;
    height: 7px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    overflow: hidden;
    display: block;
}

.mk-bar span {
    height: 100%;
    border-radius: 999px;
    background: var(--hcis-violet-600);
    display: block;
}

.mk-pct {
    font-size: 0.8rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
}

/* Grid & List of Tasks */
.pk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
    align-items: stretch;
}

.sop-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* ══ Mobile Rail Grouping Trigger Bar ══ */
.sm-rail-trigger-wrap {
    width: 100%;
    margin-bottom: 0.25rem;
}

.sm-rail-trigger-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.6rem 0.85rem;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    cursor: pointer;
    transition: all 0.18s ease;
    text-align: left;
    color: inherit;
    gap: 0.5rem;
}

.sm-rail-trigger-btn:hover,
.sm-rail-trigger-btn:active {
    background: var(--hcis-white);
    border-color: var(--hcis-violet-400);
    box-shadow: 0 3px 8px rgba(124, 58, 237, 0.12);
}

.sm-rail-trigger-btn.has-active-group {
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.95) 0%, rgba(255, 255, 255, 0.98) 100%);
    border-color: var(--hcis-violet-400);
    box-shadow: 0 0 0 1px rgba(124, 58, 237, 0.15), 0 2px 6px rgba(124, 58, 237, 0.08);
}

.sm-rail-trigger-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
}

.sm-rail-trigger-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-600);
    border: 1px solid #ddd6fe;
    flex-shrink: 0;
}

.sm-rail-trigger-texts {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sm-rail-trigger-type {
    font-size: 0.62rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
    line-height: 1.1;
}

.sm-rail-trigger-val {
    font-size: 0.88rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.25;
}

.sm-rail-trigger-right {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.sm-rail-trigger-count {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200);
}

.sm-rail-trigger-caret {
    color: var(--hcis-slate-400);
}

.sm-drawer-rail-body {
    padding: 0.25rem 0.25rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* ══ Responsif & Mobile Viewports ══ */
@media (max-width: 899.98px) {
    .ly {
        flex-direction: column;
        gap: 0;
    }
    .ly-rail {
        display: none !important;
    }
    .ly-main-wrapper {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .ly-main-inner {
        gap: 0.75rem;
    }
    .ly-main-card :deep(.hcis-card__body) {
        padding: 0.75rem !important;
    }
    .pk-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }
    .mk-head {
        padding: 0.6rem 0.75rem;
        border-radius: 10px;
    }
    .mk-judul {
        font-size: 0.95rem;
    }
    .mk-meter {
        display: none;
    }
    .tk-bar {
        padding: 0.4rem 0.65rem;
    }
    .tk-tampilan-btn {
        min-width: 36px;
        min-height: 36px;
        padding: 0;
        justify-content: center;
    }
    .mk-paging {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.85rem;
        padding-top: 0.75rem;
    }
    .mk-paging-info {
        order: -1;
        width: 100%;
        text-align: center;
        font-size: 0.72rem;
        margin-bottom: 0.25rem;
    }
    .mk-paging :deep(.hcis-btn) {
        flex: 1;
        justify-content: center;
        min-height: 38px;
    }
    .sm-drawer-actions :deep(.hcis-btn) {
        flex: 1;
        justify-content: center;
        min-height: 40px;
    }

    .ly-main-inner :deep(.hcis-empty-state) {
        padding: 1.85rem 0.85rem;
        border-radius: 14px;
    }

    .ly-main-inner :deep(.hcis-empty-state__title) {
        font-size: 1rem;
    }

    .ly-main-inner :deep(.hcis-empty-state__description) {
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .ly-main-inner :deep(.hcis-empty-state__actions) {
        flex-direction: column;
        width: 100%;
        max-width: 17rem;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .ly-main-inner :deep(.hcis-empty-state__actions .hcis-btn) {
        width: 100% !important;
        min-height: 40px !important;
        justify-content: center !important;
        font-size: 0.82rem !important;
        padding: 0 0.75rem !important;
        box-sizing: border-box !important;
    }

    .ly-main-inner :deep(.hcis-empty-state__actions .hcis-btn__text) {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
        font-weight: 700 !important;
    }
}

/* ══ Empty State Polish ══ */
.ly-main-inner :deep(.hcis-empty-state) {
    padding: 2.75rem 1.25rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1.5px dashed var(--hcis-slate-300);
    border-radius: 16px;
    box-sizing: border-box;
}

.ly-main-inner :deep(.hcis-empty-state__icon-box) {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(79, 70, 229, 0.22) 100%);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(199, 210, 254, 0.8);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.12);
}

.ly-main-inner :deep(.hcis-empty-state__title) {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.015em;
    margin-top: 0.25rem;
}

.ly-main-inner :deep(.hcis-empty-state__description) {
    font-size: 0.84rem;
    color: var(--hcis-slate-500);
    max-width: 28rem;
    line-height: 1.55;
}

.ly-main-inner :deep(.hcis-empty-state__actions) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    margin-top: 1.25rem;
    width: 100%;
}
</style>
