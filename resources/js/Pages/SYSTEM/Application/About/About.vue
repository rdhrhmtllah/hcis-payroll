<template>
    <Head>
        <title>Tentang Perusahaan & Aplikasi - EVO Group</title>
    </Head>

    <main class="about-page">
        <canvas ref="ambientCanvas" class="ab-ambient-canvas" aria-hidden="true"></canvas>

        <!-- ═══════════════ NAVBAR (preserved) ═══════════════ -->
        <nav class="ab-navbar" :class="{ 'is-scrolled': isScrolled }" aria-label="Navigasi halaman tentang">
            <Link class="ab-brand" :href="dashboardUrl">
                <span class="ab-brand__mark">
                    <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                </span>
                <span>
                    <strong>HCIS EVO</strong>
                    <small>Unified Platform</small>
                </span>
            </Link>

            <div class="ab-navlinks" aria-label="Navigasi section">
                <button type="button" @click="scrollToSection('company')">Perusahaan</button>
                <button type="button" @click="scrollToSection('platform')">Aplikasi</button>
                <button type="button" @click="scrollToSection('modules')">Modul</button>
                <button type="button" @click="scrollToSection('version')">Versi</button>
            </div>

            <Link class="ab-nav-cta" :href="dashboardUrl">
                <i class="bi bi-arrow-left"></i>
                <span>Dashboard</span>
            </Link>
        </nav>

        <!-- ═══════════════ HERO (100% preserved) ═══════════════ -->
        <section id="top" class="ab-hero">
            <video
                v-if="!videoFailed"
                ref="heroVideo"
                class="ab-hero-video"
                autoplay
                muted
                loop
                playsinline
                preload="auto"
                @error="handleVideoError"
            >
                <source :src="heroVideoUrl.replace('.mp4', '.webm')" type="video/webm" />
                <source :src="heroVideoUrl" type="video/mp4" />
            </video>

            <canvas ref="heroCanvas" class="ab-hero-canvas" aria-hidden="true"></canvas>
            <div class="ab-hero-overlay" aria-hidden="true"></div>

            <div ref="heroContent" class="ab-hero-content ab-reveal">
                <div class="ab-hero-logo">
                    <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                </div>

                <p class="ab-eyebrow">EVO Group</p>
                <h1>Human Capital Information System.</h1>
                <p class="ab-hero-subtitle">
                    Satu platform terintegrasi untuk seluruh kebutuhan SDM, KPI, absensi, shift, dan operasional
                    internal EVO Group.
                </p>

                <div class="ab-hero-actions">
                    <button class="ab-btn ab-btn--primary" type="button" @click="scrollToSection('modules')">
                        <span>Jelajahi Fitur</span>
                        <i class="bi bi-arrow-down-short"></i>
                    </button>
                    <Link class="ab-btn ab-btn--ghost" :href="dashboardUrl">
                        <i class="bi bi-arrow-left"></i>
                        <span>Kembali ke Dashboard</span>
                    </Link>
                </div>
            </div>

            <button
                class="ab-scroll-cue"
                type="button"
                aria-label="Scroll ke konten"
                @click="scrollToSection('company')"
            >
                <span></span>
            </button>
        </section>

        <!-- ═══════════════ CONTENT SECTIONS (redesigned) ═══════════════ -->
        <div class="ab-section-shell">
            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ COMPANY ══════════ -->
            <section id="company" class="ab-section ab-company">
                <div class="ab-company-visual ab-reveal reveal-left">
                    <div class="ab-liquid-card ab-company-card">
                        <div class="ab-company-card__shimmer-border"></div>
                        <div class="ab-company-card__shine"></div>
                        <div class="ab-company-card__orb ab-company-card__orb--1" aria-hidden="true"></div>
                        <div class="ab-company-card__orb ab-company-card__orb--2" aria-hidden="true"></div>
                        <div class="ab-company-card__orb ab-company-card__orb--3" aria-hidden="true"></div>

                        <div class="ab-company-card__logo" style="margin-top: 1.5rem;">
                            <div class="ab-company-card__logo-glow" aria-hidden="true"></div>
                            <div class="ab-company-card__logo-ring" aria-hidden="true"></div>
                            <img src="/logo/logo.png" alt="Evopet" />
                        </div>

                        <div class="ab-company-card__content">
                            <span>PT. Evo Nusa Bersaudara</span>
                            <strong>Evopet</strong>
                            <p>Pilihan kebutuhan hewan peliharaan yang berkualitas, aman, dan mudah dijangkau.</p>
                        </div>

                        <div class="ab-company-card__divider" aria-hidden="true"></div>

                        <div class="ab-company-highlight-grid">
                            <span v-for="item in companyHighlights" :key="item.label">
                                <i :class="item.icon"></i>
                                <small>{{ item.label }}</small>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="ab-section-copy ab-company-copy ab-reveal reveal-right">
                    <span class="ab-section-tag"><i class="bi bi-building"></i> Tentang Perusahaan</span>
                    <h2>Evopet hadir untuk kebutuhan pawparents yang lebih yakin dan terjangkau.</h2>

                    <div class="ab-company-story">
                        <p>
                            PT. Evo Nusa Bersaudara merupakan perusahaan pemasok makanan kucing, makanan anjing, dan
                            perlengkapan hewan peliharaan lainnya. Evopet hadir dengan produk berkualitas, harga
                            bersaing, aman, disukai hewan peliharaan, dan mendukung kebutuhan pawparents secara lebih
                            lengkap.
                        </p>
                        <p>
                            Evopet berangkat dari keresahan pawparents dalam memilih makanan kucing dan anjing yang
                            berkualitas namun tetap terjangkau. Banyaknya pilihan produk membuat pemilik hewan perlu
                            lebih teliti dalam menentukan konsumsi harian untuk anak bulu mereka.
                        </p>
                        <p>
                            Melalui berbagai brand seperti Life Cat, Ori Cat, Life Dog, Ori Dog, Bio Creamy, dan produk
                            lainnya, Evopet terus berkembang bersama masukan serta dukungan pawparents.
                        </p>
                    </div>

                    <div class="ab-brand-showcase">
                        <div class="ab-brand-showcase__head">
                            <span>Brand yang Kami Hadirkan</span>
                            <p>Makanan kucing, makanan anjing, dan kebutuhan hewan peliharaan pilihan.</p>
                        </div>

                        <div class="ab-pet-brand-grid">
                            <article v-for="brand in petBrands" :key="brand.name" class="ab-pet-brand-card">
                                <img :src="brand.logo" :alt="brand.name" />
                                <strong>{{ brand.name }}</strong>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ SUBSIDIARIES ══════════ -->
            <section class="ab-section">
                <div class="ab-section-head ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-building"></i> Supported Group Companies</span>
                    <h2>Bagian dari ekosistem EVO Group.</h2>
                    <p>Entitas yang saling berkolaborasi untuk mewujudkan layanan dan produk operasional terbaik.</p>
                </div>

                <div class="ab-subsidiary-grid" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; max-width: 950px; margin: 0 auto;">
                    <article
                        v-for="(company, index) in subsidiaries"
                        :key="company.id"
                        class="ab-liquid-card ab-tilt ab-reveal"
                        :style="{ '--delay': `${index * 90}ms`, padding: '1.75rem 1.5rem', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', textAlign: 'center', flex: '1 1 260px', minHeight: '160px' }"
                    >
                        <div class="ab-card-topline"></div>
                        <div style="height: 65px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; width: 100%;">
                            <img :src="company.logo" :alt="company.name" style="max-height: 100%; max-width: 85%; object-fit: contain;" />
                        </div>
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; line-height: 1.4;">{{ company.name }}</h3>
                    </article>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ VISI & MISI ══════════ -->
            <section class="ab-section ab-vision-section">
                <!-- Visi Banner -->
                <article class="ab-vision-banner ab-reveal">
                    <div class="ab-vision-quote-icon" aria-hidden="true">&ldquo;</div>
                    <span class="ab-section-tag"><i class="bi bi-eye"></i> Visi</span>
                    <h2>
                        Menjadi perusahaan penyedia segala kebutuhan kucing dan anjing yang terbaik, unggul, dan
                        terpercaya di Indonesia.
                    </h2>
                </article>

                <!-- Misi Cards -->
                <div class="ab-misi-wrapper ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-rocket-takeoff"></i> Misi</span>
                    <div class="ab-misi-grid">
                        <article
                            v-for="(mission, index) in missions"
                            :key="mission"
                            class="ab-misi-card"
                            :style="{ '--delay': `${index * 100}ms` }"
                        >
                            <span class="ab-misi-number">{{ String(index + 1).padStart(2, '0') }}</span>
                            <p>{{ mission }}</p>
                            <i class="bi bi-check-circle-fill ab-misi-check"></i>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ PLATFORM ══════════ -->
            <section id="platform" class="ab-section ab-platform-section">
                <div class="ab-section-head ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-cpu"></i> Tentang Aplikasi</span>
                    <h2>Platform internal untuk pengalaman kerja yang lebih teratur.</h2>
                    <p>
                        HCIS membantu karyawan, leader, dan tim HR melihat informasi penting, mengirim pengajuan, serta
                        memantau proses kerja lintas modul dalam satu tempat.
                    </p>
                    <div class="ab-version-pill">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>v{{ appVersion }} aktif sejak 2026</span>
                    </div>
                </div>

                <div class="ab-pillar-grid">
                    <article
                        v-for="(pillar, index) in pillars"
                        :key="pillar.title"
                        class="ab-liquid-card ab-pillar-card ab-reveal"
                        :style="{ '--delay': `${index * 80}ms` }"
                    >
                        <div class="ab-pillar-icon-area">
                            <i :class="pillar.icon"></i>
                        </div>
                        <h3>{{ pillar.title }}</h3>
                        <p>{{ pillar.description }}</p>
                    </article>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ MODULES ══════════ -->
            <section id="modules" class="ab-section">
                <div class="ab-section-head ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-grid-3x3-gap"></i> Fitur &amp; Modul</span>
                    <h2>Akses cepat ke modul inti HCIS.</h2>
                    <p>
                        Modul dibuat untuk mendukung pekerjaan harian dari dashboard personal sampai review lintas
                        divisi.
                    </p>
                </div>

                <div class="ab-module-grid">
                    <Link
                        v-for="(module, index) in modules"
                        :key="module.title"
                        class="ab-liquid-card ab-module-card ab-tilt ab-reveal"
                        :href="module.url"
                        :style="{ '--delay': `${index * 70}ms` }"
                    >
                        <div class="ab-module-icon-area">
                            <i :class="module.icon"></i>
                        </div>
                        <div class="ab-module-content">
                            <h3>{{ module.title }}</h3>
                            <p>{{ module.description }}</p>
                        </div>
                        <div class="ab-module-status">
                            <span class="ab-pulse-dot"></span>
                            <small>Aktif</small>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ TIMELINE / STEPS ══════════ -->
            <section class="ab-section">
                <div class="ab-section-head ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-signpost-2"></i> Panduan Mulai</span>
                    <h2>Alur singkat untuk karyawan baru.</h2>
                </div>

                <div class="ab-timeline">
                    <div class="ab-timeline-line" aria-hidden="true"></div>
                    <article
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="ab-timeline-item ab-reveal"
                        :class="{ 'is-right': index % 2 !== 0 }"
                        :style="{ '--delay': `${index * 130}ms` }"
                    >
                        <div class="ab-timeline-dot">
                            <span>{{ index + 1 }}</span>
                        </div>
                        <div class="ab-timeline-card">
                            <i :class="step.icon"></i>
                            <h3>{{ step.title }}</h3>
                            <p>{{ step.description }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <!-- ── Gradient Divider ── -->
            <div class="ab-section-divider" aria-hidden="true"></div>

            <!-- ══════════ CHANGELOG / VERSION HISTORY ══════════ -->
            <section id="version" class="ab-section ab-changelog-section">
                <div class="ab-section-head ab-reveal">
                    <span class="ab-section-tag"><i class="bi bi-journal-code"></i> Versi & Pembaruan</span>
                    <h2>Change Log Aplikasi.</h2>
                    <p>Jejak pembaruan dan peningkatan fitur pada sistem terintegrasi EVO Group.</p>
                </div>

                <div
                    class="ab-changelog-list"
                    style="display: flex; flex-direction: column; gap: 1.5rem; max-width: 800px; margin: 0 auto"
                >
                    <article
                        v-for="(log, index) in changelogs"
                        :key="log.version"
                        class="ab-liquid-card ab-reveal"
                        :style="{ '--delay': `${index * 90}ms`, padding: '1.75rem', textAlign: 'left' }"
                    >
                        <div
                            style="
                                display: flex;
                                flex-wrap: wrap;
                                justify-content: space-between;
                                align-items: center;
                                gap: 1rem;
                                margin-bottom: 1.2rem;
                                border-bottom: 1px solid rgba(148, 163, 184, 0.2);
                                padding-bottom: 1rem;
                            "
                        >
                            <div style="display: flex; align-items: center; gap: 0.8rem">
                                <span
                                    style="
                                        background: linear-gradient(135deg, var(--primary, #6366f1), #2563eb);
                                        color: white;
                                        padding: 0.35rem 0.75rem;
                                        border-radius: 999px;
                                        font-weight: 900;
                                        font-size: 0.75rem;
                                        letter-spacing: 0.05em;
                                        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
                                    "
                                    >{{ log.version }}</span
                                >
                                <h3
                                    style="
                                        margin: 0;
                                        font-size: 1.2rem;
                                        font-weight: 900;
                                        color: #0f172a;
                                        letter-spacing: -0.01em;
                                    "
                                >
                                    {{ log.title }}
                                </h3>
                            </div>
                            <span
                                style="
                                    color: #64748b;
                                    font-size: 0.8rem;
                                    font-weight: 800;
                                    background: rgba(241, 245, 249, 0.8);
                                    padding: 0.3rem 0.7rem;
                                    border-radius: 0.5rem;
                                "
                                ><i class="bi bi-calendar-event" style="margin-right: 0.4rem"></i> {{ log.date }}</span
                            >
                        </div>
                        <p
                            style="
                                margin: 0 0 1.2rem 0;
                                font-size: 0.88rem;
                                color: #475569;
                                line-height: 1.6;
                                font-weight: 600;
                            "
                        >
                            {{ log.description }}
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.6rem">
                            <div
                                v-for="feat in log.features"
                                :key="feat.text"
                                style="display: flex; gap: 0.6rem; align-items: flex-start"
                            >
                                <span
                                    :style="{
                                        padding: '0.2rem 0.5rem',
                                        borderRadius: '0.35rem',
                                        fontSize: '0.65rem',
                                        fontWeight: '900',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.04em',
                                        minWidth: '3.8rem',
                                        textAlign: 'center',
                                        color:
                                            feat.type === 'New'
                                                ? '#059669'
                                                : feat.type === 'Update'
                                                  ? '#0284c7'
                                                  : '#ea580c',
                                        background:
                                            feat.type === 'New'
                                                ? '#d1fae5'
                                                : feat.type === 'Update'
                                                  ? '#e0f2fe'
                                                  : '#ffedd5',
                                    }"
                                    >{{ feat.type }}</span
                                >
                                <span
                                    style="
                                        font-size: 0.84rem;
                                        color: #334155;
                                        line-height: 1.5;
                                        font-weight: 600;
                                        padding-top: 0.1rem;
                                    "
                                    >{{ feat.text }}</span
                                >
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <!-- ═══════════════ DARK FOOTER ═══════════════ -->
        <footer class="ab-footer-dark">
            <div class="ab-footer-inner">
                <div class="ab-footer-brand">
                    <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                    <p>EVO Group Unified Platform untuk kolaborasi internal yang lebih jernih dan terukur.</p>
                </div>

                <div class="ab-footer-links">
                    <div>
                        <strong>Perusahaan</strong>
                        <button type="button" @click="scrollToSection('company')">Tentang</button>
                        <button type="button" @click="scrollToSection('company')">Visi Misi</button>
                        <button type="button" @click="scrollToSection('company')">Anak Perusahaan</button>
                    </div>
                    <div>
                        <strong>Modul Aplikasi</strong>
                        <Link href="/homepage">Dashboard</Link>
                        <Link href="/formKPI">KPI</Link>
                        <Link href="/overtime">Izin &amp; Lembur</Link>
                    </div>
                    <div>
                        <strong>Portal</strong>
                        <Link :href="dashboardUrl">Kembali ke HCIS</Link>
                        <Link href="/login">Login</Link>
                    </div>
                </div>

                <div class="ab-footer-bottom">
                    <span>&copy; 2026 EVO Group. Internal Use Only.</span>
                    <span class="ab-socials">
                        <i class="bi bi-instagram"></i>
                        <i class="bi bi-linkedin"></i>
                    </span>
                </div>
            </div>
        </footer>
    </main>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps({
    appVersion: {
        type: String,
        default: '2.13.0',
    },
    heroVideoUrl: {
        type: String,
        default: '/assets/videos/about-hero.mp4',
    },
    userName: {
        type: String,
        default: 'User',
    },
    dashboardUrl: {
        type: String,
        default: '/homepage',
    },
});

const ambientCanvas = ref(null);
const heroCanvas = ref(null);
const heroContent = ref(null);
const heroVideo = ref(null);
const isScrolled = ref(false);
const videoFailed = ref(false);

const appVersion = computed(() => props.appVersion || '2.13.0');
const heroVideoUrl = computed(() => props.heroVideoUrl || '/assets/videos/about-hero.mp4');
const userName = computed(() => props.userName || 'User');
const dashboardUrl = computed(() => props.dashboardUrl || '/homepage');

const companyHighlights = [
    { icon: 'bi bi-patch-check', label: 'Kualitas terjaga' },
    { icon: 'bi bi-tag', label: 'Harga bersaing' },
    { icon: 'bi bi-heart', label: 'Disukai peliharaan' },
    { icon: 'bi bi-bag-check', label: 'Kebutuhan lengkap' },
];

const petBrands = [
    { name: 'Life Cat', logo: '/img/brand/LOGO-LIFECAT.webp' },
    { name: 'Ori Cat', logo: '/img/brand/Ori-Cat.webp' },
    { name: 'Ori Dog', logo: '/img/brand/ori-dog.webp' },
    { name: 'Bio Creamy', logo: '/img/brand/LOGO-BIO.webp' },
    { name: 'Taro', logo: '/img/brand/Logo-taro.webp' },
    { name: 'Top', logo: '/img/brand/Top.webp' },
];

const subsidiaries = [
    {
        id: 'emi',
        logo: '/logo/EMI.png',
        name: 'PT Evo Manufacturing Indonesia',
        description: 'Entitas manufaktur yang mendukung kebutuhan produksi dan operasional grup.',
        highlight: 'Manufacturing Excellence',
    },
    {
        id: 'enb',
        logo: '/logo/ENB.png',
        name: 'PT Evo Nusa Bersaudara',
        description: 'Pusat kolaborasi bisnis, people operation, dan pengembangan layanan internal.',
        highlight: 'People & Operations',
    },
    {
        id: 'gmn',
        logo: '/logo/GMN.png',
        name: 'PT Graha Maju Nusantara',
        description: 'Entitas pendukung ekosistem operasional dan perluasan jaringan perusahaan.',
        highlight: 'Business Network',
    },
];

const missions = [
    'Menghadirkan pelayanan terbaik dan menghasilkan kepuasan dari konsumen.',
    'Membangun kerja sama yang berkelanjutan dengan pelanggan dan mitra bisnis.',
    'Mengembangkan jalur distribusi yang merata ke seluruh Indonesia.',
    'Meningkatkan kualitas SDM yang ada demi mencapai target perusahaan.',
];

const pillars = [
    {
        icon: 'bi bi-person-lines-fill',
        title: 'HR Management',
        description: 'Profil, akses, pengajuan, dan proses administrasi karyawan.',
    },
    {
        icon: 'bi bi-graph-up-arrow',
        title: 'KPI Tracking',
        description: 'Pemantauan target, performa, dan review tahunan secara terstruktur.',
    },
    {
        icon: 'bi bi-calendar2-week',
        title: 'Absensi & Shift',
        description: 'Ringkasan kehadiran, jadwal kerja, shift, dan perbaikan data.',
    },
    {
        icon: 'bi bi-book',
        title: 'Learning Management System (LMS)',
        description: 'Akses materi pembelajaran, pelatihan, dan pengembangan kompetensi karyawan.',
    },
];

const modules = [
    {
        icon: 'bi bi-speedometer2',
        title: 'Dashboard HCIS',
        description: 'Ringkasan personal, notifikasi, jadwal, dan akses cepat.',
        url: '/homepage',
    },
    {
        icon: 'bi bi-bar-chart-line',
        title: 'KPI Management',
        description: 'Kelola KPI, performa, approval, dan monitoring tim.',
        url: '/formKPI',
    },
    {
        icon: 'bi bi-file-earmark-check',
        title: 'Manajemen Izin',
        description: 'Pengajuan izin, riwayat, dan approval atasan.',
        url: '/izin',
    },
    {
        icon: 'bi bi-clock-history',
        title: 'Lembur & Overtime',
        description: 'Pengajuan lembur, validasi jadwal, dan status approval.',
        url: '/overtime',
    },
    {
        icon: 'bi bi-book',
        title: 'Learning Management System (LMS)',
        description: 'Akses materi pembelajaran, pelatihan, dan pengembangan kompetensi karyawan.',
        url: '/lms',
    },
    {
        icon: 'bi bi-calendar-check',
        title: 'Absensi & Shift',
        description: 'Pantau kehadiran, shift kerja, dan jadwal mingguan.',
        url: '/uDash',
    },
];

const changelogs = [
    {
        version: 'v2.13.0',
        date: '3 Juni 2026',
        title: 'Optimasi Performa & UI',
        description:
            'Pembaruan difokuskan pada peningkatan performa memuat data dashboard serta penyempurnaan antarmuka agar lebih responsif.',
        features: [
            { type: 'New', text: 'Penambahan grafik perbandingan KPI bulanan di dashboard.' },
            { type: 'Update', text: 'Desain ulang halaman profil untuk pengalaman yang lebih rapi.' },
            { type: 'Fix', text: 'Memperbaiki kendala ekspor laporan pada perangkat mobile.' },
        ],
    },
    {
        version: 'v2.12.5',
        date: '15 Mei 2026',
        title: 'Integrasi LMS Baru',
        description:
            'Sistem Pembelajaran (LMS) kini terhubung langsung dengan portal HCIS untuk kemudahan akses edukasi karyawan.',
        features: [
            { type: 'New', text: 'Single Sign-On (SSO) untuk modul Learning Management System.' },
            { type: 'Update', text: 'Penyempurnaan alur notifikasi persetujuan lembur.' },
            { type: 'Fix', text: 'Perbaikan bug minor pada sinkronisasi sisa cuti.' },
        ],
    },
    {
        version: 'v2.11.0',
        date: '20 April 2026',
        title: 'Rilis Modul Shift & Absensi',
        description:
            'Pembaruan besar dengan merilis modul pengaturan shift kerja untuk mendukung fleksibilitas tim operasional.',
        features: [
            { type: 'New', text: 'Fitur manajemen shift 3-tier (Header, Detail Karyawan, Tanggal).' },
            { type: 'New', text: 'Mendukung integrasi penarikan data log absensi.' },
        ],
    },
];

const steps = [
    {
        icon: 'bi bi-book',
        title: 'Baca Materi LMS',
        description: 'Pelajari panduan dan materi onboarding untuk karyawan baru di LMS.',
    },
    {
        icon: 'bi bi-box-arrow-in-right',
        title: 'Login Akun',
        description: 'Masuk menggunakan akun internal yang sudah diberikan oleh tim terkait.',
    },
    {
        icon: 'bi bi-grid-1x2',
        title: 'Kenali Dashboard',
        description: 'Lihat ringkasan absensi, KPI, jadwal, notifikasi, dan menu cepat.',
    },
    {
        icon: 'bi bi-send-check',
        title: 'Submit Izin/Lembur',
        description: 'Gunakan modul pengajuan untuk kebutuhan operasional harian.',
    },
    {
        icon: 'bi bi-activity',
        title: 'Pantau KPI',
        description: 'Ikuti progress KPI dan status review agar target tetap jelas.',
    },
];

let canvasAnimationIds = [];
let observers = [];
let cleanups = [];

function handleVideoError() {
    videoFailed.value = true;
}

function scrollToSection(id) {
    const target = document.getElementById(id);
    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: shouldReduceMotion() ? 'auto' : 'smooth', block: 'start' });
}

function shouldReduceMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function handleScroll() {
    isScrolled.value = window.scrollY > 36;

    if (!heroContent.value || shouldReduceMotion()) {
        return;
    }

    const progress = Math.min(window.scrollY / 520, 1);
    heroContent.value.style.transform = `translate3d(0, ${progress * 28}px, 0)`;
    heroContent.value.style.opacity = String(1 - progress * 0.25);
}

function getAccentColor() {
    const styles = getComputedStyle(document.documentElement);
    const primary = styles.getPropertyValue('--primary').trim() || '#6366f1';
    const fallback = { r: 99, g: 102, b: 241 };

    if (!primary.startsWith('#')) {
        return fallback;
    }

    const value = primary.slice(1);
    const normalized =
        value.length === 3
            ? value
                  .split('')
                  .map((part) => part + part)
                  .join('')
            : value;

    const number = Number.parseInt(normalized, 16);
    if (Number.isNaN(number)) {
        return fallback;
    }

    return {
        r: (number >> 16) & 255,
        g: (number >> 8) & 255,
        b: number & 255,
    };
}

function startLiquidCanvas(canvasRef, variant = 'ambient') {
    const canvas = canvasRef.value;
    if (!canvas || shouldReduceMotion()) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const accent = getAccentColor();
    const particles = Array.from({ length: variant === 'hero' ? 44 : 22 }, (_, index) => ({
        x: Math.random(),
        y: Math.random(),
        radius: 1.4 + Math.random() * 2.6,
        speed: 0.00018 + Math.random() * 0.00024,
        phase: Math.random() * Math.PI * 2 + index,
    }));

    let width = 0;
    let height = 0;
    let rafId = null;

    const resize = () => {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        width = canvas.offsetWidth || window.innerWidth;
        height = canvas.offsetHeight || window.innerHeight;
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    };

    const drawBlob = (x, y, radius, alpha, color = accent) => {
        const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
        gradient.addColorStop(0, `rgba(${color.r}, ${color.g}, ${color.b}, ${alpha})`);
        gradient.addColorStop(0.55, `rgba(${color.r}, ${color.g}, ${color.b}, ${alpha * 0.38})`);
        gradient.addColorStop(1, `rgba(${color.r}, ${color.g}, ${color.b}, 0)`);
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fill();
    };

    const draw = (time) => {
        ctx.clearRect(0, 0, width, height);

        const cyan = { r: 6, g: 182, b: 212 };
        drawBlob(width * (0.18 + Math.sin(time * 0.00018) * 0.04), height * 0.18, width * 0.34, 0.18);
        drawBlob(width * 0.82, height * (0.18 + Math.cos(time * 0.00016) * 0.05), width * 0.28, 0.13, cyan);
        drawBlob(width * 0.5, height * 0.85, width * 0.4, 0.1);

        ctx.lineWidth = 1;
        particles.forEach((particle, index) => {
            const x = ((particle.x + time * particle.speed) % 1) * width;
            const y = (particle.y + Math.sin(time * 0.00055 + particle.phase) * 0.045) * height;
            ctx.fillStyle = `rgba(${accent.r}, ${accent.g}, ${accent.b}, ${variant === 'hero' ? 0.32 : 0.18})`;
            ctx.beginPath();
            ctx.arc(x, y, particle.radius, 0, Math.PI * 2);
            ctx.fill();

            const next = particles[index + 1];
            if (!next || index % 2) {
                return;
            }

            const nx = ((next.x + time * next.speed) % 1) * width;
            const ny = (next.y + Math.sin(time * 0.00055 + next.phase) * 0.045) * height;
            const distance = Math.hypot(nx - x, ny - y);
            if (distance < 170) {
                ctx.strokeStyle = `rgba(${accent.r}, ${accent.g}, ${accent.b}, ${0.14 * (1 - distance / 170)})`;
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(nx, ny);
                ctx.stroke();
            }
        });

        rafId = window.requestAnimationFrame(draw);
    };

    resize();
    window.addEventListener('resize', resize, { passive: true });
    rafId = window.requestAnimationFrame(draw);
    canvasAnimationIds.push(() => window.cancelAnimationFrame(rafId));
    cleanups.push(() => window.removeEventListener('resize', resize));
}

