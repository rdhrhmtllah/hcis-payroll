<template>
    <div class="ap-cockpit-layout" :class="{ 'is-catalog-collapsed': catalogCollapsed }">
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ══ LEFT PANE: KATALOG PERAN (ROLE SELECTOR / NAVIGATOR)         ══ -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <aside v-show="!catalogCollapsed && (mobileView === 'list' || isDesktop)" class="ap-catalog-pane">
            <div class="ap-catalog-card">
                <!-- Header Katalog -->
                <div class="ap-catalog-header">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="ap-kicker">Katalog Otorisasi</span>
                            <h6 class="ap-catalog-title mb-0">Daftar Peran Modul</h6>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <HcisBadge
                                :label="`${saring.length} Peran`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                            <!-- Desktop Collapse Toggle Button -->
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="layout-sidebar-inset"
                                title="Ciutkan katalog untuk ruang kerja lebih lega"
                                class="d-none d-lg-inline-flex"
                                @click="catalogCollapsed = true"
                            />
                        </div>
                    </div>

                    <!-- Official HcisFilterBar Component -->
                    <HcisFilterBar
                        :search="cari"
                        search-placeholder="Cari kode atau nama peran…"
                        :quick-chips="quickKategoriChips"
                        :active-chip-value="kategoriAktif"
                        :filters="hcisFilterDefinitions"
                        :filter-values="filterValues"
                        :collapsible="true"
                        variant="flat"
                        size="sm"
                        sentiment="brand"
                        class="ap-katalog-filterbar"
                        @update:search="(v) => (cari = v)"
                        @chip-click="onChipClick"
                        @filter-change="onFilterChange"
                        @reset="resetFilters"
                    />
                </div>

                <!-- Scrollable Role List -->
                <div class="ap-catalog-list">
                    <HcisEmptyState
                        v-if="!saring.length"
                        icon="person-badge"
                        title="Tidak ada peran yang cocok"
                        description="Sesuaikan kata kunci pencarian atau kategori filter."
                        size="sm"
                        class="my-4"
                    >
                        <template #actions>
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-counterclockwise"
                                label="Reset Filter"
                                @click="resetFilters"
                            />
                        </template>
                    </HcisEmptyState>

                    <HcisCard
                        v-for="p in saring"
                        :key="p.id"
                        variant="glass"
                        size="sm"
                        interactive
                        class="ap-role-card-item"
                        :sentiment="
                            p.jenis === 'POOL_APPROVAL'
                                ? 'warning'
                                : p.tingkat === 'SUPER_ADMIN'
                                  ? 'purple'
                                  : p.tingkat === 'ADMIN'
                                    ? 'brand'
                                    : 'neutral'
                        "
                        :class="{ 'is-selected': peranDipilih?.id === p.id }"
                        @click="pilihPeran(p)"
                    >
                        <div class="d-flex align-items-center gap-2.5 w-100">
                            <!-- Icon Box Berdasarkan Kategori -->
                            <div class="ap-role-icon flex-shrink-0" :class="infoBadgePeran(p).iconClass">
                                <HcisIcon :name="infoBadgePeran(p).iconName" size="sm" />
                            </div>

                            <div class="min-w-0 flex-grow-1">
                                <!-- Baris Atas: Nama Peran & Badge Status -->
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                    <span class="ap-peran-nama text-truncate" :title="p.nama">{{ p.nama }}</span>
                                    <HcisBadge
                                        :label="infoBadgePeran(p).label"
                                        :variant="infoBadgePeran(p).variant"
                                        appearance="subtle"
                                        size="xs"
                                        class="flex-shrink-0"
                                    />
                                </div>

                                <!-- Baris Tengah: Kode Monospace & Modul -->
                                <div class="ap-peran-meta-row">
                                    <span class="ap-peran-kode">{{ p.kode }}</span>
                                    <span v-if="p.modul" class="ap-peran-modul text-truncate">· {{ p.modul }}</span>
                                </div>

                                <!-- Baris Bawah: Metrik Tag Chip (Pill badges yang jelas) -->
                                <div class="ap-peran-stats-row mt-1.5">
                                    <span v-if="p.lama" class="ap-metric-pill ap-peran-lama">
                                        <HcisIcon name="clock-history" size="2xs" />
                                        <span>Peran lama</span>
                                    </span>
                                    <span
                                        v-if="p.jenis === 'POOL_APPROVAL'"
                                        class="ap-metric-pill ap-metric-pill--warning"
                                    >
                                        <HcisIcon name="diagram-2" size="2xs" />
                                        <span>{{ p.pemegang || 0 }} verifikator</span>
                                    </span>
                                    <span v-else class="ap-metric-pill">
                                        <HcisIcon name="people" size="2xs" />
                                        <span>{{ p.pemegang || 0 }} pemegang</span>
                                    </span>

                                    <span
                                        v-if="p.jenis === 'POOL_APPROVAL' && !p.halaman"
                                        class="ap-metric-pill ap-metric-pill--workflow"
                                    >
                                        <HcisIcon name="shuffle" size="2xs" />
                                        <span>Workflow</span>
                                    </span>
                                    <span v-else class="ap-metric-pill">
                                        <HcisIcon name="grid-3x3" size="2xs" />
                                        <span>{{ p.halaman || 0 }} menu</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Indicator Chevron -->
                            <HcisIcon name="chevron-right" size="xs" class="ap-card-arrow align-self-center" />
                        </div>
                    </HcisCard>
                </div>
            </div>
        </aside>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ══ RIGHT PANE: WORKSPACE & DETAIL PERAN (INSPECTOR)             ══ -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <main v-show="mobileView === 'detail' || isDesktop" class="ap-workspace-pane">
            <div class="ap-workspace-card">
                <!-- Mobile Top Navigation Bar -->
                <div class="ap-mobile-nav d-lg-none">
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-left"
                        label="Daftar Peran"
                        @click="mobileView = 'list'"
                    />
                    <div class="ap-mobile-title text-truncate fw-bold small text-dark">
                        {{ peranDipilih?.nama || 'Detail Peran' }}
                    </div>
                </div>

                <!-- Desktop Bar Saat Katalog Diciutkan -->
                <div v-if="catalogCollapsed" class="ap-uncollapse-bar d-none d-lg-flex">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        leading-icon="layout-sidebar"
                        :label="`Tampilkan Katalog (${saring.length} Peran)`"
                        @click="catalogCollapsed = false"
                    />
                    <div class="text-muted ap-kecil">
                        Katalog disembunyikan untuk memberikan ruang pandang matriks maksimal.
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="memuat" class="p-4">
                    <HcisSkeleton :lines="10" />
                </div>

                <!-- Error State -->
                <div v-else-if="galatDetail" class="p-4">
                    <HcisResultState
                        status="error"
                        variant="flat"
                        size="md"
                        title="Detail peran belum dapat dimuat"
                        description="Data otorisasi dan pemegang peran tidak tersedia saat ini. Coba muat ulang detailnya."
                    >
                        <template #actions>
                            <HcisButton
                                variant="primary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                label="Coba Lagi"
                                @click="buka(peranDipilih)"
                            />
                        </template>
                    </HcisResultState>
                </div>

                <!-- Empty State (Belum ada peran yang dipilih) -->
                <div v-else-if="!detail" class="ap-workspace-empty">
                    <HcisEmptyState
                        icon="sliders"
                        title="Pilih Peran dari Katalog"
                        description="Pilih salah satu peran dari daftar sebelah kiri untuk memeriksa hak matriks akses, mengelola penugasan anggota atau verifikator, serta memantau audit perubahan."
                        size="md"
                    />
                </div>

                <!-- Active Role Workspace Content -->
                <div v-else class="ap-workspace-content">
                    <!-- ══ 1. ROLE HERO BANNER ══ -->
                    <HcisCard
                        variant="glass"
                        :sentiment="isPoolApproval ? 'warning' : detail.tingkat === 'SUPER_ADMIN' ? 'purple' : 'brand'"
                        size="md"
                        class="ap-role-hero mb-3"
                    >
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex align-items-start gap-3 min-w-0 flex-grow-1">
                                <!-- Big Contextual Icon Box -->
                                <div class="ap-hero-icon-box flex-shrink-0" :class="infoBadgePeran(detail).iconClass">
                                    <HcisIcon :name="infoBadgePeran(detail).iconName" size="md" />
                                </div>

                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="ap-kicker">
                                            {{
                                                isPoolApproval
                                                    ? 'ALUR KERJA & POOL VERIFIKATOR'
                                                    : 'DETAIL PERAN & OTORISASI'
                                            }}
                                        </span>
                                    </div>

                                    <!-- Role Title & Identifiers -->
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1.5">
                                        <h4 class="fw-bold mb-0 text-dark ap-hero-title">{{ detail.nama }}</h4>
                                        <HcisBadge
                                            :label="detail.kode"
                                            variant="neutral"
                                            appearance="subtle"
                                            size="sm"
                                            class="font-monospace"
                                        />
                                        <HcisBadge
                                            :label="infoBadgePeran(detail).labelFull"
                                            :variant="infoBadgePeran(detail).variant"
                                            appearance="subtle"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-if="detail.lama"
                                            label="Peran lama"
                                            variant="neutral"
                                            appearance="outline"
                                            size="sm"
                                        />
                                    </div>
                                    <HcisAlert
                                        v-if="detail.lama"
                                        variant="neutral"
                                        appearance="subtle"
                                        class="mb-2"
                                        title="Peran admin lama — akan digabung"
                                        description="Fungsinya sudah tercakup peran Admin modul plus cakupan (misalnya PHL). Peran ini masih dipakai aturan lama, jadi tetap berlaku sampai modul pindah ke mode BARU. Untuk penugasan baru, pakai Admin modul bila sudah tersedia."
                                    />
                                    <HcisAlert
                                        v-if="detail.kembar?.length"
                                        variant="warning"
                                        appearance="subtle"
                                        class="mb-2"
                                        :title="`Hak identik dengan ${detail.kembar.length} peran lain`"
                                        :description="`Halaman dan aksinya persis sama dengan: ${detail.kembar.map((k) => k.nama).join(', ')}. Kandidat digabung; bedakan lewat cakupan, bukan peran baru.`"
                                    />
                                    <HcisAlert
                                        v-if="detail.tingkat === 'SUPER_ADMIN'"
                                        variant="purple"
                                        appearance="subtle"
                                        class="mb-2"
                                        title="Akses Penuh Seluruh Modul"
                                        description="Super Admin otomatis memiliki hak akses penuh ke seluruh menu dan fungsi operasional di modul ini. Matriks halaman mencakup seluruh halaman modul secara otomatis."
                                    />

                                    <p v-if="detail.deskripsi" class="text-muted small mb-2 ap-hero-desc">
                                        {{ detail.deskripsi }}
                                    </p>

                                    <!-- Quick Structured Facts Ribbon -->
                                    <div class="ap-hero-meta-row d-flex flex-wrap gap-2 align-items-center mt-2">
                                        <!-- Modul Badge -->
                                        <div class="ap-hero-meta-pill">
                                            <HcisIcon name="folder2-open" size="xs" class="text-muted" />
                                            <span
                                                >Modul: <strong>{{ detail.modul || 'Lintas Modul' }}</strong></span
                                            >
                                        </div>

                                        <!-- Members Count Button -->
                                        <button
                                            type="button"
                                            class="ap-hero-meta-btn"
                                            :class="{ 'is-active': sub === 'pemegang' }"
                                            title="Buka tab pemegang peran"
                                            @click="sub = 'pemegang'"
                                        >
                                            <HcisIcon
                                                name="people-fill"
                                                size="xs"
                                                :color="isPoolApproval ? 'warning' : 'brand'"
                                            />
                                            <span>
                                                <strong>{{ detail.pemegang?.length || 0 }}</strong>
                                                {{ isPoolApproval ? 'verifikator aktif' : 'pemegang aktif' }}
                                            </span>
                                        </button>

                                        <!-- Authorized Pages Button -->
                                        <button
                                            type="button"
                                            class="ap-hero-meta-btn"
                                            :class="{ 'is-active': sub === 'hak' }"
                                            title="Buka tab matriks hak halaman"
                                            @click="sub = 'hak'"
                                        >
                                            <HcisIcon name="grid-3x3-gap-fill" size="xs" color="brand" />
                                            <span>
                                                <strong>{{ detail.matriks?.length || 0 }}</strong>
                                                halaman terotorisasi
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons Group -->
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <HcisButton
                                    v-if="aksi"
                                    variant="primary"
                                    size="sm"
                                    :leading-icon="isPoolApproval ? 'person-check' : 'person-plus'"
                                    :label="isPoolApproval ? 'Tugaskan Verifikator' : 'Tugaskan Peran Ini'"
                                    @click="
                                        aksi.tugaskan({
                                            idRole: detail.id,
                                            peranInfo: detail,
                                            setelah: () => buka({ id: detail.id }),
                                        })
                                    "
                                />
                                <HcisButton
                                    v-if="systemAdmin && !isPoolApproval && !ubahTingkat"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="arrow-down-up"
                                    label="Ubah Tingkat"
                                    @click="mulaiUbahTingkat"
                                />
                                <HcisButton
                                    variant="ghost"
                                    size="sm"
                                    leading-icon="arrow-clockwise"
                                    title="Muat ulang detail"
                                    @click="buka(peranDipilih)"
                                />
                            </div>
                        </div>

                        <!-- Inline Form Ubah Tingkat (System Admin) -->
                        <HcisCard v-if="ubahTingkat" variant="flat" size="sm" class="mt-3">
                            <label class="form-label small fw-semibold" for="tingkat-peran">Tingkat Peran Baru</label>
                            <HcisSelect
                                id="tingkat-peran"
                                v-model="tingkatBaru"
                                :clearable="false"
                                aria-label="Tingkat peran"
                                placeholder="Pilih tingkat"
                            >
                                <HcisSelectOption
                                    v-for="k in ['SUPER_ADMIN', 'ADMIN', 'PENGGUNA']"
                                    :key="k"
                                    :value="k"
                                    :label="infoTingkat[k].label"
                                />
                            </HcisSelect>
                            <HcisTextarea
                                id="alasan-tingkat"
                                v-model="alasanTingkat"
                                class="mt-2"
                                label="Alasan Perubahan (wajib, tercatat di audit)"
                                :rows="2"
                            />
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <HcisButton variant="ghost" size="sm" label="Batal" @click="ubahTingkat = false" />
                                <HcisButton
                                    size="sm"
                                    label="Simpan Tingkat"
                                    variant="primary"
                                    :loading="menyimpanTingkat"
                                    :disabled="alasanTingkat.trim().length < 5 || tingkatBaru === detail.tingkat"
                                    @click="simpanTingkat"
                                />
                            </div>
                        </HcisCard>
                    </HcisCard>

                    <!-- ══ 2. WORKSPACE ENCLOSED SUB-TABS (Clean Enterprise Navigation) ══ -->
                    <div class="ap-workspace-tabs-container mb-3">
                        <HcisTabs
                            v-model="sub"
                            :tabs="daftarSub"
                            variant="enclosed"
                            size="md"
                            class="ap-workspace-tabs"
                        />
                    </div>

                    <!-- ══ SUBTAB KEMAMPUAN FITUR BISNIS (STANDAR BARU) ══ -->
                    <div v-if="sub === 'kemampuan'" class="ap-tab-content">
                        <HcisEmptyState
                            v-if="!detail.daftar_kemampuan?.length"
                            icon="check2-all"
                            title="Belum Ada Kemampuan Fitur Bisnis"
                            description="Peran ini belum didaftarkan pada master fitur bisnis terstandarisasi (baru tersedia pada modul percontohan ESS.CUTI). Silakan periksa tab Hak Akses Menu (Lanjutan)."
                            size="sm"
                            class="mb-3"
                        />
                        <HcisCard v-else variant="glass" size="md" class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="ap-card-title mb-0.5">Daftar Kemampuan Fitur Bisnis</h6>
                                    <span class="ap-card-subtitle">
                                        Wewenang kerja nyata yang didapatkan oleh peran ini berdasarkan master fitur
                                        sistem.
                                    </span>
                                </div>
                                <HcisBadge
                                    :label="`${detail.daftar_kemampuan.length} Kemampuan`"
                                    variant="brand"
                                    appearance="subtle"
                                    size="sm"
                                />
                            </div>

                            <div class="ap-kemampuan-table-wrap">
                                <table class="ap-kemampuan-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%">Fitur</th>
                                            <th style="width: 35%">Kemampuan Bisnis</th>
                                            <th style="width: 12%">Aksi Sistem</th>
                                            <th style="width: 15%">Cakupan Data Sah</th>
                                            <th style="width: 13%">Tipe Wewenang</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="k in detail.daftar_kemampuan" :key="k.kode_kemampuan">
                                            <td>
                                                <div class="fw-bold text-dark text-truncate">{{ k.nama_fitur }}</div>
                                                <div class="font-monospace ap-kecil text-muted">{{ k.kode_fitur }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ k.nama_kemampuan }}</div>
                                                <div class="font-monospace ap-kecil text-muted">
                                                    {{ k.kode_kemampuan }}
                                                </div>
                                            </td>
                                            <td>
                                                <HcisBadge
                                                    :label="k.jenis_aksi"
                                                    :variant="
                                                        k.jenis_aksi === 'APPROVE'
                                                            ? 'warning'
                                                            : k.jenis_aksi === 'VIEW'
                                                              ? 'neutral'
                                                              : 'brand'
                                                    "
                                                    appearance="subtle"
                                                    size="xs"
                                                />
                                            </td>
                                            <td>
                                                <span class="ap-cakupan-pill font-monospace">
                                                    {{ k.cakupan_tersedia }}
                                                </span>
                                            </td>
                                            <td>
                                                <HcisBadge
                                                    :label="
                                                        k.flag_wajib_approval === 'Y' ? 'Verifikator' : 'Operasional'
                                                    "
                                                    :variant="k.flag_wajib_approval === 'Y' ? 'warning' : 'neutral'"
                                                    appearance="subtle"
                                                    size="xs"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </HcisCard>
                    </div>

                    <!-- ══ 3. SUBTAB 1: MATRIKS HAK HALAMAN / WORKFLOW ══ -->
                    <div v-if="sub === 'hak'" class="ap-tab-content">
                        <!-- Kartu Wewenang Nyata / Alur Bisnis (Bahasa Operasional Dept Head) -->
                        <HcisCard
                            v-if="detail.wewenang_bisnis"
                            variant="glass"
                            size="md"
                            :sentiment="
                                detail.wewenang_bisnis.tipe === 'POOL_APPROVAL'
                                    ? 'warning'
                                    : detail.wewenang_bisnis.tipe === 'SUPER_ADMIN'
                                      ? 'purple'
                                      : detail.wewenang_bisnis.tipe === 'ADMIN'
                                        ? 'brand'
                                        : 'neutral'
                            "
                            class="mb-3 ap-wewenang-hero"
                        >
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <HcisIcon
                                    :name="
                                        detail.wewenang_bisnis.tipe === 'POOL_APPROVAL'
                                            ? 'diagram-2-fill'
                                            : detail.wewenang_bisnis.tipe === 'SUPER_ADMIN'
                                              ? 'shield-lock-fill'
                                              : detail.wewenang_bisnis.tipe === 'ADMIN'
                                                ? 'shield-fill-check'
                                                : 'window-stack'
                                    "
                                    size="sm"
                                    :color="
                                        detail.wewenang_bisnis.tipe === 'POOL_APPROVAL'
                                            ? 'warning'
                                            : detail.wewenang_bisnis.tipe === 'SUPER_ADMIN'
                                              ? 'purple'
                                              : 'brand'
                                    "
                                />
                                <h6 class="mb-0 fw-bold">{{ detail.wewenang_bisnis.judul }}</h6>
                            </div>
                            <p class="small text-dark mb-2.5 leading-relaxed">
                                {{ detail.wewenang_bisnis.deskripsi }}
                            </p>

                            <!-- Jalur & Rantai Alur Persetujuan (Menu & Approval Matrix) -->
                            <div
                                v-if="
                                    detail.wewenang_bisnis.jalur_approval &&
                                    detail.wewenang_bisnis.jalur_approval.length
                                "
                                class="mb-3"
                            >
                                <span class="ap-kicker mb-1.5 d-block text-warning-emphasis">
                                    ALUR PERSETUJUAN TRANSAKSI:
                                </span>
                                <div class="d-flex flex-column gap-2">
                                    <div
                                        v-for="j in detail.wewenang_bisnis.jalur_approval"
                                        :key="j.kode_jalur"
                                        class="ap-jalur-item p-2.5 rounded-3 bg-white border border-warning-subtle shadow-2xs"
                                    >
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                                <HcisIcon name="diagram-3" size="xs" color="warning" />
                                                <span class="fw-bold small text-dark text-truncate">{{
                                                    j.nama_jalur
                                                }}</span>
                                            </div>
                                            <HcisBadge
                                                :label="`Tahap ${j.tahap_ke} dari ${j.total_tahap}`"
                                                variant="warning"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                        </div>

                                        <!-- Visual Rantai Alur Step (Step 1 -> Step 2) -->
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <template v-for="(lk, idx) in j.langkah" :key="lk.urutan">
                                                <span
                                                    class="ap-step-pill"
                                                    :class="{ 'ap-step-pill--target': lk.adalah_peran_ini }"
                                                >
                                                    <HcisIcon
                                                        v-if="lk.adalah_peran_ini"
                                                        name="check-circle-fill"
                                                        size="2xs"
                                                    />
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

                            <!-- Kategori Tamu Unit (Visitor) -->
                            <div
                                v-if="
                                    detail.wewenang_bisnis.kategori_tamu && detail.wewenang_bisnis.kategori_tamu.length
                                "
                                class="mb-2.5"
                            >
                                <span class="ap-kicker mb-1.5 d-block text-warning-emphasis">
                                    KATEGORI TAMU YANG DITANGANI:
                                </span>
                                <div class="d-flex flex-wrap gap-2">
                                    <div
                                        v-for="kt in detail.wewenang_bisnis.kategori_tamu"
                                        :key="kt.kategori || kt"
                                        class="ap-tamu-item d-inline-flex align-items-center gap-1.5 p-1 px-2.5 rounded-2 bg-white border border-warning-subtle shadow-2xs"
                                    >
                                        <HcisIcon
                                            :name="kt.bisa_ajukan ? 'pencil-square' : 'eye'"
                                            size="2xs"
                                            color="warning"
                                        />
                                        <span class="small fw-semibold text-dark">{{
                                            kt.kategori_label || kt.label || kt
                                        }}</span>
                                        <span
                                            class="badge bg-warning-subtle text-warning-emphasis rounded-pill ms-1 ap-kecil"
                                        >
                                            {{ kt.aksi_label || (kt.bisa_ajukan ? 'Lihat & Ajukan' : 'Lihat Jadwal') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lingkup Kuasa & Tanggung Jawab Nyata -->
                            <div
                                v-if="detail.wewenang_bisnis.poin && detail.wewenang_bisnis.poin.length"
                                class="pt-2 border-top border-light-subtle"
                            >
                                <span class="ap-kicker mb-1 d-block">LINGKUP KUASA KERJA:</span>
                                <ul class="ap-wewenang-poin mb-0 ps-3 small text-muted">
                                    <li v-for="(pt, idx) in detail.wewenang_bisnis.poin" :key="idx" class="mb-0.5">
                                        {{ pt }}
                                    </li>
                                </ul>
                            </div>
                        </HcisCard>
                        <!-- Khusus Peran Pool Approval Murni Tanpa Menu Navigasi -->
                        <HcisCard
                            v-if="isPoolApproval && !detail.matriks?.length"
                            variant="default"
                            sentiment="warning"
                            size="md"
                            divider
                            class="mb-3"
                        >
                            <template #header>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                                    <div>
                                        <h6 class="ap-card-title mb-0.5 d-flex align-items-center gap-2">
                                            <HcisIcon name="diagram-2" color="warning" size="sm" />
                                            Karakteristik Peran Alur Persetujuan (Workflow Pool)
                                        </h6>
                                        <span class="ap-card-subtitle">
                                            Peran ini bertugas sebagai verifikator approval dan tidak mengikat menu
                                            navigasi halaman.
                                        </span>
                                    </div>
                                    <HcisBadge
                                        label="Alur Kerja / Workflow"
                                        variant="warning"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                            </template>

                            <div class="py-2">
                                <!-- Diagram Alur Kerja Visual -->
                                <div class="ap-workflow-diagram mb-4">
                                    <div class="ap-step-box">
                                        <div class="ap-step-icon"><HcisIcon name="file-earmark-text" size="sm" /></div>
                                        <div class="ap-step-title">1. Pengajuan Transaksi</div>
                                        <div class="ap-step-desc">
                                            Pengguna mengajukan cuti, lembur, atau form transaksi
                                        </div>
                                    </div>
                                    <div class="ap-step-arrow"><HcisIcon name="arrow-right" size="sm" /></div>
                                    <div class="ap-step-box is-highlight">
                                        <div class="ap-step-icon"><HcisIcon name="diagram-2" size="sm" /></div>
                                        <div class="ap-step-title">2. Pool Routing Verifikator</div>
                                        <div class="ap-step-desc">
                                            Tugas didistribusikan ke seluruh verifikator pool aktif
                                        </div>
                                    </div>
                                    <div class="ap-step-arrow"><HcisIcon name="arrow-right" size="sm" /></div>
                                    <div class="ap-step-box">
                                        <div class="ap-step-icon"><HcisIcon name="check2-circle" size="sm" /></div>
                                        <div class="ap-step-title">3. Keputusan Otorisasi</div>
                                        <div class="ap-step-desc">
                                            Verifikator memvalidasi, menyetujui, atau menolak
                                        </div>
                                    </div>
                                </div>

                                <HcisAlert
                                    variant="warning"
                                    appearance="subtle"
                                    icon="info-circle"
                                    title="Distribusi Tugas Otomatis Multi-Verifikator"
                                    description="Anggota yang ditugaskan ke peran ini akan otomatis menerima notifikasi dan antrean persetujuan saat karyawan lain mengajukan dokumen sesuai lingkup divisi/unit kerjanya."
                                    class="mb-3"
                                />

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <HcisCard variant="flat" size="sm" class="h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <HcisIcon name="person-check" color="brand" size="sm" />
                                                <span class="fw-semibold small text-dark">Mekanisme Kerja Pool</span>
                                            </div>
                                            <p class="text-muted ap-kecil mb-0">
                                                Pengajuan transaksi tidak terhambat bila salah satu verifikator
                                                berhalangan, karena seluruh anggota pool dalam cakupan yang sama dapat
                                                memproses verifikasi.
                                            </p>
                                        </HcisCard>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <HcisCard variant="flat" size="sm" class="h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <HcisIcon name="people" color="brand" size="sm" />
                                                <span class="fw-semibold small text-dark">Pengelolaan Verifikator</span>
                                            </div>
                                            <p class="text-muted ap-kecil mb-2">
                                                Saat ini terdapat
                                                <strong>{{ detail.pemegang?.length || 0 }} verifikator aktif</strong>
                                                pada pool ini.
                                            </p>
                                            <HcisButton
                                                variant="primary"
                                                size="xs"
                                                leading-icon="people"
                                                label="Kelola Anggota Verifikator Sekarang"
                                                @click="sub = 'pemegang'"
                                            />
                                        </HcisCard>
                                    </div>
                                </div>
                            </div>
                        </HcisCard>

                        <!-- Peran Menu / Admin / Pool dengan Halaman Matriks -->
                        <HcisCard v-else variant="default" size="md" divider class="mb-3">
                            <template #header>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                                    <div>
                                        <h6 class="ap-card-title mb-0.5">
                                            {{
                                                isPoolApproval
                                                    ? `Halaman Terkait Alur Kerja (${detail.matriks?.length || 0} halaman)`
                                                    : `Matriks Hak Halaman (${(ubah ? draf : detail.matriks).length} halaman)`
                                            }}
                                        </h6>
                                        <span class="ap-card-subtitle">
                                            {{
                                                isPoolApproval
                                                    ? 'Halaman operasional yang terhubung dengan peran verifikator alur kerja ini.'
                                                    : ubah
                                                      ? 'Atur izin aksi (VIEW, CREATE, EDIT, DELETE, EXPORT, dll) untuk setiap halaman.'
                                                      : 'Daftar wewenang aksi yang boleh dilakukan oleh pemegang peran ini.'
                                            }}
                                        </span>
                                    </div>

                                    <HcisButton
                                        v-if="detail.bisa_ubah_hak && !ubah"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="pencil-square"
                                        label="Ubah Hak Matriks"
                                        @click="mulaiUbah"
                                    />
                                </div>
                            </template>

                            <HcisAlert
                                v-if="!detail.bisa_ubah_hak && detail.alasan_terkunci && detail.matriks?.length"
                                variant="info"
                                appearance="subtle"
                                :description="detail.alasan_terkunci"
                                class="mb-3"
                            />

                            <!-- ══ MODE EDITOR MATRIKS ══ -->
                            <div v-if="ubah" class="ap-matriks-editor">
                                <!-- Toolbar Aksi Cepat & Pencarian Matriks (Flat inline, tanpa nested card) -->
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom"
                                >
                                    <div
                                        class="d-flex align-items-center gap-2 flex-grow-1 min-w-0"
                                        style="max-width: 380px"
                                    >
                                        <HcisField
                                            id="cari-matriks-edit"
                                            v-model="cariMatriks"
                                            placeholder="Cari halaman di matriks…"
                                            leading-icon="search"
                                            clearable
                                            size="sm"
                                            class="w-100"
                                        />
                                    </div>

                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <!-- Quick Batch Actions -->
                                        <div class="d-inline-flex align-items-center gap-1 bg-light p-1 rounded border">
                                            <HcisButton
                                                variant="ghost"
                                                size="xs"
                                                label="Centang Semua VIEW"
                                                title="Beri aksi VIEW ke seluruh baris halaman"
                                                @click="batchBeriView"
                                            />
                                            <HcisButton
                                                variant="ghost"
                                                size="xs"
                                                label="Beri Semua Aksi"
                                                title="Centang seluruh kolom aksi yang tersedia"
                                                @click="batchBeriSemua"
                                            />
                                            <HcisButton
                                                variant="ghost"
                                                size="xs"
                                                label="Kosongkan Semua"
                                                title="Cabut seluruh centang aksi"
                                                @click="batchKosongkanSemua"
                                            />
                                        </div>

                                        <!-- Summary Diff Chips -->
                                        <div class="d-flex align-items-center gap-1 ms-1">
                                            <HcisBadge
                                                v-if="ringkasPerubahan.tambah"
                                                :label="`+${ringkasPerubahan.tambah} aksi baru`"
                                                variant="success"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                            <HcisBadge
                                                v-if="ringkasPerubahan.buang"
                                                :label="`−${ringkasPerubahan.buang} dicabut`"
                                                variant="danger"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                            <span
                                                v-if="!ringkasPerubahan.tambah && !ringkasPerubahan.buang"
                                                class="text-muted ap-kecil"
                                            >
                                                Belum ada perubahan
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <HcisEmptyState
                                    v-if="!drafTampil.length"
                                    icon="search"
                                    title="Halaman tidak ditemukan"
                                    description="Coba kata kunci lain atau bersihkan pencarian."
                                    size="sm"
                                />

                                <HcisTable
                                    v-else
                                    responsive="cards"
                                    :columns="kolomEditor"
                                    :rows="drafTampil"
                                    row-key="jenis_page"
                                    size="sm"
                                    dense
                                    sticky-first-col
                                >
                                    <template #cell-nama_menu="{ row }">
                                        <div class="fw-semibold small">
                                            {{ row.nama_menu || row.jenis_page }}
                                            <HcisBadge
                                                v-if="!asli[row.jenis_page]"
                                                label="baru"
                                                variant="success"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                        </div>
                                        <div class="text-muted font-monospace ap-kecil">
                                            {{ row.jenis_page }}
                                        </div>
                                    </template>

                                    <template #cell-semua="{ row }">
                                        <HcisCheckbox
                                            :model-value="row.aksi.length === detail.kolom_aksi.length"
                                            :aria-label="`Semua aksi untuk ${row.nama_menu || row.jenis_page}`"
                                            @update:model-value="(v) => (row.aksi = v ? [...detail.kolom_aksi] : [])"
                                        />
                                    </template>

                                    <template v-for="a in detail.kolom_aksi" :key="a" #[`cell-${a}`]="{ row }">
                                        <span class="ap-sel" :class="kelasSel(row, a)">
                                            <HcisCheckbox
                                                v-model="row.aksi"
                                                :value="a"
                                                :aria-label="`${a} untuk ${row.nama_menu || row.jenis_page}`"
                                            />
                                        </span>
                                    </template>
                                </HcisTable>

                                <!-- Tambah Halaman Baru ke Matriks -->
                                <div class="d-flex flex-wrap gap-2 align-items-end mt-3 pt-3 border-top">
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-semibold" for="tambah-halaman">
                                            Tambah Halaman ke Matriks
                                        </label>
                                        <HcisSelect
                                            id="tambah-halaman"
                                            v-model="halamanBaru"
                                            placeholder="Pilih halaman modul ini untuk ditambahkan"
                                            filterable
                                            size="sm"
                                            aria-label="Tambah halaman"
                                        >
                                            <HcisSelectOption
                                                v-for="h in halamanBelum"
                                                :key="h.jenis_page"
                                                :value="h.jenis_page"
                                                :label="h.nama_menu || h.jenis_page"
                                                :description="h.header || ''"
                                            />
                                        </HcisSelect>
                                        <div class="text-muted ap-kecil mt-1">
                                            Halaman baru otomatis dimulai dengan wewenang <strong>VIEW</strong>; aksi
                                            lain dapat Anda tentukan di tabel.
                                        </div>
                                    </div>
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="plus-lg"
                                        label="Tambah Halaman"
                                        :disabled="!halamanBaru"
                                        @click="tambahHalaman"
                                    />
                                </div>

                                <HcisAlert
                                    v-if="menghitung && !pratinjauHak"
                                    class="mt-3"
                                    variant="info"
                                    appearance="subtle"
                                    title="Menghitung dampak perubahan"
                                    description="Sedang memeriksa dampak perubahan hak pada seluruh pemegang peran ini."
                                />
                                <HcisAlert
                                    v-if="pratinjauHak"
                                    class="mt-3"
                                    variant="info"
                                    appearance="subtle"
                                    title="Dampak Perubahan Hak"
                                    :description="teksPratinjau"
                                />

                                <HcisTextarea
                                    id="alasan-hak"
                                    v-model="alasanHak"
                                    class="mt-3"
                                    label="Alasan Perubahan Hak (wajib, tercatat di audit)"
                                    placeholder="Contoh: Penyesuaian wewenang tim administrasi per review triwulan"
                                    :rows="2"
                                />

                                <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        label="Batal"
                                        :disabled="menyimpan"
                                        @click="batalUbah"
                                    />
                                    <HcisButton
                                        variant="primary"
                                        size="sm"
                                        label="Simpan Perubahan Hak"
                                        :loading="menyimpan"
                                        :disabled="
                                            alasanHak.trim().length < 5 ||
                                            (!ringkasPerubahan.tambah && !ringkasPerubahan.buang)
                                        "
                                        @click="simpanHak"
                                    />
                                </div>
                            </div>

                            <!-- ══ MODE BACA-SAJA MATRIKS ══ -->
                            <template v-else>
                                <HcisEmptyState
                                    v-if="!detail.matriks?.length && detail.bisa_ubah_hak"
                                    icon="grid-3x3"
                                    title="Belum Ada Hak Halaman"
                                    description="Peran ini belum memiliki hak halaman yang ditentukan."
                                    size="sm"
                                >
                                    <template #actions>
                                        <HcisButton
                                            variant="primary"
                                            size="sm"
                                            leading-icon="plus-lg"
                                            label="Konfigurasi Hak Sekarang"
                                            @click="mulaiUbah"
                                        />
                                    </template>
                                </HcisEmptyState>

                                <HcisEmptyState
                                    v-else-if="!detail.matriks?.length && !detail.alasan_terkunci"
                                    icon="grid-3x3"
                                    title="Belum Ada Hak Halaman"
                                    description="Peran ini belum memiliki hak halaman yang ditentukan."
                                    size="sm"
                                />

                                <HcisEmptyState
                                    v-else-if="!detail.matriks?.length && detail.alasan_terkunci"
                                    icon="shield-lock"
                                    title="Hak Halaman Mengikuti Hibah"
                                    :description="detail.alasan_terkunci"
                                    size="sm"
                                />

                                <template v-else-if="detail.matriks?.length">
                                    <HcisFilterBar
                                        :search="cariMatriks"
                                        search-placeholder="Cari nama halaman atau menu di matriks…"
                                        variant="flat"
                                        size="sm"
                                        sentiment="brand"
                                        class="mb-3"
                                        @update:search="(v) => (cariMatriks = v)"
                                        @reset="cariMatriks = ''"
                                    />

                                    <HcisEmptyState
                                        v-if="!matriksTampil.length"
                                        icon="search"
                                        title="Halaman tidak ditemukan"
                                        description="Coba kata kunci lain atau bersihkan pencarian."
                                        size="sm"
                                    />

                                    <HcisTable
                                        v-else
                                        responsive="cards"
                                        :columns="kolomMatriks"
                                        :rows="matriksTampil"
                                        row-key="jenis_page"
                                        size="sm"
                                        dense
                                        sticky-first-col
                                    >
                                        <template #cell-nama_menu="{ row }">
                                            <div class="fw-semibold small text-dark">{{ row.nama_menu }}</div>
                                            <div class="text-muted font-monospace ap-kecil">
                                                {{ row.header ? `${row.header} · ` : '' }}{{ row.jenis_page }}
                                            </div>
                                        </template>

                                        <template v-for="a in detail.kolom_aksi" :key="a" #[`cell-${a}`]="{ row }">
                                            <span v-if="row.aksi.includes(a)" class="ap-aksi-chip-aktif">
                                                <HcisIcon name="check" size="xs" />
                                                <span>{{ a }}</span>
                                            </span>
                                            <span v-else class="text-muted opacity-25">—</span>
                                        </template>
                                    </HcisTable>
                                </template>
                            </template>
                        </HcisCard>
                    </div>

                    <!-- ══ 4. SUBTAB 2: PEMEGANG PERAN & VERIFIKATOR ══ -->
                    <div v-else-if="sub === 'pemegang'" class="ap-tab-content">
                        <HcisCard variant="default" size="md" divider class="mb-3">
                            <template #header>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                                    <div>
                                        <h6 class="ap-card-title mb-0.5">
                                            {{
                                                isPoolApproval
                                                    ? 'Daftar Anggota Pool Verifikator'
                                                    : 'Daftar Anggota Pemegang Peran Ini'
                                            }}
                                        </h6>
                                        <span class="ap-card-subtitle">
                                            {{
                                                isPoolApproval
                                                    ? `${detail.pemegang?.length || 0} verifikator aktif memegang peran alur persetujuan ini`
                                                    : `${detail.pemegang?.length || 0} karyawan aktif memegang peran ini`
                                            }}
                                        </span>
                                    </div>

                                    <HcisButton
                                        v-if="aksi"
                                        variant="primary"
                                        size="sm"
                                        :leading-icon="isPoolApproval ? 'person-check' : 'person-plus'"
                                        :label="
                                            isPoolApproval ? 'Tugaskan Verifikator Baru' : 'Tugaskan ke Karyawan Lain'
                                        "
                                        @click="
                                            aksi.tugaskan({
                                                idRole: detail.id,
                                                peranInfo: detail,
                                                setelah: () => buka({ id: detail.id }),
                                            })
                                        "
                                    />
                                </div>
                            </template>

                            <!-- Saringan Pencarian Pemegang (Bila ada lebih dari 3) -->
                            <div v-if="detail.pemegang?.length > 3" class="mb-3">
                                <HcisFilterBar
                                    :search="cariPemegang"
                                    search-placeholder="Cari nama atau NIK pemegang wewenang ini…"
                                    variant="flat"
                                    size="sm"
                                    sentiment="brand"
                                    @update:search="(v) => (cariPemegang = v)"
                                    @reset="cariPemegang = ''"
                                />
                            </div>

                            <TabelPenugasan
                                :rows="pemegangTampil"
                                tampil-orang
                                sembunyikan-peran
                                bisa-aksi
                                @cabut="bukaCabut"
                                @batas="bukaBatas"
                            />
                        </HcisCard>
                    </div>

                    <!-- ══ 5. SUBTAB 3: RIWAYAT AUDIT PERAN ══ -->
                    <div v-else class="ap-tab-content">
                        <HcisCard variant="default" size="md" divider class="mb-3">
                            <template #header>
                                <div>
                                    <h6 class="ap-card-title mb-0.5">Riwayat Mutasi &amp; Audit Peran</h6>
                                    <span class="ap-card-subtitle">
                                        Jejak rekam perubahan hak matriks dan penugasan peran ini yang tercatat di audit
                                        trail
                                    </span>
                                </div>
                            </template>
                            <TabAudit :id-peran="detail.id" ringkas :peran="peran" />
                        </HcisCard>
                    </div>
                </div>
            </div>
        </main>

        <!-- Dialog Aksi Cepat (Batas & Cabut) -->
        <DialogAlasan
            v-model="cabut.buka"
            :judul="cabut.judul"
            :keterangan="cabut.keterangan"
            :aksi="cabut.aksi"
            @selesai="selesaiAksi"
        />
        <DialogBatas v-model="batas.buka" :baris="batas.baris" @selesai="selesaiAksi" />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisCheckbox,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisTable,
    HcisTabs,
    HcisTextarea,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import TabelPenugasan from './TabelPenugasan.vue';
import TabAudit from './TabAudit.vue';
import DialogAlasan from './DialogAlasan.vue';
import DialogBatas from './DialogBatas.vue';
import { aksesPeranApi, infoTingkat, pesanGalat } from '../api/aksesPeranApi.js';

const props = defineProps({
    peran: { type: Array, required: true },
    systemAdmin: { type: Boolean, default: false },
    satuModul: { type: Boolean, default: false },
});
const emit = defineEmits(['berubah']);
const aksi = inject('aksiAkses');

// Layout States
const catalogCollapsed = ref(false);
const mobileView = ref('list'); // 'list' | 'detail'
const isDesktop = ref(window.innerWidth >= 992);

const handleResize = () => {
    isDesktop.value = window.innerWidth >= 992;
};
onMounted(() => window.addEventListener('resize', handleResize));
onUnmounted(() => window.removeEventListener('resize', handleResize));

// Filter States
const kategoriAktif = ref('ALL');
const modul = ref('');
const cari = ref('');
const cariPemegang = ref('');
const sub = ref('hak');
const cariMatriks = ref('');
const menghitung = ref(false);
const memuat = ref(false);
const detail = ref(null);
const peranDipilih = ref(null);
const galatDetail = ref(false);
const ubah = ref(false);
const draf = ref([]);
const halamanBaru = ref(null);
const alasanHak = ref('');
const pratinjauHak = ref(null);
const menyimpan = ref(false);

const ubahTingkat = ref(false);
const tingkatBaru = ref(null);
const alasanTingkat = ref('');
const menyimpanTingkat = ref(false);

const cabut = reactive({ buka: false, judul: '', keterangan: '', aksi: async () => {} });
const batas = reactive({ buka: false, baris: null });

const isPoolApproval = computed(() => detail.value?.jenis === 'POOL_APPROVAL');

// Helper penentuan badge yang lugas & tepat
function infoBadgePeran(p) {
    if (!p)
        return {
            label: 'Peran',
            labelFull: 'Peran Modul',
            variant: 'neutral',
            iconName: 'person-badge',
            iconClass: 'ap-role-icon--neutral',
        };
    if (p.jenis === 'POOL_APPROVAL') {
        return {
            label: 'Alur Approval',
            labelFull: 'Alur Approval / Verifikator',
            variant: 'warning',
            iconName: 'diagram-2',
            iconClass: 'ap-role-icon--warning',
        };
    }
    if (p.tingkat === 'SUPER_ADMIN') {
        return {
            label: 'Dept Head',
            labelFull: 'Kepala Bidang (Super Admin)',
            variant: 'purple',
            iconName: 'shield-lock-fill',
            iconClass: 'ap-role-icon--purple',
        };
    }
    if (p.tingkat === 'ADMIN' || p.jenis === 'ADMIN') {
        return {
            label: 'PIC Modul',
            labelFull: 'PIC Pengelola (Admin Modul)',
            variant: 'brand',
            iconName: 'shield-fill-check',
            iconClass: 'ap-role-icon--brand',
        };
    }
    return {
        label: 'Pelaksana',
        labelFull: 'Pelaksana (Akses Menu)',
        variant: 'neutral',
        iconName: 'window-stack',
        iconClass: 'ap-role-icon--neutral',
    };
}

// Sub-tabs dinamis - staf pemegang selalu prioritas untuk operasional
const daftarSub = computed(() => {
    if (isPoolApproval.value) {
        const poolItems = [
            {
                key: 'pemegang',
                label: 'Anggota Verifikator',
                icon: 'people',
                badge: detail.value?.pemegang?.length || 0,
            },
        ];
        poolItems.push({
            key: 'kemampuan',
            label: 'Kemampuan Fitur Bisnis',
            icon: 'check2-all',
            badge: detail.value?.daftar_kemampuan?.length || 0,
        });
        poolItems.push(
            {
                key: 'hak',
                label: detail.value?.matriks?.length ? 'Halaman Alur Kerja' : 'Karakteristik & Alur Approval',
                icon: 'diagram-2',
                badge: detail.value?.matriks?.length || 0,
            },
            {
                key: 'riwayat',
                label: 'Riwayat Audit',
                icon: 'clock-history',
            },
        );
        return poolItems;
    }

    const items = [
        {
            key: 'pemegang',
            label: detail.value?.tingkat === 'SUPER_ADMIN' ? 'Pejabat / Pemegang Peran' : 'Staf Pemegang Peran',
            icon: 'people',
            badge: detail.value?.pemegang?.length || 0,
        },
    ];

    items.push({
        key: 'kemampuan',
        label: 'Kemampuan Fitur Bisnis',
        icon: 'check2-all',
        badge: detail.value?.daftar_kemampuan?.length || 0,
    });

    items.push(
        {
            key: 'hak',
            label:
                detail.value?.tingkat === 'SUPER_ADMIN'
                    ? 'Menu Terotorisasi (Akses Penuh)'
                    : 'Hak Akses Menu & Aksi (Lanjutan)',
            icon: 'grid-3x3',
            badge: detail.value?.matriks?.length || 0,
        },
        {
            key: 'riwayat',
            label: 'Riwayat Audit',
            icon: 'clock-history',
        },
    );

    return items;
});

// Hitung total peran per kategori
const totalKategori = computed(() => {
    const counts = { AKSES: 0, POOL_APPROVAL: 0, ADMIN: 0, SUPER_ADMIN: 0 };
    (props.peran || []).forEach((p) => {
        if (p.tingkat === 'SUPER_ADMIN') counts.SUPER_ADMIN++;
        else if (p.jenis === 'POOL_APPROVAL') counts.POOL_APPROVAL++;
        else if (p.tingkat === 'ADMIN' || p.jenis === 'ADMIN') counts.ADMIN++;
        else counts.AKSES++;
    });
    return counts;
});

// Chips kategori peran
const quickKategoriChips = computed(() => {
    const chips = [{ id: 'semua', label: 'Semua', value: 'ALL', badge: props.peran?.length || 0 }];
    if (totalKategori.value.SUPER_ADMIN > 0) {
        chips.push({
            id: 'super',
            label: 'Dept Head / Super Admin',
            value: 'SUPER_ADMIN',
            icon: 'shield-lock',
            badge: totalKategori.value.SUPER_ADMIN || 0,
        });
    }
    if (totalKategori.value.ADMIN > 0) {
        chips.push({
            id: 'admin',
            label: 'PIC Admin Modul',
            value: 'ADMIN',
            icon: 'shield-check',
            badge: totalKategori.value.ADMIN || 0,
        });
    }
    if (totalKategori.value.AKSES > 0) {
        chips.push({
            id: 'akses',
            label: 'Pelaksana Menu',
            value: 'AKSES',
            icon: 'window-stack',
            badge: totalKategori.value.AKSES || 0,
        });
    }
    if (totalKategori.value.POOL_APPROVAL > 0) {
        chips.push({
            id: 'pool',
            label: 'Alur Approval',
            value: 'POOL_APPROVAL',
            icon: 'diagram-2',
            badge: totalKategori.value.POOL_APPROVAL || 0,
        });
    }
    return chips;
});

const opsiModul = computed(() =>
    [...new Set(props.peran.map((p) => p.modul).filter(Boolean))].map((m) => ({ label: m, value: m })),
);

const filterValues = computed(() => ({
    modul: modul.value,
}));

const hcisFilterDefinitions = computed(() => {
    const list = [];
    if (!props.satuModul && opsiModul.value.length > 1) {
        list.push({
            key: 'modul',
            label: 'Modul',
            type: 'select',
            placeholder: 'Semua modul',
            options: opsiModul.value,
        });
    }
    return list;
});

function onFilterChange({ key, value }) {
    if (key === 'modul') modul.value = value || '';
}

function onChipClick(chip) {
    kategoriAktif.value = chip.value;
}

function resetFilters() {
    cari.value = '';
    kategoriAktif.value = 'ALL';
    modul.value = '';
}

// Filter peran reaktif dengan hierarki terurut (Super Admin -> Admin -> Akses -> Pool)
const saring = computed(() => {
    const q = cari.value.trim().toLowerCase();
    const list = props.peran.filter((p) => {
        if (modul.value && p.modul !== modul.value) return false;

        if (kategoriAktif.value === 'AKSES') {
            if (p.jenis !== 'AKSES' || p.tingkat === 'SUPER_ADMIN' || p.tingkat === 'ADMIN') return false;
        } else if (kategoriAktif.value === 'POOL_APPROVAL') {
            if (p.jenis !== 'POOL_APPROVAL') return false;
        } else if (kategoriAktif.value === 'ADMIN') {
            if (p.tingkat === 'SUPER_ADMIN') return false;
            if (p.tingkat !== 'ADMIN' && p.jenis !== 'ADMIN') return false;
        } else if (kategoriAktif.value === 'SUPER_ADMIN') {
            if (p.tingkat !== 'SUPER_ADMIN') return false;
        }

        if (q && !`${p.kode} ${p.nama}`.toLowerCase().includes(q)) return false;

        return true;
    });

    const bobotTingkat = { SUPER_ADMIN: 1, ADMIN: 2, PENGGUNA: 3 };
    const bobotJenis = { ADMIN: 1, AKSES: 2, POOL_APPROVAL: 3 };

    return [...list].sort((a, b) => {
        const ta = bobotTingkat[a.tingkat] || 3;
        const tb = bobotTingkat[b.tingkat] || 3;
        if (ta !== tb) return ta - tb;
        const ja = bobotJenis[a.jenis] || 2;
        const jb = bobotJenis[b.jenis] || 2;
        if (ja !== jb) return ja - jb;
        return a.nama.localeCompare(b.nama);
    });
});

const pemegangTampil = computed(() => {
    const list = detail.value?.pemegang || [];
    const q = cariPemegang.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((p) => `${p.nama || ''} ${p.kode_karyawan || ''}`.toLowerCase().includes(q));
});

const asli = computed(() => Object.fromEntries((detail.value?.matriks || []).map((m) => [m.jenis_page, m.aksi])));

const drafTampil = computed(() => {
    const q = cariMatriks.value.trim().toLowerCase();
    return draf.value.filter((d) => !q || `${d.nama_menu} ${d.jenis_page}`.toLowerCase().includes(q));
});

const matriksTampil = computed(() => {
    const q = cariMatriks.value.trim().toLowerCase();
    if (!detail.value?.matriks) return [];
    if (!q) return detail.value.matriks;
    return detail.value.matriks.filter((m) =>
        `${m.nama_menu} ${m.header || ''} ${m.jenis_page}`.toLowerCase().includes(q),
    );
});

const ringkasPerubahan = computed(() => {
    let tambah = 0;
    let buang = 0;
    const sekarang = new Map(draf.value.map((d) => [d.jenis_page, d.aksi]));
    draf.value.forEach((d) => d.aksi.forEach((a) => !(asli.value[d.jenis_page] || []).includes(a) && tambah++));
    Object.entries(asli.value).forEach(([p, aksi]) =>
        aksi.forEach((a) => !(sekarang.get(p) || []).includes(a) && buang++),
    );
    return { tambah, buang };
});

function kelasSel(row, a) {
    const dulu = (asli.value[row.jenis_page] || []).includes(a);
    const kini = row.aksi.includes(a);
    return dulu === kini ? '' : kini ? 'ap-sel--tambah' : 'ap-sel--buang';
}

const kolomEditor = computed(() => [
    { key: 'nama_menu', label: 'Halaman', width: '28%' },
    { key: 'semua', label: 'Semua', align: 'center', width: '60px' },
    ...(detail.value?.kolom_aksi || []).map((a) => ({ key: a, label: a, align: 'center' })),
]);

const kolomMatriks = computed(() => [
    { key: 'nama_menu', label: 'Halaman', width: '30%' },
    ...(detail.value?.kolom_aksi || []).map((a) => ({ key: a, label: a, align: 'center' })),
]);

const halamanBelum = computed(() =>
    (detail.value?.halaman_modul || []).filter((h) => !draf.value.some((d) => d.jenis_page === h.jenis_page)),
);

const teksPratinjau = computed(() => {
    const p = pratinjauHak.value;
    if (!p) return '';
    const nama = (daftar) =>
        daftar
            .map((j) => detail.value?.matriks?.find((m) => m.jenis_page === j)?.nama_menu || j)
            .slice(0, 3)
            .join(', ') + (daftar.length > 3 ? ` (+${daftar.length - 3})` : '');
    const aksi = Object.entries(p.aksi_berubah || {}).map(
        ([j, a]) => `${detail.value?.matriks?.find((m) => m.jenis_page === j)?.nama_menu || j}: ${a.join(', ')}`,
    );
    const berubah = aksi.length ? ` Aksi berubah: ${aksi.join(', ')}.` : '';
    return `Ditambah: ${nama(p.ditambah)}. Dihapus: ${nama(p.dihapus)}.${berubah} Berdampak pada ${p.jumlah_pemegang} pemegang wewenang.`;
});

// Batch Actions untuk Editor Matriks
function batchBeriView() {
    draf.value.forEach((d) => {
        if (!d.aksi.includes('VIEW')) d.aksi.push('VIEW');
    });
}

function batchBeriSemua() {
    const semua = [...(detail.value?.kolom_aksi || [])];
    draf.value.forEach((d) => {
        d.aksi = [...semua];
    });
}

function batchKosongkanSemua() {
    draf.value.forEach((d) => {
        d.aksi = [];
    });
}

async function pilihPeran(row) {
    if (!row || (peranDipilih.value?.id === row.id && !galatDetail.value)) {
        mobileView.value = 'detail';
        return;
    }

    const adaDrafHak = ubah.value && (ringkasPerubahan.value.tambah > 0 || ringkasPerubahan.value.buang > 0);
    const adaDrafTingkat =
        ubahTingkat.value && (tingkatBaru.value !== detail.value?.tingkat || alasanTingkat.value.trim().length > 0);
    if (adaDrafHak || adaDrafTingkat) {
        const lanjut = await hcisConfirm({
            title: 'Buang perubahan yang belum disimpan?',
            description: 'Perubahan pada peran saat ini akan hilang jika Anda membuka peran lain.',
            sentiment: 'warning',
            confirmText: 'Buang & Buka Peran',
            cancelText: 'Tetap di Sini',
        });
        if (!lanjut) return;
    }

    mobileView.value = 'detail';
    await buka(row);
}

let urutanDetail = 0;
async function buka(row) {
    if (!row) return;
    const urutan = ++urutanDetail;
    urutanPratinjau++;
    clearTimeout(penundaPratinjau);
    menghitung.value = false;
    pratinjauHak.value = null;
    peranDipilih.value = row;
    memuat.value = true;
    detail.value = null;
    galatDetail.value = false;
    ubah.value = false;
    ubahTingkat.value = false;
    cariMatriks.value = '';
    cariPemegang.value = '';

    sub.value = 'pemegang';

    try {
        const hasil = await aksesPeranApi.detailPeran(row.id);
        if (urutan === urutanDetail) {
            detail.value = hasil;
            if (hasil.jenis === 'POOL_APPROVAL' && sub.value === 'hak' && !hasil.matriks?.length) {
                sub.value = 'pemegang';
            }
        }
    } catch {
        if (urutan === urutanDetail) {
            galatDetail.value = true;
            hcisMessage.error('Detail peran gagal dimuat. Silakan coba lagi.');
        }
    } finally {
        if (urutan === urutanDetail) memuat.value = false;
    }
}

function mulaiUbahTingkat() {
    tingkatBaru.value = detail.value.tingkat;
    alasanTingkat.value = '';
    ubahTingkat.value = true;
}

async function simpanTingkat() {
    menyimpanTingkat.value = true;
    try {
        await aksesPeranApi.ubahTingkatPeran(detail.value.id, tingkatBaru.value, alasanTingkat.value.trim());
        hcisMessage.success('Tingkat peran diperbarui.');
        ubahTingkat.value = false;
        await buka({ id: detail.value.id });
        emit('berubah');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        menyimpanTingkat.value = false;
    }
}

function mulaiUbah() {
    draf.value = detail.value.matriks.map((m) => ({
        jenis_page: m.jenis_page,
        nama_menu: m.nama_menu,
        aksi: [...m.aksi],
    }));
    alasanHak.value = '';
    pratinjauHak.value = null;
    ubah.value = true;
}

let penundaPratinjau = null;
let urutanPratinjau = 0;
watch(
    draf,
    () => {
        const urutan = ++urutanPratinjau;
        pratinjauHak.value = null;
        clearTimeout(penundaPratinjau);
        menghitung.value = false;
        if (!ubah.value || (!ringkasPerubahan.value.tambah && !ringkasPerubahan.value.buang)) return;
        menghitung.value = true;
        penundaPratinjau = setTimeout(() => hitungPratinjau(urutan), 600);
    },
    { deep: true },
);

function batalUbah() {
    urutanPratinjau++;
    clearTimeout(penundaPratinjau);
    menghitung.value = false;
    pratinjauHak.value = null;
    ubah.value = false;
}

function tambahHalaman() {
    const h = detail.value.halaman_modul.find((x) => x.jenis_page === halamanBaru.value);
    if (h) draf.value.push({ jenis_page: h.jenis_page, nama_menu: h.nama_menu, aksi: ['VIEW'] });
    halamanBaru.value = null;
}

const isiHak = () => draf.value.filter((d) => d.aksi.length).map((d) => ({ jenis_page: d.jenis_page, aksi: d.aksi }));

async function hitungPratinjau(u) {
    menghitung.value = true;
    try {
        const r = await aksesPeranApi.pratinjau({ jenis: 'hak', id_role: detail.value.id, hak: isiHak() });
        if (u === urutanPratinjau) pratinjauHak.value = r;
    } catch (e) {
        if (u === urutanPratinjau) hcisMessage.error(pesanGalat(e, 'Gagal menghitung pratinjau.'));
    } finally {
        if (u === urutanPratinjau) menghitung.value = false;
    }
}

async function simpanHak() {
    menyimpan.value = true;
    try {
        await aksesPeranApi.ubahHak(detail.value.id, isiHak(), alasanHak.value.trim(), detail.value.versi);
        hcisMessage.success('Hak peran diperbarui.');
        ubah.value = false;
        await buka({ id: detail.value.id });
        emit('berubah');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        menyimpan.value = false;
    }
}

function bukaCabut(row) {
    Object.assign(cabut, {
        buka: true,
        judul: `Cabut ${row.nama_peran} dari ${row.nama}`,
        keterangan: 'Wewenang karyawan ini pada peran ini akan dicabut permanen.',
        aksi: (alasan) => aksesPeranApi.cabutPenugasan(row.id_penugasan, alasan),
    });
}

function bukaBatas(row) {
    Object.assign(batas, { buka: true, baris: row });
}

async function selesaiAksi() {
    if (detail.value?.id) {
        await buka({ id: detail.value.id });
    }
    emit('berubah');
}

// Inisialisasi peran pertama
onMounted(() => {
    if (saring.value?.length && !detail.value) {
        pilihPeran(saring.value[0]);
    }
});

watch(
    () => props.peran,
    () => {
        if (saring.value?.length && !detail.value) {
            pilihPeran(saring.value[0]);
        }
    },
);

watch(saring, (daftar) => {
    if (daftar?.length && (!peranDipilih.value || !daftar.some((p) => p.id === peranDipilih.value.id))) {
        pilihPeran(daftar[0]);
    }
});
</script>

<style scoped>
/* ══ HCIS TYPOGRAPHY HARMONY HIERARCHY ══ */
.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-brand-600, #0284c7);
    line-height: 1.25;
    display: inline-block;
}

.ap-catalog-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.015em;
    line-height: 1.35;
}

