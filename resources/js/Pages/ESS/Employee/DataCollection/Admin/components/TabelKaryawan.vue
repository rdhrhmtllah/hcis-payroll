<template>
    <HcisDataTable
        title="Daftar Karyawan"
        icon="bi bi-people"
        :columns="kolom"
        :data="hasil.items"
        :loading="memuat"
        :search="filter.cari"
        search-placeholder="Cari nama, kode, atau NIK…"
        :filters="saringan"
        :filter-values="{ status: filter.status, departemen: filter.departemen, lokasi: filter.lokasi }"
        :total="hasil.total"
        :page="hasil.halaman"
        :page-size="hasil.per_halaman"
        @update:search="cariBerubah"
        @filter-change="saringanBerubah"
        @page-change="muat"
        @size-change="ubahPerHalaman"
    >
        <template #cell-karyawan="{ row }">
            <div class="tk__orang">
                <span class="tk__nama">{{ row.nama }}</span>
                <span class="tk__kode">{{ row.Kode_Karyawan }} · {{ row.nik_karyawan || '—' }}</span>
            </div>
        </template>

        <template #cell-status="{ row }">
            <HcisStatusBadge
                :status="nadaStatus(row.status)"
                :label="labelStatus(row.status)"
                size="sm"
            />
            <HcisBadge
                v-if="row.pernah_dibuka_kunci"
                label="pernah dibuka"
                variant="warning"
                size="xs"
                class="tk__lencana-tambahan"
            />
        </template>

        <template #cell-tindakan="{ row }">
            <div class="tk__aksi">
                <HcisButton
                    v-if="row.status === 'SELESAI'"
                    variant="ghost"
                    size="sm"
                    label="Lihat"
                    leading-icon="bi bi-eye"
                    @click="$emit('detail', row)"
                />
                <HcisButton
                    v-if="row.status === 'SELESAI'"
                    variant="ghost"
                    size="sm"
                    label="Buka kunci"
                    leading-icon="bi bi-unlock"
                    @click="$emit('buka-kunci', row)"
                />
                <!--
                    Tombol menyebut TINDAKANNYA, bukan nama fiturnya. Label tetap
                    "Pengecualian" pada orang yang sudah dikecualikan terbaca
                    seperti tombol itu akan mengecualikannya lagi — padahal ia
                    justru mengembalikannya ke kampanye.
                -->
                <HcisButton
                    variant="ghost"
                    size="sm"
                    :label="row.status === 'DIKECUALIKAN' ? 'Ikutsertakan' : 'Kecualikan'"
                    :leading-icon="row.status === 'DIKECUALIKAN' ? 'bi bi-arrow-counterclockwise' : 'bi bi-slash-circle'"
                    @click="$emit('pengecualian', row)"
                />
            </div>
        </template>

        <template #empty>
            <HcisEmptyState
                icon="bi bi-search"
                :title="adaSaringan ? 'Tidak ada yang cocok dengan saringan' : 'Belum ada karyawan terdaftar'"
                :description="adaSaringan ? 'Longgarkan saringannya untuk melihat lebih banyak.' : ''"
            />
        </template>
    </HcisDataTable>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { HcisBadge, HcisButton, HcisDataTable, HcisEmptyState, HcisStatusBadge } from '@/components/ui';

const props = defineProps({
    awal: { type: Object, required: true },
    pilihanDepartemen: { type: Array, default: () => [] },
    pilihanLokasi: { type: Array, default: () => [] },
});

defineEmits(['detail', 'buka-kunci', 'pengecualian']);

const hasil = ref({ ...props.awal });
const memuat = ref(false);
/**
 * 'lokasi' di sini murni SARINGAN TAMPILAN. Cakupan kampanye — lokasi mana yang
 * wajib mengisi — diatur di panel tersendiri dan tidak tersentuh dari sini;
 * memilih satu lokasi di sini tidak pernah memperluas kampanye.
 */
