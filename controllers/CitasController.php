<?php
// controllers/CitasController.php — Agenda de citas del afiliado

require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../lib/integracion/Integraciones.php';

class CitasController
{
    private CitaModel $model;
    private int $afilId;

    public function __construct()
    {
        $this->model  = new CitaModel();
        $this->afilId = (int) ($_SESSION['afiliado_id'] ?? 0);
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verificarCsrf();
            match ($_POST['accion'] ?? '') {
                'agendar'  => $this->agendar(),
                'cancelar' => $this->cancelar(),
                default    => $this->redirect('citas.php'),
            };
        } else {
            $this->renderIndex();
        }
    }

    // ── POST handlers ────────────────────────────────────────

    private function agendar(): void
    {
        if (!$this->afilId) {
            $this->flash(false, 'Tu cuenta no está vinculada a un afiliado. Contacta a administración.');
            $this->redirect('citas.php');
        }

        $idMedico = intval($_POST['id_medico'] ?? 0);
        $fecha    = trim($_POST['fecha'] ?? '');
        $hora     = trim($_POST['hora']  ?? '');
        $benId    = !empty($_POST['id_beneficiario']) ? intval($_POST['id_beneficiario']) : null;
        $notas    = trim($_POST['notas'] ?? '');

        if (!$idMedico || !$fecha || !$hora) {
            $this->flash(false, 'Completa el especialista, la fecha y la hora.');
            $this->redirect('citas.php');
        }

        $fechaHora = DateTime::createFromFormat('Y-m-d H:i', "$fecha $hora");
        if (!$fechaHora || $fechaHora <= new DateTime()) {
            $this->flash(false, 'La fecha y hora de la cita deben ser futuras.');
            $this->redirect('citas.php');
        }

        if (!$this->model->esEspecialistaValido($idMedico)) {
            $this->flash(false, 'El especialista seleccionado no está disponible.');
            $this->redirect('citas.php');
        }

        // El beneficiario, si se indica, debe pertenecer al afiliado en sesión (evita IDOR)
        if ($benId !== null && !$this->model->beneficiarioPerteneceA($benId, $this->afilId)) {
            $this->flash(false, 'El beneficiario seleccionado no es válido para tu cuenta.');
            $this->redirect('citas.php');
        }

        $idCita = $this->model->crear([
            'id_afiliado'     => $this->afilId,
            'id_beneficiario' => $benId,
            'id_medico'       => $idMedico,
            'fecha_hora'      => $fechaHora->format('Y-m-d H:i:s'),
            'notas'           => $notas,
        ]);

        registrarLog('cita_agendada', "Cita #$idCita agendada para " . $fechaHora->format('d/m/Y H:i'));
        $this->flash(true, 'Cita agendada. Recibirás la confirmación del IPP.');
        $this->redirect('citas.php');
    }

    private function cancelar(): void
    {
        $idCita = intval($_POST['id_cita'] ?? 0);
        if ($idCita && $this->model->cancelarDeAfiliado($idCita, $this->afilId)) {
            registrarLog('cita_cancelada', "Cita #$idCita cancelada por el afiliado");
            $this->flash(true, 'Cita cancelada.');
        } else {
            $this->flash(false, 'No se pudo cancelar la cita (puede que ya haya sido procesada).');
        }
        $this->redirect('citas.php');
    }

    // ── Render ───────────────────────────────────────────────

    private function renderIndex(): void
    {
        $ci = $_SESSION['usuario_ci'] ?? '';

        // Datos de la frontera de facturación: si el provider falla, la
        // página sigue funcionando sin el bloque de saldo (modo degradado).
        $saldo = $tarifa = null;
        if ($this->afilId && $ci) {
            try {
                $ledger = Integraciones::consultas();
                $saldo  = $ledger->saldo($ci);
                $tarifa = $ledger->tarifa('consulta');
            } catch (Throwable $e) {
                error_log('[UPTAG Citas] provider facturación no disponible: ' . $e->getMessage());
            }
        }

        $data = [
            'flash'         => $_SESSION['flash'] ?? null,
            'citas'         => $this->afilId ? $this->model->getByAfiliado($this->afilId) : [],
            'especialistas' => $this->model->getEspecialistas(),
            'familiares'    => $this->afilId ? $this->model->getFamiliares($this->afilId) : [],
            'saldo'         => $saldo,
            'tarifa'        => $tarifa,
        ];
        unset($_SESSION['flash']);
        $this->view('citas/index.php', $data);
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
