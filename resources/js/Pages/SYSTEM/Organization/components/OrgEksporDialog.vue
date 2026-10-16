<template>
    <HcisDialog
        :model-value="modelValue"
        title="Export Relasi ke Excel"
        kicker="RELASI ORGANISASI"
        icon="file-earmark-spreadsheet"
        sentiment="success"
        variant="glass"
        size="2xl"
        sticky-footer
        @update:model-value="(v) => (!memuat || sedangProses) && emit('update:modelValue', v)"
    >
        <div class="ro-eks" data-uji="ro-export-dialog">
            <!-- KIRI: apa yang diekspor -->
            <aside class="ro-eks__kiri">
                <section>
                    <div class="ro-eks__kepala">
                        <span class="ro-eks__label">Kategori relasi</span>
                        <HcisCheckbox
                            :model-value="semuaKategori"
                            :indeterminate="sebagianKategori"
                            label="Pilih semua"
                            size="sm"
                            data-uji="ro-export-semua"
                            @update:model-value="pilihSemuaKategori"
                        />
                    </div>
                    <div class="ro-eks__kategori">
                        <HcisCheckbox
                            v-for="j in jenis"
                            :key="j.id"
                            v-model="tipeIds"
                            :value="j.id"
                            :label="j.nama"
                            :description="j.jumlah !== undefined ? `${angka(j.jumlah)} relasi` : ''"
                            variant="card"
                            size="sm"
                        />
                    </div>
                </section>

                <HcisSwitch v-model="hanyaAnomali" active-text="Hanya relasi yang perlu diperiksa (anomali)" data-uji="ro-export-anomali" />

                <section class="ro-eks__ringkas" data-uji="ro-export-ringkas">
                    <span class="ro-eks__label">Yang akan diekspor</span>
                    <strong>{{ ringkasanSubjek }}</strong>
                    <small>{{ tipeIds.length === jenis.length ? 'Semua kategori' : `${tipeIds.length} kategori` }}{{ hanyaAnomali ? ' · hanya anomali' : '' }}</small>
                    <div v-if="terpilih.length" class="ro-eks__chip">
                        <HcisBadge
                            v-for="k in terpilihTampil"
                            :key="k.kunci"
                            :label="`${k.nama} · ${k.kode_karyawan}`"
                            variant="brand"
                            size="sm"
                            removable
                            @remove="lepas(k.kunci)"
                        />
                        <span v-if="terpilih.length > terpilihTampil.length" class="ro-eks__lagi">+{{ terpilih.length - terpilihTampil.length }} lainnya</span>
                    </div>
                </section>

                <HcisAlert variant="info" appearance="soft" size="sm" icon="info-circle" title="Isi berkas">
                    Sheet <b>Relasi</b> (satu baris per relasi, siap disaring/pivot) dan <b>Ringkasan</b> (saringan &amp; jumlah
                    per kategori). Hanya relasi aktif yang belum dicabut.
                </HcisAlert>
            </aside>

            <!-- KANAN: pemilih karyawan (subjek) -->
            <section class="ro-eks__kanan">
                <div class="ro-eks__kepala">
                    <span class="ro-eks__label">Karyawan (subjek)</span>
                    <HcisButton
                        v-if="adaSaringan"
                        label="Reset saringan"
                        variant="ghost"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetSaringan"
                    />
                </div>
                <HcisField
                    id="ro-export-cari"
                    v-model="saring.q"
                    size="sm"
                    leading-icon="search"
                    placeholder="Cari nama / kode karyawan…"
                    clearable
                    data-uji="ro-export-cari"
                />
                <div class="ro-eks__saring">
                    <HcisSelect v-model="saring.divisi" multiple filterable clearable :max-collapse-tags="1" placeholder="Divisi" size="sm" leading-icon="diagram-3" aria-label="Divisi" class="w-100" data-uji="ro-export-divisi">
                        <HcisSelectOption v-for="d in daftarDivisi" :key="d" :value="d" :label="d" />
                    </HcisSelect>
                    <HcisSelect v-model="saring.departemen" multiple filterable clearable :max-collapse-tags="1" placeholder="Departemen" size="sm" leading-icon="diagram-2" aria-label="Departemen" class="w-100" data-uji="ro-export-departemen">
                        <HcisSelectOption v-for="d in daftarDepartemen" :key="d" :value="d" :label="d" />
                    </HcisSelect>
                    <HcisSelect v-model="saring.level" multiple filterable clearable :max-collapse-tags="1" placeholder="Level" size="sm" leading-icon="bar-chart-steps" aria-label="Level" class="w-100" data-uji="ro-export-level">
                        <HcisSelectOption v-for="l in daftarLevel" :key="l.id" :value="String(l.id)" :label="l.nama" />
                    </HcisSelect>
                </div>

                <div class="ro-eks__aksi">
                    <span class="ro-eks__info" data-uji="ro-export-info">
                        <template v-if="mencari">Mencari…</template>
                        <template v-else>{{ angka(total) }} karyawan cocok · halaman {{ halaman }} dari {{ jumlahHalaman }}</template>
                    </span>
                    <div class="ro-eks__tombol">
                        <HcisButton :label="`Centang halaman ini (${hasil.length})`" variant="secondary" size="xs" leading-icon="check2-square" :disabled="!hasil.length" data-uji="ro-export-centang-tampil" @click="centangTampil" />
                        <HcisButton :label="`Centang semua yang cocok (${angka(total)})`" variant="secondary" size="xs" leading-icon="check-all" :loading="mencentangSemua" :disabled="!total" data-uji="ro-export-centang-semua" @click="centangSemuaCocok" />
                        <HcisButton v-if="terpilih.length" :label="`Kosongkan (${angka(terpilih.length)})`" variant="danger-quiet" size="xs" leading-icon="x-circle" data-uji="ro-export-kosongkan" @click="kosongkan" />
                    </div>
                </div>

                <div v-if="total > PER_HALAMAN" class="ro-eks__halaman">
                    <HcisPagination
                        :total="total"
                        :current-page="halaman"
                        :page-size="PER_HALAMAN"
                        layout="prev, pager, next"
                        size="sm"
                        data-uji="ro-export-halaman"
                        @update:current-page="pindahHalaman"
                    />
                </div>
                <div class="ro-eks__gulir">
                <HcisTable
                    :columns="KOLOM"
                    :rows="hasil"
                    row-key="kunci"
                    :selected-keys="kunciTerpilih"
                    selectable
                    sticky-header
                    :loading="mencari"
                    size="sm"
                    dense
                    hoverable
                    class="ro-eks__tabel"
                    empty-title="Tidak ada karyawan yang cocok"
                    empty-description="Ubah kata kunci atau saringan."
                    empty-icon="person-x"
                    data-uji="ro-export-tabel"
                    @update:selected-keys="ubahPilihan"
                >
                    <template #cell-karyawan="{ row }">
                        <div class="ro-eks__orang"><strong>{{ row.nama }}</strong><small>{{ row.kode_karyawan }}</small></div>
                    </template>
                    <template #cell-level="{ row }">{{ row.nama_level || '–' }}</template>
                    <template #cell-tgl_masuk="{ row }">
                        <span class="text-nowrap">{{ tanggal(row.tgl_masuk) }}</span>
                    </template>
                    <template #cell-unit="{ row }">
                        <div class="ro-eks__orang"><span>{{ row.divisi || '–' }}</span><small>{{ row.departemen || '' }}</small></div>
                    </template>
                </HcisTable>
                </div>

            </section>
        </div>
        <HcisAlert v-if="galat" variant="danger" appearance="soft" size="sm" class="mt-3" :title="galat" />
        <section v-if="proses" class="ro-eks__proses" data-uji="ro-export-proses">
            <HcisProgress
                :value="proses.percent"
                :sentiment="proses.status === 'failed' ? 'danger' : proses.status === 'ready' ? 'success' : 'brand'"
                :label="labelProses"
                :subtitle="`${angka(proses.processed_records)} / ${angka(proses.total_records)} relasi · ${proses.label || 'Relasi Organisasi'}`"
                size="sm"
            />
            <small v-if="proses.status !== 'ready' && proses.status !== 'failed'">
                Diproses di latar belakang per batch. Modal ini boleh ditutup — hasilnya bisa diunduh dari panel Aktivitas (ikon lonceng/aktivitas di atas).
            </small>
        </section>

        <template #footer>
            <HcisButton :label="sedangProses ? 'Tutup (lanjut di latar)' : 'Batal'" variant="secondary" size="sm" :disabled="memuat && !sedangProses" @click="emit('update:modelValue', false)" />
            <HcisButton
                label="Unduh Excel"
                variant="success"
                size="sm"
                leading-icon="download"
                :loading="memuat || sedangProses"
                :disabled="!tipeIds.length || (adaSaringan && !terpilih.length && !total) || sedangProses"
                data-uji="ro-export-unduh"
                @click="unduh"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import {
    HcisAlert, HcisBadge, HcisButton, HcisCheckbox, HcisDialog, HcisField, HcisPagination, HcisProgress, HcisSelect, HcisSelectOption, HcisSwitch, HcisTable,
} from '@/components/ui';

