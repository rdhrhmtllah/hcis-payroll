<template>
    <section class="pa-kalibrasi" aria-labelledby="pa-kalibrasi-judul">


        <!--
         | TAB PER LEVEL. Orang beda level dituntut tingkat kompetensi berbeda; menjajarkan mereka
         | dalam satu matriks membuat perbandingan menyesatkan. Satu level = satu tab.
         -->
        <!--
         | KONTROL LEVEL & KONTEKS KALIBRASI (Terpadu 3-in-1: Tab Level, Info Multi-Level, & Pengingat Uraian)
         | Menggunakan HcisCard variant="glass" pattern="rings" agar konsisten dengan kartu gradien design system.
         -->
        <HcisCard
            v-if="daftarLevel.length > 1 || pertanyaanNaratif.length"
            variant="glass"
            pattern="rings"
            class="pa-kalibrasi-unified-card"
        >
            <!-- Header Tab Level Jabatan & Aksi Uraian -->
            <div class="pa-unified-panel-top">
                <div v-if="daftarLevel.length > 1" class="pa-unified-panel-tabs" data-uji="kalibrasi-tab-level">
                    <div class="pa-level-capsule-group" role="tablist" aria-label="Pilih Level Jabatan">
                        <button
                            v-for="l in daftarLevel"
                            :key="l.id_level"
                            type="button"
                            role="tab"
                            class="pa-level-capsule-btn"
                            :class="{ 'is-active': levelAktif === l.id_level }"
                            :aria-selected="levelAktif === l.id_level"
                            @click="levelAktif = l.id_level"
                        >
                            <span class="pa-level-capsule-label">{{ l.nama_level }}</span>
                            <span class="pa-level-capsule-badge">{{ l.jumlah }}</span>
                        </button>
                    </div>
                </div>
                <div v-else class="pa-unified-panel-title">
                    <HcisIcon name="sliders" size="2xs" class="pa-text-brand" />
                    <span>Matriks Kalibrasi Skor</span>
                </div>

                <!-- Tombol Cepat Lompat ke Uraian (Bila ada pertanyaan uraian) -->
                <div v-if="pertanyaanNaratif.length" class="pa-unified-panel-uraian-btn">
                    <HcisButton
                        :label="kecil ? `Uraian (${jumlahDilihat}/${pertanyaanNaratif.length})` : `Lihat ${pertanyaanNaratif.length} Uraian (${jumlahDilihat}/${pertanyaanNaratif.length})`"
                        size="xs"
                        variant="ghost"
                        trailing-icon="arrow-down"
                        data-uji="lompat-uraian"
                        @click="keUraian()"
                    />
                </div>
            </div>

            <!-- Divider tipis memisahkan tab dan sub-keterangan di dalam kartu -->
            <div class="pa-unified-card-divider" />

            <!-- Sub-Banner Konteks: Penjelasan Multi-Level Standar & Status Uraian -->
            <div v-if="daftarLevel.length > 1 || pertanyaanNaratif.length" class="pa-unified-panel-sub">
                <div v-if="daftarLevel.length > 1" class="pa-unified-sub-item">
                    <HcisIcon name="info-circle" size="2xs" class="pa-unified-sub-icon" />
                    <span>
                        Tahapan ini menilai <strong>{{ daftarLevel.length }} level jabatan</strong> berbeda (Expected Behaviour & tuntutan tingkat dinilai terpisah). Sedang menampilkan level <strong>{{ levelAktifObj?.nama_level || '' }}</strong> ({{ levelAktifObj?.jumlah || 0 }} orang).
                    </span>
                </div>
                <div v-if="pertanyaanNaratif.length" class="pa-unified-sub-item pa-unified-sub-item--uraian" data-uji="pengingat-uraian">
                    <HcisIcon name="chat-quote" size="2xs" class="pa-unified-sub-icon-gray" />
                    <span>
                        {{
                            pratinjau.kategori?.boleh_kalibrasi
                                ? `Tersedia ${pertanyaanNaratif.length} pertanyaan uraian di bawah tabel skor yang dapat dikalibrasi (sudah dilihat ${jumlahDilihat} dari ${pertanyaanNaratif.length}).`
                                : `Tersedia ${pertanyaanNaratif.length} pertanyaan uraian di bawah tabel skor untuk perbandingan (sudah dilihat ${jumlahDilihat} dari ${pertanyaanNaratif.length}).`
                        }}
                    </span>
                </div>
            </div>
        </HcisCard>

        <!-- Alert messages -->
        <HcisAlert
            v-if="galatSimpan && !dialogTerbuka"
            variant="danger"
            icon="exclamation-octagon-fill"
            :title="galatSimpan.pesan"
            :description="
                galatSimpan.requestId ? `Kode ${galatSimpan.kode || '-'} · Permintaan ${galatSimpan.requestId}` : ''
            "
        />

        <HcisAlert
            v-if="pratinjau.dipangkas"
            variant="warning"
            icon="exclamation-triangle-fill"
            title="Daftar orang dipangkas."
            description="Hanya sebagian orang yang ditampilkan di pratinjau ini untuk menjaga performa."
        />


        <!--
         | ALAT MATRIKS. Kompetensi bisa puluhan: sakelar ini menyembunyikan kompetensi yang
         | skornya sama untuk semua orang, supaya yang tersisa adalah yang perlu dikalibrasi.
         -->
        <div v-if="orangTampil.length > 1" class="pa-matriks-alat" data-uji="matriks-alat">
            <span class="pa-matriks-alat__ringkas">
                {{ jumlahBerbeda }} dari {{ kompetensiTampil.length }} kompetensi skornya berbeda antar orang
            </span>
            <HcisSwitch
                v-model="hanyaBerbeda"
                size="sm"
                active-text="Hanya yang skornya berbeda"
                data-uji="saring-berbeda"
            />
        </div>

        <!-- Desktop Matrix View: Kompetensi × Orang (sticky kiri kompetensi) -->
        <div class="pa-kalibrasi__wadah" role="region" aria-label="Matriks skor per kompetensi dan orang" tabindex="0">
            <table class="pa-kalibrasi__matriks">
                <thead>
                    <tr>
                        <th scope="col" class="pa-kalibrasi__nama pa-col-comp-head">
                            <div class="pa-comp-head-label">
                                <HcisIcon name="diagram-3" size="2xs" />
                                <span>Dimensi Kompetensi</span>
                            </div>
                        </th>
                        <th
                            v-for="o in orangTampil"
                            :key="o.assignment_id"
                            scope="col"
                            class="pa-col-person"
                            :class="{ 'is-col-dirty': adaPerubahan(o) }"
                        >
                            <div class="pa-matrix-person-cell">
                                <div class="pa-matrix-avatar" :class="avatarClass(o)">
                                    <span>{{ inisial(o.peserta?.nama) }}</span>
                                </div>
                                <div class="pa-matrix-person-info">
                                    <div class="pa-matrix-person-name-row">
                                        <strong class="pa-matrix-person-name" :title="o.peserta?.nama">{{ o.peserta?.nama || 'Peserta' }}</strong>
                                        <HcisBadge
                                            v-if="o.diri_sendiri"
                                            label="Diri Sendiri"
                                            variant="brand"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                        <HcisBadge
                                            v-else-if="adaPerubahan(o)"
                                            label="Draf Siap"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                            dot
                                        />
                                    </div>
                                    <small class="pa-matrix-person-sub">
                                        {{ o.diri_sendiri ? 'Penilaian Diri' : [o.peserta?.jabatan, o.peserta?.nama_level, o.peserta?.divisi].filter(Boolean).join(' · ') || 'Staff' }}
                                    </small>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!kelompokKompetensi.length" class="pa-matriks-kosong">
                        <td :colspan="orangTampil.length + 1" data-uji="matriks-kosong">
                            Skor semua kompetensi sama untuk orang-orang ini.
                        </td>
                    </tr>
                    <template v-for="g in kelompokKompetensi" :key="g.kunci">
                    <tr v-if="adaKelompok" class="pa-kelompok-baris">
                        <th :colspan="orangTampil.length + 1" scope="rowgroup" class="pa-kelompok-sel">
                            <button
                                type="button"
                                class="pa-kelompok-tombol"
                                :aria-expanded="kelompokBuka(g)"
                                data-uji="kelompok-kompetensi"
                                @click="aturKelompok(g)"
                            >
                                <HcisIcon :name="kelompokBuka(g) ? 'chevron-down' : 'chevron-right'" size="2xs" />
                                <span class="pa-kelompok-nama">{{ g.nama }}</span>
                                <span class="pa-kelompok-jumlah">{{ g.items.length }} kompetensi</span>
                            </button>
                        </th>
                    </tr>
                    <template v-if="kelompokBuka(g)">
                    <tr v-for="k in g.items" :key="k.competency_id" data-uji="baris-kompetensi">
                        <th scope="row" class="pa-kalibrasi__nama">
                            <div class="pa-comp-head">
                                <div class="pa-comp-name-row">
                                    <span class="pa-comp-name">{{ k.nama }}</span>
                                    <button
                                        v-if="k.deskripsi || k.expected_behaviour"
                                        type="button"
                                        class="pa-btn-comp-info"
                                        :title="`Lihat detail standar & perilaku untuk ${k.nama}`"
                                        :aria-label="`Detail kompetensi ${k.nama}`"
                                        @click="bukaDetailKompetensi(k)"
                                    >
                                        <HcisIcon name="info-circle" size="2xs" />
                                        <span>Detail</span>
                                    </button>
                                </div>
                                <div v-if="k.jenis_nama || k.nama_tingkat" class="pa-comp-meta-row">
                                    <span v-if="k.jenis_nama" class="pa-comp-type">{{ k.jenis_nama }}</span>
                                    <span v-if="k.nama_tingkat" class="pa-comp-tingkat" :title="`Tingkat yang dituntut untuk level ${levelAktifObj?.nama_level || ''}`">L{{ k.tingkat }} · {{ k.nama_tingkat }}</span>
                                </div>
                                <PaKompetensiPenjelasan
                                    v-if="semuaPenjelasan"
                                    :deskripsi="k.deskripsi || ''"
                                    :perilaku="k.expected_behaviour || ''"
                                    :terbuka="semuaPenjelasan"
                                />
                            </div>
                        </th>

                        <!-- Satu kolom per orang yang dinilai -->
                        <td
                            v-for="o in orangTampil"
                            :key="o.assignment_id"
                            :data-uji="`sel-${o.assignment_id}-${k.competency_id}`"
                            class="pa-matrix-score-cell"
                            :class="{
                                'is-terkalibrasi': ditandai(o, k),
                                'is-modified': berubah(o, k),
                                'is-col-dirty': adaPerubahan(o),
                            }"
                        >
                            <template v-if="sel(o, k)">
                                <div class="pa-cell-input-row">
                                    <label v-if="o.boleh_diubah" class="pa-kalibrasi__isian">
                                        <span class="pa-kalibrasi__sr">Skor {{ k.nama }} untuk {{ o.peserta?.nama }}</span>
                                        <HcisInputNumber
                                            :model-value="nilai(o, k)"
                                            :min="0"
                                            :max="sel(o, k).skor_maksimal"
                                            :precision="2"
                                            size="sm"
                                            controls-position="none"
                                            class="pa-score-input"
                                            @update:model-value="(v) => ubah(o, k, v)"
                                        />
                                    </label>
                                    <span v-else class="pa-score-readonly">{{ angka(sel(o, k).skor_efektif) }}</span>

                                    <!-- Quick revert button if changed -->
                                    <button
                                        v-if="o.boleh_diubah && berubah(o, k)"
                                        type="button"
                                        class="pa-btn-revert-cell"
                                        title="Kembalikan skor ke semula"
                                        aria-label="Kembalikan skor ke semula"
                                        @click="revertNilai(o, k)"
                                    >
                                        <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                    </button>
                                </div>

                                <div v-if="ditandai(o, k)" class="pa-score-diff-tag">
                                    <s
                                        class="pa-kalibrasi__asli"
                                        :title="`Skor asli: ${angka(sel(o, k).skor_asli)}`"
                                    >
                                        {{ angka(sel(o, k).skor_asli) }}
                                    </s>
                                    <span v-if="berubah(o, k)" class="pa-score-delta" :class="deltaClass(o, k)">
                                        {{ deltaText(o, k) }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="pa-score-empty">—</span>
                            </template>
                        </td>
                    </tr>
                    </template>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="pa-matrix-footer-row">
                        <th scope="row" class="pa-kalibrasi__nama pa-footer-label">
                            <div class="pa-footer-title">
                                <HcisIcon name="check2-circle" size="2xs" />
                                <span>Aksi Simpan Kalibrasi</span>
                            </div>
                        </th>
                        <td
                            v-for="o in orangTampil"
                            :key="o.assignment_id"
                            class="pa-matrix-save-cell"
                            :class="{ 'is-col-dirty': adaPerubahan(o) }"
                        >
                            <HcisButton
                                v-if="o.boleh_diubah && adaPerubahan(o)"
                                label="Simpan Kalibrasi"
                                size="sm"
                                variant="primary"
                                leading-icon="check2"
                                :loading="menyimpanId === o.assignment_id"
                                :aria-label="`Simpan kalibrasi ${o.peserta?.nama}`"
                                class="pa-btn-save-row is-dirty"
                                @click="bukaAlasan(o)"
                            />
                            <span v-else-if="o.boleh_diubah && o.sel?.some((s) => s.terkalibrasi)" class="pa-status-saved">
                                <HcisIcon name="check-circle-fill" size="2xs" class="pa-text-success" />
                                <span>Terkalibrasi</span>
                            </span>
                            <span v-else-if="o.boleh_diubah" class="pa-status-clean">
                                <HcisIcon name="check2" size="2xs" />
                                <span>Skor Sesuai</span>
                            </span>
                            <span v-else class="pa-score-empty">—</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Mobile Card List View (< 768px) -->
        <ul class="pa-kalibrasi__kartu-daftar">
            <li
                v-for="o in orangTampil"
                :key="o.assignment_id"
                class="pa-kalibrasi__kartu"
                :class="{ 'is-row-dirty': adaPerubahan(o) }"
            >
                <header class="pa-card-person-header">
                    <div class="pa-matrix-avatar" :class="avatarClass(o)">
                        <span>{{ inisial(o.peserta?.nama) }}</span>
                    </div>
                    <div class="pa-wizard-lembar__orang">
                        <div class="pa-matrix-person-name-row">
                            <strong class="pa-person-name">{{ o.peserta?.nama }}</strong>
                            <HcisBadge
                                v-if="o.diri_sendiri"
                                label="Diri Sendiri"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                        <small class="pa-wizard-lembar__sub">
                            {{ o.diri_sendiri ? 'Penilaian Diri' : [o.peserta?.jabatan, o.peserta?.nama_level, o.peserta?.divisi].filter(Boolean).join(' · ') || 'Staff' }}
                        </small>
                    </div>
                    <HcisBadge
                        v-if="adaPerubahan(o)"
                        label="Draf Siap"
                        variant="warning"
                        appearance="glass"
                        size="xs"
                        dot
                    />
                </header>

                <dl class="pa-card-score-list">
                    <div v-if="!kelompokKompetensi.length" class="pa-matriks-kosong">
                        Skor semua kompetensi sama untuk orang-orang ini.
                    </div>
                    <template v-for="g in kelompokKompetensi" :key="g.kunci">
                    <div v-if="adaKelompok" class="pa-kelompok-kartu">
                        <button
                            type="button"
                            class="pa-kelompok-tombol"
                            :aria-expanded="kelompokBuka(g)"
                            @click="aturKelompok(g)"
                        >
                            <HcisIcon :name="kelompokBuka(g) ? 'chevron-down' : 'chevron-right'" size="2xs" />
                            <span class="pa-kelompok-nama">{{ g.nama }}</span>
                            <span class="pa-kelompok-jumlah">{{ g.items.length }}</span>
                        </button>
                    </div>
                    <template v-for="k in kelompokBuka(g) ? g.items : []" :key="k.competency_id">
                        <div
                            v-if="sel(o, k)"
                            class="pa-kalibrasi__baris"
                            :class="{ 'is-terkalibrasi': ditandai(o, k), 'is-modified': berubah(o, k) }"
                        >
                            <dt class="pa-card-score-label">
                                <div class="pa-comp-name-row">
                                    <span class="pa-card-comp-name">{{ k.nama }}</span>
                                    <button
                                        v-if="k.deskripsi || k.expected_behaviour"
                                        type="button"
                                        class="pa-btn-comp-info"
                                        :title="`Lihat detail standar & perilaku untuk ${k.nama}`"
                                        :aria-label="`Detail kompetensi ${k.nama}`"
                                        @click="bukaDetailKompetensi(k)"
                                    >
                                        <HcisIcon name="info-circle" size="2xs" />
                                    </button>
                                </div>
                                <div v-if="k.nama_tingkat" class="pa-comp-meta-row">
                                    <span class="pa-comp-tingkat">L{{ k.tingkat }} · {{ k.nama_tingkat }}</span>
                                </div>
                            </dt>
                            <dd class="pa-card-score-control">
                                <div class="pa-cell-input-row">
                                    <label v-if="o.boleh_diubah" class="pa-kalibrasi__isian">
                                        <span class="pa-kalibrasi__sr">Skor {{ k.nama }} untuk {{ o.peserta?.nama }}</span>
                                        <HcisInputNumber
                                            :model-value="nilai(o, k)"
                                            :min="0"
                                            :max="sel(o, k).skor_maksimal"
                                            :precision="2"
                                            size="sm"
                                            controls-position="none"
                                            @update:model-value="(v) => ubah(o, k, v)"
                                        />
                                    </label>
                                    <span v-else class="pa-score-readonly">{{ angka(sel(o, k).skor_efektif) }}</span>

                                    <button
                                        v-if="o.boleh_diubah && berubah(o, k)"
                                        type="button"
                                        class="pa-btn-revert-cell"
                                        title="Kembalikan skor"
                                        @click="revertNilai(o, k)"
                                    >
                                        <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                    </button>
                                </div>

                                <div v-if="ditandai(o, k)" class="pa-score-diff-tag">
                                    <s class="pa-kalibrasi__asli">{{ angka(sel(o, k).skor_asli) }}</s>
                                    <span v-if="berubah(o, k)" class="pa-score-delta" :class="deltaClass(o, k)">
                                        {{ deltaText(o, k) }}
                                    </span>
                                </div>
                            </dd>
                        </div>
                    </template>
                    </template>
                </dl>

                <HcisButton
                    v-if="o.boleh_diubah"
                    label="Simpan Kalibrasi"
                    block
                    variant="primary"
                    leading-icon="check2"
                    :disabled="!adaPerubahan(o)"
                    :loading="menyimpanId === o.assignment_id"
                    class="pa-btn-mobile-save"
                    @click="bukaAlasan(o)"
                />
            </li>
        </ul>

        <!--
         | URAIAN PER PERTANYAAN. Jawaban semua orang untuk pertanyaan yang sama dijajarkan dalam
         | satu baris (kolom orang = urutan matriks skor), supaya bisa dibandingkan tanpa membuka
         | jawaban orang satu per satu. Di layar sempit barisnya menumpuk per pertanyaan.
         -->
        <div ref="jangkarUraian" class="pa-uraian-jangkar">
        <HcisCard
            v-if="adaNaratif"
            title="Jawaban Naratif &amp; Uraian Kualitatif"
            :description="
                pratinjau.kategori?.boleh_kalibrasi
                    ? 'Jawaban dijajarkan per pertanyaan agar mudah dibandingkan. Anda boleh merevisinya — tidak boleh dikosongkan — dan jawaban asli tetap tercatat.'
                    : 'Jawaban dijajarkan per pertanyaan agar mudah dibandingkan antar orang.'
            "
            icon="chat-quote-fill"
            sentiment="purple"
            variant="glass"
            pattern="rings"
            class="pa-kalibrasi__naratif"
        >
            <template #actions>
                <div class="pa-naratif-card-actions">
                    <HcisBadge
                        v-if="totalRevisiNaratif > 0"
                        :label="`${totalRevisiNaratif} draf revisi`"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        leading-icon="pencil-fill"
                    />
                    <HcisButton
                        v-if="pertanyaanNaratif.length > 1"
                        :label="semuaPertanyaanTerbuka ? 'Tutup semua' : 'Buka semua'"
                        variant="secondary"
                        size="sm"
                        :leading-icon="semuaPertanyaanTerbuka ? 'chevron-contract' : 'chevron-expand'"
                        data-uji="naratif-toggle-semua"
                        @click="aturSemuaPertanyaan(!semuaPertanyaanTerbuka)"
                    />
                </div>
            </template>
            <div class="pa-naratif-wadah" role="region" aria-label="Perbandingan uraian per pertanyaan" tabindex="0" data-uji="naratif-matriks">
                <div class="pa-naratif-grid" :style="{ '--pa-naratif-kolom': orangBernaratif.length }">
                    <div class="pa-naratif-baris pa-naratif-baris--kepala" aria-hidden="true">
                        <div v-for="o in orangBernaratif" :key="o.assignment_id" class="pa-naratif-sel pa-naratif-sel--kepala">
                            <div class="pa-matrix-avatar" :class="avatarClass(o)">
                                <span>{{ inisial(o.peserta?.nama) }}</span>
                            </div>
                            <div class="pa-matrix-person-info">
                                <strong class="pa-matrix-person-name" :title="o.peserta?.nama">{{ o.peserta?.nama || 'Peserta' }}</strong>
                                <small class="pa-matrix-person-sub">
                                    {{ o.diri_sendiri ? 'Penilaian Diri' : [o.peserta?.jabatan, o.peserta?.nama_level].filter(Boolean).join(' · ') || 'Staff' }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <section
                        v-for="(p, i) in pertanyaanNaratif"
                        :key="p.kunci"
                        class="pa-naratif-baris"
                        :class="{ 'is-terbuka': pertanyaanBuka(p, i) }"
                        :data-kunci="p.kunci"
                        :ref="amatiPertanyaan"
                        data-uji="naratif-baris"
                    >
                        <!-- Kepala accordion selebar tabel: soal tetap yang paling menonjol. -->
                        <div class="pa-naratif-tanya">
                            <button
                                type="button"
                                class="pa-naratif-question pa-naratif-tanya__kepala"
                                :aria-expanded="pertanyaanBuka(p, i) ? 'true' : 'false'"
                                data-uji="naratif-toggle"
                                @click="aturPertanyaan(p, !pertanyaanBuka(p, i))"
                            >
                                <span class="pa-naratif-tanya__isi">
                                    <span class="pa-naratif-tanya__baris-atas">
                                        <span class="pa-naratif-tanya__nomor-pill">
                                            Q{{ i + 1 }}
                                        </span>
                                        <span v-if="p.contoh.per_soal && p.contoh.nama" class="pa-naratif-comp-badge">
                                            {{ p.contoh.nama }}
                                        </span>
                                        <span
                                            v-if="p.nama_tingkat"
                                            class="pa-naratif-level-badge"
                                            :title="`Tingkat yang dituntut untuk level ${levelAktifObj?.nama_level || ''}`"
                                        >
                                            L{{ p.tingkat }} · {{ p.nama_tingkat }}
                                        </span>
                                        <button
                                            v-if="p.expected_behaviour"
                                            type="button"
                                            class="pa-btn-comp-info pa-btn-comp-info--naratif"
                                            :title="`Lihat standar perilaku untuk ${p.contoh?.nama || 'pertanyaan ini'}`"
                                            :aria-label="`Detail standar perilaku untuk ${p.contoh?.nama || 'pertanyaan ini'}`"
                                            @click.stop="bukaDetailNaratif(p)"
                                        >
                                            <HcisIcon name="info-circle" size="2xs" />
                                            <span>Perilaku</span>
                                        </button>
                                    </span>
                                    <h4
                                        class="pa-naratif-question-text"
                                        :class="{ 'is-terpotong': !pertanyaanBuka(p, i) }"
                                        data-uji="naratif-pertanyaan"
                                    >{{ teksPertanyaan(p) }}</h4>
                                </span>
                                <span class="pa-naratif-tanya__ringkas">
                                    <span class="pa-naratif-jawab-count">{{ Object.keys(p.jawaban).length }} jawaban</span>
                                    <HcisBadge
                                        v-if="jumlahDirevisi(p)"
                                        :label="`${jumlahDirevisi(p)} direvisi`"
                                        variant="warning"
                                        appearance="subtle"
                                        size="xs"
                                        data-uji="naratif-direvisi"
                                    />
                                    <HcisBadge
                                        v-if="!sudahDilihat(p)"
                                        label="Belum dilihat"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                        data-uji="naratif-belum-dilihat"
                                    />
                                    <HcisIcon name="chevron-down" size="xs" :class="['pa-naratif-tanya__panah', { 'is-terbuka': pertanyaanBuka(p, i) }]" />
                                </span>
                            </button>
                        </div>

                        <template v-if="pertanyaanBuka(p, i)">
                        <!-- Perilaku dipisah dari soal dan dilipat di desktop; di mobile bisa dibuka via modal info agar ringkas. -->
                        <div v-if="p.expected_behaviour" class="pa-naratif-tanya pa-naratif-tanya--perilaku" data-uji="naratif-perilaku">
                            <PaKompetensiPenjelasan deskripsi="" :perilaku="p.expected_behaviour" :terbuka="semuaPenjelasan" />
                        </div>

                        <div
                            v-for="o in orangBernaratif"
                            :key="o.assignment_id"
                            class="pa-naratif-sel"
                            :class="{
                                'is-terkalibrasi': p.jawaban[o.assignment_id] && (p.jawaban[o.assignment_id].terkalibrasi || esaiBerubah(o, p.jawaban[o.assignment_id])),
                                'is-kosong': !p.jawaban[o.assignment_id],
                            }"
                            :data-uji="`naratif-sel-${o.assignment_id}`"
                        >
                            <!-- Nama orang hanya tampil saat baris menumpuk (layar sempit). -->
                            <div class="pa-naratif-sel__orang">
                                <div class="pa-matrix-avatar pa-naratif-sel__avatar" :class="avatarClass(o)">
                                    <span>{{ inisial(o.peserta?.nama) }}</span>
                                </div>
                                <div class="pa-naratif-sel__orang-info">
                                    <strong class="pa-naratif-sel__orang-nama">{{ o.peserta?.nama || 'Peserta' }}</strong>
                                    <small class="pa-naratif-sel__orang-jabatan">
                                        {{ o.diri_sendiri ? 'Penilaian Diri' : [o.peserta?.jabatan, o.peserta?.nama_level].filter(Boolean).join(' · ') || 'Staff' }}
                                    </small>
                                </div>
                            </div>

                            <template v-if="p.jawaban[o.assignment_id]">
                                <!-- Status badge & header bar untuk sel editable jika ada perubahan / terkalibrasi -->
                                <div
                                    v-if="o.boleh_diubah && bisaDiubah(p.jawaban[o.assignment_id]) && (esaiBerubah(o, p.jawaban[o.assignment_id]) || p.jawaban[o.assignment_id].terkalibrasi)"
                                    class="pa-naratif-sel__status-bar"
                                >
                                    <span v-if="esaiBerubah(o, p.jawaban[o.assignment_id])" class="pa-naratif-badge-edit">
                                        <HcisIcon name="pencil-fill" size="2xs" />
                                        <span>Draf Diubah</span>
                                    </span>
                                    <span v-else-if="p.jawaban[o.assignment_id].terkalibrasi" class="pa-naratif-badge-calibrated">
                                        <HcisIcon name="check-circle-fill" size="2xs" />
                                        <span>Terkalibrasi</span>
                                    </span>

                                    <!-- Quick revert button bila ada perubahan draf -->
                                    <button
                                        v-if="esaiBerubah(o, p.jawaban[o.assignment_id])"
                                        type="button"
                                        class="pa-btn-revert-naratif"
                                        title="Kembalikan uraian ke semula"
                                        aria-label="Kembalikan uraian ke semula"
                                        @click="revertEsai(o, p.jawaban[o.assignment_id])"
                                    >
                                        <HcisIcon name="arrow-counterclockwise" size="2xs" />
                                        <span>Reset</span>
                                    </button>
                                </div>

                                <div v-if="o.boleh_diubah && bisaDiubah(p.jawaban[o.assignment_id])" class="pa-naratif-answer is-editable">
                                    <HcisRichText
                                        :id="`pa-esai-${o.assignment_id}-${idEsai(p.jawaban[o.assignment_id])}`"
                                        :label="`Jawaban untuk ${o.peserta?.nama}`"
                                        :model-value="teksEsai(o, p.jawaban[o.assignment_id])"
                                        :toolbar="TOOLBAR_ESAI"
                                        min-height="110px"
                                        max-height="280px"
                                        :hint="`Maks. ${PANJANG_ESAI.toLocaleString('id-ID')} karakter.`"
                                        :error="esaiKosong(o, p.jawaban[o.assignment_id]) ? 'Jawaban tidak boleh dikosongkan.' : ''"
                                        :data-uji="`esai-${o.assignment_id}-${idEsai(p.jawaban[o.assignment_id])}`"
                                        @update:model-value="(v) => ubahEsai(o, p.jawaban[o.assignment_id], v)"
                                    />
                                </div>
                                <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                                <div v-else class="pa-naratif-answer is-html" v-html="htmlAman(p.jawaban[o.assignment_id].jawaban_html || p.jawaban[o.assignment_id].jawaban)" />

                                <!-- Editor footer: word count badge & status -->
                                <div class="pa-naratif-sel__kaki">
                                    <span class="pa-naratif-char-info">
                                        {{ jumlahKata(o, p.jawaban[o.assignment_id]) }} kata
                                    </span>
                                    <HcisBadge
                                        v-if="jumlahKata(o, p.jawaban[o.assignment_id]) < KATA_SINGKAT"
                                        label="Singkat"
                                        variant="warning"
                                        appearance="subtle"
                                        size="xs"
                                        title="Uraian kualitatif relatif sangat singkat"
                                        data-uji="naratif-kata"
                                    />
                                </div>

                                <div
                                    v-if="(p.jawaban[o.assignment_id].terkalibrasi || esaiBerubah(o, p.jawaban[o.assignment_id])) && p.jawaban[o.assignment_id].jawaban_asli"
                                    class="pa-naratif-asli-box"
                                >
                                    <div class="pa-naratif-asli-header">
                                        <HcisIcon name="clock-history" size="2xs" />
                                        <span>Jawaban Asli Penilai:</span>
                                    </div>
                                    <div class="pa-naratif-asli-content">
                                        <s>{{ p.jawaban[o.assignment_id].jawaban_asli }}</s>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="pa-naratif-empty-state">
                                <HcisIcon name="dash-circle" size="xs" class="pa-text-muted" />
                                <span>Tidak ada uraian</span>
                            </div>
                        </div>
                        </template>
                    </section>
                </div>
            </div>
        </HcisCard>
        </div>

        <!--
         | SATU BAR AKSI di dasar layar. Isinya berganti menurut keadaan:
         |  - bersih  : Kembali ke Lembar · Lanjut ke Level berikutnya · Lanjut;
         |  - ada draf: status draf · Batalkan draf · Simpan Kalibrasi.
         | Tombol Lanjut sengaja tidak tampil selagi ada draf: kalibrasi wajib disimpan dulu.
         -->
        <div class="pa-fixed-dock-container">
            <div class="pa-fixed-dock-wrap">
                <footer
                    class="pa-fixed-dock__nav-bar"
                    :class="{ 'is-draf': adaDrafBar }"
                    :role="adaDrafBar ? 'status' : undefined"
                    :aria-live="adaDrafBar ? 'polite' : undefined"
                    data-uji="bar-aksi"
                >
                    <template v-if="adaDrafBar">
                        <div class="pa-fixed-dock__status">
                            <HcisIcon name="exclamation-circle-fill" size="xs" class="pa-fixed-dock__status-ikon" />
                            <span>
                                <strong>{{ orangKotorCount }} peserta belum disimpan.</strong>
                                <span class="pa-fixed-dock__status-sub">Simpan kalibrasi dulu sebelum melanjutkan.</span>
                            </span>
                        </div>
                        <div class="pa-fixed-dock__nav-right">
                            <HcisButton
                                label="Batalkan draf"
                                variant="secondary"
                                leading-icon="arrow-counterclockwise"
                                data-uji="bar-batal-draf"
                                @click="revertSemua"
                            />
                            <HcisButton
                                :label="orangKotorCount === 1 ? 'Simpan Kalibrasi' : `Simpan Kalibrasi (${orangKotorCount})`"
                                variant="primary"
                                leading-icon="check2"
                                :loading="menyimpanId !== 0"
                                data-uji="bar-simpan"
                                @click="bukaAlasanSemua"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <HcisButton
                            :label="kecil ? '' : 'Kembali ke Lembar'"
                            aria-label="Kembali ke Lembar"
                            title="Kembali ke Lembar"
                            variant="secondary"
                            leading-icon="arrow-left"
                            class="pa-btn-back"
                            @click="emit('ke-lembar')"
                        />
                        <HcisButton
                            v-if="pertanyaanNaratif.length"
                            :label="kecil ? `Uraian ${jumlahDilihat}/${pertanyaanNaratif.length}` : `Uraian dilihat ${jumlahDilihat}/${pertanyaanNaratif.length}`"
                            variant="ghost"
                            :leading-icon="jumlahDilihat === pertanyaanNaratif.length ? 'check2-circle' : 'chat-quote'"
                            class="pa-btn-uraian"
                            data-uji="bar-uraian"
                            @click="keUraian()"
                        />
                        <div class="pa-fixed-dock__nav-right">
                            <HcisButton
                                v-if="levelBerikutnya"
                                :label="kecil ? labelNextLevelMobile : `Lanjut ke Level ${levelBerikutnya.nama_level}`"
                                :title="`Lanjut ke Level ${levelBerikutnya.nama_level}`"
                                variant="secondary"
                                trailing-icon="arrow-right"
                                data-uji="lanjut-level"
                                class="pa-btn-next-level"
                                @click="cobaPindah(() => pindahKeLevel(levelBerikutnya.id_level), [levelAktif])"
                            />
                            <HcisButton
                                :label="kecil ? labelLanjutMobile : labelLanjut"
                                :title="labelLanjut"
                                variant="primary"
                                trailing-icon="arrow-right"
                                data-uji="lanjut"
                                class="pa-btn-forward"
                                @click="cobaPindah(() => emit('lanjut'))"
                            />
                        </div>
                    </template>
                </footer>
            </div>
        </div>

        <!-- Calibration Reason Dialog Modal -->
        <HcisDialog
            v-model="dialogTerbuka"
            :title="orangDialogs.length > 1 ? `Simpan Kalibrasi — ${orangDialogs.length} peserta` : `Alasan Kalibrasi — ${orangDialog?.peserta?.nama ?? ''}`"
            :size="kecil ? 'full' : 'md'"
        >
            <div class="pa-dialog-content-body">
                <HcisAlert
                    v-if="galatSimpan"
                    variant="danger"
                    icon="exclamation-octagon-fill"
                    :title="galatSimpan.pesan"
                />

                <HcisAlert
                    v-if="galatEsai"
                    variant="danger"
                    icon="exclamation-octagon-fill"
                    :title="galatEsai"
                />

                <!-- Info Banner jika menyimpan lebih dari 1 peserta -->
                <div v-if="orangDialogs.length > 1" class="pa-dialog-batch-banner" data-uji="dialog-banyak">
                    <div class="pa-dialog-batch-icon">
                        <HcisIcon name="info-circle-fill" size="xs" />
                    </div>
                    <div class="pa-dialog-batch-content">
                        <strong>Simpan Massal ({{ orangDialogs.length }} Peserta)</strong>
                        <span>
                            Satu alasan kalibrasi ini akan berlaku untuk seluruh peserta terpilih. Penyimpanan diproses berurutan secara aman (transaksional per orang).
                        </span>
                    </div>
                </div>

                <!-- Daftar Peserta & Ringkasan Perubahan -->
                <div class="pa-dialog-participants-list">
                    <details
                        v-for="od in orangDialogs"
                        :key="od.assignment_id"
                        class="pa-dialog-orang"
                        :open="orangDialogs.length === 1"
                        data-uji="dialog-orang"
                    >
                        <summary class="pa-dialog-orang__kepala">
                            <div class="pa-dialog-orang__identitas">
                                <div class="pa-matrix-avatar pa-dialog-orang__avatar" :class="avatarClass(od)">
                                    <span>{{ inisial(od.peserta?.nama) }}</span>
                                </div>
                                <div class="pa-dialog-orang__nama-wrap">
                                    <strong class="pa-dialog-orang__nama">{{ od.peserta?.nama }}</strong>
                                    <small class="pa-dialog-orang__sub">{{ [od.peserta?.jabatan, od.peserta?.nama_level].filter(Boolean).join(' · ') || 'Staff' }}</small>
                                </div>
                            </div>
                            <div class="pa-dialog-orang__pills">
                                <span v-if="kompetensiBerubah(od).length" class="pa-dialog-pill pa-dialog-pill--skor">
                                    <HcisIcon name="sliders" size="2xs" />
                                    <span>{{ kompetensiBerubah(od).length }} skor</span>
                                </span>
                                <span v-if="naratifBerubah(od).length" class="pa-dialog-pill pa-dialog-pill--uraian">
                                    <HcisIcon name="chat-quote" size="2xs" />
                                    <span>{{ naratifBerubah(od).length }} uraian</span>
                                </span>
                                <HcisIcon name="chevron-down" size="xs" class="pa-dialog-orang__panah" />
                            </div>
                        </summary>

                        <div class="pa-dialog-orang__detail">
                            <!-- Ringkasan Skor Kompetensi -->
                            <div v-if="od && kompetensiBerubah(od).length" class="pa-dialog-recap-box">
                                <div class="pa-dialog-recap-header">
                                    <HcisIcon name="sliders" size="2xs" />
                                    <span>Penyesuaian Skor Kompetensi</span>
                                </div>
                                <ul class="pa-kalibrasi__ringkas">
                                    <li v-for="k in kompetensiBerubah(od)" :key="k.competency_id" class="pa-recap-item">
                                        <span class="pa-recap-name">{{ k.nama }}</span>
                                        <div class="pa-recap-scores">
                                            <span class="pa-recap-old">{{ angka(sel(od, k)?.skor_efektif) }}</span>
                                            <HcisIcon name="arrow-right" size="2xs" class="pa-text-muted" />
                                            <strong class="pa-recap-new">{{ angka(draf[od.assignment_id]?.[k.competency_id]) }}</strong>
                                            <span class="pa-score-delta" :class="deltaClass(od, k)">
                                                {{ deltaText(od, k) }}
                                            </span>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <!-- Ringkasan Uraian Naratif -->
                            <div v-if="od && naratifBerubah(od).length" class="pa-dialog-recap-box">
                                <div class="pa-dialog-recap-header">
                                    <HcisIcon name="chat-quote" size="2xs" />
                                    <span>Uraian Naratif yang Disesuaikan</span>
                                </div>
                                <ul class="pa-kalibrasi__ringkas">
                                    <li v-for="n in naratifBerubah(od)" :key="kunciEsai(n)" class="pa-recap-item pa-recap-item--naratif">
                                        <span class="pa-recap-name">{{ (n.per_soal && n.pertanyaan) || n.nama || 'Pertanyaan Uraian' }}</span>
                                        <span class="pa-dialog-badge-revisi">Telah direvisi</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Input Textarea Alasan -->
                <div class="pa-dialog-textarea-wrap">
                    <HcisTextarea
                        id="pa-kalibrasi-alasan"
                        v-model="alasan"
                        label="Alasan Perubahan Skor / Uraian"
                        :maxlength="500"
                        :rows="3"
                        required
                        placeholder="Contoh: Setelah membandingkan kontribusi antar rekan kerja pada project Q3, skor disesuaikan agar lebih proporsional."
                        hint="Wajib diisi secara objektif untuk kepatuhan audit komite evaluasi."
                        :error="galatAlasan"
                    />
                </div>
            </div>

            <template #footer>
                <div class="pa-kalibrasi__aksi-dialog">
                    <HcisButton label="Batal" variant="secondary" @click="dialogTerbuka = false" />
                    <HcisButton
                        :label="progresSimpan ? `Menyimpan ${progresSimpan.ke} dari ${progresSimpan.total}…` : orangDialogs.length > 1 ? `Simpan ${orangDialogs.length} Peserta` : 'Simpan Kalibrasi'"
                        variant="primary"
                        leading-icon="check-circle-fill"
                        :loading="menyimpanId !== 0"
                        data-uji="dialog-simpan"
                        @click="kirimSimpan"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog Detail Standar & Perilaku Kompetensi -->
        <HcisDialog
            v-model="dialogDetailKompetensiTerbuka"
            :title="kompetensiTerpilih?.nama || 'Detail Kompetensi'"
            :size="kecil ? 'full' : 'md'"
        >
            <div v-if="kompetensiTerpilih" class="pa-dialog-comp-detail">
                <div class="pa-dialog-comp-tags">
                    <span v-if="kompetensiTerpilih.jenis_nama" class="pa-comp-type">
                        {{ kompetensiTerpilih.jenis_nama }}
                    </span>
                    <span v-if="kompetensiTerpilih.nama_tingkat" class="pa-comp-tingkat">
                        L{{ kompetensiTerpilih.tingkat }} · {{ kompetensiTerpilih.nama_tingkat }}
                    </span>
                </div>

                <div v-if="kompetensiTerpilih.deskripsi" class="pa-dialog-comp-section">
                    <label class="pa-dialog-comp-label">
                        <HcisIcon name="card-text" size="2xs" />
                        <span>Definisi & Arti Kompetensi</span>
                    </label>
                    <p class="pa-dialog-comp-text">
                        {{ kompetensiTerpilih.deskripsi }}
                    </p>
                </div>

                <div v-if="kompetensiTerpilih.expected_behaviour" class="pa-dialog-comp-section">
                    <label class="pa-dialog-comp-label">
                        <HcisIcon name="person-check" size="2xs" />
                        <span>Standar Perilaku yang Diharapkan (Expected Behaviour)</span>
                    </label>
                    <PaDaftarPerilaku :teks="kompetensiTerpilih.expected_behaviour" judul="" class="pa-dialog-comp-perilaku" />
                </div>
            </div>

            <template #footer>
                <div class="pa-kalibrasi__aksi-dialog">
                    <HcisButton label="Tutup" variant="secondary" @click="dialogDetailKompetensiTerbuka = false" />
                </div>
            </template>
        </HcisDialog>

        <!-- Uraian yang belum dilihat saat penilai menekan Lanjut: diingatkan, tidak dipaksa. -->
        <HcisDialog
            v-model="dialogUraianTerbuka"
            title="Pengingat Uraian"
            size="sm"
        >
            <div class="pa-dialog-uraian" data-uji="dialog-uraian">
                <div class="pa-dialog-uraian__hero">
                    <div class="pa-dialog-uraian__icon-bubble">
                        <HcisIcon name="chat-square-text-fill" size="md" />
                    </div>
                    <div class="pa-dialog-uraian__hero-content">
                        <h4 class="pa-dialog-uraian__hero-title">Uraian Belum Dilihat</h4>
                        <p class="pa-dialog-uraian__hero-desc">
                            Ada <strong class="pa-text-warning-highlight">{{ uraianTertunda.length }} pertanyaan uraian</strong> yang belum Anda lihat.
                            <span v-if="pratinjau.kategori?.boleh_kalibrasi" class="pa-dialog-uraian__hint">
                                Jawabannya juga dapat Anda tinjau & kalibrasi sebelum lanjut.
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="daftarLevel.length > 1 && ringkasTertunda.length" class="pa-dialog-uraian__levels-section">
                    <div class="pa-dialog-uraian__section-label">
                        <span>Rincian Berdasarkan Level</span>
                    </div>
                    <div class="pa-dialog-uraian__levels-grid">
                        <div
                            v-for="r in ringkasTertunda"
                            :key="r.id_level"
                            class="pa-dialog-uraian__level-item"
                        >
                            <div class="pa-dialog-uraian__level-info">
                                <span class="pa-dialog-uraian__level-badge">{{ r.nama_level }}</span>
                            </div>
                            <div class="pa-dialog-uraian__level-count">
                                <strong>{{ r.jumlah }}</strong>
                                <span>pertanyaan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="pa-kalibrasi__aksi-dialog pa-dialog-uraian__actions">
                    <HcisButton
                        label="Tetap Lanjut"
                        variant="secondary"
                        data-uji="uraian-tetap-lanjut"
                        class="pa-dialog-uraian__btn-secondary"
                        @click="tetapLanjut"
                    />
                    <HcisButton
                        label="Tinjau Uraian"
                        variant="primary"
                        trailing-icon="arrow-down"
                        data-uji="uraian-lihat"
                        class="pa-dialog-uraian__btn-primary"
                        @click="lihatTertunda"
                    />
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisIcon,
    HcisInputNumber,
    HcisRichText,
    HcisSwitch,
    HcisTabs,
    HcisTextarea,
} from '@/components/ui';

