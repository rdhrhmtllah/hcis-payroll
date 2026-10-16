<template>
    <div class="ro-page">
        <!-- Header Banner -->
        <HcisHeader
            title="Relasi Organisasi & Struktur 360°"
            kicker="SYSTEM · STRUKTUR ORGANISASI & SUMBER PENILAI"
            subtitle="Kelola struktur pelaporan (Atasan, Bawahan) dan jejaring Peer antar-karyawan sebagai fondasi penilai otomatis pada Performance Appraisal."
            icon="diagram-3-fill"
            sentiment="brand"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            :loading="memuat"
        >
            <template #actions>
                <HcisButton
                    :label="isMobile ? 'Tambah' : 'Tambah 1 Relasi'"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    @click="bukaTambahTunggal"
                />
                <HcisButton
                    :label="isMobile ? 'Transfer' : 'Transfer Struktur'"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-left-right"
                    @click="tabUtama = 'manage'; tabMode = 'transfer'"
                />
                <HcisButton
                    :label="isMobile ? 'Excel' : 'Export Excel'"
                    variant="secondary"
                    size="sm"
                    leading-icon="file-earmark-spreadsheet"
                    data-uji="ro-export-buka"
                    @click="bukaEkspor"
                />
                <HcisButton
                    :label="isMobile ? 'Approval' : 'Atasan dari Approval'"
                    variant="secondary"
                    size="sm"
                    leading-icon="diagram-2"
                    data-uji="ro-approval-buka"
                    @click="dialogApproval = true"
                />
                <HcisButton
                    :label="isMobile ? '' : 'Muat Ulang'"
                    variant="ghost"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    :title="isMobile ? 'Muat Ulang' : ''"
                    :loading="memuat"
                    @click="muat"
                />
            </template>

            <template #tabs>
                <HcisTabs
                    v-model="tabUtama"
                    :tabs="daftarTabUtama"
                    variant="header"
                    size="sm"
                    aria-label="Mode Tampilan Relasi Organisasi"
                />
            </template>
        </HcisHeader>

        <!-- Result Error State -->
        <HcisResultState
            v-if="error && !memuat"
            status="danger"
            variant="glass"
            layout="compact"
            title="Relasi organisasi tidak dapat dimuat"
            :description="error"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <template v-else>
            <!-- SKELETON LOADING RINGKASAN -->
            <div v-if="memuat" class="ro-skeleton-cockpit">
                <HcisSkeleton v-for="n in 4" :key="`skel-top-${n}`" :height="isMobile ? '3.8rem' : '5.5rem'" rounded="md" />
            </div>

            <template v-else>
                <!-- ================================================================= -->
                <!-- TAB UTAMA 1: MANAJEMEN RELASI (TABEL, BATCH, TRANSFER, INSPECTOR) -->
                <!-- ================================================================= -->
                <div v-show="tabUtama === 'manage'" class="ro-main-tab-content">
                    <!-- EMPTY ALERT KESELURUHAN (0 RELASI) -->
                    <HcisAlert
                        v-if="totalRelasi === 0"
                    variant="danger"
                    appearance="glass"
                    title="Belum ada satu pun relasi organisasi di database"
                    icon="exclamation-octagon-fill"
                >
                    <div class="ro-empty-alert-body">
                        <p>
                            Selama tabel ini kosong, wizard siklus <strong>Performance Appraisal</strong> hanya bisa membentuk penilaian <em>Diri Sendiri (Self)</em>.
                            Usulan Atasan, Bawahan, dan Peer semuanya akan nihil karena penugasan selain SELF wajib bersumber dari relasi organisasi yang valid.
                        </p>
                        <div class="ro-empty-alert-actions">
                            <HcisButton
                                label="Tambah 1 Relasi Manual"
                                variant="danger"
                                size="xs"
                                leading-icon="plus-lg"
                                @click="bukaTambahTunggal"
                            />
                            <HcisButton
                                label="Gunakan Batch Hub (Multi-Target)"
                                variant="secondary"
                                size="xs"
                                leading-icon="lightning-charge-fill"
                                @click="tabMode = 'batch'"
                            />
                        </div>
                    </div>
                </HcisAlert>

                <!-- ============================================================= -->
                <!-- EXECUTIVE TL;DR COCKPIT (METRICS + VISUAL GRAPH BREAKDOWN)    -->
                <!-- ============================================================= -->
                <div class="ro-cockpit-section">
                    <!-- 4 CRYSTAL STAT CARDS (2x2 ULTRA-COMPACT ON MOBILE) -->
                    <HcisStatGroup
                        :stats="organizationStats"
                        :cols="isMobile ? 2 : 4"
                        gap="xs"
                        :layout="isMobile ? 'dense' : 'compact'"
                        appearance="crystal"
                        class="ro-stat-group"
                    />

                    <!-- RINCIAN ANOMALI: dihitung server sejak awal, tetapi tidak pernah -->
                    <!-- ditampilkan. Dua di antaranya bahkan tidak ikut dijumlahkan.      -->
                    <HcisAlert
                        v-if="rincianAnomali.length"
                        variant="warning"
                        appearance="glass"
                        :title="isMobile ? 'Data relasi perlu diperiksa' : 'Ada data relasi yang perlu diperiksa'"
                        icon="exclamation-triangle-fill"
                        class="ro-anomali-alert"
                    >
                        <ul class="ro-anomali-list">
                            <li
                                v-for="a in rincianAnomali"
                                :key="a.key"
                                class="ro-anomali-item"
                            >
                                <div class="ro-anomali-head">
                                    <strong class="ro-anomali-count">{{ formatAngka(a.jumlah) }} {{ a.satuan }}</strong>
                                    <span class="ro-anomali-label">{{ a.label }}</span>
                                    <button
                                        v-if="a.bisaDisaring"
                                        type="button"
                                        class="ro-text-action-btn"
                                        @click="saringAnomaliDanKeTabel"
                                    >
                                        Lihat baris
                                    </button>
                                    <span v-else class="ro-anomali-nofilter">
                                        (pasangan)
                                    </span>
                                </div>
                                <small class="ro-anomali-desc">{{ a.keterangan }}</small>
                            </li>
                        </ul>
                    </HcisAlert>
                </div>

                <!-- ============================================================= -->
                <!-- 4 ACTION HUBS (SEGMENTED MODERN TABS)                         -->
                <!-- ============================================================= -->
                <div class="ro-tabs-nav-bar">
                    <div class="ro-tabs-group">
                        <button
                            type="button"
                            class="ro-tab-btn"
                            :class="{ 'is-active': tabMode === 'table' }"
                            @click="tabMode = 'table'"
                        >
                            <HcisIcon name="table" size="2xs" />
                            <span>{{ isMobile ? '1. Tabel' : '1. Manajemen Tabel Relasi' }}</span>
                            <span class="ro-tab-count">{{ formatAngka(total) }}</span>
                        </button>

                        <button
                            type="button"
                            class="ro-tab-btn"
                            :class="{ 'is-active': tabMode === 'batch' }"
                            @click="tabMode = 'batch'"
                        >
                            <HcisIcon name="lightning-charge-fill" size="2xs" />
                            <span>{{ isMobile ? '2. Batch & Mesh' : '2. Batch & Peer Mesh Hub' }}</span>
                            <span class="ro-tab-pill">Cepat</span>
                        </button>

                        <button
                            type="button"
                            class="ro-tab-btn"
                            :class="{ 'is-active': tabMode === 'transfer' }"
                            @click="tabMode = 'transfer'"
                        >
                            <HcisIcon name="arrow-left-right" size="2xs" />
                            <span>{{ isMobile ? '3. Transfer' : '3. Transfer / Mutasi Struktur' }}</span>
                            <span class="ro-tab-pill">Mutasi</span>
                        </button>

                        <button
                            type="button"
                            class="ro-tab-btn"
                            :class="{ 'is-active': tabMode === 'inspector' }"
                            @click="tabMode = 'inspector'"
                        >
                            <HcisIcon name="diagram-3" size="2xs" />
                            <span>{{ isMobile ? '4. Inspector 360°' : '4. Ekosistem 360° Inspector' }}</span>
                            <span class="ro-tab-pill">Visual</span>
                        </button>
                    </div>
                </div>

                <!-- ================================================================= -->
                <!-- MODE 1: TABEL DATA & MULTI-ACTION SELECTION (CONTAINERLESS)       -->
                <!-- ================================================================= -->
                <div v-if="tabMode === 'table'" class="ro-tab-content">
                    <!-- Standalone Official HcisFilterBar Component -->
                    <HcisFilterBar
                        v-model:search="saringan.cari"
                        :view-mode="tampilanTabelMode"
                        :show-view-toggle="true"
                        variant="glass"
                        sentiment="brand"
                        size="sm"
                        glow
                        search-placeholder="Cari nama karyawan, kode / NIK subjek atau pihak terkait..."
                        :filters="daftarFiltersTabel"
                        :filter-values="filterValuesTabel"
                        :quick-chips="quickChipsTabel"
                        :active-chip-value="activeChipTabel"
                        :collapsible="true"
                        :results-count="total"
                        @chip-click="onChipClickTabel"
                        @filter-change="onFilterChangeTabel"
                        @update:view-mode="tampilanTabelMode = $event"
                        @reset="hapusSaringan"
                        @update:search="muatDaftar(1)"
                    />

                    <!-- Mode 1A: Official HcisTable Component -->
                    <HcisTable
                        v-if="tampilanTabelMode === 'table'"
                        :columns="kolomTabel"
                        :rows="barisTabel"
                        row-key="id"
                        :loading="memuatDaftar"
                        variant="glass"
                        sentiment="brand"
                        :size="isMobile ? 'sm' : 'md'"
                        selectable
                        v-model:selected-keys="terpilihIds"
                        hoverable
                        interactive
                        responsive="scroll"
                        empty-title="Tidak Ada Relasi yang Cocok"
                        empty-description="Coba ubah kata kunci pencarian atau reset filter untuk menampilkan data."
                        @row-click="({ row }) => bukaInspectorKaryawan(row.perusahaan_subjek, row.kode_subjek)"
                    >
                        <!-- Floating Bulk Selection Action Banner Slot -->
                        <template #bulk-actions="{ selectedKeys, clear }">
                            <div class="ro-bulk-buttons">
                                <HcisButton
                                    :label="`Cabut ${selectedKeys.length} Relasi Terpilih`"
                                    variant="danger"
                                    size="xs"
                                    leading-icon="trash3"
                                    :loading="mencabutMassal"
                                    @click="konfirmasiCabutMassal"
                                />
                                <HcisButton
                                    label="Batal Pilih"
                                    variant="ghost"
                                    size="xs"
                                    @click="clear"
                                />
                            </div>
                        </template>

                        <!-- Slot Cell: Subjek (Karyawan) -->
                        <template #cell-subjek="{ row }">
                            <div class="ro-person-cell">
                                <HcisAvatar
                                    :name="row.nama_subjek || row.kode_subjek"
                                    size="sm"
                                    shape="circle"
                                />
                                <div class="ro-person-details">
                                    <strong
                                        class="ro-person-name is-link"
                                        title="Klik untuk membuka profil ekosistem 360° karyawan subjek ini"
                                        @click.stop="bukaInspectorKaryawan(row.perusahaan_subjek, row.kode_subjek)"
                                    >
                                        {{ row.nama_subjek || row.kode_subjek }}
                                    </strong>
                                    <div class="ro-person-pills">
                                        <span class="ro-nik-pill">{{ row.kode_subjek }}</span>
                                        <span v-if="row.perusahaan_subjek" class="ro-pt-pill">PT: {{ row.perusahaan_subjek }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Slot Cell: Hubungan Relasi -->
                        <template #cell-relasi="{ row }">
                            <div class="ro-relation-badge-wrap" :title="kalimatBarisRelasi(row)">
                                <HcisBadge
                                    :variant="getBadgeVariant(row.kode_relasi)"
                                    size="xs"
                                    appearance="glass"
                                    :icon="getRelasiIcon(row.kode_relasi)"
                                >
                                    {{ formatKategoriNama(row.nama_relasi, row.kode_relasi) }}
                                </HcisBadge>
                                <span class="ro-relation-arah">dari kolom kiri</span>
                                <span v-if="row.utama === 'Y'" class="ro-utama-badge" title="Relasi Utama">
                                    Utama
                                </span>
                            </div>
                        </template>

                        <!-- Slot Cell: Pihak Terkait (Target) -->
                        <template #cell-terkait="{ row }">
                            <div class="ro-person-cell">
                                <HcisAvatar
                                    :name="row.nama_terkait || row.kode_terkait"
                                    size="sm"
                                    shape="circle"
                                    :sentiment="row.terkait_nonaktif ? 'neutral' : 'brand'"
                                />
                                <div class="ro-person-details">
                                    <div class="ro-person-name-line">
                                        <strong
                                            class="ro-person-name is-link"
                                            :class="{ 'is-strikethrough': row.terkait_nonaktif }"
                                            title="Klik untuk membuka profil ekosistem 360° pihak terkait ini"
                                            @click.stop="bukaInspectorKaryawan(row.perusahaan_terkait, row.kode_terkait)"
                                        >
                                            {{ row.nama_terkait || row.kode_terkait }}
                                        </strong>
                                        <span v-if="row.terkait_nonaktif" class="ro-status-dead-pill">
                                            <HcisIcon name="person-x-fill" size="2xs" /> Nonaktif
                                        </span>
                                        <span v-if="row.ke_diri_sendiri" class="ro-status-self-pill">
                                            <HcisIcon name="exclamation-circle-fill" size="2xs" /> Diri Sendiri
                                        </span>
                                    </div>
                                    <div class="ro-person-pills">
                                        <span class="ro-nik-pill">{{ row.kode_terkait }}</span>
                                        <span v-if="row.perusahaan_terkait" class="ro-pt-pill">PT: {{ row.perusahaan_terkait }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Slot Cell: Masa Berlaku -->
                        <template #cell-berlaku="{ row }">
                            <div class="ro-date-cell">
                                <div class="ro-date-line">
                                    <HcisIcon name="calendar-event" size="2xs" />
                                    <span>{{ formatTgl(row.berlaku_dari) || 'Mulai Awal' }}</span>
                                </div>
                                <span class="ro-date-until">
                                    s/d {{ formatTgl(row.berlaku_sampai) || 'Tanpa Batas' }}
                                </span>

                                <!-- Baris di luar masa berlaku TETAP ditampilkan di tabel -->
                                <!-- pengelolaan, tetapi diberi tanda: mesin penugasan PA    -->
                                <!-- sudah tidak melihatnya, jadi tanpa tanda ini ia terbaca -->
                                <!-- sebagai struktur yang berlaku.                          -->
                                <span v-if="row.sudah_lewat" class="ro-status-expired-pill">
                                    <HcisIcon name="hourglass-bottom" size="2xs" /> Sudah lewat
                                </span>
                                <span v-else-if="row.belum_berlaku" class="ro-status-future-pill">
                                    <HcisIcon name="hourglass-top" size="2xs" /> Belum berlaku
                                </span>
                            </div>
                        </template>

                        <!-- Slot Cell: Sumber Data & Catatan -->
                        <template #cell-sumber="{ row }">
                            <div class="ro-source-cell">
                                <span class="ro-source-badge">{{ row.sumber || 'MANUAL' }}</span>
                                <small v-if="row.catatan" class="ro-notes-text" :title="row.catatan">
                                    {{ row.catatan }}
                                </small>
                            </div>
                        </template>

                        <!-- Slot Cell: Aksi (Stops row-click on action buttons) -->
                        <template #cell-aksi="{ row }">
                            <div class="ro-table-actions" @click.stop>
                                <HcisButton
                                    label="Inspeksi"
                                    variant="ghost"
                                    size="xs"
                                    leading-icon="diagram-3"
                                    title="Buka Inspector 360° untuk orang ini"
                                    @click="bukaInspectorKaryawan(row.perusahaan_subjek, row.kode_subjek)"
                                />
                                <HcisButton
                                    label="Cabut"
                                    variant="danger"
                                    size="xs"
                                    leading-icon="trash3"
                                    :loading="sibuk === row.id"
                                    @click="bukaCabutTunggal(row)"
                                />
                            </div>
                        </template>

                        <!-- Empty State -->
                        <template #empty>
                            <HcisEmptyState
                                :title="totalRelasi === 0 ? 'Belum Ada Relasi Organisasi' : 'Tidak Ada Relasi yang Cocok'"
                                :description="totalRelasi === 0
                                    ? 'Gunakan form Tambah atau Batch Hub di atas untuk mulai membuat struktur relasi antar-karyawan.'
                                    : 'Coba ubah kata kunci pencarian atau reset filter untuk menampilkan data.'"
                                icon="diagram-3"
                            />
                        </template>

                        <!-- Footer Pagination -->
                        <template #footer>
                            <div v-if="total > 0" class="ro-table-pagination-footer">
                                <HcisPagination
                                    :total="total"
                                    :current-page="halaman"
                                    :page-size="perHalaman"
                                    :page-sizes="[10, 25, 50, 100]"
                                    :layout="isMobile ? 'prev, pager, next' : 'total, sizes, prev, pager, next'"
                                    @current-change="onUbahHalaman"
                                    @size-change="onUbahUkuranHalaman"
                                />
                            </div>
                        </template>
                    </HcisTable>

                    <!-- Mode 1B: Responsive Card Grid View (Ultra-Ergonomic on Mobile) -->
                    <div v-else-if="tampilanTabelMode === 'grid'" class="ro-grid-view-wrapper">
                        <!-- Shimmer Skeleton Loading for Cards -->
                        <div v-if="memuatDaftar" class="ro-relation-cards-grid">
                            <div v-for="n in 6" :key="`skel-card-${n}`" class="ro-relation-card ro-relation-card--skeleton">
                                <div class="ro-card-skeleton-head">
                                    <HcisSkeleton width="60px" height="0.75rem" rounded="pill" />
                                    <HcisSkeleton width="80px" height="1.2rem" rounded="pill" />
                                </div>
                                <HcisSkeleton width="100%" height="4.5rem" rounded="md" />
                                <div class="ro-card-skeleton-foot">
                                    <HcisSkeleton width="100px" height="0.7rem" rounded="sm" />
                                    <HcisSkeleton width="60px" height="1.4rem" rounded="sm" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty State for Cards -->
                        <div v-else-if="!barisTabel.length" class="ro-table-empty">
                            <HcisEmptyState
                                :title="totalRelasi === 0 ? 'Belum Ada Relasi Organisasi' : 'Tidak Ada Relasi yang Cocok'"
                                :description="totalRelasi === 0
                                    ? 'Gunakan form Tambah atau Batch Hub di atas untuk mulai membuat struktur relasi antar-karyawan.'
                                    : 'Coba ubah kata kunci pencarian atau reset filter untuk menampilkan data.'"
                                icon="diagram-3"
                            />
                        </div>

                        <!-- Card Grid List -->
                        <div v-else class="ro-relation-cards-grid">
                            <!-- Mobile Bulk Select Strip if any selected -->
                            <div v-if="terpilihIds.length" class="ro-bulk-action-bar ro-grid-bulk-bar">
                                <div class="ro-bulk-info">
                                    <HcisIcon name="check2-square" size="xs" />
                                    <strong>{{ terpilihIds.length }} baris terpilih</strong>
                                </div>
                                <div class="ro-bulk-buttons">
                                    <HcisButton
                                        :label="`Cabut ${terpilihIds.length} Relasi`"
                                        variant="danger"
                                        size="xs"
                                        leading-icon="trash3"
                                        :loading="mencabutMassal"
                                        @click="konfirmasiCabutMassal"
                                    />
                                    <HcisButton
                                        label="Batal"
                                        variant="ghost"
                                        size="xs"
                                        @click="terpilihIds = []"
                                    />
                                </div>
                            </div>

                            <article
                                v-for="row in barisTabel"
                                :key="row.id"
                                class="ro-relation-card"
                                :class="{
                                    'is-selected': terpilihIds.includes(row.id),
                                    'is-anomali': row.ke_diri_sendiri || row.terkait_nonaktif,
                                    'is-expired': row.sudah_lewat,
                                }"
                                @click="bukaInspectorKaryawan(row.perusahaan_subjek, row.kode_subjek)"
                            >
                                <!-- Card Header: Selection + Relationship Badge -->
                                <div class="ro-card-header" @click.stop>
                                    <label class="ro-card-checkbox-label">
                                        <input
                                            type="checkbox"
                                            :value="row.id"
                                            v-model="terpilihIds"
                                            class="ro-card-checkbox"
                                        />
                                        <span class="ro-card-id-text">#{{ row.id }}</span>
                                    </label>

                                    <div class="ro-card-badge-wrap">
                                        <HcisBadge
                                            :variant="getBadgeVariant(row.kode_relasi)"
                                            size="xs"
                                            appearance="glass"
                                            :icon="getRelasiIcon(row.kode_relasi)"
                                        >
                                            {{ formatKategoriNama(row.nama_relasi, row.kode_relasi) }}
                                        </HcisBadge>
                                        <span v-if="row.utama === 'Y'" class="ro-utama-badge" title="Relasi Utama">
                                            Utama
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Body: Visual 2-Node Flow (Subjek -> Terkait) -->
                                <div class="ro-card-flow">
                                    <!-- Node 1: Subjek -->
                                    <div class="ro-card-node">
                                        <HcisAvatar
                                            :name="row.nama_subjek || row.kode_subjek"
                                            size="sm"
                                            shape="circle"
                                        />
                                        <div class="ro-card-node-info">
                                            <span class="ro-node-role">SUBJEK (PENILAI)</span>
                                            <strong class="ro-node-name">{{ row.nama_subjek || row.kode_subjek }}</strong>
                                            <div class="ro-node-meta">
                                                <span class="ro-nik-pill">{{ row.kode_subjek }}</span>
                                                <span v-if="row.perusahaan_subjek" class="ro-pt-pill">PT: {{ row.perusahaan_subjek }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Connector Bridge -->
                                    <div class="ro-card-connector">
                                        <span class="ro-connector-line"></span>
                                        <div class="ro-connector-badge" :title="kalimatBarisRelasi(row)">
                                            <HcisIcon name="arrow-down" size="2xs" />
                                            <span>{{ formatKategoriNama(row.nama_relasi, row.kode_relasi) }}</span>
                                        </div>
                                    </div>

                                    <!-- Node 2: Terkait -->
                                    <div class="ro-card-node">
                                        <HcisAvatar
                                            :name="row.nama_terkait || row.kode_terkait"
                                            size="sm"
                                            shape="circle"
                                            :sentiment="row.terkait_nonaktif ? 'neutral' : 'brand'"
                                        />
                                        <div class="ro-card-node-info">
                                            <span class="ro-node-role">PIHAK TERKAIT (DINILAI)</span>
                                            <strong
                                                class="ro-node-name"
                                                :class="{ 'is-strikethrough': row.terkait_nonaktif }"
                                            >
                                                {{ row.nama_terkait || row.kode_terkait }}
                                            </strong>
                                            <div class="ro-node-meta">
                                                <span class="ro-nik-pill">{{ row.kode_terkait }}</span>
                                                <span v-if="row.perusahaan_terkait" class="ro-pt-pill">PT: {{ row.perusahaan_terkait }}</span>
                                                <span v-if="row.terkait_nonaktif" class="ro-status-dead-pill">
                                                    <HcisIcon name="person-x-fill" size="2xs" /> Nonaktif
                                                </span>
                                                <span v-if="row.ke_diri_sendiri" class="ro-status-self-pill">
                                                    <HcisIcon name="exclamation-circle-fill" size="2xs" /> Self
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Meta & Validity -->
                                <div class="ro-card-meta-row">
                                    <div class="ro-card-validity">
                                        <HcisIcon name="calendar-event" size="2xs" />
                                        <span>{{ formatTgl(row.berlaku_dari) || 'Awal' }} – {{ formatTgl(row.berlaku_sampai) || 'Seterusnya' }}</span>
                                        <span v-if="row.sudah_lewat" class="ro-status-expired-pill">
                                            Lewat
                                        </span>
                                    </div>
                                    <span class="ro-source-badge">{{ row.sumber || 'MANUAL' }}</span>
                                </div>

                                <!-- Card Footer Actions -->
                                <div class="ro-card-footer" @click.stop>
                                    <HcisButton
                                        label="Inspector 360°"
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="diagram-3"
                                        class="flex-1"
                                        @click="bukaInspectorKaryawan(row.perusahaan_subjek, row.kode_subjek)"
                                    />
                                    <HcisButton
                                        label="Cabut"
                                        variant="danger"
                                        size="xs"
                                        leading-icon="trash3"
                                        :loading="sibuk === row.id"
                                        @click="bukaCabutTunggal(row)"
                                    />
                                </div>
                            </article>
                        </div>

                        <!-- Card Grid Footer Pagination -->
                        <div v-if="total > 0" class="ro-table-pagination-footer ro-grid-pagination">
                            <HcisPagination
                                :total="total"
                                :current-page="halaman"
                                :page-size="perHalaman"
                                :page-sizes="[10, 25, 50, 100]"
                                :layout="isMobile ? 'prev, pager, next' : 'total, sizes, prev, pager, next'"
                                @current-change="onUbahHalaman"
                                @size-change="onUbahUkuranHalaman"
                            />
                        </div>
                    </div>
                </div>

                <!-- ================================================================= -->
                <!-- MODE 2: BATCH & PEER MESH HUB (MULTI-TARGET GENERATION)           -->
                <!-- ================================================================= -->
                <div v-if="tabMode === 'batch'" class="ro-tab-content">
                    <!-- Segmented Dual-Tab Switcher -->
                    <div class="ro-batch-submode-switcher">
                        <button
                            type="button"
                            class="ro-submode-btn"
                            :class="{ 'is-active': batchSubmode === '1-to-n' }"
                            @click="batchSubmode = '1-to-n'"
                        >
                            <HcisIcon name="person-plus-fill" size="2xs" />
                            <span>{{ isMobile ? '1-to-N Hub' : '1 Subjek → Banyak Terkait (1-to-N)' }}</span>
                        </button>
                        <button
                            type="button"
                            class="ro-submode-btn"
                            :class="{ 'is-active': batchSubmode === 'peer-mesh' }"
                            @click="batchSubmode = 'peer-mesh'"
                        >
                            <HcisIcon name="share-fill" size="2xs" />
                            <span>{{ isMobile ? 'Peer Mesh' : '🌐 Peer Mesh Matrix (Team)' }}</span>
                        </button>
                    </div>

                    <!-- SUBMODE A: 1 SUBJEK KE BANYAK TERKAIT -->
                    <div v-if="batchSubmode === '1-to-n'" class="ro-batch-layout">
                        <div class="ro-batch-intro">
                            <HcisIcon name="info-circle-fill" size="xs" />
                            <span>
                                Pilih 1 karyawan subjek (misal: Atasan/Manager), tentukan tipe relasi (misal: <em>Bawahan Langsung</em>), lalu pilih anggota tim sekaligus.
                                Sistem akan otomatis membentuk seluruh relasi berpasangan secara berbarengan.
                            </span>
                        </div>

                        <div class="ro-batch-form-grid">
                            <!-- Kolom Kiri: Subjek & Tipe (Langkah 1) -->
                            <div class="ro-batch-left-col">
                                <div class="ro-batch-step-header">
                                    <span class="ro-batch-step-badge">1</span>
                                    <div class="ro-batch-step-info">
                                        <strong class="ro-batch-step-title">Subjek &amp; Tipe Hubungan</strong>
                                        <span class="ro-batch-step-subtitle">Tentukan pusat relasi dan kategori hubungan</span>
                                    </div>
                                </div>

                                <div class="ro-form-group">
                                    <label class="ro-form-label">Karyawan Subjek (Pusat Relasi) <span class="ro-req">*</span></label>
                                    <OrgCariKaryawan v-model="batchForm.subjek" placeholder="Cari atasan / subjek..." />
                                </div>

                                <div v-if="batchForm.subjek" class="ro-form-group">
                                    <label class="ro-form-label">
                                        Subjek Tambahan <small class="ro-label-hint">(opsional — anggota yang sama dihubungkan ke setiap subjek)</small>
                                    </label>
                                    <OrgCariKaryawan v-model="subjekTambahanPilihan" placeholder="Tambah subjek lain..." />
                                    <div v-if="batchForm.subjekTambahan.length" class="ro-subjek-chips">
                                        <span v-for="s in batchForm.subjekTambahan" :key="kunciKaryawan(s)" class="ro-subjek-chip">
                                            {{ s.nama }} <small>{{ s.kode_karyawan }}</small>
                                            <button type="button" :aria-label="`Hapus ${s.nama}`" @click="hapusSubjekTambahan(s)">&times;</button>
                                        </span>
                                    </div>
                                </div>

                                <div class="ro-form-group">
                                    <label class="ro-form-label">Kategori Hubungan ke Anggota <span class="ro-req">*</span></label>
                                    <div class="ro-type-selector-cards">
                                        <div
                                            v-for="j in jenis"
                                            :key="j.kode"
                                            class="ro-type-card-opt"
                                            :class="{ 'is-selected': batchForm.kode_relasi === j.kode }"
                                            role="button"
                                            tabindex="0"
                                            @click="batchForm.kode_relasi = j.kode"
                                        >
                                            <span class="ro-type-card-title">{{ formatKategoriNama(j.nama, j.kode) }}</span>
                                            <small class="ro-type-card-desc">
                                                Kebalikan: <strong>{{ kebalikanDari(j.kode) || 'Sama' }}</strong>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Arah preview visual -->
                                <div v-if="kalimatArahBatch" class="ro-arah-preview">
                                    <HcisIcon name="check2-circle" size="xs" />
                                    <span>{{ kalimatArahBatch }}</span>
                                </div>

                                <div class="ro-form-grid-2">
                                    <div class="ro-form-group">
                                        <label class="ro-form-label">Berlaku Dari</label>
                                        <HcisField v-model="batchForm.berlaku_dari" type="date" size="sm" />
                                    </div>
                                    <div class="ro-form-group">
                                        <label class="ro-form-label">Sampai (Opsional)</label>
                                        <HcisField v-model="batchForm.berlaku_sampai" type="date" size="sm" />
                                    </div>
                                </div>

                                <div class="ro-form-group">
                                    <label class="ro-form-label">Catatan / Memo</label>
                                    <HcisTextarea
                                        v-model="batchForm.catatan"
                                        size="sm"
                                        :rows="2"
                                        placeholder="Contoh: Penugasan tim divisi Q3..."
                                    />
                                </div>

                                <div class="ro-batch-submit-box">
                                    <HcisButton
                                        :label="isMobile ? `Simpan (${jumlahRelasiBatch}) Relasi Massal` : `Simpan ${jumlahRelasiBatch} Relasi Massal` + (semuaSubjekBatch.length > 1 ? ` (${semuaSubjekBatch.length} subjek)` : '')"
                                        variant="primary"
                                        :size="isMobile ? 'sm' : 'md'"
                                        leading-icon="check-circle-fill"
                                        :disabled="!batchForm.subjek || !batchForm.terkaitList.length"
                                        :loading="menyimpanBatch"
                                        block
                                        @click="eksekusiBatch1ToN"
                                    />
                                </div>
                            </div>

                            <!-- Kolom Kanan: Multi-Target Search & Selector (Langkah 2) -->
                            <div class="ro-batch-right-col">
                                <div class="ro-batch-step-header">
                                    <span class="ro-batch-step-badge">2</span>
                                    <div class="ro-batch-step-info">
                                        <strong class="ro-batch-step-title">Pilih Anggota Terkait ({{ batchForm.terkaitList.length }} / {{ BATAS_MASSAL }})</strong>
                                        <span class="ro-batch-step-subtitle">Cari dan centang karyawan yang akan dihubungkan</span>
                                    </div>
                                </div>

                                <!-- Quick Action Chips -->
                                <div class="ro-batch-quick-btns">
                                    <button
                                        v-if="hasilPencarianBatch.length"
                                        type="button"
                                        class="ro-quick-chip-btn"
                                        :disabled="batchForm.terkaitList.length >= BATAS_MASSAL"
                                        @click="pilihSemuaHasilBatch"
                                    >
                                        <HcisIcon name="check-all" size="2xs" />
                                        <span>Pilih Semua Tampil ({{ hasilPencarianBatch.length }})</span>
                                    </button>

                                    <button
                                        v-if="bisaSemuaYangCocok"
                                        type="button"
                                        class="ro-quick-chip-btn is-primary"
                                        @click="bukaSemuaYangCocok"
                                    >
                                        <HcisIcon name="lightning-charge-fill" size="2xs" />
                                        <span>Semua Cocok ({{ totalCocokBatch.toLocaleString('id-ID') }})</span>
                                    </button>
                                    <button
                                        v-if="batchForm.terkaitList.length"
                                        type="button"
                                        class="ro-quick-chip-btn is-danger"
                                        @click="batchForm.terkaitList = []"
                                    >
                                        <HcisIcon name="x-circle" size="2xs" />
                                        <span>Kosongkan ({{ batchForm.terkaitList.length }})</span>
                                    </button>
                                </div>

                                <!-- Candidate Filter Controls (Inline, No Global FAB) -->
                                <div class="ro-batch-filter-row">
                                    <HcisField
                                        v-model="batchSearchKata"
                                        type="search"
                                        size="sm"
                                        placeholder="Ketik nama atau NIK kandidat..."
                                        clearable
                                        @update:model-value="cariBatchKaryawan"
                                    >
                                        <template #prefix>
                                            <HcisIcon name="search" size="xs" class="text-muted" />
                                        </template>
                                    </HcisField>
                                    <div class="ro-batch-filter-dropdowns">
                                        <HcisSelect
                                            v-model="batchFilterDivisi"
                                            size="sm"
                                            placeholder="Semua Divisi"
                                            clearable
                                            @change="cariBatchKaryawan"
                                        >
                                            <HcisSelectOption value="" label="Semua Divisi" />
                                            <HcisSelectOption
                                                v-for="d in opsiOrganisasi.divisi"
                                                :key="d"
                                                :value="d"
                                                :label="d"
                                            />
                                        </HcisSelect>
                                        <HcisSelect
                                            v-model="batchFilterDept"
                                            size="sm"
                                            placeholder="Semua Departemen"
                                            clearable
                                            @change="cariBatchKaryawan"
                                        >
                                            <HcisSelectOption value="" label="Semua Departemen" />
                                            <HcisSelectOption
                                                v-for="dp in opsiOrganisasi.departemen"
                                                :key="dp"
                                                :value="dp"
                                                :label="dp"
                                            />
                                        </HcisSelect>
                                    </div>
                                </div>

                                <!-- Selected Chips Strip -->
                                <div v-if="batchForm.terkaitList.length" class="ro-selected-chips-wrap">
                                    <div class="ro-selected-chips-head">
                                        <span class="ro-selected-label">Daftar Terpilih ({{ batchForm.terkaitList.length }})</span>
                                        <button
                                            type="button"
                                            class="ro-text-clear-btn"
                                            @click="batchForm.terkaitList = []"
                                        >
                                            Hapus Semua
                                        </button>
                                    </div>
                                    <div class="ro-selected-chips-list">
                                        <div
                                            v-for="k in batchForm.terkaitList"
                                            :key="k.kode_perusahaan + '|' + k.kode_karyawan"
                                            class="ro-selected-chip"
                                        >
                                            <span class="ro-chip-name">{{ k.nama }}</span>
                                            <span class="ro-chip-nik">({{ k.kode_karyawan }})</span>
                                            <button
                                                type="button"
                                                class="ro-chip-del"
                                                title="Hapus"
                                                @click="hapusSatuTerkaitBatch(k)"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search Results Multi-Select Box -->
                                <div class="ro-batch-results-box">
                                    <div v-if="dipangkasBatch" class="ro-dipangkas">
                                        <HcisIcon name="funnel-fill" size="2xs" />
                                        <span>
                                            Menampilkan {{ hasilPencarianBatch.length }} dari
                                            <strong>{{ totalCocokBatch.toLocaleString('id-ID') }}</strong> yang cocok.
                                            <template v-if="bisaSemuaYangCocok">
                                                Untuk menghubungkan seluruhnya, pakai tombol
                                                <strong>Semua Cocok</strong> di atas.
                                            </template>
                                            <template v-else-if="!batchFilterDept">
                                                Pilih <strong>Departemen</strong> untuk memfilter lebih spesifik.
                                            </template>
                                            <template v-else>
                                                Persempit dengan kata pencarian untuk melihat kandidat lain.
                                            </template>
                                        </span>
                                    </div>

                                    <div
                                        v-if="sudahTerhubungBatch > 0 && !memuatCariBatch"
                                        class="ro-sudah-terhubung-nota"
                                    >
                                        <HcisIcon name="link-45deg" size="2xs" />
                                        <span>
                                            <strong>{{ sudahTerhubungBatch }}</strong> orang di daftar ini sudah
                                            terhubung sebagai {{ batchForm.kode_relasi }} dengan subjek tersebut.
                                        </span>
                                    </div>

                                    <div
                                        v-if="bentrokArahBatch > 0 && !memuatCariBatch"
                                        class="ro-bentrok-nota"
                                    >
                                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                        <span>
                                            <strong>{{ bentrokArahBatch }}</strong> orang tidak bisa
                                            dijadikan {{ batchForm.kode_relasi }} karena arah hierarki sudah terbalik.
                                        </span>
                                    </div>

                                    <div v-if="memuatCariBatch" class="ro-batch-loading">
                                        <HcisSkeleton v-for="n in 3" :key="`skel-b-${n}`" height="3rem" rounded="md" />
                                    </div>
                                    <div v-else-if="!hasilPencarianBatch.length" class="ro-batch-empty-result">
                                        <HcisIcon name="search" size="sm" />
                                        <span>Ketik nama atau gunakan filter divisi/dept untuk mencari kandidat karyawan.</span>
                                    </div>
                                    <div v-else class="ro-batch-items-list">
                                        <label
                                            v-for="k in hasilPencarianBatch"
                                            :key="k.kode_perusahaan + '|' + k.kode_karyawan"
                                            class="ro-batch-item-row"
                                            :class="{
                                                'is-checked': apakahKaryawanTerpilihBatch(k),
                                                'is-terkunci': terkunciBatch(k),
                                                'is-bentrok': k.bentrok_arah,
                                            }"
                                        >
                                            <input
                                                type="checkbox"
                                                class="ro-batch-checkbox"
                                                :checked="apakahKaryawanTerpilihBatch(k)"
                                                :disabled="terkunciBatch(k)"
                                                @change="toggleKaryawanBatch(k)"
                                            />
                                            <HcisAvatar
                                                :name="k.nama || k.kode_karyawan"
                                                size="xs"
                                                shape="circle"
                                            />
                                            <div class="ro-item-details">
                                                <strong class="ro-item-name">{{ k.nama }}</strong>
                                                <div class="ro-item-meta">
                                                    <span class="ro-nik-pill">{{ k.kode_karyawan }}</span>
                                                    <span v-if="k.nama_level" class="ro-level-pill">{{ k.nama_level }}</span>
                                                    <span v-if="k.divisi" class="ro-divisi-pill">{{ k.divisi }}</span>

                                                    <span
                                                        v-for="r in (k.relasi_terpasang ?? [])"
                                                        :key="`rel-${k.kode_karyawan}-${r}`"
                                                        class="ro-terpasang-pill"
                                                        :class="{ 'is-mengunci': k.sudah_terhubung }"
                                                    >
                                                        sudah {{ r }}
                                                    </span>

                                                    <span
                                                        v-if="k.bentrok_arah"
                                                        class="ro-bentrok-pill"
                                                    >
                                                        arah bertentangan — sudah {{ k.bentrok_sebab }}
                                                    </span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMODE B: PEER CLUSTER / MESH GENERATOR -->
                    <div v-else class="ro-peer-mesh-layout">
                        <div class="ro-batch-intro">
                            <HcisIcon name="share-fill" size="xs" />
                            <span>
                                <strong>Peer Mesh Generator</strong>: Pilih sekelompok anggota tim (misal: 1 tim berisi 5 orang).
                                Sistem otomatis menghubungkan setiap orang satu sama lain sebagai <strong>Peer bilateral timbal balik</strong> (membentuk {{ hitungPasanganPeer(peerMeshList.length) }} pasangan relasi sekaligus).
                            </span>
                        </div>

                        <div class="ro-peer-mesh-grid">
                            <!-- Left Column: Selected Members & Execution (Langkah 1) -->
                            <div class="ro-peer-left">
                                <div class="ro-batch-step-header">
                                    <span class="ro-batch-step-badge">1</span>
                                    <div class="ro-batch-step-info">
                                        <strong class="ro-batch-step-title">Kelompok Peer Mesh ({{ peerMeshList.length }} Orang)</strong>
                                        <span class="ro-batch-step-subtitle">Daftar anggota yang akan saling terhubung</span>
                                    </div>
                                </div>

                                <div class="ro-peer-header-line">
                                    <span class="ro-peer-calc-badge">
                                        <HcisIcon name="arrow-repeat" size="2xs" />
                                        Membentuk <strong>{{ hitungPasanganPeer(peerMeshList.length) * 2 }}</strong> Relasi Timbal Balik
                                    </span>
                                    <span
                                        class="ro-peer-cap-badge"
                                        :class="{ 'is-penuh': peerMeshList.length >= BATAS_PEER }"
                                    >
                                        Maks {{ BATAS_PEER }} orang
                                    </span>
                                </div>

                                <div v-if="!peerMeshList.length" class="ro-peer-empty-box">
                                    <HcisIcon name="people" size="md" />
                                    <span>Pilih minimal 2 karyawan dari panel di sebelah kanan untuk membentuk kelompok peer.</span>
                                </div>

                                <div v-else class="ro-peer-members-list">
                                    <div
                                        v-for="p in peerMeshList"
                                        :key="p.kode_perusahaan + '|' + p.kode_karyawan"
                                        class="ro-peer-member-card"
                                    >
                                        <HcisAvatar
                                            :name="p.nama || p.kode_karyawan"
                                            size="xs"
                                            shape="circle"
                                        />
                                        <div class="ro-peer-member-info">
                                            <strong>{{ p.nama }}</strong>
                                            <small>{{ p.kode_karyawan }} &bull; {{ p.nama_level || '—' }}</small>
                                        </div>
                                        <button
                                            type="button"
                                            class="ro-peer-remove-btn"
                                            title="Hapus dari kelompok"
                                            @click="hapusSatuPeerMesh(p)"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>

                                <div class="ro-peer-submit-box">
                                    <HcisButton
                                        :label="isMobile ? `Bentuk (${peerMeshList.length}) Peer` : `Bentuk Kelompok Peer (${peerMeshList.length} Anggota)`"
                                        variant="primary"
                                        :size="isMobile ? 'sm' : 'md'"
                                        leading-icon="share-fill"
                                        :disabled="peerMeshList.length < 2"
                                        :loading="menyimpanPeerMesh"
                                        block
                                        @click="eksekusiPeerMesh"
                                    />
                                </div>
                            </div>

                            <!-- Right Column: Search & Add to Group (Langkah 2) -->
                            <div class="ro-peer-right">
                                <div class="ro-batch-step-header">
                                    <span class="ro-batch-step-badge">2</span>
                                    <div class="ro-batch-step-info">
                                        <strong class="ro-batch-step-title">Cari &amp; Tambahkan ke Kelompok</strong>
                                        <span class="ro-batch-step-subtitle">Temukan anggota tim untuk dimasukkan</span>
                                    </div>
                                </div>

                                <div class="ro-batch-quick-btns">
                                    <button
                                        v-if="hasilPencarianPeer.length"
                                        type="button"
                                        class="ro-quick-chip-btn is-primary"
                                        :disabled="peerMeshList.length >= BATAS_PEER"
                                        @click="tambahkanSemuaHasilPeer"
                                    >
                                        <HcisIcon name="plus-circle-fill" size="2xs" />
                                        <span>
                                            {{
                                                peerMeshList.length >= BATAS_PEER
                                                    ? `Kelompok penuh (${BATAS_PEER} orang)`
                                                    : `Tambahkan Semua Tampil (${hasilPencarianPeer.length})`
                                            }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Candidate Filter Controls (Inline, No Global FAB) -->
                                <div class="ro-batch-filter-row">
                                    <HcisField
                                        v-model="peerSearchKata"
                                        type="search"
                                        size="sm"
                                        placeholder="Ketik nama atau NIK karyawan..."
                                        clearable
                                        @update:model-value="cariPeerKaryawan"
                                    >
                                        <template #prefix>
                                            <HcisIcon name="search" size="xs" class="text-muted" />
                                        </template>
                                    </HcisField>
                                    <div class="ro-batch-filter-dropdowns ro-batch-filter-dropdowns--single">
                                        <HcisSelect
                                            v-model="peerFilterDept"
                                            size="sm"
                                            placeholder="Semua Departemen"
                                            clearable
                                            @change="cariPeerKaryawan"
                                        >
                                            <HcisSelectOption value="" label="Semua Departemen" />
                                            <HcisSelectOption
                                                v-for="dp in opsiOrganisasi.departemen"
                                                :key="dp"
                                                :value="dp"
                                                :label="dp"
                                            />
                                        </HcisSelect>
                                    </div>
                                </div>

                                <div class="ro-batch-results-box">
                                    <div v-if="dipangkasPeer" class="ro-dipangkas">
                                        <HcisIcon name="funnel-fill" size="2xs" />
                                        <span>
                                            Menampilkan {{ hasilPencarianPeer.length }} dari
                                            <strong>{{ totalCocokPeer.toLocaleString('id-ID') }}</strong> yang cocok.
                                            <template v-if="!peerFilterDept">
                                                Pilih <strong>Departemen</strong> untuk melihat kandidat spesifik.
                                            </template>
                                            <template v-else>
                                                Persempit dengan kata pencarian untuk kandidat lain.
                                            </template>
                                        </span>
                                    </div>

                                    <div v-if="memuatCariPeer" class="ro-batch-loading">
                                        <HcisSkeleton v-for="n in 3" :key="`skel-p-${n}`" height="3rem" rounded="md" />
                                    </div>
                                    <div v-else-if="!hasilPencarianPeer.length" class="ro-batch-empty-result">
                                        <HcisIcon name="search" size="sm" />
                                        <span>Gunakan pencarian nama atau filter departemen untuk menemukan anggota tim.</span>
                                    </div>
                                    <div v-else class="ro-batch-items-list">
                                        <div
                                            v-for="k in hasilPencarianPeer"
                                            :key="k.kode_perusahaan + '|' + k.kode_karyawan"
                                            class="ro-batch-item-row"
                                            :class="apakahSudahAdaDiPeerMesh(k)
                                                ? 'is-terkunci'
                                                : 'is-clickable'"
                                            @click="tambahSatuKePeerMesh(k)"
                                        >
                                            <HcisAvatar
                                                :name="k.nama || k.kode_karyawan"
                                                size="xs"
                                                shape="circle"
                                            />
                                            <div class="ro-item-details">
                                                <strong class="ro-item-name">{{ k.nama }}</strong>
                                                <div class="ro-item-meta">
                                                    <span class="ro-nik-pill">{{ k.kode_karyawan }}</span>
                                                    <span v-if="k.nama_level" class="ro-level-pill">{{ k.nama_level }}</span>

                                                    <span
                                                        v-if="apakahSudahAdaDiPeerMesh(k)"
                                                        class="ro-terpasang-pill is-mengunci"
                                                    >
                                                        sudah di kelompok
                                                    </span>
                                                </div>
                                            </div>
                                            <HcisButton
                                                :label="apakahSudahAdaDiPeerMesh(k)
                                                    ? 'Sudah Ada'
                                                    : (peerMeshList.length >= BATAS_PEER ? 'Penuh' : '+ Tambah')"
                                                :variant="apakahSudahAdaDiPeerMesh(k) ? 'ghost' : 'secondary'"
                                                size="xs"
                                                :disabled="apakahSudahAdaDiPeerMesh(k) || peerMeshList.length >= BATAS_PEER"
                                                @click.stop="tambahSatuKePeerMesh(k)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================= -->
                <!-- MODE 3: TRANSFER / MUTASI STRUKTUR PELAPORAN (CONTAINERLESS)      -->
                <!-- ================================================================= -->
                <div v-if="tabMode === 'transfer'" class="ro-tab-content">
                    <div class="ro-batch-intro">
                        <HcisIcon name="arrow-left-right" size="xs" />
                        <span>
                            <strong>Fitur Transfer Jalur Pelaporan</strong>: Gunakan ketika terjadi rotasi/promosi atasan atau restrukturisasi tim.
                            Pilih <em>Atasan Lama</em>, sistem akan memuat semua bawahan langsungnya, lalu alihkan ke <em>Atasan Baru</em> dalam 1 transaksi.
                        </span>
                    </div>

                    <div class="ro-transfer-grid">
                        <!-- Step 1: Atasan Lama -->
                        <div class="ro-transfer-step-col">
                            <div class="ro-transfer-col-header">
                                <span class="ro-step-number">1</span>
                                <div>
                                    <strong class="ro-step-title">Pilih Atasan Lama</strong>
                                    <small class="ro-step-desc">Manager yang bawahannya akan dialihkan</small>
                                </div>
                            </div>

                            <OrgCariKaryawan
                                v-model="transferForm.atasanLama"
                                placeholder="Cari atasan lama..."
                                @update:model-value="muatBawahanAtasanLama"
                            />

                            <div v-if="transferForm.atasanLama" class="ro-subordinates-box">
                                <div class="ro-subordinates-header">
                                    <span class="ro-sub-label">
                                        Bawahan Langsung Saat Ini ({{ daftarBawahanLama.length }} orang):
                                    </span>
                                    <button
                                        v-if="daftarBawahanLama.length"
                                        type="button"
                                        class="ro-text-action-btn"
                                        @click="togglePilihSemuaBawahanTransfer"
                                    >
                                        {{ transferForm.bawahanDipilih.length === daftarBawahanLama.length ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                                    </button>
                                </div>

                                <div v-if="memuatBawahanLama" class="ro-sub-loading">
                                    <HcisSkeleton v-for="n in 3" :key="`skel-bw-${n}`" height="2.5rem" rounded="md" />
                                </div>

                                <div v-else-if="!daftarBawahanLama.length" class="ro-sub-empty">
                                    <HcisIcon name="person-x" size="sm" />
                                    <span>Karyawan ini tidak memiliki bawahan langsung aktif di sistem.</span>
                                </div>

                                <div v-else class="ro-sub-list">
                                    <label
                                        v-for="b in daftarBawahanLama"
                                        :key="b.kode_perusahaan + '|' + b.kode_karyawan"
                                        class="ro-sub-row"
                                        :class="{ 'is-checked': apakahBawahanDipilihTransfer(b) }"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="apakahBawahanDipilihTransfer(b)"
                                            @change="toggleBawahanTransfer(b)"
                                        />
                                        <HcisAvatar
                                            :name="b.nama || b.kode_karyawan"
                                            size="xs"
                                            shape="circle"
                                        />
                                        <div class="ro-sub-details">
                                            <strong>{{ b.nama }}</strong>
                                            <small>{{ b.kode_karyawan }} &bull; {{ b.nama_level || '—' }}</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Transfer Arrow Visual Divider -->
                        <div class="ro-transfer-arrow-col">
                            <div class="ro-arrow-circle">
                                <HcisIcon name="arrow-right" size="sm" />
                            </div>
                            <span class="ro-arrow-label">Alihkan Ke</span>
                        </div>

                        <!-- Step 2: Atasan Baru -->
                        <div class="ro-transfer-step-col">
                            <div class="ro-transfer-col-header">
                                <span class="ro-step-number">2</span>
                                <div>
                                    <strong class="ro-step-title">Pilih Atasan Baru</strong>
                                    <small class="ro-step-desc">Manager pengganti penerima bawahan</small>
                                </div>
                            </div>

                            <OrgCariKaryawan
                                v-model="transferForm.atasanBaru"
                                placeholder="Cari atasan baru..."
                            />

                            <div class="ro-transfer-options-box">
                                <label class="ro-checkbox-label">
                                    <input
                                        v-model="transferForm.cabutLama"
                                        type="checkbox"
                                    />
                                    <span>Cabut relasi dengan atasan lama secara otomatis (Direkomendasikan)</span>
                                </label>

                                <div class="ro-form-group">
                                    <label class="ro-form-label">Tanggal Efektif Berlaku</label>
                                    <HcisField v-model="transferForm.berlaku_dari" type="date" size="sm" />
                                </div>

                                <div class="ro-form-group">
                                    <label class="ro-form-label">Alasan Transfer / Mutasi</label>
                                    <HcisTextarea
                                        v-model="transferForm.alasan"
                                        size="sm"
                                        :rows="2"
                                        placeholder="Contoh: Mutasi atasan ke cabang lain..."
                                    />
                                </div>

                                <div class="ro-transfer-submit-box">
                                    <HcisButton
                                        :label="isMobile ? `Transfer (${transferForm.bawahanDipilih.length}) Bawahan` : `Eksekusi Transfer ${transferForm.bawahanDipilih.length} Bawahan`"
                                        variant="primary"
                                        :size="isMobile ? 'sm' : 'md'"
                                        leading-icon="arrow-left-right"
                                        :disabled="!transferForm.atasanLama || !transferForm.atasanBaru || !transferForm.bawahanDipilih.length"
                                        :loading="menjalankanTransfer"
                                        block
                                        @click="eksekusiTransfer"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================= -->
                <!-- MODE 4: EKOSISTEM 360° INSPECTOR (CONTAINERLESS)                  -->
                <!-- ================================================================= -->
                <!-- ================================================================= -->
                <!-- MODE 4: EKOSISTEM 360° INSPECTOR (STRUCTURED COCKPIT)             -->
                <!-- ================================================================= -->
                <div v-if="tabMode === 'inspector'" class="ro-tab-content">
                    <!-- Inspector Search & Control Bar -->
                    <div class="ro-inspector-header-card">
                        <div class="ro-inspector-header-left">
                            <div class="ro-inspector-header-icon">
                                <HcisIcon name="diagram-3-fill" size="sm" />
                            </div>
                            <div class="ro-inspector-header-text">
                                <h5 class="ro-inspector-title">Hub Ekosistem 360° &amp; Health Inspector</h5>
                                <p class="ro-inspector-subtitle">
                                    Inspeksi visual relasi Atasan Langsung, Rekan Sejawat (Peer), dan Bawahan Langsung sebagai sumber penilai otomatis KPI.
                                </p>
                            </div>
                        </div>

                        <div class="ro-inspector-search-box">
                            <label class="ro-inspector-search-label">
                                <HcisIcon name="search" size="2xs" />
                                <span>Pilih / Ganti Karyawan yang Diinspeksi:</span>
                            </label>
                            <OrgCariKaryawan
                                v-model="inspectorKaryawanPilihan"
                                placeholder="Ketik nama atau NIK karyawan..."
                                @update:model-value="onInspectorKaryawanSelected"
                            />
                        </div>
                    </div>

                    <!-- Inspector Loading State -->
                    <div v-if="memuatInspector" class="ro-inspector-loading">
                        <HcisSkeleton height="350px" rounded="lg" />
                    </div>

                    <!-- Inspector Empty State -->
                    <div v-else-if="!ekosistemData" class="ro-inspector-empty-card">
                        <HcisEmptyState
                            title="Pilih Karyawan untuk Membuka Diagram Ekosistem 360°"
                            description="Pilih seorang karyawan pada pencarian di atas untuk memetakan seluruh relasi Atasan, Rekan Sejawat (Peer), dan Bawahan Langsung secara visual."
                            icon="diagram-3"
                        />
                    </div>

                    <!-- Inspector Active Visual Structure -->
                    <div v-else class="ro-ecosystem-visual">
                        <!-- 1. Central Executive Profile Hero Card -->
                        <div class="ro-eco-hero-card">
                            <div class="ro-eco-hero-left">
                                <HcisAvatar
                                    :name="ekosistemData.profil.nama || ekosistemData.profil.kode_karyawan"
                                    size="lg"
                                    shape="circle"
                                />
                                <div class="ro-eco-hero-info">
                                    <div class="ro-eco-hero-title-row">
                                        <h3 class="ro-eco-name">{{ ekosistemData.profil.nama }}</h3>
                                        <span class="ro-eco-center-badge">
                                            <HcisIcon name="person-check-fill" size="2xs" />
                                            Pusat Ekosistem 360°
                                        </span>
                                    </div>

                                    <div class="ro-eco-pills">
                                        <span class="ro-nik-pill">
                                            <HcisIcon name="person-badge" size="2xs" />
                                            {{ ekosistemData.profil.kode_karyawan }}
                                        </span>
                                        <span v-if="ekosistemData.profil.nama_level" class="ro-level-pill">
                                            <HcisIcon name="award" size="2xs" />
                                            {{ ekosistemData.profil.nama_level }}
                                        </span>
                                        <span v-if="ekosistemData.profil.departemen || ekosistemData.profil.divisi" class="ro-divisi-pill">
                                            <HcisIcon name="building" size="2xs" />
                                            {{ ekosistemData.profil.departemen || ekosistemData.profil.divisi }}
                                        </span>
                                        <span v-if="ekosistemData.profil.kode_perusahaan" class="ro-pt-pill">
                                            PT: {{ ekosistemData.profil.kode_perusahaan }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="ro-eco-hero-right">
                                <!-- Mini Metrics Breakdown -->
                                <div class="ro-eco-hero-stats">
                                    <div class="ro-eco-stat-item is-atasan" title="Jumlah Atasan Langsung">
                                        <span class="ro-eco-stat-num">{{ ekosistemData.atasan?.length || 0 }}</span>
                                        <span class="ro-eco-stat-lbl">Atasan</span>
                                    </div>
                                    <div class="ro-eco-stat-sep">/</div>
                                    <div class="ro-eco-stat-item is-peer" title="Jumlah Rekan Kerja (Peer)">
                                        <span class="ro-eco-stat-num">{{ ekosistemData.peers?.length || 0 }}</span>
                                        <span class="ro-eco-stat-lbl">Peer</span>
                                    </div>
                                    <div class="ro-eco-stat-sep">/</div>
                                    <div class="ro-eco-stat-item is-bawahan" title="Jumlah Bawahan Langsung">
                                        <span class="ro-eco-stat-num">{{ ekosistemData.bawahan?.length || 0 }}</span>
                                        <span class="ro-eco-stat-lbl">Bawahan</span>
                                    </div>
                                </div>

                                <div class="ro-eco-hero-actions">
                                    <HcisButton
                                        :label="isMobile ? 'Bagan Struktur' : 'Buka Bagan Struktur'"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="diagram-3-fill"
                                        title="Tampilkan bagan struktur organisasi interaktif untuk orang ini"
                                        @click="lihatPohonVisualDariInspector"
                                    />
                                    <HcisButton
                                        :label="isMobile ? '+ Relasi' : '+ Tambah Relasi'"
                                        variant="primary"
                                        size="sm"
                                        leading-icon="plus-lg"
                                        title="Buat hubungan relasi baru untuk karyawan ini"
                                        @click="bukaQuickAddInspector('ATASAN')"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- 2. RODA 360°: peta satu orang beserta seluruh pihak di       -->
                        <!-- sekelilingnya. Bukan HcisOrgChart — bagan itu menggambar      -->
                        <!-- hirarki, dan rekan sejawat tidak punya tempat di dalam pohon. -->
                        <div class="ro-eco-roda-card">
                            <div class="ro-eco-roda-head">
                                <div>
                                    <h6 class="ro-eco-roda-title">Peta Relasi 360°</h6>
                                    <span class="ro-eco-roda-sub">
                                        Klik salah satu lingkaran untuk menyorot kartunya di bawah.
                                        Letak lingkaran membawa arti: atasan di atas, rekan sejawat di samping, bawahan di bawah.
                                    </span>
                                </div>
                            </div>

                            <OrgRoda360
                                :profil="ekosistemData.profil"
                                :atasan="ekosistemData.atasan"
                                :peers="ekosistemData.peers"
                                :bawahan="ekosistemData.bawahan"
                                @pilih="sorotDariRoda"
                            />
                        </div>

                        <!-- 3. The 3 Ecosystem Spheres Grid -->
                        <div class="ro-eco-spheres-grid">
                            <!-- SPHERE 1: ATASAN LANGSUNG -->
                            <div class="ro-sphere-column is-atasan">
                                <div class="ro-sphere-header">
                                    <div class="ro-sphere-header-left">
                                        <div class="ro-sphere-icon-wrap is-atasan">
                                            <HcisIcon name="arrow-up-circle-fill" size="sm" />
                                        </div>
                                        <div>
                                            <div class="ro-sphere-title-line">
                                                <h6 class="ro-sphere-title">Atasan Langsung</h6>
                                                <span class="ro-sphere-counter is-atasan">{{ ekosistemData.atasan?.length || 0 }}</span>
                                            </div>
                                            <span class="ro-sphere-subtitle">Pimpinan yang mengevaluasi KPI</span>
                                        </div>
                                    </div>

                                    <HcisButton
                                        label="+ Atasan"
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="plus"
                                        @click="bukaQuickAddInspector('ATASAN')"
                                    />
                                </div>

                                <div class="ro-sphere-body">
                                    <div v-if="!ekosistemData.atasan?.length" class="ro-sphere-empty">
                                        <HcisIcon name="arrow-up-circle" size="md" />
                                        <p>Belum memiliki atasan langsung yang tercatat</p>
                                        <HcisButton
                                            label="+ Daftarkan Atasan"
                                            variant="secondary"
                                            size="xs"
                                            leading-icon="plus-lg"
                                            @click="bukaQuickAddInspector('ATASAN')"
                                        />
                                    </div>

                                    <div v-else class="ro-sphere-card-list">
                                        <div
                                            v-for="a in ekosistemData.atasan"
                                            :key="a.id"
                                            class="ro-sphere-member-card"
                                            :class="{ 'is-tersorot': kunciSorot === kunciSimpul('ATASAN', a) }"
                                            :data-simpul="kunciSimpul('ATASAN', a)"
                                            @click="bukaInspectorKaryawan(a.kode_perusahaan, a.kode_karyawan)"
                                        >
                                            <HcisAvatar
                                                :name="a.nama || a.kode_karyawan"
                                                size="sm"
                                                shape="circle"
                                            />
                                            <div class="ro-sphere-member-info">
                                                <strong class="ro-sphere-member-name" :title="a.nama">{{ a.nama }}</strong>
                                                <div class="ro-sphere-member-meta">
                                                    <span class="ro-sphere-meta-nik">{{ a.kode_karyawan }}</span>
                                                    <span v-if="a.nama_level" class="ro-sphere-meta-level">{{ a.nama_level }}</span>
                                                </div>
                                            </div>
                                            <div class="ro-sphere-member-actions" @click.stop>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-inspect"
                                                    title="Inspeksi 360° orang ini"
                                                    @click.stop="bukaInspectorKaryawan(a.kode_perusahaan, a.kode_karyawan)"
                                                >
                                                    <HcisIcon name="diagram-3" size="2xs" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-delete"
                                                    title="Cabut relasi atasan ini"
                                                    @click.stop="cabutRelasiDariInspector(a)"
                                                >
                                                    <HcisIcon name="trash3" size="2xs" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SPHERE 2: REKAN SEJAWAT (PEER) -->
                            <div class="ro-sphere-column is-peer">
                                <div class="ro-sphere-header">
                                    <div class="ro-sphere-header-left">
                                        <div class="ro-sphere-icon-wrap is-peer">
                                            <HcisIcon name="people-fill" size="sm" />
                                        </div>
                                        <div>
                                            <div class="ro-sphere-title-line">
                                                <h6 class="ro-sphere-title">Rekan Sejawat (Peer)</h6>
                                                <span class="ro-sphere-counter is-peer">{{ ekosistemData.peers?.length || 0 }}</span>
                                            </div>
                                            <span class="ro-sphere-subtitle">Rekan penilai setara / satu tim</span>
                                        </div>
                                    </div>

                                    <HcisButton
                                        label="+ Peer"
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="plus"
                                        @click="bukaQuickAddInspector('PEER')"
                                    />
                                </div>

                                <div class="ro-sphere-body">
                                    <div v-if="!ekosistemData.peers?.length" class="ro-sphere-empty">
                                        <HcisIcon name="people" size="md" />
                                        <p>Belum ada rekan peer yang terhubung</p>
                                        <HcisButton
                                            label="+ Hubungkan Peer"
                                            variant="secondary"
                                            size="xs"
                                            leading-icon="plus-lg"
                                            @click="bukaQuickAddInspector('PEER')"
                                        />
                                    </div>

                                    <div v-else class="ro-sphere-card-list">
                                        <div
                                            v-for="p in ekosistemData.peers"
                                            :key="p.id"
                                            class="ro-sphere-member-card"
                                            :class="{ 'is-tersorot': kunciSorot === kunciSimpul('PEER', p) }"
                                            :data-simpul="kunciSimpul('PEER', p)"
                                            @click="bukaInspectorKaryawan(p.kode_perusahaan, p.kode_karyawan)"
                                        >
                                            <HcisAvatar
                                                :name="p.nama || p.kode_karyawan"
                                                size="sm"
                                                shape="circle"
                                            />
                                            <div class="ro-sphere-member-info">
                                                <strong class="ro-sphere-member-name" :title="p.nama">{{ p.nama }}</strong>
                                                <div class="ro-sphere-member-meta">
                                                    <span class="ro-sphere-meta-nik">{{ p.kode_karyawan }}</span>
                                                    <span v-if="p.nama_level" class="ro-sphere-meta-level">{{ p.nama_level }}</span>
                                                </div>
                                            </div>
                                            <div class="ro-sphere-member-actions" @click.stop>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-inspect"
                                                    title="Inspeksi 360° orang ini"
                                                    @click.stop="bukaInspectorKaryawan(p.kode_perusahaan, p.kode_karyawan)"
                                                >
                                                    <HcisIcon name="diagram-3" size="2xs" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-delete"
                                                    title="Cabut relasi peer ini"
                                                    @click.stop="cabutRelasiDariInspector(p)"
                                                >
                                                    <HcisIcon name="trash3" size="2xs" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SPHERE 3: BAWAHAN LANGSUNG -->
                            <div class="ro-sphere-column is-bawahan">
                                <div class="ro-sphere-header">
                                    <div class="ro-sphere-header-left">
                                        <div class="ro-sphere-icon-wrap is-bawahan">
                                            <HcisIcon name="arrow-down-circle-fill" size="sm" />
                                        </div>
                                        <div>
                                            <div class="ro-sphere-title-line">
                                                <h6 class="ro-sphere-title">Bawahan Langsung</h6>
                                                <span class="ro-sphere-counter is-bawahan">{{ ekosistemData.bawahan?.length || 0 }}</span>
                                            </div>
                                            <span class="ro-sphere-subtitle">Anggota tim yang disupervisi</span>
                                        </div>
                                    </div>

                                    <HcisButton
                                        label="+ Bawahan"
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="plus"
                                        @click="bukaQuickAddInspector('BAWAHAN')"
                                    />
                                </div>

                                <div class="ro-sphere-body">
                                    <div v-if="!ekosistemData.bawahan?.length" class="ro-sphere-empty">
                                        <HcisIcon name="arrow-down-circle" size="md" />
                                        <p>Tidak memiliki bawahan langsung yang tercatat</p>
                                        <HcisButton
                                            label="+ Daftarkan Bawahan"
                                            variant="secondary"
                                            size="xs"
                                            leading-icon="plus-lg"
                                            @click="bukaQuickAddInspector('BAWAHAN')"
                                        />
                                    </div>

                                    <div v-else class="ro-sphere-card-list">
                                        <div
                                            v-for="b in ekosistemData.bawahan"
                                            :key="b.id"
                                            class="ro-sphere-member-card"
                                            :class="{ 'is-tersorot': kunciSorot === kunciSimpul('BAWAHAN', b) }"
                                            :data-simpul="kunciSimpul('BAWAHAN', b)"
                                            @click="bukaInspectorKaryawan(b.kode_perusahaan, b.kode_karyawan)"
                                        >
                                            <HcisAvatar
                                                :name="b.nama || b.kode_karyawan"
                                                size="sm"
                                                shape="circle"
                                            />
                                            <div class="ro-sphere-member-info">
                                                <strong class="ro-sphere-member-name" :title="b.nama">{{ b.nama }}</strong>
                                                <div class="ro-sphere-member-meta">
                                                    <span class="ro-sphere-meta-nik">{{ b.kode_karyawan }}</span>
                                                    <span v-if="b.nama_level" class="ro-sphere-meta-level">{{ b.nama_level }}</span>
                                                </div>
                                            </div>
                                            <div class="ro-sphere-member-actions" @click.stop>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-inspect"
                                                    title="Inspeksi 360° orang ini"
                                                    @click.stop="bukaInspectorKaryawan(b.kode_perusahaan, b.kode_karyawan)"
                                                >
                                                    <HcisIcon name="diagram-3" size="2xs" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ro-sphere-action-btn is-delete"
                                                    title="Cabut relasi bawahan ini"
                                                    @click.stop="cabutRelasiDariInspector(b)"
                                                >
                                                    <HcisIcon name="trash3" size="2xs" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Helper Explanatory Footer Card -->
                        <div class="ro-eco-footer-hint">
                            <div class="ro-eco-footer-icon">
                                <HcisIcon name="info-circle-fill" size="sm" />
                            </div>
                            <div class="ro-eco-footer-text">
                                <strong>Panduan Ekosistem 360°:</strong>
                                <span>
                                    Setiap relasi Atasan/Bawahan/Peer di atas otomatis membentuk pasangan timbal-balik dua arah.
                                    Klik kartu anggota mana pun untuk langsung menelusuri rantai 360° orang tersebut, atau gunakan tombol <em>"Buka Bagan Struktur"</em> untuk meninjau bagan struktur organisasi secara visual.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- TAB UTAMA 2: VISUALISASI STRUKTUR ORGANISASI (ZERO-LAG TREE)      -->
            <!-- ================================================================= -->
            <div v-if="tabUtama === 'visual'" class="ro-main-tab-content">
                <!-- Clean Search & Filter Control Panel (Glass Surface) -->
                <div class="ro-visual-filter-card">
                    <div class="ro-visual-filter-grid">
                        <!-- 1. Search Select Departemen -->
                        <div class="ro-visual-filter-col">
                            <label class="ro-visual-col-label">
                                <HcisIcon name="building" size="2xs" />
                                <span>Departemen / Unit Organisasi</span>
                            </label>
                            <HcisSelect
                                v-model="visualFilterDept"
                                size="sm"
                                leading-icon="building"
                                placeholder="Pilih & cari departemen..."
                                clearable
                                filterable
                                :loading="memuatPimpinanDept"
                                @update:model-value="onVisualDeptChange"
                            >
                                <HcisSelectOption
                                    v-for="dept in opsiOrganisasi.departemen"
                                    :key="`sel-dept-${dept}`"
                                    :value="dept"
                                    :label="dept"
                                />
                            </HcisSelect>
                        </div>

                        <!-- 2. Search Select Pimpinan Unit (Muncul jika departemen dipilih) -->
                        <div v-if="visualFilterDept && daftarPimpinanDept.length > 0" class="ro-visual-filter-col">
                            <label class="ro-visual-col-label">
                                <HcisIcon name="person-badge" size="2xs" />
                                <span>Pusat Pimpinan Unit</span>
                            </label>
                            <HcisSelect
                                v-model="visualPimpinanKey"
                                size="sm"
                                leading-icon="person-badge"
                                placeholder="Pilih pimpinan unit..."
                                clearable
                                filterable
                                :loading="memuatPimpinanDept"
                                @update:model-value="onVisualPimpinanChange"
                            >
                                <HcisSelectOption
                                    v-for="p in daftarPimpinanDept"
                                    :key="`sel-pimpinan-${p.kode_perusahaan}-${p.kode_karyawan}`"
                                    :value="`${p.kode_perusahaan}|${p.kode_karyawan}`"
                                    :label="`${p.nama} (${p.nama_level || 'Pimpinan'})`"
                                />
                            </HcisSelect>
                        </div>

                        <!-- 3. Cari Individu Bebas (Lintas Departemen) -->
                        <div class="ro-visual-filter-col is-cari-karyawan">
                            <label class="ro-visual-col-label">
                                <HcisIcon name="search" size="2xs" />
                                <span>{{ visualFilterDept ? 'Atau Cari Anggota Tertentu' : 'Cari Karyawan Tertentu (Semua Unit)' }}</span>
                            </label>
                            <OrgCariKaryawan
                                v-model="visualRootKaryawan"
                                placeholder="Ketik nama atau NIK karyawan..."
                                @update:model-value="onVisualRootSelected"
                            />
                        </div>
                    </div>

                    <!-- Quick Action Bar under Filters -->
                    <div class="ro-visual-filter-footer">
                        <div class="ro-visual-status-text">
                            <template v-if="visualTreeData">
                                <span class="ro-active-dot" />
                                <span v-if="visualFilterDept && !visualRootKaryawan">
                                    Menampilkan struktur <strong>Departemen {{ visualFilterDept }}</strong>
                                </span>
                                <span v-else>
                                    Menampilkan bagan <strong>{{ visualTreeData.name }}</strong>
                                </span>
                            </template>
                            <template v-else>
                                <span class="ro-idle-dot" />
                                <span>Pilih departemen atau cari karyawan untuk menampilkan bagan</span>
                            </template>
                        </div>

                        <div class="ro-visual-footer-actions">
                            <HcisButton
                                v-if="visualTreeData || visualFilterDept || visualRootKaryawan"
                                label="Reset"
                                variant="ghost"
                                size="xs"
                                leading-icon="x-circle"
                                @click="resetVisualisasi"
                            />
                            <HcisButton
                                v-if="visualTrail.length > 1"
                                label="Kembali ke Induk"
                                variant="secondary"
                                size="xs"
                                leading-icon="arrow-up-left"
                                @click="kembaliKeIndukVisual"
                            />
                            <HcisButton
                                v-if="visualTreeData"
                                label="Muat Ulang"
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-clockwise"
                                :loading="memuatVisualTree || memuatPimpinanDept"
                                @click="muatUlangVisualTree"
                            />
                        </div>
                    </div>
                </div>

                <!-- Visual Hierarchy Breadcrumbs -->
                <div v-if="visualTrail.length" class="ro-visual-breadcrumbs">
                    <span class="ro-breadcrumb-prefix">Jalur:</span>
                    <div class="ro-breadcrumb-items">
                        <button
                            v-for="(t, idx) in visualTrail"
                            :key="`trail-${t.kode_perusahaan}-${t.kode_karyawan}`"
                            type="button"
                            class="ro-breadcrumb-btn"
                            :class="{ 'is-current': idx === visualTrail.length - 1 }"
                            @click="lompatKeTrailVisual(t, idx)"
                        >
                            <span>{{ t.nama }}</span>
                            <span v-if="idx < visualTrail.length - 1" class="ro-breadcrumb-sep">›</span>
                        </button>
                    </div>
                </div>

                <!-- Mobile Gesture Tip Strip -->
                <div v-if="isMobile && visualTreeData" class="ro-visual-mobile-hint">
                    <HcisIcon name="arrows-move" size="2xs" />
                    <span>Geser dengan jari &bull; Ketuk kotak orang untuk rincian &bull; Tombol +/- untuk zoom</span>
                </div>

                <!-- Visual Tree Loading State -->
                <div v-if="memuatVisualTree || memuatPimpinanDept" class="ro-visual-loading">
                    <HcisSkeleton :height="isMobile ? '380px' : '450px'" rounded="lg" />
                </div>

                <!-- Visual Tree Empty State (Default Clean State) -->
                <div v-else-if="!visualTreeData" class="ro-visual-empty">
                    <HcisEmptyState
                        title="Pilih Departemen atau Karyawan"
                        description="Pilih unit departemen atau cari karyawan pada filter di atas untuk meninjau struktur hirarki pelaporan secara interaktif."
                        icon="diagram-3"
                    />
                </div>

                <!-- Zero-Lag Interactive HcisOrgChart -->
                <div v-else class="ro-visual-canvas-wrap">
                    <HcisOrgChart
                        :title="visualFilterDept && !visualRootKaryawan ? `Bagan: Dept. ${visualFilterDept}` : `Bagan: ${visualTreeData.name}`"
                        :subtitle="visualFilterDept && !visualRootKaryawan ? `${visualTreeData.title || 'Unit Organisasi'}` : `${visualTreeData.department || visualFilterDept || 'Unit Organisasi'} · ${visualTreeData.title || ''}`"
                        icon="diagram-3-fill"
                        variant="glass"
                        :allow-zoom="true"
                        :initial-collapse-level="4"
                        :height="isMobile ? '520px' : '680px'"
                        :data="visualTreeData"
                        :node-actions="visualNodeActions"
                        @node-action="onVisualNodeAction"
                    />
                </div>
            </div>
            </template>
        </template>

        <!-- DIALOG TAMBAH TUNGGAL RELASI ORGANISASI -->
        <HcisDialog
            v-model="dialogTambahTunggal"
            kicker="MANAJEMEN STRUKTUR"
            title="Tambah Relasi Organisasi"
            description="Hubungkan dua karyawan dalam relasi struktural yang otomatis tersinkronisasi dua arah."
            icon="diagram-3-fill"
            sentiment="brand"
            :size="isMobile ? 'full' : 'lg'"
            header-divider
            sticky-footer
        >
            <div class="ro-dialog-form">
                <!-- Info banner: Sinkronisasi 2 Arah -->
                <HcisAlert
                    variant="info"
                    appearance="soft"
                    size="sm"
                    icon="info-circle-fill"
                    title="Relasi Timbal Balik Otomatis"
                >
                    Sistem akan <strong>secara otomatis membuat relasi timbal balik</strong> (mis. Atasan ↔ Bawahan). Cukup tentukan satu arah, dan arah sebaliknya akan tersinkronisasi.
                </HcisAlert>

                <!-- Step 1: Subjek dan Pihak Terkait -->
                <div class="ro-dialog-step-card">
                    <div class="ro-step-card-header">
                        <span class="ro-step-badge">1</span>
                        <div>
                            <h4 class="ro-step-title">Pilih Karyawan yang Dihubungkan</h4>
                            <p class="ro-step-subtitle">Tentukan karyawan subjek dan pihak terkait yang akan dipetakan relasinya.</p>
                        </div>
                    </div>

                    <div class="ro-parties-grid">
                        <!-- Subjek -->
                        <div class="ro-form-group">
                            <label class="ro-form-label">
                                Karyawan Subjek <span class="ro-req">*</span>
                                <span class="ro-form-hint">(Pihak yang menjadi acuan struktur)</span>
                            </label>
                            <OrgCariKaryawan
                                v-model="formTunggal.subjek"
                                placeholder="Ketik nama atau NIK karyawan subjek..."
                            />
                        </div>

                        <!-- Pihak Terkait -->
                        <div class="ro-form-group">
                            <label class="ro-form-label">
                                Pihak Terkait (Target) <span class="ro-req">*</span>
                                <span class="ro-form-hint">(Pihak yang dihubungkan dengan subjek)</span>
                            </label>
                            <OrgCariKaryawan
                                v-model="formTunggal.terkait"
                                placeholder="Ketik nama atau NIK pihak terkait..."
                            />
                        </div>
                    </div>

                    <p v-if="apakahSubjekSamaDenganTerkaitTunggal" class="ro-form-err">
                        <HcisIcon name="exclamation-circle-fill" size="2xs" />
                        Subjek dan pihak terkait tidak boleh orang yang sama! Silakan pilih karyawan yang berbeda.
                    </p>
                </div>

                <!-- Step 2: Jenis Relasi (Peran Pihak Terkait terhadap Subjek) -->
                <div class="ro-dialog-step-card">
                    <div class="ro-step-card-header">
                        <span class="ro-step-badge">2</span>
                        <div>
                            <h4 class="ro-step-title">Tentukan Jenis Hubungan</h4>
                            <p class="ro-step-subtitle">Peran apa yang dijalankan oleh <strong>Pihak Terkait</strong> terhadap <strong>Karyawan Subjek</strong>?</p>
                        </div>
                    </div>

                    <div class="ro-type-selector-cards">
                        <div
                            v-for="j in jenis"
                            :key="j.kode"
                            class="ro-type-card-opt"
                            :class="{ 'is-selected': formTunggal.kode_relasi === j.kode }"
                            role="button"
                            tabindex="0"
                            @click="formTunggal.kode_relasi = j.kode"
                        >
                            <div class="ro-type-card-top">
                                <div class="ro-type-card-icon-wrap" :class="`is-${j.kode.toLowerCase()}`">
                                    <HcisIcon :name="ikonRelasi(j.kode)" size="xs" />
                                </div>
                                <span class="ro-type-card-title">{{ formatKategoriNama(j.nama, j.kode) }}</span>
                                <HcisIcon
                                    v-if="formTunggal.kode_relasi === j.kode"
                                    name="check-circle-fill"
                                    size="xs"
                                    class="ro-type-card-check"
                                />
                            </div>
                            <div class="ro-type-card-desc">
                                <span class="ro-type-kebalikan-label">Kebalikan:</span>
                                <strong class="ro-type-kebalikan-val">{{ kebalikanDari(j.kode) || 'Sama' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interactive Live Relationship Preview (Visual Diagram) -->
                <div v-if="formTunggal.subjek && formTunggal.terkait && !apakahSubjekSamaDenganTerkaitTunggal" class="ro-relasi-diagram-card">
                    <div class="ro-relasi-diagram-header">
                        <div class="ro-diagram-hdr-left">
                            <HcisIcon name="diagram-3-fill" size="xs" />
                            <strong>Pratinjau Hubungan Struktural</strong>
                        </div>
                        <HcisBadge label="2 Arah Otomatis" variant="brand" size="xs" dot />
                    </div>

                    <div class="ro-relasi-diagram-flow">
                        <!-- Subjek Node -->
                        <div class="ro-relasi-node">
                            <span class="ro-relasi-node-tag is-subjek">Subjek</span>
                            <div class="ro-relasi-node-person">
                                <HcisAvatar :name="formTunggal.subjek.nama || formTunggal.subjek.kode_karyawan" size="sm" shape="circle" />
                                <div class="ro-node-info">
                                    <strong class="ro-node-name">{{ formTunggal.subjek.nama }}</strong>
                                    <span class="ro-node-nik">{{ formTunggal.subjek.kode_karyawan }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Connector -->
                        <div class="ro-relasi-connector">
                            <div class="ro-connector-pill">
                                <HcisIcon :name="ikonRelasi(formTunggal.kode_relasi)" size="2xs" />
                                <span>{{ formatKategoriNama(namaRelasiTerpilih, formTunggal.kode_relasi) }}</span>
                            </div>
                            <div class="ro-connector-line">
                                <div class="ro-connector-arrow left">◀</div>
                                <div class="ro-connector-track"></div>
                                <div class="ro-connector-arrow right">▶</div>
                            </div>
                        </div>

                        <!-- Terkait Node -->
                        <div class="ro-relasi-node">
                            <span class="ro-relasi-node-tag is-terkait">Pihak Terkait</span>
                            <div class="ro-relasi-node-person">
                                <HcisAvatar :name="formTunggal.terkait.nama || formTunggal.terkait.kode_karyawan" size="sm" shape="circle" />
                                <div class="ro-node-info">
                                    <strong class="ro-node-name">{{ formTunggal.terkait.nama }}</strong>
                                    <span class="ro-node-nik">{{ formTunggal.terkait.kode_karyawan }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Explanatory Sentences -->
                    <div class="ro-relasi-sentences">
                        <div class="ro-sentence-item is-primary">
                            <HcisIcon name="check-circle-fill" size="xs" />
                            <span>Yang akan disimpan: <strong>{{ kalimatArahTunggal }}</strong></span>
                        </div>
                        <div v-if="kalimatKebalikanTunggal" class="ro-sentence-item is-secondary">
                            <HcisIcon name="arrow-repeat" size="xs" />
                            <span>Otomatis disinkronkan: <strong>{{ kalimatKebalikanTunggal }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Masa Berlaku & Catatan -->
                <div class="ro-dialog-step-card">
                    <div class="ro-step-card-header">
                        <span class="ro-step-badge">3</span>
                        <div>
                            <h4 class="ro-step-title">Masa Berlaku &amp; Rationale</h4>
                            <p class="ro-step-subtitle">Tentukan periode aktif relasi dan alasan/catatan perubahan (opsional).</p>
                        </div>
                    </div>

                    <div class="ro-form-grid-2">
                        <div class="ro-form-group">
                            <label class="ro-form-label">Berlaku Dari</label>
                            <HcisField v-model="formTunggal.berlaku_dari" type="date" size="sm" />
                        </div>
                        <div class="ro-form-group">
                            <label class="ro-form-label">Berlaku Sampai (Opsional)</label>
                            <HcisField v-model="formTunggal.berlaku_sampai" type="date" size="sm" />
                        </div>
                    </div>

                    <div class="ro-form-group" style="margin-top: 0.5rem;">
                        <label class="ro-form-label">Catatan / Rationale Relasi (Opsional)</label>
                        <HcisTextarea
                            v-model="formTunggal.catatan"
                            size="sm"
                            :rows="2"
                            placeholder="Contoh: Rotasi Q3, mutasi fungsional, atau penugasan sementara..."
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="ro-dialog-footer-actions">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        :size="isMobile ? 'md' : 'sm'"
                        @click="dialogTambahTunggal = false"
                    />
                    <HcisButton
                        label="Simpan Relasi"
                        variant="primary"
                        :size="isMobile ? 'md' : 'sm'"
                        leading-icon="check-lg"
                        :disabled="!formTunggal.subjek || !formTunggal.terkait || !formTunggal.kode_relasi || apakahSubjekSamaDenganTerkaitTunggal"
                        :loading="menyimpanTunggal"
                        @click="simpanTunggal"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- DIALOG CABUT TUNGGAL -->
        <HcisDialog
            v-model="dialogCabutTunggal"
            kicker="PENCABUTAN RELASI"
            title="Cabut Relasi Organisasi?"
            description="Relasi struktural timbal balik antara kedua karyawan akan dinonaktifkan."
            icon="trash3-fill"
            sentiment="danger"
            :size="isMobile ? 'full' : 'md'"
            header-divider
            sticky-footer
        >
            <div class="ro-cabut-modal-content">
                <div v-if="akanDicabut" class="ro-cabut-preview-card">
                    <div class="ro-cabut-person-side">
                        <HcisAvatar :name="akanDicabut.nama_subjek || akanDicabut.kode_subjek" size="sm" shape="circle" />
                        <div class="ro-cabut-person">
                            <strong>{{ akanDicabut.nama_subjek }}</strong>
                            <small>{{ akanDicabut.kode_subjek }}</small>
                            <span v-if="akanDicabut.divisi_subjek || akanDicabut.perusahaan_subjek" class="ro-cabut-dept">
                                {{ akanDicabut.divisi_subjek || akanDicabut.perusahaan_subjek }}
                            </span>
                        </div>
                    </div>

                    <div class="ro-cabut-rel-center">
                        <div class="ro-cabut-rel-pill">
                            <HcisIcon name="slash-circle-fill" size="2xs" />
                            <span>{{ formatKategoriNama(akanDicabut.nama_relasi, akanDicabut.kode_relasi) }}</span>
                        </div>
                        <span class="ro-cabut-rel-cross">✕</span>
                    </div>

                    <div class="ro-cabut-person-side">
                        <HcisAvatar :name="akanDicabut.nama_terkait || akanDicabut.kode_terkait" size="sm" shape="circle" />
                        <div class="ro-cabut-person">
                            <strong>{{ akanDicabut.nama_terkait }}</strong>
                            <small>{{ akanDicabut.kode_terkait }}</small>
                            <span v-if="akanDicabut.divisi_terkait || akanDicabut.perusahaan_terkait" class="ro-cabut-dept">
                                {{ akanDicabut.divisi_terkait || akanDicabut.perusahaan_terkait }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Kalimat yang menyatakan apa yang dicabut -->
                <div v-if="akanDicabut" class="ro-arah-preview is-revoking">
                    <HcisIcon name="info-circle-fill" size="xs" />
                    <span>Yang dicabut: <strong>{{ kalimatBarisRelasi(akanDicabut) }}</strong></span>
                </div>

                <HcisAlert variant="warning" appearance="soft" size="sm" icon="exclamation-triangle-fill">
                    Relasi kebalikannya (<strong>{{ kebalikanDari(akanDicabut?.kode_relasi) || 'kebalikan' }}</strong>) juga akan <strong>otomatis dinonaktifkan</strong> dari sistem.
                </HcisAlert>

                <div class="ro-form-group">
                    <label class="ro-form-label">Alasan Pencabutan (Opsional)</label>
                    <HcisTextarea
                        v-model="alasanCabut"
                        size="sm"
                        :rows="2"
                        placeholder="Contoh: Rotasi departemen, mutasi posisi, atau penyesuaian struktur organisasi..."
                    />
                </div>
            </div>

            <template #footer>
                <div class="ro-dialog-footer-actions">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        :size="isMobile ? 'md' : 'sm'"
                        @click="dialogCabutTunggal = false"
                    />
                    <HcisButton
                        label="Ya, Cabut Relasi"
                        variant="danger"
                        :size="isMobile ? 'md' : 'sm'"
                        leading-icon="trash3"
                        :loading="mencabutTunggal"
                        @click="cabutTunggal"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- DIALOG KONFIRMASI SEMUA YANG COCOK -->
        <HcisDialog
            v-model="dialogSemuaCocok"
            kicker="HUBUNGKAN MASSAL"
            title="Hubungkan Semua yang Cocok?"
            description="Konfirmasi pembuatan relasi massal berdasarkan kriteria saringan aktif."
            icon="diagram-3-fill"
            sentiment="warning"
            :size="isMobile ? 'full' : 'md'"
            header-divider
            sticky-footer
        >
            <div class="ro-cabut-modal-content">
                <div class="ro-batch-confirm-box">
                    <div class="ro-batch-confirm-row">
                        <span class="ro-batch-confirm-label">Karyawan Subjek:</span>
                        <div class="ro-batch-subjek-list">
                            <span v-for="k in semuaSubjekBatch" :key="kunciKaryawan(k)" class="ro-batch-subjek-chip">
                                <HcisAvatar :name="k.nama || k.kode_karyawan" size="xs" shape="circle" />
                                <strong>{{ k.nama }}</strong>
                            </span>
                            <span v-if="semuaSubjekBatch.length > 1" class="ro-batch-count-note">
                                ({{ semuaSubjekBatch.length }} subjek)
                            </span>
                        </div>
                    </div>

                    <div class="ro-batch-confirm-row">
                        <span class="ro-batch-confirm-label">Dihubungkan Sebagai:</span>
                        <HcisBadge :label="batchForm.kode_relasi" variant="brand" size="sm" />
                    </div>

                    <div class="ro-batch-confirm-row">
                        <span class="ro-batch-confirm-label">Target Terkait:</span>
                        <span class="ro-batch-target-count">
                            <strong>{{ totalCocokBatch.toLocaleString('id-ID') }} karyawan</strong> yang memenuhi saringan:
                        </span>
                    </div>

                    <ul class="ro-konfirmasi-saringan">
                        <li v-if="batchFilterDivisi">Divisi: <strong>{{ batchFilterDivisi }}</strong></li>
                        <li v-if="batchFilterDept">Departemen: <strong>{{ batchFilterDept }}</strong></li>
                        <li v-if="batchSearchKata.trim()">
                            Kata pencarian: <strong>{{ batchSearchKata.trim() }}</strong>
                        </li>
                    </ul>
                </div>

                <HcisAlert variant="info" appearance="soft" size="sm" icon="info-circle-fill">
                    Tiap relasi ditulis dua arah, sehingga menghasilkan sampai
                    <strong>{{ (totalCocokBatch * 2 * semuaSubjekBatch.length).toLocaleString('id-ID') }} baris</strong> relasi.
                    Relasi yang sudah terhubung akan dilewati otomatis, dan karyawan tidak akan berelasi dengan dirinya sendiri.
                </HcisAlert>
            </div>

            <template #footer>
                <div class="ro-dialog-footer-actions">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        :size="isMobile ? 'md' : 'sm'"
                        @click="dialogSemuaCocok = false"
                    />
                    <HcisButton
                        :label="`Ya, Hubungkan ${totalCocokBatch.toLocaleString('id-ID')} Orang`"
                        variant="primary"
                        :size="isMobile ? 'md' : 'sm'"
                        leading-icon="diagram-3"
                        :loading="menyimpanSemuaCocok"
                        @click="kirimSemuaYangCocok"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Floating Toast Notification -->
        <transition name="toast-fade">
            <div
                v-if="pesanToast.text"
                class="ro-toast-bar"
                :class="`is-${pesanToast.type}`"
                role="status"
            >
                <HcisIcon :name="pesanToast.type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'" size="xs" />
                <span>{{ pesanToast.text }}</span>
                <button type="button" class="ro-toast-close" @click="pesanToast.text = ''">×</button>
            </div>
        </transition>

        <!-- Export Excel: pilih kategori (dengan Pilih semua) + saringan, lalu unduh .xlsx -->
        <OrgEksporDialog
            v-model="ekspor.terbuka"
            :jenis="jenis"
            :daftar-divisi="opsiOrganisasi.divisi"
            :daftar-departemen="opsiOrganisasi.departemen"
            :daftar-level="opsiOrganisasi.level"
            :awal="{ tipe_id: saringan.tipe_id, hanya_anomali: saringan.hanya_anomali }"
            @terunduh="(n) => tampilkanToast(`Excel terunduh${n ? ` · ${Number(n).toLocaleString('id-ID')} relasi` : ''}.`)"
        />

        <OrgImporApprovalDialog v-model="dialogApproval" @tersimpan="setelahImporApproval" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisOrgChart,
    HcisPagination,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTable,
    HcisTabs,
    HcisTextarea,
    HcisEmptyState,
} from '@/components/ui';
import OrgCariKaryawan from './components/OrgCariKaryawan.vue';
import OrgRoda360 from './components/OrgRoda360.vue';
import OrgImporApprovalDialog from './components/OrgImporApprovalDialog.vue';
import OrgEksporDialog from './components/OrgEksporDialog.vue';

defineOptions({ name: 'SystemRelasiOrganisasiPage' });

const isMobile = ref(false);
/* Usulan ATASAN dari approval KPI & HCIS: unduh Excel, tinjau, impor. */
const dialogApproval = ref(false);
function setelahImporApproval(h) {
    tampilkanToast(`Impor selesai · ${Number(h?.dibuat || 0).toLocaleString('id-ID')} relasi atasan baru${h?.diganti ? `, ${h.diganti} berganti atasan` : ''}.`);
    muat();
}
const isTablet = ref(false);
const tampilanTabelMode = ref(typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table');

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    const wasMobile = isMobile.value;
    isMobile.value = window.innerWidth < 640;
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024;
    if (isMobile.value && !wasMobile && tampilanTabelMode.value === 'table') {
        tampilanTabelMode.value = 'grid';
    }
}

const KEBALIKAN = {
    ATASAN: 'Bawahan',
    BAWAHAN: 'Atasan',
    PEER: 'Peer',
};

const tabUtama = ref('manage'); // 'manage' | 'visual'
const tabMode = ref('table'); // 'table' | 'batch' | 'transfer' | 'inspector'
const batchSubmode = ref('1-to-n'); // '1-to-n' | 'peer-mesh'

// Visual Tree State (Zero-Lag Dynamic Hierarchy)
const visualFilterDept = ref('');
const daftarPimpinanDept = ref([]);
const visualPimpinanKey = ref('');
const memuatPimpinanDept = ref(false);
const visualRootKaryawan = ref(null);
const visualTreeData = ref(null);
const memuatVisualTree = ref(false);
const visualTrail = ref([]);

const daftarTabUtama = computed(() => [
    {
        key: 'manage',
        id: 'manage',
        label: isMobile.value ? '1. Kelola' : '1. Manajemen Relasi',
        icon: 'sliders',
        badge: totalRelasi.value ? formatAngka(totalRelasi.value) : undefined,
    },
    {
        key: 'visual',
        id: 'visual',
        label: isMobile.value ? '2. Bagan' : '2. Bagan Struktur Organisasi',
        icon: 'diagram-3-fill',
    },
]);

const visualNodeActions = [
    { key: 'focus', label: 'Pusatkan Bagan ke Orang Ini', variant: 'primary', icon: 'diagram-3' },
    { key: 'inspector', label: 'Inspeksi 360°', variant: 'secondary', icon: 'person-badge' },
];

const jenis = ref([]);
const karyawanAktif = ref(0);
const totalRelasi = ref(0);
const anomali = ref({});
const opsiOrganisasi = reactive({ divisi: [], departemen: [], level: [] });

// Table Data & Filters
const baris = ref([]);
const total = ref(0);
const halaman = ref(1);
const perHalaman = ref(25);

const BATAS_PEER = 25;
const BATAS_MASSAL = 200;
const saringan = reactive({
    tipe_id: null,
    cari: '',
    hanya_anomali: false,
});
const saringanFilterStatus = ref('semua');
const terpilihIds = ref([]);
const mencabutMassal = ref(false);

const memuat = ref(false);
const memuatDaftar = ref(false);
const error = ref('');
const sibuk = ref(null);

// Modal Tambah Tunggal
const dialogTambahTunggal = ref(false);
const menyimpanTunggal = ref(false);
const formTunggal = reactive({
    subjek: null,
    terkait: null,
    kode_relasi: 'ATASAN',
    berlaku_dari: new Date().toISOString().slice(0, 10),
    berlaku_sampai: '',
    catatan: '',
});

// Modal Cabut Tunggal
const dialogCabutTunggal = ref(false);
const mencabutTunggal = ref(false);
const akanDicabut = ref(null);
const alasanCabut = ref('');

// BATCH 1-TO-N STATE
const batchForm = reactive({
    subjek: null,
    /* Subjek TAMBAHAN: target yang sama dihubungkan ke setiap subjek (mis. beberapa atasan
       dengan anggota tim yang sama). Indikator "sudah terhubung/bentrok" tetap mengacu ke
       subjek utama. */
    subjekTambahan: [],
    kode_relasi: 'BAWAHAN',
    terkaitList: [],
    berlaku_dari: new Date().toISOString().slice(0, 10),
    berlaku_sampai: '',
    catatan: '',
});
const batchSearchKata = ref('');
const batchFilterDivisi = ref('');
const batchFilterDept = ref('');
const hasilPencarianBatch = ref([]);
const totalCocokBatch = ref(0);
const dipangkasBatch = ref(false);
const sudahTerhubungBatch = ref(0);
const bentrokArahBatch = ref(0);
const memuatCariBatch = ref(false);
const menyimpanBatch = ref(false);
let jedaBatch = null;

// PEER MESH STATE
const peerMeshList = ref([]);
const peerSearchKata = ref('');
const peerFilterDept = ref('');
const hasilPencarianPeer = ref([]);
const totalCocokPeer = ref(0);
const dipangkasPeer = ref(false);
const memuatCariPeer = ref(false);
const menyimpanPeerMesh = ref(false);
let jedaPeer = null;

// TRANSFER STATE
const transferForm = reactive({
    atasanLama: null,
    atasanBaru: null,
    bawahanDipilih: [],
    cabutLama: true,
    berlaku_dari: new Date().toISOString().slice(0, 10),
    alasan: '',
});
const daftarBawahanLama = ref([]);
const memuatBawahanLama = ref(false);
const menjalankanTransfer = ref(false);

// INSPECTOR STATE
const inspectorKaryawanPilihan = ref(null);
const ekosistemData = ref(null);
const memuatInspector = ref(false);

/* Sorotan dari roda ke kartu. Roda tidak menanggung tombol Cabut / Inspeksi — itu tetap
   milik kartu di bawahnya — jadi klik pada lingkaran menjawabnya dengan MENUNJUKKAN
   kartunya, bukan dengan menyalin tombolnya ke dua tempat. Kategori ikut jadi kunci
   karena data lama masih memuat pasangan yang memegang lebih dari satu jenis hubungan. */
const kunciSorot = ref('');
let jedaSorot = null;

// TOAST
const pesanToast = reactive({ text: '', type: 'success' });
function tampilkanToast(pesan, type = 'success') {
    pesanToast.text = pesan;
    pesanToast.type = type;
    setTimeout(() => {
        if (pesanToast.text === pesan) pesanToast.text = '';
    }, 4500);
}

/*
 * Anomali dipisah DUA GOLONGAN, dan itu bukan kerapian belaka.
 *
 * Yang pertama berbentuk BARIS: satu baris relasi yang salah, dan tabel bisa
 * menampilkannya persis. Yang kedua berbentuk PASANGAN — timpang, bertentangan, atau
 * berelasi ganda — yang tidak bisa ditunjuk sebagai satu baris, jadi penyaring tabel
 * tidak punya cara memperlihatkannya.
 *
 * Dulu keduanya dijumlahkan menjadi satu lencana yang dipakai membuka penyaring tabel.
 * Akibatnya dua hal sekaligus: lencananya tidak pernah cocok dengan jumlah baris yang
 * muncul, DAN dua jenis anomali (arah bertentangan, relasi ganda) tidak dihitung sama
 * sekali — di basis data pengembangan itu 477 pasangan yang tidak pernah dilaporkan
 * ke siapa pun.
 */
const anomaliBaris = computed(() => (
    Number(anomali.value?.relasi_ke_diri_sendiri || 0)
    + Number(anomali.value?.menunjuk_karyawan_nonaktif || 0)
));

const anomaliStruktur = computed(() => (
    Number(anomali.value?.pasangan_timpang || 0)
    + Number(anomali.value?.arah_bertentangan || 0)
    + Number(anomali.value?.relasi_ganda || 0)
));

const totalAnomali = computed(() => anomaliBaris.value + anomaliStruktur.value);

const adaAnomali = computed(() => totalAnomali.value > 0);

/* Rincian yang ditampilkan apa adanya. Angka gabungan tanpa rincian membuat admin tahu
   ada yang salah tanpa tahu apa yang harus diperiksa. */
const rincianAnomali = computed(() => [
    {
        key: 'relasi_ke_diri_sendiri',
        label: 'Relasi ke diri sendiri',
        jumlah: Number(anomali.value?.relasi_ke_diri_sendiri || 0),
        satuan: 'baris',
        keterangan: 'Orang tercatat sebagai relasi bagi dirinya sendiri — ia akan diusulkan menilai dirinya dua kali.',
        bisaDisaring: true,
    },
    {
        key: 'menunjuk_karyawan_nonaktif',
        label: 'Menunjuk karyawan nonaktif',
        jumlah: Number(anomali.value?.menunjuk_karyawan_nonaktif || 0),
        satuan: 'baris',
        keterangan: 'Penugasannya akan terbentuk dan tidak akan pernah bisa diisi.',
        bisaDisaring: true,
    },
    {
        key: 'pasangan_timpang',
        label: 'Pasangan timpang',
        jumlah: Number(anomali.value?.pasangan_timpang || 0),
        satuan: 'pasangan',
        keterangan: 'Hanya satu arah yang tercatat. "Siapa bawahan saya" menjawab tidak ada, sementara "siapa atasan saya" menjawab ada.',
        bisaDisaring: false,
    },
    {
        key: 'arah_bertentangan',
        label: 'Arah bertentangan',
        jumlah: Number(anomali.value?.arah_bertentangan || 0),
        satuan: 'pasangan',
        keterangan: 'Tercatat atasan DAN bawahan sekaligus pada arah yang sama. Penulisan sekarang menolaknya; yang lama tidak ikut tercabut.',
        bisaDisaring: false,
    },
    {
        key: 'relasi_ganda',
        label: 'Lebih dari satu jenis hubungan',
        jumlah: Number(anomali.value?.relasi_ganda || 0),
        satuan: 'pasangan',
        keterangan: 'Misalnya Peer yang menumpang di atas hubungan atasan–bawahan. Pasangan itu akan menilai dua kali dengan bobot dua kategori berbeda.',
        bisaDisaring: false,
    },
].filter((a) => a.jumlah > 0));

const totalDiLuarMasaBerlaku = ref(0);
const totalBelumDicabut = ref(0);

const totalKaryawanTercakupUnik = computed(() => {
    let maxSubjek = 0;
    for (const j of jenis.value) {
        if ((j.karyawan_tercakup || 0) > maxSubjek) maxSubjek = j.karyawan_tercakup;
    }
    return maxSubjek;
});

const persenCakupanGlobal = computed(() => {
    if (!karyawanAktif.value) return 0;
    return Math.min(100, Math.round((totalKaryawanTercakupUnik.value / karyawanAktif.value) * 100));
});

const organizationStats = computed(() => [
    {
        id: 'total-relasi',
        label: isMobile.value ? 'Total Relasi' : 'Baris Relasi Berlaku',
        value: formatAngka(totalRelasi.value),
        suffix: 'baris',
        sentiment: 'brand',
        icon: 'diagram-3-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value
            ? (totalDiLuarMasaBerlaku.value > 0 ? `${formatAngka(totalDiLuarMasaBerlaku.value)} kedaluwarsa` : 'Relasi 2 arah')
            : (totalDiLuarMasaBerlaku.value > 0 ? `${formatAngka(totalDiLuarMasaBerlaku.value)} di luar masa berlaku` : 'Berlaku aktif 2 arah'),
        pattern: 'grid',
    },
    {
        id: 'karyawan-terhubung',
        label: isMobile.value ? 'Terhubung' : 'Karyawan Terhubung',
        value: formatAngka(totalKaryawanTercakupUnik.value),
        suffix: `/ ${formatAngka(karyawanAktif.value)}`,
        sentiment: 'info',
        icon: 'people-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        progress: persenCakupanGlobal.value,
        caption: isMobile.value ? `${persenCakupanGlobal.value}% tercakup` : `${persenCakupanGlobal.value}% total karyawan`,
        pattern: 'dots',
    },
    {
        id: 'sumber-penilai',
        label: isMobile.value ? 'Kategori' : 'Sumber Penilai 360°',
        value: formatAngka(jenis.value.length),
        suffix: 'kategori',
        sentiment: 'purple',
        icon: 'award-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Atasan/Peer/Bawahan' : 'Atasan, Peer & Bawahan',
        pattern: 'rings',
    },
    {
        id: 'kesehatan-struktur',
        label: isMobile.value ? 'Kesehatan' : 'Kesehatan Struktur',
        value: totalAnomali.value > 0 ? `${formatAngka(totalAnomali.value)}` : '100%',
        suffix: totalAnomali.value > 0 ? 'isu' : 'valid',
        sentiment: totalAnomali.value > 0 ? 'warning' : 'success',
        icon: totalAnomali.value > 0 ? 'exclamation-triangle-fill' : 'patch-check-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value
            ? (totalAnomali.value > 0 ? 'Perlu diperiksa' : 'Struktur bersih')
            : (totalAnomali.value > 0 ? `${formatAngka(totalAnomali.value)} anomali terdeteksi` : 'Seluruh relasi valid'),
        pattern: 'shield',
    },
]);

// Polar gauge metrics
const radius = 40;
const circumference = 2 * Math.PI * radius;
const strokeOffset = computed(() => {
    const pct = Math.max(0, Math.min(100, persenCakupanGlobal.value));
    return circumference - (pct / 100) * circumference;
});

function hitungPersenRelasi(barisCount) {
    if (!totalRelasi.value) return 0;
    return Math.round((Number(barisCount || 0) / totalRelasi.value) * 100);
}

const apakahSubjekSamaDenganTerkaitTunggal = computed(() => {
    if (!formTunggal.subjek || !formTunggal.terkait) return false;
    return (
        String(formTunggal.subjek.kode_karyawan).trim() === String(formTunggal.terkait.kode_karyawan).trim() &&
        String(formTunggal.subjek.kode_perusahaan || '').trim() === String(formTunggal.terkait.kode_perusahaan || '').trim()
    );
});

const apakahSemuaHalamanTerpilih = computed(() => {
    if (!baris.value.length) return false;
    return baris.value.every((r) => terpilihIds.value.includes(r.id));
});

function formatAngka(n) {
    return Number(n || 0).toLocaleString('id-ID');
}

function formatKategoriNama(nama, kode = '') {
    const raw = String(nama || kode || '').trim();
    if (/rekan\s*sejawat|sejawat|peer/i.test(raw)) return 'Peer';
    return raw || 'Penilai';
}

function getArahIcon(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return '↑';
    if (k.includes('BAWAHAN')) return '↓';
    return '⇄';
}

function getRelasiIcon(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'arrow-up-right';
    if (k.includes('BAWAHAN')) return 'arrow-down-right';
    return 'arrow-left-right';
}

function getBadgeVariant(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'info';
    if (k.includes('BAWAHAN')) return 'success';
    return 'purple';
}

function getChipColor(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'sky';
    if (k.includes('BAWAHAN')) return 'emerald';
    return 'purple';
}

function getJenisClass(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'is-type-atasan';
    if (k.includes('BAWAHAN')) return 'is-type-bawahan';
    return 'is-type-peer';
}

function getBarColorClass(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'is-bar-atasan';
    if (k.includes('BAWAHAN')) return 'is-bar-bawahan';
    return 'is-bar-peer';
}

function formatTgl(iso) {
    if (!iso) return null;
    const parts = String(iso).slice(0, 10).split('-');
    if (parts.length === 3) {
        const bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${Number(parts[2])} ${bln[Number(parts[1]) - 1] || parts[1]} ${parts[0]}`;
    }
    return iso;
}

function kebalikanDari(kode) {
    return KEBALIKAN[kode] ?? null;
}

/*
 * ARAH RELASI, DITULIS SEBAGAI KALIMAT.
 *
 * Kontraknya: kode relasi menerangkan peran PIHAK TERKAIT terhadap SUBJEK.
 * (Andi, ATASAN, Budi) berarti "Budi adalah atasan Andi" — bukan sebaliknya.
 *
 * Tiga kolom "Subjek | Atasan | Pihak Terkait" terbaca kiri-ke-kanan sebagai
 * "Andi adalah atasan Budi", yaitu kebalikannya. Tidak ada yang salah pada datanya;
 * yang salah adalah tata letak yang mengundang satu-satunya pembacaan yang keliru.
 * Karena itu arahnya dieja, di setiap tempat orang mengambil keputusan.
 */
function kalimatRelasi(namaTerkait, kodeRelasi, namaRelasi, namaSubjek) {
    const peran = formatKategoriNama(namaRelasi, kodeRelasi);
    const terkait = namaTerkait || '(pihak terkait)';
    const subjek = namaSubjek || '(subjek)';

    if (String(kodeRelasi || '').toUpperCase() === 'PEER') {
        return `${terkait} adalah ${peran} (rekan sejawat) dari ${subjek} — dan sebaliknya.`;
    }

    return `${terkait} adalah ${peran} dari ${subjek}.`;
}

function kalimatBarisRelasi(row) {
    return kalimatRelasi(
        row?.nama_terkait || row?.kode_terkait,
        row?.kode_relasi,
        row?.nama_relasi,
        row?.nama_subjek || row?.kode_subjek,
    );
}

const kalimatArahTunggal = computed(() => {
    if (!formTunggal.subjek || !formTunggal.terkait) return '';

    const j = jenis.value.find((x) => x.kode === formTunggal.kode_relasi);

    return kalimatRelasi(
        formTunggal.terkait.nama,
        formTunggal.kode_relasi,
        j?.nama,
        formTunggal.subjek.nama,
    );
});

const namaRelasiTerpilih = computed(() => {
    const j = jenis.value.find((x) => x.kode === formTunggal.kode_relasi);
    return j?.nama || formTunggal.kode_relasi;
});

const kalimatKebalikanTunggal = computed(() => {
    if (!formTunggal.subjek || !formTunggal.terkait) return '';
    const kodeKebalikan = kebalikanDari(formTunggal.kode_relasi);
    if (!kodeKebalikan || formTunggal.kode_relasi === 'PEER') return '';
    const namaKebalikan = kodeKebalikan.toUpperCase();
    return kalimatRelasi(
        formTunggal.subjek.nama,
        namaKebalikan,
        kodeKebalikan,
        formTunggal.terkait.nama,
    );
});

function ikonRelasi(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'person-badge';
    if (k.includes('BAWAHAN')) return 'diagram-3';
    if (k.includes('PEER')) return 'people-fill';
    return 'arrow-left-right';
}

const subjekTambahanPilihan = ref(null);
const kunciKaryawan = (k) => `${k?.kode_perusahaan}|${k?.kode_karyawan}`;
const semuaSubjekBatch = computed(() => {
    const daftar = [batchForm.subjek, ...batchForm.subjekTambahan].filter(Boolean);
    const terlihat = new Set();
    return daftar.filter((k) => !terlihat.has(kunciKaryawan(k)) && terlihat.add(kunciKaryawan(k)));
});
watch(subjekTambahanPilihan, (k) => {
    if (!k) return;
    if (!semuaSubjekBatch.value.some((s) => kunciKaryawan(s) === kunciKaryawan(k))) {
        batchForm.subjekTambahan.push(k);
    }
    subjekTambahanPilihan.value = null;
});
function hapusSubjekTambahan(k) {
    batchForm.subjekTambahan = batchForm.subjekTambahan.filter((s) => kunciKaryawan(s) !== kunciKaryawan(k));
}
const jumlahRelasiBatch = computed(() => semuaSubjekBatch.value.reduce(
    (n, subjek) => n + batchForm.terkaitList.filter((t) => kunciKaryawan(t) !== kunciKaryawan(subjek)).length, 0,
));

const kalimatArahBatch = computed(() => {
    if (!batchForm.subjek || !batchForm.kode_relasi) return '';

    const j = jenis.value.find((x) => x.kode === batchForm.kode_relasi);
    const peran = formatKategoriNama(j?.nama, batchForm.kode_relasi);

    if (batchForm.kode_relasi === 'PEER') {
        return `Setiap orang yang dipilih akan menjadi ${peran} (rekan sejawat) dari ${batchForm.subjek.nama} — dan sebaliknya.`;
    }

    const subjek = semuaSubjekBatch.value.length > 1
        ? `masing-masing dari ${semuaSubjekBatch.value.length} subjek (${semuaSubjekBatch.value.map((k) => k.nama).join(', ')})`
        : batchForm.subjek.nama;
    return `Setiap orang yang dipilih akan menjadi ${peran} dari ${subjek}.`;
});

function hitungPasanganPeer(n) {
    if (n < 2) return 0;
    return (n * (n - 1)) / 2;
}

// =============================================================================
// HCIS FILTER BAR INTEGRATION (TABLE DATA MODE 1)
// =============================================================================
const daftarFiltersTabel = computed(() => [
    {
        key: 'tipe_id',
        label: 'Tipe Relasi',
        type: 'select',
        icon: 'diagram-3',
        placeholder: 'Semua Tipe Relasi',
        clearable: true,
        options: [
            { value: null, label: 'Semua Tipe Relasi' },
            ...jenis.value.map((j) => ({
                value: j.id,
                label: `${formatKategoriNama(j.nama, j.kode)} (${formatAngka(j.baris)})`,
            })),
        ],
        col: 6,
    },
    {
        key: 'status',
        label: 'Status Integritas',
        type: 'select',
        icon: 'shield-check',
        placeholder: 'Semua Status',
        clearable: true,
        options: [
            { value: 'semua', label: 'Semua Status' },
            { value: 'anomali', label: 'Hanya Anomali / Bermasalah' },
            { value: 'normal', label: 'Hanya Normal & Valid' },
        ],
        col: 6,
    },
]);

const filterValuesTabel = computed(() => ({
    tipe_id: saringan.tipe_id,
    status: saringan.hanya_anomali ? 'anomali' : (saringanFilterStatus.value || 'semua'),
}));

const quickChipsTabel = computed(() => {
    const chips = [
        {
            id: 'semua',
            label: 'Semua Relasi',
            value: null,
            badge: totalRelasi.value,
            icon: 'diagram-3-fill',
        },
        ...jenis.value.map((j) => ({
            id: `jenis-${j.id}`,
            label: formatKategoriNama(j.nama, j.kode),
            value: j.id,
            badge: j.baris,
            icon: getRelasiIcon(j.kode),
            color: getChipColor(j.kode),
        })),
    ];

    /* Lencananya HANYA anomali berbentuk baris — yang benar-benar bisa ditampilkan tabel
       ketika chip ini ditekan. Memasang angka gabungan di sini berarti menjanjikan baris
       yang penyaringnya tidak punya cara menemukannya. */
    if (anomaliBaris.value > 0) {
        chips.push({
            id: 'anomali',
            label: 'Isu Anomali',
            value: 'anomali',
            badge: anomaliBaris.value,
            icon: 'exclamation-triangle-fill',
            color: 'rose',
            ping: true,
        });
    }

    return chips;
});

const activeChipTabel = computed(() => {
    if (saringan.hanya_anomali) return 'anomali';
    return saringan.tipe_id;
});

function onChipClickTabel(chip) {
    if (chip.value === 'anomali') {
        saringAnomaliDanKeTabel();
    } else {
        saringan.hanya_anomali = false;
        saringanFilterStatus.value = 'semua';
        saringan.tipe_id = chip.value;
        void muatDaftar(1);
    }
}

function onFilterChangeTabel({ key, value }) {
    if (key === 'tipe_id') {
        saringan.tipe_id = value;
    } else if (key === 'status') {
        saringanFilterStatus.value = value;
        saringan.hanya_anomali = value === 'anomali';
    }
    void muatDaftar(1);
}

// =============================================================================
// HCIS TABLE INTEGRATION (TABLE DATA MODE 1)
// =============================================================================
const kolomTabel = computed(() => [
    { key: 'subjek', label: 'Karyawan (Subjek)', width: '28%' },

    /* Judul kolomnya menyebut ARAHNYA, bukan sekadar "Hubungan Relasi". Dibaca
       kiri-ke-kanan, "Subjek | Atasan | Pihak Terkait" berarti kebalikan dari yang
       sebenarnya tersimpan. */
    { key: 'relasi', label: 'Pihak Terkait Adalah…', width: '18%', align: 'center' },
    { key: 'terkait', label: 'Pihak Terkait', width: '28%' },
    { key: 'berlaku', label: 'Masa Berlaku', width: '14%' },
    { key: 'sumber', label: 'Sumber Data', width: '12%' },
    { key: 'aksi', label: 'Aksi', width: '140px', align: 'center' },
]);

const barisTabel = computed(() => {
    return baris.value.map((r) => ({
        ...r,
        rowClass: (r.ke_diri_sendiri || r.terkait_nonaktif) ? 'is-anomali-row' : '',
    }));
});

function onUbahHalaman(newPage) {
    if (halaman.value !== newPage) {
        void muatDaftar(newPage);
    }
}

function onUbahUkuranHalaman(newSize) {
    perHalaman.value = newSize;
    void muatDaftar(1);
}

// =============================================================================
// BATCH HUB & PEER MESH CANDIDATE FILTERS
// =============================================================================
const batchCandidateFilterValues = computed(() => ({
    divisi: batchFilterDivisi.value,
    departemen: batchFilterDept.value,
}));

const batchCandidateFilters = computed(() => [
    {
        key: 'divisi',
        label: 'Divisi',
        type: 'select',
        icon: 'building',
        placeholder: 'Semua Divisi',
        clearable: true,
        options: [
            { value: '', label: 'Semua Divisi' },
            ...opsiOrganisasi.divisi.map((d) => ({ value: d, label: d })),
        ],
        col: 6,
    },
    {
        key: 'departemen',
        label: 'Departemen',
        type: 'select',
        icon: 'diagram-2',
        placeholder: 'Semua Departemen',
        clearable: true,
        options: [
            { value: '', label: 'Semua Departemen' },
            ...opsiOrganisasi.departemen.map((dp) => ({ value: dp, label: dp })),
        ],
        col: 6,
    },
]);

function onBatchCandidateFilterChange({ key, value }) {
    if (key === 'divisi') batchFilterDivisi.value = value || '';
    if (key === 'departemen') batchFilterDept.value = value || '';
    void cariBatchKaryawan();
}

function resetBatchCandidateFilters() {
    batchSearchKata.value = '';
    batchFilterDivisi.value = '';
    batchFilterDept.value = '';
    void cariBatchKaryawan();
}

const peerCandidateFilterValues = computed(() => ({
    departemen: peerFilterDept.value,
}));

const peerCandidateFilters = computed(() => [
    {
        key: 'departemen',
        label: 'Departemen',
        type: 'select',
        icon: 'diagram-2',
        placeholder: 'Semua Departemen',
        clearable: true,
        options: [
            { value: '', label: 'Semua Departemen' },
            ...opsiOrganisasi.departemen.map((dp) => ({ value: dp, label: dp })),
        ],
        col: 12,
    },
]);

function onPeerCandidateFilterChange({ key, value }) {
    if (key === 'departemen') peerFilterDept.value = value || '';
    void cariPeerKaryawan();
}

function resetPeerCandidateFilters() {
    peerSearchKata.value = '';
    peerFilterDept.value = '';
    void cariPeerKaryawan();
}

// =============================================================================
// API REQUESTS & ACTIONS
// =============================================================================
/* ---------------------------------------------------------------------------
 | Export Excel. Kategori dipilih per centang (bawaan: semua); saringan diisi dari
 | saringan daftar saat dialog dibuka. Diunduh lewat fetch supaya galat 422 (mis.
 | melebihi batas baris) tampil di dialog, bukan sebagai halaman JSON.
 * ------------------------------------------------------------------------- */
/* Modal export ada di OrgEksporDialog (komponen Hcis + cari karyawan ke server). */
const ekspor = reactive({ terbuka: false });

function bukaEkspor() {
    ekspor.terbuka = true;
}

async function minta(url, opsi = {}) {
    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(opsi.headers ?? {}) },
        ...opsi,
    });
    const isi = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(isi?.message || isi?.result || `Permintaan gagal (${res.status}).`);
    }
    return isi?.result ?? isi?.data ?? isi;
}

// =============================================================================
// VISUAL TREE METHODS (ZERO-LAG DYNAMIC HIERARCHY)
// =============================================================================
async function muatPohonVisual(karyawan, resetTrail = true) {
    if (!karyawan || (!karyawan.kode_karyawan && !karyawan.code)) return;
    memuatVisualTree.value = true;

    const kodePerusahaan = karyawan.kode_perusahaan || karyawan.company || '1';
    const kodeKaryawan = karyawan.kode_karyawan || karyawan.code || karyawan.nik;
    const nama = karyawan.nama || karyawan.name || kodeKaryawan;

    try {
        const ekosistem = await minta(
            `/system/organisasi/relasi/ekosistem?kode_perusahaan=${encodeURIComponent(kodePerusahaan)}&kode_karyawan=${encodeURIComponent(kodeKaryawan)}`,
        );

        const prof = ekosistem?.profil || {};
        const centerNode = {
            id: `${prof.kode_perusahaan || kodePerusahaan}|${prof.kode_karyawan || kodeKaryawan}`,
            kode_perusahaan: prof.kode_perusahaan || kodePerusahaan,
            kode_karyawan: prof.kode_karyawan || kodeKaryawan,
            company: prof.kode_perusahaan || kodePerusahaan,
            code: prof.kode_karyawan || kodeKaryawan,
            name: prof.nama || nama,
            title: prof.nama_level || prof.nama_posisi || 'Karyawan',
            department: prof.divisi || prof.departemen || 'Unit Organisasi',
            nik: prof.kode_karyawan || kodeKaryawan,
            avatar: null,
            children: [],
        };

        const directBawahan = Array.isArray(ekosistem?.bawahan) ? ekosistem.bawahan : [];
        const childNodes = directBawahan.map((b) => ({
            id: `${b.kode_perusahaan}|${b.kode_karyawan}`,
            kode_perusahaan: b.kode_perusahaan,
            kode_karyawan: b.kode_karyawan,
            company: b.kode_perusahaan,
            code: b.kode_karyawan,
            name: b.nama || b.kode_karyawan,
            title: b.nama_level || 'Bawahan Langsung',
            department: b.divisi || b.departemen || prof.divisi || 'Tim',
            nik: b.kode_karyawan,
            avatar: null,
            children: [],
        }));

        centerNode.children = childNodes;

        // Pre-fetch Level 2 subordinates if subordinate count is moderate (<= 20) for instant expandability
        if (childNodes.length > 0 && childNodes.length <= 20) {
            const visited = new Set([centerNode.id]);
            childNodes.forEach((c) => visited.add(c.id));

            await Promise.all(
                childNodes.map(async (child) => {
                    try {
                        const subs = await minta(
                            `/system/organisasi/relasi/daftar-bawahan?kode_perusahaan=${encodeURIComponent(child.kode_perusahaan)}&kode_karyawan=${encodeURIComponent(child.kode_karyawan)}`,
                        );
                        if (Array.isArray(subs) && subs.length > 0) {
                            child.children = subs
                                .filter((s) => !visited.has(`${s.kode_perusahaan}|${s.kode_karyawan}`))
                                .map((s) => {
                                    const subId = `${s.kode_perusahaan}|${s.kode_karyawan}`;
                                    visited.add(subId);
                                    return {
                                        id: subId,
                                        kode_perusahaan: s.kode_perusahaan,
                                        kode_karyawan: s.kode_karyawan,
                                        company: s.kode_perusahaan,
                                        code: s.kode_karyawan,
                                        name: s.nama || s.kode_karyawan,
                                        title: s.nama_level || 'Bawahan',
                                        department: child.department,
                                        nik: s.kode_karyawan,
                                        avatar: null,
                                        children: [],
                                    };
                                });
                        }
                    } catch {
                        // ignore gracefully
                    }
                }),
            );
        }

        // True 360° Tree: If employee has Atasan, include the Atasan above him
        const atasanList = Array.isArray(ekosistem?.atasan) ? ekosistem.atasan : [];
        let rootToRender = centerNode;

        if (atasanList.length > 0) {
            const primaryAtasan = atasanList[0];
            const atasanNode = {
                id: `${primaryAtasan.kode_perusahaan}|${primaryAtasan.kode_karyawan}`,
                kode_perusahaan: primaryAtasan.kode_perusahaan,
                kode_karyawan: primaryAtasan.kode_karyawan,
                company: primaryAtasan.kode_perusahaan,
                code: primaryAtasan.kode_karyawan,
                name: primaryAtasan.nama || primaryAtasan.kode_karyawan,
                title: `${primaryAtasan.nama_level || 'Atasan'} (Atasan Langsung)`,
                department: primaryAtasan.divisi || primaryAtasan.departemen || prof.divisi || 'Unit Organisasi',
                nik: primaryAtasan.kode_karyawan,
                avatar: null,
                children: [centerNode],
            };
            rootToRender = atasanNode;
        }

        visualTreeData.value = rootToRender;
        visualRootKaryawan.value = {
            kode_perusahaan: prof.kode_perusahaan || kodePerusahaan,
            kode_karyawan: prof.kode_karyawan || kodeKaryawan,
            nama: prof.nama || nama,
            nama_level: prof.nama_level,
            departemen: prof.departemen,
            divisi: prof.divisi,
        };

        if (resetTrail) {
            visualTrail.value = [{
                kode_perusahaan: prof.kode_perusahaan || kodePerusahaan,
                kode_karyawan: prof.kode_karyawan || kodeKaryawan,
                nama: prof.nama || nama,
            }];
        }
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal memuat visualisasi bagan struktur.', 'danger');
    } finally {
        memuatVisualTree.value = false;
    }
}

function resetVisualisasi() {
    visualFilterDept.value = '';
    daftarPimpinanDept.value = [];
    visualPimpinanKey.value = '';
    visualRootKaryawan.value = null;
    visualTreeData.value = null;
    visualTrail.value = [];
}

async function pilihDepartemenVisual(dept) {
    visualFilterDept.value = dept || '';
    visualRootKaryawan.value = null;
    visualPimpinanKey.value = '';

    if (!dept) {
        resetVisualisasi();
        return;
    }

    memuatVisualTree.value = true;
    memuatPimpinanDept.value = true;

    try {
        const [resPohon, resKandidat] = await Promise.all([
            minta(`/system/organisasi/relasi/pohon-departemen?departemen=${encodeURIComponent(dept)}`),
            minta(`/system/organisasi/relasi/cari-karyawan?departemen=${encodeURIComponent(dept)}&limit=100`).catch(() => null),
        ]);

        visualTreeData.value = resPohon?.pohon ?? null;
        visualTrail.value = [{
            name: `Departemen ${dept}`,
            is_dept: true,
            dept,
        }];

        if (resKandidat?.baris) {
            daftarPimpinanDept.value = resKandidat.baris;
        }
    } catch (e) {
        tampilkanToast(e?.message || `Gagal memuat struktur departemen ${dept}.`, 'danger');
        visualTreeData.value = null;
    } finally {
        memuatVisualTree.value = false;
        memuatPimpinanDept.value = false;
    }
}

function onVisualDeptChange(dept) {
    void pilihDepartemenVisual(dept);
}

function onVisualPimpinanChange(val) {
    if (!val) return;
    const found = daftarPimpinanDept.value.find(
        (p) => `${p.kode_perusahaan}|${p.kode_karyawan}` === val,
    );
    if (found) {
        visualRootKaryawan.value = found;
        visualTrail.value = [found];
        void muatPohonVisual(found, true);
    }
}

async function onVisualRootSelected(karyawan) {
    if (!karyawan) {
        resetVisualisasi();
        return;
    }

    if (karyawan.departemen) {
        visualFilterDept.value = karyawan.departemen;
        try {
            const res = await minta(
                `/system/organisasi/relasi/cari-karyawan?departemen=${encodeURIComponent(karyawan.departemen)}&limit=100`,
            );
            daftarPimpinanDept.value = res?.baris ?? [];
            visualPimpinanKey.value = `${karyawan.kode_perusahaan}|${karyawan.kode_karyawan}`;
        } catch {
            // ignore
        }
    } else {
        visualFilterDept.value = '';
        daftarPimpinanDept.value = [];
        visualPimpinanKey.value = '';
    }

    visualRootKaryawan.value = karyawan;
    visualTrail.value = [karyawan];
    await muatPohonVisual(karyawan, true);
}

function onVisualNodeAction(payload, maybeNode) {
    const actionKey = typeof payload === 'object' && payload !== null ? (payload.action || payload.key) : payload;
    const node = (typeof payload === 'object' && payload !== null && payload.node) ? payload.node : maybeNode;
    if (!node) return;

    if (actionKey === 'focus') {
        const item = {
            kode_perusahaan: node.kode_perusahaan || node.company,
            kode_karyawan: node.kode_karyawan || node.code || node.nik,
            nama: node.name,
        };
        visualTrail.value.push(item);
        void muatPohonVisual(item, false);
    } else if (actionKey === 'inspector') {
        tabUtama.value = 'manage';
        tabMode.value = 'inspector';
        bukaInspectorKaryawan(node.kode_perusahaan || node.company, node.kode_karyawan || node.code || node.nik);
    }
}

function lompatKeTrailVisual(t, idx) {
    visualTrail.value = visualTrail.value.slice(0, idx + 1);
    if (t.is_dept && t.dept) {
        void pilihDepartemenVisual(t.dept);
    } else {
        void muatPohonVisual(t, false);
    }
}

function kembaliKeIndukVisual() {
    if (visualTrail.value.length > 1) {
        visualTrail.value.pop();
        const prev = visualTrail.value[visualTrail.value.length - 1];
        if (prev.is_dept && prev.dept) {
            void pilihDepartemenVisual(prev.dept);
        } else {
            void muatPohonVisual(prev, false);
        }
    }
}

function muatUlangVisualTree() {
    if (visualRootKaryawan.value) {
        void muatPohonVisual(visualRootKaryawan.value, false);
    } else if (visualFilterDept.value) {
        void pilihDepartemenVisual(visualFilterDept.value);
    }
}

async function muat(opsi = {}) {
    memuat.value = true;
    error.value = '';

    try {
        const [dRingkasan, dFilter] = await Promise.all([
            minta('/system/organisasi/relasi/ringkasan'),
            minta('/system/organisasi/relasi/filter-options').catch(() => ({})),
        ]);

        jenis.value = dRingkasan?.jenis ?? [];
        karyawanAktif.value = dRingkasan?.karyawan_aktif ?? 0;
        totalRelasi.value = dRingkasan?.total_relasi ?? 0;
        totalBelumDicabut.value = dRingkasan?.total_belum_dicabut ?? dRingkasan?.total_relasi ?? 0;
        totalDiLuarMasaBerlaku.value = dRingkasan?.total_di_luar_masa_berlaku ?? 0;
        anomali.value = dRingkasan?.anomali ?? {};

        opsiOrganisasi.divisi = dFilter?.divisi ?? [];
        opsiOrganisasi.departemen = dFilter?.departemen ?? [];
        opsiOrganisasi.level = dFilter?.level ?? [];
    } catch (e) {
        error.value = e?.message || 'Gagal memuat ringkasan relasi organisasi.';
    } finally {
        memuat.value = false;
    }

    await muatDaftar(opsi.halamanTetap ? halaman.value : 1);
    void cariBatchKaryawan();
    void cariPeerKaryawan();
}

/* Sesudah menulis relasi, SEMUA yang sedang dibuka ikut segar: tabel di halaman & saringan
   yang sama (dulu dilempar ke halaman 1), panel ekosistem/inspector, dan pohon visual. Dulu
   hanya ringkasan dan tabel yang dimuat ulang, sehingga relasi baru tidak tampil di panel
   orang yang sedang ditelusuri. */
async function segarkanSesudahTulis() {
    await muat({ halamanTetap: true });
    const profil = ekosistemData.value?.profil;
    if (profil) {
        await muatEkosistem(profil.kode_perusahaan, profil.kode_karyawan);
    }
    if (visualRootKaryawan.value) {
        void muatPohonVisual(visualRootKaryawan.value, false);
    }
}

async function muatDaftar(ke = 1) {
    memuatDaftar.value = true;

    try {
        const q = new URLSearchParams({
            halaman: String(ke),
            per_halaman: String(perHalaman.value),
        });

        if (saringan.tipe_id) q.set('tipe_id', String(saringan.tipe_id));
        if (saringan.cari.trim()) q.set('cari', saringan.cari.trim());
        if (saringan.hanya_anomali) q.set('hanya_anomali', '1');

        const d = await minta(`/system/organisasi/relasi/daftar?${q}`);

        baris.value = d?.baris ?? [];
        total.value = d?.total ?? 0;
        halaman.value = d?.halaman ?? ke;
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal memuat daftar relasi.', 'danger');
    } finally {
        memuatDaftar.value = false;
    }
}

function saringJenis(j) {
    saringan.tipe_id = saringan.tipe_id === j.id ? null : j.id;
    void muatDaftar(1);
}

function saringAnomaliDanKeTabel() {
    tabMode.value = 'table';
    saringan.hanya_anomali = true;
    saringan.tipe_id = null;
    saringanFilterStatus.value = 'anomali';
    void muatDaftar(1);
}

function hapusSaringan() {
    saringan.tipe_id = null;
    saringan.cari = '';
    saringan.hanya_anomali = false;
    saringanFilterStatus.value = 'semua';
    void muatDaftar(1);
}

// Checkbox selection in table
function togglePilihSatu(id) {
    const idx = terpilihIds.value.indexOf(id);
    if (idx >= 0) {
        terpilihIds.value.splice(idx, 1);
    } else {
        terpilihIds.value.push(id);
    }
}

function togglePilihSemuaHalaman() {
    if (apakahSemuaHalamanTerpilih.value) {
        const currentIds = baris.value.map((r) => r.id);
        terpilihIds.value = terpilihIds.value.filter((id) => !currentIds.includes(id));
    } else {
        const currentIds = baris.value.map((r) => r.id);
        terpilihIds.value = Array.from(new Set([...terpilihIds.value, ...currentIds]));
    }
}

async function konfirmasiCabutMassal() {
    if (!terpilihIds.value.length) return;
    if (!confirm(`Cabut ${terpilihIds.value.length} relasi yang dipilih? Kebalikannya juga akan otomatis dicabut.`)) {
        return;
    }

    mencabutMassal.value = true;
    try {
        const d = await minta('/system/organisasi/relasi/cabut-massal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                ids: terpilihIds.value,
                alasan: 'Pencabutan massal dari tabel relasi',
            }),
        });

        /* `dicabut` = jumlah RELASI, `baris_dicabut` = jumlah BARIS. Sebelumnya keduanya
           satu angka dan disebut "relasi", jadi satu baris terpilih dilaporkan sebagai
           dua relasi tercabut. */
        const dilewatiCabut = Number(d?.total_dilewati ?? 0);
        const relasiDicabut = Number(d?.dicabut ?? 0);
        const barisDicabut = Number(d?.baris_dicabut ?? relasiDicabut);

        tampilkanToast(
            `Berhasil mencabut ${relasiDicabut} relasi (${barisDicabut} baris, termasuk pasangannya).`
            + (dilewatiCabut > 0
                ? ` ${dilewatiCabut} dilewati — umumnya karena kedua arah satu pasangan ikut terpilih,`
                  + ' dan yang kedua sudah tercabut bersama yang pertama.'
                : ''),
            'success',
        );
        terpilihIds.value = [];
        await segarkanSesudahTulis();
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal mencabut relasi massal.', 'danger');
    } finally {
        mencabutMassal.value = false;
    }
}

// Single Add & Revoke
function bukaTambahTunggal() {
    formTunggal.subjek = null;
    formTunggal.terkait = null;
    formTunggal.kode_relasi = jenis.value[0]?.kode ?? 'ATASAN';
    formTunggal.berlaku_dari = new Date().toISOString().slice(0, 10);
    formTunggal.berlaku_sampai = '';
    formTunggal.catatan = '';
    dialogTambahTunggal.value = true;
}

async function simpanTunggal() {
    if (!formTunggal.subjek || !formTunggal.terkait || !formTunggal.kode_relasi || apakahSubjekSamaDenganTerkaitTunggal.value) return;

    menyimpanTunggal.value = true;
    try {
        const d = await minta('/system/organisasi/relasi', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                kode_relasi: formTunggal.kode_relasi,
                perusahaan_subjek: formTunggal.subjek.kode_perusahaan,
                kode_subjek: formTunggal.subjek.kode_karyawan,
                perusahaan_terkait: formTunggal.terkait.kode_perusahaan,
                kode_terkait: formTunggal.terkait.kode_karyawan,
                berlaku_dari: formTunggal.berlaku_dari || null,
                berlaku_sampai: formTunggal.berlaku_sampai || null,
                catatan: formTunggal.catatan || null,
            }),
        });

        dialogTambahTunggal.value = false;
        tampilkanToast(
            d?.dibuat === 0
                ? 'Relasi tersebut sudah ada di sistem.'
                : `Berhasil menambahkan relasi (${d?.kode_relasi} & kebalikannya).`,
            'success',
        );
        await segarkanSesudahTulis();
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal menyimpan relasi.', 'danger');
    } finally {
        menyimpanTunggal.value = false;
    }
}

function bukaCabutTunggal(r) {
    akanDicabut.value = r;
    alasanCabut.value = '';
    dialogCabutTunggal.value = true;
}

async function cabutTunggal() {
    mencabutTunggal.value = true;
    sibuk.value = akanDicabut.value?.id ?? null;

    try {
        const d = await minta(`/system/organisasi/relasi/${akanDicabut.value.id}/cabut`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ alasan: alasanCabut.value || null }),
        });

        dialogCabutTunggal.value = false;
        tampilkanToast(
            `Relasi ${d?.kode_relasi || ''} dan kebalikannya berhasil dicabut (${d?.dicabut ?? 0} baris).`,
            'success',
        );
        await segarkanSesudahTulis();
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal mencabut relasi.', 'danger');
    } finally {
        mencabutTunggal.value = false;
        sibuk.value = null;
    }
}

// BATCH 1-TO-N ACTIONS
function cariBatchKaryawan() {
    if (jedaBatch) clearTimeout(jedaBatch);
    jedaBatch = setTimeout(async () => {
        memuatCariBatch.value = true;
        dipangkasBatch.value = false;
        try {
            const q = new URLSearchParams({
                limit: '200',
                q: batchSearchKata.value.trim(),
            });
            if (batchFilterDivisi.value) q.set('divisi', batchFilterDivisi.value);
            if (batchFilterDept.value) q.set('departemen', batchFilterDept.value);

            if (batchForm.subjek) {
                q.set('perusahaan_subjek', batchForm.subjek.kode_perusahaan);
                q.set('kode_subjek', batchForm.subjek.kode_karyawan);
            }
            if (batchForm.kode_relasi) q.set('kode_relasi', batchForm.kode_relasi);

            const res = await minta(`/system/organisasi/relasi/cari-karyawan?${q}`);
            hasilPencarianBatch.value = res?.baris ?? [];
            totalCocokBatch.value = res?.total ?? (res?.baris?.length ?? 0);
            dipangkasBatch.value = Boolean(res?.dipangkas);
            sudahTerhubungBatch.value = Number(res?.total_sudah_terhubung ?? 0);
            bentrokArahBatch.value = Number(res?.total_bentrok_arah ?? 0);
        } catch {
            hasilPencarianBatch.value = [];
        } finally {
            memuatCariBatch.value = false;
        }
    }, 250);
}

watch(
    () => [batchForm.subjek?.kode_perusahaan, batchForm.subjek?.kode_karyawan, batchForm.kode_relasi],
    (baruNilai, lamaNilai) => {
        const gantiSubjek = baruNilai[0] !== lamaNilai?.[0] || baruNilai[1] !== lamaNilai?.[1];

        if (gantiSubjek && batchForm.terkaitList.length > 0) {
            const jumlah = batchForm.terkaitList.length;
            batchForm.terkaitList = [];
            tampilkanToast(
                `Subjek diganti, ${jumlah} pilihan sebelumnya dikosongkan — pilihan itu `
                + 'milik subjek yang lama.',
                'danger',
            );
        }

        void cariBatchKaryawan();
    },
);

const dialogSemuaCocok = ref(false);
const menyimpanSemuaCocok = ref(false);

const bisaSemuaYangCocok = computed(() => Boolean(
    batchForm.subjek
    && batchForm.kode_relasi
    && batchForm.kode_relasi !== 'PEER'
    && totalCocokBatch.value > 0
    && (batchFilterDivisi.value || batchFilterDept.value || batchSearchKata.value.trim()),
));

function bukaSemuaYangCocok() {
    dialogSemuaCocok.value = true;
}

async function kirimSemuaYangCocok() {
    menyimpanSemuaCocok.value = true;
    const jumlah = { orang: 0, dibuat: 0, dilewati: 0, berlawanan: 0, gagal: [] };

    try {
        for (const subjek of semuaSubjekBatch.value) {
            try {
                const d = await minta('/system/organisasi/relasi/massal-saringan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        kode_relasi: batchForm.kode_relasi,
                        perusahaan_subjek: subjek.kode_perusahaan,
                        kode_subjek: subjek.kode_karyawan,
                        saringan: {
                            q: batchSearchKata.value.trim(),
                            divisi: batchFilterDivisi.value || null,
                            departemen: batchFilterDept.value || null,
                        },
                        jumlah_dikonfirmasi: totalCocokBatch.value,
                        berlaku_dari: batchForm.berlaku_dari || null,
                        berlaku_sampai: batchForm.berlaku_sampai || null,
                        catatan: batchForm.catatan || null,
                    }),
                });
                jumlah.orang += Number(d?.orang_baru ?? 0);
                jumlah.dibuat += Number(d?.dibuat ?? 0);
                jumlah.dilewati += Number(d?.dilewati ?? 0);
                jumlah.berlawanan += Number(d?.berlawanan_arah ?? 0);
            } catch (e) {
                jumlah.gagal.push(`${subjek.nama}: ${e?.message || 'gagal'}`);
            }
        }

        dialogSemuaCocok.value = false;
        const subjekLebih = semuaSubjekBatch.value.length > 1 ? ` ke ${semuaSubjekBatch.value.length} subjek` : '';
        tampilkanToast(
            `${jumlah.orang} hubungan orang baru${subjekLebih} sebagai ${batchForm.kode_relasi} (${jumlah.dibuat} baris, dua arah)`
            + (jumlah.dilewati > 0 ? `. ${jumlah.dilewati} dilewati karena sudah terhubung.` : '.')
            + (jumlah.berlawanan > 0 ? ` ${jumlah.berlawanan} dilewati karena arahnya bertentangan.` : '')
            + (jumlah.gagal.length ? ` Gagal: ${jumlah.gagal.join(' | ')}` : ''),
            jumlah.gagal.length && !jumlah.dibuat ? 'danger' : 'success',
        );

        batchForm.terkaitList = [];
        await segarkanSesudahTulis();
        void cariBatchKaryawan();
    } finally {
        menyimpanSemuaCocok.value = false;
    }
}

function apakahKaryawanTerpilihBatch(k) {
    return batchForm.terkaitList.some(
        (item) => item.kode_karyawan === k.kode_karyawan && item.kode_perusahaan === k.kode_perusahaan,
    );
}

function terkunciBatch(k) {
    return Boolean(k.sudah_terhubung || k.bentrok_arah);
}

function toggleKaryawanBatch(k) {
    if (k.bentrok_arah) {
        tampilkanToast(
            `${k.nama} sudah tercatat sebagai ${k.bentrok_sebab} bagi subjek ini. `
            + 'Satu pasang orang tidak bisa sekaligus atasan dan bawahan — cabut relasi '
            + 'yang ada dulu kalau strukturnya memang berubah.',
            'danger',
        );
        return;
    }

    if (k.sudah_terhubung) {
        tampilkanToast(
            `${k.nama} sudah terhubung sebagai ${(k.relasi_terpasang ?? []).join(', ')} `
            + 'dengan subjek ini. Cabut dulu relasinya kalau mau diubah.',
            'danger',
        );
        return;
    }

    const idx = batchForm.terkaitList.findIndex(
        (item) => item.kode_karyawan === k.kode_karyawan && item.kode_perusahaan === k.kode_perusahaan,
    );
    if (idx >= 0) {
        batchForm.terkaitList.splice(idx, 1);
        return;
    }

    if (batchForm.terkaitList.length >= BATAS_MASSAL) {
        tampilkanToast(
            `Sudah ${BATAS_MASSAL} orang dipilih — batas satu operasi massal. `
            + 'Kirim yang ini dulu, lalu lanjutkan sisanya.',
            'danger',
        );
        return;
    }

    batchForm.terkaitList.push(k);
}

function pilihSemuaHasilBatch() {
    let masuk = 0;
    let tertolak = 0;
    let terkunci = 0;
    let bentrok = 0;

    for (const k of hasilPencarianBatch.value) {
        if (apakahKaryawanTerpilihBatch(k)) continue;

        if (k.bentrok_arah) {
            bentrok++;
            continue;
        }

        if (k.sudah_terhubung) {
            terkunci++;
            continue;
        }

        if (batchForm.terkaitList.length >= BATAS_MASSAL) {
            tertolak++;
            continue;
        }

        batchForm.terkaitList.push(k);
        masuk++;
    }

    const catatanTerkunci = (terkunci > 0
        ? ` ${terkunci} dilewati karena sudah terhubung dengan subjek ini.`
        : '')
        + (bentrok > 0
            ? ` ${bentrok} dilewati karena arahnya bertentangan (sudah atasan/bawahan).`
            : '');

    if (tertolak > 0) {
        tampilkanToast(
            `${masuk} orang ditambahkan. ${tertolak} tidak masuk karena satu operasi massal `
            + `dibatasi ${BATAS_MASSAL} orang — tiap relasi ditulis dua arah.`
            + catatanTerkunci,
            'danger',
        );
    } else if (masuk > 0) {
        tampilkanToast(`${masuk} orang ditambahkan ke pilihan.${catatanTerkunci}`);
    } else if (terkunci > 0 || bentrok > 0) {
        tampilkanToast(
            `Tidak ada yang bisa ditambahkan.${catatanTerkunci}`,
            'danger',
        );
    }
}

function hapusSatuTerkaitBatch(k) {
    const idx = batchForm.terkaitList.findIndex(
        (item) => item.kode_karyawan === k.kode_karyawan && item.kode_perusahaan === k.kode_perusahaan,
    );
    if (idx >= 0) batchForm.terkaitList.splice(idx, 1);
}

async function eksekusiBatch1ToN() {
    if (!batchForm.subjek || !batchForm.terkaitList.length) return;

    menyimpanBatch.value = true;
    const jumlah = { dibuat: 0, dilewati: 0, berlawanan: 0, contoh: [], gagal: [] };
    try {
        // Satu permintaan per subjek, berurutan: tiap permintaan tetap melewati batas dan
        // penjaga arah di server persis seperti batch satu subjek.
        for (const subjek of semuaSubjekBatch.value) {
            const terkait = batchForm.terkaitList.filter((t) => kunciKaryawan(t) !== kunciKaryawan(subjek));
            if (!terkait.length) continue;
            try {
                const d = await minta('/system/organisasi/relasi/massal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        kode_relasi: batchForm.kode_relasi,
                        perusahaan_subjek: subjek.kode_perusahaan,
                        kode_subjek: subjek.kode_karyawan,
                        daftar_terkait: terkait.map((t) => ({
                            kode_perusahaan: t.kode_perusahaan,
                            kode_karyawan: t.kode_karyawan,
                        })),
                        berlaku_dari: batchForm.berlaku_dari || null,
                        berlaku_sampai: batchForm.berlaku_sampai || null,
                        catatan: batchForm.catatan || null,
                    }),
                });
                jumlah.dibuat += Number(d?.dibuat ?? 0);
                jumlah.dilewati += Number(d?.dilewati ?? 0);
                jumlah.berlawanan += Number(d?.berlawanan_arah ?? 0);
                jumlah.contoh.push(...(d?.berlawanan_contoh ?? []));
            } catch (e) {
                jumlah.gagal.push(`${subjek.nama}: ${e?.message || 'gagal'}`);
            }
        }

        const subjekLebih = semuaSubjekBatch.value.length > 1 ? ` untuk ${semuaSubjekBatch.value.length} subjek` : '';
        tampilkanToast(
            `Berhasil membentuk ${jumlah.dibuat} relasi baru${subjekLebih} (${batchForm.kode_relasi} & kebalikannya)`
            + (jumlah.dilewati > 0 ? `. ${jumlah.dilewati} dilewati karena relasinya sudah ada.` : '.')
            + (jumlah.berlawanan > 0
                ? ` ${jumlah.berlawanan} arahnya bertentangan: ${jumlah.contoh.slice(0, 3).join('; ')}${jumlah.contoh.length > 3 ? ' …' : ''}`
                : '')
            + (jumlah.gagal.length ? ` Gagal: ${jumlah.gagal.join(' | ')}` : ''),
            jumlah.gagal.length && !jumlah.dibuat ? 'danger' : 'success',
        );
        if (!jumlah.gagal.length) {
            batchForm.terkaitList = [];
        }
        await segarkanSesudahTulis();
    } finally {
        menyimpanBatch.value = false;
    }
}

// PEER MESH ACTIONS
function cariPeerKaryawan() {
    if (jedaPeer) clearTimeout(jedaPeer);
    jedaPeer = setTimeout(async () => {
        memuatCariPeer.value = true;
        dipangkasPeer.value = false;
        try {
            const q = new URLSearchParams({
                limit: '200',
                q: peerSearchKata.value.trim(),
            });
            if (peerFilterDept.value) q.set('departemen', peerFilterDept.value);

            const res = await minta(`/system/organisasi/relasi/cari-karyawan?${q}`);
            hasilPencarianPeer.value = res?.baris ?? [];
            totalCocokPeer.value = res?.total ?? (res?.baris?.length ?? 0);
            dipangkasPeer.value = Boolean(res?.dipangkas);
        } catch {
            hasilPencarianPeer.value = [];
        } finally {
            memuatCariPeer.value = false;
        }
    }, 250);
}

function apakahSudahAdaDiPeerMesh(k) {
    return peerMeshList.value.some(
        (item) => item.kode_karyawan === k.kode_karyawan && item.kode_perusahaan === k.kode_perusahaan,
    );
}

function tambahKePeerMesh(k) {
    if (apakahSudahAdaDiPeerMesh(k)) return true;
    if (peerMeshList.value.length >= BATAS_PEER) return false;
    peerMeshList.value.push(k);
    return true;
}

function tambahkanSemuaHasilPeer() {
    let masuk = 0;
    let tertolak = 0;

    for (const k of hasilPencarianPeer.value) {
        const sudah = apakahSudahAdaDiPeerMesh(k);
        if (tambahKePeerMesh(k)) {
            if (!sudah) masuk++;
        } else {
            tertolak++;
        }
    }

    if (tertolak > 0) {
        tampilkanToast(
            `${masuk} orang ditambahkan. ${tertolak} tidak masuk karena kelompok Peer `
            + `dibatasi ${BATAS_PEER} orang — jaringnya tumbuh kuadratik.`,
            'danger',
        );
    } else if (masuk > 0) {
        tampilkanToast(`${masuk} orang ditambahkan ke kelompok Peer.`);
    }
}

function tambahSatuKePeerMesh(k) {
    if (apakahSudahAdaDiPeerMesh(k)) return;

    if (!tambahKePeerMesh(k)) {
        tampilkanToast(
            `Kelompok Peer sudah berisi ${BATAS_PEER} orang — batasnya. `
            + 'Keluarkan salah satu dulu, atau bentuk kelompok ini lebih dulu.',
            'danger',
        );
    }
}

function hapusSatuPeerMesh(p) {
    const idx = peerMeshList.value.findIndex(
        (item) => item.kode_karyawan === p.kode_karyawan && item.kode_perusahaan === p.kode_perusahaan,
    );
    if (idx >= 0) peerMeshList.value.splice(idx, 1);
}

async function eksekusiPeerMesh() {
    if (peerMeshList.value.length < 2) return;

    menyimpanPeerMesh.value = true;
    try {
        const d = await minta('/system/organisasi/relasi/peer-cluster', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                anggota: peerMeshList.value.map((p) => ({
                    kode_perusahaan: p.kode_perusahaan,
                    kode_karyawan: p.kode_karyawan,
                })),
            }),
        });

        /* Pasangan yang ditolak karena sudah punya hubungan lain WAJIB disebut. Sebelumnya
           ia hilang tanpa jejak, dan admin membaca "kelompok Peer terbentuk" untuk kelompok
           yang sebagian tidak terbentuk. */
        const dilewatiPeer = Number(d?.total_dilewati ?? 0);
        const bentrokPeer = Number(d?.berlawanan_arah ?? 0);

        tampilkanToast(
            `${d?.total_terhubung ?? 0} pasangan Peer terhubung dari ${d?.total_anggota ?? 0} anggota.`
            + (dilewatiPeer > 0
                ? ` ${dilewatiPeer} orang dilewati karena datanya tidak ditemukan atau tidak aktif.`
                : '')
            + (bentrokPeer > 0
                ? ` ${bentrokPeer} pasangan TIDAK terhubung karena sudah punya hubungan lain: `
                  + `${(d.berlawanan_contoh ?? []).join('; ')}`
                  + (bentrokPeer > (d.berlawanan_contoh ?? []).length ? ' …' : '')
                : ''),
            (dilewatiPeer > 0 || bentrokPeer > 0) ? 'danger' : 'success',
        );
        peerMeshList.value = [];
        await segarkanSesudahTulis();
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal membentuk kelompok Peer.', 'danger');
    } finally {
        menyimpanPeerMesh.value = false;
    }
}

// TRANSFER ACTIONS
async function muatBawahanAtasanLama(k) {
    if (!k) {
        daftarBawahanLama.value = [];
        transferForm.bawahanDipilih = [];
        return;
    }

    memuatBawahanLama.value = true;
    try {
        const res = await minta(
            `/system/organisasi/relasi/daftar-bawahan?kode_perusahaan=${encodeURIComponent(k.kode_perusahaan)}&kode_karyawan=${encodeURIComponent(k.kode_karyawan)}`,
        );
        daftarBawahanLama.value = res ?? [];
        transferForm.bawahanDipilih = [...daftarBawahanLama.value]; // Default select all
    } catch {
        daftarBawahanLama.value = [];
        transferForm.bawahanDipilih = [];
    } finally {
        memuatBawahanLama.value = false;
    }
}

function apakahBawahanDipilihTransfer(b) {
    return transferForm.bawahanDipilih.some(
        (item) => item.kode_karyawan === b.kode_karyawan && item.kode_perusahaan === b.kode_perusahaan,
    );
}

function toggleBawahanTransfer(b) {
    const idx = transferForm.bawahanDipilih.findIndex(
        (item) => item.kode_karyawan === b.kode_karyawan && item.kode_perusahaan === b.kode_perusahaan,
    );
    if (idx >= 0) {
        transferForm.bawahanDipilih.splice(idx, 1);
    } else {
        transferForm.bawahanDipilih.push(b);
    }
}

function togglePilihSemuaBawahanTransfer() {
    if (transferForm.bawahanDipilih.length === daftarBawahanLama.value.length) {
        transferForm.bawahanDipilih = [];
    } else {
        transferForm.bawahanDipilih = [...daftarBawahanLama.value];
    }
}

async function eksekusiTransfer() {
    if (!transferForm.atasanLama || !transferForm.atasanBaru || !transferForm.bawahanDipilih.length) return;

    menjalankanTransfer.value = true;
    try {
        const d = await minta('/system/organisasi/relasi/transfer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                perusahaan_lama: transferForm.atasanLama.kode_perusahaan,
                kode_lama: transferForm.atasanLama.kode_karyawan,
                perusahaan_baru: transferForm.atasanBaru.kode_perusahaan,
                kode_baru: transferForm.atasanBaru.kode_karyawan,
                daftar_bawahan: transferForm.bawahanDipilih.map((b) => ({
                    kode_perusahaan: b.kode_perusahaan,
                    kode_karyawan: b.kode_karyawan,
                })),
                cabut_lama: transferForm.cabutLama,
                alasan: transferForm.alasan || 'Alih tugas struktur / mutasi atasan',
                berlaku_dari: transferForm.berlaku_dari || null,
            }),
        });

        tampilkanToast(
            `Sukses mengalihkan ${d?.dialihkan ?? 0} dari ${d?.total_dikirim ?? 0} bawahan`
            + ` (${d?.atasan_lama} → ${d?.atasan_baru})`
            + (Number(d?.dilewati_tak_ditemukan ?? 0) > 0
                ? `. ${d.dilewati_tak_ditemukan} dilewati: karyawannya tidak ditemukan atau tidak aktif.`
                : '')
            + (Number(d?.dilewati_atasan_baru_sendiri ?? 0) > 0
                ? ` ${d.dilewati_atasan_baru_sendiri} dilewati: orangnya atasan baru itu sendiri.`
                : '')
            + (Number(d?.dilewati_data_tak_lengkap ?? 0) > 0
                ? ` ${d.dilewati_data_tak_lengkap} dilewati: datanya tidak lengkap.`
                : '')
            /* Yang bertentangan arah TIDAK dialihkan dan atasan lamanya juga tidak
               dicabut — keduanya keputusan yang harus sampai ke admin dengan nama, bukan
               tenggelam di antara angka "dialihkan". */
            + (Number(d?.berlawanan_arah ?? 0) > 0
                ? ` ${d.berlawanan_arah} TIDAK dialihkan karena arahnya bertentangan: `
                  + `${(d.berlawanan_contoh ?? []).join('; ')}`
                  + (d.berlawanan_arah > (d.berlawanan_contoh ?? []).length ? ' …' : '')
                : ''),
            Number(d?.berlawanan_arah ?? 0) > 0 ? 'danger' : 'success',
        );
        transferForm.atasanLama = null;
        transferForm.atasanBaru = null;
        daftarBawahanLama.value = [];
        transferForm.bawahanDipilih = [];
        await segarkanSesudahTulis();
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal menjalankan transfer struktur.', 'danger');
    } finally {
        menjalankanTransfer.value = false;
    }
}

// INSPECTOR ACTIONS
async function onInspectorKaryawanSelected(k) {
    if (!k) {
        ekosistemData.value = null;
        return;
    }
    await muatEkosistem(k.kode_perusahaan, k.kode_karyawan);
}

function bukaInspectorKaryawan(perusahaan, kode) {
    tabUtama.value = 'manage';
    tabMode.value = 'inspector';
    inspectorKaryawanPilihan.value = {
        kode_perusahaan: perusahaan,
        kode_karyawan: kode,
        nama: kode,
    };
    void muatEkosistem(perusahaan, kode);
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function kunciSimpul(kategori, orang) {
    return `${kategori}|${orang?.kode_perusahaan ?? ''}|${orang?.kode_karyawan ?? ''}`;
}

function sorotDariRoda({ kategori, orang }) {
    const kunci = kunciSimpul(kategori, orang);
    kunciSorot.value = kunci;

    if (typeof document !== 'undefined') {
        const el = document.querySelector(`[data-simpul="${CSS.escape(kunci)}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* Sorotannya padam sendiri. Sorotan yang menetap berubah arti dari "yang barusan
       Anda tunjuk" menjadi "yang terpilih", dan tidak ada yang memilih apa pun di sini. */
    if (jedaSorot) clearTimeout(jedaSorot);
    jedaSorot = setTimeout(() => {
        if (kunciSorot.value === kunci) kunciSorot.value = '';
    }, 2600);
}

async function muatEkosistem(perusahaan, kode) {
    memuatInspector.value = true;
    kunciSorot.value = '';
    try {
        const res = await minta(
            `/system/organisasi/relasi/ekosistem?kode_perusahaan=${encodeURIComponent(perusahaan)}&kode_karyawan=${encodeURIComponent(kode)}`,
        );
        ekosistemData.value = res;
        if (res?.profil) {
            inspectorKaryawanPilihan.value = {
                kode_perusahaan: res.profil.kode_perusahaan,
                kode_karyawan: res.profil.kode_karyawan,
                nama: res.profil.nama,
                nama_level: res.profil.nama_level,
                divisi: res.profil.divisi,
                departemen: res.profil.departemen,
            };
        }
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal memuat ekosistem karyawan.', 'danger');
        ekosistemData.value = null;
    } finally {
        memuatInspector.value = false;
    }
}

function lihatPohonVisualDariInspector() {
    if (!ekosistemData.value?.profil) return;
    const prof = ekosistemData.value.profil;
    tabUtama.value = 'visual';
    void onVisualRootSelected(prof);
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function bukaQuickAddInspector(kodeRelasi) {
    if (!ekosistemData.value?.profil) return;
    formTunggal.subjek = {
        kode_perusahaan: ekosistemData.value.profil.kode_perusahaan,
        kode_karyawan: ekosistemData.value.profil.kode_karyawan,
        nama: ekosistemData.value.profil.nama,
    };
    formTunggal.terkait = null;
    formTunggal.kode_relasi = kodeRelasi;
    formTunggal.berlaku_dari = new Date().toISOString().slice(0, 10);
    formTunggal.berlaku_sampai = '';
    formTunggal.catatan = '';
    dialogTambahTunggal.value = true;
}

async function cabutRelasiDariInspector(item) {
    if (!confirm(`Cabut relasi dengan ${item.nama}?`)) return;

    try {
        await minta(`/system/organisasi/relasi/${item.id}/cabut`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ alasan: 'Dicabut dari Inspector 360°' }),
        });

        tampilkanToast(`Relasi dengan ${item.nama} berhasil dicabut.`, 'success');
        if (ekosistemData.value?.profil) {
            await muatEkosistem(ekosistemData.value.profil.kode_perusahaan, ekosistemData.value.profil.kode_karyawan);
        }
        await muatDaftar(halaman.value);
    } catch (e) {
        tampilkanToast(e?.message || 'Gagal mencabut relasi.', 'danger');
    }
}

onMounted(() => {
    cekUkuranLayar();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', cekUkuranLayar);
    }
    void muat();
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});
</script>

<style scoped>
.ro-label-hint {
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
}
.ro-subjek-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.45rem;
}
.ro-subjek-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 999px;
    background: rgba(238, 242, 255, 0.9);
    font-size: 0.74rem;
}
.ro-subjek-chip small {
    color: var(--hcis-text-muted, #64748b);
}
.ro-subjek-chip button {
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1;
    color: inherit;
}
.ro-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0;
    padding-bottom: 2rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

@media (max-width: 640px) {
    .ro-page {
        gap: 0.85rem;
        padding: 0;
        padding-bottom: 1.5rem;
    }
}

/* Skeleton Cockpit */
.ro-skeleton-cockpit {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

/* Empty Alert */
.ro-empty-alert-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    align-items: flex-start;
}

.ro-empty-alert-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* ========================================================================= */
/* EXECUTIVE TL;DR COCKPIT SECTION                                           */
/* ========================================================================= */
.ro-cockpit-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.ro-stat-group {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
}

@media (max-width: 992px) {
    .ro-stat-group {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
    }
}

@media (max-width: 640px) {
    .ro-cockpit-section {
        gap: 0.4rem;
    }

    .ro-stat-group {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.25rem !important;
    }

    .ro-stat-group :deep(.hcis-stat),
    .ro-stat-group .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .ro-stat-group :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem !important;
    }

    .ro-stat-group :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
        line-height: 1.15 !important;
        font-weight: 800 !important;
    }

    .ro-stat-group :deep(.hcis-stat__label) {
        font-size: 0.64rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.02em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .ro-stat-group :deep(.hcis-stat__caption) {
        font-size: 0.58rem !important;
        margin-top: 0.05rem !important;
        color: var(--hcis-slate-500, #64748b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .ro-stat-group :deep(.hcis-stat__icon-box) {
        width: 1.35rem !important;
        height: 1.35rem !important;
        border-radius: 5px !important;
    }

    .ro-skeleton-cockpit {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
    }

    .ro-anomali-alert {
        margin-top: 0.35rem !important;
        padding: 0.55rem 0.65rem !important;
    }

    .ro-anomali-list {
        gap: 0.35rem !important;
    }

    .ro-anomali-desc {
        font-size: 0.68rem !important;
    }

    .ro-anomali-head {
        font-size: 0.76rem !important;
    }

    .ro-tldr-visual-card {
        padding: 0.85rem 0.75rem !important;
        gap: 0.85rem !important;
    }

    .ro-batch-left-col,
    .ro-batch-right-col,
    .ro-peer-left,
    .ro-peer-right,
    .ro-transfer-step-col,
    .ro-sphere-column {
        padding: 0.85rem 0.75rem !important;
        border-radius: 14px !important;
        gap: 0.75rem !important;
    }

    .ro-batch-submode-switcher {
        padding: 0.25rem !important;
        border-radius: 12px !important;
    }

    .ro-submode-btn {
        padding: 0.5rem 0.6rem !important;
        font-size: 0.76rem !important;
        gap: 0.35rem !important;
    }

    .ro-batch-intro {
        padding: 0.65rem 0.75rem !important;
        font-size: 0.74rem !important;
        border-radius: 12px !important;
    }

    .ro-batch-step-header {
        padding-bottom: 0.5rem !important;
        gap: 0.5rem !important;
    }

    .ro-batch-step-badge {
        width: 1.45rem !important;
        height: 1.45rem !important;
        font-size: 0.7rem !important;
    }

    .ro-batch-step-title {
        font-size: 0.82rem !important;
    }

    .ro-batch-step-subtitle {
        font-size: 0.65rem !important;
    }

    .ro-quick-chip-btn {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.68rem !important;
    }

    .ro-type-selector-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
    }

    .ro-type-card-opt {
        padding: 0.5rem 0.65rem !important;
        border-radius: 10px !important;
    }

    .ro-type-card-title {
        font-size: 0.74rem !important;
    }

    .ro-type-card-desc {
        font-size: 0.62rem !important;
    }

    .ro-batch-results-box {
        max-height: 18rem !important;
        border-radius: 12px !important;
    }

    .ro-batch-item-row {
        padding: 0.55rem 0.65rem !important;
        gap: 0.6rem !important;
    }

    .ro-peer-header-line {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.35rem !important;
    }

    .ro-peer-calc-badge,
    .ro-peer-cap-badge {
        width: fit-content !important;
        font-size: 0.66rem !important;
    }

    .ro-table-pagination-footer {
        justify-content: center !important;
        padding: 0.65rem 0.5rem !important;
    }
}

.ro-tldr-visual-card {
    display: grid;
    grid-template-columns: 290px 1fr 310px;
    gap: 1.5rem;
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.05);
    backdrop-filter: blur(12px);
}

@media (max-width: 1200px) {
    .ro-tldr-visual-card {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}

/* Gauge Column */
.ro-tldr-gauge-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding-right: 1.5rem;
    border-right: 1px solid #e2e8f0;
}

@media (max-width: 1200px) {
    .ro-tldr-gauge-col {
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
        padding-right: 0;
        padding-bottom: 1.25rem;
    }
}

.ro-gauge-wrapper {
    position: relative;
    width: 116px;
    height: 116px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ro-gauge-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.ro-gauge-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 8;
}

.ro-gauge-fill {
    fill: none;
    stroke: #4f46e5;
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.ro-gauge-content {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.ro-gauge-val {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}

.ro-gauge-lbl {
    font-size: 0.65rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 0.25rem;
}

.ro-gauge-legend {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    width: 100%;
}

.ro-legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.76rem;
    color: #475569;
}

.ro-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}

.ro-legend-dot.is-connected {
    background: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.ro-legend-dot.is-unconnected {
    background: #cbd5e1;
}

/* Category Breakdown Column */
.ro-tldr-breakdown-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
    min-width: 0;
}

.ro-tldr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.ro-tldr-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
    color: #0f172a;
}

.ro-tldr-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
}

.ro-stacked-bar {
    display: flex;
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    gap: 2px;
}

.ro-bar-segment {
    height: 100%;
    transition: width 0.4s ease;
    border-radius: 2px;
}

.ro-category-pills-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
}

.ro-cat-mini-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ro-cat-mini-card:hover {
    border-color: #6366f1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.ro-cat-mini-card.is-selected {
    border-color: #4f46e5;
    background: #f5f3ff;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.ro-cat-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.ro-cat-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}

.ro-cat-name {
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
}

.ro-cat-right {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
    font-size: 0.78rem;
    color: #0f172a;
}

.ro-cat-right small {
    font-size: 0.68rem;
    color: #64748b;
}

/* Health Column */
.ro-tldr-health-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.85rem;
    padding-left: 1.5rem;
    border-left: 1px solid #e2e8f0;
}

@media (max-width: 1200px) {
    .ro-tldr-health-col {
        border-left: none;
        border-top: 1px solid #e2e8f0;
        padding-left: 0;
        padding-top: 1.25rem;
    }
}

.ro-health-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.86rem;
    color: #0f172a;
}

.ro-tldr-health-col.has-issues .ro-health-header {
    color: #b45309;
}

.ro-health-clean {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ro-health-desc {
    margin: 0;
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.45;
}

.ro-clean-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #15803d;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    width: fit-content;
}

.ro-health-issues {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ro-issues-chips {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.ro-issue-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
}

.ro-issue-tag.is-danger {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.ro-issue-tag.is-warning {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.ro-health-btn {
    align-self: flex-start;
}

/* ========================================================================= */
/* SEGMENTED TABS NAVIGATION BAR                                             */
/* ========================================================================= */
.ro-tabs-nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.ro-tabs-nav-bar::-webkit-scrollbar {
    display: none;
}

.ro-tabs-group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 12px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.ro-tabs-group::-webkit-scrollbar {
    display: none;
}

.ro-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
    white-space: nowrap;
}

.ro-tab-btn:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.7);
}

.ro-tab-btn.is-active {
    color: #4338ca;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

.ro-tab-count {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
}

.ro-tab-btn.is-active .ro-tab-count {
    background: #e0e7ff;
    color: #4338ca;
}

.ro-tab-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
    background: #dbeafe;
    color: #1d4ed8;
    text-transform: uppercase;
}

.ro-tab-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.ro-per-page-select-wrap {
    min-width: 105px;
}

/* Floating Bulk Action Bar */
.ro-bulk-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.15rem;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08);
}

