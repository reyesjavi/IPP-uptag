# INTEGRACION.md — Fronteras con sistemas externos

Este portal depende de **dos sistemas que no controlamos**. Para no bloquearnos,
cada frontera está detrás de una **interfaz PHP** con una implementación **mock**
que lee tablas locales (se llenan a mano para probar). Integrar el sistema real
será escribir una nueva implementación de la interfaz y cambiar una línea en `.env`
— ningún consumidor del portal cambia.

```
Portal ──> EstadoAfiliacionProvider ──┬── MockEstadoAfiliacionProvider (hoy: tablas locales)
           (interfaz)                 └── [futuro] feed real de nómina UPTAG

Portal ──> ConsultationLedgerProvider ─┬── MockConsultationLedgerProvider (hoy: tablas locales)
           (interfaz)                  └── [futuro] API del sistema de facturación
```

**Selección de implementación** — `.env`:

```dotenv
NOMINA_PROVIDER=mock        # futuro: api | db | csv (según lo que provea UPTAG)
FACTURACION_PROVIDER=mock   # futuro: api
```

**Auditoría:** toda llamada a un provider (mock o real) queda en la tabla
`integracion_log` (sistema, operación, clave consultada, resultado).

---

## Frontera 1 — Nómina / padrón de agremiados (UPTAG · RRHH/Informática)

**Interfaz:** `lib/integracion/EstadoAfiliacionProvider.php`
**Mock:** lee `agremiado` (padrón) y `estado_afiliacion_cache` (estado de pago).
**Regla:** el pago de la afiliación es por **descuento de nómina del banco**.
Este portal **no calcula ni procesa pagos**; solo muestra lo que reporte el provider.

### Qué necesitamos que nos entreguen (por cada agremiado)

| Dato | Tipo | Obligatorio | Notas |
|---|---|---|---|
| Cédula de identidad | texto (`V-12345678`) | ✅ | Clave de correlación actual |
| ID estable en nómina | texto/número | ✅ (pedir) | Las CI se corrigen en la práctica; lo guardamos en `agremiado.ref_nomina` |
| Nombre y apellido | texto | ✅ | |
| Condición | `profesor_activo` \| `profesor_jubilado` | ✅ | Define tarifas/beneficios. Si el feed no lo trae, cae al fallback (select en registro) |
| Estado de afiliación | `activo` \| `inactivo` \| `moroso` \| `suspendido` | ✅ | Derivado del descuento bancario |
| Período procesado | `YYYY-MM` | ✅ | Último período de nómina aplicado |
| Fecha del último descuento | fecha | deseable | |
| Fecha de nacimiento, correo, teléfono | — | deseable | Para materializar el perfil al registrarse |
| Fecha de agremiación | fecha | deseable | |

### Preguntas abiertas para la reunión con nómina

1. **¿Medio de entrega?** API REST, vista de solo lectura sobre su BD, o export CSV/Excel periódico. Cualquiera sirve: la interfaz lo abstrae.
2. **¿Frecuencia de actualización?** Propuesta: sincronización **diaria** (o al cierre de cada período de nómina) + consulta puntual al iniciar sesión el afiliado si el dato local tiene más de 24 h.
3. **¿Cuál es su identificador estable** de agremiado? (para `ref_nomina`).
4. **¿Formato canónico de la CI?** (con/sin guion, prefijo V/E).
5. **¿Qué significa exactamente cada estado** en su sistema y cuándo transiciona? (ej. ¿cuántos períodos sin descuento = moroso?).
6. **Decisión pendiente interna:** hoy el acceso al portal lo controla la `vigencia_anual` local (renovación anual). ¿La reemplaza el estado de nómina, o conviven? Mientras no se decida, la vigencia sigue mandando y el estado de nómina es **solo informativo** en el dashboard.

### Contrato de la implementación real (cuando exista)

- Debe hacer **upsert** de cada registro consultado en `agremiado` y
  `estado_afiliacion_cache` (son la **caché de modo degradado**).
- Si el sistema de nómina **no responde**: servir el último dato cacheado con
  `desdeCache = true`; la UI muestra "dato al {actualizado_en}". Nunca bloquear
  el login por caída del sistema externo.

---

## Frontera 2 — Sistema de facturación de consultas (otro equipo)

**Interfaz:** `lib/integracion/ConsultationLedgerProvider.php`
**Mock:** simula el ledger sobre `consulta_ledger_cache` + `tarifa_cache`,
parametrizado por la tabla `plan` (consultas incluidas, descuento, pool).

