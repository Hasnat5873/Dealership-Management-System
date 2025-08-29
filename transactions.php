<?php
session_start();
include 'db_connect.php';

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
$transactions = [];

// Fetch transactions (only for User role)
if ($role === 'User') {
    try {
        $stmt = $conn->prepare("
            SELECT t.*, s.quantity, s.amount as sale_amount, s.payment_type, p.model, sk.name as shopkeeper_name 
            FROM transactions t 
            JOIN sales s ON t.sale_id = s.id 
            JOIN products p ON s.product_id = p.id 
            JOIN shopkeepers sk ON t.shopkeeper_id = sk.id 
            WHERE t.user_id = :user_id 
            ORDER BY t.transaction_date DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Failed to fetch transactions: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - Elite Dashboard</title>
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
        /* Table Styling */
        table tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        .payment-type {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-size: 0.9rem;
        }
        .payment-type.cash {
            background-color: #4caf50;
        }
        .payment-type.credit {
            background-color: #ff9800;
        }
        .payment-type.return {
            background-color: #e53935;
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
                <i class="fa-solid fa-money-check-dollar text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Transaction Management</h1>
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
                <i class="fa-solid fa-money-check-dollar text-xl"></i>
                <span>Transaction History</span>
            </h2>
            <?php if ($errorMsg): ?>
                <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <div class="glass-card p-10 rounded-2xl fade-in">
                <?php if ($role === 'Head of Company'): ?>
                    <div class="text-center">
                        <h3 class="text-xl font-semibold gradient-text mb-6 flex items-center justify-center space-x-3">
                            <i class="fa-solid fa-exclamation-triangle text-xl"></i>
                            <span>Access Denied</span>
                        </h3>
                        <p class="text-gray-400">Head of Company users are not authorized to view or manage transaction data.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">ID</th>
                                    <th class="p-5">Shopkeeper</th>
                                    <th class="p-5">Product</th>
                                    <th class="p-5">Quantity</th>
                                    <th class="p-5">Sale Amount (৳)</th>
                                    <th class="p-5">Payment Type</th>
                                    <th class="p-5">Transaction Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="ID"><?php echo htmlspecialchars($transaction['id']); ?></td>
                                            <td class="p-5" data-label="Shopkeeper"><?php echo htmlspecialchars($transaction['shopkeeper_name']); ?></td>
                                            <td class="p-5" data-label="Product"><?php echo htmlspecialchars($transaction['model']); ?></td>
                                            <td class="p-5" data-label="Quantity"><?php echo (int)$transaction['quantity']; ?></td>
                                            <td class="p-5" data-label="Sale Amount (৳)"><?php echo number_format($transaction['sale_amount'], 2); ?></td>
                                            <td class="p-5" data-label="Payment Type">
                                                <span class="payment-type <?php echo strtolower($transaction['payment_type']); ?>">
                                                    <?php echo htmlspecialchars($transaction['payment_type']); ?>
                                                </span>
                                            </td>
                                            <td class="p-5" data-label="Transaction Date"><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="p-5 text-center">No transaction records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
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