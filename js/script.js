// Success messages fade out after a few seconds.
// Errors stay on screen until the user acts on them.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-success').forEach(function (alert) {
        setTimeout(function () { alert.style.display = 'none'; }, 5000);
    });
});

// Escape text before putting it into HTML (titles, names, etc.)
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// Close any open .modal with Escape or by clicking the dark backdrop
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(function (m) {
            if (m.style.display === 'block' || m.style.display === 'flex') { m.style.display = 'none'; }
        });
    }
});
document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal')) { e.target.style.display = 'none'; }
});

// If the session ran out, background requests answer 401: say so once and go to the login page
(function () {
    const originalFetch = window.fetch;
    let warned = false;
    window.fetch = function () {
        return originalFetch.apply(this, arguments).then(function (response) {
            if (response.status === 401 && !warned) {
                warned = true;
                alert('You were logged out. Log in again to continue.');
                window.location.href = 'login.php?expired=1';
            }
            return response;
        });
    };
})();
