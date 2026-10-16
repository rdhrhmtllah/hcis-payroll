<template>
    <HcisDialog
        :model-value="modelValue"
        title="Buat Sesi Balancing"
        kicker="PERFORMANCE APPRAISAL · BALANCING"
        size="2xl"
        sentiment="brand"
        @update:model-value="(v) => $emit('update:modelValue', v)"
    >
        <div class="pa-bal-buat">
            <!-- Stepper: satu langkah = satu keputusan -->
            <nav class="pa-bal-stepper" aria-label="Tahapan pembuatan sesi">
                <template v-for="(l, i) in LANGKAH" :key="l.no">
                    <button
                        type="button"
                        class="pa-bal-step-btn"
                        :class="{ 'is-active': langkahAktif === l.no, 'is-complete': langkahSelesai(l.no) && langkahAktif !== l.no }"
                        :data-uji="`langkah-${l.no}-tab`"
                        :aria-current="langkahAktif === l.no ? 'step' : undefined"
                        @click="keLangkah(l.no)"
                    >
                        <span class="pa-bal-step-circle">
                            <HcisIcon v-if="langkahSelesai(l.no) && langkahAktif !== l.no" name="check-lg" size="xs" />
                            <span v-else>{{ l.no }}</span>
                        </span>
                        <span class="pa-bal-step-texts">
                            <span class="pa-bal-step-kicker">LANGKAH {{ l.no }}</span>
                            <strong class="pa-bal-step-title">{{ l.judul }}</strong>
                        </span>
                        <span v-if="l.no === 2 && dipilih.size" class="pa-bal-step-pill">{{ dipilih.size }}</span>
                        <span v-if="l.no === 3 && forum.size" class="pa-bal-step-pill is-warning">{{ forum.size }}</span>
                    </button>
                    <span v-if="i < LANGKAH.length - 1" class="pa-bal-step-line" :class="{ 'is-active': langkahAktif > l.no }" />
                </template>
            </nav>

            <!-- Judul langkah aktif: tetap terbaca di HP saat stepper diringkas -->
            <div class="pa-bal-step-heading" data-uji="langkah-aktif">
                <span class="pa-bal-step-heading__no">{{ langkahAktif }}/{{ LANGKAH.length }}</span>
                <div>
                    <strong>{{ LANGKAH[langkahAktif - 1].judul }}</strong>
                    <small>{{ LANGKAH[langkahAktif - 1].bantuan }}</small>
                </div>
            </div>


            <!-- ═══════════ LANGKAH 1 · IDENTITAS SESI ═══════════ -->
            <div v-show="langkahAktif === 1" class="pa-bal-step-content" data-uji="panel-1">
                <section class="pa-bal-card-box">
                    <div class="pa-bal-card-box__header">
                        <div class="pa-bal-card-box__icon"><HcisIcon name="pencil-square" size="xs" /></div>
                        <div class="pa-bal-card-box__titles">
                            <h3 class="pa-bal-card-box__title">Nama sesi</h3>
                            <p class="pa-bal-card-box__desc">Nama yang jelas memudahkan tim menemukan sesinya lagi.</p>
                        </div>
                    </div>
                    <div class="pa-bal-card-box__body">
                        <input
                            v-model="nama"
                            data-uji="nama-sesi"
                            type="text"
                            maxlength="200"
                            class="pa-bal-input-nama"
                            placeholder="mis. Balancing Staff & SPV — Divisi Operasional"
                            @keydown.enter.prevent="keLangkahBerikutnya"
                        >
                        <div class="pa-bal-preset-pills">
                            <span class="pa-bal-preset-label">Saran cepat:</span>
                            <button v-for="sr in SARAN_NAMA" :key="sr" type="button" class="pa-bal-preset-pill" @click="nama = sr">{{ sr }}</button>
                        </div>
                    </div>
                </section>

                <div class="pa-bal-pic-card">
                    <div class="pa-bal-pic-avatar-wrap">
                        <HcisAvatar :name="namaPic" size="sm" shape="rounded" />
                    </div>
                    <div class="pa-bal-pic-texts">
                        <div class="pa-bal-pic-role">PIC SESI</div>
                        <strong>{{ namaPic }}</strong>
                        <small class="text-muted">Anda tercatat sebagai penanggung jawab jalannya rapat.</small>
                    </div>
                    <HcisBadge label="Otomatis" variant="brand" appearance="subtle" size="xs" />
                </div>
            </div>

            <!-- ═══════════ LANGKAH 2 · PILIH PESERTA (dengan saringan sendiri) ═══════════ -->
            <div v-show="langkahAktif === 2" class="pa-bal-step-content" data-uji="panel-2">
                <section class="pa-bal-card-box">
                    <!-- Saringan sederhana; saringan lengkap dibuka bila perlu -->
                    <div class="pa-bal-saring-ringkas">
                        <span class="pa-bal-cari">
                            <HcisIcon name="search" size="xs" class="pa-bal-cari__ikon" />
                            <input v-model="fPeserta.cari" data-uji="cari" type="search" class="pa-bal-cari__input" placeholder="Cari nama atau kode karyawan…" aria-label="Cari peserta">
                        </span>
                        <HcisSelect v-model="fPeserta.level" data-uji="saring-level" class="pa-bal-saring-ringkas__level" size="sm" placeholder="Semua level" aria-label="Level" leading-icon="layers">
                            <HcisSelectOption v-for="o in opsi.level" :key="o.id" :value="o.id" :label="o.nama" />
                        </HcisSelect>
                        <button type="button" class="pa-bal-tombol-lengkap" :class="{ 'is-buka': lengkapPeserta }" :aria-expanded="lengkapPeserta" data-uji="filter-lengkap-peserta" @click="lengkapPeserta = !lengkapPeserta">
                            <HcisIcon name="sliders" size="2xs" />
                            Filter lengkap
                            <span v-if="jumlahFilterLanjutPeserta" class="pa-bal-step-pill">{{ jumlahFilterLanjutPeserta }}</span>
                        </button>
                    </div>
                    <div v-show="lengkapPeserta" class="pa-bal-saring-lengkap">
                        <div v-for="f in SARINGAN_LANJUT" :key="f.kunci" class="pa-bal-saring__field">
                            <label class="pa-bal-saring__sublabel" :for="`pa-bal-p-${f.kunci}`">{{ f.label }}</label>
                            <HcisSelect :id="`pa-bal-p-${f.kunci}`" v-model="fPeserta[f.kunci]" :data-uji="f.uji" size="sm" placeholder="Semua" :aria-label="f.label">
                                <HcisSelectOption v-for="o in opsi[f.kunci]" :key="o.id" :value="o.id" :label="o.nama" />
                            </HcisSelect>
                        </div>
                        <label class="pa-bal-saring-centang">
                            <input v-model="fPeserta.hanyaLayak" type="checkbox"> Hanya yang bisa dipilih
                        </label>
                        <button type="button" class="pa-bal-btn-link" @click="resetFilterPeserta">Reset filter</button>
                    </div>

                    <div class="pa-bal-daftar-bar">
                        <div class="pa-bal-daftar-bar__info">
                            <span class="pa-bal-count-badge is-active"><HcisIcon name="person-check-fill" size="2xs" /><strong>{{ dipilih.size }}</strong> dipilih</span>
                            <span class="pa-bal-daftar-bar__teks">
                                {{ pesertaTersaring.length }} hasil filter dari {{ kandidat.length }} · {{ layakTersaring.length }} bisa dipilih
                                <template v-if="dipilihLuarFilter"> · <b>{{ dipilihLuarFilter }}</b> terpilih di luar filter</template>
                            </span>
                        </div>
                        <div class="pa-bal-daftar-bar__aksi">
                            <button type="button" class="pa-bal-btn-link" data-uji="pilih-semua-filter" :disabled="!layakTersaring.length" @click="pilihSemuaLayak">
                                Pilih semua hasil filter ({{ layakTersaring.length }})
                            </button>
                            <span class="pa-bal-sep">&bull;</span>
                            <button type="button" class="pa-bal-btn-link" :disabled="dipilih.size === 0" @click="dipilih.clear()">Kosongkan pilihan</button>
                        </div>
                    </div>

                    <HcisSkeleton v-if="memuat" :rows="6" />
                    <div v-else class="pa-bal-tabel" role="table" aria-label="Daftar kandidat peserta">
                        <div class="pa-bal-tabel__kepala pa-bal-kolom-peserta" role="row">
                            <span role="columnheader">
                                <input
                                    type="checkbox"
                                    class="pa-bal-check"
                                    data-uji="pilih-halaman"
                                    :checked="statusHalaman === 'semua'"
                                    :indeterminate="statusHalaman === 'sebagian'"
                                    :disabled="!layakHalaman.length"
                                    title="Pilih semua di halaman ini"
                                    aria-label="Pilih semua di halaman ini"
                                    @change="pilihHalaman($event.target.checked)"
                                >
                            </span>
                            <span role="columnheader">Karyawan</span>
                            <span role="columnheader">Unit</span>
                            <span role="columnheader" class="is-tengah">Grade</span>
                            <span role="columnheader">Kotak HAV</span>
                            <span role="columnheader" class="is-kanan">Nilai akhir</span>
                            <span role="columnheader" class="is-tengah">Sesi</span>
                        </div>
                        <ul class="pa-bal-tabel__isi" role="list">
                            <li
                                v-for="k in pesertaHalaman"
                                :key="k.participant_id"
                                class="pa-bal-tabel__baris"
                                :class="{ 'is-mati': !k.layak, 'is-dipilih': dipilih.has(k.participant_id) }"
                            >
                                <label class="pa-bal-kolom-peserta" role="row">
                                    <span class="pa-bal-sel-cek">
                                        <input
                                            type="checkbox"
                                            class="pa-bal-check"
                                            :data-uji="`pilih-${k.participant_id}`"
                                            :disabled="!k.layak"
                                            :checked="dipilih.has(k.participant_id)"
                                            :aria-label="`Pilih ${k.nama}`"
                                            @change="alih(dipilih, k.participant_id, $event.target.checked)"
                                        >
                                    </span>
                                    <span class="pa-bal-sel-orang">
                                        <HcisAvatar :name="k.nama" size="xs" shape="circle" />
                                        <span class="pa-bal-kandidat-nama-text">
                                            <strong>{{ k.nama }}</strong>
                                            <small>{{ k.kode_karyawan }}</small>
                                            <span v-if="!k.layak" class="pa-bal-buat__alasan"><HcisIcon name="slash-circle" size="2xs" /> {{ k.alasan }}</span>
                                        </span>
                                    </span>
                                    <span class="pa-bal-sel-unit">
                                        <strong>{{ k.level || '–' }}</strong>
                                        <small>{{ k.sub_divisi || k.divisi || '–' }}</small>
                                    </span>
                                    <span class="pa-bal-sel-tengah" data-label="Grade"><HcisBadge :label="labelGrade(k.grade)" appearance="subtle" sentiment="brand" size="xs" /></span>
                                    <span class="pa-bal-sel-hav" data-label="HAV" :title="labelKuadran(k)">
                                        <template v-if="k.nomor_box || k.kode_kuadran">
                                            <span v-if="k.nomor_box" class="pa-bal-hav-no">{{ k.nomor_box }}</span>
                                            <span class="pa-bal-hav-nama">{{ k.nama_kuadran || labelKuadran(k) }}</span>
                                        </template>
                                        <template v-else>–</template>
                                    </span>
                                    <span class="pa-bal-sel-kanan" data-label="Nilai">{{ formatNilai(k.final) }}</span>
                                    <span class="pa-bal-sel-tengah" data-label="Sesi" title="Sesi balancing yang sudah diikuti">
                                        <span class="pa-bal-sesi-count" :class="{ 'is-nol': !k.jumlah_sesi }">{{ k.jumlah_sesi }}</span>
                                    </span>
                                </label>
                            </li>
                            <li v-if="!pesertaTersaring.length" class="pa-bal-kandidat-empty">
                                <HcisIcon name="funnel" size="sm" />
                                <span>Tidak ada kandidat yang cocok dengan filter.</span>
                                <button type="button" class="pa-bal-btn-link" @click="resetFilterPeserta">Reset filter</button>
                            </li>
                        </ul>
                    </div>
                    <HcisPagination
                        v-if="pesertaTersaring.length > UKURAN_HALAMAN[0]"
                        v-model:page="halPeserta"
                        v-model:page-size="ukuranPeserta"
                        class="pa-bal-paginasi"
                        size="sm"
                        :total="pesertaTersaring.length"
                        :page-sizes="UKURAN_HALAMAN"
                    />
                </section>
            </div>

            <!-- ═══════════ LANGKAH 3 · FORUM KOMITE (dengan saringan sendiri) ═══════════ -->
            <div v-show="langkahAktif === 3" class="pa-bal-step-content" data-uji="panel-3">
                <section class="pa-bal-card-box">
                    <div class="pa-bal-card-box__header">
                        <div class="pa-bal-card-box__icon is-warning"><HcisIcon name="people-fill" size="xs" /></div>
                        <div class="pa-bal-card-box__titles">
                            <h3 class="pa-bal-card-box__title">Siapa saja yang ikut menilai?</h3>
                            <p class="pa-bal-card-box__desc">Wajib minimal 1 orang. Bisa dipilih dari semua karyawan aktif, tidak harus peserta siklus. Perannya otomatis memakai jabatan masing-masing, dan mereka tidak perlu login.</p>
                        </div>
                    </div>
                    <div class="pa-bal-card-box__body">
                        <div v-if="forum.size" class="pa-bal-forum-terpilih">
                            <span class="pa-bal-forum-terpilih__judul">{{ forum.size }} dipilih</span>
                            <span
                                v-for="f in forumTerpilih"
                                :key="f.kunci"
                                class="pa-bal-forum-chip"
                                :title="`${f.nama} · ${f.jabatan || f.level || '–'}`"
                            >
                                <span class="pa-bal-forum-chip__nama">{{ f.nama }}</span>
                                <button type="button" class="pa-bal-forum-chip__hapus" :aria-label="`Hapus ${f.nama} dari tim penilai`" @click="forum.delete(f.kunci)">
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </span>
                            <button type="button" class="pa-bal-btn-link" @click="forum.clear()">Kosongkan</button>
                        </div>

                        <div class="pa-bal-saring-ringkas">
                            <span class="pa-bal-cari">
                                <HcisIcon name="search" size="xs" class="pa-bal-cari__ikon" />
                                <input v-model="fForum.cari" type="search" class="pa-bal-cari__input" placeholder="Cari nama atau kode karyawan…" aria-label="Cari anggota tim penilai">
                            </span>
                            <HcisSelect v-model="fForum.level" class="pa-bal-saring-ringkas__level" size="sm" placeholder="Semua level" aria-label="Level tim penilai" leading-icon="layers">
                                <HcisSelectOption v-for="o in opsiForum.level" :key="o.id" :value="o.id" :label="o.nama" />
                            </HcisSelect>
                            <button type="button" class="pa-bal-tombol-lengkap" :class="{ 'is-buka': lengkapForum }" :aria-expanded="lengkapForum" @click="lengkapForum = !lengkapForum">
                                <HcisIcon name="sliders" size="2xs" />
                                Filter lengkap
                                <span v-if="jumlahFilterLanjutForum" class="pa-bal-step-pill">{{ jumlahFilterLanjutForum }}</span>
                            </button>
                        </div>
                        <div v-show="lengkapForum" class="pa-bal-saring-lengkap">
                            <div v-for="f in SARINGAN_FORUM" :key="f.kunci" class="pa-bal-saring__field">
                                <label class="pa-bal-saring__sublabel" :for="`pa-bal-f-${f.kunci}`">{{ f.label }}</label>
                                <HcisSelect :id="`pa-bal-f-${f.kunci}`" v-model="fForum[f.kunci]" size="sm" placeholder="Semua" :aria-label="f.label">
                                    <HcisSelectOption v-for="o in opsiForum[f.kunci]" :key="o.id" :value="o.id" :label="o.nama" />
                                </HcisSelect>
                            </div>
                            <button type="button" class="pa-bal-btn-link" @click="resetFilterForum">Reset filter</button>
                        </div>

                        <HcisSkeleton v-if="memuatKaryawan" :rows="5" />
                        <div v-else class="pa-bal-tabel is-forum" role="table" aria-label="Daftar calon tim penilai">
                            <div class="pa-bal-tabel__kepala pa-bal-kolom-forum" role="row">
                                <span role="columnheader" />
                                <span role="columnheader">Karyawan</span>
                                <span role="columnheader">Jabatan</span>
                                <span role="columnheader">Divisi / Departemen</span>
                            </div>
                            <ul class="pa-bal-tabel__isi" role="list">
                                <li v-for="k in forumHalaman" :key="k.kunci" class="pa-bal-tabel__baris" :class="{ 'is-forum': forum.has(k.kunci) }">
                                    <label class="pa-bal-kolom-forum" role="row">
                                        <span class="pa-bal-sel-cek">
                                            <input
                                                type="checkbox"
                                                class="pa-bal-check pa-bal-check--forum"
                                                :data-uji="`forum-${k.kode_karyawan}`"
                                                :checked="forum.has(k.kunci)"
                                                :aria-label="`${k.nama} sebagai tim penilai`"
                                                @change="alihForum(k, $event.target.checked)"
                                            >
                                        </span>
                                        <span class="pa-bal-sel-orang">
                                            <HcisAvatar :name="k.nama" size="xs" shape="circle" />
                                            <span class="pa-bal-kandidat-nama-text">
                                                <strong>{{ k.nama }}</strong>
                                                <small>{{ k.kode_karyawan }}</small>
                                            </span>
                                        </span>
                                        <span class="pa-bal-sel-unit">
                                            <strong>{{ k.jabatan || '–' }}</strong>
                                            <small>{{ k.level || '' }}</small>
                                        </span>
                                        <span class="pa-bal-sel-unit">
                                            <strong>{{ k.divisi || '–' }}</strong>
                                            <small>{{ k.sub_divisi || '' }}</small>
                                        </span>
                                    </label>
                                </li>
                                <li v-if="!forumTersaring.length" class="pa-bal-kandidat-empty">
                                    <HcisIcon name="funnel" size="sm" />
                                    <span>Tidak ada karyawan yang cocok dengan filter.</span>
                                    <button type="button" class="pa-bal-btn-link" @click="resetFilterForum">Reset filter</button>
                                </li>
                            </ul>
                        </div>
                        <HcisPagination
                            v-if="!memuatKaryawan && forumTersaring.length > UKURAN_HALAMAN[0]"
                            v-model:page="halForum"
                            v-model:page-size="ukuranForum"
                            class="pa-bal-paginasi"
                            size="sm"
                            :total="forumTersaring.length"
                            :page-sizes="UKURAN_HALAMAN"
                        />
                    </div>
                </section>
            </div>

            <!-- ═══════════ LANGKAH 4 · TINJAU & BUAT ═══════════ -->
            <div v-show="langkahAktif === 4" class="pa-bal-step-content pa-bal-tinjau" data-uji="panel-4">
                <!-- Identitas sesi -->
                <section class="pa-bal-tinjau-identitas">
                    <div class="pa-bal-tinjau-identitas__utama">
                        <span class="pa-bal-tinjau-label">Nama sesi</span>
                        <strong class="pa-bal-tinjau-identitas__nama">{{ nama || '—' }}</strong>
                        <span class="pa-bal-tinjau-identitas__pic">
                            <HcisAvatar :name="namaPic" size="xs" shape="circle" />
                            PIC: <b>{{ namaPic }}</b>
                        </span>
                    </div>
                    <button type="button" class="pa-bal-tinjau-ubah" @click="keLangkah(1)"><HcisIcon name="pencil" size="2xs" /> Ubah</button>
                </section>

                <!-- Ringkasan angka + kesiapan -->
                <div class="pa-bal-tinjau-angka">
                    <div class="pa-bal-tinjau-kotak is-brand">
                        <HcisIcon name="people-fill" size="sm" />
                        <div><strong>{{ dipilih.size }}</strong><span>peserta dibahas</span></div>
                    </div>
                    <div class="pa-bal-tinjau-kotak is-warning">
                        <HcisIcon name="person-badge" size="sm" />
                        <div><strong>{{ forum.size }}</strong><span>tim penilai</span></div>
                    </div>
                    <ul class="pa-bal-tinjau-siap" aria-label="Kesiapan sesi">
                        <li v-for="c in cekSiap" :key="c.teks" :class="{ 'is-ok': c.ok }">
                            <HcisIcon :name="c.ok ? 'check-circle-fill' : 'exclamation-circle-fill'" size="2xs" />
                            {{ c.teks }}
                        </li>
                    </ul>
                </div>

                <div class="pa-bal-tinjau-grid">
                    <!-- Peserta -->
                    <section class="pa-bal-tinjau-panel">
                        <header class="pa-bal-tinjau-panel__kepala">
                            <h4>Peserta yang dibahas <span class="pa-bal-step-pill">{{ dipilih.size }}</span></h4>
                            <button type="button" class="pa-bal-tinjau-ubah" @click="keLangkah(2)"><HcisIcon name="pencil" size="2xs" /> Ubah</button>
                        </header>
                        <div v-if="sebaranLevel.length" class="pa-bal-tinjau-sebaran">
                            <span v-for="l in sebaranLevel" :key="l.nama" class="pa-bal-tinjau-sebaran__chip">{{ l.nama }} <b>{{ l.jumlah }}</b></span>
                        </div>
                        <!-- Peringatan lunak: tidak menahan pembuatan sesi. -->
                        <p v-if="tanpaKesimpulan.length" class="pa-bal-tinjau-peringatan" data-uji="peringatan-tanpa-kesimpulan">
                            <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                            <span>
                                <b>{{ tanpaKesimpulan.length }} peserta</b> belum punya kesimpulan kualitatif, jadi isi awal Strength &amp; Development Area
                                di board akan kosong. Tulis dulu di tahap <b>Kesimpulan Kualitatif</b> di Monitoring Cycle bila perlu.
                            </span>
                        </p>
                        <span v-if="dipilih.size > 8" class="pa-bal-cari pa-bal-tinjau-cari">
                            <HcisIcon name="search" size="xs" class="pa-bal-cari__ikon" />
                            <input v-model="cariTinjau" type="search" class="pa-bal-cari__input" placeholder="Cari peserta terpilih…" aria-label="Cari peserta terpilih">
                        </span>
                        <ul class="pa-bal-tinjau-daftar">
                            <li v-for="k in pesertaTinjau" :key="k.participant_id" class="pa-bal-tinjau-baris">
                                <HcisAvatar :name="k.nama" size="xs" shape="circle" />
                                <span class="pa-bal-tinjau-baris__teks">
                                    <strong>{{ k.nama }}</strong>
                                    <small>{{ k.level || '–' }} · {{ k.sub_divisi || k.divisi || '–' }}<template v-if="k.perlu_kesimpulan === true"> · <em class="pa-bal-tanpa-kesimpulan">tanpa kesimpulan</em></template></small>
                                </span>
                                <span class="pa-bal-tinjau-baris__meta">
                                    <HcisBadge :label="labelGrade(k.grade)" appearance="subtle" sentiment="brand" size="xs" />
                                    <span class="pa-bal-tinjau-baris__nilai">{{ formatNilai(k.final) }}</span>
                                </span>
                                <button type="button" class="pa-bal-tinjau-hapus" :aria-label="`Keluarkan ${k.nama} dari sesi`" title="Keluarkan dari sesi" @click="dipilih.delete(k.participant_id)">
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </li>
                            <li v-if="!pesertaTinjau.length" class="pa-bal-tinjau-kosong">
                                {{ dipilih.size ? 'Tidak ada peserta yang cocok dengan pencarian.' : 'Belum ada peserta dipilih.' }}
                            </li>
                        </ul>
                    </section>

                    <!-- Tim penilai -->
                    <section class="pa-bal-tinjau-panel">
                        <header class="pa-bal-tinjau-panel__kepala">
                            <h4>Tim penilai <span class="pa-bal-step-pill is-warning">{{ forum.size }}</span></h4>
                            <button type="button" class="pa-bal-tinjau-ubah" @click="keLangkah(3)"><HcisIcon name="pencil" size="2xs" /> Ubah</button>
                        </header>
                        <ul class="pa-bal-tinjau-daftar">
                            <li v-for="f in forumTerpilih" :key="f.kunci" class="pa-bal-tinjau-baris">
                                <HcisAvatar :name="f.nama" size="xs" shape="circle" />
                                <span class="pa-bal-tinjau-baris__teks">
                                    <strong>{{ f.nama }}</strong>
                                    <small>{{ f.jabatan || f.level || '–' }}</small>
                                </span>
                                <button type="button" class="pa-bal-tinjau-hapus" :aria-label="`Hapus ${f.nama} dari tim penilai`" title="Hapus dari tim penilai" @click="forum.delete(f.kunci)">
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </li>
                            <li v-if="!forum.size" class="pa-bal-tinjau-kosong">Belum ada tim penilai. Minimal 1 orang.</li>
                        </ul>
                    </section>
                </div>

                <div class="pa-bal-tinjau-catatan">
                    <HcisIcon name="info-circle-fill" size="xs" />
                    <div>
                        <strong>Setelah sesi dibuat</strong>
                        <span>Sesi langsung terbuka di board rapat. Skor Before dan bobot setiap peserta dibekukan saat itu, dan peserta ini tidak bisa masuk sesi lain sampai sesi ini selesai.</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="pa-bal-buat__footer">
                <div class="pa-bal-buat__footer-left">
                    <small class="text-muted">Langkah {{ langkahAktif }} dari {{ LANGKAH.length }}</small>
                </div>
                <div class="pa-bal-buat__footer-btns">
                    <HcisButton label="Batal" variant="glass" size="sm" @click="$emit('update:modelValue', false)" />
                    <HcisButton v-if="langkahAktif > 1" label="Sebelumnya" variant="secondary" size="sm" leading-icon="arrow-left" @click="keLangkah(langkahAktif - 1)" />
                    <HcisButton v-if="langkahAktif < LANGKAH.length" label="Selanjutnya" variant="primary" size="sm" trailing-icon="arrow-right" @click="keLangkahBerikutnya" />
                    <HcisButton
                        v-else
                        :label="dipilih.size ? `Buat sesi (${dipilih.size} peserta)` : 'Buat sesi'"
                        variant="success"
                        size="sm"
                        leading-icon="check-lg"
                        :loading="menyimpan"
                        :disabled="!bisaBuat"
                        @click="buat"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { HcisAvatar, HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisPagination, HcisSelect, HcisSelectOption, HcisSkeleton, hcisMessage } from '@/components/ui';
