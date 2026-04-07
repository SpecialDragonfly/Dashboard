let pollInterval = null;
const audioPlayers = new Map(); // videoId -> HTMLAudioElement

document.getElementById('history').addEventListener('click', async (e) => {
    const btn = e.target.closest('.play-btn');
    if (!btn) return;

    const videoId = btn.dataset.videoId;

    if (!audioPlayers.has(videoId)) {
        btn.disabled = true;
        btn.textContent = '…';

        const res = await AuthManager.fetch('/ripper/stream/' + encodeURIComponent(videoId));
        if (!res || !res.ok) {
            btn.disabled = false;
            btn.textContent = '▶';
            return;
        }

        const blob = await res.blob();
        const audio = new Audio(URL.createObjectURL(blob));
        audio.addEventListener('ended', () => updatePlayBtn(videoId, false));
        audioPlayers.set(videoId, audio);
        btn.disabled = false;
    }

    const audio = audioPlayers.get(videoId);
    if (audio.paused) {
        audioPlayers.forEach((a, id) => {
            if (id !== videoId && !a.paused) { a.pause(); updatePlayBtn(id, false); }
        });
        audio.play();
        updatePlayBtn(videoId, true);
    } else {
        audio.pause();
        updatePlayBtn(videoId, false);
    }
});

function updatePlayBtn(videoId, playing) {
    const btn = document.querySelector(`.play-btn[data-video-id="${CSS.escape(videoId)}"]`);
    if (btn) btn.textContent = playing ? '⏸' : '▶';
}

document.getElementById('rip-btn').addEventListener('click', async () => {
    const url   = document.getElementById('rip-url').value.trim();
    const btn   = document.getElementById('rip-btn');
    const status = document.getElementById('rip-status');

    if (!url) return;

    btn.disabled = true;
    showStatus('info', 'Resolving video ID…');

    const res = await AuthManager.fetch('/rip', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'url=' + encodeURIComponent(url),
    });

    btn.disabled = false;

    if (!res) return;

    const data = await res.json();

    if (data.status === 'error') {
        showStatus('error', data.message);
        return;
    }

    showStatus('info', data.message);
    document.getElementById('rip-url').value = '';
    startPolling();
});

document.getElementById('rip-url').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('rip-btn').click();
});

function showStatus(type, message) {
    const el = document.getElementById('rip-status');
    el.style.display = '';
    el.className = 'rip-status rip-status--' + type;
    el.textContent = message;
}

function startPolling() {
    if (pollInterval) return;
    loadHistory();
    pollInterval = setInterval(async () => {
        const hasDownloading = await loadHistory();
        if (!hasDownloading) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }, 4000);
}

async function loadHistory() {
    const res = await AuthManager.fetch('/history');
    if (!res || !res.ok) return false;

    const items = await res.json();
    renderHistory(items);
    return items.some(i => !i.ready);
}

function renderHistory(items) {
    const container = document.getElementById('history');
    if (items.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = items.map(item => `
        <div class="history-item ${item.ready ? '' : 'history-item--pending'}">
            <div class="history-thumb">
                ${item.thumbnail
                    ? `<img src="${escHtml(item.thumbnail)}" alt="">`
                    : '<div class="thumb-placeholder"></div>'
                }
            </div>
            <div class="history-info">
                <span class="history-title">${escHtml(item.title ?? 'Downloading…')}</span>
                ${item.ready
                    ? `<div class="history-actions">
                        <button class="play-btn btn btn-sm" data-video-id="${escHtml(item.video_id)}">▶</button>
                        <a href="/ripper/download/${escHtml(item.video_id)}" class="btn btn-sm">Download MP3</a>
                       </div>`
                    : '<span class="badge">In progress</span>'
                }
            </div>
        </div>
    `).join('');

    // Restore play/pause button state after re-render
    audioPlayers.forEach((audio, videoId) => {
        if (!audio.paused) updatePlayBtn(videoId, true);
    });
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Load history on page open; start polling if anything is still downloading.
loadHistory().then(hasDownloading => {
    if (hasDownloading) startPolling();
});
