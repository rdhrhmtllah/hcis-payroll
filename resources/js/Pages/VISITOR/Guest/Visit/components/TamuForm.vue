<template>
    <form class="visitor-guest-form" novalidate @submit.prevent="submitForm">
        <!-- Satu banner per sebab. Dulu banner approver menyala untuk SEMUA blokir,
             termasuk pengaju B2B tanpa unit, padahal alur approval-nya sehat. -->
        <HcisAlert
            v-if="fiturNonaktif"
            variant="warning"
            appearance="glass"
            title="Registrasi tamu sedang dinonaktifkan"
            description="Admin Visitor menonaktifkan pengajuan tamu untuk sementara. Data tetap dapat dilengkapi, tetapi belum dapat dikirim."
            icon="pause-circle"
        />

        <HcisAlert
            v-else-if="tanpaApprover"
            variant="warning"
            appearance="glass"
            title="Registrasi belum dapat dikirim"
            description="Alur persetujuan tamu belum memiliki approver aktif. Data tetap dapat dilengkapi sambil menunggu konfigurasi diperbarui."
            icon="person-exclamation"
        />

        <HcisAlert
            v-if="isB2b && !b2bUnits.length"
            variant="warning"
            appearance="glass"
            title="Anda belum terdaftar sebagai pengaju tamu B2B"
            description="Tamu B2B hanya dapat diajukan departemen Procurement atau Logistic. Hubungi admin Visitor untuk didaftarkan ke role departemen Anda."
            icon="person-lock"
        />

        <div class="visitor-guest-form__layout">
            <div class="visitor-guest-form__sections">
                <HcisCard
                    title="Identitas tamu"
                    description="Catat identitas dan kontak utama yang dapat dihubungi selama kunjungan."
                    icon="person-vcard-fill"
                    sentiment="brand"
                    variant="glass"
                    size="lg"
                    divider
                >
                    <div class="visitor-form-grid visitor-form-grid--identity">
                        <HcisField
                            id="guest-name"
                            v-model="form.nama_tamu"
                            label="Nama lengkap tamu"
                            placeholder="Masukkan nama sesuai identitas"
                            leading-icon="person-fill"
                            required
                            clearable
                        />
                        <HcisPhoneInput
                            id="guest-phone"
                            v-model="form.no_hp"
                            :country-code="form.kode_negara_hp || '+62'"
                            label="Nomor HP atau telepon"
                            placeholder="812 3456 7890"
                            hint="Format otomatis tanpa awalan 0."
                            required
                            @update:country-code="form.kode_negara_hp = $event"
                        />
                        <HcisField
                            id="guest-company"
                            v-model="form.instansi_asal"
                            label="Instansi atau perusahaan"
                            placeholder="Nama perusahaan, vendor, atau instansi"
                            leading-icon="buildings-fill"
                            required
                            clearable
                        />
                        <HcisIdentityInput
                            id="guest-identity-value"
                            v-model="form.no_identitas"
                            :type-value="form.jenis_identitas"
                            :type-options="identityOptions"
                            :max-length="50"
                            label="Identitas tamu"
                            number-placeholder="Masukkan nomor identitas"
                            hint="Opsional, tetapi membantu proses verifikasi."
                            inputmode="text"
                            @update:type-value="form.jenis_identitas = $event"
                        />
                    </div>

                    <div v-if="isB2b && b2bUnits.length" class="mt-3 visitor-b2b-unit">
                        <HcisField
                            v-if="b2bUnits.length > 1"
                            id="guest-b2b-unit"
                            label="Diajukan sebagai"
                            required
                            hint="Anda berhak mengajukan untuk lebih dari satu unit."
                        >
                            <HcisSelect
                                id="guest-b2b-unit"
                                v-model="form.b2b_unit"
                                placeholder="Pilih unit pengaju"
                                leading-icon="diagram-3-fill"
                                aria-label="Unit pengaju tamu B2B"
                            >
                                <HcisSelectOption
                                    v-for="unit in b2bUnits"
                                    :key="unit"
                                    :value="unit"
                                    :label="labelUnit(unit)"
                                    icon="truck"
                                />
                            </HcisSelect>
                        </HcisField>
                        <HcisBadge
                            v-else
                            :label="labelUnit(b2bUnits[0])"
                            variant="brand"
                            appearance="subtle"
                            icon="truck"
                        />
                    </div>

                    <div v-if="isB2b" class="mt-3">
                        <HcisAlert
                            variant="warning"
                            appearance="soft"
                            size="sm"
                            title="Izin Akses Gerbang (Permit Coklat)"
                            description="Pengajuan kunjungan tamu mitra kerja (B2B) memerlukan verifikasi izin akses gerbang oleh Verifikator Permit sebelum tamu dapat memasuki area perusahaan."
                            badge="Memerlukan Otorisasi"
                            icon="shield-lock-fill"
                        />
                    </div>
                </HcisCard>

                <HcisCard
                    title="Transportasi dan jumlah rombongan"
                    description="Tentukan moda kedatangan serta daftar seluruh rombongan yang berkunjung."
                    icon="car-front-fill"
                    sentiment="brand"
                    variant="glass"
                    size="lg"
                    divider
                >
                    <!-- Sub-section 1: Moda Kedatangan & Plat Kendaraan -->
                    <div class="visitor-transport-section">
                        <div class="visitor-transport-section__header">
                            <div class="visitor-transport-section__title">
                                <HcisIcon name="signpost-split-fill" size="xs" />
                                <span>Moda Kedatangan</span>
                            </div>
                        </div>

                        <div
                            class="visitor-form-grid visitor-form-grid--transport"
                            :class="{ 'visitor-form-grid--transport-driving': !isVehicleDisabled }"
                        >
                            <HcisField
                                class="visitor-form-grid__vehicle"
                                id="guest-vehicle-type"
                                label="Jenis kendaraan / moda"
                                :required="isB2b"
                                :hint="isB2b ? 'Tamu B2B wajib berkendaraan.' : ''"
                            >
                                <HcisSelect
                                    id="guest-vehicle-type"
                                    v-model="form.jenis_kendaraan"
                                    placeholder="Pilih moda kedatangan"
                                    leading-icon="signpost-split-fill"
                                    aria-label="Moda kedatangan tamu"
                                >
                                    <HcisSelectOption
                                        v-for="type in vehicleOptions"
                                        :key="type.id"
                                        :value="type.value"
                                        :label="type.label"
                                        :icon="type.icon"
                                    />
                                </HcisSelect>
                            </HcisField>

                            <!-- If driving / vehicle selected: Plat number input -->
                            <HcisPlateInput
                                v-if="!isVehicleDisabled"
                                class="visitor-form-grid__plate"
                                id="guest-plat-number"
                                v-model="form.no_plat"
                                :prefix-options="masterPlatCodes"
                                label="Nomor plat kendaraan"
                                :required="isB2b"
                                :hint="
                                    isB2b
                                        ? 'Wajib lengkap: kode wilayah, nomor, dan kode belakang.'
                                        : 'Opsional. Kode wilayah, nomor, dan kode belakang.'
                                "
                            />

                            <!-- If non-driving: Sleek informative banner -->
                            <div v-else class="visitor-transport-notice">
                                <div class="visitor-transport-notice__icon">
                                    <HcisIcon name="person-walking" size="sm" />
                                </div>
                                <div class="visitor-transport-notice__content">
                                    <span class="visitor-transport-notice__title">Tanpa Kendaraan Pribadi</span>
                                    <span class="visitor-transport-notice__sub">Nomor plat tidak diperlukan untuk pejalan kaki / drop-off.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Foto kendaraan tamu B2B: opsional, rujukan inspeksi verifikator -->
                    <div v-if="isB2b && !isVehicleDisabled" class="visitor-transport-section mt-3">
                        <HcisUpload
                            v-model="form.foto_kendaraan"
                            :accept="fotoAccept"
                            :max-size="fotoMaksMb"
                            :limit="1"
                            layout="bar"
                            label="Foto kendaraan (opsional)"
                            :hint="`Satu foto ${fotoMimeLabel}, maksimal ${fotoMaksMb} MB. Membantu verifikator saat inspeksi.`"
                            icon="camera-fill"
                        />
                    </div>

                    <!-- Divider between Transport and Rombongan -->
                    <div class="visitor-section-divider" />

                    <!-- Sub-section 2: Jumlah Rombongan & Anggota -->
                    <div class="visitor-guest-form__anggota">
                        <div class="visitor-party-header">
                            <div class="visitor-party-header__title-group">
                                <div class="visitor-party-header__title">
                                    <HcisIcon name="people-fill" size="xs" />
                                    <span>Komposisi Rombongan</span>
                                </div>
                                <p class="visitor-party-header__desc">
                                    Catat rekan atau anggota lain yang datang bersama <strong>{{ form.nama_tamu || 'tamu utama' }}</strong>.
                                </p>
                            </div>

                            <!-- Party Stats & Badge -->
                            <div class="visitor-party-stats">
                                <div class="visitor-party-stat-pill">
                                    <span class="visitor-party-stat-pill__label">Total Rombongan</span>
                                    <span class="visitor-party-stat-pill__value font-tabular">
                                        <HcisIcon name="person-fill" size="2xs" />
                                        {{ jumlahOrang }} Orang
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Party Breakdown Bar -->
                        <div class="visitor-party-breakdown">
                            <div class="visitor-party-chip visitor-party-chip--primary">
                                <div class="visitor-party-chip__avatar">
                                    <HcisIcon name="person-badge-fill" size="xs" />
                                </div>
                                <div class="visitor-party-chip__info">
                                    <span class="visitor-party-chip__role">Tamu Utama</span>
                                    <span class="visitor-party-chip__name">{{ form.nama_tamu || '(Sesuai data identitas)' }}</span>
                                </div>
                                <HcisBadge label="Penanggung Jawab" variant="brand" appearance="subtle" size="xs" class="visitor-party-chip__badge" />
                            </div>

                            <div v-if="form.anggota_lain.length > 0" class="visitor-party-chip-plus">
                                <HcisIcon name="plus" size="xs" />
                            </div>

                            <div v-if="form.anggota_lain.length > 0" class="visitor-party-chip visitor-party-chip--members">
                                <div class="visitor-party-chip__avatar">
                                    <HcisIcon name="people-fill" size="xs" />
                                </div>
                                <div class="visitor-party-chip__info">
                                    <span class="visitor-party-chip__role">Anggota Tambahan</span>
                                    <span class="visitor-party-chip__name font-tabular">{{ form.anggota_lain.length }} Orang</span>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State when 0 additional members -->
                        <div v-if="form.anggota_lain.length === 0" class="visitor-party-empty">
                            <div class="visitor-party-empty__content">
                                <div class="visitor-party-empty__icon">
                                    <HcisIcon name="person-check" size="md" />
                                </div>
                                <div class="visitor-party-empty__text">
                                    <span class="visitor-party-empty__title">Kunjungan Tunggal (1 Tamu)</span>
                                    <span class="visitor-party-empty__desc">
                                        Belum ada anggota rombongan tambahan. Klik tombol di samping jika tamu datang bersama rekan / rombongan.
                                    </span>
                                </div>
                            </div>
                            <HcisButton
                                label="Tambah Anggota Rombongan"
                                leading-icon="person-plus-fill"
                                size="sm"
                                variant="secondary"
                                :disabled="!bolehTambahAnggota"
                                @click="tambahAnggota"
                            />
                        </div>

                        <!-- Member List Cards when >= 1 member -->
                        <div v-else class="visitor-party-list">
                            <div
                                v-for="(anggota, i) in form.anggota_lain"
                                :key="i"
                                class="visitor-party-card"
                            >
                                <div class="visitor-party-card__header">
                                    <div class="visitor-party-card__tag-wrap">
                                        <div class="visitor-party-card__tag">
                                            <HcisIcon name="person-fill" size="2xs" />
                                            <span>Anggota #{{ i + 1 }}</span>
                                        </div>
                                        <span v-if="anggota.nama && String(anggota.nama).trim()" class="visitor-party-card__name-preview">
                                            · {{ anggota.nama }}
                                        </span>
                                    </div>
                                    <HcisButton
                                        label="Hapus"
                                        leading-icon="trash"
                                        size="xs"
                                        variant="danger-quiet"
                                        title="Hapus anggota ini"
                                        @click="hapusAnggota(i)"
                                    />
                                </div>

                                <div class="visitor-party-card__fields">
                                    <HcisField
                                        :id="'anggota-nama-' + i"
                                        v-model="anggota.nama"
                                        label="Nama lengkap anggota"
                                        placeholder="Masukkan nama lengkap anggota"
                                        leading-icon="person"
                                        required
                                        clearable
                                        :error="anggota.nama !== undefined && String(anggota.nama).trim() === '' ? 'Nama wajib diisi.' : ''"
                                    />
                                    <HcisIdentityInput
                                        :id="'anggota-identitas-' + i"
                                        v-model="anggota.no_identitas"
                                        :type-value="anggota.jenis_identitas"
                                        :type-options="identityOptions"
                                        :max-length="50"
                                        label="Identitas anggota"
                                        hint="Opsional (KTP / SIM / Paspor)"
                                        @update:type-value="anggota.jenis_identitas = $event"
                                    />
                                </div>
                            </div>

                            <!-- Bottom Action Bar for List -->
                            <div class="visitor-party-actions">
                                <HcisButton
                                    label="Tambah Anggota Lain"
                                    leading-icon="person-plus-fill"
                                    size="sm"
                                    variant="secondary"
                                    :disabled="!bolehTambahAnggota"
                                    @click="tambahAnggota"
                                />
                                <span class="visitor-party-limit-hint font-tabular">
                                    Maksimal 50 orang per kunjungan (tersisa {{ 50 - jumlahOrang }} slot)
                                </span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <HcisCard
                    title="Jadwal dan tujuan kunjungan"
                    description="Tentukan kebutuhan kunjungan, PIC tujuan, serta waktu kedatangan."
                    icon="calendar-event-fill"
                    sentiment="brand"
                    variant="glass"
                    size="lg"
                    divider
                >
                    <div class="visitor-form-grid visitor-form-grid--two">
                        <HcisField id="guest-purpose" label="Keperluan" required>
                            <HcisSelect
                                id="guest-purpose"
                                v-model="form.keperluan"
                                placeholder="Pilih keperluan"
                                leading-icon="briefcase-fill"
                                aria-label="Keperluan kunjungan"
                            >
                                <HcisSelectOption
                                    v-for="purpose in masterKeperluan"
                                    :key="purpose.Id_Master_Purpose"
                                    :value="purpose.Nama_Keperluan"
                                    :label="purpose.Nama_Keperluan"
                                />
                                <HcisSelectOption value="Lain-lain" label="Lain-lain" icon="three-dots" />
                            </HcisSelect>
                        </HcisField>
                        <HcisField
                            id="guest-pic"
                            v-model="form.pic_tujuan"
                            label="PIC atau departemen tujuan"
                            placeholder="Nama karyawan atau departemen"
                            leading-icon="person-badge-fill"
                            required
                            clearable
                        />
                        <HcisField
                            v-if="form.keperluan === 'Lain-lain'"
                            id="guest-purpose-other"
                            v-model="form.keperluan_lain"
                            label="Keperluan lainnya"
                            placeholder="Jelaskan tujuan kunjungan secara spesifik"
                            leading-icon="pencil-square"
                            required
                            clearable
                        />
                        <HcisField id="guest-estimasi-masuk" label="Estimasi masuk" required>
                            <HcisDatePicker
                                v-model="form.estimasi_masuk"
                                type="datetime"
                                placeholder="Pilih tanggal & jam masuk"
                                :show-presets="false"
                                :disabled-date="sebelumHariIni"
                            />
                        </HcisField>
                        <HcisField
                            id="guest-estimasi-keluar"
                            label="Estimasi keluar"
                            required
                            :error="estimasiError"
                            :hint="durasiKunjunganLabel"
                        >
                            <HcisDatePicker
                                v-model="form.estimasi_keluar"
                                type="datetime"
                                placeholder="Pilih tanggal & jam keluar"
                                :show-presets="false"
                                :disabled-date="sebelumEstimasiMasuk"
                            />
                        </HcisField>
                    </div>

                    <div v-if="isB2b" class="mt-3 mb-1">
                        <HcisAlert
                            variant="warning"
                            appearance="soft"
                            size="sm"
                            title="Otorisasi Izin Gerbang (Permit Coklat)"
                            description="Pengajuan tamu B2B ini otomatis didaftarkan ke antrean verifikasi Permit Coklat untuk divalidasi oleh Verifikator Permit."
                            badge="Verifikasi Permit"
                            icon="shield-lock-fill"
                        />
                    </div>

                    <HcisTextarea
                        id="guest-note"
                        v-model="form.keterangan"
                        label="Keterangan tambahan"
                        placeholder="Tambahkan informasi yang membantu proses penerimaan tamu."
                        hint="Opsional."
                        :maxlength="500"
                        :rows="4"
                        clearable
                    />
                </HcisCard>
            </div>

            <aside class="visitor-guest-form__summary" aria-label="Ringkasan kesiapan registrasi">
                <HcisCard
                    title="Kesiapan registrasi"
                    description="Pastikan informasi inti sudah lengkap sebelum disimpan."
                    icon="clipboard2-check-fill"
                    :sentiment="isFormValid ? 'success' : 'warning'"
                    pattern="dots"
                    variant="glass"
                    size="md"
                    divider
                >
                    <HcisProgress
                        :value="completionPercent"
                        :sentiment="isFormValid ? 'success' : 'warning'"
                        label="Kelengkapan wajib"
                        :subtitle="`${completedRequiredCount} dari ${completionItems.length} bagian lengkap`"
                        size="sm"
                        glow
                    />
                    <ul class="visitor-completion-list">
                        <li v-for="item in completionItems" :key="item.label" :class="{ 'is-complete': item.complete }">
                            <HcisIcon :name="item.complete ? 'check-circle-fill' : 'circle'" size="xs" />
                            <span>{{ item.label }}</span>
                        </li>
                    </ul>
                    <div class="visitor-summary-data">
                        <div>
                            <span>Tamu</span><strong>{{ form.nama_tamu || 'Belum diisi' }}</strong>
                        </div>
                        <div>
                            <span>Jadwal</span
                            ><strong class="font-tabular">{{ rentangEstimasiLabel }}</strong>
                        </div>
                        <div>
                            <span>Rombongan</span><strong class="font-tabular">{{ jumlahOrang }} orang</strong>
                        </div>
                        <div>
                            <span>Kendaraan</span><strong>{{ computedNoPlat || form.jenis_kendaraan || '-' }}</strong>
                        </div>
                    </div>
                    <template #footer>
                        <div class="visitor-form-actions">
                            <HcisButton
                                variant="secondary"
                                size="md"
                                leading-icon="arrow-counterclockwise"
                                title="Reset seluruh isian formulir"
                                aria-label="Reset formulir"
                                :disabled="isSubmitting"
                                @click="resetForm"
                            />
                            <HcisButton
                                label="Simpan Registrasi"
                                variant="primary"
                                size="md"
                                leading-icon="person-check-fill"
                                class="visitor-btn-submit"
                                :loading="isSubmitting"
                                :disabled="!isFormValid || isSubmitBlocked"
                                @click="openConfirmModal"
                            />
                        </div>
                    </template>
                </HcisCard>
            </aside>
        </div>

        <!-- Teleport Mobile Fixed Bottom Floating Bar -->
        <Teleport to="body">
            <div class="visitor-tamu-mobile-dock">
                <div
                    class="visitor-tamu-mobile-dock__info"
                    role="button"
                    tabindex="0"
                    title="Buka Lembar Rincian Kesiapan"
                    @click="showReadinessModal = true"
                    @keydown.enter.space.prevent="showReadinessModal = true"
                >
                    <div class="visitor-tamu-mobile-dock__progress-badge" :class="`is-${isFormValid ? 'success' : 'warning'}`">
                        <HcisIcon :name="isFormValid ? 'check-circle-fill' : 'hourglass-split'" size="xs" />
                        <span>{{ `${completionPercent}%` }}</span>
                    </div>
                    <div class="visitor-tamu-mobile-dock__text">
                        <div class="visitor-tamu-mobile-dock__title">
                            {{ isFormValid ? 'Siap Dikirim' : `${completedRequiredCount}/${completionItems.length} Bagian` }}
                        </div>
                        <div class="visitor-tamu-mobile-dock__sub">
                            <span>Rincian info</span>
                            <HcisIcon name="chevron-up" size="2xs" />
                        </div>
                    </div>
                </div>

                <div class="visitor-tamu-mobile-dock__action">
                    <HcisButton
                        label="Simpan"
                        variant="primary"
                        size="sm"
                        leading-icon="person-check-fill"
                        :loading="isSubmitting"
                        :disabled="!isFormValid || isSubmitBlocked"
                        class="tamu-mobile-submit-btn"
                        @click="openConfirmModal"
                    />
                </div>
            </div>
        </Teleport>
    </form>

    <!-- Mobile Bottom Sheet Drawer for Kesiapan Registrasi -->
    <HcisDialog
        v-model="showReadinessModal"
        title="Kesiapan Registrasi Tamu"
        kicker="Visitor · Checklist Syarat"
        icon="clipboard2-check-fill"
        :sentiment="isFormValid ? 'success' : 'warning'"
        placement="bottom"
        variant="glass"
        pattern="grid"
        size="md"
        sticky-footer
        @close="showReadinessModal = false"
    >
        <div class="visitor-readiness-sheet-body">
            <HcisProgress
                :value="completionPercent"
                :sentiment="isFormValid ? 'success' : 'warning'"
                label="Kelengkapan Wajib"
                :subtitle="`${completedRequiredCount} dari ${completionItems.length} bagian lengkap`"
                size="sm"
                glow
                class="mb-3"
            />

            <div class="tamu-sheet-section-title mb-2">Checklist Formulir:</div>
            <ul class="visitor-completion-list mb-3">
                <li v-for="item in completionItems" :key="item.label" :class="{ 'is-complete': item.complete }">
                    <HcisIcon :name="item.complete ? 'check-circle-fill' : 'circle'" size="xs" />
                    <span>{{ item.label }}</span>
                </li>
            </ul>

            <div class="tamu-sheet-section-title mb-2">Ringkasan Kunjungan:</div>
            <div class="visitor-summary-data mb-3">
                <div>
                    <span>Tamu</span>
                    <strong>{{ form.nama_tamu || 'Belum diisi' }}</strong>
                </div>
                <div>
                    <span>Jadwal</span>
                    <strong class="font-tabular">{{ rentangEstimasiLabel }}</strong>
                </div>
                <div>
                    <span>Rombongan</span>
                    <strong class="font-tabular">{{ jumlahOrang }} orang</strong>
                </div>
                <div>
                    <span>Kendaraan</span>
                    <strong>{{ computedNoPlat || form.jenis_kendaraan || '-' }}</strong>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="d-flex align-items-center gap-2 w-100">
                <HcisButton
                    label="Reset"
                    leading-icon="arrow-counterclockwise"
                    variant="secondary"
                    appearance="subtle"
                    size="sm"
                    class="flex-shrink-0"
                    @click="resetForm(); showReadinessModal = false;"
                />
                <HcisButton
                    label="Simpan Registrasi"
                    variant="primary"
                    size="md"
                    leading-icon="person-check-fill"
                    :loading="isSubmitting"
                    :disabled="!isFormValid || isSubmitBlocked"
                    class="flex-grow-1"
                    @click="showReadinessModal = false; openConfirmModal();"
                />
            </div>
        </template>
    </HcisDialog>

    <!-- Enhanced Confirmation & Result State Dialog (Using HcisResultState) -->
    <HcisDialog
        v-model="showConfirmModal"
        custom-class="tamu-confirm-dialog"
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
        <div v-if="modalStage === 'confirm'">
            <HcisResultState
                status="info"
                variant="flat"
                size="md"
                kicker="Visitor · Konfirmasi Registrasi"
                title="Kirim Pengajuan Kunjungan Tamu?"
                description="Periksa kembali ringkasan rincian identitas tamu, PIC tujuan, dan jadwal kedatangan sebelum disimpan ke sistem."
                :items="confirmReceiptItems"
            />
        </div>

        <!-- 2. Submitting / Processing State -->
        <div v-else-if="modalStage === 'submitting'">
            <HcisResultState
                status="loading"
                variant="flat"
                size="md"
                kicker="Memproses Transaksi"
                title="Menyimpan Registrasi Tamu..."
                description="Mohon tunggu, sistem sedang memverifikasi data dan alur otorisasi kunjungan."
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
                kicker="Visitor · Registrasi Berhasil"
                title="Pengajuan Tamu Berhasil Disimpan!"
                description="Data kunjungan telah tercatat di buku tamu dan nomor registrasi telah diterbitkan."
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
                title="Gagal Menyimpan Registrasi"
                :description="submitErrorMessage || 'Terjadi kesalahan sistem saat menyimpan data tamu. Silakan coba kembali.'"
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
                        label="Ya, Simpan Registrasi"
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
                        label="Daftarkan Tamu Baru"
                        variant="secondary"
                        size="md"
                        leading-icon="person-plus-fill"
                        @click="registerAnother"
                    />
                    <HcisButton
                        label="Lihat di Riwayat & Buku Tamu"
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
    HcisDatePicker,
    HcisDialog,
    HcisField,
    HcisIdentityInput,
    HcisIcon,
    HcisPhoneInput,
    HcisPlateInput,
    HcisProgress,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisTextarea,
    HcisUpload,
} from '@/components/ui';

