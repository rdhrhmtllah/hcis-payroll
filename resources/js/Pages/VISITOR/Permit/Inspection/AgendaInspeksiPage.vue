<template>
    <VisitorWorkspace
        v-model="activeTab"
        title="Agenda Inspeksi"
        subtitle="Jadwal inspeksi fisik kendaraan permit karyawan dan kedatangan tamu B2B ber-permit coklat dalam satu agenda terpadu."
        kicker="Visitor · Inspeksi SHE"
        icon="clipboard2-check"
        sentiment="teal"
        :stats="statCards"
        :stat-columns="4"
        :tabs="tabs"
        section-title="Agenda dan pengaturan slot"
        section-description="Pilih tanggal untuk melihat siapa yang datang ke pos inspeksi, atau atur pola slot mingguan."
        section-icon="calendar-week"
        @stat-click="tanganiKlikStat"
    >
        <!-- ═══════════════════════════════════════════════════════════════════
             1. PANEL AGENDA HARIAN
             ═══════════════════════════════════════════════════════════════════ -->
        <template #panel-agenda>
            <div class="agenda">
                <!-- 1A. Unified Date Navigation & Toolbar -->
                <VerifikasiKartuScanner v-model="cekKartuTerbuka" endpoint="/laporanvisitor-verifikasi-kartu" />

                <HcisCard variant="glass" size="sm" class="agenda__toolbar-card">
                    <div class="agenda__toolbar-inner">
                        <!-- Left / Top: Stepper & Presets -->
                        <div class="agenda__nav-group">
                            <div class="agenda__date-stepper">
                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="chevron-left"
                                    title="Hari Sebelumnya"
                                    aria-label="Hari Sebelumnya"
                                    @click="geserHari(-1)"
                                />
                                <div class="agenda__date-picker-wrap">
                                    <HcisDatePicker
                                        v-model="tanggal"
                                        size="sm"
                                        placeholder="Pilih tanggal"
                                        class="agenda__hcis-date-picker"
                                        @update:model-value="muatAgenda"
                                    />
                                </div>
                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    trailing-icon="chevron-right"
                                    title="Hari Berikutnya"
                                    aria-label="Hari Berikutnya"
                                    @click="geserHari(1)"
                                />
                            </div>

                            <div class="agenda__presets-group">
                                <HcisButton
                                    :variant="tanggal === hariIni ? 'primary' : 'outline'"
                                    size="sm"
                                    leading-icon="calendar-check"
                                    label="Hari Ini"
                                    @click="keHariIni"
                                />
                                <HcisButton
                                    :variant="tanggal === tanggalBesok ? 'primary' : 'outline'"
                                    size="sm"
                                    leading-icon="calendar-plus"
                                    label="Besok"
                                    @click="keHariBesok"
                                />
                            </div>
                        </div>

                        <!-- Right / Bottom: Date Callout & Quick Action Tools -->
                        <div class="agenda__tools-group">
                            <div class="agenda__date-callout">
                                <HcisIcon name="calendar-date-fill" size="xs" class="text-teal-600 me-1" />
                                <span class="agenda__date-callout-text font-tabular">{{ labelTanggal(tanggal) }}</span>
                                <HcisBadge
                                    v-if="tanggal === hariIni"
                                    label="Hari Ini"
                                    variant="teal"
                                    size="xs"
                                    :pulse="true"
                                    appearance="subtle"
                                    class="ms-1"
                                />
                                <HcisBadge
                                    v-else-if="tanggal === tanggalBesok"
                                    label="Besok"
                                    variant="info"
                                    size="xs"
                                    appearance="subtle"
                                    class="ms-1"
                                />
                            </div>

                            <div class="agenda__tools-actions">
                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="upc-scan"
                                    label="Cek Kartu"
                                    title="Pindai QR pada kartu permit untuk memeriksa keasliannya"
                                    @click="cekKartuTerbuka = true"
                                />

                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="arrow-clockwise"
                                    :loading="agendaLoading"
                                    title="Muat Ulang Data Agenda"
                                    @click="muatAgenda"
                                />
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- 1B. Alert Banner (If error occurs) -->
                <HcisAlert v-if="agendaError" variant="danger" :title="agendaError" closable @close="agendaError = ''">
                    <template #actions>
                        <HcisButton
                            size="sm"
                            variant="secondary"
                            leading-icon="arrow-clockwise"
                            label="Muat Ulang"
                            @click="muatAgenda"
                        />
                    </template>
                </HcisAlert>

                <!-- 1C. Overdue Alert Trigger Banner ("Lewat Jadwal, Belum Dicatat") -->
                <HcisCard
                    v-if="belumDicatat.length"
                    variant="glass"
                    sentiment="warning"
                    interactive
                    size="sm"
                    class="agenda__terlambat-banner"
                    role="button"
                    tabindex="0"
                    title="Klik untuk membuka daftar antrean yang lewat jadwal"
                    @click="dialogBelumDicatat = true"
                    @keydown.enter="dialogBelumDicatat = true"
                >
                    <div class="agenda__terlambat-banner-inner">
                        <div class="agenda__terlambat-banner-left">
                            <div class="agenda__terlambat-banner-icon">
                                <HcisIcon name="exclamation-triangle-fill" size="xs" />
                            </div>
                            <div class="agenda__terlambat-banner-text">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <strong class="agenda__terlambat-banner-title">Lewat Jadwal &amp; Belum Dicatat:</strong>
                                    <HcisBadge
                                        :label="`${belumDicatat.length} Antrean Tertunda`"
                                        variant="danger"
                                        size="xs"
                                        :pulse="true"
                                        appearance="subtle"
                                    />
                                </div>
                                <span class="agenda__terlambat-banner-desc">
                                    Inspeksi atau kedatangan tamu B2B yang jadwalnya telah lewat tetapi hasilnya belum dicatat. Tentukan status kehadirannya.
                                </span>
                            </div>
                        </div>
                        <HcisButton
                            variant="warning"
                            size="sm"
                            leading-icon="list-check"
                            label="Lihat Antrean"
                            class="agenda__terlambat-banner-btn flex-shrink-0"
                            @click.stop="dialogBelumDicatat = true"
                        />
                    </div>
                </HcisCard>

                <!-- 1D. Capacity & Pos Utilization Overview -->
                <div v-if="kapasitasPos.length && !agendaLoading" class="agenda__kapasitas-section">
                    <div class="agenda__kapasitas-header-bar">
                        <div class="d-flex align-items-center gap-1.5 text-xs text-slate-700 fw-bold">
                            <HcisIcon name="speedometer2" size="2xs" class="text-teal-600" />
                            <span>Okupansi &amp; Kapasitas Pos</span>
                            <span class="agenda__kapasitas-date font-tabular">({{ labelTanggal(tanggal) }})</span>
                        </div>
                        <HcisButton
                            v-if="filterPos !== 'ALL'"
                            variant="ghost"
                            size="xs"
                            trailing-icon="x-circle-fill"
                            label="Tampilkan Semua Pos"
                            sentiment="teal"
                            title="Tampilkan semua pos"
                            @click="filterPos = 'ALL'"
                        />
                    </div>

                    <div class="agenda__kapasitas-grid">
                        <HcisCard
                            v-for="pos in kapasitasPos"
                            :key="pos.id"
                            variant="glass"
                            size="sm"
                            class="agenda__kapasitas-card cursor-pointer"
                            :class="{ 'is-selected': filterPos === pos.id }"
                            role="button"
                            :aria-pressed="filterPos === pos.id"
                            @click="toggleFilterPos(pos.id)"
                        >
                            <div class="agenda__kapasitas-header">
                                <div class="agenda__kapasitas-title">
                                    <div class="agenda__kapasitas-icon-box">
                                        <HcisIcon name="geo-alt-fill" size="xs" />
                                    </div>
                                    <div class="agenda__kapasitas-name-wrap">
                                        <strong class="agenda__kapasitas-name text-slate-900">{{ pos.pos }}</strong>
                                        <div v-if="pos.lokasi" class="agenda__kapasitas-lokasi text-2xs text-muted text-truncate" :title="pos.lokasi">
                                            {{ pos.lokasi }}
                                        </div>
                                    </div>
                                </div>
                                <HcisBadge
                                    :label="pos.slot ? `${pos.slot} Slot` : (pos.tamu ? 'Layanan Tamu' : '0 Slot')"
                                    size="xs"
                                    :variant="filterPos === pos.id ? 'primary' : (pos.slot ? 'neutral' : (pos.tamu ? 'warning' : 'neutral'))"
                                    appearance="subtle"
                                />
                            </div>
                            <div v-if="pos.slot" class="agenda__kapasitas-numbers font-tabular mt-2">
                                <span class="agenda__kapasitas-filled">{{ pos.terisi }}</span>
                                <span class="agenda__kapasitas-sep">/</span>
                                <span class="agenda__kapasitas-total">{{ pos.kuota }} Unit</span>
                                <span class="agenda__kapasitas-percent ms-auto font-tabular" :class="`is-${getPosSentiment(pos)}`">
                                    {{ Math.round((pos.terisi / Math.max(pos.kuota, 1)) * 100) }}% Terisi
                                </span>
                            </div>
                            <div v-if="pos.slot" class="mt-1.5">
                                <HcisProgress
                                    :percentage="Math.min(Math.round((pos.terisi / Math.max(pos.kuota, 1)) * 100), 100)"
                                    :sentiment="getPosSentiment(pos)"
                                    size="sm"
                                    :animated="true"
                                />
                            </div>
                            <div v-else class="text-2xs text-muted mt-2">
                                {{ pos.tamu ? 'Tidak ada slot booking reguler hari ini.' : 'Tidak ada jadwal inspeksi hari ini.' }}
                            </div>
                            <!-- Layanan tamu B2B: diperiksa langsung di gerbang saat tiba, tanpa sistem kuota slot booking. -->
                            <div v-if="pos.tamu" class="agenda__kapasitas-tamu mt-2">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>+{{ pos.tamu }} Tamu B2B Terjadwal</span>
                                </div>
                                <span class="agenda__kapasitas-tamu-note">Inspeksi Langsung di Gerbang</span>
                            </div>
                        </HcisCard>
                    </div>
                </div>

                <!-- 1E. Loading Skeletons -->
                <div v-if="agendaLoading" class="agenda__daftar">
                    <HcisCard
                        v-for="n in 3"
                        :key="n"
                        variant="glass"
                        size="sm"
                        class="agenda__skeleton-card"
                    >
                        <div class="d-flex align-items-center gap-3 w-100">
                            <HcisSkeleton width="5.5rem" height="3.25rem" rounded="md" />
                            <div class="agenda__skeleton-body flex-grow-1">
                                <HcisSkeleton width="180px" height="1.25rem" rounded="sm" />
                                <HcisSkeleton width="260px" height="0.85rem" rounded="sm" />
                            </div>
                            <HcisSkeleton width="100px" height="1.5rem" rounded="full" class="ms-auto" />
                        </div>
                    </HcisCard>
                </div>

                <!-- 1F. Ready Content -->
                <template v-else-if="!agendaError">
                    <!-- Filter Bar dengan HcisFilterBar -->
                    <HcisFilterBar
                        v-if="agenda.length > 0"
                        :search="searchQuery"
                        search-placeholder="Cari pemohon, plat nomor, pos, transaksi..."
                        :filters="filtersAgenda"
                        :filter-values="{ status: filterStatus === 'ALL' ? '' : filterStatus, pos: filterPos === 'ALL' ? '' : filterPos }"
                        :quick-chips="quickChipsAgenda"
                        :active-chip-value="filterCategory"
                        :results-count="filteredAgenda.length"
                        :collapsible="true"
                        :show-view-toggle="true"
                        :view-mode="viewModeStandard"
                        show-active-tags
                        sentiment="teal"
                        variant="glass"
                        class="agenda__filter-bar-component mb-3"
                        @update:search="searchQuery = $event"
                        @update:view-mode="onViewModeUpdate($event)"
                        @filter-change="onFilterChange"
                        @chip-click="onChipClick"
                        @reset="resetFilter"
                    />

                    <!-- Empty State (No agenda) -->
                    <div v-if="agenda.length === 0" class="agenda__empty-wrap">
                        <HcisEmptyState
                            title="Tidak Ada Agenda Inspeksi"
                            :description="`Tidak ada jadwal pemeriksaan kendaraan permit atau kedatangan tamu permit coklat pada hari ${labelTanggal(tanggal)}.`"
                            icon="calendar-x"
                            sentiment="neutral"
                        >
                            <template #actions>
                                <HcisButton
                                    variant="outline"
                                    size="sm"
                                    leading-icon="sliders"
                                    label="Atur Pola Slot Mingguan"
                                    @click="activeTab = 'pengaturan'"
                                />
                            </template>
                        </HcisEmptyState>
                    </div>

                    <!-- Empty State (Search filter empty) -->
                    <div v-else-if="filteredAgenda.length === 0" class="agenda__empty-wrap">
                        <HcisEmptyState
                            title="Pencarian Tidak Ditemukan"
                            description="Tidak ada hasil yang sesuai dengan kriteria filter atau pencarian Anda."
                            icon="search"
                            sentiment="neutral"
                        >
                            <template #actions>
                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    label="Reset Filter"
                                    leading-icon="arrow-counterclockwise"
                                    @click="resetFilter"
                                />
                            </template>
                        </HcisEmptyState>
                    </div>

                    <!-- VIEW 1: AGENDA CARDS LIST (Mobile / Grid Mode) -->
                    <ul v-else-if="isMobileViewport || viewMode === 'grid' || viewMode === 'cards'" class="agenda__daftar" :class="{ 'agenda__daftar--grid': !isMobileViewport && (viewMode === 'grid' || viewMode === 'cards') }">
                        <li
                            v-for="item in filteredAgenda"
                            :key="item.Jenis + item.No_Transaksi + item.Mulai"
                            class="agenda__card-wrap"
                        >
                            <HcisCard
                                variant="glass"
                                size="sm"
                                class="agenda__card"
                            >
                                <!-- 1. Card Top Bar: Category + Transaksi (Left) & Status Badge (Right) -->
                                <div class="agenda__card-top-bar">
                                    <div class="agenda__card-meta-wrap">
                                        <HcisBadge
                                            :variant="warnaKategori(item)"
                                            :label="labelKategori(item)"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                        <span class="agenda__trans-tag font-tabular">
                                            {{ item.No_Transaksi }}
                                        </span>
                                    </div>
                                    <div class="agenda__card-status-wrap">
                                        <HcisBadge
                                            :variant="statusItem(item).variant"
                                            :label="statusItem(item).label"
                                            :pulse="statusItem(item).pulse"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>
                                </div>

                                <!-- 2. Schedule Strip: Distinct Highlighted Time Box -->
                                <div class="agenda__card-schedule-strip">
                                    <div class="agenda__card-schedule-left">
                                        <!-- Tamu tidak memakai slot: SHE berjaga menurut estimasi
                                             masuk & keluar yang diajukan untuk kunjungannya. -->
                                        <div v-if="item.Jenis === 'COKLAT'" class="agenda__waktu-tamu" data-testid="waktu-tamu-kartu">
                                            <HcisIcon name="calendar-event" size="xs" class="agenda__schedule-icon text-amber-600" />
                                            <div class="agenda__waktu-tamu-baris">
                                                <span class="agenda__waktu-tamu-label">Estimasi masuk</span>
                                                <span class="agenda__card-time-start font-tabular">{{ jam(item.Mulai) }}</span>
                                                <span v-if="!hariIniTampil(item.Mulai)" class="agenda__card-time-end font-tabular">{{ labelTanggalSingkat(item.Mulai) }}</span>
                                            </div>
                                            <span class="agenda__waktu-tamu-arrow">&rarr;</span>
                                            <div class="agenda__waktu-tamu-baris">
                                                <span class="agenda__waktu-tamu-label">Estimasi keluar</span>
                                                <span class="agenda__waktu-tamu-keluar font-tabular">{{ waktuTamu(item.Selesai) }} WIB</span>
                                            </div>
                                        </div>
                                        <div v-else class="agenda__schedule-permit-box">
                                            <HcisIcon name="clock-fill" size="xs" class="agenda__schedule-icon text-teal-600" />
                                            <span class="agenda__card-time-start font-tabular">{{ jam(item.Mulai) }}</span>
                                            <span class="agenda__card-time-sep">&ndash;</span>
                                            <span class="agenda__card-time-end font-tabular">{{ jam(item.Selesai) }} WIB</span>
                                            <span v-if="item.Jenis === 'PERMIT'" class="agenda__card-duration-badge font-tabular">
                                                30 Mnt
                                            </span>
                                            <span v-if="item.Mulai && item.Mulai.slice(0, 10) !== tanggal" class="agenda__schedule-diff-badge font-tabular">
                                                {{ labelTanggalSingkat(item.Mulai) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="item.Shift" class="agenda__card-shift-badge">
                                        <HcisIcon name="clock-history" size="2xs" class="text-indigo-600" />
                                        <span>{{ item.Shift }}</span>
                                    </div>
                                </div>

                                <!-- 3. Identity Section: Applicant / Guest Name & Instansi / Unit -->
                                <div class="agenda__card-person-block">
                                    <strong class="agenda__card-person-name">{{ item.Nama }}</strong>
                                    <div v-if="item.Instansi || item.B2B_Unit" class="agenda__card-person-meta">
                                        <span v-if="item.Instansi" class="agenda__card-instansi">
                                            <HcisIcon name="building" size="2xs" class="text-slate-400 me-1" />
                                            {{ item.Instansi }}
                                        </span>
                                        <span v-if="item.B2B_Unit" class="agenda__card-b2b-unit">
                                            Unit: <strong>{{ item.B2B_Unit }}</strong>
                                        </span>
                                    </div>
                                </div>

                                <!-- 4. Vehicle & Pos Inspection Pod -->
                                <div class="agenda__card-details-pod">
                                    <div class="agenda__card-vehicle-col">
                                        <div class="agenda__plate-badge">
                                            <HcisIcon :name="getVehicleIcon(item.Kendaraan)" size="xs" class="agenda__plate-icon" />
                                            <span class="agenda__plate-text font-tabular">{{ item.No_Plat || '-' }}</span>
                                        </div>
                                        <span class="agenda__vehicle-name text-truncate" :title="item.Kendaraan">
                                            {{ item.Kendaraan }}
                                        </span>
                                    </div>
                                    <div v-if="item.Pos_Inspeksi" class="agenda__card-pos-col" :title="item.Lokasi_Pos || ''">
                                        <HcisIcon name="geo-alt-fill" size="2xs" class="text-teal-600" />
                                        <span class="agenda__pos-text">{{ item.Pos_Inspeksi }}</span>
                                    </div>
                                </div>

                                <!-- 5. Action Buttons -->
                                <div class="agenda__card-actions-col">
                                    <!--
                                        Baris tamu B2B SELALU membawa tombol ini.
                                        Inspeksinya dikerjakan di halaman review —
                                        di sanalah kendaraannya diperiksa dan
                                        keputusannya diambil — dan keputusan itu
                                        pula yang menutup inspeksinya. Agenda
                                        karena itu tidak punya tombol "Selesai"
                                        sendiri: tak ada jalan menutup pekerjaan
                                        SHE di atas pengajuan yang menggantung.
                                    -->
                                    <!-- Sudah Diinspeksi: Lihat Detail -->
                                    <HcisButton
                                        v-if="item.Tautan_Review && item.Status_Inspeksi === 'SELESAI'"
                                        variant="outline"
                                        size="sm"
                                        leading-icon="eye"
                                        label="Lihat Detail"
                                        class="agenda__action-btn"
                                        @click="bukaReview(item)"
                                    />
                                    <!-- Belum Diinspeksi (Aktif): Review / Mulai Inspeksi -->
                                    <HcisButton
                                        v-else-if="item.Tautan_Review && item.Status_Inspeksi !== 'TIDAK_HADIR'"
                                        variant="primary"
                                        size="sm"
                                        leading-icon="clipboard2-check"
                                        :label="item.Jenis === 'COKLAT' ? 'Review & Inspeksi' : 'Mulai Inspeksi'"
                                        class="agenda__action-btn agenda__action-btn--primary"
                                        @click="bukaReview(item)"
                                    />
                                    <!-- Tidak Hadir: Lihat Detail -->
                                    <HcisButton
                                        v-else-if="item.Tautan_Review"
                                        variant="outline"
                                        size="sm"
                                        leading-icon="eye"
                                        label="Lihat Detail"
                                        class="agenda__action-btn"
                                        @click="bukaReview(item)"
                                    />
                                    <HcisButton
                                        v-if="item.Boleh_Pindah"
                                        variant="outline"
                                        size="sm"
                                        leading-icon="calendar2-week"
                                        label="Pindahkan"
                                        class="agenda__action-btn"
                                        @click="bukaPindah(item)"
                                    />
                                    <HubungiPemohonTombol
                                        v-if="item.Status_Inspeksi !== 'SELESAI'"
                                        :kontak="item.Kontak"
                                        size="sm"
                                        class="agenda__action-btn"
                                        :judul="item.Jenis === 'COKLAT' ? 'Hubungi tamu' : 'Hubungi pemohon'"
                                    />
                                    <HcisButton
                                        v-if="item.Boleh_Tidak_Hadir && item.Status_Inspeksi !== 'SELESAI'"
                                        variant="danger-quiet"
                                        size="sm"
                                        leading-icon="person-x"
                                        label="Tidak Hadir"
                                        class="agenda__action-btn"
                                        @click="konfirmasiTidakHadir(item)"
                                    />

                                    <!--
                                        Tamu B2B tidak memesan slot: SHE mendatangi
                                        pos gerbang pada jam kunjungannya, lalu
                                        mencatat hasilnya di sini. Karena itu tidak
                                        ada "Pindahkan" — jadwalnya milik kunjungan
                                        tamunya, bukan milik permitnya.
                                    -->
                                    <HcisButton
                                        v-if="item.Boleh_Tidak_Hadir_Tamu && item.Status_Inspeksi !== 'SELESAI' && item.Status_Inspeksi !== 'TIDAK_HADIR'"
                                        variant="danger-quiet"
                                        size="sm"
                                        leading-icon="person-x"
                                        label="Tamu Tidak Hadir"
                                        :disabled="memprosesKedatangan === item.No_Transaksi"
                                        class="agenda__action-btn"
                                        @click="catatKedatangan(item, 'TIDAK_HADIR')"
                                    />
                                </div>
                            </HcisCard>
                        </li>
                    </ul>

                    <!-- VIEW 2: AGENDA STRUCTURED TABLE VIEW (Desktop Default) -->
                    <HcisCard v-else variant="glass" size="sm" class="agenda__table-card">
                        <div class="agenda__table-wrapper">
                            <table class="agenda__table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">Waktu & Sesi</th>
                                        <th style="width: 150px;">Kategori</th>
                                        <th>Pemohon / Tamu</th>
                                        <th style="width: 220px;">Kendaraan & Plat</th>
                                        <th style="width: 180px;">Pos & Shift</th>
                                        <th style="width: 160px; text-align: center;">Status</th>
                                        <th style="width: 180px; text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in filteredAgenda"
                                        :key="`tbl-${item.Jenis}-${item.No_Transaksi}-${item.Mulai}`"
                                        class="agenda__table-row"
                                    >
                                        <td v-if="item.Jenis === 'COKLAT'" data-testid="waktu-tamu-tabel">
                                            <div class="agenda__waktu-tamu-label">Estimasi masuk</div>
                                            <div class="font-tabular fw-bold text-slate-800">{{ waktuTamu(item.Mulai) }} <span class="text-2xs text-muted">WIB</span></div>
                                            <div class="agenda__waktu-tamu-label mt-1">Estimasi keluar</div>
                                            <div class="font-tabular text-2xs text-slate-700 fw-semibold">{{ waktuTamu(item.Selesai) }} WIB</div>
                                        </td>
                                        <td v-else>
                                            <div class="font-tabular fw-bold text-slate-800">{{ jam(item.Mulai) }} <span class="text-2xs text-muted">WIB</span></div>
                                            <div v-if="item.Mulai && item.Mulai.slice(0, 10) !== tanggal" class="font-tabular text-2xs text-amber-700 fw-semibold mt-0.5">
                                                {{ labelTanggalSingkat(item.Mulai) }}
                                            </div>
                                            <div v-else class="font-tabular text-2xs text-muted">s.d. {{ jam(item.Selesai) }} WIB</div>
                                        </td>
                                        <td>
                                            <HcisBadge
                                                :variant="warnaKategori(item)"
                                                :label="labelKategori(item)"
                                                size="xs"
                                                appearance="subtle"
                                            />
                                        </td>
                                        <td>
                                            <div class="fw-bold text-slate-900">{{ item.Nama }}</div>
                                            <div v-if="item.Instansi || item.B2B_Unit" class="text-xs text-muted">
                                                <span v-if="item.Instansi">{{ item.Instansi }}</span>
                                                <span v-if="item.B2B_Unit"> &middot; Unit: {{ item.B2B_Unit }}</span>
                                            </div>
                                            <div class="text-2xs font-tabular text-slate-400">{{ item.No_Transaksi }}</div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5">
                                                <div class="agenda__plate-badge">
                                                    <HcisIcon :name="getVehicleIcon(item.Kendaraan)" size="2xs" class="agenda__plate-icon" />
                                                    <span class="agenda__plate-text font-tabular">{{ item.No_Plat || '-' }}</span>
                                                </div>
                                                <span class="text-xs text-slate-700 text-truncate" style="max-width: 110px;">{{ item.Kendaraan }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-xs fw-semibold text-slate-800">{{ item.Pos_Inspeksi || '-' }}</div>
                                            <div v-if="item.Shift" class="text-2xs text-indigo-600">{{ item.Shift }}</div>
                                        </td>
                                        <td class="text-center">
                                            <HcisBadge
                                                :variant="statusItem(item).variant"
                                                :label="statusItem(item).label"
                                                :pulse="statusItem(item).pulse"
                                                size="xs"
                                                appearance="subtle"
                                            />
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <!-- Sudah Diinspeksi: Detail -->
                                                <HcisButton
                                                    v-if="item.Tautan_Review && item.Status_Inspeksi === 'SELESAI'"
                                                    variant="outline"
                                                    size="xs"
                                                    leading-icon="eye"
                                                    label="Detail"
                                                    @click="bukaReview(item)"
                                                />
                                                <!-- Belum Diinspeksi (Aktif): Review / Inspeksi -->
                                                <HcisButton
                                                    v-else-if="item.Tautan_Review && item.Status_Inspeksi !== 'TIDAK_HADIR'"
                                                    variant="primary"
                                                    size="xs"
                                                    leading-icon="clipboard2-check"
                                                    :label="item.Jenis === 'COKLAT' ? 'Review' : 'Inspeksi'"
                                                    @click="bukaReview(item)"
                                                />
                                                <!-- Tidak Hadir: Detail -->
                                                <HcisButton
                                                    v-else-if="item.Tautan_Review"
                                                    variant="outline"
                                                    size="xs"
                                                    leading-icon="eye"
                                                    label="Detail"
                                                    @click="bukaReview(item)"
                                                />
                                                <HcisButton
                                                    v-if="item.Boleh_Pindah"
                                                    variant="outline"
                                                    size="xs"
                                                    leading-icon="calendar2-week"
                                                    label="Pindah"
                                                    @click="bukaPindah(item)"
                                                />
                                                <HubungiPemohonTombol
                                                    v-if="item.Status_Inspeksi !== 'SELESAI'"
                                                    :kontak="item.Kontak"
                                                    size="xs"
                                                    label="Hubungi"
                                                    :judul="item.Jenis === 'COKLAT' ? 'Hubungi tamu' : 'Hubungi pemohon'"
                                                />
                                                <HcisButton
                                                    v-if="item.Boleh_Tidak_Hadir && item.Status_Inspeksi !== 'SELESAI'"
                                                    variant="danger-quiet"
                                                    size="xs"
                                                    leading-icon="person-x"
                                                    label="Absen"
                                                    @click="konfirmasiTidakHadir(item)"
                                                />
                                                <HcisButton
                                                    v-if="item.Boleh_Tidak_Hadir_Tamu && item.Status_Inspeksi !== 'SELESAI' && item.Status_Inspeksi !== 'TIDAK_HADIR'"
                                                    variant="danger-quiet"
                                                    size="xs"
                                                    leading-icon="person-x"
                                                    label="Tidak Hadir"
                                                    :disabled="memprosesKedatangan === item.No_Transaksi"
                                                    @click="catatKedatangan(item, 'TIDAK_HADIR')"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </HcisCard>
                </template>
            </div>

            <!-- ===================================================
                 DIALOG WAJIB BERADA DI DALAM SLOT PANEL.

                 VisitorWorkspace merender <slot /> bawaannya hanya sebagai
                 CADANGAN ketika slot panel-<tab> tidak ada. Halaman ini punya
                 dua panel, jadi apa pun yang diletakkan di luar keduanya tidak
                 pernah dirender sama sekali - dialognya tidak "gagal terbuka",
                 ia tidak ada. Gejalanya tombol yang diklik tanpa reaksi apa pun,
                 tanpa galat di console, dan state-nya benar-benar berubah.
                 =================================================== -->
        <!-- ═══════════════════════════════════════════════════════════════════
             3. MODAL DIALOG CATAT TIDAK HADIR
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-model="dialogTidakHadir"
            title="Konfirmasi Pencatatan Tidak Hadir"
            icon="exclamation-triangle-fill"
            sentiment="danger"
            size="md"
        >
            <div v-if="itemTidakHadir" class="agenda__dialog-box">
                <div class="agenda__dialog-alert">
                    <HcisIcon name="person-x-fill" size="md" class="text-danger flex-shrink-0" />
                    <div>
                        <div class="agenda__dialog-alert-title">Catat Pemohon Tidak Hadir Sesuai Jadwal</div>
                        <div class="agenda__dialog-alert-desc">
                            Tindakan ini akan mengirimkan notifikasi kepada pemohon untuk memilih slot inspeksi baru.
                        </div>
                    </div>
                </div>

                <!-- Vehicle Pod Details -->
                <div class="agenda__dialog-vehicle-pod">
                    <div class="agenda__plate-badge">
                        <HcisIcon :name="getVehicleIcon(itemTidakHadir.Kendaraan)" size="xs" class="agenda__plate-icon" />
                        <span class="agenda__plate-text font-tabular">{{ itemTidakHadir.No_Plat || '-' }}</span>
                    </div>
                    <div class="agenda__dialog-vehicle-meta">
                        <strong>{{ itemTidakHadir.Nama }}</strong>
                        <span class="text-xs text-muted">{{ itemTidakHadir.Kendaraan }} · {{ itemTidakHadir.Pos_Inspeksi }}</span>
                        <span class="text-xs font-tabular text-slate-700">Waktu: {{ jam(itemTidakHadir.Mulai) }} WIB</span>
                    </div>
                </div>

                <div class="agenda__dialog-warning-callout">
                    <HcisIcon name="info-circle-fill" size="xs" class="text-amber-600 flex-shrink-0" />
                    <span>
                        Batas toleransi ketidakhadiran adalah <strong>maksimal 2 kali</strong>. Ketidakhadiran kedua akan secara otomatis <strong>menggugurkan permohonan permit</strong>.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        :disabled="menyimpanTidakHadir"
                        @click="dialogTidakHadir = false"
                    />
                    <HcisButton
                        variant="danger"
                        size="sm"
                        leading-icon="person-x"
                        label="Ya, Catat Tidak Hadir"
                        :loading="menyimpanTidakHadir"
                        @click="simpanTidakHadir"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Pindah jadwal oleh petugas: pemilih slot yang sama dengan milik pemohon,
             alasan wajib karena pemohon menerimanya di notifikasi. -->
        <HcisDialog
            v-model="dialogPindah"
            title="Pindahkan Jadwal Inspeksi"
            description="Pilih slot baru. Pemohon diberi tahu jadwal baru beserta alasannya."
            icon="calendar2-week"
            size="lg"
            footer-align="between"
            sticky-footer
            @update:model-value="(buka) => { if (!buka && menyimpanPindah) dialogPindah = true; }"
        >
            <div v-if="itemPindah" class="agenda__pindah">
                <div class="agenda__dialog-vehicle-pod">
                    <div class="agenda__plate-badge">
                        <HcisIcon :name="getVehicleIcon(itemPindah.Kendaraan)" size="xs" class="agenda__plate-icon" />
                        <span class="agenda__plate-text font-tabular">{{ itemPindah.No_Plat || '-' }}</span>
                    </div>
                    <div class="agenda__dialog-vehicle-meta">
                        <strong>{{ itemPindah.Nama }}</strong>
                        <span class="text-xs text-muted">{{ itemPindah.Kendaraan }} · {{ itemPindah.Kategori }}</span>
                        <span class="text-xs font-tabular text-slate-700">
                            Jadwal sekarang: {{ itemPindah.Mulai }} · {{ itemPindah.Pos_Inspeksi }}
                        </span>
                    </div>
                </div>

                <SlotInspeksiPicker
                    v-model="slotPindah"
                    :slots="slotTersedia"
                    :loading="memuatSlot"
                    :disabled="menyimpanPindah"
                />

                <HcisField
                    id="agenda-pindah-alasan"
                    v-model="alasanPindah"
                    label="Alasan pemindahan"
                    placeholder="Contoh: pos penuh, petugas SHE berhalangan, permintaan pemohon..."
                    :maxlength="250"
                    required
                    :disabled="menyimpanPindah"
                />
            </div>

            <template #footer>
                <div class="agenda__pindah-footer">
                    <span class="agenda__pindah-ringkas">
                        <template v-if="slotPindah">
                            Baru: <strong>{{ slotPindah.mulai }}</strong> · {{ slotPindah.pos }}
                        </template>
                        <template v-else>Belum memilih slot</template>
                    </span>
                    <div class="agenda__pindah-aksi">
                        <HcisButton
                            variant="outline"
                            label="Batal"
                            :disabled="menyimpanPindah"
                            @click="dialogPindah = false"
                        />
                        <HcisButton
                            leading-icon="check2"
                            :label="menyimpanPindah ? 'Memindahkan...' : 'Pindahkan Jadwal'"
                            :loading="menyimpanPindah"
                            :disabled="!slotPindah || !alasanPindah.trim() || menyimpanPindah"
                            @click="simpanPindah"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL DIALOG LEWAT JADWAL & BELUM DICATAT
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-model="dialogBelumDicatat"
            title="Lewat Jadwal & Belum Dicatat"
            :description="`Terdapat ${belumDicatat.length} antrean inspeksi dan kedatangan tamu B2B yang jadwalnya telah lewat tetapi hasilnya belum dicatat.`"
            icon="exclamation-triangle-fill"
            sentiment="warning"
            size="lg"
        >
            <div class="agenda__terlambat-modal-body">
                <ul v-if="belumDicatat.length" class="agenda__terlambat-list">
                    <li
                        v-for="item in belumDicatat"
                        :key="`${item.Jenis}-${item.No_Transaksi}`"
                        class="agenda__terlambat-item-wrap"
                    >
                        <HcisCard
                            variant="glass"
                            sentiment="warning"
                            size="sm"
                            class="agenda__terlambat-item"
                        >
                            <!-- 1. Card Top Bar: Category + Transaksi & Overdue Badge -->
                            <div class="agenda__card-top-bar">
                                <div class="agenda__card-meta-wrap">
                                    <HcisBadge
                                        :variant="warnaKategori(item)"
                                        :label="labelKategori(item)"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                    <span class="agenda__trans-tag font-tabular">
                                        {{ item.No_Transaksi }}
                                    </span>
                                </div>
                                <div class="agenda__card-status-wrap">
                                    <HcisBadge
                                        variant="danger"
                                        label="Belum Dicatat"
                                        :pulse="true"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                </div>
                            </div>

                            <!-- 2. Schedule Strip -->
                            <div class="agenda__card-schedule-strip agenda__card-schedule-strip--warning">
                                <div class="agenda__card-schedule-left">
                                    <!-- Tamu: estimasi masuk & keluar dari pengajuan kunjungannya. -->
                                    <div v-if="item.Jenis === 'COKLAT'" class="agenda__waktu-tamu" data-testid="waktu-tamu-terlambat">
                                        <HcisIcon name="calendar-event" size="xs" class="agenda__schedule-icon text-amber-600" />
                                        <div class="agenda__waktu-tamu-baris">
                                            <span class="agenda__waktu-tamu-label">Estimasi masuk</span>
                                            <span class="agenda__card-time-start font-tabular">{{ jam(item.Mulai) }} <span class="agenda__terlambat-tz">WIB</span></span>
                                            <span class="agenda__terlambat-time-date font-tabular">{{ labelTanggalSingkat(item.Mulai) }}</span>
                                        </div>
                                        <span class="agenda__waktu-tamu-arrow">&rarr;</span>
                                        <div class="agenda__waktu-tamu-baris">
                                            <span class="agenda__waktu-tamu-label">Estimasi keluar</span>
                                            <span class="agenda__waktu-tamu-keluar font-tabular">{{ waktuTamu(item.Selesai) }} WIB</span>
                                        </div>
                                    </div>
                                    <div v-else class="agenda__schedule-permit-box">
                                        <HcisIcon name="clock-fill" size="xs" class="agenda__schedule-icon text-amber-600" />
                                        <span class="agenda__card-time-start font-tabular">{{ jam(item.Mulai) }}</span>
                                        <span class="agenda__card-time-sep">&ndash;</span>
                                        <span class="agenda__card-time-end font-tabular">{{ jam(item.Selesai) }} WIB</span>
                                        <span class="agenda__terlambat-time-date font-tabular">{{ labelTanggalSingkat(item.Mulai) }}</span>
                                    </div>
                                </div>
                                <HcisBadge
                                    :label="item.Terlambat_Hari > 0 ? `+${item.Terlambat_Hari} Hari` : 'Hari Ini'"
                                    variant="danger"
                                    size="2xs"
                                    appearance="subtle"
                                />
                            </div>

                            <!-- 3. Identity Block -->
                            <div class="agenda__card-person-block">
                                <strong class="agenda__card-person-name">{{ item.Nama }}</strong>
                                <div v-if="item.Instansi || item.B2B_Unit" class="agenda__card-person-meta">
                                    <span v-if="item.Instansi" class="agenda__card-instansi">
                                        <HcisIcon name="building" size="2xs" class="text-slate-400 me-1" />
                                        {{ item.Instansi }}
                                    </span>
                                    <span v-if="item.B2B_Unit" class="agenda__card-b2b-unit">
                                        Unit: <strong>{{ item.B2B_Unit }}</strong>
                                    </span>
                                </div>
                            </div>

                            <!-- 4. Vehicle & Pos Pod -->
                            <div class="agenda__card-details-pod">
                                <div class="agenda__card-vehicle-col">
                                    <div class="agenda__plate-badge">
                                        <HcisIcon :name="getVehicleIcon(item.Kendaraan)" size="xs" class="agenda__plate-icon" />
                                        <span class="agenda__plate-text font-tabular">{{ item.No_Plat || '-' }}</span>
                                    </div>
                                    <span class="agenda__vehicle-name text-truncate" :title="item.Kendaraan">
                                        {{ item.Kendaraan }}
                                    </span>
                                </div>
                                <div v-if="item.Pos_Inspeksi" class="agenda__card-pos-col" :title="item.Lokasi_Pos || ''">
                                    <HcisIcon name="geo-alt-fill" size="2xs" class="text-teal-600" />
                                    <span class="agenda__pos-text">{{ item.Pos_Inspeksi }}</span>
                                </div>
                            </div>

                            <!-- 5. Action Buttons Column -->
                            <div class="agenda__card-actions-col">
                                <HcisButton
                                    v-if="item.Tautan_Review"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="clipboard2-check"
                                    :label="item.Jenis === 'COKLAT' ? 'Review & Inspeksi' : 'Mulai Inspeksi'"
                                    class="agenda__action-btn agenda__action-btn--primary"
                                    @click="bukaReview(item)"
                                />
                                <!-- Pemohon/tamu yang terlambat bisa langsung dihubungi SHE. -->
                                <HubungiPemohonTombol
                                    :kontak="item.Kontak"
                                    size="sm"
                                    class="agenda__action-btn"
                                    :judul="item.Jenis === 'COKLAT' ? 'Hubungi tamu' : 'Hubungi pemohon'"
                                />
                                <!-- Tamu B2B ditutup lewat pencatatan kedatangan (tidak menggugurkan
                                     permit); permit karyawan lewat jalur tidak-hadir miliknya. -->
                                <HcisButton
                                    v-if="item.Jenis === 'COKLAT' && item.Boleh_Tidak_Hadir_Tamu && item.Status_Inspeksi !== 'SELESAI' && item.Status_Inspeksi !== 'TIDAK_HADIR'"
                                    variant="danger-quiet"
                                    size="sm"
                                    leading-icon="person-x"
                                    label="Tamu Tidak Hadir"
                                    class="agenda__action-btn"
                                    :loading="memprosesKedatangan === item.No_Transaksi"
                                    @click="catatKedatangan(item, 'TIDAK_HADIR')"
                                />
                                <HcisButton
                                    v-else-if="item.Jenis !== 'COKLAT' && item.Boleh_Tidak_Hadir && item.Status_Inspeksi !== 'SELESAI'"
                                    variant="danger-quiet"
                                    size="sm"
                                    leading-icon="person-x"
                                    label="Tidak Hadir"
                                    class="agenda__action-btn"
                                    @click="konfirmasiTidakHadir(item)"
                                />
                            </div>
                        </HcisCard>
                    </li>
                </ul>
                <div v-else class="agenda__empty-wrap">
                    <HcisEmptyState
                        title="Semua Antrean Telah Dicatat"
                        description="Tidak ada jadwal inspeksi tertunda yang lewat tanggal saat ini."
                        icon="check-circle-fill"
                        sentiment="success"
                    />
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <span class="text-2xs text-muted font-tabular">
                        {{ belumDicatat.length }} antrean tersisa
                    </span>
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Tutup"
                        @click="dialogBelumDicatat = false"
                    />
                </div>
            </template>
        </HcisDialog>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════════
             2. PANEL PETA SLOT INSPEKSI
             ═══════════════════════════════════════════════════════════════════ -->
        <template #panel-peta-slot>
            <div class="agenda__peta-slot-tab">
                <HcisCard variant="glass" size="md" class="agenda__peta-slot">
                    <div class="agenda__peta-slot-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div class="agenda__peta-slot-title-wrap">
                            <strong class="text-slate-900 text-sm">Peta Slot Inspeksi</strong>
                            <div class="text-2xs text-muted">
                                Seluruh slot yang dibuka, termasuk yang sudah penuh dan yang jamnya sudah lewat.
                            </div>
                        </div>
                        <div class="agenda__peta-slot-actions d-flex align-items-center gap-2 flex-wrap">
                            <HcisBadge :label="`${ringkasSlot.tersedia} tersedia`" size="sm" variant="success" appearance="subtle" />
                            <HcisBadge :label="`${ringkasSlot.penuh} penuh`" size="sm" variant="danger" appearance="subtle" />
                            <HcisBadge :label="`${ringkasSlot.lewat} lewat`" size="sm" variant="neutral" appearance="subtle" />
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                label="Muat ulang"
                                :loading="memuatPetaSlot"
                                @click="muatPetaSlot"
                            />
                        </div>
                    </div>

                    <SlotInspeksiPicker :slots="petaSlot" :loading="memuatPetaSlot" hanya-baca />
                </HcisCard>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════════
             3. PANEL PENGATURAN SLOT & POLA (ACCORDION BASED UX JOURNEY)
             ═══════════════════════════════════════════════════════════════════ -->
        <template #panel-pengaturan>
            <div v-if="polaLoading" class="agenda__daftar">
                <HcisSkeleton v-for="n in 3" :key="n" height="4.5rem" rounded="md" />
            </div>
            <div v-else class="pengaturan">
                <!-- 2A. Executive Settings Metrics Bar -->
                <div class="pengaturan__metrics-strip">
                    <HcisStatCard
                        label="Total Pola Slot"
                        :value="pola.length"
                        suffix="Baris Pola"
                        icon="calendar-week"
                        sentiment="teal"
                        layout="horizontal"
                        pattern="grid"
                    />
                    <HcisStatCard
                        label="Estimasi Kapasitas Mingguan"
                        :value="totalEstimasiKuotaMingguan"
                        suffix="Unit Kendaraan"
                        :caption="`(${totalSlotMingguan} Sesi Slot)`"
                        icon="speedometer2"
                        sentiment="info"
                        layout="horizontal"
                        pattern="grid"
                    />
                    <HcisStatCard
                        label="Pengecualian Mendatang"
                        :value="pengecualian.length"
                        suffix="Tanggal Khusus"
                        icon="calendar-x"
                        sentiment="warning"
                        layout="horizontal"
                        pattern="dots"
                    />
                </div>

                <!-- ===================================================
                     2B. MASTER TEMPAT/POS & SHIFT - dua seksi tersendiri

                     Sebelumnya keduanya berdesakan sebagai chip mungil di satu
                     bilah. Ini pekerjaan penyiapan yang dilakukan SEKALI lalu
                     jarang disentuh, tetapi tanpanya seluruh halaman tidak bisa
                     dipakai: pola slot menunjuk pos dan shift, dan pemohon
                     tidak melihat jadwal apa pun sampai keduanya ada. Sesuatu
                     yang menjadi prasyarat segalanya tidak boleh berwujud chip
                     yang harus ditemukan lebih dulu.

                     Tombol aktif/nonaktif ikut disambungkan di sini. Metodenya
                     sudah ada sejak awal tetapi tidak pernah dipanggil dari
                     mana pun, sehingga pos yang salah ketik hanya bisa diubah
                     namanya - tidak pernah bisa dipensiunkan.
                     =================================================== -->
                <div class="pengaturan__master-grid">
                    <HcisCard
                        variant="glass"
                        size="sm"
                        icon="geo-alt-fill"
                        sentiment="teal"
                        title="Tempat / Pos Inspeksi"
                        description="Lokasi fisik tempat kendaraan diperiksa. Pola slot di bawah selalu menunjuk salah satu pos."
                        :badge="posList.length ? posRingkas : ''"
                        badge-variant="teal"
                    >
                        <template #actions>
                            <HcisButton
                                variant="primary"
                                size="sm"
                                leading-icon="plus-lg"
                                label="Tambah Pos"
                                @click="bukaTambahPos"
                            />
                        </template>

                        <div v-if="!posList.length" class="pengaturan__master-empty">
                            <HcisIcon name="geo-alt" size="lg" class="text-muted" />
                            <p class="pengaturan__master-empty-title">Belum ada pos inspeksi</p>
                            <p class="pengaturan__master-empty-desc">
                                Tambahkan minimal satu pos sebelum menyusun pola slot - tanpa pos,
                                pemohon tidak akan melihat jadwal apa pun.
                            </p>
                        </div>

                        <ul v-else class="pengaturan__master-list">
                            <li
                                v-for="p in posList"
                                :key="p.id"
                                class="pengaturan__master-row"
                                :class="{ 'is-disabled': !p.aktif }"
                            >
                                <span class="pengaturan__master-row-dot" :class="p.aktif ? 'is-green' : 'is-gray'" />
                                <div class="pengaturan__master-row-body">
                                    <span class="pengaturan__master-row-name">{{ p.nama }}</span>
                                    <span v-if="p.lokasi" class="pengaturan__master-row-meta">{{ p.lokasi }}</span>
                                    <span v-else class="pengaturan__master-row-meta is-empty">Tanpa keterangan lokasi</span>
                                </div>
                                <div class="pengaturan__master-row-actions">
                                    <HcisButton
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="pencil"
                                        label="Ubah"
                                        @click="bukaEditPos(p)"
                                    />
                                    <HcisButton
                                        v-if="!p.aktif"
                                        variant="outline"
                                        size="xs"
                                        leading-icon="play-circle"
                                        label="Aktifkan"
                                        @click="ubahStatusPos(p)"
                                    />
                                    <HcisButton
                                        variant="danger"
                                        size="xs"
                                        leading-icon="trash3"
                                        label="Hapus"
                                        @click="hapusPos(p)"
                                    />
                                </div>
                            </li>
                        </ul>
                    </HcisCard>

                    <HcisCard
                        variant="glass"
                        size="sm"
                        icon="clock-history"
                        sentiment="info"
                        title="Shift Kerja Operasional"
                        description="Rentang jam petugas bertugas. Pola slot hanya boleh dibuat di dalam salah satu shift."
                        :badge="shiftList.length ? shiftRingkas : ''"
                        badge-variant="indigo"
                    >
                        <template #actions>
                            <HcisButton
                                variant="primary"
                                size="sm"
                                leading-icon="plus-lg"
                                label="Tambah Shift"
                                @click="bukaTambahShift"
                            />
                        </template>

                        <div v-if="!shiftList.length" class="pengaturan__master-empty">
                            <HcisIcon name="clock" size="lg" class="text-muted" />
                            <p class="pengaturan__master-empty-title">Belum ada shift kerja</p>
                            <p class="pengaturan__master-empty-desc">
                                Tambahkan shift lebih dulu; pola slot harian disusun di dalam rentang jamnya.
                            </p>
                        </div>

                        <ul v-else class="pengaturan__master-list">
                            <li
                                v-for="sh in shiftList"
                                :key="sh.id"
                                class="pengaturan__master-row"
                                :class="{ 'is-disabled': !sh.aktif }"
                            >
                                <span class="pengaturan__master-row-dot" :class="sh.aktif ? 'is-indigo' : 'is-gray'" />
                                <div class="pengaturan__master-row-body">
                                    <span class="pengaturan__master-row-name">{{ sh.nama }}</span>
                                    <span class="pengaturan__master-row-meta font-tabular">
                                        {{ sh.jam_mulai }}-{{ sh.jam_selesai }}
                                        <template v-if="sh.jam_selesai <= sh.jam_mulai"> &middot; melewati tengah malam</template>
                                    </span>
                                </div>
                                <div class="pengaturan__master-row-actions">
                                    <HcisButton
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="pencil"
                                        label="Ubah"
                                        @click="bukaEditShift(sh)"
                                    />
                                    <HcisButton
                                        v-if="!sh.aktif"
                                        variant="outline"
                                        size="xs"
                                        leading-icon="play-circle"
                                        label="Aktifkan"
                                        @click="ubahStatusShift(sh)"
                                    />
                                    <HcisButton
                                        variant="danger"
                                        size="xs"
                                        leading-icon="trash3"
                                        label="Hapus"
                                        @click="hapusShift(sh)"
                                    />
                                </div>
                            </li>
                        </ul>
                    </HcisCard>
                </div>

                <!-- 2C. Unsaved Changes Sticky Banner -->
                <HcisAlert
                    v-if="polaDiubah"
                    variant="warning"
                    title="Perubahan Pola Slot Belum Disimpan"
                    description="Terdapat penambahan, perubahan jam, durasi, kuota, atau duplikasi jadwal yang belum tersimpan di server."
                    class="mb-1"
                >
                    <template #actions>
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="check2"
                            label="Simpan Pola Sekarang"
                            :loading="polaMenyimpan"
                            @click="simpanPola"
                        />
                    </template>
                </HcisAlert>

                <!-- ═══════════════════════════════════════════════════════════
                     2D. ACCORDION 1: POLA SLOT MINGGUAN (HARI ACCORDION)
                     ═══════════════════════════════════════════════════════════ -->
                <div class="pengaturan__acc-section">
                    <div class="pengaturan__acc-section-header" @click="toggleSeksi('pola')">
                        <div class="pengaturan__acc-section-title-wrap">
                            <div class="pengaturan__acc-icon is-teal">
                                <HcisIcon name="calendar-week" size="xs" />
                            </div>
                            <div>
                                <h3 class="pengaturan__acc-title">1. Pola Slot Inspeksi Mingguan</h3>
                                <p class="pengaturan__acc-desc">Atur matriks jam operasional, durasi, dan kuota kendaraan per hari dan shift kerja.</p>
                            </div>
                        </div>

                        <div class="pengaturan__acc-section-actions ms-auto" @click.stop>
                            <HcisButton
                                variant="primary"
                                size="sm"
                                leading-icon="check2"
                                label="Simpan Pola Slot"
                                :loading="polaMenyimpan"
                                @click="simpanPola"
                            />
                            <button
                                type="button"
                                class="pengaturan__acc-toggle-btn"
                                :title="seksiTerbuka.pola ? 'Tutup Seksi' : 'Buka Seksi'"
                                @click="toggleSeksi('pola')"
                            >
                                <HcisIcon :name="seksiTerbuka.pola ? 'chevron-up' : 'chevron-down'" size="xs" />
                            </button>
                        </div>
                    </div>

                    <div v-show="seksiTerbuka.pola" class="pengaturan__acc-section-body">
                        <!-- Toolbar Controls: Filters & Accordion Expand All -->
                        <div class="pengaturan__pola-toolbar">
                            <div class="pengaturan__pola-filters">
                                <div class="pengaturan__filter-select-wrap">
                                    <span class="pengaturan__filter-label">Filter Pos:</span>
                                    <HcisSelect
                                        v-model="polaFilterPos"
                                        size="sm"
                                        placeholder="Semua Pos"
                                        :clearable="false"
                                        style="min-width: 160px;"
                                    >
                                        <HcisSelectOption value="ALL" :label="`Semua Pos (${daftarPos.length})`" />
                                        <HcisSelectOption v-for="p in daftarPos" :key="p.id" :value="p.id" :label="p.nama" />
                                    </HcisSelect>
                                </div>

                                <div class="pengaturan__filter-select-wrap">
                                    <span class="pengaturan__filter-label">Filter Shift:</span>
                                    <HcisSelect
                                        v-model="polaFilterShift"
                                        size="sm"
                                        placeholder="Semua Shift"
                                        :clearable="false"
                                        style="min-width: 150px;"
                                    >
                                        <HcisSelectOption value="ALL" :label="`Semua Shift (${shiftList.length})`" />
                                        <HcisSelectOption v-for="sh in shiftList" :key="sh.id" :value="sh.id" :label="sh.nama" />
                                    </HcisSelect>
                                </div>

                                <div class="pengaturan__filter-select-wrap">
                                    <span class="pengaturan__filter-label">Filter Hari:</span>
                                    <HcisSelect
                                        v-model="polaFilterHari"
                                        size="sm"
                                        placeholder="Semua Hari"
                                        :clearable="false"
                                        style="min-width: 140px;"
                                    >
                                        <HcisSelectOption value="ALL" label="Semua Hari" />
                                        <HcisSelectOption v-for="(nama, idx) in NAMA_HARI_ISO" :key="idx" :value="idx + 1" :label="nama" />
                                    </HcisSelect>
                                </div>
                            </div>

                            <div class="pengaturan__acc-bulk-actions ms-auto">
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    leading-icon="arrows-expand"
                                    label="Buka Semua Hari"
                                    @click="bukaSemuaHari"
                                />
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    leading-icon="arrows-collapse"
                                    label="Tutup Semua Hari"
                                    @click="tutupSemuaHari"
                                />
                            </div>
                        </div>

                        <!-- 7-Day Accordion Items List -->
                        <div class="pengaturan__days-accordion-list mt-3">
                            <div
                                v-for="h in hariTersaring"
                                :key="h.hari"
                                class="pengaturan__day-acc-item"
                                :class="{
                                    'is-open': hariTerbuka.includes(h.hari),
                                    'is-mati': !h.aktif
                                }"
                            >
                                <!-- Day Accordion Header -->
                                <div class="pengaturan__day-acc-header" @click="toggleHari(h.hari)">
                                    <div class="pengaturan__day-acc-chevron">
                                        <HcisIcon :name="hariTerbuka.includes(h.hari) ? 'chevron-down' : 'chevron-right'" size="2xs" />
                                    </div>

                                    <div class="pengaturan__day-acc-title-group">
                                        <HcisIcon :name="h.aktif ? 'calendar-check-fill' : 'calendar-x-fill'" size="xs" :class="h.aktif ? 'text-teal' : 'text-slate-400'" />
                                        <strong class="pengaturan__day-acc-name">{{ NAMA_HARI_ISO[h.hari - 1] }}</strong>
                                        <HcisBadge
                                            :variant="h.aktif ? 'success' : 'neutral'"
                                            :label="h.aktif ? 'Aktif' : 'Nonaktif / Libur'"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>

                                    <div class="pengaturan__day-acc-summary font-tabular">
                                        <span class="pengaturan__day-acc-tag">{{ jadwalHari(h.hari).length }} baris jadwal</span>
                                        <span class="pengaturan__day-acc-sep">·</span>
                                        <span class="pengaturan__day-acc-tag">Kapasitas: <strong>{{ kuotaHari(h.hari) }} Unit</strong></span>
                                    </div>

                                    <div class="pengaturan__day-acc-actions ms-auto" @click.stop>
                                        <HcisButton
                                            v-if="h.aktif && jadwalHari(h.hari).length > 0"
                                            variant="outline"
                                            size="xs"
                                            leading-icon="copy"
                                            label="Salin Jadwal..."
                                            title="Salin seluruh jadwal hari ini ke hari-hari lainnya"
                                            @click="bukaDialogSalinJadwal(h.hari)"
                                        />
                                        <HcisButton
                                            :variant="h.aktif ? 'danger-quiet' : 'secondary'"
                                            size="xs"
                                            :leading-icon="h.aktif ? 'slash-circle' : 'check-circle'"
                                            :label="h.aktif ? 'Nonaktifkan' : 'Aktifkan'"
                                            @click="ubahStatusHari(h.hari, null, !h.aktif)"
                                        />
                                    </div>
                                </div>

                                <!-- Day Accordion Body (Shifts & Slots) -->
                                <div v-show="hariTerbuka.includes(h.hari)" class="pengaturan__day-acc-body">
                                    <div
                                        v-for="sh in shiftTersaring"
                                        :key="sh.id"
                                        class="pengaturan__shift-card-block"
                                        :class="{ 'is-mati': !h.aktif || !aktifShiftHari(h, sh.id) }"
                                    >
                                        <!-- Shift Subheader -->
                                        <div class="pengaturan__shift-card-head">
                                            <div class="pengaturan__shift-card-title-group">
                                                <HcisIcon name="clock-history" size="xs" class="text-indigo" />
                                                <strong class="pengaturan__shift-card-name">{{ sh.nama }}</strong>
                                                <span class="pengaturan__shift-card-time-badge font-tabular">
                                                    Jam Kerja: {{ sh.jam_mulai }} – {{ sh.jam_selesai }} WIB
                                                </span>
                                                <HcisBadge
                                                    v-if="!aktifShiftHari(h, sh.id)"
                                                    variant="neutral"
                                                    label="Shift Dimatikan Khusus Hari Ini"
                                                    size="xs"
                                                    appearance="subtle"
                                                />
                                            </div>

                                            <div class="pengaturan__shift-card-actions ms-auto">
                                                <HcisButton
                                                    variant="primary"
                                                    size="xs"
                                                    leading-icon="plus-lg"
                                                    label="Tambah Jadwal"
                                                    :disabled="!h.aktif || !aktifShiftHari(h, sh.id)"
                                                    @click="tambahJadwal(h.hari, sh.id)"
                                                />
                                                <HcisButton
                                                    :variant="aktifShiftHari(h, sh.id) ? 'danger-quiet' : 'secondary'"
                                                    size="xs"
                                                    :leading-icon="aktifShiftHari(h, sh.id) ? 'slash-circle' : 'check-circle'"
                                                    :label="aktifShiftHari(h, sh.id) ? 'Matikan Shift' : 'Hidupkan Shift'"
                                                    :disabled="!h.aktif"
                                                    @click="ubahStatusHari(h.hari, sh.id, !aktifShiftHari(h, sh.id))"
                                                />
                                            </div>
                                        </div>

                                        <!-- Slot Rows List -->
                                        <div v-if="jadwalHariShift(h.hari, sh.id).length > 0" class="pengaturan__slot-rows-list">
                                            <div
                                                v-for="baris in jadwalHariShift(h.hari, sh.id)"
                                                :key="baris._kunci"
                                                class="pengaturan__slot-row-card"
                                                :class="{ 'is-invalid-range': !hitungSlotDanKuota(baris).valid }"
                                            >
                                                <!-- Row Inputs Grid -->
                                                <div class="pengaturan__slot-inputs-grid">
                                                    <!-- Pos Selector -->
                                                    <HcisField
                                                        :id="`agenda-inspeksi-pos-${baris._kunci}`" label="Pos Inspeksi" size="sm" class="pengaturan__slot-col-pos">
                                                        <HcisSelect
                                                            v-model="baris.id_pos"
                                                            size="sm"
                                                            placeholder="Pilih Pos"
                                                            :clearable="false"
                                                            @change="polaDiubah = true"
                                                        >
                                                            <HcisSelectOption
                                                                v-for="p in posList"
                                                                :key="p.id"
                                                                :value="p.id"
                                                                :label="p.aktif ? p.nama : `${p.nama} (nonaktif)`"
                                                                :disabled="!p.aktif"
                                                            />
                                                        </HcisSelect>
                                                    </HcisField>

                                                    <!-- Jam Mulai & Selesai -->
                                                    <div class="pengaturan__slot-col-time">
                                                        <HcisField
                                                            :id="`agenda-inspeksi-mulai-${baris._kunci}`"
                                                            v-model="baris.jam_mulai"
                                                            type="time"
                                                            size="sm"
                                                            label="Jam Mulai"
                                                            @input="polaDiubah = true"
                                                        />
                                                        <HcisField
                                                            :id="`agenda-inspeksi-selesai-${baris._kunci}`"
                                                            v-model="baris.jam_selesai"
                                                            type="time"
                                                            size="sm"
                                                            label="Jam Selesai"
                                                            @input="polaDiubah = true"
                                                        />
                                                    </div>

                                                    <!-- Durasi with Preset Buttons -->
                                                    <div class="pengaturan__slot-col-durasi">
                                                        <HcisField
                                                            :id="`agenda-inspeksi-durasi-${baris._kunci}`"
                                                            v-model.number="baris.durasi"
                                                            type="number"
                                                            min="5"
                                                            max="240"
                                                            step="5"
                                                            suffix="mnt"
                                                            size="sm"
                                                            label="Durasi per Sesi"
                                                            @input="polaDiubah = true"
                                                        />
                                                        <div class="pengaturan__preset-chips">
                                                            <button
                                                                v-for="d in [15, 30, 45, 60]"
                                                                :key="d"
                                                                type="button"
                                                                class="pengaturan__preset-chip"
                                                                :class="{ 'is-selected': Number(baris.durasi) === d }"
                                                                @click="setDurasiPreset(baris, d)"
                                                            >
                                                                {{ d }}m
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Kuota with Preset Buttons -->
                                                    <div class="pengaturan__slot-col-kuota">
                                                        <HcisField
                                                            :id="`agenda-inspeksi-kuota-${baris._kunci}`"
                                                            v-model.number="baris.kuota"
                                                            type="number"
                                                            min="1"
                                                            max="50"
                                                            suffix="unit"
                                                            size="sm"
                                                            label="Kuota / Sesi"
                                                            @input="polaDiubah = true"
                                                        />
                                                        <div class="pengaturan__preset-chips">
                                                            <button
                                                                v-for="k in [1, 2, 3, 5]"
                                                                :key="k"
                                                                type="button"
                                                                class="pengaturan__preset-chip"
                                                                :class="{ 'is-selected': Number(baris.kuota) === k }"
                                                                @click="setKuotaPreset(baris, k)"
                                                            >
                                                                {{ k }}u
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Actions -->
                                                    <div class="pengaturan__slot-col-actions">
                                                        <HcisButton
                                                            variant="secondary"
                                                            size="sm"
                                                            leading-icon="copy"
                                                            title="Duplikasi jadwal ini ke hari berikutnya"
                                                            @click="duplikasiPola(pola.indexOf(baris))"
                                                        />
                                                        <HcisButton
                                                            variant="danger-quiet"
                                                            size="sm"
                                                            leading-icon="trash"
                                                            title="Hapus jadwal ini"
                                                            @click="hapusBarisPola(pola.indexOf(baris))"
                                                        />
                                                    </div>
                                                </div>

                                                <!-- Row Breakdown: Real-time Sesi Jam Chips -->
                                                <div class="pengaturan__slot-breakdown-bar">
                                                    <div v-if="hitungSlotDanKuota(baris).valid" class="pengaturan__slot-breakdown-left">
                                                        <div class="pengaturan__calc-pill">
                                                            <HcisIcon name="check2-circle" size="2xs" />
                                                            <span class="font-tabular font-bold">{{ hitungSlotDanKuota(baris).slotTotal }} Sesi Slot</span>
                                                            <span class="text-slate-400">·</span>
                                                            <span class="font-tabular">Total Kapasitas: <strong>{{ hitungSlotDanKuota(baris).kuotaTotal }} Kendaraan</strong></span>
                                                        </div>

                                                        <!-- Visual Session Time Chips -->
                                                        <div class="pengaturan__session-chips-wrap">
                                                            <span class="pengaturan__session-label">Sesi:</span>
                                                            <span
                                                                v-for="(sesi, sIdx) in daftarSesiSlot(baris)"
                                                                :key="sIdx"
                                                                class="pengaturan__session-chip font-tabular"
                                                            >
                                                                {{ sesi }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div v-else class="pengaturan__calc-error font-tabular">
                                                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                                        <span>{{ hitungSlotDanKuota(baris).pesan }}</span>
                                                    </div>

                                                    <div v-if="diLuarJamShift(baris)" class="pengaturan__shift-outside-alert">
                                                        <HcisIcon name="exclamation-circle-fill" size="2xs" class="text-amber-600" />
                                                        <span>Jam slot di luar jam kerja shift ({{ sh.jam_mulai }}–{{ sh.jam_selesai }})</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="pengaturan__shift-empty">
                                            <span class="text-xs text-muted">Belum ada jadwal slot inspeksi pada shift ini.</span>
                                            <HcisButton
                                                v-if="h.aktif && aktifShiftHari(h, sh.id)"
                                                variant="outline"
                                                size="xs"
                                                leading-icon="plus-lg"
                                                label="Tambah Jadwal Pertama"
                                                @click="tambahJadwal(h.hari, sh.id)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pengaturan__aksi">
                            <span class="text-xs text-muted me-auto d-flex align-items-center gap-1.5">
                                <HcisIcon name="info-circle" size="xs" />
                                Slot booking pemeriksaan karyawan dihitung otomatis dari pola aktif ini.
                            </span>
                            <HcisButton
                                variant="primary"
                                size="sm"
                                leading-icon="check2"
                                label="Simpan Pola Slot"
                                :loading="polaMenyimpan"
                                @click="simpanPola"
                            />
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     2E. ACCORDION 2: PENGECUALIAN TANGGAL (LIBUR & SLOT KHUSUS)
                     ═══════════════════════════════════════════════════════════ -->
                <div class="pengaturan__acc-section">
                    <div class="pengaturan__acc-section-header" @click="toggleSeksi('pengecualian')">
                        <div class="pengaturan__acc-section-title-wrap">
                            <div class="pengaturan__acc-icon is-amber">
                                <HcisIcon name="calendar-x" size="xs" />
                            </div>
                            <div>
                                <h3 class="pengaturan__acc-title">2. Pengecualian Tanggal (Libur Pos &amp; Slot Tambahan)</h3>
                                <p class="pengaturan__acc-desc">Tutup slot booking pada tanggal libur/audit K3 atau buka slot ekstra lembur di luar pola rutin.</p>
                            </div>
                        </div>

                        <div class="pengaturan__acc-section-actions ms-auto" @click.stop>
                            <span v-if="pengecualian.length" class="badge bg-amber-100 text-amber-800 font-tabular">
                                {{ pengecualian.length }} Terjadwal
                            </span>
                            <button
                                type="button"
                                class="pengaturan__acc-toggle-btn"
                                :title="seksiTerbuka.pengecualian ? 'Tutup Seksi' : 'Buka Seksi'"
                                @click="toggleSeksi('pengecualian')"
                            >
                                <HcisIcon :name="seksiTerbuka.pengecualian ? 'chevron-up' : 'chevron-down'" size="xs" />
                            </button>
                        </div>
                    </div>

                    <div v-show="seksiTerbuka.pengecualian" class="pengaturan__acc-section-body">
                        <!-- Mode Selector Tab (TUTUP vs TAMBAH) -->
                        <div class="pengaturan__pengecualian-mode-toggle">
                            <button
                                type="button"
                                class="pengaturan__mode-btn is-tutup"
                                :class="{ 'is-active': pengecualianBaru.jenis === 'TUTUP' }"
                                @click="pengecualianBaru.jenis = 'TUTUP'"
                            >
                                <div class="pengaturan__mode-icon-box">
                                    <HcisIcon name="lock-fill" size="xs" />
                                </div>
                                <div class="pengaturan__mode-info">
                                    <strong>Tutup Pos (Libur / Audit)</strong>
                                    <span>Menonaktifkan booking slot pada tanggal tertentu</span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="pengaturan__mode-btn is-tambah"
                                :class="{ 'is-active': pengecualianBaru.jenis === 'TAMBAH' }"
                                @click="pengecualianBaru.jenis = 'TAMBAH'"
                            >
                                <div class="pengaturan__mode-icon-box">
                                    <HcisIcon name="plus-circle-fill" size="xs" />
                                </div>
                                <div class="pengaturan__mode-info">
                                    <strong>Slot Tambahan (Lembur / Khusus)</strong>
                                    <span>Membuka kuota ekstra di luar pola rutin</span>
                                </div>
                            </button>
                        </div>

                        <!-- Dynamic Form for Exception -->
                        <div class="pengaturan__form mt-3">
                            <HcisField
                                id="agenda-inspeksi-tanggal-pengecualian"
                                v-model="pengecualianBaru.tanggal"
                                label="Tanggal Pengecualian"
                                type="date"
                                :min="hariIni"
                                size="sm"
                                required
                            />

                            <HcisField
                                id="agenda-inspeksi-pos-sasaran-pengecualianbaru-jenis-tutup"
                                :label="`Pos Sasaran ${pengecualianBaru.jenis === 'TUTUP' ? '(Kosong = Semua Pos)' : ''}`"
                                size="sm"
                            >
                                <HcisSelect
                                    v-model="pengecualianBaru.id_pos"
                                    size="sm"
                                    :placeholder="pengecualianBaru.jenis === 'TUTUP' ? 'Semua Pos Inspeksi' : 'Pilih pos'"
                                    clearable
                                >
                                    <HcisSelectOption v-for="p in daftarPos" :key="p.id" :value="p.id" :label="p.nama" />
                                </HcisSelect>
                            </HcisField>

                            <!-- Fields for TAMBAH Mode -->
                            <template v-if="pengecualianBaru.jenis === 'TAMBAH'">
                                <HcisField
                                    id="agenda-inspeksi-shift-opsional" label="Shift (opsional)" size="sm">
                                    <HcisSelect v-model="pengecualianBaru.id_shift" size="sm" placeholder="Tanpa shift" clearable>
                                        <HcisSelectOption v-for="sh in shiftAktif" :key="sh.id" :value="sh.id" :label="sh.nama" />
                                    </HcisSelect>
                                </HcisField>
                                <HcisField
                                    id="agenda-inspeksi-jam-mulai-2"
                                    v-model="pengecualianBaru.jam_mulai"
                                    label="Jam Mulai"
                                    type="time"
                                    size="sm"
                                    required
                                />
                                <HcisField
                                    id="agenda-inspeksi-jam-selesai-2"
                                    v-model="pengecualianBaru.jam_selesai"
                                    label="Jam Selesai"
                                    type="time"
                                    size="sm"
                                    required
                                />
                                <HcisField
                                    id="agenda-inspeksi-durasi-per-slot"
                                    v-model.number="pengecualianBaru.durasi"
                                    label="Durasi per Slot"
                                    type="number"
                                    min="5"
                                    max="240"
                                    step="5"
                                    suffix="mnt"
                                    size="sm"
                                    required
                                />
                                <HcisField
                                    id="agenda-inspeksi-kuota-per-slot"
                                    v-model.number="pengecualianBaru.kuota"
                                    label="Kuota per Slot"
                                    type="number"
                                    min="1"
                                    max="50"
                                    suffix="unit"
                                    size="sm"
                                    required
                                />
                            </template>

                            <HcisField
                                id="agenda-inspeksi-alasan-keterangan"
                                v-model="pengecualianBaru.keterangan"
                                label="Alasan / Keterangan"
                                type="text"
                                size="sm"
                                maxlength="250"
                                placeholder="Misal: Libur Nasional Idul Fitri, Audit K3 Tahunan, Lembur Akhir Pekan"
                                class="pengaturan__field--lebar"
                            />

                            <div class="pengaturan__field pengaturan__field--tombol">
                                <HcisButton
                                    variant="primary"
                                    size="sm"
                                    leading-icon="plus-lg"
                                    label="Tambahkan Pengecualian"
                                    :loading="pengecualianMenyimpan"
                                    :disabled="!pengecualianBaru.tanggal || (pengecualianBaru.jenis === 'TAMBAH' && !pengecualianBaru.id_pos)"
                                    @click="simpanPengecualian"
                                />
                            </div>
                        </div>

                        <!-- Impact Preview Box -->
                        <div v-if="pengecualianBaru.tanggal" class="pengaturan__preview-callout" :class="pengecualianBaru.jenis === 'TUTUP' ? 'is-danger' : 'is-success'">
                            <HcisIcon :name="pengecualianBaru.jenis === 'TUTUP' ? 'shield-slash-fill' : 'calendar-plus-fill'" size="xs" class="flex-shrink-0" />
                            <span v-if="pengecualianBaru.jenis === 'TUTUP'">
                                <strong>Dampak:</strong> Menonaktifkan booking seluruh slot inspeksi pada tanggal <strong>{{ labelTanggal(pengecualianBaru.tanggal) }}</strong> untuk <strong>{{ namaPos(pengecualianBaru.id_pos) || 'Semua Pos Inspeksi' }}</strong>.
                            </span>
                            <span v-else>
                                <strong>Dampak:</strong> Membuka slot ekstra pada tanggal <strong>{{ labelTanggal(pengecualianBaru.tanggal) }}</strong> di <strong>{{ namaPos(pengecualianBaru.id_pos) || 'Pos Terpilih' }}</strong> ({{ hitungSlotDanKuota(pengecualianBaru).pesan }}).
                            </span>
                        </div>

                        <!-- Active Exceptions List -->
                        <div class="pengaturan__pengecualian-wrap mt-4">
                            <div class="pengaturan__subhead">
                                <HcisIcon name="list-check" size="xs" class="text-brand me-1.5" />
                                <strong>Daftar Pengecualian Tanggal Mendatang</strong>
                            </div>

                            <ul v-if="pengecualian.length" class="pengaturan__daftar">
                                <li v-for="p in pengecualian" :key="p.id" class="pengaturan__pengecualian" :class="`is-${p.jenis === 'TUTUP' ? 'tutup' : 'tambah'}`">
                                    <div class="pengaturan__pengecualian-badge-col">
                                        <HcisBadge
                                            :variant="p.jenis === 'TUTUP' ? 'danger' : 'success'"
                                            :label="p.jenis === 'TUTUP' ? 'Tutup Pos' : 'Slot Tambahan'"
                                            size="sm"
                                            appearance="subtle"
                                        />
                                    </div>
                                    <div class="pengaturan__pengecualian-date">
                                        <HcisIcon name="calendar-event" size="2xs" class="text-slate-400" />
                                        <strong class="font-tabular">{{ labelTanggal(p.tanggal) }}</strong>
                                    </div>
                                    <span class="pengaturan__pengecualian-pos">
                                        <HcisIcon name="geo-alt-fill" size="2xs" class="text-teal" />
                                        {{ p.pos || 'Semua Pos Inspeksi' }}
                                    </span>
                                    <span v-if="p.jenis === 'TAMBAH'" class="pengaturan__pengecualian-specs font-tabular">
                                        {{ p.jam_mulai }} – {{ p.jam_selesai }} · {{ p.durasi }} mnt · Kuota: {{ p.kuota }} Unit
                                    </span>
                                    <span v-if="p.keterangan" class="pengaturan__pengecualian-desc text-truncate">
                                        "{{ p.keterangan }}"
                                    </span>
                                    <HcisButton
                                        variant="danger-quiet"
                                        size="sm"
                                        leading-icon="trash"
                                        title="Hapus Pengecualian Ini"
                                        class="ms-auto"
                                        @click="hapusPengecualian(p)"
                                    />
                                </li>
                            </ul>
                            <p v-else class="agenda__redup mt-2 mb-0">Belum ada pengecualian tanggal mendatang yang tercatat.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================
                 DIALOG WAJIB BERADA DI DALAM SLOT PANEL.

                 VisitorWorkspace merender <slot /> bawaannya hanya sebagai
                 CADANGAN ketika slot panel-<tab> tidak ada. Halaman ini punya
                 dua panel, jadi apa pun yang diletakkan di luar keduanya tidak
                 pernah dirender sama sekali - dialognya tidak "gagal terbuka",
                 ia tidak ada. Gejalanya tombol yang diklik tanpa reaksi apa pun,
                 tanpa galat di console, dan state-nya benar-benar berubah.
                 =================================================== -->
        <!-- ═══════════════════════════════════════════════════════════════════
             4. MODAL DIALOG TAMBAH / UBAH POS INSPEKSI
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-model="dialogPos"
            :title="posForm.id ? 'Ubah Tempat & Pos Inspeksi' : 'Tambah Tempat & Pos Inspeksi'"
            icon="geo-alt-fill"
            sentiment="teal"
            size="md"
        >
            <div class="d-flex flex-column gap-3 py-2">
                <HcisField
                    id="agenda-inspeksi-nama-pos-inspeksi"
                    v-model="posForm.nama"
                    label="Nama Pos Inspeksi"
                    size="sm"
                    maxlength="100"
                    placeholder="Misal: Pos Utama, Pos Barat, Pos 2"
                    required
                />
                <HcisField
                    id="agenda-inspeksi-keterangan-lokasi-fisik"
                    v-model="posForm.lokasi"
                    label="Keterangan Lokasi Fisik"
                    size="sm"
                    maxlength="250"
                    placeholder="Misal: Gerbang depan, sebelah pos satpam timur"
                />
                <HcisField
                    id="agenda-inspeksi-nomor-urutan-tampilan"
                    v-model.number="posForm.urutan"
                    label="Nomor Urutan Tampilan"
                    type="number"
                    min="0"
                    max="999"
                    size="sm"
                />
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        :disabled="posMenyimpan"
                        @click="dialogPos = false"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        :leading-icon="posForm.id ? 'check2' : 'plus-lg'"
                        :label="posForm.id ? 'Simpan Perubahan' : 'Tambah Pos'"
                        :loading="posMenyimpan"
                        :disabled="!posForm.nama.trim()"
                        @click="simpanPos"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ═══════════════════════════════════════════════════════════════════
             5. MODAL DIALOG TAMBAH / UBAH SHIFT KERJA
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-model="dialogShift"
            :title="shiftForm.id ? 'Ubah Shift Kerja Operasional' : 'Tambah Shift Kerja Operasional'"
            icon="clock-history"
            sentiment="info"
            size="md"
        >
            <div class="d-flex flex-column gap-3 py-2">
                <HcisField
                    id="agenda-inspeksi-nama-shift"
                    v-model="shiftForm.nama"
                    label="Nama Shift"
                    size="sm"
                    maxlength="50"
                    placeholder="Misal: Shift 1, Shift 2, Shift Pagi"
                    required
                />
                <div class="row g-2">
                    <div class="col-6">
                        <HcisField
                            id="agenda-inspeksi-jam-mulai-kerja"
                            v-model="shiftForm.jam_mulai"
                            label="Jam Mulai Kerja"
                            type="time"
                            size="sm"
                            required
                        />
                    </div>
                    <div class="col-6">
                        <HcisField
                            id="agenda-inspeksi-jam-selesai-kerja"
                            v-model="shiftForm.jam_selesai"
                            label="Jam Selesai Kerja"
                            type="time"
                            size="sm"
                            required
                        />
                    </div>
                </div>
                <HcisField
                    id="agenda-inspeksi-nomor-urutan-tampilan-2"
                    v-model.number="shiftForm.urutan"
                    label="Nomor Urutan Tampilan"
                    type="number"
                    min="0"
                    max="999"
                    size="sm"
                />

                <div v-if="shiftLewatTengahMalam(shiftForm)" class="pengaturan__preview-callout is-warning mt-1">
                    <HcisIcon name="moon-stars-fill" size="xs" class="flex-shrink-0 text-amber-600" />
                    <span>
                        Shift ini melewati tengah malam (dimulai jam <strong>{{ shiftForm.jam_mulai }}</strong> dan selesai jam <strong>{{ shiftForm.jam_selesai }}</strong> keesokan harinya).
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        :disabled="shiftMenyimpan"
                        @click="dialogShift = false"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        :leading-icon="shiftForm.id ? 'check2' : 'plus-lg'"
                        :label="shiftForm.id ? 'Simpan Perubahan' : 'Tambah Shift'"
                        :loading="shiftMenyimpan"
                        :disabled="!shiftForm.nama.trim() || !shiftForm.jam_mulai || !shiftForm.jam_selesai"
                        @click="simpanShift"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ═══════════════════════════════════════════════════════════════════
             6. MODAL DIALOG SALIN JADWAL KE HARI LAIN
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-model="dialogSalinJadwal"
            title="Salin Pola Jadwal ke Hari Lain"
            icon="copy"
            sentiment="teal"
            size="md"
        >
            <div class="d-flex flex-column gap-3 py-2">
                <div class="pengaturan__copy-source-info">
                    <HcisIcon name="calendar-check-fill" size="xs" class="text-teal" />
                    <span>
                        Menyalin <strong>{{ jadwalHari(salinSumberHari).length }} baris jadwal</strong> dari hari <strong>{{ NAMA_HARI_ISO[salinSumberHari - 1] }}</strong>.
                    </span>
                </div>

                <div>
                    <label class="form-label text-xs fw-bold text-slate-700 mb-2">Pilih Hari Tujuan Salin:</label>
                    <div class="pengaturan__copy-targets-grid">
                        <label
                            v-for="(namaHari, idx) in NAMA_HARI_ISO"
                            :key="idx"
                            class="pengaturan__copy-target-item"
                            :class="{
                                'is-disabled': idx + 1 === salinSumberHari,
                                'is-checked': salinTargetHari.includes(idx + 1)
                            }"
                        >
                            <input
                                v-model="salinTargetHari"
                                type="checkbox"
                                :value="idx + 1"
                                :disabled="idx + 1 === salinSumberHari"
                            >
                            <span>{{ namaHari }}</span>
                            <span v-if="idx + 1 === salinSumberHari" class="badge bg-slate-200 text-slate-600 font-tabular ms-auto">Sumber</span>
                        </label>
                    </div>
                </div>

                <div class="pengaturan__preview-callout is-warning">
                    <HcisIcon name="info-circle-fill" size="xs" class="text-amber-600 flex-shrink-0" />
                    <span>
                        Jadwal yang sudah ada di hari tujuan yang dipilih akan <strong>ditimpa</strong> dengan jadwal dari hari {{ NAMA_HARI_ISO[salinSumberHari - 1] }}.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        @click="dialogSalinJadwal = false"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="copy"
                        label="Terapkan Salin Jadwal"
                        :disabled="!salinTargetHari.length"
                        @click="eksekusiSalinJadwal"
                    />
                </div>
            </template>
        </HcisDialog>
        </template>
    </VisitorWorkspace>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import VerifikasiKartuScanner from '@/components/VISITOR/VerifikasiKartuScanner.vue';
import HubungiPemohonTombol from '@/components/VISITOR/HubungiPemohonTombol.vue';
import SlotInspeksiPicker from './components/SlotInspeksiPicker.vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDatePicker,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisProgress,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
} from '@/components/ui';

const NAMA_HARI_ISO = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const STATUS_INSPEKSI = {
    TERJADWAL: { label: 'Terjadwal', variant: 'info' },
    SELESAI: { label: 'Sudah diinspeksi', variant: 'success' },
    TIDAK_HADIR: { label: 'Tidak hadir', variant: 'danger' },
};

const STATUS_KUNJUNGAN = {
    PENDING_APPROVAL: { label: 'Menunggu Approval', variant: 'warning' },
    SCHEDULED: { label: 'Scheduled', variant: 'info' },
    CHECKED_IN: { label: 'Check-In', variant: 'brand' },
    CHECKED_OUT: { label: 'Check-Out', variant: 'neutral' },
    KELUAR_TAK_TERCATAT: { label: 'Keluar Tak Tercatat', variant: 'warning' },
    CANCELLED: { label: 'Cancelled', variant: 'danger' },
};

let kunciBaris = 0;

/**
 * Agenda inspeksi SHE (DDL-2026-123). Satu agenda harian yang menggabungkan
 * booking inspeksi permit karyawan (Hijau/Merah) dan kedatangan tamu B2B yang
 * membawa permit coklat. Tombol "Mulai Inspeksi" membuka meja Verifikator
 * Permit — keputusan approve/tolak tetap satu pintu di sana.
 */
export default {
    name: 'AgendaInspeksiPage',
    components: {
        HubungiPemohonTombol,
        VisitorWorkspace,
        VerifikasiKartuScanner,
        SlotInspeksiPicker,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDatePicker,
        HcisDialog,
        HcisEmptyState,
        HcisField,
        HcisFilterBar,
        HcisIcon,
        HcisProgress,
        HcisSelect,
        HcisSelectOption,
        HcisSkeleton,
        HcisStatCard,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        serverDate: { type: String, default: null },
    },
    data() {
        const hariIni = this.serverDate || new Date().toISOString().slice(0, 10);
        return {
            NAMA_HARI_ISO,
            activeTab: 'agenda',
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            isMobileViewport: typeof window !== 'undefined' ? window.innerWidth < 768 : false,
            filterStatus: 'ALL',
            filterPos: 'ALL',
            cekKartuTerbuka: false,
            belumDicatat: [],
            hariIni,
            tanggal: hariIni,
            agendaLoading: true,
            agendaError: '',
            permit: [],
            coklat: [],
            // No_Transaksi permit yang hasil kedatangannya sedang dikirim; dipakai
            // mematikan tombolnya supaya satu klik ganda tidak mengirim dua kali.
            memprosesKedatangan: null,
            slot: [],
            filterCategory: 'ALL',
            searchQuery: '',
            dialogBelumDicatat: false,
            dialogTidakHadir: false,
            itemTidakHadir: null,
            menyimpanTidakHadir: false,
            dialogPindah: false,
            itemPindah: null,
            slotTersedia: [],
            /** Peta slot untuk SHE: seluruh slot, termasuk penuh dan lewat. */
            petaSlot: [],
            memuatPetaSlot: false,
            memuatSlot: false,
            slotPindah: null,
            alasanPindah: '',
            menyimpanPindah: false,
            polaLoading: false,
            polaDimuat: false,
            polaMenyimpan: false,
            polaDiubah: false,
            polaFilterPos: 'ALL',
            polaFilterShift: 'ALL',
            polaFilterHari: 'ALL',
            pola: [],
            hariList: [],
            shiftList: [],
            dialogShift: false,
            shiftForm: this.shiftKosong(),
            shiftMenyimpan: false,
            posList: [],
            dialogPos: false,
            posForm: this.posKosong(),
            posMenyimpan: false,
            pengecualian: [],
            pengecualianMenyimpan: false,
            pengecualianBaru: this.pengecualianKosong(hariIni),
            dialogSalinJadwal: false,
            salinSumberHari: 1,
            salinTargetHari: [],
            // Accordion states
            seksiTerbuka: {
                pola: true,
                pengecualian: false,
            },
            hariTerbuka: [1], // Open Monday by default
        };
    },
    computed: {
        /** Hitungan keadaan slot untuk lencana di atas peta. */
        ringkasSlot() {
            const awal = { tersedia: 0, penuh: 0, lewat: 0 };

            return this.petaSlot.reduce((rekap, slot) => {
                const k = slot?.keadaan || ((slot?.sisa ?? 0) > 0 ? 'tersedia' : 'penuh');
                rekap[k] = (rekap[k] || 0) + 1;

                return rekap;
            }, awal);
        },
        tanggalBesok() {
            const [y, m, d] = (this.hariIni || new Date().toISOString().slice(0, 10)).split('-').map(Number);
            const besok = new Date(y, m - 1, d + 1);
            return `${besok.getFullYear()}-${String(besok.getMonth() + 1).padStart(2, '0')}-${String(besok.getDate()).padStart(2, '0')}`;
        },
        adaFilterAktif() {
            return this.filterCategory !== 'ALL' || this.filterStatus !== 'ALL' || this.filterPos !== 'ALL' || Boolean(this.searchQuery);
        },
        /** "2 aktif / 3" - jumlah yang BERGUNA lebih dulu, totalnya sebagai konteks. */
        posRingkas() {
            return this.posList.filter((p) => p.aktif).length + ' aktif / ' + this.posList.length;
        },
        shiftRingkas() {
            return this.shiftList.filter((sh) => sh.aktif).length + ' aktif / ' + this.shiftList.length;
        },
        tabs() {
            return [
                {
                    key: 'agenda',
                    label: 'Agenda Harian',
                    icon: 'calendar-day',
                    badge: this.agenda.length ? String(this.agenda.length) : null,
                    badgeVariant: 'info',
                },
                {
                    key: 'peta-slot',
                    label: 'Peta Slot Inspeksi',
                    icon: 'grid-3x3-gap',
                    badge: this.ringkasSlot?.tersedia ? `${this.ringkasSlot.tersedia} Tersedia` : null,
                    badgeVariant: 'teal',
                },
                {
                    key: 'pengaturan',
                    label: 'Pengaturan Slot',
                    icon: 'sliders',
                    badge: this.polaDiubah ? 'Perlu Simpan' : null,
                    badgeVariant: 'warning',
                },
            ];
        },
        /**
         * Yang BELUM diinspeksi naik ke atas (permintaan user 30 Sep 2026),
         * supaya admin SHE membaca antrean kerjanya dari atas; di dalam tiap
         * kelompok tetap urut jam. Yang sudah selesai, tidak hadir, dipindah,
         * atau dibatalkan turun ke bawah.
         */
        agenda() {
            const kelompok = (x) => (this.belumDiinspeksi(x) ? 0 : 1);
            return [...this.permit, ...this.coklat].sort(
                (a, b) => kelompok(a) - kelompok(b) || String(a.Mulai).localeCompare(String(b.Mulai)),
            );
        },
        filteredAgenda() {
            let list = this.agenda;
            if (this.filterCategory === 'PERMIT') {
                list = list.filter((x) => x.Jenis === 'PERMIT');
            } else if (this.filterCategory === 'COKLAT') {
                list = list.filter((x) => x.Jenis === 'COKLAT');
            }

            /* Baris tamu B2B ikut ketiga keranjang ini lewat Status_Inspeksi-nya
               sendiri. "Belum dicatat" untuk mereka berarti BELUM ADA HASIL —
               bukan "jamnya sudah lewat" — sebab jendelanya sepanjang kunjungan
               dan tak ada slot yang bisa terlewat. */
            if (this.filterStatus === 'BELUM_DICATAT') {
                list = list.filter((x) => (x.Status_Inspeksi === 'TERJADWAL' && x.Boleh_Tidak_Hadir)
                    || (x.Jenis === 'COKLAT' && !x.Status_Inspeksi));
            } else if (this.filterStatus === 'TERJADWAL') {
                list = list.filter((x) => (x.Status_Inspeksi === 'TERJADWAL' && !x.Boleh_Tidak_Hadir)
                    || (x.Status_Kunjungan === 'SCHEDULED' && !x.Status_Inspeksi));
            } else if (this.filterStatus === 'SELESAI') {
                list = list.filter((x) => x.Status_Inspeksi === 'SELESAI' || x.Status_Kunjungan === 'CHECKED_OUT' || x.Status_Kunjungan === 'CHECKED_IN');
            } else if (this.filterStatus === 'TIDAK_HADIR') {
                list = list.filter((x) => x.Status_Inspeksi === 'TIDAK_HADIR' || x.Status_Kunjungan === 'CANCELLED');
            }

            if (this.filterPos !== 'ALL') {
                list = list.filter((x) => x.Id_Pos_Inspeksi === this.filterPos || x.id_pos === this.filterPos || (x.Pos_Inspeksi && x.Pos_Inspeksi === this.namaPos(this.filterPos)));
            }

            if (this.searchQuery && this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter((x) => {
                    const nama = (x.Nama || '').toLowerCase();
                    const plat = (x.No_Plat || '').toLowerCase();
                    const pos = (x.Pos_Inspeksi || '').toLowerCase();
                    const instansi = (x.Instansi || '').toLowerCase();
                    const noTrans = (x.No_Transaksi || '').toLowerCase();
                    const kendaraan = (x.Kendaraan || '').toLowerCase();
                    const b2bUnit = (x.B2B_Unit || '').toLowerCase();
                    return (
                        nama.includes(q) ||
                        plat.includes(q) ||
                        pos.includes(q) ||
                        instansi.includes(q) ||
                        noTrans.includes(q) ||
                        kendaraan.includes(q) ||
                        b2bUnit.includes(q)
                    );
                });
            }
            return list;
        },
        kapasitasPos() {
            const peta = new Map();
            const baru = (id, pos, lokasi) => ({ id, pos, lokasi, kuota: 0, terisi: 0, slot: 0, tamu: 0 });
            for (const s of this.slot) {
                const pos = peta.get(s.id_pos) || baru(s.id_pos, s.pos, s.lokasi);
                pos.kuota += Number(s.kuota || 0);
                pos.terisi += Number(s.terisi || 0);
                pos.slot += 1;
                peta.set(s.id_pos, pos);
            }
            /* Kedatangan tamu B2B sengaja TIDAK memakan kuota (keputusan 25 Sep
               2026), tetapi tetap pekerjaan SHE di pos itu: dihitung terpisah,
               dan pos tamu tanpa slot hari itu tetap diberi kartu. */
            for (const c of this.coklat) {
                if (c.Id_Pos_Inspeksi == null) continue;
                const pos = peta.get(c.Id_Pos_Inspeksi) || baru(c.Id_Pos_Inspeksi, c.Pos_Inspeksi, null);
                pos.tamu += 1;
                peta.set(c.Id_Pos_Inspeksi, pos);
            }
            return [...peta.values()];
        },
        optionsPosFilter() {
            const list = (this.kapasitasPos || []).map((p) => ({
                value: p.id,
                label: p.pos + (p.lokasi ? ` (${p.lokasi})` : ''),
            }));
            return list;
        },
        filtersAgenda() {
            return [
                {
                    key: 'status',
                    label: 'Status Inspeksi',
                    type: 'select',
                    placeholder: 'Semua Status',
                    icon: 'check2-circle',
                    options: [
                        { value: '', label: 'Semua Status' },
                        { value: 'BELUM_DICATAT', label: 'Belum Dicatat / Tertunda' },
                        { value: 'TERJADWAL', label: 'Terjadwal' },
                        { value: 'SELESAI', label: 'Selesai' },
                        { value: 'TIDAK_HADIR', label: 'Tidak Hadir' },
                    ],
                },
                {
                    key: 'pos',
                    label: 'Pos Inspeksi',
                    type: 'select',
                    placeholder: 'Semua Pos',
                    icon: 'geo-alt',
                    options: [
                        { value: '', label: 'Semua Pos Inspeksi' },
                        ...this.optionsPosFilter,
                    ],
                },
            ];
        },
        quickChipsAgenda() {
            return [
                { label: 'Semua Antrean', value: 'ALL', badge: this.agenda.length },
                { label: 'Permit Kendaraan', value: 'PERMIT', badge: this.permit.length, color: 'brand' },
                { label: 'Tamu B2B (Coklat)', value: 'COKLAT', badge: this.coklat.length, color: 'warning' },
            ];
        },
        viewModeStandard() {
            return this.viewMode === 'cards' ? 'grid' : this.viewMode;
        },
        /** Hari yang ditampilkan; sakelar hari & shift datang dari server. */
        hariTersaring() {
            return this.polaFilterHari === 'ALL'
                ? this.hariList
                : this.hariList.filter((h) => h.hari === Number(this.polaFilterHari));
        },
        shiftTersaring() {
            return this.polaFilterShift === 'ALL'
                ? this.shiftList
                : this.shiftList.filter((sh) => sh.id === this.polaFilterShift);
        },
        shiftAktif() {
            return this.shiftList.filter((sh) => sh.aktif);
        },
        /** Pos aktif dari master — satu-satunya pilihan untuk pola & pengecualian baru. */
        daftarPos() {
            return this.posList.filter((p) => p.aktif);
        },
        totalEstimasiKuotaMingguan() {
            return this.pola.reduce((acc, p) => {
                const calc = this.hitungSlotDanKuota(p);
                return acc + (calc.valid ? calc.kuotaTotal : 0);
            }, 0);
        },
        totalSlotMingguan() {
            return this.pola.reduce((acc, p) => {
                const calc = this.hitungSlotDanKuota(p);
                return acc + (calc.valid ? calc.slotTotal : 0);
            }, 0);
        },
        statCards() {
            const hitung = (fn) => this.permit.filter(fn).length;
            return [
                {
                    label: 'Terjadwal',
                    value: hitung((x) => x.Status_Inspeksi === 'TERJADWAL'),
                    suffix: 'Kendaraan',
                    icon: 'clock-fill',
                    sentiment: 'info',
                    pattern: 'grid',
                    caption: 'Permit karyawan menunggu inspeksi',
                    loading: this.agendaLoading,
                    interactive: true,
                },
                {
                    label: 'Sudah Diinspeksi',
                    value: hitung((x) => x.Status_Inspeksi === 'SELESAI'),
                    suffix: 'Kendaraan',
                    icon: 'check-circle-fill',
                    sentiment: 'success',
                    pattern: 'dots',
                    caption: 'Permit sudah diperiksa & disetujui',
                    loading: this.agendaLoading,
                    interactive: true,
                },
                {
                    label: 'Tidak Hadir',
                    value: hitung((x) => x.Status_Inspeksi === 'TIDAK_HADIR'),
                    suffix: 'Kendaraan',
                    icon: 'person-x-fill',
                    sentiment: 'danger',
                    pattern: 'crosshair',
                    caption: 'Perlu jadwal ulang pemohon',
                    loading: this.agendaLoading,
                    interactive: true,
                },
                {
                    label: 'Tamu B2B',
                    value: this.coklat.length,
                    suffix: 'Kedatangan',
                    icon: 'truck-front-fill',
                    sentiment: 'warning',
                    pattern: 'grid',
                    caption: 'Permit coklat pada tanggal ini',
                    loading: this.agendaLoading,
                    interactive: true,
                },
            ];
        },
    },
    watch: {
        activeTab(tab) {
            if (tab === 'pengaturan' && !this.polaDimuat) this.muatPola();
            if (tab === 'peta-slot' && (!this.petaSlot || !this.petaSlot.length)) this.muatPetaSlot();
        },
    },
    mounted() {
        this.checkViewport();
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.checkViewport);
        }
        // Tautan dari modal review verifikator: ?tanggal=YYYY-MM-DD&cari=<No_Transaksi>
        try {
            const q = new URLSearchParams(window.location.search);
            if (/^\d{4}-\d{2}-\d{2}$/.test(q.get('tanggal') || '')) this.tanggal = q.get('tanggal');
            if (q.get('cari')) this.searchQuery = q.get('cari');
        } catch (e) {
            // parameter tak terbaca: tampilkan agenda hari ini
        }
        this.muatAgenda();
        this.muatBelumDicatat();
        this.muatPetaSlot();
    },
    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.checkViewport);
        }
    },
    methods: {
        /** Permit karyawan yang slotnya masih berjalan, atau tamu COKLAT tanpa hasil. */
        belumDiinspeksi(item) {
            if (item.Jenis === 'COKLAT') return !item.Status_Inspeksi;
            return item.Status_Inspeksi === 'TERJADWAL';
        },
        toggleSeksi(seksi) {
            this.seksiTerbuka[seksi] = !this.seksiTerbuka[seksi];
        },
        toggleHari(hari) {
            const idx = this.hariTerbuka.indexOf(hari);
            if (idx >= 0) {
                this.hariTerbuka.splice(idx, 1);
            } else {
                this.hariTerbuka.push(hari);
            }
        },
        bukaSemuaHari() {
            this.hariTerbuka = [1, 2, 3, 4, 5, 6, 7];
        },
        tutupSemuaHari() {
            this.hariTerbuka = [];
        },
        bukaTambahPos() {
            this.posForm = this.posKosong();
            this.dialogPos = true;
        },
        bukaEditPos(pos) {
            this.posForm = { id: pos.id, nama: pos.nama, lokasi: pos.lokasi || '', urutan: pos.urutan || 0 };
            this.dialogPos = true;
        },
        bukaTambahShift() {
            this.shiftForm = this.shiftKosong();
            this.dialogShift = true;
        },
        bukaEditShift(shift) {
            this.shiftForm = { id: shift.id, nama: shift.nama, jam_mulai: shift.jam_mulai, jam_selesai: shift.jam_selesai, urutan: shift.urutan || 0 };
            this.dialogShift = true;
        },
        bukaDialogSalinJadwal(hari) {
            this.salinSumberHari = hari;
            // Seluruh hari, bukan hanya Senin-Jumat. Pos inspeksi ini bekerja
            // tujuh hari, dan bawaan yang melewatkan akhir pekan membuat SHE
            // menyalin dua kali tanpa pernah tahu mengapa Sabtu kosong.
            this.salinTargetHari = [1, 2, 3, 4, 5, 6, 7].filter((h) => h !== hari);
            this.dialogSalinJadwal = true;
        },
        eksekusiSalinJadwal() {
            if (!this.salinTargetHari.length) {
                ElMessage.warning('Pilih minimal satu hari tujuan salin.');
                return;
            }
            const sumberPola = this.pola.filter((p) => p.hari === this.salinSumberHari);
            if (!sumberPola.length) {
                ElMessage.warning(`Tidak ada jadwal di hari ${NAMA_HARI_ISO[this.salinSumberHari - 1]} untuk disalin.`);
                return;
            }

            for (const targetHari of this.salinTargetHari) {
                this.pola = this.pola.filter((p) => p.hari !== targetHari);
                for (const item of sumberPola) {
                    this.pola.push({
                        _kunci: `baru-${++kunciBaris}`,
                        id: null,
                        id_pos: item.id_pos,
                        id_shift: item.id_shift,
                        hari: targetHari,
                        jam_mulai: item.jam_mulai,
                        jam_selesai: item.jam_selesai,
                        durasi: item.durasi,
                        kuota: item.kuota,
                    });
                }
            }
            this.polaDiubah = true;
            this.dialogSalinJadwal = false;
            // Also ensure target days are opened in accordion so user can inspect
            for (const th of this.salinTargetHari) {
                if (!this.hariTerbuka.includes(th)) {
                    this.hariTerbuka.push(th);
                }
            }
            ElMessage.success(`Jadwal hari ${NAMA_HARI_ISO[this.salinSumberHari - 1]} berhasil disalin ke ${this.salinTargetHari.length} hari.`);
        },
        setDurasiPreset(baris, durasi) {
            baris.durasi = durasi;
            this.polaDiubah = true;
        },
        setKuotaPreset(baris, kuota) {
            baris.kuota = kuota;
            this.polaDiubah = true;
        },
        daftarSesiSlot(baris) {
            if (!baris.jam_mulai || !baris.jam_selesai || !baris.durasi || Number(baris.durasi) <= 0) return [];
            const [hMulai, mMulai] = String(baris.jam_mulai).split(':').map(Number);
            const [hSelesai, mSelesai] = String(baris.jam_selesai).split(':').map(Number);
            const menitMulai = hMulai * 60 + mMulai;
            const menitSelesai = hSelesai * 60 + mSelesai;
            const durasiTotalMenit = menitSelesai - menitMulai;
            if (durasiTotalMenit <= 0) return [];
            const durasiSlot = Number(baris.durasi);
            if (durasiSlot > durasiTotalMenit) return [];
            const slotCount = Math.floor(durasiTotalMenit / durasiSlot);
            const list = [];
            for (let i = 0; i < slotCount; i++) {
                const startM = menitMulai + i * durasiSlot;
                const endM = startM + durasiSlot;
                const fmt = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
                list.push(`${fmt(startM)}–${fmt(endM)}`);
            }
            return list;
        },
        pengecualianKosong(tanggal) {
            return {
                tanggal,
                jenis: 'TUTUP',
                id_pos: null,
                id_shift: null,
                jam_mulai: '08:00',
                jam_selesai: '11:00',
                durasi: 30,
                kuota: 2,
                keterangan: '',
            };
        },
        jam(waktu) {
            return String(waktu || '').slice(11, 16);
        },
        labelTanggal(tanggal) {
            if (!tanggal) return '';
            const [y, m, d] = String(tanggal).slice(0, 10).split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        labelTanggalSingkat(tanggal) {
            if (!tanggal) return '';
            const [y, m, d] = String(tanggal).slice(0, 10).split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        /** Jam kunjungan tamu; tanggal ikut ditulis bila bukan tanggal yang ditampilkan. */
        waktuTamu(waktu) {
            const nilai = String(waktu || '');
            if (!nilai) return '-';
            return this.hariIniTampil(nilai) ? this.jam(nilai) : `${nilai.slice(8, 10)}/${nilai.slice(5, 7)} ${this.jam(nilai)}`;
        },
        hariIniTampil(waktu) {
            return String(waktu || '').slice(0, 10) === this.tanggal;
        },
        warnaKategori(item) {
            if (item.Jenis === 'COKLAT') return 'warning';
            const nama = String(item.Kategori || '').toLowerCase();
            if (nama.includes('merah')) return 'danger';
            if (nama.includes('hijau')) return 'success';
            return 'info';
        },
        labelKategori(item) {
            return item.Jenis === 'COKLAT' ? 'Coklat · Tamu B2B' : item.Kategori || 'Permit';
        },
        statusItem(item) {
            if (item.Jenis === 'COKLAT') {
                /* Hasil inspeksinya MENDAHULUI status kunjungan: begitu SHE
                   mencatat di pos, itulah kabar terbaru bagi petugas berikutnya.
                   Tanpa ini, baris yang barusan dicatat tetap berbunyi
                   "Scheduled" dan terbaca seperti pencatatan yang gagal. */
                if (item.Status_Inspeksi === 'SELESAI') {
                    return { label: 'Inspeksi Selesai', variant: 'success', pulse: false };
                }
                if (item.Status_Inspeksi === 'TIDAK_HADIR') {
                    return { label: 'Tamu Tidak Hadir', variant: 'danger', pulse: false };
                }

                return STATUS_KUNJUNGAN[item.Status_Kunjungan] || { label: item.Status_Kunjungan || '-', variant: 'neutral', pulse: false };
            }
            if (item.Flag_Approval === 'T' && item.Status_Inspeksi === 'TIDAK_HADIR') {
                return { label: 'Tidak hadir · permit gugur', variant: 'danger', pulse: false };
            }
            if (item.Status_Inspeksi === 'TERJADWAL') {
                if (item.Boleh_Tidak_Hadir) {
                    return { label: 'Belum Dicatat', variant: 'danger', pulse: true };
                }
                return { label: 'Terjadwal', variant: 'info', pulse: true };
            }
            if (item.Status_Inspeksi === 'SELESAI') {
                return { label: 'Selesai', variant: 'success', pulse: false };
            }
            if (item.Status_Inspeksi === 'TIDAK_HADIR') {
                return { label: 'Tidak Hadir', variant: 'danger', pulse: false };
            }
            return STATUS_INSPEKSI[item.Status_Inspeksi] || { label: item.Status_Inspeksi || '-', variant: 'neutral', pulse: false };
        },
        geserHari(arah) {
            const [y, m, d] = this.tanggal.split('-').map(Number);
            const baru = new Date(y, m - 1, d + arah);
            this.tanggal = `${baru.getFullYear()}-${String(baru.getMonth() + 1).padStart(2, '0')}-${String(baru.getDate()).padStart(2, '0')}`;
            this.muatAgenda();
        },
        keHariIni() {
            this.tanggal = this.hariIni;
            this.muatAgenda();
        },
        keHariBesok() {
            this.tanggal = this.tanggalBesok;
            this.muatAgenda();
        },
        tanganiKlikStat(stat) {
            if (!stat) return;
            if (stat.label === 'Terjadwal') {
                this.filterCategory = 'PERMIT';
                this.filterStatus = 'TERJADWAL';
            } else if (stat.label === 'Sudah Diinspeksi') {
                this.filterCategory = 'PERMIT';
                this.filterStatus = 'SELESAI';
            } else if (stat.label === 'Tidak Hadir') {
                this.filterCategory = 'PERMIT';
                this.filterStatus = 'TIDAK_HADIR';
            } else if (stat.label === 'Tamu B2B') {
                this.filterCategory = 'COKLAT';
                this.filterStatus = 'ALL';
            }
        },
        onFilterChange({ key, value }) {
            if (key === 'status') {
                this.filterStatus = value || 'ALL';
            } else if (key === 'pos') {
                this.filterPos = value || 'ALL';
            }
        },
        onChipClick(chip) {
            const val = chip && typeof chip === 'object' ? chip.value : chip;
            this.filterCategory = val || 'ALL';
        },
        onViewModeUpdate(mode) {
            this.viewMode = mode;
        },
        checkViewport() {
            if (typeof window !== 'undefined') {
                this.isMobileViewport = window.innerWidth < 768;
            }
        },
        resetFilter() {
            this.filterCategory = 'ALL';
            this.filterStatus = 'ALL';
            this.filterPos = 'ALL';
            this.searchQuery = '';
        },
        toggleFilterPos(idPos) {
            this.filterPos = this.filterPos === idPos ? 'ALL' : idPos;
        },
        getVehicleIcon(typeName = '') {
            const norm = (typeName || '').toLowerCase();
            if (norm.includes('motor') || norm.includes('sepeda') || norm.includes('roda dua')) return 'bicycle';
            if (norm.includes('truk') || norm.includes('truck') || norm.includes('trailer') || norm.includes('fuso')) return 'truck-front-fill';
            return 'car-front-fill';
        },
        getPosSentiment(pos) {
            const kuota = Math.max(Number(pos.kuota || 0), 1);
            const terisi = Number(pos.terisi || 0);
            const persen = Math.round((terisi / kuota) * 100);
            if (persen >= 100) return 'danger';
            if (persen >= 70) return 'warning';
            return 'success';
        },
        hitungSlotDanKuota(baris) {
            if (!baris.jam_mulai || !baris.jam_selesai || !baris.durasi || Number(baris.durasi) <= 0) {
                return { jamTotal: '0 Jam', slotTotal: 0, kuotaTotal: 0, valid: false, pesan: 'Lengkapi jam & durasi' };
            }
            const [hMulai, mMulai] = String(baris.jam_mulai).split(':').map(Number);
            const [hSelesai, mSelesai] = String(baris.jam_selesai).split(':').map(Number);
            const menitMulai = hMulai * 60 + mMulai;
            const menitSelesai = hSelesai * 60 + mSelesai;
            const durasiTotalMenit = menitSelesai - menitMulai;
            if (durasiTotalMenit <= 0) {
                return { jamTotal: '0 Jam', slotTotal: 0, kuotaTotal: 0, valid: false, pesan: 'Jam selesai harus > jam mulai' };
            }
            const durasiSlot = Number(baris.durasi);
            if (durasiSlot > durasiTotalMenit) {
                return { jamTotal: `${(durasiTotalMenit / 60).toFixed(1)} Jam`, slotTotal: 0, kuotaTotal: 0, valid: false, pesan: 'Durasi slot melebihi rentang jam' };
            }
            const slotCount = Math.floor(durasiTotalMenit / durasiSlot);
            const kuotaPerSlot = Math.max(Number(baris.kuota || 1), 1);
            const kuotaTotal = slotCount * kuotaPerSlot;
            const jamTotalFormatted = durasiTotalMenit % 60 === 0 ? `${durasiTotalMenit / 60} Jam` : `${(durasiTotalMenit / 60).toFixed(1)} Jam`;
            return {
                jamTotal: jamTotalFormatted,
                slotTotal: slotCount,
                kuotaTotal: kuotaTotal,
                valid: true,
                pesan: `${slotCount} Slot · ${kuotaTotal} Unit Kendaraan`,
            };
        },
        duplikasiPola(idx) {
            const sumber = this.pola[idx];
            if (!sumber) return;
            const hariBaru = sumber.hari < 7 ? sumber.hari + 1 : 1;
            this.pola.splice(idx + 1, 0, {
                _kunci: `baru-${++kunciBaris}`,
                id: null,
                id_pos: sumber.id_pos,
                id_shift: sumber.id_shift,
                hari: hariBaru,
                jam_mulai: sumber.jam_mulai,
                jam_selesai: sumber.jam_selesai,
                durasi: sumber.durasi,
                kuota: sumber.kuota,
            });
            this.polaDiubah = true;
            // Open target day in accordion if closed
            if (!this.hariTerbuka.includes(hariBaru)) {
                this.hariTerbuka.push(hariBaru);
            }
            ElMessage.success(`Pola diduplikasi untuk hari ${NAMA_HARI_ISO[hariBaru - 1]}.`);
        },
        hapusBarisPola(idx) {
            this.pola.splice(idx, 1);
            this.polaDiubah = true;
        },
        async muatAgenda() {
            if (!this.tanggal) return;
            this.agendaLoading = true;
            this.agendaError = '';
            try {
                const { data } = await axios.get('/agenda-inspeksi-data', { params: { tanggal: this.tanggal } });
                const isi = data?.result || {};
                this.permit = isi.permit || [];
                this.coklat = isi.coklat || [];
                this.slot = isi.slot || [];
            } catch (e) {
                this.permit = [];
                this.coklat = [];
                this.slot = [];
                this.agendaError = e?.response?.data?.message || 'Agenda inspeksi gagal dimuat.';
            } finally {
                this.agendaLoading = false;
            }
        },
        /**
         * Yang lewat jadwal dan belum dicatat, lintas tanggal. Agenda bekerja
         * per tanggal, jadi tanpa daftar ini yang terlewat kemarin hilang dari
         * pandangan. Daftarnya tidak mengubah apa pun sendiri.
         */
        async muatBelumDicatat() {
            try {
                const { data } = await axios.get('/agenda-inspeksi-belum-dicatat');
                this.belumDicatat = data?.result?.daftar || [];
            } catch {
                // Panel tambahan; kegagalannya tidak boleh menutupi agenda utama.
                this.belumDicatat = [];
            }
        },
        bukaReview(item) {
            window.location.href = item.Tautan_Review;
        },
        /** Seluruh slot beserta keadaannya; dipakai peta slot di tab Agenda. */
        async muatPetaSlot() {
            this.memuatPetaSlot = true;
            try {
                const { data } = await axios.get('/agenda-inspeksi-slot', { params: { semua: 1 } });
                this.petaSlot = data?.result?.slot || [];
            } catch (e) {
                this.petaSlot = [];
                ElMessage.error(e?.response?.data?.message || 'Peta slot gagal dimuat.');
            } finally {
                this.memuatPetaSlot = false;
            }
        },
        async bukaPindah(item) {
            this.itemPindah = item;
            this.slotPindah = null;
            this.alasanPindah = '';
            this.dialogPindah = true;
            this.memuatSlot = true;
            try {
                // SHE justru paling butuh yang penuh: itulah jam yang perlu ditambah kuotanya.
                const { data } = await axios.get('/agenda-inspeksi-slot', { params: { semua: 1 } });
                this.slotTersedia = data?.result?.slot || [];
            } catch (e) {
                this.slotTersedia = [];
                ElMessage.error(e?.response?.data?.message || 'Slot inspeksi gagal dimuat.');
            } finally {
                this.memuatSlot = false;
            }
        },
        async simpanPindah() {
            if (!this.itemPindah || !this.slotPindah || !this.alasanPindah.trim()) return;
            this.menyimpanPindah = true;
            try {
                const { data } = await axios.post(`/agenda-inspeksi/${encodeURIComponent(this.itemPindah.No_Transaksi)}/pindah`, {
                    inspeksi_id_pos: this.slotPindah.id_pos,
                    inspeksi_mulai: this.slotPindah.mulai,
                    alasan: this.alasanPindah.trim(),
                });
                ElMessage.success(data?.message || 'Jadwal inspeksi dipindah.');
                this.menyimpanPindah = false;
                this.dialogPindah = false;
                this.muatAgenda();
                this.muatBelumDicatat();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Jadwal inspeksi gagal dipindah.');
            } finally {
                this.menyimpanPindah = false;
            }
        },
        konfirmasiTidakHadir(item) {
            this.itemTidakHadir = item;
            this.dialogTidakHadir = true;
        },
        async simpanTidakHadir() {
            if (!this.itemTidakHadir) return;
            this.menyimpanTidakHadir = true;
            try {
                const { data } = await axios.post(`/agenda-inspeksi/${encodeURIComponent(this.itemTidakHadir.No_Transaksi)}/tidak-hadir`);
                ElMessage.success(data?.message || 'Ketidakhadiran dicatat.');
                this.dialogTidakHadir = false;
                this.muatAgenda();
                this.muatBelumDicatat();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Ketidakhadiran gagal dicatat.');
            } finally {
                this.menyimpanTidakHadir = false;
            }
        },
        /**
         * Catat hasil inspeksi kedatangan tamu B2B di pos gerbang.
         *
         * Tidak lewat jalur `tidak-hadir` milik permit karyawan: jalur itu
         * menghitung ketidakhadiran dan menggugurkan permit pada hitungan
         * kedua, sedangkan yang tidak datang di sini adalah TAMUNYA.
         */
        async catatKedatangan(item, status) {
            if (!item || this.memprosesKedatangan) return;

            this.memprosesKedatangan = item.No_Transaksi;
            try {
                const { data } = await axios.post(
                    `/agenda-inspeksi/${encodeURIComponent(item.No_Transaksi)}/kedatangan-tamu`,
                    { status },
                );
                ElMessage.success(data?.message || 'Hasil inspeksi tamu dicatat.');
                this.muatAgenda();
                this.muatBelumDicatat();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Hasil inspeksi tamu gagal dicatat.');
            } finally {
                this.memprosesKedatangan = null;
            }
        },
        async muatPola() {
            this.polaLoading = true;
            try {
                const { data } = await axios.get('/agenda-inspeksi-pola');
                const isi = data?.result || {};
                this.shiftList = isi.shift || [];
                this.hariList = isi.hari || [];
                this.posList = isi.pos || [];
                this.pola = (isi.pola || []).map((p) => ({ ...p, _kunci: `id-${p.id}` }));
                this.pengecualian = isi.pengecualian || [];
                this.polaDimuat = true;
                this.polaDiubah = false;
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Pengaturan slot gagal dimuat.');
            } finally {
                this.polaLoading = false;
            }
        },
        pesanValidasi(e, cadangan) {
            const galat = e?.response?.data?.errors;
            if (galat) return Object.values(galat).flat()[0];
            return e?.response?.data?.message || cadangan;
        },
        async simpanPola() {
            this.polaMenyimpan = true;
            try {
                // eslint-disable-next-line no-unused-vars
                const pola = this.pola.map(({ _kunci, ...isi }) => isi);
                const { data } = await axios.post('/agenda-inspeksi-pola', { pola });
                ElMessage.success(data?.message || 'Pola slot disimpan.');
                await this.muatPola();
                this.muatAgenda();
                this.polaDiubah = false;
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Pola slot gagal disimpan.'));
            } finally {
                this.polaMenyimpan = false;
            }
        },
        async simpanPengecualian() {
            this.pengecualianMenyimpan = true;
            try {
                const isi = { ...this.pengecualianBaru };
                if (isi.jenis === 'TUTUP') {
                    delete isi.jam_mulai;
                    delete isi.jam_selesai;
                    delete isi.durasi;
                    delete isi.kuota;
                }
                const { data } = await axios.post('/agenda-inspeksi-pengecualian', isi);
                ElMessage.success(data?.message || 'Pengecualian ditambahkan.');
                this.pengecualianBaru = this.pengecualianKosong(this.pengecualianBaru.tanggal);
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Pengecualian gagal disimpan.'));
            } finally {
                this.pengecualianMenyimpan = false;
            }
        },
        aktifShiftHari(hari, idShift) {
            return hari?.shift?.find((s) => s.id === idShift)?.aktif !== false;
        },
        jadwalHariShift(hari, idShift) {
            return this.pola.filter((p) => p.hari === hari
                && p.id_shift === idShift
                && (this.polaFilterPos === 'ALL' || p.id_pos === this.polaFilterPos));
        },
        jadwalHari(hari) {
            return this.pola.filter((p) => p.hari === hari);
        },
        kuotaHari(hari) {
            return this.jadwalHari(hari).reduce((acc, p) => {
                const calc = this.hitungSlotDanKuota(p);
                return acc + (calc.valid ? calc.kuotaTotal : 0);
            }, 0);
        },
        tambahJadwal(hari, idShift) {
            const acuan = this.jadwalHariShift(hari, idShift).slice(-1)[0] || this.pola[this.pola.length - 1];
            this.pola.push({
                _kunci: `baru-${++kunciBaris}`,
                id: null,
                id_pos: acuan?.id_pos ?? this.daftarPos[0]?.id ?? null,
                id_shift: idShift,
                hari,
                jam_mulai: acuan?.jam_mulai || '08:00',
                jam_selesai: acuan?.jam_selesai || '09:00',
                durasi: acuan?.durasi || 30,
                kuota: acuan?.kuota || 2,
            });
            this.polaDiubah = true;
            if (!this.hariTerbuka.includes(hari)) {
                this.hariTerbuka.push(hari);
            }
        },
        /**
         * Sakelar hari / (hari, shift). Jadwalnya tidak dihapus — dimatikan
         * saja, jadi bisa dihidupkan lagi tanpa menyusun ulang.
         */
        async ubahStatusHari(hari, idShift, aktif) {
            try {
                const { data } = await axios.post('/agenda-inspeksi-hari', { hari, id_shift: idShift, aktif: aktif ? 1 : 0 });
                const pesan = data?.message || 'Sakelar diperbarui.';
                if (data?.result?.booking_terdampak > 0) {
                    ElMessage.warning(pesan);
                } else {
                    ElMessage.success(pesan);
                }
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Sakelar gagal diubah.'));
            }
        },
        shiftKosong() {
            return { id: null, nama: '', jam_mulai: '07:00', jam_selesai: '15:00', urutan: 0 };
        },
        shiftLewatTengahMalam(sh) {
            return Boolean(sh?.jam_mulai && sh?.jam_selesai && sh.jam_selesai <= sh.jam_mulai);
        },
        jumlahJadwalShift(id) {
            return this.pola.filter((p) => p.id_shift === id).length;
        },
        /**
         * Penanda saja: SHE memang sengaja membuka inspeksi di sela-sela jam
         * kerja, jadi jadwal di luar jam shift TIDAK ditolak.
         */
        diLuarJamShift(baris) {
            const sh = this.shiftList.find((x) => x.id === baris.id_shift);
            if (!sh || !baris.jam_mulai || !baris.jam_selesai) return false;
            const di = (jam) => (this.shiftLewatTengahMalam(sh)
                ? jam >= sh.jam_mulai || jam <= sh.jam_selesai
                : jam >= sh.jam_mulai && jam <= sh.jam_selesai);
            return !di(baris.jam_mulai) || !di(baris.jam_selesai);
        },
        async simpanShift() {
            this.shiftMenyimpan = true;
            try {
                const { data } = await axios.post('/agenda-inspeksi-shift', this.shiftForm);
                ElMessage.success(data?.message || 'Shift disimpan.');
                this.shiftForm = this.shiftKosong();
                this.dialogShift = false;
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Shift gagal disimpan.'));
            } finally {
                this.shiftMenyimpan = false;
            }
        },
        /**
         * Hapus pos. Server yang memutuskan apakah benar-benar bisa dihapus.
         *
         * Pos yang pernah dipakai tidak boleh lenyap - riwayat booking mencatat
         * siapa diperiksa di mana - jadi server menonaktifkannya dan menjelaskan
         * sebabnya. Pesannya ditampilkan apa adanya supaya SHE tahu bedanya
         * antara "hilang" dan "disimpan tapi tidak dipakai lagi".
         */
        async hapusPos(p) {
            if (!window.confirm(`Hapus pos "${p.nama}"? Bila pos ini pernah dipakai, ia akan dinonaktifkan agar riwayatnya tetap terbaca.`)) return;

            try {
                const { data } = await axios.post(`/agenda-inspeksi-pos/${p.id}/hapus`);
                if (data?.result?.dihapus) {
                    ElMessage.success(data.message);
                } else {
                    ElMessage.warning(data.message);
                }
                await this.muatPola();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Pos gagal dihapus.');
            }
        },
        async hapusShift(sh) {
            if (!window.confirm(`Hapus shift "${sh.nama}"? Bila shift ini pernah dipakai, ia akan dinonaktifkan agar riwayatnya tetap terbaca.`)) return;

            try {
                const { data } = await axios.post(`/agenda-inspeksi-shift/${sh.id}/hapus`);
                if (data?.result?.dihapus) {
                    ElMessage.success(data.message);
                } else {
                    ElMessage.warning(data.message);
                }
                await this.muatPola();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Shift gagal dihapus.');
            }
        },
        async ubahStatusShift(sh) {
            try {
                const { data } = await axios.post(`/agenda-inspeksi-shift/${sh.id}/status`, { aktif: sh.aktif ? 0 : 1 });
                const pesan = data?.message || 'Status shift diperbarui.';
                if (data?.result?.booking_terdampak > 0) {
                    ElMessage.warning(pesan);
                } else {
                    ElMessage.success(pesan);
                }
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Status shift gagal diubah.'));
            }
        },
        posKosong() {
            return { id: null, nama: '', lokasi: '', urutan: 0 };
        },
        namaPos(id) {
            return this.posList.find((p) => p.id === id)?.nama || '';
        },
        async simpanPos() {
            this.posMenyimpan = true;
            try {
                const { data } = await axios.post('/agenda-inspeksi-pos', this.posForm);
                ElMessage.success(data?.message || 'Pos inspeksi disimpan.');
                this.posForm = this.posKosong();
                this.dialogPos = false;
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Pos inspeksi gagal disimpan.'));
            } finally {
                this.posMenyimpan = false;
            }
        },
        async ubahStatusPos(p) {
            try {
                const { data } = await axios.post(`/agenda-inspeksi-pos/${p.id}/status`, { aktif: p.aktif ? 0 : 1 });
                const pesan = data?.message || 'Status pos diperbarui.';
                if (data?.result?.booking_terdampak > 0) {
                    ElMessage.warning(pesan);
                } else {
                    ElMessage.success(pesan);
                }
                await this.muatPola();
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(this.pesanValidasi(e, 'Status pos gagal diubah.'));
            }
        },
        async hapusPengecualian(p) {
            try {
                await axios.post(`/agenda-inspeksi-pengecualian/${p.id}/hapus`);
                this.pengecualian = this.pengecualian.filter((x) => x.id !== p.id);
                this.muatAgenda();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Pengecualian gagal dihapus.');
            }
        },
    },
};
</script>

<style scoped>
.agenda,
.pengaturan {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    width: 100%;
}

/* ═════════════════════════════════════════════════════════════════════════
   1. TOOLBAR & DATE NAVIGATOR
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__toolbar-card {
    margin-bottom: 0.75rem;
}

.agenda__toolbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    width: 100%;
}

.agenda__nav-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.agenda__date-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.agenda__date-picker-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.agenda__presets-group {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.agenda__tools-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-left: auto;
    flex-wrap: wrap;
}

.agenda__date-callout {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.agenda__date-callout-text {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.agenda__today-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-teal-700, #0f766e);
    background: var(--hcis-teal-50, #f0fdfa);
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    border: 1px solid rgba(13, 148, 136, 0.2);
}

.agenda__today-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #0d9488;
    box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.25);
    animation: agenda-pulse 2s infinite ease-in-out;
}

@keyframes agenda-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.85); }
}

.agenda__tools-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.agenda__view-toggle {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-surface-sunken, #f1f5f9);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
    border-radius: var(--hcis-radius-md, 6px);
    padding: 2px;
}

.agenda__view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.agenda__view-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.agenda__view-btn.is-active {
    background: #ffffff;
    color: var(--hcis-teal-700, #0f766e);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* ═════════════════════════════════════════════════════════════════════════
   1B. OVERDUE ALERT TRIGGER BANNER & MODAL
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__terlambat-banner {
    margin-bottom: 0.75rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.agenda__terlambat-banner:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.12);
}

.agenda__terlambat-banner-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
}

.agenda__terlambat-banner-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.agenda__terlambat-banner-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: rgba(245, 158, 11, 0.18);
    color: #b45309;
    flex-shrink: 0;
}

.agenda__terlambat-banner-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.agenda__terlambat-banner-title {
    font-size: 0.875rem;
    font-weight: 800;
    color: #78350f;
}

.agenda__terlambat-banner-desc {
    font-size: 0.75rem;
    color: #92400e;
    line-height: 1.35;
}

.agenda__terlambat-modal-body {
    max-height: 68vh;
    overflow-y: auto;
    padding: 0.25rem 0.15rem;
    scrollbar-width: thin;
}

/* ═════════════════════════════════════════════════════════════════════════
   2. POS CAPACITY CARDS & METRICS
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__kapasitas-section {
    display: flex;
    flex-direction: column;
    margin-bottom: 0.75rem;
}

.agenda__kapasitas-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.agenda__kapasitas-date {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
}

.agenda__reset-pos-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.55rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #0f766e;
    background: rgba(13, 148, 136, 0.1);
    border: 1px solid rgba(13, 148, 136, 0.25);
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.agenda__reset-pos-pill:hover {
    background: rgba(13, 148, 136, 0.18);
    color: #0d9488;
}

.agenda__kapasitas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 0.75rem;
}

.agenda__kapasitas-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    transition: all 0.15s ease;
}

.agenda__kapasitas-card:hover {
    transform: translateY(-1px);
    border-color: var(--hcis-teal-400, #2dd4bf);
}

.agenda__kapasitas-card.is-selected {
    border-color: var(--hcis-teal-600, #0d9488);
    box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.18);
}

.agenda__kapasitas-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.agenda__kapasitas-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.875rem;
    color: var(--hcis-text-strong, #1e293b);
    min-width: 0;
}

.agenda__kapasitas-name-wrap {
    min-width: 0;
}

.agenda__kapasitas-name {
    display: block;
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.agenda__kapasitas-lokasi {
    max-width: 140px;
}

.agenda__kapasitas-icon-box {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.agenda__kapasitas-numbers {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, #475569);
}

.agenda__kapasitas-filled {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.agenda__kapasitas-sep {
    color: #cbd5e1;
}

.agenda__kapasitas-percent {
    font-size: 0.71875rem;
    font-weight: 700;
    color: #0d9488;
}

.agenda__kapasitas-percent.is-danger {
    color: #dc2626;
}

.agenda__kapasitas-percent.is-warning {
    color: #d97706;
}

.agenda__kapasitas-percent.is-success {
    color: #059669;
}

/* ═════════════════════════════════════════════════════════════════════════
   3. FILTER BAR & SEARCH
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.25rem 0;
}

.agenda__filter-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.agenda__filter-divider {
    width: 1px;
    height: 1.25rem;
    background: var(--hcis-border-subtle, #e2e8f0);
    margin: 0 0.25rem;
}

.agenda__active-filters-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.75rem;
    background: var(--hcis-surface-sunken, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.06));
    border-radius: var(--hcis-radius-md, 6px);
    flex-wrap: wrap;
}

.agenda__chip-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

.agenda__chip-dot.is-green {
    background-color: #10b981;
}

.agenda__chip-dot.is-amber {
    background-color: #f59e0b;
}

.agenda__search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 240px;
    flex: 0 1 300px;
}

.agenda__search-icon {
    position: absolute;
    left: 0.75rem;
    color: #94a3b8;
    pointer-events: none;
}

.agenda__search-input {
    width: 100%;
    min-height: 2.15rem;
    padding: 0.35rem 2rem 0.35rem 2.15rem;
    border: 1px solid var(--hcis-border-subtle, rgba(203, 213, 225, 0.85));
    border-radius: 9999px;
    background: var(--hcis-surface-raised, #ffffff);
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #1e293b);
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.agenda__search-input:focus {
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}

.agenda__search-clear {
    position: absolute;
    right: 0.65rem;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ═════════════════════════════════════════════════════════════════════════
   4. AGENDA CARDS LIST
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.agenda__daftar--grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 0.75rem;
}

.agenda__card-wrap {
    list-style: none;
    margin: 0;
    padding: 0;
}

.agenda__card {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.agenda__card:hover {
    box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.agenda__card :deep(.hcis-card__body) {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
}

.agenda__card-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.agenda__card-meta-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    flex-wrap: wrap;
}

.agenda__trans-tag {
    font-size: 0.71875rem;
    color: #94a3b8;
    font-weight: 600;
}

.agenda__card-status-wrap {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.agenda__card-schedule-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    background: rgba(13, 148, 136, 0.05);
    border: 1px solid rgba(13, 148, 136, 0.15);
    border-radius: 8px;
    width: 100%;
}

.agenda__card-schedule-strip--warning {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.25);
}

.agenda__card-schedule-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    flex-wrap: wrap;
}

.agenda__schedule-icon {
    flex-shrink: 0;
}

.agenda__schedule-permit-box {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    flex-wrap: wrap;
}

.agenda__card-time-start {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.agenda__card-time-sep {
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.agenda__card-time-end {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.agenda__card-duration-badge {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    background: rgba(15, 118, 110, 0.1);
    color: #0f766e;
    border-radius: 4px;
    font-size: 0.625rem;
    font-weight: 700;
}

.agenda__schedule-diff-badge {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.1rem 0.35rem;
    background: #e2e8f0;
    color: #475569;
    border-radius: 4px;
}

.agenda__card-shift-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.agenda__waktu-tamu {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.agenda__waktu-tamu-baris {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.agenda__waktu-tamu-label {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
}

.agenda__waktu-tamu-keluar {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.agenda__waktu-tamu-arrow {
    color: #94a3b8;
    font-size: 0.75rem;
}

.agenda__card-person-block {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    width: 100%;
}

.agenda__card-person-name {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.agenda__card-person-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.78125rem;
    color: var(--hcis-text-muted, #64748b);
}

.agenda__card-instansi {
    display: inline-flex;
    align-items: center;
    font-size: 0.78125rem;
    color: var(--hcis-text-muted, #64748b);
}

.agenda__card-b2b-unit {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.71875rem;
    padding: 0.1rem 0.4rem;
    background: var(--hcis-amber-50, #fef3c7);
    color: var(--hcis-amber-900, #92400e);
    border-radius: 4px;
    font-weight: 600;
}

.agenda__card-details-pod {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 100%;
}

.agenda__card-vehicle-col {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    flex: 1 1 auto;
}

.agenda__plate-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.5rem;
    background: #0f172a;
    color: #ffffff;
    border-radius: 5px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    flex-shrink: 0;
}

.agenda__plate-icon {
    color: #94a3b8;
}

.agenda__plate-text {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.agenda__vehicle-name {
    font-size: 0.78125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #334155);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.agenda__card-pos-col {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #0f766e;
    background: rgba(13, 148, 136, 0.08);
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
    flex-shrink: 0;
}

.agenda__pos-text {
    white-space: nowrap;
}

.agenda__card-actions-col {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    width: 100%;
    margin-top: 0.15rem;
}

.agenda__action-btn {
    white-space: nowrap;
    justify-content: center;
    font-size: 0.8125rem;
    flex: 1 1 calc(50% - 0.25rem);
    min-width: 0;
}

.agenda__action-btn--primary {
    flex: 1 1 100% !important;
    width: 100% !important;
}

.agenda__card-actions-col > .agenda__action-btn:only-child {
    flex: 1 1 100%;
    width: 100%;
}

/* ═════════════════════════════════════════════════════════════════════════
   5. AGENDA TABLE VIEW
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__table-card {
    padding: 0 !important;
    overflow: hidden;
}

.agenda__table-card :deep(.hcis-card__body) {
    padding: 0 !important;
}

.agenda__table-wrapper {
    overflow-x: auto;
    background: var(--hcis-surface-raised, #ffffff);
    border: 1px solid var(--hcis-border-subtle, rgba(226, 232, 240, 0.9));
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.agenda__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.agenda__table th {
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-sunken, #f8fafc);
    color: var(--hcis-text-muted, #64748b);
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.agenda__table td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(226, 232, 240, 0.7));
    vertical-align: middle;
}

.agenda__table-row:hover td {
    background: var(--hcis-surface-sunken, #f8fafc);
}

/* Skeletons */
.agenda__skeleton-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.agenda__skeleton-body {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

/* Empty States */
.agenda__empty-wrap {
    padding: 1rem 0;
}

.agenda__empty-search {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    text-align: center;
}

/* ═════════════════════════════════════════════════════════════════════════
   5. PENGATURAN SLOT & ACCORDION UX STYLES
   ═════════════════════════════════════════════════════════════════════════ */

/* 5A. Metrics Strip */
.pengaturan__metrics-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

/* 5B. Master Quick Control Bar */
.pengaturan__master-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

.pengaturan__master-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pengaturan__master-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.625rem;
    border-radius: var(--hcis-radius-sm, 0.5rem);
    border: 1px solid var(--hcis-border-subtle, rgba(148, 163, 184, 0.22));
    background: var(--hcis-surface-1, rgba(255, 255, 255, 0.04));
}

.pengaturan__master-row.is-disabled .pengaturan__master-row-name,
.pengaturan__master-row.is-disabled .pengaturan__master-row-meta {
    /* Dipensiunkan, bukan dihapus: ia masih menjelaskan booking lama yang
       menunjuk namanya, jadi tetap terbaca - hanya mundur ke belakang. */
    opacity: 0.55;
    text-decoration: line-through;
}

.pengaturan__master-row-dot {
    flex: 0 0 auto;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
}

.pengaturan__master-row-dot.is-green {
    background: var(--hcis-teal-500, #14b8a6);
}

.pengaturan__master-row-dot.is-indigo {
    background: var(--hcis-indigo-500, #6366f1);
}

.pengaturan__master-row-dot.is-gray {
    background: var(--hcis-slate-400, #94a3b8);
}

.pengaturan__master-row-body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}

.pengaturan__master-row-name {
    font-weight: 600;
    font-size: 0.8125rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pengaturan__master-row-meta {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #94a3b8);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pengaturan__master-row-meta.is-empty {
    font-style: italic;
    opacity: 0.7;
}

.pengaturan__master-row-actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pengaturan__master-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.25rem;
    padding: 1.25rem 0.75rem;
}

.pengaturan__master-empty-title {
    margin: 0;
    font-weight: 600;
    font-size: 0.8125rem;
}

.pengaturan__master-empty-desc {
    margin: 0;
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #94a3b8);
    max-width: 34ch;
}

/* 5C. Accordion Section Wrapper */
.pengaturan__acc-section {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.pengaturan__acc-section:hover {
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}

.pengaturan__acc-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: #ffffff;
    cursor: pointer;
    user-select: none;
    border-bottom: 1px solid transparent;
    transition: background 0.15s ease;
}

.pengaturan__acc-section-header:hover {
    background: #f8fafc;
}

.pengaturan__acc-section-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.pengaturan__acc-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pengaturan__acc-icon.is-teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}

.pengaturan__acc-icon.is-amber {
    background: rgba(217, 119, 6, 0.12);
    color: #d97706;
}

.pengaturan__acc-title {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    line-height: 1.2;
}

.pengaturan__acc-desc {
    font-size: 0.78125rem;
    color: #64748b;
    margin: 0.15rem 0 0;
}

.pengaturan__acc-section-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pengaturan__acc-toggle-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pengaturan__acc-toggle-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.pengaturan__acc-section-body {
    padding: 1.25rem;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
}

/* 5D. Pola Toolbar & Bulk Actions */
.pengaturan__pola-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.pengaturan__pola-filters {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pengaturan__filter-select-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78125rem;
    font-weight: 600;
    color: #475569;
}

.pengaturan__acc-bulk-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* 5E. 7-Day Accordion Items */
.pengaturan__days-accordion-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pengaturan__day-acc-item {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pengaturan__day-acc-item:hover {
    border-color: #cbd5e1;
}

.pengaturan__day-acc-item.is-open {
    border-color: #0d9488;
    box-shadow: 0 4px 12px -2px rgba(13, 148, 136, 0.08);
}

.pengaturan__day-acc-item.is-mati {
    opacity: 0.65;
    background: #fafafa;
}

.pengaturan__day-acc-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    cursor: pointer;
    user-select: none;
    flex-wrap: wrap;
    transition: background 0.15s ease;
}

.pengaturan__day-acc-item.is-open .pengaturan__day-acc-header {
    background: rgba(13, 148, 136, 0.05);
    border-bottom: 1px solid rgba(13, 148, 136, 0.15);
}

.pengaturan__day-acc-chevron {
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pengaturan__day-acc-title-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pengaturan__day-acc-name {
    font-size: 0.9375rem;
    color: #0f172a;
}

.pengaturan__day-acc-summary {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78125rem;
    color: #64748b;
    margin-left: 0.5rem;
}

.pengaturan__day-acc-sep {
    color: #cbd5e1;
}

.pengaturan__day-acc-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pengaturan__day-acc-body {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    background: #ffffff;
}

/* 5F. Shift Card Blocks */
.pengaturan__shift-card-block {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.85rem 1rem;
}

.pengaturan__shift-card-block.is-mati {
    opacity: 0.6;
    background: #fafafa;
}

.pengaturan__shift-card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.pengaturan__shift-card-title-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pengaturan__shift-card-name {
    font-size: 0.875rem;
    color: #0f172a;
}

.pengaturan__shift-card-time-badge {
    font-size: 0.71875rem;
    padding: 0.15rem 0.45rem;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 4px;
    font-weight: 600;
}

.pengaturan__shift-card-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* 5G. Slot Rows */
.pengaturan__slot-rows-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pengaturan__slot-row-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.75rem 0.95rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.15s ease;
}

.pengaturan__slot-row-card:hover {
    border-color: #cbd5e1;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.pengaturan__slot-row-card.is-invalid-range {
    border-color: #fca5a5;
    background: #fff5f5;
}

.pengaturan__slot-inputs-grid {
    display: grid;
    grid-template-columns: minmax(160px, 1.2fr) minmax(200px, 1.4fr) minmax(140px, 1fr) minmax(130px, 0.9fr) auto;
    align-items: end;
    gap: 0.75rem;
}

.pengaturan__slot-col-time {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
}

.pengaturan__preset-chips {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.3rem;
}

.pengaturan__preset-chip {
    padding: 0.1rem 0.35rem;
    font-size: 0.65625rem;
    font-weight: 700;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    line-height: 1.1;
    transition: all 0.1s ease;
}

.pengaturan__preset-chip:hover {
    border-color: #4f46e5;
    color: #4f46e5;
}

.pengaturan__preset-chip.is-selected {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}

.pengaturan__slot-col-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding-bottom: 0.2rem;
}

/* 5H. Breakdown & Sesi Jam Chips */
.pengaturan__slot-breakdown-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #e2e8f0;
    flex-wrap: wrap;
}

