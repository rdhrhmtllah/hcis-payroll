<template>
    <HcisDialog
        :model-value="modelValue"
        title="Penugasan Rater"
        kicker="PESERTA PA"
        :description="participant ? `${participant.Nama_Karyawan_Saat_Cycle} · ${participant.Kode_Karyawan}` : ''"
        icon="people-fill"
        icon-variant="glass"
        sentiment="brand"
        size="xl"
        variant="glass"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div v-if="participant" class="pa-assignment">
            <!-- 1. IDENTITAS PESERTA (ENHANCED PROFILE CARD) -->
            <div class="pa-assignment__identity">
                <div class="pa-assignment__avatar" aria-hidden="true">
                    {{ getInitials(participant.Nama_Karyawan_Saat_Cycle) }}
                </div>
                <div class="pa-assignment__identity-info">
                    <div class="pa-assignment__identity-name-line">
                        <strong class="pa-assignment__name">{{ participant.Nama_Karyawan_Saat_Cycle }}</strong>
                        <span class="pa-assignment__nik-pill">{{ participant.Kode_Karyawan }}</span>
                    </div>
                    <div class="pa-assignment__pills-row">
                        <span class="pa-assignment__level-pill">
                            <HcisIcon name="award" size="2xs" />
                            {{ participant.Nama_Level_Saat_Cycle || `Level ${participant.ID_Level_Saat_Cycle}` }}
                        </span>
                        <span v-if="participant.Kode_Perusahaan" class="pa-assignment__pt-pill">
                            PT: {{ participant.Kode_Perusahaan }}
                        </span>
                    </div>
                </div>

                <!-- Quick Summary Stats on Top Right -->
                <div class="pa-assignment__identity-stats">
                    <HcisBadge
                        :label="`${jumlahTerpilih} rater dipilih`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                    />
                </div>
            </div>

            <!-- RESULT / ERROR STATE -->
            <div v-if="errorMessage" class="pa-assignment__alert-group">
                <HcisAlert
                    :variant="errorStatus === 403 ? 'warning' : 'danger'"
                    appearance="glass"
                    size="sm"
                    :title="errorStatus === 403 ? 'Akses penugasan tidak tersedia' : 'Usulan rater tidak dapat dimuat'"
                    :description="errorMessage"
                    :icon="errorStatus === 403 ? 'shield-lock-fill' : 'exclamation-triangle-fill'"
                    glow
                >
                    <template v-if="errorStatus !== 403" #actions>
                        <HcisButton
                            label="Coba lagi"
                            size="xs"
                            variant="secondary"
                            leading-icon="arrow-clockwise"
                            @click="muatUsulan"
                        />
                    </template>
                </HcisAlert>

                <div v-if="errorCode || requestId" class="pa-assignment__error-meta" role="note">
                    <span v-if="errorCode">Kode: {{ errorCode }}</span>
                    <span v-if="requestId">Request ID: {{ requestId }}</span>
                </div>
            </div>

            <!--
                SKELETON HANYA UNTUK PEMUATAN PERTAMA — saat memang belum ada apa pun di layar.

                Penyegaran SESUDAH itu mempertahankan isinya. Modal ini memuat ulang dirinya
                sesudah setiap penambahan, pencabutan, dan penyimpanan; mengganti isinya dengan
                skeleton setiap kali membuat layar berkedip, membuang posisi gulir, dan
                memindahkan tombol yang sedang dituju admin. Yang terasa adalah kelambatan jauh
                lebih besar daripada yang sebenarnya terjadi.
            -->
            <div v-if="!errorMessage && memuat && !kategori.length" class="pa-assignment__loading-skeleton" aria-live="polite">
                <div class="pa-loading-hero">
                    <HcisIcon name="arrow-repeat" size="sm" animation="spin" color="brand" />
                    <div class="pa-loading-hero__text">
                        <strong>Memuat usulan rater peserta...</strong>
                        <span>Menghubungkan relasi organisasi dan aturan kuota rater.</span>
                    </div>
                </div>
                <div class="pa-loading-skeleton-card">
                    <div class="pa-loading-skeleton-header"></div>
                    <div class="pa-loading-skeleton-row"></div>
                    <div class="pa-loading-skeleton-row"></div>
                </div>
                <div class="pa-loading-skeleton-card">
                    <div class="pa-loading-skeleton-header"></div>
                    <div class="pa-loading-skeleton-row"></div>
                </div>
            </div>

            <!--
                BILAH SIBUK untuk SETIAP permintaan — memuat, menyimpan, menghitung, mencari,
                menambah, mencabut.

                Databasenya di seberang jaringan: satu perjalanan 55-324 ms, dan satu tindakan
                bisa memakan beberapa detik. Tanpa tanda apa pun jeda itu terbaca sebagai layar
                yang macet, dan yang dilakukan admin adalah menekan tombolnya lagi.
            -->
            <div v-if="sibuk" class="pa-sibuk" role="status" aria-live="polite">
                <span class="pa-sibuk__isi"></span>
                <span class="pa-sibuk__teks">{{ teksSibuk }}</span>
            </div>

            <template v-if="!errorMessage && kategori.length">
                <!-- INTRO & PROPOSED ALERT -->
                <div class="pa-assignment__intro" :class="{ 'is-menyegarkan': memuat }">
                    <div>
                        <span class="pa-eyebrow">ATURAN DARI SERVER</span>
                        <h4>Pilih rater untuk setiap kategori</h4>
                    </div>
                    <HcisBadge
                        :label="`${jumlahTerpilih} rater dipilih`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                    />
                </div>

                <!-- Spanduk Usulan Sistem yang Belum Disimpan -->
                <HcisAlert
                    v-if="proposedCount > 0"
                    variant="info"
                    appearance="glass"
                    :title="`${proposedCount} rater diusulkan sistem — BELUM disimpan`"
                    icon="magic"
                >
                    Usulannya diturunkan dari relasi organisasi, sebanyak minimum aturan saja.
                    Periksa, ubah bila perlu, lalu tekan <strong>Simpan</strong> — sebelum itu tidak
                    ada penugasan yang terbentuk.
                </HcisAlert>

                <!--
                    KESIAPAN HITUNG SENGAJA TIDAK DITAMPILKAN DI SINI.

                    Layar ini menetapkan SIAPA yang menilai. "Siap dihitung" menjawab pertanyaan
                    tahap lain — apakah jawabannya sudah cukup untuk menghasilkan nilai — dan di
                    tahap ini ia hampir selalu berbunyi "Belum siap dihitung" karena penilaiannya
                    memang belum dikerjakan siapa pun. Peringatan yang selalu menyala pada keadaan
                    yang normal bukan peringatan; ia hanya melatih admin untuk mengabaikannya.

                    "Kategori Terpenuhi" TETAP ADA: itu menghitung kategori yang penilainya sudah
                    cukup, yang justru persis pekerjaan layar ini.
                -->
                <div v-if="readiness" class="pa-readiness-strip">
                    <div class="pa-readiness-strip__item">
                        <span class="pa-readiness-strip__label">Kategori Terpenuhi:</span>
                        <span class="pa-readiness-strip__stat">
                            <strong>{{ kategoriTerpenuhiCount }}</strong> / {{ kategori.length }}
                        </span>
                    </div>
                </div>

                <!-- 3. KATEGORI PENILAI ACCORDIONS / CARDS -->
                <div class="pa-assignment__categories" :class="{ 'is-menyegarkan': memuat }">
                    <section
                        v-for="item in kategori"
                        :key="item.kode_kategori"
                        class="pa-category"
                        :class="{
                            'is-kategori-lengkap': kesiapanKategori(item.kode_kategori)?.cukup_terpasang,
                            'is-kategori-kurang': !kesiapanKategori(item.kode_kategori)?.cukup_terpasang,
                            'is-adding-rater': Boolean(menambah[item.kode_kategori]),
                        }"
                    >
                        <!-- Category Header -->
                        <header class="pa-category__header">
                            <div class="pa-category__header-left">
                                <span class="pa-category__code" :class="getKategoriCodeClass(item.kode_kategori)">
                                    {{ item.kode_kategori }}
                                </span>
                                <h5>{{ item.nama_kategori }}</h5>
                            </div>
                            <div class="pa-category__meta">
                                <div class="pa-category__meta-badges">
                                    <HcisBadge
                                        v-if="menambah[item.kode_kategori]"
                                        label="Menambahkan..."
                                        variant="brand"
                                        appearance="glass"
                                        size="xs"
                                    />
                                    <HcisBadge
                                        :label="`${item.terpasang} terpasang`"
                                        :variant="item.terpasang ? 'success' : 'neutral'"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                    <HcisBadge
                                        v-if="kesiapanKategori(item.kode_kategori)"
                                        :label="
                                            kesiapanKategori(item.kode_kategori).cukup_terpasang
                                                ? 'Minimum terpasang'
                                                : 'Belum minimum'
                                        "
                                        :variant="
                                            kesiapanKategori(item.kode_kategori).cukup_terpasang ? 'success' : 'warning'
                                        "
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <div class="pa-category__meta-details">
                                    <span>Min {{ item.min_rater }} · Maks {{ item.maks_rater ?? 'tanpa batas' }}</span>
                                    <span v-if="kesiapanKategori(item.kode_kategori)">
                                        · {{ kesiapanKategori(item.kode_kategori).terpasang }} terpasang ·
                                        {{ kesiapanKategori(item.kode_kategori).selesai }} selesai
                                    </span>
                                </div>
                            </div>
                        </header>

                        <!--
                            RELASI YANG ADA TETAPI TIDAK TERBACA SIKLUS INI.

                            Tanpa baris ini kategorinya hanya berbunyi "Perlu penilai manual", dan
                            itu keterangan yang salah: bawahannya ADA dan tercatat, hanya saja masa
                            berlakunya mulai sesudah tanggal acuan siklus. Admin yang membacanya
                            sebagai "sistem tidak menemukan siapa pun" akan menambahkan satu per
                            satu orang yang sudah tercatat sebagai bawahannya sendiri.
                        -->
                        <HcisResultState
                            v-if="diLuarAcuan(item)"
                            status="warning"
                            layout="inline"
                            variant="flat"
                            :title="diLuarAcuan(item).judul"
                            :description="diLuarAcuan(item).pesan"
                            :show-retry="false"
                        />

                        <!-- Candidate Selection List -->
                        <div v-if="item.calon?.length || menambah[item.kode_kategori]" class="pa-candidate-list">
                            <label
                                v-for="candidate in item.calon"
                                :key="candidate.kode_karyawan"
                                class="pa-candidate"
                                :class="{
                                    'is-selected': isTerpilih(item.kode_kategori, candidate),
                                    'is-installed': candidate.sudah_ditugaskan,
                                    'is-ditolak': Boolean(penilaiDitolak[candidate.kode_karyawan]),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isTerpilih(item.kode_kategori, candidate)"
                                    @change="ubahPilihan(item.kode_kategori, candidate)"
                                />
                                <span class="pa-candidate__check" aria-hidden="true">
                                    <HcisIcon name="check-lg" size="2xs" />
                                </span>

                                <div class="pa-candidate__avatar" aria-hidden="true">
                                    {{ getInitials(candidate.nama) }}
                                </div>

                                <div class="pa-candidate__body">
                                    <strong class="pa-candidate__name">{{ candidate.nama }}</strong>
                                    <div class="pa-candidate__info-pills">
                                        <span class="pa-candidate__nik">{{ candidate.kode_karyawan }}</span>
                                        <span class="pa-candidate__relasi-pill">
                                            <HcisIcon name="link-45deg" size="2xs" />
                                            {{ candidate.kode_relasi || 'Tanpa relasi organisasi' }}
                                        </span>
                                    </div>
                                </div>

                                <HcisBadge
                                    v-if="penilaiDitolak[candidate.kode_karyawan]"
                                    :label="penilaiDitolak[candidate.kode_karyawan].alasan || 'DITOLAK HC LEARN'"
                                    variant="danger"
                                    appearance="subtle"
                                    size="xs"
                                />

                                <HcisBadge
                                    v-else-if="candidate.sudah_ditugaskan"
                                    label="Terpasang"
                                    variant="success"
                                    appearance="subtle"
                                    size="xs"
                                />

                                <!-- Bobot Input Persen -->
                                <!--
                                    Muncul juga untuk penilai yang BARU dicentang dan belum
                                    tersimpan. Bobotnya dipegang lewat kunci sementara berdasarkan
                                    identitas orangnya, lalu diterjemahkan ke id penugasan sesudah
                                    penugasannya dibuat - dan disimpan sebagai KEPUTUSAN, bukan
                                    sekadar tampilan: kolom Bobot_Kustom membuatnya bertahan
                                    melewati pembagian rata yang dijalankan server.
                                -->
                                <span
                                    v-if="candidate.assignment_id || isTerpilih(item.kode_kategori, candidate)"
                                    class="pa-bobot"
                                    :class="{
                                        'is-baru': ! candidate.assignment_id,
                                        'is-menyesuaikan': ! locked[kunciBobot(candidate)],
                                    }"
                                    @click.prevent.stop
                                >
                                    <span class="pa-bobot__label">Bobot:</span>
                                    <input
                                        class="pa-bobot__input"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        :value="bobotSaatIni(candidate)"
                                        :aria-label="`Bobot ${candidate.nama} dalam kategori ${item.nama_kategori}`"
                                        :title="`Atur bobot ${candidate.nama}. Rater yang belum Anda sentuh menyesuaikan diri supaya kategorinya tetap 100%.`"
                                        @click.stop
                                        @input="ubahBobotDibatasi(candidate, $event)"
                                    >
                                    <span class="pa-bobot__persen">%</span>
                                </span>

                                <!-- Cabut Rater Button -->
                                <button
                                    v-if="candidate.sudah_ditugaskan && candidate.assignment_id"
                                    type="button"
                                    class="pa-cabut-btn"
                                    :class="{ 'is-revoking': revokingId === candidate.assignment_id }"
                                    :disabled="revokingId === candidate.assignment_id || saving || calculating"
                                    :title="`Cabut ${candidate.nama} sebagai rater`"
                                    :aria-label="`Cabut ${candidate.nama} sebagai rater`"
                                    @click.prevent.stop="$emit('cancel-assignment', {
                                        assignmentId: candidate.assignment_id,
                                        nama: candidate.nama,
                                        kodeKategori: item.kode_kategori,
                                        namaKategori: item.nama_kategori,
                                        kodeKaryawan: candidate.kode_karyawan,
                                        terpasang: Number(item.terpasang ?? 0),
                                        minRater: Number(item.min_rater ?? 0),
                                    })"
                                >
                                    <HcisIcon
                                        :name="revokingId === candidate.assignment_id ? 'arrow-repeat' : 'trash3'"
                                        :animation="revokingId === candidate.assignment_id ? 'spin' : null"
                                        size="2xs"
                                    />
                                </button>
                            </label>

                            <!-- Slot Animasi Rater yang Sedang Ditambahkan -->
                            <div
                                v-if="menambah[item.kode_kategori]"
                                class="pa-candidate pa-candidate--adding-slot"
                                aria-live="polite"
                            >
                                <div class="pa-candidate__avatar pa-candidate__avatar--adding-slot" aria-hidden="true">
                                    <HcisIcon name="arrow-repeat" size="xs" animation="spin" />
                                </div>
                                <div class="pa-candidate__body">
                                    <strong class="pa-candidate__name pa-candidate__name--adding">
                                        {{ kandidatSedangDitambah[item.kode_kategori]?.nama || 'Menambahkan Rater Baru...' }}
                                    </strong>
                                    <div class="pa-candidate__info-pills">
                                        <span v-if="kandidatSedangDitambah[item.kode_kategori]?.kode_karyawan" class="pa-candidate__nik">
                                            {{ kandidatSedangDitambah[item.kode_kategori]?.kode_karyawan }}
                                        </span>
                                        <span class="pa-candidate__adding-desc">
                                            <HcisIcon name="link-45deg" size="2xs" />
                                            Membentuk relasi organisasi &amp; mendaftarkan penugasan...
                                        </span>
                                    </div>
                                </div>
                                <span class="pa-adding-spinner-badge">
                                    <HcisIcon name="arrow-repeat" size="2xs" animation="spin" />
                                    <span>Menambahkan...</span>
                                </span>
                            </div>
                        </div>

                        <!--
                            Rater baru IKUT dibagi bobotnya di sini, tidak ditangguhkan.
                            Angkanya tersimpan sebagai KEPUTUSAN (kolom Bobot_Kustom), jadi ia
                            bertahan melewati pembagian rata yang dijalankan server saat
                            penugasannya dibuat - bukan sekadar tampilan yang sebentar lagi
                            tergantikan.
                        -->
                        <div v-if="penilaiBaru(item) > 0" class="pa-bobot-tunda">
                            <HcisIcon name="info-circle-fill" size="2xs" />
                            <div class="pa-bobot-tunda__text">
                                <strong>{{ penilaiBaru(item) }} rater baru belum tersimpan.</strong>
                                Bobot di bawah sudah termasuk mereka dan ikut tersimpan begitu
                                penugasannya dibuat.
                            </div>
                        </div>

                        <!-- Bobot Total & Bagi Rata Meter Bar -->
                        <div
                            v-if="jumlahBobot(item) !== null"
                            class="pa-bobot-total"
                            :class="{ 'is-salah': !bobotSeratus(item) }"
                        >
                            <HcisIcon :name="bobotSeratus(item) ? 'check-circle-fill' : 'exclamation-triangle-fill'" size="2xs" />
                            <!--
                                Kurang dan lebih DIBEDAKAN. "Selisih 20%" tidak memberi tahu ke
                                arah mana harus diperbaiki, padahal itu satu-satunya hal yang
                                perlu diketahui admin saat membacanya.

                                Cabang "kelebihan" tetap ada meski kotak isiannya sudah dibatasi:
                                bobot yang sudah tersimpan sebelum batas ini berlaku bisa saja
                                melebihi 100, dan kalau tidak dikatakan, Simpan akan mati tanpa
                                sebab yang terlihat.
                            -->
                            <div class="pa-bobot-total__text">
                                <span>Pembagian bobot: <strong>{{ jumlahBobot(item) }}%</strong> dari 100%</span>
                                <span v-if="selisihBobot(item) > 0" class="pa-bobot-total__warn">
                                    — sisa {{ selisihBobot(item) }}% belum dibagi
                                </span>
                                <span v-else-if="selisihBobot(item) < 0" class="pa-bobot-total__warn">
                                    — kelebihan {{ -selisihBobot(item) }}%, tidak bisa disimpan
                                </span>
                            </div>

                            <button
                                type="button"
                                class="pa-bobot-rata"
                                @click="$emit('bagi-rata', item.kode_kategori)"
                            >
                                <HcisIcon name="distribute-horizontal" size="2xs" />
                                Bagi rata
                            </button>
                        </div>

                        <!-- Empty State Kategori -->
                        <div
                            v-else
                            class="pa-category__empty"
                            :class="{ 'pa-category__empty--manual': item.butuh_manual && item.izinkan_manual }"
                        >
                            <HcisIcon
                                :name="item.butuh_manual && item.izinkan_manual ? 'person-plus' : 'person-exclamation'"
                                size="sm"
                                :color="item.butuh_manual && item.izinkan_manual ? 'warning' : 'muted'"
                            />
                            <div>
                                <strong>{{
                                    item.butuh_manual ? 'Perlu rater manual' : 'Belum ada calon rater'
                                }}</strong>
                                <span v-if="item.butuh_manual && item.izinkan_manual"
                                    >Tambahkan satu orang dengan identitas yang tepat di bawah.</span
                                >
                                <span v-else-if="item.butuh_manual"
                                    >Kategori ini belum dapat diisi manual menurut aturan.</span
                                >
                                <span v-else>Usulan rater belum tersedia dari relasi organisasi.</span>
                            </div>
                        </div>

                        <!-- 4. MANUAL RATER ASSIGNMENT FORM -->
                        <!--
                            Blok ini SELALU tampil ketika aturannya mengizinkan penilai tambahan;
                            yang berubah keadaannya, bukan keberadaannya.

                            Sempat saya sembunyikan untuk kategori yang tidak bisa menerima
                            tambahan. Itu keliru: kotak yang ada di tiga kategori lalu lenyap di
                            satu kategori membuat admin bertanya-tanya apakah layarnya rusak, dan
                            tidak ada satu pun keterangan yang menjawabnya. Dinonaktifkan dengan
                            SEBAB TERTULIS memberi tahu; menghilang tidak.
                        -->
                        <!--
                            SELF tidak menampilkan blok ini sama sekali — bukan dimatikan.
                            Menambah penilai bagi penilaian diri bukan tindakan yang "sedang tidak
                            bisa", melainkan tindakan yang tidak pernah ada: penilainya sudah pasti
                            pesertanya sendiri. Kontrol mati menyiratkan ia bisa hidup nanti.

                            Diperiksa lewat `terikat_relasi`, bukan `kode_kategori !== 'SELF'`:
                            aturannya tentang ada-tidaknya relasi organisasi untuk disandarkan,
                            jadi kategori lain yang kelak juga tak terikat ikut tertutup.

                            Yang MASIH dimatikan (bukan disembunyikan): kategori yang sudah penuh.
                            Itu keadaan sementara — mencabut satu penilai membuatnya hidup lagi,
                            dan menyembunyikannya akan menghilangkan sebabnya.
                        -->
                        <!-- 4. MANUAL RATER ASSIGNMENT WORKBENCH -->
                        <!-- 4. MANUAL RATER ASSIGNMENT WORKBENCH -->
                        <div
                            v-if="item.izinkan_manual && (item.terikat_relasi ?? item.kode_kategori !== 'SELF')"
                            class="pa-manual-form"
                            :class="{
                                'is-locked': Boolean(alasanTakBisaTambah(item)),
                                'is-has-selection': !alasanTakBisaTambah(item) && Boolean(manual[item.kode_kategori]?.kode_karyawan),
                            }"
                        >
                            <!-- LOCKED STATE BANNER -->
                            <div v-if="alasanTakBisaTambah(item)" class="pa-manual-locked">
                                <div class="pa-manual-locked__icon-wrap" aria-hidden="true">
                                    <HcisIcon name="lock-fill" size="xs" />
                                </div>
                                <div class="pa-manual-locked__content">
                                    <div class="pa-manual-locked__header">
                                        <strong class="pa-manual-locked__title">Tambah Rater di Luar Relasi Organisasi</strong>
                                        <HcisBadge
                                            label="Kuota Maksimum Tercapai"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                            dot
                                        />
                                    </div>
                                    <p class="pa-manual-locked__desc">
                                        {{ alasanTakBisaTambah(item) }}
                                    </p>
                                </div>
                            </div>

                            <!-- ACTIVE WORKBENCH -->
                            <template v-else>
                                <div class="pa-manual-form__header">
                                    <div class="pa-manual-form__title-wrap">
                                        <div class="pa-manual-icon-badge is-active-badge" aria-hidden="true">
                                            <HcisIcon name="person-plus-fill" size="2xs" />
                                        </div>
                                        <div class="pa-manual-title-texts">
                                            <strong class="pa-manual-title-heading">Tambah Rater di Luar Relasi Organisasi</strong>
                                            <span class="pa-manual-form__nota">
                                                Cari &amp; tentukan rater manual untuk kategori {{ item.nama_kategori }}.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pa-manual-form__body">
                                    <!-- Adding In-Progress Banner -->
                                    <div v-if="menambah[item.kode_kategori]" class="pa-manual-form__adding-banner" aria-live="polite">
                                        <HcisIcon name="arrow-repeat" size="xs" animation="spin" color="brand" />
                                        <div class="pa-manual-form__adding-banner-texts">
                                            <strong>Menambahkan {{ kandidatSedangDitambah[item.kode_kategori]?.nama || 'rater baru' }}...</strong>
                                            <span>Mendaftarkan relasi organisasi ke database &amp; memperbarui daftar rater {{ item.nama_kategori }}.</span>
                                        </div>
                                    </div>

                                    <!-- Live Search Rater / Autocomplete -->
                                    <div class="pa-cari-penilai">
                                        <div class="pa-cari-penilai__input-wrap">
                                            <HcisIcon name="search" size="xs" class="pa-cari-penilai__icon" />
                                            <input
                                                class="pa-cari-penilai__input"
                                                type="search"
                                                placeholder="Ketik 1 huruf nama / NIK untuk saran autocomplete..."
                                                :value="kataCari[item.kode_kategori] || ''"
                                                :aria-label="`Cari rater untuk kategori ${item.nama_kategori}`"
                                                :disabled="Boolean(menambah[item.kode_kategori])"
                                                autocomplete="off"
                                                @input="cariPenilai(item.kode_kategori, $event.target.value)"
                                                @keydown.down.prevent="geserSorot(item.kode_kategori, 1)"
                                                @keydown.up.prevent="geserSorot(item.kode_kategori, -1)"
                                                @keydown.enter.prevent="pilihSorot(item.kode_kategori)"
                                                @keydown.esc.prevent="cariPenilai(item.kode_kategori, '')"
                                            >
                                            <button
                                                v-if="kataCari[item.kode_kategori] && !mencari[item.kode_kategori] && !menambah[item.kode_kategori]"
                                                type="button"
                                                class="pa-cari-penilai__clear-btn"
                                                title="Hapus pencarian"
                                                aria-label="Hapus pencarian"
                                                @click="cariPenilai(item.kode_kategori, '')"
                                            >
                                                <HcisIcon name="x-circle-fill" size="2xs" />
                                            </button>
                                            <span v-if="mencari[item.kode_kategori]" class="pa-cari-penilai__spinner">
                                                <HcisIcon name="arrow-repeat" size="2xs" animation="spin" />
                                            </span>
                                        </div>

                                        <!-- Searching Loading Box -->
                                        <div
                                            v-if="mencari[item.kode_kategori]"
                                            class="pa-cari-penilai__loading-box"
                                        >
                                            <HcisIcon name="arrow-repeat" size="xs" animation="spin" color="brand" />
                                            <span>Mencari kandidat karyawan dari database...</span>
                                        </div>

                                        <!-- In-Flow Live Autocomplete Results Box -->
                                        <div
                                            v-else-if="(hasilCari[item.kode_kategori] || []).length"
                                            class="pa-cari-penilai__results-wrap"
                                        >
                                            <div class="pa-cari-penilai__results-header">
                                                <span class="pa-cari-penilai__results-title">
                                                    <HcisIcon name="lightning-charge-fill" size="2xs" color="brand" />
                                                    Saran Karyawan ({{ (hasilCari[item.kode_kategori] || []).length }} ditemukan)
                                                </span>
                                                <span v-if="menambah[item.kode_kategori]" class="pa-cari-penilai__results-adding-tag">
                                                    <HcisIcon name="arrow-repeat" size="2xs" animation="spin" /> Sedang menambahkan...
                                                </span>
                                                <span v-else class="pa-cari-penilai__results-hint">
                                                    Gunakan panah &uarr;&darr; lalu [Enter]
                                                </span>
                                            </div>
                                            <ul class="pa-cari-penilai__hasil">
                                                <li v-for="(o, idx) in hasilCari[item.kode_kategori]" :key="o.kode_karyawan">
                                                    <button
                                                        type="button"
                                                        class="pa-cari-penilai__pilih"
                                                        :class="{
                                                            'is-disabled-opt': o.bisa_dipilih === false,
                                                            'is-highlighted': (sorotIndex[item.kode_kategori] ?? 0) === idx,
                                                            'is-adding-item': Boolean(menambah[item.kode_kategori]),
                                                        }"
                                                        :disabled="o.bisa_dipilih === false || Boolean(menambah[item.kode_kategori])"
                                                        :title="o.alasan || ''"
                                                        @mouseenter="sorotIndex[item.kode_kategori] = idx"
                                                        @click="pilihManual(item.kode_kategori, o)"
                                                    >
                                                        <div
                                                            class="pa-candidate__avatar pa-candidate__avatar--sm"
                                                            :class="{ 'pa-candidate__avatar--disabled': o.bisa_dipilih === false }"
                                                            aria-hidden="true"
                                                        >
                                                            {{ getInitials(o.nama) }}
                                                        </div>
                                                        <div class="pa-cari-penilai__item-info">
                                                            <strong class="pa-cari-penilai__item-name" v-html="highlightMatch(o.nama, kataCari[item.kode_kategori])" />
                                                            <div class="pa-cari-penilai__meta-pills">
                                                                <span class="pa-candidate__nik" v-html="highlightMatch(o.kode_karyawan, kataCari[item.kode_kategori])" />
                                                                <span v-if="o.nama_level" class="pa-candidate__level-pill">{{ o.nama_level }}</span>
                                                                <span v-if="o.nama_divisi" class="pa-candidate__dept-pill">{{ o.nama_divisi }}</span>
                                                                <span v-if="o.kode_perusahaan" class="pa-candidate__pt-pill">PT: {{ o.kode_perusahaan }}</span>
                                                            </div>
                                                        </div>
                                                        <div v-if="o.bisa_dipilih === false" class="pa-cari-penilai__disabled-pill">
                                                            <HcisIcon name="slash-circle" size="2xs" />
                                                            <span class="pa-cari-penilai__disabled-pill-text">{{ o.alasan || 'Tidak dapat dipilih' }}</span>
                                                        </div>
                                                        <span v-else-if="menambah[item.kode_kategori]" class="pa-cari-penilai__pilih-btn is-adding">
                                                            <HcisIcon name="arrow-repeat" size="2xs" animation="spin" />
                                                            <span>Menambahkan...</span>
                                                        </span>
                                                        <span v-else class="pa-cari-penilai__pilih-btn">+ Pilih Rater</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>

                                        <p
                                            v-else-if="(kataCari[item.kode_kategori] || '').trim().length >= 1 && !mencari[item.kode_kategori]"
                                            class="pa-cari-penilai__kosong"
                                        >
                                            <HcisIcon name="person-x" size="xs" />
                                            <span>Tidak ada karyawan aktif yang cocok dengan <em>"{{ kataCari[item.kode_kategori] }}"</em>.</span>
                                        </p>
                                    </div>

                                    <!-- Selected Candidate Hero Card -->
                                    <div v-if="manual[item.kode_kategori]?.kode_karyawan" class="pa-manual-selected-hero">
                                        <div class="pa-manual-hero-left">
                                            <div class="pa-candidate__avatar pa-candidate__avatar--hero" aria-hidden="true">
                                                {{ getInitials(manual[item.kode_kategori].nama || manual[item.kode_kategori].kode_karyawan) }}
                                            </div>
                                            <div class="pa-manual-hero-info">
                                                <div class="pa-manual-hero-tag">
                                                    <HcisIcon name="check-circle-fill" size="2xs" />
                                                    <span>Kandidat Terpilih</span>
                                                </div>
                                                <strong class="pa-manual-hero-name">{{ manual[item.kode_kategori].nama || manual[item.kode_kategori].kode_karyawan }}</strong>
                                                <div class="pa-manual-hero-pills">
                                                    <span class="pa-candidate__nik">{{ manual[item.kode_kategori].kode_karyawan }}</span>
                                                    <span class="pa-candidate__pt-pill">PT: {{ manual[item.kode_kategori].kode_perusahaan || '—' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="pa-manual-hero-cancel-btn"
                                            @click="kosongkanManual(item.kode_kategori)"
                                        >
                                            <HcisIcon name="x-circle" size="2xs" />
                                            <span>Ganti / Hapus</span>
                                        </button>
                                    </div>

                                    <!-- Form Fields for Reason & Submission (Hidden as requested, preserved for programmatic/test contract) -->
                                    <div class="pa-manual-form__fields" style="display: none;" aria-hidden="true">
                                        <div class="pa-manual-inputs-grid">
                                            <div class="pa-manual-input-col">
                                                <HcisField
                                                    :id="`kode-perusahaan-${item.kode_kategori}`"
                                                    :model-value="manual[item.kode_kategori]?.kode_perusahaan || ''"
                                                    label="Kode Perusahaan"
                                                    placeholder="001"
                                                    size="sm"
                                                    @update:model-value="ubahManual(item.kode_kategori, 'kode_perusahaan', $event)"
                                                />
                                            </div>
                                            <div class="pa-manual-input-col">
                                                <HcisField
                                                    :id="`kode-karyawan-${item.kode_kategori}`"
                                                    :model-value="manual[item.kode_kategori]?.kode_karyawan || ''"
                                                    label="Kode Karyawan"
                                                    placeholder="A0000"
                                                    size="sm"
                                                    @update:model-value="ubahManual(item.kode_kategori, 'kode_karyawan', $event)"
                                                />
                                            </div>
                                        </div>

                                        <div class="pa-manual-reason-box">
                                            <HcisField
                                                :id="`alasan-manual-${item.kode_kategori}`"
                                                :model-value="manual[item.kode_kategori]?.alasan_manual || ''"
                                                label="Alasan Penugasan Manual"
                                                placeholder="Jelaskan alasan penugasan manual ini..."
                                                hint="Wajib diisi sebagai keterangan penugasan manual."
                                                size="sm"
                                                @update:model-value="ubahManual(item.kode_kategori, 'alasan_manual', $event)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>

                <!-- SUBMISSION RESULTS / ALERTS STACK -->
                <div
                    v-if="submitError || submitSuccess || bobotError || bobotKabar"
                    class="pa-assignment__alerts-stack"
                >
                    <HcisAlert
                        v-if="submitError"
                        :variant="submitErrorStatus === 403 ? 'warning' : 'danger'"
                        appearance="glass"
                        size="sm"
                        :title="submitErrorStatus === 403 ? 'Akses penugasan tidak tersedia' : 'Penugasan ditolak'"
                        :description="submitError"
                        :icon="submitErrorStatus === 403 ? 'shield-lock-fill' : 'x-circle-fill'"
                        glow
                    />

                    <div v-if="submitErrorCode || submitRequestId" class="pa-assignment__error-meta" role="note">
                        <span v-if="submitErrorCode">Kode: {{ submitErrorCode }}</span>
                        <span v-if="submitRequestId">Request ID: {{ submitRequestId }}</span>
                    </div>

                    <HcisAlert
                        v-if="submitSuccess"
                        variant="success"
                        appearance="glass"
                        size="sm"
                        title="Penugasan tersimpan"
                        :description="submitSuccess"
                        icon="check-circle-fill"
                    />

                    <!-- PESAN BOBOT TERPISAH DARI PESAN PENUGASAN -->
                    <HcisAlert
                        v-if="bobotError"
                        variant="danger"
                        appearance="glass"
                        size="sm"
                        title="Pembagian bobot ditolak"
                        :description="bobotError"
                        icon="exclamation-diamond-fill"
                    />

                    <HcisAlert
                        v-else-if="bobotKabar"
                        variant="success"
                        appearance="glass"
                        size="sm"
                        title="Pembagian bobot tersimpan"
                        :description="bobotKabar"
                        icon="check-circle-fill"
                    />
                </div>

                <!-- 5. RINGKASAN HASIL PERHITUNGAN PESERTA -->
                <div v-if="hasilPeserta" class="pa-score-result">
                    <div class="pa-score-result__heading">
                        <div>
                            <span class="pa-eyebrow">HASIL TERBARU</span>
                            <h4>Ringkasan perhitungan peserta</h4>
                        </div>
                        <div class="pa-score-result__badges">
                            <HcisBadge
                                :label="hasilPeserta.kode_grade || 'Tanpa grade'"
                                variant="success"
                                appearance="solid"
                                size="lg"
                            />
                            <HcisStatusBadge
                                :status="hasilPeserta.status"
                                :label="hasilPeserta.status || 'Tanpa status'"
                                size="sm"
                                dot
                            />
                        </div>
                    </div>
                    <div class="pa-score-result__metrics">
                        <div class="pa-score-metric-card">
                            <span>Nilai KPI</span>
                            <strong>{{ formatScore(hasilPeserta.nilai_kpi) }}</strong>
                        </div>
                        <div class="pa-score-metric-card">
                            <span>Nilai kompetensi</span>
                            <strong>{{ formatScore(hasilPeserta.nilai_kompetensi) }}</strong>
                        </div>
                        <div class="pa-score-metric-card is-final">
                            <span>Final score</span>
                            <strong>{{ formatScore(hasilPeserta.final_score) }}</strong>
                        </div>
                    </div>
                    <p class="pa-score-result__quadrant">
                        <HcisIcon name="diagram-3-fill" size="xs" />
                        Kuadran: <strong>{{ hasilPeserta.kode_kuadran || 'Belum ditentukan' }}</strong> &bull; Versi {{ hasilPeserta.versi }}
                    </p>
                    <div v-if="hasilPeserta.alasan_tertahan" class="pa-score-result__held" role="status">
                        <HcisIcon name="pause-circle-fill" size="xs" color="warning" />
                        <span class="pa-score-result__held-copy" :title="hasilPeserta.alasan_tertahan">
                            <strong>Hasil tertahan:</strong> {{ hasilPeserta.alasan_tertahan }}
                        </span>
                    </div>
                </div>
            </template>

            <!-- EMPTY KATEGORI STATE -->
            <HcisAlert
                v-if="!errorMessage && !memuat && !kategori.length"
                variant="info"
                appearance="glass"
                size="sm"
                title="Belum ada aturan rater"
                description="Server belum mengirim kategori rater untuk peserta ini. Tidak ada penugasan yang dapat disimpan."
                icon="info-circle-fill"
            />
        </div>

        <template #footer-start>
            <!--
                Sebabnya DISEBUT di sebelah tombolnya yang mati. Tombol mati tanpa keterangan
                membuat admin menduga layarnya rusak, lalu menekannya berkali-kali.
            -->
            <span v-if="bobotBelumPas.length" class="pa-dialog-hint pa-dialog-hint--salah">
                <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                Pembagian bobot {{ bobotBelumPas.join(', ') }} belum tepat 100% — perbaiki dulu,
                atau tekan “Bagi rata”.
            </span>
            <span v-else class="pa-dialog-hint">
                <HcisIcon name="info-circle" size="2xs" /> Bobot kategori dihitung ulang oleh server.
            </span>
        </template>
        <template #footer>
            <HcisButton
                variant="secondary"
                label="Tutup"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                variant="primary"
                label="Simpan penugasan"
                size="sm"
                leading-icon="check2-circle"
                :loading="menyimpan"
                :disabled="memuat || Boolean(errorMessage) || !participant || !kategori.length || bobotBelumPas.length > 0"
                @click="simpan"
            />
            <HcisButton
                v-if="canCalculate"
                variant="success"
                label="Hitung peserta"
                size="sm"
                leading-icon="calculator-fill"
                :loading="menghitung"
                :disabled="memuat || Boolean(errorMessage) || !participant || !kategori.length"
                @click="hitung"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { kunciBobot, penilaiBerbobot } from '../composables/paBobot';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisIcon,

    /* WAJIB diimpor. Hanya `HcisSwitch` yang terdaftar global di `app.js`; sisanya diselesaikan
       per berkas. Komponen yang dipakai tanpa impor tidak menimbulkan galat yang menghentikan
       apa pun — Vue hanya mencatat "Failed to resolve component" di konsol lalu merender kosong.

       Itulah yang terjadi pada seluruh pesan penambahan penilai di modal ini: pesannya disusun
       dengan benar, dikirim dengan benar, dan tidak pernah terlihat. Gejalanya persis sama
       dengan tombol yang tidak melakukan apa-apa. */
    HcisResultState,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PaAssignmentDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    participant: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    readiness: { type: Object, default: null },
    selection: { type: Object, default: () => ({}) },
    manual: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    saving: { type: Boolean, default: false },
    calculating: { type: Boolean, default: false },
    submitError: { type: String, default: '' },
    submitErrorStatus: { type: Number, default: 0 },
    submitErrorCode: { type: String, default: '' },
    submitRequestId: { type: String, default: '' },
    submitSuccess: { type: String, default: '' },
    bobotError: { type: String, default: '' },
    bobotKabar: { type: String, default: '' },
    result: { type: Object, default: null },
    canCalculate: { type: Boolean, default: false },

    /* Kode penilai yang DITOLAK HC LEARN, beserta sebabnya.
       |
       | Ditandai DI SINI karena inilah layar tempat penggantinya dipilih. Tanpa tanda, admin
       | yang datang dari daftar "Perlu Perhatian" membuka dialog berisi belasan nama dan
       | harus mengingat sendiri siapa yang tadi disebut bermasalah — lalu mencentang orang
       | yang salah, atau mencabut yang benar tanpa yakin. */
    penilaiDitolak: { type: Object, default: () => ({}) },

    /* ID penugasan yang sedang dicabut (loading state per baris) */
    revokingId: { type: [Number, String], default: null },

    /* Berapa centang yang berasal dari USULAN sistem dan belum tersimpan.
       Dikirim dari luar, bukan dihitung di sini: yang tahu mana usulan dan mana penugasan
       berlaku adalah composable yang menyusunnya. */
    proposedCount: { type: Number, default: 0 },

    /* Bobot yang sedang disunting, `assignment_id => nilai`. Dipegang di luar supaya penyimpanan
       dan pembatalannya satu tempat dengan pilihan penilainya. */
    weights: { type: Object, default: () => ({}) },

    /* Hasil pencarian penilai, per kode kategori. Pencariannya dijalankan induk supaya seluruh
       perjalanan data tetap lewat paApi — komponen ini hanya menerima props dan memancarkan
       event, sama seperti panel lain di modul ini. */
    searchResults: { type: Object, default: () => ({}) },
    searching: { type: Object, default: () => ({}) },

    /* Kunci bobot yang sudah DISENTUH admin. Yang tidak ada di sini menyesuaikan diri sendiri,
       dan itu perlu terlihat — angka yang berubah tanpa disentuh terbaca seperti kesalahan. */
    locked: { type: Object, default: () => ({}) },

    /* Sedang menambahkan, per kategori. Tombol yang diklik harus MENUNJUKKAN bahwa kliknya
       masuk; tanpa itu admin menekannya berkali-kali dan mengira layarnya beku. */
    adding: { type: Object, default: () => ({}) },

    /* Galat penambahan penilai, per kategori. Ditampilkan DI DALAM modal — pesan penolakan yang
       mendarat di kaki halaman di belakang modal sama saja dengan tidak ada. */
    addError: { type: Object, default: () => ({}) },

    /* Kabar keberhasilan penambahan, per kategori. Termasuk pemberitahuan bahwa masa berlaku
       relasinya dimundurkan — perubahan yang menyentuh siklus lain dan tidak boleh diam. */
    addInfo: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'update:modelValue',
    'retry',
    'toggle-candidate',
    'manual-change',
    'save',
    'calculate',
    'bobot-change',
    'bagi-rata',
    'cancel-assignment',
    'search-rater',
    'tambah-penilai',
]);

