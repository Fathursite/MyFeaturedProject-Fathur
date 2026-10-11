<?php
require 'config.php';

if (isset($_GET['pesanan_id'])) {
    $pesanan_id = mysqli_real_escape_string($conn, $_GET['pesanan_id']);
    $query = "SELECT status_pembayaran FROM pesanan WHERE id = '$pesanan_id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode(['status_pembayaran' => $row['status_pembayaran']]);
    } else {
        echo json_encode(['status_pembayaran' => 'Tidak Ditemukan']);
    }
} else {
    echo json_encode(['message' => 'ID Pesanan tidak ditemukan']);
}
?>