.ro-bulk-info {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #991b1b;
}

.ro-bulk-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Table Skeletons & Empty */
.ro-table-loading {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.5rem 0;
}

.ro-table-empty {
    padding: 3.5rem 1rem;
    text-align: center;
}

/* HcisTable Customizations & Pagination Footer */
.ro-table-pagination-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    width: 100%;
    padding: 0.85rem 1.25rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
    background: rgba(248, 250, 252, 0.6);
}

:deep(.hcis-table tr.is-anomali-row td) {
    background: rgba(239, 68, 68, 0.04) !important;
}

:deep(.hcis-table tr.is-interactive:hover td) {
    background: rgba(241, 245, 249, 0.7);
}

.ro-table-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

/* Person Cell */
.ro-person-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.ro-person-details {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.ro-person-name {
    font-size: 0.84rem;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ro-person-name.is-link {
    cursor: pointer;
    transition: color 0.15s ease;
}

.ro-person-name.is-link:hover {
    color: var(--hcis-brand-primary, #0284c7);
    text-decoration: underline;
}

.ro-person-name.is-strikethrough {
    text-decoration: line-through;
    color: #94a3b8;
}

.ro-person-name-line {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ro-person-pills {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.ro-nik-pill {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #e2e8f0;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.ro-level-pill {
    font-size: 0.68rem;
    font-weight: 600;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.ro-divisi-pill {
    font-size: 0.68rem;
    color: #475569;
    background: #f1f5f9;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.ro-pt-pill {
    font-size: 0.65rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}

.ro-status-dead-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #dc2626;
    background: #fee2e2;
    border: 1px solid #fecaca;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
}

.ro-status-self-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid #fde68a;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
}

/* Masa berlaku: baris yang masih hidup tetapi tidak dipakai mesin penugasan. */
.ro-status-expired-pill,
.ro-status-future-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    width: fit-content;
    margin-top: 0.15rem;
    font-size: 0.66rem;
    font-weight: 700;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
}

.ro-status-expired-pill {
    color: #9f1239;
    background: #ffe4e6;
    border: 1px solid #fecdd3;
}

.ro-status-future-pill {
    color: #3730a3;
    background: #e0e7ff;
    border: 1px solid #c7d2fe;
}

/* Kalimat arah relasi. Dipakai di dialog tambah, batch, dan dialog cabut. */
.ro-arah-preview {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.6rem 0.8rem;
    border-radius: 10px;
    border: 1px solid #a7f3d0;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.78rem;
    line-height: 1.4;
}

.ro-relation-arah {
    display: block;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #64748b;
}

/* ========================================================================= */
/* CARD GRID VIEW STYLES (RESPONSIVE FOR MOBILE)                             */
/* ========================================================================= */
.ro-grid-view-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ro-relation-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 0.85rem;
}

.ro-grid-bulk-bar {
    grid-column: 1 / -1;
}

.ro-grid-pagination {
    margin-top: 0.5rem;
    border-radius: 12px;
}

.ro-relation-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.ro-relation-card:hover {
    border-color: #818cf8;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.08);
    transform: translateY(-1px);
}

.ro-relation-card.is-selected {
    border-color: #4f46e5;
    background: #f5f3ff;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.ro-relation-card.is-anomali {
    border-color: #fca5a5;
    background: #fffafa;
}

.ro-relation-card.is-expired {
    opacity: 0.78;
}

.ro-relation-card--skeleton {
    cursor: default;
}

.ro-card-skeleton-head,
.ro-card-skeleton-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ro-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
}

.ro-card-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    user-select: none;
}

