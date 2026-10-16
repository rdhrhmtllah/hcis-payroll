<template>
    <div class="container-fluid px-0 sop-scope">
        <!-- ===================== MODE: LIST ===================== -->
        <template v-if="mode === 'list'">
            <!-- ══ 1. EXECUTIVE HEADER (HCIS SIGNATURE LUMINOUS HEADER) ══ -->
            <HcisHeader
                title="Document Builder"
                kicker="Tata Kelola · Arsitektur & Penyusunan Paket"
                description="Kelola paket dokumen operasional: susun section, integrasikan master dokumen terkendali, dan tinjau pengalaman alur baca karyawan."
                icon="diagram-3-fill"
                sentiment="brand"
                size="md"
                animated
                :loading="loading.list"
                class="mb-4"
            >
                <template #badge>
                    <HcisBadge
                        v-if="!loading.list"
                        :label="`${pagination.total_data || 0} Paket Dokumen`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                        radar
                        dot
                    />
                </template>

                <template #actions>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="arrow-clockwise"
                            label="Segarkan"
                            :loading="loading.list"
                            title="Segarkan daftar paket dokumen"
                            aria-label="Segarkan daftar paket dokumen"
                            @click="fetchPaket(pagination.current_page)"
                        />

                        <HcisButton
                            variant="secondary"
                            size="md"
                            leading-icon="collection-play-fill"
                            label="Library Konten"
                            title="Buka Pustaka & Master Konten"
                            aria-label="Buka Pustaka & Master Konten"
                            @click="bukaLibraryKonten"
                        />

                        <HcisButton
                            variant="primary"
                            size="md"
                            leading-icon="plus-lg"
                            label="Buat Paket Dokumen"
                            title="Rakit Paket Dokumen Baru"
                            aria-label="Rakit Paket Dokumen Baru"
                            @click="startNewPaket"
                        />
                    </div>
                </template>
            </HcisHeader>

            <div class="mb-5">
                <!-- ══ EXECUTIVE KPI SUMMARY METRICS ══ -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-icon brand">
                                <i class="bi bi-diagram-3-fill"></i>
                            </div>
                            <div class="kpi-stat-content">
                                <div class="kpi-stat-val">{{ statusCountsData.ALL || pagination.total_data || 0 }}</div>
                                <div class="kpi-stat-lbl">Total Paket Dokumen</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-icon ok">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div class="kpi-stat-content">
                                <div class="kpi-stat-val">{{ statusCountsData.PUBLISHED || 0 }}</div>
                                <div class="kpi-stat-lbl">Paket Terbit & Aktif</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-icon idle">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <div class="kpi-stat-content">
                                <div class="kpi-stat-val">{{ statusCountsData.DRAFT || 0 }}</div>
                                <div class="kpi-stat-lbl">Draft / Dalam Penyusunan</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-icon dok">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </div>
                            <div class="kpi-stat-content">
                                <div class="kpi-stat-val">{{ statusCountsData.TOTAL_DOKUMEN || 0 }}</div>
                                <div class="kpi-stat-lbl">Master Dokumen Terhubung</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ 2. COCKPIT FILTER & SEARCH BAR (HcisFilterBar) ══ -->
                <div class="mb-4">
                    <HcisFilterBar
                        :search="listFilters.search"
                        search-placeholder="Cari nama paket dokumen, kode, atau kata kunci…"
                        :quick-chips="builderQuickChips"
                        :active-chip-value="statusFilter"
                        :show-view-toggle="true"
                        :view-mode="viewMode"
                        :filters="builderFiltersConfig"
                        :filter-values="listFilters"
                        :results-count="pagination.total_data"
                        variant="glass"
                        sentiment="purple"
                        @update:search="listFilters.search = $event"
                        @update:view-mode="onViewModeToggle"
                        @chip-click="onStatusChipClick"
                        @filter-change="onFilterFieldChange"
                        @reset="onResetFilters"
                        @search="onFilterSearch"
                    />
                </div>

                <!-- Layout Dua Kolom (Sidebar Rail Pengelompokan + Panel Utama) -->
                <div class="ly">
                    <!--
                        Rail pengelompokan.

                        Markup rail dulu ditulis ulang di sini, lengkap dengan
                        salinan CSS .rl-* milik RailSumbu — dua berkas yang
                        menggambar benda yang sama dan menyimpang satu per satu.
                        Sekarang komponennya yang dipakai, dan yang tersisa di
                        halaman ini hanya pemindah sumbunya.
                    -->
                    <aside v-if="loading.list || railGrup.length || treeData.length" class="ly-rail">
                        <div class="stitch-card ly-kartu">
                            <!-- Sticky Switcher Mode Dual di bagian atas panel -->
                            <div class="ly-rail-sticky-top">
                                <div class="sm-mode-tabs">
                                    <button
                                        type="button"
                                        class="sm-mode-tab"
                                        :class="{ on: railMode === 'sumbu' }"
                                        title="Mode Metrik"
                                        @click="setRailMode('sumbu')"
                                    >
                                        <HcisIcon name="sliders2" size="2xs" />
                                        <span>Metrik</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="sm-mode-tab"
                                        :class="{ on: railMode === 'tree' }"
                                        title="Mode Folder"
                                        @click="setRailMode('tree')"
                                    >
                                        <HcisIcon name="folder2" size="2xs" />
                                        <span>Folder</span>
                                    </button>
                                </div>
                            </div>

                            <div class="ly-rail-content">
                                <!-- Mode 1: Sumbu Metrik -->
                                <div v-show="railMode === 'sumbu'">
                                    <div class="sm-pindah" role="group" aria-label="Kelompokkan paket berdasarkan">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="sm-pindah-label">
                                                <HcisIcon name="diagram-3" size="2xs" class="me-1 opacity-75" />
                                                Kelompokkan per
                                            </span>
                                        </div>
                                        <HcisSelect
                                            :model-value="sumbuAktif"
                                            size="sm"
                                            :leading-icon="sumbuMeta.ikon"
                                            placeholder="Pilih sumbu kelompok…"
                                            :clearable="false"
                                            class="w-100"
                                            @update:model-value="pilihSumbu"
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

                                    <!--
                                        bar SENGAJA MATI. Paket tak punya dimensi
                                        kemajuan di layar ini; bar yang selamanya 0%
                                        membacakan "belum dikerjakan" pada sesuatu yang
                                        memang tak untuk dikerjakan di sini.
                                    -->
                                    <RailSumbu
                                        :loading="loading.list"
                                        :grup="railGrup"
                                        :aktif="railNilaiAktif"
                                        :judul="sumbuMeta.label"
                                        :total-semua="railTotal"
                                        :bar="false"
                                        label-semua="Semua paket"
                                        @pilih="pilihGrup"
                                    />
                                </div>

                                <!-- Mode 2: Pohon Folder -->
                                <div v-show="railMode === 'tree'">
                                    <PohonFolderSop
                                        :loading="loading.list"
                                        :tree="treeData"
                                        :active-dept="listFilters.departemen"
                                        :active-jenis="listFilters.jenis"
                                        :total-semua="pagination.total_data || statusCountsData.ALL || 0"
                                        @pilih-dept="onPilihTreeDept"
                                        @pilih-sub="onPilihTreeSub"
                                        @pilih-semua="onPilihTreeSemua"
                                    />
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Panel Utama Paket -->
                    <div class="stitch-card stitch-pad ly-main">
                        <!--
                            Deretan tab status yang dulu di sini DIHAPUS, bukan
                            dipindah: pil status di HcisFilterBar sudah
                            menjawab pertanyaan yang sama dengan angka yang sama.
                            Dua kontrol yang mengerjakan satu hal akhirnya
                            berselisih, dan yang salah tak terlihat salah.

                            Yang menggantikannya adalah penyebutan saringan yang
                            SEDANG berlaku — termasuk yang dipasang dari rail,
                        <!-- Bilah Saringan Aktif Modern -->
                        <transition name="el-fade-in-linear">
                            <div v-if="hasActiveFilters" class="tk-bar mb-3">
                                <div class="tk-bar-left">
                                    <span class="tk-indicator">
                                        <HcisIcon name="funnel-fill" size="2xs" class="tk-indicator-icon" />
                                        <span>Filter Aktif:</span>
                                    </span>
                                    <div class="tk-chips">
                                        <div
                                            v-for="chip in activeFilterChips"
                                            :key="chip.key"
                                            class="tk-chip"
                                        >
                                            <span class="tk-chip-badge">
                                                <HcisIcon :name="chip.icon" size="2xs" class="tk-chip-icon" />
                                                <span class="tk-chip-label">{{ chip.label }}</span>
                                            </span>
                                            <span class="tk-chip-val" :title="chip.value">{{ chip.value }}</span>
                                            <button
                                                type="button"
                                                class="tk-chip-del"
                                                :title="`Hapus filter ${chip.label}`"
                                                :aria-label="`Hapus filter ${chip.label}`"
                                                @click="chip.clear"
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
                                        @click="onResetFilters"
                                    >
                                        <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                        <span>Reset Filter</span>
                                    </button>
                                </div>
                            </div>
                        </transition>

                        <transition name="sop-swap" mode="out-in">
                            <!-- loading skeleton -->
                            <div v-if="loading.list" key="load">
                                <div v-if="viewMode === 'grid'" class="pk-grid">
                                    <div v-for="n in 8" :key="n" class="pk-card pe-none" style="opacity: 0.9;">
                                        <div class="pk-cover d-flex align-items-center justify-content-center" style="background: rgba(241, 245, 249, 0.8);">
                                            <HcisSkeleton width="42px" height="42px" rounded="md" />
                                            <div class="pk-cover-badges-top">
                                                <HcisSkeleton width="68px" height="22px" rounded="full" />
                                                <HcisSkeleton width="36px" height="22px" rounded="full" />
                                            </div>
                                        </div>
                                        <div class="pk-body">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <HcisSkeleton width="65px" height="16px" rounded="xs" />
                                                <HcisSkeleton width="55px" height="16px" rounded="xs" />
                                            </div>
                                            <HcisSkeleton width="85%" height="1.1rem" rounded="xs" class="mb-1" />
                                            <HcisSkeleton width="55%" height="1.1rem" rounded="xs" class="mb-3" />
                                            <div class="d-flex gap-2 mb-3">
                                                <HcisSkeleton width="65px" height="20px" rounded="xs" />
                                                <HcisSkeleton width="75px" height="20px" rounded="xs" />
                                            </div>
                                            <div class="pk-metrics-strip" style="background: #f8fafc; border-radius: 8px; padding: 6px 10px;">
                                                <HcisSkeleton width="60px" height="16px" rounded="xs" />
                                                <HcisSkeleton width="60px" height="16px" rounded="xs" />
                                                <HcisSkeleton width="65px" height="16px" rounded="xs" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="sop-list">
                                    <div
                                        v-for="n in 6"
                                        :key="n"
                                        class="d-flex align-items-center gap-3 p-3 mb-2 bg-white border rounded-3 pe-none"
                                        style="opacity: 0.9;"
                                    >
                                        <HcisSkeleton width="38px" height="38px" rounded="md" />
                                        <div class="flex-grow-1 min-w-0 d-flex flex-column gap-1.5">
                                            <HcisSkeleton width="45%" height="1rem" rounded="xs" />
                                            <HcisSkeleton width="70%" height="0.8rem" rounded="xs" />
                                        </div>
                                        <HcisSkeleton width="80px" height="24px" rounded="full" />
                                        <HcisSkeleton width="32px" height="32px" rounded="sm" />
                                    </div>
                                </div>
                            </div>

                            <!-- data · GRID -->
                            <div
                                v-else-if="paketList.length && viewMode === 'grid'"
                                key="grid"
                                class="pk-grid sop-stagger"
                            >
                                <SopPaketCard
                                    v-for="p in paketList"
                                    :key="p.Id_SOP_Paket"
                                    :paket="p"
                                    :busy="busyId === `paket:${p.Id_SOP_Paket}`"
                                    @open="openEditor"
                                    @duplicate="duplikatPaket"
                                    @delete="hapusPaket"
                                />
                            </div>

                            <!-- data · LIST -->
                            <div v-else-if="paketList.length" key="list" class="sop-list sop-stagger">
                                <div
                                    v-for="p in paketList"
                                    :key="p.Id_SOP_Paket"
                                    class="sop-list-row clickable has-accent"
                                    :style="{ '--row-accent': theme(p.Nama_Paket).color }"
                                    @click="openEditor(p)"
                                >
                                    <div
                                        class="pr-thumb"
                                        :style="
                                            p.Cover_Url
                                                ? null
                                                : {
                                                      background: theme(p.Nama_Paket).color + '1a',
                                                      color: theme(p.Nama_Paket).color,
                                                  }
                                        "
                                    >
                                        <img
                                            v-if="p.Cover_Url"
                                            :src="p.Cover_Url"
                                            :style="{ objectPosition: p.Cover_Position || 'center' }"
                                            alt=""
                                            loading="lazy"
                                        />
                                        <i v-else class="bi" :class="theme(p.Nama_Paket).icon"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <span v-if="p.Kode_Paket" class="pk-code-badge">{{ p.Kode_Paket }}</span>
                                            <span class="row-title text-truncate">{{ p.Nama_Paket }}</span>
                                            <span v-if="p.Versi_Paket_Aktif" class="pk-chip release">
                                                <i class="bi bi-layers-fill"></i> v{{ p.Versi_Paket_Aktif }}
                                            </span>
                                            <span v-if="p.Has_Draft_Revisi" class="pk-chip amber" title="Draft revisi aktif sedang disunting">
                                                <i class="bi bi-pencil-fill"></i> Revisi
                                            </span>
                                        </div>
                                        <div class="row-sub d-flex align-items-center gap-2 flex-wrap">
                                            <span title="Jumlah section">
                                                <i class="bi bi-collection"></i> {{ p.Total_Section || 0 }} Section
                                            </span>
                                            <span>·</span>
                                            <span title="Jumlah item materi">
                                                <i class="bi bi-list-check"></i> {{ p.Total_Item || 0 }} Item
                                                <template v-if="p.Total_Wajib">
                                                    <span class="text-danger">({{ p.Total_Wajib }} wajib)</span>
                                                </template>
                                            </span>
                                            <template v-if="p.Total_Dokumen">
                                                <span>·</span>
                                                <span class="text-primary" title="Dokumen master terkendali">
                                                    <i class="bi bi-journal-bookmark-fill"></i> {{ p.Total_Dokumen }} Master Dok
                                                </span>
                                            </template>
                                            <template v-if="p.Estimasi_Waktu_Baca_Menit">
                                                <span>·</span>
                                                <span><i class="bi bi-clock"></i> ~{{ p.Estimasi_Waktu_Baca_Menit }} mnt</span>
                                            </template>
                                            <template v-if="p.Flag_Absensi === 'Y'">
                                                <span>·</span>
                                                <span class="text-warning-emphasis"><i class="bi bi-camera-fill"></i> Absensi Wajib</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <StatusPill
                                            :text="prioMeta(p.Prioritas).label"
                                            :warna="prioMeta(p.Prioritas).warna"
                                        />
                                        <span class="status-tag" :class="statusVariant(p.Status)">{{
                                            statusLabel(p.Status)
                                        }}</span>
                                    </div>
                                    <div class="pr-actions" @click.stop>
                                        <button
                                            class="stitch-btn ghost sm"
                                            :disabled="busyId === `paket:${p.Id_SOP_Paket}`"
                                            @click="openEditor(p)"
                                        >
                                            <i class="bi bi-sliders2"></i> Kelola
                                        </button>
                                        <button
                                            class="stitch-icon-btn"
                                            title="Duplikat"
                                            :disabled="busyId === `paket:${p.Id_SOP_Paket}`"
                                            @click="duplikatPaket(p)"
                                        >
                                            <span
                                                v-if="busyId === `paket:${p.Id_SOP_Paket}`"
                                                class="spinner-border spinner-border-sm"
                                            ></span>
                                            <i v-else class="bi bi-files"></i>
                                        </button>
                                        <button
                                            class="stitch-icon-btn danger"
                                            title="Hapus"
                                            :disabled="busyId === `paket:${p.Id_SOP_Paket}`"
                                            @click="hapusPaket(p)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <EmptyState
                                v-else
                                key="empty"
                                icon="bi-journals"
                                :title="hasActiveFilter ? 'Tidak ada paket cocok' : 'Belum ada paket'"
                                :subtitle="
                                    hasActiveFilter
                                        ? 'Coba ubah/atur ulang filter.'
                                        : 'Mulai dengan membuat paket dokumen pertama.'
                                "
                            >
                                <button class="stitch-btn primary" @click="startNewPaket">
                                    <i class="bi bi-plus-lg"></i> Buat Paket
                                </button>
                            </EmptyState>
                        </transition>

                        <div v-if="pagination.total_page > 1" class="sop-pager">
                            <button
                                class="stitch-btn ghost sm"
                                :disabled="pagination.current_page <= 1 || loading.list"
                                @click="fetchPaket(pagination.current_page - 1)"
                            >
                                <i class="bi bi-chevron-left"></i> Sebelumnya
                            </button>
                            <span class="sop-pager-info"
                                >Halaman {{ pagination.current_page }} dari {{ pagination.total_page }} ·
                                {{ pagination.total_data }} paket</span
                            >
                            <button
                                class="stitch-btn ghost sm"
                                :disabled="pagination.current_page >= pagination.total_page || loading.list"
                                @click="fetchPaket(pagination.current_page + 1)"
                            >
                                Berikutnya <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ===================== MODE: EDITOR (wizard) ===================== -->
        <template v-else>
            <!-- ══ EXECUTIVE WIZARD HEADER (HCIS SIGNATURE LUMINOUS HEADER) ══ -->
            <HcisHeader
                :title="editorHeaderTitle"
                :kicker="editorHeaderKicker"
                :description="editorHeaderDescription"
                icon="diagram-3-fill"
                sentiment="brand"
                size="md"
                animated
                :loading="loading.editor"
                class="mb-4 sop-fade-up"
            >
                <template #badge>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <HcisBadge
                            v-if="current.Id_SOP_Paket"
                            :label="statusLabel(current.Status)"
                            :variant="badgeVariantForStatus(current.Status)"
                            appearance="glass"
                            size="sm"
                            dot
                        />
                        <HcisBadge
                            v-else
                            label="Draft Baru"
                            variant="warning"
                            appearance="glass"
                            size="sm"
                            dot
                        />
                        <HcisBadge
                            v-if="current.Prioritas"
                            :label="prioMeta(current.Prioritas).label"
                            :variant="badgeVariantForPrio(current.Prioritas)"
                            appearance="subtle"
                            size="sm"
                        />
                    </div>
                </template>

                <template #actions>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="arrow-left"
                            label="Daftar Paket"
                            title="Kembali ke Daftar Paket Dokumen"
                            aria-label="Kembali ke Daftar Paket Dokumen"
                            @click="backToList"
                        />

                        <!-- Contextual Actions depending on Step -->
                        <template v-if="step === 1">
                            <HcisButton
                                variant="primary"
                                size="md"
                                trailing-icon="arrow-right"
                                :label="current.Id_SOP_Paket ? 'Simpan & Struktur' : 'Buat & Lanjut'"
                                :loading="loading.save"
                                @click="saveMetaAndNext"
                            />
                        </template>
                        <template v-else-if="step === 2">
                            <HcisButton
                                v-if="current.Id_SOP_Paket"
                                variant="glass"
                                size="md"
                                leading-icon="clock-history"
                                label="Riwayat"
                                title="Lihat jejak aktivitas & audit log"
                                :disabled="loading.struktur || loading.save"
                                @click="gotoStep(4)"
                            />
                            <HcisButton
                                variant="secondary"
                                size="md"
                                leading-icon="plus-lg"
                                label="Section"
                                title="Tambah section baru"
                                :disabled="loading.struktur || loading.save || (sectionModal && sectionModal.saving)"
                                @click="addSection"
                            />
                            <HcisButton
                                variant="primary"
                                size="md"
                                trailing-icon="arrow-right"
                                label="Tinjau & Publish"
                                :disabled="loading.struktur || loading.save || (sectionModal && sectionModal.saving)"
                                @click="gotoStep(3)"
                            />
                        </template>
                        <template v-else-if="step === 3">
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="send-check-fill"
                                :label="current.Status === 'PUBLISHED' ? `Publish v${nextVersiPaket}` : 'Publish Dokumen v1'"
                                :disabled="!canPublish || publishMengirim"
                                :title="publishHint"
                                @click="ubahStatus('PUBLISHED')"
                            />
                        </template>
                        <template v-else-if="step === 4">
                            <HcisButton
                                variant="secondary"
                                size="md"
                                leading-icon="diagram-3"
                                label="Kembali ke Struktur"
                                @click="gotoStep(2)"
                            />
                        </template>
                    </div>
                </template>

                <!-- ══ TAB NAVIGASI WIZARD (HcisTabs variant="header") ══ -->
                <template #tabs>
                    <HcisTabs
                        :model-value="step"
                        variant="header"
                        :tabs="editorStepsTabs"
                        @update:model-value="gotoStep"
                    />
                </template>
            </HcisHeader>

            <!-- ============ ANIMASI LOADING DETAIL PAKET ============ -->
            <div v-if="loading.editor" class="editor-loading-state sop-fade-up">
                <div
                    class="sop-skel-hero mb-4 p-4 p-md-5 d-flex flex-column justify-content-between position-relative overflow-hidden"
                    style="min-height: 200px; border-radius: 24px"
                >
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="sop-skel" style="width: 140px; height: 26px; border-radius: 999px"></div>
                        <div class="sop-skel" style="width: 90px; height: 26px; border-radius: 999px"></div>
                    </div>
                    <div class="mb-3">
                        <div class="sop-skel mb-2" style="width: 55%; height: 32px; border-radius: 10px"></div>
                        <div class="sop-skel" style="width: 40%; height: 18px; border-radius: 8px"></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-primary fw-medium small">
                        <span
                            class="spinner-border spinner-border-sm"
                            role="status"
                            style="width: 1.1rem; height: 1.1rem"
                        ></span>
                        <span>Memuat data detail paket dokumen...</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <div class="stitch-card p-4 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="sop-skel" style="width: 160px; height: 24px; border-radius: 8px"></div>
                                <div class="sop-skel" style="width: 100px; height: 32px; border-radius: 8px"></div>
                            </div>
                            <div class="sop-skel mb-3" style="height: 64px; border-radius: 14px"></div>
                            <div class="sop-skel mb-3" style="height: 64px; border-radius: 14px"></div>
                            <div class="sop-skel mb-3" style="height: 64px; border-radius: 14px"></div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 d-none d-lg-block">
                        <div class="stitch-card p-4">
                            <div class="sop-skel mb-3" style="width: 180px; height: 22px; border-radius: 6px"></div>
                            <div class="sop-skel" style="height: 300px; border-radius: 16px"></div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-else>
                <!-- ============ STEP 1 · IDENTITAS ============ -->
                <div v-show="step === 1" class="stitch-card stitch-pad sop-fade-up">
                    <div class="meta-grid">
                        <div class="meta-rail">
                            <div class="sop-eyebrow mb-2">Cover</div>
                            <SopCoverPicker
                                v-model="coverModel"
                                v-model:position="paketForm.Cover_Position"
                                :cover-url="current.Cover_Url || ''"
                                :accent="theme(paketForm.Nama_Paket).color"
                                :icon="theme(paketForm.Nama_Paket).icon"
                                :nama="paketForm.Nama_Paket"
                            />

                            <div class="sop-eyebrow mt-4 mb-2">Gerbang absensi</div>
                            <div class="abs-radios">
                                <label class="abs-radio" :class="{ active: !paketForm.Flag_Absensi }">
                                    <input
                                        type="radio"
                                        name="absensi"
                                        :value="false"
                                        v-model="paketForm.Flag_Absensi"
                                    />
                                    <span class="ar-ico"><i class="bi bi-door-open"></i></span>
                                    <span class="ar-txt"
                                        ><strong>Tanpa absensi</strong><small>Langsung akses isi dokumen</small></span
                                    >
                                    <i class="bi bi-check-circle-fill ar-check"></i>
                                </label>
                                <label class="abs-radio" :class="{ active: paketForm.Flag_Absensi }">
                                    <input type="radio" name="absensi" :value="true" v-model="paketForm.Flag_Absensi" />
                                    <span class="ar-ico amber"><i class="bi bi-camera-fill"></i></span>
                                    <span class="ar-txt"
                                        ><strong>Wajib absensi</strong><small>Check-in dulu sebelum mulai</small></span
                                    >
                                    <i class="bi bi-check-circle-fill ar-check"></i>
                                </label>
                            </div>
                        </div>

                        <div class="meta-main">
                            <div class="sop-eyebrow mb-2">Identitas</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="sop-label">Nama Paket *</label
                                    ><input
                                        v-model="paketForm.Nama_Paket"
                                        class="sop-field"
                                        placeholder="Contoh: SOP Perjalanan Dinas"
                                    />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="sop-label">Prioritas</label>
                                    <el-select v-model="paketForm.Prioritas" filterable style="width: 100%">
                                        <el-option label="Normal" value="NORMAL" />
                                        <el-option label="Tinggi" value="TINGGI" />
                                        <el-option label="Sangat Penting" value="KRITIS" />
                                    </el-select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="sop-label">Mode urutan</label>
                                    <el-select v-model="paketForm.Mode_Urutan_Item" style="width: 100%">
                                        <el-option label="Bebas" value="BEBAS" />
                                        <el-option label="Sekuensial" value="SEKUENSIAL" />
                                    </el-select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="sop-label">Distribusi</label>
                                    <el-select v-model="paketForm.Mode_Distribusi" style="width: 100%">
                                        <el-option label="Terbatas (yang ditugaskan)" value="TERBATAS" />
                                        <el-option label="Publik (semua karyawan)" value="PUBLIK" />
                                    </el-select>
                                    <small class="sop-hint d-block mt-1">
                                        <template v-if="paketForm.Mode_Distribusi === 'PUBLIK'">
                                            Tampil di Repository untuk semua karyawan. Wajib atau tidaknya tetap diatur
                                            di Penjadwalan.
                                            <!-- Keputusan 10 Sep 2026: Publik + Kuis DILARANG, dan penolakannya
                                                 ditegakkan di kedua pintu publish (SopGerbangPublikKuis). Kalimat
                                                 di sini dulu menjanjikan pendaftaran mandiri — layar yang
                                                 menjanjikan apa yang server tolak lebih buruk daripada layar
                                                 yang diam. -->
                                            <span v-if="adaItemKuis" class="text-danger d-block mt-1">
                                                <i class="bi bi-exclamation-octagon-fill"></i>
                                                Paket ini punya item Kuis, jadi <strong>tidak bisa diterbitkan</strong>
                                                sebagai Publik. Ubah Distribusi menjadi Terbatas, atau keluarkan item
                                                kuisnya lebih dulu.
                                            </span>
                                        </template>
                                        <template v-else
                                            >Hanya terlihat oleh karyawan yang punya penugasan aktif.</template
                                        >
                                    </small>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="sop-label">Estimasi baca (menit)</label
                                    ><input
                                        v-model.number="paketForm.Estimasi_Waktu_Baca_Menit"
                                        type="number"
                                        min="0"
                                        class="sop-field"
                                        placeholder="Opsional"
                                    />
                                </div>
                            </div>

                            <div class="sop-eyebrow mt-4 mb-2">Deskripsi &amp; tujuan</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="sop-label">Deskripsi</label
                                    ><textarea
                                        v-model="paketForm.Deskripsi"
                                        class="sop-field"
                                        rows="2"
                                        placeholder="Ringkas isi & manfaat dokumen ini"
                                    ></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="sop-label">Tujuan &amp; ruang lingkup</label
                                    ><textarea
                                        v-model="paketForm.Tujuan_Ruang_Lingkup"
                                        class="sop-field"
                                        rows="2"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button class="stitch-btn secondary" @click="backToList">Batal</button>
                                <button class="stitch-btn primary" :disabled="loading.save" @click="saveMetaAndNext">
                                    <span v-if="loading.save" class="spinner-border spinner-border-sm"></span>
                                    {{ current.Id_SOP_Paket ? 'Simpan' : 'Buat' }} &amp; lanjut ke Struktur
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ STEP 2 · STRUKTUR ============ -->
                <div v-show="step === 2">
                    <div class="meta-hero sop-fade-up" :style="heroBg">
                        <div class="mh-overlay"></div>
                        <div class="mh-content">
                            <div class="mh-top">
                                <span class="mh-status" :class="'st-' + statusVariant(current.Status)">{{
                                    statusLabel(current.Status)
                                }}</span>
                                <div class="tb-spacer"></div>
                                <button class="stitch-btn glass sm d-lg-none" @click="previewOpen = !previewOpen">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="stitch-btn glass sm" @click="goIdentitas">
                                    <i class="bi bi-gear"></i> Edit Identitas
                                </button>
                            </div>

                            <div class="mh-body">
                                <div class="mh-lead">
                                    <div class="mh-main">
                                        <h3 class="mh-title">{{ current.Nama_Paket }}</h3>
                                        <p v-if="current.Deskripsi" class="mh-desc">{{ current.Deskripsi }}</p>
                                        <div class="mh-chips">
                                            <span
                                                class="mh-chip prio"
                                                :class="'p-' + (current.Prioritas || 'NORMAL').toLowerCase()"
                                                ><i class="bi bi-flag-fill"></i>
                                                {{ prioMeta(current.Prioritas).label }}</span
                                            >
                                            <span class="mh-chip"
                                                ><i
                                                    class="bi"
                                                    :class="
                                                        current.Mode_Urutan_Item === 'SEKUENSIAL'
                                                            ? 'bi-signpost-2'
                                                            : 'bi-shuffle'
                                                    "
                                                ></i>
                                                {{
                                                    current.Mode_Urutan_Item === 'SEKUENSIAL' ? 'Sekuensial' : 'Bebas'
                                                }}</span
                                            >
                                            <span v-if="current.Flag_Absensi === 'Y'" class="mh-chip"
                                                ><i class="bi bi-camera-fill"></i> Absensi wajib</span
                                            >
                                        </div>
                                    </div>

                                    <div class="mh-stats">
                                        <div class="mh-stat">
                                            <span class="ms-num">{{ sections.length }}</span
                                            ><span class="ms-lbl">Section</span>
                                        </div>
                                        <div class="mh-stat">
                                            <span class="ms-num">{{ items.length }}</span
                                            ><span class="ms-lbl">Item</span>
                                        </div>
                                        <div class="mh-stat">
                                            <span class="ms-num">{{ wajibCount }}</span
                                            ><span class="ms-lbl">Wajib</span>
                                        </div>
                                        <div v-if="current.Estimasi_Waktu_Baca_Menit" class="mh-stat">
                                            <span class="ms-num"
                                                >~{{ current.Estimasi_Waktu_Baca_Menit }}<small>m</small></span
                                            ><span class="ms-lbl">Baca</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mh-aside d-none d-md-flex">
                                    <div class="mh-ring" :style="{ '--p': readyPct }">
                                        <span class="mh-ring-num">{{ readyPct }}<small>%</small></span>
                                    </div>
                                    <div class="mh-ring-cap">
                                        <i
                                            class="bi"
                                            :class="canPublish ? 'bi-check-circle-fill' : 'bi-hourglass-split'"
                                        ></i>
                                        {{ canPublish ? 'Siap diterbitkan' : publishHint }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sop-workspace split-right sop-fade-up">
                        <!-- Struktur -->
                        <div class="sop-panel">
                            <!-- Working draft unik per paket, bukan per user: beri tahu bila
                             admin lain juga menyentuh salinan kerja yang sama. -->
                            <div v-if="draftActivity" class="draft-shared-note">
                                <i class="bi bi-people-fill"></i>
                                <span>
                                    Draft revisi ini juga disentuh
                                    <b>{{ draftActivity.Nama || 'admin lain' }}</b>
                                    <span v-if="draftActivity.At"> · {{ waktuRelatif(draftActivity.At) }}</span
                                    >. Perubahan kalian tergabung di draft yang sama.
                                </span>
                            </div>
                            <div class="sop-panel-head">
                                <span class="ph-title">Struktur</span>
                                <button
                                    v-if="strukturChip"
                                    class="struktur-chip"
                                    :class="'tone-' + strukturChip.tone"
                                    :title="'Lihat rincian di langkah Tinjau & Publish'"
                                    @click="step = 3"
                                >
                                    <i class="bi" :class="strukturChip.icon"></i>
                                    <span>{{ strukturChip.text }}</span>
                                </button>
                                <!--
                                    Pil urutan duduk di KEPALA panel, bukan
                                    menempel pada baris yang diseret: yang
                                    sedang disimpan adalah susunan seluruh
                                    daftar, bukan satu baris itu saja.
                                -->
                                <span v-if="loading.urutan" class="sibuk-pil" role="status">
                                    <span class="spinner-border spinner-border-sm"></span>
                                    Menyimpan urutan…
                                </span>
                                <span class="flex-grow-1"></span>
                                <button
                                    v-if="current.Id_SOP_Paket"
                                    class="stitch-btn ghost sm"
                                    title="Lihat siapa mengubah paket ini dan apa yang dilakukan"
                                    @click="gotoStep(4)"
                                >
                                    <i class="bi bi-clock-history"></i> Riwayat
                                </button>
                                <button
                                    class="stitch-btn primary sm"
                                    :disabled="loading.struktur || loading.save || (sectionModal && sectionModal.saving)"
                                    @click="addSection"
                                >
                                    <i class="bi bi-plus-lg"></i> Section
                                </button>
                            </div>
                            <div class="sop-panel-body flush struktur-wrap" :class="{ sibuk: strukturSibuk }">
                                <!--
                                    Kerangka, BUKAN "Belum ada section".

                                    Muat pertama struktur berjalan atas
                                    `sections` yang masih kosong, jadi keadaan
                                    kosong sempat berkelebat lebih dulu — dan
                                    kalimatnya ("Buat section dulu") membacakan
                                    kesimpulan yang belum tentu benar tentang
                                    paket yang justru sedang dibaca.
                                -->
                                <div v-if="strukturKosongMemuat" class="struktur-skel">
                                    <div v-for="n in 3" :key="n" class="sop-skel" style="height: 64px"></div>
                                </div>

                                <EmptyState
                                    v-else-if="!sections.length"
                                    compact
                                    icon="bi-diagram-3"
                                    title="Belum ada section"
                                    subtitle="Buat section dulu, lalu tambahkan item di dalamnya."
                                >
                                    <button
                                        class="stitch-btn primary sm"
                                        :disabled="loading.struktur || loading.save || (sectionModal && sectionModal.saving)"
                                        @click="addSection"
                                    >
                                        <i class="bi bi-plus-lg"></i> Buat Section
                                    </button>
                                </EmptyState>

                                <!--
                                    Isinya SENGAJA tetap terlihat selama
                                    penyegaran — diredupkan, tak bisa diklik,
                                    dan diberi pil. Menutupinya dengan lapisan
                                    buram seperti pada simpan item akan
                                    menyembunyikan justru bagian yang barusan
                                    diubah, untuk penantian yang biasanya lebih
                                    pendek daripada animasi penutupnya sendiri.
                                -->
                                <span v-if="strukturSibuk" class="sibuk-pil melayang" role="status">
                                    <span class="spinner-border spinner-border-sm"></span>
                                    Menyegarkan struktur…
                                </span>

                                <div
                                    v-for="sec in sections"
                                    :key="sec.Id_SOP_Section"
                                    class="sec"
                                    :class="{
                                        'sec-dragover': dragOverSectionId === sec.Id_SOP_Section,
                                        'sec-collapsed': collapsedSections[sec.Id_SOP_Section],
                                    }"
                                    @dragover.prevent="dragSectionId && (dragOverSectionId = sec.Id_SOP_Section)"
                                    @dragleave="dragOverSectionId === sec.Id_SOP_Section && (dragOverSectionId = null)"
                                    @drop="onSectionZoneDrop(sec)"
                                >
                                    <div class="sec-head">
                                        <!--
                                            Gagang mati selama urutan sebelumnya
                                            masih dikirim. Dua penyusunan yang
                                            saling menyusul membuat permintaan
                                            kedua berangkat membawa susunan yang
                                            server belum sempat menyetujui, dan
                                            yang menang adalah yang kebetulan
                                            mendarat belakangan.
                                        -->
                                        <span
                                            class="sec-drag"
                                            :class="{ mati: loading.urutan }"
                                            :title="loading.urutan ? 'Menyimpan urutan…' : 'Seret untuk urutkan'"
                                            :draggable="!loading.urutan"
                                            @dragstart="onSectionDragStart(sec)"
                                            @dragend="onSectionDragEnd"
                                            ><i class="bi bi-grip-vertical"></i
                                        ></span>
                                        <button
                                            class="sec-toggle min-w-0"
                                            :aria-expanded="!collapsedSections[sec.Id_SOP_Section]"
                                            @click="toggleCollapse(sec)"
                                        >
                                            <i
                                                class="bi sec-caret"
                                                :class="
                                                    collapsedSections[sec.Id_SOP_Section]
                                                        ? 'bi-chevron-right'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                            <i class="bi bi-collection sec-ico"></i>
                                            <span class="sec-head-txt min-w-0">
                                                <span class="sec-name text-truncate">
                                                    {{ sec.Nama_Section }}
                                                    <span
                                                        v-if="badgePerubahan(sec.Id_SOP_Section)"
                                                        class="chg-badge"
                                                        :class="badgePerubahan(sec.Id_SOP_Section).cls"
                                                        :title="badgePerubahan(sec.Id_SOP_Section).title"
                                                    >
                                                        <i
                                                            v-if="badgePerubahan(sec.Id_SOP_Section).icon"
                                                            class="bi"
                                                            :class="badgePerubahan(sec.Id_SOP_Section).icon"
                                                        ></i>
                                                        <span>{{ badgePerubahan(sec.Id_SOP_Section).text }}</span>
                                                        <span
                                                            v-if="badgePerubahan(sec.Id_SOP_Section).subtext"
                                                            class="badge-subtext"
                                                            >· {{ badgePerubahan(sec.Id_SOP_Section).subtext }}</span
                                                        >
                                                    </span>
                                                    <span
                                                        v-else-if="
                                                            collapsedSections[sec.Id_SOP_Section] &&
                                                            sectionAdaPerubahan(sec.Id_SOP_Section)
                                                        "
                                                        class="chg-dot"
                                                        title="Ada perubahan di dalam section ini"
                                                    ></span>
                                                </span>
                                                <span class="sec-sub text-truncate"
                                                    >{{ itemsOf(sec.Id_SOP_Section).length }} item<span
                                                        v-if="sectionWajib(sec.Id_SOP_Section)"
                                                    >
                                                        · {{ sectionWajib(sec.Id_SOP_Section) }} wajib</span
                                                    ><span v-if="sec.Deskripsi"> · {{ sec.Deskripsi }}</span></span
                                                >
                                            </span>
                                        </button>
                                        <button
                                            class="stitch-btn ghost sm"
                                            :disabled="loading.save || loading.struktur"
                                            @click="startNewItem(sec)"
                                        >
                                            <i class="bi bi-plus-lg"></i> Item
                                        </button>
                                        <button
                                            class="stitch-icon-btn"
                                            title="Duplikat section"
                                            :disabled="busyId === `section:${sec.Id_SOP_Section}` || loading.struktur || loading.save"
                                            @click="duplikatSection(sec)"
                                        >
                                            <span
                                                v-if="busyId === `section:${sec.Id_SOP_Section}`"
                                                class="spinner-border spinner-border-sm"
                                            ></span>
                                            <i v-else class="bi bi-files"></i>
                                        </button>
                                        <button
                                            class="stitch-icon-btn"
                                            title="Ubah section"
                                            :disabled="loading.struktur || loading.save"
                                            @click="renameSection(sec)"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            class="stitch-icon-btn danger"
                                            title="Hapus"
                                            :disabled="loading.struktur || loading.save"
                                            @click="deleteSection(sec)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <div class="sec-items" v-show="!collapsedSections[sec.Id_SOP_Section]">
                                        <transition name="sop-swap">
                                            <div
                                                v-if="adding && addingSection === sec.Id_SOP_Section"
                                                class="acc-item is-open new-item"
                                            >
                                                <div class="acc-editor">
                                                    <div v-if="loading.save" class="editor-loading-overlay">
                                                        <div
                                                            class="d-flex flex-column align-items-center justify-content-center p-3 text-center"
                                                        >
                                                            <div
                                                                class="spinner-border text-primary mb-2"
                                                                role="status"
                                                                style="width: 2rem; height: 2rem"
                                                            ></div>
                                                            <span class="fw-bold text-dark small"
                                                                >Menyimpan item baru...</span
                                                            >
                                                            <span class="text-muted small"
                                                                >Harap tunggu sebentar</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <label class="sop-label">Tipe item</label>
                                                    <div class="type-palette mb-3">
                                                        <button
                                                            v-for="t in tipeList"
                                                            :key="t"
                                                            type="button"
                                                            class="type-chip"
                                                            :class="{ active: itemForm.Tipe_Item === t }"
                                                            :disabled="loading.save"
                                                            @click="itemForm.Tipe_Item = t"
                                                        >
                                                            <ItemTypeIcon :tipe="t" /><span>{{ tipeLabel(t) }}</span>
                                                        </button>
                                                    </div>
                                                    <ItemFields
                                                        :form="itemForm"
                                                        :isNew="true"
                                                        :paketPublished="paketPublished"
                                                        :dokumenDipakai="dokumenDipakaiPaket"
                                                        :dukungDokumenDraft="dukungDokumenDraft"
                                                    />
                                                    <div class="ins-actions mt-3">
                                                        <button
                                                            class="stitch-btn primary block"
                                                            :disabled="loading.save"
                                                            @click="saveItem"
                                                        >
                                                            <span
                                                                v-if="loading.save"
                                                                class="spinner-border spinner-border-sm"
                                                            ></span
                                                            ><template v-if="loading.save"> Menyimpan Item...</template
                                                            ><template v-else><i class="bi bi-check-lg"></i> Simpan Item</template>
                                                        </button>
                                                        <button
                                                            class="stitch-btn secondary"
                                                            :disabled="loading.save"
                                                            @click="cancelAdding"
                                                        >
                                                            Batal
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </transition>

                                        <p
                                            v-if="
                                                !itemsOf(sec.Id_SOP_Section).length &&
                                                !(adding && addingSection === sec.Id_SOP_Section)
                                            "
                                            class="sec-empty"
                                        >
                                            Belum ada item.
                                            <button
                                                class="linklike"
                                                :disabled="loading.save || loading.struktur"
                                                @click="startNewItem(sec)"
                                            >Tambah item</button>
                                        </p>

                                        <div class="acc-list">
                                            <div
                                                v-for="it in itemsOf(sec.Id_SOP_Section)"
                                                :key="it.Id_SOP_Paket_Item"
                                                class="acc-item"
                                                :class="{
                                                    'is-open': openItemId === it.Id_SOP_Paket_Item,
                                                    'drag-over': dragOverId === it.Id_SOP_Paket_Item,
                                                }"
                                            >
                                                <div
                                                    class="acc-head"
                                                    :draggable="!loading.urutan"
                                                    role="button"
                                                    tabindex="0"
                                                    :aria-expanded="openItemId === it.Id_SOP_Paket_Item"
                                                    @click="toggleItem(it)"
                                                    @keydown.enter.prevent="toggleItem(it)"
                                                    @keydown.space.prevent="toggleItem(it)"
                                                    @dragstart="onItemDragStart(it)"
                                                    @dragover.prevent="dragOverId = it.Id_SOP_Paket_Item"
                                                    @dragleave="
                                                        dragOverId === it.Id_SOP_Paket_Item && (dragOverId = null)
                                                    "
                                                    @drop.stop="onItemDrop(it)"
                                                    @dragend="
                                                        dragId = null;
                                                        dragOverId = null;
                                                    "
                                                >
                                                    <span class="drag-handle" title="Seret" @click.stop
                                                        ><i class="bi bi-grip-vertical"></i
                                                    ></span>
                                                    <ItemTypeIcon :tipe="it.Tipe_Item" />
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="row-title text-truncate">{{ it.Judul_Item }}</div>
                                                        <div class="row-sub">
                                                            {{ tipeLabel(it.Tipe_Item)
                                                            }}<span v-if="it.Flag_Wajib === 'Y'" class="wajib-tag"
                                                                >· Wajib</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <span
                                                        v-if="itemBadgePerubahan(it)"
                                                        class="chg-badge"
                                                        :class="itemBadgePerubahan(it).cls"
                                                        :title="itemBadgePerubahan(it).title"
                                                    >
                                                        <i
                                                            v-if="itemBadgePerubahan(it).icon"
                                                            class="bi"
                                                            :class="itemBadgePerubahan(it).icon"
                                                        ></i>
                                                        <span>{{ itemBadgePerubahan(it).text }}</span>
                                                        <span
                                                            v-if="itemBadgePerubahan(it).subtext"
                                                            class="badge-subtext"
                                                            >· {{ itemBadgePerubahan(it).subtext }}</span
                                                        >
                                                    </span>
                                                    <i
                                                        class="bi acc-caret"
                                                        :class="
                                                            openItemId === it.Id_SOP_Paket_Item
                                                                ? 'bi-chevron-up'
                                                                : 'bi-chevron-down'
                                                        "
                                                    ></i>
                                                </div>

                                                <transition name="sop-swap">
                                                    <div v-if="openItemId === it.Id_SOP_Paket_Item" class="acc-editor">
                                                        <div v-if="loading.save" class="editor-loading-overlay">
                                                            <div
                                                                class="d-flex flex-column align-items-center justify-content-center p-3 text-center"
                                                            >
                                                                <div
                                                                    class="spinner-border text-primary mb-2"
                                                                    role="status"
                                                                    style="width: 2rem; height: 2rem"
                                                                ></div>
                                                                <span class="fw-bold text-dark small"
                                                                    >Menyimpan detail item...</span
                                                                >
                                                                <span class="text-muted small"
                                                                    >Harap tunggu sebentar</span
                                                                >
                                                            </div>
                                                        </div>
                                                        <label class="sop-label">Tipe item</label>
                                                        <!--
                                                        TERKUNCI untuk item ber-dokumen — dan itu bukan pembatasan baru:
                                                        server memang sudah menolaknya sejak Fase 6, karena tipe item
                                                        adalah CERMINAN tipe dokumennya. Yang baru cuma layarnya berhenti
                                                        menawarkan. Selama kotaknya masih bisa diklik, satu-satunya hasil
                                                        yang mungkin adalah 422 yang menyalahkan dokumennya — dan tombol
                                                        yang tak pernah bisa bekerja mengajarkan bahwa tombol gagal itu wajar.
                                                    -->
                                                        <div
                                                            class="type-palette mb-2"
                                                            :class="{ terkunci: tipeTerkunci }"
                                                        >
                                                            <button
                                                                v-for="t in tipeListEdit"
                                                                :key="t"
                                                                type="button"
                                                                class="type-chip"
                                                                :class="{ active: itemForm.Tipe_Item === t }"
                                                                :disabled="tipeTerkunci && itemForm.Tipe_Item !== t"
                                                                @click="itemForm.Tipe_Item = t"
                                                            >
                                                                <ItemTypeIcon :tipe="t" /><span>{{
                                                                    tipeLabel(t)
                                                                }}</span>
                                                            </button>
                                                        </div>
                                                        <div v-if="tipeTerkunci" class="field-hint">
                                                            <i class="bi bi-lock-fill"></i>
                                                            <span>Tipe item terikat pada Dokumen terpilih.</span>
                                                        </div>
                                                        <ItemFields
                                                            :form="itemForm"
                                                            :isNew="false"
                                                            :paketPublished="paketPublished"
                                                            :dokumenDipakai="dokumenDipakaiPaket"
                                                            :dukungDokumenDraft="dukungDokumenDraft"
                                                        />
                                                        <div class="ins-actions mt-3">
                                                            <button
                                                                class="stitch-btn primary block"
                                                                :disabled="loading.save"
                                                                @click="saveItem"
                                                            >
                                                                <span
                                                                    v-if="loading.save"
                                                                    class="spinner-border spinner-border-sm"
                                                                ></span
                                                                ><i v-else class="bi bi-check-lg"></i> Simpan
                                                            </button>
                                                            <button
                                                                class="stitch-btn secondary"
                                                                :disabled="loading.save"
                                                                @click="cancelEdit"
                                                            >
                                                                Batal
                                                            </button>
                                                            <button
                                                                class="stitch-btn ghost"
                                                                :disabled="
                                                                    loading.save ||
                                                                    busyId === `item:${it.Id_SOP_Paket_Item}`
                                                                "
                                                                title="Duplikat item"
                                                                @click="duplikatItem(it)"
                                                            >
                                                                <span
                                                                    v-if="busyId === `item:${it.Id_SOP_Paket_Item}`"
                                                                    class="spinner-border spinner-border-sm"
                                                                ></span>
                                                                <i v-else class="bi bi-files"></i>
                                                            </button>
                                                            <!-- Item revisi paket terbit (token di_) hidup di tabel overlay,
                                                             bukan di tabel item live — server menolaknya. Tombolnya
                                                             disembunyikan supaya admin tak menempuh dua langkah pemilihan
                                                             lalu baru ditolak di akhir. -->
                                                            <button
                                                                v-if="bisaSalinKePaketLain(it)"
                                                                class="stitch-btn ghost"
                                                                :disabled="loading.save"
                                                                title="Salin ke paket lain"
                                                                @click="openCopyToPaketModal(it)"
                                                            >
                                                                <i class="bi bi-send-plus"></i>
                                                            </button>
                                                            <button
                                                                class="stitch-btn danger"
                                                                :disabled="loading.save"
                                                                @click="hapusItem(it)"
                                                            >
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                        <!-- BACA SAJA. Membuat, menyiapkan rilis, menerbitkan,
                                                             dan menghapus versi seluruhnya pindah ke Master Dokumen
                                                             (27 Agu 2026) bersama lima langkah pengesahannya. -->
                                                        <SopVersiRingkas
                                                            v-if="isFileType(it.Tipe_Item)"
                                                            :id-item="it.Id_SOP_Paket_Item"
                                                            :id-dokumen="it.Id_SOP_Dokumen || ''"
                                                            :tipe="it.Tipe_Item"
                                                        />
                                                        <SopKuisVersiPanel
                                                            v-else-if="it.Tipe_Item === 'KUIS' && it.Id_Master_Ujian"
                                                            :key="it.Id_Master_Ujian"
                                                            :id-item="it.Id_SOP_Paket_Item"
                                                            :nama-ujian="it.Nama_Ujian"
                                                        />
                                                    </div>
                                                </transition>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="wiz-nav">
                                    <button class="stitch-btn secondary" @click="goIdentitas">
                                        <i class="bi bi-arrow-left"></i> Identitas
                                    </button>
                                    <button class="stitch-btn primary flex-grow-1" @click="gotoStep(3)">
                                        Lanjut ke Tinjau <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Live preview -->
                        <div class="sop-panel is-sticky preview-pane" :class="{ 'mobile-open': previewOpen }">
                            <div class="sop-panel-head">
                                <span class="ph-title flex-grow-1"
                                    ><i class="bi bi-phone me-1"></i> Pratinjau Mobile Karyawan</span
                                >
                                <button class="stitch-icon-btn d-lg-none" @click="previewOpen = false">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="sop-panel-body p-0">
                                <SopLivePreview
                                    :paket="current"
                                    :sections="sections"
                                    :items="items"
                                    :editing-id="openItemId"
                                    :editing-item="itemForm"
                                    :adding="adding"
                                    :compact="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ STEP 3 · TINJAU & PUBLISH ============ -->
                <div v-show="step === 3" class="sop-fade-up">
                    <div class="review-head stitch-card stitch-pad mb-3">
                        <div class="flex-grow-1">
                            <div class="sop-eyebrow">Langkah akhir</div>
                            <h5 class="sop-h">Tinjau sebagai karyawan, lalu publikasikan</h5>
                            <div class="checks mt-2">
                                <span class="chk" :class="sections.length ? 'ok' : 'no'"
                                    ><i
                                        class="bi"
                                        :class="sections.length ? 'bi-check-circle-fill' : 'bi-x-circle'"
                                    ></i>
                                    {{ sections.length }} section</span
                                >
                                <span class="chk" :class="items.length ? 'ok' : 'no'"
                                    ><i class="bi" :class="items.length ? 'bi-check-circle-fill' : 'bi-x-circle'"></i>
                                    {{ items.length }} item</span
                                >
                                <span class="chk" :class="emptySections === 0 ? 'ok' : 'no'"
                                    ><i
                                        class="bi"
                                        :class="emptySections === 0 ? 'bi-check-circle-fill' : 'bi-x-circle'"
                                    ></i>
                                    {{
                                        emptySections === 0
                                            ? 'tak ada section kosong'
                                            : emptySections + ' section kosong'
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="review-actions">
                            <button class="stitch-btn secondary" @click="gotoStep(2)">
                                <i class="bi bi-arrow-left"></i> Struktur
                            </button>
                            <button
                                class="stitch-btn primary"
                                :disabled="!canPublish"
                                :title="publishHint"
                                @click="ubahStatus('PUBLISHED')"
                            >
                                <i class="bi bi-rocket-takeoff"></i>
                                {{
                                    current.Status === 'PUBLISHED'
                                        ? `Publish Pembaruan v${nextVersiPaket}`
                                        : 'Publish Dokumen v1'
                                }}
                            </button>
                            <button
                                v-if="
                                    current.Status === 'PUBLISHED' &&
                                    ((pendingChanges.total || 0) > 0 ||
                                        ((pendingChanges.summary || {}).draft_item_versions || 0) > 0)
                                "
                                class="stitch-btn secondary"
                                @click="discardWorkingDraft"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i> Buang Draft Revisi
                            </button>
                            <!-- Pasangannya untuk paket yang BELUM terbit. Paket
                                 terbit bisa membuang salinan kerjanya; paket draft
                                 tak punya salinan itu, jadi yang ditawarkan di sini
                                 mengosongkan isinya. Namanya sengaja berbeda: ia
                                 tidak mengembalikan keadaan sebelumnya. -->
                            <button
                                v-if="current.Status !== 'PUBLISHED' && (sections.length || items.length)"
                                class="stitch-btn secondary"
                                @click="kosongkanPaket"
                            >
                                <i class="bi bi-eraser"></i> Kosongkan Paket
                            </button>
                            <button
                                v-if="current.Status === 'PUBLISHED'"
                                class="stitch-btn danger"
                                @click="ubahStatus('ARCHIVED')"
                            >
                                <i class="bi bi-archive"></i> Arsipkan
                            </button>
                        </div>
                    </div>

                    <div class="stitch-card stitch-pad mb-3">
                        <div class="sop-eyebrow">Catatan rilis</div>
                        <div class="small text-muted mb-2">
                            {{ pendingChanges.total || 0 }} perubahan terdeteksi. Snapshot dan diff paket dicatat saat
                            rilis.
                        </div>
                        <div v-if="(pendingChanges.errors || []).length" class="alert alert-warning py-2 mb-2">
                            <div v-for="(error, index) in pendingChanges.errors" :key="index">{{ error }}</div>
                        </div>
                        <div v-if="changeGroups.length" class="release-diff mb-3">
                            <div v-for="group in changeGroups" :key="group.key" class="diff-group">
                                <div class="diff-group-title">
                                    <i class="bi" :class="group.icon"></i>
                                    <span>{{ group.label }}</span>
                                    <b>{{ group.items.length }}</b>
                                </div>
                                <div class="diff-list">
                                    <div
                                        v-for="(change, index) in group.items"
                                        :key="group.key + '-' + index"
                                        class="diff-row"
                                    >
                                        <span class="diff-action" :class="'is-' + change.action">{{
                                            changeActionLabel(change.action)
                                        }}</span>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="diff-title text-truncate">
                                                {{ change.title || 'Perubahan' }}
                                            </div>
                                            <div v-if="change.subtitle" class="diff-sub text-truncate">
                                                {{ change.subtitle }}
                                            </div>
                                            <div v-if="change.fields && change.fields.length" class="diff-fields">
                                                <span v-for="field in change.fields" :key="field">{{ field }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else-if="current.Status === 'PUBLISHED'" class="release-diff-empty mb-3">
                            Belum ada draft perubahan yang siap masuk ke versi paket baru.
                        </div>
                        <textarea
                            v-model="releaseSummary"
                            class="sop-field"
                            rows="3"
                            maxlength="1000"
                            placeholder="Ringkas perubahan pada rilis paket ini (wajib)"
                        ></textarea>

                        <!-- Pilihan mayor/minor PINDAH ke konfirmasi publish.
                         Di sini admin memilihnya sebelum melihat siapa yang
                         terdampak; di konfirmasi, angkanya sudah di depan mata.
                         Membiarkannya di dua tempat berarti dua sumber
                         kebenaran untuk satu keputusan. -->
                    </div>

                    <div class="rv-devtoggle">
                        <button :class="{ active: reviewDevice === 'desktop' }" @click="reviewDevice = 'desktop'">
                            <i class="bi bi-display"></i> Desktop
                        </button>
                        <button :class="{ active: reviewDevice === 'mobile' }" @click="reviewDevice = 'mobile'">
                            <i class="bi bi-phone"></i> Mobile
                        </button>
                    </div>
                    <div class="review-stage">
                        <SopDeviceFrame :mode="reviewDevice">
                            <SopLivePreview
                                :paket="current"
                                :sections="sections"
                                :items="items"
                                :compact="reviewDevice === 'mobile'"
                            />
                        </SopDeviceFrame>
                    </div>
                </div>

                <!-- ══ Langkah 4 · RIWAYAT ══
                 Dulu modal di balik tombol kecil, dan karena itu praktis tak
                 pernah ditemukan. Audit trail yang harus dicari lebih dulu tak
                 dipakai saat dibutuhkan — justru saat ada yang ganjil dan orang
                 sedang buru-buru. -->
                <!-- ══ Langkah 4 · RIWAYAT & AUDIT TRAIL ══ -->
                <div v-show="step === 4" class="sop-fade-up">
                    <div class="stitch-card stitch-pad">
                        <div class="sop-panel-head mb-4 pb-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history text-primary fs-5"></i>
                                    <span class="ph-title mb-0">Riwayat &amp; Jejak Audit Paket</span>
                                    <span v-if="riwayat.total" class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-2">
                                        {{ riwayat.total }} Aktivitas
                                    </span>
                                </div>
                                <div class="rw-sub">Kronologi lengkap perubahan struktur section, item materi, penautan master dokumen, dan riwayat penerbitan.</div>
                            </div>

                            <!-- Saringan per jenis aksi -->
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <select
                                    v-if="riwayat.aksi.length > 1"
                                    v-model="riwayat.filter"
                                    class="sop-field rw-filter sm"
                                    @change="muatRiwayat(true)"
                                >
                                    <option value="">Semua Jenis Aktivitas</option>
                                    <option v-for="a in riwayat.aksi" :key="a" :value="a">{{ labelAksiRiwayat(a) }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="stitch-btn ghost sm"
                                    :disabled="riwayat.loading"
                                    title="Segarkan riwayat aktivitas"
                                    @click="muatRiwayat(true)"
                                >
                                    <span v-if="riwayat.loading" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-arrow-clockwise me-1"></i> Segarkan
                                </button>
                            </div>
                        </div>

                        <div v-if="riwayat.loading && !riwayat.rows.length" class="riwayat-loading">
                            <div class="d-flex flex-column align-items-center gap-2">
                                <span class="spinner-border text-primary"></span>
                                <span class="fw-semibold text-muted small">Memuat kronologi riwayat aktivitas…</span>
                            </div>
                        </div>
                        <EmptyState
                            v-else-if="!riwayat.rows.length"
                            compact
                            icon="bi-clock-history"
                            title="Belum ada aktivitas tercatat"
                            subtitle="Jejak Builder mulai dicatat sejak fitur audit dipasang; aktivitas baru akan otomatis terekam di sini."
                        />
                        <template v-else>
                            <div class="riwayat-list">
                                <article v-for="row in riwayat.rows" :key="row.Id_SOP_Log" class="riwayat-row">
                                    <div class="riwayat-rail">
                                        <span class="riwayat-dot" :class="riwayatTone(row.Aksi)">
                                            <i class="bi" :class="riwayatIcon(row.Aksi)"></i>
                                        </span>
                                        <span class="riwayat-line"></span>
                                    </div>
                                    <div class="riwayat-content">
                                        <div class="riwayat-card-inner">
                                            <div class="riwayat-meta">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="rw-avatar">
                                                        <i class="bi bi-person-fill"></i>
                                                    </span>
                                                    <strong class="rw-actor">{{ row.Oleh_Nama || row.Oleh_Username || 'Sistem' }}</strong>
                                                    <span class="rw-rel-time">{{ waktuRelatif(row.Created_At) }}</span>
                                                </div>
                                                <time class="rw-abs-time" :title="row.Created_At">{{ formatWaktuRiwayat(row.Created_At) }}</time>
                                            </div>

                                            <p class="rw-desc">{{ row.Keterangan }}</p>

                                            <!-- Diff Nilai Lama vs Baru -->
                                            <div v-if="row.Perubahan && row.Perubahan.length" class="rw-diff-box">
                                                <div class="rw-diff-title">
                                                    <i class="bi bi-code-square me-1"></i> Rincian Perubahan Nilai:
                                                </div>
                                                <ul class="rw-diff">
                                                    <li v-for="(p, i) in row.Perubahan" :key="i">
                                                        <span class="rw-diff-field">{{ p.field }}</span>
                                                        <span class="rw-diff-lama">{{ p.lama || '(kosong)' }}</span>
                                                        <i class="bi bi-arrow-right text-muted"></i>
                                                        <span class="rw-diff-baru">{{ p.baru || '(kosong)' }}</span>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="riwayat-tags">
                                                <span class="riwayat-action">{{ labelAksiRiwayat(row.Aksi) }}</span>
                                                <span v-if="row.Judul_Item" class="riwayat-ctx item">
                                                    <i class="bi bi-file-earmark-text"></i> {{ row.Judul_Item }}
                                                </span>
                                                <span
                                                    v-if="row.Versi_Ke !== null && row.Versi_Ke !== undefined"
                                                    class="riwayat-ctx ver tnum"
                                                >
                                                    <i class="bi bi-layers-fill"></i> {{ $vlabel(row.Versi_Ke) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div class="rw-kaki d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top mt-3">
                                <span class="text-muted small">
                                    Menampilkan <b class="text-dark tnum">{{ riwayat.rows.length }}</b> dari
                                    <b class="text-dark tnum">{{ riwayat.total }}</b> riwayat aktivitas.
                                </span>
                                <button
                                    v-if="riwayat.rows.length < riwayat.total"
                                    type="button"
                                    class="stitch-btn secondary sm"
                                    :disabled="riwayat.loading"
                                    @click="muatRiwayat(false)"
                                >
                                    <span v-if="riwayat.loading" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-arrow-down-circle me-1"></i>
                                    Muat Aktivitas Terdahulu
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Modal buat/ubah section -->
                <SopModal
                    :show="sectionModal.show"
                    :title="sectionModal.mode === 'edit' ? 'Ubah Section' : 'Section Baru'"
                    :icon="sectionModal.mode === 'edit' ? 'bi-pencil' : 'bi-collection'"
                    subtitle="Section mengelompokkan item dokumen."
                    @close="closeSectionModal"
                >
                    <div v-if="sectionModal.saving" class="editor-loading-overlay">
                        <div class="d-flex flex-column align-items-center justify-content-center p-3 text-center">
                            <div
                                class="spinner-border text-primary mb-2"
                                role="status"
                                style="width: 2rem; height: 2rem"
                            ></div>
                            <span class="fw-bold text-dark small">
                                {{ sectionModal.mode === 'edit' ? 'Menyimpan perubahan section...' : 'Membuat section baru...' }}
                            </span>
                            <span class="text-muted small">Harap tunggu sebentar</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="sop-label">Nama section *</label>
                        <input
                            v-model="sectionModal.Nama_Section"
                            class="sop-field"
                            maxlength="200"
                            :disabled="sectionModal.saving"
                            placeholder="mis. Persiapan"
                            @keyup.enter="submitSection"
                        />
                    </div>
                    <div>
                        <label class="sop-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                        <textarea
                            v-model="sectionModal.Deskripsi"
                            class="sop-field"
                            rows="3"
                            maxlength="500"
                            :disabled="sectionModal.saving"
                            placeholder="Ringkas isi/ tujuan section ini"
                        ></textarea>
                        <div class="sec-desc-count">{{ (sectionModal.Deskripsi || '').length }}/500</div>
                    </div>
                    <template #footer>
                        <button class="stitch-btn secondary" :disabled="sectionModal.saving" @click="closeSectionModal">
                            Batal
                        </button>
                        <button class="stitch-btn primary" :disabled="sectionModal.saving" @click="submitSection">
                            <span v-if="sectionModal.saving" class="spinner-border spinner-border-sm"></span>
                            {{ sectionModal.saving ? (sectionModal.mode === 'edit' ? 'Menyimpan...' : 'Membuat...') : (sectionModal.mode === 'edit' ? 'Simpan' : 'Buat') }}
                        </button>
                    </template>
                </SopModal>

                <SopCopyItemToPaketModal
                    :show="copyToPaketOpen"
                    :source-item="copyToPaketSource"
                    @close="copyToPaketOpen = false"
                    @copied="onItemCopied"
                />
            </template>
        </template>

        <!-- Konfirmasi publish paket: memperlihatkan revisi dokumen yang ikut
             terbit BERIKUT paket lain yang menyajikannya. -->
        <SopPublishPaketPratinjau
            :show="publishPratinjau"
            :paket="current ? current.Id_SOP_Paket : null"
            :mengirim="publishMengirim"
            @close="publishPratinjau = false"
            @terbitkan="terbitkanDariPratinjau"
        />
        <!--
            DI LUAR percabangan mode — SENGAJA.

            Sempat dipasang di dalam blok mode DAFTAR, sehingga di mode EDITOR
            komponennya tak pernah dirender: menekan Duplikat atau Hapus item
            menyalakan `konfirmasi.buka` pada sesuatu yang tak ada, tak ada
            modal yang muncul, dan Promise-nya menggantung selamanya. Tombol
            yang tak melakukan apa-apa, tanpa satu pun galat — persis jebakan
            yang sama dengan KontenPickerModal yang bersarang di dalam blok
            kandidat konten.
        -->
        <SopKonfirmasi
            :show="konfirmasi.buka"
            :judul="konfirmasi.judul"
            :subjudul="konfirmasi.subjudul"
            :pesan="konfirmasi.pesan"
            :statistik="konfirmasi.statistik"
            :catatan="konfirmasi.catatan"
            :teks-setuju="konfirmasi.teksSetuju"
            :teks-sibuk="konfirmasi.teksSibuk"
            :teks-batal="konfirmasi.teksBatal || 'Batal'"
            :tanpa-batal="!!konfirmasi.tanpaBatal"
            :ikon-tombol="konfirmasi.ikonTombol"
            :ragam="konfirmasi.ragam"
            :sibuk="konfirmasi.sibuk"
            :memuat-statistik="!!konfirmasi.memuatStatistik"
            @update:show="batalkanKonfirmasi"
            @setuju="jalankanKonfirmasi"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisHeader,
    HcisButton,
    HcisBadge,
    HcisFilterBar,
    HcisIcon,
    HcisTabs,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
} from '@/components/ui';
import RailSumbu from '../../Shared/components/listing/RailSumbu.vue';
import PohonFolderSop from '../../Shared/components/listing/PohonFolderSop.vue';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import ItemTypeIcon from '../../Shared/components/ui/ItemTypeIcon.vue';
import EmptyState from '../../Shared/components/ui/EmptyState.vue';
import SopPublishPaketPratinjau from '../../Shared/components/ui/SopPublishPaketPratinjau.vue';
import SopCoverPicker from '../../Shared/components/ui/SopCoverPicker.vue';
import SopLivePreview from '../../Shared/components/ui/SopLivePreview.vue';
import SopVersiRingkas from '../../Shared/components/ui/SopVersiRingkas.vue';
import SopKuisVersiPanel from '../../Shared/components/ui/SopKuisVersiPanel.vue';
import SopDeviceFrame from '../../Shared/components/ui/SopDeviceFrame.vue';
import SopModal from '../../Shared/components/ui/SopModal.vue';
import SopKonfirmasi from '../../Shared/components/ui/SopKonfirmasi.vue';
import SopCopyItemToPaketModal from '../../Shared/components/ui/SopCopyItemToPaketModal.vue';
import SopPaketCard from '../../Shared/components/ui/SopPaketCard.vue';
import ItemFields from '../../Shared/components/ui/ItemFields.vue';
import { TIPE_ITEM, temaAksen, statusPaketLabel, isFileTipe } from '../../Shared/support/constants.js';
import { daftarPrioritas, metaPrioritas, setPrioritas } from '../../Shared/support/prioritas.js';
import {
    EMBER_ESTIMASI,
    EMBER_TERBIT,
    OPSI_DISTRIBUSI,
    OPSI_SORT,
    OPSI_STATUS,
    SUMBU_PAKET,
    adaSaringanAktif,
    bangunGrupPaket,
    paramsPaket,
    railAktif,
    saringanKosong,
    sumbuPaketDari,
} from './sumbuPaket.js';
import { payloadItem } from '../../Shared/support/itemPayload.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/builder';

/**
 * Document Builder v2 — wizard: Identitas → Struktur → Tinjau & Publish.
 * Paket → Section → Item; kandidat versi aktif hanya saat publish paket.
 */
export default {
    name: 'SopBuilder',
    /* Daftar tingkat prioritas dari server (master, DDL 35). Diisi ke registry
       supaya lencana di seluruh anak komponen memakai nama & warna yang sama —
       termasuk tingkat yang ditambahkan admin sesudah layar ini ditulis. */
    props: {
        prioritas: { type: Array, default: () => [] },
        tipeItemAktif: { type: Array, default: () => [] },
        departemenOpsi: { type: Array, default: () => [] },
        jenisOpsi: { type: Array, default: () => [] },
        apqcOpsi: { type: Array, default: () => [] },
    },
    components: {
        HcisHeader,
        HcisButton,
        HcisBadge,
        HcisFilterBar,
        HcisIcon,
        HcisTabs,
        HcisSelect,
        HcisSelectOption,
        HcisSkeleton,
        RailSumbu,
        PohonFolderSop,
        SopPublishPaketPratinjau,
        StatusPill,
        ItemTypeIcon,
        EmptyState,
        SopCoverPicker,
        SopLivePreview,
        SopVersiRingkas,
        SopKuisVersiPanel,
        SopDeviceFrame,
        SopModal,
        SopKonfirmasi,
        SopCopyItemToPaketModal,
        SopPaketCard,
        ItemFields,
    },
    mixins: [pageMeta],
    data() {
        return {
            mode: 'list',
            tiketKonfirmasi: 0,
            /* Overlay draft sudah bisa membawa tautan Dokumen (DDL 33)?
               Sebelum skrip itu dijalankan, item baru di paket terbit tetap
               mengetik judul — menawarkan pemilih dokumen di sana akan
               menjanjikan sesuatu yang tak bisa disimpan. */
            dukungDokumenDraft: false,
            /* Satu wadah untuk semua konfirmasi halaman ini — menggantikan
               ElMessageBox, yang membawa bahasa visualnya sendiri ke tindakan
               yang justru paling perlu terasa serius. */
            konfirmasi: {
                buka: false,
                judul: '',
                subjudul: '',
                pesan: '',
                statistik: [],
                catatan: '',
                teksSetuju: 'Ya',
                teksBatal: 'Batal',
                tanpaBatal: false,
                teksSibuk: '',
                ikonTombol: '',
                ragam: 'primary',
                sibuk: false,
                aksi: null,
                galat: 'Gagal memproses',
                selesai: null,
                memuatStatistik: false,
                /* Penanda modal yang SEDANG terbuka. Angka dampak tiba
                   belakangan; tanpa tiket, jawaban yang datang telat bisa
                   menempel pada konfirmasi yang sama sekali lain — mis. admin
                   sudah menutupnya lalu menekan Hapus pada item berbeda. */
                tiket: 0,
            },
            step: 1,
            steps: [
                { n: 1, label: 'Identitas', icon: 'bi-card-heading' },
                { n: 2, label: 'Struktur', icon: 'bi-diagram-3' },
                { n: 3, label: 'Tinjau & Publish', icon: 'bi-eye' },
                /* Bukan langkah menuju rilis, melainkan tempat menengok ke
                   belakang — karena itu ditandai referensi dan tak pernah
                   dihitung "selesai". Menaruhnya di deretan yang sama membuatnya
                   ketemu; menomorinya seperti langkah lain akan mengajarkan
                   urutan yang salah. */
                { n: 4, label: 'Riwayat', icon: 'bi-clock-history', referensi: true },
            ],
            viewMode: 'grid',
            listFilters: saringanKosong(),
            /* Sumbu pengelompokan rail. Prioritas bawaannya, tapi bukan
               satu-satunya: status rilis dan mode distribusi menjawab
               pertanyaan yang berbeda, dan yang benar tergantung apa yang
               sedang dikerjakan admin. */
            sumbuAktif: 'prioritas',
            grupRailData: [],
            railMode: 'sumbu',
            treeData: [],
            /**
             * Keadaan menunggu, satu bendera per JENIS penantian.
             *
             * Dipisah karena tiap penantian punya bentuk tampilnya sendiri dan
             * durasi yang berbeda: `editor` menggantikan seluruh layar dengan
             * skeleton, `save` menutup satu editor item, `struktur` meredupkan
             * daftar tanpa menyembunyikannya, `urutan` cuma sebuah pil di kepala
             * panel. Satu bendera untuk semuanya akan memaksa penantian
             * setengah detik memakai tampilan milik penantian tiga detik.
             */
            loading: { list: false, save: false, editor: false, struktur: false, urutan: false },
            paketList: [],
            pagination: { current_page: 1, per_page: 10, total_page: 1, total_data: 0 },
            statusCountsData: { ALL: 0, DRAFT: 0, PUBLISHED: 0, ARCHIVED: 0 },
            current: {},
            sections: [],
            items: [],
            paketForm: {},
            paketFormPristine: null,
            coverModel: '',
            itemForm: {},
            itemFormPristine: null,
            openItemId: null,
            adding: false,
            addingSection: null,
            previewOpen: false,
            reviewDevice: 'desktop',
            paketVersi: null,
            nextVersiPaket: 1,
            pendingChanges: { total: 0, summary: {} },
            // Sentuhan terakhir pada working draft oleh admin LAIN (null bila sendirian).
            draftActivity: null,
            releaseSummary: '',
            /* Default MAYOR, selalu. Admin yang tak menyentuh pilihan ini tak
               boleh diam-diam menghasilkan "tak usah baca". */
            publishPratinjau: false,
            publishMengirim: false,
            dragId: null,
            dragOverId: null,
            dragSectionId: null,
            dragOverSectionId: null,
            collapsedSections: {},
            sectionModal: { show: false, mode: 'create', id: null, Nama_Section: '', Deskripsi: '', saving: false },
            riwayat: { loading: false, rows: [], total: 0, aksi: [], filter: '' },
            copyToPaketOpen: false,
            copyToPaketSource: null,
            // Hashid paket/section/item dibuat dari PK tabel masing-masing lewat connection yang
            // sama, jadi section PK 7 dan item PK 7 menghasilkan string identik. busyId karena itu
            // disimpan ber-scope ("item:xxx") supaya spinner tidak nyasar ke baris lain.
            busyId: null,
        };
    },
    computed: {
        editorHeaderTitle() {
            if (this.current && this.current.Id_SOP_Paket) {
                return this.current.Nama_Paket || 'Paket Dokumen';
            }
            return this.paketForm?.Nama_Paket || 'Buat Paket Dokumen Baru';
        },
        editorHeaderKicker() {
            if (this.current && this.current.Id_SOP_Paket) {
                return 'Tata Kelola Dokumen · Editor Paket';
            }
            return 'Tata Kelola Dokumen · Paket Baru';
        },
        editorHeaderDescription() {
            if (this.step === 1) {
                return 'Lengkapi identitas dasar, gerbang absensi, dan kebijakan distribusi paket dokumen.';
            }
            if (this.step === 2) {
                return 'Kelola section & urutan item dokumen, tautkan dokumen terkendali, dan pantau kelengkapan materi.';
            }
            if (this.step === 3) {
                return 'Uji coba pratinjau tampilan karyawan, periksa checklist kesiapan, dan terbitkan rilis versi.';
            }
            if (this.step === 4) {
                return 'Jejak log aktivitas & riwayat perubahan paket dokumen yang tercatat di sistem.';
            }
            return this.current?.Deskripsi || 'Kelola paket dokumen operasional: susun section, versi dokumen, dan tinjau tampilan karyawan.';
        },
        editorStepsTabs() {
            return [
                {
                    key: 1,
                    label: '1. Identitas Paket',
                    icon: 'card-heading',
                    disabled: false,
                },
                {
                    key: 2,
                    label: '2. Struktur & Materi',
                    icon: 'diagram-3',
                    disabled: !this.stepReachable(2) || this.loading.editor,
                },
                {
                    key: 3,
                    label: '3. Tinjau & Publish',
                    icon: 'eye',
                    disabled: !this.stepReachable(3) || this.loading.editor,
                },
                {
                    key: 4,
                    label: '4. Riwayat & Audit',
                    icon: 'clock-history',
                    badge: this.riwayat?.total || null,
                    badgeVariant: 'neutral',
                    disabled: !this.stepReachable(4) || this.loading.editor,
                },
            ];
        },
        hasActiveFilter() {
            return adaSaringanAktif(this.listFilters);
        },
        /**
         * Daftar struktur sedang menunggu data baru.
         *
         * DIAM pada dua keadaan, dan alasannya sama: satu penantian cukup
         * ditandai satu kali.
         *
         *  - Editor item sudah memasang overlay-nya sendiri (`save`). Keduanya
         *    menandai penantian yang SAMA — simpan item lalu tarik ulang
         *    struktur — dan dua lapisan membuat layar tampak sibuk dua kali
         *    lebih lama daripada kenyataannya.
         *  - Belum ada satu pun section di layar. Di sana kerangkanya SUDAH
         *    jadi penandanya; meredupkan kerangka lalu menaruh pil di atasnya
         *    hanya menumpuk dua ucapan untuk satu hal.
         */
        strukturSibuk() {
            return this.loading.struktur && !this.loading.save && this.sections.length > 0;
        },
        /** Struktur belum pernah terisi dan sedang dimuat: tampilkan kerangka, bukan "belum ada". */
        strukturKosongMemuat() {
            return this.loading.struktur && !this.sections.length;
        },
        /** Sumbu yang ditawarkan pemindah di atas rail. */
        sumbuTersedia() {
            return SUMBU_PAKET;
        },
        sumbuMeta() {
            return sumbuPaketDari(this.sumbuAktif);
        },
        /** Kelompok rail + totalnya, dari hitungan server. */
        railSebaran() {
            return bangunGrupPaket(this.grupRailData, this.sumbuAktif);
        },
        railGrup() {
            return this.railSebaran.grup;
        },
        /**
         * Angka pada entri "Semua paket".
         *
         * Penjumlahan seluruh kelompok, BUKAN `pagination.total_data`. Sebaran
         * rail mengabaikan saringan sumbunya sendiri sementara daftarnya tidak,
         * jadi begitu satu kelompok dipilih kedua angka itu memang berbeda —
         * dan yang benar untuk entri "semua" adalah yang mengabaikannya.
         */
        railTotal() {
            return this.railSebaran.total;
        },
        /**
         * Entri rail yang menyala — kosong bila sumbunya disaring >1 nilai.
         *
         * Rail hanya bisa menyalakan satu entri. Menyalakan yang pertama dari
         * tiga pilihan akan membacakan "sedang melihat Prioritas A" pada daftar
         * yang sebetulnya berisi tiga tingkat.
         */
        railNilaiAktif() {
            return railAktif(this.listFilters, this.sumbuAktif);
        },
        /** Label kelompok yang sedang menyala, untuk chip "Disaring:". */
        railLabelAktif() {
            const g = this.railGrup.find((x) => x.nilai === this.railNilaiAktif);

            return g ? g.nama : '';
        },
        /**
         * Berapa nilai yang dipilih pada sumbu rail. Dipakai untuk menyebutkan
         * keadaan "beberapa dipilih" — yang kalau didiamkan membuat rail
         * tampak tak menyaring apa pun padahal daftarnya sudah menyempit.
         */
        railJumlahDipilih() {
            const v = this.listFilters[this.sumbuMeta.kunciSaringan];

            return Array.isArray(v) ? v.length : 0;
        },
        /**
         * Nilai chip status yang menyala.
         *
         * Diturunkan dari saringan, bukan disimpan terpisah. Bentuk lama
         * menyimpan `statusFilter` di samping `listFilters.status` dan
         * menyelaraskannya di lima tempat; satu tempat yang terlewat cukup
         * untuk membuat chip menyala pada status yang tak sedang disaring.
         * Chip padam (null) begitu status disaring lebih dari satu nilai —
         * keadaan itu memang tak bisa diwakili chip mana pun.
         */
        statusFilter() {
            const v = this.listFilters.status || [];
            if (!v.length) return 'ALL';

            return v.length === 1 ? v[0] : null;
        },
        builderQuickChips() {
            return [
                {
                    value: 'ALL',
                    label: 'Semua Paket',
                    icon: 'grid-1x2-fill',
                    color: 'brand',
                    badge: this.statusCountsData.ALL || this.pagination.total_data || 0,
                },
                {
                    value: 'DRAFT',
                    label: 'Draft',
                    icon: 'pencil-square',
                    color: 'warning',
                    badge: this.statusCountsData.DRAFT || 0,
                },
                {
                    value: 'PUBLISHED',
                    label: 'Terbit',
                    icon: 'check-circle-fill',
                    color: 'success',
                    badge: this.statusCountsData.PUBLISHED || 0,
                },
                {
                    value: 'ARCHIVED',
                    label: 'Arsip',
                    icon: 'archive-fill',
                    color: 'neutral',
                    badge: this.statusCountsData.ARCHIVED || 0,
                },
            ];
        },
        /**
         * Ladang saringan sheet.
         *
         * SUMBU-SUMBUNYA BERGANDA. Pertanyaan yang benar-benar diajukan admin
         * bukan "paket prioritas apa" melainkan "paket Kritis DAN Tinggi yang
         * mana yang belum terbit" — dan pilihan tunggal memaksanya membuka
         * daftar itu dua kali lalu membandingkannya di kepala.
         *
         * Karena berganda, TIDAK ADA lagi entri "Semua …" di dalam pilihannya:
         * daftar kosong sudah berarti semua, dan sebuah entri bernilai '' yang
         * ikut tercentang di sebelah dua pilihan lain hanya membingungkan.
         *
         * Prioritas dibaca dari master (`daftarPrioritas`), bukan dari tiga
         * nilai yang ditulis keras seperti sebelumnya — tingkat yang ditambahkan
         * admin sesudah layar ini ditulis dulu tak pernah bisa disaring.
         */
        builderFiltersConfig() {
            return [
                {
                    key: 'departemen',
                    label: 'Departemen Penanggung Jawab',
                    type: 'select',
                    placeholder: 'Semua departemen',
                    multiple: true,
                    options: (this.departemenOpsi || []).map((d) => ({
                        label: d.label || d.Nama_Departemen,
                        value: String(d.label || d.Nama_Departemen),
                    })),
                },
                {
                    key: 'jenis',
                    label: 'Jenis Dokumen',
                    type: 'select',
                    placeholder: 'Semua jenis',
                    multiple: true,
                    options: (this.jenisOpsi || []).map((j) => ({
                        label: j.label || j.Nama_Jenis,
                        value: String(j.label || j.Nama_Jenis),
                    })),
                },
                {
                    key: 'apqc',
                    label: 'Klasifikasi Proses APQC',
                    type: 'select',
                    placeholder: 'Semua proses',
                    multiple: true,
                    options: (this.apqcOpsi || []).map((a) => ({
                        label: a.kode ? `${a.kode} · ${a.label || a.Nama_Proses}` : (a.label || a.Nama_Proses),
                        value: String(a.label || a.Nama_Proses),
                    })),
                },
                {
                    key: 'prioritas',
                    label: 'Tingkat Prioritas',
                    type: 'select',
                    placeholder: 'Semua prioritas',
                    multiple: true,
                    options: daftarPrioritas().map((p) => ({ label: p.label, value: p.kode })),
                },
                {
                    key: 'status',
                    label: 'Status Rilis',
                    type: 'select',
                    placeholder: 'Semua status',
                    multiple: true,
                    options: OPSI_STATUS,
                },
                {
                    key: 'distribusi',
                    label: 'Mode Distribusi',
                    type: 'select',
                    placeholder: 'Semua mode',
                    multiple: true,
                    options: OPSI_DISTRIBUSI,
                },
                {
                    key: 'terbit',
                    label: 'Tanggal Terbit',
                    type: 'select',
                    placeholder: 'Kapan saja',
                    options: EMBER_TERBIT,
                    hint: 'Keadaan, bukan tanggal — termasuk yang belum pernah terbit.',
                },
                {
                    /* Hidup berdampingan dengan ember di atas, tidak
                       menggantikannya: "yang terbit bulan ini" dan "yang terbit
                       antara 3 dan 17 Maret" dua-duanya pertanyaan nyata.
                       Keduanya di-AND-kan di server. */
                    key: 'tgl_publish',
                    label: 'Rentang Tanggal Terbit',
                    type: 'daterange',
                    placeholder: 'Rentang khusus',
                },
                {
                    key: 'estimasi',
                    label: 'Perkiraan Waktu Baca',
                    type: 'select',
                    placeholder: 'Berapa pun',
                    options: EMBER_ESTIMASI,
                },
                {
                    key: 'absensi',
                    label: 'Absensi',
                    type: 'switch',
                    activeText: 'Hanya paket berabsensi',
                    hint: 'Paket yang menuntut kehadiran, bukan sekadar dibaca.',
                },
                {
                    key: 'sort',
                    label: 'Urutkan Berdasarkan',
                    type: 'select',
                    clearable: false,
                    options: OPSI_SORT,
                },
            ];
        },
        /**
         * Tipe yang boleh dipakai, dari sakelar di Setup System (server).
         *
         * Cadangan lokalnya bukan "semua tipe": ABSENSI memang sudah lama
         * ditolak server ("Absensi diatur lewat toggle paket, bukan item").
         * Kalau prop-nya tak sampai — halaman dirender di luar controllernya,
         * atau uji komponen — palet harus tetap berhenti menawarkannya.
         */
        tipeList() {
            if (this.tipeItemAktif.length) return this.tipeItemAktif;

            return Object.keys(TIPE_ITEM).filter((t) => t !== 'ABSENSI');
        },
        /**
         * Palet untuk item yang SUDAH ADA — selalu memuat tipe item itu sendiri.
         *
         * Mematikan sebuah tipe hanya menutup pembuatan baru. Tanpa penjagaan
         * ini, membuka item lama bertipe yang baru saja dimatikan menampilkan
         * palet yang tak punya satu pun chip menyala: layar berkata tipe item
         * ini "tak ada", padahal itemnya jelas ada dan tetap dibaca karyawan.
         */
        tipeListEdit() {
            const sekarang = this.itemForm?.Tipe_Item;
            if (!sekarang || this.tipeList.includes(sekarang)) return this.tipeList;

            return [...this.tipeList, sekarang];
        },
        itemDirty() {
            return (
                (this.adding || !!this.openItemId) &&
                this.itemFormPristine !== null &&
                this.formSig(this.itemForm) !== this.itemFormPristine
            );
        },
        /* editTypeChanged / editOrigFile / editNowFile / editTypeToFile DICABUT
           (27 Agu 2026). Keempatnya melayani satu kemampuan: mengubah item
           non-berkas menjadi item berkas dari dalam Builder, lengkap dengan
           pemilih "Konten Versi 1". Berkas kini selalu datang dari versi
           dokumennya, jadi jalur itu tak punya tempat menyimpan apa pun. */

        /**
         * Tipe item TERKUNCI begitu ia bertaut dokumen dan sudah tersimpan.
         *
         * Sejak Fase 6 tipe item adalah cerminan tipe dokumennya, dan
         * terapkanDokumen() di server menolak setiap ketidakcocokan. Jadi
         * kuncian ini tidak menghilangkan kemampuan apa pun — ia cuma berhenti
         * menawarkan sesuatu yang hasilnya sudah pasti penolakan.
         *
         * Item BARU tak ikut terkunci: dokumennya belum dipilih, jadi tipe
         * itulah yang justru menentukan dokumen mana yang boleh dipilih.
         */
        /**
         * Dokumen yang sudah dipakai item LAIN di paket ini.
         *
         * Item yang sedang dibuka dikecualikan: dokumennya sendiri tentu "sudah
         * dipakai" — olehnya — dan mematikannya di pemilih akan membuat admin
         * mengira dokumen yang sedang ia gunakan bermasalah.
         */
        dokumenDipakaiPaket() {
            const sekarang = this.itemForm?.id || this.openItemId;

            return this.items
                .filter((x) => x.Id_SOP_Dokumen && x.Id_SOP_Paket_Item !== sekarang)
                .map((x) => x.Id_SOP_Dokumen);
        },
        tipeTerkunci() {
            return !!this.itemForm?.id && !!this.itemForm?.Id_SOP_Dokumen;
        },
        /**
         * Paket terbit → item baru ditulis ke overlay draft.
         *
         * Tabel itu tak punya kolom Id_SOP_Dokumen; dokumennya baru dibuat saat
         * paket diterbitkan. Karena itu jalur tersebut masih meminta judul dan
         * konten kandidat, sedangkan paket draft meminta Dokumen.
         */
        paketPublished() {
            return this.current?.Status === 'PUBLISHED';
        },
        paketDirty() {
            return (
                this.mode === 'editor' &&
                this.paketFormPristine !== null &&
                this.formSig(this.paketForm) !== this.paketFormPristine
            );
        },
        wajibCount() {
            return this.items.filter((x) => x.Flag_Wajib === 'Y').length;
        },
        /** Dasar peringatan "kuis pada dokumen publik" di form distribusi. */
        adaItemKuis() {
            return this.items.some((x) => x.Tipe_Item === 'KUIS');
        },
        emptySections() {
            return this.sections.filter((s) => this.itemsOf(s.Id_SOP_Section).length === 0).length;
        },
        canPublish() {
            if (!(this.sections.length > 0 && this.items.length > 0 && this.emptySections === 0)) return false;
            const s = this.pendingChanges.summary || {};
            if (this.current.Status === 'PUBLISHED') {
                return this.pendingChanges.can_publish !== undefined
                    ? !!this.pendingChanges.can_publish
                    : (this.pendingChanges.total || 0) > 0;
            }
            return !(s.draft_item_versions > 0);
        },
        readyPct() {
            const checks = [this.sections.length > 0, this.items.length > 0, this.emptySections === 0];
            return Math.round((checks.filter(Boolean).length / checks.length) * 100);
        },
        publishHint() {
            if (!this.sections.length) return 'tambahkan minimal 1 section';
            if (!this.items.length) return 'tambahkan minimal 1 item';
            if (this.emptySections > 0) return 'masih ada section kosong';
            if ((this.pendingChanges.errors || []).length) return this.pendingChanges.errors[0];
            const s = this.pendingChanges.summary || {};
            if (this.current.Status === 'PUBLISHED' && !(this.pendingChanges.total > 0))
                return 'belum ada perubahan siap rilis';
            if (this.current.Status !== 'PUBLISHED' && s.draft_item_versions > 0)
                return 'ada item yang dokumennya belum punya versi terbit';
            return '';
        },
        /**
         * Peta id baris -> aksi ('added' | 'changed'), dari diff yang sama persis
         * dengan yang menyetir gerbang publish. Sengaja hanya diisi untuk paket
         * PUBLISHED: pada paket DRAFT semuanya memang belum terbit, jadi menandai
         * setiap baris "BARU" hanya menenggelamkan sinyal.
         */
        changeMap() {
            if (this.current.Status !== 'PUBLISHED') return {};
            const details = this.pendingChanges.details || {};
            const map = {};
            ['sections', 'items', 'versions'].forEach((key) => {
                (Array.isArray(details[key]) ? details[key] : []).forEach((row) => {
                    if (row && row.id && !map[row.id]) map[row.id] = row.action;
                });
            });
            return map;
        },
        /** Chip ringkas di header panel Struktur. null = tidak ada yang perlu diberitahukan. */
        strukturChip() {
            const s = this.pendingChanges.summary || {};
            if (this.current.Status === 'PUBLISHED') {
                const total = this.pendingChanges.total || 0;
                if (!total) return null;
                const details = this.pendingChanges.details || {};
                const dihapus = (Array.isArray(details.items) ? details.items : []).filter(
                    (r) => r && r.action === 'removed',
                ).length;
                return {
                    tone: 'warn',
                    icon: 'bi-record-circle-fill',
                    text: `${total} belum dipublish` + (dihapus ? ` · ${dihapus} dihapus` : ''),
                };
            }
            if ((s.draft_item_versions || 0) > 0) {
                return {
                    tone: 'warn',
                    icon: 'bi-exclamation-triangle-fill',
                    text: `${s.draft_item_versions} item belum siap rilis`,
                };
            }
            // Bersandar pada canPublish, bukan sekadar "tak ada versi Draft": paket tanpa
            // section atau bersection kosong juga akan ditolak gerbang publish.
            return this.canPublish ? { tone: 'ok', icon: 'bi-check-circle-fill', text: 'Siap dipublish' } : null;
        },
        changeGroups() {
            const details = this.pendingChanges.details || {};
            const defs = [
                { key: 'package', label: 'Identitas Paket', icon: 'bi-card-heading' },
                { key: 'sections', label: 'Section', icon: 'bi-collection' },
                { key: 'items', label: 'Item Dokumen', icon: 'bi-list-check' },
                { key: 'versions', label: 'Versi Konten', icon: 'bi-layers' },
            ];
            return defs
                .map((def) => ({ ...def, items: Array.isArray(details[def.key]) ? details[def.key] : [] }))
                .filter((group) => group.items.length);
        },
        heroBg() {
            const t = this.theme(this.current.Nama_Paket);
            if (this.current.Cover_Url)
                return {
                    backgroundImage: `url(${this.current.Cover_Url})`,
                    backgroundPosition: this.current.Cover_Position || 'center',
                };
            return { background: `linear-gradient(135deg, ${t.color}, ${t.color}cc)` };
        },
        /**
         * Rangkuman seluruh saringan aktif untuk bilah indikator saringan modern.
         */
        activeFilterChips() {
            const chips = [];
            const f = this.listFilters || {};

            // Pencarian keyword
            if (f.search && String(f.search).trim()) {
                chips.push({
                    key: 'search',
                    label: 'Cari',
                    value: `"${String(f.search).trim()}"`,
                    icon: 'search',
                    clear: () => {
                        this.listFilters.search = '';
                        this.muatUlangDaftar();
                    },
                });
            }

            // Status (hanya bila disaring khusus, misal DRAFT/PUBLISHED/ARCHIVED)
            if (f.status && f.status.length) {
                const valStr = f.status
                    .map((v) => (OPSI_STATUS.find((o) => o.value === v) || {}).label || v)
                    .join(', ');
                chips.push({
                    key: 'status',
                    label: 'Status',
                    value: valStr,
                    icon: 'check2-circle',
                    clear: () => {
                        this.listFilters.status = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Departemen
            if (f.departemen && f.departemen.length) {
                chips.push({
                    key: 'departemen',
                    label: 'Departemen',
                    value: f.departemen.join(', '),
                    icon: 'building',
                    clear: () => {
                        this.listFilters.departemen = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Jenis Dokumen
            if (f.jenis && f.jenis.length) {
                chips.push({
                    key: 'jenis',
                    label: 'Jenis Dokumen',
                    value: f.jenis.join(', '),
                    icon: 'journal-text',
                    clear: () => {
                        this.listFilters.jenis = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Prioritas
            if (f.prioritas && f.prioritas.length) {
                chips.push({
                    key: 'prioritas',
                    label: 'Prioritas',
                    value: f.prioritas.map((v) => metaPrioritas(v).label || v).join(', '),
                    icon: 'flag-fill',
                    clear: () => {
                        this.listFilters.prioritas = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // APQC
            if (f.apqc && f.apqc.length) {
                chips.push({
                    key: 'apqc',
                    label: 'APQC',
                    value: f.apqc.join(', '),
                    icon: 'diagram-2',
                    clear: () => {
                        this.listFilters.apqc = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Distribusi
            if (f.distribusi && f.distribusi.length) {
                chips.push({
                    key: 'distribusi',
                    label: 'Distribusi',
                    value: f.distribusi.map((v) => (OPSI_DISTRIBUSI.find((o) => o.value === v) || {}).label || v).join(', '),
                    icon: 'people-fill',
                    clear: () => {
                        this.listFilters.distribusi = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Estimasi
            if (f.estimasi) {
                const est = EMBER_ESTIMASI.find((o) => o.value === f.estimasi);
                chips.push({
                    key: 'estimasi',
                    label: 'Estimasi',
                    value: est ? est.label : f.estimasi,
                    icon: 'clock-history',
                    clear: () => {
                        this.listFilters.estimasi = '';
                        this.muatUlangDaftar();
                    },
                });
            }

            // Rentang Terbit (ember)
            if (f.terbit) {
                const tb = EMBER_TERBIT.find((o) => o.value === f.terbit);
                chips.push({
                    key: 'terbit',
                    label: 'Terbit',
                    value: tb ? tb.label : f.terbit,
                    icon: 'calendar-event',
                    clear: () => {
                        this.listFilters.terbit = '';
                        this.muatUlangDaftar();
                    },
                });
            }

            // Tanggal Terbit Rentang Picker
            if (f.tgl_publish && f.tgl_publish.length === 2 && (f.tgl_publish[0] || f.tgl_publish[1])) {
                chips.push({
                    key: 'tgl_publish',
                    label: 'Periode Terbit',
                    value: `${f.tgl_publish[0] || '...'} s/d ${f.tgl_publish[1] || '...'}`,
                    icon: 'calendar-range',
                    clear: () => {
                        this.listFilters.tgl_publish = [];
                        this.muatUlangDaftar();
                    },
                });
            }

            // Absensi
            if (f.absensi) {
                chips.push({
                    key: 'absensi',
                    label: 'Fitur',
                    value: 'Wajib Absensi',
                    icon: 'person-check-fill',
                    clear: () => {
                        this.listFilters.absensi = false;
                        this.muatUlangDaftar();
                    },
                });
            }

            return chips;
        },
        hasActiveFilters() {
            return this.activeFilterChips.length > 0;
        },
    },
    created() {
        setPrioritas(this.prioritas);
    },
    mounted() {
        try {
            const v = localStorage.getItem('sop_builder_view');
            if (v === 'grid' || v === 'list') {
                this.viewMode = v;
            }
            /* Sumbu dipulihkan lewat daftar yang dikenal, bukan dipasang apa
               adanya: nilai basi dari versi sebelumnya (atau yang disunting
               tangan) akan membuat `sumbuMeta` melempar saat render pertama. */
            const s = localStorage.getItem('sop_builder_sumbu');
            if (SUMBU_PAKET.some((x) => x.key === s)) {
                this.sumbuAktif = s;
            }
            const rm = localStorage.getItem('sop_builder_rail_mode');
            if (rm === 'sumbu' || rm === 'tree') {
                this.railMode = rm;
            }
        } catch (e) {
            /* noop */
        }
        this.fetchPaket(1);
        this.fetchCounts();
        this.restoreFromUrl();
        window.addEventListener('beforeunload', this.onBeforeUnload);
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.onBeforeUnload);
    },
    methods: {
        theme(n) {
            return temaAksen(n);
        },
        statusLabel(s) {
            return statusPaketLabel(s);
        },
        /* ---- guard perubahan belum tersimpan ---- */
        formSig(obj) {
            if (!obj) return '';
            const clone = { ...obj };
            delete clone.Konten_Nama;
            delete clone.Konten_Tipe; // key transien (label konten, bukan data)
            delete clone.Konten_Ext; // idem — hanya penentu ikon di pemilih
            return JSON.stringify(clone);
        },
        async guardDirty() {
            if (!this.itemDirty && !this.paketDirty) return true;
            return this.tanya('Perubahan yang belum disimpan akan hilang.', 'Perubahan belum disimpan', {
                confirmButtonText: 'Tinggalkan',
                cancelButtonText: 'Kembali',
                ragam: 'danger',
                ikonTombol: 'bi-box-arrow-right',
            });
        },
        onBeforeUnload(e) {
            if (this.itemDirty || this.paketDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        },
        /**
         * Konfirmasi + jalankan request dengan SPINNER pada tombol konfirmasi (dialog tetap
         * terbuka sampai request selesai) → user langsung dapat feedback, tak terasa nge-lag.
         * task() harus mengembalikan Promise. Return true bila sukses, false bila batal/gagal.
         */
        /**
         * Konfirmasi memakai modal MODUL INI, bukan ElMessageBox.
         *
         * Tanda tangannya sengaja dipertahankan (message, title, opts, task)
         * supaya empat pemanggil lama tak perlu ditulis ulang sekaligus — yang
         * berubah hanya apa yang muncul di layar. Pemanggil yang butuh angka
         * dampak memakai `opts.statistik` / `opts.catatan`.
         *
         * Tetap mengembalikan Promise<boolean> seperti sebelumnya.
         */
        confirmAndRun(message, title, opts, task) {
            return new Promise((resolve) => {
                this.konfirmasi = {
                    buka: true,
                    tiket: ++this.tiketKonfirmasi,
                    memuatStatistik: !!opts.memuatStatistik,
                    judul: title || 'Konfirmasi',
                    subjudul: opts.subjudul || '',
                    pesan: message,
                    statistik: opts.statistik || [],
                    catatan: opts.catatan || '',
                    teksSetuju: opts.confirmButtonText || 'Ya',
                    teksSibuk: opts.loadingText || 'Memproses…',
                    ikonTombol: opts.ikonTombol || '',
                    ragam: opts.ragam || (opts.type === 'error' ? 'danger' : 'primary'),
                    galat: opts.errorText || 'Gagal memproses',
                    sibuk: false,
                    aksi: task,
                    selesai: resolve,
                };
            });
        },
        /**
         * Tanya ya/tidak — tanpa tugas, hanya jawabannya.
         *
         * Empat konfirmasi di halaman ini memang cuma bertanya (tinggalkan
         * perubahan? ganti ujian? buka paket tujuan?) dan mengerjakan
         * lanjutannya sendiri. Membungkusnya jadi "task" hanya akan memaksa
         * alur yang tak cocok; yang dibutuhkan cukup Promise<boolean>.
         */
        tanya(message, title, opts = {}) {
            return new Promise((resolve) => {
                this.konfirmasi = {
                    buka: true,
                    tiket: ++this.tiketKonfirmasi,
                    memuatStatistik: false,
                    judul: title || 'Konfirmasi',
                    subjudul: opts.subjudul || '',
                    pesan: message,
                    statistik: opts.statistik || [],
                    catatan: opts.catatan || '',
                    teksSetuju: opts.confirmButtonText || 'Ya',
                    teksBatal: opts.cancelButtonText || 'Batal',
                    tanpaBatal: !!opts.tanpaBatal,
                    teksSibuk: '',
                    ikonTombol: opts.ikonTombol || '',
                    ragam: opts.ragam || 'primary',
                    galat: '',
                    sibuk: false,
                    /* Aksinya kosong: jalankanKonfirmasi() menutup modal lalu
                       menjawab true, dan pemanggilnya yang meneruskan. */
                    aksi: async () => {},
                    selesai: resolve,
                };
            });
        },
        async jalankanKonfirmasi() {
            const k = this.konfirmasi;
            if (k.sibuk || !k.aksi) return;
            k.sibuk = true;
            try {
                await k.aksi();
                k.buka = false;
                k.aksi = null;
                k.selesai?.(true);
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || k.galat);
                k.selesai?.(false);
            } finally {
                k.sibuk = false;
            }
        },
        /* Ditutup lewat Batal / latar: pemanggilnya harus tetap dapat jawaban,
           kalau tidak Promise-nya menggantung selamanya. */
        batalkanKonfirmasi(buka) {
            this.konfirmasi.buka = buka;
            if (!buka) {
                this.konfirmasi.selesai?.(false);
                this.konfirmasi.aksi = null;
            }
        },
        changeActionLabel(action) {
            return (
                {
                    added: 'Tambah',
                    changed: 'Ubah',
                    removed: 'Hapus',
                    ready: 'Siap rilis',
                    draft: 'Draft',
                }[action] ||
                action ||
                'Ubah'
            );
        },
        async goIdentitas() {
            if (!(await this.guardDirty())) return;
            this.editMeta();
        },
        /* ---- persistensi editor di URL (tahan reload / remount) ---- */
        syncUrl() {
            if (typeof window === 'undefined' || !window.history) return;
            const url = new URL(window.location.href);
            if (this.mode === 'editor' && this.current.Id_SOP_Paket) {
                url.searchParams.set('paket', this.current.Id_SOP_Paket);
                url.searchParams.set('step', String(this.step));
            } else {
                url.searchParams.delete('paket');
                url.searchParams.delete('step');
            }
            window.history.replaceState(window.history.state, '', url);
        },
        async restoreFromUrl() {
            if (typeof window === 'undefined') return;
            const q = new URLSearchParams(window.location.search);
            const id = q.get('paket');
            if (!id) return;
            this.mode = 'editor';
            this.resetEditorState();
            this.loading.editor = true;
            await this.loadPaket(id);
            this.loading.editor = false;
            if (!this.current.Id_SOP_Paket) {
                this.mode = 'list';
                this.syncUrl();
                return;
            } // paket hilang → balik daftar
            this.editMeta();
            const step = Number.parseInt(q.get('step'), 10);
            this.step = Number.isInteger(step) && this.stepReachable(step) ? step : 2;
            /* Jalur MASUK LEWAT URL — bukan lewat gotoStep(). Tab riwayat yang
               dibuka dari tautan tersalin (persis yang dilakukan orang saat
               menunjukkan temuan ke rekannya) akan tampil kosong permanen kalau
               pemuatannya hanya digantung di gotoStep. */
            if (this.step === 4) this.muatRiwayat(true);
            this.syncUrl();
        },
        /** "4 menit lalu" — SQL Server mengirim datetime tanpa zona, dibaca sebagai waktu lokal. */
        waktuRelatif(nilai) {
            const t = new Date(String(nilai).replace(' ', 'T'));
            if (Number.isNaN(t.getTime())) return '';
            const detik = Math.max(0, Math.floor((Date.now() - t.getTime()) / 1000));
            if (detik < 60) return 'baru saja';
            const menit = Math.floor(detik / 60);
            if (menit < 60) return `${menit} menit lalu`;
            const jam = Math.floor(menit / 60);
            if (jam < 24) return `${jam} jam lalu`;
            return `${Math.floor(jam / 24)} hari lalu`;
        },
        /**
         * @param {boolean} ulang true = mulai dari awal (buka tab, ganti saringan,
         *   muat ulang); false = sambung ke bawah. Dipisah supaya "muat lebih
         *   banyak" tak diam-diam menghapus yang sudah dibaca admin.
         */
        async muatRiwayat(ulang = true) {
            if (!this.current.Id_SOP_Paket) return;
            if (ulang) {
                this.riwayat.rows = [];
                this.riwayat.total = 0;
            }
            this.riwayat.loading = true;
            try {
                const r = await axios.get(`${API}/paket/${this.current.Id_SOP_Paket}/riwayat`, {
                    params: {
                        limit: 40,
                        offset: this.riwayat.rows.length,
                        aksi: this.riwayat.filter || undefined,
                    },
                });
                const d = r.data.result || {};
                this.riwayat.rows = [...this.riwayat.rows, ...(d.rows || [])];
                this.riwayat.total = d.total || 0;
                this.riwayat.aksi = d.aksi || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat riwayat aktivitas');
            } finally {
                this.riwayat.loading = false;
            }
        },
        formatWaktuRiwayat(nilai) {
            const t = new Date(String(nilai).replace(' ', 'T'));
            if (Number.isNaN(t.getTime())) return '';
            return new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(t);
        },
        labelAksiRiwayat(aksi) {
            const labels = {
                PAKET_BUAT: 'Paket dibuat',
                PAKET_UBAH: 'Identitas paket',
                PAKET_STATUS: 'Status paket',
                PAKET_HAPUS: 'Paket dihapus',
                PAKET_KLON: 'Paket diduplikat',
                DRAFT_BUANG: 'Draft dibuang',
                SECTION_BUAT: 'Section dibuat',
                SECTION_UBAH: 'Section diubah',
                SECTION_HAPUS: 'Section dihapus',
                SECTION_KLON: 'Section diduplikat',
                SECTION_URUT: 'Urutan section',
                ITEM_BUAT: 'Item dibuat',
                ITEM_UBAH: 'Item diubah',
                ITEM_HAPUS: 'Item dihapus',
                ITEM_KLON: 'Item diduplikat',
                ITEM_TIPE: 'Tipe item',
                ITEM_URUT: 'Urutan item',
                PAKET_VERSION: 'Versi paket',
                VERSI_PUBLISH: 'Versi item diterbitkan',
                VERSI_ROLLBACK: 'Versi item dipulihkan',
                ABSEN_IN: 'Presensi Dokumen',
                ASSIGN: 'Penugasan',
                CABUT_AKSES: 'Akses dicabut',
            };
            return labels[aksi] || String(aksi || '').replaceAll('_', ' ');
        },
        riwayatIcon(aksi) {
            if (String(aksi).includes('HAPUS') || aksi === 'DRAFT_BUANG' || aksi === 'CABUT_AKSES') return 'bi-trash3';
            if (String(aksi).includes('BUAT') || String(aksi).includes('KLON')) return 'bi-plus-lg';
            if (String(aksi).includes('VERSION') || String(aksi).includes('PUBLISH')) return 'bi-rocket-takeoff';
            if (String(aksi).includes('URUT')) return 'bi-list-ol';
            if (aksi === 'PAKET_STATUS') return 'bi-arrow-repeat';
            return 'bi-pencil';
        },
        riwayatTone(aksi) {
            if (String(aksi).includes('HAPUS') || aksi === 'DRAFT_BUANG' || aksi === 'CABUT_AKSES')
                return 'tone-danger';
            if (String(aksi).includes('BUAT') || String(aksi).includes('KLON')) return 'tone-added';
            if (String(aksi).includes('VERSION') || String(aksi).includes('PUBLISH')) return 'tone-publish';
            return 'tone-edit';
        },
        /** Badge perubahan untuk satu baris section; null bila section itu tak berubah. */
        badgePerubahan(id) {
            const aksi = this.changeMap[id];
            if (aksi === 'added')
                return {
                    text: 'Belum Dipublish',
                    subtext: 'Section Baru',
                    cls: 'is-unpublished is-added',
                    icon: 'bi-plus-circle-fill',
                    title: 'Section baru — belum dipublish',
                };
            if (aksi === 'changed')
                return {
                    text: 'Belum Dipublish',
                    subtext: 'Section Diubah',
                    cls: 'is-unpublished is-changed',
                    icon: 'bi-pencil-fill',
                    title: 'Perubahan section belum dipublish',
                };
            return null;
        },
        /** Badge status terbit/perubahan per item dokumen di step Struktur. */
        itemBadgePerubahan(it) {
            if (!it) return null;
            const itemId = it.Id_SOP_Paket_Item;
            const aksi = this.changeMap[itemId];

            if (this.current.Status === 'PUBLISHED') {
                if (aksi === 'added') {
                    return {
                        text: 'Belum Dipublish',
                        subtext: 'Baru',
                        cls: 'is-unpublished is-added',
                        icon: 'bi-record-circle-fill',
                        title: 'Item baru — belum dipublish ke publik',
                    };
                }
                if (aksi === 'changed') {
                    return {
                        text: 'Belum Dipublish',
                        subtext: 'Diubah',
                        cls: 'is-unpublished is-changed',
                        icon: 'bi-pencil-fill',
                        title: 'Perubahan item belum dipublish',
                    };
                }
                if (aksi === 'ready') {
                    return {
                        text: 'Belum Dipublish',
                        subtext: 'Versi Baru',
                        cls: 'is-unpublished is-ready',
                        icon: 'bi-rocket-takeoff-fill',
                        title: 'Versi konten baru siap dipublish',
                    };
                }
                if (aksi === 'draft' || it.Versi_Status === 'DRAFT') {
                    return {
                        text: 'Versi Draft',
                        cls: 'is-draft',
                        icon: 'bi-exclamation-triangle-fill',
                        title: 'Versi konten masih Draft — tandai Siap Rilis sebelum publish',
                    };
                }
                return null;
            }

            // Status paket = DRAFT (paket belum pernah dipublish)
            if (it.Versi_Status === 'DRAFT') {
                return {
                    text: 'Versi Draft',
                    cls: 'is-draft',
                    icon: 'bi-exclamation-triangle-fill',
                    title: 'Versi konten masih Draft',
                };
            }

            return {
                text: 'Belum Dipublish',
                cls: 'is-unpublished',
                icon: 'bi-clock-history',
                title: 'Item paket draft — belum pernah dipublish',
            };
        },
        /**
         * Section terlipat menyembunyikan perubahan di dalamnya. Tanpa penanda di
         * kepala section, fitur ini kalah oleh satu klik lipat.
         */
        sectionAdaPerubahan(sectionId) {
            if (this.changeMap[sectionId]) return true;
            return this.itemsOf(sectionId).some((it) => this.itemBadgePerubahan(it) !== null);
        },
        prioMeta(p) {
            return metaPrioritas(p);
        },
        tipeLabel(t) {
            return (TIPE_ITEM[t] || {}).label || t;
        },
        statusVariant(s) {
            return { DRAFT: 'idle', PUBLISHED: 'ok', ARCHIVED: 'bad', OBSOLETE: 'idle' }[s] || 'idle';
        },
        badgeVariantForStatus(s) {
            return { DRAFT: 'warning', PUBLISHED: 'success', ARCHIVED: 'neutral', OBSOLETE: 'neutral' }[s] || 'brand';
        },
        badgeVariantForPrio(p) {
            return { KRITIS: 'danger', TINGGI: 'warning', NORMAL: 'neutral' }[p] || 'neutral';
        },
        bukaLibraryKonten() {
            window.location.href = '/sop/admin/konten';
        },
        isFileType(t) {
            return isFileTipe(t);
        },
        onViewModeToggle(v) {
            this.viewMode = v;
            try {
                localStorage.setItem('sop_builder_view', v);
            } catch (e) {
                /* noop */
            }
        },
        /**
         * Satu-satunya jalan yang memuat ulang daftar.
         *
         * Setiap penyaring memanggil ini, bukan `fetchPaket` masing-masing.
         * Daftar dan hitungan HARUS berangkat berpasangan: badge chip dan angka
         * rail yang diperbarui tanpa daftarnya (atau sebaliknya) menampilkan
         * dua jawaban untuk satu pertanyaan, dan yang salah tak kelihatan salah.
         */
        muatUlangDaftar() {
            this.fetchPaket(1);
            this.fetchCounts();
        },
        /** Chip status: satu nilai, atau kosongkan pada "Semua Paket". */
        onStatusChipClick(chip) {
            const val = chip && chip.value !== undefined ? chip.value : chip || 'ALL';
            const baru = val === 'ALL' ? [] : [val];
            if (JSON.stringify(baru) === JSON.stringify(this.listFilters.status || [])) return;

            this.listFilters.status = baru;
            this.muatUlangDaftar();
        },
        /**
         * Ladang berganda SELALU dipegang sebagai larik.
         *
         * Tombol "×" pada lencana saringan di HcisFilterBar mengosongkan ladang
         * dengan string kosong, bukan larik kosong — kontrak yang benar untuk
         * ladang tunggal. Dibiarkan begitu, el-select berganda menerima string
         * dan berhenti bisa dicentang sampai halaman dimuat ulang.
         */
        onFilterFieldChange({ key, value }) {
            const cfg = this.builderFiltersConfig.find((f) => f.key === key);
            const berganda = !!(cfg && cfg.multiple);

            this.listFilters[key] = berganda && !Array.isArray(value)
                ? (value === '' || value === null || value === undefined ? [] : [value])
                : value;

            this.muatUlangDaftar();
        },
        onFilterSearch(query) {
            this.listFilters.search = query !== undefined ? query : this.listFilters.search;
            this.muatUlangDaftar();
        },
        /**
         * Reset MEMBERSIHKAN saringan, bukan sumbu pengelompokan.
         *
         * Sumbu bukan saringan: ia mengubah cara melihat, bukan apa yang
         * terlihat. Ikut mereset-nya membuat orang menekan "reset saringan"
         * lalu kehilangan susunan yang tak pernah dimaksudkan ikut hilang.
         * Sama seperti di Functional Document.
         */
        onResetFilters() {
            this.listFilters = saringanKosong();
            this.muatUlangDaftar();
        },
        /** Pindah mode rail: Metrik vs Folder (reset saringan kelompokan saat berpindah) */
        setRailMode(mode) {
            if (this.railMode === mode) return;
            this.railMode = mode;
            try {
                localStorage.setItem('sop_builder_rail_mode', mode);
            } catch (e) {
                /* noop */
            }

            // Bersihkan saringan yang berasal dari kelompokan mode sebelumnya agar tidak nyangkut/tumpang tindih
            this.listFilters.departemen = [];
            this.listFilters.jenis = [];
            this.listFilters.prioritas = [];
            this.listFilters.apqc = [];
            this.listFilters.distribusi = [];
            this.listFilters.estimasi = [];

            this.muatUlangDaftar();
        },
        /** Pilih node Departemen pada Pohon Folder */
        onPilihTreeDept(deptNama) {
            const val = deptNama || '';
            const cur = this.listFilters.departemen || [];
            if (cur.length === 1 && cur[0] === val) {
                this.listFilters.departemen = [];
            } else {
                this.listFilters.departemen = val ? [val] : [];
            }
            this.listFilters.jenis = [];
            this.muatUlangDaftar();
        },
        /** Pilih node Subfolder Jenis Dokumen pada Pohon Folder */
        onPilihTreeSub({ dept, jenis }) {
            this.listFilters.departemen = dept ? [dept] : [];
            this.listFilters.jenis = jenis ? [jenis] : [];
            this.muatUlangDaftar();
        },
        /** Reset filter pohon untuk menampilkan semua paket */
        onPilihTreeSemua() {
            this.listFilters.departemen = [];
            this.listFilters.jenis = [];
            this.muatUlangDaftar();
        },
        /** Pindah sumbu rail. Saringan yang sudah terpasang tidak ikut dibuang. */
        pilihSumbu(key) {
            if (this.sumbuAktif === key) return;
            this.sumbuAktif = key;
            try {
                localStorage.setItem('sop_builder_sumbu', key);
            } catch (e) {
                /* noop */
            }
            // Hanya sebaran rail yang berubah; daftarnya tidak tersentuh.
            this.fetchCounts();
        },
        /**
         * Klik pada satu entri rail.
         *
         * Menulis ke ladang saringan MILIK SUMBU AKTIF, bukan ke satu ladang
         * yang ditulis keras seperti bentuk lama. Klik kedua pada entri yang
         * sama melepasnya kembali.
         */
        pilihGrup(nilai) {
            const kunci = this.sumbuMeta.kunciSaringan;
            const val = nilai === '' || nilai === null ? '' : String(nilai);
            const sekarang = this.listFilters[kunci] || [];

            if (!val) {
                if (!sekarang.length) return;
                this.listFilters[kunci] = [];
            } else if (sekarang.length === 1 && String(sekarang[0]) === val) {
                this.listFilters[kunci] = [];
            } else {
                this.listFilters[kunci] = [val];
            }

            this.muatUlangDaftar();
        },
        /** Lepas saringan satu sumbu dari chip "Disaring:". */
        lepasSaringanSumbu() {
            this.listFilters[this.sumbuMeta.kunciSaringan] = [];
            this.muatUlangDaftar();
        },
        filterParams() {
            return paramsPaket(this.listFilters);
        },
        itemsOf(sectionId) {
            return this.items.filter((it) => it.Id_SOP_Section === sectionId);
        },

        /* ---- wizard nav ---- */
        stepReachable(n) {
            return n === 1 || !!this.current.Id_SOP_Paket;
        },
        async gotoStep(n) {
            if (!this.stepReachable(n)) {
                ElMessage.warning('Simpan identitas dulu.');
                return;
            }
            if (n !== this.step && !(await this.guardDirty())) return;
            this.step = n;
            /* Riwayat dimuat saat tabnya dibuka, bukan saat paket dimuat: ia
               data yang cepat basi dan hanya sesekali dilihat, jadi menariknya
               di muka membebani setiap kunjungan editor demi layar yang jarang
               dibuka. */
            if (n === 4) this.muatRiwayat(true);
            this.syncUrl();
        },
        editMeta() {
            this.paketForm = {
                Nama_Paket: this.current.Nama_Paket,
                Prioritas: this.current.Prioritas || 'NORMAL',
                Mode_Urutan_Item: this.current.Mode_Urutan_Item || 'BEBAS',
                Mode_Distribusi: this.current.Mode_Distribusi || 'TERBATAS',
                Deskripsi: this.current.Deskripsi || '',
                Tujuan_Ruang_Lingkup: this.current.Tujuan_Ruang_Lingkup || '',
                Estimasi_Waktu_Baca_Menit: this.current.Estimasi_Waktu_Baca_Menit || null,
                Flag_Absensi: this.current.Flag_Absensi === 'Y',
                Cover_Position: this.current.Cover_Position || '50% 50%',
            };
            this.paketFormPristine = this.formSig(this.paketForm);
            this.coverModel = this.current.Cover_Path || '';
            this.step = 1;
            this.syncUrl();
        },

        async fetchPaket(page = 1) {
            this.loading.list = true;
            this.pagination.current_page = page;
            const params = { ...this.filterParams(), page, limit: this.pagination.per_page };
            try {
                const r = await axios.get(`${API}/paket/current`, { params });
                this.paketList = r.data.success ? r.data.result : [];
                this.pagination.total_page = r.data.total_page || 1;
                this.pagination.total_data = r.data.total_data || 0;
            } catch (e) {
                if (e.response?.status === 404) {
                    this.paketList = [];
                    this.pagination.total_page = 1;
                    this.pagination.total_data = 0;
                } else ElMessage.error('Gagal memuat paket');
            } finally {
                this.loading.list = false;
            }
        },
        /**
         * Badge chip status DAN sebaran rail, dalam satu permintaan.
         *
         * Keduanya selalu dibutuhkan bersamaan — tiap sentuhan saringan
         * mengubah dua-duanya. Dua permintaan yang selalu berangkat berpasangan
         * hanya menambah satu kesempatan untuk mendarat tak sinkron.
         */
        async fetchCounts() {
            try {
                const r = await axios.get(`${API}/paket/counts`, {
                    params: { ...this.filterParams(), sumbu: this.sumbuAktif },
                });
                if (r.data.success) {
                    this.statusCountsData = r.data.result;
                    this.grupRailData = r.data.result.grup || [];
                    this.treeData = r.data.result.tree || [];
                }
            } catch (e) {
                /* silent — badge opsional */
            }
        },

        /* ---- Paket create/meta ---- */
        startNewPaket() {
            this.paketForm = {
                Nama_Paket: '',
                Prioritas: 'NORMAL',
                Mode_Urutan_Item: 'BEBAS',
                Mode_Distribusi: 'TERBATAS',
                Deskripsi: '',
                Tujuan_Ruang_Lingkup: '',
                Estimasi_Waktu_Baca_Menit: null,
                Flag_Absensi: false,
                Cover_Position: '50% 50%',
            };
            this.paketFormPristine = this.formSig(this.paketForm);
            this.coverModel = '';
            this.current = {};
            this.sections = [];
            this.items = [];
            this.releaseSummary = '';
            this.mode = 'editor';
            this.step = 1;
            this.resetEditorState();
        },
        async saveMetaAndNext() {
            if (!this.paketForm.Nama_Paket) {
                ElMessage.warning('Nama paket wajib diisi');
                return;
            }
            this.loading.save = true;
            try {
                const payload = { ...this.paketForm, Cover: this.coverModel };
                const sesudahSimpan = async (id, pesan) => {
                    /* Pintu kedua dari cacat yang sama. Penandaan bersih didahulukan
                       atas pesan sukses DAN atas loadPaket(), supaya tak ada jeda
                       ketika layar sudah berkata tersimpan sedangkan penjaga masih
                       menganggapnya kotor. loadPaket() aman didahului: ia hanya
                       mengisi current dan struktur, tak pernah menyentuh paketForm. */
                    this.paketFormPristine = this.formSig(this.paketForm);
                    ElMessage.success(pesan);
                    await this.loadPaket(id);
                };

                if (this.current.Id_SOP_Paket) {
                    await axios.put(`${API}/paket/update/${this.current.Id_SOP_Paket}`, payload);
                    await sesudahSimpan(this.current.Id_SOP_Paket, 'Identitas tersimpan');
                } else {
                    const r = await axios.post(`${API}/paket/store`, payload);
                    await sesudahSimpan(r.data.result.Id_SOP_Paket, 'Paket dibuat — susun struktur');
                }
                this.step = 2;
                this.syncUrl();
                this.fetchPaket(1);
                this.fetchCounts();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan');
            } finally {
                this.loading.save = false;
            }
        },
        async hapusPaket(p) {
            const pesan =
                p.Status === 'PUBLISHED'
                    ? `Paket "${p.Nama_Paket}" berstatus TERBIT — menghapus akan mencabutnya dari karyawan. Sebaiknya arsipkan dulu. Tetap hapus?`
                    : `Hapus paket "${p.Nama_Paket}"?`;
            await this.confirmAndRun(
                pesan,
                'Konfirmasi',
                {
                    type: p.Status === 'PUBLISHED' ? 'error' : 'warning',
                    confirmButtonText: 'Hapus',
                    loadingText: 'Menghapus…',
                    errorText: 'Gagal menghapus',
                },
                async () => {
                    await axios.delete(`${API}/paket/delete/${p.Id_SOP_Paket}`);
                    ElMessage.success('Paket dihapus');
                    await this.fetchPaket(1);
                    this.fetchCounts();
                },
            );
        },
        async duplikatPaket(p) {
            if (this.busyId) return;
            this.busyId = `paket:${p.Id_SOP_Paket}`;
            try {
                await axios.post(`${API}/paket/${p.Id_SOP_Paket}/clone`);
                ElMessage.success('Paket diduplikat sebagai draft');
                await this.fetchPaket(1);
                this.fetchCounts();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menduplikat paket');
            } finally {
                this.busyId = null;
            }
        },
        async duplikatSection(sec) {
            if (this.busyId) return;
            this.busyId = `section:${sec.Id_SOP_Section}`;
            try {
                await axios.post(`${API}/section/${sec.Id_SOP_Section}/clone`);
                ElMessage.success('Section diduplikat');
                await this.reloadItems();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menduplikat section');
            } finally {
                this.busyId = null;
            }
        },
        /**
         * Duplikat item — DENGAN konfirmasi, karena akibatnya tak terlihat.
         *
         * Menduplikat item ber-versi di paket yang sama melahirkan DOKUMEN
         * BARU: dokumen terkendali kedua berjudul sama, dengan riwayat versinya
         * sendiri. Dulu ini satu klik tanpa pertanyaan apa pun — dan begitulah
         * 9 kelompok dokumen kembar di dev lahir, semuanya dari penyalinan.
         *
         * Yang disalin tetap boleh disalin; yang tak boleh adalah melakukannya
         * tanpa tahu.
         */
        async duplikatItem(it) {
            if (this.busyId) return;

            const berversi = ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS'].includes(it.Tipe_Item);

            await this.confirmAndRun(
                berversi
                    ? `Salinan "${it.Judul_Item}" dibuat di paket ini beserta DOKUMEN-nya sendiri — dokumen terkendali baru, terpisah dari yang asli.`
                    : `Salinan "${it.Judul_Item}" dibuat di paket ini.`,
                'Duplikat Item',
                {
                    ikonTombol: 'bi-copy',
                    confirmButtonText: 'Duplikat',
                    loadingText: 'Menduplikat…',
                    errorText: 'Gagal menduplikat item',
                    catatan: berversi
                        ? 'Revisi pada salah satunya TIDAK ikut ke yang lain. Kalau yang Anda maksud memakai dokumen yang sama di dua tempat, pakai dokumen yang sama di paket berbeda — bukan menduplikat.'
                        : '',
                },
                async () => {
                    this.busyId = `item:${it.Id_SOP_Paket_Item}`;
                    try {
                        await axios.post(`${API}/item/${it.Id_SOP_Paket_Item}/clone`);
                        ElMessage.success('Item diduplikat');
                        await this.reloadItems();
                    } finally {
                        this.busyId = null;
                    }
                },
            );
        },
        /**
         * Item pada revisi paket terbit datang sebagai token "di_…" (baris
         * N_SOP_Paket_Draft_Item), bukan item live — endpoint salin menolaknya
         * karena PK kedua tabel beririsan dan salah decode berarti menyalin item
         * yang sama sekali lain. Menyalin isi revisi butuh jalur draft tersendiri.
         */
        bisaSalinKePaketLain(it) {
            return !String(it?.Id_SOP_Paket_Item || '').startsWith('di_');
        },
        openCopyToPaketModal(it) {
            if (this.openItemId === it.Id_SOP_Paket_Item && this.itemDirty) {
                ElMessage.warning('Simpan dulu perubahan pada item ini sebelum menyalin ke paket lain.');
                return;
            }
            this.copyToPaketSource = it;
            this.copyToPaketOpen = true;
        },
        async onItemCopied({ Nama_Paket_Tujuan, Id_SOP_Paket_Tujuan }) {
            this.copyToPaketOpen = false;
            const bukaPaketTujuan = await this.tanya(
                `Item berhasil disalin ke paket "${Nama_Paket_Tujuan}".`,
                'Berhasil Disalin',
                {
                    confirmButtonText: 'Buka Paket Tujuan',
                    cancelButtonText: 'Tetap di Sini',
                    ikonTombol: 'bi-box-arrow-up-right',
                },
            );
            if (bukaPaketTujuan) {
                await this.openEditor({ Id_SOP_Paket: Id_SOP_Paket_Tujuan });
            }
        },
        /* Dijalankan dari pratinjau, dengan sifat revisi yang dipilih DI SANA —
           bukan dari panel rilis di atas. Keputusan diambil di tempat
           dampaknya terlihat. */
        async terbitkanDariPratinjau(sifat, perDokumen = {}) {
            if (!this.releaseSummary.trim()) {
                ElMessage.warning('Ringkasan perubahan wajib diisi sebelum publish paket');

                return;
            }
            this.publishMengirim = true;
            try {
                const r = await axios.patch(`${API}/paket/status/${this.current.Id_SOP_Paket}`, {
                    status: 'PUBLISHED',
                    Ringkasan_Perubahan: this.releaseSummary.trim(),
                    /* Sifat menyeluruh untuk yang tak disebut per dokumen —
                       item baru belum punya dokumen saat pratinjau dihitung. */
                    Wajib_Baca_Ulang: sifat,
                    Sifat_Per_Dokumen: perDokumen,
                });
                this.current.Status = 'PUBLISHED';
                this.releaseSummary = '';
                this.publishPratinjau = false;

                /*
                 * PENERBITAN SELESAI, PENYEMAIAN BELUM.
                 *
                 * Sejak batas transaksi dipisah, versi paket sudah sah begitu
                 * respons datang, tetapi kewajiban karyawan disemai di latar
                 * lewat outbox. Mengatakan "Paket diterbitkan." saja membuat
                 * admin mengira semuanya beres, lalu bingung ketika angka
                 * kepatuhan belum bergerak beberapa detik kemudian.
                 *
                 * Panel Aktivitas yang menampilkan progresnya, jadi di sini
                 * cukup menunjuk ke sana — tak perlu poll kedua di halaman ini.
                 */
                const taskId = r?.data?.result?.task_id ?? null;
                if (taskId) {
                    ElMessage.success({
                        message:
                            'Paket diterbitkan. Kewajiban karyawan sedang disemai di latar — '
                            + 'pantau di panel Aktivitas.',
                        duration: 6000,
                    });
                } else {
                    ElMessage.success('Paket diterbitkan.');
                }

                /* Kembali ke daftar, bukan memuat ulang editor (permintaan user
                   5 Okt 2026). Paket yang baru terbit tak punya pekerjaan lagi
                   di editor, dan memuat ulang seluruh strukturnya hanya membuat
                   layar terasa macet sesudah tombol ditekan. */
                this.keDaftar(1);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menerbitkan paket');
            } finally {
                this.publishMengirim = false;
            }
        },
        async ubahStatus(status) {
            if (status === 'PUBLISHED' && !this.canPublish) {
                ElMessage.warning(this.publishHint);
                return;
            }
            if (status === 'PUBLISHED' && !this.releaseSummary.trim()) {
                ElMessage.warning('Ringkasan perubahan wajib diisi sebelum publish paket');
                return;
            }
            /* PUBLISHED tak lagi lewat konfirmasi kalimat polos: sejak dokumen
               bisa dipakai banyak paket, menerbitkan paket ini bisa menerbitkan
               revisi yang juga disajikan paket lain. Admin harus melihat itu
               sebelum menekan, bukan diberi tahu sesudahnya. */
            if (status === 'PUBLISHED') {
                this.publishPratinjau = true;

                return;
            }

            const konfirmasi = {
                // PUBLISHED tak lagi lewat sini — lihat early-return di atas.
                ARCHIVED: {
                    msg: 'Arsipkan Dokumen ini? Tidak akan lagi ditugaskan ke karyawan baru.',
                    ok: 'Arsipkan',
                    title: 'Arsipkan Dokumen',
                    loading: 'Mengarsipkan…',
                },
            }[status];
            const task = async () => {
                /* Sifat revisi tak dikirim di jalur ini: yang tersisa cuma
                   ARCHIVED, dan mengarsipkan tak menerbitkan versi apa pun. */
                await axios.patch(`${API}/paket/status/${this.current.Id_SOP_Paket}`, {
                    status,
                    Ringkasan_Perubahan: this.releaseSummary.trim(),
                });
                this.current.Status = status;
                this.releaseSummary = '';
                await this.loadPaket(this.current.Id_SOP_Paket);
                ElMessage.success(`Status: ${this.statusLabel(status)}`);
                await this.fetchPaket(1);
                this.fetchCounts();
            };
            if (konfirmasi) {
                await this.confirmAndRun(
                    konfirmasi.msg,
                    konfirmasi.title,
                    {
                        confirmButtonText: konfirmasi.ok,
                        loadingText: konfirmasi.loading,
                        errorText: 'Gagal ubah status',
                    },
                    task,
                );
            } else {
                try {
                    await task();
                } catch (e) {
                    ElMessage.error(e.response?.data?.message || 'Gagal ubah status');
                }
            }
        },

        async discardWorkingDraft() {
            await this.confirmAndRun(
                'Buang seluruh revisi paket dan kandidat versi yang belum dipublish? Data aktif tidak akan berubah.',
                'Buang Draft Revisi',
                {
                    confirmButtonText: 'Buang Draft',
                    loadingText: 'Membuang draft...',
                    errorText: 'Gagal membuang draft',
                },
                async () => {
                    await axios.delete(`${API}/paket/${this.current.Id_SOP_Paket}/draft`);
                    this.releaseSummary = '';
                    await this.loadPaket(this.current.Id_SOP_Paket);
                    ElMessage.success('Draft revisi dibuang');
                },
            );
        },

        /**
         * Kosongkan paket DRAFT — mulai menyusun dari nol.
         *
         * Angkanya disebut di muka, dan disebut apa adanya: yang dilepas
         * penempatan item & section-nya, bukan Dokumennya. Admin yang
         * mengira dokumennya ikut terhapus akan menyusunnya ulang dari awal
         * padahal cukup memilihnya kembali dari daftar.
         */
        async kosongkanPaket() {
            const item = this.items.length;
            const section = this.sections.length;

            await this.confirmAndRun(
                'Seluruh isi paket ini dilepas dan Anda mulai menyusun dari kosong. Paketnya sendiri — nama, prioritas, sampul — tetap ada.',
                'Kosongkan Paket',
                {
                    subjudul: this.current.Nama_Paket,
                    statistik: [
                        { nilai: item, label: 'item dilepas' },
                        { nilai: section, label: 'section dilepas' },
                    ],
                    catatan:
                        'Dokumennya TIDAK dihapus. Ia tetap ada di Master Dokumen dan bisa dipilih lagi kapan saja — yang hilang hanya penempatannya di paket ini.',
                    confirmButtonText: 'Kosongkan',
                    loadingText: 'Mengosongkan...',
                    errorText: 'Gagal mengosongkan paket',
                    ikonTombol: 'bi-eraser',
                    ragam: 'danger',
                },
                async () => {
                    const r = await axios.delete(`${API}/paket/${this.current.Id_SOP_Paket}/isi`);
                    await this.loadPaket(this.current.Id_SOP_Paket);
                    ElMessage.success(r.data?.message || 'Paket dikosongkan');
                },
            );
        },

        /* ---- open/load ---- */
        async openEditor(p) {
            this.mode = 'editor';
            this.resetEditorState();
            this.loading.editor = true;
            await this.loadPaket(p.Id_SOP_Paket);
            this.loading.editor = false;
            this.editMeta(); // isi paketForm dari data
            this.step = 2; // langsung ke struktur
            this.syncUrl();
        },
        async loadPaket(id) {
            try {
                const r = await axios.get(`${API}/paket/${id}`);
                if (r.data.success) {
                    this.current = r.data.result.paket;
                    this.sections = r.data.result.sections || [];
                    this.items = r.data.result.items || [];
                    this.paketVersi = r.data.result.paketVersi || null;
                    this.nextVersiPaket = r.data.result.nextVersiPaket || 1;
                    this.pendingChanges = r.data.result.pendingChanges || { total: 0, summary: {} };
                    this.draftActivity = r.data.result.draftActivity || null;
                    this.dukungDokumenDraft = !!r.data.result.dukungDokumenDraft;
                }
            } catch (e) {
                ElMessage.error('Gagal membuka editor');
            }
        },
        async backToList() {
            if (!(await this.guardDirty())) return;
            this.keDaftar(this.pagination.current_page);
        },
        /** Tutup editor dan tampilkan daftar paket pada halaman tertentu. */
        keDaftar(halaman = 1) {
            this.mode = 'list';
            this.resetEditorState();
            this.syncUrl();
            this.fetchPaket(halaman);
            this.fetchCounts();
        },
        resetEditorState() {
            this.openItemId = null;
            this.adding = false;
            this.addingSection = null;
            this.previewOpen = false;
            this.itemForm = {};
            this.releaseSummary = '';
            // Wajib dikosongkan: openEditor() memanggil ini sebelum request paket baru selesai.
            // Tanpa reset, layar Step 3 paket SEBELUMNYA tetap tampil dan tombol Publish di sana
            // menembak paket yang salah. restoreFromUrl() juga mengandalkan current kosong
            // untuk mendeteksi paket yang sudah tidak ada.
            this.step = 1;
            this.current = {};
            this.sections = [];
            this.items = [];
            this.pendingChanges = { total: 0, summary: {} };
            this.nextVersiPaket = 1;
            this.paketVersi = null;
            this.draftActivity = null;
            /* Ikut direset saat pindah paket. Riwayat yang tertinggal dari paket
               sebelumnya adalah bentuk kebohongan yang paling meyakinkan: ia
               tampak seperti data yang sah. */
            this.riwayat = { loading: false, rows: [], total: 0, aksi: [], filter: '' };
        },
        /**
         * Tarik ulang struktur paket sesudah perubahan apa pun.
         *
         * DIPANGGIL DARI DELAPAN TEMPAT dan dulu tak memberi tanda apa pun.
         * Akibatnya paling terasa sesudah simpan: layar sudah berkata "Item
         * tersimpan" sementara daftarnya masih memegang isi lama selama satu
         * putaran jaringan penuh. Yang membaca daftar itu menyimpulkan
         * simpanannya tidak masuk — lalu menekan simpan sekali lagi.
         */
        async reloadItems() {
            this.loading.struktur = true;
            try {
                const r = await axios.get(`${API}/paket/${this.current.Id_SOP_Paket}`);
                if (r.data.success) {
                    if (r.data.result.paket) this.current = r.data.result.paket;
                    this.sections = r.data.result.sections || [];
                    this.items = r.data.result.items || [];
                    // Endpoint ini sama dengan loadPaket(), jadi ikut membawa status rilis.
                    // Kalau tidak disalin, gerbang Publish & panel diff memakai data basi:
                    // tombol Publish bisa macet nonaktif, atau aktif padahal server menolak 422.
                    this.paketVersi = r.data.result.paketVersi || null;
                    this.nextVersiPaket = r.data.result.nextVersiPaket || 1;
                    this.pendingChanges = r.data.result.pendingChanges || { total: 0, summary: {} };
                    this.draftActivity = r.data.result.draftActivity || null;
                    this.dukungDokumenDraft = !!r.data.result.dukungDokumenDraft;
                }
            } catch (e) {
                ElMessage.error('Gagal memuat ulang struktur');
            } finally {
                this.loading.struktur = false;
            }
            this.mode = 'editor'; // jaga: reload struktur tak boleh melempar balik ke daftar
        },
        /**
         * Server menolak simpan karena baris sudah diubah admin lain (HTTP 409).
         * Working draft dipakai bersama, jadi ini bukan error — tutup editor, buang
         * form basi, lalu tarik ulang struktur supaya admin melihat versi terbaru.
         */
        async handleKonflik(pesan) {
            /* Pemberitahuan, bukan pilihan: apa pun jawabannya layar HARUS
               dimuat ulang — data basi di layar lebih berbahaya daripada
               gangguan satu klik. Karena itu tanpa tombol Batal. */
            await this.tanya(
                pesan || 'Data ini baru saja diubah admin lain. Muat ulang untuk melihat versi terbaru.',
                'Perubahan bentrok',
                {
                    confirmButtonText: 'Muat ulang',
                    tanpaBatal: true,
                    ragam: 'danger',
                    ikonTombol: 'bi-arrow-clockwise',
                },
            );
            this.openItemId = null;
            this.itemForm = {};
            this.itemFormPristine = null;
            await this.reloadItems();
        },
        /* onVersionChanged() DIHAPUS bersama pemanggilnya. Panel versi kini
           read-only, jadi tak ada lagi peristiwa "versi berubah" yang lahir dari
           layar ini — perubahan versi terjadi di Master Dokumen, dan pointer
           itemnya sudah tersegarkan saat Builder dibuka kembali. */

        /* ---- Section ---- */
        addSection() {
            this.sectionModal = {
                show: true,
                mode: 'create',
                id: null,
                Nama_Section: '',
                Deskripsi: '',
                saving: false,
            };
        },
        renameSection(sec) {
            this.sectionModal = {
                show: true,
                mode: 'edit',
                id: sec.Id_SOP_Section,
                Nama_Section: sec.Nama_Section || '',
                Deskripsi: sec.Deskripsi || '',
                Row_Ver: sec.Row_Ver || null, // token concurrency, lihat handleKonflik()
                saving: false,
            };
        },
        closeSectionModal() {
            if (!this.sectionModal.saving) this.sectionModal.show = false;
        },
        async submitSection() {
            /* Enter di kotak nama memanggil ini juga, jadi menekan Enter dua
               kali selagi permintaan pertama masih di jalan akan membuat dua
               section bernama sama. */
            if (this.sectionModal.saving) return;

            const nama = (this.sectionModal.Nama_Section || '').trim();
            if (!nama) {
                ElMessage.warning('Nama section wajib');
                return;
            }
            const payload = { Nama_Section: nama, Deskripsi: (this.sectionModal.Deskripsi || '').trim() || null };
            this.sectionModal.saving = true;
            try {
                if (this.sectionModal.mode === 'edit') {
                    await axios.put(`${API}/section/update/${this.sectionModal.id}`, {
                        ...payload,
                        Row_Ver: this.sectionModal.Row_Ver,
                    });
                    ElMessage.success('Section disimpan');
                } else {
                    await axios.post(`${API}/paket/${this.current.Id_SOP_Paket}/section/store`, payload);
                    ElMessage.success('Section dibuat');
                }
                /* DITUTUP SESUDAH strukturnya segar, bukan sebelum.
                   Urutan lamanya menutup modal lebih dulu, sehingga satu-satunya
                   tanda "sedang bekerja" di layar lenyap tepat ketika kerjanya
                   belum selesai — dan yang tersisa adalah daftar tanpa section
                   yang barusan dibuat. */
                await this.reloadItems();
                this.sectionModal.show = false;
            } catch (e) {
                if (e.response?.status === 409) {
                    this.sectionModal.show = false;
                    await this.handleKonflik(e.response.data.message);
                } else {
                    ElMessage.error(e.response?.data?.message || 'Gagal menyimpan section');
                }
            } finally {
                this.sectionModal.saving = false;
            }
        },
        async deleteSection(sec) {
            await this.confirmAndRun(
                `Hapus section "${sec.Nama_Section}"?`,
                'Konfirmasi',
                {
                    type: 'warning',
                    confirmButtonText: 'Hapus',
                    loadingText: 'Menghapus…',
                    errorText: 'Gagal hapus section',
                },
                async () => {
                    await axios.delete(`${API}/section/delete/${sec.Id_SOP_Section}`);
                    ElMessage.success('Section dihapus');
                    await this.reloadItems();
                },
            );
        },
        /* ---- Section: collapse + reorder ---- */
        toggleCollapse(sec) {
            const id = sec.Id_SOP_Section;
            this.collapsedSections = { ...this.collapsedSections, [id]: !this.collapsedSections[id] };
        },
        sectionWajib(sectionId) {
            return this.itemsOf(sectionId).filter((x) => x.Flag_Wajib === 'Y').length;
        },
        onSectionDragStart(sec) {
            this.dragSectionId = sec.Id_SOP_Section;
        },
        onSectionDragEnd() {
            this.dragSectionId = null;
            this.dragOverSectionId = null;
        },
        onSectionZoneDrop(sec) {
            if (this.dragSectionId) {
                this.reorderSectionTo(sec);
                return;
            }
            if (this.dragId) this.onSectionDrop(sec); // pindah item ke section (pola lama)
        },
        reorderSectionTo(targetSec) {
            const from = this.sections.findIndex((s) => s.Id_SOP_Section === this.dragSectionId);
            const to = this.sections.findIndex((s) => s.Id_SOP_Section === targetSec.Id_SOP_Section);
            this.dragOverSectionId = null;
            if (from < 0 || to < 0 || from === to) {
                this.dragSectionId = null;
                return;
            }
            const arr = this.sections.slice();
            const [moved] = arr.splice(from, 1);
            arr.splice(to, 0, moved);
            this.sections = arr;
            this.dragSectionId = null;
            this.persistSectionOrder();
        },
        /**
         * Urutan baru sudah tampak di layar SEBELUM server tahu.
         *
         * Susunannya dipindahkan optimistis saat dilepas, jadi tanpa tanda
         * apa pun tak ada cara membedakan "sudah tersimpan" dari "sedang
         * dikirim" — dan kegagalan yang menjentikkan urutannya kembali datang
         * seperti layar yang membatalkan perbuatan orang sendiri. `await` pada
         * pemulihannya juga bukan hiasan: tanpa itu pil "menyimpan urutan"
         * padam sementara struktur yang benar masih dalam perjalanan.
         */
        async persistSectionOrder() {
            this.loading.urutan = true;
            try {
                await axios.post(`${API}/section/reorder`, { ids: this.sections.map((s) => s.Id_SOP_Section) });
            } catch (e) {
                ElMessage.error('Gagal menyimpan urutan section');
                await this.reloadItems();
            } finally {
                this.loading.urutan = false;
            }
        },

        /* ---- Item ---- */
        async toggleItem(it) {
            if (this.openItemId === it.Id_SOP_Paket_Item) {
                if (!(await this.guardDirty())) return;
                this.openItemId = null;
                this.itemFormPristine = null;
                return;
            }
            if (!(await this.guardDirty())) return;
            this.adding = false;
            this.openItemId = it.Id_SOP_Paket_Item;
            this.itemForm = {
                id: it.Id_SOP_Paket_Item,
                Id_SOP_Section: it.Id_SOP_Section,
                Tipe_Item: it.Tipe_Item,
                _origTipe: it.Tipe_Item,
                Judul_Item: it.Judul_Item,
                // Fase 6: identitas item ber-versi milik dokumennya.
                Id_SOP_Dokumen: it.Id_SOP_Dokumen || null,
                /* Ikut dibawa supaya editor bisa memperingatkan item yang
                   dokumennya belum punya versi terbit — pointer kosong berarti
                   karyawan tak menerima isi apa pun. Tak dikirim balik ke
                   server: pointer digeser penerbitan versi, bukan formulir. */
                Id_Item_Versi_Aktif: it.Id_Item_Versi_Aktif || null,
                /* Dihitung server pada daftar item — dipakai keterangan
                   "dokumen ini juga dipakai N paket lain". Turunan, tak ikut
                   dikirim balik (payloadItem membuangnya). */
                Paket_Lain_Pemakai: it.Paket_Lain_Pemakai ?? 0,
                Nama_Departemen: it.Nama_Departemen || it.Departemen || '',
                Nama_Jenis: it.Nama_Jenis || it.Jenis_Dokumen || '',
                Versi: it.Versi ?? it.Versi_Ke ?? it.Versi_String ?? it.Dokumen_Versi_Aktif ?? null,
                Versi_String: it.Versi_String || it.Dokumen_Versi_Aktif || '',
                Deskripsi_Item: it.Deskripsi_Item,
                Nomor_SOP_Resmi: it.Nomor_SOP_Resmi,
                Konten_Teks: it.Konten_Teks,
                URL_Tautan: it.URL_Tautan,
                Label_Tautan: it.Label_Tautan,
                Mode_Akses: it.Mode_Akses || 'VIEW_ONLY',
                Jenis_Quiz: it.Jenis_Quiz,
                Nilai_Minimum: it.Nilai_Minimum,
                Id_Master_Ujian: it.Id_Master_Ujian,
                Nama_Ujian: it.Nama_Ujian,
                _origUjian: it.Id_Master_Ujian,
                Flag_Camera: it.Flag_Camera === 'Y' ? 'Y' : 'T',
                Flag_Wajib: it.Flag_Wajib === 'Y',
                Flag_Highlight: it.Flag_Highlight === 'Y',
                Flag_Watermark: it.Flag_Watermark !== 'T',
                // Token concurrency: dikirim balik saat simpan supaya server bisa menolak
                // penimpaan bila admin lain sudah mengubah baris ini lebih dulu.
                Row_Ver: it.Row_Ver || null,
            };
            this.itemFormPristine = this.formSig(this.itemForm);
        },
        async startNewItem(sec) {
            if (!(await this.guardDirty())) return;
            this.openItemId = null;
            this.addingSection = sec.Id_SOP_Section;
            this.itemForm = {
                id: null,
                Id_SOP_Section: sec.Id_SOP_Section,
                /* Tipe bawaan diambil dari daftar yang MENYALA, bukan ditulis
                   'DOKUMEN' begitu saja: kalau tipe itu sedang dimatikan, form
                   lahir memegang tipe yang paletnya bahkan tak merendernya, dan
                   penyimpanannya ditolak server tanpa ada chip yang bisa diklik
                   untuk memperbaikinya. */
                Tipe_Item: this.tipeList[0] || 'DOKUMEN',
                Judul_Item: '',
                Id_SOP_Dokumen: null,
                Mode_Akses: 'VIEW_ONLY',
                Flag_Wajib: true,
                Flag_Highlight: false,
                Flag_Watermark: true,
                Id_Master_Ujian: null,
                Nama_Ujian: null,
                Flag_Camera: 'T',
            };
            this.itemFormPristine = this.formSig(this.itemForm);
            this.adding = true;
        },
        cancelAdding() {
            this.adding = false;
            this.addingSection = null;
            this.itemForm = {};
            this.itemFormPristine = null;
        },
        cancelEdit() {
            this.openItemId = null;
            this.itemForm = {};
            this.itemFormPristine = null;
        },
        isValidUrl(s) {
            try {
                const u = new URL(String(s));
                return u.protocol === 'http:' || u.protocol === 'https:';
            } catch (e) {
                return false;
            }
        },
        validateItemForm() {
            const f = this.itemForm;
            /* FASE 6: identitas item ber-versi milik Dokumen, jadi judulnya
               tak lagi diketik admin — kecuali di satu jalur yang belum bisa
               menautkan dokumen: item BARU pada paket yang sudah terbit, yang
               lahir di overlay draft tanpa kolom dokumen.

               Cermin dari pastikanIdentitasItem() di server. Sebelumnya sisi ini
               menerima "konten Library saja" untuk semua kasus sementara server
               menuntut dokumen, sehingga setiap penyimpanan item berkas baru
               ditolak 422 dengan pesan mentah Laravel. */
            const berdokumen = ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS'].includes(f.Tipe_Item);
            /* Cermin PERSIS dari ItemFields::dariDokumen. Item baru di paket
               terbit lahir di overlay draft; ia menuntut dokumen hanya bila
               overlaynya sanggup menyimpan tautannya (DDL 33). Kalau dua sisi
               ini berselisih, layar meminta yang satu sementara servernya
               menuntut yang lain — dan itemnya mustahil disimpan. */
            const dariDokumen =
                berdokumen && (!(!f.id && this.paketPublished) || this.dukungDokumenDraft);
            if (dariDokumen) {
                if (!f.Id_SOP_Dokumen) {
                    /* Satu-satunya jalan yang tersisa, jadi kalimatnya menyebut
                       jalan itu. "Atau buat dokumen baru" dicabut bersama
                       jalurnya — menawarkan pintu yang sudah tak ada membuat
                       admin mencarinya, tak menemukannya, lalu menyimpulkan
                       layarnya rusak. */
                    return 'Pilih Dokumen dulu. Dokumen baru dibuat dan disahkan di Master Dokumen.';
                }
            } else if (!f.Judul_Item || !f.Judul_Item.trim()) {
                return 'Judul wajib diisi';
            }
            if (f.Tipe_Item === 'TAUTAN') {
                if (!f.URL_Tautan || !f.URL_Tautan.trim()) return 'URL tautan wajib diisi';
                if (!this.isValidUrl(f.URL_Tautan)) return 'URL tautan harus diawali http:// atau https://';
            }
            if (f.Tipe_Item === 'KUIS' && f.Nilai_Minimum != null && f.Nilai_Minimum !== '') {
                const n = Number(f.Nilai_Minimum);
                if (Number.isNaN(n) || n < 0 || n > 100) return 'Nilai minimum kuis harus 0–100';
            }
            // Server memasang requiredIf(KUIS + ujian terpilih); tanpa cek ini user hanya
            // menerima pesan validasi Laravel mentah, bukan peringatan yang bisa ditindaklanjuti.
            if (f.Tipe_Item === 'KUIS' && f.Id_Master_Ujian && (f.Nilai_Minimum == null || f.Nilai_Minimum === '')) {
                return 'Nilai minimum lulus wajib diisi untuk item kuis';
            }
            return null;
        },
        /**
         * Samakan formulir yang masih terbuka dengan baris hasil muat ulang.
         *
         * Nilai TURUNAN tak ikut berubah sendiri saat disimpan. Mengganti
         * dokumen menggeser pointer versi aktif item — tapi itu terjadi di
         * server, dan formulir yang masih memegang pointer lama terus
         * menyalakan peringatan "belum punya versi berlaku" walau
         * penyimpanannya berhasil. Admin membacanya sebagai gantinya yang
         * gagal, lalu mencoba lagi.
         *
         * Dipanggil SESUDAH reloadItems(), dan menandai ulang formulirnya
         * bersih: kalau tidak, penyegaran ini sendiri yang membuatnya terbaca
         * kotor sesaat setelah pesan "tersimpan".
         */
        segarkanFormDariBaris() {
            if (!this.itemForm?.id) return;

            const baris = this.items.find((x) => x.Id_SOP_Paket_Item === this.itemForm.id);
            if (!baris) return;

            this.itemForm.Id_SOP_Dokumen = baris.Id_SOP_Dokumen || null;
            this.itemForm.Id_Item_Versi_Aktif = baris.Id_Item_Versi_Aktif || null;
            this.itemForm.Judul_Item = baris.Judul_Item;
            this.itemForm.Nomor_SOP_Resmi = baris.Nomor_SOP_Resmi;
            this.itemForm.Versi = baris.Versi ?? baris.Versi_Ke ?? baris.Versi_String ?? null;
            this.itemForm.Versi_String = baris.Versi_String || '';
            /* Tebakan sisi klien dilepas: sejak baris ini, pointer dari server
               yang jadi sumber kebenarannya. */
            this.itemForm.Dokumen_Punya_Versi = null;

            this.itemFormPristine = this.formSig(this.itemForm);
        },
        async saveItem() {
            const err = this.validateItemForm();
            if (err) {
                ElMessage.warning(err);
                return;
            }
            const wasNew = !this.itemForm.id;
            /* Pemeriksaan "pilih konten dari library" DICABUT bersama
               pemilihnya. Berkas item selalu datang dari versi aktif dokumennya;
               tak ada lagi jalur di mana Builder memegang konten sendiri. */

            // Ganti ujian KUIS pada paket terbit = versi baru → penerima wajib ujian ulang
            const gantiUjian =
                !wasNew &&
                this.itemForm.Tipe_Item === 'KUIS' &&
                this.itemForm._origUjian &&
                this.itemForm.Id_Master_Ujian &&
                this.itemForm.Id_Master_Ujian !== this.itemForm._origUjian &&
                this.current.Status === 'PUBLISHED';
            if (gantiUjian) {
                const lanjut = await this.tanya(
                    'Mengganti ujian menerbitkan VERSI BARU. Semua penerima yang sudah lulus akan WAJIB ujian ulang.',
                    'Ganti Ujian',
                    { confirmButtonText: 'Ya, ganti', ragam: 'danger', ikonTombol: 'bi-shuffle' },
                );
                if (!lanjut) return;
            }

            const payload = payloadItem(this.itemForm);

            this.loading.save = true;
            try {
                if (this.itemForm.id) {
                    await axios.put(`${API}/item/update/${this.itemForm.id}`, payload);
                } else {
                    await axios.post(`${API}/paket/${this.current.Id_SOP_Paket}/item/store`, payload);
                }

                /* DITANDAI BERSIH SEBELUM pesan sukses, dan sebelum reloadItems().
                   Urutan ini bukan selera. Selama penandaannya menunggu di belakang
                   reloadItems(), ada jeda selebar satu permintaan jaringan ketika
                   layar sudah berkata "Item tersimpan" sedangkan editor masih
                   menganggap formulirnya kotor. */
                if (!wasNew) {
                    this.itemForm._origUjian = this.itemForm.Id_Master_Ujian; // sinkron setelah simpan
                    this.itemForm._origTipe = this.itemForm.Tipe_Item;
                }
                this.itemFormPristine = this.formSig(this.itemForm);

                ElMessage.success('Item tersimpan');
                await this.reloadItems();

                if (wasNew) {
                    this.adding = false;
                    this.addingSection = null;
                    this.itemForm = {};
                    this.itemFormPristine = null;
                } else {
                    this.segarkanFormDariBaris();
                }
            } catch (e) {
                if (e.response?.status === 409) {
                    await this.handleKonflik(e.response.data.message);
                } else {
                    ElMessage.error(e.response?.data?.message || 'Gagal menyimpan item');
                }
            } finally {
                this.loading.save = false;
            }
        },
        async hapusItem(it) {
            if (!it) return;

            /* MODALNYA DULU, angkanya menyusul.
               Sebelumnya dampak diambil lebih dulu lalu modal dibuka — jadi ada
               jeda selebar satu permintaan jaringan ketika tombol sudah ditekan
               dan layar belum berubah sama sekali. Klik yang tak menghasilkan
               apa-apa terbaca sebagai klik yang tak terdaftar, dan orang
               menekannya lagi. */
            const janji = this.confirmAndRun(
                `Item "${it.Judul_Item}" dikeluarkan dari paket ini. Dokumennya sendiri tidak dihapus dan tetap dipakai paket lain.`,
                'Hapus Item',
                {
                    ragam: 'danger',
                    ikonTombol: 'bi-trash',
                    memuatStatistik: true,
                    confirmButtonText: 'Hapus',
                    loadingText: 'Menghapus…',
                    errorText: 'Gagal menghapus',
                },
                async () => {
                    await axios.delete(`${API}/item/delete/${it.Id_SOP_Paket_Item}`);
                    ElMessage.success('Item dihapus');
                    this.openItemId = null;
                    this.itemForm = {};
                    await this.reloadItems();
                },
            );

            this.isiDampakHapus(it, this.konfirmasi.tiket);

            await janji;
        },
        /**
         * Isi angka dampak ke modal yang SUDAH terbuka.
         *
         * Tiketnya diperiksa sebelum menempel: jawaban bisa tiba setelah admin
         * menutup konfirmasi ini dan membuka yang lain, dan angka milik item
         * sebelumnya yang menempel di konfirmasi item berikutnya adalah
         * kekeliruan yang justru mendorong keputusan yang salah.
         */
        async isiDampakHapus(it, tiket) {
            try {
                const r = await axios.get(`${API}/item/${it.Id_SOP_Paket_Item}/dampak`);
                if (this.konfirmasi.tiket !== tiket) return;

                const d = r.data?.result || {};
                if (d.progres) {
                    this.konfirmasi.statistik = [
                        { nilai: d.progres, label: 'karyawan ditugaskan' },
                        { nilai: d.selesai, label: 'sudah menyelesaikan' },
                    ];
                }
                if (d.paket_terbit) {
                    this.konfirmasi.catatan = d.draft_overlay
                        ? 'Paket ini sudah terbit: penghapusannya baru berlaku setelah revisi paket diterbitkan.'
                        : 'Paket ini sudah terbit — catatan baca yang menempel pada item ini ikut kehilangan acuannya.';
                }
            } catch (e) {
                /* Gagal menghitung TIDAK mengunci tombolnya: ini penolong
                   keputusan, bukan syarat. Yang tak boleh adalah tombol yang
                   terkunci selamanya karena satu permintaan gagal. */
                if (this.konfirmasi.tiket === tiket) {
                    this.konfirmasi.catatan = 'Dampak penghapusan tak bisa dihitung — lanjutkan dengan hati-hati.';
                }
            } finally {
                if (this.konfirmasi.tiket === tiket) this.konfirmasi.memuatStatistik = false;
            }
        },

        /* ---- DnD ---- */
        onItemDragStart(it) {
            this.dragId = it.Id_SOP_Paket_Item;
        },
        onItemDrop(target) {
            this.dragOverId = null;
            if (!this.dragId || this.dragId === target.Id_SOP_Paket_Item) {
                this.dragId = null;
                return;
            }
            this.moveItem(this.dragId, target.Id_SOP_Paket_Item, target.Id_SOP_Section);
            this.dragId = null;
        },
        onSectionDrop(sec) {
            if (!this.dragId) return;
            this.moveItem(this.dragId, null, sec.Id_SOP_Section);
            this.dragId = null;
            this.dragOverId = null;
        },
        moveItem(draggedId, targetId, targetSection) {
            const arr = this.items.slice();
            const from = arr.findIndex((x) => x.Id_SOP_Paket_Item === draggedId);
            if (from < 0) return;
            const [moved] = arr.splice(from, 1);
            moved.Id_SOP_Section = targetSection;
            if (targetId) {
                const to = arr.findIndex((x) => x.Id_SOP_Paket_Item === targetId);
                arr.splice(to < 0 ? arr.length : to, 0, moved);
            } else {
                let lastIdx = -1;
                arr.forEach((x, i) => {
                    if (x.Id_SOP_Section === targetSection) lastIdx = i;
                });
                arr.splice(lastIdx + 1, 0, moved);
            }
            this.items = arr;
            this.persistOrder();
        },
        /** Lihat catatan di persistSectionOrder — alasannya sama persis. */
        async persistOrder() {
            const payload = this.items.map((x) => ({ id: x.Id_SOP_Paket_Item, id_section: x.Id_SOP_Section }));
            this.loading.urutan = true;
            try {
                await axios.post(`${API}/item/reorder`, { items: payload });
            } catch (e) {
                ElMessage.error('Gagal menyimpan urutan');
                await this.reloadItems();
            } finally {
                this.loading.urutan = false;
            }
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.pr-ico {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.pr-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.status-tag {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    padding: 0.24rem 0.5rem;
    border-radius: 999px;
    white-space: nowrap;
}
.status-tag.ok {
    background: rgba(16, 185, 129, 0.14);
    color: var(--hcis-emerald-700);
}
.status-tag.bad {
    background: rgba(239, 68, 68, 0.14);
    color: var(--hcis-red-700);
}
.status-tag.idle {
    background: #eef2f7;
    color: var(--hcis-slate-500);
}
.linklike {
    border: 0;
    background: transparent;
    color: var(--hcis-indigo-600);
    font-weight: 700;
    padding: 0;
    cursor: pointer;
    text-decoration: underline;
}

.pk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.15rem;
}
@media (max-width: 575px) {
    .pk-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 0.8rem;
    }
}

/* ── EXECUTIVE KPI SUMMARY CARDS ─────────────────────────────────── */
.kpi-stat-card {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    padding: 1rem 1.2rem;
    background: var(--hcis-white);
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 16px;
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.05);
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}
.kpi-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -8px rgba(15, 23, 42, 0.12);
    border-color: var(--hcis-slate-300);
}
.kpi-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.kpi-stat-icon.brand {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}
.kpi-stat-icon.ok {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-600);
}
.kpi-stat-icon.idle {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-600);
}
.kpi-stat-icon.dok {
    background: rgba(14, 165, 233, 0.12);
    color: var(--hcis-sky-600);
}
.kpi-stat-content {
    min-width: 0;
}
.kpi-stat-val {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.1;
    letter-spacing: -0.02em;
}
.kpi-stat-lbl {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    margin-top: 0.15rem;
    white-space: nowrap;
}

.pk-code-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pk-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border-radius: 999px;
    padding: 0.16rem 0.5rem;
}
.pk-chip.release {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.12);
}
.pk-chip.amber {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.14);
}

.pr-thumb {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    overflow: hidden;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    font-size: 1.15rem;
}
.pr-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.sop-pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.1rem;
    padding-top: 0.9rem;
    border-top: 1px solid var(--border-soft, #eef2f7);
}
.sop-pager-info {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    font-variant-numeric: tabular-nums;
}
@media (max-width: 575px) {
    .sop-pager {
        gap: 0.5rem;
    }
    .sop-pager-info {
        font-size: 0.72rem;
    }
}

/* ===== wizard step bar ===== */
.wiz-bar {
    gap: 1rem;
}
.wiz-steps {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.wiz-step {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 0.3rem 0.5rem;
    border-radius: 999px;
}
.wiz-step.disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.wiz-dot {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #fff;
    border: 2px solid var(--border, #dbe1ea);
    color: var(--hcis-slate-400);
    font-size: 0.82rem;
    transition: all 0.2s ease;
}
.wiz-step.active .wiz-dot {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-500);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
}
.wiz-step.done .wiz-dot {
    border-color: var(--hcis-emerald-500);
    background: var(--hcis-emerald-500);
    color: #fff;
}
.wiz-label {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    white-space: nowrap;
}
.wiz-step.active .wiz-label {
    color: var(--hcis-indigo-600);
}
.wiz-step.done .wiz-label {
    color: var(--hcis-emerald-700);
}
@media (max-width: 575px) {
    .wiz-label {
        display: none;
    }
}

/* ===== step 1 meta grid ===== */
.meta-grid {
    display: grid;
    grid-template-columns: minmax(260px, 320px) 1fr;
    gap: 2rem;
}
@media (max-width: 767px) {
    .meta-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}
.abs-radios {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.abs-radio {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.75rem 0.85rem;
    border: 1.5px solid var(--border, var(--hcis-slate-200));
    border-radius: 14px;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease;
}
.abs-radio input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.abs-radio:hover {
    border-color: var(--hcis-indigo-200);
}
.abs-radio.active {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.05);
}
.ar-ico {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.1);
    flex-shrink: 0;
}
.ar-ico.amber {
    color: var(--hcis-amber-500);
    background: rgba(245, 158, 11, 0.12);
}

/* Riwayat aktivitas paket — timeline ringkas & audit trail */
.riwayat-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
}
.riwayat-list {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 0.3rem;
}
.riwayat-row {
    display: flex;
    gap: 0.9rem;
}
.riwayat-rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 34px;
    flex-shrink: 0;
    position: relative;
}
.riwayat-dot {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    color: var(--hcis-slate-600);
    background: var(--hcis-white);
    border: 2px solid var(--hcis-slate-200);
    border-radius: 50%;
    font-size: 0.8rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}
.riwayat-dot.tone-added {
    color: var(--hcis-emerald-600);
    border-color: var(--hcis-emerald-200);
    background: var(--hcis-emerald-50);
}
.riwayat-dot.tone-danger {
    color: var(--hcis-red-600);
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
}
.riwayat-dot.tone-publish {
    color: var(--hcis-violet-600);
    border-color: #ddd6fe;
    background: var(--hcis-violet-50);
}
.riwayat-dot.tone-edit {
    color: var(--hcis-blue-600);
    border-color: #bfdbfe;
    background: #eff6ff;
}
.riwayat-line {
    width: 2px;
    flex-grow: 1;
    background: var(--hcis-slate-200);
    margin: 4px 0;
}
.riwayat-row:last-child .riwayat-line {
    display: none;
}
.riwayat-content {
    flex-grow: 1;
    min-width: 0;
    padding-bottom: 0.9rem;
}
.riwayat-card-inner {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.riwayat-card-inner:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}
.riwayat-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
}
.rw-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    display: grid;
    place-items: center;
    font-size: 0.7rem;
}
.rw-actor {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}
.rw-rel-time {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
}
.rw-abs-time {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
}
.rw-desc {
    margin: 0 0 0.4rem;
    font-size: 0.81rem;
    line-height: 1.45;
    color: var(--hcis-slate-700);
}
.rw-diff-box {
    margin: 0.45rem 0;
    padding: 0.45rem 0.65rem;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
    border-radius: 8px;
}
.rw-diff-title {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
    margin-bottom: 0.25rem;
}
.rw-diff {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.rw-diff li {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    font-size: 0.72rem;
}
.rw-diff-field {
    font-weight: 700;
    color: var(--hcis-slate-600);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.rw-diff-lama {
    color: var(--hcis-red-700);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 4px;
    padding: 0.05rem 0.35rem;
    text-decoration: line-through;
}
.rw-diff-baru {
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-green-200);
    border-radius: 4px;
    padding: 0.05rem 0.35rem;
    font-weight: 700;
}
.riwayat-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
}
.riwayat-action {
    display: inline-flex;
    align-items: center;
    padding: 0.12rem 0.45rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.riwayat-ctx {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.12rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 600;
    border-radius: 6px;
    max-width: 18rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.riwayat-ctx.item {
    color: var(--hcis-sky-700);
    background: var(--hcis-sky-100);
    border: 1px solid var(--hcis-sky-200);
}
.riwayat-ctx.ver {
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
    font-weight: 700;
}

.rw-sub {
    font-size: 0.76rem;
    color: var(--hcis-slate-400);
    margin-top: 0.1rem;
}
.rw-filter {
    width: auto;
    min-width: 11rem;
    font-size: 0.78rem;
    padding-top: 0.28rem;
    padding-bottom: 0.28rem;
}
.rw-kaki {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.9rem;
    padding-top: 0.7rem;
    border-top: 1px dashed var(--hcis-slate-200);
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
}

/* Tab referensi: ikut deretan langkah supaya ketemu, tapi tak pernah bertanda
   "selesai" — ia bukan sesuatu yang dituntaskan menuju rilis. */
.wiz-step.referensi .wiz-dot {
    border-style: dashed;
}
.wiz-step.referensi:not(.active) .wiz-label {
    color: var(--hcis-slate-400);
}

/* Chip ringkas kesiapan rilis di kepala panel Struktur. Bisa diklik → Step 3. */
.struktur-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    margin-left: 0.6rem;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.6;
    border: 1px solid transparent;
    border-radius: 999px;
    cursor: pointer;
    transition: filter 0.15s ease;
}
.struktur-chip:hover {
    filter: brightness(0.96);
}
.struktur-chip.tone-warn {
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.14);
    border-color: rgba(245, 158, 11, 0.3);
}
.struktur-chip.tone-ok {
    color: var(--hcis-emerald-800);
    background: rgba(16, 185, 129, 0.13);
    border-color: rgba(16, 185, 129, 0.28);
}

/* Badge perubahan per baris. Dibuat kecil dan tak berwarna kuat supaya daftar
   struktur tetap terbaca sebagai daftar, bukan deretan label. */
.chg-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    flex-shrink: 0;
    align-self: center;
    padding: 0.15rem 0.52rem;
    margin-left: 0.45rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    white-space: nowrap;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
.chg-badge i {
    font-size: 0.7rem;
    line-height: 1;
}
.chg-badge .badge-subtext {
    opacity: 0.85;
    font-weight: 600;
}
.chg-badge.is-unpublished {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.chg-badge.is-unpublished.is-added,
.chg-badge.is-added {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.28);
}
.chg-badge.is-unpublished.is-changed,
.chg-badge.is-changed {
    color: #1d4ed8;
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.28);
}
.chg-badge.is-unpublished.is-ready,
.chg-badge.is-ready {
    color: var(--hcis-violet-700);
    background: rgba(139, 92, 246, 0.12);
    border: 1px solid rgba(139, 92, 246, 0.28);
}
.chg-badge.is-draft {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.28);
}

/* Section terlipat yang memuat perubahan di dalamnya. */
.chg-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-left: 0.4rem;
    vertical-align: middle;
    background: var(--hcis-amber-500);
    border-radius: 50%;
}

/* Pemberitahuan pasif: working draft ini juga disentuh admin lain. Sengaja tenang
   (amber lembut, bukan merah) — ini informasi, bukan kesalahan. */
.draft-shared-note {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.6rem 0.85rem;
    font-size: 0.79rem;
    line-height: 1.45;
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.1);
    border-bottom: 1px solid rgba(245, 158, 11, 0.24);
}
.draft-shared-note i {
    margin-top: 0.1rem;
    color: var(--hcis-amber-500);
    flex-shrink: 0;
}
.draft-shared-note b {
    font-weight: 600;
}
.ar-txt {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}
.ar-txt strong {
    font-size: 0.88rem;
    color: var(--hcis-slate-800);
}
.ar-txt small {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
}
.ar-check {
    margin-left: auto;
    color: var(--hcis-slate-300);
    font-size: 1.2rem;
}
.abs-radio.active .ar-check {
    color: var(--hcis-indigo-500);
}

/* ===== hero (step 2) ===== */
.meta-hero {
    position: relative;
    border-radius: var(--radius-xl, 24px);
    overflow: hidden;
    background-size: cover;
    background-position: center;
    margin-bottom: 1rem;
    min-height: 210px;
    box-shadow: 0 22px 55px -30px rgba(15, 23, 42, 0.55);
}
.mh-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        102deg,
        rgba(15, 23, 42, 0.94) 0%,
        rgba(15, 23, 42, 0.74) 40%,
        rgba(15, 23, 42, 0.34) 76%,
        rgba(15, 23, 42, 0.14) 100%
    );
}
.mh-overlay::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.52) 0%, transparent 42%);
}
.mh-content {
    position: relative;
    padding: 1.05rem 1.35rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-height: 210px;
}
.mh-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.mh-body {
    display: flex;
    align-items: flex-end;
    gap: 1.1rem;
    margin-top: auto;
}
.mh-lead {
    flex: 1 1 auto;
    min-width: 0;
}
.mh-aside {
    flex-shrink: 0;
    align-self: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding-right: 0.4rem;
}
.mh-ring {
    position: relative;
    width: 98px;
    height: 98px;
    border-radius: 50%;
    background: conic-gradient(var(--hcis-white) calc(var(--p, 0) * 1%), rgba(255, 255, 255, 0.22) 0);
    display: grid;
    place-items: center;
    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.55);
}
.mh-ring::before {
    content: '';
    position: absolute;
    inset: 9px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(3px);
}
.mh-ring-num {
    position: relative;
    color: #fff;
    font-weight: 800;
    font-size: 1.4rem;
    line-height: 1;
}
.mh-ring-num small {
    font-size: 0.52em;
    font-weight: 700;
    opacity: 0.85;
}
.mh-ring-cap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    text-align: center;
    max-width: 140px;
    line-height: 1.3;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.45);
}
.mh-ring-cap .bi {
    font-size: 0.9rem;
    flex-shrink: 0;
}
.mh-ring-cap .bi.bi-check-circle-fill {
    color: var(--hcis-emerald-400);
}
.mh-status {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(4px);
}
.mh-status.st-ok {
    background: rgba(16, 185, 129, 0.32);
    border-color: rgba(16, 185, 129, 0.5);
}
.mh-status.st-bad {
    background: rgba(148, 163, 184, 0.3);
    border-color: rgba(148, 163, 184, 0.45);
}
.mh-title {
    color: #fff;
    font-weight: 800;
    font-size: 1.55rem;
    margin: 0;
    line-height: 1.15;
    text-wrap: balance;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.4);
}
.mh-desc {
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.83rem;
    margin: 0.4rem 0 0;
    max-width: 62ch;
    line-height: 1.5;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.4);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.mh-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.65rem;
}
.mh-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.71rem;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(4px);
    border-radius: 999px;
    padding: 0.22rem 0.6rem;
}
.mh-chip.prio.p-kritis {
    background: rgba(239, 68, 68, 0.34);
    border-color: rgba(239, 68, 68, 0.48);
}
.mh-chip.prio.p-tinggi {
    background: rgba(245, 158, 11, 0.32);
    border-color: rgba(245, 158, 11, 0.46);
}

.mh-stats {
    display: flex;
    align-items: stretch;
    margin-top: 0.9rem;
    width: max-content;
    max-width: 100%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(6px);
    border-radius: 14px;
    overflow: hidden;
}
.mh-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1.05rem;
    position: relative;
}
.mh-stat + .mh-stat::before {
    content: '';
    position: absolute;
    left: 0;
    top: 22%;
    bottom: 22%;
    width: 1px;
    background: rgba(255, 255, 255, 0.18);
}
.ms-num {
    color: #fff;
    font-weight: 800;
    font-size: 1.05rem;
    line-height: 1;
}
.ms-num small {
    font-size: 0.6em;
    font-weight: 700;
    opacity: 0.8;
    margin-left: 1px;
}
.ms-lbl {
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.22rem;
}

.stitch-btn.glass {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(4px);
}
.stitch-btn.glass:hover {
    background: rgba(255, 255, 255, 0.28);
    color: #fff;
}

@media (max-width: 575px) {
    .meta-hero,
    .mh-content {
        min-height: 190px;
    }
    .mh-title {
        font-size: 1.28rem;
    }
    .mh-stat {
        padding: 0.45rem 0.8rem;
    }
}

/* ===== section ===== */
.sec {
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: var(--radius-lg, 18px);
    margin-bottom: 0.75rem;
    overflow: hidden;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}
.sec.sec-dragover {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
}
.sec.sec-collapsed .sec-head {
    border-bottom-color: transparent;
}
.sec-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.7rem;
    background: var(--surface-muted, var(--hcis-slate-50));
    border-bottom: 1px solid var(--border-soft, #eef2f7);
}
.sec-drag {
    display: grid;
    place-items: center;
    width: 22px;
    height: 28px;
    color: var(--hcis-slate-300);
    cursor: grab;
    flex-shrink: 0;
}
.sec-drag:hover {
    color: var(--hcis-indigo-500);
}
.sec-drag:active {
    cursor: grabbing;
}
.sec-toggle {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 0;
    background: transparent;
    padding: 0.15rem 0.2rem;
    cursor: pointer;
    text-align: left;
    border-radius: 8px;
}
.sec-toggle:hover {
    background: rgba(99, 102, 241, 0.06);
}
.sec-toggle:focus-visible {
    outline: 2px solid var(--hcis-indigo-500);
    outline-offset: 1px;
}
.sec-caret {
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}
.sec-ico {
    color: var(--hcis-indigo-500);
    flex-shrink: 0;
}
.sec-head-txt {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}
.sec-name {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.9rem;
}
.sec-sub {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
}
.sec-items {
    padding: 0.55rem;
}
.sec-desc-count {
    text-align: right;
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    margin-top: 0.25rem;
}
@media (prefers-reduced-motion: reduce) {
    .sec,
    .sec-caret {
        transition: none;
    }
}
.sec-empty {
    font-size: 0.8rem;
    color: var(--hcis-slate-400);
    padding: 0.5rem 0.3rem;
    margin: 0;
}

.type-palette {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
    gap: 0.45rem;
}
.type-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.65rem 0.35rem;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    transition: all 0.15s ease;
}
.type-chip:hover {
    border-color: var(--hcis-indigo-200);
}
.type-chip.active {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.06);
    color: var(--hcis-indigo-600);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
/* Terkunci: yang terpilih tetap terbaca penuh, sisanya jelas tak bisa ditekan.
   Meredupkan semuanya akan menyembunyikan tipe yang sedang berlaku — justru
   satu-satunya keterangan yang berguna di keadaan ini. */
.type-palette.terkunci .type-chip:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: var(--hcis-slate-50);
}
.type-palette.terkunci .type-chip:disabled:hover {
    border-color: var(--border, #dbe1ea);
}
.type-change-note {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.28);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
    margin-bottom: 0.6rem;
    line-height: 1.4;
}
.type-change-note .bi {
    margin-top: 1px;
    flex-shrink: 0;
}

.acc-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.acc-item {
    border: 1px solid var(--border-soft, #eef2f7);
    border-radius: var(--radius, 12px);
    background: #fff;
    overflow: hidden;
    transition: border-color 0.15s ease;
}
.acc-item.is-open {
    border-color: rgba(99, 102, 241, 0.45);
}
.acc-item.drag-over {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.acc-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.7rem;
    cursor: pointer;
}
.acc-head:hover {
    background: var(--surface-muted, var(--hcis-slate-50));
}
.drag-handle {
    color: var(--hcis-slate-300);
    cursor: grab;
    font-size: 1rem;
}
.drag-handle:active {
    cursor: grabbing;
}
.wajib-tag {
    color: var(--hcis-amber-700);
    font-weight: 700;
}
.acc-caret {
    color: var(--hcis-slate-400);
}
.acc-editor {
    position: relative;
    padding: 0.5rem 0.8rem 0.9rem;
    border-top: 1px dashed var(--border-soft, #eef2f7);
}
/* ══ Keadaan sibuk pada daftar struktur ══

   BUKAN overlay buram seperti .editor-loading-overlay di bawah, dan itu
   disengaja. Penantian di sini biasanya di bawah satu detik dan menimpa daftar
   yang isinya BARU SAJA diubah orang; menutupinya berarti menyembunyikan tepat
   bagian yang sedang dicari mata. Yang dilakukan cukup tiga hal: turunkan
   ketegasannya, matikan interaksinya, dan sebutkan apa yang sedang terjadi. */
.struktur-wrap {
    position: relative;
}

/* Pil dikecualikan dari peredupan — ia satu-satunya yang harus tetap terbaca
   penuh, dan ia anak dari wadah yang sedang diredupkan. */
.struktur-wrap.sibuk > *:not(.sibuk-pil) {
    opacity: 0.5;
    pointer-events: none;
    filter: saturate(0.85);
    transition:
        opacity 0.18s ease,
        filter 0.18s ease;
}

.sibuk-pil {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.28rem 0.7rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.1;
    white-space: nowrap;
    animation: sopFadeIn 0.18s ease-out;
}
.sibuk-pil .spinner-border {
    width: 0.72rem;
    height: 0.72rem;
    border-width: 0.14em;
}

/* Melayang di tengah daftar yang sedang diredupkan, tak ikut mendorong tata
   letak — kalau ia menempati ruang, daftar akan bergeser setiap kali menyegar. */
.sibuk-pil.melayang {
    position: absolute;
    top: 0.85rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 12;
    box-shadow: 0 4px 14px -4px rgba(67, 56, 202, 0.35);
}

.struktur-skel {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.75rem;
}

/* Gagang yang sedang tak boleh dipakai harus TERLIHAT tak boleh dipakai;
   `draggable=false` saja tak mengubah apa pun di layar. */
.sec-drag.mati {
    opacity: 0.4;
    cursor: progress;
}

.editor-loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.86);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 15;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    animation: sopFadeIn 0.22s ease-out;
}
@keyframes sopFadeIn {
    from {
        opacity: 0;
        transform: scale(0.98);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.new-item {
    border-color: rgba(99, 102, 241, 0.45);
    margin-bottom: 0.5rem;
}
.ins-actions {
    display: flex;
    gap: 0.5rem;
}

.wiz-nav {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-soft, #eef2f7);
}

.sop-workspace.split-right {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(440px, 480px) !important;
    align-items: start !important;
}
.preview-pane.is-sticky,
.sop-panel.is-sticky.preview-pane {
    position: sticky !important;
    top: 90px !important;
    align-self: start !important;
    min-width: 0 !important;
    width: 100%;
    max-width: 480px;
    z-index: 20;
    overflow: hidden;
}
.preview-pane .sop-panel-body {
    max-height: calc(100vh - 160px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0;
}

/* ===== step 3 review ===== */
.review-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.2rem;
    flex-wrap: wrap;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 20px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 20px -6px rgba(15, 23, 42, 0.06);
}
.checks {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}
.chk {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 0.28rem 0.7rem;
    border-radius: 999px;
    border: 1px solid transparent;
}
.chk.ok {
    background: rgba(16, 185, 129, 0.1);
    color: var(--hcis-emerald-700);
    border-color: rgba(16, 185, 129, 0.25);
}
.chk.no {
    background: rgba(239, 68, 68, 0.1);
    color: var(--hcis-red-700);
    border-color: rgba(239, 68, 68, 0.25);
}
.review-actions {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    flex-wrap: wrap;
}
.release-diff {
    display: grid;
    gap: 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    border-radius: 14px;
    padding: 0.9rem;
}
.diff-group {
    display: grid;
    gap: 0.55rem;
}
.diff-group-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--hcis-slate-700);
    text-transform: uppercase;
}
.diff-group-title b {
    margin-left: auto;
    min-width: 1.6rem;
    text-align: center;
    border-radius: 999px;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    padding: 0.1rem 0.45rem;
}
.diff-list {
    display: grid;
    gap: 0.45rem;
}
.diff-row {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
}
.diff-action {
    flex: 0 0 auto;
    min-width: 74px;
    text-align: center;
    border-radius: 999px;
    padding: 0.18rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 900;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
}
.diff-action.is-added,
.diff-action.is-ready {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
}
.diff-action.is-changed {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.12);
}
.diff-action.is-removed {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.12);
}
.diff-action.is-draft {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.14);
}
.diff-title {
    color: var(--hcis-slate-800);
    font-weight: 800;
    font-size: 0.88rem;
}
.diff-sub {
    color: var(--hcis-slate-500);
    font-size: 0.76rem;
    margin-top: 0.05rem;
}
.diff-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.35rem;
}
.diff-fields span {
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
}
/* ── Sifat revisi pada panel rilis ────────────────────────────────── */
.rv-sifat {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.6rem;
}
.rv-opsi {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    cursor: pointer;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 10px;
    padding: 0.55rem 0.7rem;
    margin: 0;
}
.rv-opsi.on {
    border-color: #0f766e;
    background: #f0fdfa;
}
.rv-opsi input {
    margin-top: 0.2rem;
    flex: none;
}
.rv-opsi b {
    display: block;
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
}
.rv-opsi em {
    display: block;
    font-style: normal;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.rv-sifat-ket {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    margin: 0.1rem 0 0;
}

.release-diff-empty {
    border: 1px dashed var(--hcis-slate-300);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    font-size: 0.84rem;
    font-weight: 700;
}
.rv-devtoggle {
    display: flex;
    width: max-content;
    gap: 4px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    padding: 5px;
    margin: 0 auto 1.25rem;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}
.rv-devtoggle button {
    border: 0;
    background: transparent;
    color: var(--hcis-slate-500);
    font-weight: 800;
    font-size: 0.82rem;
    padding: 0.5rem 1.1rem;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.rv-devtoggle button:hover {
    color: var(--hcis-slate-800);
}
.rv-devtoggle button.active {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.08),
        0 1px 2px rgba(15, 23, 42, 0.04);
}
.review-stage {
    padding: 0.5rem 0 2.5rem;
    display: flex;
    justify-content: center;
    width: 100%;
}
.rd-devwrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Bar Filter Chips matched to Functional Document */
.sop-kat-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    padding: 0.25rem 0.1rem;
    scrollbar-width: thin;
}
.sop-kat-bar::-webkit-scrollbar {
    height: 4px;
}
.sop-kat-bar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 4px;
}
.sop-kat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.42rem 0.85rem;
    border-radius: 999px;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.sop-kat-chip:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
    transform: translateY(-1px);
}
.sop-kat-chip.active {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.5);
    color: var(--hcis-indigo-600);
    box-shadow:
        0 4px 14px -4px rgba(99, 102, 241, 0.35),
        0 0 0 3px rgba(99, 102, 241, 0.12);
}
.kat-chip-ico {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.12);
    flex-shrink: 0;
}
/* ===================== DOCUMENT BUILDER WIZARD HEADER BAR ===================== */
.sop-builder-wiz-header-bar {
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(12px);
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0 0 var(--hcis-radius-xl, 16px) var(--hcis-radius-xl, 16px);
}

