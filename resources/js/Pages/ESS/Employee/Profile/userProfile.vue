<template>
    <div class="profile-page">
        <section class="profile-hero">
            <div class="hero-orb hero-orb--right"></div>
            <div class="hero-orb hero-orb--center"></div>

            <div class="hero-content">
                <div class="hero-top">
                    <button class="hero-avatar" type="button" title="Lihat foto profil" @click="openProfilePhotoModal">
                        <img
                            v-if="activeProfilePhoto?.avatar_url"
                            v-show="profileImageLoaded.heroAvatar"
                            :src="activeProfilePhoto.avatar_url"
                            :alt="`Foto profil ${displayName}`"
                            @load="markProfileImageLoaded('heroAvatar')"
                            @error="markProfileImageLoaded('heroAvatar')"
                        />
                        <span
                            v-if="activeProfilePhoto?.avatar_url && !profileImageLoaded.heroAvatar"
                            class="image-skeleton image-skeleton--avatar"
                            aria-hidden="true"
                        ></span>
                        <span v-if="!activeProfilePhoto?.avatar_url">{{ employeeInitials }}</span>
                        <i v-if="!activeProfilePhoto?.avatar_url" class="bi bi-camera-fill hero-avatar__badge"></i>
                    </button>

                    <div class="hero-main">
                        <div class="hero-name-row">
                            <h1>{{ displayName }}</h1>
                            <span class="status-badge">
                                <i class="bi bi-circle-fill"></i>
                                Aktif
                            </span>
                        </div>
                        <p class="hero-role">{{ mockPosition.jabatan }}</p>
                        <p class="hero-dept">
                            <i class="bi bi-building"></i>
                            {{ divisi || '-' }}
                        </p>
                    </div>

                    <div class="hero-actions">
                        <button class="hero-btn hero-btn--glass" @click="resetPassword">
                            <i class="bi bi-key"></i>
                            Reset Password
                        </button>
                        <!-- <button class="hero-btn hero-btn--light" @click="editPersonalInfo">
                            <i class="bi bi-pencil-square"></i>
                            Edit Profil
                        </button> -->
                    </div>
                </div>
            </div>

            <div class="hero-stats">
                <div class="hero-stat">
                    <span>Masa Kerja</span>
                    <strong>{{ yearsOfService }}</strong>
                    <small>Bergabung {{ mockJoinDateFormatted }}</small>
                </div>
                <div class="hero-stat">
                    <span>NIK</span>
                    <strong>{{ user.No_Induk_Karyawan || '-' }}</strong>
                    <small>ID Absen: {{ user.UserID_Absen || '-' }}</small>
                </div>
                <div class="hero-stat">
                    <span>Tipe Karyawan</span>
                    <strong>{{ employmentTypeLabel }}</strong>
                    <small>{{ mockPosition.level }}</small>
                </div>
                <div class="hero-stat">
                    <span>Perusahaan</span>
                    <strong>{{ mockPosition.lokasiSingkat }}</strong>
                    <small>{{ mockPosition.areaBranch }}</small>
                </div>
            </div>
        </section>

        <nav class="profile-tabs" aria-label="Navigasi profil">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === tab.key }"
                @click="activeTab = tab.key"
            >
                <span>{{ tab.label }}</span>
                <small v-if="tab.count !== null">{{ tab.count }}</small>
            </button>
        </nav>

        <main class="profile-main">
            <section v-if="activeTab === 'info'" class="info-layout">
                <div class="info-primary">
                    <article class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-accent">
                                    <i class="bi bi-person-lines-fill"></i>
                                </div>
                                <div>
                                    <span>Data Pribadi</span>
                                    <h2>Identitas Karyawan</h2>
                                </div>
                            </div>
                            <button class="soft-btn" @click="editPersonalInfo">
                                <i class="bi bi-info"></i>
                                <!-- Edit -->
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="field-grid">
                                <div class="data-field">
                                    <i class="bi bi-hash"></i>
                                    <div>
                                        <span>No. Induk Karyawan</span>
                                        <strong>{{ user.No_Induk_Karyawan || '-' }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-person"></i>
                                    <div>
                                        <span>Nama</span>
                                        <strong>{{ displayName }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-telephone"></i>
                                    <div>
                                        <span>Nomor Telepon</span>
                                        <strong>{{ formatPhone(user.HP) }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-envelope"></i>
                                    <div>
                                        <span>Email</span>
                                        <strong>{{ user.Email || '-' }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-geo-alt"></i>
                                    <div>
                                        <span>Alamat</span>
                                        <strong>{{ user.Alamat || '-' }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-diagram-3"></i>
                                    <div>
                                        <span>Departemen</span>
                                        <strong>{{ divisi || '-' }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="badge-row">
                                <span class="tone-badge is-info">{{ employmentTypeLabel }}</span>
                                <span class="tone-badge is-success">Aktif</span>
                            </div>
                        </div>
                    </article>

                    <article class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-purple">
                                    <i class="bi bi-briefcase-fill"></i>
                                </div>
                                <div>
                                    <span>Jabatan & Posisi</span>
                                    <h2>Informasi Pekerjaan</h2>
                                </div>
                            </div>
                            <span class="count-badge">{{ mockPosition.level }}</span>
                        </div>

                        <div class="card-body">
                            <div class="role-highlight">
                                <span>Jabatan Saat Ini</span>
                                <strong>{{ mockPosition.jabatan }}</strong>
                                <small>{{ divisi || '-' }}</small>
                            </div>

                            <div class="field-grid">
                                <div class="data-field">
                                    <i class="bi bi-geo-alt"></i>
                                    <div>
                                        <span>Lokasi Kantor</span>
                                        <strong>{{ mockPosition.lokasi }}</strong>
                                    </div>
                                </div>
                                <!-- <div class="data-field">
                                    <i class="bi bi-clock"></i>
                                    <div>
                                        <span>Jam Kerja</span>
                                        <strong>{{ mockPosition.jamKerja }}</strong>
                                    </div>
                                </div> -->
                                <div class="data-field">
                                    <i class="bi bi-calendar-event"></i>
                                    <div>
                                        <span>Mulai Bergabung</span>
                                        <strong>{{ mockJoinDateFormatted }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-award"></i>
                                    <div>
                                        <span>Masa Kerja</span>
                                        <strong>{{ yearsOfService }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-award"></i>
                                    <div>
                                        <span>Status Kepegawaian</span>
                                        <strong>{{ employmentTypeLabel }}</strong>
                                    </div>
                                </div>
                                <!-- <div class="data-field">
                                    <i class="bi bi-award"></i>
                                    <div>
                                        <span>Golongan</span>
                                        <strong>{{ mockPosition.golongan || '-' }}</strong>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <i class="bi bi-award"></i>
                                    <div>
                                        <span>Sub Golongan</span>
                                        <strong>{{ mockPosition.sub_golongan || '-' }}</strong>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </article>

                    <!-- Informasi BPJS (Tampilan Baris Tabel & Logo Resmi) -->
                    <article class="profile-card bpjs-info-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-emerald">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <span>Jaminan & Proteksi Karyawan</span>
                                    <h2>Informasi BPJS</h2>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="bpjs-list">
                                <!-- Row BPJS Kesehatan -->
                                <div class="bpjs-item-row">
                                    <div class="bpjs-brand-cell">
                                        <img
                                            src="/logo/logo-bpjs-kes.webp"
                                            alt="BPJS Kesehatan"
                                            class="bpjs-brand-logo"
                                            loading="lazy"
                                        />
                                    </div>

                                    <div class="bpjs-details-cell">
                                        <div class="bpjs-detail-line">
                                            <span class="bpjs-detail-label">No. Kepesertaan</span>
                                            <span class="bpjs-detail-colon">:</span>
                                            <div class="bpjs-detail-val-wrap">
                                                <strong class="bpjs-detail-val" :class="{ 'is-muted': !hasBpjsKes }">
                                                    {{ bpjsKesNumber || '-' }}
                                                </strong>
                                                <button
                                                    v-if="hasBpjsKes"
                                                    type="button"
                                                    class="bpjs-inline-copy"
                                                    :title="copiedField === 'BPJS Kesehatan' ? 'Tersalin!' : 'Salin nomor'"
                                                    @click="copyToClipboard(bpjsKesNumber, 'BPJS Kesehatan')"
                                                >
                                                    <i :class="copiedField === 'BPJS Kesehatan' ? 'bi bi-check-lg' : 'bi bi-clipboard'"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="bpjs-detail-line">
                                            <span class="bpjs-detail-label">Status</span>
                                            <span class="bpjs-detail-colon">:</span>
                                            <div class="bpjs-detail-val-wrap">
                                                <span v-if="hasBpjsKes" class="bpjs-status-text is-active">
                                                    Aktif
                                                </span>
                                                <span v-else class="bpjs-status-text is-inactive">
                                                    Belum Terdaftar
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bpjs-divider"></div>

                                <!-- Row BPJS Ketenagakerjaan -->
                                <div class="bpjs-item-row">
                                    <div class="bpjs-brand-cell">
                                        <img
                                            src="/logo/logo-bpjs-tk.webp"
                                            alt="BPJS Ketenagakerjaan"
                                            class="bpjs-brand-logo"
                                            loading="lazy"
                                        />
                                    </div>

                                    <div class="bpjs-details-cell">
                                        <div class="bpjs-detail-line">
                                            <span class="bpjs-detail-label">No. Kepesertaan</span>
                                            <span class="bpjs-detail-colon">:</span>
                                            <div class="bpjs-detail-val-wrap">
                                                <strong class="bpjs-detail-val" :class="{ 'is-muted': !hasBpjsTk }">
                                                    {{ bpjsTkNumber || '-' }}
                                                </strong>
                                                <button
                                                    v-if="hasBpjsTk"
                                                    type="button"
                                                    class="bpjs-inline-copy"
                                                    :title="copiedField === 'BPJS TK' ? 'Tersalin!' : 'Salin nomor'"
                                                    @click="copyToClipboard(bpjsTkNumber, 'BPJS TK')"
                                                >
                                                    <i :class="copiedField === 'BPJS TK' ? 'bi bi-check-lg' : 'bi bi-clipboard'"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="bpjs-detail-line">
                                            <span class="bpjs-detail-label">Status</span>
                                            <span class="bpjs-detail-colon">:</span>
                                            <div class="bpjs-detail-val-wrap">
                                                <span v-if="hasBpjsTk" class="bpjs-status-text is-active">
                                                    Aktif
                                                </span>
                                                <span v-else class="bpjs-status-text is-inactive">
                                                    Belum Terdaftar
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-warning">
                                    <i class="bi bi-diagram-2-fill"></i>
                                </div>
                                <div>
                                    <span>Rantai Persetujuan</span>
                                    <h2>Alur Approver Izin</h2>
                                </div>
                            </div>
                            <span v-if="approverList.length" class="count-badge">
                                {{ approverList.length }} approver
                            </span>
                        </div>

                        <div class="card-body">
                            <div v-if="approverList.length" class="approval-flow">
                                <template v-for="(ap, i) in approverList" :key="`${ap.Nama}-${i}`">
                                    <div class="approval-node">
                                        <div class="mini-avatar">{{ getInitials(ap.Nama) }}</div>
                                        <div>
                                            <strong>{{ capitalize(ap.Nama) }}</strong>
                                            <span>Approver {{ ap.order_flow || i + 1 }}</span>
                                        </div>
                                    </div>
                                    <i
                                        v-if="i < approverList.length - 1"
                                        class="bi bi-chevron-double-right approval-arrow"
                                    ></i>
                                </template>
                            </div>
                            <div v-else class="empty-state compact">
                                <i class="bi bi-people"></i>
                                <span>Belum ada approver yang ditentukan.</span>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="info-sidebar">
                    <article v-if="false" class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-sky">
                                    <i class="bi bi-activity"></i>
                                </div>
                                <div>
                                    <span>Log Terkini</span>
                                    <h2>Aktivitas Terbaru</h2>
                                </div>
                            </div>
                            <button
                                class="soft-btn"
                                type="button"
                                :disabled="activityLoading"
                                title="Muat ulang"
                                @click="loadRecentActivity"
                            >
                                <i
                                    class="bi"
                                    :class="activityLoading ? 'bi-arrow-repeat spin' : 'bi-arrow-clockwise'"
                                ></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Loading skeleton -->
                            <div v-if="activityLoading && !activityLog.length" class="activity-skeleton">
                                <div v-for="n in 4" :key="n" class="activity-skeleton__row">
                                    <span class="activity-skeleton__dot"></span>
                                    <div>
                                        <span class="activity-skeleton__line activity-skeleton__line--title"></span>
                                        <span class="activity-skeleton__line activity-skeleton__line--meta"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Error -->
                            <div v-else-if="activityError" class="empty-state compact">
                                <i class="bi bi-exclamation-circle"></i>
                                <strong>{{ activityError }}</strong>
                                <button type="button" class="soft-btn" @click="loadRecentActivity">Coba lagi</button>
                            </div>

                            <!-- Empty -->
                            <div v-else-if="!activityLog.length" class="empty-state">
                                <i class="bi bi-clock-history"></i>
                                <strong>Belum ada aktivitas dalam 7 hari terakhir.</strong>
                                <span>Aktivitas akan muncul saat Anda mulai berinteraksi dengan sistem.</span>
                            </div>

                            <!-- Data -->
                            <ul v-else class="activity-feed">
                                <li
                                    v-for="ev in activityLog"
                                    :key="ev.Event_Id"
                                    class="activity-feed__item"
                                    :class="activityTone(ev.Event_Type)"
                                    :title="formatActivityFullTime(ev.Created_At)"
                                >
                                    <span class="activity-feed__icon">
                                        <i :class="activityIcon(ev.Event_Type)"></i>
                                    </span>
                                    <div class="activity-feed__body">
                                        <div class="activity-feed__title">
                                            <strong>{{ describeActivity(ev) }}</strong>
                                            <span
                                                v-if="activityBadge(ev.Event_Type)"
                                                class="activity-feed__chip"
                                                :class="activityTone(ev.Event_Type)"
                                            >
                                                {{ activityBadge(ev.Event_Type) }}
                                            </span>
                                        </div>
                                        <span class="activity-feed__meta">
                                            <!-- Auth events: tampilkan IP supaya user tahu sesi datang dari mana -->
                                            <span
                                                v-if="
                                                    ['auth.login', 'auth.logout', 'auth.failed'].includes(ev.Event_Type)
                                                "
                                            >
                                                <i class="bi bi-geo-alt"></i>
                                                {{ ev.Ip_Address || 'IP tidak terdeteksi' }}
                                            </span>
                                            <template v-else>
                                                <span v-if="ev.Path" class="activity-feed__path">
                                                    {{ ev.Path }}
                                                </span>
                                                <span v-if="ev.Method && ev.Event_Type === 'api_call'">
                                                    · {{ ev.Method }}{{ ev.Http_Status ? ' ' + ev.Http_Status : '' }}
                                                </span>
                                                <span v-else-if="ev.Http_Status && ev.Http_Status >= 400">
                                                    · HTTP {{ ev.Http_Status }}
                                                </span>
                                            </template>
                                        </span>
                                    </div>
                                    <span class="activity-feed__time">
                                        {{ formatActivityRelative(ev.Created_At) }}
                                    </span>
                                </li>
                            </ul>

                            <div v-if="activityLog.length" class="activity-feed__footer">
                                <button type="button" class="soft-btn" @click="goToFullActivity">
                                    <i class="bi bi-clock-history"></i>
                                    Lihat semua aktivitas
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- <article class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-teal">
                                    <i class="bi bi-file-earmark-lock"></i>
                                </div>
                                <div>
                                    <span>Dokumen & Identifikasi</span>
                                    <h2>Dokumen Pribadi</h2>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="privacy-note">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Data dilindungi. Klik ikon mata untuk melihat nilai.</span>
                            </div>
                            <div class="document-list">
                                <div v-for="(doc, i) in mockDokumen" :key="doc.label" class="document-row">
                                    <div class="document-icon">
                                        <i :class="doc.icon"></i>
                                    </div>
                                    <div class="document-info">
                                        <span>{{ doc.label }}</span>
                                        <strong :class="{ revealed: doc.revealed }">
                                            {{ doc.revealed ? doc.full : doc.masked }}
                                        </strong>
                                    </div>
                                    <button
                                        type="button"
                                        class="icon-btn"
                                        :title="doc.revealed ? 'Sembunyikan' : 'Tampilkan'"
                                        @click="toggleReveal(i)"
                                    >
                                        <i :class="doc.revealed ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article> -->

                    <article class="profile-card">
                        <div class="card-header">
                            <div class="card-title-group">
                                <div class="card-icon is-success">
                                    <i class="bi bi-check2-circle"></i>
                                </div>
                                <div>
                                    <span>Hak Approval Saya</span>
                                    <h2>Menyetujui Izin</h2>
                                </div>
                            </div>
                            <span v-if="approvalRights.length" class="count-badge">
                                {{ approvalRights.length }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div v-if="approvalRights.length" class="approval-rights">
                                <div
                                    v-for="(item, i) in approvalRights"
                                    :key="`${item.Nama}-${i}`"
                                    class="approval-chip"
                                >
                                    <div class="mini-avatar is-green">{{ getInitials(item.Nama) }}</div>
                                    <div>
                                        <strong>{{ truncateText(capitalize(item.Nama), 22) }}</strong>
                                        <span>{{ item.divisi || 'Requester' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state compact">
                                <i class="bi bi-check2-circle"></i>
                                <span>Belum ada hak approval.</span>
                            </div>
                        </div>
                    </article>
                </aside>
            </section>

            <section v-else-if="activeTab === 'team'" class="tab-panel">
                <article class="profile-card">
                    <div class="list-toolbar">
                        <div>
                            <span>Anggota Tim</span>
                            <h2>Tim yang Anda Pimpin</h2>
                        </div>
                        <div class="toolbar-actions">
                            <label class="search-box">
                                <i class="bi bi-search"></i>
                                <input v-model="teamSearch" type="search" placeholder="Cari anggota..." />
                            </label>
                            <select v-model="teamDeptFilter" class="filter-select">
                                <option value="all">Semua Dept ({{ teamMembers.length }})</option>
                                <option v-for="dept in teamDepartments" :key="dept" :value="dept">
                                    {{ dept }} ({{ countTeamByDept(dept) }})
                                </option>
                            </select>
                            <span class="count-badge">{{ filteredTeamMembers.length }} orang</span>
                        </div>
                    </div>
                </article>

                <div v-if="filteredTeamMembers.length" class="team-grid">
                    <article
                        v-for="(member, i) in filteredTeamMembers"
                        :key="`${member.Nama}-${i}`"
                        class="person-card"
                    >
                        <div class="person-avatar">{{ getInitials(member.Nama) }}</div>
                        <div class="person-info">
                            <strong>{{ capitalize(member.Nama) }}</strong>
                            <span>{{ member.divisi || '-' }}</span>
                        </div>
                        <a v-if="member.HP" class="person-phone" :href="`tel:${member.HP}`">
                            <i class="bi bi-telephone"></i>
                            {{ member.HP }}
                        </a>
                        <span v-else class="person-phone muted">
                            <i class="bi bi-telephone"></i>
                            -
                        </span>
                    </article>
                </div>

                <div v-else class="empty-page">
                    <i class="bi bi-search"></i>
                    <strong>Tidak ditemukan</strong>
                    <span>Coba ubah filter atau kata kunci pencarian.</span>
                </div>
            </section>

            <section v-else class="tab-panel">
                <article class="profile-card">
                    <div class="list-toolbar">
                        <div>
                            <span>Tim yang Diikuti</span>
                            <h2>Keanggotaan Tim</h2>
                        </div>
                        <div class="toolbar-actions">
                            <label class="search-box">
                                <i class="bi bi-search"></i>
                                <input v-model="memberSearch" type="search" placeholder="Cari tim..." />
                            </label>
                            <span class="count-badge">{{ filteredMemberships.length }} tim</span>
                        </div>
                    </div>

                    <div v-if="filteredMemberships.length" class="membership-list">
                        <div v-for="(team, i) in filteredMemberships" :key="`${team.Nama}-${i}`" class="membership-row">
                            <div class="mini-avatar is-purple">{{ getInitials(team.Nama) }}</div>
                            <div class="membership-info">
                                <strong>{{ capitalize(team.Nama) }}</strong>
                                <span>{{ team.divisi || '-' }}</span>
                            </div>
                            <span class="tone-badge is-purple">{{ team.status || 'Anggota' }}</span>
                            <a v-if="team.HP" class="membership-phone" :href="`tel:${team.HP}`">
                                <i class="bi bi-telephone"></i>
                                {{ team.HP }}
                            </a>
                        </div>
                    </div>

                    <div v-else class="empty-page is-inside">
                        <i class="bi bi-people"></i>
                        <strong>Tidak ditemukan</strong>
                        <span>Belum ada tim yang diikuti atau hasil pencarian kosong.</span>
                    </div>
                </article>
            </section>
        </main>

        <Modal
            :show="showProfilePhotoModal"
            :title="profilePhotoModalMode === 'view' ? 'Foto Profil' : 'Ganti Foto Profil'"
            size="large"
            body-class="profile-photo-modal-body"
            :body-style="{ padding: '0' }"
            :show-footer="false"
            :skeleton="false"
            :loading="profileUploadLoading"
            loading-text="Menyimpan foto profil..."
            @close="closeProfilePhotoModal"
            @update:show="handleProfilePhotoModalVisibility"
        >
            <template #body>
                <section v-if="profilePhotoModalMode === 'view'" class="profile-photo-view">
                    <div class="profile-photo-banner">
                        <img :src="profileBannerUrl" alt="Banner profil" />
                    </div>

                    <div class="profile-photo-identity">
                        <div
                            class="profile-photo-frame"
                            :class="{ 'is-clickable': hasProfilePhoto }"
                            :role="hasProfilePhoto ? 'button' : null"
                            :tabindex="hasProfilePhoto ? 0 : null"
                            :title="hasProfilePhoto ? 'Lihat ID Card 3D' : null"
                            @click="openIdCardExperience"
                            @keydown.enter.prevent="openIdCardExperience"
                            @keydown.space.prevent="openIdCardExperience"
                        >
                            <img
                                v-if="hasProfilePhoto"
                                v-show="profileImageLoaded.profileAvatar"
                                :src="profilePhotoAvatarUrl"
                                :alt="`Foto profil ${displayName}`"
                                @load="markProfileImageLoaded('profileAvatar')"
                                @error="markProfileImageLoaded('profileAvatar')"
                            />
                            <span
                                v-if="hasProfilePhoto && !profileImageLoaded.profileAvatar"
                                class="image-skeleton image-skeleton--fill"
                                aria-hidden="true"
                            ></span>
                            <span v-if="!hasProfilePhoto">{{ employeeInitials }}</span>
                        </div>

                        <div class="profile-photo-name-tag">
                            <span>Employee Name Tag</span>
                            <h3>{{ displayName }}</h3>
                            <div class="profile-photo-meta">
                                <strong>{{ employeeIdCardNumber }}</strong>
                                <small>{{ divisi || 'HCIS' }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="profile-photo-actions">
                        <button class="profile-photo-btn is-muted" type="button" @click="closeProfilePhotoModal">
                            Tutup
                        </button>
                        <button
                            v-if="false"
                            class="profile-photo-btn is-primary"
                            type="button"
                            @click="switchToProfileUpload"
                        >
                            <i class="bi bi-camera"></i>
                            Edit Foto Profil
                        </button>
                    </div>
                </section>

                <section v-else class="profile-photo-upload">
                    <div class="upload-grid">
                        <label class="upload-dropzone">
                            <input
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                type="file"
                                @change="handleProfileFileChange"
                            />
                            <i class="bi bi-cloud-arrow-up"></i>
                            <strong>Pilih foto JPG atau PNG</strong>
                            <span>PNG transparan akan diberi background otomatis.</span>
                        </label>

                        <div class="upload-preview-panel">
                            <div v-if="profilePreviewUrl" class="upload-preview-suite">
                                <article class="upload-preview-card upload-preview-card--profile">
                                    <div class="upload-preview-card__header">
                                        <i class="bi bi-person-badge"></i>
                                        <span>Foto Profil</span>
                                    </div>
                                    <div class="upload-profile-mock">
                                        <div class="upload-profile-avatar">
                                            <img
                                                v-show="profileImageLoaded.previewAvatar"
                                                :src="profileAvatarPreviewUrl"
                                                alt="Preview avatar profil"
                                                @load="markProfileImageLoaded('previewAvatar')"
                                                @error="markProfileImageLoaded('previewAvatar')"
                                            />
                                            <span
                                                v-if="!profileImageLoaded.previewAvatar"
                                                class="image-skeleton image-skeleton--fill"
                                                aria-hidden="true"
                                            ></span>
                                        </div>
                                        <div class="upload-profile-lines">
                                            <strong>{{ displayName }}</strong>
                                            <span>{{ employeeIdCardNumber }}</span>
                                        </div>
                                    </div>
                                </article>

                                <article class="upload-preview-card upload-preview-card--id">
                                    <div class="upload-preview-card__header">
                                        <i class="bi bi-card-heading"></i>
                                        <span>ID Card</span>
                                    </div>
                                    <div class="upload-id-card-preview-frame">
                                        <div
                                            v-if="!profileImageLoaded.previewFull"
                                            class="upload-id-card-skeleton"
                                            aria-hidden="true"
                                        >
                                            <span class="upload-id-card-skeleton__logo"></span>
                                            <span
                                                class="upload-id-card-skeleton__chevron upload-id-card-skeleton__chevron--soft"
                                            ></span>
                                            <span
                                                class="upload-id-card-skeleton__chevron upload-id-card-skeleton__chevron--gold"
                                            ></span>
                                            <span class="upload-id-card-skeleton__photo"></span>
                                            <span class="upload-id-card-skeleton__name"></span>
                                            <span class="upload-id-card-skeleton__meta"></span>
                                        </div>
                                        <div
                                            class="upload-id-card-mock"
                                            :class="{ 'is-loading': !profileImageLoaded.previewFull }"
                                        >
                                            <div class="upload-id-card-logo">
                                                <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                                            </div>
                                            <div class="upload-id-card-chevron upload-id-card-chevron--soft"></div>
                                            <div class="upload-id-card-chevron upload-id-card-chevron--gold"></div>
                                            <img
                                                class="upload-id-card-photo"
                                                :src="profilePreviewUrl"
                                                alt="Preview foto ID card"
                                                @load="markProfileImageLoaded('previewFull')"
                                                @error="markProfileImageLoaded('previewFull')"
                                            />
                                            <div class="upload-id-card-info">
                                                <strong>{{ displayName }}</strong>
                                                <span>{{ employeeIdCardNumber }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>

                                <article class="upload-preview-card upload-preview-card--sidebar">
                                    <div class="upload-preview-card__header">
                                        <i class="bi bi-layout-sidebar-inset"></i>
                                        <span>Sidebar</span>
                                    </div>
                                    <div class="upload-sidebar-mock">
                                        <div class="upload-sidebar-avatar">
                                            <img
                                                v-show="profileImageLoaded.previewSidebar"
                                                :src="profileSidebarPreviewUrl"
                                                alt="Preview avatar sidebar"
                                                @load="markProfileImageLoaded('previewSidebar')"
                                                @error="markProfileImageLoaded('previewSidebar')"
                                            />
                                            <span
                                                v-if="!profileImageLoaded.previewSidebar"
                                                class="image-skeleton image-skeleton--fill"
                                                aria-hidden="true"
                                            ></span>
                                        </div>
                                        <div class="upload-sidebar-lines">
                                            <strong>{{ displayName }}</strong>
                                            <span>{{ divisi || 'HCIS' }}</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <div v-else class="upload-preview upload-preview--empty">
                                <div class="upload-preview-empty">
                                    <i class="bi bi-person-square"></i>
                                    <span>Preview akan tampil di sini.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="placement-panel">
                        <div>
                            <span>Deteksi sistem</span>
                            <strong>{{ faceOrientationLabel }}</strong>
                        </div>
                        <div class="placement-options" aria-label="Posisi foto">
                            <button
                                v-for="option in placementOptions"
                                :key="option.value"
                                class="placement-option-btn"
                                type="button"
                                :class="{ active: selectedPlacement === option.value }"
                                @click="applyPlacement(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <canvas ref="profileCanvas" class="profile-render-canvas"></canvas>
                    <canvas ref="profileFullCanvas" class="profile-render-canvas"></canvas>
                    <canvas ref="profileSidebarCanvas" class="profile-render-canvas"></canvas>

                    <div class="profile-photo-actions">
                        <button class="profile-photo-btn is-muted" type="button" @click="switchToProfileView">
                            Kembali
                        </button>
                        <button
                            class="profile-photo-btn is-primary"
                            type="button"
                            :disabled="!selectedProfileFile || profileUploadLoading"
                            @click="submitProfilePhoto"
                        >
                            <i v-if="profileUploadLoading" class="bi bi-arrow-repeat spin"></i>
                            <i v-else class="bi bi-check-circle"></i>
                            Simpan Foto
                        </button>
                    </div>
                </section>
            </template>
        </Modal>

        <transition name="id-card-overlay">
            <div
                v-if="showIdCardExperience"
                class="id-card-fullscreen"
                role="dialog"
                aria-modal="true"
                aria-label="ID Card 3D"
                @click.self="closeIdCardExperience"
            >
                <div class="id-card-spotlight"></div>
                <button class="id-card-close-btn" type="button" title="Tutup ID Card" @click="closeIdCardExperience">
                    <i class="bi bi-x-lg"></i>
                </button>

                <div class="id-card-stage">
                    <div class="id-card-floor-shadow"></div>
                    <div class="id-card-perspective">
                        <div
                            class="id-card-shell"
                            :class="{ 'is-flipped': isIdCardFlipped }"
                            :style="idCardTiltStyle"
                            role="button"
                            tabindex="0"
                            title="Balik ID Card"
                            @click="toggleIdCardFlip"
                            @keydown.enter.prevent="toggleIdCardFlip"
                            @keydown.space.prevent="toggleIdCardFlip"
                            @pointermove="handleIdCardPointerMove"
                            @pointerleave="resetIdCardTilt"
                            @pointercancel="resetIdCardTilt"
                        >
                            <div class="id-card-flipper">
                                <div class="id-card-face id-card-face--front">
                                    <div class="id-card-glare"></div>
                                    <div class="id-card-chevron id-card-chevron--back"></div>
                                    <div class="id-card-chevron id-card-chevron--front"></div>

                                    <div class="id-card-front-header">
                                        <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                                    </div>

                                    <div class="id-card-portrait">
                                        <img
                                            v-show="profileImageLoaded.idCardFull"
                                            :src="profilePhotoFullUrl"
                                            :alt="`Foto profil ${displayName}`"
                                            @load="markProfileImageLoaded('idCardFull')"
                                            @error="markProfileImageLoaded('idCardFull')"
                                        />
                                        <span
                                            v-if="!profileImageLoaded.idCardFull"
                                            class="image-skeleton image-skeleton--id-photo"
                                            aria-hidden="true"
                                        ></span>
                                    </div>

                                    <div class="id-card-front-info">
                                        <h3>{{ displayName }}</h3>
                                        <strong>{{ employeeIdCardNumber }}</strong>
                                    </div>

                                    <a
                                        class="id-card-qr"
                                        :href="homepageBarcodeValue"
                                        target="_blank"
                                        rel="noopener"
                                        title="Buka homepage"
                                        @click.stop
                                    >
                                        <svg
                                            v-if="idCardQrCode"
                                            :viewBox="idCardQrCode.viewBox"
                                            role="img"
                                            :aria-label="`QR code menuju ${homepageBarcodeValue}`"
                                            shape-rendering="crispEdges"
                                        >
                                            <rect width="100%" height="100%" fill="#fff"></rect>
                                            <rect
                                                v-for="module in idCardQrCode.modules"
                                                :key="`${module.x}-${module.y}`"
                                                :x="module.x"
                                                :y="module.y"
                                                width="1"
                                                height="1"
                                                fill="#141414"
                                            ></rect>
                                        </svg>
                                    </a>

                                    <div class="id-card-front-strip"></div>
                                </div>

                                <div class="id-card-face id-card-face--back">
                                    <div class="id-card-glare"></div>
                                    <div class="id-card-back-header">
                                        <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                                        <h3>SYARAT DAN KETENTUAN BERLAKU</h3>
                                    </div>

                                    <div class="id-card-terms">
                                        <p v-for="term in idCardTerms" :key="term">{{ term }}</p>
                                    </div>

                                    <div class="id-card-back-logos">
                                        <img
                                            v-for="brand in idCardSubsidiaryLogos"
                                            :key="brand.id"
                                            :src="brand.src"
                                            :alt="brand.name"
                                        />
                                    </div>

                                    <div class="id-card-back-footer">
                                        <strong>HEAD OFFICE</strong>
                                        <span>
                                            <i class="bi bi-geo-alt-fill"></i>
                                            Jl. Sapta Marga No. 021 Bukit Sangkal, Palembang
                                        </span>
                                        <span>
                                            <i class="bi bi-telephone-fill"></i>
                                            0711 - 570299
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <Modal
            :show="showResetPasswordModal"
            title="Reset Password"
            subtitle="Perbarui kredensial akun dengan aman."
            size="medium"
            max-width="520px"
            :show-header="false"
            :loading="loading"
            loading-text="Mengamankan perubahan password..."
            body-class="reset-password-modal-body"
            :body-style="{ padding: '0' }"
            :close-on-overlay="!loading"
            :close-on-escape="!loading"
            @close="closeResetPasswordModal"
            @update:show="handleResetPasswordModalVisibility"
        >
            <template #body>
                <div class="reset-password-custom-header">
                    <div>
                        <h3>Reset Password</h3>
                        <p>Perbarui kredensial akun dengan aman.</p>
                    </div>
                    <button type="button" title="Tutup" :disabled="loading" @click="closeResetPasswordModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form id="reset-password-form" class="reset-password-panel" @submit.prevent="submitResetPassword">
                    <div class="reset-password-hero">
                        <div class="reset-password-orbit" aria-hidden="true">
                            <span></span>
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <strong>Keamanan Akun</strong>
                            <span>Gunakan kombinasi huruf besar, huruf kecil, dan angka agar password lebih kuat.</span>
                        </div>
                    </div>

                    <div class="reset-password-fields">
                        <label class="password-field">
                            <span>Password Saat Ini</span>
                            <div class="pw-wrap">
                                <i class="bi bi-lock-fill pw-leading-icon"></i>
                                <input
                                    v-model="currentPassword"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    placeholder="Masukkan password saat ini"
                                    autocomplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pw-eye"
                                    :title="showCurrentPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showCurrentPassword = !showCurrentPassword"
                                >
                                    <i :class="showCurrentPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                        </label>

                        <label class="password-field">
                            <span>Password Baru</span>
                            <div class="pw-wrap">
                                <i class="bi bi-key-fill pw-leading-icon"></i>
                                <input
                                    v-model="newPassword"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    placeholder="Buat password baru"
                                    autocomplete="new-password"
                                    required
                                    @input="checkPasswordStrength"
                                />
                                <button
                                    type="button"
                                    class="pw-eye"
                                    :title="showNewPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showNewPassword = !showNewPassword"
                                >
                                    <i :class="showNewPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>

                            <div class="pw-strength-shell">
                                <div class="pw-strength">
                                    <div class="pw-bar" :class="strengthClass"></div>
                                </div>
                                <strong :class="strengthClass">{{ passwordStrengthLabel }}</strong>
                            </div>
                        </label>

                        <div class="pw-reqs">
                            <span :class="{ met: hasMinLength }">
                                <i :class="hasMinLength ? 'bi bi-check2' : 'bi bi-dot'"></i>
                                Minimal 8 karakter
                            </span>
                            <span :class="{ met: hasUpperCase }">
                                <i :class="hasUpperCase ? 'bi bi-check2' : 'bi bi-dot'"></i>
                                Huruf besar
                            </span>
                            <span :class="{ met: hasLowerCase }">
                                <i :class="hasLowerCase ? 'bi bi-check2' : 'bi bi-dot'"></i>
                                Huruf kecil
                            </span>
                            <span :class="{ met: hasNumber }">
                                <i :class="hasNumber ? 'bi bi-check2' : 'bi bi-dot'"></i>
                                Angka
                            </span>
                        </div>

                        <label class="password-field">
                            <span>Konfirmasi Password Baru</span>
                            <div class="pw-wrap" :class="{ 'has-error': confirmPassword && !passwordsMatch }">
                                <i class="bi bi-patch-check-fill pw-leading-icon"></i>
                                <input
                                    v-model="confirmPassword"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    placeholder="Ulangi password baru"
                                    autocomplete="new-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pw-eye"
                                    :title="showConfirmPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                >
                                    <i :class="showConfirmPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                            <small v-if="confirmPassword && !passwordsMatch" class="pw-mismatch">
                                Password baru belum sama.
                            </small>
                        </label>
                    </div>
                </form>
            </template>

            <template #footer>
                <div class="reset-password-footer">
                    <button
                        class="reset-password-btn reset-password-btn--ghost"
                        type="button"
                        :disabled="loading"
                        @click="closeResetPasswordModal"
                    >
                        Batal
                    </button>
                    <button
                        class="reset-password-btn reset-password-btn--primary"
                        type="submit"
                        form="reset-password-form"
                        :disabled="!isFormValid || loading"
                    >
                        <i v-if="loading" class="bi bi-arrow-repeat spin"></i>
                        <i v-else class="bi bi-shield-check"></i>
                        Simpan Password
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import axios from 'axios';
import { markRaw } from 'vue';
import * as mpFaceDetection from '@mediapipe/face_detection';
import { ElMessage, ElNotification } from 'element-plus';
import Modal from '@/components/legacy/modalV2-noir.vue';
import 'element-plus/dist/index.css';
import { generateQrCodeModules } from '@/utils/qr';

const MEDIAPIPE_FACE_DETECTION_VERSION = '0.4.1646425229';


export default {
    name: 'UserProfile',
    components: {
        Modal,
    },
    props: {
        user: {
            type: Object,
            default: () => ({}),
        },
        divisi: {
            type: String,
            default: '',
        },
        nama_Approver: {
            type: Array,
            default: () => [],
        },
        nama_anggota: {
            type: Array,
            default: () => [],
        },
        nama_mengapprove: {
            type: Array,
            default: () => [],
        },
        nama_tergabung: {
            type: Array,
            default: () => [],
        },
        profilePhoto: {
            type: Object,
            default: null,
        },
        profileBannerUrl: {
            type: String,
            default: '',
        },
        position: {
            type: Array,
            default: '',
        },
    },
    data() {
        return {
            activeProfilePhoto: this.profilePhoto,
            activeTab: 'info',
            teamSearch: '',
            teamDeptFilter: 'all',
            memberSearch: '',
            loading: false,
            // ===== Activity log (sidebar card "Aktivitas Terbaru") =====
            activityLog: [],
            activityLoading: false,
            activityError: null,
            showProfilePhotoModal: false,
            profilePhotoModalMode: 'view',
            showIdCardExperience: false,
            isIdCardFlipped: false,
            idCardTiltStyle: {
                '--id-card-rotate-x': '0deg',
                '--id-card-rotate-y': '0deg',
                '--id-card-glare-x': '50%',
                '--id-card-glare-y': '18%',
            },
            profileBannerUrl:
                this.profileBannerUrl ||
                'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1400&q=80',
            selectedProfileFile: null,
            selectedProfileImage: null,
            selectedProfileMetrics: null,
            profilePreviewUrl: '',
            profileAvatarPreviewUrl: '',
            profileSidebarPreviewUrl: '',
            profileImageLoaded: {
                heroAvatar: false,
                profileAvatar: false,
                idCardFull: false,
                previewAvatar: false,
                previewFull: false,
                previewSidebar: false,
            },
            profileAvatarBlob: null,
            profileFullBlob: null,
            profileSidebarBlob: null,
            profileUploadLoading: false,
            detectedFaceOrientation: 'unknown',
            detectedSubjectCutEdge: null,
            selectedPlacement: 'right',
            faceDetection: null,
            placementOptions: [
                { value: 'left', label: 'Kiri' },
                { value: 'center', label: 'Tengah' },
                { value: 'right', label: 'Kanan' },
            ],
            showResetPasswordModal: false,
            currentPassword: '',
            newPassword: '',
            confirmPassword: '',
            showCurrentPassword: false,
            showNewPassword: false,
            showConfirmPassword: false,
            passwordStrength: 0,
            mockJoinDate: this.user?.Tgl_Masuk ?? '-',
            mockPosition: this.position,
            mockDokumen: [
                {
                    label: 'No. KTP',
                    icon: 'bi bi-card-text',
                    masked: '**** **** **** 4521',
                    full: '3201234567894521',
                    revealed: false,
                },
                {
                    label: 'NPWP',
                    icon: 'bi bi-receipt',
                    masked: '**.***.***.*-***.***',
                    full: '12.345.678.9-012.000',
                    revealed: false,
                },
                {
                    label: 'BPJS Kesehatan',
                    icon: 'bi bi-shield-plus',
                    masked: '0001-****-****-5678',
                    full: '0001-2345-6789-5678',
                    revealed: false,
                },
                {
                    label: 'BPJS Ketenagakerjaan',
                    icon: 'bi bi-shield-check',
                    masked: '13-****-****-7',
                    full: '13-1234-5678-7',
                    revealed: false,
                },
                {
                    label: 'No. Rekening',
                    icon: 'bi bi-bank',
                    masked: 'BCA ****-****-9012',
                    full: 'BCA 1234-5678-9012',
                    revealed: false,
                },
            ],
            idCardBrandLogos: [
                {
                    id: 'evo',
                    name: 'EVO Group',
                    short: 'EVO',
                    src: '/logo/EVOGROUP.png',
                },
                {
                    id: 'emi',
                    name: 'PT Evo Manufacturing Indonesia',
                    short: 'EMI',
                    src: '/logo/EMI.png',
                },
                {
                    id: 'enb',
                    name: 'PT Evo Nusa Bersaudara',
                    short: 'ENB',
                    src: '/logo/ENB.png',
                },
                {
                    id: 'gmn',
                    name: 'PT Graha Maju Nusantara',
                    short: 'GMN',
                    src: '/logo/GMN.png',
                },
            ],
            revealBpjsTk: false,
            revealBpjsKes: false,
            copiedField: null,
            idCardTerms: [
                'Kartu ini berfungsi sebagai tanda pengenal pegawai selama bekerja di Evo Group.',
                'Kartu tanda pengenal ini wajib dipakai selama jam kerja menjalankan tugas.',
                'Evo Group tidak bertanggung jawab atas penyalahgunaan kartu tanda pengenal pegawai ini.',
                'Kehilangan atau kerusakan kartu baru akan dikenakan biaya sesuai ketentuan yang berlaku.',
                'Apabila menemukan kartu ini harap dikembalikan kepada pihak perusahaan.',
            ],
        };
    },
    computed: {
        bpjsTkNumber() {
            const raw =
                this.user?.no_bpjs_tk ||
                this.user?.No_bpjs_tk ||
                this.user?.No_Bpjs_Tk ||
                this.user?.No_BPJS_TK ||
                this.user?.NO_BPJS_TK ||
                '';
            return raw ? String(raw).trim() : '';
        },
        bpjsKesNumber() {
            const raw =
                this.user?.no_bpjs_kes ||
                this.user?.No_bpjs_kes ||
                this.user?.No_Bpjs_Kes ||
                this.user?.No_BPJS_KES ||
                this.user?.NO_BPJS_KES ||
                '';
            return raw ? String(raw).trim() : '';
        },
        hasBpjsTk() {
            return Boolean(this.bpjsTkNumber);
        },
        hasBpjsKes() {
            return Boolean(this.bpjsKesNumber);
        },
        formattedBpjsTk() {
            if (!this.hasBpjsTk) return 'Belum terdaftar';
            if (!this.revealBpjsTk) {
                return this.maskCardNumber(this.bpjsTkNumber);
            }
            return this.formatCardNumberGrouping(this.bpjsTkNumber);
        },
        formattedBpjsKes() {
            if (!this.hasBpjsKes) return 'Belum terdaftar';
            if (!this.revealBpjsKes) {
                return this.maskCardNumber(this.bpjsKesNumber);
            }
            return this.formatCardNumberGrouping(this.bpjsKesNumber);
        },
        displayName() {
            return this.user?.Nama || '-';
        },
        // Label sudah jadi dari server (StatusKepegawaianHelper). JANGAN memetakan
        // ulang di sini — versi sebelumnya punya rantai if-else sendiri dan itulah
        // yang membuat layar menyimpang dari master aturan cuti.
        employmentTypeLabel() {
            const label = String(this.mockPosition?.status_kontrak ?? '').trim();

            return label === '' ? '-' : label;
        },
        employeeIdCardNumber() {
            return this.user?.No_Induk_Karyawan || '-';
        },
        hasProfilePhoto() {
            return Boolean(this.profilePhotoAvatarUrl || this.profilePhotoFullUrl);
        },
        profilePhotoAvatarUrl() {
            return this.activeProfilePhoto?.avatar_url || this.activeProfilePhoto?.full_url || '';
        },
        profilePhotoFullUrl() {
            return this.activeProfilePhoto?.full_url || this.activeProfilePhoto?.avatar_url || '';
        },
        profilePhotoSidebarUrl() {
            return this.activeProfilePhoto?.sidebar_url || this.activeProfilePhoto?.avatar_url || '';
        },
        idCardSubsidiaryLogos() {
            return this.idCardBrandLogos.filter((brand) => brand.id !== 'evo');
        },
        homepageBarcodeValue() {
            if (typeof window === 'undefined') return '/';
            return new URL('/', window.location.origin).href;
        },
        idCardQrCode() {
            return generateQrCodeModules(this.homepageBarcodeValue);
        },
        teamMembers() {
            return Array.isArray(this.nama_anggota) ? this.nama_anggota : [];
        },
        memberships() {
            return Array.isArray(this.nama_tergabung) ? this.nama_tergabung : [];
        },
        approverList() {
            return Array.isArray(this.nama_Approver) ? this.nama_Approver : [];
        },
        approvalRights() {
            return Array.isArray(this.nama_mengapprove) ? this.nama_mengapprove : [];
        },
        tabs() {
            return [
                { key: 'info', label: 'Informasi', count: null },
                { key: 'team', label: 'Tim Saya', count: this.teamMembers.length },
                { key: 'membership', label: 'Keanggotaan', count: this.memberships.length },
            ];
        },
        teamDepartments() {
            return [...new Set(this.teamMembers.map((m) => m.divisi || 'Tanpa Divisi'))].sort();
        },
        filteredTeamMembers() {
            const search = this.normalizeText(this.teamSearch);
            return this.teamMembers.filter((member) => {
                const dept = member.divisi || 'Tanpa Divisi';
                const matchesDept = this.teamDeptFilter === 'all' || dept === this.teamDeptFilter;
                const matchesSearch =
                    !search ||
                    this.normalizeText(member.Nama).includes(search) ||
                    this.normalizeText(member.divisi).includes(search);

                return matchesDept && matchesSearch;
            });
        },
        filteredMemberships() {
            const search = this.normalizeText(this.memberSearch);
            if (!search) return this.memberships;

            return this.memberships.filter((team) => {
                return (
                    this.normalizeText(team.Nama).includes(search) || this.normalizeText(team.divisi).includes(search)
                );
            });
        },
        yearsOfService() {
            const join = new Date(this.mockJoinDate);
            const now = new Date();
            let years = now.getFullYear() - join.getFullYear();
            let months = now.getMonth() - join.getMonth();

            if (months < 0) {
                years--;
                months += 12;
            }

            return `${years} Thn ${months} Bln`;
        },
        mockJoinDateFormatted() {
            return new Date(this.mockJoinDate).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        employeeInitials() {
            return this.getInitials(this.user?.Nama || '');
        },
        faceOrientationLabel() {
            if (this.detectedSubjectCutEdge === 'left') {
                return 'Foto terpotong kiri - dipepet kiri';
            }

            if (this.detectedSubjectCutEdge === 'right') {
                return 'Foto terpotong kanan - dipepet kanan';
            }

            const labels = {
                left: 'Menghadap kiri - foto ditempatkan kanan',
                right: 'Menghadap kanan - foto ditempatkan kiri',
                center: 'Menghadap tengah',
                unknown: 'Belum terdeteksi',
            };

            return labels[this.detectedFaceOrientation] || labels.unknown;
        },
        hasMinLength() {
            return this.newPassword.length >= 8;
        },
        hasUpperCase() {
            return /[A-Z]/.test(this.newPassword);
        },
        hasLowerCase() {
            return /[a-z]/.test(this.newPassword);
        },
        hasNumber() {
            return /[0-9]/.test(this.newPassword);
        },
        passwordsMatch() {
            return this.newPassword === this.confirmPassword;
        },
        isFormValid() {
            return (
                this.currentPassword &&
                this.hasMinLength &&
                this.hasUpperCase &&
                this.hasLowerCase &&
                this.hasNumber &&
                this.passwordsMatch
            );
        },
        strengthClass() {
            if (this.passwordStrength < 2) return 'strength-weak';
            if (this.passwordStrength < 4) return 'strength-medium';
            return 'strength-strong';
        },
        passwordStrengthLabel() {
            if (!this.newPassword) return 'Belum diisi';
            if (this.passwordStrength < 2) return 'Lemah';
            if (this.passwordStrength < 4) return 'Cukup';
            return 'Kuat';
        },
    },
    watch: {
        profilePhoto: {
            deep: true,
            handler(value) {
                this.activeProfilePhoto = value;
                this.resetProfileImageLoaded(['heroAvatar', 'profileAvatar', 'idCardFull']);
                if (!this.profilePhotoFullUrl) {
                    this.resetIdCardExperience();
                }
            },
        },
    },
    mounted() {
        window.addEventListener('keydown', this.handleIdCardGlobalKeydown);
        this.loadRecentActivity();
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.handleIdCardGlobalKeydown);
        document.body.classList.remove('profile-id-card-open');
        this.closeFaceDetection();
        this.revokeProfilePreview();
    },
    methods: {
        /* ============================================================
         * Recent Activity (sidebar card)
         * Mengambil 8 event terakhir user (7 hari) dari Activity Tracker.
         * Endpoint: GET /my/activity/timeline
         * ============================================================ */
        async loadRecentActivity() {
            this.activityLoading = true;
            this.activityError = null;
            try {
                const today = new Date();
                const from = new Date();
                from.setDate(today.getDate() - 7);
                const toIso = (d) => d.toISOString().slice(0, 10);
                const { data } = await axios.get('/my/activity/timeline', {
                    params: {
                        from: toIso(from),
                        to: toIso(today),
                        page: 1,
                        per_page: 8,
                    },
                });
                this.activityLog = Array.isArray(data?.result) ? data.result : [];
            } catch (e) {
                this.activityError = 'Gagal memuat aktivitas.';
                this.activityLog = [];
            } finally {
                this.activityLoading = false;
            }
        },
        describeActivity(ev) {
            if (!ev) return '';
            if (ev.Description) return ev.Description;

            const label = ev.Module_Name || ev.Module_Code || this.humanizePath(ev.Path);

            switch (ev.Event_Type) {
                case 'auth.login':
                    return 'Login berhasil';
                case 'auth.logout':
                    return 'Logout';
                case 'auth.failed':
                    return 'Login gagal';
                case 'permission_denied':
                    return `Akses ditolak${label ? ' — ' + label : ''}`;
                case 'pageview':
                    return label ? `Membuka ${label}` : 'Membuka halaman';
                case 'api_call':
                    return label ? `${ev.Method || 'API'}: ${label}` : `${ev.Method || 'API'} ${ev.Path || ''}`.trim();
                case 'download':
                    return label ? `Download — ${label}` : 'Download file';
                case 'export':
                    return label ? `Export — ${label}` : 'Export data';
                case 'error':
                    return label ? `Error di ${label}` : 'Terjadi error';
                case 'action':
                case 'api_call': {
                    const verb =
                        {
                            create: 'Menambah data',
                            update: 'Mengubah data',
                            delete: 'Menghapus data',
                            approve: 'Menyetujui',
                            reject: 'Menolak',
                        }[ev.Action_Code] || 'Aksi';
                    return label ? `${verb} — ${label}` : verb;
                }
                default:
                    return ev.Action_Code || ev.Event_Type || 'Aktivitas';
            }
        },
        /**
         * Ubah path mentah jadi label yang enak dibaca ketika modul belum
         * terpetakan di N_HRIS_Activity_Route_Map.
         * Contoh: "/uDash" -> "Dashboard", "/cuti/pengajuan" -> "Cuti › Pengajuan"
         */
        humanizePath(path) {
            if (!path) return '';
            let p = String(path)
                .split('?')[0]
                .replace(/^\/+|\/+$/g, '');
            if (!p) return 'Beranda';

            // Kamus segmen yang sering muncul → nama ramah
            const dict = {
                udash: 'Dashboard',
                dashboard: 'Dashboard',
                home: 'Beranda',
                profile: 'Profil',
                userprofile: 'Profil',
                cuti: 'Cuti',
                lembur: 'Lembur',
                overtime: 'Lembur',
                kpi: 'KPI',
                lms: 'Learning',
                training: 'Training',
                shift: 'Shift',
                approval: 'Approval',
                'transaksi-tamu': 'Tamu',
                'transaksi-permit': 'Surat Izin',
                'approval-visitor': 'Approval Tamu',
                'laporan-visitor': 'Laporan Tamu',
                admin: 'Admin',
                'role-manager': 'Role Manager',
                'system-auth': 'System Auth',
                activity: 'Aktivitas',
                api: 'API',
                v1: '',
            };

            const segments = p
                .split('/')
                .filter((s) => s && !/^\d+$/.test(s)) // buang segmen ID numerik
                .map((s) => {
                    const key = s.toLowerCase();
                    if (key in dict) return dict[key];
                    // kapitalisasi & ganti dash/underscore jadi spasi
                    return s.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                })
                .filter(Boolean);

            return segments.length ? segments.join(' › ') : 'Halaman';
        },
        activityIcon(type) {
            return (
                {
                    'auth.login': 'bi bi-box-arrow-in-right',
                    'auth.logout': 'bi bi-box-arrow-right',
                    'auth.failed': 'bi bi-exclamation-triangle',
                    permission_denied: 'bi bi-shield-x',
                    pageview: 'bi bi-file-earmark-text',
                    api_call: 'bi bi-lightning-charge',
                    download: 'bi bi-download',
                    export: 'bi bi-upload',
                    action: 'bi bi-check2-circle',
                    error: 'bi bi-x-octagon',
                }[type] || 'bi bi-dot'
            );
        },
        activityTone(type) {
            if (['auth.failed', 'permission_denied', 'error'].includes(type)) return 'is-warn';
            if (type === 'auth.login') return 'is-ok';
            if (type === 'auth.logout') return 'is-muted';
            return '';
        },
        activityBadge(type) {
            return (
                {
                    'auth.login': 'Login',
                    'auth.logout': 'Logout',
                    'auth.failed': 'Gagal',
                    permission_denied: 'Ditolak',
                    download: 'Download',
                    export: 'Export',
                    error: 'Error',
                }[type] || ''
            );
        },
        formatActivityRelative(value) {
            if (!value) return '';
            const d = new Date(value);
            const diffMs = Date.now() - d.getTime();
            const sec = Math.round(diffMs / 1000);
            if (sec < 60) return 'baru saja';
            const min = Math.round(sec / 60);
            if (min < 60) return `${min} mnt lalu`;
            const hr = Math.round(min / 60);
            if (hr < 24) return `${hr} jam lalu`;
            const day = Math.round(hr / 24);
            if (day < 7) return `${day} hari lalu`;
            return d.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });
        },
        formatActivityFullTime(value) {
            if (!value) return '';
            return new Date(value).toLocaleString('id-ID', { hour12: false });
        },
        goToFullActivity() {
            window.location.href = '/my/activity';
        },
        normalizeText(value) {
            return String(value || '')
                .toLowerCase()
                .trim();
        },
        countTeamByDept(dept) {
            return this.teamMembers.filter((member) => (member.divisi || 'Tanpa Divisi') === dept).length;
        },
        formatPhone(value) {
            if (!value) return '-';
            return String(value).startsWith('+') ? value : `+${value}`;
        },
        markProfileImageLoaded(key) {
            this.profileImageLoaded = {
                ...this.profileImageLoaded,
                [key]: true,
            };
        },
        resetProfileImageLoaded(keys) {
            const next = { ...this.profileImageLoaded };
            keys.forEach((key) => {
                next[key] = false;
            });
            this.profileImageLoaded = next;
        },
        toggleReveal(idx) {
            this.mockDokumen[idx] = {
                ...this.mockDokumen[idx],
                revealed: !this.mockDokumen[idx].revealed,
            };
        },
        getInitials(name) {
            const initials = String(name || '')
                .split(' ')
                .filter(Boolean)
                .map((word) => word[0])
                .join('')
                .slice(0, 2)
                .toUpperCase();

            return initials || 'U';
        },
        openProfilePhotoModal() {
            this.profilePhotoModalMode = 'view';
            this.resetProfileImageLoaded(['profileAvatar']);
            this.resetIdCardExperience();
            this.showProfilePhotoModal = true;
        },
        closeProfilePhotoModal() {
            this.showProfilePhotoModal = false;
            this.profilePhotoModalMode = 'view';
            this.resetIdCardExperience();
            this.clearProfileUploadState();
        },
        handleProfilePhotoModalVisibility(value) {
            if (value) {
                this.showProfilePhotoModal = true;
                return;
            }

            if (this.showIdCardExperience) {
                this.showProfilePhotoModal = false;
                return;
            }

            this.closeProfilePhotoModal();
        },
        switchToProfileUpload() {
            this.resetIdCardExperience();
            this.profilePhotoModalMode = 'upload';
        },
        switchToProfileView() {
            this.profilePhotoModalMode = 'view';
            this.resetIdCardExperience();
            this.clearProfileUploadState();
        },
        openIdCardExperience() {
            if (!this.hasProfilePhoto) return;

            this.resetProfileImageLoaded(['idCardFull']);
            this.isIdCardFlipped = false;
            this.resetIdCardTilt();
            this.showIdCardExperience = true;
            this.showProfilePhotoModal = false;
            this.profilePhotoModalMode = 'view';
            this.clearProfileUploadState();
            document.body.classList.add('profile-id-card-open');
        },
        closeIdCardExperience() {
            this.resetIdCardExperience();
        },
        resetIdCardExperience() {
            this.showIdCardExperience = false;
            this.isIdCardFlipped = false;
            this.resetIdCardTilt();
            document.body.classList.remove('profile-id-card-open');
        },
        toggleIdCardFlip() {
            this.isIdCardFlipped = !this.isIdCardFlipped;
            this.syncIdCardFlipStyle();
        },
        handleIdCardGlobalKeydown(event) {
            if (event.key === 'Escape' && this.showIdCardExperience) {
                this.closeIdCardExperience();
            }
        },
        handleIdCardPointerMove(event) {
            if (event.pointerType === 'touch') return;

            const rect = event.currentTarget.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;
            const rotateY = (x - 0.5) * 18;
            const rotateX = (0.5 - y) * 16;

            this.idCardTiltStyle = {
                '--id-card-rotate-x': `${rotateX.toFixed(2)}deg`,
                '--id-card-rotate-y': `${rotateY.toFixed(2)}deg`,
                '--id-card-glare-x': `${(x * 100).toFixed(1)}%`,
                '--id-card-glare-y': `${(y * 100).toFixed(1)}%`,
            };
        },
        resetIdCardTilt() {
            this.idCardTiltStyle = {
                '--id-card-rotate-x': '0deg',
                '--id-card-rotate-y': '0deg',
                '--id-card-glare-x': '50%',
                '--id-card-glare-y': '18%',
            };
        },
        syncIdCardFlipStyle() {
            this.idCardTiltStyle = {
                ...this.idCardTiltStyle,
            };
        },
        clearProfileUploadState() {
            this.selectedProfileFile = null;
            this.selectedProfileImage = null;
            this.selectedProfileMetrics = null;
            this.profileAvatarBlob = null;
            this.profileFullBlob = null;
            this.profileSidebarBlob = null;
            this.detectedFaceOrientation = 'unknown';
            this.detectedSubjectCutEdge = null;
            this.selectedPlacement = 'right';
            this.revokeProfilePreview();
        },
        revokeProfilePreview() {
            if (this.profilePreviewUrl) {
                URL.revokeObjectURL(this.profilePreviewUrl);
                this.profilePreviewUrl = '';
            }
            if (this.profileAvatarPreviewUrl) {
                URL.revokeObjectURL(this.profileAvatarPreviewUrl);
                this.profileAvatarPreviewUrl = '';
            }
            if (this.profileSidebarPreviewUrl) {
                URL.revokeObjectURL(this.profileSidebarPreviewUrl);
                this.profileSidebarPreviewUrl = '';
            }
        },
        async handleProfileFileChange(event) {
            const [file] = Array.from(event.target.files || []);
            event.target.value = '';

            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                ElMessage.error('Format foto harus JPG atau PNG.');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                ElMessage.error('Ukuran foto maksimal 5MB.');
                return;
            }

            try {
                this.selectedProfileFile = file;
                this.detectedFaceOrientation = 'unknown';
                this.detectedSubjectCutEdge = null;
                this.selectedPlacement = file.type === 'image/png' ? 'right' : 'center';
                this.selectedProfileImage = await this.loadImageFromFile(file);
                this.selectedProfileMetrics = this.analyzeProfileImage(this.selectedProfileImage);
                this.detectedSubjectCutEdge = this.detectProfileSubjectCutEdge(this.selectedProfileMetrics);
                if (this.detectedSubjectCutEdge) {
                    this.selectedPlacement = this.detectedSubjectCutEdge;
                }
                await this.renderProfileCanvases();
                await this.detectProfileFaceOrientation();
            } catch (error) {
                console.error(error);
                ElMessage.error('Gagal membaca foto. Silakan pilih foto lain.');
                this.clearProfileUploadState();
            }
        },
        loadImageFromFile(file) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                const objectUrl = URL.createObjectURL(file);

                image.onload = () => {
                    URL.revokeObjectURL(objectUrl);
                    resolve(image);
                };
                image.onerror = () => {
                    URL.revokeObjectURL(objectUrl);
                    reject(new Error('Gagal memuat gambar.'));
                };
                image.src = objectUrl;
            });
        },
        analyzeProfileImage(image) {
            const maxSide = 420;
            const scale = Math.min(1, maxSide / Math.max(image.width, image.height));
            const width = Math.max(1, Math.round(image.width * scale));
            const height = Math.max(1, Math.round(image.height * scale));
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.clearRect(0, 0, width, height);
            ctx.drawImage(image, 0, 0, width, height);

            const data = ctx.getImageData(0, 0, width, height).data;
            let minX = width;
            let minY = height;
            let maxX = -1;
            let maxY = -1;
            let alphaPixels = 0;

            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const alpha = data[(y * width + x) * 4 + 3];
                    if (alpha > 24) {
                        alphaPixels++;
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                    }
                }
            }

            if (maxX < 0 || alphaPixels === width * height) {
                return {
                    hasUsableAlpha: false,
                    bounds: { minX: 0, minY: 0, maxX: width, maxY: height, centerX: width / 2, width },
                    scale,
                };
            }

            const boundsWidth = maxX - minX + 1;
            const boundsHeight = maxY - minY + 1;
            const canvasCoverage = alphaPixels / (width * height);
            const hasUsableAlpha = canvasCoverage < 0.92 && boundsWidth < width * 0.98;

            return {
                hasUsableAlpha,
                bounds: {
                    minX,
                    minY,
                    maxX,
                    maxY,
                    width: boundsWidth,
                    height: boundsHeight,
                    centerX: minX + boundsWidth / 2,
                },
                scale,
            };
        },
        detectProfileSubjectCutEdge(metrics) {
            if (!metrics?.hasUsableAlpha) {
                return null;
            }

            const bounds = metrics.bounds;
            const imageWidth = Math.max(1, Math.round(this.selectedProfileImage.width * metrics.scale));
            const threshold = Math.max(4, imageWidth * 0.018);
            const touchesLeft = bounds.minX <= threshold;
            const touchesRight = bounds.maxX >= imageWidth - threshold;

            if (touchesLeft && touchesRight) {
                return bounds.centerX <= imageWidth / 2 ? 'left' : 'right';
            }

            if (touchesLeft) {
                return 'left';
            }

            if (touchesRight) {
                return 'right';
            }

            return null;
        },
        resolveFaceDetectionConstructor() {
            const candidates = [
                mpFaceDetection?.FaceDetection,
                mpFaceDetection?.default?.FaceDetection,
                mpFaceDetection?.default,
                window?.FaceDetection,
            ];

            const ctor = candidates.find((candidate) => typeof candidate === 'function');
            if (!ctor) {
                throw new Error('FaceDetection constructor tidak ditemukan.');
            }

            return ctor;
        },
        async getFaceDetection() {
            if (this.faceDetection) {
                return this.faceDetection;
            }

            const FaceDetectionCtor = this.resolveFaceDetectionConstructor();
            this.faceDetection = markRaw(
                new FaceDetectionCtor({
                    locateFile: (file) =>
                        `https://cdn.jsdelivr.net/npm/@mediapipe/face_detection@${MEDIAPIPE_FACE_DETECTION_VERSION}/${file}`,
                }),
            );
            this.faceDetection.setOptions({
                model: 'full',
                selfieMode: false,
                minDetectionConfidence: 0.35,
            });

            if (typeof this.faceDetection.initialize === 'function') {
                await this.faceDetection.initialize();
            }

            return this.faceDetection;
        },
        async detectProfileFaceOrientation() {
            if (!this.selectedProfileImage) return;

            try {
                const detector = await this.getFaceDetection();
                const result = await new Promise((resolve) => {
                    detector.onResults((results) => resolve(results));
                    detector.send({ image: this.selectedProfileImage });
                });

                const detection = result?.detections?.[0];
                const box = detection?.boundingBox;
                const nose = detection?.landmarks?.[2];

                if (!box) {
                    this.detectedFaceOrientation = 'unknown';
                    this.selectedPlacement = this.detectedSubjectCutEdge || 'right';
                    await this.renderProfileCanvases();
                    return;
                }

                const orientation = this.resolveProfileOrientationFromDetection(detection);
                this.detectedFaceOrientation = orientation;

                if (orientation === 'left') {
                    this.detectedFaceOrientation = 'left';
                    this.selectedPlacement = 'right';
                } else if (orientation === 'right') {
                    this.detectedFaceOrientation = 'right';
                    this.selectedPlacement = 'left';
                } else if (orientation === 'center') {
                    this.detectedFaceOrientation = 'center';
                    this.selectedPlacement = 'center';
                } else {
                    this.detectedFaceOrientation = 'unknown';
                    this.selectedPlacement = 'right';
                }

                if (this.detectedSubjectCutEdge) {
                    this.selectedPlacement = this.detectedSubjectCutEdge;
                }

                await this.renderProfileCanvases();
            } catch (error) {
                console.warn('Deteksi wajah gagal, memakai default placement.', error);
                this.detectedFaceOrientation = 'unknown';
                this.selectedPlacement = this.detectedSubjectCutEdge || 'right';
                await this.renderProfileCanvases();
            }
        },
        resolveProfileOrientationFromDetection(detection) {
            const box = detection?.boundingBox;
            const nose = detection?.landmarks?.[2];
            const faceCenterX = (nose?.x ?? box?.xCenter ?? 0.5) * this.selectedProfileImage.width;
            const metrics = this.selectedProfileMetrics;

            if (metrics?.hasUsableAlpha) {
                const bounds = metrics.bounds;
                const faceX = faceCenterX * metrics.scale;
                const subjectDelta = faceX - bounds.centerX;
                const threshold = Math.max(12, bounds.width * 0.055);

                if (subjectDelta > threshold) {
                    return 'left';
                }

                if (subjectDelta < -threshold) {
                    return 'right';
                }
            }

            if (nose && box) {
                const faceDelta = nose.x - box.xCenter;
                if (faceDelta < -0.025) {
                    return 'left';
                }
                if (faceDelta > 0.025) {
                    return 'right';
                }
            }

            return metrics?.hasUsableAlpha ? 'left' : 'center';
        },
        async applyPlacement(placement) {
            this.selectedPlacement = placement;
            await this.renderProfileCanvases();
        },
        async renderProfileCanvases() {
            if (!this.selectedProfileImage) return;

            const avatarCanvas = this.$refs.profileCanvas;
            const fullCanvas = this.$refs.profileFullCanvas;
            const sidebarCanvas = this.$refs.profileSidebarCanvas;
            if (!avatarCanvas || !fullCanvas || !sidebarCanvas) return;

            this.drawAvatarCanvas(avatarCanvas, this.selectedProfileImage);
            this.drawFullProfileCanvas(fullCanvas, this.selectedProfileImage);
            this.drawSidebarSnapshotCanvas(sidebarCanvas, this.selectedProfileImage);

            this.profileAvatarBlob = await this.canvasToBlob(avatarCanvas);
            this.profileFullBlob = await this.canvasToBlob(fullCanvas);
            this.profileSidebarBlob = await this.canvasToBlob(sidebarCanvas);

            this.revokeProfilePreview();
            this.resetProfileImageLoaded(['previewAvatar', 'previewFull', 'previewSidebar']);
            this.profilePreviewUrl = URL.createObjectURL(this.profileFullBlob);
            this.profileAvatarPreviewUrl = URL.createObjectURL(this.profileAvatarBlob);
            this.profileSidebarPreviewUrl = URL.createObjectURL(this.profileSidebarBlob);
        },
        drawSystemBackground(ctx, width, height) {
            const gradient = ctx.createLinearGradient(0, 0, width, height);
            gradient.addColorStop(0, '#0f172a');
            gradient.addColorStop(0.45, '#1d4ed8');
            gradient.addColorStop(1, '#14b8a6');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.11)';
            ctx.beginPath();
            ctx.arc(width * 0.82, height * 0.2, width * 0.28, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = 'rgba(15, 23, 42, 0.18)';
            ctx.beginPath();
            ctx.arc(width * 0.16, height * 0.92, width * 0.36, 0, Math.PI * 2);
            ctx.fill();
        },
        drawAvatarCanvas(canvas, image) {
            const size = 768;
            canvas.width = size;
            canvas.height = size;

            const ctx = canvas.getContext('2d');
            this.drawAvatarBackground(ctx, size);

            const metrics = this.selectedProfileMetrics;
            if (metrics?.hasUsableAlpha) {
                this.drawAvatarSubject(ctx, image, metrics, size, this.selectedPlacement);
                return;
            }

            this.drawCoverImage(ctx, image, 0, 0, size, size, 'center');
        },
        drawSidebarSnapshotCanvas(canvas, image) {
            const size = 192;
            canvas.width = size;
            canvas.height = size;

            const ctx = canvas.getContext('2d');
            this.drawAvatarBackground(ctx, size);

            const metrics = this.selectedProfileMetrics;
            if (metrics?.hasUsableAlpha) {
                this.drawAvatarSubject(ctx, image, metrics, size, this.selectedPlacement);
                return;
            }

            this.drawCoverImage(ctx, image, 0, 0, size, size, 'center');
        },
        drawAvatarBackground(ctx, size) {
            const gradient = ctx.createLinearGradient(0, 0, size, size);
            gradient.addColorStop(0, '#fffdf7');
            gradient.addColorStop(0.54, '#f7f2e5');
            gradient.addColorStop(1, '#e9dec1');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, size, size);

            const backChevron = ctx.createLinearGradient(0, size * 0.22, 0, size * 0.7);
            backChevron.addColorStop(0, 'rgba(207, 181, 105, 0.12)');
            backChevron.addColorStop(1, 'rgba(196, 165, 78, 0.34)');
            ctx.fillStyle = backChevron;
            ctx.beginPath();
            ctx.moveTo(size * -0.1, size * 0.56);
            ctx.lineTo(size * 0.5, size * 0.08);
            ctx.lineTo(size * 1.1, size * 0.56);
            ctx.lineTo(size * 1.1, size * 0.68);
            ctx.lineTo(size * 0.5, size * 0.2);
            ctx.lineTo(size * -0.1, size * 0.68);
            ctx.closePath();
            ctx.fill();

            const frontChevron = ctx.createLinearGradient(0, size * 0.46, 0, size * 1.02);
            frontChevron.addColorStop(0, '#e3bd38');
            frontChevron.addColorStop(1, '#b98d14');
            ctx.fillStyle = frontChevron;
            ctx.beginPath();
            ctx.moveTo(size * -0.16, size * 0.78);
            ctx.lineTo(size * 0.5, size * 0.26);
            ctx.lineTo(size * 1.16, size * 0.78);
            ctx.lineTo(size * 1.16, size * 0.96);
            ctx.lineTo(size * 0.5, size * 0.44);
            ctx.lineTo(size * -0.16, size * 0.96);
            ctx.closePath();
            ctx.fill();

            const light = ctx.createRadialGradient(size * 0.34, size * 0.12, 0, size * 0.34, size * 0.12, size * 0.7);
            light.addColorStop(0, 'rgba(255, 255, 255, 0.84)');
            light.addColorStop(0.48, 'rgba(255, 255, 255, 0.28)');
            light.addColorStop(1, 'rgba(255, 255, 255, 0)');
            ctx.fillStyle = light;
            ctx.fillRect(0, 0, size, size);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.28)';
            ctx.fillRect(0, 0, size, size * 0.09);
        },
        drawAvatarSubject(ctx, image, metrics, size, placement = 'center') {
            const isEdgePlacement = placement === 'left' || placement === 'right';
            const scaleBack = 1 / metrics.scale;
            const sourcePaddingX = metrics.bounds.width * (isEdgePlacement ? 0.02 : 0.08) * scaleBack;
            const sourcePaddingTop = metrics.bounds.height * (isEdgePlacement ? 0.025 : 0.045) * scaleBack;
            const sourcePaddingBottom = metrics.bounds.height * (isEdgePlacement ? 0.01 : 0.04) * scaleBack;
            const sourceX = Math.max(0, metrics.bounds.minX * scaleBack - sourcePaddingX);
            const sourceY = Math.max(0, metrics.bounds.minY * scaleBack - sourcePaddingTop);
            const sourceRight = Math.min(image.width, metrics.bounds.maxX * scaleBack + sourcePaddingX);
            const naturalBottom = metrics.bounds.maxY * scaleBack + sourcePaddingBottom;
            const tighterBottom =
                metrics.bounds.minY * scaleBack + metrics.bounds.height * scaleBack * (isEdgePlacement ? 0.72 : 0.82);
            const sourceBottom = Math.min(image.height, Math.min(naturalBottom, tighterBottom));
            const sourceWidth = sourceRight - sourceX;
            const sourceHeight = sourceBottom - sourceY;

            const targetWidth = size * (isEdgePlacement ? 1.48 : 1.12);
            const targetHeight = size * (isEdgePlacement ? 1.38 : 1.14);
            const drawScale = Math.min(targetWidth / sourceWidth, targetHeight / sourceHeight);
            const drawWidth = sourceWidth * drawScale;
            const drawHeight = sourceHeight * drawScale;
            const edgeBleed = size * (isEdgePlacement ? 0.11 : 0.03);
            const drawX =
                placement === 'left'
                    ? -edgeBleed
                    : placement === 'right'
                      ? size - drawWidth + edgeBleed
                      : (size - drawWidth) / 2;
            const drawY = isEdgePlacement
                ? drawHeight > size
                    ? size * -0.015
                    : size - drawHeight - size * 0.03
                : size - drawHeight - size * 0.015;

            ctx.save();
            ctx.shadowColor = 'rgba(15, 23, 42, 0.24)';
            ctx.shadowBlur = 22;
            ctx.shadowOffsetY = 12;
            ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, drawX, drawY, drawWidth, drawHeight);
            ctx.restore();
        },
        drawFullProfileCanvas(canvas, image) {
            const width = 1200;
            const height = 1500;
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, width, height);
            this.drawProfileSubject(ctx, image, 12, 18, width - 24, height - 18, this.selectedPlacement);
        },
        drawCoverImage(ctx, image, x, y, width, height, placement = 'center') {
            const scale = Math.max(width / image.width, height / image.height);
            const sourceWidth = width / scale;
            const sourceHeight = height / scale;
            const sourceX =
                placement === 'left'
                    ? 0
                    : placement === 'right'
                      ? image.width - sourceWidth
                      : (image.width - sourceWidth) / 2;
            const sourceY = (image.height - sourceHeight) / 2;
            ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, x, y, width, height);
        },
        drawContainImage(ctx, image, x, y, width, height, placement = 'center') {
            const scale = Math.min(width / image.width, height / image.height);
            const drawWidth = image.width * scale;
            const drawHeight = image.height * scale;
            const drawX =
                placement === 'left' ? x : placement === 'right' ? x + width - drawWidth : x + (width - drawWidth) / 2;
            const drawY = y + height - drawHeight;
            ctx.drawImage(image, drawX, drawY, drawWidth, drawHeight);
        },
        drawProfileSubject(ctx, image, x, y, width, height, placement = 'center') {
            const source = this.resolveProfileSubjectSource(image);
            const isEdgePlacement = placement === 'left' || placement === 'right';
            const sideRatio = placement === 'center' ? 0.88 : 1.08;
            const maxDrawWidth = width * sideRatio;
            const scale = Math.min(maxDrawWidth / source.width, (height * (isEdgePlacement ? 1.1 : 1)) / source.height);
            const drawWidth = source.width * scale;
            const drawHeight = source.height * scale;
            const edgePadding = isEdgePlacement ? -width * 0.015 : 0;
            const drawX =
                placement === 'left'
                    ? x + edgePadding
                    : placement === 'right'
                      ? x + width - drawWidth - edgePadding
                      : x + (width - drawWidth) / 2;
            const drawY = isEdgePlacement && drawHeight > height ? y + height * 0.015 : y + height - drawHeight;

            ctx.save();
            ctx.shadowColor = 'rgba(15, 23, 42, 0.35)';
            ctx.shadowBlur = 34;
            ctx.shadowOffsetY = 22;
            ctx.drawImage(image, source.x, source.y, source.width, source.height, drawX, drawY, drawWidth, drawHeight);
            ctx.restore();

            const groundGradient = ctx.createRadialGradient(
                drawX + drawWidth / 2,
                y + height - 8,
                drawWidth * 0.08,
                drawX + drawWidth / 2,
                y + height - 8,
                drawWidth * 0.48,
            );
            groundGradient.addColorStop(0, 'rgba(15, 23, 42, 0.2)');
            groundGradient.addColorStop(1, 'rgba(15, 23, 42, 0)');
            ctx.fillStyle = groundGradient;
            ctx.beginPath();
            ctx.ellipse(drawX + drawWidth / 2, y + height - 6, drawWidth * 0.48, 34, 0, 0, Math.PI * 2);
            ctx.fill();
        },
        resolveProfileSubjectSource(image) {
            const metrics = this.selectedProfileMetrics;
            if (!metrics?.hasUsableAlpha) {
                return {
                    x: 0,
                    y: 0,
                    width: image.width,
                    height: image.height,
                };
            }

            const scaleBack = 1 / metrics.scale;
            const paddingX = metrics.bounds.width * 0.08 * scaleBack;
            const paddingTop = metrics.bounds.height * 0.035 * scaleBack;
            const paddingBottom = metrics.bounds.height * 0.025 * scaleBack;
            const sourceX = Math.max(0, metrics.bounds.minX * scaleBack - paddingX);
            const sourceY = Math.max(0, metrics.bounds.minY * scaleBack - paddingTop);
            const sourceRight = Math.min(image.width, metrics.bounds.maxX * scaleBack + paddingX);
            const sourceBottom = Math.min(image.height, metrics.bounds.maxY * scaleBack + paddingBottom);

            return {
                x: sourceX,
                y: sourceY,
                width: Math.max(1, sourceRight - sourceX),
                height: Math.max(1, sourceBottom - sourceY),
            };
        },
        canvasToBlob(canvas) {
            return new Promise((resolve, reject) => {
                canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Gagal membuat file preview.'));
                        }
                    },
                    'image/png',
                    0.92,
                );
            });
        },
        async submitProfilePhoto() {
            if (
                !this.selectedProfileFile ||
                !this.profileAvatarBlob ||
                !this.profileFullBlob ||
                !this.profileSidebarBlob
            ) {
                ElMessage.error('Pilih foto profil terlebih dahulu.');
                return;
            }

            const formData = new FormData();
            formData.append('avatar', this.profileAvatarBlob, 'avatar.png');
            formData.append('full', this.profileFullBlob, 'full.png');
            formData.append('sidebar', this.profileSidebarBlob, 'sidebar.png');
            formData.append('original', this.selectedProfileFile, this.selectedProfileFile.name);
            formData.append('face_orientation', this.detectedFaceOrientation);
            formData.append('placement', this.selectedPlacement);

            try {
                this.profileUploadLoading = true;
                const response = await axios.post('/user-profile/photo', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                });

                this.activeProfilePhoto = response.data?.profile_photo || null;
                this.syncShellProfilePhotoCache(this.activeProfilePhoto);
                ElNotification.success(response.data?.message || 'Foto profil berhasil disimpan.');
                this.clearProfileUploadState();
                this.profilePhotoModalMode = 'view';
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal menyimpan foto profil.');
            } finally {
                this.profileUploadLoading = false;
            }
        },
        syncShellProfilePhotoCache(photo) {
            if (!photo?.sidebar_url && !photo?.avatar_url) return;

            const payload = {
                userId: this.user?.Kode_Karyawan || this.user?.Id || this.user?.Id_Users || '',
                version: photo.updated_at || photo.id || Date.now(),
                url: photo.sidebar_url || photo.avatar_url,
                expiresAt: Date.now() + 6 * 60 * 60 * 1000,
            };

            try {
                window.localStorage.setItem('shellProfilePhotoSnapshot', JSON.stringify(payload));
                window.dispatchEvent(new CustomEvent('profile-photo:snapshot-updated', { detail: payload }));
            } catch (error) {
                console.warn('Gagal menyimpan cache foto sidebar.', error);
            }
        },
        closeFaceDetection() {
            if (this.faceDetection && typeof this.faceDetection.close === 'function') {
                this.faceDetection.close();
            }
            this.faceDetection = null;
        },
        resetPassword() {
            this.resetForm();
            this.showResetPasswordModal = true;
        },
        closeResetPasswordModal() {
            if (this.loading) return;
            this.showResetPasswordModal = false;
            this.resetForm();
        },
        handleResetPasswordModalVisibility(value) {
            if (value) {
                this.showResetPasswordModal = true;
                return;
            }

            this.closeResetPasswordModal();
        },
        resetForm() {
            this.currentPassword = '';
            this.newPassword = '';
            this.confirmPassword = '';
            this.showCurrentPassword = false;
            this.showNewPassword = false;
            this.showConfirmPassword = false;
            this.passwordStrength = 0;
        },
        checkPasswordStrength() {
            let strength = 0;
            if (this.newPassword.length >= 8) strength++;
            if (/[A-Z]/.test(this.newPassword)) strength++;
            if (/[a-z]/.test(this.newPassword)) strength++;
            if (/[0-9]/.test(this.newPassword)) strength++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(this.newPassword)) strength++;
            this.passwordStrength = strength;
        },
        async submitResetPassword() {
            if (!this.isFormValid) return;

            try {
                this.loading = true;
                const response = await axios.post('/updatePassword', {
                    oldPass: this.currentPassword,
                    newPass: this.newPassword,
                });

                if (response.status === 200) {
                    ElNotification.success('Password berhasil diubah!');
                    this.showResetPasswordModal = false;
                    this.resetForm();
                } else {
                    ElMessage.error('Gagal mengubah password. Silakan coba lagi.');
                }
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal mengubah password. Silakan coba lagi.');
            } finally {
                this.loading = false;
            }
        },
        capitalize(value) {
            if (!value) return '';
            return String(value)
                .split(' ')
                .map((word) => (word ? word.charAt(0).toUpperCase() + word.slice(1).toLowerCase() : ''))
                .join(' ');
        },
        truncateText(text, length) {
            return text.length > length ? `${text.substring(0, length)}...` : text;
        },
        editPersonalInfo() {
            ElMessage.info('Fitur edit profil belum tersedia.');
        },
        maskCardNumber(val) {
            if (!val) return '—';
            const clean = String(val).trim();
            if (clean.length <= 4) return clean;
            const lastFour = clean.slice(-4);
            const prefixLength = Math.max(0, clean.length - 4);
            const maskedPart = '•'.repeat(prefixLength);
            const combined = maskedPart + lastFour;
            const chunks = combined.match(/.{1,4}/g);
            return chunks ? chunks.join(' ') : combined;
        },
        formatCardNumberGrouping(val) {
            if (!val) return '—';
            const clean = String(val).trim();
            const stripped = clean.replace(/\s+/g, '');
            const chunks = stripped.match(/.{1,4}/g);
            return chunks ? chunks.join(' ') : clean;
        },
        copyToClipboard(text, label) {
            if (!text || text === '-' || text === 'Belum terdaftar') {
                ElMessage.warning(`Nomor ${label} belum terdaftar.`);
                return;
            }
            const cleanText = String(text).trim();
            if (navigator?.clipboard?.writeText) {
                navigator.clipboard
                    .writeText(cleanText)
                    .then(() => {
                        this.copiedField = label;
                        ElMessage.success(`Nomor ${label} berhasil disalin!`);
                        setTimeout(() => {
                            if (this.copiedField === label) this.copiedField = null;
                        }, 2000);
                    })
                    .catch(() => {
                        this.fallbackCopyText(cleanText, label);
                    });
            } else {
                this.fallbackCopyText(cleanText, label);
            }
        },
        fallbackCopyText(text, label) {
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const success = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (success) {
                    this.copiedField = label;
                    ElMessage.success(`Nomor ${label} berhasil disalin!`);
                    setTimeout(() => {
                        if (this.copiedField === label) this.copiedField = null;
                    }, 2000);
                } else {
                    ElMessage.error(`Gagal menyalin nomor ${label}.`);
                }
            } catch (err) {
                ElMessage.error(`Gagal menyalin nomor ${label}.`);
            }
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

