<template>
    <div
        class="rd"
        :class="[
            modeClass,
            { immersive, 'zoomed-pan': zoom > 100, grabbing, 'all-read': allRead, 'finish-open': finishBannerVisible },
        ]"
        @keydown.esc="handleEsc"
        @contextmenu.prevent
        @dragstart.prevent
        @selectstart.prevent
    >
        <!-- Top Navigation Bar -->
        <header class="rd-bar">
            <button class="rd-ico" title="Kembali (Esc)" @click="$emit('close')">
                <i class="bi bi-arrow-left"></i>
            </button>

            <button
                v-if="navItems.length"
                class="rd-ico"
                :class="{ active: navOpen }"
                title="Navigasi Dokumen"
                @click="toggleNav"
            >
                <i class="bi bi-list-ul"></i>
            </button>

            <div class="rd-titles">
                <div class="rd-eyebrow">
                    <i class="bi bi-shield-check"></i>
                    {{ tanpaLacak ? 'Formulir · Contoh Pengisian' : 'Dokumen Terkendali · Wajib Baca' }}
                </div>
                <div class="rd-title" :title="judul || 'Dokumen'">{{ judul || 'Dokumen' }}</div>
            </div>

            <div class="rd-spacer"></div>

            <!-- Lencana ini dulu selalu berbunyi "tidak dapat diunduh", termasuk
                 pada dokumen yang izin unduhnya justru dinyalakan admin. -->
            <span
                v-if="!bolehUnduh"
                class="rd-sec"
                :title="
                    watermark
                        ? 'Halaman terenkripsi & ber-watermark — tidak dapat diunduh'
                        : 'Halaman terenkripsi — tidak dapat diunduh'
                "
            >
                <i class="bi bi-lock-fill"></i> <span class="rd-hidesm">Terlindungi</span>
            </span>

            <!-- Satu-satunya jalan unduh bagi DOKUMEN. Tombol di SopKontenViewer
                 hanya melayani FORM, dan kaki-penampilnya tak pernah dirender
                 ketika dokumen dibuka lewat pembaca ini. -->
            <button
                v-else
                class="rd-ico rd-unduh"
                :disabled="mengunduh"
                :title="mengunduh ? 'Menyiapkan berkas…' : 'Unduh dokumen ini'"
                @click="$emit('unduh')"
            >
                <i class="bi" :class="mengunduh ? 'bi-arrow-repeat rd-spin-ico' : 'bi-download'"></i>
                <span class="rd-hidesm">{{ mengunduh ? 'Menyiapkan…' : 'Unduh' }}</span>
            </button>

            <span class="rd-chip">{{ cur }} / {{ total || '…' }}</span>

            <button
                class="rd-ico"
                :class="{ active: infoOpen }"
                title="Informasi & Metadata Dokumen"
                @click="infoOpen = !infoOpen"
            >
                <i class="bi bi-info-circle"></i>
            </button>

            <button class="rd-ico" :class="{ active: pagesOpen }" title="Daftar Halaman (Drawer)" @click="toggleDrawer">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </button>

            <button class="rd-ico rd-hidesm" title="Layar Penuh" @click="immersive = !immersive">
                <i class="bi" :class="immersive ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"></i>
            </button>

            <button class="rd-ico rd-more" title="Pengaturan & Alat" @click.stop="toolsOpen = !toolsOpen">
                <i class="bi bi-three-dots-vertical"></i>
            </button>

            <div class="rd-tools" :class="{ open: toolsOpen }">
                <div class="rd-seg">
                    <button :class="{ on: mode === 'light' }" @click="mode = 'light'" title="Mode Terang">
                        ☀ <span>Terang</span>
                    </button>
                    <button :class="{ on: mode === 'sepia' }" @click="mode = 'sepia'" title="Mode Sepia">
                        🌤 <span>Sepia</span>
                    </button>
                    <button :class="{ on: mode === 'night' }" @click="mode = 'night'" title="Mode Malam">
                        🌙 <span>Malam</span>
                    </button>
                </div>

                <div class="rd-trow">
                    <div class="rd-zoom">
                        <button @click="setZoom(zoom - 20)" title="Perkecil"><i class="bi bi-dash"></i></button>
                        <span>{{ zoom }}%</span>
                        <button @click="setZoom(zoom + 20)" title="Perbesar"><i class="bi bi-plus"></i></button>
                    </div>
                    <button class="rd-ico" title="Putar Kiri" @click="rotate(-90)">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button class="rd-ico" title="Putar Kanan" @click="rotate(90)">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button class="rd-ico" title="Paskan Layar" @click="fit"><i class="bi bi-aspect-ratio"></i></button>
                </div>
            </div>
        </header>

        <!-- Pita resume: satu baris, menghilang sendiri setelah 6 detik. -->
        <transition name="rd-res">
            <div v-if="resumeDari" class="rd-res" role="status" aria-live="polite">
                <i class="bi bi-bookmark-check-fill"></i>
                <span
                    >Dilanjutkan dari halaman <strong class="tnum">{{ resumeDari }}</strong></span
                >
                <button class="rd-res-awal" @click="keAwal">Ke awal</button>
                <button class="rd-res-x" aria-label="Tutup" @click="tutupResume"><i class="bi bi-x"></i></button>
            </div>
        </transition>

        <!-- Main Workspace Stage -->
        <main class="rd-mid">
            <div class="rd-stage" ref="stage" @pointerdown="onDown" @wheel="onWheel">
                <div class="rd-book">
                    <div class="rd-pagewrap" ref="pagewrap">
                        <div class="rd-page" :style="pageStyle">
                            <template v-if="err">
                                <div class="rd-msg">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span>{{ err }}</span>
                                    <button class="rd-retry" @click="reload">
                                        <i class="bi bi-arrow-clockwise"></i> Coba lagi
                                    </button>
                                </div>
                            </template>

                            <template v-else-if="curPage && curPage.status === 'loaded'">
                                <RdCanvas :bmp="curPage.bmp" cls="rd-img" />
                            </template>

                            <div v-else-if="curPage && curPage.status === 'error'" class="rd-msg">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>{{ curPage.msg || 'Halaman gagal dimuat.' }}</span>
                                <button class="rd-retry" @click="retryPage(cur)">
                                    <i class="bi bi-arrow-clockwise"></i> Muat ulang halaman
                                </button>
                            </div>

                            <div v-else class="rd-skel">
                                <div class="rd-spin"></div>
                                <span>Memuat halaman {{ cur }}…</span>
                            </div>

                            <span class="rd-drm" v-if="watermark" title="Hak Cipta Dokumen Terlindungi"
                                ><i class="bi bi-shield-lock"></i
                            ></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rd-vignette"></div>

            <!-- Left Navigation Button -->
            <div class="rd-nav left">
                <button class="rd-navbtn" :disabled="cur <= 1" @click="go(cur - 1, 'prev')" title="Halaman Sebelumnya">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </div>

            <!-- Right Navigation Button / Finish Gate -->
            <div class="rd-nav right">
                <div class="rd-slot">
                    <div class="rd-dwell" :class="{ on: dwellOn }" :style="{ '--deg': dwellDeg + 'deg' }"></div>
                    <button
                        class="rd-navbtn"
                        :class="{ pulse: finishBannerVisible }"
                        :disabled="!finishBannerVisible && (cur >= total || !canForward)"
                        @pointerdown.prevent="finishBannerVisible ? holdStart() : null"
                        @pointerup="finishBannerVisible ? holdEnd(false) : null"
                        @pointerleave="finishBannerVisible ? holdEnd(false) : null"
                        @click="finishBannerVisible ? onFinishBtnClick() : go(cur + 1, 'next')"
                        :title="finishBannerVisible ? 'Tahan untuk menyelesaikan' : 'Halaman Selanjutnya'"
                    >
                        <span class="rd-nfill" :style="{ height: finishBannerVisible ? holdPct + '%' : '0%' }"></span>
                        <i v-if="finishBannerVisible" class="bi bi-check-lg"></i>
                        <i v-else class="bi bi-chevron-right"></i>
                    </button>

                    <div class="rd-dtag" :class="{ on: dwellOn }">Baca dulu {{ Math.ceil(dwellLeft) }}s</div>
                    <div v-if="finishBannerVisible" class="rd-dtag done">Tahan untuk selesai</div>
                </div>
            </div>

            <!-- Floating Completion Banner ketika seluruh halaman selesai dibaca -->
            <transition name="rd-pop">
                <div
                    v-if="finishBannerVisible"
                    class="rd-finish-banner"
                    :class="{ minimized: bannerMinimized }"
                    role="region"
                    aria-label="Selesaikan Dokumen"
                >
                    <!-- Expanded Banner -->
                    <template v-if="!bannerMinimized">
                        <button
                            class="rd-fb-close"
                            title="Sembunyikan sementara agar tidak menghalangi bacaan"
                            @click="bannerMinimized = true"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>

                        <div class="rd-fb-header">
                            <div class="rd-fb-icon">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                            <div class="rd-fb-info">
                                <div class="rd-fb-title">
                                    {{ tanpaLacak ? 'Contoh Pengisian Telah Dibaca!' : 'Seluruh Halaman Telah Dibaca!' }}
                                </div>
                                <div class="rd-fb-sub">
                                    <template v-if="holdPct > 0">
                                        <span class="text-emerald"
                                            >Tahan terus… <b>{{ Math.min(100, Math.round(holdPct)) }}%</b></span
                                        >
                                    </template>
                                    <template v-else>
                                        Tekan &amp; tahan tombol hijau di bawah selama 1.5 detik
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="rd-fb-body">
                            <!-- Form kosong di akhir contoh pengisian (10 Okt 2026):
                                 langkah berikutnya karyawan adalah mengisi formnya.
                                 Ditawarkan, tidak diwajibkan — progres FORM tetap dari
                                 tombol selesai di bawah (keputusan user 5 & 10 Okt). -->
                            <div v-if="formKosong.length" class="rd-fb-lampiran">
                                <div class="rd-fb-lampiran-title">
                                    <i class="bi bi-file-earmark-arrow-down-fill"></i>
                                    Unduh form kosong untuk diisi
                                </div>
                                <div class="rd-fb-lampiran-list">
                                    <button
                                        v-for="l in formKosong"
                                        :key="l.Id_Lampiran"
                                        type="button"
                                        class="rd-fb-lampiran-btn"
                                        :title="`Unduh form kosong: ${l.Nama || 'Form kosong'}`"
                                        @click="$emit('unduh-lampiran', l)"
                                    >
                                        <i class="bi bi-download"></i>
                                        <span class="rd-fb-lampiran-nama">{{ l.Nama || 'Form kosong' }}</span>
                                        <span v-if="l.Ext" class="rd-fb-lampiran-ext">{{ l.Ext }}</span>
                                    </button>
                                    <button
                                        v-if="formKosong.length > 1"
                                        type="button"
                                        class="rd-fb-lampiran-btn semua"
                                        @click="$emit('unduh-semua-lampiran')"
                                    >
                                        <i class="bi bi-file-earmark-zip"></i>
                                        <span>Unduh Semua (ZIP)</span>
                                    </button>
                                </div>
                            </div>

                            <button
                                class="rd-fb-btn"
                                :class="{ holding: holdPct > 0, hint: showHoldHint }"
                                @pointerdown.prevent="holdStart"
                                @pointerup="holdEnd(false)"
                                @pointerleave="holdEnd(false)"
                                @click="onFinishBtnClick"
                            >
                                <span class="rd-fb-fill" :style="{ width: holdPct + '%' }"></span>
                                <span class="rd-fb-btn-label">
                                    <i
                                        class="bi"
                                        :class="holdPct >= 100 ? 'bi-check-circle-fill' : 'bi-hand-index-thumb-fill'"
                                    ></i>
                                    <span>{{
                                        holdPct > 0 ? `Menahan... ${Math.round(holdPct)}%` : 'TAHAN UNTUK SELESAI'
                                    }}</span>
                                </span>
                            </button>

                            <transition name="rd-fade">
                                <div v-if="showHoldHint" class="rd-fb-tooltip">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span
                                        ><b>Jangan cuma diklik!</b> Tekan dan tahan tombol di atas selama 1.5 detik
                                        sampai 100%.</span
                                    >
                                </div>
                            </transition>
                        </div>
                    </template>

                    <!-- Minimized Floating Pill Badge -->
                    <template v-else>
                        <button
                            class="rd-fb-pill-btn"
                            @click="bannerMinimized = false"
                            title="Buka panel konfirmasi selesai dokumen"
                        >
                            <i class="bi bi-patch-check-fill text-emerald"></i>
                            <span>Selesai Dibaca · <b>Konfirmasi Dokumen</b></span>
                            <i class="bi bi-chevron-up ms-1"></i>
                        </button>
                    </template>
                </div>
            </transition>

            <!-- Backdrop & Offcanvas: Navigasi Dokumen (kiri) -->
            <transition name="rd-backdrop">
                <div v-if="navOpen" class="rd-back" @click="closeNav"></div>
            </transition>

            <transition name="rd-nav-slide">
                <aside v-if="navOpen" class="rd-navdrawer" ref="navdrawer">
                    <!-- Header compact: single row -->
                    <div class="rd-nhead">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-signpost-2-fill text-primary fs-5"></i>
                            <b class="fs-6">Navigasi Dokumen</b>
                        </div>
                        <div class="rd-nhead-right d-flex align-items-center gap-2">
                            <span class="rd-nstat-pill">
                                <i class="bi bi-pie-chart-fill text-primary me-1"></i>
                                <b>{{ navDoneCount }}</b
                                >/{{ navTotalWajib }} wajib ({{ Math.round(navPct) }}%)
                            </span>
                            <button class="rd-ico sm rounded-circle" title="Tutup" @click="closeNav">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Timeline spine -->
                    <div class="rd-dscroll" ref="navscroll">
                        <div class="rd-ntrack">
                            <template v-for="(sec, si) in navSectionsGrouped" :key="sec.key">
                                <!-- Section milestone -->
                                <div class="rd-nmile" :class="{ done: navSecDone(sec) }">
                                    <div class="rd-nmile-bulb">
                                        <i v-if="navSecDone(sec)" class="bi bi-check-lg"></i>
                                        <span v-else>{{ si + 1 }}</span>
                                    </div>
                                    <div class="rd-nmile-body">
                                        <div class="rd-nmile-name">{{ sec.Nama_Section }}</div>
                                        <div class="rd-nmile-bar">
                                            <span class="rd-nmile-fill" :style="{ width: navSecPct(sec) + '%' }"></span>
                                        </div>
                                        <div class="rd-nmile-sub">
                                            {{ navSecDoneCount(sec) }}/{{ sec.items.filter((i) => i.Wajib).length }}
                                            wajib selesai
                                        </div>
                                    </div>
                                </div>

                                <!-- Items under section -->
                                <div
                                    v-for="it in sec.items"
                                    :key="it.Id_SOP_Paket_Item"
                                    class="rd-nleaf"
                                    :class="navItemCls(it)"
                                    :data-nav-item="it.Id_SOP_Paket_Item"
                                    @click="handleNavItemClick(it)"
                                >
                                    <!-- node -->
                                    <div class="rd-nleaf-dot">
                                        <i v-if="isNavDone(it)" class="bi bi-check-lg"></i>
                                        <i v-else-if="isNavLocked(it)" class="bi bi-lock-fill"></i>
                                        <i v-else class="bi" :class="'bi-' + navIcon(it)"></i>
                                    </div>
                                    <!-- card -->
                                    <div class="rd-nleaf-card">
                                        <div class="rd-nleaf-top">
                                            <span class="rd-nleaf-tipe">{{ navTipeLabel(it.Tipe_Item) }}</span>
                                            <span v-if="isNavCurrent(it)" class="rd-nleaf-now">
                                                <span class="rd-nleaf-pulse"></span>SEDANG DIBACA
                                            </span>
                                            <span v-else-if="isNavDone(it)" class="rd-nleaf-ok">
                                                <i class="bi bi-check-circle-fill me-1"></i>SELESAI
                                            </span>
                                            <span v-else-if="isNavLocked(it)" class="rd-nleaf-lock">
                                                <i class="bi bi-lock-fill me-1"></i>TERKUNCI
                                            </span>
                                        </div>
                                        <div class="rd-nleaf-title">{{ it.Judul_Item }}</div>
                                        <div v-if="isNavCurrent(it)" class="rd-nleaf-curbar">
                                            <span class="rd-nleaf-curfill"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-if="!navSectionsGrouped.length" class="rd-nempty">
                                <i class="bi bi-inbox"></i>
                                <span>Data navigasi tak tersedia</span>
                            </div>

                            <!-- end marker -->
                            <div v-if="navSectionsGrouped.length" class="rd-nend">
                                <div class="rd-nend-dot"><i class="bi bi-flag-checkered"></i></div>
                                <span>Tuntas</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </transition>

            <!-- Backdrop & Offcanvas: Halaman Dokumen (kanan) -->
            <transition name="rd-backdrop">
                <div v-if="pagesOpen" class="rd-back" @click="closeDrawer"></div>
            </transition>

            <transition name="rd-drawer-slide">
                <aside v-if="pagesOpen" class="rd-drawer">
                    <div class="rd-dhead">
                        <b><i class="bi bi-grid-3x3-gap-fill text-indigo"></i> Halaman Dokumen</b>
                        <button class="rd-ico sm" title="Tutup Drawer" @click="closeDrawer">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="rd-phead">
                        <span
                            ><b>{{ maxPage }}</b> / {{ total }} Halaman Dibaca</span
                        >
                        <div class="rd-pacts">
                            <button class="rd-mini" @click="jump(1)"><i class="bi bi-arrow-up"></i> Pertama</button>
                            <button class="rd-mini" @click="jump(maxPage)">
                                <i class="bi bi-arrow-down"></i> Max Dibaca
                            </button>
                        </div>
                    </div>

                    <div class="rd-dscroll">
                        <div class="rd-grid">
                            <div
                                v-for="p in total"
                                :key="p"
                                class="rd-cell"
                                :class="{ read: p <= maxPage, cur: p === cur, locked: p > maxPage }"
                                @click="thumbClick(p, $event)"
                            >
                                <RdCanvas
                                    v-if="p <= maxPage && pageState[p] && pageState[p].bmp"
                                    :bmp="pageState[p].bmp"
                                    thumb
                                    cls="rd-cimg"
                                />
                                <div v-else-if="p <= maxPage" class="rd-cskel"><div class="rd-spin sm"></div></div>
                                <div v-else class="rd-clock"><i class="bi bi-lock-fill"></i></div>
                                <div class="rd-pn">Hal {{ p }}</div>
                                <div v-if="p <= maxPage" class="rd-rd"><i class="bi bi-check-lg"></i></div>
                            </div>
                        </div>
                    </div>
                </aside>
            </transition>

            <!-- Backdrop & Offcanvas: Metadata Dokumen (Kanan) -->
            <transition name="rd-backdrop">
                <div v-if="infoOpen" class="rd-back" @click="infoOpen = false"></div>
            </transition>

            <transition name="rd-drawer-slide">
                <aside v-if="infoOpen" class="rd-drawer rd-info-drawer">
                    <div class="rd-dhead">
                        <b><i class="bi bi-info-circle-fill text-indigo"></i> Metadata Dokumen</b>
                        <button class="rd-ico sm" title="Tutup" @click="infoOpen = false">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="rd-dscroll">
                        <div class="rd-meta-group">
                            <!-- Document Identity Card -->
                            <div class="rd-mcard main">
                                <div class="rd-mcard-icon"><i class="bi bi-shield-check"></i></div>
                                <div class="rd-mcard-body">
                                    <div class="rd-mcard-badge">Dokumen Terkendali</div>
                                    <div class="rd-mcard-title">{{ judul || 'Dokumen' }}</div>
                                </div>
                            </div>

                            <!--
                            Identitas, masa berlaku, pengesahan, dan riwayat versi
                            dokumen. Status keberlakuan datang jadi dari server.
                            -->
                            <div v-if="dokumen" class="rd-mdoc">
                                <SopDokumenMeta
                                    :dokumen="dokumen"
                                    :riwayat="riwayat"
                                    :memuat-riwayat="memuatRiwayat"
                                    :galat-riwayat="galatRiwayat"
                                    :versi-dibaca="versiDibaca"
                                    @muat-riwayat="muatRiwayat"
                                />
                            </div>

                            <!-- Detail Grid Specs -->
                            <div class="rd-mgrid">
                                <div class="rd-mcell">
                                    <div class="rd-mcell-lbl">
                                        <i class="bi bi-file-earmark-text"></i> Total Halaman
                                    </div>
                                    <div class="rd-mcell-val">{{ total }} Halaman</div>
                                </div>

                                <div class="rd-mcell">
                                    <div class="rd-mcell-lbl">
                                        <i class="bi bi-hourglass-split"></i> Gerbang Waktu Baca
                                    </div>
                                    <div class="rd-mcell-val">{{ dwellMin }} detik / halaman</div>
                                </div>
                            </div>

                            <!-- Live Reading Stats -->
                            <div class="rd-mcard stats">
                                <div class="rd-mcard-head">
                                    <i class="bi bi-bar-chart-line-fill me-1 text-primary"></i> Statistik Pembacaan Anda
                                </div>
                                <div class="rd-mstats-row">
                                    <div class="rd-mstat-item">
                                        <span class="num">{{ maxPage }}/{{ total }}</span>
                                        <span class="lbl">Maks. Dibaca</span>
                                    </div>
                                    <div class="rd-mstat-item">
                                        <span class="num">{{ readPct }}%</span>
                                        <span class="lbl">Progres</span>
                                    </div>
                                    <div class="rd-mstat-item">
                                        <span class="num"
                                            >{{ Math.floor(_aktifDetikTotal / 60) }}m {{ _aktifDetikTotal % 60 }}s</span
                                        >
                                        <span class="lbl">Waktu Aktif</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Security & DRM Card -->
                            <div class="rd-mcard security">
                                <div class="rd-mcard-head">
                                    <i class="bi bi-lock-fill me-1 text-emerald"></i> Keamanan &amp; Perlindungan DRM
                                </div>
                                <div class="rd-msec-item">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <span
                                        >Dokumen terlindungi dari pengunduhan langsung, pencetakan &amp; salin teks tak
                                        berizin.</span
                                    >
                                </div>
                                <div v-if="watermark" class="rd-msec-wm">
                                    <div class="wm-lbl">Watermark Keamanan Terdaftar:</div>
                                    <code class="wm-val">{{ watermark }}</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </transition>

            <!-- Success Overlay Card & Redirection Notice -->
            <div class="rd-success" :class="{ show: showSuccess }" aria-live="polite">
                <div class="rd-scard">
                    <!-- Animated Check Circle with Pulse Rings -->
                    <div class="rd-sring-wrap">
                        <div class="rd-sring-pulse"></div>
                        <div class="rd-sring"><i class="bi bi-check-lg"></i></div>
                    </div>

                    <h2>Selesai Dibaca!</h2>
                    <p class="rd-stext">
                        Anda telah membaca &amp; memahami seluruh <b>{{ total }}</b> halaman dokumen ini.
                    </p>

                    <!-- Redirection Loader Badge -->
                    <div class="rd-sredir">
                        <div class="rd-spin-dots"><span></span><span></span><span></span></div>
                        <div class="rd-sredir-msg">
                            <i class="bi bi-arrow-repeat spin-icon"></i>
                            <span>Mohon tunggu sebentar, sedang dialihkan ke langkah berikutnya…</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Bottom Navigation & Progress Footer Bar -->
        <footer class="rd-foot">
            <div class="rd-prog">
                <div class="rd-track" ref="track" @pointerdown="scrubDown">
                    <div class="rd-fill" :class="{ done: allRead }" :style="{ width: readPct + '%' }"></div>
                    <span
                        v-for="p in total"
                        :key="p"
                        class="rd-node"
                        :class="nodeCls(p)"
                        :style="{ left: nodeLeft(p) + '%' }"
                        :title="'Halaman ' + p"
                    ></span>
                </div>
                <div class="rd-meta">
                    <span
                        >Dibaca: <b>{{ maxPage }}</b
                        >/{{ total }} · <span>{{ etaTxt }}</span></span
                    >
                    <span
                        ><b>{{ progWord }}</b></span
                    >
                </div>
            </div>

            <div class="rd-ctrls">
                <div class="rd-seg">
                    <button :class="{ on: mode === 'light' }" @click="mode = 'light'">☀</button>
                    <button :class="{ on: mode === 'sepia' }" @click="mode = 'sepia'">🌤</button>
                    <button :class="{ on: mode === 'night' }" @click="mode = 'night'">🌙</button>
                </div>
                <div class="rd-zoom">
                    <button @click="setZoom(zoom - 20)">−</button>
                    <span>{{ zoom }}%</span>
                    <button @click="setZoom(zoom + 20)">+</button>
                </div>
                <button class="rd-ico" title="Putar Kiri" @click="rotate(-90)">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button class="rd-ico" title="Putar Kanan" @click="rotate(90)">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button class="rd-ico" title="Paskan Layar" @click="fit"><i class="bi bi-aspect-ratio"></i></button>
            </div>
        </footer>
    </div>
