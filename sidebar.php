<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  /* Sidebar styles */
  .sidebar {
      width: 230px;
      height: 100vh;
      background: #1e1e2f;
      color: white;
      position: fixed;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 10px rgba(0,0,0,0.25);
      top: 0;
      left: 0;
      z-index: 1000;
      font-family: 'Roboto', sans-serif;
      transition: background-color 0.3s ease;
  }
  .sidebar h2 {
      padding: 20px;
      text-align: center;
      font-size: 22px;
      font-weight: 700;
      border-bottom: 1px solid rgba(255,255,255,0.15);
      margin: 0;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #00bfa5;
      user-select: none;
  }
  .sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
      flex: 1;
      overflow-y: auto;
  }
  .sidebar ul li a {
      display: flex;
      align-items: center;
      color: #b0bec5;
      padding: 14px 22px;
      text-decoration: none;
      font-size: 16px;
      white-space: nowrap;
      border-left: 4px solid transparent;
      transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-left-color 0.3s ease;
      cursor: pointer;
  }
  .sidebar ul li a:hover {
      background: #009688;
      color: white;
      border-left-color: #00bfa5;
  }
  .sidebar ul li a.active {
      background: #00796b;
      color: white;
      border-left-color: #00bfa5;
      font-weight: 600;
      box-shadow: inset 5px 0 10px rgba(0, 191, 165, 0.5);
  }
  .sidebar ul li a i {
      margin-right: 15px;
      font-size: 18px;
      min-width: 22px;
      text-align: center;
      transition: color 0.3s ease;
  }
  .sidebar ul li a.active i,
  .sidebar ul li a:hover i {
      color: #e0f2f1;
  }

  /* Scrollbar style for sidebar */
  .sidebar ul::-webkit-scrollbar {
      width: 6px;
  }
  .sidebar ul::-webkit-scrollbar-thumb {
      background-color: rgba(0, 191, 165, 0.6);
      border-radius: 3px;
  }

  /* Responsive - make sidebar horizontal on smaller screens */
  @media(max-width: 768px) {
      .sidebar {
          width: 100%;
          height: 60px;
          flex-direction: row;
          box-shadow: 0 2px 10px rgba(0,0,0,0.2);
          overflow-x: auto;
          overflow-y: hidden;
      }
      .sidebar h2 {
          display: none;
      }
      .sidebar ul {
          display: flex;
          flex-direction: row;
          overflow-x: auto;
          overflow-y: hidden;
          flex: none;
          width: 100%;
      }
      .sidebar ul li {
          flex: none;
      }
      .sidebar ul li a {
          padding: 10px 15px;
          font-size: 14px;
          white-space: nowrap;
          border-left: none;
          border-bottom: 3px solid transparent;
      }
      .sidebar ul li a:hover {
          background: transparent;
          color: #00bfa5;
          border-bottom-color: #00bfa5;
          box-shadow: none;
      }
      .sidebar ul li a.active {
          background: transparent;
          color: #00bfa5;
          border-bottom-color: #00bfa5;
          font-weight: 700;
          box-shadow: none;
      }
      .sidebar ul li a i {
          margin-right: 8px;
      }
  }
</style>

<div class="sidebar">
    <h2><i class="fa-solid fa-car-side"></i> DEALERSHIP</h2>
    <ul>
        <?php
        $role = $_SESSION['role'] ?? 'User';
        if ($role === 'User') {
            echo '<li><a href="index.php" class="' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') . '"><i class="fa-solid fa-chart-line"></i> <span>Dashboard</span></a></li>';
            echo '<li><a href="products.php" class="' . (basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '') . '"><i class="fa-solid fa-box-open"></i> <span>Products</span></a></li>';
            echo '<li><a href="shopkeepers.php" class="' . (basename($_SERVER['PHP_SELF']) == 'shopkeepers.php' ? 'active' : '') . '"><i class="fa-solid fa-store"></i> <span>Shopkeepers</span></a></li>';
            echo '<li><a href="sales.php" class="' . (basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '') . '"><i class="fa-solid fa-receipt"></i> <span>Sales</span></a></li>';
            echo '<li><a href="stock.php" class="' . (basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'active' : '') . '"><i class="fa-solid fa-warehouse"></i> <span>Stock</span></a></li>';
            echo '<li><a href="purchases.php" class="' . (basename($_SERVER['PHP_SELF']) == 'purchases.php' ? 'active' : '') . '"><i class="fa-solid fa-cart-plus"></i> <span>Purchases</span></a></li>';
            echo '<li><a href="employees.php" class="' . (basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : '') . '"><i class="fa-solid fa-users"></i> <span>Employees</span></a></li>';
            echo '<li><a href="report.php" class="' . (basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '') . '"><i class="fa-solid fa-chart-simple"></i> <span>Report</span></a></li>';
            echo '<li><a href="expense.php" class="' . (basename($_SERVER['PHP_SELF']) == 'expense.php' ? 'active' : '') . '"><i class="fa-solid fa-money-bill-wave"></i> <span>Expense</span></a></li>';
            echo '<li><a href="income.php" class="' . (basename($_SERVER['PHP_SELF']) == 'income.php' ? 'active' : '') . '"><i class="fa-solid fa-wallet"></i> <span>Income</span></a></li>';
        } elseif ($role === 'Head of Company') {
            echo '<li><a href="index.php" class="' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') . '"><i class="fa-solid fa-chart-line"></i> <span>Dashboard</span></a></li>';
            echo '<li><a href="report.php" class="' . (basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '') . '"><i class="fa-solid fa-chart-simple"></i> <span>Report</span></a></li>';
            echo '<li><a href="stock.php" class="' . (basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'active' : '') . '"><i class="fa-solid fa-warehouse"></i> <span>Stock</span></a></li>';
            echo '<li><a href="shopkeepers.php" class="' . (basename($_SERVER['PHP_SELF']) == 'shopkeepers.php' ? 'active' : '') . '"><i class="fa-solid fa-store"></i> <span>Shopkeepers</span></a></li>';
            echo '<li><a href="sales.php" class="' . (basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '') . '"><i class="fa-solid fa-receipt"></i> <span>Sales</span></a></li>';
            echo '<li><a href="purchases.php" class="' . (basename($_SERVER['PHP_SELF']) == 'purchases.php' ? 'active' : '') . '"><i class="fa-solid fa-cart-plus"></i> <span>Purchases</span></a></li>';
        }
        ?>
        <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
    </ul>
</div>