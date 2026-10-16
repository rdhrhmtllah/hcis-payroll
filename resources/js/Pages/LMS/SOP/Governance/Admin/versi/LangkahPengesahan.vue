<template>
    <div class="d-flex flex-column gap-3">
        <!-- ══ 1. CONTEXT NOTIFICATION BANNER ══ -->
        <HcisAlert
            v-if="pakaiPengesahan"
            variant="teal"
            appearance="soft"
            icon="shield-check"
            title="Tahap Otorisasi &amp; Pengesahan Pejabat"
            :badge="`${(pengesah || []).length} Pejabat Ditugaskan`"
            size="sm"
            glow
            pattern="dots"
        >
            Tentukan daftar pejabat struktural yang menandatangani dan mengesahkan versi dokumen ini. Matriks ini menjadi dasar autentikasi lembar pengesahan resmi sebelum dokumen diterbitkan.
        </HcisAlert>

        <HcisAlert
            v-else
            variant="info"
            appearance="soft"
            icon="info-circle"
            title="Pengesahan Tidak Diperlukan"
            size="sm"
            class="mb-0"
        >
            Dokumen bertipe <strong>{{ tipe }}</strong> tidak memerlukan matriks pengesahan berjenjang. Anda dapat langsung melanjutkan ke langkah Pemberkasan.
        </HcisAlert>

        <!-- ══ 2. CARD TANGGAL TUNTAS APPROVAL ══ -->
        <HcisCard
            title="Linimasa Tanggal Tuntas Approval"
            description="Pencatatan tanggal resmi saat seluruh pejabat penanggung jawab selesai memberikan persetujuan formal."
            icon="calendar-check"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Tanggal_Akhir_Approval')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <div class="row">
                <div class="col-12 col-md-6">
                    <HcisField
                        label="Tanggal Persetujuan Tuntas"
                        description="Tercatat otomatis saat memasuki tahap Pengesahan. Dapat disesuaikan manual jika dibutuhkan."
                        size="sm"
                    >
                        <HcisDatePicker
                            :model-value="nilai.Tanggal_Akhir_Approval"
                            placeholder="Pilih tanggal persetujuan tuntas"
                            size="sm"
                            clearable
                            :disabled="terkunci"
                            class="w-100"
                            @update:model-value="ubah('Tanggal_Akhir_Approval', $event)"
                        />
                    </HcisField>
                </div>
            </div>
        </HcisCard>

        <!-- ══ 3. CARD MATRIKS PENGESAHAN & TANDA TANGAN ══ -->
        <HcisCard
            v-if="pakaiPengesahan"
            title="Matriks Pejabat Pengesah &amp; Tanda Tangan"
            description="Daftar pejabat struktural yang menandatangani dan mengesahkan versi dokumen ini beserta perannya (Dibuat / Diperiksa / Disetujui)."
            icon="people-fill"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Pengesah')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <!-- Kuncinya WAJIB diteruskan. Komponen ini tak menyimpulkan
                 apa pun sendiri; tanpa baris ini blok penanda tangan versi
                 arsip dan riwayat tetap bisa diubah di layar. -->
            <SopPengesahPicker
                v-if="Array.isArray(pengesah)"
                :rows="pengesah"
                :terkunci="mati('Pengesah')"
                basis="/api/v1/sop/admin/dokumen"
            />

            <template #footer>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small" style="font-size: 0.75rem;">
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-info-circle-fill text-primary"></i>
                        <span>Minimal <strong>1 pejabat pengesah</strong> dibutuhkan untuk melanjutkan ke tahap Pemberkasan.</span>
                    </div>
                    <span>Tanda tangan basah tetap dibubuhkan pada lembar fisik dokumen.</span>
                </div>
            </template>
        </HcisCard>
    </div>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import HcisField from '@/components/ui/HcisField.vue';
import SopPengesahPicker from '@/Pages/LMS/SOP/Shared/components/ui/SopPengesahPicker.vue';

/**
 * Langkah 3 — Pengesahan.
 */
export default {
    name: 'LangkahPengesahan',
    components: {
        HcisCard,
        HcisBadge,
        HcisAlert,
        HcisDatePicker,
        HcisField,
        SopPengesahPicker,
    },
    props: {
        modelValue: { type: Object, required: true },
        terkunci: { type: Boolean, default: false },
        /* Izin per field dari server. Baku "boleh" supaya langkah yang belum
           menerima muatannya tak mengunci apa pun — mengunci saat ragu membuat
           halaman yang lambat memuat terlihat rusak. */
        bolehUbah: { type: Function, default: () => true },
        berubah: { type: Array, default: () => [] },
        pakaiPengesahan: { type: Boolean, default: true },
        tipe: { type: String, default: 'DOKUMEN' },
    },
    emits: ['update:modelValue'],
    computed: {
        nilai() {
            return this.modelValue || {};
        },
        pengesah() {
            return this.nilai.Pengesah;
        },
    },
    created() {
        if (!Array.isArray(this.nilai.Pengesah)) {
            this.$emit('update:modelValue', { ...this.nilai, Pengesah: [] });
        }
    },
    methods: {
        /** Terkunci karena tahapnya, ATAU karena keadaan versi tak mengizinkan field ini. */
        mati(field) {
            return this.terkunci || !this.bolehUbah(field);
        },
        ditandai(...kunci) {
            return kunci.some((k) => this.berubah.includes(k));
        },
        ubah(kunci, v) {
            this.$emit('update:modelValue', { ...this.nilai, [kunci]: v });
        },
    },
};
</script>
