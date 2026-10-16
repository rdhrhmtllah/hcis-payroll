<template>
    <Modal
        :show="isVisible"
        title="Pilih Konten Modul"
        size="large"
        :closeOnEscape="true"
        :closeOnOverlay="true"
        :showFooter="false"
        :skeleton="false"
        @close="handleClose"
        @update:show="$emit('update:visible', $event)"
    >
        <template #body>
            <div class="kl-app-container w-100 p-0" style="min-height: 50vh">
                <div class="kl-tab-panel">
                    <ModernFilter
                        :config="filterConfig"
                        :activeFilters="tableFilters"
                        :breadcrumbItems="breadcrumbItems"
                        @update:filters="handleFiltersUpdate"
                        @breadcrumb-click="handleBreadcrumbNavigate"
                    />

                    <!-- Back Button when inside a folder -->
                    <div class="d-flex align-items-center mb-3 px-2 pt-1" v-if="currentCategory">
                        <button
                            class="btn btn-sm btn-light border rounded-circle shadow-sm me-3"
                            @click="goToRoot"
                            title="Kembali ke Kategori"
                        >
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <h5 class="m-0 fw-bold">{{ currentCategory.name }}</h5>
                    </div>

                    <div v-if="isLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem"></div>
                        <p class="mt-3 text-muted fw-medium">Memuat data...</p>
                    </div>

                    <template v-else>
                        <!--
                            Aturan formatnya dinyatakan di depan, dan yang tak
                            memenuhi disebut jumlahnya. Menyembunyikannya tanpa
                            keterangan membuat admin mengira kontennya hilang.
                        -->
                        <div v-if="aturan" class="aturan-bar" :class="{ 'ada-tersembunyi': jumlahTersembunyi > 0 }">
                            <i class="bi bi-funnel-fill"></i>
                            <span>
                                Item <strong>{{ aturan.nama || tipeItem }}</strong> hanya menerima {{ aturan.label }}.
                                <template v-if="jumlahTersembunyi > 0">
                                    {{ jumlahTersembunyi }} konten lain di kategori ini disembunyikan.
                                </template>
                            </span>
                        </div>

                        <div class="stitch-card bg-white rounded-4 shadow-sm border p-4">
                            <ContentContainer
                                :categories="filteredCategories"
                                :files="filteredContents"
                                :viewMode="viewMode"
                                :enableSelection="false"
                                @item-click="handleItemClick"
                            />
                            <div v-if="currentCategory && !filteredContents.length" class="kosong-hint">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">
                                    Tidak ada konten yang cocok di kategori ini.
                                    <template v-if="aturan">Unggah {{ aturan.label }} lebih dulu di Library Konten.</template>
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '../../../../components/ui/modal.vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import ContentContainer from '../../../../Content/ContentManagement/components/ui/ContentContainer.vue';

// Library konten bersama — base path dari URL (SOP builder di /sop/*, LMS builder di /lms/*).
const kontenBasePath = () =>
    (typeof window !== 'undefined' && window.location.pathname.startsWith('/sop'))
        ? '/sop/admin/konten'
        : '/lms/konten-kategori';