.ro-card-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #4f46e5;
}

.ro-card-id-text {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    font-family: monospace;
}

.ro-card-badge-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.ro-card-flow {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    background: rgba(248, 250, 252, 0.85);
    border-radius: 10px;
    padding: 0.6rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.75);
}

.ro-card-node {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.ro-card-node-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.ro-node-role {
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #94a3b8;
    text-transform: uppercase;
}

.ro-node-name {
    font-size: 0.82rem;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.15s ease;
}

.ro-node-name:hover {
    color: #4f46e5;
    text-decoration: underline;
}

.ro-node-meta {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.ro-card-connector {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: 1.15rem;
    padding: 0.05rem 0;
}

.ro-connector-line {
    width: 2px;
    height: 14px;
    background: #cbd5e1;
    border-radius: 1px;
}

.ro-connector-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}

.ro-card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.72rem;
    color: #64748b;
}

.ro-card-validity {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.ro-card-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px solid rgba(226, 232, 240, 0.7);
}

@media (max-width: 640px) {
    .ro-relation-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .ro-relation-card {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
    }

    .ro-card-flow {
        padding: 0.5rem 0.65rem;
    }
}

/* Rincian anomali. */
.ro-anomali-alert {
    margin-top: 0.75rem;
}

.ro-anomali-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ro-anomali-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.ro-anomali-head {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.ro-anomali-count {
    font-variant-numeric: tabular-nums;
    font-weight: 800;
}

.ro-anomali-label {
    font-weight: 600;
}

.ro-anomali-nofilter {
    font-size: 0.7rem;
    font-style: italic;
    opacity: 0.75;
}

.ro-anomali-desc {
    font-size: 0.74rem;
    line-height: 1.4;
    opacity: 0.85;
}

/* Relation Badge Wrap */
.ro-relation-badge-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.ro-utama-badge {
    font-size: 0.62rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.02rem 0.3rem;
    border-radius: 4px;
    text-transform: uppercase;
}

/* Date Cell */
.ro-date-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.75rem;
}

