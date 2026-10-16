<template>
    <HcisCard
        title="Form Kosong untuk Diunduh"
        description="Formulir kosong yang diunduh karyawan untuk diisi sendiri. Berkas di atas adalah contoh pengisiannya. Opsional, boleh lebih dari satu."
        icon="file-earmark-plus-fill"
        variant="outlined"
        sentiment="primary"
        size="sm"
    >
        <div v-if="daftar.length" class="d-flex flex-column gap-2">
            <div
                v-for="(l, i) in daftar"
                :key="l.Id_Master_Konten"
                class="p-2 rounded-2 border d-flex align-items-center justify-content-between flex-wrap gap-2"
            >
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <HcisIcon name="file-earmark-text" size="sm" />
                    <span class="fw-semibold small text-truncate">{{ l.Judul || 'Berkas tanpa judul' }}</span>
                    <HcisBadge v-if="l.Ext" :label="String(l.Ext).toUpperCase()" variant="brand" size="xs" />
                </div>
                <div class="d-flex align-items-center gap-1">
                    <HcisButton
                        data-aksi="pratinjau"
                        icon-only
                        variant="ghost"
                        size="sm"
                        leading-icon="eye"
                        title="Pratinjau"
                        :loading="memuat === l.Id_Master_Konten"
                        @click="pratinjau(l)"
                    />
                    <HcisButton
                        data-aksi="naik"
                        icon-only
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-up"
                        title="Naikkan"
                        :disabled="terkunci || i === 0"
                        @click="geser(i, -1)"
                    />
                    <HcisButton
                        data-aksi="turun"
                        icon-only
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-down"
                        title="Turunkan"
                        :disabled="terkunci || i === daftar.length - 1"
                        @click="geser(i, 1)"
                    />
                    <HcisButton
                        data-aksi="hapus"
                        icon-only
                        variant="danger-ghost"
                        size="sm"
                        leading-icon="trash"
                        title="Lepas form kosong ini"
                        :disabled="terkunci"
                        @click="hapus(i)"
                    />
                </div>
            </div>
        </div>
        <div v-else class="text-muted small">Belum ada form kosong. Karyawan hanya mendapat contoh pengisian.</div>

        <div class="mt-3">
            <HcisButton
                data-aksi="tambah"
                variant="secondary"
                size="sm"
                leading-icon="plus-lg"
                label="Tambah Form Kosong"
                :disabled="terkunci"
                @click="$emit('tambah')"
            />
        </div>

        <!-- Berkasnya diresolusi server (signed URL 15 menit), seperti pratinjau
             berkas utama di LangkahPemberkasan — tak dirakit di sini. -->
        <HcisFileViewer v-if="berkas" v-model="viewerBuka" :file="berkas" @close="berkas = null" />
    </HcisCard>
</template>

<script>
import axios from 'axios';
import { hcisMessage } from '@/components/ui';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';

/**
 * Form kosong (lampiran unduhan) dokumen FORM di langkah Pemberkasan.
 *
 * Tak menyimpan apa pun sendiri: daftar baru dipancarkan lewat v-model dan
 * wizard yang mengirimnya bersama isian versi lain. Izinnya datang dari luar
 * (`terkunci`), sama dengan field lain di langkah ini.
 */
export default {
    name: 'KartuLampiranForm',
    components: { HcisCard, HcisButton, HcisBadge, HcisIcon, HcisFileViewer },
    props: {
        /** [{ Id_Master_Konten, Judul, Ext }] — urutan larik adalah urutan tampil. */
        modelValue: { type: Array, default: () => [] },
        terkunci: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'tambah'],
    data() {
        return { berkas: null, viewerBuka: false, memuat: null };
    },
    computed: {
        daftar() {
            return Array.isArray(this.modelValue) ? this.modelValue : [];
        },
    },
    methods: {
        hapus(i) {
            this.$emit(
                'update:modelValue',
                this.daftar.filter((_, j) => j !== i),
            );
        },
        geser(i, arah) {
            const j = i + arah;
            if (j < 0 || j >= this.daftar.length) return;
            const baru = [...this.daftar];
            [baru[i], baru[j]] = [baru[j], baru[i]];
            this.$emit('update:modelValue', baru);
        },
        async pratinjau(l) {
            this.memuat = l.Id_Master_Konten;
            try {
                const r = await axios.get(`/api/v1/sop/admin/dokumen/konten/${l.Id_Master_Konten}/berkas`);
                const k = r.data?.result || null;
                if (!k?.url) throw new Error();
                this.berkas = { url: k.url, name: k.nama_berkas || l.Judul || 'Berkas', type: k.mime || '' };
                this.viewerBuka = true;
            } catch (e) {
                this.berkas = null;
                hcisMessage.error(e?.response?.data?.message || 'Gagal memuat berkas untuk pratinjau.');
            } finally {
                this.memuat = null;
            }
        },
    },
};
</script>
