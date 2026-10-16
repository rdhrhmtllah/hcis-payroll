<template>
    <HcisEmptyState
        v-if="!rows.length"
        icon="people"
        title="Belum ada penugasan"
        description="Belum ada anggota yang ditugaskan untuk wewenang ini."
        size="sm"
    />
    <HcisTable v-else :columns="kolom" :rows="rows" row-key="id_penugasan" responsive="cards" size="sm" dense>
        <template #cell-orang="{ row }">
            <div class="d-flex align-items-center gap-2 py-1">
                <HcisAvatar :name="row.nama || 'User'" size="sm" class="flex-shrink-0" />
                <div class="min-w-0">
                    <div class="fw-semibold small text-truncate text-dark">
                        {{ row.nama || `User #${row.id_user}` }}
                    </div>
                    <div class="text-muted ap-kecil font-monospace">{{ row.kode_karyawan || '—' }}</div>
                </div>
            </div>
        </template>
        <template #cell-peran="{ row }">
            <div class="fw-semibold small">{{ row.nama_peran }}</div>
            <div class="text-muted font-monospace ap-kecil">{{ row.kode }} · {{ row.modul || 'Lintas modul' }}</div>
        </template>
        <template #cell-cakupan="{ row }">
            <HcisBadge :label="teksCakupan(row.cakupan)" variant="info" appearance="subtle" size="xs" />
        </template>
        <template #cell-tanggung_jawab="{ row }">
            <div class="d-flex flex-wrap gap-1">
                <HcisBadge
                    v-for="t in row.tanggung_jawab"
                    :key="t"
                    :label="labelTanggungJawab[t] || t"
                    variant="brand"
                    appearance="subtle"
                    size="xs"
                />
                <span v-if="!row.tanggung_jawab?.length" class="text-muted small">—</span>
            </div>
        </template>
        <template #cell-berlaku_sampai="{ row }">
            <div class="d-flex align-items-center gap-1">
                <span class="small">{{ row.berlaku_sampai ? tanggal(row.berlaku_sampai) : 'Tanpa batas' }}</span>
                <HcisBadge
                    v-if="row.kedaluwarsa"
                    label="Kedaluwarsa"
                    variant="danger"
                    appearance="subtle"
                    size="xs"
                    class="ms-1"
                />
            </div>
        </template>
        <template #cell-aksi="{ row }">
            <div class="d-flex align-items-center justify-content-end gap-1">
                <HcisButton
                    v-if="row.bisa_cabut && row.jenis !== 'POOL_APPROVAL'"
                    variant="ghost"
                    size="xs"
                    leading-icon="hourglass-split"
                    label="Batas"
                    :aria-label="`Ubah batas waktu ${row.nama_peran}`"
                    @click="$emit('batas', row)"
                />
                <HcisButton
                    v-if="row.bisa_cabut"
                    variant="danger-ghost"
                    size="xs"
                    leading-icon="x-circle"
                    label="Cabut"
                    :aria-label="`Cabut peran ${row.nama_peran}`"
                    @click="$emit('cabut', row)"
                />
            </div>
        </template>
    </HcisTable>
</template>

<script setup>
import { computed } from 'vue';
import { HcisAvatar, HcisBadge, HcisButton, HcisEmptyState, HcisTable } from '@/components/ui';
import { labelTanggungJawab, tanggal, teksCakupan } from '../api/aksesPeranApi.js';

const props = defineProps({
    rows: { type: Array, required: true },
    /** true = kolom orang (daftar pemegang); false = kolom peran (akses seorang user). */
    tampilOrang: { type: Boolean, default: false },
    /** sembunyikan kolom peran jika sudah dalam konteks peran spesifik */
    sembunyikanPeran: { type: Boolean, default: false },
    /** tampilkan kolom tindakan (Cabut) untuk baris ber-bendera bisa_cabut */
    bisaAksi: { type: Boolean, default: false },
});
defineEmits(['cabut', 'batas']);

const kolom = computed(() => [
    props.tampilOrang ? { key: 'orang', label: 'Karyawan' } : { key: 'peran', label: 'Peran' },
    ...(props.tampilOrang && !props.sembunyikanPeran ? [{ key: 'peran', label: 'Peran' }] : []),
    { key: 'cakupan', label: 'Cakupan' },
    { key: 'tanggung_jawab', label: 'Tanggung jawab' },
    { key: 'berlaku_sampai', label: 'Masa berlaku' },
    ...(props.bisaAksi ? [{ key: 'aksi', label: '', align: 'right' }] : []),
]);
</script>
