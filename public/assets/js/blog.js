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

function mdImagePick() {
    document.getElementById('image-upload').click();
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('image-upload')?.addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        const res = await AuthManager.fetch('/blog/upload', { method: 'POST', body: formData });
        this.value = '';

        if (!res || !res.ok) {
            alert('Image upload failed');
            return;
        }

        const { url } = await res.json();
        const ta = document.getElementById('content');
        const alt = file.name.replace(/\.[^.]+$/, '');
        const md = `![${alt}](${url})`;
        const pos = ta.selectionStart;
        ta.setRangeText(md, pos, pos, 'end');
        ta.focus();
    });
});

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