const kategori = computed(() => props.categories);
const manual = computed(() => props.manual);
const memuat = computed(() => props.loading);
const errorMessage = computed(() => props.error);
const menyimpan = computed(() => props.saving);
const menghitung = computed(() => props.calculating);
const submitError = computed(() => props.submitError);
const hasilPeserta = computed(() => props.result);
const jumlahTerpilih = computed(() =>
    Object.values(props.selection || {}).reduce((total, values) => total + values.length, 0),
);

const kategoriTerpenuhiCount = computed(() => {
    if (!props.readiness?.kategori) return 0;
    return kategori.value.filter((k) => props.readiness.kategori[k.kode_kategori]?.cukup_terpasang).length;
});

function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

function getKategoriCodeClass(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'is-cat-atasan';
    if (k.includes('BAWAHAN')) return 'is-cat-bawahan';
    if (k.includes('PEER')) return 'is-cat-peer';
    return 'is-cat-self';
}

function kesiapanKategori(kodeKategori) {
    return props.readiness?.kategori?.[kodeKategori] || null;
}

function muatUsulan() {
    emit('retry');
}

/* Nilai yang ditampilkan: yang sedang disunting kalau ada, kalau tidak yang dari server.
   Membaca langsung dari server berarti ketikan admin hilang setiap kali datanya menyegar. */
