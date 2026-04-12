// State
let portfolioData = [];        // array of stock objects from /dividends/portfolio
const priceData = new Map(); // symbol -> StockQuote (price, ohlc, timestamps, etc.)
let activeSymbol = null;      // symbol whose chart is currently shown
let chart = null;      // Chart.js instance

// ─── Bootstrap ────────────────────────────────────────────────────────────────

loadPortfolio();
loadUpcoming();

// ─── Portfolio ────────────────────────────────────────────────────────────────

async function loadPortfolio() {
    const res = await AuthManager.fetch('/dividends/portfolio');
    if (!res || !res.ok) return;

    portfolioData = await res.json();
    renderPortfolioTable();
    populatePaymentSelect();

    if (portfolioData.length > 0) {
        const symbols = portfolioData.map(s => s.symbol).join(',');
        await loadPrices(symbols);
    }
}

async function loadPrices(symbolsCsv) {
    const res = await AuthManager.fetch('/dividends/prices?symbols=' + encodeURIComponent(symbolsCsv));
    if (!res || !res.ok) return;

    const quotes = await res.json();
    for (const q of quotes) {
        if (!q.error) priceData.set(q.symbol, q);
    }
    renderPortfolioTable();

    // If chart is open, refresh it with latest data
    if (activeSymbol && priceData.has(activeSymbol)) {
        drawChart(activeSymbol);
    }
}

function renderPortfolioTable() {
    const tbody = document.getElementById('portfolio-body');

    if (portfolioData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="empty-state">No stocks yet — add one below.</td></tr>';
        return;
    }

    tbody.innerHTML = portfolioData.map(stock => {
        const q = priceData.get(stock.symbol);
        const priceCell = q ? formatPrice(q.price) : '<span class="loading">…</span>';
        const checkedCell = q ? formatChecked(q.lastChecked) : '—';
        const activeClass = activeSymbol === stock.symbol ? ' active-row' : '';

        return `<tr data-symbol="${escHtml(stock.symbol)}"${activeClass}>
            <td><span class="stock-symbol">${escHtml(stock.symbol)}</span></td>
            <td>${escHtml(stock.name)}</td>
            <td class="num">${stock.quantity}</td>
            <td class="num price-cell">${formatPrice(stock.price)}</td>
            <td class="num price-cell">${priceCell}</td>
            <td><span class="last-checked${q && isStale(q.lastChecked) ? ' last-checked-stale' : ''}">${checkedCell}</span></td>
            <td class="exdiv-cell">
                <input class="editable-field" data-field="exDiv" data-symbol="${escHtml(stock.symbol)}"
                       value="${escHtml(stock.exDiv ?? '')}" placeholder="—">
            </td>
            <td class="dividend-cell">
                <input class="editable-field" data-field="dividend" data-symbol="${escHtml(stock.symbol)}"
                       value="${escHtml(stock.dividend ?? '')}" placeholder="—">
            </td>
            <td class="num received-cell">${stock.totalDividendPayments}</td>
            <td><button class="delete-btn" data-symbol="${escHtml(stock.symbol)}">✕</button></td>
        </tr>`;
    }).join('');
}

function populatePaymentSelect() {
    const sel = document.getElementById('payment-symbol');
    const existing = new Set([...sel.options].map(o => o.value).filter(Boolean));

    for (const stock of portfolioData) {
        if (!existing.has(String(stock.id))) {
            const opt = document.createElement('option');
            opt.value = stock.id;
            opt.textContent = `${stock.symbol} — ${stock.name}`;
            sel.appendChild(opt);
        }
    }
}

// ─── Table interactions ───────────────────────────────────────────────────────

document.getElementById('portfolio-body').addEventListener('click', e => {
    // Delete
    const delBtn = e.target.closest('.delete-btn');
    if (delBtn) {
        e.stopPropagation();
        const symbol = delBtn.dataset.symbol;
        if (confirm(`Remove ${symbol} from portfolio?`)) deleteStock(symbol);
        return;
    }

    // Row click → chart (ignore clicks on editable fields)
    if (e.target.classList.contains('editable-field')) return;
    const row = e.target.closest('tr[data-symbol]');
    if (!row) return;
    const symbol = row.dataset.symbol;
    if (activeSymbol === symbol) {
        closeChart();
    } else {
        openChart(symbol);
    }
});