function initReveal() {
    const elements = [...document.querySelectorAll('.ab-reveal')];
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.16, rootMargin: '0px 0px -8% 0px' },
    );

    elements.forEach((element) => observer.observe(element));
    observers.push(observer);
}

function initCounters() {
    const counters = [...document.querySelectorAll('[data-counter]')];
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const element = entry.target;
                const target = Number(element.dataset.target || 0);
                const duration = shouldReduceMotion() ? 1 : 1200;
                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 4);
                    element.textContent = String(Math.round(target * eased));

                    if (progress < 1) {
                        window.requestAnimationFrame(tick);
                    }
                };

                window.requestAnimationFrame(tick);
                observer.unobserve(element);
            });
        },
        { threshold: 0.45 },
    );

    counters.forEach((counter) => observer.observe(counter));
    observers.push(observer);
}

function initTilt() {
    if (shouldReduceMotion() || !window.matchMedia('(hover: hover)').matches) {
        return;
    }

    const cards = [...document.querySelectorAll('.ab-tilt')];

    const handleMove = (event) => {
        const card = event.currentTarget;
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        card.style.transform = `perspective(900px) rotateX(${y * -5}deg) rotateY(${x * 6}deg) translateY(-6px)`;
    };

    const handleLeave = (event) => {
        event.currentTarget.style.transform = '';
    };

    cards.forEach((card) => {
        card.addEventListener('pointermove', handleMove);
        card.addEventListener('pointerleave', handleLeave);
    });

    cleanups.push(() => {
        cards.forEach((card) => {
            card.removeEventListener('pointermove', handleMove);
            card.removeEventListener('pointerleave', handleLeave);
        });
    });
}

