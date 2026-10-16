<template>
    <div class="container-fluid px-0 sop-scope mk-page">
        <!-- ══ Header Profil Eksekutif & Navigasi Kepatuhan ══ -->
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="lg"
            icon="person-badge-fill"
            kicker="AUDIT KEPATUHAN DOKUMEN"
            :title="ringkas.Nama || kode"
            :subtitle="barisIdentitas"
            :badge="lencana.label"
            :badge-variant="lencana.variant || 'brand'"
            class="mb-3 mk-header"
        >
            <template #breadcrumb>
                <HcisBreadcrumb :items="breadcrumb" size="sm" />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        href="/sop/admin/monitoring?tab=karyawan"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        label="Kembali"
                    />
                    <HcisButton
                        v-if="bisaDiingatkan"
                        variant="warning"
                        size="sm"
                        leading-icon="bell-fill"
                        :label="`Ingatkan (${paketPerluDiingatkan.length})`"
                        :disabled="loadingIngatkan"
                        @click="bukaModalIngatkan"
                    />
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        icon-only
                        leading-icon="arrow-clockwise"
                        :loading="isRefreshing"
                        title="Muat ulang seluruh data"
                        aria-label="Muat ulang"
                        @click="muatUlangSemua"
                    />
                </div>
            </template>

            <!-- Detail Metadata Karyawan dalam Console Header -->
            <template #console>
                <div class="mk-meta-strip">
                    <div v-if="ringkas.No_Induk_Karyawan" class="mk-meta-chip">
                        <i class="bi bi-card-text text-primary"></i>
                        <span class="mk-meta-chip-label">NIK:</span>
                        <strong class="mk-meta-chip-val">{{ ringkas.No_Induk_Karyawan }}</strong>
                    </div>
                    <div v-if="ringkas.Jabatan" class="mk-meta-chip">
                        <i class="bi bi-briefcase text-indigo"></i>
                        <span class="mk-meta-chip-label">Jabatan:</span>
                        <strong class="mk-meta-chip-val text-truncate" :title="ringkas.Jabatan">{{ ringkas.Jabatan }}</strong>
                    </div>
                    <div v-if="ringkas.Departemen || ringkas.Divisi" class="mk-meta-chip">
                        <i class="bi bi-building text-sky"></i>
                        <span class="mk-meta-chip-label">Unit:</span>
                        <strong class="mk-meta-chip-val text-truncate" :title="[ringkas.Departemen, ringkas.Divisi].filter(Boolean).join(' · ')">
                            {{ [ringkas.Departemen, ringkas.Divisi].filter(Boolean).join(' · ') }}
                        </strong>
                    </div>
                    <div v-if="ringkas.Lokasi" class="mk-meta-chip">
                        <i class="bi bi-geo-alt text-rose"></i>
                        <span class="mk-meta-chip-label">Lokasi:</span>
                        <strong class="mk-meta-chip-val">{{ ringkas.Lokasi }}</strong>
                    </div>
                    <div v-if="ringkas.terakhir_aktif" class="mk-meta-chip ms-md-auto d-none d-md-flex">
                        <i class="bi bi-clock-history text-emerald"></i>
                        <span class="mk-meta-chip-label">Aktif Terakhir:</span>
                        <strong class="mk-meta-chip-val">{{ ringkas.terakhir_aktif }}</strong>
                    </div>
                </div>
            </template>
        </HcisHeader>

        <!--
            Spanduk Peringatan Tindakan Kepatuhan.
            Hanya muncul saat ada yang perlu ditangani (overdue, kritis belum selesai, atau revisi versi).
        -->
        <HcisContextBanner
            v-if="perluTindakan.ada"
            class="mb-3 mk-banner-action"
            variant="glass"
            sentiment="warning"
            :title="perluTindakan.judul"
            :subtitle="perluTindakan.kalimat"
            kicker="PERLU DITANGANI"
            icon="exclamation-triangle-fill"
            scope-variant="danger"
            :scope-label="perluTindakan.lencana"
            scope-radar
        />

        <!-- ══ KPI Strip Eksekutif ══ -->
        <HcisStatGroup :stats="statistik" :cols="4" gap="md" appearance="crystal" class="mb-3 mk-stats-group" />

        <!-- ══ Tab Bar Navigasi ══ -->
        <div class="mk-tabs-wrap mb-3">
            <HcisTabs v-model="tab" :tabs="tabs" variant="segmented" class="mk-tabs-bar" />
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             TAB 1: RINGKASAN & ANALITIK KEPATUHAN
             ═══════════════════════════════════════════════════════════════ -->
        <section v-if="tab === 'ringkasan'" class="mk-zona">
            <div class="row g-3">
                <!-- ══ KARTU 1: TINGKAT PENYELESAIAN (Col 4) ══ -->
                <div class="col-12 col-lg-4">
                    <HcisCard
                        size="sm"
                        title="Tingkat Penyelesaian"
                        description="Kesehatan kepatuhan pembacaan dokumen"
                        icon="speedometer2"
                        sentiment="brand"
                        variant="glass"
                        :badge="lencana.label"
                        :badge-variant="lencana.variant || 'brand'"
                        class="h-100"
                    >
                        <HcisChart
                            type="gauge"
                            :card="false"
                            sentiment="brand"
                            :data="ringkas.persen || 0"
                            :height="260"
                            :loading="loading.ringkas"
                            allow-export
                        />
                    </HcisCard>
                </div>

                <!-- ══ KARTU 2: PENYELESAIAN TIAP PAKET DOKUMEN (Col 8) ══ -->
                <div class="col-12 col-lg-8">
                    <HcisCard
                        size="sm"
                        title="Penyelesaian Tiap Paket Dokumen"
                        description="Progres penugasan seluruh paket dokumen"
                        icon="bar-chart-steps"
                        sentiment="brand"
                        variant="glass"
                        :badge="`${paket.length} Paket`"
                        badge-variant="neutral"
                        class="h-100"
                    >
                        <HcisChart
                            type="bar-horizontal"
                            :card="false"
                            sentiment="brand"
                            :categories="paketBar.kategori"
                            :data="paketBar.nilai"
                            :height="260"
                            :loading="loading.paket"
                            :target-value="100"
                            target-label="Tuntas (100%)"
                            allow-table-toggle
                            allow-export
                            empty-text="Belum ada paket yang ditugaskan untuk karyawan ini."
                        />
                        <template #footer>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small">
                                <span><i class="bi bi-info-circle me-1 text-primary"></i>Diurutkan dari paket yang paling membutuhkan perhatian.</span>
                                <span class="fw-semibold text-slate-700">Garis batas menandai penyelesaian 100% (tuntas)</span>
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- ══ KARTU 3: SEBARAN STATUS ITEM (Col 4) ══ -->
                <div class="col-12 col-lg-4">
                    <HcisCard
                        size="sm"
                        title="Sebaran Status Item"
                        description="Komposisi status seluruh item prosedur"
                        icon="pie-chart-fill"
                        sentiment="teal"
                        variant="glass"
                        :badge="`${rekamBaca.ringkas?.item_unik || 0} Total Item`"
                        badge-variant="neutral"
                        class="h-100"
                    >
                        <HcisChart
                            type="donut"
                            :card="false"
                            sentiment="teal"
                            :data="komposisiStatus"
                            :height="260"
                            :loading="loading.rekam"
                            :metric-value="rekamBaca.ringkas?.item_unik || 0"
                            :options="{ legend: { show: false } }"
                            allow-export
                            empty-text="Belum ada item tercatat."
                        />
                    </HcisCard>
                </div>

                <!-- ══ KARTU 4: TREN AKTIVITAS PEMBACAAN (Col 8) ══ -->
                <div class="col-12 col-lg-8">
                    <HcisCard
                        size="sm"
                        title="Tren Aktivitas Pembacaan"
                        description="Riwayat volume konfirmasi item dokumen dari waktu ke waktu"
                        icon="graph-up-arrow"
                        sentiment="violet"
                        variant="glass"
                        :badge="`${trenAktivitas.totalKonfirmasi} Item Selesai`"
                        badge-variant="violet"
                        class="h-100"
                    >
                        <HcisChart
                            type="area"
                            :card="false"
                            sentiment="violet"
                            :categories="trenAktivitas.categories"
                            :data="trenAktivitas.nilai"
                            :height="260"
                            :loading="loading.rekam"
                            metric-unit="item"
                            metric-label="Item Dikonfirmasi"
                            smooth
                            allow-table-toggle
                            allow-export
                            empty-text="Belum ada aktivitas konfirmasi dokumen tercatat."
                        />
                        <template #footer>
                            <div class="d-flex align-items-center gap-2 small text-muted">
                                <i class="bi bi-info-circle text-violet"></i>
                                <span>{{ trenAktivitas.insight }}</span>
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- ══ KARTU 5: POLA WAKTU PEMBACAAN (Col 12 Full Width) ══ -->
                <div v-if="peta.ada" class="col-12">
                    <HcisCard
                        size="sm"
                        title="Pola Waktu Pembacaan"
                        description="Kapan dokumen ini dibaca (Matriks 24 Jam × 7 Hari)"
                        icon="calendar3-week"
                        sentiment="violet"
                        variant="glass"
                        :badge="`Jam Puncak: ${peta.puncak}`"
                        badge-variant="violet"
                    >
                        <HcisChart
                            type="heatmap"
                            :card="false"
                            sentiment="violet"
                            category-unit="jam"
                            value-label="Jumlah Aktivitas"
                            :categories="peta.jam"
                            :series="[{ categories: peta.hari }]"
                            :data="peta.data"
                            :height="260"
                            :loading="loading.rekam"
                            allow-export
                        />
                        <template #footer>
                            <div v-if="peta.insight" class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-clock-history text-violet"></i>
                                <span>{{ peta.insight }}</span>
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════════════════
             TAB 2: DAFTAR PAKET DOKUMEN
             ═══════════════════════════════════════════════════════════════ -->
        <section v-else-if="tab === 'paket'" class="mk-zona">
            <HcisCard
                title="Paket Dokumen yang Ditugaskan"
                :description="`Menampilkan ${paketTersaring.length} dari total ${paket.length} paket yang ditugaskan ke karyawan ini.`"
                kicker="PENUGASAN INDIVIDUAL"
                icon="collection-fill"
                variant="glass"
                sentiment="brand"
            >
                <!-- Official HcisFilterBar Cockpit -->
                <HcisFilterBar
                    v-model:search="cariPaket"
                    search-placeholder="Cari nama paket..."
                    :quick-chips="quickChipsPaket"
                    :active-chip-value="filterStatusPaket"
                    show-view-toggle
                    v-model:view-mode="viewModePaket"
                    :filters="filtersConfigPaket"
                    :filter-values="filterValuesPaket"
                    variant="glass"
                    size="sm"
                    sentiment="brand"
                    :results-count="paketTersaring.length"
                    class="mb-3"
                    @chip-click="(c) => filterStatusPaket = c.value"
                    @filter-change="onFilterChangePaket"
                    @reset="onResetFiltersPaket"
                />

                <!-- Skeleton Loading -->
                <div v-if="loading.paket" class="mk-skeleton">
                    <HcisSkeleton v-for="n in 3" :key="n" type="card" height="92px" rounded="lg" class="mb-2" />
                </div>

                <!-- Empty State -->
                <HcisEmptyState
                    v-else-if="!paket.length"
                    title="Belum ada penugasan dokumen"
                    description="Karyawan ini belum pernah masuk ke satu pun batch penjadwalan dokumen."
                    icon="inbox"
                    variant="dashed"
                />
                <HcisEmptyState
                    v-else-if="!paketTersaring.length"
                    title="Tidak ada paket yang cocok"
                    description="Coba ubah kata kunci pencarian atau sesuaikan saringan status di atas."
                    icon="search"
                    variant="dashed"
                />

                <!-- MODE GRID KARTU (Menggunakan SopTugasCard reusable standar) -->
                <div v-else-if="viewModePaket === 'grid'" class="mk-paket-grid">
                    <SopTugasCard
                        v-for="p in paketTersaring"
                        :key="p.Id_SOP_Paket"
                        :tugas="p"
                        :href="`/sop/admin/monitoring/karyawan/${token}/paket/${p.Id_SOP_Paket}`"
                        action-label="Detail"
                        mode="admin"
                    />
                </div>

                <!-- MODE TABEL DATA -->
                <div v-else class="table-responsive mk-table-wrap">
                    <table class="table mk-table align-middle">
                        <thead>
                            <tr>
                                <th>Nama Paket Dokumen</th>
                                <th class="text-center">Prioritas</th>
                                <th class="text-center">Status</th>
                                <th style="width: 220px">Progres Penyelesaian</th>
                                <th>Tenggat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in paketTersaring" :key="p.Id_SOP_Paket" :class="{ 'table-danger-subtle': p.Is_Terlambat }">
                                <td>
                                    <a
                                        :href="`/sop/admin/monitoring/karyawan/${token}/paket/${p.Id_SOP_Paket}`"
                                        class="fw-bold text-dark text-decoration-none hover-primary d-block"
                                    >
                                        {{ p.Nama_Paket }}
                                    </a>
                                    <div v-if="p.Item_Perlu_Ulang > 0" class="small text-amber mt-0.5">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ p.Item_Perlu_Ulang }} item perlu dibaca ulang
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span v-if="p.Prioritas === 'KRITIS'" class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">
                                        Kritis
                                    </span>
                                    <span v-else class="text-muted small">Normal</span>
                                </td>
                                <td class="text-center">
                                    <StatusPill :text="statusLabel(p.Status_Paket)" :variant="statusVariant(p.Status_Paket)" />
                                </td>
                                <td>
                                    <HcisProgress
                                        :value="p.Persen || 0"
                                        :label="`${p.Item_Selesai} dari ${p.Total_Item} item`"
                                        :sentiment="p.Persen >= 100 ? 'success' : (p.Is_Terlambat ? 'danger' : (p.Prioritas === 'KRITIS' ? 'warning' : 'brand'))"
                                        size="xs"
                                        :glow="false"
                                        :animated="false"
                                        show-value
                                    />
                                </td>
                                <td>
                                    <div :class="{ 'text-danger fw-bold': p.Is_Terlambat }">
                                        <i class="bi bi-calendar-event me-1"></i>{{ p.Tenggat || 'Tanpa tenggat' }}
                                    </div>
                                    <span v-if="p.Is_Terlambat" class="badge bg-danger text-white mt-0.5" style="font-size: 0.65rem">
                                        LEWAT TENGGAT
                                    </span>
                                </td>
                                <td class="text-end">
                                    <HcisButton
                                        :href="`/sop/admin/monitoring/karyawan/${token}/paket/${p.Id_SOP_Paket}`"
                                        label="Detail"
                                        variant="outline"
                                        size="xs"
                                        pill
                                        trailing-icon="chevron-right"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </HcisCard>
        </section>

        <!-- ═══════════════════════════════════════════════════════════════
             TAB 3: RIWAYAT KEPATUHAN & AUDIT TRAIL
             ═══════════════════════════════════════════════════════════════ -->
        <section v-else-if="tab === 'riwayat'" class="mk-zona">
            <!-- Kartu Utama Riwayat dengan FilterBar & Toggle View -->
            <HcisCard
                title="Riwayat Peristiwa Kepatuhan"
                description="Kronologi lengkap jejak audit: penyelesaian, pembukaan kembali, atau pencabutan akses dokumen."
                kicker="JEJAK AUDIT"
                icon="clock-history"
                variant="glass"
                sentiment="brand"
                :badge="`${riwayatTersaring.length} dari ${riwayat.length} Peristiwa`"
                badge-variant="brand"
            >
                <!-- Saringan Cockpit Riwayat -->
                <HcisFilterBar
                    v-model:search="cariRiwayat"
                    v-model:view-mode="viewModeRiwayat"
                    :chips="quickChipsRiwayat"
                    :active-chip="filterPeristiwaRiwayat"
                    search-placeholder="Cari nama paket, aktor, sebab, atau catatan..."
                    :enable-view-mode="true"
                    :view-modes="['timeline', 'table']"
                    :results-count="riwayatTersaring.length"
                    class="mb-3"
                    @chip-click="(c) => filterPeristiwaRiwayat = c.value"
                    @reset="cariRiwayat = ''; filterPeristiwaRiwayat = 'all'"
                />

                <!-- Loading State -->
                <div v-if="loading.riwayat" class="mk-skeleton">
                    <HcisSkeleton v-for="n in 4" :key="n" type="card" height="76px" rounded="lg" class="mb-2" />
                </div>

                <!-- Empty State -->
                <HcisEmptyState
                    v-else-if="!riwayat.length"
                    title="Belum ada peristiwa tercatat"
                    description="Riwayat terisi saat penugasan selesai, dibuka kembali, atau aksesnya dicabut."
                    icon="clock-history"
                    variant="dashed"
                />
                <HcisEmptyState
                    v-else-if="!riwayatTersaring.length"
                    title="Tidak ada peristiwa yang cocok"
                    description="Coba ubah kata kunci pencarian atau pilih saringan peristiwa lain."
                    icon="search"
                    variant="dashed"
                />

                <!-- TAMPILAN 1: MODE TIMELINE AUDIT KRONOLOGIS (Bersih Tanpa Avatar) -->
                <div v-else-if="viewModeRiwayat === 'timeline'" class="mk-audit-stream">
                    <div
                        v-for="(r, idx) in riwayatTersaring"
                        :key="idx"
                        class="mk-audit-node"
                        :class="`is-${String(r.Peristiwa || '').toLowerCase()}`"
                    >
                        <!-- Timeline Jewel Marker -->
                        <div class="mk-audit-marker-col">
                            <div
                                class="mk-audit-jewel"
                                :class="{
                                    'jewel-success': r.Peristiwa === 'SELESAI',
                                    'jewel-warning': r.Peristiwa === 'DIBUKA_LAGI',
                                    'jewel-danger': r.Peristiwa === 'DICABUT',
                                    'jewel-brand': !['SELESAI', 'DIBUKA_LAGI', 'DICABUT'].includes(r.Peristiwa),
                                }"
                            >
                                <i class="bi" :class="peristiwaIcon(r.Peristiwa)"></i>
                            </div>
                            <div v-if="idx !== riwayatTersaring.length - 1" class="mk-audit-rail"></div>
                        </div>

                        <!-- Content Card -->
                        <div class="mk-audit-card">
                            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-1.5">
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-bold text-dark fs-6">{{ r.Nama_Paket }}</span>
                                        <span
                                            class="badge px-2 py-0.5 rounded-pill fw-bold"
                                            :class="{
                                                'bg-success-subtle text-success border border-success-subtle': r.Peristiwa === 'SELESAI',
                                                'bg-warning-subtle text-warning-emphasis border border-warning-subtle': r.Peristiwa === 'DIBUKA_LAGI',
                                                'bg-danger-subtle text-danger border border-danger-subtle': r.Peristiwa === 'DICABUT',
                                                'bg-light text-secondary border': !['SELESAI', 'DIBUKA_LAGI', 'DICABUT'].includes(r.Peristiwa),
                                            }"
                                        >
                                            {{ peristiwaLabel(r.Peristiwa) }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-0 mt-1">
                                        {{ peristiwaKalimat(r.Peristiwa) }}
                                    </p>
                                </div>
                                <div class="text-nowrap text-muted font-monospace small">
                                    <i class="bi bi-clock me-1 text-secondary"></i>{{ r.Tanggal }}
                                </div>
                            </div>

                            <!-- Meta Chips -->
                            <div class="mk-audit-meta-row mt-2">
                                <div v-if="r.Aktor" class="mk-audit-meta-item">
                                    <span class="lbl">Aktor:</span>
                                    <span class="val fw-semibold">{{ r.Aktor }}</span>
                                </div>
                                <div v-if="sebabTeks(r)" class="mk-audit-meta-item">
                                    <span class="lbl">Sebab:</span>
                                    <span class="val fw-semibold text-primary">{{ sebabTeks(r) }}</span>
                                </div>
                                <div v-if="r.Persen !== null && r.Persen !== undefined" class="mk-audit-meta-item">
                                    <span class="lbl">Kemajuan:</span>
                                    <span class="val fw-bold text-dark tnum">{{ Math.round(r.Persen) }}%</span>
                                </div>
                                <div v-if="r.Catatan" class="mk-audit-meta-item mk-audit-note">
                                    <span class="lbl">Catatan:</span>
                                    <span class="val fst-italic text-dark">"{{ r.Catatan }}"</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAMPILAN 2: MODE TABEL AUDIT DETAIL -->
                <div v-else class="table-responsive mk-table-wrap">
                    <table class="table mk-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 140px">Waktu</th>
                                <th>Paket Dokumen</th>
                                <th class="text-center" style="width: 130px">Peristiwa</th>
                                <th class="text-center" style="width: 120px">Progres Saat Itu</th>
                                <th>Sebab / Alasan</th>
                                <th>Aktor &amp; Catatan Audit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, idx) in riwayatTersaring" :key="idx">
                                <td>
                                    <div class="fw-bold text-dark font-monospace small">
                                        <i class="bi bi-clock-history me-1 text-muted"></i>{{ r.Tanggal || '—' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ r.Nama_Paket }}</div>
                                </td>
                                <td class="text-center">
                                    <span
                                        class="badge px-2.5 py-1 rounded-pill fw-bold"
                                        :class="{
                                            'bg-success-subtle text-success border border-success-subtle': r.Peristiwa === 'SELESAI',
                                            'bg-warning-subtle text-warning-emphasis border border-warning-subtle': r.Peristiwa === 'DIBUKA_LAGI',
                                            'bg-danger-subtle text-danger border border-danger-subtle': r.Peristiwa === 'DICABUT',
                                            'bg-light text-secondary border': !['SELESAI', 'DIBUKA_LAGI', 'DICABUT'].includes(r.Peristiwa),
                                        }"
                                    >
                                        <i class="bi me-1" :class="peristiwaIcon(r.Peristiwa)"></i>
                                        {{ peristiwaLabel(r.Peristiwa) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="r.Persen !== null && r.Persen !== undefined" class="badge bg-light text-dark border tnum fw-bold">
                                        {{ Math.round(r.Persen) }}%
                                    </span>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td>
                                    <div v-if="sebabTeks(r)" class="fw-semibold text-dark small">
                                        <i class="bi bi-tag-fill me-1 text-primary" style="font-size: 0.7rem"></i>{{ sebabTeks(r) }}
                                    </div>
                                    <div v-else class="text-muted small">—</div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <i class="bi bi-person-fill me-1 text-secondary"></i>{{ r.Aktor || 'Sistem' }}
                                    </div>
                                    <div v-if="r.Catatan" class="small text-muted fst-italic mt-0.5">
                                        "{{ r.Catatan }}"
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pemberitahuan arsip catatan lama jika ada -->
                <div v-if="riwayatTersembunyi" class="mk-nota mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ riwayatTersembunyi }} entri lama disembunyikan karena paket dokumen-nya sudah tidak ada, sehingga peristiwanya tak bisa dijelaskan lagi.
                </div>
            </HcisCard>
        </section>

        <!-- ══ MODAL KIRIM PENGINGAT (HcisDialog) ══ -->
        <HcisDialog
            v-model="modalIngatkan"
            title="Kirim Pengingat Dokumen"
            :subtitle="`Kirim notifikasi tindak lanjut ke ${ringkas.Nama || kode}`"
            kicker="NOTIFIKASI KEPATUHAN"
            icon="bell-fill"
            sentiment="warning"
            variant="glass"
            size="md"
            :badge="`${paketDipilihUntukIngatkan.length} dari ${paketPerluDiingatkan.length} Paket`"
            badge-variant="warning"
        >
            <div class="py-1">
                <p class="small text-muted mb-3">
                    Pilih satu atau beberapa paket dokumen yang belum tuntas untuk dikirimi notifikasi pengingat ke akun <strong class="text-dark">{{ ringkas.Nama || kode }}</strong>.
                </p>

                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <label class="form-label small fw-bold text-dark mb-0">Target Paket Dokumen (Multi-Select)</label>
                        <div class="d-flex gap-2">
                            <button
                                type="button"
                                class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold"
                                style="font-size: 0.75rem"
                                @click="pilihSemuaPaketIngatkan"
                            >
                                Pilih Semua
                            </button>
                            <span class="text-muted small">·</span>
                            <button
                                type="button"
                                class="btn btn-link p-0 text-danger small text-decoration-none fw-semibold"
                                style="font-size: 0.75rem"
                                @click="kosongkanPaketIngatkan"
                            >
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <HcisSelect
                        v-model="paketDipilihUntukIngatkan"
                        multiple
                        collapse-tags
                        :max-collapse-tags="1"
                        select-all-text="Semua Paket Dokumen Terpilih"
                        placeholder="Pilih paket dokumen yang ingin diingatkan..."
                        size="sm"
                        leading-icon="collection-fill"
                        clearable
                        class="w-100"
                    >
                        <HcisSelectOption
                            v-for="p in paketPerluDiingatkan"
                            :key="p.Id_SOP_Paket"
                            :value="p.Id_SOP_Paket"
                            :label="p.Nama_Paket"
                            :description="`Progres: ${p.Persen || 0}% · Tenggat: ${p.Tenggat || 'Tanpa tenggat'}`"
                            :badge="p.Is_Terlambat ? 'Terlambat' : `${p.Persen || 0}%`"
                            :badge-variant="p.Is_Terlambat ? 'danger' : 'brand'"
                            icon="folder2"
                        />
                    </HcisSelect>
                </div>

                <!-- Rincian Paket yang Dipilih -->
                <div class="mb-1">
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="small fw-bold text-dark">
                            Daftar Paket Terpilih
                            <span class="badge bg-warning-subtle text-warning-emphasis border ms-1 rounded-pill">{{ paketTampilDiModalIngatkan.length }}</span>
                        </span>
                    </div>

                    <div v-if="paketTampilDiModalIngatkan.length" class="mk-reminder-list">
                        <div v-for="p in paketTampilDiModalIngatkan" :key="p.Id_SOP_Paket" class="mk-reminder-item">
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold small text-dark text-truncate">{{ p.Nama_Paket }}</div>
                                <div class="text-muted" style="font-size: 0.72rem">
                                    Progres: {{ p.Persen || 0 }}%
                                </div>
                            </div>
                            <span v-if="p.Is_Terlambat" class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.65rem">
                                Terlambat
                            </span>
                            <span v-else class="badge bg-light text-secondary border" style="font-size: 0.65rem">
                                {{ p.Tenggat || 'Berjalan' }}
                            </span>
                        </div>
                    </div>
                    <HcisEmptyState
                        v-else
                        size="sm"
                        variant="dashed"
                        sentiment="warning"
                        icon="collection"
                        title="Belum ada paket yang dipilih"
                        description="Pilih minimal satu paket dokumen dari daftar di atas untuk mengirim notifikasi pengingat."
                        class="my-2 py-3"
                    />
                </div>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        @click="modalIngatkan = false"
                    />
                    <HcisButton
                        variant="warning"
                        size="sm"
                        leading-icon="send-fill"
                        :loading="loadingIngatkan"
                        :disabled="!paketDipilihUntukIngatkan.length"
                        :label="`Kirim Notifikasi (${paketDipilihUntukIngatkan.length} Paket)`"
                        @click="kirimPengingat"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisBreadcrumb,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisContextBanner,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisProgress,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatGroup,
    HcisTabs,
    HcisTimeline,
} from '@/components/ui';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import SopTugasCard from '../../Shared/components/ui/SopTugasCard.vue';
import { STATUS_PAKET, STATUS_ITEM, PERISTIWA, labelSebab } from '../../Shared/support/constants.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import { useActivityCenter } from '../../../../../composables/useActivityCenter.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/monitoring';

const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const TINGGI = {
    utama: 230,
    ringkas: 220,
    peta: 220,
    daftar: 220,
};

/**
 * Halaman deep-dive audit kepatuhan dokumen satu karyawan (admin).
 */
export default {
    name: 'SopMonitoringKaryawan',
    components: {
        HcisBreadcrumb,
        HcisButton,
        HcisCard,
        HcisChart,
        HcisContextBanner,
        HcisDialog,
        HcisEmptyState,
        HcisFilterBar,
        HcisHeader,
        HcisProgress,
        HcisSelect,
        HcisSelectOption,
        HcisSkeleton,
        HcisStatGroup,
        HcisTabs,
        HcisTimeline,
        StatusPill,
        SopTugasCard,
    },
    mixins: [pageMeta],
    props: {
        kode: { type: String, required: true },
        token: { type: String, required: true },
    },
    data() {
        return {
            TINGGI,
            tab: 'ringkasan',
            ringkas: {},
            paket: [],
            riwayat: [],
            riwayatTersembunyi: 0,
            rekamBaca: { item: [], komposisi: { status: [], tipe: [] }, ringkas: {}, jejak_versi: [] },
            loading: { ringkas: true, paket: true, riwayat: true, rekam: true },

            // Fitur interaktif baru
            cariPaket: '',
            filterStatusPaket: 'all',
            sortPaket: 'tertinggal',
            viewModePaket: 'grid',
            filterValuesPaket: {
                sort: 'tertinggal',
                prioritas: '',
            },
            isRefreshing: false,
            modalIngatkan: false,
            loadingIngatkan: false,
            paketDipilihUntukIngatkan: [],

            // Fitur interaktif tab riwayat
            cariRiwayat: '',
            filterPeristiwaRiwayat: 'all',
            viewModeRiwayat: 'timeline',
        };
    },
    computed: {
        breadcrumb() {
            return [
                { label: 'Monitoring Dokumen', href: '/sop/admin/monitoring?tab=karyawan', icon: 'shield-check' },
                { label: this.ringkas.Nama || this.kode, icon: 'person-fill' },
            ];
        },
        barisIdentitas() {
            const r = this.ringkas;
            const bagian = [
                r.No_Induk_Karyawan ? `NIK ${r.No_Induk_Karyawan}` : null,
                r.Jabatan,
                r.Departemen,
                r.Lokasi,
            ].filter(Boolean);

            return bagian.length ? bagian.join(' · ') : 'Memuat identitas…';
        },
        /** Lencana kepatuhan; keterlambatan mengalahkan persentase setinggi apa pun. */
        lencana() {
            const p = this.ringkas.persen || 0;
            if (!this.ringkas.total_sop) return { label: 'Tanpa penugasan', variant: 'neutral' };
            if (this.ringkas.terlambat > 0) return { label: `${this.ringkas.terlambat} terlambat`, variant: 'danger' };
            if (p >= 100) return { label: 'Tuntas', variant: 'success' };
            if (p >= 80) return { label: 'Hampir tuntas', variant: 'success' };
            if (p >= 50) return { label: 'Berjalan', variant: 'warning' };

            return { label: 'Tertinggal', variant: 'danger' };
        },
        perluUlangTotal() {
            return this.paket.reduce((n, p) => n + (p.Item_Perlu_Ulang || 0), 0);
        },
        /**
         * Spanduk tindakan dirakit dari tiga sinyal yang berbeda sifatnya.
         */
        perluTindakan() {
            const r = this.ringkas;
            const bagian = [];
            if (r.terlambat > 0) bagian.push(`${r.terlambat} paket lewat tenggat`);
            if (r.sop_kritis_belum > 0) bagian.push(`${r.sop_kritis_belum} paket prioritas kritis belum tuntas`);
            if (this.perluUlangTotal > 0) {
                bagian.push(`${this.perluUlangTotal} item perlu dibaca ulang karena versinya berganti`);
            }

            return {
                ada: bagian.length > 0,
                judul: 'Ada yang menunggu tindakan pada karyawan ini',
                kalimat: `${bagian.join(' · ')}.`,
                lencana: `${bagian.length} temuan`,
            };
        },
        statistik() {
            const r = this.ringkas;
            const rb = this.rekamBaca.ringkas || {};

            return [
                {
                    key: 'penugasan',
                    label: 'Paket Ditugaskan',
                    value: r.total_sop ?? 0,
                    suffix: 'paket',
                    icon: 'collection-fill',
                    sentiment: 'primary',
                    caption: r.terakhir_aktif ? `Aktivitas terakhir ${r.terakhir_aktif}` : 'Belum pernah aktif',
                    loading: this.loading.ringkas,
                },
                {
                    key: 'tuntas',
                    label: 'Tuntas',
                    value: r.selesai ?? 0,
                    suffix: 'paket',
                    icon: 'patch-check-fill',
                    sentiment: 'success',
                    progress: r.persen ?? 0,
                    target: `${r.persen ?? 0}% dari yang ditugaskan`,
                    caption: `${r.progress ?? 0} berjalan · ${r.belum ?? 0} belum dibuka`,
                    loading: this.loading.ringkas,
                },
                {
                    key: 'terlambat',
                    label: 'Lewat Tenggat',
                    value: r.terlambat ?? 0,
                    suffix: 'paket',
                    icon: 'exclamation-octagon-fill',
                    sentiment: r.terlambat > 0 ? 'danger' : 'neutral',
                    caption: this.kalimatTenggat,
                    loading: this.loading.ringkas,
                },
                {
                    key: 'durasi',
                    label: 'Waktu Baca Aktif',
                    value: this.durasiTotal.nilai,
                    suffix: this.durasiTotal.satuan,
                    icon: 'stopwatch-fill',
                    sentiment: 'purple',
                    caption: `${rb.dikonfirmasi ?? 0} konfirmasi dari ${rb.item_unik ?? 0} item`,
                    loading: this.loading.rekam,
                },
            ];
        },
        kalimatTenggat() {
            const r = this.ringkas;
            if (!r.tenggat_terdekat) return 'Tak ada tenggat berjalan';
            if (r.hari_tersisa === null || r.hari_tersisa === undefined) {
                return `Tenggat terdekat ${r.tenggat_terdekat}`;
            }
            if (r.hari_tersisa < 0) return `Terlewat ${Math.abs(r.hari_tersisa)} hari`;
            if (r.hari_tersisa === 0) return 'Tenggat terdekat jatuh hari ini';

            return `Tenggat terdekat ${r.hari_tersisa} hari lagi`;
        },
        /** Detik mentah disesuaikan ke menit/jam. */
        durasiTotal() {
            const d = this.rekamBaca.ringkas?.total_detik || 0;
            if (d < 60) return { nilai: d, satuan: 'detik' };
            if (d < 3600) return { nilai: Math.round(d / 60), satuan: 'menit' };

            return { nilai: (d / 3600).toFixed(1), satuan: 'jam' };
        },
        tabs() {
            return [
                { key: 'ringkasan', label: 'Ringkasan & Analitik', icon: 'graph-up-arrow' },
                { key: 'paket', label: 'Paket Dokumen', icon: 'collection', badge: this.paket.length || undefined },
                { key: 'riwayat', label: 'Riwayat Kepatuhan', icon: 'clock-history', badge: this.riwayat.length || undefined },
            ];
        },
        quickChipsPaket() {
            return [
                { value: 'all', label: 'Semua', badge: this.countPaket.all, color: 'brand' },
                { value: 'terlambat', label: 'Terlambat', badge: this.countPaket.terlambat, color: 'danger', icon: 'exclamation-octagon-fill', ping: this.countPaket.terlambat > 0 },
                { value: 'kritis', label: 'Kritis', badge: this.countPaket.kritis, color: 'warning', icon: 'lightning-charge-fill' },
                { value: 'PROGRESS', label: 'Berjalan', badge: this.countPaket.progress, color: 'primary' },
                { value: 'BELUM', label: 'Belum Dibuka', badge: this.countPaket.belum, color: 'neutral' },
                { value: 'SELESAI', label: 'Tuntas', badge: this.countPaket.selesai, color: 'success' },
            ];
        },
        filtersConfigPaket() {
            return [
                {
                    key: 'sort',
                    label: 'Urutan',
                    type: 'select',
                    options: [
                        { value: 'tertinggal', label: 'Paling Tertinggal' },
                        { value: 'nama', label: 'Nama Paket (A-Z)' },
                        { value: 'persen_asc', label: 'Persentase Terendah' },
                        { value: 'persen_desc', label: 'Persentase Tertinggi' },
                        { value: 'tenggat', label: 'Tenggat Terdekat' },
                    ],
                },
                {
                    key: 'prioritas',
                    label: 'Prioritas',
                    type: 'select',
                    options: [
                        { value: 'KRITIS', label: 'Kritis' },
                        { value: 'TINGGI', label: 'Tinggi' },
                        { value: 'NORMAL', label: 'Normal' },
                    ],
                },
            ];
        },
        paketTerurut() {
            return [...this.paket].sort(
                (a, b) => Number(!!b.Is_Terlambat) - Number(!!a.Is_Terlambat) || (a.Persen || 0) - (b.Persen || 0),
            );
        },
        /** Paket yang tersaring menurut input pencarian dan saringan status */
        paketTersaring() {
            let list = [...this.paket];
            if (this.cariPaket && this.cariPaket.trim()) {
                const q = this.cariPaket.toLowerCase().trim();
                list = list.filter((p) => p.Nama_Paket && p.Nama_Paket.toLowerCase().includes(q));
            }
            if (this.filterStatusPaket !== 'all') {
                if (this.filterStatusPaket === 'terlambat') {
                    list = list.filter((p) => !!p.Is_Terlambat);
                } else if (this.filterStatusPaket === 'kritis') {
                    list = list.filter((p) => p.Prioritas === 'KRITIS');
                } else {
                    list = list.filter((p) => p.Status_Paket === this.filterStatusPaket);
                }
            }
            if (this.filterValuesPaket?.prioritas) {
                list = list.filter((p) => p.Prioritas === this.filterValuesPaket.prioritas);
            }

            const activeSort = this.filterValuesPaket?.sort || this.sortPaket;
            if (activeSort === 'tertinggal') {
                list.sort(
                    (a, b) => Number(!!b.Is_Terlambat) - Number(!!a.Is_Terlambat) || (a.Persen || 0) - (b.Persen || 0),
                );
            } else if (activeSort === 'persen_asc') {
                list.sort((a, b) => (a.Persen || 0) - (b.Persen || 0));
            } else if (activeSort === 'persen_desc') {
                list.sort((a, b) => (b.Persen || 0) - (a.Persen || 0));
            } else if (activeSort === 'nama') {
                list.sort((a, b) => (a.Nama_Paket || '').localeCompare(b.Nama_Paket || ''));
            } else if (activeSort === 'tenggat') {
                list.sort((a, b) => (a.Tenggat || '9999').localeCompare(b.Tenggat || '9999'));
            }

            return list;
        },
        countPaket() {
            const p = this.paket || [];

            return {
                all: p.length,
                progress: p.filter((x) => x.Status_Paket === 'PROGRESS').length,
                belum: p.filter((x) => x.Status_Paket === 'BELUM').length,
                selesai: p.filter((x) => x.Status_Paket === 'SELESAI').length,
                terlambat: p.filter((x) => !!x.Is_Terlambat).length,
                kritis: p.filter((x) => x.Prioritas === 'KRITIS').length,
            };
        },
        bisaDiingatkan() {
            return (
                (this.ringkas.terlambat > 0 || this.ringkas.progress > 0 || this.ringkas.belum > 0) &&
                this.paketPerluDiingatkan.length > 0
            );
        },
        paketPerluDiingatkan() {
            return (this.paket || []).filter((p) => p.Status_Paket !== 'SELESAI');
        },
        paketTampilDiModalIngatkan() {
            if (!this.paketDipilihUntukIngatkan || !this.paketDipilihUntukIngatkan.length) {
                return [];
            }
            return (this.paketPerluDiingatkan || []).filter((p) =>
                this.paketDipilihUntukIngatkan.includes(p.Id_SOP_Paket)
            );
        },
        corong() {
            const rb = this.rekamBaca.ringkas || {};

            return [
                { name: '1. Ditugaskan', value: rb.item_unik || 0 },
                { name: '2. Dibuka', value: rb.pernah_dibuka || 0 },
                { name: '3. Konfirmasi', value: rb.dikonfirmasi || 0 },
            ];
        },
        corongPersen() {
            const rb = this.rekamBaca.ringkas || {};
            if (!rb.item_unik) return '—';

            return `${Math.round(((rb.dikonfirmasi || 0) / rb.item_unik) * 100)}%`;
        },
        corongKalimat() {
            const rb = this.rekamBaca.ringkas || {};

            return `${rb.dikonfirmasi || 0} dari ${rb.item_unik || 0} item terkonfirmasi`;
        },
        corongInsight() {
            const rb = this.rekamBaca.ringkas || {};
            if (!rb.item_unik) return '';
            const menggantung = (rb.pernah_dibuka || 0) - (rb.dikonfirmasi || 0);
            if (menggantung > 0) {
                return `${menggantung} item sudah dibuka tapi berhenti sebelum dikonfirmasi — di tahap itulah pembacaan tertahan.`;
            }
            if ((rb.pernah_dibuka || 0) < rb.item_unik) {
                return `${rb.item_unik - (rb.pernah_dibuka || 0)} item belum pernah dibuka sama sekali.`;
            }

            return 'Semua item yang dibuka sudah dikonfirmasi.';
        },
        komposisiStatus() {
            const warnaMap = {
                DIKONFIRMASI: '#10b981',
                SELESAI: '#10b981',
                DIBUKA: '#6366f1',
                BELUM: '#f59e0b',
                DIGANTIKAN: '#94a3b8',
            };

            const total = (this.rekamBaca.komposisi?.status || []).reduce((acc, s) => acc + (Number(s.jumlah) || 0), 0);

            return (this.rekamBaca.komposisi?.status || []).map((s) => {
                const label = (STATUS_ITEM[s.label] || {}).label || s.label;
                const persen = total > 0 ? Math.round(((Number(s.jumlah) || 0) / total) * 100) : 0;
                const color = warnaMap[s.label] || '#6366f1';

                return {
                    name: label,
                    value: Number(s.jumlah) || 0,
                    persen,
                    color,
                    itemStyle: {
                        color,
                        borderRadius: 6,
                        borderColor: '#ffffff',
                        borderWidth: 2,
                    },
                };
            });
        },
        paketBar() {
            const urut = [...this.paket].sort((a, b) => (a.Persen || 0) - (b.Persen || 0)).slice(0, 10);

            return {
                kategori: urut.map((p) => this.potong(p.Nama_Paket, 22)),
                nilai: urut.map((p) => p.Persen || 0),
            };
        },
        /** Peta jam × hari, diturunkan dari stempel waktu rekam baca. */
        peta() {
            const jam = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
            const hari = ['Minggu', 'Sabtu', 'Jumat', 'Kamis', 'Rabu', 'Selasa', 'Senin'];
            const kotak = new Map();
            let total = 0;

            (this.rekamBaca.item || []).forEach((it) => {
                const t = it.Waktu_Konfirmasi || it.Waktu_Buka;
                if (!t) return;
                const d = new Date(String(t).replace(' ', 'T'));
                if (Number.isNaN(d.getTime())) return;
                const y = hari.indexOf(NAMA_HARI[d.getDay()]);
                if (y < 0) return;
                const kunci = `${d.getHours()}|${y}`;
                kotak.set(kunci, (kotak.get(kunci) || 0) + 1);
                total += 1;
            });

            if (!total) return { ada: false, jam, hari, data: [], puncak: '—', insight: '' };

            const data = [...kotak.entries()].map(([k, v]) => {
                const [x, y] = k.split('|').map(Number);

                return [x, y, v];
            });
            const tertinggi = data.reduce((a, b) => (b[2] > a[2] ? b : a), data[0]);
            const diLuarJamKerja = data.filter(([x]) => x < 6 || x >= 20).reduce((n, [, , v]) => n + v, 0);

            return {
                ada: true,
                jam,
                hari,
                data,
                puncak: `${jam[tertinggi[0]]}:00`,
                insight: diLuarJamKerja
                    ? `${diLuarJamKerja} dari ${total} pembacaan terjadi di luar 06:00–20:00. Bukan pelanggaran, tapi pola yang layak ditanyakan.`
                    : `Seluruh ${total} pembacaan terjadi di dalam jam kerja wajar.`,
            };
        },
        /** Tren aktivitas pembacaan dokumen dari waktu ke waktu (Area / Line Chart) */
        trenAktivitas() {
            const items = (this.rekamBaca.item || []).filter((it) => it.Waktu_Konfirmasi);
            if (!items.length) {
                return {
                    ada: false,
                    categories: [],
                    nilai: [],
                    totalKonfirmasi: 0,
                    insight: 'Belum ada catatan aktivitas pembacaan dokumen.',
                };
            }

            // Group by Date (YYYY-MM-DD)
            const mapKonfirmasi = new Map();
            items.forEach((it) => {
                const raw = String(it.Waktu_Konfirmasi || '').trim();
                const d = raw.slice(0, 10);
                if (d && d.length === 10) {
                    mapKonfirmasi.set(d, (mapKonfirmasi.get(d) || 0) + 1);
                }
            });

            // Sort dates ascending
            const sortedDates = [...mapKonfirmasi.keys()].sort();

            const categories = sortedDates.map((d) => {
                try {
                    const dt = new Date(`${d}T00:00:00`);
                    if (!Number.isNaN(dt.getTime())) {
                        return dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                    }
                } catch {
                    /* fallback */
                }

                return d;
            });

            const nilai = sortedDates.map((d) => mapKonfirmasi.get(d) || 0);
            const total = items.length;

            return {
                ada: true,
                categories,
                nilai,
                totalKonfirmasi: total,
                insight: `Total ${total} item dokumen telah selesai dibaca dan dikonfirmasi dalam ${sortedDates.length} hari aktif.`,
            };
        },
        /** Sepuluh konfirmasi tercepat — sinyal "ditandatangani tanpa dibaca". */
        tercepat() {
            const semua = (this.rekamBaca.item || []).filter(
                (it) => it.Waktu_Konfirmasi && Number.isFinite(it.Durasi_Aktif_Detik),
            );
            const baris = [...semua].sort((a, b) => a.Durasi_Aktif_Detik - b.Durasi_Aktif_Detik).slice(0, 10);
            if (!baris.length) return { ada: false };

            const mencurigakan = semua.filter((it) => it.Durasi_Aktif_Detik < 15).length;

            return {
                ada: true,
                kategori: baris.map((it) => this.potong(it.Judul_Item, 30)),
                nilai: baris.map((it) => it.Durasi_Aktif_Detik),
                jumlahMencurigakan: mencurigakan,
                insight: mencurigakan
                    ? `${mencurigakan} item dikonfirmasi dalam kurang dari 15 detik. Durasi sesingkat itu tak cukup untuk membaca satu halaman.`
                    : 'Tak ada konfirmasi yang selesai di bawah 15 detik.',
            };
        },
        countRiwayat() {
            const list = this.riwayat || [];
            return {
                all: list.length,
                selesai: list.filter((r) => r.Peristiwa === 'SELESAI').length,
                dibuka: list.filter((r) => r.Peristiwa === 'DIBUKA_LAGI').length,
                dicabut: list.filter((r) => r.Peristiwa === 'DICABUT').length,
            };
        },
        quickChipsRiwayat() {
            return [
                { value: 'all', label: 'Semua Peristiwa', badge: this.countRiwayat.all, color: 'brand' },
                { value: 'SELESAI', label: 'Tuntas / Selesai', badge: this.countRiwayat.selesai, color: 'success', icon: 'check-circle-fill' },
                { value: 'DIBUKA_LAGI', label: 'Dibuka Kembali', badge: this.countRiwayat.dibuka, color: 'warning', icon: 'arrow-counterclockwise' },
                { value: 'DICABUT', label: 'Akses Dicabut', badge: this.countRiwayat.dicabut, color: 'danger', icon: 'slash-circle-fill' },
            ];
        },
        riwayatTersaring() {
            let list = this.riwayat || [];
            if (this.filterPeristiwaRiwayat !== 'all') {
                list = list.filter((r) => r.Peristiwa === this.filterPeristiwaRiwayat);
            }
            if (this.cariRiwayat && this.cariRiwayat.trim()) {
                const q = this.cariRiwayat.trim().toLowerCase();
                list = list.filter((r) =>
                    (r.Nama_Paket && r.Nama_Paket.toLowerCase().includes(q)) ||
                    (r.Aktor && r.Aktor.toLowerCase().includes(q)) ||
                    (r.Catatan && r.Catatan.toLowerCase().includes(q)) ||
                    (this.sebabTeks(r) && this.sebabTeks(r).toLowerCase().includes(q)) ||
                    (r.Peristiwa && r.Peristiwa.toLowerCase().includes(q))
                );
            }
            return list;
        },
        jejakWaktu() {
            return this.riwayatTersaring.map((r, i) => {
                const meta = [];
                if (this.sebabTeks(r)) meta.push({ label: 'Sebab', value: this.sebabTeks(r) });
                if (r.Persen !== null && r.Persen !== undefined) {
                    meta.push({ label: 'Kemajuan saat itu', value: `${Math.round(r.Persen)}%` });
                }
                if (r.Catatan) meta.push({ label: 'Catatan', value: r.Catatan });

                return {
                    id: i,
                    title: this.peristiwaLabel(r.Peristiwa),
                    name: r.Nama_Paket,
                    department: r.Prioritas || 'Tanpa prioritas',
                    role: r.Aktor || 'Tercatat sistem',
                    status: this.statusJejak(r.Peristiwa),
                    time: r.Tanggal,
                    content: this.peristiwaKalimat(r.Peristiwa),
                    meta,
                };
            });
        },
    },
    mounted() {
        this.fetchRingkas();
        this.fetchPaket();
        this.fetchRiwayat();
        this.fetchRekamBaca();
    },
    methods: {
        peristiwaIcon(p) {
            return {
                SELESAI: 'bi-check-circle-fill',
                DIBUKA_LAGI: 'bi-arrow-counterclockwise',
                DICABUT: 'bi-slash-circle-fill',
            }[p] || 'bi-clock-history';
        },
        inisial(nama) {
            if (!nama) return 'KP';
            const p = String(nama).trim().split(/\s+/);
            if (p.length === 1) return p[0].slice(0, 2).toUpperCase();

            return (p[0][0] + p[p.length - 1][0]).toUpperCase();
        },
        potong(teks, n) {
            const s = String(teks || '—');

            return s.length > n ? `${s.slice(0, n - 1)}…` : s;
        },
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s;
        },
        statusVariant(s) {
            return (STATUS_PAKET[s] || {}).variant || 'idle';
        },
        peristiwaLabel(p) {
            return (PERISTIWA[p] || {}).label || p;
        },
        peristiwaKalimat(p) {
            return (PERISTIWA[p] || {}).kalimat || '';
        },
        sebabTeks(r) {
            return labelSebab(r.Sebab) || labelSebab(r.Alasan);
        },
        statusJejak(p) {
            return { SELESAI: 'selesai', DIBUKA_LAGI: 'warning', DICABUT: 'batal' }[p] || 'info';
        },
        async fetchRekamBaca() {
            this.loading.rekam = true;
            try {
                const r = await axios.get(`${API}/karyawan/${this.kode}/rekam-baca`);
                if (r.data.success) this.rekamBaca = r.data.result;
            } catch (e) {
                /* diam */
            } finally {
                this.loading.rekam = false;
            }
        },
        async fetchRingkas() {
            this.loading.ringkas = true;
            try {
                const r = await axios.get(`${API}/karyawan/${this.kode}/ringkas`);
                if (r.data.success) this.ringkas = r.data.result.karyawan || {};
            } catch (e) {
                /* diam */
            } finally {
                this.loading.ringkas = false;
            }
        },
        async fetchPaket() {
            this.loading.paket = true;
            try {
                const r = await axios.get(`${API}/karyawan/${this.kode}/paket`);
                this.paket = r.data.success ? r.data.result.items || [] : [];
            } catch (e) {
                this.paket = [];
            } finally {
                this.loading.paket = false;
            }
        },
        async fetchRiwayat() {
            this.loading.riwayat = true;
            try {
                const r = await axios.get(`${API}/karyawan/${this.kode}/riwayat`);
                const hasil = r.data.success ? r.data.result || {} : {};
                this.riwayat = hasil.baris || [];
                this.riwayatTersembunyi = hasil.tersembunyi || 0;
            } catch (e) {
                this.riwayat = [];
                this.riwayatTersembunyi = 0;
            } finally {
                this.loading.riwayat = false;
            }
        },
        async muatUlangSemua() {
            this.isRefreshing = true;
            try {
                await Promise.all([
                    this.fetchRingkas(),
                    this.fetchPaket(),
                    this.fetchRiwayat(),
                    this.fetchRekamBaca(),
                ]);
                ElMessage.success('Data kepatuhan berhasil diperbarui');
            } catch (e) {
                ElMessage.error('Gagal memperbarui data');
            } finally {
                this.isRefreshing = false;
            }
        },
        async exportAtestasi() {
            try {
                const r = await axios.post(`${API}/export/request`, {
                    jenis: 'atestasi',
                    format: 'xlsx',
                    nama: `BuktiKonfirmasi_${this.kode}`,
                    kode_karyawan: this.kode,
                });
                ElMessage.success(r.data.message || 'Export diproses.');
                useActivityCenter().notifyNewTask();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memulai export');
            }
        },
        bukaModalIngatkan() {
            this.paketDipilihUntukIngatkan = (this.paketPerluDiingatkan || []).map((p) => p.Id_SOP_Paket);
            this.modalIngatkan = true;
        },
        pilihSemuaPaketIngatkan() {
            this.paketDipilihUntukIngatkan = (this.paketPerluDiingatkan || []).map((p) => p.Id_SOP_Paket);
        },
        kosongkanPaketIngatkan() {
            this.paketDipilihUntukIngatkan = [];
        },
        async kirimPengingat() {
            if (!this.paketDipilihUntukIngatkan || !this.paketDipilihUntukIngatkan.length) {
                ElMessage.warning('Pilih minimal satu paket dokumen yang ingin diingatkan.');
                return;
            }
            this.loadingIngatkan = true;
            try {
                const payload = {
                    kode_karyawan: [this.kode],
                    id_paket: this.paketDipilihUntukIngatkan,
                };
                const r = await axios.post(`${API}/ingatkan`, payload);
                if (r.data.success) {
                    ElMessage.success(r.data.message || 'Pengingat berhasil dikirim.');
                    this.modalIngatkan = false;
                    this.paketDipilihUntukIngatkan = [];
                } else {
                    ElMessage.warning(r.data.message || 'Gagal mengirim pengingat');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengirim pengingat');
            } finally {
                this.loadingIngatkan = false;
            }
        },
        onFilterChangePaket({ key, value }) {
            this.filterValuesPaket = { ...this.filterValuesPaket, [key]: value };
            if (key === 'sort') {
                this.sortPaket = value;
            }
        },
        onResetFiltersPaket() {
            this.filterValuesPaket = { sort: 'tertinggal', prioritas: '' };
            this.sortPaket = 'tertinggal';
            this.filterStatusPaket = 'all';
            this.cariPaket = '';
        },
    },
};
</script>

<style scoped>
.mk-page {
    padding-bottom: 2.5rem;
}

.mk-zona {
    animation: mkFadeUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes mkFadeUp {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

/* ══ Header & Profil Customization ══ */
.mk-header {
    margin-bottom: 1rem;
}

/* ══ Metadata Console Strip ══ */
.mk-meta-strip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 0.75rem;
    margin-top: 0.6rem;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.mk-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.65rem;
    border-radius: 8px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-800);
    font-size: 0.76rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.mk-meta-chip-label {
    color: var(--hcis-slate-500);
    font-weight: 500;
}

.mk-meta-chip-val {
    color: var(--hcis-slate-900);
    font-weight: 700;
}



/* ══ Paket Cards Grid ══ */
.mk-paket-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

/* ══ Custom Document Audit Trail Timeline ══ */
.mk-audit-stream {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
    padding: 0.5rem 0;
}

.mk-audit-node {
    display: flex;
    gap: 1.25rem;
    position: relative;
}

.mk-audit-marker-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 36px;
    flex-shrink: 0;
}

.mk-audit-jewel {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    z-index: 2;
    background: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
}

.mk-audit-jewel.jewel-success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 2px solid var(--hcis-emerald-200);
}

.mk-audit-jewel.jewel-warning {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
    border: 2px solid var(--hcis-amber-200);
}

.mk-audit-jewel.jewel-danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-600);
    border: 2px solid var(--hcis-red-200);
}

.mk-audit-jewel.jewel-brand {
    background: #eff6ff;
    color: var(--hcis-blue-600);
    border: 2px solid #bfdbfe;
}

.mk-audit-rail {
    flex-grow: 1;
    width: 2px;
    background: var(--hcis-slate-200);
    margin: 4px 0;
}

.mk-audit-card {
    flex-grow: 1;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 1rem 1.15rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.mk-audit-card:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.mk-audit-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.mk-audit-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.74rem;
    color: var(--hcis-slate-600);
}

.mk-audit-meta-item .lbl {
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.mk-audit-note {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

/* ══ Reminder List Items (Inside Dialog) ══ */
.mk-reminder-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.5rem;
    background: var(--hcis-slate-50);
}

.mk-reminder-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--hcis-slate-100);
}

/* ══ Nota Alert ══ */
.mk-nota {
    margin: 1rem 0 0;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--hcis-slate-500);
}

.mk-skeleton {
    padding: 0.25rem 0;
}
</style>

