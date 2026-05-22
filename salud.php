<?php
$pageTitle = 'Salud';
$activeNav = 'salud';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/controllers/SaludController.php';

requiereLogin();

(new SaludController())->handle();
