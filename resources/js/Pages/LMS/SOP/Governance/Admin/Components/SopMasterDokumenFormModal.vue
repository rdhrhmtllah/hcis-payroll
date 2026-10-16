<template>
    <HcisDialog
        :model-value="show"
        :title="isEditing ? 'Ubah Identitas Dokumen' : 'Buat Master Dokumen Baru'"
        :kicker="isEditing ? 'Pembaruan Dokumen Terkendali' : 'Master Dokumen Terkendali Baru'"
        icon="journal-bookmark-fill"
        size="lg"
        sentiment="primary"
        variant="gradient"
        glow
        pattern="grid"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <form v-if="show" class="sop-doc-form-wrapper" @submit.prevent="handleNextOrSubmit">
            <!-- ════ 0. HCISSTEPS INTERACTIVE WIZARD TRACKER ════ -->
            <div class="wizard-steps-wrapper mb-3.5">
                <HcisSteps
                    v-model:current="stepIndex"
                    variant="pills"
                    size="sm"
                    sentiment="primary"
                    :steps="stepperItems"
                    clickable
                    :allow-all-clickable="isEditing"
                />
            </div>

            <!-- ════ STEP 1: MUTU & MEDIA ════ -->
            <div v-show="stepIndex === 0" class="wizard-step-pane">
                <HcisCard
                    title="1. Identitas Pokok &amp; Klasifikasi Mutu"
                    description="Tentukan nama dokumen, format media, piramid hirarki mutu ISO, dan klasifikasi proses APQC PCF v7.3."
                    icon="diagram-3"
                    sentiment="primary"
                    variant="outlined"
                    size="sm"
                    :badge="isStep1Complete ? 'Lengkap' : 'Wajib Diisi'"
                    :badge-variant="isStep1Complete ? 'success' : 'neutral'"
                >
                    <div class="d-flex flex-column gap-3.5 pt-1">
                        <!-- Judul Dokumen -->
                        <HcisField
                            id="doc-form-judul"
                            v-model="formState.Judul"
                            label="Judul Dokumen"
                            type="text"
                            placeholder="mis. Prosedur Pengadaan Barang dan Jasa Operasional"
                            leading-icon="journal-text"
                            clearable
                            required
                        />

                        <!-- Grid: Tipe Media & Jenis Dokumen Mutu -->
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="d-flex flex-column gap-1">
                                    <label for="doc-form-tipe" class="hcis-form-label">
                                        Tipe Media <span class="text-danger">*</span>
                                    </label>
                                    <HcisSelect
                                        id="doc-form-tipe"
                                        v-model="formState.Tipe"
                                        size="sm"
                                        leading-icon="file-earmark-text"
                                        placeholder="Pilih Tipe Media..."
                                        :disabled="Boolean(isEditing && isDipakai)"
                                        :clearable="false"
                                        :filterable="false"
                                        @update:model-value="onTipeChange"
                                    >
                                        <HcisSelectOption
                                            v-for="t in tipePilihan"
                                            :key="t"
                                            :value="t"
                                            :label="t"
                                            :icon="getTipeIcon(t)"
                                        />
                                    </HcisSelect>
                                    <span v-if="isEditing && isDipakai" class="text-muted small mt-1 d-block font-italic">
                                        <i class="bi bi-lock me-1"></i>Terkunci karena sedang dipakai paket.
                                    </span>
                                </div>
                            </div>

                            <div class="col-12 col-md-8">
                                <div class="d-flex flex-column gap-1">
                                    <label for="doc-form-jenis" class="hcis-form-label">
                                        Jenis Dokumen (Hirarki Mutu) <span class="text-danger">*</span>
                                    </label>
                                    <HcisSelect
                                        id="doc-form-jenis"
                                        v-model="formState.Jenis_Dokumen_Id"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="380"
                                        leading-icon="diagram-3"
                                        :disabled="!adaOpsiJenis"
                                        :placeholder="adaOpsiJenis
                                            ? 'Pilih Jenis Dokumen Mutu...'
                                            : `Tipe ${formState.Tipe} belum dipetakan ke Jenis Dokumen`"
                                        @update:model-value="onSegmentChange"
                                    >
                                        <HcisSelectOption
                                            v-for="j in jenisOpsi"
                                            :key="j.Id"
                                            :value="j.Id"
                                            :label="`${j.Kode_Jenis_Dokumen || `L${j.Level_Hirarki || '-'}`} · ${j.Nama_Jenis}`"
                                            :description="`Level ${j.Level_Hirarki || '-'} Hirarki Mutu ISO`"
                                            :badge="`L${j.Level_Hirarki || '-'}`"
                                            badge-variant="brand"
                                        />
                                    </HcisSelect>
                                </div>
                            </div>
                        </div>

                        <!-- Proses Bisnis APQC (PCF v7.3) -->
                        <div class="d-flex flex-column gap-1">
                            <label for="doc-form-apqc" class="hcis-form-label">
                                Proses Bisnis APQC (PCF v7.3) <span class="text-danger">*</span>
                            </label>
                            <HcisSelect
                                id="doc-form-apqc"
                                v-model="formState.APQC_Id"
                                size="sm"
                                filterable
                                clearable
                                popover-min-width="460"
                                leading-icon="bounding-box"
                                :disabled="!adaOpsiApqc"
                                :placeholder="adaOpsiApqc
                                    ? 'Cari atau pilih proses bisnis APQC PCF v7.3...'
                                    : `Tipe ${formState.Tipe} belum dipetakan ke proses APQC`"
                                @update:model-value="onSegmentChange"
                            >
                                <HcisSelectOption
                                    v-for="a in apqcOpsi"
                                    :key="a.Id"
                                    :value="a.Id"
                                    :label="`PCF #${a.Kode_Proses} · ${a.Nama_Proses}`"
                                    :description="isOperatingProcess(a.Kode_Proses) ? 'Operating Core Process (Rantai Nilai Operasional Inti)' : 'Management & Support Services (Fungsi Manajemen & Pendukung)'"
                                    :badge="`#${a.Kode_Proses}`"
                                    :badge-variant="isOperatingProcess(a.Kode_Proses) ? 'teal' : 'purple'"
                                />
                            </HcisSelect>
                        </div>

                        <!-- PENOLAKAN YANG PUNYA JALAN KELUAR.
                             Muncul HANYA ketika masternya yang belum siap, bukan
                             ketika admin sekadar belum memilih. Tanpa ini tombol
                             Lanjut mati tanpa sebab yang bisa dibaca, dan tipe
                             yang pemetaannya kosong menjadi jalan buntu bisu. -->
                        <HcisAlert
                            v-if="pemetaanKurang.length"
                            data-uji="pemetaan-kurang"
                            variant="danger"
                            appearance="subtle"
                            size="sm"
                            icon="exclamation-octagon-fill"
                            title="Pemetaan master belum lengkap untuk tipe ini"
                            :description="pesanPemetaanKurang"
                        />
                    </div>
                </HcisCard>
            </div>

            <!-- ════ STEP 2: ORGANISASI & KEPEMILIKAN ════ -->
            <div v-show="stepIndex === 1" class="wizard-step-pane">
                <HcisCard
                    title="2. Kepemilikan &amp; Ruang Lingkup Organisasi"
                    description="Tentukan entitas PT penerbit, departemen penanggung jawab teknis, dan sasaran penerapan prosedur."
                    icon="building"
                    sentiment="primary"
                    variant="outlined"
                    size="sm"
                    :badge="isStep2Complete ? 'Lengkap' : 'Wajib Diisi'"
                    :badge-variant="isStep2Complete ? 'success' : 'neutral'"
                >
                    <div class="d-flex flex-column gap-3.5 pt-1">
                        <!-- Sub-Section A: Unit Penanggung Jawab (Owner) -->
                        <div>
                            <div class="d-flex align-items-center gap-1.5 mb-2">
                                <span class="text-xs fw-bold text-uppercase text-slate-500 tracking-wider">Unit Pemilik &amp; Penerbit Dokumen</span>
                            </div>
                            <div class="row g-3">
                                <!-- Entitas Pemilik -->
                                <div class="col-12 col-md-6">
                                    <div class="d-flex flex-column gap-1">
                                        <label for="doc-form-entitas" class="hcis-form-label">
                                            Entitas PT Pemilik <span class="text-danger">*</span>
                                        </label>
                                        <HcisSelect
                                            id="doc-form-entitas"
                                            v-model="formState.Entitas_Pemilik_Id"
                                            size="sm"
                                            filterable
                                            clearable
                                            popover-min-width="340"
                                            leading-icon="building"
                                            placeholder="Pilih Perusahaan PT..."
                                            @update:model-value="onEntitasPemilikChange"
                                        >
                                            <!-- Yang diperlihatkan adalah kode yang AKAN TERCETAK
                                                 di nomor, bukan kode identitas entitasnya. Holding
                                                 tercetak "HOL" sebagai pemilik meski kodenya "EHG";
                                                 menampilkan EHG di sini membuat admin memilih satu
                                                 hal lalu melihat hal lain muncul di nomornya. -->
                                            <HcisSelectOption
                                                v-for="e in entitasOpsi"
                                                :key="e.Id"
                                                :value="e.Id"
                                                :label="`${e.Kode_Pemilik || e.Kode_Entitas} · ${e.Nama_Singkat || e.Nama_Entitas}`"
                                                :description="e.Nama_Entitas"
                                                :badge="e.Kode_Pemilik || e.Kode_Entitas"
                                                badge-variant="neutral"
                                            />
                                        </HcisSelect>
                                    </div>
                                </div>

                                <!-- Departemen Pemilik -->
                                <div class="col-12 col-md-6">
                                    <div class="d-flex flex-column gap-1">
                                        <label for="doc-form-dept" class="hcis-form-label">
                                            Departemen Penanggung Jawab <span class="text-danger">*</span>
                                        </label>
                                        <HcisSelect
                                            id="doc-form-dept"
                                            v-model="formState.Departemen_Id"
                                            size="sm"
                                            filterable
                                            clearable
                                            popover-min-width="360"
                                            leading-icon="diagram-2"
                                            placeholder="Pilih Departemen Pemilik..."
                                            @update:model-value="onSegmentChange"
                                        >
                                            <HcisSelectOption
                                                v-for="dp in departemenOpsi"
                                                :key="dp.Id"
                                                :value="dp.Id"
                                                :label="`${dp.Kode_Departemen} — ${dp.Nama_Departemen}`"
                                                :description="`Kode Dept: ${dp.Kode_Departemen}`"
                                                :badge="dp.Kode_Departemen"
                                                badge-variant="teal"
                                            />
                                        </HcisSelect>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-Section B: Ruang Lingkup Penerapan (Scope) -->
                        <div class="pt-2 border-top border-slate-100">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <span class="text-xs fw-bold text-uppercase text-slate-500 tracking-wider">Ruang Lingkup Penerapan Dokumen</span>
                                <HcisBadge
                                    v-if="formState.Entitas_Pemilik_Id && formState.Entitas_Lingkup_Id"
                                    :label="formState.Entitas_Pemilik_Id === formState.Entitas_Lingkup_Id ? 'Penerapan Internal PT' : 'Penerapan Lintas Entitas'"
                                    :variant="formState.Entitas_Pemilik_Id === formState.Entitas_Lingkup_Id ? 'brand' : 'purple'"
                                    size="xs"
                                    :leading-icon="formState.Entitas_Pemilik_Id === formState.Entitas_Lingkup_Id ? 'building-check' : 'diagram-3'"
                                />
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex flex-column gap-1">
                                    <label for="doc-form-lingkup" class="hcis-form-label">
                                        Sasaran Penerapan di Entitas <span class="text-danger">*</span>
                                    </label>
                                    <HcisSelect
                                        id="doc-form-lingkup"
                                        v-model="formState.Entitas_Lingkup_Id"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="360"
                                        leading-icon="geo-alt"
                                        placeholder="Pilih Sasaran Entitas PT..."
                                        @update:model-value="onSegmentChange"
                                    >
                                        <!-- Ruang lingkup memakai kode IDENTITAS, bukan kode
                                             pemilik. Di posisi ini "EHG" berarti "berlaku di
                                             seluruh entitas" — sebuah pernyataan cakupan, bukan
                                             nama perusahaan — dan keterangannya mengatakan itu
                                             di titik memilih, bukan di dokumentasi. -->
                                        <HcisSelectOption
                                            v-for="e in entitasOpsi"
                                            :key="e.Id"
                                            :value="e.Id"
                                            :label="`${e.Kode_Entitas} · ${e.Nama_Singkat || e.Nama_Entitas}`"
                                            :description="e.Cakup_Semua
                                                ? `${e.Nama_Entitas} — berlaku di seluruh entitas`
                                                : e.Nama_Entitas"
                                            :badge="e.Kode_Entitas"
                                            badge-variant="neutral"
                                        />
                                    </HcisSelect>
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- ════ STEP 3: PENOMORAN BAKU & PRATINJAU SEGMEN ════ -->
            <div v-show="stepIndex === 2" class="wizard-step-pane">
                <HcisCard
                    title="3. Generator Penomoran Standar &amp; Visual Segmen"
                    description="Periksa keutuhan segmen baku penomoran dan tentukan nomor urut unik dokumen."
                    icon="hash"
                    sentiment="primary"
                    variant="outlined"
                    size="sm"
                    :badge="isStep3Complete ? 'Lengkap & Sah' : 'Segmen Belum Lengkap'"
                    :badge-variant="isStep3Complete ? 'success' : 'warning'"
                >
                    <div class="d-flex flex-column gap-3.5 pt-1">
                        <!-- 1. Hero Official Number Preview Banner -->
                        <div class="nomor-preview-box" :class="segmenKosong.length === 0 ? 'is-complete' : 'is-incomplete'">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <span class="preview-label">PRATINJAU NOMOR DOKUMEN RESMI</span>
                                <HcisBadge
                                    v-if="segmenKosong.length === 0"
                                    label="Semua Segmen Lengkap &amp; Sah"
                                    variant="success"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="check-circle-fill"
                                />
                                <HcisBadge
                                    v-else
                                    :label="`Segmen Belum Lengkap: ${segmenKosong.join(', ')}`"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="exclamation-triangle-fill"
                                />
                            </div>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="preview-number font-monospace">
                                    {{ pratinjauNomorLengkap || '—' }}
                                </div>
                                <HcisButton
                                    v-if="pratinjauNomorLengkap"
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="clipboard"
                                    label="Salin Nomor"
                                    title="Salin Nomor Dokumen ke Clipboard"
                                    @click="salinNomorPreview"
                                />
                            </div>
                        </div>

                        <!-- 2. Interactive 6-Segment Breakdown Anatomy Grid -->
                        <div>
                            <div class="d-flex align-items-center gap-1.5 mb-2">
                                <span class="text-xs fw-bold text-uppercase text-slate-500 tracking-wider">Anatomi Segmen Pembentuk Nomor</span>
                            </div>
                            <div class="segment-grid">
                                <div class="segment-tile" :class="selectedJenisKode ? 'is-active' : 'is-idle'">
                                    <span class="segment-tile-lbl">1. JENIS</span>
                                    <span class="segment-tile-val font-monospace">{{ selectedJenisKode || '??' }}</span>
                                    <span class="segment-tile-sub">{{ selectedJenisObj ? `L${selectedJenisObj.Level_Hirarki || '-'}` : 'Mutu' }}</span>
                                </div>

                                <!-- Memakai kode CETAK, bukan kode identitas: ubin ini pratinjau
                                     nomor, jadi ia harus memperlihatkan apa yang benar-benar akan
                                     tercetak. Keterangannya menyebut entitas asalnya ketika
                                     keduanya berbeda. -->
                                <div class="segment-tile" :class="selectedEntitasKodeNomor ? 'is-active' : 'is-idle'">
                                    <span class="segment-tile-lbl">2. ENTITAS</span>
                                    <span class="segment-tile-val font-monospace">{{ selectedEntitasKodeNomor || '??' }}</span>
                                    <span class="segment-tile-sub">
                                        {{ kodePemilikBeda(selectedEntitasObj) ? `PT Pemilik · ${selectedEntitasKode}` : 'PT Pemilik' }}
                                    </span>
                                </div>

                                <div class="segment-tile" :class="selectedDeptKode ? 'is-active' : 'is-idle'">
                                    <span class="segment-tile-lbl">3. DEPT</span>
                                    <span class="segment-tile-val font-monospace">{{ selectedDeptKode || '??' }}</span>
                                    <span class="segment-tile-sub">Unit Kerja</span>
                                </div>

                                <!-- SEGMEN KEEMPAT, sebelumnya tak punya ubin sama sekali padahal
                                     ia ada di nomornya. Akibatnya deretan ini menomori APQC
                                     sebagai "4" padahal posisinya kelima, dan pratinjau yang
                                     salah posisi lebih menyesatkan daripada tak ada pratinjau. -->
                                <div class="segment-tile" :class="selectedLingkupKode ? 'is-active' : 'is-idle'">
                                    <span class="segment-tile-lbl">4. LINGKUP</span>
                                    <span class="segment-tile-val font-monospace">{{ selectedLingkupKode || '??' }}</span>
                                    <span class="segment-tile-sub">
                                        {{ selectedLingkupObj && selectedLingkupObj.Cakup_Semua ? 'Seluruh entitas' : 'Sasaran PT' }}
                                    </span>
                                </div>

                                <div class="segment-tile" :class="selectedApqcKode ? 'is-active' : 'is-idle'">
                                    <span class="segment-tile-lbl">5. APQC</span>
                                    <span class="segment-tile-val font-monospace">{{ selectedApqcKode || '??' }}</span>
                                    <span class="segment-tile-sub">PCF Proses</span>
                                </div>

                                <div class="segment-tile" :class="[formState.No_Dokumen ? 'is-active is-highlight' : 'is-idle', { 'is-loading-pulse': saranLoading }]">
                                    <span class="segment-tile-lbl">6. NO URUT</span>
                                    <span class="segment-tile-val font-monospace">
                                        <span v-if="saranLoading" class="spinner-border spinner-border-sm text-primary" style="width: 0.85rem; height: 0.85rem; border-width: 2px;" role="status"></span>
                                        <template v-else>{{ padNumber(formState.No_Dokumen) || '??' }}</template>
                                    </span>
                                    <span class="segment-tile-sub">Urutan</span>
                                </div>

                                <div class="segment-tile is-active is-version">
                                    <span class="segment-tile-lbl">7. REVISI</span>
                                    <span class="segment-tile-val font-monospace">{{ isEditing && versiAktifKe ? `R${versiAktifKe}` : 'R0' }}</span>
                                    <span class="segment-tile-sub">Versi</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Smart Sequence Number Input & Recommendation -->
                        <div class="pt-2.5 border-top border-slate-100">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <span class="text-xs fw-bold text-uppercase text-slate-500 tracking-wider">Penetapan Nomor Urut</span>
                                <HcisBadge
                                    v-if="saranLoading"
                                    label="Menghitung…"
                                    variant="brand"
                                    size="xs"
                                >
                                    <template #icon>
                                        <span class="spinner-border spinner-border-sm me-1" style="width: 0.65rem; height: 0.65rem; border-width: 1.5px;" role="status"></span>
                                    </template>
                                </HcisBadge>
                                <HcisBadge
                                    v-else-if="saranError"
                                    label="Gagal Memuat Nomor"
                                    variant="danger"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="exclamation-triangle-fill"
                                />
                                <HcisBadge
                                    v-else-if="saranNoDokumen !== null && Number(formState.No_Dokumen) === Number(saranNoDokumen)"
                                    label="Rekomendasi Terpasang"
                                    variant="success"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else-if="formState.No_Dokumen"
                                    label="Nomor Kustom"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>

                            <div class="d-flex align-items-center gap-2.5 flex-wrap">
                                <!-- Input Field (Compact) -->
                                <div style="width: 135px;">
                                    <HcisField
                                        id="doc-form-nodok"
                                        v-model="formState.No_Dokumen"
                                        type="number"
                                        min="1"
                                        max="999"
                                        placeholder="001"
                                        leading-icon="123"
                                        :loading="saranLoading"
                                    />
                                </div>

                                <!-- Sleek Visual Recommendation Pill Strip -->
                                <div class="flex-grow-1 min-w-0">
                                    <!-- Loading State -->
                                    <div v-if="saranLoading" class="seq-rec-card is-loading">
                                        <span class="spinner-border spinner-border-sm text-primary" style="width: 0.85rem; height: 0.85rem; border-width: 1.5px;" role="status"></span>
                                        <span class="text-xs text-slate-500">Menganalisis urutan master berikutnya…</span>
                                    </div>

                                    <!-- Error State -->
                                    <div v-else-if="saranError" class="seq-rec-card is-error">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <i class="bi bi-exclamation-octagon-fill text-danger"></i>
                                            <span class="text-xs text-danger fw-semibold text-truncate">
                                                {{ typeof saranError === 'string' ? saranError : 'Gagal memuat rekomendasi nomor urut dari server.' }}
                                            </span>
                                        </div>
                                        <HcisButton
                                            variant="secondary"
                                            size="xs"
                                            leading-icon="arrow-clockwise"
                                            label="Coba Lagi"
                                            @click="emit('retry-saran')"
                                        />
                                    </div>

                                    <!-- Sequence Recommendation Strip -->
                                    <div v-else-if="saranNoDokumen !== null" class="seq-rec-card">
                                        <!-- Case > 1: Sequential continuation -->
                                        <template v-if="Number(saranNoDokumen) > 1">
                                            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                                <div class="d-flex align-items-center gap-1.5 font-monospace">
                                                    <span class="seq-chip seq-chip--prev" :title="dokumenTerakhir?.Nomor_SOP_Resmi ? `Nomor Terakhir: ${dokumenTerakhir.Nomor_SOP_Resmi}` : 'Nomor urut terakhir terdaftar'">
                                                        #{{ padNumber(dokumenTerakhir?.No_Dokumen || (Number(saranNoDokumen) - 1)) }}
                                                    </span>
                                                    <i class="bi bi-arrow-right text-slate-400"></i>
                                                    <span class="seq-chip seq-chip--target" title="Nomor rekomendasi sistem">
                                                        #{{ padNumber(saranNoDokumen) }}
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center gap-1.5 text-xs text-slate-600 flex-wrap min-w-0">
                                                    <span class="text-slate-500">Lanjutan:</span>
                                                    <span v-if="dokumenTerakhir?.Nomor_SOP_Resmi" class="font-monospace fw-bold text-dark bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 text-xs">
                                                        {{ dokumenTerakhir.Nomor_SOP_Resmi }}
                                                    </span>
                                                    <span v-else class="fw-semibold text-slate-700">
                                                        urutan master #{{ padNumber(Number(saranNoDokumen) - 1) }}
                                                    </span>
                                                    <span v-if="dokumenTerakhir?.Judul" class="text-slate-500 text-truncate" style="max-width: 200px;" :title="dokumenTerakhir.Judul">
                                                        ({{ dokumenTerakhir.Judul }})
                                                    </span>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Case === 1: First document in group -->
                                        <template v-else-if="Number(saranNoDokumen) === 1">
                                            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                                <span class="seq-chip seq-chip--target">#001 Perdana</span>
                                                <span class="text-xs text-slate-600 text-truncate">
                                                    Dokumen registrasi pertama pada kombinasi unit ini
                                                </span>
                                            </div>
                                        </template>

                                        <!-- Restore button if customized -->
                                        <HcisButton
                                            v-if="Number(formState.No_Dokumen) !== Number(saranNoDokumen)"
                                            variant="glass"
                                            size="xs"
                                            leading-icon="stars"
                                            :label="`Gunakan #${padNumber(saranNoDokumen)}`"
                                            title="Terapkan kembali nomor urut rekomendasi sistem"
                                            @click="applySaranNomor"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- ════ STEP 4: DESKRIPSI & RINGKASAN EKSEKUTIF ════ -->
            <div v-show="stepIndex === 3" class="wizard-step-pane">
                <HcisCard
                    title="4. Ringkasan Eksekutif &amp; Batasan Prosedur"
                    description="Lengkapi batasan prosedur dan verifikasi seluruh identitas dokumen sebelum disimpan."
                    icon="card-checklist"
                    sentiment="primary"
                    variant="outlined"
                    size="sm"
                    :badge="canSubmit ? 'Siap Disimpan' : 'Periksa Isian'"
                    :badge-variant="canSubmit ? 'success' : 'warning'"
                >
                    <div class="d-flex flex-column gap-3.5 pt-1">
                        <!-- 1. Deskripsi & Batasan Prosedur (Editable Input) -->
                        <HcisTextarea
                            id="doc-form-desc"
                            v-model="formState.Deskripsi"
                            label="Deskripsi &amp; Batasan Penerapan Prosedur"
                            placeholder="Jelaskan ringkasan alur kerja, maksud tujuan, dan batasan penerapan prosedur ini..."
                            :rows="3"
                            clearable
                            hint="Opsional · Memberikan konteks ringkas bagi pelaksana, reviewer, dan auditor dokumen."
                        />

                        <!-- 2. Dedicated Official Identity Passport Strip -->
                        <div class="passport-container">
                            <div class="passport-header">
                                <div class="passport-header-title">
                                    <i class="bi bi-shield-check text-primary me-1"></i>
                                    <span>Paspor Verifikasi Identitas Dokumen</span>
                                </div>
                                <HcisBadge
                                    :label="canSubmit ? 'Semua Segmen Sah' : 'Segmen Belum Lengkap'"
                                    :variant="canSubmit ? 'success' : 'warning'"
                                    size="xs"
                                    :leading-icon="canSubmit ? 'check-circle-fill' : 'exclamation-triangle-fill'"
                                />
                            </div>

                            <!-- Hero Card: Document Number & Title -->
                            <div class="passport-hero">
                                <div class="passport-hero-top">
                                    <span class="passport-hero-kicker">Nomor Dokumen Terkendali Resmi</span>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <HcisBadge
                                            :label="formState.Tipe"
                                            variant="neutral"
                                            size="xs"
                                            :leading-icon="getTipeIcon(formState.Tipe)"
                                        />
                                        <HcisBadge
                                            v-if="selectedJenisObj"
                                            :label="`Level ${selectedJenisObj.Level_Hirarki || '-'} Mutu`"
                                            variant="brand"
                                            size="xs"
                                        />
                                    </div>
                                </div>
                                <div class="passport-hero-number font-monospace">
                                    {{ pratinjauNomorLengkap || '—' }}
                                </div>
                                <div class="passport-hero-title">
                                    {{ formState.Judul || '(Judul dokumen belum diisi)' }}
                                </div>
                            </div>

                            <!-- 4-Tile Grid: Governance Attributes -->
                            <div class="passport-grid">
                                <div class="passport-tile">
                                    <div class="passport-tile-lbl">
                                        <i class="bi bi-diagram-3 me-1 text-primary"></i>
                                        <span>Jenis Dokumen Mutu</span>
                                    </div>
                                    <div class="passport-tile-val">
                                        {{ selectedJenisObj ? `${selectedJenisObj.Kode_Jenis_Dokumen || 'MUTU'} — ${selectedJenisObj.Nama_Jenis}` : '—' }}
                                    </div>
                                </div>

                                <div class="passport-tile">
                                    <div class="passport-tile-lbl">
                                        <i class="bi bi-bounding-box me-1 text-purple"></i>
                                        <span>Proses Bisnis APQC</span>
                                    </div>
                                    <div class="passport-tile-val">
                                        {{ selectedApqcObj ? `PCF #${selectedApqcObj.Kode_Proses} · ${selectedApqcObj.Nama_Proses}` : '—' }}
                                    </div>
                                </div>

                                <div class="passport-tile">
                                    <div class="passport-tile-lbl">
                                        <i class="bi bi-diagram-2 me-1 text-teal"></i>
                                        <span>Unit Penanggung Jawab</span>
                                    </div>
                                    <div class="passport-tile-val">
                                        <span>PT <strong>{{ selectedEntitasKode || '—' }}</strong></span>
                                        <span class="text-slate-400 mx-1">·</span>
                                        <span>Dept: <strong>{{ selectedDeptObj ? `${selectedDeptObj.Kode_Departemen} (${selectedDeptObj.Nama_Departemen})` : '—' }}</strong></span>
                                    </div>
                                </div>

                                <div class="passport-tile">
                                    <div class="passport-tile-lbl">
                                        <i class="bi bi-geo-alt me-1 text-warning"></i>
                                        <span>Ruang Lingkup Sasaran</span>
                                    </div>
                                    <div class="passport-tile-val">
                                        <span>Sasaran PT: <strong>{{ selectedLingkupKode || '—' }}</strong></span>
                                        <span class="passport-scope-pill ms-1" :class="formState.Entitas_Pemilik_Id === formState.Entitas_Lingkup_Id ? 'is-internal' : 'is-cross'">
                                            {{ formState.Entitas_Pemilik_Id === formState.Entitas_Lingkup_Id ? 'Internal PT' : 'Lintas Entitas' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Confirmation Callout -->
                        <HcisAlert
                            :variant="canSubmit ? 'success' : 'warning'"
                            appearance="subtle"
                            size="sm"
                            :icon="canSubmit ? 'shield-check' : 'info-circle-fill'"
                            :title="canSubmit ? 'Identitas Dokumen Lengkap & Siap Disimpan' : 'Lengkapi Isian yang Masih Kosong'"
                            :description="canSubmit ? 'Seluruh segmen pembentuk nomor baku dan hierarki tata kelola telah lengkap. Klik tombol di bawah untuk menyimpan perubahan.' : 'Periksa kembali langkah sebelumnya untuk memastikan seluruh isian wajib telah terisi.'"
                        />
                    </div>
                </HcisCard>
            </div>
        </form>

        <template #footer>
            <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                <!-- Status Telemetry (Left) -->
                <div class="d-flex align-items-center gap-2">
                    <HcisStatusBadge
                        :status="canSubmit ? 'published' : 'draft'"
                        :label="`Tahap ${stepIndex + 1} dari 4 · ${canSubmit ? 'Semua Segmen Sah' : 'Lengkapi Isian'}`"
                    />
                </div>

                <!-- Wizard Actions (Right) -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Cancel or Previous Button -->
                    <HcisButton
                        v-if="stepIndex === 0"
                        variant="secondary"
                        size="md"
                        :disabled="saving"
                        label="Batal"
                        @click="close"
                    />
                    <HcisButton
                        v-else
                        variant="secondary"
                        size="md"
                        leading-icon="arrow-left"
                        :disabled="saving"
                        label="Sebelumnya"
                        @click="prevStep"
                    />

                    <!-- Next or Submit Button -->
                    <HcisButton
                        v-if="stepIndex < 3"
                        variant="primary"
                        size="md"
                        trailing-icon="arrow-right"
                        :disabled="!canProceedCurrentStep"
                        label="Lanjut"
                        @click="nextStep"
                    />
                    <HcisButton
                        v-else
                        variant="primary"
                        size="md"
                        leading-icon="check2"
                        :loading="saving"
                        :disabled="!canSubmit"
                        :label="saving ? 'Menyimpan…' : isEditing ? 'Simpan Perubahan Identitas' : 'Buat Master Dokumen'"
                        @click="submit"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import {
    HcisDialog,
    HcisCard,
    HcisButton,
    HcisField,
    HcisBadge,
    HcisStatusBadge,
    HcisAlert,
    HcisSteps,
    HcisSelect,
    HcisSelectOption,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import { nomorDokumen } from '../../../Shared/support/versi.js';

defineOptions({ name: 'SopMasterDokumenFormModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    tipeOpsi: { type: Array, default: () => [] },
    jenisOpsi: { type: Array, default: () => [] },
    apqcOpsi: { type: Array, default: () => [] },
    entitasOpsi: { type: Array, default: () => [] },
    departemenOpsi: { type: Array, default: () => [] },
    saranNoDokumen: { type: [Number, String], default: null },
    saranLoading: { type: Boolean, default: false },
    saranError: { type: [Boolean, String], default: false },
    dokumenTerakhir: { type: Object, default: null },
    pratinjauNomorLengkap: { type: String, default: '' },
    segmenKosong: { type: Array, default: () => [] },
});

const emit = defineEmits([
    'close',
    'submit',
    'tipe-change',
    'segment-change',
    'no-dokumen-input',
    'apply-saran',
    'retry-saran',
]);

const stepIndex = ref(0);

const formState = reactive({
    id: null,
    Judul: '',
    Tipe: 'DOKUMEN',
    Jenis_Dokumen_Id: '',
    APQC_Id: '',
    Entitas_Pemilik_Id: '',
    Departemen_Id: '',
    Entitas_Lingkup_Id: '',
    No_Dokumen: '',
    Deskripsi: '',
});

const isEditing = computed(() => Boolean(formState.id));
/**
 * Pilihan tipe SELALU memuat tipe dokumen yang sedang disunting.
 *
 * `tipeOpsi` menyempit mengikuti sakelar di Setup System — itu benar untuk
 * dokumen BARU. Tapi mematikan sebuah tipe tak membekukan dokumen lama bertipe
 * itu, dan tanpa penjagaan ini pemilihnya kehilangan satu-satunya opsi yang
 * cocok: layar menampilkan "Pilih Tipe Media..." untuk dokumen yang tipenya
 * jelas ada, dan menyimpannya berarti mengubah tipe tanpa admin memintanya.
 */
const tipePilihan = computed(() => {
    const daftar = props.tipeOpsi || [];
    const sekarang = formState.Tipe;

    return sekarang && !daftar.includes(sekarang) ? [...daftar, sekarang] : daftar;
});
const isDipakai = computed(() => Boolean(props.item?.Jumlah_Paket_Pemakai > 0));
const versiAktifKe = computed(() => props.item?.Versi_Berlaku_Ke || null);

/*
 * NOMOR RESMI WAJIB UTUH — keputusan produk 10 Sep 2026.
 *
 * Jenis dan APQC wajib untuk SETIAP tipe, karena keduanya segmen pembentuk
 * Nomor_SOP_Resmi dan `SopNomorDokumen::rakit()` menolak merakit nomor yang
 * ada segmen bolongnya. Dokumen yang lahir tanpa salah satunya TAK AKAN PERNAH
 * bernomor — bukan sekarang, bukan setelah terbit — dan server tak akan
 * memberitahu siapa pun: `segarkanNomorResmi()` sengaja membiarkan kolomnya
 * null, sebab 16 dokumen lama menyimpan nomor ketikan tangan yang tak boleh
 * tertimpa. Layar adalah satu-satunya tempat pertanyaan ini bisa diajukan.
 *
 * Buktinya sudah ada di data: dokumen KUIS #30771 lahir tanpa Jenis pada
 * 10 Sep 2026 justru lewat layar ini, ketika kewajibannya sempat diturunkan
 * dari panjang daftar opsi.
 */
const adaOpsiJenis = computed(() => (props.jenisOpsi || []).length > 0);
const adaOpsiApqc = computed(() => (props.apqcOpsi || []).length > 0);

/**
 * Pemetaan master yang belum ada untuk tipe ini.
 *
 * DIBEDAKAN DARI "BELUM DIPILIH", dan itu seluruh gunanya. Field yang belum
 * dipilih diperbaiki di layar ini; pemetaan yang belum ada TIDAK — dan tombol
 * mati tanpa keterangan pada kasus kedua adalah jalan buntu bisu, persis yang
 * membuat tipe KUIS tak pernah bisa dibuat siapa pun. Pesannya menyebut layar
 * mana yang harus dibereskan, sehingga penolakannya punya jalan keluar.
 */
const pemetaanKurang = computed(() => {
    const kurang = [];
    if (!adaOpsiJenis.value) kurang.push({ apa: 'Jenis Dokumen', layar: 'Master Jenis Dokumen' });
    if (!adaOpsiApqc.value) kurang.push({ apa: 'Proses APQC', layar: 'Master APQC' });

    return kurang;
});

const pesanPemetaanKurang = computed(() => {
    const kurang = pemetaanKurang.value;
    if (kurang.length === 0) return '';

    const daftar = kurang.map((k) => k.apa).join(' dan ');
    const layar = [...new Set(kurang.map((k) => k.layar))].join(' dan ');

    return `Tipe ${formState.Tipe} belum dipetakan ke satu pun ${daftar}, `
        + `jadi nomor resminya tak bisa dirakit dan dokumennya tak boleh dibuat. `
        + `Tambahkan pemetaan untuk ${formState.Tipe} di ${layar} lebih dulu, lalu buka form ini lagi.`;
});

const isStep1Complete = computed(() => {
    return Boolean(
        formState.Judul?.trim() &&
        formState.Tipe &&
        formState.Jenis_Dokumen_Id &&
        formState.APQC_Id
    );
});

const isStep2Complete = computed(() => {
    return Boolean(formState.Entitas_Pemilik_Id && formState.Departemen_Id && formState.Entitas_Lingkup_Id);
});

/*
 * SEGMEN KOSONG ADALAH JARING TERAKHIR.
 *
 * Langkah 1 & 2 sudah menjaga kelima segmen identitas, jadi daftar ini
 * lazimnya sudah kosong ketika admin sampai ke sini. Ia tetap dipasang karena
 * menangkap yang tak terlihat di dua langkah itu: entitas atau departemen yang
 * SUDAH DIPILIH tetapi kode cetaknya kosong di master. Segmennya bolong bukan
 * karena admin lalai memilih, melainkan karena master yang dipilihnya belum
 * punya kode — dan tanpa jaring ini, dokumennya lahir tanpa nomor tanpa ada
 * yang bertanya.
 */
const isStep3Complete = computed(() => {
    return Boolean(
        !props.saranLoading &&
        !props.saranError &&
        props.segmenKosong.length === 0 &&
        formState.No_Dokumen !== '' &&
        formState.No_Dokumen !== null &&
        Number(formState.No_Dokumen) >= 1
    );
});

const canSubmit = computed(() => {
    return Boolean(isStep1Complete.value && isStep2Complete.value && isStep3Complete.value);
});

const canProceedCurrentStep = computed(() => {
    if (stepIndex.value === 0) return isStep1Complete.value;
    if (stepIndex.value === 1) return isStep2Complete.value;
    if (stepIndex.value === 2) return isStep3Complete.value;
    if (stepIndex.value === 3) return canSubmit.value;
    return true;
});

const stepperItems = computed(() => [
    {
        label: '1. Mutu & Media',
        caption: isStep1Complete.value ? 'Lengkap' : 'Wajib Diisi',
        state: isStep1Complete.value ? 'done' : stepIndex.value === 0 ? 'current' : 'wait',
        icon: isStep1Complete.value ? 'check2' : 'diagram-3',
    },
    {
        label: '2. Kepemilikan',
        caption: isStep2Complete.value ? 'Lengkap' : 'Unit & PT',
        state: isStep2Complete.value ? 'done' : stepIndex.value === 1 ? 'current' : 'wait',
        icon: isStep2Complete.value ? 'check2' : 'building',
    },
    {
        label: '3. Penomoran',
        caption: isStep3Complete.value ? 'Sah' : 'Standar Dokumen',
        state: isStep3Complete.value ? 'done' : stepIndex.value === 2 ? 'current' : 'wait',
        icon: isStep3Complete.value ? 'check2' : 'hash',
    },
    {
        label: '4. Ringkasan',
        caption: formState.Deskripsi ? 'Terisi' : 'Review',
        state: isStep3Complete.value && formState.Deskripsi ? 'done' : stepIndex.value === 3 ? 'current' : 'wait',
        icon: 'card-checklist',
    },
]);

const nextStep = () => {
    if (stepIndex.value < 3 && canProceedCurrentStep.value) {
        stepIndex.value += 1;
    }
};

const prevStep = () => {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
    }
};

const handleNextOrSubmit = () => {
    if (stepIndex.value < 3) {
        nextStep();
    } else {
        submit();
    }
};

const parseProcNum = (kode) => {
    const raw = String(kode || '').replace(/\D/g, '');
    return parseInt(raw, 10) || 0;
};

const isOperatingProcess = (kode) => {
    const num = parseProcNum(kode);
    return num >= 1 && num <= 6;
};

const getTipeIcon = (tipe) => {
    const map = {
        DOKUMEN: 'file-earmark-pdf',
        FORM: 'file-earmark-spreadsheet',
        VIDEO: 'play-circle',
        KUIS: 'patch-question',
        TAUTAN: 'link-45deg',
        PARAGRAF: 'text-paragraph',
        ABSENSI: 'calendar-check',
    };
    return map[tipe] || 'file-earmark-text';
};

const selectedJenisObj = computed(() => props.jenisOpsi.find((j) => String(j.Id) === String(formState.Jenis_Dokumen_Id)) || null);
const selectedJenisKode = computed(() => selectedJenisObj.value?.Kode_Jenis_Dokumen || '');

const selectedEntitasObj = computed(() => props.entitasOpsi.find((e) => String(e.Id) === String(formState.Entitas_Pemilik_Id)) || null);
/** Kode IDENTITAS entitas — dipakai badge dan ringkasan, bukan nomor. */
const selectedEntitasKode = computed(() => selectedEntitasObj.value?.Kode_Entitas || '');
/**
 * Kode yang TERCETAK di segmen pemilik.
 *
 * Datang sudah teresolusi dari server (EntitasService::kodePemilik); jatuh ke
 * Kode_Entitas hanya bila server belum mengirimkannya — misalnya halaman yang
 * masih memegang daftar opsi lama dari cache. Aturan jatuh-baliknya TIDAK
 * ditulis ulang di sini: dua salinan satu aturan pasti menyimpang, dan yang
 * menyimpang di sini adalah nomor yang tercetak di dokumen resmi.
 */
const selectedEntitasKodeNomor = computed(
    () => selectedEntitasObj.value?.Kode_Pemilik || selectedEntitasKode.value,
);

/** Apakah entitas ini tercetak berbeda dari kode identitasnya. */
const kodePemilikBeda = (e) => Boolean(e?.Kode_Pemilik && e.Kode_Pemilik !== e.Kode_Entitas);

const selectedLingkupObj = computed(() => props.entitasOpsi.find((e) => String(e.Id) === String(formState.Entitas_Lingkup_Id)) || null);
const selectedLingkupKode = computed(() => selectedLingkupObj.value?.Kode_Entitas || '');

const selectedDeptObj = computed(() => props.departemenOpsi.find((dp) => String(dp.Id) === String(formState.Departemen_Id)) || null);
const selectedDeptKode = computed(() => selectedDeptObj.value?.Kode_Departemen || '');

const selectedApqcObj = computed(() => props.apqcOpsi.find((a) => String(a.Id) === String(formState.APQC_Id)) || null);
const selectedApqcKode = computed(() => selectedApqcObj.value?.Kode_Proses || '');

// No. Dokumen tiga digit — lihat nomorDokumen() di versi.js.
const padNumber = (val) => {
    if (val === null || val === undefined || val === '') return '';
    return nomorDokumen(val);
};

const onDialogUpdate = (val) => {
    if (!val) close();
};

const close = () => {
    emit('close');
};

const onTipeChange = () => {
    emit('tipe-change', formState.Tipe);
    emit('segment-change', { ...formState });
};

const onEntitasPemilikChange = () => {
    if (!formState.Entitas_Lingkup_Id || formState.Entitas_Lingkup_Id === formState.Entitas_Pemilik_Id) {
        formState.Entitas_Lingkup_Id = formState.Entitas_Pemilik_Id;
    }
    emit('segment-change', { ...formState });
};

const onSegmentChange = () => {
    emit('segment-change', { ...formState });
};

const manualNomorDisentuh = ref(false);

/**
 * Nomor urut yang diketik dikabarkan dari NILAI FIELDNYA, bukan dari listener.
 *
 * Sebelumnya field ini memasang `@input="onNoDokumenInput"`. HcisField tidak
 * pernah mengumumkan emit bernama `input` — daftar emitnya hanya
 * update:modelValue/clear/focus/blur — jadi listener itu jatuh ke `$attrs` dan
 * menempel pada elemen <input> di dalamnya sebagai listener DOM biasa.
 * Handlernya menerima InputEvent, bukan angka.
 *
 * Akibatnya `no_dokumen` yang dikirim ke server adalah objek Event yang oleh
 * axios di-JSON-kan menjadi "{}", dan server membacanya sebagai (int) 0.
 * Pratinjau nomor resmi di langkah 3 dan 4 karena itu selalu berakhir "/00/"
 * berapa pun yang diketik — tanpa satu pun galat, karena permintaannya berhasil
 * dan jawabannya sah, hanya menjawab pertanyaan yang salah.
 *
 * Mengamati formState memutus ketergantungan pada nama emit komponen lain:
 * yang dikabarkan adalah nilai yang benar-benar dipegang field.
 */
watch(() => formState.No_Dokumen, (val) => {
    if (props.saranNoDokumen !== null && val !== '' && val !== null && Number(val) !== Number(props.saranNoDokumen)) {
        manualNomorDisentuh.value = true;
    }
    emit('no-dokumen-input', val);
});

// Otomatis terapkan saran nomor jika belum diedit manual pada pembuatan dokumen baru
watch(
    () => props.saranNoDokumen,
    (newVal) => {
        if (!isEditing.value && newVal !== null) {
            if (!manualNomorDisentuh.value || formState.No_Dokumen === '' || formState.No_Dokumen === null) {
                formState.No_Dokumen = newVal;
            }
        }
    },
    { immediate: true },
);

// Saat masuk ke step 3 (Penetapan Nomor Urut), pastikan rekomendasi terpasang jika belum ada nomor kustom
watch(
    () => stepIndex.value,
    (idx) => {
        if (idx === 2 && !isEditing.value && props.saranNoDokumen !== null) {
            if (!manualNomorDisentuh.value || formState.No_Dokumen === '' || formState.No_Dokumen === null) {
                formState.No_Dokumen = props.saranNoDokumen;
            }
        }
    },
);

const applySaranNomor = () => {
    if (props.saranNoDokumen !== null) {
        formState.No_Dokumen = props.saranNoDokumen;
        manualNomorDisentuh.value = false;
        emit('apply-saran', props.saranNoDokumen);
    }
};

const salinNomorPreview = () => {
    if (!props.pratinjauNomorLengkap) return;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(props.pratinjauNomorLengkap);
        } else {
            const el = document.createElement('textarea');
            el.value = props.pratinjauNomorLengkap;
            el.style.position = 'fixed';
            el.style.left = '-9999px';
            document.body.appendChild(el);
            el.focus();
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        hcisMessage.success(`Nomor Dokumen berhasil disalin: ${props.pratinjauNomorLengkap}`);
    } catch (e) {
        hcisMessage.info(props.pratinjauNomorLengkap);
    }
};

const submit = () => {
    if (!canSubmit.value || props.saving) return;
    emit('submit', { ...formState, isEditing: isEditing.value });
};

const syncFromItem = (item) => {
    if (item) {
        manualNomorDisentuh.value = true;
        formState.id = item.Id_SOP_Dokumen || null;
        formState.Judul = item.Judul || '';
        formState.Tipe = item.Tipe || 'DOKUMEN';
        formState.Jenis_Dokumen_Id = item.Jenis_Dokumen_Id || '';
        formState.APQC_Id = item.APQC_Id || '';
        formState.Entitas_Pemilik_Id = item.Entitas_Pemilik_Id || '';
        formState.Departemen_Id = item.Departemen_Id || '';
        formState.Entitas_Lingkup_Id = item.Entitas_Lingkup_Id || item.Entitas_Pemilik_Id || '';
        formState.No_Dokumen = item.No_Dokumen ?? '';
        formState.Deskripsi = item.Deskripsi || '';
    } else {
        manualNomorDisentuh.value = false;
        formState.id = null;
        formState.Judul = '';
        formState.Tipe = 'DOKUMEN';
        formState.Jenis_Dokumen_Id = '';
        formState.APQC_Id = '';
        formState.Entitas_Pemilik_Id = '';
        formState.Departemen_Id = '';
        formState.Entitas_Lingkup_Id = '';
        formState.No_Dokumen = props.saranNoDokumen !== null ? props.saranNoDokumen : '';
        formState.Deskripsi = '';
    }
};

watch(
    () => [props.show, props.item],
    ([isOpen, item], oldVal) => {
        const prevOpen = oldVal ? oldVal[0] : false;
        if (isOpen) {
            if (!prevOpen) {
                stepIndex.value = 0;
            }
            syncFromItem(item);
            emit('segment-change', { ...formState });
        }
    },
    { immediate: true, deep: true },
);
</script>

<style scoped>
.sop-doc-form-wrapper {
    padding: 0.25rem 0;
}

/* Full-Width Centered & Balanced Wizard Stepper */
.wizard-steps-wrapper {
    width: 100%;
}

.wizard-steps-wrapper :deep(.hcis-steps-wrapper) {
    width: 100%;
}

.wizard-steps-wrapper :deep(.hcis-steps__inner-container) {
    width: 100%;
}

.wizard-steps-wrapper :deep(.hcis-steps__pills-flow) {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.wizard-steps-wrapper :deep(.hcis-steps__pill) {
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0.45rem 0.5rem;
    min-width: 0;
    border-radius: var(--hcis-radius-md, 8px);
}

.wizard-steps-wrapper :deep(.hcis-steps__pill-text) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 700;
}

.wizard-steps-wrapper :deep(.hcis-steps__pill-separator) {
    display: none;
}

.wizard-step-pane {
    animation: fadeInStep 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInStep {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hcis-form-label {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin-bottom: 0.25rem;
}

/* Segment Grid Anatomy (7-Column Tile Matrix) */
.segment-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.45rem;
    width: 100%;
}

.segment-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.55rem 0.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    text-align: center;
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
    min-width: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.segment-tile:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px -2px rgba(15, 23, 42, 0.08);
}

.segment-tile.is-active {
    background: linear-gradient(180deg, #f0f7ff 0%, var(--hcis-sky-100) 100%);
    border-color: var(--hcis-sky-300);
}

.segment-tile.is-active .segment-tile-val {
    color: var(--hcis-sky-700);
}

.segment-tile.is-idle {
    background: linear-gradient(180deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
    border-color: var(--hcis-amber-200);
}

.segment-tile.is-idle .segment-tile-val {
    color: var(--hcis-amber-700);
}

.segment-tile.is-highlight {
    background: linear-gradient(180deg, var(--hcis-green-50) 0%, var(--hcis-green-100) 100%);
    border-color: #86efac;
}

.segment-tile.is-highlight .segment-tile-val {
    color: var(--hcis-green-700);
}

.segment-tile.is-version {
    background: linear-gradient(180deg, #faf5ff 0%, var(--hcis-purple-100) 100%);
    border-color: #ddd6fe;
}

.segment-tile.is-version .segment-tile-val {
    color: var(--hcis-violet-700);
}

.segment-tile.is-loading-pulse {
    animation: tilePulse 1.2s ease-in-out infinite;
}

@keyframes tilePulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.55;
    }
}

.segment-tile-lbl {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500);
    margin-bottom: 0.2rem;
    text-transform: uppercase;
}

.segment-tile-val {
    font-size: 0.9375rem;
    font-weight: 850;
    line-height: 1.15;
}

.segment-tile-sub {
    font-size: 0.625rem;
    color: var(--hcis-slate-400);
    margin-top: 0.2rem;
    font-weight: 600;
}

@media (max-width: 991.98px) {
    .segment-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .segment-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* Number Preview Box */
.nomor-preview-box {
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    transition: all 200ms ease;
}

.nomor-preview-box.is-complete {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.8) 0%, var(--hcis-white) 100%);
    border-color: #86efac;
    box-shadow: 0 4px 12px -2px rgba(34, 197, 94, 0.08);
}

