<!DOCTYPE html>
<html lang="en">

<head>
    @include('components.header')

    <style>
        html,
        body {
            height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        #app2 {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        #main22 {
            flex: 1;
        }

        footer {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: center;
        }
    </style>
</head>

<body>
    <div id="app">
        @include('components.sidebar')
        <div id="main22">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
            @yield('content')

            @include('sweetalert::alert')
        </div>
        @include('components.footer')
    </div>
    <script src="/assets/vendor/jquery/jquery.min.js"></script>
</body>

</html>
