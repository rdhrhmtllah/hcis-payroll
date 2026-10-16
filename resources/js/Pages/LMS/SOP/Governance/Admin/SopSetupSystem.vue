<template>
    <div class="container-fluid px-0 sop-scope sop-setup-system-page">
        <!-- ══ 1. EXECUTIVE HEADER ══ -->
        <HcisHeader
            title="Kebijakan Global & Otomasi Terjadwal"
            kicker="Set Up System · Modul DMS"
            description="Guardrail kepatuhan yang berlaku ke seluruh modul DMS, dan status scheduler yang menjalankan evaluasinya."
            icon="sliders"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="!loading.all"
                    :label="`${schedulerAktifCount}/${automationJobs.length} Scheduler Aktif`"
                    :variant="schedulerAktifCount === automationJobs.length ? 'success' : 'warning'"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="/sop/admin/kebijakan" class="text-decoration-none">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="diagram-3"
                            label="Otomasi Dokumen"
                            title="Buka halaman Aturan Penugasan & Riwayat Eksekusi"
                        />
                    </a>
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        label="Segarkan"
                        :loading="busy"
                        title="Segarkan kebijakan global dan status scheduler"
                        aria-label="Segarkan kebijakan global dan status scheduler"
                        @click="loadAll"
                    />
                </div>
            </template>

            <template #tabs>
                <HcisTabs
                    v-model="activeTab"
                    :tabs="tabItems"
                    variant="header"
                    size="md"
                    aria-label="Navigasi Set Up System DMS"
                />
            </template>
        </HcisHeader>

        <main class="sop-setup-system-main mb-5">
            <!-- ── TAB 1: KEBIJAKAN GLOBAL ── -->
            <section v-if="activeTab === 'settings'">
                <HcisCard variant="glass" sentiment="brand" size="lg">
                    <template #header>
                        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
                            <div>
                                <h2 class="hcis-card__title">Kebijakan Global Dokumen</h2>
                                <p class="hcis-card__description">
                                    Guardrail sistem untuk durasi minimum membaca materi, ambang tuntas video, dan otomatisasi mutasi karyawan (Mover).
                                </p>
                            </div>
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="floppy"
                                label="Simpan Kebijakan"
                                :loading="busy"
                                @click="saveSettings"
                            />
                        </div>
                    </template>

                    <div class="d-flex flex-column gap-3">
                        <!-- Setting 1: Dwell Minimum Reader -->
                        <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Dwell Minimum Reader
                                </strong>
                                <small class="text-slate-500">
                                    Waktu minimum per halaman sebelum bagian dokumen dianggap telah dibaca oleh karyawan.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisInputNumber
                                    v-model="settings.reader_dwell_min_dtk"
                                    :min="1"
                                    :max="120"
                                    suffix="detik"
                                />
                            </div>
                        </div>

                        <!-- Setting 2: Ambang Video Selesai -->
                        <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Ambang Video Selesai
                                </strong>
                                <small class="text-slate-500">
                                    Persentase durasi materi video yang wajib ditonton sebelum karyawan dapat menyelesaikan item.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisInputNumber
                                    v-model="settings.video_ambang_persen"
                                    :min="1"
                                    :max="100"
                                    suffix="%"
                                />
                            </div>
                        </div>

                        <!-- Setting 3: Interval Reminder Default -->
                        <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Interval Reminder Default
                                </strong>
                                <small class="text-slate-500">
                                    Digunakan secara global saat profil penjadwalan tidak menentukan interval pengingat khusus.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisInputNumber
                                    v-model="settings.default_reminder_hari"
                                    :min="1"
                                    :max="365"
                                    suffix="hari"
                                />
                            </div>
                        </div>

                        <!-- Setting 4: Auto Assign Mover -->
                        <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Auto Assign Mover
                                </strong>
                                <small class="text-slate-500">
                                    Evaluasi aturan aktif secara otomatis saat posisi, divisi, atau organisasi karyawan berubah (mutasi/promosi).
                                </small>
                            </div>
                            <div>
                                <HcisSwitch v-model="settings.mover_auto_assign" />
                            </div>
                        </div>

                        <!-- Setting 5: Auto Release -->
                        <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Auto Release
                                </strong>
                                <small class="text-slate-500">
                                    Secara otomatis mencabut assignment milik rule yang sudah tidak lagi cocok dengan kriteria karyawan.
                                </small>
                                <div class="text-xs text-warning-700 font-bold mt-1">
                                    <HcisIcon name="shield-exclamation" size="xs" class="me-1" />
                                    (Default nonaktif demi keamanan audit data kepatuhan)
                                </div>
                            </div>
                            <div>
                                <HcisSwitch v-model="settings.mover_auto_release" />
                            </div>
                        </div>

                        <!-- Setting 6: Ambang Proses Async (Readonly) -->
                        <div class="p-3 border rounded-3 bg-slate-50 d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    Ambang Proses Async
                                </strong>
                                <small class="text-slate-500">
                                    Dikelola oleh sistem untuk membagi antrean pemrosesan massal ke background worker.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisBadge
                                    :label="`${settings.penjadwalan_async_ambang} Target`"
                                    variant="neutral"
                                    size="md"
                                />
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- ── SAKELAR TIPE ITEM ──
                     Kartu terpisah, bukan baris ketujuh di kartu di atas: yang
                     di atas menyetel PERILAKU mesin, yang di sini menentukan
                     bentuk apa saja yang boleh lahir. Menyatukannya membuat
                     tujuh sakelar sejenis terbaca sebagai tujuh kebijakan yang
                     tak berhubungan. -->
                <HcisCard variant="glass" sentiment="brand" size="lg" class="mt-4">
                    <template #header>
                        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
                            <div>
                                <h2 class="hcis-card__title">Tipe Item yang Boleh Dipakai</h2>
                                <p class="hcis-card__description">
                                    Menentukan bentuk item apa saja yang boleh dibuat di Master Dokumen dan Builder Paket.
                                </p>
                            </div>
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="floppy"
                                label="Simpan Tipe Item"
                                :loading="busy"
                                @click="saveSettings"
                            />
                        </div>
                    </template>

                    <!-- Batas artinya disebut DI DEPAN. Tanpa kalimat ini
                         "nonaktif" terbaca sebagai "hilang dari mana-mana", dan
                         admin yang mematikan VIDEO akan mengira kewajiban baca
                         karyawan ikut dicabut. -->
                    <HcisAlert
                        variant="info"
                        appearance="soft"
                        size="sm"
                        class="mb-3"
                        message="Mematikan sebuah tipe hanya menutup pembuatan baru. Item dan dokumen lama bertipe itu tetap berjalan, tetap dibaca karyawan, dan tetap dihitung monitoring maupun laporan."
                    />

                    <div class="d-flex flex-column gap-3">
                        <div
                            v-for="t in tipeItemList"
                            :key="t.kunci"
                            class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3"
                        >
                            <div style="max-width: 600px;">
                                <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                    {{ t.label }}
                                </strong>
                                <small class="text-slate-500">{{ t.keterangan }}</small>
                            </div>
                            <div>
                                <HcisSwitch v-model="settings[t.kunci]" />
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </section>

            <!-- ── TAB 2: OTOMASI TERJADWAL ── -->
            <section v-else>
                <HcisCard variant="glass" sentiment="brand" size="lg">
                    <template #header>
                        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
                            <div>
                                <h2 class="hcis-card__title">Otomasi Terjadwal System</h2>
                                <p class="hcis-card__description">
                                    Daftar scheduler background worker yang menjalankan evaluasi berkala terhadap aturan dokumen dan pengiriman reminder.
                                </p>
                            </div>
                            <HcisButton
                                variant="glass"
                                size="md"
                                leading-icon="arrow-clockwise"
                                label="Segarkan Status"
                                :loading="busy"
                                @click="loadAll"
                            />
                        </div>
                    </template>

                    <!-- N_HRIS_Scheduler_Configs adalah tabel platform lintas modul.
                         Yang ditampilkan di sini hanya baris milik DMS; cron modul lain
                         (KPI, cuti, absensi, visitor) sengaja tidak ikut karena bukan
                         wewenang halaman ini. -->
                    <HcisAlert
                        variant="info"
                        appearance="soft"
                        size="sm"
                        class="mb-3"
                        message="Hanya scheduler milik modul DMS yang ditampilkan. Konfigurasi cron modul lain dikelola di luar halaman ini."
                    />

                    <div class="d-flex flex-column gap-3">
                        <div
                            v-for="job in automationJobs"
                            :key="job.type"
                            class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-3 shadow-xs"
                        >
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="sop-job-icon-box flex-shrink-0">
                                    <HcisIcon :name="job.icon" size="md" class="text-brand-600" />
                                </div>
                                <div>
                                    <strong class="d-block text-slate-800 font-bold" style="font-size: 0.95rem;">
                                        {{ job.label }}
                                    </strong>
                                    <small class="text-slate-500 d-block">{{ job.description }}</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="text-end d-none d-sm-block">
                                    <span class="font-mono text-xs font-bold text-slate-700 d-block">
                                        {{ scheduler(job.command)?.Cron_Expression || 'Belum dikonfigurasi' }}
                                    </span>
                                    <small class="text-xs text-slate-400 d-block">
                                        {{ scheduler(job.command)?.Timezone || 'Asia/Jakarta' }}
                                    </small>
                                </div>

                                <HcisBadge
                                    :label="scheduler(job.command)?.Flag_Aktif === 'Y' ? 'Aktif' : 'Perlu Setup'"
                                    :variant="scheduler(job.command)?.Flag_Aktif === 'Y' ? 'success' : 'warning'"
                                    size="sm"
                                    dot
                                    radar
                                />

                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="play-fill"
                                    label="Jalankan Sekarang"
                                    :loading="busy"
                                    @click="runNow(job.type)"
                                />
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </section>
        </main>
    </div>
