<template>
    <div class="pa-step-root">
        <div class="pa-step-container">
            <!-- Step Header Banner -->
            <div class="pa-step-header">
                <div class="pa-step-header-identity">
                    <span class="pa-step-kicker">STEP 3 OF 5 · 360 MULTI-RATER RULES</span>
                    <h2 class="pa-step-title">
                        <HcisIcon name="diagram-3" size="sm" color="brand" />
                        <span>Aturan Rater (Multi-Rater Policy Matrix)</span>
                    </h2>
                    <p class="pa-step-description">
                        Konfigurasikan relasi penugasan dan distribusi bobot tiap kategori rater per level jabatan.
                        Total bobot kategori untuk tiap level wajib berjumlah tepat 100%.
                    </p>
                </div>

                <div class="pa-step-header-actions">
                    <HcisButton
                        v-if="dapatDisunting && perluDisalin"
                        label="Salin Ulang dari Master"
                        variant="primary"
                        size="sm"
                        leading-icon="files"
                        :loading="menyalin"
                        @click="salin"
                    />
                    <HcisButton
                        v-if="dapatDisunting && !perluDisalin && level.length"
                        label="Terapkan Model ke Semua"
                        variant="secondary"
                        size="sm"
                        leading-icon="lightning-charge-fill"
                        @click="bukaBatchModelDialog"
                    />
                    <HcisButton
                        v-if="dapatDisunting && !perluDisalin && level.length"
                        label="Simpan Semua Level"
                        variant="primary"
                        size="sm"
                        leading-icon="check-all"
                        :loading="menyimpanSemua"
                        @click="simpanSemua"
                    />
                    <!--
                     | SATU BENTUK DI SEMUA LANGKAH: "Lihat Master ...".
                     |
                     | Sebelum 6 September 2026 hanya sebagian langkah punya jalan ke
                     | masternya, dan namanya berbeda-beda. Nama yang berganti tiap langkah
                     | memaksa admin membaca ulang untuk memastikan ia tidak sedang menekan
                     | sesuatu yang MENGUBAH data — padahal semuanya cuma membuka halaman.
                     -->
                    <HcisButton
                        href="/performance-appraisal/master/aturan-penilai"
                        label="Lihat Master Aturan Rater"
                        variant="secondary"
                        size="sm"
                        leading-icon="database"
                    />

                    <HcisButton
                        label="Muat Ulang"
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        @click="muat"
                    />
                </div>
            </div>

            <!-- Lock Warning -->
            <HcisAlert
                v-if="!dapatDisunting"
                variant="warning"
                appearance="subtle"
                :title="alasanTerkunci || 'Langkah ini sedang terkunci untuk penyuntingan.'"
                icon="lock-fill"
            />

            <!-- Error State -->
            <HcisResultState
                v-else-if="error && !memuat"
                status="danger"
                layout="compact"
                title="Aturan rater tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton -->
            <div v-else-if="memuat" class="pa-aturan-skeleton-list">
                <div v-for="n in 3" :key="`skel-aturan-${n}`" class="pa-aturan-skeleton-item">
                    <HcisSkeleton width="200px" height="1.4rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="4.5rem" rounded="md" />
                </div>
            </div>

            <!-- Empty State (Needs Copy from Master) -->
            <HcisEmptyState
                v-else-if="perluDisalin"
                title="Cycle ini belum memiliki salinan aturan rater"
                description="Aturan rater disalin dari master global ke dalam cycle ini, sehingga Anda bebas menyesuaikan bobot 360 khusus cycle ini tanpa mengubah master."
                icon="files"
                primary-text="Salin Aturan Rater dari Master Sekarang"
                primary-icon="download"
                :disabled="!dapatDisunting"
                :primary-loading="menyalin"
                @primary-click="salin"
            />

            <!-- Loaded Content: Accordion Levels -->
            <div v-else class="pa-aturan-content">
                <!-- Summary Cockpit Banner -->
                <div class="pa-cockpit-grid">
                    <div class="pa-cockpit-stat">
                        <span class="pa-cockpit-kicker">EVALUATED LEVELS</span>
                        <strong>{{ level.length }} Level Jabatan</strong>
                        <small class="text-slate-500">Jumlah jenjang aktif dinilai</small>
                    </div>

                    <div class="pa-cockpit-stat" :class="{ 'is-complete': semuaGenap, 'has-holes': !semuaGenap }">
                        <span class="pa-cockpit-kicker">WEIGHT BALANCE STATUS</span>
                        <strong v-if="semuaGenap" class="text-emerald-600">
                            <HcisIcon name="check-circle-fill" size="xs" /> Semua Level 100% Pas
                        </strong>
                        <strong v-else class="text-amber-600">
                            <HcisIcon name="exclamation-triangle-fill" size="xs" /> {{ cacahBelumGenap }} Level Belum 100%
                        </strong>
                        <small v-if="semuaGenap" class="text-emerald-600">Seluruh level siap dihitung</small>
                        <small v-else class="text-amber-700 font-semibold">Total bobot tiap level wajib tepat 100%</small>
                    </div>

                    <div class="pa-cockpit-stat" :class="{ 'has-custom': belumTersimpan.length > 0 }">
                        <span class="pa-cockpit-kicker">CHANGES STATUS</span>
                        <strong v-if="belumTersimpan.length === 0" class="text-indigo-600">
                            <HcisIcon name="shield-check" size="xs" /> Seluruh Level Tersimpan
                        </strong>
                        <strong v-else class="text-amber-600">
                            <HcisIcon name="pencil-fill" size="xs" /> {{ belumTersimpan.length }} Level Belum Disimpan
                        </strong>
                        <small>{{ belumTersimpan.length === 0 ? 'Data tersimpan di cycle' : 'Tekan Simpan Semua untuk merekam' }}</small>
                    </div>

                    <div class="pa-cockpit-stat pa-cockpit-stat--actions">
                        <span class="pa-cockpit-kicker">ACCORDION VIEW</span>
                        <div class="pa-expand-toggle-btns">
                            <button
                                type="button"
                                class="pa-toggle-exp-btn"
                                @click="bukaSemua"
                            >
                                <HcisIcon name="arrows-expand" size="2xs" /> Buka Semua
                            </button>
                            <button
                                type="button"
                                class="pa-toggle-exp-btn"
                                @click="tutupSemua"
                            >
                                <HcisIcon name="arrows-collapse" size="2xs" /> Tutup Semua
                            </button>
                        </div>
                        <small class="text-slate-500">{{ terbuka.length }} dari {{ level.length }} level terbuka</small>
                    </div>
                </div>

                <!-- Warning for levels missing policies -->
                <HcisAlert
                    v-if="levelTanpaAturan.length"
                    variant="warning"
                    appearance="subtle"
                    :title="`${levelTanpaAturan.length} level karyawan aktif belum memiliki aturan rater`"
                    icon="exclamation-triangle-fill"
                >
                    <div class="pa-aturan-unmapped-list">
                        <p class="pa-aturan-unmapped-desc">
                            Karyawan pada level ini akan otomatis mendarat di keranjang tertunda jika penyiapan dijalankan tanpa aturan rater:
                        </p>
                        <div class="pa-unmapped-chips">
                            <span
                                v-for="l in levelTanpaAturan"
                                :key="l.id_level"
                                class="pa-unmapped-chip"
                            >
                                {{ l.nama_level || `Level ${l.id_level}` }} &bull; {{ l.jumlah_karyawan || 0 }} orang
                            </span>
                        </div>
                    </div>
                </HcisAlert>

                <!-- Level Accordion List with Rich Information Header -->
                <HcisCollapse v-model="terbuka" class="pa-aturan-collapse">
                    <HcisCollapseItem
                        v-for="lv in level"
                        :key="lv.id_level"
                        :name="String(lv.id_level)"
                    >
                        <!-- Rich Title & Metadata in Collapse Header Bar -->
                        <template #title>
                            <div class="pa-collapse-header-title-wrap">
                                <div class="pa-collapse-header-main-row">
                                    <strong class="pa-level-title-text">{{ lv.nama_level }}</strong>
                                    <span class="pa-level-id-chip">ID: {{ lv.id_level }}</span>
                                    <span v-if="lv.nama_policy" class="pa-policy-chip">
                                        {{ lv.nama_policy }} (v{{ lv.versi_policy || 1 }})
                                    </span>
                                    <span v-if="berubah(lv)" class="pa-header-unsaved-chip">
                                        <HcisIcon name="pencil-fill" size="2xs" /> Belum Disimpan
                                    </span>
                                    <span v-else-if="lv.disunting_pada" class="pa-header-custom-chip">
                                        <HcisIcon name="sliders" size="2xs" /> Disesuaikan
                                    </span>
                                </div>
                                <div class="pa-collapse-header-sub-row">
                                    <span class="pa-header-summary-text">
                                        {{ ringkasanRater(lv) }}
                                    </span>
                                </div>
                            </div>
                        </template>

                        <!-- Right Status Badge in Collapse Header Bar -->
                        <template #badge>
                            <div class="pa-level-header-badge-wrap" @click.stop>
                                <HcisBadge
                                    v-if="genap(lv)"
                                    label="100% Pas"
                                    variant="success"
                                    appearance="subtle"
                                    size="sm"
                                    dot
                                />
                                <HcisBadge
                                    v-else
                                    :label="`Total: ${totalDraf(lv)}% (Wajib 100%)`"
                                    variant="danger"
                                    appearance="subtle"
                                    size="sm"
                                    dot
                                />
                            </div>
                        </template>

                        <div class="pa-level-policy-panel">
                            <!-- Policy Metadata & Preset Bar -->
                            <div class="pa-level-policy-top">
                                <div class="pa-policy-meta-info">
                                    <span class="pa-policy-name">{{ lv.nama_policy }}</span>
                                    <span class="pa-policy-ver">Versi {{ lv.versi_policy || 1 }}</span>
                                    <span v-if="lv.disunting_pada" class="pa-policy-edited-tag">
                                        <HcisIcon name="pencil-fill" size="2xs" /> Disesuaikan untuk cycle ini
                                    </span>
                                </div>

                                <!-- 1-Click Multi-Rater Presets -->
                                <div v-if="dapatDisunting" class="pa-level-presets">
                                    <span class="pa-preset-label">Model Preset:</span>
                                    <button
                                        type="button"
                                        class="pa-preset-btn"
                                        @click="apply360Preset(lv, 'standard')"
                                    >
                                        Standar 360
                                    </button>
                                    <button
                                        type="button"
                                        class="pa-preset-btn"
                                        @click="apply360Preset(lv, 'topdown')"
                                    >
                                        Top-Down 100%
                                    </button>
                                    <button
                                        type="button"
                                        class="pa-preset-btn"
                                        @click="apply360Preset(lv, 'managerial')"
                                    >
                                        Managerial
                                    </button>
                                </div>
                            </div>

                            <!-- Segmented Category Weight Visual Bar -->
                            <div class="pa-category-weight-bar" aria-hidden="true">
                                <div
                                    v-for="(r, idx) in draf[lv.id_level]"
                                    :key="`bar-${r.id || idx}`"
                                    class="pa-category-segment"
                                    :style="{
                                        width: `${Math.max(0, Math.min(100, Number(r.bobot_persen) || 0))}%`,
                                        backgroundColor: getCategoryColor(r.nama_kategori),
                                    }"
                                    :title="`${formatKategori(r.nama_kategori, r.kode_kategori)}: ${r.bobot_persen}%`"
                                >
                                    <span v-if="Number(r.bobot_persen) >= 16" class="pa-segment-label">
                                        {{ formatKategori(r.nama_kategori, r.kode_kategori) }} ({{ r.bobot_persen }}%)
                                    </span>
                                    <span v-else-if="Number(r.bobot_persen) >= 5" class="pa-segment-label pa-segment-label--compact">
                                        {{ r.bobot_persen }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Category Weight Legend Chips -->
                            <div class="pa-category-weight-legend">
                                <div
                                    v-for="(r, idx) in draf[lv.id_level]"
                                    :key="`leg-${r.id || idx}`"
                                    class="pa-weight-legend-chip"
                                    :class="{ 'is-zero': !(Number(r.bobot_persen) > 0) }"
                                    :title="`${formatKategori(r.nama_kategori, r.kode_kategori)}: ${r.bobot_persen}%`"
                                >
                                    <span class="pa-legend-dot" :style="{ backgroundColor: getCategoryColor(r.nama_kategori) }" />
                                    <span class="pa-legend-name">{{ formatKategori(r.nama_kategori, r.kode_kategori) }}</span>
                                    <strong class="pa-legend-percent">{{ Number(r.bobot_persen) || 0 }}%</strong>
                                </div>
                            </div>

                            <!-- Category Weight Rows -->
                            <div class="pa-category-rows-container">
                                <div
                                    v-for="r in draf[lv.id_level]"
                                    :key="r.id"
                                    class="pa-category-card"
                                >
                                    <!-- Category Title & Relation -->
                                    <div class="pa-category-identity">
                                        <div class="pa-category-badge-dot" :style="{ backgroundColor: getCategoryColor(r.nama_kategori) }" />
                                        <div class="pa-category-identity-text">
                                            <strong>{{ formatKategori(r.nama_kategori, r.kode_kategori) }}</strong>
                                            <small v-if="r.kode_relasi">Relasi: {{ r.kode_relasi }}</small>
                                        </div>
                                    </div>

                                    <!-- Input Numbers Grid (Uniform Row Alignment) -->
                                    <div class="pa-category-inputs-grid">
                                        <div class="pa-input-cell pa-input-cell--bobot">
                                            <label class="pa-input-label">Bobot (%)</label>
                                            <HcisInputNumber
                                                v-model="r.bobot_persen"
                                                size="sm"
                                                :min="0"
                                                :max="100"
                                                suffix="%"
                                                :disabled="!dapatDisunting"
                                            />
                                        </div>

                                        <div class="pa-input-cell">
                                            <label class="pa-input-label">Min Rater</label>
                                            <HcisInputNumber
                                                v-model="r.min_rater"
                                                size="sm"
                                                :min="0"
                                                :disabled="!dapatDisunting"
                                            />
                                        </div>

                                        <div class="pa-input-cell">
                                            <label class="pa-input-label">Maks Rater</label>
                                            <HcisInputNumber
                                                v-model="r.maks_rater"
                                                size="sm"
                                                :min="0"
                                                :placeholder="r.maks_rater === null ? 'Bebas' : ''"
                                                :disabled="!dapatDisunting"
                                            />
                                        </div>

                                        <!-- Min Selesai: Mode Wajib Semua (null) vs Batas Toleransi Khusus (number) -->
                                        <div class="pa-input-cell pa-input-cell--min-selesai">
                                            <div class="pa-input-label">
                                                <span>Min Selesai</span>
                                                <span
                                                    class="pa-min-selesai-mode-tag"
                                                    :class="{ 'is-custom': r.min_rater_selesai !== null && r.min_rater_selesai !== '' }"
                                                >
                                                    {{ (r.min_rater_selesai === null || r.min_rater_selesai === '') ? 'Wajib Semua' : 'Batas Khusus' }}
                                                </span>
                                            </div>

                                            <!-- Mode 1: Nonaktif / Wajib Semua (null) -->
                                            <button
                                                v-if="r.min_rater_selesai === null || r.min_rater_selesai === ''"
                                                type="button"
                                                class="pa-min-selesai-pill"
                                                :disabled="!dapatDisunting"
                                                :title="dapatDisunting ? `Bawaan: Semua ${r.min_rater || 0} rater wajib selesai. Klik untuk atur batas toleransi khusus.` : ''"
                                                @click="toggleMinSelesaiMode(r)"
                                            >
                                                <HcisIcon name="check-all" size="2xs" color="brand" />
                                                <span>Semua ({{ r.min_rater || 0 }})</span>
                                                <HcisIcon v-if="dapatDisunting" name="pencil" size="2xs" class="pa-min-selesai-edit-icon" />
                                            </button>

                                            <!-- Mode 2: Aktif / Batas Toleransi Khusus (number) -->
                                            <div v-else class="pa-min-selesai-custom-wrap">
                                                <HcisInputNumber
                                                    v-model="r.min_rater_selesai"
                                                    size="sm"
                                                    :min="1"
                                                    :max="r.min_rater || 1"
                                                    :disabled="!dapatDisunting"
                                                />
                                                <button
                                                    v-if="dapatDisunting"
                                                    type="button"
                                                    class="pa-min-selesai-reset-btn"
                                                    title="Kembalikan ke 'Wajib Semua'"
                                                    @click="r.min_rater_selesai = null"
                                                >
                                                    <HcisIcon name="x-circle-fill" size="2xs" />
                                                </button>
                                            </div>
                                        </div>

                                        <!--
                                         | Bukan setelan hiasan: server MENOLAK rater
                                         | yang ditambahkan manual pada kategori yang
                                         | mematikannya (`PaAssignmentBuilderService`
                                         | "tidak mengizinkan rater manual menurut aturan
                                         | levelnya"). Efeknya baru terlihat di langkah
                                         | Rater Assignment, jadi keterangannya WAJIB ada
                                         | di sini — tanpa itu ia terbaca seperti tombol
                                         | yang tidak mengubah apa pun.
                                         -->
                                        <div
                                            class="pa-input-cell pa-input-cell--switch"
                                            title="Hidup: pada langkah Rater Assignment, admin boleh menambahkan rater di luar relasi organisasi untuk kategori ini. Mati: hanya orang yang muncul dari relasi organisasi yang boleh dipilih — penambahan manual ditolak server."
                                        >
                                            <label class="pa-input-label">Boleh Tambah Manual</label>
                                            <HcisSwitch
                                                v-model="r.izinkan_manual"
                                                size="sm"
                                                :disabled="!dapatDisunting"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions for Level -->
                            <div class="pa-level-policy-footer">
                                <div class="pa-level-sum-stat">
                                    <span>Total Bobot Kategori:</span>
                                    <strong :class="{ 'text-danger': !genap(lv), 'text-success': genap(lv) }">
                                        {{ totalDraf(lv) }}%
                                    </strong>
                                    <small v-if="!genap(lv)" class="text-danger font-medium">(Wajib tepat 100%)</small>
                                </div>

                                <HcisButton
                                    label="Simpan Level Ini"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="check-lg"
                                    :disabled="!dapatDisunting || !genap(lv)"
                                    :loading="sibuk === lv.id_level"
                                    @click="simpan(lv)"
                                />
                            </div>
                        </div>
                    </HcisCollapseItem>
                </HcisCollapse>
            </div>

            <!-- Footer Navigation -->
            <div class="pa-step-footer">
                <div class="pa-footer-feedback">
                    <span v-if="pesan" class="pa-step-message" :class="{ 'is-error': pesanError }">
                        <HcisIcon :name="pesanError ? 'exclamation-circle-fill' : 'check-circle-fill'" size="2xs" />
                        <span>{{ pesan }}</span>
                    </span>
                    <span v-else class="pa-footer-hint">
                        Pastikan seluruh level memiliki total bobot tepat 100% sebelum melanjutkan ke tahap Peserta.
                    </span>
                </div>

                <div class="pa-step-nav-buttons">
                    <HcisButton
                        label="Kembali ke Langkah 2: Question Packages"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        @click="$emit('kembali')"
                    />
                    <HcisButton
                        label="Lanjut ke Langkah 4: Participants"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        @click="lanjut"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog: Batch Apply Model to All Levels -->
        <HcisDialog
            v-model="batchModelDialogOpen"
            title="Terapkan Model 360 ke Seluruh Level"
            kicker="MODEL BOBOT MULTI-RATER"
            icon="lightning-charge-fill"
            sentiment="brand"
            size="md"
        >
            <div class="pa-batch-model-body">
                <p class="pa-batch-instruction">
                    Pilih model distribusi bobot rater 360 yang akan diterapkan secara serentak ke seluruh level jabatan:
                </p>

                <div class="pa-model-cards-grid">
                    <div
                        class="pa-model-card"
                        :class="{ active: selectedModelPreset === 'standard' }"
                        @click="selectedModelPreset = 'standard'"
                    >
                        <strong>Standar 360 Multidireksional</strong>
                        <span>Atasan (50%), Peer (20%), Bawahan (20%), Diri Sendiri (10%)</span>
                    </div>

                    <div
                        class="pa-model-card"
                        :class="{ active: selectedModelPreset === 'topdown' }"
                        @click="selectedModelPreset = 'topdown'"
                    >
                        <strong>Top-Down Klasik 100%</strong>
                        <span>Atasan memegang 100% bobot evaluasi kinerja.</span>
                    </div>

                    <div
                        class="pa-model-card"
                        :class="{ active: selectedModelPreset === 'managerial' }"
                        @click="selectedModelPreset = 'managerial'"
                    >
                        <strong>Model Kepemimpinan / Managerial</strong>
                        <span>Atasan (60%), Bawahan (30%), Diri Sendiri (10%)</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" @click="batchModelDialogOpen = false" />
                <HcisButton
                    variant="primary"
                    label="Terapkan ke Seluruh Level"
                    size="sm"
                    leading-icon="check-all"
                    @click="applyModelToAll"
                />
            </template>
        </HcisDialog>

        <!-- Dialog: Unsaved Changes Confirmation Guard -->
        <HcisDialog
            v-model="dialogBelumTersimpan"
            title="Ada aturan rater yang belum disimpan"
            icon="exclamation-triangle-fill"
            sentiment="warning"
            size="md"
            variant="glass"
            animated
            :loading="menyimpanSemua"
        >
            <div class="pa-unsaved-dialog-body">
                <p class="pa-unsaved-dialog-desc">
                    <strong>{{ belumTersimpan.length }} level jabatan</strong> masih memiliki perubahan aturan rater yang belum disimpan. Jika Anda melanjutkan tanpa menyimpan, perubahan tersebut akan hilang.
                </p>

                <div class="pa-unsaved-level-card">
                    <div class="pa-unsaved-level-header">
                        <HcisIcon name="list-task" size="2xs" />
                        <span>Daftar Level Belum Disimpan:</span>
                    </div>
                    <div class="pa-unsaved-level-chips">
                        <div
                            v-for="lv in belumTersimpan"
                            :key="lv.id_level"
                            class="pa-unsaved-item-pill"
                        >
                            <HcisBadge
                                :label="lv.nama_level"
                                :variant="genap(lv) ? 'warning' : 'danger'"
                                appearance="glass"
                                size="sm"
                                dot
                            />
                            <span v-if="!genap(lv)" class="pa-unsaved-error-note">
                                (bobot {{ totalDraf(lv) }}%, belum genap 100%)
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="belumTersimpan.some((lv) => !genap(lv))" class="pa-unsaved-warning-alert">
                    <HcisIcon name="info-circle-fill" size="2xs" class="text-amber-600 shrink-0" />
                    <span>Level yang bobotnya belum genap 100% tidak bisa disimpan; perbaiki dulu atau lanjutkan dan buang perubahannya.</span>
                </div>
            </div>

            <template #footer>
                <div class="pa-unsaved-dialog-actions">
                    <HcisButton
                        variant="ghost"
                        label="Batal"
                        size="sm"
                        :disabled="menyimpanSemua"
                        @click="dialogBelumTersimpan = false"
                    />
                    <HcisButton
                        variant="secondary"
                        label="Lanjut Tanpa Menyimpan"
                        size="sm"
                        :disabled="menyimpanSemua"
                        @click="lanjutkanTanpaMenyimpan"
                    />
                    <HcisButton
                        variant="primary"
                        label="Simpan Semua &amp; Lanjut"
                        size="sm"
                        leading-icon="check-all"
                        :loading="menyimpanSemua"
                        :disabled="menyimpanSemua || belumTersimpan.some((lv) => !genap(lv))"
                        @click="simpanSemuaLaluLanjut"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, shallowRef } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCollapse,
    HcisCollapseItem,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisInputNumber,
    HcisResultState,
    HcisSkeleton,
    HcisSwitch,
} from '@/components/ui';
import paApi from '../../api/paApi.js';
import { pesanGagalSebagian } from '../../utils/simpanBertahap.js';

