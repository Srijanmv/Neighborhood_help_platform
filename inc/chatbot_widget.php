<?php
include __DIR__ . '/browser_push.php';

$nhBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$nhApiPath = ($nhBasePath === '' ? '' : $nhBasePath) . '/chatbot_api.php';
?>
<div class="nhbot-root" id="nhBotRoot">
  <button class="nhbot-fab" id="nhBotFab" type="button" aria-label="Open assistant">
    <span class="nhbot-fab-dot">AI</span>
    <span class="nhbot-fab-text">Neighborhood Help Chatbot</span>
  </button>

  <section class="nhbot-panel" id="nhBotPanel" aria-live="polite">
    <header class="nhbot-head">
      <div>
        <h3>Neighborhood Help Chatbot</h3>
        <p>Fast help for login, OTP, posts, and map.</p>
      </div>
      <button class="nhbot-close" id="nhBotClose" type="button" aria-label="Close">x</button>
    </header>

    <div class="nhbot-body" id="nhBotBody">
      <article class="nhbot-msg nhbot-bot">
        <p>Hi, I am Neighborhood Help Chatbot. Ask me anything about this app workflow.</p>
      </article>
    </div>

    <div class="nhbot-chips" id="nhBotChips">
      <button type="button" data-chip="How to create a new post?">New post</button>
      <button type="button" data-chip="OTP not received">OTP help</button>
      <button type="button" data-chip="Google button disabled">Google issue</button>
    </div>

    <form class="nhbot-form" id="nhBotForm">
      <input id="nhBotInput" type="text" placeholder="Type your question..." maxlength="320" autocomplete="off" required>
      <button type="submit">Send</button>
    </form>
  </section>
</div>

