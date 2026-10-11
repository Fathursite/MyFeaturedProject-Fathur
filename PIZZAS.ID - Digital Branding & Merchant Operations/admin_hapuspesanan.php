<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Hapus dari detail dulu, lalu pesanan utama
    $deleteDetail = mysqli_query($conn, "DELETE FROM rekap WHERE pesanan_id = $id");
    $deleteMain = mysqli_query($conn, "DELETE FROM pesanan WHERE id = $id");

    header("Location: admin_pesananmasuk.php");
    exit();
}
?>