import { htmlAman, teksDariHtml } from '../../utils/htmlAman.js';
import PaKompetensiPenjelasan from '../../components/PaKompetensiPenjelasan.vue';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';
import { angka } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaWizardPratinjauKalibrasi' });

const props = defineProps({
    pratinjau: { type: Object, required: true },
    menyimpanId: { type: Number, default: 0 },
    progresSimpan: { type: Object, default: null },
    galatSimpan: { type: Object, default: null },
    labelLanjut: { type: String, default: 'Lanjut' },
});

/* Modal Detail Kompetensi (Deskripsi & Expected Behaviour) */
const dialogDetailKompetensiTerbuka = ref(false);
const kompetensiTerpilih = ref(null);

function bukaDetailKompetensi(k) {
    kompetensiTerpilih.value = k;
    dialogDetailKompetensiTerbuka.value = true;
}

function bukaDetailNaratif(p) {
    kompetensiTerpilih.value = {
        nama: p.contoh?.nama || 'Standar Perilaku Soal',
        jenis_nama: 'Uraian Kualitatif',
        tingkat: p.tingkat,
        nama_tingkat: p.nama_tingkat,
        deskripsi: p.contoh?.pertanyaan || '',
        expected_behaviour: p.expected_behaviour,
    };
    dialogDetailKompetensiTerbuka.value = true;
}

