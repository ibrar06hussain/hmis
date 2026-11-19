import 'bootstrap';

// Keyboard shortcut: press '/' outside inputs to focus global patient search
document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const tag = (event.target?.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) {
        return;
    }

    const searchInput = document.getElementById('hmis-global-patient-search');
    if (searchInput) {
        event.preventDefault();
        searchInput.focus();
        searchInput.select();
    }
});
