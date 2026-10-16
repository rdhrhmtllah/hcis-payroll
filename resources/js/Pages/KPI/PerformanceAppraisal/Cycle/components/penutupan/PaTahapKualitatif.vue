<template>
    <div class="pa-tkq">
        <div class="pa-tkq__grid">
            <div class="pa-tkq__kartu">
                <span>Peserta berjawaban kualitatif</span>
                <strong>{{ angka(ringkas.total) }}</strong>
                <small>punya jawaban esai dari penilai</small>
            </div>
            <div class="pa-tkq__kartu is-hijau">
                <span>Sudah disimpulkan</span>
                <strong>{{ angka(ringkas.sudah) }} <small>/ {{ angka(ringkas.total) }}</small></strong>
                <div class="pa-tkq__bar"><i :style="{ width: persen }" /></div>
            </div>
            <div class="pa-tkq__kartu" :class="{ 'is-amber': ringkas.belum > 0 }">
                <span>Belum disimpulkan</span>
                <strong>{{ angka(ringkas.belum) }}</strong>
                <small>{{ ringkas.belum > 0 ? 'kerjakan sebelum Balancing' : 'semua sudah ditulis' }}</small>
                <small v-if="ringkas.terkunci > 0" data-uji="terkunci-tanpa-kesimpulan">+ {{ angka(ringkas.terkunci) }} sudah terbit tanpa kesimpulan (tak bisa diubah)</small>
            </div>
        </div>

        <div class="pa-tkq__info" data-uji="info-kualitatif">
            <HcisIcon name="info-circle-fill" size="2xs" />
            <span>
                Tahap ini <b>tidak mengunci</b> penerbitan. Kesimpulan <b>Strength</b> dan <b>Development Area</b> dipakai sebagai isi awal
                di board Balancing, jadi sebaiknya ditulis sebelum sesi Balancing dibuat.
            </span>
        </div>

        <section class="pa-tkq__section">
            <HcisFilterBar
                :search="saring.q"
                search-placeholder="Cari nama atau NIK..."
                :search-debounce="300"
                :quick-chips="chipStatus"
                :active-chip-value="saring.status"
                :filters="definisiSaringan"
                :filter-values="nilaiSaringan"
                variant="card"
                sentiment="purple"
                size="sm"
                :results-count="memuat ? null : total"
                show-active-tags
                data-uji="saringan-kualitatif"
                @update:search="ubahCari"
                @chip-click="ubahStatus"
                @filter-change="ubahSaringan"
                @reset="resetSaringan"
            />

            <p v-if="galat" class="pa-tkq__galat">{{ galat }}</p>

            <HcisTable
                v-else
                :columns="kolom"
                :rows="peserta"
                row-key="participant_id"
                :loading="memuat"
                :loading-rows-count="6"
                responsive="scroll"
                variant="card"
                sentiment="purple"
                hoverable
                sticky-header
                empty-title="Tidak ada peserta yang cocok"
                :empty-description="ringkas.total === 0 ? 'Belum ada jawaban kualitatif dari penilai di siklus ini.' : 'Ubah kata kunci atau saringan di atas.'"
                empty-icon="chat-quote"
                data-uji="tabel-kualitatif"
            >
                <template #cell-no="{ index }">
                    <span class="pa-tkq__redup">{{ (halaman - 1) * perHalaman + index + 1 }}</span>
                </template>
                <template #cell-karyawan="{ row }">
                    <div class="pa-tkq__orang">
                        <strong>{{ row.nama }}</strong>
                        <small>{{ row.kode_karyawan }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></small>
                    </div>
                </template>
                <template #cell-posisi="{ row }">
                    <div class="pa-tkq__orang">
                        <span>{{ row.divisi || '–' }}</span>
                        <small v-if="row.level">{{ row.level }}</small>
                    </div>
                </template>
                <template #cell-jawaban="{ row }">
                    <span class="pa-tkq__jawaban">{{ row.jumlah_jawaban }} jawaban · {{ row.jumlah_penilai }} penilai</span>
                </template>
                <template #cell-kesimpulan="{ row }">
                    <div class="pa-tkq__status">
                        <HcisBadge
                            v-if="row.status === 'SUDAH'"
                            :label="`Sudah · ${row.kategori_tersimpul}/${row.kategori_total} kategori`"
                            :variant="row.kategori_tersimpul < row.kategori_total ? 'warning' : 'success'"
                            appearance="soft"
                            size="xs"
                            icon="check-circle-fill"
                        />
                        <HcisBadge v-else label="Belum disimpulkan" variant="neutral" appearance="subtle" size="xs" />
                        <!-- Kesimpulan boleh ditulis sambil KPI-nya ditagih; ia selamat saat hasil dihitung ulang. -->
                        <HcisBadge
                            v-if="row.status_hasil === 'TERTAHAN'"
                            label="Tertahan · KPI ditagih paralel"
                            variant="danger"
                            appearance="subtle"
                            size="xs"
                            icon="hourglass-split"
                            data-uji="kualitatif-tertahan"
                        />
                        <small v-if="row.terakhir">{{ row.terakhir.oleh }} · {{ tanggal(row.terakhir.pada) }}</small>
                        <small v-if="row.ikut_balancing" class="is-ungu">Masuk sesi Balancing</small>
                    </div>
                </template>
                <template #cell-aksi="{ row, index }">
                    <HcisButton
                        :label="row.terkunci ? 'Lihat' : row.status === 'SUDAH' ? 'Ubah' : 'Tulis'"
                        :variant="row.status === 'SUDAH' || row.terkunci ? 'secondary' : 'primary'"
                        size="sm"
                        :leading-icon="row.terkunci ? 'eye' : 'pencil-square'"
                        :data-uji="`tulis-${row.participant_id}`"
                        @click="buka(index)"
                    />
                </template>
            </HcisTable>

            <div v-if="total > 0" class="pa-tkq__kaki">
                <HcisPagination
                    :current-page="halaman"
                    v-model:page-size="perHalaman"
                    :total="total"
                    :page-sizes="[10, 25, 50, 100]"
                    size="sm"
                    @current-change="ubahHalaman"
                    @size-change="gantiUkuran"
                />
            </div>
        </section>

        <PaRuangKerjaKualitatif
            v-model="ruangKerja.buka"
            :participant-id="pesertaTerbuka?.participant_id ?? null"
            :posisi="posisiTerbuka"
            :bisa-sebelum="indeksGlobal > 0"
            :bisa-berikut="indeksGlobal < total - 1"
            @tersimpan="sesudahSimpan"
            @navigasi="navigasi"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { HcisBadge, HcisButton, HcisFilterBar, HcisIcon, HcisPagination, HcisTable } from '@/components/ui';
import paApi from '../../../api/paApi.js';
import PaRuangKerjaKualitatif from './PaRuangKerjaKualitatif.vue';

/* Tahap Kesimpulan Kualitatif: peserta yang punya jawaban esai dari penilai, status
   kesimpulannya, dan ruang kerja untuk menulisnya satu per satu tanpa membuka dialog hasil. */
const props = defineProps({
    cycleId: { type: Number, required: true },
});
const emit = defineEmits(['berubah']);

const memuat = ref(false);
const galat = ref('');
const peserta = ref([]);
const total = ref(0);
const halaman = ref(1);
const perHalaman = ref(25);
const ringkas = ref({ total: 0, sudah: 0, belum: 0 });
const opsi = ref({ divisi: [], level: [] });
const saring = reactive({ q: '', status: '', id_divisi: '', id_level: '' });
const ruangKerja = reactive({ buka: false, indeks: 0 });
let urutanMuat = 0;
/* Navigasi "berikutnya" yang melewati batas halaman memuat halaman lain lebih dulu; indeks
   yang dituju disimpan di sini sampai halamannya tiba. */
let tujuanSesudahMuat = null;

const angka = (v) => Number(v || 0).toLocaleString('id-ID');
const persen = computed(() => (ringkas.value.total ? `${Math.round((ringkas.value.sudah / ringkas.value.total) * 100)}%` : '0%'));
const tanggal = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '');

