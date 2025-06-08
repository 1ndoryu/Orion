
function iniciarClonar() {
    console.log('iniciarClonar');
    const botonClonar = document.getElementById('clonarBoton');
    if (!botonClonar) return;

    const manejarClickClonar = async () => {
        const datosPeticion = { url_actual: window.location.href };
        botonClonar.disabled = true;
        botonClonar.textContent = 'Clonando...';

        try {
            const respuesta = await gloryAjax('clonarRepo', datosPeticion);
            if (respuesta && respuesta.success) {
                let mensaje = 'Página clonada con éxito.';
                if (respuesta.data && respuesta.data.nueva_url) {
                    mensaje += ' Nueva URL: ' + respuesta.data.nueva_url;
                }
                alert(mensaje);
            } else {
                alert('Error al clonar la página: ' + (respuesta && respuesta.message ? respuesta.message : 'Error desconocido.'));
            }
        } catch (error) {
            alert('Error en la petición de clonación.');
        } finally {
            botonClonar.disabled = false;
            botonClonar.textContent = 'Clonar';
        }
    };

    botonClonar.removeEventListener('click', manejarClickClonar);
    botonClonar.addEventListener('click', manejarClickClonar);
}

document.addEventListener('gloryRecarga', iniciarClonar);