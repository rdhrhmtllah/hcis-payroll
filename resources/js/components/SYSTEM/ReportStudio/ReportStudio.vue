<template>
    <div class="rs-root">
        <!-- ══ 1. TOP STUDIO NAVIGATION & MODE BAR ══ -->
        <div v-if="!tanpaTopBar" class="rs-top-bar">
            <div class="rs-top-bar-center">
                <!-- Navigasi Tab Utama (Galeri Template vs Studio Perakit & Pratinjau) -->
                <HcisTabs
                    v-model="tabUtamaInternal"
                    variant="segmented"
                    size="sm"
                    :tabs="daftarTabUtama"
                />
            </div>
        </div>

        <div v-if="katalogGalat" class="rs-galat-katalog mb-3" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <div class="d-flex flex-column">
                <strong>Gagal Memuat Katalog Dataset</strong>
                <span>{{ katalogGalat }}</span>
            </div>
        </div>

        <!-- ══ 2. TAB CONTENT 1: GALERI TEMPLATE (FULL VIEW WITH DEDICATED PREVIEW) ══
             Di ponsel SELALU galeri (keputusan user 30 Sep 2026): template tetap
             bisa dipratinjau dan dijalankan; yang tidak tersedia hanya merakit. -->
        <div v-else-if="ponsel || tabUtamaInternal === 'galeri'" class="rs-view-galeri">
            <HcisCard
                variant="default"
                size="md"
                class="rs-galeri-card"
            >
                <template #title>
                    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-collection-play-fill text-brand-600 fs-5"></i>
                            <h5 class="sop-h mb-0">Galeri Template Laporan</h5>
                            <HcisBadge v-if="galeri" :label="`${totalTemplate} template`" variant="brand" size="sm" />
                        </div>
                        <HcisButton
                            v-if="!ponsel"
                            variant="primary"
                            size="sm"
                            icon="bi-plus-lg"
                            label="Buat Laporan Baru di Studio"
                            title="Buka studio perakit untuk membuat konfigurasi baru"
                            @click="tabUtamaInternal = 'studio'"
                        />
                    </div>
                </template>

                <template #description>
                    <template v-if="ponsel">Pilih template siap pakai di bawah untuk melihat pratinjau atau mengunduh hasilnya.</template>
                    <template v-else>Pilih salah satu template siap pakai di bawah untuk melihat pratinjau mandiri, mengunduh hasil, atau menduplikat ke studio perakit.</template>
                </template>

                <p v-if="ponsel" class="rs-ponsel-catatan" data-testid="catatan-ponsel">
                    <i class="bi bi-phone me-1"></i>
                    Di ponsel Anda bisa memakai template yang ada — pratinjau dan jalankan export.
                    Merakit laporan baru hanya di layar lebar.
                </p>

                <GaleriTemplate
                    :tab-awal="tabGaleri"
                    :data="galeri"
                    :boleh-rakit="!ponsel"
                    @terapkan="bukaPratinjauTemplate"
                    @edit="terapkanTemplate"
                    @jalankan="jalankanDariGaleri"
                    @duplikat="duplikatTemplate"
                    @hapus="hapusTemplate"
                />
            </HcisCard>
        </div>

        <!-- ══ 3. TAB CONTENT 2: STUDIO PERAKIT & PRATINJAU (SPLITTER COCKPIT VIEW) ══ -->
        <div v-else-if="tabUtamaInternal === 'studio'" class="rs-view-studio">
            <!--
                GRAIN — kalimat "satu baris itu apa".
                Dikirim server (SopExportStudioController & VisitorExportStudioController
                sama-sama mengisi 'grain'), sempat berhenti dirender sementara CSS
                .rs-grain-box/.rs-grain tetap tertinggal di berkas ini — tanda bahwa
                hilangnya tak disengaja, sebab pembersihan yang disengaja membawa
                CSS-nya serta.

                Ia bukan hiasan: tanpa tahu satu baris mewakili apa, pembaca tak bisa
                tahu apakah angkanya terhitung ganda. "Satu karyawan × paket × item"
                dan "satu karyawan" menghasilkan jumlah yang berbeda dari data yang sama.
            -->
            <div v-if="katalog?.grain" class="rs-grain-box mb-3">
                <i class="bi bi-info-circle-fill rs-grain-icon"></i>
                <span class="rs-grain">{{ katalog.grain }}</span>
            </div>

            <!-- Active Template Alert / Context Bar in Builder -->
            <div v-if="templateAktif" class="rs-builder-active-template-bar mb-3">
                <div class="rs-builder-active-template-left">
                    <span class="rs-template-badge-pill">
                        <i class="bi bi-stars"></i>
                        <span>Template Aktif</span>
                    </span>
                    <span class="rs-template-active-name">{{ templateAktif.nama }}</span>
                    <span class="rs-template-active-mode-badge">{{ templateAktif.mode === 'rinci' ? 'Bentuk Rinci' : 'Bentuk Ringkasan' }}</span>
                </div>
                <div class="rs-builder-active-template-right">
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        icon="bi-x-circle-fill"
                        label="Tutup Template"
                        class="rs-btn-tutup-template"
                        title="Tutup template ini dan kosongkan konfigurasi builder"
                        @click="lepaskanTemplateAktif"
                    />
                </div>
            </div>

            <HcisCard
                variant="flat"
                size="lg"
                class="rs-kokpit-card"
            >
                <HcisSplitter
                    orientation="horizontal"
                    variant="flat"
                    :initial-ratio="42"
                    :min-ratio="28"
                    :max-ratio="72"
                    :show-preset-toolbar="true"
                    :allow-maximize="true"
                    class="rs-kokpit"
                >
                    <!-- PERAKIT (LEFT PANE) -->
                    <template #first>
                        <div class="rs-perakit-kolom" :class="{ 'is-loading-locked': sedangMemuat }">
                            <div class="rs-perakit-head">
                                <div class="rs-perakit-head-main">
                                    <span class="sop-eyebrow">Konfigurasi Laporan</span>
                                    <h6 class="sop-h mb-0">Struktur Data & Filter</h6>
                                </div>

                                <!-- Futuristic / Tactile Switch for Bentuk Laporan (Only in Builder) -->
                                <div class="rs-bentuk-switch-box">
                                    <span class="rs-bentuk-switch-label">Bentuk:</span>
                                    <div class="rs-tactical-switch" :class="[`is-${mode}`, { 'is-disabled': sedangMemuat }]">
                                        <button
                                            type="button"
                                            class="rs-tactical-switch-opt"
                                            :class="{ 'is-active': mode === 'ringkasan' }"
                                            :disabled="sedangMemuat"
                                            title="Bentuk Ringkasan (Agregasi GROUP BY & Ukuran Metrik)"
                                            @click="!sedangMemuat && (mode = 'ringkasan')"
                                        >
                                            <i class="bi bi-pie-chart-fill"></i>
                                            <span>Ringkasan</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="rs-tactical-switch-opt"
                                            :class="{ 'is-active': mode === 'rinci' }"
                                            :disabled="sedangMemuat"
                                            title="Bentuk Rinci (Kolom data faktual per baris)"
                                            @click="!sedangMemuat && (mode = 'rinci')"
                                        >
                                            <i class="bi bi-table"></i>
                                            <span>Rinci</span>
                                        </button>
                                        <!-- Animated Sliding Thumb Glider -->
                                        <div class="rs-tactical-glider" aria-hidden="true" />
                                    </div>
                                </div>
                            </div>

                            <HcisCollapse v-model="seksiAktif" variant="card">
                                <!-- Dimensi -->
                                <HcisCollapseItem
                                    v-if="mode === 'ringkasan'"
                                    name="dimensi"
                                    title="Dimensi & Pengelompokan"
                                    description="Tentukan field untuk mengelompokkan data (GROUP BY)"
                                    icon="layers"
                                    :badge="pilihan.dimensi.length ? `${pilihan.dimensi.length} Dipilih` : ''"
                                >
                                    <PanelDimensiUkuran
                                        v-model="pilihan.dimensi"
                                        :daftar="katalog?.dimensi ?? []"
                                        judul="Kelompokkan berdasarkan"
                                        eyebrow="Dimensi"
                                        keterangan="Urutannya menentukan hierarki pengelompokan dan bersarangnya subtotal."
                                        :maks="6"
                                        berkelompok
                                        :disabled="sedangMemuat"
                                        :galat="galat.dimensi"
                                    />
                                </HcisCollapseItem>

                                <!-- Ukuran (Mode Ringkasan) -->
                                <HcisCollapseItem
                                    v-if="mode === 'ringkasan'"
                                    name="ukuran"
                                    title="Ukuran & Metrik"
                                    description="Pilih metrik agregat terhitung"
                                    icon="calculator"
                                    :badge="pilihan.ukuran.length ? `${pilihan.ukuran.length} Dipilih` : ''"
                                >
                                    <PanelDimensiUkuran
                                        v-model="pilihan.ukuran"
                                        :daftar="katalog?.ukuran ?? []"
                                        judul="Hitung & Ukur"
                                        eyebrow="Ukuran"
                                        keterangan="Setiap ukuran sudah dikoreksi — 'Jumlah Karyawan' menghitung orang unik, bukan baris."
                                        :maks="12"
                                        :disabled="sedangMemuat"
                                        :galat="galat.ukuran"
                                    />
                                </HcisCollapseItem>

                                <!-- Agregat racikan (Mode Ringkasan) -->
                                <HcisCollapseItem
                                    v-if="mode === 'ringkasan'"
                                    name="agregat"
                                    title="Agregat Racikan"
                                    description="Susun angka sendiri dari kolom mana pun"
                                    icon="sliders"
                                    :badge="pilihan.agregat.length ? `${pilihan.agregat.length} Dibuat` : ''"
                                >
                                    <PanelAgregatRacikan
                                        v-model="pilihan.agregat"
                                        :dimensi="katalog?.dimensi ?? []"
                                        :katalog-agregat="katalog?.agregat ?? { fungsi: [], per_kolom: {} }"
                                        :disabled="sedangMemuat"
                                        :galat="galat.agregat"
                                    />
                                </HcisCollapseItem>

                                <!-- Kolom (Mode Rinci) -->
                                <HcisCollapseItem
                                    v-else
                                    name="kolom"
                                    title="Kolom Data"
                                    description="Pilih kolom data yang ditampilkan per baris faktual"
                                    icon="layout-three-columns"
                                    :badge="pilihan.kolom.length ? `${pilihan.kolom.length} Dipilih` : ''"
                                >
                                    <PanelDimensiUkuran
                                        v-model="pilihan.kolom"
                                        :daftar="katalog?.dimensi ?? []"
                                        judul="Kolom Data"
                                        eyebrow="Rinci"
                                        keterangan="Satu baris per entri data faktual, tanpa agregasi agregat."
                                        :maks="60"
                                        berkelompok
                                        :disabled="sedangMemuat"
                                        :galat="galat.kolom"
                                    />
                                </HcisCollapseItem>

                                <!-- Filter Data -->
                                <HcisCollapseItem
                                    name="saringan"
                                    title="Filter Data"
                                    description="Batasi cakupan data laporan dengan kriteria DAN"
                                    icon="funnel"
                                    :badge="pilihan.saringan.length ? `${pilihan.saringan.length} Filter` : ''"
                                >
                                    <PanelSaringan
                                        v-model="pilihan.saringan"
                                        :daftar="katalog?.saringan ?? []"
                                        :opsi-filter="opsiFilter"
                                        :disabled="sedangMemuat"
                                    />
                                </HcisCollapseItem>

                                <!-- Opsi Laporan -->
                                <HcisCollapseItem
                                    v-if="mode === 'ringkasan'"
                                    name="opsi"
                                    title="Opsi Laporan"
                                    description="Pengaturan baris total akhir"
                                    icon="sliders"
                                >
                                    <div class="rs-opsi">
                                        <HcisCheckbox
                                            v-model="pilihan.opsi.grand_total"
                                            :disabled="sedangMemuat"
                                            label="Sertakan baris Grand Total di akhir laporan"
                                        />
                                    </div>
                                </HcisCollapseItem>
                            </HcisCollapse>
                        </div>
                    </template>

                    <!-- PRATINJAU (RIGHT PANE) -->
                    <template #second>
                        <div class="rs-pratinjau-kolom">
                            <PanelPratinjau
                                :status="statusPratinjau"
                                :hasil="hasil"
                                :pesan-galat="pesanGalat"
                                :galat-definisi="galat"
                                :page="pratinjauHalaman"
                                :page-size="pratinjauUkuranHalaman"
                                :search="pratinjauPencarian"
                                :sort-key="pratinjauSortKey"
                                :sort-order="pratinjauSortOrder"
                                @update:page="(p) => (pratinjauHalaman = p)"
                                @update:page-size="(s) => (pratinjauUkuranHalaman = s)"
                                @update:search="(q) => (pratinjauPencarian = q)"
                                @sort="onTableSort"
                                @ulangi="mintaPratinjau"
                                @muat-halaman="mintaPratinjau"
                                @cari="mintaPratinjau"
                                @column-reorder="onTableColumnReorder"
                            />
                        </div>
                    </template>
                </HcisSplitter>
            </HcisCard>
        </div>

        <!-- ══ 4. FIXED FLOATING ACTION DOCK (Outside HcisCard to guarantee viewport fixing) ══ -->
        <BilahAksi
            v-if="!katalogGalat && tabUtamaInternal === 'studio'"
            v-model:format="format"
            :valid="valid"
            :sibuk="sibuk"
            :sebab-mati="sebabMati"
            :template-aktif="templateAktif"
            @simpan="bukaDialogSimpan"
            @jalankan="bukaModalEksekusiExportDariStudio"
            @tutup-template="lepaskanTemplateAktif"
        />

        <!-- ══ 5. DIALOG PRATINJAU TEMPLATE MANDIRI ══ -->
        <HcisDialog
            v-model="dialogPratinjauTemplate"
            :title="`Pratinjau: ${templateDipratinjau?.nama || templateDipratinjau?.Nama || 'Template'}`"
            :description="templateDipratinjau?.deskripsi || templateDipratinjau?.Deskripsi || 'Pratinjau data faktual yang dihasilkan oleh template ini.'"
            :badge="hasilPratinjauTemplate?.total ? `${hasilPratinjauTemplate.total} Data · ${hasilPratinjauTemplate.columns?.length ?? 0} Kolom` : (hasilPratinjauTemplate?.rows ? `${hasilPratinjauTemplate.rows.length} Baris` : '')"
            badge-variant="brand"
            size="full"
            custom-class="rs-dialog-template-modal"
        >
            <div class="rs-dialog-pratinjau-wrapper">
                <PanelPratinjau
                    :status="statusPratinjauTemplate"
                    :hasil="hasilPratinjauTemplate"
                    :pesan-galat="galatPratinjauTemplate"
                    :tanpa-header="true"
                    :page="templateHalaman"
                    :page-size="templateUkuranHalaman"
                    :search="templatePencarian"
                    :sort-key="templateSortKey"
                    :sort-order="templateSortOrder"
                    @update:page="(p) => (templateHalaman = p)"
                    @update:page-size="(s) => (templateUkuranHalaman = s)"
                    @update:search="(q) => (templatePencarian = q)"
                    @sort="onTemplateSort"
                    @column-reorder="onTemplateColumnReorder"
                    @ulangi="muatHalamanTemplate"
                    @muat-halaman="muatHalamanTemplate"
                    @cari="muatHalamanTemplate"
                />
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rs-format-label">Format Export:</span>
                        <HcisTabs
                            v-model="format"
                            variant="segmented"
                            size="xs"
                            :tabs="[
                                { key: 'xlsx', label: 'Excel (.xlsx)' },
                                { key: 'csv', label: 'CSV (.csv)' },
                                { key: 'pdf', label: 'PDF (.pdf)' },
                            ]"
                        />
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            label="Tutup"
                            @click="dialogPratinjauTemplate = false"
                        />
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            icon="bi-sliders"
                            v-if="!ponsel"
                            label="Buka di Studio Perakit"
                            title="Buka template ini di studio perakit untuk dimodifikasi"
                            @click="bukaTemplateDiStudio"
                        />
                        <HcisButton
                            variant="primary"
                            size="sm"
                            icon="bi-play-fill"
                            label="Jalankan Export"
                            :disabled="statusPratinjauTemplate === 'memuat'"
                            @click="bukaModalEksekusiExportDariPratinjau"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- ══ 6. DIALOG SIMPAN / UPDATE TEMPLATE ══ -->
        <HcisDialog
            v-model="dialogSimpan"
            :title="templateAktif ? `Perbarui Template: ${templateAktif.nama}` : 'Simpan sebagai template'"
            :description="templateAktif ? 'Perbarui konfigurasi dan struktur data template ini di galeri.' : 'Template tersimpan muncul di tab «Milik Saya» dan bisa dijalankan ulang kapan saja.'"
            size="sm"
        >
            <div class="sop-scope">
                <!-- HcisField adalah kontrol utuh (label + input + hint), bukan
                     pembungkus ber-slot. `id` yang menyambungkan label ke input. -->
                <HcisField
                    id="rs-nama-template"
                    v-model="namaTemplate"
                    label="Nama template"
                    placeholder="mis. Kepatuhan per Divisi"
                    required
                />
                <HcisField
                    id="rs-deskripsi-template"
                    v-model="deskripsiTemplate"
                    label="Deskripsi"
                    hint="Opsional — membantu Anda mengenalinya nanti."
                />
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    label="Batal"
                    @click="dialogSimpan = false"
                />
                <HcisButton
                    variant="primary"
                    size="sm"
                    class="rs-simpan-kirim"
                    :icon="templateAktif ? 'bi-arrow-repeat' : 'bi-bookmark-check'"
                    :label="templateAktif ? 'Update Template' : 'Simpan'"
                    :disabled="!namaTemplate.trim() || sibuk"
                    :loading="sibuk"
                    @click="simpanTemplate"
                />
            </template>
        </HcisDialog>

        <!-- ══ 7. DIALOG KONFIGURASI EXPORT (STEP 1: REVIEW & CUSTOM NAMA FILE) ══ -->
        <HcisDialog
            v-model="dialogKonfigurasiExport"
            title="Konfigurasi & Jalankan Export"
            description="Sesuaikan nama file dan format sebelum export dijadwalkan ke antrean latar belakang."
            sentiment="brand"
            size="md"
        >
            <div class="sop-scope rs-export-config-stack">
                <!-- 1. KARTU INPUT NAMA FILE -->
                <div class="rs-export-section-card rs-export-name-card">
                    <div class="rs-card-section-header">
                        <div class="rs-card-section-title">
                            <i class="bi bi-pen-fill text-brand-600"></i>
                            <span>Nama File Dokumen</span>
                        </div>
                        <span class="rs-card-section-tag">Opsional</span>
                    </div>
                    <HcisField
                        id="rs-custom-nama-file"
                        v-model="customNamaFile"
                        :placeholder="placeholderNamaFile"
                        hint="Kosongkan untuk menggunakan nama standar otomatis berdasarkan tanggal."
                        class="mb-0"
                    />
                </div>

                <!-- 2. KARTU PILIHAN FORMAT DOKUMEN -->
                <div class="rs-export-section-card rs-export-format-card">
                    <div class="rs-card-section-header">
                        <div class="rs-card-section-title">
                            <i class="bi bi-file-earmark-arrow-down-fill text-brand-600"></i>
                            <span>Pilihan Format Berkas</span>
                        </div>
                        <span class="rs-format-active-tag">.{{ formatExportDipilih }}</span>
                    </div>
                    <div class="rs-format-tabs-wrap">
                        <HcisTabs
                            v-model="formatExportDipilih"
                            variant="segmented"
                            size="sm"
                            :tabs="[
                                { key: 'xlsx', label: 'Excel (.xlsx)', icon: 'bi-file-earmark-excel-fill' },
                                { key: 'csv', label: 'CSV (.csv)', icon: 'bi-file-earmark-spreadsheet-fill' },
                                { key: 'pdf', label: 'PDF (.pdf)', icon: 'bi-file-earmark-pdf-fill' },
                            ]"
                        />
                    </div>
                    <div
                        v-if="formatExportDipilih === 'pdf' && hitungJumlahKolomExport > 10"
                        class="rs-pdf-col-warning"
                    >
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Konfigurasi ini memiliki <strong>{{ hitungJumlahKolomExport }} kolom</strong>. Dokumen PDF otomatis disesuaikan ke format lebar <strong>(A3 Landscape)</strong> agar tidak berhimpitan. Untuk tabel lebar data penuh, disarankan memilih <strong>Excel (.xlsx)</strong>.
                        </span>
                    </div>
                </div>

                <!-- 3. KARTU RINCIAN / SNAPSHOT KONFIGURASI EXPORT -->
                <div class="rs-export-section-card rs-export-snapshot-card">
                    <div class="rs-card-section-header">
                        <div class="rs-card-section-title">
                            <i class="bi bi-receipt-cutoff text-brand-600"></i>
                            <span>Rincian Konfigurasi Export</span>
                        </div>
                        <HcisBadge
                            :label="targetExport?.definisi?.mode === 'rinci' ? 'Mode Rinci' : 'Mode Ringkasan'"
                            variant="brand"
                            size="xs"
                        />
                    </div>

                    <div class="rs-snapshot-grid">
                        <div class="rs-snapshot-item">
                            <span class="rs-snapshot-label">Sumber Konfigurasi</span>
                            <span class="rs-snapshot-val" :title="targetExport?.nama || 'Studio Perakit'">
                                <i class="bi bi-database-check me-1 text-brand-500"></i>
                                {{ targetExport?.nama || 'Studio Perakit' }}
                            </span>
                        </div>

                        <div class="rs-snapshot-item">
                            <span class="rs-snapshot-label">Jumlah Kolom Terpilih</span>
                            <span class="rs-snapshot-val">
                                <i class="bi bi-layout-three-columns me-1 text-brand-500"></i>
                                {{ hitungJumlahKolomExport }} Kolom
                            </span>
                        </div>

                        <div class="rs-snapshot-item">
                            <span class="rs-snapshot-label">Target Ekspor</span>
                            <span class="rs-snapshot-val">
                                <i class="bi bi-cloud-arrow-down me-1 text-brand-500"></i>
                                Antrean Unduhan Latar Belakang
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        @click="dialogKonfigurasiExport = false"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        icon="bi-play-fill"
                        label="Mulai Export"
                        @click="prosesEksekusiExport"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ══ 8. STANDALONE NATIVE RESULT STATE OVERLAY (NO DIALOG WRAPPER) ══ -->
        <Teleport to="body">
            <Transition name="hcis-dialog-fade">
                <div
                    v-if="modalResultState"
                    class="rs-standalone-result-overlay"
                    role="dialog"
                    aria-modal="true"
                    @click.self="faseExport !== 'proses' ? (modalResultState = false) : null"
                >
                    <div class="rs-standalone-result-wrapper">
                        <HcisResultState
                            :status="statusResultState"
                            variant="glass"
                            size="md"
                            :title="judulResultState"
                            :description="deskripsiResultState"
                            :kicker="kickerResultState"
                            :items="faseExport === 'sukses' ? rincianReceiptExport : []"
                            :animated="true"
                            :loop-animation="true"
                        >
                            <template #actions>
                                <div v-if="faseExport === 'sukses'" class="rs-result-actions-row">
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        label="Tutup & Lanjutkan"
                                        @click="modalResultState = false"
                                    />
                                    <HcisButton
                                        variant="primary"
                                        size="sm"
                                        icon="bi-layout-sidebar-inset-reverse"
                                        label="Buka Pusat Aktivitas"
                                        @click="bukaAktivitasDanTutup"
                                    />
                                </div>

                                <div v-else-if="faseExport === 'galat'" class="rs-result-actions-row">
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        label="Tutup"
                                        @click="modalResultState = false"
                                    />
                                    <HcisButton
                                        variant="primary"
                                        size="sm"
                                        icon="bi-arrow-clockwise"
                                        label="Coba Lagi"
                                        @click="cobaLagiKonfigurasi"
                                    />
                                </div>
                            </template>
                        </HcisResultState>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisCheckbox from '@/components/ui/HcisCheckbox.vue';
