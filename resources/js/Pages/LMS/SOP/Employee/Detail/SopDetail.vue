<template>
    <div class="container-fluid px-0 sop-scope" :class="{ 'is-focus-mode': isFocusMode }">
        <!-- Progress baca (scroll indicator bar top) -->
        <div class="rd-scrollbar" :style="{ width: scrollPct + '%' }" aria-hidden="true"></div>

        <!-- Skeleton & Active Loading State -->
        <transition name="sop-fade" mode="out-in">
            <div v-if="loading" key="loading" class="reader-shell px-lg-4 mt-3">
                <!-- Hero Banner Skeleton with Active Loading Spinner Badge -->
                <div
                    class="sop-skel-hero mb-4 p-4 p-md-5 d-flex flex-column justify-content-between position-relative overflow-hidden"
                    style="min-height: 240px; border-radius: 24px"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="sop-skel" style="width: 140px; height: 26px; border-radius: 999px"></div>
                        <div class="sop-skel" style="width: 90px; height: 26px; border-radius: 999px"></div>
                    </div>
                    <div class="my-auto py-3">
                        <div class="sop-skel mb-3" style="width: 70%; height: 36px; border-radius: 12px"></div>
                        <div class="sop-skel" style="width: 45%; height: 20px; border-radius: 8px"></div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3 pt-3 border-top border-white-50">
                        <div
                            class="d-flex align-items-center gap-2 text-primary bg-white px-3 py-1.5 rounded-pill shadow-sm"
                        >
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            <span class="fw-bold small">Memuat Paket Dokumen...</span>
                        </div>
                        <div class="sop-skel ms-auto" style="width: 120px; height: 28px; border-radius: 999px"></div>
                    </div>
                </div>

                <!-- Workspace Layout Skeleton -->
                <div class="sop-workspace split-left">
                    <!-- Left Sidebar (TOC Kokpit) Skeleton -->
                    <div class="sop-skel-card p-3" style="border-radius: 24px">
                        <div class="d-flex align-items-center justify-content-center p-3 mb-3 border-bottom">
                            <div class="sop-skel" style="width: 110px; height: 110px; border-radius: 50%"></div>
                        </div>
                        <div class="sop-skel mb-2" style="height: 40px; border-radius: 12px"></div>
                        <div
                            v-for="n in 4"
                            :key="n"
                            class="sop-skel mb-2"
                            style="height: 48px; border-radius: 12px"
                        ></div>
                    </div>

                    <!-- Right Main Content Skeleton -->
                    <div class="sop-stack flex-grow-1">
                        <div v-for="n in 3" :key="n" class="sop-skel-card p-4 mb-3" style="border-radius: 20px">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="sop-skel" style="width: 36px; height: 36px; border-radius: 10px"></div>
                                <div class="sop-skel flex-grow-1" style="height: 24px; border-radius: 8px"></div>
                            </div>
                            <div class="sop-skel mb-2" style="height: 16px; width: 85%; border-radius: 6px"></div>
                            <div class="sop-skel" style="height: 16px; width: 60%; border-radius: 6px"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="detail" key="detail">
                <div class="reader-shell px-lg-4 mt-3">
                    <div class="sop-workspace split-left">
                        <!-- ============ RAIL: KOKPIT BACA ============
                         Di desktop ini kolom kiri yang lengket. Di layar kecil ELEMEN YANG SAMA
                         dipakai ulang sebagai bottom sheet (lihat .rd-side di CSS) — bukan
                         disalin jadi drawer terpisah, supaya tak ada daftar isi kembar yang
                         harus dijaga selaras, dan tab Legalitas/Progres ikut terjangkau. -->
                        <div class="rd-side" :class="{ open: tocOpen }" @click.self="tocOpen = false">
                            <aside
                                class="sop-panel is-sticky rd-rail sop-fade-up"
                                :role="tocOpen ? 'dialog' : null"
                                :aria-modal="tocOpen ? 'true' : null"
                            >
                                <!-- Kepala sheet (hanya layar kecil) -->
                                <div class="rd-sheet-head d-lg-none">
                                    <span class="rd-sheet-grip" aria-hidden="true"></span>
                                    <button class="rd-sheet-close" aria-label="Tutup panel" @click="tocOpen = false">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- Pengalih tab: segmented control, penanda geser mengikuti indeks -->
                                <div
                                    class="rail-seg"
                                    role="tablist"
                                    aria-label="Panel samping dokumen"
                                    :style="{ '--seg-i': railTabIndex, '--seg-n': railTabs.length }"
                                    @keydown="onRailTabKey"
                                >
                                    <span class="rail-seg-thumb" aria-hidden="true"></span>
                                    <button
                                        v-for="t in railTabs"
                                        :key="t.key"
                                        ref="railTabBtn"
                                        class="rail-seg-btn"
                                        :class="{ on: railTab === t.key }"
                                        role="tab"
                                        :id="'railtab-' + t.key"
                                        :aria-controls="'railpanel-' + t.key"
                                        :aria-selected="railTab === t.key ? 'true' : 'false'"
                                        :tabindex="railTab === t.key ? 0 : -1"
                                        @click="railTab = t.key"
                                    >
                                        <i class="bi" :class="t.icon"></i><span>{{ t.label }}</span>
                                    </button>
                                </div>

                                <transition name="sop-swap" mode="out-in">
                                    <div
                                        :key="railTab"
                                        class="rail-panel"
                                        role="tabpanel"
                                        :id="'railpanel-' + railTab"
                                        :aria-labelledby="'railtab-' + railTab"
                                    >
                                        <!-- TAB 1: DAFTAR ISI -->
                                        <template v-if="railTab === 'toc'">
                                            <div class="rail-card rail-gauge">
                                                <SopRing
                                                    :value="detail.progress.persen"
                                                    :size="120"
                                                    :stroke="10"
                                                    :color="isComplete ? '#10b981' : '#6366f1'"
                                                    :color2="isComplete ? '#34d399' : '#818cf8'"
                                                    label="Progres"
                                                />
                                                <div class="rail-gauge-sub">
                                                    <span class="tnum">{{ detail.progress.item_selesai }}</span> dari
                                                    <span class="tnum">{{ detail.progress.total }}</span> item wajib
                                                </div>
                                                <div v-if="remainingMin" class="rail-pill soft">
                                                    <i class="bi bi-hourglass-split"></i> ~{{ remainingMin }} mnt
                                                    tersisa
                                                </div>
                                            </div>

                                            <div v-if="deadlineChip" class="rd-rail-deadline" :class="deadlineChip.cls">
                                                <i class="bi" :class="deadlineChip.icon"></i>
                                                <div>
                                                    <span class="rd-dl-lbl">Tenggat Penugasan</span>
                                                    <span class="rd-dl-txt fw-bold">{{ deadlineChip.text }}</span>
                                                </div>
                                            </div>

                                            <button
                                                v-if="firstUnfinished && !isComplete"
                                                class="stitch-btn primary block rd-lanjut pulse-glow"
                                                @click="lanjutkan"
                                            >
                                                <i class="bi bi-play-fill fs-5"></i> Lanjutkan Membaca
                                            </button>

                                            <nav class="rd-toc" aria-label="Daftar isi &amp; Minimap Dokumen">
                                                <div class="rd-toc-head">
                                                    <span>Struktur Bagian &amp; Minimap</span>
                                                    <span class="rail-pill soft tnum"
                                                        >{{ visibleSections.length }} bagian</span
                                                    >
                                                </div>
                                                <div
                                                    v-for="(sec, si) in visibleSections"
                                                    :key="sec.key"
                                                    class="rd-toc-sec-group"
                                                >
                                                    <button
                                                        class="rd-toc-item"
                                                        :class="{
                                                            active: activeSection === sec.key,
                                                            done: secDone(sec.key),
                                                        }"
                                                        :aria-current="activeSection === sec.key ? 'true' : null"
                                                        @click="toggleTocSection(sec.key)"
                                                    >
                                                        <span class="rd-toc-no">
                                                            <i v-if="secDone(sec.key)" class="bi bi-check-lg"></i>
                                                            <template v-else>{{ si + 1 }}</template>
                                                        </span>
                                                        <span class="rd-toc-body">
                                                            <span class="rd-toc-name">{{ sec.Nama_Section }}</span>
                                                            <span class="rd-toc-bar"
                                                                ><i :style="{ width: sectionPct(sec.key) + '%' }"></i
                                                            ></span>
                                                        </span>
                                                        <span class="rd-toc-prog tnum"
                                                            >{{ sectionProgress(sec.key).done }}/{{
                                                                sectionProgress(sec.key).total
                                                            }}</span
                                                        >
                                                        <i
                                                            class="bi rd-toc-arrow ms-1"
                                                            :class="
                                                                expandedTocSecs[sec.key] === true
                                                                    ? 'bi-chevron-up'
                                                                    : 'bi-chevron-down'
                                                            "
                                                        ></i>
                                                    </button>

                                                    <!-- Sub-items List (Minimap Accordion) -->
                                                    <transition name="sop-expand">
                                                        <div
                                                            v-if="expandedTocSecs[sec.key] === true"
                                                            class="rd-toc-sublist"
                                                        >
                                                            <button
                                                                v-for="it in itemsOf(sec.key)"
                                                                :key="it.Id_SOP_Paket_Item"
                                                                class="rd-toc-subitem"
                                                                :class="{
                                                                    done: isDone(it),
                                                                    current: isCurrent(it),
                                                                    lock: it.Terkunci,
                                                                }"
                                                                @click="clickTocItem(it)"
                                                            >
                                                                <span class="rd-toc-subico">
                                                                    <i
                                                                        v-if="isDone(it)"
                                                                        class="bi bi-check-circle-fill text-success"
                                                                    ></i>
                                                                    <i
                                                                        v-else-if="it.Terkunci"
                                                                        class="bi bi-lock-fill text-muted"
                                                                    ></i>
                                                                    <i
                                                                        v-else-if="isCurrent(it)"
                                                                        class="bi bi-record-circle-fill text-primary"
                                                                    ></i>
                                                                    <i
                                                                        v-else
                                                                        class="bi"
                                                                        :class="itemIcon(it.Tipe_Item)"
                                                                    ></i>
                                                                </span>
                                                                <span
                                                                    class="rd-toc-subttl text-truncate"
                                                                    :title="it.Judul_Item"
                                                                    >{{ it.Judul_Item }}</span
                                                                >
                                                                <span class="rd-toc-subbadge">{{
                                                                    tipeLabel(it.Tipe_Item)
                                                                }}</span>
                                                            </button>
                                                        </div>
                                                    </transition>
                                                </div>
                                            </nav>
                                        </template>

                                        <!-- TAB 2: LEGALITAS & TATA KELOLA TERPADU -->
                                        <template v-else-if="railTab === 'info'">
                                            <!-- Legal Hero Cockpit Card -->
                                            <div class="legal-hero" :class="legalHeroClass">
                                                <div class="legal-hero-icon">
                                                    <i class="bi" :class="legalHeroIcon"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="legal-eyebrow d-flex align-items-center gap-1">
                                                        <span>Dokumen Terkendali</span>
                                                        <span v-if="legalPackage.versi_paket" class="badge bg-primary text-white fs-xs px-1.5 py-0.5">v{{ legalPackage.versi_paket }}</span>
                                                    </div>
                                                    <div class="legal-title">{{ legalHeroTitle }}</div>
                                                    <div class="legal-sub">{{ legalHeroSub }}</div>
                                                </div>
                                            </div>

                                            <!-- Executive Metrics Grid -->
                                            <div class="legal-grid">
                                                <div class="legal-metric">
                                                    <span>Status Dokumen</span>
                                                    <strong class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-patch-check-fill text-success"></i>
                                                        {{ legalDocumentStatusLabel }}
                                                    </strong>
                                                </div>
                                                <div v-if="legalPrimaryVersion" class="legal-metric">
                                                    <span>{{ legalPrimaryVersion.label }}</span>
                                                    <strong class="tnum text-primary">{{ legalPrimaryVersion.value }}</strong>
                                                </div>
                                                <div class="legal-metric">
                                                    <span>Distribusi</span>
                                                    <strong class="text-truncate">
                                                        <span class="badge" :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-success-subtle text-success border border-success'">
                                                            {{ legalPackage.mode_distribusi === 'TERBATAS' ? 'Terbatas' : 'Publik' }}
                                                        </span>
                                                    </strong>
                                                </div>
                                                <div class="legal-metric">
                                                    <span>Verifikasi Mutu</span>
                                                    <strong>{{ legalCompletenessText }}</strong>
                                                </div>
                                            </div>

                                            <!-- Card 1: Identitas Paket Resmi & Parameter -->
                                            <div class="rail-card legal-package-card">
                                                <div class="rail-cap-row">
                                                    <span class="rail-cap">Identitas Rilis Paket</span>
                                                    <span class="rail-pill ok">
                                                        <i class="bi bi-shield-check"></i>
                                                        Resmi
                                                    </span>
                                                </div>
                                                <dl class="legal-kv">
                                                    <template v-for="row in legalPackageRows" :key="row.label">
                                                        <dt>{{ row.label }}</dt>
                                                        <dd>{{ row.value }}</dd>
                                                    </template>
                                                </dl>
                                            </div>

                                            <!-- Card 2: Pengesahan Melekat Per Dokumen Resmi -->
                                            <div class="rail-card legal-docs-card">
                                                <div class="rail-cap-row">
                                                    <span class="rail-cap">Pengesahan Per Dokumen</span>
                                                    <span class="rail-pill ok">{{ dokumenPengesahanList.length }} Dokumen</span>
                                                </div>

                                                <!-- Per-Document Endorsement Stack -->
                                                <div class="legal-doc-endorsements-deck d-flex flex-column gap-2.5 mt-2">
                                                    <div
                                                        v-for="doc in (expandedOtoritas ? dokumenPengesahanList : dokumenPengesahanList.slice(0, 3))"
                                                        :key="doc.id"
                                                        class="legal-doc-endorsement-card p-2.5 rounded-3 border bg-white"
                                                    >
                                                        <div class="d-flex align-items-start gap-2 mb-1.5 pb-1 border-bottom">
                                                            <ItemTypeIcon :type="doc.tipe" class="flex-shrink-0 mt-0.5" />
                                                            <div class="min-w-0 flex-grow-1">
                                                                <div class="d-flex align-items-center justify-content-between gap-1">
                                                                    <strong class="text-dark small text-truncate" :title="doc.judul">{{ doc.judul }}</strong>
                                                                    <span class="badge bg-light text-primary border fs-xs">v{{ doc.versi }}</span>
                                                                </div>
                                                                <div v-if="doc.nomorSop" class="fs-xs text-muted">
                                                                    No. {{ doc.nomorSop }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Signer / Approver Details for this Document -->
                                                        <div v-if="doc.disahkanOleh" class="d-flex align-items-start gap-2 fs-xs">
                                                            <div class="legal-seal-chip emerald">
                                                                <i class="bi bi-patch-check-fill"></i>
                                                            </div>
                                                            <div class="min-w-0 flex-grow-1">
                                                                <div class="d-flex align-items-center justify-content-between gap-1">
                                                                    <span class="fw-bold text-success">Disahkan Oleh</span>
                                                                    <span v-if="doc.tanggalPengesahan" class="text-muted">{{ doc.tanggalPengesahan }}</span>
                                                                </div>
                                                                <div class="text-dark font-weight-600 text-truncate" :title="doc.disahkanOleh">{{ doc.disahkanOleh }}</div>
                                                                <div class="text-muted text-truncate" :title="doc.jabatanPengesah">{{ doc.jabatanPengesah }}</div>
                                                                <div v-if="doc.nomorSK" class="rail-mono mt-0.5">
                                                                    <i class="bi bi-hash"></i>SK: {{ doc.nomorSK }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Publisher for this Document if no explicit individual approver -->
                                                        <div v-else-if="doc.publisher" class="d-flex align-items-start gap-2 fs-xs">
                                                            <div class="legal-seal-chip indigo">
                                                                <i class="bi bi-person-badge-fill"></i>
                                                            </div>
                                                            <div class="min-w-0 flex-grow-1">
                                                                <span class="fw-bold text-primary">Diterbitkan Oleh</span>
                                                                <div class="text-dark font-weight-600 text-truncate">{{ doc.publisher }}</div>
                                                                <div class="text-muted fs-xs">Menginduk Standar Mutu Paket</div>
                                                            </div>
                                                        </div>

                                                        <!-- Fallback -->
                                                        <div v-else class="fs-xs text-muted d-flex align-items-center gap-1">
                                                            <i class="bi bi-shield-check text-success"></i>
                                                            <span>Tervalidasi di bawah SK Paket Induk</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Toggle Show More / Show Less for Documents -->
                                                <button
                                                    v-if="dokumenPengesahanList.length > 3"
                                                    type="button"
                                                    class="btn btn-sm btn-link text-decoration-none p-0 mt-2 fs-xs d-flex align-items-center gap-1 text-primary fw-bold"
                                                    @click="expandedOtoritas = !expandedOtoritas"
                                                >
                                                    <span>{{ expandedOtoritas ? 'Sembunyikan' : `Lihat Seluruh (${dokumenPengesahanList.length} Dokumen)` }}</span>
                                                    <i class="bi" :class="expandedOtoritas ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                                </button>
                                            </div>

                                            <!-- Card 3: Matriks Item & Versi Aktif -->
                                            <div class="rail-card legal-doc-table">
                                                <div class="rail-cap-row">
                                                    <span class="rail-cap">Silabus &amp; Versi Item</span>
                                                    <span class="rail-pill soft">{{ legalItems.length }} item</span>
                                                </div>
                                                <div class="legal-item-list">
                                                    <div
                                                        v-for="doc in legalItems"
                                                        :key="doc.Judul_Item + doc.Tipe_Item"
                                                        class="legal-item-row"
                                                    >
                                                        <i class="bi" :class="itemIcon(doc.Tipe_Item)"></i>
                                                        <div class="min-w-0">
                                                            <strong>{{ doc.Judul_Item }}</strong>
                                                            <small class="d-flex align-items-center gap-1 flex-wrap mt-0.5 text-muted">
                                                                <span>{{ tipeLabel(doc.Tipe_Item) }}</span>
                                                                <span class="badge bg-light text-primary border">v{{ $vnum(doc.Versi) }}</span>
                                                                <span v-if="doc.Wajib" class="text-danger">· wajib</span>
                                                            </small>
                                                        </div>
                                                        <span
                                                            class="legal-item-status"
                                                            :class="legalItemStatusClass(doc)"
                                                        >
                                                            {{ legalItemStatusLabel(doc) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card 4: Bukti Kepatuhan & Log Audit -->
                                            <div class="rail-card legal-proof">
                                                <div class="rail-cap-row">
                                                    <span class="rail-cap">Bukti Aktivitas Anda</span>
                                                    <span class="rail-pill" :class="isComplete ? 'ok' : 'soft'">
                                                        <i
                                                            class="bi"
                                                            :class="
                                                                isComplete
                                                                    ? 'bi-patch-check-fill'
                                                                    : 'bi-hourglass-split'
                                                            "
                                                        ></i>
                                                        {{ isComplete ? 'Tercatat' : 'Berjalan' }}
                                                    </span>
                                                </div>
                                                <dl class="legal-kv">
                                                    <template v-for="row in legalProofRows" :key="row.label">
                                                        <dt>{{ row.label }}</dt>
                                                        <dd>{{ row.value }}</dd>
                                                    </template>
                                                </dl>
                                            </div>

                                            <!-- Card 5: Catatan Pembaruan -->
                                            <div v-if="legalUpdates.length" class="rail-card">
                                                <div class="rail-cap-row">
                                                    <span class="rail-cap">Update Versi Baru</span>
                                                    <span class="rail-pill soft"
                                                        >{{ legalUpdates.length }} perubahan</span
                                                    >
                                                </div>
                                                <div class="legal-update-list">
                                                    <div
                                                        v-for="up in legalUpdates.slice(0, 4)"
                                                        :key="up.Judul_Item + up.Versi"
                                                        class="legal-update"
                                                    >
                                                        <i class="bi bi-arrow-repeat"></i>
                                                        <div class="min-w-0">
                                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                                <strong>{{ up.Judul_Item }}</strong>
                                                                <span class="badge bg-light text-primary border">v{{ $vnum(up.Versi) }}</span>
                                                            </div>
                                                            <small class="text-muted d-block mt-0.5">{{
                                                                up.Ringkasan_Perubahan || 'Riwayat versi resmi telah diperbarui.'
                                                            }}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card 6: Timeline Legalitas & Rilis -->
                                            <div v-if="legalTimeline.length" class="rail-card legal-timeline-card">
                                                <div class="rail-cap">Jejak Legalitas &amp; Rilis</div>
                                                <ol class="legal-timeline">
                                                    <li
                                                        v-for="ev in legalTimeline"
                                                        :key="ev.tanggal + ev.judul"
                                                        :class="ev.tone"
                                                    >
                                                        <span class="legal-dot"
                                                            ><i class="bi" :class="ev.icon"></i
                                                        ></span>
                                                        <div>
                                                            <time>{{ fmtDateTime(ev.tanggal) }}</time>
                                                            <strong>{{ ev.judul }}</strong>
                                                            <small>{{ ev.deskripsi }}</small>
                                                        </div>
                                                    </li>
                                                </ol>
                                            </div>

                                            <!-- Quick Trigger Button for Full Governance Modal -->
                                            <div class="mt-3">
                                                <HcisButton
                                                    label="Buka Matriks &amp; Tata Kelola"
                                                    variant="secondary"
                                                    size="sm"
                                                    leading-icon="shield-check"
                                                    block
                                                    @click="showPaketInfoModal = true"
                                                />
                                            </div>
                                        </template>

                                        <!-- TAB 3: PROGRES -->
                                        <template v-else>
                                            <div class="rail-card rail-stat">
                                                <div class="rail-cap">Kepatuhan kamu</div>
                                                <div class="rail-stat-big tnum">
                                                    {{ detail.progress.persen }}<span>%</span>
                                                </div>
                                                <div class="rail-sub">
                                                    {{ detail.progress.item_selesai }} dari
                                                    {{ detail.progress.total }} langkah wajib selesai
                                                </div>

                                                <!-- sebaran status seluruh item, bukan cuma yang wajib -->
                                                <div class="rail-split" role="img" :aria-label="breakdownLabel">
                                                    <i
                                                        class="s-ok"
                                                        :style="{ width: statusBreakdown.pctSelesai + '%' }"
                                                    ></i>
                                                    <i
                                                        class="s-go"
                                                        :style="{ width: statusBreakdown.pctBerjalan + '%' }"
                                                    ></i>
                                                    <i
                                                        class="s-no"
                                                        :style="{ width: statusBreakdown.pctBelum + '%' }"
                                                    ></i>
                                                </div>
                                                <ul class="rail-legend">
                                                    <li>
                                                        <span class="dot ok"></span> Selesai
                                                        <b class="tnum">{{ statusBreakdown.selesai }}</b>
                                                    </li>
                                                    <li>
                                                        <span class="dot go"></span> Sedang dibuka
                                                        <b class="tnum">{{ statusBreakdown.berjalan }}</b>
                                                    </li>
                                                    <li>
                                                        <span class="dot no"></span> Belum dibuka
                                                        <b class="tnum">{{ statusBreakdown.belum }}</b>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="rail-kv">
                                                <div>
                                                    <span><i class="bi bi-list-check"></i> Total item</span
                                                    ><b class="tnum">{{ counts.all }}</b>
                                                </div>
                                                <div>
                                                    <span><i class="bi bi-asterisk"></i> Langkah wajib</span
                                                    ><b class="tnum">{{ counts.wajib }}</b>
                                                </div>
                                                <div>
                                                    <span><i class="bi bi-clock"></i> Estimasi baca</span
                                                    ><b>~{{ detail.paket.Estimasi_Waktu_Baca_Menit || 10 }} mnt</b>
                                                </div>
                                                <div v-if="remainingMin">
                                                    <span><i class="bi bi-hourglass-split"></i> Perkiraan sisa</span
                                                    ><b>~{{ remainingMin }} mnt</b>
                                                </div>
                                            </div>

                                            <button
                                                v-if="firstUnfinished && !isComplete"
                                                class="stitch-btn secondary block"
                                                @click="
                                                    railTab = 'toc';
                                                    lanjutkan();
                                                "
                                            >
                                                <i class="bi bi-play-fill"></i> Lanjutkan Membaca
                                            </button>
                                        </template>
                                    </div>
                                </transition>
                            </aside>
                        </div>

                        <!-- ============ MAIN: DOKUMEN & STEP JOURNEY ============ -->
                        <main class="rd-main sop-fade-up">
                            <!-- Ultra-Premium Glassmorphism Hero Banner -->
                            <div class="rd-hero" :style="heroStyle">
                                <img
                                    v-if="detail.paket.Cover_Url"
                                    :src="detail.paket.Cover_Url"
                                    class="rd-hero-img rd-kenburns"
                                    :style="{ objectPosition: detail.paket.Cover_Position || 'center' }"
                                    alt=""
                                />
                                <div class="rd-hero-mesh"></div>
                                <div class="rd-hero-shade"></div>

                                <!-- Hero Integrated Top Navigation & Quick Actions -->
                                <div
                                    class="rd-hero-top d-flex align-items-center justify-content-between p-3 position-relative"
                                    style="z-index: 3"
                                >
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <a :href="backUrl" class="rd-hero-btn" title="Kembali">
                                            <i class="bi bi-arrow-left"></i>
                                            <span>Kembali</span>
                                        </a>
                                        <nav
                                            class="rd-hero-bc d-none d-md-flex align-items-center gap-2 text-truncate ms-1"
                                        >
                                            <span>{{ detail.penugasan ? 'Functional Document' : 'Public Document' }}</span>
                                            <i class="bi bi-chevron-right small opacity-75"></i>
                                            <span class="fw-bold text-truncate">{{ detail.paket.Nama_Paket }}</span>
                                        </nav>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <!-- Tombol Tata Kelola & Versi Paket -->
                                        <button
                                            class="rd-hero-btn rd-hero-btn--highlight"
                                            @click="showPaketInfoModal = true"
                                            title="Lihat Identitas, Tata Kelola & Riwayat Versi Paket"
                                        >
                                            <i class="bi bi-shield-check"></i>
                                            <span>Tata Kelola &amp; Versi</span>
                                        </button>
                                        <button
                                            class="rd-hero-btn"
                                            :class="{ active: isFocusMode }"
                                            @click="isFocusMode = !isFocusMode"
                                            title="Toggle Mode Fokus Membaca"
                                        >
                                            <i
                                                class="bi"
                                                :class="
                                                    isFocusMode ? 'bi-arrows-angle-contract' : 'bi-arrows-angle-expand'
                                                "
                                            ></i>
                                            <span>{{ isFocusMode ? 'Keluar Fokus' : 'Mode Fokus' }}</span>
                                        </button>
                                        <button
                                            class="rd-hero-btn d-none d-sm-inline-flex"
                                            @click="shareSop"
                                            title="Salin Tautan Dokumen"
                                        >
                                            <i class="bi bi-share-fill"></i>
                                            <span>Bagikan</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="rd-hero-body">
                                    <div class="rd-hero-eyebrow d-flex align-items-center gap-2 flex-wrap mb-2">
                                        <span v-if="detail.paket.Kode_Paket" class="rd-kode">
                                            <i class="bi bi-hash me-1"></i>{{ detail.paket.Kode_Paket }}
                                        </span>
                                        <span v-if="legalPackage.versi_paket" class="rd-ver-pill">
                                            <i class="bi bi-layers-fill me-1"></i>Paket Rilis v{{ legalPackage.versi_paket }}
                                        </span>
                                        <span class="rd-dist-pill" :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'limited' : 'public'">
                                            <i :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'bi bi-shield-lock-fill me-1' : 'bi bi-globe2 me-1'"></i>
                                            {{ legalPackage.mode_distribusi === 'TERBATAS' ? 'Akses Terbatas' : 'Akses Publik' }}
                                        </span>
                                    </div>

                                    <h1 class="rd-title">{{ detail.paket.Nama_Paket }}</h1>

                                    <div class="rd-chips">
                                        <span
                                            class="rd-chip"
                                            :style="
                                                prio.warna
                                                    ? {
                                                          color: prio.warna,
                                                          borderColor: prio.warna + '4d',
                                                          background: prio.warna + '1f',
                                                      }
                                                    : null
                                            "
                                        >
                                            <i class="bi bi-flag-fill"></i> Prioritas {{ prio.label }}
                                        </span>
                                        <span v-if="detail.paket.Estimasi_Waktu_Baca_Menit" class="rd-chip">
                                            <i class="bi bi-clock-history"></i> ~{{
                                                detail.paket.Estimasi_Waktu_Baca_Menit
                                            }} Menit Baca
                                        </span>
                                        <span v-if="detail.paket.Mode_Urutan === 'SEKUENSIAL'" class="rd-chip">
                                            <i class="bi bi-signpost-2-fill"></i> Urutan Sekuensial
                                        </span>
                                        <span v-if="isControlled" class="rd-chip rd-chip-seal">
                                            <i class="bi bi-patch-check-fill"></i> Dokumen Terkendali &amp; Sah
                                        </span>
                                        <span v-if="komposisiRingkas" class="rd-chip">
                                            <i class="bi bi-collection-fill"></i> {{ komposisiRingkas }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Glassmorphism Spec Bar -->
                                <div class="rd-spec">
                                    <div v-if="detail.paket.Tanggal_Publish" class="rd-spec-cell">
                                        <span class="rd-spec-lbl"
                                            ><i class="bi bi-calendar-check-fill"></i> Tanggal Terbit</span
                                        >
                                        <span class="rd-spec-val">{{ fmtDate(detail.paket.Tanggal_Publish) }}</span>
                                    </div>
                                    <div v-if="detail.paket.Updated_At" class="rd-spec-cell">
                                        <span class="rd-spec-lbl"
                                            ><i class="bi bi-arrow-repeat"></i> Terakhir Diperbarui</span
                                        >
                                        <span class="rd-spec-val">{{ fmtDate(detail.paket.Updated_At) }}</span>
                                    </div>
                                    <div v-if="legalPublisherLabel || ownerLabel" class="rd-spec-cell">
                                        <span class="rd-spec-lbl"
                                            ><i class="bi bi-person-badge-fill"></i> Otoritas / PIC</span
                                        >
                                        <span class="rd-spec-val text-truncate">{{ legalPublisherLabel || ownerLabel }}</span>
                                    </div>
                                    <div class="rd-spec-cell">
                                        <span class="rd-spec-lbl"><i class="bi bi-list-check"></i> Total Silabus</span>
                                        <span class="rd-spec-val">{{ counts.all }} Langkah ({{ detail.progress.total }} Wajib)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Banner Penugasan Karyawan -->
                            <div
                                v-if="detail.penugasan"
                                class="rd-assign"
                                :class="{ late: detail.penugasan.Is_Terlambat }"
                            >
                                <div class="rd-assign-main">
                                    <div class="rd-assign-icon">
                                        <i
                                            class="bi"
                                            :class="
                                                detail.penugasan.Is_Terlambat
                                                    ? 'bi-exclamation-octagon-fill'
                                                    : 'bi-calendar2-week-fill'
                                            "
                                        ></i>
                                    </div>
                                    <div>
                                        <div class="rd-assign-title">{{ assignTitle }}</div>
                                        <div class="rd-assign-sub">
                                            <span v-if="detail.penugasan.Tanggal_Assign"
                                                >Ditugaskan {{ fmtDate(detail.penugasan.Tanggal_Assign) }}</span
                                            >
                                            <span v-if="detail.penugasan.Tanggal_Selesai">
                                                · Selesai {{ fmtDateTime(detail.penugasan.Tanggal_Selesai) }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <StatusPill :text="statusPaketMeta.label" :variant="statusPaketMeta.variant" />
                            </div>

                            <!-- Interactive Version Changelog & Revision Matrix Accordion Banner -->
                            <div v-if="hasVersionUpdates" class="rd-version-banner mb-4">
                                <div class="rd-ver-banner-head d-flex align-items-center justify-content-between p-3.5">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <div class="rd-ver-banner-icon">
                                            <i class="bi bi-stars"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="rd-ver-banner-title d-flex align-items-center gap-2 flex-wrap">
                                                <span>Pembaruan Versi Dokumen Terkendali</span>
                                                <span v-if="legalUpdates.length" class="rd-ver-counter-badge">
                                                    <i class="bi bi-patch-check-fill me-1"></i>{{ legalUpdates.length }} Dokumen Baru
                                                </span>
                                            </div>
                                            <div class="rd-ver-banner-desc text-truncate">
                                                {{ detail.banner_perubahan || 'Terdapat dokumen dalam paket ini yang telah diperbarui ke versi rilis terbaru.' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
                                        <HcisButton
                                            label="Tata Kelola &amp; Versi"
                                            variant="secondary"
                                            size="sm"
                                            leading-icon="journal-text"
                                            @click="showPaketInfoModal = true"
                                        />
                                        <button
                                            type="button"
                                            class="rd-ver-banner-toggle"
                                            :class="{ open: openChangelogBanner }"
                                            @click="openChangelogBanner = !openChangelogBanner"
                                            :title="openChangelogBanner ? 'Tutup rincian' : 'Buka rincian perubahan'"
                                        >
                                            <i class="bi" :class="openChangelogBanner ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Collapsible Changelog List -->
                                <transition name="sop-expand">
                                    <div v-if="openChangelogBanner && (legalUpdates.length || itemsWithChangelog.length)" class="rd-ver-banner-body px-3.5 pb-3.5 pt-0">
                                        <div class="rd-ver-change-grid">
                                            <div
                                                v-for="up in (legalUpdates.length ? legalUpdates : itemsWithChangelog)"
                                                :key="up.Judul_Item + (up.Versi || '')"
                                                class="rd-ver-change-item"
                                            >
                                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1.5">
                                                    <strong class="text-dark small text-truncate" :title="up.Judul_Item">
                                                        <i class="bi bi-file-earmark-text-fill text-primary me-1"></i>{{ up.Judul_Item }}
                                                    </strong>
                                                    <span class="rd-ver-tag-pill">
                                                        v{{ up.Versi != null ? $vnum(up.Versi) : 1 }}
                                                    </span>
                                                </div>
                                                <p class="small text-muted mb-2">
                                                    {{ up.Ringkasan_Perubahan || 'Pembaruan prosedur, parameter operasional, dan pengesahan dokumen terbaru.' }}
                                                </p>
                                                <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2 fs-xs text-muted flex-wrap">
                                                    <span v-if="up.Tanggal_Berlaku" class="d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-calendar-check text-success"></i> Berlaku: {{ fmtDate(up.Tanggal_Berlaku) }}
                                                    </span>
                                                    <span v-if="up.Publisher_Nama" class="d-inline-flex align-items-center gap-1 ms-auto text-truncate" :title="up.Publisher_Nama">
                                                        <i class="bi bi-person-badge text-primary"></i> {{ up.Publisher_Nama }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>

                            <!-- Kartu BUKTI presensi. Gerbangnya sendiri sudah lewat:
                             halaman ini mustahil terbuka sebelum check-in, karena
                             server merender SopCheckin alih-alih halaman ini. Jadi
                             yang ditampilkan di sini murni catatan, bukan gerbang. -->
                            <SopAbsensiGate v-if="detail.paket.Flag_Absensi === 'Y'" :id-paket="idPaket" />

                            <!-- Section: Tentang Dokumen (Collapsible Accordion) -->
                            <div v-if="hasAbout" class="rd-about">
                                <button class="rd-about-toggle" @click="aboutOpen = !aboutOpen">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-info-circle-fill text-primary"></i>
                                        <span>Deskripsi &amp; Ruang Lingkup Dokumen</span>
                                    </div>
                                    <i class="bi" :class="aboutOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                </button>
                                <transition name="sop-swap">
                                    <div v-if="aboutOpen" class="rd-about-body">
                                        <p v-if="detail.paket.Deskripsi" class="rd-desc">
                                            {{ detail.paket.Deskripsi }}
                                        </p>
                                        <div v-if="detail.paket.Tujuan_Ruang_Lingkup" class="rd-scope">
                                            <div class="rd-scope-lbl">
                                                <i class="bi bi-bullseye me-1"></i> Tujuan &amp; Ruang Lingkup
                                                Operasional
                                            </div>
                                            <p>{{ detail.paket.Tujuan_Ruang_Lingkup }}</p>
                                        </div>
                                    </div>
                                </transition>
                            </div>

                            <!-- Quick Saring Item (Filter Tabs Bar) -->
                            <div v-if="counts.all > 3" class="rd-filter" role="tablist" aria-label="Saring item dokumen">
                                <button
                                    v-for="f in filters"
                                    :key="f.key"
                                    class="rd-fchip"
                                    :class="{ on: filter === f.key }"
                                    role="tab"
                                    :aria-selected="filter === f.key ? 'true' : 'false'"
                                    @click="filter = f.key"
                                >
                                    <i class="bi" :class="f.icon"></i> {{ f.label }}
                                    <span class="rd-fcount tnum">{{ f.count }}</span>
                                </button>
                                <span v-if="filter !== 'ALL' || searchQuery" class="rd-fnote ms-auto fs-xs text-muted"
                                    >Menampilkan {{ shownCount }} dari {{ counts.all }} item</span
                                >
                            </div>

                            <!-- Reading Journey Spine Timeline: Section → Step Item Card -->
                            <div class="rd-flow">
                                <div v-if="!shownCount" class="rd-flow-empty">
                                    <i class="bi bi-search fs-3 text-muted"></i>
                                    <div>
                                        <div class="fw-bold">Tidak Ada Item yang Cocok</div>
                                        <div class="small text-muted">
                                            Coba ubah kata kunci pencarian atau kriteria saringan.
                                        </div>
                                    </div>
                                    <button
                                        class="stitch-btn secondary sm ms-auto"
                                        @click="
                                            filter = 'ALL';
                                            searchQuery = '';
                                        "
                                    >
                                        Reset Saringan
                                    </button>
                                </div>

                                <template v-for="(sec, si) in visibleSections" :key="sec.key">
                                    <!-- Section Header Container & Interactive Card -->
                                    <div :id="'sec-' + sec.key" class="flow-sec">
                                        <div class="flow-node sec" :class="{ done: secDone(sec.key) }">
                                            <i v-if="secDone(sec.key)" class="bi bi-check-lg"></i>
                                            <template v-else>{{ si + 1 }}</template>
                                        </div>

                                        <div
                                            class="flow-sec-card d-flex align-items-center justify-content-between"
                                            @click="toggleSectionCollapse(sec.key)"
                                        >
                                            <div class="flow-sec-body min-w-0 me-2">
                                                <div class="rd-sec-name">{{ sec.Nama_Section }}</div>
                                                <div v-if="sec.Deskripsi" class="rd-sec-desc">{{ sec.Deskripsi }}</div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                <span class="rd-sec-prog tnum"
                                                    >{{ sectionProgress(sec.key).done }}/{{
                                                        sectionProgress(sec.key).total
                                                    }}
                                                    Wajib</span
                                                >
                                                <span
                                                    class="flow-sec-chevron"
                                                    :class="{ open: !isSectionCollapsed(sec.key) }"
                                                >
                                                    <i class="bi bi-chevron-down"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Collapsible Section Body -->
                                    <transition name="sop-expand">
                                        <div v-if="!isSectionCollapsed(sec.key)" class="flow-sec-content">
                                            <!-- Step Item Cards -->
                                            <div
                                                v-for="it in itemsOf(sec.key)"
                                                :key="it.Id_SOP_Paket_Item"
                                                :id="'item-' + it.Id_SOP_Paket_Item"
                                                class="flow-item"
                                                :class="{
                                                    done: isDone(it),
                                                    locked: it.Terkunci,
                                                    current: isCurrent(it),
                                                }"
                                            >
                                                <!-- Node Indicator Icon -->
                                                <div
                                                    class="flow-node"
                                                    :class="{
                                                        done: isDone(it),
                                                        current: isCurrent(it),
                                                        lock: it.Terkunci,
                                                    }"
                                                >
                                                    <i v-if="isDone(it)" class="bi bi-check-lg"></i>
                                                    <i v-else-if="it.Terkunci" class="bi bi-lock-fill"></i>
                                                    <i v-else-if="isCurrent(it)" class="bi bi-record-circle-fill"></i>
                                                    <i v-else class="bi" :class="itemIcon(it.Tipe_Item)"></i>
                                                </div>

                                                <!-- Main Item Card with Type Border Accent -->
                                                <article
                                                    class="ritem"
                                                    :class="[
                                                        'type-accent-' + it.Tipe_Item,
                                                        {
                                                            done: isDone(it),
                                                            locked: it.Terkunci,
                                                            highlight: it.Flag_Highlight === 'Y',
                                                            current: isCurrent(it),
                                                        },
                                                    ]"
                                                >
                                                    <!-- Top Ribbon Indicators -->
                                                    <span v-if="it.Flag_Highlight === 'Y'" class="ritem-ribbon">
                                                        <i class="bi bi-star-fill me-1"></i> Penting
                                                    </span>
                                                    <!-- Satu slot lencana, dua cerita berbeda. Dokumen yang
                                                 SUDAH pernah dituntaskan lalu direvisi bukan "langkah
                                                 berikutnya" — pembacanya tak sedang melanjutkan, ia
                                                 diminta membaca ulang sesuatu yang ia kira selesai.
                                                 Menyebutnya "Lanjut Dari Sini" menyembunyikan justru
                                                 satu-satunya hal yang perlu ia tahu. -->
                                                    <span
                                                        v-if="it.Perlu_Baca_Ulang && !it.Terkunci"
                                                        class="ritem-now ulang"
                                                    >
                                                        <i class="bi bi-arrow-repeat me-1"></i> Diperbarui — Baca Ulang
                                                    </span>
                                                    <span
                                                        v-else-if="isCurrent(it) && !isDone(it) && !it.Terkunci"
                                                        class="ritem-now"
                                                    >
                                                        <i class="bi bi-cursor-fill me-1"></i> Lanjut Dari Sini
                                                    </span>

                                                    <div class="ri-head flex-column gap-2">
                                                        <div
                                                            class="d-flex align-items-center justify-content-between w-100 gap-2"
                                                        >
                                                            <div
                                                                class="d-flex align-items-center gap-2 min-w-0 flex-wrap"
                                                            >
                                                                <ItemTypeIcon
                                                                    :type="it.Tipe_Item"
                                                                    class="flex-shrink-0"
                                                                />
                                                                <div
                                                                    class="ri-meta d-flex flex-wrap align-items-center gap-2 min-w-0"
                                                                >
                                                                    <span class="ri-type-badge">{{
                                                                        tipeLabel(it.Tipe_Item)
                                                                    }}</span>
                                                                    <span v-if="it.Wajib" class="ri-wajib"
                                                                        ><i class="bi bi-asterisk"></i> Wajib</span
                                                                    >
                                                                    <span
                                                                        v-if="it.Versi !== null && it.Versi !== undefined"
                                                                        class="ri-ver-tag"
                                                                        :class="it.Keberlakuan ? it.Keberlakuan.status.toLowerCase() : 'berlaku'"
                                                                        :title="it.Keberlakuan ? it.Keberlakuan.alasan : 'Versi resmi'"
                                                                    >
                                                                        <i class="bi bi-layers-fill me-1"></i>v{{ $vnum(it.Versi) }}
                                                                    </span>
                                                                    <span
                                                                        v-if="it.Nomor_SOP_Resmi"
                                                                        class="ri-nomor small"
                                                                        title="Nomor Kontrol Dokumen Resmi"
                                                                    >
                                                                        <i class="bi bi-file-earmark-ruled me-1"></i>{{ it.Nomor_SOP_Resmi }}
                                                                    </span>
                                                                    <span
                                                                        v-if="it.Disahkan_Oleh || it.Nomor_Pengesahan"
                                                                        class="ri-seal-pill"
                                                                        :title="`Disahkan oleh: ${it.Disahkan_Oleh || 'Manajemen Operasional'}${it.Nomor_Pengesahan ? ' (SK: ' + it.Nomor_Pengesahan + ')' : ''}`"
                                                                    >
                                                                        <i class="bi bi-patch-check-fill me-1"></i>Tersertifikasi
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <StatusPill
                                                                :text="statusMeta(it.Status).label"
                                                                :variant="statusMeta(it.Status).variant"
                                                                :dot="false"
                                                                class="flex-shrink-0 ms-auto"
                                                            />
                                                        </div>
                                                        <div class="ri-title">{{ it.Judul_Item }}</div>
                                                    </div>

                                                    <p v-if="it.Deskripsi_Item" class="ri-desc mt-2 mb-0">
                                                        {{ it.Deskripsi_Item }}
                                                    </p>

                                                    <!-- Apa yang berubah, di tempat keputusannya diambil.
                                                 Meminta orang membaca ulang tanpa menyebut bagian mana
                                                 yang berubah membuat sebagian besar hanya menekan
                                                 "Konfirmasi" sekali lagi. -->
                                                    <p
                                                        v-if="it.Perlu_Baca_Ulang && !it.Terkunci"
                                                        class="ri-ulang mt-2 mb-0"
                                                    >
                                                        <i class="bi bi-info-circle-fill me-1"></i>
                                                        <template v-if="it.Ringkasan_Perubahan">
                                                            Yang berubah pada versi {{ $vnum(it.Versi) }}:
                                                            {{ it.Ringkasan_Perubahan }}
                                                        </template>
                                                        <template v-else>
                                                            Sudah pernah Anda tuntaskan, lalu terbit versi
                                                            {{ $vnum(it.Versi) }}. Konfirmasi Anda diminta lagi untuk
                                                            versi ini.
                                                        </template>
                                                    </p>

                                                    <!-- Locked Warning state -->
                                                    <div
                                                        v-if="it.Terkunci"
                                                        class="ri-lock mt-3 p-2 rounded-3 bg-light text-muted border border-dashed"
                                                    >
                                                        <i class="bi bi-lock-fill text-warning me-1"></i> Selesaikan
                                                        item wajib sebelumnya untuk membuka langkah ini.
                                                    </div>

                                                    <template v-else>
                                                        <!-- Item Type: PARAGRAF (Rich Text displayed directly in full) -->
                                                        <div v-if="it.Tipe_Item === 'PARAGRAF'" class="mt-3">
                                                            <div
                                                                class="ri-prose shadow-sm"
                                                                :class="'font-scale-' + fontSizeLevel"
                                                                v-html="paragrafHtml(it.Konten_Teks)"
                                                            ></div>
                                                            <div
                                                                class="ri-actions mt-2.5 d-flex align-items-center flex-wrap gap-2"
                                                            >
                                                                <button
                                                                    v-if="!isDone(it)"
                                                                    class="stitch-btn primary sm"
                                                                    :disabled="confirmingItem === it.Id_SOP_Paket_Item"
                                                                    @click="markSelesai(it)"
                                                                >
                                                                    <i
                                                                        class="bi me-1"
                                                                        :class="
                                                                            confirmingItem === it.Id_SOP_Paket_Item
                                                                                ? 'bi-arrow-repeat ri-spin'
                                                                                : 'bi-check2-circle'
                                                                        "
                                                                    ></i>
                                                                    {{
                                                                        confirmingItem === it.Id_SOP_Paket_Item
                                                                            ? 'Menyimpan…'
                                                                            : 'Tandai Selesai & Pahami'
                                                                    }}
                                                                </button>
                                                                <template v-else>
                                                                    <span class="ri-baca-ok">
                                                                        <i
                                                                            class="bi bi-check-circle-fill text-success me-1"
                                                                        ></i>
                                                                        Tuntas Dipahami
                                                                    </span>
                                                                    <span
                                                                        v-if="it.Waktu_Konfirmasi"
                                                                        class="ri-track-pill ok"
                                                                    >
                                                                        <i class="bi bi-check-circle-fill me-1"></i>
                                                                        Dikonfirmasi
                                                                        {{ fmtDateTime(it.Waktu_Konfirmasi) }}
                                                                    </span>
                                                                    <span
                                                                        v-if="it.Durasi_Detik"
                                                                        class="ri-track-pill idle"
                                                                    >
                                                                        <i class="bi bi-stopwatch-fill me-1"></i>
                                                                        {{ fmtDurasi(it.Durasi_Detik) }}
                                                                    </span>
                                                                </template>
                                                            </div>
                                                        </div>

                                                        <!-- Item Type: TAUTAN (Interactive External Link Card) -->
                                                        <div
                                                            v-else-if="it.Tipe_Item === 'TAUTAN'"
                                                            class="ri-link-card mt-3"
                                                        >
                                                            <div class="ri-link-card-inner">
                                                                <div class="ri-link-main flex-grow-1 min-w-0">
                                                                    <div class="ri-link-ico">
                                                                        <i class="bi bi-globe2"></i>
                                                                    </div>
                                                                    <div class="ri-link-meta min-w-0 flex-grow-1">
                                                                        <div
                                                                            class="d-flex align-items-center gap-2 flex-wrap"
                                                                        >
                                                                            <span class="ri-link-domain">
                                                                                <i
                                                                                    class="bi bi-box-arrow-up-right me-1"
                                                                                ></i>
                                                                                TAUTAN RESMI
                                                                            </span>
                                                                            <span v-if="isDone(it)" class="ri-baca-ok">
                                                                                <i
                                                                                    class="bi bi-check-circle-fill text-success me-1"
                                                                                ></i>
                                                                                Telah Dikunjungi
                                                                            </span>
                                                                        </div>
                                                                        <div
                                                                            class="ri-link-label text-truncate mt-1"
                                                                            :title="
                                                                                it.Label_Tautan || 'Tautan Eksternal'
                                                                            "
                                                                        >
                                                                            {{
                                                                                it.Label_Tautan ||
                                                                                'Tautan Eksternal Dokumen'
                                                                            }}
                                                                        </div>
                                                                        <div
                                                                            v-if="it.URL_Tautan"
                                                                            class="ri-link-url text-truncate mt-0.5"
                                                                            :title="it.URL_Tautan"
                                                                        >
                                                                            {{ it.URL_Tautan }}
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="ri-actions flex-shrink-0 d-flex align-items-center gap-2 mt-2 mt-md-0"
                                                                >
                                                                    <a
                                                                        class="stitch-btn primary sm"
                                                                        :href="it.URL_Tautan || '#'"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        @click="onOpenTautan(it)"
                                                                    >
                                                                        <i class="bi bi-box-arrow-up-right me-1"></i>
                                                                        {{
                                                                            it.Label_Tautan
                                                                                ? 'Kunjungi Tautan'
                                                                                : 'Buka Tautan'
                                                                        }}
                                                                    </a>
                                                                    <button
                                                                        v-if="!isDone(it)"
                                                                        class="stitch-btn secondary sm"
                                                                        :disabled="
                                                                            confirmingItem === it.Id_SOP_Paket_Item
                                                                        "
                                                                        @click="markSelesai(it)"
                                                                    >
                                                                        <i
                                                                            class="bi me-1"
                                                                            :class="
                                                                                confirmingItem === it.Id_SOP_Paket_Item
                                                                                    ? 'bi-arrow-repeat ri-spin'
                                                                                    : 'bi-check2-circle'
                                                                            "
                                                                        ></i>
                                                                        {{
                                                                            confirmingItem === it.Id_SOP_Paket_Item
                                                                                ? 'Menyimpan…'
                                                                                : 'Konfirmasi Selesai'
                                                                        }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Item Type: KUIS (Interactive Quiz Card) -->
                                                        <div
                                                            v-else-if="it.Tipe_Item === 'KUIS'"
                                                            class="ri-quiz-card mt-3"
                                                            :class="
                                                                it.Status_Lulus === 'Y'
                                                                    ? 'passed'
                                                                    : kuisFinal(it)
                                                                      ? 'failed'
                                                                      : ''
                                                            "
                                                        >
                                                            <div class="ri-quiz-card-inner">
                                                                <div class="ri-quiz-main flex-grow-1 min-w-0">
                                                                    <div class="ri-quiz-ico">
                                                                        <i
                                                                            class="bi"
                                                                            :class="
                                                                                it.Status_Lulus === 'Y'
                                                                                    ? 'bi-trophy-fill'
                                                                                    : 'bi-patch-question-fill'
                                                                            "
                                                                        ></i>
                                                                    </div>
                                                                    <div class="ri-quiz-meta min-w-0 flex-grow-1">
                                                                        <div
                                                                            class="d-flex align-items-center gap-2 flex-wrap mb-1"
                                                                        >
                                                                            <span class="ri-quiz-badge-type">
                                                                                <i
                                                                                    class="bi bi-mortarboard-fill me-1"
                                                                                ></i>
                                                                                {{ it.Jenis_Quiz || 'Kuis Pemahaman' }}
                                                                            </span>
                                                                            <span
                                                                                v-if="it.Status_Lulus === 'Y'"
                                                                                class="ri-quiz-status ok"
                                                                            >
                                                                                <i
                                                                                    class="bi bi-check-circle-fill me-1"
                                                                                ></i>
                                                                                LULUS
                                                                            </span>
                                                                            <span
                                                                                v-else-if="
                                                                                    ['N', 'T'].includes(it.Status_Lulus)
                                                                                "
                                                                                class="ri-quiz-status bad"
                                                                            >
                                                                                <i class="bi bi-x-circle-fill me-1"></i>
                                                                                BELUM LULUS
                                                                            </span>
                                                                            <span
                                                                                v-else-if="it.Kuis_Menunggu"
                                                                                class="ri-quiz-status wait"
                                                                            >
                                                                                <i
                                                                                    class="bi bi-hourglass-split me-1"
                                                                                ></i>
                                                                                MENUNGGU HASIL
                                                                            </span>
                                                                        </div>

                                                                        <!-- Metrics Grid -->
                                                                        <div
                                                                            class="ri-quiz-metrics d-flex flex-wrap gap-2 mt-2"
                                                                        >
                                                                            <div
                                                                                v-if="it.Skor_Quiz != null"
                                                                                class="ri-quiz-m-chip"
                                                                            >
                                                                                <span class="ri-quiz-m-lbl"
                                                                                    >Skor Anda</span
                                                                                >
                                                                                <strong
                                                                                    class="ri-quiz-m-val"
                                                                                    :class="
                                                                                        it.Status_Lulus === 'Y'
                                                                                            ? 'text-success'
                                                                                            : 'text-danger'
                                                                                    "
                                                                                    >{{ it.Skor_Quiz }}</strong
                                                                                >
                                                                            </div>
                                                                            <div
                                                                                v-if="it.Nilai_Minimum != null"
                                                                                class="ri-quiz-m-chip"
                                                                            >
                                                                                <span class="ri-quiz-m-lbl"
                                                                                    >Batas Kelulusan</span
                                                                                >
                                                                                <strong
                                                                                    class="ri-quiz-m-val text-dark"
                                                                                    >{{ it.Nilai_Minimum }}</strong
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="ri-quiz-action flex-shrink-0 mt-3 mt-md-0 d-flex flex-column align-items-md-end gap-1"
                                                                >
                                                                    <div
                                                                        v-if="it.Kuis_Menunggu"
                                                                        class="ri-quiz-wait-box mb-1 text-md-end"
                                                                    >
                                                                        <span class="small text-muted d-block"
                                                                            >Menunggu hasil ujian</span
                                                                        >
                                                                        <button
                                                                            class="linklike text-primary fw-bold small"
                                                                            :disabled="loading"
                                                                            @click="fetchDetail(false)"
                                                                        >
                                                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                                                            Perbarui Status
                                                                        </button>
                                                                    </div>

                                                                    <div
                                                                        v-else-if="kuisFinal(it)"
                                                                        class="ri-quiz-final-pill"
                                                                    >
                                                                        <i class="bi bi-flag-fill me-1"></i> Hasil Akhir
                                                                        Kuis
                                                                    </div>

                                                                    <a
                                                                        v-if="!kuisFinal(it)"
                                                                        class="ri-btn-quiz-cta"
                                                                        :class="{
                                                                            disabled:
                                                                                startingKuis === it.Id_SOP_Paket_Item,
                                                                        }"
                                                                        :href="urlKuis(it)"
                                                                        @click="onStartKuis(it, $event)"
                                                                    >
                                                                        <i
                                                                            class="bi me-1"
                                                                            :class="
                                                                                startingKuis === it.Id_SOP_Paket_Item
                                                                                    ? 'bi-arrow-repeat ri-spin'
                                                                                    : 'bi-play-circle-fill'
                                                                            "
                                                                        ></i>
                                                                        {{
                                                                            startingKuis === it.Id_SOP_Paket_Item
                                                                                ? 'Mengarahkan ke Ujian…'
                                                                                : it.Kuis_Menunggu
                                                                                  ? 'Lanjutkan Ujian'
                                                                                  : it.Jumlah_Percobaan
                                                                                    ? 'Ulangi Ujian'
                                                                                    : 'Mulai Ujian'
                                                                        }}
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Item Type: FORM — selalu bisa diunduh, apa pun jenis berkasnya.
                                                     Yang berupa PDF juga bisa dilihat di tempat; sisanya
                                                     (.xlsx/.csv/.docx) tak punya penampil web yang bisa dipercaya,
                                                     jadi tombol "Lihat" sengaja tak muncul di sana daripada
                                                     memunculkan tombol yang gagal setelah ditekan. -->
                                                        <div
                                                            v-else-if="it.Tipe_Item === 'FORM'"
                                                            class="ri-doc-card mt-3"
                                                        >
                                                            <div class="ri-doc-card-inner">
                                                                <div class="ri-doc-main">
                                                                    <div class="ri-doc-ico">
                                                                        <!-- Ikon dari mime asli. Konten_Tipe cuma 'document' untuk
                                                                     PDF maupun spreadsheet, jadi ikonnya dulu selalu sama. -->
                                                                        <i
                                                                            class="bi"
                                                                            :class="
                                                                                kontenIcon(
                                                                                    it.Konten_Mime,
                                                                                    it.Konten_Ext,
                                                                                )
                                                                            "
                                                                        ></i>
                                                                    </div>
                                                                    <div class="ri-doc-meta min-w-0">
                                                                        <div
                                                                            class="ri-doc-name text-truncate"
                                                                            :title="it.Konten_Nama || 'Berkas formulir'"
                                                                        >
                                                                            {{ it.Konten_Nama || 'Berkas Formulir' }}
                                                                        </div>
                                                                        <div
                                                                            class="d-flex align-items-center gap-2 mt-1 flex-wrap"
                                                                        >
                                                                            <span
                                                                                v-if="it.Lampiran && it.Lampiran.length > 0"
                                                                                class="ri-badge-contoh"
                                                                                :title="it.Bisa_Pratinjau ? 'Dokumen acuan contoh pengisian (dapat dibuka langsung)' : 'Dokumen acuan contoh pengisian'"
                                                                            >
                                                                                <i class="bi bi-file-earmark-check me-1"></i>Contoh Pengisian
                                                                            </span>
                                                                            <span
                                                                                v-else
                                                                                class="ri-akses ok"
                                                                            >
                                                                                <i
                                                                                    class="bi me-1"
                                                                                    :class="
                                                                                        it.Bisa_Pratinjau
                                                                                            ? 'bi-eye'
                                                                                            : 'bi-download'
                                                                                    "
                                                                                ></i>
                                                                                {{
                                                                                    it.Bisa_Pratinjau
                                                                                        ? 'Lihat formulir'
                                                                                        : 'Unduh formulir'
                                                                                }}
                                                                            </span>
                                                                            <!-- Ekstensi asli dari server. Konten_Nama itu judul konten,
                                                                         tanpa ekstensi — membacanya dari sana selalu kosong. -->
                                                                            <span
                                                                                v-if="it.Konten_Ext"
                                                                                class="ri-doc-ext"
                                                                                >{{ it.Konten_Ext }}</span
                                                                            >
                                                                            <span
                                                                                v-if="it.Ukuran_File"
                                                                                class="text-muted small"
                                                                                >·
                                                                                {{ fmtFileSize(it.Ukuran_File) }}</span
                                                                            >
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="ri-actions flex-shrink-0 d-flex align-items-center flex-wrap gap-2">
                                                                    <!-- 1. Tombol Utama: Contoh Pengisian / Formulir (Merged: Lihat jika bisa pratinjau, atau Unduh jika non-pratinjau) -->
                                                                    <button
                                                                        class="stitch-btn primary sm"
                                                                        :disabled="
                                                                            !it.Id_Item_Versi_Aktif ||
                                                                            openingKonten === it.Id_SOP_Paket_Item ||
                                                                            unduhing === it.Id_SOP_Paket_Item
                                                                        "
                                                                        :title="
                                                                            it.Bisa_Pratinjau
                                                                                ? (it.Lampiran && it.Lampiran.length > 0
                                                                                    ? 'Buka pratinjau contoh pengisian'
                                                                                    : 'Buka pratinjau formulir')
                                                                                : (it.Lampiran && it.Lampiran.length > 0
                                                                                    ? 'Unduh berkas contoh pengisian'
                                                                                    : 'Unduh berkas formulir')
                                                                        "
                                                                        @click="
                                                                            it.Bisa_Pratinjau
                                                                                ? openKonten(
                                                                                      it,
                                                                                      it.Id_Item_Versi_Aktif,
                                                                                      false,
                                                                                  )
                                                                                : unduhForm(it, it.Id_Item_Versi_Aktif)
                                                                        "
                                                                    >
                                                                        <i
                                                                            class="bi me-1"
                                                                            :class="
                                                                                openingKonten === it.Id_SOP_Paket_Item ||
                                                                                unduhing === it.Id_SOP_Paket_Item
                                                                                    ? 'bi-arrow-repeat ri-spin'
                                                                                    : it.Bisa_Pratinjau
                                                                                      ? 'bi-eye'
                                                                                      : 'bi-download'
                                                                            "
                                                                        ></i>
                                                                        {{
                                                                            openingKonten === it.Id_SOP_Paket_Item
                                                                                ? 'Membuka…'
                                                                                : unduhing === it.Id_SOP_Paket_Item
                                                                                  ? 'Menyiapkan…'
                                                                                  : it.Bisa_Pratinjau
                                                                                    ? (it.Lampiran && it.Lampiran.length > 0
                                                                                        ? 'Lihat Contoh Pengisian'
                                                                                        : 'Lihat Formulir')
                                                                                    : (it.Lampiran && it.Lampiran.length > 0
                                                                                        ? 'Unduh Contoh Pengisian'
                                                                                        : 'Unduh Formulir')
                                                                        }}
                                                                    </button>
                                                                    <!-- 3. Form Kosong / Lampiran Template (Compact & Inline) -->
                                                                    <!-- Kasus 1: Tepat 1 lampiran -> langsung unduh file tersebut -->
                                                                    <button
                                                                        v-if="it.Lampiran && it.Lampiran.length === 1"
                                                                        class="stitch-btn secondary sm"
                                                                        :disabled="
                                                                            !it.Id_Item_Versi_Aktif ||
                                                                            unduhLampiranId === it.Lampiran[0].Id_Lampiran
                                                                        "
                                                                        :title="`Unduh form kosong: ${it.Lampiran[0].Nama || 'Form Kosong'}`"
                                                                        @click="unduhLampiran(it, it.Lampiran[0])"
                                                                    >
                                                                        <HcisIcon
                                                                            :name="unduhLampiranId === it.Lampiran[0].Id_Lampiran ? 'arrow-repeat' : 'file-earmark-arrow-down'"
                                                                            :animation="unduhLampiranId === it.Lampiran[0].Id_Lampiran ? 'spin' : ''"
                                                                            size="sm"
                                                                            class="me-1"
                                                                        />
                                                                        {{
                                                                            unduhLampiranId === it.Lampiran[0].Id_Lampiran
                                                                                ? 'Menyiapkan…'
                                                                                : 'Form Kosong'
                                                                        }}
                                                                    </button>

                                                                    <!-- Kasus 2: Banyak lampiran -> Popover dengan opsi Unduh Semua atau Unduh per berkas -->
                                                                    <el-popover
                                                                        v-else-if="it.Lampiran && it.Lampiran.length > 1"
                                                                        placement="bottom-end"
                                                                        :width="410"
                                                                        trigger="click"
                                                                        popper-class="ri-lampiran-popover sop-scope"
                                                                    >
                                                                        <template #reference>
                                                                            <button
                                                                                type="button"
                                                                                class="stitch-btn secondary sm"
                                                                                :disabled="!it.Id_Item_Versi_Aktif"
                                                                                title="Daftar template form kosong untuk diisi"
                                                                            >
                                                                                <HcisIcon name="file-earmark-arrow-down" size="sm" class="me-1" />
                                                                                <span
                                                                                    >Form Kosong ({{
                                                                                        it.Lampiran.length
                                                                                    }})</span
                                                                                >
                                                                                <HcisIcon name="chevron-down" :size="10" class="ms-1" />
                                                                            </button>
                                                                        </template>

                                                                        <div class="ri-pop-lampiran-container">
                                                                            <!-- Header Popover -->
                                                                            <div
                                                                                class="ri-pop-header d-flex align-items-center justify-content-between"
                                                                            >
                                                                                <div class="min-w-0 me-3">
                                                                                    <div class="ri-pop-title text-truncate">
                                                                                        Form Kosong untuk Diisi
                                                                                    </div>
                                                                                    <div class="ri-pop-subtitle mt-0.5">
                                                                                        {{ it.Lampiran.length }} berkas formulir tersedia
                                                                                    </div>
                                                                                </div>
                                                                                <button
                                                                                    type="button"
                                                                                    class="stitch-btn secondary sm flex-shrink-0"
                                                                                    :disabled="
                                                                                        unduhSemuaLoading ===
                                                                                        it.Id_SOP_Paket_Item
                                                                                    "
                                                                                    title="Unduh seluruh form kosong sekaligus"
                                                                                    @click="unduhSemuaLampiran(it)"
                                                                                >
                                                                                    <HcisIcon
                                                                                        :name="unduhSemuaLoading === it.Id_SOP_Paket_Item ? 'arrow-repeat' : 'cloud-arrow-down-fill'"
                                                                                        :animation="unduhSemuaLoading === it.Id_SOP_Paket_Item ? 'spin' : ''"
                                                                                        :class="{ 'text-primary': unduhSemuaLoading !== it.Id_SOP_Paket_Item }"
                                                                                        size="sm"
                                                                                    />
                                                                                    <span>{{
                                                                                        unduhSemuaLoading ===
                                                                                        it.Id_SOP_Paket_Item
                                                                                            ? 'Mengunduh…'
                                                                                            : 'Unduh Semua'
                                                                                    }}</span>
                                                                                </button>
                                                                            </div>

                                                                            <!-- List Lampiran -->
                                                                            <div
                                                                                class="ri-pop-list d-flex flex-column gap-2"
                                                                            >
                                                                                <div
                                                                                    v-for="l in it.Lampiran"
                                                                                    :key="l.Id_Lampiran"
                                                                                    class="ri-pop-item d-flex align-items-center justify-content-between"
                                                                                    :class="{
                                                                                        'is-loading':
                                                                                            unduhLampiranId ===
                                                                                            l.Id_Lampiran,
                                                                                    }"
                                                                                    :title="`Klik untuk mengunduh ${l.Nama || 'Form kosong'}`"
                                                                                    @click="unduhLampiran(it, l)"
                                                                                >
                                                                                    <div
                                                                                        class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1 me-2"
                                                                                    >
                                                                                        <div
                                                                                            class="ri-pop-file-icon"
                                                                                            :class="
                                                                                                lampiranIcoClass(l.Ext)
                                                                                            "
                                                                                        >
                                                                                            <HcisIcon
                                                                                                :name="kontenIcon('', '.' + (l.Ext || ''))"
                                                                                                size="sm"
                                                                                            />
                                                                                        </div>
                                                                                        <div
                                                                                            class="min-w-0 flex-grow-1"
                                                                                        >
                                                                                            <div
                                                                                                class="ri-pop-file-name text-truncate"
                                                                                                :title="l.Nama"
                                                                                            >
                                                                                                {{
                                                                                                    l.Nama ||
                                                                                                    'Form kosong'
                                                                                                }}
                                                                                            </div>
                                                                                            <div
                                                                                                class="d-flex align-items-center gap-2 mt-1"
                                                                                            >
                                                                                                <span
                                                                                                    v-if="l.Ext"
                                                                                                    class="ri-doc-ext"
                                                                                                >
                                                                                                    {{
                                                                                                        String(
                                                                                                            l.Ext,
                                                                                                        ).toUpperCase()
                                                                                                    }}
                                                                                                </span>
                                                                                                <span
                                                                                                    v-if="l.Ukuran"
                                                                                                    class="text-muted small"
                                                                                                >
                                                                                                    · {{
                                                                                                        fmtFileSize(
                                                                                                            l.Ukuran,
                                                                                                        )
                                                                                                    }}
                                                                                                </span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <button
                                                                                        type="button"
                                                                                        class="stitch-icon-btn flex-shrink-0"
                                                                                        :disabled="
                                                                                            !it.Id_Item_Versi_Aktif ||
                                                                                            unduhLampiranId ===
                                                                                                l.Id_Lampiran
                                                                                        "
                                                                                        :title="`Unduh ${l.Nama || 'Form kosong'}`"
                                                                                        @click.stop="
                                                                                            unduhLampiran(it, l)
                                                                                        "
                                                                                    >
                                                                                        <HcisIcon
                                                                                            :name="unduhLampiranId === l.Id_Lampiran ? 'arrow-repeat' : 'download'"
                                                                                            :animation="unduhLampiranId === l.Id_Lampiran ? 'spin' : ''"
                                                                                            size="sm"
                                                                                        />
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </el-popover>

                                                                    <button
                                                                        v-if="!isDone(it)"
                                                                        class="stitch-btn secondary sm"
                                                                        :disabled="
                                                                            confirmingItem === it.Id_SOP_Paket_Item
                                                                        "
                                                                        @click="markSelesai(it)"
                                                                    >
                                                                        <i
                                                                            class="bi me-1"
                                                                            :class="
                                                                                confirmingItem === it.Id_SOP_Paket_Item
                                                                                    ? 'bi-arrow-repeat ri-spin'
                                                                                    : 'bi-check2-circle'
                                                                            "
                                                                        ></i>
                                                                        {{
                                                                            confirmingItem === it.Id_SOP_Paket_Item
                                                                                ? 'Menyiapkan…'
                                                                                : 'Konfirmasi Selesai'
                                                                        }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Item Type: DOKUMEN / VIDEO -->
                                                        <div v-else class="ri-doc-card mt-3">
                                                            <div class="ri-doc-card-inner">
                                                                <div class="ri-doc-main flex-grow-1">
                                                                    <div class="ri-doc-ico">
                                                                        <i
                                                                            class="bi"
                                                                            :class="kontenIcon(it.Konten_Tipe)"
                                                                        ></i>
                                                                    </div>
                                                                    <div class="ri-doc-meta min-w-0 flex-grow-1">
                                                                        <div
                                                                            class="ri-doc-name text-truncate"
                                                                            :title="
                                                                                it.Konten_Nama || 'Dokumen Lampiran'
                                                                            "
                                                                        >
                                                                            {{
                                                                                it.Konten_Nama || 'Dokumen Lampiran'
                                                                            }}
                                                                        </div>

                                                                        <div
                                                                            class="d-flex align-items-center gap-2 mt-1 flex-wrap"
                                                                        >
                                                                            <span
                                                                                class="ri-akses"
                                                                                :class="canDownload(it) ? 'ok' : 'lock'"
                                                                            >
                                                                                <i
                                                                                    class="bi me-1"
                                                                                    :class="
                                                                                        canDownload(it)
                                                                                            ? 'bi-download'
                                                                                            : 'bi-eye'
                                                                                    "
                                                                                ></i>
                                                                                {{
                                                                                    canDownload(it)
                                                                                        ? 'Izin Unduh'
                                                                                        : 'Mode Lihat'
                                                                                }}
                                                                            </span>
                                                                            <span
                                                                                v-if="it.Ukuran_File"
                                                                                class="text-muted small"
                                                                                >·
                                                                                {{ fmtFileSize(it.Ukuran_File) }}</span
                                                                            >

                                                                            <span
                                                                                v-if="itemProgressText(it)"
                                                                                class="ri-prog-badge ms-auto ms-sm-1"
                                                                                :class="
                                                                                    isDone(it)
                                                                                        ? 'ok'
                                                                                        : itemProgressPct(it) > 0
                                                                                          ? 'reading'
                                                                                          : 'idle'
                                                                                "
                                                                            >
                                                                                <i
                                                                                    class="bi me-1"
                                                                                    :class="
                                                                                        isDone(it)
                                                                                            ? 'bi-check-circle-fill'
                                                                                            : itemProgressPct(it) > 0
                                                                                              ? 'bi-book-half'
                                                                                              : 'bi-circle'
                                                                                    "
                                                                                ></i>
                                                                                {{ itemProgressText(it) }}
                                                                            </span>
                                                                        </div>

                                                                        <!-- Progress Bar Strip -->
                                                                        <div
                                                                            v-if="itemProgressPct(it) > 0 || isDone(it)"
                                                                            class="ri-prog-bar-wrap mt-2"
                                                                        >
                                                                            <div
                                                                                class="ri-prog-bar"
                                                                                :class="{ done: isDone(it) }"
                                                                                :style="{
                                                                                    width: itemProgressPct(it) + '%',
                                                                                }"
                                                                            ></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <button
                                                                    class="ri-btn-doc-cta flex-shrink-0 ms-md-3 mt-2 mt-md-0"
                                                                    :disabled="
                                                                        !it.Id_Item_Versi_Aktif ||
                                                                        openingKonten === it.Id_SOP_Paket_Item
                                                                    "
                                                                    @click="
                                                                        openKonten(it, it.Id_Item_Versi_Aktif, false)
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi me-1.5"
                                                                        :class="
                                                                            openingKonten === it.Id_SOP_Paket_Item
                                                                                ? 'bi-arrow-repeat ri-spin'
                                                                                : ctaIcon(it.Tipe_Item)
                                                                        "
                                                                    ></i>
                                                                    <span>{{
                                                                        openingKonten === it.Id_SOP_Paket_Item
                                                                            ? 'Membuka…'
                                                                            : ctaLabel(it.Tipe_Item)
                                                                    }}</span>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Timestamp Audit Track Meta -->
                                                        <div
                                                            v-if="
                                                                it.Tipe_Item !== 'PARAGRAF' &&
                                                                (it.Waktu_Buka ||
                                                                    it.Waktu_Konfirmasi ||
                                                                    it.Durasi_Detik)
                                                            "
                                                            class="ri-track mt-3"
                                                        >
                                                            <span v-if="it.Waktu_Konfirmasi" class="ri-track-pill ok">
                                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                                Dikonfirmasi {{ fmtDateTime(it.Waktu_Konfirmasi) }}
                                                            </span>
                                                            <span v-else-if="it.Waktu_Buka" class="ri-track-pill info">
                                                                <i class="bi bi-eye-fill me-1"></i> Pertama dibuka
                                                                {{ fmtDateTime(it.Waktu_Buka) }}
                                                            </span>
                                                            <span v-if="it.Durasi_Detik" class="ri-track-pill idle">
                                                                <i class="bi bi-stopwatch-fill me-1"></i>
                                                                {{ fmtDurasi(it.Durasi_Detik) }}
                                                            </span>
                                                        </div>

                                                        <!-- Collapsible Document Information Card (Official Seal & Approval) -->
                                                        <div v-if="hasDocInfo(it)" class="ri-docinfo mt-3 pt-2">
                                                            <button
                                                                class="ri-btn-collapse"
                                                                :class="{ open: openDoc === it.Id_SOP_Paket_Item }"
                                                                @click="toggleDoc(it)"
                                                            >
                                                                <div
                                                                    class="d-flex align-items-center gap-3 min-w-0 flex-grow-1"
                                                                >
                                                                    <div class="ri-collapse-icon">
                                                                        <i class="bi bi-shield-check"></i>
                                                                    </div>
                                                                    <div class="ri-collapse-text text-truncate">
                                                                        Legalitas &amp; Pengesahan Dokumen
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="d-flex align-items-center gap-2 flex-shrink-0 ms-3"
                                                                >
                                                                    <span class="ri-btn-collapse-badge ok">
                                                                        <i class="bi bi-patch-check-fill"></i> Resmi
                                                                    </span>
                                                                    <div class="ri-chevron-box">
                                                                        <i
                                                                            class="bi"
                                                                            :class="
                                                                                openDoc === it.Id_SOP_Paket_Item
                                                                                    ? 'bi-chevron-up'
                                                                                    : 'bi-chevron-down'
                                                                            "
                                                                        ></i>
                                                                    </div>
                                                                </div>
                                                            </button>

                                                            <transition name="sop-swap">
                                                                <div
                                                                    v-if="openDoc === it.Id_SOP_Paket_Item"
                                                                    class="ri-docinfo-body"
                                                                >
                                                                    <!-- Single Unified Executive Document Info Card -->
                                                                    <div class="ri-doc-unified-card">
                                                                        <!-- Header: Official Seal & Approval Info -->
                                                                        <div class="ri-doc-head">
                                                                            <div
                                                                                class="d-flex align-items-center gap-3"
                                                                            >
                                                                                <div class="ri-doc-seal-ico">
                                                                                    <i
                                                                                        class="bi bi-patch-check-fill"
                                                                                    ></i>
                                                                                </div>
                                                                                <div class="flex-grow-1 min-w-0">
                                                                                    <div
                                                                                        class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1"
                                                                                    >
                                                                                        <span class="ri-doc-eyebrow"
                                                                                            >PENGESAHAN RESMI
                                                                                            DOKUMEN</span
                                                                                        >
                                                                                        <span class="ri-doc-status-tag">
                                                                                            <i
                                                                                                class="bi bi-shield-lock-fill me-1"
                                                                                            ></i
                                                                                            >TERVERIFIKASI
                                                                                        </span>
                                                                                    </div>
                                                                                    <div class="ri-doc-authority">
                                                                                        {{
                                                                                            it.Disahkan_Oleh ||
                                                                                            'Tim Manajemen Operational & Mutu'
                                                                                        }}
                                                                                    </div>
                                                                                    <div class="ri-doc-role">
                                                                                        {{
                                                                                            it.Jabatan_Pengesah ||
                                                                                            'Otoritas Penjaminan Mutu Dokumen'
                                                                                        }}
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div
                                                                                class="ri-doc-tags mt-2.5 pt-2.5 border-top d-flex align-items-center flex-wrap gap-2"
                                                                            >
                                                                                <span class="ri-doc-tag">
                                                                                    <i
                                                                                        class="bi bi-hash text-emerald"
                                                                                    ></i>
                                                                                    <span>{{
                                                                                        it.Nomor_Pengesahan ||
                                                                                        'SOP-REF-V' + (it.Versi_Ke || 1)
                                                                                    }}</span>
                                                                                </span>
                                                                                <span
                                                                                    v-if="
                                                                                        it.Tanggal_Pengesahan ||
                                                                                        it.Tanggal_Berlaku ||
                                                                                        detail?.paket?.Tanggal_Publish
                                                                                    "
                                                                                    class="ri-doc-tag"
                                                                                >
                                                                                    <i
                                                                                        class="bi bi-calendar-check text-emerald"
                                                                                    ></i>
                                                                                    <span
                                                                                        >Disahkan:
                                                                                        {{
                                                                                            fmtDate(
                                                                                                it.Tanggal_Pengesahan ||
                                                                                                    it.Tanggal_Berlaku ||
                                                                                                    detail?.paket
                                                                                                        ?.Tanggal_Publish,
                                                                                            )
                                                                                        }}</span
                                                                                    >
                                                                                </span>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Middle: Validity Grid -->
                                                                        <div class="ri-doc-validity-strip">
                                                                            <div class="ri-doc-val-cell">
                                                                                <div class="ri-doc-val-ico">
                                                                                    <i class="bi bi-calendar-event"></i>
                                                                                </div>
                                                                                <div>
                                                                                    <span class="ri-doc-val-lbl"
                                                                                        >Tanggal Mulai Berlaku</span
                                                                                    >
                                                                                    <span class="ri-doc-val-val tnum">{{
                                                                                        fmtDate(
                                                                                            it.Tanggal_Berlaku ||
                                                                                                it.Tanggal_Publish ||
                                                                                                detail?.paket
                                                                                                    ?.Tanggal_Publish,
                                                                                        ) || 'Sejak Diterbitkan'
                                                                                    }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Bottom: Version History Drawer Trigger -->
                                                                        <div
                                                                            v-if="
                                                                                it.Ada_Riwayat ||
                                                                                it.Id_Item_Versi_Aktif ||
                                                                                ['DOKUMEN', 'FORM', 'VIDEO'].includes(
                                                                                    it.Tipe_Item,
                                                                                )
                                                                            "
                                                                            class="ri-doc-foot"
                                                                        >
                                                                            <button
                                                                                class="ri-doc-versi-btn"
                                                                                :class="{
                                                                                    open:
                                                                                        openVersi ===
                                                                                        it.Id_SOP_Paket_Item,
                                                                                }"
                                                                                @click="toggleVersi(it)"
                                                                            >
                                                                                <i
                                                                                    class="bi bi-clock-history text-primary"
                                                                                ></i>
                                                                                <span
                                                                                    >Lihat Riwayat Versi Terdahulu</span
                                                                                >
                                                                                <i
                                                                                    class="bi ri-btn-chevron ms-auto"
                                                                                    :class="
                                                                                        openVersi ===
                                                                                        it.Id_SOP_Paket_Item
                                                                                            ? 'bi-chevron-up'
                                                                                            : 'bi-chevron-down'
                                                                                    "
                                                                                ></i>
                                                                            </button>
                                                                            <transition name="sop-swap">
                                                                                <div
                                                                                    v-if="
                                                                                        openVersi ===
                                                                                        it.Id_SOP_Paket_Item
                                                                                    "
                                                                                    class="ri-versi-body pt-2"
                                                                                >
                                                                                    <SopVersiTimeline
                                                                                        :versions="versiList"
                                                                                        :loading="versiLoading"
                                                                                        @view="viewVersi(it, $event)"
                                                                                    />
                                                                                </div>
                                                                            </transition>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </transition>
                                                        </div>
                                                    </template>
                                                </article>
                                            </div>
                                        </div>
                                    </transition>
                                </template>

                                <!-- Cool Terminal Spine Cap at the VERY END of ALL sections & items -->
                                <div class="rd-flow-terminal mt-4 mb-2">
                                    <div class="rd-terminal-node" :class="{ done: isComplete }">
                                        <i class="bi" :class="isComplete ? 'bi-trophy-fill' : 'bi-flag-fill'"></i>
                                        <span class="rd-terminal-ripple"></span>
                                    </div>
                                    <div class="rd-terminal-card" :class="{ done: isComplete }">
                                        <div class="rd-terminal-title">
                                            <i
                                                class="bi me-1.5"
                                                :class="
                                                    isComplete
                                                        ? 'bi-patch-check-fill text-success'
                                                        : 'bi-flag-fill text-primary'
                                                "
                                            ></i>
                                            <span>{{
                                                isComplete ? 'Seluruh Langkah Dokumen Selesai!' : 'Akhir Alur Langkah Dokumen'
                                            }}</span>
                                        </div>
                                        <div class="rd-terminal-sub text-muted small">
                                            <template v-if="isComplete">
                                                Catatan penyelesaian {{ detail?.progress?.total || 0 }} langkah wajib
                                                sudah tersimpan.
                                            </template>
                                            <template v-else>
                                                Total {{ detail?.progress?.total || 0 }} langkah wajib dalam paket
                                                dokumen ini.
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Completion Celebration Banner -->
                            <div v-if="isComplete" class="rd-done my-4">
                                <div class="rd-done-badge"><i class="bi bi-patch-check-fill"></i></div>
                                <div class="rd-done-txt">
                                    <strong>Alur Dokumen telah tuntas</strong>
                                    <div class="small mt-1">
                                        Seluruh {{ detail?.progress?.total || 0 }} langkah wajib telah diselesaikan dan
                                        tercatat.
                                    </div>
                                </div>
                                <div class="rd-done-actions">
                                    <button class="stitch-btn secondary sm" @click="openCelebration">
                                        <i class="bi bi-stars me-1"></i> Rayakan Lagi
                                    </button>
                                    <button class="stitch-btn primary sm" @click="gotoSaya">
                                        <i class="bi bi-grid-1x2-fill me-1"></i> Functional Document
                                    </button>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>

                <!-- Bar bawah (layar kecil). Sisi kiri bukan sekadar tombol "Daftar Isi":
                 ia menampilkan bagian yang sedang dibaca, jadi pembaca tahu posisinya
                 tanpa membuka apa pun. Menekannya membuka rail sebagai bottom sheet. -->
                <div class="rd-mbar d-lg-none">
                    <div class="rd-mbar-prog"><span :style="{ width: detail.progress.persen + '%' }"></span></div>
                    <div class="rd-mbar-row">
                        <button
                            class="rd-mbar-nav"
                            :aria-expanded="tocOpen ? 'true' : 'false'"
                            aria-label="Buka daftar isi, legalitas, dan progres"
                            @click="tocOpen = true"
                        >
                            <span class="rd-mbar-pct tnum">{{ detail.progress.persen }}%</span>
                            <span class="rd-mbar-ctx">
                                <span class="rd-mbar-lbl">{{ activeSectionName || 'Daftar isi' }}</span>
                                <span class="rd-mbar-sub tnum">
                                    {{ detail.progress.item_selesai }}/{{ detail.progress.total }} wajib<template
                                        v-if="remainingMin"
                                    >
                                        · ~{{ remainingMin }} mnt</template
                                    >
                                </span>
                            </span>
                            <i class="bi bi-chevron-up rd-mbar-caret"></i>
                        </button>
                        <button v-if="firstUnfinished && !isComplete" class="rd-mbar-cta" @click="lanjutkan">
                            <i class="bi bi-play-fill"></i><span class="rd-mbar-cta-txt">Lanjut</span>
                        </button>
                        <span v-else class="rd-mbar-done" title="Semua item tuntas"
                            ><i class="bi bi-check2-circle"></i
                        ></span>
                    </div>
                </div>
            </div>

            <!-- Empty State Error -->
            <div v-else key="empty" class="reader-shell p-4">
                <EmptyState
                    icon="bi-exclamation-triangle"
                    title="Dokumen Tidak Ditemukan"
                    subtitle="Tautan dokumen mungkin kedaluwarsa atau berada di luar wewenang unit kerja Anda."
                />
            </div>
        </transition>

        <!-- Integrated Modal Viewers -->
        <SopKontenViewer
            :show="viewer.show"
            :loading="viewer.loading"
            :konten="viewer.konten"
            :judul="viewer.judul"
            :tipe="viewer.tipe"
            :status="viewer.status"
            :readonly="viewer.readonly"
            :marking="acting"
            :mode-akses="viewer.item ? viewer.item.Mode_Akses : null"
            :id-item="viewer.item ? viewer.item.Id_SOP_Paket_Item : ''"
            :halaman-terakhir="viewer.item && viewer.item.Halaman_Terakhir ? viewer.item.Halaman_Terakhir : 0"
            :id-paket="idPaket"
            :dokumen="viewer.item"
            :versi-dibaca="viewer.versiDibaca || null"
            :mengunduh="!!viewer.item && unduhing === viewer.item.Id_SOP_Paket_Item"
            :nav-sections="orderedSections"
            :nav-items="detail ? detail.items : []"
            @close="viewer.show = false"
            @complete="completeFromViewer"
            @select-item="handleViewerItemSelect"
            @unduh="unduhDariViewer"
            @unduh-lampiran="(l) => viewer.item && unduhLampiran(viewer.item, l)"
            @unduh-semua-lampiran="viewer.item && unduhSemuaLampiran(viewer.item)"
        />

        <SopCompletionCelebration
            :show="celebrate"
            :nama-paket="detail?.paket?.Nama_Paket || 'Paket Dokumen'"
            :total-item="detail?.progress?.total || 0"
            :accent="celebrateAccent"
            :completed-at="detail?.penugasan?.Tanggal_Selesai || ''"
            :quiz-total="completionQuiz.total"
            :quiz-passed="completionQuiz.passed"
            :quiz-failed="completionQuiz.failed"
            @close="celebrate = false"
            @review="reviewCompletion"
            @goto-saya="gotoSaya"
        />

        <!-- ══ 4. DIALOG TATA KELOLA & RIWAYAT VERSI PAKET (HcisDialog Enterprise) ══ -->
        <HcisDialog
            :model-value="showPaketInfoModal"
            :title="`Tata Kelola & Identitas — ${detail?.paket?.Nama_Paket || 'Paket Dokumen'}`"
            subtitle="Spesifikasi legalitas, matriks riwayat versi, otoritas pengesahan, dan jejak kepatuhan audit"
            icon="shield-check"
            sentiment="brand"
            size="xl"
            glow
            pattern="dots"
            sticky-footer
            @update:model-value="showPaketInfoModal = $event"
            @close="showPaketInfoModal = false"
        >
            <div class="sop-gov-dialog-body d-flex flex-column gap-3.5">
                <!-- Segmented Tab Cockpit -->
                <div class="sop-gov-tab-nav d-flex align-items-center gap-1.5 p-1 rounded-3 bg-light border flex-wrap">
                    <button
                        type="button"
                        class="sop-gov-tab-btn"
                        :class="{ active: paketInfoTab === 'identitas' }"
                        @click="paketInfoTab = 'identitas'"
                    >
                        <i class="bi bi-shield-check"></i>
                        <span>Identitas &amp; Tata Kelola</span>
                    </button>
                    <button
                        type="button"
                        class="sop-gov-tab-btn"
                        :class="{ active: paketInfoTab === 'dokumen' }"
                        @click="paketInfoTab = 'dokumen'"
                    >
                        <i class="bi bi-layers-fill"></i>
                        <span>Matriks Dokumen &amp; Versi</span>
                        <span class="sop-gov-tab-badge">{{ detail?.items?.length || 0 }}</span>
                    </button>
                    <button
                        type="button"
                        class="sop-gov-tab-btn"
                        :class="{ active: paketInfoTab === 'audit' }"
                        @click="paketInfoTab = 'audit'"
                    >
                        <i class="bi bi-patch-check-fill"></i>
                        <span>Jejak Kepatuhan &amp; Audit</span>
                    </button>
                    <button
                        v-if="legalTimeline.length"
                        type="button"
                        class="sop-gov-tab-btn"
                        :class="{ active: paketInfoTab === 'timeline' }"
                        @click="paketInfoTab = 'timeline'"
                    >
                        <i class="bi bi-clock-history"></i>
                        <span>Timeline Legalitas &amp; Rilis</span>
                        <span class="sop-gov-tab-badge">{{ legalTimeline.length }}</span>
                    </button>
                </div>

                <!-- Tab 1: Identitas & Tata Kelola -->
                <div v-if="paketInfoTab === 'identitas'" class="d-flex flex-column gap-3">
                    <!-- Hero Status Pill Matrix -->
                    <div class="sop-gov-hero-banner p-3.5 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="sop-gov-hero-seal">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="sop-gov-hero-title d-flex align-items-center gap-2 flex-wrap">
                                    <span>Dokumen Acuan Kerja Terkendali &amp; Sah</span>
                                    <span class="badge bg-success text-white">TERVERIFIKASI MUTU</span>
                                </div>
                                <div class="sop-gov-hero-sub text-muted small mt-0.5">
                                    {{ legalHeroSub || 'Paket prosedur ini diterbitkan di bawah pengawasan sistem manajemen kepatuhan HCIS.' }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap flex-shrink-0">
                            <span class="sop-gov-pill-ver">
                                <i class="bi bi-layers-fill me-1"></i>Paket v{{ legalPackage.versi_paket || '1.0' }}
                            </span>
                            <span class="sop-gov-pill-dist" :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'limited' : 'public'">
                                <i :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'bi bi-shield-lock-fill me-1' : 'bi bi-globe2 me-1'"></i>
                                {{ legalPackage.mode_distribusi === 'TERBATAS' ? 'Akses Terbatas' : 'Akses Publik' }}
                            </span>
                        </div>
                    </div>

                    <!-- Side-by-Side Detail Pillars -->
                    <div class="row g-3">
                        <!-- Pillar 1: Identitas Paket -->
                        <div class="col-12 col-md-6">
                            <div class="sop-gov-pillar-card h-100 p-3.5 rounded-4 border">
                                <div class="sop-gov-pillar-head d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="sop-gov-pillar-icon indigo">
                                            <i class="bi bi-file-earmark-text-fill"></i>
                                        </div>
                                        <strong class="text-dark small text-uppercase" style="letter-spacing: 0.04em;">Identitas &amp; Parameter</strong>
                                    </div>
                                    <span class="badge bg-light text-primary border">Paket Resmi</span>
                                </div>
                                <dl class="legal-kv mb-0">
                                    <dt>Kode Paket</dt>
                                    <dd><code class="sop-code-badge">{{ detail?.paket?.Kode_Paket || '-' }}</code></dd>
                                    <dt>Versi Rilis</dt>
                                    <dd><span class="badge bg-indigo-subtle text-indigo fw-bold">v{{ legalPackage.versi_paket || '1.0' }}</span></dd>
                                    <dt>Mode Distribusi</dt>
                                    <dd>
                                        <span class="badge" :class="legalPackage.mode_distribusi === 'TERBATAS' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-success-subtle text-success border border-success'">
                                            {{ legalPackage.mode_distribusi === 'TERBATAS' ? 'Akses Terbatas (Penugasan)' : 'Akses Publik Terbuka' }}
                                        </span>
                                    </dd>
                                    <dt>Kebijakan Alur</dt>
                                    <dd>{{ detail?.paket?.Mode_Urutan === 'SEKUENSIAL' ? 'Sekuensial (Wajib Sesuai Urutan)' : 'Bebas / Fleksibel' }}</dd>
                                    <dt>Presensi / Check-in</dt>
                                    <dd>{{ detail?.paket?.Flag_Absensi === 'Y' ? 'Wajib Check-in Kehadiran' : 'Tanpa Presensi' }}</dd>
                                    <dt>Estimasi Belajar</dt>
                                    <dd><i class="bi bi-clock me-1 text-muted"></i>~{{ detail?.paket?.Estimasi_Waktu_Baca_Menit || 15 }} Menit</dd>
                                </dl>
                            </div>
                        </div>

                        <!-- Pillar 2: Otoritas & Pengesahan -->
                        <div class="col-12 col-md-6">
                            <div class="sop-gov-pillar-card h-100 p-3.5 rounded-4 border">
                                <div class="sop-gov-pillar-head d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="sop-gov-pillar-icon emerald">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                        <strong class="text-dark small text-uppercase" style="letter-spacing: 0.04em;">Otoritas &amp; Pengesahan</strong>
                                    </div>
                                    <span class="badge bg-light text-success border">Tersertifikasi</span>
                                </div>
                                <dl class="legal-kv mb-0">
                                    <dt>Publisher Utama</dt>
                                    <dd>
                                        <div class="d-flex align-items-center gap-1.5 font-weight-bold text-dark">
                                            <i class="bi bi-person-badge-fill text-primary"></i>
                                            <span>{{ legalPublisherLabel || 'Administrator LMS & SOP' }}</span>
                                        </div>
                                    </dd>
                                    <dt>Unit Penanggung Jawab</dt>
                                    <dd>{{ ownerLabel || '-' }}</dd>
                                    <dt>Tanggal Terbit</dt>
                                    <dd>{{ fmtDate(detail?.paket?.Tanggal_Publish) || '-' }}</dd>
                                    <dt>Terakhir Diperbarui</dt>
                                    <dd>{{ fmtDate(detail?.paket?.Updated_At) || '-' }}</dd>
                                    <dt>Tingkat Pengesahan</dt>
                                    <dd><span class="badge bg-light text-dark border">{{ legalCompletenessText }}</span></dd>
                                    <dt>Komposisi Silabus</dt>
                                    <dd>{{ counts.all }} Langkah ({{ detail?.progress?.total || 0 }} Wajib)</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Scope / Ruang Lingkup -->
                    <div v-if="detail?.paket?.Tujuan_Ruang_Lingkup || detail?.paket?.Deskripsi" class="sop-gov-scope-card p-3.5 rounded-4 border">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="sop-gov-pillar-icon amber">
                                <i class="bi bi-bullseye"></i>
                            </div>
                            <strong class="text-dark small text-uppercase" style="letter-spacing: 0.04em;">Tujuan &amp; Ruang Lingkup Operasional</strong>
                        </div>
                        <p class="small mb-0 text-secondary leading-relaxed">
                            {{ detail?.paket?.Tujuan_Ruang_Lingkup || detail?.paket?.Deskripsi }}
                        </p>
                    </div>

                    <!-- Matriks Otoritas & Tanda Tangan Pengesahan (Multi-Signer Matrix) -->
                    <div class="sop-gov-authority-section p-3.5 rounded-4 border bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sop-gov-pillar-icon emerald">
                                    <i class="bi bi-patch-check-fill"></i>
                                </div>
                                <div>
                                    <strong class="text-dark small text-uppercase" style="letter-spacing: 0.04em;">Matriks Otoritas Pengesahan &amp; Tanda Tangan</strong>
                                    <div class="text-muted fs-xs">Daftar seluruh pejabat penyusun, pemeriksa, penyetuju, dan pengesah resmi</div>
                                </div>
                            </div>
                            <span class="badge bg-light text-success border px-2 py-1">
                                <i class="bi bi-shield-fill-check me-1"></i>{{ otoritasPengesahanList.length }} Otoritas Terdaftar
                            </span>
                        </div>

                        <div class="sop-gov-authority-grid">
                            <div
                                v-for="auth in otoritasPengesahanList"
                                :key="auth.nama + auth.peran"
                                class="sop-gov-authority-card p-3 rounded-3 border"
                                :class="'role-' + auth.peranBadge"
                            >
                                <div class="d-flex align-items-start gap-2.5">
                                    <div class="sop-gov-auth-icon" :class="auth.peranBadge">
                                        <i class="bi" :class="auth.ikon"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                            <span class="sop-gov-auth-pill" :class="auth.peranBadge">{{ auth.peran }}</span>
                                            <span v-if="auth.tanggal" class="fs-xs text-muted">{{ auth.tanggal }}</span>
                                        </div>
                                        <strong class="text-dark small d-block text-truncate" :title="auth.nama">{{ auth.nama }}</strong>
                                        <div class="text-muted fs-xs text-truncate mt-0.5">{{ auth.jabatan }}</div>
                                        <div v-if="auth.nomorSK" class="sop-gov-sk-tag mt-1.5">
                                            <i class="bi bi-file-earmark-check-fill me-1"></i>SK: {{ auth.nomorSK }}
                                        </div>
                                        <div v-if="auth.dokumenCount" class="fs-xs text-muted mt-1">
                                            <i class="bi bi-file-earmark-text me-1"></i>{{ auth.dokumenCount }} Dokumen Terkait
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Matriks Dokumen & Lembar Pengesahan Per Dokumen -->
                <div v-else-if="paketInfoTab === 'dokumen'" class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="small text-muted">
                            Matriks kontrol dokumen resmi, versi rilis, dan rantai lembar pengesahan yang melekat pada setiap dokumen:
                        </span>
                        <span class="badge bg-light text-primary border px-2 py-1">
                            <i class="bi bi-collection-fill me-1"></i>{{ dokumenPengesahanList.length }} Dokumen Terdaftar
                        </span>
                    </div>

                    <!-- Per-Document Executive Control Cards Deck -->
                    <div class="sop-gov-doc-sheets-deck d-flex flex-column gap-3">
                        <div
                            v-for="doc in dokumenPengesahanList"
                            :key="doc.id"
                            class="sop-gov-doc-sheet p-3.5 rounded-4 border bg-white"
                        >
                            <!-- Header: Title, Type, Nomor SOP, Versi, Completion -->
                            <div class="d-flex align-items-start justify-content-between gap-3 pb-2.5 mb-2.5 border-bottom flex-wrap">
                                <div class="d-flex align-items-start gap-2.5 min-w-0">
                                    <div class="sop-gov-matrix-idx mt-0.5">
                                        #{{ doc.index }}
                                    </div>
                                    <ItemTypeIcon :type="doc.tipe" class="flex-shrink-0 mt-0.5" />
                                    <div class="min-w-0">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <strong class="text-dark small text-truncate" :title="doc.judul">
                                                {{ doc.judul }}
                                            </strong>
                                            <span v-if="doc.wajib" class="badge bg-danger-subtle text-danger border border-danger-subtle fs-xs">Wajib</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2.5 flex-wrap fs-xs text-muted mt-0.5">
                                            <span><i class="bi bi-tag me-1"></i>{{ tipeLabel(doc.tipe) }}</span>
                                            <span v-if="doc.nomorSop" class="sop-gov-doc-no-badge">
                                                <i class="bi bi-file-earmark-ruled me-1 text-primary"></i>No. {{ doc.nomorSop }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
                                    <span
                                        class="sop-gov-matrix-ver-tag"
                                        :class="doc.keberlakuan.status.toLowerCase()"
                                        :title="doc.keberlakuan.alasan"
                                    >
                                        <i class="bi bi-git me-1"></i>v{{ doc.versi }}
                                    </span>
                                    <span
                                        class="badge px-2 py-1"
                                        :class="doc.isDone ? 'bg-success text-white' : 'bg-secondary text-white'"
                                    >
                                        <i :class="doc.isDone ? 'bi bi-check-circle-fill me-1' : 'bi bi-clock me-1'"></i>
                                        {{ doc.isDone ? 'Tuntas' : 'Belum' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Endorsement Sub-Grid per Dokumen -->
                            <div class="row g-2.5">
                                <!-- Pillar 1: Otoritas Pengesahan Dokumen -->
                                <div class="col-12 col-md-6">
                                    <div class="p-2.5 rounded-3 bg-light border h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-1.5 pb-1 border-bottom">
                                            <span class="fs-xs fw-bold text-uppercase text-muted" style="letter-spacing: 0.04em;">
                                                <i class="bi bi-patch-check-fill text-success me-1"></i>Lembar Pengesahan
                                            </span>
                                            <span v-if="doc.disahkanOleh" class="badge bg-success-subtle text-success fs-xs border border-success-subtle">Tersertifikasi</span>
                                            <span v-else class="badge bg-secondary-subtle text-secondary fs-xs">Standar Paket</span>
                                        </div>
                                        <div v-if="doc.disahkanOleh" class="d-flex flex-column gap-1 fs-xs">
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Pejabat Pengesah:</span>
                                                <strong class="text-dark">{{ doc.disahkanOleh }}</strong>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Jabatan:</span>
                                                <span class="text-secondary">{{ doc.jabatanPengesah }}</span>
                                            </div>
                                            <div v-if="doc.nomorSK" class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Nomor SK / Izin:</span>
                                                <code class="sop-code-badge">{{ doc.nomorSK }}</code>
                                            </div>
                                            <div v-if="doc.tanggalPengesahan" class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Tanggal Sah:</span>
                                                <span class="text-dark">{{ doc.tanggalPengesahan }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="fs-xs text-muted py-1">
                                            <em>Pengesahan dokumen ini menginduk pada otorisasi resmi paket dokumen.</em>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pillar 2: Penerbitan & Keberlakuan Mutu -->
                                <div class="col-12 col-md-6">
                                    <div class="p-2.5 rounded-3 bg-light border h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-1.5 pb-1 border-bottom">
                                            <span class="fs-xs fw-bold text-uppercase text-muted" style="letter-spacing: 0.04em;">
                                                <i class="bi bi-shield-lock-fill text-primary me-1"></i>Penerbitan &amp; Mutu
                                            </span>
                                            <span class="badge bg-indigo-subtle text-indigo fs-xs border border-indigo-subtle">Terkendali</span>
                                        </div>
                                        <div class="d-flex flex-column gap-1 fs-xs">
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Penerbit:</span>
                                                <strong class="text-dark">{{ doc.publisher || 'Administrator LMS' }}</strong>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Mulai Berlaku:</span>
                                                <span class="text-dark">{{ doc.tanggalBerlaku || 'Sejak Terbit' }}</span>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="text-muted">Status Mutu:</span>
                                                <span class="text-success font-weight-600">{{ doc.keberlakuan.alasan }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Jejak Kepatuhan & Audit -->
                <div v-else-if="paketInfoTab === 'audit'" class="d-flex flex-column gap-3">
                    <div class="sop-gov-audit-hero p-3.5 rounded-4 border">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="sop-gov-audit-badge" :class="{ ok: isComplete }">
                                    <i class="bi" :class="isComplete ? 'bi-patch-check-fill' : 'bi-hourglass-split'"></i>
                                </div>
                                <div>
                                    <div class="small text-muted text-uppercase fw-bold" style="letter-spacing: 0.04em;">Status Kepatuhan Karyawan</div>
                                    <h4 class="mb-0 fw-bold" :class="isComplete ? 'text-success' : 'text-primary'">
                                        {{ isComplete ? '100% Selesai &amp; Tercatat Resmi' : `${detail?.progress?.persen || 0}% Selesai (Sedang Berjalan)` }}
                                    </h4>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-light text-dark border p-2">
                                    <strong>{{ detail?.progress?.item_selesai || 0 }}</strong> / {{ detail?.progress?.total || 0 }} Langkah Wajib
                                </span>
                                <span v-if="completionQuiz.total > 0" class="badge bg-light text-dark border p-2">
                                    <i class="bi bi-patch-question-fill text-warning me-1"></i>
                                    Kuis: <strong>{{ completionQuiz.passed }}/{{ completionQuiz.total }}</strong> Lulus
                                </span>
                            </div>
                        </div>
                        <div class="sop-gov-progress-bar mt-3">
                            <div class="sop-gov-progress-fill" :style="{ width: `${detail?.progress?.persen || 0}%` }"></div>
                        </div>
                    </div>

                    <div class="sop-gov-pillar-card p-3.5 rounded-4 border">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <div class="sop-gov-pillar-icon indigo">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <strong class="text-dark small text-uppercase" style="letter-spacing: 0.04em;">Log Catatan &amp; Bukti Aktivitas</strong>
                        </div>
                        <dl class="legal-kv mb-0">
                            <template v-for="row in legalProofRows" :key="row.label">
                                <dt>{{ row.label }}</dt>
                                <dd>{{ row.value }}</dd>
                            </template>
                            <dt>Integritas Data</dt>
                            <dd><span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-lock-fill me-1"></i>Tervalidasi Sistem HCIS LMS</span></dd>
                        </dl>
                    </div>
                </div>

                <!-- Tab 4: Timeline Rilis & Sejarah Legalitas -->
                <div v-else-if="paketInfoTab === 'timeline'" class="d-flex flex-column gap-3">
                    <div class="small text-muted mb-1">Kronologi penerbitan, pengesahan, dan rilis versi paket dokumen:</div>
                    <div class="sop-gov-timeline-wrap p-3.5 rounded-4 border bg-white">
                        <ol class="legal-timeline mb-0">
                            <li
                                v-for="ev in legalTimeline"
                                :key="ev.tanggal + ev.judul"
                                :class="ev.tone"
                            >
                                <span class="legal-dot">
                                    <i class="bi" :class="ev.icon"></i>
                                </span>
                                <div>
                                    <time class="tnum">{{ fmtDateTime(ev.tanggal) }}</time>
                                    <strong>{{ ev.judul }}</strong>
                                    <small class="text-muted d-block mt-0.5">{{ ev.deskripsi }}</small>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-1.5 small text-muted">
                        <i class="bi bi-shield-check text-success"></i>
                        <span>Sistem Penjaminan Mutu &amp; Kepatuhan SOP HCIS · Dokumen Terkendali</span>
                    </div>
                    <HcisButton
                        label="Tutup Dialog"
                        variant="secondary"
                        size="sm"
                        @click="showPaketInfoModal = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElPopover } from 'element-plus';
import {
    HcisDialog,
    HcisBadge,
    HcisButton,
    HcisIcon,
    HcisAlert,
} from '@/components/ui';
import { sanitasiSop } from '@/utils/sopHtml';
import SopRing from '../../Shared/components/ui/SopRing.vue';
import ItemTypeIcon from '../../Shared/components/ui/ItemTypeIcon.vue';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import EmptyState from '../../Shared/components/ui/EmptyState.vue';
import SopKontenViewer from '../../Shared/components/ui/SopKontenViewer.vue';
import SopVersiTimeline from '../../Shared/components/ui/SopVersiTimeline.vue';
import SopCompletionCelebration from '../../Shared/components/ui/SopCompletionCelebration.vue';
import SopAbsensiGate from '../../Shared/components/ui/SopAbsensiGate.vue';
import { TIPE_ITEM, STATUS_ITEM, STATUS_PAKET, SOP_URL, temaAksen } from '../../Shared/support/constants.js';
import { metaPrioritas, setPrioritas } from '../../Shared/support/prioritas.js';
import '../../Shared/support/sopTheme.css';

/**
 * SOP Detail — "Controlled Document Reader" v3.
 * 2 kolom (rail kokpit + dokumen), scroll-spy TOC, data dokumen resmi penuh,
 * konteks penugasan/tenggat, progres per item. Reuse SopKontenViewer + SopVersiTimeline.
 */
export default {
    name: 'SopDetail',
    components: {
        ElPopover,
        HcisDialog,
        HcisBadge,
        HcisButton,
        HcisIcon,
        HcisAlert,
        SopRing,
        ItemTypeIcon,
        StatusPill,
        EmptyState,
        SopKontenViewer,
        SopVersiTimeline,
        SopCompletionCelebration,
        SopAbsensiGate,
    },
    // hashid paket dari route /sop/{id} (dikirim SopRepositoryController@show).
    // WAJIB ada: tanpa ini this.idPaket = undefined → fetch ke /api/v1/sop/undefined → 400.
    props: {
        idPaket: { type: String, required: true },
        prioritas: { type: Array, default: () => [] },
    },
    data() {
        return {
            detail: null,
            /* LAHIR DALAM KEADAAN MEMUAT.
             *
             * Halaman ini memang sedang memuat sejak detik ia ada — fetchDetail()
             * dipanggil di mounted() tanpa syarat. Memulainya dari false membuat
             * render PERTAMA jatuh ke cabang v-else, yaitu "Dokumen Tidak Ditemukan",
             * sampai mounted() sempat berjalan. Dan karena transisinya out-in,
             * layar salah itu harus selesai memudar dulu sebelum kerangkanya
             * muncul — jadi bukan kedipan sepersekian detik, melainkan pesan
             * yang benar-benar terbaca: setiap karyawan disambut "tidak
             * ditemukan" sebelum dokumennya terbuka.
             *
             * finally di fetchDetail() selalu mematikannya, termasuk saat gagal,
             * jadi tak ada jalan yang meninggalkannya menyala selamanya. */
            loading: true,
            /* Hashid item yang diminta lewat ?item=; dibaca sekali di created()
               lalu DILEPAS oleh sorotItemDiminta(). Lihat catatan di sana soal
               kenapa ia tidak boleh bertahan melewati pemuatan pertama. */
            itemDiminta: null,
            acting: false,
            confirmingItem: null,
            openingKonten: null,
            startingKuis: null,
            navigatingSaya: false,
            aboutOpen: false,
            tocOpen: false,
            expandedTocSecs: {},
            filter: 'ALL', // ALL | TODO | WAJIB
            railTab: 'toc', // toc | info | stats
            searchQuery: '',
            fontSizeLevel: 'md', // sm | md | lg
            isFocusMode: false,
            collapsedSections: {},
            activeSection: null,
            scrollPct: 0,
            openParagraf: null,
            celebrate: false,
            celebrationShown: false,
            openDoc: null,
            openVersi: null,
            showPaketInfoModal: false,
            openChangelogBanner: true,
            expandedOtoritas: false,
            paketInfoTab: 'identitas', // 'identitas' | 'dokumen' | 'audit'
            unduhing: null, // Id item FORM yang sedang disiapkan berkasnya
            unduhLampiranId: null, // Id form kosong yang sedang disiapkan
            unduhSemuaLoading: null, // Id item yang sedang mengunduh seluruh form kosong
            expandedLampiran: {}, // Status ekspansi list form kosong per item
            versiList: [],
            versiLoading: false,
            // versiId dicatat supaya tombol unduh di dalam penampil mengambil
            // versi yang SEDANG dilihat, bukan selalu versi aktif.
            viewer: {
                show: false,
                loading: false,
                konten: null,
                judul: '',
                tipe: 'DOKUMEN',
                status: 'BELUM',
                readonly: false,
                item: null,
                versiDibaca: null,
                versiId: null,
            },
            // Baris riwayat versi apa adanya — versiList sudah dipetakan untuk
            // timeline, sedangkan panel metadata butuh field tata kelolanya.
            versiRaw: [],
        };
    },
    computed: {
        backUrl() {
            return this.detail?.penugasan ? '/sop/saya' : '/sop/repository';
        },
        komposisiRingkas() {
            const items = this.detail?.items || [];
            if (!items.length) return '';
            const peta = {
                DOKUMEN: 'Dokumen',
                KUIS: 'Kuis',
                VIDEO: 'Video',
                FORM: 'Formulir',
                TAUTAN: 'Tautan',
                PARAGRAF: 'Modul',
            };
            const counts = {};
            for (const it of items) {
                const t = it.Tipe_Item || 'DOKUMEN';
                counts[t] = (counts[t] || 0) + 1;
            }
            const parts = Object.entries(counts).map(([t, n]) => `${n} ${peta[t] || t.toLowerCase()}`);
            return parts.join(' · ');
        },
        hasVersionUpdates() {
            // Banner peringatan HANYA muncul jika karyawan ditugaskan membaca ulang karena ada revisi baru setelah sebelumnya selesai
            return Boolean(
                this.detail?.banner_perubahan ||
                (this.detail?.items || []).some((i) => i.Perlu_Baca_Ulang)
            );
        },
        itemsWithChangelog() {
            return (this.detail?.items || []).filter((it) => it.Perlu_Baca_Ulang);
        },
        prio() {
            return metaPrioritas(this.detail?.paket?.Prioritas);
        },
        celebrateAccent() {
            return temaAksen(this.detail?.paket?.Nama_Paket || '').color || '#6366f1';
        },
        completionQuiz() {
            const quizzes = (this.detail?.items || []).filter(
                (it) => it.Tipe_Item === 'KUIS' && ['Y', 'T', 'N'].includes(it.Status_Lulus),
            );
            return {
                total: quizzes.length,
                passed: quizzes.filter((it) => it.Status_Lulus === 'Y').length,
                failed: quizzes.filter((it) => ['T', 'N'].includes(it.Status_Lulus)).length,
            };
        },
        heroStyle() {
            const t = temaAksen(this.detail?.paket?.Nama_Paket || '');
            return { background: `linear-gradient(135deg, ${t.color}, ${t.color}cc)` };
        },
        isComplete() {
            return this.detail && this.detail.progress.total > 0 && this.detail.progress.persen >= 100;
        },
        ownerLabel() {
            const p = this.detail?.paket;
            return p?.Owner_Nama || p?.Owner_Kode || null;
        },
        hasAbout() {
            const p = this.detail?.paket || {};
            return !!(p.Deskripsi || p.Tujuan_Ruang_Lingkup);
        },
        isControlled() {
            return (this.detail?.items || []).some((i) => i.Disahkan_Oleh || i.Nomor_Pengesahan);
        },
        officialDocItem() {
            return (this.detail?.items || []).find((i) => i.Disahkan_Oleh || i.Nomor_Pengesahan) || null;
        },
        orderedSections() {
            const secs = (this.detail?.sections || []).map((s) => ({
                key: s.Id_SOP_Section,
                Nama_Section: s.Nama_Section,
                Deskripsi: s.Deskripsi,
            }));
            const hasOrphan = (this.detail?.items || []).some((i) => !i.Id_SOP_Section);
            if (hasOrphan) secs.push({ key: '__none__', Nama_Section: 'Umum', Deskripsi: null });
            return secs;
        },
        firstUnfinished() {
            return (this.detail?.items || []).find((i) => !this.isDone(i) && !i.Terkunci) || null;
        },
        counts() {
            const items = this.detail?.items || [];
            return {
                all: items.length,
                todo: items.filter((i) => !this.isDone(i)).length,
                wajib: items.filter((i) => i.Wajib).length,
            };
        },
        railTabs() {
            return [
                { key: 'toc', label: 'Daftar Isi', icon: 'bi-list-nested' },
                { key: 'info', label: 'Legalitas', icon: 'bi-shield-check' },
                { key: 'stats', label: 'Progres', icon: 'bi-pie-chart' },
            ];
        },
        /** Indeks tab aktif — dipakai --seg-i untuk menggeser penanda. */
        railTabIndex() {
            const i = this.railTabs.findIndex((t) => t.key === this.railTab);
            return i < 0 ? 0 : i;
        },
        /**
         * Sebaran status SELURUH item (bukan hanya yang wajib, seperti
         * detail.progress) — supaya bar rincian di tab Progres menjumlah 100%.
         */
        statusBreakdown() {
            const items = this.detail?.items || [];
            const total = items.length;
            const selesai = items.filter((i) => this.isDone(i)).length;
            const berjalan = items.filter((i) => !this.isDone(i) && i.Status === 'DIBUKA').length;
            const belum = total - selesai - berjalan;
            const pct = (n) => (total ? (n / total) * 100 : 0);
            return {
                total,
                selesai,
                berjalan,
                belum,
                pctSelesai: pct(selesai),
                pctBerjalan: pct(berjalan),
                pctBelum: pct(belum),
            };
        },
        /** Nama section yang sedang dibaca — konteks untuk bar bawah di layar kecil. */
        activeSectionName() {
            const s = this.visibleSections.find((x) => x.key === this.activeSection);
            return s ? s.Nama_Section : '';
        },
        breakdownLabel() {
            const b = this.statusBreakdown;
            return `${b.selesai} selesai, ${b.berjalan} sedang dibuka, ${b.belum} belum dibuka, dari ${b.total} item`;
        },
        filters() {
            return [
                { key: 'ALL', label: 'Semua', icon: 'bi-list-ul', count: this.counts.all },
                { key: 'TODO', label: 'Belum selesai', icon: 'bi-circle', count: this.counts.todo },
                { key: 'WAJIB', label: 'Wajib', icon: 'bi-asterisk', count: this.counts.wajib },
            ];
        },
        shownCount() {
            return this.orderedSections.reduce((n, s) => n + this.itemsOf(s.key).length, 0);
        },
        /** Section yang masih punya item setelah saringan — dipakai spine & daftar isi. */
        visibleSections() {
            return this.orderedSections.filter((s) => this.itemsOf(s.key).length);
        },
        remainingMin() {
            const est = this.detail?.paket?.Estimasi_Waktu_Baca_Menit;
            if (!est || !this.detail?.progress?.total) return null;
            const left = 1 - this.detail.progress.persen / 100;
            const m = Math.round(est * left);
            return m > 0 ? m : null;
        },
        deadlineChip() {
            const pen = this.detail?.penugasan;
            if (!pen || !pen.Tenggat) return null;
            const d = pen.Hari_Tersisa;
            if (d == null) return null;
            if (d < 0) return { text: `Lewat ${Math.abs(d)} hari`, icon: 'bi-exclamation-octagon-fill', cls: 'bad' };
            if (d === 0) return { text: 'Jatuh tempo hari ini', icon: 'bi-clock-fill', cls: 'bad' };
            if (d <= 3) return { text: `${d} hari lagi`, icon: 'bi-clock-history', cls: 'warn' };
            return { text: `${d} hari lagi (${this.fmtDate(pen.Tenggat)})`, icon: 'bi-calendar-event', cls: 'idle' };
        },
        assignTitle() {
            const pen = this.detail?.penugasan;
            if (!pen) return '';
            if (pen.Is_Terlambat) return 'Melewati tenggat — segera selesaikan';
            if (pen.Tenggat) return 'Dokumen ini ditugaskan kepada Anda';
            return 'Dokumen ini ditugaskan (tanpa tenggat)';
        },
        statusPaketMeta() {
            const s = this.detail?.penugasan?.Status_Paket;
            return STATUS_PAKET[s] || { label: s || 'Belum', variant: 'idle' };
        },
        legal() {
            return this.detail?.legalitas || {};
        },
        legalPackage() {
            return this.legal.paket_resmi || {};
        },
        legalStatus() {
            return this.legal.status || {};
        },
        legalDocs() {
            return this.legal.dokumen_resmi || [];
        },
        legalItems() {
            return this.legal.dokumen || [];
        },
        legalUpdates() {
            return this.legal.update_dokumen || [];
        },
        legalTimeline() {
            return this.legal.timeline || [];
        },
        legalHeroClass() {
            return { complete: this.legalPackage.dokumen_disahkan > 0 };
        },
        legalDocumentStatusLabel() {
            if (this.legalPackage.dokumen_disahkan > 0) return 'Terkendali';
            if (this.legalPackage.status_paket === 'PUBLISHED') return 'Published';
            return this.legalPackage.status_paket || 'Tersedia';
        },
        legalPublisherLabel() {
            return this.legalPackage.publisher_utama || this.legalPackage.publisher_kode || null;
        },
        legalOwnerLabel() {
            return this.legalPackage.owner_nama || this.legalPackage.owner_kode || null;
        },
        legalPrimaryVersion() {
            if (this.legalPackage.versi_paket) {
                return { label: 'Versi Paket', value: `v${this.legalPackage.versi_paket}` };
            }
            if (this.legalPackage.versi_tertinggi) {
                return { label: 'Versi Dokumen', value: `v${this.legalPackage.versi_tertinggi}` };
            }
            return null;
        },
        legalCompletenessText() {
            const total = Number(this.legalPackage.dokumen_total || 0);
            if (!total) return '-';
            const official = Number(this.legalPackage.dokumen_disahkan || 0);
            const publisher = Number(this.legalPackage.dokumen_dengan_publisher || 0);
            if (official && publisher) return `${official}/${total} Disahkan`;
            if (publisher) return `${publisher}/${total} Diterbitkan`;
            return `${total} Item Aktif`;
        },
        legalStatusLabel() {
            if (this.isComplete) return 'Selesai';
            if (this.legalStatus.terlambat) return 'Lewat tenggat';
            if (this.detail?.penugasan) return this.statusPaketMeta.label || 'Berjalan';
            return 'Informasi publik';
        },
        legalHeroIcon() {
            if (this.legalDocs.length) return 'bi-shield-check';
            if (this.legalPackage.publisher_utama) return 'bi-patch-check-fill';
            return 'bi-journal-check';
        },
        legalHeroTitle() {
            if (this.legalDocs.length) return 'Dokumen Resmi & Terkendali';
            if (this.legalPackage.status_paket === 'PUBLISHED') return 'Paket Dokumen Resmi Terpublikasi';
            return 'Identitas & Legalitas Paket Dokumen';
        },
        legalHeroSub() {
            const publisher = this.legalPublisherLabel;
            const date = this.legalPackage.tanggal_publish ? this.fmtDate(this.legalPackage.tanggal_publish) : null;
            const owner = this.legalOwnerLabel;
            const parts = [];
            if (publisher) parts.push(`Diterbitkan oleh ${publisher}`);
            if (date) parts.push(`pada ${date}`);
            if (owner && owner !== publisher) parts.push(`(PIC: ${owner})`);
            if (!parts.length) return 'Informasi keabsahan dan keandalan dokumen tersedia resmi.';
            return parts.join(' ') + '.';
        },
        legalPackageRows() {
            const p = this.legalPackage;
            return [
                { label: 'Kode Paket', value: p.kode_paket || this.detail?.paket?.Kode_Paket },
                { label: 'Versi Rilis Paket', value: p.versi_paket ? `v${p.versi_paket}` : 'v1.0' },
                { label: 'Distribusi Akses', value: p.mode_distribusi === 'TERBATAS' ? 'Akses Terbatas' : 'Akses Publik' },
                { label: 'Alur Navigasi', value: this.detail?.paket?.Mode_Urutan === 'SEKUENSIAL' ? 'Sekuensial' : 'Bebas' },
                { label: 'Publisher', value: this.legalPublisherLabel },
                { label: 'Penanggung Jawab', value: this.legalOwnerLabel },
                { label: 'Tanggal Terbit', value: p.tanggal_publish ? this.fmtDate(p.tanggal_publish) : null },
                { label: 'Terakhir Diperbarui', value: p.updated_at ? this.fmtDate(p.updated_at) : null },
                { label: 'Presensi Kehadiran', value: this.detail?.paket?.Flag_Absensi === 'Y' ? 'Wajib Check-in' : null },
            ].filter((row) => row.value !== null && row.value !== undefined && row.value !== '');
        },
        legalProofRows() {
            const b = this.legal.bukti || {};
            return [
                { label: 'Karyawan', value: b.kode_karyawan || '-' },
                { label: 'Kode Paket', value: b.kode_paket || this.detail?.paket?.Kode_Paket || '-' },
                {
                    label: 'Ditugaskan',
                    value: b.tanggal_assign ? this.fmtDate(b.tanggal_assign) : 'Tidak ada penugasan',
                },
                {
                    label: 'Pertama dibuka',
                    value: b.pertama_dibuka ? this.fmtDateTime(b.pertama_dibuka) : 'Belum dibuka',
                },
                {
                    label: 'Konfirmasi terakhir',
                    value: b.konfirmasi_terakhir ? this.fmtDateTime(b.konfirmasi_terakhir) : 'Belum ada',
                },
                { label: 'Selesai', value: b.tanggal_selesai ? this.fmtDateTime(b.tanggal_selesai) : 'Belum selesai' },
            ];
        },
        otoritasPengesahanList() {
            const list = [];
            const seen = new Set();
            const items = this.detail?.items || [];

            // 1. Otoritas Pengesah Dokumen (Disahkan Oleh)
            items.forEach((it) => {
                const nama = it.Disahkan_Oleh;
                if (!nama) return;
                const key = `sah_${nama}_${it.Nomor_Pengesahan || ''}_${it.Jabatan_Pengesah || ''}`;
                if (!seen.has(key)) {
                    seen.add(key);
                    const docs = items.filter((x) => x.Disahkan_Oleh === nama).map((x) => x.Judul_Item);
                    list.push({
                        peran: 'Disahkan Oleh',
                        peranTipe: 'DISAHKAN',
                        peranBadge: 'emerald',
                        ikon: 'bi-patch-check-fill',
                        nama: nama,
                        jabatan: it.Jabatan_Pengesah || 'Pejabat Pengesah Dokumen',
                        nomorSK: it.Nomor_Pengesahan || null,
                        tanggal: it.Tanggal_Pengesahan ? this.fmtDate(it.Tanggal_Pengesahan) : null,
                        dokumenTerkait: docs,
                        dokumenCount: docs.length,
                    });
                }
            });

            // 2. Otoritas Penyetuju Tambahan (Jika ada field Disetujui_Oleh)
            items.forEach((it) => {
                if (it.Disetujui_Oleh && it.Disetujui_Oleh !== it.Disahkan_Oleh) {
                    const key = `setuju_${it.Disetujui_Oleh}`;
                    if (!seen.has(key)) {
                        seen.add(key);
                        list.push({
                            peran: 'Disetujui Oleh',
                            peranTipe: 'DISETUJUI',
                            peranBadge: 'blue',
                            ikon: 'bi-check-circle-fill',
                            nama: it.Disetujui_Oleh,
                            jabatan: it.Jabatan_Penyetuju || 'Manajemen Penyetuju',
                            nomorSK: null,
                            tanggal: null,
                            dokumenTerkait: [it.Judul_Item],
                            dokumenCount: 1,
                        });
                    }
                }
            });

            // 3. Otoritas Mengetahui (Jika ada field Diketahui_Oleh)
            items.forEach((it) => {
                if (it.Diketahui_Oleh) {
                    const key = `tahu_${it.Diketahui_Oleh}`;
                    if (!seen.has(key)) {
                        seen.add(key);
                        list.push({
                            peran: 'Diketahui Oleh',
                            peranTipe: 'MENGETAHUI',
                            peranBadge: 'purple',
                            ikon: 'bi-eye-fill',
                            nama: it.Diketahui_Oleh,
                            jabatan: it.Jabatan_Mengetahui || 'Pimpinan Unit Terkait',
                            nomorSK: null,
                            tanggal: null,
                            dokumenTerkait: [it.Judul_Item],
                            dokumenCount: 1,
                        });
                    }
                }
            });

            // 4. Publisher Dokumen Terkendali
            if (this.legalPublisherLabel && !seen.has(`pub_${this.legalPublisherLabel}`)) {
                seen.add(`pub_${this.legalPublisherLabel}`);
                list.push({
                    peran: 'Penerbit Dokumen',
                    peranTipe: 'PENERBIT',
                    peranBadge: 'indigo',
                    ikon: 'bi-person-badge-fill',
                    nama: this.legalPublisherLabel,
                    jabatan: 'Document Controller & Administrator LMS',
                    nomorSK: null,
                    tanggal: this.detail?.paket?.Tanggal_Publish ? this.fmtDate(this.detail.paket.Tanggal_Publish) : null,
                    dokumenTerkait: ['Seluruh Silabus Paket'],
                    dokumenCount: items.length,
                });
            }

            // 5. Penanggung Jawab Unit / Owner Prosedur
            if (this.ownerLabel && this.ownerLabel !== this.legalPublisherLabel && !seen.has(`owner_${this.ownerLabel}`)) {
                seen.add(`owner_${this.ownerLabel}`);
                list.push({
                    peran: 'Penyusun / PIC Unit',
                    peranTipe: 'PENYUSUN',
                    peranBadge: 'amber',
                    ikon: 'bi-pencil-square',
                    nama: this.ownerLabel,
                    jabatan: 'Unit Kerja Pemilik Proses',
                    nomorSK: null,
                    tanggal: null,
                    dokumenTerkait: ['Paket SOP Induk'],
                    dokumenCount: items.length,
                });
            }

            // Fallback jika belum ada pengesah terdaftar sama sekali
            if (list.length === 0) {
                list.push({
                    peran: 'Pengesahan Operasional',
                    peranTipe: 'PENGESAH',
                    peranBadge: 'emerald',
                    ikon: 'bi-patch-check-fill',
                    nama: 'Manajemen Operasional & Penjaminan Mutu',
                    jabatan: 'Sistem Terkendali HCIS LMS',
                    nomorSK: null,
                    tanggal: this.fmtDate(this.detail?.paket?.Tanggal_Publish) || null,
                    dokumenTerkait: ['Seluruh Dokumen'],
                    dokumenCount: items.length,
                });
            }

            return list;
        },
        dokumenPengesahanList() {
            return (this.detail?.items || []).map((it, idx) => {
                const hasLegal = !!(it.Disahkan_Oleh || it.Nomor_Pengesahan || it.Tanggal_Pengesahan || it.Publisher_Nama || it.Nomor_SOP_Resmi);
                return {
                    index: idx + 1,
                    id: it.Id_SOP_Paket_Item,
                    judul: it.Judul_Item,
                    tipe: it.Tipe_Item,
                    wajib: it.Wajib,
                    versi: it.Versi != null ? this.$vnum(it.Versi) : 1,
                    nomorSop: it.Nomor_SOP_Resmi || null,
                    disahkanOleh: it.Disahkan_Oleh || null,
                    jabatanPengesah: it.Jabatan_Pengesah || 'Pejabat Pengesah Dokumen',
                    nomorSK: it.Nomor_Pengesahan || null,
                    tanggalPengesahan: it.Tanggal_Pengesahan ? this.fmtDate(it.Tanggal_Pengesahan) : null,
                    publisher: it.Publisher_Nama || it.Kode_Karyawan_Publisher || this.legalPublisherLabel || null,
                    tanggalBerlaku: it.Tanggal_Berlaku ? this.fmtDate(it.Tanggal_Berlaku) : null,
                    keberlakuan: it.Keberlakuan || { status: 'BERLAKU', alasan: 'Versi resmi berlaku' },
                    isDone: this.isDone(it),
                    hasLegal,
                };
            });
        },
    },
    watch: {
        filter() {
            this.$nextTick(() => this.setupObserver());
        },
        searchQuery() {
            this.$nextTick(() => this.setupObserver());
        },
        tocOpen() {
            this.syncBodyLock();
        },
        celebrate() {
            this.syncBodyLock();
        },
    },
    created() {
        setPrioritas(this.prioritas);

        /* Dibaca di created, bukan di dalam fetchDetail: URL bisa berubah di
           tengah jalan (navigasi Inertia), dan yang dimaksud pengguna adalah
           item yang ia klik saat halaman ini dibuka. */
        try {
            this.itemDiminta = new URLSearchParams(window.location.search).get('item') || null;
        } catch (e) {
            this.itemDiminta = null;
        }
    },
    mounted() {
        this.fetchDetail();
        window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.setupObserver, { passive: true });
        window.addEventListener('keydown', this.onEsc);
        try {
            const err = new URLSearchParams(window.location.search).get('kuis_err');
            if (err) {
                ElMessage.error(err);
                const u = new URL(window.location.href);
                u.searchParams.delete('kuis_err');
                window.history.replaceState({}, '', u);
            }
        } catch (e) {
            /* noop */
        }
    },
    beforeUnmount() {
        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.setupObserver);
        window.removeEventListener('keydown', this.onEsc);
        if (this._spyRaf) cancelAnimationFrame(this._spyRaf);
        if (typeof document !== 'undefined') document.body.style.overflow = '';
    },
    methods: {
        syncBodyLock() {
            if (typeof document !== 'undefined') {
                document.body.style.overflow = this.tocOpen || this.celebrate ? 'hidden' : '';
            }
        },
        printPage() {
            window.print();
        },
        itemProgressPct(it) {
            if (!it) return 0;
            if (this.isDone(it)) return 100;
            if (it.Total_Halaman && it.Total_Halaman > 0) {
                const h = it.Halaman_Terakhir || 0;
                return Math.min(100, Math.round((h / it.Total_Halaman) * 100));
            }
            if (it.Tipe_Item === 'VIDEO' && it.Durasi_Detik) {
                return Math.min(100, Math.round((it.Durasi_Detik / 120) * 100));
            }
            return 0;
        },
        itemProgressText(it) {
            if (!it) return '';
            if (it.Tipe_Item === 'VIDEO') {
                if (this.isDone(it)) return 'Video tuntas ditonton (100%)';
                if (it.Durasi_Detik) return `Ditonton ${this.fmtDurasi(it.Durasi_Detik)}`;
                return 'Belum ditonton';
            }
            // PDF / Dokumen
            if (this.isDone(it)) {
                if (it.Total_Halaman) return `Tuntas ${it.Total_Halaman}/${it.Total_Halaman} Halaman (100%)`;
                return 'Tuntas dibaca (100%)';
            }
            if (it.Total_Halaman && it.Total_Halaman > 0) {
                const h = it.Halaman_Terakhir || 0;
                const pct = Math.round((h / it.Total_Halaman) * 100);
                return `Dibaca: Halaman ${h} dari ${it.Total_Halaman} (${pct}%)`;
            }
            if (it.Halaman_Terakhir && it.Halaman_Terakhir > 0) {
                return `Dibaca sampai Halaman ${it.Halaman_Terakhir}`;
            }
            if (it.Waktu_Buka) return 'Sedang dibaca';
            return 'Belum dibaca';
        },
        async shareSop() {
            const url = window.location.href;
            if (navigator.share) {
                try {
                    await navigator.share({ title: this.detail?.paket?.Nama_Paket || 'Paket Dokumen', url: url });
                    return;
                } catch (e) {
                    /* fallback */
                }
            }
            if (navigator.clipboard) {
                try {
                    await navigator.clipboard.writeText(url);
                    ElMessage.success('Tautan dokumen berhasil disalin ke papan klip!');
                } catch (e) {
                    ElMessage.info('Tautan dokumen: ' + url);
                }
            }
        },
        toggleSectionCollapse(key) {
            this.collapsedSections[key] = !this.collapsedSections[key];
        },
        isSectionCollapsed(key) {
            return !!this.collapsedSections[key];
        },
        setFontScale(level) {
            this.fontSizeLevel = level;
        },
        onEsc(e) {
            if (e.key !== 'Escape') return;
            if (this.celebrate) this.celebrate = false;
            else if (this.tocOpen) this.tocOpen = false;
        },
        tipeLabel(t) {
            return (TIPE_ITEM[t] || {}).label || t;
        },
        legalItemStatusLabel(doc) {
            if (doc.Status_Lulus === 'Y') return 'Lulus';
            if (['T', 'N'].includes(doc.Status_Lulus)) return 'Belum lulus';
            if (['SELESAI', 'DIKONFIRMASI'].includes(doc.Status)) return 'Selesai';
            if (doc.Status === 'DIBUKA') return 'Dibuka';
            return 'Belum';
        },
        legalItemStatusClass(doc) {
            if (doc.Status_Lulus === 'Y' || ['SELESAI', 'DIKONFIRMASI'].includes(doc.Status)) return 'ok';
            if (['T', 'N'].includes(doc.Status_Lulus)) return 'bad';
            if (doc.Status === 'DIBUKA') return 'work';
            return 'idle';
        },
        /** Panah kiri/kanan/atas/bawah berpindah tab, sesuai pola tablist WAI-ARIA. */
        onRailTabKey(e) {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
            if (!step) return;
            e.preventDefault();
            const n = this.railTabs.length;
            const next = (this.railTabIndex + step + n) % n;
            this.railTab = this.railTabs[next].key;
            this.$nextTick(() => {
                const btns = this.$refs.railTabBtn;
                if (btns && btns[next]) btns[next].focus();
            });
        },
        statusMeta(s) {
            return STATUS_ITEM[s] || { label: s, variant: 'idle' };
        },
        isDone(it) {
            return ['SELESAI', 'DIKONFIRMASI'].includes(it.Status);
        },
        isCurrent(it) {
            return this.firstUnfinished && this.firstUnfinished.Id_SOP_Paket_Item === it.Id_SOP_Paket_Item;
        },
        rawItemsOf(key) {
            return (this.detail?.items || []).filter((i) => (i.Id_SOP_Section || '__none__') === key);
        },
        itemsOf(key) {
            let list = this.rawItemsOf(key);
            if (this.filter === 'TODO') list = list.filter((i) => !this.isDone(i));
            else if (this.filter === 'WAJIB') list = list.filter((i) => i.Wajib);
            if (this.searchQuery && this.searchQuery.trim()) {
                const q = this.searchQuery.trim().toLowerCase();
                list = list.filter(
                    (i) =>
                        (i.Judul_Item || '').toLowerCase().includes(q) ||
                        (i.Deskripsi_Item || '').toLowerCase().includes(q) ||
                        (i.Konten_Teks || '').toLowerCase().includes(q) ||
                        (i.Konten_Nama || '').toLowerCase().includes(q),
                );
            }
            return list;
        },
        sectionProgress(key) {
            const items = this.rawItemsOf(key).filter((i) => i.Wajib);
            return { done: items.filter((i) => this.isDone(i)).length, total: items.length };
        },
        sectionPct(key) {
            const p = this.sectionProgress(key);
            return p.total ? Math.round((p.done / p.total) * 100) : 0;
        },
        secDone(key) {
            const p = this.sectionProgress(key);
            return p.total > 0 && p.done >= p.total;
        },
        itemIcon(t) {
            const icons = {
                PARAGRAF: 'bi-text-paragraph',
                DOKUMEN: 'bi-file-earmark-text-fill',
                KUIS: 'bi-patch-question-fill',
                FORM: 'bi-file-earmark-check-fill',
                VIDEO: 'bi-play-btn-fill',
                TAUTAN: 'bi-link-45deg',
                ABSENSI: 'bi-camera-fill',
            };
            return icons[String(t || '').toUpperCase()] || 'bi-file-earmark';
        },
        canDownload(it) {
            // Keputusan server (SopAksesBerkas::bolehUnduh); Mode_Akses hanya cadangan.
            if (typeof it.Boleh_Unduh === 'boolean') return it.Boleh_Unduh;
            return it.Mode_Akses === 'VIEW_UNDUH';
        },
        // FORM tak ada di sini: ia punya blok sendiri (unduh), bukan tombol "buka".
        ctaLabel(t) {
            return { DOKUMEN: 'Baca dokumen', VIDEO: 'Tonton video' }[t] || 'Buka';
        },
        ctaIcon(t) {
            return { DOKUMEN: 'bi-book', VIDEO: 'bi-play-circle' }[t] || 'bi-arrow-right';
        },
        /** Ikon berkas — dibaca dari mime DAN nama berkas, karena Konten_Tipe kerap kosong. */
        kontenIcon(t, nama) {
            const s = (String(t || '') + ' ' + String(nama || '')).toLowerCase();
            if (s.includes('video') || /\.(mp4|mov|mkv|webm)$/.test(s)) return 'bi-play-btn';
            if (s.includes('pdf')) return 'bi-file-earmark-pdf';
            if (/(spreadsheet|excel|csv|\.xls|\.xlsx|\.csv)/.test(s)) return 'bi-file-earmark-spreadsheet';
            if (/(wordprocessing|msword|\.doc|\.docx|\.rtf)/.test(s)) return 'bi-file-earmark-word';
            if (/(presentation|powerpoint|\.ppt)/.test(s)) return 'bi-file-earmark-slides';
            if (/(zip|rar|7z)/.test(s)) return 'bi-file-earmark-zip';
            return 'bi-file-earmark';
        },
        urlKuis(it) {
            return SOP_URL.checkpoint(this.idPaket, it.Id_SOP_Paket_Item);
        },
        /**
         * Jatah percobaan sudah terpakai habis → hasilnya FINAL.
         * Bukan "kesempatan habis": nilainya sudah keluar, tak ada yang hilang.
         * Item yang sudah lulus tidak dihitung final di sini — ia punya
         * penanda "Lulus" sendiri dan tombolnya memang tak perlu muncul.
         */
        /*
         * Kuis ini sudah selesai urusannya — tak ada lagi yang bisa dikerjakan.
         *
         * CERMIN PERSIS dari penjagaan di SopKuisDispatchService::launch(), yang
         * menolak dengan 409 pada dua keadaan: item sudah SELESAI/DIKONFIRMASI,
         * atau jatah percobaan habis. Layar ini WAJIB menjawab sama; kalau tidak,
         * ia menawarkan tombol yang server pasti tolak — dan penolakan yang tak
         * bisa dijelaskan lebih merusak kepercayaan daripada tombol yang memang
         * tak ada.
         *
         * Dulu ada syarat ketiga: `&& Status_Lulus !== 'Y'`. Maksudnya "yang
         * gagal dan kehabisan jatah sudah final" — tapi efeknya justru
         * MEMBATALKAN kefinalan bagi yang LULUS, keadaan yang paling final dari
         * semuanya. Peserta yang lulus disodori "Ulangi Ujian", menekannya, dan
         * dibalas 409. Lulus selalu menutup item (SopCompletionService:
         * $hasilFinal = $lulus || $percobaanFinalGagal → Status = SELESAI), jadi
         * cabang pertama di bawah sudah menangkapnya.
         */
        kuisFinal(it) {
            if (this.isDone(it)) return true;

            const maks = Number(it.Maks_Percobaan || 0);
            return maks > 0 && Number(it.Jumlah_Percobaan || 0) >= maks;
        },
        /** Sisa kesempatan; null bila tak berbatas atau belum pernah mencoba. */
        sisaPercobaan(it) {
            const maks = Number(it.Maks_Percobaan || 0);
            if (!maks || !it.Jumlah_Percobaan) return null;
            return Math.max(0, maks - Number(it.Jumlah_Percobaan));
        },
        hasDocInfo(it) {
            if (!it) return false;
            return !!(
                it.Disahkan_Oleh ||
                it.Nomor_Pengesahan ||
                it.Tanggal_Berlaku ||
                it.Ada_Riwayat ||
                it.Id_Item_Versi_Aktif ||
                it.Versi_Ke ||
                ['DOKUMEN', 'FORM', 'VIDEO'].includes(it.Tipe_Item)
            );
        },
        paragrafHtml(html) {
            return sanitasiSop(html);
        },

        /* format */
        fmtDate(d) {
            if (!d) return '';
            const dt = new Date(d);
            if (isNaN(dt.getTime())) return d;
            return dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        fmtDateTime(d) {
            if (!d) return '';
            const dt = new Date(String(d).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return d;
            return (
                dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) +
                ' ' +
                dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            );
        },
        fmtDurasi(s) {
            if (!s) return '';
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return m > 0 ? `${m} mnt${sec ? ' ' + sec + ' dtk' : ''}` : `${sec} dtk`;
        },
        /* Ukuran_File datang dalam byte dari SopProgressReadService. Kembalikan
           string kosong untuk nilai yang tak masuk akal, bukan "0 B": baris
           pemakainya sudah ber-v-if, jadi tak ada yang perlu ditulis. */
        fmtFileSize(b) {
            const n = Number(b);
            if (!Number.isFinite(n) || n <= 0) return '';
            if (n < 1024) return `${n} B`;

            const satuan = ['KB', 'MB', 'GB'];
            let v = n;
            let i = -1;
            while (v >= 1024 && i < satuan.length - 1) {
                v /= 1024;
                i++;
            }

            return `${v.toFixed(v < 10 ? 1 : 0)} ${satuan[i]}`;
        },

        async fetchDetail(silent = false) {
            if (!silent) this.loading = true;
            try {
                const r = await axios.get(`/api/v1/sop/${this.idPaket}`);
                if (r.data.success) {
                    this.detail = r.data.result;
                    if (this.isComplete) this.maybeCelebrate();
                }
            } catch (e) {
                if (!silent) ElMessage.error(e.response?.data?.message || 'Gagal memuat dokumen');
            } finally {
                if (!silent) {
                    this.loading = false;
                    this.$nextTick(() => {
                        this.setupObserver();
                        this.sorotItemDiminta();
                    });
                }
            }
        },

        /**
         * Sorot item yang diminta lewat `?item=` — jalur "Per Dokumen" di Functional Document.
         *
         * Halaman Functional Document bisa menampilkan daftar DOKUMEN, dan mengklik salah
         * satunya membawa orang ke paket yang memuatnya. Tanpa sorotan ini ia
         * mendarat di puncak paket berisi belasan item dan harus mencari lagi
         * dokumen yang baru saja ia klik — persis pekerjaan yang tautan itu
         * dimaksudkan untuk menghapus.
         *
         * TIGA HAL YANG DISENGAJA:
         *
         * 1. Parameter yang tak ditemukan TIDAK menghasilkan galat apa pun.
         *    Item bisa saja sudah dicabut, atau id-nya karangan. Halaman
         *    paketnya sendiri baik-baik saja, dan sebuah pesan merah di atasnya
         *    hanya akan membuat orang mengira dokumennya yang bermasalah. Ia
         *    kembali ke perilaku baku: gulir ke item pertama yang belum tuntas.
         * 2. Dipakai SEKALI lalu dilepas. fetchDetail() dipanggil lagi setiap
         *    kali progres berubah; tanpa pelepasan ini, setiap konfirmasi akan
         *    melempar layar kembali ke item yang dulu diklik dari halaman lain.
         * 3. Lewat clickTocItem(), bukan focusItem() langsung — section yang
         *    terlipat harus dibuka dulu, kalau tidak elemennya memang tak ada
         *    di DOM dan gulirnya diam-diam tak terjadi.
         */
        sorotItemDiminta() {
            const diminta = this.itemDiminta;
            this.itemDiminta = null;

            if (!diminta) {
                this.scrollToActiveItem();

                return;
            }

            const it = (this.detail?.items || []).find((x) => x.Id_SOP_Paket_Item === diminta);
            if (!it) {
                this.scrollToActiveItem();

                return;
            }

            this.clickTocItem(it);
        },

        scrollToActiveItem() {
            const it = this.firstUnfinished;
            if (!it) return;
            this.$nextTick(() => {
                setTimeout(() => {
                    const el = document.getElementById('item-' + it.Id_SOP_Paket_Item);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 350);
            });
        },

        /* scroll-spy + progress */
        setupObserver() {
            this._secEls = Array.from(document.querySelectorAll('.flow-sec'));
            this.pickActiveSection();
        },
        /**
         * Sorot section yang sedang dibaca. IntersectionObserver sebelumnya memakai
         * urutan entri callback, sehingga saat menggulir ke atas section yang aktif
         * kerap salah. Di sini dipilih section terdekat DI ATAS garis baca.
         */
        pickActiveSection() {
            const els = this._secEls || [];
            if (!els.length) return;
            const line = window.innerHeight * 0.3;
            let best = null,
                bestD = Infinity;
            for (const el of els) {
                const top = el.getBoundingClientRect().top;
                // yang sudah dilewati diprioritaskan; yang masih di bawah diberi penalti
                const d = top <= line ? line - top : (top - line) * 4;
                if (d < bestD) {
                    bestD = d;
                    best = el;
                }
            }
            if (best) this.activeSection = best.id.replace('sec-', '');
        },
        onScroll() {
            const h = document.documentElement;
            const max = h.scrollHeight - h.clientHeight;
            this.scrollPct = max > 0 ? Math.min(100, (h.scrollTop / max) * 100) : 0;
            if (this._spyRaf) return;
            this._spyRaf = requestAnimationFrame(() => {
                this._spyRaf = null;
                this.pickActiveSection();
            });
        },
        scrollToSection(key) {
            // Rail = bottom sheet di layar kecil; memilih bagian berarti sheet-nya
            // harus menutup, kalau tidak tujuannya ketutupan sheet itu sendiri.
            this.tocOpen = false;
            // Bagian yang dituju boleh saja sedang dilipat — buka dulu, kalau tidak
            // yang tampil cuma judulnya dan pembaca merasa tak terjadi apa-apa.
            const dibuka = this.isSectionCollapsed(key);
            if (dibuka) this.collapsedSections[key] = false;
            this.activeSection = key;
            // Kalau baru dibentangkan, tunggu transisi max-height selesai dulu:
            // mengukur posisi di tengah animasi menghasilkan target yang meleset.
            this.afterExpand(dibuka, () => this.scrollToEl(document.getElementById('sec-' + key)));
        },
        /**
         * Aksi utama halaman: bukan cuma menggulir, tapi langsung membuka item
         * berikutnya sesuai tipenya (satu ketukan, bukan dua).
         */
        lanjutkan() {
            const it = this.firstUnfinished;
            if (!it) return;
            this.tocOpen = false; // bila dipicu dari bottom sheet

            // --- Bereskan dulu apa pun yang bisa menyembunyikan kartu tujuan ---
            // (a) saringan aktif
            if (this.filter === 'WAJIB' && !it.Wajib) this.filter = 'ALL';
            // (b) section yang sedang dilipat — tanpa ini elemen #item-… TIDAK ADA
            //     di DOM sama sekali, jadi scroll & sorotnya diam-diam gagal.
            const secKey = it.Id_SOP_Section || '__none__';
            const dibuka = this.isSectionCollapsed(secKey);
            if (dibuka) this.collapsedSections[secKey] = false;
            this.activeSection = secKey; // daftar isi ikut menyorot bagian itu

            // DOKUMEN = item yang isinya memang dokumen itu sendiri → langsung
            // masuk PDF reader, tak ada gunanya berhenti di kartunya.
            // PARAGRAF → teksnya dibentangkan di tempat (isi item ya teks itu).
            // FORM / VIDEO / TAUTAN / KUIS → cukup diantar ke kartunya: aksinya
            // mengunduh, pindah halaman, atau buka popup, jadi biar pembaca yang
            // menekan sendiri.
            this.afterExpand(dibuka, () => {
                this.focusItem(it);
                if (it.Tipe_Item === 'DOKUMEN' && it.Id_Item_Versi_Aktif) {
                    this.openKonten(it, it.Id_Item_Versi_Aktif, false);
                } else if (it.Tipe_Item === 'PARAGRAF' && this.openParagraf !== it.Id_SOP_Paket_Item) {
                    this.toggleParagraf(it);
                }
            });
        },
        /**
         * Jalankan cb setelah DOM siap. Bila section baru saja dibentangkan,
         * tunggu transisi .sop-expand (350ms) tuntas — mengukur posisi saat
         * max-height masih beranimasi bikin target scroll meleset.
         */
        afterExpand(baruDibuka, cb) {
            if (baruDibuka) setTimeout(cb, 380);
            else this.$nextTick(cb);
        },
        /**
         * Gulir halaman ke sebuah elemen.
         *
         * Sengaja memakai window.scrollTo, BUKAN el.scrollIntoView().
         * scrollIntoView menggulir SEMUA leluhur yang bisa di-scroll, termasuk
         * pembungkus ber-`overflow: hidden` milik transisi lipat section
         * (.sop-expand-*). Akibatnya kartu "melompat" ke dalam wadah itu dan
         * halamannya sendiri nyaris tak bergerak — terlihat seperti scroll yang
         * tidak mulus. Menggulir window secara eksplisit menghilangkan ambiguitas.
         */
        scrollToEl(el) {
            if (!el) return;
            const off = window.innerWidth < 992 ? 72 : 90; // ruang untuk header lengket
            const top = el.getBoundingClientRect().top + window.scrollY - off;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        },
        focusItem(it) {
            const el = document.getElementById('item-' + it.Id_SOP_Paket_Item);
            if (!el) return;
            this.scrollToEl(el);
            // animasi .flash didefinisikan pada .ritem (kartu), bukan pembungkus .flow-item
            const card = el.querySelector('.ritem') || el;
            card.classList.add('flash');
            setTimeout(() => card.classList.remove('flash'), 1400);
        },
        toggleTocSection(key) {
            this.expandedTocSecs = {
                ...this.expandedTocSecs,
                [key]: !this.expandedTocSecs[key],
            };
            this.scrollToSection(key);
        },
        clickTocItem(it) {
            this.tocOpen = false;
            const secKey = it.Id_SOP_Section || '__none__';
            const dibuka = this.isSectionCollapsed(secKey);
            if (dibuka) this.collapsedSections[secKey] = false;
            this.activeSection = secKey;
            this.afterExpand(dibuka, () => {
                this.focusItem(it);
            });
        },

        /* progress helpers */
        async buka(it) {
            if (!it || !it.Id_SOP_Paket_Item || it.Id_SOP_Paket_Item === 'undefined') return;
            try {
                await axios.post('/api/v1/sop/progress/buka', { id_item: it.Id_SOP_Paket_Item });
            } catch (e) {
                /* silent */
            }
        },
        async markSelesai(it) {
            if (!it || !it.Id_SOP_Paket_Item || it.Id_SOP_Paket_Item === 'undefined') return;
            if (this.confirmingItem === it.Id_SOP_Paket_Item) return;

            this.confirmingItem = it.Id_SOP_Paket_Item;
            const wasComplete = this.isComplete;
            try {
                await axios.post('/api/v1/sop/progress/konfirmasi', { id_item: it.Id_SOP_Paket_Item, persen: 100 });
                await this.fetchDetail(true);
                if (wasComplete || !this.isComplete) ElMessage.success('Progres berhasil diperbarui');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengonfirmasi');
            } finally {
                this.confirmingItem = null;
            }
        },
        maybeCelebrate() {
            if (!this.isComplete || this.celebrationShown) return;
            this.celebrationShown = true;
            this.celebrate = true;
        },
        openCelebration() {
            this.celebrationShown = true;
            this.celebrate = true;
        },
        reviewCompletion() {
            this.celebrate = false;
            this.railTab = 'stats';
            if (window.innerWidth < 992) this.tocOpen = true;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },
        gotoSaya() {
            window.location.href = this.backUrl;
        },
        toggleParagraf(it) {
            if (!it || !it.Id_SOP_Paket_Item) return;
            if (this.openParagraf === it.Id_SOP_Paket_Item) {
                this.openParagraf = null;
                return;
            }
            this.openParagraf = it.Id_SOP_Paket_Item;
            if (!this.isDone(it)) this.buka(it);
        },
        onOpenTautan(it) {
            if (it && it.Id_SOP_Paket_Item && !this.isDone(it)) this.buka(it);
        },
        onStartKuis(it, ev) {
            if (!it || !it.Id_SOP_Paket_Item) return;
            if (this.startingKuis === it.Id_SOP_Paket_Item) {
                if (ev) ev.preventDefault();
                return;
            }
            this.startingKuis = it.Id_SOP_Paket_Item;
            if (!this.isDone(it)) this.buka(it);
        },

        /**
         * FORM = unduh, bukan baca.
         * Berkas formulir bisa .xlsx/.csv/.docx — tak ada penampil web yang
         * bisa dipercaya untuk itu, jadi berkasnya langsung diambil. URL
         * bertanda tangan dari server cuma hidup 15 menit, maka diminta saat
         * tombol ditekan, bukan disiapkan di muka.
         * @param readonly true bila dari riwayat versi → jangan catat progres.
         */
        async unduhForm(it, versiId, readonly = false) {
            if (!versiId || versiId === 'undefined' || versiId === 'null') {
                // Bukan cuma formulir lagi: dokumen ber-Mode_Akses VIEW_UNDUH
                // memakai jalur yang sama sejak tombol unduhnya dipasang di
                // pembaca aman.
                ElMessage.warning('Berkas belum tersedia');
                return;
            }
            if (this.unduhing) return;
            this.unduhing = it.Id_SOP_Paket_Item;
            try {
                // unduh=1 → server menandatangani URL dengan Content-Disposition: attachment.
                const r = await axios.get(`/api/v1/sop/${this.idPaket}/versi/${versiId}/konten?unduh=1`);
                const url = r.data.success ? r.data.result?.url : null;
                if (!url) {
                    ElMessage.error(r.data.message || 'Gagal menyiapkan berkas');
                    return;
                }
                /* Tetap dicatat "dibuka" walau tanpa penampil — jejak kepatuhan
                   jalan terus.

                   DITUNGGU SAMPAI SELESAI, bukan ditembakkan lalu ditinggal.
                   Baris di bawah memulai NAVIGASI TINGKAT ATAS, dan permintaan
                   yang masih terbang saat halaman mulai dilepas akan dibatalkan
                   peramban. Yang hilang bukan sekadar satu baris log: inilah
                   satu-satunya penanda bahwa karyawan pernah membuka dokumen
                   itu, dan tanpa penampil tak ada peristiwa kedua yang menyusul
                   untuk menutupinya. Penjaga 419 global pun butuh halaman yang
                   masih hidup untuk menyegarkan token lalu mengulang. */
                if (!readonly && !this.isDone(it)) await this.buka(it);
                // Navigasi di tab ini, bukan target=_blank: karena responsnya attachment,
                // browser mengunduh tanpa memindahkan halaman — dan tak meninggalkan
                // tab kosong seperti kalau dibuka di jendela baru.
                window.location.href = url;
                ElMessage.success('Berkas formulir sedang diunduh');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyiapkan berkas');
            } finally {
                this.unduhing = null;
            }
        },

        /* Form kosong item FORM. Sengaja TIDAK memanggil buka(): yang dicatat
           sebagai "dibuka" adalah berkas utamanya (contoh pengisian); progres
           item FORM tetap ditentukan "Tandai selesai". */
        async unduhLampiran(it, l) {
            if (this.unduhLampiranId) return;
            this.unduhLampiranId = l.Id_Lampiran;
            try {
                const r = await axios.get(
                    `/api/v1/sop/${this.idPaket}/versi/${it.Id_Item_Versi_Aktif}/lampiran/${l.Id_Lampiran}`,
                );
                const url = r.data.success ? r.data.result?.url : null;
                if (!url) {
                    ElMessage.error(r.data.message || 'Gagal menyiapkan form kosong');
                    return;
                }
                window.location.href = url;
                ElMessage.success('Form kosong sedang diunduh');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyiapkan form kosong');
            } finally {
                this.unduhLampiranId = null;
            }
        },

        /* Unduh seluruh lampiran form kosong secara berurutan */
        /* Unduh Semua = SATU ZIP dari server (6 Okt 2026). Dulu satu tab per
           berkas, dan browser memblokir semuanya kecuali yang pertama. */
        async unduhSemuaLampiran(it) {
            if (this.unduhSemuaLoading === it.Id_SOP_Paket_Item) return;
            if (!(it.Lampiran || []).length || !it.Id_Item_Versi_Aktif) return;

            this.unduhSemuaLoading = it.Id_SOP_Paket_Item;
            try {
                const r = await axios.get(
                    `/api/v1/sop/${this.idPaket}/versi/${it.Id_Item_Versi_Aktif}/lampiran-zip`,
                    { responseType: 'blob' },
                );
                const cd = r.headers?.['content-disposition'] || '';
                const nama =
                    (cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i) || [])[1] ||
                    `Form kosong - ${it.Judul_Item || 'SOP'}.zip`;
                const url = URL.createObjectURL(r.data);
                const a = document.createElement('a');
                a.href = url;
                a.download = decodeURIComponent(nama);
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                ElMessage.success('Semua form kosong sedang diunduh (ZIP)');
            } catch (e) {
                // Respons galat datang sebagai Blob karena responseType di atas.
                let pesan = '';
                try {
                    pesan = JSON.parse(await e.response?.data?.text?.())?.message || '';
                } catch (_) {
                    pesan = '';
                }
                ElMessage.error(pesan || 'Gagal menyiapkan unduhan form kosong');
            } finally {
                this.unduhSemuaLoading = null;
            }
        },

        toggleLampiranExpand(itemId) {
            this.expandedLampiran = {
                ...this.expandedLampiran,
                [itemId]: !this.expandedLampiran[itemId],
            };
        },
        isLampiranExpanded(itemId) {
            return !!this.expandedLampiran[itemId];
        },
        displayedLampiran(it) {
            if (!it.Lampiran || !it.Lampiran.length) return [];
            if (it.Lampiran.length <= 4 || this.isLampiranExpanded(it.Id_SOP_Paket_Item)) {
                return it.Lampiran;
            }
            return it.Lampiran.slice(0, 4);
        },
        lampiranIcoClass(ext) {
            const e = String(ext || '').toLowerCase().trim();
            if (['xlsx', 'xls', 'csv'].includes(e)) return 'ico-excel';
            if (['docx', 'doc', 'rtf'].includes(e)) return 'ico-word';
            if (['pdf'].includes(e)) return 'ico-pdf';
            if (['zip', 'rar', '7z'].includes(e)) return 'ico-zip';
            if (['png', 'jpg', 'jpeg', 'webp'].includes(e)) return 'ico-image';
            return 'ico-default';
        },

        /* viewer */
        async openKonten(it, versiId, readonly) {
            if (!versiId || versiId === 'undefined' || versiId === 'null') {
                ElMessage.warning('Konten belum tersedia');
                return;
            }
            this.openingKonten = it.Id_SOP_Paket_Item;
            // Versi lama dikenali dari identitas versinya, bukan dari flag readonly:
            // panel metadata harus menjelaskan versi yang BENAR-BENAR dibuka.
            const versiLama = !!it.Id_Item_Versi_Aktif && versiId !== it.Id_Item_Versi_Aktif;
            const versiDibaca = versiLama ? this.versiRaw.find((v) => v.Id_SOP_Item_Versi === versiId) || null : null;
            this.viewer = {
                show: true,
                loading: true,
                konten: null,
                judul: it.Konten_Nama || it.Judul_Item,
                tipe: it.Tipe_Item,
                status: it.Status,
                readonly: !!readonly,
                item: it,
                versiDibaca,
                versiId: versiId,
            };
            if (!readonly && !this.isDone(it)) this.buka(it);
            try {
                const r = await axios.get(`/api/v1/sop/${this.idPaket}/versi/${versiId}/konten`);
                if (r.data.success) this.viewer.konten = r.data.result;
                else ElMessage.error(r.data.message || 'Gagal memuat konten');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat konten');
            } finally {
                this.viewer.loading = false;
                this.openingKonten = null;
            }
        },
        /** Unduh dari dalam penampil — versi yang sedang dilihat, bukan versi aktif. */
        unduhDariViewer() {
            const it = this.viewer.item;
            if (!it) return;
            this.unduhForm(it, this.viewer.versiId || it.Id_Item_Versi_Aktif, this.viewer.readonly);
        },
        async completeFromViewer() {
            const it = this.viewer.item;
            if (!it || !it.Id_SOP_Paket_Item) return;
            await this.markSelesai(it);
            this.viewer.show = false;
        },
        handleViewerItemSelect(it) {
            if (!it) return;
            const itemId = it.Id_SOP_Paket_Item;
            // FORM dinilai dari Bisa_Pratinjau, bukan Konten_Tipe: kolom itu
            // bernilai 'document' untuk PDF MAUPUN .xlsx, jadi memakainya di sini
            // akan menyeret berkas spreadsheet ke dalam iframe dan menampilkan
            // layar kosong.
            const isMediaCapable =
                ['DOKUMEN', 'VIDEO'].includes(it.Tipe_Item) || (it.Tipe_Item === 'FORM' && !!it.Bisa_Pratinjau);

            if (isMediaCapable) {
                // If clicked item is Video or PDF document: switch media directly inside viewer with loading state!
                const versiId = it.Id_Item_Versi_Aktif || it.Id_SOP_Item_Versi;
                if (versiId) {
                    this.openKonten(it, versiId, this.viewer.readonly);
                } else {
                    ElMessage.warning('Media untuk item ini belum tersedia');
                }
            } else {
                // Non-media items (Text, Checklist, Quiz, etc.): Close viewer and smooth scroll to item on detail page!
                this.viewer.show = false;
                this.$nextTick(() => {
                    setTimeout(() => {
                        const el = document.getElementById('item-' + itemId);
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            el.classList.add('item-nav-highlight');
                            setTimeout(() => el.classList.remove('item-nav-highlight'), 2200);
                        }
                    }, 250);
                });
            }
        },

        /* dokumen & versi */
        toggleDoc(it) {
            if (!it || !it.Id_SOP_Paket_Item) return;
            this.openDoc = this.openDoc === it.Id_SOP_Paket_Item ? null : it.Id_SOP_Paket_Item;
        },
        async toggleVersi(it) {
            if (!it || !it.Id_SOP_Paket_Item || it.Id_SOP_Paket_Item === 'undefined') return;
            if (this.openVersi === it.Id_SOP_Paket_Item) {
                this.openVersi = null;
                return;
            }
            this.openVersi = it.Id_SOP_Paket_Item;
            this.versiList = [];
            this.versiLoading = true;
            try {
                const r = await axios.get(`/api/v1/sop/${this.idPaket}/item/${it.Id_SOP_Paket_Item}/versi`);
                const rows = r.data.success ? r.data.result : [];
                this.versiRaw = rows;
                this.versiList = rows.map((v) => ({
                    id: v.Id_SOP_Item_Versi,
                    versi: v.Versi_Ke,
                    aktif: v.Is_Aktif,
                    baca: v.Status_Baca,
                    changelog: v.Ringkasan_Perubahan,
                    tanggal: v.Tanggal_Publish || v.Tanggal_Berlaku,
                    kontenNama: v.Konten_Nama,
                    kontenTipe: v.Konten_Tipe,
                    /* Isi hanya dari versi yang berlaku. Bisa_Dibuka datang dari
                       server, bukan diturunkan dari v.aktif di sini: aturannya
                       ditegakkan endpoint konten, dan tombol yang menawarkan
                       lebih dari yang diizinkan endpoint hanya melahirkan galat
                       yang tak bisa dijelaskan ke pemakainya. */
                    canView: !!v.Bisa_Dibuka && !!v.Konten_Nama,
                    pengesah: v.Disahkan_Oleh,
                    tanggalSah: v.Tanggal_Pengesahan,
                    nomorSah: v.Nomor_Pengesahan,
                    // Menentukan versi ini dilihat atau diunduh — lihat viewVersi().
                    bisaPratinjau: !!v.Bisa_Pratinjau,
                    unduh: it.Tipe_Item === 'FORM' && !v.Bisa_Pratinjau,
                }));
            } catch (e) {
                ElMessage.error('Gagal memuat riwayat versi');
            } finally {
                this.versiLoading = false;
            }
        },
        /*
         * Versi lama FORM mengikuti aturan yang sama dengan versi aktifnya:
         * PDF dilihat, sisanya diunduh. Kalau tidak, sebuah formulir PDF bisa
         * dilihat pada v3 tapi memaksa unduh pada v2 — beda perlakuan yang tak
         * bisa dijelaskan ke pemakainya.
         */
        viewVersi(it, v) {
            if (it.Tipe_Item === 'FORM' && !v.bisaPratinjau) this.unduhForm(it, v.id, true);
            else this.openKonten(it, v.id, true);
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.rd-muted {
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.fs-xs {
    font-size: 0.72rem;
}

/* Top Command / Action Bar */
.sop-topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}
.sop-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.sop-search-input-wrap .bi-search {
    position: absolute;
    left: 10px;
    color: var(--hcis-slate-400);
    font-size: 0.8rem;
    pointer-events: none;
}
.sop-top-search-field {
    padding-left: 2rem !important;
    padding-right: 1.8rem !important;
    border-radius: 10px !important;
    font-size: 0.8rem !important;
    border: 1px solid var(--border, #dbe1ea) !important;
    background: rgba(248, 250, 252, 0.8) !important;
    width: 200px;
    transition: all 0.2s ease;
}
.sop-top-search-field:focus {
    width: 240px;
    background: #fff !important;
    border-color: var(--hcis-indigo-500) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}
.sop-clear-search-btn {
    position: absolute;
    right: 6px;
    border: 0;
    background: transparent;
    color: var(--hcis-slate-400);
    padding: 0;
    cursor: pointer;
    font-size: 1rem;
}
.sop-clear-search-btn:hover {
    color: var(--hcis-slate-500);
}

.sop-font-scale-group .btn-icon-xs {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.15rem 0.45rem;
    line-height: 1.2;
    border-radius: 6px;
    color: var(--hcis-slate-500);
    border: 0;
    background: transparent;
}
.sop-font-scale-group .btn-icon-xs:hover {
    color: var(--hcis-indigo-600);
    background: var(--hcis-slate-200);
}
.sop-font-scale-group .btn-icon-xs.active {
    background: #fff;
    color: var(--hcis-indigo-600);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.btn-white {
    background: #fff !important;
}
.btn-icon-xs {
    padding: 0.2rem 0.45rem !important;
    font-size: 0.75rem !important;
}

.sop-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--hcis-slate-600);
    font-size: 0.84rem;
    font-weight: 700;
    text-decoration: none;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    background: rgba(241, 245, 249, 0.7);
    transition: all 0.2s ease;
}
.sop-back-btn:hover {
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
}
.sop-breadcrumb-divider {
    color: var(--hcis-slate-300);
    font-weight: 600;
}
.sop-breadcrumbs {
    font-size: 0.82rem;
}
.sop-bc-sub {
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.sop-bc-active {
    color: var(--hcis-slate-900);
    font-weight: 700;
}
.sop-top-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: #fff;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 9px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sop-top-action:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.04);
}
.sop-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.78rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    border: 1px solid var(--border, #dbe1ea);
}

/* ===== FILTER CHIPS BAR (FLOATING GLASS PILLS) ===== */
.rd-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem;
    background: var(--hcis-slate-100);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 999px;
    /* margin-bottom: 1.25rem; */
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.04);
}
.rd-fchip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    background: transparent;
    border: 0;
    border-radius: 999px;
    padding: 0.42rem 0.95rem;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.22, 1, 0.36, 1);
    outline: none;
}
.rd-fchip:hover {
    color: var(--hcis-slate-700);
    background: rgba(255, 255, 255, 0.7);
}
.rd-fchip.on {
    background: var(--hcis-indigo-500) !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.45) !important;
}
.rd-fcount {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.1rem 0.48rem;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}
.rd-fchip.on .rd-fcount {
    background: rgba(255, 255, 255, 0.25) !important;
    color: var(--hcis-white) !important;
}

/* Glassmorphism helpers */
.card-glass {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 4px 18px -4px rgba(15, 23, 42, 0.05);
}
.badge-pill-subtle {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: rgba(241, 245, 249, 0.9);
    border-radius: 999px;
    border: 1px solid var(--border, #dbe1ea);
}

/* Pulse animation for action CTA */
.pulse-glow {
    position: relative;
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.5);
    animation: pulseGlow 2.2s infinite;
}
@keyframes pulseGlow {
    0% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.5);
    }
    70% {
        box-shadow: 0 0 0 12px rgba(99, 102, 241, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
    }
}

/* Scroll progress top line */
.rd-scrollbar {
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    z-index: 70;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6, var(--hcis-pink-500));
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.7);
    transition: width 0.1s linear;
}

.reader-shell {
    width: 100%;
    padding-bottom: 4rem;
}

/* ===== RAIL (LEFT SIDEBAR) ===== */
.rd-rail {
    position: sticky;
    top: 82px !important;
    max-height: calc(100vh - 96px) !important;
    padding: 1.15rem 1.05rem;
    gap: 0.95rem;
    overflow-y: auto;
    overscroll-behavior: contain;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: var(--radius-xl, 24px);
    box-shadow: 0 8px 30px -10px rgba(15, 23, 42, 0.08);
}
.rd-rail::-webkit-scrollbar {
    width: 5px;
}
.rd-rail::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.16);
    border-radius: 6px;
}
.rd-rail::-webkit-scrollbar-track {
    background: transparent;
}

.rd-rail-header {
    display: flex;
    align-items: center;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--hcis-slate-500);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}
/* ---- Pengalih tab (segmented control) ----
   Menempel di puncak rail yang bisa digulir, jadi tab tetap terjangkau
   walau daftar isinya panjang. ::before menutup celah padding rail di
   atasnya supaya konten tak terlihat menyelinap di balik tab. */
.rail-seg {
    position: sticky;
    top: 0;
    z-index: 3;
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    padding: 4px;
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    flex-shrink: 0;
}
.rail-seg::before {
    content: '';
    position: absolute;
    left: -1.05rem;
    right: -1.05rem;
    top: -1.2rem;
    height: 1.2rem;
    background: rgba(255, 255, 255, 0.96);
}
.rail-seg-thumb {
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 4px;
    width: calc((100% - 8px) / var(--seg-n, 3));
    transform: translateX(calc(var(--seg-i, 0) * 100%));
    background: var(--hcis-white);
    border-radius: 10px;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.07),
        0 4px 12px -6px rgba(15, 23, 42, 0.28);
    transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}
