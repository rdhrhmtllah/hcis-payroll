<template>
    <HcisDrawer
        :model-value="modelValue"
        title="Riwayat Jadwal"
        :description="data ? `${data.karyawan.nama} · ${formatTanggal(data.tanggal)}` : formatTanggal(tanggal)"
        icon="clock-history"
        sentiment="primary"
        size="35rem"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <HcisSkeleton v-if="memuat" height="16rem" rounded="md" />

        <HcisAlert
            v-else-if="galat"
            variant="danger"
            appearance="soft"
            size="sm"
            icon="exclamation-octagon"
            title="Riwayat jadwal tidak bisa dimuat"
            :description="galat"
        />

        <div v-else-if="data" class="jjh">
            <!-- Hasil akhir: yang benar-benar berlaku hari itu -->
            <section class="jjh-hero-card" :class="`is-${data.hasil.kategori === 'LIBUR' ? 'libur' : 'masuk'}`">
                <div class="jjh-hero-header">
                    <div class="jjh-hero-kicker-row">
                        <span class="jjh-hero-kicker">Jadwal yang berlaku</span>
                        <HcisBadge
                            :label="data.hasil.kategori === 'LIBUR' ? 'Libur' : 'Masuk kerja'"
                            :variant="data.hasil.kategori === 'LIBUR' ? 'warning' : 'success'"
                            size="xs"
                            dot
                        />
                    </div>

                    <div class="jjh-hero-title-row">
                        <div class="jjh-shift-badge-box">
                            <strong class="jjh-shift-name">{{ data.hasil.shift_nama || (data.hasil.kategori === 'LIBUR' ? 'LIBUR' : 'OFF') }}</strong>
                            <span v-if="data.hasil.sumber_shift" class="jjh-shift-source-pill">
                                {{ data.hasil.sumber_shift.toLowerCase() }}
                            </span>
                        </div>

                        <div v-if="data.hasil.kategori !== 'LIBUR' && data.hasil.jam_masuk" class="jjh-time-capsule font-tabular">
                            <HcisIcon name="clock-fill" size="2xs" />
                            <span>{{ data.hasil.jam_masuk }}–{{ data.hasil.jam_keluar }}</span>
                        </div>
                    </div>
                </div>

                <div class="jjh-hero-body">
                    <div class="jjh-reason-badge">
                        <HcisIcon :name="data.hasil.kategori === 'LIBUR' ? 'calendar-x-fill' : 'calendar-check-fill'" size="xs" />
                        <strong class="jjh-hasil__alasan">{{ penjelasan.judul }}</strong>
                    </div>

                    <div v-if="penjelasan.rincian.length" class="jjh-rincian-list">
                        <div v-for="(r, i) in penjelasan.rincian" :key="i" class="jjh-rincian-item">
                            <HcisIcon name="info-circle" size="2xs" class="jjh-rincian-icon" />
                            <span>{{ r }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <HcisAlert
                v-for="(p, i) in data.peringatan"
                :key="`p-${i}`"
                variant="warning"
                appearance="soft"
                size="sm"
                icon="exclamation-triangle"
                title="Perlu diperhatikan"
                :description="p"
            />

            <!-- Lapisan penentu: hanya yang ada; yang menang ditandai -->
            <section class="jjh-section">
                <div class="jjh-section-header">
                    <div class="jjh-section-title-wrap">
                        <HcisIcon name="layers-fill" size="xs" class="text-indigo-600" />
                        <h3 class="jjh-judul-bagian">Penentu jadwal</h3>
                    </div>
                    <HcisBadge :label="`${lapisanAda.length} sumber`" variant="neutral" size="xs" />
                </div>

                <ul class="jjh-lapisan">
                    <li v-for="l in lapisanAda" :key="l.kode" class="jjh-lapisan__baris" :class="{ 'is-berlaku': l.berlaku }">
                        <div class="jjh-lapisan__icon" :class="{ 'is-berlaku': l.berlaku }">
                            <HcisIcon :name="l.berlaku ? 'check-circle-fill' : 'dash-circle'" size="xs" />
                        </div>
                        <div class="jjh-tumpuk">
                            <div class="jjh-lapisan__header-line">
                                <span class="jjh-lapisan__judul">{{ l.judul }}</span>
                                <HcisBadge
                                    :label="l.berlaku ? 'Berlaku' : 'Tertimpa'"
                                    :variant="l.berlaku ? 'success' : 'neutral'"
                                    size="xs"
                                    appearance="subtle"
                                    dot
                                />
                            </div>
                            <span v-if="l.keterangan" class="jjh-lapisan__desc">{{ l.keterangan }}</span>
                            <div v-if="l.transaksi" class="jjh-lapisan__trx">
                                <HcisIcon name="file-earmark-text" size="2xs" />
                                <span>{{ l.transaksi.label }} · {{ l.transaksi.no }}</span>
                            </div>
                        </div>
                    </li>
                    <li v-if="!lapisanAda.length" class="jjh-muted">
                        Tidak ada pengaturan khusus — hari kerja biasa menurut shift yang berlaku.
                    </li>
                </ul>
            </section>

            <!-- Linimasa perubahan -->
            <section class="jjh-section">
                <div class="jjh-section-header">
                    <div class="jjh-section-title-wrap">
                        <HcisIcon name="clock-history" size="xs" class="text-indigo-600" />
                        <h3 class="jjh-judul-bagian">Riwayat perubahan</h3>
                    </div>
                </div>
                <div class="jjh-timeline-box">
                    <HcisTimeline v-if="itemLinimasa.length" :items="itemLinimasa" variant="flat" sentiment="primary">
                        <template #avatar="{ item }">
                            <HcisIcon
                                :name="item.sentiment === 'success' ? 'check-circle-fill' : (item.sentiment === 'warning' ? 'hourglass-split' : 'arrow-repeat')"
                                size="xs"
                            />
                        </template>

                        <template #itemContent="{ item }">
                            <div class="jjh-timeline-content">
                                <p v-if="item.rawAlasan" class="jjh-timeline-alasan">
                                    {{ item.rawAlasan }}
                                </p>

                                <div v-if="item.rawPelaku || item.rawTransaksi" class="jjh-timeline-meta-row">
                                    <span v-if="item.rawPelaku" class="jjh-meta-pill is-pelaku">
                                        <HcisIcon name="person-circle" size="2xs" />
                                        <span>{{ item.rawPelaku }}</span>
                                    </span>
                                    <span v-if="item.rawTransaksi" class="jjh-meta-pill is-trx">
                                        <HcisIcon name="file-earmark-text" size="2xs" />
                                        <span>{{ item.rawTransaksi }}</span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </HcisTimeline>
                    <p v-else class="jjh-muted">Belum pernah ada perubahan untuk tanggal ini.</p>
                </div>
            </section>
        </div>
    </HcisDrawer>
</template>

<script>
import axios from 'axios';
import { HcisAlert, HcisBadge, HcisDrawer, HcisIcon, HcisSkeleton, HcisTimeline } from '@/components/ui';

const SEBAB = {
    KONVERSI_LEMBUR: 'Libur pengganti dari konversi lembur.',
    STATUS_HARI: 'Diatur lewat status hari khusus (oleh leader/admin atau tukar hari).',
    HARI_LIBUR: 'Hari libur nasional.',
    CUTI_BERSAMA: 'Termasuk cuti bersama.',
    SHIFT_DEFAULT_OFF: 'Hari libur bawaan dari shift permanen.',
    BAWAAN: 'Hari kerja biasa menurut shift yang berlaku.',
};

const AKSI = {
    PASANG: { status: 'Dipasang', sentiment: 'brand' },
    BERLAKU: { status: 'Sedang Berlaku', sentiment: 'success' },
    GANTI: { status: 'Diganti', sentiment: 'brand' },
    PERBARUI: { status: 'Diperbarui', sentiment: 'brand' },
    LAMPAU: { status: 'Lampau', sentiment: 'neutral' },
    NONAKTIF: { status: 'Dinonaktifkan', sentiment: 'neutral' },
    MENUNGGU: { status: 'Menunggu persetujuan', sentiment: 'warning' },
};

export default {
    name: 'JejakJadwalHariDrawer',
    components: { HcisAlert, HcisBadge, HcisDrawer, HcisIcon, HcisSkeleton, HcisTimeline },
    props: {
        modelValue: { type: Boolean, default: false },
        /** Pintu sesuai halaman pemanggil — batas aksesnya ditentukan rute itu. */
        endpoint: { type: String, required: true },
        /** Kosong untuk pintu pribadi: server memakai karyawan yang login. */
        kode: { type: String, default: '' },
        tanggal: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    data() {
        return { memuat: false, galat: '', data: null };
    },
    computed: {
        /** Penjelasan dari server; label umum hanya cadangan bila server lama belum mengirimnya. */
        penjelasan() {
            const p = this.data?.hasil?.penjelasan;
            if (p?.judul) return { judul: p.judul, rincian: p.rincian || [] };
            return { judul: SEBAB[this.data?.hasil?.sebab] || '', rincian: [] };
        },
        lapisanAda() {
            return (this.data?.lapisan || []).filter((l) => l.ada);
        },
        itemLinimasa() {
            return (this.data?.linimasa || []).map((a, i) => {
                const aksi = AKSI[a.aksi] || AKSI.PASANG;
                const trxLabel = a.transaksi?.no ? `${a.transaksi.label} · ${a.transaksi.no}` : a.transaksi?.label;
                const keterangan = [
                    a.pelaku ? `oleh ${a.pelaku}` : null,
                    trxLabel,
                    a.alasan,
                ].filter(Boolean);

                const pelakuValid = a.pelaku && a.pelaku !== 'Tidak tercatat' ? a.pelaku : null;

                return {
                    id: i,
                    title: a.judul,
                    description: keterangan.join(' — '),
                    rawPelaku: pelakuValid ? `oleh ${pelakuValid}` : null,
                    rawTransaksi: trxLabel || null,
                    rawAlasan: a.alasan || null,
                    time: a.waktu ? this.formatWaktu(a.waktu) : '',
                    status: aksi.status,
                    sentiment: aksi.sentiment,
                };
            });
        },
    },
    watch: {
        modelValue(buka) {
            if (buka) this.muat();
        },
        tanggal() {
            if (this.modelValue) this.muat();
        },
        kode() {
            if (this.modelValue) this.muat();
        },
    },
    mounted() {
        if (this.modelValue) this.muat();
    },
    methods: {
        async muat() {
            if (!this.tanggal) return;
            this.memuat = true;
            this.galat = '';
            this.data = null;
            const params = this.kode ? { kode: this.kode, tanggal: this.tanggal } : { tanggal: this.tanggal };
            try {
                const { data } = await axios.get(this.endpoint, { params });
                if (data?.success) this.data = data.result;
                else this.galat = data?.message || 'Riwayat jadwal gagal dimuat.';
            } catch (e) {
                this.galat = e.response?.data?.message || 'Riwayat jadwal gagal dimuat.';
            } finally {
                this.memuat = false;
            }
        },
        formatTanggal(v) {
            if (!v) return '';
            const d = new Date(`${String(v).slice(0, 10)}T00:00:00`);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        },
        formatWaktu(v) {
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.jjh {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Hero Card: Jadwal yang berlaku */
.jjh-hero-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.15rem 1.25rem;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
}

.jjh-hero-card.is-masuk {
    border-color: #86efac;
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%);
}

.jjh-hero-card.is-libur {
    border-color: #fde047;
    background: linear-gradient(135deg, #fefce8 0%, #ffffff 60%);
}

.jjh-hero-header {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.jjh-hero-kicker-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.jjh-hero-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

.jjh-hero-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.jjh-shift-badge-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.jjh-shift-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.jjh-shift-source-pill {
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: capitalize;
}

.jjh-time-capsule {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.jjh-hero-body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #cbd5e1;
}

.jjh-reason-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #0f172a;
    font-size: 0.88rem;
}

.jjh-rincian-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.jjh-rincian-item {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.45;
}

.jjh-rincian-icon {
    margin-top: 0.15rem;
    color: #94a3b8;
    flex-shrink: 0;
}

/* Sections */
.jjh-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.jjh-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.jjh-section-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.jjh-judul-bagian {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
}

/* Lapisan Penentu */
.jjh-lapisan {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.jjh-lapisan__baris {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.15s ease;
}

.jjh-lapisan__baris.is-berlaku {
    background: #f0fdf4;
    border-color: #86efac;
}

.jjh-lapisan__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 8px;
    background: #f1f5f9;
    color: #94a3b8;
    flex-shrink: 0;
}

.jjh-lapisan__icon.is-berlaku {
    background: #dcfce7;
    color: #16a34a;
}

.jjh-tumpuk {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.jjh-lapisan__header-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.jjh-lapisan__judul {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
}

.jjh-lapisan__desc {
    font-size: 0.76rem;
    color: #334155;
    line-height: 1.4;
}

.jjh-lapisan__trx {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.15rem;
}

.jjh-muted {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}

/* Timeline Box */
.jjh-timeline-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
}

/* Timeline Item Customization */
.jjh-timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-top: 0.25rem;
}

.jjh-timeline-alasan {
    margin: 0;
    font-size: 0.8rem;
    color: #334155;
    line-height: 1.5;
}

.jjh-timeline-meta-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.2rem;
}

.jjh-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
}

.jjh-meta-pill.is-pelaku {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

.jjh-meta-pill.is-trx {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
</style>