import paApi from '../../api/paApi.js';
import { formatNilai, labelGrade, labelKuadran } from '../utils/balancing.js';

defineOptions({ name: 'PaBalancingBuatSesiDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycleId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue', 'dibuat']);

const halaman = usePage();
const langkahAktif = ref(1);
const nama = ref('');
const kandidat = ref([]);
// Calon tim penilai: semua karyawan aktif (bukan hanya peserta siklus), kunci = "perusahaan|karyawan".
const karyawan = ref([]);
const memuatKaryawan = ref(false);
const dipilih = reactive(new Set());
const forum = reactive(new Map());
const FILTER_PESERTA_KOSONG = { cari: '', level: '', divisi: '', sub_divisi: '', grup: '', kuadran: '', grade: '', jumlah_sesi: '', hanyaLayak: false };
const FILTER_FORUM_KOSONG = { cari: '', level: '', divisi: '', sub_divisi: '', jabatan: '' };
const fPeserta = reactive({ ...FILTER_PESERTA_KOSONG });
const fForum = reactive({ ...FILTER_FORUM_KOSONG });
const lengkapPeserta = ref(false);
const lengkapForum = ref(false);

/* Semua kandidat dimuat sekali; penyaringan di layar, jadi langsung terasa tanpa tombol
   Terapkan. Pilihan tiap saringan dikumpulkan dari data yang ada di siklus ini saja. */
