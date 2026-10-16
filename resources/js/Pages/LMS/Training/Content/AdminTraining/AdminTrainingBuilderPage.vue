<template>
    <div class="container-fluid px-0 builder-app-container py-3">
        <HeaderKpi
            :title="isEdit ? 'Edit Training' : 'Buat Training Baru'"
            subtitle="Susun hierarki training: Section → Item → Konten → Aktivitas"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <div class="mt-n4 mb-5">
            <!-- Loading -->
            <div v-if="pageLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem"></div>
                <p class="mt-3 text-muted fw-medium">Memuat data...</p>
            </div>

            <template v-else>
                <!-- SECTION A: Training Info -->
                <div class="stitch-card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="p-4 p-md-5">
                        <div
                            class="d-flex align-items-center gap-3 mb-4 cursor-pointer"
                            @click="showInfoSection = !showInfoSection"
                        >
                            <div
                                class="avatar-sm bg-primary-subtle text-primary fw-bold shadow-sm d-flex justify-content-center align-items-center"
                                style="width: 42px; height: 42px; border-radius: 50%"
                            >
                                <i class="bi bi-info-circle fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0 form-section-title">Informasi Training</h5>
                                <p class="text-muted small mb-0">Atur profil dasar, kategori, metode, dan durasi pembelajaran</p>
                            </div>
                            <i
                                class="bi ms-auto text-secondary"
                                :class="showInfoSection ? 'bi-chevron-up' : 'bi-chevron-down'"
                                style="font-size: 1.1rem"
                            ></i>
                        </div>

                        <transition name="slide-down">
                            <div v-show="showInfoSection">
                                <div class="row g-3 g-md-4">
                                    <!-- Thumbnail -->
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-image text-primary"></i>
                                            Thumbnail Training
                                        </label>
                                        <div class="thumbnail-upload-area position-relative">
                                            <input
                                                ref="thumbnailInput"
                                                type="file"
                                                accept="image/*"
                                                class="d-none"
                                                @change="onThumbnailChange"
                                            />
                                            <div v-if="thumbnailPreview" class="thumbnail-preview-box">
                                                <img
                                                    :src="thumbnailPreview"
                                                    class="thumbnail-img rounded-3"
                                                    @click="openThumbnailModal"
                                                    style="cursor: pointer"
                                                    alt="Thumbnail"
                                                />
                                                <div class="thumbnail-actions">
                                                    <button
                                                        class="stitch-btn secondary text-xs"
                                                        @click="openThumbnailModal"
                                                        title="Perbesar"
                                                        type="button"
                                                    >
                                                        <i class="bi bi-arrows-fullscreen"></i>
                                                    </button>
                                                    <button
                                                        class="stitch-btn outline-primary text-xs"
                                                        @click="triggerThumbnailInput"
                                                        title="Ganti Thumbnail"
                                                        type="button"
                                                    >
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                    <button
                                                        class="stitch-btn outline-danger text-xs"
                                                        @click="removeThumbnail"
                                                        title="Hapus Thumbnail"
                                                        type="button"
                                                    >
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="thumbnail-upload-drop d-flex flex-column align-items-center justify-content-center text-center"
                                                @dragover.prevent
                                                @drop.prevent="onDropThumbnail"
                                                @click="triggerThumbnailInput"
                                            >
                                                <i class="bi bi-cloud-arrow-up fs-2 text-primary mb-2"></i>
                                                <div class="fw-bold text-dark mb-1">Upload Thumbnail</div>
                                                <div class="text-muted small mb-2">
                                                    Klik atau drag & drop file ke sini (JPG, PNG, maks 2MB)
                                                </div>
                                                <div class="stitch-btn outline-primary text-xs fw-bold px-3">
                                                    Pilih Gambar
                                                </div>
                                            </div>
                                            <div v-if="thumbnailError" class="validation-error mt-2">
                                                {{ thumbnailError }}
                                            </div>
                                        </div>

                                        <!-- Modal Preview Thumbnail -->
                                        <transition name="preview-fade">
                                            <div
                                                v-if="showThumbnailModal"
                                                class="thumbnail-modal-overlay"
                                                @click.self="closeThumbnailModal"
                                            >
                                                <div class="thumbnail-modal-content rounded-4 shadow-lg">
                                                    <img
                                                        :src="thumbnailPreview"
                                                        class="w-100 rounded-3"
                                                        style="max-height: 70vh; object-fit: contain"
                                                        alt="Preview"
                                                    />
                                                    <button
                                                        class="btn btn-light border rounded-circle position-absolute top-0 end-0 m-3"
                                                        style="width: 40px; height: 40px"
                                                        @click="closeThumbnailModal"
                                                        type="button"
                                                    >
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </transition>
                                    </div>

                                    <!-- Judul Training -->
                                    <div class="col-12 col-lg-8">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-type text-primary"></i>
                                            Judul Training <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            v-model="form.judul_training"
                                            type="text"
                                            class="form-control rounded-3 builder-input"
                                            placeholder="Contoh: Basic K3L Safety Training"
                                        />
                                    </div>

                                    <!-- Kategori -->
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-bookmark text-primary"></i>
                                            Kategori
                                        </label>
                                        <el-select
                                            v-model="form.id_training_kategori"
                                            placeholder="Pilih Kategori"
                                            class="w-100 builder-select"
                                            size="large"
                                            clearable
                                        >
                                            <el-option
                                                v-for="k in masterData.kategori"
                                                :key="k.id"
                                                :label="k.nama"
                                                :value="k.id"
                                            />
                                        </el-select>
                                    </div>

                                    <!-- Status -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-toggle-on text-primary"></i>
                                            Status
                                        </label>
                                        <el-select v-model="form.flag_aktif" class="w-100 builder-select" size="large">
                                            <el-option label="Aktif" value="Y" />
                                            <el-option label="Non-Aktif" value="T" />
                                        </el-select>
                                    </div>

                                    <!-- Standar Kelulusan -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-patch-check text-primary"></i>
                                            Standar Kelulusan
                                        </label>
                                        <input
                                            v-model="form.standar_kelulusan"
                                            type="text"
                                            inputmode="decimal"
                                            class="form-control rounded-3 builder-input"
                                            placeholder="Contoh: 75"
                                            @input="sanitizeStandarKelulusanInput"
                                        />
                                    </div>

                                    <!-- Total Durasi (menit) -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-hourglass-split text-primary"></i>
                                            Total Durasi (menit)
                                        </label>
                                        <input
                                            :value="computedTotalDurasi ?? ''"
                                            type="number"
                                            min="0"
                                            class="form-control rounded-3 builder-input bg-light"
                                            placeholder="Otomatis dari Durasi"
                                            readonly
                                        />
                                        <small class="text-muted d-block mt-1">Otomatis dihitung dari list Durasi</small>
                                    </div>

                                    <!-- Deskripsi -->
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-card-text text-primary"></i>
                                            Deskripsi
                                        </label>
                                        <textarea
                                            v-model="form.deskripsi"
                                            class="form-control rounded-3"
                                            rows="3"
                                            placeholder="Deskripsi singkat tentang training ini..."
                                        ></textarea>
                                    </div>

                                    <!-- Outline Session -->
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-list-ol text-primary"></i>
                                            Outline Session
                                        </label>
                                        <el-select
                                            v-model="form.outline_session"
                                            class="w-100 builder-select"
                                            multiple
                                            filterable
                                            allow-create
                                            default-first-option
                                            :reserve-keyword="false"
                                            collapse-tags
                                            collapse-tags-tooltip
                                            placeholder="Tambah outline, tekan Enter"
                                            size="large"
                                        >
                                            <el-option
                                                v-for="item in outlineSessionOptions"
                                                :key="item"
                                                :label="item"
                                                :value="item"
                                            />
                                        </el-select>
                                    </div>

                                    <!-- Sasaran Peserta -->
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-people text-primary"></i>
                                            Sasaran Peserta
                                        </label>
                                        <el-select
                                            v-model="form.sasaran_peserta"
                                            class="w-100 builder-select"
                                            multiple
                                            filterable
                                            allow-create
                                            default-first-option
                                            :reserve-keyword="false"
                                            collapse-tags
                                            collapse-tags-tooltip
                                            placeholder="Tambah sasaran peserta, tekan Enter"
                                            size="large"
                                        >
                                            <el-option
                                                v-for="item in sasaranPesertaOptions"
                                                :key="item"
                                                :label="item"
                                                :value="item"
                                            />
                                        </el-select>
                                    </div>

                                    <!-- Metode -->
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-diagram-3 text-primary"></i>
                                            Metode
                                        </label>
                                        <el-select
                                            v-model="form.metode"
                                            class="w-100 builder-select"
                                            multiple
                                            filterable
                                            allow-create
                                            default-first-option
                                            :reserve-keyword="false"
                                            collapse-tags
                                            collapse-tags-tooltip
                                            placeholder="Tambah metode, tekan Enter"
                                            size="large"
                                        >
                                            <el-option
                                                v-for="item in metodeOptions"
                                                :key="item"
                                                :label="item"
                                                :value="item"
                                            />
                                        </el-select>
                                    </div>

                                    <!-- Durasi Builder -->
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-clock-history text-primary"></i>
                                            Durasi Breakdown
                                        </label>
                                        <div class="free-tag-input">
                                            <div v-if="form.durasi.length" class="free-tag-list mb-2">
                                                <span
                                                    v-for="(tag, idx) in form.durasi"
                                                    :key="`durasi-${idx}-${tag}`"
                                                    class="free-tag-chip"
                                                >
                                                    {{ tag }}
                                                    <button
                                                        type="button"
                                                        class="free-tag-remove"
                                                        @click="removeTagItem('durasi', idx)"
                                                        title="Hapus durasi"
                                                    >
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </span>
                                            </div>
                                            <div class="tag-input-row tag-input-row-durasi">
                                                <input
                                                    v-model="durasiDraft.menit"
                                                    type="text"
                                                    inputmode="decimal"
                                                    class="form-control rounded-3 builder-input"
                                                    placeholder="Menit (angka)"
                                                    @input="sanitizeDurasiMenitInput"
                                                    @keydown.enter.prevent="addDurasiTag()"
                                                />
                                                <input
                                                    v-model="durasiDraft.judul"
                                                    type="text"
                                                    class="form-control rounded-3 builder-input"
                                                    placeholder="Judul durasi (contoh: In Class)"
                                                    @keydown.enter.prevent="addDurasiTag()"
                                                />
                                                <button
                                                    type="button"
                                                    class="stitch-btn primary text-sm px-3 py-2"
                                                    @click="addDurasiTag()"
                                                >
                                                    Tambah
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Kiri angka menit (boleh desimal), kanan judul durasi.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <!-- SECTION B: Section Builder -->
                <div class="stitch-card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="avatar-sm bg-warning-subtle text-warning fw-bold shadow-sm d-flex justify-content-center align-items-center"
                                    style="width: 42px; height: 42px; border-radius: 50%"
                                >
                                    <i class="bi bi-layers fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0 form-section-title">Sections & Konten</h5>
                                    <p class="text-muted small mb-0">Susun struktur pembelajaran per modul & aktivitas</p>
                                </div>
                            </div>
                            <button
                                class="stitch-btn primary text-sm fw-bold d-inline-flex align-items-center gap-2 shadow-sm"
                                @click="addSection"
                                type="button"
                            >
                                <i class="bi bi-plus-circle"></i> Tambah Section
                            </button>
                        </div>

                        <div
                            v-if="form.sections.length === 0"
                            class="text-center py-5 text-muted d-flex flex-column align-items-center gap-2 empty-section-box rounded-4"
                        >
                            <i class="bi bi-inbox fs-1 text-primary opacity-50 mb-1"></i>
                            <p class="mb-2 fw-medium">Belum ada section materi.</p>
                            <button
                                class="stitch-btn outline-primary text-xs fw-bold"
                                @click="addSection"
                                type="button"
                            >
                                <i class="bi bi-plus-circle me-1"></i> Tambah Section Pertama
                            </button>
                        </div>

                        <!-- Each Section -->
                        <div
                            v-for="(section, sIdx) in form.sections"
                            :key="sIdx"
                            class="section-card mb-4 rounded-4 border overflow-hidden shadow-sm"
                        >
                            <!-- Section Header -->
                            <div
                                class="section-header p-3 px-md-4 d-flex align-items-center gap-3"
                                :class="{ collapsed: section._collapsed, 'opacity-75 bg-light': section.flag_aktif === 'T' }"
                                @click="section._collapsed = !section._collapsed"
                            >
                                <div class="section-order-badge">
                                    {{ sIdx + 1 }}
                                </div>
                                <div class="flex-grow-1 min-w-0" @click.stop>
                                    <input
                                        v-model="section.nama_sub"
                                        type="text"
                                        class="form-control section-title-input"
                                        placeholder="Nama Section (cth: Pre Test, Materials, Video & Quiz)"
                                    />
                                </div>
                                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0" @click.stop>
                                    <div class="form-check form-switch mb-0 d-flex align-items-center me-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            :checked="section.flag_aktif === 'Y'"
                                            @change="section.flag_aktif = $event.target.checked ? 'Y' : 'T'"
                                            :id="'sec-status-' + sIdx"
                                        />
                                        <label
                                            class="form-check-label small ms-2 fw-semibold d-none d-sm-inline"
                                            :for="'sec-status-' + sIdx"
                                            :class="section.flag_aktif === 'Y' ? 'text-primary' : 'text-muted'"
                                        >
                                            {{ section.flag_aktif === 'Y' ? 'Aktif' : 'Non-Aktif' }}
                                        </label>
                                    </div>
                                    <span class="badge bg-white text-dark border px-2 py-1.5 shadow-sm fw-semibold" style="font-size: 0.75rem">
                                        {{ section.items.length }} Item
                                    </span>
                                    <button
                                        class="stitch-btn outline-danger text-xs px-2 py-1.5"
                                        @click.stop="removeSection(sIdx)"
                                        title="Hapus Section"
                                        type="button"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <button
                                        class="stitch-btn secondary text-xs px-2 py-1.5"
                                        @click.stop="section._collapsed = !section._collapsed"
                                        title="Lipat/Buka"
                                        type="button"
                                    >
                                        <i
                                            class="bi"
                                            :class="section._collapsed ? 'bi-chevron-down' : 'bi-chevron-up'"
                                        ></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Section Body (Items) -->
                            <transition name="slide-down">
                                <div v-show="!section._collapsed" class="section-body p-3 p-md-4">
                                    <!-- Items List -->
                                    <div
                                        v-for="(item, iIdx) in section.items"
                                        :key="iIdx"
                                        class="item-card mb-3 p-3 rounded-3 bg-white border shadow-sm"
                                        :class="{ 'opacity-75 bg-light': item.flag_aktif === 'T' }"
                                    >
                                        <div class="row g-3 align-items-center">
                                            <!-- Aktivitas -->
                                            <div class="col-12 col-md-3">
                                                <label class="form-label small fw-bold text-secondary mb-1 d-flex align-items-center gap-1.5">
                                                    <span class="item-number-chip">{{ iIdx + 1 }}</span>
                                                    Aktivitas
                                                </label>
                                                <el-select
                                                    v-model="item.id_master_aktivitas"
                                                    placeholder="Pilih Aktivitas"
                                                    class="w-100"
                                                    size="default"
                                                    @change="onAktivitasChange(item)"
                                                >
                                                    <el-option
                                                        v-for="a in masterData.aktivitas"
                                                        :key="a.id"
                                                        :label="a.nama"
                                                        :value="a.id"
                                                    >
                                                        <span style="float: left">{{ a.nama }}</span>
                                                        <span style="float: right; color: #94a3b8; font-size: 0.75rem">{{ a.kode }}</span>
                                                    </el-option>
                                                </el-select>
                                            </div>

                                            <!-- Konten -->
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small fw-bold text-secondary mb-1">Konten</label>
                                                <div class="input-group input-group-sm">
                                                    <input
                                                        type="text"
                                                        class="form-control text-sm"
                                                        readonly
                                                        :value="getKontenTitle(item.id_master_konten)"
                                                        placeholder="Pilih Konten..."
                                                        :disabled="!isModul(item)"
                                                    />
                                                    <button
                                                        class="btn btn-outline-primary"
                                                        type="button"
                                                        @click="openKontenPicker(sIdx, iIdx)"
                                                        :disabled="!isModul(item)"
                                                        title="Browse Konten"
                                                    >
                                                        <i class="bi bi-folder2-open"></i>
                                                    </button>
                                                    <button
                                                        v-if="item.id_master_konten && isModul(item)"
                                                        class="btn btn-outline-danger"
                                                        type="button"
                                                        @click="item.id_master_konten = null"
                                                        title="Clear Konten"
                                                    >
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                                <div class="mt-1" v-if="item.id_master_konten && isModul(item)">
                                                    <button
                                                        class="stitch-btn outline-primary text-xs d-inline-flex align-items-center gap-1.5 py-0.5 px-2"
                                                        @click="openPreview(item.id_master_konten)"
                                                        title="Preview Konten"
                                                        type="button"
                                                    >
                                                        <i class="bi bi-eye-fill"></i> Preview Konten
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Keterangan / Label -->
                                            <div class="col-12 col-md-3">
                                                <label class="form-label small fw-bold text-secondary mb-1">Keterangan / Label</label>
                                                <input
                                                    v-model="item.keterangan_level"
                                                    type="text"
                                                    class="form-control form-control-sm rounded-3"
                                                    placeholder="Cth: Video Pengantar"
                                                />
                                            </div>

                                            <!-- Switch, Download, Delete -->
                                            <div class="col-12 col-md-2 d-flex align-items-center justify-content-between gap-2 pt-md-3">
                                                <div class="d-flex flex-column gap-1">
                                                    <div class="form-check form-switch mb-0">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :checked="item.flag_aktif === 'Y'"
                                                            @change="item.flag_aktif = $event.target.checked ? 'Y' : 'T'"
                                                            :id="'item-status-' + sIdx + '-' + iIdx"
                                                        />
                                                        <label
                                                            class="form-check-label small fw-medium"
                                                            :for="'item-status-' + sIdx + '-' + iIdx"
                                                            :class="item.flag_aktif === 'Y' ? 'text-primary' : 'text-muted'"
                                                        >
                                                            {{ item.flag_aktif === 'Y' ? 'Aktif' : 'Off' }}
                                                        </label>
                                                    </div>
                                                    <div v-if="canShowDownloadOption(item)" class="form-check mb-0">
                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input"
                                                            :id="'dl-' + sIdx + '-' + iIdx"
                                                            :checked="item.flag_download === 'Y'"
                                                            @change="item.flag_download = $event.target.checked ? 'Y' : 'T'"
                                                        />
                                                        <label
                                                            class="form-check-label small text-secondary"
                                                            :for="'dl-' + sIdx + '-' + iIdx"
                                                        >
                                                            Unduh
                                                        </label>
                                                    </div>
                                                </div>
                                                <button
                                                    class="stitch-btn outline-danger text-xs px-2 py-1.5 flex-shrink-0"
                                                    @click="removeItem(sIdx, iIdx)"
                                                    title="Hapus Item"
                                                    type="button"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Add Item Button -->
                                    <div class="mt-3">
                                        <button
                                            class="stitch-btn outline-primary text-xs fw-bold d-inline-flex align-items-center gap-1.5"
                                            @click="addItem(sIdx)"
                                            type="button"
                                        >
                                            <i class="bi bi-plus-circle"></i> Tambah Item ke Section
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between gap-3 mb-5 border-top pt-4">
                    <button class="stitch-btn secondary text-sm fw-bold px-4" @click="goBack" type="button">
                        <i class="bi bi-arrow-left me-2"></i> Batal
                    </button>
                    <button
                        class="stitch-btn primary text-sm fw-bold px-5 shadow-sm d-flex align-items-center gap-2"
                        :disabled="saving"
                        @click="handleSave"
                        type="button"
                    >
                        <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-floppy"></i>
                        {{ isEdit ? 'Perbarui Training' : 'Simpan Training' }}
                    </button>
                </div>
            </template>
        </div>

        <!-- Preview Modal Overlay -->
        <transition name="preview-fade">
            <div v-if="previewVisible" class="preview-overlay" @click.self="closePreview">
                <div class="preview-container rounded-4 shadow-lg">
                    <!-- Header -->
                    <div class="preview-header d-flex justify-content-between align-items-center px-4 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <i
                                class="bi fs-5"
                                :class="
                                    previewData.type === 'video'
                                        ? 'bi-play-circle text-danger'
                                        : 'bi-file-earmark-pdf text-warning'
                                "
                            ></i>
                            <h6 class="fw-bold mb-0 text-dark">{{ previewData.judul || 'Preview Konten' }}</h6>
                            <span
                                v-if="previewData.type"
                                class="badge rounded-pill px-2 py-1"
                                :class="
                                    previewData.type === 'video'
                                        ? 'bg-danger-subtle text-danger'
                                        : 'bg-warning-subtle text-warning'
                                "
                                style="font-size: 0.7rem"
                            >
                                {{ previewData.type === 'video' ? 'Video' : 'Dokumen' }}
                            </span>
                        </div>
                        <button
                            class="stitch-action-btn rounded-circle d-flex align-items-center justify-content-center border"
                            style="width: 36px; height: 36px; padding: 0; background: var(--kl-surface)"
                            @click="closePreview"
                            type="button"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="preview-body">
                        <div
                            v-if="previewLoading"
                            class="d-flex flex-column align-items-center justify-content-center h-100"
                        >
                            <div class="spinner-border text-primary" style="width: 2.5rem; height: 2.5rem"></div>
                            <p class="mt-3 text-muted fw-medium">Memuat preview...</p>
                        </div>

                        <div
                            v-else-if="!previewData.url"
                            class="d-flex flex-column align-items-center justify-content-center h-100 text-muted"
                        >
                            <i class="bi bi-exclamation-circle fs-1 mb-2"></i>
                            <p class="fw-medium">Preview tidak tersedia untuk konten ini</p>
                        </div>

                        <iframe
                            v-else
                            :src="previewData.url"
                            class="preview-iframe"
                            frameborder="0"
                            allow="autoplay; encrypted-media; fullscreen"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Picker Modal -->
        <KontenPickerModal v-model:visible="showKontenPicker" @selected="onKontenSelected" />
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import { ElSelect, ElOption, ElMessage } from 'element-plus';
import axios from 'axios';
import KontenPickerModal from './components/KontenPickerModal.vue';

