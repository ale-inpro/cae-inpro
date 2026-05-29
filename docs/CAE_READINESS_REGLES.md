# Reglas de negocio: documentación CAE y generación con IA

Versión: 1.2  
Objetivo: definir qué significa que un técnico esté **listo para generar el CAE con IA** y cómo encajan fechas, estados de intake y verificación AEAT (solo Hacienda).

**Fuente de tipos en BD:** migración `database/migrations/2026_05_12_rename_doc_types.sql` (nombres canónicos y tipos sobrantes desactivados).

---

## 1. Documentos obligatorios (conjunto fijo de 4 tipos)

Cada técnico debe tener, para el **CAE record vigente** (`cae_records.is_current = TRUE` o el criterio que use la app), como máximo **cuatro** documentos complementarios activos, **uno por tipo**, sin duplicados activos del mismo tipo.

**Implementación (paso 3–4):** `CaeDocumentSlotService::replaceActiveSupportingSlot()` (desactivación + INSERT atómico; si no hay transacción abierta —p. ej. portal técnico— abre `BEGIN`/`COMMIT` propio); índice único parcial `uq_cae_documents_one_active_supporting_slot` (`database/migrations/2026_05_15_cae_documents_one_active_supporting_per_type.sql`).

Los **cuatro** tipos complementarios oficiales (`document_types`: `scope = 'technician_cae'`, `is_cae_file_type = FALSE`, `is_active = TRUE`) son **exactamente** estos nombres en base de datos:

1. **Certificado de estar al corriente con Hacienda**  
2. **Certificado de estar al corriente con Seguridad Social**  
3. **Póliza de Responsabilidad Civil**  
4. **Certificado de Prevención de Riesgos Laborales**  

> **Histórico de producto:** el cuarto documento sustituyó al antiguo tipo “Recibo RC”; en BD y código actual corresponde al certificado de **prevención de riesgos laborales** (nombre completo arriba), no a un recibo de póliza.

**Regla:** para **Generar CAE con IA** deben existir **los cuatro tipos** anteriores, cada uno con al menos un `cae_documents` **activo** (`is_active = TRUE`, `is_cae_file = FALSE`) vinculado al mismo `cae_record_id` que corresponda al CAE vigente del técnico.

Los **IDs** numéricos de `document_types` pueden variar entre entornos: la implementación debe resolver tipos por **nombre** (o por slug estable si se añade en el futuro), no hardcodear IDs en el doc.

---

## 2. Estados por fechas (`DocumentIntakeAiService::calcStatus`)

Se calculan a partir de `expires_at` e `issue_date` (formato `Y-m-d`).

| Condición | Estado resultante |
|-----------|-------------------|
| `expires_at` válida y **anterior a hoy** | `rejected` (caducado) |
| `expires_at` válida y **≤ 30 días** desde hoy | `in_review` (próximo a caducar) |
| `expires_at` válida y **> 30 días** | `approved` |
| Sin `expires_at` pero con `issue_date` | `in_review` |
| Sin fechas usables | `manual_review` |

**Persistencia:** en intake se guarda `ai_status` con estos valores; en documento publicado la caducidad va en `cae_documents.expires_at`.

**Caso especial Prevención de Riesgos:** en `CaeController::autoExpireFromIssue` la caducidad automática desde solo la fecha de emisión puede ser **null** si el documento requiere fecha fin explícita; eso empuja con frecuencia a **revisión manual** hasta tener `expires_at` clara.

---

## 3. ¿Cuándo va a revisión manual en la subida?

En `CaeController::uploadDocument` (documentos complementarios):

- `needsManual = true` si `calcStatus === 'manual_review'` **o** `expires_at === null` tras análisis y reglas automáticas.

Consecuencia: intake `pending_manual` y **no** se crea fila en `cae_documents` hasta aprobación manual (`approveIntake`).

---

## 4. Estado “aprobado” para **permitir generar CAE**

Un documento complementario cuenta como **válido para generación** solo si cumple **todas** las condiciones:

1. Existe fila en `cae_documents` con `is_active = TRUE`, `is_cae_file = FALSE`, y `document_type_id` corresponde a uno de los **cuatro nombres** de la sección 1.
2. `expires_at` **no es null** y **fecha ≥ hoy** (no caducado).
3. El estado por fechas inferido con la misma lógica que `calcStatus` aplicada a `expires_at` (y emisión si aplica) es **`approved`** (criterio **estricto** para el botón “Generar CAE”: **no** vale `in_review`, `rejected` ni `manual_review`).

### 4.1 Certificado de estar al corriente con **Hacienda**

Además de 1–3:

4. Verificación AEAT completada con éxito (columnas en `cae_documents` tras `AeatCotejoVerifierService`):
   - `aeat_cotejo_codigo = '1'` (cotejo SOAP correcto).
   - `aeat_pdf_validation_ok = TRUE`: el **PDF oficial** devuelto por AEAT (campo `binario` decodificado) cumple:
     - NIF/CIF del titular = `technicians.tax_id`,
     - resultado POSITIVO / al corriente,
     - vigencia no caducada (fecha extraída del PDF oficial; si OK, se actualiza `expires_at`).
   - Si la validación es OK, `storage_path` apunta al PDF oficial AEAT; el escaneo queda en `aeat_upload_backup_path`.
   - `aeat_cotejo_huella_ok` es **informativo** (comparación escaneo vs huella AEAT); **no** bloquea si difiere.

5. **Subida del escaneo (solo Hacienda):** en intake solo se exige extraer (o indicar manualmente) el **CSV AEAT** de 16 caracteres. No se validan fechas ni contenido del escaneo.
6. Tras publicar con CSV, se ejecuta AEAT; la vigencia y el resto de comprobaciones se hacen sobre el **PDF oficial** devuelto por AEAT.

Si falla extracción de CSV, cotejo ≠ 1, o validación del PDF oficial: el documento **no** cumple la regla de generación.

### 4.2 Certificado de estar al corriente con **Seguridad Social**

- Debe cumplir 1–3 (mismo listado que el resto de tipos del conjunto de cuatro).
- **Verificación automática adicional (p. ej. CSV / terceros):** no implementada; reservado para una versión futura del mismo checklist (paso posterior de producto).

### 4.3 Póliza de Responsabilidad Civil y Certificado de Prevención de Riesgos Laborales

- Deben cumplir solo 1–3 (fechas + estado `approved` estricto).
- No hay cotejo AEAT aplicable a estos tipos en el diseño actual.

---

## 5. Archivo principal CAE (`is_cae_file = TRUE`)

- No forma parte del checklist de los **cuatro complementarios**.
- La generación con IA crea o sustituye ese PDF según el flujo actual de la app.

---

## 6. Verificación AEAT: cuándo ejecutarla

- Solo tiene sentido automática para complementarios cuyo tipo sea **Certificado de estar al corriente con Hacienda** (PDF con CSV en texto).
- **Tras COMMIT / publicación** de la fila en `cae_documents` (`uploadDocument` admin auto, `approveIntake`, portal técnico auto), ejecutar el verificador vía **`CaeAeatUploadHook::afterSupportingPdfPersisted()`** cuando el tipo coincide con el resuelto en BD (`CaeReadinessService::resolveHaciendaDocumentTypeId`). También se puede disparar otros `document_types` listados opcionalmente en `aeat_csv_auto_verify_document_type_ids` en `config/app.php`.
- Actualiza `extracted_aeat_csv`, `aeat_cotejo_*`, etc. (migración `2026_05_14_cae_documents_aeat_cotejo.sql`).
- `aeat_cotejo_use_mock = true` en `config/app.php`: mismo flujo, respuesta simulada (desarrollo).
- Certificado FNMT real: cambio de configuración y certificado; **sin** alterar las reglas de negocio de las secciones 4 y 4.1.

---

## 7. Mensajes al bloquear “Generar CAE”

- Enumerar **por cada uno de los cuatro tipos** qué falla: falta documento, caducado, `in_review`, pendiente manual, Hacienda sin AEAT OK, etc.
- Preferible un mensaje claro por tipo frente a un único error genérico.

---

## 8. Estado de implementación (pasos 2–7)

- **Hecho:** Pasos **2–4** (readiness, slot único, AEAT hook). Paso **5–6–7** en **revisión CAE** (`history.php`): generación IA solo con complementarios del CAE vigente (**`CaeAiController::generate`** + **`CaeReadinessService`**), sin chips ni `new_docs`; mensajes de bloqueo multilínea; solicitud de documentos con **modal Bootstrap** (`modalCaeRequestDocsConfirm`) y `data-replaces-filename`; sin botón «Verificar» AEAT en el bloque IA (la verificación es automática al publicar + reglas readiness).
- **Hecho:** Estado unificado **«Válido» / «No válido»** vía **`CaeDocumentValidityService`** (misma lógica de fechas + AEAT en Hacienda que readiness): ficha técnico (`technicians/show`) con detalle desplegable; chips en revisión CAE con badge de validez; PRL marcado como opcional al generar; `CaeAiService::determineStatus` simplificado tras readiness.
- **Pendiente / siguiente fase:** Pruebas E2E; pulir textos restantes en otras pantallas si aparece lenguaje antiguo.
- **Hecho (post-builder):** `GET /admin/tecnicos/{id}/cae/ia/builder` redirige a la revisión CAE (`history#cae-manage`). Vista `cae.ai_builder` queda obsoleta (no se renderiza). Flash `pending_docs` en `save()` usa los cuatro tipos actuales.

Cualquier cambio de producto debe actualizar **primero** este archivo y después el código.

---

## 9. Deuda de texto en UI (opcional, no bloquea paso 2)

Algunos textos de la interfaz o mensajes de IA pueden seguir mencionando “Recibo RC”; los **tipos reales** y las reglas son los de la **sección 1** y la migración `2026_05_12_rename_doc_types.sql`.