.sop-builder-wiz-header-bar .wiz-steps {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.sop-builder-wiz-header-bar .wiz-step {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid transparent;
    background: rgba(255, 255, 255, 0.06);
    cursor: pointer;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}

.sop-builder-wiz-header-bar .wiz-step:hover:not(.disabled) {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.2);
}

.sop-builder-wiz-header-bar .wiz-step.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.sop-builder-wiz-header-bar .wiz-dot {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.15);
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.78rem;
    transition: all 0.2s ease;
}

.sop-builder-wiz-header-bar .wiz-step.active {
    background: rgba(99, 102, 241, 0.32);
    border-color: rgba(165, 180, 252, 0.45);
}

.sop-builder-wiz-header-bar .wiz-step.active .wiz-dot {
    border-color: var(--hcis-indigo-400);
    background: var(--hcis-indigo-500);
    color: #fff;
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.55);
}

.sop-builder-wiz-header-bar .wiz-step.done .wiz-dot {
    border-color: var(--hcis-emerald-400);
    background: var(--hcis-emerald-500);
    color: #fff;
}

.sop-builder-wiz-header-bar .wiz-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
    white-space: nowrap;
}

.sop-builder-wiz-header-bar .wiz-step.active .wiz-label {
    color: var(--hcis-white);
}

