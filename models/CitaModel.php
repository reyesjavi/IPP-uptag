<?php
// models/CitaModel.php — Citas con especialistas del IPP

require_once __DIR__ . '/Model.php';

class CitaModel extends Model
{
    protected string $table      = 'cita';
    protected string $primaryKey = 'id_cita';

    // ── Lado afiliado ────────────────────────────────────────

    /** Citas del afiliado con médico y paciente (NULL beneficiario = titular). */
    public function getByAfiliado(int $afilId): array
    {
        return $this->query("
            SELECT c.*,
                   m.nombre AS medico_nombre, m.apellido AS medico_apellido, m.especialidad,
                   b.nombre AS ben_nombre, b.apellido AS ben_apellido, b.parentesco
            FROM cita c
            JOIN medico m ON m.id_medico = c.id_medico
            LEFT JOIN beneficiario b ON b.id_beneficiario = c.id_beneficiario
            WHERE c.id_afiliado = :id
            ORDER BY c.fecha_hora DESC
        ", [':id' => $afilId]);
    }

    public function crear(array $c): int
    {
        $this->execute("
            INSERT INTO cita (id_afiliado, id_beneficiario, id_medico, fecha_hora, notas)
            VALUES (:afil, :ben, :med, :fh, :notas)
        ", [
            ':afil'  => $c['id_afiliado'],
            ':ben'   => $c['id_beneficiario'],
            ':med'   => $c['id_medico'],
            ':fh'    => $c['fecha_hora'],
            ':notas' => $c['notas'] ?: null,
        ]);
        return $this->lastId();
    }

    /** El afiliado solo puede cancelar SU cita y solo si aún no ocurrió. */
    public function cancelarDeAfiliado(int $idCita, int $afilId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE cita SET estado = 'cancelada'
            WHERE id_cita = :id AND id_afiliado = :afil
              AND estado IN ('pendiente','confirmada')
        ");
        $stmt->execute([':id' => $idCita, ':afil' => $afilId]);
        return $stmt->rowCount() > 0;
    }

    /** Especialistas del IPP agendables (solo tipo medico, activos). */
    public function getEspecialistas(): array
    {
        return $this->query("
            SELECT id_medico, nombre, apellido, especialidad, horario
            FROM medico
            WHERE tipo = 'medico' AND activo = 1
            ORDER BY especialidad, apellido, nombre
        ");
    }

    public function esEspecialistaValido(int $idMedico): bool
    {
        return (bool) $this->scalar(
            "SELECT 1 FROM medico WHERE id_medico = :id AND tipo = 'medico' AND activo = 1",
            [':id' => $idMedico]
        );
    }

    /** Familiares agendables (sin la fila titular; el titular es id_beneficiario NULL). */
    public function getFamiliares(int $afilId): array
    {
        return $this->query("
            SELECT id_beneficiario, nombre, apellido, parentesco
            FROM beneficiario
            WHERE id_afiliado = :id AND activo = 1 AND parentesco != 'titular'
            ORDER BY nombre
        ", [':id' => $afilId]);
    }

    /** Anti-IDOR: el beneficiario debe pertenecer al afiliado en sesión. */
    public function beneficiarioPerteneceA(int $benId, int $afilId): bool
    {
        return (bool) $this->scalar(
            "SELECT 1 FROM beneficiario WHERE id_beneficiario = :ben AND id_afiliado = :afil AND activo = 1",
            [':ben' => $benId, ':afil' => $afilId]
        );
    }

    // ── Lado admin ───────────────────────────────────────────

    public function getTodas(string $estado = ''): array
    {
        $where  = '';
        $params = [];
        if ($estado !== '') {
            $where  = 'WHERE c.estado = :e';
            $params = [':e' => $estado];
        }
        return $this->query("
            SELECT c.*,
                   a.nombre AS afil_nombre, a.apellido AS afil_apellido, a.ci AS afil_ci,
                   m.nombre AS medico_nombre, m.apellido AS medico_apellido, m.especialidad,
                   b.nombre AS ben_nombre, b.apellido AS ben_apellido, b.ci AS ben_ci, b.parentesco
            FROM cita c
            JOIN afiliado a ON a.id_afiliado = c.id_afiliado
            JOIN medico m   ON m.id_medico = c.id_medico
            LEFT JOIN beneficiario b ON b.id_beneficiario = c.id_beneficiario
            $where
            ORDER BY FIELD(c.estado,'pendiente','confirmada','atendida','no_asistio','cancelada'), c.fecha_hora DESC
        ", $params);
    }

    /** Datos que necesita la frontera de facturación para registrar el consumo. */
    public function getParaConsumo(int $idCita): array|false
    {
        return $this->row("
            SELECT c.id_cita, c.estado, a.ci AS afil_ci, b.ci AS ben_ci, c.id_beneficiario
            FROM cita c
            JOIN afiliado a ON a.id_afiliado = c.id_afiliado
            LEFT JOIN beneficiario b ON b.id_beneficiario = c.id_beneficiario
            WHERE c.id_cita = :id
        ", [':id' => $idCita]);
    }

    /** Transición de estado controlada (solo transiciones válidas). */
    public function cambiarEstado(int $idCita, string $nuevo): bool
    {
        $desde = match ($nuevo) {
            'confirmada'             => ['pendiente'],
            'atendida', 'no_asistio' => ['pendiente', 'confirmada'],
            'cancelada'              => ['pendiente', 'confirmada'],
            default                  => [],
        };
        if (!$desde) return false;

        $marcadores = implode(',', array_fill(0, count($desde), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE cita SET estado = ? WHERE id_cita = ? AND estado IN ($marcadores)"
        );
        $stmt->execute([$nuevo, $idCita, ...$desde]);
        return $stmt->rowCount() > 0;
    }
}
