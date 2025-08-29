<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'User';
$user_id = $_SESSION['user_id'];

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

// Initialize variables
$successMsg = '';
$errorMsg = '';
$products = [];
$totals = ['quantity' => 0, 'buy_value' => 0, 'sell_value' => 0, 'profit' => 0];

if ($role === 'Head of Company') {
    $errorMsg = "Head of Company users cannot access or manage product data.";
} else {
    // Handling form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add'])) {
            $brand_name = trim($_POST['brand_name'] ?? '');
            $product_name = trim($_POST['product_name'] ?? '');
            $buy_rate = trim($_POST['buy_rate'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');

            // Enhanced validation
            if (empty($brand_name) || !preg_match("/^[a-zA-Z0-9\s\-_\.]{2,50}$/", $brand_name)) {
                $errorMsg = "Brand Name must be 2-50 characters (letters, numbers, spaces, - _ . allowed).";
            } elseif (empty($product_name) || !preg_match("/^[a-zA-Z0-9\s\-_\.]{2,100}$/", $product_name)) {
                $errorMsg = "Product Name must be 2-100 characters (letters, numbers, spaces, - _ . allowed).";
            } elseif (!is_numeric($buy_rate) || $buy_rate < 0 || !preg_match("/^\d+(\.\d{1,2})?$/", $buy_rate)) {
                $errorMsg = "Buy Rate must be a valid non-negative number (up to 2 decimals).";
            } elseif (!is_numeric($price) || $price < 0 || !preg_match("/^\d+(\.\d{1,2})?$/", $price)) {
                $errorMsg = "Sell Rate must be a valid non-negative number (up to 2 decimals).";
            } elseif (!is_numeric($quantity) || $quantity < 0 || !preg_match("/^[0-9]+$/", $quantity)) {
                $errorMsg = "Quantity must be a non-negative integer.";
            } else {
                try {
                    $stmt = $conn->prepare("INSERT INTO products (user_id, brand_name, model, buy_rate, price, quantity) 
                                            VALUES (:user_id, :brand_name, :product_name, :buy_rate, :price, :quantity)");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'brand_name' => $brand_name,
                        'product_name' => $product_name,
                        'buy_rate' => $buy_rate,
                        'price' => $price,
                        'quantity' => $quantity
                    ]);
                    $successMsg = "✅ Product added successfully!";
                } catch (PDOException $e) {
                    $errorMsg = "Error adding product: " . $e->getMessage();
                }
            }
        } elseif (isset($_POST['update'])) {
            $id = $_POST['id'] ?? '';
            $brand_name = trim($_POST['brand_name'] ?? '');
            $product_name = trim($_POST['product_name'] ?? '');
            $buy_rate = trim($_POST['buy_rate'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');

            $stmt = $conn->prepare("SELECT user_id FROM products WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if ($stmt->fetchColumn() != $user_id) {
                $errorMsg = "⛔ You are not authorized to update this product.";
            } elseif (empty($brand_name) || !preg_match("/^[a-zA-Z0-9\s\-_\.]{2,50}$/", $brand_name)) {
                $errorMsg = "Brand Name must be 2-50 characters (letters, numbers, spaces, - _ . allowed).";
            } elseif (empty($product_name) || !preg_match("/^[a-zA-Z0-9\s\-_\.]{2,100}$/", $product_name)) {
                $errorMsg = "Product Name must be 2-100 characters (letters, numbers, spaces, - _ . allowed).";
            } elseif (!is_numeric($buy_rate) || $buy_rate < 0 || !preg_match("/^\d+(\.\d{1,2})?$/", $buy_rate)) {
                $errorMsg = "Buy Rate must be a valid non-negative number (up to 2 decimals).";
            } elseif (!is_numeric($price) || $price < 0 || !preg_match("/^\d+(\.\d{1,2})?$/", $price)) {
                $errorMsg = "Sell Rate must be a valid non-negative number (up to 2 decimals).";
            } elseif (!is_numeric($quantity) || $quantity < 0 || !preg_match("/^[0-9]+$/", $quantity)) {
                $errorMsg = "Quantity must be a non-negative integer.";
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE products 
                        SET brand_name = :brand_name, model = :product_name, buy_rate = :buy_rate, price = :price, quantity = :quantity 
                        WHERE id = :id AND user_id = :user_id");
                    $stmt->execute([
                        'id' => $id,
                        'user_id' => $user_id,
                        'brand_name' => $brand_name,
                        'product_name' => $product_name,
                        'buy_rate' => $buy_rate,
                        'price' => $price,
                        'quantity' => $quantity
                    ]);
                    $successMsg = "✅ Product updated successfully!";
                } catch (PDOException $e) {
                    $errorMsg = "Error updating product: " . $e->getMessage();
                }
            }
        } elseif (isset($_POST['delete'])) {
            $id = $_POST['id'] ?? '';
            $stmt = $conn->prepare("SELECT user_id FROM products WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if ($stmt->fetchColumn() == $user_id) {
                try {
                    $stmt = $conn->prepare("DELETE FROM products WHERE id = :id AND user_id = :user_id");
                    $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                    $successMsg = "🗑️ Product deleted successfully!";
                } catch (PDOException $e) {
                    $errorMsg = "Error deleting product: " . $e->getMessage();
                }
            } else {
                $errorMsg = "⛔ You are not authorized to delete this product.";
            }
        }
    }

    // Fetch products
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE user_id = :user_id ORDER BY id DESC");
        $stmt->execute(['user_id' => $user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            $totals['quantity'] += $p['quantity'];
            $totals['buy_value'] += $p['buy_rate'] * $p['quantity'];
            $totals['sell_value'] += $p['price'] * $p['quantity'];
            $totals['profit'] += ($p['price'] - $p['buy_rate']) * $p['quantity'];
        }
    } catch (PDOException $e) {
        $errorMsg = "Error fetching products: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Product Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <style>
        body {
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
            font-family: 'Inter', sans-serif;
            color: #e5e7eb;
            overflow-x: hidden;
        }
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -10;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5);
        }
        .gradient-text {
            background: linear-gradient(90deg, #34d399, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #1e293b;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(#34d399, #10b981);
            border-radius: 5px;
            border: 2px solid #1e293b;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(#10b981, #059669);
        }
        input, button {
            transition: all 0.3s ease;
        }
        input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.4);
        }
        table tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .modal-overlay {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
        }
        .modal {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
        .modal input, .modal select {
            background: rgba(255, 255, 255, 0.1);
            color: #e5e7eb;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .modal button:hover {
            background: #059669;
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
        #searchInput {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e5e7eb;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 320px;
        }
        #searchInput:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.4);
        }
        .action-buttons button, .action-buttons form {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Particle Background -->
    <div id="particles-js"></div>
    <script>
        particlesJS('particles-js', {
            particles: {
                number: { value: 100, density: { enable: true, value_area: 1200 } },
                color: { value: '#34d399' },
                shape: { type: 'circle' },
                opacity: { value: 0.5, random: true },
                size: { value: 2.5, random: true },
                line_linked: { enable: true, distance: 120, color: '#34d399', opacity: 0.4, width: 1 },
                move: { enable: true, speed: 1.5, direction: 'none', random: false, straight: false, out_mode: 'out' }
            },
            interactivity: {
                detect_on: 'canvas',
                events: { onhover: { enable: true, mode: 'grab' }, onclick: { enable: true, mode: 'push' }, resize: true },
                modes: { grab: { distance: 180, line_linked: { opacity: 0.6 } }, push: { particles_nb: 3 } }
            },
            retina_detect: true
        });
    </script>

    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-900 to-teal-800 text-white py-10 px-8 shadow-2xl">
        <div class="container mx-auto flex items-center justify-between pl-64">
            <div class="flex items-center space-x-4">
                <i class="fa-solid fa-boxes-stacked text-5xl animate-pulse text-emerald-300"></i>
                <h1 class="text-4xl font-bold gradient-text">Elite Product Dashboard</h1>
            </div>
            <div class="text-right">
                <p class="text-2xl font-semibold"><?php echo htmlspecialchars($name); ?></p>
                <p class="text-md opacity-80"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto mt-12 px-6 flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Product Management Content -->
        <main class="flex-1 ml-64 p-10" role="main">
            <?php if ($errorMsg): ?>
                <div class="glass-card bg-red-600/20 border-red-600/30 text-red-200 p-6 rounded-2xl mb-10 fade-in" aria-live="assertive">
                    <i class="fa-solid fa-circle-exclamation mr-3 text-xl"></i>
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php else: ?>
                <!-- Add Product Form -->
                <div class="glass-card p-10 rounded-2xl mb-12 fade-in">
                    <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                        <i class="fa-solid fa-plus-circle text-xl"></i>
                        <span>Add New Product</span>
                    </h3>
                    <form method="POST" id="addProductForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <input type="text" name="brand_name" placeholder="Brand Name" required minlength="2" maxlength="50" class="w-full p-4 rounded-lg glass-card text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-tag absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <input type="text" name="product_name" placeholder="Product Name" required minlength="2" maxlength="100" class="w-full p-4 rounded-lg glass-card text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-box absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <input type="number" name="buy_rate" step="0.01" min="0" placeholder="Buy Rate (৳)" required class="w-full p-4 rounded-lg glass-card text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-money-bill-wave absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <input type="number" name="price" step="0.01" min="0" placeholder="Sell Rate (৳)" required class="w-full p-4 rounded-lg glass-card text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-coins absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <input type="number" name="quantity" min="0" step="1" placeholder="Quantity" required class="w-full p-4 rounded-lg glass-card text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-cubes absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <button type="submit" name="add" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg flex items-center justify-center">
                            <i class="fa-solid fa-plus mr-3"></i>Add Product
                        </button>
                    </form>
                </div>

                <!-- Product List -->
                <div class="glass-card p-10 rounded-2xl fade-in">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-semibold gradient-text flex items-center space-x-3">
                            <i class="fa-solid fa-boxes-stacked text-xl"></i>
                            <span>Product Inventory</span>
                        </h3>
                        <div class="flex space-x-4">
                            <input type="text" id="searchInput" placeholder="Search products..." class="mb-0">
                            <button id="exportPdfBtn" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-8 py-4 rounded-lg transition-all duration-300 shadow-lg flex items-center">
                                <i class="fa-solid fa-file-pdf mr-3"></i>Export to PDF
                            </button>
                        </div>
                    </div>
                    <div class="table-container overflow-x-auto">
                        <table id="productTable" class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-900/60 text-gray-100">
                                    <th class="p-5 rounded-tl-lg">ID</th>
                                    <th class="p-5">Brand Name</th>
                                    <th class="p-5">Product Name</th>
                                    <th class="p-5">Buy Rate (৳)</th>
                                    <th class="p-5">Sell Rate (৳)</th>
                                    <th class="p-5">Quantity</th>
                                    <th class="p-5 rounded-tr-lg">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                <tr class="border-b border-gray-700/30 hover:bg-emerald-900/20 transition-colors">
                                    <td class="p-5" data-label="ID"><?php echo $p['id']; ?></td>
                                    <td class="p-5" data-label="Brand Name"><?php echo htmlspecialchars($p['brand_name']); ?></td>
                                    <td class="p-5" data-label="Product Name"><?php echo htmlspecialchars($p['model']); ?></td>
                                    <td class="p-5" data-label="Buy Rate (৳)"><?php echo number_format($p['buy_rate'], 2); ?></td>
                                    <td class="p-5" data-label="Sell Rate (৳)"><?php echo number_format($p['price'], 2); ?></td>
                                    <td class="p-5" data-label="Quantity"><?php echo $p['quantity']; ?></td>
                                    <td class="p-5 action-buttons" data-label="Actions">
                                        <button class="update-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow" 
                                                data-id="<?php echo $p['id']; ?>"
                                                data-brand="<?php echo htmlspecialchars($p['brand_name']); ?>"
                                                data-product="<?php echo htmlspecialchars($p['model']); ?>"
                                                data-buy-rate="<?php echo $p['buy_rate']; ?>"
                                                data-price="<?php echo $p['price']; ?>"
                                                data-quantity="<?php echo $p['quantity']; ?>"
                                                title="Edit Product">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirmDelete();" class="inline-block">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow" title="Delete Product">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-emerald-900/40 text-gray-100 font-semibold">
                                    <td class="p-5 rounded-bl-lg" colspan="3">Totals</td>
                                    <td class="p-5"><?php echo number_format($totals['buy_value'], 2); ?></td>
                                    <td class="p-5"><?php echo number_format($totals['sell_value'], 2); ?></td>
                                    <td class="p-5"><?php echo $totals['quantity']; ?></td>
                                    <td class="p-5 rounded-br-lg"></td>
                                </tr>
                                <tr class="bg-emerald-900/40 text-gray-100 font-semibold">
                                    <td class="p-5 rounded-bl-lg" colspan="6">Potential Profit: <?php echo number_format($totals['profit'], 2); ?> ৳</td>
                                    <td class="p-5 rounded-br-lg"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Update Product Modal -->
    <div class="modal-overlay fixed inset-0 flex items-center justify-center z-[1000] hidden" id="modalOverlay">
        <div class="modal p-10 rounded-2xl max-w-lg w-full">
            <button class="close-btn absolute top-6 right-6 text-gray-300 hover:text-gray-100 text-2xl" id="closeModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
                <span>Update Product</span>
            </h3>
            <form method="POST" id="updateProductForm" class="space-y-6">
                <input type="hidden" name="id" id="updateId">
                <div class="relative">
                    <input type="text" name="brand_name" id="updateBrand" placeholder="Brand Name" required minlength="2" maxlength="50" class="w-full p-4 rounded-lg">
                    <i class="fa-solid fa-tag absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="relative">
                    <input type="text" name="product_name" id="updateProduct" placeholder="Product Name" required minlength="2" maxlength="100" class="w-full p-4 rounded-lg">
                    <i class="fa-solid fa-box absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="relative">
                    <input type="number" name="buy_rate" id="updateBuyRate" step="0.01" min="0" placeholder="Buy Rate (৳)" required class="w-full p-4 rounded-lg">
                    <i class="fa-solid fa-money-bill-wave absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="relative">
                    <input type="number" name="price" id="updatePrice" step="0.01" min="0" placeholder="Sell Rate (৳)" required class="w-full p-4 rounded-lg">
                    <i class="fa-solid fa-coins absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="relative">
                    <input type="number" name="quantity" id="updateQuantity" min="0" step="1" placeholder="Quantity" required class="w-full p-4 rounded-lg">
                    <i class="fa-solid fa-cubes absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <button type="submit" name="update" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg w-full transition-all duration-300 shadow-lg flex items-center justify-center">
                    <i class="fa-solid fa-floppy-disk mr-3"></i>Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white bg-gradient-to-r from-emerald-600 to-teal-600 opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white bg-gradient-to-r from-red-600 to-red-700 opacity-0 pointer-events-none z-[1100]"></div>

    <!-- JavaScript for Interactivity -->
    <script>
        const modalOverlay = document.getElementById('modalOverlay');
        const closeModalBtn = document.getElementById('closeModal');
        const successToast = document.getElementById('successToast');
        const errorToast = document.getElementById('errorToast');
        const searchInput = document.getElementById('searchInput');
        const productTable = document.getElementById('productTable');

        // Modal Handling
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modalOverlay.classList.remove('hidden');
                document.getElementById('updateId').value = btn.getAttribute('data-id');
                document.getElementById('updateBrand').value = btn.getAttribute('data-brand');
                document.getElementById('updateProduct').value = btn.getAttribute('data-product');
                document.getElementById('updateBuyRate').value = btn.getAttribute('data-buy-rate');
                document.getElementById('updatePrice').value = btn.getAttribute('data-price');
                document.getElementById('updateQuantity').value = btn.getAttribute('data-quantity');
            });
        });

        closeModalBtn.addEventListener('click', () => {
            modalOverlay.classList.add('hidden');
        });

        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) modalOverlay.classList.add('hidden');
        });

        // Delete Confirmation
        function confirmDelete() {
            return confirm('Are you sure you want to delete this product? This action cannot be undone.');
        }

        // Show Toasts
        <?php if ($successMsg): ?>
            showToast('success', "<?php echo addslashes($successMsg); ?>");
        <?php endif; ?>
        <?php if ($errorMsg && $role !== 'Head of Company'): ?>
            showToast('error', "<?php echo addslashes($errorMsg); ?>");
        <?php endif; ?>
        function showToast(type, message) {
            const toast = type === 'success' ? successToast : errorToast;
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Table Search
        searchInput.addEventListener('input', () => {
            const filter = searchInput.value.toLowerCase();
            const rows = productTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // PDF Export
        document.getElementById('exportPdfBtn').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF("p", "pt", "a4");
            doc.setFont("Inter", "bold");
            doc.setFontSize(26);
            doc.setTextColor(40, 40, 40);
            doc.text("Elite Product Inventory Report", doc.internal.pageSize.getWidth() / 2, 50, { align: "center" });
            doc.setFont("Inter", "normal");
            doc.setFontSize(12);
            doc.setTextColor(100);
            const today = new Date();
            doc.text(`Generated by: <?php echo addslashes($name); ?> on ${today.toLocaleDateString()} ${today.toLocaleTimeString()}`, 
                     doc.internal.pageSize.getWidth() / 2, 70, { align: "center" });
            doc.autoTable({
                startY: 90,
                html: '#productTable',
                styles: {
                    font: "Inter",
                    fontSize: 11,
                    cellPadding: 10,
                    valign: 'middle',
                    lineColor: [200, 200, 200],
                    lineWidth: 0.5,
                },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontSize: 12,
                    fontStyle: 'bold',
                },
                footStyles: {
                    fillColor: [4, 120, 87],
                    textColor: 255,
                    fontSize: 11,
                    fontStyle: 'bold',
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                },
                columnStyles: {
                    6: { cellWidth: 0, cellPadding: 0, fontSize: 0 }
                },
                didDrawCell: function (data) {
                    if (data.column.index === 6) {
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
            doc.save('elite-product-report.pdf');
        });
    </script>
</body>
</html>