.bi {
    display: contents !important;
}
.profile-page {
    --accent: var(--hcis-violet-600);
    --accent-dark: var(--hcis-indigo-600);
    --text: var(--hcis-slate-900);
    --muted: var(--hcis-slate-500);
    --soft: var(--hcis-slate-50);
    --line: #e8ecf1;
    min-height: 100%;
    padding: 20px 24px 40px;
    color: var(--text);
    background: #f4f6f9;
}

button,
input,
select {
    font: inherit;
}

.profile-hero {
    position: relative;
    margin: 0 auto 20px;
    overflow: hidden;
    color: #fff;
    background: linear-gradient(135deg, var(--accent), var(--hcis-blue-600));
    border-radius: 20px;
    box-shadow: 0 18px 45px rgba(79, 70, 229, 0.18);
}

.hero-orb {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
}

.hero-orb--right {
    top: -120px;
    right: -80px;
    width: 320px;
    height: 320px;
}

.hero-orb--center {
    bottom: -90px;
    left: 42%;
    width: 180px;
    height: 180px;
}

.hero-content {
    position: relative;
    z-index: 1;
    padding: 28px 28px 0;
}

.hero-top {
    display: flex;
    align-items: flex-start;
    gap: 20px;
}

.hero-avatar {
    --avatar-card-bg:
        radial-gradient(circle at 28% 14%, rgba(255, 255, 255, 0.86), transparent 34%),
        linear-gradient(135deg, transparent 0 38%, rgba(207, 181, 105, 0.18) 38% 48%, transparent 48% 100%),
        linear-gradient(45deg, transparent 0 42%, rgba(218, 180, 49, 0.92) 42% 53%, transparent 53% 100%),
        linear-gradient(180deg, #fffdf7, #eee4c9);
    position: relative;
    display: grid;
    flex: 0 0 auto;
    width: 72px;
    height: 72px;
    place-items: center;
    padding: 4px;
    border: 1px solid rgba(221, 178, 45, 0.48);
    border-radius: 23px;
    background: var(--avatar-card-bg);
    color: #2f2b22;
    cursor: pointer;
    font-size: 28px;
    font-weight: 800;
    overflow: hidden;
    backdrop-filter: blur(8px);
    box-shadow:
        0 0 0 3px rgba(255, 255, 255, 0.42),
        0 14px 30px rgba(15, 23, 42, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.72);
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.hero-avatar:hover {
    transform: translateY(-1px);
    box-shadow:
        0 0 0 3px rgba(255, 255, 255, 0.5),
        0 18px 38px rgba(15, 23, 42, 0.24),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
}

.hero-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 18px;
    object-fit: cover;
    background: var(--avatar-card-bg);
    box-shadow: inset 0 0 0 1px rgba(142, 106, 21, 0.12);
}

.hero-avatar span {
    display: grid;
    width: 100%;
    height: 100%;
    place-items: center;
    border-radius: 18px;
    background: var(--avatar-card-bg);
    box-shadow: inset 0 0 0 1px rgba(142, 106, 21, 0.12);
}

.hero-avatar__badge {
    position: absolute;
    right: 2px;
    bottom: 2px;
    display: grid;
    width: 19px;
    height: 19px;
    place-items: center;
    border: 2px solid rgba(255, 255, 255, 0.92);
    border-radius: 999px;
    color: #2a2110;
    background: #e3bd38;
    font-size: 9px;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.22);
}