const DIMENSI = {
    level: { id: 'level_id', nama: 'level' },
    divisi: { id: 'divisi_id', nama: 'divisi' },
    sub_divisi: { id: 'sub_divisi_id', nama: 'sub_divisi' },
    grup: { id: 'grup_kurva_id', nama: 'grup_kurva' },
    kuadran: { id: 'kuadran_id', nama: 'kode_kuadran', labelBaris: labelKuadran, urut: 'nomor_box' },
    grade: { id: 'grade', nama: 'grade', label: labelGrade },
    jumlah_sesi: { id: 'jumlah_sesi', nama: 'jumlah_sesi', label: (v) => (Number(v) === 0 ? 'Belum pernah' : `${v} sesi`) },
    jabatan: { id: 'jabatan', nama: 'jabatan' },
};
const SARINGAN_LANJUT = [
    { kunci: 'divisi', label: 'Divisi', uji: 'saring-divisi' },
    { kunci: 'sub_divisi', label: 'Departemen', uji: 'saring-sub-divisi' },
    { kunci: 'grup', label: 'Grup kurva', uji: 'saring-grup' },
    { kunci: 'kuadran', label: 'Kotak HAV', uji: 'saring-kuadran' },
    { kunci: 'grade', label: 'Grade', uji: 'saring-grade' },
    { kunci: 'jumlah_sesi', label: 'Sudah ikut sesi', uji: 'saring-jumlah-sesi' },
];
const SARINGAN_FORUM = [
    { kunci: 'divisi', label: 'Divisi' },
    { kunci: 'sub_divisi', label: 'Departemen' },
    { kunci: 'jabatan', label: 'Jabatan' },
];

