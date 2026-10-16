<template>
    <div class="all-monitoring">
        <HcisAlert v-if="galat" variant="danger" :title="galat" closable class="mb-3" @close="galat = ''" />

        <!--
            Saringan memakai HcisFilterBar:
            Pencarian ber-debounce, filter deklaratif, quick-chips status,
            dan tombol switch mode Tampilan (Tabel vs Kartu Grid).
        -->
        <HcisFilterBar
            :search="saring.cari"
            search-placeholder="Cari pemohon, plat nomor, nomor transaksi, divisi, atau jabatan..."
            :filters="saringanTampil"
            :filter-values="{ status: saring.status, jenis_kendaraan: saring.jenis_kendaraan, jenis_permit: saring.jenis_permit }"
            :quick-chips="QUICK_CHIPS"
            :active-chip-value="saring.status"
            :results-count="paginasi.total"
            :collapsible="true"
            :show-view-toggle="true"
            :view-mode="viewMode"
            show-active-tags
            sentiment="brand"
            variant="glass"
            class="mb-3"
            @update:search="gantiCari"
            @update:view-mode="viewMode = $event"
            @filter-change="gantiSaringan"
            @chip-click="gantiChipStatus"
            @reset="reset"
        />

        <!-- Ringkasan Statistik Eksekutif (4 Kolom Desktop, 2x2 Mobile) -->
        <div class="all-monitoring__stats mb-3">
            <HcisStatGroup :cols="stats.dicabut ? 4 : 3" gap="sm" appearance="crystal">
                <HcisStatCard
                    label="Total permit"
                    :value="paginasi.total ?? 0"
                    suffix="Permit"
                    icon="car-front-fill"
                    sentiment="brand"
                    :loading="memuat"
                    interactive
                    caption="Klik untuk reset filter status"
                    @click="gantiChipStatus({ value: '' })"
                />
                <HcisStatCard
                    label="Masih berlaku"
                    :value="stats.permit_aktif ?? 0"
                    suffix="Permit"
                    icon="patch-check-fill"
                    sentiment="success"
                    :loading="memuat"
                    interactive
                    caption="Izin kendaraan aktif"
                    @click="gantiChipStatus({ value: 'aktif' })"
                />
                <HcisStatCard
                    label="Segera kedaluwarsa"
                    :value="stats.hampir_habis ?? 0"
                    suffix="Permit"
                    icon="calendar-event-fill"
                    sentiment="warning"
                    :loading="memuat"
                    interactive
                    caption="Dalam 30 hari ke depan"
                    @click="gantiChipStatus({ value: 'segera' })"
                />
                <HcisStatCard
                    v-if="stats.dicabut"
                    label="Akses Dihapus"
                    :value="stats.dicabut"
                    suffix="Permit"
                    icon="slash-circle-fill"
                    sentiment="danger"
                    :loading="memuat"
                    caption="Akses dibatalkan/dicabut"
                />
            </HcisStatGroup>
        </div>

        <!-- Banner Select All Across All Pages matching Filters -->
        <Transition name="hcis-fade">
            <div
                v-if="bolehRevoke && semuaHalamanIniTerpilih && paginasi.total > baris.length"
                class="all-monitoring__select-all-banner mb-3"
            >
                <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                    <HcisIcon name="info-circle-fill" size="xs" class="text-indigo-600 flex-shrink-0" />
                    <span v-if="!pilihSemuaFilter" class="all-monitoring__banner-text">
                        Semua <strong>{{ barisTerpilihHalamanIni.length }}</strong> permit di halaman ini telah dipilih.
                    </span>
                    <span v-else class="all-monitoring__banner-text">
                        Semua <strong>{{ paginasi.total }}</strong> permit yang sesuai filter aktif telah dipilih.
                        <!-- Angka di atas mencakup permit yang aksesnya sudah
                             dicabut; server melewatinya. Menyebutkannya di muka
                             mencegah pemakainya menyangka ada yang gagal ketika
                             hasilnya lebih kecil dari yang ia pilih. -->
                        <span class="all-monitoring__banner-catatan">
                            Yang sudah dicabut akan dilewati.
                        </span>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <HcisButton
                        v-if="!pilihSemuaFilter"
                        :label="`Pilih semua ${paginasi.total} permit yang sesuai filter`"
                        variant="brand"
                        size="xs"
                        leading-icon="check-all"
                        @click="pilihSemuaSeluruhFilter"
                    />
                    <HcisButton
                        v-else
                        label="Batal pilih seluruh filter"
                        variant="danger"
                        size="xs"
                        leading-icon="x-circle"
                        @click="batalPilihSemuaSeluruhFilter"
                    />
                </div>
            </div>
        </Transition>

        <!-- TAMPILAN 1: Tabel Data Desktop (viewMode === 'table') -->
        <div v-if="viewMode === 'table'" class="all-monitoring__table-wrap">
            <HcisTable
                :columns="KOLOM"
                :rows="baris"
                row-key="No_Transaksi"
                :loading="memuat"
                sentiment="brand"
                remote-sort
                :sort-key="urutKolom"
                :sort-order="saring.arah"
                :empty-title="saring.cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada permit pada saringan ini'"
                :empty-description="saring.cari ? 'Coba ubah kata kunci pencarian atau reset filter aktif.' : 'Belum ada permit kendaraan yang memenuhi kriteria filter.'"
                dense
                interactive
                :selectable="bolehRevoke"
                :row-selectable="bolehDicabut"
                :selected-keys="terpilih"
                @sort="gantiUrutan"
                @row-click="bukaDetail"
                @update:selected-keys="onUpdateSelectedKeys"
                @select-all="onSelectAll"
            >
                <!-- Floating Bulk Actions Slot -->
                <template #bulk-actions="{ clear }">
                    <div class="d-flex align-items-center gap-2">
                        <HcisButton
                            :label="`Cetak Kartu (${idsCetakTerpilih.length})`"
                            variant="secondary"
                            size="xs"
                            leading-icon="printer"
                            title="Cetak kartu dashboard permit terpilih, 2 kartu per lembar A4"
                            :disabled="!idsCetakTerpilih.length"
                            :loading="memuatKartuBatch"
                            @click="cetakKartuBatch"
                        />
                        <HcisButton
                            :label="`Hapus Akses (${jumlahTerpilihEfektif})`"
                            variant="danger"
                            size="xs"
                            leading-icon="shield-slash-fill"
                            @click="bukaBulkRevoke"
                        />
                        <HcisButton
                            label="Batal Pilih"
                            variant="secondary"
                            size="xs"
                            leading-icon="x-circle"
                            @click="batalPilih(clear)"
                        />
                    </div>
                </template>

                <!-- 1. Kolom No Transaksi -->
                <template #cell-No_Transaksi="{ row }">
                    <button
                        type="button"
                        class="all-monitoring__link font-tabular font-mono"
                        title="Lihat detail permit"
                        @click.stop="bukaDetail(row)"
                    >
                        <HcisIcon name="receipt" size="xs" class="me-1 opacity-70" />
                        <span>#{{ row.No_Transaksi }}</span>
                    </button>
                </template>

                <!-- 2. Kolom Pemohon & Jabatan -->
                <template #cell-pemohon="{ row }">
                    <div class="d-flex align-items-center gap-2.5 min-w-0 py-1">
                        <HcisAvatar
                            :name="row.Nama_Pemegang || row.Nama_Karyawan"
                            size="sm"
                            shape="circle"
                            :variant="row.Jenis_Asal_Permit === 'KARYAWAN' ? 'brand' : 'warning'"
                            class="flex-shrink-0"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <!-- PEMEGANG izin, bukan pemohonnya. Izin tamu
                                 diajukan atas nama PIC; menampilkan nama PIC di
                                 sini membuat satpam mencari orang yang memang
                                 tidak akan datang. -->
                            <strong class="text-truncate d-block text-slate-900 text-xs fw-bold">
                                {{ row.Nama_Pemegang || row.Nama_Karyawan || '-' }}
                            </strong>
                            <div class="d-flex align-items-center gap-1 flex-wrap mt-0.5">
                                <span class="pm-tag" :class="'pm-tag--' + asalVarian(row.Jenis_Asal_Permit)">
                                    {{ labelAsal(row.Jenis_Asal_Permit) }}
                                </span>
                                <span
                                    v-if="row.Instansi_Pemegang"
                                    class="text-2xs text-muted text-truncate"
                                    :title="row.Instansi_Pemegang"
                                >
                                    {{ row.Instansi_Pemegang }}
                                </span>
                                <!-- Untuk izin tamu, pemohonnya tetap disebut —
                                     ia PIC yang bertanggung jawab, dan admin
                                     sering perlu menghubunginya. -->
                                <span
                                    v-if="row.Jenis_Asal_Permit === 'TAMU' && row.Nama_Karyawan"
                                    class="text-2xs text-muted text-truncate"
                                    :title="'PIC: ' + row.Nama_Karyawan"
                                >
                                    · PIC {{ row.Nama_Karyawan }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                <span v-if="row.Kode_Karyawan_Pemohon" class="all-monitoring__nik-tag font-tabular">
                                    Kode: {{ row.Kode_Karyawan_Pemohon }}
                                </span>
                                <span v-if="row.Nama_Jabatan && row.Nama_Jabatan !== '-'" class="text-2xs text-slate-500 text-truncate">
                                    {{ row.Nama_Jabatan }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- 3. Kolom Kendaraan -->
                <template #cell-kendaraan="{ row }">
                    <div class="d-flex flex-column gap-1 min-w-0 py-1">
                        <div>
                            <span class="all-monitoring__plat font-tabular">{{ row.No_Plat_Kendaraan || '-' }}</span>
                        </div>
                        <span v-if="row.Nama_Vehicle_Type || row.Nama_Model_Manual" class="text-2xs text-slate-600 text-truncate">
                            {{ row.Nama_Vehicle_Type || '' }}
                            <template v-if="row.Nama_Model_Manual && row.Nama_Model_Manual !== '-'">
                                · {{ row.Nama_Model_Manual }}
                            </template>
                        </span>
                    </div>
                </template>

                <!-- 4. Kolom Unit Kerja / Divisi -->
                <template #cell-unit_kerja="{ row }">
                    <div class="d-flex flex-column min-w-0 py-1">
                        <span class="text-xs fw-semibold text-slate-800 text-truncate" :title="row.Nama_Divisi">
                            {{ row.Nama_Divisi || '-' }}
                        </span>
                        <span v-if="row.Nama_Sub_Divisi && row.Nama_Sub_Divisi !== '-'" class="text-2xs text-slate-500 text-truncate" :title="row.Nama_Sub_Divisi">
                            {{ row.Nama_Sub_Divisi }}
                        </span>
                    </div>
                </template>

                <!-- 5. Kolom Kategori Permit -->
                <template #cell-kategori="{ row }">
                    <span class="all-monitoring__kategori-pill text-truncate">
                        <span
                            v-if="row.Kode_Warna"
                            class="all-monitoring__color-dot me-1"
                            :style="{ backgroundColor: row.Kode_Warna }"
                        />
                        {{ row.Nama_Kategori || 'Permit Akses' }}
                    </span>
                </template>

                <!-- 6. Kolom Masa Berlaku -->
                <template #cell-masa_berlaku="{ row }">
                    <div class="d-flex flex-column gap-0.5 py-1">
                        <div class="d-flex align-items-center gap-1.5 text-xs text-slate-700 font-tabular">
                            <HcisIcon name="calendar-range" size="2xs" class="text-slate-400 flex-shrink-0" />
                            <!-- Jendela BERJAM bila izinnya punya, tanggal saja
                                 bila tidak. Izin bulanan karyawan tidak punya
                                 jendela jam, dan menempelkan "00:00" padanya
                                 hanya derau yang harus diabaikan pembacanya. -->
                            <span>
                                {{ waktuBerlaku(row.Waktu_Mulai_Berlaku || row.Tanggal_Berlaku) }}
                                <span class="text-slate-400">s/d</span>
                                {{ waktuBerlaku(row.Waktu_Berakhir || row.Tanggal_Kadaluarsa) }}
                            </span>
                        </div>
                        <span v-if="row.Snapshot_Nama_Durasi" class="text-2xs text-muted">
                            {{ row.Snapshot_Nama_Durasi }}
                        </span>
                    </div>
                </template>

                <!-- 7. Kolom Status & Sisa -->
                <template #cell-status="{ row }">
                    <div class="d-flex flex-column align-items-start gap-1 py-1">
                        <HcisBadge
                            :label="sisaLabel(row.Sisa_Hari, row.Waktu_Berakhir)"
                            :variant="sisaWarna(row.Sisa_Hari)"
                            size="xs"
                            appearance="subtle"
                            :dot="row.Status_Efektif === 'AKTIF' && Number(row.Sisa_Hari) > 0"
                        />
                        <span
                            v-if="row.Status_Efektif !== 'AKTIF'"
                            class="all-monitoring__dicabut"
                            :title="keteranganHapus(row)"
                        >
                            {{ labelStatus(row.Status_Efektif) }}
                        </span>
                    </div>
                </template>

                <!-- 8. Kolom Aksi -->
                <template #cell-aksi="{ row }">
                    <div class="d-inline-flex align-items-center gap-1">
                        <HcisButton
                            label="Detail"
                            variant="secondary"
                            size="xs"
                            leading-icon="eye"
                            @click.stop="bukaDetail(row)"
                        />
                        <HcisButton
                            v-if="bolehDicetak(row)"
                            variant="secondary"
                            size="xs"
                            leading-icon="printer"
                            title="Cetak kartu dashboard permit ini"
                            :loading="idKartuDimuat === idBaris(row)"
                            @click.stop="cetakKartu(row)"
                        />
                    </div>
                </template>

                <!-- Footer Pagination -->
                <template #footer>
                    <HcisPagination
                        v-if="paginasi.total > 10"
                        :model-value="paginasi.current_page"
                        :page-size="saring.limit"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="paginasi.total"
                        @update:model-value="gantiHalaman"
                        @update:page-size="gantiUkuranHalaman"
                    />
                </template>
            </HcisTable>
        </div>

        <!-- TAMPILAN 2: Kartu Grid Responsif Mobile & Tablet (viewMode === 'grid') -->
        <div v-else class="all-monitoring__grid-wrap">
            <!-- Skeleton Shimmer Cards -->
            <div v-if="memuat && !baris.length" class="all-monitoring__grid">
                <div v-for="n in 6" :key="`skel-permit-${n}`" class="all-monitoring__card all-monitoring__card--skeleton">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <HcisSkeleton width="35%" height="1.3rem" rounded="sm" />
                        <HcisSkeleton width="25%" height="1.1rem" rounded="full" />
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <HcisSkeleton width="2.25rem" height="2.25rem" rounded="full" />
                        <div class="flex-grow-1">
                            <HcisSkeleton width="70%" height="0.9rem" rounded="sm" class="mb-1" />
                            <HcisSkeleton width="40%" height="0.75rem" rounded="sm" />
                        </div>
                    </div>
                    <div class="all-monitoring__card-specs mb-3">
                        <HcisSkeleton height="2rem" rounded="sm" />
                        <HcisSkeleton height="2rem" rounded="sm" />
                    </div>
                    <HcisSkeleton height="2.25rem" rounded="md" />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!memuat && !baris.length" class="py-4">
                <HcisEmptyState
                    :title="saring.cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada permit pada saringan ini'"
                    :description="saring.cari ? 'Coba ubah kata kunci pencarian atau reset filter aktif.' : 'Belum ada permit kendaraan yang memenuhi kriteria filter.'"
                    icon="car-front"
                >
                    <template #actions>
                        <HcisButton
                            v-if="saring.cari || saring.status || saring.jenis_kendaraan"
                            label="Reset Filter"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            @click="reset"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <!-- Live Card Grid Items -->
            <div v-else class="all-monitoring__grid">
                <div
                    v-for="row in baris"
                    :key="row.No_Transaksi"
                    class="all-monitoring__card"
                    :class="getCardStatusClass(row)"
                    @click="bukaDetail(row)"
                >
                    <!-- Header: Top Badges + Checkbox (if eligible) -->
                    <div class="all-monitoring__card-top">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <!-- Checkbox for Bulk Revoke -->
                            <!-- Centang ikut terkunci untuk permit yang aksesnya
                                 sudah dicabut. Tombol "Cabut" per baris memang
                                 sudah disembunyikan, tetapi jalur MASSAL lewat
                                 centang ini tidak ikut terjaga — dan itu pintu
                                 kedua yang menuju tindakan yang sama. -->
                            <div
                                v-if="bolehRevoke"
                                class="all-monitoring__card-checkbox"
                                :class="{ 'is-terkunci': !bolehDicabut(row) }"
                                @click.stop
                            >
                                <input
                                    type="checkbox"
                                    :checked="terpilih.includes(row.No_Transaksi)"
                                    :disabled="!bolehDicabut(row)"
                                    class="form-check-input"
                                    :aria-label="bolehDicabut(row)
                                        ? `Pilih permit ${row.No_Transaksi}`
                                        : `Permit ${row.No_Transaksi} sudah dicabut, tidak bisa dipilih`"
                                    @change="toggleSelectCard(row.No_Transaksi)"
                                />
                            </div>
                            <span class="all-monitoring__plat font-tabular">{{ row.No_Plat_Kendaraan || '-' }}</span>
                            <span class="all-monitoring__kategori-pill text-truncate">
                                <span
                                    v-if="row.Kode_Warna"
                                    class="all-monitoring__color-dot me-1"
                                    :style="{ backgroundColor: row.Kode_Warna }"
                                />
                                {{ row.Nama_Kategori || 'Permit' }}
                            </span>
                        </div>

                        <HcisBadge
                            :label="sisaLabel(row.Sisa_Hari, row.Waktu_Berakhir)"
                            :variant="sisaWarna(row.Sisa_Hari)"
                            size="xs"
                            appearance="subtle"
                            :dot="row.Status_Efektif === 'AKTIF' && Number(row.Sisa_Hari) > 0"
                            class="flex-shrink-0"
                        />
                    </div>

                    <!-- Driver / Requester Info -->
                    <div class="all-monitoring__card-requester">
                        <HcisAvatar
                            :name="row.Nama_Karyawan"
                            size="sm"
                            shape="circle"
                            variant="brand"
                            class="flex-shrink-0"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <strong class="all-monitoring__card-name text-truncate d-block">
                                {{ row.Nama_Karyawan || '-' }}
                            </strong>
                            <div class="d-flex align-items-center gap-1.5 text-2xs text-slate-500 mt-0.5 font-tabular">
                                <span v-if="row.Kode_Karyawan_Pemohon" class="all-monitoring__nik-tag">
                                    Kode: {{ row.Kode_Karyawan_Pemohon }}
                                </span>
                                <span class="font-mono text-slate-400">#{{ row.No_Transaksi }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2x2 Specs Body Grid -->
                    <div class="all-monitoring__card-specs">
                        <div class="all-monitoring__card-spec-cell">
                            <span class="all-monitoring__card-spec-label">Divisi / Unit</span>
                            <span class="all-monitoring__card-spec-val text-truncate" :title="`${row.Nama_Divisi || '-'} / ${row.Nama_Sub_Divisi || '-'}`">
                                {{ row.Nama_Divisi || '-' }}
                            </span>
                        </div>

                        <div class="all-monitoring__card-spec-cell">
                            <span class="all-monitoring__card-spec-label">Jabatan</span>
                            <span class="all-monitoring__card-spec-val text-truncate" :title="row.Nama_Jabatan">
                                {{ row.Nama_Jabatan || '-' }}
                            </span>
                        </div>

                        <div class="all-monitoring__card-spec-cell">
                            <span class="all-monitoring__card-spec-label">Kendaraan</span>
                            <span class="all-monitoring__card-spec-val text-truncate">
                                {{ row.Nama_Vehicle_Type || row.Nama_Model_Manual || '-' }}
                            </span>
                        </div>

                        <div class="all-monitoring__card-spec-cell">
                            <span class="all-monitoring__card-spec-label">Masa Berlaku</span>
                            <span class="all-monitoring__card-spec-val font-tabular text-2xs text-truncate">
                                {{ tanggal(row.Tanggal_Berlaku) }} s/d {{ tanggal(row.Tanggal_Kadaluarsa) }}
                            </span>
                        </div>
                    </div>

                    <!-- Revocation Info Callout if Revoked -->
                    <div v-if="row.Status_Efektif === 'DIBATALKAN'" class="all-monitoring__card-revoked-box">
                        <HcisIcon name="slash-circle-fill" size="2xs" class="text-rose-500 flex-shrink-0 mt-0.5" />
                        <div class="min-w-0 flex-grow-1 text-2xs">
                            <strong>Akses Dihapus:</strong> {{ row.Alasan_Pencabutan || 'Alasan tidak tercatat' }}
                            <span v-if="row.Dicabut_Oleh" class="text-slate-500 d-block mt-0.5">Oleh: {{ row.Dicabut_Oleh }}</span>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="all-monitoring__card-footer" @click.stop>
                        <HcisButton
                            label="Detail Permit"
                            variant="secondary"
                            size="xs"
                            leading-icon="eye"
                            class="all-monitoring__card-btn-detail"
                            @click="bukaDetail(row)"
                        />
                        <HcisButton
                            v-if="bolehRevoke && bolehDicabut(row)"
                            label="Hapus Akses"
                            variant="danger"
                            size="xs"
                            leading-icon="shield-slash"
                            @click="bukaRevoke(row)"
                        />
                    </div>
                </div>
            </div>

            <!-- Card Grid Pagination -->
            <div v-if="paginasi.total > 10" class="all-monitoring__grid-pagination mt-3">
                <HcisPagination
                    :model-value="paginasi.current_page"
                    :page-size="saring.limit"
                        :page-sizes="[10, 20, 50, 100]"
                    :total="paginasi.total"
                    @update:model-value="gantiHalaman"
                    @update:page-size="gantiUkuranHalaman"
                />
            </div>
        </div>

        <!-- Floating Glass Bottom Dock for Bulk Actions on Mobile & Desktop -->
        <Teleport to="body">
            <Transition name="dock-slide">
                <div v-if="terpilih.length > 0 || pilihSemuaFilter" class="all-monitoring__floating-dock">
                    <div class="all-monitoring__dock-inner">
                        <!-- Left: Counter Pill -->
                        <div class="all-monitoring__dock-left">
                            <span class="all-monitoring__dock-count-pill">
                                <HcisIcon name="check2-all" size="xs" />
                                <span class="font-tabular font-bold">{{ jumlahTerpilihEfektif }}</span>
                            </span>
                            <span class="all-monitoring__dock-count-label">Terpilih</span>
                        </div>

                        <!-- Right: Actions -->
                        <div class="all-monitoring__dock-right">
                            <button
                                type="button"
                                class="all-monitoring__dock-btn all-monitoring__dock-btn--print"
                                :disabled="!idsCetakTerpilih.length || memuatKartuBatch"
                                :title="idsCetakTerpilih.length ? `Cetak ${idsCetakTerpilih.length} kartu permit (PDF)` : 'Pilih permit aktif yang dapat dicetak'"
                                @click="cetakKartuBatch"
                            >
                                <span v-if="memuatKartuBatch" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <HcisIcon v-else name="printer" size="xs" />
                                <span>Cetak Kartu <strong class="font-tabular">({{ idsCetakTerpilih.length }})</strong></span>
                            </button>

                            <button
                                type="button"
                                class="all-monitoring__dock-btn all-monitoring__dock-btn--danger"
                                @click="bukaBulkRevoke"
                            >
                                <HcisIcon name="shield-slash-fill" size="xs" />
                                <span>Hapus Akses <strong class="font-tabular">({{ jumlahTerpilihEfektif }})</strong></span>
                            </button>

                            <span class="all-monitoring__dock-divider" aria-hidden="true"></span>

                            <button
                                type="button"
                                class="all-monitoring__dock-btn all-monitoring__dock-btn--cancel"
                                title="Batalkan semua pilihan"
                                @click="batalPilih"
                            >
                                <HcisIcon name="x-lg" size="xs" />
                                <span>Batal</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Kartu dashboard yang dicetak admin: PDF server, viewer yang sama
             dengan milik pemegang kartunya. -->
        <HcisFileViewer
            v-model="showKartuViewer"
            :files="kartuFiles"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />

        <!-- Modal Review & Detail Permit (sama dengan riwayat verifikator) -->
        <PermitReviewModal
            v-if="dialogDetail"
            :show="dialogDetail"
            :permit-id="idDetail"
            detail-endpoint="/laporanvisitor-detail-permit"
            read-only
            @close="dialogDetail = false"
            revoke-tersedia
            cetak-tersedia
            @minta-revoke="bukaRevoke"
        />

        <!-- Dialog Hapus Akses Single Permit -->
        <HcisDialog
            v-model="dialogRevoke"
            title="Hapus akses permit yang sudah disetujui"
            :description="`Akses kendaraan ${targetRevoke?.No_Plat_Kendaraan || ''} atas nama ${targetRevoke?.Nama_Karyawan || ''} akan dihapus dan pemohon diberi tahu.`"
            sentiment="danger"
            size="sm"
            @close="tutupRevoke"
        >
            <HcisField
                id="permit-alasan-revoke"
                v-model="alasanRevoke"
                label="Alasan penghapusan akses"
                type="textarea"
                :rows="3"
                placeholder="Mis. kendaraan sudah tidak dipakai, pemohon pindah unit, mutasi tugas…"
                :max-length="500"
                show-count
                required
                :error="galatAlasan"
            />

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton label="Batal" variant="ghost" size="sm" :disabled="mengirimRevoke" @click="tutupRevoke" />
                    <HcisButton
                        label="Hapus akses"
                        variant="danger"
                        size="sm"
                        :loading="mengirimRevoke"
                        @click="kirimRevoke"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog Hapus Akses Massal (Bulk Revoke) -->
        <HcisDialog
            v-model="dialogBulkRevoke"
            :title="`Hapus akses ${jumlahTerpilihEfektif} permit`"
            :description="pilihSemuaFilter
                ? `Akses untuk permit yang sesuai filter akan dihapus bersamaan. Permit yang aksesnya sudah dicabut dilewati, sehingga jumlah akhirnya bisa lebih kecil dari ${jumlahTerpilihEfektif}. Seluruh pemohon terkait menerima notifikasi.`
                : `Akses untuk ${jumlahTerpilihEfektif} permit yang dipilih akan dihapus secara bersamaan dan seluruh pemohon terkait akan menerima notifikasi pemberitahuan.`"
            sentiment="danger"
            size="sm"
            @close="tutupBulkRevoke"
        >
            <HcisField
                id="permit-alasan-bulk-revoke"
                v-model="alasanBulkRevoke"
                label="Alasan penghapusan akses"
                type="textarea"
                :rows="3"
                placeholder="Mis. penataan ulang akses, pemohon mutasi divisi, pembaruan kebijakan operasional…"
                :max-length="500"
                show-count
                required
                :error="galatAlasanBulk"
            />

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton label="Batal" variant="ghost" size="sm" :disabled="mengirimBulkRevoke" @click="tutupBulkRevoke" />
                    <HcisButton
                        :label="`Hapus Akses (${jumlahTerpilihEfektif} Permit)`"
                        variant="danger"
                        size="sm"
                        :loading="mengirimBulkRevoke"
                        @click="kirimBulkRevoke"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import PermitReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFileViewer,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTable,
} from '@/components/ui';

/**
 * Kolom tabel yang dipadatkan dan dirancang ergonomis:
 * Menggabungkan informasi hirarkis agar tabel tidak meluber ke samping.
 */
const KOLOM = [
    { key: 'No_Transaksi', label: 'No Transaksi', width: '130px', sortable: true },
    { key: 'pemohon', label: 'Pemegang permit', minWidth: '220px', sortable: true },
    { key: 'kendaraan', label: 'Kendaraan', width: '150px' },
    { key: 'unit_kerja', label: 'Unit / Divisi', width: '160px', sortable: true },
    { key: 'kategori', label: 'Kategori', width: '130px', sortable: true },
    { key: 'masa_berlaku', label: 'Masa Berlaku', width: '175px', sortable: true },
    { key: 'status', label: 'Status & Sisa', width: '130px', align: 'left' },
    { key: 'aksi', label: 'Aksi', width: '85px', align: 'right' },
];

/**
 * Kolom tabel -> kunci urut yang dikenal server (URUT_PERMIT di controller).
 */
const PETA_URUT = {
    No_Transaksi: 'no_transaksi',
    pemohon: 'pemohon',
    unit_kerja: 'divisi',
    kategori: 'kategori',
    masa_berlaku: 'kadaluarsa',
    status: 'kadaluarsa',
};

const SARINGAN = [
    {
        // Dua makna rentang tanggal yang sering tertukar, jadi modenya dipilih
        // pemakainya sendiri: kapan permit DISETUJUI, atau kapan ia BERLAKU.
        key: 'mode_tanggal',
        label: 'Mode tanggal',
        type: 'select',
        placeholder: 'Masa berlaku',
        icon: 'calendar2-week',
        clearable: false,
        options: [
            { value: 'berlaku', label: 'Masa berlaku' },
            { value: 'approve', label: 'Tanggal disetujui' },
        ],
    },
    {
        key: 'rentang_tanggal',
        label: 'Rentang tanggal',
        type: 'daterange',
        placeholder: 'Semua tanggal',
        icon: 'calendar-range',
        span: 2,
    },
    {
        key: 'status',
        label: 'Masa berlaku',
        type: 'select',
        placeholder: 'Semua masa berlaku',
        icon: 'calendar-range',
        options: [
            { value: 'aktif', label: 'Masih berlaku' },
            { value: 'segera', label: 'Segera kedaluwarsa (30 hari)' },
            { value: 'kadaluarsa', label: 'Sudah kedaluwarsa' },
        ],
    },
    {
        // KATEGORI izin (HIJAU / MERAH / COKLAT …). Pilihannya diisi dari
        // master kategori aktif yang dikirim server (opsi_kategori), lihat
        // computed saringanTampil.
        key: 'jenis_permit',
        label: 'Kategori permit',
        type: 'select',
        placeholder: 'Semua kategori',
        icon: 'tags',
        options: [],
    },
    {
        // ASAL izin, bukan KATEGORI-nya. Keduanya sengaja terpisah: kategori
        // COKLAT juga sah dipakai permit karyawan biasa, jadi menyaring lewat
        // kategori akan menyapu izin yang salah.
        key: 'asal',
        label: 'Asal permit',
        type: 'select',
        placeholder: 'Semua asal permit',
        icon: 'diagram-2',
        options: [
            { value: 'KARYAWAN', label: 'Kendaraan karyawan' },
            { value: 'TAMU', label: 'Permit kunjungan tamu' }
            // { value: 'MITRA', label: 'Procurment B2B' },
        ],
    },
    {
        key: 'jenis_kendaraan',
        label: 'Tipe kendaraan',
        type: 'select',
        placeholder: 'Semua kendaraan',
        icon: 'car-front',
        options: [
            { value: 'Mobil', label: 'Mobil' },
            { value: 'Motor', label: 'Motor' },
            { value: 'Truk', label: 'Truk / Bus' },
        ],
    },
];

const QUICK_CHIPS = [
    { label: 'Semua Permit', value: '' },
    { label: 'Masih Berlaku', value: 'aktif' },
    { label: 'Segera Kedaluwarsa (≤30 hari)', value: 'segera' },
    { label: 'Sudah Kedaluwarsa', value: 'kadaluarsa' },
];

export default {
    name: 'PermitAllMonitoring',
    components: {
        PermitReviewModal,
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisDialog,
        HcisEmptyState,
        HcisField,
        HcisFileViewer,
        HcisFilterBar,
        HcisIcon,
        HcisPagination,
        HcisSkeleton,
        HcisStatCard,
        HcisStatGroup,
        HcisTable,
    },
    props: {
        needsApproval: { type: Boolean, default: true },
    },
    data() {
        return {
            KOLOM,
            SARINGAN,
            QUICK_CHIPS,
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            memuat: true,
            galat: '',
            // Kartu dashboard yang dicetak admin
            showKartuViewer: false,
            kartuFiles: [],
            idKartuDimuat: null,
            memuatKartuBatch: false,
            baris: [],
            stats: {},
            paginasi: { current_page: 1, total_pages: 1, total: 0 },
            // Bawaan server: pengajuan terbaru di atas — tidak mewakili satu
            // kolom tabel, jadi tak ada kolom yang disorot.
            urutKolom: null,
            opsiKategori: [],
            saring: this.saringanBaku(),
            // Dijawab server; layar tidak pernah memutuskannya sendiri.
            bolehRevoke: false,
            dialogDetail: false,
            idDetail: null,
            dialogRevoke: false,
            targetRevoke: null,
            alasanRevoke: '',
            galatAlasan: '',
            mengirimRevoke: false,

            // Multiple Selection & Bulk Revoke
            terpilih: [],
            pilihSemuaFilter: false,
            dialogBulkRevoke: false,
            alasanBulkRevoke: '',
            galatAlasanBulk: '',
            mengirimBulkRevoke: false,
        };
    },
    computed: {
        saringanTampil() {
            return SARINGAN.map((f) => (f.key === 'jenis_permit'
                ? { ...f, options: this.opsiKategori.map((k) => ({ value: k, label: k })) }
                : f));
        },
        barisTerpilihHalamanIni() {
            if (!this.baris.length) return [];
            return this.baris.filter((r) => this.terpilih.includes(r.No_Transaksi));
        },
        semuaHalamanIniTerpilih() {
            if (!this.baris.length) return false;
            return this.baris.every((r) => this.terpilih.includes(r.No_Transaksi));
        },
        /**
         * Kunci terpilih yang MASIH boleh dicabut pada halaman ini.
         *
         * Dipakai membersihkan sisa pilihan setelah data dimuat ulang: sebuah
         * permit bisa dicabut orang lain — atau oleh kita sendiri di halaman
         * sebelumnya — sementara centangnya masih tersimpan di memori layar.
         */
        terpilihMasihSah() {
            const terkunci = this.baris
                .filter((r) => !this.bolehDicabut(r))
                .map((r) => r.No_Transaksi);

            return this.terpilih.filter((k) => !terkunci.includes(k));
        },
        jumlahTerpilihEfektif() {
            return this.pilihSemuaFilter ? (this.paginasi.total ?? 0) : this.terpilih.length;
        },
        /**
         * Id permit terpilih yang punya wujud cetak. Diambil dari baris yang
         * SEDANG dimuat: kartu butuh datanya, dan "pilih semua sesuai filter"
         * bisa berarti ratusan baris yang belum pernah diturunkan ke layar.
         */
        idsCetakTerpilih() {
            return this.baris
                .filter((row) => this.terpilih.includes(row.No_Transaksi) && this.bolehDicetak(row))
                .map((row) => this.idBaris(row))
                .filter(Boolean);
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        saringanBaku() {
            return {
                cari: '',
                status: '',
                asal: '',
                jenis_kendaraan: '',
                jenis_permit: '',
                mode_tanggal: 'berlaku',
                rentang_tanggal: [],
                // Pengajuan TERBARU di atas (permintaan user 30 Sep 2026).
                urut: 'diajukan',
                arah: 'desc',
                page: 1,
                limit: 20,
            };
        },
        tanggal(nilai) {
            return nilai ? String(nilai).slice(0, 10) : '-';
        },
        /**
         * Rentang tanggal dipecah jadi dua ruas untuk server, dan kuncinya
         * sendiri TIDAK ikut dikirim: `rentang_tanggal` adalah larik, dan
         * mengirim larik sebagai query param membuat server membacanya sebagai
         * nilai kosong tanpa satu pun galat.
         */
        /** Saringan yang menentukan HIMPUNAN barisnya (tanpa halaman & urutan). */
        saringanAktif() {
            const { cari, status, asal, jenis_kendaraan, jenis_permit, mode_tanggal, tanggal_dari, tanggal_sampai } = this.paramSaringan();
            return { cari, status, asal, jenis_kendaraan, jenis_permit, mode_tanggal, tanggal_dari, tanggal_sampai };
        },
        paramSaringan() {
            // eslint-disable-next-line no-unused-vars
            const { rentang_tanggal: rentang, ...sisa } = this.saring;
            const [dari, sampai] = Array.isArray(rentang) ? rentang : [];

            return {
                ...sisa,
                tanggal_dari: this.keTanggal(dari),
                tanggal_sampai: this.keTanggal(sampai),
            };
        },
        keTanggal(nilai) {
            if (!nilai) return '';
            if (nilai instanceof Date) {
                const p = (n) => String(n).padStart(2, '0');

                return `${nilai.getFullYear()}-${p(nilai.getMonth() + 1)}-${p(nilai.getDate())}`;
            }

            return String(nilai).slice(0, 10);
        },
        /**
         * Masa berlaku yang JUJUR terhadap jendela jam.
         *
         * Sejak verifikator menetapkan jendelanya sendiri, dua izin yang
         * sama-sama berakhir "hari ini" bisa berarti pukul delapan pagi dan
         * tengah malam — dan yang pertama sudah mati saat layarnya dibaca.
         * Jamnya hanya ditampilkan bila memang ada; izin bulanan karyawan tidak
         * punya jendela jam, dan menempelkan "00:00" padanya hanya derau.
         */
        waktuBerlaku(nilai) {
            if (!nilai) return '-';
            const teks = String(nilai).replace('T', ' ');

            return teks.length > 10 ? teks.slice(0, 16) : teks.slice(0, 10);
        },
        labelAsal(asal) {
            return {
                KARYAWAN: 'Karyawan',
                TAMU: 'Permit tamu',
                MITRA: 'Mitra',
            }[asal] || 'Karyawan';
        },
        asalVarian(asal) {
            if (asal === 'TAMU') return 'tamu';
            if (asal === 'MITRA') return 'mitra';

            return 'karyawan';
        },
        /**
         * Sisa_Hari dihitung per tanggal: 0 berarti HARI TERAKHIR berlaku, bukan
         * habis. Jam berakhirnya ikut ditulis — izin sehari tamu berlaku sampai
         * 23:59, dan "0 hari" terbaca seperti sudah kedaluwarsa (29 Sep 2026).
         */
        sisaLabel(sisa, waktuBerakhir = null) {
            if (sisa === null || sisa === undefined) return '-';
            const n = Number(sisa);
            if (n < 0) return `Lewat ${Math.abs(n)} hari`;
            if (n === 0) {
                const jam = String(waktuBerakhir || '').slice(11, 16);
                return jam ? `Berakhir hari ini ${jam}` : 'Berakhir hari ini';
            }

            return `${n} hari`;
        },
        sisaWarna(sisa) {
            const n = Number(sisa);
            if (! Number.isFinite(n)) return 'neutral';
            if (n < 0) return 'danger';
            if (n <= 30) return 'warning';

            return 'success';
        },
        getCardStatusClass(row) {
            if (row.Status_Efektif === 'DIBATALKAN') return 'all-monitoring__card--revoked';
            const n = Number(row.Sisa_Hari);
            if (n < 0) return 'all-monitoring__card--expired';
            if (n <= 30) return 'all-monitoring__card--warning';
            return 'all-monitoring__card--active';
        },
        gantiCari(nilai) {
            this.saring.cari = nilai;
            this.batalPilih();
            this.muatDariAwal();
        },
        gantiSaringan({ key, value }) {
            this.saring[key] = value ?? '';
            this.batalPilih();
            this.muatDariAwal();
        },
        gantiChipStatus({ value }) {
            this.saring.status = value ?? '';
            this.batalPilih();
            this.muatDariAwal();
        },
        gantiUrutan({ key, order }) {
            const kunci = PETA_URUT[key];
            if (! kunci) return;

            this.urutKolom = key;
            this.saring.urut = kunci;
            this.saring.arah = order;
            this.batalPilih();
            this.muatDariAwal();
        },
        gantiUkuranHalaman(ukuran) {
            this.saring.limit = ukuran;
            this.batalPilih();
            this.muatDariAwal();
        },
        muatDariAwal() {
            this.saring.page = 1;
            this.muat();
        },
        gantiHalaman(halaman) {
            this.saring.page = halaman;
            this.muat();
        },
        reset() {
            this.saring = this.saringanBaku();
            this.urutKolom = null;
            this.batalPilih();
            this.muat();
        },
        keteranganHapus(row) {
            if (row.Status_Efektif !== 'DIBATALKAN') return '';

            const alasan = row.Alasan_Pencabutan || 'Alasan tidak tercatat.';
            const oleh = row.Dicabut_Oleh ? ` — Dihapus oleh ${row.Dicabut_Oleh}` : '';
            const pada = row.Dicabut_Pada ? `, ${this.tanggal(row.Dicabut_Pada)}` : '';

            return `${alasan}${oleh}${pada}`;
        },
        labelStatus(status) {
            if (status === 'DIBATALKAN') return 'Akses Dihapus';
            if (status === 'TANPA_AKSES') return 'Tanpa akses';
            if (status === 'EXPIRED') return 'Kedaluwarsa';

            return status || '-';
        },
        bukaDetail(sumber) {
            const baris = sumber?.row ?? sumber;
            if (!baris) return;
            this.idDetail = baris.Id ?? baris.Id_Transaksi_Permit ?? baris.No_Transaksi ?? null;
            this.dialogDetail = true;
        },
        /**
         * Hanya akses yang MASIH hidup yang bisa dicabut.
         *
         * Dipakai tiga tempat: kolom centang tabel, centang kartu, dan tombol
         * Cabut per baris. Satu penentu untuk ketiganya — versi sebelumnya
         * hanya menjaga tombolnya, sehingga permit yang sudah dicabut tetap
         * bisa ikut tercentang untuk pencabutan massal. Server menolaknya diam
         * (revokePermitEfektifMany hanya memulangkan baris yang tadinya aktif),
         * jadi yang terjadi bukan data rusak melainkan laporan yang
         * membingungkan: "3 permit dicabut" atas sepuluh yang dipilih.
         */
        bolehDicabut(row) {
            return Boolean(row) && row.Status_Efektif === 'AKTIF';
        },
        /**
         * Kartu hanya punya wujud cetak selama permitnya masih berlaku;
         * server menolak sisanya. Tombolnya ikut disembunyikan supaya admin
         * tidak menekan sesuatu yang pasti gagal.
         */
        bolehDicetak(row) {
            return Boolean(row) && row.Status_Efektif === 'AKTIF';
        },
        idBaris(row) {
            return row?.Id ?? row?.Id_Transaksi_Permit ?? null;
        },
        /**
         * PDF-nya dirender server dan dibuka di viewer yang sama dengan milik
         * pemegang kartu — satu berkas untuk dilihat, diunduh, dan dicetak.
         * Pintunya berbeda: rute admin dijaga kunci halaman monitoring /
         * verifikator, bukan kepemilikan permit.
         */
        async cetakKartu(row) {
            const id = this.idBaris(row);
            if (!id || this.idKartuDimuat) return;

            this.idKartuDimuat = id;
            try {
                const { data } = await axios.get(`/laporanvisitor-permit/${id}/kartu-pdf`, { responseType: 'blob' });
                const plat = String(row?.No_Plat_Kendaraan || 'permit').replace(/\s+/g, '');

                this.kartuFiles = [{
                    url: URL.createObjectURL(data),
                    name: `kartu-permit-${plat}.pdf`,
                    title: `Kartu Permit ${row?.No_Plat_Kendaraan || ''}`.trim(),
                    ext: 'pdf',
                    allowDownload: true,
                }];
                this.showKartuViewer = true;
            } catch (e) {
                ElMessage.error(await this.pesanGalatBlob(e) || 'Kartu permit gagal dibuat.');
            } finally {
                this.idKartuDimuat = null;
            }
        },
        /**
         * Satu PDF berisi banyak kartu, DUA per lembar A4 dengan garis potong.
         * Permit yang sudah tidak aktif disaring server, jadi centang yang
         * terlanjur mengenai kartu mati tidak menggagalkan seluruh berkas.
         */
        async cetakKartuBatch() {
            const ids = this.idsCetakTerpilih;
            if (!ids.length || this.memuatKartuBatch) return;

            this.memuatKartuBatch = true;
            try {
                const { data } = await axios.post('/laporanvisitor-permit/kartu-pdf-batch', { ids }, { responseType: 'blob' });

                this.kartuFiles = [{
                    url: URL.createObjectURL(data),
                    name: `kartu-permit-${ids.length}-kartu.pdf`,
                    title: `Kartu Permit (${ids.length} kartu)`,
                    ext: 'pdf',
                    allowDownload: true,
                }];
                this.showKartuViewer = true;
            } catch (e) {
                ElMessage.error(await this.pesanGalatBlob(e) || 'Kartu permit gagal dibuat.');
            } finally {
                this.memuatKartuBatch = false;
            }
        },
        /** Galat dari permintaan blob datang sebagai Blob, bukan JSON. */
        async pesanGalatBlob(e) {
            const isi = e?.response?.data;
            if (!(isi instanceof Blob)) return isi?.message || '';

            try {
                return JSON.parse(await isi.text())?.message || '';
            } catch {
                return '';
            }
        },
        bukaRevoke(baris) {
            this.targetRevoke = baris;
            this.alasanRevoke = '';
            this.galatAlasan = '';
            this.dialogRevoke = true;
        },
        tutupRevoke() {
            if (this.mengirimRevoke) return;

            this.dialogRevoke = false;
            this.targetRevoke = null;
        },
        async kirimRevoke() {
            const alasan = this.alasanRevoke.trim();

            if (! alasan) {
                this.galatAlasan = 'Alasan penghapusan akses wajib diisi.';

                return;
            }

            this.mengirimRevoke = true;
            this.galatAlasan = '';
            try {
                await axios.post(
                    `/verifikator-permit/${encodeURIComponent(this.targetRevoke.No_Transaksi)}/revoke`,
                    { alasan },
                );
                const dicabut = this.targetRevoke.No_Transaksi;
                this.dialogRevoke = false;
                this.targetRevoke = null;
                this.dialogDetail = false;
                this.terpilih = this.terpilih.filter((k) => k !== dicabut);
                await this.muat();
            } catch (error) {
                this.galatAlasan = error?.response?.data?.message
                    || 'Akses permit gagal dihapus. Coba lagi atau hubungi verifikator.';
            } finally {
                this.mengirimRevoke = false;
            }
        },

        // Selection & Bulk Revoke Methods
        onUpdateSelectedKeys(keys) {
            this.terpilih = keys;
            if (!keys.length) {
                this.pilihSemuaFilter = false;
            }
        },
        onSelectAll({ checked, selectedKeys }) {
            this.terpilih = selectedKeys;
            if (!checked) {
                this.pilihSemuaFilter = false;
            }
        },
        toggleSelectCard(key) {
            // Penjaga kedua bagi tampilan kartu; atribut disabled tidak berlaku
            // bila pemanggilannya datang dari tempat lain.
            const baris = this.baris.find((r) => r.No_Transaksi === key);
            if (baris && !this.bolehDicabut(baris)) return;

            if (this.terpilih.includes(key)) {
                this.terpilih = this.terpilih.filter((k) => k !== key);
            } else {
                this.terpilih = [...this.terpilih, key];
            }
            if (!this.terpilih.length) {
                this.pilihSemuaFilter = false;
            }
        },
        pilihSemuaSeluruhFilter() {
            this.pilihSemuaFilter = true;
        },
        batalPilihSemuaSeluruhFilter() {
            this.pilihSemuaFilter = false;
        },
        batalPilih(clearFn) {
            this.terpilih = [];
            this.pilihSemuaFilter = false;
            if (typeof clearFn === 'function') {
                clearFn();
            }
        },
        bukaBulkRevoke() {
            if (this.jumlahTerpilihEfektif === 0) return;
            this.alasanBulkRevoke = '';
            this.galatAlasanBulk = '';
            this.dialogBulkRevoke = true;
        },
        tutupBulkRevoke() {
            if (this.mengirimBulkRevoke) return;
            this.dialogBulkRevoke = false;
            this.alasanBulkRevoke = '';
            this.galatAlasanBulk = '';
        },
        async kirimBulkRevoke() {
            const alasan = this.alasanBulkRevoke.trim();
            if (!alasan) {
                this.galatAlasanBulk = 'Alasan penghapusan akses wajib diisi.';
                return;
            }

            this.mengirimBulkRevoke = true;
            this.galatAlasanBulk = '';
            try {
                const payload = {
                    alasan,
                    select_all_matching: this.pilihSemuaFilter,
                    items: this.pilihSemuaFilter ? [] : this.terpilih,
                    // SELURUH saringan yang sedang aktif — server memakai definisi
                    // yang sama dengan daftar (SaringanMonitoringPermit). Dulu asal
                    // dan rentang tanggal tidak ikut, sehingga "pilih semua" ikut
                    // mencabut permit di luar yang terlihat.
                    filters: this.pilihSemuaFilter ? this.saringanAktif() : {},
                };

                await axios.post('/verifikator-permit-bulk-revoke', payload);
                this.dialogBulkRevoke = false;
                this.alasanBulkRevoke = '';
                this.batalPilih();
                await this.muat();
            } catch (error) {
                this.galatAlasanBulk = error?.response?.data?.message
                    || 'Akses permit gagal dihapus. Coba lagi atau hubungi verifikator.';
            } finally {
                this.mengirimBulkRevoke = false;
            }
        },
        async muat() {
            this.memuat = true;
            this.galat = '';
            try {
                const { data } = await axios.get('/laporanvisitor-permit', { params: this.paramSaringan() });
                this.baris = data?.result ?? [];

                // Pilihan yang sudah tidak sah dibuang setiap kali data dimuat
                // ulang. Sebuah permit bisa dicabut orang lain — atau oleh kita
                // sendiri sesaat sebelumnya — sementara centangnya masih
                // tersimpan di memori layar, dan dok tindakan massal terus
                // menghitungnya sebagai sasaran yang sah.
                if (this.terpilih.length) {
                    this.terpilih = this.terpilihMasihSah;
                }

                this.stats = data?.stats ?? {};
                if (Array.isArray(data?.opsi_kategori)) this.opsiKategori = data.opsi_kategori;
                this.bolehRevoke = data?.boleh_revoke === true;
                this.paginasi = data?.pagination ?? { current_page: 1, total_pages: 1, total: 0 };
            } catch (error) {
                this.galat = error?.response?.data?.message || 'Gagal memuat daftar permit.';
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
/* Centang yang terkunci tetap terlihat — diredupkan, bukan dihilangkan.
   Kolom yang mendadak kosong pada sebagian baris terbaca sebagai tabel yang
   rusak, sementara centang redup menjelaskan sendiri kenapa ia tak bisa
   ditekan. */
.all-monitoring__card-checkbox.is-terkunci {
    opacity: 0.4;
    cursor: not-allowed;
}

.all-monitoring__banner-catatan {
    display: inline-block;
    margin-left: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

/* Bentuknya sama persis dengan penanda di Monitoring Tamu: satu admin sering
   membuka keduanya berdampingan, dan penanda yang berbeda rupa membuatnya
   mengira sedang membaca hal yang berbeda. */
.pm-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.pm-tag--karyawan {
    color: #3730a3;
    background: #e0e7ff;
    border: 1px solid #c7d2fe;
}

.pm-tag--tamu {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.pm-tag--mitra {
    color: #9a3412;
    background: #ffedd5;
    border: 1px solid #fed7aa;
}

.all-monitoring {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.all-monitoring__table-wrap {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
}

.all-monitoring__waktu {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    font-size: 0.8125rem;
    color: var(--hcis-slate-700, #334155);
}

.all-monitoring__link {
    display: inline-flex;
    align-items: center;
    background: transparent;
    border: none;
    padding: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
    cursor: pointer;
    text-align: left;
    transition: color 0.15s ease;
}

.all-monitoring__link:hover {
    color: var(--hcis-brand-700, #4338ca);
    text-decoration: underline;
}

.all-monitoring__plat {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    background: var(--hcis-slate-900, #0f172a);
    color: var(--hcis-white, #ffffff);
    font-weight: 750;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.all-monitoring__kategori-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.55rem;
    border-radius: 9999px;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    font-size: 0.71875rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
    max-width: 180px;
}

.all-monitoring__color-dot {
    display: inline-block;
    width: 6.5px;
    height: 6.5px;
    border-radius: 50%;
    flex-shrink: 0;
}

.all-monitoring__nik-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.65625rem;
    font-weight: 650;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.all-monitoring__aksi {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    justify-content: flex-end;
}

.all-monitoring__dicabut {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-danger-600, #e11d48);
    background: var(--hcis-danger-50, #fff1f2);
    border: 1px solid var(--hcis-danger-200, #fecdd3);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    cursor: help;
}

.all-monitoring__select-all-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    border-radius: 0.625rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(224, 231, 255, 0.85) 100%);
    border: 1px solid rgba(199, 210, 254, 0.9);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);
}

.all-monitoring__banner-text {
    font-size: 0.8125rem;
    font-weight: 550;
    color: var(--hcis-slate-800, #1e293b);
}

/* =========================================================================
   CARD GRID VIEW FOR MOBILE & TABLETS (Section 19.5, 19.9)
   ========================================================================= */
.all-monitoring__grid-wrap {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.all-monitoring__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

@media (min-width: 640px) {
    .all-monitoring__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .all-monitoring__grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.all-monitoring__card {
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.95rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.all-monitoring__card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
}

/* Distinct Emerald Green Active Status Identification (Section 19.9) */
.all-monitoring__card--active {
    border-left: 4.5px solid var(--hcis-success-500, #10b981) !important;
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95) 0%, rgba(255, 255, 255, 0.98) 50%, rgba(209, 250, 229, 0.6) 100%) !important;
    border-color: rgba(16, 185, 129, 0.35);
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.12);
}

.all-monitoring__card--warning {
    border-left: 4.5px solid var(--hcis-warning-500, #f59e0b) !important;
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.9) 0%, rgba(255, 255, 255, 0.98) 50%, rgba(253, 230, 138, 0.4) 100%) !important;
    border-color: rgba(245, 158, 11, 0.35);
}

.all-monitoring__card--expired,
.all-monitoring__card--revoked {
    border-left: 4.5px solid var(--hcis-danger-500, #ef4444) !important;
    background: linear-gradient(135deg, rgba(254, 226, 226, 0.9) 0%, rgba(255, 255, 255, 0.98) 50%, rgba(254, 202, 202, 0.4) 100%) !important;
    border-color: rgba(239, 68, 68, 0.35);
}

.all-monitoring__card--skeleton {
    cursor: default;
}

.all-monitoring__card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
}

.all-monitoring__card-checkbox {
    display: inline-flex;
    align-items: center;
}

.all-monitoring__card-checkbox input {
    cursor: pointer;
    width: 1.1rem;
    height: 1.1rem;
}

.all-monitoring__card-requester {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.all-monitoring__card-name {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
}

.all-monitoring__card-specs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.6rem 0.7rem;
    min-width: 0;
}

.all-monitoring__card-spec-cell {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.all-monitoring__card-spec-label {
    font-size: 0.625rem;
    font-weight: 650;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.all-monitoring__card-spec-val {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
}

.all-monitoring__card-revoked-box {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    padding: 0.45rem 0.55rem;
    border-radius: 6px;
    background: rgba(254, 226, 226, 0.75);
    border: 1px solid rgba(248, 113, 113, 0.4);
    color: var(--hcis-danger-700, #be123c);
}

.all-monitoring__card-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.25rem;
}

.all-monitoring__card-btn-detail {
    flex-grow: 1;
    font-weight: 700;
}

/* =========================================================================
   FLOATING GLASS BOTTOM DOCK FOR BULK ACTIONS (HCIS LUXURY PILL ARCHITECTURE)
   ========================================================================= */
.all-monitoring__floating-dock {
    position: fixed;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1050;
    width: auto;
    min-width: 320px;
    max-width: min(calc(100vw - 2rem), 680px);
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.96) 0%, rgba(30, 41, 59, 0.94) 100%);
    backdrop-filter: blur(20px) saturate(190%);
    -webkit-backdrop-filter: blur(20px) saturate(190%);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 9999px;
    padding: 0.4rem 0.55rem 0.4rem 0.85rem;
    box-sizing: border-box;
    box-shadow:
        0 20px 45px -8px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.08) inset,
        0 8px 18px -4px rgba(15, 23, 42, 0.4);
    white-space: nowrap;
}

.all-monitoring__dock-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
}

.all-monitoring__dock-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.all-monitoring__dock-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(99, 102, 241, 0.28);
    border: 1px solid rgba(129, 140, 248, 0.45);
    color: #e0e7ff;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.8125rem;
    font-weight: 750;
    letter-spacing: 0.01em;
}

.all-monitoring__dock-count-label {
    font-size: 0.8125rem;
    font-weight: 650;
    color: rgba(255, 255, 255, 0.9);
    letter-spacing: 0.01em;
}

.all-monitoring__dock-right {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.all-monitoring__dock-divider {
    width: 1px;
    height: 1.25rem;
    background: rgba(255, 255, 255, 0.18);
    margin: 0 0.1rem;
    flex-shrink: 0;
}

.all-monitoring__dock-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    font-weight: 700;
    padding: 0.38rem 0.8rem;
    border-radius: 9999px;
    border: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    outline: none;
    line-height: 1.2;
}

.all-monitoring__dock-btn:focus-visible {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.4);
}

/* Print Button (Crisp White Luxury Pill) */
.all-monitoring__dock-btn--print {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
}

.all-monitoring__dock-btn--print:hover:not(:disabled) {
    background: #f8fafc;
    color: #0284c7;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
}

.all-monitoring__dock-btn--print:active:not(:disabled) {
    transform: translateY(0);
}

.all-monitoring__dock-btn--print:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: #cbd5e1;
    color: #64748b;
    box-shadow: none;
}

/* Danger Button (Crimson Glow Pill) */
.all-monitoring__dock-btn--danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.45);
}

.all-monitoring__dock-btn--danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.6);
}

.all-monitoring__dock-btn--danger:active:not(:disabled) {
    transform: translateY(0);
}

/* Cancel Button (Frosted Dark Pill) */
.all-monitoring__dock-btn--cancel {
    background: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 0.38rem 0.7rem;
}

.all-monitoring__dock-btn--cancel:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.all-monitoring__dock-btn--cancel:active {
    transform: translateY(0);
}

@media (max-width: 640px) {
    .all-monitoring__floating-dock {
        bottom: 1rem;
        width: calc(100% - 1.25rem);
        max-width: 100%;
        padding: 0.35rem 0.45rem 0.35rem 0.65rem;
    }

    .all-monitoring__dock-inner {
        gap: 0.45rem;
    }

    .all-monitoring__dock-count-label {
        display: none;
    }

    .all-monitoring__dock-btn {
        font-size: 0.75rem;
        padding: 0.32rem 0.6rem;
    }
}

.dock-slide-enter-active,
.dock-slide-leave-active {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}

.dock-slide-enter-from,
.dock-slide-leave-to {
    opacity: 0;
    transform: translate(-50%, 1.5rem);
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Stat Cards Responsive Layout (2x2 on Mobile, Auto on Desktop) */
@media (max-width: 640px) {
    .all-monitoring__stats :deep(.hcis-stat-group) {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem !important;
        width: 100% !important;
    }

    .all-monitoring__stats :deep(.hcis-stat) {
        min-height: 4.85rem !important;
        padding: 0.65rem 0.75rem !important;
        border-radius: 12px !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}

@media (min-width: 641px) and (max-width: 991.98px) {
    .all-monitoring__stats :deep(.hcis-stat-group) {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.65rem !important;
        width: 100% !important;
    }
}
</style>
