<template>
    <div class="container-fluid px-0 sop-scope sop-kebijakan-page">
        <!-- ══ 1. EXECUTIVE HEADER (HCIS SIGNATURE LUMINOUS HEADER) ══ -->
        <HcisHeader
            title="Otomasi Dokumen"
            kicker="Tata Kelola · Otomasi Penugasan"
            description="Atur cakupan penugasan, tenggat waktu, dan pengingat digest, lalu telusuri riwayat eksekusinya."
            icon="shield-check"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="!loading.rules"
                    :label="`${summary.active_rules || 0} Aturan Aktif`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="/sop/repository" class="text-decoration-none">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="journal-bookmark-fill"
                            label="Perpustakaan"
                            title="Buka Public Document"
                        />
                    </a>
                    <a href="/sop/saya" class="text-decoration-none">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="clipboard2-check-fill"
                            label="Functional Document"
                            title="Buka Halaman Functional Document"
                        />
                    </a>
                    <a href="/sop/admin/setup-system" class="text-decoration-none">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="sliders"
                            label="Kebijakan Global"
                            title="Buka Set Up System — kebijakan global & otomasi terjadwal"
                        />
                    </a>
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        label="Segarkan"
                        :loading="busy"
                        title="Segarkan aturan penugasan & riwayat eksekusi"
                        aria-label="Segarkan aturan penugasan dan riwayat eksekusi"
                        @click="loadAll"
                    />
                </div>
            </template>

            <template #tabs>
                <HcisTabs
                    v-model="activeTab"
                    :tabs="tabItems"
                    variant="header"
                    size="md"
                    aria-label="Navigasi Bagian Kebijakan"
                />
            </template>
        </HcisHeader>

        <!-- ══ 2. EXECUTIVE TELEMETRY KPI STATS ══ -->
        <SopKebijakanStats
            :summary="summary"
            :rules-count="rules.length"
            :runs-count="runs.length"
            :loading="loading.rules"
            class="mb-4"
            @filter-attention="onStatFilterAttention"
        />

        <!-- ══ 3. MAIN WORKSPACE CONTAINER ══ -->
        <main class="sop-kebijakan-main mb-5">
            <!-- ── TAB 1: ATURAN PENUGASAN (MASTER-DETAIL DENGAN PANEL KANAN RAPI) ── -->
            <section v-if="activeTab === 'rules'" class="sop-rules-workspace">
                <div class="row g-4">
                    <!-- ── KOLOM KIRI: DAFTAR ATURAN PENUGASAN ── -->
                    <div class="col-12 col-lg-4 col-xl-4">
                        <HcisCard
                            variant="glass"
                            sentiment="brand"
                            size="md"
                            class="sop-rule-sidebar-card h-100"
                        >
                            <template #header>
                                <div class="d-flex justify-content-between align-items-center w-100">
                                    <div class="d-flex align-items-center gap-2">
                                        <h2 class="hcis-card__title">Daftar Aturan</h2>
                                        <HcisBadge :label="`${filteredRules.length}`" variant="neutral" size="xs" />
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <!-- Scheduler harian tetap ada di Set Up System; tombol ini
                                             untuk menjalankan evaluasi segera setelah aturan diubah. -->
                                        <HcisButton
                                            variant="glass"
                                            size="xs"
                                            leading-icon="play-fill"
                                            label="Jalankan Evaluasi"
                                            title="Jalankan evaluasi aturan sekarang tanpa menunggu jadwal harian"
                                            :loading="busy"
                                            @click="runNow('rule-scan')"
                                        />
                                        <HcisButton
                                            variant="primary"
                                            size="xs"
                                            leading-icon="plus-lg"
                                            label="Aturan Baru"
                                            title="Rancang Aturan Baru"
                                            @click="newRule"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Bilah Pencarian & Filter Cepat Resmi HcisFilterBar -->
                            <HcisFilterBar
                                :search="searchRule"
                                search-placeholder="Cari nama aturan atau paket… (/)"
                                :quick-chips="ruleQuickChips"
                                :active-chip-value="statusFilter"
                                variant="flat"
                                sentiment="brand"
                                size="sm"
                                class="mb-3"
                                @update:search="searchRule = $event"
                                @chip-click="onRuleChipClick"
                                @reset="resetRuleFilters"
                            />

                            <!-- List Skeleton & Content -->
                            <div v-if="loading.rules" class="d-flex flex-column gap-2 py-2">
                                <HcisSkeleton v-for="n in 4" :key="n" width="100%" height="64px" rounded="md" />
                            </div>

                            <div v-else-if="filteredRules.length" class="sop-rule-rows d-flex flex-column gap-2">
                                <div
                                    v-for="rule in filteredRules"
                                    :key="rule.Id_SOP_Assignment_Rule"
                                    class="sop-rule-item"
                                    :class="[
                                        `status-${statusClass(rule)}`,
                                        { 'is-selected': selectedId === rule.Id_SOP_Assignment_Rule },
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    @click="openRule(rule.Id_SOP_Assignment_Rule)"
                                    @keydown.enter="openRule(rule.Id_SOP_Assignment_Rule)"
                                >
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <span class="sop-status-dot" :class="statusClass(rule)"></span>
                                            <strong class="text-truncate text-slate-900 font-bold" style="font-size: 0.875rem;">
                                                {{ rule.Nama_Aturan }}
                                            </strong>
                                        </div>
                                        <HcisBadge
                                            :label="`${rule.Jumlah_Paket || 0} paket`"
                                            variant="brand"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                                        <!-- Nama paketnya muncul lengkap di panel kanan; di sini
                                             hanya cuplikan, karena satu aturan kini bisa memuat
                                             lima nama sekaligus. -->
                                        <span
                                            class="text-truncate text-slate-500 text-xs d-flex align-items-center gap-1"
                                            :title="namaPaketRule(rule)"
                                        >
                                            <HcisIcon name="box-seam" size="xs" class="text-slate-400" />
                                            {{ namaPaketRule(rule) || '—' }}
                                        </span>
                                        <span class="text-xs font-semibold" :class="statusTextColor(rule)">
                                            {{ statusLabel(rule) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <HcisEmptyState
                                v-else
                                title="Tidak Ada Aturan Sesuai"
                                description="Ubah kata kunci pencarian atau buat aturan penugasan baru."
                                icon="diagram-3"
                                size="sm"
                            >
                                <template #actions>
                                    <HcisButton
                                        v-if="searchRule || statusFilter !== 'ALL'"
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="arrow-clockwise"
                                        label="Reset Filter"
                                        @click="resetRuleFilters"
                                    />
                                </template>
                            </HcisEmptyState>
                        </HcisCard>
                    </div>

                    <!-- ── KOLOM KANAN: PANEL EDITOR RAPI & TERSTRUKTUR ── -->
                    <div class="col-12 col-lg-8 col-xl-8">
                        <HcisCard
                            variant="glass"
                            sentiment="brand"
                            size="lg"
                            :title="form.nama_aturan || 'Rancang Aturan Baru'"
                            description="Tentukan kriteria target unit kerja, level jabatan, pengecualian NIK, dan batas waktu penyelesaian dokumen."
                            icon="diagram-3"
                            class="sop-rule-editor-card h-100"
                        >
                            <template #badge>
                                <div v-if="selectedRule" class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <HcisBadge
                                        :label="statusLabel(selectedRule)"
                                        :variant="statusBadgeVariant(selectedRule)"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-if="selectedRule.Draft_Berubah"
                                        label="Draft berubah"
                                        variant="warning"
                                        size="xs"
                                    />
                                </div>
                                <HcisBadge
                                    v-else
                                    label="Draft Baru"
                                    variant="brand"
                                    size="xs"
                                />
                            </template>

                            <!-- Alert Error Penerapan Terakhir (Jika Ada) -->
                            <HcisAlert
                                v-if="selectedRule?.Last_Apply_Error"
                                variant="danger"
                                title="Error Penerapan Terakhir"
                                :message="selectedRule.Last_Apply_Error"
                                icon="exclamation-octagon-fill"
                                class="mb-4"
                            />

                            <!-- ── SEKSI 1: NAMA ATURAN & DAFTAR PAKET DOKUMEN ── -->
                            <div class="sop-section-block mb-4 pb-4 border-bottom">
                                <div class="row g-3 mb-4">
                                    <div class="col-12">
                                        <HcisField
                                            id="sop-nama-aturan"
                                            label="Nama Aturan Penugasan"
                                            required
                                            hint="Beri nama deskriptif (maks 150 karakter)"
                                        >
                                            <input
                                                id="sop-nama-aturan"
                                                v-model.trim="form.nama_aturan"
                                                type="text"
                                                class="hcis-field__control"
                                                maxlength="150"
                                                placeholder="Contoh: SOP Wajib Finance HO"
                                            />
                                        </HcisField>
                                    </div>

                                </div>

                                <!-- Daftarnya TETAP bisa disunting setelah aturan aktif:
                                     membuang paket hanya menghentikan penugasan baru,
                                     penugasan yang sudah tercatat dibiarkan utuh. Kunci
                                     :disabled="!!selectedId" yang dulu menempel pada
                                     dropdown paket sengaja dicabut. -->
                                <SopAturanPaketList v-model="form.paket" :options="availablePackages" />
                            </div>

                            <!-- ── SEKSI 2: KRITERIA TARGET KARYAWAN (COHORT) ── -->
                            <div class="sop-section-block mb-4 pb-4 border-bottom">
                                <SopKriteriaGrup
                                    v-model="form.groups"
                                    :options="options.filters"
                                    judul="2. Kriteria Target Karyawan (Cohort)"
                                    keterangan="Karyawan cocok jika memenuhi SEMUA kriteria di dalam satu grup (AND), atau cocok di SALAH SATU grup (OR)"
                                    label-grup="Grup Target"
                                    label-tambah="Tambah Grup (OR)"
                                    judul-kosong="Belum Ada Grup Target"
                                    keterangan-kosong="Aturan wajib memiliki minimal satu grup target."
                                    sentiment="neutral"
                                    :minimal="1"
                                    id-prefix="sop-grup"
                                />
                            </div>

                            <!-- ── SEKSI 3: PENGECUALIAN ── -->
                            <div class="sop-section-block mb-4 pb-4 border-bottom">
                                <div class="mb-3">
                                    <h6 class="text-xs uppercase font-extrabold text-brand-700 tracking-wider mb-1">
                                        3. Pengecualian &amp; Inklusi Khusus
                                    </h6>
                                    <small class="text-slate-500">
                                        Pengecualian selalu menang atas kriteria target — berlaku untuk kriteria
                                        maupun NIK.
                                    </small>
                                </div>

                                <!-- Pengecualian berbasis kriteria. Sebelumnya satu-satunya
                                     cara mengecualikan adalah mengetik NIK satu per satu,
                                     sehingga "seluruh Finance kecuali level Manager" harus
                                     dibongkar jadi daftar nama yang langsung basi begitu ada
                                     yang naik jabatan. -->
                                <SopKriteriaGrup
                                    v-model="form.exclude_groups"
                                    :options="options.filters"
                                    judul="3a. Kecualikan Berdasarkan Kriteria"
                                    keterangan="Dikecualikan jika memenuhi SEMUA kriteria di dalam satu grup (AND), atau cocok di SALAH SATU grup (OR)"
                                    label-grup="Grup Pengecualian"
                                    label-tambah="Tambah Grup Pengecualian (OR)"
                                    judul-kosong="Tidak Ada Pengecualian Berbasis Kriteria"
                                    keterangan-kosong="Tambahkan grup bila ingin mengecualikan satu divisi, departemen, level, jabatan, atau lokasi sekaligus."
                                    sentiment="danger"
                                    :minimal="0"
                                    id-prefix="sop-grup-kecuali"
                                    class="mb-4"
                                />

                                <div class="mb-3">
                                    <h6 class="text-xs uppercase font-extrabold text-brand-700 tracking-wider mb-1">
                                        3b. Perorangan
                                    </h6>
                                    <small class="text-slate-500">
                                        Untuk orang tertentu yang tak terwakili kriteria mana pun. Cari dengan
                                        mengetik nama atau NIK.
                                    </small>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <HcisField
                                            id="sop-include-karyawan"
                                            label="Selalu Sertakan"
                                            hint="Karyawan ini akan selalu ditugaskan dokumen, apa pun kriterianya"
                                        >
                                            <template #corner>
                                                <HcisBadge
                                                    v-if="includeCount > 0"
                                                    :label="`${includeCount} Orang`"
                                                    variant="success"
                                                    size="xs"
                                                />
                                            </template>
                                            <HcisSelect
                                                v-model="form.include_karyawan"
                                                multiple
                                                collapse-tags
                                                :max-collapse-tags="2"
                                                clearable
                                                filterable
                                                placeholder="Cari nama atau NIK…"
                                            >
                                                <HcisSelectOption
                                                    v-for="orang in opsiOrang(form.include_karyawan)"
                                                    :key="orang.value"
                                                    :value="orang.value"
                                                    :label="orang.label"
                                                />
                                            </HcisSelect>
                                        </HcisField>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <HcisField
                                            id="sop-exclude-karyawan"
                                            label="Selalu Kecualikan"
                                            hint="Karyawan ini tidak akan pernah ditugaskan dokumen oleh aturan ini"
                                        >
                                            <template #corner>
                                                <HcisBadge
                                                    v-if="excludeCount > 0"
                                                    :label="`${excludeCount} Orang`"
                                                    variant="danger"
                                                    size="xs"
                                                />
                                            </template>
                                            <HcisSelect
                                                v-model="form.exclude_karyawan"
                                                multiple
                                                collapse-tags
                                                :max-collapse-tags="2"
                                                clearable
                                                filterable
                                                placeholder="Cari nama atau NIK…"
                                            >
                                                <HcisSelectOption
                                                    v-for="orang in opsiOrang(form.exclude_karyawan)"
                                                    :key="orang.value"
                                                    :value="orang.value"
                                                    :label="orang.label"
                                                />
                                            </HcisSelect>
                                        </HcisField>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SEKSI 4: PROFIL TENGGAT WAKTU & REMINDER ── -->
                            <div class="sop-section-block mb-4 pb-4 border-bottom">
                                <div class="mb-3">
                                    <h6 class="text-xs uppercase font-extrabold text-brand-700 tracking-wider mb-1">
                                        4. Tenggat &amp; Pengingat Induk
                                    </h6>
                                    <small class="text-slate-500">
                                        Berlaku untuk <strong>semua paket</strong> di atas, kecuali paket yang
                                        menyalakan &ldquo;tenggat sendiri&rdquo;.
                                    </small>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-sm-6 col-md-4">
                                        <HcisField id="sop-deadline-mode" label="Mode Tenggat Waktu">
                                            <HcisSelect v-model="form.schedule.deadline_mode" placeholder="Pilih Mode">
                                                <HcisSelectOption label="Tanpa tenggat waktu" value="NONE" />
                                                <HcisSelectOption label="Relatif dari waktu penugasan" value="RELATIVE" />
                                                <HcisSelectOption label="Tanggal kalender absolut" value="ABSOLUTE" />
                                            </HcisSelect>
                                        </HcisField>
                                    </div>

                                    <div
                                        v-if="form.schedule.deadline_mode === 'RELATIVE'"
                                        class="col-12 col-sm-6 col-md-4"
                                    >
                                        <HcisField id="sop-deadline-days" label="Hari Setelah Penugasan" hint="Batas waktu penyelesaian">
                                            <HcisInputNumber
                                                v-model="form.schedule.deadline_days"
                                                :min="1"
                                                :max="3650"
                                                suffix="hari"
                                            />
                                        </HcisField>
                                    </div>

                                    <div
                                        v-if="form.schedule.deadline_mode === 'ABSOLUTE'"
                                        class="col-12 col-sm-6 col-md-4"
                                    >
                                        <HcisField id="sop-deadline-date" label="Tanggal Tenggat Absolut" hint="Batas akhir serentak">
                                            <HcisDatePicker
                                                v-model="form.schedule.deadline_date"
                                                placeholder="Pilih tanggal tenggat"
                                            />
                                        </HcisField>
                                    </div>

                                    <div class="col-12 col-sm-6 col-md-4">
                                        <HcisField id="sop-reminder-toggle" label="Digest Reminder Berkala" hint="Kirim digest notifikasi">
                                            <div
                                                class="d-flex align-items-center justify-content-between p-2 rounded border bg-white"
                                                style="min-height: 42px;"
                                            >
                                                <span class="text-xs text-slate-700 font-semibold">
                                                    {{ form.schedule.reminder_enabled ? 'Pengingat Aktif' : 'Nonaktif' }}
                                                </span>
                                                <HcisSwitch v-model="form.schedule.reminder_enabled" />
                                            </div>
                                        </HcisField>
                                    </div>

                                    <div
                                        v-if="form.schedule.reminder_enabled"
                                        class="col-12 col-sm-6 col-md-4"
                                    >
                                        <HcisField id="sop-reminder-interval" label="Interval Pengiriman" hint="Frekuensi pengingat berkala">
                                            <HcisInputNumber
                                                v-model="form.schedule.reminder_interval_days"
                                                :min="1"
                                                :max="365"
                                                suffix="hari"
                                            />
                                        </HcisField>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SEKSI 5: PRATINJAU DAMPAK, DIRINCI PER PAKET ── -->
                            <SopAturanPratinjau v-if="preview" :preview="preview" />

                            <!-- ── SEKSI 6: ACTION FOOTER TOOLBAR ── -->
                            <template #footer>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                                    <div>
                                        <HcisButton
                                            v-if="selectedRule?.Status_Aturan === 'ACTIVE'"
                                            variant="danger"
                                            size="md"
                                            leading-icon="pause-circle"
                                            label="Nonaktifkan Aturan"
                                            :loading="busy"
                                            @click="deactivate"
                                        />
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                                        <HcisButton
                                            variant="secondary"
                                            size="md"
                                            leading-icon="floppy"
                                            label="Simpan Draft"
                                            :loading="busy"
                                            @click="saveDraft"
                                        />
                                        <HcisButton
                                            variant="glass"
                                            size="md"
                                            leading-icon="eye"
                                            label="Hitung Dampak"
                                            :disabled="!selectedId || busy"
                                            :loading="busy"
                                            @click="previewRule"
                                        />
                                        <HcisButton
                                            variant="primary"
                                            size="md"
                                            leading-icon="lightning-charge-fill"
                                            label="Aktifkan &amp; Terapkan"
                                            :disabled="!selectedId || !preview || busy"
                                            :loading="busy"
                                            @click="activate"
                                        />
                                    </div>
                                </div>
                            </template>
                        </HcisCard>
                    </div>
                </div>
            </section>

            <!-- ── TAB 2: RIWAYAT EKSEKUSI (AUDIT LOG) ── -->
            <section v-else>
                <HcisCard
                    variant="glass"
                    sentiment="brand"
                    size="lg"
                    title="Riwayat Eksekusi Otomasi"
                    description="Catatan jejak audit eksekusi background jobs evaluasi aturan penugasan dan pengiriman digest reminder."
                    icon="list-check"
                >
                    <template #badge>
                        <HcisBadge :label="`${runs.length} Catatan`" variant="neutral" size="xs" />
                    </template>
                    <template #actions>
                        <HcisButton
                            variant="glass"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            label="Segarkan Riwayat"
                            :loading="busy || loading.runs"
                            @click="loadRuns"
                        />
                    </template>

                    <!-- Bilah Filter & Pencarian Resmi HcisFilterBar -->
                    <HcisFilterBar
                        :search="searchRun"
                        search-placeholder="Cari nama paket dokumen, jenis proses, atau pesan error…"
                        :quick-chips="runQuickChips"
                        :active-chip-value="statusRunFilter"
                        variant="flat"
                        sentiment="brand"
                        size="sm"
                        class="mb-3"
                        @update:search="searchRun = $event"
                        @chip-click="onRunChipClick"
                        @reset="resetRunFilters"
                    />

                    <!-- Tabel Data Menggunakan HcisTable -->
                    <HcisTable
                        v-if="filteredRuns.length || loading.runs"
                        :columns="runColumns"
                        :rows="filteredRuns"
                        row-key="Id_SOP_Automation_Run"
                        :loading="loading.runs"
                        variant="card"
                        sentiment="brand"
                        size="md"
                        hoverable
                        header-gradient
                    >
                        <!-- Cell Waktu -->
                        <template #cell-Queued_At="{ value }">
                            <div class="d-flex align-items-center gap-1.5 text-slate-700 font-medium">
                                <HcisIcon name="clock" size="xs" class="text-slate-400" />
                                <span>{{ formatDate(value) }}</span>
                            </div>
                        </template>

                        <!-- Cell Jenis Proses -->
                        <template #cell-Tipe_Run="{ value }">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sop-section-icon-dot bg-brand-50 text-brand-600">
                                    <HcisIcon :name="runTypeIcon(value)" size="xs" />
                                </div>
                                <div>
                                    <strong class="d-block text-slate-900 text-sm font-semibold">
                                        {{ runLabel(value) }}
                                    </strong>
                                    <small class="text-slate-400 font-mono text-xs">{{ value }}</small>
                                </div>
                            </div>
                        </template>

                        <!-- Cell Pemicu -->
                        <template #cell-Trigger_Run="{ value }">
                            <HcisBadge
                                :label="triggerLabel(value)"
                                :variant="triggerVariant(value)"
                                size="xs"
                            />
                        </template>

                        <!-- Cell Status -->
                        <template #cell-Status_Run="{ value }">
                            <HcisBadge
                                :label="value"
                                :variant="runStatusVariant(value)"
                                size="xs"
                                dot
                            />
                        </template>

                        <!-- Cell Hasil Pemrosesan -->
                        <template #cell-hasil="{ row }">
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-2 py-0.5 rounded-pill">
                                        +{{ row.Total_Ditambahkan || 0 }} baru
                                    </span>
                                    <span class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200/60 px-2 py-0.5 rounded-pill">
                                        -{{ row.Total_Dicabut || 0 }} dicabut
                                    </span>
                                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-pill">
                                        {{ row.Total_Dilewati || 0 }} dilewati
                                    </span>
                                </div>
                                <small v-if="row.Error_Message" class="text-danger d-block mt-1 font-mono text-xs bg-rose-50 p-1.5 rounded border border-rose-200">
                                    {{ row.Error_Message }}
                                </small>
                            </div>
                        </template>

                        <!-- Cell Paket Dokumen. Baris anak membawa paketnya sendiri;
                             baris induk sengaja tidak, karena ia mewakili seluruh
                             paket aturan itu sekaligus. -->
                        <template #cell-Nama_Paket="{ value, row }">
                            <div
                                v-if="value"
                                class="d-flex align-items-center gap-1.5 text-slate-700 font-medium text-sm"
                                :class="{ 'ps-4': row.Is_Anak }"
                            >
                                <HcisIcon name="box-seam" size="xs" class="text-brand-500" />
                                <span>{{ value }}</span>
                            </div>
                            <span v-else-if="row.Nama_Aturan" class="text-slate-500 text-xs">
                                Seluruh paket aturan &ldquo;{{ row.Nama_Aturan }}&rdquo;
                            </span>
                            <span v-else class="text-slate-400 text-xs italic">Semua / Global</span>
                        </template>
                    </HcisTable>

                    <!-- Plafon riwayat tidak boleh memotong diam-diam: jejak audit
                         yang terpotong terbaca persis seperti jejak yang lengkap. -->
                    <div v-if="runsTotal > runsDitampilkan" class="mt-2 text-end">
                        <small class="text-slate-500">
                            Menampilkan {{ runsDitampilkan }} eksekusi terakhir dari {{ runsTotal }}.
                        </small>
                    </div>

                    <!-- Empty State. Berdiri sendiri, bukan v-else, karena bilah
                         pemotongan di atas menyela rantainya. -->
                    <HcisEmptyState
                        v-if="!filteredRuns.length && !loading.runs"
                        title="Tidak Ada Catatan Eksekusi Sesuai"
                        :description="searchRun || statusRunFilter !== 'ALL' ? 'Coba sesuaikan kata kunci pencarian atau filter status.' : 'Riwayat eksekusi otomasi akan tercatat otomatis saat background scheduler atau pemrosesan manual dijalankan.'"
                        icon="list-check"
                        size="md"
                    >
                        <template #actions>
                            <HcisButton
                                v-if="searchRun || statusRunFilter !== 'ALL'"
                                variant="secondary"
                                size="xs"
                                leading-icon="arrow-clockwise"
                                label="Reset Filter"
                                @click="resetRunFilters"
                            />
                        </template>
                    </HcisEmptyState>
                </HcisCard>
            </section>
        </main>
    </div>
</template>

<script>
import axios from 'axios';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';
import SopAturanPaketList from './Components/SopAturanPaketList.vue';
import SopKriteriaGrup, { blankGroup } from './Components/SopKriteriaGrup.vue';
import SopAturanPratinjau from './Components/SopAturanPratinjau.vue';
import SopKebijakanStats from './Components/SopKebijakanStats.vue';
import {
    HcisHeader,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisTabs,
    HcisTable,
    HcisFilterBar,
    HcisField,
    HcisSelect,
    HcisSelectOption,
    HcisInputNumber,
    HcisSwitch,
    HcisDatePicker,
    HcisAlert,
    HcisEmptyState,
    HcisIcon,
    HcisSkeleton,
    hcisMessage,
    hcisConfirm,
} from '@/components/ui';

const API = '/api/v1/sop/admin/kebijakan';
const blankForm = () => ({
    paket: [],
    nama_aturan: '',
    groups: [blankGroup()],
    exclude_groups: [],
    include_karyawan: [],
    exclude_karyawan: [],
    schedule: {
        deadline_mode: 'NONE',
        deadline_days: 7,
        deadline_date: null,
        reminder_enabled: false,
        reminder_interval_days: 3,
    },
});

export default {
    name: 'SopKebijakan',
    components: {
        SopKebijakanStats,
        SopAturanPaketList,
        SopAturanPratinjau,
        SopKriteriaGrup,
        HcisHeader,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisTabs,
        HcisTable,
        HcisFilterBar,
        HcisField,
        HcisSelect,
        HcisSelectOption,
        HcisInputNumber,
        HcisSwitch,
        HcisDatePicker,
        HcisAlert,
        HcisEmptyState,
        HcisIcon,
        HcisSkeleton,
    },
    mixins: [pageMeta],
    data() {
        return {
            activeTab: 'rules',
            busy: false,
            searchRule: '',
            statusFilter: 'ALL',
            searchRun: '',
            statusRunFilter: 'ALL',
            tipeRunFilter: 'ALL',
            selectedId: null,
            selectedRule: null,
            preview: null,
            summary: {
                total_rules: 0,
                active_rules: 0,
                attention_rules: 0,
                active_runs: 0,
            },
            options: { packages: [], filters: {} },
            rules: [],
            runs: [],
            // Plafon riwayat tidak boleh memotong diam-diam; kaki tabel
            // menyebut kedua angka ini.
            runsDitampilkan: 0,
            runsTotal: 0,
            form: blankForm(),
            loading: {
                rules: false,
                runs: false,
                all: false,
            },
        };
    },
    computed: {
        tabItems() {
            return [
                { key: 'rules', label: 'Aturan Penugasan', icon: 'diagram-3', badge: this.rules.length || undefined, badgeVariant: 'neutral' },
                { key: 'history', label: 'Riwayat Eksekusi', icon: 'list-check', badge: this.runs.length || undefined, badgeVariant: 'neutral' },
            ];
        },
        runColumns() {
            return [
                { key: 'Queued_At', label: 'Waktu Eksekusi', width: '180px', sortable: true },
                { key: 'Tipe_Run', label: 'Jenis Proses', width: '200px', sortable: true },
                { key: 'Trigger_Run', label: 'Pemicu', width: '120px' },
                { key: 'Status_Run', label: 'Status', width: '120px', sortable: true },
                { key: 'hasil', label: 'Hasil Pemrosesan', width: '260px' },
                { key: 'Nama_Paket', label: 'Paket Dokumen', width: '200px' },
            ];
        },
        availablePackages() {
            // Dua saringan: paket yang sudah diklaim aturan LAIN, dan paket yang
            // sudah ada di daftar aturan ini. Yang pertama memang ditolak server
            // juga, tapi menawarkannya lalu menolaknya membuat admin menebak.
            const dipakaiLain = new Set(
                this.rules
                    .filter((r) => r.Id_SOP_Assignment_Rule !== this.selectedId)
                    .flatMap((r) => (r.Paket || []).map((p) => p.Id_SOP_Paket))
            );
            const sudahDiForm = new Set(this.form.paket.map((p) => p.Id_SOP_Paket));

            return (this.options.packages || []).filter(
                (pkg) => !dipakaiLain.has(pkg.Id_SOP_Paket) && !sudahDiForm.has(pkg.Id_SOP_Paket)
            );
        },
        includeCount() {
            return this.form.include_karyawan.length;
        },
        excludeCount() {
            return this.form.exclude_karyawan.length;
        },
        filteredRules() {
            let list = this.rules;
            if (this.statusFilter === 'ACTIVE') {
                list = list.filter((r) => r.Status_Aturan === 'ACTIVE');
            } else if (this.statusFilter === 'DRAFT') {
                list = list.filter((r) => r.Status_Aturan === 'DRAFT' || !r.Status_Aturan);
            } else if (this.statusFilter === 'ATTENTION') {
                list = list.filter((r) => ['FAILED', 'NEEDS_ATTENTION', 'RUNNING'].includes(r.Last_Apply_Status));
            }

            if (!this.searchRule) return list;
            const q = this.searchRule.toLowerCase().trim();
            return list.filter(
                (r) =>
                    (r.Nama_Aturan || '').toLowerCase().includes(q) ||
                    this.namaPaketRule(r).toLowerCase().includes(q)
            );
        },
        ruleQuickChips() {
            return [
                { value: 'ALL', label: 'Semua', badge: this.rules.length },
                { value: 'ACTIVE', label: 'Aktif', badge: this.countByStatus('ACTIVE'), color: 'emerald' },
                { value: 'DRAFT', label: 'Draft', badge: this.countByStatus('DRAFT'), color: 'slate' },
                ...(this.summary.attention_rules > 0
                    ? [{ value: 'ATTENTION', label: 'Perhatian', badge: this.summary.attention_rules, color: 'rose' }]
                    : []),
            ];
        },
        runQuickChips() {
            return [
                { value: 'ALL', label: 'Semua', badge: this.runs.length },
                { value: 'SUCCESS', label: 'Berhasil', badge: this.countRunsByStatus('SUCCESS'), color: 'emerald' },
                { value: 'RUNNING', label: 'Berjalan', badge: this.countRunsByStatus('RUNNING'), color: 'blue' },
                ...(this.countRunsByStatus('FAILED') > 0
                    ? [{ value: 'FAILED', label: 'Gagal', badge: this.countRunsByStatus('FAILED'), color: 'rose' }]
                    : []),
            ];
        },
        filteredRuns() {
            let list = this.runs || [];
            if (this.statusRunFilter !== 'ALL') {
                list = list.filter((r) => r.Status_Run === this.statusRunFilter);
            }
            if (this.tipeRunFilter !== 'ALL') {
                list = list.filter((r) => r.Tipe_Run === this.tipeRunFilter);
            }
            if (!this.searchRun) return list;
            const q = this.searchRun.toLowerCase().trim();
            return list.filter(
                (r) =>
                    (r.Nama_Paket || '').toLowerCase().includes(q) ||
                    (r.Tipe_Run || '').toLowerCase().includes(q) ||
                    (this.runLabel(r.Tipe_Run) || '').toLowerCase().includes(q) ||
                    (r.Error_Message || '').toLowerCase().includes(q)
            );
        },
    },
    mounted() {
        this.loadAll();
    },
    methods: {
        async loadAll() {
            this.loading.all = true;
            try {
                await Promise.all([
                    this.loadSummary(),
                    this.loadOptions(),
                    this.loadRules(),
                    this.loadRuns(),
                ]);
            } finally {
                this.loading.all = false;
            }
        },
        async loadSummary() {
            try {
                const r = await axios.get(`${API}/summary`);
                if (r.data.success) this.summary = r.data.result;
            } catch (_) {}
        },
        async loadOptions() {
            try {
                const r = await axios.get(`${API}/options`);
                if (r.data.success) this.options = r.data.result;
            } catch (_) {}
        },
        async loadRules() {
            this.loading.rules = true;
            try {
                const r = await axios.get(`${API}/rules`);
                if (r.data.success) {
                    this.rules = r.data.result || [];
                    if (this.selectedId) {
                        this.selectedRule =
                            this.rules.find((x) => x.Id_SOP_Assignment_Rule === this.selectedId) ||
                            this.selectedRule;
                    } else if (this.rules.length > 0) {
                        this.openRule(this.rules[0].Id_SOP_Assignment_Rule);
                    }
                }
            } catch (e) {
                this.fail(e, 'Gagal memuat daftar aturan penugasan');
            } finally {
                this.loading.rules = false;
            }
        },
        async loadRuns() {
            this.loading.runs = true;
            try {
                const r = await axios.get(`${API}/runs`);
                if (r.data.success) {
                    this.runs = r.data.result?.rows || [];
                    this.runsDitampilkan = r.data.result?.ditampilkan || 0;
                    this.runsTotal = r.data.result?.total_induk || 0;
                }
            } catch (e) {
                this.fail(e, 'Gagal memuat riwayat eksekusi');
            } finally {
                this.loading.runs = false;
            }
        },
        /** Cuplikan nama paket untuk baris daftar kiri. */
        namaPaketRule(rule) {
            return (rule.Paket || []).map((p) => p.Nama_Paket).join(' · ');
        },
        countByStatus(status) {
            if (status === 'ACTIVE') return this.rules.filter((r) => r.Status_Aturan === 'ACTIVE').length;
            if (status === 'DRAFT') return this.rules.filter((r) => r.Status_Aturan === 'DRAFT' || !r.Status_Aturan).length;
            return this.rules.length;
        },
        onStatFilterAttention() {
            this.activeTab = 'rules';
            this.statusFilter = 'ATTENTION';
        },
        resetRuleFilters() {
            this.searchRule = '';
            this.statusFilter = 'ALL';
        },
        newRule() {
            this.selectedId = null;
            this.selectedRule = null;
            this.preview = null;
            this.form = blankForm();
        },
        async openRule(id) {
            this.busy = true;
            try {
                const r = await axios.get(`${API}/rules/${id}`);
                const rule = r.data.result;
                this.selectedId = id;
                this.selectedRule = rule;
                this.preview = null;
                this.form = {
                    paket: (rule.Paket || []).map((p) => ({ ...p, schedule: p.schedule || null })),
                    nama_aturan: rule.Nama_Aturan,
                    groups: rule.config.groups?.length ? rule.config.groups : [blankGroup()],
                    exclude_groups: rule.config.exclude_groups || [],
                    include_karyawan: rule.config.include_karyawan || [],
                    exclude_karyawan: rule.config.exclude_karyawan || [],
                    schedule: { ...blankForm().schedule, ...(rule.config.schedule || {}) },
                };
            } catch (e) {
                this.fail(e, 'Gagal membuka rincian aturan');
            } finally {
                this.busy = false;
            }
        },
        configPayload() {
            return {
                groups: this.form.groups,
                exclude_groups: this.form.exclude_groups,
                include_karyawan: this.form.include_karyawan,
                exclude_karyawan: this.form.exclude_karyawan,
                schedule: this.form.schedule,
            };
        },
        /*
         * Opsi pemilih orang: seluruh karyawan aktif, DITAMBAH kode yang sudah
         * tersimpan tapi tak lagi ada di daftar aktif.
         *
         * Tanpa penambahan itu, karyawan yang keluar akan lenyap dari layar
         * sementara kodenya tetap tersimpan di config — pengecualian yang
         * hilang dari pandangan tapi masih bekerja adalah yang paling sulit
         * dilacak ketika seseorang bertanya kenapa ia tak menerima dokumen.
         */
        opsiOrang(terpilih) {
            const daftar = (this.options.karyawan || []).map((k) => ({
                value: k.Kode_Karyawan,
                label: `${k.Nama} · ${k.No_Induk_Karyawan || k.Kode_Karyawan}`
                    + (k.Jabatan ? ` · ${k.Jabatan}` : ''),
            }));

            const dikenal = new Set(daftar.map((o) => o.value));
            (terpilih || []).forEach((kode) => {
                if (!dikenal.has(kode)) {
                    daftar.push({ value: kode, label: `${kode} · tidak aktif / tak dikenal` });
                }
            });

            return daftar;
        },
        async saveDraft(showMessage = true) {
            if (!this.form.paket.length || !this.form.nama_aturan) {
                hcisMessage.warning('Nama aturan dan minimal satu paket dokumen wajib diisi.');
                return false;
            }
            this.busy = true;
            try {
                const payload = {
                    nama_aturan: this.form.nama_aturan,
                    config: this.configPayload(),
                    // Urutan diambil dari posisi baris, bukan dari nilai yang
                    // menempel di objeknya: keduanya bisa berbeda setelah
                    // penggeseran, dan yang benar adalah yang terlihat di layar.
                    paket: this.form.paket.map((p, i) => ({
                        id_paket: p.Id_SOP_Paket,
                        urutan: i + 1,
                        override: !!p.override,
                        schedule: p.override ? p.schedule : null,
                    })),
                };
                const r = this.selectedId
                    ? await axios.put(`${API}/rules/${this.selectedId}`, payload)
                    : await axios.post(`${API}/rules`, payload);
                if (!this.selectedId) this.selectedId = r.data.result.id;
                if (showMessage) hcisMessage.success('Draft aturan berhasil disimpan.');
                await this.loadRules();
                await this.openRule(this.selectedId);
                return true;
            } catch (e) {
                this.fail(e, 'Draft aturan gagal disimpan');
                return false;
            } finally {
                this.busy = false;
            }
        },
        async previewRule() {
            this.busy = true;
            try {
                const r = await axios.post(`${API}/rules/${this.selectedId}/preview`, {
                    config: this.configPayload(),
                });
                this.preview = r.data.result;
                hcisMessage.success('Dampak aturan berhasil dihitung dari data karyawan terkini.');
            } catch (e) {
                this.fail(e, 'Pratinjau dampak gagal dihitung');
            } finally {
                this.busy = false;
            }
        },
        async activate() {
            const saved = await this.saveDraft(false);
            if (!saved) return;
            this.busy = true;
            try {
                await axios.post(`${API}/rules/${this.selectedId}/activate`);
                hcisMessage.success('Aktivasi aturan masuk antrean pemrosesan.');
                this.preview = null;
                await this.loadAll();
            } catch (e) {
                this.fail(e, 'Aktivasi aturan gagal');
            } finally {
                this.busy = false;
            }
        },
        async deactivate() {
            try {
                const confirmed = await hcisConfirm({
                    title: 'Nonaktifkan Aturan Penugasan?',
                    description: 'Otomasi penugasan baru akan berhenti. Seluruh penugasan yang sudah tercatat sebelumnya tetap dipertahankan.',
                    sentiment: 'danger',
                    confirmText: 'Ya, Nonaktifkan',
                });
                if (!confirmed) return;

                this.busy = true;
                await axios.post(`${API}/rules/${this.selectedId}/deactivate`);
                hcisMessage.success('Aturan penugasan berhasil dinonaktifkan.');
                await this.loadRules();
            } catch (e) {
                this.fail(e, 'Gagal menonaktifkan aturan');
            } finally {
                this.busy = false;
            }
        },
        /* Rutenya bergerbang dua kunci (menuSopKebijakanPage ATAU
           menuSopSetupSystemPage). Tombolnya juga ada di kartu job halaman Set
           Up System; di sini ia dipakai untuk menjalankan evaluasi tepat setelah
           sebuah aturan diaktifkan, tanpa menunggu scheduler harian. */
        async runNow(type) {
            this.busy = true;
            try {
                await axios.post(`${API}/runs/${type}`);
                hcisMessage.success('Otomasi berhasil dimasukkan ke antrean worker.');
                await this.loadAll();
            } catch (e) {
                this.fail(e, 'Otomasi gagal dijalankan');
            } finally {
                this.busy = false;
            }
        },
        statusClass(rule) {
            if (['FAILED', 'NEEDS_ATTENTION'].includes(rule.Last_Apply_Status)) return 'danger';
            if (['QUEUED', 'RUNNING'].includes(rule.Last_Apply_Status)) return 'running';
            if (rule.Status_Aturan === 'ACTIVE') return 'success';
            return 'draft';
        },
        statusTextColor(rule) {
            if (['FAILED', 'NEEDS_ATTENTION'].includes(rule.Last_Apply_Status)) return 'text-danger';
            if (['QUEUED', 'RUNNING'].includes(rule.Last_Apply_Status)) return 'text-indigo-600';
            if (rule.Status_Aturan === 'ACTIVE') return 'text-success-700';
            return 'text-slate-500';
        },
        statusBadgeVariant(rule) {
            if (['FAILED', 'NEEDS_ATTENTION'].includes(rule.Last_Apply_Status)) return 'danger';
            if (['QUEUED', 'RUNNING'].includes(rule.Last_Apply_Status)) return 'info';
            if (rule.Status_Aturan === 'ACTIVE') return 'success';
            return 'neutral';
        },
        statusLabel(rule) {
            if (rule.Last_Apply_Status === 'NEEDS_ATTENTION') return 'Perlu Perhatian';
            if (rule.Last_Apply_Status === 'FAILED') return 'Gagal';
            if (['QUEUED', 'RUNNING'].includes(rule.Last_Apply_Status)) return 'Memproses';
            return rule.Status_Aturan === 'ACTIVE'
                ? 'Aktif'
                : rule.Status_Aturan === 'INACTIVE'
                  ? 'Nonaktif'
                  : 'Draft';
        },
        runStatusVariant(status) {
            return status === 'SUCCESS'
                ? 'success'
                : status === 'FAILED'
                  ? 'danger'
                  : status === 'SKIPPED'
                    ? 'warning'
                    : 'info';
        },
        runLabel(type) {
            return (
                {
                    RULE_APPLY: 'Aktivasi Aturan',
                    RULE_SCAN: 'Evaluasi Aturan',
                    REMINDER: 'Digest Reminder',
                }[type] || type
            );
        },
        triggerLabel(value) {
            return { ACTIVATION: 'Aktivasi', SCHEDULED: 'Terjadwal', MANUAL: 'Manual' }[value] || value;
        },
        formatDate(value) {
            return value
                ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(
                      new Date(value)
                  )
                : '-';
        },
        countRunsByStatus(status) {
            return (this.runs || []).filter((r) => r.Status_Run === status).length;
        },
        onRuleChipClick(chip) {
            this.statusFilter = this.statusFilter === chip.value ? 'ALL' : chip.value;
        },
        onRunChipClick(chip) {
            this.statusRunFilter = this.statusRunFilter === chip.value ? 'ALL' : chip.value;
        },
        resetRunFilters() {
            this.searchRun = '';
            this.statusRunFilter = 'ALL';
            this.tipeRunFilter = 'ALL';
        },
        triggerVariant(trigger) {
            return trigger === 'ACTIVATION' ? 'brand' : trigger === 'SCHEDULED' ? 'purple' : 'neutral';
        },
        runTypeIcon(type) {
            if (type === 'RULE_APPLY') return 'check-circle-fill';
            if (type === 'RULE_SCAN') return 'search';
            if (type === 'REMINDER') return 'bell-fill';
            return 'gear-wide-connected';
        },
        fail(error, fallback) {
            hcisMessage.error(
                error?.response?.data?.message ||
                    Object.values(error?.response?.data?.errors || {})?.[0]?.[0] ||
                    fallback
            );
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.sop-kebijakan-page {
    width: 100%;
}

.sop-tab-pill-btn {
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    background: var(--hcis-white);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.sop-tab-pill-btn:hover {
    color: var(--hcis-text-main, var(--hcis-slate-800));
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300));
    background: var(--surface-muted, var(--hcis-slate-50));
}

.sop-tab-pill-btn.active {
    background: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.sop-tab-pill-btn.is-danger.active {
    background: var(--hcis-danger-600, var(--hcis-red-600));
    border-color: var(--hcis-danger-600, var(--hcis-red-600));
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
}

.sop-rule-rows {
    max-height: 560px;
    overflow-y: auto;
    padding-right: 4px;
}

.sop-rule-item {
    padding: 0.85rem 0.95rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-left: 3.5px solid var(--hcis-slate-300);
    border-radius: 10px;
    background: var(--hcis-white);
    cursor: pointer;
    transition: all 0.16s ease-in-out;
}

.sop-rule-item:hover {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    background: var(--surface-muted, var(--hcis-slate-50));
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.06);
}

.sop-rule-item.is-selected {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500));
    border-left-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: rgba(99, 102, 241, 0.06);
    box-shadow: inset 2px 0 0 var(--hcis-brand-600, var(--hcis-indigo-600)), 0 4px 12px rgba(99, 102, 241, 0.12);
}

.sop-rule-item.status-success {
    border-left-color: var(--hcis-success-500, var(--hcis-emerald-500));
}

.sop-rule-item.status-danger {
    border-left-color: var(--hcis-danger-500, var(--hcis-red-500));
}

.sop-rule-item.status-running {
    border-left-color: var(--hcis-brand-500, var(--hcis-indigo-500));
}

.sop-rule-item.status-draft {
    border-left-color: var(--hcis-slate-400);
}

.sop-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-slate-400);
    flex-shrink: 0;
}

.sop-status-dot.success {
    background: var(--hcis-emerald-500);
}

.sop-status-dot.danger {
    background: var(--hcis-red-500);
}

.sop-status-dot.running {
    background: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.sop-status-dot.draft {
    background: var(--hcis-slate-400);
}

.sop-section-icon-dot {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sop-cohort-box {
    transition: all 0.15s ease-in-out;
}

.sop-cohort-box:hover {
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300));
    background: var(--hcis-white);
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
