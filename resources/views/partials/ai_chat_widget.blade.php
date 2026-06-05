{{-- Floating AI assistant (authenticated users only; API key stays server-side) --}}
<div id="snipe-ai-chat-root" aria-live="polite">
    <button type="button" id="snipe-ai-chat-toggle" class="snipe-ai-chat-fab" aria-expanded="false"
            aria-controls="snipe-ai-chat-panel" title="AI assistant — {{ trans('general.show_help') }}">
        <svg class="snipe-ai-chat-fab-icon" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="snipe-ai-fab-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#fff;stop-opacity:1"/>
                    <stop offset="50%" style="stop-color:#e0f7ff;stop-opacity:1"/>
                    <stop offset="100%" style="stop-color:#c8f0ff;stop-opacity:1"/>
                </linearGradient>
            </defs>
            <circle cx="12" cy="13" r="9" fill="url(#snipe-ai-fab-grad)" opacity=".95"/>
            <path fill="#1a6b9c" d="M11 4h2v2h3a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v7a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-7a2 2 0 0 1 2-2h1V8a2 2 0 0 1 2-2h3V4Zm5 7H8a1 1 0 0 0-1 1v4a3 3 0 0 0 3 3h4a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1ZM9 8v1h6V8H9Zm1.25 4.75a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm3.5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z"/>
            <circle cx="9.25" cy="13.75" r="1.15" fill="#ff7043"/>
            <circle cx="14.75" cy="13.75" r="1.15" fill="#7e57c2"/>
        </svg>
    </button>
    <div id="snipe-ai-chat-panel" class="snipe-ai-chat-panel" hidden role="dialog" aria-label="AI assistant">
        <div class="snipe-ai-chat-header">
            <span>AI assistant</span>
            <button type="button" id="snipe-ai-chat-close" class="snipe-ai-chat-close" aria-label="{{ trans('general.cancel') }}">&times;</button>
        </div>
        <div id="snipe-ai-chat-log" class="snipe-ai-chat-log"></div>
        <form id="snipe-ai-chat-form" class="snipe-ai-chat-form">
            <label class="sr-only" for="snipe-ai-chat-input">Message</label>
            <textarea id="snipe-ai-chat-input" rows="4" maxlength="8000" required
                      placeholder="Ask about Snipe-IT or ITAM…"></textarea>
            <button type="submit" class="btn btn-primary btn-sm" id="snipe-ai-chat-send">Send</button>
        </form>
        <p class="snipe-ai-chat-hint text-muted small">Try `ops help` — requestable search, request asset, links open in a new tab.</p>
    </div>
</div>

