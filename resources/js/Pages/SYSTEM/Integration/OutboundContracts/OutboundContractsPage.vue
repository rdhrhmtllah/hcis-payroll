<template>
    <div class="container-fluid px-0 kontrak-outbound-page">
        <!-- ── Page Header ─────────────────────────────────────────────── -->
        <HcisHeader
            title="Kontrak Integrasi Outbound"
            kicker="System · Integrasi Hub"
            description="Spesifikasi rute endpoint yang HCIS minta disediakan oleh sistem mitra pihak ketiga. Setiap kontrak memiliki tautan publik mandiri yang dapat langsung dibagikan ke tim developer vendor."
            icon="box-arrow-up-right"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge :label="`${contracts.length} Kontrak`" variant="brand" appearance="glass" size="sm" dot />
            </template>

            <template #actions>
                <HcisButton
                    variant="glass"
                    size="md"
                    leading-icon="file-earmark-code"
                    label="Dokumentasi API Inbound"
                    @click="bukaTautan('/api/docs')"
                />
            </template>
        </HcisHeader>

        <!-- ── Executive Key Metrics ────────────────────────────────────── -->
        <HcisStatGroup
            cols="4"
            gap="md"
            class="mb-4"
            :stats="statCards"
        />

        <!-- ── Security & Audience Notice Alert ────────────────────────── -->
        <HcisAlert
            variant="warning"
            appearance="subtle"
            class="mb-4"
            title="Tautan viewer kontrak bersifat publik untuk developer vendor"
            description="Halaman internal ini khusus System Administrator, namun tautan spesifikasi tiap kontrak sengaja dibuat publik (tanpa login / Api_Public) agar memudahkan implementasi oleh developer pihak ketiga. Hindari menaruh kredensial rahasia atau data karyawan sungguhan di dalam contoh payload."
        />

        <!-- ── Official Standard HcisFilterBar Cockpit ─────────────────── -->
        <div class="mb-4">
            <HcisFilterBar
                v-model:search="pencarian"
                v-model:view-mode="modeTampilan"
                variant="card"
                sentiment="primary"
                size="md"
                search-placeholder="Cari judul kontrak, sistem tujuan, path endpoint, atau slug…"
                :quick-chips="chipsSistem"
                :active-chip-value="chipAktif"
                :filters="definisiFilter"
                :filter-values="filterValues"
                :show-view-toggle="true"
                :results-count="terpilih.length"
                @chip-click="handleChipClick"
                @filter-change="handleFilterChange"
                @reset="handleResetFilter"
            />
        </div>

        <!-- ── Content Workspace (Table / Grid) ─────────────────────────── -->
        <HcisCard variant="glass" sentiment="brand" size="lg" class="mb-4">
            <template #header>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="min-w-0">
                        <div class="fw-bold text-dark fs-6">Daftar Kontrak Integrasi</div>
                        <div class="text-muted small">{{ ringkasanSaringan }}</div>
                    </div>
                </div>
            </template>

            <!-- ── Empty State ─────────────────────────────────────────── -->
            <HcisEmptyState
                v-if="terpilih.length === 0"
                icon="funnel"
                title="Tidak Ada Kontrak Yang Cocok"
                description="Ubah kata kunci pencarian atau bersihkan saringan sistem dan kategori."
                variant="flat"
                sentiment="neutral"
            >
                <template #actions>
                    <HcisButton
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-counterclockwise"
                        label="Reset Saringan"
                        @click="handleResetFilter"
                    />
                </template>
            </HcisEmptyState>

            <!-- ── Mode 1: Table View ───────────────────────────────────── -->
            <HcisTable
                v-else-if="modeTampilan === 'table'"
                :columns="kolom"
                :rows="terpilih"
                row-key="slug"
                hoverable
                sentiment="brand"
            >
                <template #cell-title="{ row }">
                    <div class="py-1 min-w-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark text-truncate" style="font-size: 0.9rem">{{ row.title }}</span>
                            <HcisBadge
                                :label="`${jumlahEndpoint(row)} endpoint`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                        <div class="text-muted font-monospace text-truncate mt-0.5" style="font-size: 0.72rem">{{ row.slug }}</div>
                    </div>
                </template>

                <template #cell-target_system="{ row }">
                    <div class="min-w-0">
                        <div class="text-dark fw-medium text-truncate" style="font-size: 0.8125rem">{{ row.target_system }}</div>
                        <div class="text-muted text-truncate" style="font-size: 0.72rem">{{ row.category }}</div>
                    </div>
                </template>

                <template #cell-endpoint="{ row }">
                    <div class="d-flex flex-column gap-1 min-w-0">
                        <div
                            v-for="ep in endpointTampil(row)"
                            :key="ep.id || ep.path"
                            class="d-flex align-items-center gap-2 min-w-0"
                        >
                            <HcisBadge :label="ep.method" :variant="variantMethod(ep.method)" appearance="subtle" size="xs" />
                            <code class="text-truncate ko-path" :title="ep.name ? `${ep.path} — ${ep.name}` : ep.path">{{ ep.path }}</code>
                        </div>

                        <button
                            v-if="sisaEndpoint(row) > 0"
                            type="button"
                            class="ko-sisa text-start"
                            @click="inspeksiKontrak(row)"
                        >
                            +{{ sisaEndpoint(row) }} endpoint lagi — lihat semua
                        </button>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div>
                        <HcisBadge :label="row.status" :variant="variantStatus(row.status)" appearance="subtle" size="xs" dot />
                        <div class="text-muted mt-1" style="font-size: 0.7rem">{{ row.version }} · {{ row.last_updated }}</div>
                    </div>
                </template>

                <template #cell-aksi="{ row }">
                    <div class="d-flex justify-content-end gap-1.5">
                        <HcisButton
                            variant="ghost"
                            size="sm"
                            leading-icon="link-45deg"
                            label="Salin"
                            title="Salin tautan publik shareable"
                            @click="salinTautan(row)"
                        />
                        <HcisButton
                            variant="ghost"
                            size="sm"
                            leading-icon="layout-sidebar-reverse"
                            label="Rincian"
                            title="Buka pratinjau spesifikasi cepat di panel laci"
                            @click="inspeksiKontrak(row)"
                        />
                        <HcisButton
                            variant="glass"
                            size="sm"
                            leading-icon="box-arrow-up-right"
                            label="Viewer"
                            title="Buka halaman viewer publik di tab baru"
                            @click="bukaTautan(tautanViewer(row))"
                        />
                    </div>
                </template>
            </HcisTable>

            <!-- ── Mode 2: Grid / Card View ────────────────────────────── -->
            <div v-else class="row g-3 py-1">
                <div
                    v-for="contract in terpilih"
                    :key="contract.slug"
                    class="col-12 col-lg-6"
                >
                    <div class="ko-card h-100 d-flex flex-column justify-content-between p-3.5 rounded-3 border bg-white shadow-sm">
                        <div>
                            <!-- Card Meta Top Row -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <div class="d-flex flex-wrap align-items-center gap-1.5">
                                    <span class="ko-tag ko-tag--brand">{{ contract.target_system }}</span>
                                    <span class="ko-tag ko-tag--neutral">{{ contract.category }}</span>
                                    <span class="ko-tag ko-tag--amber">{{ jumlahEndpoint(contract) }} Endpoint</span>
                                </div>
                                <HcisBadge :label="contract.status" :variant="variantStatus(contract.status)" appearance="subtle" size="xs" dot />
                            </div>

                            <!-- Title & Slug -->
                            <h3 class="fw-bold text-dark fs-6 mb-1 text-truncate" :title="contract.title">
                                {{ contract.title }}
                            </h3>
                            <div class="text-muted font-monospace mb-2.5" style="font-size: 0.72rem">{{ contract.slug }} · Versi {{ contract.version }}</div>

                            <!-- Description -->
                            <p class="text-muted small mb-3 text-clamp-2" style="font-size: 0.8125rem; line-height: 1.45">
                                {{ contract.summary }}
                            </p>

                            <!-- Endpoints Box -->
                            <div class="p-2.5 rounded-2 bg-slate-50 border border-slate-200 mb-3">
                                <div class="text-muted fw-semibold mb-1.5" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em">
                                    Endpoint Harapan Wajib Disediakan:
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <div
                                        v-for="ep in daftarEndpoint(contract)"
                                        :key="ep.id || ep.path"
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <HcisBadge :label="ep.method" :variant="variantMethod(ep.method)" appearance="subtle" size="xs" />
                                        <code class="text-truncate text-dark font-monospace" style="font-size: 0.75rem">{{ ep.path }}</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Action Bar -->
                        <div class="pt-2.5 border-top border-slate-100 d-flex justify-content-between align-items-center gap-2">
                            <div class="d-flex gap-1">
                                <HcisButton
                                    variant="ghost"
                                    size="sm"
                                    leading-icon="link-45deg"
                                    label="Salin Tautan"
                                    @click="salinTautan(contract)"
                                />
                                <HcisButton
                                    variant="ghost"
                                    size="sm"
                                    leading-icon="layout-sidebar-reverse"
                                    label="Rincian"
                                    @click="inspeksiKontrak(contract)"
                                />
                            </div>

                            <HcisButton
                                variant="glass"
                                size="sm"
                                leading-icon="box-arrow-up-right"
                                label="Buka Viewer"
                                @click="bukaTautan(tautanViewer(contract))"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- ── In-Page Quick Inspection Drawer ─────────────────────────── -->
        <HcisDrawer
            v-model="drawerTerbuka"
            direction="rtl"
            size="xl"
            :title="kontrakAktif ? kontrakAktif.title : 'Rincian Kontrak'"
            :description="kontrakAktif ? `${kontrakAktif.target_system} · ${kontrakAktif.category} · Versi ${kontrakAktif.version}` : ''"
            icon="box-arrow-up-right"
            sentiment="brand"
        >
            <div v-if="kontrakAktif" class="d-flex flex-column gap-3.5 py-1">
                <!-- Top Summary & Share Link Card -->
                <div class="p-3 rounded-3 bg-indigo-50/70 border border-indigo-200 d-flex flex-column gap-2.5">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-1.5">
                            <span class="ko-tag ko-tag--brand">{{ kontrakAktif.target_system }}</span>
                            <span class="ko-tag ko-tag--neutral">{{ kontrakAktif.category }}</span>
                            <span class="ko-tag ko-tag--amber">Versi {{ kontrakAktif.version }}</span>
                            <HcisBadge :label="kontrakAktif.status" :variant="variantStatus(kontrakAktif.status)" appearance="subtle" size="xs" dot />
                        </div>
                        <span class="text-muted small">Update: {{ kontrakAktif.last_updated }}</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="font-size: 0.75rem">
                                <i class="bi bi-link-45deg"></i>
                            </span>
                            <input
                                type="text"
                                readonly
                                :value="tautanViewer(kontrakAktif)"
                                class="form-control form-control-sm bg-white font-monospace text-dark border-start-0"
                                style="font-size: 0.75rem"
                                title="Tautan spesifikasi publik (bebas login)"
                            />
                        </div>
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="clipboard-check"
                            label="Salin Link"
                            @click="salinTautan(kontrakAktif)"
                        />
                        <HcisButton
                            variant="glass"
                            size="sm"
                            leading-icon="box-arrow-up-right"
                            label="Viewer"
                            @click="bukaTautan(tautanViewer(kontrakAktif))"
                        />
                    </div>
                </div>

                <!-- Drawer Navigation Tabs -->
                <HcisTabs
                    v-model="tabAktifDrawer"
                    :tabs="tabsDrawer"
                    variant="segmented"
                    block
                />

                <!-- ═════════════════════════════════════════════════════════ -->
                <!-- TAB 1: ENDPOINT & SKEMA PAYLOAD                          -->
                <!-- ═════════════════════════════════════════════════════════ -->
                <div v-if="tabAktifDrawer === 'endpoints'" class="d-flex flex-column gap-3">
                    <!-- Endpoint Selector Pills (jika ada lebih dari 1 endpoint) -->
                    <div v-if="daftarEndpoint(kontrakAktif).length > 1" class="d-flex flex-wrap gap-2 p-1.5 rounded-3 bg-slate-100 border">
                        <button
                            v-for="(ep, idx) in daftarEndpoint(kontrakAktif)"
                            :key="ep.id || idx"
                            type="button"
                            class="btn btn-sm d-flex align-items-center gap-2 rounded-2 transition-all"
                            :class="endpointAktifIdx === idx ? 'btn-white bg-white text-primary shadow-sm border border-primary-subtle fw-semibold' : 'btn-ghost text-muted border-0'"
                            @click="endpointAktifIdx = idx"
                        >
                            <HcisBadge :label="ep.method" :variant="variantMethod(ep.method)" appearance="solid" size="xs" />
                            <span class="text-truncate font-sans" style="font-size: 0.78rem; max-width: 200px">{{ ep.name }}</span>
                        </button>
                    </div>

                    <!-- Active Endpoint Detail Card -->
                    <div v-if="endpointAktif" class="p-3.5 rounded-3 border bg-white shadow-sm d-flex flex-column gap-3">
                        <!-- Endpoint Header & SLA -->
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 pb-2.5 border-bottom border-slate-100">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <HcisBadge :label="endpointAktif.method" :variant="variantMethod(endpointAktif.method)" appearance="solid" size="xs" />
                                    <span class="font-monospace fw-bold text-dark" style="font-size: 0.95rem">{{ endpointAktif.path }}</span>
                                </div>
                                <div class="fw-semibold text-dark" style="font-size: 0.875rem">{{ endpointAktif.name }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <!-- Hanya bila angkanya ada; cadangan 'Maks 3.0s' dibuang.
                                     Angka yang tak seorang pun menjanjikannya tetap dibaca
                                     sebagai janji oleh yang melihatnya. -->
                                <span
                                    v-if="endpointAktif.sla_timeout"
                                    class="badge bg-amber-50 text-amber-800 border border-amber-200"
                                    style="font-size: 0.72rem"
                                >
                                    ⏱️ SLA: {{ endpointAktif.sla_timeout }}
                                </span>
                                <span class="badge bg-slate-100 text-muted font-monospace" style="font-size: 0.68rem">
                                    {{ endpointAktif.trigger_event }}
                                </span>
                            </div>
                        </div>

                        <!-- Purpose & Trigger Info Box -->
                        <div class="p-2.5 rounded-2 bg-slate-50 border border-slate-200 text-muted small" style="font-size: 0.78rem; line-height: 1.45">
                            <span class="fw-semibold text-dark">Tujuan & Pemicu: </span>
                            {{ endpointAktif.purpose }}
                        </div>

                        <!-- Sub-Segment Tabs: Payload | Response | Headers -->
                        <div class="d-flex gap-1.5 p-1 rounded-2 bg-slate-100 border">
                            <button
                                type="button"
                                class="btn btn-sm flex-fill rounded-1 fw-medium"
                                :class="subTabEndpoint === 'payload' ? 'btn-white bg-white text-dark shadow-sm' : 'btn-ghost text-muted'"
                                style="font-size: 0.75rem"
                                @click="subTabEndpoint = 'payload'"
                            >
                                <i class="bi bi-file-earmark-code me-1 text-primary"></i>Skema & Sample Payload
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm flex-fill rounded-1 fw-medium"
                                :class="subTabEndpoint === 'response' ? 'btn-white bg-white text-dark shadow-sm' : 'btn-ghost text-muted'"
                                style="font-size: 0.75rem"
                                @click="subTabEndpoint = 'response'"
                            >
                                <i class="bi bi-arrow-return-left me-1 text-success"></i>Format Respon Harapan Wajib
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm flex-fill rounded-1 fw-medium"
                                :class="subTabEndpoint === 'headers' ? 'btn-white bg-white text-dark shadow-sm' : 'btn-ghost text-muted'"
                                style="font-size: 0.75rem"
                                @click="subTabEndpoint = 'headers'"
                            >
                                <i class="bi bi-tag me-1 text-warning"></i>Header Request ({{ endpointAktif.headers?.length ?? 0 }})
                            </button>
                        </div>

                        <!-- SUB-TAB A: SKEMA & SAMPLE PAYLOAD -->
                        <div v-if="subTabEndpoint === 'payload'" class="d-flex flex-column gap-3">
                            <!-- Fields Table -->
                            <div v-if="endpointAktif.payload_fields?.length">
                                <div class="fw-bold text-dark small mb-1.5">Daftar Field Request Harapan Wajib:</div>
                                <div class="table-responsive rounded-2 border border-slate-200">
                                    <table class="table table-sm table-hover mb-0" style="font-size: 0.75rem">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 28%">Field</th>
                                                <th style="width: 22%">Tipe Data</th>
                                                <th style="width: 20%">Kewajiban</th>
                                                <th style="width: 30%">Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="pf in endpointAktif.payload_fields" :key="pf.field">
                                                <td><code class="font-monospace text-primary fw-bold">{{ pf.field }}</code></td>
                                                <td><span class="text-muted font-monospace">{{ pf.type }}</span></td>
                                                <td>
                                                    <span v-if="pf.required" class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.68rem">Harapan Wajib</span>
                                                    <span v-else class="badge bg-light text-muted border" style="font-size: 0.68rem">Opsional</span>
                                                </td>
                                                <td class="text-muted">{{ pf.description }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Sample JSON Body -->
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark small">Sample Payload JSON (Dikirim oleh HCIS):</span>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm py-0 px-2"
                                        style="font-size: 0.72rem"
                                        @click="salinTeks(JSON.stringify(endpointAktif.sample_payload, null, 2), 'JSON Payload berhasil disalin!')"
                                    >
                                        <i class="bi bi-clipboard me-1"></i>Salin JSON
                                    </button>
                                </div>
                                <div class="rounded-3 bg-slate-900 border border-slate-800 p-3">
                                    <pre class="text-indigo-200 font-monospace mb-0 custom-scrollbar" style="font-size: 0.73rem; max-height: 220px; overflow: auto"><code>{{ JSON.stringify(endpointAktif.sample_payload, null, 2) }}</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- SUB-TAB B: FORMAT RESPON HARAPAN WAJIB -->
                        <div v-else-if="subTabEndpoint === 'response'" class="d-flex flex-column gap-3">
                            <!-- Success 200 OK Response -->
                            <div class="p-3 rounded-2 border border-emerald-200 bg-emerald-50/40">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success" style="font-size: 0.75rem">
                                            HTTP {{ endpointAktif.expected_response?.success?.http_status ?? 200 }} OK
                                        </span>
                                        <span class="fw-bold text-emerald-900" style="font-size: 0.8rem">Respon Sukses Harapan Wajib</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-outline-success btn-sm py-0 px-2 bg-white"
                                        style="font-size: 0.72rem"
                                        @click="salinTeks(JSON.stringify(endpointAktif.expected_response?.success?.sample ?? { status: 'success', code: 200 }, null, 2), 'JSON Respon Sukses disalin!')"
                                    >
                                        <i class="bi bi-clipboard me-1"></i>Salin JSON
                                    </button>
                                </div>
                                <p class="text-muted small mb-2" style="font-size: 0.75rem">
                                    {{ endpointAktif.expected_response?.success?.description ?? 'Harapan wajib mengembalikan status success jika data berhasil diproses.' }}
                                </p>
                                <div class="rounded-2 bg-slate-900 border border-slate-800 p-2.5">
                                    <pre class="text-emerald-300 font-monospace mb-0 custom-scrollbar" style="font-size: 0.72rem; max-height: 140px; overflow: auto"><code>{{ JSON.stringify(endpointAktif.expected_response?.success?.sample ?? { status: 'success', code: 200 }, null, 2) }}</code></pre>
                                </div>
                            </div>

                            <!-- Error 422 / 400 Response -->
                            <div class="p-3 rounded-2 border border-amber-200 bg-amber-50/40">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-amber-600 text-white" style="font-size: 0.75rem">
                                            HTTP {{ endpointAktif.expected_response?.error?.http_status ?? 422 }} Error
                                        </span>
                                        <span class="fw-bold text-amber-900" style="font-size: 0.8rem">Respon Validasi Gagal</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm py-0 px-2 bg-white"
                                        style="font-size: 0.72rem"
                                        @click="salinTeks(JSON.stringify(endpointAktif.expected_response?.error?.sample ?? { status: 'error', code: 422 }, null, 2), 'JSON Respon Error disalin!')"
                                    >
                                        <i class="bi bi-clipboard me-1"></i>Salin JSON
                                    </button>
                                </div>
                                <p class="text-muted small mb-2" style="font-size: 0.75rem">
                                    {{ endpointAktif.expected_response?.error?.description ?? 'Kembalikan format ini jika validasi data gagal.' }}
                                </p>
                                <div class="rounded-2 bg-slate-900 border border-slate-800 p-2.5">
                                    <pre class="text-amber-200 font-monospace mb-0 custom-scrollbar" style="font-size: 0.72rem; max-height: 140px; overflow: auto"><code>{{ JSON.stringify(endpointAktif.expected_response?.error?.sample ?? { status: 'error', code: 422 }, null, 2) }}</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- SUB-TAB C: HEADER REQUEST HARAPAN WAJIB -->
                        <div v-else-if="subTabEndpoint === 'headers'" class="d-flex flex-column gap-2">
                            <div class="fw-bold text-dark small">Daftar HTTP Header Dikirimkan HCIS:</div>
                            <div class="table-responsive rounded-2 border border-slate-200">
                                <table class="table table-sm table-hover mb-0" style="font-size: 0.75rem">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 25%">Header</th>
                                            <th style="width: 20%">Tipe</th>
                                            <th style="width: 18%">Status</th>
                                            <th style="width: 37%">Deskripsi & Contoh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="hd in endpointAktif.headers" :key="hd.name">
                                            <td><code class="font-monospace text-primary fw-bold">{{ hd.name }}</code></td>
                                            <td><span class="text-muted font-monospace">{{ hd.type }}</span></td>
                                            <td>
                                                <span v-if="hd.required" class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.68rem">Harapan Wajib</span>
                                                <span v-else class="badge bg-light text-muted border" style="font-size: 0.68rem">Opsional</span>
                                            </td>
                                            <td>
                                                <div class="text-dark">{{ hd.description }}</div>
                                                <code class="text-muted font-monospace" style="font-size: 0.7rem">Ex: {{ hd.example }}</code>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════ -->
                <!-- TAB 2: AUTENTIKASI & KEAMANAN (HMAC-SHA256)              -->
                <!-- ═════════════════════════════════════════════════════════ -->
                <div v-else-if="tabAktifDrawer === 'security'" class="d-flex flex-column gap-3">
                    <!-- Security Parameter Cards Grid -->
                    <div class="row g-2.5">
                        <div class="col-6">
                            <div class="p-2.5 rounded-2 border bg-slate-50">
                                <div class="text-muted small fw-semibold" style="font-size: 0.7rem">METODE AUTENTIKASI</div>
                                <div class="fw-bold text-dark mt-0.5" style="font-size: 0.8125rem">{{ kontrakAktif.security?.auth_type ?? 'HMAC-SHA256 Signature' }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-2 border bg-slate-50">
                                <div class="text-muted small fw-semibold" style="font-size: 0.7rem">SECRET ENV VARIABLE</div>
                                <code class="fw-bold text-primary font-monospace mt-0.5 d-block" style="font-size: 0.8125rem">{{ kontrakAktif.security?.secret_env_name ?? 'WEBHOOK_SHARED_SECRET' }}</code>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-2 border bg-slate-50">
                                <div class="text-muted small fw-semibold" style="font-size: 0.7rem">SIGNATURE HEADER</div>
                                <code class="text-dark font-monospace mt-0.5 d-block" style="font-size: 0.75rem">{{ kontrakAktif.security?.signature_header ?? 'X-HC-Signature' }}</code>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-2 border bg-slate-50">
                                <div class="text-muted small fw-semibold" style="font-size: 0.7rem">TOLERANSI TIMESTAMP</div>
                                <div class="text-dark mt-0.5" style="font-size: 0.75rem">{{ kontrakAktif.security?.tolerance_seconds ?? 300 }} detik (5 menit)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step-by-Step Security Verification Workflow -->
                    <div class="p-3 rounded-3 border bg-white shadow-sm">
                        <h4 class="fw-bold text-dark fs-6 mb-2">Panduan Verifikasi Signature (Langkah demi Langkah)</h4>
                        <ol class="text-muted small ps-3 mb-0 d-flex flex-column gap-1.5" style="font-size: 0.78rem; line-height: 1.45">
                            <li><strong class="text-dark">Ambil Raw Body:</strong> Ambil string mentah dari HTTP request body persis apa adanya sebelum di-decode oleh middleware framework.</li>
                            <li><strong class="text-dark">Cek Timestamp:</strong> Ambil header <code>{{ kontrakAktif.security?.timestamp_header ?? 'X-HC-Timestamp' }}</code> dan pastikan selisih dengan waktu server tidak melebihi 300 detik untuk mencegah <em>Replay Attack</em>.</li>
                            <li><strong class="text-dark">Hitung Hash HMAC:</strong> Gunakan algoritma <code>sha256</code> dengan shared secret yang telah disepakati antara HCIS dan mitra.</li>
                            <li><strong class="text-dark">Bandingkan Hash:</strong> Bandingkan string <code>sha256={hash}</code> dengan nilai header <code>{{ kontrakAktif.security?.signature_header ?? 'X-HC-Signature' }}</code> menggunakan perbandingan waktu konstan (misal: <code>hash_equals</code> di PHP atau <code>timingSafeEqual</code> di Node.js).</li>
                        </ol>
                    </div>

                    <!-- Code Snippet Box (PHP / Node.js) -->
                    <div class="p-3 rounded-3 border bg-white shadow-sm d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-sm py-0.5 px-2.5 rounded-pill"
                                    :class="bahasaKode === 'php' ? 'btn-primary' : 'btn-outline-secondary'"
                                    style="font-size: 0.72rem"
                                    @click="bahasaKode = 'php'"
                                >
                                    PHP (Native / Laravel)
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm py-0.5 px-2.5 rounded-pill"
                                    :class="bahasaKode === 'nodejs' ? 'btn-primary' : 'btn-outline-secondary'"
                                    style="font-size: 0.72rem"
                                    @click="bahasaKode = 'nodejs'"
                                >
                                    Node.js (Express / Crypto)
                                </button>
                            </div>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm py-0.5 px-2"
                                style="font-size: 0.72rem"
                                @click="salinTeks(contohKodeVerifikasi, 'Contoh kode verifikasi disalin!')"
                            >
                                <i class="bi bi-clipboard me-1"></i>Salin Kode
                            </button>
                        </div>

                        <div class="rounded-3 bg-slate-900 border border-slate-800 p-3">
                            <pre class="text-emerald-300 font-monospace mb-0 custom-scrollbar" style="font-size: 0.72rem; max-height: 240px; overflow: auto"><code>{{ contohKodeVerifikasi }}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════ -->
                <!-- TAB 3: SKENARIO & KEBIJAKAN RETRY                        -->
                <!-- ═════════════════════════════════════════════════════════ -->
                <div v-else-if="tabAktifDrawer === 'scenario'" class="d-flex flex-column gap-3">
                    <!-- Skenario Bisnis Card -->
                    <div class="p-3 rounded-3 border bg-white shadow-sm">
                        <h4 class="fw-bold text-dark fs-6 mb-1.5">Skenario Bisnis & Alur Integrasi</h4>
                        <p class="text-muted small leading-relaxed mb-0" style="font-size: 0.8125rem">
                            {{ kontrakAktif.description }}
                        </p>
                    </div>

                    <!-- Kebijakan Retry & SLA Card -->
                    <div v-if="endpointAktif?.retry_policy" class="p-3 rounded-3 border bg-white shadow-sm d-flex flex-column gap-2.5">
                        <h4 class="fw-bold text-dark fs-6 mb-0">Kebijakan Pengiriman Ulang & Ketahanan (Retry Policy)</h4>
                        <p class="text-muted small mb-1" style="font-size: 0.75rem">
                            Jika endpoint sistem mitra mengalami kendala koneksi atau mengembalikan error server 5xx, HCIS menerapkan mekanisme pengiriman ulang otomatis:
                        </p>

                        <div class="table-responsive rounded-2 border border-slate-200">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem">
                                <tbody>
                                    <tr>
                                        <th class="table-light text-muted" style="width: 32%">Batas Maksimal Percobaan</th>
                                        <td class="fw-bold text-dark">{{ endpointAktif.retry_policy.max_attempts }}x Percobaan</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light text-muted">Skema Jeda (Backoff)</th>
                                        <td class="text-dark font-monospace">{{ endpointAktif.retry_policy.backoff }}</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light text-muted">Kode HTTP Yang Di-retry</th>
                                        <td class="text-success font-monospace">{{ endpointAktif.retry_policy.retryable_codes }}</td>
                                    </tr>
                                    <tr>
                                        <th class="table-light text-muted">Kode HTTP Non-Retryable</th>
                                        <td class="text-danger font-monospace">{{ endpointAktif.retry_policy.non_retryable_codes }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2.5 rounded-2 bg-indigo-50/70 border border-indigo-200 text-indigo-950 small" style="font-size: 0.75rem">
                            <strong>💡 Catatan Idempotensi:</strong> Gunakan <code>event_id</code> (UUID) pada setiap payload sebagai kunci idempotensi untuk mencegah duplikasi data jika terjadi retry jaringan.
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <HcisButton
                        variant="ghost"
                        size="md"
                        label="Tutup"
                        @click="drawerTerbuka = false"
                    />
                    <div class="d-flex gap-2">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="clipboard-check"
                            label="Salin Tautan Publik"
                            @click="kontrakAktif && salinTautan(kontrakAktif)"
                        />
                        <HcisButton
                            variant="primary"
                            size="md"
                            leading-icon="box-arrow-up-right"
                            label="Buka Halaman Publik Viewer"
                            @click="kontrakAktif && bukaTautan(tautanViewer(kontrakAktif))"
                        />
                    </div>
                </div>
            </template>
        </HcisDrawer>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
    HcisHeader,
    HcisStatGroup,
    HcisFilterBar,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisTable,
    HcisDrawer,
    HcisTabs,
    HcisEmptyState,
    HcisAlert,
    hcisMessage,
} from '@/components/ui';

defineOptions({ name: 'OutboundContractsPage' });

const props = defineProps({
    contracts: { type: Array, default: () => [] },
    targetSystems: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    baseUrl: { type: String, default: '' },
});

const pencarian = ref('');
const chipAktif = ref('all');
const modeTampilan = ref('table'); // 'table' | 'grid'

const filterValues = ref({
    system: '',
    category: '',
    status: '',
});

/* ── Drawer Detail State ─────────────────────────────────────────────── */
const drawerTerbuka = ref(false);
const kontrakAktif = ref(null);
const tabAktifDrawer = ref('endpoints'); // 'endpoints' | 'security' | 'scenario'
const endpointAktifIdx = ref(0);
const subTabEndpoint = ref('payload'); // 'payload' | 'response' | 'headers'
const bahasaKode = ref('php'); // 'php' | 'nodejs'

const tabsDrawer = computed(() => [
    {
        key: 'endpoints',
        label: 'Endpoint & Skema',
        icon: 'bezier2',
        badge: kontrakAktif.value ? jumlahEndpoint(kontrakAktif.value) : 0,
        badgeVariant: 'primary',
    },
    {
        key: 'security',
        label: 'Otentikasi & Keamanan',
        icon: 'shield-lock-fill',
    },
    {
        key: 'scenario',
        label: 'Skenario & Retry',
        icon: 'diagram-3-fill',
    },
]);

const endpointAktif = computed(() => {
    if (!kontrakAktif.value) return null;
    const eps = daftarEndpoint(kontrakAktif.value);
    return eps[endpointAktifIdx.value] ?? eps[0] ?? null;
});

const contohKodeVerifikasi = computed(() => {
    const sec = kontrakAktif.value?.security || {};
    const headerSig = sec.signature_header || 'X-HC-Signature';
    const headerTime = sec.timestamp_header || 'X-HC-Timestamp';
    const secretName = sec.secret_env_name || 'WEBHOOK_SHARED_SECRET';

    if (bahasaKode.value === 'php') {
        return `<?php
// 1. Ambil raw payload dan HTTP header dari request HCIS
$rawBody   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_${headerSig.toUpperCase().replace(/-/g, '_')}'] ?? '';
$timestamp = (int) ($_SERVER['HTTP_${headerTime.toUpperCase().replace(/-/g, '_')}'] ?? 0);
$secret    = getenv('${secretName}');

// 2. Cegah Replay Attack: toleransi waktu maksimal 300 detik (5 menit)
if (abs(time() - $timestamp) > 300) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Timestamp expired / deviasi terlalu besar']);
    exit;
}

// 3. Hitung HMAC-SHA256 dari raw payload
$expectedSig = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

// 4. Verifikasi signature dengan perbandingan waktu konstan
if (!hash_equals($expectedSig, $signature)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid HMAC Signature']);
    exit;
}

// 5. Payload valid! Lanjutkan pemrosesan bisnis
$data = json_decode($rawBody, true);
http_response_code(200);
echo json_encode(['status' => 'success', 'code' => 200, 'message' => 'Processed successfully']);
`;
    } else {
        return `const crypto = require('crypto');

// Middleware / Route Handler Express.js
app.post('/api/webhooks/*', express.raw({ type: 'application/json' }), (req, res) => {
    const rawBody   = req.body.toString('utf8');
    const signature = req.headers['${headerSig.toLowerCase()}'];
    const timestamp = parseInt(req.headers['${headerTime.toLowerCase()}'] || '0', 10);
    const secret    = process.env.${secretName};

    // 1. Validasi toleransi waktu (300 detik)
    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - timestamp) > 300) {
        return res.status(401).json({ status: 'error', message: 'Timestamp expired' });
    }

    // 2. Hitung HMAC-SHA256 signature
    const expectedSig = 'sha256=' + crypto.createHmac('sha256', secret).update(rawBody).digest('hex');

    // 3. Verifikasi tanda tangan secara timing-safe
    if (!crypto.timingSafeEqual(Buffer.from(signature || ''), Buffer.from(expectedSig))) {
        return res.status(401).json({ status: 'error', message: 'Invalid HMAC Signature' });
    }

    // 4. Sukses: proses payload JSON
    const payload = JSON.parse(rawBody);
    return res.status(200).json({ status: 'success', code: 200, message: 'Processed successfully' });
});
`;
    }
});

const kolom = [
    { key: 'title', label: 'Kontrak Integrasi', width: '28%' },
    { key: 'target_system', label: 'Sistem Tujuan', width: '18%' },
    { key: 'endpoint', label: 'Endpoint Harapan Wajib', width: '26%' },
    { key: 'status', label: 'Status & Versi', width: '12%' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '16%' },
];

const MAKS_ENDPOINT_TAMPIL = 2;

const daftarEndpoint = (row) => (Array.isArray(row?.endpoints) ? row.endpoints : []);
const jumlahEndpoint = (row) => daftarEndpoint(row).length;
const endpointTampil = (row) => daftarEndpoint(row).slice(0, MAKS_ENDPOINT_TAMPIL);
const sisaEndpoint = (row) => Math.max(0, jumlahEndpoint(row) - MAKS_ENDPOINT_TAMPIL);

/* ── Metrics Summary Calculation ─────────────────────────────────────── */
const totalEndpointKeseluruhan = computed(() =>
    props.contracts.reduce((acc, c) => acc + jumlahEndpoint(c), 0),
);

const totalAktif = computed(() =>
    props.contracts.filter((c) => c.status === 'ACTIVE').length,
);

const statCards = computed(() => [
    {
        id: 'stat-total-kontrak',
        label: 'Total Kontrak',
        value: props.contracts.length,
        sentiment: 'brand',
        icon: 'folder2-open',
        caption: 'Spesifikasi integrasi',
    },
    {
        id: 'stat-total-endpoint',
        label: 'Endpoint Harapan Wajib',
        value: totalEndpointKeseluruhan.value,
        sentiment: 'info',
        icon: 'bezier2',
        caption: 'Total rute yang diminta',
    },
    {
        id: 'stat-mitra-sistem',
        label: 'Sistem Pihak Ketiga',
        value: props.targetSystems.length,
        sentiment: 'neutral',
        icon: 'hdd-network',
        caption: 'Web Careers, LMS, dll',
    },
    {
        id: 'stat-aktif',
        label: 'Status Aktif',
        value: totalAktif.value,
        sentiment: 'success',
        icon: 'check2-circle',
        caption: 'Siap diimplementasikan',
    },
]);

/* ── FilterBar Quick Status Chips ────────────────────────────────────── */
const chipsSistem = computed(() => [
    {
        label: 'Semua Kontrak',
        value: 'all',
        badge: props.contracts.length,
        icon: 'grid-fill',
    },
    ...props.targetSystems.map((sys) => ({
        label: sys,
        value: sys,
        badge: props.contracts.filter((c) => c.target_system === sys).length,
        icon: sys.toLowerCase().includes('careers')
            ? 'globe2'
            : sys.toLowerCase().includes('assessment') || sys.toLowerCase().includes('psikotes')
                ? 'person-check-fill'
                : 'mortarboard-fill',
        color: sys.toLowerCase().includes('careers')
            ? 'brand'
            : sys.toLowerCase().includes('assessment') || sys.toLowerCase().includes('psikotes')
                ? 'purple'
                : 'success',
    })),
]);

/* ── FilterBar Dropdown Filters Schema ───────────────────────────────── */
const definisiFilter = computed(() => [
    {
        key: 'system',
        label: 'Sistem Tujuan',
        type: 'select',
        icon: 'hdd-network',
        col: 4,
        options: [
            { label: 'Semua Sistem', value: '' },
            ...props.targetSystems.map((s) => ({ label: s, value: s })),
        ],
    },
    {
        key: 'category',
        label: 'Kategori Integrasi',
        type: 'select',
        icon: 'tags-fill',
        col: 4,
        options: [
            { label: 'Semua Kategori', value: '' },
            ...props.categories.map((k) => ({ label: k, value: k })),
        ],
    },
    {
        key: 'status',
        label: 'Status Kontrak',
        type: 'select',
        icon: 'check2-circle',
        col: 4,
        options: [
            { label: 'Semua Status', value: '' },
            { label: 'Aktif (ACTIVE)', value: 'ACTIVE' },
            { label: 'Draft (DRAFT)', value: 'DRAFT' },
            { label: 'Deprecated', value: 'DEPRECATED' },
        ],
    },
]);

/* ── Filter Handlers ─────────────────────────────────────────────────── */
const handleChipClick = (chip) => {
    chipAktif.value = chip.value;
    filterValues.value.system = chip.value === 'all' ? '' : chip.value;
};

const handleFilterChange = (event) => {
    const vals = event?.values ?? filterValues.value;
    filterValues.value = { ...filterValues.value, ...vals };

    if (filterValues.value.system) {
        chipAktif.value = filterValues.value.system;
    } else {
        chipAktif.value = 'all';
    }
};

const handleResetFilter = () => {
    pencarian.value = '';
    chipAktif.value = 'all';
    filterValues.value = {
        system: '',
        category: '',
        status: '',
    };
};

/* ── Filtered Result Set ─────────────────────────────────────────────── */
const terpilih = computed(() => {
    const kata = pencarian.value.trim().toLowerCase();
    const { system, category, status } = filterValues.value;

    return props.contracts.filter((k) => {
        if (system && k.target_system !== system) return false;
        if (category && k.category !== category) return false;
        if (status && k.status !== status) return false;
        if (!kata) return true;

        const bahan = [k.title, k.slug, k.target_system, k.category, k.summary]
            .concat(daftarEndpoint(k).flatMap((ep) => [ep.path, ep.name, ep.method]));

        return bahan.some((n) => String(n ?? '').toLowerCase().includes(kata));
    });
});

const ringkasanSaringan = computed(() => {
    const endpoint = terpilih.value.reduce((n, k) => n + jumlahEndpoint(k), 0);
    const cakupan =
        terpilih.value.length === props.contracts.length
            ? `${props.contracts.length} kontrak terdaftar`
            : `${terpilih.value.length} dari ${props.contracts.length} kontrak ditampilkan`;

    return `${cakupan} · ${endpoint} endpoint diminta`;
});

/*
 * Tautan viewer datang JADI dari server, bertanda tangan.
 *
 * Sebelumnya halaman ini merangkainya sendiri dari baseUrl + slug. Sejak viewer
 * bergerbang `signed`, tautan rakitan seperti itu tampak benar di layar tetapi
 * selalu dijawab 403 — dan yang menemukannya bukan kita, melainkan vendor.
 * Karena itu tidak ada cadangan di sini: kalau prop-nya hilang, tombolnya mati
 * dan kita tahu, alih-alih menyebarkan tautan rusak.
 */
const tautanViewer = (row) => row?.viewer_url ?? '';

const variantMethod = (method) =>
    ({ GET: 'success', POST: 'brand', PUT: 'warning', PATCH: 'warning', DELETE: 'danger' })[method] ?? 'neutral';

const variantStatus = (status) =>
    ({ ACTIVE: 'success', DRAFT: 'warning', DEPRECATED: 'danger' })[status] ?? 'neutral';

const bukaTautan = (url) => window.open(url, '_blank', 'noopener');

const inspeksiKontrak = (row) => {
    kontrakAktif.value = row;
    endpointAktifIdx.value = 0;
    tabAktifDrawer.value = 'endpoints';
    subTabEndpoint.value = 'payload';
    drawerTerbuka.value = true;
};

const salinTautan = async (row) => {
    const tautan = tautanViewer(row);
    try {
        await navigator.clipboard.writeText(tautan);
        hcisMessage.success('Tautan viewer publik berhasil disalin ke clipboard.');
    } catch {
        hcisMessage.error(`Peramban menolak menyalin otomatis. Salin manual: ${tautan}`);
    }
};

const salinTeks = async (teks, pesanSukses) => {
    try {
        await navigator.clipboard.writeText(teks);
        hcisMessage.success(pesanSukses || 'Berhasil disalin!');
    } catch {
        hcisMessage.error('Gagal menyalin ke clipboard.');
    }
};
</script>

<style scoped>
.ko-path {
    font-size: 0.75rem;
    color: var(--hcis-text-secondary, var(--hcis-slate-600));
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50));
    padding: 0.15rem 0.35rem;
    border-radius: 0.25rem;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.ko-sisa {
    align-self: flex-start;
    padding: 0;
    border: 0;
    background: none;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    text-decoration: underline dotted;
    cursor: pointer;
}

.ko-sisa:hover {
    text-decoration-style: solid;
}

.ko-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.ko-card:hover {
    border-color: var(--hcis-brand-200, var(--hcis-indigo-200));
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08) !important;
}

.ko-tag {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid transparent;
}

.ko-tag--brand {
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    border-color: var(--hcis-brand-200, var(--hcis-indigo-200));
}

.ko-tag--neutral {
    background-color: var(--hcis-surface-soft, var(--hcis-slate-100));
    color: var(--hcis-text-secondary, var(--hcis-slate-500));
    border-color: var(--hcis-border-subtle, var(--hcis-slate-200));
}

.ko-tag--amber {
    background-color: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border-color: var(--hcis-amber-200);
}

.text-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: var(--hcis-slate-900);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-700);
    border-radius: 3px;
}
</style>