function opsiDari(daftarSumber, kunciList) {
    return Object.fromEntries(kunciList.map((kunci) => {
        const d = DIMENSI[kunci];
        const peta = new Map();
        for (const k of daftarSumber) {
            const v = k[d.id];
            if (v === null || v === undefined || v === '') continue;
            const ada = peta.get(String(v));
            if (ada) ada.jumlah += 1;
            else peta.set(String(v), { id: String(v), teks: d.labelBaris ? d.labelBaris(k) : d.label ? d.label(v) : (k[d.nama] || `#${v}`), urut: d.urut ? k[d.urut] : null, jumlah: 1 });
        }
        const daftar = [...peta.values()].map((o) => ({ ...o, nama: `${o.teks} (${o.jumlah})` }));
        daftar.sort((x, y) => {
            if (kunci === 'jumlah_sesi') return Number(x.id) - Number(y.id);
            if (x.urut !== null && y.urut !== null) return Number(x.urut) - Number(y.urut);
            return String(x.teks).localeCompare(String(y.teks));
        });
        return [kunci, daftar];
    }));
}
const opsi = computed(() => opsiDari(kandidat.value, ['level', ...SARINGAN_LANJUT.map((x) => x.kunci)]));
const opsiForum = computed(() => opsiDari(karyawan.value, ['level', ...SARINGAN_FORUM.map((x) => x.kunci)]));

function cocok(k, f, kunciList) {
    const q = String(f.cari || '').trim().toLowerCase();
    if (q && !`${k.nama} ${k.kode_karyawan}`.toLowerCase().includes(q)) return false;
    return kunciList.every((kunci) => f[kunci] === '' || f[kunci] === null || String(k[DIMENSI[kunci].id] ?? '') === String(f[kunci]));
}