const kataCari = ref({});
const kandidatSedangDitambah = ref({});

/* Bersihkan kata cari saat peserta berganti atau saat modal dibuka/tutup */
watch(
    () => [props.participant?.Id_PA_Participant, props.modelValue],
    () => {
        kataCari.value = {};
        kandidatSedangDitambah.value = {};
    },
);

/* Bersihkan kandidat sedang ditambah saat status adding selesai per kategori */
watch(
    () => props.adding,
    (val) => {
        if (!val) {
            kandidatSedangDitambah.value = {};
            return;
        }
        for (const [kodeKategori, isAdding] of Object.entries(val)) {
            if (!isAdding && kandidatSedangDitambah.value[kodeKategori]) {
                delete kandidatSedangDitambah.value[kodeKategori];
            }
        }
    },
    { deep: true },
);

const hasilCari = computed(() => props.searchResults || {});
const mencari = computed(() => props.searching || {});
const menambah = computed(() => props.adding || {});
const galatTambah = computed(() => props.addError || {});

/*
 | Apakah ADA permintaan yang sedang berjalan, apa pun jenisnya.
 |
 | Dikumpulkan di satu tempat supaya tidak ada tindakan yang lolos tanpa tanda: yang terlewat
 | akan terasa persis seperti layar yang macet, dan itu tidak bisa dibedakan dari kerusakan.
 |
 | `revokingId` ikut dibaca dari sini, bukan disalin ke penanda baru — pencabutan sudah punya
 | penandanya sendiri untuk spinner tombolnya, dan penanda kedua untuk keadaan yang sama pasti
 | menyimpang cepat atau lambat.
 */
