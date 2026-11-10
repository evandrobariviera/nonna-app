/*!
 * Nonna Tag — substitui o Google Tag Manager nos sites dos clientes.
 * Servida pelo Nonna App em /api/t/{chave}.js com a configuração do cliente
 * (aba Rastreamento) logo acima deste código, em `NONNA_TAG_CONFIG`.
 *
 * Faz:
 *  1. guarda a origem da visita (UTM, gclid, fbclid, site de onde veio) no
 *     cookie `nonna_attr` — o MESMO do plugin Nonna Leads; se o plugin está
 *     no site, só lê (o plugin grava pelo servidor, que dura 12 meses);
 *  2. carrega Pixel da Meta, GA4, tag do Google Ads e Clarity;
 *  3. lead: quando o plugin capta um formulário (evento `nonna_lead_captured`
 *     no dataLayer), dispara o Lead no Pixel/GA4 com o mesmo event_id que vai
 *     para o App — a Meta junta os dois e não conta 2x. Sem plugin, a própria
 *     tag capta formulários com e-mail ou telefone e manda o lead ao App;
 *  4. clique em link de WhatsApp vira evento "Contact" / "whatsapp_click";
 *  5. consentimento: window.nonnaTag.consent(true|false).
 *
 * ES5 de propósito (Safari antigo). Nenhum dado pessoal fica em cookie.
 */