export default {
    name: 'AdminTrainingBuilderPage',
    components: { HeaderKpi, ElSelect, ElOption, KontenPickerModal },
    props: {
        trainingId: { type: [Number, String], default: null },
    },
    data() {
        return {
            now: new Date(),
            pageLoading: true,
            saving: false,
            showInfoSection: true,
            // Preview
            previewVisible: false,
            previewLoading: false,
            previewData: { type: null, url: null, judul: '' },
            thumbnailPreview: null,
            thumbnailFile: null,
            thumbnailError: '',
            showThumbnailModal: false,
            showKontenPicker: false,
            activePickerIndices: { sIdx: null, iIdx: null },
            durasiDraft: {
                judul: '',
                menit: '',
            },
            masterData: {
                kategori: [],
                tipe: [],
                aktivitas: [],
                konten: [],
            },
            form: {
                judul_training: '',
                deskripsi: '',
                id_training_kategori: null,
                id_training_tipe: null,
                thumbnail: null,
                flag_aktif: 'Y',
                outline_session: [],
                metode: [],
                durasi: [],
                total_durasi: null,
                sasaran_peserta: [],
                standar_kelulusan: '',
                sections: [],
            },
        };
    },
    computed: {
        isEdit() {
            return !!this.trainingId;
        },
        dayNight() {
            const h = this.now.getHours();
            return h >= 6 && h < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(
                this.now,
            );
        },
        outlineSessionOptions() {
            return this.form.outline_session || [];
        },
        metodeOptions() {
            return this.form.metode || [];
        },
        sasaranPesertaOptions() {
            return this.form.sasaran_peserta || [];
        },
        computedTotalDurasi() {
            const calculated = this.calculateTotalDurasiFromList(this.form.durasi);
            if (calculated !== null) {
                return Math.round(calculated);
            }

            return this.form.total_durasi === '' || this.form.total_durasi === null
                ? null
                : Math.round(Number(this.form.total_durasi));
        },
    },
    async mounted() {
        await this.fetchMasterData();
        if (this.isEdit) {
            await this.fetchTraining();
        }
        this.pageLoading = false;
    },
    methods: {
        async fetchMasterData() {
            try {
                const { data } = await axios.get('/lms/admin/training/master-data');
                if (data.success) {
                    const result = data.result || data;
                    this.masterData = {
                        kategori: result.kategori || [],
                        tipe: result.tipe || [],
                        aktivitas: result.aktivitas || [],
                        konten: result.konten || [],
                    };
                }
            } catch (err) {
                console.error('Failed to fetch master data:', err);
            }
        },

        async fetchTraining() {
            try {
                const { data } = await axios.get(`/lms/admin/training/show/${this.trainingId}`);
                if (data.success) {
                    const result = data.result || data;
                    const m = result.materi;
                    this.form.judul_training = m.Judul_Training;
                    this.form.deskripsi = m.Deskripsi;
                    this.form.id_training_kategori = m.Id_Training_Kategori;
                    this.form.id_training_tipe = m.Id_Training_Tipe;
                    this.form.thumbnail = m.Thumbnail_Training;
                    this.thumbnailPreview = m.thumbnail_url || m.Thumbnail_Training || null;
                    this.form.flag_aktif = m.Flag_Aktif || 'Y';
                    this.form.outline_session = this.parseMetaListField(m.Outline_Session);
                    this.form.metode = this.parseMetaListField(m.Metode);
                    this.form.durasi = this.parseMetaListField(m.Durasi);
                    this.form.sasaran_peserta = this.parseMetaListField(m.Sasaran_Peserta);
                    this.form.total_durasi = m.Total_Durasi ?? null;
                    this.form.standar_kelulusan =
                        m.Standar_Kelulusan === null || m.Standar_Kelulusan === undefined
                            ? ''
                            : String(m.Standar_Kelulusan);

                    this.form.sections = (result.sections || []).map((sec) => ({
                        id: sec.id,
                        nama_sub: sec.nama,
                        urutan: sec.urutan,
                        flag_aktif: sec.flag_aktif || 'Y',
                        _collapsed: false,
                        items: (sec.items || []).map((it) => ({
                            id: it.Id_Materi_Det,
                            id_master_aktivitas: it.Id_Master_Aktivitas,
                            id_master_konten: it.Id_Master_Konten,
                            id_master_rumus: it.Id_Master_Rumus,
                            keterangan_level: it.Keterangan_Level,
                            urutan_materi: it.Urutan_Materi,
                            durasi: it.Durasi,
                            flag_download: it.Flag_Download || 'T',
                            flag_aktif: it.Flag_Aktif || 'Y',
                        })),
                    }));
                }
            } catch (err) {
                console.error('Failed to fetch training for edit:', err);
                ElMessage.error('Gagal memuat data training');
            }
        },
        parseMetaListField(value) {
            if (Array.isArray(value)) {
                return value.filter(Boolean);
            }

            if (typeof value !== 'string') {
                return [];
            }

            const trimmed = value.trim();
            if (!trimmed) {
                return [];
            }

            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    return parsed
                        .filter(Boolean)
                        .map((entry) => String(entry).trim())
                        .filter(Boolean);
                }
            } catch (error) {
                // Fallback to delimiter parsing
            }

            return trimmed
                .split(/\r?\n|;/)
                .map((entry) => entry.trim())
                .filter(Boolean);
        },
        sanitizeListField(list) {
            if (!Array.isArray(list)) {
                return [];
            }

            return [...new Set(list.map((entry) => String(entry || '').trim()).filter(Boolean))];
        },
        sanitizeDurasiMenitInput(event) {
            const raw = String(event?.target?.value ?? this.durasiDraft.menit ?? '');
            const normalized = raw
                .replace(/,/g, '.')
                .replace(/[^0-9.]/g, '')
                .replace(/(\..*)\./g, '$1');

            this.durasiDraft.menit = normalized;
        },
        sanitizeStandarKelulusanInput(event) {
            const raw = String(event?.target?.value ?? this.form.standar_kelulusan ?? '');
            const normalized = raw
                .replace(/,/g, '.')
                .replace(/[^0-9.]/g, '')
                .replace(/(\..*)\./g, '$1');

            this.form.standar_kelulusan = normalized;
        },
        addDurasiTag() {
            const judul = String(this.durasiDraft.judul || '').trim();
            const menitRaw = String(this.durasiDraft.menit || '').trim();
            const menit = Number(menitRaw);

            if (!judul) {
                ElMessage.warning('Judul durasi wajib diisi');
                return;
            }

            if (!/^\d+(\.\d+)?$/.test(menitRaw) || !Number.isFinite(menit) || menit <= 0) {
                ElMessage.warning('Menit durasi hanya boleh angka dan titik desimal');
                return;
            }

            const menitLabel = menitRaw.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
            const entry = `${judul}: ${menitLabel} menit`;
            this.form.durasi = this.sanitizeListField([...(this.form.durasi || []), entry]);
            this.durasiDraft = { judul: '', menit: '' };
        },
        removeTagItem(fieldName, index) {
            if (!Array.isArray(this.form[fieldName])) {
                return;
            }

            this.form[fieldName].splice(index, 1);
            this.form[fieldName] = this.sanitizeListField(this.form[fieldName]);
        },
        isDurasiEntryValid(entry) {
            const text = String(entry || '').trim();
            if (!text) {
                return false;
            }

            // Format required: <judul>: <angka/desimal> menit
            return /^.+:\s*\d+(\.\d+)?\s*menit$/i.test(text);
        },
        extractMinutesFromDurasiEntry(entry) {
            const text = String(entry || '').trim();
            if (!text) {
                return null;
            }

            const minuteMatches = [...text.matchAll(/(\d+(?:\.\d+)?)\s*menit/gi)];
            if (minuteMatches.length > 0) {
                const last = minuteMatches[minuteMatches.length - 1];
                return Number(last[1]);
            }

            if (/^\d+(\.\d+)?$/.test(text)) {
                return Number(text);
            }

            return null;
        },
        calculateTotalDurasiFromList(list) {
            const values = this.sanitizeListField(list);
            if (!values.length) {
                return null;
            }

            let total = 0;
            let hasDuration = false;
            values.forEach((entry) => {
                const minutes = this.extractMinutesFromDurasiEntry(entry);
                if (minutes !== null && Number.isFinite(minutes)) {
                    total += minutes;
                    hasDuration = true;
                }
            });

            return hasDuration ? total : null;
        },

        // --- Section / Item Management ---
        addSection() {
            this.form.sections.push({
                nama_sub: '',
                urutan: this.form.sections.length + 1,
                flag_aktif: 'Y',
                _collapsed: false,
                items: [],
            });
        },
        removeSection(idx) {
            if (!confirm('Hapus section ini beserta seluruh item-nya?')) return;
            this.form.sections.splice(idx, 1);
            this.reorderSections();
        },
        addItem(sIdx) {
            this.form.sections[sIdx].items.push({
                id_master_aktivitas: null,
                id_master_konten: null,
                id_master_rumus: null,
                keterangan_level: '',
                urutan_materi: this.form.sections[sIdx].items.length + 1,
                durasi: null,
                flag_download: 'T',
                flag_aktif: 'Y',
            });
        },
        removeItem(sIdx, iIdx) {
            this.form.sections[sIdx].items.splice(iIdx, 1);
            // Re-order remaining items
            this.form.sections[sIdx].items.forEach((it, i) => {
                it.urutan_materi = i + 1;
            });
        },
        reorderSections() {
            this.form.sections.forEach((sec, i) => {
                sec.urutan = i + 1;
            });
        },
        isModul(item) {
            if (!item.id_master_aktivitas) return false;
            const akt = this.masterData.aktivitas.find((a) => a.id === item.id_master_aktivitas);
            return akt && akt.nama.toLowerCase().includes('modul');
        },
        isNonVideoKonten(item) {
            if (!item?.id_master_konten) return false;

            const konten = this.masterData.konten.find((k) => k.id === item.id_master_konten);
            if (!konten) return false;

            return String(konten.tipe || '').toLowerCase() !== 'video';
        },
        canShowDownloadOption(item) {
            return this.isModul(item) && this.isNonVideoKonten(item);
        },
        getKontenTitle(id_master_konten) {
            if (!id_master_konten) return '';
            const konten = this.masterData.konten.find((k) => k.id === id_master_konten);
            return konten ? konten.judul : 'Konten Baru Dipilih';
        },
        openKontenPicker(sIdx, iIdx) {
            this.activePickerIndices = { sIdx, iIdx };
            this.showKontenPicker = true;
        },
        onKontenSelected(selectedFile) {
            const { sIdx, iIdx } = this.activePickerIndices;
            if (sIdx !== null && iIdx !== null) {
                const item = this.form.sections[sIdx].items[iIdx];
                item.id_master_konten = selectedFile.id;

                // If it's a new file not in masterData, quickly append it for display
                const existing = this.masterData.konten.find((k) => k.id === selectedFile.id);
                if (!existing) {
                    this.masterData.konten.push({
                        id: selectedFile.id,
                        judul: selectedFile.judul,
                        tipe: selectedFile.tipe,
                    });
                }

                if (!this.canShowDownloadOption(item)) {
                    item.flag_download = 'T';
                }
            }
        },
        onAktivitasChange(item) {
            // Auto-fill keterangan_level from aktivitas name
            const akt = this.masterData.aktivitas.find((a) => a.id === item.id_master_aktivitas);
            if (akt && !item.keterangan_level) {
                item.keterangan_level = akt.nama;
            }
            if (!this.isModul(item)) {
                item.id_master_konten = null;
            }

            if (!this.canShowDownloadOption(item)) {
                item.flag_download = 'T';
            }
        },

        onDropThumbnail(event) {
            const files = event.dataTransfer?.files;
            if (files && files[0]) {
                this.handleThumbnailFile(files[0]);
            }
        },
        triggerThumbnailInput() {
            this.$refs.thumbnailInput?.click();
        },
        onThumbnailChange(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.handleThumbnailFile(file);
            }
        },
        handleThumbnailFile(file) {
            this.thumbnailError = '';

            if (!file.type.startsWith('image/')) {
                this.thumbnailError = 'File harus berupa gambar.';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                this.thumbnailError = 'Ukuran gambar maksimal 2MB.';
                return;
            }

            this.thumbnailFile = file;
            this.form.thumbnail = file;

            const reader = new FileReader();
            reader.onload = (loadEvent) => {
                this.thumbnailPreview = loadEvent.target?.result || null;
            };
            reader.readAsDataURL(file);
        },
        removeThumbnail() {
            this.thumbnailFile = null;
            this.thumbnailPreview = null;
            this.form.thumbnail = null;
            this.thumbnailError = '';
            if (this.$refs.thumbnailInput) {
                this.$refs.thumbnailInput.value = '';
            }
        },
        openThumbnailModal() {
            if (this.thumbnailPreview) {
                this.showThumbnailModal = true;
            }
        },
        closeThumbnailModal() {
            this.showThumbnailModal = false;
        },

        // --- Preview ---
        async openPreview(kontenId) {
            this.previewVisible = true;
            this.previewLoading = true;
            this.previewData = { type: null, url: null, judul: '' };
            try {
                const res = await axios.get(`/lms/admin/training/preview-konten/${kontenId}`);
                const payload = res.data?.result || res.data;
                const preview = payload?.preview || res.data?.preview;
                const konten = payload?.konten || res.data?.konten;

                if (preview && preview.url) {
                    this.previewData = {
                        type: preview.type,
                        url: preview.url,
                        judul: konten?.judul || 'Konten',
                    };
                } else {
                    this.previewData = {
                        type: null,
                        url: null,
                        judul: konten?.judul || 'Konten',
                    };
                }
            } catch (err) {
                console.error('Preview error:', err);
                this.previewData = { type: null, url: null, judul: '' };
            } finally {
                this.previewLoading = false;
            }
        },
        closePreview() {
            this.previewVisible = false;
            this.previewData = { type: null, url: null, judul: '' };
        },

        // --- Save ---
        async handleSave() {
            // Validation
            if (!this.form.judul_training.trim()) {
                ElMessage.warning('Judul Training wajib diisi');
                return;
            }
            if (this.form.sections.length === 0) {
                ElMessage.warning('Tambahkan minimal 1 section');
                return;
            }
            for (const sec of this.form.sections) {
                if (!sec.nama_sub.trim()) {
                    ElMessage.warning('Semua section harus memiliki nama');
                    return;
                }
            }
            const emptySectionIndex = this.form.sections.findIndex((sec) => !sec.items || sec.items.length === 0);
            if (emptySectionIndex !== -1) {
                const section = this.form.sections[emptySectionIndex];
                const sectionName = section.nama_sub?.trim() || `Section ke-${emptySectionIndex + 1}`;
                section._collapsed = false;
                ElMessage.error(`Section "${sectionName}" harus memiliki minimal 1 item.`);
                return;
            }
            if (this.durasiDraft.judul || this.durasiDraft.menit) {
                ElMessage.warning('Masih ada draft Durasi, klik Tambah dulu sebelum menyimpan');
                return;
            }

            const invalidDurasi = (this.form.durasi || []).find((entry) => !this.isDurasiEntryValid(entry));
            if (invalidDurasi) {
                ElMessage.warning('Format Durasi harus "Judul: angka menit". Contoh: In Class: 120 menit');
                return;
            }
            const standarRaw = String(this.form.standar_kelulusan ?? '').trim();
            if (standarRaw && !/^\d+(\.\d+)?$/.test(standarRaw)) {
                ElMessage.warning('Standar Kelulusan hanya boleh angka dan titik desimal');
                return;
            }

            // Pastikan thumbnail adalah string (base64) atau kosong
            if (this.thumbnailFile && this.thumbnailPreview) {
                this.form.thumbnail = this.thumbnailPreview; // base64 string
            } else if (!this.form.thumbnail || typeof this.form.thumbnail !== 'string') {
                this.form.thumbnail = '';
            }

            this.saving = true;
            try {
                const payload = {
                    judul_training: this.form.judul_training,
                    deskripsi: this.form.deskripsi,
                    id_training_kategori: this.form.id_training_kategori,
                    id_training_tipe: this.form.id_training_tipe,
                    thumbnail: this.form.thumbnail,
                    flag_aktif: this.form.flag_aktif,
                    outline_session: this.sanitizeListField(this.form.outline_session),
                    metode: this.sanitizeListField(this.form.metode),
                    durasi: this.sanitizeListField(this.form.durasi),
                    sasaran_peserta: this.sanitizeListField(this.form.sasaran_peserta),
                    total_durasi: this.computedTotalDurasi,
                    standar_kelulusan: standarRaw === '' ? null : Math.round(Number(standarRaw)),
                    sections: this.form.sections.map((sec) => ({
                        id: sec.id || null,
                        nama_sub: sec.nama_sub,
                        urutan: sec.urutan,
                        flag_aktif: sec.flag_aktif,
                        items: sec.items.map((it) => ({
                            id: it.id || null,
                            id_master_aktivitas: it.id_master_aktivitas,
                            id_master_konten: it.id_master_konten,
                            id_master_rumus: it.id_master_rumus,
                            keterangan_level: it.keterangan_level,
                            urutan_materi: it.urutan_materi,
                            durasi: it.durasi,
                            flag_download: it.flag_download,
                            flag_aktif: it.flag_aktif,
                        })),
                    })),
                };

                let response;
                if (this.isEdit) {
                    response = await axios.put(`/lms/admin/training/update/${this.trainingId}`, payload);
                } else {
                    response = await axios.post('/lms/admin/training/store', payload);
                }

                if (response.data.success) {
                    ElMessage.success(response.data.message);
                    this.$inertia.visit('/lms/admin/training');
                } else {
                    ElMessage.error(response.data.message || 'Gagal menyimpan');
                }
            } catch (err) {
                console.error('Save error:', err);
                ElMessage.error(err.response?.data?.message || 'Terjadi kesalahan saat menyimpan');
            } finally {
                this.saving = false;
            }
        },

        goBack() {
            this.$inertia.visit('/lms/admin/training');
        },
    },
};
</script>