.hero-main {
    min-width: 0;
    flex: 1;
}

.hero-name-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}

.hero-name-row h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    line-height: 1.15;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border: 1px solid rgba(74, 222, 128, 0.25);
    border-radius: 99px;
    color: var(--hcis-green-200);
    background: rgba(74, 222, 128, 0.2);
    font-size: 11px;
    font-weight: 700;
}

.status-badge .bi {
    font-size: 6px;
}

.hero-role,
.hero-dept {
    margin: 0;
}

.hero-role {
    font-size: 15px;
    font-weight: 650;
    opacity: 0.92;
}

.hero-dept {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: 13px;
    font-weight: 500;
    opacity: 0.76;
}

.hero-actions {
    display: flex;
    gap: 8px;
    align-self: center;
    flex-shrink: 0;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 38px;
    padding: 9px 16px;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 650;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.hero-btn:hover {
    transform: translateY(-1px);
}

.hero-btn--glass {
    color: #fff;
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
}

.hero-btn--light {
    color: var(--hcis-slate-800);
    border-color: #fff;
    background: #fff;
}

.hero-stats {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.1);
}

.hero-stat {
    min-width: 0;
    padding: 16px 20px;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.hero-stat:last-child {
    border-right: 0;
}

.hero-stat span {
    display: block;
    font-size: 10px;
    font-weight: 750;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    opacity: 0.68;
}

.hero-stat strong {
    display: block;
    margin-top: 4px;
    overflow: hidden;
    font-size: 16px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hero-stat small {
    display: block;
    margin-top: 2px;
    overflow: hidden;
    font-size: 11px;
    font-weight: 500;
    opacity: 0.62;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-tabs,
.profile-main {
    margin-right: auto;
    margin-left: auto;
}

.profile-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    padding: 4px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 38px;
    padding: 8px 16px;
    border: 0;
    border-radius: 10px;
    color: var(--muted);
    background: transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 750;
    transition:
        background 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease;
}

.tab-btn small {
    display: inline-flex;
    min-width: 20px;
    height: 20px;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    border-radius: 99px;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-100);
    font-size: 11px;
    font-weight: 800;
}

.tab-btn.active {
    color: #fff;
    background: var(--accent);
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.26);
}

