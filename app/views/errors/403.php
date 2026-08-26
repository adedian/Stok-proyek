<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>403 - Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column vh-100 bg-light">
    <div class="text-center m-auto">
        <h1 class="display-1 text-danger">403</h1>
        <p class="fs-4">Anda tidak memiliki hak akses ke halaman ini.</p>
        <a href="<?= BASE_URL ?>/index.php?module=dashboard" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
    <footer class="text-center small text-muted py-3">
        &copy; PT. Hexa Multi Energi. All rights reserved. Designed by Ade Dian Sukmana
    </footer>
</body>
</html>
