/**
 * Precision Portal - Main Application JS
 */

layui.use(['layer', 'form', 'table', 'element'], function () {
    var layer = layui.layer;
    var $ = layui.$;

    // ─── Session timeout check (15-minute idle) ───
    var idleTimeout = 900000; // 15 minutes in ms
    var idleTimer;

    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () {
            layer.confirm('Your session has expired due to inactivity. Please log in again.', {
                btn: ['Login'],
                title: 'Session Expired',
                closeBtn: 0
            }, function () {
                window.location.href = '/login';
            });
        }, idleTimeout);
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'click', 'scroll'].forEach(function (evt) {
        document.addEventListener(evt, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();

    // ─── AJAX defaults ───
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        error: function (xhr) {
            if (xhr.status === 401) {
                window.location.href = '/login';
            } else if (xhr.status === 403) {
                layer.msg('Access denied: insufficient permissions', { icon: 5 });
            } else if (xhr.status === 429) {
                layer.msg('Too many requests. Please wait.', { icon: 0 });
            } else {
                var msg = 'An error occurred';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    msg = resp.message || msg;
                } catch (e) {}
                layer.msg(msg, { icon: 2 });
            }
        }
    });

    // ─── Device fingerprint collection (for risk module) ───
    window.PP = window.PP || {};
    PP.collectFingerprint = function () {
        return {
            userAgent: navigator.userAgent,
            screenResolution: screen.width + 'x' + screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            platform: navigator.platform
        };
    };

    // ─── Utility: format status badge ───
    PP.statusBadge = function (status) {
        var cls = 'pp-status pp-status-' + status.toLowerCase().replace(/_/g, '');
        return '<span class="' + cls + '">' + status + '</span>';
    };

    // ─── Utility: API helper ───
    PP.api = function (method, url, data) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: url,
                type: method,
                contentType: 'application/json',
                data: data ? JSON.stringify(data) : undefined,
                success: function (res) {
                    resolve(res);
                },
                error: function (xhr) {
                    reject(xhr);
                }
            });
        });
    };

    PP.get = function (url, params) {
        return PP.api('GET', url + (params ? '?' + $.param(params) : ''));
    };

    PP.post = function (url, data) {
        return PP.api('POST', url, data);
    };

    PP.put = function (url, data) {
        return PP.api('PUT', url, data);
    };

    PP.del = function (url) {
        return PP.api('DELETE', url);
    };
});
