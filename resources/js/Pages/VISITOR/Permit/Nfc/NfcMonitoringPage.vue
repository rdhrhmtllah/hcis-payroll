<template>
    <VisitorWorkspace
        title="Monitoring ID Card & NFC"
        subtitle="Pemantauan master kartu NFC dan pairing ID Card karyawan & mitra operasional"
        kicker="Administrator Visitor · ID Card Management"
        icon="credit-card-2-front-fill"
        sentiment="brand"
        :stats="statCards"
        section-title="Daftar Master & Pairing ID Card"
        section-description="Data integrasi kartu fisik ke alur akses gerbang InBio dan pengajuan permit kendaraan."
        section-icon="card-checklist"
        @stat-click="tanganiKlikStat"
    >
        <template #actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <HcisButton
                    variant="primary"
                    size="sm"
                    leading-icon="plus-circle-fill"
                    label="Tambah Pairing Manual"
                    @click="bukaDialogManual"
                />
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="file-earmark-arrow-up-fill"
                    label="Import Excel"
                    @click="bukaDialogUpload"
                />
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    label="Segarkan"
                    :loading="memuat"
                    @click="muatData"
                />
            </div>
        </template>

        <!-- 1. Filter Bar Standar HCIS (HcisFilterBar) -->
        <HcisFilterBar
            :search="kataKunci"
            search-placeholder="Cari nama, NIK, kode, no mitra, NFC hex, InBio..."
            :quick-chips="daftarQuickChips"
            :active-chip-value="tabAktif"
            :results-count="totalItem"
            sentiment="brand"
            variant="glass"
            class="mb-3"
            @update:search="gantiCari"
            @chip-click="gantiChip"
            @reset="resetFilter"
        />

        <!-- 2. Tabel Data Standar HCIS (HcisTable) -->
        <HcisTable
            :columns="KOLOM"
            :rows="daftarItem"
            row-key="id_binding"
            :loading="memuat"
            sentiment="brand"
            variant="card"
            dense
            empty-title="Tidak ada data ID Card"
            :empty-description="kataKunci ? 'Tidak ada kartu yang cocok dengan kueri pencarian.' : 'Belum ada data master atau pairing ID Card.'"
        >
            <!-- Kolom No -->
            <template #cell-no="{ index }">
                <span class="font-tabular text-slate-500">
                    {{ (halamanAktif - 1) * limitPerHalaman + index + 1 }}
                </span>
            </template>

            <!-- Kolom Kategori -->
            <template #cell-kategori="{ row }">
                <HcisBadge
                    :variant="row.kategori === 'KARYAWAN' ? 'brand' : 'purple'"
                    :label="row.kategori"
                    size="xs"
                    appearance="subtle"
                />
            </template>

            <!-- Kolom Identitas Pemegang -->
            <template #cell-identitas="{ row }">
                <div class="fw-bold text-slate-900 mb-1">{{ row.nama || '-' }}</div>
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <HcisBadge
                        v-if="row.kode_karyawan"
                        :label="'Kode: ' + row.kode_karyawan"
                        icon="person-vcard"
                        variant="neutral"
                        size="xs"
                        appearance="subtle"
                    />
                    <HcisBadge
                        v-if="row.nik"
                        :label="'NIK: ' + row.nik"
                        variant="neutral"
                        size="xs"
                        appearance="subtle"
                    />
                    <HcisBadge
                        v-if="row.no_mitra"
                        :label="'Mitra: ' + row.no_mitra"
                        icon="briefcase-fill"
                        variant="purple"
                        size="xs"
                        appearance="subtle"
                    />
                </div>
            </template>

            <!-- Kolom Terpisah 1: NFC HEX -->
            <template #cell-nfc_hex="{ row }">
                <span class="nfc-hex-badge font-mono" :title="row.nfc_hex">
                    <HcisIcon name="upc-scan" size="2xs" />
                    {{ row.nfc_hex || '-' }}
                </span>
            </template>

            <!-- Kolom Terpisah 2: NFC InBio -->
            <template #cell-nfc_inbio="{ row }">
                <span v-if="row.nfc_inbio" class="nfc-inbio-badge font-tabular" title="Nomor kartu desimal InBio">
                    {{ row.nfc_inbio }}
                </span>
                <span v-else class="text-2xs text-muted fst-italic">Tanpa InBio</span>
            </template>

            <!-- Kolom Gabungan: Status Kartu (Status Pairing + Validasi Sistem) -->
            <template #cell-status_gabungan="{ row }">
                <div class="d-flex flex-column align-items-center gap-1">
                    <HcisBadge
                        :variant="lencanaStatus(row.status).variant"
                        :label="lencanaStatus(row.status).label"
                        size="xs"
                        appearance="subtle"
                    />
                    <HcisBadge
                        :variant="row.flag_validasi_sistem === 'Y' ? 'success' : 'warning'"
                        :label="row.flag_validasi_sistem === 'Y' ? 'Sistem Valid' : 'Belum Divalidasi'"
                        size="xs"
                        appearance="subtle"
                        dot
                    />
                </div>
            </template>

            <!-- Kolom Tanggal Pairing -->
            <template #cell-created_at="{ row }">
                <span class="text-slate-500 text-2xs font-tabular">
                    {{ formatTanggal(row.created_at) }}
                </span>
            </template>

            <!-- Kolom Aksi -->
            <template #cell-aksi="{ row }">
                <div class="d-flex align-items-center justify-content-end gap-1">
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        :leading-icon="row.binding_aktif === 'Y' ? 'slash-circle' : 'check-circle'"
                        :title="row.binding_aktif === 'Y' ? 'Nonaktifkan pairing ini' : 'Aktifkan pairing ini'"
                        @click="toggleBinding(row)"
                    />
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        :leading-icon="row.flag_validasi_sistem === 'Y' ? 'shield-slash' : 'shield-check'"
                        :title="row.flag_validasi_sistem === 'Y' ? 'Batalkan validasi master kartu' : 'Validasi master kartu'"
                        @click="toggleValidasi(row)"
                    />
                </div>
            </template>

            <!-- Footer Pagination Standar HCIS (HcisPagination) -->
            <template #footer>
                <HcisPagination
                    v-if="totalItem > limitPerHalaman"
                    :model-value="halamanAktif"
                    :page-size="limitPerHalaman"
                    :total="totalItem"
                    @update:model-value="gantiHalaman"
                />
            </template>
        </HcisTable>

        <!-- ============================================================= -->
        <!-- DIALOG FORM TAMBAH PAIRING MANUAL (KOMPONEN RESMI HCIS)       -->
        <!-- ============================================================= -->
        <HcisDialog
            v-if="dialogManualTerbuka"
            v-model="dialogManualTerbuka"
            title="Tambah Pairing ID Card Manual"
            kicker="Administrator Visitor · Data Entry"
            sentiment="brand"
            icon="plus-circle-fill"
            size="md"
            sticky-footer
            @close="dialogManualTerbuka = false"
        >
            <div class="d-flex flex-column gap-3">
                <!-- 1. Pilihan Kategori via Segmented Button -->
                <div>
                    <span class="text-xs fw-bold text-slate-700 d-block mb-1.5">Kategori Pemegang Kartu</span>
                    <div class="d-flex gap-2">
                        <HcisButton
                            :variant="formManual.tipe === 'karyawan' ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="person-badge-fill"
                            label="Karyawan"
                            class="flex-fill justify-content-center"
                            @click="formManual.tipe = 'karyawan'"
                        />
                        <HcisButton
                            :variant="formManual.tipe === 'phl' ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="people-fill"
                            label="PHL / Mitra"
                            class="flex-fill justify-content-center"
                            @click="formManual.tipe = 'phl'"
                        />
                    </div>
                </div>

                <!-- 2. Data Kartu Fisik -->
                <div class="row g-2">
                    <div class="col-md-6">
                        <HcisField
                            id="manual-nfc-hex"
                            v-model="formManual.nfc_hex"
                            label="NFC HEX (Hardware UID)"
                            placeholder="Contoh: 9BDAC6A6"
                            leading-icon="upc-scan"
                            required
                            hint="UID fisik kartu dalam format hex."
                        />
                    </div>
                    <div class="col-md-6">
                        <HcisField
                            id="manual-nfc-inbio"
                            v-model="formManual.nfc_inbio"
                            label="NFC InBio (Parse)"
                            placeholder="Contoh: 13032091"
                            leading-icon="hash"
                            numeric-only
                            hint="Nomor kartu desimal gerbang InBio."
                        />
                    </div>
                </div>

                <!-- 3. Identitas Pemegang (Autocomplete Resmi HCIS untuk Karyawan & PHL) -->
                <div>
                    <label class="form-label text-xs fw-bold text-slate-700 d-block mb-1">
                        Nama Lengkap Pemegang <span class="text-danger">*</span>
                    </label>

                    <HcisAutocomplete
                        id="manual-nama-autocomplete"
                        v-model="formManual.nama"
                        :fetch-suggestions="ambilSaranKaryawan"
                        value-key="nama"
                        label-key="nama"
                        placeholder="Ketik nama pemegang (muncul saran dari database karyawan)..."
                        prefix-icon="person-fill"
                        size="md"
                        clearable
                        class="w-100"
                        @select="tanganiPilihSaran"
                    >
                        <template #default="{ item }">
                            <div class="d-flex align-items-center justify-content-between w-100 gap-2 py-0.5">
                                <span class="fw-bold text-slate-800 text-truncate">{{ item.nama }}</span>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <HcisBadge :label="'Kode: ' + item.kode_karyawan" variant="brand" size="xs" appearance="subtle" />
                                    <HcisBadge v-if="item.nik" :label="'NIK: ' + item.nik" variant="neutral" size="xs" appearance="subtle" />
                                </div>
                            </div>
                        </template>
                        <template #empty>
                            <div class="text-xs text-muted p-2 text-center">
                                Tidak ada data karyawan yang cocok. Anda tetap bisa mengetik manual.
                            </div>
                        </template>
                    </HcisAutocomplete>
                </div>

                <!-- 4. Kode Identifikasi & NIK / No Mitra -->
                <div v-if="formManual.tipe === 'karyawan'" class="row g-2">
                    <div class="col-md-6">
                        <HcisField
                            id="manual-kode-karyawan"
                            v-model="formManual.kode_karyawan"
                            label="Kode Karyawan"
                            placeholder="Contoh: AALAMSYAH"
                            leading-icon="person-vcard"
                            required
                        />
                    </div>
                    <div class="col-md-6">
                        <HcisField
                            id="manual-nik"
                            v-model="formManual.nik"
                            label="NIK Karyawan"
                            placeholder="Contoh: 2502118"
                            leading-icon="card-text"
                        />
                    </div>
                </div>

                <div v-else class="row g-2">
                    <div class="col-md-6">
                        <HcisField
                            id="manual-no-mitra"
                            v-model="formManual.no_mitra"
                            label="No Mitra / Vendor"
                            placeholder="Contoh: 03-V067"
                            leading-icon="briefcase-fill"
                            required
                        />
                    </div>
                    <div class="col-md-6">
                        <HcisField
                            id="manual-kode-karyawan-phl"
                            v-model="formManual.kode_karyawan"
                            label="Kode Identitas (Opsional)"
                            placeholder="Kode identitas mitra"
                            leading-icon="person-vcard"
                        />
                    </div>
                </div>

                <!-- Banner Informasi Status -->
                <HcisAlert
                    variant="info"
                    appearance="soft"
                    size="sm"
                    icon="shield-check"
                    title="Aktivasi & Validasi Otomatis"
                    description="Kartu yang disimpan manual akan langsung berstatus Aktif dan Tervalidasi sistem untuk pengajuan permit dan akses gerbang."
                />
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2 w-100">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        size="sm"
                        @click="dialogManualTerbuka = false"
                    />
                    <HcisButton
                        label="Simpan Pairing"
                        variant="primary"
                        leading-icon="check-circle-fill"
                        :loading="sedangSimpanManual"
                        :disabled="sedangSimpanManual || !formManual.nfc_hex || !formManual.nama"
                        size="sm"
                        @click="kirimManual"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ============================================================= -->
        <!-- DIALOG UNGGAH & PRATINJAU BERKAS EXCEL (KOMPONEN RESMI HCIS)  -->
        <!-- ============================================================= -->
        <HcisDialog
            v-if="dialogUploadTerbuka"
            v-model="dialogUploadTerbuka"
            :title="dataPratinjau ? 'Pratinjau Data Impor NFC' : 'Import Berkas Pairing NFC Excel'"
            :kicker="dataPratinjau ? 'Hasil Pemeriksaan Berkas' : 'Administrator Visitor · Import Utility'"
            sentiment="brand"
            :icon="dataPratinjau ? 'table' : 'file-earmark-arrow-up-fill'"
            :size="dataPratinjau ? 'lg' : 'md'"
            sticky-footer
            @close="dialogUploadTerbuka = false"
        >
            <!-- TAHAP 1: PILIH BERKAS & UPLOAD UNTUK PRATINJAU -->
            <div v-if="!dataPratinjau" class="d-flex flex-column gap-3">
                <!-- 1. Pilihan Kategori via Segmented Button -->
                <div>
                    <span class="text-xs fw-bold text-slate-700 d-block mb-1.5">Kategori Data Impor</span>
                    <div class="d-flex gap-2">
                        <HcisButton
                            :variant="formUpload.tipe === 'karyawan' ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="person-badge-fill"
                            label="Karyawan"
                            class="flex-fill justify-content-center"
                            @click="formUpload.tipe = 'karyawan'"
                        />
                        <HcisButton
                            :variant="formUpload.tipe === 'phl' ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="people-fill"
                            label="PHL / Mitra"
                            class="flex-fill justify-content-center"
                            @click="formUpload.tipe = 'phl'"
                        />
                    </div>
                    <div class="text-2xs text-muted mt-1.5">
                        {{ formUpload.tipe === 'karyawan' ? 'Format lembar mencakup data karyawan dengan NIK dan Kode Karyawan.' : 'Format lembar mencakup data PHL atau vendor dengan No Mitra.' }}
                    </div>
                </div>

                <!-- 2. Dropzone Berkas Komponen Standar -->
                <div
                    class="nfc-upload-dropzone"
                    :class="{ 'is-selected': Boolean(formUpload.berkas), 'is-dragging': isDragging }"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="tanganiDropBerkas"
                    @click="pemicuPilihBerkas"
                >
                    <input
                        ref="berkasInputRef"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="d-none"
                        @change="tanganiPilihBerkas"
                    />

                    <!-- Tampilan Belum Pilih Berkas -->
                    <div v-if="!formUpload.berkas" class="text-center p-3">
                        <div class="nfc-upload-dropzone__icon-wrap mb-2">
                            <HcisIcon name="file-earmark-excel-fill" size="lg" />
                        </div>
                        <div class="text-xs fw-bold text-slate-800 mb-0.5">Pilih Berkas Excel Pairing NFC</div>
                        <div class="text-2xs text-muted mb-2">Tarik &amp; lepas berkas ke sini, atau klik untuk memilih</div>
                        <div class="d-flex align-items-center justify-content-center gap-1.5 flex-wrap">
                            <HcisBadge label=".xlsx, .xls, .csv" variant="neutral" size="xs" appearance="subtle" />
                            <HcisBadge label="Maks. 10 MB" variant="neutral" size="xs" appearance="subtle" />
                        </div>
                    </div>

                    <!-- Tampilan Berkas Terpilih -->
                    <div v-else class="d-flex align-items-center justify-content-between p-2.5 bg-white rounded-2 w-100 border">
                        <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                            <HcisIcon name="file-earmark-check-fill" size="md" class="text-emerald-600 flex-shrink-0" />
                            <div class="overflow-hidden">
                                <div class="text-xs fw-bold text-slate-900 text-truncate">{{ formUpload.berkas.name }}</div>
                                <div class="text-2xs text-slate-500 font-tabular">{{ formatUkuranBerkas(formUpload.berkas.size) }}</div>
                            </div>
                        </div>
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="x-lg"
                            title="Hapus berkas terpilih"
                            @click.stop="hapusBerkasTerpilih"
                        />
                    </div>
                </div>

                <!-- 3. Alert Format Header -->
                <HcisAlert
                    variant="info"
                    appearance="soft"
                    size="sm"
                    icon="info-circle"
                    title="Petunjuk Format Kolom"
                    description="Pastikan lembar memiliki kolom Kode Karyawan/Nama, NFC HEX, dan NFC PARSE. Berkas akan diperiksa terlebih dahulu dan pratinjaunya ditampilkan sebelum disimpan ke basis data."
                />
            </div>

            <!-- TAHAP 2: PRATINJAU DATA SIAP IMPOR -->
            <div v-else class="d-flex flex-column gap-3">
                <!-- 1. Ringkasan Statistik Pratinjau -->
                <div class="row g-2">
                    <div class="col">
                        <div class="p-2.5 rounded-2 bg-light border text-center">
                            <div class="text-2xs text-muted">Total Baris</div>
                            <div class="fw-bold fs-6 font-tabular text-slate-800">{{ dataPratinjau.total_baris }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-2 bg-emerald-50 border border-emerald-200 text-center">
                            <div class="text-2xs text-emerald-700">Data Baru / Perubahan</div>
                            <div class="fw-bold fs-6 font-tabular text-emerald-800">{{ dataPratinjau.jumlah_siap_impor }}</div>
                        </div>
                    </div>
                    <div v-if="dataPratinjau.jumlah_sudah_terdaftar > 0" class="col">
                        <div class="p-2.5 rounded-2 bg-indigo-50 border border-indigo-200 text-center">
                            <div class="text-2xs text-indigo-700">Sudah Terdaftar</div>
                            <div class="fw-bold fs-6 font-tabular text-indigo-800">{{ dataPratinjau.jumlah_sudah_terdaftar }}</div>
                        </div>
                    </div>
                    <div v-if="dataPratinjau.jumlah_konflik_aktif > 0" class="col">
                        <div class="p-2.5 rounded-2 bg-red-50 border border-red-200 text-center">
                            <div class="text-2xs text-red-700">Konflik Aktif</div>
                            <div class="fw-bold fs-6 font-tabular text-red-800">{{ dataPratinjau.jumlah_konflik_aktif }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-2 bg-amber-50 border border-amber-200 text-center">
                            <div class="text-2xs text-amber-700">Tanpa NFC</div>
                            <div class="fw-bold fs-6 font-tabular text-amber-800">{{ dataPratinjau.jumlah_tanpa_nfc }}</div>
                        </div>
                    </div>
                </div>

                <!-- Alert Semua Data Sudah Terdaftar -->
                <HcisAlert
                    v-if="dataPratinjau.jumlah_siap_impor === 0 && dataPratinjau.jumlah_sudah_terdaftar > 0"
                    variant="info"
                    appearance="soft"
                    size="sm"
                    icon="patch-check-fill"
                    title="Seluruh Data Sudah Terdaftar"
                    :description="`Sebanyak ${dataPratinjau.jumlah_sudah_terdaftar} data kartu pada berkas ini sudah terdaftar identik dan aktif di database. Tidak ada penambahan atau perubahan data baru yang perlu diimpor.`"
                />

                <!-- Alert Kartu Sedang Aktif pada Orang Lain -->
                <HcisAlert
                    v-if="dataPratinjau.jumlah_konflik_aktif > 0"
                    variant="danger"
                    appearance="soft"
                    size="sm"
                    icon="exclamation-octagon-fill"
                    title="Kartu NFC Sedang Aktif pada Pemegang Lain"
                    :description="`Terdapat ${dataPratinjau.jumlah_konflik_aktif} baris di mana kartu NFC sudah aktif dipasangkan ke pemegang lain. Baris ini akan otomatis dilewati agar tidak terjadi pairing ganda. Untuk memasangkan ke orang baru, nonaktifkan pairing lamanya terlebih dahulu.`"
                />

                <!-- 2. Cuplikan Baris Data yang Akan Diimpor -->
                <div class="border rounded-2 overflow-hidden">
                    <div class="px-2.5 py-1.5 bg-light border-bottom text-2xs fw-bold text-slate-700 d-flex align-items-center justify-content-between">
                        <span v-if="dataPratinjau.jumlah_siap_impor > 0">
                            Cuplikan Data yang Akan Diimpor ({{ dataPratinjau.cuplikan_data?.length || 0 }} dari {{ dataPratinjau.jumlah_siap_impor }})
                        </span>
                        <span v-else>
                            Contoh Data yang Sudah Terdaftar ({{ dataPratinjau.cuplikan_sudah_terdaftar?.length || 0 }} dari {{ dataPratinjau.jumlah_sudah_terdaftar }})
                        </span>
                        <span class="text-muted font-normal">Kategori: {{ formUpload.tipe === 'karyawan' ? 'Karyawan' : 'PHL / Mitra' }}</span>
                    </div>
                    <div class="table-responsive" style="max-height: 260px;">
                        <table class="table table-sm table-hover align-middle mb-0 text-xs">
                            <thead class="table-light text-slate-600">
                                <tr>
                                    <th class="ps-2 py-1.5" style="width: 40px">#</th>
                                    <th class="py-1.5">Identitas Pemegang</th>
                                    <th class="py-1.5" style="width: 140px">NFC HEX</th>
                                    <th class="py-1.5" style="width: 140px">NFC InBio</th>
                                    <th class="pe-2 py-1.5 text-center" style="width: 130px">Status di Sistem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, idx) in (dataPratinjau.jumlah_siap_impor > 0 ? (dataPratinjau.cuplikan_data || []) : (dataPratinjau.cuplikan_sudah_terdaftar || []))" :key="idx">
                                    <td class="ps-2 py-1 text-slate-400 font-tabular">{{ idx + 1 }}</td>
                                    <td class="py-1">
                                        <div class="fw-bold text-slate-900">{{ b.nama || '-' }}</div>
                                        <div class="text-2xs text-slate-500 d-flex align-items-center gap-1">
                                            <span v-if="b.kode_karyawan">Kode: {{ b.kode_karyawan }}</span>
                                            <span v-if="b.nik">· NIK: {{ b.nik }}</span>
                                            <span v-if="b.no_mitra">· Mitra: {{ b.no_mitra }}</span>
                                        </div>
                                    </td>
                                    <td class="py-1">
                                        <span class="nfc-hex-badge font-mono">{{ b.nfc_hex }}</span>
                                    </td>
                                    <td class="py-1 font-tabular">
                                        <span v-if="b.nfc_inbio" class="nfc-inbio-badge">{{ b.nfc_inbio }}</span>
                                        <span v-else class="text-2xs text-muted fst-italic">Tanpa InBio</span>
                                    </td>
                                    <td class="pe-2 py-1 text-center">
                                        <HcisBadge
                                            :variant="dataPratinjau.jumlah_siap_impor > 0 ? 'success' : 'neutral'"
                                            :label="dataPratinjau.jumlah_siap_impor > 0 ? 'Siap Simpan' : 'Sudah Terdaftar'"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Alert Keterangan Baris Dilewati -->
                <HcisAlert
                    v-if="dataPratinjau.jumlah_tanpa_nfc > 0"
                    variant="warning"
                    appearance="soft"
                    size="sm"
                    icon="exclamation-circle-fill"
                    title="Data Tanpa Kartu NFC"
                    :description="`Terdapat ${dataPratinjau.jumlah_tanpa_nfc} baris data pada berkas yang tidak memiliki nilai NFC HEX. Baris-baris ini akan otomatis dilewati secara aman saat disimpan.`"
                />
            </div>

            <template #footer>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <HcisButton
                            v-if="dataPratinjau"
                            label="Pilih Berkas Lain"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-left"
                            @click="batalPratinjau"
                        />
                    </div>
                    <div class="d-flex gap-2">
                        <HcisButton
                            label="Batal"
                            variant="secondary"
                            size="sm"
                            @click="dialogUploadTerbuka = false"
                        />
                        <HcisButton
                            v-if="!dataPratinjau"
                            label="Periksa &amp; Pratinjau Berkas"
                            variant="primary"
                            leading-icon="eye-fill"
                            :loading="sedangPratinjau"
                            :disabled="!formUpload.berkas || sedangPratinjau"
                            size="sm"
                            @click="kirimPratinjau"
                        />
                        <HcisButton
                            v-else
                            label="Konfirmasi &amp; Simpan ke Database"
                            variant="primary"
                            leading-icon="check-circle-fill"
                            :loading="sedangEksekusi"
                            :disabled="sedangEksekusi || dataPratinjau.jumlah_siap_impor === 0"
                            size="sm"
                            @click="konfirmasiSimpanImport"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>
    </VisitorWorkspace>
</template>

<script>
import axios from 'axios';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import {
    HcisAlert,
    HcisAutocomplete,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisTable,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';

const KOLOM = [
    { key: 'no', label: 'No', width: '55px', align: 'center' },
    { key: 'kategori', label: 'Kategori', width: '105px' },
    { key: 'identitas', label: 'Identitas Pemegang', minWidth: '220px' },
    { key: 'nfc_hex', label: 'NFC HEX', width: '130px' },
    { key: 'nfc_inbio', label: 'NFC InBio', width: '130px' },
    { key: 'status_gabungan', label: 'Status Kartu', width: '160px', align: 'center' },
    { key: 'created_at', label: 'Terdaftar Pada', width: '145px' },
    { key: 'aksi', label: 'Aksi', width: '90px', align: 'right' },
];

export default {
    name: 'NfcMonitoringPage',
    components: {
        VisitorWorkspace,
        HcisAlert,
        HcisAutocomplete,
        HcisBadge,
        HcisButton,
        HcisDialog,
        HcisField,
        HcisFilterBar,
        HcisIcon,
        HcisPagination,
        HcisTable,
    },
    props: {
        initialStats: { type: Object, default: () => ({}) },
        initialData: { type: Object, default: () => ({ items: [], total: 0, page: 1, limit: 15 }) },
    },
    data() {
        return {
            KOLOM,
            memuat: false,
            stats: {
                total_master: this.initialStats.total_master || 0,
                total_karyawan: this.initialStats.total_karyawan || 0,
                total_phl: this.initialStats.total_phl || 0,
                total_anomali: this.initialStats.total_anomali || 0,
                total_nonaktif: this.initialStats.total_nonaktif || 0,
            },
            daftarItem: this.initialData.items || [],
            totalItem: this.initialData.total || 0,
            halamanAktif: this.initialData.page || 1,
            limitPerHalaman: this.initialData.limit || 15,
            tabAktif: 'all',
            kataKunci: '',

            // Dialog Upload & Pratinjau
            dialogUploadTerbuka: false,
            sedangPratinjau: false,
            sedangEksekusi: false,
            isDragging: false,
            formUpload: {
                tipe: 'karyawan',
                berkas: null,
            },
            sidikPratinjau: null,
            dataPratinjau: null,
            pesanHasilUpload: null,

            // Dialog Tambah Manual
            dialogManualTerbuka: false,
            sedangSimpanManual: false,
            formManual: {
                tipe: 'karyawan',
                nfc_hex: '',
                nfc_inbio: '',
                nama: '',
                kode_karyawan: '',
                nik: '',
                no_mitra: '',
            },
        };
    },
    computed: {
        statCards() {
            return [
                {
                    key: 'master',
                    label: 'Master Kartu NFC',
                    value: this.stats.total_master,
                    suffix: 'Kartu',
                    icon: 'credit-card-2-front-fill',
                    sentiment: 'brand',
                    caption: 'Total Kartu Fisik Terdaftar',
                },
                {
                    key: 'karyawan',
                    label: 'Pairing Karyawan',
                    value: this.stats.total_karyawan,
                    suffix: 'Orang',
                    icon: 'person-badge-fill',
                    sentiment: 'primary',
                    caption: 'ID Card Karyawan Aktif',
                    clickable: true,
                },
                {
                    key: 'phl',
                    label: 'Pairing PHL / Mitra',
                    value: this.stats.total_phl,
                    suffix: 'Orang',
                    icon: 'people-fill',
                    sentiment: 'purple',
                    caption: 'ID Card Mitra Aktif',
                    clickable: true,
                },
                {
                    key: 'anomali',
                    label: 'Perlu Perhatian',
                    value: this.stats.total_anomali + this.stats.total_nonaktif,
                    suffix: 'Kartu',
                    icon: 'exclamation-triangle-fill',
                    sentiment: this.stats.total_anomali ? 'warning' : 'neutral',
                    caption: `${this.stats.total_anomali} Anomali · ${this.stats.total_nonaktif} Nonaktif`,
                    clickable: true,
                },
            ];
        },
        daftarQuickChips() {
            return [
                { label: 'Semua Kartu', value: 'all' },
                { label: `Karyawan (${this.stats.total_karyawan})`, value: 'karyawan' },
                { label: `PHL / Mitra (${this.stats.total_phl})`, value: 'phl' },
                { label: `Anomali & Nonaktif (${this.stats.total_anomali + this.stats.total_nonaktif})`, value: 'anomali' },
            ];
        },
    },
    methods: {
        tanganiKlikStat(stat) {
            if (stat?.key === 'karyawan') this.gantiTab('karyawan');
            else if (stat?.key === 'phl') this.gantiTab('phl');
            else if (stat?.key === 'anomali') this.gantiTab('anomali');
        },
        gantiTab(kunci) {
            this.tabAktif = kunci;
            this.halamanAktif = 1;
            this.muatData();
        },
        gantiChip(chip) {
            this.gantiTab(chip?.value || 'all');
        },
        gantiCari(cari) {
            this.kataKunci = cari;
            this.halamanAktif = 1;
            this.muatData();
        },
        resetFilter() {
            this.kataKunci = '';
            this.tabAktif = 'all';
            this.halamanAktif = 1;
            this.muatData();
        },
        gantiHalaman(hal) {
            this.halamanAktif = hal;
            this.muatData();
        },
        async muatData() {
            this.memuat = true;
            try {
                const res = await axios.get('/admin/visitor/nfc-data', {
                    params: {
                        tab: this.tabAktif,
                        search: this.kataKunci,
                        page: this.halamanAktif,
                        limit: this.limitPerHalaman,
                    },
                });

                if (res.data?.success) {
                    const listData = res.data.result.list || {};
                    this.daftarItem = listData.items || [];
                    this.totalItem = listData.total || 0;
                    this.stats = res.data.result.stats || this.stats;
                }
            } catch (err) {
                hcisMessage.error('Gagal memuat data monitoring ID Card.');
            } finally {
                this.memuat = false;
            }
        },
        lencanaStatus(status) {
            switch (status) {
                case 'TERDAFTAR':
                    return { variant: 'success', label: 'Aktif' };
                case 'KARTU_DIBLOKIR':
                    return { variant: 'danger', label: 'Diblokir' };
                case 'MENUNGGU_VALIDASI':
                    return { variant: 'warning', label: 'Perlu Validasi' };
                case 'KARTU_GANDA':
                    return { variant: 'danger', label: 'Ganda' };
                case 'PAIRING_NONAKTIF':
                    return { variant: 'neutral', label: 'Nonaktif' };
                default:
                    return { variant: 'neutral', label: status || '-' };
            }
        },
        formatTanggal(tglStr) {
            if (!tglStr) return '-';
            try {
                const d = new Date(tglStr);
                return d.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch {
                return tglStr;
            }
        },
        formatUkuranBerkas(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
        },
        async toggleBinding(item) {
            const aksi = item.binding_aktif === 'Y' ? 'menonaktifkan' : 'mengaktifkan';
            const ok = await hcisConfirm(`Yakin ingin ${aksi} pairing ID Card atas nama ${item.nama}?`);
            if (!ok) return;

            try {
                const res = await axios.post(`/admin/visitor/nfc-toggle-binding/${item.id_binding}`);
                if (res.data?.success) {
                    hcisMessage.success(res.data.message || 'Status pairing berhasil diperbarui.');
                    this.muatData();
                }
            } catch (err) {
                hcisMessage.error('Gagal mengubah status pairing ID Card.');
            }
        },
        async toggleValidasi(item) {
            const aksi = item.flag_validasi_sistem === 'Y' ? 'mencabut validasi sistem' : 'memvalidasi sistem';
            const ok = await hcisConfirm(`Yakin ingin ${aksi} master kartu NFC ${item.nfc_hex}?`);
            if (!ok) return;

            try {
                const res = await axios.post(`/admin/visitor/nfc-toggle-validasi/${item.id_nfc}`);
                if (res.data?.success) {
                    hcisMessage.success(res.data.message || 'Validasi master NFC berhasil diperbarui.');
                    this.muatData();
                }
            } catch (err) {
                hcisMessage.error('Gagal memperbarui validasi master NFC.');
            }
        },
        bukaDialogManual() {
            this.formManual = {
                tipe: 'karyawan',
                nfc_hex: '',
                nfc_inbio: '',
                nama: '',
                kode_karyawan: '',
                nik: '',
                no_mitra: '',
            };
            this.dialogManualTerbuka = true;
        },
        ambilSaranKaryawan(queryString, cb) {
            if (!queryString || String(queryString).trim().length < 2) {
                cb([]);
                return;
            }
            axios.get('/admin/visitor/nfc-suggest-karyawan', {
                params: { q: String(queryString).trim() },
            })
            .then((res) => {
                cb(res.data?.result || []);
            })
            .catch(() => {
                cb([]);
            });
        },
        tanganiPilihSaran(item) {
            if (!item) return;
            this.formManual.nama = item.nama;
            this.formManual.kode_karyawan = item.kode_karyawan;
            if (item.nik) {
                this.formManual.nik = item.nik;
            }
        },
        async kirimManual() {
            this.sedangSimpanManual = true;
            try {
                const res = await axios.post('/admin/visitor/nfc-manual', this.formManual);
                if (res.data?.success) {
                    hcisMessage.success(res.data.message || 'Pairing ID Card berhasil disimpan.');
                    this.dialogManualTerbuka = false;
                    this.muatData();
                }
            } catch (err) {
                const pesan = err.response?.data?.message || 'Gagal menyimpan pairing manual.';
                hcisMessage.error(pesan);
            } finally {
                this.sedangSimpanManual = false;
            }
        },
        bukaDialogUpload() {
            this.pesanHasilUpload = null;
            this.sidikPratinjau = null;
            this.dataPratinjau = null;
            this.formUpload.berkas = null;
            if (this.$refs.berkasInputRef) {
                this.$refs.berkasInputRef.value = '';
            }
            this.dialogUploadTerbuka = true;
        },
        batalPratinjau() {
            this.sidikPratinjau = null;
            this.dataPratinjau = null;
            this.formUpload.berkas = null;
            if (this.$refs.berkasInputRef) {
                this.$refs.berkasInputRef.value = '';
            }
        },
        pemicuPilihBerkas() {
            this.$refs.berkasInputRef?.click();
        },
        tanganiPilihBerkas(e) {
            this.formUpload.berkas = e.target.files?.[0] || null;
            this.pesanHasilUpload = null;
            this.sidikPratinjau = null;
            this.dataPratinjau = null;
        },
        tanganiDropBerkas(e) {
            this.isDragging = false;
            const file = e.dataTransfer?.files?.[0];
            if (file) {
                this.formUpload.berkas = file;
                this.pesanHasilUpload = null;
                this.sidikPratinjau = null;
                this.dataPratinjau = null;
            }
        },
        hapusBerkasTerpilih() {
            this.formUpload.berkas = null;
            if (this.$refs.berkasInputRef) {
                this.$refs.berkasInputRef.value = '';
            }
            this.pesanHasilUpload = null;
            this.sidikPratinjau = null;
            this.dataPratinjau = null;
        },
        async kirimPratinjau() {
            if (!this.formUpload.berkas) return;

            this.sedangPratinjau = true;
            this.pesanHasilUpload = null;
            const fd = new FormData();
            fd.append('file', this.formUpload.berkas);
            fd.append('tipe', this.formUpload.tipe);

            try {
                const res = await axios.post('/admin/visitor/nfc-import-preview', fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (res.data?.success) {
                    this.sidikPratinjau = res.data.result.sidik;
                    this.dataPratinjau = res.data.result.pratinjau;
                }
            } catch (err) {
                const pesan = err.response?.data?.message || 'Gagal memeriksa berkas Excel.';
                hcisMessage.error(pesan);
            } finally {
                this.sedangPratinjau = false;
            }
        },
        async konfirmasiSimpanImport() {
            if (!this.sidikPratinjau || !this.formUpload.berkas) return;

            // Berkas dikirim ulang: server tidak menyimpannya di antara pratinjau dan
            // simpan (Cloud Run multi-instance). Sidik memastikan isinya sama.
            this.sedangEksekusi = true;
            const fd = new FormData();
            fd.append('file', this.formUpload.berkas);
            fd.append('tipe', this.formUpload.tipe);
            fd.append('sidik', this.sidikPratinjau);
            try {
                const res = await axios.post('/admin/visitor/nfc-import-apply', fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (res.data?.success) {
                    const h = res.data.result?.hasil || {};
                    hcisMessage.success(
                        `Tersimpan: ${h.imported_binding || 0} pairing baru, ${h.updated_binding || 0} diaktifkan ulang. `
                        + `Dilewati: ${h.skipped_sudah_terdaftar || 0} sudah terdaftar, ${h.skipped_konflik || 0} konflik.`,
                    );
                    this.dialogUploadTerbuka = false;
                    this.sidikPratinjau = null;
                    this.dataPratinjau = null;
                    this.formUpload.berkas = null;
                    this.muatData();
                }
            } catch (err) {
                const pesan = err.response?.data?.message || 'Gagal menyimpan hasil impor.';
                hcisMessage.error(pesan);
            } finally {
                this.sedangEksekusi = false;
            }
        },
    },
};
</script>

<style scoped>
.nfc-hex-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 0.25rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-text-strong);
    font-weight: 600;
    border: 1px solid var(--hcis-border-subtle);
}

.nfc-inbio-badge {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 0.25rem;
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    font-weight: 600;
    border: 1px solid var(--hcis-brand-200);
}

.nfc-upload-dropzone {
    border: 2px dashed var(--hcis-border-subtle);
    border-radius: 0.5rem;
    background: var(--hcis-slate-50);
    transition: all var(--hcis-duration-fast, 150ms) ease;
    cursor: pointer;
}

.nfc-upload-dropzone:hover,
.nfc-upload-dropzone.is-dragging {
    border-color: var(--hcis-brand-500);
    background: var(--hcis-brand-50);
}

.nfc-upload-dropzone.is-selected {
    border-style: solid;
    border-color: var(--hcis-emerald-300, var(--hcis-border-subtle));
    background: var(--hcis-emerald-50, var(--hcis-slate-50));
}

.nfc-upload-dropzone__icon-wrap {
    color: var(--hcis-brand-600);
}

:deep(.hcis-autocomplete) {
    display: block;
    width: 100%;
    max-width: 100%;
}

.cursor-pointer {
    cursor: pointer;
}
</style>
