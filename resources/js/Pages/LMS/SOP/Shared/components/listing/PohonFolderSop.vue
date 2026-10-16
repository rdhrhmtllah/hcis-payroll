<template>
    <div class="sop-tree-explorer">
        <!-- Toolbar Kontrol Pohon -->
        <div class="sop-tree-toolbar">
            <div class="sop-tree-search-wrap">
                <HcisIcon name="search" size="2xs" class="sop-tree-search-icon" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari folder…"
                    class="sop-tree-search-input"
                    aria-label="Cari folder"
                />
                <button
                    v-if="search"
                    type="button"
                    class="sop-tree-search-clear"
                    aria-label="Hapus pencarian"
                    @click="search = ''"
                >
                    <HcisIcon name="x" size="2xs" />
                </button>
            </div>
            <div class="sop-tree-actions">
                <button
                    type="button"
                    class="sop-tree-act-btn"
                    title="Buka semua folder"
                    aria-label="Buka semua folder"
                    @click="expandAll"
                >
                    <HcisIcon name="arrows-expand" size="2xs" />
                </button>
                <button
                    type="button"
                    class="sop-tree-act-btn"
                    title="Tutup semua folder"
                    aria-label="Tutup semua folder"
                    @click="collapseAll"
                >
                    <HcisIcon name="arrows-collapse" size="2xs" />
                </button>
            </div>
        </div>

        <!-- Node Root: Semua Paket -->
        <div class="sop-tree-root mb-1">
            <button
                type="button"
                class="sop-tree-node-btn is-root"
                :class="{ 'is-active': isSemuaAktif }"
                @click="$emit('pilih-semua'); $emit('select-all');"
            >
                <span class="sop-tree-icon root-icon">
                    <HcisIcon name="collection-fill" size="xs" />
                </span>
                <span class="sop-tree-label">Semua Paket</span>
                <span class="sop-tree-count">{{ totalSemua }}</span>
            </button>
        </div>

        <!-- Daftar Cabang Pohon Departemen (Loading Skeleton) -->
        <div v-if="loading" class="sop-tree-branches py-2 px-1">
            <div v-for="n in 5" :key="n" class="d-flex align-items-center gap-2 py-1.5 px-2 mb-1">
                <HcisSkeleton width="16px" height="16px" rounded="xs" />
                <HcisSkeleton width="18px" height="18px" rounded="xs" />
                <HcisSkeleton :width="`${55 + (n * 8)}%`" height="0.85rem" rounded="xs" />
                <HcisSkeleton width="22px" height="16px" rounded="xs" class="ms-auto" />
            </div>
        </div>

        <!-- Daftar Cabang Pohon Departemen -->
        <div v-else-if="filteredTree.length" class="sop-tree-branches">
            <div
                v-for="dept in filteredTree"
                :key="dept.id"
                class="sop-tree-branch"
                :class="{ 'is-expanded': isExpanded(dept.id) }"
            >
                <!-- Node Tingkat 1: Departemen -->
                <div
                    class="sop-tree-row"
                    :class="{
                        'is-active': isDeptAktif(dept.nama) && !isSubAktif,
                        'is-parent-active': isDeptAktif(dept.nama),
                    }"
                >
                    <!-- Chevron Toggle -->
                    <button
                        v-if="dept.children && dept.children.length"
                        type="button"
                        class="sop-tree-toggle"
                        :aria-expanded="isExpanded(dept.id)"
                        :aria-label="isExpanded(dept.id) ? 'Tutup folder' : 'Buka folder'"
                        @click.stop="toggleNode(dept.id)"
                    >
                        <HcisIcon
                            :name="isExpanded(dept.id) ? 'chevron-down' : 'chevron-right'"
                            size="2xs"
                        />
                    </button>
                    <span v-else class="sop-tree-toggle-spacer" />

                    <!-- Node Button -->
                    <button
                        type="button"
                        class="sop-tree-node-btn"
                        :title="dept.nama"
                        @click="$emit('pilih-dept', dept.nama); $emit('select-dept', dept.nama);"
                    >
                        <span class="sop-tree-icon dept-icon">
                            <HcisIcon
                                :name="isExpanded(dept.id) ? 'folder2-open' : 'folder2'"
                                size="xs"
                            />
                        </span>
                        <span class="sop-tree-label">{{ dept.nama }}</span>
                        <span class="sop-tree-count">{{ dept.total }}</span>
                    </button>
                </div>

                <!-- Node Tingkat 2: Jenis Dokumen (Children) -->
                <div
                    v-if="dept.children && dept.children.length && isExpanded(dept.id)"
                    class="sop-tree-children"
                >
                    <div
                        v-for="sub in dept.children"
                        :key="sub.id"
                        class="sop-tree-row is-child"
                        :class="{ 'is-active': isDeptAktif(dept.nama) && isJenisAktif(sub.nama) }"
                    >
                        <button
                            type="button"
                            class="sop-tree-node-btn is-leaf"
                            :title="`${dept.nama} · ${sub.nama}`"
                            @click="$emit('pilih-sub', { dept: dept.nama, jenis: sub.nama }); $emit('select-sub', { dept: dept.nama, jenis: sub.nama });"
                        >
                            <span class="sop-tree-icon child-icon">
                                <HcisIcon name="file-earmark-text" size="2xs" />
                            </span>
                            <span class="sop-tree-label">{{ sub.nama }}</span>
                            <span class="sop-tree-count">{{ sub.total }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty Search State -->
        <div v-else class="sop-tree-empty py-3 px-2 text-center text-muted">
            <HcisIcon name="search" size="sm" class="mb-1 opacity-50" />
            <p class="mb-0 small">Tidak ada folder yang cocok</p>
        </div>
    </div>
</template>

<script>
import { HcisIcon, HcisSkeleton } from '@/components/ui';

export default {
    name: 'PohonFolderSop',
    components: {
        HcisIcon,
        HcisSkeleton,
    },
    props: {
        loading: {
            type: Boolean,
            default: false,
        },
        tree: {
            type: Array,
            default: () => [],
        },
        activeDept: {
            type: [Array, String],
            default: () => [],
        },
        activeJenis: {
            type: [Array, String],
            default: () => [],
        },
        totalSemua: {
            type: Number,
            default: 0,
        },
    },
    emits: ['pilih-dept', 'pilih-sub', 'pilih-semua'],
    data() {
        return {
            search: '',
            expandedMap: {},
        };
    },
    computed: {
        deptList() {
            if (Array.isArray(this.activeDept)) return this.activeDept;
            return this.activeDept ? [this.activeDept] : [];
        },
        jenisList() {
            if (Array.isArray(this.activeJenis)) return this.activeJenis;
            return this.activeJenis ? [this.activeJenis] : [];
        },
        isSemuaAktif() {
            return !this.deptList.length && !this.jenisList.length;
        },
        isSubAktif() {
            return this.jenisList.length > 0;
        },
        filteredTree() {
            if (!this.search.trim()) return this.tree;
            const q = this.search.toLowerCase().trim();

            return this.tree
                .map((dept) => {
                    const matchDept = (dept.nama || '').toLowerCase().includes(q);
                    const matchingChildren = (dept.children || []).filter((sub) =>
                        (sub.nama || '').toLowerCase().includes(q)
                    );

                    if (matchDept) {
                        return dept;
                    }
                    if (matchingChildren.length > 0) {
                        return {
                            ...dept,
                            children: matchingChildren,
                        };
                    }
                    return null;
                })
                .filter(Boolean);
        },
    },
    watch: {
        search(val) {
            if (val.trim()) {
                this.expandAll();
            }
        },
        tree: {
            immediate: true,
            handler(newTree) {
                if (newTree && newTree.length) {
                    newTree.forEach((d) => {
                        if (this.isDeptAktif(d.nama) && this.expandedMap[d.id] === undefined) {
                            this.expandedMap[d.id] = true;
                        }
                    });
                }
            },
        },
    },
    methods: {
        isExpanded(nodeId) {
            return this.expandedMap[nodeId] !== false;
        },
        toggleNode(nodeId) {
            const cur = this.isExpanded(nodeId);
            this.expandedMap = {
                ...this.expandedMap,
                [nodeId]: !cur,
            };
        },
        expandAll() {
            const map = {};
            (this.tree || []).forEach((d) => {
                map[d.id] = true;
            });
            this.expandedMap = map;
        },
        collapseAll() {
            const map = {};
            (this.tree || []).forEach((d) => {
                map[d.id] = false;
            });
            this.expandedMap = map;
        },
        isDeptAktif(deptNama) {
            return this.deptList.includes(deptNama);
        },
        isSubAktifExact(jenisNama) {
            return this.jenisList.includes(jenisNama);
        },
        isJenisAktif(jenisNama) {
            return this.jenisList.includes(jenisNama);
        },
        handleDeptClick(dept) {
            this.$emit('pilih-dept', dept.nama);
            this.$emit('select-dept', dept.nama);
        },
        handleSubClick(dept, sub) {
            const payload = {
                dept: dept.nama,
                jenis: sub.nama,
            };
            this.$emit('pilih-sub', payload);
            this.$emit('select-sub', payload);
        },
    },
};
</script>

<style scoped>
.sop-tree-explorer {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.8125rem;
}

/* Toolbar */
.sop-tree-toolbar {
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--hcis-white);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0 0.5rem;
    border-bottom: 1px dashed var(--border-soft, var(--hcis-slate-200));
    margin-bottom: 0.35rem;
}

