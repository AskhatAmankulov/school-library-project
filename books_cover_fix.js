// INSTRUCTIONS: Replace the JavaScript section in books.php (lines 460-500 approximately)
// with this improved version that fixes the URL/link option

// Cover source toggle and preview  
document.getElementById('cover_source_upload')?.addEventListener('change', () => {
    document.getElementById('coverUploadContainer').style.display = '';
    document.getElementById('coverUrlContainer').style.display = 'none';
    // Clear URL input when switching to upload
    const urlInput = document.getElementById('cover_url');
    if (urlInput) {
        urlInput.value = '';
    }
});

document.getElementById('cover_source_url')?.addEventListener('change', () => {
    document.getElementById('coverUploadContainer').style.display = 'none';
    document.getElementById('coverUrlContainer').style.display = '';
    // Clear file input when switching to URL
    const fileInput = document.getElementById('cover_file');
    if (fileInput) {
        fileInput.value = '';
    }
    // Clear preview if it was from file
    const coverPrev = document.getElementById('coverPreview');
    if (coverPrev) {
        coverPrev.style.display = 'none';
    }
});

const coverFileInput = document.getElementById('cover_file');
const coverUrlInput = document.getElementById('cover_url');
const coverPrev = document.getElementById('coverPreview');
const coverPrevImg = document.getElementById('coverPreviewImg');

if (coverFileInput) {
    coverFileInput.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file && coverPrevImg && coverPrev) {
            const reader = new FileReader();
            reader.onload = () => {
                coverPrevImg.src = reader.result;
                coverPrev.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else if (coverPrev && coverPrevImg) {
            coverPrev.style.display = 'none';
            coverPrevImg.src = '';
        }
    });
}

if (coverUrlInput) {
    coverUrlInput.addEventListener('input', (e) => {
        const url = e.target.value.trim();
        if (url && coverPrevImg && coverPrev) {
            coverPrevImg.src = url;
            coverPrev.style.display = 'block';
        } else if (coverPrev && coverPrevImg) {
            coverPrev.style.display = 'none';
            coverPrevImg.src = '';
        }
    });
}