.rail-seg-btn {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    min-width: 0;
    padding: 0.42rem 0.2rem;
    border: 0;
    background: transparent;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: color 0.2s ease;
}
.rail-seg-btn i {
    font-size: 0.86rem;
    line-height: 1;
}
.rail-seg-btn:hover {
    color: var(--hcis-slate-600);
}
.rail-seg-btn.on {
    color: var(--hcis-indigo-600);
}
.rail-seg-btn:focus-visible {
    outline: 2px solid rgba(99, 102, 241, 0.55);
    outline-offset: 1px;
}

.rail-panel {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* ---- Keluarga kartu rail: satu bahasa untuk ketiga tab ---- */
.rail-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.95rem 1rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.rail-cap {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-slate-400);
}
.rail-cap-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.rail-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin-top: 0.35rem;
    line-height: 1.3;
}
.rail-sub {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    margin-top: 0.25rem;
    line-height: 1.5;
}
.rail-mono {
    margin-top: 0.6rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.72rem;
    color: var(--hcis-slate-600);
    background: var(--surface-muted, var(--hcis-slate-50));
    border: 1px solid var(--border-soft, #e7ecf3);
    border-radius: 8px;
    padding: 0.3rem 0.5rem;
}
.rail-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    white-space: nowrap;
}
.rail-pill.soft {
    color: var(--hcis-slate-600);
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
}
.rail-pill.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.28);
}
.rail-pill.bad {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.28);
}

