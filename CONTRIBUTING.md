# CONTRIBUTING.md

## Filosofía

Este archivo describe el workflow con el repositorio remoto y las convenciones de trabajo entre el desarrollador, Claude como co-arquitecto y el agente de codeo. El objetivo es que en cualquier momento el estado del repositorio refleje exactamente qué está hecho, qué está en curso y qué sigue.

El agente de codeo nunca hace commit ni push sin confirmación explícita del desarrollador. Ningún paso de git se ejecuta de forma anticipada ni automática.

---

## Estructura de ramas

```
main          — solo código que pasó el quality gate completo
dev           — integración continua, rama de trabajo habitual
feature/xxx   — una rama por unidad de trabajo, sale de dev
spike/xxx     — una rama por spike de infraestructura, sale de dev
fix/xxx       — una rama por fix, sale de dev
```

Nunca se commitea directo a `main`. Los merges a `main` son siempre desde `dev` y solo cuando el quality gate pasó completo.

---

## Workflow para feature branch y spike branch

### Fase 1 — Implementación

```bash
1. Crear rama desde dev
   git checkout dev && git pull
   git checkout -b feature/nombre-de-la-unidad
   # o: git checkout -b spike/nombre-del-spike
```

El agente implementa la unidad en esa rama exclusivamente.

Al terminar la implementación, el agente:
- Ejecuta el quality gate y reporta el resultado
- Avisa al desarrollador que la implementación está lista
- Espera confirmación del desarrollador antes de cualquier acción adicional

El agente no hace commit en este momento. No hace push. No hace merge. Espera.

### Fase 2 — Smoke tests y correcciones

El desarrollador ejecuta los smoke tests manuales definidos en el task file de la unidad.

Si el desarrollador encuentra errores o comportamiento incorrecto:
- El agente aplica las correcciones necesarias en la misma rama
- Ejecuta el quality gate nuevamente y reporta resultados
- Avisa al desarrollador que las correcciones están listas
- Vuelve a esperar confirmación del desarrollador

Este ciclo se repite todas las veces que sea necesario hasta que el desarrollador confirme que todo funciona correctamente. El agente no hace commit entre correcciones. Todos los cambios de la fase de correcciones se acumulan sin commitear hasta la confirmación final.

### Fase 3 — Commit y merge (solo tras confirmación del desarrollador)

Cuando el desarrollador confirma que los smoke tests pasan y la unidad está completa:

```bash
2. Commit de la feature branch
   git add .
   git commit -m "feat: descripción de la unidad"
   # o: git commit -m "spike: descripción del spike"

3. Merge a dev
   git checkout dev
   git pull

   # Si hubo commits intermedios sucios (WIP, pruebas, etc.):
   git merge --squash feature/nombre-de-la-unidad
   git commit -m "feat: descripción de la unidad"

   # Si los commits intermedios son limpios y tienen historia útil:
   git merge --no-ff feature/nombre-de-la-unidad

4. Eliminar la rama de feature
   git branch -d feature/nombre-de-la-unidad

5. Push de dev
   git push origin dev
```

### Fase 4 — Documentación

```bash
6. Actualizar CONTEXT.md con lo implementado en esta unidad
   — qué se hizo, qué decisiones se tomaron, qué no tocar

7. Si hubo decisiones o cambios arquitectónicos durante la implementación,
   actualizar ARCHITECTURE.md antes de commitear

8. Mover el task file a tasks/done/

9. Commit de la documentación
   git add CONTEXT.md ARCHITECTURE.md tasks/done/
   git commit -m "chore: update CONTEXT.md post feature-XX"
   git push origin dev
```

### Fase 5 — Merge a main y sincronización

```bash
10. Rebasar dev sobre main
    git checkout dev
    git rebase main

11. Avanzar main con fast-forward
    git checkout main
    git merge --ff-only dev

12. Subir main
    git push origin main

13. Subir dev (el rebase reescribió commits, requiere push forzado)
    git push origin dev --force-with-lease

14. Verificar que local y remoto están alineados
    git fetch --all
    git status
    # ambas ramas deben reportar "up to date"
```

Usar siempre `--force-with-lease` en lugar de `--force`. La diferencia: `--force-with-lease` falla si alguien subió cambios a la rama remota mientras tanto, evitando pisar trabajo ajeno.

---

## Workflow para fix branch

### Fase 1 — Implementación

```bash
1. Crear rama desde dev
   git checkout dev && git pull
   git checkout -b fix/nombre-del-fix
```

El agente implementa el fix en esa rama exclusivamente. Al terminar, ejecuta el quality gate, reporta resultados y espera confirmación del desarrollador. No hace commit. Espera.

### Fase 2 — Smoke tests y correcciones

El desarrollador verifica que el fix resuelve el problema reportado. Si el fix es incompleto o genera nuevos problemas, el agente aplica correcciones, reporta y vuelve a esperar. Este ciclo se repite hasta que el desarrollador confirme.

### Fase 3 — Commit y merge (solo tras confirmación del desarrollador)