</template>

<script>
import axios from 'axios';
import { h, markRaw } from 'vue';
import SopDokumenMeta from './SopDokumenMeta.vue';

const HDR = { headers: { 'X-App-Source': 'vue-client' } };

/**
 * RdCanvas — pelukis halaman ke <canvas>.
 * Bitmap hasil dekode tak pernah jadi <img> maupun objectURL, sehingga
 * "Simpan gambar sebagai…", drag-to-save, dan Buka gambar di tab baru tak tersedia.
 */
const RdCanvas = {
    name: 'RdCanvas',
    props: {
        bmp: { default: null }, // ImageBitmap | HTMLImageElement (fallback)
        thumb: { type: Boolean, default: false },
        cls: { type: String, default: 'rd-img' },
    },
    render() {
        return h('canvas', { class: this.cls, ref: 'cv' });
    },
    mounted() {
        this.paint();
    },
    watch: {
        bmp() {
            this.$nextTick(this.paint);
        },
    },
    methods: {
        paint() {
            const cv = this.$refs.cv,
                b = this.bmp;
            if (!cv || !b) return;
            const bw = b.width || b.naturalWidth,
                bh = b.height || b.naturalHeight;
            if (!bw || !bh) return;
            const s = this.thumb ? Math.min(1, 320 / bw) : 1;
            const w = Math.max(1, Math.round(bw * s)),
                y = Math.max(1, Math.round(bh * s));
            if (cv.width !== w || cv.height !== y) {
                cv.width = w;
                cv.height = y;
            }
            const ctx = cv.getContext('2d');
            if (!ctx) return;
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.clearRect(0, 0, w, y);
            ctx.drawImage(b, 0, 0, w, y);
        },
    },
};

