<template>
    <div class="container-fluid px-0 sop-scope mk-page">
        <!-- ══ Header Halaman Eksekutif (HcisHeader) ══ -->
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="lg"
            :title="karyawanNama || kode"
            kicker="AUDIT KEPATUHAN DOKUMEN INDIVIDUAL"
            icon="person-badge-fill"
            :badge="karyawanData?.Jabatan || 'Karyawan'"
            badge-variant="brand"
            class="mb-4 mk-header"
        >
            <template #breadcrumb>
                <HcisBreadcrumb :items="breadcrumb" size="sm" />
            </template>

            <!-- Sorotan Paket Dokumen yang Diaudit -->
            <template #subtitle>
                <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                    <span class="text-muted small">Paket Dokumen:</span>
                    <HcisBadge
                        :label="detail?.paket?.Nama_Paket || 'Memuat Dokumen...'"
                        variant="brand"
                        size="md"
                        icon="file-earmark-ruled-fill"
                    />
                    <HcisBadge
                        v-if="detail?.paket?.Kategori"
                        :label="detail.paket.Kategori"
                        variant="neutral"
                        appearance="outline"
                        size="sm"
                        icon="tag-fill"
                    />
                </div>
            </template>

            <template #meta>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisBadge
                        v-if="karyawanData?.No_Induk_Karyawan"
                        :label="`NIK: ${karyawanData.No_Induk_Karyawan}`"
                        variant="neutral"
                        appearance="outline"
                        size="sm"
                        icon="card-text"
                    />
                    <HcisBadge
                        v-if="karyawanData?.Departemen || karyawanData?.Divisi"
                        :label="[karyawanData.Departemen, karyawanData.Divisi].filter(Boolean).join(' · ')"
                        variant="neutral"
                        appearance="outline"
                        size="sm"
                        icon="building"
                    />
                    <HcisBadge
                        v-if="karyawanData?.Lokasi"
                        :label="karyawanData.Lokasi"
                        variant="neutral"
                        appearance="outline"
                        size="sm"
                        icon="geo-alt"
                    />
                    <HcisBadge
                        v-if="detail?.paket?.Prioritas"
                        :label="detail.paket.Prioritas"
                        :variant="detail.paket.Prioritas === 'KRITIS' ? 'danger' : (detail.paket.Prioritas === 'TINGGI' ? 'warning' : 'neutral')"
                        size="sm"
                        icon="flag-fill"
                    />
                    <HcisBadge
                        v-if="detail?.paket?.Flag_Absensi === 'Y'"
                        label="Wajib Presensi"
                        variant="info"
                        size="sm"
                        icon="camera-fill"
                    />
                </div>
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        :href="`/sop/admin/paket/${idPaket}`"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        label="Kembali ke Paket"
                    />
                    <HcisButton
                        :href="`/sop/admin/monitoring/karyawan/${token}`"
                        variant="secondary"
                        size="sm"
                        leading-icon="person-lines-fill"
                        label="Semua Dokumen Karyawan"
                    />
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        icon-only
                        leading-icon="arrow-clockwise"
                        :loading="loading || isRefreshing"
                        title="Muat ulang data"
                        aria-label="Muat ulang"
                        @click="muatUlang"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ Peringatan Mode Admin (HcisContextBanner) ══ -->
        <HcisContextBanner
            class="mb-4"
            variant="glass"
            sentiment="brand"
            title="Mode Audit Pengawasan Individual"
            subtitle="Halaman ini menyajikan log kepatuhan milik karyawan terkait. Meninjau item di sini bersifat read-only dan tidak akan mempengaruhi catatan progres karyawan."
            kicker="HAK AKSES ADMINISTRATOR"
            icon="shield-lock-fill"
            scope-variant="brand"
            scope-label="Read Only Audit"
        />

        <!-- ══ Banner Peringatan Perubahan Versi (Jika Ada) ══ -->
        <HcisContextBanner
            v-if="detail?.banner_perubahan"
            class="mb-4"
            variant="glass"
            sentiment="warning"
            title="Pemberitahuan Pembaruan Versi Dokumen"
            :subtitle="detail.banner_perubahan.pesan || detail.banner_perubahan"
            kicker="PERUBAHAN VERSI"
            icon="arrow-repeat"
            scope-variant="warning"
            scope-label="Versi Diperbarui"
        />

        <!-- ══ Loading Skeleton State ══ -->
        <div v-if="loading && !detail" class="my-4">
            <div class="row g-3 mb-4">
                <div v-for="n in 4" :key="n" class="col-12 col-sm-6 col-lg-3">
                    <HcisSkeleton type="card" height="108px" rounded="xl" />
                </div>
            </div>
            <HcisSkeleton type="card" height="340px" rounded="xl" />
        </div>

        <!-- ══ Empty State (Jika Data Tidak Ditemukan) ══ -->
        <HcisEmptyState
            v-else-if="!detail"
            title="Dokumen Tidak Ditemukan"
            description="Paket ini mungkin sudah dihapus atau penugasannya dicabut dari karyawan."
            icon="exclamation-triangle"
            variant="dashed"
            sentiment="warning"
            class="my-5"
        >
            <template #actions>
                <HcisButton
                    variant="primary"
                    size="sm"
                    leading-icon="arrow-left"
                    label="Kembali ke Profil Karyawan"
                    @click="kembaliKeKaryawan"
                />
            </template>
        </HcisEmptyState>

        <!-- ══ Konten Utama Monitoring Paket ══ -->
        <template v-else>
            <!-- ══ KPI Strip Eksekutif (HcisStatGroup - Mode Compact) ══ -->
            <HcisStatGroup :stats="statistik" :cols="4" gap="sm" layout="compact" appearance="crystal" class="mb-4 mk-stats-group" />

            <!-- ══ Tab Bar Navigasi (HcisTabs) ══ -->
            <div class="mk-tabs-wrap mb-4">
                <HcisTabs v-model="tab" :tabs="tabs" variant="segmented" class="mk-tabs-bar" />
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 1: RINCIAN ITEM PROSEDUR & RIWAYAT PEMBACAAN
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'items'" class="mk-zona">
                <!-- ══ CARD UTAMA RINCIAN ITEM PROSEDUR ══ -->
                <HcisCard
                    size="sm"
                    variant="glass"
                    sentiment="brand"
                    icon="list-check"
                    title="Daftar Prosedur & Catatan Aktivitas"
                    description="Rincian status baca, waktu interaksi, dan bukti otentikasi per item dokumen"
                    :badge="`${itemsTersaring.length} dari ${detail.items?.length || 0} Prosedur`"
                    badge-variant="brand"
                    class="mb-4"
                >
                    <!-- Cockpit Filter & Search Bar -->
                    <div class="mb-3">
                        <HcisFilterBar
                            v-model:search="cariItem"
                            v-model:active-chip="filterStatusItem"
                            :chips="quickChipsItems"
                            :filters="filtersConfigItems"
                            :filter-values="filterValuesItem"
                            :view-mode="viewModeItem"
                            :available-view-modes="['table', 'grid']"
                            search-placeholder="Cari judul item, nomor dokumen, pengesahan, atau bab..."
                            @filter-change="onFilterChangeItem"
                            @reset="onResetFiltersItem"
                            @view-mode-change="(m) => viewModeItem = m"
                        >
                            <template #extra-actions>
                                <button
                                    v-if="hasSections"
                                    type="button"
                                    class="btn btn-sm d-flex align-items-center gap-1.5 fw-semibold"
                                    :class="kelompokkanBab ? 'btn-primary' : 'btn-outline-secondary'"
                                    @click="kelompokkanBab = !kelompokkanBab"
                                >
                                    <i class="bi bi-diagram-2"></i>
                                    <span>{{ kelompokkanBab ? 'Dikelompokkan per Bab' : 'Kelompokkan Bab' }}</span>
                                </button>
                            </template>
                        </HcisFilterBar>
                    </div>

                    <!-- Empty State saat filter tidak menemukan hasil -->
                    <HcisEmptyState
                        v-if="!itemsTersaring.length"
                        title="Tidak ada item prosedur yang cocok"
                        description="Coba sesuaikan kata kunci pencarian atau ganti filter saringan status di atas."
                        icon="search"
                        variant="dashed"
                        sentiment="neutral"
                        class="my-4"
                    />

                    <!-- TAMPILAN 1: MODE TABEL AUDIT DETAIL PRESISI -->
                    <div v-else-if="viewModeItem === 'table'" class="table-responsive mk-table-wrap">
                        <table class="table mk-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 48px" class="text-center">No</th>
                                    <th>Item Prosedur &amp; Dokumen</th>
                                    <th style="width: 180px">Status &amp; Nilai</th>
                                    <th style="width: 175px">Kemajuan &amp; Durasi</th>
                                    <th style="width: 185px">Waktu Aktivitas</th>
                                    <th style="width: 165px">Jejak Digital</th>
                                    <th style="width: 75px" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- JIKA DIKELOMPOKKAN PER BAB / SECTION -->
                                <template v-if="kelompokkanBab && hasSections">
                                    <template v-for="sec in sectionsGrouped" :key="sec.key">
                                        <!-- Header Row Bab / Section -->
                                        <tr class="mk-sec-row">
                                            <td colspan="7">
                                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-1">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="mk-sec-badge"><i class="bi bi-folder-fill text-primary"></i></span>
                                                        <div>
                                                            <span class="fw-bold text-dark fs-6">{{ sec.nama }}</span>
                                                            <span v-if="sec.deskripsi" class="text-muted small ms-2 d-none d-md-inline">{{ sec.deskripsi }}</span>
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill small fw-semibold">
                                                        {{ sec.itemsSelesai }} dari {{ sec.items.length }} Selesai
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Item Rows dalam Bab ini -->
                                        <tr v-for="(it, idx) in sec.items" :key="it.Id_SOP_Paket_Item" class="mk-table-row">
                                            <!-- No -->
                                            <td class="text-center align-middle">
                                                <div class="d-inline-flex flex-column align-items-center gap-1">
                                                    <span class="mk-row-idx">{{ idx + 1 }}</span>
                                                    <span v-if="it.Terkunci" class="text-muted" title="Terkunci urutan" style="font-size: 0.65rem">
                                                        <i class="bi bi-lock-fill text-secondary"></i>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Item Prosedur & Dokumen -->
                                            <td class="align-middle">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="mk-item-ico-wrap mt-1">
                                                        <ItemTypeIcon :tipe="it.Tipe_Item" />
                                                    </div>
                                                    <div class="min-w-0 flex-grow-1">
                                                        <div
                                                            class="mk-item-title"
                                                            :title="it.Judul_Item"
                                                            @click="bukaDetailItem(it)"
                                                        >
                                                            {{ it.Judul_Item }}
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                                            <HcisBadge
                                                                :variant="it.Wajib ? 'warning' : 'neutral'"
                                                                size="xs"
                                                                :icon="it.Wajib ? 'exclamation-circle-fill' : undefined"
                                                            >
                                                                {{ it.Wajib ? 'Wajib' : 'Opsional' }}
                                                            </HcisBadge>
                                                            <HcisBadge
                                                                v-if="it.Nomor_SOP_Resmi"
                                                                variant="neutral"
                                                                size="xs"
                                                                icon="file-earmark-text"
                                                            >
                                                                {{ it.Nomor_SOP_Resmi }}
                                                            </HcisBadge>
                                                            <HcisBadge
                                                                v-if="it.Versi !== null && it.Versi !== undefined"
                                                                variant="brand"
                                                                size="xs"
                                                            >
                                                                {{ $vlabel(it.Versi) }}
                                                            </HcisBadge>
                                                        </div>
                                                        <div v-if="barisPengesahan(it)" class="mk-legal-line mt-2 d-flex align-items-center gap-2 text-truncate" :title="barisPengesahan(it)">
                                                            <i class="bi bi-patch-check-fill text-success flex-shrink-0"></i>
                                                            <span class="text-truncate">{{ barisPengesahan(it) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Status & Nilai -->
                                            <td class="align-middle">
                                                <div class="d-flex flex-column gap-2">
                                                    <div>
                                                        <StatusPill
                                                            :text="statusItem(it.Status)"
                                                            :variant="variantItem(it.Status)"
                                                            :dot="false"
                                                        />
                                                    </div>
                                                    <!-- Kuis / Ujian Score Box -->
                                                    <div v-if="it.Skor_Quiz != null || it.Jumlah_Percobaan > 0" class="mk-quiz-chip">
                                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                                            <span class="mk-quiz-score">
                                                                Nilai: <strong>{{ it.Skor_Quiz != null ? it.Skor_Quiz : '—' }}</strong><span v-if="it.Nilai_Minimum != null" class="text-muted">/{{ it.Nilai_Minimum }}</span>
                                                            </span>
                                                            <span
                                                                class="mk-quiz-status"
                                                                :class="it.Status_Lulus === 'Y' ? 'text-success' : 'text-danger'"
                                                            >
                                                                {{ it.Status_Lulus === 'Y' ? 'Lulus' : 'Belum' }}
                                                            </span>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-between text-muted mk-quiz-sub mt-1">
                                                            <span>Percobaan {{ it.Jumlah_Percobaan || 1 }}<span v-if="it.Maks_Percobaan">/{{ it.Maks_Percobaan }}</span></span>
                                                            <span v-if="it.Kuis_Menunggu" class="text-warning-emphasis fw-bold">Menunggu</span>
                                                        </div>
                                                    </div>
                                                    <!-- Keberlakuan Status -->
                                                    <div v-else-if="it.Keberlakuan">
                                                        <HcisBadge
                                                            :variant="it.Keberlakuan.status === 'BERLAKU' ? 'success' : (it.Keberlakuan.status === 'VERSI_LAMA' ? 'danger' : 'warning')"
                                                            size="xs"
                                                            :title="it.Keberlakuan.alasan"
                                                        >
                                                            {{ labelKeberlakuan(it.Keberlakuan.status) }}
                                                        </HcisBadge>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Kemajuan & Durasi (Premium Capsule Layout) -->
                                            <td class="align-middle">
                                                <div class="mk-metric-cell">
                                                    <!-- Baris Atas: Pill Durasi & Persentase/Halaman -->
                                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                        <div class="mk-time-pill" :class="{ 'has-value': !!it.Durasi_Detik }">
                                                            <i class="bi bi-stopwatch-fill me-1" :class="it.Durasi_Detik ? 'text-primary' : 'text-muted'"></i>
                                                            <span>{{ it.Durasi_Detik ? durasi(it.Durasi_Detik) : '0 dtk' }}</span>
                                                        </div>
                                                        <span
                                                            class="mk-pct-pill"
                                                            :class="{
                                                                'pct-done': itemPersen(it) === 100,
                                                                'pct-active': itemPersen(it) > 0 && itemPersen(it) < 100,
                                                                'pct-zero': itemPersen(it) === 0
                                                            }"
                                                        >
                                                            <i v-if="itemPersen(it) === 100" class="bi bi-check me-0.5"></i>
                                                            {{ itemProgressLabel(it) }}
                                                        </span>
                                                    </div>

                                                    <!-- Meter Bar Halus & Bergradasi -->
                                                    <div class="mk-meter-track">
                                                        <div
                                                            class="mk-meter-fill"
                                                            :class="{
                                                                'fill-done': itemPersen(it) === 100,
                                                                'fill-active': itemPersen(it) > 0 && itemPersen(it) < 100,
                                                            }"
                                                            :style="{ width: `${itemPersen(it)}%` }"
                                                        ></div>
                                                    </div>

                                                    <!-- Micro Footnote: Selang Waktu Pengerjaan -->
                                                    <div v-if="rentang(it) !== '—'" class="mk-span-footnote mt-1">
                                                        <i class="bi bi-hourglass-split me-1 text-secondary"></i>
                                                        <span>Selang: {{ rentang(it) }}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Waktu Aktivitas -->
                                            <td class="align-middle">
                                                <div class="d-flex flex-column gap-2">
                                                    <div v-if="it.Waktu_Buka" class="d-flex align-items-center gap-2 mk-time-entry" title="Waktu Pertama Dibuka">
                                                        <i class="bi bi-box-arrow-in-right text-info flex-shrink-0"></i>
                                                        <span class="mk-time-val">{{ fmt(it.Waktu_Buka) }}</span>
                                                    </div>
                                                    <div v-if="it.Waktu_Konfirmasi" class="d-flex align-items-center gap-2 mk-time-entry" title="Waktu Dikonfirmasi Selesai">
                                                        <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                                        <span class="mk-time-val">{{ fmt(it.Waktu_Konfirmasi) }}</span>
                                                    </div>
                                                    <span v-if="!it.Waktu_Buka && !it.Waktu_Konfirmasi" class="text-muted small">—</span>
                                                </div>
                                            </td>

                                            <!-- Jejak Digital -->
                                            <td class="align-middle">
                                                <div class="d-flex flex-column gap-2">
                                                    <div v-if="it.Konfirmasi_IP" class="d-flex align-items-center gap-2 mk-ip-entry">
                                                        <i class="bi bi-hdd-network text-secondary flex-shrink-0"></i>
                                                        <span class="mk-ip-val">{{ it.Konfirmasi_IP }}</span>
                                                    </div>
                                                    <div
                                                        v-if="it.Konfirmasi_User_Agent"
                                                        class="d-flex align-items-center gap-2 mk-ua-entry text-truncate"
                                                        style="max-width: 150px;"
                                                        :title="it.Konfirmasi_User_Agent"
                                                    >
                                                        <i class="bi bi-laptop text-secondary flex-shrink-0"></i>
                                                        <span class="text-truncate mk-ua-val">{{ ringkasUA(it.Konfirmasi_User_Agent) }}</span>
                                                    </div>
                                                    <span v-if="!it.Konfirmasi_IP && !it.Konfirmasi_User_Agent" class="text-muted small">—</span>
                                                </div>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-center align-middle">
                                                <HcisButton
                                                    label="Detail"
                                                    variant="secondary"
                                                    size="xs"
                                                    trailing-icon="chevron-right"
                                                    pill
                                                    @click="bukaDetailItem(it)"
                                                />
                                            </td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- JIKA TAMPILAN CONTINUOUS / FLAT -->
                                <template v-else>
                                    <tr v-for="(it, idx) in itemsTersaring" :key="it.Id_SOP_Paket_Item" class="mk-table-row">
                                        <!-- No -->
                                        <td class="text-center align-middle">
                                            <div class="d-inline-flex flex-column align-items-center gap-1">
                                                <span class="mk-row-idx">{{ idx + 1 }}</span>
                                                <span v-if="it.Terkunci" class="text-muted" title="Terkunci urutan" style="font-size: 0.65rem">
                                                    <i class="bi bi-lock-fill text-secondary"></i>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Item Prosedur & Dokumen -->
                                        <td class="align-middle">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="mk-item-ico-wrap mt-1">
                                                    <ItemTypeIcon :tipe="it.Tipe_Item" />
                                                </div>
                                                <div class="min-w-0 flex-grow-1">
                                                    <div
                                                        class="mk-item-title"
                                                        :title="it.Judul_Item"
                                                        @click="bukaDetailItem(it)"
                                                    >
                                                        {{ it.Judul_Item }}
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                                        <HcisBadge
                                                            :variant="it.Wajib ? 'warning' : 'neutral'"
                                                            size="xs"
                                                            :icon="it.Wajib ? 'exclamation-circle-fill' : undefined"
                                                        >
                                                            {{ it.Wajib ? 'Wajib' : 'Opsional' }}
                                                        </HcisBadge>
                                                        <HcisBadge
                                                            v-if="it.Nomor_SOP_Resmi"
                                                            variant="neutral"
                                                            size="xs"
                                                            icon="file-earmark-text"
                                                        >
                                                            {{ it.Nomor_SOP_Resmi }}
                                                        </HcisBadge>
                                                        <HcisBadge
                                                            v-if="it.Versi !== null && it.Versi !== undefined"
                                                            variant="brand"
                                                            size="xs"
                                                        >
                                                            {{ $vlabel(it.Versi) }}
                                                        </HcisBadge>
                                                        <HcisBadge
                                                            v-if="it.Nama_Section"
                                                            variant="neutral"
                                                            size="xs"
                                                            icon="folder"
                                                        >
                                                            {{ it.Nama_Section }}
                                                        </HcisBadge>
                                                    </div>
                                                    <div v-if="barisPengesahan(it)" class="mk-legal-line mt-2 d-flex align-items-center gap-2 text-truncate" :title="barisPengesahan(it)">
                                                        <i class="bi bi-patch-check-fill text-success flex-shrink-0"></i>
                                                        <span class="text-truncate">{{ barisPengesahan(it) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status & Nilai -->
                                        <td class="align-middle">
                                            <div class="d-flex flex-column gap-2">
                                                <div>
                                                    <StatusPill
                                                        :text="statusItem(it.Status)"
                                                        :variant="variantItem(it.Status)"
                                                        :dot="false"
                                                    />
                                                </div>
                                                <!-- Kuis / Ujian Score Box -->
                                                <div v-if="it.Skor_Quiz != null || it.Jumlah_Percobaan > 0" class="mk-quiz-chip">
                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                        <span class="mk-quiz-score">
                                                            Nilai: <strong>{{ it.Skor_Quiz != null ? it.Skor_Quiz : '—' }}</strong><span v-if="it.Nilai_Minimum != null" class="text-muted">/{{ it.Nilai_Minimum }}</span>
                                                        </span>
                                                        <span
                                                            class="mk-quiz-status"
                                                            :class="it.Status_Lulus === 'Y' ? 'text-success' : 'text-danger'"
                                                        >
                                                            {{ it.Status_Lulus === 'Y' ? 'Lulus' : 'Belum' }}
                                                        </span>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between text-muted mk-quiz-sub mt-1">
                                                        <span>Percobaan {{ it.Jumlah_Percobaan || 1 }}<span v-if="it.Maks_Percobaan">/{{ it.Maks_Percobaan }}</span></span>
                                                        <span v-if="it.Kuis_Menunggu" class="text-warning-emphasis fw-bold">Menunggu</span>
                                                    </div>
                                                </div>
                                                <!-- Keberlakuan Status -->
                                                <div v-else-if="it.Keberlakuan">
                                                    <HcisBadge
                                                        :variant="it.Keberlakuan.status === 'BERLAKU' ? 'success' : (it.Keberlakuan.status === 'VERSI_LAMA' ? 'danger' : 'warning')"
                                                        size="xs"
                                                        :title="it.Keberlakuan.alasan"
                                                    >
                                                        {{ labelKeberlakuan(it.Keberlakuan.status) }}
                                                    </HcisBadge>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Kemajuan & Durasi (Premium Capsule Layout) -->
                                        <td class="align-middle">
                                            <div class="mk-metric-cell">
                                                <!-- Baris Atas: Pill Durasi & Persentase/Halaman -->
                                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                    <div class="mk-time-pill" :class="{ 'has-value': !!it.Durasi_Detik }">
                                                        <i class="bi bi-stopwatch-fill me-1" :class="it.Durasi_Detik ? 'text-primary' : 'text-muted'"></i>
                                                        <span>{{ it.Durasi_Detik ? durasi(it.Durasi_Detik) : '0 dtk' }}</span>
                                                    </div>
                                                    <span
                                                        class="mk-pct-pill"
                                                        :class="{
                                                            'pct-done': itemPersen(it) === 100,
                                                            'pct-active': itemPersen(it) > 0 && itemPersen(it) < 100,
                                                            'pct-zero': itemPersen(it) === 0
                                                        }"
                                                    >
                                                        <i v-if="itemPersen(it) === 100" class="bi bi-check me-0.5"></i>
                                                        {{ itemProgressLabel(it) }}
                                                    </span>
                                                </div>

                                                <!-- Meter Bar Halus & Bergradasi -->
                                                <div class="mk-meter-track">
                                                    <div
                                                        class="mk-meter-fill"
                                                        :class="{
                                                            'fill-done': itemPersen(it) === 100,
                                                            'fill-active': itemPersen(it) > 0 && itemPersen(it) < 100,
                                                        }"
                                                        :style="{ width: `${itemPersen(it)}%` }"
                                                    ></div>
                                                </div>

                                                <!-- Micro Footnote: Selang Waktu Pengerjaan -->
                                                <div v-if="rentang(it) !== '—'" class="mk-span-footnote mt-1">
                                                    <i class="bi bi-hourglass-split me-1 text-secondary"></i>
                                                    <span>Selang: {{ rentang(it) }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Waktu Aktivitas -->
                                        <td class="align-middle">
                                            <div class="d-flex flex-column gap-2">
                                                <div v-if="it.Waktu_Buka" class="d-flex align-items-center gap-2 mk-time-entry" title="Waktu Pertama Dibuka">
                                                    <i class="bi bi-box-arrow-in-right text-info flex-shrink-0"></i>
                                                    <span class="mk-time-val">{{ fmt(it.Waktu_Buka) }}</span>
                                                </div>
                                                <div v-if="it.Waktu_Konfirmasi" class="d-flex align-items-center gap-2 mk-time-entry" title="Waktu Dikonfirmasi Selesai">
                                                    <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                                    <span class="mk-time-val">{{ fmt(it.Waktu_Konfirmasi) }}</span>
                                                </div>
                                                <span v-if="!it.Waktu_Buka && !it.Waktu_Konfirmasi" class="text-muted small">—</span>
                                            </div>
                                        </td>

                                        <!-- Jejak Digital -->
                                        <td class="align-middle">
                                            <div class="d-flex flex-column gap-2">
                                                <div v-if="it.Konfirmasi_IP" class="d-flex align-items-center gap-2 mk-ip-entry">
                                                    <i class="bi bi-hdd-network text-secondary flex-shrink-0"></i>
                                                    <span class="mk-ip-val">{{ it.Konfirmasi_IP }}</span>
                                                </div>
                                                <div
                                                    v-if="it.Konfirmasi_User_Agent"
                                                    class="d-flex align-items-center gap-2 mk-ua-entry text-truncate"
                                                    style="max-width: 150px;"
                                                    :title="it.Konfirmasi_User_Agent"
                                                >
                                                    <i class="bi bi-laptop text-secondary flex-shrink-0"></i>
                                                    <span class="text-truncate mk-ua-val">{{ ringkasUA(it.Konfirmasi_User_Agent) }}</span>
                                                </div>
                                                <span v-if="!it.Konfirmasi_IP && !it.Konfirmasi_User_Agent" class="text-muted small">—</span>
                                            </div>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="text-center align-middle">
                                            <HcisButton
                                                label="Detail"
                                                variant="secondary"
                                                size="xs"
                                                trailing-icon="chevron-right"
                                                pill
                                                @click="bukaDetailItem(it)"
                                            />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- TAMPILAN 2: MODE ALUR KARTU PROSEDUR (GRID) -->
                    <div v-else class="mk-item-grid">
                        <div
                            v-for="(it, idx) in itemsTersaring"
                            :key="it.Id_SOP_Paket_Item"
                            class="mk-item-card"
                            @click="bukaDetailItem(it)"
                        >
                            <div class="mk-item-card-top">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="mk-row-idx">{{ idx + 1 }}</span>
                                    <ItemTypeIcon :tipe="it.Tipe_Item" />
                                    <HcisBadge
                                        :variant="it.Wajib ? 'warning' : 'neutral'"
                                        size="xs"
                                    >
                                        {{ it.Wajib ? 'Wajib' : 'Opsional' }}
                                    </HcisBadge>
                                </div>
                                <StatusPill
                                    :text="statusItem(it.Status)"
                                    :variant="variantItem(it.Status)"
                                    :dot="false"
                                />
                            </div>

                            <div class="mk-item-card-body">
                                <h6 class="fw-bold text-dark mb-1 text-truncate" :title="it.Judul_Item">
                                    {{ it.Judul_Item }}
                                </h6>
                                <div v-if="it.Nomor_SOP_Resmi" class="small text-muted mb-2">
                                    <i class="bi bi-file-earmark-text me-1 text-primary"></i>{{ it.Nomor_SOP_Resmi }}
                                </div>
                                <div v-if="barisPengesahan(it)" class="small text-muted mb-2" style="font-size: 0.72rem">
                                    <i class="bi bi-patch-check-fill text-success me-1"></i>{{ barisPengesahan(it) }}
                                </div>

                                <!-- Progress Bar Mini pada Card -->
                                <div class="mb-2">
                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-1" style="font-size: 0.7rem">
                                        <span>Kemajuan</span>
                                        <span class="fw-bold text-dark">{{ itemProgressLabel(it) }}</span>
                                    </div>
                                    <div class="mk-meter-track">
                                        <div
                                            class="mk-meter-fill"
                                            :class="{
                                                'fill-done': itemPersen(it) === 100,
                                                'fill-active': itemPersen(it) > 0 && itemPersen(it) < 100,
                                            }"
                                            :style="{ width: `${itemPersen(it)}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between gap-2 mt-auto pt-2 border-top">
                                    <div class="small text-muted">
                                        <i class="bi bi-stopwatch me-1 text-primary"></i>
                                        <span class="fw-bold text-dark">{{ it.Durasi_Detik ? durasi(it.Durasi_Detik) : '—' }}</span>
                                    </div>
                                    <HcisBadge
                                        v-if="it.Versi !== null && it.Versi !== undefined"
                                        variant="brand"
                                        size="xs"
                                    >
                                        {{ $vlabel(it.Versi) }}
                                    </HcisBadge>
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 2: LOG KRONOLOGI & JEJAK AUDIT
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-else-if="tab === 'log'" class="mk-zona">
                <LogAktivitasAudit
                    :detail="detail || {}"
                    :kode="kode"
                    :id-paket="idPaket"
                />
            </section>
        </template>

        <!-- ══ MODAL RINCIAN JEJAK AUDIT ITEM (HcisDialog) ══ -->
        <HcisDialog
            v-model="modalItemDetail"
            :title="activeItemForModal?.Judul_Item || 'Rincian Item Prosedur'"
            :subtitle="`Jejak audit dan bukti otentikasi pembacaan · ${detail?.karyawan?.Nama || kode} (${kode})`"
            kicker="AUDIT TRAIL INDIVIDUAL"
            icon="file-earmark-medical-fill"
            sentiment="brand"
            variant="glass"
            size="lg"
        >
            <div v-if="activeItemForModal" class="mk-modal-flow">
                <!-- 1. Context Banner Ringkasan Prosedur -->
                <HcisContextBanner
                    variant="glass"
                    sentiment="brand"
                    :title="activeItemForModal.Judul_Item"
                    :subtitle="`Kategori Prosedur: ${detail?.paket?.Nama_Paket || 'Paket Dokumen'} · ${activeItemForModal.Nama_Section ? 'Bab: ' + activeItemForModal.Nama_Section : 'Prosedur Utama'}`"
                    :kicker="activeItemForModal.Tipe_Item ? `TIPE MEDIA: ${activeItemForModal.Tipe_Item}` : 'ITEM PROSEDUR'"
                    icon="file-earmark-text-fill"
                    scope-variant="brand"
                    :scope-label="statusItem(activeItemForModal.Status)"
                >
                    <template #actions>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <HcisBadge
                                :variant="activeItemForModal.Wajib ? 'warning' : 'neutral'"
                                size="xs"
                                :icon="activeItemForModal.Wajib ? 'exclamation-circle-fill' : undefined"
                            >
                                {{ activeItemForModal.Wajib ? 'Wajib Diselesaikan' : 'Prosedur Opsional' }}
                            </HcisBadge>
                            <HcisBadge
                                v-if="activeItemForModal.Nomor_SOP_Resmi"
                                variant="neutral"
                                size="xs"
                                icon="file-earmark-text"
                            >
                                {{ activeItemForModal.Nomor_SOP_Resmi }}
                            </HcisBadge>
                            <HcisBadge
                                v-if="activeItemForModal.Versi !== null && activeItemForModal.Versi !== undefined"
                                variant="brand"
                                size="xs"
                            >
                                {{ $vlabel(activeItemForModal.Versi) }}
                            </HcisBadge>
                        </div>
                    </template>
                </HcisContextBanner>

                <!-- 2. KPI Executive Strip (HcisStatGroup - Mode Dense / Ultra-Compact) -->
                <HcisStatGroup
                    :stats="modalStats"
                    :cols="4"
                    gap="xs"
                    layout="dense"
                    appearance="crystal"
                />

                <!-- 3. Dual Card Section: Kronologi Stempel Waktu (Timeline Stream) & Autentikasi Perangkat -->
                <div class="row g-3">
                    <!-- Card 1: Kronologi Stempel Waktu (Visual Runway Stepper) -->
                    <div class="col-12 col-md-6">
                        <HcisCard
                            title="Kronologi Stempel Waktu"
                            description="Catatan urutan waktu dari awal dibuka hingga tuntas"
                            kicker="LOG AKTIVITAS"
                            icon="clock-history"
                            variant="glass"
                            sentiment="brand"
                            size="sm"
                            badge="Waktu Server"
                            badge-variant="neutral"
                            class="h-100"
                        >
                            <div class="mk-timeline-stream">
                                <!-- Step 1: Akses Awal / Waktu Buka -->
                                <div class="mk-timeline-item" :class="{ 'is-done': !!activeItemForModal.Waktu_Buka }">
                                    <div class="mk-timeline-node">
                                        <div class="mk-timeline-dot" :class="activeItemForModal.Waktu_Buka ? 'dot-info' : 'dot-muted'">
                                            <i class="bi bi-box-arrow-in-right"></i>
                                        </div>
                                        <div class="mk-timeline-line"></div>
                                    </div>
                                    <div class="mk-timeline-content">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="mk-timeline-title">Pertama Kali Dibuka</span>
                                            <HcisBadge
                                                :variant="activeItemForModal.Waktu_Buka ? 'brand' : 'neutral'"
                                                size="2xs"
                                            >
                                                {{ activeItemForModal.Waktu_Buka ? 'Tercatat' : 'Belum Dibuka' }}
                                            </HcisBadge>
                                        </div>
                                        <div class="mk-timeline-timestamp">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <span>{{ fmt(activeItemForModal.Waktu_Buka) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2: Progres & Interaksi Aktif -->
                                <div class="mk-timeline-item" :class="{ 'is-done': itemPersen(activeItemForModal) > 0 }">
                                    <div class="mk-timeline-node">
                                        <div class="mk-timeline-dot" :class="itemPersen(activeItemForModal) > 0 ? 'dot-primary' : 'dot-muted'">
                                            <i class="bi bi-book-half"></i>
                                        </div>
                                        <div class="mk-timeline-line"></div>
                                    </div>
                                    <div class="mk-timeline-content">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="mk-timeline-title">Interaksi &amp; Pembacaan</span>
                                            <span class="mk-timeline-badge-pill">
                                                {{ activeItemForModal.Durasi_Detik ? durasi(activeItemForModal.Durasi_Detik) : '0 dtk' }}
                                            </span>
                                        </div>
                                        <div class="mk-timeline-desc">
                                            <span>Posisi Terakhir: <strong>{{ activeItemForModal.Halaman_Terakhir ? `Halaman ${activeItemForModal.Halaman_Terakhir}` : `${itemPersen(activeItemForModal)}%` }}</strong></span>
                                            <span v-if="rentang(activeItemForModal) !== '—'" class="text-muted ms-2">· Selang: {{ rentang(activeItemForModal) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3: Konfirmasi Penyelesaian -->
                                <div class="mk-timeline-item is-last" :class="{ 'is-done': ['SELESAI', 'DIKONFIRMASI'].includes(activeItemForModal.Status) }">
                                    <div class="mk-timeline-node">
                                        <div
                                            class="mk-timeline-dot"
                                            :class="['SELESAI', 'DIKONFIRMASI'].includes(activeItemForModal.Status) ? 'dot-success' : 'dot-muted'"
                                        >
                                            <i class="bi bi-check2-circle"></i>
                                        </div>
                                    </div>
                                    <div class="mk-timeline-content">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="mk-timeline-title">Konfirmasi Penyelesaian</span>
                                            <HcisBadge
                                                :variant="variantItem(activeItemForModal.Status) === 'success' ? 'success' : (variantItem(activeItemForModal.Status) === 'progress' ? 'brand' : 'neutral')"
                                                size="2xs"
                                            >
                                                {{ statusItem(activeItemForModal.Status) }}
                                            </HcisBadge>
                                        </div>
                                        <div class="mk-timeline-timestamp">
                                            <i class="bi bi-clock-history me-1"></i>
                                            <span>{{ fmt(activeItemForModal.Waktu_Konfirmasi) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </HcisCard>
                    </div>

                    <!-- Card 2: Autentikasi Jaringan & Perangkat (Executive Digital Fingerprint Inspector) -->
                    <div class="col-12 col-md-6">
                        <HcisCard
                            title="Autentikasi &amp; Jejak Digital"
                            description="Verifikasi identitas jaringan, perangkat, dan stempel digital"
                            kicker="INTEGRITAS DIGITAL"
                            icon="shield-check"
                            variant="glass"
                            sentiment="brand"
                            size="sm"
                            badge="Terverifikasi"
                            badge-variant="success"
                            class="h-100"
                        >
                            <div class="d-flex flex-column gap-3">
                                <!-- Block 1: Network / IP Gateway Identity -->
                                <div class="mk-auth-hero-card">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="mk-auth-ico-box ico-ip">
                                                <i class="bi bi-hdd-network-fill"></i>
                                            </div>
                                            <div>
                                                <div class="mk-auth-label">Alamat IP Gateway</div>
                                                <div class="mk-auth-sub-label">Jaringan Karyawan</div>
                                            </div>
                                        </div>
                                        <HcisBadge
                                            :variant="activeItemForModal.Konfirmasi_IP ? 'success' : 'neutral'"
                                            size="2xs"
                                            :dot="!!activeItemForModal.Konfirmasi_IP"
                                        >
                                            {{ activeItemForModal.Konfirmasi_IP ? 'IPv4 Tercatat' : 'Tidak Terekam' }}
                                        </HcisBadge>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between gap-2 pt-1 border-top">
                                        <span class="mk-auth-ip-val font-monospace">{{ activeItemForModal.Konfirmasi_IP || '—' }}</span>
                                        <button
                                            v-if="activeItemForModal.Konfirmasi_IP"
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-muted mk-copy-btn"
                                            title="Salin Alamat IP"
                                            @click="salinTeks(activeItemForModal.Konfirmasi_IP, 'IP Address')"
                                        >
                                            <i class="bi bi-clipboard me-1"></i>Salin IP
                                        </button>
                                    </div>
                                </div>

                                <!-- Block 2: Client Device & Browser -->
                                <div class="mk-auth-hero-card">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="mk-auth-ico-box ico-client">
                                                <i class="bi bi-laptop-fill"></i>
                                            </div>
                                            <div>
                                                <div class="mk-auth-label">Perangkat &amp; Browser</div>
                                                <div class="mk-auth-sub-label">Sistem Operasi / Klien</div>
                                            </div>
                                        </div>
                                        <HcisBadge
                                            v-if="activeItemForModal.Konfirmasi_User_Agent"
                                            variant="brand"
                                            size="2xs"
                                        >
                                            Web Client
                                        </HcisBadge>
                                    </div>
                                    <div class="pt-1 border-top">
                                        <span class="mk-auth-client-val fw-bold text-dark">
                                            {{ ringkasUA(activeItemForModal.Konfirmasi_User_Agent) || '—' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Block 3: Raw User-Agent Inspector Terminal -->
                                <div class="mk-ua-terminal">
                                    <div class="mk-ua-terminal-header">
                                        <div class="mk-ua-terminal-dots">
                                            <span class="dot dot-red"></span>
                                            <span class="dot dot-yellow"></span>
                                            <span class="dot dot-green"></span>
                                        </div>
                                        <span class="mk-ua-terminal-title">User-Agent Fingerprint</span>
                                        <button
                                            v-if="activeItemForModal.Konfirmasi_User_Agent"
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-light mk-copy-btn ms-auto"
                                            title="Salin User-Agent"
                                            @click="salinTeks(activeItemForModal.Konfirmasi_User_Agent, 'User-Agent')"
                                        >
                                            <i class="bi bi-copy me-1"></i>Salin
                                        </button>
                                    </div>
                                    <div class="mk-ua-terminal-body">
                                        <code>{{ activeItemForModal.Konfirmasi_User_Agent || 'Data User-Agent tidak terekam' }}</code>
                                    </div>
                                </div>
                            </div>
                        </HcisCard>
                    </div>
                </div>

                <!-- 4. Card Evaluasi Kuis / Ujian (Jika Ada Kuis) -->
                <HcisCard
                    v-if="activeItemForModal.Skor_Quiz != null || activeItemForModal.Jumlah_Percobaan > 0"
                    title="Hasil Evaluasi Ujian & Kuis"
                    description="Nilai kelulusan dan rekam jejak percobaan kuis pemahaman dokumen"
                    kicker="EVALUASI KOMPETENSI"
                    icon="card-checklist"
                    variant="glass"
                    :sentiment="activeItemForModal.Status_Lulus === 'Y' ? 'success' : 'danger'"
                    size="sm"
                    :badge="activeItemForModal.Status_Lulus === 'Y' ? 'Lulus Ujian' : 'Belum Lulus'"
                    :badge-variant="activeItemForModal.Status_Lulus === 'Y' ? 'success' : 'danger'"
                >
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Skor Akhir</div>
                                <div class="fw-bold text-dark fs-5 font-monospace">{{ activeItemForModal.Skor_Quiz != null ? activeItemForModal.Skor_Quiz : '—' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Nilai Kelulusan Minimum</div>
                                <div class="fw-bold text-dark fs-5 font-monospace">{{ activeItemForModal.Nilai_Minimum != null ? activeItemForModal.Nilai_Minimum : '—' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Jumlah Percobaan</div>
                                <div class="fw-bold text-dark fs-5 font-monospace">{{ activeItemForModal.Jumlah_Percobaan || 1 }}{{ activeItemForModal.Maks_Percobaan ? ` / ${activeItemForModal.Maks_Percobaan}` : '' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Status Verifikasi Kuis</div>
                                <div class="fw-bold fs-6 mt-1" :class="activeItemForModal.Kuis_Menunggu ? 'text-warning-emphasis' : 'text-success'">
                                    {{ activeItemForModal.Kuis_Menunggu ? 'Menunggu Evaluasi' : 'Tervalidasi Sistem' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- 5. Card Legalitas Pengesahan Dokumen (Jika Ada) -->
                <HcisCard
                    v-if="barisPengesahan(activeItemForModal)"
                    title="Legalitas & Pengesahan Dokumen"
                    description="Admin yang mengesahkan keberlakuan dokumen prosedur ini"
                    kicker="SURAT KEPUTUSAN & LEGALITAS"
                    icon="patch-check-fill"
                    variant="glass"
                    sentiment="success"
                    size="sm"
                    badge="Resmi Disahkan"
                    badge-variant="success"
                >
                    <div class="d-flex align-items-center gap-3">
                        <div class="mk-legal-badge-icon">
                            <i class="bi bi-patch-check-fill text-success fs-4"></i>
                        </div>
                        <div class="text-muted small flex-grow-1" style="line-height: 1.5;">
                            {{ barisPengesahan(activeItemForModal) }}
                        </div>
                    </div>
                </HcisCard>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end w-100 gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Tutup"
                        @click="modalItemDetail = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import {
    HcisAvatar,
    HcisBadge,
    HcisBreadcrumb,
    HcisButton,
    HcisCard,
    HcisContextBanner,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisSkeleton,
    HcisStatGroup,
    HcisTabs,
} from '@/components/ui';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import ItemTypeIcon from '../../Shared/components/ui/ItemTypeIcon.vue';
import LogAktivitasAudit from './monitoring/paket/LogAktivitasAudit.vue';
import { STATUS_ITEM } from '../../Shared/support/constants.js';
import pageMeta from '../../Shared/support/pageMeta.js';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'SopMonitoringKaryawanPaket',
    components: {
        HcisAvatar,
        HcisBadge,
        HcisBreadcrumb,
        HcisButton,
        HcisCard,
        HcisContextBanner,
        HcisDialog,
        HcisEmptyState,
        HcisFilterBar,
        HcisHeader,
        HcisSkeleton,
        HcisStatGroup,
        HcisTabs,
        StatusPill,
        ItemTypeIcon,
        LogAktivitasAudit,
    },
    mixins: [pageMeta],
    props: {
        kode: { type: String, required: true },
        token: { type: String, required: true },
        idPaket: { type: String, required: true },
        karyawanInitial: { type: Object, default: null },
    },
    data() {
        return {
            detail: null,
            loading: true,
            isRefreshing: false,
            tab: 'items',

            // Interactive filter cockpit state
            cariItem: '',
            filterStatusItem: 'all',
            filterValuesItem: {
                sort: 'default',
                tipe: '',
                section: '',
            },
            viewModeItem: 'table',
            kelompokkanBab: false,

            // Modal audit detail state
            modalItemDetail: false,
            activeItemForModal: null,
        };
    },
    computed: {
        karyawanData() {
            return this.detail?.karyawan || this.karyawanInitial || null;
        },
        karyawanNama() {
            return this.karyawanData?.Nama || '';
        },
        breadcrumb() {
            const namaKaryawan = this.karyawanNama || 'Karyawan';
            const namaPaket = this.detail?.paket?.Nama_Paket || 'Detail Paket';
            return [
                { label: 'Monitoring Dokumen', href: '/sop/admin/monitoring?tab=paket', icon: 'speedometer2' },
                { label: namaPaket, href: `/sop/admin/paket/${this.idPaket}`, icon: 'folder2-open' },
                { label: namaKaryawan, active: true, icon: 'person-badge' },
            ];
        },
        itemWajibCount() {
            return (this.detail?.items || []).filter((it) => !!it.Wajib || it.Flag_Wajib === 'Y').length;
        },
        itemOpsionalCount() {
            return Math.max(0, (this.detail?.items || []).length - this.itemWajibCount);
        },
        totalWaktuBacaAktif() {
            if (!this.detail?.items) return '—';
            const totalDetik = this.detail.items.reduce((n, it) => n + (Number(it.Durasi_Detik) || 0), 0);
            return this.durasi(totalDetik);
        },
        hasSections() {
            return !!(this.detail?.sections && this.detail.sections.length > 0);
        },
        statusPenugasanCaption() {
            const p = this.detail?.penugasan || {};
            if (p.Is_Terlambat) {
                return `Lewat ${Math.abs(p.Hari_Tersisa || 0)} hari dari tenggat`;
            }
            if (p.Tenggat) {
                return `Tenggat: ${p.Tenggat}`;
            }
            return 'Berlaku tanpa batas tenggat';
        },
        statistik() {
            const d = this.detail || {};
            const pr = d.progress || {};
            const pen = d.penugasan || {};
            const persen = pr.persen || 0;

            return [
                {
                    key: 'kepatuhan',
                    label: 'Tingkat Kepatuhan',
                    value: `${persen}%`,
                    progress: persen,
                    icon: 'pie-chart-fill',
                    sentiment: persen === 100 ? 'success' : (pen.Is_Terlambat ? 'danger' : 'brand'),
                    target: `${pr.item_selesai || 0}/${pr.total || 0} Item Selesai`,
                    caption: persen === 100 ? 'Seluruh item tuntas dibaca' : `${(pr.total || 0) - (pr.item_selesai || 0)} item tersisa`,
                    loading: this.loading,
                },
                {
                    key: 'total_item',
                    label: 'Item Prosedur',
                    value: (d.items || []).length,
                    suffix: 'item',
                    icon: 'collection-fill',
                    sentiment: 'primary',
                    caption: `${this.itemWajibCount} item wajib · ${this.itemOpsionalCount} opsional`,
                    loading: this.loading,
                },
                {
                    key: 'durasi',
                    label: 'Waktu Baca Aktif',
                    value: this.totalWaktuBacaAktif,
                    icon: 'stopwatch-fill',
                    sentiment: 'purple',
                    caption: 'Akumulasi durasi interaksi',
                    loading: this.loading,
                },
                {
                    key: 'penyelesaian',
                    label: 'Penyelesaian Prosedur',
                    value: `${pr.item_selesai || 0}/${pr.total || this.itemWajibCount}`,
                    suffix: 'Item',
                    icon: persen === 100 ? 'check2-circle' : 'card-checklist',
                    sentiment: persen === 100 ? 'success' : (pen.Is_Terlambat ? 'danger' : 'brand'),
                    caption: this.statusPenugasanCaption,
                    loading: this.loading,
                },
            ];
        },
        tabs() {
            const count = (this.detail?.items || []).length;
            return [
                { key: 'items', label: 'Rincian Item Prosedur', icon: 'list-check', badge: count || undefined },
                { key: 'log', label: 'Log Kronologi & Jejak Audit', icon: 'clock-history' },
            ];
        },
        countItems() {
            const items = this.detail?.items || [];
            return {
                all: items.length,
                wajib: items.filter((it) => !!it.Wajib || it.Flag_Wajib === 'Y').length,
                selesai: items.filter((it) => ['SELESAI', 'DIKONFIRMASI'].includes(it.Status)).length,
                progress: items.filter((it) => ['PROGRESS', 'DIBUKA'].includes(it.Status)).length,
                belum: items.filter((it) => !it.Status || it.Status === 'BELUM').length,
                quiz: items.filter((it) => ['QUIZ', 'UJIAN', 'KUIS'].includes(it.Tipe_Item)).length,
                versiLama: items.filter((it) => it.Keberlakuan && it.Keberlakuan.status === 'VERSI_LAMA').length,
            };
        },
        quickChipsItems() {
            const chips = [
                { value: 'all', label: 'Semua', badge: this.countItems.all, color: 'brand' },
                { value: 'wajib', label: 'Wajib', badge: this.countItems.wajib, color: 'warning', icon: 'exclamation-circle-fill' },
                { value: 'selesai', label: 'Tuntas', badge: this.countItems.selesai, color: 'success', icon: 'check-circle-fill' },
                { value: 'progress', label: 'Sedang Dibaca', badge: this.countItems.progress, color: 'primary', icon: 'arrow-clockwise' },
                { value: 'belum', label: 'Belum Dibuka', badge: this.countItems.belum, color: 'neutral' },
            ];
            if (this.countItems.quiz > 0) {
                chips.push({ value: 'quiz', label: 'Kuis / Ujian', badge: this.countItems.quiz, color: 'purple', icon: 'card-checklist' });
            }
            if (this.countItems.versiLama > 0) {
                chips.push({ value: 'versiLama', label: 'Perlu Baca Ulang', badge: this.countItems.versiLama, color: 'danger', icon: 'arrow-repeat' });
            }
            return chips;
        },
        filtersConfigItems() {
            const tipeSet = Array.from(new Set((this.detail?.items || []).map((it) => it.Tipe_Item).filter(Boolean)));
            const secOptions = (this.detail?.sections || []).map((s) => ({ value: s.Id_SOP_Section, label: s.Nama_Section }));

            return [
                {
                    key: 'sort',
                    label: 'Urutan',
                    type: 'select',
                    options: [
                        { value: 'default', label: 'Urutan Prosedur Asli' },
                        { value: 'judul', label: 'Judul Prosedur (A-Z)' },
                        { value: 'durasi_desc', label: 'Waktu Baca Terlama' },
                        { value: 'durasi_asc', label: 'Waktu Baca Tercepat' },
                        { value: 'status', label: 'Status Pembacaan' },
                    ],
                },
                ...(tipeSet.length > 0
                    ? [
                          {
                              key: 'tipe',
                              label: 'Tipe Media',
                              type: 'select',
                              options: tipeSet.map((t) => ({ value: t, label: t })),
                          },
                      ]
                    : []),
                ...(secOptions.length > 0
                    ? [
                          {
                              key: 'section',
                              label: 'Bab / Bagian',
                              type: 'select',
                              options: secOptions,
                          },
                      ]
                    : []),
            ];
        },
        itemsTersaring() {
            let list = [...(this.detail?.items || [])];

            // Filter Search Text
            if (this.cariItem && this.cariItem.trim()) {
                const q = this.cariItem.toLowerCase().trim();
                list = list.filter((it) => {
                    const judul = (it.Judul_Item || '').toLowerCase();
                    const sopNo = (it.Nomor_SOP_Resmi || '').toLowerCase();
                    const pengesahan = (it.Disahkan_Oleh || '').toLowerCase();
                    const section = (it.Nama_Section || '').toLowerCase();
                    return judul.includes(q) || sopNo.includes(q) || pengesahan.includes(q) || section.includes(q);
                });
            }

            // Filter Chips Status
            if (this.filterStatusItem && this.filterStatusItem !== 'all') {
                if (this.filterStatusItem === 'wajib') {
                    list = list.filter((it) => !!it.Wajib || it.Flag_Wajib === 'Y');
                } else if (this.filterStatusItem === 'selesai') {
                    list = list.filter((it) => ['SELESAI', 'DIKONFIRMASI'].includes(it.Status));
                } else if (this.filterStatusItem === 'progress') {
                    list = list.filter((it) => ['PROGRESS', 'DIBUKA'].includes(it.Status));
                } else if (this.filterStatusItem === 'belum') {
                    list = list.filter((it) => !it.Status || it.Status === 'BELUM');
                } else if (this.filterStatusItem === 'quiz') {
                    list = list.filter((it) => ['QUIZ', 'UJIAN', 'KUIS'].includes(it.Tipe_Item));
                } else if (this.filterStatusItem === 'versiLama') {
                    list = list.filter((it) => it.Keberlakuan && it.Keberlakuan.status === 'VERSI_LAMA');
                }
            }

            // Filter Dropdown Tipe
            if (this.filterValuesItem.tipe) {
                list = list.filter((it) => it.Tipe_Item === this.filterValuesItem.tipe);
            }

            // Filter Dropdown Section
            if (this.filterValuesItem.section) {
                list = list.filter((it) => it.Id_SOP_Section === this.filterValuesItem.section);
            }

            // Sorting
            const sortMode = this.filterValuesItem.sort || 'default';
            if (sortMode === 'judul') {
                list.sort((a, b) => (a.Judul_Item || '').localeCompare(b.Judul_Item || ''));
            } else if (sortMode === 'durasi_desc') {
                list.sort((a, b) => (Number(b.Durasi_Detik) || 0) - (Number(a.Durasi_Detik) || 0));
            } else if (sortMode === 'durasi_asc') {
                list.sort((a, b) => (Number(a.Durasi_Detik) || 0) - (Number(b.Durasi_Detik) || 0));
            } else if (sortMode === 'status') {
                list.sort((a, b) => (a.Status || '').localeCompare(b.Status || ''));
            }

            return list;
        },
        sectionsGrouped() {
            const rawSecs = this.detail?.sections || [];
            const items = this.itemsTersaring;

            const groups = [];
            const itemMap = new Map();

            items.forEach((it) => {
                const secId = it.Id_SOP_Section || 'ungrouped';
                if (!itemMap.has(secId)) {
                    itemMap.set(secId, []);
                }
                itemMap.get(secId).push(it);
            });

            rawSecs.forEach((s) => {
                const secItems = itemMap.get(s.Id_SOP_Section) || [];
                if (secItems.length > 0) {
                    const selesaiCount = secItems.filter((i) => ['SELESAI', 'DIKONFIRMASI'].includes(i.Status)).length;
                    groups.push({
                        key: s.Id_SOP_Section,
                        nama: s.Nama_Section,
                        deskripsi: s.Deskripsi,
                        items: secItems,
                        itemsSelesai: selesaiCount,
                    });
                }
            });

            // Ungrouped items if any
            const ungrouped = itemMap.get('ungrouped') || [];
            if (ungrouped.length > 0) {
                const selesaiCount = ungrouped.filter((i) => ['SELESAI', 'DIKONFIRMASI'].includes(i.Status)).length;
                groups.push({
                    key: 'ungrouped',
                    nama: 'Prosedur Lainnya',
                    deskripsi: null,
                    items: ungrouped,
                    itemsSelesai: selesaiCount,
                });
            }

            return groups;
        },
        modalStats() {
            const it = this.activeItemForModal;
            if (!it) return [];
            const persen = this.itemPersen(it);
            const dur = it.Durasi_Detik ? this.durasi(it.Durasi_Detik) : '—';
            const rent = this.rentang(it);
            const kb = it.Keberlakuan?.status || 'BERLAKU';

            return [
                {
                    key: 'durasi',
                    label: 'Durasi Interaksi',
                    value: dur,
                    icon: 'stopwatch-fill',
                    sentiment: it.Durasi_Detik ? 'primary' : 'neutral',
                    caption: it.Durasi_Detik ? `${it.Durasi_Detik} dtk terekam` : 'Belum aktif',
                },
                {
                    key: 'kemajuan',
                    label: 'Tingkat Kemajuan',
                    value: this.itemProgressLabel(it),
                    progress: persen,
                    icon: 'pie-chart-fill',
                    sentiment: persen === 100 ? 'success' : (persen > 0 ? 'brand' : 'neutral'),
                    caption: persen === 100 ? 'Tuntas dikonfirmasi' : (it.Halaman_Terakhir ? `Hal. ${it.Halaman_Terakhir}` : 'Belum dibuka'),
                },
                {
                    key: 'rentang',
                    label: 'Rentang Waktu',
                    value: rent !== '—' ? rent : '—',
                    icon: 'hourglass-split',
                    sentiment: 'purple',
                    caption: 'Buka s/d konfirmasi',
                },
                {
                    key: 'keberlakuan',
                    label: 'Keberlakuan',
                    value: this.labelKeberlakuan(kb),
                    icon: 'patch-check-fill',
                    sentiment: kb === 'BERLAKU' ? 'success' : (kb === 'VERSI_LAMA' ? 'danger' : 'warning'),
                    caption: it.Keberlakuan?.alasan || 'Versi terverifikasi',
                },
            ];
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
        kembaliKeKaryawan() {
            window.location.href = `/sop/admin/monitoring/karyawan/${this.token}`;
        },
        async muatUlang() {
            this.isRefreshing = true;
            await this.fetch();
            this.isRefreshing = false;
        },
        bukaDetailItem(it) {
            this.activeItemForModal = it;
            this.modalItemDetail = true;
        },
        onFilterChangeItem({ key, value }) {
            this.filterValuesItem = { ...this.filterValuesItem, [key]: value };
        },
        onResetFiltersItem() {
            this.filterValuesItem = { sort: 'default', tipe: '', section: '' };
            this.filterStatusItem = 'all';
            this.cariItem = '';
        },
        statusItem(s) {
            return (STATUS_ITEM[s] || {}).label || s || 'Belum Dibuka';
        },
        variantItem(s) {
            return (STATUS_ITEM[s] || {}).variant || 'idle';
        },
        fmt(v) {
            return v ? String(v).replace('T', ' ').slice(0, 16) : '—';
        },
        labelKeberlakuan(s) {
            return (
                {
                    BERLAKU: 'berlaku',
                    BELUM_BERLAKU: 'belum berlaku',
                    VERSI_LAMA: 'versi lama',
                }[s] || String(s || '').toLowerCase()
            );
        },
        barisPengesahan(it) {
            const bagian = [];
            if (it.Disahkan_Oleh) {
                bagian.push(`Disahkan ${it.Disahkan_Oleh}${it.Jabatan_Pengesah ? ` (${it.Jabatan_Pengesah})` : ''}`);
            }
            if (it.Nomor_Pengesahan) bagian.push(it.Nomor_Pengesahan);
            if (it.Tanggal_Berlaku) bagian.push(`berlaku ${it.Tanggal_Berlaku}`);

            return bagian.join(' · ');
        },
        itemPersen(it) {
            if (!it) return 0;
            if (['SELESAI', 'DIKONFIRMASI'].includes(it.Status)) return 100;
            if (it.Persen_Item != null && it.Persen_Item !== undefined) {
                return Math.min(100, Math.max(0, Math.round(Number(it.Persen_Item) || 0)));
            }
            if (it.Halaman_Terakhir && it.Total_Halaman) {
                return Math.min(100, Math.round((Number(it.Halaman_Terakhir) / Number(it.Total_Halaman)) * 100));
            }
            if (['PROGRESS', 'DIBUKA'].includes(it.Status)) return 50;
            return 0;
        },
        itemProgressLabel(it) {
            if (!it) return '0%';
            if (['SELESAI', 'DIKONFIRMASI'].includes(it.Status)) return '100%';
            if (it.Halaman_Terakhir) return `Hal. ${it.Halaman_Terakhir}`;
            if (it.Persen_Item != null) return `${Math.round(Number(it.Persen_Item) || 0)}%`;
            if (['PROGRESS', 'DIBUKA'].includes(it.Status)) return '50%';
            return '0%';
        },
        durasi(d) {
            const n = Number(d) || 0;
            if (!n) return '—';
            if (n < 60) return `${n} dtk`;
            if (n < 3600) return `${Math.round(n / 60)}m`;
            const jam = Math.floor(n / 3600);
            const menit = Math.round((n % 3600) / 60);

            return menit ? `${jam}j ${menit}m` : `${jam}j`;
        },
        rentang(it) {
            if (!it.Waktu_Buka || !it.Waktu_Konfirmasi) return '—';
            const a = new Date(String(it.Waktu_Buka).replace(' ', 'T'));
            const b = new Date(String(it.Waktu_Konfirmasi).replace(' ', 'T'));
            const d = Math.round((b - a) / 1000);
            if (!Number.isFinite(d) || d < 0) return '—';
            if (d < 3600) return `${Math.round(d / 60)}m`;
            if (d < 86400) return `${Math.round(d / 3600)}j`;

            return `${Math.round(d / 86400)} hr`;
        },
        ringkasUA(ua) {
            if (!ua) return '';
            const m = String(ua).match(/(Chrome|Firefox|Safari|Edg|OPR)\/[\d.]+/);
            const os = /Android/i.test(ua)
                ? 'Android'
                : /iPhone|iPad/i.test(ua)
                  ? 'iOS'
                  : /Windows/i.test(ua)
                    ? 'Windows'
                    : /Mac/i.test(ua)
                      ? 'macOS'
                      : '';

            return [m ? m[0] : String(ua).slice(0, 24), os].filter(Boolean).join(' · ');
        },
        salinTeks(teks, label = 'Teks') {
            if (!teks) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(teks);
            } else {
                const ta = document.createElement('textarea');
                ta.value = teks;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
        },
        async fetch() {
            this.loading = true;
            try {
                const r = await axios.get(`${API}/karyawan/${this.kode}/paket/${this.idPaket}`);
                this.detail = r.data.success ? r.data.result : null;
            } catch (e) {
                this.detail = null;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

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

/* ══ Tab Bar Styling ══ */
.mk-tabs-wrap {
    display: flex;
    align-items: center;
}

.mk-tabs-bar {
    width: 100%;
}

/* ══ Section Group Row Styling ══ */
.mk-sec-row td {
    background: var(--hcis-slate-100) !important;
    padding: 0.75rem 1.15rem !important;
    border-top: 1px solid var(--hcis-slate-300) !important;
    border-bottom: 1px solid var(--hcis-slate-300) !important;
}

.mk-sec-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--hcis-white);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    font-size: 0.9rem;
}

/* ══ Table Styling ══ */
.mk-table-wrap {
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    overflow: hidden;
}

.mk-table {
    margin-bottom: 0;
    font-size: 0.8125rem;
    color: var(--hcis-slate-700);
}

.mk-table thead th {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.95rem 1.15rem;
    border-bottom: 1px solid var(--hcis-slate-200);
    white-space: nowrap;
}

.mk-table tbody tr.mk-table-row {
    transition: background-color 150ms ease;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.mk-table tbody tr.mk-table-row:hover {
    background-color: var(--hcis-slate-50);
}

.mk-table td {
    padding: 0.95rem 1.15rem;
    vertical-align: middle;
}

/* Item Prosedur Cell */
.mk-item-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-slate-900);
    line-height: 1.35;
    cursor: pointer;
    transition: color 150ms ease;
}

.mk-item-title:hover {
    color: var(--hcis-indigo-600);
}

.mk-legal-line {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    line-height: 1.3;
}

.mk-item-ico-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--hcis-slate-100);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.mk-row-idx {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-weight: 700;
    font-size: 0.75rem;
}

/* Quiz Mini Chip */
.mk-quiz-chip {
    padding: 0.35rem 0.55rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
}

.mk-quiz-score {
    font-size: 0.73rem;
    color: var(--hcis-slate-700);
}

.mk-quiz-status {
    font-size: 0.73rem;
    font-weight: 700;
}

.mk-quiz-sub {
    font-size: 0.68rem;
}

/* ══ Kemajuan & Durasi Premium Metric Cell ══ */
.mk-metric-cell {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    width: 100%;
    min-width: 160px;
}

.mk-time-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 6px;
    padding: 0.18rem 0.5rem;
    letter-spacing: 0.01em;
}

.mk-time-pill.has-value {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
    color: #166534;
}

.mk-pct-pill {
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: 6px;
    padding: 0.18rem 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.mk-pct-pill.pct-done {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-200);
}

.mk-pct-pill.pct-active {
    background: #eff6ff;
    color: var(--hcis-blue-600);
    border: 1px solid #bfdbfe;
}

.mk-pct-pill.pct-zero {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
    border: 1px solid var(--hcis-slate-200);
}

.mk-meter-track {
    width: 100%;
    height: 6px;
    background: var(--hcis-slate-200);
    border-radius: 99px;
    overflow: hidden;
    position: relative;
    margin: 0.1rem 0;
}

.mk-meter-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 300ms cubic-bezier(0.16, 1, 0.3, 1);
    background: var(--hcis-slate-400);
}

.mk-meter-fill.fill-done {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.35);
}

.mk-meter-fill.fill-active {
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    box-shadow: 0 0 6px rgba(99, 102, 241, 0.35);
}

.mk-span-footnote {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Time Entries */
.mk-time-entry {
    font-size: 0.74rem;
    color: var(--hcis-slate-600);
}

.mk-time-val {
    color: var(--hcis-slate-700);
    font-weight: 500;
}

/* IP & UA Entries */
.mk-ip-entry {
    font-size: 0.74rem;
    color: var(--hcis-slate-700);
    font-weight: 500;
}

.mk-ip-val {
    color: var(--hcis-slate-900);
}

.mk-ua-entry {
    font-size: 0.71rem;
    color: var(--hcis-slate-500);
}

.mk-ua-val {
    color: var(--hcis-slate-500);
}

/* ══ Grid View Cards ══ */
.mk-item-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.mk-item-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.mk-item-card:hover {
    transform: translateY(-2px);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08);
}

.mk-item-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.mk-item-card-body {
    display: flex;
    flex-direction: column;
    flex: 1;
}

/* ══ Modal Detail Styling ══ */
.mk-modal-hero {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border-color: var(--hcis-slate-200);
}

.mk-modal-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    display: grid;
    place-items: center;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    flex-shrink: 0;
}

.mk-modal-kpi-card {
    background: var(--hcis-white);
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.mk-modal-kpi-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.mk-modal-kpi-val {
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    line-height: 1.3;
}

.mk-modal-kpi-sub {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    margin-top: auto;
}

.mk-audit-entry {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.mk-audit-entry-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
}

.mk-audit-entry-val {
    font-size: 0.84rem;
    font-weight: 600;
}

.mk-ua-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 6px;
    padding: 0.4rem 0.6rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-600);
    word-break: break-all;
    max-height: 64px;
    overflow-y: auto;
}

.mk-legal-badge-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

/* ══ Timeline Stream Visual in Modal ══ */
.mk-timeline-stream {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
    padding-left: 0.25rem;
}

.mk-timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    position: relative;
    padding-bottom: 1.15rem;
}

.mk-timeline-item.is-last {
    padding-bottom: 0;
}

.mk-timeline-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex-shrink: 0;
}

.mk-timeline-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.8rem;
    z-index: 2;
    transition: all 200ms ease;
    border: 2px solid var(--hcis-white);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}

.mk-timeline-dot.dot-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
    border-color: var(--hcis-sky-200);
}

.mk-timeline-dot.dot-primary {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-200);
}

.mk-timeline-dot.dot-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
    border-color: var(--hcis-green-200);
}

.mk-timeline-dot.dot-muted {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
    border-color: var(--hcis-slate-200);
}

.mk-timeline-line {
    position: absolute;
    top: 28px;
    bottom: -4px;
    width: 2px;
    background: var(--hcis-slate-200);
    z-index: 1;
}

.mk-timeline-item.is-done .mk-timeline-line {
    background: linear-gradient(180deg, var(--hcis-indigo-500), var(--hcis-slate-200));
}

.mk-timeline-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.mk-timeline-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}

.mk-timeline-timestamp {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
}

.mk-timeline-badge-pill {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
}

.mk-timeline-desc {
    font-size: 0.72rem;
    color: var(--hcis-slate-600);
}

/* ══ Premium Auth Cards & Fingerprint Inspector ══ */
.mk-auth-hero-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
}

.mk-auth-hero-card:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-white);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
}

