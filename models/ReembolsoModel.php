<?php
// models/ReembolsoModel.php

require_once __DIR__ . '/Model.php';

class ReembolsoModel extends Model
{
    protected string $table      = 'reembolso';
    protected string $primaryKey = 'id_reembolso';

    public function getByAfiliado(int $afilId): array
    {
        return $this->query(
            "SELECT * FROM reembolso WHERE id_afiliado = :id ORDER BY fecha_solicitud DESC",
            [':id' => $afilId]
        );
    }

    public function getResumen(int $afilId): array
    {
        return $this->row("
            SELECT
              COALESCE(SUM(CASE WHEN estado IN ('pendiente','en_revision') THEN 1 ELSE 0 END), 0) AS pendientes,
              COALESCE(SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END), 0)                  AS aprobados,
              COALESCE(SUM(CASE WHEN estado = 'aprobado' THEN monto_aprobado ELSE 0 END), 0)      AS reintegrado
            FROM reembolso
            WHERE id_afiliado = :id
        ", [':id' => $afilId]) ?: ['pendientes' => 0, 'aprobados' => 0, 'reintegrado' => 0];
    }

    public function crear(array $campos): int
    {
        $this->execute("
            INSERT INTO reembolso
              (tipo_servicio, fecha_atencion, monto_solicitado, centro_medico, descripcion, archivo_adjunto, id_afiliado)
            VALUES
              (:tipo, :fecha, :monto, :centro, :desc, :archivo, :id)
        ", [
            ':tipo'    => $campos['tipo_servicio'],
            ':fecha'   => $campos['fecha_atencion'],
            ':monto'   => $campos['monto'],
            ':centro'  => $campos['centro_medico'],
            ':desc'    => $campos['descripcion'],
            ':archivo' => $campos['archivo_adjunto'],
            ':id'      => $campos['id_afiliado'],
        ]);
        return $this->lastId();
    }

    // ── Carta Aval ───────────────────────────────────────────

    public function getAvalesByAfiliado(int $afilId): array
    {
        return $this->query(
            "SELECT * FROM carta_aval WHERE id_afiliado = :id ORDER BY fecha_solicitud DESC",
            [':id' => $afilId]
        );
    }

    public function crearAval(array $campos): int
    {
        $this->execute("
            INSERT INTO carta_aval
              (medico_tratante, especialidad, centro_medico, procedimiento, monto_estimado, id_afiliado, id_beneficiario)
            VALUES
              (:medico, :esp, :centro, :proc, :monto, :id, :ben)
        ", [
            ':medico' => $campos['medico_tratante'],
            ':esp'    => $campos['especialidad'],
            ':centro' => $campos['centro_medico'],
            ':proc'   => $campos['procedimiento'],
            ':monto'  => $campos['monto_estimado'] ?: null,
            ':id'     => $campos['id_afiliado'],
            ':ben'    => $campos['id_beneficiario'] ?: null,
        ]);
        return $this->lastId();
    }

    // ── Beneficiarios (para selectores) ──────────────────────

    public function getBeneficiarios(int $afilId): array
    {
        return $this->query(
            "SELECT id_beneficiario, nombre, apellido, parentesco FROM beneficiario WHERE id_afiliado = :id",
            [':id' => $afilId]
        );
    }

    // ── Verificar que un beneficiario pertenece al afiliado ──
    public function beneficiarioPerteneceA(int $benId, int $afilId): bool
    {
        return (bool) $this->scalar(
            "SELECT 1 FROM beneficiario WHERE id_beneficiario = :ben AND id_afiliado = :afil LIMIT 1",
            [':ben' => $benId, ':afil' => $afilId]
        );
    }
}