.rail-gauge {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.45rem;
}
.rail-gauge-sub {
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
}
.rail-gauge-sub .tnum {
    font-weight: 800;
    color: var(--hcis-slate-800);
}

/* ---- Tab Progres ---- */
.rail-stat {
    text-align: center;
}
.rail-stat-big {
    font-size: 2.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-indigo-600);
    letter-spacing: -0.02em;
    margin-top: 0.3rem;
}
.rail-stat-big span {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--hcis-indigo-300);
    margin-left: 2px;
}
.rail-split {
    display: flex;
    height: 8px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--surface-soft, var(--hcis-slate-100));
    margin-top: 0.85rem;
}
.rail-split i {
    display: block;
    height: 100%;
    transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.rail-split .s-ok {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}
.rail-split .s-go {
    background: linear-gradient(90deg, var(--hcis-amber-500), var(--hcis-amber-400));
}
.rail-split .s-no {
    background: var(--hcis-slate-200);
}
.rail-legend {
    list-style: none;
    margin: 0.7rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.rail-legend li {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
}
.rail-legend b {
    margin-left: auto;
    color: var(--hcis-slate-800);
    font-weight: 800;
}
.rail-legend .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.rail-legend .dot.ok {
    background: var(--hcis-emerald-500);
}
.rail-legend .dot.go {
    background: var(--hcis-amber-500);
}
.rail-legend .dot.no {
    background: var(--hcis-slate-300);
}

.rail-kv {
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    overflow: hidden;
}
.rail-kv > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.6rem 0.85rem;
    font-size: 0.78rem;
}
.rail-kv > div + div {
    border-top: 1px solid var(--border-soft, #e7ecf3);
}
.rail-kv span {
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.rail-kv b {
    color: var(--hcis-slate-800);
    font-weight: 800;
}

.rail-seal-ttl {
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    letter-spacing: -0.01em;
}
.rail-seal-sub {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
}

.legal-hero {
    display: flex;
    gap: 0.8rem;
    align-items: flex-start;
    padding: 0.95rem 1rem;
    border-radius: 16px;
    color: #0f766e;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.13), rgba(14, 165, 233, 0.08));
    border: 1px solid rgba(20, 184, 166, 0.25);
}
.legal-hero.complete {
    color: var(--hcis-emerald-700);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.16), rgba(34, 197, 94, 0.08));
    border-color: rgba(16, 185, 129, 0.28);
}
.legal-hero.late {
    color: var(--hcis-red-700);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.14), rgba(245, 158, 11, 0.08));
    border-color: rgba(239, 68, 68, 0.25);
}
.legal-hero-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 6px 16px -10px rgba(15, 23, 42, 0.4);
    font-size: 1.25rem;
}
.legal-eyebrow {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    opacity: 0.78;
}
.legal-title {
    margin-top: 0.12rem;
    color: var(--hcis-slate-900);
    font-size: 0.94rem;
    font-weight: 850;
    line-height: 1.28;
}
.legal-sub {
    margin-top: 0.22rem;
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    line-height: 1.45;
}
.legal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}
.legal-metric {
    min-width: 0;
    padding: 0.65rem 0.75rem;
    border-radius: 14px;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
}
.legal-metric span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
}
.legal-metric strong {
    display: block;
    margin-top: 0.18rem;
    color: var(--hcis-slate-800);
    font-size: 0.82rem;
    font-weight: 850;
    overflow-wrap: anywhere;
}
.legal-kv {
    display: grid;
    grid-template-columns: minmax(92px, auto) 1fr;
    gap: 0.42rem 0.7rem;
    margin: 0.55rem 0 0;
}
.legal-kv dt {
    color: var(--hcis-slate-400);
    font-size: 0.72rem;
    font-weight: 700;
}
.legal-kv dd {
    margin: 0;
    color: var(--hcis-slate-800);
    font-size: 0.74rem;
    font-weight: 800;
    text-align: right;
    overflow-wrap: anywhere;
}
.legal-approver {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-top: 0.6rem;
}
.legal-approver-seal {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 8px 18px -8px rgba(16, 185, 129, 0.85);
}
.legal-doc-list,
.legal-timeline {
    list-style: none;
    padding: 0;
    margin: 0;
}
.legal-doc-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin-top: 0.8rem;
}
.legal-doc-list li,
.legal-update,
.legal-item-row {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    min-width: 0;
}
.legal-doc-list li > i,
.legal-update > i,
.legal-item-row > i {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 8px;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
}
.legal-doc-list b,
.legal-update strong,
.legal-item-row strong {
    display: block;
    color: var(--hcis-slate-800);
    font-size: 0.78rem;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.legal-doc-list small,
.legal-update small,
.legal-item-row small {
    display: block;
    margin-top: 0.12rem;
    color: var(--hcis-slate-500);
    font-size: 0.69rem;
    line-height: 1.35;
}
.legal-item-list,
.legal-update-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-top: 0.65rem;
}
.legal-item-row {
    align-items: center;
    padding: 0.55rem 0;
    border-top: 1px solid var(--border-soft, #e7ecf3);
}
.legal-item-row:first-child {
    border-top: 0;
    padding-top: 0;
}
.legal-item-status {
    margin-left: auto;
    flex: 0 0 auto;
    border-radius: 999px;
    padding: 0.18rem 0.5rem;
    font-size: 0.62rem;
    font-weight: 850;
}
.legal-item-status.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
}
.legal-item-status.bad {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.12);
}
.legal-item-status.work {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.14);
}
.legal-item-status.idle {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
}
.legal-timeline-card {
    overflow: hidden;
}
.legal-timeline {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.75rem;
}
.legal-timeline li {
    position: relative;
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    gap: 0.58rem;
}
.legal-dot {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    font-size: 0.78rem;
}
.legal-timeline li.ok .legal-dot {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
}
.legal-timeline li.warn .legal-dot {
    color: var(--hcis-amber-700);
    background: #fff7ed;
}
.legal-timeline time {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 0.66rem;
    font-weight: 800;
}
.legal-timeline strong {
    display: block;
    margin-top: 0.08rem;
    color: var(--hcis-slate-800);
    font-size: 0.78rem;
    line-height: 1.25;
}
.legal-timeline small {
    display: block;
    margin-top: 0.12rem;
    color: var(--hcis-slate-500);
    font-size: 0.7rem;
    line-height: 1.38;
}

