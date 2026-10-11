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

// Aksi Konfirmasi Pembayaran
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['konfirmasi_bayar'])) {
    $pesanan_id = clean_input($conn, $_POST['id']);
    // Update status_pembayaran di database
    $update_query = "UPDATE pesanan SET status_pembayaran = 'Dibayar' WHERE id = '$pesanan_id'";
    mysqli_query($conn, $update_query);
    // Redirect untuk menghindari form resubmission
    header("Location: admin_pesananmasuk.php");
    exit();
}

// Aksi Hapus Pesanan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hapus'])) {
    $id = clean_input($conn, $_POST['id']);
    // Mulai transaksi untuk memastikan konsistensi data
    mysqli_begin_transaction($conn);
    try {
        // Pertama, hapus detail pesanan dari tabel 'rekap'
        mysqli_query($conn, "DELETE FROM rekap WHERE pesanan_id = '$id'");
        // Kemudian, hapus pesanan dari tabel 'pesanan'
        mysqli_query($conn, "DELETE FROM pesanan WHERE id = '$id'");
        mysqli_commit($conn); // Konfirmasi transaksi
        // Tidak ada file foto yang terkait langsung dengan pesanan, jadi tidak perlu unlink
        header("Location: admin_pesananmasuk.php"); // Redirect setelah hapus
        exit();
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn); // Rollback jika ada error
        die("Error menghapus pesanan: " . $e->getMessage());
    }
}

