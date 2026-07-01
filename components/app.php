<?php
declare(strict_types=1);

function app_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    if ($https === 'on' || $https === '1') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    if ($forwardedProto === 'https') {
        return true;
    }

    $forwardedScheme = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '')));

    if ($forwardedScheme === 'https') {
        return true;
    }

    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));

    return $forwardedSsl === 'on' || $forwardedSsl === '1';
}

function app_session_cookie_params(): array
{
    $params = session_get_cookie_params();

    return [
        'lifetime' => 0,
        'path' => (string) ($params['path'] ?? '/'),
        'domain' => (string) ($params['domain'] ?? ''),
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function app_session_cookie_delete_options(): array
{
    $params = app_session_cookie_params();
    $options = [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ];

    if ($params['domain'] !== '') {
        $options['domain'] = $params['domain'];
    }

    return $options;
}

function app_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    if (app_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params(app_session_cookie_params());
    session_start();
}

app_send_security_headers();

if (session_status() !== PHP_SESSION_ACTIVE) {
    app_start_session();
}

const APP_DELIVERY_FEE = 60.00;
const APP_PROFILE_IMAGE_DIR = 'uploads/profile-pictures';
const APP_PROFILE_IMAGE_MAX_SIZE = 2097152;
const APP_PRODUCT_IMAGE_DIR = 'uploads/product-images';
const APP_PRODUCT_IMAGE_MAX_SIZE = 5242880;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_password_min_length(): int
{
    return 8;
}

function app_validate_password(string $password): array
{
    $errors = [];

    if (strlen($password) < app_password_min_length()) {
        $errors[] = 'Password must be at least ' . app_password_min_length() . ' characters.';
    }

    return $errors;
}

function app_password_policy_text(): string
{
    return 'Use at least ' . app_password_min_length() . ' characters.';
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function app_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(app_csrf_token()) . '">';
}

function app_csrf_is_valid(?string $token): bool
{
    return is_string($token) && hash_equals(app_csrf_token(), $token);
}

function app_request_wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function app_reject_csrf(): void
{
    http_response_code(403);
    $message = 'Security check failed. Please refresh the page and try again.';

    if (app_request_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
        exit();
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit();
}

function app_require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? null;

    if (!app_csrf_is_valid(is_string($token) ? $token : null)) {
        app_reject_csrf();
    }
}

function app_destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', app_session_cookie_delete_options());
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function app_config_error(string $message): void
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo $message;
    exit();
}

function app_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';

    if (!is_file($configPath)) {
        app_config_error('Database config is missing. Copy config.example.php to config.local.php and update your local database settings.');
    }

    $loadedConfig = require $configPath;

    if (!is_array($loadedConfig)) {
        app_config_error('Database config is invalid. config.local.php must return a PHP array.');
    }

    foreach (['db_host', 'db_user', 'db_password', 'db_name'] as $key) {
        if (!array_key_exists($key, $loadedConfig) || !is_string($loadedConfig[$key])) {
            app_config_error('Database config is invalid. Check config.local.php against config.example.php.');
        }
    }

    $config = [
        'db_host' => trim($loadedConfig['db_host']),
        'db_user' => trim($loadedConfig['db_user']),
        'db_password' => $loadedConfig['db_password'],
        'db_name' => trim($loadedConfig['db_name']),
    ];

    if ($config['db_host'] === '' || $config['db_user'] === '' || $config['db_name'] === '') {
        app_config_error('Database config is incomplete. Set db_host, db_user, and db_name in config.local.php.');
    }

    return $config;
}

function app_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $config = app_config();

    $mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

function app_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));
    return trim($slug, '-');
}

function app_admin_asset_src(?string $path): string
{
    $normalized = str_replace('\\', '/', trim((string) $path));

    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^(?:https?:|data:|\/|\.\.\/)/i', $normalized)) {
        return $normalized;
    }

    return '../' . ltrim($normalized, './');
}

function app_product_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Product image is too large. Please choose an image under 5MB.',
        UPLOAD_ERR_PARTIAL => 'The product image upload was interrupted. Please try again.',
        default => 'Unable to upload that product image. Please choose another file.',
    };
}

function app_product_image_upload_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, APP_PRODUCT_IMAGE_DIR);
}

