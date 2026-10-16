<template>
    <div class="pa-ttb">
        <!-- 1. Executive Stat Cards -->
        <div class="pa-ttb__grid">
            <div class="pa-ttb__kartu">
                <span>Hasil siap terbit</span>
                <strong>{{ siap }}</strong>
                <small>status DIHITUNG</small>
            </div>
            <div class="pa-ttb__kartu is-ungu">
                <span>Dirilis ke leader</span>
                <strong>{{ angka(cacah?.dirilis_leader) }} <small>/ {{ angka(cacah?.hasil_total) }}</small></strong>
                <div class="pa-ttb__bar"><i :style="{ width: persen(cacah?.dirilis_leader) }" /></div>
            </div>
            <div class="pa-ttb__kartu is-hijau">
                <span>Terbit ke karyawan</span>
                <strong>{{ angka(cacah?.hasil_terbit) }} <small>/ {{ angka(cacah?.hasil_total) }}</small></strong>
                <div class="pa-ttb__bar"><i :style="{ width: persen(cacah?.hasil_terbit) }" /></div>
            </div>
            <div class="pa-ttb__kartu" :class="{ 'is-warning': belumSiap }">
                <span>Belum siap</span>
                <strong>{{ belumSiap }}</strong>
                <small>{{ angka(cacah?.hasil_tertahan) }} tertahan · {{ angka(cacah?.hasil_belum) }} belum dihitung</small>
            </div>
        </div>

        <!-- Syarat server belum terpenuhi: tombol rilis/terbit mati, sebabnya disebut di sini. -->
        <p v-if="syarat.alasan" class="pa-ttb__syarat" data-uji="syarat-publish">
            <HcisIcon name="lock-fill" size="2xs" /> {{ syarat.alasan }}
        </p>

        <!-- 2. Alur Utama Penerbitan Siklus -->
        <ol class="pa-ttb__urutan">
            <li>
                <div>
                    <strong>1 · Rilis ke leader</strong>
                    <p>Opsional. Leader bisa melihat hasil timnya lebih dulu sebelum karyawan. Hanya hasil DIHITUNG/TERBIT yang dirilis.</p>
                </div>
                <HcisButton
                    label="Rilis ke leader"
                    variant="secondary"
                    size="sm"
                    leading-icon="person-badge"
                    :disabled="!rilisAktif || !bisaDirilis"
                    :title="!rilisAktif ? (syarat.alasan || 'Belum boleh: lihat penghambat di atas') : !bisaDirilis ? 'Semua hasil yang siap sudah dirilis' : ''"
                    data-uji="rilis-leader"
                    @click="bukaDialogRilisSiklus"
                />
            </li>
            <li>
                <div>
                    <strong>2 · Terbitkan ke karyawan</strong>
                    <p>Hasil yang siap diterbitkan; yang tertahan/belum dihitung tetap menunggu. Bisa diulang setelah sisanya beres.</p>
                </div>
                <HcisButton
                    label="Terbitkan…"
                    variant="success"
                    size="sm"
                    leading-icon="send-check-fill"
                    :disabled="!terbitAktif"
                    :title="!terbitAktif ? (syarat.alasan || '') : ''"
                    data-uji="buka-penerbitan"
                    @click="$emit('buka-penerbitan')"
                />
            </li>
            <li>
                <div>
                    <strong>3 · Tutup penerbitan</strong>
                    <p>Satu arah: siklus menjadi PUBLISHED dan tidak bisa dihitung ulang lagi. Hanya bisa bila semua hasil sudah terbit.</p>
                </div>
                <HcisButton
                    label="Tutup penerbitan…"
                    variant="warning"
                    size="sm"
                    leading-icon="lock-fill"
                    :disabled="!terbitAktif || !semuaTerbit"
                    :title="!semuaTerbit ? 'Masih ada hasil yang belum terbit' : ''"
                    data-uji="buka-tutup-penerbitan"
                    @click="$emit('buka-penerbitan')"
                />
            </li>
        </ol>

        <!-- 3. Daftar peserta: saringan & paginasi di server, centang berlaku lintas halaman -->
        <section class="pa-ttb__section">
            <div class="pa-ttb__section-head">
                <div class="pa-ttb__section-titles">
                    <h4 class="pa-ttb__section-title">
                        <HcisIcon name="check2-square" size="xs" />
                        Kontrol Selektif Penerbitan &amp; Rilis Peserta
                    </h4>
                    <p class="pa-ttb__section-sub">
                        Centang peserta untuk dirilis ke leader atau diterbitkan ke karyawan. Centang di kepala tabel memilih
                        <b>seluruh hasil saringan di semua halaman</b>, bukan hanya halaman yang terbuka.
                    </p>
                </div>
            </div>

            <HcisFilterBar
                :search="saring.q"
                search-placeholder="Cari nama atau NIK..."
                :search-debounce="300"
                :quick-chips="chipStatus"
                :active-chip-value="saring.status"
                :filters="definisiSaringan"
                :filter-values="nilaiSaringan"
                variant="card"
                sentiment="brand"
                size="sm"
                :results-count="memuat ? null : totalPeserta"
                show-active-tags
                data-uji="saringan-terbit"
                @update:search="ubahCari"
                @chip-click="ubahStatus"
                @filter-change="ubahSaringan"
                @reset="resetSaringan"
            />

            <p v-if="galat" class="pa-ttb-galat">{{ galat }}</p>

            <HcisTable
                v-else
                :columns="kolom"
                :rows="peserta"
                row-key="Id_PA_Participant"
                :loading="memuat || mengambilSemua"
                :loading-rows-count="6"
                selectable
                :row-selectable="bisaDipilih"
                :selected-keys="terpilih"
                responsive="scroll"
                variant="card"
                sentiment="brand"
                hoverable
                sticky-header
                empty-title="Tidak ada peserta yang cocok"
                empty-description="Ubah kata kunci atau saringan di atas."
                empty-icon="people"
                data-uji="tabel-terbit"
                @update:selected-keys="(k) => (terpilih = k)"
                @select-all="pilihSemua"
            >
                <template #bulk-actions>
                    <span class="pa-ttb__selection-hint">
                        {{ terpilih.length }} dipilih{{ terpilih.length > peserta.length ? ' dari seluruh halaman' : '' }}
                    </span>
                    <HcisButton
                        label="Rilis ke Leader"
                        variant="secondary"
                        size="sm"
                        leading-icon="person-badge"
                        :disabled="!rilisAktif || memprosesBatch"
                        :loading="memprosesBatch && aksiBatch === 'rilis'"
                        data-uji="rilis-terpilih"
                        @click="bukaRilisTerpilih"
                    />
                    <HcisButton
                        label="Terbitkan ke Karyawan"
                        variant="success"
                        size="sm"
                        leading-icon="send-check-fill"
                        :disabled="!terbitAktif || memprosesBatch"
                        :loading="memprosesBatch && aksiBatch === 'terbit'"
                        data-uji="terbit-terpilih"
                        @click="bukaTerbitTerpilih"
                    />
                    <HcisButton label="Batal pilih" variant="ghost" size="sm" :disabled="memprosesBatch" @click="terpilih = []" />
                </template>

                <template #cell-no="{ index }">
                    <span class="text-muted font-num">{{ (halaman - 1) * perHalaman + index + 1 }}</span>
                </template>

                <template #cell-karyawan="{ row }">
                    <div class="pa-ttb__karyawan-cell">
                        <strong class="pa-ttb__karyawan-nama">{{ row.Nama_Karyawan_Saat_Cycle }}</strong>
                        <small class="pa-ttb__karyawan-nip">{{ row.Kode_Karyawan }}</small>
                    </div>
                </template>

                <template #cell-posisi="{ row }">
                    <div class="pa-ttb__posisi-cell">
                        <span class="pa-ttb__posisi-unit" :title="row.Nama_Divisi_Saat_Cycle">{{ row.Nama_Divisi_Saat_Cycle || '–' }}</span>
                        <span v-if="row.Nama_Level_Saat_Cycle" class="pa-ttb__level-chip">{{ labelLevelPa(row) }}</span>
                    </div>
                </template>

                <template #cell-nilai="{ row }">
                    <div class="pa-ttb__nilai-cell">
                        <strong class="pa-ttb__skor">{{ row.Final_Score !== null ? angka(row.Final_Score, 2) : '–' }}</strong>
                        <span
                            v-if="gradeBaris(row)"
                            class="pa-grade-pill"
                            :class="getGradeTierClass(gradeBaris(row))"
                        >
                            {{ labelGrade(gradeBaris(row)) }}
                        </span>
                    </div>
                </template>

                <template #cell-leader="{ row }">
                    <div class="pa-ttb__status-cell">
                        <HcisBadge v-if="row.Published_Leader_At" label="Sudah Dirilis" variant="purple" appearance="soft" size="xs" icon="person-check-fill" dot />
                        <HcisBadge v-else label="Belum Dirilis" variant="neutral" appearance="subtle" size="xs" />
                        <small v-if="row.Published_Leader_At" class="pa-ttb__tgl-kecil">{{ formatTgl(row.Published_Leader_At) }}</small>
                    </div>
                </template>

                <template #cell-karyawan_status="{ row }">
                    <div class="pa-ttb__status-cell">
                        <HcisBadge v-if="row.Status_Hasil === 'TERBIT' || row.Published_At" label="Sudah Terbit" variant="success" appearance="soft" size="xs" icon="check-circle-fill" dot />
                        <HcisBadge v-else-if="row.Status_Hasil === 'DIHITUNG'" label="Siap Terbit" variant="brand" appearance="soft" size="xs" icon="clock-fill" />
                        <HcisBadge v-else-if="row.Status_Hasil === 'TERTAHAN'" label="Tertahan" variant="warning" appearance="soft" size="xs" icon="exclamation-triangle-fill" />
                        <HcisBadge v-else label="Belum Dihitung" variant="neutral" appearance="subtle" size="xs" />
                        <small v-if="row.Status_Hasil === 'TERTAHAN' && row.Alasan_Tertahan" class="pa-ttb__alasan-tertahan" :title="row.Alasan_Tertahan">
                            {{ row.Alasan_Tertahan }}
                        </small>
                        <small v-else-if="row.Published_At" class="pa-ttb__tgl-kecil">{{ formatTgl(row.Published_At) }}</small>
                    </div>
                </template>

                <template #cell-aksi="{ row }">
                    <div class="pa-ttb__row-actions">
                        <button
                            v-if="!row.Published_Leader_At && ['DIHITUNG', 'TERBIT'].includes(row.Status_Hasil)"
                            type="button"
                            class="pa-ttb__mini-btn is-leader"
                            :disabled="!rilisAktif || memprosesSatu === row.Id_PA_Participant"
                            title="Buka akses hasil ini untuk Leader di PA Tim"
                            @click="bukaRilisSatu(row)"
                        >
                            <HcisIcon name="person-badge" size="2xs" />
                            <span>Rilis</span>
                        </button>
                        <button
                            v-else-if="row.Published_Leader_At && row.Status_Hasil !== 'TERBIT'"
                            type="button"
                            class="pa-ttb__mini-btn is-cabut"
                            :disabled="!bolehRilis || memprosesSatu === row.Id_PA_Participant"
                            title="Tarik kembali akses leader atas hasil ini"
                            @click="bukaCabutLeader(row)"
                        >
                            <HcisIcon name="person-x" size="2xs" />
                            <span>Cabut</span>
                        </button>
                        <button
                            v-if="row.Status_Hasil === 'DIHITUNG'"
                            type="button"
                            class="pa-ttb__mini-btn is-terbit"
                            :disabled="!terbitAktif || memprosesSatu === row.Id_PA_Participant"
                            title="Terbitkan hasil ke akun HCIS karyawan"
                            :data-uji="`terbit-satu-${row.Id_PA_Participant}`"
                            @click="bukaTerbitSatu(row)"
                        >
                            <HcisIcon name="send-check" size="2xs" />
                            <span>Terbitkan</span>
                        </button>
                    </div>
                </template>
            </HcisTable>

            <div v-if="totalPeserta > 0" class="pa-ttb__footer-page">
                <HcisPagination
                    :current-page="halaman"
                    v-model:page-size="perHalaman"
                    :total="totalPeserta"
                    :page-sizes="[10, 25, 50, 100]"
                    size="sm"
                    @current-change="ubahHalaman"
                    @size-change="gantiUkuran"
                />
            </div>
        </section>

        <!-- DIALOG 1: Rilis ke Leader (Siklus Seluruhnya ATAU Peserta Terpilih) -->
        <HcisDialog
            v-model="dialogRilis"
            :title="modeAksi === 'terpilih' ? `Rilis hasil ${terpilih.length} peserta ke leader?` : 'Rilis hasil ke leader?'"
            size="sm"
            sentiment="brand"
            icon="person-badge"
        >
            <p v-if="modeAksi === 'siklus'" class="pa-ttb__konfirmasi">
                Hasil berstatus DIHITUNG/TERBIT yang belum dirilis akan dibuka untuk leader masing-masing
                (sekarang {{ angka(cacah?.dirilis_leader) }} dari {{ angka(cacah?.hasil_total) }} sudah dirilis).
                Rilis per orang bisa dicabut dari detail hasil selama belum terbit.
            </p>
            <div v-else class="pa-ttb__konfirmasi">
                <p>
                    Anda akan membuka hasil <strong>{{ terpilih.length }} peserta terpilih</strong> kepada atasan/leader masing-masing di halaman PA Tim.
                </p>
                <p class="text-muted mb-0">
                    Leader dapat meninjau capaian timnya terlebih dahulu sebelum hasil diterbitkan resmi ke karyawan.
                </p>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="merilis" @click="dialogRilis = false" />
                <HcisButton
                    label="Ya, rilis"
                    variant="primary"
                    size="sm"
                    leading-icon="send"
                    :loading="merilis"
                    data-uji="konfirmasi-rilis"
                    @click="rilis"
                />
            </template>
        </HcisDialog>

        <!-- DIALOG 2: Terbitkan ke Karyawan (Peserta Terpilih) -->
        <HcisDialog
            v-model="dialogTerbitTerpilih"
            :title="`Terbitkan hasil ${terpilih.length} peserta ke karyawan?`"
            size="sm"
            sentiment="success"
            icon="send-check-fill"
        >
            <div class="pa-ttb__konfirmasi">
                <p>
                    Anda akan menerbitkan hasil penilaian kinerja untuk <strong>{{ terpilih.length }} karyawan terpilih</strong>.
                </p>
                <p class="text-muted mb-0">
                    Hasil berstatus <strong>TERBIT</strong> akan langsung dapat diakses dan dilihat oleh karyawan yang dinilai pada menu profil PA mereka.
                </p>
                <p v-if="pengingatKualitatif.belum > 0" class="pa-ttb__pengingat" data-uji="pengingat-kualitatif">
                    <HcisIcon name="chat-quote-fill" size="2xs" />
                    {{ pengingatKualitatif.belum }} peserta belum punya kesimpulan kualitatif ({{ pengingatKualitatif.contoh.join(', ') }}{{ pengingatKualitatif.belum > pengingatKualitatif.contoh.length ? ', …' : '' }}).
                    Sesudah terbit, kesimpulannya tidak bisa ditulis lagi.
                </p>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="menerbitkanBatch" @click="dialogTerbitTerpilih = false" />
                <HcisButton
                    label="Ya, terbitkan sekarang"
                    variant="success"
                    size="sm"
                    leading-icon="send-check-fill"
                    :loading="menerbitkanBatch"
                    @click="jalankanTerbitTerpilih"
                />
            </template>
        </HcisDialog>

        <!-- DIALOG: Terbitkan SATU peserta. Dulu tombol per baris langsung menerbitkan tanpa konfirmasi,
             padahal terbit tidak bisa ditarik kembali dan karyawan langsung menerima notifikasinya. -->
        <HcisDialog
            v-model="dialogTerbitSatu"
            :title="`Terbitkan hasil ${pesertaTerbit?.Nama_Karyawan_Saat_Cycle || ''}?`"
            size="sm"
            sentiment="success"
            icon="send-check-fill"
        >
            <div class="pa-ttb__konfirmasi" data-uji="konfirmasi-terbit-satu">
                <p>Hasil akan langsung terlihat oleh karyawan dan ia menerima notifikasi. <strong>Penerbitan tidak bisa ditarik kembali.</strong></p>
                <p v-if="pengingatKualitatif.belum > 0" class="pa-ttb__pengingat" data-uji="pengingat-kualitatif">
                    <HcisIcon name="chat-quote-fill" size="2xs" />
                    Peserta ini belum punya kesimpulan kualitatif. Sesudah terbit, kesimpulannya tidak bisa ditulis lagi.
                </p>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="memprosesSatu !== null" @click="dialogTerbitSatu = false" />
                <HcisButton
                    label="Ya, terbitkan"
                    variant="success"
                    size="sm"
                    leading-icon="send-check-fill"
                    :loading="memprosesSatu !== null"
                    data-uji="ya-terbit-satu"
                    @click="terbitkanSatu(pesertaTerbit)"
                />
            </template>
        </HcisDialog>

        <!-- DIALOG: Rilis SATU peserta ke leader. Dulu tombol per baris langsung merilis. -->
        <HcisDialog
            v-model="dialogRilisSatu"
            :title="`Rilis hasil ${pesertaRilis?.Nama_Karyawan_Saat_Cycle || ''} ke leader?`"
            size="sm"
            sentiment="brand"
            icon="person-badge"
        >
            <div class="pa-ttb__konfirmasi" data-uji="konfirmasi-rilis-satu">
                <p>Atasan/leader akan bisa melihat hasil ini di halaman PA Tim sebelum diterbitkan ke karyawan. Rilis bisa dicabut selama belum terbit.</p>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="memprosesSatu !== null" @click="dialogRilisSatu = false" />
                <HcisButton
                    label="Ya, rilis"
                    variant="primary"
                    size="sm"
                    leading-icon="send"
                    :loading="memprosesSatu !== null"
                    data-uji="ya-rilis-satu"
                    @click="jalankanRilisSatu"
                />
            </template>
        </HcisDialog>

        <!-- DIALOG 3: Cabut Rilis Leader (Single Participant) -->
        <HcisDialog
            v-model="dialogCabut"
            title="Tarik kembali akses leader?"
            size="sm"
            sentiment="warning"
            icon="person-x"
        >
            <div class="pa-ttb__konfirmasi">
                <p>
                    Akses leader atas hasil <strong>{{ pesertaCabut?.Nama_Karyawan_Saat_Cycle }}</strong> akan ditarik.
                </p>
                <div class="pa-ttb__form-group">
                    <label class="pa-ttb__form-label">
                        Alasan pencabutan <span class="text-rose-600">*</span> (min. 5 karakter)
                    </label>
                    <textarea
                        v-model="alasanCabut"
                        rows="3"
                        class="pa-ttb__form-textarea"
                        placeholder="Contoh: Perlu verifikasi ulang nilai kompetensi bersama HR..."
                    />
                </div>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="mencabut" @click="dialogCabut = false" />
                <HcisButton
                    label="Ya, cabut rilis"
                    variant="warning"
                    size="sm"
                    leading-icon="lock"
                    :disabled="!alasanCabut.trim() || alasanCabut.trim().length < 5"
                    :loading="mencabut"
                    @click="jalankanCabutLeader"
                />
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { labelLevelPa } from '../../../utils/levelPa.js';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisTable,
    hcisMessage,
} from '@/components/ui';
import paApi from '../../../api/paApi.js';
import { adaYangDilewati, pesanKpiBelumLengkap, rilisLeaderPesertaBerkonfirmasi, terbitkanPesertaBerkonfirmasi } from '../../composables/usePaPublication.js';