.sop-tree-search-wrap {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
}

.sop-tree-search-icon {
    position: absolute;
    left: 0.55rem;
    color: var(--text-faint, var(--hcis-slate-400));
    pointer-events: none;
}

.sop-tree-search-input {
    width: 100%;
    height: 30px;
    padding: 0.2rem 1.4rem 0.2rem 1.65rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 8px;
    background: var(--surface-muted, var(--hcis-slate-50));
    font-size: 0.73rem;
    font-weight: 500;
    color: var(--text, var(--hcis-slate-800));
    transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
}

.sop-tree-search-input:focus {
    outline: none;
    background: var(--hcis-white);
    border-color: var(--primary, var(--hcis-indigo-500));
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.sop-tree-search-input::placeholder {
    color: var(--text-faint, var(--hcis-slate-400));
}

.sop-tree-search-clear {
    position: absolute;
    right: 0.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border: none;
    background: var(--hcis-slate-200);
    border-radius: 50%;
    color: var(--hcis-slate-500);
    cursor: pointer;
    padding: 0;
    transition: all 120ms ease;
}

.sop-tree-search-clear:hover {
    background: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
}

.sop-tree-actions {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}

.sop-tree-act-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 8px;
    background: var(--hcis-white);
    color: var(--text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition: all 140ms ease;
}

.sop-tree-act-btn:hover {
    background: var(--surface-soft, var(--hcis-slate-100));
    border-color: var(--border, var(--hcis-slate-300));
    color: var(--text, var(--hcis-slate-800));
}

/* Node Rows & Buttons */
.sop-tree-row {
    display: flex;
    align-items: center;
    border-radius: 8px;
    transition: all 140ms ease;
    margin-bottom: 2px;
    border: 1px solid transparent;
}

.sop-tree-row:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-100);
}

