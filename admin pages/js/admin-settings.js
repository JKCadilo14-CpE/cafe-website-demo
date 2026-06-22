const setupAdminFileLabels = () => {
    const fileLabels = document.querySelectorAll('[data-admin-file-label]');

    fileLabels.forEach((fileLabel) => {
        const fileDrop = fileLabel.closest('.settings-file-drop');
        const fileInput = fileDrop?.querySelector('input[type="file"]');
        const defaultLabel = fileLabel.getAttribute('data-default-label') || fileLabel.textContent || 'No file selected';

        if (!fileInput) {
            return;
        }

        fileInput.addEventListener('change', () => {
            const fileName = fileInput.files && fileInput.files.length > 0
                ? fileInput.files[0].name
                : defaultLabel;

            fileLabel.textContent = fileName;
            fileLabel.classList.toggle('has-file', fileName !== defaultLabel);
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupAdminFileLabels();
});
