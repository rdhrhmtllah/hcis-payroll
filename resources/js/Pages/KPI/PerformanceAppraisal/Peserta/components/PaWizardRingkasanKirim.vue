<template>
    <section class="pa-ringkasan" aria-label="Ringkasan dan Pengiriman Evaluasi 360°">
        <!-- Alert Sukses Jika Sudah Terkirim Semua -->
        <HcisAlert
            v-if="pengiriman?.sudah_terkirim_semua"
            variant="success"
            icon="patch-check-fill"
            title="Penilaian sudah terkirim"
            description="Terima kasih. Semua penilaian Anda sudah kami terima dan tidak bisa diubah lagi."
            class="pa-ringkasan-alert"
        />

        <!-- Alert Error Pengiriman -->
        <HcisAlert
            v-if="galatKirim"
            variant="danger"
            icon="exclamation-octagon-fill"
            :title="galatKirim.pesan || 'Pengiriman gagal'"
            :description="
                galatKirim.requestId ? `Kode: ${galatKirim.kode || '-'} · ID Permintaan: ${galatKirim.requestId}` : ''
            "
        />



        <!-- 3. Rekapitulasi Kategori Penilai via HcisCard -->
        <HcisCard
            title="Rekapitulasi Kategori Penilai"
            description="Pilih kategori untuk meninjau kembali lembar atau menyesuaikan kalibrasi skor sebelum pengiriman resmi."
            icon="diagram-3-fill"
            sentiment="brand"
            variant="glass"
            class="pa-ringkasan-rekap-card"
        >
            <template #actions>
                <HcisBadge
                    :label="`${totalKategoriTuntas} dari ${langkah.length} Kategori Tuntas`"
                    :variant="isSemuaKategoriTuntas ? 'success' : 'brand'"
                    appearance="glass"
                    size="xs"
                    :dot="isSemuaKategoriTuntas"
                />
            </template>

            <!-- List Category Breakdown -->
            <div class="pa-rekap-list" role="list">
                <div
                    v-for="l in langkah"
                    :key="l.kode_kategori"
                    class="pa-rekap-row"
                    :class="{ 'is-tuntas': l.status === 'TUNTAS' }"
                    role="listitem"
                >
                    <div class="pa-rekap-left">
                        <div class="pa-category-jewel" :class="categoryIconClass(l.kode_kategori)">
                            <HcisIcon :name="categoryIconName(l.kode_kategori)" size="xs" />
                        </div>

                        <div class="pa-rekap-info">
                            <div class="pa-rekap-title-row">
                                <strong class="pa-rekap-category-name">{{ l.nama_kategori }}</strong>
                                <HcisBadge
                                    :label="l.status === 'TUNTAS' ? 'Tuntas' : (l.status === 'TERKUNCI' ? 'Terkunci' : 'Sedang Diisi')"
                                    :variant="l.status === 'TUNTAS' ? 'success' : (l.status === 'TERKUNCI' ? 'neutral' : 'brand')"
                                    appearance="subtle"
                                    size="xs"
                                    :dot="l.status === 'TUNTAS'"
                                />
                                <HcisBadge
                                    v-if="l.boleh_kalibrasi"
                                    label="Dapat Dikalibrasi"
                                    variant="purple"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="sliders"
                                />
                            </div>

                            <div class="pa-rekap-meta">
                                <span class="pa-meta-sheet-count">
                                    {{ l.jumlah_terisi }} dari {{ l.jumlah_lembar }} lembar terisi
                                </span>
                                <span v-if="l.jumlah_terkalibrasi > 0" class="pa-meta-sep">·</span>
                                <span v-if="l.jumlah_terkalibrasi > 0" class="pa-meta-calib">
                                    {{ l.jumlah_terkalibrasi }} skor dikalibrasi
                                </span>
                            </div>

                            <!-- Progress Track Bar -->
                            <div class="pa-rekap-prog-track">
                                <div
                                    class="pa-rekap-prog-fill"
                                    :style="{ width: `${l.jumlah_lembar > 0 ? (l.jumlah_terisi / l.jumlah_lembar) * 100 : 0}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="pa-rekap-actions">
                        <!-- Kategori tuntas tanpa kalibrasi tidak punya apa pun untuk ditinjau di sini. -->
                        <HcisButton
                            v-if="!pengiriman?.sudah_terkirim_semua && (l.status !== 'TUNTAS' || l.boleh_kalibrasi)"
                            data-uji="buka-langkah"
                            :label="l.status === 'TUNTAS' ? 'Tinjau Kalibrasi' : 'Lengkapi Lembar'"
                            size="sm"
                            :variant="l.status === 'TUNTAS' ? 'secondary' : 'primary'"
                            :leading-icon="l.status === 'TUNTAS' ? 'sliders' : 'pencil-square'"
                            :disabled="l.status === 'TERKUNCI'"
                            :aria-label="`Buka tahapan ${l.nama_kategori}`"
                            @click="emit('buka', l.kode_kategori)"
                        />
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- 4. Disclaimer Alert: Pengiriman Bersifat Final -->
        <HcisAlert
            v-if="!pengiriman?.sudah_terkirim_semua"
            variant="warning"
            icon="shield-lock-fill"
            title="Penting: Pengiriman Bersifat Final"
            description="Setelah dikirim, seluruh skor penilaian tidak dapat diubah kembali dan akan segera digunakan dalam penghitungan hasil resmi. Pastikan Anda telah meninjau seluruh kalibrasi dengan seksama."
            class="pa-ringkasan-alert"
        />

        <!-- 5. Dedicated Action Submission Card via HcisCard -->
        <HcisCard
            v-if="!pengiriman?.sudah_terkirim_semua"
            title="Kirim Seluruh Evaluasi 360°"
            :description="`${pengiriman?.terkumpul || 0} dari ${pengiriman?.total || 0} lembar siap dikirim.`"
            icon="send-check-fill"
            sentiment="primary"
            variant="glass"
            pattern="rings"
            class="pa-ringkasan-action-card"
        >
            <!-- Kenapa tombol Kirim mati. Tanpa ini penilai melihat semua kategori tuntas, tombol
                 yang tidak bisa ditekan, dan tidak satu pun petunjuk. -->
            <p v-if="alasanBelumBisaKirim" class="pa-ringkasan__alasan" data-uji="alasan-belum-kirim">
                <HcisIcon name="hourglass-split" size="xs" />
                <span>{{ alasanBelumBisaKirim }}</span>
            </p>

            <template #actions>
                <HcisButton
                    label="Kirim Seluruh Penilaian"
                    variant="primary"
                    size="md"
                    leading-icon="send-fill"
                    data-uji="kirim"
                    :disabled="!bolehKirim"
                    :loading="mengirim"
                    class="pa-btn-submit-main"
                    @click="konfirmasiTerbuka = true"
                />
            </template>
        </HcisCard>

        <!-- 6. Confirmation Dialog -->
        <HcisDialog
            v-model="konfirmasiTerbuka"
            title="Kirim penilaian?"
            size="sm"
            variant="glass"
            sentiment="brand"
        >
            <div class="pa-kirim" data-uji="konfirmasi-kirim">
                <p class="pa-kirim__lead">
                    Anda akan mengirim <strong>{{ pengiriman?.terkumpul ?? 0 }} penilaian</strong>:
                </p>

                <ul v-if="rincianKirim.length" class="pa-kirim__daftar">
                    <li v-for="r in rincianKirim" :key="r.kode" class="pa-kirim__baris">
                        <span class="pa-category-jewel pa-kirim__ikon" :class="categoryIconClass(r.kode)">
                            <HcisIcon :name="categoryIconName(r.kode)" size="2xs" />
                        </span>
                        <span class="pa-kirim__nama">{{ r.nama }}</span>
                        <span class="pa-kirim__jumlah">{{ r.jumlah }} penilaian</span>
                    </li>
                </ul>

                <div class="pa-kirim__catatan">
                    <HcisIcon name="lock-fill" size="xs" />
                    <span>
                        Setelah dikirim, nilai <strong>tidak bisa diubah lagi</strong>. Kalau masih
                        ingin menyesuaikan nilai, pilih <em>Periksa Lagi</em>.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="pa-ringkasan__aksi">
                    <HcisButton label="Periksa Lagi" variant="secondary" @click="konfirmasiTerbuka = false" />
                    <HcisButton
                        label="Kirim Penilaian"
                        variant="primary"
                        leading-icon="send-fill"
                        :loading="mengirim"
                        data-uji="konfirmasi-kirim-ya"
                        @click="konfirmasiKirim"
                    />
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisIcon,
} from '@/components/ui';

