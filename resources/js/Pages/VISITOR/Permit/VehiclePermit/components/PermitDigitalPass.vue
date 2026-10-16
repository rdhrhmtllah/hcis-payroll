<template>
    <div class="permit-digital-pass-wrapper">
        <!-- ═══════════════════════════════════════════════════════════════════
             1. LOADING SKELETON STATE (DISPLAYED WHILE FETCHING PERMIT DATA)
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="loading" class="permit-digital-pass-skeleton">
            <!-- Toolbar Skeleton -->
            <div class="permit-pass-toolbar">
                <div class="permit-toolbar-content">
                    <div class="permit-toolbar-left">
                        <div class="permit-toolbar-title-row">
                            <HcisSkeleton width="230px" height="1.5rem" rounded="sm" />
                            <HcisSkeleton width="130px" height="1.35rem" rounded="full" />
                        </div>
                        <HcisSkeleton width="360px" height="0.85rem" rounded="sm" style="margin-top: 0.35rem;" />
                    </div>
                    <div class="permit-toolbar-right">
                        <HcisSkeleton width="140px" height="2.25rem" rounded="md" />
                        <HcisSkeleton width="110px" height="2.25rem" rounded="md" />
                    </div>
                </div>
            </div>

            <!-- Main Stage Skeleton Grid -->
            <div class="permit-pass-stage-grid">
                <!-- Left Column Skeleton (3D Badge Placeholder) -->
                <div class="permit-card-showcase-column">
                    <div class="permit-lanyard-assembly">
                        <div class="permit-lanyard-ribbon">
                            <span class="permit-lanyard-pattern">HCIS VEHICLE PASS · SECURITY CLEARANCE</span>
                        </div>
                        <div class="permit-lanyard-hardware">
                            <div class="permit-lanyard-swivel" />
                            <div class="permit-lanyard-hook" />
                        </div>
                    </div>
                    <div class="permit-badge-face permit-badge-skeleton-face">
                        <div class="permit-badge-header">
                            <div class="permit-badge-header-left">
                                <HcisSkeleton width="2.35rem" height="2.35rem" rounded="md" />
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <HcisSkeleton width="100px" height="0.6rem" rounded="sm" />
                                    <HcisSkeleton width="140px" height="0.85rem" rounded="sm" />
                                </div>
                            </div>
                            <HcisSkeleton width="90px" height="1.35rem" rounded="full" />
                        </div>
                        <div class="permit-tech-row">
                            <HcisSkeleton width="44px" height="32px" rounded="sm" />
                            <HcisSkeleton width="24px" height="24px" rounded="full" />
                            <HcisSkeleton width="110px" height="1.25rem" rounded="full" />
                        </div>
                        <div class="permit-plate-container">
                            <HcisSkeleton width="100%" height="68px" rounded="lg" />
                        </div>
                        <div class="permit-specs-grid">
                            <HcisSkeleton width="100%" height="86px" rounded="md" />
                            <HcisSkeleton width="100%" height="86px" rounded="md" />
                        </div>
                        <div class="permit-badge-footer">
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <HcisSkeleton width="100px" height="0.6rem" rounded="sm" />
                                <HcisSkeleton width="150px" height="0.85rem" rounded="sm" />
                            </div>
                            <HcisSkeleton width="100px" height="1.65rem" rounded="md" />
                        </div>
                    </div>
                    <div class="permit-flip-controls-wrapper">
                        <HcisSkeleton width="240px" height="2.25rem" rounded="md" />
                    </div>
                </div>

                <!-- Right Column Skeleton (Cards Placeholder) -->
                <div class="permit-pass-actions-column">
                    <HcisCard
                        title="Ringkasan Hak Akses Kendaraan"
                        description="Memuat otorisasi dan cakupan izin akses resmi..."
                        icon="shield-check"
                        sentiment="success"
                        variant="glass"
                        size="sm"
                        divider
                    >
                        <div class="permit-metric-tiles-grid">
                            <HcisSkeleton v-for="i in 4" :key="i" width="100%" height="90px" rounded="md" />
                        </div>
                        <div class="permit-gate-scope-card">
                            <div class="permit-scope-header">
                                <HcisSkeleton width="200px" height="0.85rem" rounded="sm" />
                                <HcisSkeleton width="100px" height="1.25rem" rounded="full" />
                            </div>
                            <div class="permit-scope-akses-line">
                                <HcisSkeleton width="70%" height="1.05rem" rounded="sm" />
                            </div>
                            <HcisSkeleton width="100%" height="3.5rem" rounded="sm" />
                        </div>
                    </HcisCard>

                    <div class="permit-actions-card-wrap">
                        <HcisCard
                            title="Aksi &amp; Dokumen Permit"
                            description="Memuat opsi tindakan cetak dan audit..."
                            icon="tools"
                            sentiment="brand"
                            variant="glass"
                            size="sm"
                            divider
                        >
                            <div class="permit-quick-actions-list">
                                <HcisSkeleton width="100%" height="72px" rounded="md" />
                                <HcisSkeleton width="100%" height="72px" rounded="md" />
                            </div>
                        </HcisCard>
                    </div>
                </div>
            </div>
        </div>


        <!--
            Dek ini hanya urusan pass yang masih hidup. Permit yang dicabut
            dilaporkan di kartunya sendiri pada daftar status (permit-revoked-box
            di PermitStatusList), lengkap dengan sebab dan pencabutnya — jadi
            tidak ada pemberitahuan pencabutan yang menumpang di sini.
        -->

        <!-- ═══════════════════════════════════════════════════════════════════
             2. EMPTY STATE / SCHEDULED INSPECTION HERO
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="!loading && !activePermits.length" class="permit-empty-pass-wrapper">
            <!-- 2A. Smart Hero Inspection Ticket (If user has pending permit with scheduled inspection) -->
            <HcisCard
                v-if="upcomingInspection"
                title="Tiket Jadwal Inspeksi SHE Kendaraan"
                description="Pass Digital akan aktif otomatis setelah kendaraan Anda dinyatakan lolos inspeksi fisik di pos SHE."
                icon="calendar2-check-fill"
                sentiment="brand"
                variant="glass"
                size="md"
                divider
                class="permit-pass-scheduled-card"
            >
                <template #badge>
                    <HcisBadge
                        :label="upcomingInspection.inspeksi?.status === 'TIDAK_HADIR' ? 'Perlu Jadwal Ulang' : 'Menunggu Inspeksi SHE'"
                        :variant="upcomingInspection.inspeksi?.status === 'TIDAK_HADIR' ? 'danger' : 'brand'"
                        :pulse="upcomingInspection.inspeksi?.status === 'TERJADWAL'"
                        size="sm"
                        appearance="glass"
                    />
                </template>

                <div class="permit-pass-hero-content">
                    <!-- Vehicle Identity Row -->
                    <div class="permit-pass-hero-vehicle">
                        <div class="permit-hero-plate-badge">
                            <span class="permit-font-tabular">{{ upcomingInspection.No_Plat_Kendaraan || '-' }}</span>
                        </div>
                        <div class="permit-pass-hero-vmeta">
                            <div class="permit-pass-hero-vtitle">
                                <strong>{{ upcomingInspection.Nama_Model_Manual || upcomingInspection.Nama_Vehicle_Type || 'Kendaraan' }}</strong>
                                <span v-if="upcomingInspection.Tahun_Kendaraan" class="permit-pass-hero-vyear permit-font-tabular">({{ upcomingInspection.Tahun_Kendaraan }})</span>
                            </div>
                            <div class="permit-pass-hero-vsub">
                                <span
                                    class="permit-color-swatch-sm"
                                    :style="{ backgroundColor: getVehicleColorHex(upcomingInspection.Warna_Manual) }"
                                />
                                <span>{{ upcomingInspection.Warna_Manual || '-' }}</span>
                                <span class="permit-pass-hero-sep">·</span>
                                <span class="permit-badge-category-pill permit-badge-category-pill--sm" :style="getCategoryBadgeStyle(upcomingInspection)">
                                    <span class="permit-beacon-dot" :style="{ backgroundColor: upcomingInspection.Kode_Warna || '#10b981' }" />
                                    <span>{{ upcomingInspection.Nama_Kategori || 'PERMIT' }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="permit-pass-hero-trans ms-auto">
                            <span class="permit-pass-hero-trans-label">No. Transaksi</span>
                            <span class="permit-font-tabular permit-pass-hero-trans-val">{{ upcomingInspection.No_Transaksi }}</span>
                        </div>
                    </div>

                    <!-- Ticket Schedule Details Grid -->
                    <div class="permit-pass-ticket-grid">
                        <div class="permit-pass-ticket-item">
                            <div class="permit-pass-ticket-icon is-calendar">
                                <HcisIcon name="calendar2-check-fill" size="sm" />
                            </div>
                            <div class="permit-pass-ticket-body">
                                <span class="permit-pass-ticket-kicker">Waktu Pemeriksaan Fisik</span>
                                <div class="permit-pass-ticket-val">
                                    <span class="permit-pass-ticket-date permit-font-tabular">
                                        {{ formatTanggalInspeksi(upcomingInspection.inspeksi) }}
                                    </span>
                                    <span class="permit-pass-ticket-time-badge permit-font-tabular">
                                        <HcisIcon name="clock-fill" size="2xs" />
                                        <span>{{ formatJamInspeksi(upcomingInspection.inspeksi) }} WIB</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="permit-pass-ticket-divider" />

                        <div class="permit-pass-ticket-item">
                            <div class="permit-pass-ticket-icon is-location">
                                <HcisIcon name="geo-alt-fill" size="sm" />
                            </div>
                            <div class="permit-pass-ticket-body">
                                <span class="permit-pass-ticket-kicker">Lokasi Pos Pemeriksaan</span>
                                <div class="permit-pass-ticket-pos">
                                    <strong>{{ upcomingInspection.inspeksi?.pos || 'Pos SHE' }}</strong>
                                    <span class="permit-pass-ticket-pos-tag">· Pos SHE &amp; Keselamatan</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Callout Alert Box -->
                    <div v-if="upcomingInspection.inspeksi?.wajib_pilih_ulang" class="permit-pass-hero-notice is-danger">
                        <HcisIcon name="exclamation-triangle-fill" size="xs" class="flex-shrink-0" />
                        <span><strong>Jadwal Ulang Diperlukan:</strong> Anda tercatat belum hadir pada jadwal sebelumnya. Silakan klik tombol di bawah untuk memilih jadwal baru.</span>
                    </div>
                    <div v-else-if="upcomingInspection.inspeksi?.lewat_jadwal" class="permit-pass-hero-notice is-danger">
                        <HcisIcon name="clock-history" size="xs" class="flex-shrink-0" />
                        <span><strong>Jadwal Sudah Lewat:</strong> Hasil inspeksi belum dicatat petugas SHE. Hubungi SHE bila Anda sudah datang<template v-if="upcomingInspection.inspeksi?.boleh_pindah">, atau pilih jadwal baru lewat tombol di bawah</template>.</span>
                    </div>
                    <div v-else class="permit-pass-hero-notice is-info">
                        <HcisIcon name="info-circle-fill" size="xs" class="flex-shrink-0" />
                        <span>Harap membawa fisik kendaraan dan berkas asli (<strong>STNK &amp; SIM</strong> aktif) ke pos inspeksi SHE sesuai jadwal tertera.</span>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="permit-pass-hero-actions">
                        <HcisButton
                            label="Lihat Rincian &amp; Pindah Jadwal"
                            variant="primary"
                            size="sm"
                            leading-icon="journal-text"
                            @click="$emit('view-detail', upcomingInspection)"
                        />
                        <HcisButton
                            label="Ajukan Permit Baru"
                            variant="secondary"
                            size="sm"
                            leading-icon="plus-circle"
                            @click="$emit('navigate-to-form')"
                        />
                        <HcisButton
                            label="Riwayat Permit"
                            variant="secondary"
                            size="sm"
                            leading-icon="clock-history"
                            class="ms-sm-auto"
                            @click="$emit('view-detail', null)"
                        />
                    </div>
                </div>
            </HcisCard>

            <!-- 2B. Normal Empty State (If no active pass & no scheduled inspection) -->
            <HcisCard
                v-else
                title="Pass Kendaraan"
                icon="person-vcard-fill"
                sentiment="brand"
                variant="glass"
                size="md"
            >
                <div class="permit-empty-state-box">
                    <!--
                        SATU kalimat untuk keadaan benar-benar kosong, tanpa
                        menuduh sebab.

                        Dulu ada varian "Pass Kendaraan Anda Tidak Lagi Aktif /
                        akses Anda telah dicabut" yang menyala hanya dari
                        `revokedPermits.length`. Syarat itu tidak mengenal waktu:
                        sekali seseorang pernah punya permit dicabut, kalimat itu
                        menang SELAMANYA — termasuk ketika kejadian terakhirnya
                        penolakan, kedaluwarsa, atau pengajuan yang tinggal
                        menunggu. Yang terbaca pemakainya: tuduhan pencabutan di
                        layar orang yang permitnya tidak sedang dicabut.

                        Sebab sebenarnya ada di kartu masing-masing pengajuan di
                        tab Status Pengajuan; di sini cukup fakta yang pasti
                        benar — tidak ada pass yang aktif.
                    -->
                    <HcisEmptyState
                        title="Tidak Ada Pass Kendaraan Aktif"
                        description="Saat ini tidak ada permit berstatus Disetujui atas nama Anda."
                        icon="car-front-fill"
                        sentiment="brand"
                        size="sm"
                    >
                        <template #actions>
                            <div class="permit-empty-actions-row">
                                <HcisButton
                                    label="Ajukan Permit Baru"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="plus-circle"
                                    @click="$emit('navigate-to-form')"
                                />
                                <HcisButton
                                    label="Lihat Status Pengajuan"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="card-checklist"
                                    @click="$emit('view-detail', null)"
                                />
                            </div>
                        </template>
                    </HcisEmptyState>
                </div>
            </HcisCard>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             3. ACTIVE PERMITS CONTENT (READY STATE)
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-else-if="!loading" class="permit-digital-pass-ready">
            <!-- Header Toolbar -->
            <div class="permit-pass-toolbar">
                <div class="permit-toolbar-content">
                    <div class="permit-toolbar-left">
                        <div class="permit-toolbar-title-row">
                            <h2 class="permit-pass-title">Pass Kendaraan</h2>
                            <span class="permit-count-pill" :title="`${activePermits.length} Kendaraan Terdaftar`">
                                <span class="permit-count-pill__dot" />
                                <span class="permit-count-pill__text">{{ activePermits.length }} Terdaftar</span>
                            </span>
                        </div>
                        <p class="permit-pass-subtitle">
                            Kartu tanda izin akses kendaraan resmi yang telah disetujui dan terhubung dengan profil ID Card Karyawan Anda.
                        </p>
                    </div>

                    <div class="permit-toolbar-right">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="printer"
                            class="permit-toolbar-print-all-btn"
                            :loading="cetakSemuaLoading"
                            :disabled="!activePermits.length || cetakSemuaLoading"
                            title="Cetak seluruh kartu permit kendaraan aktif sekaligus"
                            @click="bukaSemuaKartuPdf"
                        >
                            <span>Cetak Semua</span><span class="d-none d-sm-inline">&nbsp;Permit</span>
                        </HcisButton>
                        <HcisButton
                            label="Ajukan Baru"
                            variant="primary"
                            size="sm"
                            leading-icon="plus-circle"
                            class="permit-toolbar-add-btn"
                            @click="$emit('navigate-to-form')"
                        />
                    </div>
                </div>
            </div>

            <!-- Upcoming Inspection Notification Banner (When active permits exist & user also has a scheduled permit) -->
            <div
                v-if="upcomingInspection"
                class="permit-pass-inspection-banner mb-3"
                role="button"
                tabindex="0"
                title="Klik untuk melihat rincian jadwal inspeksi SHE"
                @click="$emit('view-detail', upcomingInspection)"
            >
                <div class="permit-inspection-banner__left">
                    <div class="permit-inspection-banner__icon-box">
                        <HcisIcon name="calendar-check-fill" size="sm" />
                        <span class="permit-inspection-banner__pulse" />
                    </div>
                    <div class="permit-inspection-banner__content">
                        <div class="permit-inspection-banner__title-row">
                            <span class="permit-inspection-banner__kicker">JADWAL INSPEKSI SHE TERDEKAT</span>
                            <span class="permit-inspection-banner__plate permit-font-tabular">
                                {{ upcomingInspection.No_Plat_Kendaraan }}
                            </span>
                            <span class="permit-inspection-banner__model text-truncate">
                                {{ upcomingInspection.Nama_Model_Manual || upcomingInspection.Nama_Vehicle_Type }}
                            </span>
                        </div>
                        <div class="permit-inspection-banner__meta-row">
                            <span class="permit-inspection-banner__datetime permit-font-tabular">
                                <HcisIcon name="calendar2-event" size="2xs" />
                                {{ formatTanggalInspeksi(upcomingInspection.inspeksi) }}
                            </span>
                            <span class="permit-inspection-banner__time-badge permit-font-tabular">
                                <HcisIcon name="clock-fill" size="2xs" />
                                {{ formatJamInspeksi(upcomingInspection.inspeksi) }} WIB
                            </span>
                            <span class="permit-inspection-banner__sep">·</span>
                            <span class="permit-inspection-banner__pos">
                                <HcisIcon name="geo-alt-fill" size="2xs" />
                                {{ upcomingInspection.inspeksi?.pos || 'Pos SHE' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="permit-inspection-banner__right">
                    <HcisButton
                        label="Lihat Rincian &amp; Tiket"
                        variant="primary"
                        size="xs"
                        trailing-icon="chevron-right"
                        class="permit-inspection-banner__btn"
                        @click.stop="$emit('view-detail', upcomingInspection)"
                    />
                </div>
            </div>

            <!-- Multi-Vehicle Deck Strip (Only if > 1 vehicle) -->
            <div v-if="activePermits.length > 1" class="permit-vehicle-deck-container">
                <div class="permit-deck-header-row">
                    <div class="permit-deck-title-group">
                        <HcisIcon name="car-front-fill" size="xs" class="text-brand" />
                        <span class="permit-deck-header-title">PILIH KENDARAAN AKTIF</span>
                        <HcisBadge
                            :label="`${activePermits.length} Kendaraan`"
                            variant="brand"
                            size="xs"
                            appearance="subtle"
                        />
                    </div>
                    <div class="permit-deck-nav-group">
                        <span class="permit-deck-counter permit-font-tabular">
                            <strong>{{ selectedIndex + 1 }}</strong> dari <strong>{{ activePermits.length }}</strong>
                        </span>
                        <div class="permit-deck-nav-btns">
                            <button
                                type="button"
                                class="permit-deck-arrow-btn"
                                :disabled="selectedIndex === 0"
                                title="Kendaraan Sebelumnya (Panah Kiri)"
                                @click="selectPrevPermit"
                            >
                                <HcisIcon name="chevron-left" size="xs" />
                            </button>
                            <button
                                type="button"
                                class="permit-deck-arrow-btn"
                                :disabled="selectedIndex === activePermits.length - 1"
                                title="Kendaraan Selanjutnya (Panah Kanan)"
                                @click="selectNextPermit"
                            >
                                <HcisIcon name="chevron-right" size="xs" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sleek Segmented Vehicle Grid -->
                <div class="permit-vehicle-deck-scroll">
                    <div
                        v-for="(p, idx) in activePermits"
                        :key="p.Id || p.Id_Transaksi_Permit || idx"
                        class="permit-vehicle-deck-card"
                        :class="{ 'is-active': selectedIndex === idx }"
                        @click="selectedIndex = idx"
                    >
                        <div class="permit-deck-card-inner">
                            <div class="permit-deck-card-main">
                                <div
                                    class="permit-deck-card-icon-box"
                                    :style="{ backgroundColor: `${p.Kode_Warna || '#4f46e5'}15`, color: p.Kode_Warna || '#4f46e5' }"
                                >
                                    <HcisIcon :name="getVehicleIcon(p.Nama_Vehicle_Type)" size="sm" />
                                </div>
                                <div class="permit-deck-card-details">
                                    <span class="permit-deck-card-plate permit-font-tabular">
                                        {{ p.No_Plat_Kendaraan }}
                                    </span>
                                    <span class="permit-deck-card-model">
                                        {{ p.Nama_Model_Manual || p.Nama_Vehicle_Type }}
                                    </span>
                                </div>
                            </div>

                            <div class="permit-deck-card-aside">
                                <span class="permit-badge-category-pill" :style="getCategoryBadgeStyle(p)">
                                    <span class="permit-beacon-dot" :style="{ backgroundColor: p.Kode_Warna || '#10b981' }" />
                                    <span>{{ p.Nama_Kategori }}</span>
                                </span>
                                <span class="permit-deck-card-expiry permit-font-tabular">
                                    {{ getRemainingBadgeLabel(p) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main 3D Digital Vehicle Pass Stage -->
            <div class="permit-pass-stage">
                <div class="permit-pass-stage-grid">
                    <!-- LEFT COLUMN: AUTHENTIC LANYARD & PHYSICAL BADGE -->
                    <div class="permit-card-showcase-column">
                        <!-- Realistic Woven Lanyard Strap & Chrome Clip -->
                        <div class="permit-lanyard-assembly">
                            <div class="permit-lanyard-ribbon">
                                <span class="permit-lanyard-pattern">HCIS VEHICLE PASS · OFFICIAL SECURITY CLEARANCE · HCIS</span>
                            </div>
                            <div class="permit-lanyard-hardware">
                                <div class="permit-lanyard-swivel" />
                                <div class="permit-lanyard-hook" />
                            </div>
                        </div>

                        <!-- 3D Perspective Card Scene (Click to Flip!) -->
                        <div
                            class="permit-card-3d-scene"
                            title="Klik kartu untuk membalik (Sisi Depan / Belakang)"
                            @click="toggleFlip"
                        >
                            <div
                                class="permit-physical-badge"
                                :class="{ 'is-flipped': isFlipped }"
                            >
                                <!-- SIDE A: FRONT (OFFICIAL PASS & VEHICLE DATA) -->
                                <div class="permit-badge-face permit-badge-face--front">
                                    <!-- Security Guilloche Grid Pattern Layer -->
                                    <div class="permit-security-pattern-overlay" />

                                    <!-- Ambient Soft Gradient Glows -->
                                    <div
                                        class="permit-ambient-glow permit-ambient-glow--top"
                                        :style="{ background: `radial-gradient(circle, ${currentPermit.Kode_Warna || '#4f46e5'}20 0%, transparent 70%)` }"
                                    />
                                    <div
                                        class="permit-ambient-glow permit-ambient-glow--bottom"
                                        :style="{ background: `radial-gradient(circle, ${currentPermit.Kode_Warna || '#10b981'}15 0%, transparent 70%)` }"
                                    />

                                    <!-- Top Lanyard Slot Hole Cutout -->
                                    <div class="permit-slot-punch" />

                                    <!-- Watermark Shield Arc in Background -->
                                    <div class="permit-watermark-emblem">
                                        <svg viewBox="0 0 200 200" class="permit-watermark-svg">
                                            <circle cx="100" cy="100" r="80" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                                            <circle cx="100" cy="100" r="60" fill="none" stroke="currentColor" stroke-width="1" />
                                            <path d="M100 35 L150 60 V105 C150 140 100 165 100 165 C100 165 50 140 50 105 V60 Z" fill="none" stroke="currentColor" stroke-width="2" />
                                        </svg>
                                    </div>

                                    <!-- 1. Header Row: Corporate Logo, Pass Title, Category Pill -->
                                    <div class="permit-badge-header">
                                        <div class="permit-badge-header-left">
                                            <div class="permit-badge-logo" :style="getLogoGradientStyle(currentPermit)">
                                                <HcisIcon name="shield-lock-fill" size="sm" class="text-white" />
                                            </div>
                                            <div class="permit-badge-brand-text">
                                                <span class="permit-badge-kicker">HCIS CORPORATE PASS</span>
                                                <h3 class="permit-badge-heading">VEHICLE ACCESS PASS</h3>
                                            </div>
                                        </div>

                                        <div class="permit-badge-category-box">
                                            <div class="permit-badge-category-pill" :style="getCategoryBadgeStyle(currentPermit)">
                                                <span class="permit-beacon-dot" :style="{ backgroundColor: currentPermit.Kode_Warna || '#10b981' }" />
                                                <span>{{ currentPermit.Nama_Kategori || 'PERMIT AKSES' }}</span>
                                            </div>
                                            <div class="permit-rfid-chip-label">
                                                <HcisIcon name="shield-check" size="2xs" />
                                                <span>AUTHORIZED</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. Gold Smart Chip & Official Security Seal -->
                                    <div class="permit-tech-row">
                                        <!-- Realistic Gold EMV Contact Chip -->
                                        <div class="permit-emv-chip" title="Simbol Keamanan Terverifikasi">
                                            <div class="permit-emv-line permit-emv-line--h1" />
                                            <div class="permit-emv-line permit-emv-line--h2" />
                                            <div class="permit-emv-line permit-emv-line--v1" />
                                            <div class="permit-emv-core" />
                                        </div>

                                        <!-- Security Contactless Icon -->
                                        <div class="permit-contactless-waves">
                                            <svg viewBox="0 0 24 24" class="permit-nfc-icon">
                                                <path d="M12 4a14 14 0 0 1 0 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                <path d="M8 7a9 9 0 0 1 0 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                <path d="M4 10a4 4 0 0 1 0 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        </div>

                                        <!-- Official Security Seal Badge -->
                                        <div class="permit-security-stamp">
                                            <HcisIcon name="patch-check-fill" size="xs" class="text-emerald" />
                                            <span>OFFICIALLY LINKED</span>
                                        </div>
                                    </div>

                                    <!-- 3. Embossed Indonesian License Plate (Plat Nomor) -->
                                    <div class="permit-plate-container">
                                        <div class="permit-plate-box">
                                            <span class="permit-plate-rivet permit-plate-rivet--tl" />
                                            <span class="permit-plate-rivet permit-plate-rivet--tr" />
                                            <span class="permit-plate-rivet permit-plate-rivet--bl" />
                                            <span class="permit-plate-rivet permit-plate-rivet--br" />

                                            <div class="permit-plate-content">
                                                <div class="permit-plate-vehicle-icon">
                                                    <HcisIcon :name="getVehicleIcon(currentPermit.Nama_Vehicle_Type)" size="sm" />
                                                </div>
                                                <div class="permit-plate-letters permit-font-tabular">
                                                    {{ currentPermit.No_Plat_Kendaraan || 'BG 7898 KAD' }}
                                                </div>
                                                <div class="permit-plate-expiry permit-font-tabular">
                                                    {{ formatPlateMonthYear(currentPermit.Tanggal_Kadaluarsa) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4. Body Grid: Vehicle Specs & Driver Passholder -->
                                    <div class="permit-specs-grid">
                                        <!-- Vehicle Spec Card -->
                                        <div class="permit-spec-card">
                                            <span class="permit-spec-card-title">KENDARAAN TERDAFTAR</span>
                                            <div class="permit-spec-card-main">
                                                {{ currentPermit.Nama_Model_Manual || currentPermit.Nama_Vehicle_Type || '-' }}
                                            </div>
                                            <div class="permit-spec-divider">
                                                <div class="permit-spec-color-row">
                                                    <span
                                                        class="permit-color-swatch"
                                                        :style="{ backgroundColor: getVehicleColorHex(currentPermit.Warna_Manual) }"
                                                    />
                                                    <span class="permit-spec-text">{{ currentPermit.Warna_Manual || '-' }}</span>
                                                </div>
                                                <span class="permit-font-tabular permit-spec-year">Thn: {{ currentPermit.Tahun_Kendaraan || '2024' }}</span>
                                            </div>
                                        </div>

                                        <!-- Driver / Employee Passholder Card -->
                                        <div class="permit-spec-card">
                                            <span class="permit-spec-card-title">PEMILIK / DRIVER</span>
                                            <div class="permit-driver-row">
                                                <div class="permit-avatar-circle" :style="getAvatarGradientStyle(currentPermit)">
                                                    {{ getInitials(currentPermit.Nama_Pemohon) }}
                                                </div>
                                                <div class="permit-driver-info">
                                                    <strong class="permit-driver-title">
                                                        {{ currentPermit.Nama_Pemohon || user?.Nama || '-' }}
                                                    </strong>
                                                    <span class="permit-driver-subtitle">
                                                        {{ currentPermit.Departemen || 'PRODUCTION SUPPORT' }}
                                                        <span v-if="currentPermit.Kode_Karyawan_Pemohon" class="permit-font-tabular">
                                                            ({{ currentPermit.Kode_Karyawan_Pemohon }})
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 5. Bottom Strip: Live Validity & Official Token Seal -->
                                    <div class="permit-badge-footer">
                                        <div class="permit-validity-meta">
                                            <div class="permit-validity-heading">MASA BERLAKU PERMIT</div>
                                            <div class="permit-validity-daterange permit-font-tabular">
                                                {{ formatDate(currentPermit.Tanggal_Mulai || currentPermit.Tanggal_Berlaku) }}
                                                <span class="permit-validity-sep">s/d</span>
                                                {{ formatDate(currentPermit.Tanggal_Kadaluarsa) }}
                                            </div>
                                            <div class="permit-validity-badge-pill">
                                                <span class="permit-live-dot" />
                                                <span>{{ getRemainingBadgeLabel(currentPermit) }}</span>
                                            </div>
                                        </div>

                                        <!-- Official Token / Seal Badge -->
                                        <div class="permit-token-seal-box">
                                            <div class="permit-token-seal-inner">
                                                <HcisIcon name="award-fill" size="xs" class="text-brand" />
                                                <span class="permit-token-title">SECURITY PASS</span>
                                            </div>
                                            <span class="permit-token-id permit-font-tabular">
                                                {{ currentPermit.No_Transaksi || 'PMT-2026-0801' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- SIDE B: BACK (RULES, AUDIT STAMP & EMERGENCY) -->
                                <div class="permit-badge-face permit-badge-face--back">
                                    <!-- Security Guilloche Grid Pattern Layer -->
                                    <div class="permit-security-pattern-overlay" />

                                    <!-- Top Slot Punch -->
                                    <div class="permit-slot-punch" />

                                    <!-- Magnetic Stripe Band -->
                                    <div class="permit-magstripe-bar">
                                        <div class="permit-magstripe-glimmer" />
                                    </div>

                                    <div class="permit-back-content">
                                        <!-- Security QR & Token Verification Section -->
                                        <div class="permit-back-qr-wrapper">
                                            <div class="permit-back-qr-frame">
                                                <svg
                                                    v-if="cardQrCode"
                                                    :viewBox="cardQrCode.viewBox"
                                                    role="img"
                                                    :aria-label="`QR Code Verifikasi Kartu ${currentPermit.No_Transaksi || ''}`"
                                                    shape-rendering="crispEdges"
                                                    class="permit-back-qr-svg"
                                                >
                                                    <rect width="100%" height="100%" fill="#ffffff" />
                                                    <rect
                                                        v-for="(mod, idx) in cardQrCode.modules"
                                                        :key="`${mod.x}-${mod.y}-${idx}`"
                                                        :x="mod.x"
                                                        :y="mod.y"
                                                        width="1"
                                                        height="1"
                                                        fill="#0f172a"
                                                    />
                                                </svg>
                                                <div v-else class="permit-back-qr-fallback">
                                                    <HcisIcon name="qr-code-scan" size="md" class="text-slate-400" />
                                                </div>
                                            </div>
                                            <div class="permit-back-qr-meta">
                                                <div class="permit-back-qr-badge-row">
                                                    <span class="permit-back-qr-badge">VERIFIKASI RESMI</span>
                                                    <HcisIcon name="patch-check-fill" size="2xs" class="text-emerald" />
                                                </div>
                                                <span class="permit-back-qr-token permit-font-tabular">
                                                    *{{ currentPermit.No_Transaksi || 'PMT-SECURE-PASS' }}*
                                                </span>
                                                <span class="permit-back-qr-hint">Pindai di pos jaga untuk konfirmasi keabsahan permit kendaraan</span>
                                            </div>
                                        </div>

                                        <!-- Gate Rules List -->
                                        <div class="permit-back-guidelines">
                                            <span class="permit-guidelines-heading">KETENTUAN &amp; KESELAMATAN BERKENDARA KORPORAT</span>
                                            <ul class="permit-guidelines-list">
                                                <li>Izin akses kendaraan ini berlaku sah selama status kepegawaian aktif dan terdaftar di sistem HCIS.</li>
                                                <li>Batas kecepatan maksimum di seluruh area fasilitas adalah <strong>20 km/jam</strong>.</li>
                                                <li>Wajib memarkir kendaraan pada zona yang sesuai (<strong>{{ currentPermit.Nama_Kategori || '-' }}</strong>).</li>
                                                <li>Wajib mengenakan helm standar SNI (motor) atau sabuk pengaman (mobil).</li>
                                            </ul>
                                        </div>

                                        <!-- Verified Stamp & Emergency Pos Jaga -->
                                        <div class="permit-back-bottom-row">
                                            <div class="permit-back-stamp-group">
                                                <HcisIcon name="patch-check-fill" size="md" class="text-emerald" />
                                                <div>
                                                    <div class="permit-stamp-verified">OFFICIALLY APPROVED</div>
                                                    <div class="permit-stamp-timestamp permit-font-tabular">
                                                        {{ currentPermit.Approved_At ? formatDateTime(currentPermit.Approved_At) : formatDate(currentPermit.Tanggal_Mulai) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="permit-back-emergency-contact">
                                                <span class="permit-emergency-label">Emergency Gate Control</span>
                                                <strong class="permit-font-tabular permit-emergency-number">Ext. 110 / 112</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flip Card Toggle Button & Pagination Controls -->
                        <div class="permit-flip-controls-wrapper">
                            <div class="permit-flip-btn-group">
                                <HcisButton
                                    v-if="activePermits.length > 1"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="chevron-left"
                                    class="permit-flip-nav-btn"
                                    :disabled="selectedIndex === 0"
                                    title="Kendaraan Sebelumnya"
                                    @click="selectPrevPermit"
                                />
                                <HcisButton
                                    :label="isFlipped ? 'Sisi Depan' : 'Balik Kartu'"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="arrow-repeat"
                                    class="permit-flip-main-btn"
                                    :title="isFlipped ? 'Kembali ke tampilan sisi depan kartu permit' : 'Lihat informasi & ketentuan di sisi belakang kartu'"
                                    @click="toggleFlip"
                                />
                                <HcisButton
                                    :label="kartuLoading ? 'Menyiapkan...' : 'Cetak Kartu'"
                                    variant="secondary"
                                    size="sm"
                                    :leading-icon="kartuLoading ? 'arrow-repeat' : 'printer'"
                                    class="permit-flip-main-btn"
                                    title="Lihat & cetak kartu permit tempel dashboard kendaraan (Format ½ A4)"
                                    :loading="kartuLoading"
                                    :disabled="kartuLoading"
                                    @click="bukaKartuPdf"
                                />
                                <HcisButton
                                    v-if="activePermits.length > 1"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="chevron-right"
                                    class="permit-flip-nav-btn"
                                    :disabled="selectedIndex === activePermits.length - 1"
                                    title="Kendaraan Selanjutnya"
                                    @click="selectNextPermit"
                                />
                            </div>

                            <!-- Stepper Dots for Multi-Vehicle Navigation -->
                            <div v-if="activePermits.length > 1" class="permit-stepper-dots">
                                <button
                                    v-for="(_, dIdx) in activePermits"
                                    :key="dIdx"
                                    type="button"
                                    class="permit-dot-btn"
                                    :class="{ 'is-active': selectedIndex === dIdx }"
                                    :title="`Lihat Kendaraan ${dIdx + 1}`"
                                    @click="selectedIndex = dIdx"
                                />
                            </div>

                            <span class="permit-flip-hint">💡 Klik langsung pada kartu untuk memutar</span>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: AUTHORIZATION SUMMARY & ACTIONS -->
                    <div class="permit-pass-actions-column">
                        <!-- Card 1: Clearance & Scope Summary -->
                        <HcisCard
                            title="Ringkasan Hak Akses Kendaraan"
                            description="Rincian otorisasi dan cakupan izin akses resmi di area korporat."
                            icon="shield-check"
                            sentiment="success"
                            variant="glass"
                            size="sm"
                            divider
                        >
                            <!-- 2x2 Metric Tiles Grid -->
                            <div class="permit-metric-tiles-grid">
                                <!-- Metric 1: Pemegang Hak Akses -->
                                <div class="permit-metric-pod">
                                    <div class="permit-pod-header">
                                        <div class="permit-pod-icon-box permit-pod-icon--brand">
                                            <HcisIcon name="person-badge-fill" size="xs" />
                                        </div>
                                        <span class="permit-pod-kicker">PEMEGANG AKSES</span>
                                    </div>
                                    <div class="permit-pod-primary text-truncate">
                                        {{ currentPermit.Nama_Pemohon || user?.Nama || '-' }}
                                    </div>
                                    <div class="permit-pod-meta permit-font-tabular">
                                        Kode: <strong>{{ currentPermit.Kode_Karyawan_Pemohon || user?.Kode_Karyawan || 'EMP-2026' }}</strong>
                                    </div>
                                </div>

                                <!-- Metric 2: Kendaraan Terdaftar -->
                                <div class="permit-metric-pod">
                                    <div class="permit-pod-header">
                                        <div class="permit-pod-icon-box permit-pod-icon--indigo">
                                            <HcisIcon name="car-front-fill" size="xs" />
                                        </div>
                                        <span class="permit-pod-kicker">KENDARAAN RESMI</span>
                                    </div>
                                    <div class="permit-pod-primary permit-font-tabular text-truncate">
                                        {{ currentPermit.No_Plat_Kendaraan }}
                                    </div>
                                    <div class="permit-pod-meta text-truncate">
                                        {{ currentPermit.Nama_Model_Manual || currentPermit.Nama_Vehicle_Type || '-' }}
                                    </div>
                                </div>

                                <!-- Metric 3: Zonasi Izin -->
                                <div class="permit-metric-pod">
                                    <div class="permit-pod-header">
                                        <div
                                            class="permit-pod-icon-box"
                                            :style="{ backgroundColor: `${currentPermit.Kode_Warna || '#10b981'}18`, color: currentPermit.Kode_Warna || '#10b981' }"
                                        >
                                            <HcisIcon name="geo-alt-fill" size="xs" />
                                        </div>
                                        <span class="permit-pod-kicker">ZONASI IZIN</span>
                                    </div>
                                    <div class="permit-pod-primary text-truncate">
                                        {{ currentPermit.Nama_Kategori }}
                                    </div>
                                    <div class="permit-pod-meta">
                                        <span class="permit-live-dot" :style="{ backgroundColor: currentPermit.Kode_Warna || '#10b981' }" />
                                        <span>Zona Terotorisasi</span>
                                    </div>
                                </div>

                                <!-- Metric 4: Status Masa Aktif -->
                                <div class="permit-metric-pod">
                                    <div class="permit-pod-header">
                                        <div class="permit-pod-icon-box permit-pod-icon--success">
                                            <HcisIcon name="calendar-check-fill" size="xs" />
                                        </div>
                                        <span class="permit-pod-kicker">MASA BERLAKU</span>
                                    </div>
                                    <div class="permit-pod-primary text-success text-truncate">
                                        {{ getRemainingBadgeLabel(currentPermit) }}
                                    </div>
                                    <div class="permit-pod-meta permit-font-tabular">
                                        s/d {{ formatDate(currentPermit.Tanggal_Kadaluarsa) }}
                                    </div>
                                </div>
                            </div>

                            <!--
                                Cakupan akses dibaca dari master kategori permit
                                (N_EMI_VISITOR_Master_Kategori_Permit.Deskripsi_Akses), bukan daftar
                                gerbang tetap. Versi sebelumnya menyebut "Gerbang Utama", "Gerbang
                                Logistik", dan lencana "LIVE CLEARANCE" sebagai teks mati: tidak ada
                                satu pun yang berasal dari data, dan untuk zona HIJAU - yang menurut
                                master hanya berhak "Akses hanya sampai Area Parkir" - kartunya
                                justru menyatakan otorisasi penuh atas seluruh portal gerbang.
                                Jangan menambahkan nama gerbang di sini tanpa kolom yang menyimpannya.
                            -->
                            <div class="permit-gate-scope-card">
                                <div class="permit-scope-header">
                                    <div class="permit-scope-title-group">
                                        <HcisIcon name="shield-lock-fill" size="xs" class="text-emerald" />
                                        <strong class="permit-scope-heading">CAKUPAN AKSES</strong>
                                    </div>
                                    <span
                                        v-if="currentPermit.Nama_Kategori"
                                        class="permit-scope-zona-badge"
                                        :style="getCategoryBadgeStyle(currentPermit)"
                                    >
                                        <span class="permit-live-dot" :style="{ backgroundColor: currentPermit.Kode_Warna || '#10b981' }" />
                                        <span>ZONA {{ currentPermit.Nama_Kategori }}</span>
                                    </span>
                                </div>

                                <div v-if="currentPermit.Deskripsi_Kategori" class="permit-scope-akses-line">
                                    <HcisIcon
                                        name="geo-alt-fill"
                                        size="xs"
                                        :style="{ color: currentPermit.Kode_Warna || '#10b981' }"
                                    />
                                    <span class="permit-scope-akses-text">{{ currentPermit.Deskripsi_Kategori }}</span>
                                </div>
                                <div v-else class="permit-scope-akses-line permit-scope-akses-line--kosong">
                                    <HcisIcon name="question-circle-fill" size="xs" />
                                    <span class="permit-scope-akses-text">Cakupan akses zona ini belum diisi pada master kategori permit.</span>
                                </div>

                                <div class="permit-scope-statement-callout">
                                    <div class="permit-scope-callout-inner">
                                        <HcisIcon name="info-circle-fill" size="xs" class="text-emerald permit-callout-icon" />
                                        <p class="permit-scope-statement-text">
                                            Berlaku untuk kendaraan nomor plat <strong class="permit-font-tabular text-dark">{{ currentPermit.No_Plat_Kendaraan }}</strong>. Batas akses mengikuti ketetapan zona pada master kategori permit; konfirmasikan ke petugas gerbang bila ada perbedaan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </HcisCard>

                        <!-- Card 2: Practical Actions -->
                        <div class="permit-actions-card-wrap">
                            <HcisCard
                                title="Aksi &amp; Dokumen Permit"
                                description="Opsi cetak dokumen kartu permit atau melihat riwayat persetujuan."
                                icon="tools"
                                sentiment="brand"
                                variant="glass"
                                size="sm"
                                divider
                            >
                                <div class="permit-quick-actions-list">
                                    <!-- Action 1: Print / Download Dashboard Physical Card -->
                                    <div
                                        class="permit-action-tile"
                                        :class="{ 'is-loading': kartuLoading }"
                                        role="button"
                                        tabindex="0"
                                        :aria-disabled="kartuLoading"
                                        @click="bukaKartuPdf"
                                    >
                                        <div class="permit-action-icon-box permit-action-icon--brand">
                                            <HcisIcon
                                                :name="kartuLoading ? 'arrow-repeat' : 'printer-fill'"
                                                :class="{ 'permit-icon-spinning': kartuLoading }"
                                                size="md"
                                            />
                                        </div>
                                        <div class="permit-action-text">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="permit-action-title">Cetak Kartu Permit</strong>
                                                <span v-if="kartuLoading" class="permit-action-loading-badge">
                                                    <span class="permit-action-loading-dot" />
                                                    <span>Menyiapkan PDF...</span>
                                                </span>
                                            </div>
                                            <span class="permit-action-desc">
                                                {{ kartuLoading ? 'Sedang memproses dan merender dokumen PDF kartu permit...' : 'Unduh atau cetak dokumen kartu izin akses kendaraan.' }}
                                            </span>
                                        </div>
                                        <div class="ms-auto d-flex align-items-center">
                                            <div v-if="kartuLoading" class="permit-mini-spinner" />
                                            <HcisIcon v-else name="chevron-right" size="xs" class="permit-chevron-icon" />
                                        </div>
                                    </div>

                                    <!-- Action 2: View Audit Trail / Approval History -->
                                    <div class="permit-action-tile" role="button" tabindex="0" @click="emitViewDetail">
                                        <div class="permit-action-icon-box permit-action-icon--info">
                                            <HcisIcon name="journal-check" size="md" />
                                        </div>
                                        <div class="permit-action-text">
                                            <strong class="permit-action-title">Rincian &amp; Riwayat Persetujuan</strong>
                                            <span class="permit-action-desc">Periksa alur validasi pejabat, berkas STNK/SIM, dan status.</span>
                                        </div>
                                        <HcisIcon name="chevron-right" size="xs" class="permit-chevron-icon ms-auto" />
                                    </div>
                                </div>
                            </HcisCard>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--
            Kartu tempel dashboard: PDF setengah A4 dari server, dilihat dan
            diunduh lewat viewer yang sama dengan dokumen permit lainnya.
        -->
        <HcisFileViewer
            v-model="showKartuViewer"
            :files="kartuFiles"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { generateQrCodeModules } from '@/utils/qr';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFileViewer,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
} from '@/components/ui';

export default {
    name: 'PermitDigitalPass',
    components: {
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisFileViewer,
        HcisIcon,
        HcisResultState,
        HcisSkeleton,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        permitsData: { type: Array, default: null },
    },
    emits: ['navigate-to-form', 'view-detail', 'permit-updated'],
    data() {
        return {
            internalPermits: [],
            loading: true,
            selectedIndex: 0,
            isFlipped: false,
            showKartuViewer: false,
            kartuFiles: [],
            kartuLoading: false,
            cetakSemuaLoading: false,
        };
    },
    computed: {
        allPermits() {
            return this.permitsData || this.internalPermits;
        },
        activePermits() {
            return (this.allPermits || []).filter((p) => p.Status_Permit === 'APPROVED');
        },
        revokedPermits() {
            return (this.allPermits || []).filter((p) => p.Status_Permit === 'REVOKED');
        },
        scheduledPermits() {
            return (this.allPermits || []).filter((p) => {
                // Kedaluwarsa tinggal arsip, bukan jadwal yang perlu didatangi.
                if (!p.inspeksi || p.inspeksi.kedaluwarsa || p.Status_Permit === 'EXPIRED') return false;
                const status = p.inspeksi.status;
                return (p.Status_Permit === 'PENDING' || p.Flag_Approval === null)
                    && (status === 'TERJADWAL' || status === 'TIDAK_HADIR' || p.inspeksi.wajib_pilih_ulang);
            });
        },
        upcomingInspection() {
            return this.scheduledPermits[0] || null;
        },
        currentPermit() {
            return this.activePermits[this.selectedIndex] || this.activePermits[0] || {};
        },
        cardQrCode() {
            const token = this.currentPermit?.No_Transaksi || this.currentPermit?.Id_Transaksi_Permit || '';
            if (!token) return null;
            return generateQrCodeModules(String(token));
        },
    },
    watch: {
        activePermits(newVal) {
            if (this.selectedIndex >= newVal.length) {
                this.selectedIndex = 0;
            }
        },
        permitsData: {
            immediate: true,
            handler(val) {
                if (val !== null) {
                    this.loading = false;
                }
            },
        },
    },
    mounted() {
        if (this.permitsData === null) {
            this.fetchPermits();
        }
        window.addEventListener('keydown', this.handleKeydown);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.handleKeydown);
    },
    methods: {
        /**
         * Kartunya dirender server sebagai PDF A5 mendatar, lalu dibuka di
         * viewer: yang dilihat, diunduh, dan dicetak adalah berkas yang sama.
         * Permintaannya dicoba lebih dulu supaya penolakan server (permit
         * dicabut/kedaluwarsa) muncul sebagai pesan, bukan viewer kosong.
         */
        async bukaKartuPdf() {
            const id = this.currentPermit?.Id_Transaksi_Permit || this.currentPermit?.Id;
            if (!id || this.kartuLoading) return;

            this.kartuLoading = true;
            const url = `/transaksipermit/${id}/kartu-pdf`;
            try {
                const { data } = await axios.get(url, { responseType: 'blob' });
                const plat = String(this.currentPermit?.No_Plat_Kendaraan || 'permit').replace(/\s+/g, '');

                this.kartuFiles = [{
                    url: URL.createObjectURL(data),
                    name: `kartu-permit-${plat}.pdf`,
                    title: `Kartu Permit ${this.currentPermit?.No_Plat_Kendaraan || ''}`.trim(),
                    ext: 'pdf',
                    allowDownload: true,
                }];
                this.showKartuViewer = true;
            } catch (e) {
                const pesan = await this.pesanGalatBlob(e);
                ElMessage.error(pesan || 'Kartu permit gagal dibuat.');
            } finally {
                this.kartuLoading = false;
            }
        },
        /**
         * Cetak seluruh permit aktif sekaligus (batch A4).
         */
        async bukaSemuaKartuPdf() {
            if (!this.activePermits.length || this.cetakSemuaLoading) return;

            if (this.activePermits.length === 1) {
                return this.bukaKartuPdf();
            }

            const ids = this.activePermits
                .map((p) => p.Id_Transaksi_Permit || p.Id)
                .filter(Boolean);

            if (!ids.length) return;

            this.cetakSemuaLoading = true;
            try {
                const { data } = await axios.post(
                    '/transaksipermit-kartu-pdf-batch',
                    { ids },
                    { responseType: 'blob' },
                );
                this.kartuFiles = [{
                    url: URL.createObjectURL(data),
                    name: `kartu-permit-semua-${ids.length}-kendaraan.pdf`,
                    title: `Kartu Permit (${ids.length} Kendaraan)`,
                    ext: 'pdf',
                    allowDownload: true,
                }];
                this.showKartuViewer = true;
            } catch (e) {
                const pesan = await this.pesanGalatBlob(e);
                ElMessage.error(pesan || 'Kartu permit massal gagal dibuat.');
            } finally {
                this.cetakSemuaLoading = false;
            }
        },
        /** Galat dari permintaan blob datang sebagai Blob, bukan JSON. */
        async pesanGalatBlob(e) {
            const isi = e?.response?.data;
            if (!(isi instanceof Blob)) return isi?.message || '';

            try {
                return JSON.parse(await isi.text())?.message || '';
            } catch {
                return '';
            }
        },
        async fetchPermits() {
            this.loading = true;
            try {
                const { data } = await axios.get('/transaksipermit-my');
                if (data && data.success) {
                    this.internalPermits = data.result || [];
                }
            } catch (e) {
                console.error('Failed fetching permit passes', e);
            } finally {
                this.loading = false;
            }
        },
        toggleFlip() {
            this.isFlipped = !this.isFlipped;
        },
        selectPrevPermit() {
            if (this.selectedIndex > 0) {
                this.selectedIndex -= 1;
            }
        },
        selectNextPermit() {
            if (this.selectedIndex < this.activePermits.length - 1) {
                this.selectedIndex += 1;
            }
        },
        handleKeydown(e) {
            if (this.activePermits.length <= 1) return;
            if (e.key === 'ArrowLeft') {
                this.selectPrevPermit();
            } else if (e.key === 'ArrowRight') {
                this.selectNextPermit();
            }
        },
        getInitials(name) {
            if (!name) return 'HC';
            return name
                .split(' ')
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        getVehicleIcon(typeName = '') {
            const norm = (typeName || '').toLowerCase();
            if (norm.includes('motor') || norm.includes('sepeda')) return 'bicycle';
            if (norm.includes('truk') || norm.includes('truck')) return 'truck-front-fill';
            return 'car-front-fill';
        },
        getVehicleColorHex(colorName = '') {
            const norm = (colorName || '').toLowerCase();
            if (norm.includes('putih') || norm.includes('white')) return '#f8fafc';
            if (norm.includes('hitam') || norm.includes('black')) return '#09090b';
            if (norm.includes('silver') || norm.includes('abu')) return '#94a3b8';
            if (norm.includes('merah') || norm.includes('red')) return '#dc2626';
            if (norm.includes('biru') || norm.includes('blue')) return '#2563eb';
            if (norm.includes('hijau') || norm.includes('green')) return '#16a34a';
            return '#64748b';
        },
        getCategoryBadgeStyle(permit) {
            const hex = permit?.Kode_Warna || '#10b981';
            return {
                backgroundColor: `${hex}15`,
                color: hex,
                border: `1px solid ${hex}40`,
            };
        },
        getLogoGradientStyle(permit) {
            const hex = permit?.Kode_Warna || '#4f46e5';
            return {
                background: `linear-gradient(135deg, ${hex} 0%, #1e1b4b 100%)`,
            };
        },
        getAvatarGradientStyle(permit) {
            const hex = permit?.Kode_Warna || '#4f46e5';
            return {
                background: `linear-gradient(135deg, ${hex}20 0%, ${hex}40 100%)`,
                color: hex,
                borderColor: `${hex}50`,
            };
        },
        getRemainingBadgeLabel(permit) {
            if (!permit.Tanggal_Kadaluarsa) return 'Aktif';
            const diffDays = permit.Sisa_Hari !== undefined ? permit.Sisa_Hari : this.calculateDaysLeft(permit.Tanggal_Kadaluarsa);
            if (diffDays < 0) return 'Kedaluwarsa';
            if (diffDays === 0) return 'Berakhir Hari Ini';
            return `Sisa ${diffDays} Hari`;
        },
        calculateDaysLeft(expiryDateStr) {
            if (!expiryDateStr) return 0;
            const expiry = new Date(expiryDateStr);
            const today = new Date();
            const diffTime = expiry.getTime() - today.getTime();
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch {
                return dateStr;
            }
        },
        formatDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch {
                return dateStr;
            }
        },
        formatPlateMonthYear(dateStr) {
            if (!dateStr) return '12·28';
            try {
                const d = new Date(dateStr);
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yy = String(d.getFullYear()).slice(-2);
                return `${mm}·${yy}`;
            } catch {
                return '--·--';
            }
        },
        formatTanggalInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const [tanggal] = String(inspeksi.mulai).split(' ');
            const [y, m, d] = tanggal.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        formatJamInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const jamMulai = String(inspeksi.mulai).slice(11, 16);
            const jamSelesai = inspeksi.selesai ? String(inspeksi.selesai).slice(11, 16) : '';
            return jamSelesai ? `${jamMulai} – ${jamSelesai}` : jamMulai;
        },
        emitViewDetail() {
            this.$emit('view-detail', this.currentPermit);
        },
    },
};
</script>

<style scoped>
.permit-digital-pass-wrapper {
    width: 100%;
}

/* ═════════════════════════════════════════════════════════════════════════
   1. TOOLBAR & HEADER
   ═════════════════════════════════════════════════════════════════════════ */
.permit-pass-toolbar {
    margin-bottom: 1.75rem;
}

.permit-toolbar-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    width: 100%;
}

.permit-toolbar-left {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.permit-toolbar-title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.permit-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    color: var(--hcis-emerald-800);
    font-size: 0.71875rem;
    font-weight: 800;
    line-height: 1;
    flex-shrink: 0;
}

.permit-count-pill__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    box-shadow: 0 0 5px var(--hcis-emerald-500);
}

.permit-pass-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0;
    line-height: 1.25;
}

.permit-pass-subtitle {
    font-size: 0.8125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    line-height: 1.5;
    margin: 0;
}

.permit-toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Ensure default font family is used with tabular numbers for alignment */
.permit-font-tabular {
    font-variant-numeric: tabular-nums;
    font-family: inherit;
}

/* ═════════════════════════════════════════════════════════════════════════
   2. MULTI-VEHICLE DECK CONTAINER & CAROUSEL
   ═════════════════════════════════════════════════════════════════════════ */
.permit-vehicle-deck-container {
    background: var(--hcis-white);
    border: 1.5px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 1.25rem 1.35rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    margin-bottom: 1.75rem;
}

.permit-deck-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.permit-deck-title-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.permit-deck-header-title {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    text-transform: uppercase;
}

.permit-deck-nav-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.permit-deck-counter {
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.permit-deck-nav-btns {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.permit-deck-arrow-btn {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 6px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}

.permit-deck-arrow-btn:hover:not(:disabled) {
    background: var(--hcis-brand-600, var(--hcis-indigo-600));
    color: var(--hcis-white);
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.permit-deck-arrow-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

/* Horizontal Scrollable Deck */
.permit-vehicle-deck-scroll {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(290px, 1fr);
    gap: 1rem;
    overflow-x: auto;
    padding: 0.25rem 0.25rem 0.5rem 0.25rem;
    scrollbar-width: thin;
}

.permit-vehicle-deck-card {
    position: relative;
    background: var(--hcis-slate-50);
    border: 1.5px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 0.95rem 1.25rem;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}

.permit-vehicle-deck-card:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-300);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08);
}

.permit-vehicle-deck-card.is-active {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.14), 0 0 0 1px var(--hcis-indigo-600);
}

.permit-deck-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
}

.permit-deck-card-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.permit-deck-card-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-deck-card-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.permit-deck-card-plate {
    font-size: 0.9375rem;
    font-weight: 850;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    letter-spacing: 0.03em;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-deck-card-model {
    font-size: 0.75rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-weight: 500;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-deck-card-aside {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.4rem;
    flex-shrink: 0;
}

.permit-deck-card-expiry {
    font-size: 0.6875rem;
    font-weight: 750;
    color: var(--hcis-emerald-600);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 4px;
    padding: 0.15rem 0.5rem;
    flex-shrink: 0;
}

/* Stepper Dots below Card */
.permit-stepper-dots {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.permit-dot-btn {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-slate-300);
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
}

.permit-dot-btn.is-active {
    width: 22px;
    border-radius: 9999px;
    background: var(--hcis-indigo-600);
}

/* Stage Layout */
.permit-pass-stage {
    width: 100%;
}

.permit-pass-stage-grid {
    display: grid;
    grid-template-columns: minmax(360px, 430px) minmax(0, 1fr);
    gap: 2rem;
    align-items: start;
    width: 100%;
}

/* ═════════════════════════════════════════════════════════════════════════
   3. LANYARD STRAP & METALLIC HARDWARE
   ═════════════════════════════════════════════════════════════════════════ */
.permit-card-showcase-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.permit-lanyard-assembly {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    margin-bottom: -18px;
    z-index: 10;
    pointer-events: none;
}
.permit-lanyard-ribbon {
    width: 140px;
    height: 38px;
    background: var(--hcis-slate-800);
    background-image: repeating-linear-gradient(
        45deg,
        var(--hcis-slate-800),
        var(--hcis-slate-800) 8px,
        var(--hcis-slate-700) 8px,
        var(--hcis-slate-700) 16px
    );
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px 4px 0 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.permit-lanyard-pattern {
    font-size: 0.375rem;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.15em;
    white-space: nowrap;
}
.permit-lanyard-hardware {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.permit-lanyard-swivel {
    width: 24px;
    height: 12px;
    border: 3px solid var(--hcis-slate-400);
    border-radius: 6px 6px 0 0;
    background: var(--hcis-slate-500);
    box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.6);
}
.permit-lanyard-hook {
    width: 16px;
    height: 18px;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-300) 50%, var(--hcis-slate-500) 100%);
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* ═════════════════════════════════════════════════════════════════════════
   3D CARD PERSPECTIVE SCENE & WHITE PHYSICAL BADGE
   ═════════════════════════════════════════════════════════════════════════ */
.permit-card-3d-scene {
    width: 100%;
    max-width: 430px;
    perspective: 1200px;
    cursor: pointer;
    user-select: none;
}

.permit-physical-badge {
    position: relative;
    width: 100%;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.34, 1.4, 0.64, 1);
}

.permit-physical-badge.is-flipped {
    transform: rotateY(180deg);
}

.permit-card-3d-scene:hover .permit-physical-badge:not(.is-flipped) {
    transform: translateY(-4px) rotateY(4deg) rotateX(2deg);
}

.permit-card-3d-scene:hover .permit-physical-badge.is-flipped {
    transform: translateY(-4px) rotateY(184deg) rotateX(2deg);
}

/* Subtle Security Guilloche Pattern Overlay */
.permit-security-pattern-overlay {
    position: absolute;
    inset: 0;
    border-radius: 20px;
    background-image:
        radial-gradient(circle at 50% 50%, transparent 80%, rgba(79, 70, 229, 0.03) 100%),
        repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(148, 163, 184, 0.04) 10px, rgba(148, 163, 184, 0.04) 11px),
        repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(148, 163, 184, 0.04) 10px, rgba(148, 163, 184, 0.04) 11px);
    pointer-events: none;
    z-index: 1;
}

/* Ambient Corner Aura Glows */
.permit-ambient-glow {
    position: absolute;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
}
.permit-ambient-glow--top {
    top: -50px;
    right: -50px;
}
.permit-ambient-glow--bottom {
    bottom: -50px;
    left: -50px;
}

/* Watermark Shield Emblem in Background */
.permit-watermark-emblem {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 220px;
    height: 220px;
    color: rgba(79, 70, 229, 0.035);
    pointer-events: none;
    z-index: 1;
}
.permit-watermark-svg {
    width: 100%;
    height: 100%;
}

/* Hole punch slot at top center */
.permit-slot-punch {
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 44px;
    height: 10px;
    border-radius: 9999px;
    background: var(--hcis-slate-100);
    border: 1.5px solid var(--hcis-slate-300);
    box-shadow: inset 0 2px 4px rgba(15, 23, 42, 0.2);
    z-index: 7;
}

/* Face padding & comfortable gaps */
.permit-badge-face {
    position: relative;
    width: 100%;
    padding: 2.25rem 1.6rem 1.6rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    border-radius: 20px;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    overflow: hidden;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.14), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    border: 1.5px solid var(--hcis-slate-300, var(--hcis-slate-300));
}

.permit-badge-skeleton-face {
    background: var(--hcis-white);
}

.permit-badge-face--front {
    position: relative;
    background:
        radial-gradient(ellipse 350px 220px at 95% 5%, rgba(79, 70, 229, 0.08) 0%, transparent 60%),
        radial-gradient(ellipse 300px 200px at 5% 95%, rgba(16, 185, 129, 0.07) 0%, transparent 55%),
        linear-gradient(165deg, var(--hcis-white) 0%, var(--hcis-slate-50) 45%, var(--hcis-slate-100) 100%);
    transform: rotateY(0deg);
}

.permit-badge-face--back {
    position: absolute;
    inset: 0;
    height: 100%;
    background:
        radial-gradient(ellipse 350px 220px at 5% 5%, rgba(79, 70, 229, 0.08) 0%, transparent 60%),
        linear-gradient(165deg, var(--hcis-white) 0%, var(--hcis-slate-50) 50%, var(--hcis-slate-100) 100%);
    transform: rotateY(180deg);
}

/* 1. Header */
.permit-badge-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding-bottom: 0.95rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    position: relative;
    z-index: 2;
}

