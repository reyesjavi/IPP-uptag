<?php
// controllers/BeneficiariosController.php — Gestión de carga familiar

require_once __DIR__ . '/../models/BeneficiarioModel.php';

class BeneficiariosController
{
    private BeneficiarioModel $model;
    private int $afilId;

    public function __construct()
    {
        $this->model  = new BeneficiarioModel();
        $this->afilId = (int) ($_SESSION['afiliado_id'] ?? 0);
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verificarCsrf();
            match ($_POST['accion'] ?? '') {
                'agregar'    => $this->agregar(),
                'desactivar' => $this->setActivo(false),
                'reactivar'  => $this->setActivo(true),
                default      => $this->redirect('beneficiarios.php'),
            };
        } else {
            $this->renderIndex();
        }
    }

    // ── POST handlers ────────────────────────────────────────

    private function agregar(): void
    {
        if (!$this->afilId) {
            $this->flash(false, 'Tu cuenta no está vinculada a un afiliado. Contacta a administración.');
            $this->redirect('beneficiarios.php');
        }

        $nombre     = trim($_POST['nombre']   ?? '');
        $apellido   = trim($_POST['apellido'] ?? '');
        $ci         = strtoupper(trim($_POST['ci'] ?? ''));
        $fnac       = trim($_POST['fecha_nacimiento'] ?? '');
        $parentesco = $_POST['parentesco'] ?? '';

        if (!$nombre || !$apellido || !in_array($parentesco, BeneficiarioModel::PARENTESCOS, true)) {
            $this->flash(false, 'Completa nombre, apellido y un parentesco válido.');
            $this->redirect('beneficiarios.php');
        }

        if ($ci !== '' && !preg_match('/^[VEJ]-?\d{6,9}$/i', $ci)) {
            $this->flash(false, 'Formato de cédula inválido. Use V-12345678 (o déjala vacía para menores).');
            $this->redirect('beneficiarios.php');
        }

        if ($fnac !== '' && (!($f = DateTime::createFromFormat('Y-m-d', $fnac)) || $f > new DateTime())) {
            $this->flash(false, 'La fecha de nacimiento no es válida.');
            $this->redirect('beneficiarios.php');
        }

        try {
            $this->model->crear($this->afilId, [
                'nombre'           => $nombre,
                'apellido'         => $apellido,
                'ci'               => $ci,
                'fecha_nacimiento' => $fnac,
                'parentesco'       => $parentesco,
            ]);
        } catch (PDOException $e) {
            // UNIQUE (id_afiliado, ci): la misma cédula ya está en tu carga familiar
            if ($e->getCode() === '23000') {
                $this->flash(false, 'Esa cédula ya está registrada en tu carga familiar.');
                $this->redirect('beneficiarios.php');
            }
            throw $e;
        }

        registrarLog('beneficiario_agregado', "Beneficiario $nombre $apellido ($parentesco) agregado");
        $this->flash(true, 'Beneficiario agregado a tu carga familiar.');
        $this->redirect('beneficiarios.php');
    }

    private function setActivo(bool $activo): void
    {
        $benId = intval($_POST['id_beneficiario'] ?? 0);
        if ($benId && $this->model->setActivo($benId, $this->afilId, $activo)) {
            registrarLog('beneficiario_' . ($activo ? 'reactivado' : 'desactivado'), "Beneficiario #$benId");
            $this->flash(true, $activo ? 'Beneficiario reactivado.' : 'Beneficiario desactivado. Ya no aparecerá al agendar citas ni solicitar avales.');
        } else {
            $this->flash(false, 'No se pudo actualizar el beneficiario.');
        }
        $this->redirect('beneficiarios.php');
    }

    // ── Render ───────────────────────────────────────────────

    private function renderIndex(): void
    {
        $data = [
            'flash'         => $_SESSION['flash'] ?? null,
            'beneficiarios' => $this->afilId ? $this->model->getByAfiliado($this->afilId) : [],
            'parentescos'   => BeneficiarioModel::PARENTESCOS,
        ];
        unset($_SESSION['flash']);
        $this->view('beneficiarios/index.php', $data);
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
