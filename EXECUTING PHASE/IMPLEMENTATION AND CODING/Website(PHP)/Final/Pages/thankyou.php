<?php
// Basic security checks
$success = isset($_GET['success']) && $_GET['success'] === 'true';
$totalPrice = htmlspecialchars($_GET['total_price'] ?? '0.00');
$totalProducts = htmlspecialchars($_GET['total_products'] ?? '0');
$orderId = htmlspecialchars($_GET['order_id'] ?? 'N/A');
$customerId = htmlspecialchars($_GET['customer_id'] ?? 'N/A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Thank You | Cleckfax Trader Hub</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
      color: #fff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      overflow: hidden;
    }

    .thank-you-container {
      text-align: center;
      background: rgba(255, 255, 255, 0.05);
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(10px);
      max-width: 500px;
      width: 90%;
    }

    .checkmark {
      font-size: 60px;
      color: #00ffae;
      animation: popIn 0.6s ease-out forwards;
      transform: scale(0);
    }

    @keyframes popIn {
      to {
        transform: scale(1);
      }
    }

    h1 {
      font-size: 32px;
      margin-bottom: 10px;
    }

    .details {
      margin-top: 20px;
      font-size: 18px;
      line-height: 1.6;
    }

    .btn {
      display: inline-block;
      margin-top: 30px;
      padding: 12px 24px;
      background: #00ffae;
      color: #000;
      border: none;
      border-radius: 30px;
      font-weight: bold;
      text-decoration: none;
      transition: background 0.3s;
    }

    .btn:hover {
      background: #00e0a0;
    }
  </style>
</head>
<body>

<div class="thank-you-container">
  <?php if ($success): ?>
    <div class="checkmark">✔</div>
    <h1>Thank You for Your Purchase!</h1>
    <div class="details">
      <p><strong>Order ID:</strong> <?= $orderId ?></p>
      <p><strong>Total Products:</strong> <?= $totalProducts ?></p>
      <p><strong>Total Paid:</strong> £<?= number_format((float)$totalPrice / 100, 2) ?></p>
      <p><strong>Customer ID:</strong> <?= $customerId ?></p>
    </div>
    <a href="index.php" class="btn">Return to Home</a>
  <?php else: ?>
    <h1>Payment Cancelled</h1>
    <p class="details">You cancelled the payment or something went wrong.</p>
    <a href="index.php" class="btn">Go Back</a>
  <?php endif; ?>
</div>

</body>
</html>
