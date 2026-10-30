<?php if (str_ends_with((string) \Config\Database::connect()->getDatabase(), '_training')): ?>
<div style="background:#b91c1c;color:#fff;text-align:center;padding:6px 12px;font-weight:700;letter-spacing:.04em">
    MODE LATIHAN — data contoh, bukan data toko sebenarnya
</div>
<div style="position:fixed;right:12px;bottom:12px;z-index:2000;background:#b91c1c;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.3);pointer-events:none">LATIHAN</div>
<?php endif ?>