import HcisCollapse from '@/components/ui/HcisCollapse.vue';
import HcisCollapseItem from '@/components/ui/HcisCollapseItem.vue';
import HcisDialog from '@/components/ui/HcisDialog.vue';
import HcisField from '@/components/ui/HcisField.vue';
import HcisResultState from '@/components/ui/HcisResultState.vue';
import HcisSplitter from '@/components/ui/HcisSplitter.vue';
import HcisTabs from '@/components/ui/HcisTabs.vue';
import { hcisMessage } from '@/components/ui/hcisMessage';
import { useActivityCenter } from '@/composables/useActivityCenter';
import { useShellState } from '@/composables/useShellState';
import BilahAksi from './BilahAksi.vue';
import GaleriTemplate from './GaleriTemplate.vue';
import PanelDimensiUkuran from './PanelDimensiUkuran.vue';
import PanelAgregatRacikan from './PanelAgregatRacikan.vue';
import PanelPratinjau from './PanelPratinjau.vue';
import PanelSaringan from './PanelSaringan.vue';
import { reportStudioApi } from './api/reportStudioApi';
import { useDefinisi } from './composables/useDefinisi';

const props = defineProps({
    baseUrl: { type: String, required: true },
    datasetAwal: { type: String, default: 'kepatuhan' },
    tanpaTopBar: { type: Boolean, default: false },
    tabUtama: { type: String, default: undefined },
});