.mk-auth-ico-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.mk-auth-ico-box.ico-ip {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-200);
}

.mk-auth-ico-box.ico-client {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-200);
}

.mk-auth-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    line-height: 1.2;
}

.mk-auth-sub-label {
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
    line-height: 1.2;
}

.mk-auth-ip-val {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    letter-spacing: 0.02em;
}

.mk-auth-client-val {
    font-size: 0.82rem;
    color: var(--hcis-slate-700);
}

.mk-copy-btn {
    font-size: 0.68rem;
    font-weight: 600;
    transition: color 150ms ease;
}

.mk-copy-btn:hover {
    color: var(--hcis-indigo-600) !important;
}

/* ══ User-Agent Terminal Box ══ */
.mk-ua-terminal {
    background: var(--hcis-slate-900);
    border: 1px solid var(--hcis-slate-800);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.mk-ua-terminal-header {
    background: var(--hcis-slate-800);
    padding: 0.35rem 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.mk-ua-terminal-dots {
    display: flex;
    align-items: center;
    gap: 4px;
}

.mk-ua-terminal-dots .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

.mk-ua-terminal-dots .dot-red { background: var(--hcis-red-500); }
.mk-ua-terminal-dots .dot-yellow { background: var(--hcis-amber-500); }
.mk-ua-terminal-dots .dot-green { background: var(--hcis-emerald-500); }

.mk-ua-terminal-title {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.mk-ua-terminal-body {
    padding: 0.5rem 0.65rem;
    max-height: 60px;
    overflow-y: auto;
}

.mk-ua-terminal-body code {
    font-size: 0.68rem;
    color: var(--hcis-sky-400);
    word-break: break-all;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    line-height: 1.35;
}

/* ══ Modal Layout Spacing ══ */
.mk-modal-flow {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
</style>
