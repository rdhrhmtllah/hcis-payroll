<template>
    <div class="bc" :class="{ open: terbuka, memproses: !!batch.Status_Proses }">
        <!-- Kepala kartu -->
        <button type="button" class="bc-head" @click="$emit('toggle')">
            <span class="bc-ico"><i class="bi bi-journal-text"></i></span>

            <span class="bc-main">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <span class="bc-title">{{ batch.Label }}</span>
                    <span v-if="batch.Versi_Paket" class="bc-ver-pill">v{{ batch.Versi_Paket }}.0</span>
                </div>
                <span class="bc-sub">
                    <span v-if="batch.Kode_Paket" class="font-mono text-muted fw-bold me-1">[{{ batch.Kode_Paket }}]</span>
                    <i class="bi bi-journal-text"></i> {{ batch.Nama_Paket }}
                </span>
                <span class="bc-pills">
                    <!-- Sumber Penugasan -->
                    <HcisBadge
                        :variant="batch.Sumber === 'MANDIRI' ? 'success' : (batch.Sumber === 'AUTO_RULE' ? 'brand' : 'neutral')"
                        size="xs"
                        :icon="batch.Sumber === 'MANDIRI' ? 'person-raised-hand' : (batch.Sumber === 'AUTO_RULE' ? 'lightning-charge' : 'person-badge')"
                    >
                        {{ batch.Sumber === 'MANDIRI' ? 'Mandiri' : (batch.Sumber === 'AUTO_RULE' ? 'Otomatis' : 'Ditugaskan') }}
                    </HcisBadge>

                    <!-- Status Tenggat -->
                    <HcisBadge
                        :variant="batch.Is_Terlambat ? 'danger' : 'neutral'"
                        size="xs"
                        :icon="batch.Tipe_Tenggat === 'DEADLINE' ? 'calendar-event' : 'infinity'"
                    >
                        {{ teksTenggat }}
                    </HcisBadge>

                    <!-- Komposisi Isi Paket -->
                    <span v-if="batch.Total_Dokumen || batch.Total_Kuis" class="bc-comp-pill">
                        <i class="bi bi-file-earmark-text me-0.5"></i>
                        {{ batch.Total_Dokumen || 0 }} Dok<template v-if="batch.Total_Kuis"> · {{ batch.Total_Kuis }} Kuis</template>
                    </span>

                    <!-- Estimasi Waktu -->
                    <span v-if="batch.Estimasi" class="bc-time-pill">
                        <i class="bi bi-clock me-0.5"></i>~{{ batch.Estimasi }}m
                    </span>

                    <!-- Reminder -->
                    <HcisBadge
                        v-if="batch.Flag_Reminder === 'Y'"
                        variant="neutral"
                        size="xs"
                        icon="bell"
                    >
                        Tiap {{ batch.Irama_Reminder_Hari }} hari
                    </HcisBadge>

                    <!-- Background Processing Pill -->
                    <HcisBadge
                        v-if="batch.Status_Proses"
                        variant="warning"
                        size="xs"
                        icon="arrow-repeat"
                    >
                        Memproses {{ batch.Proses_Persen }}%
                    </HcisBadge>
                </span>
            </span>

            <span class="bc-stats">
                <span class="bc-stat">
                    <b class="tnum">{{ batch.Total_Target }}</b>
                    <small>target</small>
                </span>
                <span class="bc-stat ok">
                    <b class="tnum">{{ batch.Total_Selesai }}</b>
                    <small>tuntas</small>
                </span>
                <span v-if="batch.Total_Terlambat > 0" class="bc-stat bad">
                    <b class="tnum">{{ batch.Total_Terlambat }}</b>
                    <small>terlambat</small>
                </span>
                <span v-if="batch.Total_Dicabut > 0" class="bc-stat faint">
                    <b class="tnum">{{ batch.Total_Dicabut }}</b>
                    <small>dihapus</small>
                </span>
            </span>

            <!-- Official HcisProgress Visualizer -->
            <div class="bc-prog">
                <HcisProgress
                    :value="batch.Persen"
                    :variant="progressVariant"
                    size="xs"
                    class="flex-grow-1"
                />
                <span class="bc-prog-num tnum">{{ batch.Persen }}%</span>
            </div>

            <i class="bi bc-chev" :class="terbuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>

        <!-- Isi Collapse -->
        <transition name="bc-exp">
            <div v-if="terbuka" class="bc-body">
                <div class="bc-actions">
                    <span class="bc-meta">
                        <i class="bi bi-person-check"></i> Dibuat {{ tglAssign }} oleh
                        <strong>{{ batch.Dibuat_Oleh || 'Sistem' }}</strong>
                        <template v-if="batch.Diperbarui">
                            · <i class="bi bi-pencil"></i> Diperbarui {{ tglUpdate
                            }}{{ batch.Diperbarui_Oleh ? ' oleh ' + batch.Diperbarui_Oleh : '' }}
                        </template>
                        <template v-if="batch.Sisa_Hari !== null && batch.Tipe_Tenggat === 'DEADLINE'">
                            ·
                            <span :class="batch.Sisa_Hari < 0 ? 'text-danger fw-bold' : 'text-primary fw-semibold'">
                                {{
                                    batch.Sisa_Hari < 0
                                        ? `lewat ${Math.abs(batch.Sisa_Hari)} hari`
                                        : `sisa ${batch.Sisa_Hari} hari`
                                }}
                            </span>
                        </template>
                    </span>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <HcisButton
                            v-if="batch.Total_Target > batch.Total_Selesai"
                            variant="warning"
                            size="xs"
                            leading-icon="bell"
                            :loading="mengingatkan"
                            label="Kirim Pengingat"
                            title="Kirim notifikasi pengingat ke seluruh karyawan yang belum menyelesaikan batch ini"
                            @click="ingatkanBatch"
                        />
                        <HcisButton
                            variant="ghost"
                            size="xs"
                            leading-icon="download"
                            label="Ekspor Roster"
                            title="Unduh file rekap data kepatuhan batch ini (.CSV)"
                            @click="eksporRoster"
                        />
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="calendar2-check"
                            label="Ubah Tenggat"
                            @click="$emit('edit-tenggat', batch)"
                        />
                        <HcisButton
                            v-if="batch.Sumber !== 'AUTO_RULE'"
                            variant="secondary"
                            size="xs"
                            leading-icon="person-plus"
                            label="Tambah Orang"
                            @click="$emit('tambah-orang', batch)"
                        />
                    </div>
                </div>

                <!-- Note card untuk keterangan distribusi -->
                <div v-if="batch.Keterangan" class="bc-note-card mb-3">
                    <div class="bc-note-ico">
                        <i class="bi bi-chat-left-quote-fill"></i>
                    </div>
                    <div class="bc-note-content flex-grow-1">
                        <div class="bc-note-title">Keterangan Penjadwalan</div>
                        <div class="bc-note-text">{{ batch.Keterangan }}</div>
                    </div>
                </div>

                <div class="bc-tabs-wrap mb-3">
                    <HcisTabs
                        v-model="tab"
                        :tabs="detailTabs"
                        variant="segmented"
                    />
                </div>

                <BatchKaryawanTable
                    v-if="tab === 'orang'"
                    :id-batch="batch.Id_SOP_Penjadwalan"
                    :id-paket="batch.Id_SOP_Paket"
                    :opsi-filter="opsiFilter"
                    @berubah="$emit('berubah')"
                />
                <BatchRiwayat v-else :key="riwayatKey" :id-batch="batch.Id_SOP_Penjadwalan" />
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { HcisBadge, HcisButton, HcisContextBanner, HcisProgress, HcisTabs } from '@/components/ui';
import BatchKaryawanTable from './BatchKaryawanTable.vue';
import BatchRiwayat from './BatchRiwayat.vue';

