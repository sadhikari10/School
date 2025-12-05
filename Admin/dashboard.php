<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'];

// Fetch counts
$total_schools = $pdo->prepare("SELECT COUNT(*) FROM schools WHERE outlet_id = ?");
$total_schools->execute([$outlet_id]);
$total_schools = $total_schools->fetchColumn();

$total_items = $pdo->prepare("SELECT COUNT(*) FROM items WHERE outlet_id = ?");
$total_items->execute([$outlet_id]);
$total_items = $total_items->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as brand_count FROM brands WHERE outlet_id = ?");
$stmt->execute([$outlet_id]);
$brandCount = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?php echo htmlspecialchars($outlet_name); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#8e44ad,#9b59b6,#a569bd);
    min-height:100vh;
    padding:40px 20px;
    color:#2c3e50;
    display:flex;
    align-items:center;
    justify-content:center;
}
.container{max-width:1300px;margin:0 auto;width:100%;}

.header{
    text-align:center;
    margin-bottom:60px;
}
.header h1{
    font-size:2.2rem;
    color:white;
    margin-bottom:12px;
    text-shadow:0 4px 15px rgba(0,0,0,0.3);
}
.outlet-badge{
    background:rgba(255,255,255,0.95);
    color:#8e44ad;
    padding:8px 30px;
    border-radius:50px;
    font-weight:700;
    font-size:1rem;
    display:inline-block;
    box-shadow:0 12px 35px rgba(0,0,0,0.2);
}

/* Dashboard Cards */
.dashboard{
    display:flex;
    gap:25px;
    justify-content:center;
    flex-wrap:wrap;
}
.card{
    background:white;
    width:260px;
    height:220px;
    border-radius:26px;
    box-shadow:0 20px 50px rgba(0,0,0,0.22);
    text-align:center;
    padding:25px 20px;
    transition:all 0.4s ease;
    text-decoration:none;
    color:#2c3e50;
    position:relative;
    overflow:hidden;
}
.card::before{
    content:'';
    position:absolute;
    top:0;left:0;right:0;bottom:0;
    background:linear-gradient(135deg,rgba(142,68,173,0.08),rgba(155,89,182,0.08));
    opacity:0;
    transition:0.4s;
}
.card:hover{
    transform:translateY(-12px);
    box-shadow:0 25px 60px rgba(0,0,0,0.3);
}
.card:hover::before{opacity:1;}

.card i{
    font-size:3.2rem;
    margin-bottom:15px;
    background:linear-gradient(135deg,#8e44ad,#9b59b6);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}
.card h3{
    font-size:2rem;
    font-weight:700;
    margin:10px 0;
    color:#2c3e50;
}
.card p{
    font-size:1rem;
    font-weight:600;
    color:#7f8c8d;
    margin-top:5px;
}

/* Smaller text for report cards */
.dashboard .card.report-card h3 {
    font-size:1.4rem;
}
.dashboard .card.report-card p {
    font-size:0.8rem;
}
.dashboard .card.report-card i {
    font-size:2.5rem;
}

/* Footer Links */
.footer-links{
    text-align:center;
    margin-top:50px;
}
.footer-links a{
    color:white;
    background:rgba(255,255,255,0.2);
    padding:12px 35px;
    border-radius:50px;
    text-decoration:none;
    font-weight:600;
    font-size:1rem;
    margin:0 10px;
    backdrop-filter:blur(10px);
    transition:0.3s;
}
.footer-links a:hover{
    background:rgba(255,255,255,0.3);
    transform:scale(1.05);
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Admin Dashboard</h1>
        <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
    </div>

    <div class="dashboard">
        <!-- Total Schools -->
        <a href="schools.php" class="card">
            <i class="fas fa-school"></i>
            <h3><?php echo $total_schools; ?></h3>
            <p>Total Schools</p>
        </a>

        <!-- Total Items -->
        <a href="school_items.php" class="card">
            <i class="fas fa-tshirt"></i>
            <h3><?php echo $total_items; ?></h3>
            <p>Total Items</p>
        </a>

        <!-- Brand Items -->
        <a href="brand.php" class="card">
            <i class="fas fa-tags"></i>
            <h3><?php echo $brandCount; ?></h3>
            <p>Brand Items</p>
        </a>

        <!-- Sales -->
        <a href="sales.php" class="card report-card">
            <i class="fas fa-dollar-sign"></i>
            <h3>Sales</h3>
            <p>View Sales</p>
        </a>

        <!-- Advance Payment -->
        <a href="advance_payment.php" class="card report-card">
            <i class="fas fa-credit-card"></i>
            <h3>Advance Payment</h3>
            <p>View Payments</p>
        </a>

        <!-- Order Cancel -->
        <a href="order_cancel.php" class="card report-card">
            <i class="fas fa-ban"></i>
            <h3>Order Cancel</h3>
            <p>View Cancelled Orders</p>
        </a>

        <!-- Order Return -->
        <a href="order_return.php" class="card report-card">
            <i class="fas fa-undo-alt"></i>
            <h3>Order Return</h3>
            <p>View Returned Orders</p>
        </a>
    </div>

    <div class="footer-links">
        <a href="index.php">Change Outlet</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
</body>
</html>
