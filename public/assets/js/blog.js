async function deletePost(slug) {
    if (!confirm('Delete this post?')) return;
    const res = await AuthManager.fetch('/blog/' + slug, { method: 'DELETE' });
    if (res && res.ok) {
        window.location.href = '/blog';
    }
}

function mdWrap(before, after) {
    const ta = document.getElementById('content');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    const selected = ta.value.slice(start, end);
    ta.setRangeText(before + selected + after, start, end, 'select');
    ta.focus();
}

function mdLink() {
    const ta = document.getElementById('content');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    const selected = ta.value.slice(start, end) || 'link text';
    const url = prompt('URL:', 'https://');
    if (!url) return;
    ta.setRangeText(`[${selected}](${url})`, start, end, 'end');
    ta.focus();
}