const api = reportStudioApi(props.baseUrl);
const { mode, pilihan, definisi, galat, valid, geser } = useDefinisi(props.datasetAwal);

const katalog = ref(null);
const katalogGalat = ref('');
const galeri = ref(null);
const opsiFilter = ref({});

const statusPratinjau = ref('belum');
const hasil = ref(null);
const pesanGalat = ref('');
const format = ref('xlsx');
const emit = defineEmits(['ringkas', 'update:tabUtama', 'update:templateAktif', 'daftarTab']);

const shell = useShellState();
const activity = useActivityCenter();
const tabUtamaInternal = ref(props.tabUtama || 'galeri');
const seksiAktif = ref(['dimensi', 'kolom']);

watch(() => props.tabUtama, (v) => {
    if (v && v !== tabUtamaInternal.value) {
        tabUtamaInternal.value = v;
    }
});

watch(tabUtamaInternal, (v) => {
    emit('update:tabUtama', v);
});

const totalTemplate = computed(() =>
    (galeri.value?.resmi?.length || 0)
    + (galeri.value?.saya?.length || 0)
    + (galeri.value?.dibagikan?.length || 0)
);

/*
 * Ambang ponsel. Idiomnya menyalin yang sudah terbukti di HcisTabs.vue:310 —
 * `typeof window.matchMedia === 'function'` lebih dulu, baru `.matches`, karena
 * jsdom tidak mengimplementasikannya.
 */