const pesertaTersaring = computed(() => kandidat.value.filter((k) => cocok(k, fPeserta, ['level', ...SARINGAN_LANJUT.map((x) => x.kunci)]) && (!fPeserta.hanyaLayak || k.layak)));
const layakTersaring = computed(() => pesertaTersaring.value.filter((k) => k.layak));
const forumTersaring = computed(() => karyawan.value.filter((k) => cocok(k, fForum, ['level', ...SARINGAN_FORUM.map((x) => x.kunci)])));
const jumlahFilterLanjutPeserta = computed(() => SARINGAN_LANJUT.filter((x) => fPeserta[x.kunci] !== '' && fPeserta[x.kunci] !== null).length + (fPeserta.hanyaLayak ? 1 : 0));
const jumlahFilterLanjutForum = computed(() => SARINGAN_FORUM.filter((x) => fForum[x.kunci] !== '' && fForum[x.kunci] !== null).length);

/* Paginasi hanya untuk tampilan: semua kandidat tetap di memori, jadi filter dan
   "pilih semua hasil filter" berlaku ke seluruh halaman, bukan halaman yang terbuka saja. */
const UKURAN_HALAMAN = [10, 25, 50, 100];
const halPeserta = ref(1);
const ukuranPeserta = ref(25);
const halForum = ref(1);
const ukuranForum = ref(10);

function potongHalaman(daftar, hal, ukuran) {
    const awal = (hal - 1) * ukuran;
    return daftar.slice(awal, awal + ukuran);
}
const pesertaHalaman = computed(() => potongHalaman(pesertaTersaring.value, halPeserta.value, ukuranPeserta.value));
const forumHalaman = computed(() => potongHalaman(forumTersaring.value, halForum.value, ukuranForum.value));
const layakHalaman = computed(() => pesertaHalaman.value.filter((k) => k.layak));
const statusHalaman = computed(() => {
    const n = layakHalaman.value.filter((k) => dipilih.has(k.participant_id)).length;
    if (n === 0) return 'kosong';
    return n === layakHalaman.value.length ? 'semua' : 'sebagian';
});
const dipilihLuarFilter = computed(() => {
    const tampil = new Set(pesertaTersaring.value.map((k) => k.participant_id));
    return [...dipilih].filter((id) => !tampil.has(id)).length;
});

function pilihHalaman(ya) {
    for (const k of layakHalaman.value) {
        if (ya) dipilih.add(k.participant_id);
        else dipilih.delete(k.participant_id);
    }
}

// Filter berubah → kembali ke halaman 1; halaman dijaga tetap dalam jangkauan.
watch(fPeserta, () => { halPeserta.value = 1; });
watch(fForum, () => { halForum.value = 1; });
watch(ukuranPeserta, () => { halPeserta.value = 1; });
watch(ukuranForum, () => { halForum.value = 1; });

function resetFilterPeserta() { Object.assign(fPeserta, FILTER_PESERTA_KOSONG); }
function resetFilterForum() { Object.assign(fForum, FILTER_FORUM_KOSONG); }

const memuat = ref(false);
const menyimpan = ref(false);

const kandidatLayak = computed(() => kandidat.value.filter((k) => k.layak));

/* Satu langkah = satu keputusan. `syarat` mengembalikan pesan galat bila langkah itu belum
   boleh ditinggalkan; kosong berarti boleh lanjut. */
const LANGKAH = [
    { no: 1, judul: 'Identitas Sesi', bantuan: 'Beri nama sesi; PIC terisi otomatis.', syarat: () => (nama.value.trim() ? '' : 'Isi nama sesi lebih dulu.') },
    { no: 2, judul: 'Pilih Peserta', bantuan: 'Saring lalu centang karyawan yang akan dibahas dalam rapat.', syarat: () => (dipilih.size ? '' : 'Pilih minimal 1 peserta.') },
    { no: 3, judul: 'Tim Penilai', bantuan: 'Centang siapa saja yang ikut menilai, minimal 1 orang.', syarat: () => (forum.size ? '' : 'Pilih minimal 1 anggota tim penilai.') },
    { no: 4, judul: 'Tinjau & Buat', bantuan: 'Periksa sekali lagi sebelum sesi dibuat.', syarat: () => '' },
];
const SARAN_NAMA = ['Balancing Staff & SPV', 'Balancing Section Head', 'Balancing Manajerial'];

const namaPic = computed(() => halaman.props.auth?.user?.name || 'Pengguna saat ini');
const pesertaTerpilih = computed(() => kandidat.value.filter((k) => dipilih.has(k.participant_id)));
// Hanya yang punya jawaban kualitatif tetapi belum disimpulkan; tanpa jawaban = tidak perlu.
const tanpaKesimpulan = computed(() => pesertaTerpilih.value.filter((k) => k.perlu_kesimpulan === true));
const forumTerpilih = computed(() => [...forum.keys()].map((kunci) => karyawan.value.find((k) => k.kunci === kunci) || { kunci, nama: kunci }));

// Langkah 4: ringkasan dan daftar yang bisa dirapikan langsung tanpa kembali ke langkah sebelumnya.
const cariTinjau = ref('');
const pesertaTinjau = computed(() => {
    const q = cariTinjau.value.trim().toLowerCase();
    return q ? pesertaTerpilih.value.filter((k) => `${k.nama} ${k.kode_karyawan}`.toLowerCase().includes(q)) : pesertaTerpilih.value;
});
const sebaranLevel = computed(() => {
    const peta = new Map();
    for (const k of pesertaTerpilih.value) {
        const n = k.level || 'Tanpa level';
        peta.set(n, (peta.get(n) || 0) + 1);
    }
    return [...peta].map(([nama, jumlah]) => ({ nama, jumlah })).sort((x, y) => y.jumlah - x.jumlah);
});
const cekSiap = computed(() => [
    { teks: 'Nama sesi terisi', ok: nama.value.trim() !== '' },
    { teks: 'Minimal 1 peserta', ok: dipilih.size > 0 },
    { teks: 'Minimal 1 tim penilai', ok: forum.size > 0 },
]);

function langkahSelesai(no) {
    return LANGKAH.slice(0, no).every((l) => !l.syarat()) && langkahAktif.value > no;
}


const bisaBuat = computed(() => nama.value.trim() !== '' && dipilih.size > 0 && forum.size > 0);

function keLangkah(target) {
    // Mundur selalu boleh; maju harus melewati syarat setiap langkah di antaranya.
    if (target > langkahAktif.value) {
        for (const l of LANGKAH.slice(langkahAktif.value - 1, target - 1)) {
            const pesan = l.syarat();
            if (pesan) {
                // Toast, bukan banner: muncul di atas layar dan tidak tertutup isi langkah.
                hcisMessage.warning(pesan, 'Belum bisa lanjut');
                langkahAktif.value = l.no;
                return;
            }
        }
    }
    langkahAktif.value = target;
}

function keLangkahBerikutnya() {
    if (langkahAktif.value < LANGKAH.length) keLangkah(langkahAktif.value + 1);
}

function alih(himpunan, id, ya) {
    if (ya) himpunan.add(id);
    else himpunan.delete(id);
}

function alihForum(k, ya) {
    if (ya) forum.set(k.kunci, { kode_perusahaan: k.kode_perusahaan, kode_karyawan: k.kode_karyawan });
    else forum.delete(k.kunci);
}

function pilihSemuaLayak() {
    for (const k of layakTersaring.value) {
        dipilih.add(k.participant_id);
    }
}

function batalPilihSemua() {
    dipilih.clear();
}


async function muatKandidat() {
    if (!props.cycleId) return;
    memuat.value = true;
    try {
        kandidat.value = await paApi.kandidatBalancing(Number(props.cycleId), {});
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal memuat kandidat.', 'Kandidat tidak termuat');
    } finally {
        memuat.value = false;
    }
}

async function muatKaryawan() {
    if (karyawan.value.length) return; // daftar karyawan tidak bergantung siklus; cukup sekali
    memuatKaryawan.value = true;
    try {
        karyawan.value = await paApi.timPenilaiBalancing();
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal memuat daftar karyawan.', 'Tim penilai tidak termuat');
    } finally {
        memuatKaryawan.value = false;
    }
}

