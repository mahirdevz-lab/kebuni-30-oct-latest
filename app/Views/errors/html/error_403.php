<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>403 - Akses Ditolak</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="text-center">
        <i class="fa-solid fa-lock text-danger" style="font-size: 4rem;"></i>
        <h1 class="mt-3">403</h1>
        <p class="text-muted">Anda tidak memiliki akses untuk membuka halaman ini.</p>
        <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-primary mt-2">Kembali ke Dashboard</a>
    </div>
</body>
</html>
