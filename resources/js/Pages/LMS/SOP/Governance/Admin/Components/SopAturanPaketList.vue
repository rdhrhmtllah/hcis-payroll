<template>
    <div class="sop-aturan-paket-list">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3 flex-wrap">
            <div>
                <h6 class="text-xs uppercase font-extrabold text-brand-700 tracking-wider mb-1">
                    1. Paket Dokumen yang Diwajibkan
                </h6>
                <small class="text-slate-500">
                    Satu aturan boleh memuat beberapa paket. Paket yang sudah dimiliki aturan lain
                    tidak muncul di pilihan.
                </small>
            </div>
            <HcisBadge :label="`${modelValue.length} paket`" variant="brand" size="xs" />
        </div>

        <HcisField id="sop-tambah-paket" label="Tambah Paket">
            <HcisSelect
                model-value=""
                placeholder="Pilih paket dokumen published…"
                filterable
                :disabled="disabled || !options.length"
                @update:model-value="tambah"
            >
                <HcisSelectOption
                    v-for="pkg in options"
                    :key="pkg.Id_SOP_Paket"
                    :value="pkg.Id_SOP_Paket"
                    :label="pkg.Nama_Paket"
                    :badge="pkg.Mode_Distribusi === 'PUBLIK' ? 'Publik' : 'Terbatas'"
                    :badge-variant="pkg.Mode_Distribusi === 'PUBLIK' ? 'success' : 'warning'"
                />
            </HcisSelect>
        </HcisField>

        <HcisEmptyState
            v-if="!modelValue.length"
            title="Belum Ada Paket"
            description="Aturan belum memuat paket dokumen mana pun. Tambahkan minimal satu."
            icon="box-seam"
            size="sm"
            class="mt-3"
        />

        <div v-else class="d-flex flex-column gap-2 mt-3">
            <HcisCard
                v-for="(baris, index) in modelValue"
                :key="baris.Id_SOP_Paket"
                variant="flat"
                sentiment="neutral"
                size="sm"
                divider
                class="sop-paket-baris"
            >
                <template #header>
                    <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <HcisBadge :label="`#${index + 1}`" variant="brand" size="xs" />
                            <strong class="text-truncate text-slate-900" style="font-size: 0.875rem;">
                                {{ baris.Nama_Paket }}
                            </strong>
                            <HcisBadge
                                :label="baris.Mode_Distribusi === 'PUBLIK' ? 'Publik' : 'Terbatas'"
                                :variant="baris.Mode_Distribusi === 'PUBLIK' ? 'success' : 'warning'"
                                size="xs"
                                appearance="subtle"
                            />
                            <HcisBadge
                                v-if="baris.Last_Apply_Status === 'FAILED' || baris.Last_Apply_Status === 'NEEDS_ATTENTION'"
                                :label="baris.Last_Apply_Status === 'FAILED' ? 'Gagal' : 'Perlu Perhatian'"
                                variant="danger"
                                size="xs"
                                :title="baris.Last_Apply_Error || ''"
                            />
                        </div>

                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-up"
                                :disabled="disabled || index === 0"
                                :title="`Naikkan ${baris.Nama_Paket}`"
                                :aria-label="`Naikkan urutan ${baris.Nama_Paket}`"
                                @click="geser(index, -1)"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-down"
                                :disabled="disabled || index === modelValue.length - 1"
                                :title="`Turunkan ${baris.Nama_Paket}`"
                                :aria-label="`Turunkan urutan ${baris.Nama_Paket}`"
                                @click="geser(index, 1)"
                            />
                            <HcisButton
                                variant="danger-ghost"
                                size="xs"
                                leading-icon="trash3"
                                :disabled="disabled"
                                :data-uji="`buang-${baris.Id_SOP_Paket}`"
                                :title="`Buang ${baris.Nama_Paket}`"
                                :aria-label="`Buang paket ${baris.Nama_Paket}`"
                                @click="buang(index)"
                            />
                        </div>
                    </div>
                </template>

                <!-- Override adalah PENGGANTIAN utuh setelan induk, bukan tambahan di
                     atasnya: mode NONE di sini berarti "paket ini tanpa tenggat"
                     walaupun setelan induknya bertenggat. -->
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="text-xs text-slate-600">
                        {{ baris.override ? 'Memakai tenggat sendiri' : 'Mengikuti tenggat induk' }}
                    </span>
                    <HcisSwitch
                        :model-value="!!baris.override"
                        :disabled="disabled"
                        active-text="Tenggat sendiri"
                        :aria-label="`Pakai tenggat sendiri untuk ${baris.Nama_Paket}`"
                        @update:model-value="setOverride(index, $event)"
                    />
                </div>

                <div v-if="baris.override && baris.schedule" data-uji="setelan-override" class="row g-2 mt-2">
                    <div class="col-12 col-md-4">
                        <HcisField :id="`ov-mode-${index}`" label="Mode Tenggat">
                            <HcisSelect
                                :model-value="baris.schedule.deadline_mode"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'deadline_mode', $event)"
                            >
                                <HcisSelectOption value="NONE" label="Tanpa tenggat" />
                                <HcisSelectOption value="RELATIVE" label="Sekian hari sejak ditugaskan" />
                                <HcisSelectOption value="ABSOLUTE" label="Tanggal tertentu" />
                            </HcisSelect>
                        </HcisField>
                    </div>

                    <div v-if="baris.schedule.deadline_mode === 'RELATIVE'" class="col-12 col-md-3">
                        <HcisField :id="`ov-hari-${index}`" label="Jumlah Hari">
                            <HcisInputNumber
                                :model-value="baris.schedule.deadline_days"
                                :min="1"
                                :max="3650"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'deadline_days', $event)"
                            />
                        </HcisField>
                    </div>

                    <div v-if="baris.schedule.deadline_mode === 'ABSOLUTE'" class="col-12 col-md-3">
                        <HcisField :id="`ov-tgl-${index}`" label="Tanggal Tenggat">
                            <HcisDatePicker
                                :model-value="baris.schedule.deadline_date"
                                :disabled="disabled"
                                value-format="YYYY-MM-DD"
                                @update:model-value="ubahJadwal(index, 'deadline_date', $event)"
                            />
                        </HcisField>
                    </div>

                    <div class="col-12 col-md-3">
                        <HcisField :id="`ov-rem-${index}`" label="Pengingat">
                            <HcisSwitch
                                :model-value="!!baris.schedule.reminder_enabled"
                                :disabled="disabled"
                                active-text="Aktif"
                                inactive-text="Mati"
                                :aria-label="`Kirim pengingat untuk ${baris.Nama_Paket}`"
                                @update:model-value="ubahJadwal(index, 'reminder_enabled', $event)"
                            />
                        </HcisField>
                    </div>

                    <div v-if="baris.schedule.reminder_enabled" class="col-12 col-md-2">
                        <HcisField :id="`ov-irama-${index}`" label="Tiap (hari)">
                            <HcisInputNumber
                                :model-value="baris.schedule.reminder_interval_days"
                                :min="1"
                                :max="365"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'reminder_interval_days', $event)"
                            />
                        </HcisField>
                    </div>
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDatePicker,
    HcisEmptyState,
    HcisField,
    HcisInputNumber,
    HcisSelect,
    HcisSelectOption,
    HcisSwitch,
} from '@/components/ui';

