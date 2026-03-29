<!-- includes/chatbot.php  –  include just before </body> on student pages -->
<style>
#cogChatBtn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 58px;
    height: 58px;
    background: linear-gradient(135deg, #800000, #660000);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(128,0,0,.45);
    z-index: 1050;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
#cogChatBtn:hover { transform: scale(1.1); box-shadow: 0 8px 28px rgba(128,0,0,.6); }
#cogChatBtn .chat-badge {
    position: absolute;
    top: -4px; right: -4px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    width: 20px; height: 20px;
    font-size: 11px;
    display: flex; align-items: center; justify-content: center;
}

#cogChatWindow {
    position: fixed;
    bottom: 100px;
    right: 28px;
    width: 360px;
    max-height: 520px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 12px 40px rgba(0,0,0,.18);
    display: flex;
    flex-direction: column;
    z-index: 1049;
    overflow: hidden;
    transition: opacity .25s, transform .25s;
    transform: scale(.92) translateY(12px);
    opacity: 0;
    pointer-events: none;
}
#cogChatWindow.open {
    transform: scale(1) translateY(0);
    opacity: 1;
    pointer-events: all;
}

#cogChatHeader {
    background: linear-gradient(135deg, #800000, #660000);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
#cogChatHeader .bot-avatar {
    width: 36px; height: 36px;
    background: rgba(255,255,255,.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
#cogChatHeader .bot-info .bot-name { font-weight: 700; font-size: 15px; }
#cogChatHeader .bot-info .bot-status { font-size: 11px; opacity: .85; }
#cogChatHeader .close-btn {
    margin-left: auto;
    background: none;
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
    opacity: .8;
}
#cogChatHeader .close-btn:hover { opacity: 1; }

#cogChatMessages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8f9fa;
}
.chat-msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-word;
}
.chat-msg.bot {
    background: #fff;
    color: #333;
    border: 1px solid #e9ecef;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
}
.chat-msg.user {
    background: linear-gradient(135deg, #800000, #660000);
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.chat-msg.typing { color: #6c757d; font-style: italic; }
.chat-msg .msg-time { font-size: 10px; opacity: .6; margin-top: 4px; display: block; }

.quick-replies {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 16px 10px;
    background: #f8f9fa;
}
.quick-reply-btn {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 12px;
    cursor: pointer;
    transition: all .2s;
    color: #800000;
}
.quick-reply-btn:hover {
    background: #800000;
    color: #fff;
    border-color: #800000;
}

#cogChatInputArea {
    display: flex;
    gap: 8px;
    padding: 12px 14px;
    background: #fff;
    border-top: 1px solid #e9ecef;
    flex-shrink: 0;
}
#cogChatInput {
    flex: 1;
    border: 1px solid #dee2e6;
    border-radius: 22px;
    padding: 9px 16px;
    font-size: 14px;
    outline: none;
    resize: none;
    height: 40px;
    line-height: 1.4;
}
#cogChatInput:focus { border-color: #800000; }
#cogChatSend {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, #800000, #660000);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: transform .2s;
}
#cogChatSend:hover { transform: scale(1.1); }
#cogChatSend:disabled { opacity: .5; }

@media (max-width: 420px) {
    #cogChatWindow { width: calc(100vw - 16px); right: 8px; }
}
</style>

<!-- Chat toggle button -->
<button id="cogChatBtn" title="Chat with COGBot" aria-label="Open chatbot">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="chat-badge" id="chatBadge" style="display:none">1</span>
</button>

<!-- Chat window -->
<div id="cogChatWindow" role="dialog" aria-label="COGBot Chat">
    <div id="cogChatHeader">
        <div class="bot-avatar"><i class="bi bi-robot"></i></div>
        <div class="bot-info">
            <div class="bot-name">COGBot</div>
            <div class="bot-status">● Online – OLSHCO Assistant</div>
        </div>
        <button class="close-btn" id="cogChatClose" aria-label="Close chat">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div id="cogChatMessages"></div>

    <!-- Quick reply suggestions -->
    <div class="quick-replies" id="quickReplies">
        <button class="quick-reply-btn">How to request COG?</button>
        <button class="quick-reply-btn">Check my request status</button>
        <button class="quick-reply-btn">Payment process</button>
        <button class="quick-reply-btn">Processing time</button>
    </div>

    <div id="cogChatInputArea">
        <input id="cogChatInput" type="text" placeholder="Type your question…" maxlength="400" autocomplete="off">
        <button id="cogChatSend" title="Send"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<script>
(function () {
    const btn      = document.getElementById('cogChatBtn');
    const win      = document.getElementById('cogChatWindow');
    const closeBtn = document.getElementById('cogChatClose');
    const input    = document.getElementById('cogChatInput');
    const sendBtn  = document.getElementById('cogChatSend');
    const msgs     = document.getElementById('cogChatMessages');
    const qrDiv    = document.getElementById('quickReplies');
    const badge    = document.getElementById('chatBadge');

    let history  = [];
    let isOpen   = false;
    let hasGreeted = false;

    function now() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function appendMsg(text, role) {
        const div = document.createElement('div');
        div.className = `chat-msg ${role}`;
        div.innerHTML = `${escHtml(text)}<span class="msg-time">${now()}</span>`;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                  .replace(/\n/g,'<br>');
    }

    function showTyping() {
        const div = appendMsg('COGBot is typing…', 'bot typing');
        return div;
    }

    function greet() {
        if (hasGreeted) return;
        hasGreeted = true;
        appendMsg('👋 Hi! I\'m COGBot, your OLSHCO assistant.\nHow can I help you today?', 'bot');
        badge.style.display = 'flex';
    }

    function toggleChat() {
        isOpen = !isOpen;
        win.classList.toggle('open', isOpen);
        if (isOpen) {
            badge.style.display = 'none';
            greet();
            input.focus();
        }
    }

    async function sendMessage(text) {
        text = text.trim();
        if (!text || sendBtn.disabled) return;

        appendMsg(text, 'user');
        history.push({ role: 'user', content: text });
        qrDiv.style.display = 'none';

        input.value = '';
        sendBtn.disabled = true;

        const typingEl = showTyping();

        try {
            const res = await fetch('/chatbot_api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ message: text, history }),
            });
            const data = await res.json();
            typingEl.remove();

            if (data.reply) {
                appendMsg(data.reply, 'bot');
                history.push({ role: 'assistant', content: data.reply });
                // Trim history to avoid huge payloads
                if (history.length > 20) history = history.slice(-20);
            } else {
                appendMsg(data.error || 'Something went wrong. Try again.', 'bot');
            }
        } catch (err) {
            typingEl.remove();
            appendMsg('Connection error. Please check your internet and try again.', 'bot');
        }

        sendBtn.disabled = false;
        input.focus();
    }

    // Quick-reply buttons
    qrDiv.querySelectorAll('.quick-reply-btn').forEach(b => {
        b.addEventListener('click', () => sendMessage(b.textContent));
    });

    btn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    sendBtn.addEventListener('click', () => sendMessage(input.value));
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(input.value);
        }
    });
})();
</script>