.rd-rail-deadline {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 14px;
    padding: 0.6rem 0.8rem;
    font-weight: 700;
    border: 1px solid transparent;
}
.rd-rail-deadline.idle {
    background: rgba(241, 245, 249, 0.8);
    color: var(--hcis-slate-600);
    border-color: var(--border, #dbe1ea);
}
.rd-rail-deadline.warn {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-700);
    border-color: rgba(245, 158, 11, 0.25);
}
.rd-rail-deadline.bad {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-700);
    border-color: rgba(239, 68, 68, 0.25);
}
.rd-rail-deadline .bi {
    font-size: 1.2rem;
}
.rd-dl-lbl {
    display: block;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.8;
}
.rd-dl-txt {
    display: block;
    font-size: 0.82rem;
}

.rd-toc {
    border-top: 1px solid var(--border-soft, #eef2f7);
    padding-top: 0.8rem;
}
.rd-toc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-slate-400);
    padding: 0 0.3rem 0.5rem;
}
.rd-toc-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    padding: 0.6rem 0.75rem;
    border-radius: 12px;
    text-align: left;
    transition: all 0.2s ease;
}
.rd-toc-item:hover {
    background: var(--hcis-slate-50);
    border-color: var(--border, #dbe1ea);
}
.rd-toc-item.active {
    background: rgba(99, 102, 241, 0.08) !important;
    border-color: rgba(99, 102, 241, 0.25) !important;
    border-left: 3px solid var(--hcis-indigo-500) !important;
}
.rd-toc-no {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 800;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    flex-shrink: 0;
}
.rd-toc-item.active .rd-toc-no {
    background: var(--hcis-indigo-500);
    color: var(--hcis-white);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.35);
}
.rd-toc-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.rd-toc-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-toc-item.active .rd-toc-name {
    color: var(--hcis-indigo-600);
}
.rd-toc-prog {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    flex-shrink: 0;
}
.rd-toc-bar {
    display: block;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    overflow: hidden;
}
.rd-toc-bar i {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
    transition: width 0.45s ease;
}
.rd-toc-item.done .rd-toc-no {
    background: var(--hcis-emerald-500);
    color: #fff;
}
.rd-toc-item.done .rd-toc-bar i {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}
.rd-toc-item.done .rd-toc-prog {
    color: var(--hcis-emerald-600);
}

