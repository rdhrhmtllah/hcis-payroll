<template>
    <Head title="Pola Halaman (Archetypes) — HCIS Design System" />

    <div class="hcis-page hcis-stack ds-archetypes-root">
        <!-- Top Portal Navigation -->
        <DsPortalNav />

        <!-- Header -->
        <HcisHeader
            title="Pola & Cetak Biru Halaman (Page Archetypes)"
            subtitle="Standar arsitektur halaman enterprise HCIS. Setiap pola menggabungkan komponen atom menjadi pengalaman pengguna yang konsisten, responsif, dan siap produksi."
        >
            <template #actions>
                <div class="ds-pattern-stats">
                    <HcisBadge label="5 Pola Siap Pakai" variant="brand" dot />
                    <HcisBadge label="100% Native Hcis*" variant="primary" />
                </div>
            </template>
        </HcisHeader>

        <!-- Navigation Tabs between Archetypes -->
        <HcisTabs
            v-model="activePattern"
            :tabs="patternTabs"
            variant="pills"
        />

        <!-- PATTERN 1: MASTER LIST & FILTER -->
        <section v-if="activePattern === 'list-filter'" class="ds-pattern-card">
            <div class="ds-pattern-meta">
                <div class="ds-pattern-meta__left">
                    <div class="ds-pattern-number">01</div>
                    <div>
                        <h2 class="ds-pattern-title">Master Data List & Advanced Filter</h2>
                        <p class="ds-pattern-desc">
                            Pola standar untuk seluruh halaman indeks (e.g. Master Karyawan, Riwayat Pengajuan, Daftar Dokumen SOP).
                            Mengombinasikan <code>HcisHeader</code>, <code>HcisStatGroup</code>, <code>HcisFilterBar</code>, <code>HcisTable</code>, dan <code>HcisPagination</code>.
                        </p>
                    </div>
                </div>
                <div class="ds-pattern-meta__right">
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'preview' }"
                        @click="patternViewMode = 'preview'"
                    >
                        <HcisIcon name="eye" size="xs" /> Live Preview
                    </button>
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'code' }"
                        @click="patternViewMode = 'code'"
                    >
                        <HcisIcon name="code-slash" size="xs" /> Kode Scaffold
                    </button>
                </div>
            </div>

            <!-- Pattern 1: Live Preview -->
            <div v-if="patternViewMode === 'preview'" class="ds-pattern-stage">
                <div class="ds-mockup-frame">
                    <!-- Page Header in Mockup -->
                    <div class="hcis-row hcis-row--between" style="margin-bottom: var(--hcis-space-4);">
                        <div>
                            <h3 class="hcis-title" style="font-size: var(--hcis-font-size-lg);">Master Data Karyawan</h3>
                            <p class="hcis-subtitle" style="font-size: var(--hcis-font-size-xs);">Kelola direktori seluruh pegawai aktif dan riwayat penempatan unit kerja.</p>
                        </div>
                        <HcisButton label="Tambah Karyawan" variant="primary" icon="person-plus" size="sm" />
                    </div>

                    <!-- Stat Row -->
                    <HcisStatGroup style="margin-bottom: var(--hcis-space-4);">
                        <HcisStatCard label="Total Karyawan" value="1,420" caption="seluruh unit kerja" />
                        <HcisStatCard label="Pegawai Aktif" value="1,385" sentiment="good" caption="status aktif bekerja" />
                        <HcisStatCard label="Cuti Hari Ini" value="12" sentiment="neutral" caption="ijin & cuti tahunan" />
                        <HcisStatCard label="Menunggu Otorisasi" value="23" sentiment="bad" caption="berkas mutasi tertunda" />
                    </HcisStatGroup>

                    <!-- Filter Bar -->
                    <HcisFilterBar
                        v-model:search="listSearch"
                        variant="glass"
                        :quick-chips="[
                            { label: 'Semua Status', value: 'all' },
                            { label: 'Aktif', value: 'active' },
                            { label: 'Cuti', value: 'leave' },
                            { label: 'Nonaktif', value: 'inactive' },
                        ]"
                        :active-chip-value="listStatus"
                        search-placeholder="Cari NIK, nama lengkap, divisi…"
                        @chip-click="(v) => listStatus = v"
                    />

                    <!-- Data Table -->
                    <HcisCard style="margin-top: var(--hcis-space-4);">
                        <HcisTable
                            :columns="[
                                { key: 'karyawan', label: 'Karyawan / NIK' },
                                { key: 'unit', label: 'Unit & Jabatan' },
                                { key: 'status', label: 'Status' },
                                { key: 'tmt', label: 'TMT Masuk' },
                                { key: 'actions', label: 'Aksi', align: 'right' },
                            ]"
                            :rows="sampleEmployees"
                            row-key="nik"
                        >
                            <template #cell-karyawan="{ row }">
                                <div style="display: flex; align-items: center; gap: var(--hcis-space-2-5);">
                                    <HcisAvatar :name="row.nama" size="sm" />
                                    <div>
                                        <div style="font-weight: 700; font-size: var(--hcis-font-size-xs); color: var(--hcis-text-strong);">{{ row.nama }}</div>
                                        <div style="font-size: var(--hcis-font-size-2xs); color: var(--hcis-text-muted);">{{ row.nik }}</div>
                                    </div>
                                </div>
                            </template>
                            <template #cell-unit="{ row }">
                                <div style="font-size: var(--hcis-font-size-xs); font-weight: 600;">{{ row.jabatan }}</div>
                                <div style="font-size: var(--hcis-font-size-2xs); color: var(--hcis-text-muted);">{{ row.divisi }}</div>
                            </template>
                            <template #cell-status="{ row }">
                                <HcisBadge
                                    :label="row.status"
                                    :variant="row.status === 'AKTIF' ? 'success' : (row.status === 'CUTI' ? 'warning' : 'neutral')"
                                    size="sm"
                                    dot
                                />
                            </template>
                            <template #cell-tmt="{ row }">
                                <span style="font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">{{ row.tmt }}</span>
                            </template>
                            <template #cell-actions>
                                <div style="display: flex; justify-content: flex-end; gap: var(--hcis-space-1);">
                                    <HcisButton label="Detail" variant="ghost" size="sm" icon="eye" />
                                    <HcisButton variant="ghost" size="sm" icon="three-dots-vertical" aria-label="Opsi lainnya" />
                                </div>
                            </template>
                        </HcisTable>

                        <div style="margin-top: var(--hcis-space-4); display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">Menampilkan 1 - 3 dari 1,420 karyawan</span>
                            <HcisPagination :total="1420" :page-size="10" :model-value="1" />
                        </div>
                    </HcisCard>
                </div>
            </div>

            <!-- Pattern 1: Code Scaffold -->
            <div v-else class="ds-pattern-code-box">
                <div class="ds-code-toolbar">
                    <span>Scaffold: ListPageTemplate.vue</span>
                    <button type="button" class="ds-copy-btn" @click="copyCode(codePattern1)">
                        <HcisIcon name="clipboard" size="xs" /> Salin Template
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ codePattern1 }}</code></pre>
            </div>
        </section>

        <!-- PATTERN 2: MULTI-STEP FORM WIZARD -->
        <section v-else-if="activePattern === 'form-wizard'" class="ds-pattern-card">
            <div class="ds-pattern-meta">
                <div class="ds-pattern-meta__left">
                    <div class="ds-pattern-number">02</div>
                    <div>
                        <h2 class="ds-pattern-title">Formulir Entri Data & Multi-step Wizard</h2>
                        <p class="ds-pattern-desc">
                            Pola formulir multi-langkah untuk entri data kompleks (e.g. Onboarding Karyawan Baru, Pengajuan Mutasi, Pembuatan KPI).
                            Dilengkapi <code>HcisSteps</code>, <code>HcisField</code>, validasi inline, dan Sticky Action Bar di bawah.
                        </p>
                    </div>
                </div>
                <div class="ds-pattern-meta__right">
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'preview' }"
                        @click="patternViewMode = 'preview'"
                    >
                        <HcisIcon name="eye" size="xs" /> Live Preview
                    </button>
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'code' }"
                        @click="patternViewMode = 'code'"
                    >
                        <HcisIcon name="code-slash" size="xs" /> Kode Scaffold
                    </button>
                </div>
            </div>

            <!-- Pattern 2: Live Preview -->
            <div v-if="patternViewMode === 'preview'" class="ds-pattern-stage">
                <div class="ds-mockup-frame">
                    <!-- Stepper -->
                    <div style="margin-bottom: var(--hcis-space-6);">
                        <HcisSteps
                            :active="currentStep"
                            :steps="[
                                { title: 'Data Pribadi', description: 'Identitas KTP & Kontak' },
                                { title: 'Jabatan & Kontrak', description: 'Unit kerja & gaji' },
                                { title: 'Dokumen Lampiran', description: 'Unggah berkas KTP/NPWP' },
                                { title: 'Konfirmasi', description: 'Tinjau & kirim data' },
                            ]"
                        />
                    </div>

                    <!-- Step 1 Form Card -->
                    <HcisCard title="Informasi Identitas Karyawan" description="Lengkapi data diri sesuai kartu identitas resmi." divider>
                        <div class="hcis-grid hcis-grid--2" style="gap: var(--hcis-space-4);">
                            <HcisField label="Nama Lengkap Sesuai KTP" required>
                                <input type="text" class="ds-control-input" value="Raden Aria Pratama" />
                            </HcisField>

                            <HcisField label="Nomor Induk Kependudukan (NIK KTP)" required>
                                <input type="text" class="ds-control-input" value="3271021990080002" />
                            </HcisField>

                            <HcisField label="Alamat Email Perusahaan" required hint="Gunakan domain @company.co.id">
                                <input type="email" class="ds-control-input" value="aria.pratama@company.co.id" />
                            </HcisField>

                            <HcisField label="Nomor Telepon / WhatsApp" required>
                                <input type="tel" class="ds-control-input" value="081234567890" />
                            </HcisField>
                        </div>

                        <div style="margin-top: var(--hcis-space-4);">
                            <HcisCheckbox label="Karyawan telah menandatangani persetujuan pengolahan data pribadi (UU PDP)" :model-value="true" />
                        </div>
                    </HcisCard>

                    <!-- Sticky Action Bar -->
                    <div class="ds-sticky-action-bar">
                        <div class="hcis-row hcis-row--between" style="width: 100%;">
                            <HcisButton label="Kembali" variant="ghost" icon="arrow-left" :disabled="currentStep === 1" @click="currentStep = Math.max(1, currentStep - 1)" />
                            <div style="display: flex; gap: var(--hcis-space-2);">
                                <HcisButton label="Simpan Draf" variant="secondary" icon="save" />
                                <HcisButton label="Lanjutkan ke Langkah Berikutnya" variant="primary" icon="arrow-right" @click="currentStep = Math.min(4, currentStep + 1)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pattern 2: Code Scaffold -->
            <div v-else class="ds-pattern-code-box">
                <div class="ds-code-toolbar">
                    <span>Scaffold: FormWizardTemplate.vue</span>
                    <button type="button" class="ds-copy-btn" @click="copyCode(codePattern2)">
                        <HcisIcon name="clipboard" size="xs" /> Salin Template
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ codePattern2 }}</code></pre>
            </div>
        </section>

        <!-- PATTERN 3: EMPLOYEE DETAIL & 360 VIEW -->
        <section v-else-if="activePattern === 'detail-360'" class="ds-pattern-card">
            <div class="ds-pattern-meta">
                <div class="ds-pattern-meta__left">
                    <div class="ds-pattern-number">03</div>
                    <div>
                        <h2 class="ds-pattern-title">Detail Karyawan & 360 Profile View</h2>
                        <p class="ds-pattern-desc">
                            Pola halaman profil / berkas tunggal yang komprehensif. Menampilkan Hero Header Karyawan dengan aksi cepat, Key Metrics, dan Tabbed Timeline Riwayat Karir.
                        </p>
                    </div>
                </div>
                <div class="ds-pattern-meta__right">
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'preview' }"
                        @click="patternViewMode = 'preview'"
                    >
                        <HcisIcon name="eye" size="xs" /> Live Preview
                    </button>
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'code' }"
                        @click="patternViewMode = 'code'"
                    >
                        <HcisIcon name="code-slash" size="xs" /> Kode Scaffold
                    </button>
                </div>
            </div>

            <!-- Pattern 3: Live Preview -->
            <div v-if="patternViewMode === 'preview'" class="ds-pattern-stage">
                <div class="ds-mockup-frame">
                    <!-- Profile Hero Card -->
                    <HcisCard variant="glass" style="margin-bottom: var(--hcis-space-4);">
                        <div class="hcis-row hcis-row--between" style="flex-wrap: wrap; gap: var(--hcis-space-4);">
                            <div style="display: flex; align-items: center; gap: var(--hcis-space-4);">
                                <HcisAvatar name="Siti Rahmawati" size="xl" />
                                <div>
                                    <div style="display: flex; align-items: center; gap: var(--hcis-space-2);">
                                        <h3 style="margin: 0; font-size: var(--hcis-font-size-lg); font-weight: 800; color: var(--hcis-text-strong);">Siti Rahmawati, S.Kom</h3>
                                        <HcisBadge label="AKTIF" variant="success" size="sm" dot />
                                    </div>
                                    <p style="margin: 2px 0 0; font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">
                                        NIK: <strong>HCIS-2021-0849</strong> · Senior People Operations Specialist · Divisi Human Capital
                                    </p>
                                </div>
                            </div>
                            <div style="display: flex; gap: var(--hcis-space-2);">
                                <HcisButton label="Cetak Biodata" variant="secondary" size="sm" icon="printer" />
                                <HcisButton label="Edit Data" variant="primary" size="sm" icon="pencil" />
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Profile Tabs -->
                    <HcisTabs
                        v-model="profileTab"
                        :tabs="[
                            { key: 'utama', label: 'Ringkasan Utama', icon: 'person' },
                            { key: 'karir', label: 'Riwayat Jabatan & Mutasi', icon: 'clock-history' },
                            { key: 'dokumen', label: 'Dokumen & Kontrak', icon: 'file-earmark-text' },
                        ]"
                        variant="underline"
                        style="margin-bottom: var(--hcis-space-4);"
                    />

                    <!-- Tab Content: Timeline -->
                    <HcisCard title="Linimasa Perjalanan Karir" description="Rekam jejak promosi, rotasi, dan evaluasi berkala.">
                        <HcisTimeline
                            :items="[
                                { title: 'Promosi — Senior People Operations Specialist', time: '01 Januari 2024', description: 'Promosi jabatan grade HC-3 atas pencapaian evaluasi KPI A.' },
                                { title: 'Rotasi — People Operations Specialist', time: '15 Maret 2022', description: 'Mutasi internal dari Talent Acquisition ke People Operations.' },
                                { title: 'Bergabung di Perusahaan (TMT Awal)', time: '01 Agustus 2021', description: 'Memulai masa kerja kontrak PKWT-1 sebagai HR Officer.' },
                            ]"
                        />
                    </HcisCard>
                </div>
            </div>

            <!-- Pattern 3: Code Scaffold -->
            <div v-else class="ds-pattern-code-box">
                <div class="ds-code-toolbar">
                    <span>Scaffold: Detail360Template.vue</span>
                    <button type="button" class="ds-copy-btn" @click="copyCode(codePattern3)">
                        <HcisIcon name="clipboard" size="xs" /> Salin Template
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ codePattern3 }}</code></pre>
            </div>
        </section>

        <!-- PATTERN 4: APPROVAL WORKFLOW DIALOG -->
        <section v-else-if="activePattern === 'approval-modal'" class="ds-pattern-card">
            <div class="ds-pattern-meta">
                <div class="ds-pattern-meta__left">
                    <div class="ds-pattern-number">04</div>
                    <div>
                        <h2 class="ds-pattern-title">Modal Persetujuan & Alur Otorisasi</h2>
                        <p class="ds-pattern-desc">
                            Pola dialog konfirmasi otorisasi multi-level (e.g. Persetujuan Cuti, Otorisasi Lembur, Pengajuan Klaim Medis).
                            Dilengkapi ringkasan pengajuan, histori persetujuan bertingkat, dan aksi Tolak / Setujui.
                        </p>
                    </div>
                </div>
                <div class="ds-pattern-meta__right">
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'preview' }"
                        @click="patternViewMode = 'preview'"
                    >
                        <HcisIcon name="eye" size="xs" /> Live Preview
                    </button>
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'code' }"
                        @click="patternViewMode = 'code'"
                    >
                        <HcisIcon name="code-slash" size="xs" /> Kode Scaffold
                    </button>
                </div>
            </div>

            <!-- Pattern 4: Live Preview -->
            <div v-if="patternViewMode === 'preview'" class="ds-pattern-stage">
                <div class="ds-mockup-frame" style="max-width: 640px; margin: 0 auto;">
                    <HcisCard title="Otorisasi Pengajuan Cuti Tahunan" description="Nomor Pengajuan: CUTI-2026-0819-0012" divider>
                        <div class="hcis-stack hcis-stack--md">
                            <!-- Context Summary -->
                            <HcisContextBanner
                                title="Pengajuan Cuti 3 Hari Kerja"
                                subtitle="Pemohon: Budi Santoso (Engineering) · Sisa Cuti: 9 Hari"
                                variant="surface"
                                icon="calendar-check"
                            />

                            <!-- Approval Stages -->
                            <div>
                                <h4 style="font-size: var(--hcis-font-size-xs); font-weight: 700; color: var(--hcis-text-strong); margin-bottom: var(--hcis-space-2);">
                                    Histori Alur Persetujuan Bertingkat:
                                </h4>
                                <HcisTimeline
                                    :items="[
                                        { title: 'Level 1: Supervisor (Disetujui)', time: '19 Agu 2026, 08:30', description: 'Disetujui oleh Ahmad Hidayat: Pekerjaan sudah di-handover.' },
                                        { title: 'Level 2: Head of Department (Menunggu Anda)', time: 'Sedang Berjalan', description: 'Menunggu otorisasi persetujuan Anda saat ini.' },
                                    ]"
                                />
                            </div>

                            <!-- Comment Field -->
                            <HcisField label="Catatan Otorisator (Opsional)">
                                <textarea class="ds-control-textarea" placeholder="Tambahkan catatan persetujuan atau alasan penolakan…"></textarea>
                            </HcisField>
                        </div>

                        <template #footer>
                            <div class="hcis-row hcis-row--between" style="width: 100%;">
                                <HcisButton label="Minta Revisi" variant="ghost" icon="arrow-counterclockwise" />
                                <div style="display: flex; gap: var(--hcis-space-2);">
                                    <HcisButton label="Tolak Pengajuan" variant="danger" icon="x-lg" />
                                    <HcisButton label="Setujui Pengajuan" variant="primary" icon="check-lg" />
                                </div>
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </div>

            <!-- Pattern 4: Code Scaffold -->
            <div v-else class="ds-pattern-code-box">
                <div class="ds-code-toolbar">
                    <span>Scaffold: ApprovalModalTemplate.vue</span>
                    <button type="button" class="ds-copy-btn" @click="copyCode(codePattern4)">
                        <HcisIcon name="clipboard" size="xs" /> Salin Template
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ codePattern4 }}</code></pre>
            </div>
        </section>

        <!-- PATTERN 5: EXCEL IMPORT & ERROR STUDIO -->
        <section v-else-if="activePattern === 'excel-studio'" class="ds-pattern-card">
            <div class="ds-pattern-meta">
                <div class="ds-pattern-meta__left">
                    <div class="ds-pattern-number">05</div>
                    <div>
                        <h2 class="ds-pattern-title">Batch Excel Import & Validation Studio</h2>
                        <p class="ds-pattern-desc">
                            Pola studio impor data massal (e.g. Impor Absensi, Unggah Penilaian KPI, Update Rekening).
                            Dilengkapi Drag & Drop upload zone, progress bar validasi, dan tabel inspeksi baris error sebelum komit ke database.
                        </p>
                    </div>
                </div>
                <div class="ds-pattern-meta__right">
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'preview' }"
                        @click="patternViewMode = 'preview'"
                    >
                        <HcisIcon name="eye" size="xs" /> Live Preview
                    </button>
                    <button
                        type="button"
                        class="ds-tab-toggle-btn"
                        :class="{ 'is-active': patternViewMode === 'code' }"
                        @click="patternViewMode = 'code'"
                    >
                        <HcisIcon name="code-slash" size="xs" /> Kode Scaffold
                    </button>
                </div>
            </div>

            <!-- Pattern 5: Live Preview -->
            <div v-if="patternViewMode === 'preview'" class="ds-pattern-stage">
                <div class="ds-mockup-frame">
                    <!-- Upload Dropzone -->
                    <HcisCard style="margin-bottom: var(--hcis-space-4); text-align: center; border: 2px dashed var(--hcis-brand-300); background: var(--hcis-brand-50);">
                        <HcisIcon name="cloud-arrow-up" size="xl" style="color: var(--hcis-brand-600); margin-bottom: var(--hcis-space-2);" />
                        <h4 style="margin: 0; font-size: var(--hcis-font-size-md); font-weight: 700; color: var(--hcis-text-strong);">
                            Tarik & Letakkan File Excel (.xlsx, .csv) di Sini
                        </h4>
                        <p style="margin: var(--hcis-space-1) 0 var(--hcis-space-3); font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">
                            Maksimal ukuran file 10MB. Gunakan template resmi per 2026.
                        </p>
                        <div style="display: flex; justify-content: center; gap: var(--hcis-space-2);">
                            <HcisButton label="Unduh Template Excel" variant="secondary" size="sm" icon="download" />
                            <HcisButton label="Pilih File dari Komputer" variant="primary" size="sm" icon="folder2-open" />
                        </div>
                    </HcisCard>

                    <!-- Validation Summary -->
                    <HcisAlert
                        variant="warning"
                        title="Hasil Validasi: Ditemukan 1 Baris Bermasalah"
                        message="48 baris data valid dan siap disimpan, namun 1 baris memiliki format NIK yang salah. Periksa tabel di bawah sebelum melanjutkan."
                        style="margin-bottom: var(--hcis-space-4);"
                    />

                    <!-- Table Review -->
                    <HcisCard title="Pratinjau Data Impor (49 Baris Terbaca)">
                        <HcisTable
                            :columns="[
                                { key: 'baris', label: 'Baris' },
                                { key: 'nik', label: 'NIK Karyawan' },
                                { key: 'nama', label: 'Nama' },
                                { key: 'nilai', label: 'Skor Evaluasi' },
                                { key: 'validasi', label: 'Status Validasi' },
                            ]"
                            :rows="[
                                { baris: 'Baris 2', nik: 'EMP-001', nama: 'Agus Setiawan', nilai: '92.5', validasi: 'VALID' },
                                { baris: 'Baris 3', nik: 'EMP-002', nama: 'Dewi Lestari', nilai: '88.0', validasi: 'VALID' },
                                { baris: 'Baris 4', nik: 'INVALID_NIK', nama: 'Kurniawan', nilai: '75.0', validasi: 'ERROR: Format NIK Tidak Sesuai' },
                            ]"
                            row-key="baris"
                        >
                            <template #cell-validasi="{ row }">
                                <HcisBadge
                                    :label="row.validasi"
                                    :variant="row.validasi === 'VALID' ? 'success' : 'danger'"
                                    size="sm"
                                    dot
                                />
                            </template>
                        </HcisTable>

                        <template #footer>
                            <div class="hcis-row hcis-row--between" style="width: 100%;">
                                <HcisButton label="Batal & Upload Ulang" variant="ghost" />
                                <HcisButton label="Komit 48 Baris Valid ke Database" variant="primary" icon="check2-circle" />
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </div>

            <!-- Pattern 5: Code Scaffold -->
            <div v-else class="ds-pattern-code-box">
                <div class="ds-code-toolbar">
                    <span>Scaffold: BatchImportStudioTemplate.vue</span>
                    <button type="button" class="ds-copy-btn" @click="copyCode(codePattern5)">
                        <HcisIcon name="clipboard" size="xs" /> Salin Template
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ codePattern5 }}</code></pre>
            </div>
        </section>

        <!-- Toast Feedback -->
        <div v-if="toastText" class="ds-toast">
            <HcisIcon name="check-lg" size="sm" />
            <span>{{ toastText }}</span>
        </div>

        <DsMotionBanner />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    HcisHeader,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisStatGroup,
    HcisStatCard,
    HcisFilterBar,
    HcisTable,
    HcisPagination,
    HcisAvatar,
    HcisSteps,
    HcisField,
    HcisCheckbox,
    HcisTabs,
    HcisTimeline,
    HcisContextBanner,
    HcisAlert,
    HcisIcon,
} from '@/components/ui';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';