async function buat() {
    menyimpan.value = true;
    try {
        const hasil = await paApi.buatSesiBalancing(Number(props.cycleId), {
            nama: nama.value.trim(),
            pic_user_id: halaman.props.auth?.user?.id,
            forum: [...forum.values()],
            peserta_ids: [...dipilih],
        });
        emit('dibuat', hasil);
    } catch (e) {
        hcisMessage.error(e?.message || 'Sesi tidak bisa dibuat.', 'Sesi gagal dibuat');
    } finally {
        menyimpan.value = false;
    }
}

watch(() => [props.modelValue, props.cycleId], ([buka]) => {
    if (buka) {
        langkahAktif.value = 1;
        nama.value = '';
        dipilih.clear();
        forum.clear();
        resetFilterPeserta();
        resetFilterForum();
        lengkapPeserta.value = false;
        lengkapForum.value = false;
        halPeserta.value = 1;
        halForum.value = 1;
        cariTinjau.value = '';
        muatKandidat();
        muatKaryawan();
    }
}, { immediate: true });
</script>

<style scoped>
.pa-bal-buat {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-bal-buat__alert {
    margin-bottom: 1rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   STEPPER INDICATOR
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 16px);
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.25rem;
}

.pa-bal-step-btn {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: none;
    border: none;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-lg, 10px);
    cursor: pointer;
    text-align: left;
    transition: all 180ms ease;
    user-select: none;
}

.pa-bal-step-btn:hover {
    background: rgba(241, 245, 249, 0.8);
}

.pa-bal-step-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    background: #e2e8f0;
    color: #64748b;
    transition: all 180ms ease;
    flex-shrink: 0;
}

