<?php
session_start();
if (isset($_POST['login'])) {
    if ($_POST['password'] === '123') {
        $_SESSION['admin'] = true;
        header("Location: admin_editmenu.php");
        exit();
    } else {
        $login_error = "Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizzas.id - Lezatnya Pizza Modern</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B; /* Merah muda cerah */
            --secondary-color: #FFD166; /* Kuning pastel */
            --dark-text: #333;
            --light-text: #666;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.2);
            --border-radius-soft: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: var(--dark-text);
            background-image: url('background/halamanawal.png');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            position: relative;
            overflow: hidden;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.8);
            padding: 15px 30px;
            box-shadow: var(--shadow-light);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Modifikasi di sini */
        .navbar .navbar-left {
            display: flex; /* Membuat item di dalamnya sejajar horizontal */
            gap: 25px; /* Memberi jarak antar tombol */
            align-items: center;
        }

        .navbar .navbar-left a {
            color: white;
            text-decoration: none;
            /* margin-right: 25px; Hapus ini karena sudah ada gap */
            font-size: 1.1rem;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar .navbar-right a {
            color: white;
            text-decoration: none;
            margin-left: 25px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar a:hover {
            color: var(--secondary-color);
        }
        .navbar a i {
            font-size: 1.2em;
        }

        .container {
            position: relative;
            z-index: 2;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            padding: 20px;
            max-width: 90%;
            margin-top: 250px;
        }

        .buttons {
            display: flex;
            flex-direction: column;
            gap: 25px;
            align-items: center;
        }
        .btn {
            padding: 18px 45px;
            text-decoration: none;
            color: white;
            background-color: var(--primary-color);
            border: none;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            box-shadow: var(--shadow-medium);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn:hover {
            background-color: #E05C5C;
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
        }
        .btn:active {
            transform: translateY(-2px);
        }
        .btn i {
            font-size: 1.3em;
        }

        /* Modal Styling */
        .modal-overlay {
            display: none; /* Default tersembunyi */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease-out;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: white;
            padding: 40px;
            border-radius: var(--border-radius-soft);
            box-shadow: var(--shadow-medium);
            text-align: center;
            width: 90%;
            max-width: 400px;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease-out;
        }
        .modal-overlay.active .modal {
            transform: translateY(0);
            opacity: 1;
        }

        .modal h2 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 26px;
        }
        .modal input[type="password"] {
            width: calc(100% - 20px);
            padding: 14px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: border-color 0.3s ease;
        }
        .modal input[type="password"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        .modal button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
            margin: 0 10px;
        }
        .modal button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
        }
        .modal button[type="submit"]:hover {
            background-color: #E05C5C;
            transform: translateY(-2px);
        }
        .modal button[type="button"] {
            background-color: #6c757d;
            color: white;
        }
        .modal button[type="button"]:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .error-message {
            color: var(--primary-color);
            margin-top: 10px;
            font-weight: 500;
        }

        .footer {
            position: fixed; /* Mengubah posisi menjadi fixed */
            bottom: 0;       /* Menempel di bagian bawah */
            left: 0;         /* Menempel di kiri */
            right: 0;        /* Menempel di kanan (membuat lebar 100%) */
            
            z-index: 2;
            width: 100%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 15px 0;
            text-align: center;
            font-size: 0.95rem;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.1);
        }

        .footer-content {
            display: flex;
            justify-content: center;
            gap: 30px;
            align-items: center;
            flex-wrap: wrap;
        }

        .social-icon {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .social-icon:hover {
            color: var(--secondary-color);
        }

        .social-icon i {
            font-size: 1.6rem;
        }

        
    </style>
    <script>
        function openAdminModal() {
            document.getElementById('adminModalOverlay').classList.add('active');
        }

        function closeAdminModal() {
            document.getElementById('adminModalOverlay').classList.remove('active');
            document.getElementById('adminPassword').value = '';
            document.getElementById('loginError').innerText = '';
        }

        function validateLogin() {
            let password = document.getElementById("adminPassword").value;
            if (password === "") {
                document.getElementById("loginError").innerText = "Password tidak boleh kosong!";
                return false;
            }
            document.getElementById("loginError").innerText = "";
            return true;
        }

        window.onload = function() {
            <?php if (isset($login_error)): ?>
                openAdminModal();
                document.getElementById('loginError').innerText = "<?php echo $login_error; ?>";
            <?php endif; ?>
        };
    </script>
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <a href="halamanabout.php"><i class="fas fa-info-circle"></i> About</a>
            <a href="how_to.php"><i class="fas fa-question-circle"></i> How To</a>
        </div>
        <div class="navbar-right">
            <a href="#" onclick="openAdminModal();"><i class="fas fa-user-shield"></i> Admin</a>
        </div>
    </div>

    <div class="container">
        <div class="buttons">
            <a href="halamanmenu.php" class="btn"><i class="fas fa-utensils"></i> Order Now!!!</a>
        </div>
    </div>

    <div id="adminModalOverlay" class="modal-overlay">
        <div id="adminModalContent" class="modal">
            <h2><i class="fas fa-lock"></i> Akses Admin</h2>
            <form method="POST" action="" onsubmit="return validateLogin()">
                <input type="password" name="password" id="adminPassword" placeholder="Masukkan Password Anda" autocomplete="off">
                <div id="loginError" class="error-message"></div>
                <button type="submit" name="login"><i class="fas fa-sign-in-alt"></i> Login</button>
                <button type="button" onclick="closeAdminModal()"><i class="fas fa-times-circle"></i> Batal</button>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <a href="https://wa.me/6281380674078?text=Hallo%20Min%20Pizzas.id,%20Ada%20yang%20mau%20Aku%20Tanyakan%20nih..." target="_blank" class="social-icon">
                <i class="fab fa-whatsapp"></i>
                <span>081380674078</span>
            </a>
            <a href="https://www.instagram.com/pizzas.id?igsh=MWM0bms2NG9jOHBvdw==" target="_blank" class="social-icon">
                <i class="fab fa-instagram"></i>
                <span>@pizzas.id</span>
            </a>
        </div>
    </footer>
    <script src="./bootstrap-5.3.6-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>