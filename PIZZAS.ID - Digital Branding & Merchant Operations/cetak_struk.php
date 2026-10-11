<?php
require 'config.php';
require 'fpdf/fpdf.php'; // Sesuaikan path

if (isset($_GET['pesanan_id'])) {
    $pesanan_id = $_GET['pesanan_id'];

    // Ambil informasi pesanan
    $query_pesanan = mysqli_query($conn, "SELECT p.nama, p.no_meja, p.tipe FROM pesanan p WHERE p.id = $pesanan_id");
    $data_pesanan = mysqli_fetch_assoc($query_pesanan);

    // Ambil detail pesanan (menu dan harga)
    $query_detail = mysqli_query($conn, "SELECT r.menu, r.qty, r.harga FROM rekap r WHERE r.pesanan_id = $pesanan_id");
    $detail_pesanan = mysqli_fetch_all($query_detail, MYSQLI_ASSOC);

    // Hitung total harga, SC, dan PPN
    $total_harga = 0;
    foreach ($detail_pesanan as $item) {
        $total_harga += $item['harga'] * $item['qty'];
    }
    $sc = $total_harga * 0.05;
    $ppn = ($total_harga + $sc) * 0.1;
    $total_bayar = $total_harga + $sc + $ppn;

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Struk Pesanan', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Nama: ' . $data_pesanan['nama'], 0, 1);
    if ($data_pesanan['tipe'] == 'Dine In') {
        $pdf->Cell(0, 6, 'No Meja: ' . $data_pesanan['no_meja'], 0, 1);
    }
    $pdf->Cell(0, 6, 'Tipe Pesanan: ' . $data_pesanan['tipe'], 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 6, 'Menu', 1);
    $pdf->Cell(20, 6, 'Qty', 1, 0, 'C');
    $pdf->Cell(30, 6, 'Harga', 1, 0, 'R');
    $pdf->Cell(30, 6, 'Total', 1, 1, 'R');
    $pdf->SetFont('Arial', '', 10);

    foreach ($detail_pesanan as $item) {
        $pdf->Cell(80, 6, $item['menu'], 1);
        $pdf->Cell(20, 6, $item['qty'], 1, 0, 'C');
        $pdf->Cell(30, 6, number_format($item['harga'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, number_format($item['harga'] * $item['qty'], 0, ',', '.'), 1, 1, 'R');
    }

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(130, 6, 'Subtotal', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format($total_harga, 0, ',', '.'), 0, 1, 'R');
    $pdf->Cell(130, 6, 'Service Charge (5%)', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format($sc, 0, ',', '.'), 0, 1, 'R');
    $pdf->Cell(130, 6, 'PPN (10%)', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format($ppn, 0, ',', '.'), 0, 1, 'R');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(130, 6, 'Total Bayar', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format($total_bayar, 0, ',', '.'), 0, 1, 'R');

    $pdf->Output('struk_pesanan_' . $pesanan_id . '.pdf', 'D');
    exit();
} else {
    echo "ID pesanan tidak valid.";
}
?>