.sop-builder-wiz-header-bar .wiz-step.done .wiz-label {
    color: var(--hcis-emerald-200);
}

.field-hint {
    font-size: 0.72rem;
    line-height: 1.4;
    color: var(--hcis-slate-500);
    margin: 0.35rem 0 0;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.field-hint .bi {
    color: var(--hcis-indigo-500);
    font-size: 0.78rem;
    flex-shrink: 0;
}

/* ===================== LAYOUT DUA KOLOM (RAIL + MAIN) ===================== */
.ly {
    display: grid;
    grid-template-columns: 236px minmax(0, 1fr);
    gap: 1.1rem;
    align-items: start;
}
.ly-rail {
    position: sticky;
    top: 4.75rem;
    z-index: 5;
}
.ly-kartu {
    padding: 0;
    max-height: calc(100vh - 6rem);
    overflow-y: auto;
    scrollbar-width: thin;
    background: var(--hcis-white);
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 16px;
    box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}
.ly-rail-sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--hcis-white);
    padding: 0.65rem 0.65rem 0.45rem;
    border-bottom: 1px solid var(--border-soft, var(--hcis-slate-100));
}
.ly-rail-content {
    padding: 0.45rem 0.65rem 0.75rem;
    flex: 1;
}
.ly-main {
    min-width: 0;
}