const filter = reactive({ cari: '', status: '', departemen: '', lokasi: '' });

const kolom = [
    { key: 'karyawan', label: 'Karyawan' },
    { key: 'departemen', label: 'Departemen' },
    { key: 'status', label: 'Status' },
    { key: 'tindakan', label: 'Tindakan', align: 'right' },
];

/**
 * 'type' WAJIB ada di setiap saringan. HcisFilterField memetakan tipe yang
 * hilang ke 'input' — kotak teks bebas — dan karena ia tetap merender sesuatu
 * yang terlihat wajar, ketiga saringan di sini tampil sebagai kotak kosong yang
 * tidak pernah menawarkan satu pilihan pun. 'options' saja tidak membuatnya
 * menjadi dropdown.
 */
const saringan = computed(() => [
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'Semua status',
        options: [
            { value: '', label: 'Semua status' },
            { value: 'BELUM', label: 'Belum mengisi' },
            { value: 'TERKUNCI', label: 'Terkunci' },
            { value: 'SELESAI', label: 'Sudah mengisi' },
            { value: 'DIKECUALIKAN', label: 'Dikecualikan' },
        ],
    },
    {
        key: 'departemen',
        label: 'Departemen',
        type: 'select',
        placeholder: 'Semua departemen',
        options: [
            { value: '', label: 'Semua departemen' },
            ...props.pilihanDepartemen.map((d) => ({ value: d, label: d })),
        ],
    },
    {
        key: 'lokasi',
        label: 'Lokasi kerja',
        type: 'select',
        placeholder: 'Semua lokasi',
        options: [
            { value: '', label: 'Semua lokasi' },
            ...props.pilihanLokasi.map((l) => ({ value: l, label: l })),
        ],
    },
]);

/** Satu peta status → nada, supaya warnanya tidak ditebak di dua tempat. */
const nadaStatus = (s) =>
    ({ SELESAI: 'success', TERKUNCI: 'danger', DIKECUALIKAN: 'neutral' })[s] ?? 'warning';

/** Mengubah jumlah baris selalu kembali ke halaman 1 — halaman 7 dari daftar
 *  lama hampir pasti bukan halaman 7 dari daftar baru. */
const ubahPerHalaman = (jumlah) => {
    hasil.value = { ...hasil.value, per_halaman: jumlah };
    muat(1);
};

const cariBerubah = (nilai) => {
    filter.cari = nilai;
    muatTertunda();
};

const saringanBerubah = ({ key, value }) => {
    filter[key] = value ?? '';
    muat(1);
};

const adaSaringan = computed(() => !!(filter.cari || filter.status || filter.departemen || filter.lokasi));

const labelStatus = (s) =>
    ({
        BELUM: 'Belum mengisi',
        TERKUNCI: 'Terkunci',
        SELESAI: 'Sudah mengisi',
        DIKECUALIKAN: 'Dikecualikan',
    })[s] ?? s;

const muat = async (halaman = 1) => {
    memuat.value = true;

    try {
        const { data } = await axios.get('/admin/pengumpulan-data/daftar', {
            params: { ...filter, halaman, per_halaman: hasil.value.per_halaman },
        });
        hasil.value = data;
    } finally {
        memuat.value = false;
    }
};

// Mengetik tidak boleh memicu satu permintaan per huruf.
let jeda = null;
const muatTertunda = () => {
    clearTimeout(jeda);
    jeda = setTimeout(() => muat(1), 300);
};

defineExpose({ muat });
</script>

<style scoped>
.tk__orang {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-0-5);
}

.tk__nama {
    font-weight: 600;
    color: var(--hcis-text-strong);
}

.tk__kode {
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
    font-variant-numeric: tabular-nums;
}

.tk__lencana-tambahan {
    margin-left: var(--hcis-space-2);
}

.tk__aksi {
    display: flex;
    justify-content: flex-end;
    gap: var(--hcis-space-1);
}
</style>
