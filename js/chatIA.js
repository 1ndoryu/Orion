function configurarChatIA() {
    const bloqueChat = document.getElementById('bloqueTestChat');
    if (!bloqueChat) {
        return;
    }

    const contenedorConversacion = bloqueChat.querySelector('.Conversacion');
    const inputMensaje = bloqueChat.querySelector('#mensajeUsuarioChat');
    const botonEnviar = bloqueChat.querySelector('#enviarMensajeChat');

    if (!contenedorConversacion || !inputMensaje || !botonEnviar) {
        return;
    }

    botonEnviar.addEventListener('click', procesarEnvioMensaje);
    inputMensaje.addEventListener('keypress', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            procesarEnvioMensaje();
        }
    });

    function agregarMensajeInterfaz(mensaje, tipoRemitente) {
        const elementoMensaje = document.createElement('div');
        elementoMensaje.classList.add('mensaje', `mensaje-${tipoRemitente}`);

        const parrafoMensaje = document.createElement('p');
        parrafoMensaje.textContent = (typeof mensaje === 'object' && mensaje !== null) 
            ? JSON.stringify(mensaje, null, 2) 
            : mensaje;
            
        elementoMensaje.appendChild(parrafoMensaje);
        contenedorConversacion.appendChild(elementoMensaje);

        if (tipoRemitente === 'prompt-sistema' && elementoMensaje.scrollHeight > elementoMensaje.clientHeight + 1) {
            const botonVerCompleto = document.createElement('button');
            botonVerCompleto.textContent = 'Ver completo';
            botonVerCompleto.classList.add('boton-ver-completo');
            botonVerCompleto.addEventListener('click', function () {
                elementoMensaje.classList.toggle('expandido');
                this.textContent = elementoMensaje.classList.contains('expandido') ? 'Ver menos' : 'Ver completo';
            });
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

        const idMensajePensando = `pensando-${Date.now()}`;
        agregarMensajeInterfaz('IA está pensando...', 'info');
        const mensajePensandoActual = contenedorConversacion.querySelector('.mensaje-info:last-child');
        if (mensajePensandoActual) {
            mensajePensandoActual.id = idMensajePensando;
        }

        try {
            const respuestaAjax = await GloryAjax('ajaxIA', { instruccion: mensajeUsuario });
            const elementoPensando = document.getElementById(idMensajePensando);
            if (elementoPensando) elementoPensando.remove();

            if (!respuestaAjax || typeof respuestaAjax.data === 'undefined') {
                agregarMensajeInterfaz('No se recibió una respuesta válida del servidor.', 'error');
                return;
            }
            
            const datosRespuesta = respuestaAjax.data;

            // Mostrar prompts y respuestas intermedias con lógica mejorada
            if (datosRespuesta.prompts) {
                for (const prompt of Object.values(datosRespuesta.prompts)) {
                    agregarMensajeInterfaz(prompt, 'prompt-sistema');
                }
            }
            if (datosRespuesta.respuestasIaIntermedias) {
                for (const [key, value] of Object.entries(datosRespuesta.respuestasIaIntermedias)) {
                    // Lógica para dar formato especial a mensajes de estado
                    if (key === 'contextoRecuperado' || key.startsWith('estadoAnalisis')) {
                        agregarMensajeInterfaz(value, 'info');
                    } else {
                        agregarMensajeInterfaz(value, 'respuesta-intermedia-ia');
                    }
                }
            }

            // Mostrar mensaje principal (éxito o error)
            const mensajePrincipal = datosRespuesta.mensaje || datosRespuesta.datos || datosRespuesta;
            const tipoMensaje = respuestaAjax.success ? 'ia' : 'error';
            
            // Evitar mostrar el objeto de datos completo si ya se mostró el mensaje
            if(datosRespuesta.mensaje){
                 agregarMensajeInterfaz(datosRespuesta, tipoMensaje);
            } else {
                 agregarMensajeInterfaz(mensajePrincipal, tipoMensaje);
            }

        } catch (error) {
            const elementoPensando = document.getElementById(idMensajePensando);
            if (elementoPensando) elementoPensando.remove();
            let mensajeErrorCatch = 'Error crítico al conectar con el servicio de IA.';
            if (error && error.message) {
                mensajeErrorCatch += `\nDetalles: ${error.message}`;
            }
            agregarMensajeInterfaz(mensajeErrorCatch, 'error');
            console.error('Error en procesarEnvioMensaje (catch):', error);
        } finally {
            inputMensaje.disabled = false;
            botonEnviar.disabled = false;
            inputMensaje.focus();
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', configurarChatIA);
} else {
    configurarChatIA();
}
