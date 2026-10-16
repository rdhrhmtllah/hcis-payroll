<template>
    <div v-if="section === 'anatomy'" style="max-width: 32rem">
        <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-3 text-amber-900 text-xs">
            <strong>⚠️ Komponen Usang / Dipensiunkan:</strong> <code>el-upload</code> sudah dipensiunkan di seluruh HCIS. Harap gunakan <code>&lt;HcisUpload&gt;</code> dengan fitur dropzone cerdas dan integrasi preview otomatis.
        </div>

        <HcisUpload
            v-model="daftarBerkas"
            label="Lampiran Surat Dokter & Bukti Medis"
            accept=".pdf,.jpg,.png"
            :limit="2"
            :max-size="5"
            drag
            hint="Format PDF, JPG, atau PNG · Maksimal 5 MB per berkas"
        />
        <p class="ds-hint">
            Jenis dan batas ukuran disebut di dekat kontrolnya, bukan disimpan sebagai kejutan sampai unggahan gagal di server.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div class="hcis-grid hcis-grid--2">
            <div>
                <p class="ds-hint">drag — untuk berkas tunggal yang penting</p>
                <HcisUpload
                    v-model="berkasPdf"
                    accept=".pdf"
                    :limit="2"
                    :max-size="10"
                    drag
                    hint="Format PDF saja · Maksimal 10 MB"
                />
            </div>
            <div>
                <p class="ds-hint">tombol — untuk lampiran sekunder</p>
                <HcisUpload
                    v-model="berkasSekunder"
                    accept=".pdf,.jpg,.png"
                    :drag="false"
                    hint="PDF, JPG, PNG"
                />
            </div>
        </div>

        <div style="max-width: 32rem; margin-top: 12px">
            <p class="ds-hint">Batas Limit Terpenuhi (Dropzone Tersembunyi Otomatis)</p>
            <HcisUpload
                v-model="berkasLimitTerpenuhi"
                accept=".pdf"
                :limit="1"
                drag
            />
        </div>
    </div>

    <div v-else-if="section === 'states'" style="max-width: 32rem">
        <p class="ds-hint">Dengan daftar berkas terpilih</p>
        <HcisUpload
            v-model="berkasLengkap"
            accept=".pdf"
            multiple
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisUpload } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const daftarBerkas = ref([
    { name: 'surat-dokter-agustus.pdf', size: 1420000, status: 'success' },
]);
const berkasPdf = ref([]);
const berkasSekunder = ref([]);
const berkasLimitTerpenuhi = ref([
    { name: 'dokumen-spk-kontrak-kerja-final.pdf', size: 3200000, status: 'success' },
]);
const berkasLengkap = ref([
    { name: 'surat-dokter-agustus.pdf', size: 1420000, status: 'success' },
]);
</script>

<style scoped>
.ds-hint { margin: var(--hcis-space-2) 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
</style>
