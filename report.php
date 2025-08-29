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
    $errorMsg = "Error fetching user details: " . htmlspecialchars($e->getMessage());
}

$report_type = '';
$start_date = $end_date = $selected_date = $selected_month = $selected_year = '';
$total_sales = $total_purchases = $total_income = $profit = 0;
$sales_transactions = [];
$purchase_transactions = [];
$successMsg = '';
$errorMsg = '';
$debugMsg = '';
$selected_user_id = null; // Null means all users; specific ID for individual user
$selected_user_name = 'All Users'; // Default for Head of Company

// Fetch users for Head of Company dropdown
$users = [];
if ($role === 'Head of Company') {
    try {
        $stmt = $conn->prepare("SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) AS name 
                                FROM users u 
                                LEFT JOIN user_profiles up ON u.id = up.user_id 
                                WHERE u.id != :current_user_id 
                                ORDER BY up.first_name, up.last_name");
        $stmt->execute(['current_user_id' => $user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($users)) {
            $errorMsg = "No users available to select. Please add users to the system.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Error fetching users: " . htmlspecialchars($e->getMessage());
    }
}

// Handle user selection for Head of Company
if ($role === 'Head of Company' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        $user_id_input = $_POST['user_id'] ?? '';
        if ($user_id_input === 'all') {
            $selected_user_id = null;
            $selected_user_name = 'All Users';
        } else {
            $selected_user_id = (int)$user_id_input;
            // Verify the selected user exists
            try {
                $stmt = $conn->prepare("SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) AS name 
                                        FROM users u 
                                        LEFT JOIN user_profiles up ON u.id = up.user_id 
                                        WHERE u.id = :user_id");
                $stmt->execute(['user_id' => $selected_user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $selected_user_name = $user['name'] ?: 'User ID: ' . $selected_user_id;
                } else {
                    $errorMsg = "Selected user (ID: $selected_user_id) does not exist.";
                    $selected_user_id = null;
                    $selected_user_name = 'All Users';
                }
            } catch (PDOException $e) {
                $errorMsg = "Error verifying user: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        try {
            $report_type = $_POST['report_type'] ?? '';
            if (!in_array($report_type, ['daily', 'monthly', 'yearly'])) {
                $errorMsg = "Invalid report type selected.";
            } else {
                // Validate date inputs
                if ($report_type == 'daily') {
                    $selected_date = $_POST['selected_date'] ?? '';
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || !strtotime($selected_date)) {
                        $errorMsg = "Invalid date format for daily report.";
                    } else {
                        $where_clause = "WHERE DATE(s.sale_date) = :date";
                        $params = ['date' => $selected_date];
                        $date_range_msg = "Date: $selected_date";
                    }
                } elseif ($report_type == 'monthly') {
                    $selected_month = $_POST['month'] ?? '';
                    $selected_year = $_POST['year'] ?? '';
                    if (!preg_match('/^\d{2}$/', $selected_month) || !preg_match('/^\d{4}$/', $selected_year) ||
                        $selected_month < 1 || $selected_month > 12 || $selected_year < 1900 || $selected_year > date('Y') + 5) {
                        $errorMsg = "Invalid month or year selected.";
                    } else {
                        $start_date = "$selected_year-$selected_month-01";
                        $end_date = date('Y-m-t', strtotime($start_date));
                        $where_clause = "WHERE s.sale_date BETWEEN :start AND :end";
                        $params = ['start' => $start_date, 'end' => $end_date];
                        $date_range_msg = "Month: $selected_year-$selected_month";
                    }
                } elseif ($report_type == 'yearly') {
                    $start_date = $_POST['start_date'] ?? '';
                    $end_date = $_POST['end_date'] ?? '';
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) ||
                        !strtotime($start_date) || !strtotime($end_date) || strtotime($start_date) > strtotime($end_date)) {
                        $errorMsg = "Invalid date range for yearly report.";
                    } else {
                        $where_clause = "WHERE s.sale_date BETWEEN :start AND :end";
                        $params = ['start' => $start_date, 'end' => $end_date];
                        $date_range_msg = "From $start_date to $end_date";
                    }
                }

                if (empty($errorMsg)) {
                    // Add user_id filter if a specific user is selected
                    if ($selected_user_id !== null) {
                        $where_clause .= " AND s.user_id = :user_id";
                        $params['user_id'] = $selected_user_id;
                        // Validate user existence
                        try {
                            $stmt = $conn->prepare("SELECT id FROM users WHERE id = :user_id");
                            $stmt->execute(['user_id' => $selected_user_id]);
                            if (!$stmt->fetch() && $role === 'Head of Company') {
                                $errorMsg = "Selected user (ID: $selected_user_id) does not exist.";
                                $report_type = '';
                            }
                        } catch (PDOException $e) {
                            $errorMsg = "Error verifying user: " . htmlspecialchars($e->getMessage());
                        }
                    }

                    if (empty($errorMsg)) {
                        // Debug: Log selected user and date range
                        $debugMsg = "Generating report for " . ($selected_user_id === null ? "all users" : "user_id: $selected_user_id ($selected_user_name)") . "; $date_range_msg";

                        // Fetch Sales Transactions with Product Names
                        $stmt = $conn->prepare("SELECT s.id, p.model AS product_name, s.amount, s.sale_date 
                                               FROM sales s 
                                               JOIN products p ON s.product_id = p.id 
                                               $where_clause 
                                               ORDER BY s.sale_date DESC");
                        $stmt->execute($params);
                        $sales_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $total_sales = array_sum(array_map(function($t) { return floatval($t['amount']); }, $sales_transactions));
                        $debugMsg .= "; Sales records: " . count($sales_transactions);

                        // Fetch Purchase Transactions with Product Names
                        $purchase_where = str_replace('s.sale_date', 'p.purchase_date', $where_clause);
                        $purchase_where = str_replace('s.user_id', 'p.user_id', $purchase_where);
                        $stmt = $conn->prepare("SELECT p.id, pr.model AS product_name, p.quantity, p.cost, p.purchase_date 
                                               FROM purchases p 
                                               JOIN products pr ON p.product_id = pr.id 
                                               $purchase_where 
                                               ORDER BY p.purchase_date DESC");
                        $stmt->execute($params);
                        $purchase_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $total_purchases = array_sum(array_map(function($t) { return floatval($t['quantity']) * floatval($t['cost']); }, $purchase_transactions));
                        $debugMsg .= "; Purchase records: " . count($purchase_transactions);

                        // Total General Income
                        $income_where = str_replace('s.sale_date', 'gi.income_date', $where_clause);
                        $income_where = str_replace('s.user_id', 'gi.user_id', $income_where);
                        $stmt = $conn->prepare("SELECT SUM(amount) AS total 
                                               FROM general_incomes gi 
                                               $income_where");
                        $stmt->execute($params);
                        $total_income = floatval($stmt->fetchColumn() ?: 0);
                        $debugMsg .= "; Total income: $total_income";

                        // Profit: (Total Sales + Total General Income) - Total Purchases
                        $profit = ($total_sales + $total_income) - $total_purchases;

                        if (empty($sales_transactions) && empty($purchase_transactions) && $total_income == 0) {
                            $errorMsg = "No data found for " . ($selected_user_id === null ? "any users" : "user '$selected_user_name' (ID: $selected_user_id)") . " in the selected date range ($date_range_msg).";
                        } else {
                            $successMsg = "Report generated successfully for " . htmlspecialchars($selected_user_name) . "!";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Error generating report: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Financial Report - Elite Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
            .container { margin-left: 0; margin-top: 60px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
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
                <i class="fa-solid fa-chart-simple text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Comprehensive Financial Report</h1>
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
            <!-- Messages -->
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
            <?php if ($debugMsg && $role === 'Head of Company'): ?>
                <div class="glass-card bg-blue-600/20 border-blue-600/30 text-blue-200 p-6 rounded-2xl mb-10 fade-in" aria-live="polite">
                    <i class="fa-solid fa-info-circle mr-3 text-xl"></i>
                    <?php echo htmlspecialchars($debugMsg); ?>
                </div>
            <?php endif; ?>

            <!-- User Selection for Head of Company -->
            <?php if ($role === 'Head of Company'): ?>
                <div class="glass-card p-6 rounded-2xl mb-10 fade-in">
                    <form method="POST" class="flex flex-col md:flex-row items-center gap-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="flex-1">
                            <label for="user_id" class="text-lg font-semibold gradient-text mb-2">Select User</label>
                            <select name="user_id" id="user_id" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 w-full" required>
                                <option value="all" <?php echo $selected_user_id === null ? 'selected' : ''; ?>>All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                                            <?php echo $selected_user_id == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name'] ?: 'User ID: ' . $user['id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="select_user" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-6 py-4 rounded-lg transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-user-check mr-3"></i> View Data
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Report Generator -->
            <div class="glass-card p-10 rounded-2xl mb-10 fade-in">
                <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                    <i class="fa-solid fa-chart-line text-xl"></i>
                    <span>Detailed Financial Report Generator</span>
                </h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6" id="reportForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="report_type" class="text-lg font-semibold gradient-text mb-2">Report Type</label>
                        <select name="report_type" id="report_type" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required onchange="showDateInputs()">
                            <option value="" disabled selected>Select Report Type</option>
                            <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    <div id="daily_input" class="form-group date-input hidden">
                        <label for="selected_date" class="text-lg font-semibold gradient-text mb-2">Select Date</label>
                        <input type="date" name="selected_date" id="selected_date" value="<?php echo htmlspecialchars($selected_date ?: date('Y-m-d')); ?>" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" />
                    </div>
                    <div id="monthly_input" class="form-group date-input hidden">
                        <label for="month" class="text-lg font-semibold gradient-text mb-2">Select Month</label>
                        <div class="flex gap-4">
                            <select name="month" id="month" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 flex-1">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" 
                                            <?php echo $selected_month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0,0,0,$m)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" id="year" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 flex-1">
                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?php echo $y; ?>" 
                                            <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="yearly_input" class="form-group date-input hidden">
                        <label for="start_date" class="text-lg font-semibold gradient-text mb-2">Date Range</label>
                        <div class="flex gap-4">
                            <input type="date" name="start_date" id="start_date" placeholder="From Date" 
                                   value="<?php echo htmlspecialchars($start_date ?: date('Y-m-d', strtotime('first day of january this year'))); ?>" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 flex-1" />
                            <input type="date" name="end_date" id="end_date" placeholder="To Date" 
                                   value="<?php echo htmlspecialchars($end_date ?: date('Y-m-d')); ?>" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 flex-1" />
                        </div>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                        <i class="fa-solid fa-chart-bar mr-3"></i> Generate Detailed Report
                    </button>
                </form>
            </div>

            <!-- Report Output -->
            <?php if ($report_type): ?>
                <div class="glass-card p-10 rounded-2xl fade-in">
                    <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                        <i class="fa-solid fa-file-alt text-xl"></i>
                        <span><?php echo ucfirst($report_type); ?> Transaction Report - <?php echo date('h:i A, d M Y'); ?></span>
                    </h2>
                    <?php if (!empty($sales_transactions)): ?>
                        <div class="flex gap-4 mb-6">
                            <button id="exportPdfBtn" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-6 py-4 rounded-lg transition-all duration-300 shadow-lg">
                                <i class="fa-solid fa-file-pdf mr-3"></i> Export Sales to PDF
                            </button>
                            <button id="exportExcelBtn" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-4 rounded-lg transition-all duration-300 shadow-lg">
                                <i class="fa-solid fa-file-excel mr-3"></i> Export Sales to Excel
                            </button>
                        </div>
                    <?php endif; ?>
                    <!-- Sales Transactions -->
                    <?php if (!empty($sales_transactions)): ?>
                        <div class="glass-card p-6 rounded-2xl mb-6">
                            <h3 class="text-xl font-semibold gradient-text mb-4 flex items-center space-x-3">
                                <i class="fa-solid fa-money-bill-wave text-lg"></i>
                                <span>Sales Transactions</span>
                            </h3>
                            <div class="table-container overflow-x-auto">
                                <table id="salesTable" class="w-full text-left">
                                    <thead>
                                        <tr class="bg-emerald-800/60 text-gray-100">
                                            <th class="p-5">Transaction ID</th>
                                            <th class="p-5">Product Name</th>
                                            <th class="p-5">Sale Amount (৳)</th>
                                            <th class="p-5">Sale Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_transactions as $sale): ?>
                                            <tr class="border-b border-gray-700/50">
                                                <td class="p-5" data-label="Transaction ID"><?php echo htmlspecialchars($sale['id']); ?></td>
                                                <td class="p-5" data-label="Product Name"><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                                <td class="p-5" data-label="Sale Amount (৳)"><?php echo number_format($sale['amount'], 2); ?></td>
                                                <td class="p-5" data-label="Sale Date"><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- Purchase Transactions -->
                    <?php if (!empty($purchase_transactions)): ?>
                        <div class="glass-card p-6 rounded-2xl mb-6">
                            <h3 class="text-xl font-semibold gradient-text mb-4 flex items-center space-x-3">
                                <i class="fa-solid fa-cart-shopping text-lg"></i>
                                <span>Purchase Transactions</span>
                            </h3>
                            <div class="table-container overflow-x-auto">
                                <table id="purchaseTable" class="w-full text-left">
                                    <thead>
                                        <tr class="bg-emerald-800/60 text-gray-100">
                                            <th class="p-5">Transaction ID</th>
                                            <th class="p-5">Product Name</th>
                                            <th class="p-5">Quantity</th>
                                            <th class="p-5">Cost (৳)</th>
                                            <th class="p-5">Total Cost (৳)</th>
                                            <th class="p-5">Purchase Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_transactions as $purchase): ?>
                                            <tr class="border-b border-gray-700/50">
                                                <td class="p-5" data-label="Transaction ID"><?php echo htmlspecialchars($purchase['id']); ?></td>
                                                <td class="p-5" data-label="Product Name"><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                                                <td class="p-5" data-label="Quantity"><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                                                <td class="p-5" data-label="Cost (৳)"><?php echo number_format($purchase['cost'], 2); ?></td>
                                                <td class="p-5" data-label="Total Cost (৳)"><?php echo number_format($purchase['quantity'] * $purchase['cost'], 2); ?></td>
                                                <td class="p-5" data-label="Purchase Date"><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- Financial Summary -->
                    <div class="glass-card p-6 rounded-2xl">
                        <h3 class="text-xl font-semibold gradient-text mb-4 flex items-center space-x-3">
                            <i class="fa-solid fa-calculator text-lg"></i>
                            <span>Financial Summary</span>
                        </h3>
                        <div class="table-container overflow-x-auto">
                            <table id="summaryTable" class="w-full text-left">
                                <thead>
                                    <tr class="bg-emerald-800/60 text-gray-100">
                                        <th class="p-5">Metric</th>
                                        <th class="p-5">Value (৳)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-700/50">
                                        <td class="p-5" data-label="Metric">Total Sales</td>
                                        <td class="p-5" data-label="Value (৳)"><?php echo number_format($total_sales, 2); ?></td>
                                    </tr>
                                    <tr class="border-b border-gray-700/50">
                                        <td class="p-5" data-label="Metric">Total Purchases</td>
                                        <td class="p-5" data-label="Value (৳)"><?php echo number_format($total_purchases, 2); ?></td>
                                    </tr>
                                    <tr class="border-b border-gray-700/50">
                                        <td class="p-5" data-label="Metric">Total General Income</td>
                                        <td class="p-5" data-label="Value (৳)"><?php echo number_format($total_income, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="p-5" data-label="Metric">Profit</td>
                                        <td class="p-5 text-green-400 font-semibold" data-label="Value (৳)"><?php echo number_format($profit, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        // Date Input Visibility
        function showDateInputs() {
            const type = document.getElementById('report_type').value;
            document.getElementById('daily_input').classList.add('hidden');
            document.getElementById('monthly_input').classList.add('hidden');
            document.getElementById('yearly_input').classList.add('hidden');
            if (type === 'daily') document.getElementById('daily_input').classList.remove('hidden');
            if (type === 'monthly') document.getElementById('monthly_input').classList.remove('hidden');
            if (type === 'yearly') document.getElementById('yearly_input').classList.remove('hidden');
        }
        window.onload = showDateInputs;

        // Client-side Form Validation
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                const reportType = document.getElementById('report_type').value;
                if (!reportType) {
                    e.preventDefault();
                    showToast('error', 'Please select a report type.');
                    return;
                }
                if (reportType === 'daily') {
                    const selectedDate = document.getElementById('selected_date').value;
                    if (!selectedDate || !/^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
                        e.preventDefault();
                        showToast('error', 'Please select a valid date.');
                        return;
                    }
                } else if (reportType === 'monthly') {
                    const month = document.getElementById('month').value;
                    const year = document.getElementById('year').value;
                    if (!month || !year || !/^\d{2}$/.test(month) || !/^\d{4}$/.test(year) || 
                        month < 1 || month > 12 || year < 1900 || year > new Date().getFullYear() + 5) {
                        e.preventDefault();
                        showToast('error', 'Please select a valid month and year.');
                        return;
                    }
                } else if (reportType === 'yearly') {
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    if (!startDate || !endDate || !/^\d{4}-\d{2}-\d{2}$/.test(startDate) || 
                        !/^\d{4}-\d{2}-\d{2}$/.test(endDate) || new Date(startDate) > new Date(endDate)) {
                        e.preventDefault();
                        showToast('error', 'Please select a valid date range.');
                        return;
                    }
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
        document.getElementById('exportPdfBtn')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            doc.setFont('Inter', 'bold');
            doc.setFontSize(18);
            doc.setTextColor(40, 40, 40);
            doc.text("Sales Transactions Report", 14, 20);
            doc.setFont('Inter', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(100);
            const now = new Date();
            doc.text(`Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`, 14, 28);
            doc.autoTable({
                startY: 35,
                html: '#salesTable',
                styles: {
                    font: 'Inter',
                    fontSize: 10,
                    cellPadding: 6,
                    halign: 'center',
                    lineColor: [200, 200, 200],
                    lineWidth: 0.5
                },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontSize: 11,
                    fontStyle: 'bold',
                    halign: 'center'
                },
                alternateRowStyles: { fillColor: [240, 240, 240] },
                margin: { left: 14, right: 14 }
            });
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(9);
                doc.setTextColor(120);
                doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.width - 30, doc.internal.pageSize.height - 10);
                doc.text("Generated by Elite Dashboard", 14, doc.internal.pageSize.height - 10);
            }
            doc.save('sales-transactions-report.pdf');
        });

        // Excel Export
        document.getElementById('exportExcelBtn')?.addEventListener('click', () => {
            const table = document.getElementById('salesTable');
            if (!table) return showToast('error', 'Sales Transactions table not found!');
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, 'Sales Transactions');
            XLSX.writeFile(wb, 'sales-transactions-report.xlsx');
        });
    </script>
</body>
</html>