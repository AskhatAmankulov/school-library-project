// Success messages fade out after a few seconds.
// Errors stay on screen until the user acts on them.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-success').forEach(function (alert) {
        setTimeout(function () { alert.style.display = 'none'; }, 5000);
    });
});
