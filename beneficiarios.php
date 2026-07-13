<?php
$pageTitle = 'Mi Carga Familiar';
$activeNav = '';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/controllers/BeneficiariosController.php';

requiereLogin();

(new BeneficiariosController())->handle();