const activePattern = ref('list-filter');
const patternViewMode = ref('preview'); // preview | code
const toastText = ref('');

const patternTabs = [
    { key: 'list-filter', label: '1. Master List & Filter', icon: 'table' },
    { key: 'form-wizard', label: '2. Multi-step Form Wizard', icon: 'ui-checks' },
    { key: 'detail-360', label: '3. Detail & 360 View', icon: 'person-badge' },
    { key: 'approval-modal', label: '4. Approval Dialog', icon: 'check2-square' },
    { key: 'excel-studio', label: '5. Excel Batch Studio', icon: 'file-earmark-spreadsheet' },
];

// Mock states for interactive demos
const listSearch = ref('');
const listStatus = ref('all');
const currentStep = ref(1);
const profileTab = ref('karir');

const sampleEmployees = [
    { nik: 'HCIS-2024-001', nama: 'Ahmad Fauzi', jabatan: 'Lead Software Engineer', divisi: 'Information Technology', status: 'AKTIF', tmt: '12 Jan 2022' },
    { nik: 'HCIS-2023-045', nama: 'Budi Santoso', jabatan: 'Senior UI/UX Designer', divisi: 'Product Design', status: 'CUTI', tmt: '01 Mar 2023' },
    { nik: 'HCIS-2021-089', nama: 'Citra Amelia', jabatan: 'HR Operations Specialist', divisi: 'Human Capital', status: 'AKTIF', tmt: '15 Agu 2021' },
];

