<?php
require_once __DIR__ . '/db.php';
$db    = get_db();
$res   = $db->query('SELECT id, name FROM rooms ORDER BY id');
$rooms = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $rooms[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP Chat</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600&family=Barlow:wght@300;400;500&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0d0f12;
    --bg2:       #13161b;
    --bg3:       #1a1e25;
    --border:    #252930;
    --accent:    #00e5a0;
    --accent2:   #0099ff;
    --danger:    #ff4757;
    --text:      #e2e8f0;
    --text-dim:  #6b7280;
    --text-muted:#3d434d;
    --radius:    6px;
    --mono:      'Share Tech Mono', monospace;
    --sans:      'Barlow', sans-serif;
  }

  html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); }

  /* ── Login ── */
  #login-screen {
    display: flex; align-items: center; justify-content: center;
    height: 100vh;
    background-image:
      repeating-linear-gradient(0deg,   transparent, transparent 39px, var(--border) 39px, var(--border) 40px),
      repeating-linear-gradient(90deg,  transparent, transparent 39px, var(--border) 39px, var(--border) 40px);
    animation: fadeIn .4s ease;
  }
  .login-card {
    background: var(--bg2); border: 1px solid var(--border);
    border-radius: 10px; padding: 48px 40px; width: 380px;
    box-shadow: 0 0 60px rgba(0,229,160,.07);
  }
  .login-card .logo { font-family: var(--mono); font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
  .login-card h1   { font-size: 26px; font-weight: 500; margin-bottom: 32px; }
  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px; font-family: var(--mono); }
  .field input, .field select {
    width: 100%; background: var(--bg3); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px 14px; color: var(--text);
    font-family: var(--mono); font-size: 14px; outline: none; transition: border-color .15s;
  }
  .field input:focus, .field select:focus { border-color: var(--accent); }
  .field select option { background: var(--bg3); }
  .btn-join {
    width: 100%; margin-top: 8px; padding: 12px; background: var(--accent);
    color: #000; border: none; border-radius: var(--radius); font-family: var(--mono);
    font-size: 13px; font-weight: 600; letter-spacing: .08em; cursor: pointer; transition: opacity .15s;
  }
  .btn-join:hover { opacity: .88; }
  .error-msg { color: var(--danger); font-size: 12px; font-family: var(--mono); margin-top: 10px; min-height: 16px; }

  /* ── App layout ── */
  #app { display: none; height: 100vh; flex-direction: row; }
  #app.visible { display: flex; animation: fadeIn .3s ease; }

  #sidebar {
    width: 220px; flex-shrink: 0; background: var(--bg2);
    border-right: 1px solid var(--border); display: flex; flex-direction: column;
  }
  .sidebar-header { padding: 18px 16px 12px; border-bottom: 1px solid var(--border); }
  .sidebar-header .brand { font-family: var(--mono); font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: var(--accent); }
  .sidebar-header .me { font-size: 13px; color: var(--text-dim); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .sidebar-header .me span { color: var(--text); font-weight: 500; }

  /* WS status indicator */
  .ws-status { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-family: var(--mono); font-size: 10px; color: var(--text-muted); }
  .ws-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted); transition: background .3s; }
  .ws-dot.connected    { background: var(--accent); box-shadow: 0 0 6px var(--accent); }
  .ws-dot.connecting   { background: #ffd93d; }
  .ws-dot.disconnected { background: var(--danger); }

  .sidebar-section { padding: 14px 16px 6px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); font-family: var(--mono); }
  .room-list { flex: 1; overflow-y: auto; padding-bottom: 8px; }
  .room-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 16px;
    cursor: pointer; font-size: 14px; color: var(--text-dim);
    border-left: 2px solid transparent; transition: background .12s, color .12s, border-color .12s;
  }
  .room-item:hover  { background: var(--bg3); color: var(--text); }
  .room-item.active { background: var(--bg3); color: var(--accent); border-left-color: var(--accent); }
  .room-item .hash  { font-family: var(--mono); opacity: .5; }

  .online-section  { border-top: 1px solid var(--border); padding: 12px 0 8px; }
  .online-header   { padding: 0 16px 6px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); font-family: var(--mono); }
  .user-list       { max-height: 160px; overflow-y: auto; }
  .user-item       { display: flex; align-items: center; gap: 8px; padding: 5px 16px; font-size: 13px; color: var(--text-dim); }
  .user-dot        { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 6px var(--accent); }

  /* ── Main ── */
  #main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
  #chat-header {
    height: 52px; border-bottom: 1px solid var(--border); display: flex;
    align-items: center; padding: 0 24px; gap: 10px; background: var(--bg2); flex-shrink: 0;
  }
  #chat-header .room-name { font-family: var(--mono); font-size: 15px; font-weight: 500; }
  #chat-header .hash      { color: var(--accent); margin-right: 2px; }
  #chat-header .online-count { margin-left: auto; font-size: 11px; font-family: var(--mono); color: var(--text-dim); }
  #chat-header .dot { color: var(--accent); }

  #messages {
    flex: 1; overflow-y: auto; padding: 16px 24px;
    display: flex; flex-direction: column; gap: 2px; scroll-behavior: smooth;
  }
  .msg { display: flex; gap: 12px; padding: 6px 8px; border-radius: var(--radius); transition: background .1s; animation: msgIn .18s ease; }
  .msg:hover { background: var(--bg3); }
  .msg-avatar { width: 32px; height: 32px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: var(--mono); font-size: 12px; font-weight: 600; color: #000; margin-top: 2px; }
  .msg-right  { flex: 1; min-width: 0; }
  .msg-meta   { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
  .msg-user   { font-size: 13px; font-weight: 500; font-family: var(--mono); }
  .msg-time   { font-size: 11px; color: var(--text-muted); font-family: var(--mono); }
  .msg-body   { font-size: 14px; line-height: 1.55; color: var(--text); word-break: break-word; }
  .msg-system { justify-content: center; padding: 4px; }
  .msg-system .sys-text { font-size: 11px; font-family: var(--mono); color: var(--text-muted); background: var(--bg3); padding: 3px 10px; border-radius: 20px; }

  #input-bar { padding: 14px 24px; border-top: 1px solid var(--border); background: var(--bg2); flex-shrink: 0; }
  #input-wrap {
    display: flex; gap: 10px; background: var(--bg3);
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 6px 8px 6px 14px; transition: border-color .15s;
  }
  #input-wrap:focus-within { border-color: var(--accent); }
  #msg-input {
    flex: 1; background: none; border: none; outline: none;
    color: var(--text); font-family: var(--sans); font-size: 14px;
    resize: none; max-height: 120px; line-height: 1.5;
  }
  #msg-input::placeholder { color: var(--text-muted); }
  #msg-input:disabled { opacity: .4; cursor: not-allowed; }
  #send-btn {
    align-self: flex-end; background: var(--accent); border: none; border-radius: 4px;
    width: 34px; height: 34px; cursor: pointer; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; transition: opacity .15s; color: #000;
  }
  #send-btn:hover    { opacity: .85; }
  #send-btn:disabled { opacity: .3; cursor: not-allowed; }

  ::-webkit-scrollbar       { width: 4px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
  @keyframes msgIn  { from { opacity: 0; transform: translateX(-4px); } to { opacity: 1; transform: none; } }
</style>
</head>
<body>

<div id="login-screen">
  <div class="login-card">
    <div class="logo">PHP Chat</div>
    <h1>Join a room</h1>
    <div class="field">
      <label>Your username</label>
      <input type="text" id="username-input" placeholder="e.g. alice" maxlength="30" autocomplete="off">
    </div>
    <div class="field">
      <label>Room</label>
      <select id="room-select"><option value="">Loading rooms…</option></select>
    </div>
    <button class="btn-join" id="join-btn">Join →</button>
    <div class="error-msg" id="login-error"></div>
  </div>
</div>

<div id="app">
  <aside id="sidebar">
    <div class="sidebar-header">
      <div class="brand">PHP Chat</div>
      <div class="me">Chatting as <span id="me-label">—</span></div>
      <div class="ws-status">
        <div class="ws-dot" id="ws-dot"></div>
        <span id="ws-label">disconnected</span>
      </div>
    </div>
    <div class="sidebar-section">Rooms</div>
    <div class="room-list" id="room-list"></div>
    <div class="online-section">
      <div class="online-header">Online — <span id="online-count">0</span></div>
      <div class="user-list" id="user-list"></div>
    </div>
  </aside>

  <main id="main">
    <div id="chat-header">
      <span class="room-name"><span class="hash">#</span><span id="room-label">—</span></span>
      <span class="online-count"><span class="dot">●</span> <span id="hdr-online">0</span> online</span>
    </div>
    <div id="messages"></div>
    <div id="input-bar">
      <div id="input-wrap">
        <textarea id="msg-input" rows="1" placeholder="Message…" disabled></textarea>
        <button id="send-btn" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
    </div>
  </main>
</div>

<script>
const WS_URL = 'ws://localhost:8081';


const ROOMS = <?= json_encode($rooms) ?>;

const state = {
  username: '',
  rooms:    [],
  roomId:   null,
  roomName: '',
  ws:       null,
};

const PALETTE = ['#00e5a0','#0099ff','#ff6b6b','#ffd93d','#c77dff','#ff9f43','#54a0ff','#48dbfb'];
const userColour = n => PALETTE[n.split('').reduce((a,c) => a + c.charCodeAt(0), 0) % PALETTE.length];

// ── Boot ──────────────────────────────────────────────────────
(() => {
  state.rooms = ROOMS;
  const sel = document.getElementById('room-select');
  sel.innerHTML = ROOMS.map(r => `<option value="${r.id}">${r.name}</option>`).join('');

  const saved = sessionStorage.getItem('chat_user');
  if (saved) {
    const { username, roomId } = JSON.parse(saved);
    document.getElementById('username-input').value = username;
    sel.value = roomId;
  }
})();

// ── Login ─────────────────────────────────────────────────────
document.getElementById('join-btn').addEventListener('click', joinChat);
document.getElementById('username-input').addEventListener('keydown', e => { if (e.key === 'Enter') joinChat(); });

function joinChat() {
  const username = document.getElementById('username-input').value.trim();
  const roomId   = parseInt(document.getElementById('room-select').value);
  const errEl    = document.getElementById('login-error');

  if (!username) { errEl.textContent = 'Please enter a username.'; return; }
  if (!roomId)   { errEl.textContent = 'Please select a room.';    return; }

  errEl.textContent = '';
  state.username = username;
  state.roomId   = roomId;
  state.roomName = state.rooms.find(r => r.id === roomId)?.name ?? '';

  sessionStorage.setItem('chat_user', JSON.stringify({ username, roomId }));

  document.getElementById('login-screen').style.display = 'none';
  document.getElementById('app').classList.add('visible');
  document.getElementById('me-label').textContent = username;
  renderRoomList();
  connectWS();
}

// ── WebSocket ─────────────────────────────────────────────────
function connectWS() {
  setWsStatus('connecting');

  const ws = new WebSocket(WS_URL);
  state.ws  = ws;

  ws.onopen = () => {
    setWsStatus('connected');
    enableInput(true);
    joinRoom(state.roomId);
  };

  ws.onmessage = ({ data }) => {
    let msg;
    try { msg = JSON.parse(data); } catch { return; }
    handlePacket(msg);
  };

  ws.onclose = () => {
    setWsStatus('disconnected');
    enableInput(false);
    addSystemMsg('Disconnected — reconnecting in 3s…');
    setTimeout(connectWS, 3000);
  };

  ws.onerror = () => ws.close();
}

function send(obj) {
  if (state.ws?.readyState === WebSocket.OPEN) {
    state.ws.send(JSON.stringify(obj));
  }
}

function joinRoom(roomId) {
  send({ type: 'join', username: state.username, room_id: roomId });
}

// ── Incoming packets ──────────────────────────────────────────
function handlePacket(msg) {
  switch (msg.type) {
    case 'history':
      document.getElementById('messages').innerHTML = '';
      msg.messages.forEach(renderMessage);
      scrollBottom();
      updateOnline(msg.online ?? []);
      break;

    case 'message':
      renderMessage(msg);
      scrollBottom();
      break;

    case 'system':
      addSystemMsg(msg.body);
      updateOnline(msg.online ?? []);
      break;

    case 'error':
      addSystemMsg('⚠ ' + msg.body);
      break;
  }
}

// ── Room switching ────────────────────────────────────────────
function renderRoomList() {
  const el = document.getElementById('room-list');
  el.innerHTML = state.rooms.map(r => `
    <div class="room-item ${r.id === state.roomId ? 'active' : ''}" data-id="${r.id}" data-name="${r.name}">
      <span class="hash">#</span>${r.name}
    </div>`).join('');

  el.querySelectorAll('.room-item').forEach(item => {
    item.addEventListener('click', () => {
      const id   = +item.dataset.id;
      const name = item.dataset.name;
      if (id === state.roomId) return;

      state.roomId   = id;
      state.roomName = name;

      document.querySelectorAll('.room-item').forEach(el => el.classList.toggle('active', +el.dataset.id === id));
      document.getElementById('room-label').textContent  = name;
      document.getElementById('msg-input').placeholder   = `Message #${name.toLowerCase()}…`;
      document.getElementById('messages').innerHTML = '';

      joinRoom(id);
    });
  });

  document.getElementById('room-label').textContent  = state.roomName;
  document.getElementById('msg-input').placeholder   = `Message #${state.roomName.toLowerCase()}…`;
}

// ── Render messages ───────────────────────────────────────────
function renderMessage(msg) {
  const el  = document.createElement('div');
  el.className = 'msg';
  const col = userColour(msg.username);
  const ini = msg.username.slice(0, 2).toUpperCase();
  const ts  = new Date(msg.created_at * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  el.innerHTML = `
    <div class="msg-avatar" style="background:${col}">${ini}</div>
    <div class="msg-right">
      <div class="msg-meta">
        <span class="msg-user" style="color:${col}">${msg.username}</span>
        <span class="msg-time">${ts}</span>
      </div>
      <div class="msg-body">${msg.body}</div>
    </div>`;
  document.getElementById('messages').appendChild(el);
}

function addSystemMsg(text) {
  const el = document.createElement('div');
  el.className = 'msg msg-system';
  el.innerHTML = `<span class="sys-text">${text}</span>`;
  document.getElementById('messages').appendChild(el);
}

function scrollBottom() {
  const el = document.getElementById('messages');
  el.scrollTop = el.scrollHeight;
}

// ── Send ──────────────────────────────────────────────────────
document.getElementById('send-btn').addEventListener('click', sendMsg);
document.getElementById('msg-input').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});
document.getElementById('msg-input').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

function sendMsg() {
  const input = document.getElementById('msg-input');
  const body  = input.value.trim();
  if (!body) return;
  input.value = '';
  input.style.height = 'auto';
  send({ type: 'message', body });
}

// ── Online users ──────────────────────────────────────────────
function updateOnline(users) {
  document.getElementById('online-count').textContent = users.length;
  document.getElementById('hdr-online').textContent   = users.length;
  document.getElementById('user-list').innerHTML = users.map(u => `
    <div class="user-item">
      <span class="user-dot" style="background:${userColour(u)};box-shadow:0 0 6px ${userColour(u)}"></span>
      ${u}
    </div>`).join('');
}

// ── UI helpers ────────────────────────────────────────────────
function enableInput(on) {
  document.getElementById('msg-input').disabled  = !on;
  document.getElementById('send-btn').disabled   = !on;
}

function setWsStatus(status) {
  const dot   = document.getElementById('ws-dot');
  const label = document.getElementById('ws-label');
  dot.className = `ws-dot ${status}`;
  label.textContent = status;
}
</script>
</body>
</html>