/**
 * SopPdfReader — Pembaca Dokumen Secure & Responsive
 * Features:
 * - Handshake API, halaman dilukis ke canvas (tanpa objectURL) untuk DRM
 * - Dwell timer compliance gate per page
 * - Smooth offcanvas drawer for page thumbnails
 * - Hold-to-finish mechanism
 * - Fluid gesture panning & zooming
 */
export default {
    name: 'SopPdfReader',
    components: { RdCanvas, SopDokumenMeta },
    props: {
        berkasId: { type: String, required: true },
        idItem: { type: String, default: '' },
        judul: { type: String, default: '' },
        halamanTerakhir: { type: Number, default: 0 },
        readonly: { type: Boolean, default: false },
        /*
         * Tanpa pelacakan halaman: jeda baca per halaman dan simpan-halaman ke
         * server dimatikan, tetapi penyelesaian di akhir TETAP ada. Untuk contoh
         * pengisian FORM "hanya lihat" (10 Okt 2026) — dibaca lewat pembaca aman
         * demi tanda air, padahal FORM tak dilacak per halaman. Beda dengan
         * readonly, yang juga menutup penyelesaian (versi lama / sudah selesai).
         */
        tanpaLacak: { type: Boolean, default: false },
        marking: { type: Boolean, default: false },
        navSections: { type: Array, default: () => [] },
        navItems: { type: Array, default: () => [] },
        /*
         * Item ini boleh diunduh (Mode_Akses = VIEW_UNDUH).
         *
         * Tanpa prop ini, dokumen ber-izin-unduh tak punya tombol unduh DI MANA
         * PUN: satu-satunya tombol unduh di SopKontenViewer digerbangi
         * `tipe === 'FORM'`, dan kaki-penampil yang memuatnya ada di cabang
         * v-else — cabang yang tak pernah dirender ketika dokumen dibuka lewat
         * pembaca aman ini. Admin menyalakan izinnya, lencana halaman menulis
         * "Izin Unduh", dan tetap tak ada yang bisa diklik.
         *
         * Baku FALSE. Pembaca ini adalah pintu terakhir menuju isi dokumen, jadi
         * pemanggil yang lupa mengirim prop tak boleh diam-diam membuka unduhan.
         */
        bolehUnduh: { type: Boolean, default: false },
        /** Berkas sedang disiapkan server — tombolnya dikunci selama itu. */
        mengunduh: { type: Boolean, default: false },
        // Gerbang baca per halaman, DARI SERVER (N_SOP_Setting.reader_dwell_min_dtk).
        // Sebelumnya 3 detik ditulis keras di sini — kebijakan kepatuhan yang hidup
        // di browser dan tak bisa diubah tanpa deploy.
        dwellMin: { type: Number, default: 3 },
        // Metadata dokumen. Sebelumnya empat prop string (kodeSop, versiSop,
        // unitKerja, tanggalEfektif) yang semuanya menunjuk field tak ada di
        // skema, jadi panelnya selalu kosong. Sekarang objek item apa adanya.
        idPaket: { type: String, default: '' },
        dokumen: { type: Object, default: null },
        /** Terisi bila yang dibuka versi lama, bukan versi aktif. */
        versiDibaca: { type: Object, default: null },
        /*
         * Pratinjau di layar ADMIN memakai handshake yang berbeda.
         *
         * Rute karyawan menuntut versi PUBLISHED pada paket yang ditugaskan;
         * admin justru membuka draft dan versi cabutan, jadi lewat rute itu
         * pratinjaunya selalu 403. Bacanya sendiri identik — yang berbeda hanya
         * pintu otorisasinya.
         */
        admin: { type: Boolean, default: false },
    },
    emits: ['close', 'complete', 'select-item', 'unduh', 'unduh-lampiran', 'unduh-semua-lampiran'],
    data() {
        return {
            // Riwayat versi dimuat sekali per dokumen, dan hanya kalau pembaca
            // membentangkannya — blok versi-sekarang tak bergantung padanya.
            riwayat: [],
            memuatRiwayat: false,
            galatRiwayat: '',
            viewerSig: '',
            pageBase: '',
            total: 0,
            watermark: null,
            manifestPages: [],
            pageState: {},
            cur: 1,
            maxPage: 1,
            resumeDari: 0,
            resumeTimer: null,
            dwellDone: new Set(),
            zoom: 100,
            panX: 0,
            panY: 0,
            rot: 0,
            mode: 'light',
            fitW: 0,
            fitH: 0,
            rotFit: 1,
            ro: null,
            immersive: false,
            toolsOpen: false,
            pagesOpen: false,
            navOpen: false,
            infoOpen: false,
            _aktifDetikTotal: 0,
            err: '',
            showSuccess: false,
            dwellOn: false,
            dwellLeft: 0,
            dwellTotal: 0,
            dwellDeg: 0,
            dwellTimer: null,
            holdPct: 0,
            holdTimer: null,
            showHoldHint: false,
            _hintTimer: null,
            bannerMinimized: true,
            drag: null,
            grabbing: false,
            scrub: false,
            busy: false,
            _pinchDist: 0,
            _pinchStartZoom: 0,
            _clickTimer: null,
            trackReq: 0,
        };
    },
    computed: {
        /** Pintu handshake — admin dan karyawan punya otorisasi sendiri. */
        urlToken() {
            const dasar = this.admin ? '/api/v1/sop/admin/secure-pdf/token' : '/api/v1/sop/secure-pdf/token';
            return `${dasar}/${this.berkasId}`;
        },
        modeClass() {
            return 'mode-' + this.mode;
        },
        curPage() {
            return this.pageState[this.cur];
        },
        canForward() {
            return this.dwellDone.has(this.cur);
        },
        allRead() {
            return this.total > 0 && this.maxPage >= this.total;
        },
        /** Form kosong item FORM, ditawarkan di banner akhir contoh pengisian. */
        formKosong() {
            return this.tanpaLacak ? (this.dokumen && this.dokumen.Lampiran) || [] : [];
        },
        canFinish() {
            return !this.readonly && this.allRead;
        },
        /** Sama seperti syarat tampil rd-finish-banner — dipakai buat kelas
         *  root yang menyediakan ruang scroll utknya. Beda dgn allRead: dokumen
         *  readonly/sudah-selesai tak pernah menampilkan banner itu, jadi tak
         *  boleh ikut mencadangkan ruangnya juga (lihat fitPage/.rd-stage). */
        finishBannerVisible() {
            return this.allRead && !this.readonly && !this.showSuccess;
        },
        readPct() {
            return this.total ? Math.round((this.maxPage / this.total) * 100) : 0;
        },
        etaTxt() {
            const l = this.total - this.maxPage;
            return this.allRead ? 'Tuntas' : '~' + Math.max(1, Math.ceil(l * 0.5)) + ' mnt lagi';
        },
        progWord() {
            const l = this.total - this.maxPage;
            return this.allRead ? 'Selesai dibaca ✓' : this.maxPage <= 1 ? 'Baru mulai' : l + ' halaman lagi';
        },
        /* navigasi dokumen */
        navSectionsGrouped() {
            if (!this.navSections.length || !this.navItems.length) return [];
            return this.navSections
                .map((sec) => ({
                    ...sec,
                    items: this.navItems.filter((it) => (it.Id_SOP_Section || '__none__') === sec.key),
                }))
                .filter((sec) => sec.items.length);
        },
        navDoneCount() {
            return this.navItems.filter((it) => ['SELESAI', 'DIKONFIRMASI'].includes(it.Status)).length;
        },
        navTotalWajib() {
            return this.navItems.filter((it) => it.Wajib).length;
        },
        navPct() {
            return this.navTotalWajib ? Math.round((this.navDoneCount / this.navTotalWajib) * 100) : 0;
        },
        navRemainingCount() {
            return Math.max(0, this.navTotalWajib - this.navDoneCount);
        },
        pageStyle() {
            const z = this.zoom / 100,
                s = { '--rot': this.rot + 'deg', '--rotfit': this.rotFit };
            if (this.fitW) {
                s.width = Math.round(this.fitW * z) + 'px';
                s.height = Math.round(this.fitH * z) + 'px';
            }
            return s;
        },
    },
    watch: {
        immersive() {
            this.$nextTick(() => {
                if (this._fitRaf) cancelAnimationFrame(this._fitRaf);
                this._fitRaf = requestAnimationFrame(() => this.fitPage());
            });
        },
        // Pindah dokumen → riwayat dokumen lama tak boleh ikut terbawa.
        idItem() {
            this.riwayat = [];
            this.galatRiwayat = '';
            this.memuatRiwayat = false;
        },
    },
    async mounted() {
        this._pointers = {}; // non-reactive pointer tracker for pinch
        window.addEventListener('pointermove', this.onMove);
        window.addEventListener('pointerup', this.onUp);
        window.addEventListener('keydown', this.onKey);
        this._fitRaf = null;
        this._onResize = () => {
            if (this._fitRaf) cancelAnimationFrame(this._fitRaf);
            this._fitRaf = requestAnimationFrame(() => this.fitPage());
        };
        window.addEventListener('resize', this._onResize);
        if (window.ResizeObserver && this.$refs.stage) {
            this.ro = new ResizeObserver(() => {
                if (this._fitRaf) cancelAnimationFrame(this._fitRaf);
                this._fitRaf = requestAnimationFrame(() => this.fitPage());
            });
            this.ro.observe(this.$refs.stage);
        }
        document.body.style.overflow = 'hidden';
        await this.handshake();
        this.$nextTick(this.fitPage);

        // Waktu baca aktif. Detik hanya dihitung saat tab terlihat, di-flush ke
        // server bersama trackPage() dan tiap 60 dtk. Inilah penulis pertama
        // kolom Durasi_Aktif_Detik yang selama ini selalu 0.
        this._aktifDetik = 0;
        this._aktifTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                this._aktifDetik += 1;
                this._aktifDetikTotal += 1;
            }
        }, 1000);
        this._flushTimer = setInterval(() => this.trackPage(), 60000);

        /*
         * beforeUnmount TIDAK dipanggil saat tab ditutup atau halaman di-refresh —
         * Vue tak pernah tahu itu terjadi. Tanpa dua pendengar ini, sampai 59 detik
         * waktu baca hangus setiap kali orang menutup tab (Halaman_Terakhir sendiri
         * aman: ia disetor tiap pindah halaman).
         *
         * `visibilitychange -> hidden` adalah satu-satunya peristiwa yang andal di
         * ponsel; `pagehide` menangkap navigasi keluar & tutup tab di desktop.
         */
        this._onSembunyi = () => {
            if (document.visibilityState === 'hidden') this.trackPage(true);
        };
        this._onPagehide = () => this.trackPage(true);
        document.addEventListener('visibilitychange', this._onSembunyi);
        window.addEventListener('pagehide', this._onPagehide);
    },
    beforeUnmount() {
        if (this._fitRaf) cancelAnimationFrame(this._fitRaf);
        clearTimeout(this.resumeTimer);
        window.removeEventListener('pointermove', this.onMove);
        window.removeEventListener('pointerup', this.onUp);
        window.removeEventListener('keydown', this.onKey);
        window.removeEventListener('resize', this._onResize);
        document.removeEventListener('visibilitychange', this._onSembunyi);
        window.removeEventListener('pagehide', this._onPagehide);
        if (this.ro) this.ro.disconnect();
        document.body.style.overflow = '';
        if (this.dwellTimer) clearInterval(this.dwellTimer);
        if (this.holdTimer) clearInterval(this.holdTimer);
        if (this._hintTimer) clearTimeout(this._hintTimer);
        if (this._sigTimer) clearInterval(this._sigTimer);
        if (this._aktifTimer) clearInterval(this._aktifTimer);
        if (this._flushTimer) clearInterval(this._flushTimer);
        this.trackPage(); // setor sisa waktu baca sebelum reader ditutup
        this.freePages();
    },
    methods: {
        /**
         * Riwayat versi diminta hanya saat pembaca membentangkannya. Gagal muat
         * tidak menutup panel: blok versi-sekarang datanya sudah di tangan.
         */
        async muatRiwayat() {
            if (this.memuatRiwayat || !this.idPaket || !this.idItem) return;
            this.memuatRiwayat = true;
            this.galatRiwayat = '';
            try {
                const r = await axios.get(`/api/v1/sop/${this.idPaket}/item/${this.idItem}/versi`);
                this.riwayat = r.data.success ? r.data.result || [] : [];
                if (!r.data.success) this.galatRiwayat = 'Gagal memuat riwayat versi.';
            } catch (e) {
                this.galatRiwayat = 'Gagal memuat riwayat versi.';
            } finally {
                this.memuatRiwayat = false;
            }
        },
        toggleDrawer() {
            this.pagesOpen = !this.pagesOpen;
        },
        openDrawer() {
            this.pagesOpen = true;
        },
        closeDrawer() {
            this.pagesOpen = false;
        },
        toggleNav() {
            this.navOpen = !this.navOpen;
            if (this.navOpen) this.$nextTick(() => this.scrollNavToCurrent());
        },
        closeNav() {
            this.navOpen = false;
        },
        handleNavItemClick(it) {
            if (!it) return;
            this.closeNav();
            this.$emit('select-item', it);
        },
        scrollNavToCurrent() {
            const el = this.$refs.navscroll;
            if (!el || !this.idItem) return;
            const leaf = el.querySelector(`[data-nav-item="${this.idItem}"]`);
            if (leaf) {
                leaf.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        },
        isNavDone(it) {
            return ['SELESAI', 'DIKONFIRMASI'].includes(it.Status);
        },
        isNavCurrent(it) {
            return it.Id_SOP_Paket_Item === this.idItem;
        },
        isNavLocked(it) {
            return !!it.Terkunci;
        },
        navIcon(it) {
            if (this.isNavDone(it)) return 'check-lg';
            if (this.isNavLocked(it)) return 'lock-fill';
            const map = {
                DOKUMEN: 'file-earmark-pdf',
                FORM: 'file-earmark-text',
                VIDEO: 'play-btn',
                KUIS: 'patch-question',
                PARAGRAF: 'paragraph',
                TAUTAN: 'link-45deg',
            };
            return map[it.Tipe_Item] || 'circle';
        },
        navTipeLabel(t) {
            const map = {
                DOKUMEN: 'Dokumen',
                FORM: 'Formulir',
                VIDEO: 'Video',
                KUIS: 'Kuis',
                PARAGRAF: 'Paragraf',
                TAUTAN: 'Tautan',
            };
            return map[t] || t;
        },
        navItemCls(it) {
            return { done: this.isNavDone(it), current: this.isNavCurrent(it), locked: this.isNavLocked(it) };
        },
        navSecDone(sec) {
            return sec.items.filter((i) => i.Wajib).every((i) => this.isNavDone(i));
        },
        navSecDoneCount(sec) {
            return sec.items.filter((i) => i.Wajib && this.isNavDone(i)).length;
        },
        navSecTotalWajib(sec) {
            return sec.items.filter((i) => i.Wajib).length;
        },
        navSecPct(sec) {
            const t = this.navSecTotalWajib(sec);
            return t ? Math.round((this.navSecDoneCount(sec) / t) * 100) : 0;
        },
        handleEsc() {
            if (this.infoOpen) this.infoOpen = false;
            else if (this.navOpen) this.closeNav();
            else if (this.pagesOpen) this.closeDrawer();
            else this.$emit('close');
        },

        /* ── Secure Loading API ── */
        async handshake() {
            this.err = '';
            try {
                /* Dua sisi digabung, dan keduanya perlu:
                     - urlToken: rute ADMIN vs rute karyawan. Pratinjau admin
                       membuka draft & versi cabutan yang memang ditolak lensa
                       karyawan, jadi memakai rute karyawan berarti selalu 403.
                     - id_item: konteks item, dipakai server memutuskan apakah
                       watermark di-burn ke halamannya. Tanpa ini watermark
                       selalu menyala, termasuk pada item yang mematikannya. */
                const t = await axios.post(this.urlToken, { id_item: this.idItem }, HDR);
                if (!t.data.success) throw new Error(t.data.message);
                const m = await axios.get(t.data.result.page_manifest_url, HDR);
                if (!m.data.success) throw new Error(m.data.message);
                const r = m.data.result;
                this.viewerSig = r.viewer_signature;
                this.pageBase = r.page_url_base;
                this.total = r.total_pages;
                // r.watermark datang '[]' (array kosong) dari JSON saat watermark
                // dimatikan untuk item ini — dan '[]' truthy di JS, jadi tanpa
                // normalisasi ini badge DRM & panel info tetap tampil seolah aktif.
                this.watermark = r.watermark && Object.keys(r.watermark).length ? r.watermark : null;
                this.manifestPages = r.pages || [];
                /*
                 * RESUME. Sebelumnya angka resume hanya dipakai membuka gerbang
                 * dwell (maxPage + dwellDone) lalu `cur` dipaksa 1 — jadi orang
                 * yang berhenti di halaman 20 selalu dikembalikan ke halaman 1
                 * dan harus menggulir dua puluh halaman lagi setiap kali membuka.
                 * Penanda halamannya SUDAH tersimpan benar sejak awal
                 * (SopCompletionService::simpanHalaman, semantik maju-saja);
                 * yang hilang cuma langkah berpindahnya.
                 */
                const resume = Math.min(Math.max(1, this.halamanTerakhir || 1), this.total);
                this.maxPage = resume;
                for (let i = 1; i < resume; i++) this.dwellDone.add(i);
                /*
                 * Halaman frontier (resume) sendiri tak ikut ditandai oleh loop di
                 * atas — padahal drawer & footer progress sudah menganggap p <=
                 * maxPage sebagai "sudah dibaca". Tanpa ini, membuka ulang dokumen
                 * selalu me-relock halaman terakhir yang dicapai dengan dwell baru,
                 * meski sebenarnya bukan "pertama kali dibaca". `halamanTerakhir`
                 * hanya bisa > 1 kalau dwell di halaman sebelumnya benar-benar
                 * sudah pernah tuntas (go() mensyaratkannya) — jadi ini aman dan
                 * tak menyentuh gerbang dwell untuk dokumen yang baru dibuka.
                 */
                if (this.halamanTerakhir > 1) this.dwellDone.add(resume);
                this.cur = resume;
                await this.ensurePage(resume);
                this.prefetch();
                this.startDwell(resume);
                this.startSigKeepalive();
                if (resume > 1) this.beriTahuResume(resume);
            } catch (e) {
                this.err = e?.response?.data?.message || e.message || 'Gagal memuat dokumen aman.';
            }
        },
        async ensurePage(n, retry = true) {
            if (n < 1 || n > this.total) return;
            const s = this.pageState[n];
            if (s && s.status === 'loaded') return;
            // Guard hanya berlaku untuk panggilan normal. Panggilan retry (retry=false)
            // WAJIB menembus guard — kalau tidak, halaman ngendon di 'loading' selamanya.
            if (s && s.status === 'loading' && retry) return;
            this.pageState = { ...this.pageState, [n]: { status: 'loading', bmp: null } };
            try {
                const g = await axios.post(
                    `${this.pageBase}/${n}/grant`,
                    { viewer_signature: this.viewerSig },
                    {
                        ...HDR,
                        timeout: 20000,
                    },
                );
                const nonce = g.data.result.page_nonce;
                const img = await axios.post(
                    `${this.pageBase}/${n}`,
                    { page_nonce: nonce, viewer_signature: this.viewerSig },
                    { ...HDR, responseType: 'blob', timeout: 45000 },
                );
                const bmp = await this.decodeBlob(img.data);
                const bw = bmp.width || bmp.naturalWidth,
                    bh = bmp.height || bmp.naturalHeight;
                this.pageState = {
                    ...this.pageState,
                    [n]: { status: 'loaded', bmp: markRaw(bmp), aspect: bw && bh ? bw / bh : null },
                };
                if (n === this.cur) this.$nextTick(this.fitPage);
            } catch (e) {
                const st = e?.response?.status;
                // Sesi viewer / nonce kedaluwarsa → handshake ulang lalu coba sekali lagi.
                if (retry && (st === 403 || st === 419)) {
                    if (await this.rehandshake()) return this.ensurePage(n, false);
                }
                // Gangguan sementara (jaringan putus, timeout, 5xx, 429) → satu kali ulang.
                if (retry && (!e?.response || st >= 500 || st === 429)) {
                    await new Promise((r) => setTimeout(r, 1200));
                    return this.ensurePage(n, false);
                }
                this.pageState = {
                    ...this.pageState,
                    [n]: { status: 'error', bmp: null, msg: this.pageErrMsg(e) },
                };
            }
        },
        pageErrMsg(e) {
            const st = e?.response?.status;
            if (st === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu muat ulang.';
            if (st === 403 || st === 419) return 'Sesi baca dokumen kedaluwarsa.';
            if (e?.code === 'ECONNABORTED') return 'Waktu muat habis — jaringan lambat.';
            if (!e?.response) return 'Koneksi terputus saat memuat halaman.';
            return 'Halaman gagal dimuat.';
        },
        /** Paksa muat ulang satu halaman (dipakai tombol di state error). */
        retryPage(n) {
            const st = { ...this.pageState };
            delete st[n];
            this.pageState = st;
            this.ensurePage(n);
        },
        /** Dekode blob → ImageBitmap (objectURL hanya dipakai sementara pada fallback). */
        async decodeBlob(blob) {
            if (typeof createImageBitmap === 'function') {
                try {
                    return await createImageBitmap(blob);
                } catch (e) {
                    /* lanjut ke fallback */
                }
            }
            const url = URL.createObjectURL(blob);
            try {
                const im = new Image();
                im.src = url;
                if (im.decode) await im.decode();
                else
                    await new Promise((res, rej) => {
                        im.onload = res;
                        im.onerror = rej;
                    });
                return im;
            } finally {
                URL.revokeObjectURL(url);
            }
        },
        /** Lepas seluruh bitmap halaman dari memori. */
        freePages() {
            Object.values(this.pageState).forEach((s) => {
                if (s && s.bmp && typeof s.bmp.close === 'function') s.bmp.close();
            });
            this.pageState = {};
        },
        /**
         * Sesi viewer di server hidup 300 dtk (SECURE_PDF_VIEWER_SESSION_TTL_SECONDS).
         * Membaca 1 dokumen jelas lebih lama dari itu, jadi segarkan tiap 4 menit supaya
         * tak ada halaman yang tertahan 403 di tengah baca.
         */
        startSigKeepalive() {
            if (this._sigTimer) clearInterval(this._sigTimer);
            this._sigTimer = setInterval(() => {
                this.rehandshake();
            }, 240000);
        },
        async rehandshake() {
            try {
                /* Dua sisi digabung, dan keduanya perlu:
                     - urlToken: rute ADMIN vs rute karyawan. Pratinjau admin
                       membuka draft & versi cabutan yang memang ditolak lensa
                       karyawan, jadi memakai rute karyawan berarti selalu 403.
                     - id_item: konteks item, dipakai server memutuskan apakah
                       watermark di-burn ke halamannya. Tanpa ini watermark
                       selalu menyala, termasuk pada item yang mematikannya. */
                const t = await axios.post(this.urlToken, { id_item: this.idItem }, HDR);
                const m = await axios.get(t.data.result.page_manifest_url, HDR);
                this.viewerSig = m.data.result.viewer_signature;
                this.pageBase = m.data.result.page_url_base;
                return true;
            } catch (e) {
                return false;
            }
        },
        /**
         * Pesan resume: satu baris, hilang sendiri.
         *
         * Sengaja TIDAK memakai dialog atau toast global — orang baru saja
         * membuka dokumen untuk membaca, dan apa pun yang menuntut ditutup dulu
         * justru memotong niat itu. Tombol "Ke awal" disediakan karena resume
         * otomatis kadang bukan yang diinginkan, dan tanpa itu satu-satunya
         * jalan kembali adalah menggulir mundur dua puluh halaman.
         */
        beriTahuResume(n) {
            this.resumeDari = n;
            clearTimeout(this.resumeTimer);
            this.resumeTimer = setTimeout(() => {
                this.resumeDari = 0;
            }, 6000);
        },
        tutupResume() {
            clearTimeout(this.resumeTimer);
            this.resumeDari = 0;
        },
        keAwal() {
            this.jump(1);
            this.tutupResume();
        },
        prefetch() {
            // Dua halaman ke depan: menutupi latensi grant+render agar next terasa instan.
            for (const d of [1, 2]) {
                const n = this.cur + d;
                if (n <= this.total) this.ensurePage(n);
            }
        },
        reload() {
            this.freePages();
            this.handshake();
        },
        manifestAspect(n) {
            const p = this.manifestPages && this.manifestPages[n - 1];
            if (!p) return null;
            const w = p.width || p.w || p.Width,
                h = p.height || p.h || p.Height;
            return w && h ? w / h : null;
        },
        fitPage() {
            const st = this.$refs.stage;
            if (!st) return;
            const cs = getComputedStyle(st);
            // Gunakan offsetWidth dan offsetHeight agar ukuran acuan 100% fit tidak goyang saat scrollbar muncul/hilang ketika zoomed
            const availW = Math.max(
                0,
                st.offsetWidth - parseFloat(cs.paddingLeft || 0) - parseFloat(cs.paddingRight || 0) - 12,
            );
            const availH = Math.max(
                0,
                st.offsetHeight - parseFloat(cs.paddingTop || 0) - parseFloat(cs.paddingBottom || 0) - 12,
            );
            if (availW <= 0 || availH <= 0) return;
            const pa = (this.curPage && this.curPage.aspect) || this.manifestAspect(this.cur) || 0.707;
            let w = availW,
                h = w / pa;
            if (h > availH) {
                h = availH;
                w = h * pa;
            }
            const newFitW = Math.round(w);
            const newFitH = Math.round(h);
            const land = (((this.rot % 360) + 360) % 360) % 180 === 90;
            const newRotFit = land ? Math.min(availW / h, availH / w, 1) : 1;

            if (Math.abs(this.fitW - newFitW) >= 1 || Math.abs(this.fitH - newFitH) >= 1) {
                this.fitW = newFitW;
                this.fitH = newFitH;
            }
            if (Math.abs(this.rotFit - newRotFit) > 0.001) {
                this.rotFit = newRotFit;
            }
        },

        /* ── Navigasi ── */
        go(n, dir) {
            if (this.busy || n < 1 || n > this.total) return;
            if (n > this.maxPage) {
                if (n !== this.maxPage + 1 || n !== this.cur + 1 || !this.dwellDone.has(this.cur)) return;
            }
            this.busy = true;
            this.cur = n;
            if (this.cur > this.maxPage) {
                this.maxPage = this.cur;
                this.trackPage();
            }
            this.zoom = 100;
            this.fitPage();
            this.ensurePage(n);
            this.prefetch();
            if (dir) {
                const pw = this.$refs.pagewrap;
                if (pw) {
                    pw.classList.remove('t-next', 't-prev');
                    void pw.offsetWidth;
                    pw.classList.add(dir === 'next' ? 't-next' : 't-prev');
                }
            }
            this.startDwell(n);
            setTimeout(() => (this.busy = false), 300);
        },
        jump(n) {
            n = Math.max(1, Math.min(n, this.maxPage));
            if (n !== this.cur) {
                this.cur = n;
                this.zoom = 100;
                this.fitPage();
                this.ensurePage(n);
                this.startDwell(n);
            }
            if (window.innerWidth < 900) this.closeDrawer();
        },
        thumbClick(p, ev) {
            if (p <= this.maxPage) this.jump(p);
            else {
                const el = ev.currentTarget;
                el.classList.remove('shake');
                void el.offsetWidth;
                el.classList.add('shake');
            }
        },

        startDwell(p) {
            if (this.dwellTimer) clearInterval(this.dwellTimer);
            this.dwellOn = false;
            if (this.dwellDone.has(p) || p >= this.total || this.readonly || this.tanpaLacak) {
                this.dwellDone.add(p);
                return;
            }
            this.dwellTotal = Math.max(1, this.dwellMin);
            this.dwellLeft = this.dwellTotal;
            this.dwellOn = true;
            this.dwellDeg = 0;
            this.dwellTimer = setInterval(() => {
                this.dwellLeft -= 0.1;
                this.dwellDeg = (1 - this.dwellLeft / this.dwellTotal) * 360;
                if (this.dwellLeft <= 0) {
                    this.dwellDone.add(p);
                    this.dwellOn = false;
                    clearInterval(this.dwellTimer);
                    this.$forceUpdate();
                }
            }, 100);
        },

        /**
         * Setor penanda halaman + delta waktu baca.
         *
         * @param {boolean} ditinggalkan Halaman sedang dibongkar (tab ditutup /
         *   di-refresh / berpindah ke belakang layar). Pada keadaan itu XHR biasa
         *   DIBATALKAN peramban sebelum sampai, jadi dipakai sendBeacon —
         *   permintaan diserahkan ke antrean sistem dan tetap terkirim setelah
         *   dokumennya hilang.
         */
        trackPage(ditinggalkan = false) {
            if (!this.idItem || this.readonly || this.tanpaLacak) return;
            const durasi = this._aktifDetik || 0;
            const majuHalaman = this.maxPage > (this._halamanTerkirim ?? 0);

            /*
             * TAK ADA KABAR, TAK ADA PERMINTAAN.
             *
             * Penjaga ini dulu hanya berlaku saat ditinggalkan. Akibatnya tab
             * yang tersembunyi tetap menembakkan satu permintaan tiap 60 detik
             * berisi nol detik baru dan halaman yang sama — dan sejak jalur
             * simpan diubah menjadi conditional update tunggal, permintaan itu
             * benar-benar MENULIS (menyentuh Updated_At) padahal dulu tidak
             * mengubah apa pun. Pada 200 pembaca itu ~3 tulis per detik ke
             * N_SOP_Progress, tabel terpanas di modul ini, pada basis data
             * tanpa RCSI.
             *
             * Kemajuan halaman TETAP dikirim walau tanpa detik baru: itu kabar
             * yang nyata, dan sebelumnya justru hilang saat orang menutup tab
             * tepat setelah pindah halaman.
             */
            if (durasi === 0 && !majuHalaman) return;

            this._aktifDetik = 0; // delta: sekali kirim, sekali nol — tak ada hitung ganda
            this._halamanTerkirim = this.maxPage;
            const muatan = {
                id_item: this.idItem,
                halaman: this.maxPage,
                durasi_detik: durasi,
            };

            const url = '/api/v1/sop/progress/halaman';

            if (ditinggalkan) {
                // Rute ini ada di grup `web`, jadi CSRF aktif. Token dikirim DUA
                // jalur sekaligus: lewat header bila alatnya mendukung, dan lewat
                // _token di badan sebagai jaring — sendBeacon tak bisa menyetel
                // header sama sekali.
                const token = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
                const badan = JSON.stringify({ ...muatan, _token: token });

                /*
                 * fetch + keepalive DIUTAMAKAN atas sendBeacon: ia bisa menyetel
                 * header (jadi CSRF lewat X-CSRF-TOKEN, jalur yang paling pasti)
                 * dan sama-sama bertahan setelah dokumen dibongkar. sendBeacon
                 * jadi cadangan untuk peramban yang belum mendukung keepalive.
                 */
                let bisaKeepalive = false;
                try {
                    bisaKeepalive = typeof Request === 'function' && 'keepalive' in new Request(url);
                } catch (e) {
                    /* biarkan false */
                }

                if (bisaKeepalive) {
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: badan,
                        keepalive: true,
                        credentials: 'same-origin',
                    }).catch(() => {
                        /* halaman sudah hilang — tak ada yang bisa dicoba lagi */
                    });

                    return;
                }

                if (navigator.sendBeacon) {
                    const terkirim = navigator.sendBeacon(url, new Blob([badan], { type: 'application/json' }));
                    // Antrean menolak (mis. muatan terlalu besar) -> kembalikan
                    // detiknya supaya tak hilang diam-diam bila halaman ternyata hidup.
                    if (!terkirim) this._aktifDetik += durasi;

                    return;
                }

                this._aktifDetik += durasi; // tak ada alat yang tersedia

                return;
            }

            axios.post(url, muatan).catch(() => {
                this._aktifDetik += durasi; /* gagal kirim → kembalikan, coba di flush berikutnya */
            });
        },

        /* ── Zoom / Pan / Rotate ── */
        setZoom(z) {
            const st = this.$refs.stage,
                prev = this.zoom / 100;
            const targetZoom = Math.max(100, Math.min(320, z));
            if (this.zoom === targetZoom) return;
            this.zoom = targetZoom;
            const nz = this.zoom / 100;
            this.$nextTick(() => {
                if (!st) return;
                if (nz <= 1) {
                    st.scrollLeft = 0;
                    st.scrollTop = 0;
                    return;
                }
                const availW = st.offsetWidth || st.clientWidth;
                const availH = st.offsetHeight || st.clientHeight;
                st.scrollLeft = (st.scrollLeft + availW / 2) * (nz / prev) - availW / 2;
                st.scrollTop = (st.scrollTop + availH / 2) * (nz / prev) - availH / 2;
            });
        },
        rotate(d) {
            this.rot += d;
            this.fitPage();
        },
        fit() {
            this.rot = 0;
            this.setZoom(100);
            this.fitPage();
        },

        /* ── Gestures ── */
        onDown(e) {
            this.toolsOpen = false;
            if (e.target.closest('.rd-navbtn')) return;

            // Track all active pointers for pinch detection
            this._pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            const ids = Object.keys(this._pointers);
            if (ids.length === 2) {
                // Pinch start — cancel any drag
                this.drag = null;
                const p1 = this._pointers[ids[0]],
                    p2 = this._pointers[ids[1]];
                this._pinchDist = Math.hypot(p2.x - p1.x, p2.y - p1.y);
                this._pinchStartZoom = this.zoom;
                return;
            }
            if (ids.length > 2) return;

            if (this.busy) return;
            const st = this.$refs.stage;
            this.drag = {
                x0: e.clientX,
                y0: e.clientY,
                moved: false,
                zoomed: this.zoom > 100,
                axis: null,
                sl0: st ? st.scrollLeft : 0,
                st0: st ? st.scrollTop : 0,
            };
            if (this.drag.zoomed) {
                this.grabbing = true; // kursor jadi "menggenggam"
            } else {
                const pw = this.$refs.pagewrap;
                if (pw) pw.style.transition = 'none';
            }
        },
        onMove(e) {
            // Update pointer position for pinch tracking
            if (this._pointers[e.pointerId]) {
                this._pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            }

            // Pinch-to-zoom (mobile: 2-finger, desktop: touchpad)
            if (this._pinchDist > 0) {
                const ids = Object.keys(this._pointers);
                if (ids.length === 2) {
                    const p1 = this._pointers[ids[0]],
                        p2 = this._pointers[ids[1]];
                    const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y);
                    const scale = dist / this._pinchDist;
                    this.setZoom(Math.round(this._pinchStartZoom * scale));
                }
                return;
            }

            if (!this.drag) return;
            const dx = e.clientX - this.drag.x0,
                dy = e.clientY - this.drag.y0;
            if (Math.abs(dx) > 6 || Math.abs(dy) > 6) this.drag.moved = true;

            // Zoomed → geser halaman (grab-to-pan), bukan ganti halaman.
            if (this.drag.zoomed) {
                const st = this.$refs.stage;
                if (st) {
                    st.scrollLeft = this.drag.sl0 - dx;
                    st.scrollTop = this.drag.st0 - dy;
                }
                if (e.cancelable) e.preventDefault();
                return;
            }
            if (!this.drag.axis) this.drag.axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
            if (this.drag.axis !== 'x') return;
            this.drag.moved = true;
            let d = dx;
            const fwd = dx < 0 && (this.cur >= this.total || !this.dwellDone.has(this.cur)),
                back = dx > 0 && this.cur <= 1;
            if (fwd || back) d = dx * 0.22;
            this.drag.d = d;
            const pw = this.$refs.pagewrap;
            if (pw) pw.style.transform = `translateX(${d}px) rotateY(${-d * 0.02}deg)`;
        },
        onUp(e) {
            // Clean up pointer tracking
            delete this._pointers[e.pointerId];
            const rest = Object.keys(this._pointers);
            if (rest.length < 2) this._pinchDist = 0;

            // Cubit selesai tapi satu jari masih menempel → lanjutkan jadi geser.
            if (rest.length === 1 && this.zoom > 100) {
                const p = this._pointers[rest[0]],
                    stg = this.$refs.stage;
                this.grabbing = true;
                this.drag = {
                    x0: p.x,
                    y0: p.y,
                    moved: true, // bukan tap
                    zoomed: true,
                    axis: null,
                    sl0: stg ? stg.scrollLeft : 0,
                    st0: stg ? stg.scrollTop : 0,
                };
                return;
            }

            this.grabbing = false;
            if (!this.drag) return;
            const dr = this.drag;
            this.drag = null;
            if (dr.zoomed) {
                if (!dr.moved) this.handleTap();
                return;
            }
            const pw = this.$refs.pagewrap;
            if (pw) {
                pw.style.transition = '';
                pw.style.transform = '';
            }
            if (dr.axis === 'x' && Math.abs(dr.d || 0) > 70) {
                dr.d < 0 ? this.go(this.cur + 1, 'next') : this.go(this.cur - 1, 'prev');
            } else if (!dr.moved) {
                this.handleTap();
            }
        },
        onWheel(e) {
            // Ctrl+scroll (mouse) or pinch-to-zoom (touchpad) → zoom
            if (e.ctrlKey) {
                e.preventDefault();
                const step = -e.deltaY;
                this.setZoom(this.zoom + Math.sign(step) * 10);
                return;
            }

            if (this.zoom > 100) return;
            if (Math.abs(e.deltaX) <= Math.abs(e.deltaY) + 2) return;
            e.preventDefault();
            if (this._wl) return;
            this._wa = (this._wa || 0) + e.deltaX;
            clearTimeout(this._wt);
            this._wt = setTimeout(() => (this._wa = 0), 160);
            if (Math.abs(this._wa) > 55) {
                this._wa > 0 ? this.go(this.cur + 1, 'next') : this.go(this.cur - 1, 'prev');
                this._wa = 0;
                this._wl = true;
                setTimeout(() => (this._wl = false), 480);
            }
        },
        handleTap() {
            // Debounce: single-click → toggle immersive; double-click (within 300ms) → fit zoom
            if (this._clickTimer) {
                clearTimeout(this._clickTimer);
                this._clickTimer = null;
                if (this.zoom > 100) this.fit();
            } else {
                this._clickTimer = setTimeout(() => {
                    this._clickTimer = null;
                    this.immersive = !this.immersive;
                }, 300);
            }
        },
        onKey(e) {
            const k = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && (k === 's' || k === 'p')) {
                e.preventDefault(); // cegah Simpan halaman & Cetak
                return;
            }
            if (e.key === 'ArrowRight') this.go(this.cur + 1, 'next');
            else if (e.key === 'ArrowLeft') this.go(this.cur - 1, 'prev');
            else if (e.key === 'Escape') this.handleEsc();
        },

        /* ── Progress Scrubbing ── */
        scrubDown(e) {
            this.scrub = true;
            this.scrubTo(e);
        },
        scrubTo(e) {
            if (!this.$refs.track) return;
            const r = this.$refs.track.getBoundingClientRect();
            let f = (e.clientX - r.left) / r.width;
            f = Math.max(0, Math.min(1, f));
            let p = Math.max(1, Math.min(Math.round(1 + f * (this.total - 1)), this.maxPage));
            if (p !== this.cur) this.jump(p);
        },
        nodeLeft(p) {
            return this.total > 1 ? ((p - 1) / (this.total - 1)) * 100 : 0;
        },
        nodeCls(p) {
            return p === this.cur ? 'cur' : p <= this.maxPage ? (this.allRead ? 'read done' : 'read') : 'locked';
        },

        /* ── Hold to Finish ── */
        holdStart() {
            if (!this.canFinish) return;
            this.showHoldHint = false;
            this.holdPct = 0;
            if (navigator.vibrate) {
                try {
                    navigator.vibrate(35);
                } catch (e) {}
            }
            this.holdTimer = setInterval(() => {
                this.holdPct += 100 / 15;
                if (navigator.vibrate && Math.round(this.holdPct) % 25 === 0) {
                    try {
                        navigator.vibrate(15);
                    } catch (e) {}
                }
                if (this.holdPct >= 100) this.holdEnd(true);
            }, 100);
        },
        holdEnd(ok) {
            if (this.holdTimer) clearInterval(this.holdTimer);
            this.holdTimer = null;
            if (ok) {
                this.holdPct = 100;
                if (navigator.vibrate) {
                    try {
                        navigator.vibrate([40, 40, 80]);
                    } catch (e) {}
                }
                this.showSuccess = true;
                this.$emit('complete');
            } else {
                if (this.holdPct > 0 && this.holdPct < 100) {
                    this.triggerHoldHint();
                }
                this.holdPct = 0;
            }
        },
        onFinishBtnClick() {
            if (this.bannerMinimized) {
                this.bannerMinimized = false;
                return;
            }
            if (this.holdPct < 100) {
                this.triggerHoldHint();
            }
        },
        triggerHoldHint() {
            this.showHoldHint = true;
            if (this._hintTimer) clearTimeout(this._hintTimer);
            this._hintTimer = setTimeout(() => {
                this.showHoldHint = false;
            }, 4500);
        },
    },
    created() {
        this._scrubMove = (e) => {
            if (this.scrub) this.scrubTo(e);
        };
        this._scrubUp = () => (this.scrub = false);
        window.addEventListener('pointermove', this._scrubMove);
        window.addEventListener('pointerup', this._scrubUp);
    },
    unmounted() {
        window.removeEventListener('pointermove', this._scrubMove);
        window.removeEventListener('pointerup', this._scrubUp);
    },
};
</script>