.permit-badge-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.permit-badge-logo {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
}
.permit-badge-brand-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.permit-badge-kicker {
    font-size: 0.5625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
}
.permit-badge-heading {
    font-size: 0.875rem;
    font-weight: 900;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0;
}

.permit-badge-category-box {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.3rem;
}
.permit-badge-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}
.permit-beacon-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    box-shadow: 0 0 6px currentColor;
    animation: permitPulse 2s infinite;
}
.permit-rfid-chip-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.53125rem;
    font-weight: 750;
    color: var(--hcis-emerald-600);
    letter-spacing: 0.06em;
}

/* 2. Chip & Stamp Row */
.permit-tech-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 2;
    margin: 0.25rem 0;
}
.permit-emv-chip {
    position: relative;
    width: 44px;
    height: 32px;
    border-radius: 6px;
    background: linear-gradient(135deg, var(--hcis-yellow-200) 0%, #eab308 50%, #ca8a04 100%);
    border: 1px solid var(--hcis-yellow-700);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), inset 0 1px 1px rgba(255, 255, 255, 0.9);
    overflow: hidden;
}
.permit-emv-line {
    position: absolute;
    background: #854d0e;
    opacity: 0.7;
}
.permit-emv-line--h1 { top: 10px; left: 0; right: 0; height: 1px; }
.permit-emv-line--h2 { bottom: 10px; left: 0; right: 0; height: 1px; }
.permit-emv-line--v1 { top: 0; bottom: 0; left: 16px; width: 1px; }
.permit-emv-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 14px;
    height: 12px;
    border: 1px solid #854d0e;
    border-radius: 3px;
}

