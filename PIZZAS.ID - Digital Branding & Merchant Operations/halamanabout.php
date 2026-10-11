<?php
session_start();
$ratings = [
    "sangat_puas" => 85,
    "puas" => 10,
    "netral" => 3,
    "tidak_puas" => 2
];

// Tangani permintaan update rating (jika ada)
if (isset($_POST['action']) && $_POST['action'] === 'update_rating' && isset($_POST['type'])) {
    // Gunakan $ratings yang sudah didefinisikan di atas (secara statis)
    $currentRatings = $ratings; // Mengambil salinan nilai statis untuk manipulasi sementara
    $ratingType = $_POST['type'];

    // Validasi tipe rating dan pastikan pengguna belum merating dalam sesi ini
    if (array_key_exists($ratingType, $currentRatings) && !isset($_SESSION['has_rated'])) {
        $currentRatings[$ratingType]++;

        // Tandai bahwa pengguna sudah memberikan rating dalam sesi ini
        $_SESSION['has_rated'] = true;
        
        // Simpan tipe rating yang baru di sesi (opsional, untuk menandai rating terpilih di UI)
        $_SESSION['last_rating_type'] = $ratingType;

        // Normalisasi ulang persentase agar total tetap 100
        $totalVotes = array_sum($currentRatings);
        if ($totalVotes > 0) {
            foreach ($currentRatings as $key => $value) {
                $currentRatings[$key] = round(($value / $totalVotes) * 100);
            }
        }
        
        // ====== PERUBAHAN DIMULAI DI SINI ======
        // TIDAK ADA LAGI file_put_contents karena tidak ada penyimpanan ke file eksternal
        // Data $currentRatings hanya akan dikirim kembali ke JavaScript
        // ====== PERUBAHAN BERAKHIR DI SINI ======

        echo json_encode(['success' => true, 'new_ratings' => $currentRatings]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Anda sudah memberikan rating dalam sesi ini atau tipe rating tidak valid.']);
        exit();
    }
}

// Ambil rating terbaru saat halaman dimuat (akan selalu mengambil nilai statis awal)
// $ratings sudah didefinisikan di atas, jadi tidak perlu memanggil getRatings lagi
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Pizzas.id - Lezatnya Pizza Modern</title>
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
            padding-top: 80px;
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
            justify-content: flex-start;
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
            margin-right: 25px;
        }
        .navbar a:hover {
            color: var(--secondary-color);
        }
        .navbar a i {
            font-size: 1.2em;
        }

        .about-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: var(--border-radius-soft);
            box-shadow: var(--shadow-light);
        }

        .about-container h1, .about-container h2 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 25px;
            font-size: 2.5rem;
        }
        .about-container h2 {
            font-size: 2rem;
            margin-top: 40px;
        }

        .about-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .about-section:last-child {
            border-bottom: none;
        }

        .about-section h3 {
            color: var(--dark-text);
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .about-section p, .about-section ul, .about-section .content-text {
            color: var(--light-text);
            margin-bottom: 10px;
        }

        .about-section ul {
            list-style: none;
            padding: 0;
        }
        /* -- START PERUBAHAN CSS UNTUK MERAPIKAN MISI KAMI -- */
        .about-section ul li {
            margin-bottom: 15px; /* Jarak antar item misi */
            display: grid; /* Gunakan Grid untuk layout yang lebih rapi */
            grid-template-columns: auto 180px 1fr; /* Kolom: ikon, teks tebal, deskripsi */
            align-items: start; /* Item rata atas */
            gap: 10px; /* Jarak antar kolom */
        }
        .about-section ul li i {
            color: var(--primary-color);
            font-size: 1.2em; /* Ukuran ikon */
            margin-top: 4px; /* Penyesuaian vertikal agar ikon sejajar dengan teks */
            grid-column: 1; /* Posisikan ikon di kolom pertama */
        }
        .about-section ul li strong {
            display: inline-block; /* Pastikan strong tetap dalam baris */
            color: var(--dark-text); /* Warna teks tebal */
            grid-column: 2; /* Posisikan teks tebal di kolom kedua */
            font-weight: 600; /* Sedikit lebih tebal */
            line-height: 1.5; /* Tinggi baris untuk konsistensi */
        }
        .about-section ul li p {
            margin: 0; /* Hapus margin default paragraf di dalam li */
            grid-column: 3; /* Posisikan paragraf di kolom ketiga */
            color: var(--light-text);
            line-height: 1.5; /* Tinggi baris untuk konsistensi */
        }
        /* -- AKHIR PERUBAHAN CSS UNTUK MERAPIKAN MISI KAMI -- */


        .rating-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .rating-item {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
            color: var(--dark-text);
            width: 100%;
            max-width: 400px;
            justify-content: space-between;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .rating-item:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .rating-item.disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        .rating-item.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        .rating-item.selected .rating-fill {
            background-color: var(--secondary-color);
        }
        .rating-item.selected .emoji {
            filter: grayscale(0%);
        }

        .rating-item .emoji {
            font-size: 2.5rem;
            margin-right: 10px;
            pointer-events: none;
        }
        .rating-bar {
            flex-grow: 1;
            background-color: #eee;
            border-radius: 5px;
            height: 15px;
            overflow: hidden;
            position: relative;
        }
        .rating-fill {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 5px;
            transition: width 0.5s ease-out;
        }
        .rating-percentage {
            font-weight: 600;
            width: 60px;
            text-align: right;
        }

        /* Call to Action (CTA) */
        .cta-section {
            text-align: center;
            background-color: var(--secondary-color);
            color: var(--dark-text);
            padding: 30px;
            border-radius: var(--border-radius-soft);
            margin-top: 30px;
            box-shadow: var(--shadow-medium);
        }
        .cta-section h3 {
            color: var(--dark-text);
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        .cta-section .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .cta-section .btn:hover {
            background-color: #E05C5C;
            transform: translateY(-3px);
        }

        .footer {
            position: fixed; /* Mengubah posisi menjadi fixed */
            bottom: 0;      /* Menempel di bagian bawah */
            left: 0;        /* Menempel di kiri */
            right: 0;       /* Menempel di kanan (membuat lebar 100%) */
            
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

        /* Media Queries Responsiveness */
        @media screen and (max-width: 768px) {
            .about-container {
                margin: 20px auto;
                padding: 20px;
            }
            .about-container h1 {
                font-size: 2rem;
            }
            .about-container h2 {
                font-size: 1.7rem;
            }
            .about-section h3 {
                font-size: 1.2rem;
            }
            .rating-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .rating-item .emoji {
                align-self: center;
            }
            .rating-bar {
                width: 100%;
            }
            /* -- START PERUBAHAN MEDIA QUERY UNTUK MISI KAMI -- */
            .about-section ul li {
                grid-template-columns: auto 1fr; /* Ikon dan teks tebal di kolom 1, deskripsi di kolom 2 */
                gap: 10px 15px; /* Jarak vertikal dan horizontal */
            }
            .about-section ul li i {
                grid-column: 1;
                margin-top: 0; /* Hapus margin atas */
            }
            .about-section ul li strong {
                grid-column: 2; /* Pindah ke kolom kedua */
                min-width: unset; /* Hapus min-width */
            }
            .about-section ul li p {
                grid-column: 1 / span 2; /* Rentangkan deskripsi ke seluruh lebar (2 kolom) */
                margin-left: 30px; /* Beri indentasi agar sejajar dengan teks di atasnya */
            }
            /* -- AKHIR PERUBAHAN MEDIA QUERY UNTUK MISI KAMI -- */
        }

        @media screen and (max-width: 480px) {
            body {
                padding-top: 60px;
            }
            .navbar {
                padding: 10px 15px;
            }
            .about-container {
                padding: 15px;
            }
            .about-container h1 {
                font-size: 1.7rem;
            }
            .about-container h2 {
                font-size: 1.5rem;
            }
            .about-section h3 {
                font-size: 1.1rem;
            }
            .rating-item .emoji {
                font-size: 2rem;
            }
            .rating-percentage {
                width: auto;
                text-align: left;
            }
            /* Penyesuaian mobile yang lebih ketat */
            .about-section ul li {
                grid-template-columns: auto 1fr; /* Kembali ke 2 kolom: ikon dan teks tebal/deskripsi */
            }
            .about-section ul li strong {
                 grid-column: 2; /* Teks tebal tetap di kolom kedua */
                 display: inline; /* Biarkan inline */
            }
            .about-section ul li p {
                grid-column: 1 / span 2; /* Rentangkan ke seluruh lebar */
                margin-left: 0; /* Hapus indentasi */
                text-align: left; /* Rata kiri */
            }
            .about-section ul li:last-child {
                margin-bottom: 0; /* Hapus margin bawah pada item terakhir di mobile */
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="halamanawal.php"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>

    <div class="about-container">
        <h1>Tentang Pizzas.id</h1>
        <p>Selamat datang di Pizzas.id, surga bagi para pecinta pizza modern! Didirikan dengan semangat untuk menghadirkan cita rasa pizza yang tak terlupakan, kami berkomitmen untuk menyajikan kreasi pizza inovatif yang memadukan tradisi Italia dengan sentuhan modern. Setiap pizza kami dibuat dengan hati, menggunakan bahan-bahan pilihan terbaik dan resep rahasia yang telah disempurnakan.</p>

        <div class="about-section">
            <h3><i class="fas fa-map-marker-alt"></i> Lokasi Kami</h3>
            <p>Anda bisa menemukan kelezatan Pizzas.id di:</p>
            <p class="content-text"><strong>Jl. Alamanda 5 Taman Tridaya Indah Blok G15/12A Tambun Selatan Bekasi</strong></p>
            <p>Kami hadir untuk menjadi bagian dari momen spesial Anda, baik untuk santap di tempat atau dibawa pulang.</p>
        </div>

        <div class="about-section">
            <h3><i class="fas fa-calendar-alt"></i> Perjalanan Kami</h3>
            <p class="content-text">Pizzas.id pertama kali membuka pintunya pada tahun <strong>2018</strong>. Sejak itu, kami terus tumbuh dan berinovasi, berkat dukungan pelanggan setia kami. Kami bangga menjadi destinasi kuliner favorit di Bekasi dan sekitarnya.</p>
        </div>

        <div class="about-section">
            <h2>Visi & Misi</h2>
            <div class="content-text">
                <h3><i class="fas fa-eye"></i> Visi Kami</h3>
                <p>Menjadi restoran pizza terkemuka di Indonesia yang dikenal karena inovasi rasa, kualitas premium, dan pengalaman bersantap yang luar biasa bagi setiap pelanggan.</p>

                <h3><i class="fas fa-bullseye"></i> Misi Kami</h3>
                <ul>
                    <li><i class="fas fa-star-of-life"></i> <strong>Kualitas Tak Tertandingi:</strong> <p>Menggunakan bahan-bahan segar, alami, dan berkualitas tinggi untuk setiap produk.</p></li>
                    <li><i class="fas fa-lightbulb"></i> <strong>Inovasi Berkelanjutan:</strong> <p>Terus menciptakan resep dan varian pizza baru yang unik dan menarik.</p></li>
                    <li><i class="fas fa-smile"></i> <strong>Kepuasan Pelanggan:</strong> <p>Memberikan pelayanan yang ramah, cepat, dan profesional, serta menciptakan suasana restoran yang nyaman dan menyenangkan.</p></li>
                    <li><i class="fas fa-leaf"></i> <strong>Tanggung Jawab Komunitas:</strong> <p>Berkontribusi positif terhadap masyarakat lokal melalui program dan inisiatif yang berkelanjutan.</p></li>
                </ul>
            </div>
        </div>
        
        <div class="about-section">
            <h3><i class="fas fa-star-half-alt"></i> Rating Pelanggan</h3>
            <p>Pendapat Anda sangat berharga bagi kami. Klik emoji di bawah untuk memberikan rating Anda:</p>
            <div class="rating-section">
                <div class="rating-item" data-rating-type="sangat_puas">
                    <span class="emoji">😄</span> Sangat Puas
                    <div class="rating-bar">
                        <div class="rating-fill" style="width: <?php echo $ratings['sangat_puas']; ?>%;"></div>
                    </div>
                    <span class="rating-percentage"><?php echo $ratings['sangat_puas']; ?>%</span>
                </div>
                <div class="rating-item" data-rating-type="puas">
                    <span class="emoji">😊</span> Puas
                    <div class="rating-bar">
                        <div class="rating-fill" style="width: <?php echo $ratings['puas']; ?>%;"></div>
                    </div>
                    <span class="rating-percentage"><?php echo $ratings['puas']; ?>%</span>
                </div>
                <div class="rating-item" data-rating-type="netral">
                    <span class="emoji">😐</span> Netral
                    <div class="rating-bar">
                        <div class="rating-fill" style="width: <?php echo $ratings['netral']; ?>%;"></div>
                    </div>
                    <span class="rating-percentage"><?php echo $ratings['netral']; ?>%</span>
                </div>
                <div class="rating-item" data-rating-type="tidak_puas">
                    <span class="emoji">😕</span> Tidak Puas
                    <div class="rating-bar">
                        <div class="rating-fill" style="width: <?php echo $ratings['tidak_puas']; ?>%;"></div>
                    </div>
                    <span class="rating-percentage"><?php echo $ratings['tidak_puas']; ?>%</span>
                </div>
            </div>
        </div>

        <div class="about-section">
            <h3><i class="fas fa-pizza-slice"></i> Filosofi Pizza Kami</h3>
            <ul>
                <li><i class="fas fa-check-circle"></i> <strong>Bahan Baku Premium:</strong> <p>Kami hanya menggunakan tepung berkualitas, saus tomat segar, keju mozzarella asli, dan topping pilihan.</p></li>
                <li><i class="fas fa-check-circle"></i> <strong>Adonan Rahasia:</strong> <p>Adonan pizza kami dibuat setiap hari dengan resep turun-temurun yang menghasilkan tekstur renyah di luar dan lembut di dalam, untuk setiap gigitan yang sempurna.</p></li>
                <li><i class="fas fa-check-circle"></i> <strong>Inovasi Rasa:</strong> <p>Kami terus bereksperimen untuk menciptakan kombinasi rasa baru yang unik dan menarik, di samping menu klasik favorit yang tak lekang oleh waktu.</p></li>
                <li><i class="fas fa-check-circle"></i> <strong>Pelayanan Ramah:</strong> <p>Staf kami selalu siap melayani Anda dengan senyum dan memastikan pengalaman bersantap Anda menyenangkan dan berkesan.</p></li>
            </ul>
        </div>

        <div class="cta-section">
            <h3>Siap Menikmati Pizza Lezat Kami?</h3>
            <p>Pesan sekarang dan rasakan perbedaan Pizzas.id!</p>
            <a href="halamanmenu.php" class="btn"><i class="fas fa-pizza-slice"></i> Pesan Sekarang!</a>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ratingItems = document.querySelectorAll('.rating-item');
            
            // Cek apakah ada rating terakhir yang disimpan di localStorage
            const lastRatedType = localStorage.getItem('last_rating_type');
            if (lastRatedType) {
                // Tandai rating yang terakhir dipilih
                const selectedItem = document.querySelector(`.rating-item[data-rating-type="${lastRatedType}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('selected');
                }
                // Nonaktifkan klik setelah rating diberikan sekali
                ratingItems.forEach(item => item.classList.add('disabled')); // Tambahkan kelas disabled
            }

            ratingItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Hanya izinkan klik jika belum ada rating yang diberikan dalam sesi browser ini
                    if (!localStorage.getItem('last_rating_type')) {
                        const ratingType = this.dataset.ratingType; // Ambil tipe rating dari data-attribute

                        // Kirim data ke server (PHP) menggunakan Fetch API
                        fetch('halamanabout.php', { // Pastikan ini mengarah ke file ini sendiri
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=update_rating&type=${ratingType}`
                        })
                        .then(response => {
                            if (!response.ok) {
                                console.error('Network response was not ok', response.statusText);
                                return response.text().then(text => { throw new Error('Server error: ' + text); });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Update tampilan persentase di halaman
                                for (const type in data.new_ratings) {
                                    const percentage = data.new_ratings[type];
                                    const itemElement = document.querySelector(`.rating-item[data-rating-type="${type}"]`);
                                    if (itemElement) {
                                        itemElement.querySelector('.rating-fill').style.width = percentage + '%';
                                        itemElement.querySelector('.rating-percentage').textContent = percentage + '%';
                                    }
                                }

                                // Hapus class 'selected' dari yang sebelumnya (jika ada)
                                document.querySelectorAll('.rating-item.selected').forEach(el => el.classList.remove('selected'));
                                // Tambahkan class 'selected' ke item yang baru diklik
                                this.classList.add('selected');

                                // Simpan rating yang diberikan ke localStorage agar tidak bisa merating lagi
                                localStorage.setItem('last_rating_type', ratingType);
                                
                                // Nonaktifkan klik setelah rating diberikan
                                ratingItems.forEach(item => item.classList.add('disabled')); // Tambahkan kelas disabled
                                alert('Terima kasih atas rating Anda!');
                            } else {
                                alert('Gagal mengupdate rating: ' + (data.message || 'Pesan tidak diketahui.'));
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            alert('Terjadi kesalahan saat mengupdate rating. Detail di konsol.');
                        });
                    } else {
                        alert('Anda sudah memberikan rating. Hanya satu rating per sesi browser.');
                    }
                });
            });
        });
    </script>
    <script src="./bootstrap-5.3.6-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>