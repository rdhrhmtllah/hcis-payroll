<template>
    <!-- ═══════════════════════════════════════════════════════════════════
         0. KARTU NFC BELUM SIAP — FORM TIDAK DITAMPILKAN SAMA SEKALI
         Server (store) menolak dengan alasan yang sama; panel ini hanya
         memberi tahu lebih awal supaya pemohon tidak mengisi form sia-sia.
         ═══════════════════════════════════════════════════════════════════ -->
    <div v-if="kartuNfcTerkunci" class="permit-kartu-nfc-blokir" data-testid="permit-kartu-nfc-blokir">
        <HcisResultState
            status="warning"
            variant="card"
            size="md"
            kicker="Visitor · ID Card"
            :title="judulKartuNfc"
            :description="kartuNfcAktif.pesan"
            :show-retry="false"
        >
            <template #actions>
                <HcisButton
                    variant="secondary"
                    leading-icon="credit-card-2-front"
                    label="Lihat Pass Digital"
                    @click="$emit('navigate-to-cards')"
                />
            </template>
        </HcisResultState>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         1. LOADING SKELETON STATE (DISPLAYED WHILE FETCHING MASTER DATA)
         ═══════════════════════════════════════════════════════════════════ -->
    <div v-else-if="masterLoading" class="visitor-permit-form-skeleton">
        <div class="visitor-permit-form__layout">
            <div class="visitor-permit-form__sections">
                <!-- Stepper Skeleton -->
                <div class="permit-wizard-stepper-skeleton mb-3">
                    <HcisSkeleton width="100%" height="48px" rounded="lg" />
                </div>

                <!-- Active Card Skeleton -->
                <HcisCard
                    title="Identitas kendaraan"
                    description="Memuat master data spesifikasi fisik kendaraan..."
                    icon="car-front-fill"
                    sentiment="brand"
                    variant="glass"
                    size="lg"
                    divider
                >
                    <div class="visitor-vehicle-grid">
                        <div class="visitor-vehicle-grid__plate">
                            <HcisSkeleton width="100%" height="42px" rounded="md" />
                        </div>
                        <div class="visitor-vehicle-grid__year">
                            <HcisSkeleton width="100%" height="42px" rounded="md" />
                        </div>
                        <div class="visitor-vehicle-grid__type">
                            <HcisSkeleton width="100%" height="42px" rounded="md" />
                        </div>
                        <div class="visitor-vehicle-grid__model">
                            <HcisSkeleton width="100%" height="42px" rounded="md" />
                        </div>
                        <div class="visitor-vehicle-grid__color">
                            <HcisSkeleton width="100%" height="42px" rounded="md" />
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- Summary Sidebar Skeleton -->
            <aside class="visitor-permit-form__summary">
                <HcisCard
                    title="Kesiapan pengajuan"
                    description="Memeriksa kelengkapan..."
                    icon="clipboard2-check-fill"
                    sentiment="brand"
                    variant="glass"
                    size="md"
                    divider
                >
                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <HcisSkeleton width="100%" height="32px" rounded="md" />
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <HcisSkeleton v-for="i in 6" :key="i" width="100%" height="18px" rounded="sm" />
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <HcisSkeleton v-for="i in 3" :key="i" width="100%" height="42px" rounded="md" />
                        </div>
                    </div>
                    <template #footer>
                        <HcisSkeleton width="100%" height="42px" rounded="md" />
                    </template>
                </HcisCard>
            </aside>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         2. ACTIVE FORM VIEW (WHEN MASTER DATA READY)
         ═══════════════════════════════════════════════════════════════════ -->
    <form v-else class="visitor-permit-form" novalidate @submit.prevent="submitForm">
        <!-- QUOTA REACHED COMPACT HERO BANNER -->
        <div v-if="kuotaHabis" class="permit-quota-hero-banner mb-3">
            <div class="permit-quota-hero-inner">
                <div class="permit-quota-content-row">
                    <div class="permit-quota-icon-badge">
                        <HcisIcon name="shield-lock-fill" size="md" />
                    </div>
                    <div class="permit-quota-text-body">
                        <div class="permit-quota-header-row">
                            <h3 class="permit-quota-hero-title">
                                {{ blokirKuota.judul }}
                            </h3>
                            <HcisBadge
                                :label="blokirKuota.lencana"
                                variant="danger"
                                size="xs"
                                appearance="solid"
                            />
                        </div>
                        <p class="permit-quota-hero-desc">
                            {{ blokirKuota.pesan }}
                        </p>
                    </div>
                    <div class="permit-quota-actions-row">
                        <HcisButton
                            label="Lihat Pass"
                            variant="primary"
                            size="xs"
                            leading-icon="person-vcard-fill"
                            class="permit-quota-btn"
                            @click="$emit('navigate-to-cards')"
                        />
                        <HcisButton
                            label="Riwayat"
                            variant="secondary"
                            size="xs"
                            leading-icon="clock-history"
                            class="permit-quota-btn"
                            @click="$emit('navigate-to-status')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <HcisAlert
            v-else-if="isSubmitBlocked"
            variant="warning"
            appearance="glass"
            title="Pengajuan belum dapat dikirim"
            :description="alasanTerkunci"
            icon="person-exclamation"
            class="mb-4"
        />

        <div class="visitor-permit-form__layout">
            <fieldset :disabled="isFormDisabled" class="visitor-permit-fieldset">
                <div class="visitor-permit-form__sections" :class="{ 'is-disabled': isFormDisabled }">
                    <!-- Navigasi langkah wizard: komponen resmi HcisSteps (design system).
                         Klik/Enter/Spasi pada langkah diteruskan ke goToStep, yang tetap
                         menjadi penjaga validasi maju-mundur. -->
                    <div class="permit-wizard-stepper-wrap mb-3">
                        <HcisSteps
                            :steps="langkahWizard"
                            :current="currentStep"
                            size="sm"
                            clickable
                            allow-all-clickable
                            class="permit-wizard-steps"
                            aria-label="Alur Pengisian Permit Kendaraan"
                            @change="({ index }) => goToStep(index)"
                        >
                            <template #marker="{ step, state }">
                                <HcisIcon v-if="state === 'done'" name="check-lg" size="xs" />
                                <HcisIcon v-else :name="step.icon" size="xs" />
                            </template>
                            <template #label="{ step }">
                                <span class="permit-step-label-full d-none d-sm-inline">{{ step.label }}</span>
                                <span class="permit-step-label-mobile d-inline d-sm-none">{{ step.shortLabel || step.label }}</span>
                            </template>
                        </HcisSteps>

                        <!-- Mobile Compact Status Indicator -->
                        <div class="permit-wizard-mobile-status d-flex d-sm-none">
                            <div class="permit-wizard-mobile-status__left">
                                <span class="permit-wizard-mobile-badge">Tahap {{ currentStep + 1 }}/3</span>
                                <span class="permit-wizard-mobile-desc">{{ wizardSteps[currentStep]?.subtitle }}</span>
                            </div>
                            <span
                                class="permit-wizard-mobile-pill"
                                :class="isCurrentStepValid ? 'is-valid' : 'is-filling'"
                            >
                                <HcisIcon :name="isCurrentStepValid ? 'check-circle-fill' : 'pencil-fill'" size="2xs" />
                                <span>{{ isCurrentStepValid ? 'Lengkap' : 'Sedang Diisi' }}</span>
                            </span>
                        </div>
                    </div>

<!-- ═══════════════════════════════════════════════════════════════════
     STEP 0 (LANGKAH 1): IDENTITAS & BERKAS DOKUMEN KENDARAAN
     ═══════════════════════════════════════════════════════════════════ -->
<div v-show="currentStep === 0" class="permit-wizard-step-pane">
    <HcisCard
        title="Identitas &amp; Berkas Kendaraan"
        kicker="Tahap 1 dari 3 · Spesifikasi & Lampiran"
        description="Lengkapi nomor plat, jenis, model, warna, dan unggah berkas STNK, SIM, serta foto fisik kendaraan."
        icon="car-front-fill"
        sentiment="brand"
        variant="glass"
        size="lg"
        divider
    >
        <template v-if="isFormDisabled && kuotaHabis" #actions>
            <span class="permit-card-locked-tag" title="Formulir Terkunci">
                <HcisIcon name="lock-fill" size="2xs" />
                <span class="permit-card-locked-tag__text">Terkunci</span>
            </span>
        </template>

        <div class="permit-step-grid">
            <!-- Section A: Spesifikasi Fisik Kendaraan -->
            <div class="permit-section-divider-title">
                <HcisIcon name="car-front-fill" size="2xs" class="text-indigo-600" />
                <span>Spesifikasi Fisik Kendaraan</span>
            </div>

            <!-- Row 1: Nomor Plat (1.5fr) & Tahun Kendaraan (0.8fr) -->
            <div class="permit-form-row two-cols-plate">
                <div class="permit-form-col">
                    <HcisPlateInput
                        id="permit-plat-number"
                        v-model="form.no_plat"
                        :prefix-options="master.plat_codes"
                        label="Nomor Plat Kendaraan"
                        hint="Contoh: B 1234 XYZ (masukkan nomor plat lengkap)."
                        :disabled="isFormDisabled"
                        required
                    />
                </div>
                <div class="permit-form-col">
                    <HcisField id="permit-vehicle-year" label="Tahun Pembuatan" required :disabled="isFormDisabled">
                        <HcisInputNumber
                            v-model="form.tahun_kendaraan"
                            :min="1990"
                            :max="currentYear"
                            :disabled="isFormDisabled"
                            controls-position="right"
                            prefix="Tahun"
                            align="center"
                        />
                    </HcisField>
                </div>
            </div>

            <!-- Row 2: Jenis Kendaraan (1fr) + Merek/Model (1fr) + Warna Kendaraan (1fr) -->
            <div class="permit-form-row three-cols">
                <div class="permit-form-col">
                    <HcisField id="permit-vehicle-type" label="Jenis Kendaraan" required :disabled="isFormDisabled">
                        <HcisSelect
                            id="permit-vehicle-type"
                            v-model="form.id_vehicle_type"
                            placeholder="Pilih jenis"
                            leading-icon="car-front"
                            aria-label="Jenis kendaraan"
                            :disabled="isFormDisabled"
                        >
                            <HcisSelectOption
                                v-for="type in master.vehicle_types"
                                :key="type.Id"
                                :value="type.Id"
                                :label="type.Nama_Vehicle_Type"
                                :icon="getVehicleIcon(type.Nama_Vehicle_Type)"
                            />
                        </HcisSelect>
                    </HcisField>
                </div>

                <div class="permit-form-col">
                    <HcisField
                        id="permit-vehicle-model"
                        label="Merek atau Model"
                        :hint="!form.id_vehicle_type ? 'Pilih jenis terlebih dahulu' : ''"
                        required
                        :disabled="isFormDisabled || !form.id_vehicle_type"
                    >
                        <HcisSelect
                            id="permit-vehicle-model"
                            v-model="form.id_vehicle_model"
                            placeholder="Pilih model"
                            leading-icon="tags-fill"
                            aria-label="Merek atau model kendaraan"
                            :disabled="isFormDisabled || !form.id_vehicle_type"
                        >
                            <HcisSelectOption
                                v-for="model in filteredModels"
                                :key="model.Id"
                                :value="model.Id"
                                :label="model.Nama_Model_Kendaraan"
                            />
                        </HcisSelect>
                    </HcisField>
                </div>

                <div class="permit-form-col">
                    <HcisField id="permit-vehicle-color" label="Warna Kendaraan" required :disabled="isFormDisabled">
                        <HcisSelect
                            id="permit-vehicle-color"
                            v-model="form.id_vehicle_color"
                            placeholder="Pilih warna"
                            leading-icon="palette-fill"
                            selected-icon="circle-fill"
                            :selected-icon-color="selectedVehicleColorSwatch"
                            aria-label="Warna kendaraan"
                            :disabled="isFormDisabled"
                        >
                            <HcisSelectOption
                                v-for="color in master.colors"
                                :key="color.Id"
                                :value="color.Id"
                                :label="color.Nama_Warna"
                                icon="circle-fill"
                                :icon-color="getVehicleColorSwatch(color)"
                            />
                        </HcisSelect>
                    </HcisField>
                </div>
            </div>

            <!-- Section B: Dokumen & Berkas Verifikasi -->
            <div class="permit-section-divider-title mt-2">
                <HcisIcon name="folder-check" size="2xs" class="text-teal-600" />
                <span>Dokumen &amp; Berkas Verifikasi</span>
            </div>

            <!-- Row 3: Uploads Grid -->
            <div class="permit-upload-grid">
                <HcisUpload
                    :model-value="uploadLists.foto_kendaraan"
                    label="Foto Kendaraan (Tampak Depan)"
                    hint="Tampak depan jelas & plat nomor terbaca · Format JPG, PNG (Maks 5 MB)"
                    accept="image/jpeg,image/png,image/jpg,.jpg,.jpeg,.png"
                    :limit="1"
                    :max-size="BATAS_PILIH_MB"
                    drag
                    camera
                    icon="car-front-fill"
                    sentiment="brand"
                    variant="glass"
                    :disabled="isFormDisabled"
                    @update:model-value="updateUpload('foto_kendaraan', $event)"
                />
                <HcisUpload
                    :model-value="uploadLists.foto_stnk"
                    label="Dokumen STNK"
                    hint="STNK Asli (JPG, PNG, PDF) · Maks 5 MB"
                    accept="image/jpeg,image/png,image/jpg,application/pdf,.jpg,.jpeg,.png,.pdf"
                    :limit="1"
                    :max-size="BATAS_PILIH_MB"
                    drag
                    camera
                    icon="file-earmark-richtext-fill"
                    sentiment="teal"
                    variant="glass"
                    :disabled="isFormDisabled"
                    @update:model-value="updateUpload('foto_stnk', $event)"
                />
                <HcisUpload
                    :model-value="uploadLists.foto_sim"
                    label="Dokumen SIM Pengemudi"
                    hint="SIM aktif sesuai jenis kendaraan · Format JPG, PNG (Maks 5 MB)"
                    accept="image/jpeg,image/png,image/jpg,.jpg,.jpeg,.png"
                    :limit="1"
                    :max-size="BATAS_PILIH_MB"
                    drag
                    camera
                    icon="person-vcard-fill"
                    sentiment="purple"
                    variant="glass"
                    :disabled="isFormDisabled"
                    @update:model-value="updateUpload('foto_sim', $event)"
                />
            </div>
        </div>

        <template #footer>
            <div class="permit-wizard-step-footer">
                <div class="permit-wizard-step-footer__left">
                    <HcisButton
                        v-if="!kuotaHabis"
                        label="Reset Form"
                        leading-icon="arrow-counterclockwise"
                        variant="secondary"
                        appearance="subtle"
                        size="sm"
                        :disabled="isSubmitting"
                        @click="resetForm"
                    />
                </div>
                <div class="permit-wizard-step-footer__right">
                    <HcisButton
                        label="Lanjut: Kategori Permit"
                        variant="primary"
                        size="md"
                        trailing-icon="arrow-right"
                        :disabled="!step0Valid || isFormDisabled"
                        @click="nextStep"
                    />
                </div>
            </div>
        </template>
    </HcisCard>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     STEP 1 (LANGKAH 2): JENIS PERMIT & KETERANGAN
     ═══════════════════════════════════════════════════════════════════ -->
