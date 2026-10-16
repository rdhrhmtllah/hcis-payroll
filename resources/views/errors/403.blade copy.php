<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <style>
        /* Reset dan Base Styles */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            padding: 20px;
        }

        /* Partikel Background */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.5);
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
                transform: translateY(50vh) translateX(50px) rotate(180deg);
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-150px) translateX(-50px) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes float-alt {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            50% {
                transform: translateY(50vh) translateX(-50px) rotate(-180deg);
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-150px) translateX(50px) rotate(-360deg);
                opacity: 0;
            }
        }

        /* Container Utama */
        .container {
            text-align: center;
            max-width: 600px;
            width: 100%;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10;
            position: relative;
            animation: fadeInAnimation 1s ease-in-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Scrollbar styling untuk container */
        .container::-webkit-scrollbar {
            width: 6px;
        }

        .container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        .container::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
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

        /* Logo */
        .logo {
            margin-bottom: 1.5rem;
        }

        .logo img {
            width: 80px;
            height: auto;
        }

        /* Kode Error */
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            background-image: linear-gradient(to right, #ef4444, #f87171);
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
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* Deskripsi */
        .description {
            font-size: 1rem;
            margin-bottom: 2rem;
            color: #e0e0e0;
            line-height: 1.6;
            padding: 0 1rem;
        }

        /* Ikon Error */
        .error-icon {
            margin: 1.5rem 0;
            font-size: 4rem;
            color: #ef4444;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        /* Loading Bar */
        .loading-container {
            width: 100%;
            margin: 1.5rem 0;
        }

        .loading-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .loading-progress {
            height: 100%;
            width: 0%;
            background: linear-gradient(to right, #ef4444, #f87171);
            border-radius: 10px;
            transition: width 1s linear;
            position: relative;
            overflow: hidden;
        }

        .loading-progress::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(
                -45deg,
                rgba(255, 255, 255, 0.2) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0.2) 75%,
                transparent 75%,
                transparent
            );
            background-size: 50px 50px;
            animation: move 2s linear infinite;
            border-radius: 10px;
        }

        @keyframes move {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 50px 50px;
            }
        }

        .loading-text {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #f87171;
        }

        /* Access Info */
        .access-info {
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
            border-left: 4px solid #ef4444;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: #f87171;
        }

        .info-value {
            font-weight: 600;
        }

        .info-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .info-danger {
            background: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .info-warning {
            background: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        /* Auto Redirect */
        .auto-redirect {
            margin-top: 1rem;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            font-size: 0.85rem;
        }

        .redirect-countdown {
            color: #f87171;
            font-weight: 600;
        }

        /* Tombol Aksi */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #10b981, #34d399);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(52, 211, 153, 0.6);
            cursor: pointer;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            min-height: 48px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px -5px rgba(52, 211, 153, 0.5);
        }

        .btn-alt {
            background: linear-gradient(to right, #667eea, #764ba2);
            box-shadow: 0 4px 15px -5px rgba(102, 126, 234, 0.6);
        }

        .btn-alt:hover {
            box-shadow: 0 7px 20px -5px rgba(102, 126, 234, 0.5);
        }

        .btn-danger {
            background: linear-gradient(to right, #ef4444, #f87171);
            box-shadow: 0 4px 15px -5px rgba(239, 68, 68, 0.6);
        }

        .btn-danger:hover {
            box-shadow: 0 7px 20px -5px rgba(239, 68, 68, 0.5);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        /* Tips */
        .tips {
            margin-top: 1.5rem;
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            text-align: left;
        }

        .tips h3 {
            margin-bottom: 0.8rem;
            color: #f87171;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .tips h3 i {
            margin-right: 0.5rem;
        }

        .tips ul {
            list-style-type: none;
        }

        .tips li {
            margin-bottom: 0.5rem;
            padding-left: 1.2rem;
            position: relative;
            font-size: 0.85rem;
        }

        .tips li::before {
            content: '•';
            color: #f87171;
            position: absolute;
            left: 0;
        }

        /* Responsif */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 1.5rem;
                max-height: 95vh;
            }

            .error-code {
                font-size: 5rem;
            }

            .main-title {
                font-size: 1.5rem;
            }

            .description {
                font-size: 0.9rem;
                padding: 0;
            }

            .error-icon {
                font-size: 3rem;
            }

            .access-info {
                padding: 0.8rem;
            }

            .btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.85rem;
            }

            .tips {
                padding: 1rem;
            }

            .tips h3 {
                font-size: 0.9rem;
            }

            .tips li {
                font-size: 0.8rem;
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

            .logo img {
                width: 70px;
            }

            .action-buttons {
                max-width: 100%;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }
        }

        /* Untuk layar sangat kecil */
        @media (max-width: 320px) {
            .container {
                padding: 1rem;
            }

            .error-code {
                font-size: 3.5rem;
            }

            .main-title {
                font-size: 1.2rem;
            }

            .description {
                font-size: 0.85rem;
            }
        }

        /* Untuk landscape mode di mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .container {
                max-height: 85vh;
                padding: 1rem;
            }

            .logo {
                margin-bottom: 1rem;
            }

            .error-code {
                font-size: 4rem;
                margin-bottom: 0.3rem;
            }

            .main-title {
                font-size: 1.3rem;
                margin-bottom: 0.5rem;
            }

            .description {
                margin-bottom: 1rem;
            }

            .error-icon {
                margin: 1rem 0;
                font-size: 2.5rem;
            }

            .action-buttons {
                margin-top: 1rem;
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

        <h1 class="main-title">Akses Ditolak</h1>

        <p class="description">
            {{-- Cek apakah ada pesan kustom dari abort(), jika tidak pakai pesan default --}}
            {{ $exception->getMessage() ?: 'Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.' }}
            
            <br>
            Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
        </p>

        <div class="error-icon">
            <i class="bi bi-shield-exclamation"></i>
        </div>

        <!-- Loading Bar -->
        <div class="loading-container">
            <div class="loading-bar">
                <div class="loading-progress" id="loadingProgress"></div>
            </div>
            <div class="loading-text">Mempersiapkan halaman beranda...</div>
        </div>
        {{-- <span class="info-value" id="requestTime"></span> --}}

        <!-- Access Info -->
        {{-- <div class="access-info">
            <div class="info-item">
                <span class="info-label">Status Akses:</span>
                <span class="info-value info-badge info-danger">DITOLAK</span>
            </div>
            <div class="info-item">
                <span class="info-label">Level Akses Diperlukan:</span>
                <span class="info-value" id="requiredLevel">Administrator</span>
            </div>
            <div class="info-item">
                <span class="info-label">Level Akses Anda:</span>
                <span class="info-value info-badge info-warning" id="userLevel">Pengguna</span>
            </div>
            <div class="info-item">
                <span class="info-label">Waktu Permintaan:</span>
                <span class="info-value" id="requestTime"></span>
            </div>
        </div> --}}

        <!-- Auto Redirect Countdown -->
        <div class="auto-redirect">
            <i class="bi bi-arrow-left-circle"></i>
            Anda akan diarahkan ke halaman beranda dalam
            <span class="redirect-countdown" id="countdown">5</span> detik
        </div>

        <!-- Tombol Aksi -->
        <div class="action-buttons">
            <a href="/" class="btn btn-alt">
                <i class="bi bi-house"></i> Kembali ke Beranda
            </a>
            <a href="javascript:history.back()" class="btn">
                <i class="bi bi-box-arrow-in-left"></i> Kembali ke Halaman Sebelumnya
            </a>
            {{-- <button class="btn btn-danger" id="reportBtn">
                <i class="bi bi-flag"></i> Laporkan Masalah
            </button> --}}
        </div>

        <!-- Tips -->
        {{-- <div class="tips">
            <h3><i class="bi bi-lightbulb"></i> Tips untuk mengatasi masalah ini:</h3>
            <ul>
                <li>Pastikan Anda login dengan akun yang memiliki hak akses yang sesuai</li>
                <li>Periksa kembali URL yang Anda akses</li>
                <li>Hubungi administrator sistem untuk meminta akses</li>
                <li>Clear cache dan cookies browser Anda</li>
            </ul>
        </div> --}}
    </div>

    <script>
        // Membuat partikel
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            if (!container) return;

            const particleCount = 300;
            const colors = ["#ef4444", "#f87171", "#667eea", "#ffffff"];

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                const size = Math.random() * 10 + 3;
                const duration = Math.random() * 5 + 5;
                const delay = 0;

                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.bottom = `-${size}px`;
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.opacity = Math.random() * 0.4 + 0.1;

                const animationName = Math.random() > 0.5 ? 'float' : 'float-alt';
                particle.style.animation = `${animationName} ${duration}s linear ${delay}s infinite`;

                container.appendChild(particle);
            }
        }

        // Set waktu permintaan
        // document.getElementById('requestTime').textContent = new Date().toLocaleString('id-ID');

        // Level akses acak (simulasi)
        // const accessLevels = ['Pengguna', 'Moderator', 'Administrator', 'Super Admin'];
        // const userLevels = ['Pengguna', 'Pengguna', 'Moderator', 'Pengguna']; // Bias ke level rendah
        // document.getElementById('userLevel').textContent = userLevels[Math.floor(Math.random() * userLevels.length)];
        // document.getElementById('requiredLevel').textContent = accessLevels[Math.floor(Math.random() * 2) + 2]; // Administrator atau Super Admin

        // Auto redirect countdown dengan loading bar
        let countdown = 5
        const countdownElement = document.getElementById('countdown');
        const loadingProgress = document.getElementById('loadingProgress');
        const totalTime = countdown * 1000; // Total waktu dalam milidetik
        let startTime = Date.now();

        function updateProgress() {
            const elapsedTime = Date.now() - startTime;
            const progress = Math.min((elapsedTime / totalTime) * 100, 100);

            loadingProgress.style.width = `${progress}%`;

            if (progress < 100) {
                requestAnimationFrame(updateProgress);
            }
        }

        const countdownInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0 && $exception->getMessage() ) {
                clearInterval(countdownInterval);
                window.location.href = '/';
            }
        }, 1000);

        // Mulai animasi loading bar
        updateProgress();

        // Tombol laporan masalah
        document.getElementById('reportBtn').addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-check"></i> Masalah Dilaporkan';
            this.style.background = 'linear-gradient(to right, #10b981, #34d399)';
            this.style.boxShadow = '0 4px 15px -5px rgba(52, 211, 153, 0.6)';

            // Simulasi pengiriman laporan
            setTimeout(() => {
                alert('Terima kasih! Laporan Anda telah dikirim ke administrator.');
            }, 500);
        });

        // Mencegah scroll di body
        document.body.addEventListener('touchmove', function(e) {
            if (e.target.closest('.container')) {
                return;
            }
            e.preventDefault();
        }, { passive: false });

        // Inisialisasi
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();

            // Pastikan konten tidak melebihi viewport
            const container = document.querySelector('.container');
            const viewportHeight = window.innerHeight;

            if (container.scrollHeight > viewportHeight * 0.9) {
                container.style.maxHeight = '90vh';
            }
        });

        // Handle resize
        window.addEventListener('resize', function() {
            const container = document.querySelector('.container');
            const viewportHeight = window.innerHeight;

            if (container.scrollHeight > viewportHeight * 0.9) {
                container.style.maxHeight = '90vh';
            } else {
                container.style.maxHeight = 'none';
            }
        });
    </script>
</body>
</html>