const kolom = [
    { key: 'no', label: 'No', width: '56px', align: 'center' },
    { key: 'karyawan', label: 'Karyawan', minWidth: '220px' },
    { key: 'posisi', label: 'Unit / Level', minWidth: '170px' },
    { key: 'jawaban', label: 'Jawaban Penilai', width: '170px' },
    { key: 'kesimpulan', label: 'Kesimpulan', minWidth: '200px' },
    { key: 'aksi', label: 'Aksi', width: '110px', align: 'center' },
];

const chipStatus = computed(() => [
    { value: '', label: 'Semua', icon: 'people', color: 'brand', badge: angka(ringkas.value.total) },
    { value: 'BELUM', label: 'Belum', icon: 'hourglass-split', color: 'warning', badge: angka(ringkas.value.belum) },
    { value: 'SUDAH', label: 'Sudah', icon: 'check-circle', color: 'success', badge: angka(ringkas.value.sudah) },
]);

const definisiSaringan = computed(() => [
    { key: 'id_divisi', label: 'Divisi', type: 'select', icon: 'building', placeholder: 'Semua divisi', clearable: true, options: opsi.value.divisi, col: 6 },
    { key: 'id_level', label: 'Level', type: 'select', icon: 'layers', placeholder: 'Semua level', clearable: true, options: opsi.value.level, col: 6 },
]);
const nilaiSaringan = computed(() => ({ id_divisi: saring.id_divisi, id_level: saring.id_level }));