.rd-toc-sec-group {
    border-radius: 14px;
    margin-bottom: 0.35rem;
    transition: all 0.2s ease;
}
.rd-toc-arrow {
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
    transition: transform 0.2s ease;
}
.rd-toc-sublist {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.4rem 0.2rem 0.35rem 0.65rem;
    border-left: 2px dashed rgba(99, 102, 241, 0.25);
    margin-left: 1.15rem;
    margin-top: 0.25rem;
    margin-bottom: 0.4rem;
}
.rd-toc-subitem {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: 8px;
    padding: 0.38rem 0.6rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;
}
.rd-toc-subitem:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
    transform: translateX(2px);
}
.rd-toc-subitem.done {
    color: var(--hcis-emerald-600);
}
.rd-toc-subitem.current {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    font-weight: 700;
}
.rd-toc-subico {
    font-size: 0.85rem;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.rd-toc-subttl {
    flex: 1;
    min-width: 0;
    font-weight: 600;
    line-height: 1.3;
}
.rd-toc-subbadge {
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
    background: var(--surface-soft, var(--hcis-slate-100));
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    flex-shrink: 0;
}

.rd-rail-meta {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.45rem 0.6rem;
    margin: 0;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-soft, #e7ecf3);
}
.rd-rail-meta dt {
    font-size: 0.73rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.rd-rail-meta dd {
    font-size: 0.78rem;
    color: var(--hcis-slate-700);
    font-weight: 700;
    margin: 0;
    text-align: right;
}

/* ===== HERO ===== */
.rd-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.rd-hero {
    position: relative;
    border-radius: var(--radius-xl, 24px);
    overflow: hidden;
    min-height: 270px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 20px 44px -20px rgba(15, 23, 42, 0.4);
}
.rd-hero-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform-origin: center;
}
.rd-kenburns {
    animation: kenburns 24s ease-in-out infinite alternate;
}
@keyframes kenburns {
    0% {
        transform: scale(1.04);
    }
    100% {
        transform: scale(1.15) translate(-1%, -1%);
    }
}
.rd-hero-mesh {
    position: absolute;
    inset: 0;
    mix-blend-mode: overlay;
    pointer-events: none;
    background:
        radial-gradient(450px 240px at 88% 8%, rgba(139, 92, 246, 0.45), transparent 60%),
        radial-gradient(350px 240px at 8% 92%, rgba(99, 102, 241, 0.4), transparent 60%);
}
.rd-hero-shade {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        rgba(15, 23, 42, 0.35) 0%,
        rgba(15, 23, 42, 0.45) 45%,
        rgba(15, 23, 42, 0.88) 100%
    );
}