<style scoped>
.text-indigo {
    color: var(--hcis-indigo-500) !important;
}
.rd-hidesm {
    display: inline;
}

.rd {
    position: fixed;
    inset: 0;
    z-index: 1090;
    display: flex;
    flex-direction: column;
    color: var(--hcis-slate-800);
    -webkit-user-select: none;
    user-select: none;
    -webkit-touch-callout: none;
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, #edf2f7 100%);
}

/* ── Top Bar ── */
.rd-bar {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.85rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border, #dbe1ea);
    z-index: 30;
    transition:
        opacity 0.35s ease,
        max-height 0.35s ease,
        padding 0.35s ease;
    max-height: 60px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.rd.immersive .rd-bar,
.rd.immersive .rd-foot,
.rd.immersive .rd-nav {
    opacity: 0;
    pointer-events: none;
}
.rd.immersive .rd-bar {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    border-bottom-width: 0;
}
.rd.immersive .rd-foot {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    border-top-width: 0;
}

.rd-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: all 0.16s ease;
}
.rd-ico i {
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rd-ico.sm {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    font-size: 0.82rem;
}
.rd-ico:hover {
    color: var(--hcis-indigo-600);
    border-color: #c7cbf5;
    background: #f5f6ff;
}
.rd-ico.active {
    color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-500);
    background: #eeefff;
}