defineOptions({ name: 'PaStepAturanPenilai' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },
});

const emit = defineEmits(['tersimpan', 'kembali', 'lanjut']);

const level = shallowRef([]);
const perluDisalin = ref(false);
const levelTanpaAturan = shallowRef([]);
const terbuka = ref([]);
const draf = reactive({});

const dialogBelumTersimpan = ref(false);

const memuat = ref(false);
const menyalin = ref(false);
const error = ref('');
const pesan = ref('');
const pesanError = ref(false);
const sibuk = ref(null);
const menyimpanSemua = ref(false);

const batchModelDialogOpen = ref(false);
const selectedModelPreset = ref('standard');

const semuaGenap = computed(() => {
    return level.value.every((lv) => genap(lv));
});

const cacahBelumGenap = computed(() => {
    return level.value.filter((lv) => !genap(lv)).length;
});

function getCategoryColor(name) {
    const s = String(name || '').toLowerCase();
    if (s.includes('atasan langsung')) return '#4f46e5';
    if (s.includes('atasan tidak langsung') || s.includes('atasan 2')) return '#06b6d4';
    if (s.includes('peer') || s.includes('sejawat') || s.includes('rekan')) return '#10b981';
    if (s.includes('bawahan') || s.includes('subordinate')) return '#f59e0b';
    if (s.includes('diri') || s.includes('self')) return '#8b5cf6';
    return '#64748b';
}

