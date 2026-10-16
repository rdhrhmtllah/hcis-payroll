<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Sedang Pemeliharaan</title>
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
            /* Latar belakang gradien biru gelap elegan */
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
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
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            50% { transform: translateY(50vh) translateX(50px) rotate(180deg); }
            90% { opacity: 1; }
            100% { transform: translateY(-150px) translateX(-50px) rotate(360deg); opacity: 0; }
        }

        @keyframes float-alt {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            50% { transform: translateY(50vh) translateX(-50px) rotate(-180deg); }
            90% { opacity: 1; }
            100% { transform: translateY(-150px) translateX(50px) rotate(-360deg); opacity: 0; }
        }

        /* Container Utama */
        .container {
            text-align: center;
            max-width: 600px;
            width: 100%;
            padding: 2.5rem 2rem;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10;
            position: relative;
            animation: fadeInAnimation 1s ease-in-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        .container::-webkit-scrollbar { width: 6px; }
        .container::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        .container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        .container::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.4); }

        @keyframes fadeInAnimation {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Logo */
        .logo { margin-bottom: 1.5rem; }
        .logo img { width: 80px; height: auto; }

        /* Kode Error (Tema Maintenance: Oranye/Emas) */
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
            background-image: linear-gradient(to right, #f6d365 0%, #fda085 100%);
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
            margin-bottom: 1.5rem;
            color: #d1d5db;
            line-height: 1.6;
            padding: 0 1rem;
        }

        /* Ikon Maintenance Berputar */
        .maintenance-icon {
            margin: 1.5rem 0;
            font-size: 4.5rem;
            color: #f6d365;
            display: inline-block;
            animation: spin 4s linear infinite;
            text-shadow: 0 0 20px rgba(246, 211, 101, 0.4);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Auto Refresh Countdown */
        .auto-refresh {
            margin: 1.5rem 0;
            padding: 0.8rem;
            background: rgba(246, 211, 101, 0.1);
            border: 1px solid rgba(246, 211, 101, 0.2);
            border-radius: 10px;
            font-size: 0.85rem;
            color: #e5e7eb;
        }

        .refresh-countdown {
            color: #f6d365;
            font-weight: 700;
            font-size: 1rem;
        }

        /* Tombol Aksi */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto 1.5rem auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, var(--hcis-amber-500), var(--hcis-amber-400));
            color: #1f2937;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(245, 158, 11, 0.6);
            cursor: pointer;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            min-height: 48px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px -5px rgba(245, 158, 11, 0.8);
            color: #000;
        }

        .btn-alt {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            box-shadow: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-alt:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .btn i { margin-right: 0.5rem; }

        /* Tips */
        .tips {
            padding: 1.2rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            text-align: left;
        }

        .tips h3 {
            margin-bottom: 0.8rem;
            color: #f6d365;
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .tips h3 i { margin-right: 0.5rem; }
        .tips ul { list-style-type: none; }
        .tips li {
            margin-bottom: 0.5rem;
            padding-left: 1.2rem;
            position: relative;
            font-size: 0.85rem;
            color: #d1d5db;
        }
        .tips li::before {
            content: '⚙';
            font-size: 0.7rem;
            color: #f6d365;
            position: absolute;
            left: 0;
            top: 2px;
        }

        /* Responsif */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 1.5rem; max-height: 95vh; }
            .error-code { font-size: 5rem; }
            .main-title { font-size: 1.5rem; }
            .description { font-size: 0.9rem; padding: 0; }
            .maintenance-icon { font-size: 3.5rem; }
            .btn { padding: 0.7rem 1.2rem; font-size: 0.85rem; }
        }

        @media (max-width: 480px) {
            .container { padding: 1.2rem; }
            .error-code { font-size: 4rem; }
            .main-title { font-size: 1.3rem; }
            .logo img { width: 70px; }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .container { max-height: 85vh; padding: 1rem; }
            .error-code { font-size: 3.5rem; margin-bottom: 0.3rem; }
            .main-title { font-size: 1.2rem; margin-bottom: 0.5rem; }
            .maintenance-icon { font-size: 2.5rem; margin: 0.5rem 0; }
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

        <div class="error-code">503</div>

        <h1 class="main-title">Sistem Sedang Dalam Perbaikan</h1>

        <p class="description">
            Kami sedang melakukan pemeliharaan rutin untuk meningkatkan performa dan layanan kami. Situs akan segera kembali normal dalam beberapa saat.
        </p>

        <div class="maintenance-icon">
            <i class="bi bi-gear-wide-connected"></i>
        </div>

        <!-- Auto Refresh Countdown -->
        <div class="auto-refresh">
            <i class="bi bi-hourglass-split"></i>
            Halaman akan memuat ulang dalam 
            <span class="refresh-countdown" id="countdown">60</span> detik
        </div>

        <!-- Tombol Aksi -->
        <div class="action-buttons">
            <a href="javascript:location.reload()" class="btn">
                <i class="bi bi-arrow-clockwise"></i> Coba Muat Ulang
            </a>
            <a href="/uDash" class="btn btn-alt">
                <i class="bi bi-house-door"></i> Kembali ke Beranda
            </a>
        </div>

        <!-- Tips -->
        <div class="tips">
            <h3><i class="bi bi-info-circle"></i> Informasi Tambahan:</h3>
            <ul>
                <li>Sistem sedang ditingkatkan ke versi terbaru</li>
                <li>Data Anda tetap aman selama proses ini berlangsung</li>
                <li>Silakan ambil jeda sejenak, kami akan segera kembali!</li>
            </ul>
        </div>
    </div>

    <script>
        // Membuat partikel dengan tema warna yang sesuai
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            if (!container) return;

            const particleCount = 200; // Dikurangi sedikit agar lebih rapi
            // Warna partikel disesuaikan dengan tema maintenance (kuning, oranye, putih)
            const colors = ["#f6d365", "#fda085", "#ffffff", "#4facfe"];

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                const size = Math.random() * 8 + 2;
                const duration = Math.random() * 6 + 4;
                const delay = 0;

                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.bottom = `-${size}px`;
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.opacity = Math.random() * 0.3 + 0.1;

                const animationName = Math.random() > 0.5 ? 'float' : 'float-alt';
                particle.style.animation = `${animationName} ${duration}s linear ${delay}s infinite`;

                container.appendChild(particle);
            }
        }

        // Auto refresh countdown (Diubah menjadi 60 detik untuk maintenance)
        let countdown = 60;
        const countdownElement = document.getElementById('countdown');
        const countdownInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(countdownInterval);
                location.reload();
            }
        }, 1000);

        // Mencegah scroll di body mobile
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