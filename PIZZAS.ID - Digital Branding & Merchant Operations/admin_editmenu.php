<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: halamanawal.php");
    exit();
}
require 'config.php';

// Fungsi untuk membersihkan input dan mencegah SQL Injection
function clean_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Tambah Menu
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah'])) {
    $judul = clean_input($conn, $_POST['judul']);
    $deskripsi = clean_input($conn, $_POST['deskripsi']);
    $harga = $_POST['harga']; // Ambil harga mentah dulu
    $jenis = clean_input($conn, $_POST['jenis']);
    $stok = intval($_POST['stok']);

    // Validasi Harga: Pastikan hanya angka
    if (!preg_match('/^[0-9]+$/', $harga)) {
        echo "<script>alert('Tidak boleh ada karakter selain angka pada harga menu!');</script>";
    } else {
        $harga_bersih = clean_input($conn, $harga); // Sanitasi harga setelah validasi
        
        $foto = $_FILES['foto']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($foto);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Cek jika file sudah ada
        if (file_exists($target_file)) {
            echo "<script>alert('Maaf, file foto sudah ada.');</script>";
            $uploadOk = 0;
        }

        // Batasi ukuran file (misal: 5MB)
        if ($_FILES["foto"]["size"] > 5000000) {
            echo "<script>alert('Maaf, ukuran file foto terlalu besar (maks 5MB).');</script>";
            $uploadOk = 0;
        }

        // Izinkan format file tertentu
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
            echo "<script>alert('Maaf, hanya file JPG, JPEG, PNG & GIF yang diizinkan.');</script>";
            $uploadOk = 0;
        }

        // Cek jika $uploadOk adalah 0 karena ada error
        if ($uploadOk == 0) {
            echo "<script>alert('Maaf, file foto Anda tidak diunggah.');</script>";
        } else {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $sql = "INSERT INTO menu (judul, deskripsi, harga, jenis, stok, foto)
                                    VALUES ('$judul', '$deskripsi', '$harga_bersih', '$jenis', '$stok', '$foto')";
                if (mysqli_query($conn, $sql)) {
                    // Berhasil ditambahkan
                } else {
                    echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                echo "<script>alert('Maaf, terjadi kesalahan saat mengunggah file foto.');</script>";
            }
        }
    }
}

// Hapus Menu
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hapus'])) {
    $id = clean_input($conn, $_POST['id']);
    $result = mysqli_query($conn, "SELECT foto FROM menu WHERE id = '$id'");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $file_path = "uploads/" . $row['foto'];
        mysqli_query($conn, "DELETE FROM menu WHERE id = '$id'");
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}

// Tambah Stok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_stok'])) {
    $id = intval($_POST['id']);
    $tambah_stok = intval($_POST['tambah_stok']);

    // Pastikan tambah_stok tidak negatif
    if ($tambah_stok < 0) {
        echo "<script>alert('Jumlah stok yang ditambahkan tidak boleh negatif!');</script>";
    } else {
        // Ambil stok saat ini dari database
        $getStokQuery = mysqli_query($conn, "SELECT stok FROM menu WHERE id = $id");
        if ($getStokQuery && mysqli_num_rows($getStokQuery) > 0) {
            $stokSaatIni = mysqli_fetch_assoc($getStokQuery)['stok'];
            // Hitung stok baru
            $stokBaru = $stokSaatIni + $tambah_stok;
            // Update stok di database
            mysqli_query($conn, "UPDATE menu SET stok = $stokBaru WHERE id = $id");
        }
    }
}

