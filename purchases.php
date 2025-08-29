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
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($role === 'User' ? $user_id : 0);

$errorMsg = '';
$successMsg = '';

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
    $errorMsg = "Error fetching user details: " . htmlspecialchars($e->getMessage());
}

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

// Handle purchase form submission (only for User role)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_purchase']) && $role === 'User') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $cost = (float)($_POST['cost'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');

    // Enhanced validation
    if ($product_id <= 0) {
        $errorMsg = "Please select a valid product.";
    } elseif (empty($brand) || strlen($brand) > 100) {
        $errorMsg = "Brand is required and must not exceed 100 characters.";
    } elseif ($quantity <= 0 || $quantity > 10000) {
        $errorMsg = "Quantity must be a positive integer and not exceed 10,000.";
    } elseif ($cost < 0 || $cost > 1000000) {
        $errorMsg = "Cost must be a non-negative number and not exceed 1,000,000.";
    } elseif (!in_array($payment_method, ['cash', 'bank'])) {
        $errorMsg = "Invalid payment method selected.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT quantity, user_id, price FROM products WHERE id = :id");
            $stmt->execute(['id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product && $product['user_id'] == $user_id) {
                // Validate cost matches calculated value to prevent tampering
                $expected_cost = $product['price'] * $quantity;
                if (abs($cost - $expected_cost) > 0.01) {
                    $errorMsg = "Cost mismatch detected. Please try again.";
                } else {
                    $new_quantity = $product['quantity'] + $quantity;

                    $conn->beginTransaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO purchases (user_id, product_id, brand, quantity, cost, purchase_date, payment_method) 
                                                VALUES (:user_id, :product_id, :brand, :quantity, :cost, CURDATE(), :payment_method)");
                        $stmt->execute([
                            'user_id' => $user_id,
                            'product_id' => $product_id,
                            'brand' => $brand,
                            'quantity' => $quantity,
                            'cost' => $cost,
                            'payment_method' => $payment_method
                        ]);

                        $stmt = $conn->prepare("UPDATE products SET quantity = :quantity WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['quantity' => $new_quantity, 'id' => $product_id, 'user_id' => $user_id]);

                        $conn->commit();
                        $successMsg = "Purchase recorded successfully!";
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $errorMsg = "Error recording purchase: " . htmlspecialchars($e->getMessage());
                    }
                }
            } else {
                $errorMsg = "Selected product does not exist or you are not authorized.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch products and brands (filtered by user_id for User role)
$products = [];
$brands = [];
try {
    $fetch_user_id = ($role === 'User') ? $user_id : $selected_user_id;
    if ($fetch_user_id > 0) {
        $stmt = $conn->prepare("SELECT id, model, price FROM products WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT DISTINCT brand_name FROM products WHERE user_id = :user_id ORDER BY brand_name");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching products or brands: " . htmlspecialchars($e->getMessage());
}

// Fetch purchase history (filtered by user_id)
$purchases = [];
try {
    $fetch_user_id = ($role === 'User') ? $user_id : $selected_user_id;
    if ($fetch_user_id > 0) {
        $stmt = $conn->prepare("SELECT p.*, pr.model FROM purchases p 
                                JOIN products pr ON p.product_id = pr.id 
                                WHERE p.user_id = :user_id 
                                ORDER BY p.purchase_date DESC");
        $stmt->execute(['user_id' => $fetch_user_id]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching purchase history: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - Elite Dashboard</title>
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
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin-left: 0;
                margin-top: 60px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                margin-bottom: 15px;
                background: rgba(255, 255, 255, 0.08);
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                padding: 15px;
                border-radius: 12px;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
                white-space: normal;
                text-align: left;
            }
            td::before {
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: #d1d5db;
                content: attr(data-label);
            }
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
                <i class="fa-solid fa-cart-plus text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text"> Purchase Management</h1>
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
            <!-- Error and Success Messages -->
            <?php if ($errorMsg): ?>
                <div class="glass-card bg-red-600/20 border-red-600/30 text-red-200 p-6 rounded-2xl mb-10 fade-in" aria-live="assertive">
                    <i class="fa-solid fa-circle-exclamation mr-3 text-xl"></i>
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>
            <?php if ($successMsg): ?>
                <div class="glass-card bg-green-600/20 border-green-600/30 text-green-200 p-6 rounded-2xl mb-10 fade-in" aria-live="polite">
                    <i class="fa-solid fa-check-circle mr-3 text-xl"></i>
                    <?php echo htmlspecialchars($successMsg); ?>
                </div>
            <?php endif; ?>

            <!-- User Filter for Head of Company -->
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

            <!-- Purchase Form for User Role -->
            <?php if ($role === 'User'): ?>
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-cart-plus text-xl"></i>
                    <span>Record a Purchase</span>
                </h2>
                <div class="glass-card p-10 rounded-2xl mb-10 fade-in">
                    <form class="purchase-form grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" novalidate>
                        <div class="form-group">
                            <label for="product_id" class="text-lg font-semibold gradient-text mb-2">Product</label>
                            <select name="product_id" id="product_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['id']); ?>" 
                                            data-price="<?php echo htmlspecialchars($product['price']); ?>">
                                        <?php echo htmlspecialchars($product['model']) . ' (৳' . htmlspecialchars(number_format($product['price'], 2)) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="brand" class="text-lg font-semibold gradient-text mb-2">Brand</label>
                            <select name="brand" id="brand" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Brand</option>
                                <?php foreach ($brands as $brand_item): ?>
                                    <option value="<?php echo htmlspecialchars($brand_item); ?>">
                                        <?php echo htmlspecialchars($brand_item); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity" class="text-lg font-semibold gradient-text mb-2">Quantity</label>
                            <input type="number" name="quantity" id="quantity" placeholder="Quantity" min="1" step="1" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="cost" class="text-lg font-semibold gradient-text mb-2">Cost (৳)</label>
                            <input type="number" name="cost" id="cost" placeholder="Cost (৳)" step="0.01" min="0" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" readonly />
                        </div>
                        <div class="form-group">
                            <label for="payment_method" class="text-lg font-semibold gradient-text mb-2">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Payment Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                        <button type="submit" name="save_purchase" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-floppy-disk mr-3"></i> Save Purchase
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Purchase History -->
            <?php if ($role === 'User' || $selected_user_id > 0): ?>
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                    <span>Purchase History</span>
                </h2>
                <button id="exportPdfBtn" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-8 py-4 rounded-lg mb-6 transition-all duration-300 shadow-lg">
                    <i class="fa-solid fa-file-pdf mr-3"></i> Export PDF
                </button>
                <div class="glass-card p-10 rounded-2xl fade-in">
                    <div class="table-container overflow-x-auto">
                        <table id="purchaseTable" class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">Product</th>
                                    <th class="p-5">Brand</th>
                                    <th class="p-5">Quantity</th>
                                    <th class="p-5">Cost (৳)</th>
                                    <th class="p-5">Payment Method</th>
                                    <th class="p-5">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($purchases): ?>
                                    <?php foreach ($purchases as $purchase): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="Product"><?php echo htmlspecialchars($purchase['model']); ?></td>
                                            <td class="p-5" data-label="Brand"><?php echo htmlspecialchars($purchase['brand']); ?></td>
                                            <td class="p-5" data-label="Quantity"><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                                            <td class="p-5" data-label="Cost (৳)"><?php echo number_format($purchase['cost'], 2); ?></td>
                                            <td class="p-5" data-label="Payment Method"><?php echo htmlspecialchars(ucfirst($purchase['payment_method'])); ?></td>
                                            <td class="p-5" data-label="Date"><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="p-5 text-center">No purchase records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        const productSelect = document.getElementById('product_id');
        const quantityInput = document.getElementById('quantity');
        const costInput = document.getElementById('cost');
        const purchaseForm = document.querySelector('.purchase-form');

        function updateCost() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const unitPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) : 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const totalCost = (unitPrice * quantity).toFixed(2);
            costInput.value = totalCost;
        }

        if (productSelect && quantityInput) {
            productSelect.addEventListener('change', updateCost);
            quantityInput.addEventListener('input', updateCost);
        }

        // Client-side form validation
        if (purchaseForm) {
            purchaseForm.addEventListener('submit', function(e) {
                const productId = productSelect.value;
                const brand = document.getElementById('brand').value;
                const quantity = parseInt(quantityInput.value) || 0;
                const paymentMethod = document.getElementById('payment_method').value;

                if (!productId) {
                    e.preventDefault();
                    showToast('error', 'Please select a product.');
                    return;
                }
                if (!brand) {
                    e.preventDefault();
                    showToast('error', 'Please select a brand.');
                    return;
                }
                if (quantity <= 0) {
                    e.preventDefault();
                    showToast('error', 'Quantity must be a positive integer.');
                    return;
                }
                if (!paymentMethod) {
                    e.preventDefault();
                    showToast('error', 'Please select a payment method.');
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
            doc.text("Purchase History Report", doc.internal.pageSize.getWidth() / 2, 50, { align: "center" });
            doc.setFont("Inter", "normal");
            doc.setFontSize(12);
            doc.setTextColor(100);
            const today = new Date();
            doc.text(`Generated on: ${today.toLocaleDateString()} ${today.toLocaleTimeString()}`, 
                     doc.internal.pageSize.getWidth() / 2, 70, { align: "center" });
            doc.autoTable({
                startY: 90,
                html: '#purchaseTable',
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
            doc.save('elite-purchase-report.pdf');
        });
    </script>
</body>
</html> 