function formatKategori(nama, kode) {
    const n = String(nama || '');
    const k = String(kode || '').toUpperCase();
    if (k === 'PEER' || n.toLowerCase().includes('sejawat') || n.toLowerCase().includes('rekan')) {
        return 'Peer';
    }
    return n;
}

const belumTersimpan = computed(() =>
    level.value.filter((lv) => {
        const kini = draf[lv.id_level] ?? [];

        return lv.rincian?.some((r, i) => {
            const d = kini[i];

            if (!d) return false;

            return Number(d.bobot_persen) !== Number(r.bobot_persen)
                || Number(d.min_rater) !== Number(r.min_rater)
                || Number(d.maks_rater ?? -1) !== Number(r.maks_rater ?? -1)
                || (d.min_rater_selesai ?? null) !== (r.min_rater_selesai ?? null)
                || Boolean(d.izinkan_manual) !== Boolean(r.izinkan_manual);
        }) ?? false;
    }),
);

function lanjut() {
    if (belumTersimpan.value.length === 0) {
        emit('lanjut');
        return;
    }

    dialogBelumTersimpan.value = true;
}

async function simpanSemuaLaluLanjut() {
    await simpanSemua();

    if (belumTersimpan.value.length === 0) {
        dialogBelumTersimpan.value = false;
        emit('lanjut');
    }
}

