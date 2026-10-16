<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akun Nonaktif</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1f2937 0%, #7c2d12 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            padding: 20px;
        }

        .particles {
            position: absolute;
            inset: 0;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.35);
            border-radius: 50%;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            50% {
                transform: translateY(50vh) translateX(40px) rotate(180deg);
            }
            100% {
                transform: translateY(-150px) translateX(-40px) rotate(360deg);
                opacity: 0;
            }
        }

        .container {
            text-align: center;
            max-width: 620px;
            width: 100%;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.35);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10;
            position: relative;
            animation: fadeInAnimation 0.8s ease-in-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        .container::-webkit-scrollbar {
            width: 6px;
        }

        .container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        @keyframes fadeInAnimation {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            margin-bottom: 1.5rem;
        }

        .logo img {
            width: 80px;
            height: auto;
        }

        .error-code {
            font-size: 6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background-image: linear-gradient(to right, var(--hcis-amber-500), var(--hcis-amber-300));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            line-height: 1;
        }

        .main-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .description {
            font-size: 1rem;
            margin-bottom: 1.6rem;
            color: #f3f4f6;
            line-height: 1.7;
            padding: 0 1rem;
        }

        .error-icon {
            margin: 1.4rem 0;
            font-size: 4rem;
            color: var(--hcis-amber-400);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.08);
            }
        }

        .status-box {
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(245, 158, 11, 0.1);
            border-radius: 10px;
            border-left: 4px solid var(--hcis-amber-500);
        }

        .status-box p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--hcis-amber-200);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            min-height: 48px;
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--hcis-amber-500), var(--hcis-amber-400));
            box-shadow: 0 4px 15px -5px rgba(245, 158, 11, 0.7);
        }

        .btn-secondary {
            background: linear-gradient(to right, var(--hcis-slate-600), var(--hcis-slate-700));
            box-shadow: 0 4px 15px -5px rgba(71, 85, 105, 0.7);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .tips {
            margin-top: 1.5rem;
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            text-align: left;
        }

        .tips h3 {
            margin-bottom: 0.8rem;
            color: var(--hcis-amber-300);
            text-align: center;
            font-size: 1rem;
        }

        .tips ul {
            list-style: none;
        }

        .tips li {
            margin-bottom: 0.6rem;
            padding-left: 1.2rem;
            position: relative;
            font-size: 0.9rem;
        }

        .tips li::before {
            content: '*';
            color: var(--hcis-amber-400);
            position: absolute;
            left: 0;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 1.5rem;
            }

            .error-code {
                font-size: 5rem;
            }

            .main-title {
                font-size: 1.5rem;
            }

            .description {
                font-size: 0.92rem;
                padding: 0;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1.2rem;
            }

            .error-code {
                font-size: 4rem;
            }

            .main-title {
                font-size: 1.3rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="particles" id="particlesContainer"></div>

    <div class="container">
        <div class="logo">
            <img src="/logo/logo.png" alt="Logo" onerror="this.style.display='none'" />
        </div>

        <div class="error-code">403</div>

        <h1 class="main-title">Akun Anda telah nonaktif</h1>

        <p class="description">
            Login tidak dapat dilanjutkan karena status karyawan Anda non aktif.
        </p>

        <div class="error-icon">
            <i class="bi bi-person-x-fill"></i>
        </div>

        <div class="status-box">
            <p>
                Jika ini seharusnya tidak terjadi, silakan hubungi HR atau administrator agar status karyawan Anda dapat diperiksa kembali.
            </p>
        </div>

        <div class="action-buttons">
            <a href="/login" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-left"></i> Kembali ke Login
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Halaman Sebelumnya
            </a>
        </div>

        <div class="tips">
            <h3>Yang bisa Anda lakukan</h3>
            <ul>
                <li>Pastikan data karyawan Anda memang masih aktif di sistem HR.</li>
                <li>Hubungi HRD atau administrator aplikasi jika akun seharusnya masih dapat digunakan.</li>
                <li>Coba login kembali setelah status karyawan diperbarui.</li>
            </ul>
        </div>
    </div>

    <script>
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            if (!container) return;

            const particleCount = 160;
            const colors = ['#f59e0b', '#fcd34d', '#ffffff', '#fdba74'];

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                const size = Math.random() * 9 + 3;
                const duration = Math.random() * 5 + 6;

                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.bottom = `-${size}px`;
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.opacity = Math.random() * 0.4 + 0.1;
                particle.style.animation = `float ${duration}s linear infinite`;

                container.appendChild(particle);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
        });
    </script>
</body>
</html>