function initStatLines() {
    const lines = [...document.querySelectorAll('.ab-stat-line')];
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.45 },
    );

    lines.forEach((line) => observer.observe(line));
    observers.push(observer);
}

onMounted(() => {
    startLiquidCanvas(ambientCanvas, 'ambient');
    startLiquidCanvas(heroCanvas, 'hero');
    initReveal();
    initCounters();
    initStatLines();
    initTilt();
    handleScroll();

    window.addEventListener('scroll', handleScroll, { passive: true });
    cleanups.push(() => window.removeEventListener('scroll', handleScroll));
});

onUnmounted(() => {
    canvasAnimationIds.forEach((cancel) => cancel());
    observers.forEach((observer) => observer.disconnect());
    cleanups.forEach((cleanup) => cleanup());
    canvasAnimationIds = [];
    observers = [];
    cleanups = [];
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════
   BASE & PAGE
   ═══════════════════════════════════════════════════════════ */
.about-page {
    width: 100%;
    min-height: 100vh;
    color: var(--hcis-slate-900);
    font-family: 'Inter', sans-serif;
    font-size: 13.5px;
    background:
        radial-gradient(circle at 15% 10%, rgba(99, 102, 241, 0.12), transparent 24rem),
        radial-gradient(circle at 88% 12%, rgba(6, 182, 212, 0.1), transparent 22rem),
        linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-indigo-50) 42%, var(--hcis-white) 100%);
    overflow-x: hidden;
    isolation: isolate;
}

.ab-ambient-canvas {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.ab-btn,
.ab-brand,
.ab-nav-cta,
.ab-module-card,
.ab-footer-links a {
    text-decoration: none;
}

/* ═══════════════════════════════════════════════════════════
   NAVBAR (preserved)
   ═══════════════════════════════════════════════════════════ */
.ab-navbar {
    position: fixed;
    top: 0.9rem;
    left: 50%;
    z-index: 40;
    width: min(1040px, calc(100vw - 2rem));
    min-height: 3.65rem;
    padding: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.76);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.44);
    backdrop-filter: blur(20px) saturate(1.16);
    -webkit-backdrop-filter: blur(20px) saturate(1.16);
    box-shadow:
        0 18px 44px rgba(15, 23, 42, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.74);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transform: translateX(-50%);
    transition:
        background 220ms ease,
        box-shadow 220ms ease,
        transform 220ms ease;
}

.ab-navbar.is-scrolled {
    background: rgba(255, 255, 255, 0.82);
    box-shadow:
        0 16px 38px rgba(15, 23, 42, 0.14),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transform: translateX(-50%) translateY(-0.1rem);
}

.ab-brand {
    min-width: 0;
    color: var(--hcis-slate-900);
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
}

.ab-brand__mark {
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 0.82rem;
    border: 1px solid rgba(199, 210, 254, 0.72);
    background:
        radial-gradient(circle at 30% 12%, rgba(255, 255, 255, 0.95), transparent 42%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.82), rgba(219, 234, 254, 0.58));
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.82),
        0 10px 24px rgba(79, 70, 229, 0.16);
    display: grid;
    place-items: center;
    overflow: hidden;
}

.ab-brand__mark img {
    width: 82%;
    height: 82%;
    object-fit: contain;
    mix-blend-mode: multiply;
}

.ab-brand strong,
.ab-brand small {
    display: block;
    line-height: 1;
}

.ab-brand strong {
    font-size: 0.9rem;
    font-weight: 900;
}

.ab-brand small {
    margin-top: 0.26rem;
    color: var(--hcis-slate-500);
    font-size: 0.63rem;
    font-weight: 800;
}

.ab-navlinks {
    margin-left: auto;
    padding: 0.22rem;
    border-radius: 999px;
    background: rgba(248, 250, 252, 0.7);
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
}

.ab-navlinks button,
.ab-footer-links button {
    border: none;
    background: transparent;
    font: inherit;
    cursor: pointer;
}

.ab-navlinks button {
    min-height: 2.1rem;
    padding: 0 0.75rem;
    border-radius: 999px;
    color: var(--hcis-slate-600);
    font-size: 0.74rem;
    font-weight: 900;
    transition:
        background 180ms ease,
        color 180ms ease;
}

.ab-navlinks button:hover {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary-dark);
}

.ab-nav-cta {
    min-height: 2.35rem;
    padding: 0 0.85rem;
    border-radius: 0.78rem;
    color: var(--hcis-white);
    font-size: 0.76rem;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    box-shadow: 0 12px 26px rgba(79, 70, 229, 0.24);
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
}

/* ═══════════════════════════════════════════════════════════
   HERO (100% preserved)
   ═══════════════════════════════════════════════════════════ */
.ab-hero {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    min-height: 100dvh;
    padding: clamp(6rem, 10vw, 7rem) 1.25rem clamp(3rem, 5vw, 4rem);
    display: grid;
    place-items: center;
    overflow: hidden;
}

.ab-hero-video,
.ab-hero-canvas,
.ab-hero-overlay {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.ab-hero-video {
    object-fit: cover;
    opacity: 0.72;
    filter: saturate(1.05) contrast(0.98) brightness(1.04);
}

.ab-hero-canvas {
    opacity: 0.48;
    mix-blend-mode: screen;
}

.ab-hero-overlay {
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.54), rgba(238, 242, 255, 0.34) 48%, rgba(255, 255, 255, 0.78)),
        radial-gradient(circle at center, rgba(255, 255, 255, 0.02), rgba(15, 23, 42, 0.12));
    pointer-events: none;
}

.ab-hero-content {
    position: relative;
    z-index: 2;
    width: min(780px, 100%);
    text-align: center;
    will-change: transform, opacity;
}

.ab-hero-logo {
    width: clamp(4rem, 7.6vw, 5.9rem);
    height: clamp(4rem, 7.6vw, 5.9rem);
    margin: 0 auto 0.9rem;
    border-radius: 1.35rem;
    border: 1px solid rgba(255, 255, 255, 0.82);
    background:
        radial-gradient(circle at 30% 18%, rgba(255, 255, 255, 0.96), transparent 45%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.74), rgba(219, 234, 254, 0.48));
    backdrop-filter: blur(16px);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.86),
        0 22px 60px rgba(79, 70, 229, 0.2);
    display: grid;
    place-items: center;
    animation: logoFloat 5.2s ease-in-out infinite;
}

.ab-hero-logo img {
    width: 78%;
    height: 78%;
    object-fit: contain;
    mix-blend-mode: multiply;
}

