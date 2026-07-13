<?php
$pageTitle = 'Citas';
$activeNav = 'citas';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/controllers/CitasController.php';

requiereLogin();

(new CitasController())->handle();