function app_product_image_is_local(string $path): bool
{
    $normalized = str_replace('\\', '/', trim($path));

    return $normalized !== ''
        && str_starts_with($normalized, APP_PRODUCT_IMAGE_DIR . '/')
        && !str_contains($normalized, '..');
}

function app_delete_product_image(string $path): void
{
    $normalized = str_replace('\\', '/', trim($path));

    if (!app_product_image_is_local($normalized)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function app_store_product_image(array $file, string $productName): array
{
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return ['path' => '', 'error' => ''];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => app_product_upload_error_message($uploadError)];
    }

    if ((int) ($file['size'] ?? 0) > APP_PRODUCT_IMAGE_MAX_SIZE) {
        return ['path' => '', 'error' => 'Product image is too large. Please choose an image under 5MB.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['path' => '', 'error' => 'Unable to read that product image. Please choose another image.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';

    if ($finfo) {
        finfo_close($finfo);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['path' => '', 'error' => 'Please upload a JPG, PNG, WebP, or GIF image.'];
    }

    $uploadDir = app_product_image_upload_dir();

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['path' => '', 'error' => 'Unable to prepare the product image folder.'];
    }

    $slug = app_slug($productName);
    $filenameBase = $slug !== '' ? $slug : 'product';
    $filename = $filenameBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mimeType];
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return ['path' => '', 'error' => 'Unable to save that product image. Please try again.'];
    }

    return ['path' => APP_PRODUCT_IMAGE_DIR . '/' . $filename, 'error' => ''];
}

function app_is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function app_user_role(): int
{
    return (int) ($_SESSION['role'] ?? -1);
}

function app_is_admin(): bool
{
    return app_is_logged_in() && app_user_role() === 1;
}

function app_dashboard_for_current_user(): string
{
    return app_is_admin() ? 'admin pages/admin-home.php' : 'profile.php';
}

function app_require_admin(string $loginPath = '../login.php', string $userPath = '../profile.php'): void
{
    if (!app_is_logged_in()) {
        header('Location: ' . $loginPath);
        exit();
    }

    if (!app_is_admin()) {
        header('Location: ' . $userPath);
        exit();
    }
}

function app_ensure_profile_image_column(mysqli $mysqli): void
{
    static $hasColumn = false;

    if ($hasColumn) {
        return;
    }

    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    $column = $result->fetch_assoc();
    $result->free();

    if ($column === null) {
        $mysqli->query('ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL DEFAULT NULL');
    }

    $hasColumn = true;
}

function app_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];

    if ($parts === []) {
        return 'A';
    }

    $initials = strtoupper(substr($parts[0], 0, 1));

    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    }

    return substr($initials !== '' ? $initials : 'A', 0, 2);
}

function app_profile_image_upload_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, APP_PROFILE_IMAGE_DIR);
}

function app_profile_image_is_local(string $path): bool
{
    $normalized = str_replace('\\', '/', trim($path));

    return $normalized !== ''
        && str_starts_with($normalized, APP_PROFILE_IMAGE_DIR . '/')
        && !str_contains($normalized, '..');
}