.pa-bal-step-btn.is-active .pa-bal-step-circle {
    background: linear-gradient(135deg, var(--hcis-brand-600, #4f46e5), var(--hcis-brand-700, #4338ca));
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
}

.pa-bal-step-btn.is-complete:not(.is-active) .pa-bal-step-circle {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.pa-bal-step-line {
    flex: 1;
    height: 2px;
    background: rgba(226, 232, 240, 0.9);
    transition: all 180ms ease;
}

.pa-bal-step-line.is-active {
    background: var(--hcis-brand-500, #6366f1);
}

.pa-bal-step-texts {
    display: flex;
    flex-direction: column;
}

.pa-bal-step-kicker {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-bal-step-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-step-btn.is-active .pa-bal-step-title {
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-step-pill {
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-step-pill.is-warning {
    background: rgba(245, 158, 11, 0.12);
    color: #b45309;
}

/* ═══════════════════════════════════════════════════════════════════════════
   STEP CONTENT CARDS
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-step-content {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.pa-bal-card-box {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 16px);
    padding: 1.15rem 1.35rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-bal-card-box__header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

.pa-bal-card-box__icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--hcis-brand-600, #4f46e5);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.pa-bal-card-box__icon.is-brand {
    background: rgba(79, 70, 229, 0.1);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-card-box__icon.is-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.pa-bal-card-box__titles {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.pa-bal-kicker {
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.pa-bal-card-box__title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    margin: 0;
}

.pa-bal-card-box__desc {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    margin: 0.15rem 0 0;
}

.pa-bal-card-box__body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-bal-card-box__body.pa-p-0 {
    padding: 0;
}

/* Step 1 Inputs & Presets */
.pa-bal-input-nama {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    background: #ffffff;
    transition: all 150ms ease;
}

.pa-bal-input-nama:focus {
    outline: none;
    border-color: rgba(79, 70, 229, 0.5);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.pa-bal-preset-pills {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.pa-bal-preset-label {
    font-size: 0.6875rem;
    color: #94a3b8;
    font-weight: 600;
}

.pa-bal-preset-pill {
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-strong, #0f172a);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-bal-preset-pill:hover {
    background: rgba(79, 70, 229, 0.08);
    border-color: rgba(79, 70, 229, 0.25);
    color: var(--hcis-brand-600, #4f46e5);
}

/* Saring Grid */
.pa-bal-buat__saring {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-bal-saring__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 0.65rem;
}

.pa-bal-saring__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-bal-saring__sublabel {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-bal-saring__input,
.pa-bal-saring__select {
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    width: 100%;
}

.pa-bal-saring__input:focus,
.pa-bal-saring__select:focus {
    outline: none;
    border-color: rgba(79, 70, 229, 0.5);
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
}

.pa-bal-saring__actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-top: 0.25rem;
}

.pa-bal-step1-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(79, 70, 229, 0.05);
    border: 1px solid rgba(79, 70, 229, 0.15);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.65rem 1rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pa-bal-step1-banner-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--hcis-text-strong, #0f172a);
}

/* ═══════════════════════════════════════════════════════════════════════════
   STEP 2 TOOLBAR & FAST SELECTION
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-step2-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 0.85rem;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.pa-bal-step2-counters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-bal-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-count-badge.is-active {
    background: rgba(79, 70, 229, 0.1);
    border-color: rgba(79, 70, 229, 0.3);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-count-badge.is-warning-pill.is-active {
    background: rgba(217, 119, 6, 0.1);
    border-color: rgba(217, 119, 6, 0.3);
    color: #d97706;
}

.pa-bal-step2-fast {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.75rem;
}

.pa-bal-btn-link {
    background: none;
    border: none;
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}

.pa-bal-btn-link:hover {
    text-decoration: underline;
}

.pa-bal-btn-link:disabled {
    color: #94a3b8;
    cursor: not-allowed;
    text-decoration: none;
}

.pa-bal-sep {
    color: #cbd5e1;
}

/* ═══════════════════════════════════════════════════════════════════════════
   TABLES (STEP 2 & STEP 3)
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-buat__tabel {
    overflow-x: auto;
    max-height: 44vh;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-bal-buat__tabel table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}

.pa-bal-buat__tabel th {
    position: sticky;
    top: 0;
    background: rgba(248, 250, 252, 0.95);
    backdrop-filter: blur(8px);
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    color: var(--hcis-text-muted, #64748b);
    font-weight: 700;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-align: left;
    white-space: nowrap;
    z-index: 1;
}

.pa-bal-buat__tabel td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.85);
    vertical-align: middle;
}

.pa-bal-buat__tabel tr.is-dipilih td {
    background: rgba(240, 253, 244, 0.75);
}

.pa-bal-buat__tabel tr.is-forum-selected td {
    background: rgba(254, 243, 199, 0.5);
}

.pa-bal-buat__tabel tr.is-mati {
    color: #94a3b8;
    background: rgba(250, 250, 250, 0.8);
}

.pa-bal-check {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #10b981;
}

.pa-bal-check--forum {
    accent-color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-kandidat-nama {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-bal-kandidat-nama-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-bal-kandidat-nama strong {
    display: block;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.8125rem;
    font-weight: 600;
}

.pa-bal-buat__tabel tr.is-mati .pa-bal-kandidat-nama strong {
    color: #94a3b8;
}

.pa-bal-kandidat-nama small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.6875rem;
}

.pa-bal-kandidat-org {
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-sesi-count {
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.pa-bal-buat__alasan {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #b91c1c;
    font-size: 0.6875rem;
    background: rgba(254, 242, 242, 0.85);
    border: 1px solid rgba(254, 202, 202, 0.85);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}



.pa-bal-kandidat-empty {
    text-align: center;
    padding: 2rem;
    color: #94a3b8;
}

/* ═══════════════════════════════════════════════════════════════════════════
   STEP 3 SPECIAL CARDS
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-pic-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(99, 102, 241, 0.04));
    border: 1px solid rgba(79, 70, 229, 0.2);
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-bal-pic-avatar-wrap {
    flex-shrink: 0;
}

.pa-bal-pic-texts {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.pa-bal-pic-role {
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
    letter-spacing: 0.05em;
}

.pa-bal-pic-texts strong {
    font-size: 0.875rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-pic-texts small {
    font-size: 0.725rem;
}






/* ═══════════════════════════════════════════════════════════════════════════
   FOOTER ACTIONS
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-buat__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    flex-wrap: wrap;
    gap: 0.85rem;
}

.pa-bal-buat__footer-left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
}

.pa-bal-buat__footer-btns {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-left: auto;
}

@media (max-width: 640px) {
    .pa-bal-stepper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-bal-step-line {
        display: none;
    }

    .pa-bal-buat__footer {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-bal-buat__footer-btns {
        width: 100%;
        justify-content: space-between;
    }
}

/* ── Wizard 5 langkah (tambahan) ─────────────────────────────── */
.pa-bal-stepper { overflow-x: auto; scrollbar-width: none; }
.pa-bal-stepper::-webkit-scrollbar { display: none; }
.pa-bal-step-btn { flex: 0 0 auto; }
.pa-bal-step-line { flex: 1 1 24px; min-width: 16px; }
.pa-bal-step-heading { display: flex; align-items: center; gap: 0.75rem; margin: -0.25rem 0 1rem; }
.pa-bal-step-heading strong { display: block; font-size: 1rem; color: #0f172a; }
.pa-bal-step-heading small { color: #64748b; }
.pa-bal-step-heading__no { flex: 0 0 auto; font-weight: 700; font-size: 0.75rem; color: #4f46e5; background: #eef2ff; border-radius: 999px; padding: 0.2rem 0.6rem; }
.pa-bal-daftar-pilih { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.4rem; max-height: 52vh; overflow-y: auto; }
.pa-bal-daftar-pilih.is-ringkas { max-height: 34vh; }
.pa-bal-pilih-baris { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; min-width: 0; }
.pa-bal-pilih-baris.is-dipilih { border-color: #818cf8; background: #eef2ff; }
.pa-bal-pilih-baris.is-forum-selected { border-color: #fbbf24; background: #fffbeb; }
.pa-bal-pilih-baris.is-mati { opacity: 0.6; background: #f8fafc; }
.pa-bal-pilih-label { display: flex; align-items: center; gap: 0.6rem; flex: 1 1 240px; min-width: 0; cursor: pointer; }
.pa-bal-pilih-baris.is-mati .pa-bal-pilih-label { cursor: not-allowed; }
.pa-bal-kandidat-nama-text { display: flex; flex-direction: column; min-width: 0; }
.pa-bal-kandidat-nama-text strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pa-bal-kandidat-nama-text small { color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pa-bal-pilih-meta { display: flex; align-items: center; gap: 0.5rem; }
.pa-bal-pilih-baris .pa-bal-buat__alasan { flex-basis: 100%; }
.pa-bal-forum-terpilih { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; margin-bottom: 0.75rem; padding: 0.5rem 0.6rem; border: 1px solid #fde68a; border-radius: 10px; background: #fffbeb; max-height: 96px; overflow-y: auto; }
.pa-bal-forum-terpilih__judul { font-size: 0.72rem; font-weight: 700; color: #92400e; margin-right: 0.25rem; }
.pa-bal-forum-terpilih .pa-bal-btn-link { font-size: 0.72rem; margin-left: auto; }
.pa-bal-forum-chip { display: inline-flex; align-items: center; gap: 0.2rem; max-width: 220px; padding: 0.15rem 0.2rem 0.15rem 0.6rem; border-radius: 999px; background: #fff; border: 1px solid #fcd34d; font-size: 0.75rem; font-weight: 600; color: #78350f; }
.pa-bal-forum-chip__nama { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-forum-chip__hapus { display: inline-grid; place-items: center; width: 18px; height: 18px; flex: 0 0 auto; border: 0; border-radius: 999px; background: transparent; color: #b45309; cursor: pointer; }
.pa-bal-forum-chip__hapus:hover { background: #fde68a; }
.pa-bal-cari-forum { width: 100%; margin-bottom: 0.5rem; }
@media (max-width: 720px) {
    .pa-bal-step-texts { display: none; }
    .pa-bal-step-btn.is-active .pa-bal-step-texts { display: none; }
}

/* ── Saringan bertingkat: sederhana dulu, lengkap bila dibuka ── */
.pa-bal-saring-ringkas { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; }
.pa-bal-saring-ringkas__cari { flex: 1 1 220px; min-width: 0; }
.pa-bal-saring-ringkas__level { flex: 0 1 200px; min-width: 160px; }
.pa-bal-tombol-lengkap { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; color: #334155; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
.pa-bal-tombol-lengkap.is-buka { border-color: #818cf8; background: #eef2ff; color: #4338ca; }
.pa-bal-saring-lengkap { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.6rem 0.75rem; align-items: end; padding: 0.75rem; margin-bottom: 0.75rem; border: 1px dashed #c7d2fe; border-radius: 12px; background: #f8faff; }
.pa-bal-saring-centang { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #334155; }

/* ── Cari dengan ikon ── */
.pa-bal-cari { position: relative; display: flex; align-items: center; flex: 1 1 220px; min-width: 0; }
.pa-bal-cari__ikon { position: absolute; left: 0.7rem; color: #94a3b8; pointer-events: none; }
.pa-bal-cari__input { width: 100%; height: 36px; padding: 0 0.75rem 0 2.1rem; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; font-size: 0.85rem; color: #0f172a; transition: border-color 0.15s, box-shadow 0.15s; }
.pa-bal-cari__input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
.pa-bal-tombol-lengkap { height: 36px; }

/* ── Bar ringkasan di atas daftar ── */
.pa-bal-daftar-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; margin-bottom: 0.6rem; }
.pa-bal-daftar-bar__info { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; min-width: 0; }
.pa-bal-daftar-bar__teks { font-size: 0.78rem; color: #64748b; }
.pa-bal-daftar-bar__aksi { display: flex; align-items: center; gap: 0.4rem; }

/* ── Daftar bergaya tabel ── */
.pa-bal-tabel { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }
.pa-bal-kolom-peserta { display: grid; grid-template-columns: 32px minmax(0, 2.2fr) minmax(0, 1.5fr) 64px minmax(150px, 1.3fr) 88px 52px; align-items: center; gap: 0.75rem; padding: 0.55rem 0.85rem; }
.pa-bal-kolom-forum { display: grid; grid-template-columns: 32px minmax(0, 2.4fr) minmax(0, 1fr) minmax(0, 1.8fr); align-items: center; gap: 0.75rem; padding: 0.5rem 0.85rem; }
.pa-bal-tabel__kepala { background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; }
.pa-bal-tabel__isi { list-style: none; margin: 0; padding: 0; max-height: 46vh; overflow-y: auto; }
.pa-bal-tabel.is-forum .pa-bal-tabel__isi { max-height: 32vh; }
.pa-bal-tabel__baris { border-bottom: 1px solid #f1f5f9; transition: background 0.12s; }
.pa-bal-tabel__baris:last-child { border-bottom: 0; }
.pa-bal-tabel__baris > label { cursor: pointer; margin: 0; }
.pa-bal-tabel__baris:hover { background: #f8fafc; }
.pa-bal-tabel__baris.is-dipilih { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
.pa-bal-tabel__baris.is-forum { background: #fffbeb; box-shadow: inset 3px 0 0 #f59e0b; }
.pa-bal-tabel__baris.is-mati { background: #fafafa; }
.pa-bal-tabel__baris.is-mati > label { cursor: not-allowed; }
.pa-bal-tabel__baris.is-mati .pa-bal-sel-orang strong,
.pa-bal-tabel__baris.is-mati .pa-bal-sel-unit,
.pa-bal-tabel__baris.is-mati .pa-bal-sel-tengah,
.pa-bal-tabel__baris.is-mati .pa-bal-sel-kanan { opacity: 0.55; }
.pa-bal-sel-cek { display: flex; justify-content: center; }
.pa-bal-sel-orang { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.pa-bal-sel-orang .pa-bal-kandidat-nama-text { display: flex; flex-direction: column; min-width: 0; }
.pa-bal-sel-orang strong, .pa-bal-sel-unit strong { font-size: 0.84rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-sel-orang small, .pa-bal-sel-unit small { font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-sel-unit { display: flex; flex-direction: column; min-width: 0; font-weight: 500; }
.pa-bal-sel-unit strong { font-weight: 600; font-size: 0.8rem; }
.pa-bal-sel-tengah { text-align: center; font-size: 0.8rem; color: #334155; font-weight: 600; }
.pa-bal-sel-kanan { text-align: right; font-variant-numeric: tabular-nums; font-size: 0.84rem; font-weight: 700; color: #0f172a; }
.pa-bal-tabel__kepala .is-tengah { text-align: center; }
.pa-bal-tabel__kepala .is-kanan { text-align: right; }
.pa-bal-sel-orang .pa-bal-buat__alasan { margin-top: 0.15rem; font-size: 0.7rem; color: #b45309; white-space: normal; }
.pa-bal-sesi-count.is-nol { color: #94a3b8; }
.pa-bal-sel-hav { display: flex; align-items: center; gap: 0.4rem; min-width: 0; font-size: 0.78rem; color: #334155; font-weight: 600; }
.pa-bal-hav-no { flex: 0 0 auto; display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 6px; background: #ecfdf5; color: #047857; font-size: 0.7rem; font-weight: 800; }
.pa-bal-hav-nama { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-tabel .pa-bal-kandidat-empty { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; padding: 1.75rem 1rem; color: #64748b; font-size: 0.82rem; }
.pa-bal-paginasi { margin-top: 0.6rem; }

@media (max-width: 720px) {
    .pa-bal-tabel__kepala.pa-bal-kolom-peserta { grid-template-columns: 32px 1fr; }
    .pa-bal-tabel__kepala.pa-bal-kolom-peserta > :nth-child(n + 3) { display: none; }
    .pa-bal-tabel__kepala.pa-bal-kolom-forum { display: none; }
    .pa-bal-tabel__baris > .pa-bal-kolom-peserta,
    .pa-bal-tabel__baris > .pa-bal-kolom-forum { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem 0.75rem; }
    .pa-bal-tabel__baris .pa-bal-sel-cek { flex: 0 0 24px; }
    .pa-bal-tabel__baris .pa-bal-sel-orang { flex: 1 1 calc(100% - 40px); }
    .pa-bal-tabel__baris .pa-bal-sel-unit { flex: 1 1 100%; flex-direction: row; gap: 0.4rem; padding-left: 36px; }
    .pa-bal-tabel__baris .pa-bal-sel-tengah,
    .pa-bal-tabel__baris .pa-bal-sel-hav,
    .pa-bal-tabel__baris .pa-bal-sel-kanan { text-align: left; font-size: 0.74rem; }
    .pa-bal-tabel__baris [data-label="Grade"] { padding-left: 36px; }
    .pa-bal-tabel__baris [data-label]::before { content: attr(data-label) ': '; font-weight: 500; color: #94a3b8; }
}

/* ── Langkah 4 · Tinjau ── */
.pa-bal-tinjau { display: flex; flex-direction: column; gap: 0.85rem; }
.pa-bal-tinjau-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
.pa-bal-tinjau-identitas { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 0.9rem 1rem; border: 1px solid #c7d2fe; border-radius: 14px; background: linear-gradient(135deg, #eef2ff, #f8faff); }
.pa-bal-tinjau-identitas__utama { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.pa-bal-tinjau-identitas__nama { font-size: 1.05rem; color: #1e1b4b; overflow-wrap: anywhere; }
.pa-bal-tinjau-identitas__pic { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.25rem; font-size: 0.78rem; color: #475569; }
.pa-bal-tinjau-ubah { display: inline-flex; align-items: center; gap: 0.3rem; flex: 0 0 auto; padding: 0.3rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #4338ca; font-size: 0.75rem; font-weight: 600; cursor: pointer; }
.pa-bal-tinjau-ubah:hover { border-color: #818cf8; background: #eef2ff; }

.pa-bal-tinjau-angka { display: grid; grid-template-columns: repeat(2, minmax(0, 150px)) 1fr; gap: 0.75rem; align-items: stretch; }
.pa-bal-tinjau-kotak { display: flex; align-items: center; gap: 0.65rem; padding: 0.7rem 0.85rem; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; }
.pa-bal-tinjau-kotak div { display: flex; flex-direction: column; line-height: 1.1; }
.pa-bal-tinjau-kotak strong { font-size: 1.35rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-bal-tinjau-kotak span { font-size: 0.72rem; color: #64748b; }
.pa-bal-tinjau-kotak.is-brand { color: #4f46e5; border-color: #c7d2fe; }
.pa-bal-tinjau-kotak.is-warning { color: #d97706; border-color: #fde68a; }
.pa-bal-tinjau-siap { list-style: none; margin: 0; padding: 0.55rem 0.85rem; display: flex; flex-direction: column; justify-content: center; gap: 0.2rem; border-radius: 12px; border: 1px dashed #e2e8f0; background: #f8fafc; }
.pa-bal-tinjau-siap li { display: flex; align-items: center; gap: 0.4rem; font-size: 0.76rem; color: #b45309; font-weight: 600; }
.pa-bal-tinjau-siap li.is-ok { color: #047857; }

.pa-bal-tinjau-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 0.85rem; }
.pa-bal-tinjau-panel { display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
.pa-bal-tinjau-panel__kepala { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.pa-bal-tinjau-panel__kepala h4 { display: flex; align-items: center; gap: 0.4rem; margin: 0; font-size: 0.86rem; color: #0f172a; }
.pa-bal-tinjau-sebaran { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pa-bal-tinjau-sebaran__chip { padding: 0.1rem 0.5rem; border-radius: 999px; background: #f1f5f9; font-size: 0.7rem; color: #475569; }
.pa-bal-tinjau-sebaran__chip b { color: #0f172a; }
.pa-bal-tinjau-cari { flex: 0 0 auto; }
.pa-bal-tinjau-daftar { list-style: none; margin: 0; padding: 0; max-height: 34vh; overflow-y: auto; display: flex; flex-direction: column; }
.pa-bal-tinjau-baris { display: flex; align-items: center; gap: 0.6rem; padding: 0.45rem 0.25rem; border-bottom: 1px solid #f1f5f9; }
.pa-bal-tinjau-baris:last-child { border-bottom: 0; }
.pa-bal-tinjau-baris__teks { display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0; }
.pa-bal-tinjau-baris__teks strong { font-size: 0.82rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-tinjau-baris__teks small { font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-bal-tinjau-baris__meta { display: flex; align-items: center; gap: 0.5rem; flex: 0 0 auto; }
.pa-bal-tinjau-baris__nilai { min-width: 44px; text-align: right; font-size: 0.8rem; font-weight: 700; font-variant-numeric: tabular-nums; color: #0f172a; }
.pa-bal-tinjau-hapus { display: inline-grid; place-items: center; width: 24px; height: 24px; flex: 0 0 auto; border: 0; border-radius: 8px; background: transparent; color: #94a3b8; cursor: pointer; }
.pa-bal-tinjau-hapus:hover { background: #fee2e2; color: #dc2626; }
.pa-bal-tinjau-kosong { padding: 1rem 0.25rem; font-size: 0.8rem; color: #94a3b8; font-style: italic; text-align: center; }

.pa-bal-tinjau-catatan { display: flex; gap: 0.6rem; align-items: flex-start; padding: 0.7rem 0.9rem; border-radius: 12px; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; }
.pa-bal-tinjau-catatan div { display: flex; flex-direction: column; gap: 0.1rem; font-size: 0.78rem; color: #075985; }
.pa-bal-tinjau-catatan strong { font-size: 0.8rem; color: #0c4a6e; }

@media (max-width: 720px) {
    .pa-bal-tinjau-angka { grid-template-columns: 1fr 1fr; }
    .pa-bal-tinjau-siap { grid-column: 1 / -1; }
    .pa-bal-tinjau-grid { grid-template-columns: 1fr; }
    .pa-bal-tinjau-baris__nilai { display: none; }
}
.pa-bal-tinjau-peringatan { display: flex; align-items: flex-start; gap: 0.4rem; margin: 0 0 0.6rem; padding: 0.5rem 0.7rem; border: 1px solid #fde68a; border-radius: 10px; background: #fffbeb; font-size: 0.76rem; line-height: 1.45; color: #92400e; }
.pa-bal-tanpa-kesimpulan { font-style: normal; font-weight: 700; color: #b45309; }
</style>