.rd-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--hcis-white);
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    padding: 0.38rem 0.85rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.2s ease;
}
.rd-hero-btn:hover {
    color: var(--hcis-white);
    background: rgba(255, 255, 255, 0.35);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}
.rd-hero-bc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.95);
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    padding: 0.32rem 0.85rem;
}
.rd-hero-body {
    position: relative;
    width: 100%;
    padding: 1.6rem 1.6rem 1.1rem;
}
.rd-hero-eyebrow {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}
.rd-kode {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #fff;
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 8px;
    padding: 0.18rem 0.55rem;
}
.rd-ver-pill {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #fff;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.5) 0%, rgba(79, 70, 229, 0.6) 100%);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(199, 210, 254, 0.5);
    border-radius: 8px;
    padding: 0.18rem 0.58rem;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}
.rd-dist-pill {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    border-radius: 8px;
    padding: 0.18rem 0.58rem;
    backdrop-filter: blur(8px);
}
.rd-dist-pill.public {
    color: var(--hcis-emerald-50);
    background: rgba(16, 185, 129, 0.45);
    border: 1px solid rgba(110, 231, 183, 0.45);
}
.rd-dist-pill.limited {
    color: var(--hcis-amber-50);
    background: rgba(245, 158, 11, 0.45);
    border: 1px solid rgba(253, 230, 138, 0.45);
}
.rd-hero-btn--highlight {
    background: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.55) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.4);
}
.rd-hero-btn--highlight:hover {
    background: rgba(255, 255, 255, 0.45) !important;
    border-color: var(--hcis-white) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
}