.permit-contactless-waves {
    width: 22px;
    height: 22px;
    color: var(--hcis-indigo-600);
}
.permit-nfc-icon {
    width: 100%;
    height: 100%;
}

.permit-security-stamp {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.permit-security-stamp span {
    font-size: 0.53125rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-emerald-800);
}

/* 3. Embossed Plate */
.permit-plate-container {
    width: 100%;
    margin: 0.25rem 0;
}

.permit-plate-box {
    position: relative;
    background: #09090b;
    border: 2px solid #52525b;
    border-radius: 12px;
    padding: 0.95rem 1.25rem;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.3);
    z-index: 2;
}
.permit-plate-rivet {
    position: absolute;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: radial-gradient(circle, #e4e4e7 30%, #52525b 100%);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.8);
}
.permit-plate-rivet--tl { top: 6px; left: 8px; }
.permit-plate-rivet--tr { top: 6px; right: 8px; }
.permit-plate-rivet--bl { bottom: 6px; left: 8px; }
.permit-plate-rivet--br { bottom: 6px; right: 8px; }

.permit-plate-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
}
.permit-plate-vehicle-icon {
    color: rgba(255, 255, 255, 0.6);
    flex-shrink: 0;
}
.permit-plate-letters {
    font-size: 1.55rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    color: var(--hcis-white);
    text-shadow: 0 1px 0 #71717a, 0 2px 0 #3f3f46, 0 3px 6px rgba(0, 0, 0, 0.8);
}
.permit-plate-expiry {
    font-size: 0.78125rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.05em;
    flex-shrink: 0;
}

