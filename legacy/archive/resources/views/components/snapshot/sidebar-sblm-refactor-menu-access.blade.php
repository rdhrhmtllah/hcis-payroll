@php
$currentRoute = Route::currentRouteName();
$currentPath = Request::path();

// Custom check for Cross Review group
$isCrossActive =
$currentRoute === 'Cross.index' ||
Request::is('crossReviewPeriode') ||
Request::is('crossReviewSummary') ||
Request::is('historyCrossReview') ||
Request::is('crossFeedbackReview');

$isDashboard = Request::is('uDash');
@endphp

<style>
    @keyframes pulseGlowRed {
        0% {
            opacity: 0.9;
            box-shadow: 0 0 8px rgba(255, 0, 0, 0.4), inset 0 0 8px rgba(255, 0, 0, 0.2);
        }
        50% {
            opacity: 1;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.8), inset 0 0 15px rgba(255, 0, 0, 0.3);
        }
        100% {
            opacity: 0.9;
            box-shadow: 0 0 8px rgba(255, 0, 0, 0.4), inset 0 0 8px rgba(255, 0, 0, 0.2);
        }
    }

    @keyframes blinkStrong {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.6;
            transform: scale(1.02);
        }
    }

    .database-mention {
        animation: pulseGlowRed 1.8s ease-in-out infinite;
        font-weight: 700;
        color: #ffffff;
        background-color: #ff3333;
        letter-spacing: 1px;
        padding: 6px 8px;
        border-radius: 4px;
        display: inline-block;
        border: 2px solid #cc0000;
    }

    .database-name {
        animation: blinkStrong 1.2s ease-in-out infinite;
        font-weight: 800;
        color: #ffffff;
        background-color: #d32f2f;
        padding: 0px 3px;
        border-radius: 3px;
        margin-left: 1px;
        display: inline-block;
    }
</style>

@php