/* Version Changelog Banner (Ultra-Cool Enterprise Glass Card) */
.rd-version-banner {
    background: linear-gradient(135deg, rgba(240, 245, 255, 0.95) 0%, rgba(248, 250, 252, 0.98) 50%, rgba(243, 240, 255, 0.92) 100%);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(165, 180, 252, 0.55);
    border-radius: 20px;
    box-shadow:
        0 4px 20px -2px rgba(99, 102, 241, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    overflow: hidden;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-version-banner:hover {
    border-color: var(--hcis-indigo-400);
    box-shadow:
        0 8px 26px -4px rgba(99, 102, 241, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}
.rd-ver-banner-head {
    border-bottom: 0;
}
.rd-ver-banner-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: var(--hcis-white);
    font-size: 1.3rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}
.rd-ver-banner-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-indigo-950);
    letter-spacing: -0.01em;
}
.rd-ver-counter-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 100%);
    padding: 0.18rem 0.6rem;
    border-radius: 999px;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}
.rd-ver-banner-desc {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    margin-top: 2px;
}
.rd-ver-banner-toggle {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    transition: all 0.15s ease;
}
.rd-ver-banner-toggle:hover {
    color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-400);
    background: var(--hcis-indigo-50);
    transform: translateY(-1px);
}
.rd-ver-change-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 0.85rem;
}
.rd-ver-change-item {
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-ver-change-item:hover {
    border-color: var(--hcis-indigo-400);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px -2px rgba(99, 102, 241, 0.16);
    background: var(--hcis-white);
}
.rd-ver-tag-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-indigo-700);
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid var(--hcis-indigo-200);
    padding: 0.12rem 0.5rem;
    border-radius: 6px;
}

/* Item Card Version Tags & Seal Pill */
.ri-ver-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.14rem 0.48rem;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.ri-ver-tag.berlaku {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.ri-ver-tag.belum_berlaku {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}
.ri-ver-tag.versi_lama {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-300);
}
.ri-seal-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.14rem 0.48rem;
    border-radius: 6px;
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border: 1px solid var(--hcis-emerald-200);
}
.ri-title {
    color: #fff;
    font-weight: 800;
    font-size: 1.85rem;
    margin: 0 0 0.2rem;
    line-height: 1.18;
    max-width: 42ch;
    text-shadow: 0 2px 18px rgba(0, 0, 0, 0.5);
    letter-spacing: -0.02em;
}
.rd-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-top: 0.7rem;
}
.rd-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    padding: 0.28rem 0.7rem;
}
.rd-chip.prio-bad {
    background: rgba(239, 68, 68, 0.7);
    border-color: rgba(255, 255, 255, 0.4);
}
.rd-chip.prio-warn {
    background: rgba(245, 158, 11, 0.65);
    border-color: rgba(255, 255, 255, 0.4);
}
.rd-chip-seal {
    background: rgba(16, 185, 129, 0.7);
    border-color: rgba(255, 255, 255, 0.4);
}
.rd-hero-ring {
    position: absolute;
    top: 1.2rem;
    right: 1.4rem;
}

/* spec bar kaca */
.rd-spec {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    background: rgba(15, 23, 42, 0.45) !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.18) !important;
}
.rd-spec-cell {
    flex: 1;
    min-width: 130px;
    padding: 0.65rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.rd-spec-cell + .rd-spec-cell {
    border-left: 1px solid rgba(255, 255, 255, 0.15);
}
.rd-spec-lbl {
    font-size: 0.64rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.78);
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
}
.rd-spec-val {
    font-size: 0.85rem;
    font-weight: 800;
    color: #fff;
}

/* ===== BANNERS ===== */
.rd-assign {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0.02));
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 16px;
    padding: 0.9rem 1.1rem;
    box-shadow: 0 4px 14px -4px rgba(99, 102, 241, 0.1);
}
.rd-assign.late {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.02));
    border-color: rgba(239, 68, 68, 0.28);
}
.rd-assign-main {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    min-width: 0;
}
.rd-assign-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-500);
    font-size: 1.3rem;
    flex-shrink: 0;
}
.rd-assign.late .rd-assign-icon {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-500);
}
.rd-assign-title {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.92rem;
}
.rd-assign-sub {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    margin-top: 1px;
}

.rd-banner {
    display: flex;
    gap: 0.8rem;
    align-items: center;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(6, 182, 212, 0.03));
    border: 1px solid rgba(6, 182, 212, 0.28);
    border-radius: 16px;
    padding: 0.9rem 1.1rem;
    color: var(--hcis-cyan-700);
}

.rd-gate {
    padding: 1.1rem 1.2rem;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.09), rgba(245, 158, 11, 0.03));
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.rd-gate-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.16);
    border-radius: 999px;
    padding: 0.26rem 0.65rem;
}
.rd-gate-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    margin-top: 0.85rem;
}
.rd-gate-ico {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 1.45rem;
    color: var(--hcis-amber-500);
    background: rgba(245, 158, 11, 0.14);
    flex-shrink: 0;
}
.rd-gate h5 {
    font-weight: 800;
    color: var(--hcis-slate-800);
    margin: 0;
    font-size: 1.02rem;
}
.rd-gate-note {
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
    margin: 0.65rem 0 0;
}

/* ===== ABOUT ===== */
.rd-about {
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}
.rd-about-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 0.85rem 1.1rem;
    font-weight: 800;
    color: var(--hcis-slate-700);
    font-size: 0.9rem;
}
.rd-about-body {
    padding: 0 1.1rem 1.1rem;
}
.rd-desc {
    font-size: 0.92rem;
    color: var(--hcis-slate-600);
    line-height: 1.65;
    margin: 0;
}
.rd-scope {
    background: var(--surface-muted, var(--hcis-slate-50));
    border: 1px solid var(--border-soft, #eef2f7);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    margin-top: 0.8rem;
}
.rd-scope-lbl {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-indigo-500);
    margin-bottom: 0.35rem;
}
.rd-scope p {
    margin: 0;
    font-size: 0.88rem;
    color: var(--hcis-slate-600);
    line-height: 1.6;
}
/* ===== READING JOURNEY SPINE ===== */
.rd-flow {
    position: relative;
    margin-top: 0.4rem;
}
.rd-flow::before {
    content: '';
    position: absolute;
    left: 19px;
    top: 22px;
    bottom: 28px;
    width: 3px;
    background: linear-gradient(180deg, var(--hcis-indigo-500) 0%, var(--hcis-slate-300) 100%);
    border-radius: 3px;
    z-index: 0;
}

/* Terminal End-Cap Node on Spine Line */
.rd-flow-terminal {
    position: relative;
    padding-left: 56px;
    display: flex;
    align-items: center;
}
.rd-terminal-node {
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 0.95rem;
    z-index: 2;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45);
    transition: all 0.3s ease;
}
.rd-terminal-node.done {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45);
}
.rd-terminal-ripple {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(99, 102, 241, 0.35);
    animation: rdRipple 2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
}
.rd-terminal-node.done .rd-terminal-ripple {
    border-color: rgba(16, 185, 129, 0.35);
}
@keyframes rdRipple {
    0% {
        transform: scale(0.9);
        opacity: 0.8;
    }
    100% {
        transform: scale(1.45);
        opacity: 0;
    }
}

.rd-terminal-card {
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.75rem 1.1rem;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    transition: all 0.25s ease;
}
.rd-terminal-card.done {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.06), rgba(16, 185, 129, 0.02));
    border-color: rgba(16, 185, 129, 0.3);
}
.rd-terminal-title {
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--hcis-slate-900);
    display: flex;
    align-items: center;
}

/* Smooth Accordion Section Transition */
.sop-expand-enter-active,
.sop-expand-leave-active {
    transition:
        max-height 0.35s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.28s ease,
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
    max-height: 3000px;
    overflow: hidden;
}
.sop-expand-enter-from,
.sop-expand-leave-to {
    max-height: 0 !important;
    opacity: 0;
    transform: translateY(-8px);
}

.flow-sec {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding-left: 56px;
    margin: 1.2rem 0 0.95rem;
    scroll-margin-top: 90px !important;
}
.flow-sec:first-child {
    margin-top: 0.5rem;
}
.flow-sec-body {
    flex: 1;
    min-width: 0;
}
/* scroll-margin-top menyisakan ruang untuk header lengket saat scrollIntoView */
.flow-item {
    position: relative;
    padding-left: 56px;
    margin-bottom: 0.85rem;
    scroll-margin-top: 90px;
}

/* Interactive Section Card */
.flow-sec-card {
    width: 100%;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.8rem 1rem 0.8rem 0.9rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.flow-sec-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 14px -4px rgba(99, 102, 241, 0.14);
}
.flow-sec-chevron {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--surface-soft, var(--hcis-slate-100));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    transition:
        transform 0.25s ease,
        background-color 0.2s ease,
        color 0.2s ease;
}
.flow-sec-chevron.open {
    transform: rotate(180deg);
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}

.flow-node {
    position: absolute;
    z-index: 1;
    display: grid;
    place-items: center;
}
.flow-node.sec {
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    color: #fff;
    font-weight: 800;
    font-size: 0.95rem;
    box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.7);
    transition: all 0.3s ease;
}
.flow-node.sec.done {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 8px 20px -6px rgba(16, 185, 129, 0.7);
}
.flow-node:not(.sec) {
    left: 6px;
    top: 14px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    font-size: 0.85rem;
    background: #fff;
    border: 2px solid var(--border, #dbe1ea);
    color: var(--hcis-slate-400);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
.flow-node.done {
    background: var(--hcis-emerald-500) !important;
    border-color: var(--hcis-emerald-500) !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.45) !important;
}
.flow-node.current {
    background: var(--hcis-indigo-500) !important;
    border-color: var(--hcis-indigo-500) !important;
    color: var(--hcis-white) !important;
}
.flow-node.lock {
    background: var(--surface-soft, var(--hcis-slate-100)) !important;
    border-color: var(--border, #dbe1ea) !important;
    color: var(--hcis-slate-400) !important;
}
.flow-node :deep(i) {
    color: inherit;
    font-size: 0.85rem;
}

.rd-sec-name {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 1.08rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.rd-sec-desc {
    font-size: 0.82rem;
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
}
.rd-sec-prog {
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 999px;
    padding: 0.22rem 0.65rem;
    flex-shrink: 0;
}

/* Kartu item.
   Warna tipe TIDAK lagi jadi palang di tepi kiri, dan juga bukan garis
   sepotong di tepi atas — potongan seperti itu terbaca sebagai cacat karena
   berhenti di tengah tanpa alasan. Sekarang ronanya ikut GARIS TEPI penuh
   (--ri-line), ditambah cahaya redup di sudut kiri-atas (--ri-tint) dan
   warna bayangan saat hover (--ri-shadow). Token dari sopTheme.css. */
.ritem {
    --ri-lift: 0 1px 2px rgba(15, 23, 42, 0.05), 0 10px 26px -16px rgba(15, 23, 42, 0.22);
    --ri-gloss: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    position: relative;
    border: 1px solid var(--ri-line, var(--border, #dbe1ea));
    border-radius: 20px;
    background:
        radial-gradient(125% 115% at 0% 0%, var(--ri-tint, rgba(99, 102, 241, 0.06)), rgba(255, 255, 255, 0) 58%),
        var(--hcis-white);
    padding: 1.2rem 1.35rem;
    box-shadow: var(--ri-lift), var(--ri-gloss);
    transition:
        transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.25s ease;
}
.ritem:hover {
    transform: translateY(-3px);
    border-color: var(--ri-glow, rgba(99, 102, 241, 0.42));
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 24px 46px -22px var(--ri-shadow, rgba(99, 102, 241, 0.4)),
        var(--ri-gloss);
}
/* selesai → token tipe diambil alih hijau, jadi status menang atas tipe */
.ritem.done {
    --ri-accent: var(--hcis-emerald-500);
    --ri-line: rgba(5, 150, 105, 0.32);
    --ri-tint: rgba(16, 185, 129, 0.07);
    --ri-glow: rgba(16, 185, 129, 0.45);
    --ri-shadow: rgba(16, 185, 129, 0.4);
}
.ritem.current {
    --ri-line: rgba(99, 102, 241, 0.45);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.1),
        0 22px 44px -20px rgba(99, 102, 241, 0.45),
        var(--ri-gloss);
}
.ritem.highlight {
    --ri-accent: var(--hcis-amber-500);
    --ri-line: rgba(217, 119, 6, 0.45);
    box-shadow:
        0 0 0 4px rgba(245, 158, 11, 0.12),
        0 22px 44px -20px rgba(245, 158, 11, 0.4),
        var(--ri-gloss);
}
/* terkunci → rata & netral: tanpa rona tipe, tanpa kilau, tanpa angkat */
.ritem.locked {
    --ri-line: var(--border, #dbe1ea);
    --ri-tint: rgba(148, 163, 184, 0.08);
    opacity: 0.88;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.ritem.locked:hover {
    transform: none;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    border-color: var(--border, #dbe1ea);
}
.ritem.flash {
    animation: riFlash 1.4s ease;
}
@keyframes riFlash {
    0%,
    100% {
        box-shadow: var(--ri-lift), var(--ri-gloss);
    }
    30% {
        box-shadow:
            0 0 0 5px var(--ri-glow, rgba(99, 102, 241, 0.45)),
            0 20px 40px -18px var(--ri-shadow, rgba(99, 102, 241, 0.5)),
            var(--ri-gloss);
    }
}
@media (prefers-reduced-motion: reduce) {
    .ritem {
        transition: none;
    }
    .ritem:hover {
        transform: none;
    }
    .ritem.flash {
        animation: none;
    }
}

/* z-index: pita menumpang di tepi kartu, harus di atas latar & bayangannya */
.ritem-ribbon {
    position: absolute;
    z-index: 3;
    top: -1px;
    right: 16px;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-amber-500), #f97316);
    border-radius: 0 0 9px 9px;
    padding: 0.22rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
}
.ritem-now {
    position: absolute;
    z-index: 3;
    top: -11px;
    left: 16px;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    border-radius: 999px;
    padding: 0.22rem 0.65rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    box-shadow: 0 6px 16px -4px rgba(99, 102, 241, 0.8);
}

/* Nada berbeda dari "Lanjut Dari Sini" yang ungu: revisi bukan kemajuan, ia
   permintaan untuk kembali. Warnanya sendiri yang membedakan sebelum teksnya
   sempat dibaca. */
.ritem-now.ulang {
    background: linear-gradient(135deg, var(--hcis-amber-600), var(--hcis-amber-500));
    box-shadow: 0 6px 16px -4px rgba(217, 119, 6, 0.75);
}
.ri-ulang {
    font-size: 0.82rem;
    line-height: 1.5;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
}

.ri-head {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}
.ri-title {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 1.08rem;
    line-height: 1.35;
    letter-spacing: -0.01em;
}
.ri-meta {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
}
.ri-wajib {
    color: var(--hcis-amber-700);
    font-weight: 700;
}
.ri-nomor {
    font-weight: 700;
    color: var(--hcis-indigo-500);
}
.ri-desc {
    font-size: 0.88rem;
    color: var(--hcis-slate-600);
    line-height: 1.6;
}
.ri-akses {
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
}
.ri-doc-ext {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--text-muted, var(--hcis-slate-500));
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
}
.ri-spin {
    display: inline-block;
    animation: riSpin 0.9s linear infinite;
}
@keyframes riSpin {
    to {
        transform: rotate(360deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .ri-spin {
        animation: none;
    }
}
.ri-akses.ok {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-700);
}
.ri-akses.lock {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}
.ri-badge-contoh {
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    background: rgba(var(--primary-rgb, 99, 102, 241), 0.12);
    color: var(--primary-dark, #4f46e5);
    border: 1px solid rgba(var(--primary-rgb, 99, 102, 241), 0.25);
    letter-spacing: 0.01em;
}
.dark-mode .ri-badge-contoh {
    background: rgba(var(--primary-rgb, 99, 102, 241), 0.22);
    color: var(--primary-light, #a5b4fc);
    border-color: rgba(var(--primary-rgb, 99, 102, 241), 0.35);
}

.ri-btn-doc-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.65rem 1.35rem;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: var(--hcis-white) !important;
    font-weight: 700;
    font-size: 0.88rem;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 20px -4px rgba(99, 102, 241, 0.45);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
}
.ri-btn-doc-cta:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -4px rgba(99, 102, 241, 0.65);
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 100%);
    color: var(--hcis-white) !important;
}
.ri-btn-doc-cta:active:not(:disabled) {
    transform: translateY(0);
}
.ri-btn-doc-cta:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    box-shadow: none;
}

.ri-lock {
    background: rgba(245, 158, 11, 0.08) !important;
    border: 1px dashed rgba(245, 158, 11, 0.35) !important;
    color: var(--hcis-amber-800) !important;
    font-weight: 600;
    font-size: 0.86rem;
}

/* Notion-Style Prose Container */
.ri-prose {
    margin: 0.65rem 0;
    font-size: 0.92rem;
    color: var(--hcis-slate-700);
    line-height: 1.65;
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.85rem 1.1rem;
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.03);
}
.ri-prose p {
    margin-bottom: 0.55rem;
}
.ri-prose p:last-child {
    margin-bottom: 0;
}

/* Link Card Container */
.ri-link-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.9rem 1.1rem;
    transition: all 0.2s ease;
}
.ri-link-card:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 16px -4px rgba(99, 102, 241, 0.12);
}
.ri-link-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.ri-link-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}
.ri-link-ico {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.14), rgba(99, 102, 241, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.ri-link-domain {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
}
.ri-link-label {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 0.92rem;
}
.ri-link-url {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
}

/* Quiz Card Container */
.ri-quiz-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.95rem 1.1rem;
    transition: all 0.2s ease;
}
.ri-quiz-card:hover {
    background: var(--hcis-white);
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.08);
}
.ri-quiz-card.passed {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.06), rgba(16, 185, 129, 0.01));
    border-color: rgba(16, 185, 129, 0.35);
}
.ri-quiz-card.failed {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.06), rgba(239, 68, 68, 0.01));
    border-color: rgba(239, 68, 68, 0.35);
}

.ri-quiz-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.ri-quiz-main {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    min-width: 0;
}
.ri-quiz-ico {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.14), rgba(124, 58, 237, 0.04));
    border: 1px solid rgba(124, 58, 237, 0.25);
    color: var(--hcis-violet-600);
    display: grid;
    place-items: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.ri-quiz-card.passed .ri-quiz-ico {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.18), rgba(16, 185, 129, 0.05));
    border-color: rgba(16, 185, 129, 0.3);
    color: var(--hcis-emerald-600);
}
.ri-quiz-card.failed .ri-quiz-ico {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.18), rgba(239, 68, 68, 0.05));
    border-color: rgba(239, 68, 68, 0.3);
    color: var(--hcis-red-600);
}

.ri-quiz-badge-type {
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-violet-700);
    background: rgba(124, 58, 237, 0.1);
    border: 1px solid rgba(124, 58, 237, 0.25);
    border-radius: 999px;
    padding: 0.18rem 0.6rem;
}