/* ══ Pemindah sumbu pengelompokan ══
   Duduk di ATAS rail, bukan di bilah saringan: ia mengubah CARA MELIHAT, bukan
   apa yang terlihat. Menaruhnya bersama saringan akan membuat orang menekan
   "reset" dan kehilangan susunan yang tak pernah dimaksudkan ikut direset.

   Seluruh gaya rail itu sendiri (.rl-*) sudah TIDAK di sini: ia milik
   RailSumbu.vue dan ikut ke mana pun komponennya dipakai. Salinannya yang dulu
   berdiri di berkas ini menggambar benda yang sama dengan angka yang lambat
   laun berbeda. */
/* ══ Switcher Mode Dual: Metrik vs Folder ══ */
.sm-mode-tabs {
    display: flex;
    align-items: center;
    background: var(--surface-soft, var(--hcis-slate-100));
    padding: 3px;
    border-radius: 10px;
    gap: 3px;
    border: 1px solid var(--border-soft, #e7ecf3);
}
.sm-mode-tab {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.35rem 0.5rem;
    border: 1px solid transparent;
    background: transparent;
    border-radius: 7px;
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}
.sm-mode-tab:hover {
    color: var(--text, var(--hcis-slate-800));
    background: rgba(255, 255, 255, 0.6);
}
.sm-mode-tab.on {
    background: var(--hcis-white);
    border-color: rgba(226, 232, 240, 0.7);
    color: var(--primary-dark, var(--hcis-indigo-700));
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.sm-pindah {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.2rem 0.25rem 0.75rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px dashed var(--border-soft, var(--hcis-slate-200));
}
.sm-pindah-label {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-faint, var(--hcis-slate-500));
    display: flex;
    align-items: center;
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

@media (max-width: 991.98px) {
    .ly {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
    .ly-rail {
        position: static;
    }
    .ly-kartu {
        max-height: none;
        overflow: visible;
        padding: 0.5rem;
    }
    /* Bentuk sempit rail diurus RailSumbu.vue sendiri, pada ambang yang sama
       (991.98px). Yang tersisa di sini hanya pemindah sumbunya. */
    .sm-pindah {
        padding-bottom: 0.4rem;
    }

    .sop-panel.is-sticky {
        position: static;
    }
    .preview-pane {
        display: none;
    }
    .preview-pane.mobile-open {
        display: flex;
        position: fixed;
        inset: 0;
        z-index: 1055;
        border-radius: 0;
        overflow: auto;
    }
}
</style>
