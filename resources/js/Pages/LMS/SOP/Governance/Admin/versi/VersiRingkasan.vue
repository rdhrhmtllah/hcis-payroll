<template>
    <HcisCard
        title="Ringkasan Perjalanan Versi"
        description="Pantau progres dan kelengkapan segmen versi operasional ini."
        icon="clipboard-data"
        variant="outlined"
        sentiment="primary"
        size="sm"
    >
        <div class="versi-ringkasan">
            <!-- 1. Header Passport -->
            <div class="versi-ringkasan__hero">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1.5 mb-1.5">
                    <span class="versi-ringkasan__nomor">{{ labelVersi }}</span>
                    <HcisBadge
                        v-if="tahap?.Nama_Tahap"
                        :label="tahap.Nama_Tahap"
                        :variant="resolvedTahapVariant"
                        appearance="solid"
                        size="xs"
                    />
                </div>
                <!-- Nomor GABUNGAN, sama persis dengan kepala halaman. Baris ini
                     dulu jatuh ke No_Dokumen — satu segmen dari tujuh — sehingga
                     dua tempat di halaman yang sama menyebut nomor berbeda untuk
                     satu dokumen. -->
                <div
                    v-if="nomorDokumen"
                    class="font-monospace text-primary fw-bold text-xs mb-0.5 text-truncate"
                    :title="nomorDokumen"
                >
                    <i class="bi bi-hash"></i>{{ nomorDokumen }}
                </div>
                <div v-if="dokumen?.Judul" class="text-xs text-slate-600 text-truncate fw-medium" :title="dokumen.Judul">
                    {{ dokumen.Judul }}
                </div>
            </div>

            <!-- 2. Progress Indicator -->
            <div class="versi-ringkasan__progress-box">
                <div class="d-flex align-items-center justify-content-between text-xs mb-1.5">
                    <span class="fw-bold text-slate-600">Progres Langkah</span>
                    <span class="fw-bold text-primary">{{ langkahSelesaiCount }} / {{ langkah.length }} Selesai</span>
                </div>
                <div class="progress" style="height: 6px; border-radius: 999px; background: #e2e8f0;">
                    <div
                        class="progress-bar bg-primary"
                        role="progressbar"
                        :style="{ width: `${progressPercent}%`, borderRadius: '999px', transition: 'width 0.3s ease' }"
                        :aria-valuenow="progressPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
            </div>

            <!-- 3. Step Checklist Items -->
            <div v-if="memuat && !langkah.length" class="d-flex flex-column gap-2 py-1">
                <div v-for="n in 4" :key="n" class="d-flex align-items-center gap-2 p-2 rounded-2 border bg-light">
                    <HcisSkeleton width="18px" height="18px" rounded="full" />
                    <div class="d-flex flex-column gap-1 flex-grow-1">
                        <HcisSkeleton width="55%" height="0.8rem" rounded="sm" />
                        <HcisSkeleton width="35%" height="0.65rem" rounded="sm" />
                    </div>
                </div>
            </div>

            <ul v-else class="versi-ringkasan__daftar">
                <li
                    v-for="l in langkah"
                    :key="l.Nomor"
                    class="versi-ringkasan__item"
                    :class="{
                        'is-complete': l.Lengkap,
                        'is-clickable': !l.Terkunci,
                        'is-locked': l.Terkunci,
                    }"
                    :role="!l.Terkunci ? 'button' : undefined"
                    :tabindex="!l.Terkunci ? 0 : undefined"
                    @click="!l.Terkunci && $emit('pilih', l.Nomor)"
                    @keydown.enter="!l.Terkunci && $emit('pilih', l.Nomor)"
                >
                    <div class="versi-ringkasan__item-icon">
                        <i
                            v-if="l.Lengkap"
                            class="bi bi-check-circle-fill text-success"
                        ></i>
                        <i
                            v-else-if="l.Terkunci"
                            class="bi bi-lock-fill text-slate-400"
                        ></i>
                        <i
                            v-else-if="l.Keadaan === 'berjalan'"
                            class="bi bi-play-circle-fill text-primary"
                        ></i>
                        <i
                            v-else
                            class="bi bi-dash-circle text-slate-400"
                        ></i>
                    </div>
                    <div class="versi-ringkasan__item-content">
                        <div class="d-flex align-items-center justify-content-between gap-1">
                            <span class="versi-ringkasan__nama">{{ l.Nama }}</span>
                            <span v-if="l.Lengkap" class="text-success text-xs fw-bold"><i class="bi bi-check"></i></span>
                        </div>
                        <small class="versi-ringkasan__sub">{{ l.Ringkasan || (l.Terkunci ? 'Terkunci' : 'Belum diisi') }}</small>
                    </div>
                </li>
            </ul>

            <!-- 4. Helper Note -->
            <p class="versi-ringkasan__nota">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Setiap perubahan disimpan otomatis saat melangkah ke tahap berikutnya.
            </p>
        </div>

        <template #footer>
            <HcisButton
                variant="ghost"
                size="xs"
                leading-icon="arrow-left"
                label="Kembali ke Master Dokumen"
                class="w-100 justify-content-center"
                @click="$emit('riwayat')"
            />
        </template>
    </HcisCard>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisSkeleton from '@/components/ui/HcisSkeleton.vue';