function lanjutkanTanpaMenyimpan() {
    dialogBelumTersimpan.value = false;
    emit('lanjut');
}

function totalDraf(lv) {
    const baris = draf[lv.id_level] ?? [];
    const sum = baris.reduce((acc, r) => acc + (Number(r.bobot_persen) || 0), 0);
    return Math.round(sum * 100) / 100;
}

function genap(lv) {
    return Math.abs(totalDraf(lv) - 100) < 0.01;
}

function berubah(lv) {
    const kini = draf[lv.id_level] ?? [];
    return lv.rincian?.some((r, i) => {
        const d = kini[i];
        if (!d) return false;
        return Number(d.bobot_persen) !== Number(r.bobot_persen)
            || Number(d.min_rater) !== Number(r.min_rater)
            || Number(d.maks_rater ?? -1) !== Number(r.maks_rater ?? -1)
            || (d.min_rater_selesai ?? null) !== (r.min_rater_selesai ?? null)
            || Boolean(d.izinkan_manual) !== Boolean(r.izinkan_manual);
    }) ?? false;
}

function ringkasanRater(lv) {
    const baris = draf[lv.id_level] ?? [];
    const aktif = baris.filter((r) => Number(r.bobot_persen) > 0);
    if (!aktif.length) return 'Belum ada pembagian bobot rater';
    return aktif.map((r) => `${formatKategori(r.nama_kategori, r.kode_kategori)}: ${r.bobot_persen}%`).join(' · ');
}