<style>
    #snipe-ai-chat-root { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 100050; font-size: 14px; }
    .snipe-ai-chat-fab {
        width: 4.5rem; height: 4.5rem; border-radius: 50%; border: 3px solid rgba(255,255,255,.35); cursor: pointer;
        background: linear-gradient(145deg, #26c6da 0%, var(--main-theme-color, #3c8dbc) 45%, #5c6bc0 100%);
        color: #fff; box-shadow: 0 6px 20px rgba(60, 141, 188, .45), 0 2px 8px rgba(0,0,0,.15);
        font-size: 1.25rem; line-height: 1; display: flex; align-items: center; justify-content: center;
        touch-action: none;
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        animation: snipe-ai-fab-idle 2.8s ease-in-out infinite;
    }
    .snipe-ai-chat-fab:active { cursor: grabbing; }
    .snipe-ai-chat-fab-icon {
        width: 2.15rem;
        height: 2.15rem;
        pointer-events: none;
        transition: transform .25s ease;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,.2));
    }
    .snipe-ai-chat-fab:hover, .snipe-ai-chat-fab:focus { filter: brightness(1.05); outline: 2px solid rgba(255,255,255,.6); }
    #snipe-ai-chat-root.chat-open .snipe-ai-chat-fab {
        animation: none;
        transform: scale(1.03);
        box-shadow: 0 6px 16px rgba(0,0,0,.24);
    }
    #snipe-ai-chat-root.chat-open .snipe-ai-chat-fab-icon { transform: rotate(12deg) scale(1.05); }
    .snipe-ai-chat-fab:hover { transform: translateY(-2px); }
    .snipe-ai-chat-panel {
        position: absolute; right: 0; bottom: 5.25rem;
        width: min(32rem, calc(100vw - 2rem));
        height: min(34rem, calc(100vh - 8rem));
        min-width: 22rem;
        min-height: 20rem;
        max-width: calc(100vw - 1rem);
        max-height: calc(100vh - 1rem);
        resize: both;
        overflow: hidden;
        background: var(--surface-1, #fff); color: var(--text, #333);
        border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,.18); display: flex; flex-direction: column;
        border: 1px solid rgba(0,0,0,.08);
        opacity: 0;
        transform: translateY(12px) scale(0.97);
        transform-origin: bottom right;
        pointer-events: none;
        transition: opacity .2s ease, transform .24s ease, box-shadow .24s ease;
    }
    .snipe-ai-chat-panel.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
        box-shadow: 0 12px 38px rgba(0,0,0,.22);
    }
    .snipe-ai-chat-header {
        padding: .6rem .75rem; font-weight: 600; border-bottom: 1px solid rgba(0,0,0,.08);
        display: flex; justify-content: space-between; align-items: center;
        background: var(--main-theme-color, #3c8dbc); color: #fff; border-radius: 8px 8px 0 0;
    }
    .snipe-ai-chat-close { background: transparent; border: none; color: #fff; font-size: 1.5rem; line-height: 1; cursor: pointer; opacity: .9; }
    .snipe-ai-chat-close:hover { opacity: 1; }
    .snipe-ai-chat-log { flex: 1; overflow-y: auto; padding: .75rem; min-height: 10rem; }
    .snipe-ai-chat-bubble { margin-bottom: .5rem; padding: .45rem .6rem; border-radius: 6px; white-space: pre-wrap; word-break: break-word; }
    .snipe-ai-chat-bubble.user { background: rgba(60, 141, 188, .12); margin-left: 1rem; }
    .snipe-ai-chat-bubble.ai { background: rgba(0,0,0,.05); margin-right: 1rem; }
    .snipe-ai-chat-bubble.err { background: rgba(221, 75, 57, .12); color: #a94442; }
    .snipe-ai-chat-links {
        margin: 0 0 0.5rem 0.75rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        max-width: 100%;
    }
    .snipe-ai-chat-links a {
        font-size: 12px;
        padding: 0.2rem 0.45rem;
        border-radius: 4px;
        border: 1px solid rgba(0,0,0,.12);
        background: rgba(60, 141, 188, .08);
        color: var(--main-theme-color, #3c8dbc);
        text-decoration: none;
    }
    .snipe-ai-chat-links a:hover { text-decoration: underline; }
    .snipe-ai-chat-form { padding: .5rem .75rem .75rem; border-top: 1px solid rgba(0,0,0,.08); display: flex; gap: .5rem; align-items: flex-end; flex-wrap: wrap; }
    .snipe-ai-chat-form textarea { flex: 1 1 100%; min-height: 4.5rem; resize: vertical; }
    .snipe-ai-chat-hint { margin: 0 .75rem .5rem; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    @keyframes snipe-ai-fab-idle {
        0% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
        100% { transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .snipe-ai-chat-fab,
        .snipe-ai-chat-fab-icon,
        .snipe-ai-chat-panel {
            animation: none !important;
            transition: none !important;
        }
    }
</style>

<script nonce="{{ csrf_token() }}">
(function () {
    var endpoint = @json(filled(config('ai_chat.endpoint_override')) ? config('ai_chat.endpoint_override') : route('ai-chat.message'));
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    var root = document.getElementById('snipe-ai-chat-root');
    if (!root) return;

    var btn = document.getElementById('snipe-ai-chat-toggle');
    var panel = document.getElementById('snipe-ai-chat-panel');
    var closeBtn = document.getElementById('snipe-ai-chat-close');
    var form = document.getElementById('snipe-ai-chat-form');
    var input = document.getElementById('snipe-ai-chat-input');
    var log = document.getElementById('snipe-ai-chat-log');
    var sendBtn = document.getElementById('snipe-ai-chat-send');
    var dragStoreKey = 'snipeAiChatWidgetPosV1';
    var suppressClick = false;
    var drag = {
        active: false,
        moved: false,
        offsetX: 0,
        offsetY: 0
    };

    function getWidgetMaxX() {
        return Math.max(12, window.innerWidth - root.offsetWidth - 12);
    }

    function getWidgetMaxY() {
        return Math.max(12, window.innerHeight - root.offsetHeight - 12);
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function moveWidget(x, y) {
        var boundedX = clamp(x, 12, getWidgetMaxX());
        var boundedY = clamp(y, 12, getWidgetMaxY());
        root.style.left = boundedX + 'px';
        root.style.top = boundedY + 'px';
        root.style.right = 'auto';
        root.style.bottom = 'auto';
    }

    function persistWidgetPosition() {
        var left = parseInt(root.style.left, 10);
        var top = parseInt(root.style.top, 10);
        if (!Number.isFinite(left) || !Number.isFinite(top)) return;
        localStorage.setItem(dragStoreKey, JSON.stringify({ left: left, top: top }));
    }

    function initWidgetPosition() {
        var raw = localStorage.getItem(dragStoreKey);
        if (raw) {
            try {
                var saved = JSON.parse(raw);
                if (Number.isFinite(saved.left) && Number.isFinite(saved.top)) {
                    moveWidget(saved.left, saved.top);
                    return;
                }
            } catch (e) {
                // ignore bad localStorage values
            }
        }

        var defaultLeft = window.innerWidth - root.offsetWidth - 20;
        var defaultTop = window.innerHeight - root.offsetHeight - 20;
        moveWidget(defaultLeft, defaultTop);
    }

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        root.classList.toggle('chat-open', open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) input.focus();
    }

    function isOpen() {
        return panel.classList.contains('is-open');
    }

    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'true');
    initWidgetPosition();

    btn.addEventListener('click', function () {
        if (suppressClick) {
            suppressClick = false;
            return;
        }
        setOpen(!isOpen());
    });
    closeBtn.addEventListener('click', function () { setOpen(false); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) setOpen(false);
    });

    window.addEventListener('resize', function () {
        var left = parseInt(root.style.left, 10);
        var top = parseInt(root.style.top, 10);
        if (!Number.isFinite(left) || !Number.isFinite(top)) return;
        moveWidget(left, top);
        persistWidgetPosition();
    });

    btn.addEventListener('pointerdown', function (e) {
        var rect = root.getBoundingClientRect();
        drag.active = true;
        drag.moved = false;
        drag.offsetX = e.clientX - rect.left;
        drag.offsetY = e.clientY - rect.top;
        btn.setPointerCapture(e.pointerId);
    });

    btn.addEventListener('pointermove', function (e) {
        if (!drag.active) return;
        var nextLeft = e.clientX - drag.offsetX;
        var nextTop = e.clientY - drag.offsetY;
        if (!drag.moved) {
            var currentLeft = parseInt(root.style.left, 10) || 0;
            var currentTop = parseInt(root.style.top, 10) || 0;
            if (Math.abs(nextLeft - currentLeft) > 3 || Math.abs(nextTop - currentTop) > 3) {
                drag.moved = true;
            }
        }
        moveWidget(nextLeft, nextTop);
    });

    btn.addEventListener('pointerup', function (e) {
        if (!drag.active) return;
        drag.active = false;
        if (btn.hasPointerCapture(e.pointerId)) {
            btn.releasePointerCapture(e.pointerId);
        }
        if (drag.moved) {
            suppressClick = true;
            persistWidgetPosition();
        }
    });

    btn.addEventListener('pointercancel', function (e) {
        drag.active = false;
        if (btn.hasPointerCapture(e.pointerId)) {
            btn.releasePointerCapture(e.pointerId);
        }
    });

    function appendBubble(text, cls) {
        var d = document.createElement('div');
        d.className = 'snipe-ai-chat-bubble ' + cls;
        d.textContent = text;
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
    }

    function appendAiReply(text, links) {
        appendBubble(text, 'ai');
        if (links && links.length) {
            var wrap = document.createElement('div');
            wrap.className = 'snipe-ai-chat-links';
            for (var i = 0; i < links.length; i++) {
                var item = links[i];
                if (!item || !item.url) continue;
                var a = document.createElement('a');
                a.href = item.url;
                a.textContent = item.label || item.url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                wrap.appendChild(a);
            }
            if (wrap.childNodes.length) {
                log.appendChild(wrap);
            }
        }
        log.scrollTop = log.scrollHeight;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = (input.value || '').trim();
        if (!msg) return;

        appendBubble(msg, 'user');
        input.value = '';
        sendBtn.disabled = true;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: msg })
        })
        .then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, status: r.status, j: j };
            }).catch(function () {
                return { ok: r.ok, status: r.status, j: {} };
            });
        })
        .then(function (x) {
            if (x.ok && x.j.reply !== undefined) {
                appendAiReply(x.j.reply || '(empty)', x.j.links || []);
            } else {
                var errLine = (x.j && (x.j.error || x.j.message)) ? (x.j.error || x.j.message) : '';
                appendBubble(errLine || ('Request failed (HTTP ' + (x.status || '?') + ')'), 'err');
            }
        })
        .catch(function () {
            appendBubble('Network error', 'err');
        })
        .finally(function () {
            sendBtn.disabled = false;
        });
    });
})();
</script>
