<?php
/* MODIFICADO: Adicionado require notificacao_helper.php e notificação com suporte CRI */
require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../views/planejamento.php");
    exit;
}

// Dados do formulário
$turma_id = mysqli_real_escape_string($conn, $_POST['turma_id']);
$docente_id = mysqli_real_escape_string($conn, $_POST['docente_id']);
$ambiente_id = mysqli_real_escape_string($conn, $_POST['ambiente_id']);
$dias_semana = $_POST['dias_semana'] ?? [];
$h_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio']);
$h_fim = mysqli_real_escape_string($conn, $_POST['horario_fim']);

if (empty($dias_semana)) {
    die("Erro: Selecione ao menos um dia da semana.");
}

// Verificar conflitos
$conflitos = [];
$turma_res = mysqli_query($conn, "SELECT data_inicio, data_fim FROM Turma WHERE id='$turma_id'");
$t_data = mysqli_fetch_assoc($turma_res);
$data_inicio = $t_data['data_inicio'];
$data_fim = $t_data['data_fim'];

$daysMap = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

foreach ($dias_semana as $dia) {
    $dia_esc = mysqli_real_escape_string($conn, $dia);

    $q_base = "SELECT a.dia_semana, t.data_inicio AS t_start, t.data_fim AS t_end, c.nome AS curso 
               FROM Agenda a JOIN Turma t ON a.turma_id = t.id JOIN Curso c ON t.curso_id = c.id
               WHERE a.dia_semana='$dia_esc' AND a.horario_inicio < '$h_fim' AND a.horario_fim > '$h_inicio'
               AND t.data_inicio <= '$data_fim' AND t.data_fim >= '$data_inicio'";

    // Conflito de docente
    $res = mysqli_query($conn, $q_base . " AND a.docente_id='$docente_id'");
    if ($r = mysqli_fetch_assoc($res)) {
        $overlap_start = max($data_inicio, $r['t_start']);
        $overlap_end = min($data_fim, $r['t_end']);
        $it = new DateTime($overlap_start);
        $itEnd = new DateTime($overlap_end);
        while($it <= $itEnd) {
            if ($daysMap[(int)$it->format('w')] === $dia) {
                $conflitos[] = "Conflito de Docente em " . $it->format('d/m/Y') . ": já alocado no curso '{$r['curso']}'.";
                break;
            }
            $it->modify('+1 day');
        }
    }

    // Conflito de ambiente
    $res = mysqli_query($conn, $q_base . " AND a.ambiente_id='$ambiente_id'");
    if ($r = mysqli_fetch_assoc($res)) {
        $overlap_start = max($data_inicio, $r['t_start']);
        $overlap_end = min($data_fim, $r['t_end']);
        $it = new DateTime($overlap_start);
        $itEnd = new DateTime($overlap_end);
        while($it <= $itEnd) {
            if ($daysMap[(int)$it->format('w')] === $dia) {
                $conflitos[] = "Conflito de Ambiente em " . $it->format('d/m/Y') . ": ocupado por '{$r['curso']}'.";
                break;
            }
            $it->modify('+1 day');
        }
    }
}

// Se houver conflitos, mostrar página de erro
if (!empty($conflitos)) {
    include '../components/header.php';
    ?>
    <div class="page-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Falha no Planejamento</h2>
    </div>
    <div class="card conflict-card">
        <h3 style="color: var(--primary-red); margin-bottom: 15px;">Conflitos Encontrados</h3>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
            <?php foreach ($conflitos as $c): ?>
                <div class="conflict-item"><?= htmlspecialchars($c) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="../views/planejamento.php" class="btn btn-primary">Voltar</a>
        </div>
    </div>
    <?php
    include '../components/footer.php';
    exit;
}

// Inserir na agenda
foreach ($dias_semana as $dia) {
    $dia_esc = mysqli_real_escape_string($conn, $dia);
    mysqli_query($conn, "INSERT INTO Agenda (docente_id, ambiente_id, turma_id, dia_semana, horario_inicio, horario_fim)
                         VALUES ('$docente_id', '$ambiente_id', '$turma_id', '$dia_esc', '$h_inicio', '$h_fim')");
}

/* NOVO: Notificação de registro de horário com suporte ao CRI */
$turmaInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.nome AS curso_nome FROM Turma t JOIN Curso c ON t.curso_id = c.id WHERE t.id = '$turma_id'"));
$cursoNome = $turmaInfo['curso_nome'] ?? 'Turma';
$tipoNotif = isCri() ? 'reserva' : 'registro_horario';
$msgNotif = isCri()
    ? "CRI reservou horário para \"$cursoNome\" ($h_inicio-$h_fim)."
    : "Horário registrado para \"$cursoNome\" ($h_inicio-$h_fim).";
criarNotificacao($conn, $tipoNotif, $msgNotif, $auth_user_id, (int) $turma_id, 'Agenda');

header("Location: ../../index.php?msg=agenda_updated");
exit;
?>