function copyCode(text) {
    navigator.clipboard.writeText(text).then(() => {
        toastText.value = 'Kode template berhasil disalin ke clipboard!';
        setTimeout(() => {
            toastText.value = '';
        }, 2500);
    });
}

// Code templates for copying
const codePattern1 = `<template>
  <div class="hcis-page hcis-stack hcis-stack--md">
    <HcisHeader title="Master Data Karyawan" subtitle="Kelola seluruh data pegawai...">
      <template #actions>
        <HcisButton label="Tambah Karyawan" variant="primary" icon="person-plus" />
      </template>
    </HcisHeader>

    <HcisStatGroup>
      <HcisStatCard label="Total Karyawan" :value="stats.total" />
      <HcisStatCard label="Pegawai Aktif" :value="stats.aktif" sentiment="good" />
      <HcisStatCard label="Cuti Hari Ini" :value="stats.cuti" sentiment="neutral" />
    </HcisStatGroup>

    <HcisFilterBar
      v-model:search="searchQuery"
      variant="glass"
      :quick-chips="statusChips"
      :active-chip-value="activeStatus"
      @chip-click="onStatusFilter"
    />

    <HcisCard>
      <HcisTable :columns="tableColumns" :rows="items" row-key="id">
        <template #cell-status="{ row }">
          <HcisBadge :label="row.status" :variant="getStatusVariant(row.status)" dot />
        </template>
      </HcisTable>

      <HcisPagination v-model="page" :total="totalItems" :page-size="pageSize" />
    </HcisCard>
  </div>
</template>`;