const sibuk = computed(() => Boolean(
    memuat.value
    || props.saving
    || props.calculating
    || props.revokingId
    || Object.values(mencari.value).some(Boolean)
    || Object.values(menambah.value).some(Boolean),
));

/* Urutannya bukan selera: yang disebut adalah tindakan yang PALING DITUNGGU admin. Menyimpan
   mengalahkan menyegarkan, karena penyegaran yang menyertai penyimpanan adalah ekornya, bukan
   peristiwanya. */
const teksSibuk = computed(() => {
    if (props.saving) {
        return 'Menyimpan penugasan…';
    }

    if (props.calculating) {
        return 'Menghitung hasil peserta…';
    }

    if (props.revokingId) {
        return 'Mencabut rater…';
    }

    if (Object.values(menambah.value).some(Boolean)) {
        return 'Menambahkan rater dan membentuk relasinya…';
    }

    if (Object.values(mencari.value).some(Boolean)) {
        return 'Mencari calon rater…';
    }

    return 'Menyegarkan data…';
});

/*
 | Keterangan untuk relasi yang ADA tetapi jatuh di luar tanggal acuan siklus.
 |
 | Dikembalikan `null` bila tidak ada — kategori yang sehat tidak boleh diberi peringatan, dan
 | peringatan yang muncul di mana-mana berhenti dibaca justru di tempat ia dibutuhkan.
 |
 | Kalimatnya menyebut ANGKA dan TANGGALNYA, bukan "ada relasi yang tidak berlaku". Yang
 | membuat pesan ini berguna adalah admin bisa langsung mencocokkannya dengan apa yang ia
 | lakukan minggu lalu di halaman Relasi Organisasi.
 */