.ab-eyebrow {
    color: var(--primary-dark);
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ab-hero h1 {
    max-width: 650px;
    margin: 0 auto;
    color: var(--hcis-slate-900);
    font-size: clamp(1.9rem, 4.6vw, 3.45rem);
    line-height: 1.06;
    font-weight: 900;
    letter-spacing: 0;
}

.ab-hero-subtitle {
    max-width: 560px;
    margin: 0.85rem auto 0;
    color: var(--hcis-slate-600);
    font-size: clamp(0.86rem, 1.15vw, 0.96rem);
    line-height: 1.56;
    font-weight: 600;
}

.ab-hero-actions {
    margin-top: 1.15rem;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.85rem;
}

.ab-btn {
    min-height: 2.75rem;
    padding: 0 1rem;
    border: 1px solid transparent;
    border-radius: 0.85rem;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 900;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    transition:
        transform 220ms var(--liquid-ease),
        box-shadow 220ms ease,
        background 220ms ease;
}

.ab-btn:hover {
    transform: translateY(-2px);
}

.ab-btn--primary {
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    box-shadow: 0 16px 34px rgba(79, 70, 229, 0.26);
}

.ab-btn--primary.is-sent {
    background: linear-gradient(135deg, var(--hcis-emerald-600), var(--hcis-blue-600));
}

.ab-btn--ghost {
    color: var(--hcis-slate-700);
    border-color: rgba(255, 255, 255, 0.82);
    background: rgba(255, 255, 255, 0.58);
    backdrop-filter: blur(14px);
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
}

.ab-btn--shimmer {
    position: relative;
    overflow: hidden;
}

.ab-btn--shimmer::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
    animation: shimmerSweep 3s ease-in-out infinite;
    pointer-events: none;
}

.ab-scroll-cue {
    position: absolute;
    bottom: 1.5rem;
    left: 50%;
    z-index: 3;
    width: 2.2rem;
    height: 3.25rem;
    border: 1px solid rgba(99, 102, 241, 0.28);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.54);
    backdrop-filter: blur(12px);
    transform: translateX(-50%);
    cursor: pointer;
}

.ab-scroll-cue span {
    width: 0.42rem;
    height: 0.42rem;
    margin: 0.7rem auto 0;
    border-radius: 999px;
    background: var(--primary);
    display: block;
    animation: scrollCue 1.6s ease-in-out infinite;
}

/* ═══════════════════════════════════════════════════════════
   SECTION SHELL & COMMON
   ═══════════════════════════════════════════════════════════ */
.ab-section-shell {
    position: relative;
    z-index: 2;
    padding: 0 1rem 3rem;
}

.ab-section {
    width: min(1040px, 100%);
    margin: 0 auto;
    padding: clamp(2.5rem, 5vw, 4rem) 0;
    scroll-margin-top: 5.5rem;
}

.ab-section-divider {
    width: min(600px, 80%);
    height: 1px;
    margin: 0 auto;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.28), transparent);
}

/* Section Tag — pill with icon */
.ab-section-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.78rem;
    border-radius: 999px;
    border: 1px solid rgba(199, 210, 254, 0.65);
    background: rgba(238, 242, 255, 0.55);
    color: var(--primary-dark);
    font-size: 0.66rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    backdrop-filter: blur(8px);
}

.ab-section-tag i {
    font-size: 0.72rem;
}

/* Section Head */
.ab-section-head {
    max-width: 600px;
    margin: 0 auto 1.6rem;
    text-align: center;
}

.ab-section-head h2,
.ab-section-copy h2,
.ab-support-info h2 {
    margin: 0.65rem 0 0;
    color: var(--hcis-slate-900);
    font-size: clamp(1.35rem, 2.4vw, 2rem);
    line-height: 1.18;
    font-weight: 900;
    letter-spacing: -0.01em;
}

.ab-section-head p,
.ab-section-copy p,
.ab-support-info > p {
    color: var(--hcis-slate-500);
    font-size: 0.86rem;
    line-height: 1.6;
    font-weight: 600;
}

/* Liquid Card (shared base) */
.ab-liquid-card {
    position: relative;
    border: 1px solid var(--liquid-border-strong);
    border-radius: 1rem;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.48)), rgba(255, 255, 255, 0.62);
    backdrop-filter: blur(22px) saturate(1.14);
    -webkit-backdrop-filter: blur(22px) saturate(1.14);
    box-shadow:
        0 12px 34px rgba(15, 23, 42, 0.075),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
    overflow: hidden;
}

.ab-liquid-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.56), transparent 38%);
    pointer-events: none;
}

/* ═══════════════════════════════════════════════════════════
   COMPANY SECTION
   ═══════════════════════════════════════════════════════════ */
.ab-company {
    display: grid;
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
    align-items: start;
    gap: clamp(1.4rem, 2.8vw, 2.2rem);
}

.ab-company-visual {
    position: sticky;
    top: 5.5rem;
    z-index: 1;
}

.ab-company-card {
    padding: clamp(1rem, 2vw, 1.35rem);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    isolation: isolate;
    background:
        radial-gradient(circle at 15% 8%, rgba(99, 102, 241, 0.2), transparent 35%),
        radial-gradient(circle at 90% 15%, rgba(14, 165, 233, 0.16), transparent 32%),
        radial-gradient(circle at 50% 92%, rgba(168, 85, 247, 0.1), transparent 28%),
        linear-gradient(160deg, rgba(255, 255, 255, 0.88), rgba(238, 242, 255, 0.55), rgba(224, 231, 255, 0.45));
    border-radius: 1.15rem;
}

/* Animated shimmer border */
.ab-company-card__shimmer-border {
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    padding: 1.5px;
    background: linear-gradient(
        135deg,
        rgba(99, 102, 241, 0.4),
        rgba(6, 182, 212, 0.28),
        rgba(168, 85, 247, 0.2),
        rgba(99, 102, 241, 0.1),
        rgba(6, 182, 212, 0.35),
        rgba(168, 85, 247, 0.3)
    );
    background-size: 400% 400%;
    animation: shimmerBorder 6s ease infinite;
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    z-index: 0;
}

.ab-company-card__shine {
    position: absolute;
    inset: -22% auto auto -12%;
    width: 70%;
    height: 58%;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.22), rgba(14, 165, 233, 0.12));
    filter: blur(38px);
    z-index: -1;
}

/* Floating orbs */
.ab-company-card__orb {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    z-index: -1;
    filter: blur(1px);
    opacity: 0.55;
}

.ab-company-card__orb--1 {
    width: 6px;
    height: 6px;
    top: 18%;
    right: 12%;
    background: var(--primary);
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.5);
    animation: orbFloat1 4.5s ease-in-out infinite;
}

.ab-company-card__orb--2 {
    width: 4px;
    height: 4px;
    top: 55%;
    right: 8%;
    background: var(--hcis-cyan-500);
    box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
    animation: orbFloat2 5.2s ease-in-out infinite;
}

.ab-company-card__orb--3 {
    width: 5px;
    height: 5px;
    bottom: 22%;
    left: 10%;
    background: var(--hcis-purple-500);
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
    animation: orbFloat3 6s ease-in-out infinite;
}

/* Card header row */
.ab-company-card__header {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.ab-company-card__eyebrow {
    position: relative;
    z-index: 1;
    width: fit-content;
    padding: 0.36rem 0.62rem;
    border: 1px solid rgba(199, 210, 254, 0.78);
    border-radius: 999px;
    color: var(--hcis-indigo-900);
    background: rgba(255, 255, 255, 0.68);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    backdrop-filter: blur(14px);
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
}

.ab-company-card__eyebrow i {
    font-size: 0.62rem;
    color: var(--hcis-indigo-500);
}

.ab-company-card__year {
    padding: 0.3rem 0.55rem;
    border-radius: 999px;
    color: var(--hcis-slate-500);
    background: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(226, 232, 240, 0.6);
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

/* Logo area — enhanced with glow and ring */
.ab-company-card__logo {
    position: relative;
    z-index: 1;
    min-height: 9.5rem;
    border: 1px solid rgba(255, 255, 255, 0.78);
    border-radius: 1.1rem;
    background:
        radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), transparent 55%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.38));
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.92),
        0 8px 28px rgba(99, 102, 241, 0.08);
    display: grid;
    place-items: center;
    overflow: hidden;
}

.ab-company-card__logo-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 60%;
    height: 60%;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 65%);
    transform: translate(-50%, -50%);
    animation: logoGlow 3s ease-in-out infinite;
}

.ab-company-card__logo-ring {
    position: absolute;
    inset: 14%;
    border-radius: 999px;
    border: 1.5px dashed rgba(99, 102, 241, 0.12);
    animation: logoRingSpin 25s linear infinite;
}

.ab-company-card__logo img {
    position: relative;
    z-index: 1;
    width: min(11rem, 68%);
    max-height: 6.8rem;
    object-fit: contain;
    filter: drop-shadow(0 20px 32px rgba(15, 23, 42, 0.18));
    transition: transform 350ms var(--liquid-ease);
}

.ab-company-card:hover .ab-company-card__logo img {
    transform: scale(1.04);
}

/* Content area */
.ab-company-card__content,
.ab-company-highlight-grid {
    position: relative;
    z-index: 1;
}