/* Deskripsi & expected behaviour kompetensi (DDL-2026-166): ringkas secara bawaan supaya
   matriks tetap rapat; satu tombol membuka semuanya untuk rater yang ingin membaca dulu. */
const semuaPenjelasan = ref(false);
const adaPenjelasan = computed(() => kompetensiTampil.value.some((k) => k.deskripsi || k.expected_behaviour));

/* Label tombol aksi navigasi: dirampingkan di layar kecil/mobile agar tidak overflow */
const labelLanjutMobile = computed(() => {
    if (!kecil.value) return props.labelLanjut;
    const l = props.labelLanjut || 'Lanjut';
    if (l.includes('Ringkasan')) return 'Ringkasan';
    if (l.startsWith('Lanjut ke ')) return l.replace('Lanjut ke ', '');
    return l;
});

const labelNextLevelMobile = computed(() => {
    if (!levelBerikutnya.value) return '';
    return levelBerikutnya.value.nama_level;
});

/* Tab per level (server mengirim `level`: jumlah, rata-rata per kompetensi, dan perilaku
   tingkat yang dituntut). Respons lama tanpa `level` = satu tab berisi semua orang. */
const daftarLevel = computed(() => props.pratinjau?.level || []);
const levelAktif = ref(null);
const levelAktifObj = computed(() => daftarLevel.value.find((l) => l.id_level === levelAktif.value) || null);