const codePattern2 = `<template>
  <div class="hcis-page hcis-stack hcis-stack--md">
    <HcisSteps :active="currentStep" :steps="wizardSteps" />

    <HcisCard title="Informasi Karyawan" divider>
      <div class="hcis-grid hcis-grid--2">
        <HcisField label="Nama Lengkap" required :error="errors.nama">
          <input v-model="form.nama" type="text" class="hcis-input" />
        </HcisField>
        <HcisField label="NIK KTP" required :error="errors.nik">
          <input v-model="form.nik" type="text" class="hcis-input" />
        </HcisField>
      </div>
    </HcisCard>

    <div class="ds-sticky-action-bar">
      <HcisButton label="Kembali" variant="ghost" @click="prevStep" />
      <HcisButton label="Lanjutkan" variant="primary" @click="nextStep" />
    </div>
  </div>
</template>`;

const codePattern3 = `<template>
  <div class="hcis-page hcis-stack hcis-stack--md">
    <HcisCard variant="glass">
      <div class="hcis-row hcis-row--between">
        <HcisAvatar :name="employee.name" size="xl" />
        <HcisButton label="Edit Profil" variant="primary" icon="pencil" />
      </div>
    </HcisCard>

    <HcisTabs v-model="activeTab" :tabs="profileTabs" variant="underline" />

    <HcisCard title="Riwayat Jabatan">
      <HcisTimeline :items="careerHistory" />
    </HcisCard>
  </div>
</template>`;

