<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #E0F4FF, #FFE5F1);
            overflow: hidden;
        }

        .container {
            text-align: center;
            padding: 2rem;
        }

        .logo {
            width: 300px;
            height: 300px;
            margin-bottom: 3rem;
            animation: fadeInDown 1.5s;
        }

        .login-btn {
            padding: 1rem 3rem;
            font-size: 1.2rem;
            color: #fff;
            background: linear-gradient(45deg, #FFB3B3, #FFDBA4);
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            animation: fadeInUp 1.5s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            background: linear-gradient(45deg, #FFB3B3, #FFE5F1);
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            z-index: -1;
            animation: float 6s infinite ease-in-out;
        }

        .blob-1 {
            width: 300px;
            height: 300px;
            background: rgba(255, 182, 193, 0.3);
            top: -150px;
            left: -150px;
        }

        .blob-2 {
            width: 250px;
            height: 250px;
            background: rgba(173, 216, 230, 0.3);
            bottom: -100px;
            right: -100px;
            animation-delay: -3s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="container">
        <img src="{{ asset('logo/logo.png') }}" alt="Logo" class="logo animate__animated animate__fadeInDown">
        <div class="row">
                <a href="/ticket">
                    <button class="login-btn animate__animated animate__fadeInUp">
                        Create Ticket
                    </button>
                </a>
    </div>
</body>
</html> 