const tabLevelList = computed(() => {
    return daftarLevel.value.map((l) => ({
        key: String(l.id_level),
        label: l.nama_level,
        badge: `${l.jumlah} orang`,
        badgeVariant: 'neutral',
    }));
});

const tabLevelAktif = computed({
    get: () => String(levelAktif.value ?? ''),
    set: (v) => {
        levelAktif.value = v === '' ? null : Number(v);
    },
});

watch(daftarLevel, (lv) => {
    if (!lv.some((l) => l.id_level === levelAktif.value)) levelAktif.value = lv[0]?.id_level ?? null;
}, { immediate: true });

const orangTampil = computed(() => {
    const semua = props.pratinjau?.orang || [];
    if (daftarLevel.value.length <= 1 || levelAktif.value === null) return semua;
    return semua.filter((o) => (o.peserta?.id_level ?? 0) === levelAktif.value);
});

const kompetensiTampil = computed(() => {
    const ada = new Set(orangTampil.value.flatMap((o) => (o.sel || []).map((x) => x.competency_id)));
    const lv = levelAktifObj.value;
    // Server mengirim daftar ber-competency_id; dibaca sebagai peta di sini.
    const perilakuLv = Object.fromEntries((lv?.perilaku || []).map((p) => [p.competency_id, p]));
    const rataLv = Object.fromEntries((lv?.rata_rata || []).map((r) => [r.competency_id, r.nilai]));
    return (props.pratinjau?.kompetensi || [])
        // Disaring hanya bila ada lebih dari satu tab: satu level = seluruh kompetensi apa adanya.
        .filter((k) => daftarLevel.value.length <= 1 || ada.has(k.competency_id))
        .map((k) => {
            const p = perilakuLv[k.competency_id];
            return {
                ...k,
                rata_rata: rataLv[k.competency_id] ?? k.rata_rata,
                expected_behaviour: p?.expected_behaviour || k.expected_behaviour,
                tingkat: p?.tingkat ?? null,
                nama_tingkat: p?.nama_tingkat ?? null,
            };
        });
});

/* Saringan "hanya yang berbeda" dihitung dari skor tersimpan (bukan draf), supaya baris tidak
   menghilang di tengah penyuntingan; baris yang punya draf selalu tetap tampil. */
const hanyaBerbeda = ref(false);

function skorBerbeda(k) {
    const skor = orangTampil.value
        .map((o) => sel(o, k))
        .filter(Boolean)
        .map((s) => Math.round(Number(s.skor_efektif) * 100));
    return new Set(skor).size > 1;
}

const jumlahBerbeda = computed(() => kompetensiTampil.value.filter(skorBerbeda).length);

const kompetensiTersaring = computed(() =>
    hanyaBerbeda.value && orangTampil.value.length > 1
        ? kompetensiTampil.value.filter((k) => skorBerbeda(k) || orangTampil.value.some((o) => berubah(o, k)))
        : kompetensiTampil.value,
);

