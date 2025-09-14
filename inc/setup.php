<?php
require_once 'connection.php';

$createAdmins = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(200) UNIQUE,
    password VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$createUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(200),
    password VARCHAR(255),
    phone VARCHAR(15),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$createCategories = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200),
    image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$createBrands = "CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$createProducts = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200),
    description TEXT,
    image VARCHAR(255),
    price INT,
    stock INT,
    category_id INT NULL,
    brand_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
)";

$createOrders = "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_price DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'Pending',
    payment_method VARCHAR(50) DEFAULT 'Cash on Delivery',
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    shipping_region VARCHAR(100),
    shipping_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$createOrderDetails = "CREATE TABLE IF NOT EXISTS order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT,
    price DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";


$createCart = "CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    quantity INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";

// $createRatings = "CREATE TABLE IF NOT EXISTS product_ratings (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT,
//     product_id INT,
//     rating INT CHECK (rating BETWEEN 1 AND 5),
//     review TEXT,
//     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//     UNIQUE (user_id, product_id),
//     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
//     FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
// )";

$createAddresses = "CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    address TEXT,
    city VARCHAR(100),
    region VARCHAR(100),
    notes TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$createShippingFees = "CREATE TABLE IF NOT EXISTS shipping_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) UNIQUE,
    fee DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";


$createShippingTracking = "CREATE TABLE IF NOT EXISTS shipping_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    status VARCHAR(100),
    location VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";
$createProductVariants = "CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    flavor VARCHAR(100),
    size VARCHAR(50),
    nicotine VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";
$createWishlist = "CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE(user_id, product_id) 
)";



$conn->query($createAdmins);
$conn->query($createUsers);
$conn->query($createCategories);
$conn->query($createBrands);
$conn->query($createProducts);
$conn->query($createOrders);
$conn->query($createOrderDetails);
$conn->query($createCart);
// $conn->query($createRatings);
$conn->query($createShippingFees);
$conn->query($createAddresses);
$conn->query($createShippingTracking);
$conn->query($createProductVariants);
$conn->query($createWishlist);

echo "✅ All tables created successfully.";
?>