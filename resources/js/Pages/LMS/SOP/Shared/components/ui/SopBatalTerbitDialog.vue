<template>
    <!-- MEMBATALKAN VERSI YANG SEDANG BERLAKU.
         Dua hal harus terbaca sebelum admin menekan apa pun: bahwa versi ini
         akan dinonaktifkan, dan versi mana yang akan menggantikannya. Dialog
         yang hanya bertanya "yakin?" memindahkan pekerjaan menimbang ke orang
         yang tak diberi bahannya. -->
    <SopModal
        :show="!!versi"
        title="Batalkan Versi yang Sedang Berlaku?"
        :subtitle="versi ? `${labelVersi(versi.Versi_Ke)} akan dinonaktifkan dan nomornya dilepas` : ''"
        icon="bi-x-octagon"
        size="md"
        @close="$emit('close')"
    >
        <div v-if="memuat" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <template v-else>
            <!-- Ditolak: sebabnya disebut, bukan tombolnya dimatikan diam-diam. -->
            <HcisAlert
                v-if="!bolehBatal"
                variant="danger"
                appearance="soft"
                icon="shield-exclamation"
                title="Versi ini tak bisa dibatalkan lagi"
                size="sm"
            >
                Versi ini sudah sampai ke karyawan{{ kurang.length ? ` (${kurang.join(', ')})` : '' }}.
                Membatalkannya berarti menyatakan dokumen yang sudah dibaca orang tak pernah terbit.
                Untuk memperbaikinya, terbitkan revisi pengganti.
            </HcisAlert>

            <template v-else>
                <!-- PERINGATAN 1 — apa yang terjadi pada versi ini. -->
                <HcisAlert
                    variant="warning"
                    appearance="soft"
                    icon="exclamation-triangle-fill"
                    title="Versi ini akan dinonaktifkan"
                    size="sm"
                    class="mb-3"
                >
                    {{ labelVersi(versi.Versi_Ke) }} berhenti berlaku dan tersimpan sebagai arsip —
                    tak bisa disunting maupun dibangkitkan lagi. Nomornya dilepas, sehingga versi
                    berikutnya bisa memakai <b>{{ labelVersi(versi.Versi_Ke) }}</b> kembali.
                </HcisAlert>

                <!-- PERINGATAN 2 — apa yang berlaku sesudahnya. -->
                <div class="bt-pilih">
                    <div class="bt-judul">
                        <i class="bi bi-arrow-counterclockwise text-primary"></i>
                        <span>Versi mana yang berlaku sesudah ini?</span>
                    </div>

                    <p v-if="!kandidat.length" class="bt-kosong">
                        Tak ada versi lain yang bisa dikembalikan. Dokumen ini akan berakhir
                        <b>tanpa versi yang berlaku</b> sampai Anda menerbitkan versi baru.
                    </p>

                    <ul v-else class="bt-daftar">
                        <li v-for="k in kandidat" :key="k.Id_SOP_Item_Versi">
                            <label class="bt-opsi" :class="{ on: pilihan === k.Id_SOP_Item_Versi }">
                                <input v-model="pilihan" type="radio" :value="k.Id_SOP_Item_Versi" />
                                <span>
                                    <b>{{ labelVersi(k.Versi_Ke) }}</b>
                                    <em>
                                        Berlaku {{ k.Tanggal_Berlaku || '—' }}
                                        <template v-if="k.Disahkan_Oleh"> · disahkan {{ k.Disahkan_Oleh }}</template>
                                    </em>
                                </span>
                            </label>
                        </li>
                        <li>
                            <label class="bt-opsi" :class="{ on: pilihan === null }">
                                <input v-model="pilihan" type="radio" :value="null" />
                                <span>
                                    <b>Tidak ada</b>
                                    <em>Dokumen dibiarkan tanpa versi berlaku sampai ada versi baru.</em>
                                </span>
                            </label>
                        </li>
                    </ul>
                </div>
            </template>
        </template>

        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100">
                <HcisButton variant="secondary" label="Batal" :disabled="mengirim" @click="$emit('close')" />
                <HcisButton
                    v-if="bolehBatal"
                    variant="danger"
                    leading-icon="x-octagon"
                    :loading="mengirim"
                    :disabled="mengirim || memuat"
                    label="Ya, Batalkan Versi Ini"
                    @click="kirim"
                />
            </div>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import SopModal from './SopModal.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import { hcisMessage } from '@/components/ui';
import { labelVersi } from '@/Pages/LMS/SOP/Shared/support/versi.js';

const API = '/api/v1/sop/admin/dokumen';

export default {
    name: 'SopBatalTerbitDialog',
    components: { SopModal, HcisButton, HcisAlert },
    props: {
        /** Baris versi yang sedang berlaku; null = dialog tertutup. */
        versi: { type: Object, default: null },
    },
    emits: ['close', 'berhasil'],
    data() {
        return { memuat: false, mengirim: false, bolehBatal: false, kurang: [], kandidat: [], pilihan: null };
    },
    watch: {
        versi: {
            immediate: true,
            handler(v) {
                if (v) this.muat();
            },
        },
    },
    methods: {
        labelVersi,
        /**
         * Dampaknya diambil SEBELUM dialog bisa ditekan.
         *
         * Kandidat dan penolakannya dijawab server — layar tak menyimpulkan
         * "boleh atau tidak" dari status, sebab yang menentukan adalah apakah
         * versinya sudah sampai ke orang, dan itu hanya diketahui basis data.
         */
        async muat() {
            this.memuat = true;
            this.bolehBatal = false;
            this.kandidat = [];
            this.pilihan = null;
            try {
                const r = await axios.get(`${API}/versi/${this.versi.Id_SOP_Item_Versi}/batal-terbit`);
                const d = r.data?.result || {};
                this.bolehBatal = d.Boleh_Batal === true;
                this.kurang = d.Kurang || [];
                this.kandidat = d.Kandidat || [];
                /* Yang terbaru dipilih lebih dulu — itu yang paling mungkin
                   dimaksud, dan admin tetap bisa memindahkannya. */
                this.pilihan = this.kandidat.length ? this.kandidat[0].Id_SOP_Item_Versi : null;
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal memeriksa dampak pembatalan.');
                this.$emit('close');
            } finally {
                this.memuat = false;
            }
        },
        async kirim() {
            this.mengirim = true;
            try {
                const r = await axios.post(
                    `${API}/versi/${this.versi.Id_SOP_Item_Versi}/batal-terbit`,
                    { Id_Pengganti: this.pilihan },
                );
                hcisMessage.success(r.data?.message || 'Versi dibatalkan.');
                this.$emit('berhasil');
                this.$emit('close');
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal membatalkan versi.');
            } finally {
                this.mengirim = false;
            }
        },
    },
};
</script>

<style scoped>
.bt-pilih {
    padding: 0.75rem 0.85rem;
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.bt-judul {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin-bottom: 0.6rem;
}

.bt-kosong {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.55;
    color: var(--hcis-warning-700, var(--hcis-amber-700));
}

.bt-daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.bt-opsi {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.5rem 0.6rem;
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 10px);
    background: var(--hcis-surface, var(--hcis-white));
    cursor: pointer;
}

.bt-opsi.on {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: inset 3px 0 0 0 var(--hcis-brand-500, var(--hcis-indigo-500));
}

.bt-opsi b {
    display: block;
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.bt-opsi em {
    display: block;
    font-style: normal;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}
</style>