const codePattern4 = `<template>
  <HcisDialog v-model="isOpen" title="Otorisasi Pengajuan" width="600px">
    <HcisContextBanner title="Pengajuan Cuti Tahunan" subtitle="3 Hari Kerja" />
    <HcisTimeline :items="approvalSteps" />
    <HcisField label="Catatan Otorisator">
      <textarea v-model="notes" class="hcis-textarea"></textarea>
    </HcisField>
    <template #footer>
      <HcisButton label="Tolak" variant="danger" @click="reject" />
      <HcisButton label="Setujui" variant="primary" @click="approve" />
    </template>
  </HcisDialog>
</template>`;

const codePattern5 = `<template>
  <div class="hcis-page hcis-stack hcis-stack--md">
    <HcisCard style="border: 2px dashed var(--hcis-brand-300);">
      <HcisUpload @change="handleFileUpload" />
    </HcisCard>
    <HcisAlert v-if="hasErrors" variant="warning" title="Validasi Error Ditemukan" />
    <HcisCard title="Pratinjau Data">
      <HcisTable :columns="columns" :rows="parsedRows" />
      <template #footer>
        <HcisButton label="Komit Data" variant="primary" :loading="isSaving" @click="commit" />
      </template>
    </HcisCard>
  </div>
</template>`;
</script>

