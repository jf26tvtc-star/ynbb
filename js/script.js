function filterPlaces() {
    const search = document.getElementById('search')?.value.trim() || '';
    const category = document.getElementById('category')?.value || '';
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (category) params.set('category', category);
    window.location.href = 'places.php' + (params.toString() ? '?' + params.toString() : '');
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('search');
    if (search) search.addEventListener('keydown', e => { if (e.key === 'Enter') filterPlaces(); });
});
