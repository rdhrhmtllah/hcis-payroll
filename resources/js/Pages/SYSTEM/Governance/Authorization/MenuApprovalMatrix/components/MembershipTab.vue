<template>
    <div class="membership-tab">
        <div v-if="!grupId" class="empty-state">
            <i class="bi bi-arrow-up-circle"></i>
            <strong>Pilih Grup Fitur</strong>
            <span>Pilih grup untuk mulai mengelola keanggotaan tim.</span>
        </div>

        <div v-else-if="setupRequired && !loading" class="empty-state warning">
            <i class="bi bi-database-exclamation"></i>
            <strong>Config keanggotaan belum tersedia</strong>
            <span>System Admin perlu mengaktifkan config keanggotaan untuk grup ini.</span>
        </div>

        <approval-tabs
            v-else
            layout="sidebar"
            title="Keanggotaan"
            :tabs="tabs"
            :activeTab="activeTab"
            contentPadding="0rem"
            @tab-change="setActiveTab"
        >
            <template #summary>
                <main class="page-shell">
                    <header class="hero">
                        <div>
                            <span class="eyebrow"><i class="bi bi-diagram-3-fill"></i> Pusat kontrol keanggotaan</span>
                            <h4>Ringkasan Keanggotaan</h4>
                            <p>Pantau cakupan tim dan tangani struktur yang belum lengkap.</p>
                        </div>
                        <div class="hero-actions">
                            <button class="btn secondary" @click="loadOverview"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                            <button v-if="canAssign" class="btn primary" @click="setActiveTab('manage')"><i class="bi bi-people-fill"></i> Kelola Tim</button>
                        </div>
                    </header>

                    <section class="metrics-grid">
                        <article><i class="bi bi-pie-chart-fill indigo"></i><div><strong>{{ metrics.coverage_percent || 0 }}%</strong><span>Cakupan tim</span></div></article>
                        <article><i class="bi bi-person-check-fill green"></i><div><strong>{{ metrics.members || 0 }}</strong><span>Anggota terhubung</span></div></article>
                        <article><i class="bi bi-person-exclamation amber"></i><div><strong>{{ metrics.unassigned || 0 }}</strong><span>Belum memiliki tim</span></div></article>
                        <article><i class="bi bi-people-fill blue"></i><div><strong>{{ metrics.leaders || 0 }}</strong><span>Leader aktif</span></div></article>
                    </section>

                    <section v-if="advisoryIssues.length" class="advisory-panel">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>Perlu dipastikan</strong>
                            <p v-for="issue in advisoryIssues" :key="issue.type">
                                {{ issue.title }}. {{ issue.description }}
                            </p>
                        </div>
                    </section>

                    <section class="summary-grid">
                        <article class="card">
                            <header><div><i class="bi bi-exclamation-diamond"></i><strong>Perlu Ditangani</strong></div><span class="count warning">{{ actionableIssues.length }}</span></header>
                            <div v-if="!actionableIssues.length" class="healthy"><i class="bi bi-check-circle-fill"></i><strong>Struktur utama terlihat baik</strong></div>
                            <button v-for="issue in actionableIssues" :key="issue.type" class="issue-row" @click="handleIssue(issue)">
                                <i :class="issueIcon(issue.type)"></i>
                                <span><strong>{{ issue.title }}</strong><small>{{ issue.description }}</small></span>
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </article>
                        <article class="card">
                            <header><div><i class="bi bi-bar-chart-fill"></i><strong>Sebaran Tim</strong></div><span class="count">{{ leaders.length }}</span></header>
                            <button v-for="leader in leaders.slice(0, 8)" :key="leader.kode" class="distribution-row" @click="openLeader(leader)">
                                <span class="avatar">{{ initials(leader.nama) }}</span>
                                <span><strong>{{ leader.nama }}</strong><small>{{ leader.kode }}</small></span>
                                <b>{{ leader.member_count }}</b>
                            </button>
                        </article>
                    </section>
                </main>
            </template>

            <template #manage>
                <main class="page-shell manage-page">
                    <header class="manage-header">
                        <div>
                            <span class="eyebrow"><i class="bi bi-people-fill"></i> Workspace assignment cerdas</span>
                            <h4>Kelola Tim</h4>
                            <p>Pilih leader dan anggota. Sistem otomatis menangani single, bulk, dan transfer.</p>
                        </div>
                        <div class="manage-header-actions" v-if="canAssign">
                            <button class="btn primary" @click="openCreateTeam">
                                <i class="bi bi-person-plus-fill"></i> Buat Tim / Leader Baru
                            </button>
                        </div>
                    </header>

                    <section class="directory">
                        <aside class="leader-pane">
                            <div class="pane-title">
                                <div class="pane-title-left">
                                    <strong>Direktori Leader</strong>
                                    <span class="count">{{ leaders.length }}</span>
                                </div>
                                <button v-if="canAssign" type="button" class="btn-create-team-sm" @click="openCreateTeam" title="Buat Tim / Tambah Leader Baru">
                                    <i class="bi bi-plus-lg"></i> Tim Baru
                                </button>
                            </div>
                            <label class="search"><i class="bi bi-search"></i><input v-model.trim="leaderSearch" placeholder="Cari leader atau kode..." /></label>
                            <div class="leader-scroll">
                                <button
                                    v-for="leader in filteredLeaders"
                                    :key="leader.kode"
                                    :class="['leader-row', { active: selectedLeader?.kode === leader.kode }]"
                                    @click="selectLeader(leader)"
                                >
                                    <span class="avatar">{{ initials(leader.nama) }}</span>
                                    <span><strong>{{ leader.nama }}</strong><small>{{ leader.kode }}</small></span>
                                    <b>{{ leader.member_count }}</b>
                                </button>
                                <div v-if="!filteredLeaders.length" class="empty-state compact leader-empty">
                                    <i class="bi bi-person-x"></i>
                                    <small>{{ leaderSearch ? 'Leader tidak ditemukan' : 'Belum ada leader' }}</small>
                                    <button v-if="canAssign && !leaderSearch" class="btn secondary btn-xs" @click="openCreateTeam">
                                        <i class="bi bi-plus"></i> Buat Tim Baru
                                    </button>
                                </div>
                            </div>
                        </aside>

                        <section class="member-pane">
                            <header v-if="selectedLeader">
                                <span class="avatar large">{{ initials(selectedLeader.nama) }}</span>
                                <div><small>Tim terpilih</small><strong>{{ selectedLeader.nama }}</strong><span>{{ selectedLeader.kode }} · {{ selectedLeader.member_count }} anggota</span></div>
                                <div class="member-header-actions" v-if="canAssign">
                                    <button class="btn secondary" :disabled="!selectedLeader.members.length" @click="transferWholeTeam"><i class="bi bi-arrow-left-right"></i> Transfer seluruh tim</button>
                                    <button class="btn primary" @click="openPicker('add')"><i class="bi bi-person-plus-fill"></i> Tambah anggota</button>
                                </div>
                            </header>
                            <div v-if="!selectedLeader" class="empty-state empty-member-pane">
                                <div class="empty-icon-circle"><i class="bi bi-diagram-3-fill"></i></div>
                                <strong>Pilih Leader atau Buat Tim Baru</strong>
                                <p>Pilih salah satu leader di direktori sebelah kiri untuk mengelola anggotanya, atau mulai buat struktur tim baru.</p>
                                <button v-if="canAssign" class="btn primary" @click="openCreateTeam">
                                    <i class="bi bi-person-plus-fill"></i> Buat Tim / Leader Baru
                                </button>
                            </div>
                            <template v-else>
                                <div class="member-tools">
                                    <label class="search"><i class="bi bi-search"></i><input v-model.trim="memberSearch" placeholder="Cari anggota tim..." /></label>
                                    <button class="btn ghost" @click="toggleAllVisible"><i class="bi bi-check2-square"></i> {{ allVisibleSelected ? 'Batal pilih semua' : 'Pilih semua' }}</button>
                                </div>
                                <div class="member-scroll">
                                    <label v-for="member in filteredMembers" :key="member.kode" :class="['member-row', { selected: selectedCodes.includes(member.kode) }]">
                                        <input v-if="canAssign" type="checkbox" :value="member.kode" v-model="selectedCodes" />
                                        <span class="avatar">{{ initials(member.nama) }}</span>
                                        <span><strong>{{ member.nama }}</strong><small>{{ member.kode }}</small></span>
                                        <em>Anggota {{ selectedLeader.nama }}</em>
                                    </label>
                                    <div v-if="!filteredMembers.length" class="empty-state compact"><i class="bi bi-inbox"></i><strong>Belum ada anggota</strong><small>Klik tombol "Tambah anggota" di atas untuk menambahkan anggota ke tim ini.</small></div>
                                </div>
                            </template>
                        </section>
                    </section>

                    <div v-if="selectedCodes.length && canAssign" class="selection-bar">
                        <button class="selection-summary" @click="selectionOpen = !selectionOpen">
                            <span><i class="bi bi-check2-circle"></i> {{ selectedCodes.length }} anggota dipilih</span>
                            <i :class="selectionOpen ? 'bi bi-chevron-down' : 'bi bi-chevron-up'"></i>
                        </button>
                        <div class="selection-actions">
                            <button class="btn secondary" @click="openPicker('move')"><i class="bi bi-arrow-left-right"></i> Pindahkan</button>
                            <button class="btn danger" @click="openPicker('remove')"><i class="bi bi-person-dash-fill"></i> Lepaskan</button>
                            <button class="btn ghost" @click="selectedCodes = []"><i class="bi bi-x-lg"></i> Bersihkan</button>
                        </div>
                        <div v-if="selectionOpen" class="selection-tray">
                            <button v-for="code in selectedCodes" :key="code" @click="removeSelection(code)">{{ memberName(code) }} <i class="bi bi-x"></i></button>
                        </div>
                    </div>
                </main>
            </template>

            <template #history>
                <main class="page-shell">
                    <header class="manage-header">
                        <div><span class="eyebrow"><i class="bi bi-clock-history"></i> Audit trail</span><h4>Riwayat Keanggotaan</h4><p>Satu baris mewakili satu transaksi perubahan.</p></div>
                        <button class="btn secondary" @click="loadHistory"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                    </header>
                    <label class="search history-search"><i class="bi bi-search"></i><input v-model.trim="historySearch" placeholder="Cari transaksi, admin, atau karyawan..." /></label>
                    <section class="history-list">
                        <article v-for="item in filteredHistory" :key="item.transaction_no" class="history-card">
                            <button class="history-main" @click="toggleHistory(item.transaction_no)">
                                <span class="history-icon"><i class="bi bi-people-fill"></i></span>
                                <span><strong>{{ transactionTitle(item) }}</strong><small>{{ item.actor }} · {{ formatDate(item.created_at) }}</small></span>
                                <b>{{ item.count }} perubahan</b><i class="bi bi-chevron-down"></i>
                            </button>
                            <div v-if="expandedHistory === item.transaction_no" class="history-detail">
                                <div v-for="detail in item.details" :key="`${item.transaction_no}-${detail.member_code}`" class="history-detail-row">
                                    <span :class="['impact-dot', detail.action.toLowerCase()]"></span>
                                    <span><strong>{{ detail.member_name }}</strong><small>{{ actionSentence(detail) }}</small></span>
                                </div>
                                <button v-if="canAssign" class="btn secondary rollback-btn" @click="previewRollback(item)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Batalkan transaksi
                                </button>
                            </div>
                        </article>
                        <div v-if="!filteredHistory.length" class="empty-state"><i class="bi bi-clock-history"></i><strong>Belum ada riwayat</strong></div>
                    </section>
                </main>
            </template>
        </approval-tabs>

        <el-drawer v-model="drawerOpen" :size="drawerSize" :with-header="false" :close-on-click-modal="!operationLoading" :close-on-press-escape="!operationLoading" destroy-on-close @closed="resetDrawerState">
            <div class="drawer-shell">
                <header class="drawer-header">
                    <div><span class="eyebrow">{{ drawerKicker }}</span><h4>{{ drawerTitle }}</h4><p>{{ drawerDescription }}</p></div>
                    <button class="icon-btn" :disabled="operationLoading" @click="closeDrawer"><i class="bi bi-x-lg"></i></button>
                </header>

                <!-- ADD / CREATE TEAM MODE -->
                <template v-if="drawerMode === 'add' && !previewData">
                    <!-- Step 1: Leader Tim -->
                    <div class="leader-step-card">
                        <div class="leader-section-header">
                            <div class="step-title-group">
                                <span class="step-badge">1</span>
                                <div>
                                    <label class="step-label">Leader Tim <span class="text-danger">*</span></label>
                                    <small class="step-sub">{{ isNewTeamFlow ? 'Pilih karyawan yang akan menjadi leader tim baru ini' : 'Leader yang membawahi anggota tim' }}</small>
                                </div>
                            </div>
                            <span v-if="selectedTargetLeader" class="leader-status-hint">
                                <span v-if="selectedTargetLeader.is_active_leader" class="badge-active-leader">
                                    <i class="bi bi-shield-check"></i> Leader aktif
                                </span>
                                <span v-else class="badge-new-leader">
                                    <i class="bi bi-person-plus-fill"></i> Leader baru
                                </span>
                            </span>
                        </div>

                        <div ref="leaderPickerContainer" class="leader-selector-wrapper">
                            <!-- Trigger Button / Display -->
                            <div
                                class="leader-select-trigger"
                                :class="{ 'is-open': leaderPickerOpen, 'has-value': !!selectedTargetLeader, 'is-disabled': operationLoading, 'highlight-pulse': !targetLeaderCode }"
                                @click="toggleLeaderPicker"
                            >
                                <template v-if="selectedTargetLeader">
                                    <div class="selected-leader-display">
                                        <span class="avatar mini" :class="{ 'is-leader': selectedTargetLeader.is_active_leader }">
                                            {{ initials(selectedTargetLeader.nama) }}
                                        </span>
                                        <div class="selected-info">
                                            <div class="selected-name-row">
                                                <strong>{{ selectedTargetLeader.nama }}</strong>
                                                <small class="code-badge">({{ selectedTargetLeader.kode }})</small>
                                            </div>
                                            <small class="selected-meta">{{ [selectedTargetLeader.divisi, selectedTargetLeader.lokasi].filter(Boolean).join(' · ') || 'Karyawan Aktif' }}</small>
                                        </div>
                                    </div>
                                    <div class="trigger-actions">
                                        <button type="button" class="btn-change-leader" :disabled="operationLoading" @click.stop="toggleLeaderPicker">
                                            Ganti <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="placeholder-display">
                                        <i class="bi bi-person-plus"></i>
                                        <span>Klik untuk mencari dan memilih leader...</span>
                                    </div>
                                    <i class="bi bi-chevron-down trigger-chevron"></i>
                                </template>
                            </div>

                            <!-- Dropdown Popup Panel -->
                            <div v-if="leaderPickerOpen" class="leader-dropdown-panel" @click.stop>
                                <header class="leader-dropdown-header">
                                    <div class="search-input-box">
                                        <i class="bi bi-search"></i>
                                        <input
                                            ref="leaderSearchInput"
                                            v-model="leaderPickerSearch"
                                            type="text"
                                            placeholder="Cari nama atau kode karyawan..."
                                            @keydown.esc="leaderPickerOpen = false"
                                        />
                                        <button v-if="leaderPickerSearch" type="button" class="clear-search-btn" @click="leaderPickerSearch = ''">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                    </div>
                                    <div class="leader-filter-pills">
                                        <button
                                            type="button"
                                            :class="{ active: leaderPickerFilter === 'all' }"
                                            @click="leaderPickerFilter = 'all'"
                                        >
                                            Semua ({{ targetLeaderOptions.length }})
                                        </button>
                                        <button
                                            type="button"
                                            :class="{ active: leaderPickerFilter === 'leaders' }"
                                            @click="leaderPickerFilter = 'leaders'"
                                        >
                                            <i class="bi bi-shield-check"></i> Leader Aktif ({{ activeLeaderCount }})
                                        </button>
                                    </div>
                                </header>

                                <div class="leader-options-list">
                                    <div v-if="leaderLoading && !targetLeaderOptions.length" class="empty-state compact">
                                        <i class="bi bi-arrow-repeat spin"></i>
                                        <small>Memuat daftar leader & karyawan...</small>
                                    </div>
                                    <template v-else-if="filteredTargetLeaders.length">
                                        <button
                                            v-for="leader in filteredTargetLeaders"
                                            :key="leader.kode"
                                            type="button"
                                            class="leader-option-row"
                                            :class="{ selected: targetLeaderCode === leader.kode }"
                                            @click="selectTargetLeader(leader)"
                                        >
                                            <span class="avatar mini" :class="{ 'is-leader': leader.is_active_leader }">
                                                {{ initials(leader.nama) }}
                                            </span>
                                            <div class="option-details">
                                                <div class="option-name-row">
                                                    <strong>{{ leader.nama }}</strong>
                                                    <span class="option-code">({{ leader.kode }})</span>
                                                    <span v-if="leader.is_active_leader" class="tag-active-leader">
                                                        Leader ({{ leader.member_count || 0 }} tim)
                                                    </span>
                                                </div>
                                                <small class="option-meta">
                                                    {{ [leader.divisi, leader.lokasi].filter(Boolean).join(' · ') || 'Karyawan Aktif' }}
                                                </small>
                                            </div>
                                            <i v-if="targetLeaderCode === leader.kode" class="bi bi-check2 check-icon"></i>
                                        </button>
                                    </template>
                                    <div v-else class="empty-state compact">
                                        <i class="bi bi-search"></i>
                                        <strong>Tidak ditemukan</strong>
                                        <small>Tidak ada karyawan dengan kata kunci "{{ leaderPickerSearch }}"</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Pilih Anggota Tim -->
                    <div class="step-title-group member-step-title-group">
                        <span class="step-badge">2</span>
                        <div>
                            <label class="step-label">Pilih Anggota Tim <span class="text-danger">*</span></label>
                            <small class="step-sub">{{ selectedTargetLeader ? `Pilih karyawan yang akan masuk ke tim ${selectedTargetLeader.nama}` : 'Pilih karyawan yang akan ditugaskan ke tim ini' }}</small>
                        </div>
                    </div>

                    <div class="filter-tabs">
                        <button v-for="filter in assignmentFilters" :key="filter.value" :disabled="pickerLoading || operationLoading" :class="{ active: pickerFilters.assignment_status === filter.value }" @click="setAssignmentFilter(filter.value)">{{ filter.label }}</button>
                    </div>
                    <div class="filter-grid">
                        <div class="filter-field search-field">
                            <small><i class="bi bi-search"></i> Cari karyawan</small>
                            <label class="search"><input v-model.trim="pickerFilters.search" :disabled="operationLoading" @input="debouncedSearch" placeholder="Nama atau kode karyawan..." /></label>
                        </div>
                        <div class="filter-field">
                            <small><i class="bi bi-diagram-3"></i> Divisi</small>
                            <el-select v-model="pickerFilters.divisi" filterable clearable :disabled="operationLoading" placeholder="Semua divisi" @change="searchPicker">
                                <el-option v-for="division in filterOptions.divisions" :key="division.value" :label="division.label" :value="String(division.value)" />
                            </el-select>
                        </div>
                        <div class="filter-field">
                            <small><i class="bi bi-geo-alt"></i> Lokasi</small>
                            <el-select v-model="pickerFilters.cabang" filterable clearable :disabled="operationLoading" placeholder="Semua lokasi" @change="searchPicker">
                                <el-option v-for="location in filterOptions.locations" :key="location" :label="location" :value="location" />
                            </el-select>
                        </div>
                    </div>
                    <div class="picker-list">
                        <div v-if="pickerLoading" class="empty-state compact"><i class="bi bi-arrow-repeat spin"></i><strong>Mencari karyawan...</strong><small>Menyiapkan hasil terbaru</small></div>
                        <template v-else>
                            <label v-for="employee in pickerResults" :key="employee.kode" :class="['picker-row', { selected: selectedCodes.includes(employee.kode), disabled: employee.in_target_team || employee.is_target_leader || operationLoading }]">
                                <input type="checkbox" :value="employee.kode" v-model="selectedCodes" :disabled="employee.in_target_team || employee.is_target_leader || operationLoading" />
                                <span class="avatar">{{ initials(employee.nama) }}</span>
                                <span><strong>{{ employee.nama }}</strong><small>{{ employee.kode }} · {{ employee.divisi || '-' }} · {{ employee.lokasi || '-' }}</small></span>
                                <em v-if="employee.is_target_leader" class="self-leader">Leader tujuan</em>
                                <em v-else-if="employee.in_target_team" class="joined">Sudah di tim ini</em>
                                <span v-else-if="employee.assigned" class="leader-summary">
                                    <strong>{{ employee.current_leader_name }}</strong>
                                    <el-tooltip v-if="employee.current_leaders?.length > 1" placement="left" effect="dark" popper-class="membership-leader-popper">
                                        <template #content>
                                            <div class="leader-tooltip">
                                                <small>Terhubung dengan {{ employee.current_leaders.length }} leader</small>
                                                <span v-for="leader in employee.current_leaders" :key="leader.code"><b>{{ leader.name }}</b><em>{{ leader.code }}</em></span>
                                            </div>
                                        </template>
                                        <button type="button" class="more-leaders" @click.prevent.stop>+{{ employee.current_leaders.length - 1 }}</button>
                                    </el-tooltip>
                                </span>
                                <em v-else class="free">Belum punya tim</em>
                            </label>
                            <div v-if="!pickerResults.length" class="empty-state compact"><i class="bi bi-search"></i><strong>Karyawan tidak ditemukan</strong><small>Coba ubah kata kunci atau filter.</small></div>
                        </template>
                    </div>
                </template>

                <!-- MOVE / TRANSFER MODE -->
                <template v-if="drawerMode === 'move' && !previewData">
                    <div class="leader-step-card">
                        <div class="leader-section-header">
                            <div class="step-title-group">
                                <span class="step-badge">1</span>
                                <div>
                                    <label class="step-label">Leader Tujuan Transfer <span class="text-danger">*</span></label>
                                    <small class="step-sub">Pilih leader yang akan menerima anggota yang dipindahkan</small>
                                </div>
                            </div>
                            <span v-if="selectedTargetLeader" class="leader-status-hint">
                                <span v-if="selectedTargetLeader.is_active_leader" class="badge-active-leader">
                                    <i class="bi bi-shield-check"></i> Leader aktif
                                </span>
                                <span v-else class="badge-new-leader">
                                    <i class="bi bi-person-plus-fill"></i> Leader baru
                                </span>
                            </span>
                        </div>

                        <div ref="leaderPickerContainer" class="leader-selector-wrapper">
                            <!-- Trigger Button / Display -->
                            <div
                                class="leader-select-trigger"
                                :class="{ 'is-open': leaderPickerOpen, 'has-value': !!selectedTargetLeader, 'is-disabled': operationLoading, 'highlight-pulse': !targetLeaderCode }"
                                @click="toggleLeaderPicker"
                            >
                                <template v-if="selectedTargetLeader">
                                    <div class="selected-leader-display">
                                        <span class="avatar mini" :class="{ 'is-leader': selectedTargetLeader.is_active_leader }">
                                            {{ initials(selectedTargetLeader.nama) }}
                                        </span>
                                        <div class="selected-info">
                                            <div class="selected-name-row">
                                                <strong>{{ selectedTargetLeader.nama }}</strong>
                                                <small class="code-badge">({{ selectedTargetLeader.kode }})</small>
                                            </div>
                                            <small class="selected-meta">{{ [selectedTargetLeader.divisi, selectedTargetLeader.lokasi].filter(Boolean).join(' · ') || 'Karyawan Aktif' }}</small>
                                        </div>
                                    </div>
                                    <div class="trigger-actions">
                                        <button type="button" class="btn-change-leader" :disabled="operationLoading" @click.stop="toggleLeaderPicker">
                                            Ganti <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="placeholder-display">
                                        <i class="bi bi-person-plus"></i>
                                        <span>Cari atau pilih leader tujuan transfer...</span>
                                    </div>
                                    <i class="bi bi-chevron-down trigger-chevron"></i>
                                </template>
                            </div>

                            <!-- Dropdown Popup Panel -->
                            <div v-if="leaderPickerOpen" class="leader-dropdown-panel" @click.stop>
                                <header class="leader-dropdown-header">
                                    <div class="search-input-box">
                                        <i class="bi bi-search"></i>
                                        <input
                                            ref="leaderSearchInput"
                                            v-model="leaderPickerSearch"
                                            type="text"
                                            placeholder="Cari nama atau kode karyawan..."
                                            @keydown.esc="leaderPickerOpen = false"
                                        />
                                        <button v-if="leaderPickerSearch" type="button" class="clear-search-btn" @click="leaderPickerSearch = ''">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                    </div>
                                    <div class="leader-filter-pills">
                                        <button
                                            type="button"
                                            :class="{ active: leaderPickerFilter === 'all' }"
                                            @click="leaderPickerFilter = 'all'"
                                        >
                                            Semua ({{ targetLeaderOptions.length }})
                                        </button>
                                        <button
                                            type="button"
                                            :class="{ active: leaderPickerFilter === 'leaders' }"
                                            @click="leaderPickerFilter = 'leaders'"
                                        >
                                            <i class="bi bi-shield-check"></i> Leader Aktif ({{ activeLeaderCount }})
                                        </button>
                                    </div>
                                </header>

                                <div class="leader-options-list">
                                    <div v-if="leaderLoading && !targetLeaderOptions.length" class="empty-state compact">
                                        <i class="bi bi-arrow-repeat spin"></i>
                                        <small>Memuat daftar leader & karyawan...</small>
                                    </div>
                                    <template v-else-if="filteredTargetLeaders.length">
                                        <button
                                            v-for="leader in filteredTargetLeaders"
                                            :key="leader.kode"
                                            type="button"
                                            class="leader-option-row"
                                            :class="{ selected: targetLeaderCode === leader.kode }"
                                            @click="selectTargetLeader(leader)"
                                        >
                                            <span class="avatar mini" :class="{ 'is-leader': leader.is_active_leader }">
                                                {{ initials(leader.nama) }}
                                            </span>
                                            <div class="option-details">
                                                <div class="option-name-row">
                                                    <strong>{{ leader.nama }}</strong>
                                                    <span class="option-code">({{ leader.kode }})</span>
                                                    <span v-if="leader.is_active_leader" class="tag-active-leader">
                                                        Leader ({{ leader.member_count || 0 }} tim)
                                                    </span>
                                                </div>
                                                <small class="option-meta">
                                                    {{ [leader.divisi, leader.lokasi].filter(Boolean).join(' · ') || 'Karyawan Aktif' }}
                                                </small>
                                            </div>
                                            <i v-if="targetLeaderCode === leader.kode" class="bi bi-check2 check-icon"></i>
                                        </button>
                                    </template>
                                    <div v-else class="empty-state compact">
                                        <i class="bi bi-search"></i>
                                        <strong>Tidak ditemukan</strong>
                                        <small>Tidak ada karyawan dengan kata kunci "{{ leaderPickerSearch }}"</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="!previewData" class="drawer-selection">
                    <div><strong>{{ selectedCodes.length }} anggota dipilih</strong><button :disabled="operationLoading" @click="selectedCodes = []">Bersihkan</button></div>
                    <div class="chip-list"><button v-for="code in selectedCodes" :key="code" :disabled="operationLoading" @click="removeSelection(code)">{{ memberName(code) }} <i class="bi bi-x"></i></button></div>
                </div>

                <div v-if="previewData" class="preview-shell">
                    <div class="impact-grid">
                        <article><strong>{{ previewData.counts.add }}</strong><span>Ditambahkan</span></article>
                        <article><strong>{{ previewData.counts.move }}</strong><span>Dipindahkan</span></article>
                        <article><strong>{{ previewData.counts.remove + selectedReleaseTotal }}</strong><span>Dilepaskan</span></article>
                        <article><strong>{{ previewData.counts.skip + previewData.counts.conflict }}</strong><span>Dilewati</span></article>
                    </div>
                    <div class="preview-list">
                        <div v-for="item in previewData.items" :key="item.member_code" :class="['preview-row', 'impact-card', `is-${item.impact}`, { warning: item.impact === 'add' && item.has_other_teams }]">
                            <header>
                                <span :class="['impact-dot', item.impact]"></span>
                                <span><strong>{{ item.member_name }}</strong><small>{{ item.member_code }}</small></span>
                                <em>{{ impactLabel(item) }}</em>
                            </header>
                            <div class="leader-impact">
                                <div v-if="impactFrom(item)" class="leader-impact-cell">
                                    <small>{{ item.impact === 'remove' ? 'Dilepas dari' : 'Dari leader' }}</small>
                                    <strong>{{ impactFrom(item) }}</strong>
                                </div>
                                <i v-if="impactFrom(item) && impactTo(item)" class="bi bi-arrow-right"></i>
                                <div v-if="impactTo(item)" class="leader-impact-cell target">
                                    <small>{{ item.impact === 'add' ? 'Ditambahkan ke' : 'Menjadi leader' }}</small>
                                    <strong>{{ impactTo(item) }}</strong>
                                </div>
                            </div>
                            <div v-if="item.impact === 'add' && item.current_leaders?.length" class="team-choice-panel">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    <header>
                                        <span><strong>Masih terhubung dengan tim lain</strong><small>Pilih tim yang ingin dilepaskan setelah anggota ditambahkan.</small></span>
                                        <b>{{ releaseCount(item.member_code) }} dilepas</b>
                                    </header>
                                    <div class="team-choice-list">
                                        <button v-for="leader in item.current_leaders" :key="leader.code" type="button" :disabled="operationLoading" :class="{ release: isTeamReleased(item.member_code, leader.code) }" @click="toggleReleaseTeam(item.member_code, leader.code)">
                                            <span><small>{{ leader.code }}</small><strong>{{ leader.name }}</strong></span>
                                            <em><i :class="isTeamReleased(item.member_code, leader.code) ? 'bi bi-person-dash-fill' : 'bi bi-link-45deg'"></i>{{ isTeamReleased(item.member_code, leader.code) ? 'Akan dilepas' : 'Tetap terhubung' }}</em>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="item.retained_leader_names?.length" class="retained-teams">
                                <i class="bi bi-info-circle"></i>
                                <span><small>Tim lain tetap terhubung</small><strong>{{ item.retained_leader_names.join(', ') }}</strong></span>
                            </div>
                            <div v-if="item.skip_reason" class="impact-reason"><i class="bi bi-exclamation-circle"></i>{{ item.skip_reason }}</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <label>Catatan perubahan <small>(opsional)</small></label>
                    <textarea v-model="note" rows="3" :disabled="operationLoading" placeholder="Contoh: penyesuaian struktur organisasi"></textarea>
                </div>

                <div v-if="operationLoading" class="processing-banner">
                    <i class="bi bi-arrow-repeat spin"></i>
                    <span><strong>{{ processingTitle }}</strong><small>Mohon tunggu, jangan tutup drawer.</small></span>
                </div>

                <footer class="drawer-footer">
                    <button class="btn secondary" :disabled="operationLoading" @click="previewData ? previewData = null : closeDrawer()">{{ previewData ? 'Kembali' : 'Batal' }}</button>
                    <button v-if="!previewData" class="btn primary" :disabled="!canPreview" @click="proceedOperation">
                        <i :class="previewLoading ? 'bi bi-arrow-repeat spin' : directAddEligible ? 'bi bi-person-check-fill' : 'bi bi-eye-fill'"></i>
                        {{ submitButtonText }}
                    </button>
                    <button v-else class="btn primary" :disabled="saving" @click="executeOperation"><i :class="saving ? 'bi bi-arrow-repeat spin' : 'bi bi-check2-circle'"></i> {{ saving ? 'Memproses...' : 'Terapkan perubahan' }}</button>
                </footer>
            </div>
        </el-drawer>

        <el-dialog v-model="rollbackOpen" title="Batalkan Transaksi" width="min(680px, 94vw)">
            <div v-if="rollbackPreview" class="rollback-preview">
                <div class="impact-grid"><article><strong>{{ rollbackPreview.ready }}</strong><span>Siap dipulihkan</span></article><article><strong>{{ rollbackPreview.conflicts }}</strong><span>Konflik dilewati</span></article></div>
                <div class="preview-list">
                    <div v-for="item in rollbackPreview.items" :key="item.member_code" class="preview-row">
                        <span :class="['impact-dot', item.status]"></span><span><strong>{{ item.member_name }}</strong><small>{{ rollbackSentence(item) }}</small></span><em>{{ item.status === 'ready' ? 'Siap' : 'Konflik' }}</em>
                    </div>
                </div>
            </div>
            <template #footer><button class="btn secondary" @click="rollbackOpen = false">Batal</button><button class="btn danger" :disabled="saving || !rollbackPreview?.ready" @click="executeRollback"><i class="bi bi-arrow-counterclockwise"></i> Batalkan yang valid</button></template>
        </el-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';

