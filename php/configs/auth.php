<?php
/**
 * Authentication Middleware
 * Include this file at the top of every protected page (or via header.php).
 * Handles session validation, role checks, and forced password change redirects.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the user is authenticated.
 * Redirects to login if not.
 */
function requireAuth()
{
    if (empty($_SESSION['user_id'])) {
        // Determine the correct path to login.php
        $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
        $is_in_subdir = !empty(array_intersect(['views', 'controllers', 'components', 'configs'], $path_parts));
        $prefix = $is_in_subdir ? '' : 'php/views/';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }
}

/**
 * Checks if the user must change their password.
 * Redirects/flags if obrigar_troca_senha is true.
 */
function checkForcePasswordChange()
{
    if (!empty($_SESSION['obrigar_troca_senha'])) {
        $current_page = basename($_SERVER['PHP_SELF']);
        // Allow the change password action and the login page itself
        $allowed_pages = ['login.php', 'login_process.php', 'usuarios_process.php', 'logout.php'];
        if (!in_array($current_page, $allowed_pages)) {
            // Set a flag that the frontend will use to show the modal
            $_SESSION['show_change_password_modal'] = true;
        }
    }
}

/**
 * Requires a specific role. Returns 403 if the user doesn't have it.
 * @param string|array $roles Allowed role(s)
 */
function requireRole($roles)
{
    if (!is_array($roles))
        $roles = [$roles];
    if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado. Permissão insuficiente.']);
        exit;
    }
}

/**
 * Check if the current user is an admin.
 * @return bool
 */
function isAdmin()
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Check if the current user is a gestor.
 * @return bool
 */
function isGestor()
{
    return ($_SESSION['user_role'] ?? '') === 'gestor';
}

/**
 * Check if the current user is a professor.
 * @return bool
 */
function isProfessor()
{
    return ($_SESSION['user_role'] ?? '') === 'professor';
}

/**
 * Check if the current user is CRI.
 * @return bool
 */
function isCri()
{
    return ($_SESSION['user_role'] ?? '') === 'cri';
}

/**
 * Get the current user's name.
 * @return string
 */
function getUserName()
{
    return $_SESSION['user_nome'] ?? 'Usuário';
}

/**
 * Get the current user's role.
 * @return string
 */
function getUserRole()
{
    return $_SESSION['user_role'] ?? 'professor';
}

/**
 * Get the current user's linked docente_id.
 * @return int|null
 */
function getUserDocenteId()
{
    return $_SESSION['user_docente_id'] ?? null;
}

/**
 * Check if the current user can edit (admin or gestor).
 * Compatibility with Parafal code.
 * @return bool
 */
function can_edit()
{
    return isAdmin() || isGestor();
}

// Variáveis globais de compatibilidade com código Parafal
$auth_user_id = $_SESSION['user_id'] ?? 0;
$auth_user_nome = $_SESSION['user_nome'] ?? 'Usuário';
$auth_user_role = $_SESSION['user_role'] ?? '';
