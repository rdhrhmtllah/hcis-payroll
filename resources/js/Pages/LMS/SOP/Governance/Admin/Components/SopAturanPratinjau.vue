<template>
    <HcisCard
        variant="glass"
        sentiment="brand"
        size="md"
        title="Pratinjau Dampak"
        description="Dihitung dari data karyawan terkini, sebelum satu penugasan pun dibuat."
        icon="eye"
        class="mb-4 sop-aturan-pratinjau"
    >
        <template #badge>
            <HcisBadge label="Data Real-Time" variant="brand" size="xs" />
        </template>

        <!-- Menutup kebingungan yang selama ini tak pernah dijelaskan layar:
             kolom Dicabut selalu nol selama saklar auto-release mati, dan tak
             satu pun kalimat memberi tahu di mana saklarnya berada. -->
        <HcisAlert
            v-if="!preview.auto_release"
            variant="info"
            title="Pencabutan otomatis sedang mati"
            message="Kolom Dicabut karena itu selalu nol, dan angkanya masuk ke kolom Ditahan. Saklarnya ada di halaman Set Up System."
            icon="info-circle-fill"
            class="mb-3"
        />

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 sop-pratinjau-tabel">
                <thead>
                    <tr class="text-xs text-slate-500 uppercase">
                        <th scope="col">Paket Dokumen</th>
                        <th scope="col" class="text-end">Cocok</th>
                        <th scope="col" class="text-end">Baru</th>
                        <th scope="col" class="text-end">Dipertahankan</th>
                        <th scope="col" class="text-end">Tercakup</th>
                        <th scope="col" class="text-end">Dicabut</th>
                        <th scope="col" class="text-end">Ditahan</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="baris in preview.paket" :key="baris.Id_SOP_Paket || baris.Nama_Paket">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-truncate">{{ baris.Nama_Paket }}</span>
                                <HcisBadge
                                    v-if="baris.tertahan_tenggat"
                                    data-uji="tertahan-tenggat"
                                    label="Tertahan"
                                    variant="danger"
                                    size="xs"
                                    title="Tenggat absolut sudah lewat — paket ini tidak akan menugaskan siapa pun sampai tanggalnya diperbaiki."
                                />
                            </div>
                        </td>
                        <td class="text-end font-semibold">{{ baris.matched }}</td>
                        <td class="text-end font-semibold text-success-700">+{{ baris.new }}</td>
                        <td class="text-end">{{ baris.kept }}</td>
                        <td class="text-end">{{ baris.covered }}</td>
                        <td class="text-end text-warning-700">-{{ baris.release }}</td>
                        <td class="text-end text-danger-700">{{ baris.withheld }}</td>
                    </tr>
                </tbody>

                <tfoot>
                    <!-- Kolom Cocok pada baris TOTAL adalah jumlah ORANG, dan
                         sengaja TIDAK dijumlahkan lintas paket: cohort-nya sama
                         untuk setiap paket, jadi penjumlahan akan memberi
                         kelipatan headcount. Kolom sisanya adalah jumlah
                         PENUGASAN, dan memang dijumlahkan. -->
                    <tr data-uji="pratinjau-total" class="border-top font-extrabold">
                        <td>
                            TOTAL
                            <small class="d-block text-slate-500 font-normal" style="font-size: 0.6875rem;">
                                Cocok = jumlah orang · sisanya = jumlah penugasan
                            </small>
                        </td>
                        <td class="text-end">{{ preview.total.matched }}</td>
                        <td class="text-end text-success-700">+{{ preview.total.new }}</td>
                        <td class="text-end">{{ preview.total.kept }}</td>
                        <td class="text-end">{{ preview.total.covered }}</td>
                        <td class="text-end text-warning-700">-{{ preview.total.release }}</td>
                        <td class="text-end text-danger-700">{{ preview.total.withheld }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </HcisCard>
</template>

<script>
import { HcisAlert, HcisBadge, HcisCard } from '@/components/ui';

export default {
    name: 'SopAturanPratinjau',
    components: { HcisAlert, HcisBadge, HcisCard },
    props: {
        /** { paket: [...], total: {...}, auto_release: bool } */
        preview: {
            type: Object,
            required: true,
            validator: (v) => Array.isArray(v?.paket) && typeof v?.total === 'object',
        },
    },
};
</script>

<style scoped>
.sop-aturan-pratinjau .sop-pratinjau-tabel tfoot td {
    border-top: 2px solid var(--hcis-color-border-strong, var(--hcis-slate-300));
}
</style>