// Inline editable fields — save on blur or Enter
document.getElementById('portfolio-body').addEventListener('change', async e => {
    const field = e.target.dataset.field;
    if (!field) return;
    await saveInlineField(e.target);
});

document.getElementById('portfolio-body').addEventListener('keydown', async e => {
    if (e.key !== 'Enter') return;
    const field = e.target.dataset.field;
    if (!field) return;
    e.target.blur();
});

async function saveInlineField(input) {
    const symbol = input.dataset.symbol;
    const stock = portfolioData.find(s => s.symbol === symbol);
    if (!stock) return;

    // Collect both fields for this symbol from the live DOM
    const row = document.querySelector(`tr[data-symbol="${CSS.escape(symbol)}"]`);
    const exDiv = row.querySelector('[data-field="exDiv"]').value;
    const dividend = row.querySelector('[data-field="dividend"]').value;

    await AuthManager.fetch('/dividends/portfolio/' + encodeURIComponent(symbol), {
        method: 'PUT',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `symbol=${encodeURIComponent(symbol)}&exDiv=${encodeURIComponent(exDiv)}&dividend=${encodeURIComponent(dividend)}`,
    });
}

async function deleteStock(symbol) {
    const res = await AuthManager.fetch('/dividends/portfolio/' + encodeURIComponent(symbol), {
        method: 'DELETE',
    });
    if (res && (res.ok || res.status === 204)) {
        if (activeSymbol === symbol) closeChart();
        await loadPortfolio();
    }
}

// ─── Add stock form ───────────────────────────────────────────────────────────

document.getElementById('add-stock-form').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    const res = await AuthManager.fetch('/dividends/portfolio', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            symbol: document.getElementById('stock-symbol').value.trim().toUpperCase(),
            name: document.getElementById('stock-name').value.trim(),
            quantity: document.getElementById('stock-quantity').value,
            price: document.getElementById('stock-price').value,
        }).toString(),
    });

    btn.disabled = false;
    if (res && res.ok) {
        e.target.reset();
        await loadPortfolio();
    }
});

// ─── Add payment form ─────────────────────────────────────────────────────────

document.getElementById('add-payment-form').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    const res = await AuthManager.fetch('/dividends/payment', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            symbolId: document.getElementById('payment-symbol').value,
            date: document.getElementById('payment-date').value,
            amount: document.getElementById('payment-amount').value,
        }).toString(),
    });

    btn.disabled = false;
    if (res && (res.ok || res.status === 204)) {
        e.target.reset();
        document.getElementById('payment-symbol').value = '';
        await loadPortfolio();
    }
});

// ─── Chart ────────────────────────────────────────────────────────────────────

function openChart(symbol) {
    activeSymbol = symbol;
    const panel = document.getElementById('chart-panel');
    panel.style.display = '';

    // Scroll chart into view
    panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});

    // Update active row highlight
    document.querySelectorAll('#portfolio-body tr[data-symbol]').forEach(r => {
        r.classList.toggle('active-row', r.dataset.symbol === symbol);
    });

    if (priceData.has(symbol)) {
        drawChart(symbol);
    } else {
        document.getElementById('chart-title').textContent = symbol + ' — loading…';
    }
}

function closeChart() {
    activeSymbol = null;
    document.getElementById('chart-panel').style.display = 'none';
    document.querySelectorAll('#portfolio-body tr.active-row').forEach(r => r.classList.remove('active-row'));
    if (chart) {
        chart.destroy();
        chart = null;
    }
}

document.getElementById('close-chart').addEventListener('click', closeChart);