const indeksGlobal = computed(() => (halaman.value - 1) * perHalaman.value + ruangKerja.indeks);
const pesertaTerbuka = computed(() => peserta.value[ruangKerja.indeks] || null);
const posisiTerbuka = computed(() => (total.value ? `${indeksGlobal.value + 1} dari ${total.value}` : ''));

function parameter() {
    return {
        status: saring.status || undefined,
        id_divisi: saring.id_divisi || undefined,
        id_level: saring.id_level || undefined,
        q: saring.q.trim() || undefined,
    };
}

async function muat() {
    if (!props.cycleId) return;
    const nomor = ++urutanMuat;
    memuat.value = true;
    galat.value = '';
    try {
        const res = await paApi.daftarKesimpulanKualitatif(props.cycleId, {
            ...parameter(),
            halaman: halaman.value,
            per_halaman: perHalaman.value,
            dengan_opsi: opsi.value.divisi.length ? undefined : 1,
        });
        // Hanya respons TERBARU yang ditulis: saringan/halaman lama yang tiba belakangan dibuang.
        if (nomor !== urutanMuat) return;
        const data = res?.result ?? res;
        peserta.value = data?.peserta || [];
        total.value = Number(data?.total ?? peserta.value.length);
        if (data?.ringkas) ringkas.value = data.ringkas;
        if (data?.opsi) opsi.value = data.opsi;
        if (tujuanSesudahMuat !== null) {
            ruangKerja.indeks = Math.min(tujuanSesudahMuat, Math.max(0, peserta.value.length - 1));
            tujuanSesudahMuat = null;
        }
    } catch (e) {
        if (nomor !== urutanMuat) return;
        galat.value = e?.message || 'Gagal memuat daftar peserta.';
    } finally {
        if (nomor === urutanMuat) memuat.value = false;
    }
}

function terapkan() {
    halaman.value = 1;
    muat();
}
function ubahCari(v) { saring.q = v || ''; terapkan(); }
function ubahStatus(chip) { saring.status = chip?.value || ''; terapkan(); }
function ubahSaringan({ key, value }) { saring[key] = value ?? ''; terapkan(); }
function resetSaringan() {
    Object.assign(saring, { q: '', status: '', id_divisi: '', id_level: '' });
    terapkan();
}

/* HcisPagination memancarkan beberapa event untuk satu klik; halaman yang tidak berubah
   tidak dimuat ulang. */
function ubahHalaman(p) {
    if (!p || p === halaman.value) return;
    halaman.value = p;
    muat();
}
function gantiUkuran(u) {
    perHalaman.value = u;
    halaman.value = 1;
    muat();
}

function buka(indeks) {
    ruangKerja.indeks = indeks;
    ruangKerja.buka = true;
}

/* Saringan "Belum" membuat orang yang baru disimpulkan hilang dari daftar; karena itu
   daftar dimuat ulang di halaman yang sama dan indeks TIDAK maju — orang berikutnya
   bergeser ke posisi yang sama. */
