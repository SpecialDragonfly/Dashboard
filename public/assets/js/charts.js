// State
let activeSymbol = null;     // symbol whose candlestick chart is shown; null = growth chart is the active view
const priceData = new Map(); // symbol -> StockQuote-like object from /dividends/prices
let growthData = null;       // array of {date, value} from /charts/growth
let chart = null;            // Lightweight Charts chart instance (lazy, reused across redraws)
let series = null;           // current series handle on `chart`

let portfolioData = [];      // array of stock objects from /dividends/portfolio
let upcomingData = [];       // array of upcoming-dividend objects from /dividends/upcoming

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const portfolioBody = document.getElementById('charts-portfolio-body');
    const upcomingBody = document.getElementById('charts-upcoming-body');
    const closeBtn = document.getElementById('close-chart');

    if (portfolioBody) portfolioBody.addEventListener('click', handleRowClick);
    if (upcomingBody) upcomingBody.addEventListener('click', handleRowClick);
    if (closeBtn) closeBtn.addEventListener('click', closeChart);

    loadPortfolio();
    loadUpcoming();
    loadGrowth();
});

// ─── Portfolio ────────────────────────────────────────────────────────────────

async function loadPortfolio() {
    const res = await AuthManager.fetch('/dividends/portfolio');
    if (!res || !res.ok) return;

    portfolioData = await res.json();
    renderPortfolioTable();
}

function renderPortfolioTable() {
    const tbody = document.getElementById('charts-portfolio-body');
    if (!tbody) return;

    if (portfolioData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No portfolio stocks yet.</td></tr>';
        return;
    }

    tbody.innerHTML = portfolioData.map(stock => {
        const activeClass = activeSymbol === stock.symbol ? ' active-row' : '';
        return `<tr data-symbol="${escHtml(stock.symbol)}"${activeClass}>
            <td><span class="stock-symbol">${escHtml(stock.symbol)}</span></td>
            <td>${escHtml(stock.name)}</td>
            <td class="num">${stock.quantity}</td>
            <td class="num price-cell">${formatPrice(stock.price)}</td>
            <td>${escHtml(stock.exDiv ?? '—')}</td>
            <td>${escHtml(stock.dividend ?? '—')}</td>
            <td class="num">${stock.totalDividendPayments}</td>
        </tr>`;
    }).join('');
}

// ─── Upcoming dividends ───────────────────────────────────────────────────────

async function loadUpcoming() {
    const res = await AuthManager.fetch('/dividends/upcoming');
    if (!res || !res.ok) return;

    upcomingData = await res.json();
    renderUpcomingTable();
}

function renderUpcomingTable() {
    const tbody = document.getElementById('charts-upcoming-body');
    if (!tbody) return;

    if (upcomingData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No upcoming dividends.</td></tr>';
        return;
    }

    tbody.innerHTML = upcomingData.map(item => {
        const activeClass = activeSymbol === item.symbol ? ' active-row' : '';
        return `<tr data-symbol="${escHtml(item.symbol)}"${activeClass}>
            <td><span class="stock-symbol">${escHtml(item.symbol)}</span></td>
            <td>${escHtml(item.name ?? '—')}</td>
            <td>${escHtml(item.exDivDate ?? '—')}</td>
            <td class="num">${formatDividendAmount(item.amount, item.currency)}</td>
        </tr>`;
    }).join('');
}

// ─── Row click delegation (uniform: portfolio rows and upcoming rows behave identically) ──

function handleRowClick(e) {
    const row = e.target.closest('tr[data-symbol]');
    if (!row) return;

    const symbol = row.dataset.symbol;
    if (activeSymbol === symbol) {
        closeChart();
    } else {
        openChart(symbol);
    }
}

function updateActiveRows(symbol) {
    document
        .querySelectorAll('#charts-portfolio-body tr[data-symbol], #charts-upcoming-body tr[data-symbol]')
        .forEach(r => r.classList.toggle('active-row', r.dataset.symbol === symbol));
}

function clearActiveRows() {
    document
        .querySelectorAll('#charts-portfolio-body tr.active-row, #charts-upcoming-body tr.active-row')
        .forEach(r => r.classList.remove('active-row'));
}

// ─── Chart panel ──────────────────────────────────────────────────────────────

async function openChart(symbol) {
    activeSymbol = symbol;

    const closeBtn = document.getElementById('close-chart');
    if (closeBtn) closeBtn.style.display = '';

    updateActiveRows(symbol);

    if (!priceData.has(symbol)) {
        const titleEl = document.getElementById('chart-title');
        if (titleEl) titleEl.textContent = symbol + ' — loading…';

        const res = await AuthManager.fetch('/dividends/prices?symbols=' + encodeURIComponent(symbol));
        if (res && res.ok) {
            const quotes = await res.json();
            for (const q of quotes) {
                if (!q.error) priceData.set(q.symbol, q);
            }
        }

        // If the user closed the panel or clicked a different row while this
        // fetch was in flight, don't clobber whatever is now active.
        if (activeSymbol !== symbol) return;
    }

    if (priceData.has(symbol)) {
        drawCandles(symbol);
    } else {
        showNoDataState(symbol);
    }
}