/* Tahap Rilis & Terbit: Urutan global (Rilis Leader, Terbit Karyawan, Tutup Siklus),
   serta daftar selektif peserta dengan fitur centang (checklist) untuk memilih peserta mana
   yang siap dirilis ke leader atau diterbitkan ke karyawan. */
const props = defineProps({
    cycleId: { type: Number, required: true },
    cacah: { type: Object, default: null },
    bolehRilis: { type: Boolean, default: false },
    bolehTerbit: { type: Boolean, default: false },
});
const emit = defineEmits(['berubah', 'buka-penerbitan']);

/* Syarat rilis/terbit dari server (kurva terkunci, Balancing tuntas, status siklus) — sama
   dengan pemeriksaan aksinya. `bolehRilis`/`bolehTerbit` dari induk hanya hak akses. */
const syarat = ref({ boleh_rilis: true, boleh_terbit: true, alasan: null });
const rilisAktif = computed(() => props.bolehRilis && syarat.value.boleh_rilis);
const terbitAktif = computed(() => props.bolehTerbit && syarat.value.boleh_terbit);
const pengingatKualitatif = ref({ belum: 0, contoh: [] });
const dialogTerbitSatu = ref(false);
const pesertaTerbit = ref(null);

async function muatSyarat() {
    if (!props.cycleId) return;
    try {
        const r = await paApi.ringkasanPenerbitan(props.cycleId);
        const d = r?.result ?? r;
        if (d?.syarat) syarat.value = d.syarat;
    } catch {
        // Tanpa syarat, tombol mengikuti hak akses saja; server tetap menolak bila belum boleh.
    }
}