const PONSEL = '(max-width: 991.98px)';

const bacaViewport = () =>
    typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia(PONSEL).matches;

const ponsel = ref(bacaViewport());

function segarkanViewport() {
    ponsel.value = bacaViewport();
}

// Layar yang menyempit saat studio terbuka jatuh ke galeri, bukan ke layar
// kosong dengan bilah aksi yang tak punya isi.
watch(ponsel, (v) => {
    if (v && tabUtamaInternal.value === 'studio') {
        tabUtamaInternal.value = 'galeri';
    }
}, { immediate: true });

/* Tab studio disembunyikan di ponsel: perakit tidak dirender di sana. */
const daftarTabUtama = computed(() => [
    {
        key: 'galeri',
        label: totalTemplate.value ? `Galeri Template (${totalTemplate.value})` : 'Galeri Template',
        icon: 'bi-collection-play-fill',
    },
    ...(ponsel.value ? [] : [{
        key: 'studio',
        label: 'Studio Perakit & Pratinjau',
        icon: 'bi-sliders',
    }]),
]);

watch(daftarTabUtama, (v) => {
    emit('daftarTab', v);
}, { immediate: true, deep: true });


const namaTemplate = ref('');
const deskripsiTemplate = ref('');
const dialogSimpan = ref(false);
const tabGaleri = ref('resmi');
const sibuk = ref(false);
const sedangMemuat = computed(() => statusPratinjau.value === 'memuat' || sibuk.value);

/*
 * Sebab pertama saja. Menumpuk seluruh galat di satu baris membuat bilah aksi
 * membungkus dua baris dan justru tidak terbaca — sementara galat lengkapnya
 * tetap muncul inline di panel penyebabnya.
 */
const sebabMati = computed(() => (valid.value ? '' : Object.values(galat.value)[0] ?? ''));

function bukaPusatAktivitas() {
    shell.state.showActivity = true;

    // Membuka panel dari sini harus memuat ulang isinya, persis seperti tombol
    // lonceng di topbar. Menyalakan benderanya saja menampilkan daftar hasil
    // pemuatan TERAKHIR — yang bisa berumur menit dan belum memuat tugas yang
    // baru saja dijalankan, sehingga panelnya terlihat kosong.
    activity.refresh();
}

/*
 * Ringkasan untuk chip di hero. `baris` sengaja null — bukan 0 — selama belum
 * ada pratinjau: nol terbaca sebagai fakta ("laporannya kosong") padahal
 * artinya "belum dihitung".
 */
const ringkas = computed(() => ({
    mode: mode.value,
    kolom: mode.value === 'ringkasan'
        ? pilihan.dimensi.length + pilihan.ukuran.length + pilihan.agregat.length
        : pilihan.kolom.length,
    baris: hasil.value?.rows?.length ?? null,
}));

watch(ringkas, (v) => emit('ringkas', v), { deep: true, immediate: true });

let pembatal = null;
let tunda = null;

async function muat() {
    try {
        katalog.value = await api.katalog(props.datasetAwal);

        /*
         * Pilihan saringan datang dari KATALOG, bukan dari endpoint modul.
         * Komponen ini milik SYSTEM: memanggil alamat SOP di sini berarti modul
         * berikutnya ikut memanggil endpoint SOP, dan alamat itu dijaga hak
         * halaman yang berbeda sehingga dropdown bisa kosong tanpa penjelasan.
         */
        opsiFilter.value = katalog.value?.opsi_saringan ?? {};
    } catch (e) {
        katalogGalat.value = e?.response?.data?.message ?? 'Katalog dataset gagal dimuat.';
    }
    try {
        galeri.value = await api.galeri();
    } catch {
        galeri.value = { resmi: [], saya: [], dibagikan: [] };
    }
}

const pratinjauHalaman = ref(1);
/* Total baris dari permintaan pertama, dipakai ulang saat berpindah halaman.
   Server hanya menghitungnya saat diminta: satu COUNT atas query beragregasi
   sama mahalnya dengan mengambil halamannya, dan angkanya tidak berubah
   selama definisi & pencarian tetap. */
const totalTerakhir = ref(0);
const pratinjauUkuranHalaman = ref(24);
const pratinjauPencarian = ref('');
const pratinjauSortKey = computed(() => pilihan.urut[0]?.key || '');
const pratinjauSortOrder = computed(() => pilihan.urut[0]?.arah || 'asc');

function onTableSort(sortData) {
    if (!sortData?.key) {
        pilihan.urut = [];
    } else {
        pilihan.urut = [{ key: sortData.key, arah: sortData.order === 'desc' ? 'desc' : 'asc' }];
    }
    pratinjauHalaman.value = 1;
    mintaPratinjau();
}

async function mintaPratinjau() {
    if (!valid.value) {
        statusPratinjau.value = 'belum';
        hasil.value = null;
        return;
    }

    // Request sebelumnya dibatalkan, bukan dibiarkan selesai: definisi berubah
    // lebih cepat daripada pratinjau, dan hasil lama yang datang belakangan
    // akan menimpa hasil baru.
    pembatal?.abort();
    pembatal = new AbortController();

    statusPratinjau.value = 'memuat';

    // Halaman pertama = definisi atau pencarian baru saja berubah, jadi total
    // wajib dihitung ulang. Halaman berikutnya memakai angka yang sama.
    const mintaTotal = pratinjauHalaman.value === 1;

    try {
        const r = await api.pratinjau(
            {
                definisi: definisi.value,
                page: pratinjauHalaman.value,
                per_page: pratinjauUkuranHalaman.value,
                search: pratinjauPencarian.value,
                hitung_total: mintaTotal,
            },
            pembatal.signal
        );

        if (r.total === null || r.total === undefined) {
            r.total = totalTerakhir.value;
            r.total_pages = r.per_page ? Math.ceil((totalTerakhir.value || 0) / r.per_page) : null;
        } else {
            totalTerakhir.value = r.total;
        }

        hasil.value = r;
        statusPratinjau.value = (r.rows && r.rows.length === 0) ? 'kosong' : 'siap';
    } catch (e) {
        if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return;

        const kode = e?.response?.data?.error_code;
        // Turun anggun, bukan kegagalan: tombol Jalankan tetap hidup.
        statusPratinjau.value = kode === 'REPORT_PREVIEW_TOO_HEAVY' ? 'berat' : 'galat';
        pesanGalat.value = e?.response?.data?.message ?? 'Terjadi kesalahan saat menyusun pratinjau.';
    }
}

watch(
    definisi,
    () => {
        pratinjauHalaman.value = 1;
        clearTimeout(tunda);
        tunda = setTimeout(mintaPratinjau, 400);
    },
    { deep: true }
);

function onTableColumnReorder(payload) {
    if (!payload?.columns || !Array.isArray(payload.columns)) return;
    const newOrderKeys = payload.columns.map((c) => c.key);

    if (mode.value === 'ringkasan') {
        // 1. Reorder dimensi berdasarkan urutan kolom baru di tabel
        const currentDimensi = [...pilihan.dimensi];
        const newDimensi = [];
        for (const k of newOrderKeys) {
            if (currentDimensi.includes(k) && !newDimensi.includes(k)) {
                newDimensi.push(k);
            }
        }
        for (const k of currentDimensi) {
            if (!newDimensi.includes(k)) newDimensi.push(k);
        }
        pilihan.dimensi.splice(0, pilihan.dimensi.length, ...newDimensi);

        // 2. Reorder ukuran berdasarkan urutan kolom baru di tabel
        const currentUkuran = [...pilihan.ukuran];
        const newUkuran = [];
        for (const k of newOrderKeys) {
            if (currentUkuran.includes(k) && !newUkuran.includes(k)) {
                newUkuran.push(k);
            }
        }
        for (const k of currentUkuran) {
            if (!newUkuran.includes(k)) newUkuran.push(k);
        }
        pilihan.ukuran.splice(0, pilihan.ukuran.length, ...newUkuran);
    } else if (mode.value === 'rinci') {
        // Reorder kolom rinci berdasarkan urutan kolom baru di tabel
        const currentKolom = [...pilihan.kolom];
        const newKolom = [];
        for (const k of newOrderKeys) {
            if (currentKolom.includes(k) && !newKolom.includes(k)) {
                newKolom.push(k);
            }
        }
        for (const k of currentKolom) {
            if (!newKolom.includes(k)) newKolom.push(k);
        }
        pilihan.kolom.splice(0, pilihan.kolom.length, ...newKolom);
    }
}