function showNoDataState(symbol) {
    const titleEl = document.getElementById('chart-title');
    if (titleEl) titleEl.textContent = symbol + ' — no data available';

    destroyChart();
    const container = document.getElementById('price-chart');
    if (container) container.innerHTML = '<p class="empty-state">No price data available for ' + escHtml(symbol) + '.</p>';
}

function closeChart() {
    activeSymbol = null;

    const closeBtn = document.getElementById('close-chart');
    if (closeBtn) closeBtn.style.display = 'none';

    clearActiveRows();
    drawGrowthChart();
}

// ─── Lightweight Charts lifecycle ─────────────────────────────────────────────

function ensureChart() {
    const container = document.getElementById('price-chart');
    if (!container) return null;

    if (!chart) {
        if (typeof LightweightCharts === 'undefined') {
            console.error('LightweightCharts library not loaded');
            return null;
        }

        container.innerHTML = ''; // clear any empty-state placeholder markup left behind
        chart = LightweightCharts.createChart(container, {
            width: container.clientWidth,
            height: container.clientHeight || 340,
            layout: {
                background: {color: 'transparent'},
                textColor: '#8888aa',
            },
            grid: {
                vertLines: {color: 'rgba(255,255,255,0.05)'},
                horzLines: {color: 'rgba(255,255,255,0.05)'},
            },
            timeScale: {borderColor: 'rgba(255,255,255,0.1)'},
            rightPriceScale: {borderColor: 'rgba(255,255,255,0.1)'},
        });

        window.addEventListener('resize', () => {
            if (chart && container) {
                chart.applyOptions({width: container.clientWidth});
            }
        });
    }

    return container;
}

function destroyChart() {
    if (chart) {
        try {
            chart.remove();
        } catch (e) {
            console.warn('Failed to remove chart instance', e);
        }
    }
    chart = null;
    series = null;
}

function removeCurrentSeries() {
    if (chart && series) {
        try {
            // Standard Lightweight Charts v4 API for dropping a series before
            // adding a replacement. Wrapped defensively in case the exact
            // removal method differs from what's assumed here.
            chart.removeSeries(series);
        } catch (e) {
            console.warn('Failed to remove previous series', e);
        }
    }
    series = null;
}

function drawCandles(symbol) {
    const q = priceData.get(symbol);
    if (!q) return;

    const titleEl = document.getElementById('chart-title');
    if (titleEl) {
        titleEl.textContent = `${symbol} — 6 months weekly  |  ${formatPrice(q.price)}p`;
    }

    const points = q.timestamps
        .map((ts, i) => ({
            time: unixToDateString(ts),
            open: q.ohlc[i][0],
            high: q.ohlc[i][1],
            low: q.ohlc[i][2],
            close: q.ohlc[i][3],
        }))
        // Port dividends.js's illiquid-week filter: Yahoo returns null/0 OHLC
        // for weeks with no trading. Skip them rather than interpolate — the
        // resulting gap is itself a meaningful signal (charts.md edge case #8).
        .filter(d => d.open > 0);

    if (!ensureChart()) return;

    removeCurrentSeries();
    series = chart.addCandlestickSeries({
        upColor: '#6bcb77',
        downColor: '#e05c6a',
        borderVisible: false,
        wickUpColor: '#6bcb77',
        wickDownColor: '#e05c6a',
    });
    series.setData(points);
    chart.timeScale().fitContent();
}

function drawGrowthChart() {
    const titleEl = document.getElementById('chart-title');
    if (titleEl) titleEl.textContent = 'Portfolio Growth';

    if (!growthData || growthData.length === 0) {
        // Never render an empty/broken chart — show a placeholder instead
        // (charts.md edge case #6).
        destroyChart();
        const container = document.getElementById('price-chart');
        if (container) container.innerHTML = '<p class="empty-state">Not enough data yet to show portfolio growth.</p>';
        return;
    }

    if (!ensureChart()) return;

    removeCurrentSeries();
    series = chart.addLineSeries({color: '#4f8cff'});
    series.setData(growthData.map(d => ({time: d.date, value: d.value})));
    chart.timeScale().fitContent();
}

// ─── Growth data ──────────────────────────────────────────────────────────────

async function loadGrowth() {
    const res = await AuthManager.fetch('/charts/growth');
    if (!res || !res.ok) return;

    growthData = await res.json();
    if (activeSymbol === null) {
        drawGrowthChart();
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function unixToDateString(ts) {
    const d = new Date(ts * 1000);
    const yyyy = d.getUTCFullYear();
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(d.getUTCDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function formatPrice(p) {
    return (Math.round(p * 100) / 100).toFixed(2);
}

function formatDividendAmount(amount, currency) {
    if (amount == null) return '—';
    const val = parseFloat(amount).toFixed(4).replace(/\.?0+$/, '');
    return currency ? `${escHtml(currency)} ${val}` : val;
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
