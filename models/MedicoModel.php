<?php
// models/MedicoModel.php

require_once __DIR__ . '/Model.php';

class MedicoModel extends Model
{
    protected string $table      = 'medico';
    protected string $primaryKey = 'id_medico';

    public function getMedicos(string $busqueda = '', bool $soloActivos = true): array
    {
        $where  = "tipo = 'medico'";
        $params = [];
        if ($soloActivos) { $where .= " AND activo = 1"; }
        if ($busqueda) {
            $where .= " AND (nombre LIKE :q OR apellido LIKE :q OR especialidad LIKE :q)";
            $params[':q'] = "%$busqueda%";
        }
        return $this->findAll($where, $params, 'apellido, nombre');
    }

    public function getCentros(bool $soloActivos = true): array
    {
        $where = "tipo = 'centro'";
        if ($soloActivos) $where .= " AND activo = 1";
        return $this->findAll($where, [], 'nombre');
    }

    public function buscarConServicio(string $busqueda = ''): array
    {
        $sql    = "SELECT m.*, s.tipo_servicio
                   FROM medico m
                   LEFT JOIN servicio s ON s.id_servicio = m.id_servicio
                   WHERE m.tipo = 'medico' AND m.activo = 1";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (m.nombre LIKE :q OR m.apellido LIKE :q OR m.especialidad LIKE :q)";
            $params[':q'] = "%$busqueda%";
        }
        $sql .= " ORDER BY m.apellido LIMIT 50";
        return $this->query($sql, $params);
    }

    public function crear(array $campos): int
    {
        $this->execute("
            INSERT INTO medico (tipo, nombre, apellido, especialidad, cedula, numero_contacto, direccion, horario, convenio, servicios, id_servicio, activo)
            VALUES (:tipo, :nom, :ape, :esp, :ced, :tel, :dir, :horario, :conv, :servicios, :srv, 1)
        ", [
            ':tipo'   => $campos['tipo']             ?? 'medico',
            ':nom'    => $campos['nombre'],
            ':ape'    => $campos['apellido'],
            ':esp'    => $campos['especialidad']     ?? null,
            ':ced'    => $campos['cedula']           ?? null,
            ':tel'    => $campos['numero_contacto']  ?? null,
            ':dir'    => $campos['direccion']        ?? null,
            ':horario'=> $campos['horario']          ?? null,
            ':conv'   => $campos['convenio']         ?? null,
            ':servicios'=> $campos['servicios']      ?? null,
            ':srv'    => $campos['id_servicio']      ?? null,
        ]);
        return $this->lastId();
    }

    public function actualizar(int $id, array $campos): void
    {
        $this->execute("
            UPDATE medico
               SET tipo = :tipo, nombre = :nom, apellido = :ape,
                   especialidad = :esp, cedula = :ced, numero_contacto = :tel,
                   direccion = :dir, horario = :horario, convenio = :conv,
                   servicios = :servicios, id_servicio = :srv, activo = :activo
             WHERE id_medico = :id
        ", [
            ':tipo'   => $campos['tipo']             ?? 'medico',
            ':nom'    => $campos['nombre'],
            ':ape'    => $campos['apellido'],
            ':esp'    => $campos['especialidad']     ?? null,
            ':ced'    => $campos['cedula']           ?? null,
            ':tel'    => $campos['numero_contacto']  ?? null,
            ':dir'    => $campos['direccion']        ?? null,
            ':horario'=> $campos['horario']          ?? null,
            ':conv'   => $campos['convenio']         ?? null,
            ':servicios'=> $campos['servicios']      ?? null,
            ':srv'    => $campos['id_servicio']      ?? null,
            ':activo' => isset($campos['activo']) ? (int)$campos['activo'] : 1,
            ':id'     => $id,
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $this->execute("UPDATE medico SET activo = 1 - activo WHERE id_medico = :id", [':id' => $id]);
    }
}
