document.addEventListener('DOMContentLoaded', () => {
    loadApod();
});

async function loadApod() {
    const el = document.getElementById('apodContent');
    if (!el) return;
    try {
        const res = await fetch('https://api.nasa.gov/planetary/apod?api_key=DEMO_KEY');
        const data = await res.json();
        el.innerHTML = `
            <img src="${data.url}" alt="${data.title}" loading="lazy">
            <p class="apod-title">${data.title}</p>
            <p class="apod-explanation">${data.explanation}</p>
        `;
    } catch {
        el.innerHTML = '<p class="loading">Could not load APOD.</p>';
    }
}
