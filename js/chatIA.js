function configurarChatIA() {
    const bloqueChat = document.getElementById('bloqueTestChat');
    if (!bloqueChat) return;

    const contenedorConversacion = bloqueChat.querySelector('.Conversacion');
    const inputMensaje = bloqueChat.querySelector('#mensajeUsuarioChat');
    const botonEnviar = bloqueChat.querySelector('#enviarMensajeChat');
    const estadoConexion = document.createElement('div');
    estadoConexion.className = 'estado-conexion-ws';
    bloqueChat.insertBefore(estadoConexion, bloqueChat.firstChild);

    const idUsuario = window.gloryAjaxNavConfig?.idUsuario || null;

    const componentesFaltantes = [];
    if (!contenedorConversacion) componentesFaltantes.push('.Conversacion');
    if (!inputMensaje) componentesFaltantes.push('#mensajeUsuarioChat');
    if (!botonEnviar) componentesFaltantes.push('#enviarMensajeChat');
    if (!idUsuario) componentesFaltantes.push('idUsuario');

    if (componentesFaltantes.length) {
        estadoConexion.textContent = `Error: Faltan componentes del chat: ${componentesFaltantes.join(', ')}.`;
        estadoConexion.style.color = 'red';
        console.error(`Error de inicialización del chat: Faltan componentes del DOM o idUsuario: ${componentesFaltantes.join(', ')}.`);
        return;
    }

    let socket;

    function conectarWebSocket() {
        socket = new WebSocket('ws://refactor.local:8080');

        socket.onopen = function () {
            estadoConexion.textContent = 'Conectado';
            estadoConexion.style.color = 'green';
            console.log('WebSocket conectado.');
            socket.send(
                JSON.stringify({
                    accion: 'registrar',
                    idUsuario: idUsuario
                })
            );
        };

        socket.onmessage = function (event) {
            const datos = JSON.parse(event.data);
            const elementoPensando = document.querySelector('.mensaje-info');
            if (elementoPensando) elementoPensando.remove(); // Procesar y mostrar la respuesta recibida en tiempo real desde el backend

            if (datos.prompts) {
                for (const prompt of Object.values(datos.prompts)) {
                    agregarMensajeInterfaz(prompt, 'prompt-sistema');
                }
            }
            if (datos.respuestasIaIntermedias) {
                for (const [key, value] of Object.entries(datos.respuestasIaIntermedias)) {
                    if (key === 'contextoRecuperado' || key.startsWith('estadoAnalisis')) {
                        agregarMensajeInterfaz(value, 'info');
                    } else {
                        agregarMensajeInterfaz(value, 'respuesta-intermedia-ia');
                    }
                }
            } // Se da prioridad a la propiedad 'tipo' del payload para definir el estilo del mensaje.

            const tipoRemitente = datos.tipo || (datos.success ? 'ia' : 'error'); // La lógica principal ahora se centra en `datos.mensaje`. // Se evita procesar de nuevo si el payload ya fue manejado por las estructuras // complejas (`prompts`, `respuestasIaIntermedias`) para evitar duplicados.

            if (!datos.prompts && !datos.respuestasIaIntermedias) {
                if (typeof datos.mensaje !== 'undefined') {
                    // Usa el contenido de 'mensaje' directamente, no todo el objeto 'datos'.
                    agregarMensajeInterfaz(datos.mensaje, tipoRemitente);
                } else {
                    // Fallback para formatos antiguos que no contienen 'mensaje'.
                    const mensajePrincipal = datos.datos || datos;
                    agregarMensajeInterfaz(mensajePrincipal, tipoRemitente);
                }
            }
        };

        socket.onclose = function (event) {
            estadoConexion.textContent = 'Desconectado. Intentando reconectar...';
            estadoConexion.style.color = 'orange';
            console.log('WebSocket desconectado. Intentando reconectar en 3 segundos...', event.reason);
            setTimeout(conectarWebSocket, 3000);
        };

        socket.onerror = function (error) {
            estadoConexion.textContent = 'Error de conexión.';
            estadoConexion.style.color = 'red';
            console.error('Error en WebSocket:', error);
            socket.close();
        };
    }

    function agregarMensajeInterfaz(mensaje, tipoRemitente) {
        const elementoMensaje = document.createElement('div');
        elementoMensaje.classList.add('mensaje', `mensaje-${tipoRemitente}`);

        const parrafoMensaje = document.createElement('p');
        let contenidoTexto = typeof mensaje === 'object' && mensaje !== null ? JSON.stringify(mensaje, null, 2) : mensaje;

        parrafoMensaje.innerHTML = contenidoTexto.replace(/\n/g, '<br>');

        elementoMensaje.appendChild(parrafoMensaje);
        contenedorConversacion.appendChild(elementoMensaje);
        contenedorConversacion.scrollTop = contenedorConversacion.scrollHeight;

        if (tipoRemitente === 'prompt-sistema' && elementoMensaje.scrollHeight > elementoMensaje.clientHeight + 1) {
            const botonVerCompleto = document.createElement('button');
            botonVerCompleto.textContent = 'Ver completo';
            botonVerCompleto.classList.add('boton-ver-completo');
            botonVerCompleto.onclick = function () {
                elementoMensaje.classList.toggle('expandido');
                this.textContent = elementoMensaje.classList.contains('expandido') ? 'Ver menos' : 'Ver completo';
            };
            elementoMensaje.appendChild(botonVerCompleto);
        }
    }

    async function procesarEnvioMensaje() {
        const mensajeUsuario = inputMensaje.value.trim();
        if (mensajeUsuario === '') return;

        agregarMensajeInterfaz(mensajeUsuario, 'usuario');
        inputMensaje.value = '';
        inputMensaje.disabled = true;
        botonEnviar.disabled = true;

        agregarMensajeInterfaz('IA está pensando...', 'info');

        try {
            const respuestaAjax = await GloryAjax('ajaxIA', {instruccion: mensajeUsuario});
            if (!respuestaAjax.success) {
                const elementoPensando = document.querySelector('.mensaje-info');
                if (elementoPensando) elementoPensando.remove();
                agregarMensajeInterfaz(respuestaAjax.data.mensaje || 'Error al iniciar el proceso.', 'error');
            }
        } catch (error) {
            const elementoPensando = document.querySelector('.mensaje-info');
            if (elementoPensando) elementoPensando.remove();
            agregarMensajeInterfaz('Error crítico al conectar con el servicio de IA.', 'error');
            console.error('Error en la llamada AJAX inicial:', error);
        } finally {
            inputMensaje.disabled = false;
            botonEnviar.disabled = false;
            inputMensaje.focus();
        }
    }

    botonEnviar.addEventListener('click', procesarEnvioMensaje);
    inputMensaje.addEventListener('keypress', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            procesarEnvioMensaje();
        }
    });

    conectarWebSocket();
}

// Asegurarse de que el DOM esté cargado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', configurarChatIA);
} else {
    configurarChatIA();
}