.tab-btn.active small {
    color: var(--accent);
    background: #fff;
}

.info-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 380px;
    gap: 16px;
    align-items: start;
}

.info-primary,
.info-sidebar,
.tab-panel {
    display: grid;
    gap: 16px;
}

.info-sidebar {
    position: sticky;
    top: 76px;
}

.profile-card {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.035);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.card-title-group {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
}

.card-title-group span,
.list-toolbar span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.card-title-group h2,
.list-toolbar h2 {
    margin: 2px 0 0;
    color: var(--text);
    font-size: 16px;
    font-weight: 780;
    line-height: 1.25;
}

.card-icon {
    display: grid;
    flex: 0 0 auto;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 10px;
    font-size: 17px;
}

.card-icon.is-accent {
    color: var(--accent);
    background: var(--hcis-purple-100);
}

.card-icon.is-purple {
    color: var(--hcis-violet-600);
    background: #ede9fe;
}

.card-icon.is-warning {
    color: var(--hcis-amber-600);
    background: var(--hcis-amber-100);
}

.card-icon.is-sky {
    color: var(--hcis-sky-600);
    background: var(--hcis-sky-100);
}

.card-icon.is-teal {
    color: #0d9488;
    background: #ccfbf1;
}

.card-icon.is-emerald {
    color: var(--hcis-emerald-600);
    background: var(--hcis-emerald-100);
}