/* 4. Specs Grid */
.permit-specs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.95rem;
    position: relative;
    z-index: 2;
}
.permit-spec-card {
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 10px;
    padding: 0.85rem 0.95rem;
    backdrop-filter: blur(4px);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.permit-spec-card-title {
    display: block;
    font-size: 0.5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
.permit-spec-card-main {
    font-size: 0.84375rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-weight: 750;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.permit-spec-divider {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.35rem;
    padding-top: 0.45rem;
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}
.permit-spec-color-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}
.permit-spec-text {
    font-size: 0.71875rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.permit-spec-year {
    font-size: 0.71875rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    flex-shrink: 0;
}
.permit-color-swatch {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}

.permit-driver-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.25rem;
}

.permit-avatar-circle {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 50%;
    border: 1px solid var(--hcis-indigo-200);
    font-size: 0.6875rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.permit-driver-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.permit-driver-title {
    font-size: 0.8125rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.permit-driver-subtitle {
    font-size: 0.65625rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 5. Footer Strip */
.permit-badge-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 12px;
    padding: 0.85rem 1.05rem;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 2;
}
.permit-validity-heading {
    font-size: 0.5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
.permit-validity-daterange {
    font-size: 0.78125rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
    margin-top: 0.25rem;
}
.permit-validity-sep {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-weight: normal;
}
.permit-validity-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.65625rem;
    font-weight: 800;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-800);
    border: 1px solid var(--hcis-emerald-200);
    text-transform: uppercase;
    margin-top: 0.35rem;
}
.permit-live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    box-shadow: 0 0 6px var(--hcis-emerald-500);
    animation: permitPulse 1.5s infinite;
}

/* Official Token Seal Box */
.permit-token-seal-box {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
    gap: 0.25rem;
}
.permit-token-seal-inner {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 6px;
    padding: 0.25rem 0.55rem;
}
.permit-token-title {
    font-size: 0.59375rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-indigo-600);
}
.permit-token-id {
    font-size: 0.65625rem;
    font-weight: 750;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

/* ═════════════════════════════════════════════════════════════════════════
   BACK FACE STYLING
   ═════════════════════════════════════════════════════════════════════════ */
.permit-magstripe-bar {
    height: 42px;
    margin: 0.5rem -1.6rem 0.5rem -1.6rem;
    background: var(--hcis-slate-800);
    border-top: 1px solid var(--hcis-slate-700);
    border-bottom: 1px solid var(--hcis-slate-700);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4);
    position: relative;
    z-index: 2;
}
.permit-magstripe-glimmer {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
}

.permit-back-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
    z-index: 2;
}
.permit-back-qr-wrapper {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 0.65rem 0.85rem;
    color: var(--hcis-slate-900);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
}

