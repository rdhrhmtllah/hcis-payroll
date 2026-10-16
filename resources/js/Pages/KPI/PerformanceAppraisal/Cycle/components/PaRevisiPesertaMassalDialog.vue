<template>
    <!--
     | REVISI PESERTA SESUDAH DIKUNCI — satu orang atau banyak sekaligus.
     |
     |   1. pilih tindakan            → menentukan siapa yang dicari (peserta / karyawan belum peserta /
     |                                   penilai yang masih punya tugas menilai)
     |   2. cari berfilter, centang   → satu per satu, atau "pilih semua" hasil filter
     |   3. pratinjau per orang       → bisa / tidak beserta sebabnya; yang tidak bisa dilewati
     |   4. satu alasan, jalankan     → tugas latar; penjadwalan HC Learn sekali di akhir
     -->
    <HcisDialog
        :model-value="modelValue"
        title="Revisi Peserta"
        kicker="SESUDAH SIKLUS DIKUNCI"
        icon="person-gear"
        size="lg"
        data-uji="dialog-revisi-massal"
        @update:model-value="tutup"
    >
        <div class="pa-rvm">
            <!-- 4. BERJALAN / HASIL -->
            <template v-if="tugas">
                <div class="pa-rvm__task-card">
                    <div class="pa-rvm__task-header">
                        <div class="pa-rvm__task-icon" :class="tugas.status === 'failed' ? 'is-failed' : 'is-running'">
                            <HcisIcon
                                :name="tugas.status === 'failed' ? 'x-circle-fill' : berjalan ? 'arrow-repeat' : 'check-circle-fill'"
                                :animation="berjalan ? 'spin' : undefined"
                                size="md"
                            />
                        </div>
                        <div class="pa-rvm__task-meta">
                            <h4 class="pa-rvm__judul" data-uji="rvm-status">{{ judulTugas }}</h4>
                            <p v-if="berjalan" class="pa-rvm__muted">
                                Berjalan di latar belakang — dialog ini boleh ditutup; Anda dapat memantau perkembangannya di Tugas &amp; Aktivitas.
                            </p>
                        </div>
                    </div>

                    <div class="pa-rvm__bar-track" aria-hidden="true">
                        <div class="pa-rvm__bar-fill" :style="{ width: `${tugas.persen || 0}%` }" />
                    </div>

                    <div v-if="(tugas.hasil || []).length" class="pa-rvm__task-results-wrap">
                        <span class="pa-rvm__section-label">Rincian Hasil Pemrosesan:</span>
                        <ul class="pa-rvm__hasil" data-uji="rvm-hasil">
                            <li v-for="(h, i) in tugas.hasil" :key="`${h.kode_karyawan}-${i}`" :class="{ 'is-gagal': !h.ok }">
                                <div class="pa-rvm__hasil-head">
                                    <HcisIcon :name="h.ok ? 'check-circle-fill' : 'x-circle-fill'" size="2xs" />
                                    <strong>{{ h.nama }}</strong>
                                </div>
                                <span v-if="h.ok" class="pa-rvm__hasil-detail">{{ ringkasHasil(h) }}</span>
                                <span v-else class="pa-rvm__hasil-error">{{ h.pesan }}</span>
                            </li>
                        </ul>
                    </div>

                    <HcisAlert
                        v-if="tugas.status === 'failed'"
                        variant="danger"
                        appearance="subtle"
                        size="sm"
                        :description="tugas.galat || 'Revisi terhenti.'"
                        icon="exclamation-triangle-fill"
                    />
                </div>
            </template>

            <template v-else>
                <!-- Mini Stepper Visual -->
                <div class="pa-rvm__stepper">
                    <div class="pa-rvm__step-node" :class="{ 'is-active': !tindakan || (tindakan && !pratinjau), 'is-done': Boolean(tindakan) }">
                        <span class="pa-rvm__step-num">1</span>
                        <span class="pa-rvm__step-text">Pilih Tindakan</span>
                    </div>
                    <div class="pa-rvm__step-line" :class="{ 'is-done': Boolean(tindakan) }" />
                    <div class="pa-rvm__step-node" :class="{ 'is-active': tindakan && !pratinjau, 'is-done': Boolean(pratinjau) }">
                        <span class="pa-rvm__step-num">2</span>
                        <span class="pa-rvm__step-text">Pilih Orang</span>
                    </div>
                    <div class="pa-rvm__step-line" :class="{ 'is-done': Boolean(pratinjau) }" />
                    <div class="pa-rvm__step-node" :class="{ 'is-active': Boolean(pratinjau) }">
                        <span class="pa-rvm__step-num">3</span>
                        <span class="pa-rvm__step-text">Pratinjau &amp; Alasan</span>
                    </div>
                </div>

                <!-- 1. TINDAKAN -->
                <section v-if="!pratinjau" class="pa-rvm__bagian">
                    <div class="pa-rvm__section-heading">
                        <div class="pa-rvm__section-badge">LANGKAH 1</div>
                        <h4 class="pa-rvm__sub">Pilih Jenis Tindakan Revisi</h4>
                        <p class="pa-rvm__section-hint">Tentukan perubahan yang ingin dilakukan pada peserta atau penilai siklus ini.</p>
                    </div>

                    <div class="pa-rvm__tindakan" role="radiogroup" aria-label="Tindakan revisi">
                        <button
                            v-for="t in DAFTAR_TINDAKAN"
                            :key="t.kode"
                            type="button"
                            class="pa-rvm__pilihan"
                            :class="[`is-${t.accent}`, { 'is-dipilih': tindakan === t.kode }]"
                            role="radio"
                            :aria-checked="tindakan === t.kode"
                            :data-uji="`rvm-tindakan-${t.kode}`"
                            @click="pilihTindakan(t.kode)"
                        >
                            <div class="pa-rvm__pilihan-top">
                                <div class="pa-rvm__pilihan-icon" :class="`is-${t.accent}`">
                                    <HcisIcon :name="t.icon" size="sm" />
                                </div>
                                <div class="pa-rvm__radio-circle" :class="{ 'is-checked': tindakan === t.kode }">
                                    <div class="pa-rvm__radio-dot" />
                                </div>
                            </div>
                            <div class="pa-rvm__pilihan-body">
                                <strong class="pa-rvm__pilihan-title">{{ t.judul }}</strong>
                                <span class="pa-rvm__pilihan-desc">{{ t.ket }}</span>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- 2. PILIH ORANG -->
                <section v-if="tindakan && !pratinjau" class="pa-rvm__bagian pa-rvm__bagian--pilih">
                    <div class="pa-rvm__section-heading">
                        <div class="pa-rvm__section-badge is-step2">LANGKAH 2</div>
                        <div class="pa-rvm__sub-wrap">
                            <h4 class="pa-rvm__sub">
                                2. Pilih orang
                            </h4>
                            <span class="pa-rvm__sumber-tag">
                                <HcisIcon name="people-fill" size="2xs" />
                                Target: {{ KET_SUMBER[sumber] }}
                            </span>
                        </div>
                    </div>

                    <!-- Filter Control Panel -->
                    <div class="pa-rvm__filter-card">
                        <div class="pa-rvm__saring">
                            <div class="pa-rvm__saring-search">
                                <HcisField
                                    id="rvm-cari"
                                    v-model="saring.q"
                                    size="sm"
                                    placeholder="Cari nama atau NIK karyawan…"
                                    leading-icon="search"
                                    clearable
                                />
                            </div>
                            <div class="pa-rvm__saring-selects">
                                <HcisSelect
                                    v-if="sumber === 'peserta'"
                                    v-model="saring.status"
                                    size="sm"
                                    placeholder="Semua status"
                                    :clearable="false"
                                    :filterable="false"
                                >
                                    <HcisSelectOption value="" label="Semua status" />
                                    <HcisSelectOption value="DIIKUTKAN" label="Diikutkan" />
                                    <HcisSelectOption value="DIKECUALIKAN" label="Dikecualikan" />
                                </HcisSelect>
                                <HcisSelect
                                    v-if="sumber === 'penilai'"
                                    v-model="saring.status"
                                    size="sm"
                                    placeholder="Semua penilai"
                                    :clearable="false"
                                    :filterable="false"
                                    data-uji="rvm-status-penilai"
                                >
                                    <HcisSelectOption value="" label="Semua penilai" />
                                    <HcisSelectOption value="TIDAK_AKTIF" label="Sudah tidak aktif di HRIS" />
                                </HcisSelect>
                                <template v-if="sumber !== 'penilai'">
                                    <HcisSelect
                                        v-model="saring.id_level"
                                        size="sm"
                                        placeholder="Semua level"
                                    >
                                        <HcisSelectOption value="" label="Semua level" />
                                        <HcisSelectOption v-for="l in opsi.level" :key="l.id" :value="String(l.id)" :label="l.nama" />
                                    </HcisSelect>
                                    <HcisSelect
                                        v-model="saring.divisi"
                                        size="sm"
                                        placeholder="Semua divisi"
                                    >
                                        <HcisSelectOption value="" label="Semua divisi" />
                                        <HcisSelectOption v-for="d in opsi.divisi" :key="d" :value="d" :label="d" />
                                    </HcisSelect>
                                    <HcisSelect
                                        v-model="saring.departemen"
                                        size="sm"
                                        placeholder="Semua departemen"
                                        data-uji="rvm-departemen"
                                    >
                                        <HcisSelectOption value="" label="Semua departemen" />
                                        <HcisSelectOption v-for="d in opsi.departemen" :key="d" :value="d" :label="d" />
                                    </HcisSelect>
                                </template>
                            </div>
                        </div>

                        <!-- Selection Toolbar -->
                        <div class="pa-rvm__alat">
                            <HcisCheckbox
                                :model-value="semuaHasilTerpilih"
                                :indeterminate="sebagianHasilTerpilih"
                                :label="`Pilih semua hasil (${hasil.length}${hasil.length >= BATAS ? '+' : ''})`"
                                size="sm"
                                :disabled="!hasil.length"
                                data-uji="rvm-pilih-semua"
                                @update:model-value="aturSemuaHasil"
                            />
                            <div class="pa-rvm__counter-pill" data-uji="rvm-terpilih">
                                <span class="pa-rvm__counter-badge">{{ terpilihList.length }} dipilih</span>
                                <button
                                    v-if="terpilihList.length"
                                    type="button"
                                    class="pa-rvm__tautan"
                                    @click="kosongkan"
                                >
                                    Kosongkan
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="hasil.length >= BATAS" class="pa-rvm__notice">
                        <HcisIcon name="info-circle" size="2xs" />
                        Hasil dibatasi {{ BATAS }} orang — persempit kata kunci atau filter untuk melihat sisanya.
                    </p>

                    <!-- Modern Data Table -->
                    <div class="pa-rvm__tabel-wrap">
                        <div v-if="mencari" class="pa-rvm__empty-state">
                            <HcisIcon name="arrow-repeat" size="sm" animation="spin" color="brand" />
                            <span>Mencari data karyawan…</span>
                        </div>
                        <div v-else-if="!hasil.length" class="pa-rvm__empty-state">
                            <HcisIcon name="person-x" size="sm" color="muted" />
                            <span>Tidak ada karyawan yang cocok dengan kriteria filter.</span>
                        </div>
                        <table v-else class="pa-rvm__tabel">
                            <thead>
                                <tr>
                                    <th class="pa-rvm__th-cek" />
                                    <th class="pa-rvm__th-karyawan">KARYAWAN</th>
                                    <th class="pa-rvm__th-status">STATUS SAAT INI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in hasil"
                                    :key="kunci(r)"
                                    :class="{ 'is-terpilih': Boolean(terpilih[kunci(r)]) }"
                                    @click="aturSatu(r, !terpilih[kunci(r)])"
                                >
                                    <td class="pa-rvm__cek" @click.stop>
                                        <HcisCheckbox
                                            :model-value="Boolean(terpilih[kunci(r)])"
                                            size="sm"
                                            :aria-label="`Pilih ${r.nama}`"
                                            :data-uji="`rvm-pilih-${r.kode_karyawan}`"
                                            @update:model-value="(v) => aturSatu(r, v)"
                                        />
                                    </td>
                                    <td>
                                        <div class="pa-rvm__karyawan-cell">
                                            <div class="pa-rvm__karyawan-avatar">
                                                {{ (r.nama || r.kode_karyawan || '?').charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="pa-rvm__karyawan-info">
                                                <strong class="pa-rvm__karyawan-nama">{{ r.nama || r.kode_karyawan }}</strong>
                                                <div class="pa-rvm__karyawan-tags">
                                                    <span class="pa-rvm__tag-code">{{ r.kode_karyawan }}</span>
                                                    <span v-if="r.nama_level" class="pa-rvm__tag-level">{{ r.nama_level }}</span>
                                                    <span v-if="r.nama_divisi" class="pa-rvm__tag-divisi">{{ r.nama_divisi }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="pa-rvm__status">
                                        <template v-if="sumber === 'peserta'">
                                            <span
                                                class="pa-rvm__status-pill"
                                                :class="r.status === 'DIIKUTKAN' ? 'is-green' : 'is-amber'"
                                            >
                                                {{ r.status === 'DIIKUTKAN' ? 'Diikutkan' : r.status === 'DIKECUALIKAN' ? 'Dikecualikan' : r.status }}
                                            </span>
                                        </template>
                                        <template v-else-if="sumber === 'penilai'">
                                            <div class="pa-rvm__penilai-status">
                                                <span class="pa-rvm__penilai-tugas">Menilai {{ r.menilai }} peserta</span>
                                                <span v-if="r.status === 'TIDAK_AKTIF'" class="pa-rvm__status-pill is-rose">
                                                    Tidak aktif di HRIS
                                                </span>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 3. PRATINJAU -->
                <section v-if="pratinjau" class="pa-rvm__bagian" data-uji="rvm-pratinjau">
                    <div class="pa-rvm__section-heading">
                        <div class="pa-rvm__section-badge is-step3">LANGKAH 3</div>
                        <h4 class="pa-rvm__sub">Pratinjau Hasil &amp; Konfirmasi</h4>
                    </div>

                    <div class="pa-rvm__preview-banner">
                        <div class="pa-rvm__preview-summary">
                            <HcisIcon name="info-circle-fill" size="sm" color="brand" />
                            <p class="pa-rvm__judul">{{ kalimatPratinjau }}</p>
                    <!-- Penilai baru memakai jendela pengisian yang sama: hampir habis / lewat disebut di muka. -->
                    <HcisAlert
                        v-if="peringatanJendela"
                        :variant="pratinjau.jendela.status === 'HAMPIR' ? 'warning' : 'danger'"
                        appearance="subtle"
                        size="sm"
                        :description="pratinjau.jendela.pesan"
                        icon="clock-history"
                        data-uji="rvm-jendela"
                    />
                        </div>

                        <p v-if="tindakan === 'SELARASKAN_RELASI'" class="pa-rvm__muted pa-rvm__preview-note" data-uji="rvm-acuan">
                            Relasi dibaca per tanggal acuan siklus<template v-if="acuanRelasi"> ({{ acuanRelasi }})</template>.
                            Hanya penilai usulan yang belum mengisi yang diganti; penilai pilihan admin dan yang sudah mengisi tidak disentuh.
                            <template v-if="pratinjau.rencana_dihitung === false"> Rincian keluar/masuk dihitung saat dijalankan — orang yang ternyata sudah sesuai dilewati.</template>
                        </p>
                    </div>

                    <!-- Alerts in Preview -->
                    <HcisAlert
                        v-if="pratinjau.ringkasan.perlu_koreksi_level > 0"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        :title="`${pratinjau.ringkasan.perlu_koreksi_level} orang Level PA-nya tidak dinilai di siklus ini`"
                        description="Bila mereka memang harus dinilai, koreksi Level PA-nya dulu di halaman Koreksi Level, lalu kembali ke sini dan tekan Ubah pilihan → Pratinjau lagi."
                        icon="exclamation-triangle-fill"
                        data-uji="rvm-perlu-koreksi"
                    >
                        <div class="pa-rvm__alert-btn-wrap">
                            <HcisButton
                                label="Buka Koreksi Level"
                                variant="secondary"
                                size="xs"
                                trailing-icon="box-arrow-up-right"
                                data-uji="rvm-buka-koreksi"
                                @click="bukaKoreksiLevel"
                            />
                        </div>
                    </HcisAlert>

                    <HcisAlert
                        v-if="tampilPeringatan"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        :title="`${pratinjau.ringkasan.peringatan} catatan penilai & bobot sebelum dijalankan`"
                        description="Kategori yang pengisinya di bawah ambang tidak dihitung di hasil (bobotnya dialihkan ke kategori lain), dan bobot khusus penilai dibagi ulang atau hilang. Rinciannya di bawah tiap orang; bereskan lewat Kelola penilai per peserta."
                        icon="exclamation-triangle-fill"
                        data-uji="rvm-peringatan"
                    />

                    <p v-if="pratinjau.peringatan_lengkap === false && (tindakan !== 'KECUALIKAN' || lepasPenilai)" class="pa-rvm__notice" data-uji="rvm-peringatan-sebagian">
                        <HcisIcon name="exclamation-circle" size="2xs" />
                        Kategori yang tidak akan dihitung hanya diperiksa untuk sebagian orang (terlalu banyak peserta yang dinilai). Pilih lebih sedikit orang untuk rincian lengkap.
                    </p>

                    <!-- Preview Rows List -->
                    <ul class="pa-rvm__hasil">
                        <li v-for="b in pratinjau.baris" :key="kunci(b)" :class="{ 'is-gagal': !b.boleh }">
                            <div class="pa-rvm__hasil-head">
                                <HcisIcon :name="b.boleh ? 'check-circle-fill' : 'x-circle-fill'" size="2xs" :color="b.boleh ? 'success' : 'danger'" />
                                <strong>{{ b.nama }}</strong>
                            </div>
                            <span class="pa-rvm__hasil-detail">{{ b.boleh ? dampakOrang(b) : b.sebab }}</span>
                            <template v-if="b.boleh && tampilPeringatan">
                                <em v-for="(w, i) in b.peringatan || []" :key="i" class="pa-rvm__peringatan">
                                    <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                    {{ w }}
                                </em>
                            </template>
                        </li>
                    </ul>

                    <!-- Additional Action Checkbox & Reason Input -->
                    <div class="pa-rvm__form-footer">
                        <HcisCheckbox
                            v-if="tindakan === 'KECUALIKAN' && (pratinjau.ringkasan.menilai ?? 0) > 0"
                            v-model="lepasPenilai"
                            size="sm"
                            :label="`Lepas juga sebagai penilai (${pratinjau.ringkasan.menilai} tugas menilai yang belum diisi) dan bentuk pengganti dari relasi — pilih bila mereka resign atau tidak bisa menilai`"
                            data-uji="rvm-lepas-penilai"
                        />
                        <div v-if="pratinjau.ringkasan.bisa > 0" class="pa-rvm__alasan-wrap">
                            <label class="pa-rvm__alasan-label">Alasan Revisi (Wajib):</label>
                            <HcisTextarea
                                v-model="alasan"
                                :rows="2"
                                placeholder="Tulis alasan revisi (minimal 5 huruf) — berlaku untuk semua orang yang dipilih di atas…"
                                data-uji="rvm-alasan"
                            />
                        </div>
                    </div>
                </section>

                <HcisAlert v-if="galat" variant="danger" appearance="subtle" size="sm" :description="galat" icon="exclamation-triangle-fill" data-uji="rvm-galat" />
            </template>
        </div>

        <template #footer>
            <div class="pa-rvm__dialog-footer">
                <HcisButton :label="tugas ? 'Tutup' : 'Batal'" variant="secondary" size="sm" @click="tutup(false)" />
                <HcisButton v-if="tugas && !berjalan" label="Revisi lagi" variant="secondary" size="sm" leading-icon="arrow-clockwise" @click="ulangi" />
                <HcisButton
                    v-if="!tugas && !pratinjau"
                    :label="`Lanjut ke Pratinjau (${terpilihList.length})`"
                    variant="primary"
                    size="sm"
                    trailing-icon="arrow-right"
                    :disabled="!tindakan || !terpilihList.length"
                    :loading="memuatPratinjau"
                    data-uji="rvm-pratinjau-btn"
                    @click="muatPratinjau"
                />
                <template v-if="!tugas && pratinjau">
                    <HcisButton label="Ubah pilihan" variant="ghost" size="sm" leading-icon="arrow-left" @click="pratinjau = null" />
                    <HcisButton
                        :label="`Jalankan untuk ${pratinjau.ringkasan.bisa} orang`"
                        variant="primary"
                        size="sm"
                        leading-icon="check-lg"
                        :disabled="!pratinjau.ringkasan.bisa || alasan.trim().length < 5"
                        :loading="memulai"
                        data-uji="rvm-jalankan"
                        @click="jalankan"
                    />
                </template>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import {
    HcisAlert, HcisButton, HcisCheckbox, HcisDialog, HcisField, HcisIcon, HcisSelect, HcisSelectOption, HcisTextarea,
} from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaRevisiPesertaMassalDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycleId: { type: [Number, String], required: true },
    /* Dibuka dari satu baris (mis. "level tertahan" di daftar tugas): orang itu langsung
       dicari dan dicentang, tindakannya Terapkan level kecuali disebut lain. */
    karyawanAwal: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'selesai']);

const BATAS = 1000;

const DAFTAR_TINDAKAN = [
    {
        kode: 'TERAPKAN_LEVEL',
        judul: 'Terapkan Level PA terbaru',
        ket: 'Untuk yang Level PA-nya dikoreksi. Penugasan dibentuk ulang.',
        sumber: 'peserta',
        status: 'DIIKUTKAN',
        icon: 'arrow-up-circle-fill',
        accent: 'blue',
    },
    {
        kode: 'IKUTKAN_KEMBALI',
        judul: 'Ikutkan kembali',
        ket: 'Untuk peserta yang dikecualikan. Penilai dibentuk lagi.',
        sumber: 'peserta',
        status: 'DIKECUALIKAN',
        icon: 'person-check-fill',
        accent: 'emerald',
    },
    {
        kode: 'KECUALIKAN',
        judul: 'Kecualikan dari siklus',
        ket: 'Penugasan yang belum diisi dibatalkan, jadwal HC Learn dihapus.',
        sumber: 'peserta',
        status: 'DIIKUTKAN',
        icon: 'person-dash-fill',
        accent: 'amber',
    },
    {
        kode: 'TAMBAH_SUSULAN',
        judul: 'Tambah peserta susulan',
        ket: 'Karyawan aktif yang belum menjadi peserta.',
        sumber: 'karyawan',
        status: '',
        icon: 'person-plus-fill',
        accent: 'violet',
    },
    {
        kode: 'SELARASKAN_RELASI',
        judul: 'Selaraskan penilai dengan relasi terbaru',
        ket: 'Untuk yang pindah atasan atau mutasi. Penilai lama yang belum mengisi diganti.',
        sumber: 'peserta',
        status: 'DIIKUTKAN',
        icon: 'diagram-3-fill',
        accent: 'teal',
    },
    {
        kode: 'LEPAS_PENILAI',
        judul: 'Lepas sebagai penilai',
        ket: 'Untuk penilai yang resign atau tidak bisa menilai. Pengganti dibentuk dari relasi.',
        sumber: 'penilai',
        status: '',
        icon: 'person-x-fill',
        accent: 'rose',
    },
];

const KET_SUMBER = {
    peserta: 'peserta siklus ini',
    karyawan: 'karyawan aktif yang belum menjadi peserta',
    penilai: 'siapa pun yang masih punya tugas menilai yang belum diisi',
};

const tindakan = ref('');
const sumber = computed(() => DAFTAR_TINDAKAN.find((t) => t.kode === tindakan.value)?.sumber ?? 'peserta');

const saring = reactive({ q: '', status: '', id_level: '', divisi: '', departemen: '' });
const opsi = reactive({ level: [], divisi: [], departemen: [] });
const hasil = ref([]);
const mencari = ref(false);
const terpilih = ref({});
const pratinjau = ref(null);
const memuatPratinjau = ref(false);
const alasan = ref('');
const memulai = ref(false);
const tugas = ref(null);
const galat = ref('');
const lepasPenilai = ref(false);

const kunci = (r) => `${r.kode_perusahaan}|${r.kode_karyawan}`;
const terpilihList = computed(() => Object.values(terpilih.value));
const semuaHasilTerpilih = computed(() => hasil.value.length > 0 && hasil.value.every((r) => terpilih.value[kunci(r)]));
const sebagianHasilTerpilih = computed(() => !semuaHasilTerpilih.value && hasil.value.some((r) => terpilih.value[kunci(r)]));
const berjalan = computed(() => ['queued', 'processing'].includes(tugas.value?.status));

let penunda = null;
let pemantau = null;

let pilihSetelahCari = null;

watch(() => props.modelValue, async (buka) => {
    if (!buka) return;
    if (props.karyawanAwal && !props.karyawanAwal.kode_karyawan && props.karyawanAwal.tindakan) {
        /* Dibuka dari peringatan (mis. "penilai tidak aktif"): tindakan dan saringannya saja. */
        pilihTindakan(props.karyawanAwal.tindakan);
        if (props.karyawanAwal.status !== undefined) saring.status = props.karyawanAwal.status;
        cari();
    } else if (props.karyawanAwal?.kode_karyawan) {
        pilihTindakan(props.karyawanAwal.tindakan || 'TERAPKAN_LEVEL');
        pilihSetelahCari = String(props.karyawanAwal.kode_karyawan).trim();
        saring.q = pilihSetelahCari;
        /* Langsung dicari: saat dibuka pertama kali watcher saringan belum tentu terdaftar. */
        cari();
    }
    try {
        const o = await paApi.opsiFilterKaryawanPraKecuali(props.cycleId);
        opsi.level = o?.level ?? [];
        opsi.divisi = o?.divisi ?? [];
        opsi.departemen = o?.departemen ?? [];
    } catch {
        /* Filter tetap bisa dipakai tanpa daftar pilihan. */
    }
}, { immediate: true });

watch(() => [saring.q, saring.status, saring.id_level, saring.divisi, saring.departemen, tindakan.value], () => {
    if (!tindakan.value) return;
    clearTimeout(penunda);
    penunda = setTimeout(cari, 350);
});

function pilihTindakan(kode) {
    const lama = DAFTAR_TINDAKAN.find((t) => t.kode === tindakan.value)?.sumber;
    tindakan.value = kode;
    const t = DAFTAR_TINDAKAN.find((x) => x.kode === kode);
    saring.status = t.status;
    pratinjau.value = null;
    lepasPenilai.value = false;
    /* Berganti sumber (peserta ↔ karyawan ↔ penilai) membuat pilihan lama tidak berlaku. */
    if (lama && lama !== t.sumber) terpilih.value = {};
}

async function cari() {
    mencari.value = true;
    galat.value = '';
    try {
        const r = await paApi.cariRevisiPeserta(props.cycleId, {
            sumber: sumber.value,
            q: saring.q,
            status: sumber.value === 'karyawan' ? '' : saring.status,
            id_level: saring.id_level && sumber.value !== 'penilai' ? [saring.id_level] : [],
            divisi: saring.divisi && sumber.value !== 'penilai' ? [saring.divisi] : [],
            departemen: saring.departemen && sumber.value !== 'penilai' ? [saring.departemen] : [],
        });
        hasil.value = r?.baris ?? [];
        if (pilihSetelahCari) {
            const cocok = hasil.value.find((x) => String(x.kode_karyawan).trim() === pilihSetelahCari);
            if (cocok) aturSatu(cocok, true);
            pilihSetelahCari = null;
        }
    } catch (e) {
        galat.value = e?.message || 'Pencarian gagal.';
    } finally {
        mencari.value = false;
    }
}

function aturSatu(r, pilih) {
    const salinan = { ...terpilih.value };
    if (pilih) salinan[kunci(r)] = { kode_perusahaan: r.kode_perusahaan, kode_karyawan: r.kode_karyawan, nama: r.nama };
    else delete salinan[kunci(r)];
    terpilih.value = salinan;
    pratinjau.value = null;
}

function aturSemuaHasil(pilih) {
    const salinan = { ...terpilih.value };
    for (const r of hasil.value) {
        if (pilih) salinan[kunci(r)] = { kode_perusahaan: r.kode_perusahaan, kode_karyawan: r.kode_karyawan, nama: r.nama };
        else delete salinan[kunci(r)];
    }
    terpilih.value = salinan;
    pratinjau.value = null;
}

function kosongkan() {
    terpilih.value = {};
    pratinjau.value = null;
}

async function muatPratinjau() {
    memuatPratinjau.value = true;
    galat.value = '';
    try {
        pratinjau.value = await paApi.pratinjauRevisiMassal(props.cycleId, {
            tindakan: tindakan.value,
            orang: terpilihList.value.map((o) => ({ kode_perusahaan: o.kode_perusahaan, kode_karyawan: o.kode_karyawan })),
        });
    } catch (e) {
        galat.value = e?.message || 'Pratinjau gagal.';
    } finally {
        memuatPratinjau.value = false;
    }
}

const kalimatPratinjau = computed(() => {
    const r = pratinjau.value?.ringkasan;
    if (!r) return '';
    const bagian = [`${r.bisa} dari ${r.total} bisa diproses`];
    if (r.tidak) bagian.push(`${r.tidak} dilewati`);
    if (r.penugasan_dibatalkan) bagian.push(`${r.penugasan_dibatalkan} penugasan belum diisi akan dibatalkan`);
    return `${bagian.join(' · ')}.`;
});

/* Jendela pengisian hanya relevan bila revisinya MEMBENTUK penilai baru. */
const peringatanJendela = computed(() => ['HAMPIR', 'LEWAT', 'KOSONG'].includes(pratinjau.value?.jendela?.status)
    && (tindakan.value !== 'KECUALIKAN' || lepasPenilai.value));

/* Kecualikan: peringatannya hanya berlaku bila orangnya juga dilepas sebagai penilai. */
const tampilPeringatan = computed(() => (pratinjau.value?.ringkasan?.peringatan ?? 0) > 0
    && (tindakan.value !== 'KECUALIKAN' || lepasPenilai.value));

const acuanRelasi = computed(() => pratinjau.value?.baris?.find((b) => b.rencana?.acuan)?.rencana.acuan ?? '');

function bukaKoreksiLevel() {
    window.open(pratinjau.value?.alamat_koreksi_level || '/performance-appraisal/master/level-pa', '_blank', 'noopener');
}

function dampakOrang(b) {
    const belum = (b.dampak?.penugasan_aktif ?? 0) - (b.dampak?.penugasan_diisi ?? 0);
    if (tindakan.value === 'TAMBAH_SUSULAN') return `Masuk sebagai peserta${b.level_terkini ? ` level ${b.level_terkini}` : ''}.`;
    if (tindakan.value === 'TERAPKAN_LEVEL') return `${b.level_siklus ?? '-'} → ${b.level_terkini ?? '-'} · ${belum} penugasan dibentuk ulang.`;
    if (tindakan.value === 'KECUALIKAN') {
        return `${belum} penugasan dibatalkan${b.dampak?.penugasan_diisi ? ` · ${b.dampak.penugasan_diisi} yang sudah diisi tetap tercatat` : ''}`
            + `${b.dampak?.menilai ? ` · masih menilai ${b.dampak.menilai} peserta` : ''}.`;
    }
    if (tindakan.value === 'LEPAS_PENILAI') return `${b.dampak?.menilai ?? 0} tugas menilai dibatalkan · pengganti dibentuk dari relasi.`;
    if (tindakan.value === 'SELARASKAN_RELASI') {
        if (!b.rencana) return 'Rencana dihitung saat dijalankan.';
        const bagian = [];
        if (b.rencana.keluar.length) bagian.push(`Keluar: ${b.rencana.keluar.join(', ')}`);
        if (b.rencana.masuk.length) bagian.push(`Masuk: ${b.rencana.masuk.join(', ')}`);
        return `${bagian.join(' · ')}.`;
    }
    return 'Penilai dibentuk lagi dari relasi organisasi.';
}

async function jalankan() {
    memulai.value = true;
    galat.value = '';
    try {
        const r = await paApi.mulaiRevisiMassal(props.cycleId, {
            tindakan: tindakan.value,
            orang: terpilihList.value.map((o) => ({ kode_perusahaan: o.kode_perusahaan, kode_karyawan: o.kode_karyawan })),
            alasan: alasan.value.trim(),
            ...(tindakan.value === 'KECUALIKAN' && lepasPenilai.value ? { lepas_penilai: true } : {}),
        });
        tugas.value = r?.tugas ?? null;
        emit('selesai', { mulai: true });
        pantau();
    } catch (e) {
        galat.value = e?.message || 'Revisi gagal dimulai.';
    } finally {
        memulai.value = false;
    }
}

function pantau() {
    clearInterval(pemantau);
    pemantau = setInterval(async () => {
        try {
            const r = await paApi.statusRevisiMassal(props.cycleId);
            if (r?.tugas && r.tugas.id === tugas.value?.id) tugas.value = r.tugas;
        } catch {
            /* Pembacaan berikutnya yang menjawab. */
        }
        if (!berjalan.value) {
            clearInterval(pemantau);
            emit('selesai', { tugas: tugas.value });
        }
    }, 3000);
}

onBeforeUnmount(() => {
    clearInterval(pemantau);
    clearTimeout(penunda);
});

const NAMA_TINDAKAN = Object.fromEntries(DAFTAR_TINDAKAN.map((t) => [t.kode, t.judul]));

const judulTugas = computed(() => {
    const t = tugas.value;
    if (!t) return '';
    const r = t.ringkasan || {};
    const nama = NAMA_TINDAKAN[t.tindakan] ?? 'Revisi';
    if (berjalan.value) return `${nama}: ${r.sudah ?? 0} dari ${r.total ?? 0} orang diproses…`;
    if (t.status === 'failed') return `${nama} terhenti.`;
    return `${nama} selesai: ${r.berhasil ?? 0} berhasil${r.gagal ? `, ${r.gagal} gagal` : ''}.`
        + (t.penjadwalan ? ' Penjadwalan ke HC Learn berjalan di latar.' : '');
});

function ringkasHasil(h) {
    /* Catatan server dipakai bila ada yang perlu ditindaklanjuti (peserta tanpa pengganti) atau
       tidak ada yang berubah (sudah sesuai relasi). */
    if (h.pesan) return h.pesan;
    const t = tugas.value?.tindakan || tindakan.value;
    if (t === 'KECUALIKAN') return `${h.dibatalkan} penugasan dibatalkan${h.dibentuk ? ` · ${h.dibentuk} pengganti dibentuk` : ''}`;
    if (t === 'LEPAS_PENILAI' || t === 'SELARASKAN_RELASI') return `${h.dibatalkan} penilai dibatalkan · ${h.dibentuk} pengganti dibentuk`;
    return `${h.dibentuk} penugasan dibentuk${h.dibatalkan ? ` · ${h.dibatalkan} dibatalkan` : ''}`;
}

function ulangi() {
    tugas.value = null;
    pratinjau.value = null;
    terpilih.value = {};
    alasan.value = '';
    galat.value = '';
    lepasPenilai.value = false;
    if (tindakan.value) cari();
}

function tutup(v) {
    if (v) return;
    clearInterval(pemantau);
    emit('update:modelValue', false);
    if (!berjalan.value) {
        tindakan.value = '';
        Object.assign(saring, { q: '', status: '', id_level: '', divisi: '', departemen: '' });
        hasil.value = [];
        ulangi();
    }
}
</script>

<style scoped>
.pa-rvm {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Stepper Visual */
.pa-rvm__stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.pa-rvm__step-node {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 600;
}

.pa-rvm__step-node.is-active {
    color: #0f172a;
}

.pa-rvm__step-node.is-done {
    color: #2563eb;
}

.pa-rvm__step-num {
    width: 1.375rem;
    height: 1.375rem;
    border-radius: 9999px;
    background: #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
}

.pa-rvm__step-node.is-active .pa-rvm__step-num {
    background: #2563eb;
    color: #ffffff;
}

.pa-rvm__step-node.is-done .pa-rvm__step-num {
    background: #dbeafe;
    color: #1d4ed8;
}

.pa-rvm__step-line {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    margin: 0 0.75rem;
}

.pa-rvm__step-line.is-done {
    background: #93c5fd;
}

/* Section Headings */
.pa-rvm__bagian {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.pa-rvm__section-heading {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-rvm__section-badge {
    font-size: 0.625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    width: fit-content;
}

.pa-rvm__section-badge.is-step2 {
    color: #0d9488;
    background: #f0fdfa;
    border-color: #ccfbf1;
}

.pa-rvm__section-badge.is-step3 {
    color: #7c3aed;
    background: #f5f3ff;
    border-color: #ede9fe;
}

.pa-rvm__sub-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pa-rvm__sub {
    margin: 0;
    color: #0f172a;
    font-size: 0.9375rem;
    font-weight: 700;
}

.pa-rvm__section-hint {
    margin: 0;
    color: #64748b;
    font-size: 0.78125rem;
}

.pa-rvm__sumber-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.2rem 0.5rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #475569;
}

/* Action Cards Grid */
.pa-rvm__tindakan {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-rvm__pilihan {
    display: flex;
    flex-direction: column;
    padding: 0.875rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    position: relative;
}

.pa-rvm__pilihan:hover {
    border-color: #cbd5e1;
    background: #fafafa;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
}

.pa-rvm__pilihan:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

.pa-rvm__pilihan.is-dipilih {
    border-color: #2563eb;
    background: #f8faff;
    box-shadow: 0 0 0 1px #2563eb, 0 4px 8px -2px rgba(37, 99, 235, 0.1);
}

.pa-rvm__pilihan-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.625rem;
}

.pa-rvm__pilihan-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rvm__pilihan-icon.is-blue {
    background: #eff6ff;
    color: #2563eb;
}

.pa-rvm__pilihan-icon.is-emerald {
    background: #ecfdf5;
    color: #059669;
}

.pa-rvm__pilihan-icon.is-amber {
    background: #fffbeb;
    color: #d97706;
}

.pa-rvm__pilihan-icon.is-violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.pa-rvm__pilihan-icon.is-teal {
    background: #f0fdfa;
    color: #0d9488;
}

.pa-rvm__pilihan-icon.is-rose {
    background: #fff1f2;
    color: #e11d48;
}

.pa-rvm__radio-circle {
    width: 1.125rem;
    height: 1.125rem;
    border-radius: 9999px;
    border: 1.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.pa-rvm__radio-circle.is-checked {
    border-color: #2563eb;
    background: #2563eb;
}

.pa-rvm__radio-dot {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 9999px;
    background: #ffffff;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.pa-rvm__radio-circle.is-checked .pa-rvm__radio-dot {
    opacity: 1;
}

.pa-rvm__pilihan-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-rvm__pilihan-title {
    color: #0f172a;
    font-size: 0.8125rem;
    font-weight: 700;
    line-height: 1.3;
}

.pa-rvm__pilihan-desc {
    color: #64748b;
    font-size: 0.71875rem;
    line-height: 1.45;
}

/* Filter Card */
.pa-rvm__filter-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-rvm__saring {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-rvm__saring-search {
    width: 100%;
}

.pa-rvm__saring-selects {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.5rem;
}

.pa-rvm__alat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.625rem;
    border-top: 1px solid #edf2f7;
}

.pa-rvm__counter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
}

.pa-rvm__counter-badge {
    background: #e2e8f0;
    color: #1e293b;
    font-weight: 700;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
}

.pa-rvm__tautan {
    background: none;
    border: none;
    color: #2563eb;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
}

.pa-rvm__tautan:hover {
    background: #dbeafe;
    text-decoration: underline;
}

.pa-rvm__notice {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin: 0;
    color: #64748b;
    font-size: 0.75rem;
}

/* Modern Data Table */
.pa-rvm__tabel-wrap {
    max-height: 20rem;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
}

.pa-rvm__empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2.5rem 1rem;
    color: #64748b;
    font-size: 0.8125rem;
}

.pa-rvm__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.pa-rvm__tabel thead {
    position: sticky;
    top: 0;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    z-index: 2;
}

.pa-rvm__tabel th {
    padding: 0.625rem 0.75rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #475569;
    letter-spacing: 0.04em;
}

.pa-rvm__th-cek {
    width: 2.5rem;
}

.pa-rvm__th-karyawan {
    width: auto;
}

.pa-rvm__th-status {
    width: 150px;
    text-align: right;
}

.pa-rvm__tabel tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.pa-rvm__tabel tbody tr:hover {
    background: #f8fafc;
}

.pa-rvm__tabel tbody tr.is-terpilih {
    background: #eff6ff;
}

.pa-rvm__tabel td {
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.pa-rvm__cek {
    text-align: center;
}

.pa-rvm__karyawan-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-rvm__karyawan-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    background: #e2e8f0;
    color: #334155;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rvm__karyawan-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.pa-rvm__karyawan-nama {
    color: #0f172a;
    font-weight: 600;
    line-height: 1.25;
}

.pa-rvm__karyawan-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.pa-rvm__tag-code {
    font-family: monospace;
    font-size: 0.6875rem;
    background: #f1f5f9;
    color: #475569;
    padding: 0.0625rem 0.25rem;
    border-radius: 4px;
}

.pa-rvm__tag-level {
    font-size: 0.6875rem;
    font-weight: 600;
    background: #f0fdf4;
    color: #166534;
    padding: 0.0625rem 0.25rem;
    border-radius: 4px;
}

.pa-rvm__tag-divisi {
    font-size: 0.6875rem;
    color: #64748b;
}

.pa-rvm__status {
    text-align: right;
}

.pa-rvm__status-pill {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    border-radius: 9999px;
}

.pa-rvm__status-pill.is-green {
    background: #dcfce7;
    color: #15803d;
}

.pa-rvm__status-pill.is-amber {
    background: #fef3c7;
    color: #b45309;
}

.pa-rvm__status-pill.is-rose {
    background: #ffe4e6;
    color: #be123c;
}

.pa-rvm__penilai-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.pa-rvm__penilai-tugas {
    font-size: 0.75rem;
    color: #334155;
    font-weight: 500;
}

/* Preview Section */
.pa-rvm__preview-banner {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 0.875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.pa-rvm__preview-summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-rvm__judul {
    margin: 0;
    color: #1e3a8a;
    font-size: 0.875rem;
    font-weight: 700;
}

.pa-rvm__preview-note {
    font-size: 0.75rem;
    color: #1e40af;
    line-height: 1.45;
}

.pa-rvm__hasil {
    display: flex;
    flex-direction: column;
    max-height: 15rem;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    list-style: none;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
}

.pa-rvm__hasil li {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.625rem 0.875rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.8125rem;
}

.pa-rvm__hasil li:last-child {
    border-bottom: none;
}

.pa-rvm__hasil li.is-gagal {
    background: #fff5f5;
}

.pa-rvm__hasil-head {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.pa-rvm__hasil-head strong {
    color: #0f172a;
    font-weight: 600;
}

.pa-rvm__hasil-detail {
    color: #475569;
    font-size: 0.75rem;
    padding-left: 1.25rem;
}

.pa-rvm__hasil-error {
    color: #b91c1c;
    font-size: 0.75rem;
    padding-left: 1.25rem;
    font-weight: 500;
}

.pa-rvm__peringatan {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    color: #b45309;
    font-size: 0.75rem;
    font-style: normal;
    padding-left: 1.25rem;
    margin-top: 0.125rem;
}

.pa-rvm__form-footer {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.pa-rvm__alasan-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.pa-rvm__alasan-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
}

.pa-rvm__alert-btn-wrap {
    margin-top: 0.5rem;
}

/* Background Task Status */
.pa-rvm__task-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.pa-rvm__task-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
}

.pa-rvm__task-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rvm__task-icon.is-running {
    background: #eff6ff;
    color: #2563eb;
}

.pa-rvm__task-icon.is-failed {
    background: #fef2f2;
    color: #ef4444;
}

.pa-rvm__task-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-rvm__bar-track {
    width: 100%;
    height: 8px;
    border-radius: 9999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-rvm__bar-fill {
    height: 100%;
    background: #2563eb;
    border-radius: 9999px;
    transition: width 0.3s ease;
}

.pa-rvm__task-results-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-rvm__section-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
}

.pa-rvm__muted {
    margin: 0;
    color: #64748b;
    font-size: 0.8125rem;
}

/* Dialog Footer */
.pa-rvm__dialog-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

@media (max-width: 768px) {
    .pa-rvm__tindakan {
        grid-template-columns: 1fr;
    }

    .pa-rvm__saring-selects {
        grid-template-columns: 1fr;
    }

    .pa-rvm__stepper {
        display: none;
    }
}
</style>

