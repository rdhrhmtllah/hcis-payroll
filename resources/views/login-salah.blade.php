<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HCIS EVO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
             --primary: var(--hcis-indigo-500);
            --primary-dark: var(--hcis-indigo-600);
            --secondary: var(--hcis-slate-500);
            --success: var(--hcis-emerald-500);
            --warning: var(--hcis-amber-500);
            --danger: var(--hcis-red-500);
            --info: var(--hcis-cyan-500);
            --light: var(--hcis-slate-50);
            --dark: var(--hcis-slate-800);
            --surface: var(--hcis-white);
            --surface-soft: var(--hcis-slate-100);
            --border: var(--hcis-slate-200);
            --text: var(--hcis-slate-700);
            --text-muted: var(--hcis-slate-500);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            --primary-gradient: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
            --primary-color: var(--hcis-legacy-violet-400);
            --secondary-color: var(--hcis-legacy-violet-700);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--surface);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }


        /* Main Container */
        .login-wrapper {
            min-height: 100vh;
            backdrop-filter: blur(5px);
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            animation: slideInUp 0.8s ease-out;
        }

        .login-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
        }

        .logo-container {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .logo-container i {
            font-size: 2.2rem;
        }

        .login-title {
            color: #2d3748;
            font-weight: 700;
        }

        .login-subtitle {
            color: #718096;
            font-size: 0.95rem;
        }

        /* Form Styling */
        .form-label {
            color: #4a5568;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            border: 2px solid var(--hcis-slate-200);
            border-radius: 12px;
            padding: 0.875rem 1rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            background: rgba(255, 255, 255, 0.95);
        }

        .input-group .form-control:focus {
            z-index: 2;
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
            border-radius: 0 12px 12px 0;
            border-left: none;
        }

        .btn-outline-primary:hover,
        .btn-outline-primary:focus {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--surface);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            padding: 0.875rem 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        element.style{
            justify-content: start;
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Captcha Container */
        .captcha-wrapper {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        /* Links */
        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(10px);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .text-danger {
            color: #dc3545 !important;
            font-size: 0.85rem;
        }


        /* Modern Buttons */
        .btn-modern {
            display: flex;

            align-items: center;
            padding: 0.75rem 1.2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-modern::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: var(--text);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }


        /* Animations */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-card {
                margin: 1rem;
                border-radius: 20px;
            }

            .logo-container {
                width: 70px;
                height: 70px;
            }

            .logo-container i {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

    <!-- Alert untuk error -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
        @if (Session::has('error'))
        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__slideInRight" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ Session::get('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif
    </div>

    <!-- Main Login Container -->
    <div class="login-wrapper d-flex align-items-center justify-content-center p-3">
        <div class="login-card shadow-lg">
            <!-- Header Section -->
            <div class="login-header p-4 text-center">
                <img src="./assets/compiled/png/logo-lg.webp" alt="Logo" width="20%" style="height: fit-content"><br>
                {{-- <div class="logo-container mx-auto mb-3 d-flex align-items-center justify-content-center animate__animated animate__bounceIn">

                </div> --}}
                {{-- <h1 class="login-title h3 mb-2">Manajemen Penugasan Shift</h1> --}}
                {{-- <p class="login-subtitle mb-0">Kelola pergantian shift divisi production support</p> --}}
            </div>

            <!-- Form Section -->
            <div class="p-4">
                @if(Session::has('status'))
                <div class="alert alert-danger mb-4 animate__animated animate__fadeIn">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    {{ Session::get('status') }}
                </div>
                @endif

                <form action="/prosesLogin" method="POST">
                    @csrf

                    <!-- Username Field -->
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username
                        </label>
                        <input type="text"
                               class="form-control form-control-lg"
                               id="username"
                               name="username"
                               placeholder="Masukan Username"
                               autofocus
                               required>
                    </div>

                    <!-- Password Field -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control form-control-lg"
                                   id="password"
                                   name="password"
                                   placeholder="Masukan Password"
                                   required>
                            <button class="btn btn-outline-primary"
                                    type="button"
                                    id="togglePassword">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Captcha Section -->
                    <div class="mb-4">
                        <div class="captcha-wrapper py-3 text-center ">
                            <div class="cf-turnstile"
                                 data-sitekey="{{ env('CLOUDFLARE_TURNSTILE_SITEKEY') }}"
                                 data-theme="light">
                            </div>
                            @error('captcha')
                            <div class="text-danger mt-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>

                    <!-- Login Button -->
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn-modern btn-primary btn-lg justify-content-center">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </div>

                    <!-- Forgot Password Link -->
                    <div class="text-center">
                        <a href="/forgot-password" class="forgot-link">
                            <i class="bi bi-question-circle me-1"></i>Lupa password?
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                eyeIcon.className = 'bi bi-eye';
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation feedback
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const button = form.querySelector('button[type="submit"]');
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
            button.disabled = true;
        });
    </script>

    @include('sweetalert::alert')
</body>
</html>