.permit-back-qr-frame {
    width: 68px;
    height: 68px;
    flex-shrink: 0;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

.permit-back-qr-svg {
    width: 100%;
    height: 100%;
    display: block;
}

.permit-back-qr-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.permit-back-qr-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1;
}

.permit-back-qr-badge-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.permit-back-qr-badge {
    font-size: 0.5625rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 4px;
    padding: 0.1rem 0.35rem;
    text-transform: uppercase;
    display: inline-block;
}

.permit-back-qr-token {
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-back-qr-hint {
    font-size: 0.625rem;
    color: var(--hcis-slate-500);
    line-height: 1.3;
}

.permit-guidelines-heading {
    display: block;
    font-size: 0.59375rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-bottom: 0.5rem;
}
.permit-guidelines-list {
    margin: 0;
    padding-left: 1.15rem;
    font-size: 0.71875rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    line-height: 1.5;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.permit-back-bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}
.permit-back-stamp-group {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.text-emerald {
    color: var(--hcis-emerald-500);
}
.permit-stamp-verified {
    font-size: 0.65625rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-emerald-800);
    text-transform: uppercase;
}
.permit-stamp-timestamp {
    font-size: 0.59375rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
.permit-back-emergency-contact {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.15rem;
}
.permit-emergency-label {
    font-size: 0.59375rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
.permit-emergency-number {
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-size: 0.8125rem;
}

/* Controls wrapper */
.permit-flip-controls-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    margin-top: 1.5rem;
    width: 100%;
}
.permit-flip-btn-group {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    max-width: 430px;
}

:deep(.permit-flip-nav-btn) {
    width: 2.5rem !important;
    height: 2.5rem !important;
    flex: 0 0 2.5rem !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 8px !important;
}

:deep(.permit-flip-main-btn) {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    height: 2.5rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
    font-size: 0.8125rem !important;
    white-space: nowrap !important;
}

.permit-flip-hint {
    font-size: 0.75rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

/* ═════════════════════════════════════════════════════════════════════════
   4. UPGRADED CLEARANCE METRIC TILES & GATE SCOPE
   ═════════════════════════════════════════════════════════════════════════ */
.permit-pass-actions-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
}

/* Keempat pod berjajar dalam SATU baris. Kolom ini bersebelahan dengan kolom
   kartu selebar 360–430px, jadi tiap pod hanya kebagian ruang sempit — takaran
   padding, ikon, dan hurufnya diturunkan di bawah agar tetap terbaca. */
.permit-metric-tiles-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.permit-metric-pod {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 12px;
    padding: 0.85rem 0.9rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03), 0 0 0 1px rgba(255, 255, 255, 0.8) inset;
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.permit-metric-pod:hover {
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300));
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
}

