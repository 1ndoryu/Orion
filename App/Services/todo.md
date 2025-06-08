# Flujo de Trabajo y Plan de Acción para Misiones con Validación

A continuación se detalla el flujo de trabajo actualizado que incluye el ciclo de validación, y la lista de tareas de desarrollo necesarias para implementarlo.

---

### Flujo de Trabajo Detallado (.md)

**Restricción Clave:** El ciclo de solicitud de contexto adicional al usuario solo puede ocurrir **una vez**. En el segundo intento, la IA debe tomar una decisión final. Si el contexto sigue siendo insuficiente, el proceso se cancela.

* **Fase 1: Intento Inicial**
    1.  **Entrada de Usuario:** El usuario proporciona una instrucción.
    2.  **Análisis Preliminar:** `analizarArchivosRelevantes` selecciona y resume un conjunto inicial de archivos, obteniendo también su contenido completo.
    3.  **Validación de Contexto:** Se invoca a `definirMisionConIA` (que ahora actúa como validador).
        * **Input:** Instrucción, resúmenes y contenido completo de los archivos.
        * **Output Esperado (JSON):** `{ "contextoSuficiente": bool, "archivosRelevantes": [], "archivosIrrelevantes": [], "motivoInsuficiencia": "..." }`

* **Fase 2: Bifurcación**
    * **CASO A: Contexto Suficiente**
        1.  La IA devuelve `contextoSuficiente: true`.
        2.  `comandoCrearMision` procede a llamar a `guardarMisionEnBD` usando solo los `archivosRelevantes`.
        3.  **FIN DEL PROCESO (ÉXITO).**

    * **CASO B: Contexto Insuficiente (Primer Intento)**
        1.  La IA devuelve `contextoSuficiente: false`.
        2.  `comandoCrearMision` **NO** crea la misión.
        3.  **Guarda el Estado:** Se persiste el estado actual en `user_meta` (ej: `metaMisionPendienteContexto`), almacenando la instrucción original, los archivos relevantes e irrelevantes ya identificados, y un flag que indique que estamos en la fase de re-análisis.
        4.  **Notifica al Cliente:** Se envía un mensaje por socket con `{ "accionRequerida": "pedirContextoAdicional", "mensaje": "...", "motivo": "..." }`.
        5.  El cliente (JS) activa un estado de espera para la próxima entrada del usuario.

* **Fase 3: Intento Final (Guiado por el Usuario)**
    1.  **Entrada del Usuario:** El usuario proporciona información adicional.
    2.  **Llamada AJAX Específica:** El cliente envía la nueva información con un parámetro especial (ej: `accion: 'continuarMision'`).
    3.  **Gestión de la Continuación:** `gestionarComandosIA` detecta esta acción y llama a una nueva función `comandoContinuarMision`.
    4.  **Re-análisis:** `comandoContinuarMision` recupera el estado guardado, analiza los nuevos archivos sugeridos y combina toda la información.
    5.  **Validación Definitiva:** Se vuelve a llamar a `definirMisionConIA` con el contexto enriquecido.
        * **Si `contextoSuficiente: true`:** Se crea la misión. Se limpia el estado temporal de `user_meta`. **FIN DEL PROCESO (ÉXITO).**
        * **Si `contextoSuficiente: false`:** **NO** se crea la misión. Se envía un mensaje final al usuario explicando por qué no se puede proceder. Se limpia el estado temporal de `user_meta`. **FIN DEL PROCESO (FALLO).**

---

### Lista de Tareas (.md)

#### Backend (PHP)

* [ ] **Modificar `obtenerOcrearResumenArchivo`:**
    * [ ] Debe devolver un array que contenga tanto el resumen como el contenido completo del archivo: `['resumen' => $resumen, 'contenido' => $contenido]`.

* [ ] **Modificar `analizarArchivosRelevantes`:**
    * [ ] Ajustar el bucle para que `archivosAnalizadosDetalles` almacene el array completo (`ruta`, `resumen`, `contenido`) devuelto por `obtenerOcrearResumenArchivo`.
    * [ ] La función debe devolver esta estructura de datos enriquecida.