<style>
  .nhbot-root {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 2147483647;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    font-family: 'Manrope', sans-serif;
  }

  .nhbot-fab {
    border: 0;
    border-radius: 999px;
    padding: 12px 16px 12px 12px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #14532d, #22c55e);
    color: #fff;
    font-weight: 800;
    box-shadow: 0 16px 34px rgba(20, 83, 45, 0.28);
    cursor: pointer;
  }

  .nhbot-fab-dot {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.5);
    font-size: 0.82rem;
  }

  .nhbot-fab-text {
    font-size: 0.95rem;
    letter-spacing: 0.01em;
  }

  .nhbot-panel {
    width: min(390px, calc(100vw - 22px));
    height: min(560px, calc(100vh - 88px));
    margin-top: 10px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(20, 83, 45, 0.16);
    border-radius: 24px;
    box-shadow: 0 24px 60px rgba(17, 24, 39, 0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
  }

  .nhbot-panel.open {
    display: flex;
  }

  .nhbot-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 16px 12px;
    border-bottom: 1px solid rgba(20, 83, 45, 0.12);
    background: linear-gradient(135deg, rgba(217, 249, 157, 0.45), rgba(249, 115, 22, 0.14));
  }

  .nhbot-head h3 {
    margin: 0;
    font-size: 1rem;
    color: #14532d;
  }

  .nhbot-head p {
    margin: 4px 0 0;
    font-size: 0.84rem;
    color: #4b5563;
    line-height: 1.45;
  }

  .nhbot-close {
    border: 0;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    color: #14532d;
    font-weight: 800;
    cursor: pointer;
  }

  .nhbot-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    background:
      radial-gradient(circle at 10% 8%, rgba(34, 197, 94, 0.06), transparent 20%),
      radial-gradient(circle at 90% 0%, rgba(249, 115, 22, 0.08), transparent 22%),
      #fff;
  }

  .nhbot-msg {
    max-width: 88%;
    margin-bottom: 10px;
    padding: 10px 12px;
    border-radius: 14px;
    font-size: 0.92rem;
    line-height: 1.5;
    word-break: break-word;
  }

  .nhbot-msg p {
    margin: 0;
  }

  .nhbot-bot {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #14532d;
    border-top-left-radius: 6px;
  }

  .nhbot-user {
    margin-left: auto;
    background: #14532d;
    color: #fff;
    border-top-right-radius: 6px;
  }

  .nhbot-chips {
    display: flex;
    gap: 8px;
    padding: 0 14px 12px;
    flex-wrap: wrap;
    border-top: 1px solid rgba(20, 83, 45, 0.08);
    background: rgba(248, 250, 252, 0.9);
  }

  .nhbot-chips button {
    border: 1px solid rgba(20, 83, 45, 0.2);
    background: #fff;
    color: #14532d;
    border-radius: 999px;
    padding: 6px 11px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
  }

  .nhbot-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid rgba(20, 83, 45, 0.12);
    background: #fff;
  }

  .nhbot-form input {
    width: 100%;
    border: 1px solid rgba(20, 83, 45, 0.2);
    border-radius: 12px;
    padding: 10px 12px;
    font: inherit;
    color: #1f2937;
  }

  .nhbot-form input:focus {
    outline: 2px solid rgba(34, 197, 94, 0.25);
    border-color: rgba(20, 83, 45, 0.28);
  }

  .nhbot-form button {
    border: 0;
    border-radius: 12px;
    padding: 0 14px;
    font-weight: 800;
    background: linear-gradient(135deg, #14532d, #22c55e);
    color: #fff;
    cursor: pointer;
  }

  @media (max-width: 640px) {
    .nhbot-root {
      right: 10px;
      bottom: 10px;
    }

    .nhbot-fab-text {
      display: none;
    }

    .nhbot-panel {
      width: calc(100vw - 20px);
      height: min(70vh, 520px);
    }
  }
</style>

<script>
  (function () {
    const chatbotApiUrl = <?php echo json_encode($nhApiPath); ?>;
    const root = document.getElementById('nhBotRoot');
    if (!root) return;

    const fab = document.getElementById('nhBotFab');
    const panel = document.getElementById('nhBotPanel');
    const closeBtn = document.getElementById('nhBotClose');
    const body = document.getElementById('nhBotBody');
    const form = document.getElementById('nhBotForm');
    const input = document.getElementById('nhBotInput');
    const chips = document.getElementById('nhBotChips');

    function togglePanel(forceOpen) {
      const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !panel.classList.contains('open');
      panel.classList.toggle('open', shouldOpen);
      if (shouldOpen) {
        setTimeout(() => input.focus(), 80);
      }
    }

    function addMessage(text, type) {
      const wrap = document.createElement('article');
      wrap.className = 'nhbot-msg ' + (type === 'user' ? 'nhbot-user' : 'nhbot-bot');
      const p = document.createElement('p');
      p.textContent = text;
      wrap.appendChild(p);
      body.appendChild(wrap);
      body.scrollTop = body.scrollHeight;
    }

    function updateChips(suggestions) {
      if (!chips || !Array.isArray(suggestions) || suggestions.length === 0) return;
      chips.innerHTML = '';
      suggestions.slice(0, 3).forEach((text) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.chip = text;
        btn.textContent = text;
        chips.appendChild(btn);
      });
    }

    async function askBot(question) {
      addMessage(question, 'user');
      addMessage('Thinking...', 'bot');
      const typingNode = body.lastElementChild;

      try {
        const response = await fetch(chatbotApiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: question })
        });

        const data = await response.json();
        const text = data && data.reply ? data.reply : 'Sorry, I could not process this right now.';
        if (typingNode && typingNode.parentNode) {
          typingNode.parentNode.removeChild(typingNode);
        }
        addMessage(text, 'bot');

        if (data && data.suggestions) {
          updateChips(data.suggestions);
        }
      } catch (error) {
        if (typingNode && typingNode.parentNode) {
          typingNode.parentNode.removeChild(typingNode);
        }
        addMessage('Network issue. Please try again in a moment.', 'bot');
      }
    }

    fab.addEventListener('click', () => togglePanel());
    closeBtn.addEventListener('click', () => togglePanel(false));

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const question = input.value.trim();
      if (!question) return;
      input.value = '';
      askBot(question);
    });

    chips.addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-chip]');
      if (!btn) return;
      askBot(btn.dataset.chip);
      togglePanel(true);
    });
  })();
</script>