.nomor-preview-box.is-incomplete {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.8) 0%, var(--hcis-white) 100%);
    border-color: var(--hcis-red-300);
}

.preview-label {
    font-size: 0.68rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500);
    display: block;
    margin-bottom: 0.2rem;
}

.preview-number {
    font-size: 1.0625rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    word-break: break-all;
}

/* ════ Sleek Sequence Recommendation Strip (Step 3) ════ */
.seq-rec-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    min-height: 2.375rem;
    transition: all 180ms ease;
}

.seq-rec-card.is-loading {
    background: var(--hcis-slate-50);
    border-style: dashed;
    gap: 0.5rem;
}

.seq-rec-card.is-error {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-300);
    gap: 0.5rem;
}

.seq-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    line-height: 1.2;
}

.seq-chip--prev {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-300);
}

.seq-chip--target {
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #93c5fd;
}

/* ════ Passport Container & Styles (Step 4) ════ */
.passport-container {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--hcis-slate-100);
}

.passport-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.passport-header-title {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-600);
}

.passport-hero {
    padding: 0.9rem 1.1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid var(--hcis-slate-300);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
}

.passport-hero-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.passport-hero-kicker {
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500);
}

.passport-hero-number {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-blue-600);
    margin-bottom: 0.25rem;
    word-break: break-all;
}

.passport-hero-title {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    line-height: 1.35;
}

.passport-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

@media (max-width: 767.98px) {
    .passport-grid {
        grid-template-columns: 1fr;
    }
}

.passport-tile {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.passport-tile-lbl {
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
}

.passport-tile-val {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    line-height: 1.35;
    word-break: break-word;
}

.passport-scope-pill {
    display: inline-block;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
}

.passport-scope-pill.is-internal {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
}

.passport-scope-pill.is-cross {
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-700);
    border: 1px solid #ddd6fe;
}
</style>