.permit-pod-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.15rem;
    min-width: 0;
}

.permit-pod-icon-box {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.permit-pod-icon--brand {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-100);
}
.permit-pod-icon--indigo {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #ccfbf1;
}
.permit-pod-icon--success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-100);
}

.permit-pod-kicker {
    font-size: 0.5625rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    line-height: 1.2;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
    min-width: 0;
}

.permit-pod-primary {
    font-size: 0.875rem;
    font-weight: 850;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.3;
}

.permit-pod-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin-top: 0.15rem;
    min-width: 0;
}

/* Gate Scope Container */
.permit-gate-scope-card {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(241, 245, 249, 0.85) 100%);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 14px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
    position: relative;
}

.permit-scope-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.permit-scope-title-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.permit-scope-heading {
    font-size: 0.71875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

/*
 * Lencana zona memakai warna kategori dari master (Kode_Warna_UI) lewat
 * getCategoryBadgeStyle(), bukan hijau tetap. Hijau tetap yang dipakai lencana
 * "LIVE CLEARANCE" lama membuat setiap zona - termasuk MERAH - terbaca aman.
 */
.permit-scope-zona-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    font-size: 0.59375rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.permit-scope-akses-line {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.65rem 0.95rem;
    border-radius: 10px;
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.permit-scope-akses-line--kosong {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border-style: dashed;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.permit-scope-akses-text {
    font-size: 0.8125rem;
    font-weight: 700;
    line-height: 1.5;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.permit-scope-akses-line--kosong .permit-scope-akses-text {
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.permit-scope-statement-callout {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--hcis-slate-300, var(--hcis-slate-300));
}

.permit-scope-callout-inner {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.permit-callout-icon {
    margin-top: 0.15rem;
    flex-shrink: 0;
}

.permit-scope-statement-text {
    font-size: 0.8125rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    line-height: 1.6;
    margin: 0;
}

/* ═════════════════════════════════════════════════════════════════════════
   5. RIGHT ACTION TILES
   ═════════════════════════════════════════════════════════════════════════ */
.permit-actions-card-wrap {
    margin-top: 0;
}

.permit-quick-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.permit-action-tile {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 12px;
    padding: 1.1rem 1.35rem;
    cursor: pointer;
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.permit-action-tile:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -4px rgba(79, 70, 229, 0.12);
}

.permit-action-tile:hover .permit-chevron-icon {
    transform: translateX(3px);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.permit-action-icon-box {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.permit-action-icon--brand {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-100);
}
.permit-action-icon--success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-100);
}
.permit-action-icon--info {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #ccfbf1;
}

.permit-action-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.permit-action-title {
    display: block;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-size: 0.875rem;
    font-weight: 750;
    margin: 0;
}

.permit-action-desc {
    font-size: 0.78125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    display: block;
    line-height: 1.45;
    margin: 0;
}

.permit-chevron-icon {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    flex-shrink: 0;
    transition: transform 180ms ease, color 180ms ease;
}

.permit-action-tile.is-loading {
    cursor: wait;
    pointer-events: none;
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    background: linear-gradient(180deg, rgba(238, 242, 255, 0.65) 0%, var(--hcis-white) 100%);
    box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.15);
}

.permit-action-loading-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.5rem;
    border-radius: 9999px;
    background: var(--hcis-indigo-100, #e0e7ff);
    color: var(--hcis-indigo-700, #4338ca);
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.2;
}

.permit-action-loading-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-indigo-600, #4f46e5);
    animation: permitPulse 1.2s ease-in-out infinite;
}

.permit-mini-spinner {
    width: 1.15rem;
    height: 1.15rem;
    border: 2px solid var(--hcis-indigo-200, #c7d2fe);
    border-top-color: var(--hcis-indigo-600, #4f46e5);
    border-radius: 50%;
    animation: permitSpin 0.75s linear infinite;
    flex-shrink: 0;
}

.permit-icon-spinning {
    display: inline-block;
    animation: permitSpin 0.85s linear infinite;
}

@keyframes permitSpin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* ═════════════════════════════════════════════════════════════════════════
   6. EMPTY STATE STYLING
   ═════════════════════════════════════════════════════════════════════════ */
.permit-empty-pass-wrapper {
    width: 100%;
}

.permit-empty-state-box {
    padding: 2rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.permit-empty-actions-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

@keyframes permitPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.85); }
}

/* ═════════════════════════════════════════════════════════════════════════
   INSPECTION HERO CARD & TOP BANNER STYLES (HYBRID DISPLAY)
   ═════════════════════════════════════════════════════════════════════════ */

/* Top Actionable Reminder Banner */
.permit-pass-inspection-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.15rem;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(99, 102, 241, 0.04) 100%);
    border: 1px solid rgba(99, 102, 241, 0.28);
    border-left: 4px solid var(--hcis-brand-primary, #4f46e5);
    border-radius: 12px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 4px 16px -4px rgba(79, 70, 229, 0.12);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.permit-pass-inspection-banner:hover {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.12) 0%, rgba(99, 102, 241, 0.07) 100%);
    border-color: rgba(99, 102, 241, 0.45);
    box-shadow: 0 6px 20px -4px rgba(79, 70, 229, 0.18);
    transform: translateY(-1px);
}

.permit-inspection-banner__left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}

