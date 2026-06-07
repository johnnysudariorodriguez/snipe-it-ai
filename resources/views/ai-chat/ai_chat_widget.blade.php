{{-- Floating AI assistant (authenticated users only; API key stays server-side) --}}
<div id="snipe-ai-chat-root" aria-live="polite">
    <button type="button" id="snipe-ai-chat-toggle" class="snipe-ai-chat-fab" aria-expanded="false"
        aria-controls="snipe-ai-chat-panel" title="AI assistant — {{ trans('general.show_help') }}">
        <svg class="snipe-ai-chat-fab-icon" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="snipe-ai-fab-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#fff;stop-opacity:1" />
                    <stop offset="50%" style="stop-color:#e0f7ff;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#c8f0ff;stop-opacity:1" />
                </linearGradient>
            </defs>
            <circle cx="12" cy="13" r="9" fill="url(#snipe-ai-fab-grad)" opacity=".95" />
            <path fill="#1a6b9c"
                d="M11 4h2v2h3a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v7a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-7a2 2 0 0 1 2-2h1V8a2 2 0 0 1 2-2h3V4Zm5 7H8a1 1 0 0 0-1 1v4a3 3 0 0 0 3 3h4a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1ZM9 8v1h6V8H9Zm1.25 4.75a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm3.5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z" />
            <circle cx="9.25" cy="13.75" r="1.15" fill="#ff7043" />
            <circle cx="14.75" cy="13.75" r="1.15" fill="#7e57c2" />
        </svg>
    </button>
    <div id="snipe-ai-chat-panel" class="snipe-ai-chat-panel" hidden role="dialog" aria-label="AI assistant">
        {{-- header removed from top so Home tab shows full search UI without header --}}
        {{-- Two-tab layout: Home (help/search) and Messages (chat) --}}
        <div class="snipe-ai-tabs">

            <div id="snipe-ai-tab-home" class="snipe-ai-tab snipe-ai-tab-active" role="tabpanel" aria-hidden="false">
                <div class="snipe-ai-home-header">
                    <div class="snipe-ai-home-title">Get help from Snipe‑IT Support</div>
                </div>
                <div class="snipe-ai-search-wrap">
                    <div class="snipe-ai-search">
                        <input id="snipe-ai-search-input" type="search" placeholder="Search for help"
                            aria-label="Search for help">
                        <button type="button" class="snipe-ai-search-icon" aria-hidden="true">🔍</button>
                    </div>
                    <ul class="snipe-ai-suggestions" id="snipe-ai-suggestions">
                        <li class="snipe-ai-suggestion"
                            data-query="Network recommendations for ChatGPT errors on web and apps">Network
                            recommendations for ChatGPT errors on web and apps</li>
                        <li class="snipe-ai-suggestion" data-query="How can I contact support?">How can I contact
                            support?</li>
                        <li class="snipe-ai-suggestion"
                            data-query="What happens after I submit my ID for age verification?">What happens after I
                            submit my ID for age verification?</li>
                        <li class="snipe-ai-suggestion"
                            data-query="How do I request a refund for ChatGPT Plus or ChatGPT Pro?">How do I request a
                            refund for ChatGPT Plus or ChatGPT Pro?</li>
                    </ul>
                </div>

                <div class="snipe-ai-search-wrap">
                    <button id="snipe-ai-ask-btn" class="snipe-ai-ask-btn" type="button" aria-label="Ask a question">
                        <div class="snipe-ai-ask-title">Ask a question</div>
                        <div class="snipe-ai-ask-sub">AI Agent and team can help</div>
                    </button>
                </div>
            </div>

            <div id="snipe-ai-tab-chat" class="snipe-ai-tab" role="tabpanel" aria-hidden="true">
                <div class="snipe-ai-chat-header messages-header history-header">
                    <div class="messages-title">Messages</div>
                    <div class="messages-placeholder"></div>
                    <button type="button" id="snipe-ai-chat-close" class="snipe-ai-chat-close"
                        aria-label="{{ trans('general.cancel') }}">
                        <img src="/img/chatbot/close.png" alt="Close" class="snipe-ai-icon snipe-ai-close-icon" />
                    </button>
                </div>

                <div class="snipe-ai-chat-header convo-header" hidden>
                    <button type="button" id="snipe-ai-convo-back" class="snipe-ai-convo-back" aria-label="Back">
                        <img src="/img/chatbot/left-arrow.png" alt="Back" class="snipe-ai-icon" />
                    </button>
                    <div class="convo-meta">
                        <img src="/img/chatbot/bot.png" alt="Fin" class="convo-avatar-img" />
                        <div class="convo-info">
                            <div class="convo-name"><span id="snipe-ai-convo-name">Fin</span></div>
                            <div class="convo-time" id="snipe-ai-convo-time">Just now</div>
                        </div>
                    </div>
                    <div class="convo-actions">
                        {{-- <button type="button" id="snipe-ai-convo-toggle" class="snipe-ai-convo-toggle"
                            aria-label="Minimize">
                            <img src="/img/chatbot/minimize.png" alt="Minimize" class="snipe-ai-toggle-icon" />
                        </button> --}}
                        <button type="button" id="snipe-ai-convo-close" class="snipe-ai-chat-close"
                            aria-label="Close">
                            <img src="/img/chatbot/close.png" alt="Close" class="snipe-ai-icon" />
                        </button>
                    </div>
                </div>

                <div id="snipe-ai-messages-list" class="snipe-ai-messages-list" aria-live="polite">
                    {{-- Message history items will be rendered here. For testing, include one sample item. --}}
                    <div class="snipe-ai-message-item" data-convo-id="sample-1" role="button" tabindex="0">
                        <img src="/img/chatbot/bot.png" alt="Fin" class="snipe-ai-message-avatar" />
                        <div class="meta">
                            <div class="title">Rate your conversation</div>
                            <div class="muted">OpenAI · 1d ago</div>
                        </div>
                        <img src="/img/chatbot/left-arrow.png" alt="" class="snipe-ai-chev-icon"
                            aria-hidden="true" />
                    </div>

                    {{-- Ask button moved inside messages container so it's contained and sits above bottom nav --}}
                    <div class="snipe-ai-ask-floating-wrap">
                        <button id="snipe-ai-ask-floating" class="snipe-ai-ask-floating" type="button"
                            aria-label="Ask a question">
                            <span class="ask-label">Ask a question</span>
                        </button>
                    </div>
                </div>

                <div id="snipe-ai-chat-log" class="snipe-ai-chat-log" hidden></div>
                <form id="snipe-ai-chat-form" class="snipe-ai-chat-form" hidden>
                    <label class="sr-only" for="snipe-ai-chat-input">Message</label>
                    <textarea id="snipe-ai-chat-input" rows="1" maxlength="8000" required
                        placeholder="Ask about Snipe-IT or ITAM…"></textarea>
                    <button type="submit" class="btn" id="snipe-ai-chat-send" aria-label="Send">
                        <span class="send-icon">➤</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Bottom tab bar like mobile UI --}}
        <div class="snipe-ai-bottom-nav" role="tablist" aria-label="Chat tabs">
            <button class="nav-item nav-item-active" type="button" role="tab" data-tab="home"
                aria-controls="snipe-ai-tab-home" aria-selected="true">
                <span class="icon">📨</span>
                <span class="label">Home</span>
            </button>
            <button class="nav-item" type="button" role="tab" data-tab="chat"
                aria-controls="snipe-ai-tab-chat" aria-selected="false">
                <span class="icon">💬</span>
                <span class="label">Messages</span>
            </button>
            <button class="nav-item" type="button" role="tab" data-tab="help"
                aria-controls="snipe-ai-tab-home" aria-selected="false">
                <span class="icon">❓</span>
                <span class="label">Help</span>
            </button>
        </div>
    </div>