onMounted(() => {
    muat();
    window.addEventListener('resize', segarkanViewport);
});

onBeforeUnmount(() => {
    clearTimeout(tunda);
    pembatal?.abort();
    window.removeEventListener('resize', segarkanViewport);
});

function bukaDialogSimpan() {
    if (templateAktif.value) {
        namaTemplate.value = templateAktif.value.nama || '';
        deskripsiTemplate.value = templateAktif.value.deskripsi || '';
    } else {
        namaTemplate.value = '';
        deskripsiTemplate.value = '';
    }
    dialogSimpan.value = true;
}

function lepaskanTemplateAktif() {
    pilihan.dimensi.splice(0, pilihan.dimensi.length);
    pilihan.ukuran.splice(0, pilihan.ukuran.length);
    pilihan.agregat.splice(0, pilihan.agregat.length);
    pilihan.kolom.splice(0, pilihan.kolom.length);
    pilihan.saringan.splice(0, pilihan.saringan.length);
    pilihan.urut.splice(0, pilihan.urut.length);
    pilihan.opsi.subtotal = false;
    pilihan.opsi.grand_total = false;
    mode.value = 'ringkasan';
    hasil.value = null;
    statusPratinjau.value = 'belum';
    templateAktif.value = null;
    namaTemplate.value = '';
    deskripsiTemplate.value = '';
    hcisMessage({ type: 'info', message: 'Template ditutup. Konfigurasi builder telah dikosongkan.' });
}

async function simpanTemplate() {
    if (!namaTemplate.value.trim()) return;

    sibuk.value = true;
    try {
        let res;
        const payload = {
            nama: namaTemplate.value.trim(),
            deskripsi: deskripsiTemplate.value.trim(),
            definisi: definisi.value,
        };

        const sedangUpdate = Boolean(templateAktif.value?.kode);

        // Jika sedang mengedit template yang sudah ada, perbarui template tersebut
        if (sedangUpdate) {
            try {
                res = await api.perbarui(templateAktif.value.kode, payload);
            } catch (err) {
                // Jika template sistem / resmi (bukan milik sendiri), buat template baru
                if (err?.response?.status === 403) {
                    res = await api.simpan(payload);
                } else {
                    throw err;
                }
            }
        } else {
            res = await api.simpan(payload);
        }

        const targetKode = res?.kode || templateAktif.value?.kode;

        // 1. Segarkan galeri
        galeri.value = await api.galeri();

        // 2. Kosongkan seluruh state builder
        pilihan.dimensi.splice(0, pilihan.dimensi.length);
        pilihan.ukuran.splice(0, pilihan.ukuran.length);
        pilihan.kolom.splice(0, pilihan.kolom.length);
        pilihan.saringan.splice(0, pilihan.saringan.length);
        pilihan.urut.splice(0, pilihan.urut.length);
        pilihan.opsi.subtotal = false;
        pilihan.opsi.grand_total = false;
        mode.value = 'ringkasan';
        hasil.value = null;
        statusPratinjau.value = 'belum';
        templateAktif.value = null;
        namaTemplate.value = '';
        deskripsiTemplate.value = '';
        dialogSimpan.value = false;
        const pesanSukses = sedangUpdate ? 'Template berhasil diperbarui.' : 'Template berhasil disimpan.';

        // 3. Pindah tab ke Galeri Template dan tab sub "Milik Saya"
        tabGaleri.value = 'saya';
        tabUtamaInternal.value = 'galeri';
        emit('update:tabUtama', 'galeri');

        // 4. Buka modal pratinjau dari template yang baru dibuat / diperbarui
        const listSaya = galeri.value?.saya || [];
        const templateTerkait = listSaya.find((t) => (t.kode ?? t.Kode) === targetKode) || listSaya[0];
        if (templateTerkait) {
            bukaPratinjauTemplate(templateTerkait);
        }

        hcisMessage({ type: 'success', message: pesanSukses });
    } catch (e) {
        const msg = e?.response?.data?.message ?? 'Template gagal disimpan.';
        hcisMessage({ type: 'error', message: msg });
    } finally {
        sibuk.value = false;
    }
}

function gagalDijalankan(e) {
    hcisMessage({
        type: 'error',
        message: e?.response?.data?.message ?? 'Laporan gagal dijalankan.',
    });
}

// ══ FLOW EKSEKUSI EXPORT INTERAKTIF (GOJEK-STYLE ORDER & RESULT STATE) ══
const dialogKonfigurasiExport = ref(false);
const modalResultState = ref(false);
const faseExport = ref('konfigurasi'); // 'konfigurasi' | 'proses' | 'sukses' | 'galat'
const targetExport = ref(null);
const customNamaFile = ref('');
const formatExportDipilih = ref('xlsx');
const hasilTugasExport = ref(null);
const pesanGalatExport = ref('');

const placeholderNamaFile = computed(() => {
    const baseName = targetExport.value?.nama || 'Laporan_Kepatuhan_SOP';
    const cleanBase = String(baseName).trim().replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_\-]/g, '');
    const dateStr = new Date().toISOString().slice(0, 10);
    return `${cleanBase || 'Laporan'}_${dateStr}`;
});

const hitungJumlahKolomExport = computed(() => {
    if (targetExport.value?.definisi) {
        const def = targetExport.value.definisi;
        if (def.mode === 'rinci') return def.kolom?.length || 0;
        return (def.dimensi?.length || 0) + (def.ukuran?.length || 0) + (def.agregat?.length || 0);
    }
    if (mode.value === 'rinci') return pilihan.kolom.length;
    return pilihan.dimensi.length + pilihan.ukuran.length;
});

const statusResultState = computed(() => {
    if (faseExport.value === 'proses') return 'loading';
    if (faseExport.value === 'sukses') return 'success';
    return 'error';
});

const kickerResultState = computed(() => {
    if (faseExport.value === 'proses') return 'SISTEM ANTREAN HCIS';
    if (faseExport.value === 'sukses') return 'DOKUMEN SIAP DIPROSES';
    return 'KENDALA SISTEM';
});

const judulResultState = computed(() => {
    if (faseExport.value === 'proses') return 'Menyiapkan Antrean Export…';
    if (faseExport.value === 'sukses') return 'Export Berhasil Masuk Antrean!';
    return 'Gagal Menjadwalkan Export';
});

const deskripsiResultState = computed(() => {
    if (faseExport.value === 'proses') return 'Memvalidasi dataset dan mendaftarkan pekerjaan ke antrean worker latar belakang...';
    if (faseExport.value === 'sukses') return 'Pekerjaan export telah dijadwalkan di latar belakang. Anda dapat memantau progresnya di Pusat Aktivitas atau tetap melanjutkan pekerjaan.';
    return pesanGalatExport.value || 'Terjadi kesalahan saat menjadwalkan export.';
});

const rincianReceiptExport = computed(() => {
    const finalNama = customNamaFile.value.trim() || placeholderNamaFile.value;
    const ext = formatExportDipilih.value.toLowerCase();
    return [
        { label: 'Nama Dokumen', value: `${finalNama}.${ext}` },
        { label: 'Format File', value: `${ext.toUpperCase()} (.${ext})` },
        { label: 'Status Antrean', value: 'Menunggu Worker / Berjalan' },
        { label: 'Task ID', value: hasilTugasExport.value?.task_id || '—' },
    ];
});

function bukaModalEksekusiExport(target) {
    targetExport.value = {
        ...target,
        definisi: target.definisi ? JSON.parse(JSON.stringify(target.definisi)) : null,
    };
    customNamaFile.value = '';
    formatExportDipilih.value = target.format || format.value || 'xlsx';
    modalResultState.value = false;
    hasilTugasExport.value = null;
    pesanGalatExport.value = '';
    dialogKonfigurasiExport.value = true;
}

function bukaModalEksekusiExportDariStudio() {
    bukaModalEksekusiExport({
        tipe: 'studio',
        nama: templateAktif.value?.nama || 'Laporan Kepatuhan Dokumen',
        format: format.value,
        definisi: definisi.value,
    });
}

function jalankanDariGaleri(kode) {
    let nama = 'Template Laporan';
    const list = [...(galeri.value?.resmi || []), ...(galeri.value?.saya || []), ...(galeri.value?.dibagikan || [])];
    const found = list.find((t) => (t.kode ?? t.Kode) === kode);
    if (found) nama = found.nama ?? found.Nama ?? 'Template Laporan';
    bukaModalEksekusiExport({
        tipe: 'galeri',
        kode,
        nama,
        format: format.value,
    });
}

