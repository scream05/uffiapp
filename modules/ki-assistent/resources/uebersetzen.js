/*
 * „Übersetzen“ unter Beiträgen, Kommentaren, Terminen und Vereins-/Betriebsseiten, nur wenn die eingestellte Sprache
 * nicht Deutsch ist (Konfiguration in widgets/UebersetzenConfig.php). Die Übersetzung holt der Server
 * (UebersetzungController), angezeigt wird sie an Ort und Stelle; „Original anzeigen“ schaltet zurück.
 */
(function () {
    var cfgEl = document.getElementById('ki-uebersetzen-cfg');
    if (!cfgEl || window.kiUebersetzen) {
        return;
    }
    window.kiUebersetzen = true;
    var L = JSON.parse(cfgEl.getAttribute('data-labels'));
    var url = cfgEl.getAttribute('data-url');
    var ageUrl = cfgEl.getAttribute('data-age-url');

    function csrf() {
        var p = document.querySelector('meta[name="csrf-param"]'), t = document.querySelector('meta[name="csrf-token"]');
        return p && t ? encodeURIComponent(p.content) + '=' + encodeURIComponent(t.content) : '';
    }

    function post(target, body) {
        return fetch(target, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
            body: body + '&' + csrf()
        }).then(function (r) { return r.json(); });
    }

    function addLink(target, typ, id) {
        if (!target || target.getAttribute('data-ki-ue')) {
            return;
        }
        target.setAttribute('data-ki-ue', '1');
        var wrap = document.createElement('div');
        wrap.className = 'ki-ue';
        var a = document.createElement('a');
        a.href = '#';
        a.className = 'ki-ue-link';
        a.innerHTML = '<i class="fa fa-language"></i> ';
        a.appendChild(document.createTextNode(L.uebersetzen));
        var hint = document.createElement('span');
        hint.className = 'ki-ue-hint';
        wrap.appendChild(a);
        wrap.appendChild(hint);
        target.parentNode.insertBefore(wrap, target.nextSibling);

        var original = null, originalTitle = null, titleEl = null, translated = null, busy = false;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            if (busy) {
                return;
            }
            if (original !== null && translated !== null && target.getAttribute('data-ki-ue-zeigt') === '1') {
                target.innerHTML = original;
                if (titleEl) { titleEl.textContent = originalTitle; }
                target.setAttribute('data-ki-ue-zeigt', '0');
                a.lastChild.nodeValue = L.uebersetzen;
                hint.textContent = '';
                return;
            }
            if (translated !== null) {
                show();
                return;
            }
            busy = true;
            hint.textContent = ' ' + L.laedt;
            var anfrage = function () {
                return post(url, 'typ=' + encodeURIComponent(typ) + '&id=' + encodeURIComponent(id)).then(function (res) {
                    if (res.alter) {
                        if (!window.confirm(L.alter)) {
                            return {fehler: ''};
                        }
                        return post(ageUrl, 'adult=1').then(anfrage);
                    }
                    return res;
                });
            };
            anfrage().then(function (res) {
                busy = false;
                if (res.fehler !== undefined) {
                    hint.textContent = res.fehler ? ' ' + res.fehler : '';
                    return;
                }
                translated = res;
                show();
            }).catch(function () {
                busy = false;
                hint.textContent = ' ' + L.fehler;
            });
        });

        function show() {
            original = target.innerHTML;
            target.innerHTML = translated.html;
            if (translated.titel) {
                titleEl = titleFor(target);
                if (titleEl) {
                    originalTitle = titleEl.textContent;
                    titleEl.textContent = translated.titel;
                }
            }
            target.setAttribute('data-ki-ue-zeigt', '1');
            a.lastChild.nodeValue = L.original;
            hint.textContent = ' · ' + L.hinweis;
        }
    }

    /** Titel eines Termins im Stream bzw. auf der Terminseite (falls vorhanden) */
    function titleFor(target) {
        var entry = target.closest('[data-content-key]');
        var el = entry && entry.querySelector('.calendar-entry-title, h4.media-heading a, .stream-entry-title');
        return el || document.querySelector('.calendar-entry-title');
    }

    function inComments(el) {
        return !!el.closest('.comment-container, .comment, .nested-comments-root');
    }

    function scan(root) {
        root.querySelectorAll('[data-content-key]').forEach(function (entry) {
            var id = entry.getAttribute('data-content-key');
            var texts = Array.prototype.filter.call(entry.querySelectorAll('[data-ui-richtext]'), function (el) {
                return !inComments(el) && el.closest('[data-content-key]') === entry && el.textContent.trim().length > 1;
            });
            if (texts.length && id) {
                addLink(texts[0], 'content', id);
            }
        });
        root.querySelectorAll('.comment-message[id^="comment-message-"]').forEach(function (el) {
            var target = el.querySelector('[data-ui-richtext]') || el;
            if (target.textContent.trim().length > 1) {
                addLink(target, 'comment', el.id.replace('comment-message-', ''));
            }
        });
        root.querySelectorAll('[data-uebersetzbar]').forEach(function (el) {
            var parts = el.getAttribute('data-uebersetzbar').split(':');
            addLink(el, parts[0], parts[1]);
        });
    }

    var pending = null;
    function later() {
        if (pending) {
            return;
        }
        pending = setTimeout(function () {
            pending = null;
            scan(document);
        }, 300);
    }

    scan(document);
    new MutationObserver(later).observe(document.body, {childList: true, subtree: true});
})();
