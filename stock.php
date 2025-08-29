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
$products = [];
$grand_total_buy = 0;
$grand_total_sale = 0;

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

// Function to fetch products and calculate totals
function fetchProducts($conn, $user_id) {
    global $products, $grand_total_buy, $grand_total_sale;
    try {
        $stmt = $conn->prepare("SELECT id, brand_name, model, buy_rate, price, quantity FROM products WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$p) {
            $p['available_quantity'] = $p['quantity'];
            $grand_total_buy += $p['buy_rate'] * $p['quantity'];
            $grand_total_sale += $p['price'] * $p['quantity'];
        }
    } catch (PDOException $e) {
        return "Database error: " . htmlspecialchars($e->getMessage());
    }
    return '';
}

// Fetch products initially
if ($role === 'User' || $selected_user_id > 0) {
    $errorMsg = $errorMsg ?: fetchProducts($conn, $role === 'User' ? $user_id : $selected_user_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Elite Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
        /* Responsive Table */
        .table-container {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .table-container {
                margin-bottom: 20px;
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
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
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
                <i class="fa-solid fa-warehouse text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Stock Management</h1>
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
            <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-warehouse text-xl"></i>
                <span>Stock Management</span>
            </h2>
            <?php if ($errorMsg): ?>
                <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <div class="glass-card p-10 rounded-2xl fade-in">
                <?php if ($role === 'Head of Company'): ?>
                    <form class="user-filter-form mb-6" method="GET">
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
                <?php if ($role === 'User' || $selected_user_id > 0): ?>
                    <div class="table-container">
                        <h3 class="text-xl font-semibold gradient-text mb-6">Product Stock</h3>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">Brand Name</th>
                                    <th class="p-5">Product Name</th>
                                    <th class="p-5">Buy Rate (৳)</th>
                                    <th class="p-5">Buy Value (৳)</th>
                                    <th class="p-5">Sell Rate (৳)</th>
                                    <th class="p-5">Sale Value (৳)</th>
                                    <th class="p-5">Available Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($products): ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="Brand Name"><?php echo htmlspecialchars($product['brand_name']); ?></td>
                                            <td class="p-5" data-label="Product Name"><?php echo htmlspecialchars($product['model']); ?></td>
                                            <td class="p-5" data-label="Buy Rate (৳)"><?php echo number_format($product['buy_rate'], 2); ?></td>
                                            <td class="p-5" data-label="Buy Value (৳)"><?php echo number_format($product['buy_rate'] * $product['available_quantity'], 2); ?></td>
                                            <td class="p-5" data-label="Sell Rate (৳)"><?php echo number_format($product['price'], 2); ?></td>
                                            <td class="p-5" data-label="Sale Value (৳)"><?php echo number_format($product['price'] * $product['available_quantity'], 2); ?></td>
                                            <td class="p-5" data-label="Available Quantity"><?php echo htmlspecialchars($product['available_quantity']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="p-5 text-center">No products found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-700/50">
                                    <td colspan="5" class="p-5 font-bold gradient-text">Grand Total Buy</td>
                                    <td colspan="2" class="p-5 font-bold gradient-text"><?php echo number_format($grand_total_buy, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="p-5 font-bold gradient-text">Grand Total Sale</td>
                                    <td colspan="2" class="p-5 font-bold gradient-text"><?php echo number_format($grand_total_sale, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Toast -->
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        // Show Toast
        function showToast(type, message) {
            const toast = document.getElementById('errorToast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Display initial error message if present
        <?php if ($errorMsg): ?>
            showToast('error', '<?php echo htmlspecialchars($errorMsg); ?>');
        <?php endif; ?>
    </script>
</body>
</html>