.permit-inspection-banner__icon-box {
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

.permit-inspection-banner__pulse {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 2px #ffffff, 0 0 8px #10b981;
    animation: permitPulse 2s infinite ease-in-out;
}

.permit-inspection-banner__content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.permit-inspection-banner__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-inspection-banner__kicker {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #4f46e5;
}

.permit-inspection-banner__plate {
    display: inline-block;
    padding: 0.1rem 0.45rem;
    background: #0f172a;
    color: #f8fafc;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.permit-inspection-banner__model {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
}

.permit-inspection-banner__meta-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    color: #64748b;
    flex-wrap: wrap;
}

.permit-inspection-banner__datetime {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
    color: #1e293b;
}

.permit-inspection-banner__time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.1rem 0.4rem;
    background: rgba(79, 70, 229, 0.1);
    color: #4338ca;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.6875rem;
}

.permit-inspection-banner__sep {
    color: #cbd5e1;
}

.permit-inspection-banner__pos {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
    color: #059669;
}

.permit-inspection-banner__right {
    flex-shrink: 0;
}

/* Smart Hero Inspection Ticket (In Empty State) */
.permit-pass-scheduled-card {
    width: 100%;
}

.permit-pass-hero-content {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0.25rem 0;
}

.permit-pass-hero-vehicle {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 10px;
    flex-wrap: wrap;
}

.permit-hero-plate-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.75rem;
    background: #0f172a;
    color: #ffffff;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    border: 1.5px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.permit-pass-hero-vmeta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.permit-pass-hero-vtitle {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.9375rem;
    color: #0f172a;
}

.permit-pass-hero-vyear {
    color: #64748b;
    font-size: 0.8125rem;
}

.permit-pass-hero-vsub {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: #64748b;
}

.permit-color-swatch-sm {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    display: inline-block;
}

.permit-badge-category-pill--sm {
    font-size: 0.65625rem !important;
    padding: 0.15rem 0.45rem !important;
}

.permit-pass-hero-sep {
    color: #cbd5e1;
}

.permit-pass-hero-trans {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.1rem;
}

.permit-pass-hero-trans-label {
    font-size: 0.6875rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.permit-pass-hero-trans-val {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #334155;
}

/* Ticket Grid */
.permit-pass-ticket-grid {
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04) 0%, rgba(248, 250, 252, 0.9) 100%);
    border: 1px dashed rgba(99, 102, 241, 0.35);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    gap: 1.25rem;
}

.permit-pass-ticket-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1;
    min-width: 0;
}

.permit-pass-ticket-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-pass-ticket-icon.is-calendar {
    background: rgba(79, 70, 229, 0.12);
    color: #4f46e5;
}

.permit-pass-ticket-icon.is-location {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.permit-pass-ticket-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.permit-pass-ticket-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #64748b;
}

.permit-pass-ticket-val {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-pass-ticket-date {
    font-size: 0.875rem;
    font-weight: 700;
    color: #0f172a;
}

.permit-pass-ticket-time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    background: #4f46e5;
    color: #ffffff;
    border-radius: 5px;
    font-size: 0.71875rem;
    font-weight: 700;
    white-space: nowrap;
}

.permit-pass-ticket-pos {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.875rem;
    color: #0f172a;
    flex-wrap: wrap;
}

.permit-pass-ticket-pos-tag {
    font-size: 0.75rem;
    color: #64748b;
}

.permit-pass-ticket-divider {
    width: 1px;
    height: 48px;
    background: rgba(203, 213, 225, 0.6);
}

/* Callout Notice */
.permit-pass-hero-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.95rem;
    border-radius: 8px;
    font-size: 0.78125rem;
    line-height: 1.45;
}

.permit-pass-hero-notice.is-info {
    background: rgba(79, 70, 229, 0.06);
    border: 1px solid rgba(79, 70, 229, 0.15);
    color: #3730a3;
}

.permit-pass-hero-notice.is-danger {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #991b1b;
}

/* Bottom Actions */
.permit-pass-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    padding-top: 0.35rem;
    border-top: 1px solid rgba(226, 232, 240, 0.6);
}

