<template>
    <main class="pa-lpa-page">
        <HcisHeader
            title="Koreksi Level PA"
            kicker="MASTER DATA · LEVEL PA"
            description="Level yang dipakai PA untuk karyawan yang level HRIS-nya tidak sesuai pekerjaannya. HRIS tidak diubah; koreksi berlaku untuk siklus yang dibentuk sesudahnya."
            icon="person-badge"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-4"
        >
            <template #actions>
                <div class="pa-lpa-aksi-kepala">
                    <HcisButton label="Tambah Koreksi" variant="primary" size="sm" leading-icon="plus-lg" data-uji="lpa-tambah" @click="bukaForm(null)" />
                    <HcisButton label="Impor Excel" variant="secondary" size="sm" leading-icon="upload" :loading="mengimpor" data-uji="lpa-impor" @click="pilihBerkas" />
                    <HcisButton label="Unduh Templat" variant="secondary" size="sm" leading-icon="file-earmark-arrow-down" data-uji="lpa-templat" @click="unduh(true)" />
                    <HcisButton label="Ekspor Excel" variant="secondary" size="sm" leading-icon="file-earmark-spreadsheet" data-uji="lpa-ekspor" @click="unduh(false)" />
                    <input ref="inputBerkas" type="file" accept=".xlsx" class="d-none" @change="imporBerkas">
                </div>
            </template>
            <template #tabs>
                <HcisTabs v-model="tab" :tabs="daftarTab" variant="header" size="sm" aria-label="Koreksi Level PA" @update:model-value="gantiTab" />
            </template>
        </HcisHeader>

        <!-- Calon terdeteksi (Tahap 5) -->
        <section v-if="tab === 'calon'" data-uji="lpa-calon">
            <HcisAlert
                variant="info"
                appearance="soft"
                size="sm"
                class="mb-3"
                title="Bahan tinjauan, bukan perubahan otomatis"
                description="Karyawan aktif tanpa koreksi yang level HRIS-nya di bawah OFFICER (atau belum diisi) tetapi punya bawahan di relasi organisasi dan/atau jabatannya bernada penyelia (SPV, Supervisor, Koordinator, Leader, Kepala, Head, Manager). Sistem tidak mengubah apa pun sampai Anda membuat koreksinya."
            />
            <HcisFilterBar
                :search="cariCalon"
                search-placeholder="Cari nama / kode karyawan…"
                :collapsible="false"
                :results-count="calon && cariCalon ? calon.total : null"
                variant="card"
                sentiment="brand"
                size="sm"
                class="mb-3"
                @update:search="(v) => { cariCalon = v; muatCalon(); }"
                @reset="() => { cariCalon = ''; muatCalon(); }"
            />
            <HcisTable
                :columns="kolomCalon"
                :rows="calon?.baris || []"
                row-key="kode_karyawan"
                :loading="memuatCalon"
                size="sm"
                hoverable
                bordered
                empty-title="Tidak ada calon terdeteksi"
                empty-description="Semua karyawan dengan tanda penyelia sudah sesuai levelnya atau sudah dikoreksi."
                empty-icon="person-check"
            >
                <template #cell-karyawan="{ row }">
                    <div class="pa-lpa-orang"><strong>{{ row.nama }}</strong><small>{{ row.kode_karyawan }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></small></div>
                </template>
                <template #cell-level_hris="{ row }">{{ row.nama_level_hris || 'belum diisi' }}</template>
                <template #cell-divisi="{ row }">{{ row.divisi || '–' }}</template>
                <template #cell-tanda="{ row }">
                    <ul class="pa-lpa-tanda"><li v-for="t in row.tanda" :key="t">{{ t }}</li></ul>
                </template>
                <template #cell-aksi="{ row }">
                    <HcisButton label="Buat koreksi" size="xs" variant="primary" leading-icon="person-badge" @click="koreksiDariCalon(row)" />
                </template>
            </HcisTable>
        </section>

        <!-- Daftar koreksi -->
        <section v-else>
            <HcisAlert
                v-if="dampak.length"
                variant="info"
                appearance="soft"
                size="sm"
                class="mb-3"
                icon="arrow-repeat"
                closable
                data-uji="lpa-dampak"
                title="Koreksi terakhir menyentuh siklus yang sudah membentuk peserta"
                @close="dampak = []"
            >
                <ul class="mb-0 ps-3">
                    <li v-for="d in dampak" :key="d.cycle_id"><b>{{ d.nama }}</b> ({{ d.status }}, {{ d.jumlah }} orang) — {{ d.tindakan }}</li>
                </ul>
            </HcisAlert>
            <HcisAlert v-if="galatImpor" variant="danger" appearance="soft" size="sm" class="mb-3" title="Impor dibatalkan" :description="galatImpor" closable @close="galatImpor = ''" />
            <HcisAlert
                v-if="(data?.cacah?.PERLU_DITINJAU || 0) > 0"
                variant="warning"
                appearance="soft"
                size="sm"
                class="mb-3"
                data-uji="lpa-perlu-tinjau"
                :title="`${data.cacah.PERLU_DITINJAU} koreksi perlu ditinjau`"
                description="Level HRIS orangnya sudah berubah sejak koreksi dibuat, sehingga koreksinya tidak dipakai. Konfirmasi (simpan ulang) bila masih berlaku, atau cabut."
            />
            <HcisFilterBar
                :search="saring.q"
                search-placeholder="Cari nama / kode karyawan…"
                :quick-chips="chipStatus"
                :active-chip-value="saring.status"
                :collapsible="false"
                :results-count="data && (saring.q || saring.status) ? Number(data.total || 0) : null"
                variant="card"
                sentiment="brand"
                size="sm"
                class="mb-3"
                @update:search="(v) => { saring.q = v; saring.halaman = 1; muat(); }"
                @chip-click="(c) => pilihStatus(c.value)"
                @reset="() => { saring.q = ''; pilihStatus(''); }"
            />
            <HcisTable
                :columns="kolomKoreksi"
                :rows="data?.baris || []"
                row-key="id"
                :loading="memuat"
                size="sm"
                hoverable
                bordered
                data-uji="lpa-tabel"
                :empty-title="saring.status || saring.q ? 'Tidak ada koreksi yang cocok' : 'Belum ada koreksi Level PA'"
                :empty-description="saring.status || saring.q ? 'Ubah saringan atau kata kunci pencarian.' : 'Tambah koreksi untuk karyawan yang level HRIS-nya tidak sesuai pekerjaannya, atau tinjau tab Calon terdeteksi.'"
                empty-icon="person-badge"
            >
                <template #cell-karyawan="{ row }">
                    <div class="pa-lpa-orang" :class="{ 'is-redup': row.status === 'DICABUT' }">
                        <strong>{{ row.nama || '(tidak ditemukan)' }}</strong>
                        <small>{{ row.kode_karyawan }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></small>
                    </div>
                </template>
                <template #cell-level_hris="{ row }">
                    <div class="pa-lpa-orang">
                        <span>{{ row.nama_level_hris || '–' }}</span>
                        <small v-if="row.status === 'PERLU_DITINJAU'" class="pa-lpa-merah">saat dibuat: {{ row.nama_level_hris_saat_dibuat || '–' }}</small>
                    </div>
                </template>
                <template #cell-level_pa="{ row }">
                    <HcisBadge :label="row.nama_level_pa" variant="brand" size="sm" />
                </template>
                <template #cell-alasan="{ row }">
                    <div class="pa-lpa-orang pa-lpa-alasan">
                        <span>{{ row.alasan }}</span>
                        <small v-if="row.alasan_cabut" class="pa-lpa-merah">Dicabut: {{ row.alasan_cabut }}</small>
                    </div>
                </template>
                <template #cell-berlaku="{ row }">
                    <span class="text-nowrap">{{ tgl(row.berlaku_dari) }} – {{ row.berlaku_sampai ? tgl(row.berlaku_sampai) : 'seterusnya' }}</span>
                </template>
                <template #cell-status="{ row }">
                    <HcisStatusBadge :status="row.status" :label="LABEL[row.status] || row.status" :variant="NADA[row.status] || 'neutral'" size="xs" />
                </template>
                <template #cell-aksi="{ row }">
                    <div v-if="row.status !== 'DICABUT'" class="pa-lpa-tombol">
                        <HcisButton
                            :label="row.status === 'PERLU_DITINJAU' ? 'Konfirmasi' : 'Ubah'"
                            size="xs"
                            :variant="row.status === 'PERLU_DITINJAU' ? 'warning' : 'secondary'"
                            :leading-icon="row.status === 'PERLU_DITINJAU' ? 'check2-circle' : 'pencil'"
                            @click="bukaForm(row)"
                        />
                        <HcisButton label="Cabut" size="xs" variant="danger-quiet" leading-icon="slash-circle" @click="bukaCabut(row)" />
                    </div>
                </template>
            </HcisTable>
            <div v-if="Number(data?.total || 0) > Number(data?.per_halaman || 25)" class="pa-lpa-hal">
                <HcisPagination
                    :total="Number(data.total)"
                    :current-page="saring.halaman"
                    :page-size="Number(data.per_halaman || 25)"
                    layout="total, prev, pager, next"
                    size="sm"
                    @update:current-page="gantiHalaman"
                />
            </div>
        </section>

        <!-- Form tambah / ubah -->
        <HcisDialog v-model="form.terbuka" :title="form.id ? (form.konfirmasi ? 'Konfirmasi Koreksi Level PA' : 'Ubah Koreksi Level PA') : 'Tambah Koreksi Level PA'" icon="person-badge" sentiment="brand" variant="glass" size="lg" sticky-footer>
            <div class="pa-lpa-form">
                <div class="pa-lpa-isian">
                    <span class="pa-lpa-label">Karyawan <b class="text-danger">*</b> <small v-if="!form.id">(bisa lebih dari satu)</small></span>
                    <HcisSelect
                        id="lpa-karyawan"
                        :model-value="nilaiKaryawan"
                        :multiple="!form.id"
                        :max-collapse-tags="2"
                        :select-all-text="`${form.terpilih.length} karyawan terpilih`"
                        aria-label="Karyawan"
                        :placeholder="form.id ? '' : 'Cari nama / kode karyawan aktif, pilih satu atau lebih'"
                        filterable
                        :clearable="!form.id"
                        :disabled="Boolean(form.id)"
                        leading-icon="person"
                        class="w-100"
                        data-uji="lpa-karyawan"
                        @search="cariKaryawanTunda"
                        @update:model-value="pilihKunciKaryawan"
                    >
                        <!-- Di luar HcisSelectOption supaya tidak ikut disaring ketikan. -->
                        <div v-if="keteranganCari" class="pa-lpa-cari-status" aria-live="polite">{{ keteranganCari }}</div>
                        <HcisSelectOption
                            v-for="k in opsiKaryawan"
                            :key="kunciKaryawan(k)"
                            :value="kunciKaryawan(k)"
                            :label="`${k.nama} · ${k.kode_karyawan}`"
                            :description="[k.nama_level_hris || 'tanpa level HRIS', k.jabatan].filter(Boolean).join(' · ')"
                            :disabled="Boolean(k.koreksi_id) && !form.id"
                            :badge="k.koreksi_id && !form.id ? 'sudah dikoreksi' : ''"
                            badge-variant="warning"
                        />
                    </HcisSelect>
                </div>
                <p v-if="form.id && form.terpilih[0]" class="pa-lpa-terpilih">
                    Level HRIS sekarang <b>{{ form.terpilih[0].nama_level_hris || 'belum diisi' }}</b>
                    <template v-if="form.terpilih[0].jabatan"> · {{ form.terpilih[0].jabatan }}</template>
                </p>
                <div v-else-if="form.terpilih.length" class="pa-lpa-terpilih pa-lpa-terpilih--banyak" data-uji="lpa-terpilih">
                    <div class="pa-lpa-terpilih__kepala">
                        <b>{{ form.terpilih.length }} karyawan terpilih</b>
                        <HcisButton v-if="form.terpilih.length > 1" label="Kosongkan" size="xs" variant="ghost" @click="form.terpilih = []" />
                    </div>
                    <div class="pa-lpa-terpilih__daftar">
                        <HcisBadge
                            v-for="k in form.terpilih"
                            :key="kunciKaryawan(k)"
                            :label="`${k.nama} · ${k.nama_level_hris || 'tanpa level'}`"
                            :variant="samaDenganLevelDipilih(k) ? 'danger' : 'neutral'"
                            size="sm"
                            removable
                            @remove="hapusTerpilih(k)"
                        />
                    </div>
                    <small v-if="bentrok.length" class="pa-lpa-merah" data-uji="lpa-bentrok">
                        {{ bentrok.length }} orang sudah di level {{ namaLevelDipilih }} di HRIS — keluarkan dari pilihan (tanda merah), koreksinya tidak diperlukan.
                    </small>
                </div>
                <div class="pa-lpa-isian">
                    <span class="pa-lpa-label">Level PA <b class="text-danger">*</b></span>
                    <HcisSelect
                        id="lpa-level"
                        v-model="form.id_level_pa"
                        aria-label="Level PA"
                        placeholder="Pilih level yang dipakai karyawan aktif"
                        filterable
                        :clearable="false"
                        leading-icon="diagram-3"
                        class="w-100"
                        data-uji="lpa-level"
                    >
                        <HcisSelectOption
                            v-for="l in opsiLevel"
                            :key="l.id"
                            :value="l.id"
                            :label="l.nama"
                            :description="keteranganLevel(l)"
                            :disabled="levelSamaHris(l)"
                        />
                    </HcisSelect>
                </div>
                <HcisTextarea id="lpa-alasan" v-model="form.alasan" label="Alasan" required :rows="3" :maxlength="500" placeholder="Mis. menjabat fungsi Officer dan membawahi 3 orang" hint="Minimal 5 karakter. Tercatat sebagai jejak audit." />
                <div class="pa-lpa-dua">
                    <div class="pa-lpa-isian">
                        <span class="pa-lpa-label">Berlaku dari</span>
                        <HcisDatePicker v-model="form.berlaku_dari" type="date" placeholder="YYYY-MM-DD" :show-presets="false" :clearable="false" aria-label="Berlaku dari" />
                    </div>
                    <div class="pa-lpa-isian">
                        <span class="pa-lpa-label">Berlaku sampai <small>(opsional)</small></span>
                        <HcisDatePicker v-model="form.berlaku_sampai" type="date" placeholder="Seterusnya" :show-presets="false" :disabled-date="sebelumBerlakuDari" aria-label="Berlaku sampai" />
                    </div>
                </div>
                <HcisAlert v-if="form.galat" variant="danger" appearance="glass" size="sm" :title="form.galat" />
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="form.menyimpan" @click="form.terbuka = false" />
                <HcisButton :label="form.konfirmasi ? 'Konfirmasi' : (!form.id && form.terpilih.length > 1 ? `Simpan untuk ${form.terpilih.length} orang` : 'Simpan')" variant="primary" size="sm" leading-icon="check-lg" :loading="form.menyimpan" data-uji="lpa-simpan" @click="simpan" />
            </template>
        </HcisDialog>

        <!-- Cabut -->
        <HcisDialog v-model="cabut.terbuka" title="Cabut koreksi Level PA?" icon="slash-circle" sentiment="warning" variant="glass" size="sm" sticky-footer>
            <p class="pa-lpa-teks">Koreksi <b>{{ cabut.baris?.nama }}</b> → {{ cabut.baris?.nama_level_pa }} dicabut. Siklus berikutnya kembali memakai level HRIS-nya.</p>
            <HcisTextarea id="lpa-alasan-cabut" v-model="cabut.alasan" label="Alasan pencabutan" required :rows="3" :maxlength="500" placeholder="Mis. sudah dipromosikan resmi di HRIS" hint="Minimal 5 karakter." />
            <HcisAlert v-if="cabut.galat" variant="danger" appearance="glass" size="sm" :title="cabut.galat" />
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" :disabled="cabut.menyimpan" @click="cabut.terbuka = false" />
                <HcisButton label="Ya, cabut" variant="warning" size="sm" :disabled="cabut.alasan.trim().length < 5" :loading="cabut.menyimpan" @click="jalankanCabut" />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert, HcisBadge, HcisButton, HcisDatePicker, HcisDialog, HcisFilterBar, HcisHeader, HcisPagination,
    HcisSelect, HcisSelectOption, HcisStatusBadge, HcisTable, HcisTabs, HcisTextarea, hcisMessage,
} from '@/components/ui';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalLevelPaPage' });

/* Master Koreksi Level PA (DDL-2026-177). Status dihitung server; PERLU_DITINJAU = level HRIS
   orangnya berubah sejak koreksi dibuat, sehingga koreksinya tidak dipakai sampai dikonfirmasi. */
const LABEL = { AKTIF: 'Aktif', PERLU_DITINJAU: 'Perlu ditinjau', BELUM_BERLAKU: 'Belum berlaku', KEDALUWARSA: 'Kedaluwarsa', DICABUT: 'Dicabut' };
const NADA = { AKTIF: 'success', PERLU_DITINJAU: 'danger', BELUM_BERLAKU: 'warning', KEDALUWARSA: 'neutral', DICABUT: 'neutral' };
const daftarStatus = [
    { kode: '', label: 'Semua' },
    { kode: 'AKTIF', label: 'Aktif', color: 'success' },
    { kode: 'PERLU_DITINJAU', label: 'Perlu ditinjau', color: 'danger' },
    { kode: 'BELUM_BERLAKU', label: 'Belum berlaku', color: 'warning' },
    { kode: 'KEDALUWARSA', label: 'Kedaluwarsa' },
    { kode: 'DICABUT', label: 'Dicabut' },
];
const kolomKoreksi = [
    { key: 'karyawan', label: 'Karyawan' },
    { key: 'level_hris', label: 'Level HRIS sekarang', width: '160px' },
    { key: 'level_pa', label: 'Level PA', width: '150px' },
    { key: 'alasan', label: 'Alasan' },
    { key: 'berlaku', label: 'Berlaku', width: '200px' },
    { key: 'status', label: 'Status', width: '130px' },
    { key: 'aksi', label: '', align: 'right', width: '170px' },
];
const kolomCalon = [
    { key: 'karyawan', label: 'Karyawan' },
    { key: 'level_hris', label: 'Level HRIS', width: '150px' },
    { key: 'divisi', label: 'Divisi', width: '180px' },
    { key: 'tanda', label: 'Tanda' },
    { key: 'aksi', label: '', align: 'right', width: '140px' },
];

const data = ref(null);
/* Siklus yang sudah membentuk peserta untuk orang yang baru dikoreksi/dicabut (server). */
const dampak = ref([]);
const memuat = ref(false);
const saring = reactive({ q: '', status: '', halaman: 1 });
const chipStatus = computed(() => daftarStatus.map((c) => ({
    id: c.kode || 'SEMUA',
    value: c.kode,
    label: c.label,
    color: c.color,
    badge: angka(c.kode === '' ? totalSemua.value : data.value?.cacah?.[c.kode]),
})));
const totalSemua = computed(() => Object.values(data.value?.cacah || {}).reduce((a, b) => a + Number(b || 0), 0));
const angka = (v) => Number(v || 0).toLocaleString('id-ID');
const tgl = (v) => (v ? new Date(`${v}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–');

async function muat() {
    memuat.value = true;
    try {
        data.value = await paApi.daftarLevelPa({ q: saring.q.trim(), status: saring.status, halaman: saring.halaman });
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal memuat koreksi.', 'Koreksi Level PA');
    } finally {
        memuat.value = false;
    }
}

function gantiHalaman(h) {
    saring.halaman = Number(h) || 1;
    muat();
}
/* Unduhan GET biasa (sesi peramban ikut), lewat tombol supaya tampilannya sama dengan tombol lain. */
function unduh(templat) {
    window.location.assign(paApi.alamatEksporLevelPa(templat));
}
function pilihStatus(kode) {
    saring.status = kode;
    saring.halaman = 1;
    muat();
}

const kosongForm = () => ({ terbuka: true, id: null, konfirmasi: false, cari: '', mencari: false, hasil: [], terpilih: [], id_level_pa: null, alasan: '', berlaku_dari: new Date().toISOString().slice(0, 10), berlaku_sampai: '', galat: '', menyimpan: false });
const form = reactive({ ...kosongForm(), terbuka: false });

function bukaForm(r) {
    Object.assign(form, kosongForm());
    if (r) {
        Object.assign(form, {
            id: r.id, konfirmasi: r.status === 'PERLU_DITINJAU',
            terpilih: [{ kode_perusahaan: r.kode_perusahaan, nama: r.nama, kode_karyawan: r.kode_karyawan, nama_level_hris: r.nama_level_hris, id_level_hris: r.id_level_hris, jabatan: r.jabatan }],
            id_level_pa: r.id_level_pa, alasan: r.alasan, berlaku_dari: r.berlaku_dari, berlaku_sampai: r.berlaku_sampai || '',
        });
    }
}

let tundaK = null;
function cariKaryawanTunda(kata) {
    clearTimeout(tundaK);
    form.cari = String(kata ?? '').trim();
    if (form.cari.length < 2) { form.hasil = []; form.mencari = false; return; }
    form.mencari = true;
    tundaK = setTimeout(async () => {
        const diminta = form.cari;
        try {
            const hasil = await paApi.cariKaryawanLevelPa(diminta);
            if (diminta === form.cari) form.hasil = hasil || [];
        } catch { form.hasil = []; } finally {
            if (diminta === form.cari) form.mencari = false;
        }
    }, 300);
}
const kunciKaryawan = (k) => `${k.kode_perusahaan ?? ''}|${k.kode_karyawan}`;
/* Tambah: banyak orang (array kunci). Ubah: satu orang (kunci tunggal, select terkunci). */
const nilaiKaryawan = computed(() => (form.id ? (form.terpilih[0] ? kunciKaryawan(form.terpilih[0]) : '') : form.terpilih.map(kunciKaryawan)));
/* Yang terpilih selalu ikut di daftar opsi, supaya labelnya tetap tampil walau hasil cari berganti. */
const opsiKaryawan = computed(() => {
    const ada = new Set(form.hasil.map(kunciKaryawan));
    return [...form.terpilih.filter((k) => !ada.has(kunciKaryawan(k))), ...form.hasil];
});
const keteranganCari = computed(() => {
    if (form.id) return '';
    if (form.mencari) return 'Mencari…';
    if (form.cari.length < 2) return 'Ketik minimal 2 huruf nama atau kode karyawan.';
    if (!form.hasil.length) return 'Tidak ada karyawan aktif yang cocok.';
    return '';
});
function pilihKunciKaryawan(nilai) {
    const kunci = Array.isArray(nilai) ? nilai : (nilai ? [nilai] : []);
    const peta = new Map(opsiKaryawan.value.map((k) => [kunciKaryawan(k), k]));
    form.terpilih = kunci.map((x) => peta.get(x)).filter(Boolean);
}
function hapusTerpilih(k) {
    form.terpilih = form.terpilih.filter((x) => kunciKaryawan(x) !== kunciKaryawan(k));
}
/* Pilihan level = level yang dipakai karyawan aktif (server). Level koreksi lama yang kini kosong tetap ditampilkan. */
const opsiLevel = computed(() => {
    const daftar = [...(data.value?.level || [])];
    if (form.id_level_pa != null && !daftar.some((l) => l.id === form.id_level_pa)) {
        const r = data.value?.baris?.find((b) => b.id === form.id);
        daftar.push({ id: form.id_level_pa, nama: r?.nama_level_pa || `Level ${form.id_level_pa}`, jumlah: null });
    }
    return daftar;
});
const diLevel = (k, id) => k.id_level_hris != null && Number(k.id_level_hris) === Number(id);
/* Level dimatikan hanya bila SEMUA yang terpilih sudah di level itu; sebagian → diberi tanda saja. */
const levelSamaHris = (l) => form.terpilih.length > 0 && form.terpilih.every((k) => diLevel(k, l.id));
function keteranganLevel(l) {
    if (l.khusus_pa) return 'Level khusus PA';
    if (levelSamaHris(l)) return form.terpilih.length > 1 ? 'Semua terpilih sudah di level ini' : 'Sama dengan level HRIS-nya — tidak perlu koreksi';
    const n = form.terpilih.filter((k) => diLevel(k, l.id)).length;
    const jumlah = l.jumlah != null ? `${angka(l.jumlah)} karyawan aktif` : '';
    return n ? `${jumlah} · ${n} terpilih sudah di level ini` : jumlah;
}
const samaDenganLevelDipilih = (k) => form.id_level_pa != null && diLevel(k, form.id_level_pa);
const bentrok = computed(() => form.terpilih.filter(samaDenganLevelDipilih));
const namaLevelDipilih = computed(() => opsiLevel.value.find((l) => l.id === form.id_level_pa)?.nama || '');
const sebelumBerlakuDari = (d) => Boolean(form.berlaku_dari) && d < new Date(`${form.berlaku_dari}T00:00:00`);
const tab = ref('koreksi');
const calon = ref(null);
const memuatCalon = ref(false);
const cariCalon = ref('');
async function muatCalon() {
    memuatCalon.value = true;
    try {
        calon.value = await paApi.calonLevelPa(cariCalon.value.trim());
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal memindai calon.', 'Calon Level PA');
    } finally {
        memuatCalon.value = false;
    }
}
const daftarTab = computed(() => [
    { key: 'koreksi', label: 'Koreksi', icon: 'person-badge', badge: angka(totalSemua.value) },
    { key: 'calon', label: 'Calon terdeteksi', icon: 'search', badge: calon.value ? angka(calon.value.total) : null, badgeVariant: 'warning' },
]);
function gantiTab(t) {
    if (t === 'calon' && !calon.value) muatCalon();
}
/* Formulir terisi dari temuan: karyawan, saran OFFICER, dan alasan usulan — admin tetap memutuskan. */
function koreksiDariCalon(c) {
    bukaForm(null);
    pilihKaryawan(c);
    form.id_level_pa = calon.value?.saran_level?.id ?? null;
    form.alasan = c.usulan_alasan;
}

function pilihKaryawan(k) {
    form.terpilih = [k];
    if (levelSamaHris({ id: form.id_level_pa })) form.id_level_pa = null;
}

async function simpan() {
    form.galat = '';
    if (!form.id && !form.terpilih.length) { form.galat = 'Pilih minimal satu karyawan.'; return; }
    if (bentrok.value.length) { form.galat = `Keluarkan ${bentrok.value.map((k) => k.nama).join(', ')} — level HRIS-nya sudah ${namaLevelDipilih.value}.`; return; }
    form.menyimpan = true;
    try {
        let r;
        const isi = { id_level_pa: form.id_level_pa, alasan: form.alasan, berlaku_dari: form.berlaku_dari || null, berlaku_sampai: form.berlaku_sampai || null };
        if (form.id) r = await paApi.ubahLevelPa(form.id, isi);
        else r = await paApi.simpanLevelPa({ ...isi, karyawan: form.terpilih.map((k) => ({ kode_perusahaan: k.kode_perusahaan, kode_karyawan: k.kode_karyawan })) });
        const n = form.id ? 1 : form.terpilih.length;
        dampak.value = r?.dampak || [];
        form.terbuka = false;
        hcisMessage.success(`${n > 1 ? `${n} koreksi` : 'Koreksi'} Level PA tersimpan. Berlaku untuk siklus yang dibentuk sesudahnya.`, 'Koreksi Level PA');
        muat();
        if (calon.value) muatCalon();
    } catch (e) {
        form.galat = e?.message || 'Gagal menyimpan.';
    } finally {
        form.menyimpan = false;
    }
}

const cabut = reactive({ terbuka: false, baris: null, alasan: '', galat: '', menyimpan: false });
function bukaCabut(r) {
    Object.assign(cabut, { terbuka: true, baris: r, alasan: '', galat: '', menyimpan: false });
}
async function jalankanCabut() {
    cabut.menyimpan = true;
    try {
        const r = await paApi.cabutLevelPa(cabut.baris.id, cabut.alasan.trim());
        dampak.value = r?.dampak || [];
        cabut.terbuka = false;
        hcisMessage.success('Koreksi dicabut.', 'Koreksi Level PA');
        muat();
    } catch (e) {
        cabut.galat = e?.message || 'Gagal mencabut.';
    } finally {
        cabut.menyimpan = false;
    }
}

const inputBerkas = ref(null);
const mengimpor = ref(false);
const galatImpor = ref('');
function pilihBerkas() {
    inputBerkas.value?.click();
}
async function imporBerkas(ev) {
    const berkas = ev.target.files?.[0];
    ev.target.value = '';
    if (!berkas) return;
    mengimpor.value = true;
    galatImpor.value = '';
    try {
        const r = await paApi.imporLevelPa(berkas);
        hcisMessage.success(`${r?.dibuat ?? 0} koreksi dibuat, ${r?.diperbarui ?? 0} diperbarui.`, 'Impor selesai');
        muat();
    } catch (e) {
        galatImpor.value = e?.message || 'Impor gagal.';
    } finally {
        mengimpor.value = false;
    }
}

onMounted(muat);
</script>

<style scoped>
.pa-lpa-page { padding-bottom: 2rem; }
.pa-lpa-aksi-kepala { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 0.5rem; }
.pa-lpa-orang { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.pa-lpa-orang small { color: #64748b; font-size: 0.74rem; }
.pa-lpa-orang.is-redup { opacity: 0.55; }
.pa-lpa-alasan { max-width: 24rem; white-space: normal; }
.pa-lpa-merah { color: #b91c1c !important; }
.pa-lpa-tombol { display: inline-flex; gap: 0.35rem; justify-content: flex-end; }
.pa-lpa-tanda { margin: 0; padding-left: 1rem; font-size: 0.78rem; color: #92400e; }
.pa-lpa-hal { display: flex; justify-content: flex-end; margin-top: 0.75rem; }
.pa-lpa-form { display: flex; flex-direction: column; gap: 0.75rem; min-width: 0; }
.pa-lpa-isian { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.pa-lpa-label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.pa-lpa-label small { font-weight: 400; color: #94a3b8; }
.pa-lpa-cari-status { padding: 0.45rem 0.7rem; font-size: 0.78rem; color: #64748b; }
.pa-lpa-dua { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem; }
/* Kolom grid dan isian boleh menyusut: tanpa min-width 0, lebar bawaan input tanggal/select
   mendorong isi modal melebar dan memunculkan gulir mendatar. */
.pa-lpa-isian :deep(.hcis-date-picker__input), .pa-lpa-isian :deep(.hcis-select) { min-width: 0; max-width: 100%; }
.pa-lpa-terpilih { margin: 0; padding: 0.5rem 0.7rem; border-radius: 10px; background: #eef2ff; color: #3730a3; font-size: 0.82rem; }
.pa-lpa-teks { font-size: 0.85rem; }
.pa-lpa-terpilih--banyak { display: flex; flex-direction: column; gap: 0.45rem; background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; }
.pa-lpa-terpilih__kepala { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.pa-lpa-terpilih__daftar { display: flex; flex-wrap: wrap; gap: 0.35rem; max-height: 9.5rem; overflow-y: auto; }
@media (max-width: 640px) {
    .pa-lpa-dua { grid-template-columns: 1fr; }
    .pa-lpa-aksi-kepala { justify-content: flex-start; }
}
</style>
