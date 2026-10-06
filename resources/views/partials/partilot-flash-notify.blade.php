<script>
    window.partilotPageFlashes = @json($partilotPageFlashes ?? []);
    (function () {
        var hasShownPageFlashes = false;

        function showPartilotPageFlashes() {
            if (hasShownPageFlashes) {
                return;
            }
            if (typeof PNotify === 'undefined' || !Array.isArray(window.partilotPageFlashes)) {
                return;
            }

            var pending = window.partilotPageFlashes.filter(function (item) {
                return item && item.text;
            });
            if (!pending.length) {
                return;
            }

            hasShownPageFlashes = true;

            PNotify.prototype.options.styling = 'bootstrap3';
            PNotify.prototype.options.delay = 7000;
            PNotify.prototype.options.opacity = 1;
            PNotify.prototype.options.animate = false;
            PNotify.prototype.options.stack = {
                dir1: 'down',
                dir2: 'left',
                firstpos1: 90,
                firstpos2: 12
            };

            pending.forEach(function (item) {
                new PNotify({
                    title: item.title || '',
                    type: item.type || 'error',
                    addclass: 'partilot-notify' + (item.type === 'error' ? ' partilot-notify-error' : ''),
                    width: '460px',
                    text: item.text,
                    hide: true,
                    icon: false,
                    delay: item.type === 'error' ? 9000 : 7000,
                    buttons: {
                        closer: true,
                        sticker: false,
                        closer_hover: false
                    }
                });
            });

            window.partilotPageFlashes = [];
        }

        window.partilotShowPageFlashes = showPartilotPageFlashes;

        document.addEventListener('DOMContentLoaded', function () {
            window.setTimeout(function () {
                if (!document.documentElement.classList.contains('partilot-loading')) {
                    showPartilotPageFlashes();
                }
            }, 0);
        });

        document.addEventListener('click', function (e) {
            var closer = e.target.closest('.ui-pnotify .ui-pnotify-closer');
            if (!closer) {
                return;
            }
            var notice = closer.closest('.ui-pnotify');
            if (notice) {
                notice.remove();
            }
        });
    })();
</script>
