<template>
    <div>
        <HcisAlert
            variant="warning"
            appearance="subtle"
            class="mb-3"
            title="Sakelar keputusan akses per modul"
            description="LAMA = kondisi sekarang. BAYANGAN = aturan lama tetap memutuskan, aturan baru dihitung dan bedanya dicatat. BARU = aturan baru yang memutuskan. Pindah ke BARU hanya setelah selisih modul itu nol atau sudah ditinjau."
        />
        <div class="d-flex justify-content-end flex-wrap gap-2 mb-2">
            <HcisButton
                variant="secondary"
                size="sm"
                leading-icon="clipboard-check"
                :loading="menghitung"
                label="Hitung kesiapan"
                @click="hitungKesiapan"
            />
            <HcisButton
                variant="secondary"
                size="sm"
                leading-icon="arrow-repeat"
                :loading="menyinkron"
                label="Sinkronkan hak peran"
                @click="sinkron"
            />
        </div>

        <HcisTable :columns="kolom" :rows="modul" row-key="id" :loading="memuat" size="sm" responsive="cards">
            <template #cell-nama="{ row }">
                <div class="fw-semibold">{{ row.nama }}</div>
                <div class="text-muted small">{{ row.kode }} · {{ row.jumlah_hak }} hak peran aktif</div>
            </template>
            <template #cell-mode="{ row }">
                <HcisBadge
                    :label="infoMode[row.mode]?.label || row.mode"
                    :variant="infoMode[row.mode]?.varian || 'neutral'"
                    appearance="subtle"
                    size="sm"
                    dot
                />
            </template>
            <template #cell-selisih="{ row }">
                <HcisButton
                    v-if="row.selisih"
                    variant="ghost"
                    size="sm"
                    :label="`${row.selisih} beda`"
                    leading-icon="exclamation-triangle"
                    @click="lihatSelisih(row)"
                />
                <span v-else class="text-muted small">0</span>
            </template>
            <template #cell-kesiapan="{ row }">
                <template v-if="kesiapan[row.id]">
                    <HcisBadge
                        :label="infoKesiapan[kesiapan[row.id].status]?.label || kesiapan[row.id].status"
                        :variant="infoKesiapan[kesiapan[row.id].status]?.varian || 'neutral'"
                        appearance="subtle"
                        size="sm"
                    />
                    <div class="text-muted small mt-1">{{ teksKesiapan(kesiapan[row.id]) }}</div>
                </template>
                <span v-else class="text-muted small">—</span>
            </template>
            <template #cell-tindakan="{ row }">
                <HcisButton variant="ghost" size="sm" leading-icon="toggles" label="Ubah mode" @click="bukaUbah(row)" />
            </template>
        </HcisTable>

        <div class="mt-4">
            <h6 class="fw-bold mb-1">Tingkat peran</h6>
            <p class="text-muted small mb-2">
                Hanya boleh mengangkat atau mengubah tingkat yang lebih rendah, di modul yang sama. Kemampuan Super
                Admin dan Admin bisa diubah di sini; System Admin dan Pengguna tetap.
            </p>
            <HcisTable :columns="kolomTingkat" :rows="tingkat" row-key="kode" size="sm" responsive="cards">
                <template #cell-nama="{ row }">
                    <HcisBadge
                        :label="row.nama"
                        :variant="infoTingkat[row.kode]?.varian || 'neutral'"
                        appearance="subtle"
                        size="sm"
                    />
                    <div class="text-muted small mt-1">{{ row.keterangan }}</div>
                </template>
                <template v-for="k in kemampuan" :key="k.key" #[`cell-${k.key}`]="{ row }">
                    <HcisCheckbox
                        :model-value="row[k.key]"
                        :disabled="!row.bisa_diubah"
                        :aria-label="`${k.label} — ${row.nama}`"
                        @update:model-value="(v) => bukaTingkat(row, k, v)"
                    />
                </template>
            </HcisTable>
        </div>

        <div v-if="selisihModul" class="mt-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h6 class="fw-bold mb-0">Selisih — {{ selisihModul.nama }} ({{ selisih.total }})</h6>
                <HcisButton
                    variant="danger-ghost"
                    size="sm"
                    leading-icon="trash"
                    label="Tandai sudah ditinjau"
                    @click="bukaBersihkan"
                />
            </div>
            <HcisTable :columns="kolomSelisih" :rows="selisih.data" row-key="id" size="sm" dense responsive="cards">
                <template #cell-orang="{ row }">
                    <div class="fw-semibold small">{{ row.nama || `User #${row.id_user}` }}</div>
                    <div class="text-muted ap-kecil">{{ row.kode_karyawan || '—' }}</div>
                </template>
                <template #cell-halaman="{ row }">
                    <div class="small">{{ row.nama_menu || row.jenis_page }}</div>
                    <div class="text-muted ap-kecil">{{ row.aksi }}</div>
                </template>
                <template #cell-keputusan="{ row }">
                    <span class="small"
                        >Lama: <strong>{{ row.lama ? 'boleh' : 'tidak' }}</strong> · Baru:
                        <strong>{{ row.baru ? 'boleh' : 'tidak' }}</strong></span
                    >
                </template>
                <template #cell-terakhir="{ row }"
                    ><span class="small">{{ tanggal(row.terakhir) }} ({{ row.jumlah }}×)</span></template
                >
            </HcisTable>
            <div v-if="selisih.total > selisih.per_halaman" class="d-flex justify-content-end mt-2">
                <HcisPagination
                    :total="selisih.total"
                    :page-size="selisih.per_halaman"
                    :current-page="selisih.halaman"
                    layout="total, prev, pager, next"
                    @current-change="(h) => lihatSelisih(selisihModul, h)"
                />
            </div>
        </div>

        <HcisDialog
            v-model="dialog.buka"
            :title="dialog.judul"
            icon="toggles"
            sentiment="brand"
            size="md"
            header-divider
        >
            <div v-if="dialog.jenis === 'mode'" class="mb-3">
                <label class="form-label small fw-semibold" for="mode-katalog">Mode keputusan</label>
                <HcisSelect
                    id="mode-katalog"
                    v-model="dialog.mode"
                    placeholder="Pilih mode"
                    :clearable="false"
                    aria-label="Mode keputusan"
                >
                    <HcisSelectOption
                        v-for="(m, k) in infoMode"
                        :key="k"
                        :value="k"
                        :label="m.label"
                        :description="baruTerkunci(k) ? 'Pindahkan ke Bayangan dulu dan tinjau selisihnya.' : m.arti"
                        :disabled="baruTerkunci(k)"
                    />
                </HcisSelect>
                <HcisAlert
                    v-if="dialog.mode === 'BARU' && dialog.baris?.selisih"
                    class="mt-2"
                    variant="danger"
                    appearance="subtle"
                    title="Masih ada selisih"
                    :description="`${dialog.baris.selisih} beda belum ditinjau. Dengan BARU, sebagian user bisa kehilangan atau mendapat akses.`"
                />
                <HcisAlert
                    v-if="dialog.mode === 'BARU' && dialog.baris?.mode === 'BAYANGAN'"
                    class="mt-2"
                    variant="info"
                    appearance="subtle"
                    title="Sinkron terakhir"
                    description="Bila modul ini belum pernah BARU, hak perannya disamakan dulu dengan kondisi sekarang. Sesudahnya hak peran diubah lewat matriks di tab Peran."
                />
                <HcisAlert
                    v-if="dialog.baris?.mode === 'BARU' && dialog.mode !== 'BARU'"
                    class="mt-2"
                    variant="info"
                    appearance="subtle"
                    title="Matriks hak dipertahankan"
                    description="Aturan lama kembali memutuskan, tetapi matriks hak modul ini tidak lagi disamakan dengan hibah — suntingan admin tetap ada bila modul dipindah ke BARU lagi."
                />
            </div>
            <HcisTextarea
                id="alasan-katalog"
                v-model="dialog.alasan"
                label="Alasan (wajib, tercatat di audit)"
                :rows="3"
            />
            <template #footer>
                <div class="d-flex justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        label="Batal"
                        :disabled="dialog.simpan"
                        @click="dialog.buka = false"
                    />
                    <HcisButton
                        variant="primary"
                        label="Simpan"
                        :loading="dialog.simpan"
                        :disabled="dialog.alasan.trim().length < 5"
                        @click="simpan"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisDialog,
    HcisSelect,
    HcisSelectOption,
    HcisTable,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, infoKesiapan, infoMode, infoTingkat, tanggal } from '../api/aksesPeranApi.js';