export default {
    name: 'KontenPickerModal',
    components: {
        Modal,
        ModernFilter,
        ContentContainer,
    },
    props: {
        visible: { type: Boolean, default: false },
        /*
         * Tipe item yang sedang diisi: DOKUMEN | FORM | VIDEO.
         * Kosong = tanpa penyaringan (dipakai builder training LMS).
         *
         * Aturannya sama persis dengan yang ditegakkan server di
         * SopAdminBuilderController::validateKontenForTipe. Penyaringan di sini
         * BUKAN pengganti pemeriksaan itu — ia hanya mencegah admin memilih
         * sesuatu yang sudah pasti ditolak saat disimpan.
         */
        tipeItem: { type: String, default: '' },
    },
    emits: ['update:visible', 'selected'],
    data() {
        return {
            isVisible: this.visible,
            isLoading: false,
            categories: [],
            files: [],
            currentCategory: null,
            searchQuery: '',
            viewMode: 'grid',
            tableFilters: { viewMode: 'grid', search: '' },
        };
    },
    computed: {
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: this.currentCategory ? 'Cari file konten...' : 'Cari folder kategori...',
                showResultsCount: false,
                showActiveFilters: true,
                showActions: false,
                showReset: true,
                showClearAll: true,
                filters: [
                    {
                        key: 'viewMode',
                        label: 'Tampilan',
                        type: 'viewToggle',
                        col: 4,
                        options: [
                            { value: 'grid', icon: 'bi bi-grid-fill' },
                            { value: 'list', icon: 'bi bi-list-ul' },
                        ],
                    },
                ],
            };
        },
        breadcrumbItems() {
            let items = [{ label: 'Root (Semua Kategori)', id: 'root', isRoot: true }];
            if (!this.currentCategory) return items;
            items.push({
                label: this.currentCategory.name,
                id: this.currentCategory.id,
            });
            return items;
        },
        filteredCategories() {
            if (this.currentCategory) return [];
            let result = this.categories;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter((c) => c.name.toLowerCase().includes(query));
            }
            return result;
        },
        aturan() {
            return (
                {
                    DOKUMEN: { label: 'berkas PDF', uji: (f) => this.extDari(f) === 'pdf' },
                    VIDEO: { label: 'video', uji: (f) => this.tipeDari(f) === 'video' },
                    // Berkas utama FORM adalah contoh pengisian — wajib PDF sejak
                    // 10 Okt 2026 supaya bisa ditahan "hanya lihat" di pembaca
                    // aman. Server menagihnya di gerbang terbit (pastikanIsiSah).
                    FORM: { label: 'berkas PDF (contoh pengisian)', uji: (f) => this.extDari(f) === 'pdf' },
                    // Form kosong yang diunduh karyawan: Word/Excel justru lazim.
                    LAMPIRAN_FORM: { nama: 'Form kosong', label: 'semua format library', uji: () => true },
                }[String(this.tipeItem || '').toUpperCase()] || null
            );
        },
        /** Cocok formatnya DAN sudah lolos verifikasi berkas. */
        kandidat() {
            if (!this.currentCategory) return [];

            return this.files.filter((f) => {
                if (!this.terverifikasi(f)) return false;

                return this.aturan ? this.aturan.uji(f) : true;
            });
        },
        jumlahTersembunyi() {
            return this.currentCategory ? this.files.length - this.kandidat.length : 0;
        },
        filteredContents() {
            let result = this.kandidat;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter((f) => f.name.toLowerCase().includes(query));
            }
            return result;
        },
    },
    watch: {
        visible(newVal) {
            this.isVisible = newVal;
            if (newVal) {
                this.goToRoot();
                if (this.categories.length === 0) {
                    this.fetchKategori();
                }
            }
        },
        isVisible(newVal) {
            this.$emit('update:visible', newVal);
        },
    },
    methods: {
        /*
         * Ekstensi konten. `ext` datang dari server sejak Library mendukung banyak
         * format; baris lama belum punya, jadi mime-nya dipakai sebagai cadangan —
         * bukan nama berkas, yang bisa saja berbohong.
         */
        extDari(file) {
            const ext = String(file?.ext || '').toLowerCase();
            if (ext) return ext;

            const mime = String(file?.metadata?.typeFile || '').toLowerCase();
            return mime.includes('pdf') ? 'pdf' : '';
        },
        tipeDari(file) {
            return String(file?.fileType || file?.type || '').toLowerCase();
        },
        /*
         * Video tak punya baris berkas — medianya di Bunny — sehingga tak pernah
         * punya status verifikasi. Menuntut status untuknya akan menyembunyikan
         * seluruh konten video.
         */
        terverifikasi(file) {
            if (this.tipeDari(file) === 'video') return true;

            const s = String(file?.statusVerifikasi || '').toUpperCase();
            return s === '' || s === 'SIAP' || s === 'LAMA';
        },
        handleClose() {
            this.isVisible = false;
        },
        handleFiltersUpdate(updated) {
            this.tableFilters = updated;
            this.searchQuery = updated.search || '';
            if (updated.viewMode) {
                this.viewMode = updated.viewMode;
            }
        },
        handleBreadcrumbNavigate(item) {
            if (item.isRoot) {
                this.goToRoot();
            } else {
                this.currentCategory = this.categories.find((c) => c.id === item.id);
                this.searchQuery = '';
                this.tableFilters.search = '';
            }
        },
        goToRoot() {
            this.currentCategory = null;
            this.files = [];
            this.searchQuery = '';
            this.tableFilters.search = '';
        },
        async fetchKategori() {
            this.isLoading = true;
            try {
                const response = await axios.get(kontenBasePath() + '/kategori');
                if (response.data.success) {
                    this.categories = response.data.result.data || [];
                }
            } catch (error) {
                console.error('Error fetching kategori:', error);
                ElMessage.error('Gagal memuat data kategori');
            } finally {
                this.isLoading = false;
            }
        },
        async fetchKontenByKategori(categoryId) {
            this.isLoading = true;
            try {
                const response = await axios.get(`${kontenBasePath()}/konten/${categoryId}`);
                if (response.data.success) {
                    this.files = response.data.result.data || [];
                }
            } catch (error) {
                console.error('Error fetching konten:', error);
                ElMessage.error('Gagal memuat data konten');
            } finally {
                this.isLoading = false;
            }
        },
        handleItemClick(item) {
            if (item.type === 'category') {
                this.currentCategory = item;
                this.searchQuery = '';
                this.tableFilters.search = '';
                this.fetchKontenByKategori(item.id);
            } else {
                // It's a file
                this.confirmSelection(item);
            }
        },
        confirmSelection(file) {
            // Penjaga terakhir di sisi klien: daftar sudah disaring, tapi item
            // bisa saja sampai ke sini lewat jalur lain.
            if (this.aturan && !this.aturan.uji(file)) {
                ElMessage.warning(`Item ${this.aturan.nama || this.tipeItem} hanya menerima ${this.aturan.label}.`);

                return;
            }

            if (confirm(`Pilih konten "${file.name}" untuk materi ini?`)) {
                this.$emit('selected', {
                    id: file.id,
                    judul: file.name,
                    tipe: file.type || '',
                    ext: file.ext || '',
                });
                this.handleClose();
            }
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table;
}

.aturan-bar {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.65rem 0.9rem;
    border-radius: 12px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
    font-size: 0.82rem;
    line-height: 1.5;
}
.aturan-bar.ada-tersembunyi {
    border-color: var(--hcis-amber-200);
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
}
.aturan-bar .bi {
    margin-top: 2px;
}

.kosong-hint {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    padding: 2.2rem 1rem;
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
    text-align: center;
}
.kosong-hint .bi {
    font-size: 1.8rem;
    opacity: 0.6;
}

.btn-light {
    background-color: var(--hcis-white) !important;
}

.kl-app-container {
    background: transparent;
    --color-border: var(--hcis-slate-200);
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --color-primary: var(--hcis-blue-500);
}
.kl-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Force file card to always be white, to prevent type-* classes from making the entire card colored */
:deep(.file-card) {
    background-color: var(--hcis-white) !important;
}
/* Ensure file-thumbnail correctly isolates its background */
:deep(.file-thumbnail) {
    border-top-left-radius: 11px;
    border-top-right-radius: 11px;
}
:deep(.file-footer) {
    border-bottom-left-radius: 11px;
    border-bottom-right-radius: 11px;
    border-top: 1px solid var(--color-border);
}
</style>
