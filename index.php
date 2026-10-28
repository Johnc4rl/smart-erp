<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart ERP Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-body">

    <div class="login-container">
        <div class="left-panel">
            <h1>Smart Enterprise Resource Planning</h1>
            <p>Smart solutions for Sales, Inventory & Payroll</p>
        </div>

        <div class="right-panel">
            <form action="auth/login.php" method="POST">
                <h2>Welcome!</h2>
                <p>Please sign in to continue</p>

                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" role="alert">
                        <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <input type="text" name="username" placeholder="Employee Number or Username" required autofocus autocomplete="username">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">

                <button type="submit">LOGIN</button>
            </form>
        </div>
    </div>

</body>

</html>