defineOptions({ name: 'OrgEksporDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Kategori relasi: [{ id, nama, jumlah? }] */
    jenis: { type: Array, default: () => [] },
    daftarDivisi: { type: Array, default: () => [] },
    daftarDepartemen: { type: Array, default: () => [] },
    /** [{ id, nama }] */
    daftarLevel: { type: Array, default: () => [] },
    /** Saringan halaman saat dialog dibuka: { tipe_id, hanya_anomali } */
    awal: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue', 'terunduh']);

const PER_HALAMAN = 50;
const KOLOM = [
    { key: 'karyawan', label: 'Karyawan' },
    { key: 'level', label: 'Level', width: '130px' },
    { key: 'unit', label: 'Divisi / Departemen' },
    { key: 'tgl_masuk', label: 'Tgl Masuk', width: '120px' },
];
const angka = (v) => Number(v || 0).toLocaleString('id-ID');
const tanggal = (v) => (v ? new Date(`${String(v).slice(0, 10)}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–');
const memuat = ref(false);
const galat = ref('');

/* ---- Kategori ---- */
const tipeIds = ref([]);
const hanyaAnomali = ref(false);
const semuaKategori = computed(() => props.jenis.length > 0 && tipeIds.value.length === props.jenis.length);
const sebagianKategori = computed(() => tipeIds.value.length > 0 && !semuaKategori.value);
function pilihSemuaKategori(ya) {
    tipeIds.value = ya ? props.jenis.map((j) => j.id) : [];
}
/* Kategori awal = kategori yang disaring di halaman, BILA itu memang id kategori. Chip "Semua
   Relasi" mengisi tipe_id dengan nilai yang bukan id; tanpa pencocokan ini tidak ada yang
   tercentang dan tombol Unduh mati (uji browser 10 Okt). */
function kategoriAwal() {
    const cocok = props.jenis.find((j) => String(j.id) === String(props.awal?.tipe_id ?? ''));
    return cocok ? [cocok.id] : props.jenis.map((j) => j.id);
}

/* ---- Pemilih karyawan ---- */
const saring = reactive({ q: '', divisi: [], departemen: [], level: [] });
const adaSaringan = computed(() => Boolean(saring.q.trim() || saring.divisi.length || saring.departemen.length || saring.level.length));
const hasil = ref([]);
const total = ref(0);
const halaman = ref(1);
const jumlahHalaman = computed(() => Math.max(1, Math.ceil(total.value / PER_HALAMAN)));
const mencentangSemua = ref(false);
const mencari = ref(false);
/* Pilihan disimpan sebagai objek (bukan hanya kunci) supaya tetap terbaca walau sudah tidak
   ada di halaman hasil sekarang — HcisTable mempertahankan pilihan lintas saringan. */
const terpilih = ref([]);
const kunciTerpilih = computed(() => terpilih.value.map((k) => k.kunci));
const terpilihTampil = computed(() => terpilih.value.slice(0, 8));
const kenal = new Map();

const kunci = (k) => `${k.kode_perusahaan}|${k.kode_karyawan}`;

function ubahPilihan(daftar) {
    terpilih.value = daftar.map((x) => kenal.get(x)).filter(Boolean);
}
function centangTampil() {
    ubahPilihan(Array.from(new Set([...kunciTerpilih.value, ...hasil.value.map((r) => r.kunci)])));
}
function lepas(x) {
    terpilih.value = terpilih.value.filter((k) => k.kunci !== x);
}
function kosongkan() {
    terpilih.value = [];
}
function pindahHalaman(h) {
    halaman.value = Number(h) || 1;
    muat();
}
/* Mencentang SELURUH yang cocok, bukan hanya yang termuat: server memulangkan daftar ringkasnya
   (maks 5.000). Sesudahnya admin bisa melepas beberapa orang. */
async function centangSemuaCocok() {
    mencentangSemua.value = true;
    galat.value = '';
    try {
        const q = new URLSearchParams({ q: saring.q.trim() });
        saring.divisi.forEach((d) => q.append('divisi[]', d));
        saring.departemen.forEach((d) => q.append('departemen[]', d));
        saring.level.forEach((l) => q.append('id_level[]', l));
        const res = await fetch(`/system/organisasi/relasi/cari-karyawan/semua?${q.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const isi = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(isi?.message || `Gagal mengambil daftar (${res.status}).`);
        const semua = (isi?.result?.baris ?? isi?.data?.baris ?? []).map((r) => {
            const baris = { ...r, kunci: kunci(r) };
            kenal.set(baris.kunci, baris);
            return baris;
        });
        const ada = new Set(kunciTerpilih.value);
        terpilih.value = [...terpilih.value, ...semua.filter((r) => !ada.has(r.kunci))];
    } catch (e) {
        galat.value = e?.message || 'Gagal mencentang semua.';
    } finally {
        mencentangSemua.value = false;
    }
}
function resetSaringan() {
    Object.assign(saring, { q: '', divisi: [], departemen: [], level: [] });
}

const ringkasanSubjek = computed(() => {
    if (terpilih.value.length) return `${angka(terpilih.value.length)} karyawan dipilih`;
    if (adaSaringan.value) return `Semua ${angka(total.value)} karyawan yang cocok saringan`;
    return 'Semua karyawan';
});

let tunda = null;
let nomorMinta = 0;
function muatTunda() {
    clearTimeout(tunda);
    tunda = setTimeout(() => {
        halaman.value = 1;
        muat();
    }, 300);
}
async function muat() {
    const nomor = ++nomorMinta;
    mencari.value = true;
    try {
        const q = new URLSearchParams({ q: saring.q.trim(), limit: String(PER_HALAMAN), halaman: String(halaman.value) });
        saring.divisi.forEach((d) => q.append('divisi[]', d));
        saring.departemen.forEach((d) => q.append('departemen[]', d));
        saring.level.forEach((l) => q.append('id_level[]', l));
        const res = await fetch(`/system/organisasi/relasi/cari-karyawan?${q.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const isi = await res.json().catch(() => ({}));
        if (nomor !== nomorMinta) return;
        if (!res.ok) throw new Error(isi?.message || `Pencarian gagal (${res.status}).`);
        const data = isi?.result ?? isi?.data ?? {};
        hasil.value = (data.baris || []).map((r) => {
            const baris = { ...r, kunci: kunci(r) };
            kenal.set(baris.kunci, baris);
            return baris;
        });
        total.value = Number(data.total || 0);
    } catch (e) {
        if (nomor === nomorMinta) {
            hasil.value = [];
            total.value = 0;
            galat.value = e?.message || 'Pencarian karyawan gagal.';
        }
    } finally {
        if (nomor === nomorMinta) mencari.value = false;
    }
}
watch(() => [saring.q, saring.divisi.join('|'), saring.departemen.join('|'), saring.level.join('|')], () => {
    if (props.modelValue) muatTunda();
});

/* Setiap kali dibuka, mulai dari saringan halaman. */
watch(() => props.modelValue, (buka) => {
    if (!buka) return;
    tipeIds.value = kategoriAwal();
    hanyaAnomali.value = Boolean(props.awal?.hanya_anomali);
    resetSaringan();
    terpilih.value = [];
    galat.value = '';
    // Lewat debounce yang sama dengan watcher saringan: reset saringan di atas juga memicunya,
    // dan memanggil muat() langsung membuat daftar dimuat dua kali tiap dialog dibuka.
    muatTunda();
}, { immediate: true });
// Daftar kategori bisa baru termuat sesudah dialog dibuka.
watch(() => props.jenis.length, () => {
    if (props.modelValue && !tipeIds.value.length) tipeIds.value = kategoriAwal();
});

/* ---- Unduh ----
   Utamanya di LATAR BELAKANG: server membaca relasi per batch, progresnya dipantau di sini dan di
   panel Aktivitas, lalu berkasnya diunduh begitu siap. Bila penyimpanan ekspor tidak tersedia,
   server menjawab mode "langsung" dan berkas dibuat dalam satu permintaan seperti sebelumnya. */
const proses = ref(null);
const sedangProses = computed(() => Boolean(proses.value) && !['ready', 'failed', 'expired'].includes(proses.value.status));
const labelProses = computed(() => {
    const st = proses.value?.status;
    if (st === 'ready') return 'Selesai — berkas diunduh';
    if (st === 'failed') return proses.value?.error_message || 'Ekspor gagal';
    if (st === 'queued') return 'Menunggu antrean…';
    /* Sesudah semua relasi terbaca, persen berhenti di 99% selama berkas Excel ditulis (±15 detik
       untuk 7.000 relasi). Labelnya mengatakan itu supaya tidak terlihat macet. */
    const p = proses.value || {};
    if (p.total_records > 0 && p.processed_records >= p.total_records) return 'Menulis berkas Excel (tahap akhir)…';
    return 'Membaca relasi per batch…';
});
let pantau = null;
watch(() => props.modelValue, (buka) => {
    if (buka) return;
    clearTimeout(pantau); // tugasnya tetap berjalan di server
    proses.value = null;
});

function saringanEkspor() {
    const isi = {};
    if (!semuaKategori.value) isi.tipe_ids = tipeIds.value;
    if (hanyaAnomali.value) isi.hanya_anomali = 1;
    if (terpilih.value.length) {
        isi.karyawan = kunciTerpilih.value;
    } else if (adaSaringan.value) {
        // Semua yang cocok: server memakai rumus yang sama dengan daftar di atas.
        if (saring.q.trim()) isi.subjek_q = saring.q.trim();
        if (saring.divisi.length) isi.subjek_divisi = saring.divisi;
        if (saring.departemen.length) isi.subjek_departemen = saring.departemen;
        if (saring.level.length) isi.subjek_level = saring.level;
    }
    return isi;
}
function simpanBlob(blob, nama) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nama;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function unduh() {
    memuat.value = true;
    galat.value = '';
    proses.value = null;
    try {
        const isi = saringanEkspor();
        const res = await fetch('/system/organisasi/relasi/ekspor/mulai', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(isi),
        });
        const balas = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(balas?.message || `Export gagal (${res.status}).`);
        const hasil = balas?.result ?? balas?.data ?? {};
        if (hasil.mode === 'latar' && hasil.export_id) {
            proses.value = { status: 'queued', percent: 0, processed_records: 0, total_records: 0, label: '' };
            await pantauProses(hasil.export_id);
            return;
        }
        await unduhLangsung(isi);
    } catch (e) {
        galat.value = e?.message || 'Export gagal.';
    } finally {
        memuat.value = false;
    }
}

async function pantauProses(id) {
    try {
        const res = await fetch(`/system/organisasi/relasi/ekspor/${encodeURIComponent(id)}/status`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const balas = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(balas?.message || `Status ekspor tidak terbaca (${res.status}).`);
        if (!props.modelValue) return;
        proses.value = balas?.result ?? balas?.data ?? proses.value;
    } catch (e) {
        galat.value = e?.message || 'Status ekspor tidak terbaca.';
        return;
    }
    if (proses.value?.status === 'ready') {
        // Unduhan lewat Activity Center: menandai berkasnya sudah diunduh, sama seperti dari panel.
        const a = document.createElement('a');
        a.href = `/api/v1/activities/export/${encodeURIComponent(id)}/download`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        emit('terunduh', Number(proses.value.total_records || 0));
        return;
    }
    if (proses.value?.status === 'failed' || proses.value?.status === 'expired') return;
    pantau = setTimeout(() => pantauProses(id), 1500);
}

async function unduhLangsung(isi) {
    // POST, bukan GET: ratusan kode karyawan terpilih tidak muat di URL.
    const res = await fetch('/system/organisasi/relasi/ekspor', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(isi),
    });
    if (!res.ok) {
        const balas = await res.json().catch(() => ({}));
        throw new Error(balas?.message || `Export gagal (${res.status}).`);
    }
    const blob = await res.blob();
    const nama = (res.headers.get('Content-Disposition') || '').match(/filename="?([^"]+)"?/)?.[1] || 'Relasi-Organisasi.xlsx';
    simpanBlob(blob, nama);
    emit('terunduh', Number(res.headers.get('X-Jumlah-Baris') || 0));
    emit('update:modelValue', false);
}
</script>

<style scoped>
.ro-eks { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 1.25rem; min-width: 0; }
.ro-eks__kiri, .ro-eks__kanan { display: flex; flex-direction: column; gap: 0.85rem; min-width: 0; }
.ro-eks__kepala { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.ro-eks__label { font-size: 0.8rem; font-weight: 600; color: #334155; }
.ro-eks__kategori { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.45rem; }
.ro-eks__ringkas { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.75rem; border: 1px solid #c7d2fe; border-radius: 12px; background: #eef2ff; }
.ro-eks__ringkas strong { font-size: 0.9rem; color: #312e81; }
.ro-eks__ringkas small { color: #4f46e5; font-size: 0.75rem; }
.ro-eks__chip { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.35rem; max-height: 7.5rem; overflow-y: auto; }
.ro-eks__lagi { font-size: 0.72rem; color: #4338ca; align-self: center; }
.ro-eks__saring { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
.ro-eks__saring :deep(.hcis-select) { min-width: 0; max-width: 100%; }
.ro-eks__aksi { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
.ro-eks__info { font-size: 0.78rem; color: #64748b; }
.ro-eks__tombol { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.ro-eks__gulir { max-height: 22rem; overflow-y: auto; border-radius: 12px; }
.ro-eks__orang { display: flex; flex-direction: column; min-width: 0; }
.ro-eks__orang small { color: #64748b; font-size: 0.72rem; }
.ro-eks__halaman { display: flex; justify-content: flex-end; }
.ro-eks__proses { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 1rem; padding: 0.85rem 1rem; border: 1px solid #c7d2fe; border-radius: 12px; background: #f8faff; }
.ro-eks__proses small { color: #64748b; font-size: 0.75rem; }
@media (max-width: 900px) {
    .ro-eks { grid-template-columns: 1fr; }
    .ro-eks__saring { grid-template-columns: 1fr; }
}
</style>
