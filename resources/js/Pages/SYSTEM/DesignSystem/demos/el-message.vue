<template>
    <div v-if="section === 'anatomy'" class="hcis-stack" style="max-width: 32rem">
        <div class="hcis-inline-gap">
            <HcisButton label="Pesan Sukses" variant="success" size="sm" @click="showMsg('success')" />
            <HcisButton label="Pesan Galat" variant="danger" size="sm" @click="showMsg('error')" />
        </div>
        <p class="ds-hint">
            Notifikasi hcisMessage memberikan umpan balik sekilas atas tindakan tanpa menghentikan alur kerja pengguna.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-inline-gap">
        <HcisButton label="Sukses" variant="success" size="sm" @click="showMsg('success')" />
        <HcisButton label="Peringatan" variant="warning" size="sm" @click="showMsg('warning')" />
        <HcisButton label="Galat" variant="danger" size="sm" @click="showMsg('error')" />
        <HcisButton label="Info" variant="secondary" size="sm" @click="showMsg('info')" />
    </div>

    <div v-else-if="section === 'states'" style="max-width: 32rem">
        <p class="ds-hint">Opsi penutupan & durasi</p>
        <HcisButton label="Notifikasi Dengan Judul" variant="primary" size="sm" @click="showCustomMsg" />
    </div>
</template>

<script setup>
import { HcisButton, hcisMessage } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const showMsg = (type) => {
    switch (type) {
        case 'success':
            hcisMessage.success('Perubahan berhasil disimpan.');
            break;
        case 'warning':
            hcisMessage.warning('Peringatan: Kuota tersisa sedikit.');
            break;
        case 'error':
            hcisMessage.error('Gagal terhubung ke server.');
            break;
        case 'info':
            hcisMessage.info('Sistem sedang memperbarui data.');
            break;
    }
};

const showCustomMsg = () => {
    hcisMessage.success('Pengajuan berhasil diproses oleh sistem.', 'Status Berhasil');
};
</script>

<style scoped>
.hcis-inline-gap {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.ds-hint { margin: var(--hcis-space-2) 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
</style>
