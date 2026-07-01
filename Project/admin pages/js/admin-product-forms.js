const setupAddProductPreview = () => {
    const nameInput = document.querySelector('[data-add-product-name]');
    const categoryInput = document.querySelector('[data-add-product-category]');
    const priceInput = document.querySelector('[data-add-product-price]');
    const imageInput = document.querySelector('[data-add-product-image]');
    const uploadZone = document.querySelector('[data-product-upload-zone]');
    const fileLabel = document.querySelector('[data-product-file-label]');
    const categoryStatus = document.querySelector('[data-category-match-status]');
    const categoryChips = document.querySelectorAll('[data-category-chip]');
    const previewName = document.querySelector('[data-add-product-preview-name]');
    const previewCategory = document.querySelector('[data-add-product-preview-category]');
    const previewPrice = document.querySelector('[data-add-product-preview-price]');
    const previewImage = document.querySelector('[data-add-product-preview-image]');
    const previewIcon = document.querySelector('[data-add-product-preview-icon]');

    if (!nameInput || !categoryInput || !priceInput || !imageInput || !previewName || !previewCategory || !previewPrice || !previewImage || !previewIcon) {
        return;
    }

    const existingCategories = Array.from(document.querySelectorAll('#product-category-suggestions option'))
        .map((option) => normalizeText(option.value))
        .filter(Boolean);
    const defaultFileLabel = fileLabel?.getAttribute('data-default-label') || 'No image selected';
    const initialPreviewSrc = previewImage.getAttribute('src') || '';
    const hasInitialPreview = initialPreviewSrc !== '' && !previewImage.hidden;
    let previewObjectUrl = '';

    const showImageFallback = () => {
        if (previewObjectUrl !== '') {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = '';
        }

        previewImage.hidden = true;
        previewImage.removeAttribute('src');
        previewIcon.hidden = false;
    };

    const showInitialImage = () => {
        if (previewObjectUrl !== '') {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = '';
        }

        previewImage.hidden = false;
        previewIcon.hidden = true;
        previewImage.alt = `${nameInput.value.trim() || 'Product'} preview`;
        previewImage.src = initialPreviewSrc;
    };

    const updateImagePreview = () => {
        const file = imageInput.files && imageInput.files.length > 0 ? imageInput.files[0] : null;

        if (fileLabel) {
            fileLabel.textContent = file ? file.name : defaultFileLabel;
            fileLabel.classList.toggle('has-file', Boolean(file));
        }

        if (!file) {
            if (hasInitialPreview) {
                showInitialImage();
            } else {
                showImageFallback();
            }
            return;
        }

        if (!file.type.startsWith('image/')) {
            showImageFallback();
            return;
        }

        if (previewObjectUrl !== '') {
            URL.revokeObjectURL(previewObjectUrl);
        }

        previewObjectUrl = URL.createObjectURL(file);
        previewImage.hidden = false;
        previewIcon.hidden = true;
        previewImage.alt = `${nameInput.value.trim() || 'Product'} preview`;
        previewImage.src = previewObjectUrl;
    };

    const updateCategoryStatus = () => {
        if (!categoryStatus) {
            return;
        }

        const category = normalizeText(categoryInput.value);

        categoryStatus.classList.remove('is-match', 'is-new');

        if (category === '') {
            categoryStatus.textContent = '';
            return;
        }

        if (existingCategories.includes(category)) {
            categoryStatus.textContent = 'Matches an existing category.';
            categoryStatus.classList.add('is-match');
            return;
        }

        categoryStatus.textContent = 'This will create a new category name.';
        categoryStatus.classList.add('is-new');
    };

    const updatePreview = () => {
        const name = nameInput.value.trim();
        const category = categoryInput.value.trim();
        const priceValue = priceInput.value.trim();
        const parsedPrice = Number(priceValue);

        previewName.textContent = name || 'New Menu Item';
        previewCategory.textContent = category || 'Category';

        if (priceValue === '') {
            previewPrice.textContent = 'Price not set';
        } else if (Number.isFinite(parsedPrice) && parsedPrice >= 0) {
            previewPrice.textContent = `PHP ${parsedPrice.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        } else {
            previewPrice.textContent = 'Check price';
        }

        updateImagePreview();
        updateCategoryStatus();
    };

    previewImage.addEventListener('error', showImageFallback);
    [nameInput, categoryInput, priceInput, imageInput].forEach((input) => {
        input.addEventListener(input === imageInput ? 'change' : 'input', updatePreview);
    });

    categoryChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            categoryInput.value = chip.getAttribute('data-category-chip') || chip.textContent.trim();
            categoryInput.focus();
            categoryInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    if (uploadZone) {
        ['dragenter', 'dragover'].forEach((eventName) => {
            uploadZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                uploadZone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            uploadZone.addEventListener(eventName, () => {
                uploadZone.classList.remove('is-dragging');
            });
        });

        uploadZone.addEventListener('drop', (event) => {
            event.preventDefault();

            if (!event.dataTransfer || event.dataTransfer.files.length === 0) {
                return;
            }

            imageInput.files = event.dataTransfer.files;
            imageInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    window.addEventListener('beforeunload', () => {
        if (previewObjectUrl !== '') {
            URL.revokeObjectURL(previewObjectUrl);
        }
    });

    updatePreview();
};

document.addEventListener('DOMContentLoaded', () => {
    setupAddProductPreview();
});
