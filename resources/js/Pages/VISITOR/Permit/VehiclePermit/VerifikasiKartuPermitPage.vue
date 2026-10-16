<template>
    <div class="verif-page-bg min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 py-4 px-3 sm:px-6 sm:py-8 transition-colors duration-200">
        <HcisContainer size="md" class="max-w-3xl mx-auto space-y-4">
            <!-- ═══════════════════════════════════════════════════════════════
                 1. TOP SYSTEM NAVBAR / BRAND HEADER
                 ═══════════════════════════════════════════════════════════════ -->
            <header class="flex items-center justify-between p-3.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 dark:bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                        <HcisIcon name="shield-check" size="20" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-sm font-black tracking-wide uppercase text-slate-900 dark:text-white">
                                HCIS SHE VERIFIKASI
                            </h1>
                            <HcisBadge variant="solid" sentiment="brand" size="xs">
                                OFFICIAL
                            </HcisBadge>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            Sistem Otorisasi Akses Gerbang &amp; Kawasan
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="kartu.sah ? 'bg-emerald-400' : 'bg-rose-400'" />
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="kartu.sah ? 'bg-emerald-500' : 'bg-rose-500'" />
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider" :class="kartu.sah ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
                        LIVE
                    </span>
                </div>
            </header>

            <!-- ═══════════════════════════════════════════════════════════════
                 2. HERO VERIFICATION RESULT BANNER
                 ═══════════════════════════════════════════════════════════════ -->
            <section
                class="relative overflow-hidden rounded-2xl p-6 sm:p-7 text-center text-white shadow-lg transition-all duration-300"
                :class="kartu.sah
                    ? 'bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 shadow-emerald-900/20'
                    : 'bg-gradient-to-br from-rose-600 via-rose-700 to-red-900 shadow-rose-900/20'"
            >
                <div class="relative z-10 space-y-2">
                    <div class="w-14 h-14 mx-auto rounded-full bg-white/20 border-2 border-white/40 backdrop-blur-md flex items-center justify-center shadow-inner">
                        <HcisIcon :name="kartu.sah ? 'check-lg' : 'x-lg'" size="28" class="text-white" />
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black uppercase tracking-wide drop-shadow-sm">
                        {{ kartu.judul }}
                    </h2>

                    <div class="pt-1">
                        <span
                            class="inline-block px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider shadow-sm"
                            :class="kartu.sah ? 'bg-white text-emerald-800' : 'bg-white text-rose-800'"
                        >
                            STATUS: {{ kartu.status ?? (kartu.sah ? 'AKTIF' : 'TIDAK VALID') }}
                        </span>
                    </div>

                    <p class="text-sm font-medium text-white/90 max-w-lg mx-auto pt-2 leading-relaxed">
                        {{ kartu.sebab || 'Izin akses kendaraan terdaftar dan diverifikasi sah di basis data resmi HCIS SHE.' }}
                    </p>
                </div>

                <!-- Decorative Watermark Graphic -->
                <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none">
                    <HcisIcon :name="kartu.sah ? 'shield-check' : 'shield-x'" size="160" />
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 3. HERO LICENSE PLATE HUD (OBSIDIAN AUTOMOTIVE CARD)
                 ═══════════════════════════════════════════════════════ -->
            <section v-if="kartu.plat" class="bg-slate-950 border-2 rounded-2xl p-5 sm:p-6 text-center text-white shadow-md relative overflow-hidden" :style="{ borderColor: kartu.aksen || '#334155' }">
                <div class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-1.5 flex items-center justify-center gap-2">
                    <span>&#9670;</span>
                    <span>NOMOR REGISTRASI KENDARAAN</span>
                    <span>&#9670;</span>
                </div>

                <div class="text-4xl sm:text-5xl font-black tracking-widest text-white uppercase font-mono py-1 select-all drop-shadow-md">
                    {{ kartu.plat }}
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 pt-3">
                    <HcisBadge
                        variant="solid"
                        size="sm"
                        :style="{ backgroundColor: kartu.aksen || '#2563eb', color: '#ffffff' }"
                    >
                        {{ kartu.kategori }}
                    </HcisBadge>
                    <HcisBadge
                        v-if="kartu.kendaraan && kartu.kendaraan !== '-'"
                        variant="outline"
                        sentiment="secondary"
                        size="sm"
                        class="bg-slate-900 border-slate-700 text-slate-200"
                    >
                        {{ kartu.kendaraan }}
                    </HcisBadge>
                    <HcisBadge
                        v-else
                        variant="outline"
                        sentiment="secondary"
                        size="sm"
                        class="bg-slate-900 border-slate-700 text-slate-200"
                    >
                        {{ kartu.tipe_kendaraan || 'Kendaraan' }}
                    </HcisBadge>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 4. DETAIL GRID: VALIDITY, HOLDER, & AUTHENTICATION
                 ═══════════════════════════════════════════════════════ -->
            <div v-if="kartu.plat" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- KARTU 1: PERIODE & MASA BERLAKU -->
                <HcisCard
                    title="Masa Berlaku Izin"
                    subtitle="Periode otorisasi dan cakupan akses"
                    icon="calendar2-check"
                    sentiment="brand"
                    variant="flat"
                    divider
                >
                    <div class="space-y-3 pt-1">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Berlaku Hingga
                            </div>
                            <div class="text-2xl font-black pt-0.5 tracking-tight" :style="{ color: kartu.aksen || 'var(--primary-color)' }">
                                {{ kartu.sampai }}
                            </div>
                        </div>

                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800 text-sm">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Berlaku Sejak</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100">{{ kartu.sejak }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1.5 text-sm">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Lingkup Akses</span>
                            <span class="font-bold text-right text-slate-900 dark:text-slate-100">{{ kartu.zona_badge || kartu.kategori }}</span>
                        </div>
                    </div>
                </HcisCard>

                <!-- KARTU 2: IDENTITAS PEMEGANG HAK AKSES -->
                <HcisCard
                    title="Pemegang Hak Akses"
                    subtitle="Data penanggung jawab kendaraan"
                    icon="person-badge"
                    sentiment="secondary"
                    variant="flat"
                    divider
                >
                    <div class="space-y-2.5 pt-1 text-sm">
                        <div class="flex justify-between items-start py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Nama Pemohon</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100 text-right">{{ kartu.pemegang }}</span>
                        </div>

                        <div v-if="kartu.nik && kartu.nik !== '-'" class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">NIK / Identitas</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100 font-mono">{{ kartu.nik }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Departemen</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100 text-right">{{ kartu.departemen }}</span>
                        </div>

                        <div v-if="kartu.jabatan && kartu.jabatan !== '-'" class="flex justify-between items-center py-1.5">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Jabatan</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100 text-right">{{ kartu.jabatan }}</span>
                        </div>
                    </div>
                </HcisCard>

                <!-- KARTU 3: AUTENTIKASI & JEJAK DIGITAL -->
                <HcisCard
                    title="Autentikasi &amp; Jejak Verifikasi"
                    subtitle="Integritas tanda tangan digital resmi"
                    icon="qr-code-scan"
                    sentiment="info"
                    variant="flat"
                    divider
                    class="md:col-span-2"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">No. Transaksi</div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ kartu.no_transaksi }}</span>
                                <HcisButton
                                    size="xs"
                                    variant="ghost"
                                    icon="clipboard"
                                    title="Salin Nomor Transaksi"
                                    @click="salinNomor(kartu.no_transaksi)"
                                />
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Kode Seri Verifikasi</div>
                            <div class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ kartu.seri || '-' }}</div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Waktu Pengecekan</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white">{{ kartu.dicek }}</div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 5. PETUNJUK PETUGAS PATROLI / GERBANG
                 ═══════════════════════════════════════════════════════════════ -->
            <HcisAlert
                variant="info"
                title="Panduan Petugas Patroli &amp; Keamanan"
                description="Cocokkan plat nomor dan spesifikasi fisik kendaraan dengan data di atas. Jika status permit DICABUT atau KEDALUWARSA, tahan kendaraan dan arahkan ke pos SHE."
                icon="info-circle"
            />

            <!-- ═══════════════════════════════════════════════════════════════
                 6. BOTTOM ACTIONS & LEGAL FOOTER
                 ═══════════════════════════════════════════════════════════════ -->
            <footer class="pt-2 text-center space-y-3">
                <HcisButton
                    variant="outline"
                    sentiment="secondary"
                    size="md"
                    icon="arrow-clockwise"
                    class="w-full sm:w-auto"
                    @click="muatUlang"
                >
                    Muat Ulang Status Verifikasi
                </HcisButton>

                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                    Data divalidasi langsung secara live dari server HCIS SHE melalui URL bertanda tangan digital. Apabila terdapat perbedaan dengan kartu fisik, status resmi yang berlaku adalah halaman ini.
                </p>
            </footer>
        </HcisContainer>
    </div>
</template>

<script>
import {
    HcisContainer,
    HcisCard,
    HcisBadge,
    HcisButton,
    HcisIcon,
    HcisAlert,
    hcisMessage,
} from '@/components/ui';

export default {
    name: 'VerifikasiKartuPermitPage',
    components: {
        HcisContainer,
        HcisCard,
        HcisBadge,
        HcisButton,
        HcisIcon,
        HcisAlert,
    },
    props: {
        kartu: {
            type: Object,
            required: true,
            default: () => ({}),
        },
    },
    methods: {
        salinNomor(teks) {
            if (!teks || teks === '-') return;
            navigator.clipboard.writeText(teks).then(() => {
                hcisMessage.success('Nomor transaksi berhasil disalin ke clipboard.');
            }).catch(() => {
                hcisMessage.info(`Nomor Transaksi: ${teks}`);
            });
        },
        muatUlang() {
            window.location.reload();
        },
    },
};
</script>

<style scoped>
.verif-page-bg {
    font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}
</style>
