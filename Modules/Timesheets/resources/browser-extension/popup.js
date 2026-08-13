/**
 * WideHalo Timer — Popup script
 */

const timerDisplay = document.getElementById('timerDisplay');
const startBtn     = document.getElementById('startBtn');
const stopBtn      = document.getElementById('stopBtn');
const descInput    = document.getElementById('descInput');
const statusMsg    = document.getElementById('statusMsg');
const runningDot   = document.getElementById('runningDot');

// Settings
const apiKeyInput   = document.getElementById('apiKeyInput');
const apiBaseInput  = document.getElementById('apiBaseInput');
const projectIdInput= document.getElementById('projectIdInput');
const saveBtn       = document.getElementById('saveBtn');

let tickInterval = null;
let startedAtMs  = null;

// ── Tick (elapsed display) ────────────────────────────────────────────────────

function formatElapsed(ms) {
  const totalSec = Math.floor(ms / 1000);
  const h = Math.floor(totalSec / 3600);
  const m = Math.floor((totalSec % 3600) / 60);
  const s = totalSec % 60;
  return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
}

function startTick(startedAt) {
  startedAtMs = new Date(startedAt).getTime();
  clearInterval(tickInterval);
  tickInterval = setInterval(() => {
    timerDisplay.textContent = formatElapsed(Date.now() - startedAtMs);
  }, 1000);
}

function stopTick() {
  clearInterval(tickInterval);
  tickInterval = null;
  startedAtMs  = null;
  timerDisplay.textContent = '00:00:00';
}

// ── UI state ──────────────────────────────────────────────────────────────────

function setRunning(state) {
  if (state) {
    startBtn.disabled     = true;
    stopBtn.disabled      = false;
    runningDot.classList.add('running');
    descInput.disabled    = true;
    descInput.value       = state.description ?? '';
    startTick(state.started_at);
  } else {
    startBtn.disabled     = false;
    stopBtn.disabled      = true;
    runningDot.classList.remove('running');
    descInput.disabled    = false;
    stopTick();
  }
}

function showStatus(msg, isError = false) {
  statusMsg.textContent = msg;
  statusMsg.className   = 'status-msg' + (isError ? ' error' : '');
  setTimeout(() => { statusMsg.textContent = ''; }, 4000);
}

// ── Load saved settings ───────────────────────────────────────────────────────

chrome.storage.local.get(['apiKey', 'apiBase', 'projectId'], (r) => {
  if (r.apiKey)   apiKeyInput.value    = r.apiKey;
  if (r.apiBase)  apiBaseInput.value   = r.apiBase;
  if (r.projectId) projectIdInput.value = r.projectId;
});

// ── Get current timer state ───────────────────────────────────────────────────

chrome.runtime.sendMessage({ type: 'GET_STATE' }, (res) => {
  if (res?.ok && res.state) {
    setRunning(res.state);
  }
});

// ── Button handlers ───────────────────────────────────────────────────────────

startBtn.addEventListener('click', () => {
  startBtn.disabled = true;
  statusMsg.textContent = 'Démarrage…';
  chrome.runtime.sendMessage(
    { type: 'START_TIMER', description: descInput.value.trim() },
    (res) => {
      if (res?.ok) {
        setRunning(res.data);
        showStatus('Timer démarré !');
      } else {
        startBtn.disabled = false;
        showStatus(res?.error ?? 'Erreur.', true);
      }
    }
  );
});

stopBtn.addEventListener('click', () => {
  stopBtn.disabled = true;
  statusMsg.textContent = 'Arrêt en cours…';
  chrome.runtime.sendMessage({ type: 'STOP_TIMER' }, (res) => {
    if (res?.ok) {
      setRunning(null);
      const min = res.data?.duration_minutes ?? 0;
      showStatus(`Arrêté — ${min} min enregistrée${min !== 1 ? 's' : ''}.`);
    } else {
      stopBtn.disabled = false;
      showStatus(res?.error ?? 'Erreur.', true);
    }
  });
});

saveBtn.addEventListener('click', () => {
  chrome.storage.local.set({
    apiKey:    apiKeyInput.value.trim(),
    apiBase:   apiBaseInput.value.trim(),
    projectId: projectIdInput.value.trim(),
  }, () => {
    showStatus('Paramètres sauvegardés.');
  });
});
