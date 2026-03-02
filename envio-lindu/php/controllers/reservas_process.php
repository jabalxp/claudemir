<?php
/**
 * reservas_process.php — AJAX backend for the reservation system
 * Portado do Parafal, adaptado para schema gestao_escolar (Projeto do Miguel)
 * Handles: create, delete, complete, list, check conflicts
 * Tabelas: reservas, docente (era professores), Usuario (era usuarios), agenda
 * 
 * MODIFICADO: Adicionado require notificacao_helper.php, chamadas criarNotificacao(),
 *             e permissão isCri() nos checks de permissão
 */

require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── CREATE RESERVATION ──
if ($action === 'create') {
    /* MODIFICADO: isCri() adicionado à verificação de permissão */
    if (!can_edit() && !isCri()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $docente_id = (int) $_POST['professor_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $dias_semana = $_POST['dias_semana'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $notas = $_POST['notas'] ?? '';
    $usuario_id = $auth_user_id;

    if (!$docente_id || !$data_inicio || !$data_fim || !$dias_semana || !$hora_inicio || !$hora_fim) {
        echo json_encode(['ok' => false, 'error' => 'Dados incompletos.']);
        exit;
    }

    $dias_arr = explode(',', $dias_semana);
    $conflict = checkReservationConflict($mysqli, $docente_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim, $usuario_id);
    if ($conflict) {
        echo json_encode(['ok' => false, 'error' => $conflict]);
        exit;
    }

    $agendaConflict = checkAgendaConflictForReservation($mysqli, $docente_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim);
    if ($agendaConflict) {
        echo json_encode(['ok' => false, 'error' => $agendaConflict]);
        exit;
    }

    $st = $mysqli->prepare("INSERT INTO reservas (docente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param('iissssss', $docente_id, $usuario_id, $data_inicio, $data_fim, $dias_semana, $hora_inicio, $hora_fim, $notas);
    $st->execute();

    $reserva_id_new = $mysqli->insert_id;

    /* NOVO: Notificação de reserva criada */
    $docNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Docente WHERE id = $docente_id"))['nome'] ?? 'Docente';
    criarNotificacao($conn, 'reserva', "Professor \"$docNome\" foi reservado ($data_inicio a $data_fim).", $auth_user_id, (int) $reserva_id_new, 'Reserva');

    echo json_encode(['ok' => true, 'id' => $reserva_id_new, 'msg' => 'Professor reservado com sucesso!']);
    exit;
}

// ── DELETE (UNRESERVE) ──
if ($action === 'delete') {
    /* MODIFICADO: isCri() adicionado à verificação de permissão */
    if (!can_edit() && !isCri()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $reserva_id = (int) $_POST['reserva_id'];

    $st = $mysqli->prepare("SELECT usuario_id FROM reservas WHERE id = ? AND status = 'ativo'");
    $st->bind_param('i', $reserva_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Reserva não encontrada ou já concluída.']);
        exit;
    }

    if ($row['usuario_id'] !== $auth_user_id && $auth_user_role !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Apenas quem criou a reserva (ou admin) pode excluí-la.']);
        exit;
    }

    $resInfo = $mysqli->prepare("SELECT d.nome as docente_nome FROM reservas r JOIN docente d ON r.docente_id = d.id WHERE r.id = ?");
    $resInfo->bind_param('i', $reserva_id);
    $resInfo->execute();
    $resRow = $resInfo->get_result()->fetch_assoc();
    $docNomeDel = $resRow['docente_nome'] ?? 'Docente';

    $st2 = $mysqli->prepare("DELETE FROM reservas WHERE id = ?");
    $st2->bind_param('i', $reserva_id);
    $st2->execute();

    /* NOVO: Notificação de reserva removida */
    criarNotificacao($conn, 'reserva', "Reserva do professor \"$docNomeDel\" foi removida.", $auth_user_id, (int) $reserva_id, 'Reserva');

    echo json_encode(['ok' => true, 'msg' => 'Reserva removida com sucesso.']);
    exit;
}

// ── COMPLETE RESERVATION (mark as concluido) ──
if ($action === 'complete') {
    /* MODIFICADO: isCri() adicionado à verificação de permissão */
    if (!can_edit() && !isCri()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $reserva_id = (int) $_POST['reserva_id'];

    $st = $mysqli->prepare("SELECT * FROM reservas WHERE id = ? AND status = 'ativo'");
    $st->bind_param('i', $reserva_id);
    $st->execute();
    $reserva = $st->get_result()->fetch_assoc();

    if (!$reserva) {
        echo json_encode(['ok' => false, 'error' => 'Reserva não encontrada.']);
        exit;
    }

    if ($reserva['usuario_id'] !== $auth_user_id && $auth_user_role !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Apenas quem criou a reserva (ou admin) pode concluí-la.']);
        exit;
    }

    $st2 = $mysqli->prepare("UPDATE reservas SET status = 'concluido' WHERE id = ?");
    $st2->bind_param('i', $reserva_id);
    $st2->execute();

    echo json_encode(['ok' => true, 'msg' => 'Reserva marcada como concluída.']);
    exit;
}

// ── LIST RESERVAS ──
if ($action === 'list') {
    $status_filter = $_GET['status'] ?? 'ativo';
    $prof_filter = (int) ($_GET['professor_id'] ?? 0);

    $where = "WHERE r.status = ?";
    $params = [$status_filter];
    $types = 's';

    if ($prof_filter) {
        $where .= " AND r.docente_id = ?";
        $params[] = $prof_filter;
        $types .= 'i';
    }

    /* MODIFICADO: CRI também vê apenas suas próprias reservas */
    if ($auth_user_role === 'gestor' || $auth_user_role === 'cri') {
        $where .= " AND r.usuario_id = ?";
        $params[] = $auth_user_id;
        $types .= 'i';
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

    echo json_encode(['ok' => true, 'reservas' => $reservas]);
    exit;
}

// ── CHECK RESERVATIONS FOR A PROFESSOR (used by agenda views) ──
if ($action === 'check_professor') {
    $prof_id = (int) $_GET['professor_id'];
    $month = $_GET['month'] ?? date('Y-m');
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));

    $st = $mysqli->prepare("
        SELECT r.*, u.nome as gestor_nome
        FROM reservas r
        JOIN Usuario u ON r.usuario_id = u.id
        WHERE r.docente_id = ? AND r.status = 'ativo'
        AND r.data_inicio <= ? AND r.data_fim >= ?
    ");
    $st->bind_param('iss', $prof_id, $l_day, $f_day);
    $st->execute();
    $reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $reserved_dates = [];
    foreach ($reservas as $r) {
        $dias_arr = explode(',', $r['dias_semana']);
        $cur = new DateTime(max($r['data_inicio'], $f_day));
        $end = new DateTime(min($r['data_fim'], $l_day));
        $end->modify('+1 day');
        while ($cur < $end) {
            $dow = $cur->format('N');
            if (in_array($dow, $dias_arr)) {
                $d = $cur->format('Y-m-d');
                $reserved_dates[$d] = [
                    'reserva_id' => $r['id'],
                    'gestor' => $r['gestor_nome'],
                    'hora_inicio' => $r['hora_inicio'],
                    'hora_fim' => $r['hora_fim'],
                    'own' => ($r['usuario_id'] == $auth_user_id)
                ];
            }
            $cur->modify('+1 day');
        }
    }

    echo json_encode(['ok' => true, 'reserved' => $reserved_dates]);
    exit;
}

// ── HELPER FUNCTIONS ──

function checkReservationConflict($mysqli, $docente_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim, $usuario_id)
{
    $st = $mysqli->prepare("
        SELECT r.*, u.nome as gestor_nome
        FROM reservas r
        JOIN Usuario u ON r.usuario_id = u.id
        WHERE r.docente_id = ? AND r.status = 'ativo'
        AND r.usuario_id != ?
        AND r.data_inicio <= ? AND r.data_fim >= ?
        AND (r.hora_inicio < ? AND r.hora_fim > ?)
    ");
    $st->bind_param('iissss', $docente_id, $usuario_id, $data_fim, $data_inicio, $hora_fim, $hora_inicio);
    $st->execute();
    $conflicts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($conflicts as $c) {
        $c_dias = explode(',', $c['dias_semana']);
        $overlap_dias = array_intersect($dias_arr, $c_dias);
        if (!empty($overlap_dias)) {
            $dow_names = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
            $overlap_names = array_map(function ($d) use ($dow_names) {
                return $dow_names[$d] ?? $d;
            }, $overlap_dias);
            return "Conflito: Gestor \"{$c['gestor_nome']}\" já reservou este professor em " . implode(', ', $overlap_names) .
                " ({$c['data_inicio']} a {$c['data_fim']}, {$c['hora_inicio']}-{$c['hora_fim']}).";
        }
    }

    return null;
}

function checkAgendaConflictForReservation($mysqli, $docente_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim)
{
    $mysql_dows = array_map(function ($d) {
        return $d + 1; }, $dias_arr);
    $dows_str = implode(',', $mysql_dows);

    $st = $mysqli->prepare("
        SELECT COUNT(*) as cnt
        FROM agenda a
        WHERE a.docente_id = ?
        AND a.data BETWEEN ? AND ?
        AND DAYOFWEEK(a.data) IN ($dows_str)
        AND (a.horario_inicio < ? AND a.horario_fim > ?)
    ");
    $st->bind_param('issss', $docente_id, $data_inicio, $data_fim, $hora_fim, $hora_inicio);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if ($row['cnt'] > 0) {
        return "O professor já possui {$row['cnt']} aula(s) agendada(s) que conflitam com este horário no período selecionado.";
    }

    return null;
}