async function periksaKualitatif(ids) {
    pengingatKualitatif.value = { belum: 0, contoh: [] };
    try {
        const r = await paApi.periksaKesimpulanSebelumTerbit(props.cycleId, ids);
        const d = r?.result ?? r;
        pengingatKualitatif.value = { belum: Number(d?.belum || 0), contoh: d?.contoh || [] };
    } catch {
        // Pengingat lunak: gagal memuat tidak menahan penerbitan.
    }
}

function bukaTerbitSatu(p) {
    pesertaTerbit.value = p;
    dialogTerbitSatu.value = true;
    periksaKualitatif([p.Id_PA_Participant]);
}

// State Dialogs
const dialogRilis = ref(false);
const dialogTerbitTerpilih = ref(false);
const dialogCabut = ref(false);
const modeAksi = ref('siklus'); // 'siklus' | 'terpilih'

// State Action Loading
const merilis = ref(false);
const menerbitkanBatch = ref(false);
const mencabut = ref(false);
const memprosesSatu = ref(null);
const memprosesBatch = computed(() => merilis.value || menerbitkanBatch.value);
const aksiBatch = ref('');

// State Data Peserta
const memuat = ref(false);
const galat = ref('');
const peserta = ref([]);
const totalPeserta = ref(0);
const halaman = ref(1);
const perHalaman = ref(25);
const terpilih = ref([]);
const mengambilSemua = ref(false);
let urutanMuat = 0;
let urutanPilihSemua = 0;
const opsi = ref({ divisi: [], level: [] });

