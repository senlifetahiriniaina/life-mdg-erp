/**
 * WideHalo Timer — Service Worker (MV3)
 *
 * Responsibilities:
 *  - Maintain timer state via chrome.storage.local
 *  - POST to WideHalo API: /api/v1/timesheets/timer/start|stop
 *  - Fire chrome.alarms to auto-stop after configurable max duration
 *  - Show desktop notifications on start/stop
 */

const ALARM_AUTO_STOP = 'widehalo_timer_autostop';
const MAX_DURATION_HOURS = 8;

// ── Helper: read settings ─────────────────────────────────────────────────────

async function getSettings() {
  return new Promise((resolve) => {
    chrome.storage.local.get(['apiKey', 'apiBase', 'projectId', 'taskId'], resolve);
  });
}

async function getTimerState() {
  return new Promise((resolve) => {
    chrome.storage.local.get(['timerState'], (r) => resolve(r.timerState ?? null));
  });
}

async function setTimerState(state) {
  return new Promise((resolve) => {
    chrome.storage.local.set({ timerState: state }, resolve);
  });
}

// ── API calls ─────────────────────────────────────────────────────────────────

async function apiPost(path, body, apiKey, apiBase) {
  const base = apiBase?.replace(/\/$/, '') ?? 'https://app.widehalo.com';
  const res  = await fetch(`${base}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type':  'application/json',
      'Authorization': `Bearer ${apiKey}`,
      'Accept':        'application/json',
    },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`API error ${res.status}: ${text}`);
  }

  return res.json();
}

// ── Start timer ───────────────────────────────────────────────────────────────

async function startTimer(description = '') {
  const { apiKey, apiBase, projectId, taskId } = await getSettings();

  if (!apiKey) {
    throw new Error('API key not configured. Open the extension settings.');
  }

  const payload = {};
  if (projectId) payload.project_id = parseInt(projectId);
  if (taskId)    payload.task_id    = parseInt(taskId);
  if (description) payload.description = description;

  const data = await apiPost('/api/v1/timesheets/timer/start', payload, apiKey, apiBase);

  await setTimerState({
    timer_id:   data.timer_id,
    started_at: data.started_at,
    project_id: data.project_id ?? null,
    description,
  });

  // Set auto-stop alarm
  chrome.alarms.create(ALARM_AUTO_STOP, {
    delayInMinutes: MAX_DURATION_HOURS * 60,
  });

  chrome.notifications.create({
    type:    'basic',
    iconUrl: 'icons/icon48.png',
    title:   'WideHalo Timer',
    message: `Timer démarré${description ? ': ' + description : '.'}`,
  });

  return data;
}

// ── Stop timer ────────────────────────────────────────────────────────────────

async function stopTimer() {
  const { apiKey, apiBase } = await getSettings();

  if (!apiKey) {
    throw new Error('API key not configured.');
  }

  const data = await apiPost('/api/v1/timesheets/timer/stop', {}, apiKey, apiBase);

  await setTimerState(null);
  chrome.alarms.clear(ALARM_AUTO_STOP);

  const minutes = data.duration_minutes ?? 0;
  chrome.notifications.create({
    type:    'basic',
    iconUrl: 'icons/icon48.png',
    title:   'WideHalo Timer',
    message: `Timer arrêté — ${minutes} minute${minutes !== 1 ? 's' : ''} enregistrée${minutes !== 1 ? 's' : ''}.`,
  });

  return data;
}

// ── Message handler (from popup) ──────────────────────────────────────────────

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg.type === 'START_TIMER') {
    startTimer(msg.description ?? '')
      .then((d) => sendResponse({ ok: true, data: d }))
      .catch((e) => sendResponse({ ok: false, error: e.message }));
    return true; // async
  }

  if (msg.type === 'STOP_TIMER') {
    stopTimer()
      .then((d) => sendResponse({ ok: true, data: d }))
      .catch((e) => sendResponse({ ok: false, error: e.message }));
    return true;
  }

  if (msg.type === 'GET_STATE') {
    getTimerState().then((s) => sendResponse({ ok: true, state: s }));
    return true;
  }
});

// ── Auto-stop on alarm ────────────────────────────────────────────────────────

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === ALARM_AUTO_STOP) {
    const state = await getTimerState();
    if (state) {
      await stopTimer();
    }
  }
});