/* Preset adalah TITIK AWAL yang dipilih admin, bukan aturan resmi. Dicocokkan lewat KODE
   kategori master (ATASAN/BAWAHAN/PEER/SELF): pencocokan nama dulu mencari "atasan langsung",
   padahal master menamainya "Atasan", sehingga bobot atasan selalu 0 dan totalnya tidak 100. */
const PRESET_BOBOT = {
    standard: { ATASAN: 50, BAWAHAN: 20, PEER: 20, SELF: 10 },
    topdown: { ATASAN: 100 },
    managerial: { ATASAN: 60, BAWAHAN: 30, SELF: 10 },
};

function apply360Preset(lv, presetType) {
    const rows = draf[lv.id_level];
    const preset = PRESET_BOBOT[presetType];
    if (!rows || !preset) return;

    for (const r of rows) {
        r.bobot_persen = preset[String(r.kode_kategori || '').toUpperCase()] ?? 0;
    }
}

function bukaBatchModelDialog() {
    selectedModelPreset.value = 'standard';
    batchModelDialogOpen.value = true;
}

function applyModelToAll() {
    for (const lv of level.value) {
        apply360Preset(lv, selectedModelPreset.value);
    }
    batchModelDialogOpen.value = false;
    pesan.value = `Model ${selectedModelPreset.value} telah diterapkan ke semua level. Klik "Simpan Semua Level" untuk merekam permanen.`;
    pesanError.value = false;
}

