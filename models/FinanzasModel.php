<?php
// models/FinanzasModel.php

require_once __DIR__ . '/Model.php';

class FinanzasModel extends Model
{
    protected string $table      = 'movimiento_cuenta';
    protected string $primaryKey = 'id_movimiento';

    public function getMovimientos(int $afilId, int $limite = 20): array
    {
        return $this->query("
            SELECT * FROM movimiento_cuenta
            WHERE id_afiliado = :id
            ORDER BY fecha DESC, id_movimiento DESC
            LIMIT $limite
        ", [':id' => $afilId]);
    }

    public function getSaldo(int $afilId): float
    {
        return (float) $this->scalar("
            SELECT COALESCE(SUM(CASE WHEN tipo = 'credito' THEN monto ELSE -monto END), 0)
            FROM movimiento_cuenta
            WHERE id_afiliado = :id
        ", [':id' => $afilId]);
    }

    public function crearSolicitudRetiro(array $campos): int
    {
        $this->execute("
            INSERT INTO solicitud_retiro (id_afiliado, tipo_retiro, monto, motivo, estado)
            VALUES (:id, :tipo, :monto, :motivo, 'pendiente')
        ", [
            ':id'    => $campos['id_afiliado'],
            ':tipo'  => $campos['tipo_retiro'],
            ':monto' => $campos['monto'],
            ':motivo'=> $campos['motivo'],
        ]);
        return $this->lastId();
    }
}