/* ═════════════════════════════════════════════════════════════════════════
   RESPONSIVE LAYOUT FOR TABLET & MOBILE DEVICES
   ═════════════════════════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
    .permit-pass-stage-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 1.75rem;
    }
    .permit-card-showcase-column {
        max-width: 440px;
        margin: 0 auto;
    }
    .permit-vehicle-deck-scroll {
        grid-auto-columns: minmax(280px, 1fr);
    }
}

@media (max-width: 768px) {
    .permit-toolbar-content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .permit-toolbar-right {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
        width: 100%;
    }
    .permit-toolbar-right :deep(.hcis-btn),
    .permit-toolbar-print-all-btn,
    .permit-toolbar-add-btn {
        width: 100% !important;
        justify-content: center !important;
        min-height: 2.35rem !important;
        height: 2.35rem !important;
        font-size: 0.8125rem !important;
        font-weight: 700 !important;
        border-radius: var(--hcis-radius-md, 8px) !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    .permit-toolbar-right :deep(.hcis-btn__text) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .permit-vehicle-deck-container {
        padding: 1rem 1.15rem;
        margin-bottom: 1.25rem;
    }
    .permit-deck-header-row {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .permit-vehicle-deck-scroll {
        grid-auto-columns: minmax(250px, 82vw);
        scroll-snap-type: x mandatory;
        padding-bottom: 0.5rem;
    }
    .permit-vehicle-deck-card {
        scroll-snap-align: start;
    }
    .permit-metric-tiles-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .permit-pass-inspection-banner {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem 0.85rem;
        gap: 0.75rem;
    }
    .permit-inspection-banner__btn {
        width: 100%;
        justify-content: center;
    }
    .permit-pass-hero-vehicle {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.65rem;
    }
    .permit-pass-hero-trans {
        align-items: flex-start;
    }
    .permit-pass-ticket-grid {
        flex-direction: column;
        align-items: stretch;
        gap: 0.85rem;
        padding: 0.85rem;
    }
    .permit-pass-ticket-divider {
        width: 100%;
        height: 1px;
    }
    .permit-pass-hero-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .permit-pass-hero-actions > * {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .permit-pass-toolbar {
        margin-bottom: 0.75rem;
        padding: 0.75rem 0.85rem;
        background: #ffffff;
        border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
        border-radius: var(--hcis-radius-xl, 14px);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    .permit-toolbar-content {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }
    .permit-toolbar-left {
        gap: 0.25rem;
        min-width: 0;
        width: 100%;
    }
    .permit-toolbar-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 0.5rem;
    }
    .permit-pass-title {
        font-size: 0.9375rem;
        font-weight: 850;
        letter-spacing: -0.01em;
        line-height: 1.25;
        white-space: nowrap;
    }
    .permit-count-pill {
        padding: 0.15rem 0.45rem !important;
        font-size: 0.65625rem !important;
        font-weight: 750 !important;
        gap: 0.3rem !important;
    }
    .permit-pass-subtitle {
        display: none;
    }
    .permit-toolbar-right {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
        margin-top: 0;
    }
    .permit-toolbar-right :deep(.hcis-btn),
    .permit-toolbar-print-all-btn,
    .permit-toolbar-add-btn {
        width: 100% !important;
        min-height: 2.35rem !important;
        height: 2.35rem !important;
        padding-left: 0.45rem !important;
        padding-right: 0.45rem !important;
        font-size: 0.78125rem !important;
        font-weight: 750 !important;
        border-radius: var(--hcis-radius-md, 8px) !important;
        justify-content: center !important;
        text-align: center !important;
        box-sizing: border-box !important;
    }
    .permit-toolbar-right :deep(.hcis-btn__text) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .permit-lanyard-assembly {
        margin-bottom: -10px;
    }
    .permit-lanyard-ribbon {
        width: 96px;
        height: 24px;
    }
    .permit-lanyard-pattern {
        font-size: 0.28125rem;
    }
    .permit-lanyard-swivel {
        width: 16px;
        height: 8px;
    }
    .permit-lanyard-hook {
        width: 12px;
        height: 12px;
    }
    .permit-badge-face {
        padding: 1.75rem 1.15rem 1.15rem 1.15rem;
        gap: 0.85rem;
        border-radius: 16px;
    }
    .permit-slot-punch {
        top: 8px;
        width: 36px;
        height: 8px;
    }
    .permit-badge-header {
        gap: 0.5rem;
        padding-bottom: 0.65rem;
    }
    .permit-badge-logo {
        width: 2rem;
        height: 2rem;
    }
    .permit-badge-heading {
        font-size: 0.75rem;
    }
    .permit-badge-kicker {
        font-size: 0.46875rem;
    }
    .permit-badge-category-pill {
        font-size: 0.59375rem;
        padding: 0.2rem 0.5rem;
    }
    .permit-emv-chip {
        width: 36px;
        height: 26px;
    }
    .permit-security-stamp {
        padding: 0.2rem 0.45rem;
    }
    .permit-security-stamp span {
        font-size: 0.46875rem;
    }
    .permit-plate-box {
        padding: 0.65rem 0.85rem;
        border-radius: 10px;
    }
    .permit-plate-letters {
        font-size: clamp(1.15rem, 5.5vw, 1.45rem);
        letter-spacing: 0.05em;
    }
    .permit-plate-expiry {
        font-size: 0.6875rem;
    }
    .permit-specs-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        align-items: stretch;
    }
    .permit-spec-card {
        padding: 0.6rem 0.7rem;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 5.25rem;
        height: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .permit-spec-card-title {
        font-size: 0.5rem;
    }
    .permit-spec-card-main {
        font-size: 0.75rem;
    }
    .permit-spec-divider {
        margin-top: auto;
        padding-top: 0.3rem;
    }
    .permit-avatar-circle {
        width: 1.65rem;
        height: 1.65rem;
        font-size: 0.59375rem;
        flex-shrink: 0;
    }
    .permit-driver-row {
        gap: 0.45rem;
        min-width: 0;
    }
    .permit-driver-info {
        min-width: 0;
        overflow: hidden;
    }
    .permit-driver-title {
        font-size: 0.6875rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .permit-driver-subtitle {
        font-size: 0.5625rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .permit-badge-footer {
        padding: 0.65rem 0.85rem;
        border-radius: 10px;
    }
    .permit-validity-heading {
        font-size: 0.5rem;
    }
    .permit-validity-daterange {
        font-size: 0.71875rem;
    }
    .permit-token-seal-inner {
        padding: 0.2rem 0.45rem;
    }
    .permit-token-title {
        font-size: 0.53125rem;
    }
    .permit-token-id {
        font-size: 0.59375rem;
    }
    .permit-magstripe-bar {
        height: 32px;
        margin: 0.35rem -1.15rem 0.45rem -1.15rem;
    }
    .permit-back-content {
        gap: 0.65rem;
    }
    .permit-back-qr-wrapper {
        padding: 0.5rem 0.65rem;
        gap: 0.65rem;
        border-radius: 10px;
    }
    .permit-back-qr-frame {
        width: 52px;
        height: 52px;
        padding: 3px;
        border-radius: 6px;
    }
    .permit-back-qr-badge {
        font-size: 0.5rem;
        padding: 0.1rem 0.3rem;
    }
    .permit-back-qr-token {
        font-size: 0.71875rem;
    }
    .permit-back-qr-hint {
        font-size: 0.5625rem;
        line-height: 1.25;
    }
    .permit-guidelines-heading {
        font-size: 0.53125rem;
        margin-bottom: 0.35rem;
    }
    .permit-guidelines-list {
        padding-left: 1rem;
        font-size: 0.65625rem;
        line-height: 1.35;
        gap: 0.25rem;
    }
    .permit-back-bottom-row {
        padding-top: 0.55rem;
        gap: 0.5rem;
    }
    .permit-stamp-verified {
        font-size: 0.5625rem;
    }
    .permit-stamp-timestamp {
        font-size: 0.53125rem;
    }
    .permit-emergency-label {
        font-size: 0.53125rem;
    }
    .permit-emergency-number {
        font-size: 0.71875rem;
    }
    .permit-flip-controls-wrapper {
        margin-top: 1rem;
        gap: 0.65rem;
    }
    :deep(.permit-flip-nav-btn) {
        width: 2.25rem !important;
        height: 2.25rem !important;
        flex: 0 0 2.25rem !important;
    }
    :deep(.permit-flip-main-btn) {
        height: 2.25rem !important;
        font-size: 0.75rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    .permit-flip-hint {
        font-size: 0.6875rem;
    }
    .permit-metric-tiles-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.45rem !important;
        margin-bottom: 0.65rem !important;
    }
    .permit-metric-pod {
        padding: 0.55rem 0.6rem !important;
        border-radius: 8px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        min-height: 4.75rem !important;
        height: 100% !important;
        gap: 0.2rem !important;
        min-width: 0 !important;
    }
    .permit-pod-header {
        display: flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
        margin-bottom: 0.1rem !important;
    }
    .permit-pod-icon-box {
        width: 1.45rem !important;
        height: 1.45rem !important;
        font-size: 0.75rem !important;
        border-radius: 5px !important;
    }
    .permit-pod-kicker {
        font-size: 0.5rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .permit-pod-primary {
        font-size: 0.75rem !important;
        font-weight: 850 !important;
        line-height: 1.2 !important;
    }
    .permit-pod-meta {
        font-size: 0.625rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .permit-gate-scope-card {
        padding: 0.65rem 0.75rem !important;
        border-radius: 10px !important;
    }
    .permit-scope-header {
        margin-bottom: 0.45rem !important;
        flex-wrap: wrap !important;
        gap: 0.35rem !important;
    }
    .permit-scope-heading {
        font-size: 0.625rem !important;
    }
    .permit-scope-zona-badge {
        font-size: 0.53125rem !important;
        padding: 0.15rem 0.45rem !important;
    }
    .permit-scope-akses-line {
        gap: 0.45rem !important;
        padding: 0.35rem 0.55rem !important;
        border-radius: 6px !important;
    }
    .permit-scope-akses-text {
        font-size: 0.6875rem !important;
    }
    .permit-scope-statement-callout {
        margin-top: 0.45rem !important;
        padding-top: 0.45rem !important;
    }
    .permit-scope-statement-text {
        font-size: 0.65625rem !important;
        line-height: 1.35 !important;
    }
    .permit-actions-card-wrap {
        margin-top: 0.25rem;
    }
    .permit-quick-actions-list {
        gap: 0.5rem;
    }
    .permit-action-tile {
        padding: 0.65rem 0.85rem;
        gap: 0.75rem;
        border-radius: 10px;
        -webkit-tap-highlight-color: transparent;
    }
    .permit-action-tile:active {
        transform: scale(0.985);
        background: var(--hcis-surface-soft, var(--hcis-slate-100));
    }
    .permit-action-icon-box {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 6px;
    }
    .permit-action-title {
        font-size: 0.78125rem;
        font-weight: 800;
        color: var(--hcis-slate-900, var(--hcis-slate-900));
        line-height: 1.25;
    }
    .permit-action-desc {
        font-size: 0.6875rem;
        line-height: 1.3;
        color: var(--hcis-slate-500, var(--hcis-slate-500));
        margin-top: 0.1rem;
    }
    .permit-chevron-icon {
        color: var(--hcis-slate-400, var(--hcis-slate-400));
        flex-shrink: 0;
    }
}

@media (max-width: 380px) {
    .permit-pass-toolbar {
        padding: 0.55rem 0.65rem !important;
        margin-bottom: 0.65rem !important;
    }
    .permit-pass-title {
        font-size: 0.875rem !important;
    }
    .permit-count-pill {
        padding: 0.125rem 0.35rem !important;
        font-size: 0.625rem !important;
    }
    .permit-toolbar-right :deep(.hcis-btn),
    .permit-toolbar-print-all-btn,
    .permit-toolbar-add-btn {
        height: 2.2rem !important;
        min-height: 2.2rem !important;
        padding-left: 0.35rem !important;
        padding-right: 0.35rem !important;
        font-size: 0.71875rem !important;
    }
    .permit-lanyard-assembly {
        margin-bottom: -8px !important;
    }
    .permit-lanyard-ribbon {
        width: 80px !important;
        height: 20px !important;
    }
    .permit-lanyard-swivel {
        width: 14px !important;
        height: 7px !important;
    }
    .permit-lanyard-hook {
        width: 10px !important;
        height: 10px !important;
    }
    .permit-badge-face {
        padding: 1.35rem 0.65rem 0.65rem 0.65rem !important;
        gap: 0.5rem !important;
        border-radius: 14px !important;
    }
    .permit-badge-header {
        gap: 0.35rem !important;
        padding-bottom: 0.45rem !important;
    }
    .permit-badge-logo {
        width: 1.75rem !important;
        height: 1.75rem !important;
        border-radius: 5px !important;
    }
    .permit-badge-heading {
        font-size: 0.6875rem !important;
        line-height: 1.15 !important;
    }
    .permit-badge-kicker {
        font-size: 0.4375rem !important;
    }
    .permit-badge-category-pill {
        font-size: 0.5rem !important;
        padding: 0.15rem 0.35rem !important;
        gap: 0.25rem !important;
    }
    .permit-rfid-chip-label {
        font-size: 0.46875rem !important;
    }
    .permit-tech-row {
        margin: 0.1rem 0 !important;
    }
    .permit-emv-chip {
        width: 34px !important;
        height: 24px !important;
    }
    .permit-contactless-waves {
        width: 18px !important;
        height: 18px !important;
    }
    .permit-security-stamp {
        padding: 0.15rem 0.4rem !important;
    }
    .permit-security-stamp span {
        font-size: 0.46875rem !important;
    }
    .permit-plate-box {
        padding: 0.45rem 0.6rem !important;
        border-radius: 8px !important;
    }
    .permit-plate-letters {
        font-size: clamp(1.05rem, 5vw, 1.25rem) !important;
        letter-spacing: 0.04em !important;
    }
    .permit-plate-expiry {
        font-size: 0.625rem !important;
    }
    .permit-specs-grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.35rem !important;
    }
    .permit-spec-card {
        padding: 0.4rem 0.55rem !important;
        min-height: auto !important;
        border-radius: 6px !important;
        gap: 0.2rem !important;
    }
    .permit-spec-card-title {
        font-size: 0.46875rem !important;
    }
    .permit-spec-card-main {
        font-size: 0.71875rem !important;
        font-weight: 800 !important;
    }
    .permit-spec-divider {
        margin-top: 0.15rem !important;
        padding-top: 0.2rem !important;
    }
    .permit-spec-text,
    .permit-spec-year {
        font-size: 0.625rem !important;
    }
    .permit-driver-row {
        margin-top: 0.15rem !important;
        gap: 0.45rem !important;
    }
    .permit-avatar-circle {
        width: 1.65rem !important;
        height: 1.65rem !important;
        font-size: 0.5625rem !important;
    }
    .permit-driver-title {
        font-size: 0.71875rem !important;
        font-weight: 800 !important;
    }
    .permit-driver-subtitle {
        font-size: 0.59375rem !important;
    }
    .permit-badge-footer {
        padding: 0.4rem 0.55rem !important;
        border-radius: 8px !important;
        gap: 0.35rem !important;
    }
    .permit-validity-heading {
        font-size: 0.46875rem !important;
    }
    .permit-validity-daterange {
        font-size: 0.65625rem !important;
        margin-top: 0.1rem !important;
    }
    .permit-validity-badge-pill {
        font-size: 0.53125rem !important;
        padding: 0.1rem 0.35rem !important;
        margin-top: 0.15rem !important;
    }
    .permit-token-seal-inner {
        padding: 0.15rem 0.35rem !important;
    }
    .permit-token-title {
        font-size: 0.46875rem !important;
    }
    .permit-token-id {
        font-size: 0.5625rem !important;
    }
    .permit-magstripe-bar {
        height: 22px !important;
        margin: 0.25rem -0.65rem 0.35rem -0.65rem !important;
    }
    .permit-back-qr-wrapper {
        padding: 0.35rem 0.5rem !important;
        gap: 0.45rem !important;
        border-radius: 8px !important;
    }
    .permit-back-qr-frame {
        width: 44px !important;
        height: 44px !important;
        padding: 2px !important;
    }
    .permit-back-qr-badge {
        font-size: 0.46875rem !important;
    }
    .permit-back-qr-token {
        font-size: 0.65625rem !important;
    }
    .permit-back-qr-hint {
        font-size: 0.5rem !important;
        line-height: 1.2 !important;
    }
    .permit-guidelines-heading {
        font-size: 0.46875rem !important;
    }
    .permit-guidelines-list {
        gap: 0.2rem !important;
        margin-bottom: 0.35rem !important;
    }
    .permit-guidelines-list li {
        font-size: 0.5625rem !important;
        line-height: 1.25 !important;
    }
    .permit-stamp-verified {
        font-size: 0.53125rem !important;
    }
    .permit-stamp-timestamp {
        font-size: 0.46875rem !important;
    }
    .permit-emergency-label {
        font-size: 0.46875rem !important;
    }
    .permit-emergency-number {
        font-size: 0.625rem !important;
    }
    .permit-metric-tiles-grid {
        gap: 0.35rem !important;
        margin-bottom: 0.5rem !important;
    }
    .permit-metric-pod {
        padding: 0.45rem 0.5rem !important;
        min-height: 4.4rem !important;
    }
    .permit-pod-icon-box {
        width: 1.35rem !important;
        height: 1.35rem !important;
        font-size: 0.7rem !important;
    }
    .permit-pod-kicker {
        font-size: 0.46875rem !important;
    }
    .permit-pod-primary {
        font-size: 0.71875rem !important;
    }
    .permit-pod-meta {
        font-size: 0.59375rem !important;
    }
    .permit-gate-scope-card {
        padding: 0.55rem 0.65rem !important;
    }
    .permit-scope-akses-line {
        padding: 0.3rem 0.45rem !important;
    }
    .permit-scope-akses-text {
        font-size: 0.65625rem !important;
    }
    .permit-scope-statement-text {
        font-size: 0.625rem !important;
    }
    .permit-quick-actions-list {
        gap: 0.35rem !important;
    }
    .permit-action-tile {
        padding: 0.5rem 0.65rem !important;
        gap: 0.55rem !important;
        border-radius: 8px !important;
    }
    .permit-action-icon-box {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 5px !important;
    }
    .permit-action-title {
        font-size: 0.75rem !important;
    }
    .permit-action-desc {
        font-size: 0.625rem !important;
        line-height: 1.25 !important;
    }
    :deep(.permit-flip-nav-btn) {
        width: 2.15rem !important;
        height: 2.15rem !important;
        flex: 0 0 2.15rem !important;
    }
    :deep(.permit-flip-main-btn) {
        height: 2.15rem !important;
        font-size: 0.71875rem !important;
    }
    .permit-empty-state-box {
        padding: 1rem 0.5rem !important;
    }
    .permit-empty-actions-row {
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.45rem !important;
    }
    .permit-empty-actions-row > * {
        width: 100% !important;
    }
}

/* ═════════════════════════════════════════════════════════════════════════
   PRINT STYLES: CRISP PHYSICAL ID CARD / PASS OUTPUT
   ═════════════════════════════════════════════════════════════════════════ */
/*
 * Aturan @media print kartu tempel dihapus bersama tombol cetaknya. Versi lama
 * memakai `visibility: hidden` pada seluruh isi <body>, yang menyembunyikan
 * elemen tetapi MENYISAKAN ruang layoutnya - cetakan apa pun yang lebih panjang
 * dari satu kartu akan menghasilkan halaman-halaman kosong. Aturan cetak yang
 * berlaku sekarang ada di PermitBuktiTransaksi.vue dan memakai `display: none`.
 */
</style>
