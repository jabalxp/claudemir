<?php
/**
 * Gerenciar Reservas
 * Portado do Parafal, adaptado para schema gestao_escolar (Projeto do Miguel)
 * Tabelas: reservas, docente (era professores), Usuario (era usuarios)
 * 
 * MODIFICADO: Permissão isCri() adicionada para CRI acessar esta página
 */
require_once '../configs/db.php';
require_once '../configs/auth.php';

/* MODIFICADO: CRI pode acessar esta página junto com gestors e admins */
if (!can_edit() && !isCri()) {
    $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
    $is_in_subdir = !empty(array_intersect(['views', 'controllers'], $path_parts));
    $prefix = $is_in_subdir ? '../../' : '';
    header('Location: ' . $prefix . 'index.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reserva_id = (int) ($_POST['reserva_id'] ?? 0);

    if ($action === 'delete' && $reserva_id) {
        $st = $mysqli->prepare("SELECT usuario_id FROM reservas WHERE id = ? AND status = 'ativo'");
        $st->bind_param('i', $reserva_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row && ($row['usuario_id'] == $auth_user_id || $auth_user_role === 'admin')) {
            $del = $mysqli->prepare("DELETE FROM reservas WHERE id = ?");
            $del->bind_param('i', $reserva_id);
            $del->execute();
            $msg_success = 'Reserva removida com sucesso.';
        } else {
            $msg_error = 'Somente quem criou a reserva pode removê-la.';
        }
    }

    if ($action === 'complete' && $reserva_id) {
        $st = $mysqli->prepare("SELECT * FROM reservas WHERE id = ? AND status = 'ativo'");
        $st->bind_param('i', $reserva_id);
        $st->execute();
        $reserva = $st->get_result()->fetch_assoc();
        if ($reserva && ($reserva['usuario_id'] == $auth_user_id || $auth_user_role === 'admin')) {
            $upd = $mysqli->prepare("UPDATE reservas SET status = 'concluido' WHERE id = ?");
            $upd->bind_param('i', $reserva_id);
            $upd->execute();
            $msg_success = 'Reserva marcada como concluída.';
        } else {
            $msg_error = 'Somente quem criou a reserva pode concluí-la.';
        }
    }
}

// Fetch reservations
$status_filter = $_GET['status'] ?? 'ativo';
$owner_filter = $_GET['owner'] ?? 'mine';

$where = "WHERE r.status = ?";
$params = [$status_filter];
$types = 's';

if ($owner_filter === 'others') {
    $where .= " AND r.usuario_id != ?";
    $params[] = $auth_user_id;
    $types .= 'i';
} else {
    if ($auth_user_role !== 'admin' || $owner_filter === 'mine') {
        $where .= " AND r.usuario_id = ?";
        $params[] = $auth_user_id;
        $types .= 'i';
    }
}

$st = $mysqli->prepare("
    SELECT r.*, d.nome as professor_nome, d.area_conhecimento as especialidade, d.cor_agenda,
           u.nome as gestor_nome
    FROM reservas r
    JOIN docente d ON r.docente_id = d.id
    JOIN Usuario u ON r.usuario_id = u.id
    $where
    ORDER BY r.created_at DESC
");
$st->bind_param($types, ...$params);
$st->execute();
$reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$st_mine = $mysqli->prepare("SELECT COUNT(*) FROM reservas WHERE status = ? AND usuario_id = ?");
$st_mine->bind_param('si', $status_filter, $auth_user_id);
$st_mine->execute();
$count_mine = $st_mine->get_result()->fetch_row()[0];

$st_others = $mysqli->prepare("SELECT COUNT(*) FROM reservas WHERE status = ? AND usuario_id != ?");
$st_others->bind_param('si', $status_filter, $auth_user_id);
$st_others->execute();
$count_others = $st_others->get_result()->fetch_row()[0];

$dow_names = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

include '../components/header.php';
/* ... restante da view permanece igual (HTML dos cards de reserva) ... */
?>