function bukaModalEksekusiExportDariPratinjau() {
    if (!templateDipratinjau.value) return;
    const t = templateDipratinjau.value;
    const kode = t.kode ?? t.Kode;
    const nama = t.nama ?? t.Nama ?? 'Template Laporan';
    const def = templateDefinisiSementara.value ? JSON.parse(JSON.stringify(templateDefinisiSementara.value)) : null;
    dialogPratinjauTemplate.value = false;
    bukaModalEksekusiExport({
        tipe: 'pratinjau',
        kode,
        nama,
        format: format.value,
        definisi: def,
    });
}

async function prosesEksekusiExport() {
    dialogKonfigurasiExport.value = false;
    modalResultState.value = true;
    faseExport.value = 'proses';

    try {
        const finalName = customNamaFile.value.trim() || undefined;
        const payload = {
            format: formatExportDipilih.value,
            custom_name: finalName,
        };

        if (targetExport.value?.definisi) {
            payload.definisi = targetExport.value.definisi;
        } else if (targetExport.value?.kode) {
            payload.template_kode = targetExport.value.kode;
        } else {
            payload.definisi = definisi.value;
        }

        const [res] = await Promise.all([
            api.jalankan(payload),
            new Promise((resolve) => setTimeout(resolve, 600)),
        ]);

        hasilTugasExport.value = res;
        faseExport.value = 'sukses';

        // Polling Pusat Aktivitas LAZY: ia berhenti sendiri saat tak ada tugas
        // berjalan dan hanya menyala lagi lewat notifyNewTask(). Tanpa panggilan
        // ini, tugas yang baru saja didaftarkan tidak terlihat oleh siapa pun —
        // panel baru memuatnya pada pemicu lain (membuka lonceng, tab kembali
        // fokus, pindah halaman), yang biasanya terjadi setelah tugasnya SELESAI.
        // Akibatnya fase antre & proses tak pernah terlihat, dan export tampak
        // "tiba-tiba sudah jadi".
        activity.notifyNewTask();
    } catch (e) {
        pesanGalatExport.value = e?.response?.data?.message ?? 'Terjadi kesalahan saat menjadwalkan export.';
        faseExport.value = 'galat';
    }
}

function cobaLagiKonfigurasi() {
    modalResultState.value = false;
    dialogKonfigurasiExport.value = true;
}

function bukaAktivitasDanTutup() {
    modalResultState.value = false;
    bukaPusatAktivitas();
}

async function jalankanDefinisi() {
    bukaModalEksekusiExportDariStudio();
}

async function jalankanTemplate(kode, resolve, reject) {
    try {
        jalankanDariGaleri(kode);
        resolve?.();
    } catch (e) {
        reject?.(e);
    }
}

const templateAktif = ref(null);

const dialogPratinjauTemplate = ref(false);
const templateDipratinjau = ref(null);
const templateDefinisiSementara = ref(null);
const statusPratinjauTemplate = ref('belum');
const hasilPratinjauTemplate = ref(null);
const galatPratinjauTemplate = ref('');
const templateHalaman = ref(1);
const templateUkuranHalaman = ref(24);
const templatePencarian = ref('');
const templateSortKey = ref('');
const templateSortOrder = ref('asc');

let pembatalTemplate = null;

watch(dialogPratinjauTemplate, (isOpen) => {
    if (!isOpen) {
        pembatalTemplate?.abort();
        templateDipratinjau.value = null;
        templateDefinisiSementara.value = null;
        hasilPratinjauTemplate.value = null;
        templateHalaman.value = 1;
        templatePencarian.value = '';
        templateSortKey.value = '';
        templateSortOrder.value = 'asc';
        galatPratinjauTemplate.value = '';
    }
});

function onTemplateSort(sortData) {
    const key = sortData?.key || '';
    const order = sortData?.order || 'asc';
    templateSortKey.value = key;
    templateSortOrder.value = order;
    templateHalaman.value = 1;

    // Perbarui urutan sort di definisi sementara tanpa mengubah template permanen
    if (templateDefinisiSementara.value) {
        templateDefinisiSementara.value.urut = key ? [{ key, arah: order }] : [];
    }

    muatHalamanTemplate();
}

function onTemplateColumnReorder(newColumns) {
    if (!hasilPratinjauTemplate.value || !newColumns) return;
    const newOrderKeys = (Array.isArray(newColumns) ? newColumns : []).map((col) =>
        (typeof col === 'string' ? col : col.key)
    ).filter(Boolean);

    // 1. Reorder tampilan kolom di tabel modal pratinjau secara instan
    const currentCols = hasilPratinjauTemplate.value.columns ?? [];
    const reorderedCols = [];
    for (const k of newOrderKeys) {
        const found = currentCols.find((c) => c.key === k);
        if (found) reorderedCols.push(found);
    }
    for (const c of currentCols) {
        if (!reorderedCols.some((r) => r.key === c.key)) reorderedCols.push(c);
    }
    hasilPratinjauTemplate.value.columns = reorderedCols;

    // 2. Sinkronkan ke definisi sementara agar hasil export mengikuti urutan kolom baru
    if (templateDefinisiSementara.value) {
        const tMode = templateDefinisiSementara.value.mode;
        if (tMode === 'ringkasan') {
            const curDim = [...templateDefinisiSementara.value.dimensi];
            const newDim = [];
            for (const k of newOrderKeys) {
                if (curDim.includes(k) && !newDim.includes(k)) newDim.push(k);
            }
            for (const k of curDim) {
                if (!newDim.includes(k)) newDim.push(k);
            }
            templateDefinisiSementara.value.dimensi = newDim;

            const curUkr = [...templateDefinisiSementara.value.ukuran];
            const newUkr = [];
            for (const k of newOrderKeys) {
                if (curUkr.includes(k) && !newUkr.includes(k)) newUkr.push(k);
            }
            for (const k of curUkr) {
                if (!newUkr.includes(k)) newUkr.push(k);
            }
            templateDefinisiSementara.value.ukuran = newUkr;
        } else if (tMode === 'rinci') {
            const curKol = [...templateDefinisiSementara.value.kolom];
            const newKol = [];
            for (const k of newOrderKeys) {
                if (curKol.includes(k) && !newKol.includes(k)) newKol.push(k);
            }
            for (const k of curKol) {
                if (!newKol.includes(k)) newKol.push(k);
            }
            templateDefinisiSementara.value.kolom = newKol;
        }
    }
}

async function muatHalamanTemplate() {
    if (!templateDipratinjau.value || !templateDefinisiSementara.value) return;

    pembatalTemplate?.abort();
    pembatalTemplate = new AbortController();
    statusPratinjauTemplate.value = 'memuat';

    try {
        const payload = {
            definisi: templateDefinisiSementara.value,
            page: templateHalaman.value,
            per_page: templateUkuranHalaman.value,
            search: templatePencarian.value,
            sort_key: templateSortKey.value || undefined,
            sort_order: templateSortOrder.value || undefined,
        };

        const r = await api.pratinjau(payload, pembatalTemplate.signal);
        hasilPratinjauTemplate.value = r;
        statusPratinjauTemplate.value = (r.rows && r.rows.length === 0) ? 'kosong' : 'siap';
    } catch (e) {
        if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return;
        statusPratinjauTemplate.value = 'galat';
        galatPratinjauTemplate.value = e?.response?.data?.message ?? 'Gagal memuat pratinjau data template.';
    }
}

async function bukaPratinjauTemplate(t) {
    if (!t) return;
    templateDipratinjau.value = t;

    let def = t.definisi;
    if (!def && t.Definisi_Json) {
        try {
            def = typeof t.Definisi_Json === 'string' ? JSON.parse(t.Definisi_Json) : t.Definisi_Json;
        } catch {
            def = null;
        }
    }

    if (def) {
        templateDefinisiSementara.value = {
            versi: def.versi ?? 1,
            dataset: def.dataset ?? props.datasetAwal,
            mode: (def.mode || 'ringkasan').toLowerCase(),
            dimensi: Array.isArray(def.dimensi) ? [...def.dimensi] : [],
            ukuran: Array.isArray(def.ukuran) ? [...def.ukuran] : [],
            agregat: Array.isArray(def.agregat) ? JSON.parse(JSON.stringify(def.agregat)) : [],
            kolom: Array.isArray(def.kolom) ? [...def.kolom] : [],
            saringan: Array.isArray(def.saringan) ? JSON.parse(JSON.stringify(def.saringan)) : [],
            urut: Array.isArray(def.urut) ? JSON.parse(JSON.stringify(def.urut)) : [],
            opsi: def.opsi ? { ...def.opsi } : { grand_total: false },
            bagan: def.bagan ?? null,
            format_bawaan: def.format_bawaan ?? 'xlsx',
        };
    } else {
        templateDefinisiSementara.value = null;
    }

    dialogPratinjauTemplate.value = true;
    templateHalaman.value = 1;
    templatePencarian.value = '';
    templateSortKey.value = templateDefinisiSementara.value?.urut?.[0]?.key || '';
    templateSortOrder.value = templateDefinisiSementara.value?.urut?.[0]?.arah || 'asc';
    galatPratinjauTemplate.value = '';
    hasilPratinjauTemplate.value = null;
    await muatHalamanTemplate();
}

