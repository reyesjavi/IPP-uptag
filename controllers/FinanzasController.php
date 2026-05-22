<?php
// controllers/FinanzasController.php

require_once __DIR__ . '/../models/FinanzasModel.php';

class FinanzasController
{
    private FinanzasModel $model;
    private int $afilId;

    public function __construct()
    {
        $this->model  = new FinanzasModel();
        $this->afilId = (int) ($_SESSION['afiliado_id'] ?? 0);
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verificarCsrf();
            match ($_POST['accion'] ?? '') {
                'retiro' => $this->crearRetiro(),
                default  => $this->redirect('finanzas.php'),
            };
        } else {
            $this->renderIndex();
        }
    }

    // ── POST handlers ────────────────────────────────────────

    private function crearRetiro(): void
    {
        if (!$this->afilId) {
            $this->flash(false, 'Tu cuenta no está vinculada a un afiliado. Contacta a administración.');
            $this->redirect('finanzas.php?tab=retiro');
        }

        $tipoRetiro = trim($_POST['tipo_retiro'] ?? '');
        $monto      = floatval($_POST['monto']   ?? 0);
        $motivo     = trim($_POST['motivo']       ?? '');
        $saldo      = $this->model->getSaldo($this->afilId);

        if ($monto <= 0) {
            $this->flash(false, 'Ingresa un monto válido mayor a cero.');
        } elseif ($tipoRetiro === 'Parcial' && $monto > $saldo * 0.30) {
            $this->flash(false, 'El retiro parcial no puede superar el 30% de tu saldo (Bs. ' . number_format($saldo * 0.30, 2, ',', '.') . ').');
        } elseif ($monto > $saldo) {
            $this->flash(false, 'El monto solicitado supera tu saldo disponible.');
        } else {
            $this->model->crearSolicitudRetiro([
                'id_afiliado' => $this->afilId,
                'tipo_retiro' => $tipoRetiro,
                'monto'       => $monto,
                'motivo'      => $motivo,
            ]);
            registrarLog('retiro_solicitado', "Retiro $tipoRetiro de Bs. $monto");
            $this->flash(true, 'Solicitud de retiro enviada. Será revisada por administración.');
        }

        $this->redirect('finanzas.php?tab=retiro');
    }

    // ── Render ───────────────────────────────────────────────

    private function renderIndex(): void
    {
        $tab  = $_GET['tab'] ?? 'cuenta';
        $data = [
            'tab'        => $tab,
            'flash'      => $_SESSION['flash'] ?? null,
            'movimientos'=> $this->model->getMovimientos($this->afilId),
            'saldo'      => $this->model->getSaldo($this->afilId),
        ];
        unset($_SESSION['flash']);
        $this->view('finanzas/index.php', $data);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function flash(bool $ok, string $msg): void
    {
        $_SESSION['flash'] = ['ok' => $ok, 'msg' => $msg];
    }

    private function redirect(string $ruta): never
    {
        header('Location: ' . url($ruta));
        exit;
    }

    private function view(string $plantilla, array $data): void
    {
        extract($data);
        require_once __DIR__ . '/../views/' . $plantilla;
    }
}