.card-icon.is-success {
    color: var(--hcis-green-600);
    background: var(--hcis-green-100);
}

.card-body {
    padding: 4px 20px 20px;
}

.soft-btn,
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    color: var(--muted);
    background: #fff;
    cursor: pointer;
}

.soft-btn {
    gap: 6px;
    min-height: 32px;
    padding: 7px 12px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
}

.soft-btn:hover,
.icon-btn:hover {
    color: var(--accent);
    border-color: #ddd6fe;
    background: var(--hcis-violet-50);
}

.icon-btn {
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 8px;
}

.field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 24px;
}

.data-field {
    display: flex;
    min-width: 0;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid var(--hcis-slate-50);
}

.data-field > i {
    flex: 0 0 auto;
    margin-top: 2px;
    color: var(--hcis-slate-400);
    font-size: 15px;
}

.data-field div {
    min-width: 0;
}

.data-field span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 12px;
    font-weight: 600;
}

.data-field strong {
    display: block;
    margin-top: 3px;
    color: var(--hcis-slate-800);
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.tone-badge,
.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.tone-badge {
    min-height: 24px;
    padding: 4px 10px;
}

.count-badge {
    min-height: 26px;
    padding: 4px 11px;
    color: var(--accent);
    background: var(--hcis-violet-50);
}

.tone-badge.is-info {
    color: var(--hcis-sky-600);
    background: var(--hcis-sky-100);
}

.tone-badge.is-success {
    color: var(--hcis-green-600);
    background: var(--hcis-green-100);
}

.tone-badge.is-purple {
    color: var(--hcis-violet-600);
    background: #ede9fe;
}

.role-highlight {
    margin-bottom: 16px;
    padding: 16px 18px;
    border: 1px solid #ede9fe;
    border-radius: 14px;
    background: linear-gradient(135deg, #faf5ff, var(--hcis-slate-50));
}

.role-highlight span {
    display: block;
    color: var(--accent);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.role-highlight strong {
    display: block;
    margin-top: 4px;
    color: var(--text);
    font-size: 18px;
    font-weight: 800;
}

.role-highlight small {
    display: block;
    margin-top: 2px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
}

.approval-flow {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.approval-node,
.approval-chip {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
    border-radius: 12px;
}

.approval-node {
    padding: 10px 12px;
    border: 1px solid var(--hcis-amber-200);
    background: var(--hcis-amber-50);
}

.approval-node strong,
.approval-chip strong,
.membership-info strong,
.person-info strong {
    display: block;
    color: var(--text);
    font-size: 13px;
    font-weight: 750;
}

.approval-node span,
.approval-chip span,
.membership-info span,
.person-info span {
    display: block;
    margin-top: 2px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 600;
}

.approval-arrow {
    color: var(--hcis-slate-300);
    font-size: 14px;
}

.mini-avatar {
    display: grid;
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: var(--accent);
    font-size: 11px;
    font-weight: 850;
}

.mini-avatar.is-green {
    background: var(--hcis-green-600);
}

.mini-avatar.is-purple {
    background: var(--hcis-violet-600);
}

.empty-state,
.empty-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 148px;
    padding: 24px;
    color: var(--hcis-slate-400);
    text-align: center;
}

.empty-state.compact {
    min-height: 0;
    flex-direction: row;
    justify-content: flex-start;
    padding: 8px 0 0;
    text-align: left;
}

.empty-state i,
.empty-page i {
    font-size: 24px;
}

.empty-state strong,
.empty-page strong {
    color: var(--muted);
    font-size: 14px;
}

.empty-state span,
.empty-page span {
    font-size: 12px;
    font-weight: 600;
}

.empty-page {
    min-height: 260px;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.72);
}

.empty-page.is-inside {
    margin: 0 20px 20px;
}

.privacy-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #99f6e4;
    border-radius: 10px;
    color: #0d9488;
    background: #f0fdfa;
    font-size: 12px;
    font-weight: 700;
}

.document-list,
.approval-rights,
.membership-list {
    display: grid;
    gap: 8px;
}

.document-row {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--hcis-slate-100);
    border-radius: 10px;
    background: var(--soft);
}