.ab-company-card__content span,
.ab-company-card__content strong,
.ab-company-card__content p {
    display: block;
}

.ab-company-card__content span {
    color: var(--hcis-indigo-600);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ab-company-card__content strong {
    margin-top: 0.2rem;
    color: var(--hcis-slate-900);
    font-size: clamp(2.1rem, 4.2vw, 3.2rem);
    line-height: 0.94;
    font-weight: 950;
    background: linear-gradient(135deg, var(--hcis-slate-900) 40%, var(--hcis-indigo-600));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ab-company-card__content p {
    max-width: 22rem;
    margin: 0.45rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.79rem;
    line-height: 1.5;
    font-weight: 700;
}

/* Divider line inside card */
.ab-company-card__divider {
    position: relative;
    z-index: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.2), rgba(6, 182, 212, 0.15), transparent);
}

/* Highlight grid — premium glass badges */
.ab-company-highlight-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.ab-company-highlight-grid span {
    min-height: 3.8rem;
    padding: 0.6rem;
    border: 1px solid rgba(226, 232, 240, 0.62);
    border-radius: 0.88rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0.35));
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.82),
        0 4px 14px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.35rem;
    transition:
        transform 240ms var(--liquid-ease),
        border-color 240ms ease,
        box-shadow 240ms ease;
}

.ab-company-highlight-grid span:hover {
    transform: translateY(-2px);
    border-color: rgba(99, 102, 241, 0.25);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.82),
        0 10px 24px rgba(99, 102, 241, 0.1);
}

.ab-company-highlight-grid i {
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 0.58rem;
    font-size: 0.78rem;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    display: inline-grid;
    place-items: center;
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.22);
}

.ab-company-highlight-grid small {
    display: block;
    color: var(--hcis-slate-700);
    font-size: 0.68rem;
    line-height: 1.28;
    font-weight: 900;
}

.ab-company-copy {
    align-self: stretch;
}

/* Story paragraphs with left accent border */
.ab-company-story {
    margin-top: 0.82rem;
    padding-left: 1rem;
    border-left: 3px solid var(--primary);
    display: grid;
    gap: 0.6rem;
}

.ab-company-story p {
    max-width: 46rem;
    margin: 0;
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.6;
    font-weight: 600;
}

/* Brand Showcase */
.ab-brand-showcase {
    margin-top: clamp(0.88rem, 1.6vw, 1.1rem);
    padding: clamp(0.68rem, 1.4vw, 0.86rem);
    border: 1px solid rgba(226, 232, 240, 0.74);
    border-radius: 1rem;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.68), rgba(255, 255, 255, 0.42)),
        radial-gradient(circle at 100% 0%, rgba(99, 102, 241, 0.1), transparent 38%);
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.065);
    backdrop-filter: blur(18px);
}

.ab-brand-showcase__head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
}

.ab-brand-showcase__head span {
    color: var(--hcis-slate-900);
    font-size: 0.86rem;
    font-weight: 950;
}

.ab-brand-showcase__head p {
    max-width: 18rem;
    margin: 0;
    text-align: right;
    font-size: 0.68rem;
    line-height: 1.35;
}

.ab-pet-brand-grid {
    margin-top: 0.62rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
}

.ab-pet-brand-card {
    min-width: 0;
    min-height: 5.65rem;
    padding: 0.54rem;
    border: 1px solid rgba(226, 232, 240, 0.72);
    border-radius: 0.82rem;
    background: rgba(255, 255, 255, 0.58);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.82);
    display: grid;
    grid-template-rows: minmax(3.05rem, 1fr) auto;
    align-items: center;
    justify-items: center;
    gap: 0.26rem;
    transition:
        transform 220ms var(--liquid-ease),
        border-color 220ms var(--liquid-ease),
        box-shadow 220ms var(--liquid-ease),
        background 220ms var(--liquid-ease);
}

.ab-pet-brand-card:hover {
    transform: translateY(-0.24rem) scale(1.02);
    border-color: rgba(99, 102, 241, 0.35);
    background: rgba(255, 255, 255, 0.78);
    box-shadow:
        0 18px 34px rgba(79, 70, 229, 0.13),
        0 0 0 3px rgba(99, 102, 241, 0.06);
}

.ab-pet-brand-card img {
    width: 100%;
    height: 3.18rem;
    object-fit: contain;
    filter: drop-shadow(0 12px 18px rgba(15, 23, 42, 0.12));
}

.ab-pet-brand-card strong {
    color: var(--hcis-slate-700);
    font-size: 0.67rem;
    font-weight: 900;
    text-align: center;
}

/* ═══════════════════════════════════════════════════════════
   SUBSIDIARIES
   ═══════════════════════════════════════════════════════════ */
.ab-subsidiary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.ab-subsidiary-card {
    position: relative;
    min-height: 13.5rem;
    padding: 1rem;
    transition:
        transform 260ms var(--liquid-ease),
        box-shadow 260ms ease,
        border-color 260ms ease;
}

.ab-subsidiary-number {
    position: absolute;
    top: 0.7rem;
    right: 0.7rem;
    z-index: 2;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.12);
    color: var(--primary);
    font-size: 0.72rem;
    font-weight: 900;
    display: grid;
    place-items: center;
}

.ab-card-topline {
    position: absolute;
    top: 0;
    left: 1.1rem;
    right: 1.1rem;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, var(--primary), transparent);
}

.ab-subsidiary-card img {
    position: relative;
    z-index: 1;
    width: 5.6rem;
    height: 3.25rem;
    object-fit: contain;
    mix-blend-mode: multiply;
}

.ab-subsidiary-card h3 {
    position: relative;
    z-index: 1;
    margin: 0.5rem 0 0;
    color: var(--hcis-slate-900);
    font-size: 0.86rem;
    font-weight: 900;
}

.ab-subsidiary-card p {
    position: relative;
    z-index: 1;
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.58;
    font-weight: 600;
}

.ab-subsidiary-badge {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: fit-content;
    margin-top: 0.7rem;
    padding: 0.4rem 0.68rem;
    border-radius: 999px;
    color: var(--primary-dark);
    background: rgba(99, 102, 241, 0.1);
    font-size: 0.72rem;
    font-weight: 900;
}

.ab-subsidiary-badge i {
    font-size: 0.58rem;
}

.ab-subsidiary-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow:
        0 18px 44px rgba(99, 102, 241, 0.14),
        0 0 0 1px rgba(99, 102, 241, 0.08);
    transform: translateY(-5px);
}

/* ═══════════════════════════════════════════════════════════
   VISI & MISI
   ═══════════════════════════════════════════════════════════ */
.ab-vision-section {
    display: grid;
    gap: 1.8rem;
}

/* Visi Banner */
.ab-vision-banner {
    position: relative;
    padding: clamp(1.8rem, 3.5vw, 2.8rem);
    border-radius: 1.25rem;
    background:
        radial-gradient(circle at 18% 28%, rgba(99, 102, 241, 0.12), transparent 48%),
        radial-gradient(circle at 82% 72%, rgba(6, 182, 212, 0.08), transparent 38%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.82), rgba(238, 242, 255, 0.62));
    border: 1px solid var(--liquid-border-strong);
    backdrop-filter: blur(20px);
    box-shadow:
        0 22px 54px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
    overflow: hidden;
}

.ab-vision-quote-icon {
    position: absolute;
    top: -1.2rem;
    right: 1.8rem;
    font-size: 10rem;
    font-weight: 900;
    line-height: 1;
    color: rgba(99, 102, 241, 0.06);
    pointer-events: none;
    font-family: Georgia, 'Times New Roman', serif;
    user-select: none;
}

.ab-vision-banner h2 {
    max-width: 740px;
    margin: 0.8rem 0 0;
    font-size: clamp(1.45rem, 2.8vw, 2.25rem);
    line-height: 1.2;
    font-weight: 900;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.ab-vision-banner p {
    max-width: 620px;
    margin: 0.85rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.88rem;
    line-height: 1.62;
    font-weight: 600;
}

/* Misi Cards */
.ab-misi-wrapper {
    display: grid;
    gap: 1rem;
}

.ab-misi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.ab-misi-card {
    position: relative;
    padding: 1.1rem;
    border-radius: 1rem;
    border: 1px solid var(--liquid-border-strong);
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.42)), rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(16px);
    box-shadow:
        0 8px 28px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.78);
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    transition:
        transform 260ms var(--liquid-ease),
        box-shadow 260ms ease,
        border-color 260ms ease;
}

.ab-misi-card:hover {
    transform: translateY(-3px);
    border-color: rgba(99, 102, 241, 0.25);
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.12);
}

.ab-misi-number {
    font-size: 2rem;
    font-weight: 900;
    color: rgba(99, 102, 241, 0.13);
    line-height: 1;
    letter-spacing: -0.02em;
}

.ab-misi-card p {
    color: var(--hcis-slate-600);
    font-size: 0.84rem;
    line-height: 1.58;
    font-weight: 700;
    margin: 0;
}

.ab-misi-check {
    color: var(--hcis-emerald-500);
    font-size: 0.92rem;
    margin-top: auto;
}

/* ═══════════════════════════════════════════════════════════
   PLATFORM SECTION (centered header + grid)
   ═══════════════════════════════════════════════════════════ */
.ab-platform-section .ab-section-head {
    max-width: 640px;
}