import { nomorDokumenTampil } from '@/Pages/LMS/SOP/Shared/support/versi.js';

export default {
    name: 'VersiRingkasan',
    components: { HcisCard, HcisBadge, HcisButton, HcisSkeleton },
    props: {
        versiKe: { type: [Number, String], default: null },
        langkah: { type: Array, default: () => [] },
        tahap: { type: Object, default: null },
        baru: { type: Boolean, default: false },
        dokumen: { type: Object, default: () => ({}) },
        memuat: { type: Boolean, default: false },
    },
    emits: ['riwayat', 'pilih'],
    computed: {
        nomorDokumen() {
            return nomorDokumenTampil(this.dokumen);
        },
        labelVersi() {
            if (this.versiKe !== null && this.versiKe !== undefined && this.versiKe !== '') {
                return `v${String(this.versiKe).padStart(2, '0')}${this.baru ? ' (Draft Baru)' : ''}`;
            }
            if (this.baru) return 'Versi Baru';

            return 'Versi —';
        },
        langkahSelesaiCount() {
            return (this.langkah || []).filter((l) => l.Lengkap).length;
        },
        progressPercent() {
            if (!this.langkah || !this.langkah.length) return 0;
            return Math.round((this.langkahSelesaiCount / this.langkah.length) * 100);
        },
        resolvedTahapVariant() {
            const nama = (this.tahap?.Nama_Tahap || '').toUpperCase();
            if (nama.includes('TERBIT') || nama.includes('PUBLISH') || nama.includes('SELESAI')) return 'success';
            if (nama.includes('REVIEW')) return 'purple';
            if (nama.includes('PENGESAHAN') || nama.includes('APPROV')) return 'teal';
            if (nama.includes('BATAL') || nama.includes('CANCEL')) return 'danger';
            if (nama.includes('DRAFT') || nama.includes('PENGAJUAN')) return 'brand';
            return 'brand';
        },
    },
};
</script>

<style scoped>
.versi-ringkasan {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.versi-ringkasan__hero {
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid var(--hcis-slate-200);
}

.versi-ringkasan__nomor {
    font-size: 0.9375rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.versi-ringkasan__progress-box {
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-100);
}

.versi-ringkasan__daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.versi-ringkasan__item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-100);
    transition: all 0.15s ease;
}

.versi-ringkasan__item.is-clickable {
    cursor: pointer;
}

.versi-ringkasan__item.is-clickable:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    transform: translateX(2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
}

.versi-ringkasan__item.is-complete {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-100);
}

.versi-ringkasan__item.is-locked {
    opacity: 0.6;
    cursor: not-allowed;
}

.versi-ringkasan__item-icon {
    font-size: 0.95rem;
    line-height: 1;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.versi-ringkasan__item-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex-grow: 1;
}

.versi-ringkasan__nama {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800);
    line-height: 1.25;
}

.versi-ringkasan__sub {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    line-height: 1.35;
    margin-top: 0.1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.versi-ringkasan__nota {
    margin: 0;
    font-size: 0.6875rem;
    color: var(--hcis-slate-500);
    line-height: 1.4;
    padding: 0.25rem 0.1rem;
}
</style>
