<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">

<head>
    @include('components.header')
    @stack('vueapp')
    <style>
        html,
        body {
            height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        #app {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        #main {
            flex: 1;
            padding: 1rem;
        }

        @media (max-width: 991.98px) {
            #main {
                padding: 0;
            }
        }

        footer {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: center;
        }
        #sidebar.active .sidebar-wrapper {
            left: 0;
            z-index: 999;
        }
        .sidebar-backdrop{
            z-index: 998;
            transition: all 2s ease;
        }
    </style>

<script src="https://cdn.lordicon.com/lordicon.js"></script>
</head>


<body>

    {{-- New Topbar Component --}}
    @include('components.topbar', [
        'user' => Auth::user(),
        'notifications' => $notifications ?? [],
        'messages' => $messages ?? [],
        'unreadCount' => $unreadCount ?? 0,
        'activeModule' => $activeModule ?? 'APP',
        'activeModuleUrl' => $activeModuleUrl ?? '/homepage'
    ])

    <div id="app1">
        @include('components.sidebar')
        <div id="main">
            @yield('content')
            @include('sweetalert::alert')
        </div>
    </div>

    <!-- Load jQuery & DataTables BEFORE shell scripts -->
    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <!-- Footer loads compiled app.js (includes shell-topbar & shell-sidebar) -->
    @include('components.footer')
    
    @stack('scripts')
</body>

</html>
