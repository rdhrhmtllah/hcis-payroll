<template>
    <HcisDrawer
        :model-value="true"
        direction="right"
        size="lg"
        :title="detail.nama || detail.kode_karyawan"
        :description="`NIK ${detail.nik_karyawan || detail.kode_karyawan}${detail.dikirim_pada ? ` · dikirim ${detail.dikirim_pada}` : ''}`"
        icon="bi bi-person-vcard"
        @close="$emit('tutup')"
    >
        <HcisEmptyState
            v-if="!detail.ada"
            icon="bi bi-inbox"
            title="Karyawan ini belum mengirimkan data"
        />

        <template v-else>
            <HcisAlert
                variant="info"
                appearance="soft"
                icon="bi bi-info-circle-fill"
                class="pdet__petunjuk"
            >
                Field bertanda <strong>berubah</strong> berbeda dari data di sistem induk. Field tanpa tanda
                memang tidak punya padanan di sana.
            </HcisAlert>

            <HcisCard
                v-for="l in skema.langkah"
                :key="l.kunci"
                :title="l.judul"
                icon="bi bi-folder2-open"
                size="sm"
                divider
                class="pdet__grup"
            >
                <dl class="pdet__daftar">
                    <div v-for="kunci in fieldTampil(l)" :key="kunci" class="pdet__baris">
                        <dt>{{ skema.field[kunci]?.label ?? kunci }}</dt>
                        <dd>
                            <span class="pdet__nilai">{{ tampil(kunci) }}</span>
                            <HcisBadge
                                v-if="detail.perbedaan[kunci]"
                                label="berubah"
                                variant="warning"
                                size="xs"
                            />
                            <span v-if="detail.perbedaan[kunci]" class="pdet__lama">
                                sebelumnya: {{ detail.lama[kunci] || '—' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </HcisCard>

            <HcisCard
                v-if="detail.riwayat.length > 1"
                title="Riwayat kiriman"
                icon="bi bi-clock-history"
                size="sm"
                divider
                class="pdet__grup"
            >
                <ul class="pdet__riwayat">
                    <li v-for="r in detail.riwayat" :key="r.id">
                        <HcisBadge
                            :label="r.aktif ? 'Berlaku' : 'Riwayat'"
                            :variant="r.aktif ? 'success' : 'neutral'"
                            size="xs"
                        />
                        <span class="pdet__riwayat-waktu">{{ r.dikirim_pada }}</span>
                    </li>
                </ul>
            </HcisCard>
        </template>
    </HcisDrawer>
</template>

<script setup>
import { HcisAlert, HcisBadge, HcisCard, HcisDrawer, HcisEmptyState } from '@/components/ui';
import { onMounted, ref } from 'vue';
import { useScrollLock } from '@/components/ui/useScrollLock';
import { kunciTampil } from '@/composables/ESS/Employee/DataCollection/formatBidang.js';

const props = defineProps({
    detail: { type: Object, required: true },
    skema: { type: Object, required: true },
});

defineEmits(['tutup']);

const panel = ref(null);
const idJudul = 'pdet-judul';
const { kunci } = useScrollLock();

onMounted(() => {
    kunci();
    panel.value?.focus();
});

/**
 * Pertanyaan bersyarat disaring memakai JAWABAN kiriman ini, bukan katalog
 * penuh: baris 'Jumlah Tanggungan — —' pada karyawan yang belum menikah
 * terbaca seperti data yang hilang, padahal ia memang tidak pernah ditanyai.
 */
const fieldTampil = (langkah) => kunciTampil(langkah.field, props.skema.field, props.detail.jawaban);

const tampil = (k) => {
    const v = props.detail.jawaban[k];
    if (v === undefined || v === null || String(v).trim() === '') return '—';

    return props.skema.field[k]?.label_pilihan?.[v] ?? v;
};
</script>

<style scoped>
.pdet__petunjuk,
.pdet__grup {
    margin-bottom: var(--hcis-space-4);
}

.pdet__daftar {
    display: grid;
    gap: var(--hcis-space-3);
    margin: 0;
}

.pdet__baris {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
    gap: var(--hcis-space-3);
    align-items: baseline;
}

.pdet__baris dt {
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
}

.pdet__baris dd {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--hcis-space-2);
    margin: 0;
}

.pdet__nilai {
    font-size: var(--hcis-text-sm);
    font-weight: 600;
    color: var(--hcis-text-strong);
    overflow-wrap: anywhere;
}

.pdet__lama {
    flex-basis: 100%;
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
}

.pdet__riwayat {
    display: grid;
    gap: var(--hcis-space-2);
    margin: 0;
    padding: 0;
    list-style: none;
}

.pdet__riwayat li {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.pdet__riwayat-waktu {
    font-size: var(--hcis-text-xs);
    color: var(--hcis-text-muted);
}

@media (max-width: 34rem) {
    .pdet__baris {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--hcis-space-1);
    }
}
</style>