const modul = ref([]);
const memuat = ref(false);
const menyinkron = ref(false);
const menghitung = ref(false);
/** Kesiapan per modul (id → baris); dihitung atas permintaan karena memakai sinkron kering. */
const kesiapan = ref({});
const selisihModul = ref(null);
const selisih = ref({ data: [], total: 0, halaman: 1, per_halaman: 20 });
const terlihatSampai = ref(null);
const dialog = reactive({
    buka: false,
    jenis: 'mode',
    judul: '',
    baris: null,
    mode: 'LAMA',
    alasan: '',
    simpan: false,
});

/** BARU hanya dari BAYANGAN (RBAC Fase 4) — selisihnya harus sempat terlihat. */
const baruTerkunci = (k) => k === 'BARU' && dialog.baris?.mode !== 'BAYANGAN' && dialog.baris?.mode !== 'BARU';

const tingkat = ref([]);
const kemampuan = [
    { key: 'boleh_angkat', label: 'Angkat admin' },
    { key: 'boleh_ubah_matriks', label: 'Atur matriks hak' },
    { key: 'boleh_tugaskan', label: 'Tugaskan peran & grant' },
];
const kolomTingkat = [
    { key: 'nama', label: 'Tingkat' },
    { key: 'peringkat', label: 'Peringkat', align: 'right' },
    ...kemampuan.map((k) => ({ key: k.key, label: k.label, align: 'center' })),
];