defineOptions({ name: 'PaWizardRingkasanKirim' });

const props = defineProps({
    langkah: { type: Array, default: () => [] },
    pengiriman: { type: Object, default: null },
    bolehKirim: { type: Boolean, default: false },
    mengirim: { type: Boolean, default: false },
    galatKirim: { type: Object, default: null },
});

const emit = defineEmits(['buka', 'kirim']);

const konfirmasiTerbuka = ref(false);

const totalKategoriTuntas = computed(() => props.langkah.filter((l) => l.status === 'TUNTAS').length);
const isSemuaKategoriTuntas = computed(() => totalKategoriTuntas.value === props.langkah.length && props.langkah.length > 0);





function categoryIconName(kode) {
    const k = String(kode || '').toUpperCase();
    if (k === 'SELF' || k === 'DIRI_SENDIRI') return 'person-circle';
    if (k === 'ATASAN') return 'person-badge';
    if (k === 'PEER' || k === 'REKAN') return 'people-fill';
    if (k === 'BAWAHAN') return 'diagram-3-fill';
    return 'clipboard-check';
}

function categoryIconClass(kode) {
    const k = String(kode || '').toUpperCase();
    if (k === 'SELF' || k === 'DIRI_SENDIRI') return 'is-self';
    if (k === 'ATASAN') return 'is-atasan';
    if (k === 'PEER' || k === 'REKAN') return 'is-peer';
    if (k === 'BAWAHAN') return 'is-bawahan';
    return 'is-brand';
}