<style scoped>
.builder-app-container {
    --primary: var(--hcis-indigo-600);
    --primary-dark: var(--hcis-indigo-700);
    --primary-light: var(--hcis-indigo-100);
    --kl-surface: var(--hcis-white);
    --border: var(--hcis-slate-200);
}

.cursor-pointer {
    cursor: pointer;
}

.form-section-title {
    font-size: 1.15rem;
    letter-spacing: -0.01em;
}

/* Inputs & Form Controls */
.builder-input {
    min-height: 42px;
    border-color: var(--hcis-slate-300);
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.builder-input:focus {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

:deep(.builder-select .el-input__wrapper) {
    min-height: 42px;
    border-radius: 8px;
    box-shadow: 0 0 0 1px var(--hcis-slate-300) inset !important;
}

:deep(.builder-select .el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px var(--hcis-indigo-500) inset !important;
}

/* Section Card */
.section-card {
    border-color: var(--hcis-slate-200) !important;
    background: var(--hcis-white);
    transition:
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.section-card:hover {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
    border-color: var(--hcis-slate-300) !important;
}

.section-header {
    background: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-100);
    cursor: pointer;
    transition: background 0.15s ease;
}

.section-header:hover {
    background: #fafbfc;
}

.section-header.collapsed {
    border-bottom: none;
}

.section-order-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.section-title-input {
    font-weight: 700;
    font-size: 0.95rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    background: var(--hcis-white);
    transition: all 0.2s ease;
}

.section-title-input:focus {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-white);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.section-body {
    background: var(--hcis-slate-50);
    border-top: 1px solid var(--hcis-slate-200);
}

/* Empty Section Box */
.empty-section-box {
    border: 2px dashed var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}

/* Item Card */
.item-card {
    border-color: var(--hcis-slate-200) !important;
    background: var(--hcis-white);
    transition: all 0.2s ease;
}

.item-card:hover {
    border-color: var(--hcis-indigo-200) !important;
    box-shadow: 0 3px 12px rgba(99, 102, 241, 0.08);
}

.item-number-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    font-size: 0.7rem;
    font-weight: 700;
}

/* Slide Down Transition */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.25s ease;
    overflow: hidden;
}