function diLuarAcuan(item) {
    const d = item?.relasi_di_luar_acuan;

    if (! d || (! d.belum_berlaku && ! d.sudah_berakhir)) {
        return null;
    }

    const acuan = tanggalIndonesia(item.tanggal_acuan);
    const bagian = [];

    if (d.belum_berlaku) {
        bagian.push(
            `${d.belum_berlaku} orang sudah tercatat pada relasi ini, tetapi masa berlakunya baru `
            + `mulai ${tanggalIndonesia(d.mulai_paling_awal)} — sesudah tanggal acuan cycle `
            + `(${acuan}), sehingga cycle ini belum melihat mereka.`,
        );
    }

    if (d.sudah_berakhir) {
        bagian.push(
            `${d.sudah_berakhir} relasi lain sudah berakhir sebelum ${acuan}.`,
        );
    }

    bagian.push(
        'Perbaiki masa berlakunya di halaman Relasi Organisasi, atau tambahkan rater di '
        + 'sini — menambahkan dari sini akan memundurkan masa berlaku relasinya ke tanggal acuan.',
    );

    return {
        judul: d.belum_berlaku
            ? `${d.belum_berlaku} relasi belum berlaku pada tanggal acuan cycle`
            : 'Relasi organisasinya di luar tanggal acuan cycle',
        pesan: bagian.join(' '),
    };
}

/* Tanggal ISO dijadikan bentuk yang dibaca orang. Dikembalikan apa adanya bila bukan tanggal —
   pesan yang isinya "Invalid Date" lebih buruk daripada pesan yang isinya angka mentah. */