.ro-date-line {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #1e293b;
    font-weight: 600;
}

.ro-date-until {
    font-size: 0.7rem;
    color: #64748b;
}

/* Source Cell */
.ro-source-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.ro-source-badge {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    display: inline-block;
    width: fit-content;
}

.ro-notes-text {
    font-size: 0.7rem;
    color: #64748b;
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Pagination Bar */
.ro-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.25rem 0.5rem;
    flex-wrap: wrap;
}

.ro-pagination-info {
    font-size: 0.76rem;
    color: #64748b;
}

.ro-pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.ro-pagination-indicator {
    font-size: 0.76rem;
    color: #475569;
    padding: 0 0.4rem;
}

/* ========================================================================= */
/* BATCH HUB & PEER MESH STYLES (RESPONSIVE COCKPIT)                        */
/* ========================================================================= */
.ro-batch-submode-switcher {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: #f1f5f9;
    padding: 0.3rem;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    width: 100%;
}

.ro-submode-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.ro-submode-btn:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.6);
}

.ro-submode-btn.is-active {
    color: #4338ca;
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(67, 56, 202, 0.08);
}

.ro-batch-layout {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.ro-batch-intro {
    padding: 0.75rem 1rem;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 14px;
    font-size: 0.78rem;
    color: #3730a3;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    line-height: 1.45;
}

.ro-batch-form-grid {
    display: grid;
    grid-template-columns: 370px 1fr;
    gap: 1.25rem;
}

@media (max-width: 900px) {
    .ro-batch-form-grid {
        grid-template-columns: 1fr;
    }
}

.ro-batch-left-col,
.ro-batch-right-col {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

/* Step Header inside Batch / Peer Cards */
.ro-batch-step-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.ro-batch-step-badge {
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 999px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.76rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ro-batch-step-info {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
}

.ro-batch-step-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
}

.ro-batch-step-subtitle {
    font-size: 0.7rem;
    color: #64748b;
}

/* Quick Action Chips */
.ro-batch-quick-btns {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.ro-quick-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.ro-quick-chip-btn:hover:not(:disabled) {
    background: #e0e7ff;
    border-color: #a5b4fc;
}

.ro-quick-chip-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ro-quick-chip-btn.is-primary {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.ro-quick-chip-btn.is-danger {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

/* Candidate Filter Row */
.ro-batch-filter-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ro-batch-filter-dropdowns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.ro-batch-filter-dropdowns--single {
    grid-template-columns: 1fr;
}

@media (max-width: 640px) {
    .ro-batch-filter-dropdowns {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }
}

/* Selected Chips Strip */
.ro-selected-chips-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
}

.ro-selected-chips-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.ro-selected-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #15803d;
}

.ro-text-clear-btn {
    border: none;
    background: transparent;
    font-size: 0.68rem;
    font-weight: 700;
    color: #dc2626;
    cursor: pointer;
    text-decoration: underline;
}

.ro-selected-chips-list {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    max-height: 5.5rem;
    overflow-y: auto;
}

.ro-selected-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    background: #ffffff;
    border: 1px solid #86efac;
    border-radius: 999px;
    font-size: 0.7rem;
    color: #166534;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.ro-chip-name {
    font-weight: 700;
}

.ro-chip-nik {
    font-size: 0.64rem;
    color: #64748b;
}

.ro-chip-del {
    border: none;
    background: transparent;
    font-weight: 800;
    font-size: 0.9rem;
    cursor: pointer;
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.15rem;
    line-height: 1;
}

/* Results Box */
.ro-batch-results-box {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    max-height: 22rem;
    overflow-y: auto;
    background: #f8fafc;
}

.ro-batch-loading {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
}

.ro-batch-empty-result {
    padding: 2.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.78rem;
    text-align: center;
}

.ro-batch-items-list {
    display: flex;
    flex-direction: column;
}

.ro-batch-item-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    cursor: pointer;
    transition: background 0.15s ease;
    user-select: none;
}

.ro-batch-item-row:last-child {
    border-bottom: none;
}

.ro-batch-item-row:hover {
    background: #f8fafc;
}

.ro-batch-item-row.is-checked {
    background: #f0fdf4;
}

.ro-batch-item-row.is-terkunci {
    opacity: 0.65;
    background: #f8fafc;
    cursor: not-allowed;
}

.ro-batch-item-row.is-bentrok {
    background: #fef2f2;
}

.ro-batch-item-row.is-clickable {
    cursor: pointer;
}

.ro-batch-checkbox {
    width: 1.15rem;
    height: 1.15rem;
    accent-color: #16a34a;
    cursor: pointer;
    flex-shrink: 0;
}

.ro-item-details {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.ro-item-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.ro-item-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ro-terpasang-pill {
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.66rem;
    white-space: nowrap;
}

.ro-terpasang-pill.is-mengunci {
    border-color: #fdba74;
    background: #fff7ed;
    color: #c2410c;
    font-weight: 600;
}

.ro-bentrok-pill {
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 0.66rem;
    font-weight: 600;
    white-space: nowrap;
}

.ro-sudah-terhubung-nota {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    margin: 0.5rem;
    border-radius: 10px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
    font-size: 0.74rem;
    line-height: 1.35;
}

.ro-bentrok-nota {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    margin: 0.5rem;
    border-radius: 10px;
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #991b1b;
    font-size: 0.74rem;
    line-height: 1.35;
}

.ro-dipangkas {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    margin: 0.5rem;
    border-radius: 10px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.74rem;
    line-height: 1.35;
}

.ro-batch-submit-box {
    margin-top: 0.25rem;
    width: 100%;
}

.ro-batch-submit-box :deep(.hcis-btn) {
    width: 100%;
    justify-content: center;
}

/* Peer Mesh Grid */
.ro-peer-mesh-layout {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.ro-peer-mesh-grid {
    display: grid;
    grid-template-columns: 370px 1fr;
    gap: 1.25rem;
}

@media (max-width: 900px) {
    .ro-peer-mesh-grid {
        grid-template-columns: 1fr;
    }
}

.ro-peer-left,
.ro-peer-right {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.ro-peer-header-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.ro-peer-calc-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #7e22ce;
    background: #f3e8ff;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-weight: 600;
}

.ro-peer-cap-badge {
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-size: 0.68rem;
    white-space: nowrap;
}

.ro-peer-cap-badge.is-penuh {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
    font-weight: 700;
}

.ro-peer-empty-box {
    padding: 2.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.78rem;
    text-align: center;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: #fafafa;
}

.ro-peer-members-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 18rem;
    overflow-y: auto;
}

.ro-peer-member-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.55rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.15s ease;
}

.ro-peer-member-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.ro-peer-member-info {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    flex: 1;
    min-width: 0;
}

.ro-peer-member-info strong {
    font-size: 0.82rem;
    color: #0f172a;
    font-weight: 700;
}

.ro-peer-member-info small {
    font-size: 0.68rem;
    color: #64748b;
}

.ro-peer-remove-btn {
    border: none;
    background: transparent;
    font-size: 1.15rem;
    font-weight: 700;
    color: #dc2626;
    cursor: pointer;
    padding: 0 0.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.ro-peer-submit-box {
    margin-top: 0.25rem;
    width: 100%;
}

.ro-peer-submit-box :deep(.hcis-btn) {
    width: 100%;
    justify-content: center;
}

/* ========================================================================= */
/* TRANSFER STRUCTURE STYLES                                                 */
/* ========================================================================= */
.ro-transfer-grid {
    display: grid;
    grid-template-columns: 1fr 100px 1fr;
    gap: 1.5rem;
    align-items: flex-start;
}

@media (max-width: 950px) {
    .ro-transfer-grid {
        grid-template-columns: 1fr;
    }
    .ro-transfer-arrow-col {
        transform: rotate(90deg);
        padding: 1rem 0;
    }
}

.ro-transfer-step-col {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.ro-transfer-col-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ro-step-number {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 999px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ro-step-title {
    display: block;
    font-size: 0.95rem;
    color: #0f172a;
}

.ro-step-desc {
    font-size: 0.73rem;
    color: #64748b;
}

.ro-subordinates-box {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    background: #f8fafc;
}

.ro-subordinates-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
}

.ro-sub-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 16rem;
    overflow-y: auto;
}

.ro-sub-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.ro-sub-row.is-checked {
    background: #f0fdf4;
    border-color: #86efac;
}

.ro-sub-details {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    flex: 1;
    min-width: 0;
}

.ro-sub-details strong {
    font-size: 0.82rem;
    color: #0f172a;
}

.ro-sub-details small {
    font-size: 0.68rem;
    color: #64748b;
}

.ro-transfer-arrow-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    align-self: center;
}

.ro-arrow-circle {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(67, 56, 202, 0.2);
}

.ro-arrow-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #4338ca;
    text-transform: uppercase;
}