.slide-down-enter-from,
.slide-down-leave-to {
    max-height: 0;
    opacity: 0;
}

.slide-down-enter-to,
.slide-down-leave-from {
    max-height: 2500px;
    opacity: 1;
}

/* Thumbnail Area */
.thumbnail-upload-area {
    min-height: 140px;
    background: var(--hcis-slate-50);
    border: 2px dashed var(--hcis-slate-300);
    border-radius: 12px;
    transition: all 0.2s ease;
}

.thumbnail-upload-area:hover {
    border-color: var(--hcis-indigo-500);
}

.thumbnail-upload-drop {
    cursor: pointer;
    min-height: 130px;
    padding: 20px 0;
    color: var(--hcis-slate-500);
    transition: background 0.2s ease;
}

.thumbnail-upload-drop:hover {
    background: var(--hcis-indigo-50);
}

.thumbnail-preview-box {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    min-height: 130px;
    padding: 0.75rem;
}

.thumbnail-img {
    max-height: 130px;
    max-width: 100%;
    object-fit: cover;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-radius: 10px;
    background: #fff;
}

.thumbnail-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 6px;
    z-index: 2;
}

.thumbnail-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.thumbnail-modal-content {
    background: #fff;
    position: relative;
    max-width: 600px;
    width: 100%;
    padding: 0;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.validation-error {
    color: var(--hcis-red-500);
    font-size: 0.85rem;
}

/* Free Tag Input */
.free-tag-input {
    border: 1px solid var(--hcis-slate-300);
    border-radius: 10px;
    background: var(--hcis-white);
    padding: 0.6rem;
    transition: all 0.2s ease;
}

.free-tag-input:focus-within {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
}

.free-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.38rem;
}

