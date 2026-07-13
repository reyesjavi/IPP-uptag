<?php
// models/BeneficiarioModel.php — Carga familiar del afiliado
//
// Los beneficiarios heredan el plan del afiliado; no son afiliados.
// La fila con parentesco 'titular' representa al propio afiliado y se
// gestiona automáticamente (registro) — nunca desde esta pantalla.
// No se borran filas (las citas históricas las referencian): se
// desactivan con `activo = 0`.

require_once __DIR__ . '/Model.php';

class BeneficiarioModel extends Model
{
    protected string $table      = 'beneficiario';
    protected string $primaryKey = 'id_beneficiario';

    public const PARENTESCOS = ['padre', 'madre', 'hijo', 'conyuge', 'otro'];

    /** Carga familiar completa, titular primero. */
    public function getByAfiliado(int $afilId): array
    {
        return $this->query("
            SELECT id_beneficiario, ci, nombre, apellido, fecha_nacimiento, parentesco, activo
            FROM beneficiario
            WHERE id_afiliado = :id
            ORDER BY (parentesco = 'titular') DESC, nombre, apellido
        ", [':id' => $afilId]);
    }

    public function crear(int $afilId, array $d): int
    {
        $num = (int) $this->scalar(
            "SELECT COALESCE(MAX(numero_beneficiario),0)+1 FROM beneficiario WHERE id_afiliado = :id",
            [':id' => $afilId]
        );
        $this->execute("
            INSERT INTO beneficiario (numero_beneficiario, ci, nombre, apellido, fecha_nacimiento, parentesco, activo, id_afiliado)
            VALUES (:num, :ci, :nom, :ape, :fnac, :par, 1, :afil)
        ", [
            ':num'  => $num,
            ':ci'   => $d['ci'] ?: null,
            ':nom'  => $d['nombre'],
            ':ape'  => $d['apellido'],
            ':fnac' => $d['fecha_nacimiento'] ?: null,
            ':par'  => $d['parentesco'],
            ':afil' => $afilId,
        ]);
        return $this->lastId();
    }

    /**
     * Activa/desactiva un familiar. El WHERE incluye id_afiliado (anti-IDOR)
     * y excluye al titular (no se puede desactivar a uno mismo).
     */
    public function setActivo(int $benId, int $afilId, bool $activo): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE beneficiario SET activo = :a
            WHERE id_beneficiario = :ben AND id_afiliado = :afil AND parentesco != 'titular'
        ");
        $stmt->execute([':a' => (int) $activo, ':ben' => $benId, ':afil' => $afilId]);
        return $stmt->rowCount() > 0;
    }
}