**Decisión de arquitectura (acordada):** el equipo de facturación es la **fuente
de verdad del contador de consultas y del precio** (incluido el descuento
posterior — decisión #3). Este portal **no lleva contador propio**: lee y consume.

### Contrato propuesto (borrador para la reunión)

| Operación del portal | Qué necesitamos de ellos | Método de la interfaz |
|---|---|---|
| Mostrar consultas restantes | Saldo por afiliado (CI): incluidas, usadas, restantes | `saldo(ciAfiliado, ciBeneficiario?)` |
| Notificar consulta realizada | Registrar consumo **idempotente** (clave `referencia`, ej. `cita-123`) y devolver saldo resultante | `registrarConsumo(ciAfiliado, ciBeneficiario?, tipo, referencia)` |
| Historial del afiliado | Movimientos del año (fecha, tipo, quién, precio aplicado) | `historial(ciAfiliado, anio)` |
| Mostrar costo de la consulta | Precio base vigente + descuento aplicable | `tarifa(tipo)` |

**Qué les exponemos nosotros:** hoy, nada (solo consumimos). Si necesitan
validar afiliación/plan de una CI, podemos publicar un endpoint de solo lectura
— a discutir.

### Reglas que necesitamos acordar

1. **Idempotencia obligatoria** en el registro de consumos: la misma `referencia`
   enviada dos veces no puede descontar dos consultas. (El mock ya lo garantiza
   con `UNIQUE(referencia)`.)
2. **Identificador:** hablamos en **CI** (afiliado y, si el pool no es compartido,
   beneficiario). Confirmar si su sistema maneja otro ID.
3. **Pool de consultas:** decidimos pool **compartido por grupo familiar**
   (4 consultas por afiliado/año — configurable en `plan`). Confirmar que su
   contador agrupa igual, o quién agrega.
4. **Precio y descuento:** ¿el precio se indexa (USD referencial) o es fijo en VES?
   ¿El 50% posterior lo calculan ellos por transacción? (Asumimos que sí.)
5. **¿Reinicio anual del contador?** ¿Calendario (1-ene) u otra fecha?
6. **¿Existe límite duro** al agotar el plan (bloquear) o solo cambia el precio?
   (Asumimos: solo cambia el precio.)

### Comportamiento degradado (si facturación no responde)

- `saldo`/`tarifa`: servir el último valor de `consulta_ledger_cache` /
  `tarifa_cache` con `desdeCache = true`; la UI lo marca como "dato al {fecha}".
- `registrarConsumo`: encolar localmente el movimiento (queda en
  `consulta_ledger_cache` con su `referencia`) y re-enviarlo cuando el sistema
  vuelva — la idempotencia hace seguro el reintento.
- **Nunca** impedir agendar una cita por caída del sistema de facturación.

---

## Tablas locales de la frontera (se llenan a mano para probar)

| Tabla | Rol hoy (mock) | Rol futuro (integración real) |
|---|---|---|
| `agremiado` | Padrón que edita el admin | Espejo/caché del padrón de nómina |
| `estado_afiliacion_cache` | Estado de pago simulado | Caché degradado del feed de nómina |
| `consulta_ledger_cache` | Ledger simulado | Caché degradado + cola de reintentos |
| `tarifa_cache` | Precio de prueba | Caché del precio de facturación |

Ejemplo para probar el dashboard (ajusta la CI a un afiliado real de tu BD):

```sql
INSERT INTO estado_afiliacion_cache (ci, estado, periodo, fecha_ultimo_descuento, tipo_afiliado)
VALUES ('V-12345678', 'activo', '2026-07', '2026-07-01', 'profesor_activo');

INSERT INTO consulta_ledger_cache (ci_afiliado, ci_beneficiario, tipo, fecha, precio_aplicado, referencia)
VALUES ('V-12345678', NULL, 'consulta', '2026-07-05', 0.00, 'prueba-001');
```

## Decisiones ya tomadas (no reabrir sin motivo)

1. Pool de consultas **compartido** por afiliado (4/año, configurable en `plan`).
2. La `vigencia_anual` sigue controlando el acceso; el estado de nómina es informativo (hasta reunión con nómina).
3. El **descuento posterior lo aplica facturación**; `plan.descuento_posterior` es config del mock/display.
4. `plan_medico` queda **congelada** (solo lectura, ningún flujo nuevo la usa).
5. **Centros en convenio desactivados** (`medico.activo=0` donde `tipo='centro'`): todas las consultas son en IPP-UPTAG.
6. En `cita`, `id_beneficiario NULL` = la cita es del **titular** (mismo patrón que `carta_aval`).