.document-icon {
    display: grid;
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 8px;
    color: #0d9488;
    background: #ccfbf1;
}

.document-info {
    min-width: 0;
    flex: 1;
}

.document-info span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 11px;
    font-weight: 650;
}

.document-info strong {
    display: block;
    overflow: hidden;
    color: var(--muted);
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.document-info strong.revealed {
    color: var(--text);
    font-family: inherit;
}

.approval-chip {
    padding: 10px 12px;
    border: 1px solid var(--hcis-green-100);
    background: var(--hcis-green-50);
}

/* ==========================================================================
   Informasi BPJS (Table / Row Layout with Official Logos)
   ========================================================================== */
.bpjs-info-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.035);
}

.bpjs-list {
    display: flex;
    flex-direction: column;
    padding: 6px 0;
}

.bpjs-item-row {
    display: grid;
    grid-template-columns: 220px 1fr;
    align-items: center;
    gap: 28px;
    padding: 18px 12px;
}

.bpjs-brand-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-width: 0;
}

.bpjs-brand-logo {
    height: 54px;
    width: auto;
    max-width: 200px;
    object-fit: contain;
    flex-shrink: 0;
    margin: 0 auto;
}

.bpjs-details-cell {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    min-width: 0;
}

.bpjs-detail-line {
    display: flex;
    align-items: center;
    font-size: 14px;
    line-height: 1.4;
}

.bpjs-detail-label {
    width: 140px;
    color: var(--hcis-slate-700);
    font-weight: 600;
    flex-shrink: 0;
}

.bpjs-detail-colon {
    margin-right: 14px;
    color: var(--hcis-slate-500);
    font-weight: 600;
    flex-shrink: 0;
}

.bpjs-detail-val-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.bpjs-detail-val {
    color: var(--hcis-slate-900);
    font-weight: 800;
    font-size: 14.5px;
    letter-spacing: 0.02em;
    word-break: break-all;
}

.bpjs-detail-val.is-muted {
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.bpjs-inline-copy {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    cursor: pointer;
    font-size: 12px;
    transition: all 0.15s ease;
}

.bpjs-inline-copy:hover {
    color: var(--accent);
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-100);
}

.bpjs-status-text {
    font-weight: 750;
    font-size: 14px;
}

.bpjs-status-text.is-active {
    color: var(--hcis-slate-900);
}

.bpjs-status-text.is-inactive {
    color: var(--hcis-slate-400);
    font-weight: 500;
    font-style: italic;
}

.bpjs-divider {
    height: 1px;
    background: var(--hcis-slate-100);
    margin: 4px 0;
}

.list-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 20px;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute !important;
    left: 11px;
    color: var(--hcis-slate-400);
    font-size: 14px;
    display: flex !important;
}

.search-box input,
.filter-select {
    height: 36px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    color: var(--hcis-slate-600);
    background: var(--soft);
    font-size: 13px;
}

.search-box input {
    width: 220px;
    padding: 8px 12px 8px 34px;
}

.filter-select {
    min-width: 190px;
    padding: 8px 12px;
    cursor: pointer;
}

.search-box input:focus,
.filter-select:focus {
    outline: 2px solid rgba(124, 58, 237, 0.2);
    border-color: #c4b5fd;
    background: #fff;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}

.person-card {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.035);
}

.person-avatar {
    display: grid;
    flex: 0 0 auto;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(135deg, var(--accent), var(--hcis-blue-600));
    font-size: 13px;
    font-weight: 850;
}

.person-info,
.membership-info {
    min-width: 0;
    flex: 1;
}

.person-info strong,
.person-info span,
.membership-info strong,
.membership-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-phone,
.membership-phone {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.person-phone.muted {
    color: var(--hcis-slate-300);
}

.membership-list {
    padding: 0 0 8px;
}

.membership-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border-top: 1px solid var(--hcis-slate-50);
}

.membership-row:hover {
    background: #fafbfc;
}

.profile-photo-view,
.profile-photo-upload {
    display: grid;
    gap: 18px;
}

:global(.profile-photo-modal-body) {
    padding: 0;
}

.profile-photo-banner {
    height: 190px;
    overflow: hidden;
    background: var(--hcis-slate-900);
}

.profile-photo-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    /* margin-top: -2%; */
    filter: blur(1px) brightness(0.8);
}

.profile-photo-identity {
    display: grid;
    grid-template-columns: 240px minmax(0, 1fr);
    gap: 22px;
    align-items: end;
    padding: 0 26px;
    margin-top: -72px;
}

.profile-photo-frame {
    --profile-photo-card-bg:
        radial-gradient(circle at 30% 12%, rgba(255, 255, 255, 0.86), transparent 32%),
        linear-gradient(135deg, transparent 0 34%, rgba(207, 181, 105, 0.18) 34% 45%, transparent 45% 100%),
        linear-gradient(45deg, transparent 0 43%, rgba(221, 178, 45, 0.9) 43% 54%, transparent 54% 100%),
        linear-gradient(180deg, #fffdf7, #eee4c9);
    position: relative;
    display: grid;
    width: 240px;
    aspect-ratio: 1;
    place-items: center;
    overflow: hidden;
    padding: 6px;
    border: 1px solid rgba(221, 178, 45, 0.5);
    border-radius: 22px;
    color: #2f2b22;
    background: var(--profile-photo-card-bg);
    box-shadow:
        0 0 0 5px rgba(255, 255, 255, 0.78),
        0 18px 42px rgba(15, 23, 42, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 0.74);
    font-size: 54px;
    font-weight: 850;
}

.profile-photo-frame::before {
    position: absolute;
    inset: calc(18% + 6px);
    border: 16px solid rgba(198, 162, 57, 0.16);
    border-bottom: 0;
    border-radius: 10px 10px 0 0;
    content: '';
    pointer-events: none;
    transform: rotate(45deg) translateY(16%);
}

.profile-photo-frame img {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    background: var(--profile-photo-card-bg);
    object-fit: cover;
}

.profile-photo-frame.is-clickable {
    cursor: pointer;
    transition:
        box-shadow 180ms ease,
        transform 180ms ease;
}

.profile-photo-frame.is-clickable::after {
    position: absolute;
    right: 12px;
    bottom: 12px;
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.58);
    border-radius: 50%;
    color: #fff;
    background: rgba(15, 23, 42, 0.64);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.24);
    content: '\F1DD';
    font-family: 'bootstrap-icons';
    font-size: 16px;
    line-height: 1;
    backdrop-filter: blur(10px);
}

.profile-photo-frame.is-clickable:hover {
    box-shadow: 0 22px 52px rgba(15, 23, 42, 0.26);
    transform: translateY(-2px);
}

.profile-photo-frame.is-clickable:focus-visible {
    outline: 3px solid rgba(37, 99, 235, 0.34);
    outline-offset: 4px;
}

.profile-photo-name-tag {
    min-width: 0;
    margin-bottom: 18px;
    padding: 18px 20px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.profile-photo-name-tag > span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.profile-photo-name-tag h3 {
    margin: 4px 0 10px;
    color: var(--text);
    font-size: 24px;
    font-weight: 850;
    line-height: 1.15;
}

.profile-photo-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.profile-photo-meta strong,
.profile-photo-meta small {
    display: inline-flex;
    min-height: 26px;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.profile-photo-meta strong {
    color: #1d4ed8;
    background: #dbeafe;
}

.profile-photo-meta small {
    color: #0f766e;
    background: #ccfbf1;
}

.profile-photo-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 0 26px 24px;
}

.profile-photo-btn {
    appearance: none;
    border: 1px solid transparent;
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 15px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    transition:
        background-color 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease,
        color 180ms ease,
        transform 180ms ease;
}

.profile-photo-btn.is-muted {
    border-color: #d8e0ec;
    color: var(--hcis-slate-600);
    background: var(--hcis-white);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.profile-photo-btn.is-primary {
    border-color: var(--hcis-blue-600);
    color: #fff;
    background: var(--hcis-blue-600);
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
}

.profile-photo-btn:not(:disabled):hover {
    transform: translateY(-1px);
}

.profile-photo-btn.is-muted:not(:disabled):hover {
    border-color: #c4d3e7;
    color: var(--hcis-slate-800);
    background: var(--hcis-slate-50);
}

.profile-photo-btn.is-primary:not(:disabled):hover {
    border-color: #1d4ed8;
    background: #1d4ed8;
    box-shadow: 0 14px 28px rgba(37, 99, 235, 0.3);
}

.profile-photo-btn:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.profile-photo-btn.is-iconic {
    min-height: 36px;
    padding-inline: 12px;
}

.id-card-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 1090;
    display: grid;
    place-items: center;
    overflow: hidden;
    padding: 34px 18px;
    background:
        radial-gradient(circle at 50% 18%, rgba(227, 187, 77, 0.24), transparent 22%),
        linear-gradient(180deg, rgba(6, 8, 12, 0.74), rgba(6, 8, 12, 0.88));
    backdrop-filter: blur(16px) saturate(118%);
    -webkit-backdrop-filter: blur(16px) saturate(118%);
}

.id-card-fullscreen::before {
    position: absolute;
    inset: -20% -10% 0;
    background:
        conic-gradient(
            from 180deg at 50% -6%,
            transparent 0 33%,
            rgba(255, 227, 147, 0.2) 42%,
            rgba(255, 245, 210, 0.42) 50%,
            rgba(255, 227, 147, 0.2) 58%,
            transparent 68% 100%
        ),
        radial-gradient(ellipse at 50% 86%, rgba(207, 160, 34, 0.18), transparent 34%);
    content: '';
    opacity: 0.95;
    pointer-events: none;
}

.id-card-spotlight {
    position: absolute;
    top: -26vh;
    left: 50%;
    width: min(720px, 94vw);
    height: 72vh;
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        rgba(255, 243, 205, 0.52),
        rgba(219, 177, 62, 0.2) 42%,
        transparent 70%
    );
    filter: blur(18px);
    opacity: 0.9;
    pointer-events: none;
    transform: translateX(-50%);
    animation: idCardSpotlightIn 760ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.id-card-close-btn {
    position: absolute;
    top: 22px;
    right: 24px;
    z-index: 4;
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 50%;
    color: #fff7df;
    background: rgba(15, 23, 42, 0.38);
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.22);
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition:
        background 180ms ease,
        transform 180ms ease;
}

.id-card-close-btn:hover {
    background: rgba(15, 23, 42, 0.58);
    transform: translateY(-1px);
}

.id-card-stage {
    position: relative;
    z-index: 2;
    display: grid;
    width: min(390px, 82vw);
    place-items: center;
    animation: idCardEnter 760ms cubic-bezier(0.2, 0.8, 0.2, 1) 80ms both;
}

.id-card-floor-shadow {
    position: absolute;
    bottom: -24px;
    width: 74%;
    height: 44px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, rgba(0, 0, 0, 0.54), transparent 70%);
    filter: blur(9px);
    transform: perspective(600px) rotateX(62deg);
}

.id-card-perspective {
    width: 100%;
    perspective: 1400px;
}

.id-card-shell {
    --id-card-rotate-x: 0deg;
    --id-card-rotate-y: 0deg;
    --id-card-glare-x: 50%;
    --id-card-glare-y: 18%;
    position: relative;
    width: 100%;
    aspect-ratio: 0.63;
    cursor: pointer;
    filter: drop-shadow(0 34px 48px rgba(0, 0, 0, 0.34));
    transform: rotateX(var(--id-card-rotate-x)) rotateY(var(--id-card-rotate-y));
    transition:
        transform 700ms cubic-bezier(0.2, 0.8, 0.2, 1),
        filter 360ms ease;
}

