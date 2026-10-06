/**
 * Sistema de persistencia de imágenes para formularios
 * Mantiene la preview de imagen incluso después de errores de validación
 */

(function() {
    'use strict';

    // Base64 ocupa ~33% más; por encima de este tamaño no se guarda en localStorage (cuota ~5 MB por origen).
    var MAX_STORED_CHARS = 1500000;
    var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    window.partilotImageUpload = {
        accept: ALLOWED_TYPES.join(','),

        /** Devuelve '' si el archivo es válido; si no, el mensaje para el usuario. */
        validate: function(file, maxKb) {
            if (!file) return '';
            if (ALLOWED_TYPES.indexOf(String(file.type || '').toLowerCase()) === -1) {
                return 'Formato no permitido. Usa una imagen JPG, PNG, GIF o WEBP (SVG no está permitido).';
            }
            var limitKb = maxKb || 10240;
            if (file.size > limitKb * 1024) {
                return 'La imagen supera el tamaño máximo permitido (' + Math.round(limitKb / 1024 * 10) / 10 + ' MB).';
            }
            return '';
        },

        /** Guarda la vista previa si cabe; nunca lanza excepción. */
        store: function(key, dataUrl) {
            try {
                if (!dataUrl || dataUrl.length > MAX_STORED_CHARS) {
                    localStorage.removeItem(key);
                    return false;
                }
                localStorage.setItem(key, dataUrl);
                return true;
            } catch (err) {
                try { localStorage.removeItem(key); } catch (ignored) {}
                return false;
            }
        },

        /** Aplica accept restringido y validación a un input file; devuelve false y limpia el input si no es válido. */
        check: function(input, maxKb) {
            var file = input && input.files ? input.files[0] : null;
            var message = window.partilotImageUpload.validate(file, maxKb);
            if (message) {
                input.value = '';
                alert(message);
                return false;
            }
            return true;
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[type="file"][accept="image/*"]').forEach(function(input) {
            input.setAttribute('accept', window.partilotImageUpload.accept);
        });
    });

    /**
     * Inicializar persistencia de imagen para un input file
     * @param {string} inputId - ID del input file
     * @param {string} previewId - ID del elemento de preview
     * @param {string} sessionKey - Clave de sesión para guardar temporalmente
     */
    window.initImagePersistence = function(inputId, previewId, sessionKey) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        if (!input || !preview) return;

        // Cargar imagen guardada en localStorage si existe
        const savedImage = localStorage.getItem(`image_${sessionKey}`);
        if (savedImage && !input.files.length) {
            preview.style.backgroundImage = `url(${savedImage})`;
            if (preview.querySelector('i')) {
                preview.querySelector('i').style.display = 'none';
            }
        }

        // Guardar imagen en localStorage cuando se selecciona
        input.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                if (!window.partilotImageUpload.check(e.target)) return;
                const reader = new FileReader();
                reader.onload = function(event) {
                    const imageData = event.target.result;
                    window.partilotImageUpload.store(`image_${sessionKey}`, imageData);
                    
                    // Actualizar preview
                    preview.style.backgroundImage = `url(${imageData})`;
                    if (preview.querySelector('i')) {
                        preview.querySelector('i').style.display = 'none';
                    }
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Limpiar al enviar el formulario exitosamente
        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                // No limpiar aquí, se limpiará cuando se cargue la página de éxito
                // O se puede limpiar después de un tiempo
                setTimeout(() => {
                    localStorage.removeItem(`image_${sessionKey}`);
                }, 1000);
            });
        }
    };

    /**
     * Función helper para preview de imagen (compatible con código existente)
     */
    window.previewImage = function(input, previewId) {
        const preview = document.getElementById(previewId || 'image-preview');
        if (!preview) return;

        if (input.files && input.files[0]) {
            if (!window.partilotImageUpload.check(input)) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.style.backgroundImage = `url(${e.target.result})`;
                if (preview.querySelector('i')) {
                    preview.querySelector('i').style.display = 'none';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    /**
     * Función helper para eliminar preview de imagen
     */
    window.removeImagePreview = function(previewId, inputId) {
        const preview = document.getElementById(previewId);
        const input = document.getElementById(inputId);
        
        if (input) {
            input.value = '';
        }
        
        if (preview) {
            preview.style.backgroundImage = 'none';
            const icon = preview.querySelector('i');
            if (icon) {
                icon.style.display = 'block';
            } else {
                preview.innerHTML = '<i class="ri-image-add-line"></i>';
            }
        }

        // Limpiar de localStorage si existe
        if (inputId) {
            const sessionKey = inputId.replace('image-input-', '');
            localStorage.removeItem(`image_${sessionKey}`);
        }
    };

})();