.ro-transfer-options-box {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 0.5rem;
}

.ro-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
}

.ro-transfer-submit-box {
    margin-top: 0.5rem;
}

/* ========================================================================= */
/* INSPECTOR 360° STYLES (STRUCTURED COCKPIT ARCHITECTURE)                   */
/* ========================================================================= */
.ro-inspector-header-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.25rem 1.5rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.ro-inspector-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 280px;
}

.ro-inspector-header-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    flex-shrink: 0;
}

.ro-inspector-header-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.ro-inspector-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.ro-inspector-subtitle {
    margin: 0;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.4;
}

.ro-inspector-search-box {
    width: 380px;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ro-inspector-search-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.ro-inspector-loading {
    padding: 1rem 0;
}

.ro-inspector-empty-card {
    padding: 3rem 1.5rem;
    background: rgba(255, 255, 255, 0.6);
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    text-align: center;
}

.ro-ecosystem-visual {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* 1. Hero Central Profile Card */
.ro-eco-hero-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
    backdrop-filter: blur(12px);
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: 18px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
    flex-wrap: wrap;
}

.ro-eco-hero-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex: 1;
    min-width: 320px;
}

.ro-eco-hero-info {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    flex: 1;
}

.ro-eco-hero-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.ro-eco-name {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.ro-eco-center-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    border: 1px solid #bae6fd;
}

