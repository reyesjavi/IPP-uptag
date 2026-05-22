<?php
$pageTitle = 'Finanzas';
$activeNav = 'finanzas';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/controllers/FinanzasController.php';

requiereLogin();

(new FinanzasController())->handle();
