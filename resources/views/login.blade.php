<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HCIS EVO</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('logo/logo.png') }}" sizes="any" />
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('logo/logo.png') }}" />
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('logo/logo.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('logo/logo.png') }}" />
    <link rel="shortcut icon" href="{{ asset('logo/logo.png') }}" type="image/png" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />

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
            --base-start: #f6f8ff; /* gradasi awal: putih kebiruan lembut */
            --base-end: #e0e4ff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--surface);
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        .logo-shimmer {
            mask: url('/logo/logo.png') center/contain no-repeat;
            -webkit-mask: url('/logo/logo.png') center/contain no-repeat;
        }

       .blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(40px);
        z-index: -1;
     }
        /* Blob 1 */
        .blob-1 {
            width: 300px;
            height: 300px;
            background: rgba(99, 102, 241, 0.25);
            top: -150px;
            right: -150px;
        }

        /* Blob 2 */
        .blob-2 {
            width: 250px;
            height: 250px;
            background: rgba(99, 102, 241, 0.15);
            bottom: -100px;
            left: -100px;
            animation-delay: -3s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .gradient-bg {
            position: fixed;
            top: 0;
            width: 100vw;
            height: 100vh;
            left: 0;
            z-index: -1;
            overflow: hidden;
            background: linear-gradient(9deg,rgba(224, 228, 255, 0.25) 0%, rgba(246, 248, 255, 1) 53%);
        }

        .gradient-overlay {
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background:
                radial-gradient(circle at 30% 30%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 50% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
            animation: gradientMove 20s ease-in-out infinite;
            transform-origin: center;
        }

        .logo {
            position: relative;
            display: inline-block;
            overflow: hidden;
            border-radius: 10px; /* Sesuaikan dengan bentuk logo */
        }

        .logo img {
            width: 80px;
            height: auto;
            border-radius: 0px; /* Sesuaikan dengan bentuk logo */
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            position: relative;
            z-index: 1;
            display: block;
        }

        /* Shimmer Effect yang mengikuti bentuk logo */
        .login-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.1),
                rgba(255, 255, 255, 0.3),
                rgba(255, 255, 255, 0.1),
                transparent
            );
            transform: skewX(-25deg);
            z-index: 2;
            animation: shimmer 1.5s infinite ease;
            border-radius: 10px;
        }

        .logo-shimmer {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.6),
                transparent
            );
            transform: skewX(-25deg);
            z-index: 2;
            animation: shimmer 3s infinite;
            border-radius: 20px;
            mask: url('/logo/logo.png') center/contain no-repeat;
            -webkit-mask: url('/logo/logo.png') center/contain no-repeat;
        }

        .version-text {
            display: block;
            text-align: center;
            margin-top: 8px;
            width: 100%;
            font-size: 10px;
            color: #666;
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
            position: relative;
            overflow: hidden;
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
            border: 2px solid var(--border);
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
            min-height: 103px;
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
        @keyframes gradientMove {
            0%, 100% {
                transform: scale(1) rotate(0deg);
            }
            50% {
                transform: scale(1.05) rotate(1deg);
            }
        }

        @keyframes shimmer {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
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
                border-radius: 20px;
            }

            .logo {
                margin-bottom: 1rem;
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

    <!-- Animated Gradient Background -->
    <div class="gradient-bg">
         <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="gradient-overlay"></div>
    </div>

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
            <div class="login-header py-3 text-center">
                <div class="logo">
                    <img src="/logo/logo.png" alt="Logo" onerror="this.style.display='none'" />
                    <!-- Alternatif: Gunakan div untuk shimmer jika bentuk logo kompleks -->
                    <!-- <div class="logo-shimmer"></div> -->
                </div>
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
                        <small class="version-text">Versi 2.13.0</small>
                    </div>

        <div style="display: flex; align-items: center; text-align: center; margin: 20px 0;">
            <div style="flex: 1; border-top: 1px solid #ccc;"></div>
            <span style="padding: 0 10px; font-size: 14px; font-weight: bold; color: #555;">Support</span>
            <div style="flex: 1; border-top: 1px solid #ccc;"></div>
        </div>

        <div style="text-align: center;">
            <img src="/logo/logo-group-horizon.jpg" alt="Logo" style="max-width: 270px; height: auto; margin-top: 10px; margin-bottom: 10px;">
        </div>



                    <!-- Forgot Password Link -->
                    <!-- <div class="text-center">
                        <a href="/forgot-password" class="forgot-link">
                            <i class="bi bi-question-circle me-1"></i>Lupa password?
                        </a>
                    </div> -->
                </form>
            </div>
        </div>
    </div>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            localStorage.removeItem("theme");
            localStorage.setItem("theme", "light");
        });
    </script>
    <script>
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

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

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