export default {
    name: 'TamuForm',
    components: {
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDatePicker,
        HcisDialog,
        HcisField,
        HcisIdentityInput,
        HcisIcon,
        HcisPhoneInput,
        HcisPlateInput,
        HcisProgress,
        HcisResultState,
        HcisSelect,
        HcisSelectOption,
        HcisTextarea,
        HcisUpload,
    },
    emits: ['saved'],
    props: {
        // Ditetapkan halaman (rute), bukan dipilih pengisi form.
        tipe: { type: String, default: 'REGULAR' },
        apiPrefix: { type: String, default: 'transaksitamu' },
        serverNow: { type: String, default: null },
        aturanForm: { type: Object, default: () => ({}) },
        b2bUnits: { type: Array, default: () => [] },
        config: { type: Object, default: () => ({}) },
        approvalPolicy: { type: Object, default: () => ({}) },
        approverFlows: { type: Array, default: () => [] },
    },
    data() {
        return {
            form: this.defaultForm(),
            masterPlatCodes: [],
            masterVehicleTypes: [],
            masterKeperluan: [],
            masterJenisIdentitas: [],
            isSubmitting: false,
            showConfirmModal: false,
            showReadinessModal: false,
            modalStage: 'confirm', // 'confirm' | 'submitting' | 'success' | 'error'
            pendingPayload: null,
            lastSubmittedResult: null,
            submitErrorMessage: '',
            idempotencyKey: null,
        };
    },
    computed: {
        isB2b() {
            return this.tipe === 'B2B';
        },
        rentangMaksHari() {
            return Number(this.aturanForm?.rentang_maks_hari) || 30;
        },
        fotoMaksMb() {
            return Math.max(1, Math.round((Number(this.aturanForm?.foto_maks_kb) || 5120) / 1024));
        },
        fotoMimeList() {
            return this.aturanForm?.foto_mime?.length ? this.aturanForm.foto_mime : ['jpg', 'jpeg', 'png', 'webp'];
        },
        fotoAccept() {
            return this.fotoMimeList.map((ext) => `.${ext}`).join(',');
        },
        fotoMimeLabel() {
            return this.fotoMimeList
                .filter((ext) => ext !== 'jpeg')
                .map((ext) => ext.toUpperCase())
                .join('/');
        },
        estimasiMasukDate() {
            return this.parseWaktu(this.form.estimasi_masuk);
        },
        estimasiKeluarDate() {
            return this.parseWaktu(this.form.estimasi_keluar);
        },
        estimasiError() {
            const masuk = this.estimasiMasukDate;
            const keluar = this.estimasiKeluarDate;
            if (!masuk || !keluar) return '';
            if (keluar <= masuk) return 'Estimasi keluar harus setelah estimasi masuk.';
            if (keluar - masuk > this.rentangMaksHari * 24 * 60 * 60 * 1000) {
                return `Rentang kunjungan maksimal ${this.rentangMaksHari} hari.`;
            }
            return '';
        },
        estimasiValid() {
            return Boolean(this.estimasiMasukDate && this.estimasiKeluarDate && !this.estimasiError);
        },
        durasiKunjunganLabel() {
            if (!this.estimasiValid) return '';
            const menit = Math.round((this.estimasiKeluarDate - this.estimasiMasukDate) / 60000);
            const jam = Math.floor(menit / 60);
            const hari = Math.floor(jam / 24);
            const teks = hari > 0 ? `${hari} hari ${jam % 24} jam` : `${jam} jam ${menit % 60} menit`;
            return `Durasi ${teks}.`;
        },
        rentangEstimasiLabel() {
            if (!this.estimasiMasukDate) return 'Belum diisi';
            const awal = this.formatDisplayDateTime(this.estimasiMasukDate);
            return this.estimasiKeluarDate ? `${awal} – ${this.formatDisplayDateTime(this.estimasiKeluarDate)}` : awal;
        },
        platLengkap() {
            // Tamu B2B: kode belakang ikut wajib (permintaan user 29 Sep 2026).
            return Boolean(this.plateParts.prefix && this.plateParts.angka && this.plateParts.suffix);
        },
        identityOptions() {
            const options = this.masterJenisIdentitas
                .map((item) => (typeof item === 'string' ? item : item.Nama_Jenis_Identitas || item.Nama || item.Kode))
                .filter(Boolean);
            return options.length ? options : ['KTP', 'SIM', 'Paspor'];
        },
        vehicleOptions() {
            const seenLabels = new Set();
            const options = [];
            const normalizeLabel = (value) =>
                String(value || '')
                    .normalize('NFKC')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim()
                    .replace(/\s+/g, ' ');
            const isNonDrivingText = (val) => {
                const n = normalizeLabel(val);
                return (
                    !n ||
                    n.includes('tidak berkendara') ||
                    n.includes('tidak berkendaraan') ||
                    n.includes('tanpa kendaraan') ||
                    n.includes('pejalan kaki') ||
                    n === 'jalan kaki' ||
                    n === 'none' ||
                    n === 'non driving'
                );
            };
            const addOption = (item, index) => {
                const rawLabel =
                    typeof item === 'string'
                        ? item
                        : item?.label ||
                          item?.Nama_Vehicle_Type ||
                          item?.Nama_Tipe_Kendaraan ||
                          item?.Nama ||
                          item?.name ||
                          item?.value ||
                          '';
                const label = String(rawLabel).trim();
                const rawValue =
                    typeof item === 'string'
                        ? item
                        : item?.value ||
                          item?.Nama_Vehicle_Type ||
                          item?.Nama_Tipe_Kendaraan ||
                          item?.Kode ||
                          item?.kode ||
                          label;
                const value = String(rawValue || '').trim();

                const isNonDriving = isNonDrivingText(label) || isNonDrivingText(value);
                const canonicalKey = isNonDriving ? 'tidak berkendara' : normalizeLabel(label || value);

                if (!canonicalKey || seenLabels.has(canonicalKey)) return;
                seenLabels.add(canonicalKey);

                const id =
                    typeof item === 'object' && item
                        ? item.Id ?? item.Id_Vehicle_Type ?? item.id ?? value
                        : value || `vehicle-${index}`;
                options.push({
                    id: isNonDriving ? 'non-driving' : String(id),
                    value: isNonDriving ? 'Tidak Berkendara' : value,
                    label: isNonDriving ? 'Tidak berkendara' : label,
                    icon: this.getVehicleIcon(isNonDriving ? 'Tidak berkendara' : label),
                });
            };

            addOption({ id: 'non-driving', value: 'Tidak Berkendara', label: 'Tidak berkendara' }, 0);
            this.masterVehicleTypes.forEach(addOption);

            // Tamu B2B wajib berkendaraan: pilihan tanpa kendaraan disembunyikan
            // (server juga menolaknya — lihat AturanFormTamu).
            return this.isB2b ? options.filter((opt) => opt.id !== 'non-driving') : options;
        },
        completionItems() {
            const f = this.form;
            return [
                { label: 'Identitas dan kontak', complete: Boolean(f.nama_tamu && f.no_hp) },
                { label: 'Instansi asal', complete: Boolean(f.instansi_asal) },
                {
                    label: 'Tujuan kunjungan',
                    complete: Boolean(f.keperluan && (f.keperluan !== 'Lain-lain' || f.keperluan_lain)),
                },
                { label: 'PIC atau departemen', complete: Boolean(f.pic_tujuan) },
                { label: 'Estimasi masuk & keluar', complete: this.estimasiValid },
                ...(this.isB2b
                    ? [
                          { label: 'Kendaraan & plat nomor', complete: !this.isVehicleDisabled && this.platLengkap },
                          { label: 'Unit pengaju B2B', complete: Boolean(this.unitB2b) },
                      ]
                    : []),
                { label: 'Daftar rombongan', complete: this.daftarAnggotaValid },
            ];
        },
        jumlahOrang() {
            return 1 + (this.form.anggota_lain?.length ?? 0);
        },
        daftarAnggotaValid() {
            return (this.form.anggota_lain ?? []).every(
                (a) => String(a?.nama ?? '').trim() !== '',
            );
        },
        bolehTambahAnggota() {
            return this.jumlahOrang < 50;
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
        /** Unit terkunci bila hanya satu yang boleh; bila lebih, pilihan pengguna. */
        unitB2b() {
            if (!this.isB2b) return null;
            if (this.b2bUnits.length === 1) return this.b2bUnits[0];
            return this.b2bUnits.includes(this.form.b2b_unit) ? this.form.b2b_unit : null;
        },
        fiturNonaktif() {
            return this.config.enabled === false;
        },
        tanpaApprover() {
            return Boolean(
                this.approvalPolicy.needs_approval &&
                !this.approvalPolicy.allow_submit_without_flow &&
                this.approverFlows.length === 0,
            );
        },
        isSubmitBlocked() {
            return this.fiturNonaktif || (this.isB2b && !this.b2bUnits.length) || this.tanpaApprover;
        },
        isVehicleDisabled() {
            const value = (this.form.jenis_kendaraan || '').toLowerCase().trim();
            return (
                !value ||
                value.includes('tidak') ||
                value.includes('tanpa') ||
                value.includes('pejalan kaki') ||
                value.includes('jalan kaki') ||
                value === 'none'
            );
        },
        computedNoPlat() {
            if (this.isVehicleDisabled) return null;
            return this.normalizePlateNumber(this.form.no_plat) || null;
        },
        plateParts() {
            return this.parsePlateNumber(this.computedNoPlat);
        },
        confirmReceiptItems() {
            if (!this.pendingPayload) return [];
            const p = this.pendingPayload;
            return [
                { label: 'Nama Tamu', value: p.nama_tamu, isHighlight: true, icon: 'person-fill' },
                { label: 'Instansi / Asal', value: p.instansi_asal || 'Umum', icon: 'buildings-fill' },
                { label: 'Nomor Kontak', value: p.no_hp, isMono: true, icon: 'telephone-fill' },
                { label: 'PIC Tujuan', value: p.pic_tujuan, icon: 'person-badge-fill' },
                { label: 'Estimasi Kunjungan', value: p.rentang_label, isMono: true, icon: 'calendar-event-fill' },
                {
                    label: 'Permit Akses',
                    value: this.isB2b ? 'Permit Coklat (Verifikasi Petugas)' : 'Tidak Diperlukan (Tamu Regular)',
                    icon: 'shield-check',
                },
                ...(this.isB2b ? [{ label: 'Unit Pengaju', value: this.labelUnit(this.unitB2b), icon: 'truck' }] : []),
                ...(this.isB2b && this.form.foto_kendaraan?.length
                    ? [{ label: 'Foto Kendaraan', value: this.form.foto_kendaraan[0]?.name || '1 foto', icon: 'camera-fill' }]
                    : []),
                { label: 'Jumlah Rombongan', value: `${p.jumlah_tamu} Orang`, icon: 'people-fill' },
                { label: 'Keperluan', value: p.keperluan, icon: 'briefcase-fill' },
                {
                    label: 'Moda Transportasi',
                    value: this.computedNoPlat ? `${this.form.jenis_kendaraan} (${this.computedNoPlat})` : this.form.jenis_kendaraan,
                    icon: 'car-front-fill',
                },
            ];
        },
        successReceiptItems() {
            if (!this.lastSubmittedResult) return [];
            const s = this.lastSubmittedResult;
            const p = this.pendingPayload || {};
            return [
                {
                    label: 'No. Registrasi Tamu',
                    value: s.no_transaksi || '-',
                    isHighlight: true,
                    isMono: true,
                    copyable: true,
                    icon: 'receipt',
                },
                {
                    label: 'Nama Tamu',
                    value: p.nama_tamu || s.nama_tamu || '-',
                    icon: 'person-fill',
                },
                {
                    label: 'PIC Tujuan',
                    value: p.pic_tujuan || s.pic_tujuan || '-',
                    icon: 'person-badge-fill',
                },
                {
                    label: 'Estimasi Kunjungan',
                    value: p.rentang_label || '-',
                    isMono: true,
                    icon: 'calendar-event-fill',
                },
                ...(s.peringatan?.length
                    ? [{ label: 'Catatan', value: s.peringatan.join(' '), icon: 'exclamation-triangle-fill' }]
                    : []),
                {
                    label: 'Status Alur',
                    value: this.approvalPolicy?.needs_approval ? 'Menunggu Approval' : 'Terjadwal',
                    isBadge: true,
                    badgeVariant: this.approvalPolicy?.needs_approval ? 'warning' : 'brand',
                    icon: 'shield-check',
                },
            ];
        },
        dialogMeta() {
            switch (this.modalStage) {
                case 'success':
                    return {
                        title: 'Registrasi Berhasil Disimpan',
                        kicker: 'Visitor · Success Receipt',
                        description: 'Kunjungan telah terdaftar secara resmi di sistem.',
                        icon: 'check-circle-fill',
                        sentiment: 'success',
                    };
                case 'error':
                    return {
                        title: 'Kendala Registrasi Tamu',
                        kicker: 'Visitor · Error State',
                        description: 'Gagal memproses transaksi registrasi tamu.',
                        icon: 'exclamation-circle-fill',
                        sentiment: 'danger',
                    };
                case 'submitting':
                    return {
                        title: 'Memproses Registrasi',
                        kicker: 'Visitor · Processing',
                        description: 'Sedang menyimpan data ke database server...',
                        icon: 'hourglass-split',
                        sentiment: 'brand',
                    };
                default:
                    return {
                        title: 'Konfirmasi Registrasi Tamu',
                        kicker: 'Visitor · Guest Management',
                        description: 'Periksa rincian data sebelum pengajuan dikirim.',
                        icon: 'person-check-fill',
                        sentiment: 'brand',
                    };
            }
        },
    },
    mounted() {
        this.fetchMasterData();
    },
    methods: {
        defaultForm() {
            return {
                nama_tamu: '',
                no_hp: '',
                kode_negara_hp: '+62',
                no_identitas: '',
                jenis_identitas: 'KTP',
                instansi_asal: '',
                no_plat: '',
                // B2B wajib berkendaraan, jadi tidak ada bawaan "Tidak Berkendara".
                jenis_kendaraan: this.tipe === 'B2B' ? '' : 'Tidak Berkendara',
                foto_kendaraan: [],
                b2b_unit: '',
                anggota_lain: [],
                keperluan: '',
                keperluan_lain: '',
                pic_tujuan: '',
                estimasi_masuk: '',
                estimasi_keluar: '',
                keterangan: '',
            };
        },
        async fetchMasterData() {
            try {
                const { data } = await axios.get(`/${this.apiPrefix}-master`);
                if (data.success) {
                    const result = data.result;
                    this.masterPlatCodes = result.plat_codes || [];
                    this.masterVehicleTypes = result.vehicle_types || [];
                    this.masterKeperluan = result.keperluan || [];
                    this.masterJenisIdentitas = result.jenis_identitas || [];
                }
            } catch (error) {
                console.error('Gagal load master data', error);
                ElMessage.error('Gagal memuat data referensi tamu');
            }
        },
        getVehicleIcon(name) {
            const normalized = (name || '').toLowerCase();
            if (
                normalized.includes('tidak') ||
                normalized.includes('tanpa') ||
                normalized.includes('pejalan') ||
                normalized.includes('jalan')
            ) {
                return 'person-walking';
            }
            if (normalized.includes('motor') || normalized.includes('sepeda')) return 'bicycle';
            if (normalized.includes('mobil')) return 'car-front-fill';
            if (normalized.includes('truk') || normalized.includes('pickup') || normalized.includes('lorry')) return 'truck-front-fill';
            if (normalized.includes('bus') || normalized.includes('bis')) return 'bus-front';
            return 'car-front';
        },
        normalizePlateNumber(value) {
            const raw = String(value || '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, ' ')
                .trim();
            if (!raw) return '';

            const compact = raw.replace(/\s+/g, '');
            const compactMatch = compact.match(/^([A-Z]{1,3})(\d{1,4})([A-Z]{0,3})$/);
            if (compactMatch) {
                return [compactMatch[1], compactMatch[2], compactMatch[3]].filter(Boolean).join(' ');
            }

            return raw;
        },
        parsePlateNumber(value) {
            const parts = this.normalizePlateNumber(value).split(' ').filter(Boolean);
            return {
                prefix: parts[0] || '',
                angka: parts[1] || '',
                suffix: parts.slice(2).join(' '),
            };
        },
        buildPayload() {
            const finalPurpose = this.form.keperluan === 'Lain-lain' ? this.form.keperluan_lain : this.form.keperluan;
            // Tipe TIDAK dikirim: server membacanya dari rute halaman ini.
            return {
                nama_tamu: this.form.nama_tamu,
                no_hp: this.form.no_hp,
                no_identitas: this.form.no_identitas || null,
                jenis_identitas: this.form.jenis_identitas || null,
                instansi_asal: this.form.instansi_asal,
                plat_prefix: this.plateParts.prefix || null,
                plat_angka: this.plateParts.angka || null,
                plat_suffix: this.plateParts.suffix || null,
                no_plat_kendaraan: this.computedNoPlat,
                no_plat: this.computedNoPlat,
                jenis_kendaraan: this.form.jenis_kendaraan || null,
                keperluan: finalPurpose,
                pic_tujuan: this.form.pic_tujuan,
                ...(this.isB2b ? { b2b_unit: this.unitB2b } : {}),
                waktu_mulai_kunjungan: this.form.estimasi_masuk,
                waktu_selesai_kunjungan: this.form.estimasi_keluar,
                rentang_label: this.rentangEstimasiLabel,
                jumlah_tamu: this.jumlahOrang,
                daftar_anggota: (this.form.anggota_lain ?? []).map((a) => {
                    const bersih = { nama: String(a.nama ?? '').trim() };
                    if (String(a.jenis_identitas ?? '').trim() !== '') bersih.jenis_identitas = String(a.jenis_identitas).trim();
                    if (String(a.no_identitas ?? '').trim() !== '') bersih.no_identitas = String(a.no_identitas).trim();
                    return bersih;
                }),
                keterangan: this.form.keterangan || null,
            };
        },
        openConfirmModal() {
            if (!this.isFormValid || this.isSubmitBlocked || this.isSubmitting) return;
            this.pendingPayload = this.buildPayload();
            this.modalStage = 'confirm';
            this.idempotencyKey = this.idempotencyKey || this.createIdempotencyKey();
            this.showConfirmModal = true;
        },
        submitForm() {
            this.openConfirmModal();
        },
        closeConfirmModal() {
            if (this.isSubmitting) return;
            const wasSuccess = this.modalStage === 'success';
            this.showConfirmModal = false;
            this.modalStage = 'confirm';
            this.pendingPayload = null;
            if (wasSuccess) {
                this.lastSubmittedResult = null;
                this.idempotencyKey = null;
                this.resetForm();
            }
        },
        async confirmSubmit() {
            if (!this.pendingPayload || this.isSubmitting) return;
            this.isSubmitting = true;
            this.modalStage = 'submitting';
            this.submitErrorMessage = '';
            try {
                // rentang_label hanya untuk struk di layar, bukan untuk server.
                const { rentang_label: _rentang, ...payload } = this.pendingPayload;
                const foto = this.isB2b ? this.form.foto_kendaraan?.[0]?.raw : null;
                const body = foto ? this.keFormData(payload, foto) : payload;
                const { data } = await axios.post(`/${this.apiPrefix}`, body, {
                    headers: { 'Idempotency-Key': this.idempotencyKey },
                });
                if (data.success) {
                    this.lastSubmittedResult = data.result || {};
                    this.modalStage = 'success';
                    ElMessage.success('Data tamu berhasil diregistrasi!');
                    (data.result?.peringatan || []).forEach((pesan) => ElMessage.warning(pesan));
                    // Segera bersihkan data form input demi keamanan dan privasi data
                    this.form = this.defaultForm();
                    this.idempotencyKey = null;
                } else {
                    this.submitErrorMessage = data.message || 'Gagal menyimpan data tamu.';
                    this.modalStage = 'error';
                    ElMessage.error(this.submitErrorMessage);
                }
            } catch (error) {
                this.submitErrorMessage = error.response?.data?.message || 'Terjadi kesalahan sistem saat menyimpan data tamu.';
                this.modalStage = 'error';
                ElMessage.error(this.submitErrorMessage);
            } finally {
                this.isSubmitting = false;
            }
        },
        registerAnother() {
            this.showConfirmModal = false;
            this.modalStage = 'confirm';
            this.lastSubmittedResult = null;
            this.pendingPayload = null;
            this.idempotencyKey = null;
            this.resetForm();
        },
        goToHistory() {
            this.showConfirmModal = false;
            this.modalStage = 'confirm';
            this.lastSubmittedResult = null;
            this.pendingPayload = null;
            this.idempotencyKey = null;
            this.resetForm();
            this.$emit('saved');
        },
        tambahAnggota() {
            if (!this.bolehTambahAnggota) return;

            this.form.anggota_lain.push({ nama: '', jenis_identitas: '', no_identitas: '' });
        },
        hapusAnggota(i) {
            this.form.anggota_lain.splice(i, 1);
        },
        formatDisplayDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        labelUnit(unit) {
            return { PROCUREMENT: 'B2B Procurement', LOGISTIC: 'B2B Logistic' }[unit] || '-';
        },
        formatDisplayDateTime(date) {
            if (!date) return '-';
            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        /** 'YYYY-MM-DD HH:mm[:ss]' -> Date waktu lokal (tanpa geser zona waktu). */
        parseWaktu(nilai) {
            const cocok = String(nilai || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
            if (!cocok) return null;
            const [, y, m, d, h, i] = cocok.map(Number);
            return new Date(y, m - 1, d, h, i);
        },
        hariIni() {
            const acuan = this.parseWaktu(this.serverNow) || new Date();
            return new Date(acuan.getFullYear(), acuan.getMonth(), acuan.getDate());
        },
        sebelumHariIni(date) {
            return date < this.hariIni();
        },
        sebelumEstimasiMasuk(date) {
            const masuk = this.estimasiMasukDate;
            const batas = masuk ? new Date(masuk.getFullYear(), masuk.getMonth(), masuk.getDate()) : this.hariIni();
            return date < batas;
        },
        /** Payload + foto sebagai multipart. Array ditulis dalam notasi kurung PHP. */
        keFormData(payload, foto) {
            const fd = new FormData();
            const tambah = (kunci, nilai) => {
                if (nilai === null || nilai === undefined) return;
                if (Array.isArray(nilai)) {
                    nilai.forEach((item, i) => tambah(`${kunci}[${i}]`, item));
                } else if (typeof nilai === 'object') {
                    Object.entries(nilai).forEach(([k, v]) => tambah(`${kunci}[${k}]`, v));
                } else {
                    fd.append(kunci, nilai);
                }
            };
            Object.entries(payload).forEach(([k, v]) => tambah(k, v));
            fd.append('foto_kendaraan', foto);
            return fd;
        },
        resetForm() {
            this.idempotencyKey = null;
            this.pendingPayload = null;
            this.form = this.defaultForm();
        },
        createIdempotencyKey() {
            return window.crypto?.randomUUID?.() || `visitor-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        },
    },
};
</script>

<style scoped>
/* Transport Section & Notices */
.visitor-transport-section {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

.visitor-transport-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.visitor-transport-section__title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.visitor-section-divider {
    height: 1px;
    background: var(--hcis-slate-200, rgba(226, 232, 240, 0.8));
    margin: 1.25rem 0 1rem;
}

.visitor-transport-notice {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--hcis-slate-50, rgba(248, 250, 252, 0.8));
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    align-self: end;
    min-height: 2.75rem;
    box-sizing: border-box;
}

.visitor-transport-notice__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
    flex-shrink: 0;
}

.visitor-transport-notice__content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.visitor-transport-notice__title {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.2;
}

.visitor-transport-notice__sub {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.3;
}

/* Sub-section 2: Party & Group Composition */
.visitor-guest-form__anggota {
    margin-top: 0.25rem;
}

.visitor-party-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}

.visitor-party-header__title-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.visitor-party-header__title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.visitor-party-header__desc {
    margin: 0;
    font-size: 0.78125rem;
    color: var(--hcis-slate-500, #64748b);
}

.visitor-party-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.visitor-party-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.visitor-party-stat-pill__label {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-700, #4338ca);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.visitor-party-stat-pill__value {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-brand-900, #312e81);
}

/* Party Breakdown Bar */
.visitor-party-breakdown {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.visitor-party-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.85rem;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.15s ease;
}

.visitor-party-chip--primary {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.6) 0%, rgba(255, 255, 255, 0.9) 100%);
    border-color: var(--hcis-brand-200, #c7d2fe);
}

.visitor-party-chip__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    background: var(--hcis-brand-100, #e0e7ff);
    color: var(--hcis-brand-700, #4338ca);
    flex-shrink: 0;
}

.visitor-party-chip__info {
    display: flex;
    flex-direction: column;
}

.visitor-party-chip__role {
    font-size: 0.65625rem;
    font-weight: 700;
    color: var(--hcis-slate-400, #94a3b8);
    text-transform: uppercase;
    letter-spacing: 0.02em;
    line-height: 1;
}

.visitor-party-chip__name {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.3;
}

.visitor-party-chip-plus {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400, #94a3b8);
}

/* Empty State */
.visitor-party-empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.8) 0%, rgba(241, 245, 249, 0.6) 100%);
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: var(--hcis-radius-md, 12px);
    flex-wrap: wrap;
}

.visitor-party-empty__content {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1 1 240px;
}

.visitor-party-empty__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: rgba(226, 232, 240, 0.7);
    color: var(--hcis-slate-600, #475569);
    flex-shrink: 0;
}

.visitor-party-empty__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.visitor-party-empty__title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, #1e293b);
}

.visitor-party-empty__desc {
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.4;
}

/* Member List & Individual Member Card */
.visitor-party-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.visitor-party-card {
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 12px);
    padding: 0.85rem 1rem 1rem 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.visitor-party-card:hover {
    border-color: var(--hcis-brand-300, #a5b4fc);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.05);
}

.visitor-party-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid var(--hcis-slate-100, #f1f5f9);
}

.visitor-party-card__tag-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    flex: 1 1 auto;
}

.visitor-party-card__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-brand-700, #4338ca);
    background: var(--hcis-brand-50, #eef2ff);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    flex-shrink: 0;
}

.visitor-party-card__name-preview {
    font-size: 0.78125rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

.visitor-party-card__fields {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.3fr);
    gap: 0.75rem;
    align-items: start;
}

.visitor-party-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.visitor-party-limit-hint {
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, #64748b);
    font-weight: 500;
}

.visitor-guest-form {
    display: grid;
    gap: var(--hcis-space-4, 1rem);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.visitor-guest-form__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: var(--hcis-space-4, 1rem);
    align-items: start;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.visitor-guest-form__sections {
    display: grid;
    gap: var(--hcis-space-4, 1rem);
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.visitor-form-grid {
    display: grid;
    gap: var(--hcis-space-3, 0.75rem);
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.visitor-form-grid > * {
    min-width: 0;
    max-width: 100%;
}

.visitor-form-grid--identity {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visitor-form-grid--transport {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visitor-form-grid--transport-driving {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visitor-form-grid__vehicle {
    grid-column: 1;
}

.visitor-form-grid__plate {
    grid-column: 2;
}

.visitor-form-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visitor-guest-form__permit-note {
    margin: 0.75rem 0 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
}

.visitor-guest-form__permit-note strong {
    color: #795548;
}

.visitor-guest-form__summary {
    position: sticky;
    top: 5.5rem;
    align-self: start;
    min-width: 0;
    max-height: calc(100vh - 6rem);
    max-height: calc(100dvh - 6rem);
    z-index: 15;
}

.visitor-guest-form__summary :deep(.hcis-card) {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: inherit;
}

.visitor-guest-form__summary :deep(.hcis-card__header),
.visitor-guest-form__summary :deep(.hcis-card__footer) {
    flex-shrink: 0;
}

.visitor-guest-form__summary :deep(.hcis-card__body) {
    overflow-y: auto;
    min-height: 0;
}

.visitor-completion-list {
    list-style: none;
    padding: 0;
    margin: var(--hcis-space-3, 0.75rem) 0 0;
    display: grid;
    gap: var(--hcis-space-2, 0.5rem);
}

.visitor-completion-list li {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    font-size: 0.8125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    transition: color 0.15s ease;
}

.visitor-completion-list li.is-complete {
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-weight: 600;
}

.visitor-summary-data {
    margin-top: var(--hcis-space-3, 0.75rem);
    padding-top: var(--hcis-space-3, 0.75rem);
    border-top: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    display: grid;
    gap: var(--hcis-space-2, 0.5rem);
    font-size: 0.8125rem;
}

.visitor-summary-data div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
}

.visitor-summary-data span {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.visitor-summary-data strong {
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    text-align: right;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.visitor-form-actions {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    width: 100%;
}

.visitor-btn-submit {
    flex: 1 1 auto;
    justify-content: center;
    font-weight: 700;
}

.visitor-dialog-actions {
    width: 100%;
}

.visitor-tamu-mobile-dock {
    display: none;
}

.tamu-sheet-section-title {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

@media (max-width: 992px) {
    .visitor-guest-form__layout {
        grid-template-columns: 1fr;
    }

    .visitor-guest-form__summary {
        position: static;
    }
}

@media (max-width: 640px) {
    .visitor-guest-form {
        padding-bottom: 5.5rem;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .visitor-guest-form__layout {
        grid-template-columns: minmax(0, 1fr) !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .visitor-guest-form__summary {
        display: none !important;
    }

    .visitor-tamu-mobile-dock {
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

    .visitor-tamu-mobile-dock__info {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        background: rgba(241, 245, 249, 0.75);
        border: 1px solid rgba(226, 232, 240, 0.9);
        cursor: pointer;
        user-select: none;
        flex: 1 1 auto;
        min-width: 0;
        transition: all 0.15s ease;
    }

    .visitor-tamu-mobile-dock__info:active {
        transform: scale(0.98);
        background: rgba(226, 232, 240, 0.9);
    }

    .visitor-tamu-mobile-dock__progress-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        flex-shrink: 0;
    }

    .visitor-tamu-mobile-dock__progress-badge.is-success {
        background: var(--hcis-emerald-50);
        color: var(--hcis-emerald-700);
        border: 1px solid var(--hcis-emerald-200);
    }

    .visitor-tamu-mobile-dock__progress-badge.is-warning {
        background: var(--hcis-amber-50);
        color: var(--hcis-amber-700);
        border: 1px solid var(--hcis-amber-200);
    }

    .visitor-tamu-mobile-dock__text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
    }

    .visitor-tamu-mobile-dock__title {
        font-size: 0.78125rem;
        font-weight: 750;
        color: var(--hcis-slate-800, var(--hcis-slate-800));
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .visitor-tamu-mobile-dock__sub {
        display: flex;
        align-items: center;
        gap: 0.2rem;
        font-size: 0.65625rem;
        font-weight: 600;
        color: var(--hcis-slate-500, var(--hcis-slate-500));
        line-height: 1.2;
    }

    .visitor-tamu-mobile-dock__action {
        flex-shrink: 0;
    }

    .tamu-mobile-submit-btn {
        height: 2.35rem !important;
        font-weight: 750 !important;
        font-size: 0.8125rem !important;
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
        border-radius: 10px !important;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
    }

    .visitor-guest-form__sections {
        gap: var(--hcis-space-3, 0.75rem);
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .visitor-guest-form__sections :deep(.hcis-card) {
        border-radius: 14px !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .visitor-guest-form__sections :deep(.hcis-card__header) {
        padding: 0.85rem 1rem 0.65rem 1rem !important;
    }

    .visitor-guest-form__sections :deep(.hcis-card__body) {
        padding: 0.75rem 1rem 1rem 1rem !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .visitor-form-grid {
        gap: 0.85rem;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .visitor-form-grid--identity,
    .visitor-form-grid--two,
    .visitor-form-grid--transport,
    .visitor-form-grid--transport-driving {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .visitor-form-grid__vehicle,
    .visitor-form-grid__plate {
        grid-column: 1 !important;
        min-width: 0;
    }

    /* =========================================================================
       KOMPOSISI ROMBONGAN - MOBILE OPTIMIZATION (COMPACT & NEAT)
       ========================================================================= */
    .visitor-section-divider {
        margin: 0.85rem 0 0.75rem !important;
    }

    .visitor-party-header {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        margin-bottom: 0.55rem !important;
    }

    .visitor-party-header__title-group {
        flex: 1 1 auto;
        min-width: 0;
    }

    .visitor-party-header__title {
        font-size: 0.75rem !important;
        gap: 0.35rem !important;
    }

    .visitor-party-header__desc {
        display: none !important; /* Hide long sentence to keep mobile header single-row & neat */
    }

    .visitor-party-stats {
        flex-shrink: 0;
    }

    .visitor-party-stat-pill {
        padding: 0.2rem 0.55rem !important;
        gap: 0.35rem !important;
    }

    .visitor-party-stat-pill__label {
        display: none !important; /* Display count directly on mobile to save horizontal space */
    }

    .visitor-party-stat-pill__value {
        font-size: 0.75rem !important;
        font-weight: 800 !important;
    }

    /* Breakdown Bar - Dual Pill cleanly aligned on 1 Row */
    .visitor-party-breakdown {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.35rem !important;
        margin-bottom: 0.65rem !important;
        width: 100% !important;
    }

    .visitor-party-chip {
        padding: 0.35rem 0.55rem !important;
        border-radius: 8px !important;
        gap: 0.45rem !important;
    }

    .visitor-party-chip--primary {
        flex: 1 1 0 !important;
        min-width: 0 !important;
    }

    .visitor-party-chip--members {
        flex: 0 0 auto !important;
    }

    .visitor-party-chip__avatar {
        width: 1.4rem !important;
        height: 1.4rem !important;
        font-size: 0.7rem !important;
        border-radius: 6px !important;
    }

    .visitor-party-chip__role {
        font-size: 0.59375rem !important;
        line-height: 1 !important;
    }

    .visitor-party-chip__name {
        font-size: 0.75rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 120px !important;
    }

    .visitor-party-chip__badge {
        display: none !important; /* Hide bulky badge on mobile */
    }

    .visitor-party-chip-plus {
        font-size: 0.75rem !important;
        flex-shrink: 0 !important;
    }

    /* Empty state */
    .visitor-party-empty {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 0.75rem 0.85rem !important;
        gap: 0.65rem !important;
        border-radius: 10px !important;
    }

    .visitor-party-empty__content {
        gap: 0.65rem !important;
        flex: 1 1 auto !important;
    }

    .visitor-party-empty__icon {
        width: 2rem !important;
        height: 2rem !important;
        font-size: 0.875rem !important;
    }

    .visitor-party-empty__title {
        font-size: 0.78125rem !important;
    }

    .visitor-party-empty__desc {
        font-size: 0.71875rem !important;
        line-height: 1.35 !important;
    }

    /* Member Cards */
    .visitor-party-list {
        gap: 0.55rem !important;
    }

    .visitor-party-card {
        padding: 0.65rem 0.75rem 0.75rem !important;
        border-radius: 10px !important;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
        border-color: var(--hcis-slate-200, #e2e8f0) !important;
    }

    .visitor-party-card__header {
        margin-bottom: 0.45rem !important;
        padding-bottom: 0.35rem !important;
    }

    .visitor-party-card__tag-wrap {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .visitor-party-card__tag {
        font-size: 0.65625rem !important;
        padding: 0.15rem 0.45rem !important;
        border-radius: 5px !important;
        flex-shrink: 0;
    }

    .visitor-party-card__name-preview {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: var(--hcis-slate-800, #1e293b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 140px !important;
    }

    .visitor-party-card__fields {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 0.45rem !important;
    }

    .visitor-party-card__fields :deep(.hcis-field) {
        margin-bottom: 0 !important;
    }

    .visitor-party-card__fields :deep(.hcis-field__label) {
        font-size: 0.71875rem !important;
        margin-bottom: 0.15rem !important;
        font-weight: 650 !important;
    }

    .visitor-party-card__fields :deep(.hcis-field__hint) {
        font-size: 0.65625rem !important;
        margin-top: 0.15rem !important;
    }

    .visitor-party-empty :deep(.hcis-btn),
    .visitor-party-actions :deep(.hcis-btn) {
        width: 100% !important;
        justify-content: center !important;
    }

    .visitor-party-actions {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.4rem !important;
        margin-top: 0.4rem !important;
    }

    .visitor-party-limit-hint {
        text-align: center !important;
        font-size: 0.6875rem !important;
    }

    .visitor-transport-notice {
        align-self: stretch !important;
    }

    :deep(.hcis-field),
    :deep(.hcis-field__group),
    :deep(.hcis-field__control-wrap),
    :deep(.hcis-field__control),
    :deep(.hcis-phone-input__control),
    :deep(.hcis-identity-input__control),
    :deep(.hcis-plate-input__control),
    :deep(.hcis-date-picker),
    :deep(.hcis-select),
    :deep(.hcis-input-number),
    :deep(.hcis-textarea) {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    :deep(.tamu-confirm-dialog .hcis-dialog__description),
    :deep(.hcis-dialog__description),
    :deep(.tamu-confirm-dialog .hcis-result-state__description) {
        display: none !important;
    }
    :deep(.tamu-confirm-dialog .hcis-result-state__title) {
        font-size: 1rem !important;
        margin-bottom: 0.35rem !important;
    }
    :deep(.tamu-confirm-dialog .hcis-receipt) {
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

@media (max-width: 420px) {
    .visitor-form-grid--transport,
    .visitor-form-grid--transport-driving {
        grid-template-columns: minmax(0, 1fr) !important;
    }
    .visitor-form-grid__vehicle,
    .visitor-form-grid__plate {
        grid-column: 1 !important;
    }
}
</style>