.ro-eco-pills {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.ro-eco-hero-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.ro-eco-hero-stats {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.65rem 1.15rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.ro-eco-stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 42px;
}

.ro-eco-stat-num {
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1.2;
}

.ro-eco-stat-item.is-atasan .ro-eco-stat-num {
    color: #0284c7;
}

.ro-eco-stat-item.is-peer .ro-eco-stat-num {
    color: #9333ea;
}

.ro-eco-stat-item.is-bawahan .ro-eco-stat-num {
    color: #16a34a;
}

.ro-eco-stat-lbl {
    font-size: 0.65rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}

.ro-eco-stat-sep {
    font-size: 1rem;
    color: #cbd5e1;
    font-weight: 300;
}

.ro-eco-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

@media (max-width: 768px) {
    .ro-eco-hero-card {
        flex-direction: column;
        align-items: stretch;
        padding: 1rem;
        gap: 1rem;
    }
    .ro-eco-hero-left {
        min-width: 0;
        width: 100%;
        gap: 0.85rem;
    }
    .ro-eco-hero-right {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .ro-eco-hero-stats {
        width: 100%;
        justify-content: space-around;
        padding: 0.5rem 0.75rem;
    }
    .ro-eco-hero-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
        gap: 0.5rem;
    }
    .ro-eco-hero-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}

/* 2. The 3 Spheres 3-Column Matrix */
/* Kartu Roda 360°. */
.ro-eco-roda-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem 1rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 6rem);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.ro-eco-roda-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.ro-eco-roda-title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 800;
    color: #0f172a;
}