export default {
    name: 'MembershipTab',
    components: { ApprovalTabs },
    props: { grupId: { type: Number, default: null }, canAssign: { type: Boolean, default: false } },
    data: () => ({
        activeTab: 'summary', loading: false, setupRequired: false, selectedConfigId: null,
        metrics: {}, issues: [], leaders: [], candidateLeaders: [], selectedLeaderCode: '', leaderSearch: '', memberSearch: '',
        selectedCodes: [], selectionOpen: false, drawerOpen: false, drawerMode: 'add', isNewTeamFlow: false, previewData: null,
        pickerResults: [], pickerLoading: false, pickerTimer: null, pickerRequestId: 0,
        pickerFilters: { search: '', divisi: '', cabang: '', assignment_status: 'all' }, filterOptions: { divisions: [], locations: [] },
        targetLeaderCode: '', targetLeaderOptions: [], leaderLoading: false, leaderPickerOpen: false, leaderPickerSearch: '', leaderPickerFilter: 'all',
        previewLoading: false, previewRequestId: 0, note: '', saving: false, releaseSelections: {},
        history: [], historySearch: '', expandedHistory: '', rollbackOpen: false, rollbackPreview: null, rollbackItem: null,
    }),
    computed: {
        tabs() { return [{ id: 'summary', label: 'Ringkasan', icon: 'bi bi-grid-1x2-fill', section: 'Keanggotaan' }, { id: 'manage', label: 'Kelola Tim', icon: 'bi bi-people-fill', section: 'Keanggotaan' }, { id: 'history', label: 'Riwayat', icon: 'bi bi-clock-history', section: 'Audit Trail' }]; },
        filteredLeaders() { const q = this.leaderSearch.toLowerCase(); return this.leaders.filter(l => `${l.nama} ${l.kode}`.toLowerCase().includes(q)); },
        selectedLeader() { return this.leaders.find(l => l.kode === this.selectedLeaderCode) || this.filteredLeaders[0] || null; },
        filteredMembers() { const q = this.memberSearch.toLowerCase(); return (this.selectedLeader?.members || []).filter(m => `${m.nama} ${m.kode}`.toLowerCase().includes(q)); },
        allVisibleSelected() { return !!this.filteredMembers.length && this.filteredMembers.every(m => this.selectedCodes.includes(m.kode)); },
        drawerTitle() {
            if (this.isNewTeamFlow) return 'Buat Tim / Tambah Leader';
            return { add: 'Tambah Anggota Tim', move: 'Transfer Anggota', remove: 'Lepaskan Anggota' }[this.drawerMode];
        },
        drawerKicker() {
            if (this.previewData) return 'Preview dampak perubahan';
            if (this.isNewTeamFlow) return 'Struktur tim baru';
            return 'Smart assignment';
        },
        drawerDescription() {
            if (this.previewData) return 'Periksa dampak terbaru sebelum perubahan diterapkan.';
            if (this.isNewTeamFlow) return this.targetLeaderCode ? `Tentukan anggota tim yang akan dipimpin oleh ${this.targetLeaderName}.` : 'Pilih karyawan sebagai leader baru dan tentukan anggota timnya.';
            return this.drawerMode === 'add' ? `Tambahkan atau pindahkan karyawan ke tim ${this.targetLeaderName || 'leader tujuan'}.` : this.drawerMode === 'move' ? 'Pindahkan anggota terpilih ke satu leader tujuan.' : 'Lepaskan anggota terpilih dari tim saat ini.';
        },
        drawerSize() { return window.innerWidth < 768 ? '100%' : 'min(720px, 94vw)'; },
        targetLeaderName() { return this.selectedTargetLeader?.nama || this.leaders.find(l => l.kode === this.targetLeaderCode)?.nama; },
        selectedTargetLeader() {
            if (!this.targetLeaderCode) return null;
            return this.targetLeaderOptions.find(l => l.kode === this.targetLeaderCode)
                || this.leaders.find(l => l.kode === this.targetLeaderCode)
                || { kode: this.targetLeaderCode, nama: this.targetLeaderCode, is_active_leader: false };
        },
        filteredTargetLeaders() {
            const q = (this.leaderPickerSearch || '').trim().toLowerCase();
            let list = this.targetLeaderOptions || [];
            if (this.leaderPickerFilter === 'leaders') {
                list = list.filter(l => l.is_active_leader);
            }
            if (!q) return list.slice(0, 150);
            return list.filter(l =>
                (l.nama && l.nama.toLowerCase().includes(q)) ||
                (l.kode && l.kode.toLowerCase().includes(q)) ||
                (l.divisi && l.divisi.toLowerCase().includes(q)) ||
                (l.lokasi && l.lokasi.toLowerCase().includes(q))
            ).slice(0, 150);
        },
        activeLeaderCount() {
            return (this.targetLeaderOptions || []).filter(l => l.is_active_leader).length;
        },
        submitButtonText() {
            if (this.previewLoading) return 'Menyiapkan...';
            if (this.drawerMode !== 'remove' && !this.targetLeaderCode) return 'Pilih leader tim';
            if (!this.selectedCodes.length) return 'Pilih anggota tim';
            if (this.directAddEligible) return 'Tambahkan sekarang';
            return 'Tinjau perubahan';
        },
        canPreview() {
            if (this.operationLoading) return false;
            if (!this.selectedCodes.length) return false;
            if (this.drawerMode === 'remove') return true;
            return !!this.targetLeaderCode && !this.selectedCodes.includes(this.targetLeaderCode);
        },
        directAddEligible() { const employee = this.pickerResults.find(item => item.kode === this.selectedCodes[0]); return this.drawerMode === 'add' && this.selectedCodes.length === 1 && employee && !employee.assigned && employee.kode !== this.targetLeaderCode && !!this.targetLeaderCode; },
        assignmentFilters() { return [{ value: 'all', label: 'Semua' }, { value: 'unassigned', label: 'Belum punya tim' }, { value: 'leader', label: 'Tim ini' }, { value: 'other', label: 'Tim lain' }]; },
        operationLoading() { return this.previewLoading || this.saving; },
        selectedReleaseTotal() { return Object.values(this.releaseSelections).reduce((total, leaders) => total + leaders.length, 0); },
        processingTitle() { return this.previewLoading ? 'Menyusun preview perubahan...' : this.drawerMode === 'move' ? 'Memindahkan anggota...' : this.drawerMode === 'remove' ? 'Melepaskan anggota...' : 'Menyimpan keanggotaan...'; },
        memberMap() { const map = {}; this.leaders.forEach(l => l.members.forEach(m => { map[m.kode] = m.nama; })); this.pickerResults.forEach(m => { map[m.kode] = m.nama; }); return map; },
        filteredHistory() { const q = this.historySearch.toLowerCase(); return this.history.filter(h => !q || `${h.transaction_no} ${h.actor} ${h.details.map(d => `${d.member_name} ${d.member_code}`).join(' ')}`.toLowerCase().includes(q)); },
        advisoryIssues() { return this.issues.filter(issue => issue.severity === 'info'); },
        actionableIssues() { return this.issues.filter(issue => issue.severity !== 'info'); },
    },
    watch: {
        grupId: { immediate: true, handler() { this.resetWorkspace(); this.loadOverview(); } },
        activeTab(value) { if (value === 'history') this.loadHistory(); },
    },
    mounted() {
        document.addEventListener('click', this.handleDocumentClick);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.handleDocumentClick);
    },
    methods: {
        setActiveTab(tab) { this.activeTab = tab; },
        async loadOverview() {
            if (!this.grupId) return;
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/membership/${this.grupId}`, { params: { config_id: this.selectedConfigId || undefined } });
                this.setupRequired = !!data.setup_required; this.selectedConfigId = data.selected_config_id;
                this.metrics = data.metrics || {}; this.issues = data.issues || []; this.leaders = data.leaders || [];
                if (!this.leaders.some(l => l.kode === this.selectedLeaderCode)) this.selectedLeaderCode = this.leaders[0]?.kode || '';
                this.loadLeaderOptions();
            } catch (e) { ElMessage.error(e.response?.data?.message || 'Gagal memuat keanggotaan.'); } finally { this.loading = false; }
        },
        resetWorkspace() { this.selectedConfigId = null; this.activeTab = 'summary'; this.drawerOpen = false; this.resetDrawerState(); },
        openLeader(leader) { this.selectedLeaderCode = leader.kode; this.activeTab = 'manage'; },
        selectLeader(leader) { this.selectedLeaderCode = leader.kode; this.selectedCodes = []; },
        toggleAllVisible() { const visible = this.filteredMembers.map(m => m.kode); this.selectedCodes = this.allVisibleSelected ? this.selectedCodes.filter(c => !visible.includes(c)) : [...new Set([...this.selectedCodes, ...visible])]; },
        transferWholeTeam() { this.selectedCodes = this.selectedLeader.members.map(m => m.kode); this.openPicker('move'); },
        openCreateTeam() {
            this.drawerMode = 'add';
            this.isNewTeamFlow = true;
            this.previewData = null;
            this.note = '';
            this.targetLeaderCode = '';
            this.selectedCodes = [];
            this.drawerOpen = true;
            this.leaderPickerOpen = true;
            this.leaderPickerSearch = '';
            this.leaderPickerFilter = 'all';
            this.rebuildLeaderOptions();
            if (!this.candidateLeaders || !this.candidateLeaders.length) {
                this.loadLeaderOptions();
            }
            this.searchPicker();
            this.$nextTick(() => {
                this.$refs.leaderSearchInput?.focus();
            });
        },
        openPicker(mode) {
            this.drawerMode = mode;
            this.isNewTeamFlow = false;
            this.previewData = null;
            this.note = '';
            this.drawerOpen = true;
            this.leaderPickerOpen = false;
            this.leaderPickerSearch = '';
            this.leaderPickerFilter = 'all';

            if (mode === 'add') {
                this.selectedCodes = [];
                this.targetLeaderCode = this.selectedLeader?.kode || (this.leaders[0]?.kode || '');
                this.setAssignmentFilter('all');
            } else if (mode === 'move') {
                this.targetLeaderCode = '';
            }

            this.rebuildLeaderOptions();
            if (!this.candidateLeaders || !this.candidateLeaders.length) {
                this.loadLeaderOptions();
            }
        },
        closeDrawer() { if (!this.operationLoading) this.drawerOpen = false; },
        resetDrawerState() {
            clearTimeout(this.pickerTimer);
            this.pickerRequestId += 1;
            this.previewRequestId += 1;
            this.drawerMode = 'add'; this.isNewTeamFlow = false; this.previewData = null; this.pickerResults = []; this.pickerLoading = false; this.previewLoading = false; this.leaderLoading = false; this.releaseSelections = {};
            this.pickerFilters = { search: '', divisi: '', cabang: '', assignment_status: 'all' };
            this.targetLeaderCode = ''; this.note = ''; this.selectedCodes = []; this.selectionOpen = false;
            this.leaderPickerOpen = false; this.leaderPickerSearch = '';
            this.rebuildLeaderOptions();
        },
        toggleLeaderPicker() {
            if (this.operationLoading) return;
            this.leaderPickerOpen = !this.leaderPickerOpen;
            if (this.leaderPickerOpen) {
                this.leaderPickerSearch = '';
                this.$nextTick(() => {
                    this.$refs.leaderSearchInput?.focus();
                });
            }
        },
        selectTargetLeader(leader) {
            this.targetLeaderCode = leader.kode;
            this.leaderPickerOpen = false;
            this.handleTargetLeaderChange();
        },
        clearTargetLeader(e) {
            if (e) e.stopPropagation();
            this.targetLeaderCode = '';
            this.leaderPickerOpen = false;
            this.handleTargetLeaderChange();
        },
        handleDocumentClick(e) {
            if (this.leaderPickerOpen && this.$refs.leaderPickerContainer && !this.$refs.leaderPickerContainer.contains(e.target)) {
                this.leaderPickerOpen = false;
            }
        },
        setAssignmentFilter(value) { this.pickerFilters.assignment_status = value; this.searchPicker(); },
        debouncedSearch() { clearTimeout(this.pickerTimer); this.pickerLoading = true; this.pickerTimer = setTimeout(this.searchPicker, 350); },
        async searchPicker() {
            if (!this.selectedConfigId) return;
            clearTimeout(this.pickerTimer);
            const requestId = ++this.pickerRequestId;
            this.pickerLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/membership/${this.grupId}/employees`, { params: { type: 'member', config_id: this.selectedConfigId, leader_code: this.targetLeaderCode || this.selectedLeader?.kode, limit: 200, ...this.pickerFilters } });
                if (requestId !== this.pickerRequestId) return;
                this.pickerResults = data.items || [];
                if (data.filter_options) this.filterOptions = data.filter_options;
                const disabledCodes = new Set(this.pickerResults.filter(item => item.in_target_team || item.is_target_leader).map(item => item.kode));
                this.selectedCodes = this.selectedCodes.filter(code => !disabledCodes.has(code));
            } catch (e) {
                if (requestId === this.pickerRequestId) ElMessage.error(e.response?.data?.message || 'Gagal mencari karyawan.');
            } finally { if (requestId === this.pickerRequestId) this.pickerLoading = false; }
        },
        handleTargetLeaderChange() {
            if (this.selectedCodes.includes(this.targetLeaderCode)) {
                this.selectedCodes = this.selectedCodes.filter(code => code !== this.targetLeaderCode);
                ElMessage.warning('Leader tujuan dikeluarkan dari pilihan karena tidak dapat menjadi anggota dirinya sendiri.');
            }
            if (this.drawerMode === 'add') this.searchPicker();
        },
        async loadLeaderOptions() {
            if (!this.selectedConfigId) return;
            this.leaderLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/membership/${this.grupId}/employees`, {
                    params: {
                        type: 'leader',
                        config_id: this.selectedConfigId,
                        limit: 1000,
                    },
                });
                this.candidateLeaders = data.items || [];
                this.rebuildLeaderOptions();
            } catch (e) {
                console.error('Failed to load leader options:', e);
            } finally {
                this.leaderLoading = false;
            }
        },
        rebuildLeaderOptions() {
            const map = new Map();
            if (Array.isArray(this.leaders)) {
                this.leaders.forEach(l => {
                    map.set(l.kode, {
                        kode: l.kode,
                        nama: l.nama,
                        member_count: l.member_count,
                        divisi: l.divisi || '',
                        lokasi: l.lokasi || '',
                        is_active_leader: true,
                    });
                });
            }
            (this.candidateLeaders || []).forEach(item => {
                if (!map.has(item.kode)) {
                    map.set(item.kode, {
                        kode: item.kode,
                        nama: item.nama,
                        member_count: 0,
                        divisi: item.divisi || '',
                        lokasi: item.lokasi || '',
                        is_active_leader: false,
                    });
                }
            });
            if (this.targetLeaderCode && !map.has(this.targetLeaderCode)) {
                const prev = this.targetLeaderOptions.find(o => o.kode === this.targetLeaderCode);
                if (prev) {
                    map.set(prev.kode, prev);
                } else if (this.selectedLeader && this.selectedLeader.kode === this.targetLeaderCode) {
                    map.set(this.selectedLeader.kode, {
                        kode: this.selectedLeader.kode,
                        nama: this.selectedLeader.nama,
                        member_count: this.selectedLeader.member_count,
                        divisi: this.selectedLeader.divisi || '',
                        lokasi: this.selectedLeader.lokasi || '',
                        is_active_leader: true,
                    });
                }
            }
            this.targetLeaderOptions = Array.from(map.values());
        },
        async previewOperation() {
            if (this.previewLoading) return null;
            const requestId = ++this.previewRequestId;
            this.previewLoading = true;
            try {
                const { data } = await axios.post(`/api/v1/menu-approval-matrix/membership/${this.grupId}/preview`, { config_id: this.selectedConfigId, action: this.drawerMode === 'remove' ? 'remove' : 'assign', mode: this.drawerMode, leader_code: this.targetLeaderCode || null, source_leader_code: this.drawerMode === 'add' ? null : this.selectedLeader?.kode, member_codes: this.selectedCodes }, { timeout: 15000 });
                if (requestId !== this.previewRequestId) return null;
                this.previewData = data;
                this.releaseSelections = {};
                return data;
            } catch (e) {
                if (requestId === this.previewRequestId) ElMessage.error(e.code === 'ECONNABORTED' ? 'Preview terlalu lama. Silakan coba kembali.' : (e.response?.data?.message || 'Gagal meninjau perubahan.'));
                return null;
            } finally {
                if (requestId === this.previewRequestId) this.previewLoading = false;
            }
        },
        async proceedOperation() {
            const preview = await this.previewOperation();
            if (this.directAddEligible && preview?.counts?.add === 1 && !preview.counts.conflict) await this.executeOperation();
        },
        async executeOperation() {
            if (!this.previewData) return;
            this.saving = true;
            try {
                const action = this.drawerMode === 'remove' ? 'remove' : 'assign';
                const savedLeaderCode = this.targetLeaderCode;
                const payload = {
                    config_id: this.selectedConfigId,
                    member_codes: this.selectedCodes,
                    source_leader_code: this.drawerMode === 'add' ? null : this.selectedLeader?.kode,
                    note: this.note || null,
                    expected_assignments: this.previewData.expected_assignments,
                };
                if (action === 'assign') {
                    payload.leader_code = this.targetLeaderCode;
                    payload.mode = this.drawerMode;
                    payload.release_assignments = this.drawerMode === 'add' ? this.releaseSelections : {};
                }
                const { data } = await axios.post(`/api/v1/menu-approval-matrix/membership/${this.grupId}/${action}`, payload);
                const result = data.detail || data;
                ElMessage.success(`${result.affected || 0} perubahan keanggotaan diterapkan${result.conflicts?.length ? `, ${result.conflicts.length} konflik dilewati` : ''}.`);
                this.selectedCodes = [];
                this.drawerOpen = false;
                if (savedLeaderCode) {
                    this.selectedLeaderCode = savedLeaderCode;
                }
                await this.loadOverview();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menerapkan perubahan.');
            } finally {
                this.saving = false;
            }
        },
        handleIssue(issue) { this.activeTab = 'manage'; if (issue.type === 'unassigned') this.openPicker('add'); },
        removeSelection(code) { this.selectedCodes = this.selectedCodes.filter(c => c !== code); },
        memberName(code) { return this.memberMap[code] || code; },
        async loadHistory() { try { const { data } = await axios.get(`/api/v1/menu-approval-matrix/membership/${this.grupId}/history`); this.history = data.items || []; } catch (e) { ElMessage.error(e.response?.data?.message || 'Gagal memuat riwayat.'); } },
        toggleHistory(no) { this.expandedHistory = this.expandedHistory === no ? '' : no; },
        async previewRollback(item) {
            try { const { data } = await axios.post(`/api/v1/menu-approval-matrix/membership/${this.grupId}/history/${encodeURIComponent(item.transaction_no)}/rollback`, { execute: false }); this.rollbackPreview = data; this.rollbackItem = item; this.rollbackOpen = true; }
            catch (e) { ElMessage.error(e.response?.data?.message || 'Gagal meninjau pembatalan.'); }
        },
        async executeRollback() {
            this.saving = true;
            try { const { data } = await axios.post(`/api/v1/menu-approval-matrix/membership/${this.grupId}/history/${encodeURIComponent(this.rollbackItem.transaction_no)}/rollback`, { execute: true }); ElMessage.success(`${data.affected || 0} perubahan berhasil dipulihkan.`); this.rollbackOpen = false; await Promise.all([this.loadOverview(), this.loadHistory()]); }
            catch (e) { ElMessage.error(e.response?.data?.message || 'Gagal membatalkan transaksi.'); } finally { this.saving = false; }
        },
        transactionTitle(item) { const a = item.actions || {}; return a.ASSIGN && a.REMOVE ? `Menambahkan ${a.ASSIGN} dan melepaskan ${a.REMOVE} relasi tim` : a.MOVE ? `Transfer ${a.MOVE} anggota` : a.REMOVE ? `Melepaskan ${a.REMOVE} anggota` : `Menambahkan ${a.ASSIGN || item.count} anggota`; },
        actionSentence(d) { return d.action === 'MOVE' ? `Dipindahkan dari ${d.before || '-'} ke ${d.after || '-'}` : d.action === 'REMOVE' ? `Dilepaskan dari ${d.before || '-'}` : `Ditambahkan ke ${d.after || '-'}`; },
        impactSentence(i) { return i.impact === 'add' ? i.has_other_teams ? `Ditambahkan ke ${i.target_leader_name}. Saat ini juga berada di tim: ${i.current_leader_names.join(', ')}` : `Ditambahkan ke ${i.target_leader_name}` : i.impact === 'move' ? `${i.current_leader_name} → ${i.target_leader_name}` : i.impact === 'remove' ? `Akan dilepaskan dari ${i.current_leader_name}` : i.impact === 'conflict' ? 'Leader tidak dapat menjadi anggota dirinya sendiri' : 'Sudah berada pada kondisi tujuan'; },
        impactLabel(item) { return item.impact === 'add' && item.has_other_teams ? 'Tambah · Periksa' : ({ add: 'Tambah', move: 'Transfer', remove: 'Lepas', skip: 'Lewati', conflict: 'Konflik' }[item.impact] || item.impact); },
        impactFrom(item) {
            if (item.impact === 'add') return null;
            if (item.impact === 'skip' && !item.source_leader_name) return null;
            return item.source_leader_name || item.current_leader_name || item.current_leader_names?.[0] || null;
        },
        impactTo(item) { return item.impact === 'remove' ? null : (item.target_leader_name || (item.impact === 'skip' ? item.current_leader_name : null)); },
        isTeamReleased(memberCode, leaderCode) { return (this.releaseSelections[memberCode] || []).includes(leaderCode); },
        releaseCount(memberCode) { return (this.releaseSelections[memberCode] || []).length; },
        toggleReleaseTeam(memberCode, leaderCode) {
            const selected = new Set(this.releaseSelections[memberCode] || []);
            selected.has(leaderCode) ? selected.delete(leaderCode) : selected.add(leaderCode);
            const next = { ...this.releaseSelections };
            if (selected.size) next[memberCode] = [...selected];
            else delete next[memberCode];
            this.releaseSelections = next;
        },
        rollbackSentence(i) { return i.status === 'conflict' ? `Kondisi terbaru berubah menjadi ${i.current_leader || 'tanpa tim'}; tidak akan ditimpa` : `Pulihkan dari ${i.current_leader || 'tanpa tim'} ke ${i.restore_to || 'tanpa tim'}`; },
        issueIcon(type) { return { unassigned: 'bi bi-person-exclamation', duplicate: 'bi bi-people-fill', inactive: 'bi bi-person-x-fill' }[type] || 'bi bi-exclamation-circle'; },
        initials(name) { return String(name || '?').split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); },
        formatDate(value) { return value ? new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'; },
    },
};
</script>

<style scoped>
.membership-tab{min-width:0;color:#172554}.page-shell{padding:16px;background:#f7f9fd;min-height:540px}.hero,.manage-header{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:18px 20px;border:1px solid #dbe5f5;border-radius:16px;background:linear-gradient(135deg,var(--hcis-indigo-50),#f0fdfa)}h4{margin:3px 0 5px;font-weight:800}p{margin:0;color:var(--hcis-slate-500);font-size:12px}.eyebrow{color:var(--hcis-indigo-600);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.hero-actions,.manage-header-actions,.member-header-actions,.selection-actions,.drawer-footer{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.btn{min-height:38px;padding:8px 13px;border:1px solid transparent;border-radius:10px;font-size:12px;font-weight:700;background:#fff}.btn.primary{background:#5b5ce2;color:#fff}.btn.secondary{border-color:#d8e0ee;color:var(--hcis-slate-700)}.btn.danger{background:#fff1f2;border-color:#fecdd3;color:var(--hcis-rose-700)}.btn.ghost{color:var(--hcis-slate-500)}.btn:disabled{opacity:.45}.btn-xs{min-height:26px;padding:3px 9px;font-size:10px;border-radius:7px}.metrics-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px}.metrics-grid article{display:flex;gap:12px;align-items:center;padding:15px;background:#fff;border:1px solid #e5eaf3;border-radius:14px}.metrics-grid i{font-size:20px}.metrics-grid div{display:grid}.metrics-grid strong{font-size:20px}.metrics-grid span{font-size:10px;color:var(--hcis-slate-500)}.indigo{color:var(--hcis-indigo-500)}.green{color:var(--hcis-emerald-500)}.amber{color:var(--hcis-amber-500)}.blue{color:var(--hcis-blue-500)}.advisory-panel{display:flex;align-items:flex-start;gap:10px;margin-top:12px;padding:12px 14px;border:1px solid var(--hcis-amber-200);border-radius:13px;background:var(--hcis-amber-50);color:var(--hcis-amber-800)}.advisory-panel>i{margin-top:2px;color:var(--hcis-amber-600)}.advisory-panel div{display:grid;gap:3px}.advisory-panel strong{font-size:11px}.advisory-panel p{color:var(--hcis-yellow-700);font-size:10px}.summary-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:12px;margin-top:12px}.card{padding:14px;background:#fff;border:1px solid #e5eaf3;border-radius:14px}.card header,.pane-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.card header div{display:flex;gap:8px;align-items:center}.pane-title-left{display:flex;align-items:center;gap:8px}.btn-create-team-sm{padding:4px 9px;border:1px solid var(--hcis-indigo-200);border-radius:8px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-700);font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:all .15s ease}.btn-create-team-sm:hover{background:var(--hcis-indigo-100);border-color:var(--hcis-indigo-300)}.count{padding:3px 8px;border-radius:99px;background:var(--hcis-indigo-50);font-size:10px}.count.warning{background:#fff7ed;color:#c2410c}.issue-row,.distribution-row,.leader-row,.history-main{width:100%;display:flex;align-items:center;gap:9px;padding:9px;border:0;border-top:1px solid #edf1f6;background:transparent;text-align:left}.issue-row span,.distribution-row span:nth-child(2),.leader-row span:nth-child(2),.history-main span:nth-child(2){min-width:0;flex:1;display:grid}.issue-row small,.distribution-row small,.leader-row small,.history-main small{font-size:10px;color:#718096;overflow-wrap:anywhere}.healthy,.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;min-height:130px;color:var(--hcis-slate-500);text-align:center}.healthy i{font-size:25px;color:var(--hcis-emerald-500)}.empty-state i{font-size:28px}.empty-state.compact{min-height:100px}.empty-member-pane{padding:30px 20px;gap:10px;text-align:center}.empty-member-pane p{max-width:320px;font-size:11px;color:var(--hcis-slate-500);margin:0}.empty-icon-circle{width:52px;height:52px;border-radius:50%;background:var(--hcis-indigo-50);color:var(--hcis-indigo-600);display:grid;place-items:center;font-size:24px;margin-bottom:4px}.leader-empty{padding:20px 10px;gap:6px}.avatar{width:32px;height:32px;display:grid;place-items:center;flex:none;border-radius:10px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-600);font-size:10px;font-weight:800}.avatar.large{width:42px;height:42px}.directory{display:grid;grid-template-columns:minmax(230px,4fr) minmax(0,8fr);height:clamp(440px,62vh,620px);margin-top:12px;border:1px solid #dfe6f0;border-radius:16px;overflow:hidden;background:#fff}.leader-pane,.member-pane{min-height:0;display:grid;grid-template-rows:auto auto minmax(0,1fr)}.leader-pane{background:#fafbfe;border-right:1px solid #e8edf5}.pane-title,.leader-pane>.search,.member-tools{margin:0;padding:12px}.leader-scroll,.member-scroll,.picker-list,.preview-list{min-height:0;overflow-y:auto;overscroll-behavior:contain}.leader-row.active{background:var(--hcis-indigo-50);border-left:3px solid var(--hcis-indigo-500)}.leader-row b,.distribution-row b{padding:3px 8px;border-radius:99px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-600);font-size:10px}.member-pane{grid-template-rows:auto auto minmax(0,1fr)}.member-pane>header{display:flex;align-items:center;gap:10px;padding:13px 15px;border-bottom:1px solid #edf1f6}.member-pane>header>div:nth-child(2){display:grid;min-width:0;flex:1}.member-pane>header small{font-size:9px;text-transform:uppercase;color:var(--hcis-slate-500);font-weight:800}.member-pane>header span{font-size:10px;color:var(--hcis-slate-500)}.member-tools{display:flex;gap:8px;border-bottom:1px solid #edf1f6}.search{display:flex;align-items:center;gap:8px;min-height:38px;padding:7px 10px;border:1px solid #dbe3ef;border-radius:10px;background:#fff}.search input{width:100%;border:0;outline:0;font-size:12px}.member-tools .search{flex:1}.member-row,.picker-row{display:flex;gap:9px;align-items:center;padding:10px 14px;border-bottom:1px solid #edf1f6}.member-row.selected,.picker-row.selected{background:#f5f7ff}.member-row span:nth-of-type(2),.picker-row span:nth-of-type(2){display:grid;min-width:0;flex:1}.member-row small,.picker-row small{font-size:10px;color:#718096}.member-row em,.picker-row em{font-size:9px;color:var(--hcis-amber-700);font-style:normal}.picker-row em.free{color:var(--hcis-emerald-600)}.selection-bar{position:sticky;z-index:5;bottom:10px;display:flex;align-items:center;gap:10px;margin:10px;padding:10px;border:1px solid var(--hcis-indigo-200);border-radius:14px;background:#fff;box-shadow:0 12px 30px #3341551f}.selection-summary{display:flex;justify-content:space-between;gap:10px;min-width:180px;border:0;background:transparent;color:var(--hcis-indigo-700);font-weight:800}.selection-tray{position:absolute;left:0;right:0;bottom:calc(100% + 6px);display:flex;gap:6px;flex-wrap:wrap;max-height:150px;overflow:auto;padding:10px;border:1px solid #dbe3ef;border-radius:12px;background:#fff;box-shadow:0 10px 25px #3341551f}.selection-tray button,.chip-list button{border:0;border-radius:99px;padding:5px 8px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-700);font-size:10px}.history-search{margin:12px 0}.history-list{display:grid;gap:8px}.history-card{border:1px solid #e1e7f0;border-radius:13px;background:#fff;overflow:hidden}.history-icon{width:34px;height:34px;display:grid!important;place-items:center!important;flex:none!important;border-radius:10px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-600)}.history-main b{font-size:10px}.history-detail{padding:5px 14px 13px;background:#fafbfe}.history-detail-row,.preview-row{display:flex;gap:9px;align-items:center;padding:8px 0;border-bottom:1px solid #edf1f6}.history-detail-row span:nth-child(2),.preview-row span:nth-child(2){display:grid;min-width:0;flex:1}.history-detail-row small,.preview-row small{font-size:10px;color:var(--hcis-slate-500)}.rollback-btn{margin-top:10px}.drawer-shell{height:100%;display:flex;flex-direction:column}.drawer-header{display:flex;justify-content:space-between;gap:12px;padding-bottom:13px;border-bottom:1px solid #e8edf5}.icon-btn{width:38px;height:38px;border:1px solid #dbe3ef;border-radius:10px;background:#fff}.step-title-group{display:flex;align-items:flex-start;gap:8px;margin-bottom:8px}.step-badge{width:20px;height:20px;border-radius:50%;background:#5b5ce2;color:#fff;font-size:10px;font-weight:800;display:grid;place-items:center;flex-shrink:0;margin-top:1px}.step-label{font-size:11px;font-weight:800;color:#1e293b;margin:0;display:block}.step-sub{font-size:10px;color:var(--hcis-slate-500);display:block}.leader-step-card{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#fafcff;margin-top:12px;margin-bottom:12px}.member-step-title-group{margin-top:4px;margin-bottom:8px}.filter-tabs{display:flex;gap:5px;margin:8px 0 10px;overflow-x:auto}.filter-tabs button{white-space:nowrap;padding:7px 10px;border:1px solid #dbe3ef;border-radius:99px;background:#fff;font-size:10px}.filter-tabs button.active{background:var(--hcis-indigo-50);border-color:var(--hcis-indigo-300);color:var(--hcis-indigo-700);font-weight:800}.filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(150px,.55fr) minmax(150px,.55fr);align-items:end;gap:9px;margin-bottom:12px;padding:10px;border:1px solid #e5eaf3;border-radius:13px;background:var(--hcis-slate-50)}.filter-field{display:grid;gap:5px;min-width:0}.filter-field>small{display:flex;align-items:center;gap:5px;color:var(--hcis-slate-500);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.filter-field>small i{color:var(--hcis-indigo-500)}.filter-field .search{min-height:40px;padding:4px 10px;border-color:#dbe3ef}.form-section textarea{width:100%;padding:9px;border:1px solid #dbe3ef;border-radius:10px;font-size:11px}.picker-list{flex:1;border:1px solid #e5eaf3;border-radius:12px}.form-section{display:grid;gap:6px;margin-top:12px}.form-section label{font-size:10px;font-weight:800}.form-section small{color:var(--hcis-slate-400)}.drawer-selection{margin-top:12px;padding:10px;border:1px solid #dbe3ef;border-radius:12px}.drawer-selection>div:first-child{display:flex;justify-content:space-between;font-size:11px}.drawer-selection button{border:0;background:transparent;color:var(--hcis-indigo-600);font-size:10px}.chip-list{display:flex;gap:5px;flex-wrap:wrap;max-height:95px;overflow:auto;margin-top:8px}.drawer-footer{justify-content:flex-end;margin-top:auto;padding-top:14px}.impact-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:14px 0}.impact-grid article{display:grid;padding:10px;border-radius:11px;background:var(--hcis-slate-50);border:1px solid #e5eaf3}.impact-grid strong{font-size:18px}.impact-grid span{font-size:9px;color:var(--hcis-slate-500)}.preview-list{max-height:360px}.preview-row em{font-size:9px;font-style:normal}.impact-dot{width:8px;height:8px;flex:none;border-radius:50%;background:var(--hcis-slate-400)}.impact-dot.add,.impact-dot.assign,.impact-dot.ready{background:var(--hcis-emerald-500)}.impact-dot.move{background:var(--hcis-amber-500)}.impact-dot.remove,.impact-dot.conflict{background:var(--hcis-red-500)}.spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:900px){.metrics-grid{grid-template-columns:repeat(2,1fr)}.summary-grid{grid-template-columns:1fr}.directory{grid-template-columns:1fr;grid-template-rows:minmax(220px,38vh) minmax(300px,50vh);height:auto}.leader-pane{border-right:0;border-bottom:1px solid #e8edf5}.selection-bar{flex-wrap:wrap}.selection-actions{width:100%}.filter-grid{grid-template-columns:1fr 1fr}.filter-grid .search-field{grid-column:1/-1}}@media(max-width:600px){.page-shell{padding:9px}.hero,.manage-header{align-items:stretch;flex-direction:column}.hero-actions .btn,.manage-header-actions .btn,.member-header-actions .btn,.selection-actions .btn{flex:1}.metrics-grid{gap:6px}.metrics-grid article{padding:10px}.member-pane>header{align-items:flex-start;flex-wrap:wrap}.member-header-actions{width:100%}.selection-bar{bottom:4px;margin:5px}.selection-summary{width:100%}.impact-grid{grid-template-columns:repeat(2,1fr)}.filter-grid{grid-template-columns:1fr;padding:8px}.filter-grid .search-field{grid-column:auto}}
.picker-row.disabled{cursor:not-allowed;background:var(--hcis-slate-50);opacity:.68}.picker-row.disabled input{cursor:not-allowed}.picker-row em.joined,.picker-row em.self-leader{padding:4px 7px;border-radius:99px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-600);font-weight:800}.picker-row em.self-leader{background:#fff7ed;color:#c2410c}.preview-list{display:grid;gap:8px}.preview-row.impact-card{display:grid;gap:9px;margin:0;padding:12px;border:1px solid #e5eaf3;border-radius:12px;background:#fff}.impact-card>header{display:flex;align-items:center;gap:8px}.impact-card>header span:nth-child(2){display:grid;min-width:0;flex:1}.impact-card>header small{font-size:9px;color:var(--hcis-slate-400)}.impact-card>header em{padding:4px 7px;border-radius:99px;background:var(--hcis-slate-100);font-size:9px;font-style:normal;font-weight:800}.impact-card.is-add>header em{background:var(--hcis-emerald-50);color:var(--hcis-emerald-700)}.impact-card.is-move>header em{background:#fff7ed;color:#c2410c}.impact-card.is-remove>header em,.impact-card.is-conflict>header em{background:#fff1f2;color:var(--hcis-rose-700)}.impact-card.is-skip>header em{color:var(--hcis-slate-500)}.leader-impact{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:8px}.leader-impact>i{color:var(--hcis-slate-400)}.leader-impact-cell{display:grid;gap:2px;padding:8px 10px;border-radius:9px;background:var(--hcis-slate-50)}.leader-impact-cell.target{background:var(--hcis-indigo-50)}.leader-impact-cell small,.retained-teams small{font-size:8px;color:var(--hcis-slate-500);text-transform:uppercase;font-weight:800}.leader-impact-cell strong,.retained-teams strong{font-size:10px;overflow-wrap:anywhere}.retained-teams,.impact-reason{display:flex;align-items:flex-start;gap:7px;padding:8px 10px;border-radius:9px;font-size:9px}.retained-teams{background:var(--hcis-amber-50);color:var(--hcis-amber-800)}.retained-teams span{display:grid}.impact-reason{background:var(--hcis-slate-50);color:var(--hcis-slate-500)}.preview-row.warning{border-color:var(--hcis-amber-200)}.icon-btn:disabled{cursor:wait;opacity:.45}.processing-banner{display:flex;align-items:center;gap:10px;margin-top:12px;padding:10px 12px;border:1px solid var(--hcis-indigo-200);border-radius:12px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-700)}.processing-banner>i{font-size:18px}.processing-banner span{display:grid}.processing-banner strong{font-size:11px}.processing-banner small{font-size:9px;color:var(--hcis-indigo-500)}
.team-choice-panel{display:flex;align-items:flex-start;gap:8px;padding:10px;border:1px solid var(--hcis-amber-200);border-radius:10px;background:var(--hcis-amber-50);color:var(--hcis-amber-800)}.team-choice-panel>div{display:grid;gap:8px;min-width:0;flex:1}.team-choice-panel>div>header{display:flex;align-items:center;justify-content:space-between;gap:8px}.team-choice-panel>div>header span{display:grid;gap:2px}.team-choice-panel>div>header strong{font-size:10px}.team-choice-panel>div>header small{font-size:9px;color:var(--hcis-yellow-700)}.team-choice-panel>div>header b{white-space:nowrap;padding:4px 7px;border-radius:99px;background:#fff7ed;color:#c2410c;font-size:8px}.team-choice-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}.team-choice-list button{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:42px;padding:7px 8px;border:1px solid var(--hcis-amber-200);border-radius:9px;background:#fff;text-align:left;color:var(--hcis-slate-700)}.team-choice-list button span{display:grid;min-width:0}.team-choice-list button small{font-size:8px;color:var(--hcis-slate-400)}.team-choice-list button strong{font-size:9px;overflow-wrap:anywhere}.team-choice-list button em{display:flex;align-items:center;gap:4px;white-space:nowrap;padding:4px 6px;border-radius:99px;background:var(--hcis-emerald-50);color:var(--hcis-emerald-700);font-size:8px;font-style:normal;font-weight:800}.team-choice-list button.release{border-color:#fecdd3;background:#fff7f7}.team-choice-list button.release em{background:#fff1f2;color:var(--hcis-rose-700)}@media(max-width:600px){.team-choice-panel>div>header{align-items:flex-start;flex-direction:column}.team-choice-list{grid-template-columns:1fr}}
.filter-field :deep(.el-select){width:100%}.filter-field :deep(.el-select__wrapper){min-height:40px;border-radius:10px;box-shadow:0 0 0 1px #dbe3ef inset}.filter-field :deep(.el-select__wrapper:hover){box-shadow:0 0 0 1px var(--hcis-indigo-300) inset}.filter-field :deep(.el-select__placeholder),.filter-field :deep(.el-select__selected-item){font-size:11px}
.leader-summary{display:flex!important;align-items:center;justify-content:flex-end;gap:6px;min-width:0;max-width:180px}.leader-summary>strong{overflow:hidden;color:var(--hcis-amber-700);font-size:9px;font-weight:500;text-overflow:ellipsis;white-space:nowrap}.more-leaders{display:grid;place-items:center;min-width:26px;height:24px;padding:0 6px;border:1px solid var(--hcis-indigo-200);border-radius:99px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-700);font-size:9px;font-weight:800;cursor:help}.more-leaders:hover{border-color:var(--hcis-indigo-400);background:var(--hcis-indigo-100)}
:global(.membership-leader-popper){max-width:280px!important;padding:9px 10px!important}.leader-tooltip{display:grid;gap:6px;min-width:180px}.leader-tooltip>small{padding-bottom:5px;border-bottom:1px solid #ffffff2b;color:var(--hcis-slate-300);font-size:9px}.leader-tooltip>span{display:flex;align-items:center;justify-content:space-between;gap:12px}.leader-tooltip b{font-size:10px}.leader-tooltip em{color:var(--hcis-slate-300);font-size:8px;font-style:normal}@media(max-width:600px){.leader-summary{max-width:120px}}
.leader-section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.leader-section-header label{font-size:11px;font-weight:800;color:var(--hcis-slate-700);margin:0;display:flex;align-items:center;gap:5px}
.leader-section-header label i{color:var(--hcis-indigo-500)}
.text-danger{color:#ef4444}
.leader-status-hint{font-size:10px}
.badge-active-leader{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;background:#e0f2fe;color:#0369a1;font-weight:700;font-size:10px}
.badge-new-leader{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;background:#fef3c7;color:#92400e;font-weight:700;font-size:10px}
.leader-selector-wrapper{position:relative;width:100%}
.leader-select-trigger{width:100%;min-height:46px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 12px;border:1px solid #dbe3ef;border-radius:12px;background:#fff;cursor:pointer;text-align:left;transition:all .18s ease}
.leader-select-trigger:hover:not(.is-disabled){border-color:var(--hcis-indigo-300);background:#fafcff}
.leader-select-trigger.is-open{border-color:var(--hcis-indigo-500);box-shadow:0 0 0 3px rgba(99,102,241,0.15)}
.leader-select-trigger.is-disabled{opacity:.6;cursor:not-allowed}
.leader-select-trigger.highlight-pulse{border-color:var(--hcis-indigo-300);background:#fafbff}
.selected-leader-display{display:flex;align-items:center;gap:10px;min-width:0;flex:1}
.avatar.mini{width:28px;height:28px;font-size:9px;border-radius:8px}
.avatar.mini.is-leader{background:#e0f2fe;color:#0369a1}
.selected-info{display:grid;gap:1px;min-width:0}
.selected-name-row{display:flex;align-items:center;gap:6px}
.selected-name-row strong{font-size:12px;color:#1e293b}
.code-badge{font-size:10px;color:#64748b;font-weight:600}
.selected-meta{font-size:10px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.btn-change-leader{padding:4px 10px;border:1px solid #dbe3ef;border-radius:8px;background:#f8faff;color:var(--hcis-indigo-600);font-size:10px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:all .15s}
.btn-change-leader:hover{border-color:var(--hcis-indigo-300);background:var(--hcis-indigo-50)}
.placeholder-display{display:flex;align-items:center;gap:8px;color:#94a3b8;font-size:12px}
.trigger-chevron{font-size:12px;color:#94a3b8;transition:transform .18s ease}
.leader-select-trigger.is-open .trigger-chevron{transform:rotate(180deg)}
.leader-dropdown-panel{position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:30;background:#fff;border:1px solid #dbe3ef;border-radius:14px;box-shadow:0 14px 34px -4px rgba(15,23,42,0.14),0 4px 12px -2px rgba(15,23,42,0.06);overflow:hidden;animation:dropdownFadeIn .16s ease-out}
@keyframes dropdownFadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.leader-dropdown-header{padding:10px;border-bottom:1px solid #edf2f7;background:#fafbfe;display:grid;gap:8px}
.search-input-box{display:flex;align-items:center;gap:8px;padding:6px 10px;background:#fff;border:1px solid #dbe3ef;border-radius:10px}
.search-input-box i{color:#94a3b8;font-size:12px}
.search-input-box input{width:100%;border:0;outline:0;font-size:12px;background:transparent}
.clear-search-btn{border:0;background:transparent;color:#94a3b8;padding:0;cursor:pointer;display:flex;align-items:center}
.clear-search-btn:hover{color:#64748b}
.leader-filter-pills{display:flex;gap:6px}
.leader-filter-pills button{border:1px solid #e2e8f0;border-radius:99px;background:#fff;padding:3px 9px;font-size:10px;font-weight:700;color:#64748b;cursor:pointer;transition:all .15s}
.leader-filter-pills button.active{background:var(--hcis-indigo-50);border-color:var(--hcis-indigo-300);color:var(--hcis-indigo-700)}
.leader-options-list{max-height:220px;overflow-y:auto;padding:6px;display:grid;gap:2px}
.leader-option-row{width:100%;display:flex;align-items:center;gap:10px;padding:8px 10px;border:0;border-radius:10px;background:transparent;cursor:pointer;text-align:left;transition:background-color .12s ease}
.leader-option-row:hover{background:#f1f5f9}
.leader-option-row.selected{background:#eff6ff}
.option-details{display:grid;gap:1px;min-width:0;flex:1}
.option-name-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.option-name-row strong{font-size:11px;color:#1e293b}
.option-code{font-size:10px;color:#64748b}
.tag-active-leader{font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;background:#e0f2fe;color:#0369a1}
.option-meta{font-size:9px;color:#94a3b8}
.check-icon{color:var(--hcis-indigo-600);font-size:16px;font-weight:800;flex-shrink:0}
</style>

