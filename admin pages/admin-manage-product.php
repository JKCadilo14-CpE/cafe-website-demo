<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$message = '';
$messageType = '';
$schemaMessage = '';
$products = [];
$totalProducts = 0;
$shownProducts = 0;
$categoryCount = 0;
$unpricedProducts = 0;
$productStatusCounts = [
    'available' => 0,
    'out_of_stock' => 0,
    'unavailable' => 0,
];
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$hasStatusColumn = false;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    $statusColumnResult = $mysqli->query("SHOW COLUMNS FROM products LIKE 'status'");
    $hasStatusColumn = (bool) $statusColumnResult->fetch_assoc();
    $statusColumnResult->free();

    if (!$hasStatusColumn) {
        $schemaMessage = "Product status column is missing. Run: ALTER TABLE products ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'available';";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if ($productId !== false && $productId !== null) {
            $statement = $mysqli->prepare('DELETE FROM products WHERE id = ?');
            $statement->bind_param('i', $productId);
            $statement->execute();
            $statement->close();

            header('Location: admin-manage-product.php?deleted=1');
            exit();
        }

        $message = 'Please choose a valid product before deleting.';
        $messageType = 'error';
    }

    if (isset($_GET['deleted'])) {
        $message = 'Product deleted successfully.';
        $messageType = 'success';
    }

    $totalProducts = (int) ($mysqli->query('SELECT COUNT(*) FROM products')->fetch_row()[0] ?? 0);
    $categoryCount = (int) ($mysqli->query("SELECT COUNT(DISTINCT category) FROM products WHERE category IS NOT NULL AND TRIM(category) <> ''")->fetch_row()[0] ?? 0);
    $unpricedProducts = (int) ($mysqli->query('SELECT COUNT(*) FROM products WHERE price IS NULL')->fetch_row()[0] ?? 0);
    $statusSelect = $hasStatusColumn ? ', status' : '';

    if ($hasStatusColumn) {
        $statusCountResult = $mysqli->query('SELECT status, COUNT(*) AS total FROM products GROUP BY status');

        while ($row = $statusCountResult->fetch_assoc()) {
            $statusKey = app_normalize_product_status((string) ($row['status'] ?? 'available'));
            $productStatusCounts[$statusKey] += (int) ($row['total'] ?? 0);
        }

        $statusCountResult->free();
    } else {
        $productStatusCounts['available'] = $totalProducts;
    }

    if ($searchTerm !== '') {
        $countStatement = $mysqli->prepare(
            "SELECT COUNT(*) FROM products
             WHERE name LIKE CONCAT('%', ?, '%')
                OR category LIKE CONCAT('%', ?, '%')"
        );
        $countStatement->bind_param('ss', $searchTerm, $searchTerm);
        $countStatement->execute();
        $shownProducts = (int) ($countStatement->get_result()->fetch_row()[0] ?? 0);
        $countStatement->close();

        $statement = $mysqli->prepare(
            "SELECT id, name, category, price, image{$statusSelect}, created_at
             FROM products
             WHERE name LIKE CONCAT('%', ?, '%')
                OR category LIKE CONCAT('%', ?, '%')
             ORDER BY id ASC"
        );
        $statement->bind_param('ss', $searchTerm, $searchTerm);
        $statement->execute();
        $result = $statement->get_result();
    } else {
        $shownProducts = $totalProducts;
        $result = $mysqli->query("SELECT id, name, category, price, image{$statusSelect}, created_at FROM products ORDER BY id ASC");
    }

    while ($row = $result->fetch_assoc()) {
        if (!isset($row['status'])) {
            $row['status'] = 'available';
        }

        $products[] = $row;
    }

    $result->free();

    if (isset($statement) && $statement instanceof mysqli_stmt) {
        $statement->close();
    }

    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load products right now. Please check the database connection.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Products | JKC Cafe Admin</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-products.css">
    </head>
    <body>
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="products-dashboard" aria-label="Manage Products">
                    <div class="products-panel">
                        <div class="products-panel-header">
                            <div>
                                <p class="products-panel-eyebrow">Menu inventory</p>
                                <h1 class="products-panel-title">
                                    <span class="products-title-icon" aria-hidden="true">
                                        <i class="fa-solid fa-mug-hot"></i>
                                    </span>
                                    Manage Products
                                </h1>
                                <p class="products-panel-subtitle">
                                    Review cafe items, check product photos, and keep menu records ready for customers.
                                </p>
                            </div>

                            <div class="products-header-actions">
                                <?php if ($searchTerm !== ''): ?>
                                    <a href="admin-manage-product.php" class="clear-search-button">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        Clear Search
                                    </a>
                                <?php endif; ?>
                                <a href="admin-add-product.php" class="product-add-button">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    Add Product
                                </a>
                            </div>
                        </div>

                        <div class="products-summary-grid" aria-label="Products summary">
                            <div class="products-summary-chip">
                                <span class="products-summary-icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($totalProducts); ?></strong>
                                    <span>Total products</span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon shown"><i class="fa-solid fa-eye" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($shownProducts); ?></strong>
                                    <span><?php echo $searchTerm !== '' ? 'Search results' : 'Shown now'; ?></span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon category"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($categoryCount); ?></strong>
                                    <span>Categories</span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon warning"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($unpricedProducts); ?></strong>
                                    <span>Without prices</span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon available"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($productStatusCounts['available']); ?></strong>
                                    <span>Available</span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon out-of-stock"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($productStatusCounts['out_of_stock']); ?></strong>
                                    <span>Out of Stock</span>
                                </div>
                            </div>
                            <div class="products-summary-chip">
                                <span class="products-summary-icon unavailable"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?php echo number_format($productStatusCounts['unavailable']); ?></strong>
                                    <span>Unavailable</span>
                                </div>
                            </div>
                        </div>

                        <?php if ($schemaMessage !== ''): ?>
                            <div class="admin-message error">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                <span><?php echo e($schemaMessage); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo e($messageType); ?>">
                                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                                <span><?php echo e($message); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="products-table-wrap">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Image</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="7" class="products-empty-state">
                                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                                <h2>No products found</h2>
                                                <p>
                                                    <?php echo $searchTerm !== '' ? 'Try another search term or clear the current search.' : 'Start by adding a product to your cafe menu.'; ?>
                                                </p>
                                                <div class="products-empty-actions">
                                                    <?php if ($searchTerm !== ''): ?>
                                                        <a href="admin-manage-product.php" class="clear-search-button">
                                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                            Clear Search
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="admin-add-product.php" class="product-add-button">
                                                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                                        Add Product
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($products as $product): ?>
                                        <?php
                                            $productId = (int) $product['id'];
                                            $productName = (string) ($product['name'] ?? '');
                                            $productCategory = (string) ($product['category'] ?? '');
                                            $productImage = (string) ($product['image'] ?? '');
                                            $productImageSrc = app_admin_asset_src($productImage);
                                            $productPrice = $product['price'];
                                            $productStatus = app_normalize_product_status((string) ($product['status'] ?? 'available'));
                                            $productStatusClass = str_replace('_', '-', $productStatus);
                                        ?>
                                        <tr class="product-row">
                                            <td data-label="Product ID">
                                                <span class="product-id-chip">#<?php echo $productId; ?></span>
                                            </td>
                                            <td data-label="Image">
                                                <div class="product-thumb">
                                                    <?php if ($productImageSrc !== ''): ?>
                                                        <img src="<?php echo e($productImageSrc); ?>" alt="<?php echo e($productName); ?>">
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-mug-saucer" aria-hidden="true"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="product-name-cell" data-label="Product Name">
                                                <strong><?php echo e($productName); ?></strong>
                                                <span><?php echo $productImageSrc !== '' ? 'Photo ready' : 'Needs product photo'; ?></span>
                                            </td>
                                            <td data-label="Category">
                                                <span class="product-category-badge"><?php echo e($productCategory !== '' ? $productCategory : 'Uncategorized'); ?></span>
                                            </td>
                                            <td class="product-price-cell" data-label="Price">
                                                <?php if ($productPrice !== null): ?>
                                                    <strong>P<?php echo number_format((float) $productPrice, 2); ?></strong>
                                                <?php else: ?>
                                                    <span class="muted-text">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Status">
                                                <span class="product-status <?php echo e($productStatusClass); ?>"><?php echo e(app_product_status_label($productStatus)); ?></span>
                                            </td>
                                            <td class="product-actions-cell" data-label="Actions">
                                                <div class="product-actions">
                                                    <a class="product-action-button edit" href="admin-edit-product.php?id=<?php echo $productId; ?>" aria-label="Edit <?php echo e($productName); ?>">
                                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                        Edit
                                                    </a>
                                                    <form action="admin-manage-product.php" method="POST" onsubmit="return confirm('Delete this product from the database?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                                        <button type="submit" class="product-action-button delete" aria-label="Delete <?php echo e($productName); ?>">
                                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