.ab-version-pill {
    width: fit-content;
    margin: 1rem auto 0;
    padding: 0.55rem 0.78rem;
    border-radius: 999px;
    color: #166534;
    background: rgba(240, 253, 244, 0.72);
    border: 1px solid rgba(187, 247, 208, 0.8);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.76rem;
    font-weight: 900;
}

.ab-pillar-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}

.ab-pillar-card {
    padding: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    position: relative;
    overflow: hidden;
    transition:
        transform 260ms var(--liquid-ease),
        box-shadow 260ms ease,
        border-color 260ms ease;
}

/* Shimmer sweep on hover */
.ab-pillar-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.45), transparent);
    transition: left 550ms ease;
    pointer-events: none;
    z-index: 1;
}

.ab-pillar-card:hover::before {
    left: 100%;
}

.ab-pillar-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 14px 38px rgba(99, 102, 241, 0.12);
    transform: translateY(-4px);
}

.ab-pillar-icon-area {
    width: 3.2rem;
    height: 3.2rem;
    border-radius: 0.85rem;
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 1.22rem;
    box-shadow: 0 14px 28px rgba(79, 70, 229, 0.22);
    flex-shrink: 0;
}

.ab-pillar-card h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 900;
}

.ab-pillar-card p {
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.58;
    font-weight: 600;
    margin: 0;
}

/* ═══════════════════════════════════════════════════════════
   MODULES
   ═══════════════════════════════════════════════════════════ */
.ab-module-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.ab-module-card {
    min-height: 11.5rem;
    padding: 1.1rem;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    transition:
        transform 260ms var(--liquid-ease),
        box-shadow 260ms ease,
        border-color 260ms ease;
}

.ab-module-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow:
        0 16px 42px rgba(99, 102, 241, 0.14),
        0 0 0 1px rgba(99, 102, 241, 0.06);
    transform: translateY(-4px);
}

.ab-module-icon-area {
    width: 3rem;
    height: 3rem;
    border-radius: 0.85rem;
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    box-shadow: 0 12px 24px rgba(79, 70, 229, 0.22);
    flex-shrink: 0;
}

.ab-module-content {
    flex: 1;
}

.ab-module-content h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 900;
}

.ab-module-content p {
    margin: 0.3rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.58;
    font-weight: 600;
}

.ab-module-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    width: fit-content;
    padding: 0.38rem 0.68rem;
    border-radius: 999px;
    background: rgba(240, 253, 244, 0.76);
    font-size: 0.72rem;
    font-weight: 900;
    color: #166534;
    margin-top: auto;
}

.ab-module-status small {
    font-weight: 900;
}

.ab-pulse-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: #22c55e;
    animation: pulseDot 2s ease-in-out infinite;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════
   STATS STRIP
   ═══════════════════════════════════════════════════════════ */
.ab-stats-section {
    padding-top: clamp(1rem, 2vw, 1.5rem) !important;
    padding-bottom: clamp(1rem, 2vw, 1.5rem) !important;
}

.ab-stats-strip {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(1.8rem, 3.5vw, 2.8rem) clamp(1rem, 2vw, 2rem);
    border-radius: 1.25rem;
    background:
        radial-gradient(circle at 18% 45%, rgba(99, 102, 241, 0.12), transparent 48%),
        radial-gradient(circle at 82% 55%, rgba(6, 182, 212, 0.08), transparent 38%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.78), rgba(238, 242, 255, 0.62));
    border: 1px solid var(--liquid-border-strong);
    backdrop-filter: blur(20px);
    box-shadow:
        0 18px 48px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
}

.ab-stat-item {
    flex: 1;
    text-align: center;
    padding: 0.5rem 1.2rem;
    position: relative;
}

.ab-stat-item:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 12%;
    height: 76%;
    width: 1px;
    background: linear-gradient(180deg, transparent, rgba(99, 102, 241, 0.22), transparent);
}

.ab-stat-number {
    color: var(--hcis-slate-900);
    font-size: clamp(2rem, 3.5vw, 2.8rem);
    font-weight: 900;
    line-height: 1;
}

.ab-stat-number strong,
.ab-stat-number span {
    display: inline;
}

.ab-stat-line {
    width: 0;
    height: 3px;
    margin: 0.65rem auto;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--primary), var(--hcis-blue-600));
    transition: width 800ms var(--liquid-ease);
}

.ab-stat-line.is-visible {
    width: 2.2rem;
}

.ab-stat-item p {
    color: var(--hcis-slate-500);
    font-size: 0.8rem;
    font-weight: 800;
    margin: 0;
}

/* ═══════════════════════════════════════════════════════════
   TIMELINE / STEPS
   ═══════════════════════════════════════════════════════════ */
.ab-timeline {
    position: relative;
    max-width: 750px;
    margin: 0 auto;
    padding: 1rem 0 0;
}

.ab-timeline-line {
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, transparent, rgba(99, 102, 241, 0.3), rgba(99, 102, 241, 0.18), transparent);
    transform: translateX(-50%);
}

.ab-timeline-item {
    position: relative;
    width: 50%;
    padding: 0 2.5rem 2rem 0;
    display: flex;
    justify-content: flex-end;
}

.ab-timeline-item.is-right {
    margin-left: 50%;
    padding: 0 0 2rem 2.5rem;
    justify-content: flex-start;
}

.ab-timeline-dot {
    position: absolute;
    right: -1.15rem;
    top: 0.2rem;
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-weight: 900;
    font-size: 0.82rem;
    box-shadow: 0 10px 24px rgba(79, 70, 229, 0.28);
    z-index: 2;
}

.ab-timeline-item.is-right .ab-timeline-dot {
    right: auto;
    left: -1.15rem;
}

.ab-timeline-card {
    width: 100%;
    padding: 1.15rem;
    border-radius: 1rem;
    border: 1px solid var(--liquid-border-strong);
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.42)), rgba(255, 255, 255, 0.58);
    backdrop-filter: blur(18px);
    box-shadow:
        0 10px 28px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.78);
    transition:
        transform 260ms var(--liquid-ease),
        box-shadow 260ms ease,
        border-color 260ms ease;
}

.ab-timeline-card:hover {
    transform: translateY(-3px);
    border-color: rgba(99, 102, 241, 0.25);
    box-shadow: 0 16px 38px rgba(99, 102, 241, 0.12);
}

.ab-timeline-card > i {
    color: var(--primary-dark);
    font-size: 1.18rem;
    margin-bottom: 0.45rem;
    display: block;
}

.ab-timeline-card h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 900;
}

.ab-timeline-card p {
    margin: 0.3rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.58;
    font-weight: 600;
}

/* ═══════════════════════════════════════════════════════════
   SUPPORT
   ═══════════════════════════════════════════════════════════ */
.ab-support {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: start;
    gap: clamp(1.2rem, 2.6vw, 2rem);
}

.ab-support-info,
.ab-support-form {
    padding: clamp(1.1rem, 2.35vw, 1.45rem);
}

.ab-contact-list {
    margin-top: 1.35rem;
    display: grid;
    gap: 0.75rem;
}

.ab-contact-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.85rem;
    padding: 0.82rem;
    border: 1px solid rgba(226, 232, 240, 0.65);
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.45);
    transition:
        border-color 220ms ease,
        background 220ms ease,
        box-shadow 220ms ease;
}

.ab-contact-card:hover {
    border-color: rgba(99, 102, 241, 0.2);
    background: rgba(255, 255, 255, 0.62);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
}

.ab-contact-card i {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.72rem;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--primary), var(--hcis-blue-600));
    box-shadow: 0 12px 24px rgba(79, 70, 229, 0.22);
    display: inline-grid;
    place-items: center;
    flex-shrink: 0;
}

.ab-contact-card h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 0.86rem;
    font-weight: 900;
}

.ab-contact-card p {
    margin: 0.25rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.84rem;
    line-height: 1.55;
    font-weight: 600;
}

/* Support Form */
.ab-support-form {
    display: grid;
    gap: 0.85rem;
}

.ab-support-form label {
    display: grid;
    gap: 0.4rem;
}

.ab-support-form span {
    color: var(--hcis-slate-700);
    font-size: 0.78rem;
    font-weight: 900;
}

.ab-support-form input,
.ab-support-form textarea {
    width: 100%;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 0.9rem;
    padding: 0.8rem 0.9rem;
    color: var(--hcis-slate-900);
    background: rgba(255, 255, 255, 0.68);
    font: inherit;
    font-size: 0.88rem;
    outline: none;
    transition:
        border-color 220ms ease,
        box-shadow 220ms ease,
        background 220ms ease;
}

.ab-support-form textarea {
    resize: vertical;
}

.ab-support-form input:focus,
.ab-support-form textarea:focus {
    border-color: rgba(99, 102, 241, 0.46);
    background: rgba(255, 255, 255, 0.92);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.1),
        0 8px 22px rgba(99, 102, 241, 0.06);
}

/* ═══════════════════════════════════════════════════════════
   DARK FOOTER
   ═══════════════════════════════════════════════════════════ */
.ab-footer-dark {
    position: relative;
    z-index: 2;
    background: linear-gradient(180deg, var(--hcis-slate-900), var(--hcis-slate-800));
    margin-top: 1rem;
}