.rd-titles {
    flex: 1;
    min-width: 0;
    line-height: 1.2;
}
.rd-eyebrow {
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--hcis-indigo-500);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-title {
    font-weight: 800;
    font-size: 0.88rem;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-spacer {
    flex: 0 0 0px;
} /* spacer no longer eats space — titles takes it */

.rd-sec {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: 999px;
    padding: 0.26rem 0.6rem;
    white-space: nowrap;
}
/* Tombol unduh dokumen ber-izin. Lebih lebar dari .rd-ico polos karena membawa
   label teks di layar lebar; di layar sempit .rd-hidesm menyembunyikan labelnya
   dan tombolnya menyusut kembali jadi ikon. */
.rd-unduh {
    width: auto;
    gap: 0.4rem;
    padding: 0 0.7rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.3);
}
.rd-unduh:hover:not(:disabled) {
    background: rgba(16, 185, 129, 0.2);
    color: var(--hcis-emerald-800);
}
.rd-unduh:disabled {
    opacity: 0.6;
    cursor: progress;
}
.rd-spin-ico {
    animation: sp 0.8s linear infinite;
}

.rd-tools {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.rd-trow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.rd-seg {
    display: inline-flex;
    background: var(--hcis-slate-100);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 10px;
    padding: 3px;
    gap: 2px;
}
.rd-seg button {
    border: 0;
    background: transparent;
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.3rem 0.55rem;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.rd-seg button.on {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    font-weight: 800;
}

.rd-zoom {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-100);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 10px;
    padding: 2px;
}
.rd-zoom button {
    width: 26px;
    height: 26px;
    border: 0;
    background: transparent;
    color: var(--hcis-slate-500);
    cursor: pointer;
    border-radius: 7px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rd-zoom button i {
    line-height: 1;
}
.rd-zoom span {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    min-width: 42px;
    text-align: center;
    font-variant-numeric: tabular-nums;
}
.rd-more {
    display: none;
}
.rd-chip {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    background: var(--hcis-slate-100);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 999px;
    padding: 0.3rem 0.65rem;
    font-variant-numeric: tabular-nums;
}

/* ── Main Workspace Stage ── */
.rd-mid {
    flex: 1;
    min-height: 0;
    position: relative;
    overflow: hidden;
}
.rd-stage {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: safe center;
    padding: 1.8rem 1rem;
    perspective: 2600px;
    overflow: auto;
    cursor: grab;
    touch-action: none;
}
/* Saat zoom, JS yang menggeser (grab-to-pan) — matikan pan native agar tak dobel
   dan agar pointermove tetap sampai ke handler kita. */
.rd.zoomed-pan .rd-stage {
    touch-action: none;
    cursor: grab;
}
.rd.grabbing .rd-stage,
.rd.grabbing .rd-stage * {
    cursor: grabbing;
}
.rd-stage::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.rd-stage::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.22);
    border-radius: 8px;
}
.rd-stage::-webkit-scrollbar-track {
    background: transparent;
}