// Ambil data menu setelah semua operasi
$menus_query = "SELECT * FROM menu ORDER BY jenis ASC, judul ASC"; // Urutkan berdasarkan jenis dan judul
$menus = mysqli_query($conn, $menus_query);
if (!$menus) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Pizzas.id - Edit Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./bootstrap-5.3.6-dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B; /* Merah muda cerah */
            --secondary-color: #4ECDC4; /* Hijau tosca */
            --dark-bg: #2c3e50; /* Biru gelap */
            --light-bg: #f4f7f6; /* Abu-abu terang */
            --text-color: #333;
            --white-color: #fff;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
            --border-radius-soft: 10px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light-bg);
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: var(--text-color);
        }

        .navbar {
            background-color: var(--dark-bg);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar .nav-links a {
            color: var(--white-color);
            text-decoration: none;
            margin-right: 25px;
            font-weight: 500;
            font-size: 1.05rem;
            transition: color 0.3s ease;
            display: inline-flex; /* Agar ikon dan teks sejajar */
            align-items: center;
            gap: 8px; /* Jarak antara ikon dan teks */
        }
        .navbar .nav-links a:hover {
            color: var(--secondary-color);
        }
        .navbar .nav-links a i {
            font-size: 1.1em;
        }
        .logout-btn {
            color: var(--white-color);
            text-decoration: none;
            background-color: var(--primary-color);
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .logout-btn:hover {
            background-color: #E05C5C;
            transform: translateY(-2px);
        }
        .logout-btn i {
            font-size: 1.1em;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            background: var(--white-color);
            padding: 35px;
            border-radius: var(--border-radius-soft);
            box-shadow: var(--shadow-medium);
        }

        h1, h2 {
            font-family: 'Montserrat', sans-serif;
            text-align: center;
            color: var(--dark-bg);
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* Form Tambah Menu */
        .form-section {
            background-color: #e8f5e9; /* Hijau muda */
            padding: 25px;
            border-radius: var(--border-radius-soft);
            margin-bottom: 40px;
            border: 1px solid #c8e6c9;
            box-shadow: var(--shadow-light);
        }
        .form-section h2 {
            color: #2e7d32; /* Hijau tua */
            margin-top: 0;
            font-size: 1.8rem;
        }
        form input[type="text"],
        form textarea,
        form input[type="number"],
        form select,
        form input[type="file"] {
            width: calc(100% - 22px); /* Mengakomodasi padding */
            margin-bottom: 15px;
            padding: 12px;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box; /* Pastikan padding tidak menambah lebar total */
            transition: border-color 0.3s ease;
        }
        form input[type="text"]:focus,
        form textarea:focus,
        form input[type="number"]:focus,
        form select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        form textarea {
            resize: vertical; /* Hanya izinkan resize vertikal */
            min-height: 80px;
        }
        form button[type="submit"] {
            background-color: var(--primary-color);
            color: var(--white-color);
            padding: 12px 25px;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        form button[type="submit"]:hover {
            background-color: #E05C5C;
            transform: translateY(-2px);
        }

        /* Daftar Menu */
        .menu-list-section h2 {
            color: var(--dark-bg);
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .menu-category {
            margin-bottom: 30px;
            border-left: 5px solid var(--secondary-color);
            padding-left: 15px;
            background-color: #eef7f6; /* Warna latar belakang kategori */
            border-radius: var(--border-radius-soft);
            padding-top: 15px;
            padding-bottom: 15px;
        }
        .menu-category h3 {
            font-family: 'Montserrat', sans-serif;
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--secondary-color);
            font-size: 1.6rem;
            border-bottom: 2px solid var(--secondary-color);
            display: inline-block;
            padding-bottom: 5px;
        }
        .menu-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: background-color 0.2s ease;
        }
        .menu-item:last-child {
            border-bottom: none; /* Hapus border bawah pada item terakhir */
        }
        .menu-item:hover {
            background-color: #fcfcfc;
        }
        .menu-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius-soft);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex-shrink: 0; /* Agar gambar tidak mengecil */
        }
        .menu-text {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .menu-text h3 {
            margin: 0;
            font-size: 1.3rem;
            color: var(--dark-bg);
            font-weight: 600;
        }
        .menu-text p {
            margin: 5px 0;
            color: var(--light-text);
            font-size: 0.95rem;
        }
        .menu-text p strong {
            color: var(--primary-color);
            font-weight: 700;
        }
        .menu-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0; /* Agar aksi tidak mengecil */
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .delete-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .stok-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        .stok-form input[type="number"] {
            width: 80px; /* Lebar input stok */
            padding: 8px;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 0; /* Hapus margin-bottom default */
            flex-grow: 0; /* Jangan biarkan tumbuh */
        }
        .stok-form button {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .stok-form button:hover {
            background-color: #3aa69e;
            transform: translateY(-2px);
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
            }
            .navbar .nav-links {
                margin-bottom: 15px;
                text-align: center;
                width: 100%;
            }
            .navbar .nav-links a {
                margin: 0 10px 10px 10px;
            }
            .logout-btn {
                width: calc(100% - 40px);
                text-align: center;
                justify-content: center;
            }
            .container {
                margin: 20px auto;
                padding: 20px;
            }
            .form-section {
                padding: 15px;
            }
            form input[type="text"],
            form textarea,
            form input[type="number"],
            form select,
            form input[type="file"] {
                width: calc(100% - 20px);
                padding: 10px;
            }
            form button[type="submit"] {
                width: 100%;
                justify-content: center;
            }
            .menu-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 10px 0;
            }
            .menu-item img {
                width: 80px;
                height: 80px;
            }
            .menu-text h3 {
                font-size: 1.15rem;
            }
            .menu-text p {
                font-size: 0.9rem;
            }
            .menu-actions {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 10px;
            }
            .stok-form {
                flex-direction: column;
                gap: 5px;
                width: 100%;
                align-items: stretch;
            }
            .stok-form input[type="number"] {
                width: 100%; /* Lebarkan input stok di mobile */
                box-sizing: border-box;
            }
            .stok-form button, .delete-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .navbar .nav-links a {
                font-size: 0.9rem;
                margin: 0 5px 8px 5px;
            }
            .logout-btn {
                padding: 8px 15px;
                font-size: 0.95rem;
            }
            .container {
                padding: 15px;
            }
            h1, h2 {
                font-size: 1.6rem;
            }
            .form-section h2, .menu-list-section h2 {
                font-size: 1.5rem;
            }
            .menu-category h3 {
                font-size: 1.4rem;
            }
            .menu-item img {
                width: 70px;
                height: 70px;
            }
        }
    </style>
    <script>
        // Validasi harga (hanya angka) secara real-time
        function validateHarga(event) {
            const input = event.target;
            const value = input.value;
            // Hapus karakter selain angka
            const cleanValue = value.replace(/[^0-9]/g, '');
            
            if (value !== cleanValue) {
                // Tampilkan notifikasi jika ada karakter non-angka
                alert("Tidak boleh ada karakter selain angka pada harga menu!");
                input.value = cleanValue; // Hapus karakter non-angka dari input
            }
        }

        // Pastikan input stok hanya angka non-negatif
        function validateStokInput(event) {
            const input = event.target;
            const value = input.value;
            const cleanValue = value.replace(/[^0-9]/g, ''); // Hapus karakter non-angka
            
            if (value !== cleanValue) {
                alert("Jumlah stok harus berupa angka positif!");
                input.value = cleanValue;
            }
            if (parseInt(cleanValue) < 0) {
                alert("Jumlah stok tidak boleh negatif!");
                input.value = 0; // Atur ke 0 jika negatif
            }
        }

        // Konfirmasi penghapusan menu
        function confirmDelete() {
            return confirm("Apakah Anda yakin ingin menghapus menu ini? Aksi ini tidak dapat dibatalkan.");
        }

        // Tambahkan event listener saat DOM sudah dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const hargaInput = document.querySelector('form input[name="harga"]');
            if (hargaInput) {
                hargaInput.addEventListener('input', validateHarga);
            }

            const stokInputs = document.querySelectorAll('.stok-form input[name="tambah_stok"]');
            stokInputs.forEach(input => {
                input.addEventListener('input', validateStokInput);
            });

            // Tambahkan event listener untuk konfirmasi hapus
            const deleteForms = document.querySelectorAll('.menu-item form:last-child');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!confirmDelete()) {
                        event.preventDefault(); // Batalkan submit jika pengguna membatalkan
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_editmenu.php"><i class="fas fa-edit"></i> Edit Menu</a>
            <a href="admin_pesananmasuk.php"><i class="fas fa-clipboard-list"></i> Pesanan Masuk</a>
            <a href="admin_rekapharian.php"><i class="fas fa-chart-line"></i> Rekap Harian</a>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <h1>Manajemen Menu Pizzas.id</h1>

        <div class="form-section">
            <h2><i class="fas fa-plus-circle"></i> Tambah Menu Baru</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="judul" placeholder="Judul Menu (cth: Pizza Margherita)" required>
                <textarea name="deskripsi" placeholder="Deskripsi Menu (cth: Saus tomat, keju mozzarella)" required></textarea>
                <input type="number" name="harga" placeholder="Harga (hanya angka, cth: 20000)" min="0" required>
                <input type="number" name="stok" placeholder="Stok Awal" min="0" required>
                <select name="jenis" required>
                    <option value="">-- Pilih Kategori Menu --</option>
                    <option value="Makanan">Makanan</option>
                    <option value="Minuman">Minuman</option>
                    <option value="Hidangan Penutup">Hidangan Penutup</option>
                </select>
                <input type="file" name="foto" accept="image/*" required>
                <button type="submit" name="tambah"><i class="fas fa-plus"></i> Tambah Menu</button>
            </form>
        </div>

        <div class="menu-list-section">
            <h2><i class="fas fa-pizza-slice"></i> Daftar Menu</h2>
            <?php
            // Kumpulkan semua menu ke dalam array untuk pengelompokan
            $all_menus = [];
            mysqli_data_seek($menus, 0); // Pastikan pointer direset
            while ($row = mysqli_fetch_assoc($menus)) {
                $all_menus[] = $row;
            }

            $kategori = ['Makanan', 'Minuman', 'Hidangan Penutup'];
            foreach ($kategori as $k) {
                $found_in_category = false;
                foreach ($all_menus as $row) {
                    if ($row['jenis'] == $k) {
                        if (!$found_in_category) {
                            echo "<div class='menu-category'><h3>" . htmlspecialchars($k) . "</h3>";
                            $found_in_category = true;
                        }
                        echo "
                            <div class='menu-item'>
                                <img src='uploads/" . htmlspecialchars($row['foto']) . "' alt='" . htmlspecialchars($row['judul']) . "'>
                                <div class='menu-text'>
                                    <h3>" . htmlspecialchars($row['judul']) . "</h3>
                                    <p>" . htmlspecialchars($row['deskripsi']) . "</p>
                                    <p><strong>Harga: Rp " . number_format($row['harga'], 0, ',', '.') . "</strong></p>
                                    <p>Stok: <strong>" . intval($row['stok']) . "</strong></p>
                                </div>
                                <div class='menu-actions'>
                                    <form method='POST' class='stok-form'>
                                        <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
                                        <input type='number' name='tambah_stok' min='1' placeholder='Tambah' required>
                                        <button type='submit' name='update_stok'><i class='fas fa-plus'></i> Stok</button>
                                    </form>
                                    <form method='POST'>
                                        <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
                                        <button type='submit' name='hapus' class='delete-btn'><i class='fas fa-trash-alt'></i> Hapus</button>
                                    </form>
                                </div>
                            </div>
                        ";
                    }
                }
                if ($found_in_category) {
                    echo "</div>"; // Tutup div.menu-category jika ada item di dalamnya
                }
            }
            ?>
        </div>
    </div>
    <script src="./bootstrap-5.3.6-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>