/* Baris dikelompokkan per jenis kompetensi (urutan dari server) dan tiap kelompok bisa dilipat.
   Judul kelompok hanya tampil bila jenisnya lebih dari satu. */
const JENIS_LAIN = 'Lainnya';

const adaKelompok = computed(() => new Set(kompetensiTampil.value.map((k) => k.jenis_nama || JENIS_LAIN)).size > 1);

const kelompokKompetensi = computed(() => {
    const peta = new Map();
    for (const k of kompetensiTersaring.value) {
        const nama = k.jenis_nama || JENIS_LAIN;
        if (!peta.has(nama)) peta.set(nama, { kunci: nama, nama, items: [] });
        peta.get(nama).items.push(k);
    }
    return [...peta.values()];
});

const kelompokTertutup = reactive({});
const kelompokBuka = (g) => !adaKelompok.value || !kelompokTertutup[g.kunci];

function aturKelompok(g) {
    kelompokTertutup[g.kunci] = !kelompokTertutup[g.kunci];
}

const kotorPerLevel = computed(() => {
    const hitung = {};
    for (const o of props.pratinjau?.orang || []) {
        if (adaPerubahan(o)) {
            const lv = o.peserta?.id_level ?? 0;
            hitung[lv] = (hitung[lv] || 0) + 1;
        }
    }
    return hitung;
});

const levelBerikutnya = computed(() => {
    if (daftarLevel.value.length <= 1) return null;
    const idx = daftarLevel.value.findIndex((l) => l.id_level === levelAktif.value);
    if (idx === -1 || idx >= daftarLevel.value.length - 1) return null;
    return daftarLevel.value[idx + 1];
});

function pindahKeLevel(idLevel) {
    levelAktif.value = idLevel;
    try {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch {}
}

const emit = defineEmits(['simpan', 'simpan-banyak', 'kotor', 'lanjut', 'ke-lembar']);

const TOLERANSI = 0.005;
const PANJANG_ESAI = 5000;

const TOOLBAR_ESAI = [
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote'],
    ['clean'],
];

const draf = reactive({});
const drafEsai = reactive({});

function kunciEsai(n) {
    return n.per_soal ? `${n.kode_soal}#${n.id_master_soal ?? ''}` : n.kode_soal;
}

function idEsai(n) {
    return n.per_soal ? `${n.kode_soal}-${n.id_master_soal ?? 'x'}` : n.kode_soal;
}

function bisaDiubah(n) {
    return Boolean(n.kode_soal) && (!n.per_soal || n.id_master_soal != null);
}

const galatEsai = ref('');
const dialogTerbuka = ref(false);
const orangDialog = ref(null);
const alasan = ref('');
const galatAlasan = ref('');
const sedangDisimpan = ref(0);

const kecil = ref(false);
let media = null;
const segarkanUkuran = () => {
    kecil.value = Boolean(media?.matches);
};

onMounted(() => {
    if (typeof window.matchMedia === 'function') {
        media = window.matchMedia('(max-width: 767px)');
        segarkanUkuran();
        media.addEventListener?.('change', segarkanUkuran);
    }
});

onBeforeUnmount(() => media?.removeEventListener?.('change', segarkanUkuran));

function sel(o, k) {
    return o.sel?.find((s) => s.competency_id === k.competency_id) ?? null;
}

function nilai(o, k) {
    return draf[o.assignment_id]?.[k.competency_id] ?? sel(o, k)?.skor_efektif ?? null;
}

function berubah(o, k) {
    const d = draf[o.assignment_id]?.[k.competency_id];
    const s = sel(o, k);
    return d !== undefined && s !== null && Math.abs(Number(d) - s.skor_efektif) >= TOLERANSI;
}

function ditandai(o, k) {
    return Boolean(sel(o, k)?.terkalibrasi) || berubah(o, k);
}

function revertNilai(o, k) {
    if (draf[o.assignment_id]?.[k.competency_id] !== undefined) {
        delete draf[o.assignment_id][k.competency_id];
        if (Object.keys(draf[o.assignment_id]).length === 0) {
            delete draf[o.assignment_id];
        }
    }
}

function revertSemua() {
    Object.keys(draf).forEach((k) => delete draf[k]);
    Object.keys(drafEsai).forEach((k) => delete drafEsai[k]);
}


function deltaText(o, k) {
    const d = draf[o.assignment_id]?.[k.competency_id];
    const s = sel(o, k);
    if (d === undefined || !s) return '';
    const diff = Number(d) - s.skor_efektif;
    if (Math.abs(diff) < TOLERANSI) return '';
    const sign = diff > 0 ? '+' : '';
    return `${sign}${diff.toFixed(1)}`;
}

function deltaClass(o, k) {
    const d = draf[o.assignment_id]?.[k.competency_id];
    const s = sel(o, k);
    if (d === undefined || !s) return '';
    const diff = Number(d) - s.skor_efektif;
    return diff > 0 ? 'is-up' : 'is-down';
}

function kompetensiBerubah(o) {
    return (props.pratinjau.kompetensi || []).filter((k) => berubah(o, k));
}

function teksEsai(o, n) {
    return drafEsai[o.assignment_id]?.[kunciEsai(n)] ?? n.jawaban_html ?? n.jawaban;
}

function esaiBerubah(o, n) {
    const d = drafEsai[o.assignment_id]?.[kunciEsai(n)];
    return d !== undefined && d !== (n.jawaban_html ?? n.jawaban ?? '');
}

function esaiKosong(o, n) {
    const d = drafEsai[o.assignment_id]?.[kunciEsai(n)];
    return d !== undefined && teksDariHtml(d) === '';
}

function naratifBerubah(o) {
    return (o.naratif || []).filter((n) => bisaDiubah(n) && esaiBerubah(o, n));
}

function ubahEsai(o, n, v) {
    drafEsai[o.assignment_id] ??= {};
    drafEsai[o.assignment_id][kunciEsai(n)] = String(v ?? '');
}

function revertEsai(o, n) {
    if (drafEsai[o.assignment_id]?.[kunciEsai(n)] !== undefined) {
        delete drafEsai[o.assignment_id][kunciEsai(n)];
        if (Object.keys(drafEsai[o.assignment_id]).length === 0) {
            delete drafEsai[o.assignment_id];
        }
    }
}

function adaPerubahan(o) {
    return kompetensiBerubah(o).length > 0 || naratifBerubah(o).length > 0;
}

const orangKotorList = computed(() => (props.pratinjau.orang || []).filter((o) => adaPerubahan(o) && o.boleh_diubah));
const orangKotorCount = computed(() => orangKotorList.value.length);
/* Bar aksi berganti ke mode draf hanya bila ada draf yang memang boleh disimpan penilai ini. */
const adaDrafBar = computed(() => orangKotorCount.value > 0);

function ubah(o, k, v) {
    draf[o.assignment_id] ??= {};
    draf[o.assignment_id][k.competency_id] = v === null || v === '' ? sel(o, k).skor_efektif : Number(v);
}

const orangBernaratif = computed(() => orangTampil.value.filter((o) => (o.naratif || []).length > 0));

/* Uraian dikelompokkan PER PERTANYAAN: satu baris berisi jawaban semua orang untuk pertanyaan
   yang sama. Tingkat & Expected Behaviour diambil dari level yang sedang ditampilkan. */
const pertanyaanNaratif = computed(() => {
    const perilakuLv = Object.fromEntries((levelAktifObj.value?.perilaku || []).map((p) => [p.competency_id, p]));
    const peta = new Map();
    for (const o of orangBernaratif.value) {
        for (const n of o.naratif || []) {
            const kunci = kunciEsai(n) || n.nama || 'tanpa-kode';
            if (!peta.has(kunci)) {
                const p = perilakuLv[n.competency_id];
                peta.set(kunci, {
                    kunci,
                    contoh: n,
                    tingkat: p?.tingkat ?? null,
                    nama_tingkat: p?.nama_tingkat ?? null,
                    expected_behaviour: p?.expected_behaviour || '',
                    jawaban: {},
                });
            }
            peta.get(kunci).jawaban[o.assignment_id] ??= n;
        }
    }
    return [...peta.values()];
});

/* Accordion per pertanyaan: bawaannya hanya pertanyaan pertama yang terbuka. Kuncinya kunci soal,
   jadi pilihan buka/tutup bertahan saat pindah tab level dan saat penyegaran senyap. */
const pertanyaanTerbuka = reactive({});

function teksPertanyaan(p) {
    if (!p.contoh.per_soal) return p.contoh.nama || 'Pertanyaan Uraian';
    return p.contoh.pertanyaan || 'Pertanyaan tidak tersedia (soal sudah dihapus di HC Learn)';
}

const pertanyaanBuka = (p, i) => pertanyaanTerbuka[p.kunci] ?? i === 0;

function aturPertanyaan(p, buka) {
    pertanyaanTerbuka[p.kunci] = buka;
    if (buka) tandaDilihat(p.kunci);
}

const semuaPertanyaanTerbuka = computed(() => pertanyaanNaratif.value.every((p, i) => pertanyaanBuka(p, i)));

function aturSemuaPertanyaan(buka) {
    for (const p of pertanyaanNaratif.value) pertanyaanTerbuka[p.kunci] = buka;
}

/*
 | PERTANYAAN YANG SUDAH DILIHAT, per level. Dihitung dilihat bila penilai membukanya sendiri,
 | atau bila pertanyaan itu terbuka saat masuk layar (IntersectionObserver). Dipakai pengingat,
 | status di bar, dan dialog saat Lanjut — penilai diingatkan, tidak dipaksa.
 */
const dilihat = reactive({});
const kunciLevel = (idLevel = levelAktif.value) => idLevel ?? 'semua';

function tandaDilihat(kunci) {
    dilihat[`${kunciLevel()}|${kunci}`] = true;
}

const sudahDilihat = (p) => Boolean(dilihat[`${kunciLevel()}|${p.kunci}`]);
const jumlahDilihat = computed(() => pertanyaanNaratif.value.filter(sudahDilihat).length);

const terlihat = new Set();
const diamati = new WeakSet();
const pengamat =
    typeof window !== 'undefined' && 'IntersectionObserver' in window
        ? new window.IntersectionObserver(
              (entri) => {
                  for (const e of entri) {
                      const kunci = e.target.dataset.kunci;
                      if (!e.isIntersecting) {
                          terlihat.delete(kunci);
                          continue;
                      }
                      terlihat.add(kunci);
                      if (e.target.classList.contains('is-terbuka')) tandaDilihat(kunci);
                  }
              },
              { rootMargin: '0px 0px -20% 0px' },
          )
        : null;

function amatiPertanyaan(el) {
    if (!el || !pengamat || diamati.has(el)) return;
    diamati.add(el);
    pengamat.observe(el);
}

// Dibuka lewat "Buka semua" saat sudah di layar: tidak ada peristiwa masuk layar baru.
watch(
    () => pertanyaanNaratif.value.filter((p, i) => pertanyaanBuka(p, i)).map((p) => p.kunci),
    (buka) => nextTick(() => buka.forEach((k) => terlihat.has(k) && tandaDilihat(k))),
);

onBeforeUnmount(() => pengamat?.disconnect());

const jangkarUraian = ref(null);

/* Lompat ke uraian: membuka pertanyaan (bawaan: yang pertama belum dilihat) lalu menggulir ke sana. */
async function keUraian(kunci) {
    const target = kunci ?? pertanyaanNaratif.value.find((p) => !sudahDilihat(p))?.kunci;
    if (target) pertanyaanTerbuka[target] = true;
    await nextTick();
    const el =
        [...(jangkarUraian.value?.querySelectorAll('[data-kunci]') || [])].find((x) => x.dataset.kunci === target) ||
        jangkarUraian.value;
    el?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
}

function kunciPertanyaanLevel(idLevel) {
    const semua = props.pratinjau?.orang || [];
    const orang =
        daftarLevel.value.length <= 1 || idLevel === null
            ? semua
            : semua.filter((o) => (o.peserta?.id_level ?? 0) === idLevel);
    return [...new Set(orang.flatMap((o) => (o.naratif || []).map((n) => kunciEsai(n) || n.nama || 'tanpa-kode')))];
}

const dialogUraianTerbuka = ref(false);
const uraianTertunda = ref([]);
let aksiTertunda = null;

/* Sebelum pindah (level berikutnya atau langkah berikutnya): bila masih ada uraian yang belum
   dilihat di level yang ditinggalkan, tanya dulu. Tanpa `daftarIdLevel` = semua level. */
function cobaPindah(aksi, daftarIdLevel) {
    const ids = daftarIdLevel ?? (daftarLevel.value.length > 1 ? daftarLevel.value.map((l) => l.id_level) : [levelAktif.value]);
    const tertunda = ids.flatMap((id) =>
        kunciPertanyaanLevel(id)
            .filter((k) => !dilihat[`${kunciLevel(id)}|${k}`])
            .map((kunci) => ({ id_level: id, kunci })),
    );
    if (!tertunda.length) return aksi();
    uraianTertunda.value = tertunda;
    aksiTertunda = aksi;
    dialogUraianTerbuka.value = true;
}

const ringkasTertunda = computed(() =>
    daftarLevel.value
        .map((l) => ({
            id_level: l.id_level,
            nama_level: l.nama_level,
            jumlah: uraianTertunda.value.filter((t) => t.id_level === l.id_level).length,
        }))
        .filter((r) => r.jumlah),
);

function tetapLanjut() {
    dialogUraianTerbuka.value = false;
    const aksi = aksiTertunda;
    aksiTertunda = null;
    aksi?.();
}

function lihatTertunda() {
    dialogUraianTerbuka.value = false;
    aksiTertunda = null;
    const t = uraianTertunda.value[0];
    if (!t) return;
    if (daftarLevel.value.length > 1) levelAktif.value = t.id_level;
    keUraian(t.kunci);
}

/* Revisi yang belum disimpan di soal ini — tetap terlihat walau soalnya tertutup. */
const jumlahDirevisi = (p) =>
    orangBernaratif.value.filter((o) => p.jawaban[o.assignment_id] && esaiBerubah(o, p.jawaban[o.assignment_id])).length;

/* Di bawah ambang ini uraian ditandai "sangat singkat" supaya jawaban tipis mudah terlihat. */
const KATA_SINGKAT = 10;

function jumlahKata(o, n) {
    return teksDariHtml(teksEsai(o, n)).split(/\s+/).filter(Boolean).length;
}

const totalRevisiNaratif = computed(() => {
    let jml = 0;
    for (const p of pertanyaanNaratif.value) {
        jml += jumlahDirevisi(p);
    }
    return jml;
});

const adaNaratif = computed(() => orangBernaratif.value.length > 0);
const kotor = computed(() => (props.pratinjau.orang || []).some((o) => adaPerubahan(o)));

watch(kotor, (v) => emit('kotor', v), { immediate: true });

watch(
    () => props.pratinjau,
    () => {
        if (sedangDisimpan.value) {
            delete draf[sedangDisimpan.value];
            delete drafEsai[sedangDisimpan.value];
            sedangDisimpan.value = 0;
            dialogTerbuka.value = false;
        }
        emit('kotor', kotor.value);
    },
);

watch(
    () => props.galatSimpan,
    (g) => {
        if (g) sedangDisimpan.value = 0;
    },
);

/* Isi dialog: satu orang (tombol di kolom matriks) atau semua yang punya draf (tombol di bar).
   Disimpan sebagai id lalu dibaca ulang dari data terbaru, supaya orang yang sudah tersimpan
   di tengah simpan-banyak otomatis keluar dari daftar. */
const idDialog = ref([]);
const orangDialogs = computed(() =>
    idDialog.value
        .map((id) => (props.pratinjau.orang || []).find((o) => o.assignment_id === id))
        .filter((o) => o && adaPerubahan(o)),
);

function bukaDialog(daftar) {
    idDialog.value = daftar.map((o) => o.assignment_id);
    orangDialog.value = daftar[0] ?? null;
    alasan.value = '';
    galatAlasan.value = '';
    galatEsai.value = '';
    dialogTerbuka.value = true;
}

function bukaAlasan(o) {
    bukaDialog([o]);
}

function bukaAlasanSemua() {
    bukaDialog(orangKotorList.value);
}

function payloadSimpan(o, teks) {
    return {
        assignmentId: o.assignment_id,
        perubahan: kompetensiBerubah(o).map((k) => ({
            competency_id: k.competency_id,
            skor: Number(draf[o.assignment_id][k.competency_id]),
        })),
        perubahanJawaban: naratifBerubah(o).map((n) => ({
            kode_soal: n.kode_soal,
            ...(n.per_soal ? { id_master_soal: n.id_master_soal } : {}),
            jawaban: drafEsai[o.assignment_id][kunciEsai(n)],
        })),
        alasan: teks,
    };
}

function kirimSimpan() {
    const daftar = orangDialogs.value.length ? orangDialogs.value : [orangDialog.value].filter(Boolean);
    const o = daftar[0];

    if (daftar.some((x) => (x?.naratif || []).some((n) => bisaDiubah(n) && esaiKosong(x, n)))) {
        galatEsai.value = 'Ada jawaban uraian yang dikosongkan. Isi kembali sebelum menyimpan.';
        return;
    }

    galatEsai.value = '';
    const teks = alasan.value.trim();

    if (!teks) {
        galatAlasan.value = 'Alasan wajib diisi.';
        return;
    }

    if (teks.length > 500) {
        galatAlasan.value = 'Alasan paling panjang 500 karakter.';
        return;
    }

    galatAlasan.value = '';

    if (daftar.length > 1) {
        // Draf tiap orang dibuang begitu ia tersimpan; dialog tertutup setelah semuanya selesai.
        emit(
            'simpan-banyak',
            daftar.map((x) => ({ ...payloadSimpan(x, teks), nama: x.peserta?.nama })),
            (id) => {
                delete draf[id];
                delete drafEsai[id];
                if (!orangDialogs.value.length) dialogTerbuka.value = false;
            },
        );
        return;
    }

    sedangDisimpan.value = o.assignment_id;
    emit('simpan', payloadSimpan(o, teks));
}

function inisial(nama) {
    return String(nama || 'P')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p.charAt(0).toUpperCase())
        .join('');
}