function app_profile_image_src(?string $path): string
{
    $normalized = str_replace('\\', '/', trim((string) $path));

    if (!app_profile_image_is_local($normalized)) {
        return '';
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

    return is_file($absolutePath) ? $normalized : '';
}

function app_delete_profile_image(?string $path): void
{
    $normalized = str_replace('\\', '/', trim((string) $path));

    if (!app_profile_image_is_local($normalized)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function app_menu_products(): array
{
    return [
        [
            'name' => 'Signature Latte',
            'category' => 'Featured Drinks',
            'price' => 145.00,
            'image' => 'images/signature-latte.png',
            'alt' => 'Signature latte with smooth foam in a white ceramic cup.',
            'description' => 'Balanced espresso, steamed milk, and soft foam for an everyday favorite.',
            'search' => 'balanced espresso steamed milk soft foam house favorite',
        ],
        [
            'name' => 'Caramel Cloud Coffee',
            'category' => 'Featured Drinks',
            'price' => 165.00,
            'image' => 'images/caramel-cloud-coffee.png',
            'alt' => 'Creamy caramel coffee with soft foam in a clear glass.',
            'description' => 'Caramel coffee with a creamy finish and a light cloud of foam.',
            'search' => 'caramel creamy coffee foam sweet chilled favorite',
        ],
        [
            'name' => 'Mocha Cream Latte',
            'category' => 'Featured Drinks',
            'price' => 170.00,
            'image' => 'images/mocha-cream-latte.png',
            'alt' => 'Mocha cream latte with chocolate drizzle and foam.',
            'description' => 'Espresso, milk, and chocolate notes with a smooth creamy top.',
            'search' => 'mocha chocolate latte espresso milk cream',
        ],
        [
            'name' => 'Butter Croissant',
            'category' => 'Featured Food',
            'price' => 95.00,
            'image' => 'images/croissant-card.png',
            'alt' => 'Golden butter croissant on a white plate.',
            'description' => 'Light, flaky, and baked to pair with any hot drink.',
            'search' => 'butter croissant flaky pastry baked fresh',
        ],
        [
            'name' => 'Blueberry Muffin',
            'category' => 'Featured Food',
            'price' => 105.00,
            'image' => 'images/blueberry-muffin.png',
            'alt' => 'Golden blueberry muffin on a small white plate.',
            'description' => 'Soft muffin with blueberries and a lightly golden crumb top.',
            'search' => 'blueberry muffin pastry sweet crumb breakfast snack',
        ],
        [
            'name' => 'Ham & Cheese Toast',
            'category' => 'Featured Food',
            'price' => 155.00,
            'image' => 'images/ham-cheese-toast.png',
            'alt' => 'Toasted ham and cheese sandwich cut in half on a cafe plate.',
            'description' => 'Toasted bread with melted cheese and ham for a simple savory bite.',
            'search' => 'ham cheese toast sandwich savory cafe food',
        ],
        [
            'name' => 'Americano',
            'category' => 'Hot Coffee',
            'price' => 120.00,
            'image' => 'images/americano.png',
            'alt' => 'Hot americano in a white ceramic cup.',
            'description' => 'Clean espresso with hot water for a simple, bold cup.',
            'search' => 'hot americano black coffee espresso water',
        ],
        [
            'name' => 'Cappuccino',
            'category' => 'Hot Coffee',
            'price' => 140.00,
            'image' => 'images/cappuccino.png',
            'alt' => 'Hot cappuccino with thick foam and cocoa dusting.',
            'description' => 'Espresso with steamed milk and a thicker foam finish.',
            'search' => 'hot cappuccino espresso foam cocoa coffee',
        ],
        [
            'name' => 'Vanilla Latte',
            'category' => 'Hot Coffee',
            'price' => 155.00,
            'image' => 'images/vanilla-latte.png',
            'alt' => 'Vanilla latte with smooth foam and latte art.',
            'description' => 'Warm espresso and milk with a gentle vanilla sweetness.',
            'search' => 'vanilla latte hot espresso milk sweet',
        ],
        [
            'name' => 'Iced Americano',
            'category' => 'Iced Coffee',
            'price' => 130.00,
            'image' => 'images/iced-americano.png',
            'alt' => 'Iced americano in a clear glass with ice.',
            'description' => 'Espresso over ice with water for a crisp, refreshing coffee.',
            'search' => 'iced americano black coffee espresso cold ice',
        ],
        [
            'name' => 'Iced Caramel Coffee',
            'category' => 'Iced Coffee',
            'price' => 165.00,
            'image' => 'images/iced-coffee-card.png',
            'alt' => 'Iced caramel coffee in a clear glass with creamy coffee layers.',
            'description' => 'Chilled coffee with caramel notes and a clean, creamy taste.',
            'search' => 'iced caramel coffee cold creamy sweet',
        ],
        [
            'name' => 'Cold Brew',
            'category' => 'Iced Coffee',
            'price' => 150.00,
            'image' => 'images/cold-brew.png',
            'alt' => 'Cold brew coffee over ice in a clear glass.',
            'description' => 'Slow-steeped coffee over ice with a smooth, clean finish.',
            'search' => 'cold brew iced coffee smooth dark refreshing',
        ],
    ];
}

function app_seed_menu_products(): array
{
    static $productIds = null;

    if ($productIds !== null) {
        return $productIds;
    }

    $productIds = [];

    try {
        $mysqli = app_db();
        $select = $mysqli->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $insert = $mysqli->prepare('INSERT INTO products (name, category, price, image) VALUES (?, ?, ?, ?)');
        $update = $mysqli->prepare('UPDATE products SET category = ?, price = ?, image = ? WHERE id = ?');

        foreach (app_menu_products() as $product) {
            $name = $product['name'];
            $category = $product['category'];
            $price = (float) $product['price'];
            $image = $product['image'];
            $id = null;

            $select->bind_param('s', $name);
            $select->execute();
            $result = $select->get_result();
            $row = $result->fetch_assoc();
            $result->free();

            if ($row) {
                $id = (int) $row['id'];
                $update->bind_param('sdsi', $category, $price, $image, $id);
                $update->execute();
            } else {
                $insert->bind_param('ssds', $name, $category, $price, $image);
                $insert->execute();
                $id = (int) $mysqli->insert_id;
            }

            $productIds[$name] = $id;
        }

        $select->close();
        $insert->close();
        $update->close();
        $mysqli->close();
    } catch (mysqli_sql_exception $exception) {
        $productIds = [];
    }

    return $productIds;
}

function app_product_status_options(): array
{
    return [
        'available' => 'Available',
        'unavailable' => 'Unavailable',
        'out_of_stock' => 'Out of stock',
    ];
}

function app_normalize_product_status(?string $status): string
{
    $normalized = strtolower(trim((string) $status));

    return array_key_exists($normalized, app_product_status_options()) ? $normalized : 'available';
}

function app_product_status_label(?string $status): string
{
    $normalized = app_normalize_product_status($status);
    $options = app_product_status_options();

    return $options[$normalized];
}

function app_public_menu_products(): array
{
    try {
        $mysqli = app_db();
        $result = $mysqli->query('SELECT id, name, category, price, image, status FROM products ORDER BY id ASC');
        $products = [];

        while ($row = $result->fetch_assoc()) {
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $image = trim((string) ($row['image'] ?? ''));
            $price = $row['price'];
            $status = app_normalize_product_status((string) ($row['status'] ?? 'available'));
            $isOrderable = $status === 'available' && is_numeric($price) && (float) $price >= 0;

            if ($name === '') {
                $name = 'Menu Item';
            }

            if ($category === '') {
                $category = 'Uncategorized';
            }

            if ($image === '') {
                $image = 'images/latte-card.png';
            }

            $description = $name . ' from the ' . $category . ' menu.';

            $products[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'category' => $category,
                'price' => $isOrderable ? (float) $price : null,
                'image' => $image,
                'alt' => $name . ' from JKC Cafe menu.',
                'description' => $description,
                'search' => trim($name . ' ' . $category . ' ' . $description),
                'status' => $status,
                'status_label' => app_product_status_label($status),
                'is_orderable' => $isOrderable,
            ];
        }

        $result->free();
        $mysqli->close();

        return $products;
    } catch (mysqli_sql_exception $exception) {
        return [];
    }
}

function app_cart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function app_cart_count(): int
{
    $count = 0;

    foreach (app_cart() as $quantity) {
        $count += max(0, (int) $quantity);
    }

    return $count;
}

function app_add_to_cart(int $productId, int $quantity = 1): bool
{
    if ($productId < 1) {
        return false;
    }

    try {
        $mysqli = app_db();
        $statement = $mysqli->prepare('SELECT price, status FROM products WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $productId);
        $statement->execute();
        $result = $statement->get_result();
        $product = $result->fetch_assoc();
        $result->free();
        $statement->close();
        $mysqli->close();

        if (
            !$product
            || app_normalize_product_status((string) ($product['status'] ?? 'available')) !== 'available'
            || !is_numeric($product['price'])
            || (float) $product['price'] < 0
        ) {
            return false;
        }
    } catch (mysqli_sql_exception $exception) {
        return false;
    }

    $quantity = max(1, min(20, $quantity));
    app_cart();
    $key = (string) $productId;
    $_SESSION['cart'][$key] = min(99, (int) ($_SESSION['cart'][$key] ?? 0) + $quantity);

    return true;
}

function app_update_cart_item(int $productId, int $quantity): void
{
    app_cart();
    $key = (string) $productId;

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$key]);
        return;
    }

    $_SESSION['cart'][$key] = min(99, $quantity);
}

function app_remove_cart_item(int $productId): void
{
    app_cart();
    unset($_SESSION['cart'][(string) $productId]);
}

function app_clear_cart(): void
{
    $_SESSION['cart'] = [];
}

function app_cart_items(): array
{
    $cart = app_cart();

    if ($cart === []) {
        return [];
    }

    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn (int $id): bool => $id > 0));

    if ($ids === []) {
        return [];
    }

    try {
        $mysqli = app_db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $statement = $mysqli->prepare("SELECT id, name, category, price, image, status FROM products WHERE id IN ($placeholders)");
        $statement->bind_param($types, ...$ids);
        $statement->execute();
        $result = $statement->get_result();
        $products = [];
        $foundIds = [];

        while ($row = $result->fetch_assoc()) {
            $id = (int) $row['id'];
            $foundIds[] = $id;

            if (
                app_normalize_product_status((string) ($row['status'] ?? 'available')) !== 'available'
                || !is_numeric($row['price'])
                || (float) $row['price'] < 0
            ) {
                unset($_SESSION['cart'][(string) $id]);
                continue;
            }

            $quantity = max(1, (int) ($cart[(string) $id] ?? 1));
            $price = (float) $row['price'];
            $products[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'category' => (string) $row['category'],
                'price' => $price,
                'image' => (string) $row['image'],
                'quantity' => $quantity,
                'line_total' => $price * $quantity,
            ];
        }

        foreach ($ids as $id) {
            if (!in_array($id, $foundIds, true)) {
                unset($_SESSION['cart'][(string) $id]);
            }
        }

        $result->free();
        $statement->close();
        $mysqli->close();

        usort($products, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $products;
    } catch (mysqli_sql_exception $exception) {
        return [];
    }
}