.ro-eco-roda-sub {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.74rem;
    line-height: 1.4;
    color: #64748b;
}

.ro-eco-spheres-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

/* Kartu yang barusan ditunjuk dari roda. */
.ro-sphere-member-card.is-tersorot {
    box-shadow: 0 0 0 2px #2563eb, 0 6px 16px rgba(37, 99, 235, 0.18);
    background: #eff6ff;
}

@media (max-width: 1024px) {
    .ro-eco-spheres-grid {
        grid-template-columns: 1fr;
    }
}

.ro-sphere-column {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem;
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    min-height: 380px;
}

.ro-sphere-column.is-atasan {
    border-top: 4px solid #0284c7;
    background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 4.5rem);
}

.ro-sphere-column.is-peer {
    border-top: 4px solid #9333ea;
    background: linear-gradient(180deg, #faf5ff 0%, #ffffff 4.5rem);
}

.ro-sphere-column.is-bawahan {
    border-top: 4px solid #16a34a;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 4.5rem);
}

.ro-sphere-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
}

.ro-sphere-header-left {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    flex: 1;
}

.ro-sphere-icon-wrap {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ro-sphere-icon-wrap.is-atasan {
    background: #e0f2fe;
    color: #0284c7;
}

.ro-sphere-icon-wrap.is-peer {
    background: #f3e8ff;
    color: #9333ea;
}

.ro-sphere-icon-wrap.is-bawahan {
    background: #dcfce7;
    color: #16a34a;
}

.ro-sphere-title-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ro-sphere-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
}