function bukaTemplateDiStudio() {
    if (!templateDipratinjau.value) return;
    const t = templateDipratinjau.value;
    const defSementara = templateDefinisiSementara.value;
    dialogPratinjauTemplate.value = false;
    terapkanTemplate(t, defSementara);
}

function jalankanExportDariPratinjau() {
    bukaModalEksekusiExportDariPratinjau();
}

function terapkanTemplate(t, defOverride = null) {
    if (!t) return;
    const kode = t.kode ?? t.Kode;
    const nama = t.nama ?? t.Nama ?? 'Template';
    const deskripsi = t.deskripsi ?? t.Deskripsi ?? '';
    let def = defOverride ?? t.definisi;
    if (!def && t.Definisi_Json) {
        try {
            def = typeof t.Definisi_Json === 'string' ? JSON.parse(t.Definisi_Json) : t.Definisi_Json;
        } catch {
            def = null;
        }
    }

    if (def) {
        mode.value = (def.mode || 'ringkasan').toLowerCase();
        pilihan.dimensi = Array.isArray(def.dimensi) ? [...def.dimensi] : [];
        pilihan.ukuran = Array.isArray(def.ukuran) ? [...def.ukuran] : [];
        // Tanpa baris ini, template yang memuat agregat racikan akan terbuka
        // TANPA agregatnya — lalu tersimpan kembali dalam keadaan itu, dan
        // pemakainya kehilangan susunannya tanpa pernah diberi tahu.
        pilihan.agregat = Array.isArray(def.agregat) ? JSON.parse(JSON.stringify(def.agregat)) : [];
        pilihan.kolom = Array.isArray(def.kolom) ? [...def.kolom] : [];
        pilihan.saringan = Array.isArray(def.saringan) ? JSON.parse(JSON.stringify(def.saringan)) : [];
        pilihan.opsi = def.opsi ? { ...def.opsi } : { grand_total: false };

        templateAktif.value = {
            kode,
            nama,
            deskripsi,
            mode: mode.value,
        };

        // Beralih ke studio splitter agar user langsung melihat perakit dan pratinjau bersatu
        tabUtamaInternal.value = 'studio';

        hcisMessage({
            type: 'info',
            message: `Template «${nama}» dimuat di Studio Perakit.`,
        });

        // Trigger permintaan pratinjau langsung di studio
        mintaPratinjau();
    } else {
        jalankanTemplate(kode);
    }
}

async function duplikatTemplate(kode, resolve, reject) {
    try {
        await api.duplikat(kode);
        galeri.value = await api.galeri();
        tabGaleri.value = 'saya';
        hcisMessage({ type: 'success', message: 'Template diduplikat ke "Milik Saya".' });
        resolve?.();
    } catch (e) {
        const msg = e?.response?.data?.message ?? 'Template gagal diduplikat.';
        hcisMessage({ type: 'error', message: msg });
        reject?.(e);
    }
}

async function hapusTemplate(kode, resolve, reject) {
    try {
        await api.hapus(kode);
        galeri.value = await api.galeri();
        hcisMessage({ type: 'info', message: 'Template dinonaktifkan.' });
        resolve?.();
    } catch (e) {
        const msg = e?.response?.data?.message ?? 'Template gagal dinonaktifkan.';
        hcisMessage({ type: 'error', message: msg });
        reject?.(e);
    }
}

watch(templateAktif, (v) => {
    emit('update:templateAktif', v);
}, { immediate: true });

defineExpose({
    pilihan,
    mode,
    templateAktif,
    tabUtama: tabUtamaInternal,
    daftarTabUtama,
});
</script>

<style scoped>
.rs-root {
    position: relative;
    padding-bottom: 5.5rem;
}

.rs-top-bar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0;
    margin-bottom: 1.25rem;
    gap: 0.75rem;
}

.rs-top-bar-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.rs-top-bar-right {
    position: absolute;
    right: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 991.98px) {
    .rs-top-bar {
        flex-direction: column;
        justify-content: center;
        gap: 0.65rem;
        padding: 0.25rem 0;
    }

    .rs-top-bar-right {
        position: static;
        margin-left: 0;
        justify-content: center;
    }
}

.rs-template-aktif-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.7rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: linear-gradient(135deg, #fefce8, #f0fdf4);
    border: 1px solid #fde047;
    color: #854d0e;
    font-size: 0.76rem;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(234, 179, 8, 0.15);
}

.rs-template-aktif-star {
    color: #eab308;
    display: flex;
    align-items: center;
}

.rs-template-aktif-text {
    font-weight: 700;
    color: #713f12;
}

.rs-template-aktif-mode {
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.06);
    text-transform: capitalize;
}

.rs-template-reset-btn {
    border: none;
    background: transparent;
    color: #a16207;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    margin-left: 0.15rem;
    border-radius: 9999px;
}

.rs-template-reset-btn:hover {
    color: #b91c1c;
}

.rs-builder-active-template-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(254, 252, 232, 0.95), rgba(240, 253, 244, 0.95));
    border: 1px solid rgba(234, 179, 8, 0.4);
    box-shadow: 0 4px 14px -3px rgba(234, 179, 8, 0.12);
}

.rs-builder-active-template-left {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.rs-template-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: #eab308;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}

.rs-template-active-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #713f12;
}

.rs-template-active-mode-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: rgba(113, 63, 18, 0.1);
    color: #854d0e;
}

.rs-btn-tutup-template {
    border-color: rgba(202, 138, 4, 0.4) !important;
    background: rgba(255, 255, 255, 0.9) !important;
    color: #a16207 !important;
    font-weight: 600 !important;
}

.rs-btn-tutup-template:hover {
    background: #fef08a !important;
    border-color: #ca8a04 !important;
    color: #713f12 !important;
}

/* ══ TAB CONTENT VIEWS ══ */
.rs-view-galeri {
    animation: rs-tab-fade-in 200ms ease;
}

.rs-view-studio {
    animation: rs-tab-fade-in 200ms ease;
    padding-bottom: 4.5rem;
}