.id-card-shell.is-flipped {
    filter: drop-shadow(0 38px 58px rgba(0, 0, 0, 0.42));
}

.id-card-flipper {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    transition: transform 760ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.id-card-shell.is-flipped .id-card-flipper {
    transform: rotateY(180deg);
}

.id-card-shell:focus-visible {
    outline: 3px solid rgba(231, 184, 58, 0.5);
    outline-offset: 12px;
}

.id-card-face {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border: 1px solid rgba(116, 93, 35, 0.16);
    border-radius: 20px;
    color: #201d17;
    background: linear-gradient(180deg, #fbfaf6, #f0eee6), #f7f5ee;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.9),
        inset 0 -1px 0 rgba(132, 105, 33, 0.14);
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    -webkit-transform-style: preserve-3d;
    transform-style: preserve-3d;
}

.id-card-face::before {
    position: absolute;
    inset: 0;
    border: 10px solid rgba(255, 255, 255, 0.26);
    border-radius: inherit;
    content: '';
    pointer-events: none;
}

.id-card-face--front {
    padding: 26px 24px 22px;
}

.id-card-face--back {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto auto;
    gap: 12px;
    padding: 24px 22px 0;
    transform: rotateY(180deg);
}

.id-card-glare {
    position: absolute;
    inset: 0;
    z-index: 5;
    background:
        radial-gradient(
            circle at var(--id-card-glare-x) var(--id-card-glare-y),
            rgba(255, 255, 255, 0.46),
            rgba(255, 255, 255, 0.13) 20%,
            transparent 42%
        ),
        linear-gradient(118deg, transparent 24%, rgba(255, 255, 255, 0.24) 44%, transparent 61%);
    opacity: 0.72;
    pointer-events: none;
}

.id-card-chevron {
    position: absolute;
    left: 50%;
    width: 118%;
    height: 250px;
    border: 0;
    border-radius: 0;
    clip-path: polygon(0 74%, 50% 0, 100% 74%, 100% 91%, 50% 17%, 0 91%);
    pointer-events: none;
    transform: translateX(-50%);
}

.id-card-chevron--back {
    top: 146px;
    background: linear-gradient(180deg, rgba(207, 181, 105, 0.22), rgba(196, 165, 78, 0.34));
}

.id-card-chevron--front {
    top: 244px;
    height: 270px;
    background: linear-gradient(180deg, rgba(204, 160, 31, 0.94), rgba(187, 143, 20, 0.9));
}

.id-card-front-header,
.id-card-front-info,
.id-card-qr,
.id-card-portrait,
.id-card-back-header,
.id-card-terms,
.id-card-back-logos,
.id-card-back-footer {
    position: relative;
    z-index: 2;
    transform: translateZ(34px);
}

.id-card-front-header {
    display: grid;
    justify-items: center;
}

.id-card-front-header img {
    width: 86px;
    max-height: 70px;
    object-fit: contain;
    filter: drop-shadow(0 6px 10px rgba(145, 112, 21, 0.18));
}

.id-card-portrait {
    position: absolute;
    right: -34px;
    bottom: -4px;
    z-index: 3;
    width: 73%;
    height: 66%;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    overflow: hidden;
    transform: translateZ(50px);
}

.id-card-portrait img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center bottom;
    filter: drop-shadow(-18px 22px 20px rgba(34, 28, 16, 0.24)) saturate(1.02) contrast(1.03);
}

.id-card-front-info {
    position: absolute;
    left: 24px;
    bottom: 112px;
    max-width: 42%;
    /* padding: 9px 12px 9px 0;
    background: linear-gradient(90deg, rgba(222, 199, 135, 0.58), rgba(222, 199, 135, 0.12)); */
}

.id-card-front-info h3 {
    overflow-wrap: anywhere;
    margin: 0 0 8px;
    color: #171717;
    font-size: 21px;
    font-weight: 950;
    line-height: 1.02;
    text-transform: uppercase;
}

.id-card-front-info strong {
    display: block;
    color: #171717;
    font-size: 17px;
    font-weight: 950;
    letter-spacing: 0.02em;
}

.id-card-qr {
    position: absolute;
    left: 24px;
    bottom: 42px;
    display: block;
    width: 72px;
    height: 72px;
    /* padding: 4px; */
    background: #fff;
    box-shadow: 0 4px 0 rgba(188, 142, 14, 0.85);
}

.id-card-qr svg {
    display: block;
    width: 100%;
    height: 100%;
}

.id-card-front-strip {
    position: absolute;
    right: 0;
    bottom: 36px;
    left: 80px;
    height: 46px;
    background:
        linear-gradient(90deg, rgba(193, 151, 25, 0.9), rgba(193, 151, 25, 0.78) 48%, rgba(193, 151, 25, 0.92)), #c19719;
}

.id-card-front-strip::before {
    position: absolute;
    inset: -18px auto auto 0;
    width: 100%;
    height: 18px;
    background: rgba(218, 199, 145, 0.58);
    content: '';
}

.id-card-back-header {
    display: grid;
    justify-items: center;
    gap: 12px;
    text-align: center;
}

.id-card-back-header img {
    width: 72px;
    max-height: 54px;
    object-fit: contain;
}

.id-card-back-header h3 {
    margin: 0;
    color: #2b2b2b;
    font-size: 13px;
    font-weight: 950;
    letter-spacing: 0.08em;
}

.id-card-terms {
    display: grid;
    align-content: start;
    gap: 12px;
    padding: 12px 16px 0;
}

.id-card-terms p {
    margin: 0;
    color: #4a4a4a;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
    text-align: left;
}

.id-card-back-logos {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: end;
    gap: 10px;
    padding: 0 10px;
}

.id-card-back-logos img {
    width: 100%;
    max-height: 34px;
    object-fit: contain;
}

.id-card-back-footer {
    display: grid;
    justify-items: center;
    gap: 3px;
    margin: 0 -22px;
    padding: 12px 18px 14px;
    color: #211b0d;
    background: #c8a326;
    text-align: center;
}

.id-card-back-footer strong {
    font-size: 17px;
    font-weight: 950;
    letter-spacing: 0.08em;
}

.id-card-back-footer span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.25;
}

.id-card-overlay-enter-active,
.id-card-overlay-leave-active {
    transition:
        opacity 260ms ease,
        backdrop-filter 260ms ease;
}

.id-card-overlay-enter-from,
.id-card-overlay-leave-to {
    opacity: 0;
    backdrop-filter: blur(0);
}

.id-card-overlay-enter-active .id-card-stage {
    animation: idCardEnter 760ms cubic-bezier(0.2, 0.8, 0.2, 1) 80ms both;
}

.id-card-overlay-enter-active .id-card-spotlight {
    animation: idCardSpotlightIn 760ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

@keyframes idCardEnter {
    from {
        opacity: 0;
        transform: translateY(24px) scale(0.92);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes idCardSpotlightIn {
    from {
        opacity: 0;
        transform: translate(-50%, -30px) scale(0.88);
    }
    to {
        opacity: 0.9;
        transform: translate(-50%, 0) scale(1);
    }
}

.profile-photo-upload {
    padding: 24px 26px 0;
}

.upload-grid {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 18px;
}

.upload-dropzone {
    display: flex;
    min-height: 320px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    padding: 20px;
    border: 1px dashed var(--hcis-slate-400);
    border-radius: 14px;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    cursor: pointer;
    text-align: center;
}

.upload-dropzone:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--hcis-violet-50);
}

.upload-dropzone input {
    display: none;
}

.upload-dropzone i {
    font-size: 30px;
}

.upload-dropzone strong {
    color: var(--text);
    font-size: 14px;
}

.upload-dropzone span {
    font-size: 12px;
    font-weight: 650;
}

.upload-preview-panel {
    min-width: 0;
}

.upload-preview-suite {
    display: grid;
    min-height: 320px;
    grid-template-columns: 0.78fr 1.08fr;
    grid-template-rows: auto auto;
    gap: 12px;
}

.upload-preview {
    display: grid;
    min-height: 320px;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-slate-900);
}

.upload-preview--empty {
    background:
        radial-gradient(circle at 50% 24%, rgba(221, 178, 45, 0.14), transparent 30%),
        linear-gradient(180deg, #111827, var(--hcis-slate-900));
}

.upload-preview img {
    width: 100%;
    height: 100%;
    max-height: 520px;
    object-fit: contain;
}

.upload-preview-empty {
    display: grid;
    gap: 8px;
    place-items: center;
    color: var(--hcis-slate-400);
    font-size: 13px;
    font-weight: 700;
}

.upload-preview-empty i {
    font-size: 34px;
}

.upload-preview-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #e4dfd0;
    border-radius: 14px;
    background: #fffdf8;
    box-shadow:
        0 14px 26px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.upload-preview-card__header {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(228, 223, 208, 0.8);
    color: #564315;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(246, 239, 218, 0.58));
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.upload-preview-card__header i {
    color: #c19719;
    font-size: 14px;
}

.upload-preview-card--profile,
.upload-preview-card--sidebar {
    display: grid;
    align-content: start;
}

.upload-preview-card--id {
    grid-row: span 2;
}

.upload-profile-mock {
    display: grid;
    justify-items: center;
    gap: 10px;
    padding: 16px 12px 18px;
    text-align: center;
}

.upload-profile-avatar {
    position: relative;
    display: grid;
    width: 112px;
    aspect-ratio: 1;
    place-items: center;
    padding: 5px;
    overflow: hidden;
    border: 1px solid rgba(221, 178, 45, 0.54);
    border-radius: 24px;
    background:
        radial-gradient(circle at 28% 14%, rgba(255, 255, 255, 0.86), transparent 34%),
        linear-gradient(135deg, transparent 0 38%, rgba(207, 181, 105, 0.18) 38% 48%, transparent 48% 100%),
        linear-gradient(45deg, transparent 0 42%, rgba(218, 180, 49, 0.92) 42% 53%, transparent 53% 100%),
        linear-gradient(180deg, #fffdf7, #eee4c9);
    box-shadow:
        0 0 0 4px rgba(255, 255, 255, 0.74),
        0 12px 24px rgba(142, 106, 21, 0.16);
}

.upload-profile-avatar img,
.upload-sidebar-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.upload-profile-avatar img {
    border-radius: 18px;
}

.upload-profile-lines,
.upload-sidebar-lines,
.upload-id-card-info {
    min-width: 0;
}

.upload-profile-lines strong,
.upload-sidebar-lines strong,
.upload-id-card-info strong {
    display: block;
    overflow: hidden;
    color: #171717;
    font-size: 13px;
    font-weight: 950;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.upload-profile-lines span,
.upload-sidebar-lines span,
.upload-id-card-info span {
    display: block;
    overflow: hidden;
    margin-top: 3px;
    color: #6b7280;
    font-size: 11px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.upload-id-card-mock {
    position: relative;
    width: min(178px, 92%);
    aspect-ratio: 0.63;
    overflow: hidden;
    border: 1px solid rgba(116, 93, 35, 0.18);
    border-radius: 14px;
    background: linear-gradient(180deg, #fbfaf6, #f0eee6);
    box-shadow:
        0 16px 28px rgba(15, 23, 42, 0.14),
        inset 0 0 0 7px rgba(255, 255, 255, 0.28);
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.upload-id-card-preview-frame {
    display: grid;
    place-items: center;
    margin: 14px auto 16px;
}

.upload-id-card-preview-frame > * {
    grid-area: 1 / 1;
}

.upload-id-card-mock.is-loading {
    opacity: 0;
    pointer-events: none;
    transform: translateY(4px) scale(0.985);
}

.upload-id-card-skeleton {
    position: relative;
    width: min(178px, 92%);
    aspect-ratio: 0.63;
    overflow: hidden;
    border: 1px solid rgba(211, 184, 93, 0.32);
    border-radius: 14px;
    background:
        linear-gradient(110deg, transparent 0 30%, rgba(255, 255, 255, 0.72) 42%, transparent 56%),
        linear-gradient(180deg, #fbf7eb, #ebe4cf);
    background-size:
        220% 100%,
        100% 100%;
    box-shadow:
        0 16px 28px rgba(15, 23, 42, 0.1),
        inset 0 0 0 7px rgba(255, 255, 255, 0.32);
    animation: imageSkeletonSweep 1.05s ease-in-out infinite;
}

.upload-id-card-skeleton span {
    position: absolute;
    display: block;
    border-radius: 999px;
    background: rgba(206, 185, 121, 0.34);
}

.upload-id-card-skeleton__logo {
    top: 17px;
    left: 50%;
    width: 45px;
    height: 34px;
    border-radius: 10px;
    transform: translateX(-50%);
}

.upload-id-card-skeleton__chevron {
    left: 50%;
    width: 122%;
    clip-path: polygon(0 74%, 50% 0, 100% 74%, 100% 91%, 50% 17%, 0 91%);
    transform: translateX(-50%);
}

.upload-id-card-skeleton__chevron--soft {
    top: 78px;
    height: 116px;
    background: rgba(207, 181, 105, 0.18);
}

.upload-id-card-skeleton__chevron--gold {
    top: 126px;
    height: 126px;
    background: rgba(198, 157, 33, 0.32);
}

.upload-id-card-skeleton__photo {
    right: -16px;
    bottom: -3px;
    width: 74%;
    height: 65%;
    border-radius: 18px 18px 0 0;
    background: rgba(223, 213, 184, 0.76);
}

.upload-id-card-skeleton__name {
    left: 14px;
    bottom: 57px;
    width: 64px;
    height: 9px;
    background: rgba(96, 81, 42, 0.24);
}

.upload-id-card-skeleton__meta {
    left: 14px;
    bottom: 42px;
    width: 42px;
    height: 7px;
    background: rgba(96, 81, 42, 0.16);
}

.upload-id-card-logo {
    position: relative;
    z-index: 2;
    display: grid;
    justify-items: center;
    padding-top: 16px;
}

.upload-id-card-logo img {
    width: 45px;
    max-height: 36px;
    object-fit: contain;
}

.upload-id-card-chevron {
    position: absolute;
    left: 50%;
    width: 122%;
    clip-path: polygon(0 74%, 50% 0, 100% 74%, 100% 91%, 50% 17%, 0 91%);
    transform: translateX(-50%);
}

.upload-id-card-chevron--soft {
    top: 78px;
    height: 116px;
    background: linear-gradient(180deg, rgba(207, 181, 105, 0.18), rgba(196, 165, 78, 0.3));
}

.upload-id-card-chevron--gold {
    top: 126px;
    height: 126px;
    background: linear-gradient(180deg, rgba(204, 160, 31, 0.94), rgba(187, 143, 20, 0.9));
}

.upload-id-card-photo {
    position: absolute;
    right: -16px;
    bottom: -3px;
    z-index: 3;
    width: 74%;
    height: 65%;
    object-fit: contain;
    object-position: center bottom;
    filter: drop-shadow(-9px 13px 12px rgba(34, 28, 16, 0.22));
}

.upload-id-card-info {
    position: absolute;
    left: 14px;
    bottom: 44px;
    z-index: 4;
    max-width: 48%;
}

.upload-id-card-info strong {
    font-size: 10px;
    line-height: 1.08;
}

.upload-id-card-info span {
    color: #171717;
    font-size: 9px;
}

.upload-sidebar-mock {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 14px 12px 16px;
    padding: 10px;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 13px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.76));
}

.upload-sidebar-avatar {
    position: relative;
    display: grid;
    width: 46px;
    height: 46px;
    flex: 0 0 auto;
    place-items: center;
    padding: 3px;
    overflow: hidden;
    border: 1px solid rgba(221, 178, 45, 0.54);
    border-radius: 15px;
    background: #fffdf7;
    box-shadow:
        0 0 0 2px rgba(255, 255, 255, 0.68),
        0 9px 18px rgba(142, 106, 21, 0.16);
}

.upload-sidebar-avatar img {
    border-radius: 11px;
}

.upload-sidebar-lines strong,
.upload-sidebar-lines span {
    text-transform: none;
}

.placement-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-slate-50);
}

.placement-panel span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.placement-panel strong {
    display: block;
    margin-top: 2px;
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
}

.placement-options {
    display: inline-flex;
    gap: 6px;
    padding: 4px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
}

.placement-option-btn {
    appearance: none;
    min-height: 32px;
    padding: 6px 11px;
    border: 1px solid transparent;
    border-radius: 9px;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    cursor: pointer;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
    transition:
        background-color 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease,
        color 180ms ease;
}

.placement-option-btn:hover {
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
    background: #eef4ff;
}

.placement-option-btn.active {
    border-color: var(--hcis-blue-600);
    color: #fff;
    background: var(--hcis-blue-600);
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2);
}

.profile-render-canvas {
    display: none;
}

:global(.reset-password-modal-body) {
    padding: 0;
    background:
        radial-gradient(circle at 18% 0%, rgba(124, 58, 237, 0.13), transparent 31%),
        radial-gradient(circle at 88% 14%, rgba(37, 99, 235, 0.11), transparent 28%),
        linear-gradient(180deg, #f8f7ff, var(--hcis-slate-50) 64%, var(--hcis-white));
}

:global(.modal-container:has(.reset-password-modal-body) .modal-footer) {
    border-top: 1px solid rgba(226, 232, 240, 0.86);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.82), rgba(239, 246, 255, 0.92));
}

.reset-password-custom-header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    overflow: hidden;
    padding: 24px 26px 22px;
    color: #fff;
    background:
        radial-gradient(circle at 90% -18%, rgba(255, 255, 255, 0.22), transparent 32%),
        radial-gradient(circle at 12% 120%, rgba(255, 255, 255, 0.16), transparent 34%),
        linear-gradient(135deg, var(--hcis-violet-600), var(--hcis-blue-600));
}

.reset-password-custom-header::after {
    position: absolute;
    right: -44px;
    bottom: -72px;
    width: 180px;
    height: 180px;
    border: 24px solid rgba(255, 255, 255, 0.09);
    border-radius: 50%;
    content: '';
}

.reset-password-custom-header h3 {
    position: relative;
    z-index: 1;
    margin: 0;
    color: #fff;
    font-size: 22px;
    font-weight: 900;
    line-height: 1.2;
}

.reset-password-custom-header p {
    position: relative;
    z-index: 1;
    margin: 8px 0 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: 13px;
    font-weight: 750;
}

.reset-password-custom-header button {
    position: relative;
    z-index: 1;
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 13px;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition:
        background 0.18s ease,
        transform 0.18s ease;
}

.reset-password-custom-header button:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
}