$query = "SELECT
            p.id,
            p.nama,
            p.no_meja,
            p.tipe,
            p.tanggal,
            p.status_pembayaran,
            GROUP_CONCAT(CONCAT(dp.menu, ' (', dp.qty, 'x)') SEPARATOR '<br>') AS daftar_menu,
            SUM(dp.harga * dp.qty) AS total
          FROM pesanan p
          JOIN rekap dp ON p.id = dp.pesanan_id
          GROUP BY p.id, p.nama, p.no_meja, p.tipe, p.tanggal, p.status_pembayaran
          ORDER BY p.tanggal DESC"; // Urutkan berdasarkan tanggal terbaru

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Pizzas.id - Pesanan Masuk</title>
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
            max-width: 1200px; /* Biarkan lebih lebar agar tabel lebih leluasa */
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

        table {
            width: 100%; /* Pastikan tabel mengisi lebar container */
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-soft);
            overflow: hidden;
            table-layout: fixed; /* Penting! Buat lebar kolom tetap */
        }
        table thead {
            background-color: var(--dark-bg);
            color: var(--white-color);
        }
        table th, table td {
            padding: 12px 8px; /* Padding sedikit dikurangi untuk kepadatan */
            text-align: center; /* **Perubahan disini untuk rata tengah** */
            vertical-align: middle;
            border-bottom: 1px solid #eee;
            word-wrap: break-word; /* Memastikan teks panjang bisa pecah baris */
            font-size: 0.85rem; /* Ukuran font standar untuk sel tabel */
        }
        table th {
            font-weight: 600;
            font-size: 0.9rem; /* Ukuran font standar untuk header tabel */
            white-space: normal; /* Izinkan teks wrapping di header */
        }
        /* Penyesuaian lebar kolom agar muat dalam satu tampilan */
        table th:nth-child(1), table td:nth-child(1) { width: 12%; } /* Nama Pelanggan */
        table th:nth-child(2), table td:nth-child(2) { width: 6%; } /* No. Meja */
        table th:nth-child(3), table td:nth-child(3) { width: 8%; } /* Tipe Pesanan */
        table th:nth-child(4), table td:nth-child(4) { width: 28%; } /* Daftar Menu (Lebih lebar) */
        table th:nth-child(5), table td:nth-child(5) { width: 9%; } /* Total */
        table th:nth-child(6), table td:nth-child(6) { width: 11%; } /* Total (PPN+SC) */
        table th:nth-child(7), table td:nth-child(7) { width: 7%; } /* Tanggal */
        table th:nth-child(8), table td:nth-child(8) { width: 7%; } /* Jam */
        table th:nth-child(9), table td:nth-child(9) { width: 8%; } /* Status Pembayaran */
        table th:nth-child(10), table td:nth-child(10) { width: 10%; } /* Aksi */

        /* Override text-align untuk kolom daftar menu dan nama pelanggan agar rata kiri */
        table td:nth-child(1), table td:nth-child(4) {
            text-align: left;
        }

        table th:first-child { border-top-left-radius: var(--border-radius-soft); }
        table th:last-child { border-top-right-radius: var(--border-radius-soft); }
        table tr:last-child td:first-child { border-bottom-left-radius: var(--border-radius-soft); }
        table tr:last-child td:last-child { border-bottom-right-radius: var(--border-radius-soft); }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        table tbody tr:hover {
            background-color: #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center; /* Rata tengah tombol aksi */
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: var(--white-color);
            font-weight: 500;
            width: 100%; /* Agar tombol mengisi lebar cell */
            justify-content: center; /* Rata tengah ikon dan teks di tombol */
            box-sizing: border-box; /* Pastikan padding tidak menambah lebar */
        }
        .btn i {
            font-size: 0.95em;
        }

        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #ffedcc;
            color: #e69d00;
        }
        .status-dibayar {
            background-color: #d4edda;
            color: #28a745;
        }

        /* Media Queries untuk memastikan tabel muat */
        @media (max-width: 1200px) {
            .container {
                max-width: 98%; /* Agak mengecilkan container untuk layar menengah */
                padding: 30px;
            }
            table th, table td {
                padding: 10px 7px; /* Sesuaikan padding */
                font-size: 0.82rem;
            }
            table th:nth-child(1), table td:nth-child(1) { width: 12%; }
            table th:nth-child(2), table td:nth-child(2) { width: 6%; }
            table th:nth-child(3), table td:nth-child(3) { width: 8%; }
            table th:nth-child(4), table td:nth-child(4) { width: 28%; } /* Tetap lebih lebar */
            table th:nth-child(5), table td:nth-child(5) { width: 9%; }
            table th:nth-child(6), table td:nth-child(6) { width: 11%; }
            table th:nth-child(7), table td:nth-child(7) { width: 7%; }
            table th:nth-child(8), table td:nth-child(8) { width: 7%; }
            table th:nth-child(9), table td:nth-child(9) { width: 8%; }
            table th:nth-child(10), table td:nth-child(10) { width: 10%; }
        }

        @media (max-width: 992px) {
            .container {
                margin: 30px auto;
                padding: 20px;
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
            }
            .logout-btn {
                width: calc(100% - 40px);
                text-align: center;
                justify-content: center;
            }
            /* Sesuaikan lebar kolom untuk layar tablet */
            table th, table td {
                padding: 8px 5px; /* Padding lebih kecil */
                font-size: 0.75rem; /* Ukuran font lebih kecil */
            }
            table th:nth-child(1), table td:nth-child(1) { width: 12%; }
            table th:nth-child(2), table td:nth-child(2) { width: 5%; } /* Meja lebih kecil */
            table th:nth-child(3), table td:nth-child(3) { width: 8%; }
            table th:nth-child(4), table td:nth-child(4) { width: 25%; } /* Daftar Menu lebih kecil lagi */
            table th:nth-child(5), table td:nth-child(5) { width: 9%; }
            table th:nth-child(6), table td:nth-child(6) { width: 10%; }
            table th:nth-child(7), table td:nth-child(7) { width: 7%; }
            table th:nth-child(8), table td:nth-child(8) { width: 7%; }
            table th:nth-child(9), table td:nth-child(9) { width: 8%; }
            table th:nth-child(10), table td:nth-child(10) { width: 12%; } /* Aksi sedikit diperlebar */

            .btn {
                padding: 6px 8px;
                font-size: 0.7rem;
                gap: 4px;
            }
            .status-badge {
                font-size: 0.65rem;
                padding: 3px 6px;
            }
            /* Untuk daftar menu agar tidak terlalu panjang */
            table td:nth-child(4) {
                line-height: 1.2;
            }
        }

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }
            h1, h2 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            table th, table td {
                padding: 6px 3px; /* Padding paling kecil */
                font-size: 0.65rem; /* Font paling kecil */
            }
            table th:nth-child(1), table td:nth-child(1) { width: 13%; } /* Nama Pelanggan */
            table th:nth-child(2), table td:nth-child(2) { width: 5%; } /* Meja */
            table th:nth-child(3), table td:nth-child(3) { width: 8%; } /* Tipe */
            table th:nth-child(4), table td:nth-child(4) { width: 22%; } /* Menu (Qty) */
            table th:nth-child(5), table td:nth-child(5) { width: 8%; } /* Total */
            table th:nth-child(6), table td:nth-child(6) { width: 10%; } /* Total (PPN+SC) */
            table th:nth-child(7), table td:nth-child(7) { width: 7%; } /* Tanggal */
            table th:nth-child(8), table td:nth-child(8) { width: 7%; } /* Jam */
            table th:nth-child(9), table td:nth-child(9) { width: 8%; } /* Status Pembayaran */
            table th:nth-child(10), table td:nth-child(10) { width: 12%; } /* Aksi */

            .btn {
                font-size: 0.6rem;
                padding: 4px 6px;
                gap: 2px;
            }
            .btn i {
                font-size: 0.8em;
            }
            .status-badge {
                font-size: 0.6rem;
                padding: 2px 4px;
            }
            /* Untuk daftar menu agar tidak terlalu panjang */
            table td:nth-child(4) {
                line-height: 1.1;
            }
        }
    </style>
    <script>
        function confirmDelete() {
            return confirm("Apakah Anda yakin ingin menghapus pesanan ini? Aksi ini tidak dapat dibatalkan dan akan menghapus semua detail pesanan!");
        }
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
        <h2><i class="fas fa-bell"></i> Pesanan Masuk</h2>

        <div class="order-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nama Pelanggan</th>
                        <th>Meja</th>
                        <th>Tipe</th>
                        <th>Menu (Qty)</th>
                        <th>Total</th>
                        <th>Total (Inc. PPN+SC)</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $tanggal = date('d-m-Y', strtotime($row['tanggal']));
                            $jam = date('H:i', strtotime($row['tanggal'])); // Jam lebih singkat
                            $total_pesanan_mentah = $row['total'];
                            // PPN 10% + Service Charge 5% = Total * 1.15
                            $ppn_sc_total = $total_pesanan_mentah * 1.15;

                            $status_class = ($row['status_pembayaran'] == 'Dibayar') ? 'status-dibayar' : 'status-pending';

                            echo "<tr>
                                <td>" . htmlspecialchars($row['nama']) . "</td>
                                <td>" . ($row['tipe'] === 'Dine In' ? htmlspecialchars($row['no_meja']) : '-') . "</td>
                                <td>" . htmlspecialchars($row['tipe']) . "</td>
                                <td>" . $row['daftar_menu'] . "</td>
                                <td>Rp " . number_format($total_pesanan_mentah, 0, ',', '.') . "</td>
                                <td>Rp " . number_format($ppn_sc_total, 0, ',', '.') . "</td>
                                <td>$tanggal</td>
                                <td>$jam</td>
                                <td><span class='status-badge {$status_class}'>" . htmlspecialchars($row['status_pembayaran']) . "</span></td>
                                <td>
                                    <div class='btn-group'>";

                            // Tampilkan tombol konfirmasi hanya jika status masih 'Pending'
                            if ($row['status_pembayaran'] == 'Pending') {
                                echo "
                                        <form method='POST'>
                                            <input type='hidden' name='id' value='{$row['id']}'>
                                            <button class='btn btn-success' type='submit' name='konfirmasi_bayar'><i class='fas fa-check-circle'></i> Bayar</button>
                                        </form>";
                            }
                            echo "
                                        <form method='POST' onsubmit='return confirmDelete();'>
                                            <input type='hidden' name='id' value='{$row['id']}'>
                                            <button class='btn btn-danger' type='submit' name='hapus'><i class='fas fa-trash-alt'></i> Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='10' style='text-align:center; padding: 20px;'>Belum ada pesanan masuk.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>