let lanjutSesudahSimpan = false;

function sesudahSimpan() {
    emit('berubah');
    if (saring.status === 'BELUM') {
        lanjutSesudahSimpan = true;
        tujuanSesudahMuat = ruangKerja.indeks;
    }
    muat();
}

function navigasi(arah) {
    if (arah === 'berikut' && lanjutSesudahSimpan) {
        // Daftar "Belum" sedang dimuat ulang; orang berikutnya akan menempati indeks yang sama.
        lanjutSesudahSimpan = false;
        return;
    }
    lanjutSesudahSimpan = false;
    const geser = arah === 'berikut' ? 1 : -1;
    const tujuan = ruangKerja.indeks + geser;

    if (tujuan >= 0 && tujuan < peserta.value.length) {
        ruangKerja.indeks = tujuan;
        return;
    }

    // Lewat batas halaman: muat halaman tetangga, lalu buka orang pertama/terakhirnya.
    const halamanBaru = halaman.value + geser;
    if (halamanBaru < 1 || (halamanBaru - 1) * perHalaman.value >= total.value) return;
    halaman.value = halamanBaru;
    tujuanSesudahMuat = geser > 0 ? 0 : perHalaman.value - 1;
    muat();
}

onMounted(muat);

// Daftar yang habis (mis. orang terakhir di saringan "Belum" baru disimpulkan) menutup ruang kerja.
watch(peserta, () => {
    if (ruangKerja.buka && !pesertaTerbuka.value && tujuanSesudahMuat === null) ruangKerja.buka = false;
});

watch(() => props.cycleId, () => {
    halaman.value = 1;
    opsi.value = { divisi: [], level: [] };
    ruangKerja.buka = false;
    muat();
});
</script>

<style scoped>
.pa-tkq { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.pa-tkq__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; }
.pa-tkq__kartu { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.8rem 0.95rem; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
.pa-tkq__kartu span { font-size: 0.72rem; font-weight: 700; color: #64748b; }
.pa-tkq__kartu strong { font-size: 1.35rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-tkq__kartu strong small { font-size: 0.8rem; color: #94a3b8; }
.pa-tkq__kartu > small { font-size: 0.72rem; color: #94a3b8; }
.pa-tkq__kartu.is-hijau { border-color: #bbf7d0; background: #f0fdf4; }
.pa-tkq__kartu.is-amber { border-color: #fde68a; background: #fffbeb; }
.pa-tkq__bar { height: 6px; margin-top: 0.3rem; border-radius: 999px; background: #dcfce7; overflow: hidden; }
.pa-tkq__bar i { display: block; height: 100%; border-radius: 999px; background: #22c55e; }
.pa-tkq__info { display: flex; align-items: flex-start; gap: 0.45rem; padding: 0.6rem 0.8rem; border: 1px solid #e9d5ff; border-radius: 12px; background: #faf5ff; font-size: 0.8rem; line-height: 1.45; color: #581c87; }
.pa-tkq__section { display: flex; flex-direction: column; gap: 0.75rem; min-width: 0; }
.pa-tkq__galat { margin: 0; color: #b91c1c; font-size: 0.82rem; }
.pa-tkq__redup { color: #94a3b8; font-variant-numeric: tabular-nums; }
.pa-tkq__orang { display: flex; flex-direction: column; min-width: 0; }
.pa-tkq__orang strong { font-size: 0.84rem; color: #0f172a; }
.pa-tkq__orang span { font-size: 0.8rem; color: #334155; }
.pa-tkq__orang small { font-size: 0.72rem; color: #64748b; }
.pa-tkq__jawaban { font-size: 0.78rem; color: #475569; }
.pa-tkq__status { display: flex; flex-direction: column; align-items: flex-start; gap: 0.2rem; }
.pa-tkq__status small { font-size: 0.7rem; color: #64748b; }
.pa-tkq__status small.is-ungu { color: #7e22ce; font-weight: 700; }
.pa-tkq__kaki { display: flex; justify-content: flex-end; }
@media (max-width: 720px) {
    .pa-tkq__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