.ab-footer-inner {
    width: min(1040px, calc(100% - 2rem));
    margin: 0 auto;
    padding: clamp(2rem, 4vw, 3rem) clamp(1rem, 2vw, 1.5rem);
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1.85fr);
    gap: 2rem;
}

.ab-footer-dark .ab-footer-brand img {
    width: 7.8rem;
    filter: brightness(0) invert(1);
    opacity: 0.88;
}

.ab-footer-dark .ab-footer-brand p {
    max-width: 24rem;
    margin: 0.5rem 0 0;
    color: rgba(148, 163, 184, 0.85);
    line-height: 1.6;
    font-size: 0.84rem;
    font-weight: 600;
}

.ab-footer-dark .ab-footer-links {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.ab-footer-dark .ab-footer-links div {
    display: grid;
    align-content: start;
    gap: 0.55rem;
}

.ab-footer-dark .ab-footer-links strong {
    color: rgba(255, 255, 255, 0.92);
    font-weight: 900;
    font-size: 0.86rem;
}

.ab-footer-dark .ab-footer-links a,
.ab-footer-dark .ab-footer-links button {
    padding: 0;
    color: rgba(148, 163, 184, 0.78);
    text-align: left;
    font-size: 0.8rem;
    font-weight: 700;
    background: none;
    border: none;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    position: relative;
    width: fit-content;
    transition: color 200ms ease;
}

.ab-footer-dark .ab-footer-links a::after,
.ab-footer-dark .ab-footer-links button::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1.5px;
    background: var(--primary-light);
    border-radius: 999px;
    transition: width 280ms var(--liquid-ease);
}

.ab-footer-dark .ab-footer-links a:hover,
.ab-footer-dark .ab-footer-links button:hover {
    color: rgba(255, 255, 255, 0.95);
}

.ab-footer-dark .ab-footer-links a:hover::after,
.ab-footer-dark .ab-footer-links button:hover::after {
    width: 100%;
}

.ab-footer-bottom {
    grid-column: 1 / -1;
    padding-top: 1.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(148, 163, 184, 0.55);
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.ab-footer-dark .ab-socials {
    display: inline-flex;
    gap: 0.85rem;
    color: rgba(148, 163, 184, 0.65);
}

.ab-footer-dark .ab-socials i {
    cursor: pointer;
    font-size: 1.05rem;
    transition:
        color 200ms ease,
        transform 200ms var(--liquid-ease);
}

.ab-footer-dark .ab-socials i:hover {
    color: var(--primary-light);
    transform: translateY(-2px);
}

/* ═══════════════════════════════════════════════════════════
   REVEAL ANIMATIONS
   ═══════════════════════════════════════════════════════════ */
.ab-reveal {
    opacity: 0;
    transform: translateY(32px) scale(0.985);
    transition:
        opacity 780ms var(--liquid-ease),
        transform 780ms var(--liquid-ease);
    transition-delay: var(--delay, 0ms);
}

.ab-reveal.reveal-left {
    transform: translateX(-34px);
}

.ab-reveal.reveal-right {
    transform: translateX(34px);
}

.ab-reveal.is-visible {
    opacity: 1;
    transform: translate(0, 0) scale(1);
}

/* ═══════════════════════════════════════════════════════════
   KEYFRAME ANIMATIONS
   ═══════════════════════════════════════════════════════════ */
@keyframes logoFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

@keyframes scrollCue {
    0%,
    100% {
        transform: translateY(0);
        opacity: 0.95;
    }
    50% {
        transform: translateY(1.05rem);
        opacity: 0.45;
    }
}

@keyframes pulseDot {
    0%,
    100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
    }
    50% {
        opacity: 0.6;
        box-shadow: 0 0 0 5px rgba(34, 197, 94, 0);
    }
}

@keyframes shimmerSweep {
    0%,
    100% {
        left: -100%;
    }
    50% {
        left: 100%;
    }
}

@keyframes shimmerBorder {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

@keyframes orbFloat1 {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0.55;
    }
    50% {
        transform: translate(-8px, 12px);
        opacity: 0.35;
    }
}

@keyframes orbFloat2 {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0.5;
    }
    50% {
        transform: translate(6px, -10px);
        opacity: 0.3;
    }
}

@keyframes orbFloat3 {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0.45;
    }
    50% {
        transform: translate(10px, 8px);
        opacity: 0.25;
    }
}

@keyframes logoGlow {
    0%,
    100% {
        opacity: 0.6;
        transform: translate(-50%, -50%) scale(1);
    }
    50% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.15);
    }
}

@keyframes logoRingSpin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE — TABLET (≤980px)
   ═══════════════════════════════════════════════════════════ */
@media (max-width: 980px) {
    .ab-navbar {
        width: min(720px, calc(100vw - 1.4rem));
    }

    .ab-navlinks {
        display: none;
    }

    .ab-nav-cta {
        margin-left: auto;
    }

    .ab-nav-cta span {
        display: none;
    }

    .ab-company,
    .ab-support {
        grid-template-columns: 1fr;
    }

    .ab-company-visual {
        position: relative;
        top: auto;
    }

    .ab-footer-inner {
        grid-template-columns: 1fr;
    }

    .ab-subsidiary-grid,
    .ab-module-grid,
    .ab-misi-grid,
    .ab-pet-brand-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ab-pillar-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ab-stats-strip {
        flex-wrap: wrap;
    }

    .ab-stat-item {
        flex: 0 0 50%;
        padding-block: 0.8rem;
    }

    .ab-stat-item:not(:last-child)::after {
        display: none;
    }

    /* Timeline: single column */
    .ab-timeline-line {
        left: 1.15rem;
    }

    .ab-timeline-item,
    .ab-timeline-item.is-right {
        width: 100%;
        margin-left: 0;
        padding: 0 0 1.5rem 3.5rem;
        justify-content: flex-start;
    }

    .ab-timeline-dot,
    .ab-timeline-item.is-right .ab-timeline-dot {
        left: 0;
        right: auto;
    }

    .ab-footer-links {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE — MOBILE (≤640px)
   ═══════════════════════════════════════════════════════════ */
@media (max-width: 640px) {
    .ab-navbar {
        top: 0.65rem;
        width: calc(100vw - 1rem);
        min-height: 3.35rem;
        border-radius: 0.9rem;
    }

    .ab-brand small {
        display: none;
    }

    .ab-brand__mark {
        width: 2.35rem;
        height: 2.35rem;
    }

    .ab-nav-cta {
        min-width: 2.35rem;
        padding-inline: 0;
        justify-content: center;
    }

    .ab-hero {
        min-height: 100vh;
        min-height: 100svh;
        padding: 5.8rem 1rem 2.35rem;
    }

    .ab-hero-actions,
    .ab-btn {
        width: 100%;
    }

    .ab-section-shell {
        padding-inline: 1rem;
    }

    .ab-section {
        padding-block: 2.5rem;
    }

    .ab-subsidiary-grid,
    .ab-module-grid,
    .ab-pillar-grid,
    .ab-misi-grid,
    .ab-footer-dark .ab-footer-links {
        grid-template-columns: 1fr;
    }

    .ab-stats-strip {
        flex-direction: column;
        gap: 0.5rem;
    }

    .ab-stat-item {
        flex: none;
        width: 100%;
        padding-block: 0.7rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    }

    .ab-stat-item:last-child {
        border-bottom: none;
    }

    .ab-stat-item:not(:last-child)::after {
        display: none;
    }

    .ab-company-card {
        min-height: auto;
        gap: 0.78rem;
    }

    .ab-company-card__logo {
        min-height: 7.6rem;
    }

    .ab-company-highlight-grid,
    .ab-pet-brand-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ab-brand-showcase__head {
        align-items: start;
        flex-direction: column;
        gap: 0.35rem;
    }

    .ab-brand-showcase__head p {
        max-width: none;
        text-align: left;
    }

    .ab-pet-brand-card {
        min-height: 5.3rem;
        padding: 0.5rem;
    }

    .ab-pet-brand-card img {
        height: 2.9rem;
    }

    .ab-vision-banner {
        padding: 1.5rem;
    }

    .ab-vision-quote-icon {
        font-size: 6rem;
        top: -0.8rem;
        right: 0.8rem;
    }

    .ab-footer-inner {
        width: calc(100% - 1rem);
        padding: 2rem 1rem;
    }

    .ab-footer-bottom {
        flex-direction: column;
        gap: 0.6rem;
    }

    .ab-timeline-item,
    .ab-timeline-item.is-right {
        padding-left: 3rem;
    }
}

/* ═══════════════════════════════════════════════════════════
   REDUCED MOTION
   ═══════════════════════════════════════════════════════════ */
@media (prefers-reduced-motion: reduce) {
    .ab-reveal,
    .ab-hero-logo,
    .ab-scroll-cue span,
    .ab-subsidiary-card,
    .ab-module-card,
    .ab-pillar-card,
    .ab-misi-card,
    .ab-timeline-card,
    .ab-pulse-dot,
    .ab-btn--shimmer::before,
    .ab-company-card__shimmer-border,
    .ab-company-card__orb,
    .ab-company-card__logo-glow,
    .ab-company-card__logo-ring {
        animation: none;
        transition: none;
    }

    .ab-reveal {
        opacity: 1;
        transform: none;
    }

    .ab-stat-line {
        width: 2.2rem;
        transition: none;
    }
}
</style>