.rd-vignette {
    position: absolute;
    inset: 0;
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.5s;
    box-shadow: inset 0 0 160px 50px rgba(15, 23, 42, 0.13);
}
.rd.immersive .rd-vignette {
    opacity: 1;
}
.rd-book {
    position: relative;
    min-width: 100%;
    min-height: 100%;
    display: grid;
    place-items: safe center;
    z-index: 5;
}
.rd-pagewrap {
    transform-style: preserve-3d;
    transform-origin: left center;
}
.rd-pagewrap.t-next {
    animation: tn 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}
.rd-pagewrap.t-prev {
    animation: tp 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes tn {
    0% {
        transform: rotateY(0);
    }
    48% {
        transform: rotateY(-18deg);
    }
    100% {
        transform: rotateY(0);
    }
}
@keyframes tp {
    0% {
        transform: rotateY(0);
    }
    48% {
        transform: rotateY(18deg);
    }
    100% {
        transform: rotateY(0);
    }
}

.rd-page {
    position: relative;
    /* % terhadap kontainer, BUKAN vw (viewport) — fitPage() di script sudah
       menghitung ukuran pas dari .rd-stage yang sesungguhnya dan menuliskannya
       sebagai width/height inline. max-width dalam vw akan bersaing dengan
       angka itu memakai basis yang beda (lebar viewport, bukan kontainer), dan
       di layar mana pun yang lebih lebar dari ±1100px CSS ini diam-diam
       memenangkan pertarungan lalu memperkecil halaman di bawah ukuran "fit"
       yang sebenarnya dihitung JS — padahal indikator zoom tetap bilang 100%. */
    max-width: 100%;
    max-height: 100%;
    background: transparent;
    box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.28);
    overflow: hidden;
    transform: rotate(var(--rot, 0deg)) scale(var(--rotfit, 1));
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: grid;
    place-items: center;
    border-radius: 4px;
}
.rd.zoomed-pan .rd-page {
    max-width: none;
    max-height: none;
    transition: none !important;
}
.rd-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    transform-origin: center;
    will-change: transform;
    pointer-events: none; /* gestur ditangani .rd-stage; kanvas tak bisa disentuh langsung */
    user-select: none;
    -webkit-user-drag: none;
    -webkit-touch-callout: none;
}
.rd.mode-sepia .rd-img {
    filter: sepia(0.4) saturate(1.1) brightness(0.98);
}
.rd.mode-night .rd-img {
    filter: invert(1) hue-rotate(180deg) brightness(0.92) contrast(0.95);
}

.rd-skel {
    display: grid;
    place-items: center;
    gap: 0.6rem;
    color: var(--hcis-slate-400);
    font-size: 0.78rem;
    font-weight: 600;
}
.rd-msg {
    display: grid;
    place-items: center;
    gap: 0.6rem;
    color: var(--hcis-slate-500);
    padding: 2rem;
    text-align: center;
}
.rd-msg .bi {
    font-size: 2.2rem;
}
.rd-retry {
    border: 1px solid rgba(99, 102, 241, 0.4);
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-indigo-600);
    border-radius: 8px;
    padding: 0.4rem 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.rd-spin {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 3px solid rgba(15, 23, 42, 0.12);
    border-top-color: var(--hcis-indigo-500);
    animation: sp 0.9s linear infinite;
}
.rd-spin.sm {
    width: 20px;
    height: 20px;
    border-width: 2px;
}
@keyframes sp {
    to {
        transform: rotate(360deg);
    }
}

.rd-drm {
    position: absolute;
    top: 10px;
    right: 12px;
    z-index: 4;
    color: rgba(15, 23, 42, 0.22);
    font-size: 0.85rem;
}
.rd.mode-night .rd-drm {
    color: rgba(255, 255, 255, 0.4);
}

/* ── Side Navigation Buttons ── */
.rd-nav {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 70px;
    display: grid;
    place-items: center;
    z-index: 20;
}
.rd-nav.left {
    left: 0;
}
.rd-nav.right {
    right: 0;
}
.rd-slot {
    position: relative;
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
}

.rd-navbtn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
    cursor: pointer;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    position: relative;
    overflow: hidden;
    transition: all 0.2s ease;
}
.rd-navbtn i {
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rd-navbtn:hover:not(:disabled) {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-white);
    transform: scale(1.05);
}
.rd-navbtn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.rd-navbtn.pulse {
    border-color: var(--hcis-emerald-500);
    background: var(--hcis-emerald-500);
    color: var(--hcis-white);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5);
    animation: navPulse 1.6s ease-in-out infinite;
    pointer-events: auto;
    opacity: 1;
}
@keyframes navPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5);
    }
    70% {
        box-shadow: 0 0 0 14px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

.rd-nfill {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(5, 34, 26, 0.22);
    z-index: 1;
    transition: height 0.1s linear;
    pointer-events: none;
}
.rd-dwell {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    z-index: 1;
    opacity: 0;
    background: conic-gradient(var(--hcis-indigo-500) var(--deg, 0deg), rgba(99, 102, 241, 0.12) 0);
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
    mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 3px));
}
.rd-dwell.on {
    opacity: 1;
}
.rd-dtag {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--hcis-indigo-500);
    white-space: nowrap;
    opacity: 0;
}
.rd-dtag.on {
    opacity: 1;
}
.rd-dtag.done {
    color: var(--hcis-emerald-500);
    bottom: -32px;
}

/* ── Backdrop Overlay ── */
.rd-back {
    position: absolute;
    inset: 0;
    z-index: 40;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
    pointer-events: auto;
}
.rd-backdrop-enter-active,
.rd-backdrop-leave-active {
    transition: opacity 0.35s ease !important;
}
.rd-backdrop-enter-from,
.rd-backdrop-leave-to {
    opacity: 0 !important;
}
.rd-backdrop-enter-to,
.rd-backdrop-leave-from {
    opacity: 1 !important;
}

