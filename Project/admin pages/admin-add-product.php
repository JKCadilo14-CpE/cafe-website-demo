<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$message = '';
$messageType = '';
$name = '';
$category = '';
$priceInput = '';
$image = '';
$categorySuggestions = [];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $priceInput = trim((string) ($_POST['price'] ?? ''));
        $price = null;

        if ($name === '' || $category === '') {
            $message = 'Product name and category are required.';
            $messageType = 'error';
        } elseif ($priceInput !== '' && (!is_numeric($priceInput) || (float) $priceInput < 0)) {
            $message = 'Please enter a valid price.';
            $messageType = 'error';
        } else {
            $upload = app_store_product_image($_FILES['image_file'] ?? [], $name);

            if ($upload['error'] !== '') {
                $message = $upload['error'];
                $messageType = 'error';
            } else {
                $image = $upload['path'];

                if ($priceInput !== '') {
                    $price = (float) $priceInput;
                }

                $status = 'available';
                $statement = $mysqli->prepare(
                    'INSERT INTO products (name, category, price, image, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $statement->bind_param('ssdss', $name, $category, $price, $image, $status);
                $statement->execute();
                $newProductId = $statement->insert_id;
                $statement->close();

                header('Location: admin-add-product.php?saved=1&id=' . $newProductId);
                exit();
            }
        }
    }

    if (isset($_GET['saved'])) {
        $message = 'Product saved successfully.';
        $messageType = 'success';
    }

    $categoryResult = $mysqli->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category ASC");

    while ($row = $categoryResult->fetch_assoc()) {
        $categorySuggestions[] = (string) $row['category'];
    }

    $categoryResult->free();

    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    app_delete_product_image($image);
    $message = 'Unable to save products right now. Please check the database connection.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Add Product | JKC Cafe Admin</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
    </head>
    <body>
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="add-product-dashboard" aria-label="Add product">
                    <div class="add-product-panel">
                        <div class="add-product-header">
                            <div>
                                <p class="add-product-eyebrow">Menu inventory</p>
                                <h1 class="add-product-title">
                                    <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                                    Add Menu Product
                                </h1>
                                <p class="add-product-subtitle">Create a polished product record for the JKC Cafe menu and storefront.</p>
                            </div>
                            <a href="admin-manage-product.php" class="back-products-button">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                Manage Products
                            </a>
                        </div>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                                <span>
                                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($messageType === 'success' && isset($_GET['id'])): ?>
                                        <a class="message-link" href="admin-edit-product.php?id=<?php echo (int) $_GET['id']; ?>">Edit this product</a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="add-product-content">
                            <form class="add-product-form add-product-form-card" action="admin-add-product.php" method="POST" enctype="multipart/form-data">
                                <div class="form-section-heading">
                                    <span class="form-section-icon" aria-hidden="true">
                                        <i class="fa-solid fa-pen-nib"></i>
                                    </span>
                                    <div>
                                        <h2>Product details</h2>
                                        <p>Add the product basics, reuse existing categories, and upload a menu-ready photo.</p>
                                    </div>
                                </div>

                                <div class="product-form-grid">
                                    <div class="form-field product-field">
                                        <label for="name">Product Name</label>
                                        <div class="field-control">
                                            <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                                            <input id="name" type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Iced Caramel Latte" required data-add-product-name>
                                        </div>
                                        <small>Shown on the menu card and product lists.</small>
                                    </div>

                                    <div class="form-field product-field">
                                        <label for="category">Category</label>
                                        <div class="field-control">
                                            <i class="fa-solid fa-tags" aria-hidden="true"></i>
                                            <input id="category" type="text" name="category" value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Coffee" list="product-category-suggestions" required data-add-product-category aria-describedby="category-help category-match-status">
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
                                            <input id="price" type="number" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars($priceInput, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Leave blank if not set" data-add-product-price>
                                        </div>
                                        <small>Use numbers only. Decimals are supported.</small>
                                    </div>

                                    <div class="form-field product-field product-field-wide product-upload-field">
                                        <label for="image_file">Product Photo</label>
                                        <label class="product-upload-zone" for="image_file" data-product-upload-zone>
                                            <span class="product-upload-icon" aria-hidden="true">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                            </span>
                                            <span class="product-upload-copy">
                                                <strong>Drop a product photo here</strong>
                                                <span>or click to choose from your computer</span>
                                            </span>
                                            <span class="product-upload-meta">JPG, PNG, WebP, or GIF. Max 5MB.</span>
                                            <span class="product-selected-file" data-product-file-label data-default-label="No image selected">No image selected</span>
                                            <input id="image_file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-add-product-image>
                                        </label>
                                        <small>Optional. If left blank, the product keeps the cafe placeholder until edited.</small>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="save-product-button">
                                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                        Save Product
                                    </button>
                                    <a href="admin-manage-product.php" class="cancel-product-button">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>

                            <aside class="add-product-preview" aria-label="Live product preview">
                                <div class="preview-kicker">
                                    <span>Live preview</span>
                                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                </div>
                                <div class="add-product-image">
                                    <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="" data-add-product-preview-image <?php echo $image === '' ? 'hidden' : ''; ?>>
                                    <i class="fa-solid fa-mug-saucer" aria-hidden="true" data-add-product-preview-icon <?php echo $image !== '' ? 'hidden' : ''; ?>></i>
                                </div>
                                <div class="preview-copy">
                                    <span class="preview-category" data-add-product-preview-category><?php echo htmlspecialchars($category !== '' ? $category : 'Category', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <h2 data-add-product-preview-name><?php echo htmlspecialchars($name !== '' ? $name : 'New Menu Item', ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <p data-add-product-preview-price>
                                        <?php echo $priceInput !== '' && is_numeric($priceInput) ? 'PHP ' . htmlspecialchars(number_format((float) $priceInput, 2), ENT_QUOTES, 'UTF-8') : 'Price not set'; ?>
                                    </p>
                                </div>
                                <div class="preview-note">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    <span>Saved products appear in Manage Products immediately.</span>
                                </div>
                            </aside>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
        <script src="js/admin-product-forms.js"></script>
    </body>
</html>