.sop-tree-row.is-active {
    background: var(--hcis-indigo-50);
    border-color: rgba(99, 102, 241, 0.25);
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.06);
}

.sop-tree-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 26px;
    border: none;
    background: transparent;
    color: var(--text-faint, var(--hcis-slate-400));
    cursor: pointer;
    padding: 0;
    transition: color 120ms ease;
}

.sop-tree-toggle:hover {
    color: var(--primary-dark, var(--hcis-indigo-700));
}

.sop-tree-toggle-spacer {
    width: 22px;
    flex-shrink: 0;
}

.sop-tree-node-btn {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.5rem 0.35rem 0.2rem;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-size: 0.76rem;
    font-weight: 500;
    color: var(--text, var(--hcis-slate-700));
    border-radius: 6px;
    transition: all 120ms ease;
}

.sop-tree-node-btn.is-root {
    padding: 0.42rem 0.65rem;
    background: var(--hcis-white);
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.sop-tree-node-btn.is-root:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}

.sop-tree-node-btn.is-root.is-active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-300);
    color: var(--primary-dark, var(--hcis-indigo-700));
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.12);
}

.sop-tree-row.is-active .sop-tree-node-btn {
    font-weight: 700;
    color: var(--primary-dark, var(--hcis-indigo-700));
}

.sop-tree-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.root-icon {
    color: var(--primary, var(--hcis-indigo-500));
}

.dept-icon {
    color: var(--hcis-amber-600); /* Folder warm amber */
}

.sop-tree-row.is-active .dept-icon {
    color: var(--primary, var(--hcis-indigo-500));
}

.sub-icon {
    color: #8b5cf6; /* Document violet */
}

.sop-tree-row.is-active .sub-icon {
    color: var(--primary-dark, var(--hcis-indigo-700));
}

.sop-tree-label {
    flex: 1;
}

.sop-tree-count {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.12rem 0.42rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
    font-variant-numeric: tabular-nums;
    transition: all 120ms ease;
}

.sop-tree-row.is-active .sop-tree-count {
    background: var(--hcis-indigo-200);
    color: var(--hcis-indigo-900);
    border-color: var(--hcis-indigo-300);
}

.sub-count {
    font-size: 0.65rem;
    background: transparent;
    border-color: transparent;
    color: var(--text-faint, var(--hcis-slate-400));
}

.sop-tree-row.is-active .sub-count {
    background: var(--hcis-indigo-200);
    color: var(--hcis-indigo-900);
    border-color: var(--hcis-indigo-300);
}

/* Children Branches & Indentation */
.sop-tree-children {
    position: relative;
    margin-left: 0.75rem;
    padding-left: 0.75rem;
    border-left: 1px dashed var(--hcis-slate-300);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sop-tree-row.is-child {
    position: relative;
}

.sop-tree-guide-branch {
    position: absolute;
    left: -0.75rem;
    top: 50%;
    width: 0.55rem;
    height: 1px;
    border-bottom: 1px dashed var(--hcis-slate-300);
}
</style>
