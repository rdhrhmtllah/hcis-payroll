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


$isAdmin = Request::is('izinAdmin', 'swapShiftAdmin', 'overtimeAdmin', 'add-karyawan-team', 'perbaiki-checkinout');
$rolesAdmin = ['IzinPageAdmin', 'OvertimeManagementAdmin', 'ShiftManagementAdmin', 'addKaryawanTeam', 'perbaikanCheckinOut'];
$hasAccessAdmin = array_filter($rolesAdmin, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessAdmin = !empty($hasAccessAdmin);

$isPerson = Request::is('izin', 'absensi', 'absensiSales');
$rolesPerson = ['IzinPage', 'absensiPage', ];
$hasAccessPerson = array_filter($rolesPerson, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessPerson = !empty($hasAccessPerson);

$isTeam = Request::is('izinLevelUp', 'overtime', 'swapShift');
$rolesTeam = ['OvertimeManagement', 'IzinPageApprover', 'ShiftManagement'];
$hasAccessTeam = array_filter($rolesTeam, fn($role) => auth()->user()->can('akses-spesial', $role));
$hasAccessTeam = !empty($hasAccessTeam);

// Custom check for Ticket group
$isTicketActive =
Request::is('displayTicket') ||
Request::is('takeTicket') ||
Request::is('finishTicket');
@endphp

<div id="sidebar">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative mt-4">
            <div class="d-flex justify-content-start align-items-center rounded mb-3">
                <img src="./assets/compiled/png/logo-lg.webp" alt="Logo" width="20%" style="height: fit-content"><br>
                <span class="text-uppercase fw-bold px-2 py-0" style="font-size: 16px">EVO GROUP</span>

            </div>
            <div class="my-0 py-0 d-flex flex-column mt-3">
                <span class="  fw-bold px-2 py-0" style="font-size: 18px"><i class="me-2 bi bi-person-fill"></i><span class="text-uppercase">{{
                        Auth::user()->Username
                        }}</span></span>
            <span style="font-size: 0.9rem; font-weight: 400;" class=" px-2 fw-0 my-0"> <span class=""></span> {{Auth::user()->divisionKaryawan->nama_sub_divisi}}</span>
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
                <li class="sidebar-item {{ $isDashboard ? 'active' : '' }}">
                    <a href="/uDash" class="sidebar-link">
                        <i class="bi bi-house-door"></i>
                        <span>Dashboard</span>
                    </a>
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

                        </ul>
                    </li>
                @endif

                @if($hasAccessTeam)
                    <li class="sidebar-item has-sub {{ $isTeam ? 'active' : '' }}">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-person-video2"></i>
                            <span>Manajemen Tim</span>
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

                                    <li class="submenu-item {{ Request::is('overtime') ? 'active' : '' }}">
                                        <a href="/overtime" class="submenu-link">
                                            <i class="bi bi-stack-overflow"></i> Penugasan Lembur
                                        </a>
                                    </li>
                                @endcan

                                @can('akses-spesial','ShiftManagement')
                                <li class="submenu-item {{ Request::is('swapShift') ? 'active' : '' }}">
                                    <a href="/swapShift" class="submenu-link">
                                        <i class="bi bi-shuffle"></i> Pergantian Shift
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
                        <span>Administrator</span>
                    </a>
                       <ul class="submenu px-0 {{ $isAdmin ? 'd-block' : '' }}">

                        @can('akses-spesial', 'IzinPageAdmin')

                            <li class="submenu-item {{ Request::is('izinAdmin') ? 'active' : '' }}">
                                <a href="/izinAdmin" class="submenu-link">
                                    <i class="bi bi-back"></i> Izin Admin
                                </a>
                            </li>
                        @endcan

                        @can('akses-spesial','OvertimeManagementAdmin')

                                <li class="submenu-item {{ Request::is('overtimeAdmin') ? 'active' : '' }}">
                                    <a href="/overtimeAdmin" class="submenu-link">
                                        <i class="bi bi-stack-overflow"></i> Lembur Admin
                                    </a>
                                </li>
                            @endcan
                            @can('akses-spesial','ShiftManagementAdmin')
                            <li class="submenu-item {{ Request::is('swapShiftAdmin') ? 'active' : '' }}">
                                <a href="/swapShiftAdmin" class="submenu-link">
                                    <i class="bi bi-shuffle"></i> Shift Admin
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