<style scoped>
.ds-archetypes-root {
    padding-bottom: var(--hcis-space-12);
}

.ds-pattern-stats {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.ds-pattern-card {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    overflow: hidden;
    box-shadow: 0 4px 20px -2px rgb(var(--hcis-shadow-rgb) / 0.05);
}

.ds-pattern-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-5) var(--hcis-space-6);
    background: linear-gradient(180deg, var(--hcis-surface-soft) 0%, var(--hcis-surface) 100%);
    border-bottom: 1px solid var(--hcis-border);
    flex-wrap: wrap;
}

.ds-pattern-meta__left {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-4);
}

.ds-pattern-number {
    font-size: 1.75rem;
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-brand-600);
    line-height: 1;
    font-family: var(--hcis-font-mono);
}

.ds-pattern-title {
    margin: 0;
    font-size: var(--hcis-font-size-md);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
}

.ds-pattern-desc {
    margin: 4px 0 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
    max-width: 680px;
    line-height: 1.5;
}

.ds-pattern-meta__right {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--hcis-surface-soft);
    padding: 3px;
    border-radius: var(--hcis-radius-lg);
    border: 1px solid var(--hcis-border);
}

.ds-tab-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    padding: var(--hcis-space-2) var(--hcis-space-3-5);
    border-radius: var(--hcis-radius-md);
    border: none;
    background: transparent;
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-tab-toggle-btn.is-active {
    background: var(--hcis-surface);
    color: var(--hcis-brand-700);
    box-shadow: 0 1px 3px rgb(var(--hcis-black-rgb) / 0.08);
}