.ro-sphere-counter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
}

.ro-sphere-counter.is-atasan {
    background: #bae6fd;
    color: #0369a1;
}

.ro-sphere-counter.is-peer {
    background: #e9d5ff;
    color: #6b21a8;
}

.ro-sphere-counter.is-bawahan {
    background: #bbf7d0;
    color: #15803d;
}

.ro-sphere-subtitle {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.15rem;
}

.ro-sphere-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.ro-sphere-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 2.5rem 1rem;
    text-align: center;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: rgba(248, 250, 252, 0.6);
    color: #94a3b8;
}

.ro-sphere-empty p {
    margin: 0;
    font-size: 0.76rem;
    color: #64748b;
    max-width: 220px;
}

.ro-sphere-card-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    max-height: 24rem;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.ro-sphere-member-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    cursor: pointer;
    transition: all 0.18s ease;
}

.ro-sphere-member-card:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
}

.ro-sphere-member-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.ro-sphere-member-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ro-sphere-member-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.ro-sphere-meta-nik {
    font-size: 0.68rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.ro-sphere-meta-level {
    font-size: 0.68rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ro-sphere-member-actions {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.ro-sphere-action-btn {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    color: #64748b;
}

.ro-sphere-action-btn.is-inspect:hover {
    background: #e0f2fe;
    color: #0284c7;
    border-color: #bae6fd;
}

.ro-sphere-action-btn.is-delete:hover {
    background: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
}

/* 3. Footer Explanatory Hint */
.ro-eco-footer-hint {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.9rem 1.25rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    font-size: 0.78rem;
    color: #1e40af;
    line-height: 1.5;
}

.ro-eco-footer-icon {
    margin-top: 0.1rem;
    color: #2563eb;
    flex-shrink: 0;
}

.ro-eco-footer-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

/* Modal Form Styles */
.ro-dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.ro-dialog-step-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    transition: border-color 0.15s ease;
}

.ro-dialog-step-card:hover {
    border-color: #cbd5e1;
}

.ro-step-card-header {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.ro-step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 999px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.ro-step-title {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.ro-step-subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.74rem;
    color: #64748b;
    line-height: 1.4;
}

.ro-parties-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 640px) {
    .ro-parties-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

.ro-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ro-form-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #334155;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.2rem;
}

.ro-form-hint {
    font-size: 0.7rem;
    font-weight: 400;
    color: #64748b;
}

.ro-req {
    color: #dc2626;
}

.ro-form-err {
    margin: 0.25rem 0 0;
    font-size: 0.73rem;
    font-weight: 600;
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* Type Selector Cards */
.ro-type-selector-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 0.65rem;
}

.ro-type-card-opt {
    padding: 0.75rem 0.95rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.ro-type-card-opt:hover {
    border-color: #818cf8;
    background: #fafafa;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.ro-type-card-opt.is-selected {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 1px #4f46e5, 0 4px 14px rgba(79, 70, 229, 0.12);
}

.ro-type-card-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ro-type-card-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 8px;
    background: #f1f5f9;
    color: #475569;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.ro-type-card-icon-wrap.is-atasan {
    background: #eff6ff;
    color: #2563eb;
}

.ro-type-card-icon-wrap.is-bawahan {
    background: #f0fdf4;
    color: #16a34a;
}

.ro-type-card-icon-wrap.is-peer {
    background: #faf5ff;
    color: #9333ea;
}

.ro-type-card-opt.is-selected .ro-type-card-icon-wrap {
    background: #4f46e5;
    color: #ffffff;
}

.ro-type-card-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f172a;
}

.ro-type-card-check {
    margin-left: auto;
    color: #4f46e5;
    flex-shrink: 0;
}

.ro-type-card-desc {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    color: #64748b;
}

.ro-type-kebalikan-label {
    color: #64748b;
}

.ro-type-kebalikan-val {
    color: #0f172a;
    font-weight: 600;
}

/* Interactive Live Relationship Preview (Visual Diagram) */
.ro-relasi-diagram-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0fdfa 100%);
    border: 1.5px solid #a7f3d0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.06);
}

.ro-relasi-diagram-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.ro-diagram-hdr-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #065f46;
}

.ro-relasi-diagram-flow {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    background: #ffffff;
    border: 1px solid #d1fae5;
    border-radius: 12px;
    padding: 0.85rem 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

@media (max-width: 640px) {
    .ro-relasi-diagram-flow {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
        gap: 0.65rem;
    }
}

.ro-relasi-node {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ro-relasi-node-tag {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.ro-relasi-node-tag.is-subjek {
    color: #4f46e5;
}

.ro-relasi-node-tag.is-terkait {
    color: #059669;
}

.ro-relasi-node-person {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

@media (max-width: 640px) {
    .ro-relasi-node-person {
        flex-direction: column;
        text-align: center;
    }
}

.ro-node-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.ro-node-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.ro-node-nik {
    font-size: 0.68rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

.ro-relasi-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0 0.5rem;
}

.ro-connector-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e40af;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid #bfdbfe;
    white-space: nowrap;
}

.ro-connector-line {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 80px;
    color: #3b82f6;
    font-size: 0.65rem;
}

.ro-connector-track {
    flex: 1;
    height: 2px;
    background: #93c5fd;
}

.ro-connector-arrow {
    line-height: 1;
}

.ro-relasi-sentences {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #a7f3d0;
}

.ro-sentence-item {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.77rem;
    line-height: 1.45;
}

.ro-sentence-item.is-primary {
    color: #065f46;
}

.ro-sentence-item.is-secondary {
    color: #047857;
    font-size: 0.74rem;
}

.ro-form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
}

@media (max-width: 640px) {
    .ro-form-grid-2 {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }
    .ro-type-selector-cards {
        grid-template-columns: repeat(auto-fit, minmax(115px, 1fr));
        gap: 0.45rem;
    }
    .ro-type-card-opt {
        padding: 0.6rem 0.75rem;
    }
}

.ro-dialog-footer-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

@media (max-width: 640px) {
    .ro-dialog-footer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
        gap: 0.5rem;
    }

    .ro-dialog-footer-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}

/* Cabut Modal Content */
.ro-cabut-modal-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ro-cabut-preview-card {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.15rem;
    background: linear-gradient(135deg, #fff1f2 0%, #fef2f2 100%);
    border: 1.5px solid #fecaca;
    border-radius: 14px;
}

@media (max-width: 640px) {
    .ro-cabut-preview-card {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
        gap: 0.65rem;
    }
}

.ro-cabut-person-side {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

@media (max-width: 640px) {
    .ro-cabut-person-side {
        flex-direction: column;
    }
}

.ro-cabut-person {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.ro-cabut-person strong {
    font-size: 0.85rem;
    color: #0f172a;
    line-height: 1.25;
}

.ro-cabut-person small {
    font-size: 0.7rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

.ro-cabut-dept {
    font-size: 0.65rem;
    color: #94a3b8;
}

.ro-cabut-rel-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.ro-cabut-rel-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: 999px;
    white-space: nowrap;
}

.ro-cabut-rel-cross {
    font-size: 0.75rem;
    color: #ef4444;
    font-weight: 900;
}

.ro-arah-preview.is-revoking {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #991b1b;
}

/* Batch Confirmation Box */
.ro-batch-confirm-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.95rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ro-batch-confirm-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.ro-batch-confirm-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    min-width: 140px;
}

.ro-batch-subjek-list {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.ro-batch-subjek-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.55rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    font-size: 0.75rem;
    color: #0f172a;
}

.ro-batch-count-note {
    font-size: 0.7rem;
    color: #64748b;
}

.ro-batch-target-count {
    font-size: 0.78rem;
    color: #0f172a;
}

.ro-konfirmasi-kalimat {
    margin: 0 0 0.6rem;
    font-size: 0.84rem;
    line-height: 1.5;
}

.ro-konfirmasi-saringan {
    margin: 0.35rem 0 0;
    padding-left: 1.1rem;
    font-size: 0.78rem;
    color: #334155;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.ro-konfirmasi-nota {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.76rem;
    line-height: 1.4;
}

/* Floating Toast */
.ro-toast-bar {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 1.15rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.ro-toast-bar.is-success {
    background: #15803d;
    color: #ffffff;
}

.ro-toast-bar.is-danger {
    background: #b91c1c;
    color: #ffffff;
}

.ro-toast-close {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0;
    margin-left: 0.35rem;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: all 0.25s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

/* ========================================================================= */
/* MAIN TAB 2: VISUALISASI STRUKTUR ORGANISASI (ZERO-LAG TREE)               */
/* ========================================================================= */
.ro-main-tab-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    min-width: 0;
    width: 100%;
}

/* Clean Search & Filter Control Panel (Glass Surface) */
.ro-visual-filter-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
    backdrop-filter: blur(12px);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.04);
}

.ro-visual-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    align-items: flex-start;
}

.ro-visual-filter-col {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

.ro-visual-filter-col.is-cari-karyawan {
    grid-column: span 1;
}

.ro-visual-col-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.ro-visual-filter-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid #edf2f7;
    flex-wrap: wrap;
}

.ro-visual-status-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #64748b;
}

.ro-active-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
    display: inline-block;
}

.ro-idle-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #94a3b8;
    display: inline-block;
}

.ro-visual-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.ro-visual-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.78rem;
    overflow-x: auto;
}

.ro-breadcrumb-prefix {
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.ro-breadcrumb-items {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
}

.ro-breadcrumb-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.5rem;
    border: none;
    background: transparent;
    color: #4f46e5;
    font-weight: 500;
    font-size: 0.78rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.ro-breadcrumb-btn:hover {
    background: #e0e7ff;
    color: #3730a3;
}

.ro-breadcrumb-btn.is-current {
    font-weight: 700;
    color: #0f172a;
    cursor: default;
    background: transparent;
}

.ro-breadcrumb-sep {
    color: #94a3b8;
    margin-left: 0.2rem;
    font-weight: bold;
}

.ro-visual-loading {
    padding: 1.5rem 0;
}

.ro-visual-empty {
    padding: 2.5rem 1rem;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    text-align: center;
}

.ro-visual-canvas-wrap {
    width: 100%;
    min-height: 680px;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.05);
}

.ro-visual-canvas-wrap :deep(.hcis-org-viewport) {
    height: clamp(580px, 72vh, 900px);
    border-radius: 14px;
}

.ro-visual-mobile-hint {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.75rem;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    font-size: 0.72rem;
    color: #3730a3;
    line-height: 1.35;
}

@media (max-width: 768px) {
    .ro-visual-filter-card {
        padding: 0.85rem 0.75rem;
        gap: 0.75rem;
        border-radius: 14px;
    }

    .ro-visual-filter-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .ro-visual-filter-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
        padding-top: 0.65rem;
    }

    .ro-visual-status-text {
        font-size: 0.75rem;
    }

    .ro-visual-footer-actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 0.35rem;
    }

    .ro-visual-footer-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }

    .ro-visual-breadcrumbs {
        padding: 0.45rem 0.65rem;
        gap: 0.4rem;
        font-size: 0.72rem;
        border-radius: 10px;
        -webkit-overflow-scrolling: touch;
    }

    .ro-breadcrumb-btn {
        padding: 0.15rem 0.4rem;
        font-size: 0.72rem;
    }

    .ro-visual-canvas-wrap {
        min-height: 460px;
        border-radius: 14px;
    }

    .ro-visual-canvas-wrap :deep(.hcis-org-viewport) {
        height: clamp(440px, 62vh, 560px) !important;
        touch-action: none !important;
    }

    .ro-visual-canvas-wrap :deep(.hcis-org-chart__header) {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.65rem !important;
        padding: 0.75rem 0.85rem !important;
    }

    .ro-visual-canvas-wrap :deep(.hcis-org-chart__header-right) {
        width: 100% !important;
        justify-content: space-between !important;
    }

    .ro-visual-canvas-wrap :deep(.hcis-org-canvas-controls) {
        flex: 1 !important;
        justify-content: center !important;
    }

    .ro-visual-canvas-wrap :deep(.hcis-org-popover) {
        max-width: calc(100vw - 2rem) !important;
        width: 290px !important;
    }
}
</style>