function avatarClass(o) {
    if (o.diri_sendiri) return 'is-self';
    const kode = props.pratinjau.kategori?.kode?.toUpperCase();
    if (kode === 'ATASAN') return 'is-atasan';
    if (kode === 'BAWAHAN') return 'is-bawahan';
    return 'is-peer';
}

defineExpose({ ubah, ubahEsai, revertNilai, bukaAlasan, bukaAlasanSemua, kirimSimpan, alasan, galatAlasan, galatEsai });
</script>

<style scoped>
.pa-kalibrasi {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    position: relative;
    padding-bottom: 6.5rem;
}


.pa-dirty-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

.pa-dirty-pill--sm {
    font-size: 0.6rem;
    padding: 0.1rem 0.35rem;
}

.pa-kalibrasi__sr {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

.pa-kalibrasi__isian {
    display: inline-flex;
    justify-content: center;
    width: 5.5rem;
    margin: 0;
}

/* Clamping & Zero overflow */
.pa-kalibrasi__wadah {
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
    position: relative;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-kalibrasi__matriks {
    border-collapse: separate;
    border-spacing: 0;
    min-width: 100%;
    font-size: 0.825rem;
}

.pa-kalibrasi__matriks th,
.pa-kalibrasi__matriks td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    text-align: center;
    white-space: nowrap;
    color: var(--hcis-color-text-main, #0f172a);
    transition: background 0.15s ease;
}

.pa-kalibrasi__matriks thead th {
    color: var(--hcis-color-text-main, #0f172a);
    font-weight: 750;
    background: rgba(248, 250, 252, 0.95);
    border-bottom: 2px solid rgba(226, 232, 240, 0.95);
}

.pa-comp-head-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-comp-head {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
}

.pa-comp-name-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-comp-name {
    font-size: 0.9rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.35;
}

.pa-btn-comp-info {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: var(--hcis-color-text-muted, #475569);
    font-size: 0.68rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.3;
}

.pa-btn-comp-info:hover {
    background: #e2e8f0;
    color: var(--hcis-color-text-main, #0f172a);
    border-color: #cbd5e1;
}

.pa-comp-meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pa-comp-type {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #475569);
    padding: 0.15rem 0.5rem;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    line-height: 1.3;
}

.pa-comp-tingkat {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 600;
    color: #4338ca;
    padding: 0.15rem 0.5rem;
    background: #eef2ff;
    border: 1px solid #e0e7ff;
    border-radius: 6px;
    line-height: 1.3;
}

/* Sticky Left Person Column */
.pa-kalibrasi__matriks .pa-kalibrasi__nama {
    min-width: 240px;
    max-width: 340px;
    white-space: normal;
    vertical-align: top;
    position: sticky;
    left: 0;
    z-index: 2;
    text-align: left;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    color: var(--hcis-color-text-main, #0f172a);
    border-right: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 3px 0 8px rgba(15, 23, 42, 0.02);
}

.pa-matrix-person-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 13rem;
    padding: 0.2rem 0;
}

.pa-matrix-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(79, 70, 229, 0.2);
}

.pa-matrix-avatar.is-self {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    box-shadow: 0 1px 4px rgba(139, 92, 246, 0.2);
}

.pa-matrix-avatar.is-atasan {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    box-shadow: 0 1px 4px rgba(14, 165, 233, 0.2);
}

.pa-matrix-avatar.is-bawahan {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 1px 4px rgba(16, 185, 129, 0.2);
}

.pa-matrix-avatar.is-peer {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 1px 4px rgba(245, 158, 11, 0.2);
}

.pa-matrix-person-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.pa-matrix-person-name-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-matrix-person-name {
    font-size: 0.85rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-matrix-person-sub {
    font-size: 0.7rem;
    color: var(--hcis-color-text-muted, #64748b);
    font-weight: 500;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pa-matrix-score-cell {
    position: relative;
}

.pa-cell-input-row {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-btn-revert-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.45rem;
    height: 1.45rem;
    border-radius: 6px;
    border: 1px solid rgba(245, 158, 11, 0.3);
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-btn-revert-cell:hover {
    background: #d97706;
    color: #ffffff;
}

.pa-matrix-score-cell.is-terkalibrasi {
    background: rgba(79, 70, 229, 0.04);
}

.pa-matrix-score-cell.is-modified {
    background: rgba(245, 158, 11, 0.08);
}

.pa-kalibrasi__matriks .is-col-dirty {
    background-color: rgba(245, 158, 11, 0.05);
}

.pa-kalibrasi__matriks thead th.pa-col-person {
    min-width: 15rem;
    text-align: left;
    vertical-align: top;
}

.pa-kalibrasi__matriks .pa-col-comp-head {
    min-width: 14rem;
}

.pa-matrix-save-cell {
    background: rgba(248, 250, 252, 0.95);
}

.pa-score-readonly {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-score-empty {
    color: var(--hcis-color-text-muted, #94a3b8);
}

.pa-score-diff-tag {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 0.25rem;
}

.pa-kalibrasi__asli {
    display: block;
    color: var(--hcis-color-text-muted, #94a3b8);
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
}

.pa-score-delta {
    font-size: 0.68rem;
    font-weight: 750;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    font-variant-numeric: tabular-nums;
}

.pa-score-delta.is-up {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.pa-score-delta.is-down {
    color: #dc2626;
    background: rgba(220, 38, 38, 0.12);
    border: 1px solid rgba(220, 38, 38, 0.25);
}

/* Average Score Row */
.pa-matrix-footer-row th,
.pa-matrix-footer-row td {
    background: rgba(248, 250, 252, 0.95) !important;
    border-top: 2px solid rgba(226, 232, 240, 0.95);
    border-bottom: none;
    font-weight: 700;
}

.pa-footer-label {
    position: sticky;
    left: 0;
    z-index: 2;
}

.pa-footer-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--hcis-color-text-main, #0f172a);
    font-size: 0.82rem;
    font-weight: 750;
}

.pa-btn-save-row.is-dirty {
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
    font-weight: 700;
    border-radius: 8px;
}

.pa-status-saved {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #059669;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.1);
}

.pa-status-clean {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

/* Card Kontrol Terpadu: HcisCard Variant Glass + Rings (Konsisten dengan Hero Card) */
.pa-kalibrasi-unified-card {
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
}

.pa-kalibrasi-unified-card :deep(.hcis-card__body) {
    padding: 0;
    display: flex;
    flex-direction: column;
}

.pa-unified-panel-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 1.25rem 0.35rem;
    flex-wrap: wrap;
}

.pa-unified-panel-tabs {
    flex: 1;
    min-width: 0;
}

.pa-level-capsule-group {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    gap: 0.25rem;
    max-width: 100%;
}

.pa-level-capsule-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: none;
    background: transparent;
    color: var(--hcis-color-text-muted, #64748b);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    white-space: nowrap;
}

.pa-level-capsule-btn:hover {
    color: var(--hcis-color-text-main, #0f172a);
    background: rgba(255, 255, 255, 0.6);
}

.pa-level-capsule-btn.is-active {
    background: #ffffff;
    color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.pa-level-capsule-label {
    min-width: 0;
}

.pa-level-capsule-badge {
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 750;
    background: rgba(0, 0, 0, 0.06);
    color: inherit;
}

.pa-level-capsule-btn.is-active .pa-level-capsule-badge {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-unified-panel-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    padding: 0.4rem 0;
}

.pa-unified-panel-uraian-btn {
    flex-shrink: 0;
    margin-left: auto;
}

.pa-unified-card-divider {
    height: 1px;
    width: 100%;
    background: linear-gradient(90deg, rgba(226, 232, 240, 0.2) 0%, rgba(226, 232, 240, 0.9) 25%, rgba(226, 232, 240, 0.9) 75%, rgba(226, 232, 240, 0.2) 100%);
}

.pa-unified-panel-sub {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.75rem 1.25rem;
    background: rgba(248, 250, 252, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.pa-unified-sub-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.78rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.45;
}

.pa-unified-sub-item--uraian {
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-unified-sub-icon {
    width: 0.95rem;
    height: 0.95rem;
    color: var(--hcis-color-brand-600, #4f46e5);
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.pa-unified-sub-icon-gray {
    width: 0.95rem;
    height: 0.95rem;
    color: #64748b;
    flex-shrink: 0;
    margin-top: 0.1rem;
}


/* Judul kelompok jenis kompetensi: baris divider rapi & elegan */
.pa-kalibrasi__matriks .pa-kelompok-sel {
    padding: 0;
    text-align: left;
    background: #f1f5f9;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}

.pa-kelompok-tombol {
    position: sticky;
    left: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    border: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
    color: var(--hcis-color-text-main, #0f172a);
    user-select: none;
    transition: color 0.15s ease;
}

.pa-kelompok-tombol:hover {
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-kelompok-nama {
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-color-text-main, #1e293b);
}

.pa-kelompok-jumlah {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(79, 70, 229, 0.08);
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}

.pa-kelompok-kartu {
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    background: #f8fafc;
    border-radius: var(--hcis-radius-md, 8px);
    margin-bottom: 0.35rem;
}

.pa-kelompok-kartu .pa-kelompok-tombol {
    position: static;
    width: 100%;
    padding: 0.5rem 0.75rem;
    display: flex;
    justify-content: space-between;
}

.pa-matriks-kosong,
.pa-kalibrasi__matriks .pa-matriks-kosong td {
    padding: 1.25rem 1rem;
    text-align: center;
    font-size: 0.85rem;
    color: var(--hcis-color-text-muted, #64748b);
}

/* Lompatan ke uraian berhenti sedikit di bawah tepi atas layar. */
.pa-uraian-jangkar,
.pa-naratif-baris {
    scroll-margin-top: 6rem;
}

.pa-btn-uraian {
    margin-right: auto;
}

.pa-dialog-uraian {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-dialog-uraian__hero {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(251, 191, 36, 0.04) 100%);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.pa-dialog-uraian__icon-bubble {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    flex-shrink: 0;
}

.pa-dialog-uraian__hero-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pa-dialog-uraian__hero-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #92400e;
    line-height: 1.25;
}

.pa-dialog-uraian__hero-desc {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.45;
    color: #475569;
}

.pa-text-warning-highlight {
    color: #b45309;
    font-weight: 750;
}

.pa-dialog-uraian__hint {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: #64748b;
}

.pa-dialog-uraian__levels-section {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-dialog-uraian__section-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

.pa-dialog-uraian__levels-grid {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-dialog-uraian__level-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.pa-dialog-uraian__level-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.pa-dialog-uraian__level-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    background: #e2e8f0;
    color: #1e293b;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    border-radius: 6px;
}

.pa-dialog-uraian__level-count {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    font-size: 0.8rem;
    color: #64748b;
}

.pa-dialog-uraian__level-count strong {
    font-size: 0.95rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-dialog-uraian__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
}

/* Fixed Unified Navigation & Calibration Dock */
.pa-fixed-dock-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 50;
    display: flex;
    justify-content: center;
    padding: 0 1rem 0.85rem;
    pointer-events: none;
}

.pa-fixed-dock-wrap {
    width: 100%;
    max-width: 80rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    pointer-events: auto;
}

/* Mode draf: bar yang sama, isi berganti. Polos — tanpa latar gelap atau aksen. */
.pa-fixed-dock__status {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
    font-size: 0.85rem;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-fixed-dock__status strong {
    font-weight: 700;
}

.pa-fixed-dock__status-sub {
    margin-left: 0.35rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-fixed-dock__status-ikon {
    color: #d97706;
    flex-shrink: 0;
}

/* 2. Baris Navigasi Utama */
.pa-fixed-dock__nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-fixed-dock__nav-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-btn-back {
    font-weight: 650;
    transition: all 0.15s ease;
}

.pa-btn-forward {
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    border-radius: 10px;
    font-weight: 700;
}

.pa-slide-up-enter-active,
.pa-slide-up-leave-active {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-slide-up-enter-from,
.pa-slide-up-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

/* Mobile Card List View (< 768px) */
.pa-kalibrasi__kartu-daftar {
    display: none;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: var(--hcis-space-3, 0.75rem);
}

.pa-kalibrasi__kartu {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    padding: 1.15rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.pa-kalibrasi__kartu.is-row-dirty {
    border-color: rgba(245, 158, 11, 0.35);
    background: radial-gradient(ellipse at 100% 0%, rgba(254, 243, 199, 0.3) 0%, #ffffff 70%);
}

.pa-card-score-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0;
}

.pa-kalibrasi__baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.85rem;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-kalibrasi__baris.is-modified {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
}

.pa-card-score-label {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-card-comp-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-card-comp-type {
    font-size: 0.68rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-card-score-control {
    margin: 0;
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pa-btn-mobile-save {
    margin-top: 0.5rem;
}

/* Uraian per pertanyaan: kolom pertanyaan menempel di kiri, satu kolom per orang. */
.pa-kalibrasi__naratif {
    margin-bottom: var(--hcis-space-4, 1rem);
}

.pa-naratif-card-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-naratif-wadah {
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* Lebar mengikuti wadah supaya jawaban terbungkus; baru bergulir ke samping bila tiap orang
   tidak lagi kebagian 18rem. */
.pa-naratif-grid {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: calc(var(--pa-naratif-kolom, 1) * 18rem);
}

.pa-naratif-baris {
    display: grid;
    grid-template-columns: repeat(var(--pa-naratif-kolom, 1), minmax(0, 1fr));
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    transition: background-color 0.15s ease;
}

.pa-naratif-baris:last-child {
    border-bottom: 0;
}

.pa-naratif-baris--kepala {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid rgba(226, 232, 240, 0.95);
}

.pa-naratif-tanya {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    background: rgba(248, 250, 252, 0.95);
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
}

/* Kepala accordion: sleek compact card */
.pa-naratif-tanya .pa-naratif-question {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    min-width: 0;
    padding: 0.55rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    font: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    transition: all 0.15s ease;
}

.pa-naratif-tanya .pa-naratif-question:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.pa-naratif-tanya__isi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.pa-naratif-tanya__baris-atas {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
}

.pa-naratif-tanya__nomor-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    line-height: 1.25;
}

.pa-naratif-comp-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.45rem;
    border-radius: 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.25;
}

.pa-naratif-level-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.45rem;
    border-radius: 5px;
    background: #faf5ff;
    border: 1px solid #f3e8ff;
    color: #7e22ce;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.25;
}

.pa-naratif-tanya .pa-naratif-question-text {
    display: block;
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.35;
    color: #0f172a;
}

.pa-naratif-question-text.is-terpotong {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pa-naratif-tanya__ringkas {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.pa-naratif-jawab-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
    padding: 0.15rem 0.45rem;
    background: #f1f5f9;
    border-radius: 999px;
}

.pa-naratif-tanya__panah {
    color: var(--hcis-color-text-muted, #64748b);
    transition: transform 0.2s ease;
}

.pa-naratif-tanya__panah.is-terbuka {
    transform: rotate(180deg);
}

.pa-naratif-tanya--perilaku {
    padding-top: 0;
}

/* Nama orang sudah ada di kepala kolom; label editor disembunyikan dari tampilan saja. */
.pa-naratif-sel :deep(.hcis-rich-text__header) {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

.pa-naratif-sel {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 1rem 1.15rem;
    min-width: 0;
    border-right: 1px solid rgba(241, 245, 249, 0.95);
    background: #ffffff;
    transition: background-color 0.2s ease;
}

.pa-naratif-sel:last-child {
    border-right: 0;
}

.pa-naratif-sel--kepala {
    flex-direction: row;
    align-items: center;
    gap: 0.65rem;
    padding: 0.85rem 1.15rem;
    background: transparent;
}

.pa-naratif-sel.is-terkalibrasi {
    background: radial-gradient(ellipse at 100% 0%, rgba(238, 242, 255, 0.75) 0%, #ffffff 80%);
}

.pa-naratif-sel.is-kosong {
    justify-content: center;
    background: rgba(248, 250, 252, 0.4);
}

.pa-naratif-empty-state {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: var(--hcis-color-text-muted, #94a3b8);
    padding: 1rem 0;
}

.pa-naratif-sel__orang {
    display: none;
    align-items: center;
    gap: 0.6rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px dashed rgba(226, 232, 240, 0.9);
}

.pa-naratif-sel__orang-info {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
}

.pa-naratif-sel__orang-nama {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-naratif-sel__orang-jabatan {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-naratif-sel__avatar {
    width: 2rem;
    height: 2rem;
    font-size: 0.75rem;
    border-radius: 10px;
    flex-shrink: 0;
}

/* Status Bar & Quick Revert */
.pa-naratif-sel__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.55rem;
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-sm, 6px);
}

.pa-naratif-badge-edit {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #b45309;
}

.pa-naratif-badge-calibrated {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-color-success-600, #16a34a);
}

.pa-btn-revert-naratif {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 4px;
    background: #ffffff;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-btn-revert-naratif:hover {
    color: #b91c1c;
    border-color: #fca5a5;
    background: #fef2f2;
}

.pa-naratif-sel .pa-naratif-answer {
    overflow-wrap: anywhere;
}

.pa-naratif-sel__kaki {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.2rem;
}

.pa-naratif-char-info {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #94a3b8);
}

.pa-naratif-question {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-naratif-question-text {
    font-size: 0.825rem;
    color: var(--hcis-color-text-main, #334155);
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.pa-naratif-question-meta {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-naratif-answer {
    margin: 0;
    font-size: 0.875rem;
    color: var(--hcis-color-text-main, #334155);
    line-height: 1.55;
    white-space: pre-line;
}

.pa-naratif-answer.is-editable,
.pa-naratif-answer.is-html {
    white-space: normal;
}

.pa-naratif-answer.is-editable :deep(.ql-editor) {
    font-size: 0.875rem;
    line-height: 1.55;
    color: var(--hcis-color-text-main, #1e293b);
}

.pa-naratif-answer.is-html {
    padding: 0.65rem 0.85rem;
    background: rgba(248, 250, 252, 0.7);
    border: 1px solid rgba(241, 245, 249, 0.95);
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-naratif-answer.is-html :deep(p),
.pa-naratif-answer.is-html :deep(ul),
.pa-naratif-answer.is-html :deep(ol),
.pa-naratif-answer.is-html :deep(blockquote) {
    margin: 0 0 var(--hcis-space-1);
}

.pa-naratif-answer.is-html :deep(p:last-child) {
    margin-bottom: 0;
}

/* Jawaban Asli Box */
.pa-naratif-asli-box {
    margin-top: 0.35rem;
    padding: 0.55rem 0.75rem;
    background: rgba(241, 245, 249, 0.6);
    border: 1px dashed rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-naratif-asli-header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-color-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-naratif-asli-content {
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--hcis-color-text-muted, #64748b);
    overflow-wrap: anywhere;
}

.pa-naratif-asli-content s {
    text-decoration-color: rgba(148, 163, 184, 0.6);
}

/* Dialog Content */

.pa-dialog-content-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-dialog-recap-box {
    padding: 0.95rem 1.15rem;
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
}

.pa-dialog-recap-header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    margin-bottom: 0.5rem;
}

.pa-kalibrasi__ringkas {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-recap-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.82rem;
    padding: 0.3rem 0;
    border-bottom: 1px dashed rgba(226, 232, 240, 0.9);
}

.pa-recap-name {
    color: var(--hcis-color-text-main, #334155);
    font-weight: 600;
}

.pa-recap-scores {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-recap-old {
    color: var(--hcis-color-text-muted, #64748b);
    font-variant-numeric: tabular-nums;
}

.pa-recap-new {
    color: var(--hcis-color-text-main, #0f172a);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-dialog-textarea-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-kalibrasi__aksi-dialog {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    width: 100%;
}

@media (max-width: 768px) {
    .pa-kalibrasi {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .pa-kalibrasi-unified-panel,
    .pa-kalibrasi-unified-card {
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-kalibrasi-unified-card :deep(.hcis-card__body) {
        padding: 0.25rem 0.35rem;
    }

    .pa-unified-panel-top {
        padding: 0;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 0;
    }

    .pa-unified-panel-uraian-btn {
        display: none !important;
    }

    .pa-unified-panel-tabs {
        flex: 1;
        width: 100%;
        min-width: 0;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .pa-unified-panel-tabs::-webkit-scrollbar {
        display: none;
    }

    .pa-level-capsule-group {
        display: flex;
        width: 100%;
        max-width: 100%;
        padding: 0.15rem;
        background: transparent;
        border: none;
        gap: 0.25rem;
    }

    .pa-level-capsule-btn {
        flex: 1;
        justify-content: center;
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
        gap: 0.3rem;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .pa-level-capsule-btn.is-active {
        background: #ffffff;
        border-color: rgba(79, 70, 229, 0.3);
        box-shadow: 0 1px 4px rgba(79, 70, 229, 0.12);
    }

    .pa-level-capsule-badge {
        font-size: 0.625rem;
        padding: 0.05rem 0.35rem;
    }

    .pa-unified-card-divider {
        display: none !important;
    }

    .pa-unified-panel-sub {
        display: none !important;
    }

    .pa-kalibrasi-toolbar {
        padding: 0.75rem 0.85rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .pa-kalibrasi-desc,
    .pa-naratif-subtitle {
        display: none !important;
    }

    .pa-kalibrasi-toolbar-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-kalibrasi-stat-pills {
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .pa-kalibrasi__wadah {
        display: none;
    }

    .pa-kalibrasi__kartu-daftar {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .pa-kalibrasi__kartu {
        padding: 0.85rem;
        gap: 0.65rem;
        border-radius: var(--hcis-radius-lg, 12px);
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }

    .pa-card-person-header {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(241, 245, 249, 0.95);
    }

    .pa-matrix-avatar {
        width: 2.25rem;
        height: 2.25rem;
        font-size: 0.75rem;
        border-radius: 8px;
    }

    .pa-person-name {
        font-size: 0.875rem;
        font-weight: 750;
        color: var(--hcis-color-text-main, #0f172a);
    }

    .pa-wizard-lembar__sub {
        font-size: 0.7rem;
        color: var(--hcis-color-text-muted, #64748b);
        line-height: 1.25;
    }

    .pa-card-score-list {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin: 0;
    }

    .pa-kelompok-kartu {
        padding: 0.25rem 0 0.1rem;
    }

    .pa-kelompok-tombol {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        width: 100%;
        font-size: 0.75rem;
        font-weight: 750;
        color: var(--hcis-color-text-muted, #475569);
        padding: 0.25rem 0.45rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }

    .pa-kelompok-nama {
        font-weight: 750;
        letter-spacing: 0.01em;
    }

    .pa-kelompok-jumlah {
        margin-left: auto;
        font-size: 0.65rem;
        font-weight: 750;
        padding: 0.05rem 0.4rem;
        background: #e2e8f0;
        color: #475569;
        border-radius: 999px;
    }

    .pa-kalibrasi__baris {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.45rem 0.65rem;
        gap: 0.5rem;
        background: #f8fafc;
        border: 1px solid #eef2f6;
        border-radius: 8px;
    }

    .pa-kalibrasi__baris.is-modified {
        background: rgba(245, 158, 11, 0.08);
        border-color: rgba(245, 158, 11, 0.3);
    }

    .pa-card-score-label {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        min-width: 0;
        flex: 1;
    }

    .pa-card-comp-name {
        font-size: 0.8rem;
        line-height: 1.3;
        font-weight: 700;
        color: var(--hcis-color-text-main, #1e293b);
        word-break: break-word;
    }

    .pa-btn-comp-info {
        padding: 0.1rem 0.3rem;
        font-size: 0.7rem;
        color: var(--hcis-color-brand-600, #4f46e5);
        background: transparent;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        line-height: 1;
    }

    .pa-comp-tingkat {
        font-size: 0.625rem;
        padding: 0.05rem 0.35rem;
        border-radius: 4px;
        line-height: 1.25;
    }

    .pa-card-score-control {
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.2rem;
    }

    .pa-card-score-control :deep(.hcis-input-number) {
        width: 5.25rem;
    }

    .pa-card-score-control :deep(.hcis-input-number__field) {
        height: 1.85rem;
        font-size: 0.825rem;
        font-weight: 700;
        padding: 0 0.35rem;
        text-align: center;
    }

    .pa-score-readonly {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--hcis-color-text-main, #0f172a);
        padding-right: 0.5rem;
    }

    .pa-score-diff-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.68rem;
    }

    .pa-btn-mobile-save {
        margin-top: 0.35rem;
        height: 2.35rem;
        font-size: 0.825rem;
        font-weight: 700;
        border-radius: 8px;
    }

    .pa-kalibrasi__naratif {
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-kalibrasi__naratif :deep(.hcis-card__header) {
        padding: 0.65rem 0.85rem;
        gap: 0.5rem;
    }

    .pa-kalibrasi__naratif :deep(.hcis-card__title) {
        font-size: 0.925rem;
        font-weight: 750;
    }

    .pa-kalibrasi__naratif :deep(.hcis-card__desc) {
        display: none !important;
    }

    .pa-kalibrasi__naratif :deep(.hcis-card__body) {
        padding: 0.5rem 0.65rem;
    }

    .pa-naratif-wadah {
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-naratif-grid {
        min-width: 0;
    }

    .pa-naratif-baris {
        grid-template-columns: 1fr;
    }

    .pa-naratif-baris--kepala {
        display: none;
    }

    .pa-naratif-tanya {
        padding: 0.35rem 0.45rem;
    }

    .pa-naratif-tanya .pa-naratif-question {
        flex-direction: column;
        align-items: stretch;
        padding: 0.5rem 0.65rem;
        gap: 0.45rem;
        border-radius: 8px;
    }

    .pa-naratif-tanya__isi {
        gap: 0.25rem;
        width: 100%;
        min-width: 0;
    }

    .pa-naratif-tanya__baris-atas {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .pa-naratif-question-text {
        font-size: 0.825rem;
        line-height: 1.35;
        font-weight: 700;
        word-break: break-word;
    }

    .pa-naratif-tanya__ringkas {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        font-size: 0.7rem;
        width: 100%;
        padding-top: 0.35rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.8);
    }

    .pa-naratif-tanya__panah {
        margin-left: auto;
        flex-shrink: 0;
    }

    /* Expected behaviour di mobile dipindah ke modal agar layar tetap compact & rapi */
    .pa-naratif-tanya--perilaku {
        display: none !important;
    }

    .pa-btn-comp-info--naratif {
        padding: 0.1rem 0.4rem;
        font-size: 0.65rem;
        background: #ede9fe;
        color: #6b21a8;
        border-color: #ddd6fe;
    }

    .pa-btn-comp-info--naratif:hover {
        background: #ddd6fe;
        color: #581c87;
    }

    .pa-naratif-sel {
        padding: 0.65rem 0.75rem;
        gap: 0.45rem;
        border-right: 0;
        border-top: 1px solid rgba(241, 245, 249, 0.95);
    }

    .pa-naratif-sel__orang {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding-bottom: 0.35rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .pa-naratif-sel__avatar {
        width: 1.85rem;
        height: 1.85rem;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .pa-naratif-sel__orang-nama {
        font-size: 0.8rem;
        font-weight: 750;
    }

    .pa-naratif-sel__orang-jabatan {
        font-size: 0.65rem;
    }

    .pa-naratif-answer.is-html {
        padding: 0.5rem 0.65rem;
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .pa-naratif-answer.is-editable :deep(.ql-editor) {
        font-size: 0.825rem;
        min-height: 80px;
        padding: 0.5rem;
    }

    .pa-naratif-asli-box {
        padding: 0.4rem 0.55rem;
        font-size: 0.72rem;
    }

    .pa-fixed-dock-container {
        padding: 0 0.5rem calc(env(safe-area-inset-bottom, 0px) + 0.5rem);
    }

    .pa-fixed-dock__nav-bar {
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-xl, 16px);
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14), 0 2px 6px rgba(15, 23, 42, 0.06);
        border: 1px solid rgba(226, 232, 240, 0.95);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    /* Status banner draf di-hide di mobile agar dock ramping & maksimal untuk aksi */
    .pa-fixed-dock__status {
        display: none !important;
    }

    /* Mode Draf: Batalkan & Simpan 50/50 bersebelahan */
    .pa-fixed-dock__nav-bar.is-draf .pa-fixed-dock__nav-right {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }

    /* Mode Bersih di Mobile:
       - Sembunyikan tombol Uraian (.pa-btn-uraian) agar dock bersih & compact
       - Jika hanya ada Kembali & Lanjut: sejajar 1 baris proporsional
       - Jika ada Level Berikutnya: Kembali di kiri, Next Level & Lanjut di kanan */
    .pa-btn-uraian {
        display: none !important;
    }

    .pa-fixed-dock__nav-bar:not(.is-draf) {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .pa-fixed-dock__nav-bar:not(.is-draf) .pa-btn-back {
        flex-shrink: 0;
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .pa-fixed-dock__nav-bar:not(.is-draf) .pa-fixed-dock__nav-right {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
        justify-content: flex-end;
        min-width: 0;
    }

    .pa-fixed-dock__nav-bar:not(.is-draf) .pa-btn-next-level {
        flex: 1;
        min-width: 0;
    }

    .pa-fixed-dock__nav-bar:not(.is-draf) .pa-btn-forward {
        flex: 1;
        min-width: 0;
        font-weight: 750;
    }

    .pa-fixed-dock__nav-bar :deep(.hcis-btn) {
        min-height: 2.35rem;
        font-size: 0.8rem;
        border-radius: 8px;
        padding-left: 0.65rem;
        padding-right: 0.65rem;
    }

    .pa-fixed-dock__nav-bar :deep(.hcis-btn__text) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pa-kalibrasi__aksi-dialog {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .pa-dialog-uraian__hero {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
    }

    .pa-dialog-uraian__icon-bubble {
        width: 2rem;
        height: 2rem;
    }

    .pa-dialog-uraian__hero-title {
        font-size: 0.9rem;
    }

    .pa-dialog-uraian__hero-desc {
        font-size: 0.8rem;
    }

    .pa-dialog-uraian__level-item {
        padding: 0.5rem 0.75rem;
    }

    .pa-dialog-uraian__actions {
        width: 100%;
    }

    .pa-dialog-uraian__actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 2.5rem;
    }
}
/* Dialog Batch Banner */
.pa-dialog-batch-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-dialog-batch-icon {
    display: flex;
    align-items: center;
    color: var(--hcis-color-text-muted, #64748b);
    margin-top: 0.15rem;
    flex-shrink: 0;
}

.pa-dialog-batch-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.8rem;
    color: var(--hcis-color-text-main, #475569);
    line-height: 1.45;
}

.pa-dialog-batch-content strong {
    color: var(--hcis-color-text-main, #1e293b);
    font-size: 0.84rem;
}

.pa-dialog-participants-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 240px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

/* Detail per orang */
.pa-dialog-orang {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    transition: border-color 0.15s ease;
}

.pa-dialog-orang[open] {
    border-color: #cbd5e1;
}

.pa-dialog-orang__kepala {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    cursor: pointer;
    list-style: none;
    user-select: none;
}

.pa-dialog-orang__kepala::-webkit-details-marker {
    display: none;
}

.pa-dialog-orang__identitas {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-dialog-orang__avatar {
    width: 2rem;
    height: 2rem;
    font-size: 0.72rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.pa-dialog-orang__nama-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
}

.pa-dialog-orang__nama {
    font-size: 0.85rem;
    color: var(--hcis-color-text-main, #1e293b);
}

.pa-dialog-orang__sub {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-dialog-orang__pills {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.pa-dialog-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}

.pa-dialog-pill--skor {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.pa-dialog-pill--uraian {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.pa-dialog-orang__panah {
    color: var(--hcis-color-text-muted, #94a3b8);
    transition: transform 0.2s ease;
}

.pa-dialog-orang[open] .pa-dialog-orang__panah {
    transform: rotate(180deg);
}

.pa-dialog-orang__detail {
    padding: 0 0.85rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-dialog-badge-revisi {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pa-kalibrasi__kaki-kanan {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Modal Detail Kompetensi */
.pa-dialog-comp-detail {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.pa-dialog-comp-tags {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-dialog-comp-section {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-dialog-comp-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-color-text-main, #1e293b);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-dialog-comp-text {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-color-text-main, #334155);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.75rem 0.95rem;
}

.pa-dialog-comp-perilaku {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.75rem 0.95rem;
}

.pa-matriks-alat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-bottom: 0.6rem;
}

.pa-matriks-alat__ringkas {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
}
</style>