.pengaturan__slot-breakdown-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pengaturan__calc-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.55rem;
    background: rgba(13, 148, 136, 0.08);
    border: 1px solid rgba(13, 148, 136, 0.2);
    border-radius: 6px;
    font-size: 0.75rem;
    color: #0f766e;
    white-space: nowrap;
}

.pengaturan__session-chips-wrap {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pengaturan__session-label {
    font-size: 0.6875rem;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pengaturan__session-chip {
    padding: 0.1rem 0.4rem;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #334155;
}

.pengaturan__calc-error {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #e11d48;
    font-size: 0.71875rem;
    font-weight: 600;
}

.pengaturan__shift-outside-alert {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    color: #b45309;
    background: #fef3c7;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
}

.pengaturan__shift-empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px dashed #e2e8f0;
}

.pengaturan__aksi {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
    flex-wrap: wrap;
}

/* 5I. Pengecualian Segmented Mode Toggle */
.pengaturan__pengecualian-mode-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    padding: 0.35rem;
    background: #f1f5f9;
    border-radius: 12px;
}

.pengaturan__mode-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: transparent;
    border: 2px solid transparent;
    border-radius: 9px;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s ease;
}

.pengaturan__mode-btn:hover {
    background: rgba(255, 255, 255, 0.6);
}