```bash
2. Commit de la fix branch
   git add .
   git commit -m "fix: descripción del fix"

3. Merge a dev
   git checkout dev
   git pull
   git merge --no-ff fix/nombre-del-fix

4. Eliminar la rama de fix
   git branch -d fix/nombre-del-fix

5. Push de dev
   git push origin dev
```

### Fase 4 — Documentación

```bash
6. Actualizar CONTEXT.md si el fix implica algo que no debe repetirse
   o una decisión técnica nueva

7. Mover el task file a tasks/done/

8. Commit de la documentación
   git add CONTEXT.md tasks/done/
   git commit -m "chore: update CONTEXT.md post fix-XX"
   git push origin dev
```

### Fase 5 — Merge a main y sincronización

Igual que en el workflow de feature branch, pasos 10–14.

---

## Quality gate

No hay excepciones. Una unidad no está cerrada hasta que el desarrollador confirma que pasa todo esto.

```bash
composer test           → todos los tests de Pest en verde
composer analyse        → Larastan nivel 5 sin errores
smoke test              → comportamiento observable verificado manualmente por el desarrollador
```

Los scripts `test` y `analyse` se definen en `composer.json`:

```json
"scripts": {
    "test": "pest",
    "analyse": "phpstan analyse --level=5"
}
```

### Smoke tests

Cada task file define sus propios smoke tests manuales en la sección "Criterio de done". Los smoke tests son la única forma de contrastar lo que el agente dice que funciona con lo que realmente funciona. No se omiten bajo ninguna circunstancia. En unidades de solo lógica sin UI, el task file documenta explícitamente que los smoke tests de esa unidad son verificables desde tinker o desde un comando artisan simple.

---

## Versionado

### Formato

`major.minor.fix` — tres números separados por punto. Ejemplo: `0.1.0`

- **major**: solo cambia cuando el desarrollador decide que el package alcanzó un estado estable para release público
- **minor**: se incrementa en 1 por cada task file de feature o spike completado y mergeado a `dev`
- **fix**: se incrementa en 1 por cada task file de fix o refactor completado y mergeado a `dev`

### Dónde vive

La versión vive exclusivamente en git tags. El campo `version` fue eliminado de `composer.json` porque `composer validate --strict` lo rechaza para paquetes distribuidos via Packagist. El agente crea el tag al cerrar la unidad (Fase 5), después del merge a `main`. El tag sigue el formato `v{major}.{minor}.{fix}`, ejemplo: `v0.4.0`.

### Regla de decisión del agente

El agente siempre pregunta antes de incrementar la versión:

> ¿Incremento el minor a `0.X+1.0` o esta feature representa un milestone que merece bump de major?

Solo el desarrollador decide si una feature cierra un ciclo completo. El agente nunca asume un bump de major sin consultar.

### Versión actual

```
0.1.0 — documentación inicial (ARCHITECTURE.md, CONTEXT.md, CONTRIBUTING.md)
```

---

## Convención de commits

```
feat:   nueva funcionalidad o unidad completada
fix:    corrección de bug dentro de una unidad en curso
spike:  resultado de un spike de infraestructura
chore:  actualización de CONTEXT.md, ARCHITECTURE.md, task files
test:   agregado o corrección de tests sin cambio de lógica
```

Ejemplos correctos:
- `feat: implementar JsonConnection con CRUD básico`
- `fix: corregir normalización de columnas prefijadas en applyWhere`
- `test: cubrir operadores de where en WhereOperatorsTest`
- `chore: update CONTEXT.md post feature-01`

Ejemplos incorrectos:
- `cambios varios`
- `WIP`
- `Fix bug`

Mensajes y descripciones de commit siempre en español.

---

## Archivos que siempre van al agente

En cada sesión nueva el agente recibe exactamente estos tres archivos y nada más:

```
ARCHITECTURE.md          — siempre, sin excepción
CONTEXT.md               — siempre, sin excepción
tasks/unidad-en-curso.md — solo el de la unidad activa
```

Nunca se le pasan task files de unidades futuras ni de unidades cerradas.

---

## Archivos de tasks

Viven en `tasks/`. Se archivan en `tasks/done/` cuando la unidad está cerrada. No se modifican retroactivamente.

### Convención de nomenclatura

Formato: `[tipo]-[número]-[nombre].md`

| Tipo | Prefijo | Ejemplo |
|------|---------|---------|
| Feature | `feature` | `feature-01-core-driver.md` |
| Spike | `spike` | `spike-01-json-driver.md` |
| Fix | `fix` | `fix-01-where-null.md` |
| Refactor | `refactor` | `refactor-01-storage-interface.md` |

Reglas:
- El número siempre tiene dos dígitos (01, 02, ..., 15, etc.)
- El nombre describe brevemente la unidad en kebab-case
- El agente numera automáticamente según el último archivo del mismo tipo en `tasks/done/`

---

## Regla de los 3 intentos

Si el agente no logra resolver un problema después de 3 prompts concretos, se detiene. El desarrollador interviene manualmente en esa parte puntual, estabiliza, y el agente retoma para lo que sigue. Los bugs persistentes casi siempre son un problema de diseño, no de código.