<div v-show="currentStep === 1" class="permit-wizard-step-pane">
    <HcisCard
        title="Kategori Permit Kendaraan"
        kicker="Tahap 2 dari 3 · Kategori Akses"
        description="Pilih kategori hak akses kendaraan dan isi keterangan atau alasan pengajuan jika diperlukan."
        icon="shield-check"
        sentiment="purple"
        variant="glass"
        size="lg"
        divider
    >
        <template v-if="isFormDisabled && kuotaHabis" #actions>
            <span class="permit-card-locked-tag" title="Formulir Terkunci">
                <HcisIcon name="lock-fill" size="2xs" />
                <span class="permit-card-locked-tag__text">Terkunci</span>
            </span>
        </template>

        <div class="permit-step-grid">
            <HcisField id="permit-category" label="Kategori Permit Kendaraan" required :disabled="isFormDisabled">
                <div class="permit-category-picker">
                    <HcisRadio
                        v-model="form.id_kategori_permit"
                        name="permit-category"
                        :options="categoryOptions"
                        variant="card"
                        direction="horizontal"
                        size="lg"
                        :disabled="isFormDisabled"
                    />
                </div>
            </HcisField>

            <HcisTextarea
                id="permit-note"
                v-model="form.keterangan_pengajuan"
                label="Keterangan atau Alasan Pengajuan"
                placeholder="Tuliskan alasan pengajuan atau kebutuhan akses khusus jika diperlukan..."
                hint="Opsional, maksimal 500 karakter."
                :rows="3"
                :maxlength="500"
                :disabled="isFormDisabled"
                clearable
            />
        </div>

        <template #footer>
            <div class="permit-wizard-step-footer">
                <div class="permit-wizard-step-footer__left">
                    <HcisButton
                        label="Sebelumnya"
                        leading-icon="arrow-left"
                        variant="secondary"
                        size="md"
                        :disabled="isSubmitting"
                        @click="prevStep"
                    />
                </div>
                <div class="permit-wizard-step-footer__right">
                    <HcisButton
                        label="Lanjut: Jadwal Inspeksi SHE"
                        variant="primary"
                        size="md"
                        trailing-icon="arrow-right"
                        :disabled="!step1Valid || isFormDisabled"
                        @click="nextStep"
                    />
                </div>
            </div>
        </template>
    </HcisCard>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     STEP 2 (LANGKAH 3): JADWAL INSPEKSI SHE & FINALISASI
     ═══════════════════════════════════════════════════════════════════ -->
