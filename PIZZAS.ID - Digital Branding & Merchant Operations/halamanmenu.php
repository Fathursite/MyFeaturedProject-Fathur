<?php
require 'config.php';
$menus = mysqli_query($conn, "SELECT * FROM menu");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        echo json_encode(["message" => "Data tidak valid!"]);
        exit;
    }

    $name = mysqli_real_escape_string($conn, $data['name']);
    $table = isset($data['table']) ? intval($data['table']) : null;
    $dine = mysqli_real_escape_string($conn, $data['dine']);
    $cart = $data['cart'];

    // Memeriksa stok setiap menu
    foreach ($cart as $menu => $qty) {
        $menu_escaped = mysqli_real_escape_string($conn, $menu);
        $result = mysqli_query($conn, "SELECT stok FROM menu WHERE judul = '$menu_escaped'");
        $row = mysqli_fetch_assoc($result);
        if (!$row || $row['stok'] < $qty) {
            echo json_encode(["message" => "Stok untuk $menu tidak mencukupi."]);
            exit;
        }
    }

    // Menyimpan data pesanan
    // Default status_pembayaran adalah 'Pending'
    $sql = "INSERT INTO pesanan (nama, no_meja, tipe, status_pembayaran) VALUES (?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $name, $table, $dine);
    $stmt->execute();
    $pesanan_id = $stmt->insert_id;
    $stmt->close();

    $total_bayar = 0; // Variabel untuk menghitung total bayar

    // Menyimpan detail pesanan dan mengurangi stok
    foreach ($cart as $menu => $qty) {
        $menu_escaped = mysqli_real_escape_string($conn, $menu);
        $qty = intval($qty);
        $result = mysqli_query($conn, "SELECT harga, stok FROM menu WHERE judul = '$menu_escaped'");
        $row = mysqli_fetch_assoc($result);
        $harga = $row ? intval($row['harga']) : 0;
        $sisa = $row['stok'] - $qty;

        // Menambahkan detail pesanan ke dalam tabel rekap
        $stmt = $conn->prepare("INSERT INTO rekap (pesanan_id, menu, qty, harga) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $pesanan_id, $menu, $qty, $harga);
        $stmt->execute();
        $stmt->close();

        // Mengurangi stok menu
        mysqli_query($conn, "UPDATE menu SET stok = $sisa WHERE judul = '$menu_escaped'");

        // Menghitung total bayar setelah service charge
        $harga_plus_sc = $harga + ($harga * 0.05);
        $total_bayar += $harga_plus_sc * $qty;
    }

    // Menambahkan PPN 10%
    $ppn = $total_bayar * 0.1;
    $total_bayar += $ppn;

    // Mengembalikan response dengan total bayar
    echo json_encode([
        "message" => "Pesanan berhasil disimpan!",
        "total_bayar" => number_format($total_bayar, 0, ',', '.'),
        "raw_total_bayar" => $total_bayar, // Tambahkan total bayar tanpa format untuk digunakan JS
        "pesanan_id" => $pesanan_id // Tambahkan ID pesanan ke response
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu PIZZAS.ID</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="./bootstrap-5.3.6-dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #4CAF50; /* Hijau segar */
            --secondary-color: #FFC107; /* Kuning cerah */
            --dark-text: #333;
            --light-text: #666;
            --bg-light: #f8f9fa; /* Latar belakang sangat terang */
            --bg-dark: #e9ecef; /* Latar belakang agak gelap */
            --border-color: #dee2e6;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
            margin: 0;
            padding: 0;
            color: var(--dark-text);
            line-height: 1.6;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: var(--dark-text);
            padding: 15px 30px;
            text-align: right;
            box-shadow: var(--shadow-light);
            z-index: 1000;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 25px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .navbar a:hover {
            color: var(--secondary-color);
        }

        .container {
            max-width: 1200px;
            margin: 100px auto 100px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            overflow: hidden;
        }
        .container h2 {
            font-family: 'Montserrat', sans-serif;
            text-align: center;
            margin-bottom: 40px;
            font-size: 36px;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 15px;
        }
        .container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--secondary-color);
            border-radius: 2px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 20px 0;
        }
        .menu-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--border-color);
        }
        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }
        .menu-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .menu-card h3 {
            font-family: 'Montserrat', sans-serif;
            margin: 0 0 8px;
            font-size: 22px;
            color: var(--dark-text);
        }
        .menu-card p {
            font-size: 15px;
            color: var(--light-text);
            margin: 5px 0 15px;
        }
        .menu-card .price-info {
            margin-bottom: 15px;
        }
        .menu-card .price-info strong {
            font-size: 20px;
            color: var(--primary-color);
            display: block;
            margin-bottom: 5px;
        }
        .menu-card .price-info .sub-text {
            font-size: 0.9em;
            color: var(--light-text);
        }
        .menu-card .stock-info {
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        .menu-card .stock-info.available {
            color: var(--primary-color);
        }
        .menu-card .stock-info.out-of-stock {
            color: #dc3545;
            font-weight: 600;
        }

        .qty-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }
        .qty-btn {
            width: 40px;
            height: 40px;
            font-size: 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .qty-btn:hover:not(:disabled) {
            background-color: #43a047;
            transform: scale(1.05);
        }
        .qty-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .cart-tab {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            z-index: 900;
            transition: transform 0.3s ease-out;
            transform: translateY(100%);
        }
        .cart-tab.active {
            transform: translateY(0%);
        }
        .cart-items {
        /* Untuk mengaktifkan scrollbar di sisi kiri */
        direction: rtl; /* Mengubah arah teks dan elemen menjadi Right-to-Left */
        text-align: left; /* Mengembalikan perataan teks ke kiri agar tetap terbaca */

        /* Pengaturan untuk tampilan vertikal dan scroll */
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 110px; /* Atur tinggi maksimum sesuai kebutuhan */
        overflow-y: auto; /* Aktifkan scroll vertikal */
        flex: 1;
        padding-right: 5px; /* Sesuaikan padding */
        }

        /* Tambahan: pastikan item di dalamnya tidak ikut terbalik */
        .cart-item {
        direction: ltr; /* Mengembalikan arah teks dan elemen ke Left-to-Right untuk setiap item */
        background: var(--bg-dark);
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 0.95em;
        white-space: normal;
        border: 1px solid var(--border-color);
        }

        .checkout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.1s ease;
            white-space: nowrap;
        }
        .checkout-btn:hover {
            background-color: #43a047;
            transform: translateY(-2px);
        }
        .checkout-btn i {
            margin-right: 8px;
        }


        /* Order Form & Popups (General Styling) */
        #orderForm,
        #paymentConfirmationPopup,
        #qrisPopup,
        #waitingForConfirmation,
        #successConfirmation {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            z-index: 9999;
            text-align: center;
            max-width: 450px;
            width: 90%;
            transition: all 0.3s ease-out;
            opacity: 0;
            visibility: hidden;
            transform: translate(-50%, -45%) scale(0.95);
        }

        /* Khusus untuk #cartDetailPopup agar bisa di-scroll */
        #cartDetailPopup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            z-index: 9999;
            text-align: center;
            max-width: 450px;
            width: 90%;
            transition: all 0.3s ease-out;
            opacity: 0;
            visibility: hidden;
            transform: translate(-50%, -45%) scale(0.95);

            max-height: 80vh; /* Batasi tinggi maksimum popup */
            overflow-y: auto; /* Aktifkan vertical scrollbar untuk seluruh popup */
        }


        #orderForm.active,
        #cartDetailPopup.active,
        #paymentConfirmationPopup.active,
        #qrisPopup.active,
        #waitingForConfirmation.active,
        #successConfirmation.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }


        #orderForm h3,
        #cartDetailPopup h3,
        #paymentConfirmationPopup h3,
        #qrisPopup h3,
        #waitingForConfirmation h3,
        #successConfirmation h3 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 24px;
        }
        #orderForm input[type="text"],
        #orderForm input[type="number"] {
            width: calc(100% - 20px);
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        #orderForm input[type="text"]:focus,
        #orderForm input[type="number"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        #orderForm label {
            display: inline-block;
            margin: 0 15px 20px 0;
            font-size: 1.05rem;
            cursor: pointer;
        }
        #orderForm input[type="radio"] {
            margin-right: 5px;
            accent-color: var(--primary-color);
        }
        #orderForm button {
            padding: 12px 30px;
            border: none;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        #orderForm button:hover {
            background-color: #43a047;
            transform: translateY(-2px);
        }
        #table.fade {
            background-color: var(--bg-dark);
            color: var(--light-text);
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        #cartDetailPopup .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--bg-light);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        #cartDetailPopup .detail-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        #cartDetailPopup .detail-item strong {
            font-size: 1.1em;
            color: var(--dark-text);
        }
        #cartDetailPopup #totalBayar {
            margin-top: 25px; /* Kembali ke nilai asli */
            font-size: 1.2em;
            line-height: 1.5;
            color: var(--light-text);
        }
        #cartDetailPopup #totalBayar strong {
            font-size: 1.4em;
            color: var(--primary-color);
        }
        #cartDetailPopup .popup-buttons {
            margin-top: 30px; /* Kembali ke nilai asli */
            display: flex;
            justify-content: space-around;
            gap: 15px;
        }
        #cartDetailPopup .popup-buttons button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
            flex-grow: 1;
        }
        #cartDetailPopup .popup-buttons .close-btn {
            background:#dc3545;
            color:white;
        }
        #cartDetailPopup .popup-buttons .close-btn:hover {
            background:#c82333;
            transform: translateY(-2px);
        }
        #cartDetailPopup .popup-buttons .confirm-btn {
            background:var(--primary-color);
            color:white;
        }
        #cartDetailPopup .popup-buttons .confirm-btn:hover {
            background:#43a047;
            transform: translateY(-2px);
        }

        /* Payment Confirmation Popup */
        #paymentConfirmationPopup {
            background-color: #e6ffe6;
            border: 2px solid var(--primary-color);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }
        #paymentConfirmationPopup h3 {
            color: var(--primary-color);
        }
        #paymentConfirmationPopup p {
            color: var(--light-text);
        }
        #paymentConfirmationPopup .close-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 16px;
        }
        #paymentConfirmationPopup .close-btn:hover {
            background-color: #43a047;
        }

        /* QRIS Popup */
        #qrisPopup {
            background: white;
            border: 1px solid var(--border-color);
        }
        #qrisPopup img {
            width: 100%;
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        #qrisPopup #totalBayarQris {
            font-size: 1.8em;
            color: var(--primary-color);
            margin-top: 15px;
        }
        #qrisPopup .close-qris-btn {
            background: #007bff;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
        }
        #qrisPopup .close-qris-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        /* Waiting / Success Confirmation */
        #waitingForConfirmation,
        #successConfirmation {
            background-color: #fff;
            border: 1px solid var(--border-color);
        }
        #waitingForConfirmation .icon,
        #successConfirmation .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        #waitingForConfirmation .icon { color: #f0ad4e; }
        #successConfirmation .icon { color: var(--primary-color); }
        #waitingForConfirmation h3, #successConfirmation h3 {
            color: var(--dark-text);
            font-size: 26px;
        }
        #waitingForConfirmation p, #successConfirmation p {
            font-size: 1.05em;
            color: var(--light-text);
            margin-bottom: 20px;
        }
        #successConfirmation p strong {
            color: var(--primary-color);
            font-size: 1.2em;
        }
        #successConfirmation .button-group {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 25px;
        }
        #successConfirmation .button-group button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        #successConfirmation .button-group #cetakStrukButton {
            background: #007bff;
            color: white;
        }
        #successConfirmation .button-group #cetakStrukButton:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        #successConfirmation .button-group #qrisPaymentButton {
            background: #6f42c1;
            color: white;
        }
        #successConfirmation .button-group #qrisPaymentButton:hover {
            background: #5a36a0;
            transform: translateY(-2px);
        }
        #successConfirmation .button-group #cancelOrderFromSuccess {
            background: #dc3545;
            color: white;
        }
        #successConfirmation .button-group #cancelOrderFromSuccess:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        #waitingForConfirmation button {
            padding: 12px 25px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        #waitingForConfirmation button:hover {
            background: #c82333;
            transform: translateY(-2px);
        }


        
    </style>
    <script>
        let cart = {};
        // menuLookup akan menyimpan data menu asli dari database
        let menuLookup = {};
        // menuStokSementara akan menyimpan stok yang terpengaruh oleh penambahan/pengurangan di UI
        let menuStokSementara = {}; 

        let currentPesananId = null;
        let statusCheckInterval = null;
        let currentTotalBayar = 0;
        let lastActivePopup = null;

        function togglePopup(popupId, show) {
            const popup = document.getElementById(popupId);
            if (popup) {
                if (show) {
                    popup.classList.add('active');
                    const overlay = document.createElement('div');
                    overlay.id = 'popup-overlay';
                    overlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5);
                        z-index: 9998;
                        transition: opacity 0.3s ease;
                        opacity: 0;
                    `;
                    document.body.appendChild(overlay);
                    setTimeout(() => overlay.style.opacity = '1', 10);

                    if (popupId !== 'orderForm') {
                        overlay.addEventListener('click', () => {
                            togglePopup(popupId, false);
                            if (popupId === 'qrisPopup') {
                                togglePopup('cartDetailPopup', true);
                            }
                        });
                    }
                } else {
                    popup.classList.remove('active');
                    const overlay = document.getElementById('popup-overlay');
                    if (overlay) {
                        overlay.style.opacity = '0';
                        setTimeout(() => overlay.remove(), 300);
                    }
                }
            }
        }

        function toggleCartTab(show) {
            const cartTab = document.querySelector('.cart-tab');
            if (cartTab) {
                if (show) {
                    cartTab.classList.add('active');
                } else {
                    cartTab.classList.remove('active');
                }
            }
        }

        // Fungsi untuk memperbarui tampilan stok di kartu menu
        function updateMenuItemStockDisplay(itemTitle) {
            const card = document.querySelector(`.menu-item h3[data-menu-title="${itemTitle}"]`).closest('.menu-card');
            if (!card) return;

            const stockInfoElement = card.querySelector('.stock-info');
            const minusBtn = card.querySelector('.qty-btn:first-of-type');
            const plusBtn = card.querySelector('.qty-btn:last-of-type');
            
            const currentStock = menuStokSementara[itemTitle];
            const currentQtyInCart = cart[itemTitle] || 0;

            if (currentStock !== undefined) { // Pastikan stok sementara ada
                stockInfoElement.textContent = `Stok: ${currentStock} tersedia`;
                if (currentStock <= 0) {
                    stockInfoElement.classList.remove('available');
                    stockInfoElement.classList.add('out-of-stock');
                    stockInfoElement.textContent = 'Stok: Habis';
                } else {
                    stockInfoElement.classList.remove('out-of-stock');
                    stockInfoElement.classList.add('available');
                }

                // Nonaktifkan tombol plus jika stok habis atau sudah di keranjang sebanyak stok yang tersedia
                if (plusBtn) {
                    plusBtn.disabled = currentStock <= 0;
                }
                // Nonaktifkan tombol minus jika item belum ada di keranjang
                if (minusBtn) {
                    minusBtn.disabled = currentQtyInCart === 0;
                }
            }
        }


        function increaseQty(item) {
            // Gunakan menuStokSementara untuk cek stok
            const currentStock = menuStokSementara[item] || 0;
            const currentQtyInCart = cart[item] || 0;

            if (currentStock > 0) { // Pastikan masih ada stok yang bisa diambil
                if (!cart[item]) cart[item] = 0;
                cart[item]++;
                menuStokSementara[item]--; // Kurangi stok sementara

                renderCart();
                updateMenuItemStockDisplay(item); // Perbarui tampilan stok di kartu menu
            } else {
                alert(`Stok untuk ${item} tidak mencukupi! Tersisa 0.`);
            }
        }

        function decreaseQty(item) {
            if (cart[item] && cart[item] > 0) {
                cart[item]--;
                menuStokSementara[item]++; // Kembalikan stok sementara
                if (cart[item] <= 0) {
                    delete cart[item];
                }
                renderCart();
                updateMenuItemStockDisplay(item); // Perbarui tampilan stok di kartu menu
            }
        }

        function renderCart() {
            const container = document.getElementById('cartItems');
            container.innerHTML = '';
            let totalHarga = 0;

            for (const item in cart) {
                if (menuLookup[item]) {
                    const qty = cart[item];
                    const hargaDasar = menuLookup[item].harga;
                    const hargaSC = hargaDasar + (hargaDasar * 0.05);
                    const subtotal = hargaSC * qty;
                    totalHarga += subtotal;

                    const div = document.createElement('div');
                    div.className = 'cart-item';
                    div.innerHTML = `
                        <strong>${item}</strong> (${qty}) - Rp ${subtotal.toLocaleString('id-ID')}
                    `;
                    container.appendChild(div);
                }
            }
            toggleCartTab(Object.keys(cart).length > 0);
        }


        function showCartDetails() {
            const content = document.getElementById('cartDetailContent');
            content.innerHTML = '';
            for (const item in cart) {
                if (menuLookup[item]) {
                    const hargaDasar = menuLookup[item].harga;
                    const hargaPlusSC = hargaDasar + (hargaDasar * 0.05);
                    const detail = document.createElement('div');
                    detail.className = 'detail-item';
                    detail.innerHTML = `
                        <img src="uploads/${menuLookup[item].foto}" alt="${item}">
                        <div>
                            <strong>${item}</strong><br>
                            Rp ${hargaPlusSC.toLocaleString('id-ID')} x ${cart[item]}
                        </div>
                    `;
                    content.appendChild(detail);
                }
            }
            updateTotalBayar();
            togglePopup('cartDetailPopup', true);
        }


        function updateTotalBayar() {
            let subtotal = 0;
            for (const item in cart) {
                if (menuLookup[item]) {
                    const harga = menuLookup[item].harga;
                    const hargaPlusSC = harga + (harga * 0.05);
                    subtotal += hargaPlusSC * cart[item];
                }
            }

            const ppn = subtotal * 0.1;
            const total = subtotal + ppn;
            currentTotalBayar = total;

            document.getElementById('totalBayar').innerHTML = `
                Subtotal + SC: Rp ${subtotal.toLocaleString('id-ID')}<br>
                PPN (10%): Rp ${ppn.toLocaleString('id-ID')}<br>
                <strong>Total Bayar: Rp ${total.toLocaleString('id-ID')}</strong>
            `;
        }

        async function konfirmasiPesanan() {
            const name = document.getElementById('name').value;
            const table = document.getElementById('table').value;
            const dine = document.querySelector('input[name="dine"]:checked').value;

            if (Object.keys(cart).length === 0) {
                alert('Keranjang belanja Anda kosong. Silakan pilih menu terlebih dahulu.');
                return;
            }

            const data = {
                name,
                table,
                dine,
                cart
            };

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const response = await res.json();

                if (response.message.includes("Stok")) {
                    alert(response.message);
                    return;
                }

                alert(response.message);

                if (response.pesanan_id) {
                    currentPesananId = response.pesanan_id;
                    localStorage.setItem('currentPesananId', currentPesananId);
                    currentTotalBayar = response.raw_total_bayar;
                    startStatusCheck();
                }

                // Reset cart dan stok sementara setelah pesanan berhasil
                cart = {};
                // Reset menuStokSementara ke nilai asli dari menuLookup
                for (const item in menuLookup) {
                    menuStokSementara[item] = menuLookup[item].stok;
                }
                renderCart();
                // Perbarui semua tampilan stok di menu
                document.querySelectorAll('.menu-item h3').forEach(element => {
                    updateMenuItemStockDisplay(element.dataset.menuTitle);
                });
                togglePopup('cartDetailPopup', false);

                const successTab = document.createElement('div');
                successTab.id = 'successConfirmation';
                successTab.innerHTML = `
                    <div class="icon">&#10004;</div>
                    <h3>Pesanan Anda Sudah Tersimpan!</h3>
                    <p>Mohon menunggu pelayan kami yang akan menghampirimu untuk Konfirmasi Pesananmu</p>
                    <p><strong>Total yang harus Anda Bayar: Rp ${response.total_bayar}</strong></p>
                    <p>Pembayaran dapat dilakukan secara tunai (cash) maupun non-tunai (cashless) melalui berbagai metode seperti e-wallet, transfer bank, dan kartu debit/kredit.<p>
                    <div class="button-group">
                        <button id="cetakStrukButton"><i class="fas fa-print"></i> Cetak Struk</button>
                        <button id="qrisPaymentButton"><i class="fas fa-qrcode"></i> Bayar Pakai QRIS</button>
                        <button id="cancelOrderFromSuccess"><i class="fas fa-times-circle"></i> Batalkan & Buat Pesanan Baru</button>
                    </div>
                `;

                document.body.appendChild(successTab);
                setTimeout(() => togglePopup('successConfirmation', true), 10);

                const cetakStrukButton = successTab.querySelector('#cetakStrukButton');
                if (cetakStrukButton) {
                    cetakStrukButton.addEventListener('click', () => {
                        cetakStruk(response.pesanan_id);
                    });
                }

                const qrisPaymentButton = successTab.querySelector('#qrisPaymentButton');
                if (qrisPaymentButton) {
                    qrisPaymentButton.addEventListener('click', () => {
                        lastActivePopup = 'successConfirmation';
                        showQrisPopup(response.raw_total_bayar);
                    });
                }

                const cancelOrderFromSuccessButton = successTab.querySelector('#cancelOrderFromSuccess');
                if (cancelOrderFromSuccessButton) {
                    cancelOrderFromSuccessButton.addEventListener('click', async () => {
                        if (confirm('Anda yakin ingin membatalkan pesanan ini? Pesanan akan dihapus dari sistem.')) {
                            try {
                                const deleteResponse = await fetch('batalkan_pesanan.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `pesanan_id=${currentPesananId}`
                                });
                                const deleteData = await deleteResponse.json();

                                if (deleteData.success) {
                                    alert(deleteData.message);
                                    localStorage.removeItem('currentPesananId');
                                    if (statusCheckInterval) {
                                        clearInterval(statusCheckInterval);
                                        statusCheckInterval = null;
                                    }
                                    togglePopup('successConfirmation', false);
                                    const existingSuccessTab = document.getElementById('successConfirmation');
                                    if (existingSuccessTab) {
                                        existingSuccessTab.remove();
                                    }
                                    window.location.reload();
                                } else {
                                    alert('Terjadi kesalahan: ' + deleteData.message);
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('Terjadi kesalahan saat membatalkan pesanan.');
                            }
                        }
                    });
                }

            } catch (err) {
                alert("Gagal menyimpan pesanan!");
                console.error(err);
            }
        }

        function handleDineChange() {
            const dineRadio = document.querySelector('input[name="dine"]:checked');
            const tableInput = document.getElementById('table');
            if (dineRadio && dineRadio.value === 'Take Away') {
                tableInput.value = '';
                tableInput.classList.add('fade');
                tableInput.removeAttribute('required');
            } else {
                tableInput.classList.remove('fade');
                tableInput.setAttribute('required', 'required');
            }
        }

        function submitForm() {
            const name = document.getElementById('name').value;
            const table = document.getElementById('table').value;
            const dine = document.querySelector('input[name="dine"]:checked');

            if (!name || (!table && dine.value !== 'Take Away') || !dine) {
                alert("Semua field harus diisi!");
                return false;
            }

            togglePopup('orderForm', false);
            document.querySelectorAll('.menu-card').forEach(e => e.style.display = 'flex');
            return false;
        }

        function cetakStruk(pesananId) {
            window.open(`cetak_struk.php?pesanan_id=${pesananId}`, '_blank');
        }

        function showPaymentConfirmationPopup() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
            localStorage.removeItem('currentPesananId');

            togglePopup('waitingForConfirmation', false);
            togglePopup('successConfirmation', false);
            togglePopup('qrisPopup', false);

            togglePopup('paymentConfirmationPopup', true);

            const closeBtn = document.getElementById('paymentConfirmationPopup').querySelector('.close-btn');
            if (closeBtn) {
                closeBtn.onclick = () => {
                    togglePopup('paymentConfirmationPopup', false);
                    window.location.href = 'halamanawal.php';
                };
            }
        }

        async function checkPaymentStatus() {
            if (!currentPesananId) {
                if (statusCheckInterval) {
                    clearInterval(statusCheckInterval);
                    statusCheckInterval = null;
                }
                return;
            }

            try {
                const response = await fetch(`cek_status_pesanan.php?pesanan_id=${currentPesananId}`);
                const data = await response.json();

                if (data.status_pembayaran === 'Dibayar') {
                    showPaymentConfirmationPopup();
                }
            } catch (error) {
                console.error('Error checking payment status:', error);
            }
        }

        function startStatusCheck() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
            statusCheckInterval = setInterval(checkPaymentStatus, 5000);
            console.log("Polling status pembayaran dimulai untuk ID:", currentPesananId);
        }

        function showQrisPopup(totalAmount) {
            const totalBayarQris = document.getElementById('totalBayarQris');
            totalBayarQris.textContent = `Rp ${totalAmount.toLocaleString('id-ID')}`;

            const activePopups = document.querySelectorAll('.active');
            activePopups.forEach(popup => {
                if (popup.id !== 'orderForm') {
                    popup.classList.remove('active');
                }
            });

            togglePopup('qrisPopup', true);

            const closeQrisButton = document.getElementById('qrisPopup').querySelector('.close-qris-btn');
            if (closeQrisButton) {
                closeQrisButton.onclick = () => {
                    togglePopup('qrisPopup', false);
                    if (lastActivePopup) {
                        togglePopup(lastActivePopup, true);
                    }
                    lastActivePopup = null;
                };
            }
        }

        window.onload = () => {
            // Inisialisasi menuLookup dan menuStokSementara dari data PHP
            <?php
            $result_all_menus_js = mysqli_query($conn, "SELECT judul, harga, stok, foto FROM menu");
            while ($row_js = mysqli_fetch_assoc($result_all_menus_js)) {
                echo "menuLookup[\"" . htmlspecialchars($row_js['judul']) . "\"] = { harga: " . $row_js['harga'] . ", stok: " . $row_js['stok'] . ", foto: \"" . htmlspecialchars($row_js['foto']) . "\" };\n";
                echo "menuStokSementara[\"" . htmlspecialchars($row_js['judul']) . "\"] = " . $row_js['stok'] . ";\n";
            }
            ?>

            handleDineChange(); // Ensure dine-in/take-away state is correct on load

            const storedPesananId = localStorage.getItem('currentPesananId');
            if (storedPesananId) {
                currentPesananId = storedPesananId;
                togglePopup('orderForm', false); // Close order form if a session exists

                const waitingMessage = document.createElement('div');
                waitingMessage.id = 'waitingForConfirmation';
                waitingMessage.innerHTML = `
                    <div class="icon">&#9203;</div>
                    <h3>Menunggu Konfirmasi Pembayaran...</h3>
                    <p>Pesanan Anda sedang diproses. Silakan tunggu konfirmasi dari admin.</p>
                    <p>Setelah pembayaran dikonfirmasi, notifikasi akan muncul secara otomatis.</p>
                    <button id="cancelOrderFromWaiting"><i class="fas fa-times-circle"></i> Batalkan & Buat Pesanan Baru</button>
                `;
                document.body.appendChild(waitingMessage);
                setTimeout(() => togglePopup('waitingForConfirmation', true), 10);

                const cancelOrderFromWaitingButton = document.getElementById('cancelOrderFromWaiting');
                if (cancelOrderFromWaitingButton) {
                    cancelOrderFromWaitingButton.addEventListener('click', async () => {
                        if (confirm('Anda yakin ingin membatalkan pesanan ini? Pesanan akan dihapus dari sistem.')) {
                            try {
                                const response = await fetch('batalkan_pesanan.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `pesanan_id=${currentPesananId}`
                                });
                                const data = await response.json();

                                if (data.success) {
                                    alert(data.message);
                                    localStorage.removeItem('currentPesananId');
                                    if (statusCheckInterval) {
                                        clearInterval(statusCheckInterval);
                                        statusCheckInterval = null;
                                    }
                                    togglePopup('waitingForConfirmation', false);
                                    const existingWaitingMessage = document.getElementById('waitingForConfirmation');
                                    if (existingWaitingMessage) {
                                        existingWaitingMessage.remove();
                                    }
                                    window.location.reload();
                                } else {
                                    alert('Terjadi kesalahan: ' + data.message);
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('Terjadi kesalahan saat membatalkan pesanan.');
                            }
                        }
                    });
                }
                startStatusCheck();
            } else {
                togglePopup('orderForm', true); // Show order form if no session
            }
        };
    </script>
</head>
<body>
    <div class="navbar">
        <a href="halamanawal.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>

    <form id="orderForm" onsubmit="return submitForm()">
        <h3><i class="fas fa-user-circle"></i> Data Pemesan</h3>
        <input type="text" id="name" placeholder="Nama Anda" required><br>
        <input type="number" id="table" placeholder="Nomor Meja" required><br>
        <div style="margin-bottom: 20px;">
            <label><input type="radio" name="dine" value="Dine In" onchange="handleDineChange()" required> Dine In</label>
            <label><input type="radio" name="dine" value="Take Away" onchange="handleDineChange()"> Take Away</label>
        </div>
        <button type="submit"><i class="fas fa-play-circle"></i> Mulai Pesan</button>
    </form>

    <div class="container">
        <h2>Pilihan Menu Terbaik Kami</h2>
        <?php
        $menus_grouped = [
            'Makanan' => [],
            'Minuman' => [],
            'Hidangan Penutup' => []
        ];

        // Ambil semua menu dari database sekali untuk ditampilkan dan diinisialisasi di JS
        $result_all_menus = mysqli_query($conn, "SELECT * FROM menu ORDER BY jenis");
        while ($row_all_menus = mysqli_fetch_assoc($result_all_menus)) {
            $jenis = $row_all_menus['jenis'];
            if (isset($menus_grouped[$jenis])) {
                $menus_grouped[$jenis][] = $row_all_menus;
            }
        }
        ?>

        <?php foreach ($menus_grouped as $jenis => $items): ?>
            <h3 style="margin-top: 40px; color: var(--dark-text); font-family: 'Montserrat', sans-serif; font-size: 24px; text-align: center; margin-bottom: 25px;"><?= htmlspecialchars($jenis) ?></h3>
            <div class="menu-grid">
                <?php foreach ($items as $row): ?>
                    <?php
                    $stok_habis_class = ($row['stok'] <= 0) ? 'out-of-stock' : 'available';
                    $stok_text = ($row['stok'] > 0) ? htmlspecialchars($row['stok']) . ' tersedia' : 'Habis';
                    $disabled_buttons = ($row['stok'] <= 0) ? 'disabled' : '';
                    ?>
                    <div class="menu-card menu-item">
                        <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" alt="<?= htmlspecialchars($row['judul']) ?>">
                        <h3 data-menu-title="<?= htmlspecialchars($row['judul']) ?>"><?= htmlspecialchars($row['judul']) ?></h3>
                        <p><?= htmlspecialchars($row['deskripsi']) ?></p>
                        <div class="price-info">
                            <strong>Rp <?= number_format($row['harga'], 0, ',', '.') ?></strong>
                            <span class="sub-text">+ SC (5%): Rp <?= number_format($row['harga'] + ($row['harga'] * 0.05), 0, ',', '.') ?></span>
                        </div>
                        <p class="stock-info <?= $stok_habis_class ?>">
                            Stok: <span class="stock-value"><?= $stok_text ?></span>
                        </p>
                        <div class="qty-controls">
                            <button class="qty-btn" onclick="decreaseQty('<?= htmlspecialchars($row['judul']) ?>')" <?= $disabled_buttons ?> >-</button>
                            <button class="qty-btn" onclick="increaseQty('<?= htmlspecialchars($row['judul']) ?>')" <?= $disabled_buttons ?> >+</button>
                        </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="cart-tab">
        <div style="flex: 1;">
            <strong>Keranjang Anda:</strong>
            <div id="cartItems" class="cart-items"></div>
        </div>
        <button class="checkout-btn" onclick="showCartDetails()"><i class="fas fa-shopping-cart"></i> Lihat Keranjang</button>
    </div>

    <div id="cartDetailPopup">
        <h3><i class="fas fa-shopping-basket"></i> Detail Keranjang</h3>
        <div id="cartDetailContent"></div>
        <h4 id="totalBayar" style="margin-top: 20px;">Total Bayar: Rp 0</h4>
        <div class="popup-buttons">
            <button class="close-btn" onclick="togglePopup('cartDetailPopup', false)"><i class="fas fa-times"></i> Tutup</button>
            <button class="confirm-btn" onclick="konfirmasiPesanan()"><i class="fas fa-check-circle"></i> Konfirmasi Pesanan</button>
        </div>
    </div>

    <div id="paymentConfirmationPopup">
        <h3><i class="fas fa-check-circle"></i> Pembayaran Berhasil!</h3>
        <p>Pesanan kamu berhasil dibayar. Mohon ditunggu yaa, pesanan akan segera kami antar.</p>
        <button class="close-btn">Oke</button>
        <a href="halamanawal.php"></a>
    </div>

    <div id="qrisPopup">
        <h3><i class="fas fa-qrcode"></i> Bayar dengan QRIS</h3>
        <p style="color: var(--light-text);">Scan QR Code di bawah ini:</p>
        <img src="qris/qris.jpg" alt="QRIS Code">
        <p>Total yang harus Anda Bayar:</p>
        <p id="totalBayarQris" style="font-size: 1.8em; color: var(--primary-color); font-weight: bold;">Rp 0</p>
        <button class="close-qris-btn"><i class="fas fa-times"></i> Tutup</button>
    </div>

    <div id="waitingForConfirmation">
        <div class="icon">&#9203;</div>
        <h3>Menunggu Konfirmasi Pembayaran...</h3>
        <p>Pesanan Anda sedang diproses. Silakan tunggu konfirmasi dari admin.</p>
        <p>Setelah pembayaran dikonfirmasi, notifikasi akan muncul secara otomatis.</p>
        <button id="cancelOrderFromWaiting"><i class="fas fa-times-circle"></i> Batalkan & Buat Pesanan Baru</button>
    </div>
    <script src="./bootstrap-5.3.6-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>