export default {
    name: 'BatchCard',
    components: {
        HcisBadge,
        HcisButton,
        HcisContextBanner,
        HcisProgress,
        HcisTabs,
        BatchKaryawanTable,
        BatchRiwayat,
    },
    props: {
        batch: { type: Object, required: true },
        terbuka: { type: Boolean, default: false },
        opsiFilter: { type: Object, default: () => ({}) },
    },
    emits: ['toggle', 'edit-tenggat', 'tambah-orang', 'berubah'],
    data() {
        return {
            tab: 'orang',
            riwayatKey: 0,
            mengingatkan: false,
            detailTabs: [
                { key: 'orang', value: 'orang', label: 'Daftar Karyawan', icon: 'people' },
                { key: 'riwayat', value: 'riwayat', label: 'Riwayat Audit Batch', icon: 'clock-history' },
            ],
        };
    },
    watch: {
        terbuka(v) {
            if (v) this.riwayatKey += 1;
        },
    },
    computed: {
        progressVariant() {
            if (this.batch.Persen >= 100) return 'success';
            if (this.batch.Persen > 0) return 'warning';
            return 'brand';
        },
        teksTenggat() {
            if (this.batch.Tipe_Tenggat !== 'DEADLINE') return 'Tanpa tenggat';
            const d = this.batch.Tanggal_Deadline;
            return d
                ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                : 'Deadline';
        },
        tglAssign() {
            return this.tanggal(this.batch.Tanggal_Assign);
        },
        tglUpdate() {
            return this.tanggal(this.batch.Updated_At);
        },
    },
    methods: {
        tanggal(d) {
            return d
                ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                : '—';
        },
        async ingatkanBatch() {
            const sisa = (this.batch.Total_Target || 0) - (this.batch.Total_Selesai || 0);
            try {
                await ElMessageBox.confirm(
                    `Kirim notifikasi pengingat kepada ${sisa} karyawan yang belum menyelesaikan penugasan ini?`,
                    'Konfirmasi Kirim Pengingat',
                    {
                        confirmButtonText: 'Ya, Kirim Pengingat',
                        cancelButtonText: 'Batal',
                        type: 'warning',
                    }
                );
            } catch {
                return;
            }

            this.mengingatkan = true;
            try {
                const res = await axios.post(`/api/v1/sop/admin/penjadwalan/batch/${this.batch.Id_SOP_Penjadwalan}/ingatkan`);
                ElMessage.success(res.data?.message || 'Pengingat berhasil dikirim.');
                this.riwayatKey += 1;
                this.$emit('berubah');
            } catch (err) {
                ElMessage.error(err.response?.data?.message || 'Gagal mengirim pengingat.');
            } finally {
                this.mengingatkan = false;
            }
        },
        eksporRoster() {
            const url = `/api/v1/sop/admin/penjadwalan/batch/${this.batch.Id_SOP_Penjadwalan}/export`;
            window.open(url, '_blank');
        },
    },
};
</script>

