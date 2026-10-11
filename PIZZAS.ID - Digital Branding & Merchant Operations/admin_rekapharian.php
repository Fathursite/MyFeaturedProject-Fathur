<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: halamanawal.php");
    exit();
}
require 'config.php';

$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Fungsi untuk membersihkan input dan mencegah SQL Injection
function clean_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Gunakan fungsi clean_input untuk tanggal
$tanggal_bersih = clean_input($conn, $tanggal);

// Query total menu terjual & pendapatan
$query_summary = "
    SELECT
        SUM(qty) AS total_menu_terjual,
        SUM(harga * qty) AS pendapatan_kotor
    FROM rekap
    WHERE DATE(waktu) = '$tanggal_bersih'
";
$result_summary = mysqli_query($conn, $query_summary);
$data_summary = mysqli_fetch_assoc($result_summary);

$total_menu_terjual = isset($data_summary['total_menu_terjual']) ? $data_summary['total_menu_terjual'] : 0;
$pendapatan_kotor = isset($data_summary['pendapatan_kotor']) ? $data_summary['pendapatan_kotor'] : 0;
$service_charge = $pendapatan_kotor * 0.05;
$ppn = ($pendapatan_kotor + $service_charge) * 0.1;
$pendapatan_bersih = $pendapatan_kotor + $service_charge + $ppn;

// Query rincian menu terjual
$query_detail = "
    SELECT
        r.menu AS nama_menu,
        SUM(r.qty) AS jumlah_terjual,
        m.harga AS harga_satuan
    FROM rekap r
    JOIN menu m ON r.menu = m.judul
    WHERE DATE(r.waktu) = '$tanggal_bersih'
    GROUP BY r.menu, m.harga
    ORDER BY SUM(r.qty) DESC
";
$result_detail = mysqli_query($conn, $query_detail);
$rincian_menu = mysqli_fetch_all($result_detail, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Pizzas.id - Rekap Harian</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            max-width: 900px;
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
        h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .filter-form {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap; /* Agar responsif */
        }
        .filter-form label {
            font-weight: 500;
            font-size: 1rem;
            color: var(--dark-bg);
        }
        .filter-form input[type="date"] {
            padding: 10px 15px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .filter-form input[type="date"]:focus {
            border-color: var(--secondary-color);
        }
        .filter-form button {
            padding: 10px 20px;
            background-color: var(--secondary-color);
            color: var(--white-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .filter-form button:hover {
            background-color: #3BBFBA; /* Sedikit lebih gelap */
            transform: translateY(-2px);
        }

        .summary {
            margin-top: 30px;
        }
        .summary table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-soft);
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }
        .summary th, .summary td {
            padding: 18px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .summary th {
            background-color: var(--dark-bg);
            color: var(--white-color);
            font-weight: 600;
            font-size: 1rem;
        }
        .summary tr:last-child td {
            border-bottom: none;
        }
        .summary tr:first-child th:first-child { border-top-left-radius: var(--border-radius-soft); }
        .summary tr:first-child th:last-child { border-top-right-radius: var(--border-radius-soft); }
        .summary tr:last-child td:first-child { border-bottom-left-radius: var(--border-radius-soft); }
        .summary tr:last-child td:last-child { border-bottom-right-radius: var(--border-radius-soft); }

        .summary td {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-color);
        }
        .summary td:nth-child(2), .summary td:nth-child(3) { /* Kolom pendapatan */
            font-weight: 700;
            color: var(--primary-color);
        }
        .summary td:nth-child(3) {
            color: var(--secondary-color);
        }


        .link-button {
            background: none;
            border: none;
            color: var(--dark-bg); /* Warna link lebih konsisten */
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            font-size: inherit;
            font-family: inherit;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .link-button:hover {
            color: var(--secondary-color);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 101; /* Di atas navbar */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px); /* Efek blur */
        }
        .modal-content {
            background-color: var(--white-color);
            margin: 8% auto; /* Jarak dari atas dikurangi */
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            width: 90%; /* Lebih lebar */
            max-width: 700px; /* Batas lebar */
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 30px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .close-button:hover,
        .close-button:focus {
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }
        .modal-content h3 {
            text-align: center;
            color: var(--dark-bg);
            margin-bottom: 25px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
        }
        .modal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            border: 1px solid #c0c0c0; /* Border modal table */
            border-radius: var(--border-radius-soft);
            overflow: hidden;
            table-layout: fixed; /* Agar kolom konsisten */
        }
        .modal-table th, .modal-table td {
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: center;
            font-size: 0.95rem;
        }
        .modal-table th {
            background-color: #f0f0f0; /* Latar header modal */
            color: var(--dark-bg);
            font-weight: 600;
        }
        .modal-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .modal-table tbody tr:hover {
            background-color: #f0f0f0;
        }

        /* Media Queries */
        @media (max-width: 992px) {
            .container {
                margin: 30px auto;
                padding: 25px;
            }
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
                font-size: 0.95rem;
            }
            .logout-btn {
                width: calc(100% - 40px);
                text-align: center;
                justify-content: center;
            }
            .filter-form {
                flex-direction: column;
                gap: 10px;
            }
            .filter-form button {
                width: 100%;
            }
            .summary th, .summary td {
                padding: 12px;
                font-size: 0.9rem;
            }
            .summary td {
                font-size: 1rem;
            }
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
            .modal-table th, .modal-table td {
                padding: 10px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            h1, h2 {
                font-size: 1.8rem;
            }
            .filter-form input[type="date"], .filter-form button {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
            .summary th, .summary td {
                padding: 10px;
                font-size: 0.8rem;
            }
            .summary td {
                font-size: 0.9rem;
            }
            .link-button {
                font-size: 0.9em;
            }
            .modal-content {
                padding: 15px;
            }
            .modal-content h3 {
                font-size: 1.3rem;
            }
            .modal-table th, .modal-table td {
                padding: 8px;
                font-size: 0.75rem;
            }
        }
    </style>
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
        <h2><i class="fas fa-chart-line"></i> Rekap Harian</h2>
        <form class="filter-form" method="GET">
            <label for="tanggal">Pilih Tanggal:</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
            <button type="submit"><i class="fas fa-search"></i> Tampilkan</button>
        </form>

        <div class="summary">
            <table>
                <thead>
                    <tr>
                        <th>Jumlah Menu Terjual</th>
                        <th>Pendapatan Kotor <br>(Tanpa SC & PPN)</th>
                        <th>Pendapatan Bersih <br>(Include SC 5% + PPN 10%)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <button class="link-button" onclick="document.getElementById('detailModal').style.display='block'">
                                <i class="fas fa-info-circle"></i> <?= $total_menu_terjual ?> item
                            </button>
                        </td>
                        <td>Rp <?= number_format($pendapatan_kotor, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($pendapatan_bersih, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="document.getElementById('detailModal').style.display='none'">&times;</span>
            <h3>Rincian Menu Terjual - Tanggal <?= htmlspecialchars($tanggal) ?></h3>
            <?php if (!empty($rincian_menu)): ?>
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Nama Menu</th>
                            <th>Jumlah Terjual</th>
                            <th>Harga Satuan</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rincian_menu as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['nama_menu']) ?></td>
                                <td><?= htmlspecialchars($item['jumlah_terjual']) ?></td>
                                <td>Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($item['jumlah_terjual'] * $item['harga_satuan'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; margin-top: 20px; color: #666;">Tidak ada rincian menu terjual pada tanggal ini.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Fungsi untuk menutup modal
        var modal = document.getElementById("detailModal");
        var closeButton = document.getElementsByClassName("close-button")[0];

        closeButton.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>