</template>

<script>
import axios from 'axios';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';
import {
    HcisHeader,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisTabs,
    HcisInputNumber,
    HcisSwitch,
    HcisAlert,
    HcisIcon,
    hcisMessage,
} from '@/components/ui';

const API = '/api/v1/sop/admin/setup-system';
// Pemicu manual masih milik controller Kebijakan; rutenya bergerbang dua kunci
// sehingga halaman ini dan halaman Otomasi Dokumen sama-sama boleh memanggilnya.
const RUN_API = '/api/v1/sop/admin/kebijakan';

export default {
    name: 'SopSetupSystem',
    components: {
        HcisHeader,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisTabs,
        HcisInputNumber,
        HcisSwitch,
        HcisAlert,
        HcisIcon,
    },
    mixins: [pageMeta],
    data() {
        return {
            activeTab: 'settings',
            busy: false,
            schedulers: [],
            settings: {
                reader_dwell_min_dtk: 3,
                video_ambang_persen: 90,
                default_reminder_hari: 3,
                mover_auto_assign: true,
                mover_auto_release: false,
                penjadwalan_async_ambang: 30,
                /* Bawaan di sini HANYA berlaku sampai /settings menjawab.
                   Sumber kebenarannya SopTipeItemAktif di server; nilai ini ada
                   supaya v-model punya kunci sejak render pertama. */
                tipe_item_dokumen: true,
                tipe_item_form: true,
                tipe_item_video: true,
                tipe_item_kuis: true,
                tipe_item_tautan: true,
                tipe_item_paragraf: true,
                tipe_item_absensi: false,
            },
            /* Keterangannya menyebut APA YANG DILIHAT KARYAWAN, bukan nama
               teknis tipenya — yang memilih di sini admin kepatuhan, bukan
               orang yang hafal enum. */
            tipeItemList: [
                {
                    kunci: 'tipe_item_dokumen',
                    label: 'Dokumen',
                    keterangan: 'PDF wajib baca dengan pelacakan per halaman. Bentuk utama dokumen terkendali.',
                },
                {
                    kunci: 'tipe_item_form',
                    label: 'Formulir',
                    keterangan: 'Berkas yang diunduh dan diisi karyawan, mis. formulir serah terima.',
                },
                {
                    kunci: 'tipe_item_video',
                    label: 'Video',
                    keterangan: 'Materi video; dianggap tuntas setelah ambang persentase tontonan tercapai.',
                },
                {
                    kunci: 'tipe_item_kuis',
                    label: 'Kuis',
                    keterangan: 'Ujian HC Learn dengan nilai minimum kelulusan.',
                },
                {
                    kunci: 'tipe_item_tautan',
                    label: 'Tautan',
                    keterangan: 'Pranala ke sistem lain. Tak punya versi dan tak melewati Master Dokumen.',
                },
                {
                    kunci: 'tipe_item_paragraf',
                    label: 'Paragraf',
                    keterangan: 'Blok teks penjelas di dalam paket. Tak punya versi dan tak melewati Master Dokumen.',
                },
                {
                    kunci: 'tipe_item_absensi',
                    label: 'Absensi',
                    keterangan: 'Check-in kehadiran. Sudah digantikan toggle absensi di tingkat paket.',
                },
            ],
            loading: {
                all: false,
            },
            /* type = segmen URL yang diterima runNow(); command = baris di
               N_HRIS_Scheduler_Configs. Keduanya sebelumnya salah pada job
               pertama ('apply' / 'sop:auto-apply'): runNow membalas 422 dan
               kartunya selalu berbunyi "Belum dikonfigurasi". */
            automationJobs: [
                {
                    type: 'rule-scan',
                    command: 'sop:rule-scan',
                    label: 'Evaluasi & penetapan penugasan dokumen',
                    description: 'Mengevaluasi seluruh karyawan terhadap aturan aktif dan menugaskan paket dokumen baru.',
                    icon: 'gear-wide-connected',
                },
                {
                    type: 'reminder',
                    command: 'sop:reminder-scan',
                    label: 'Digest reminder',
                    description: 'Mengirim satu ringkasan notifikasi digest in-app per karyawan.',
                    icon: 'bell',
                },
            ],
        };
    },
    computed: {
        tabItems() {
            return [
                { key: 'settings', label: 'Kebijakan Global', icon: 'sliders' },
                { key: 'automation', label: 'Otomasi Terjadwal', icon: 'clock-history', badge: this.automationJobs.length, badgeVariant: 'neutral' },
            ];
        },
        schedulerAktifCount() {
            return this.automationJobs.filter((job) => this.scheduler(job.command)?.Flag_Aktif === 'Y').length;
        },
    },
    mounted() {
        this.loadAll();
    },
    methods: {
        async loadAll() {
            this.loading.all = true;
            try {
                const r = await axios.get(`${API}/settings`);
                if (r.data.success) {
                    this.settings = r.data.result.settings;
                    this.schedulers = r.data.result.schedulers || [];
                }
            } catch (e) {
                this.fail(e, 'Gagal memuat kebijakan global dan status scheduler');
            } finally {
                this.loading.all = false;
            }
        },
        async saveSettings() {
            this.busy = true;
            try {
                const payload = { ...this.settings };
                delete payload.penjadwalan_async_ambang;
                await axios.put(`${API}/settings`, payload);
                hcisMessage.success('Kebijakan Global Dokumen berhasil diperbarui.');
            } catch (e) {
                this.fail(e, 'Pengaturan kebijakan gagal disimpan');
            } finally {
                this.busy = false;
            }
        },
        async runNow(type) {
            this.busy = true;
            try {
                await axios.post(`${RUN_API}/runs/${type}`);
                hcisMessage.success('Otomasi berhasil dimasukkan ke antrean worker.');
                await this.loadAll();
            } catch (e) {
                this.fail(e, 'Otomasi gagal dijalankan');
            } finally {
                this.busy = false;
            }
        },
        scheduler(command) {
            return (this.schedulers || []).find((x) => x.Command === command);
        },
        fail(error, fallback) {
            hcisMessage.error(
                error?.response?.data?.message ||
                    Object.values(error?.response?.data?.errors || {})?.[0]?.[0] ||
                    fallback
            );
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.sop-setup-system-page {
    width: 100%;
}

.sop-job-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
</style>