* [ ] **Refactorizar `definirMisionConIA`:**
    * [ ] Modificar los parámetros que acepta para recibir la estructura completa de los archivos analizados (incluyendo contenido).
    * [ ] Actualizar el prompt del sistema para que realice la **validación de contexto** (solicitando `contextoSuficiente`, `archivosRelevantes`, etc.) en lugar de definir la misión directamente.
    * [ ] Añadir un segundo prompt que solo se usará si `contextoSuficiente` es `true`, para entonces sí definir `nombreMision`, `objetivoMision`, etc.
    * [ ] La función debe devolver una estructura que indique el resultado de la validación. Ej: `['validacionOk' => bool, 'datos' => ..., 'motivoFallo' => '...']`.

* [ ] **Modificar `comandoCrearMision` (Orquestador Principal):**
    * [ ] Tras llamar a `analizarArchivosRelevantes`, debe invocar la nueva lógica de `definirMisionConIA`.
    * [ ] Implementar la lógica de bifurcación:
        * Si la validación es exitosa, proceder a `guardarMisionEnBD`.
        * Si la validación falla por falta de contexto:
            * [ ] Crear una función para guardar el estado de la misión pendiente en `user_meta`.
            * [ ] Enviar el mensaje por socket con `accionRequerida: 'pedirContextoAdicional'`.

* [ ] **Modificar `ajaxIA` y `gestionarComandosIA`:**
    * [ ] `ajaxIA` debe poder recibir un nuevo parámetro en el `$_POST`, como `accion: 'continuarMision'`.
    * [ ] `gestionarComandosIA` debe tener un `if` al principio para detectar esta nueva acción y derivarla a una nueva función, evitando el flujo de detección de comando normal.

* [ ] **Crear `comandoContinuarMision`:**
    * [ ] Será la función llamada por `gestionarComandosIA` para el segundo intento.
    * [ ] Debe leer el estado guardado en `user_meta`.
    * [ ] Debe procesar la nueva información del usuario y analizar los archivos adicionales.
    * [ ] Debe volver a llamar a `definirMisionConIA` para la validación final.
    * [ ] Debe manejar ambos resultados finales (éxito o fallo definitivo).
    * [ ] Debe limpiar el estado temporal de `user_meta` en ambos casos.

#### Frontend (JavaScript)

* [ ] **Actualizar el Manejador de Sockets:**
    * [ ] Añadir lógica para detectar el payload `{ "accionRequerida": "pedirContextoAdicional" }`.
    * [ ] Al recibirlo, establecer una variable de estado global (ej: `window.estadoMision = 'esperandoContexto'`).

* [ ] **Actualizar la Lógica de Envío de Mensajes (Wrapper de `GloryAjax`):**
    * [ ] Antes de enviar un mensaje, comprobar el valor de `window.estadoMision`.
    * [ ] Si es `'esperandoContexto'`, modificar el payload de la petición AJAX para que incluya la acción de continuación (ej: `{ accion: 'continuarMision', instruccion: '...' }`).
    * [ ] Restablecer el estado `window.estadoMision = null` después de enviar la petición.


## Tareas para despues

- [x] Las misiones necesitan crearse como posttype
- [x] Mensajes en tiempo real
- [ ] Los resumenes tiene que ser mas detallados, posibles fallos, posibles oportunidades de refactorizacion.
- [ ] Busqueda de función. 
- [ ] Comando limpiar cache
- [ ] El analisis final para crear una mision debe contener todo los contenido de los archivos
- [ ] Mensajes en tiempo real para los demas comandos
- [ ] Poder elegir un repositorio y empezar chat en base al repositorio, poder tener varios repositorios.
- [ ] Remplazar comando eliminar por comando reset (borra misiones, resumenes, y repositorio)
- [ ] El chat, los mensajes no aparecen en orden.