</div>

<style>
    #snipe-ai-chat-root {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 100050;
        font-size: 14px;
    }

    .snipe-ai-chat-fab {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, .35);
        cursor: pointer;
        background: linear-gradient(145deg, #26c6da 0%, var(--main-theme-color, #3c8dbc) 45%, #5c6bc0 100%);
        color: #fff;
        box-shadow: 0 6px 20px rgba(60, 141, 188, .45), 0 2px 8px rgba(0, 0, 0, .15);
        font-size: 1.25rem;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        touch-action: none;
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        animation: snipe-ai-fab-idle 2.8s ease-in-out infinite;
    }

    .snipe-ai-chat-fab:active {
        cursor: grabbing;
    }

    .snipe-ai-chat-fab-icon {
        width: 2.15rem;
        height: 2.15rem;
        pointer-events: none;
        transition: transform .25s ease;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .2));
    }

    .snipe-ai-chat-fab:hover,
    .snipe-ai-chat-fab:focus {
        filter: brightness(1.05);
        outline: 2px solid rgba(255, 255, 255, .6);
    }

    #snipe-ai-chat-root.chat-open .snipe-ai-chat-fab {
        animation: none;
        transform: scale(1.03);
        box-shadow: 0 6px 16px rgba(0, 0, 0, .24);
    }

    #snipe-ai-chat-root.chat-open .snipe-ai-chat-fab-icon {
        transform: rotate(12deg) scale(1.05);
    }

    .snipe-ai-chat-fab:hover {
        transform: translateY(-2px);
    }

    .snipe-ai-chat-panel {
        position: absolute;
        right: 1rem;
        bottom: 5.5rem;
        width: 360px;
        /* narrow, like second image */
        height: min(60vh, 720px);
        /* tall and responsive */
        min-width: 320px;
        min-height: 420px;
        max-width: calc(100vw - 2rem);
        max-height: calc(100vh - 3rem);
        overflow: hidden;
        background: #fff;
        color: #222;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(0, 0, 0, .06);
        opacity: 0;
        transform: translateY(12px) scale(0.99);
        transform-origin: bottom right;
        pointer-events: none;
        transition: opacity .18s ease, transform .18s ease, box-shadow .18s ease;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
        font-size: 15px;
        line-height: 1.35;
    }

    .snipe-ai-chat-panel.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
        box-shadow: 0 12px 38px rgba(0, 0, 0, .22);
    }

    .snipe-ai-chat-header {
        padding: .6rem .75rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: transparent;
        color: #111;
        border-radius: 12px 12px 0 0;
    }

    /* History header: title centered, close left */
    .history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .6rem .8rem;
    }

    .history-header .snipe-ai-chat-close {
        order: 2;
    }

    .history-header .messages-title {
        order: 1;
        flex: 1;
        text-align: center;
        font-weight: 600;
    }

    .history-header .messages-placeholder {
        order: 2;
        width: 24px;
    }

    /* Conversation header: back, avatar + title, controls on right */
    .convo-header {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .6rem .75rem;
        border-bottom: 1px solid rgba(0, 0, 0, .04);
    }

    /* Make history and convo headers consistent height */
    .history-header,
    .convo-header {
        height: 56px;
        /* fixed header height */
        padding-top: 0;
        padding-bottom: 0;
        align-items: center;
    }

    .snipe-ai-convo-back {
        background: transparent;
        border: none;
        padding: 0;
        cursor: pointer;
    }

    .snipe-ai-convo-back .snipe-ai-icon {
        width: 10px;
        height: 10px;
    }

    .convo-meta {
        display: flex;
        gap: .6rem;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .convo-avatar-img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        display: inline-block;
        flex: 0 0 auto;
    }

    .convo-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        overflow: hidden;
    }

    .convo-name {
        font-weight: 600;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .convo-time {
        font-size: .78rem;
        color: #999;
        margin-top: 2px;
    }

    .convo-actions {
        margin-left: auto;
        display: flex;
        gap: .4rem;
        align-items: center;
    }

    .snipe-ai-icon {
        width: 18px;
        height: 18px;
        display: inline-block;
    }

    /* Make close icons slightly smaller for a cleaner header */
    .snipe-ai-close-icon {
        width: 14px;
        height: 14px;
    }

    .snipe-ai-chat-close img {
        width: 10px;
        height: 10px;
    }

    .snipe-ai-toggle-icon {
        width: 10px;
        height: 10px;
        display: inline-block;
    }

    /* When conversation is open, hide bottom nav and let chat use full panel height */
    .snipe-ai-chat-panel.convo-open .snipe-ai-bottom-nav {
        display: none;
    }

    .snipe-ai-chat-close {
        background: transparent;
        border: none;
        color: #444;
        font-size: 1.1rem;
        line-height: 1;
        cursor: pointer;
        opacity: .9;
        padding: .25rem .5rem;
        border-radius: 6px;
    }

    .snipe-ai-chat-close:hover {
        opacity: 1;
    }

    .snipe-ai-chat-log {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: .9rem 1rem;
        min-height: 10rem;
        display: flex;
        flex-direction: column;
        gap: .6rem;
    }

    .snipe-ai-chat-bubble {
        max-width: 84%;
        padding: .6rem .75rem;
        border-radius: 12px;
        white-space: pre-wrap;
        word-break: break-word;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .03) inset;
    }

    .snipe-ai-chat-bubble.user {
        background: #0b6aa3;
        /* brandy blue */
        color: #fff;
        align-self: flex-end;
        border-bottom-right-radius: 10px;
    }

    .snipe-ai-chat-bubble.ai {
        background: #f5f7f9;
        color: #111;
        align-self: flex-start;
        border-bottom-left-radius: 10px;
    }

    .snipe-ai-chat-bubble.err {
        background: rgba(221, 75, 57, .12);
        color: #a94442;
    }

    .snipe-ai-chat-links {
        margin: 0 0 0.5rem 0.75rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        max-width: 100%;
    }

    .snipe-ai-chat-links a {
        font-size: 13px;
        padding: 0.35rem .6rem;
        border-radius: 6px;
        border: 1px solid rgba(0, 0, 0, .06);
        background: #fff;
        color: var(--main-theme-color, #3c8dbc);
        text-decoration: none;
    }

    .snipe-ai-chat-links a:hover {
        text-decoration: underline;
    }

    .snipe-ai-chat-form {
        padding: .4rem .75rem .6rem .75rem;
        border-top: 1px solid rgba(0, 0, 0, .06);
        display: flex;
        gap: .5rem;
        align-items: center;
        position: relative;
        background: transparent;
    }

    .snipe-ai-chat-form textarea {
        flex: 1 1 auto;
        min-height: 44px;
        /* The container height */
        max-height: 6rem;
        resize: none;

        /* Adjusted padding and sizing */
        padding: 12px 4.2rem 12px 1.5rem;
        /* Use pixel values for better control */
        line-height: 20px;
        /* Adjust this to match your desired text height */

        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: #fff;
        font-size: 15px;
        overflow: auto;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03) inset;

        /* Ensure the textarea itself is centered within the flex container */
        align-self: center;
    }

    /* Remove platform focus rings / orange accents while preserving subtle inset */
    .snipe-ai-chat-form textarea:focus {
        outline: none;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03) inset;
    }

    .snipe-ai-chat-form #snipe-ai-chat-send {
        position: absolute;
        right: 1.9rem;
        /* fixed to right inside the form */
        top: 50%;
        transform: translateY(-50%);
        background: #111;
        /* black circular button like image */
        color: #fff;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .14);
        padding: 0;
        cursor: pointer;
        z-index: 3;
    }

    .snipe-ai-chat-form #snipe-ai-chat-send:focus,
    .snipe-ai-chat-form #snipe-ai-chat-send:active {
        outline: none;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .14);
    }

    .send-icon {
        font-size: 14px;
        line-height: 1;
        transform: translateX(1px);
        color: #fff;
    }

    .snipe-ai-chat-hint {
        margin: 0 .75rem .5rem;
    }

    /* Search / suggestions */
    .snipe-ai-search-wrap {
        padding: .5rem .75rem;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
    }

    /* make search and ask containers look like cards */
    .snipe-ai-search-wrap {
        background: var(--surface-1, #fff);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
        margin: .6rem .75rem 0 .75rem;
        padding: .6rem;
    }

    /* Home header (acts as large title area like reference) */
    .snipe-ai-home-header {
        padding: 1.6rem 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .snipe-ai-home-title {
        font-size: 22px;
        font-weight: 600;
        color: #111;
        text-align: center;
        padding-top: 5.25rem;
        padding-bottom: 5.25rem;
    }

    /* Ask card flows in normal document order under search; no absolute positioning */
    .snipe-ai-ask-btn {
        position: static;
        width: 100%;
        margin-top: .6rem;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
    }

    /* Reduce extra bottom padding so header + content scroll naturally */
    .snipe-ai-search-wrap {
        padding-bottom: 1.25rem;
    }

    .snipe-ai-search {
        display: flex;
        gap: .5rem;
        align-items: center;
    }

    .snipe-ai-search input {
        flex: 1;
        padding: .5rem .6rem;
        border-radius: 8px;
        border: 1px solid rgba(0, 0, 0, .08);
    }

    .snipe-ai-search-icon {
        background: transparent;
        border: none;
        font-size: 1.05rem;
    }

    .snipe-ai-suggestions {
        list-style: none;
        padding: .5rem 0 0 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }

    .snipe-ai-suggestion {
        padding: .45rem .6rem;
        border-radius: 6px;
        background: transparent;
        cursor: pointer;
        border: 1px solid rgba(0, 0, 0, .04);
    }

    .snipe-ai-suggestion:hover {
        background: rgba(0, 0, 0, .03);
    }

    /* Ask card */
    .snipe-ai-ask-btn {
        width: 100%;
        text-align: left;
        margin-top: .5rem;
        padding: .6rem;
        border-radius: 8px;
        border: 1px solid rgba(0, 0, 0, .06);
        background: white;
    }

    .snipe-ai-ask-title {
        font-weight: 600;
    }

    .snipe-ai-ask-sub {
        font-size: .85rem;
        color: #666;
    }

    /* Bottom nav (compact) */
    .snipe-ai-bottom-nav {
        display: flex;
        gap: 1rem;
        justify-content: space-around;
        padding: .5rem;
        border-top: 1px solid rgba(0, 0, 0, .06);
        background: var(--surface-1, #fff);
    }

    .snipe-ai-bottom-nav .nav-item {
        font-size: .9rem;
        color: #444;
        text-align: center;
    }

    .nav-item {
        background: transparent;
        border: none;
        padding: .4rem .6rem;
        border-radius: 8px;
    }

    .nav-item-active {
        background: rgba(0, 0, 0, .05);
    }

    .snipe-ai-tab {
        display: none;
    }

    .snipe-ai-tab-active {
        display: block;
    }

    /* Make tabs area fill panel and be scrollable so bottom nav stays visible */
    .snipe-ai-tabs {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .snipe-ai-tab {
        display: none;
        flex: 1 1 auto;
        overflow: hidden;
    }

    .snipe-ai-tab-active {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Content inside each tab should scroll within the tab area */
    .snipe-ai-tab .snipe-ai-search-wrap,
    .snipe-ai-tab .snipe-ai-messages-list,
    .snipe-ai-tab .snipe-ai-chat-log {
        overflow-y: auto;
    }

    .snipe-ai-search-wrap {
        flex: 0 0 auto;
    }

    /* .snipe-ai-messages-list {
        flex: 1 1 auto;
    } */

    .snipe-ai-chat-log {
        flex: 1 1 auto;
    }

    /* Messages list + floating ask button */
    .snipe-ai-messages-list {
        min-height: 12rem;
        max-height: calc(100% - 6.5rem);
        overflow-y: auto;
        padding: .5rem .75rem;
    }

    .snipe-ai-message-item {
        display: flex;
        gap: .5rem;
        align-items: center;
        padding: .6rem;
        border-bottom: 1px solid rgba(0, 0, 0, .04);
    }

    .snipe-ai-message-item {
        cursor: pointer;
    }

    .snipe-ai-message-item:hover {
        background: rgba(0, 0, 0, 0.02);
    }

    .snipe-ai-message-item .title {
        font-weight: 600;
        display: block;
        word-break: break-word;
        font-size: 15px;
    }

    .snipe-ai-message-item .muted {
        font-size: .78rem;
        color: #888;
        margin-top: 2px;
        font-size: 10px;
    }

    .snipe-ai-message-item .chev {
        margin-left: auto;
        color: #bbb;
        font-size: 1.2rem;
    }

    .snipe-ai-chev-icon {
        margin-left: auto;
        width: 10px;
        height: 10px;
        display: inline-block;
        transform: scaleX(-1);
        /* mirror left-arrow to point right */
        opacity: .85;
    }

    .snipe-ai-message-avatar,
    .snipe-ai-message-item img.snipe-ai-message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        display: inline-block;
        flex: 0 0 auto;
    }

    .snipe-ai-message-item .meta {
        font-size: .9rem;
        color: #333;
        display: flex;
        flex-direction: column;
        gap: 2px;
        line-height: 1.15;
        flex: 1 1 auto;
        min-width: 0;
        /* allow text to wrap inside flex */
        align-items: flex-start;
    }

    /* Ask button is now inside the messages list; make it stick to the bottom of the scroll area */
    /* Position ask button at bottom of Messages tab, above bottom nav */
    #snipe-ai-tab-chat {
        position: relative;
    }

    .snipe-ai-ask-floating-wrap {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 4.2rem;
        z-index: 110;
        display: block;
        width: auto;
        pointer-events: none;
    }

    .snipe-ai-ask-floating {
        pointer-events: auto;
        display: inline-flex;
        padding: .55rem 1rem;
        border-radius: 20px;
        border: none;
        background: #111;
        color: #fff;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .12);
    }

    .snipe-ai-ask-floating .ask-label {
        font-weight: 600;
    }

    /* Bottom nav layout: icons above labels */
    .snipe-ai-bottom-nav {
        display: flex;
        gap: 1rem;
        justify-content: space-around;
        padding: .6rem 0 .9rem 0;
        border-top: 1px solid rgba(0, 0, 0, .04);
        background: #fff;
        flex: 0 0 auto;
        border-radius: 0 0 12px 12px;
        box-shadow: 0 -8px 20px rgba(0, 0, 0, .06);
    }

    .snipe-ai-bottom-nav .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .25rem;
        font-size: .85rem;
        color: #7a7a7a;
        background: transparent;
        border: none;
        padding: .35rem .5rem;
        border-radius: 8px;
    }

    .snipe-ai-bottom-nav .nav-item .icon {
        font-size: 1.18rem;
        display: inline-flex;
        width: 36px;
        height: 36px;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .snipe-ai-bottom-nav .nav-item.nav-item-active {
        color: #111;
        font-weight: 600;
    }

    .snipe-ai-bottom-nav .nav-item.nav-item-active .icon {
        background: #111;
        color: #fff;
        border-radius: 10px;
    }

    /* Messages header inside Messages tab (keeps header out of Home) */
    .snipe-ai-chat-header.messages-header {
        padding: .6rem .75rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--surface-1, #fff);
        color: var(--text, #333);
        border-radius: 8px 8px 0 0;
    }

    .snipe-ai-chat-header.messages-header .snipe-ai-chat-close {
        color: #333;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    @keyframes snipe-ai-fab-idle {
        0% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-2px);
        }

        100% {
            transform: translateY(0);
        }
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
    (function() {
        var endpoint = @json(filled(config('ai_chat.endpoint_override')) ? config('ai_chat.endpoint_override') : route('ai-chat.message'));
        var token = document.querySelector('meta[name="csrf-token"]');
        token = token ? token.getAttribute('content') : '';

        var root = document.getElementById('snipe-ai-chat-root');
        if (!root) return;

        var btn = document.getElementById('snipe-ai-chat-toggle');
        var panel = document.getElementById('snipe-ai-chat-panel');
        var closeBtn = document.getElementById('snipe-ai-chat-close');
        var convoBackBtn = document.getElementById('snipe-ai-convo-back');
        var convoCloseBtn = document.getElementById('snipe-ai-convo-close');
        var convoToggle = document.getElementById('snipe-ai-convo-toggle');
        var convoHeader = panel.querySelector('.convo-header');
        var form = document.getElementById('snipe-ai-chat-form');
        var input = document.getElementById('snipe-ai-chat-input');
        var log = document.getElementById('snipe-ai-chat-log');
        var sendBtn = document.getElementById('snipe-ai-chat-send');
        var searchInput = document.getElementById('snipe-ai-search-input');
        var suggestions = document.getElementById('snipe-ai-suggestions');
        var askBtn = document.getElementById('snipe-ai-ask-btn');
        var tabHome = document.getElementById('snipe-ai-tab-home');
        var tabChat = document.getElementById('snipe-ai-tab-chat');
        var navItems = panel.querySelectorAll('.snipe-ai-bottom-nav .nav-item');
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
            localStorage.setItem(dragStoreKey, JSON.stringify({
                left: left,
                top: top
            }));
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

        btn.addEventListener('click', function() {
            if (suppressClick) {
                suppressClick = false;
                return;
            }
            setOpen(!isOpen());
        });
        closeBtn.addEventListener('click', function() {
            setOpen(false);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isOpen()) setOpen(false);
        });

        window.addEventListener('resize', function() {
            var left = parseInt(root.style.left, 10);
            var top = parseInt(root.style.top, 10);
            if (!Number.isFinite(left) || !Number.isFinite(top)) return;
            moveWidget(left, top);
            persistWidgetPosition();
        });

        btn.addEventListener('pointerdown', function(e) {
            var rect = root.getBoundingClientRect();
            drag.active = true;
            drag.moved = false;
            drag.offsetX = e.clientX - rect.left;
            drag.offsetY = e.clientY - rect.top;
            btn.setPointerCapture(e.pointerId);
        });

        btn.addEventListener('pointermove', function(e) {
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

        btn.addEventListener('pointerup', function(e) {
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

        btn.addEventListener('pointercancel', function(e) {
            drag.active = false;
            if (btn.hasPointerCapture(e.pointerId)) {
                btn.releasePointerCapture(e.pointerId);
            }
        });

        // Suggestions click: populate chat input and focus
        if (suggestions) {
            suggestions.addEventListener('click', function(e) {
                var li = e.target.closest('.snipe-ai-suggestion');
                if (!li) return;
                var q = li.dataset.query || li.textContent || '';
                if (!q) return;
                input.value = q;
                setOpen(true);
                input.focus();
            });
        }

        // Ask button behavior: open and focus chat input
        if (askBtn) {
            askBtn.addEventListener('click', function() {
                // create new convo and open it
                var id = 'new-' + Date.now();
                messagesStore[id] = {
                    id: id,
                    title: 'New conversation',
                    updated_at: Date.now(),
                    messages: []
                };
                switchTab('chat');
                setOpen(true);
                openConversation(id);
                input.focus();
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            });
        }

        // messages store (in-memory for testing)
        var messagesStore = {
            'sample-1': {
                id: 'sample-1',
                title: 'Rate your conversation',
                updated_at: Date.now() - 24 * 60 * 60 * 1000,
                messages: [{
                        from: 'ai',
                        text: 'Hi there — this is a sample reply from the AI.'
                    },
                    {
                        from: 'user',
                        text: 'Thanks, I have a question about billing.'
                    }
                ]
            }
        };

        var currentConversationId = null;

        var messagesList = document.getElementById('snipe-ai-messages-list');

        function renderMessagesList() {
            // keep the ask button at the end; rebuild list except ask wrapper
            if (!messagesList) return;
            var askWrap = messagesList.querySelector('.snipe-ai-ask-floating-wrap');
            // clear existing items
            messagesList.innerHTML = '';

            // render each convo
            Object.keys(messagesStore).forEach(function(id) {
                var convo = messagesStore[id];
                var item = document.createElement('div');
                item.className = 'snipe-ai-message-item';
                item.setAttribute('data-convo-id', id);
                item.setAttribute('role', 'button');
                item.tabIndex = 0;

                var av = document.createElement('img');
                av.className = 'snipe-ai-message-avatar';
                av.src = '/img/chatbot/bot.png';
                av.alt = convo.title || 'Fin';

                var meta = document.createElement('div');
                meta.className = 'meta';
                var title = document.createElement('div');
                title.className = 'title';
                title.textContent = convo.title || 'Conversation';
                var muted = document.createElement('div');
                muted.className = 'muted';
                muted.textContent = 'You · just now';
                meta.appendChild(title);
                meta.appendChild(muted);
                var chev = document.createElement('img');
                chev.className = 'snipe-ai-chev-icon';
                chev.src = '/img/chatbot/left-arrow.png';
                chev.alt = '';
                chev.setAttribute('aria-hidden', 'true');
                item.appendChild(av);
                item.appendChild(meta);
                item.appendChild(chev);
                messagesList.appendChild(item);
            });

            // re-add ask button wrapper
            if (askWrap) messagesList.appendChild(askWrap);
        }

        function openConversation(id) {
            currentConversationId = id;
            // hide messages list, show chat log + form
            if (messagesList) messagesList.style.display = 'none';
            if (log) {
                log.hidden = false;
                log.style.display = 'flex';
            }
            if (form) {
                form.hidden = false;
                form.style.display = 'flex';
            }
            // show conversation header, hide history header
            var historyHeader = panel.querySelector('.history-header');
            if (historyHeader) historyHeader.hidden = true;
            if (convoHeader) convoHeader.hidden = false;
            // hide bottom nav when in conversation
            var bottomNav = panel.querySelector('.snipe-ai-bottom-nav');
            if (bottomNav) bottomNav.style.display = 'none';
            panel.classList.add('convo-open');
            // set header title and time
            if (messagesStore[id]) {
                var nameEl = document.getElementById('snipe-ai-convo-name');
                var timeEl = document.getElementById('snipe-ai-convo-time');
                if (nameEl) nameEl.textContent = messagesStore[id].title || 'Fin';
                if (timeEl) timeEl.textContent = new Date(messagesStore[id].updated_at).toLocaleString();
            }

            // populate chat log
            if (!log) return;
            log.innerHTML = '';
            var convo = messagesStore[id] || {
                messages: []
            };
            (convo.messages || []).forEach(function(m) {
                appendBubble(m.text, m.from === 'user' ? 'user' : 'ai');
            });
        }

        // floating ask button inside Messages tab: start a new conversation
        var askFloating = document.getElementById('snipe-ai-ask-floating');
        if (askFloating) {
            askFloating.addEventListener('click', function() {
                // create new convo
                var id = 'new-' + Date.now();
                messagesStore[id] = {
                    id: id,
                    title: 'New conversation',
                    updated_at: Date.now(),
                    messages: []
                };
                switchTab('chat');
                setOpen(true);
                // hide messages list and open conversation
                openConversation(id);
                input.focus();
            });
        }

        // clicking a message in the messages list opens it
        if (messagesList) {
            messagesList.addEventListener('click', function(e) {
                var item = e.target.closest('.snipe-ai-message-item');
                if (!item) return;
                var id = item.getAttribute('data-convo-id');
                if (id) {
                    switchTab('chat');
                    openConversation(id);
                }
            });
        }

        // ensure messages list is rendered initially
        renderMessagesList();

        // Simple suggestion filtering
        if (searchInput && suggestions) {
            searchInput.addEventListener('input', function() {
                var v = (searchInput.value || '').toLowerCase();
                Array.from(suggestions.children).forEach(function(li) {
                    var txt = (li.dataset.query || li.textContent || '').toLowerCase();
                    li.style.display = txt.indexOf(v) === -1 ? 'none' : '';
                });
            });
        }

        function switchTab(which) {
            // map help to home for now
            if (which === 'help') which = 'home';

            if (which === 'home') {
                tabHome.classList.add('snipe-ai-tab-active');
                tabHome.setAttribute('aria-hidden', 'false');
                tabChat.classList.remove('snipe-ai-tab-active');
                tabChat.setAttribute('aria-hidden', 'true');
            } else if (which === 'chat') {
                tabChat.classList.add('snipe-ai-tab-active');
                tabChat.setAttribute('aria-hidden', 'false');
                tabHome.classList.remove('snipe-ai-tab-active');
                tabHome.setAttribute('aria-hidden', 'true');

                // Always show the messages list when entering Messages tab
                if (messagesList) {
                    messagesList.style.display = '';
                }
                if (log) {
                    log.hidden = true;
                }
                if (form) {
                    form.hidden = true;
                }
                // show history header, hide convo header
                var historyHeader = panel.querySelector('.history-header');
                if (historyHeader) historyHeader.hidden = false;
                if (convoHeader) convoHeader.hidden = true;
                // show bottom nav
                var bottomNav = panel.querySelector('.snipe-ai-bottom-nav');
                if (bottomNav) bottomNav.style.display = '';
                panel.classList.remove('convo-open');
                renderMessagesList();
            }

            // update nav active state
            if (navItems && navItems.length) {
                Array.from(navItems).forEach(function(it) {
                    if ((it.dataset && it.dataset.tab) === which) {
                        it.classList.add('nav-item-active');
                        it.setAttribute('aria-selected', 'true');
                    } else {
                        it.classList.remove('nav-item-active');
                        it.setAttribute('aria-selected', 'false');
                    }
                });
            }
        }

        // attach click handlers to bottom nav items
        if (navItems && navItems.length) {
            Array.from(navItems).forEach(function(it) {
                it.addEventListener('click', function() {
                    var t = it.dataset && it.dataset.tab ? it.dataset.tab : 'home';
                    switchTab(t);
                });
            });
        }

        // ensure initial tab is Home
        switchTab('home');

        // wire convo close button (top-right in convo header)
        if (convoCloseBtn) convoCloseBtn.addEventListener('click', function() {
            setOpen(false);
        });

        // wire convo minimize/maximize toggle
        if (convoToggle) {
            convoToggle.addEventListener('click', function() {
                var img = convoToggle.querySelector('img');
                var minimized = panel.classList.toggle('convo-minimized');
                if (img) {
                    img.src = minimized ? '/img/chatbot/maximize.png' : '/img/chatbot/minimize.png';
                    img.alt = minimized ? 'Maximize' : 'Minimize';
                }
                // hide/show chat log and form when minimized
                if (minimized) {
                    if (log) log.style.display = 'none';
                    if (form) form.style.display = 'none';
                } else {
                    if (log) log.style.display = panel.classList.contains('convo-open') ? 'flex' : 'none';
                    if (form) form.style.display = panel.classList.contains('convo-open') ? 'flex' : 'none';
                }
            });
        }

        // wire convo back button to return to history list
        if (convoBackBtn) convoBackBtn.addEventListener('click', function() {
            // clear current conversation and show history
            currentConversationId = null;
            if (messagesList) messagesList.style.display = '';
            if (log) {
                log.hidden = true;
                log.style.display = '';
            }
            if (form) {
                form.hidden = true;
                form.style.display = '';
            }
            var historyHeader = panel.querySelector('.history-header');
            if (historyHeader) historyHeader.hidden = false;
            if (convoHeader) convoHeader.hidden = true;
            var bottomNav = panel.querySelector('.snipe-ai-bottom-nav');
            if (bottomNav) bottomNav.style.display = '';
            panel.classList.remove('convo-open');
            renderMessagesList();
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

        // Keyboard shortcuts: Enter to send (Shift+Enter = newline), Ctrl/Cmd+Enter to send
        if (input) {
            input.addEventListener('keydown', function(e) {
                var isEnter = e.key === 'Enter' || e.keyCode === 13;
                if (!isEnter) return;

                // Send when Enter pressed without Shift/Alt, or when Ctrl/Cmd+Enter pressed
                var ctrlOrMeta = e.ctrlKey || e.metaKey;
                var sendOnEnter = !e.shiftKey && !e.altKey;

                if (sendOnEnter || ctrlOrMeta) {
                    e.preventDefault();
                    if (form) {
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else if (sendBtn && typeof sendBtn.click === 'function') {
                            sendBtn.click();
                        } else {
                            form.dispatchEvent(new Event('submit', {
                                cancelable: true
                            }));
                        }
                    }
                }
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var msg = (input.value || '').trim();
            if (!msg) return;
            // ensure we have an active conversation
            if (!currentConversationId) {
                currentConversationId = 'new-' + Date.now();
                messagesStore[currentConversationId] = {
                    id: currentConversationId,
                    title: 'New conversation',
                    updated_at: Date.now(),
                    messages: []
                };
            }

            // save and render user message
            messagesStore[currentConversationId].messages.push({
                from: 'user',
                text: msg
            });
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
                    body: JSON.stringify({
                        message: msg
                    })
                })
                .then(function(r) {
                    return r.json().then(function(j) {
                        return {
                            ok: r.ok,
                            status: r.status,
                            j: j
                        };
                    }).catch(function() {
                        return {
                            ok: r.ok,
                            status: r.status,
                            j: {}
                        };
                    });
                })
                .then(function(x) {
                    if (x.ok && x.j.reply !== undefined) {
                        // store AI reply
                        var replyText = x.j.reply || '(empty)';
                        if (currentConversationId && messagesStore[currentConversationId]) {
                            messagesStore[currentConversationId].messages.push({
                                from: 'ai',
                                text: replyText
                            });
                        }
                        appendAiReply(replyText, x.j.links || []);
                    } else {
                        var errLine = (x.j && (x.j.error || x.j.message)) ? (x.j.error || x.j.message) :
                            '';
                        appendBubble(errLine || ('Request failed (HTTP ' + (x.status || '?') + ')'),
                            'err');
                    }
                })
                .catch(function() {
                    appendBubble('Network error', 'err');
                })
                .finally(function() {
                    sendBtn.disabled = false;
                });
        });
    })();
</script>
