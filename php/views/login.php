<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="pt-br" data-tema="claro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Gestão Escolar SENAI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/login.css">
</head>

<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="../../assets/images/senailogo.png" alt="SENAI" class="login-logo">
                <h1>Gestão Escolar</h1>
                <p>Faça login para acessar o sistema</p>
            </div>

            <?php if ($error): ?>
                <div class="login-alert login-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="../controllers/login_process.php" method="POST" class="login-form">
                <input type="hidden" name="action" value="login">
                <div class="login-field">
                    <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                    <input type="email" name="email" id="email" class="login-input" placeholder="seu@email.com" required
                        autofocus>
                </div>
                <div class="login-field">
                    <label for="senha"><i class="fas fa-lock"></i> Senha</label>
                    <div class="login-password-wrapper">
                        <input type="password" name="senha" id="senha" class="login-input" placeholder="••••••••"
                            required>
                        <button type="button" class="login-toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>

            <div class="login-footer">
                <p>SENAI — Serviço Nacional de Aprendizagem Industrial</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.getElementById('toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>