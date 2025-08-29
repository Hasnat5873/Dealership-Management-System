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
$selected_shopkeeper_id = isset($_GET['shopkeeper_id']) ? (int)$_GET['shopkeeper_id'] : 0;
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($role === 'User' ? $user_id : 0);
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate inputs
function validate_string($input, $field_name, $min_length = 2, $max_length = 255) {
    $input = trim($input);
    if (empty($input)) {
        return "$field_name is required.";
    }
    if (strlen($input) < $min_length) {
        return "$field_name must be at least $min_length characters.";
    }
    if (strlen($input) > $max_length) {
        return "$field_name cannot exceed $max_length characters.";
    }
    if (!preg_match('/^[a-zA-Z0-9\s\-\,\.\#]+$/', $input)) {
        return "$field_name contains invalid characters.";
    }
    return '';
}

function validate_contact($contact) {
    $contact = trim($contact);
    if (empty($contact)) {
        return "Contact is required.";
    }
    if (!preg_match('/^[\+]?[0-9\s\-]{10,15}$/', $contact)) {
        return "Contact must be a valid phone number (10-15 digits, may include + or -).";
    }
    return '';
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

// Handle form submissions (only for User role)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $role === 'User') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        try {
            if (isset($_POST['add'])) {
                $name_input = trim($_POST['name']);
                $contact = trim($_POST['contact']);
                $address = trim($_POST['address']);

                // Validate inputs
                $name_error = validate_string($name_input, "Name");
                $contact_error = validate_contact($contact);
                $address_error = validate_string($address, "Address", 5, 500);

                if ($name_error || $contact_error || $address_error) {
                    $errorMsg = implode(" ", array_filter([$name_error, $contact_error, $address_error]));
                } else {
                    $stmt = $conn->prepare("INSERT INTO shopkeepers (user_id, name, contact, address) 
                                            VALUES (:user_id, :name, :contact, :address)");
                    $stmt->execute(['user_id' => $user_id, 'name' => $name_input, 'contact' => $contact, 'address' => $address]);
                    $successMsg = "Shopkeeper added successfully!";
                }
            } elseif (isset($_POST['update'])) {
                $id = (int)$_POST['id'];
                $name_input = trim($_POST['name']);
                $contact = trim($_POST['contact']);
                $address = trim($_POST['address']);

                // Validate inputs
                $name_error = validate_string($name_input, "Name");
                $contact_error = validate_contact($contact);
                $address_error = validate_string($address, "Address", 5, 500);

                if ($name_error || $contact_error || $address_error) {
                    $errorMsg = implode(" ", array_filter([$name_error, $contact_error, $address_error]));
                } else {
                    $stmt = $conn->prepare("SELECT user_id FROM shopkeepers WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    if ($stmt->fetchColumn() == $user_id) {
                        $stmt = $conn->prepare("UPDATE shopkeepers SET name = :name, contact = :contact, address = :address 
                                                WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['id' => $id, 'user_id' => $user_id, 'name' => $name_input, 'contact' => $contact, 'address' => $address]);
                        $successMsg = "Shopkeeper updated successfully!";
                    } else {
                        $errorMsg = "You are not authorized to update this shopkeeper.";
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $id = (int)$_POST['id'];
                if ($id <= 0) {
                    $errorMsg = "Invalid shopkeeper ID.";
                } else {
                    $stmt = $conn->prepare("SELECT user_id FROM shopkeepers WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    if ($stmt->fetchColumn() == $user_id) {
                        $stmt = $conn->prepare("DELETE FROM shopkeepers WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                        $successMsg = "Shopkeeper deleted successfully!";
                    } else {
                        $errorMsg = "You are not authorized to delete this shopkeeper.";
                    }
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch shopkeepers with search filter
$fetch_user_id = ($role === 'User') ? $user_id : $selected_user_id;
$shopkeepers = [];
if ($fetch_user_id > 0) {
    try {
        $sql = "SELECT * FROM shopkeepers WHERE user_id = :user_id";
        $params = ['user_id' => $fetch_user_id];
        if ($search_query) {
            $sql .= " AND (name LIKE :search OR contact LIKE :search)";
            $params['search'] = "%$search_query%";
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $shopkeepers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Failed to load shopkeepers: " . htmlspecialchars($e->getMessage());
    }
}

// Fetch transactions and summary for selected shopkeeper
$transactions = [];
$shopkeeper_name = '';
$total_sales = 0;
$cash_total = 0;
$credit_total = 0;
if ($selected_shopkeeper_id > 0 && $fetch_user_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT name FROM shopkeepers WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $selected_shopkeeper_id, 'user_id' => $fetch_user_id]);
        $shopkeeper = $stmt->fetch(PDO::FETCH_ASSOC);
        $shopkeeper_name = $shopkeeper ? htmlspecialchars($shopkeeper['name']) : 'Unknown Shopkeeper';

        $stmt = $conn->prepare("
            SELECT t.*, p.model, s.quantity as sale_quantity, s.amount as sale_amount, s.payment_type 
            FROM transactions t 
            JOIN sales s ON t.sale_id = s.id 
            JOIN products p ON s.product_id = p.id 
            WHERE t.shopkeeper_id = :shopkeeper_id AND t.user_id = :user_id 
            ORDER BY t.transaction_date DESC
        ");
        $stmt->execute(['shopkeeper_id' => $selected_shopkeeper_id, 'user_id' => $fetch_user_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($transactions as $transaction) {
            $total_sales += $transaction['sale_amount'];
            if ($transaction['payment_type'] === 'Cash') {
                $cash_total += $transaction['sale_amount'];
            } elseif ($transaction['payment_type'] === 'Credit') {
                $credit_total += $transaction['sale_amount'];
            }
        }
    } catch (PDOException $e) {
        $errorMsg = "Failed to load transactions: " . htmlspecialchars($e->getMessage());
    }
}
$summary = $cash_total - $credit_total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopkeeper Management - Elite Dashboard</title>
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
        /* Responsive Table */
        .table-container {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .right-panel {
                max-width: 100%;
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
                <i class="fa-solid fa-store text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Shopkeeper Management</h1>
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
                <i class="fa-solid fa-store text-xl"></i>
                <span>Shopkeeper Management</span>
            </h2>
            <?php if ($successMsg): ?>
                <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>
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
                    <form class="search-form mb-6 grid grid-cols-1 md:grid-cols-2 gap-6" method="GET">
                        <div class="form-group">
                            <label for="search" class="text-lg font-semibold gradient-text mb-2">Search Shopkeepers</label>
                            <input type="text" name="search" id="search" placeholder="Search by name or contact" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" />
                            <?php if ($role === 'Head of Company'): ?>
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user_id); ?>" />
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-search mr-3"></i> Search
                        </button>
                    </form>
                    <?php if ($role === 'User'): ?>
                        <form class="add-form mb-6 grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" id="addShopkeeperForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <label for="name" class="text-lg font-semibold gradient-text mb-2">Name</label>
                                <input type="text" name="name" id="name" placeholder="Name" 
                                       class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                            </div>
                            <div class="form-group">
                                <label for="contact" class="text-lg font-semibold gradient-text mb-2">Contact</label>
                                <input type="text" name="contact" id="contact" placeholder="Contact" 
                                       class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                            </div>
                            <div class="form-group">
                                <label for="address" class="text-lg font-semibold gradient-text mb-2">Address</label>
                                <input type="text" name="address" id="address" placeholder="Address" 
                                       class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                            </div>
                            <button type="submit" name="add" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                                <i class="fa-solid fa-plus mr-3"></i> Add Shopkeeper
                            </button>
                        </form>
                    <?php endif; ?>
                    <div class="table-container">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">ID</th>
                                    <th class="p-5">Name</th>
                                    <th class="p-5">Contact</th>
                                    <th class="p-5">Address</th>
                                    <th class="p-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($shopkeepers): ?>
                                    <?php foreach ($shopkeepers as $shopkeeper): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="ID"><?php echo htmlspecialchars($shopkeeper['id']); ?></td>
                                            <td class="p-5" data-label="Name"><?php echo htmlspecialchars($shopkeeper['name']); ?></td>
                                            <td class="p-5" data-label="Contact"><?php echo htmlspecialchars($shopkeeper['contact']); ?></td>
                                            <td class="p-5" data-label="Address"><?php echo htmlspecialchars($shopkeeper['address']); ?></td>
                                            <td class="p-5 action-buttons" data-label="Actions">
                                                <?php if ($role === 'User'): ?>
                                                    <button class="update-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" 
                                                            data-id="<?php echo htmlspecialchars($shopkeeper['id']); ?>" 
                                                            data-name="<?php echo htmlspecialchars($shopkeeper['name']); ?>" 
                                                            data-contact="<?php echo htmlspecialchars($shopkeeper['contact']); ?>" 
                                                            data-address="<?php echo htmlspecialchars($shopkeeper['address']); ?>" 
                                                            title="Update Shopkeeper">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this shopkeeper?');" class="inline-block">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($shopkeeper['id']); ?>">
                                                        <button type="submit" name="delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" title="Delete Shopkeeper">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" 
                                                        onclick="window.location.href='shopkeepers.php?shopkeeper_id=<?php echo htmlspecialchars($shopkeeper['id']); ?><?php echo $role === 'Head of Company' ? '&user_id=' . htmlspecialchars($selected_user_id) : ''; ?>'" 
                                                        title="View Transactions">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-5 text-center">No shopkeepers found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <aside class="right-panel max-w-md">
            <div class="glass-card p-10 rounded-2xl fade-in">
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-money-check-dollar text-xl"></i>
                    <span>Transaction History for <?php echo $shopkeeper_name; ?></span>
                </h2>
                <?php if ($selected_shopkeeper_id > 0): ?>
                    <div class="table-container">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">Product</th>
                                    <th class="p-5">Quantity</th>
                                    <th class="p-5">Amount (৳)</th>
                                    <th class="p-5">Payment Type</th>
                                    <th class="p-5">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="Product"><?php echo htmlspecialchars($transaction['model']); ?></td>
                                            <td class="p-5" data-label="Quantity"><?php echo (int)$transaction['sale_quantity']; ?></td>
                                            <td class="p-5" data-label="Amount (৳)"><?php echo number_format($transaction['sale_amount'], 2); ?></td>
                                            <td class="p-5" data-label="Payment Type">
                                                <span class="payment-type <?php echo strtolower($transaction['payment_type']); ?>">
                                                    <?php echo htmlspecialchars($transaction['payment_type']); ?>
                                                </span>
                                            </td>
                                            <td class="p-5" data-label="Date"><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-5 text-center">No transactions found for this shopkeeper.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <table class="w-full mt-6 text-left">
                        <thead>
                            <tr class="bg-emerald-800/60 text-gray-100">
                                <th class="p-5">Metric</th>
                                <th class="p-5">Amount (৳)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-700/50">
                                <td class="p-5">Total Sales</td>
                                <td class="p-5"><?php echo number_format($total_sales, 2); ?></td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="p-5">Cash</td>
                                <td class="p-5"><?php echo number_format($cash_total, 2); ?></td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="p-5">Credit</td>
                                <td class="p-5"><?php echo number_format($credit_total, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="p-5">Summary (Cash - Credit)</td>
                                <td class="p-5"><?php echo number_format($summary, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-gray-400">Select a shopkeeper to view their transaction history.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <!-- Update Modal -->
    <div class="modal-overlay fixed inset-0 flex items-center justify-center z-[1000] hidden" id="updateModal">
        <div class="modal p-10 rounded-2xl max-w-lg w-full">
            <button class="close-btn absolute top-6 right-6 text-gray-400 hover:text-gray-100 text-3xl" id="closeModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
                <span>Update Shopkeeper</span>
            </h3>
            <form method="POST" id="updateForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" id="updateShopkeeperId">
                <div class="form-group">
                    <label for="updateName" class="text-lg font-semibold gradient-text mb-2">Name</label>
                    <input type="text" name="name" id="updateName" placeholder="Name" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateContact" class="text-lg font-semibold gradient-text mb-2">Contact</label>
                    <input type="text" name="contact" id="updateContact" placeholder="Contact" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateAddress" class="text-lg font-semibold gradient-text mb-2">Address</label>
                    <input type="text" name="address" id="updateAddress" placeholder="Address" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <button type="submit" name="update" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg w-full transition-all duration-300 shadow-lg">
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
        const updateShopkeeperId = document.getElementById('updateShopkeeperId');
        const updateName = document.getElementById('updateName');
        const updateContact = document.getElementById('updateContact');
        const updateAddress = document.getElementById('updateAddress');
        const addShopkeeperForm = document.getElementById('addShopkeeperForm');

        // Modal Handling
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                updateShopkeeperId.value = btn.getAttribute('data-id');
                updateName.value = btn.getAttribute('data-name');
                updateContact.value = btn.getAttribute('data-contact');
                updateAddress.value = btn.getAttribute('data-address');
                modalOverlay.classList.remove('hidden');
            });
        });

        closeModalBtn.addEventListener('click', () => {
            modalOverlay.classList.add('hidden');
        });

        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) modalOverlay.classList.add('hidden');
        });

        // Client-side Validation for Add Shopkeeper Form
        if (addShopkeeperForm) {
            addShopkeeperForm.addEventListener('submit', (e) => {
                const name = document.getElementById('name').value.trim();
                const contact = document.getElementById('contact').value.trim();
                const address = document.getElementById('address').value.trim();

                if (name.length < 2 || name.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Name must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(name)) {
                    e.preventDefault();
                    showToast('error', 'Name contains invalid characters.');
                    return;
                }
                if (!/^[\+]?[0-9\s\-]{10,15}$/.test(contact)) {
                    e.preventDefault();
                    showToast('error', 'Contact must be a valid phone number (10-15 digits, may include + or -).');
                    return;
                }
                if (address.length < 5 || address.length > 500) {
                    e.preventDefault();
                    showToast('error', 'Address must be between 5 and 500 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(address)) {
                    e.preventDefault();
                    showToast('error', 'Address contains invalid characters.');
                    return;
                }
            });
        }

        // Client-side Validation for Update Form
        if (updateForm) {
            updateForm.addEventListener('submit', (e) => {
                const name = updateName.value.trim();
                const contact = updateContact.value.trim();
                const address = updateAddress.value.trim();

                if (name.length < 2 || name.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Name must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(name)) {
                    e.preventDefault();
                    showToast('error', 'Name contains invalid characters.');
                    return;
                }
                if (!/^[\+]?[0-9\s\-]{10,15}$/.test(contact)) {
                    e.preventDefault();
                    showToast('error', 'Contact must be a valid phone number (10-15 digits, may include + or -).');
                    return;
                }
                if (address.length < 5 || address.length > 500) {
                    e.preventDefault();
                    showToast('error', 'Address must be between 5 and 500 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(address)) {
                    e.preventDefault();
                    showToast('error', 'Address contains invalid characters.');
                    return;
                }
            });
        }

        // Show Toasts
        function showToast(type, message) {
            const toast = type === 'success' ? document.getElementById('successToast') : document.getElementById('errorToast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>