.ri-quiz-status {
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-radius: 999px;
    padding: 0.18rem 0.6rem;
}
.ri-quiz-status.ok {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
}
.ri-quiz-status.bad {
    color: var(--hcis-red-700);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
}
.ri-quiz-status.wait {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
}

.ri-quiz-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.ri-quiz-m-chip {
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 0.3rem 0.65rem;
    display: flex;
    flex-direction: column;
}
.ri-quiz-m-lbl {
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}
.ri-quiz-m-val {
    font-size: 0.88rem;
    font-weight: 800;
    line-height: 1.1;
}

.ri-quiz-final-pill {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    display: inline-flex;
    align-items: center;
}

.ri-btn-quiz-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    border: 1px solid var(--hcis-indigo-700);
    border-radius: 999px;
    padding: 0.52rem 1.25rem;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.38);
    transition: all 0.25s ease;
}
.ri-btn-quiz-cta:hover {
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-indigo-700));
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.48);
}

/* Document Attachment Card Container */
.ri-doc-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.85rem 1rem;
    transition: all 0.2s ease;
}
.ri-doc-card:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 16px -4px rgba(99, 102, 241, 0.12);
}
.ri-doc-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.ri-doc-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}
.ri-doc-ico {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.ri-prog-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
}
.ri-prog-badge.reading {
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
}
.ri-prog-badge.ok {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
}
.ri-prog-badge.idle {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.ri-baca-ok {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 999px;
    padding: 0.32rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.08);
}

.ri-prog-bar-wrap {
    width: 100%;
    height: 4px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
}
.ri-prog-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-indigo-400));
    border-radius: 999px;
    transition: width 0.4s ease;
}
.ri-prog-bar.done {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}

.ri-doc-name {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.92rem;
    line-height: 1.35;
}

/* Popover Form Kosong untuk Kartu FORM - Fully harmonized with SOP Design System */
:global(.ri-lampiran-popover.el-popper) {
    padding: 0 !important;
    border-radius: var(--radius-lg, 16px) !important;
    background: var(--surface, var(--hcis-white)) !important;
    border: 1px solid var(--border, var(--hcis-slate-200)) !important;
    box-shadow: 0 16px 36px -8px rgb(var(--hcis-slate-900-rgb) / 0.16), 0 4px 12px -2px rgb(var(--hcis-slate-900-rgb) / 0.06) !important;
    font-family: inherit !important;
    /* Lebar 410px dari prop :width melampaui layar ponsel (iPhone 390px) —
       tombol Unduh terpotong. max-width tetap membatasi lebar inline. */
    max-width: calc(100vw - 16px);
}
:global(.ri-lampiran-popover[data-popper-placement^="bottom"] .el-popper__arrow::before) {
    background: var(--surface-muted, var(--hcis-slate-50)) !important;
    border-color: var(--border, var(--hcis-slate-200)) !important;
}
:global(.ri-lampiran-popover[data-popper-placement^="top"] .el-popper__arrow::before) {
    background: var(--surface, var(--hcis-white)) !important;
    border-color: var(--border, var(--hcis-slate-200)) !important;
}
:global(.ri-pop-lampiran-container) {
    color: var(--text, var(--hcis-slate-800));
    font-family: inherit;
}
:global(.ri-pop-header) {
    padding: 0.9rem 1.15rem;
    background: var(--surface-muted, var(--hcis-slate-50));
    border-bottom: 1px solid var(--border, var(--hcis-slate-200));
    border-top-left-radius: var(--radius-lg, 16px);
    border-top-right-radius: var(--radius-lg, 16px);
}
:global(.ri-pop-title) {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--text, var(--hcis-slate-800));
    line-height: 1.3;
    letter-spacing: -0.01em;
}
:global(.ri-pop-subtitle) {
    font-size: 0.74rem;
    color: var(--text-muted, var(--hcis-slate-500));
    font-weight: 500;
    line-height: 1.25;
}
:global(.ri-pop-list) {
    padding: 0.85rem;
    max-height: 290px;
    overflow-y: auto;
}
:global(.ri-pop-item) {
    padding: 0.65rem 0.85rem;
    background: var(--surface, var(--hcis-white));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: var(--radius, 12px);
    cursor: pointer;
    transition: var(--transition, all 0.2s cubic-bezier(0.4, 0, 0.2, 1));
}
:global(.ri-pop-item:hover) {
    background: var(--surface-muted, var(--hcis-slate-50));
    border-color: rgb(var(--hcis-indigo-500-rgb) / 0.35);
    box-shadow: 0 4px 14px -3px rgb(var(--hcis-indigo-500-rgb) / 0.12);
    transform: translateY(-1px);
}
:global(.ri-pop-item:hover .ri-pop-file-name) {
    color: var(--primary-dark, var(--hcis-indigo-600));
}
:global(.ri-pop-item.is-loading) {
    opacity: 0.65;
    pointer-events: none;
}
:global(.ri-pop-file-icon) {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
:global(.ri-pop-file-icon.ico-excel) {
    background: rgb(var(--hcis-emerald-500-rgb) / 0.1);
    color: var(--hcis-emerald-600);
    border: 1px solid rgb(var(--hcis-emerald-500-rgb) / 0.2);
}
:global(.ri-pop-file-icon.ico-word) {
    background: rgb(var(--hcis-blue-600-rgb) / 0.1);
    color: var(--hcis-blue-600);
    border: 1px solid rgb(var(--hcis-blue-600-rgb) / 0.2);
}
:global(.ri-pop-file-icon.ico-pdf) {
    background: rgb(var(--hcis-red-500-rgb) / 0.1);
    color: var(--hcis-red-600);
    border: 1px solid rgb(var(--hcis-red-500-rgb) / 0.2);
}
:global(.ri-pop-file-icon.ico-zip) {
    background: rgb(var(--hcis-amber-500-rgb) / 0.1);
    color: var(--hcis-amber-600);
    border: 1px solid rgb(var(--hcis-amber-500-rgb) / 0.2);
}
:global(.ri-pop-file-icon.ico-image) {
    background: color-mix(in srgb, var(--hcis-violet-600) 10%, transparent);
    color: var(--hcis-violet-600);
    border: 1px solid color-mix(in srgb, var(--hcis-violet-600) 20%, transparent);
}
:global(.ri-pop-file-icon.ico-default) {
    background: rgb(var(--hcis-indigo-500-rgb) / 0.1);
    color: var(--primary, var(--hcis-indigo-500));
    border: 1px solid rgb(var(--hcis-indigo-500-rgb) / 0.2);
}
:global(.ri-pop-file-name) {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text, var(--hcis-slate-800));
    line-height: 1.3;
    transition: color 0.15s ease;
}
:global(.ri-lampiran-popover .ri-doc-ext) {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--text-muted, var(--hcis-slate-500));
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
}

/* Audit Track Pills */
.ri-track {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 0.75rem;
}
.ri-track-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.76rem;
    font-weight: 700;
    padding: 0.28rem 0.75rem;
    border-radius: 999px;
}
.ri-track-pill.ok {
    background: rgba(16, 185, 129, 0.1);
    color: var(--hcis-emerald-700);
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.ri-track-pill.info {
    background: rgba(6, 182, 212, 0.1);
    color: var(--hcis-cyan-700);
    border: 1px solid rgba(6, 182, 212, 0.25);
}
.ri-track-pill.idle {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--border, #dbe1ea);
}

.ri-quiz {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
}
.ri-quiz-info {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    flex: 1;
}
.ri-quiz-chip {
    display: inline-flex;
    align-items: center;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-violet-700);
    background: rgba(139, 92, 246, 0.12);
    border-radius: 999px;
    padding: 0.24rem 0.65rem;
}
.ri-quiz-chip.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
}
/* Menunggu callback — informatif, bukan galat. */
.ri-quiz-wait {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    padding: 0.5rem 0.8rem;
    margin-bottom: 0.6rem;
}
/* Penutup netral, bukan peringatan: hasilnya final, tak ada yang hilang. */
.ri-quiz-final {
    display: inline-flex;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 12px;
    padding: 0.5rem 0.8rem;
}
.ri-quiz-chip.warn {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.14);
}

.ri-track {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1rem;
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
}
.ri-docinfo {
    border-top: 1px dashed var(--hcis-slate-200);
}

.ri-docinfo-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.ri-versi-body {
    margin-top: 0.85rem;
}

.ri-btn-collapse {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.05rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    color: var(--hcis-slate-900);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.ri-btn-collapse:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}
.ri-btn-collapse.open {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-400);
    box-shadow: 0 4px 16px -2px rgba(99, 102, 241, 0.15);
}
.ri-collapse-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    font-size: 0.95rem;
    box-shadow: 0 3px 10px -2px rgba(99, 102, 241, 0.35);
    flex-shrink: 0;
}
.ri-collapse-text {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.86rem;
    letter-spacing: -0.01em;
}
.ri-btn-collapse-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
    white-space: nowrap;
}
.ri-btn-collapse-badge.ok {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-100);
    border: 1px solid var(--hcis-emerald-300);
}
.ri-btn-collapse-badge.warn {
    color: var(--hcis-red-700);
    background: var(--hcis-red-100);
    border: 1px solid var(--hcis-red-300);
}

.ri-chevron-box {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 0.75rem;
    transition: all 0.2s ease;
}
.ri-btn-collapse.open .ri-chevron-box {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}

.ri-approve-card {
    background: linear-gradient(135deg, var(--hcis-green-50) 0%, var(--hcis-white) 100%);
    border: 1px solid var(--hcis-green-200);
    border-radius: 16px;
    padding: 1rem 1.15rem;
    box-shadow: 0 4px 16px -2px rgba(16, 185, 129, 0.08);
}
.ri-doc-seal-ico {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: var(--hcis-white);
    font-size: 1.35rem;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.35);
    flex-shrink: 0;
}
/* Unified Executive Document Info Card */
.ri-doc-unified-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.06);
    transition: all 0.25s ease;
}
.ri-doc-unified-card:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 6px 24px -4px rgba(15, 23, 42, 0.09);
}
.ri-doc-head {
    padding: 1.15rem 1.25rem;
    background: linear-gradient(135deg, var(--hcis-green-50) 0%, var(--hcis-white) 100%);
    border-bottom: 1px solid var(--hcis-slate-200);
}
.ri-doc-eyebrow {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: var(--hcis-emerald-700);
}
.ri-doc-status-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-100);
    border: 1px solid var(--hcis-emerald-300);
    padding: 0.18rem 0.6rem;
    border-radius: 999px;
    box-shadow: 0 1px 3px rgba(5, 150, 105, 0.1);
}
.ri-doc-authority {
    font-size: 0.94rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.35;
}
.ri-doc-role {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
    margin-top: 2px;
}
.ri-doc-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.3rem;
    margin-top: 0.3rem;
}
.ri-doc-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-emerald-800);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-emerald-200);
    padding: 0.25rem 0.7rem;
    border-radius: 8px;
}
.ri-doc-validity-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    padding: 0.9rem 1.25rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--hcis-slate-200);
    gap: 1rem;
}
.ri-doc-val-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.ri-doc-val-ico {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.ri-doc-val-ico.exp {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.ri-doc-val-ico.rev {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}
.ri-doc-val-lbl {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.2;
}
.ri-doc-val-val {
    display: block;
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    line-height: 1.25;
    margin-top: 1px;
}
.ri-doc-foot {
    padding: 0.55rem 0.85rem;
    background: var(--hcis-white);
}
.ri-doc-versi-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: none;
    background: transparent;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.2s ease;
}
.ri-doc-versi-btn:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-indigo-700);
}

.ri-validity {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.65rem;
}
.ri-val {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.6rem 0.8rem;
    transition: all 0.2s ease;
}
.ri-val:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}
.ri-val-ico {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.9rem;
    color: var(--hcis-blue-600);
    background: rgba(37, 99, 235, 0.1);
    flex-shrink: 0;
}
.ri-val-ico.exp {
    color: var(--hcis-red-600);
    background: rgba(220, 38, 38, 0.1);
}
.ri-val-ico.rev {
    color: var(--hcis-violet-600);
    background: rgba(124, 58, 237, 0.1);
}

.ri-val-lbl {
    display: block;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
}
.ri-val-v {
    display: block;
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin-top: 1px;
}

/* DONE / CELEBRATION */
.rd-done {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1.2rem;
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 8px;
    padding: 1.3rem 1.5rem;
}
.rd-done-badge {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
    color: #fff;
    font-size: 2rem;
    box-shadow: 0 12px 28px -8px rgba(16, 185, 129, 0.85);
    flex-shrink: 0;
}
.rd-done-txt strong {
    color: var(--hcis-emerald-700);
    font-size: 1.2rem;
}
.rd-done-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    flex-shrink: 0;
}
@media (max-width: 767px) {
    .rd-done {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 1rem;
    }
    .rd-done-badge {
        width: 48px;
        height: 48px;
        font-size: 1.55rem;
    }
    .rd-done-txt {
        flex: 1;
        min-width: 0;
    }
    .rd-done-actions {
        width: 100%;
        margin-left: 0;
    }
    .rd-done-actions .stitch-btn {
        flex: 1;
    }
}
.rd-end {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
}
.rd-end-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 1.25rem;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    font-size: 0.84rem;
    color: var(--hcis-slate-700);
}
.rd-end-count {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.1);
    border-radius: 999px;
    padding: 0.15rem 0.5rem;
}

/* ===== BAR BAWAH (LAYAR KECIL) ===== */
.rd-mbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 55;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid var(--border, #dbe1ea);
    box-shadow: 0 -8px 28px -12px rgba(15, 23, 42, 0.25);
    padding-bottom: env(safe-area-inset-bottom);
}
.rd-mbar-prog {
    height: 3px;
    background: var(--surface-soft, var(--hcis-slate-100));
}
.rd-mbar-prog span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
    transition: width 0.4s ease;
}
.rd-mbar-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.85rem;
}
/* sisi kiri: satu tombol lebar — sasaran sentuh besar, bukan tiga elemen kecil */
.rd-mbar-nav {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 46px;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.3rem 0.6rem 0.3rem 0.35rem;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    border-radius: 14px;
    cursor: pointer;
    text-align: left;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}
.rd-mbar-nav:active {
    border-color: rgba(99, 102, 241, 0.45);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.rd-mbar-pct {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 46px;
    height: 32px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
}
.rd-mbar-ctx {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.rd-mbar-lbl {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-mbar-sub {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-mbar-caret {
    flex-shrink: 0;
    margin-left: auto;
    font-size: 0.8rem;
    color: var(--hcis-slate-400);
}
.rd-mbar-cta {
    flex-shrink: 0;
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.95rem;
    border: 0;
    border-radius: 14px;
    font-size: 0.84rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    box-shadow: 0 8px 20px -8px rgba(99, 102, 241, 0.8);
    cursor: pointer;
}
.rd-mbar-cta i {
    font-size: 1.05rem;
}
.rd-mbar-cta:active {
    transform: translateY(1px);
}
.rd-mbar-done {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.3);
    font-size: 1.2rem;
}

/* Kepala sheet — hanya muncul saat rail dipakai sebagai bottom sheet */
.rd-sheet-head {
    display: none;
}
.rd-sheet-grip {
    display: block;
    width: 38px;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-300);
    margin: 0 auto;
}
.rd-sheet-close {
    position: absolute;
    right: 0.25rem;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: rgba(148, 163, 184, 0.15);
    border-radius: 50%;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.rd-sheet-close:active {
    background: rgba(148, 163, 184, 0.3);
    transform: translateY(-50%) scale(0.95);
}

/* ===== MEDIA QUERIES ===== */

/* Desktop: pembungkus rail tak berbentuk, .rd-rail langsung jadi kolom grid. */
.rd-side {
    display: contents;
}

@media (max-width: 991.98px) {
    .reader-shell {
        padding-bottom: calc(5.75rem + env(safe-area-inset-bottom));
    }
    .rd-hero-ring {
        display: none;
    }
    /* header lengket lebih pendek di layar kecil */
    .flow-item {
        scroll-margin-top: 72px;
    }
    .flow-sec {
        scroll-margin-top: 72px !important;
    }

    /* Rail berubah jadi bottom sheet — elemen yang sama, penyajian berbeda. */
    .rd-side {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 65;
        align-items: flex-end;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        animation: sideFade 0.2s ease;
    }
    .rd-side.open {
        display: flex;
    }
    .rd-side .rd-rail {
        position: relative !important;
        top: auto !important;
        width: 100%;
        max-height: 84vh !important;
        overflow-y: auto;
        padding: 0.6rem 1.05rem 1.2rem;
        padding-bottom: max(1.2rem, env(safe-area-inset-bottom));
        border-radius: 24px 24px 0 0;
        border-left: 0;
        border-right: 0;
        border-bottom: 0;
        box-shadow: 0 -14px 40px rgba(15, 23, 42, 0.28);
        animation: sheetUp 0.32s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .rd-sheet-head {
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 0.2rem 0 0.65rem;
        margin-bottom: 0.25rem;
    }
    /* padding sheet lebih rapat dari rail desktop → penutup celahnya ikut disetel */
    .rd-side .rail-seg::before {
        top: -0.65rem;
        height: 0.65rem;
    }
}
@keyframes sheetUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}
@keyframes sideFade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (max-width: 767.98px) {
    .rd-spec {
        flex-wrap: nowrap;
        overflow-x: auto;
        scroll-snap-type: x proximity;
        scrollbar-width: none;
    }
    .rd-spec::-webkit-scrollbar {
        display: none;
    }
    .rd-spec-cell {
        flex: 0 0 auto;
        min-width: 135px;
        scroll-snap-align: start;
    }
    .rd-filter {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }
    .rd-filter::-webkit-scrollbar {
        display: none;
    }
    .rd-fchip {
        flex: 0 0 auto;
    }
}
@media (max-width: 575px) {
    .rd-hero {
        min-height: 190px;
        border-radius: 18px;
    }
    .rd-hero-body {
        padding: 1rem 1.1rem;
    }
    .rd-title {
        font-size: clamp(1.25rem, 5vw, 1.55rem);
        word-break: break-word;
    }
    /* tombol hero jadi ikon saja — judul dokumen dapat lebar, bar atas tak berdesakan */
    .rd-hero-btn span {
        display: none;
    }
    .rd-hero-btn {
        padding: 0.42rem 0.6rem;
        min-height: 38px;
        min-width: 38px;
        justify-content: center;
    }
    .flow-sec {
        padding-left: 44px;
        margin-top: 1.25rem;
    }
    .flow-item {
        padding-left: 44px;
        margin-bottom: 0.75rem;
    }
    .rd-flow::before {
        left: 15px;
    }
    .flow-node.sec {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        font-size: 0.85rem;
    }
    .flow-node:not(.sec) {
        left: 3px;
        width: 26px;
        height: 26px;
        font-size: 0.78rem;
    }
    .ritem {
        padding: 0.9rem 1rem;
        border-radius: 16px;
    }
    .ritem-now {
        top: -10px;
        left: 12px;
        font-size: 0.58rem;
        padding: 0.18rem 0.55rem;
    }
    .ritem-ribbon {
        top: -1px;
        right: 12px;
        font-size: 0.58rem;
        padding: 0.18rem 0.55rem;
    }
    .ri-title {
        font-size: 0.98rem;
        line-height: 1.35;
        font-weight: 800;
    }
    .ri-desc {
        font-size: 0.84rem;
        line-height: 1.55;
    }
    .ri-quiz {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .ri-quiz-info {
        width: 100%;
        gap: 0.35rem;
    }
    .ri-quiz > .stitch-btn,
    .ri-quiz > a.stitch-btn {
        width: 100%;
        justify-content: center;
        min-height: 42px;
    }
    /* ri-docinfo comfortable mobile spacing */
    .ri-docinfo {
        margin-top: 0.75rem;
        padding-top: 0.5rem;
    }
    .ri-docinfo-body {
        gap: 0.65rem;
        margin-top: 0.65rem;
    }
    .ri-btn-collapse {
        padding: 0.6rem 0.85rem;
        border-radius: 12px;
    }
    .ri-collapse-icon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
        border-radius: 9px;
    }
    .ri-collapse-text {
        font-size: 0.82rem;
    }
    .ri-btn-collapse-badge {
        font-size: 0.6rem;
        padding: 0.18rem 0.5rem;
    }
    .ri-chevron-box {
        width: 24px;
        height: 24px;
        font-size: 0.72rem;
    }

    .ri-doc-unified-card {
        border-radius: 14px;
    }
    .ri-doc-head {
        padding: 0.85rem 1rem;
    }
    .ri-doc-authority {
        font-size: 0.88rem;
    }
    .ri-doc-role {
        font-size: 0.74rem;
    }
    .ri-doc-tag {
        font-size: 0.7rem;
        padding: 0.2rem 0.55rem;
    }
    .ri-doc-validity-strip {
        padding: 0.75rem 1rem;
        gap: 0.75rem;
    }
    .ri-doc-val-lbl {
        font-size: 0.62rem;
    }
    .ri-doc-val-val {
        font-size: 0.78rem;
    }
    .ri-doc-foot {
        padding: 0.45rem 0.65rem;
    }
    .ri-doc-versi-btn {
        padding: 0.42rem 0.6rem;
        font-size: 0.76rem;
    }

    /* aksi item: layout compact & touch-friendly */
    .ri-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        width: 100%;
    }
    .ri-actions > .stitch-btn,
    .ri-actions > a.stitch-btn {
        flex: 1 1 100%;
        justify-content: center;
        min-height: 42px;
        font-size: 0.82rem;
    }
    .ri-doc-card-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .ri-doc-card-inner > .stitch-btn {
        width: 100%;
        justify-content: center;
        min-height: 42px;
    }
    .ri-doc-main {
        width: 100%;
    }
    .rd-toc-item {
        min-height: 44px;
        padding: 0.5rem 0.75rem;
    }

    /* Responsive Banners & Bottom Bar */
    .rd-assign {
        flex-direction: column;
        align-items: stretch;
        gap: 0.7rem;
    }
    .rd-assign-main {
        width: 100%;
    }
    .rd-gate-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .rd-gate-row .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Tombol Lanjut pada bar bawah: Tampilkan teks compact dengan rapi */
    .rd-mbar-cta {
        padding: 0 0.85rem;
        gap: 0.25rem;
        font-size: 0.8rem;
        min-height: 44px;
        border-radius: 12px;
    }
    .rd-mbar-cta-txt {
        display: inline !important;
        font-weight: 800;
        letter-spacing: -0.01em;
    }
    .rd-mbar-nav {
        min-height: 44px;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
    }
    .rd-mbar-pct {
        min-width: 40px;
        height: 30px;
        font-size: 0.78rem;
        border-radius: 8px;
    }
}

.rd-hero-btn.active {
    background: var(--hcis-indigo-500) !important;
    border-color: var(--hcis-indigo-400) !important;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45) !important;
}
.is-focus-mode .rd-rail {
    display: none !important;
}
.is-focus-mode .sop-workspace {
    display: block !important;
}

@media print {
    .sop-topbar,
    .rd-scrollbar,
    .rd-rail,
    .rd-mbar,
    .rd-filter,
    .ri-actions {
        display: none !important;
    }
    .reader-shell {
        padding: 0 !important;
    }
    .rd-hero {
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
    }
    .rd-hero-shade,
    .rd-hero-mesh {
        display: none !important;
    }
    .rd-title {
        color: #000 !important;
        text-shadow: none !important;
    }
}

/* Highlight pulse animation when navigated from document reader drawer */
.item-nav-highlight {
    animation: itemNavPulse 2.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes itemNavPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.6);
        border-color: var(--hcis-indigo-500) !important;
        background-color: rgba(99, 102, 241, 0.08) !important;
    }
    50% {
        box-shadow: 0 0 0 12px rgba(99, 102, 241, 0);
        border-color: var(--hcis-indigo-500) !important;
    }
    100% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
    }
}

/* ========================================================
   SOP GOVERNANCE & VERSION MODAL (EXECUTIVE COCKPIT DESIGN)
   ======================================================== */
.sop-gov-tab-nav {
    background: rgba(241, 245, 249, 0.8) !important;
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
}
.sop-gov-tab-btn {
    border: 0;
    background: transparent;
    padding: 0.45rem 0.9rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.sop-gov-tab-btn:hover {
    color: var(--hcis-slate-800);
    background: rgba(255, 255, 255, 0.6);
}
.sop-gov-tab-btn.active {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
.sop-gov-tab-badge {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.1rem 0.42rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}

/* Hero Banner in Modal */
.sop-gov-hero-banner {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-indigo-50) 100%);
    border: 1px solid var(--hcis-indigo-200);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 2px 10px rgba(99, 102, 241, 0.06);
}
.sop-gov-hero-seal {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: var(--hcis-white);
    font-size: 1.4rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
    flex-shrink: 0;
}
.sop-gov-hero-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}
.sop-gov-pill-ver {
    display: inline-flex;
    align-items: center;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 8px;
    padding: 0.25rem 0.65rem;
}
.sop-gov-pill-dist {
    display: inline-flex;
    align-items: center;
    font-size: 0.74rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.25rem 0.65rem;
}
.sop-gov-pill-dist.public {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    border: 1px solid var(--hcis-green-200);
}
.sop-gov-pill-dist.limited {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}

/* Pillar Detail Cards */
.sop-gov-pillar-card {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-200) !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.sop-gov-pillar-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.sop-gov-pillar-icon.indigo {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.sop-gov-pillar-icon.emerald {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.sop-gov-pillar-icon.amber {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}
.sop-code-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid var(--hcis-slate-200);
}
.sop-gov-scope-card {
    background: #fafaf9;
    border-color: #e7e5e4 !important;
}

/* Matrix Row & Deck */
.sop-gov-matrix-row {
    border-color: var(--hcis-slate-200) !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.sop-gov-matrix-row:hover {
    border-color: var(--hcis-indigo-400) !important;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.1);
    transform: translateX(3px);
}
.sop-gov-matrix-idx {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 6px;
    padding: 0.15rem 0.4rem;
    min-width: 28px;
    text-align: center;
}
.sop-gov-matrix-ver-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.sop-gov-matrix-ver-tag.berlaku {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.sop-gov-matrix-ver-tag.belum_berlaku {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}
.sop-gov-matrix-ver-tag.versi_lama {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-300);
}

/* Audit Hero & Progress */
.sop-gov-audit-hero {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border-color: var(--hcis-slate-300) !important;
}
.sop-gov-audit-badge {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-500);
    font-size: 1.5rem;
}
.sop-gov-audit-badge.ok {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: var(--hcis-white);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
}
.sop-gov-progress-bar {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    overflow: hidden;
}
.sop-gov-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500) 0%, var(--hcis-emerald-500) 100%);
    border-radius: 999px;
    transition: width 0.4s ease;
}
.sop-gov-timeline-wrap {
    border-color: var(--hcis-slate-200) !important;
}

/* Multi-Signer Rail Cards */
.legal-signer-card {
    border-color: var(--hcis-slate-200) !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}
.legal-signer-card:hover {
    border-color: var(--hcis-slate-300) !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}
.legal-signer-seal {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.legal-signer-seal.emerald {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.legal-signer-seal.blue {
    background: #eff6ff;
    color: var(--hcis-blue-600);
}
.legal-signer-seal.purple {
    background: #faf5ff;
    color: var(--hcis-violet-600);
}
.legal-signer-seal.indigo {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.legal-signer-seal.amber {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}
.legal-signer-role {
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.12rem 0.42rem;
    border-radius: 5px;
}
.legal-signer-role.emerald {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.legal-signer-role.blue {
    background: #dbeafe;
    color: #1d4ed8;
}
.legal-signer-role.purple {
    background: var(--hcis-purple-100);
    color: #6b21a8;
}
.legal-signer-role.indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}
.legal-signer-role.amber {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

/* Authority Matrix in Modal */
.sop-gov-authority-section {
    border-color: var(--hcis-slate-200) !important;
    background: var(--hcis-white);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.sop-gov-authority-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}
.sop-gov-authority-card {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200) !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.sop-gov-authority-card:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-200) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
    transform: translateY(-2px);
}
.sop-gov-auth-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.sop-gov-auth-icon.emerald {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-700);
}
.sop-gov-auth-icon.blue {
    background: #dbeafe;
    color: #1e40af;
}
.sop-gov-auth-icon.purple {
    background: #ede9fe;
    color: #5b21b6;
}
.sop-gov-auth-icon.indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-800);
}
.sop-gov-auth-icon.amber {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}
.sop-gov-auth-pill {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}
.sop-gov-auth-pill.emerald {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    border: 1px solid var(--hcis-green-200);
}
.sop-gov-auth-pill.blue {
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.sop-gov-auth-pill.purple {
    background: var(--hcis-purple-100);
    color: #6b21a8;
    border: 1px solid #e9d5ff;
}
.sop-gov-auth-pill.indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    border: 1px solid var(--hcis-indigo-200);
}
.sop-gov-auth-pill.amber {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}
.sop-gov-sk-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
}

/* Per-Document Endorsement Cards in Rail */
.legal-doc-endorsement-card {
    border-color: var(--hcis-slate-200) !important;
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}
.legal-doc-endorsement-card:hover {
    border-color: var(--hcis-slate-300) !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
}
.legal-seal-chip {
    width: 24px;
    height: 24px;
    border-radius: 7px;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    flex-shrink: 0;
}
.legal-seal-chip.emerald {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.legal-seal-chip.indigo {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

/* Per-Document Control Sheets in Modal */
.sop-gov-doc-sheet {
    border-color: var(--hcis-slate-200) !important;
    background: var(--hcis-white);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.sop-gov-doc-sheet:hover {
    border-color: var(--hcis-indigo-300) !important;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.09);
    transform: translateY(-2px);
}
.sop-gov-doc-no-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-800);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
    font-weight: 700;
    font-size: 0.72rem;
}
</style>