.tag-input-row-durasi {
    display: grid;
    grid-template-columns: 0.6fr 1.4fr auto;
    gap: 0.45rem;
    align-items: center;
}

.free-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-800);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    font-size: 0.78rem;
    font-weight: 600;
}

.free-tag-remove {
    width: 18px;
    height: 18px;
    border: 0;
    border-radius: 999px;
    background: rgba(55, 48, 163, 0.12);
    color: var(--hcis-indigo-800);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    transition: all 0.15s ease;
}

.free-tag-remove:hover {
    background: rgba(55, 48, 163, 0.22);
    transform: scale(1.06);
}

@media (max-width: 768px) {
    .tag-input-row-durasi {
        grid-template-columns: 1fr;
    }
}

/* Preview Modal */
.preview-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.preview-container {
    background: #fff;
    width: 100%;
    max-width: 960px;
    height: 80vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.preview-header {
    border-bottom: 1px solid var(--hcis-slate-200);
    flex-shrink: 0;
}

.preview-body {
    flex: 1;
    overflow: hidden;
    background: var(--hcis-slate-50);
}

.preview-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* Transitions */
.preview-fade-enter-active,
.preview-fade-leave-active {
    transition: all 0.2s ease;
}

.preview-fade-enter-from,
.preview-fade-leave-to {
    opacity: 0;
}
</style>