function toggleMinSelesaiMode(r) {
    if (r.min_rater_selesai === null || r.min_rater_selesai === undefined || r.min_rater_selesai === '') {
        const minVal = Number(r.min_rater) || 2;
        r.min_rater_selesai = Math.max(1, minVal > 1 ? minVal - 1 : 1);
    } else {
        r.min_rater_selesai = null;
    }
}

function inisialisasiDraf(levels) {
    for (const lv of levels) {
        draf[lv.id_level] = (lv.rincian ?? []).map((r) => ({
            id: r.id,
            kategori_id: r.kategori_id,
            nama_kategori: r.nama_kategori,
            kode_relasi: r.kode_relasi,
            bobot_persen: Number(r.bobot_persen),
            min_rater: Number(r.min_rater),
            maks_rater: r.maks_rater,
            min_rater_selesai: r.min_rater_selesai,
            izinkan_manual: Boolean(r.izinkan_manual),
        }));
    }
}

function bukaSemua() {
    terbuka.value = level.value.map((l) => String(l.id_level));
}

function tutupSemua() {
    terbuka.value = [];
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const response = await paApi.daftarAturanPenilai(props.cycleId);
        const data = response?.result ?? response?.data ?? response;

        level.value = data?.level ?? [];
        perluDisalin.value = Boolean(data?.perlu_disalin);
        levelTanpaAturan.value = data?.level_tanpa_aturan ?? [];

        inisialisasiDraf(level.value);

        if (!terbuka.value.length && level.value.length) {
            terbuka.value = [String(level.value[0].id_level)];
        }
    } catch (e) {
        error.value = e?.message || 'Gagal memuat aturan rater.';
    } finally {
        memuat.value = false;
    }
}

async function salin() {
    menyalin.value = true;
    pesan.value = '';
    pesanError.value = false;

    try {
        await paApi.salinAturanPenilai(props.cycleId);
        pesan.value = 'Berhasil menyalin aturan rater dari master global.';
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menyalin aturan rater dari master.';
        pesanError.value = true;
    } finally {
        menyalin.value = false;
    }
}

