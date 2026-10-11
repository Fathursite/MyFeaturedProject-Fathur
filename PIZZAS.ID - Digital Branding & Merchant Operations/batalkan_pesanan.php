<?php
require 'config.php'; // Pastikan file koneksi database Anda ada di sini

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pesanan_id'])) {
    $pesanan_id = mysqli_real_escape_string($conn, $_POST['pesanan_id']);

    // Mulai transaksi untuk memastikan konsistensi data
    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil detail pesanan dari tabel 'rekap' sebelum dihapus
        $get_rekap_query = "SELECT menu, qty FROM rekap WHERE pesanan_id = ?";
        $stmt_get_rekap = $conn->prepare($get_rekap_query);
        $stmt_get_rekap->bind_param("i", $pesanan_id);
        $stmt_get_rekap->execute();
        $rekap_result = $stmt_get_rekap->get_result();
        $items_to_restore = [];
        while ($row = $rekap_result->fetch_assoc()) {
            $items_to_restore[] = $row;
        }
        $stmt_get_rekap->close();

        // 2. Hapus detail pesanan dari tabel 'rekap'
        $delete_rekap_query = "DELETE FROM rekap WHERE pesanan_id = ?";
        $stmt_rekap = $conn->prepare($delete_rekap_query);
        $stmt_rekap->bind_param("i", $pesanan_id);
        $stmt_rekap->execute();
        $stmt_rekap->close();

        // 3. Kemudian, hapus pesanan dari tabel 'pesanan'
        $delete_pesanan_query = "DELETE FROM pesanan WHERE id = ?";
        $stmt_pesanan = $conn->prepare($delete_pesanan_query);
        $stmt_pesanan->bind_param("i", $pesanan_id);
        $stmt_pesanan->execute();
        $stmt_pesanan->close();

        // 4. Kembalikan stok ke tabel 'menu'
        foreach ($items_to_restore as $item) {
            $menu_judul = mysqli_real_escape_string($conn, $item['menu']);
            $qty_to_restore = intval($item['qty']);
            $update_stok_query = "UPDATE menu SET stok = stok + ? WHERE judul = ?";
            $stmt_update_stok = $conn->prepare($update_stok_query);
            $stmt_update_stok->bind_param("is", $qty_to_restore, $menu_judul);
            $stmt_update_stok->execute();
            $stmt_update_stok->close();
        }

        // Commit transaksi jika semua berhasil
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibatalkan dan stok dikembalikan.']);

    } catch (Exception $e) {
        // Rollback transaksi jika ada kesalahan
        mysqli_rollback($conn);
        error_log("Gagal membatalkan pesanan: " . $e->getMessage()); // Log error untuk debugging
        echo json_encode(['success' => false, 'message' => 'Gagal membatalkan pesanan. Silakan coba lagi.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
}
?>