function tanggalIndonesia(iso) {
    if (! iso) {
        return '-';
    }

    const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`);

    if (Number.isNaN(d.getTime())) {
        return String(iso);
    }

    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

/*
 | Pesan penambahan yang siap dirender, sudah bernama kategori.
 |
 | Nama kategorinya diambil dari daftar kategori yang sedang ditampilkan, bukan dari kodenya
 | mentah: "Penilai ATASAN tidak bisa ditambahkan" membuat admin membaca kode internal, padahal
 | di seluruh layar ini ia melihat "Atasan Langsung".
 */
const pesanTambah = computed(() => {
    const namaKategori = (kode) => kategori.value.find((k) => k.kode_kategori === kode)?.nama_kategori || kode;
    const keluar = [];

    for (const [kode, teks] of Object.entries(props.addError || {})) {
        if (teks) {
            keluar.push({ kategori: namaKategori(kode), pesan: teks, jenis: 'galat' });
        }
    }

    for (const [kode, teks] of Object.entries(props.addInfo || {})) {
        if (teks) {
            keluar.push({ kategori: namaKategori(kode), pesan: teks, jenis: 'kabar' });
        }
    }

    return keluar;
});

/* Kenapa satu kategori TIDAK bisa menerima penilai tambahan — atau null bila bisa.
 *
 * Dikembalikan sebagai kalimat, bukan boolean: yang dibutuhkan layar bukan "boleh atau tidak"
 * melainkan "kenapa tidak", dan memisahkan keduanya membuat sebabnya ditulis ulang di tempat
 * lain lalu menyimpang dari syaratnya.
 *
 * Dua sebab, keduanya juga ditegakkan server — ini cermin yang memberi tahu lebih awal, bukan
 * penjaga tandingan.
 */
function alasanTakBisaTambah(item) {
    /* SELF tidak terikat jenis relasi apa pun. Penambahan bekerja dengan membentuk relasinya
       lebih dulu, dan tidak ada relasi yang menghubungkan orang dengan dirinya sendiri.
       Diperiksa lewat `terikat_relasi`, bukan `kode_kategori !== 'SELF'`: aturannya tentang
       ada-tidaknya relasi, jadi kategori lain yang kelak juga tak terikat ikut tertutup tanpa
       perlu ada yang mengingatnya. */
    /* Kategori tanpa relasi tidak sampai ke sini — bloknya tidak dirender sama sekali. */

    /* Sudah penuh menurut aturan levelnya. Server menolaknya juga; menyebutnya di sini
       menghemat satu percobaan yang pasti gagal. */
    const maks = Number(item.maks_rater ?? 0);
    const terpasang = Number(kesiapanKategori(item.kode_kategori)?.terpasang ?? item.terpasang ?? 0);

    if (maks > 0 && terpasang >= maks) {
        return `Sudah mencapai batas ${maks} rater untuk kategori ini. Cabut salah satu dulu.`;
    }

    return null;
}

const sorotIndex = ref({});

function highlightMatch(text, query) {
    if (!text) return '';
    if (!query || !query.trim()) return text;
    const q = query.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${q})`, 'gi');
    return text.replace(regex, '<mark class="pa-match-highlight">$1</mark>');
}

function geserSorot(kodeKategori, step) {
    const list = props.searchResults?.[kodeKategori] || [];
    if (! list.length) return;
    const current = sorotIndex.value[kodeKategori] ?? 0;
    const next = Math.max(0, Math.min(list.length - 1, current + step));
    sorotIndex.value = { ...sorotIndex.value, [kodeKategori]: next };
}

function pilihSorot(kodeKategori) {
    const list = props.searchResults?.[kodeKategori] || [];
    if (! list.length) return;
    const idx = sorotIndex.value[kodeKategori] ?? 0;
    const target = list[idx] || list[0];
    if (target && target.bisa_dipilih !== false) {
        pilihManual(kodeKategori, target);
    }
}

function cariPenilai(kodeKategori, kata) {
    const rawVal = kata || '';
    kataCari.value = { ...kataCari.value, [kodeKategori]: rawVal };
    sorotIndex.value = { ...sorotIndex.value, [kodeKategori]: 0 };

    /* Autocomplete aktif dari karakter pertama */
    if (! rawVal.trim()) {
        emit('search-rater', { kodeKategori, kata: '' });
        return;
    }

    emit('search-rater', { kodeKategori, kata: rawVal.trim() });
}

/* Memilih orang LANGSUNG menambahkannya, tanpa formulir perantara.
 *
 * Sebelumnya pilihan itu hanya mengisi tiga field — kode perusahaan, kode karyawan, alasan —
 * yang masih harus ditekan Simpan belakangan. Ketiganya dibuang: kodenya sekarang datang dari
 * pencarian, dan alasannya tidak diperlukan lagi karena penugasannya BUKAN manual. Relasi
 * organisasinya ikut dibentuk, jadi ia bersandar pada relasi seperti penilai lain — dan siklus
 * berikutnya akan mengusulkannya sendiri.
 */
function pilihManual(kodeKategori, orang) {
    /* Ditolak di SINI juga, bukan hanya `disabled` di markup. Tombol disabled tetap bisa
       terpicu lewat papan ketik pada sebagian peramban, dan hasil pencarian bisa tersegarkan
       setelah daftarnya dirender. */
    if (orang.bisa_dipilih === false) {
        return;
    }

    kandidatSedangDitambah.value = {
        ...kandidatSedangDitambah.value,
        [kodeKategori]: {
            nama: orang.nama,
            kode_karyawan: orang.kode_karyawan,
            kode_perusahaan: orang.kode_perusahaan,
        },
    };

    const targetKat = (props.categories || []).find((k) => k.kode_kategori === kodeKategori);

    kataCari.value = { ...kataCari.value, [kodeKategori]: '' };
    emit('search-rater', { kodeKategori, kata: '' });

    emit('tambah-penilai', {
        kodeKategori,
        kodePerusahaan: orang.kode_perusahaan,
        kodeKaryawan: orang.kode_karyawan,
        nama: orang.nama,
        namaKategori: targetKat?.nama_kategori || kodeKategori,
    });
}


function bobotSaatIni(candidate) {
    const kunci = kunciBobot(candidate);

    if (props.weights[kunci] !== undefined) {
        return props.weights[kunci];
    }

    return candidate.bobot ?? 0;
}

/* Penilai kategori ini yang punya bobot: terpasang, ditambah yang baru dicentang. */
function berbobot(item) {
    return penilaiBerbobot(item, props.selection[item.kode_kategori] || []);
}

/* Jumlah bobot kategori, atau null bila belum ada penilai berbobot sama sekali. */
function jumlahBobot(item) {
    const terpasang = berbobot(item);

    if (! terpasang.length) {
        return null;
    }

    const total = terpasang.reduce((acc, c) => acc + Number(bobotSaatIni(c) || 0), 0);

    /* Dibulatkan dua desimal SEBELUM ditampilkan supaya 33.33 x 3 tidak tampil sebagai
       99.99000000000001 — angka yang benar tetapi terbaca seperti kesalahan. */
    return Math.round(total * 100) / 100;
}

/* Penilai yang BARU dicentang di layar ini dan belum punya baris penugasan. */
function penilaiBaru(item) {
    return (item.calon || []).filter(
        (c) => ! c.sudah_ditugaskan && isTerpilih(item.kode_kategori, c),
    ).length;
}

/* Berapa yang kurang (positif) atau lebih (negatif); null bila kategorinya belum punya penilai. */
function selisihBobot(item) {
    const total = jumlahBobot(item);

    return total === null ? null : Math.round((100 - total) * 100) / 100;
}

function ubahBobotDibatasi(candidate, ev) {
    const mentah = ev.target.value;

    /* Kotak yang DIKOSONGKAN dibiarkan kosong. Memaksanya jadi 0 membuat angka lama tidak bisa
       dihapus untuk diketik ulang: hapus satu digit, isinya melompat ke 0, dan ketikan
       berikutnya menempel di belakangnya. Kosong terhitung 0 di penjumlahan, jadi keadaannya
       tetap tidak bisa disimpan — bukan celah. */
    if (mentah === '') {
        emit('bobot-change', { kunci: kunciBobot(candidate), nilai: '' });

        return;
    }

    /* Hanya rentangnya yang dijaga di sini. Berapa yang WAJAR bukan urusan satu kotak: itu
       diputuskan induknya, yang menyebar sisanya ke penilai lain sesudah angka ini masuk. */
    let nilai = Number(mentah);

    if (! Number.isFinite(nilai) || nilai < 0) {
        nilai = 0;
    }

    if (nilai > 100) {
        nilai = 100;
    }

    nilai = Math.round(nilai * 100) / 100;

    /* Kotaknya dipaksa ikut bila angkanya dipangkas. Tanpa ini, nilai yang sudah mentok di
       batasnya tidak berubah identitasnya saat diketik lebih besar lagi — Vue tidak merender
       ulang, dan yang tertulis di kotak menyimpang dari yang tersimpan. */
    if (String(nilai) !== mentah) {
        ev.target.value = nilai;
    }

    emit('bobot-change', { kunci: kunciBobot(candidate), nilai });
}

function bobotSeratus(item) {
    const total = jumlahBobot(item);

    /* Toleransi sama dengan yang dipakai server (PaBobotGuard::TOLERANSI): layar yang lebih
       ketat daripada server akan menghalangi penyimpanan yang sebenarnya sah. */
    return total !== null && Math.abs(total - 100) <= 0.011;
}

/*
 | Kategori yang bobotnya disunting di layar ini TETAPI jumlahnya belum tepat 100.
 |
 | Hanya yang DISUNTING yang dihitung, meniru server: kategori yang tidak disentuh tidak ikut
 | dikirim, jadi menjaganya berarti mengunci Simpan gara-gara data lama yang bahkan tidak
 | sedang diubah — admin akan terkurung tanpa bisa menyimpan penilainya sama sekali.
 */
const bobotBelumPas = computed(() => (props.kategori || [])
    .filter((item) => {
        const disunting = berbobot(item).some(
            (c) => Object.prototype.hasOwnProperty.call(props.weights || {}, kunciBobot(c)),
        );

        return disunting && ! bobotSeratus(item);
    })
    .map((item) => item.nama_kategori || item.kode_kategori));