async function simpan(lv) {
    if (!genap(lv)) {
        pesan.value = `Total bobot untuk ${lv.nama_level} harus tepat 100% (saat ini ${totalDraf(lv)}%).`;
        pesanError.value = true;
        return;
    }

    sibuk.value = lv.id_level;
    pesan.value = '';
    pesanError.value = false;

    try {
        const rows = draf[lv.id_level] ?? [];
        await paApi.simpanAturanPenilai(
            props.cycleId,
            lv.id_level,
            rows.map((r) => ({
                id: r.id,
                bobot_persen: Number(r.bobot_persen),
                min_rater: Number(r.min_rater),
                maks_rater: r.maks_rater === null || r.maks_rater === '' ? null : Number(r.maks_rater),
                min_rater_selesai: r.min_rater_selesai === null || r.min_rater_selesai === ''
                    ? null
                    : Number(r.min_rater_selesai),
                izinkan_manual: Boolean(r.izinkan_manual),
            }))
        );

        pesan.value = `Aturan rater untuk ${lv.nama_level} berhasil disimpan.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menyimpan aturan rater level ini.';
        pesanError.value = true;
    } finally {
        sibuk.value = null;
    }
}

async function simpanSemua() {
    for (const lv of level.value) {
        if (!genap(lv)) {
            pesan.value = `Gagal simpan: Total bobot ${lv.nama_level} masih ${totalDraf(lv)}% (wajib 100%).`;
            pesanError.value = true;
            return;
        }
    }

    menyimpanSemua.value = true;
    pesan.value = '';
    pesanError.value = false;

    let tersimpan = 0;
    let sedang = null;
    try {
        for (const lv of level.value) {
            sedang = lv;
            const rows = draf[lv.id_level] ?? [];
            await paApi.simpanAturanPenilai(
                props.cycleId,
                lv.id_level,
                rows.map((r) => ({
                    id: r.id,
                    bobot_persen: Number(r.bobot_persen),
                    min_rater: Number(r.min_rater),
                    maks_rater: r.maks_rater === null || r.maks_rater === '' ? null : Number(r.maks_rater),
                    min_rater_selesai: r.min_rater_selesai === null || r.min_rater_selesai === ''
                        ? null
                        : Number(r.min_rater_selesai),
                    izinkan_manual: Boolean(r.izinkan_manual),
                }))
            );
            tersimpan++;
        }

        pesan.value = `Seluruh ${level.value.length} level aturan rater berhasil disimpan.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        /* Draf level yang BELUM tersimpan dibiarkan (tidak dimuat ulang) supaya suntingan
           admin tidak hilang; yang sudah tersimpan memang sudah berlaku di server. */
        pesan.value = pesanGagalSebagian({ tersimpan, total: level.value.length, gagalDi: sedang?.nama_level || '', galat: e });
        pesanError.value = true;
        if (tersimpan > 0) emit('tersimpan');
    } finally {
        menyimpanSemua.value = false;
    }
}

onMounted(muat);
</script>