<style scoped>
.bc {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-white);
    overflow: hidden;
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.bc:hover {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}
.bc.open {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 10px 25px -15px rgba(99, 102, 241, 0.3);
}
.bc.memproses {
    border-color: rgba(99, 102, 241, 0.5);
}

.bc-head {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto 150px 20px;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    text-align: left;
    padding: 0.85rem 1.15rem;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.bc-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-indigo-600);
    font-size: 1.1rem;
}
.bc-main {
    min-width: 0;
}
.bc-title {
    display: block;
    font-weight: 800;
    font-size: 0.92rem;
    color: var(--hcis-slate-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bc-sub {
    display: block;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bc-sub i {
    color: var(--hcis-indigo-300);
}
.bc-pills {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.35rem;
}
.bc-ver-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    font-family: monospace, SFMono-Regular, Consolas, sans-serif;
}
.bc-comp-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-sky-700);
    background: var(--hcis-sky-50);
    border: 1px solid var(--hcis-sky-200);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
}
.bc-time-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
}

.bc-stats {
    display: flex;
    gap: 0.9rem;
}
.bc-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.1;
}
.bc-stat b {
    font-size: 1rem;
    color: var(--hcis-slate-800);
}
.bc-stat small {
    font-size: 0.62rem;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.bc-stat.ok b {
    color: var(--hcis-emerald-600);
}
.bc-stat.bad b {
    color: var(--hcis-red-600);
}
.bc-stat.faint b {
    color: var(--hcis-slate-400);
}

.bc-prog {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.bc-prog-num {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    min-width: 34px;
    text-align: right;
}
.bc-chev {
    color: var(--hcis-slate-400);
    font-size: 0.9rem;
}

.bc-body {
    padding: 0.25rem 1.15rem 1.15rem;
    border-top: 1px solid var(--hcis-slate-100);
    background: var(--hcis-zinc-50);
}
.bc-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.75rem 0;
}
.bc-meta {
    flex: 1;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    min-width: 240px;
}
.bc-tabs-wrap {
    width: fit-content;
}

.bc-note-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: linear-gradient(135deg, #f8faff 0%, var(--hcis-indigo-50) 100%);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 12px;
}
.bc-note-ico {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    margin-top: 1px;
}
.bc-note-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-indigo-700);
    margin-bottom: 0.25rem;
}
.bc-note-text {
    font-size: 0.84375rem;
    color: var(--hcis-indigo-950);
    line-height: 1.5;
    white-space: pre-wrap;
}

.bc-exp-enter-active,
.bc-exp-leave-active {
    transition: all 0.2s ease;
}
.bc-exp-enter-from,
.bc-exp-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 991.98px) {
    .bc-head {
        grid-template-columns: 38px minmax(0, 1fr) 20px;
        row-gap: 0.6rem;
    }
    .bc-stats {
        grid-column: 1 / -1;
        justify-content: space-around;
    }
    .bc-prog {
        grid-column: 1 / -1;
    }
}
</style>
