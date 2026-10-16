<template>
    <section class="pa-saya-assignment-panel" aria-label="Tugas penilaian saya">
        <!-- Error State -->
        <HcisResultState
            v-if="error"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="glass"
            layout="compact"
            :title="errorStatus === 403 ? 'Tugas tidak tersedia' : 'Tugas gagal dimuat'"
            :description="error"
            :error-code="errorCode"
            :request-id="requestId"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="loading && !rows.length" class="pa-assignment-skeleton">
            <HcisSkeleton height="5rem" rounded="lg" />
            <HcisSkeleton height="3rem" rounded="md" />
            <HcisSkeleton v-for="n in 3" :key="`skel-${n}`" height="12rem" rounded="lg" />
        </div>

        <!-- Global Empty State -->
        <HcisEmptyState
            v-else-if="!rows.length"
            variant="card"
            sentiment="neutral"
            size="md"
            icon="clipboard2-check"
            title="Belum Ada Tugas Penilaian"
            :description="emptyStateDescription"
        >
            <template #actions>
                <HcisButton
                    label="Segarkan Data"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    :loading="loading"
                    @click="$emit('retry')"
                />
            </template>
        </HcisEmptyState>

        <!-- Main Grouped Content -->
        <div v-else class="pa-assignment-main">
            <!-- 1. OVERALL PROGRESS HERO COCKPIT CARD -->
            <HcisCard
                :title="activeCycleInfo.nama || 'Progres Penilaian Saya'"
                :kicker="`CYCLE AKTIF · ${activeCycleInfo.tahun ? 'TAHUN ' + activeCycleInfo.tahun : 'EVALUASI 360°'}`"
                :description="totalSelesai >= totalTugas && totalTugas > 0 ? 'Seluruh tugas evaluasi telah selesai Anda kerjakan.' : `${formatAngka(totalSelesai)} dari ${formatAngka(totalTugas)} tugas selesai dikerjakan · Tersisa ${formatAngka(totalTugas - totalSelesai)} tugas yang perlu diselesaikan.`"
                icon="clipboard2-check-fill"
                sentiment="brand"
                variant="glass"
                pattern="rings"
                class="pa-assignment-hero-card"
                :class="{ 'is-tuntas': totalSelesai >= totalTugas && totalTugas > 0 }"
            >
                <template #actions>
                    <div class="pa-hero-actions-wrap">
                        <HcisBadge
                            v-if="activeCycleInfo.kode"
                            :label="activeCycleInfo.kode"
                            variant="neutral"
                            appearance="glass"
                            size="xs"
                            leading-icon="tag"
                        />
                        <HcisBadge
                            :label="`${formatAngka(kategoriKelompokRaw.length)} Kategori Penilai`"
                            variant="brand"
                            appearance="glass"
                            size="xs"
                            leading-icon="people"
                        />
                        <HcisBadge
                            :label="cycleDeadlineText"
                            :variant="totalTerlambat > 0 ? 'danger' : (totalMendekatiTenggat > 0 ? 'warning' : 'neutral')"
                            appearance="glass"
                            size="xs"
                            :leading-icon="totalTerlambat > 0 ? 'exclamation-triangle-fill' : (totalMendekatiTenggat > 0 ? 'clock-fill' : 'calendar-check')"
                        />
                    </div>
                </template>

                <!-- Cockpit Body: Split Grid -->
                <div class="pa-hero-cockpit-grid">
                    <!-- Left: Progress Track & Bento Stat Tiles -->
                    <div class="pa-hero-cockpit-main">
                        <!-- Progress Bar -->
                        <div class="pa-cockpit-progress-track" role="progressbar" :aria-valuenow="persenSelesai" aria-valuemin="0" aria-valuemax="100">
                            <div
                                class="pa-cockpit-progress-fill"
                                :class="{ 'is-tuntas': totalSelesai >= totalTugas && totalTugas > 0 }"
                                :style="{ width: `${persenSelesai}%` }"
                            />
                        </div>

                        <!-- Bento Stat Tiles Grid (styled consistent with Ringkasan) -->
                        <div class="pa-hero-stats-grid">
                            <div class="pa-hero-stat-box is-total">
                                <div class="pa-hero-stat-box__icon is-brand">
                                    <HcisIcon name="list-task" size="2xs" />
                                </div>
                                <div class="pa-hero-stat-box__info">
                                    <span class="pa-hero-stat-box__lbl">Total Tugas</span>
                                    <strong class="pa-hero-stat-box__val">{{ formatAngka(totalTugas) }}</strong>
                                </div>
                            </div>

                            <div class="pa-hero-stat-box is-selesai">
                                <div class="pa-hero-stat-box__icon is-success">
                                    <HcisIcon name="check-circle-fill" size="2xs" />
                                </div>
                                <div class="pa-hero-stat-box__info">
                                    <span class="pa-hero-stat-box__lbl">Selesai Diisi</span>
                                    <strong class="pa-hero-stat-box__val text-success">{{ formatAngka(totalSelesai) }}</strong>
                                </div>
                            </div>

                            <div class="pa-hero-stat-box is-berjalan">
                                <div class="pa-hero-stat-box__icon is-teal">
                                    <HcisIcon name="pencil-square" size="2xs" />
                                </div>
                                <div class="pa-hero-stat-box__info">
                                    <span class="pa-hero-stat-box__lbl">Sedang Diisi</span>
                                    <strong class="pa-hero-stat-box__val text-teal">{{ formatAngka(totalBerjalan) }}</strong>
                                </div>
                            </div>

                            <div class="pa-hero-stat-box is-belum">
                                <div class="pa-hero-stat-box__icon is-neutral">
                                    <HcisIcon name="hourglass-split" size="2xs" />
                                </div>
                                <div class="pa-hero-stat-box__info">
                                    <span class="pa-hero-stat-box__lbl">Belum Mulai</span>
                                    <strong class="pa-hero-stat-box__val text-muted">{{ formatAngka(totalBelumMulai) }}</strong>
                                </div>
                            </div>

                            <div v-if="totalTerlambat > 0" class="pa-hero-stat-box is-danger">
                                <div class="pa-hero-stat-box__icon is-danger">
                                    <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                </div>
                                <div class="pa-hero-stat-box__info">
                                    <span class="pa-hero-stat-box__lbl">Terlambat</span>
                                    <strong class="pa-hero-stat-box__val text-danger">{{ formatAngka(totalTerlambat) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Radial Circular Gauge & Summary Hub -->
                    <div class="pa-cockpit-gauge-box">
                        <div class="pa-cockpit-ring-container">
                            <svg class="pa-cockpit-svg-ring" viewBox="0 0 100 100">
                                <circle
                                    class="pa-cockpit-ring-bg"
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="none"
                                    stroke-width="8"
                                />
                                <circle
                                    class="pa-cockpit-ring-stroke"
                                    :class="{ 'is-tuntas': totalSelesai >= totalTugas && totalTugas > 0 }"
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="none"
                                    stroke-width="8"
                                    stroke-linecap="round"
                                    :stroke-dasharray="251.33"
                                    :stroke-dashoffset="251.33 * (1 - (persenSelesai / 100))"
                                />
                            </svg>
                            <div class="pa-cockpit-ring-center">
                                <strong class="pa-cockpit-ring-persen">{{ persenSelesai }}<small>%</small></strong>
                                <span class="pa-cockpit-ring-label">
                                    {{ totalSelesai >= totalTugas && totalTugas > 0 ? 'SELESAI' : 'PROGRES' }}
                                </span>
                            </div>
                        </div>

                        <div class="pa-cockpit-gauge-meta">
                            <HcisBadge
                                :label="totalSelesai >= totalTugas && totalTugas > 0 ? '100% Selesai' : `${persenSelesai}% Terisi`"
                                :variant="totalSelesai >= totalTugas && totalTugas > 0 ? 'success' : 'brand'"
                                appearance="glass"
                                size="xs"
                                :dot="totalSelesai >= totalTugas && totalTugas > 0"
                            />
                        </div>
                    </div>
                </div>

                <!-- Integrated One-Door Submission Footer -->
                <template #footer>
                    <div
                        class="pa-assignment-submit-strip"
                        data-pa-kirim-strip
                        :class="{
                            'is-siap': pengiriman && pengiriman.bisa_kirim,
                            'is-terkirim': pengiriman && pengiriman.sudah_terkirim_semua,
                            'is-berjalan': pengiriman && !pengiriman.bisa_kirim && !pengiriman.sudah_terkirim_semua,
                            'is-loading': !pengiriman,
                        }"
                    >
                        <div class="pa-submit-strip__icon-wrap">
                            <HcisIcon
                                :name="!pengiriman
                                    ? 'arrow-repeat'
                                    : (pengiriman.sudah_terkirim_semua
                                        ? 'patch-check-fill'
                                        : (pengiriman.bisa_kirim ? 'send-check' : 'info-circle-fill'))"
                                size="sm"
                                :animation="!pengiriman ? 'spin' : undefined"
                            />
                        </div>

                        <div class="pa-submit-strip__content">
                            <template v-if="galatKirim">
                                <h5 class="pa-submit-strip-title text-danger">Status pengiriman tidak dapat dimuat</h5>
                                <p class="pa-submit-strip-desc">{{ galatKirim }}</p>
                            </template>
                            <template v-else-if="!pengiriman">
                                <h5 class="pa-submit-strip-title">Memuat status pengiriman...</h5>
                                <p class="pa-submit-strip-desc">Menghubungkan ke layanan verifikasi penilaian.</p>
                            </template>
                            <template v-else-if="pengiriman.total === 0">
                                <h5 class="pa-submit-strip-title">Tidak Ada Penugasan Aktif</h5>
                                <p class="pa-submit-strip-desc">Belum ada penilaian yang perlu dikirim pada cycle ini.</p>
                            </template>
                            <template v-else-if="pengiriman.sudah_terkirim_semua">
                                <div class="pa-submit-strip-header-row">
                                    <h5 class="pa-submit-strip-title">Terkirim dan Selesai</h5>
                                    <HcisBadge label="Selesai" variant="success" appearance="glass" size="xs" dot />
                                </div>
                                <p class="pa-submit-strip-desc">
                                    Seluruh <strong>{{ formatAngka(pengiriman.total) }} lembar penilaian</strong> Anda telah berhasil dikirim dan selesai.
                                </p>
                            </template>
                            <template v-else-if="pengiriman.bisa_kirim">
                                <div class="pa-submit-strip-header-row">
                                    <h5 class="pa-submit-strip-title">Semua Form Penilaian Lengkap &amp; Siap Dikirim!</h5>
                                    <HcisBadge label="Siap Dikirim" variant="success" appearance="glass" size="xs" dot />
                                </div>
                                <p class="pa-submit-strip-desc">
                                    Seluruh <strong>{{ formatAngka(pengiriman.total) }} lembar penilaian</strong> sudah terisi penuh. Kirim dan kunci sekarang agar penilaian Anda resmi dihitung oleh sistem.
                                </p>
                            </template>
                            <template v-else>
                                <div class="pa-submit-strip-header-row">
                                    <h5 class="pa-submit-strip-title">Pengiriman Penilaian Satu Pintu</h5>
                                </div>
                                <p class="pa-submit-strip-desc">
                                    Pengiriman dilakukan serentak setelah seluruh lembar penilaian selesai diisi. Masih ada <strong>{{ formatAngka(pengiriman.sisa) }} form tersisa</strong>
                                    <template v-if="pengiriman.terkumpul > 0">
                                        <em>({{ formatAngka(pengiriman.terkumpul) }} form sudah tersimpan aman sebagai draft).</em>
                                    </template>
                                </p>
                            </template>
                        </div>

                        <!-- Action Button when Ready -->
                        <div v-if="pengiriman && pengiriman.bisa_kirim" class="pa-submit-strip__cta">
                            <template v-if="!konfirmasiKirim">
                                <HcisButton
                                    :label="`Kirim ${formatAngka(pengiriman.terkumpul)} Penilaian`"
                                    variant="success"
                                    size="sm"
                                    leading-icon="send-check"
                                    :disabled="mengirim"
                                    @click="konfirmasiKirim = true"
                                />
                            </template>
                            <template v-else>
                                <div class="pa-cockpit-confirm-inline">
                                    <span class="pa-cockpit-confirm-prompt">Kirim &amp; kunci sekarang?</span>
                                    <HcisButton
                                        label="Ya, Kirim Resmi"
                                        variant="success"
                                        size="sm"
                                        leading-icon="check-lg"
                                        :loading="mengirim"
                                        @click="emit('kirim', pengiriman.cycle_id)"
                                    />
                                    <HcisButton
                                        label="Batal"
                                        variant="secondary"
                                        size="sm"
                                        :disabled="mengirim"
                                        @click="konfirmasiKirim = false"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </HcisCard>

            <!-- 2. INTERACTIVE SEARCH & FILTER TOOLBAR -->
            <div class="pa-toolbar">
                <div class="pa-toolbar__top-row">
                    <!-- Search Input -->
                    <div class="pa-toolbar__search-wrap">
                        <HcisIcon name="search" size="xs" class="pa-toolbar__search-icon" />
                        <input
                            v-model="kataCari"
                            type="search"
                            class="pa-toolbar__search-input"
                            placeholder="Cari nama karyawan, NIK, jabatan, atau divisi..."
                            aria-label="Cari tugas penilaian"
                        />
                        <button
                            v-if="kataCari"
                            type="button"
                            class="pa-toolbar__search-clear"
                            title="Hapus pencarian"
                            aria-label="Hapus pencarian"
                            @click="kataCari = ''"
                        >
                            <HcisIcon name="x-circle-fill" size="2xs" />
                        </button>
                    </div>

                    <!-- Category Switcher Tabs -->
                    <div v-if="daftarKategoriFilter.length > 2" class="pa-toolbar__cat-segmented" role="tablist" aria-label="Filter kategori penilaian">
                        <button
                            v-for="cat in daftarKategoriFilter"
                            :key="`cat-pill-${cat.kode}`"
                            type="button"
                            role="tab"
                            :aria-selected="kategoriFilter === cat.kode"
                            class="pa-cat-tab-btn"
                            :class="{ active: kategoriFilter === cat.kode }"
                            @click="kategoriFilter = cat.kode"
                        >
                            <span>{{ cat.label }}</span>
                            <span class="pa-tab-count-badge">{{ formatAngka(cat.count) }}</span>
                        </button>
                    </div>

                    <!-- Toolbar Actions -->
                    <div class="pa-toolbar__actions">
                        <button
                            type="button"
                            class="pa-toggle-all-btn"
                            :title="apakahSemuaTerbuka ? 'Tutup semua kategori' : 'Buka semua kategori'"
                            @click="toggleSemuaKategori"
                        >
                            <HcisIcon :name="apakahSemuaTerbuka ? 'arrows-collapse' : 'arrows-expand'" size="2xs" />
                            <span>{{ apakahSemuaTerbuka ? 'Tutup Semua' : 'Buka Semua' }}</span>
                        </button>
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            :loading="loading"
                            title="Segarkan data tugas penilaian"
                            aria-label="Segarkan data tugas penilaian"
                            @click="$emit('retry')"
                        />
                    </div>
                </div>

                <!-- Status Filter Chips Row -->
                <div class="pa-toolbar__chips" role="group" aria-label="Filter status tugas">
                    <button
                        type="button"
                        class="pa-filter-chip"
                        :class="{ active: statusFilter === '' }"
                        @click="statusFilter = ''"
                    >
                        <span>Semua Status</span>
                        <span class="pa-chip-badge">{{ formatAngka(totalTugas) }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-filter-chip"
                        :class="{ active: statusFilter === 'BELUM_MULAI' }"
                        @click="statusFilter = 'BELUM_MULAI'"
                    >
                        <span class="pa-dot is-neutral" />
                        <span>Belum Mulai</span>
                        <span class="pa-chip-badge">{{ formatAngka(totalBelumMulai) }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-filter-chip"
                        :class="{ active: statusFilter === 'BERJALAN' }"
                        @click="statusFilter = 'BERJALAN'"
                    >
                        <span class="pa-dot is-info" />
                        <span>Sedang Mengisi</span>
                        <span class="pa-chip-badge">{{ formatAngka(totalBerjalan) }}</span>
                    </button>
                    <button
                        v-if="totalTerkumpul > 0"
                        type="button"
                        class="pa-filter-chip"
                        :class="{ active: statusFilter === 'TERKUMPUL' }"
                        @click="statusFilter = 'TERKUMPUL'"
                    >
                        <span class="pa-dot is-warning" />
                        <span>Terisi · Siap Kirim</span>
                        <span class="pa-chip-badge">{{ formatAngka(totalTerkumpul) }}</span>
                    </button>
                    <button
                        type="button"
                        class="pa-filter-chip"
                        :class="{ active: statusFilter === 'SELESAI' }"
                        @click="statusFilter = 'SELESAI'"
                    >
                        <span class="pa-dot is-success" />
                        <span>Sudah Dikerjakan</span>
                        <span class="pa-chip-badge">{{ formatAngka(totalSelesai) }}</span>
                    </button>
                    <button
                        v-if="totalTerlambat > 0"
                        type="button"
                        class="pa-filter-chip is-late"
                        :class="{ active: statusFilter === 'TERLAMBAT' }"
                        @click="statusFilter = 'TERLAMBAT'"
                    >
                        <span class="pa-dot is-danger" />
                        <span>Terlambat</span>
                        <span class="pa-chip-badge is-danger">{{ formatAngka(totalTerlambat) }}</span>
                    </button>
                </div>
            </div>

            <!-- Filter Reset Notice if 0 Total Matching -->
            <div v-if="!totalTersaringSemua" class="pa-no-results">
                <div class="pa-no-results__icon-wrap">
                    <HcisIcon name="search" size="md" />
                </div>
                <div class="pa-no-results__text">
                    <strong>Tidak ada tugas penilaian yang cocok</strong>
                    <span>Tidak ditemukan karyawan dengan kata kunci <em>"{{ kataCari }}"</em>{{ statusFilter ? ` dan status "${statusFilter}"` : '' }}{{ kategoriFilter ? ` pada kategori ${kategoriFilter}` : '' }}.</span>
                </div>
                <HcisButton
                    label="Reset Filter &amp; Pencarian"
                    variant="secondary"
                    size="sm"
                    leading-icon="x-circle"
                    @click="resetFilterDanCari"
                />
            </div>

            <!-- 3. GROUPED CATEGORY COLLAPSE ACCORDION -->
            <div v-else class="pa-kategori-stack">
                <article
                    v-for="grup in kategoriKelompok"
                    :key="grup.kode_kategori"
                    class="pa-kategori-card"
                    :class="{
                        'is-tuntas': grup.tuntas,
                        'is-terbuka': isKategoriTerbuka(grup.kode_kategori),
                        'has-late': grup.terlambat_semua > 0,
                    }"
                >
                    <!-- Category Header (Trigger Accordion) -->
                    <button
                        type="button"
                        class="pa-kategori-card__header"
                        :aria-expanded="isKategoriTerbuka(grup.kode_kategori)"
                        :aria-controls="`pa-kategori-body-${grup.kode_kategori}`"
                        @click="toggleKategori(grup.kode_kategori)"
                    >
                        <div class="pa-kategori-card__header-left">
                            <div class="pa-kategori-icon-box" :class="getKategoriCodeClass(grup.kode_kategori)">
                                <HcisIcon :name="getKategoriIcon(grup.kode_kategori)" size="sm" />
                            </div>

                            <div class="pa-kategori-card__title-group">
                                <div class="pa-kategori-title-row">
                                    <h4 class="pa-kategori-card__title">
                                        {{ getJudulKategori(grup.kode_kategori, grup.nama_kategori) }}
                                    </h4>
                                    <HcisBadge
                                        v-if="grup.tuntas"
                                        label="Tuntas"
                                        variant="success"
                                        appearance="subtle"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-else-if="grup.terlambat_semua > 0"
                                        :label="`${formatAngka(grup.terlambat_semua)} Terlambat`"
                                        variant="danger"
                                        appearance="subtle"
                                        size="xs"
                                        dot
                                    />
                                </div>
                                <div class="pa-kategori-meta-row">
                                    <span class="pa-kategori-meta-item">
                                        <strong>{{ formatAngka(grup.selesai_semua) }}</strong> dari {{ formatAngka(grup.total_semua) }} selesai
                                    </span>
                                    <span class="pa-kategori-dot-sep">&bull;</span>
                                    <span class="pa-kategori-meta-item">
                                        <strong>{{ formatAngka(grup.items.length) }}</strong> Karyawan
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pa-kategori-card__header-right">
                            <!-- Progress Pill with Mini Bar & Persen -->
                            <div class="pa-kategori-progress-pill" :class="{ 'is-tuntas': grup.tuntas }" :title="`Kemajuan ${grup.persen}%`">
                                <div class="pa-kategori-mini-progress">
                                    <span class="pa-kategori-mini-progress__bar" :style="{ width: `${grup.persen}%` }" />
                                </div>
                                <strong class="pa-kategori-persen-text">{{ grup.persen }}%</strong>
                            </div>

                            <!-- Chevron Action Button -->
                            <div class="pa-kategori-chevron-wrap" :class="{ 'is-terbuka': isKategoriTerbuka(grup.kode_kategori) }">
                                <HcisIcon name="chevron-down" size="xs" />
                            </div>
                        </div>
                    </button>

                    <!-- Category Content (List of People Inside Collapse) -->
                    <div
                        v-show="isKategoriTerbuka(grup.kode_kategori)"
                        :id="`pa-kategori-body-${grup.kode_kategori}`"
                        class="pa-kategori-card__body"
                        role="region"
                        :aria-label="`Daftar karyawan yang dinilai dalam kategori ${grup.nama_kategori}`"
                    >
                        <!-- Micro empty state when filtered inside this category -->
                        <div v-if="!grup.items.length" class="pa-kategori-empty">
                            <HcisIcon name="info-circle" size="xs" />
                            <span>Tidak ada tugas yang cocok dengan filter aktif di kategori ini.</span>
                        </div>

                        <!-- Grid of Actual People Cards to Rate -->
                        <div v-else class="pa-tugas-grid">
                            <div
                                v-for="item in grup.items"
                                :key="item.Id_PA_Assignment"
                                class="pa-tugas-card"
                                :class="{
                                    'is-done': SUDAH_DIISI.includes(item.Status_Penugasan),
                                    'is-late': item.terlambat,
                                    'is-active': item.Status_Penugasan === 'BERJALAN',
                                }"
                            >
                                <div class="pa-tugas-card__top">
                                    <!-- Person Avatar & Identity -->
                                    <div class="pa-tugas-card__person">
                                        <HcisAvatar
                                            :name="item.Nama_Karyawan_Saat_Cycle || 'PA'"
                                            size="sm"
                                            shape="circle"
                                            :variant="getAvatarSentiment(grup.kode_kategori)"
                                        />
                                        <div class="pa-tugas-identity">
                                            <div class="pa-tugas-name-row">
                                                <strong class="pa-tugas-name" :title="item.Nama_Karyawan_Saat_Cycle">
                                                    {{ item.Nama_Karyawan_Saat_Cycle || '—' }}
                                                </strong>
                                                <span v-if="item.Kode_Karyawan" class="pa-tugas-nik">
                                                    {{ item.Kode_Karyawan }}
                                                </span>
                                            </div>
                                            <div class="pa-tugas-meta-row">
                                                <span v-if="item.Nama_Jabatan_Saat_Cycle" class="pa-tugas-jabatan">
                                                    {{ item.Nama_Jabatan_Saat_Cycle }}
                                                </span>
                                                <span v-if="item.Nama_Divisi_Saat_Cycle" class="pa-tugas-divisi">
                                                    &bull; {{ item.Nama_Divisi_Saat_Cycle }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Badge (Top Right) -->
                                    <div class="pa-tugas-card__status-col">
                                        <HcisStatusBadge
                                            :label="labelStatus(item)"
                                            :variant="varianStatus(item)"
                                            size="sm"
                                            dot
                                        />
                                    </div>
                                </div>

                                <!-- Card Center Info: Cycle & Due Date Urgency -->
                                <div class="pa-tugas-card__center">
                                    <div class="pa-tugas-cycle-info" :title="labelCycle(item)">
                                        <HcisIcon name="calendar2-check" size="2xs" />
                                        <span class="pa-tugas-cycle-text">{{ labelCycle(item) }}</span>
                                    </div>

                                    <!-- Due Date / Finished Badge -->
                                    <div class="pa-tugas-due-wrapper">
                                        <span v-if="SUDAH_DIISI.includes(item.Status_Penugasan)" class="pa-due-pill is-selesai">
                                            <HcisIcon name="check-circle-fill" size="2xs" />
                                            <span>Selesai diisi {{ formatTanggalJam(item.Finished_At) }}</span>
                                        </span>
                                        <span v-else-if="item.terlambat" class="pa-due-pill is-terlambat">
                                            <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                            <span>Terlambat {{ angka(item.hari_terlambat) }} hari &bull; Tenggat: {{ formatTanggalIndo(item.Due_At) }}</span>
                                        </span>
                                        <span v-else-if="item.sisa_hari !== null && item.sisa_hari !== undefined && item.sisa_hari <= 3" class="pa-due-pill is-mendesak">
                                            <HcisIcon name="clock-fill" size="2xs" />
                                            <span>Sisa {{ angka(item.sisa_hari) }} hari &bull; Tenggat: {{ formatTanggalIndo(item.Due_At) }}</span>
                                        </span>
                                        <span v-else-if="item.Due_At" class="pa-due-pill is-normal">
                                            <HcisIcon name="calendar-event" size="2xs" />
                                            <span>Tenggat: {{ formatTanggalIndo(item.Due_At) }}</span>
                                        </span>
                                        <span v-else class="pa-due-pill is-no-window">
                                            <HcisIcon name="infinity" size="2xs" />
                                            <span>Tanpa batas tenggat</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Bottom Actions & Token Status -->
                                <div class="pa-tugas-card__bottom">
                                    <div class="pa-tugas-token-status">
                                        <span v-if="item.Id_Ujian_Token || item.url_ujian" class="pa-token-pill is-ready">
                                            <HcisIcon name="shield-check" size="2xs" />
                                            <span>Kuesioner Siap</span>
                                        </span>
                                        <span v-else class="pa-token-pill is-waiting">
                                            <HcisIcon name="clock" size="2xs" />
                                            <span>Menunggu Akses</span>
                                        </span>
                                    </div>

                                    <!-- Evaluation Action CTA -->
                                    <div class="pa-tugas-cta">
                                        <HcisButton
                                            v-if="item.Status_Penugasan === 'SELESAI' && item.url_ujian"
                                            label="Lihat Form"
                                            variant="secondary"
                                            size="sm"
                                            leading-icon="box-arrow-up-right"
                                            @click="mulaiNilai(item)"
                                        />
                                        <span v-else-if="item.Status_Penugasan === 'SELESAI'" class="pa-tugas-terkunci-tag">
                                            <HcisIcon name="lock-fill" size="2xs" />
                                            <span>Terkunci Resmi</span>
                                        </span>
                                        <HcisButton
                                            v-else-if="item.Status_Penugasan === 'TERKUMPUL' && item.url_ujian"
                                            label="Edit Form"
                                            variant="secondary"
                                            size="sm"
                                            leading-icon="pencil-square"
                                            @click="mulaiNilai(item)"
                                        />
                                        <HcisButton
                                            v-else-if="item.Status_Penugasan === 'BERJALAN' && item.url_ujian"
                                            label="Lanjutkan Menilai"
                                            variant="primary"
                                            size="sm"
                                            trailing-icon="arrow-right"
                                            @click="mulaiNilai(item)"
                                        />
                                        <HcisButton
                                            v-else-if="item.url_ujian"
                                            label="Mulai Menilai"
                                            variant="primary"
                                            size="sm"
                                            trailing-icon="arrow-right"
                                            @click="mulaiNilai(item)"
                                        />
                                        <HcisButton
                                            v-else
                                            label="Belum Dibuka"
                                            variant="secondary"
                                            size="sm"
                                            leading-icon="hourglass-split"
                                            disabled
                                            :title="sebabBelumBisa(item)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <!-- Truncated Notice -->
        <HcisResultState
            v-if="!error && truncated"
            class="pa-saya-assignment__truncated"
            status="info"
            variant="flat"
            layout="inline"
            title="Daftar dipangkas server"
            description="Jumlah baris per halaman yang diminta melewati batas server dan sudah disesuaikan."
            :show-retry="false"
        />
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatusBadge,
    hcisMessage,
} from '@/components/ui';

defineOptions({ name: 'PaSayaAssignmentPanel' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    page: { type: Number, default: 1 },
    pageSize: { type: Number, default: 25 },
    truncated: { type: Boolean, default: false },
    query: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    summary: { type: Object, default: null },
    progress: { type: Object, default: null },

    /* Kemajuan PENGIRIMAN siklus yang sedang dilihat - berbeda dari kemajuan pengisian di
       atas. Lembar boleh terisi semua sementara belum satu pun terkirim, dan justru selisih
       itulah yang perlu dilihat orang di sini. Null = belum dimuat. */
    pengiriman: { type: Object, default: null },
    mengirim: { type: Boolean, default: false },
    galatKirim: { type: String, default: '' },
});

const emit = defineEmits([
    'filter-change',
    'retry',
    'page-change',
    'size-change',
    'kirim',
]);

const konfirmasiKirim = ref(false);

/* Reactive Toolbar Filter States */
const kataCari = ref('');
const statusFilter = ref(props.query?.status || '');
const kategoriFilter = ref('');

/* Set of collapsed category codes */
const kategoriDilipat = ref(new Set());
const sudahInisialisasiCollapse = ref(false);

/* Overall Computed Stats */
const totalTugas = computed(() => {
    if (props.progress && props.progress.total !== undefined && props.progress.total !== null) {
        return Number(props.progress.total);
    }
    return props.total || props.rows.length;
});

/* SUDAH DIKERJAKAN, terkirim atau belum. */
const SUDAH_DIISI = ['SELESAI', 'TERKUMPUL'];

const totalSelesai = computed(() => {
    if (props.progress && props.progress.selesai !== undefined && props.progress.selesai !== null) {
        return Number(props.progress.selesai);
    }
    return props.rows.filter((r) => SUDAH_DIISI.includes(r.Status_Penugasan)).length;
});
const totalTerkumpul = computed(() => props.rows.filter((r) => r.Status_Penugasan === 'TERKUMPUL').length);
const totalBelumMulai = computed(() => {
    if (props.progress && props.progress.belum_mulai !== undefined && props.progress.belum_mulai !== null) {
        return Number(props.progress.belum_mulai);
    }
    return props.rows.filter((r) => r.Status_Penugasan === 'BELUM_MULAI').length;
});
const totalBerjalan = computed(() => {
    if (props.progress && props.progress.berjalan !== undefined && props.progress.berjalan !== null) {
        return Number(props.progress.berjalan);
    }
    return props.rows.filter((r) => r.Status_Penugasan === 'BERJALAN').length;
});
const totalTerlambat = computed(() => {
    if (props.progress && props.progress.terlambat !== undefined && props.progress.terlambat !== null) {
        return Number(props.progress.terlambat);
    }
    return props.rows.filter((r) => Boolean(r.terlambat)).length;
});
const totalMendekatiTenggat = computed(() => {
    if (props.progress && props.progress.deadline_dekat !== undefined && props.progress.deadline_dekat !== null) {
        return Number(props.progress.deadline_dekat);
    }
    return props.rows.filter((r) => !r.terlambat && !SUDAH_DIISI.includes(r.Status_Penugasan) && r.sisa_hari !== null && r.sisa_hari !== undefined && r.sisa_hari <= 3).length;
});

const persenSelesai = computed(() => {
    if (props.progress && props.progress.persen !== undefined && props.progress.persen !== null) {
        return Number(props.progress.persen);
    }
    if (!totalTugas.value) return 0;
    return Math.round((totalSelesai.value / totalTugas.value) * 100);
});

/* Active Cycle Metadata */
const activeCycleInfo = computed(() => {
    // 1. Dari props.pengiriman jika ada
    if (props.pengiriman?.nama_cycle || props.pengiriman?.kode_cycle) {
        return {
            id: props.pengiriman.cycle_id,
            nama: props.pengiriman.nama_cycle || props.pengiriman.kode_cycle,
            kode: props.pengiriman.kode_cycle || '',
            tahun: props.pengiriman.tahun || '',
        };
    }
    // 2. Dari baris tugas pertama yang memiliki info cycle
    const firstWithCycle = props.rows.find((r) => r.Nama_Cycle || r.Kode_Cycle);
    if (firstWithCycle) {
        return {
            id: firstWithCycle.Id_PA_Cycle || null,
            nama: firstWithCycle.Nama_Cycle || firstWithCycle.Kode_Cycle,
            kode: firstWithCycle.Kode_Cycle || '',
            tahun: firstWithCycle.Tahun || '',
        };
    }
    // 3. Fallback
    return {
        id: null,
        nama: 'Cycle Penilaian Berjalan',
        kode: '',
        tahun: new Date().getFullYear(),
    };
});

const nearestDueDate = computed(() => {
    const unfinishedWithDue = props.rows
        .filter((r) => !SUDAH_DIISI.includes(r.Status_Penugasan) && r.Due_At)
        .map((r) => r.Due_At)
        .sort();
    return unfinishedWithDue[0] || null;
});

const cycleDeadlineText = computed(() => {
    if (totalTerlambat.value > 0) {
        return `${totalTerlambat.value} Terlambat`;
    }
    if (nearestDueDate.value) {
        return `Tenggat: ${formatTanggalIndo(nearestDueDate.value)}`;
    }
    if (props.rows.some((r) => r.Due_At)) {
        return `Tenggat: ${formatTanggalIndo(props.rows.find((r) => r.Due_At).Due_At)}`;
    }
    return 'Jadwal Aktif';
});

const emptyStateDescription = computed(() => {
    if (activeCycleInfo.value?.nama && activeCycleInfo.value.nama !== 'Cycle Penilaian Berjalan') {
        return `Tidak ada karyawan yang perlu Anda nilai pada ${activeCycleInfo.value.nama}. Tugas akan otomatis muncul saat Anda ditetapkan sebagai penilai dalam cycle PA aktif.`;
    }
    return 'Tidak ada karyawan yang perlu Anda nilai pada periode ini. Tugas akan otomatis muncul saat Anda ditetapkan sebagai penilai dalam cycle PA aktif.';
});

/* Helper to get category standard ordering & metadata */
const KATEGORI_PRIORITAS = {
    SELF: 1,
    ATASAN: 2,
    PEER: 3,
    BAWAHAN: 4,
};

/* Raw category groupings before category tab filtering */
const kategoriKelompokRaw = computed(() => {
    const map = new Map();

    // 1. Inisialisasi dari props.progress jika ada kategori resmi
    if (props.progress?.kategori && Array.isArray(props.progress.kategori)) {
        for (const k of props.progress.kategori) {
            map.set(k.kode_kategori, {
                kode_kategori: k.kode_kategori,
                nama_kategori: k.nama_kategori,
                total_semua: Number(k.total || 0),
                selesai_semua: Number(k.selesai || 0),
                terlambat_semua: Number(k.terlambat || 0),
                tuntas: Boolean(k.tuntas),
                persen: Number(k.persen || 0),
                items: [],
            });
        }
    }

    // 2. Kumpulkan baris tugas ke grup yang sesuai
    for (const row of props.rows) {
        const kode = row.Kode_Kategori || 'LAINNYA';
        if (!map.has(kode)) {
            map.set(kode, {
                kode_kategori: kode,
                nama_kategori: row.Nama_Kategori || kode,
                total_semua: 0,
                selesai_semua: 0,
                terlambat_semua: 0,
                tuntas: false,
                persen: 0,
                items: [],
            });
        }
        const grup = map.get(kode);
        if (!props.progress?.kategori) {
            grup.total_semua++;
            if (SUDAH_DIISI.includes(row.Status_Penugasan)) grup.selesai_semua++;
            if (row.terlambat) grup.terlambat_semua++;
        }
    }

    // Hitung ulang persen jika tanpa props.progress
    if (!props.progress?.kategori) {
        for (const grup of map.values()) {
            grup.persen = grup.total_semua > 0 ? Math.round((grup.selesai_semua / grup.total_semua) * 100) : 0;
            grup.tuntas = grup.total_semua > 0 && grup.selesai_semua >= grup.total_semua;
        }
    }

    // 3. Masukkan baris yang lolos filter pencarian dan status
    const q = kataCari.value.trim().toLowerCase();
    const st = statusFilter.value;

    for (const row of props.rows) {
        const kode = row.Kode_Kategori || 'LAINNYA';
        const grup = map.get(kode);
        if (!grup) continue;

        // Cek filter status
        if (st === 'TERLAMBAT') {
            if (!row.terlambat) continue;
        } else if (st === 'SELESAI') {
            if (!SUDAH_DIISI.includes(row.Status_Penugasan)) continue;
        } else if (st && row.Status_Penugasan !== st) {
            continue;
        }

        // Cek filter kata kunci
        if (q) {
            const nama = String(row.Nama_Karyawan_Saat_Cycle || '').toLowerCase();
            const nik = String(row.Kode_Karyawan || '').toLowerCase();
            const jabatan = String(row.Nama_Jabatan_Saat_Cycle || '').toLowerCase();
            const divisi = String(row.Nama_Divisi_Saat_Cycle || '').toLowerCase();
            const cycleName = String(row.Nama_Cycle || '').toLowerCase();

            if (!nama.includes(q) && !nik.includes(q) && !jabatan.includes(q) && !divisi.includes(q) && !cycleName.includes(q)) {
                continue;
            }
        }

        grup.items.push(row);
    }

    // Urutkan grup berdasarkan prioritas standar
    return Array.from(map.values()).sort((a, b) => {
        const pA = KATEGORI_PRIORITAS[a.kode_kategori] || 99;
        const pB = KATEGORI_PRIORITAS[b.kode_kategori] || 99;
        return pA - pB;
    });
});

/* List of categories for toolbar switcher tabs */
const daftarKategoriFilter = computed(() => {
    const list = [
        { kode: '', label: 'Semua Kategori', count: totalTugas.value },
    ];
    for (const g of kategoriKelompokRaw.value) {
        list.push({
            kode: g.kode_kategori,
            label: getJudulKategori(g.kode_kategori, g.nama_kategori),
            count: g.total_semua,
            tuntas: g.tuntas,
        });
    }
    return list;
});

/* Final displayed categories respecting kategoriFilter */
const kategoriKelompok = computed(() => {
    if (!kategoriFilter.value) {
        return kategoriKelompokRaw.value;
    }
    return kategoriKelompokRaw.value.filter((g) => g.kode_kategori === kategoriFilter.value);
});

/* Total matching items across all filtered categories */
const totalTersaringSemua = computed(() => {
    return kategoriKelompok.value.reduce((acc, g) => acc + g.items.length, 0);
});

/* Apakah semua kategori sedang terbuka */
const apakahSemuaTerbuka = computed(() => {
    return kategoriKelompok.value.every((g) => !kategoriDilipat.value.has(g.kode_kategori));
});

/* Auto-collapse completed categories on first load */
watch(
    kategoriKelompokRaw,
    (groups) => {
        if (sudahInisialisasiCollapse.value || !groups.length) return;
        for (const g of groups) {
            if (g.tuntas) {
                kategoriDilipat.value.add(g.kode_kategori);
            }
        }
        sudahInisialisasiCollapse.value = true;
    },
    { immediate: true },
);

function isKategoriTerbuka(kode) {
    return !kategoriDilipat.value.has(kode);
}

function toggleKategori(kode) {
    const baru = new Set(kategoriDilipat.value);
    if (baru.has(kode)) {
        baru.delete(kode);
    } else {
        baru.add(kode);
    }
    kategoriDilipat.value = baru;
}

function toggleSemuaKategori() {
    if (apakahSemuaTerbuka.value) {
        kategoriDilipat.value = new Set(kategoriKelompok.value.map((g) => g.kode_kategori));
    } else {
        kategoriDilipat.value = new Set();
    }
}

function resetFilterDanCari() {
    kataCari.value = '';
    statusFilter.value = '';
    kategoriFilter.value = '';
}

function getJudulKategori(kode, nama) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('SELF') || k === 'DIRI_SENDIRI') return 'Penilaian Diri (Self)';
    if (k.includes('ATASAN')) return 'Penilaian Atasan Langsung';
    if (k.includes('PEER') || k.includes('REKAN')) return 'Penilaian Rekan (Peer)';
    if (k.includes('BAWAHAN')) return 'Penilaian Bawahan Langsung';
    return String(nama || kode || 'Kategori Penilai').replace(/Rekan Sejawat/gi, 'Rekan');
}