.reset-password-custom-header button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.reset-password-panel {
    display: grid;
    gap: 18px;
    padding: 22px 22px 8px;
}

.reset-password-hero {
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    overflow: hidden;
    padding: 16px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 16px;
    background:
        radial-gradient(circle at 92% 0%, rgba(255, 255, 255, 0.2), transparent 30%),
        linear-gradient(135deg, var(--hcis-violet-600), var(--hcis-blue-600));
    box-shadow:
        0 18px 38px rgba(79, 70, 229, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.22);
}

.reset-password-hero::after {
    position: absolute;
    right: -34px;
    bottom: -48px;
    width: 160px;
    height: 160px;
    border: 22px solid rgba(255, 255, 255, 0.12);
    border-radius: 50%;
    content: '';
}

.reset-password-orbit {
    position: relative;
    display: grid;
    width: 54px;
    height: 54px;
    place-items: center;
    color: #fff;
    border-radius: 18px;
    background:
        radial-gradient(circle at 32% 22%, rgba(255, 255, 255, 0.36), transparent 35%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.08));
    box-shadow:
        0 14px 24px rgba(15, 23, 42, 0.2),
        inset 0 0 0 1px rgba(255, 255, 255, 0.24);
    backdrop-filter: blur(10px);
}

.reset-password-orbit span {
    position: absolute;
    inset: -6px;
    border: 1px solid rgba(255, 255, 255, 0.34);
    border-radius: 22px;
    animation: resetPasswordOrbit 2.8s ease-in-out infinite;
}

.reset-password-orbit i {
    position: relative;
    z-index: 1;
    font-size: 24px;
}

.reset-password-hero strong {
    display: block;
    color: #fff;
    font-size: 15px;
    font-weight: 950;
}

.reset-password-hero span {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 12px;
    font-weight: 750;
    line-height: 1.45;
}

.reset-password-fields {
    display: grid;
    gap: 14px;
}

.password-field {
    display: grid;
    gap: 7px;
    min-width: 0;
}

.password-field > span {
    color: #1f2937;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.02em;
}

.pw-wrap {
    position: relative;
    display: flex;
    align-items: center;
    border: 1px solid #dbe4f0;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow:
        0 10px 20px rgba(15, 23, 42, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.86);
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.pw-wrap input {
    width: 100%;
    min-height: 46px;
    padding: 11px 44px 11px 42px;
    border: 0;
    color: #111827;
    background: transparent;
    font-size: 14px;
    font-weight: 750;
}

.pw-wrap input::placeholder {
    color: #7b8494;
    opacity: 1;
    font-weight: 750;
}

.pw-wrap input:focus {
    outline: 0;
}

.pw-wrap:focus-within {
    border-color: rgba(124, 58, 237, 0.68);
    box-shadow:
        0 0 0 4px rgba(124, 58, 237, 0.12),
        0 14px 26px rgba(79, 70, 229, 0.1);
    transform: translateY(-1px);
}

.pw-wrap.has-error {
    border-color: rgba(239, 68, 68, 0.72);
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
}

.pw-leading-icon {
    position: absolute;
    top: 50%;
    left: 15px;
    display: grid !important;
    width: 20px;
    height: 20px;
    place-items: center;
    color: var(--hcis-violet-600);
    font-size: 15px;
    line-height: 1;
    transform: translateY(-50%);
    pointer-events: none;
}

.pw-eye {
    position: absolute;
    right: 10px;
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    border: 0;
    border-radius: 10px;
    color: var(--hcis-slate-500);
    background: rgba(248, 250, 252, 0.86);
    cursor: pointer;
    transition:
        color 0.18s ease,
        background 0.18s ease,
        transform 0.18s ease;
}

.pw-eye:hover {
    color: var(--hcis-indigo-600);
    background: rgba(239, 246, 255, 0.96);
    transform: translateY(-1px);
}

.pw-strength-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    margin-top: 2px;
}

.pw-strength {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: #e7e5e4;
}

.pw-strength-shell strong {
    min-width: 72px;
    padding: 3px 8px;
    border-radius: 999px;
    text-align: right;
    font-size: 11px;
    font-weight: 950;
    line-height: 1.2;
    white-space: nowrap;
}

.pw-bar {
    height: 100%;
    border-radius: 10px;
    transition:
        width 0.28s ease,
        background 0.28s ease;
}

.pw-bar.strength-weak {
    width: 33.33%;
    background: var(--hcis-red-500);
}

.pw-bar.strength-medium {
    width: 66.66%;
    background: var(--hcis-amber-500);
}

.pw-bar.strength-strong {
    width: 100%;
    background: #22c55e;
}

.pw-strength-shell strong.strength-weak {
    color: var(--hcis-red-700);
    background: rgba(254, 226, 226, 0.88);
}

.pw-strength-shell strong.strength-medium {
    color: var(--hcis-amber-800);
    background: rgba(254, 243, 199, 0.9);
}

.pw-strength-shell strong.strength-strong {
    color: #166534;
    background: rgba(220, 252, 231, 0.9);
}

.pw-reqs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.pw-reqs span {
    display: inline-flex;
    align-items: center;
    flex: 0 1 auto;
    gap: 5px;
    min-height: 30px;
    padding: 6px 10px;
    border: 1px solid #dbe4f0;
    border-radius: 999px;
    color: var(--hcis-slate-600);
    background: rgba(255, 255, 255, 0.78);
    font-size: 11px;
    font-weight: 850;
    line-height: 1.1;
    white-space: nowrap;
    transition:
        border-color 0.18s ease,
        color 0.18s ease,
        background 0.18s ease,
        transform 0.18s ease;
}

.pw-reqs span.met {
    border-color: rgba(124, 58, 237, 0.22);
    color: var(--hcis-indigo-600);
    background: rgba(239, 246, 255, 0.86);
    transform: translateY(-1px);
}

.pw-reqs i {
    display: grid;
    width: 14px;
    height: 14px;
    flex: 0 0 auto;
    place-items: center;
    font-size: 14px;
    line-height: 1;
}

.pw-mismatch {
    margin: 0;
    color: var(--hcis-red-500);
    font-size: 12px;
    font-weight: 800;
}

.reset-password-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    width: 100%;
}

.reset-password-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 10px 16px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 900;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        opacity 0.18s ease;
}

.reset-password-btn:not(:disabled):hover {
    transform: translateY(-1px);
}

.reset-password-btn--ghost {
    border: 1px solid #dbe4f0;
    color: var(--hcis-slate-500);
    background: #fff;
}

.reset-password-btn--primary {
    border: 1px solid rgba(124, 58, 237, 0.48);
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-violet-600), var(--hcis-blue-600));
    box-shadow: 0 12px 24px rgba(79, 70, 229, 0.18);
}

.reset-password-btn:disabled {
    cursor: not-allowed;
    opacity: 0.74;
}

.reset-password-btn--primary:disabled {
    color: #fff;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.64), rgba(37, 99, 235, 0.64));
    box-shadow: none;
}

.spin {
    display: inline-block;
    animation: spin 1s linear infinite;
}

.image-skeleton {
    position: absolute;
    inset: 0;
    z-index: 4;
    overflow: hidden;
    border-radius: inherit;
    background:
        linear-gradient(110deg, transparent 0 30%, rgba(255, 255, 255, 0.62) 42%, transparent 56%),
        linear-gradient(180deg, rgba(231, 218, 182, 0.46), rgba(248, 250, 252, 0.72));
    background-size:
        220% 100%,
        100% 100%;
    animation: imageSkeletonSweep 1.05s ease-in-out infinite;
    pointer-events: none;
}

.image-skeleton--avatar,
.image-skeleton--fill {
    inset: 4px;
}

.image-skeleton--id-photo {
    inset: auto -16px -3px auto;
    width: 74%;
    height: 65%;
    border-radius: 18px 18px 0 0;
}

@keyframes imageSkeletonSweep {
    from {
        background-position:
            140% 0,
            0 0;
    }
    to {
        background-position:
            -80% 0,
            0 0;
    }
}

@keyframes resetPasswordOrbit {
    0%,
    100% {
        opacity: 0.72;
        transform: rotate(0deg) scale(1);
    }
    50% {
        opacity: 1;
        transform: rotate(7deg) scale(1.06);
    }
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1024px) {
    .info-layout {
        grid-template-columns: 1fr;
    }

    .info-sidebar {
        position: static;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .info-sidebar .profile-card:first-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 820px) {
    .profile-page {
        padding: 14px;
    }

    .hero-top,
    .list-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .hero-actions,
    .toolbar-actions,
    .search-box,
    .search-box input,
    .filter-select {
        width: 100%;
    }

    .hero-actions {
        flex-direction: row;
    }

    .hero-btn {
        flex: 1;
        justify-content: center;
    }

    .hero-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .hero-stat:nth-child(2) {
        border-right: 0;
    }

    .hero-stat:nth-child(-n + 2) {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-tabs {
        overflow-x: auto;
    }

    .tab-btn {
        flex: 1;
        white-space: nowrap;
    }

    .field-grid,
    .info-sidebar,
    .bpjs-grid {
        grid-template-columns: 1fr;
    }

    .profile-photo-identity,
    .upload-grid {
        grid-template-columns: 1fr;
    }

    .profile-photo-identity {
        justify-items: center;
        padding: 0 18px;
        text-align: center;
    }

    .profile-photo-name-tag {
        width: 100%;
        margin-bottom: 0;
    }

    .profile-photo-actions,
    .placement-panel {
        flex-direction: column;
    }

    .id-card-fullscreen {
        padding: 24px 14px;
    }

    .id-card-stage {
        width: min(360px, 78vw);
    }

    .id-card-close-btn {
        top: 16px;
        right: 16px;
    }

    .profile-photo-actions,
    .placement-options {
        width: 100%;
    }

    .profile-photo-btn,
    .placement-options button {
        flex: 1;
    }
}

@media (max-width: 560px) {
    .hero-content {
        padding: 22px 18px 0;
    }

    .hero-top {
        gap: 14px;
    }

    .hero-avatar {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        font-size: 22px;
    }

    .hero-actions {
        flex-direction: column;
    }

    .hero-stats {
        grid-template-columns: 1fr;
    }

    .hero-stat,
    .hero-stat:nth-child(2) {
        border-right: 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .hero-stat:last-child {
        border-bottom: 0;
    }

    .card-header,
    .membership-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .bpjs-digital-card {
        padding: 18px 16px;
    }

    .bpjs-number-display {
        font-size: 14px;
        letter-spacing: 0.06em;
    }

    .bpjs-program-tags {
        display: none;
    }

    .membership-phone,
    .tone-badge {
        align-self: flex-start;
    }

    .approval-flow {
        align-items: flex-start;
        flex-direction: column;
    }

    .approval-arrow {
        transform: rotate(90deg);
        margin-left: 12px;
    }

    .reset-password-footer {
        flex-direction: column-reverse;
    }

    .reset-password-btn {
        width: 100%;
    }

    .profile-photo-banner {
        height: 150px;
    }

    .profile-photo-frame {
        width: 190px;
        font-size: 42px;
    }

    .profile-photo-frame.is-clickable::after {
        right: 10px;
        bottom: 10px;
        width: 30px;
        height: 30px;
        font-size: 14px;
    }

    .id-card-stage {
        width: min(315px, 82vw);
    }

    .id-card-face {
        border-radius: 18px;
    }

    .id-card-face::before {
        border-width: 8px;
    }

    .id-card-front-header img {
        width: 70px;
    }

    .id-card-portrait {
        right: -28px;
        bottom: -4px;
        width: 72%;
        height: 64%;
    }

    .id-card-front-info h3 {
        font-size: 18px;
    }

    .id-card-front-info strong {
        font-size: 15px;
    }

    .id-card-front-info {
        left: 20px;
        bottom: 92px;
        max-width: 40%;
    }

    .id-card-qr {
        left: 20px;
        bottom: 32px;
        width: 60px;
        height: 60px;
        padding: 3px;
    }

    .id-card-chevron {
        width: 120%;
        height: 208px;
    }

    .id-card-chevron--back {
        top: 126px;
    }

    .id-card-chevron--front {
        top: 204px;
        height: 228px;
    }

    .id-card-back-header h3 {
        font-size: 11px;
    }

    .id-card-terms {
        gap: 8px;
        padding-inline: 10px;
    }

    .id-card-terms p {
        font-size: 10px;
        line-height: 1.28;
    }

    .id-card-back-footer {
        padding: 9px 14px 11px;
    }

    .id-card-back-footer strong {
        font-size: 14px;
    }

    .id-card-back-footer span {
        font-size: 9px;
    }

    .profile-photo-upload {
        padding: 18px 18px 0;
    }

    .upload-dropzone,
    .upload-preview {
        min-height: 240px;
    }
}

/* ============================================================
 * Activity feed (sidebar "Aktivitas Terbaru")
 * ============================================================ */
.activity-feed {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.activity-feed__item {
    display: grid;
    grid-template-columns: 30px 1fr auto;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 8px;
    border-radius: 10px;
    transition: background-color 0.15s ease;
    border-left: 3px solid transparent;
}

.activity-feed__item:hover {
    background: rgba(15, 23, 42, 0.03);
}

.activity-feed__item.is-ok {
    border-left-color: var(--hcis-emerald-500);
}

.activity-feed__item.is-warn {
    border-left-color: var(--hcis-red-500);
    background: rgba(239, 68, 68, 0.04);
}

.activity-feed__item.is-muted {
    border-left-color: var(--hcis-slate-400);
}

.activity-feed__icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 14px;
    flex-shrink: 0;
}

.activity-feed__item.is-ok .activity-feed__icon {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-700);
}

.activity-feed__item.is-warn .activity-feed__icon {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.activity-feed__item.is-muted .activity-feed__icon {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}

.activity-feed__body {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.activity-feed__title {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.activity-feed__body strong {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-slate-900);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.activity-feed__chip {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    line-height: 1.2;
    flex-shrink: 0;
}

.activity-feed__chip.is-ok {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-700);
}

.activity-feed__chip.is-warn {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.activity-feed__chip.is-muted {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}

.activity-feed__meta {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.activity-feed__path {
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
    font-size: 0.7rem;
    color: #7c8698;
}

.activity-feed__time {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    padding-top: 2px;
}

.activity-feed__footer {
    margin-top: 10px;
    text-align: center;
}

.activity-feed__footer .soft-btn {
    width: 100%;
    justify-content: center;
}

/* Skeleton loader */
.activity-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.activity-skeleton__row {
    display: grid;
    grid-template-columns: 30px 1fr;
    gap: 10px;
    align-items: center;
}

.activity-skeleton__dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(90deg, var(--hcis-slate-200) 0%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 100%);
    background-size: 200% 100%;
    animation: activity-shimmer 1.4s ease-in-out infinite;
}

.activity-skeleton__line {
    display: block;
    height: 10px;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--hcis-slate-200) 0%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 100%);
    background-size: 200% 100%;
    animation: activity-shimmer 1.4s ease-in-out infinite;
    margin-bottom: 6px;
}

.activity-skeleton__line--title {
    width: 70%;
}

.activity-skeleton__line--meta {
    width: 45%;
    height: 8px;
}

@keyframes activity-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* ==========================================================================
   Ultra-Responsive Mobile Refinements (<= 480px & <= 380px / e.g. 368x560)
   ========================================================================== */
@media (max-width: 480px) {
    .profile-page {
        padding: 10px 10px 32px;
    }

    .profile-hero {
        border-radius: 16px;
        margin-bottom: 14px;
    }

    .hero-content {
        padding: 16px 14px 0;
    }

    .hero-name-row h1 {
        font-size: 20px;
    }

    .hero-stat {
        padding: 12px 14px;
    }

    .profile-tabs {
        margin-bottom: 14px;
        padding: 3px;
        border-radius: 12px;
        gap: 4px;
    }

    .tab-btn {
        min-height: 34px;
        padding: 6px 10px;
        font-size: 12px;
        border-radius: 8px;
    }

    .profile-card {
        border-radius: 14px;
    }

    .card-header {
        padding: 12px 14px;
        gap: 8px;
    }

    .card-title-group {
        gap: 10px;
    }

    .card-title-group h2 {
        font-size: 15px;
    }

    .card-icon {
        width: 32px;
        height: 32px;
        font-size: 15px;
        border-radius: 8px;
    }

    .bpjs-header-badges {
        margin-top: 4px;
        flex-wrap: wrap;
        gap: 6px;
    }

    .card-body {
        padding: 4px 14px 14px;
    }

    /* BPJS Row Table Mobile UI/UX */
    .bpjs-item-row {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 12px 2px;
    }

    .bpjs-brand-cell {
        min-width: 0;
    }

    .bpjs-brand-logo {
        height: 42px;
        max-width: 165px;
    }

    .bpjs-details-cell {
        gap: 6px;
    }

    .bpjs-detail-line {
        font-size: 13px;
    }

    .bpjs-detail-label {
        width: 120px;
        font-size: 12.5px;
    }

    .bpjs-detail-colon {
        margin-right: 10px;
    }

    .bpjs-detail-val {
        font-size: 13.5px;
    }
}

@media (max-width: 380px) {
    .profile-page {
        padding: 8px 8px 28px;
    }

    .hero-name-row h1 {
        font-size: 18px;
    }

    .hero-role {
        font-size: 13.5px;
    }

    .hero-stat strong {
        font-size: 14px;
    }

    .card-header {
        padding: 10px 12px;
    }

    .card-body {
        padding: 2px 12px 12px;
    }

    .bpjs-detail-label {
        width: 105px;
        font-size: 12px;
    }

    .bpjs-detail-colon {
        margin-right: 6px;
    }

    .bpjs-detail-val {
        font-size: 12.5px;
    }

    .bpjs-status-text {
        font-size: 12.5px;
    }
}
</style>
