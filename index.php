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

$successMsg = '';
$errorMsg = '';
$selected_user_id = $user_id; // Default to current user's ID

// Handle user selection for Head of Company
if ($role === 'Head of Company' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        $selected_user_id = (int)$_POST['user_id'];
        // Verify the selected user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = :user_id AND role = 'User'");
        $stmt->execute(['user_id' => $selected_user_id]);
        if (!$stmt->fetch()) {
            $errorMsg = "Invalid user selected.";
            $selected_user_id = $user_id; // Fallback to current user
        }
    }
}

// Fetch users for Head of Company dropdown
$users = [];
if ($role === 'Head of Company') {
    try {
        $stmt = $conn->prepare("SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) AS name 
                                FROM users u 
                                LEFT JOIN user_profiles up ON u.id = up.user_id 
                                WHERE u.role = 'User' 
                                ORDER BY up.first_name, up.last_name");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Error fetching users: " . htmlspecialchars($e->getMessage());
    }
}

// Fetch stock and sales data
try {
    $query = "
        SELECT p.model, p.quantity, 
               IFNULL(SUM(s.amount), 0) as total_sales 
        FROM products p 
        LEFT JOIN sales s ON p.id = s.product_id AND s.user_id = :user_id
        WHERE p.user_id = :user_id
        GROUP BY p.model, p.quantity
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute(['user_id' => $selected_user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_sales = array_sum(array_column($data, 'total_sales'));
    $productModels = array_column($data, 'model');
    $productQty = array_column($data, 'quantity');

    // Economist advice
    $avgSales = count($productModels) ? $total_sales / count($productModels) : 0;
    $advice = "";
    if ($avgSales > 500000) {
        $advice = "Your dealership is performing excellently. Consider expanding inventory and negotiating bulk purchase discounts.";
    } elseif ($avgSales > 100000) {
        $advice = "Sales are steady. Focus on customer loyalty programs and seasonal promotions.";
    } else {
        $advice = "Sales are low. Revisit pricing, boost marketing, and reduce low-performing stock.";
    }

    // Fetch yearly sales
    $yearly_query = "SELECT YEAR(sale_date) as year, SUM(amount) as total 
                     FROM sales 
                     WHERE user_id = :user_id 
                     GROUP BY year 
                     ORDER BY year";
    $stmt = $conn->prepare($yearly_query);
    $stmt->execute(['user_id' => $selected_user_id]);
    $yearly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $years = array_column($yearly_data, 'year');
    $yearly_sales = array_column($yearly_data, 'total');

    // Fetch monthly sales (last 12 months)
    $monthly_query = "SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, SUM(amount) as total 
                      FROM sales 
                      WHERE user_id = :user_id 
                      GROUP BY month 
                      ORDER BY month DESC 
                      LIMIT 12";
    $stmt = $conn->prepare($monthly_query);
    $stmt->execute(['user_id' => $selected_user_id]);
    $monthly_data = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $months = array_column($monthly_data, 'month');
    $monthly_sales = array_column($monthly_data, 'total');

    // Fetch daily sales (last 30 days)
    $daily_query = "SELECT sale_date as day, SUM(amount) as total 
                    FROM sales 
                    WHERE user_id = :user_id 
                    GROUP BY day 
                    ORDER BY day DESC 
                    LIMIT 30";
    $stmt = $conn->prepare($daily_query);
    $stmt->execute(['user_id' => $selected_user_id]);
    $daily_data = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $days = array_column($daily_data, 'day');
    $daily_sales = array_column($daily_data, 'total');
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . htmlspecialchars($e->getMessage());
    $data = $yearly_data = $monthly_data = $daily_data = [];
    $total_sales = 0;
    $productModels = $productQty = $years = $yearly_sales = $months = $monthly_sales = $days = $daily_sales = [];
    $advice = "Unable to generate advice due to data retrieval error.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealership Management System - Elite Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        /* Glassmorphism and Premium Styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
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
        body {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            overflow-x: hidden;
        }
        /* Particle Background */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #1f2937; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(#34d399, #10b981); border-radius: 5px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(#10b981, #059669); }
        /* Custom Chart Styling */
        canvas { transition: all 0.3s ease; }
        canvas:hover { filter: brightness(1.1); }
        /* Form and Select Styling */
        select, input {
            transition: all 0.4s ease;
        }
        select:focus, input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #10b981;
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
    </style>
</head>
<body class="min-h-screen text-gray-100 font-inter">
    <!-- Particle Background -->
    <div id="particles-js"></div>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: '#34d399' },
                shape: { type: 'circle' },
                opacity: { value: 0.3, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: '#34d399', opacity: 0.2, width: 1 },
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
    <header class="bg-gradient-to-r from-emerald-700 to-teal-600 text-white py-6 px-6 shadow-2xl">
        <div class="container mx-auto flex items-center justify-between pl-64">
            <div class="flex items-center space-x-4">
                <i class="fa-solid fa-car-side text-4xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text"> Dealership Dashboard</h1>
            </div>
            <div class="text-right">
                <p class="text-xl font-semibold"><?php echo htmlspecialchars($name); ?></p>
                <p class="text-sm opacity-80"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto mt-8 px-4 flex">
        <!-- Sidebar (Assuming sidebar.php exists) -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Dashboard Content -->
        <main class="flex-1 ml-64 p-8" role="main">
            <?php if ($errorMsg): ?>
                <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <!-- User Selection for Head of Company -->
            <?php if ($role === 'Head of Company'): ?>
                <div class="mb-8 fade-in">
                    <form method="POST" class="flex items-center space-x-4" id="userSelectForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <select name="user_id" required class="glass-card text-gray-100 border-none rounded-xl p-4 focus:ring-2 focus:ring-emerald-500 w-64">
                            <option value="" class="text-gray-400">Select a User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                                        <?php echo $selected_user_id == $user['id'] ? 'selected' : ''; ?>
                                        class="text-gray-100 bg-gray-800">
                                    <?php echo htmlspecialchars($user['name'] ?: 'User ID: ' . $user['id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="select_user" 
                                class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-6 py-3 rounded-xl transition-all duration-300 shadow-lg">
                            View Data
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Dashboard Overview -->
            <h2 class="text-2xl font-bold mb-8 pl-64 flex items-center space-x-3 gradient-text fade-in">
                <i class="fa-solid fa-chart-line text-xl"></i>
                <span>Dashboard Overview</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-semibold text-gray-200 mb-4">Total Sales</h3>
                    <p id="totalSales" class="text-4xl font-bold gradient-text text-center">৳0</p>
                    <script>
                        const totalSalesValue = <?php echo json_encode($total_sales); ?>;
                        const totalSalesElement = document.getElementById('totalSales');
                        let count = 0;
                        const stepTime = 20;
                        const timer = setInterval(() => {
                            if (count < totalSalesValue) {
                                count += totalSalesValue / 100;
                                if (count > totalSalesValue) count = totalSalesValue;
                                totalSalesElement.textContent = '৳' + count.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            } else {
                                clearInterval(timer);
                            }
                        }, stepTime);
                    </script>
                </div>
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-semibold text-gray-200 mb-4">Economist Advice</h3>
                    <p class="text-gray-300 text-center leading-relaxed"><?php echo htmlspecialchars($advice); ?></p>
                </div>
            </div>

            <!-- Sales Analytics -->
            <h2 class="text-2xl font-bold mb-8 pl-64 flex items-center space-x-3 gradient-text fade-in">
                <i class="fa-solid fa-chart-bar text-xl"></i>
                <span>Sales Analytics</span>
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-medium text-gray-200 mb-4">Stock Quantity</h3>
                    <canvas id="stockChart"></canvas>
                </div>
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-medium text-gray-200 mb-4">Stock Distribution</h3>
                    <canvas id="stockPieChart"></canvas>
                </div>
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-medium text-gray-200 mb-4">Yearly Sales</h3>
                    <canvas id="yearlySalesChart"></canvas>
                </div>
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-medium text-gray-200 mb-4">Monthly Sales (Last 12 Months)</h3>
                    <canvas id="monthlySalesChart"></canvas>
                </div>
                <div class="glass-card p-8 rounded-xl fade-in">
                    <h3 class="text-lg font-medium text-gray-200 mb-4">Daily Sales (Last 30 Days)</h3>
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <!-- Chart.js Scripts with Enhanced Styling -->
    <script>
        Chart.register(ChartDataLabels);

        // Common Chart Options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: '#d1d5db', font: { size: 14, family: 'Inter' } } },
                datalabels: { color: '#d1d5db', font: { size: 12, family: 'Inter' } }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)', borderColor: 'rgba(255, 255, 255, 0.2)' },
                    ticks: { color: '#d1d5db', font: { family: 'Inter' } }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#d1d5db', font: { family: 'Inter' } }
                }
            }
        };

        // Stock Bar Chart
        new Chart(document.getElementById('stockChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($productModels); ?>,
                datasets: [{
                    label: 'Stock Quantity',
                    data: <?php echo json_encode($productQty); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.4)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    datalabels: { anchor: 'end', align: 'top', formatter: Math.round }
                }
            }
        });

        // Stock Pie Chart
        new Chart(document.getElementById('stockPieChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($productModels); ?>,
                datasets: [{
                    label: 'Stock Share',
                    data: <?php echo json_encode($productQty); ?>,
                    backgroundColor: [
                        '#10b981', '#34d399', '#6ee7b7', '#a7f3d0', '#d1fae5',
                        '#e5e7eb', '#9ca3af', '#6b7280', '#4b5563', '#374151'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: { position: 'bottom' },
                    datalabels: { formatter: (value, ctx) => ctx.chart.data.labels[ctx.dataIndex] }
                }
            }
        });

        // Yearly Sales Bar Chart
        new Chart(document.getElementById('yearlySalesChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($years); ?>,
                datasets: [{
                    label: 'Yearly Sales (৳)',
                    data: <?php echo json_encode($yearly_sales); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.4)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    datalabels: { anchor: 'end', align: 'top', formatter: (value) => '৳' + value.toLocaleString() }
                }
            }
        });

        // Monthly Sales Line Chart
        const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
        const monthlyGradient = monthlyCtx.createLinearGradient(0, 0, 0, 400);
        monthlyGradient.addColorStop(0, 'rgba(16, 185, 129, 0.6)');
        monthlyGradient.addColorStop(1, 'rgba(16, 185, 129, 0.1)');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Monthly Sales (৳)',
                    data: <?php echo json_encode($monthly_sales); ?>,
                    fill: true,
                    backgroundColor: monthlyGradient,
                    borderColor: '#10b981',
                    borderWidth: 3,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    datalabels: { display: false }
                }
            }
        });

        // Daily Sales Line Chart
        const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
        const dailyGradient = dailyCtx.createLinearGradient(0, 0, 0, 400);
        dailyGradient.addColorStop(0, 'rgba(16, 185, 129, 0.6)');
        dailyGradient.addColorStop(1, 'rgba(16, 185, 129, 0.1)');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($days); ?>,
                datasets: [{
                    label: 'Daily Sales (৳)',
                    data: <?php echo json_encode($daily_sales); ?>,
                    fill: true,
                    backgroundColor: dailyGradient,
                    borderColor: '#10b981',
                    borderWidth: 3,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    datalabels: { display: false }
                }
            }
        });

        // Client-side Validation for User Selection Form
        const userSelectForm = document.getElementById('userSelectForm');
        if (userSelectForm) {
            userSelectForm.addEventListener('submit', (e) => {
                const userId = userSelectForm.querySelector('select[name="user_id"]').value;
                if (!userId) {
                    e.preventDefault();
                    showToast('error', 'Please select a valid user.');
                }
            });
        }

        // Show Toast
        function showToast(type, message) {
            const toast = document.getElementById('errorToast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>