$isAdmin = Request::is('izinAdmin','admin/cuti', 'role-manager','page-access' ,'leader-shift/admin', 'lembur-management/admin', 'add-karyawan-team', 'perbaiki-checkinout', 'admin/kuota-management', 'approval-flow-shift', 'leader-shift/admin/shift-rule', 'admin/cuti-bersama', 'admin/cuti');
$rolesAdmin = ['IzinPageAdmin', 'adminAllPagesAccess','superAdminManajemenPeranPage' ,'adminHCCutiManagementPage' ,'OvertimeManagementAdmin', 'ShiftManagementAdmin', 'addKaryawanTeam', 'perbaikanCheckinOut', 'adminHCKuotaManagementPage', 'approvalFlowShift', 'adminHCCutiBersamaPage'];
$hasAccessAdmin = array_filter($rolesAdmin, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessAdmin = !empty($hasAccessAdmin);

$isPerson = Request::is('izin', 'absensi', 'cuti', 'absensiSales', 'self-shift','konversi-lembur');
$rolesPerson = ['IzinPage', 'absensiPage', 'cutiPage', 'selfShift', 'konversiLembur' ];
$hasAccessPerson = array_filter($rolesPerson, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessPerson = !empty($hasAccessPerson);

$isTeam = Request::is('izinLevelUp', 'leader-shift', 'lembur-management', 'shift-approval');
$rolesTeam = ['OvertimeManagement', 'IzinPageApprover', 'ShiftManagement', 'shiftApproval'];
$hasAccessTeam = array_filter($rolesTeam, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessTeam = !empty($hasAccessTeam);

// KPI ACESS
$kpiMenu =  Request::is('dashboard-my-performance','dashboard-performance-all');
$rolesAccessPerformance = [ 'dashboardStaff','dashboardCeo'];
$hasAccessPerformance = array_filter($rolesAccessPerformance, fn($role) => auth()->user()->can('akses-spesial', $role));

// Performance Menus
$performanceMenu =  Request::is( 'form-kpi', 'update-performance');
$rolesperformanceMenu = ['FormKPIPage', 'UpdatePerformancePage'];
$hasperformanceMenu = array_filter($rolesperformanceMenu, fn($role) => auth()->user()->can('akses-spesial', $role));

// Manajemen KPI
$manajemenKpiMenu =  Request::is('performance-approval','approval-kpi-admin' ,'adminKPIKaryawanTeamPage','kpi-approval','form-kpi/team', 'update-performance/team');
$rolesmanajemenKpiMenu = ['ApprovalUpdatePerformance','adminHCApprovalKPIPage', 'manajemen-karyawan-tim-kpi','ApprovalParameterKPI', 'FormKpiTeam', 'UpdatePerformanceTeam'];
$hasmanajemenKpiMenu = array_filter($rolesmanajemenKpiMenu, fn($role) => auth()->user()->can('akses-spesial', $role));

// Administrator KPI ACESS
$administratorKpiMenu =  Request::is('satuan', 'performance-kpi','target-kpi', 'bsc-kategori', 'formula-kpi', 'satuan-formula', 'penjadwalan-kpi', 'akses-user/level', 'akses-user/divisi');
$rolesadministratorKpiMenu = ['MasterSatuan', 'MasterPerformance', 'MasterTarget', 'MasterBscKategori', 'MasterFormula', 'MasterSatuanFormula', 'MasterPenjadwalan', 'ManajemenAksesLevel', 'ManajemenAksesDivisi'];
$hasadministratorKpiMenu = array_filter($rolesadministratorKpiMenu, fn($role) => auth()->user()->can('akses-spesial', $role));

// LMS MENU (User Biasa)
$lmsMenu =  Request::is('lms/training*');
$rolesLmsMenu = ['LMSTrainingCatalogPage'];
$hasLmsMenu = array_filter($rolesLmsMenu, fn($role) => auth()->user()->can('akses-spesial', $role));

// Administrator LMS ACESS
$administratorLmsMenu =  Request::is('lms/admin/*', 'lms/penjadwalan', 'lms/training-kategori', 'lms/konten-kategori');
$rolesadministratorLmsMenu = ['LMSContentManagementPage', 'LMSPenjadwalanPage', 'LMSTrainingKategoriPage', 'LMSAdminMonitoringPage', 'LMSAdminTrainingPage'];
$hasadministratorLmsMenu = array_filter($rolesadministratorLmsMenu, fn($role) => auth()->user()->can('akses-spesial', $role));

// Custom check for Ticket group
$isTicketActive =
Request::is('displayTicket') ||
Request::is('takeTicket') ||
Request::is('finishTicket');
@endphp

<div id="sidebar">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative mt-4">
            <div class="d-flex justify-content-center align-items-center rounded mb-3">
              <div class="logo">
                    <img src="/logo/logo-gourp.jpg" style="width: 190px; height:auto" alt="Logo" onerror="this.style.display='none'" />
                    <!-- Alternatif: Gunakan div untuk shimmer jika bentuk logo kompleks -->
                    <!-- <div class="logo-shimmer"></div> -->
                </div>

            </div>
            <div class="my-0 py-0 d-flex flex-column mt-3">
                <span class="  fw-bold px-2 py-0" style="font-size: 18px"><i class="me-2 bi bi-person-fill"></i>
                    <span class="text-uppercase">
                        {{Auth::user()->Username}} 
                        
                        
                    </span>
                </span>
            <span style="font-size: 0.9rem; font-weight: 400;" class=" px-2 fw-0 my-0"> <span class=""></span> {{Auth::user()->divisionKaryawan->nama_sub_divisi}}</span>
            @if(env('APP_ENV') === 'local' && request()->getHost() != 'hcis.evonusabersaudara.co.id')
            <span class="px-2 fw-0 my-0 database-mention" style="font-size: 0.8rem; display:inline-block  margin-top: 8px;">
                📊 Database used : 
                    <span class="database-name">{{DB::connection()->getDatabaseName()}}</span>
            </span>
            @endif    
            </div>
        </div>

        {{-- <div class="theme-toggle d-flex gap-2 align-items-center mb-4 float-end px-4">
            <i class="bi bi-sun"></i>
            <div class="form-check form-switch fs-6">
                <input class="form-check-input me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
                <label class="form-check-label"></label>
            </div>
            <i class="bi bi-moon"></i>
        </div> --}}

        <div class="sidebar-menu mx-0">
            <ul class="menu">
                <li class="sidebar-title">Menu</li>

                {{-- <li class="sidebar-item {{ $currentRoute === 'superUser' ? 'active' : '' }}">
                    <a href="{{ route('superUser') }}" class="sidebar-link">
                        <i class="bi bi-house-door"></i>
                        <span>Dashboard</span>
                    </a>
                </li> --}}
                {{-- <li class="sidebar-item {{ $isDashboard ? 'active' : '' }}">
                    <a href="/uDash" class="sidebar-link">
                        <i class="bi bi-house-door"></i>
                        <span>Dashboard</span>
                    </a>
                </li> --}}

                <li class="sidebar-item has-sub {{ $kpiMenu ? 'active' : '' }}">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-house-door"></i>
                        <span>Dashboard</span>
                    </a>
                    <ul class="submenu px-0 {{ $kpiMenu ? 'd-block' : '' }}">
                        <li class="submenu-item {{ Request::is('uDash') ? 'active' : '' }}">
                            <a href="/uDash" class="submenu-link">
                                <i class="bi bi-bar-chart-line"></i> Kehadiran
                            </a>
                        </li>
                        @can('akses-spesial', 'dashboardStaff')
                            <li class="submenu-item {{ Request::is('dashboard-my-performance') ? 'active' : '' }}">
                                <a href="/dashboard-my-performance" class="submenu-link">
                                    <i class="bi bi-trophy"></i> KPI Performance Individu
                                </a>
                            </li>
                        @endCan
                        @can('akses-spesial', 'dashboardCeo')
                            <li class="submenu-item {{ Request::is('dashboard-performance-all') ? 'active' : '' }}">
                                <a href="/dashboard-performance-all" class="submenu-link">
                                    <i class="bi bi-trophy"></i> KPI Performance Tim
                                </a>
                            </li>
                        @endCan
                    </ul>
                </li>

                @if($hasAccessPerson)
                    <li class="sidebar-item has-sub {{ $isPerson ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-person-fill-gear"></i>
                            <span>Kehadiran</span>
                        </a>
                        <ul class="submenu px-0 {{ $isPerson ? 'd-block' : '' }}">
                            @can('akses-spesial', 'absensiPage')

                                <li class="submenu-item {{ Request::is('absensiSales') ? 'active' : '' }}">
                                    <a href="/absensiSales" class="submenu-link">
                                        <i class="bi bi-person-badge"></i> Absensi
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'IzinPage')

                                <li class="submenu-item {{ Request::is('izin') ? 'active' : '' }}">
                                    <a href="/izin" class="submenu-link">
                                        <i class="bi bi-mailbox-flag"></i> Izin
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'cutiPage')

                                <li class="submenu-item {{ Request::is('cuti') ? 'active' : '' }}">
                                    <a href="/cuti" class="submenu-link">
                                        <i class="bi bi-calendar-event"></i> Cuti
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'selfShift')

                                <li class="submenu-item {{ Request::is('selfShift') ? 'active' : '' }}">
                                    <a href="/self-shift" class="submenu-link">
                                        <i class="bi bi-clock"></i> Shift
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'konversiLembur')

                                <li class="submenu-item {{ Request::is('konversi-lembur') ? 'active' : '' }}">
                                    <a href="/konversi-lembur" class="submenu-link">
                                        <i class="bi bi-currency-exchange"></i> Konversi Lembur
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endif

                @if($hasperformanceMenu)
                    <li class="sidebar-item has-sub {{ $performanceMenu ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>KPI</span>
                        </a>
                        <ul class="submenu px-0 {{ $performanceMenu ? 'd-block' : '' }}">
                            @can('akses-spesial', 'FormKPIPage')
                                <li class="submenu-item {{ Request::is('form-kpi') ? 'active' : '' }}">
                                    <a href="/form-kpi" class="submenu-link">
                                        <i class="bi bi-journal-plus"></i> Pengajuan KPI
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'UpdatePerformancePage')
                                <li class="submenu-item {{ Request::is('update-performance') ? 'active' : '' }}">
                                    <a href="/update-performance" class="submenu-link">
                                        <i class="bi bi-pencil-square"></i> Update KPI
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif

                
                <!-- LMS Menu (User Biasa) -->
                @if($hasLmsMenu)
                    <li class="sidebar-item has-sub {{ $lmsMenu ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-book"></i>
                            <span>LMS</span>
                        </a>
                        <ul class="submenu px-0 {{ $lmsMenu ? 'd-block' : '' }}">
                            @can('akses-spesial', 'LMSTrainingCatalogPage')
                                <li class="submenu-item {{ Request::is('lms/training/catalog', 'lms/training/*') ? 'active' : '' }}">
                                    <a href="/lms/training/catalog" class="submenu-link">
                                        <i class="bi bi-list-task"></i> Training Catalog
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif


                @if($hasmanajemenKpiMenu)
                    <li class="sidebar-item has-sub {{ $manajemenKpiMenu ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-people-fill"></i>
                            <span>Manajemen KPI</span>
                        </a>
                        <ul class="submenu px-0 {{ $manajemenKpiMenu ? 'd-block' : '' }}">
                            @can('akses-spesial', 'FormKpiTeam')
                                <li class="submenu-item {{ Request::is('form-kpi/team') ? 'active' : '' }}">
                                    <a href="/form-kpi/team" class="submenu-link">
                                        <i class="bi bi-clipboard-data"></i> Pengajuan KPI Team
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'ApprovalParameterKPI')
                                <li class="submenu-item {{ Request::is('kpi-approval') ? 'active' : '' }}">
                                    <a href="/kpi-approval" class="submenu-link">
                                        <i class="bi bi-check2-square"></i> Approval Parameter KPI
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'UpdatePerformanceTeam')
                                <li class="submenu-item {{ Request::is('update-performance/team') ? 'active' : '' }}">
                                    <a href="/update-performance/team" class="submenu-link">
                                        <i class="bi bi-bar-chart-line-fill"></i> Update KPI Team
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial','ApprovalUpdatePerformance')
                                <li class="submenu-item {{ Request::is('performance-approval') ? 'active' : '' }}">
                                    <a href="/performance-approval" class="submenu-link">
                                        <i class="bi bi-award-fill"></i> Approval Update KPI
                                    </a>
                                </li>
                            @endcan
                        </ul>

                    </li>
                @endif

                @if($hasAccessTeam)
                    <li class="sidebar-item has-sub {{ $isTeam ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-person-video2"></i>
                            <span>Manajemen HCIS</span>
                        </a>
                        <ul class="submenu px-0 {{ $isTeam ? 'd-block' : '' }}">

                            @can('akses-spesial', 'IzinPageApprover')

                                <li class="submenu-item {{ Request::is('izinLevelUp') ? 'active' : '' }}">
                                    <a href="/izinLevelUp" class="submenu-link">
                                        <i class="bi bi-check-circle"></i> Pengajuan Izin
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial','OvertimeManagement')

                                    <li class="submenu-item {{ Request::is('lembur-management') ? 'active' : '' }}">
                                        <a href="/lembur-management" class="submenu-link">
                                            <i class="bi bi-stack-overflow"></i> Penugasan Lembur
                                        </a>
                                    </li>
                                @endcan

                                @can('akses-spesial','ShiftManagement')
                                <li class="submenu-item {{ Request::is('leader-shift') ? 'active' : '' }}">
                                    <!-- <a href="/swapShift" class="submenu-link"> -->
                                    <a href="/leader-shift" class="submenu-link">
                                        <i class="bi bi-shuffle"></i> Pergantian Shift
                                    </a>
                                </li>
                               @endcan
                                @can('akses-spesial','shiftApproval')
                                <li class="submenu-item {{ Request::is('shiftApproval') ? 'active' : '' }}">
                                    <a href="/shift-approval" class="submenu-link">
                                        <i class="bi bi-check2-square"></i> Pengajuan Pergantian Shift & Lembur
                                    </a>
                                </li>
                               @endcan
                        </ul>
                    </li>
                @endif

                @if($hasAccessAdmin)
                    <li class="sidebar-item has-sub {{ $isAdmin ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-gear-fill"></i>
                            <span>Administrator HCIS</span>
                        </a>
                        <ul class="submenu px-0 {{ $isAdmin ? 'd-block' : '' }}">

                            @can('akses-spesial', 'adminHCApprovalKPIPage')

                                <li class="submenu-item {{ Request::is('approval-kpi-admin') ? 'active' : '' }}">
                                    <a href="/approval-kpi-admin" class="submenu-link">
                                        <i class="bi bi-diagram-3"></i> Approval Flow Setup
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'adminAllPagesAccess')

                                <li class="submenu-item {{ Request::is('page-access') ? 'active' : '' }}">
                                    <a href="/page-access" class="submenu-link">
                                        <i class="bi bi-shield-check"></i> Page Access Setup
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'adminKPIKaryawanTeamPage')

                                <li class="submenu-item {{ Request::is('manajemen-karyawan-tim-kpi') ? 'active' : '' }}">
                                    <a href="/manajemen-karyawan-tim-kpi" class="submenu-link">
                                        <i class="bi bi-person-gear"></i> Karyawan Tim Setup
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'superAdminManajemenPeranPage')

                                <li class="submenu-item {{ Request::is('admin/system-manager') ? 'active' : '' }}">
                                    <a href="/admin/system-manager" class="submenu-link">
                                        <i class="bi bi-shield-lock"></i> System Auth Manager
                                    </a>
                                </li>

                                <li class="submenu-item {{ Request::is('admin/role-manager') ? 'active' : '' }}">
                                    <a href="/admin/role-manager" class="submenu-link">
                                        <i class="bi bi-key"></i> Module Role Manager
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'adminHCCutiManagementPage')

                                <li class="submenu-item {{ Request::is('admin/cuti') ? 'active' : '' }}">
                                    <a href="/admin/cuti" class="submenu-link">
                                        <i class="bi bi-calendar-event"></i> Cuti Admin
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'adminHCCutiBersamaPage')

                                <li class="submenu-item {{ Request::is('admin/cuti-bersama') ? 'active' : '' }}">
                                    <a href="/admin/cuti-bersama" class="submenu-link">
                                        <i class="bi bi-calendar-event"></i> Cuti Bersama
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'adminHCKuotaManagementPage')

                                <li class="submenu-item {{ Request::is('admin/kuota-management') ? 'active' : '' }}">
                                    <a href="/admin/kuota-management" class="submenu-link">
                                        <i class="bi bi-pie-chart"></i> Kuota Cuti 
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'IzinPageAdmin')

                                <li class="submenu-item {{ Request::is('izinAdmin') ? 'active' : '' }}">
                                    <a href="/izinAdmin" class="submenu-link">
                                        <i class="bi bi-back"></i> Izin Admin
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial','OvertimeManagementAdmin')

                                    <li class="submenu-item {{ Request::is('lembur-management/admin') ? 'active' : '' }}">
                                        <a href="/lembur-management/admin" class="submenu-link">
                                            <i class="bi bi-stack-overflow"></i> Lembur Admin
                                        </a>
                                    </li>
                                @endcan
                                @can('akses-spesial','ShiftManagementAdmin')
                                <li class="submenu-item {{ Request::is('leader-shift/admin') ? 'active' : '' }}">
                                    <a href="/leader-shift/admin" class="submenu-link">
                                        <i class="bi bi-shuffle"></i> Shift Admin
                                    </a>
                                </li>
                                <li class="submenu-item {{ Request::is('leader-shift/admin/shift-rule') ? 'active' : '' }}">
                                    <a href="/leader-shift/admin/shift-rule" class="submenu-link">
                                        <i class="bi bi-file-earmark-text"></i> Shift Rule
                                    </a>
                                </li>
                            @endcan
                                @can('akses-spesial','approvalFlowShift')
                                <li class="submenu-item {{ Request::is('approval-flow-shift') ? 'active' : '' }}">
                                    <a href="/approval-flow-shift" class="submenu-link">
                                        <i class="bi bi-file-text"></i> Approval Flow Shift
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial','perbaikanCheckinOut')
                                <li class="submenu-item {{ Request::is('perbaiki-checkinout') ? 'active' : '' }}">
                                    <a href="/perbaiki-checkinout" class="submenu-link">
                                        <i class="bi bi-file-ruled"></i> Perbaikan Absensi
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial','addKaryawanTeam')
                                <li class="submenu-item {{ Request::is('add-karyawan-team') ? 'active' : '' }}">
                                    <a href="/add-karyawan-team" class="submenu-link">
                                        <i class="bi bi-gear"></i> Pengaturan Tim & Pengguna
                                    </a>
                                </li>
                            @endcan



                        </ul>
                    </li>
                @endif

                @if($hasadministratorKpiMenu)
                    <li class="sidebar-item has-sub {{ $administratorKpiMenu ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-folder2-open"></i>
                            <span>Administrator KPI</span>
                        </a>
                        <ul class="submenu px-0 {{ $administratorKpiMenu ? 'd-block' : '' }}">
                            @can('akses-spesial', 'MasterPerformance')
                                <li class="submenu-item {{ Request::is('performance-kpi') ? 'active' : '' }}">
                                    <a href="/performance-kpi" class="submenu-link">
                                        <i class="bi bi-graph-up-arrow"></i> Performance
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterBscKategori')
                                <li class="submenu-item {{ Request::is('bsc-kategori') ? 'active' : '' }}">
                                    <a href="/bsc-kategori" class="submenu-link">
                                        <i class="bi bi-grid-1x2-fill"></i> Kategori BSC
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterTarget')
                                <li class="submenu-item {{ Request::is('target-kpi') ? 'active' : '' }}">
                                    <a href="/target-kpi" class="submenu-link">
                                        <i class="bi bi-bullseye"></i> Target
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterFormula')
                                <li class="submenu-item {{ Request::is('formula-kpi') ? 'active' : '' }}">
                                    <a href="/formula-kpi" class="submenu-link">
                                        <i class="bi bi-calculator-fill"></i> Formula KPI
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterSatuan')
                                <li class="submenu-item {{ Request::is('satuan') ? 'active' : '' }}">
                                    <a href="/satuan" class="submenu-link">
                                        <i class="bi bi-rulers"></i> Satuan
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterSatuanFormula')
                                <li class="submenu-item {{ Request::is('satuan-formula') ? 'active' : '' }}">
                                    <a href="/satuan-formula" class="submenu-link">
                                        <i class="bi bi-bezier"></i> Satuan Formula
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'ManajemenAksesDivisi')
                                <li class="submenu-item {{ Request::is('akses-user/divisi') ? 'active' : '' }}">
                                    <a href="/akses-user/divisi" class="submenu-link">
                                        <i class="bi bi-diagram-3-fill"></i> Akses Divisi
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'ManajemenAksesLevel')
                                <li class="submenu-item {{ Request::is('akses-user/level') ? 'active' : '' }}">
                                    <a href="/akses-user/level" class="submenu-link">
                                        <i class="bi bi-person-badge-fill"></i> Akses Level
                                    </a>
                                </li>
                            @endcan

                            @can('akses-spesial', 'MasterPenjadwalan')
                                <li class="submenu-item {{ Request::is('penjadwalan-kpi') ? 'active' : '' }}">
                                    <a href="/penjadwalan-kpi" class="submenu-link">
                                        <i class="bi bi-calendar-check-fill"></i> Jadwal KPI
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif

                <!-- -- Administrator LMS Menu -- -->
                @if($hasadministratorLmsMenu)
                    <li class="sidebar-item has-sub {{ $administratorLmsMenu ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-gear-fill"></i>
                            <span>Administrator LMS</span>
                        </a>
                        <ul class="submenu px-0 {{ $administratorLmsMenu ? 'd-block' : '' }}">
                            @can('akses-spesial', 'LMSTrainingKategoriPage')
                                <li class="submenu-item {{ Request::is('lms/training-kategori') ? 'active' : '' }}">
                                    <a href="/lms/training-kategori" class="submenu-link">
                                        <i class="bi bi-tags"></i> Kategori Training
                                    </a>
                                </li>
                            @endcan    
                            @can('akses-spesial', 'LMSContentManagementPage')
                                <li class="submenu-item {{ Request::is('lms/konten-kategori') ? 'active' : '' }}">
                                    <a href="/lms/konten-kategori" class="submenu-link">
                                        <i class="bi bi-collection-play"></i> Manajemen Konten
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'LMSAdminTrainingPage')
                                <li class="submenu-item {{ Request::is('lms/admin/training*') ? 'active' : '' }}">
                                    <a href="/lms/admin/training" class="submenu-link">
                                        <i class="bi bi-pencil-square"></i> Manajemen Training
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'LMSPenjadwalanPage')
                                <li class="submenu-item {{ Request::is('lms/penjadwalan') ? 'active' : '' }}">
                                    <a href="/lms/penjadwalan" class="submenu-link">
                                        <i class="bi bi-calendar-event"></i> Penjadwalan Training
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial', 'LMSAdminMonitoringPage')
                                <li class="submenu-item {{ Request::is('lms/admin/monitoring*') ? 'active' : '' }}">
                                    <a href="/lms/admin/monitoring" class="submenu-link">
                                        <i class="bi bi-eye"></i> Pemantauan
                                    </a>
                                </li>
                            @endcan
                       
                        </ul>
                    </li>
                @endif

                @can('open-kpi')
                    {{-- Cross Review Menu --}}
                    <li class="sidebar-item has-sub {{ $isCrossActive ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Cross Review</span>
                        </a>
                        <ul class="submenu px-0 {{ $isCrossActive ? 'd-block' : '' }}">
                            <li class="submenu-item {{ $currentRoute === 'Cross.index' ? 'active' : '' }}">
                                <a href="{{ route('Cross.index') }}" class="submenu-link">
                                    <i class="bi bi-person-bounding-box"></i> Create Cross Review
                                </a>
                            </li>
                            @if (auth()->user()->Username == 'welly' || auth()->user()->Username == 'yustina')
                            <li class="submenu-item {{ Request::is('crossReviewPeriode') ? 'active' : '' }}">
                                <a href="/crossReviewPeriode" class="submenu-link">
                                    <i class="bi bi-calendar2-plus"></i> Cross Review Period
                                </a>
                            </li>
                            @endif
                            @if (auth()->user()->Level_Id == 2 && auth()->user()->Username == 'yustina')
                            <li class="submenu-item {{ Request::is('crossReviewSummary') ? 'active' : '' }}">
                                <a href="/crossReviewSummary" class="submenu-link">
                                    <i class="bi bi-journals"></i> Summary Review
                                </a>
                            </li>
                            @endif
                            <li class="submenu-item {{ Request::is('historyCrossReview') ? 'active' : '' }}">
                                <a href="/historyCrossReview" class="submenu-link">
                                    <i class="bi bi-clock-history"></i> Review History
                                </a>
                            </li>
                            <li class="submenu-item {{ Request::is('crossFeedbackReview') ? 'active' : '' }}">
                                <a href="/crossFeedbackReview" class="submenu-link">
                                    <i class="bi bi-clock-history"></i> Cross Review Feedback
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Project & Task (Admin Only) --}}
                    @if (auth()->user()->Username == 'adminreza')
                    <li class="sidebar-item {{ $currentRoute === 'project.index' ? 'active' : '' }}">
                        <a href="{{ route('project.index') }}" class="sidebar-link">
                            <i class="bi bi-kanban"></i>
                            <span>Project</span>
                        </a>
                    </li>
                    <li class="sidebar-item {{ Request::is('task') ? 'active' : '' }}">
                        <a href="/task" class="sidebar-link">
                            <i class="bi bi-list-task"></i>
                            <span>Task</span>
                        </a>
                    </li>
                    @endif

                    {{-- Ticket --}}
                    <li class="sidebar-item has-sub {{ $isTicketActive ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-ticket-detailed"></i>
                            <span>Ticket</span>
                        </a>
                        <ul class="submenu px-0 {{ $isTicketActive ? 'd-block' : '' }}">
                            <li class="submenu-item {{ Request::is('displayTicket') ? 'active' : '' }}">
                                <a href="/displayTicket" class="submenu-link">
                                    <i class="bi bi-card-list"></i> Display Ticket
                                </a>
                            </li>
                            <li class="submenu-item {{ Request::is('takeTicket') ? 'active' : '' }}">
                                <a href="/takeTicket" class="submenu-link">
                                    <i class="bi bi-reply"></i> Response Ticket
                                </a>
                            </li>
                            <li class="submenu-item {{ Request::is('finishTicket') ? 'active' : '' }}">
                                <a href="/finishTicket" class="submenu-link">
                                    <i class="bi bi-check-circle"></i> Completion Ticket
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Global Review --}}
                    @if (auth()->user()->Level_Id == 2)
                    <li class="sidebar-item {{ Request::is('globalReview') ? 'active' : '' }}">
                        <a href="/globalReview" class="sidebar-link">
                            <i class="fa-regular fa-star-half-stroke"></i>
                            <span>Global Review</span>
                        </a>
                    </li>
                    <li class="sidebar-item {{ Request::is('master-periode') ? 'active' : '' }}">
                        <a href="/master-periode" class="sidebar-link">
                            <i class="fa-regular fa-star-half-stroke"></i>
                            <span>Master Periode</span>
                        </a>
                    </li>
                    <li class="sidebar-item {{ $currentRoute === 'summary.review' ? 'active' : '' }}">
                        <a href="{{ route('summary.review') }}" class="sidebar-link">
                            <i class="fa-regular fa-star-half-stroke"></i>
                            <span>Summary Global Review</span>
                        </a>
                    </li>
                    @endif

                    <li class="sidebar-item {{ Request::is('ticket') ? 'active' : '' }}">
                        <a href="/ticket" class="sidebar-link">
                            <i class="bi bi-plus-circle"></i>
                            <span>Create Ticket</span>
                        </a>
                    </li>
                    @endcan
                    <hr>

                    <li class="sidebar-item {{ $currentRoute === 'profileUser.index' ? 'active' : '' }}">
                        <a href="{{route('profileUser.index')}}" class="sidebar-link">
                            <i class="bi bi-person-circle"></i>
                            <span>Profile</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a href="/logout" class="sidebar-link">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                    </li>

            </ul>
        </div>
    </div>
</div>