<div v-show="currentStep === 2" class="permit-wizard-step-pane">
    <HcisCard
        title="Jadwal Inspeksi SHE &amp; Finalisasi"
        kicker="Tahap 3 dari 3 · Jadwal SHE & Verifikasi Akhir"
        description="Pilih slot tanggal dan waktu pemeriksaan fisik kendaraan oleh tim SHE, lalu periksa ringkasan sebelum mengajukan permit."
        icon="calendar-check-fill"
        sentiment="brand"
        variant="glass"
        size="lg"
        divider
    >
        <div class="permit-step-grid">
            <HcisAlert
                v-if="slotError"
                variant="danger"
                :title="slotError"
                class="permit-slot-alert"
            >
                <template #actions>
                    <HcisButton size="sm" variant="secondary" leading-icon="arrow-clockwise" label="Muat ulang slot" @click="fetchSlotInspeksi" />
                </template>
            </HcisAlert>

            <!-- 1. LIVE INSPECTION SLOT PICKER -->
            <SlotInspeksiPicker
                v-model="form.inspeksi"
                :slots="slotInspeksi"
                :loading="slotLoading"
                :disabled="isFormDisabled"
            />

            <!-- 2. OFFICIAL SHE RULES & SAFETY PROTOCOL PASS
                 HcisCollapse (design system), bukan tombol buatan sendiri (30 Sep 2026). -->
            <HcisCollapse
                class="permit-she-rules-collapse"
                :model-value="isSheRulesExpanded ? ['she'] : []"
                @update:model-value="(v) => (isSheRulesExpanded = v.includes('she'))"
            >
                <HcisCollapseItem
                    name="she"
                    icon="shield-fill-check"
                    title="Ketentuan & Tata Tertib Pemeriksaan SHE"
                    description="Patuhi standar keselamatan dan batas kehadiran saat inspeksi fisik"
                    badge="Prosedur Wajib"
                >
                        <div class="permit-she-policy-grid">
                            <div class="permit-she-policy-item">
                                <div class="permit-she-policy-item__icon is-indigo">
                                    <HcisIcon name="arrow-repeat" size="xs" />
                                </div>
                                <div class="permit-she-policy-item__text">
                                    <strong class="policy-title">Reschedule Mandiri</strong>
                                    <p class="policy-desc">
                                        Pengubahan jadwal <strong>Maks. H-1</strong> (Maksimal <strong>{{ aturanInspeksi.maks_jadwal_ulang ?? 2 }}x</strong> pengubahan).
                                    </p>
                                </div>
                            </div>

                            <div class="permit-she-policy-item">
                                <div class="permit-she-policy-item__icon is-amber">
                                    <HcisIcon name="person-x-fill" size="xs" />
                                </div>
                                <div class="permit-she-policy-item__text">
                                    <strong class="policy-title">Batas Kehadiran</strong>
                                    <p class="policy-desc">
                                        Tidak hadir <strong>{{ aturanInspeksi.maks_tidak_hadir ?? 2 }}x</strong> berturut-turut membuat pengajuan permit gugur.
                                    </p>
                                </div>
                            </div>

                            <div class="permit-she-policy-item">
                                <div class="permit-she-policy-item__icon is-emerald">
                                    <HcisIcon name="file-earmark-check-fill" size="xs" />
                                </div>
                                <div class="permit-she-policy-item__text">
                                    <strong class="policy-title">Unit &amp; Berkas Fisik</strong>
                                    <p class="policy-desc">
                                        Wajib membawa unit kendaraan asli bersama dokumen STNK &amp; SIM fisik ke pos SHE.
                                    </p>
                                </div>
                            </div>
                        </div>
                </HcisCollapseItem>
            </HcisCollapse>

            <!-- 3. EXECUTIVE BOARDING TICKET REVIEW CARD -->
            <HcisCollapse
                class="permit-executive-review-collapse"
                :model-value="isReviewCardExpanded ? ['ringkasan'] : []"
                @update:model-value="(v) => (isReviewCardExpanded = v.includes('ringkasan'))"
            >
                <HcisCollapseItem
                    name="ringkasan"
                    icon="card-checklist"
                    title="Ringkasan Berkas Permohonan Permit"
                    description="Periksa kesesuaian identitas, lampiran, dan jadwal sebelum konfirmasi akhir"
                >
                    <template #badge>
                        <HcisBadge
                            :label="isFormValid ? 'Siap Diajukan' : 'Lengkapi Data'"
                            :variant="isFormValid ? 'success' : 'warning'"
                            size="xs"
                            appearance="solid"
                        />
                    </template>
                        <div class="permit-executive-review-body">
                            <!-- Column 1: Identitas Fisik Kendaraan -->
                            <div class="permit-review-col">
                                <div class="permit-review-col-header">
                                    <HcisIcon name="car-front-fill" size="2xs" class="text-indigo-600" />
                                    <span>Identitas Kendaraan</span>
                                </div>
                                <div class="permit-review-col-content">
                                    <div class="permit-review-plate-badge font-tabular">
                                        {{ computedNoPlat || '-' }}
                                    </div>
                                    <div class="permit-review-spec-line">
                                        <span class="spec-main">{{ selectedVehicleTypeName }} · {{ selectedModelName }}</span>
                                        <span class="spec-sub text-slate-500">Tahun {{ form.tahun_kendaraan }}</span>
                                    </div>
                                    <div class="permit-review-color-chip">
                                        <span class="permit-review-color-dot" :style="{ backgroundColor: selectedVehicleColorSwatch }" />
                                        <span>{{ selectedColorName }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 2: Kategori Permit & Dokumen Lampiran -->
                            <div class="permit-review-col">
                                <div class="permit-review-col-header">
                                    <HcisIcon name="shield-check" size="2xs" class="text-indigo-600" />
                                    <span>Kategori &amp; Dokumen</span>
                                </div>
                                <div class="permit-review-col-content">
                                    <div class="permit-review-category-badge">
                                        <HcisBadge
                                            :label="selectedCategoryName"
                                            variant="brand"
                                            size="sm"
                                            appearance="subtle"
                                        />
                                    </div>
                                    <div class="permit-review-docs-list">
                                        <span class="permit-review-doc-pill" :class="{ 'is-attached': Boolean(files.foto_kendaraan) }">
                                            <HcisIcon :name="files.foto_kendaraan ? 'check-circle-fill' : 'circle'" size="2xs" />
                                            <span>Foto Unit</span>
                                        </span>
                                        <span class="permit-review-doc-pill" :class="{ 'is-attached': Boolean(files.foto_stnk) }">
                                            <HcisIcon :name="files.foto_stnk ? 'check-circle-fill' : 'circle'" size="2xs" />
                                            <span>STNK</span>
                                        </span>
                                        <span class="permit-review-doc-pill" :class="{ 'is-attached': Boolean(files.foto_sim) }">
                                            <HcisIcon :name="files.foto_sim ? 'check-circle-fill' : 'circle'" size="2xs" />
                                            <span>SIM</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 3: Tiket Jadwal Inspeksi SHE -->
                            <div class="permit-review-col permit-review-col--schedule">
                                <div class="permit-review-col-header">
                                    <HcisIcon name="calendar2-check-fill" size="2xs" class="text-indigo-600" />
                                    <span>Jadwal Inspeksi Fisik</span>
                                </div>
                                <div class="permit-review-col-content">
                                    <div v-if="form.inspeksi" class="permit-review-ticket">
                                        <div class="permit-review-ticket-date font-tabular">
                                            <HcisIcon name="calendar-event" size="2xs" class="text-indigo-600" />
                                            <strong>{{ formatTanggalTiket(form.inspeksi.mulai) }}</strong>
                                        </div>
                                        <div class="permit-review-ticket-time font-tabular">
                                            <HcisIcon name="clock-fill" size="2xs" class="text-indigo-600" />
                                            <span>{{ jam(form.inspeksi.mulai) }} – {{ jam(form.inspeksi.selesai) }} WIB</span>
                                        </div>
                                        <div class="permit-review-ticket-pos">
                                            <HcisIcon name="geo-alt-fill" size="2xs" class="text-indigo-600" />
                                            <span>{{ form.inspeksi.pos }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="permit-review-ticket-empty">
                                        <HcisIcon name="exclamation-circle" size="xs" class="text-amber-500" />
                                        <span>Pilih tanggal &amp; slot jam di atas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                </HcisCollapseItem>
            </HcisCollapse>

            <!-- 4. FINAL COMMITMENT & STATEMENT ACCEPTANCE -->
            <HcisCheckbox
                v-model="form.statement_accepted"
                variant="card"
                color="brand"
                label="Saya menyatakan data yang diisi adalah benar dan valid."
                description="Saya bertanggung jawab penuh atas kendaraan, dokumen, serta siap mematuhi peraturan keselamatan (SHE) di area perusahaan."
                :disabled="isFormDisabled"
            />
        </div>

        <template #footer>
            <div class="permit-wizard-step-footer">
                <div class="permit-wizard-step-footer__left">
                    <HcisButton
                        label="Sebelumnya"
                        leading-icon="arrow-left"
                        variant="secondary"
                        size="md"
                        :disabled="isSubmitting"
                        @click="prevStep"
                    />
                </div>
                <div class="permit-wizard-step-footer__right">
                    <HcisButton
                        :label="submitButtonLabel"
                        :variant="kuotaHabis ? 'secondary' : 'primary'"
                        size="md"
                        :leading-icon="submitButtonIcon"
                        :loading="isSubmitting"
                        :disabled="!isFormValid || isSubmitBlocked"
                        class="permit-submit-btn"
                        @click="openConfirmModal"
                    />
                </div>
            </div>
        </template>
    </HcisCard>
</div>
                </div>
            </fieldset>

            <aside class="visitor-permit-form__summary" aria-label="Ringkasan kesiapan pengajuan">
                <HcisCard
                    title="Kesiapan pengajuan"
                    :kicker="`Tahap ${currentStep + 1} dari 3`"
                    description="Periksa kelengkapan sebelum mengirim permit."
                    icon="clipboard2-check-fill"
                    :sentiment="kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')"
                    pattern="grid"
                    variant="glass"
                    size="md"
                    divider
                >
                    <template v-if="kuotaHabis" #actions>
                        <HcisBadge label="Batas Maksimal" variant="danger" size="xs" appearance="subtle" />
                    </template>
                    <template v-else #actions>
                        <HcisBadge
                            :label="`Tahap ${currentStep + 1}/3`"
                            :variant="isCurrentStepValid ? 'success' : 'brand'"
                            size="xs"
                            appearance="subtle"
                        />
                    </template>

                    <HcisProgress
                        :value="kuotaHabis ? 100 : completionPercent"
                        :sentiment="kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')"
                        :label="kuotaHabis ? 'Status Jatah Kuota' : 'Kelengkapan wajib'"
                        :subtitle="kuotaHabis ? blokirKuota.ringkas : `${completedRequiredCount} dari ${completionItems.length} bagian lengkap`"
                        size="sm"
                        glow
                    />

                    <!-- Interactive Step Checklist -->
                    <ul class="visitor-completion-list">
                        <li
                            v-for="item in completionItems"
                            :key="item.label"
                            class="visitor-completion-item"
                            :class="{
                                'is-complete': !kuotaHabis && item.complete,
                                'is-active': currentStep === item.stepIndex,
                                'is-clickable': !isFormDisabled
                            }"
                            role="button"
                            tabindex="0"
                            :title="`Buka Tahap ${item.stepIndex + 1}: ${item.label}`"
                            @click="goToStep(item.stepIndex)"
                            @keydown.enter.space.prevent="goToStep(item.stepIndex)"
                        >
                            <HcisIcon
                                :name="kuotaHabis ? 'dash-circle' : (item.complete ? 'check-circle-fill' : (currentStep === item.stepIndex ? 'record-circle-fill' : 'circle'))"
                                size="xs"
                            />
                            <span class="visitor-completion-label">{{ item.label }}</span>
                            <span v-if="currentStep === item.stepIndex" class="visitor-completion-pill">Aktif</span>
                        </li>
                    </ul>

                    <div class="visitor-summary-data">
                        <div>
                            <span>Nomor plat</span>
                            <strong class="font-tabular">{{ computedNoPlat || 'Belum lengkap' }}</strong>
                        </div>
                        <div>
                            <span>Kendaraan</span>
                            <strong>{{ selectedModelName }}</strong>
                        </div>
                        <div>
                            <span>Kategori</span>
                            <strong>{{ selectedCategoryName }}</strong>
                        </div>
                        <div v-if="form.inspeksi">
                            <span>Jadwal Inspeksi</span>
                            <strong class="text-xs text-indigo-700 font-tabular">{{ formatJadwalInspeksi(form.inspeksi) }}</strong>
                        </div>
                    </div>
                    <template #footer>
                        <div class="visitor-permit-summary-footer">
                            <!-- Clean Quota Badge Box -->
                            <div v-if="ringkasanKuota" class="permit-kuota-badge-box" :class="{ 'is-full': kuotaHabis }">
                                <HcisIcon :name="kuotaHabis ? 'shield-lock-fill' : 'shield-check'" size="xs" :class="kuotaHabis ? 'text-danger' : 'text-success'" />
                                <span>{{ ringkasanKuota }}</span>
                            </div>

                            <!-- Dynamic Wizard Action in Sidebar -->
                            <div class="visitor-form-actions">
                                <HcisButton
                                    v-if="currentStep < 2"
                                    :label="nextStepLabel"
                                    variant="primary"
                                    size="md"
                                    trailing-icon="arrow-right"
                                    :disabled="!isCurrentStepValid || isFormDisabled"
                                    class="permit-submit-btn w-100"
                                    @click="nextStep"
                                />
                                <HcisButton
                                    v-else
                                    :label="submitButtonLabel"
                                    :variant="kuotaHabis ? 'secondary' : 'primary'"
                                    size="md"
                                    :leading-icon="submitButtonIcon"
                                    :loading="isSubmitting"
                                    :disabled="!isFormValid || isSubmitBlocked"
                                    class="permit-submit-btn w-100"
                                    @click="openConfirmModal"
                                />
                                <HcisButton
                                    v-if="currentStep > 0"
                                    label="Langkah Sebelumnya"
                                    leading-icon="arrow-left"
                                    variant="secondary"
                                    appearance="subtle"
                                    size="sm"
                                    :disabled="isSubmitting"
                                    class="permit-prev-btn w-100"
                                    @click="prevStep"
                                />
                            </div>
                        </div>
                    </template>
                </HcisCard>
            </aside>
        </div>

        <!-- Fixed Mobile Floating Bottom Dock (Teleported to body for true viewport sticking) -->
        <Teleport to="body">
            <div class="visitor-permit-mobile-dock" aria-label="Aksi Pengajuan Mobile">
                <!-- Top ambient progress hairline -->
                <div class="visitor-permit-mobile-dock__progress-track" aria-hidden="true">
                    <div
                        class="visitor-permit-mobile-dock__progress-fill"
                        :class="`is-${kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')}`"
                        :style="{ width: `${kuotaHabis ? 100 : completionPercent}%` }"
                    />
                </div>

                <div
                    class="visitor-permit-mobile-dock__info"
                    role="button"
                    tabindex="0"
                    title="Buka Lembar Rincian Kesiapan"
                    @click="showReadinessModal = true"
                    @keydown.enter.space.prevent="showReadinessModal = true"
                >
                    <div class="visitor-permit-mobile-dock__progress-badge" :class="`is-${kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')}`">
                        <HcisIcon :name="kuotaHabis ? 'shield-lock-fill' : (isFormValid ? 'check-circle-fill' : 'hourglass-split')" size="xs" />
                        <span>{{ kuotaHabis ? 'Penuh' : `Tahap ${currentStep + 1}/3` }}</span>
                    </div>
                    <div class="visitor-permit-mobile-dock__text">
                        <div class="visitor-permit-mobile-dock__title">
                            {{ kuotaHabis ? blokirKuota.lencana : (isFormValid ? 'Siap Dikirim' : `${completedRequiredCount}/${completionItems.length} Syarat`) }}
                        </div>
                        <div class="visitor-permit-mobile-dock__sub">
                            <span>Rincian &amp; Navigasi</span>
                            <HcisIcon name="chevron-up" size="2xs" />
                        </div>
                    </div>
                </div>

                <div class="visitor-permit-mobile-dock__action">
                    <HcisButton
                        v-if="currentStep < 2"
                        :label="`Lanjut: Tahap ${currentStep + 2}`"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        :disabled="!isCurrentStepValid || isFormDisabled"
                        class="permit-mobile-submit-btn"
                        @click="nextStep"
                    />
                    <HcisButton
                        v-else
                        :label="submitButtonLabel"
                        :variant="kuotaHabis ? 'secondary' : 'primary'"
                        size="sm"
                        :leading-icon="submitButtonIcon"
                        :loading="isSubmitting"
                        :disabled="!isFormValid || isSubmitBlocked"
                        class="permit-mobile-submit-btn"
                        @click="openConfirmModal"
                    />
                </div>
            </div>
        </Teleport>
    </form>

    <!-- Mobile Bottom Sheet Drawer for Kesiapan Pengajuan & Step Navigation -->
    <HcisDialog
        v-model="showReadinessModal"
        title="Kesiapan Pengajuan Permit"
        :kicker="`Visitor · Tahap ${currentStep + 1} dari 3`"
        icon="clipboard2-check-fill"
        :sentiment="kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')"
        placement="bottom"
        variant="glass"
        pattern="grid"
        size="md"
        sticky-footer
        @close="showReadinessModal = false"
    >
        <div class="visitor-readiness-sheet-body">
            <HcisProgress
                :value="kuotaHabis ? 100 : completionPercent"
                :sentiment="kuotaHabis ? 'danger' : (isFormValid ? 'success' : 'warning')"
                :label="kuotaHabis ? 'Status Jatah Kuota' : 'Kelengkapan Wajib'"
                :subtitle="kuotaHabis ? blokirKuota.ringkas : `${completedRequiredCount} dari ${completionItems.length} bagian lengkap`"
                size="sm"
                glow
                class="mb-3"
            />

            <div class="permit-sheet-section-title mb-2">Tahapan &amp; Checklist Dokumen:</div>
            <ul class="visitor-completion-list mb-3">
                <li
                    v-for="item in completionItems"
                    :key="item.label"
                    class="visitor-completion-item"
                    :class="{
                        'is-complete': !kuotaHabis && item.complete,
                        'is-active': currentStep === item.stepIndex,
                        'is-clickable': !isFormDisabled
                    }"
                    role="button"
                    tabindex="0"
                    @click="goToStep(item.stepIndex); showReadinessModal = false;"
                    @keydown.enter.space.prevent="goToStep(item.stepIndex); showReadinessModal = false;"
                >
                    <HcisIcon
                        :name="kuotaHabis ? 'dash-circle' : (item.complete ? 'check-circle-fill' : (currentStep === item.stepIndex ? 'record-circle-fill' : 'circle'))"
                        size="xs"
                    />
                    <span class="visitor-completion-label">{{ item.label }}</span>
                    <span v-if="currentStep === item.stepIndex" class="visitor-completion-pill">Aktif</span>
                </li>
            </ul>

            <div class="permit-sheet-section-title mb-2">Ringkasan Spesifikasi:</div>
            <div class="visitor-summary-data mb-3">
                <div>
                    <span>Nomor plat</span>
                    <strong class="font-tabular">{{ computedNoPlat || 'Belum lengkap' }}</strong>
                </div>
                <div>
                    <span>Kendaraan</span>
                    <strong>{{ selectedModelName }}</strong>
                </div>
                <div>
                    <span>Kategori</span>
                    <strong>{{ selectedCategoryName }}</strong>
                </div>
                <div v-if="form.inspeksi">
                    <span>Jadwal Inspeksi</span>
                    <strong class="text-xs text-indigo-700">{{ formatJadwalInspeksi(form.inspeksi) }}</strong>
                </div>
            </div>

            <div v-if="ringkasanKuota" class="permit-kuota-badge-box" :class="{ 'is-full': kuotaHabis }">
                <HcisIcon :name="kuotaHabis ? 'shield-lock-fill' : 'shield-check'" size="xs" :class="kuotaHabis ? 'text-danger' : 'text-success'" />
                <span>{{ ringkasanKuota }}</span>
            </div>
        </div>

        <template #footer>
            <div class="d-flex align-items-center gap-2 w-100">
                <HcisButton
                    v-if="currentStep > 0"
                    label="Sebelumnya"
                    leading-icon="arrow-left"
                    variant="secondary"
                    size="sm"
                    class="flex-shrink-0"
                    @click="prevStep(); showReadinessModal = false;"
                />
                <HcisButton
                    v-else-if="!kuotaHabis"
                    label="Reset"
                    leading-icon="arrow-counterclockwise"
                    variant="secondary"
                    appearance="subtle"
                    size="sm"
                    class="flex-shrink-0"
                    @click="resetForm(); showReadinessModal = false;"
                />
                <HcisButton
                    v-if="currentStep < 2"
                    :label="nextStepLabel"
                    variant="primary"
                    size="md"
                    trailing-icon="arrow-right"
                    :disabled="!isCurrentStepValid || isFormDisabled"
                    class="flex-grow-1"
                    @click="nextStep(); showReadinessModal = false;"
                />
                <HcisButton
                    v-else
                    :label="submitButtonLabel"
                    :variant="kuotaHabis ? 'secondary' : 'primary'"
                    size="md"
                    :leading-icon="submitButtonIcon"
                    :loading="isSubmitting"
                    :disabled="!isFormValid || isSubmitBlocked"
                    class="flex-grow-1"
                    @click="showReadinessModal = false; openConfirmModal();"
                />
            </div>
        </template>
    </HcisDialog>

    <!-- Enhanced Confirmation & Result State Dialog (Aligned with TamuForm) -->
    <HcisDialog
        v-model="showConfirmModal"
        custom-class="permit-confirm-dialog"
        :title="dialogMeta.title"
        :kicker="dialogMeta.kicker"
        :description="dialogMeta.description"
        :icon="dialogMeta.icon"
        :sentiment="dialogMeta.sentiment"
        variant="glass"
        pattern="grid"
        size="md"
        sticky-footer
        :close-on-overlay="modalStage !== 'submitting'"
        :close-on-esc="modalStage !== 'submitting'"
        :show-close="modalStage !== 'submitting'"
        @close="closeConfirmModal"
    >
        <!-- 1. Confirmation Stage -->
        <div v-if="modalStage === 'confirm'" class="permit-confirm-stage">
            <HcisResultState
                status="info"
                variant="flat"
                size="md"
                kicker="Visitor · Konfirmasi Permit"
                title="Kirim Pengajuan Permit Kendaraan?"
                description="Periksa kembali spesifikasi fisik kendaraan, berkas lampiran, dan jadwal inspeksi sebelum disimpan ke sistem."
            />

            <!-- Structured Executive Review Card -->
            <div v-if="pendingPayload" class="permit-modal-review-card">
                <!-- Header Ringkasan Transaksi -->
                <div class="permit-modal-review-header">
                    <div class="d-flex align-items-center gap-2">
                        <HcisIcon name="card-checklist" size="xs" class="text-indigo-600" />
                        <span class="permit-modal-review-title">Rincian Ringkasan Pengajuan</span>
                    </div>
                    <HcisBadge label="VERIFIKASI DATA" variant="brand" size="xs" appearance="subtle" />
                </div>

                <!-- Block 1: Identitas & Spesifikasi Fisik Kendaraan -->
                <div class="permit-modal-section">
                    <div class="permit-modal-section-title">
                        <HcisIcon name="car-front-fill" size="2xs" class="text-indigo-600" />
                        <span>Identitas &amp; Spesifikasi Fisik</span>
                    </div>
                    <div class="permit-modal-vehicle-hero">
                        <div class="permit-modal-plate font-tabular">
                            {{ pendingPayload.display_no_plat }}
                        </div>
                        <div class="permit-modal-vehicle-meta">
                            <div class="permit-modal-model-text">
                                <strong>{{ pendingPayload.display_model }}</strong>
                            </div>
                            <div class="permit-modal-sub-meta">
                                <span class="permit-modal-color-chip">
                                    <span class="permit-modal-color-dot" :style="{ backgroundColor: pendingPayload.display_warna_swatch }" />
                                    <span>Warna {{ pendingPayload.display_warna }}</span>
                                </span>
                                <span class="permit-modal-dot-separator">•</span>
                                <span class="permit-modal-year font-tabular">Tahun {{ pendingPayload.tahun_kendaraan }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block 2: Kategori Permit & Berkas Verifikasi -->
                <div class="permit-modal-section">
                    <div class="permit-modal-section-title">
                        <HcisIcon name="shield-check" size="2xs" class="text-indigo-600" />
                        <span>Kategori Akses &amp; Lampiran Berkas</span>
                    </div>
                    <div class="permit-modal-access-row">
                        <div class="permit-modal-access-badge-wrap">
                            <span class="permit-modal-field-kicker">Kategori Akses:</span>
                            <div>
                                <HcisBadge
                                    :label="pendingPayload.display_kategori"
                                    :variant="pendingPayload.kategori_sentiment"
                                    size="sm"
                                    appearance="solid"
                                />
                            </div>
                        </div>
                        <div class="permit-modal-docs-wrap">
                            <span class="permit-modal-field-kicker">Berkas Terlampir:</span>
                            <div class="permit-modal-docs-pills">
                                <span class="permit-modal-doc-tag" :class="{ 'is-attached': pendingPayload.has_foto_kendaraan }">
                                    <HcisIcon :name="pendingPayload.has_foto_kendaraan ? 'check-circle-fill' : 'dash-circle'" size="2xs" />
                                    <span>Foto Unit</span>
                                </span>
                                <span class="permit-modal-doc-tag" :class="{ 'is-attached': pendingPayload.has_foto_stnk }">
                                    <HcisIcon :name="pendingPayload.has_foto_stnk ? 'check-circle-fill' : 'dash-circle'" size="2xs" />
                                    <span>STNK</span>
                                </span>
                                <span class="permit-modal-doc-tag" :class="{ 'is-attached': pendingPayload.has_foto_sim }">
                                    <HcisIcon :name="pendingPayload.has_foto_sim ? 'check-circle-fill' : 'dash-circle'" size="2xs" />
                                    <span>SIM</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block 3: Jadwal Inspeksi SHE -->
                <div class="permit-modal-section">
                    <div class="permit-modal-section-title">
                        <HcisIcon name="calendar-check-fill" size="2xs" class="text-indigo-600" />
                        <span>Jadwal Pemeriksaan Fisik SHE</span>
                    </div>
                    <div class="permit-modal-inspection-card">
                        <div class="permit-modal-inspection-item">
                            <div class="permit-modal-inspection-icon is-indigo">
                                <HcisIcon name="calendar-event-fill" size="xs" />
                            </div>
                            <div class="permit-modal-inspection-text">
                                <span class="insp-label">Hari &amp; Tanggal</span>
                                <strong class="insp-val font-tabular">{{ pendingPayload.inspeksi_tanggal }}</strong>
                            </div>
                        </div>
                        <div class="permit-modal-inspection-divider" />
                        <div class="permit-modal-inspection-item">
                            <div class="permit-modal-inspection-icon is-amber">
                                <HcisIcon name="clock-fill" size="xs" />
                            </div>
                            <div class="permit-modal-inspection-text">
                                <span class="insp-label">Slot Waktu &amp; Pos</span>
                                <strong class="insp-val font-tabular">{{ pendingPayload.inspeksi_jam }} · {{ pendingPayload.inspeksi_pos }}</strong>
                                <span v-if="pendingPayload.inspeksi_lokasi" class="insp-label">{{ pendingPayload.inspeksi_lokasi }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block 4: Keterangan / Alasan (Opsional) -->
                <div v-if="pendingPayload.keterangan_pengajuan" class="permit-modal-section">
                    <div class="permit-modal-note-box">
                        <span class="permit-modal-note-label">Keterangan / Alasan Pengajuan:</span>
                        <p class="permit-modal-note-text">"{{ pendingPayload.keterangan_pengajuan }}"</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Submitting / Processing State -->
        <div v-else-if="modalStage === 'submitting'">
            <HcisResultState
                status="loading"
                variant="flat"
                size="md"
                kicker="Memproses Transaksi"
                title="Mengirim Pengajuan Permit..."
                description="Mohon tunggu, sistem sedang memverifikasi data dan berkas verifikasi kendaraan."
            />
        </div>

        <!-- 3. Success State with Digital Receipt -->
        <div v-else-if="modalStage === 'success'">
            <HcisResultState
                status="success"
                variant="flat"
                size="md"
                :animated="true"
                :loop-animation="true"
                kicker="Visitor · Pengajuan Berhasil"
                title="Pengajuan Permit Berhasil Disimpan!"
                description="Data kendaraan dan dokumen telah tercatat secara resmi dan nomor transaksi telah diterbitkan."
                :items="successReceiptItems"
            />
        </div>

        <!-- 4. Error State with Retry -->
        <div v-else-if="modalStage === 'error'">
            <HcisResultState
                status="error"
                variant="flat"
                size="md"
                :animated="true"
                kicker="Visitor · Kendala Transaksi"
                title="Gagal Mengirim Pengajuan"
                :description="submitErrorMessage || 'Terjadi kesalahan sistem saat mengirim data permit. Silakan coba kembali.'"
            />
        </div>

        <!-- Modal Footer Actions Adapted to Current Stage -->
        <template #footer>
            <div class="visitor-dialog-actions w-100 d-flex align-items-center justify-content-end gap-2">
                <!-- Confirmation Actions -->
                <template v-if="modalStage === 'confirm'">
                    <HcisButton
                        label="Periksa Kembali"
                        variant="secondary"
                        size="md"
                        leading-icon="arrow-left"
                        :disabled="isSubmitting"
                        @click="closeConfirmModal"
                    />
                    <HcisButton
                        label="Ya, Ajukan Permit"
                        variant="primary"
                        size="md"
                        leading-icon="check2-circle"
                        :loading="isSubmitting"
                        @click="confirmSubmit"
                    />
                </template>

                <!-- Success Actions -->
                <template v-else-if="modalStage === 'success'">
                    <HcisButton
                        label="Ajukan Kendaraan Baru"
                        variant="secondary"
                        size="md"
                        leading-icon="car-front"
                        @click="registerAnother"
                    />
                    <HcisButton
                        label="Lihat di Riwayat Permit"
                        variant="primary"
                        size="md"
                        leading-icon="journal-text"
                        @click="goToHistory"
                    />
                </template>

                <!-- Error Actions -->
                <template v-else-if="modalStage === 'error'">
                    <HcisButton
                        label="Tutup"
                        variant="secondary"
                        size="md"
                        leading-icon="x-lg"
                        @click="closeConfirmModal"
                    />
                    <HcisButton
                        label="Coba Lagi"
                        variant="primary"
                        size="md"
                        leading-icon="arrow-clockwise"
                        @click="confirmSubmit"
                    />
                </template>
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisCheckbox,
    HcisCollapse,
    HcisCollapseItem,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisInputNumber,
    HcisPlateInput,
    HcisProgress,
    HcisRadio,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisSteps,
    HcisTextarea,
    HcisUpload,
} from '@/components/ui';
import SlotInspeksiPicker from '@/Pages/VISITOR/Permit/Inspection/components/SlotInspeksiPicker.vue';
import { kompresGambar } from '@/utils/kompresGambar';

/** Batas server per berkas (VehiclePermitController::store, max:5120). */
const BATAS_SERVER_MB = 5;
/**
 * Batas saat MEMILIH berkas, lebih longgar dari server: foto kamera di atas
 * 5 MB tetap bisa dipilih karena diperkecil dulu sebelum dikirim.
 */
const BATAS_PILIH_MB = 20;

/**
 * Kode penolakan dari VisitorPermitQuotaService — ketiganya berarti angka kuota
 * di layar sudah basi, jadi keputusan yang tepat sama untuk ketiganya.
 */
const KODE_BATAS_KUOTA = [
    'VISITOR_PERMIT_LIMIT_REACHED',
    'VISITOR_PERMIT_PENDING_LIMIT',
    'VISITOR_PERMIT_AKSES_HARIAN',
];

export default {
    name: 'PermitForm',
    components: {
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisCheckbox,
        HcisCollapse,
        HcisCollapseItem,
        HcisDialog,
        HcisField,
        HcisIcon,
        HcisInputNumber,
        HcisPlateInput,
        HcisProgress,
        HcisRadio,
        HcisResultState,
        HcisSelect,
        HcisSelectOption,
        HcisSkeleton,
        HcisSteps,
        HcisTextarea,
        HcisUpload,
        SlotInspeksiPicker,
    },
    emits: ['saved', 'navigate-to-status', 'navigate-to-cards', 'refresh-kuota'],
    props: {
        config: { type: Object, default: () => ({}) },
        approvalPolicy: { type: Object, default: () => ({}) },
        approverFlows: { type: Array, default: () => [] },
        // { terpakai, maks } dari /transaksipermit-stats. Angkanya lahir dari
        // VisitorPermitQuotaService — sumber yang sama dengan penjaga di server,
        // supaya layar tak pernah berselisih dengan penolakannya.
        kuota: { type: Object, default: () => ({}) },
        // { hari_ke_depan, maks_jadwal_ulang, maks_tidak_hadir } — konstanta
        // JadwalInspeksiService, dikirim VehiclePermitController::index().
        aturanInspeksi: { type: Object, default: () => ({}) },
        // { boleh, status, pesan } dari KartuNfcPemohon (VehiclePermitController::index).
        // null = halaman lama tanpa prop ini: form tetap tampil, server tetap menjaga.
        kartuNfc: { type: Object, default: null },
    },
    data() {
        return {
            // Nomor urut unggahan per kolom: hasil kompresi berkas lama yang
            // selesai belakangan tidak boleh menimpa berkas yang dipilih sesudahnya.
            giliranUnggah: {},
            BATAS_PILIH_MB,
            // Diisi saat server menolak karena kartu (mis. kartu dicabut SESUDAH
            // halaman dimuat), supaya panel blokir muncul tanpa muat ulang.
            kartuNfcDariServer: null,
            masterLoading: true,
            idempotencyKey: null,
            currentStep: 0,
            form: this.defaultForm(),
            files: this.defaultFiles(),
            uploadLists: this.defaultUploadLists(),
            master: { plat_codes: [], vehicle_types: [], models: [], colors: [], kategoris: [] },
            slotInspeksi: [],
            slotLoading: true,
            slotError: '',
            isSubmitting: false,
            showConfirmModal: false,
            showReadinessModal: false,
            modalStage: 'confirm', // 'confirm' | 'submitting' | 'success' | 'error'
            pendingPayload: null,
            lastSubmittedResult: null,
            submitErrorMessage: '',
            isSheRulesExpanded: typeof window !== 'undefined' ? window.innerWidth > 768 : true,
            isReviewCardExpanded: typeof window !== 'undefined' ? window.innerWidth > 768 : true,
        };
    },
    computed: {
        kartuNfcAktif() {
            return this.kartuNfcDariServer || this.kartuNfc || { boleh: true };
        },
        kartuNfcTerkunci() {
            return this.kartuNfcAktif.boleh === false;
        },
        judulKartuNfc() {
            return {
                BELUM_PAIRING: 'ID Card belum terdaftar',
                KARTU_DIBLOKIR: 'ID Card sedang dinonaktifkan',
                MENUNGGU_VALIDASI: 'ID Card belum divalidasi',
                KARTU_GANDA: 'ID Card tercatat ganda',
                TIDAK_TERSEDIA: 'Pengecekan ID Card belum tersedia',
            }[this.kartuNfcAktif.status] || 'ID Card belum siap';
        },
        currentYear() {
            return new Date().getFullYear();
        },
        computedNoPlat() {
            return this.normalizePlateNumber(this.form.no_plat);
        },
        plateParts() {
            return this.parsePlateNumber(this.computedNoPlat);
        },
        filteredModels() {
            if (!this.form.id_vehicle_type) return [];
            return this.master.models.filter((model) => model.Id_Master_Tipe === this.form.id_vehicle_type);
        },
        categoryOptions() {
            return this.master.kategoris.map((kategori) => ({
                value: kategori.Id,
                label: kategori.Nama_Kategori,
                description: kategori.Deskripsi || kategori.Deskripsi_Akses || 'Kategori akses kendaraan',
                color: this.categorySentiment(kategori),
                icon: this.categoryIcon(kategori),
                iconColor: this.categorySentiment(kategori),
            }));
        },
        stepVehicleValid() {
            return Boolean(
                this.plateParts.prefix &&
                this.plateParts.angka &&
                this.plateParts.suffix &&
                this.form.id_vehicle_type &&
                this.form.id_vehicle_model &&
                this.form.id_vehicle_color &&
                this.form.tahun_kendaraan,
            );
        },
        stepDocsValid() {
            return Boolean(
                this.files.foto_kendaraan &&
                this.files.foto_stnk &&
                this.files.foto_sim,
            );
        },
        step0Valid() {
            return this.stepVehicleValid && this.stepDocsValid;
        },
        step1Valid() {
            return Boolean(this.form.id_kategori_permit);
        },
        step2Valid() {
            return Boolean(
                this.form.inspeksi?.mulai &&
                this.form.statement_accepted,
            );
        },
        isCurrentStepValid() {
            switch (this.currentStep) {
                case 0: return this.step0Valid;
                case 1: return this.step1Valid;
                case 2: return this.step2Valid && this.isFormValid;
                default: return false;
            }
        },
        nextStepLabel() {
            switch (this.currentStep) {
                case 0: return 'Lanjut: Kategori Permit';
                case 1: return 'Lanjut: Jadwal Inspeksi SHE';
                case 2: return this.submitButtonLabel;
                default: return 'Lanjut';
            }
        },
        wizardSteps() {
            return [
                {
                    label: 'Identitas & Dokumen',
                    shortLabel: 'Identitas',
                    subtitle: 'Unit, STNK & SIM',
                    icon: 'car-front-fill',
                    disabled: false,
                    status: this.step0Valid ? 'Selesai' : (this.currentStep === 0 ? 'Sedang Diisi' : 'Menunggu'),
                },
                {
                    label: 'Kategori Permit',
                    shortLabel: 'Kategori',
                    subtitle: 'Pilihan hak akses',
                    icon: 'shield-check',
                    disabled: !this.step0Valid && this.currentStep < 1,
                    status: this.step1Valid ? 'Selesai' : (this.currentStep === 1 ? 'Sedang Diisi' : 'Menunggu'),
                },
                {
                    label: 'Jadwal SHE & Kirim',
                    shortLabel: 'Jadwal',
                    subtitle: 'Slot waktu & komitmen',
                    icon: 'calendar-check-fill',
                    disabled: (!this.step0Valid || !this.step1Valid) && this.currentStep < 2,
                    status: this.step2Valid ? 'Selesai' : (this.currentStep === 2 ? 'Sedang Diisi' : 'Menunggu'),
                },
            ];
        },
        /** Data HcisSteps: langkah selesai (bukan yang sedang dibuka) bertanda centang. */
        langkahWizard() {
            return this.wizardSteps.map((step, index) => ({
                label: step.label,
                shortLabel: step.shortLabel,
                caption: step.subtitle,
                icon: step.icon,
                disabled: step.disabled || this.isFormDisabled,
                state: index === this.currentStep
                    ? 'current'
                    : (index < this.currentStep || step.status === 'Selesai' ? 'done' : 'todo'),
            }));
        },
        completionItems() {
            return [
                { label: 'Identitas kendaraan', complete: this.stepVehicleValid, stepIndex: 0 },
                { label: 'Foto kendaraan', complete: Boolean(this.files.foto_kendaraan), stepIndex: 0 },
                { label: 'Dokumen STNK', complete: Boolean(this.files.foto_stnk), stepIndex: 0 },
                { label: 'Dokumen SIM', complete: Boolean(this.files.foto_sim), stepIndex: 0 },
                { label: 'Kategori permit', complete: Boolean(this.form.id_kategori_permit), stepIndex: 1 },
                { label: 'Jadwal inspeksi SHE', complete: Boolean(this.form.inspeksi?.mulai), stepIndex: 2 },
                { label: 'Pernyataan & komitmen', complete: Boolean(this.form.statement_accepted), stepIndex: 2 },
            ];
        },
        completedRequiredCount() {
            return this.completionItems.filter((item) => item.complete).length;
        },
        completionPercent() {
            return Math.round((this.completedRequiredCount / this.completionItems.length) * 100);
        },
        isFormValid() {
            return this.completionItems.every((item) => item.complete);
        },
        /**
         * Sebab pengajuan terblokir — atau null bila tidak.
         *
         * URUTANNYA WAJIB SAMA dengan VisitorPermitQuotaService::
         * assertAvailableWithinTransaction(): jatah permit, lalu pengajuan yang
         * masih menunggu keputusan, lalu akses inspeksi hari ini. Kalau dua
         * batas sama-sama penuh, layar harus menyebut sebab yang sama dengan
         * yang akan diucapkan penolakannya — bukan sebab yang kebetulan
         * diperiksa lebih dulu di sini.
         *
         * Semua batas dibaca dari /transaksipermit-stats, yang mengambilnya dari
         * service yang sama dengan penjaganya. maks 0 = tanpa batas, dan selama
         * angkanya belum tiba (prop masih kosong) tidak ada yang dikunci:
         * tombol mati sekejap tanpa sebab terbaca seperti kerusakan.
         */
        blokirKuota() {
            const batas = (terpakai, maks) => {
                const m = Number(this.kuota?.[maks] ?? 0);
                const t = Number(this.kuota?.[terpakai] ?? 0);

                return m > 0 && t >= m ? { terpakai: t, maks: m } : null;
            };

            const jatah = batas('terpakai', 'maks');
            if (jatah) {
                return {
                    kode: 'KUOTA',
                    ...jatah,
                    lencana: 'Kuota Penuh',
                    judul: `Batas Maksimal Permit (${jatah.terpakai}/${jatah.maks})`,
                    pesan: `Seluruh ${jatah.maks} jatah permit kendaraan Anda telah terpakai. Formulir pengajuan baru dinonaktifkan sementara.`,
                    ringkas: `Jatah penuh: ${jatah.terpakai} dari ${jatah.maks} permit aktif`,
                    tombol: 'Batas Kuota Penuh',
                    alasan: `Jatah permit Anda sudah penuh: ${jatah.terpakai} dari maksimal ${jatah.maks}. Jatah kembali setelah permit ditolak atau kedaluwarsa.`,
                };
            }

            const pending = batas('pendingTerpakai', 'pendingMaks');
            if (pending) {
                return {
                    kode: 'PENDING',
                    ...pending,
                    lencana: 'Menunggu Keputusan',
                    judul: `Pengajuan Masih Diproses (${pending.terpakai}/${pending.maks})`,
                    pesan: `Anda hanya boleh punya ${pending.maks} pengajuan yang menunggu keputusan pada satu waktu. Formulir terbuka lagi setelah pengajuan itu disetujui, ditolak, atau dibatalkan.`,
                    ringkas: `Menunggu keputusan: ${pending.terpakai} dari ${pending.maks} pengajuan`,
                    tombol: 'Pengajuan Masih Diproses',
                    alasan: `Anda masih punya ${pending.terpakai} pengajuan yang menunggu keputusan (maksimal ${pending.maks}). Ajukan lagi setelah pengajuan itu diputus atau dibatalkan.`,
                };
            }

            const harian = batas('aksesTerpakai', 'aksesMaks');
            if (harian) {
                return {
                    kode: 'AKSES_HARIAN',
                    ...harian,
                    lencana: 'Batas Harian',
                    judul: `Batas Pengajuan Hari Ini (${harian.terpakai}/${harian.maks})`,
                    pesan: `Setiap pengajuan langsung menerbitkan jadwal inspeksi, dan hari ini Anda sudah memakai ${harian.terpakai} dari ${harian.maks} jatah harian. Silakan ajukan kembali besok.`,
                    ringkas: `Batas harian terpakai: ${harian.terpakai} dari ${harian.maks} pengajuan`,
                    tombol: 'Batas Harian Tercapai',
                    alasan: `Anda sudah mengajukan ${harian.terpakai} permit hari ini (maksimal ${harian.maks} per hari). Jatahnya kembali besok — pembatalan tidak mengembalikannya.`,
                };
            }

            return null;
        },
        kuotaHabis() {
            return this.blokirKuota !== null;
        },
        isFormDisabled() {
            return this.isSubmitting || this.kuotaHabis || this.config.enabled === false;
        },
        submitButtonLabel() {
            if (this.blokirKuota) {
                return this.blokirKuota.tombol;
            }
            if (this.config.enabled === false) {
                return 'Fitur Dinonaktifkan';
            }
            return 'Ajukan Permit';
        },
        submitButtonIcon() {
            if (this.kuotaHabis || this.config.enabled === false) {
                return 'lock-fill';
            }
            return 'send-fill';
        },
        ringkasanKuota() {
            // Selagi terblokir, yang wajib terbaca adalah SEBAB yang sedang
            // berlaku — bukan sisa jatah permit, yang bisa saja masih longgar
            // padahal formulirnya terkunci batas lain.
            if (this.blokirKuota) {
                return this.blokirKuota.ringkas;
            }

            const maks = Number(this.kuota?.maks ?? 0);

            if (maks <= 0) {
                return null;
            }

            const terpakai = Number(this.kuota?.terpakai ?? 0);
            const sisa = Math.max(maks - terpakai, 0);

            return `Sisa jatah: ${sisa} dari ${maks} permit`;
        },
        tanpaApprover() {
            return Boolean(
                this.approvalPolicy.needs_approval &&
                !this.approvalPolicy.allow_submit_without_flow &&
                this.approverFlows.length === 0,
            );
        },
        isSubmitBlocked() {
            if (this.config.enabled === false) return true;
            return this.kuotaHabis || this.tanpaApprover;
        },
        alasanTerkunci() {
            if (this.config.enabled === false) {
                return 'Fitur pengajuan permit sedang dinonaktifkan.';
            }

            if (this.blokirKuota) {
                return this.blokirKuota.alasan;
            }

            return 'Alur persetujuan permit belum memiliki approver aktif. Data tetap dapat dilengkapi sambil menunggu konfigurasi diperbarui.';
        },
        selectedVehicleTypeName() {
            return (
                this.master.vehicle_types.find((item) => item.Id === this.form.id_vehicle_type)?.Nama_Vehicle_Type ||
                '-'
            );
        },
        selectedModelName() {
            return (
                this.master.models.find((item) => item.Id === this.form.id_vehicle_model)?.Nama_Model_Kendaraan || '-'
            );
        },
        selectedCategoryName() {
            return this.master.kategoris.find((item) => item.Id === this.form.id_kategori_permit)?.Nama_Kategori || '-';
        },
        selectedCategoryColor() {
            const selected = this.master.kategoris.find((item) => item.Id === this.form.id_kategori_permit);
            return selected?.Kode_Warna || selected?.Kode_Warna_UI || '';
        },
        selectedColorName() {
            const selected = this.master.colors.find((item) => String(item.Id) === String(this.form.id_vehicle_color));
            return selected ? selected.Nama_Warna : '-';
        },
        selectedVehicleColorSwatch() {
            const selected = this.master.colors.find((item) => String(item.Id) === String(this.form.id_vehicle_color));
            return selected ? this.getVehicleColorSwatch(selected) : '';
        },
        confirmReceiptItems() {
            if (!this.pendingPayload) return [];
            const p = this.pendingPayload;
            return [
                {
                    label: 'Nomor Plat Kendaraan',
                    value: p.display_no_plat || '-',
                    isHighlight: true,
                    isMono: true,
                    icon: 'card-text',
                },
                {
                    label: 'Merek & Jenis Kendaraan',
                    value: p.display_model || '-',
                    icon: this.getVehicleIcon(p.vehicle_type_name),
                },
                {
                    label: 'Warna Kendaraan',
                    value: p.display_warna || '-',
                    icon: 'palette-fill',
                },
                {
                    label: 'Tahun Pembuatan',
                    value: String(p.tahun_kendaraan || '-'),
                    isMono: true,
                    icon: 'calendar-event-fill',
                },
                {
                    label: 'Kategori Akses',
                    value: p.display_kategori || '-',
                    icon: 'shield-check',
                },
                {
                    label: 'Jadwal Inspeksi',
                    value: p.display_inspeksi || '-',
                    icon: 'calendar-check',
                },
                {
                    label: 'Foto Kendaraan',
                    value: p.display_foto_kendaraan ? 'Sudah Terlampir' : '-',
                    icon: 'camera-fill',
                },
                {
                    label: 'Dokumen STNK',
                    value: p.display_foto_stnk ? 'Sudah Terlampir' : '-',
                    icon: 'file-earmark-richtext-fill',
                },
                {
                    label: 'Dokumen SIM',
                    value: p.display_foto_sim ? 'Sudah Terlampir' : '-',
                    icon: 'person-vcard-fill',
                },
                ...(p.keterangan_pengajuan ? [{
                    label: 'Alasan / Keterangan',
                    value: p.keterangan_pengajuan,
                    icon: 'chat-left-text-fill',
                }] : []),
            ];
        },
        successReceiptItems() {
            const r = this.lastSubmittedResult;
            if (!r) return [];
            return [
                {
                    label: 'No. Transaksi Permit',
                    value: r.No_Transaksi || r.no_transaksi || '-',
                    isHighlight: true,
                    isMono: true,
                    icon: 'hash',
                },
                {
                    label: 'Nomor Plat Kendaraan',
                    value: r.No_Plat_Kendaraan || r.no_plat || this.pendingPayload?.display_no_plat || '-',
                    isHighlight: true,
                    isMono: true,
                    icon: 'card-text',
                },
                {
                    label: 'Status Pengajuan',
                    value: 'Menunggu Approval',
                    badgeVariant: 'warning',
                    icon: 'hourglass-split',
                },
                {
                    label: 'Waktu Pengajuan',
                    value: new Date().toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }),
                    icon: 'clock-fill',
                },
            ];
        },
        dialogMeta() {
            switch (this.modalStage) {
                case 'confirm':
                    return {
                        title: 'Konfirmasi Pengajuan Permit',
                        kicker: 'Visitor · Verifikasi Data',
                        description: 'Pastikan seluruh berkas dan data kendaraan yang Anda daftarkan sudah tepat.',
                        icon: 'shield-check',
                        sentiment: 'brand',
                    };
                case 'submitting':
                    return {
                        title: 'Mengirim Berkas...',
                        kicker: 'Visitor · Proses Transaksi',
                        description: 'Data dan dokumen sedang diunggah ke server HCIS.',
                        icon: 'arrow-repeat',
                        sentiment: 'brand',
                    };
                case 'success':
                    return {
                        title: 'Pengajuan Berhasil Dikirim',
                        kicker: 'Visitor · Transaksi Terbit',
                        description: 'Permit Anda telah masuk ke dalam antrean persetujuan Admin.',
                        icon: 'check-circle-fill',
                        sentiment: 'success',
                    };
                case 'error':
                    return {
                        title: 'Pengajuan Belum Berhasil',
                        kicker: 'Visitor · Kendala Sistem',
                        description: 'Sistem mengalami kendala saat memproses permohonan permit.',
                        icon: 'x-circle-fill',
                        sentiment: 'danger',
                    };
                default:
                    return {
                        title: 'Pengajuan Permit',
                        kicker: 'Visitor',
                        description: '',
                        icon: 'card-checklist',
                        sentiment: 'brand',
                    };
            }
        },
    },
    watch: {
        'form.id_vehicle_type'(newVal, oldVal) {
            if (newVal !== oldVal) {
                const currentModelValid = this.filteredModels.some(
                    (model) => model.Id === this.form.id_vehicle_model,
                );
                if (!currentModelValid) {
                    this.form.id_vehicle_model = null;
                }
            }
        },
    },
    mounted() {
        // Form terkunci tidak perlu master data maupun slot inspeksi.
        if (this.kartuNfcTerkunci) {
            return;
        }
        this.fetchMasterData();
        this.fetchSlotInspeksi();
    },
    methods: {
        defaultForm() {
            return {
                no_plat: '',
                tahun_kendaraan: new Date().getFullYear(),
                id_vehicle_type: null,
                id_vehicle_model: null,
                id_vehicle_color: null,
                id_kategori_permit: null,
                keterangan_pengajuan: '',
                inspeksi: null,
                statement_accepted: false,
            };
        },
        defaultFiles() {
            return {
                foto_kendaraan: null,
                foto_stnk: null,
                foto_sim: null,
            };
        },
        defaultUploadLists() {
            return {
                foto_kendaraan: [],
                foto_stnk: [],
                foto_sim: [],
            };
        },
        getOrCreateIdempotencyKey() {
            if (!this.idempotencyKey) {
                this.idempotencyKey = `${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
            }
            return this.idempotencyKey;
        },
        async fetchMasterData() {
            this.masterLoading = true;
            try {
                const { data } = await axios.get('/transaksipermit-master');
                if (data && data.success) {
                    // ResponseHelper::success() membungkus muatannya di bawah
                    // `result`. Membacanya dari akar tetap lolos `data.success`,
                    // jadi tak ada error dan tak ada pesan — semua daftar master
                    // sekadar jadi kosong dan pilihan jenis permit hilang.
                    const isi = data.result || {};

                    this.master = {
                        plat_codes: isi.plat_codes || [],
                        vehicle_types: isi.vehicle_types || [],
                        models: isi.models || [],
                        colors: isi.colors || [],
                        kategoris: isi.kategoris || [],
                    };
                }
            } catch (error) {
                ElMessage.error(error?.response?.data?.message || 'Gagal memuat master data permit.');
            } finally {
                this.masterLoading = false;
            }
        },
        async fetchSlotInspeksi() {
            this.slotLoading = true;
            this.slotError = '';
            try {
                /* semua=1: slot penuh dan yang jamnya sudah lewat ikut dikirim, bertanda
                   'keadaan'. Pemohon perlu melihat jadwal yang HABIS sebagai habis,
                   bukan sebagai jadwal yang tak pernah dibuka. */
                const { data } = await axios.get('/transaksipermit-slot-inspeksi', { params: { semua: 1 } });
                this.slotInspeksi = data?.result?.slot || [];

                // Slot pilihan yang sudah tidak ada (penuh/ditutup) dilepas,
                // supaya kesiapan pengajuan tidak berbohong.
                const pilihan = this.form.inspeksi;
                if (pilihan && !this.slotInspeksi.some((x) => x.id_pos === pilihan.id_pos && x.mulai === pilihan.mulai)) {
                    this.form.inspeksi = null;
                }
            } catch (error) {
                this.slotInspeksi = [];
                this.slotError = error?.response?.data?.message || 'Slot inspeksi gagal dimuat.';
            } finally {
                this.slotLoading = false;
            }
        },
        formatJadwalInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const [tanggal, jam] = inspeksi.mulai.split(' ');
            const [y, m, d] = tanggal.split('-').map(Number);
            const label = new Date(y, m - 1, d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            const selesai = inspeksi.selesai ? `–${String(inspeksi.selesai).slice(11, 16)}` : '';
            const lokasi = inspeksi.lokasi ? ` (${inspeksi.lokasi})` : '';
            return `${label}, ${jam}${selesai} · ${inspeksi.pos}${lokasi}`;
        },
        jam(waktu) {
            if (!waktu) return '-';
            return String(waktu).slice(11, 16);
        },
        formatTanggalTiket(waktu) {
            if (!waktu) return '-';
            const [tanggal] = String(waktu).split(' ');
            const [y, m, d] = tanggal.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        getVehicleIcon(typeName = '') {
            const norm = (typeName || '').toLowerCase();
            if (norm.includes('motor') || norm.includes('sepeda')) return 'bicycle';
            if (norm.includes('truk') || norm.includes('truck')) return 'truck-front-fill';
            return 'car-front-fill';
        },
        getVehicleColorSwatch(color) {
            if (!color) return '#cbd5e1';
            if (color.Kode_Hex || color.Kode_Warna) {
                return color.Kode_Hex || color.Kode_Warna;
            }

            // Master lama hanya menyimpan nama warna. Tetap berikan swatch
            // deterministik agar pilihan kendaraan tidak tampak abu-abu semua.
            const name = String(color.Nama_Warna || color.Nama || '').toLowerCase();
            if (name.includes('putih') || name.includes('white')) return '#f8fafc';
            if (name.includes('hitam') || name.includes('black')) return '#09090b';
            if (name.includes('silver') || name.includes('abu')) return '#94a3b8';
            if (name.includes('merah') || name.includes('red')) return '#dc2626';
            if (name.includes('biru') || name.includes('blue')) return '#2563eb';
            if (name.includes('hijau') || name.includes('green')) return '#16a34a';
            if (name.includes('kuning') || name.includes('yellow')) return '#eab308';
            return '#94a3b8';
        },
        categorySentiment(kategori) {
            const hex = (kategori?.Kode_Warna || kategori?.Kode_Warna_UI || '').toLowerCase();
            if (hex.includes('dc2626') || hex.includes('red') || hex.includes('ef4444')) return 'danger';
            if (hex.includes('16a34a') || hex.includes('10b981') || hex.includes('green') || hex.includes('emerald')) return 'success';
            if (hex.includes('d97706') || hex.includes('eab308') || hex.includes('f59e0b') || hex.includes('yellow') || hex.includes('amber')) return 'warning';
            if (hex.includes('4f46e5') || hex.includes('6366f1') || hex.includes('2563eb') || hex.includes('blue') || hex.includes('indigo')) return 'brand';

            const name = (kategori?.Nama_Kategori || '').toLowerCase();
            if (name.includes('merah') || name.includes('vip') || name.includes('direksi')) return 'danger';
            if (name.includes('hijau') || name.includes('tamu') || name.includes('visitor')) return 'success';
            if (name.includes('kuning') || name.includes('vendor') || name.includes('kontraktor')) return 'warning';
            if (name.includes('biru') || name.includes('karyawan') || name.includes('staff')) return 'brand';
            return 'brand';
        },
        categoryIcon(kategori) {
            const name = (kategori?.Nama_Kategori || '').toLowerCase();
            if (name.includes('merah') || name.includes('vip') || name.includes('direksi')) return 'shield-fill-exclamation';
            if (name.includes('hijau') || name.includes('tamu') || name.includes('visitor')) return 'shield-fill-check';
            if (name.includes('kuning') || name.includes('vendor') || name.includes('kontraktor')) return 'shield-fill';
            if (name.includes('biru') || name.includes('karyawan') || name.includes('staff')) return 'shield-fill';
            return 'shield-check';
        },
        normalizePlateNumber(val) {
            const raw = String(val || '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, ' ')
                .trim();
            if (!raw) return '';

            // Terima baik input ber-spasi ("B 1234 XYZ") maupun input padat
            // dari scanner/copy-paste ("B1234XYZ"), lalu kirimkan format
            // kanonik yang sama dengan validasi server.
            const compact = raw.replace(/\s+/g, '');
            const compactMatch = compact.match(/^([A-Z]{1,3})(\d{1,4})([A-Z]{0,3})$/);
            if (compactMatch) {
                return [compactMatch[1], compactMatch[2], compactMatch[3]].filter(Boolean).join(' ');
            }

            return raw.replace(/\s+/g, ' ');
        },
        parsePlateNumber(normalized) {
            if (!normalized) return { prefix: '', angka: '', suffix: '' };
            const parts = normalized.split(' ');
            return {
                prefix: parts[0] || '',
                angka: parts[1] || '',
                suffix: parts.slice(2).join(' ') || '',
            };
        },
        /**
         * Foto diperkecil di perangkat sebelum dipakai (kamera HP 3–5 MB+ membuat
         * pengajuan lambat dan bisa menabrak batas unggah server). Selama
         * diproses, berkasnya dianggap BELUM ada — tombol lanjut/kirim tertahan,
         * bukan mengirim berkas setengah jadi. Hasil (atau PDF) yang tetap di
         * atas batas server ditolak di sini dengan pesan yang jelas.
         */
        async updateUpload(field, fileList) {
            this.uploadLists[field] = fileList || [];
            const rawFile = fileList && fileList.length > 0 ? (fileList[0].raw || fileList[0]) : null;
            const giliran = (this.giliranUnggah[field] || 0) + 1;
            this.giliranUnggah[field] = giliran;

            if (!rawFile) {
                this.files[field] = null;
                return;
            }

            this.files[field] = null;
            const hasil = await kompresGambar(rawFile);
            if (this.giliranUnggah[field] !== giliran) return; // sudah diganti berkas lain

            if (hasil.size > BATAS_SERVER_MB * 1024 * 1024) {
                this.uploadLists[field] = [];
                ElMessage.error(`Berkas "${rawFile.name}" melebihi ${BATAS_SERVER_MB} MB. Gunakan foto, atau PDF yang lebih kecil.`);
                return;
            }
            this.files[field] = hasil;
        },
        scrollToTop() {
            const el = this.$el;
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        nextStep() {
            if (this.isFormDisabled) return;
            if (this.currentStep === 0) {
                if (!this.stepVehicleValid) {
                    ElMessage.warning('Mohon lengkapi seluruh isian identitas fisik kendaraan.');
                    return;
                }
                if (!this.stepDocsValid) {
                    ElMessage.warning('Mohon unggah Foto Kendaraan, STNK, dan SIM terlebih dahulu.');
                    return;
                }
                this.currentStep = 1;
            } else if (this.currentStep === 1) {
                if (!this.step1Valid) {
                    ElMessage.warning('Pilih kategori permit kendaraan terlebih dahulu.');
                    return;
                }
                this.currentStep = 2;
            } else if (this.currentStep === 2) {
                if (!this.step2Valid) {
                    if (!this.form.inspeksi?.mulai) {
                        ElMessage.warning('Pilih slot jadwal inspeksi fisik SHE terlebih dahulu.');
                    } else if (!this.form.statement_accepted) {
                        ElMessage.warning('Centang persetujuan pernyataan kebenaran data dan komitmen SHE.');
                    }
                    return;
                }
                this.openConfirmModal();
            }
            this.scrollToTop();
        },
        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.scrollToTop();
            }
        },
        goToStep(index) {
            if (this.isFormDisabled) return;
            if (index === this.currentStep) return;
            if (index < this.currentStep) {
                this.currentStep = index;
                this.scrollToTop();
                return;
            }
            // Jumping forward checks
            if (index === 1 && !this.step0Valid) {
                ElMessage.warning('Lengkapi spesifikasi dan berkas kendaraan terlebih dahulu.');
                return;
            }
            if (index === 2 && (!this.step0Valid || !this.step1Valid)) {
                ElMessage.warning('Lengkapi data kendaraan dan kategori permit terlebih dahulu.');
                return;
            }
            this.currentStep = index;
            this.scrollToTop();
        },
        resetForm() {
            if (this.kuotaHabis) return;
            this.form = this.defaultForm();
            this.files = this.defaultFiles();
            this.uploadLists = this.defaultUploadLists();
            this.idempotencyKey = null;
            this.currentStep = 0;
        },
        openConfirmModal() {
            if (this.isSubmitBlocked || !this.isFormValid) return;

            // Reserve the idempotency key when the confirmation is opened so
            // retries from the same modal always represent one logical
            // submission, even if the first request fails before it reaches
            // the server.
            this.getOrCreateIdempotencyKey();

            const selectedCat = this.master.kategoris.find((item) => item.Id === this.form.id_kategori_permit);

            this.pendingPayload = {
                display_no_plat: this.computedNoPlat,
                display_model: `${this.selectedVehicleTypeName} · ${this.selectedModelName}`,
                display_warna: this.selectedColorName,
                display_warna_swatch: this.selectedVehicleColorSwatch,
                tahun_kendaraan: this.form.tahun_kendaraan,
                display_kategori: this.selectedCategoryName,
                kategori_sentiment: this.categorySentiment(selectedCat),
                display_inspeksi: this.formatJadwalInspeksi(this.form.inspeksi),
                inspeksi_tanggal: this.form.inspeksi?.mulai ? this.formatTanggalTiket(this.form.inspeksi.mulai) : '-',
                inspeksi_jam: this.form.inspeksi?.mulai ? `${this.jam(this.form.inspeksi.mulai)} – ${this.jam(this.form.inspeksi.selesai)} WIB` : '-',
                inspeksi_pos: this.form.inspeksi?.pos || '-',
                inspeksi_lokasi: this.form.inspeksi?.lokasi || '',
                display_foto_kendaraan: this.files.foto_kendaraan ? (this.files.foto_kendaraan.name || 'Sudah Terlampir') : 'Sudah Terlampir',
                display_foto_stnk: this.files.foto_stnk ? (this.files.foto_stnk.name || 'Sudah Terlampir') : 'Sudah Terlampir',
                display_foto_sim: this.files.foto_sim ? (this.files.foto_sim.name || 'Sudah Terlampir') : 'Sudah Terlampir',
                has_foto_kendaraan: Boolean(this.files.foto_kendaraan),
                has_foto_stnk: Boolean(this.files.foto_stnk),
                has_foto_sim: Boolean(this.files.foto_sim),
                keterangan_pengajuan: this.form.keterangan_pengajuan,
                vehicle_type_name: this.selectedVehicleTypeName,
            };

            this.modalStage = 'confirm';
            this.showConfirmModal = true;
        },
        closeConfirmModal() {
            const wasSuccess = this.modalStage === 'success';
            this.showConfirmModal = false;
            if (wasSuccess) {
                this.resetForm();
                this.$emit('saved');
            }
            this.modalStage = 'confirm';
            this.submitErrorMessage = '';
        },
        registerAnother() {
            this.showConfirmModal = false;
            this.resetForm();
            this.modalStage = 'confirm';
            this.$emit('saved');
        },
        goToHistory() {
            this.showConfirmModal = false;
            this.resetForm();
            this.modalStage = 'confirm';
            this.$emit('saved');
            this.$emit('navigate-to-status');
        },
        async confirmSubmit() {
            this.modalStage = 'submitting';
            this.isSubmitting = true;
            this.submitErrorMessage = '';

            try {
                // Nama field WAJIB mengikuti yang divalidasi
                // VehiclePermitController::store() — bukan nama kolom database.
                // Platnya dikirim per ruas: server merangkainya ulang lewat
                // resolvePlatNomor(), plat utuh saja membuatnya gagal.
                const formData = new FormData();
                formData.append('no_plat', this.computedNoPlat);
                formData.append('plat_prefix', this.plateParts.prefix);
                formData.append('plat_angka', this.plateParts.angka);
                formData.append('plat_suffix', this.plateParts.suffix);
                formData.append('tahun_kendaraan', this.form.tahun_kendaraan);
                formData.append('id_vehicle_type', this.form.id_vehicle_type);
                formData.append('id_vehicle_model', this.form.id_vehicle_model);
                formData.append('id_vehicle_color', this.form.id_vehicle_color);
                formData.append('id_kategori_permit', this.form.id_kategori_permit);
                if (this.form.keterangan_pengajuan) {
                    formData.append('keterangan_pengajuan', this.form.keterangan_pengajuan);
                }
                formData.append('foto_kendaraan', this.files.foto_kendaraan);
                formData.append('foto_stnk', this.files.foto_stnk);
                formData.append('foto_sim', this.files.foto_sim);
                formData.append('inspeksi_id_pos', this.form.inspeksi?.id_pos ?? '');
                formData.append('inspeksi_mulai', this.form.inspeksi?.mulai || '');

                // Idempotency-Key adalah HEADER, bukan field body; store()
                // menolak permintaan tanpa header ini sebelum validasi apa pun.
                const { data } = await axios.post('/transaksipermit', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                        'Idempotency-Key': this.getOrCreateIdempotencyKey(),
                    },
                });

                if (data && data.success) {
                    this.lastSubmittedResult = data.result || {
                        No_Transaksi: data.No_Transaksi,
                        No_Plat_Kendaraan: this.computedNoPlat,
                    };
                    this.modalStage = 'success';
                    // Reset internal form immediately for safety and memory clearing
                    this.form = this.defaultForm();
                    this.files = this.defaultFiles();
                    this.uploadLists = this.defaultUploadLists();
                    this.idempotencyKey = null;
                } else {
                    this.submitErrorMessage = data?.message || 'Gagal memproses pengajuan permit.';
                    this.modalStage = 'error';
                }
            } catch (error) {
                this.submitErrorMessage = error?.response?.data?.message || 'Terjadi kesalahan sistem saat mengirim data.';
                this.modalStage = 'error';

                // Slotnya direbut pengaju lain: muat ulang daftar agar pemohon
                // langsung bisa memilih yang lain tanpa mengisi ulang formulir.
                if (error?.response?.data?.error_code === 'VISITOR_PERMIT_SLOT_INVALID') {
                    this.form.inspeksi = null;
                    this.fetchSlotInspeksi();
                }

                /*
                 * Batasnya baru penuh SESUDAH layar ini memuat angkanya —
                 * misalnya pengajuan lain dikirim dari perangkat kedua. Minta
                 * angkanya dimuat ulang supaya bannernya muncul sekarang juga,
                 * bukan menunggu halaman dibuka lagi.
                 */
                if (KODE_BATAS_KUOTA.includes(error?.response?.data?.error_code)) {
                    this.$emit('refresh-kuota');
                }
                const kodeGalat = String(error?.response?.data?.error_code || '');
                if (kodeGalat.startsWith('VISITOR_PERMIT_KARTU_NFC_')) {
                    this.kartuNfcDariServer = {
                        boleh: false,
                        status: kodeGalat.replace('VISITOR_PERMIT_KARTU_NFC_', ''),
                        pesan: error?.response?.data?.message || '',
                    };
                }
            } finally {
                this.isSubmitting = false;
            }
        },
    },
};
</script>

<style scoped>
.permit-slot-alert {
    margin-bottom: var(--hcis-space-3, 0.75rem);
}

.permit-slot-note {
    margin: var(--hcis-space-3, 0.75rem) 0 0;
    font-size: 0.8rem;
    color: var(--hcis-text-secondary, var(--hcis-slate-500));
}

.visitor-permit-form,
.visitor-permit-form-skeleton {
    width: 100%;
}

/* ═════════════════════════════════════════════════════════════════════════
   QUOTA REACHED COMPACT BANNER
   ═════════════════════════════════════════════════════════════════════════ */
.permit-quota-hero-banner {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.95) 0%, rgba(255, 241, 242, 0.85) 100%);
    border: 1.5px solid #fecdd3;
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.85rem 1.15rem;
    box-shadow: 0 2px 10px rgba(225, 29, 72, 0.06);
}

.permit-quota-content-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.permit-quota-icon-badge {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    background: var(--hcis-rose-100);
    color: var(--hcis-rose-600);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(225, 29, 72, 0.15);
}

.permit-quota-text-body {
    flex: 1 1 200px;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.permit-quota-header-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-quota-hero-title {
    font-size: 0.9375rem;
    font-weight: 800;
    color: #9f1239;
    letter-spacing: -0.01em;
    margin: 0;
    line-height: 1.25;
}

.permit-quota-hero-desc {
    font-size: 0.78125rem;
    color: #881337;
    line-height: 1.4;
    margin: 0;
}

.permit-quota-actions-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.permit-quota-btn {
    font-weight: 700;
}

.permit-card-locked-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65625rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

.visitor-permit-fieldset {
    border: none;
    margin: 0;
    padding: 0;
    min-width: 0;
    width: 100%;
}

.visitor-permit-form__sections.is-disabled {
    opacity: 0.65;
    pointer-events: none;
    user-select: none;
    filter: grayscale(20%);
    transition: opacity 0.2s ease;
}

/* ═════════════════════════════════════════════════════════════════════════
   LAYOUT & FORM SECTIONS
   ═════════════════════════════════════════════════════════════════════════ */
.visitor-permit-form__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: var(--hcis-space-6, 1.5rem);
    align-items: start;
}

.visitor-permit-form__sections {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-6, 1.5rem);
}

.visitor-vehicle-grid {
    display: grid;
    grid-template-columns: 1.4fr 0.8fr 1fr;
    grid-template-areas:
        'plate plate year'
        'type model color';
    gap: var(--hcis-space-4, 1rem);
}
.visitor-vehicle-grid__plate {
    grid-area: plate;
}
.visitor-vehicle-grid__year {
    grid-area: year;
}
.visitor-vehicle-grid__type {
    grid-area: type;
}
.visitor-vehicle-grid__model {
    grid-area: model;
}
.visitor-vehicle-grid__color {
    grid-area: color;
}

.permit-category-picker {
    width: 100%;
}
.permit-category-picker :deep(.hcis-radio-group) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: var(--hcis-space-3, 0.75rem);
}
.permit-category-picker :deep(.hcis-choice--variant-card) {
    --permit-category-accent: var(--hcis-brand-600, var(--hcis-indigo-600));
    --permit-category-accent-rgb: 79, 70, 229;
    --permit-category-tint: var(--hcis-brand-50, var(--hcis-indigo-50));
    width: 100%;
    min-width: 0;
    min-height: 9.5rem;
    margin: 0;
    padding: var(--hcis-space-4, 1rem) var(--hcis-space-5, 1.25rem);
    border: 1.5px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-lg, 0.875rem);
    background: var(--hcis-surface-raised, var(--hcis-white, #fff));
    box-shadow: var(--hcis-shadow-xs, 0 1px 2px rgb(var(--hcis-slate-900-rgb) / 0.06));
    transition:
        transform var(--hcis-duration-fast, 160ms) var(--hcis-ease, ease),
        border-color var(--hcis-duration-fast, 160ms) var(--hcis-ease, ease),
        box-shadow var(--hcis-duration-fast, 160ms) var(--hcis-ease, ease),
        background var(--hcis-duration-fast, 160ms) var(--hcis-ease, ease);
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.permit-category-picker :deep(.hcis-choice--color-danger) {
    --permit-category-accent: var(--hcis-danger-600, var(--hcis-red-600));
    --permit-category-accent-rgb: 220, 38, 38;
    --permit-category-tint: var(--hcis-danger-50, var(--hcis-red-50));
}
.permit-category-picker :deep(.hcis-choice--color-success) {
    --permit-category-accent: var(--hcis-green-600, var(--hcis-green-600));
    --permit-category-accent-rgb: 22, 163, 74;
    --permit-category-tint: var(--hcis-green-50, var(--hcis-green-50));
}
.permit-category-picker :deep(.hcis-choice--color-warning) {
    --permit-category-accent: var(--hcis-warning-600, var(--hcis-amber-600));
    --permit-category-accent-rgb: 217, 119, 6;
    --permit-category-tint: var(--hcis-warning-50, var(--hcis-amber-50));
}
.permit-category-picker :deep(.hcis-choice--color-brand) {
    --permit-category-accent: var(--hcis-brand-600, var(--hcis-indigo-600));
    --permit-category-accent-rgb: 79, 70, 229;
    --permit-category-tint: var(--hcis-brand-50, var(--hcis-indigo-50));
}
.permit-category-picker :deep(.hcis-choice--color-purple) {
    --permit-category-accent: var(--hcis-purple-600);
    --permit-category-accent-rgb: 147, 51, 234;
    --permit-category-tint: #faf5ff;
}
.permit-category-picker :deep(.hcis-choice--variant-card:hover:not(.is-disabled)) {
    border-color: var(--permit-category-accent);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    box-shadow: 0 10px 22px -16px rgba(var(--permit-category-accent-rgb), 0.65);
    transform: translateY(-2px);
}
.permit-category-picker :deep(.hcis-choice--variant-card:focus-within) {
    border-color: var(--permit-category-accent);
    outline: 3px solid rgba(var(--permit-category-accent-rgb), 0.2);
    outline-offset: 2px;
}
.permit-category-picker :deep(.hcis-choice--variant-card.is-checked) {
    border-color: var(--permit-category-accent);
    background: var(--permit-category-tint);
    box-shadow:
        0 0 0 2px rgba(var(--permit-category-accent-rgb), 0.24),
        0 14px 28px -18px rgba(var(--permit-category-accent-rgb), 0.8);
    transform: translateY(-1px);
}
.permit-category-picker :deep(.hcis-choice__box-wrap) {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 0.15rem;
}
.permit-category-picker :deep(.hcis-choice__body) {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 0.5rem);
}
.permit-category-picker :deep(.hcis-choice__label),
.permit-category-picker :deep(.hcis-choice__hint) {
    overflow-wrap: anywhere;
}
.permit-category-picker :deep(.hcis-choice__label) {
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-size: var(--hcis-font-size-md, 0.9375rem);
    font-weight: 800;
    line-height: 1.3;
}
.permit-category-picker :deep(.hcis-choice__hint) {
    max-width: 32rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: var(--hcis-font-size-sm, 0.8125rem);
    line-height: 1.5;
}
.permit-category-picker :deep(.hcis-choice__visual) {
    width: 2.75rem;
    height: 2.75rem;
    margin-bottom: var(--hcis-space-2, 0.5rem);
    border: 1px solid rgba(var(--permit-category-accent-rgb), 0.25);
    background: var(--permit-category-tint);
    box-shadow: inset 0 0 0 3px var(--hcis-white, #fff);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.permit-category-picker :deep(.hcis-choice__visual .hcis-icon) {
    color: var(--permit-category-accent);
    font-size: 1.35rem;
}
.permit-category-picker :deep(.hcis-choice__radio-circle) {
    border-color: rgba(var(--permit-category-accent-rgb), 0.5);
}
.permit-category-picker :deep(.hcis-choice--variant-card.is-checked .hcis-choice__radio-circle) {
    background: var(--permit-category-accent);
    border-color: var(--permit-category-accent);
    box-shadow: 0 2px 6px rgba(var(--permit-category-accent-rgb), 0.35);
}
.visitor-upload-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}
.visitor-completion-list {
    display: grid;
    gap: 0.35rem;
    padding: 0;
    margin: var(--hcis-space-4, 1rem) 0;
    list-style: none;
}
.visitor-completion-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.78125rem;
    font-weight: 650;
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.3rem 0.5rem;
    transition: all 0.15s ease;
}
.visitor-completion-item.is-clickable {
    cursor: pointer;
}
.visitor-completion-item.is-clickable:hover {
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-800, #1e293b);
}
.visitor-completion-item.is-active {
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-700, #4338ca) !important;
    font-weight: 750;
}
.visitor-completion-item.is-complete {
    color: var(--hcis-success-700, var(--hcis-emerald-700));
}
.visitor-completion-label {
    flex: 1 1 auto;
    min-width: 0;
}
.visitor-completion-pill {
    font-size: 0.625rem;
    font-weight: 800;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    line-height: 1.2;
}

.visitor-summary-data {
    display: grid;
    gap: 0.65rem;
}
.visitor-summary-data > div {
    display: grid;
    gap: 0.15rem;
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.88));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.visitor-summary-data span {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.visitor-summary-data strong {
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    font-size: 0.8125rem;
    font-weight: 750;
}

.visitor-permit-form__summary {
    position: sticky;
    top: 5.5rem;
    align-self: start;
    z-index: 15;
}

.visitor-permit-form__summary :deep(.hcis-card__footer) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
}

.visitor-permit-summary-footer {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    width: 100%;
}

.permit-kuota-badge-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    width: 100%;
    box-sizing: border-box;
}

.permit-kuota-badge-box.is-full {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
}

.visitor-form-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}
.permit-submit-btn {
    width: 100%;
    justify-content: center;
    font-weight: 700;
    letter-spacing: 0.01em;
}
.permit-prev-btn {
    width: 100%;
    justify-content: center;
    font-weight: 600;
}
.permit-reset-btn {
    width: 100%;
    justify-content: center;
    font-weight: 600;
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}

/* Bingkai navigasi langkah (HcisSteps): kaca tipis, selaras kartu form. */
.permit-wizard-stepper-wrap {
    width: 100%;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.88) 0%, rgba(248, 250, 252, 0.82) 100%);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    border-radius: var(--hcis-radius-xl, 16px);
    padding: 1rem 1.25rem 0.85rem;
    box-shadow: 0 4px 20px -2px rgba(79, 70, 229, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition: all 200ms ease;
}

.permit-wizard-steps {
    width: 100%;
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none;
}

/* Pastikan ketiga tahap proporsional seimbang dan CENTERED di desktop, tablet & mobile */
.permit-wizard-steps :deep(.hcis-steps__list--horizontal) {
    display: flex;
    width: 100%;
    align-items: flex-start;
}

.permit-wizard-steps :deep(.hcis-steps__list--horizontal .hcis-steps__item),
.permit-wizard-steps :deep(.hcis-steps__list--horizontal .hcis-steps__item:last-child) {
    flex: 1 1 0 !important;
    min-width: 0 !important;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.permit-wizard-steps :deep(.hcis-steps__head) {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
}

.permit-wizard-steps :deep(.hcis-steps__marker) {
    position: relative;
    z-index: 2;
    margin: 0 auto;
    flex-shrink: 0;
}

/* Track konektor menghubungkan titik tengah marker ke marker berikutnya */
.permit-wizard-steps :deep(.hcis-steps__connector-track) {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 3px;
    margin: 0;
    transform: translateY(-50%);
    z-index: 1;
}

.permit-wizard-steps :deep(.hcis-steps__content) {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
    padding: 0 4px;
}

.permit-wizard-steps :deep(.hcis-steps__title) {
    text-align: center;
    width: 100%;
    display: block;
}

.permit-wizard-steps :deep(.hcis-steps__subtitle) {
    text-align: center;
    width: 100%;
    display: block;
}

@media (max-width: 575.98px) {
    .permit-wizard-stepper-wrap {
        padding: 0.65rem 0.75rem 0.55rem;
        border-radius: 14px;
    }

    .permit-wizard-steps :deep(.hcis-steps__head) {
        margin-bottom: 0.25rem;
    }

    .permit-wizard-steps :deep(.hcis-steps__marker) {
        width: 1.65rem;
        height: 1.65rem;
        font-size: 0.65rem;
    }

    .permit-wizard-steps :deep(.hcis-steps__connector-track) {
        height: 2.5px;
    }

    .permit-wizard-steps :deep(.hcis-steps__content) {
        padding: 0 2px;
    }

    .permit-wizard-steps :deep(.hcis-steps__title) {
        font-size: 0.68rem;
        line-height: 1.2;
        font-weight: 750;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        display: block;
        text-align: center;
    }

    /* Subtitle disembunyikan di header langkah mobile agar ringkas & rapi */
    .permit-wizard-steps :deep(.hcis-steps__subtitle) {
        display: none !important;
    }

    /* Mobile Status Bar di bawah steps */
    .permit-wizard-mobile-status {
        margin-top: 0.45rem;
        padding-top: 0.45rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.9);
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .permit-wizard-mobile-status__left {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex: 1;
    }

    .permit-wizard-mobile-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.12rem 0.45rem;
        border-radius: 6px;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
        border: 1px solid rgba(79, 70, 229, 0.18);
        flex-shrink: 0;
    }

    .permit-wizard-mobile-desc {
        font-size: 0.7rem;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .permit-wizard-mobile-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .permit-wizard-mobile-pill.is-valid {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.25);
    }

    .permit-wizard-mobile-pill.is-filling {
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
        border: 1px solid rgba(79, 70, 229, 0.18);
    }
}

@media (max-width: 360px) {
    .permit-wizard-stepper-wrap {
        padding: 0.5rem 0.55rem 0.45rem;
    }
    .permit-wizard-steps :deep(.hcis-steps__title) {
        font-size: 0.63rem;
    }
    .permit-wizard-mobile-desc {
        font-size: 0.65rem;
    }
}

.permit-wizard-step-pane {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}
.permit-wizard-step-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding-top: 0.35rem;
}
.permit-wizard-step-footer__left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.permit-wizard-step-footer__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

/* ══ WIZARD VERIFICATION REVIEW BOX ══ */
.permit-wizard-review-box {
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.9) 100%);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    padding: 0.9rem 1.15rem;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.04);
}
.permit-wizard-review-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-brand-100, #e0e7ff);
}
.permit-wizard-review-title {
    font-size: 0.84375rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
}
.permit-wizard-review-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem 1rem;
}
.permit-wizard-review-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.permit-wizard-review-label {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.permit-wizard-review-val {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
}

@media (max-width: 1024px) {
    .visitor-permit-form__layout {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--hcis-space-5, 1.25rem);
    }
    .visitor-permit-form__summary {
        position: static;
        max-height: none;
    }
    .visitor-vehicle-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
            'plate plate'
            'year type'
            'model color';
    }
    .visitor-upload-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.visitor-permit-mobile-dock {
    display: none;
}

@media (max-width: 640px) {
    .permit-wizard-step-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }
    .permit-wizard-step-footer__left,
    .permit-wizard-step-footer__right {
        width: 100%;
    }
    .permit-wizard-step-footer :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
    .permit-wizard-review-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.5rem;
    }
    .visitor-permit-form {
        padding-bottom: 5.25rem;
    }
    .visitor-permit-form__summary {
        display: none !important;
    }
    .visitor-permit-mobile-dock {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1040 !important;
        background: rgba(255, 255, 255, 0.94) !important;
        background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.94) 100%) !important;
        backdrop-filter: blur(20px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
        border-top: 1px solid rgba(226, 232, 240, 0.85) !important;
        box-shadow: 0 -8px 25px -4px rgba(15, 23, 42, 0.08), 0 -1px 0 rgba(255, 255, 255, 0.8) inset !important;
        padding: 0.55rem 0.85rem calc(0.55rem + env(safe-area-inset-bottom, 0px)) 0.85rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.65rem !important;
        box-sizing: border-box !important;
    }
    .visitor-permit-mobile-dock__progress-track {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: rgba(226, 232, 240, 0.6);
        overflow: hidden;
    }
    .visitor-permit-mobile-dock__progress-fill {
        height: 100%;
        transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .visitor-permit-mobile-dock__progress-fill.is-warning {
        background: linear-gradient(90deg, var(--hcis-amber-500), #eab308);
    }
    .visitor-permit-mobile-dock__progress-fill.is-success {
        background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
    }
    .visitor-permit-mobile-dock__progress-fill.is-danger {
        background: linear-gradient(90deg, var(--hcis-red-500), var(--hcis-red-600));
    }
    .visitor-permit-mobile-dock__info {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        cursor: pointer;
        flex: 1 1 auto;
        min-width: 0;
        padding: 0.25rem 0.45rem;
        border-radius: 8px;
        transition: all 0.2s ease;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }
    .visitor-permit-mobile-dock__info:hover,
    .visitor-permit-mobile-dock__info:active {
        background: rgba(241, 245, 249, 0.8);
        transform: scale(0.985);
    }
    .visitor-permit-mobile-dock__progress-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 800;
        flex-shrink: 0;
        line-height: 1;
    }
    .visitor-permit-mobile-dock__progress-badge.is-success {
        background: var(--hcis-emerald-50);
        color: var(--hcis-emerald-800);
        border: 1px solid var(--hcis-emerald-200);
    }
    .visitor-permit-mobile-dock__progress-badge.is-warning {
        background: var(--hcis-amber-50);
        color: var(--hcis-amber-800);
        border: 1px solid var(--hcis-amber-200);
    }
    .visitor-permit-mobile-dock__progress-badge.is-danger {
        background: var(--hcis-red-50);
        color: var(--hcis-red-800);
        border: 1px solid var(--hcis-red-200);
    }
    .visitor-permit-mobile-dock__text {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
    }
    .visitor-permit-mobile-dock__title {
        font-size: 0.78125rem;
        font-weight: 800;
        color: var(--hcis-slate-900, var(--hcis-slate-900));
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .visitor-permit-mobile-dock__sub {
        font-size: 0.65625rem;
        color: var(--hcis-brand-600, var(--hcis-indigo-600));
        font-weight: 700;
        line-height: 1.2;
        display: flex;
        align-items: center;
        gap: 0.2rem;
    }
    .visitor-permit-mobile-dock__action {
        flex-shrink: 0;
    }
    .permit-mobile-submit-btn {
        height: 2.35rem !important;
        border-radius: 8px !important;
        font-weight: 750 !important;
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
        font-size: 0.78125rem !important;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25) !important;
    }
    .permit-sheet-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--hcis-slate-500, var(--hcis-slate-500));
    }
    .visitor-upload-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .visitor-upload-grid :deep(.hcis-upload__dragger) {
        padding: 0.85rem 1rem !important;
        min-height: auto !important;
        border-radius: 10px !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__drop-content) {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        text-align: left !important;
        gap: 0.75rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__icon-badge) {
        width: 2.25rem !important;
        height: 2.25rem !important;
        font-size: 1rem !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
        margin-bottom: 0 !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__title) {
        font-size: 0.78125rem !important;
        line-height: 1.3 !important;
        font-weight: 700 !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__hint) {
        font-size: 0.6875rem !important;
        margin-top: 0.1rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__label) {
        font-size: 0.75rem !important;
        font-weight: 750 !important;
        margin-bottom: 0.3rem !important;
        color: var(--hcis-slate-700, var(--hcis-slate-700)) !important;
    }
    .visitor-vehicle-grid {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas:
            'plate'
            'year'
            'type'
            'model'
            'color';
    }
    .visitor-vehicle-grid__plate {
        width: 100%;
    }
    .permit-category-picker :deep(.hcis-radio-group) {
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }
    .permit-category-picker :deep(.hcis-choice--variant-card) {
        min-height: auto !important;
        padding: 0.75rem 0.85rem !important;
        border-radius: 10px !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.65rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__visual) {
        width: 2.25rem !important;
        height: 2.25rem !important;
        min-width: 2.25rem !important;
        margin-bottom: 0 !important;
        border-radius: 7px !important;
    }
    .permit-category-picker :deep(.hcis-choice__visual .hcis-icon) {
        font-size: 1.1rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__body) {
        gap: 0.15rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__label) {
        font-size: 0.84375rem !important;
        font-weight: 800 !important;
        line-height: 1.25 !important;
    }
    .permit-category-picker :deep(.hcis-choice__hint) {
        font-size: 0.71875rem !important;
        line-height: 1.35 !important;
    }
    .permit-category-picker :deep(.hcis-choice__box-wrap) {
        margin-top: 0 !important;
        align-self: center !important;
    }
    .visitor-permit-form :deep(.hcis-choice--variant-card) {
        padding: 0.75rem 0.85rem !important;
        border-radius: 10px !important;
        gap: 0.65rem !important;
    }
    .visitor-permit-form :deep(.hcis-choice--variant-card .hcis-choice__label) {
        font-size: 0.8125rem !important;
        font-weight: 750 !important;
        line-height: 1.3 !important;
    }
    .visitor-permit-form :deep(.hcis-choice--variant-card .hcis-choice__hint) {
        font-size: 0.71875rem !important;
        line-height: 1.35 !important;
        margin-top: 0.15rem !important;
    }
    .visitor-permit-form :deep(.hcis-textarea textarea) {
        min-height: 4.5rem !important;
        font-size: 0.8125rem !important;
        padding: 0.65rem 0.75rem !important;
    }
    .permit-quota-hero-banner {
        padding: 0.75rem 0.9rem;
    }
    .permit-quota-content-row {
        gap: 0.65rem;
    }
    .permit-quota-icon-badge {
        width: 2rem;
        height: 2rem;
        border-radius: 6px;
    }
    .permit-quota-hero-title {
        font-size: 0.875rem;
    }
    .permit-quota-hero-desc {
        font-size: 0.71875rem;
    }
    .permit-quota-actions-row {
        width: 100%;
        margin-top: 0.35rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.45rem;
    }
    .permit-quota-actions-row :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        height: 2.15rem;
    }
    :deep(.permit-confirm-dialog .hcis-dialog__description),
    :deep(.hcis-dialog__description),
    :deep(.permit-confirm-dialog .hcis-result-state__description) {
        display: none !important;
    }
    :deep(.permit-confirm-dialog .hcis-result-state__title) {
        font-size: 1rem !important;
        margin-bottom: 0.35rem !important;
    }
    :deep(.permit-confirm-dialog .hcis-receipt) {
        margin-top: 0.35rem !important;
    }
    .visitor-dialog-actions {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        width: 100% !important;
    }
    .visitor-dialog-actions :deep(.hcis-btn) {
        flex: 1 1 50% !important;
        width: auto !important;
        height: 2.35rem !important;
        font-size: 0.8125rem !important;
        font-weight: 700 !important;
        justify-content: center !important;
        padding: 0 0.5rem !important;
    }
}

@media (max-width: 380px) {
    .permit-card-locked-tag {
        padding: 0.1rem 0.35rem !important;
        font-size: 0.59375rem !important;
        gap: 0.2rem !important;
    }
    .visitor-permit-mobile-dock {
        padding: 0.45rem 0.6rem calc(0.45rem + env(safe-area-inset-bottom, 0px)) 0.6rem !important;
        gap: 0.45rem !important;
    }
    .visitor-permit-mobile-dock__info {
        padding: 0.15rem 0.35rem !important;
        gap: 0.4rem !important;
    }
    .visitor-permit-mobile-dock__progress-badge {
        padding: 0.15rem 0.4rem !important;
        font-size: 0.625rem !important;
        gap: 0.2rem !important;
    }
    .visitor-permit-mobile-dock__title {
        font-size: 0.71875rem !important;
    }
    .visitor-permit-mobile-dock__sub {
        font-size: 0.59375rem !important;
    }
    .permit-mobile-submit-btn {
        height: 2.15rem !important;
        padding-left: 0.65rem !important;
        padding-right: 0.65rem !important;
        font-size: 0.71875rem !important;
    }
    .permit-category-picker :deep(.hcis-choice--variant-card) {
        padding: 0.55rem 0.65rem !important;
        gap: 0.45rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__visual) {
        width: 1.85rem !important;
        height: 1.85rem !important;
        min-width: 1.85rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__visual .hcis-icon) {
        font-size: 0.9375rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__label) {
        font-size: 0.78125rem !important;
    }
    .permit-category-picker :deep(.hcis-choice__hint) {
        font-size: 0.65625rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__dragger) {
        padding: 0.65rem 0.75rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__icon-badge) {
        width: 1.85rem !important;
        height: 1.85rem !important;
        font-size: 0.875rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__title) {
        font-size: 0.71875rem !important;
    }
    .visitor-upload-grid :deep(.hcis-upload__hint) {
        font-size: 0.625rem !important;
    }
}

/* ══ INSPECTION GUIDELINE STEPPER & RULES ══ */
.permit-inspeksi-guideline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(90deg, var(--hcis-brand-50, #eef2ff) 0%, var(--hcis-surface-sunken, #f8fafc) 100%);
    border: 1px solid var(--hcis-brand-100, #e0e7ff);
}

.permit-inspeksi-guideline__step {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.permit-inspeksi-guideline__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 800;
    flex-shrink: 0;
}

.permit-inspeksi-guideline__text {
    line-height: 1.25;
}

.permit-inspeksi-guideline__divider {
    flex: 1 1 auto;
    height: 1px;
    background: var(--hcis-border-subtle, rgba(79, 70, 229, 0.2));
    margin: 0 0.25rem;
}

.permit-slot-rules {
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: var(--hcis-surface-sunken, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
}

/* ═════════════════════════════════════════════════════════════════════════
   CLEAN FORM STEP GRID & FIELDS
   ═════════════════════════════════════════════════════════════════════════ */
.permit-section-divider-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78125rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-700, #334155);
    padding-bottom: 0.45rem;
    border-bottom: 1px solid var(--hcis-border, #e2e8f0);
}

.permit-step-grid {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.permit-form-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--hcis-space-4, 1rem);
}

.permit-form-row.two-cols-plate {
    display: grid;
    grid-template-columns: 1.5fr 0.8fr;
    gap: var(--hcis-space-4, 1rem);
}

.permit-form-row.three-cols {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

.permit-form-col {
    min-width: 0;
}

.permit-upload-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

/* ═════════════════════════════════════════════════════════════════════════
   STEP 4: SHE RULES CALLOUT & EXECUTIVE REVIEW PASS
   ═════════════════════════════════════════════════════════════════════════ */
.permit-she-rules-callout {
    display: flex;
    flex-direction: column;
    padding: 0;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.03);
    overflow: hidden;
    transition: all 0.2s ease;
}

.permit-she-rules-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    width: 100%;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.15s ease;
}

.permit-she-rules-header:hover {
    background: rgba(238, 242, 255, 0.4);
}

.permit-she-header-main {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.permit-she-header-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.permit-she-icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.08);
    flex-shrink: 0;
}

.permit-she-rules-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-she-rules-subtitle {
    display: block;
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.25;
    margin-top: 0.1rem;
}

.permit-she-header-actions,
.permit-review-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.permit-accordion-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: rgba(241, 245, 249, 0.85);
    color: var(--hcis-slate-600, #475569);
    transition: transform 250ms cubic-bezier(0.16, 1, 0.3, 1), background 0.15s ease;
    flex-shrink: 0;
}

.permit-accordion-chevron.is-rotated {
    transform: rotate(180deg);
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
}

.permit-she-rules-body {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 280ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
    opacity: 0;
    overflow: hidden;
}

.permit-she-rules-callout.is-expanded .permit-she-rules-body {
    grid-template-rows: 1fr;
    opacity: 1;
    border-top: 1px solid var(--hcis-brand-100, #e0e7ff);
}

.permit-she-rules-body-inner {
    min-height: 0;
    overflow: hidden;
    padding: 0.85rem 1.25rem 1.15rem;
}

.permit-she-policy-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.permit-she-policy-item {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.75rem 0.85rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.permit-she-policy-item__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.permit-she-policy-item__icon.is-indigo {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

.permit-she-policy-item__icon.is-amber {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fcd34d;
}

.permit-she-policy-item__icon.is-emerald {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.permit-she-policy-item__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.permit-she-policy-item__text .policy-title {
    font-size: 0.78125rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-she-policy-item__text .policy-desc {
    font-size: 0.6875rem;
    color: var(--hcis-slate-600, #475569);
    line-height: 1.35;
    margin: 0;
}

.permit-she-policy-item__text .policy-desc strong {
    color: var(--hcis-slate-900, #0f172a);
}

/* ── EXECUTIVE REVIEW CARD ── */
.permit-executive-review-card {
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1.5px solid var(--hcis-border, #e2e8f0);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    margin-top: 0.25rem;
    transition: all 0.2s ease;
}

.permit-executive-review-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1.15rem;
    background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
    border: none;
    cursor: pointer;
    text-align: left;
    width: 100%;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.15s ease;
}

.permit-executive-review-header:hover {
    background: #f1f5f9;
}

.permit-executive-review-card.is-expanded .permit-executive-review-header {
    border-bottom: 1.5px solid var(--hcis-border, #e2e8f0);
}

.permit-review-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.permit-review-header-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.permit-review-header-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.08);
    flex-shrink: 0;
}

.permit-review-card-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-review-card-sub {
    display: block;
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.25;
    margin-top: 0.1rem;
}

.permit-executive-review-content {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 280ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
    opacity: 0;
    overflow: hidden;
}

.permit-executive-review-card.is-expanded .permit-executive-review-content {
    grid-template-rows: 1fr;
    opacity: 1;
}

.permit-executive-review-content-inner {
    min-height: 0;
    overflow: hidden;
}

.permit-executive-review-body {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.permit-review-col {
    padding: 1rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    background: #ffffff;
    border-right: 1px dashed var(--hcis-border, #e2e8f0);
}

.permit-review-col:last-child {
    border-right: none;
}

.permit-review-col--schedule {
    background: linear-gradient(180deg, #ffffff 0%, rgba(238, 242, 255, 0.3) 100%);
}

.permit-review-col-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500, #64748b);
}

.permit-review-col-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permit-review-plate-badge {
    display: inline-block;
    align-self: flex-start;
    background: #0f172a;
    color: #ffffff;
    font-weight: 800;
    font-size: 0.9375rem;
    letter-spacing: 0.06em;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    border: 1.5px solid #334155;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}

.permit-review-spec-line {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.permit-review-spec-line .spec-main {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.25;
}

.permit-review-spec-line .spec-sub {
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, #64748b);
}

.permit-review-color-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
}

.permit-review-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.18);
    flex-shrink: 0;
}

.permit-review-category-badge {
    align-self: flex-start;
}

.permit-review-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.permit-review-doc-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-400, #94a3b8);
    transition: color 0.2s ease;
}

.permit-review-doc-pill.is-attached {
    color: var(--hcis-emerald-600, #059669);
    font-weight: 700;
}

.permit-review-ticket {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.65rem 0.85rem;
    background: #eef2ff;
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    border-radius: var(--hcis-radius-md, 8px);
}

.permit-review-ticket-date {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-brand-800, #3730a3);
}

.permit-review-ticket-time {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-brand-700, #4338ca);
}

.permit-review-ticket-pos {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.71875rem;
    color: var(--hcis-brand-600, #4f46e5);
}

.permit-review-ticket-empty {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem;
    background: #fffbeb;
    border: 1px dashed #fcd34d;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    font-weight: 600;
    color: #b45309;
}

@media (max-width: 1024px) {
    .permit-upload-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .permit-form-row.two-cols-plate {
        grid-template-columns: minmax(0, 1fr);
    }
    .permit-form-row.three-cols {
        grid-template-columns: minmax(0, 1fr);
    }
    .permit-upload-grid {
        grid-template-columns: minmax(0, 1fr);
    }
    .permit-executive-review-body {
        grid-template-columns: minmax(0, 1fr);
    }
    .permit-she-rules-callout {
        border-radius: var(--hcis-radius-md, 10px);
    }
    .permit-she-rules-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0.85rem;
        gap: 0.6rem;
    }
    .permit-she-header-main {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex: 1;
        min-width: 0;
    }
    .permit-she-icon-badge {
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
        flex-shrink: 0;
    }
    .permit-she-header-text {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
        flex: 1;
    }
    .permit-she-rules-title {
        font-size: 0.8125rem;
        font-weight: 800;
        color: var(--hcis-slate-900, #0f172a);
        line-height: 1.3;
    }
    .permit-she-rules-subtitle {
        display: none !important;
    }
    .permit-she-badge-mobile {
        display: inline-flex;
        align-self: flex-start;
    }
    .permit-she-header-actions {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .permit-she-rules-body-inner {
        padding: 0.65rem 0.85rem 0.85rem;
    }
    .permit-she-policy-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.5rem;
    }
    .permit-she-policy-item {
        padding: 0.7rem 0.8rem;
        gap: 0.65rem;
    }
    .permit-she-policy-item__icon {
        width: 2rem;
        height: 2rem;
        border-radius: 7px;
    }
    .permit-she-policy-item__text .policy-title {
        font-size: 0.78125rem;
    }
    .permit-she-policy-item__text .policy-desc {
        font-size: 0.71875rem;
        line-height: 1.35;
    }

    /* Executive Review Card Mobile */
    .permit-executive-review-card {
        border-radius: var(--hcis-radius-md, 10px);
    }
    .permit-executive-review-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0.85rem;
        gap: 0.6rem;
    }
    .permit-review-header-left {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex: 1;
        min-width: 0;
    }
    .permit-review-header-badge {
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
        flex-shrink: 0;
    }
    .permit-review-header-text {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
        flex: 1;
    }
    .permit-review-card-title {
        font-size: 0.8125rem;
        font-weight: 800;
        color: var(--hcis-slate-900, #0f172a);
        line-height: 1.3;
    }
    .permit-review-card-sub {
        display: none !important;
    }
    .permit-review-badge-mobile {
        display: inline-flex;
        align-self: flex-start;
    }
    .permit-review-header-actions {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .permit-accordion-chevron {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 7px;
    }
    .permit-review-col {
        border-right: none;
        border-bottom: 1px dashed var(--hcis-border, #e2e8f0);
        padding: 0.85rem 0.85rem;
    }
    .permit-review-col:last-child {
        border-bottom: none;
    }
}

/* ═════════════════════════════════════════════════════════════════════════
   MODAL CONFIRMATION EXECUTIVE REVIEW CARD
   ═════════════════════════════════════════════════════════════════════════ */
.permit-confirm-stage {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.permit-modal-review-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    margin-top: 0.75rem;
    padding: 1rem 1.15rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.05);
    text-align: left;
    box-sizing: border-box;
}

.permit-modal-review-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px dashed var(--hcis-border, #e2e8f0);
}

.permit-modal-review-title {
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
}

.permit-modal-section {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.permit-modal-section-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500, #64748b);
}

.permit-modal-vehicle-hero {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.permit-modal-plate {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.75rem;
    background: #0f172a;
    color: #ffffff;
    border-radius: 6px;
    font-family: 'JetBrains Mono', 'Fira Code', var(--hcis-font-mono, monospace);
    font-size: 0.9375rem;
    font-weight: 850;
    letter-spacing: 0.08em;
    border: 1.5px solid #334155;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 2px 5px rgba(15, 23, 42, 0.15);
    flex-shrink: 0;
}

.permit-modal-vehicle-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.permit-modal-model-text strong {
    font-size: 0.84375rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-modal-sub-meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-600, #475569);
    font-weight: 600;
    flex-wrap: wrap;
}

.permit-modal-color-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.permit-modal-color-dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}

.permit-modal-dot-separator {
    color: var(--hcis-slate-300, #cbd5e1);
}

.permit-modal-access-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
}

.permit-modal-access-badge-wrap,
.permit-modal-docs-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.permit-modal-field-kicker {
    font-size: 0.65625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, #94a3b8);
}

.permit-modal-docs-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.permit-modal-doc-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-size: 0.6875rem;
    font-weight: 750;
    background: var(--hcis-emerald-50, #ecfdf5);
    color: var(--hcis-emerald-700, #047857);
    border: 1px solid var(--hcis-emerald-200, #a7f3d0);
}

.permit-modal-doc-tag:not(.is-attached) {
    background: var(--hcis-slate-50, #f8fafc);
    color: var(--hcis-slate-400, #94a3b8);
    border-color: var(--hcis-slate-200, #e2e8f0);
}

.permit-modal-inspection-card {
    display: grid;
    grid-template-columns: 1fr auto 1.3fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: linear-gradient(135deg, #f0f4ff 0%, #eef2ff 100%);
    border: 1px solid #c7d2fe;
    border-radius: var(--hcis-radius-md, 8px);
}

.permit-modal-inspection-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}

.permit-modal-inspection-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.permit-modal-inspection-icon.is-indigo {
    background: #ffffff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.08);
}

.permit-modal-inspection-icon.is-amber {
    background: #ffffff;
    color: #d97706;
    border: 1px solid #fcd34d;
    box-shadow: 0 1px 3px rgba(217, 119, 6, 0.08);
}

.permit-modal-inspection-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.insp-label {
    font-size: 0.65625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, #64748b);
}

.insp-val {
    font-size: 0.78125rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-modal-inspection-divider {
    width: 1px;
    height: 1.75rem;
    background: #c7d2fe;
}

.permit-modal-note-box {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.55rem 0.75rem;
    background: #ffffff;
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: var(--hcis-radius-md, 8px);
}

.permit-modal-note-label {
    font-size: 0.65625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, #94a3b8);
}

.permit-modal-note-text {
    font-size: 0.75rem;
    color: var(--hcis-slate-700, #334155);
    font-style: italic;
    margin: 0;
    line-height: 1.35;
}

@media (max-width: 640px) {
    .permit-modal-vehicle-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .permit-modal-access-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.5rem;
    }
    .permit-modal-inspection-card {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.5rem;
    }
    .permit-modal-inspection-divider {
        display: none;
    }
    .permit-modal-inspection-item {
        width: 100%;
    }
}

/* Panel lipat (HcisCollapse, 30 Sep 2026). Lencana status ringkasan membawa
   warnanya sendiri; pembungkus pil bawaan item dinetralkan supaya tidak
   tampil sebagai pil di dalam pil. */
.permit-executive-review-collapse :deep(.hcis-collapse-item__badge) {
    padding: 0;
    background: none;
    border: 0;
}

/* Di ponsel kepala panel cukup judul + lencana — sama dengan desain
   sebelumnya yang menyembunyikan subjudul di layar sempit. */
@media (max-width: 767.98px) {
    .permit-she-rules-collapse :deep(.hcis-collapse-item__description),
    .permit-executive-review-collapse :deep(.hcis-collapse-item__description) {
        display: none;
    }
}
</style>