/* Rincian per kategori di dialog: angka total saja tidak menjawab "penilaian yang mana". */
const rincianKirim = computed(() =>
    props.langkah
        .filter((l) => Number(l.jumlah_terisi) > 0)
        .map((l) => ({ kode: l.kode_kategori, nama: l.nama_kategori, jumlah: Number(l.jumlah_terisi) })),
);

const alasanBelumBisaKirim = computed(() => {
    const p = props.pengiriman;
    if (!p || props.bolehKirim || p.sudah_terkirim_semua) return '';

    const menunggu = Number(p.menunggu_verifikasi) || 0;
    const belum = Number(p.belum_diisi) || 0;
    const bagian = [];

    if (belum > 0) bagian.push(`${belum} lembar belum diisi`);
    if (menunggu > 0) bagian.push(`${menunggu} lembar masih menunggu verifikasi nilai di HC Learn`);

    return bagian.length ? `Belum bisa dikirim: ${bagian.join(' dan ')}. Tombol Kirim aktif setelah semuanya selesai.` : '';
});

function konfirmasiKirim() {
    konfirmasiTerbuka.value = false;
    emit('kirim');
}

defineExpose({ konfirmasiKirim });
</script>

<style scoped>
.pa-ringkasan {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1.25rem);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Category Rekapitulasi */
.pa-ringkasan-rekap-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-rekap-list {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.pa-rekap-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    padding: 1.15rem 1.35rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 14px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-rekap-row:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.98);
}

.pa-rekap-row.is-tuntas {
    border-color: rgba(16, 185, 129, 0.35);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(16, 185, 129, 0.04) 100%);
}

.pa-rekap-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 0;
}

.pa-category-jewel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.85rem;
    height: 2.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.pa-category-jewel.is-self {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
}

.pa-category-jewel.is-atasan {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
}

.pa-category-jewel.is-peer {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
}

.pa-category-jewel.is-bawahan {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}

.pa-category-jewel.is-brand {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.pa-rekap-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
}

.pa-rekap-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-rekap-category-name {
    font-size: 0.98rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
}

