<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$message = '';
$messageType = '';
$product = null;
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$categorySuggestions = [];
$newUploadedImage = '';
$productStatusOptions = app_product_status_options();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($productId === false || $productId === null) {
    $message = 'Please choose a valid product to edit.';
    $messageType = 'error';
} else {
    try {
        $mysqli = app_db();

        $statement = $mysqli->prepare(
            'SELECT id, name, category, price, image, status, created_at
             FROM products
             WHERE id = ?
             LIMIT 1'
        );
        $statement->bind_param('i', $productId);
        $statement->execute();
        $result = $statement->get_result();
        $product = $result->fetch_assoc();
        $result->free();
        $statement->close();

        if ($product === null) {
            $message = 'Product not found. It may have been deleted already.';
            $messageType = 'error';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $previousStatus = app_normalize_product_status((string) ($product['status'] ?? 'available'));
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $priceInput = trim((string) ($_POST['price'] ?? ''));
            $statusInput = trim((string) ($_POST['status'] ?? 'available'));
            $image = (string) ($product['image'] ?? '');
            $price = null;
            $product['name'] = $name;
            $product['category'] = $category;
            $product['price'] = $priceInput;
            $product['status'] = $statusInput;

            if ($name === '' || $category === '') {
                $message = 'Product name and category are required.';
                $messageType = 'error';
            } elseif ($priceInput !== '' && !is_numeric($priceInput)) {
                $message = 'Please enter a valid price.';
                $messageType = 'error';
            } elseif (!array_key_exists($statusInput, $productStatusOptions)) {
                $message = 'Please choose a valid product status.';
                $messageType = 'error';
            } else {
                if ($priceInput !== '') {
                    $price = (float) $priceInput;
                }

                $upload = app_store_product_image($_FILES['image_file'] ?? [], $name);

                if ($upload['error'] !== '') {
                    $message = $upload['error'];
                    $messageType = 'error';
                } else {
                    $previousImage = $image;
                    $newUploadedImage = $upload['path'];

                    if ($newUploadedImage !== '') {
                        $image = $newUploadedImage;
                    }

                    $statement = $mysqli->prepare(
                        'UPDATE products
                         SET name = ?, category = ?, price = ?, image = ?, status = ?
                         WHERE id = ?'
                    );
                    $statement->bind_param('ssdssi', $name, $category, $price, $image, $statusInput, $productId);
                    $statement->execute();
                    $statement->close();

                    if ($newUploadedImage !== '' && $previousImage !== $newUploadedImage) {
                        app_delete_product_image($previousImage);
                    }

                    if ($previousStatus !== 'available' && $statusInput === 'available') {
                        app_notify_product_available_reminders($mysqli, $productId, $name);
                    }

                    header('Location: admin-edit-product.php?id=' . $productId . '&updated=1');
                    exit();
                }
            }
        }

        if (isset($_GET['updated'])) {
            $message = 'Product updated successfully.';
            $messageType = 'success';
        }

        if ($product !== null) {
            $product['status'] = app_normalize_product_status((string) ($product['status'] ?? 'available'));
        }

        $categoryResult = $mysqli->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category ASC");

        while ($row = $categoryResult->fetch_assoc()) {
            $categorySuggestions[] = (string) $row['category'];
        }

        $categoryResult->free();
        $mysqli->close();
    } catch (mysqli_sql_exception $exception) {
        app_delete_product_image($newUploadedImage);
        $message = 'Unable to load this product right now. Please check the database connection.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Product | JKC Cafe Admin</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
    </head>
    <body>
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="edit-product-dashboard" aria-label="Edit Product">
                    <div class="edit-product-panel">
                        <div class="edit-product-header">
                            <div>
                                <p class="edit-product-eyebrow">Menu inventory</p>
                                <h1 class="edit-product-title">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                    Edit Menu Product
                                </h1>
                                <?php if ($product !== null): ?>
                                    <p class="edit-product-subtitle">Update product #<?php echo (int) $product['id']; ?> for the JKC Cafe menu and storefront.</p>
                                <?php else: ?>
                                    <p class="edit-product-subtitle">Choose a product from Manage Products to update its record.</p>
                                <?php endif; ?>
                            </div>
                            <a href="admin-manage-product.php" class="back-products-button">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                Manage Products
                            </a>
                        </div>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($product !== null): ?>
                            <?php $productImageSrc = app_admin_asset_src((string) ($product['image'] ?? '')); ?>
                            <?php $previewPrice = (string) ($product['price'] ?? '') !== '' && is_numeric((string) $product['price']) ? 'PHP ' . number_format((float) $product['price'], 2) : 'Price not set'; ?>
                            <div class="edit-product-content">
                                <form class="edit-product-form edit-product-form-card" action="admin-edit-product.php?id=<?php echo (int) $product['id']; ?>" method="POST" enctype="multipart/form-data">
                                    <div class="form-section-heading">
                                        <span class="form-section-icon" aria-hidden="true">
                                            <i class="fa-solid fa-pen-nib"></i>
                                        </span>
                                        <div>
                                            <h2>Product details</h2>
                                            <p>Edit the menu basics, reuse existing categories, or upload a replacement product photo.</p>
                                        </div>
                                    </div>

                                    <div class="product-form-grid">
                                        <div class="form-field product-field">
                                            <label for="name">Product Name</label>
                                            <div class="field-control">
                                                <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                                                <input id="name" type="text" name="name" value="<?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Iced Caramel Latte" required data-add-product-name>
                                            </div>
                                            <small>Shown on the menu card and product lists.</small>
                                        </div>

                                        <div class="form-field product-field">
                                            <label for="category">Category</label>
                                            <div class="field-control">
                                                <i class="fa-solid fa-tags" aria-hidden="true"></i>
                                                <input id="category" type="text" name="category" value="<?php echo htmlspecialchars((string) $product['category'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Coffee" list="product-category-suggestions" required data-add-product-category aria-describedby="category-help category-match-status">
                                            </div>
                                            <datalist id="product-category-suggestions">
                                                <?php foreach ($categorySuggestions as $suggestion): ?>
                                                    <option value="<?php echo htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                                <?php endforeach; ?>
                                            </datalist>
                                            <small id="category-help">Choose an existing category when possible to keep the menu tidy.</small>
                                            <?php if (!empty($categorySuggestions)): ?>
                                                <div class="category-suggestion-chips" aria-label="Category suggestions">
                                                    <?php foreach (array_slice($categorySuggestions, 0, 10) as $suggestion): ?>
                                                        <button type="button" class="category-chip" data-category-chip="<?php echo htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8'); ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="category-match-status" id="category-match-status" data-category-match-status aria-live="polite"></span>
                                        </div>

                                        <div class="form-field product-field">
                                            <label for="price">Price</label>
                                            <div class="field-control">
                                                <i class="fa-solid fa-peso-sign" aria-hidden="true"></i>
                                                <input id="price" type="number" name="price" min="0" step="0.01" value="<?php echo (string) ($product['price'] ?? '') !== '' ? htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Leave blank if not set" data-add-product-price>
                                            </div>
                                            <small>Use numbers only. Decimals are supported.</small>
                                        </div>

                                        <div class="form-field product-field">
                                            <label for="status">Status</label>
                                            <div class="field-control edit-status-field">
                                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                                <select id="status" name="status" required>
                                                    <?php foreach ($productStatusOptions as $statusValue => $statusLabel): ?>
                                                        <option value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo app_normalize_product_status((string) ($product['status'] ?? 'available')) === $statusValue ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <small>Controls whether customers can add this product to their cart.</small>
                                        </div>

                                        <div class="form-field product-field product-field-wide product-upload-field">
                                            <label for="image_file">Product Photo</label>
                                            <label class="product-upload-zone" for="image_file" data-product-upload-zone>
                                                <span class="product-upload-icon" aria-hidden="true">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                                </span>
                                                <span class="product-upload-copy">
                                                    <strong>Drop a replacement photo here</strong>
                                                    <span>or click to choose from your computer</span>
                                                </span>
                                                <span class="product-upload-meta">JPG, PNG, WebP, or GIF. Max 5MB.</span>
                                                <span class="product-selected-file" data-product-file-label data-default-label="Current image kept">Current image kept</span>
                                                <input id="image_file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-add-product-image>
                                            </label>
                                            <small>Optional. Leave blank to keep the current product image.</small>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="update-product-button">
                                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                            Update Product
                                        </button>
                                        <a href="admin-manage-product.php" class="cancel-product-button">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>

                                <aside class="edit-product-preview add-product-preview" aria-label="Live product preview">
                                    <div class="preview-kicker">
                                        <span>Live preview</span>
                                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                    </div>
                                    <div class="edit-product-image add-product-image">
                                        <img src="<?php echo htmlspecialchars($productImageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?> preview" data-add-product-preview-image <?php echo $productImageSrc === '' ? 'hidden' : ''; ?>>
                                        <i class="fa-solid fa-mug-saucer" aria-hidden="true" data-add-product-preview-icon <?php echo $productImageSrc !== '' ? 'hidden' : ''; ?>></i>
                                    </div>
                                    <div class="preview-copy">
                                        <span class="preview-category" data-add-product-preview-category><?php echo htmlspecialchars((string) $product['category'] !== '' ? (string) $product['category'] : 'Category', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <h2 data-add-product-preview-name><?php echo htmlspecialchars((string) $product['name'] !== '' ? (string) $product['name'] : 'Menu Item', ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <p data-add-product-preview-price><?php echo htmlspecialchars($previewPrice, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="preview-note">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                        <span>Saved edits update Manage Products and the public menu immediately.</span>
                                    </div>
                                </aside>
                            </div>
                        <?php else: ?>
                            <div class="edit-product-empty">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <p>No editable product record is available.</p>
                                <a href="admin-manage-product.php" class="back-products-button">
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                    Manage Products
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
        <script src="js/admin-product-forms.js"></script>
    </body>
</html>
