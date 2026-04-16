/*!
 * Precision Portal — offline Layui-compatible shim (v2.9.21-compat)
 *
 * This is an intentionally small, dependency-free replacement for the subset of
 * the Layui 2.9 API the portal actually uses, so the project can be built and
 * served in fully-offline environments without pulling Layui from a CDN/npm.
 * Surface covered: layui.use, layui.$, layui.form, layui.table, layui.layer.
 */
(function (global) {
  'use strict';

  // ---------- jQuery-ish lightweight DOM helper ----------
  function $(selector, root) {
    if (!(this instanceof $)) return new $(selector, root);
    if (!selector) { this.elements = []; return this; }
    if (typeof selector === 'object' && selector.nodeType) { this.elements = [selector]; return this; }
    var scope = root || document;
    this.elements = Array.prototype.slice.call(
      typeof selector === 'string' ? scope.querySelectorAll(selector) : selector
    );
  }
  $.prototype.each = function (fn) {
    for (var i = 0; i < this.elements.length; i++) fn.call(this.elements[i], i, this.elements[i]);
    return this;
  };
  $.prototype.on = function (evt, handler) { return this.each(function () { this.addEventListener(evt, handler); }); };
  $.prototype.off = function (evt, handler) { return this.each(function () { this.removeEventListener(evt, handler); }); };
  $.prototype.val = function (v) {
    if (v === undefined) return this.elements[0] ? this.elements[0].value : '';
    return this.each(function () { this.value = v; });
  };
  $.prototype.text = function (v) {
    if (v === undefined) return this.elements[0] ? this.elements[0].textContent : '';
    return this.each(function () { this.textContent = v; });
  };
  $.prototype.html = function (v) {
    if (v === undefined) return this.elements[0] ? this.elements[0].innerHTML : '';
    return this.each(function () { this.innerHTML = v; });
  };
  $.prototype.attr = function (n, v) {
    if (v === undefined) return this.elements[0] ? this.elements[0].getAttribute(n) : null;
    return this.each(function () { this.setAttribute(n, v); });
  };
  $.prototype.addClass = function (c) { return this.each(function () { this.classList.add(c); }); };
  $.prototype.removeClass = function (c) { return this.each(function () { this.classList.remove(c); }); };
  $.prototype.append = function (node) {
    return this.each(function () {
      if (typeof node === 'string') this.insertAdjacentHTML('beforeend', node);
      else this.appendChild(node);
    });
  };
  $.prototype.find = function (sel) {
    var out = [];
    this.each(function () { out = out.concat(Array.prototype.slice.call(this.querySelectorAll(sel))); });
    var wrapped = new $(); wrapped.elements = out; return wrapped;
  };
  $.prototype.empty = function () { return this.each(function () { this.innerHTML = ''; }); };
  $.prototype.show = function () { return this.each(function () { this.style.display = ''; }); };
  $.prototype.hide = function () { return this.each(function () { this.style.display = 'none'; }); };
  $.get = function (url, cb) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(cb);
  };
  $.post = function (url, data, cb) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data || {}),
    }).then(function (r) { return r.json(); }).then(cb);
  };
  $.ajax = function (opts) {
    var init = {
      method: (opts.type || 'GET').toUpperCase(),
      credentials: 'same-origin',
      headers: opts.contentType ? { 'Content-Type': opts.contentType } : {},
    };
    if (opts.data && init.method !== 'GET') init.body = typeof opts.data === 'string' ? opts.data : JSON.stringify(opts.data);
    return fetch(opts.url, init)
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, status: r.status, text: t }; }); })
      .then(function (resp) {
        var body;
        try { body = JSON.parse(resp.text); } catch (e) { body = { message: resp.text }; }
        if (resp.ok && opts.success) opts.success(body);
        else if (!resp.ok && opts.error) opts.error({ responseText: resp.text, status: resp.status });
      });
  };

  // ---------- layer (toast / dialog) ----------
  var layer = {
    msg: function (text, opts) {
      var icon = (opts && opts.icon) === 2 ? '✗' : (opts && opts.icon) === 1 ? '✓' : '•';
      console.log('[layer.msg]', icon, text);
      var d = document.createElement('div');
      d.className = 'pp-toast';
      d.textContent = icon + ' ' + text;
      d.style.cssText = 'position:fixed;top:20px;right:20px;background:#323232;color:#fff;padding:10px 18px;border-radius:4px;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.25);';
      document.body.appendChild(d);
      var ms = (opts && opts.time) || 1500;
      setTimeout(function () { d.parentNode && d.parentNode.removeChild(d); if (opts && typeof opts === 'function') opts(); }, ms);
      if (typeof opts === 'function') setTimeout(opts, ms);
    },
    alert: function (text, cb) {
      window.alert(text); if (typeof cb === 'function') cb();
    },
    confirm: function (text, yes) {
      if (window.confirm(text) && typeof yes === 'function') yes(0);
    },
    open: function (opts) {
      opts = opts || {};
      var overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:9999';
      var box = document.createElement('div');
      box.style.cssText = 'background:#fff;padding:20px;min-width:320px;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.3)';
      box.innerHTML = '<h3 style="margin:0 0 12px">' + (opts.title || 'Dialog') + '</h3><div>' + (opts.content || '') + '</div>'
        + '<div style="margin-top:16px;text-align:right"><button class="layui-btn layui-btn-sm" data-close>Close</button></div>';
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      box.querySelector('[data-close]').addEventListener('click', function () { overlay.remove(); });
      return overlay;
    },
    close: function (layerObj) { if (layerObj && layerObj.remove) layerObj.remove(); },
  };

  // ---------- form ----------
  var form = {
    handlers: {},
    on: function (filterEvent, handler) {
      // filterEvent like "submit(login)"
      var m = /^([a-z]+)\(([^)]+)\)$/i.exec(filterEvent);
      if (!m) return;
      var eventName = m[1];
      var filter = m[2];
      if (eventName === 'submit') {
        var formEl = document.querySelector('form[lay-filter="' + filter + '"]')
          || document.querySelector('[lay-filter="' + filter + '"]')
          || document.querySelector('form'); // fallback
        if (!formEl) return;
        formEl.addEventListener('submit', function (ev) {
          ev.preventDefault();
          var fd = new FormData(formEl);
          var field = {};
          fd.forEach(function (v, k) { field[k] = v; });
          var res = handler({ field: field, elem: formEl });
          if (res === false) ev.preventDefault();
        });
      }
    },
    render: function () { /* layui native renders selects; noop for plain <select> */ },
  };

  // ---------- table ----------
  var table = {
    instances: {},
    render: function (opts) {
      var elem = document.querySelector(opts.elem);
      if (!elem) return;
      this.instances[opts.elem] = opts;
      this._fetchAndRender(opts);
    },
    reload: function (filter, overrides) {
      for (var selector in this.instances) {
        var opts = this.instances[selector];
        if (opts.elem === filter || (opts.elem && opts.elem.indexOf(filter) >= 0)) {
          if (overrides && overrides.where) opts.where = Object.assign({}, opts.where || {}, overrides.where);
          this._fetchAndRender(opts);
        }
      }
    },
    _fetchAndRender: function (opts) {
      var elem = document.querySelector(opts.elem);
      if (!elem) return;
      var url = opts.url;
      if (opts.where) {
        var qs = Object.keys(opts.where).filter(function (k) { return opts.where[k] !== ''; })
          .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(opts.where[k]); }).join('&');
        if (qs) url += (url.indexOf('?') >= 0 ? '&' : '?') + qs;
      }
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          var parsed = opts.parseData ? opts.parseData(res) : { code: res.code, count: (res.data || []).length, data: res.data };
          var data = parsed.data || [];
          var cols = (opts.cols && opts.cols[0]) || [];
          var html = '<table class="layui-table"><thead><tr>';
          cols.forEach(function (c) { html += '<th>' + (c.title || c.field || '') + '</th>'; });
          html += '</tr></thead><tbody>';
          data.forEach(function (row) {
            html += '<tr>';
            cols.forEach(function (c) {
              if (c.toolbar) {
                var tmplEl = document.querySelector(c.toolbar);
                var tmpl = tmplEl ? tmplEl.innerHTML : '';
                html += '<td>' + tmpl.replace(/\{\{d\.([a-zA-Z0-9_]+)\}\}/g, function (_, k) { return row[k] != null ? row[k] : ''; })
                  .replace(/\{\{#[^}]*\}\}/g, '').replace(/\{\{\#\s*\}\}/g, '') + '</td>';
              } else if (c.templet && typeof c.templet === 'function') {
                try { html += '<td>' + c.templet(row) + '</td>'; } catch (e) { html += '<td></td>'; }
              } else {
                var v = row[c.field];
                html += '<td>' + (v == null ? '' : String(v)) + '</td>';
              }
            });
            html += '</tr>';
          });
          html += '</tbody></table>';
          elem.innerHTML = html;
        })
        .catch(function (err) {
          elem.innerHTML = '<div class="pp-error">Failed to load: ' + err.message + '</div>';
        });
    },
  };

  // ---------- layui module loader ----------
  var modules = { '$': $, form: form, table: table, layer: layer };
  function use(names, cb) {
    var ctx = { '$': $, jquery: $, form: form, table: table, layer: layer };
    if (typeof cb === 'function') cb(ctx);
    return ctx;
  }

  global.layui = {
    '$': $,
    jquery: $,
    form: form,
    table: table,
    layer: layer,
    use: use,
  };
})(window);
