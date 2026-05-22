<?php
// controllers/SaludController.php

require_once __DIR__ . '/../models/ReembolsoModel.php';

class SaludController
{
    private ReembolsoModel $model;
    private int $afilId;

    public function __construct()
    {
        $this->model  = new ReembolsoModel();
        $this->afilId = (int) ($_SESSION['afiliado_id'] ?? 0);
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verificarCsrf();
            match ($_POST['accion'] ?? '') {
                'reembolso' => $this->crearReembolso(),
                'aval'      => $this->crearAval(),
                default     => $this->redirect('salud.php'),
            };
        } else {
            $this->renderIndex();
        }
    }

    // ── POST handlers ────────────────────────────────────────

    private function crearReembolso(): void
    {
        if (!$this->afilId) {
            $this->flash(false, 'Tu cuenta no está vinculada a un afiliado. Contacta a administración.');
            $this->redirect('salud.php?tab=reimb');
        }

        $tipo   = trim($_POST['tipo_servicio']  ?? '');
        $fecha  = trim($_POST['fecha_atencion'] ?? '');
        $monto  = floatval($_POST['monto']      ?? 0);
        $centro = trim($_POST['centro_medico']  ?? '');
        $desc   = trim($_POST['descripcion']    ?? '');

        if (!$tipo || !$fecha || $monto <= 0) {
            $this->flash(false, 'Por favor completa todos los campos obligatorios.');
            $this->redirect('salud.php?tab=reimb');
        }

        $archivo = $this->procesarAdjunto('salud.php?tab=reimb');

        $this->model->crear([
            'tipo_servicio'  => $tipo,
            'fecha_atencion' => $fecha,
            'monto'          => $monto,
            'centro_medico'  => $centro,
            'descripcion'    => $desc,
            'archivo_adjunto'=> $archivo,
            'id_afiliado'    => $this->afilId,
        ]);

        registrarLog('reembolso_solicitado', "Reembolso $tipo por Bs. $monto");
        $this->flash(true, 'Solicitud de reembolso enviada correctamente.');
        $this->redirect('salud.php?tab=reimb');
    }

    private function crearAval(): void
    {
        $medico   = trim($_POST['medico_tratante'] ?? '');
        $especial = trim($_POST['especialidad']    ?? '');
        $centro   = trim($_POST['centro_medico']   ?? '');
        $proc     = trim($_POST['procedimiento']   ?? '');
        $monto    = floatval($_POST['monto_estimado'] ?? 0);
        $benId    = !empty($_POST['id_beneficiario']) ? intval($_POST['id_beneficiario']) : null;

        if (!$medico || !$centro || !$proc) {
            $this->flash(false, 'Completa los campos obligatorios.');
            $this->redirect('salud.php?tab=aval');
        }

        $this->model->crearAval([
            'medico_tratante' => $medico,
            'especialidad'    => $especial,
            'centro_medico'   => $centro,
            'procedimiento'   => $proc,
            'monto_estimado'  => $monto ?: null,
            'id_afiliado'     => $this->afilId,
            'id_beneficiario' => $benId,
        ]);

        $this->flash(true, 'Carta aval solicitada correctamente.');
        $this->redirect('salud.php?tab=aval');
    }

    // ── Render ───────────────────────────────────────────────

    private function renderIndex(): void
    {
        $tab  = $_GET['tab'] ?? 'reimb';
        $data = [
            'tab'          => $tab,
            'flash'        => $_SESSION['flash'] ?? null,
            'reembolsos'   => $this->model->getByAfiliado($this->afilId),
            'resumen'      => $this->model->getResumen($this->afilId),
            'avales'       => $this->model->getAvalesByAfiliado($this->afilId),
            'beneficiarios'=> $this->model->getBeneficiarios($this->afilId),
            'afiliado'     => getAfiliado(),
        ];
        unset($_SESSION['flash']);
        $this->view('salud/index.php', $data);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function procesarAdjunto(string $redireccionError): ?string
    {
        if (empty($_FILES['archivo']['name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $permitidos    = ['application/pdf', 'image/jpeg', 'image/png'];
        $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $finfo         = new finfo(FILEINFO_MIME_TYPE);
        $tipoReal      = $finfo->file($_FILES['archivo']['tmp_name']);
        $ext           = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));

        if (!in_array($tipoReal, $permitidos) || !in_array($ext, $extPermitidas)) {
            $this->flash(false, 'Solo se permiten archivos PDF, JPG o PNG.');
            $this->redirect($redireccionError);
        }

        if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
            $this->flash(false, 'El archivo no puede superar los 5 MB.');
            $this->redirect($redireccionError);
        }

        $carpeta = UPLOAD_PATH;
        if (!is_dir($carpeta)) mkdir($carpeta, 0750, true);

        $nombre  = 'reemb_' . $this->afilId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $carpeta . '/' . $nombre;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            $this->flash(false, 'Error al guardar el archivo. Intenta de nuevo.');
            $this->redirect($redireccionError);
        }

        return $nombre;
    }

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