.pa-rekap-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.775rem;
    color: var(--hcis-color-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-meta-sep {
    color: var(--hcis-color-text-muted, #94a3b8);
}

.pa-meta-calib {
    color: var(--hcis-color-brand-600, #4f46e5);
    font-weight: 650;
}

.pa-rekap-prog-track {
    width: 100%;
    max-width: 16rem;
    height: 6px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.85);
    overflow: hidden;
    margin-top: 0.25rem;
}

.pa-rekap-prog-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #4f46e5 0%, #10b981 100%);
    transition: width 0.3s ease;
}

.pa-ringkasan-alert {
    border-radius: var(--hcis-radius-xl, 14px);
}

/* Action Submission Box */
.pa-ringkasan-action-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-btn-submit-main {
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    white-space: nowrap;
    border-radius: 10px;
    font-weight: 750;
}

.pa-ringkasan__alasan {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin: 0;
    padding: 0.65rem 0.85rem;
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border-radius: var(--hcis-radius-md, 10px);
    font-size: 0.85rem;
    line-height: 1.5;
}

/* Confirmation Dialog */
.pa-kirim {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    color: var(--hcis-text-muted, #475569);
    font-size: 0.9rem;
    line-height: 1.55;
}

.pa-kirim__lead {
    margin: 0;
    color: var(--hcis-text, #0f172a);
}

.pa-kirim__daftar {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
    border: 1px solid var(--hcis-border, rgba(148, 163, 184, 0.35));
    border-radius: var(--hcis-radius-md, 10px);
    overflow: hidden;
}

.pa-kirim__baris {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.8rem;
}

.pa-kirim__baris + .pa-kirim__baris {
    border-top: 1px solid var(--hcis-border, rgba(148, 163, 184, 0.35));
}

.pa-kirim__ikon {
    width: 1.75rem;
    height: 1.75rem;
    flex-shrink: 0;
}

.pa-kirim__nama {
    flex: 1;
    min-width: 0;
    color: var(--hcis-text, #0f172a);
    font-weight: 600;
}

.pa-kirim__jumlah {
    color: var(--hcis-text-muted, #475569);
    font-size: 0.85rem;
    white-space: nowrap;
}

.pa-kirim__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.7rem 0.85rem;
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border-radius: var(--hcis-radius-md, 10px);
    font-size: 0.85rem;
}

.pa-kirim__catatan > :first-child {
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.pa-kirim__catatan em {
    font-style: normal;
    font-weight: 600;
}

.pa-ringkasan__aksi {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    width: 100%;
}

@media (max-width: 768px) {
    .pa-ringkasan {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    :deep(.hcis-card__description),
    :deep(.hcis-card p.hcis-card__description) {
        display: none !important;
    }

    .pa-rekap-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.75rem 0.85rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-rekap-left {
        gap: 0.75rem;
    }

    .pa-category-jewel {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 10px;
    }

    .pa-rekap-category-name {
        font-size: 0.875rem;
    }

    .pa-rekap-prog-track {
        max-width: 100%;
    }

    .pa-rekap-actions,
    .pa-rekap-actions > * {
        width: 100%;
    }

    .pa-ringkasan-action-card {
        border-radius: var(--hcis-radius-lg, 14px);
    }

    .pa-ringkasan-action-card :deep(.hcis-card__header) {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
    }

    .pa-ringkasan-action-card :deep(.hcis-card__header-main) {
        gap: 0.65rem;
        align-items: center;
    }

    .pa-ringkasan-action-card :deep(.hcis-card__icon-box) {
        width: 2rem;
        height: 2rem;
        font-size: 0.95rem;
        border-radius: 8px;
    }

    .pa-ringkasan-action-card :deep(.hcis-card__title) {
        font-size: 0.875rem;
        line-height: 1.3;
        font-weight: 750;
    }

    .pa-ringkasan-action-card :deep(.hcis-card__actions) {
        width: 100%;
        margin-top: 0.25rem;
    }

    .pa-ringkasan-action-card :deep(.hcis-card__body) {
        padding: 0 0.85rem 0.75rem;
    }

    .pa-btn-submit-main {
        width: 100%;
        min-height: 2.35rem;
        font-size: 0.84rem;
        font-weight: 750;
        border-radius: 8px;
    }

    .pa-ringkasan__alasan {
        padding: 0.5rem 0.65rem;
        font-size: 0.75rem;
        border-radius: 6px;
        gap: 0.4rem;
    }

    .pa-ringkasan__aksi {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
}
</style>
