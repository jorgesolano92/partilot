{{-- Captura HTML → imagen nitida: zoom del layout a 300 dpi + JPG comprimido. --}}
<script>
window.PartilotCaptureParticipationImage = (function () {
    var TARGET_DPI = 300;

    function mmToPx(mm, dpi) {
        return Math.round((mm / 25.4) * dpi);
    }

    function waitForAssets(root) {
        var imgs = Array.prototype.slice.call(root.querySelectorAll('img'));
        var imgWait = Promise.all(imgs.map(function (img) {
            if (img.complete && img.naturalWidth > 0) {
                return Promise.resolve();
            }
            return new Promise(function (resolve) {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            });
        }));
        var fontsWait = (document.fonts && document.fonts.ready)
            ? document.fonts.ready.catch(function () {})
            : Promise.resolve();
        return Promise.all([imgWait, fontsWait]);
    }

    function supportsZoom() {
        return typeof document.createElement('div').style.zoom !== 'undefined';
    }

    /**
     * Clon fuera de pantalla. Zoom re-pinta tipografía e imágenes a resolución alta
     * (a diferencia de html2canvas.scale, que solo amplía el bitmap borroso).
     */
    async function mountPrintClone(sourceEl, widthMm, heightMm, dpi) {
        var targetW = mmToPx(widthMm, dpi);
        var targetH = mmToPx(heightMm, dpi);
        var cssW = Math.max(1, sourceEl.getBoundingClientRect().width);
        var zoom = targetW / cssW;

        var host = document.createElement('div');
        host.setAttribute('aria-hidden', 'true');
        host.style.cssText = [
            'position:fixed',
            'left:-10000px',
            'top:0',
            'width:' + targetW + 'px',
            'height:' + targetH + 'px',
            'overflow:hidden',
            'background:#fff',
            'pointer-events:none',
            'z-index:-1',
        ].join(';');

        var clone = sourceEl.cloneNode(true);
        clone.removeAttribute('id');
        Array.prototype.forEach.call(clone.querySelectorAll('[id]'), function (n) {
            n.removeAttribute('id');
        });
        if (!clone.classList.contains('js-capture-root')) {
            clone.classList.add('js-capture-root');
        }
        clone.style.border = 'none';
        clone.style.margin = '0';
        clone.style.maxWidth = 'none';
        clone.style.background = '#ffffff';

        var useZoom = supportsZoom();
        if (useZoom) {
            clone.style.zoom = String(zoom);
        }

        host.appendChild(clone);
        document.body.appendChild(host);

        await waitForAssets(clone);
        await new Promise(function (r) {
            requestAnimationFrame(function () {
                requestAnimationFrame(r);
            });
        });

        return {
            host: host,
            clone: clone,
            targetW: targetW,
            targetH: targetH,
            zoom: zoom,
            useZoom: useZoom,
        };
    }

    function canvasToBlob(canvas, mime, quality) {
        return new Promise(function (resolve) {
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    resolve(blob);
                }, mime, quality);
                return;
            }
            var dataUrl = canvas.toDataURL(mime, quality);
            var parts = dataUrl.split(',');
            var bin = atob(parts[1] || '');
            var arr = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) {
                arr[i] = bin.charCodeAt(i);
            }
            resolve(new Blob([arr], { type: mime }));
        });
    }

    /**
     * @param {HTMLElement} el
     * @param {object} opts - widthMm, heightMm?, mime?, quality?, filename?, dpi?
     */
    async function download(el, opts) {
        opts = opts || {};
        var widthMm = opts.widthMm || 160;
        var heightMm = opts.heightMm || 92;
        var mime = opts.mime || 'image/jpeg';
        var dpi = opts.dpi || TARGET_DPI;
        var quality = typeof opts.quality === 'number' ? opts.quality : 0.85;
        var filename = opts.filename || ('captura.' + (mime === 'image/jpeg' ? 'jpg' : 'png'));

        await waitForAssets(el);

        var built = await mountPrintClone(el, widthMm, heightMm, dpi);
        var canvas;
        try {
            var h2cOpts = {
                backgroundColor: '#ffffff',
                useCORS: true,
                allowTaint: false,
                logging: false,
                imageTimeout: 20000,
                removeContainer: true,
                scrollX: 0,
                scrollY: 0,
            };

            if (built.useZoom) {
                // El clon ya está a tamaño de impresión: no volver a escalar
                h2cOpts.scale = 1;
                h2cOpts.width = built.targetW;
                h2cOpts.height = built.targetH;
                h2cOpts.windowWidth = built.targetW;
                h2cOpts.windowHeight = built.targetH;
            } else {
                // Firefox sin zoom: un solo scale hacia 300 dpi
                h2cOpts.scale = built.zoom;
            }

            canvas = await html2canvas(built.clone, h2cOpts);
        } finally {
            if (built.host && built.host.parentNode) {
                built.host.parentNode.removeChild(built.host);
            }
        }

        var out = document.createElement('canvas');
        out.width = built.targetW;
        out.height = built.targetH;
        var ctx = out.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(
            canvas,
            0,
            0,
            canvas.width,
            canvas.height,
            0,
            0,
            built.targetW,
            built.targetH
        );

        var blob = await canvasToBlob(out, mime, mime === 'image/jpeg' ? quality : undefined);
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.download = filename;
        link.href = url;
        link.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 2000);

        return { width: out.width, height: out.height, bytes: blob.size };
    }

    return { download: download, TARGET_DPI: TARGET_DPI };
})();
</script>