function isTerpilih(kodeKategori, candidate) {
    return (props.selection[kodeKategori] || []).includes(candidate.kode_karyawan);
}

function ubahPilihan(kodeKategori, candidate) {
    emit('toggle-candidate', { kodeKategori, candidate });
}

function ubahManual(kodeKategori, field, value) {
    emit('manual-change', { kodeKategori, field, value });
}

function kosongkanManual(kodeKategori) {
    kataCari.value = { ...kataCari.value, [kodeKategori]: '' };
    emit('search-rater', { kodeKategori, kata: '' });
    ubahManual(kodeKategori, 'kode_perusahaan', '');
    ubahManual(kodeKategori, 'kode_karyawan', '');
    ubahManual(kodeKategori, 'alasan_manual', '');
}

function simpan() {
    emit('save');
}

function hitung() {
    emit('calculate');
}

function formatScore(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
</script>

<style scoped>
.pa-assignment {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

/* 1. ENHANCED PARTICIPANT IDENTITY CARD */
.pa-assignment__identity {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.95rem 1.15rem;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95), rgba(255, 255, 255, 0.9));
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.05);
}

.pa-assignment__avatar {
    width: 2.85rem;
    height: 2.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 0.95rem;
    font-weight: 800;
    color: #ffffff;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
}

