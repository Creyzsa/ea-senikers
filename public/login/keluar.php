<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';

sesi_hancurkan_total();

header('Location: ' . aplikasi_url('login/masuk.php'));
exit;