const jadwalBaku = () => ({
    deadline_mode: 'NONE',
    deadline_days: 7,
    deadline_date: null,
    reminder_enabled: false,
    reminder_interval_days: 3,
});

export default {
    name: 'SopAturanPaketList',
    components: {
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDatePicker,
        HcisEmptyState,
        HcisField,
        HcisInputNumber,
        HcisSelect,
        HcisSelectOption,
        HcisSwitch,
    },
    props: {
        /** [{ Id_SOP_Paket, Nama_Paket, Mode_Distribusi, Urutan, override, schedule }] */
        modelValue: { type: Array, default: () => [] },
        /** Paket published yang masih boleh diklaim aturan ini. */
        options: { type: Array, default: () => [] },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    methods: {
        /* Urutan selalu ditulis ulang dari posisi baris, bukan dipercayakan ke
           nilai yang datang: dua paket ber-Urutan sama membuat urutan apply
           berubah-ubah antar pembacaan. */
        pancarkan(daftar) {
            this.$emit(
                'update:modelValue',
                daftar.map((p, i) => ({ ...p, Urutan: i + 1 })),
            );
        },
        tambah(idPaket) {
            if (!idPaket) return;
            const pkg = this.options.find((p) => p.Id_SOP_Paket === idPaket);
            if (!pkg) return;

            this.pancarkan([
                ...this.modelValue,
                {
                    Id_SOP_Paket: pkg.Id_SOP_Paket,
                    Nama_Paket: pkg.Nama_Paket,
                    Mode_Distribusi: pkg.Mode_Distribusi,
                    Urutan: this.modelValue.length + 1,
                    override: false,
                    schedule: null,
                },
            ]);
        },
        buang(index) {
            const daftar = [...this.modelValue];
            daftar.splice(index, 1);
            this.pancarkan(daftar);
        },
        geser(index, arah) {
            const tujuan = index + arah;
            if (tujuan < 0 || tujuan >= this.modelValue.length) return;
            const daftar = [...this.modelValue];
            [daftar[index], daftar[tujuan]] = [daftar[tujuan], daftar[index]];
            this.pancarkan(daftar);
        },
        setOverride(index, nyala) {
            const daftar = [...this.modelValue];
            daftar[index] = {
                ...daftar[index],
                override: !!nyala,
                /* Salinan baru tiap kali dinyalakan. Satu objek jadwal yang
                   dipakai bersama membuat dua paket saling mengubah tenggat
                   tanpa satu pun sentuhan pada baris yang lain. */
                schedule: nyala ? { ...(daftar[index].schedule || jadwalBaku()) } : null,
            };
            this.pancarkan(daftar);
        },
        ubahJadwal(index, kunci, nilai) {
            const daftar = [...this.modelValue];
            daftar[index] = {
                ...daftar[index],
                schedule: { ...(daftar[index].schedule || jadwalBaku()), [kunci]: nilai },
            };
            this.pancarkan(daftar);
        },
    },
};
</script>

<style scoped>
.sop-aturan-paket-list .sop-paket-baris {
    transition: box-shadow var(--hcis-duration-fast, 150ms) ease;
}

.sop-aturan-paket-list .sop-paket-baris:hover {
    box-shadow: var(--hcis-shadow-sm, 0 1px 3px rgba(15, 23, 42, 0.08));
}

.sop-aturan-paket-list .min-w-0 {
    min-width: 0;
}
</style>