/* ── Offcanvas Drawer Sidebar (Pure Slide from Right) ── */
.rd-drawer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: min(360px, 88vw);
    z-index: 50;
    display: flex;
    flex-direction: column;
    background: var(--hcis-white);
    border-left: 1px solid var(--border, #dbe1ea);
    box-shadow: -16px 0 40px rgba(15, 23, 42, 0.18);
    pointer-events: auto;
}
.rd-drawer-slide-enter-active,
.rd-drawer-slide-leave-active {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1) !important;
    will-change: transform !important;
}
.rd-drawer-slide-enter-from,
.rd-drawer-slide-leave-to {
    transform: translate3d(100%, 0, 0) !important;
}
.rd-drawer-slide-enter-to,
.rd-drawer-slide-leave-from {
    transform: translate3d(0, 0, 0) !important;
}

/* ── Navigasi Dokumen Drawer (Left) ── */
.rd-navdrawer {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: min(360px, 90vw);
    z-index: 50;
    display: flex;
    flex-direction: column;
    background: var(--hcis-white);
    border-right: 1px solid var(--border, #dbe1ea);
    box-shadow: 16px 0 40px rgba(15, 23, 42, 0.18);
    pointer-events: auto;
}
.rd-nav-slide-enter-active,
.rd-nav-slide-leave-active {
    transition: transform 0.38s cubic-bezier(0.16, 1, 0.3, 1) !important;
    will-change: transform !important;
}
.rd-nav-slide-enter-from,
.rd-nav-slide-leave-to {
    transform: translate3d(-100%, 0, 0) !important;
}
.rd-nav-slide-enter-to,
.rd-nav-slide-leave-from {
    transform: translate3d(0, 0, 0) !important;
}

/* Header compact single-row */
.rd-nhead {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.85rem;
    border-bottom: 1px solid #eef2f7;
    background: linear-gradient(180deg, #fafbff 0%, var(--hcis-white) 100%);
    flex-shrink: 0;
}
.rd-nhead b {
    font-size: 0.78rem;
    color: var(--hcis-slate-900);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-right: auto;
}
.rd-nhead b .bi {
    color: var(--hcis-indigo-500);
    font-size: 0.8rem;
}

.rd-nprog-ring {
    position: relative;
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
}
.rd-nprog-ring svg {
    position: absolute;
    inset: 0;
    width: 28px;
    height: 28px;
}
.rd-nprog-val {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--hcis-indigo-600);
    line-height: 1;
}
.rd-nprog-val small {
    font-size: 0.5em;
    font-weight: 700;
}

.rd-nstat {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}
.rd-nstat b {
    color: var(--hcis-indigo-600);
}

/* Timeline spine container */
.rd-ntrack {
    position: relative;
    padding: 0.2rem 0 1.2rem;
}
.rd-ntrack::before {
    content: '';
    position: absolute;
    left: 31px;
    top: 18px;
    bottom: 18px;
    width: 2px;
    background: var(--hcis-slate-300);
    border-radius: 1px;
    z-index: 0;
}

/* Section milestone */
.rd-nmile {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.6rem 1rem 0.3rem 1rem;
    z-index: 1;
}
.rd-nmile:first-child {
    padding-top: 0.15rem;
}

.rd-nmile-bulb {
    width: 30px;
    height: 30px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    box-shadow: 0 4px 12px -3px rgba(99, 102, 241, 0.5);
    flex-shrink: 0;
    margin-left: 0;
    z-index: 2;
    transition: all 0.3s ease;
}
.rd-nmile-bulb i,
.rd-nmile-bulb span {
    line-height: 1;
}
.rd-nmile.done .rd-nmile-bulb {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
    box-shadow: 0 4px 12px -3px rgba(16, 185, 129, 0.45);
    font-size: 0.82rem;
}

.rd-nmile-body {
    flex: 1;
    min-width: 0;
    padding-top: 1px;
}
.rd-nmile-name {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.84rem;
    line-height: 1.2;
    margin-bottom: 0.25rem;
}
.rd-nmile-bar {
    height: 3px;
    background: #eef2f7;
    border-radius: 999px;
    overflow: hidden;
}
.rd-nmile-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
    transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-nmile.done .rd-nmile-fill {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}
.rd-nmile-sub {
    font-size: 0.64rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    margin-top: 0.2rem;
}

/* Item leaf nodes */
.rd-nleaf {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.1rem 1rem 0.1rem 1rem;
    z-index: 1;
    cursor: pointer;
}
.rd-nleaf:hover .rd-nleaf-card {
    border-color: var(--hcis-indigo-400);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);
    transform: translateX(2px);
}

/* dot node */
.rd-nleaf-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    line-height: 1;
    background: var(--hcis-white);
    border: 2px solid var(--border, var(--hcis-slate-300));
    color: var(--hcis-slate-400);
    flex-shrink: 0;
    z-index: 2;
    transition: all 0.22s ease;
    margin-top: 8px;
    margin-left: 5px;
}
.rd-nleaf-dot i {
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rd-nleaf.done .rd-nleaf-dot {
    background: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
    color: #fff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}
.rd-nleaf.current .rd-nleaf-dot {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: #fff;
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.2),
        0 2px 10px rgba(99, 102, 241, 0.35);
    animation: navDotPulse 2s ease infinite;
}
@keyframes navDotPulse {
    0%,
    100% {
        box-shadow:
            0 0 0 4px rgba(99, 102, 241, 0.2),
            0 2px 10px rgba(99, 102, 241, 0.35);
    }
    50% {
        box-shadow:
            0 0 0 9px rgba(99, 102, 241, 0.06),
            0 2px 14px rgba(99, 102, 241, 0.45);
    }
}
.rd-nleaf.locked .rd-nleaf-dot {
    background: var(--hcis-slate-100);
    border-color: var(--border, #dbe1ea);
    color: var(--hcis-slate-300);
}

/* item card */
.rd-nstat-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.22rem 0.65rem;
    white-space: nowrap;
}
.rd-nstat-pill b {
    color: var(--hcis-indigo-600);
}

/* item card */
.rd-nleaf-card {
    flex: 1;
    min-width: 0;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.5rem 0.75rem;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0.4rem;
}
.rd-nleaf.done .rd-nleaf-card {
    background: rgba(16, 185, 129, 0.03);
    border-color: rgba(16, 185, 129, 0.22);
    border-left: 3px solid var(--hcis-emerald-500);
}
.rd-nleaf.current .rd-nleaf-card {
    background: rgba(99, 102, 241, 0.05);
    border-color: rgba(99, 102, 241, 0.35);
    border-left: 3px solid var(--hcis-indigo-500);
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.12),
        0 6px 18px -6px rgba(99, 102, 241, 0.28);
}
.rd-nleaf.locked .rd-nleaf-card {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
    opacity: 0.78;
}

.rd-nleaf-top {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.15rem;
}
.rd-nleaf-tipe {
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border-radius: 4px;
    padding: 0.12rem 0.4rem;
}
.rd-nleaf.done .rd-nleaf-tipe {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
}

.rd-nleaf-now {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
}
.rd-nleaf-ok {
    display: inline-flex;
    align-items: center;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
}
.rd-nleaf-lock {
    display: inline-flex;
    align-items: center;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
}
.rd-nleaf-pulse {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-indigo-500);
    animation: navPulse 1.5s ease infinite;
}
@keyframes navPulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.45;
        transform: scale(1.4);
    }
}

.rd-nleaf-ok {
    color: var(--hcis-emerald-500);
    font-size: 0.7rem;
}

.rd-nleaf-title {
    font-weight: 700;
    font-size: 0.74rem;
    color: var(--hcis-slate-700);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.rd-nleaf.current .rd-nleaf-title {
    color: var(--hcis-slate-800);
}
.rd-nleaf.done .rd-nleaf-title {
    color: var(--hcis-slate-500);
}

/* animated current indicator bar */
.rd-nleaf-curbar {
    height: 2px;
    background: #eef2f7;
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}
.rd-nleaf-curfill {
    display: block;
    height: 100%;
    width: 60%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6, var(--hcis-indigo-500));
    background-size: 200% 100%;
    animation: curShimmer 2s linear infinite;
}
@keyframes curShimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* End marker */
.rd-nend {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.5rem 1.1rem 0 1rem;
    z-index: 1;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
}
.rd-nend-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-400);
    font-size: 0.65rem;
    margin-left: 5px;
    z-index: 2;
}
.rd-nend-dot i {
    line-height: 1;
}

/* empty */
.rd-nempty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2.5rem 0;
    color: var(--hcis-slate-400);
    font-size: 0.82rem;
    font-weight: 600;
}
.rd-nempty .bi {
    font-size: 1.6rem;
}

.rd-dhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-slate-50);
}
.rd-dhead b {
    font-size: 0.85rem;
    color: var(--hcis-slate-900);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.rd-phead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border, #dbe1ea);
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
    background: var(--hcis-white);
}
.rd-pacts {
    display: flex;
    gap: 0.4rem;
}
.rd-mini {
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    font-size: 0.64rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.3rem 0.55rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.rd-mini:hover {
    background: #f5f6ff;
    border-color: #c7cbf5;
}

.rd-dscroll {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}
.rd-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
    gap: 12px;
}
.rd-cell {
    position: relative;
    aspect-ratio: 1/1.414;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-slate-100);
    cursor: pointer;
    outline: 2px solid transparent;
    transition:
        transform 0.16s ease,
        box-shadow 0.16s ease;
}
.rd-cell.read:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
}
.rd-cell.cur {
    outline-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.28);
}
.rd-cell.locked {
    border-style: dashed;
    cursor: not-allowed;
    background: var(--hcis-slate-50);
    opacity: 0.7;
}
.rd-cell.shake {
    animation: shk 0.35s;
}
@keyframes shk {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-4px);
    }
    75% {
        transform: translateX(4px);
    }
}

.rd-cimg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top;
    background: var(--hcis-white);
    pointer-events: none;
    user-select: none;
    -webkit-user-drag: none;
    -webkit-touch-callout: none;
}

/* Konten Dokumen tak ikut tercetak */
@media print {
    .rd {
        display: none !important;
    }
}
.rd-cskel {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
}
.rd-clock {
    position: absolute;
    inset: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
    font-size: 1.1rem;
}
.rd-clock i {
    line-height: 1;
}
.rd-pn {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: linear-gradient(transparent, rgba(15, 23, 42, 0.75));
    padding: 0.6rem 0.2rem 0.2rem;
}
.rd-rd {
    position: absolute;
    top: 5px;
    left: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    color: var(--hcis-white);
    font-size: 0.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
}
.rd-rd i {
    line-height: 1;
}