.pengaturan__mode-btn.is-active {
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.pengaturan__mode-btn.is-tutup.is-active {
    border-color: rgba(239, 68, 68, 0.4);
}

.pengaturan__mode-btn.is-tambah.is-active {
    border-color: rgba(16, 185, 129, 0.4);
}

.pengaturan__mode-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pengaturan__mode-btn.is-tutup .pengaturan__mode-icon-box {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.pengaturan__mode-btn.is-tambah .pengaturan__mode-icon-box {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pengaturan__mode-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pengaturan__mode-info strong {
    font-size: 0.875rem;
    color: #1e293b;
}

.pengaturan__mode-info span {
    font-size: 0.75rem;
    color: #64748b;
}

.pengaturan__preview-callout {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.95rem;
    border-radius: 8px;
    font-size: 0.78125rem;
    margin-top: 0.85rem;
}

.pengaturan__preview-callout.is-danger {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #991b1b;
}

.pengaturan__preview-callout.is-success {
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    color: #166534;
}

.pengaturan__preview-callout.is-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

/* Pengecualian Form & List */
.pengaturan__form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
    gap: 0.85rem;
    align-items: end;
    padding: 0.25rem 0;
}

.pengaturan__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.78125rem;
    font-weight: 700;
    color: #475569;
}

.pengaturan__field--lebar {
    grid-column: span 2;
}

.pengaturan__field--tombol {
    justify-content: flex-end;
}

.pengaturan__subhead {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    color: #1e293b;
    margin-bottom: 0.65rem;
}

.pengaturan__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pengaturan__pengecualian {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.95rem;
    border: 1px solid var(--hcis-border-subtle, rgba(226, 232, 240, 0.9));
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-surface-raised, #ffffff);
    font-size: 0.84375rem;
    transition: all 0.15s ease;
}

.pengaturan__pengecualian.is-tutup {
    background: var(--hcis-danger-50, #fef2f2);
    border-color: var(--hcis-danger-200, #fecaca);
}

.pengaturan__pengecualian.is-tambah {
    background: var(--hcis-teal-50, #f0fdfa);
    border-color: var(--hcis-teal-200, #99f6e4);
}

.pengaturan__pengecualian:hover {
    border-color: var(--hcis-border-strong, #cbd5e1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.pengaturan__pengecualian-date {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #0f172a;
}

.pengaturan__pengecualian-pos {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #0f766e;
    font-weight: 600;
}

.pengaturan__pengecualian-specs {
    font-size: 0.78125rem;
    color: #475569;
    padding: 0.1rem 0.45rem;
    background: #f1f5f9;
    border-radius: 4px;
}

.pengaturan__pengecualian-desc {
    color: #64748b;
    font-style: italic;
    font-size: 0.78125rem;
    max-width: 260px;
}

/* ═════════════════════════════════════════════════════════════════════════
   6. DIALOG CATAT TIDAK HADIR & COPY TOOL
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__dialog-box {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.agenda__dialog-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 10px;
}

.agenda__dialog-alert-title {
    font-size: 0.875rem;
    font-weight: 800;
    color: #991b1b;
    margin-bottom: 0.15rem;
}

.agenda__dialog-alert-desc {
    font-size: 0.78125rem;
    color: #7f1d1d;
    line-height: 1.4;
}

.agenda__dialog-vehicle-pod {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.95rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.agenda__dialog-vehicle-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.agenda__dialog-warning-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #92400e;
    background: #fef3c7;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(217, 119, 6, 0.2);
}

.pengaturan__copy-source-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    background: rgba(13, 148, 136, 0.08);
    border: 1px solid rgba(13, 148, 136, 0.2);
    border-radius: 8px;
    font-size: 0.8125rem;
    color: #0f766e;
}

.pengaturan__copy-targets-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.pengaturan__copy-target-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.15s ease;
}

.pengaturan__copy-target-item:hover:not(.is-disabled) {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.pengaturan__copy-target-item.is-checked {
    border-color: #0d9488;
    background: rgba(13, 148, 136, 0.05);
    color: #0f766e;
}

.pengaturan__copy-target-item.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
}

/* ═════════════════════════════════════════════════════════════════════════
   7. LEWAT JADWAL, BELUM DICATAT (BASE STYLES)
   ═════════════════════════════════════════════════════════════════════════ */
.agenda__terlambat-list {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 0.5rem);
    margin: 0;
    padding: 0;
    list-style: none;
}

.agenda__terlambat-item-wrap {
    list-style: none;
    margin: 0;
    padding: 0;
}

.agenda__terlambat-item {
    border-left: 4px solid var(--hcis-amber-500, #f59e0b) !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.agenda__terlambat-item:hover {
    border-left-color: var(--hcis-amber-600, #d97706) !important;
    box-shadow: 0 4px 12px -2px rgba(245, 158, 11, 0.15);
    transform: translateY(-1px);
}

.agenda__terlambat-item :deep(.hcis-card__body) {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
}

.agenda__terlambat-tz {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.agenda__terlambat-time-date {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-amber-900, #92400e);
    background: var(--hcis-amber-100, #fef3c7);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.agenda__pindah { display: flex; flex-direction: column; gap: 1rem; }
.agenda__peta-slot-tab { display: flex; flex-direction: column; gap: 1rem; width: 100%; }
.agenda__pindah-footer { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%; }
.agenda__pindah-ringkas { min-width: 0; font-size: 0.75rem; color: var(--hcis-slate-500, #64748b); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.agenda__pindah-ringkas strong { color: var(--hcis-slate-800, #1e293b); }
.agenda__pindah-aksi { display: flex; gap: 0.5rem; flex-shrink: 0; }

.agenda__kapasitas-tamu {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.25rem 0.5rem;
    padding: 0.35rem 0.55rem;
    border: 1px dashed var(--hcis-warning-300, #fcd34d);
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-warning-800, #92400e);
    background: var(--hcis-warning-50, #fffbeb);
}
.agenda__kapasitas-tamu-note {
    font-weight: 500;
    color: #b45309;
    font-size: 0.6875rem;
}

/* ═════════════════════════════════════════════════════════════════════════
   8. RESPONSIVE OVERRIDES
   ═════════════════════════════════════════════════════════════════════════ */
@media (max-width: 1200px) {
    .pengaturan__slot-inputs-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
        align-items: start;
    }

    .pengaturan__slot-col-pos {
        grid-column: span 2;
    }

    .pengaturan__slot-col-time {
        grid-column: span 2;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }

    .pengaturan__slot-col-durasi {
        grid-column: span 1;
    }

    .pengaturan__slot-col-kuota {
        grid-column: span 1;
    }

    .pengaturan__slot-col-actions {
        grid-column: span 2;
        display: flex;
        justify-content: flex-end;
        gap: 0.35rem;
        padding-top: 0.25rem;
        border-top: 1px dashed #e2e8f0;
    }
}

@media (max-width: 991.98px) {
    .agenda__card-actions-col {
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    /* 1. Toolbar & Date Navigator */
    .agenda__toolbar-card {
        padding: 0.65rem 0.75rem;
    }

    .agenda__toolbar-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .agenda__nav-group {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .agenda__date-stepper {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        width: 100%;
    }

    .agenda__date-picker-wrap {
        flex: 1 1 auto;
        min-width: 0;
    }

    .agenda__date-picker-wrap :deep(.hcis-date-picker),
    .agenda__date-picker-wrap :deep(.el-date-editor) {
        width: 100% !important;
    }

    .agenda__presets-group {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        width: 100%;
    }

    .agenda__presets-group > * {
        flex: 1 1 50%;
    }

    .agenda__tools-group {
        margin-left: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding-top: 0.5rem;
        border-top: 1px solid #f1f5f9;
        gap: 0.45rem;
        flex-wrap: wrap;
    }

    .agenda__date-callout {
        font-size: 0.8125rem;
    }

    .agenda__tools-actions {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: auto;
    }

    /* 1C. Overdue Alert Trigger Banner */
    .agenda__terlambat-banner {
        padding: 0.75rem 0.85rem;
    }

    .agenda__terlambat-banner-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .agenda__terlambat-banner-left {
        gap: 0.6rem;
    }

    .agenda__terlambat-banner-btn {
        width: 100%;
        justify-content: center;
    }

    /* 2. Kapasitas & Pos Section - Compact Horizontal Swipe Deck */
    .agenda__kapasitas-header-bar {
        margin-bottom: 0.35rem;
    }

    .agenda__kapasitas-grid {
        display: flex;
        overflow-x: auto;
        flex-wrap: nowrap;
        gap: 0.65rem;
        padding: 2px 2px 8px 2px;
        margin: 0 -0.25rem;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .agenda__kapasitas-grid::-webkit-scrollbar {
        display: none;
    }

    .agenda__kapasitas-card {
        flex: 0 0 240px;
        min-width: 220px;
        max-width: 82vw;
        scroll-snap-align: start;
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
    }

    .agenda__kapasitas-card.is-selected {
        border-color: var(--hcis-teal-600, #0d9488);
        box-shadow: 0 0 0 2px #0d9488, 0 4px 12px rgba(13, 148, 136, 0.12);
        background: #f0fdfa;
    }

    /* 3. Filter Bar & Chips (Swipeable horizontally on mobile) */
    .agenda__filter-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        padding: 0.15rem 0;
    }

    .agenda__filter-chips {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 4px;
        margin: 0 -0.25rem;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .agenda__filter-chips::-webkit-scrollbar {
        display: none;
    }

    .agenda__filter-chips > * {
        flex-shrink: 0;
    }

    .agenda__search-wrap {
        width: 100%;
        min-width: 0;
        flex: 1 1 100%;
    }

    /* 4. Agenda Cards (Mobile Compact & Highly Readable Card Layout) */
    .agenda__card {
        margin-bottom: 0.65rem;
        width: 100%;
    }

    .agenda__card :deep(.hcis-card__body) {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.75rem 0.85rem;
        width: 100%;
    }

    .agenda__card-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        width: 100%;
    }

    .agenda__card-meta-wrap {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex-wrap: wrap;
    }

    .agenda__trans-tag {
        font-size: 0.71875rem;
        color: #94a3b8;
        font-weight: 600;
    }

    .agenda__card-status-wrap {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .agenda__card-schedule-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        padding: 0.45rem 0.65rem;
        border-radius: 8px;
        width: 100%;
    }

    .agenda__card-schedule-left {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex-wrap: wrap;
    }

    .agenda__schedule-permit-box {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8125rem;
        font-weight: 700;
        flex-wrap: wrap;
    }

    .agenda__card-time-start {
        font-size: 1.05rem;
        font-weight: 850;
        line-height: 1.1;
    }

    .agenda__card-time-end {
        font-size: 0.75rem;
        font-weight: 600;
    }

    .agenda__waktu-tamu {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
    }

    .agenda__waktu-tamu-baris {
        display: flex;
        align-items: baseline;
        gap: 0.25rem;
    }

    .agenda__card-person-block {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        width: 100%;
    }

    .agenda__card-person-name {
        font-size: 0.9375rem;
        font-weight: 750;
        color: #0f172a;
        line-height: 1.25;
    }

    .agenda__card-person-meta {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
        font-size: 0.78125rem;
    }

    .agenda__card-details-pod {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.45rem 0.65rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        width: 100%;
    }

    .agenda__card-vehicle-col {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
        flex: 1 1 auto;
        overflow: hidden;
    }

    .agenda__plate-badge {
        padding: 0.15rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 6px;
        flex-shrink: 0;
    }

    .agenda__vehicle-name {
        font-size: 0.78125rem;
        font-weight: 600;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .agenda__card-pos-col {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0f766e;
        background: rgba(13, 148, 136, 0.08);
        padding: 0.15rem 0.45rem;
        border-radius: 5px;
        flex-shrink: 0;
    }

    /* Compact 2-column actions grid on mobile */
    .agenda__card-actions-col {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        width: 100%;
        margin-top: 0.15rem;
    }

    .agenda__action-btn {
        flex: 1 1 calc(50% - 0.25rem);
        min-width: 0;
        justify-content: center;
        font-size: 0.8125rem;
    }

    .agenda__action-btn--primary {
        flex: 1 1 100% !important;
        width: 100% !important;
    }

    .agenda__card-actions-col > .agenda__action-btn:only-child {
        flex: 1 1 100%;
        width: 100%;
    }

    /* 5. Terlambat Item in Modal */
    .agenda__terlambat-modal-body {
        max-height: 72vh;
        padding: 0.15rem 0;
    }

    .agenda__terlambat-item :deep(.hcis-card__body) {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.75rem 0.85rem;
        width: 100%;
    }

    /* 6. TAB 2: Peta Slot */
    .agenda__peta-slot :deep(.hcis-card__body) {
        padding: 0.75rem 0.65rem;
    }

    .agenda__peta-slot-header {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.65rem !important;
    }

    .agenda__peta-slot-actions {
        width: 100%;
        justify-content: space-between;
    }

    /* 7. TAB 3: Master Pos & Shift Cards */
    .pengaturan__master-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    /* 8. TAB 3: Accordion Headers & Controls */
    .pengaturan__acc-section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.75rem 0.85rem;
    }

    .pengaturan__acc-section-actions {
        width: 100%;
        justify-content: space-between;
    }

    .pengaturan__acc-section-body {
        padding: 0.75rem 0.85rem;
    }

    .pengaturan__pola-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .pengaturan__pola-filters {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0.5rem;
    }

    .pengaturan__filter-select-wrap {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
    }

    .pengaturan__filter-select-wrap > :deep(.hcis-select) {
        flex: 1;
        min-width: 0 !important;
    }

    .pengaturan__acc-bulk-actions {
        width: 100%;
        justify-content: space-between;
        padding-top: 0.25rem;
        border-top: 1px dashed #f1f5f9;
    }

    .pengaturan__day-acc-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.7rem 0.85rem;
    }

    .pengaturan__day-acc-actions {
        width: 100%;
        justify-content: space-between;
        padding-top: 0.35rem;
        border-top: 1px dashed #e2e8f0;
    }

    .pengaturan__day-acc-body {
        padding: 0.75rem 0.65rem;
    }

    .pengaturan__shift-card-block {
        padding: 0.75rem 0.75rem;
    }

    .pengaturan__shift-card-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .pengaturan__shift-card-actions {
        width: 100%;
        justify-content: space-between;
        padding-top: 0.35rem;
        border-top: 1px dashed #e2e8f0;
    }

    /* Slot inputs grid remains compact 2-column */
    .pengaturan__slot-inputs-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.55rem;
        align-items: start;
    }

    .pengaturan__slot-col-pos {
        grid-column: span 2;
    }

    .pengaturan__slot-col-time {
        grid-column: span 2;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }

    .pengaturan__slot-col-durasi {
        grid-column: span 1;
    }

    .pengaturan__slot-col-kuota {
        grid-column: span 1;
    }

    .pengaturan__slot-col-actions {
        grid-column: span 2;
        display: flex;
        justify-content: flex-end;
        gap: 0.35rem;
        padding-top: 0.25rem;
        border-top: 1px dashed #e2e8f0;
    }

    .pengaturan__slot-breakdown-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.45rem;
    }

    .pengaturan__slot-breakdown-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
        width: 100%;
    }

    .pengaturan__calc-pill {
        width: 100%;
        white-space: normal;
        font-size: 0.71875rem;
    }

    .pengaturan__session-chips-wrap {
        gap: 0.25rem;
    }

    .pengaturan__session-chip {
        font-size: 0.65625rem;
        padding: 0.08rem 0.3rem;
    }

    /* 9. TAB 3: Pengecualian */
    .pengaturan__pengecualian-mode-toggle {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .pengaturan__mode-btn {
        padding: 0.55rem 0.75rem;
        gap: 0.6rem;
    }

    .pengaturan__form {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .pengaturan__field--lebar {
        grid-column: span 1;
    }

    .pengaturan__pengecualian {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.35rem 0.75rem;
        padding: 0.65rem 0.85rem;
        align-items: start;
    }

    .pengaturan__pengecualian-badge-col {
        grid-column: 1;
    }

    .pengaturan__pengecualian > :last-child {
        grid-column: 2;
        grid-row: 1 / span 2;
        margin-left: auto !important;
        align-self: center;
    }

    .pengaturan__pengecualian-date {
        grid-column: 1;
    }

    .pengaturan__pengecualian-pos,
    .pengaturan__pengecualian-specs,
    .pengaturan__pengecualian-desc {
        grid-column: span 2;
    }
}

@media (max-width: 575.98px) {
    /* Navigation Presets on small mobile */
    .agenda__nav-group > :nth-child(4),
    .agenda__nav-group > :nth-child(5) {
        flex: 1 1 calc(50% - 0.25rem);
        justify-content: center;
    }

    /* Master row: wrap nicely */
    .pengaturan__master-row {
        flex-wrap: wrap;
        gap: 0.35rem 0.5rem;
        padding: 0.55rem 0.65rem;
    }

    .pengaturan__master-row-body {
        flex: 1 1 calc(100% - 1.5rem);
        min-width: 0;
    }

    .pengaturan__master-row-actions {
        width: 100%;
        justify-content: flex-end;
        padding-top: 0.35rem;
        border-top: 1px dashed #f1f5f9;
    }

    /* Dialog Pindah Footer */
    .agenda__pindah-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .agenda__pindah-ringkas {
        white-space: normal;
        text-align: center;
    }

    .agenda__pindah-aksi > * {
        flex: 1;
    }
}
</style>
