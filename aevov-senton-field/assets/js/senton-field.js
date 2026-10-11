/*global AevovSentonField */
(function () {
    'use strict';

    var TWO_PI = Math.PI * 2;
    var state = null;
    var poll = null;

    function api(path, options) {
        options = options || {};
        options.headers = options.headers || { 'Content-Type': 'application/json', 'X-WP-Nonce': AevovSentonField.nonce };
        options.credentials = 'same-origin';
        return fetch(AevovSentonField.root + path, options).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok) {
                    throw body;
                }
                return body;
            });
        });
    }

    function $(id) {
        return document.getElementById(id);
    }

    function refresh() {
        return api('/field').then(function (body) {
            state = body;
            paintStats(body);
            paintField(body);
            paintSpark(body);
        }).catch(function (err) {
            out(typeof err === 'string' ? err : 'field unreachable');
        });
    }

    function out(message) {
        var node = $('sf-out');
        if (node) {
            node.textContent = typeof message === 'string' ? message : JSON.stringify(message, null, 2);
        }
    }

    function paintStats(body) {
        var f = body.field;
        setText('sf-tick', f.tick);
        setText('sf-coherence', f.coherence);
        setText('sf-delta', (f.delta_m > 0 ? '+' : '') + f.delta_m);
        setText('sf-reputation', f.reputation);
        setText('sf-signature', f.signature);
        setText('sf-peers', body.peer_count);

        var delta = $('sf-delta');
        if (delta) {
            delta.className = f.delta_m >= 0 ? 'sf-up' : 'sf-down';
        }
    }

    function setText(id, value) {
        var node = $(id);
        if (node) {
            node.textContent = value;
        }
    }

    /**
     * Each oscillator is a spoke; its fill is how close its phase sits to the field's
     * mean phase, so the ring visibly locks as R climbs.
     */
    function paintField(body) {
        var canvas = $('aevov-senton-canvas');
        if (!canvas || !canvas.getContext) {
            return;
        }

        var ctx = canvas.getContext('2d');
        var size = canvas.width;
        var centre = size / 2;
        var radius = centre * 0.74;
        var phases = body.phases || [];
        var psi = body.field.psi;
        var R = body.field.coherence;

        ctx.clearRect(0, 0, size, size);

        ctx.strokeStyle = 'rgba(148,163,184,0.25)';
        ctx.beginPath();
        ctx.arc(centre, centre, radius, 0, TWO_PI);
        ctx.stroke();

        var meanX = 0;
        var meanY = 0;

        for (var i = 0; i < phases.length; i++) {
            var angle = phases[i];
            var x = centre + radius * Math.cos(angle);
            var y = centre + radius * Math.sin(angle);
            var alignment = 0.5 + 0.5 * Math.cos(angle - psi);

            ctx.strokeStyle = 'rgba(34,211,238,' + (0.08 + (0.5 * alignment * R)).toFixed(3) + ')';
            ctx.beginPath();
            ctx.moveTo(centre, centre);
            ctx.lineTo(x, y);
            ctx.stroke();

            ctx.fillStyle = 'rgba(34,211,238,' + (0.25 + (0.75 * alignment)).toFixed(3) + ')';
            ctx.beginPath();
            ctx.arc(x, y, 3 + (3 * alignment), 0, TWO_PI);
            ctx.fill();

            meanX += Math.cos(angle);
            meanY += Math.sin(angle);
        }

        var orderX = centre + (radius * 0.82 * meanX) / Math.max(1, phases.length);
        var orderY = centre + (radius * 0.82 * meanY) / Math.max(1, phases.length);

        ctx.strokeStyle = '#f0abfc';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(centre, centre);
        ctx.lineTo(orderX, orderY);
        ctx.stroke();
        ctx.lineWidth = 1;

        ctx.fillStyle = '#e2e8f0';
        ctx.font = '14px ui-monospace, monospace';
        ctx.textAlign = 'center';
        ctx.fillText('R = ' + R.toFixed(3), centre, size - 12);
    }

    function paintSpark(body) {
        var canvas = $('aevov-senton-spark');
        if (!canvas || !canvas.getContext) {
            return;
        }

        var ctx = canvas.getContext('2d');
        var history = body.history || [];
        var width = canvas.width;
        var height = canvas.height;

        ctx.clearRect(0, 0, width, height);
        ctx.strokeStyle = 'rgba(148,163,184,0.3)';
        ctx.beginPath();
        ctx.moveTo(0, height - 1);
        ctx.lineTo(width, height - 1);
        ctx.stroke();

        if (history.length < 2) {
            return;
        }

        ctx.strokeStyle = '#22d3ee';
        ctx.beginPath();
        for (var i = 0; i < history.length; i++) {
            var x = (i / (history.length - 1)) * width;
            var y = height - (history[i] * (height - 6)) - 3;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();
    }

    function bind() {
        var form = $('aevov-senton-perceive');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var input = $('sf-input').value.trim();
            var salience = $('sf-salience').value;
            if (!input) {
                out('say something first');
                return;
            }

            api('/perceive', {
                method: 'POST',
                body: JSON.stringify({ input: input, salience: salience === '' ? null : parseFloat(salience) })
            }).then(function (body) {
                out(body);
                return refresh();
            }).catch(function (err) {
                out(err.message || err);
            });
        });

        $('sf-step').addEventListener('click', function () {
            api('/step', { method: 'POST', body: JSON.stringify({ ticks: 16 }) })
                .then(out).then(refresh).catch(function (err) { out(err.message || err); });
        });

        $('sf-announce').addEventListener('click', function () {
            api('/announce', { method: 'POST' })
                .then(out).then(refresh).catch(function (err) { out(err.message || err); });
        });

        $('sf-reset').addEventListener('click', function () {
            if (!window.confirm('Reset the field? Its accumulated reputation goes with it.')) {
                return;
            }
            api('/reset', { method: 'POST' })
                .then(out).then(refresh).catch(function (err) { out(err.message || err); });
        });

        refresh();
        poll = window.setInterval(refresh, 2500);
        window.addEventListener('unload', function () { window.clearInterval(poll); });
    }

    if (document.readyState !== 'loading') {
        bind();
    } else {
        document.addEventListener('DOMContentLoaded', bind);
    }
})();