(function () {
    'use strict';

    var C = window.NONNA_TAG_CONFIG;
    if (!C || window.nonnaTag && window.nonnaTag.loaded) return;

    var w = window, d = document;
    var dataLayer = w.dataLayer = w.dataLayer || [];
    var hasPlugin = !!(w.NONNA_LEADS || w.NONNA_LEADS_PLUGIN);
    var PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'gbraid', 'wbraid'];

    function gtag() { dataLayer.push(arguments); }
    w.gtag = w.gtag || gtag;

    function rand() {
        var s = '';
        for (var i = 0; i < 16; i++) s += 'abcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random() * 36));
        return s;
    }

    function readCookie(name) {
        var m = d.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return m ? m[1] : null;
    }

    function hostOf(url) {
        var a = d.createElement('a');
        a.href = url;
        return String(a.hostname || '').toLowerCase().replace(/^www\./, '');
    }

    function loadScript(src) {
        var s = d.createElement('script');
        s.async = true;
        s.src = src;
        (d.head || d.documentElement).appendChild(s);
    }

    /* ---------------------------------------------- 1. origem da visita */

    function readAttr() {
        var raw = readCookie('nonna_attr');
        if (!raw) return { first: {}, last: {} };
        try {
            var data = JSON.parse(decodeURIComponent(raw.replace(/\+/g, ' ')));
            return { first: data.first || {}, last: data.last || {} };
        } catch (e) {
            return { first: {}, last: {} };
        }
    }

    function query() {
        var out = {}, search = w.location.search.replace(/^\?/, '');
        if (!search) return out;
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var idx = pairs[i].indexOf('=');
            if (idx < 1) continue;
            try {
                var v = decodeURIComponent(pairs[i].slice(idx + 1).replace(/\+/g, ' '));
                if (v !== '') out[decodeURIComponent(pairs[i].slice(0, idx))] = v.slice(0, 190);
            } catch (e) {}
        }
        return out;
    }

    function touch() {
        if (hasPlugin) return; // o plugin grava pelo servidor (cookie de 12 meses)
        var q = query(), incoming = {}, has = false;
        for (var i = 0; i < PARAMS.length; i++) {
            if (q[PARAMS[i]]) { incoming[PARAMS[i]] = q[PARAMS[i]]; has = true; }
        }
        var ref = d.referrer || '';
        var external = ref && hostOf(ref) !== hostOf(w.location.href) ? ref.slice(0, 300) : '';
        if (!has && !external) return;

        incoming.referrer = external;
        incoming.landing_page = (w.location.protocol + '//' + w.location.host + w.location.pathname).slice(0, 300);
        incoming.ts = Math.floor(Date.now() / 1000);

        var attr = readAttr();
        var empty = true;
        for (var k in attr.first) { if (Object.prototype.hasOwnProperty.call(attr.first, k)) { empty = false; break; } }
        if (empty) attr.first = incoming;
        attr.last = incoming;

        d.cookie = 'nonna_attr=' + encodeURIComponent(JSON.stringify(attr)) + '; path=/; max-age=' + (365 * 86400) + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
    }

    /* ---------------------------------------------- 5. consentimento */

    var granted = C.consent !== 'denied';
    function consentState(on) {
        var v = on ? 'granted' : 'denied';
        return { ad_storage: v, analytics_storage: v, ad_user_data: v, ad_personalization: v };
    }
    gtag('consent', 'default', consentState(granted));

    /* ---------------------------------------------- 2. ferramentas */

    function loadPixel() {
        if (!C.pixel || w.fbq) return;
        var n = w.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!w._fbq) w._fbq = n;
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        loadScript('https://connect.facebook.net/en_US/fbevents.js');
        if (!granted) w.fbq('consent', 'revoke');
        w.fbq('init', C.pixel);
        w.fbq('track', 'PageView', {}, { eventID: 'pv_' + rand() });
    }

    function loadGoogle() {
        var first = C.ga4 || C.ads;
        if (!first) return;
        loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(first));
        gtag('js', new Date());
        if (C.ga4) gtag('config', C.ga4);
        if (C.ads) gtag('config', C.ads);
    }

    function loadClarity() {
        if (!C.clarity) return;
        w.clarity = w.clarity || function () { (w.clarity.q = w.clarity.q || []).push(arguments); };
        loadScript('https://www.clarity.ms/tag/' + encodeURIComponent(C.clarity));
    }

    /* ---------------------------------------------- 3. lead */

    function fireLead(eventId, formName) {
        if (w.fbq && C.pixel) w.fbq('track', 'Lead', formName ? { content_name: formName } : {}, { eventID: eventId });
        gtag('event', 'generate_lead', { event_id: eventId, form_name: formName || '' });
    }

    // Lead captado pelo plugin (WordPress): só o navegador; o servidor já vai pelo plugin.
    function watchDataLayer() {
        var seen = {};
        function check(item) {
            if (item && item.event === 'nonna_lead_captured' && item.nonna_event_id && !seen[item.nonna_event_id]) {
                seen[item.nonna_event_id] = 1;
                fireLead(String(item.nonna_event_id), item.nonna_form_name || '');
            }
        }
        for (var i = 0; i < dataLayer.length; i++) check(dataLayer[i]);
        var push = dataLayer.push;
        dataLayer.push = function () {
            for (var j = 0; j < arguments.length; j++) check(arguments[j]);
            return push.apply(dataLayer, arguments);
        };
    }

    function send(payload) {
        var body = JSON.stringify(payload);
        // text/plain: o navegador manda sem pedir permissão antes (sem "preflight").
        if (navigator.sendBeacon && navigator.sendBeacon(C.endpoint, new Blob([body], { type: 'text/plain' }))) return;
        try {
            var x = new XMLHttpRequest();
            x.open('POST', C.endpoint, true);
            x.setRequestHeader('Content-Type', 'text/plain;charset=UTF-8');
            x.send(body);
        } catch (e) {}
    }

    /** Lead vindo do site sem plugin (ou chamado à mão: window.nonnaTag.lead({...})). */
    function lead(data) {
        data = data || {};
        if (!data.email && !data.phone) return null;
        var eventId = (C.prefix || 'nt') + '_' + rand();
        fireLead(eventId, data.form_name || '');
        send({
            event_id: eventId,
            name: data.name || '', email: data.email || '', phone: data.phone || '',
            city: data.city || '', state: data.state || '',
            form_name: data.form_name || '', fields: data.fields || {},
            page_url: w.location.href.slice(0, 500), page_title: d.title.slice(0, 200),
            attribution: readAttr(),
            fbp: readCookie('_fbp'), fbc: readCookie('_fbc'),
            consent: granted
        });
        return eventId;
    }

    function fieldValue(form, test) {
        var els = form.elements;
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (!el.name && !el.id) continue;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            var key = String(el.name || el.id).toLowerCase() + ' ' + String(el.type || '').toLowerCase() + ' ' + String(el.placeholder || '').toLowerCase();
            if (test(key, el) && el.value) return String(el.value).slice(0, 190);
        }
        return '';
    }

    function captureForm(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || form.hasAttribute('data-nonna-ignore')) return;
        if (form.querySelector('input[type=password]') || form.getAttribute('role') === 'search') return;
        var now = Date.now();
        if (form._nonnaAt && now - form._nonnaAt < 5000) return; // clique duplo
        form._nonnaAt = now;

        var email = fieldValue(form, function (k, el) { return el.type === 'email' || /e-?mail/.test(k); });
        var phone = fieldValue(form, function (k, el) { return el.type === 'tel' || /(tel|fone|phone|whats|celular)/.test(k); });
        if (!email && !phone) return;

        var fields = {};
        for (var i = 0; i < form.elements.length && i < 40; i++) {
            var el = form.elements[i];
            if (!el.name || el.type === 'hidden' || el.type === 'submit' || el.type === 'password') continue;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            fields[String(el.name).slice(0, 60)] = String(el.value || '').slice(0, 300);
        }

        lead({
            email: email, phone: phone,
            name: fieldValue(form, function (k) { return /(^| |_|-)(nome|name)/.test(k) && !/(empresa|company|user)/.test(k); }),
            city: fieldValue(form, function (k) { return /(cidade|city)/.test(k); }),
            state: fieldValue(form, function (k) { return /(estado|uf|state)/.test(k); }),
            form_name: form.getAttribute('name') || form.getAttribute('id') || d.title,
            fields: fields
        });
    }

    /* ---------------------------------------------- 4. WhatsApp */

    function watchWhatsapp(e) {
        var el = e.target;
        while (el && el.tagName !== 'A') el = el.parentNode;
        if (!el || !el.href || !/(wa\.me|api\.whatsapp\.com|whatsapp:\/\/|web\.whatsapp\.com)/i.test(el.href)) return;
        if (w.fbq && C.pixel) w.fbq('track', 'Contact', { content_name: 'whatsapp' }, { eventID: 'wa_' + rand() });
        gtag('event', 'whatsapp_click', { link_url: el.href.slice(0, 200) });
    }

    /* ---------------------------------------------- início */

    w.nonnaTag = {
        loaded: true,
        version: C.version,
        lead: lead,
        consent: function (on) {
            granted = !!on;
            gtag('consent', 'update', consentState(granted));
            if (w.fbq) w.fbq('consent', granted ? 'grant' : 'revoke');
        }
    };

    try { touch(); } catch (e) {}
    loadPixel();
    loadGoogle();
    loadClarity();
    watchDataLayer();
    if (C.capture && !hasPlugin) d.addEventListener('submit', captureForm, true);
    d.addEventListener('click', watchWhatsapp, true);
})();