function labelCycle(item) {
    if (!item) return 'Cycle Penilaian';
    const name = item.Nama_Cycle || item.Kode_Cycle || 'Cycle Penilaian';
    const year = item.Tahun ? String(item.Tahun) : '';
    if (year && !name.includes(year)) {
        return `${name} (${year})`;
    }
    return name;
}

function getKategoriIcon(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'briefcase-fill';
    if (k.includes('BAWAHAN')) return 'diagram-3-fill';
    if (k.includes('PEER')) return 'people-fill';
    return 'person-badge-fill';
}

function getKategoriCodeClass(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'is-cat-atasan';
    if (k.includes('BAWAHAN')) return 'is-cat-bawahan';
    if (k.includes('PEER')) return 'is-cat-peer';
    return 'is-cat-self';
}

function getAvatarSentiment(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'brand';
    if (k.includes('BAWAHAN')) return 'cyan';
    if (k.includes('PEER')) return 'teal';
    return 'amber';
}

function inisial(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function angka(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatTanggalIndo(value) {
    if (!value) return '—';
    const parts = String(value).slice(0, 10).split('-');
    if (parts.length !== 3) return String(value);
    const [year, month, day] = parts;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${Number(day)} ${months[Number(month) - 1] || month} ${year}`;
}

function formatTanggalJam(value) {
    if (!value) return '';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return String(value).slice(0, 16);
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(value).slice(0, 16);
    }
}

function labelStatus(row) {
    if (row?.terlambat) return `Terlambat ${angka(row.hari_terlambat)} hari`;
    return {
        BELUM_MULAI: 'Belum Mulai',
        BERJALAN: 'Sedang Mengisi',

        /* "Terisi" dan bukan "Selesai": lembarnya memang sudah penuh, tetapi belum dikirim,
           dan menyebutnya selesai akan membuat orang menutup halaman ini dengan yakin
           pekerjaannya beres - padahal penilaiannya belum dihitung sama sekali. */
        TERKUMPUL: 'Terisi · Belum Dikirim',
        MENUNGGU_VERIFIKASI: 'Menunggu Verifikasi',
        SELESAI: 'Terkirim',
    }[row?.Status_Penugasan] || row?.Status_Penugasan || '—';
}

function varianStatus(row) {
    if (row?.terlambat) return 'danger';
    return {
        BELUM_MULAI: 'neutral',
        BERJALAN: 'info',
        TERKUMPUL: 'warning',
        MENUNGGU_VERIFIKASI: 'warning',
        SELESAI: 'success',
    }[row?.Status_Penugasan] || 'neutral';
}

function mulaiNilai(item) {
    if (! item?.url_ujian) {
        hcisMessage.info(sebabBelumBisa(item), 'Belum Bisa Dinilai');
        return;
    }

    hcisMessage.info(
        `Membuka lembar penilaian untuk ${item.Nama_Karyawan_Saat_Cycle}...`,
        'Membuka Form',
    );

    window.open(item.url_ujian, '_blank');
}

function sebabBelumBisa(item) {
    if (! item?.Id_Ujian_Token) {
        return 'Tautan kuesioner evaluasi belum diterbitkan oleh administrator untuk penugasan ini.';
    }

    if (String(item.Id_Ujian_Token).startsWith('SIM-PA-')) {
        return 'Tautan kuesioner masih dalam proses integrasi sistem dan akan segera dibuka.';
    }

    return 'Alamat tautan kuesioner evaluasi belum tersedia.';
}
</script>

<style scoped>
.pa-saya-assignment-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
    max-width: 100%;
}

.pa-assignment-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-assignment-main {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ==========================================================================
   1. OVERALL PROGRESS HERO COCKPIT (DESIGN SYSTEM ALIGNED)
   ========================================================================== */
/* ==========================================================================
   1. OVERALL PROGRESS HERO COCKPIT CARD (DESIGN SYSTEM ALIGNED)
   ========================================================================== */
.pa-assignment-hero-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-hero-actions-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Body: 2-Column Split Grid */
.pa-hero-cockpit-grid {
    display: grid;
    grid-template-columns: 1fr 180px;
    gap: 1.5rem;
    align-items: center;
}

.pa-hero-cockpit-main {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
}

/* Track Bar */
.pa-cockpit-progress-track {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-cockpit-progress-fill {
    height: 100%;
    background: var(--hcis-brand-600, #4f46e5);
    border-radius: 999px;
    transition: width 0.4s ease;
}

.pa-cockpit-progress-fill.is-tuntas {
    background: var(--hcis-success-600, #16a34a);
}

/* Bento Stats Grid */
.pa-hero-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.625rem;
}

.pa-hero-stat-box {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-hero-stat-box:hover {
    transform: translateY(-1px);
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-hero-stat-box__icon {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    flex-shrink: 0;
}

.pa-hero-stat-box__icon.is-brand {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-hero-stat-box__icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-hero-stat-box__icon.is-teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}

.pa-hero-stat-box__icon.is-neutral {
    background: rgba(100, 116, 139, 0.12);
    color: #64748b;
}

.pa-hero-stat-box__icon.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.pa-hero-stat-box.is-danger {
    border-color: rgba(254, 202, 202, 0.85);
    background: rgba(254, 242, 242, 0.7);
}

.pa-hero-stat-box__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-hero-stat-box__lbl {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-hero-stat-box__val {
    font-size: 1.125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
}

/* Right: Circular Radial Gauge */
.pa-cockpit-gauge-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.85rem;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(8px);
    border-radius: 14px;
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-cockpit-ring-container {
    position: relative;
    width: 90px;
    height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-cockpit-svg-ring {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.pa-cockpit-ring-bg {
    stroke: #e2e8f0;
}

.pa-cockpit-ring-stroke {
    stroke: var(--hcis-brand-600, #4f46e5);
    transition: stroke-dashoffset 0.6s ease;
}

.pa-cockpit-ring-stroke.is-tuntas {
    stroke: var(--hcis-success-600, #16a34a);
}

.pa-cockpit-ring-center {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.pa-cockpit-ring-persen {
    font-size: 1.35rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.pa-cockpit-ring-persen small {
    font-size: 0.85rem;
    font-weight: 700;
}

.pa-cockpit-ring-label {
    font-size: 0.5625rem;
    font-weight: 750;
    letter-spacing: 0.06em;
    color: var(--hcis-text-muted, #64748b);
    margin-top: 0.1rem;
}

.pa-cockpit-gauge-meta {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Footer: Integrated One-Door Submission Action Bar */
.pa-assignment-submit-strip {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.pa-submit-strip__icon-wrap {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.85);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    color: var(--hcis-text-muted, #64748b);
}

.pa-assignment-submit-strip.is-siap .pa-submit-strip__icon-wrap,
.pa-assignment-submit-strip.is-terkirim .pa-submit-strip__icon-wrap {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
    color: #059669;
}

.pa-submit-strip__content {
    flex: 1 1 18rem;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-submit-strip-header-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-submit-strip-title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-submit-strip-desc {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

.pa-submit-strip__cta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pa-cockpit-confirm-inline {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-cockpit-confirm-prompt {
    font-size: 0.75rem;
    font-weight: 700;
    color: #15803d;
}

/* ==========================================================================
   2. INTERACTIVE TOOLBAR & MULTI-CATEGORY SEGMENTED TABS
   ========================================================================== */
.pa-toolbar {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.95rem 1.15rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.pa-toolbar__top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-toolbar__search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1 1 260px;
    min-width: 220px;
}

.pa-toolbar__search-icon {
    position: absolute;
    left: 0.75rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-toolbar__search-input {
    width: 100%;
    padding: 0.5rem 2.2rem 0.5rem 2.25rem;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font: inherit;
    font-size: 0.82rem;
    background: #f8fafc;
    color: #1e293b;
    transition: all 0.2s ease;
}

.pa-toolbar__search-input:focus {
    background: #ffffff;
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.pa-toolbar__search-clear {
    position: absolute;
    right: 0.75rem;
    background: transparent;
    border: none;
    padding: 0;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s ease;
}

.pa-toolbar__search-clear:hover {
    color: #475569;
}

.pa-toolbar__cat-segmented {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    overflow-x: auto;
    scrollbar-width: none;
}

.pa-toolbar__cat-segmented::-webkit-scrollbar {
    display: none;
}

.pa-cat-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #64748b;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.18s ease;
}

.pa-cat-tab-btn:hover {
    color: #1e293b;
    background: rgba(255, 255, 255, 0.6);
}

.pa-cat-tab-btn.active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.pa-tab-count-badge {
    font-size: 0.66rem;
    font-weight: 750;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

.pa-cat-tab-btn.active .pa-tab-count-badge {
    background: #e0e7ff;
    color: #4338ca;
}

.pa-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-toggle-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-toggle-all-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Status Filter Chips */
.pa-toolbar__chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    padding-top: 0.25rem;
    border-top: 1px solid #f1f5f9;
}

.pa-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-filter-chip:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #1e293b;
}

.pa-filter-chip.active {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4338ca;
    font-weight: 750;
}

.pa-filter-chip.is-late.active {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #b91c1c;
}

.pa-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
}
.pa-dot.is-neutral { background: #94a3b8; }
.pa-dot.is-info { background: #3b82f6; }
.pa-dot.is-warning { background: #f59e0b; }
.pa-dot.is-success { background: #22c55e; }
.pa-dot.is-danger { background: #ef4444; }

.pa-chip-badge {
    font-size: 0.68rem;
    font-weight: 750;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.pa-filter-chip.active .pa-chip-badge {
    background: #e0e7ff;
    color: #4338ca;
}

.pa-chip-badge.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

/* ==========================================================================
   3. NO RESULTS STATE
   ========================================================================== */
.pa-no-results {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 3.5rem 1.5rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    text-align: center;
}

.pa-no-results__icon-wrap {
    width: 3.2rem;
    height: 3.2rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-no-results__text {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.84rem;
    color: #64748b;
    max-width: 28rem;
}

.pa-no-results__text strong {
    color: #0f172a;
    font-size: 0.98rem;
}

/* ==========================================================================
   4. GROUPED CATEGORY ACCORDION
   ========================================================================== */
.pa-kategori-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-kategori-card {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-xl, 14px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    transition: all 0.2s ease;
}

.pa-kategori-card.is-terbuka {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
}

.pa-kategori-card.is-tuntas {
    border-left: 4px solid #16a34a;
}

.pa-kategori-card.has-late {
    border-left: 4px solid #ef4444;
}

.pa-kategori-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    padding: 0.95rem 1.25rem;
    background: #ffffff;
    border: none;
    border-bottom: 1px solid transparent;
    cursor: pointer;
    font: inherit;
    text-align: left;
    transition: all 0.18s ease;
}

.pa-kategori-card__header:hover {
    background: #f8fafc;
}

.pa-kategori-card.is-terbuka .pa-kategori-card__header {
    border-bottom-color: #f1f5f9;
    background: #f8fafc;
}

.pa-kategori-card__header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.pa-kategori-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.pa-kategori-card__header:hover .pa-kategori-icon-box {
    transform: scale(1.04);
}

.pa-kategori-icon-box.is-cat-self {
    background: linear-gradient(135deg, #e0e7ff, #ede9fe);
    color: #4f46e5;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.12);
}

.pa-kategori-icon-box.is-cat-atasan {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0284c7;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.12);
}

.pa-kategori-icon-box.is-cat-peer {
    background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
    color: #7e22ce;
    box-shadow: 0 2px 6px rgba(126, 34, 206, 0.12);
}

.pa-kategori-icon-box.is-cat-bawahan {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #15803d;
    box-shadow: 0 2px 6px rgba(21, 128, 61, 0.12);
}

.pa-kategori-card__title-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pa-kategori-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-kategori-card__title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

.pa-kategori-meta-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-kategori-meta-item strong {
    color: #334155;
    font-weight: 700;
}

.pa-kategori-dot-sep {
    color: #cbd5e1;
    font-size: 0.7rem;
}

.pa-kategori-card__header-right {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-shrink: 0;
}

.pa-kategori-progress-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-kategori-progress-pill.is-tuntas {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.pa-kategori-mini-progress {
    width: 60px;
    height: 7px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-kategori-mini-progress__bar {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    border-radius: 999px;
    transition: width 0.35s ease;
}

.pa-kategori-progress-pill.is-tuntas .pa-kategori-mini-progress__bar {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}

.pa-kategori-persen-text {
    font-size: 0.78rem;
    font-weight: 850;
    color: #334155;
    font-variant-numeric: tabular-nums;
    min-width: 2.2rem;
    text-align: right;
    line-height: 1;
}

.pa-kategori-progress-pill.is-tuntas .pa-kategori-persen-text {
    color: #15803d;
}

.pa-kategori-chevron-wrap {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}

.pa-kategori-card__header:hover .pa-kategori-chevron-wrap {
    background: #e2e8f0;
    color: #1e293b;
}

.pa-kategori-chevron-wrap.is-terbuka {
    transform: rotate(180deg);
    background: #e0e7ff;
    border-color: #c7d2fe;
    color: #4338ca;
}

/* Category Body */
.pa-kategori-card__body {
    padding: 1.15rem;
    background: #ffffff;
}

.pa-kategori-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.8rem;
    justify-content: center;
    border: 1px dashed #cbd5e1;
}

/* ==========================================================================
   5. TASK CARDS GRID & ITEM DETAILS
   ========================================================================== */
.pa-tugas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1rem;
}

.pa-tugas-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.9rem;
    padding: 1.15rem 1.25rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.pa-tugas-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    transform: translateY(-2px);
}

.pa-tugas-card.is-done {
    background: linear-gradient(180deg, #ffffff 0%, #fcfdfd 100%);
    border-color: #d1fae5;
}

.pa-tugas-card.is-done:hover {
    border-color: #a7f3d0;
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.1);
}

.pa-tugas-card.is-late {
    background: linear-gradient(180deg, #ffffff 0%, #fffbfb 100%);
    border-color: #fecaca;
}

.pa-tugas-card.is-late:hover {
    border-color: #fca5a5;
    box-shadow: 0 8px 24px -4px rgba(239, 68, 68, 0.1);
}

.pa-tugas-card.is-active {
    background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
    border-color: #c7d2fe;
}

.pa-tugas-card.is-active:hover {
    border-color: #a5b4fc;
    box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.1);
}

.pa-tugas-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem;
}

.pa-tugas-card__person {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    min-width: 0;
}

.pa-tugas-identity {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pa-tugas-name-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-tugas-name {
    font-size: 0.9375rem;
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.01em;
}

.pa-tugas-nik {
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
    font-variant-numeric: tabular-nums;
    border: 1px solid #e2e8f0;
}

.pa-tugas-meta-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: #64748b;
    flex-wrap: wrap;
    line-height: 1.4;
}

.pa-tugas-jabatan {
    font-weight: 600;
    color: #334155;
}

.pa-tugas-divisi {
    color: #94a3b8;
}

.pa-tugas-card__status-col {
    flex-shrink: 0;
}

/* Card Center Info Box */
.pa-tugas-card__center {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.7rem 0.85rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}

.pa-tugas-cycle-info {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    color: #475569;
}

.pa-tugas-cycle-text {
    font-weight: 650;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-tugas-due-wrapper {
    display: flex;
    align-items: center;
}

.pa-due-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    line-height: 1.35;
}

.pa-due-pill.is-selesai {
    color: #15803d;
    font-weight: 650;
}

.pa-due-pill.is-terlambat {
    color: #b91c1c;
    font-weight: 800;
}

.pa-due-pill.is-mendesak {
    color: #b45309;
    font-weight: 800;
}

.pa-due-pill.is-normal {
    color: #475569;
}

.pa-due-pill.is-no-window {
    color: #94a3b8;
}

/* Card Bottom Footer */
.pa-tugas-card__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid #f1f5f9;
}

.pa-token-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
}

.pa-token-pill.is-ready {
    color: #16a34a;
}

.pa-token-pill.is-waiting {
    color: #94a3b8;
}

.pa-tugas-terkunci-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pa-tugas-cta {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.pa-saya-assignment__truncated {
    margin-top: 0.85rem;
}

/* ==========================================================================
   6. RESPONSIVE QUERIES
   ========================================================================== */
@media (max-width: 768px) {
    .pa-toolbar__top-row {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-toolbar__cat-segmented {
        order: 2;
    }
    .pa-toolbar__actions {
        order: 3;
        justify-content: space-between;
    }
}

@media (max-width: 900px) {
    .pa-hero-cockpit-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    .pa-cockpit-gauge-box {
        flex-direction: row;
        justify-content: space-around;
        padding: 1rem;
    }
}

@media (max-width: 640px) {
    .pa-hero-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .pa-assignment-submit-strip {
        padding: 0.25rem 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.85rem;
    }
    .pa-submit-strip__cta {
        width: 100%;
    }
    .pa-submit-strip__cta :deep(button) {
        width: 100%;
    }
    .pa-cockpit-confirm-inline {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .pa-cockpit-confirm-inline :deep(button) {
        width: 100%;
    }
    .pa-tugas-grid {
        grid-template-columns: 1fr;
    }
    .pa-kategori-card__header {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
    }
    .pa-kategori-card__header-left {
        width: 100%;
    }
    .pa-kategori-card__header-right {
        width: 100%;
        justify-content: space-between;
        padding-top: 0.5rem;
        border-top: 1px dashed #e2e8f0;
    }
}
</style>