function app_cart_subtotal(array $items): float
{
    $subtotal = 0.00;

    foreach ($items as $item) {
        $subtotal += (float) $item['line_total'];
    }

    return $subtotal;
}

function app_format_money(float $amount): string
{
    return 'P' . number_format($amount, 2);
}

function app_order_status_options(): array
{
    return ['Pending', 'Preparing', 'Out for Delivery', 'Completed', 'Cancelled'];
}

function app_normalize_order_status(?string $status): string
{
    $normalized = strtolower(trim((string) $status));
    $lookup = [];

    foreach (app_order_status_options() as $option) {
        $lookup[strtolower($option)] = $option;
    }

    return $lookup[$normalized] ?? 'Pending';
}

function app_order_status_class(?string $status): string
{
    return strtolower(str_replace(' ', '-', app_normalize_order_status($status)));
}

function app_order_terminal_statuses(): array
{
    return ['Completed', 'Cancelled'];
}

function app_order_cancellable_statuses(): array
{
    return ['Pending', 'Preparing'];
}

function app_order_can_cancel(?string $status): bool
{
    return in_array(app_normalize_order_status($status), app_order_cancellable_statuses(), true);
}

function app_order_progress_steps(): array
{
    return [
        [
            'status' => 'Pending',
            'label' => 'Order placed',
            'description' => 'Your order reached the cafe queue.',
        ],
        [
            'status' => 'Preparing',
            'label' => 'Preparing',
            'description' => 'The team is preparing your items.',
        ],
        [
            'status' => 'Out for Delivery',
            'label' => 'Out for delivery',
            'description' => 'Your order is on its way.',
        ],
        [
            'status' => 'Completed',
            'label' => 'Completed',
            'description' => 'Your order is complete.',
        ],
    ];
}

