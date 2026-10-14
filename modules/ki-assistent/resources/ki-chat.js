/**
 * Chat-Logik des KI-Assistenten, genutzt von der Chat-Seite und vom Chatfenster unten rechts (FloatingChat).
 * kiChat(root, opts) sucht in root: .ki-messages, .ki-form, .ki-input, .ki-reset
 * opts: askUrl, feedbackUrl, storageKey (Verlauf in sessionStorage – bleibt beim Seitenwechsel erhalten,
 * endet mit dem Tab), pageScroll (Handy-Chat-Seite: die Seite scrollt statt der Nachrichtenliste),
 * newestFirst (Handy-Chatfenster mit Eingabe oben: neueste Frage oben, Antwort direkt darunter),
 * onAsk(), onUsage(usage)
 */
window.kiChat = function (root, opts) {
    var box = root.querySelector('.ki-messages');
    var input = root.querySelector('.ki-input');
    var form = root.querySelector('.ki-form');
    var history = [];
    var log = []; // angezeigte Nachrichten zum Wiederherstellen: {role, text, cards}
    var newestFirst = !!(opts.newestFirst && opts.newestFirst());
    var lastUser = null;

    function touchDevice() {
        return window.matchMedia('(max-width: 767px), (hover: none) and (pointer: coarse)').matches;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    // Links auf die Plattform im selben Fenster öffnen (bleibt in der App), fremde in neuem Tab
    function linkAttrs(url) {
        return url.indexOf(location.origin + '/') === 0 || /^\/(?!\/)/.test(url) ? '' : ' target="_blank" rel="noopener"';
    }

    // Links nur zu vertrauten Seiten klickbar (Liste vom Server: window.kiChatDomains), alle anderen bleiben Text
    function erlaubt(url) {
        var host;
        try { host = new URL(url.replace(/&amp;/g, '&')).hostname.toLowerCase(); } catch (e) { return false; }
        if (host === location.hostname) return true;
        return (window.kiChatDomains || []).some(function (d) { return host === d || host.slice(-(d.length + 1)) === '.' + d; });
    }

    // Kleines, sicheres Markdown: erst alles escapen, dann nur Links (http/https, vertraute Seiten), fett und Listen
    function render(text) {
        return esc(text).split('\n').map(function (line) {
            line = line.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (m, label, url) {
                return erlaubt(url) ? '<a href="' + url + '"' + linkAttrs(url) + '>' + label + '</a>' : label + ' (' + url + ')';
            }).replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, function (m, pre, url) {
                return erlaubt(url) ? pre + '<a href="' + url + '"' + linkAttrs(url) + '>' + url + '</a>' : m;
            }).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');
            if (/^#{1,4} /.test(line)) return '<strong>' + line.replace(/^#{1,4} /, '') + '</strong>';
            return /^\s*[-*] /.test(line) ? line.match(/^\s*/)[0].replace(/ /g, '&nbsp;') + '&bull; ' + line.replace(/^\s*[-*] /, '') : line;
        }).join('<br>');
    }

    // Karten aus Aktionen: Knöpfe zu vorausgefülltem Formular, Kalenderdatei, PDF-Download
    function cards(list) {
        return (list || []).map(function (c) {
            var html = '<div class="ki-card"><strong><i class="fa fa-' + esc(c.icon || 'bolt') + '"></i> ' + esc(c.titel) + '</strong>';
            (c.zeilen || []).forEach(function (z) { html += '<br><small>' + esc(z) + '</small>'; });
            var buttons = c.knoepfe || (c.url ? [{text: c.knopf, url: c.url}] : []);
            html += '<div>';
            buttons.forEach(function (b, i) {
                if (!/^\/|^https:\/\//.test(b.url)) return;
                html += '<a class="btn btn-sm ' + (i === 0 ? 'btn-primary' : 'btn-light') + '" href="' + esc(b.url) + '"'
                    + (c.typ === 'download' ? ' download' : ' target="_blank" rel="noopener"') + '>' + esc(b.text) + '</a>';
            });
            return html + '</div></div>';
        }).join('');
    }

    // 👍/👎: speichert Frage und Antwort ohne Konto, bei 👎 optional mit Kommentar
    function rating(question, answer) {
        var wrap = document.createElement('div');
        wrap.className = 'ki-rate';
        wrap.innerHTML = '<a href="#" data-r="1" title="Hilfreich">👍</a><a href="#" data-r="-1" title="Nicht hilfreich">👎</a>';
        function send(r, comment) {
            var data = new FormData();
            data.append('bewertung', r);
            data.append('frage', question);
            data.append('antwort', answer);
            data.append('kommentar', comment || '');
            data.append(yii.getCsrfParam(), yii.getCsrfToken());
            fetch(opts.feedbackUrl, {method: 'POST', body: data, credentials: 'same-origin'}).then(function () {
                wrap.textContent = 'Danke für Ihre Rückmeldung!';
            });
        }
        wrap.addEventListener('click', function (e) {
            var a = e.target.closest('[data-r]');
            if (!a) return;
            e.preventDefault();
            if (a.getAttribute('data-r') === '1') { send(1); return; }
            wrap.innerHTML = 'Was war falsch oder hat gefehlt? (optional)'
                + '<div class="input-group input-group-sm"><input type="text" class="form-control" maxlength="500">'
                + '<button class="btn btn-light btn-sm" type="button">Senden</button></div>';
            var inp = wrap.querySelector('input');
            inp.focus();
            wrap.querySelector('button').addEventListener('click', function () { send(-1, inp.value); });
            inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); send(-1, inp.value); } });
        });
        return wrap;
    }

    function scrollTo(div) {
        if (newestFirst) {
            box.scrollTop = 0;
        } else if (opts.pageScroll && opts.pageScroll()) {
            div.scrollIntoView({block: 'start', behavior: 'smooth'});
        } else {
            box.scrollTop = box.scrollHeight;
        }
    }

    function add(role, html) {
        var div = document.createElement('div');
        div.className = 'ki-msg ki-msg-' + (role === 'user' ? 'user' : 'model');
        div.innerHTML = html;
        if (!newestFirst) {
            box.appendChild(div);
        } else if (role === 'user' || !lastUser) {
            box.insertBefore(div, box.querySelector('.ki-msg')); // unter der Begrüßung
            lastUser = div;
        } else {
            lastUser.after(div); // Antwort unter ihre Frage
        }
        scrollTo(div);
        return div;
    }

    function save() {
        if (!opts.storageKey) return;
        try {
            sessionStorage.setItem(opts.storageKey, JSON.stringify({history: history, log: log}));
        } catch (e) { /* privater Modus o. Ä.: dann eben ohne Wiederherstellen */ }
    }

    if (opts.storageKey) {
        try {
            var saved = JSON.parse(sessionStorage.getItem(opts.storageKey) || 'null');
            if (saved && Array.isArray(saved.history) && Array.isArray(saved.log)) {
                history = saved.history;
                log = saved.log;
                log.forEach(function (m) {
                    add(m.role, m.role === 'user' ? esc(m.text) : render(m.text) + cards(m.cards));
                });
            }
        } catch (e) { /* defekter Eintrag: neu beginnen */ }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) return;
        input.value = '';
        input.disabled = true;
        if (opts.onAsk) opts.onAsk();
        add('user', esc(q));
        history.push({role: 'user', text: q});
        var pending = add('model', '<em>…</em>');
        var data = new FormData();
        data.append('history', JSON.stringify(history));
        data.append(yii.getCsrfParam(), yii.getCsrfToken());
        fetch(opts.askUrl, {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.error) {
                    history.pop();
                    pending.innerHTML = '<span class="text-danger">' + esc(res.error) + '</span>';
                    return;
                }
                history.push({role: 'model', text: res.answer + (res.memo ? '\n' + res.memo : '')});
                log.push({role: 'user', text: q}, {role: 'model', text: res.answer, cards: res.cards});
                save();
                pending.innerHTML = render(res.answer) + cards(res.cards);
                pending.appendChild(rating(q, res.answer));
                scrollTo(pending);
                if (res.usage && opts.onUsage) opts.onUsage(res.usage);
            })
            .catch(function () {
                history.pop();
                pending.innerHTML = '<span class="text-danger">Verbindungsfehler</span>';
            })
            .finally(function () {
                input.disabled = false;
                // Handy/Tablet: Bildschirmtastatur nicht wieder über die Antwort legen
                if (!touchDevice()) input.focus();
            });
    });

    root.querySelector('.ki-reset').addEventListener('click', function (e) {
        e.preventDefault();
        history = [];
        log = [];
        save();
        box.querySelectorAll('.ki-msg').forEach(function (m) { m.remove(); });
        lastUser = null;
        if (!touchDevice()) input.focus();
    });

    return {
        enable: function () {
            root.querySelectorAll('.ki-form input, .ki-form button').forEach(function (el) { el.disabled = false; });
        },
        focus: function () { input.focus(); }
    };
};
