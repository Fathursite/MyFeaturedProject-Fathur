<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How To Order - Pizzas.id</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./bootstrap-5.3.6-dist/css/bootstrap.min.css">
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
            margin: 0;
            color: var(--dark-text);
            background-color: #f8f8f8;
            line-height: 1.6;
            padding-top: 80px; /* Ruang untuk navbar */
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
            justify-content: flex-start; /* Tombol Back di kiri */
            align-items: center;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 25px; /* Margin kanan untuk tombol "Back" */
        }
        .navbar a:hover {
            color: var(--secondary-color);
        }
        .navbar a i {
            font-size: 1.2em;
        }

        .how-to-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: var(--border-radius-soft);
            box-shadow: var(--shadow-light);
        }

        .how-to-container h1 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.8rem;
        }

        .steps-list {
            counter-reset: step-counter; /* Inisialisasi counter */
            list-style: none; /* Hapus bullet default */
            padding: 0;
        }

        .step-item {
            display: flex;
            align-items: flex-start; /* Item rata atas */
            margin-bottom: 25px;
            padding: 20px;
            background-color: #fefefe;
            border-left: 5px solid var(--primary-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .step-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            counter-increment: step-counter; /* Tambah counter */
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-right: 20px;
            line-height: 1; /* Pastikan angka tidak terlalu tinggi */
            flex-shrink: 0; /* Jangan biarkan angka menyusut */
            padding-right: 10px; /* Jarak antara nomor dan teks */
            border-right: 1px solid #eee; /* Garis pemisah */
        }

        .step-content {
            flex-grow: 1;
        }

        .step-content h4 {
            color: var(--dark-text);
            font-size: 1.3rem;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .step-content p {
            color: var(--light-text);
            font-size: 1rem;
            margin: 0;
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

        /* Media Queries untuk halaman How To */
        @media screen and (max-width: 768px) {
            .how-to-container {
                margin: 20px auto;
                padding: 20px;
            }
            .how-to-container h1 {
                font-size: 2.2rem;
            }
            .step-number {
                font-size: 2rem;
                margin-right: 15px;
            }
            .step-content h4 {
                font-size: 1.2rem;
            }
            .step-content p {
                font-size: 0.95rem;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding-top: 60px;
            }
            .navbar {
                padding: 10px 15px;
            }
            .how-to-container {
                padding: 15px;
            }
            .how-to-container h1 {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }
            .step-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 15px;
                border-left: none; /* Hapus border kiri di mobile kecil */
                border-bottom: 5px solid var(--primary-color); /* Ganti dengan border bawah */
            }
            .step-number {
                margin-right: 0;
                margin-bottom: 10px;
                padding-right: 0;
                border-right: none;
                font-size: 2.2rem;
            }
            .step-content h4 {
                font-size: 1.1rem;
            }
            .step-content p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="halamanawal.php"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>

    <div class="how-to-container">
        <h1>Cara Memesan di Pizzas.id</h1>
        <p style="text-align: center; margin-bottom: 30px; color: var(--dark-text);">Ikuti langkah-langkah mudah berikut untuk menikmati pizza favorit Anda!</p>

        <ol class="steps-list">
            <li class="step-item">
                <div class="step-number"></div>
                <div class="step-content">
                    <h4>Masukkan Nama & Pilihan Layanan</h4>
                    <p>Pada halaman awal, Anda akan diminta untuk memasukkan <strong>nama</strong> Anda dan memilih opsi <strong>Dine-in</strong> atau <strong>Take Away</strong>. Jika memilih Dine-in, isi nomor meja yang Anda inginkan.</p>
                </div>
            </li>
            <li class="step-item">
                <div class="step-number"></div>
                <div class="step-content">
                    <h4>Pilih Menu</h4>
                    <p>Jelajahi berbagai pilihan menu pizza dan minuman kami. Klik tanda plus (+) pada menu yang Anda inginkan untuk menambahkannya ke keranjang belanja Anda. Anda bisa menyesuaikan jumlah pesanan.</p>
                </div>
            </li>
            <li class="step-item">
                <div class="step-number"></div>
                <div class="step-content">
                    <h4>Konfirmasi Pesanan</h4>
                    <p>Setelah selesai memilih menu, cek kembali pesanan Anda di keranjang. Pastikan semua sudah sesuai, lalu klik tombol <strong>Konfirmasi Pesanan</strong>.</p>
                </div>
            </li>
            <li class="step-item">
                <div class="step-number"></div>
                <div class="step-content">
                    <h4>Menunggu Konfirmasi Waiter & Pembayaran</h4>
                    <p>Waiter kami akan segera menghampiri meja Anda (untuk Dine-in) atau menghampiri ke ruang tunggu khusus (untuk Take Away) dan mengkonfirmasi pesanan Anda. Setelah pesanan dikonfirmasi, Anda dapat melanjutkan ke proses pembayaran.</p>
                    <p>Pembayaran dapat dilakukan secara <strong>tunai (cash)</strong> maupun <strong>non-tunai (cashless)</strong> melalui berbagai metode seperti <strong>QRIS, e-wallet (OVO, GoPay, Dana, dll.), transfer bank, dan kartu debit/kredit.</strong></p>
                    <ul>
                        <li><i class="fas fa-qrcode"></i> <strong>Bayar Pakai QRIS:</strong> Scan QRIS untuk pembayaran digital yang cepat.</li>
                        <li><i class="fas fa-money-bill-wave"></i> <strong>Bayar Tunai/Cashless Lainnya:</strong> Informasikan ke waiter metode pembayaran yang Anda pilih.</li>
                        <li><i class="fas fa-print"></i> <strong>Cetak Struk:</strong> Dapatkan struk pesanan Anda sebagai bukti transaksi.</li>
                        <li><i class="fas fa-undo"></i> <strong>Batalkan dan Buat Pesanan Baru:</strong> Jika ada perubahan, Anda bisa membatalkan pesanan dan melakukan pemesanan kembali.</li>
                    </ul>
                    <p>Admin kami akan memberikan notifikasi jika pembayaran Anda berhasil.</p>
                </div>
            </li>
            <li class="step-item">
                <div class="step-number"></div>
                <div class="step-content">
                    <h4>Pesanan Anda Siap Dinikmati!</h4>
                    <p>Setelah pembayaran dikonfirmasi, pesanan Anda akan segera disiapkan. Tunggu sebentar dan pizza lezat Anda siap untuk dinikmati!</p>
                </div>
            </li>
        </ol>
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