<?php
require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    // Buscar info antes de deletar
    $turmaInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT t.id, c.nome AS curso_nome FROM Turma t LEFT JOIN Curso c ON t.curso_id = c.id WHERE t.id = '$id'"));
    $descTurma = $turmaInfo ? "Turma #{$turmaInfo['id']} ({$turmaInfo['curso_nome']})" : "Turma #$id";

    mysqli_query($conn, "DELETE FROM Agenda WHERE turma_id = '$id'");
    mysqli_query($conn, "DELETE FROM Turma WHERE id = '$id'");

    criarNotificacao($conn, 'exclusao_turma', "$descTurma foi excluída.", $auth_user_id, (int) $id, 'Turma');

    header("Location: ../views/turmas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $curso_id = mysqli_real_escape_string($conn, $_POST['curso_id']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $periodo = mysqli_real_escape_string($conn, $_POST['periodo']);
    $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim']);
    $ambiente_id = mysqli_real_escape_string($conn, $_POST['ambiente_id']);
    $sigla = mysqli_real_escape_string($conn, $_POST['sigla']);
    $vagas = (int) $_POST['vagas'];
    $local = mysqli_real_escape_string($conn, $_POST['local']);
    $docente_id1 = !empty($_POST['docente_id1']) ? (int) $_POST['docente_id1'] : "NULL";
    $docente_id2 = !empty($_POST['docente_id2']) ? (int) $_POST['docente_id2'] : "NULL";
    $docente_id3 = !empty($_POST['docente_id3']) ? (int) $_POST['docente_id3'] : "NULL";
    $docente_id4 = !empty($_POST['docente_id4']) ? (int) $_POST['docente_id4'] : "NULL";
    $dias_semana_arr = $_POST['dias_semana'] ?? [];
    $dias_semana_str = mysqli_real_escape_string($conn, implode(',', $dias_semana_arr));

    // Period to time mapping
    $period_times = [
        'Manhã' => ['07:30', '11:30'],
        'Tarde' => ['13:30', '17:30'],
        'Noite' => ['19:30', '23:30'],
        'Integral' => ['07:30', '17:30'],
    ];
    $horario_inicio = $period_times[$periodo][0] ?? '07:30';
    $horario_fim = $period_times[$periodo][1] ?? '11:30';

    if ($id) {
        // UPDATE existing turma
        $query = "UPDATE Turma SET 
                  curso_id = '$curso_id', 
                  tipo = '$tipo', 
                  periodo = '$periodo', 
                  data_inicio = '$data_inicio', 
                  data_fim = '$data_fim', 
                  ambiente_id = '$ambiente_id', 
                  sigla = '$sigla',
                  vagas = $vagas,
                  local = '$local',
                  dias_semana = '$dias_semana_str',
                  docente_id1 = $docente_id1,
                  docente_id2 = $docente_id2,
                  docente_id3 = $docente_id3,
                  docente_id4 = $docente_id4
                  WHERE id = '$id'";
        mysqli_query($conn, $query);

        // Regenerate agenda: delete old records and create new ones
        mysqli_query($conn, "DELETE FROM Agenda WHERE turma_id = '$id'");
        generateAgendaRecords($conn, $id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docente_id1);

        $cursoNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Curso WHERE id = '$curso_id'"))['nome'] ?? 'Curso';
        criarNotificacao($conn, 'edicao_turma', "Turma #$id ($cursoNome) foi editada.", $auth_user_id, (int) $id, 'Turma');

        header("Location: ../views/planejamento.php?docente_id=" . ($docente_id1 !== "NULL" ? $docente_id1 : '') . "&msg=updated");
    } else {
        // INSERT new turma
        $query = "INSERT INTO Turma (curso_id, tipo, periodo, data_inicio, data_fim, ambiente_id, sigla, vagas, local, dias_semana, docente_id1, docente_id2, docente_id3, docente_id4) 
                  VALUES ('$curso_id', '$tipo', '$periodo', '$data_inicio', '$data_fim', '$ambiente_id', '$sigla', $vagas, '$local', '$dias_semana_str', $docente_id1, $docente_id2, $docente_id3, $docente_id4)";
        mysqli_query($conn, $query);
        $turma_id = mysqli_insert_id($conn);

        // Auto-generate agenda records
        generateAgendaRecords($conn, $turma_id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docente_id1);

        $cursoNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Curso WHERE id = '$curso_id'"))['nome'] ?? 'Curso';
        criarNotificacao($conn, 'registro_turma', "Nova turma #$turma_id ($cursoNome) foi criada.", $auth_user_id, (int) $turma_id, 'Turma');

        // Redirect to calendar instead of turmas list
        header("Location: ../views/planejamento.php?docente_id=" . ($docente_id1 !== "NULL" ? $docente_id1 : '') . "&msg=created");
    }
    exit;
}

/**
 * Generate Agenda records for each valid day between data_inicio and data_fim
 */
function generateAgendaRecords($conn, $turma_id, $dias_arr, $periodo, $h_inicio, $h_fim, $data_inicio, $data_fim, $ambiente_id, $docente_id)
{
    $daysMap = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado'
    ];

    $it = new DateTime($data_inicio);
    $end = new DateTime($data_fim);
    $docente_val = ($docente_id !== "NULL" && $docente_id > 0) ? (int) $docente_id : "NULL";

    while ($it <= $end) {
        $w = (int) $it->format('w');
        $dayName = $daysMap[$w] ?? '';

        if (in_array($dayName, $dias_arr)) {
            $dateStr = $it->format('Y-m-d');
            $dia_esc = mysqli_real_escape_string($conn, $dayName);
            $periodo_esc = mysqli_real_escape_string($conn, $periodo);

            mysqli_query($conn, "INSERT INTO Agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                 VALUES ('$turma_id', $docente_val, '$ambiente_id', '$dia_esc', '$periodo_esc', '$h_inicio', '$h_fim', '$dateStr', 'CONFIRMADO')");
        }
        $it->modify('+1 day');
    }
}

header("Location: ../views/turmas.php");
?>