@keyframes rs-tab-fade-in {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.rs-galeri-card {
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
}

.rs-kokpit-card {
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.rs-kokpit-card :deep(.hcis-card__body) {
    padding: 0;
}

.rs-kokpit {
    min-height: 600px;
    background: var(--hcis-surface, #ffffff);
    border: none;
}

.rs-perakit-kolom {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem 1.15rem 2rem 1.25rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border-right: 1px solid var(--hcis-border-subtle, #e2e8f0);
    transition: opacity 200ms ease;
    position: relative;
}

.rs-perakit-kolom.is-loading-locked {
    pointer-events: none !important;
    user-select: none !important;
    opacity: 0.62;
    cursor: not-allowed !important;
}

.rs-perakit-kolom.is-loading-locked * {
    pointer-events: none !important;
    cursor: not-allowed !important;
}

.rs-perakit-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: nowrap;
    padding-bottom: 0.25rem;
}

.rs-perakit-head-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1 1 auto;
}

.rs-perakit-head-main .sop-eyebrow {
    font-size: 0.66rem;
    margin-bottom: 0.05rem;
}

.rs-perakit-head-main .sop-h {
    font-size: 0.85rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ══ COMPACT SINGLE-ROW TACTICAL BENTUK SWITCH ══ */
.rs-bentuk-switch-box {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.rs-bentuk-switch-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
}

.rs-tactical-switch {
    position: relative;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: center;
    padding: 2px;
    background: #e2e8f0;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-pill, 9999px);
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
    user-select: none;
    z-index: 1;
    min-width: 154px;
}

.rs-tactical-switch-opt {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: none;
    background: transparent;
    font-size: 0.69rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    line-height: 1.2;
    transition: color 150ms ease;
    white-space: nowrap;
    text-align: center;
}

.rs-tactical-switch-opt:hover {
    color: #1e293b;
}

.rs-tactical-switch-opt.is-active {
    color: #4338ca;
    font-weight: 750;
}

.rs-tactical-switch-opt i {
    font-size: 0.72rem;
    color: inherit;
    transition: transform 200ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.rs-tactical-switch-opt.is-active i {
    transform: scale(1.1);
    color: #4f46e5;
}

/* Sliding Glider Background Thumb */
.rs-tactical-glider {
    position: absolute;
    top: 2px;
    bottom: 2px;
    left: 2px;
    width: calc(50% - 2px);
    border-radius: var(--hcis-radius-pill, 9999px);
    background: #ffffff;
    border: 1px solid rgba(99, 102, 241, 0.25);
    box-shadow: 0 1px 4px -1px rgba(99, 102, 241, 0.22), 0 1px 2px rgba(15, 23, 42, 0.08);
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1;
    pointer-events: none;
}

.rs-tactical-switch.is-rinci .rs-tactical-glider {
    transform: translateX(100%);
}

.rs-pratinjau-kolom {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem 1.35rem 2rem 1.15rem;
    background: var(--hcis-surface, #ffffff);
}

.rs-perakit-kolom :deep(.hcis-collapse-item) {
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 2px 8px -1px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    transition: all 180ms ease;
    margin-bottom: 0.75rem;
}

.rs-perakit-kolom :deep(.hcis-collapse-item--active) {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 8px 24px -2px rgba(99, 102, 241, 0.12), 0 2px 6px rgba(15, 23, 42, 0.04);
}

.rs-perakit-kolom :deep(.hcis-collapse-item__header) {
    padding: 0.85rem 1.15rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.rs-perakit-kolom :deep(.hcis-collapse-item--active .hcis-collapse-item__header) {
    background: var(--hcis-surface-brand, #eef2ff);
    border-bottom-color: var(--hcis-border-brand, #c7d2fe);
}

.rs-perakit-kolom :deep(.hcis-collapse-item__content) {
    padding: 1.15rem;
    background: var(--hcis-surface, #ffffff);
}

:deep(.rs-dialog-template-modal) {
    max-width: calc(100vw - 32px) !important;
    width: calc(100vw - 32px) !important;
    height: calc(100vh - 32px) !important;
    max-height: calc(100vh - 32px) !important;
    display: flex !important;
    flex-direction: column !important;
}

:deep(.rs-dialog-template-modal .hcis-dialog__body) {
    padding: 1rem 1.5rem !important;
    overflow-y: auto !important;
    flex: 1 1 100% !important;
    display: flex !important;
    flex-direction: column !important;
}

.rs-dialog-pratinjau-wrapper {
    width: 100%;
    display: flex;
    flex-direction: column;
    flex: 1 1 100%;
    min-height: 0;
    padding: 0;
}

.rs-dialog-pratinjau-wrapper :deep(.rs-preview) {
    min-height: unset;
    height: 100%;
    display: flex;
    flex-direction: column;
    flex: 1 1 100%;
}

.rs-dialog-pratinjau-wrapper :deep(.rs-tabel-section) {
    flex: 1 1 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.rs-dialog-pratinjau-wrapper :deep(.rs-tabel-bungkus) {
    box-shadow: none;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex: 1 1 100%;
    overflow-y: auto;
}

.rs-format-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
}

.rs-mode {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #f1f5f9);
}

/* Segmented dua pilihan tidak boleh melar selebar kolom — dua kata di dalam
   bilah 470px terbaca seperti kesalahan tata letak, bukan pilihan.
   Akarnya `.hcis-tabs-wrapper` (bukan `.hcis-tabs`) dan ia `width: 100%`. */
.rs-mode :deep(.hcis-tabs-wrapper) {
    width: auto;
    flex: 0 0 auto;
}

.rs-grain-box {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.55rem 0.85rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    margin: 0;
}

.rs-grain-icon {
    font-size: 0.85rem;
    color: var(--hcis-brand-600, #4f46e5);
    margin-top: 0.05rem;
    flex-shrink: 0;
}

.rs-grain {
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--hcis-text-muted, #64748b);
    margin: 0;
}

.rs-opsi {
    font-size: 0.82rem;
    color: var(--hcis-text, #1e293b);
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    margin-top: 0.25rem;
}

.rs-opsi-label {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.8rem;
}

.rs-ponsel-catatan {
    margin: 0 0 0.75rem;
    padding: 0.6rem 0.8rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border-left: 3px solid var(--hcis-brand-600, #4f46e5);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8rem;
    color: var(--hcis-text, #334155);
}

.rs-dialog-btn {
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.35rem 0.9rem;
    color: var(--hcis-text-strong, #1e293b);
    cursor: pointer;
    transition: all 150ms ease;
}

.rs-dialog-btn:hover {
    background: var(--hcis-surface-soft, #f8fafc);
    border-color: var(--hcis-border-strong, #94a3b8);
}

.rs-dialog-btn.utama {
    background: var(--hcis-brand-600, #4f46e5);
    border-color: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.2);
}

.rs-dialog-btn.utama:hover {
    background: var(--hcis-brand-700, #4338ca);
    border-color: var(--hcis-brand-700, #4338ca);
}

.rs-dialog-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}



.rs-galat-katalog {
    padding: 1rem 1.25rem;
    color: var(--hcis-danger-700, #b91c1c);
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--hcis-radius-lg, 12px);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
}

:deep(.rs-dialog-template-modal) {
    max-width: 1200px !important;
    width: 95vw !important;
}

.rs-dialog-pratinjau-wrapper {
    min-height: 420px;
}

.rs-export-config-stack {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.rs-export-section-card {
    position: relative;
    border-radius: var(--hcis-radius-xl, 18px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    padding: 0.95rem 1.15rem;
    box-shadow: 0 4px 18px -4px rgba(15, 23, 42, 0.07), 0 2px 6px -2px rgba(99, 102, 241, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.rs-export-name-card {
    background: linear-gradient(145deg, #ffffff 0%, rgba(248, 250, 252, 0.95) 55%, rgb(var(--hcis-indigo-50-rgb) / 0.35) 100%);
    border-color: rgba(99, 102, 241, 0.18);
}

.rs-export-format-card {
    background: linear-gradient(145deg, #ffffff 0%, rgba(248, 250, 252, 0.9) 45%, rgb(var(--hcis-indigo-50-rgb) / 0.4) 100%);
    border-color: rgba(99, 102, 241, 0.2);
}

.rs-export-snapshot-card {
    background:
        radial-gradient(180px 120px at 95% -10%, rgb(var(--hcis-indigo-500-rgb) / 0.08), transparent 70%),
        linear-gradient(145deg, #ffffff 0%, rgb(var(--hcis-indigo-50-rgb) / 0.5) 45%, rgba(241, 245, 249, 0.9) 100%);
    border-color: rgba(99, 102, 241, 0.25);
}

.rs-card-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
}

.rs-card-section-title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
    letter-spacing: -0.01em;
}

.rs-card-section-tag {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    background: rgba(100, 116, 139, 0.1);
    padding: 0.08rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
}

.rs-format-active-tag {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-brand-700, #4338ca);
    background: rgb(var(--hcis-indigo-50-rgb) / 0.9);
    border: 1px solid rgba(99, 102, 241, 0.3);
    padding: 0.1rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
}

.rs-format-tabs-wrap {
    display: flex;
    width: 100%;
}

.rs-format-tabs-wrap :deep(.hcis-tabs) {
    width: 100%;
}

.rs-snapshot-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.45rem;
}

.rs-snapshot-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.42rem 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(203, 213, 225, 0.65);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.rs-snapshot-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.rs-snapshot-val {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
    display: inline-flex;
    align-items: center;
    max-width: 60%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rs-standalone-result-overlay {
    position: fixed;
    inset: 0;
    z-index: var(--hcis-z-modal, 2010);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--hcis-space-4, 16px);
    background-color: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(12px) saturate(160%);
    -webkit-backdrop-filter: blur(12px) saturate(160%);
}

.rs-standalone-result-wrapper {
    position: relative;
    max-width: 460px;
    width: 100%;
    max-height: calc(100vh - 2.5rem);
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    animation: rsResultFadeIn 240ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.rs-standalone-result-wrapper :deep(.hcis-result-state--glass),
.rs-standalone-result-wrapper :deep(.hcis-result-state--card) {
    padding: 1.5rem 1.25rem !important;
    border-radius: var(--hcis-radius-xl, 18px);
}

.rs-standalone-result-wrapper :deep(.hcis-result-state__title) {
    font-size: 1.075rem !important;
    margin-top: 0.5rem !important;
    margin-bottom: 0.25rem !important;
}

.rs-standalone-result-wrapper :deep(.hcis-result-state__description) {
    font-size: 0.78rem !important;
    line-height: 1.4 !important;
    max-width: 380px !important;
}

.rs-standalone-result-wrapper :deep(.hcis-result-state__receipt) {
    margin-top: 0.85rem !important;
    margin-bottom: 0.25rem !important;
    padding: 0.45rem 0.75rem !important;
}

.rs-standalone-result-wrapper :deep(.hcis-result-state__receipt-row) {
    padding: 0.3rem 0 !important;
    font-size: 0.76rem !important;
}

@keyframes rsResultFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.rs-result-actions-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    width: 100%;
    margin-top: 0.85rem;
    flex-wrap: wrap;
}

.rs-pdf-col-warning {
    margin-top: 10px;
    padding: 8px 12px;
    background: #f0f7ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 11.5px;
    line-height: 1.45;
    color: #1e40af;
}

.rs-pdf-col-warning i {
    font-size: 13px;
    margin-top: 1px;
    flex-shrink: 0;
    color: #2563eb;
}
</style>