.ap-card-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.015em;
    line-height: 1.35;
}

.ap-card-subtitle {
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.45;
    display: block;
}

.ap-kecil {
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-text-secondary, #64748b);
}

/* ══ COCKPIT DUAL-PANE LAYOUT ══ */
.ap-cockpit-layout {
    display: flex;
    gap: 1rem;
    align-items: stretch;
    min-height: 650px;
    height: calc(100vh - 220px);
    width: 100%;
}

/* ══ LEFT CATALOG PANE ══ */
.ap-catalog-pane {
    width: 360px;
    flex-shrink: 0;
    height: 100%;
    transition: all 0.25s ease;
}

.ap-catalog-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow:
        inset 0 1px 0 var(--hcis-white, #ffffff),
        0 4px 20px -2px rgba(15, 23, 42, 0.08),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.ap-catalog-header {
    padding: var(--hcis-space-3, 0.75rem);
    background: var(--hcis-surface, #ffffff);
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex-shrink: 0;
}

/* ══ FILTER BAR STYLING DI SIDEBAR ══ */
.ap-katalog-filterbar {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__main) {
    padding: 0 !important;
    margin-bottom: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__search) {
    width: 100%;
    flex: 1 1 100%;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__actions) {
    display: flex;
    width: 100%;
    gap: 0.35rem;
}

.ap-katalog-filterbar :deep(.hcis-filter-toggle-btn),
.ap-katalog-filterbar :deep(.hcis-filter-reset-btn) {
    flex: 1 1 0%;
    width: 100%;
    justify-content: center;
    text-align: center;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__chips) {
    margin-bottom: 0.25rem;
    padding-bottom: 0.25rem;
    border-bottom: none !important;
}

/* Horizontal scrollable pills/chips */
.ap-katalog-filterbar :deep(.hcis-filter-chips-wrapper) {
    display: flex !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    white-space: nowrap !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    gap: 0.35rem;
    padding-bottom: 0.25rem;
    -webkit-overflow-scrolling: touch;
}

.ap-katalog-filterbar :deep(.hcis-filter-chips-wrapper::-webkit-scrollbar) {
    display: none !important;
    height: 0 !important;
}

.ap-katalog-filterbar :deep(.hcis-chip-btn) {
    flex-shrink: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* ══ ICON ALIGNMENT GUARD (Mencegah glyph Bootstrap Icons turun/miring) ══ */
:deep(.hcis-icon) {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    vertical-align: middle !important;
}

:deep(.hcis-icon::before) {
    vertical-align: 0 !important;
    line-height: 1 !important;
    display: inline-block !important;
    margin: 0 !important;
}

/* Scrollable Role List */
.ap-catalog-list {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: var(--hcis-space-2, 0.5rem);
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    background: var(--hcis-surface-subtle, #f8fafc);
}

/* Role Item Component (HcisCard variant="glass") */
.ap-role-card-item {
    cursor: pointer;
    flex-shrink: 0 !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0.5rem;
    border-radius: var(--hcis-radius-lg, 10px);
    position: relative;
}

.ap-role-card-item :deep(.hcis-card__body) {
    padding: 0.75rem 0.85rem !important;
}

.ap-role-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08) !important;
}

.ap-role-card-item.is-selected {
    box-shadow:
        0 0 0 2px var(--hcis-brand-500, #0284c7),
        0 6px 18px -2px rgba(2, 132, 199, 0.25) !important;
}

.ap-role-card-item.hcis-card--sentiment-warning.is-selected {
    box-shadow:
        0 0 0 2px var(--hcis-warning-500, #f59e0b),
        0 6px 18px -2px rgba(245, 158, 11, 0.25) !important;
}

.ap-role-card-item.hcis-card--sentiment-purple.is-selected {
    box-shadow:
        0 0 0 2px #8b5cf6,
        0 6px 18px -2px rgba(139, 92, 246, 0.25) !important;
}

.ap-role-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    flex-shrink: 0;
}

.ap-role-card-item:hover .ap-role-icon {
    transform: scale(1.06);
}

.ap-role-icon--neutral {
    background: linear-gradient(135deg, #ffffff, #f1f5f9);
    border: 1px solid rgba(148, 163, 184, 0.4);
    color: #334155;
    box-shadow:
        0 2px 6px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--warning {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1px solid rgba(217, 119, 6, 0.35);
    color: #b45309;
    box-shadow:
        0 2px 8px rgba(217, 119, 6, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--brand {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid rgba(2, 132, 199, 0.35);
    color: #0284c7;
    box-shadow:
        0 2px 8px rgba(2, 132, 199, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--purple {
    background: linear-gradient(135deg, #faf5ff, #f3e8ff);
    border: 1px solid rgba(124, 58, 237, 0.35);
    color: #7e22ce;
    box-shadow:
        0 2px 8px rgba(124, 58, 237, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-peran-nama {
    font-size: 0.875rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    letter-spacing: -0.015em;
}

.ap-peran-meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    line-height: 1.3;
}

.ap-peran-kode {
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-muted, #475569);
    font-weight: 500;
}

.ap-peran-modul {
    color: var(--hcis-text-secondary, #64748b);
}
.ap-peran-lama {
    color: var(--hcis-text-muted);
    font-style: italic;
}

.ap-peran-stats-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

/* Subtle Pill Badges for metadata stats inside role card */
.ap-metric-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 500;
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-text-secondary, #334155);
    line-height: 1.25;
}

.ap-metric-pill--warning {
    background: #fffbeb;
    border-color: #fef3c7;
    color: #92400e;
    font-weight: 600;
}

.ap-metric-pill--workflow {
    background: #f0f9ff;
    border-color: #e0f2fe;
    color: #0369a1;
    font-weight: 600;
}

.ap-card-arrow {
    color: var(--hcis-text-subtle, #94a3b8);
    opacity: 0.5;
    transition: all 0.2s ease;
}

.ap-role-card-item:hover .ap-card-arrow {
    opacity: 1;
    transform: translateX(3px);
    color: var(--hcis-brand-600, #0284c7) !important;
}

.ap-role-card-item.is-selected .ap-card-arrow {
    opacity: 1;
    transform: translateX(3px);
    color: var(--hcis-brand-600, #0284c7) !important;
}

/* ══ RIGHT WORKSPACE PANE ══ */
.ap-workspace-pane {
    flex: 1 1 0%;
    min-width: 0;
    height: 100%;
}

.ap-workspace-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow:
        inset 0 1px 0 var(--hcis-white, #ffffff),
        0 4px 20px -2px rgba(15, 23, 42, 0.08),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
    overflow: hidden;
    padding: var(--hcis-space-4, 1rem) var(--hcis-space-5, 1.25rem);
}

.ap-workspace-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    flex: 1 1 0%;
    overflow: hidden;
}

.ap-tab-content {
    flex: 1 1 0%;
    min-height: 0;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.ap-uncollapse-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.35rem 0.65rem;
    margin-bottom: 0.75rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    border: 1px dashed var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
}

.ap-mobile-nav {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.ap-workspace-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 420px;
}

.ap-role-hero {
    flex-shrink: 0;
    box-shadow: 0 4px 16px -2px rgba(2, 132, 199, 0.08);
}

.ap-role-hero :deep(.hcis-card__body) {
    padding: 1.15rem 1.35rem !important;
}

.ap-workspace-tabs-container {
    flex-shrink: 0;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.ap-workspace-tabs :deep(.hcis-tabs) {
    margin-bottom: -1px;
}

.ap-workspace-tabs {
    flex-shrink: 0;
}

.ap-hero-title {
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.3;
}

.ap-hero-desc {
    max-width: 720px;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #475569);
}

/* Big Hero Icon Box */
.ap-hero-icon-box {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 4px 12px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

/* Metadata pill badges in Hero Banner */
.ap-hero-meta-row {
    font-size: 0.75rem;
    line-height: 1.4;
}

.ap-hero-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-pill, 9999px);
    color: var(--hcis-text-secondary, #475569);
    font-size: 0.75rem;
    line-height: 1.25;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.ap-hero-meta-pill strong {
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 600;
}

.ap-hero-meta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-pill, 9999px);
    color: var(--hcis-text-secondary, #475569);
    font-size: 0.75rem;
    line-height: 1.25;
    cursor: pointer;
    transition: all 0.16s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.ap-hero-meta-btn strong {
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 700;
}

.ap-hero-meta-btn:hover {
    background: #ffffff;
    border-color: var(--hcis-brand-300, #7dd3fc);
    color: var(--hcis-brand-700, #0369a1);
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.12);
}

.ap-hero-meta-btn.is-active {
    background: #ffffff;
    border-color: var(--hcis-brand-500, #0284c7);
    color: var(--hcis-brand-700, #0369a1);
    font-weight: 600;
    box-shadow:
        0 0 0 1.5px var(--hcis-brand-500, #0284c7),
        0 2px 6px rgba(2, 132, 199, 0.15);
}

/* Workflow Visual Diagram */
.ap-jalur-item {
    transition: all 0.15s ease;
}
.ap-step-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: #f1f5f9;
    color: #475569;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.25;
}
.ap-step-pill--target {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.15);
}
.ap-kemampuan-table-wrap {
    overflow-x: auto;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}
.ap-kemampuan-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}
.ap-kemampuan-table th {
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    color: var(--hcis-text-secondary, #64748b);
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}
.ap-kemampuan-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    vertical-align: middle;
}
.ap-kemampuan-table tr:last-child td {
    border-bottom: none;
}
.ap-kemampuan-table tr:hover td {
    background-color: var(--hcis-surface-subtle, #f8fafc);
}
.ap-cakupan-pill {
    display: inline-block;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.6875rem;
}
.ap-wewenang-hero {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.98));
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}
.ap-wewenang-poin {
    line-height: 1.45;
}
.ap-workflow-diagram {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
}

.ap-step-box {
    flex: 1;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.75rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.ap-step-box.is-highlight {
    border-color: var(--hcis-warning-400, #f59e0b);
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    box-shadow: 0 4px 12px -2px rgba(217, 119, 6, 0.15);
}

.ap-step-icon {
    width: 2rem;
    height: 2rem;
    margin: 0 auto 0.5rem;
    border-radius: 50%;
    background: var(--hcis-surface-subtle, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text, #0f172a);
}

.ap-step-box.is-highlight .ap-step-icon {
    background: #fde68a;
    color: #92400e;
}

.ap-step-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text, #0f172a);
    margin-bottom: 0.25rem;
}

.ap-step-desc {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.3;
}

.ap-step-arrow {
    color: var(--hcis-text-subtle, #94a3b8);
    flex-shrink: 0;
}

/* Matriks Table Cells */
.ap-tab-content {
    animation: ap-fade-in 180ms ease;
}

@keyframes ap-fade-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ap-aksi-chip-aktif {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-success-50, #ecfdf5);
    border: 1px solid var(--hcis-success-200, #a7f3d0);
    color: var(--hcis-success-700, #065f46);
    font-size: var(--hcis-font-size-2xs, 0.6875rem);
    font-weight: 600;
}

.ap-sel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.ap-sel--tambah {
    background: var(--hcis-success-100, rgba(16, 185, 129, 0.15));
    border-radius: var(--hcis-radius-sm, 0.25rem);
}

.ap-sel--buang {
    background: var(--hcis-danger-100, rgba(239, 68, 68, 0.15));
    border-radius: var(--hcis-radius-sm, 0.25rem);
}

.min-w-0 {
    min-width: 0;
}

/* ══ RESPONSIVE STYLES ══ */
@media (max-width: 991.98px) {
    .ap-cockpit-layout {
        display: block;
        height: auto;
        min-height: unset;
    }

    .ap-catalog-pane {
        width: 100%;
        height: calc(100vh - 220px);
        min-height: 540px;
    }

    .ap-workspace-pane {
        width: 100%;
        height: calc(100vh - 220px);
        min-height: 540px;
    }

    .ap-jalur-item {
        transition: all 0.15s ease;
    }
    .ap-step-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.5rem;
        border-radius: var(--hcis-radius-md, 6px);
        background: #f1f5f9;
        color: #475569;
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1.25;
    }
    .ap-step-pill--target {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.15);
    }
    .ap-kemampuan-table-wrap {
        overflow-x: auto;
        border-radius: var(--hcis-radius-md, 8px);
        border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    }
    .ap-kemampuan-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .ap-kemampuan-table th {
        padding: 0.65rem 0.85rem;
        background: var(--hcis-surface-subtle, #f8fafc);
        color: var(--hcis-text-secondary, #64748b);
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    }
    .ap-kemampuan-table td {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
        vertical-align: middle;
    }
    .ap-kemampuan-table tr:last-child td {
        border-bottom: none;
    }
    .ap-kemampuan-table tr:hover td {
        background-color: var(--hcis-surface-subtle, #f8fafc);
    }
    .ap-cakupan-pill {
        display: inline-block;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.6875rem;
    }
    .ap-wewenang-hero {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.98));
        border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    }
    .ap-wewenang-poin {
        line-height: 1.45;
    }
    .ap-workflow-diagram {
        flex-direction: column;
    }

    .ap-step-arrow {
        transform: rotate(90deg);
        margin: 0.25rem 0;
    }
}
</style>