function bukaTingkat(row, k, nilai) {
    Object.assign(dialog, {
        buka: true,
        jenis: 'tingkat',
        judul: `${nilai ? 'Beri' : 'Cabut'} "${k.label}" — ${row.nama}`,
        baris: { ...row, ubah: { [k.key]: nilai } },
        alasan: '',
    });
}

const kolom = [
    { key: 'nama', label: 'Modul' },
    { key: 'mode', label: 'Mode' },
    { key: 'selisih', label: 'Selisih' },
    { key: 'kesiapan', label: 'Kesiapan' },
    { key: 'tindakan', label: '', align: 'right' },
];
const kolomSelisih = [
    { key: 'orang', label: 'Karyawan' },
    { key: 'halaman', label: 'Halaman' },
    { key: 'keputusan', label: 'Keputusan' },
    { key: 'terakhir', label: 'Terakhir' },
];

async function muatTingkat() {
    try {
        tingkat.value = (await aksesPeranApi.tingkat()) || [];
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || 'Gagal memuat tingkat peran.');
    }
}

async function muat() {
    memuat.value = true;
    try {
        modul.value = (await aksesPeranApi.katalog()) || [];
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || 'Gagal memuat katalog.');
    } finally {
        memuat.value = false;
    }
}

async function hitungKesiapan() {
    menghitung.value = true;
    try {
        const r = (await aksesPeranApi.kesiapan()) || [];
        kesiapan.value = Object.fromEntries(r.map((m) => [m.id, m]));
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || 'Gagal menghitung kesiapan.');
    } finally {
        menghitung.value = false;
    }
}

function teksKesiapan(k) {
    const beda = k.beda_hak === null ? 'matriks milik admin' : `${k.beda_hak} beda hak vs hibah`;
    return `${k.halaman} halaman · ${k.penugasan} penugasan · ${beda}`;
}

let urutanSelisih = 0;
async function lihatSelisih(row, halaman = 1) {
    selisihModul.value = row;
    const urutan = ++urutanSelisih;
    try {
        const r = (await aksesPeranApi.selisih(row.id, halaman)) || { data: [], total: 0 };
        if (urutan !== urutanSelisih) return; // respons lama, abaikan
        selisih.value = r;
        // Batas "sudah terlihat": Terakhir_At terbaru saat daftar pertama dimuat.
        if (halaman === 1)
            terlihatSampai.value = r.data?.[0]?.terakhir ? String(r.data[0].terakhir).slice(0, 19) : null;
    } catch (e) {
        if (urutan === urutanSelisih) hcisMessage.error(e?.response?.data?.message || 'Gagal memuat selisih.');
    }
}

function bukaUbah(row) {
    Object.assign(dialog, {
        buka: true,
        jenis: 'mode',
        judul: `Ubah mode — ${row.nama}`,
        baris: row,
        mode: row.mode,
        alasan: '',
    });
}

function bukaBersihkan() {
    Object.assign(dialog, {
        buka: true,
        jenis: 'bersihkan',
        judul: `Tandai selisih ${selisihModul.value.nama} sudah ditinjau`,
        baris: selisihModul.value,
        alasan: '',
    });
}

async function simpan() {
    dialog.simpan = true;
    try {
        if (dialog.jenis === 'mode') {
            const r = await aksesPeranApi.ubahMode(dialog.baris.id, dialog.mode, dialog.alasan.trim());
            // Tampilkan hasil seketika; muat ulang (beberapa detik lewat tunnel) menyusul.
            const baris = modul.value.find((m) => m.id === dialog.baris.id);
            if (baris && r?.mode) baris.mode = r.mode;
            hcisMessage.success(`Mode ${dialog.baris.nama} kini ${infoMode[dialog.mode].label}.`);
        } else if (dialog.jenis === 'tingkat') {
            await aksesPeranApi.ubahKemampuanTingkat(dialog.baris.kode, dialog.baris.ubah, dialog.alasan.trim());
            hcisMessage.success(`Kemampuan ${dialog.baris.nama} diperbarui.`);
            await muatTingkat();
        } else {
            await aksesPeranApi.bersihkanSelisih(dialog.baris.id, dialog.alasan.trim(), terlihatSampai.value);
            hcisMessage.success('Selisih ditandai sudah ditinjau.');
            selisihModul.value = null;
        }
        dialog.buka = false;
        await muat();
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || 'Gagal menyimpan.');
    } finally {
        dialog.simpan = false;
    }
}

async function sinkron() {
    menyinkron.value = true;
    try {
        const r = await aksesPeranApi.sinkron();
        hcisMessage.success(`Sinkron: +${r.tambah} baru, ${r.aktifkan} diaktifkan lagi, ${r.nonaktif} dinonaktifkan.`);
        await muat();
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || 'Gagal menyinkronkan.');
    } finally {
        menyinkron.value = false;
    }
}

onMounted(() => {
    muat();
    muatTingkat();
});
</script>

<style scoped>
.ap-kecil {
    font-size: var(--hcis-font-size-xs, 0.75rem);
}
</style>
