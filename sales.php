<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'User';
$user_id = $_SESSION['user_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user name based on role
$name = '';
try {
    if ($role === 'User') {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM user_profiles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $name = trim($profile['first_name'] . ' ' . $profile['last_name']);
        }
    } elseif ($role === 'Head of Company') {
        $stmt = $conn->prepare("SELECT company_name FROM company_head_details WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($company) {
            $name = $company['company_name'];
        }
    }
    if (empty($name)) {
        $name = $_SESSION['username'];
    }
} catch (PDOException $e) {
    $name = $_SESSION['username'];
}

$errorMsg = '';
$successMsg = '';
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($role === 'User' ? $user_id : 0);

// Fetch users for Head of Company dropdown
$users = [];
if ($role === 'Head of Company') {
    try {
        $stmt = $conn->prepare("SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name 
                                FROM users u 
                                JOIN user_profiles up ON u.id = up.user_id 
                                WHERE u.role = 'User'");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Failed to load users: " . htmlspecialchars($e->getMessage());
    }
}

// Helper: safe integer from POST
function safe_int($arr, $key, $default = 0) {
    return isset($arr[$key]) && is_numeric($arr[$key]) && (int)$arr[$key] > 0 ? (int)$arr[$key] : $default;
}

// Helper: safe string
function safe_str($arr, $key, $default = '') {
    return isset($arr[$key]) ? trim($arr[$key]) : $default;
}

// Handle form submissions (only for User role)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $role === 'User') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        try {
            if (isset($_POST['save_sale'])) {
                $product_id = safe_int($_POST, 'product_id');
                $shopkeeper_id = safe_int($_POST, 'shopkeeper_id');
                $delivery_man_id = safe_int($_POST, 'delivery_man_id');
                $quantity = safe_int($_POST, 'quantity');
                $payment_type = safe_str($_POST, 'payment_type');

                if ($product_id <= 0) {
                    $errorMsg = "Invalid product selected.";
                } elseif ($shopkeeper_id <= 0) {
                    $errorMsg = "Invalid shopkeeper selected.";
                } elseif ($delivery_man_id <= 0) {
                    $errorMsg = "Invalid delivery man selected.";
                } elseif ($quantity <= 0 || $quantity > 10000) {
                    $errorMsg = "Quantity must be a positive integer not exceeding 10,000.";
                } elseif (!in_array($payment_type, ['Cash', 'Credit'])) {
                    $errorMsg = "Invalid payment type.";
                } else {
                    $stmt = $conn->prepare("SELECT price, quantity, user_id FROM products WHERE id = :id");
                    $stmt->execute(['id' => $product_id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product && $product['user_id'] == $user_id) {
                        if ($product['quantity'] >= $quantity) {
                            $stmt = $conn->prepare("SELECT user_id FROM shopkeepers WHERE id = :id");
                            $stmt->execute(['id' => $shopkeeper_id]);
                            if ($stmt->fetchColumn() == $user_id) {
                                $stmt = $conn->prepare("SELECT role FROM employees WHERE id = :id AND user_id = :user_id");
                                $stmt->execute(['id' => $delivery_man_id, 'user_id' => $user_id]);
                                $delivery_role = $stmt->fetchColumn();
                                if (stripos($delivery_role, 'delivery') !== false) {
                                    $amount = $product['price'] * $quantity;
                                    $conn->beginTransaction();
                                    try {
                                        $stmt = $conn->prepare("
                                            INSERT INTO sales (user_id, product_id, shopkeeper_id, delivery_man_id, quantity, sale_date, amount, payment_type, return_quantity) 
                                            VALUES (:user_id, :product_id, :shopkeeper_id, :delivery_man_id, :quantity, CURDATE(), :amount, :payment_type, 0)
                                        ");
                                        $stmt->execute([
                                            'user_id' => $user_id,
                                            'product_id' => $product_id,
                                            'shopkeeper_id' => $shopkeeper_id,
                                            'delivery_man_id' => $delivery_man_id,
                                            'quantity' => $quantity,
                                            'amount' => $amount,
                                            'payment_type' => $payment_type
                                        ]);

                                        $sale_id = (int)$conn->lastInsertId();

                                        $stmt = $conn->prepare("
                                            INSERT INTO transactions (user_id, shopkeeper_id, sale_id, amount, payment_type, transaction_date) 
                                            VALUES (:user_id, :shopkeeper_id, :sale_id, :amount, :payment_type, CURDATE())
                                        ");
                                        $stmt->execute([
                                            'user_id' => $user_id,
                                            'shopkeeper_id' => $shopkeeper_id,
                                            'sale_id' => $sale_id,
                                            'amount' => $amount,
                                            'payment_type' => $payment_type
                                        ]);

                                        $new_quantity = $product['quantity'] - $quantity;
                                        $stmt = $conn->prepare("UPDATE products SET quantity = :quantity WHERE id = :id AND user_id = :user_id");
                                        $stmt->execute(['quantity' => $new_quantity, 'id' => $product_id, 'user_id' => $user_id]);

                                        $conn->commit();
                                        $successMsg = "Sale recorded successfully!";
                                    } catch (Exception $e) {
                                        $conn->rollBack();
                                        $errorMsg = "Failed to record sale: " . htmlspecialchars($e->getMessage());
                                    }
                                } else {
                                    $errorMsg = "Selected employee is not a delivery man.";
                                }
                            } else {
                                $errorMsg = "You are not authorized to select this shopkeeper.";
                            }
                        } else {
                            $errorMsg = "Insufficient product quantity!";
                        }
                    } else {
                        $errorMsg = "Invalid product selected or you are not authorized!";
                    }
                }
            } elseif (isset($_POST['update_sale'])) {
                $sale_id = safe_int($_POST, 'sale_id');
                $return_quantity = safe_int($_POST, 'return_quantity');

                if ($sale_id <= 0) {
                    $errorMsg = "Invalid sale ID.";
                } elseif ($return_quantity < 0) {
                    $errorMsg = "Return quantity cannot be negative.";
                } else {
                    $stmt = $conn->prepare("
                        SELECT s.quantity, COALESCE(s.return_quantity,0) as return_quantity, s.product_id, s.shopkeeper_id, s.user_id, p.quantity as product_quantity, p.price 
                        FROM sales s 
                        JOIN products p ON s.product_id = p.id 
                        WHERE s.id = :id
                    ");
                    $stmt->execute(['id' => $sale_id]);
                    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($sale && $sale['user_id'] == $user_id) {
                        $max_return = $sale['quantity'] - $sale['return_quantity'];
                        if ($return_quantity <= $max_return) {
                            $conn->beginTransaction();
                            try {
                                $new_return_quantity = $sale['return_quantity'] + $return_quantity;
                                $stmt = $conn->prepare("UPDATE sales SET return_quantity = :return_quantity WHERE id = :id AND user_id = :user_id");
                                $stmt->execute(['return_quantity' => $new_return_quantity, 'id' => $sale_id, 'user_id' => $user_id]);

                                $new_stock = $sale['product_quantity'] + $return_quantity;
                                $stmt = $conn->prepare("UPDATE products SET quantity = :quantity WHERE id = :id AND user_id = :user_id");
                                $stmt->execute(['quantity' => $new_stock, 'id' => $sale['product_id'], 'user_id' => $user_id]);

                                $return_amount = $return_quantity * $sale['price'];
                                $stmt = $conn->prepare("
                                    INSERT INTO transactions (user_id, shopkeeper_id, sale_id, amount, payment_type, transaction_date) 
                                    VALUES (:user_id, :shopkeeper_id, :sale_id, :amount, 'Return', CURDATE())
                                ");
                                $stmt->execute([
                                    'user_id' => $user_id,
                                    'shopkeeper_id' => $sale['shopkeeper_id'],
                                    'sale_id' => $sale_id,
                                    'amount' => -$return_amount
                                ]);

                                $conn->commit();
                                $successMsg = "Sale updated successfully!";
                            } catch (Exception $e) {
                                $conn->rollBack();
                                $errorMsg = "Failed to update sale: " . htmlspecialchars($e->getMessage());
                            }
                        } else {
                            $errorMsg = "Return quantity exceeds available amount.";
                        }
                    } else {
                        $errorMsg = "Invalid sale selected or you are not authorized!";
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $id = safe_int($_POST, 'id');
                if ($id <= 0) {
                    $errorMsg = "Invalid sale ID for deletion.";
                } else {
                    $stmt = $conn->prepare("SELECT user_id FROM sales WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    if ($stmt->fetchColumn() == $user_id) {
                        $conn->beginTransaction();
                        try {
                            $stmt = $conn->prepare("DELETE FROM sales WHERE id = :id AND user_id = :user_id");
                            $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                            $stmt = $conn->prepare("DELETE FROM transactions WHERE sale_id = :id AND user_id = :user_id");
                            $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                            $conn->commit();
                            $successMsg = "Sale deleted successfully!";
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $errorMsg = "Failed to delete sale: " . htmlspecialchars($e->getMessage());
                        }
                    } else {
                        $errorMsg = "You are not authorized to delete this sale.";
                    }
                }
            }
        } catch (Exception $e) {
            $errorMsg = "Unexpected error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch lists for dropdowns
$products = $shopkeepers = $delivery_men = $sales = [];
try {
    $fetch_user_id = ($role === 'User') ? $user_id : $selected_user_id;
    if ($fetch_user_id > 0) {
        $stmt = $conn->prepare("SELECT id, model FROM products WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT id, name FROM shopkeepers WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $shopkeepers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT id, name, role FROM employees WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $delivery_men = array_filter($all_employees, function($emp) {
            return isset($emp['role']) && stripos($emp['role'], 'delivery') !== false;
        });

        $sql = "
            SELECT s.*, COALESCE(s.return_quantity,0) as return_quantity, p.model, sk.name as shopkeeper_name, e.name as delivery_man_name 
            FROM sales s 
            JOIN products p ON s.product_id = p.id 
            JOIN shopkeepers sk ON s.shopkeeper_id = sk.id 
            JOIN employees e ON s.delivery_man_id = e.id 
            WHERE s.user_id = :user_id 
            ORDER BY s.sale_date DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $fetch_user_id]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - Elite Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <style>
        /* Enhanced Glassmorphism and Premium Styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
        }
        .gradient-text {
            background: linear-gradient(90deg, #34d399, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .fade-in {
            animation: fadeIn 1s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            overflow-x: hidden;
        }
        /* Enhanced Particle Background */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        /* Premium Scrollbar */
        ::-webkit-scrollbar { width: 12px; }
        ::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(#34d399, #10b981); border-radius: 10px; border: 2px solid #1e293b; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(#10b981, #059669); }
        /* Form and Table Styling */
        input, select, button {
            transition: all 0.4s ease;
        }
        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 2px #10b981;
        }
        table tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        /* Premium Select Styling */
        select {
            appearance: none;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23d1d5db'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") no-repeat right 1rem center/1.5em;
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
        }
        select option {
            background: #1e293b;
            color: #d1d5db;
        }
        /* Modal Styling */
        .modal-overlay {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
        }
        .modal {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .modal input {
            background: rgba(255, 255, 255, 0.08);
            color: #d1d5db;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .modal button:hover {
            background: #059669;
        }
        /* Toast Styling */
        #successToast {
            background: linear-gradient(90deg, #34d399, #10b981);
        }
        #errorToast {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        .toast {
            transition: opacity 0.5s ease, transform 0.5s ease;
            opacity: 0;
            transform: translateY(20px);
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="min-h-screen text-gray-100 font-inter">
    <!-- Particle Background -->
    <div id="particles-js"></div>
    <script>
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 1000 } },
                color: { value: '#34d399' },
                shape: { type: 'circle' },
                opacity: { value: 0.4, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: '#34d399', opacity: 0.3, width: 1.5 },
                move: { enable: true, speed: 2, direction: 'none', random: false, straight: false, out_mode: 'out' }
            },
            interactivity: {
                detect_on: 'canvas',
                events: { onhover: { enable: true, mode: 'grab' }, onclick: { enable: true, mode: 'push' }, resize: true },
                modes: { grab: { distance: 200, line_linked: { opacity: 0.5 } }, push: { particles_nb: 4 } }
            },
            retina_detect: true
        });
    </script>

    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-800 to-teal-700 text-white py-8 px-8 shadow-2xl">
        <div class="container mx-auto flex items-center justify-between pl-64">
            <div class="flex items-center space-x-4">
                <i class="fa-solid fa-receipt text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Sales Management</h1>
            </div>
            <div class="text-right">
                <p class="text-2xl font-semibold"><?php echo htmlspecialchars($name); ?></p>
                <p class="text-md opacity-80"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto mt-10 px-6 flex">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 ml-64 p-10" role="main">
            <?php if ($role === 'Head of Company'): ?>
                <form class="user-filter-form glass-card p-6 rounded-2xl mb-10 fade-in" method="GET">
                    <div class="form-group">
                        <label for="user_id" class="text-lg font-semibold gradient-text mb-2">Select User</label>
                        <select name="user_id" id="user_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" onchange="this.form.submit()">
                            <option value="" <?php echo $selected_user_id == 0 ? 'selected' : ''; ?>>Select a User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                                        <?php echo $selected_user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
            <?php if ($role === 'User'): ?>
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-receipt text-xl"></i>
                    <span>Record a Sale</span>
                </h2>
                <div class="glass-card p-10 rounded-2xl mb-10 fade-in">
                    <form method="POST" id="saleForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="product_id" class="text-lg font-semibold gradient-text mb-2">Select Product</label>
                            <select name="product_id" id="product_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['id']); ?>">
                                        <?php echo htmlspecialchars($product['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="shopkeeper_id" class="text-lg font-semibold gradient-text mb-2">Select Shopkeeper</label>
                            <select name="shopkeeper_id" id="shopkeeper_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Shopkeeper</option>
                                <?php foreach ($shopkeepers as $shopkeeper): ?>
                                    <option value="<?php echo htmlspecialchars($shopkeeper['id']); ?>">
                                        <?php echo htmlspecialchars($shopkeeper['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="delivery_man_id" class="text-lg font-semibold gradient-text mb-2">Select Delivery Man</label>
                            <select name="delivery_man_id" id="delivery_man_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Delivery Man</option>
                                <?php foreach ($delivery_men as $delivery_man): ?>
                                    <option value="<?php echo htmlspecialchars($delivery_man['id']); ?>">
                                        <?php echo htmlspecialchars($delivery_man['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity" class="text-lg font-semibold gradient-text mb-2">Quantity</label>
                            <input type="number" name="quantity" id="quantity" placeholder="Quantity" min="1" max="10000" step="1" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="payment_type" class="text-lg font-semibold gradient-text mb-2">Payment Type</label>
                            <select name="payment_type" id="payment_type" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Payment Type</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                        <button type="submit" name="save_sale" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-floppy-disk mr-3"></i> Save Sale
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($role === 'User' || $selected_user_id > 0): ?>
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                    <span>Sales History</span>
                </h2>
                <button id="exportPdfBtn" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-8 py-4 rounded-lg mb-6 transition-all duration-300 shadow-lg">
                    <i class="fa-solid fa-file-pdf mr-3"></i> Export PDF
                </button>
                <div class="glass-card p-10 rounded-2xl fade-in">
                    <div class="table-container overflow-x-auto">
                        <table id="salesTable" class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">ID</th>
                                    <th class="p-5">Product</th>
                                    <th class="p-5">Shopkeeper</th>
                                    <th class="p-5">Delivery Man</th>
                                    <th class="p-5">Quantity</th>
                                    <th class="p-5">Return Quantity</th>
                                    <th class="p-5">Amount (৳)</th>
                                    <th class="p-5">Payment Type</th>
                                    <th class="p-5">Date</th>
                                    <th class="p-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($sales): ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="ID"><?php echo htmlspecialchars($sale['id']); ?></td>
                                            <td class="p-5" data-label="Product"><?php echo htmlspecialchars($sale['model']); ?></td>
                                            <td class="p-5" data-label="Shopkeeper"><?php echo htmlspecialchars($sale['shopkeeper_name']); ?></td>
                                            <td class="p-5" data-label="Delivery Man"><?php echo htmlspecialchars($sale['delivery_man_name']); ?></td>
                                            <td class="p-5" data-label="Quantity"><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                            <td class="p-5" data-label="Return Quantity"><?php echo htmlspecialchars($sale['return_quantity']); ?></td>
                                            <td class="p-5" data-label="Amount (৳)"><?php echo number_format($sale['amount'], 2); ?></td>
                                            <td class="p-5" data-label="Payment Type"><span class="payment-type <?php echo strtolower($sale['payment_type']); ?>"><?php echo htmlspecialchars($sale['payment_type']); ?></span></td>
                                            <td class="p-5" data-label="Date"><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                                            <td class="p-5 action-buttons" data-label="Actions">
                                                <?php if ($role === 'User'): ?>
                                                    <button class="update-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" 
                                                            data-id="<?php echo htmlspecialchars($sale['id']); ?>" 
                                                            data-return-quantity="<?php echo htmlspecialchars($sale['return_quantity']); ?>" 
                                                            title="Update Sale">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this sale?');" class="inline-block">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($sale['id']); ?>">
                                                        <button type="submit" name="delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" title="Delete Sale">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="p-5 text-center">No sales found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Update Modal -->
    <div class="modal-overlay fixed inset-0 flex items-center justify-center z-[1000] hidden" id="updateModal">
        <div class="modal p-10 rounded-2xl max-w-lg w-full">
            <button class="close-btn absolute top-6 right-6 text-gray-400 hover:text-gray-100 text-3xl" id="closeModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
                <span>Update Sale</span>
            </h3>
            <form method="POST" id="updateForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="sale_id" id="updateSaleId">
                <input type="number" name="return_quantity" id="updateReturnQuantity" placeholder="Return Quantity" min="0" step="1" class="w-full p-4 rounded-lg" required>
                <button type="submit" name="update_sale" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg w-full transition-all duration-300 shadow-lg">
                    <i class="fa-solid fa-floppy-disk mr-3"></i>Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        const modalOverlay = document.getElementById('updateModal');
        const closeModalBtn = document.getElementById('closeModal');
        const updateForm = document.getElementById('updateForm');
        const updateSaleId = document.getElementById('updateSaleId');
        const updateReturnQuantity = document.getElementById('updateReturnQuantity');
        const saleForm = document.getElementById('saleForm');

        // Modal Handling
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                updateSaleId.value = btn.getAttribute('data-id');
                updateReturnQuantity.value = btn.getAttribute('data-return-quantity');
                modalOverlay.classList.remove('hidden');
            });
        });

        closeModalBtn.addEventListener('click', () => {
            modalOverlay.classList.add('hidden');
        });

        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) modalOverlay.classList.add('hidden');
        });

        // Client-side Validation for Sale Form
        if (saleForm) {
            saleForm.addEventListener('submit', (e) => {
                const productId = document.getElementById('product_id').value;
                const shopkeeperId = document.getElementById('shopkeeper_id').value;
                const deliveryManId = document.getElementById('delivery_man_id').value;
                const quantity = parseInt(document.getElementById('quantity').value) || 0;
                const paymentType = document.getElementById('payment_type').value;

                if (!productId) {
                    e.preventDefault();
                    showToast('error', 'Please select a product.');
                    return;
                }
                if (!shopkeeperId) {
                    e.preventDefault();
                    showToast('error', 'Please select a shopkeeper.');
                    return;
                }
                if (!deliveryManId) {
                    e.preventDefault();
                    showToast('error', 'Please select a delivery man.');
                    return;
                }
                if (quantity <= 0 || quantity > 10000) {
                    e.preventDefault();
                    showToast('error', 'Quantity must be between 1 and 10,000.');
                    return;
                }
                if (!paymentType) {
                    e.preventDefault();
                    showToast('error', 'Please select a payment type.');
                    return;
                }
            });
        }

        // Client-side Validation for Update Form
        if (updateForm) {
            updateForm.addEventListener('submit', (e) => {
                const returnQuantity = parseInt(updateReturnQuantity.value) || 0;
                if (returnQuantity < 0) {
                    e.preventDefault();
                    showToast('error', 'Return quantity cannot be negative.');
                    return;
                }
            });
        }

        // Show Toasts
        <?php if ($successMsg): ?>
            showToast('success', "<?php echo addslashes($successMsg); ?>");
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            showToast('error', "<?php echo addslashes($errorMsg); ?>");
        <?php endif; ?>
        function showToast(type, message) {
            const toast = type === 'success' ? document.getElementById('successToast') : document.getElementById('errorToast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // PDF Export
        document.getElementById('exportPdfBtn').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF("p", "pt", "a4");
            doc.setFont("Inter", "bold");
            doc.setFontSize(24);
            doc.setTextColor(40, 40, 40);
            doc.text("Sales History Report", doc.internal.pageSize.getWidth() / 2, 50, { align: "center" });
            doc.setFont("Inter", "normal");
            doc.setFontSize(12);
            doc.setTextColor(100);
            const today = new Date();
            doc.text(`Generated on: ${today.toLocaleDateString()} ${today.toLocaleTimeString()}`, 
                     doc.internal.pageSize.getWidth() / 2, 70, { align: "center" });
            doc.autoTable({
                startY: 90,
                html: '#salesTable',
                styles: {
                    font: "Inter",
                    fontSize: 11,
                    cellPadding: 8,
                    valign: 'middle',
                    lineColor: [200, 200, 200],
                    lineWidth: 1,
                },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontSize: 12,
                    fontStyle: 'bold',
                },
                alternateRowStyles: {
                    fillColor: [240, 240, 240]
                },
                columnStyles: {
                    9: { cellWidth: 0, cellPadding: 0, fontSize: 0, textColor: [255, 255, 255] }
                },
                didDrawCell: function (data) {
                    if (data.column.index === 9) {
                        data.cell.text = [''];
                    }
                },
                margin: { left: 40, right: 40 },
            });
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(10);
                doc.setTextColor(150);
                doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.getWidth() - 50, 
                         doc.internal.pageSize.getHeight() - 30, { align: "right" });
            }
            doc.save('elite-sales-report.pdf');
        });
    </script>
</body>
</html>