/* Seluruh saringan dikirim ke server. Menyaring di layar sesudah halaman dipotong
   membuat orang yang dicari tidak pernah muncul bila ia ada di halaman lain. */
const saring = reactive({ q: '', status: '', rilis_leader: '', id_divisi: '', id_level: '' });
const STATUS_HASIL = { SIAP: 'DIHITUNG', TERBIT: 'TERBIT', TERTAHAN: 'TERTAHAN,BELUM_DIHITUNG' };

// State Cabut Rilis Leader
const pesertaCabut = ref(null);
const alasanCabut = ref('');

// Formatters
const angka = (v, d = 0) => (v === null || v === undefined ? '0' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }));
const labelGrade = (k) => (k ? String(k).replace(/_PLUS/gi, '+').replace(/_MINUS/gi, '-') : '–');
const total = computed(() => Number(props.cacah?.hasil_total || 0));
const siap = computed(() => Number(props.cacah?.hasil_dihitung || 0));
const belumSiap = computed(() => Number(props.cacah?.hasil_tertahan || 0) + Number(props.cacah?.hasil_belum || 0));
const bisaDirilis = computed(() => Number(props.cacah?.dirilis_leader || 0) < siap.value + Number(props.cacah?.hasil_terbit || 0));
const semuaTerbit = computed(() => total.value > 0 && Number(props.cacah?.hasil_terbit || 0) === total.value);
const persen = (v) => (total.value ? `${Math.round((Number(v || 0) / total.value) * 100)}%` : '0%');