function drawChart(symbol) {
    const q = priceData.get(symbol);
    if (!q) return;

    document.getElementById('chart-title').textContent =
        `${symbol} — 6 months weekly  |  ${formatPrice(q.price)}p  |  ${formatDateTime(q.priceTime)}`;

    const chartData = q.timestamps
        .map((ts, i) => ({
            x: ts * 1000, // seconds → ms
            o: q.ohlc[i][0],
            h: q.ohlc[i][1],
            l: q.ohlc[i][2],
            c: q.ohlc[i][3],
        }))
        .filter(d => d.o > 0); // skip nulls

    const ctx = document.getElementById('price-chart').getContext('2d');
    if (chart) chart.destroy();

    chart = new Chart(ctx, {
        type: 'candlestick',
        data: {
            datasets: [{
                label: symbol,
                data: chartData,
                color: {
                    up: '#6bcb77',
                    down: '#e05c6a',
                    unchanged: '#8888aa',
                },
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: {display: false},
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const d = ctx.raw;
                            return `O: ${d.o}  H: ${d.h}  L: ${d.l}  C: ${d.c}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    type: 'time',
                    time: {unit: 'week', displayFormats: {week: 'dd MMM'}},
                    ticks: {color: '#8888aa', maxTicksLimit: 12},
                    grid: {color: 'rgba(255,255,255,0.05)'},
                },
                y: {
                    ticks: {color: '#8888aa', callback: v => v + 'p'},
                    grid: {color: 'rgba(255,255,255,0.05)'},
                },
            },
        },
    });
}

// ─── Upcoming dividends ───────────────────────────────────────────────────────

async function loadUpcoming() {
    document.getElementById('upcoming-status').textContent = 'Loading…';
    document.getElementById('upcoming-list').innerHTML = '';

    const res = await AuthManager.fetch('/dividends/upcoming');
    if (!res || !res.ok) {
        document.getElementById('upcoming-status').textContent = '';
        document.getElementById('upcoming-list').innerHTML = '<p class="empty-state">Failed to load.</p>';
        return;
    }

    const items = await res.json();
    document.getElementById('upcoming-status').textContent = items.length + ' upcoming';
    renderUpcomingTable(items);
}

function renderUpcomingTable(items) {
    const list = document.getElementById('upcoming-list');
    const ownedSymbols = new Set(portfolioData.map(s => s.symbol));

    if (items.length === 0) {
        list.innerHTML = '';
        return;
    }

    list.innerHTML = `
        <table class="upcoming-table">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Name</th>
                    <th>Ex-div date</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr>
                        <td>
                            <span class="upcoming-symbol">${escHtml(item.symbol)}</span>
                            ${ownedSymbols.has(item.symbol) ? '<span class="upcoming-owned">owned</span>' : ''}
                        </td>
                        <td>${escHtml(item.name ?? '—')}</td>
                        <td>${escHtml(item.exDivDate ?? '—')}</td>
                        <td>${formatDividendAmount(item.amount, item.currency)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
}

// ─── Cookie upload ────────────────────────────────────────────────────────────

document.getElementById('cookie-upload-form').addEventListener('submit', async e => {
    e.preventDefault();
    const status = document.getElementById('cookie-status');
    const file = document.getElementById('cookie-file').files[0];
    if (!file) return;

    status.textContent = 'Uploading…';
    status.className = 'cookie-status';

    const body = new FormData();
    body.append('cookies', file);

    const res = await AuthManager.fetch('/dividends/cookies', {method: 'POST', body});

    if (res && res.ok) {
        status.textContent = 'Saved — reloading dividend data…';
        status.className = 'cookie-status cookie-status-ok';
        e.target.reset();
        setTimeout(() => loadUpcoming(), 1000);
    } else {
        const data = res ? await res.json().catch(() => ({})) : {};
        status.textContent = data.error ?? 'Upload failed';
        status.className = 'cookie-status cookie-status-err';
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDividendAmount(amount, currency) {
    if (amount == null) return '—';
    const val = parseFloat(amount).toFixed(4).replace(/\.?0+$/, '');
    return currency ? `${escHtml(currency)} ${val}` : val;
}

function formatPrice(p) {
    return (Math.round(p * 100) / 100).toFixed(2);
}

function formatChecked(ts) {
    if (!ts) return '—';
    const diff = Math.floor((Date.now() / 1000) - ts);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return new Date(ts * 1000).toLocaleDateString('en-GB', {day: 'numeric', month: 'short'});
}

function formatDateTime(ts) {
    if (!ts) return '—';
    return new Date(ts * 1000).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
}

function isStale(ts) {
    return (Date.now() / 1000) - ts > 1800; // > 30 minutes
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