function app_order_progress_steps_for_status(?string $status): array
{
    $currentStatus = app_normalize_order_status($status);
    $currentIndex = 0;
    $steps = app_order_progress_steps();

    foreach ($steps as $index => $step) {
        if ($step['status'] === $currentStatus) {
            $currentIndex = $index;
            break;
        }
    }

    foreach ($steps as $index => $step) {
        $state = 'upcoming';

        if ($currentStatus === 'Cancelled') {
            $state = $index === 0 ? 'complete' : 'upcoming';
        } elseif ($index < $currentIndex) {
            $state = 'complete';
        } elseif ($index === $currentIndex) {
            $state = 'current';
        }

        $steps[$index]['class'] = app_order_status_class($step['status']);
        $steps[$index]['state'] = $state;
        $steps[$index]['is_complete'] = $state === 'complete';
        $steps[$index]['is_current'] = $state === 'current';
    }

    return $steps;
}

function app_ensure_product_reminder_tables(mysqli $mysqli): void
{
    static $hasTables = false;

    if ($hasTables) {
        return;
    }

    $mysqli->query(
        'CREATE TABLE IF NOT EXISTS product_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "pending",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notified_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY unique_user_product_reminder (user_id, product_id),
            INDEX idx_product_reminders_product_status (product_id, status),
            INDEX idx_product_reminders_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $mysqli->query(
        'CREATE TABLE IF NOT EXISTS user_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT "product_available",
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "unread",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_user_notifications_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $hasTables = true;
}

function app_save_product_reminder(int $userId, int $productId): string
{
    if ($userId < 1) {
        return 'login_required';
    }

    if ($productId < 1) {
        return 'invalid';
    }

    try {
        $mysqli = app_db();
        app_ensure_product_reminder_tables($mysqli);

        $productStatement = $mysqli->prepare('SELECT name, status FROM products WHERE id = ? LIMIT 1');
        $productStatement->bind_param('i', $productId);
        $productStatement->execute();
        $productResult = $productStatement->get_result();
        $product = $productResult->fetch_assoc();
        $productResult->free();
        $productStatement->close();

        if ($product === null) {
            $mysqli->close();
            return 'invalid';
        }

        $status = app_normalize_product_status((string) ($product['status'] ?? 'available'));

        if ($status === 'available') {
            $mysqli->close();
            return 'available';
        }

        $existingStatement = $mysqli->prepare(
            'SELECT id, status FROM product_reminders WHERE user_id = ? AND product_id = ? LIMIT 1'
        );
        $existingStatement->bind_param('ii', $userId, $productId);
        $existingStatement->execute();
        $existingResult = $existingStatement->get_result();
        $existingReminder = $existingResult->fetch_assoc();
        $existingResult->free();
        $existingStatement->close();

        if ($existingReminder !== null) {
            if ((string) ($existingReminder['status'] ?? '') === 'pending') {
                $mysqli->close();
                return 'exists';
            }

            $updateStatement = $mysqli->prepare(
                'UPDATE product_reminders
                 SET status = "pending", created_at = NOW(), notified_at = NULL
                 WHERE id = ?'
            );
            $reminderId = (int) $existingReminder['id'];
            $updateStatement->bind_param('i', $reminderId);
            $updateStatement->execute();
            $updateStatement->close();
            $mysqli->close();

            return 'created';
        }

        $insertStatement = $mysqli->prepare(
            'INSERT INTO product_reminders (user_id, product_id, status) VALUES (?, ?, "pending")'
        );
        $insertStatement->bind_param('ii', $userId, $productId);
        $insertStatement->execute();
        $insertStatement->close();
        $mysqli->close();

        return 'created';
    } catch (mysqli_sql_exception $exception) {
        return 'error';
    }
}

function app_user_pending_product_reminder_ids(int $userId): array
{
    if ($userId < 1) {
        return [];
    }

    try {
        $mysqli = app_db();
        app_ensure_product_reminder_tables($mysqli);

        $statement = $mysqli->prepare(
            'SELECT product_id FROM product_reminders WHERE user_id = ? AND status = "pending"'
        );
        $statement->bind_param('i', $userId);
        $statement->execute();
        $result = $statement->get_result();
        $productIds = [];

        while ($row = $result->fetch_assoc()) {
            $productIds[(int) $row['product_id']] = true;
        }

        $result->free();
        $statement->close();
        $mysqli->close();

        return $productIds;
    } catch (mysqli_sql_exception $exception) {
        return [];
    }
}

function app_notify_product_available_reminders(mysqli $mysqli, int $productId, string $productName): int
{
    if ($productId < 1) {
        return 0;
    }

    try {
        app_ensure_product_reminder_tables($mysqli);

        $reminderStatement = $mysqli->prepare(
            'SELECT id, user_id FROM product_reminders WHERE product_id = ? AND status = "pending"'
        );
        $reminderStatement->bind_param('i', $productId);
        $reminderStatement->execute();
        $reminderResult = $reminderStatement->get_result();
        $reminders = [];

        while ($row = $reminderResult->fetch_assoc()) {
            $reminders[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
            ];
        }

        $reminderResult->free();
        $reminderStatement->close();

        if ($reminders === []) {
            return 0;
        }

        $title = 'A menu item is available';
        $safeProductName = trim($productName) !== '' ? trim($productName) : 'A menu item';
        $message = $safeProductName . ' is back on the menu and ready to order.';
        $linkUrl = 'menu.php#' . app_slug($safeProductName);
        $type = 'product_available';
        $status = 'unread';
        $notificationStatement = $mysqli->prepare(
            'INSERT INTO user_notifications (user_id, type, title, message, link_url, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $notificationUserId = 0;
        $notificationStatement->bind_param(
            'isssss',
            $notificationUserId,
            $type,
            $title,
            $message,
            $linkUrl,
            $status
        );

        foreach ($reminders as $reminder) {
            $notificationUserId = $reminder['user_id'];
            $notificationStatement->execute();
        }

        $notificationStatement->close();

        $updateStatement = $mysqli->prepare(
            'UPDATE product_reminders
             SET status = "notified", notified_at = NOW()
             WHERE product_id = ? AND status = "pending"'
        );
        $updateStatement->bind_param('i', $productId);
        $updateStatement->execute();
        $updateStatement->close();

        return count($reminders);
    } catch (mysqli_sql_exception $exception) {
        return 0;
    }
}

function app_user_notifications(int $userId, int $limit = 5): array
{
    if ($userId < 1) {
        return [];
    }

    $limit = max(1, min(10, $limit));

    try {
        $mysqli = app_db();
        app_ensure_product_reminder_tables($mysqli);

        $statement = $mysqli->prepare(
            'SELECT id, type, title, message, link_url, status, created_at
             FROM user_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?'
        );
        $statement->bind_param('ii', $userId, $limit);
        $statement->execute();
        $result = $statement->get_result();
        $notifications = [];

        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        $result->free();
        $statement->close();
        $mysqli->close();

        return $notifications;
    } catch (mysqli_sql_exception $exception) {
        return [];
    }
}

function app_contact_topics(): array
{
    return [
        'visit' => 'Cafe visit',
        'order' => 'Order question',
        'events' => 'Small gathering',
        'support' => 'General support',
    ];
}

function app_contact_topic_label(string $topic): string
{
    $topics = app_contact_topics();

    return $topics[$topic] ?? 'General support';
}

function app_ensure_contact_messages_table(mysqli $mysqli): void
{
    static $hasTable = false;

    if ($hasTable) {
        return;
    }

    $mysqli->query(
        'CREATE TABLE IF NOT EXISTS contact_messages (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            topic VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            status ENUM("unread", "read") DEFAULT "unread",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $hasTable = true;
}

function app_create_contact_message(string $name, string $email, string $topic, string $message): void
{
    $mysqli = app_db();
    app_ensure_contact_messages_table($mysqli);

    $statement = $mysqli->prepare(
        'INSERT INTO contact_messages (name, email, topic, message, status) VALUES (?, ?, ?, ?, "unread")'
    );
    $statement->bind_param('ssss', $name, $email, $topic, $message);
    $statement->execute();
    $statement->close();
    $mysqli->close();
}

function app_contact_messages(): array
{
    $mysqli = app_db();
    app_ensure_contact_messages_table($mysqli);

    $messages = [];
    $result = $mysqli->query(
        'SELECT id, name, email, topic, message, status, created_at
         FROM contact_messages
         ORDER BY created_at DESC, id DESC'
    );

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    $result->free();
    $mysqli->close();

    return $messages;
}

function app_contact_message(int $messageId): ?array
{
    if ($messageId < 1) {
        return null;
    }

    $mysqli = app_db();
    app_ensure_contact_messages_table($mysqli);

    $statement = $mysqli->prepare(
        'SELECT id, name, email, topic, message, status, created_at
         FROM contact_messages
         WHERE id = ?
         LIMIT 1'
    );
    $statement->bind_param('i', $messageId);
    $statement->execute();
    $result = $statement->get_result();
    $message = $result->fetch_assoc() ?: null;
    $result->free();
    $statement->close();
    $mysqli->close();

    return $message;
}

function app_mark_contact_message_read(int $messageId): void
{
    if ($messageId < 1) {
        return;
    }

    $mysqli = app_db();
    app_ensure_contact_messages_table($mysqli);

    $statement = $mysqli->prepare('UPDATE contact_messages SET status = "read" WHERE id = ?');
    $statement->bind_param('i', $messageId);
    $statement->execute();
    $statement->close();
    $mysqli->close();
}