function formatTgl(isoStr) {
    if (!isoStr) return '';
    try {
        const d = new Date(isoStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return isoStr;
    }
}

function getGradeTierClass(code) {
    if (!code) return 'is-grade-c';
    const c = String(code).toUpperCase().trim();
    if (c.startsWith('A')) return 'is-grade-a';
    if (c.startsWith('B')) return 'is-grade-b';
    if (c.startsWith('C')) return 'is-grade-c';
    if (c.startsWith('D')) return 'is-grade-d';
    if (c.startsWith('E')) return 'is-grade-e';
    return 'is-grade-c';
}

function bisaDipilih(p) {
    if (!p) return false;
    // Bisa dipilih bila berstatus DIHITUNG (siap rilis/terbit), atau TERBIT tetapi belum dirilis ke leader
    if (p.Status_Hasil === 'DIHITUNG') return true;
    if (p.Status_Hasil === 'TERBIT' && !p.Published_Leader_At) return true;
    return false;
}

const gradeBaris = (p) => p?.Kode_Grade_Final || p?.Kode_Grade_Sistem || p?.Grade_Sementara || '';

const kolom = [
    { key: 'no', label: 'No', width: '56px', align: 'center' },
    { key: 'karyawan', label: 'Karyawan', width: '220px' },
    { key: 'posisi', label: 'Unit / Level', width: '180px' },
    { key: 'nilai', label: 'Nilai & Grade', width: '130px', align: 'right' },
    { key: 'leader', label: 'Akses Leader', width: '150px' },
    { key: 'karyawan_status', label: 'Status Karyawan', width: '170px' },
    { key: 'aksi', label: 'Aksi', width: '170px', align: 'center' },
];

const chipStatus = computed(() => [
    { value: '', label: 'Semua', icon: 'people', color: 'brand', badge: angka(total.value) },
    { value: 'SIAP', label: 'Siap terbit', icon: 'clock', color: 'brand', badge: angka(siap.value) },
    { value: 'TERBIT', label: 'Sudah terbit', icon: 'check-circle', color: 'success', badge: angka(props.cacah?.hasil_terbit) },
    { value: 'TERTAHAN', label: 'Tertahan / belum siap', icon: 'exclamation-triangle', color: 'warning', badge: angka(belumSiap.value) },
]);

const definisiSaringan = computed(() => [
    {
        key: 'rilis_leader', label: 'Akses leader', type: 'select', icon: 'person-badge', placeholder: 'Semua', clearable: true,
        options: [{ value: 'Y', label: 'Sudah dirilis ke leader' }, { value: 'T', label: 'Belum dirilis ke leader' }],
        col: 4,
    },
    { key: 'id_divisi', label: 'Divisi', type: 'select', icon: 'building', placeholder: 'Semua divisi', clearable: true, options: opsi.value.divisi, col: 4 },
    { key: 'id_level', label: 'Level', type: 'select', icon: 'layers', placeholder: 'Semua level', clearable: true, options: opsi.value.level, col: 4 },
]);

const nilaiSaringan = computed(() => ({ rilis_leader: saring.rilis_leader, id_divisi: saring.id_divisi, id_level: saring.id_level }));

function parameterSaringan() {
    return {
        status_peserta: 'DIIKUTKAN',
        status_hasil: STATUS_HASIL[saring.status] || undefined,
        rilis_leader: saring.rilis_leader || undefined,
        id_divisi: saring.id_divisi || undefined,
        id_level: saring.id_level || undefined,
        q: saring.q.trim() || undefined,
    };
}

/* Saringan berubah = kumpulan peserta berubah; pilihan lama dibuang supaya tidak ada
   orang yang ikut terbit padahal sudah tidak terlihat di layar. */
function terapkanSaringan() {
    urutanPilihSemua++; // "pilih semua" yang masih memuat milik saringan lama — dibatalkan
    terpilih.value = [];
    halaman.value = 1;
    muatPeserta();
}

function ubahCari(v) { saring.q = v || ''; terapkanSaringan(); }
function ubahStatus(chip) { saring.status = chip?.value || ''; terapkanSaringan(); }
function ubahSaringan({ key, value }) { saring[key] = value ?? ''; terapkanSaringan(); }
function resetSaringan() {
    Object.assign(saring, { q: '', status: '', rilis_leader: '', id_divisi: '', id_level: '' });
    terapkanSaringan();
}

/* Centang di kepala tabel = SELURUH hasil saringan di semua halaman. Id-nya diminta ke
   server (yang memakai saringan dan aturan "bisa dipilih" yang sama), bukan dirakit dari
   halaman yang kebetulan terbuka. */
async function pilihSemua({ checked }) {
    if (!checked) {
        terpilih.value = [];
        return;
    }
    mengambilSemua.value = true;
    const nomor = ++urutanPilihSemua;
    try {
        const res = await paApi.daftarPeserta(props.cycleId, { ...parameterSaringan(), hanya_id: 1 });
        /* Saringan berubah selagi id dimuat: id itu milik kumpulan lama dan bisa ikut
           dirilis/diterbitkan tanpa terlihat di layar — buang. */
        if (nomor !== urutanPilihSemua) return;
        const data = res?.result ?? res;
        terpilih.value = (data?.ids || []).map(Number);
        if (terpilih.value.length > peserta.value.length) {
            hcisMessage.info(`${terpilih.value.length} peserta dipilih dari seluruh halaman sesuai saringan.`, 'Pilih semua');
        }
    } catch (e) {
        if (nomor !== urutanPilihSemua) return;
        hcisMessage.error(e?.message || 'Gagal mengambil seluruh peserta.', 'Pilih semua gagal');
    } finally {
        mengambilSemua.value = false;
    }
}

/* HcisPagination memancarkan update:current-page dan current-change untuk satu klik, dan
   current-change(1) lagi sesudah ukuran halaman diganti. Halaman yang tidak berubah
   tidak dimuat ulang. */
function ubahHalaman(p) {
    if (!p || p === halaman.value) return;
    halaman.value = p;
    muatPeserta();
}

function gantiUkuran(ukuran) {
    perHalaman.value = ukuran;
    halaman.value = 1;
    muatPeserta();
}

async function muatPeserta() {
    if (!props.cycleId) return;
    memuat.value = true;
    galat.value = '';
    const nomor = ++urutanMuat;
    try {
        const res = await paApi.daftarPeserta(props.cycleId, {
            ...parameterSaringan(),
            halaman: halaman.value,
            per_halaman: perHalaman.value,
            dengan_opsi: opsi.value.divisi.length ? undefined : 1,
        });
        // Hanya respons TERBARU yang ditulis; saringan/halaman lama yang tiba belakangan dibuang.
        if (nomor !== urutanMuat) return;
        const data = res?.result ?? res;
        peserta.value = data?.peserta || [];
        totalPeserta.value = Number(data?.total ?? peserta.value.length);
        if (data?.opsi) opsi.value = data.opsi;
    } catch (e) {
        if (nomor !== urutanMuat) return;
        galat.value = e?.message || 'Gagal memuat daftar peserta.';
    } finally {
        if (nomor === urutanMuat) memuat.value = false;
    }
}

// Buka dialog rilis siklus (seluruh hasil siap)
function bukaDialogRilisSiklus() {
    modeAksi.value = 'siklus';
    dialogRilis.value = true;
}

// Buka dialog rilis terpilih
function bukaRilisTerpilih() {
    modeAksi.value = 'terpilih';
    dialogRilis.value = true;
}

// Eksekusi Rilis ke Leader
async function rilis() {
    merilis.value = true;
    aksiBatch.value = 'rilis';
    try {
        if (modeAksi.value === 'siklus') {
            const r = await paApi.rilisLeader(props.cycleId);
            (adaYangDilewati(r) ? hcisMessage.warning : hcisMessage.success)(
                `${r?.dirilis ?? 0} hasil dirilis ke leader (${r?.sudah_dirilis ?? 0} sudah dirilis sebelumnya, ${r?.belum_layak ?? 0} belum layak).${pesanKpiBelumLengkap(r)}`,
                'Rilis ke leader selesai',
            );
        } else {
            const r = await paApi.rilisLeader(props.cycleId, { participant_ids: terpilih.value });
            (adaYangDilewati(r) ? hcisMessage.warning : hcisMessage.success)(
                `${r?.dirilis ?? terpilih.value.length} hasil peserta terpilih berhasil dirilis ke leader.${pesanKpiBelumLengkap(r)}`,
                'Rilis leader terpilih',
            );
            terpilih.value = [];
        }
        dialogRilis.value = false;
        emit('berubah');
        await muatPeserta();
    } catch (e) {
        hcisMessage.error(e?.message || 'Rilis ke leader ditolak.', 'Rilis gagal');
    } finally {
        merilis.value = false;
        aksiBatch.value = '';
    }
}

// Buka dialog terbitkan terpilih
function bukaTerbitTerpilih() {
    dialogTerbitTerpilih.value = true;
    periksaKualitatif(terpilih.value);
}

// Eksekusi Terbitkan Peserta Terpilih ke Karyawan
async function jalankanTerbitTerpilih() {
    menerbitkanBatch.value = true;
    aksiBatch.value = 'terbit';
    try {
        const r = await paApi.terbitkan(props.cycleId, { participant_ids: terpilih.value });
        // Ada yang dilewati karena KPI belum lengkap: peringatan, bukan ucapan sukses biasa.
        (adaYangDilewati(r) ? hcisMessage.warning : hcisMessage.success)(
            `${r?.diterbitkan ?? terpilih.value.length} hasil peserta terpilih berhasil diterbitkan ke karyawan.${pesanKpiBelumLengkap(r)}`,
            'Penerbitan selesai',
        );
        terpilih.value = [];
        dialogTerbitTerpilih.value = false;
        emit('berubah');
        await muatPeserta();
    } catch (e) {
        hcisMessage.error(e?.message || 'Penerbitan peserta terpilih gagal.', 'Terbit gagal');
    } finally {
        menerbitkanBatch.value = false;
        aksiBatch.value = '';
    }
}

const dialogRilisSatu = ref(false);
const pesertaRilis = ref(null);

function bukaRilisSatu(p) {
    pesertaRilis.value = p;
    dialogRilisSatu.value = true;
}

async function jalankanRilisSatu() {
    if (!pesertaRilis.value) return;
    await rilisSatu(pesertaRilis.value);
    dialogRilisSatu.value = false;
}

// Aksi Satuan: Rilis Satu Peserta ke Leader
async function rilisSatu(p) {
    memprosesSatu.value = p.Id_PA_Participant;
    try {
        if ((await rilisLeaderPesertaBerkonfirmasi(paApi, p.Id_PA_Participant))?.dibatalkan) return;
        hcisMessage.success(`Hasil ${p.Nama_Karyawan_Saat_Cycle} dirilis ke leader.`, 'Rilis leader');
        emit('berubah');
        await muatPeserta();
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal merilis ke leader.', 'Rilis gagal');
    } finally {
        memprosesSatu.value = null;
    }
}

// Aksi Satuan: Buka Dialog Cabut Rilis Leader
function bukaCabutLeader(p) {
    pesertaCabut.value = p;
    alasanCabut.value = '';
    dialogCabut.value = true;
}

// Aksi Satuan: Eksekusi Cabut Rilis Leader
async function jalankanCabutLeader() {
    if (!pesertaCabut.value || !alasanCabut.value.trim()) return;
    mencabut.value = true;
    try {
        await paApi.cabutRilisLeader(pesertaCabut.value.Id_PA_Participant, alasanCabut.value.trim());
        hcisMessage.success(`Akses leader atas hasil ${pesertaCabut.value.Nama_Karyawan_Saat_Cycle} telah dicabut.`, 'Rilis dicabut');
        dialogCabut.value = false;
        emit('berubah');
        await muatPeserta();
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal mencabut rilis leader.', 'Pencabutan gagal');
    } finally {
        mencabut.value = false;
    }
}

// Aksi Satuan: Terbitkan Satu Peserta ke Karyawan
async function terbitkanSatu(p) {
    memprosesSatu.value = p.Id_PA_Participant;
    try {
        if ((await terbitkanPesertaBerkonfirmasi(paApi, p.Id_PA_Participant))?.dibatalkan) return;
        dialogTerbitSatu.value = false;
        hcisMessage.success(`Hasil ${p.Nama_Karyawan_Saat_Cycle} resmi diterbitkan ke karyawan.`, 'Hasil terbit');
        emit('berubah');
        await muatPeserta();
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal menerbitkan hasil peserta.', 'Terbit gagal');
    } finally {
        memprosesSatu.value = null;
    }
}

onMounted(() => {
    muatPeserta();
    muatSyarat();
});

watch(() => props.cycleId, () => {
    muatSyarat();
    halaman.value = 1;
    urutanPilihSemua++;
    terpilih.value = [];
    opsi.value = { divisi: [], level: [] };
    muatPeserta();
});
</script>

<style scoped>
.pa-ttb {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-ttb-galat {
    color: #dc2626;
    font-size: 0.85rem;
    margin: 0;
    padding: 0.75rem 1rem;
    background: #fef2f2;
    border-radius: 8px;
    border: 1px solid #fecaca;
}

/* 1. Summary Cards */
.pa-ttb__grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.6rem;
}

.pa-ttb__kartu {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #6366f1;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.pa-ttb__kartu span {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.pa-ttb__kartu strong {
    font-size: 1.3rem;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-ttb__kartu strong small,
.pa-ttb__kartu > small {
    font-size: 0.72rem;
    font-weight: 500;
    color: #94a3b8;
}

.pa-ttb__kartu.is-ungu { border-left-color: #a855f7; }
.pa-ttb__kartu.is-hijau { border-left-color: #22c55e; }
.pa-ttb__kartu.is-warning { border-left-color: #f59e0b; background: #fffbeb; }

.pa-ttb__bar {
    height: 6px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
    margin-top: 0.15rem;
}

.pa-ttb__bar i {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: currentColor;
    color: #6366f1;
    transition: width 0.3s ease;
}

.is-ungu .pa-ttb__bar i { color: #a855f7; }
.is-hijau .pa-ttb__bar i { color: #22c55e; }

/* 2. Process Order List */
.pa-ttb__urutan {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.pa-ttb__urutan li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.95rem;
    border-bottom: 1px solid #f1f5f9;
}

.pa-ttb__urutan li:last-child {
    border-bottom: 0;
}

.pa-ttb__urutan strong {
    font-size: 0.85rem;
    color: #0f172a;
}

.pa-ttb__urutan p {
    margin: 0.15rem 0 0;
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.45;
}

/* 3. Interactive Section & Checklist Table */
.pa-ttb__section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-ttb__section-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.pa-ttb__section-titles {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-ttb__section-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-ttb__section-sub {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
}

.pa-ttb__toolbar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-ttb__search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.pa-ttb__search-icon {
    position: absolute;
    left: 0.65rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-ttb__search-input {
    padding: 0.35rem 1.8rem 0.35rem 2rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.76rem;
    color: #0f172a;
    background: #ffffff;
    min-width: 220px;
    transition: all 150ms ease;
}

.pa-ttb__search-input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.pa-ttb__search-clear {
    position: absolute;
    right: 0.45rem;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem;
}

.pa-ttb__search-clear:hover {
    color: #475569;
}

.pa-ttb__selection-hint {
    font-size: 0.74rem;
    font-weight: 600;
    color: #3730a3;
}

.pa-ttb__karyawan-cell {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-ttb__karyawan-nama {
    font-size: 0.78rem;
    color: #0f172a;
    font-weight: 700;
}

.pa-ttb__karyawan-nip {
    font-size: 0.68rem;
    color: #94a3b8;
    font-family: ui-monospace, monospace;
}

.pa-ttb__posisi-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-ttb__posisi-unit {
    font-size: 0.72rem;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

.pa-ttb__level-chip {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.65rem;
    font-weight: 600;
    width: fit-content;
}

.pa-ttb__nilai-cell {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.45rem;
}

.pa-ttb__skor {
    font-size: 0.8rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-ttb__status-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    align-items: flex-start;
}

.pa-ttb__tgl-kecil {
    font-size: 0.65rem;
    color: #94a3b8;
}

.pa-ttb__alasan-tertahan {
    font-size: 0.65rem;
    color: #b45309;
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Row Quick Actions */
.pa-ttb__row-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.pa-ttb__mini-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 150ms ease;
    border: 1px solid transparent;
}

.pa-ttb__mini-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pa-ttb__mini-btn.is-leader {
    background: #f3e8ff;
    color: #7e22ce;
    border-color: #e9d5ff;
}
.pa-ttb__mini-btn.is-leader:hover:not(:disabled) {
    background: #e9d5ff;
}

.pa-ttb__mini-btn.is-cabut {
    background: #fff1f2;
    color: #e11d48;
    border-color: #fecdd3;
}
.pa-ttb__mini-btn.is-cabut:hover:not(:disabled) {
    background: #ffe4e6;
}

.pa-ttb__mini-btn.is-terbit {
    background: #dcfce7;
    color: #15803d;
    border-color: #bbf7d0;
}
.pa-ttb__mini-btn.is-terbit:hover:not(:disabled) {
    background: #bbf7d0;
}

.pa-ttb__footer-page {
    display: flex;
    justify-content: flex-end;
    padding-top: 0.25rem;
}

/* Grade Pills */
.pa-grade-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.25rem;
    padding: 0 0.3rem;
    border-radius: 4px;
    font-weight: 800;
    font-size: 0.70rem;
    font-variant-numeric: tabular-nums;
}

.pa-grade-pill.is-grade-a { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
.pa-grade-pill.is-grade-b { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.pa-grade-pill.is-grade-c { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.pa-grade-pill.is-grade-d { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.pa-grade-pill.is-grade-e { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

/* Dialog Confirmation */
.pa-ttb__konfirmasi {
    margin: 0;
    font-size: 0.85rem;
    color: #334155;
    line-height: 1.5;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-ttb__form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.pa-ttb__form-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
}

.pa-ttb__form-textarea {
    width: 100%;
    padding: 0.5rem 0.65rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.76rem;
    color: #0f172a;
    font-family: inherit;
    resize: vertical;
}

.pa-ttb__form-textarea:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 200ms ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (max-width: 900px) {
    .pa-ttb__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pa-ttb__urutan li { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 640px) {
    .pa-ttb {
        gap: 0.75rem;
    }

    /* 1. Stat cards 2-col compact grid */
    .pa-ttb__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }
    .pa-ttb__kartu {
        padding: 0.55rem 0.65rem;
        border-radius: 10px;
        gap: 0.15rem;
    }
    .pa-ttb__kartu span {
        font-size: 0.64rem;
    }
    .pa-ttb__kartu strong {
        font-size: 1.15rem;
    }
    .pa-ttb__kartu strong small,
    .pa-ttb__kartu > small {
        font-size: 0.65rem;
    }

    /* 2. Process Order List */
    .pa-ttb__urutan {
        border-radius: 10px;
    }
    .pa-ttb__urutan li {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        padding: 0.65rem 0.75rem;
    }
    .pa-ttb__urutan strong {
        font-size: 0.82rem;
    }
    .pa-ttb__urutan p {
        font-size: 0.7rem;
        line-height: 1.35;
    }
    .pa-ttb__urutan li :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 36px;
    }

    /* 3. Section & Filter Table */
    .pa-ttb__section {
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
        gap: 0.6rem;
    }
    .pa-ttb__section-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
    }
    .pa-ttb__section-title {
        font-size: 0.82rem;
    }
    .pa-ttb__section-sub {
        display: none;
    }
    .pa-ttb__selection-hint {
        text-align: center;
        font-size: 0.72rem;
        display: block;
        margin-bottom: 0.2rem;
    }
    :deep(.hcis-table__bulk-actions) {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.4rem !important;
    }
    :deep(.hcis-table__bulk-actions .hcis-btn) {
        width: 100% !important;
        justify-content: center !important;
    }
    .pa-ttb__footer-page {
        justify-content: center;
        overflow-x: auto;
    }
}

@media (max-width: 420px) {
    .pa-ttb__grid {
        grid-template-columns: 1fr;
    }
}
.pa-ttb__syarat { display: flex; align-items: center; gap: 0.4rem; margin: 0; padding: 0.55rem 0.8rem; border: 1px solid #fde68a; border-radius: 10px; background: #fffbeb; font-size: 0.8rem; color: #92400e; }
.pa-ttb__pengingat { display: flex; align-items: flex-start; gap: 0.35rem; margin: 0.5rem 0 0; padding: 0.45rem 0.65rem; border-radius: 8px; background: #faf5ff; font-size: 0.78rem; color: #6b21a8; }
</style>