.ds-pattern-stage {
    padding: var(--hcis-space-6);
    background: var(--hcis-surface-soft);
}

.ds-mockup-frame {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    padding: var(--hcis-space-6);
    box-shadow: 0 10px 30px -5px rgb(var(--hcis-black-rgb) / 0.05);
}

.ds-control-input,
.ds-control-textarea {
    width: 100%;
    min-height: 2.375rem;
    padding: var(--hcis-space-2) var(--hcis-space-3);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-strong);
    background-color: var(--hcis-surface);
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-xs);
    box-shadow: 0 1px 2px 0 rgb(var(--hcis-slate-900-rgb) / 0.05), inset 0 1px 1.5px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
    outline: none;
    transition: border-color 200ms ease, box-shadow 200ms ease;
}

.ds-control-input:hover,
.ds-control-textarea:hover {
    border-color: var(--hcis-brand-400);
    box-shadow: 0 1px 3px 0 rgb(var(--hcis-brand-600-rgb) / 0.08), inset 0 1px 1.5px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-control-input:focus,
.ds-control-textarea:focus {
    border-color: var(--hcis-brand-600);
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb) / 0.1);
}

.ds-control-textarea {
    min-height: 80px;
    resize: vertical;
}

.ds-sticky-action-bar {
    margin-top: var(--hcis-space-6);
    padding-top: var(--hcis-space-4);
    border-top: 1px solid var(--hcis-border);
    background: var(--hcis-surface);
}

.ds-pattern-code-box {
    background: var(--hcis-slate-900);
}

.ds-code-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--hcis-space-3) var(--hcis-space-6);
    border-bottom: 1px solid var(--hcis-slate-800);
    color: var(--hcis-slate-400);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
}

.ds-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    background: var(--hcis-slate-800);
    border: 1px solid var(--hcis-slate-700);
    color: var(--hcis-slate-200);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    padding: 4px 10px;
    border-radius: var(--hcis-radius-md);
    cursor: pointer;
}

.ds-copy-btn:hover {
    background: var(--hcis-brand-600);
    color: var(--hcis-white);
}

.ds-code-block {
    margin: 0;
    padding: var(--hcis-space-4) var(--hcis-space-6);
    overflow-x: auto;
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-sky-400);
    line-height: var(--hcis-line-relaxed);
}

.ds-toast {
    position: fixed;
    bottom: var(--hcis-space-6);
    right: var(--hcis-space-6);
    z-index: 999;
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-3) var(--hcis-space-5);
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    border-radius: var(--hcis-radius-lg);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
}
</style>
