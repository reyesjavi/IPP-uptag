<?php
// index.php — Punto de entrada
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';

if (estaAutenticado()) {
    header('Location: ' . url('dashboard.php'));
} else {
    header('Location: ' . url('login.php'));
}
exit;