/* ── Success Overlay & Redirection Banner ── */
.rd-success {
    position: absolute;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    background: rgba(248, 250, 252, 0.95);
    backdrop-filter: blur(14px);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-success.show {
    opacity: 1;
    pointer-events: auto;
}
.rd-scard {
    text-align: center;
    width: min(420px, calc(100vw - 2.5rem));
    padding: 2.2rem 1.8rem;
    background: var(--hcis-white);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 24px;
    box-shadow:
        0 24px 60px -15px rgba(16, 185, 129, 0.28),
        0 10px 30px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    transform: scale(0.92);
    transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-success.show .rd-scard {
    transform: scale(1);
    animation: successCardPop 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes successCardPop {
    0% {
        opacity: 0;
        transform: scale(0.88) translateY(16px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.rd-sring-wrap {
    position: relative;
    width: 96px;
    height: 96px;
    display: grid;
    place-items: center;
    margin-bottom: 0.25rem;
}
.rd-sring-pulse {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.18);
    animation: ringPulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes ringPulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.6;
    }
    50% {
        transform: scale(1.18);
        opacity: 0.1;
    }
}

.rd-sring {
    position: relative;
    z-index: 2;
    width: 88px;
    height: 88px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.8rem;
    line-height: 1;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    box-shadow: 0 16px 36px rgba(16, 185, 129, 0.42);
    animation: iconCheckBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes iconCheckBounce {
    0% {
        transform: scale(0);
    }
    60% {
        transform: scale(1.15);
    }
    100% {
        transform: scale(1);
    }
}

.rd-scard h2 {
    margin: 0;
    font-weight: 900;
    color: var(--hcis-slate-900);
    font-size: 1.45rem;
    letter-spacing: -0.01em;
}
.rd-stext {
    color: var(--hcis-slate-600);
    font-size: 0.88rem;
    font-weight: 600;
    margin: 0;
    line-height: 1.4;
}
.rd-stext b {
    color: var(--hcis-emerald-600);
}

/* Redirection Badge */
.rd-sredir {
    width: 100%;
    margin-top: 0.6rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(99, 102, 241, 0.06) 100%);
    border: 1px solid rgba(16, 185, 129, 0.22);
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.rd-sredir-msg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    line-height: 1.35;
}
.rd-sredir-msg .spin-icon {
    font-size: 1rem;
    color: var(--hcis-emerald-500);
    animation: redirSpin 1.2s linear infinite;
    flex-shrink: 0;
}
@keyframes redirSpin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.rd-spin-dots {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.rd-spin-dots span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    animation: dotPulse 1.4s ease-in-out infinite;
}
.rd-spin-dots span:nth-child(2) {
    animation-delay: 0.2s;
}
.rd-spin-dots span:nth-child(3) {
    animation-delay: 0.4s;
}
@keyframes dotPulse {
    0%,
    80%,
    100% {
        transform: scale(0.6);
        opacity: 0.4;
    }
    40% {
        transform: scale(1.2);
        opacity: 1;
    }
}

/* ── Bottom Progress Footer ── */
.rd-foot {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0.65rem 1rem 0.5rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(14px);
    border-top: 1px solid var(--border, #dbe1ea);
    z-index: 30;
    padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
    flex-wrap: wrap;
    transition: opacity 0.4s;
}
.rd-prog {
    flex: 1;
    min-width: 0;
}
.rd-track {
    position: relative;
    height: 28px;
    display: flex;
    align-items: center;
    cursor: pointer;
    touch-action: none;
}
.rd-track::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 4px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.09);
}
.rd-fill {
    position: absolute;
    left: 0;
    top: 50%;
    height: 4px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-indigo-400));
    transition: width 0.4s ease;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
}
.rd-fill.done {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}
.rd-node {
    position: absolute;
    top: 50%;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: rgba(15, 23, 42, 0.16);
    z-index: 2;
    transition: all 0.25s ease;
}
.rd-node.read {
    background: var(--hcis-indigo-500);
}
.rd-node.read.done {
    background: var(--hcis-emerald-500);
}
.rd-node.locked {
    width: 7px;
    height: 7px;
    background: rgba(15, 23, 42, 0.14);
}
.rd-node.cur {
    width: 16px;
    height: 16px;
    background: var(--hcis-white);
    border: 2px solid var(--hcis-indigo-500);
    box-shadow:
        0 0 0 3px rgba(99, 102, 241, 0.28),
        0 1px 4px rgba(15, 23, 42, 0.25);
    z-index: 3;
}
.rd-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 0.05rem;
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.rd-ctrls {
    display: none;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
}
.rd-ctrls .rd-seg {
    flex-shrink: 0;
}
.rd-ctrls .rd-zoom {
    flex: 1;
    justify-content: center;
}

/* ── Responsive Styling ── */
@media (max-width: 760px) {
    .rd-foot {
        gap: 0;
    }

    .rd-hidesm {
        display: none;
    }
    .rd-chip {
        display: none;
    }
    .rd-sec {
        display: none;
    } /* hide "Terlindungi" badge on mobile — save space for title */
    .rd-ctrls {
        display: flex;
        flex-basis: 100%;
        justify-content: space-between;
        width: 100%;
    }
    .rd-bar .rd-tools {
        display: none;
    }
    .rd-bar {
        gap: 0.35rem;
        padding: 0.45rem 0.55rem;
    }
    .rd-ico {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-size: 0.85rem;
    }
    .rd-nav {
        width: 48px;
    }
    .rd-navbtn {
        width: 40px;
        height: 40px;
    }
    .rd-prog {
        flex-basis: 100%;
    }
    .rd-eyebrow {
        font-size: 0.54rem;
        letter-spacing: 0.06em;
    }
    .rd-title {
        font-size: 0.8rem;
    }
}

/* ══════════════ Pita resume ══════════════
   Melayang di atas panggung, bukan mendorongnya: pita yang memakan tinggi lalu
   hilang 6 detik kemudian akan membuat halaman melompat sendiri. */
.rd-res {
    position: absolute;
    top: 3.9rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 12;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    max-width: calc(100% - 1.5rem);
    padding: 0.4rem 0.5rem 0.4rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    color: var(--hcis-slate-800);
    background: rgba(255, 255, 255, 0.97);
    border: 1px solid #dbe1ea;
    box-shadow: 0 12px 30px -14px rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(8px);
}
.rd-res > i {
    color: var(--hcis-indigo-600);
}
.rd-res span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-res strong {
    color: var(--hcis-indigo-700);
    font-weight: 800;
}

.rd-res-awal {
    flex-shrink: 0;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
}
.rd-res-awal:hover {
    background: var(--hcis-indigo-100);
}
.rd-res-x {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--hcis-slate-400);
    cursor: pointer;
    display: grid;
    place-items: center;
}
.rd-res-x:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.rd-res-enter-active,
.rd-res-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}
.rd-res-enter-from,
.rd-res-leave-to {
    opacity: 0;
    transform: translate(-50%, -8px);
}

@media (max-width: 560px) {
    .rd-res {
        top: 3.5rem;
        font-size: 0.73rem;
        padding-left: 0.6rem;
    }
    .rd-res-awal {
        padding: 0.18rem 0.5rem;
        font-size: 0.68rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .rd-res-enter-active,
    .rd-res-leave-active {
        transition: none;
    }
}

/* ══════════════ Floating Completion Banner ══════════════ */
.rd-finish-banner {
    position: absolute;
    bottom: 4.8rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 35;
    width: min(440px, calc(100vw - 2rem));
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(16, 185, 129, 0.35);
    border-radius: 20px;
    padding: 1.1rem 1.25rem;
    box-shadow:
        0 20px 45px -12px rgba(16, 185, 129, 0.28),
        0 4px 16px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    pointer-events: auto;
    animation: bannerBounce 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd.immersive .rd-finish-banner {
    bottom: 2rem;
}
@keyframes bannerBounce {
    0% {
        opacity: 0;
        transform: translate(-50%, 20px) scale(0.94);
    }
    100% {
        opacity: 1;
        transform: translate(-50%, 0) scale(1);
    }
}
.rd-pop-enter-active,
.rd-pop-leave-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-pop-enter-from,
.rd-pop-leave-to {
    opacity: 0;
    transform: translate(-50%, 24px) scale(0.95);
}

.rd-fb-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.rd-fb-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.25));
    color: var(--hcis-emerald-500);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.2);
}
.rd-fb-icon i {
    line-height: 1;
}
.rd-fb-info {
    flex: 1;
    min-width: 0;
}
.rd-fb-title {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--hcis-slate-900);
    line-height: 1.25;
}
.rd-fb-sub {
    font-size: 0.76rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
    margin-top: 0.15rem;
}
.text-emerald {
    color: var(--hcis-emerald-600);
}

.rd-fb-body {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

/* Form kosong di akhir contoh pengisian FORM. */
.rd-fb-lampiran {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.6rem 0.7rem;
    border-radius: 10px;
    border: 1px dashed var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}
.rd-fb-lampiran-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
}
.rd-fb-lampiran-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.rd-fb-lampiran-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    max-width: 100%;
    min-height: 34px;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-surface);
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    cursor: pointer;
}
.rd-fb-lampiran-btn:hover {
    border-color: var(--hcis-slate-400);
}
.rd-fb-lampiran-btn.semua {
    color: var(--hcis-emerald-600);
}
.rd-fb-lampiran-nama {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-fb-lampiran-ext {
    flex-shrink: 0;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.rd-fb-btn {
    position: relative;
    width: 100%;
    height: 48px;
    border-radius: 14px;
    border: 0;
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: var(--hcis-white);
    font-weight: 800;
    font-size: 0.88rem;
    letter-spacing: 0.03em;
    cursor: pointer;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px -6px rgba(16, 185, 129, 0.45);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    -webkit-user-select: none;
    user-select: none;
    touch-action: none;
}
.rd-fb-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 28px -6px rgba(16, 185, 129, 0.55);
}
.rd-fb-btn:active,
.rd-fb-btn.holding {
    transform: scale(0.98);
}
.rd-fb-btn.hint {
    animation: fbShake 0.4s ease;
}
@keyframes fbShake {
    0%,
    100% {
        transform: translateX(0);
    }
    20%,
    60% {
        transform: translateX(-6px);
    }
    40%,
    80% {
        transform: translateX(6px);
    }
}

.rd-fb-fill {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    background: linear-gradient(90deg, var(--hcis-emerald-700), var(--hcis-emerald-800));
    z-index: 1;
    transition: width 0.1s linear;
    pointer-events: none;
}

.rd-fb-btn-label {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.rd-fb-btn-label i {
    font-size: 1.1rem;
    line-height: 1;
}

.rd-fb-tooltip {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-300);
    color: var(--hcis-red-800);
    padding: 0.45rem 0.75rem;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.35;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
}
.rd-fb-tooltip i {
    font-size: 0.9rem;
    color: var(--hcis-red-600);
    flex-shrink: 0;
}

/* Stage scroll clearance when banner is present so document text is never covered.
   Keyed on finish-open (allRead && !readonly && !showSuccess), NOT all-read alone —
   a document that's already SELESAI/DIKONFIRMASI (readonly) or mid celebration
   overlay (showSuccess) never renders the banner, so it must not reserve its
   space either; doing so was silently shrinking fitPage()'s "fit" size. */
.rd.finish-open .rd-stage {
    padding-bottom: 9.5rem;
}

.rd-fb-close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    transition: all 0.16s ease;
    z-index: 5;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}
.rd-fb-close:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-300);
    transform: scale(1.08);
}

.rd-finish-banner.minimized {
    width: auto;
    padding: 0;
    border-radius: 999px;
    box-shadow: 0 12px 32px -6px rgba(16, 185, 129, 0.38);
    background: rgba(255, 255, 255, 0.98);
    left: auto;
    right: 1.5rem;
    transform: none;
    bottom: 4.8rem;
    animation: none;
}
.rd.immersive .rd-finish-banner.minimized {
    bottom: 2rem;
}

.rd-fb-pill-btn {
    border: 1px solid rgba(16, 185, 129, 0.45);
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-green-50) 100%);
    color: var(--hcis-slate-800);
    padding: 0.55rem 1.1rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);
}
.rd-fb-pill-btn:hover {
    background: var(--hcis-white);
    transform: translateY(-2px);
    border-color: var(--hcis-emerald-500);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.28);
}

@media (max-width: 560px) {
    .rd-finish-banner {
        bottom: 5.5rem;
        padding: 0.9rem 1rem;
        gap: 0.65rem;
    }
    .rd.immersive .rd-finish-banner {
        bottom: 1.5rem;
    }
    .rd-finish-banner.minimized {
        right: 1rem;
        bottom: 5.2rem;
    }
    .rd-fb-pill-btn {
        padding: 0.45rem 0.85rem;
        font-size: 0.74rem;
    }
    .rd-fb-icon {
        width: 38px;
        height: 38px;
        font-size: 1.2rem;
    }
    .rd-fb-title {
        font-size: 0.88rem;
    }
    .rd-fb-sub {
        font-size: 0.7rem;
    }
    .rd-fb-btn {
        height: 44px;
        font-size: 0.82rem;
    }
}

/* ══════════════ Metadata Drawer & Spec Cards ══════════════ */
.rd-info-drawer {
    width: min(420px, 92vw);
}
.rd-meta-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.rd-mcard {
    background: var(--hcis-white);
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 16px;
    padding: 1rem 1.1rem;
    transition: all 0.2s ease;
}
.rd-mcard.main {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.06), rgba(139, 92, 246, 0.04));
    border-color: rgba(99, 102, 241, 0.25);
}
.rd-mcard-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 6px 16px -4px rgba(99, 102, 241, 0.4);
}
.rd-mcard-badge {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--hcis-indigo-500);
    margin-bottom: 0.15rem;
}
.rd-mcard-title {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--hcis-slate-900);
    line-height: 1.3;
}
.rd-mcard-sub {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.45rem;
}
.badge-code {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}
.badge-ver {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}

.rd-mgrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}
.rd-mcell {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
}
.rd-mcell-lbl {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.2rem;
}
.rd-mcell-lbl i {
    color: var(--hcis-indigo-500);
}
.rd-mcell-val {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    word-break: break-word;
}

.rd-mcard-head {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin-bottom: 0.65rem;
}
.rd-mstats-row {
    display: flex;
    align-items: center;
    justify-content: space-around;
    background: #fafbff;
    border: 1px solid #eef2f7;
    border-radius: 12px;
    padding: 0.75rem 0.5rem;
    text-align: center;
}
.rd-mstat-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.rd-mstat-item .num {
    font-size: 0.95rem;
    font-weight: 900;
    color: var(--hcis-indigo-600);
    font-variant-numeric: tabular-nums;
}
.rd-mstat-item .lbl {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.rd-mcard.security {
    background: rgba(16, 185, 129, 0.03);
    border-color: rgba(16, 185, 129, 0.22);
}
.rd-msec-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
    line-height: 1.4;
}
.rd-msec-item i {
    color: var(--hcis-emerald-500);
    font-size: 0.95rem;
    margin-top: 2px;
    flex-shrink: 0;
}
.rd-msec-wm {
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(16, 185, 129, 0.25);
}
.rd-msec-wm .wm-lbl {
    font-size: 0.64rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    margin-bottom: 0.2rem;
}
.rd-msec-wm .wm-val {
    font-size: 0.68rem;
    color: var(--hcis-slate-900);
    background: var(--hcis-white);
    border: 1px solid rgba(16, 185, 129, 0.25);
    padding: 0.3rem 0.5rem;
    border-radius: 6px;
    display: block;
    word-break: break-all;
    font-family: monospace;
}
</style>