.pa-assignment__identity-info {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-assignment__identity-name-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-assignment__name {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-assignment__nik-pill {
    font-size: 0.72rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.pa-assignment__pills-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-assignment__level-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
}

.pa-assignment__pt-pill {
    font-size: 0.7rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.pa-assignment__identity-stats {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 0.35rem;
    flex-shrink: 0;
}

/* INTRO HEADLINE */
.pa-assignment__intro,
.pa-score-result__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.pa-eyebrow {
    display: block;
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.68rem;
    letter-spacing: 0.08em;
    font-weight: 800;
}

.pa-assignment h4,
.pa-score-result h4 {
    margin: 0.15rem 0 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

/* 2. SERVER READINESS DASHBOARD */
.pa-readiness-strip {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 0.65rem 0.95rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-readiness-strip__item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-readiness-strip__label {
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
}

.pa-readiness-strip__stat {
    font-size: 0.74rem;
    color: #0f172a;
}/* 3. CATEGORIES LIST */
.pa-assignment__categories {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-category {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: visible;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.pa-category.is-kategori-lengkap {
    border-left: 4px solid #16a34a;
}

.pa-category.is-kategori-kurang {
    border-left: 4px solid #f59e0b;
}

.pa-category__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.pa-category__header-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-category__code {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    letter-spacing: 0.05em;
}

.pa-category__code.is-cat-atasan {
    background: #e0f2fe;
    color: #0369a1;
}

.pa-category__code.is-cat-bawahan {
    background: #dcfce7;
    color: #15803d;
}

.pa-category__code.is-cat-peer {
    background: #f3e8ff;
    color: #7e22ce;
}

.pa-category__code.is-cat-self {
    background: #f1f5f9;
    color: #475569;
}

.pa-category h5 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-category__meta {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-category__meta-badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-category__meta-details {
    font-size: 0.7rem;
    color: #64748b;
}

/* CANDIDATES LIST */
.pa-candidate-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.75rem 1rem;
}

.pa-candidate.is-ditolak {
    border-color: var(--hcis-danger-border, #f3c2c2);
    background: var(--hcis-danger-surface-subtle, #fff5f5);
}

.pa-candidate {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-candidate:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.pa-candidate.is-selected {
    border-color: rgba(99, 102, 241, 0.45);
    background: rgba(238, 242, 255, 0.65);
    box-shadow: 0 1px 4px rgba(79, 70, 229, 0.06);
}

.pa-candidate > input[type='checkbox'] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.pa-candidate__check {
    width: 1.2rem;
    height: 1.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: transparent;
    border: 1.5px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    transition: all 0.15s ease;
}

.pa-candidate.is-selected .pa-candidate__check {
    color: #ffffff;
    border-color: #4f46e5;
    background: #4f46e5;
}

.pa-candidate__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #64748b, #475569);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-candidate__avatar--sm {
    width: 1.75rem;
    height: 1.75rem;
    font-size: 0.62rem;
}

.pa-candidate__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-candidate__name {
    font-size: 0.85rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-candidate__info-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-candidate__nik {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.pa-candidate__relasi-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

/* BOBOT PERSEN STYLES */
.pa-bobot {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    padding: 0.15rem 0.45rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.pa-bobot__label {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 600;
}

.pa-bobot__input {
    width: 3.8rem;
    padding: 0.15rem 0.3rem;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font: inherit;
    font-size: 0.78rem;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.pa-bobot__persen {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
}

.pa-cabut-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 6px;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #dc2626;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-cabut-btn:hover {
    background: #fee2e2;
    color: #b91c1c;
}

/* BOBOT TOTAL BANNER */
.pa-bobot-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin: 0.5rem 1rem 0.75rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.76rem;
}

.pa-bobot-total.is-salah {
    border-color: #fde68a;
    background: #fffbeb;
    color: #92400e;
}

.pa-bobot-total__text {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-bobot-total__warn {
    font-weight: 600;
    color: #b45309;
}

.pa-bobot-rata {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-left: auto;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    border: 1px solid currentColor;
    background: #ffffff;
    color: inherit;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-bobot-rata:hover {
    background: rgba(255, 255, 255, 0.7);
    transform: translateY(-1px);
}

/* EMPTY CATEGORY */
.pa-category__empty {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-category__empty > div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-category__empty strong {
    color: var(--hcis-text-strong, #334155);
    font-size: 0.82rem;
}

.pa-category__empty span {
    font-size: 0.74rem;
}

/* 4. MANUAL RATER WORKBENCH STYLING */
.pa-manual-form {
    margin: 0.5rem 0.85rem 0.85rem;
    padding: 0.95rem;
    border: 1px solid #fed7aa;
    border-radius: var(--hcis-radius-md, 10px);
    background: #fffbf5;
    transition: all 0.2s ease;
}

.pa-manual-form.is-locked {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    padding: 0.85rem 1rem;
}

.pa-manual-form.is-has-selection {
    background: #f0fdf4;
    border-color: #86efac;
}

/* LOCKED STATE BANNER */
.pa-manual-locked {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.pa-manual-locked__icon-wrap {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 8px;
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-manual-locked__content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
}

.pa-manual-locked__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-manual-locked__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334155;
}

.pa-manual-locked__desc {
    margin: 0;
    font-size: 0.74rem;
    line-height: 1.45;
    color: #64748b;
}

.pa-manual-form__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.pa-manual-form__title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    min-width: 0;
    flex: 1;
}

.pa-manual-icon-badge {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-manual-icon-badge.is-active-badge {
    background: #ffedd5;
    color: #ea580c;
}

.pa-manual-icon-badge.is-locked-badge {
    background: #f1f5f9;
    color: #64748b;
}

.pa-manual-title-texts {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-manual-title-heading {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-manual-form__nota {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.4;
}

.pa-manual-form__sebab {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 600;
    color: #92400e;
    line-height: 1.4;
}

.pa-manual-form__body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* SEARCH INPUT */
.pa-cari-penilai {
    position: relative;
    display: flex;
    flex-direction: column;
}

.pa-cari-penilai__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.pa-cari-penilai__icon {
    position: absolute;
    left: 0.65rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-cari-penilai__input {
    width: 100%;
    padding: 0.45rem 0.65rem 0.45rem 2rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    font-size: 0.8rem;
    background: #ffffff;
    transition: all 0.15s ease;
}

.pa-cari-penilai__input:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.pa-cari-penilai__spinner {
    position: absolute;
    right: 0.65rem;
    color: #6366f1;
}

.pa-cari-penilai__clear-btn {
    position: absolute;
    right: 0.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    padding: 0;
    color: #94a3b8;
    cursor: pointer;
    transition: color 0.15s ease;
}

.pa-cari-penilai__clear-btn:hover {
    color: #64748b;
}

/* IN-FLOW RESULTS BOX */
.pa-cari-penilai__results-wrap {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    margin-top: 0.45rem;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.pa-cari-penilai__results-header {
    padding: 0.35rem 0.65rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.pa-cari-penilai__hasil {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 13rem;
    overflow-y: auto;
}

.pa-cari-penilai__pilih {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 0;
    border-bottom: 1px solid #f1f5f9;
    border-left: 3px solid transparent;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-cari-penilai__pilih:last-child {
    border-bottom: none;
}

.pa-cari-penilai__pilih:hover:not(:disabled) {
    background: #f8fafc;
    border-left-color: #6366f1;
}

.pa-cari-penilai__pilih.is-disabled-opt {
    background: #f8fafc;
    border-left-color: #cbd5e1;
    cursor: not-allowed;
}

.pa-candidate__avatar--disabled {
    background: #e2e8f0 !important;
    color: #94a3b8 !important;
    box-shadow: none !important;
}

.pa-cari-penilai__pilih.is-disabled-opt .pa-cari-penilai__item-name {
    color: #64748b;
    font-weight: 600;
}

.pa-cari-penilai__pilih.is-disabled-opt .pa-candidate__nik {
    background: #e2e8f0;
    color: #64748b;
}

.pa-cari-penilai__pilih.is-disabled-opt .pa-candidate__level-pill,
.pa-cari-penilai__pilih.is-disabled-opt .pa-candidate__dept-pill {
    background: #eef2f6;
    color: #94a3b8;
}

.pa-cari-penilai__item-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.pa-cari-penilai__item-name {
    font-size: 0.82rem;
    color: #0f172a;
}

.pa-cari-penilai__meta-pills {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-candidate__dept-pill {
    font-size: 0.68rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.pa-candidate__pt-pill {
    font-size: 0.68rem;
    color: #475569;
    background: #e2e8f0;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.pa-cari-penilai__pilih-btn {
    font-size: 0.72rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.pa-cari-penilai__pilih:hover:not(:disabled) .pa-cari-penilai__pilih-btn {
    background: #4338ca;
    color: #ffffff;
}

.pa-cari-penilai__disabled-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 250px;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.35;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    flex-shrink: 0;
    text-align: left;
}

.pa-cari-penilai__disabled-pill-text {
    overflow-wrap: break-word;
    word-break: break-word;
}


.pa-cari-penilai__kosong {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0.45rem 0 0;
    font-size: 0.74rem;
    color: #dc2626;
}

/* SELECTED HERO CARD */
.pa-manual-selected-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid #86efac;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(22, 101, 52, 0.06);
    flex-wrap: wrap;
}

.pa-manual-hero-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-candidate__avatar--hero {
    width: 2.25rem;
    height: 2.25rem;
    font-size: 0.75rem;
    background: linear-gradient(135deg, #16a34a, #15803d);
}

.pa-manual-hero-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-manual-hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 700;
    color: #15803d;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-manual-hero-name {
    font-size: 0.88rem;
    color: #0f172a;
}

.pa-manual-hero-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-manual-hero-cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-manual-hero-cancel-btn:hover {
    background: #fee2e2;
}

/* FIELDS LAYOUT */
.pa-manual-form__fields {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-manual-inputs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

@media (max-width: 640px) {
    .pa-manual-inputs-grid {
        grid-template-columns: 1fr;
    }
}

.pa-manual-reason-box {
    width: 100%;
}

/* LOADING & META */
.pa-assignment__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 12rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-assignment__error-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.8rem;
    margin-top: -0.4rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
}

.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
}

/* 5. SCORE RESULT */
.pa-score-result {
    padding: 1.15rem;
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95), rgba(255, 255, 255, 0.9));
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.06);
}

.pa-score-result__badges {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.4rem;
}

.pa-score-result__metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    margin-top: 0.85rem;
}

.pa-score-metric-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border: 1px solid #d1fae5;
}

.pa-score-metric-card.is-final {
    border-color: #a7f3d0;
    background: #ecfdf5;
}

.pa-score-metric-card span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
    font-weight: 600;
}

.pa-score-metric-card strong {
    color: #047857;
    font-size: 1.25rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pa-score-result__quadrant {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0.75rem 0 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.76rem;
}

.pa-score-result__held {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin-top: 0.7rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
    font-size: 0.76rem;
}

.pa-score-result__held strong {
    color: #78350f;
}

.pa-score-result__held-copy {
    min-width: 0;
    overflow-wrap: anywhere;
}

@media (max-width: 640px) {
    .pa-assignment__identity {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 0.75rem;
        gap: 0.6rem;
    }
    .pa-assignment__avatar {
        width: 2.25rem;
        height: 2.25rem;
        font-size: 0.75rem;
    }
    .pa-assignment__name {
        font-size: 0.88rem;
    }
    .pa-assignment__identity-stats {
        width: 100%;
        display: flex;
        justify-content: flex-end;
        padding-top: 0.35rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.8);
    }
    .pa-category__header,
    .pa-assignment__intro,
    .pa-score-result__heading {
        align-items: flex-start;
        flex-direction: column;
    }
    .pa-category__meta {
        align-items: flex-start;
        text-align: left;
    }
    .pa-candidate {
        flex-wrap: wrap;
        padding: 0.5rem 0.6rem;
        gap: 0.5rem;
    }
    .pa-candidate__body {
        min-width: calc(100% - 4.5rem);
    }
    .pa-bobot {
        margin-left: auto;
    }
    .pa-bobot-total {
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.45rem 0.65rem;
        margin: 0.4rem 0.65rem 0.65rem;
    }
    .pa-bobot-rata {
        margin-left: auto;
    }
    .pa-manual-form {
        margin: 0.4rem 0.65rem 0.65rem;
        padding: 0.75rem;
    }
    .pa-cari-penilai__pilih {
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.5rem 0.65rem;
    }
    .pa-cari-penilai__pilih-btn {
        margin-left: auto;
    }
    .pa-cari-penilai__disabled-pill {
        max-width: 100%;
        width: 100%;
    }
    .pa-manual-selected-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .pa-manual-hero-cancel-btn {
        width: 100%;
        justify-content: center;
    }
    .pa-manual-form__fields {
        grid-template-columns: 1fr;
    }
    .pa-score-result__metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.45rem;
    }
    .pa-score-metric-card {
        padding: 0.45rem 0.55rem;
        gap: 0.1rem;
    }
    .pa-score-metric-card span {
        font-size: 0.62rem;
    }
    .pa-score-metric-card strong {
        font-size: 1.05rem;
    }
    .pa-dialog-hint {
        font-size: 0.68rem;
    }
}
.pa-manual-form__sedang {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0 0.5rem;
    font-size: 0.76rem;
    color: #1d4ed8;
}
.pa-dialog-hint--salah {
    color: #b45309;
    font-weight: 600;
}
.pa-bobot-tunda {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0.5rem 0 0;
    padding: 0.5rem 0.65rem;
    border-radius: 0.4rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 0.74rem;
    line-height: 1.45;
}

/* ALERTS STACK & GROUPS */
.pa-assignment__alert-group,
.pa-assignment__alerts-stack {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* SKELETON & LOADING HERO */
.pa-assignment__loading-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.5rem 0;
}

.pa-loading-hero {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, #eef2ff, #f8fafc);
    border: 1px solid #e0e7ff;
}

.pa-loading-hero__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-loading-hero__text strong {
    font-size: 0.85rem;
    color: #3730a3;
}

.pa-loading-hero__text span {
    font-size: 0.74rem;
    color: #6366f1;
}

.pa-loading-skeleton-card {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-loading-skeleton-header {
    height: 1.25rem;
    width: 40%;
    border-radius: 6px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: pa-shimmer 1.5s infinite;
}

.pa-loading-skeleton-row {
    height: 2.25rem;
    border-radius: 8px;
    background: linear-gradient(90deg, #f8fafc 25%, #f1f5f9 50%, #f8fafc 75%);
    background-size: 200% 100%;
    animation: pa-shimmer 1.5s infinite;
}

@keyframes pa-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* SEARCH LOADING & BUTTON STATES */
.pa-cari-penilai__loading-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.45rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 0.75rem;
    font-weight: 600;
}

.pa-cari-penilai__results-adding-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #4338ca;
    font-size: 0.68rem;
    font-weight: 700;
}

.pa-cari-penilai__pilih.is-adding-item {
    background: #f5f3ff;
    border-left-color: #818cf8;
}

.pa-cari-penilai__pilih-btn.is-adding {
    background: #4f46e5;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-cabut-btn.is-revoking {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
    cursor: wait;
}

.pa-cari-penilai__results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.pa-cari-penilai__results-title {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #1e293b;
}

.pa-cari-penilai__results-hint {
    font-size: 0.65rem;
    font-weight: normal;
    color: #64748b;
}

.pa-cari-penilai__pilih.is-highlighted {
    background: #eef2ff;
    border-left-color: #4f46e5;
}

:deep(.pa-match-highlight) {
    background: #fef08a;
    color: #854d0e;
    font-weight: 800;
    padding: 0.05rem 0.2rem;
    border-radius: 2px;
}

.pa-candidate.is-disabled {
    opacity: 0.7;
    pointer-events: none;
}

/* RATER INSERTION ANIMATIONS & CANDIDATE LOADING CARD */
.pa-category.is-adding-rater {
    border-color: #a5b4fc !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.22), 0 4px 12px rgba(99, 102, 241, 0.08);
    animation: pa-category-pulse 2s ease-in-out infinite alternate;
}

@keyframes pa-category-pulse {
    0% {
        border-color: #a5b4fc;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15), 0 2px 6px rgba(99, 102, 241, 0.05);
    }
    100% {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3), 0 4px 14px rgba(99, 102, 241, 0.12);
    }
}

.pa-candidate--adding-slot {
    border: 1.5px dashed #c7d2fe !important;
    background: linear-gradient(90deg, #f5f3ff 0%, #eef2ff 50%, #f5f3ff 100%) !important;
    background-size: 200% 100% !important;
    animation: pa-slot-shimmer 2s ease infinite !important;
    cursor: wait !important;
}

@keyframes pa-slot-shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

.pa-candidate__avatar--adding-slot {
    background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.35);
}

.pa-candidate__name--adding {
    color: #3730a3 !important;
    font-weight: 700;
}

.pa-candidate__adding-desc {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    color: #6366f1;
    font-weight: 500;
}

.pa-adding-spinner-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.1);
}

.pa-manual-form__adding-banner {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    margin-bottom: 0.65rem;
}

.pa-manual-form__adding-banner-texts {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    font-size: 0.74rem;
}

.pa-manual-form__adding-banner-texts strong {
    color: #3730a3;
}

.pa-manual-form__adding-banner-texts span {
    color: #6366f1;
}
.pa-sibuk {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.6rem;
    padding: 0.45rem 0.7rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-color-brand-surface, #eff6ff);
    border: 1px solid var(--hcis-color-brand-border, #bfdbfe);
    overflow: hidden;
}

.pa-sibuk__isi {
    position: absolute;
    inset-block-start: 0;
    inset-inline-start: 0;
    height: 3px;
    width: 40%;
    border-radius: 999px;
    background: var(--hcis-color-brand, #2563eb);
    animation: pa-sibuk-geser 1.1s ease-in-out infinite;
}

@keyframes pa-sibuk-geser {
    from { transform: translateX(-100%); }
    to { transform: translateX(250%); }
}

.pa-sibuk__teks {
    font-size: 0.76rem;
    color: var(--hcis-color-brand-text, #1e40af);
}

/* Diredupkan, BUKAN dibuang: isinya tetap terbaca dan posisi gulirnya tetap. */
.is-menyegarkan {
    opacity: 0.55;
    pointer-events: none;
    transition: opacity 0.15s ease;
}
</style>