<style scoped>
.pa-step-root {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-step-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

/* Step Header */
.pa-step-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-step-header-identity {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 42rem;
}

.pa-step-kicker {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #4f46e5;
}

.pa-step-title {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-step-description {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-aturan-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

.pa-aturan-skeleton-item {
    padding: 1rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-aturan-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
}

/* 4-Metric Cockpit Summary */
.pa-cockpit-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
    width: 100%;
    box-sizing: border-box;
}

.pa-cockpit-stat {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    min-width: 0;
}

.pa-cockpit-stat--actions {
    justify-content: space-between;
}

.pa-cockpit-kicker {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #4f46e5;
}

.pa-cockpit-stat strong {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cockpit-stat small {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cockpit-stat.is-complete {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.pa-cockpit-stat.has-holes {
    background: #fffbeb;
    border-color: #fde68a;
}

.pa-cockpit-stat.has-custom {
    background: #f8faff;
    border-color: #c7d2fe;
}

.pa-expand-toggle-btns {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.1rem;
}

.pa-toggle-exp-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-toggle-exp-btn:hover {
    border-color: #6366f1;
    color: #4338ca;
    background: #f8faff;
}

.pa-aturan-unmapped-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-aturan-unmapped-desc {
    margin: 0;
    font-size: 0.78rem;
    color: #92400e;
}

.pa-unmapped-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-unmapped-chip {
    font-size: 0.72rem;
    font-weight: 700;
    color: #78350f;
    background: #fef3c7;
    border: 1px solid #fde68a;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
}

/* Accordion Styling */
.pa-aturan-collapse {
    border-radius: var(--hcis-radius-md, 12px);
    overflow: hidden;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-collapse-header-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    text-align: left;
}

.pa-collapse-header-main-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-level-title-text {
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-id-chip {
    font-size: 0.65rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.pa-policy-chip {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-header-unsaved-chip {
    font-size: 0.65rem;
    font-weight: 700;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid #fde68a;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-header-custom-chip {
    font-size: 0.65rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-collapse-header-sub-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.pa-header-summary-text {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-level-header-badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-level-policy-panel {
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
    padding: 1.15rem;
    background: #fafbff;
    border-top: 1px solid #eef2ff;
}

.pa-level-policy-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-policy-meta-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-policy-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e1b4b;
}

.pa-policy-ver {
    font-size: 0.72rem;
    font-weight: 650;
    color: #64748b;
    background: #e2e8f0;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.pa-policy-edited-tag {
    font-size: 0.7rem;
    font-weight: 650;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-level-presets {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-preset-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: #64748b;
}

.pa-preset-btn {
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-preset-btn:hover {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
}

.pa-category-weight-bar {
    display: flex;
    height: 1.65rem;
    border-radius: 8px;
    overflow: hidden;
    background: #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
}

.pa-category-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transition: width 220ms ease;
    overflow: hidden;
    padding: 0 0.15rem;
    box-sizing: border-box;
}

.pa-segment-label {
    font-size: 0.72rem;
    font-weight: 750;
    color: #ffffff;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-segment-label--compact {
    font-size: 0.66rem;
    font-weight: 800;
}

.pa-category-weight-legend {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.pa-weight-legend-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    font-size: 0.7rem;
    color: #334155;
}

.pa-weight-legend-chip.is-zero {
    opacity: 0.5;
    background: #f8fafc;
}

.pa-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    flex-shrink: 0;
}

.pa-legend-name {
    font-weight: 600;
    color: #475569;
}

.pa-legend-percent {
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #0f172a;
}

.pa-category-rows-container {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-category-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.95rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-category-identity {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 12rem;
}

.pa-category-identity-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-category-badge-dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    flex-shrink: 0;
}

.pa-category-identity strong {
    font-size: 0.86rem;
    color: #0f172a;
    display: block;
}

.pa-category-identity small {
    font-size: 0.7rem;
    color: #64748b;
}

.pa-category-inputs-grid {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-input-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    width: 6.5rem;
    min-width: 6rem;
}

.pa-input-cell--bobot {
    width: 7.25rem;
    min-width: 6.75rem;
}

.pa-input-cell--min-selesai {
    width: 9rem;
    min-width: 8.5rem;
}

.pa-min-selesai-pill {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
    height: 2rem;
    padding: 0 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 140ms ease;
    width: 100%;
    box-sizing: border-box;
}

.pa-min-selesai-pill:hover:not(:disabled) {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
}

.pa-min-selesai-pill:disabled {
    cursor: not-allowed;
    opacity: 0.75;
}

.pa-min-selesai-edit-icon {
    opacity: 0.5;
    margin-left: auto;
}

.pa-min-selesai-custom-wrap {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    width: 100%;
}

.pa-min-selesai-reset-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 2rem;
    border: none;
    background: transparent;
    color: #94a3b8;
    border-radius: 4px;
    cursor: pointer;
    transition: color 120ms ease;
    flex-shrink: 0;
}

.pa-min-selesai-reset-btn:hover {
    color: #ef4444;
}

.pa-min-selesai-mode-tag {
    font-size: 0.6rem;
    font-weight: 750;
    padding: 0.05rem 0.3rem;
    border-radius: 3px;
    color: #64748b;
    background: #f1f5f9;
}

.pa-min-selesai-mode-tag.is-custom {
    color: #b45309;
    background: #fef3c7;
}

.pa-input-cell--switch {
    width: auto;
    min-width: 4.5rem;
    align-items: center;
}

.pa-input-label {
    font-size: 0.68rem;
    font-weight: 650;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
    min-height: 1.1rem;
}

.pa-level-policy-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
}

.pa-level-sum-stat {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    color: #475569;
}

.pa-level-sum-stat strong {
    font-size: 0.95rem;
    font-weight: 750;
}

/* Step Footer Navigation */
.pa-step-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-footer-feedback {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1;
    min-width: 14rem;
}

.pa-step-message {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
    font-size: 0.76rem;
    font-weight: 650;
}

.pa-step-message.is-error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}

.pa-footer-hint {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-step-nav-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Batch Model Dialog */
.pa-batch-model-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-batch-instruction {
    font-size: 0.82rem;
    color: #475569;
    margin: 0;
}

.pa-model-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-model-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.75rem 0.95rem;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-model-card:hover {
    border-color: #818cf8;
    background: #f8faff;
}

.pa-model-card.active {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 1px #4f46e5;
}

.pa-model-card strong {
    font-size: 0.88rem;
    color: #1e1b4b;
}

.pa-model-card span {
    font-size: 0.76rem;
    color: #64748b;
}

.pa-unsaved-dialog-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-unsaved-dialog-desc {
    margin: 0;
    font-size: 0.86rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #334155);
}

.pa-unsaved-level-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(254, 243, 199, 0.45);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.pa-unsaved-level-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #92400e;
}

.pa-unsaved-level-chips {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-unsaved-item-pill {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-unsaved-error-note {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-danger-600, #dc2626);
}

.pa-unsaved-warning-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(254, 243, 199, 0.6);
    border: 1px solid rgba(245, 158, 11, 0.25);
    font-size: 0.74rem;
    color: #92400e;
    line-height: 1.4;
}

.pa-unsaved-dialog-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
}

@media (max-width: 640px) {
    .pa-unsaved-dialog-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-unsaved-dialog-actions :deep(button) {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 1024px) {
    .pa-cockpit-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .pa-cockpit-grid {
        grid-template-columns: 1fr;
    }
    .pa-step-header {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-step-header-actions {
        width: 100%;
    }
    .pa-step-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-step-nav-buttons {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
