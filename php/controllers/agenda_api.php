<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../configs/db.php';
require_once '../configs/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_docente_agenda':
        $docente_id = (int) ($_GET['docente_id'] ?? 0);
        if (!$docente_id) {
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }

        $agendas = [];
        // Get turma-based agendas
        $res = mysqli_query($conn, "
            SELECT a.id, a.dia_semana, a.periodo, a.horario_inicio, a.horario_fim,
                   a.data AS agenda_data, a.status,
                   c.nome AS curso_nome, t.data_inicio, t.data_fim, t.id AS turma_id,
                   amb.nome AS ambiente_nome
            FROM Agenda a
            JOIN Turma t ON a.turma_id = t.id
            JOIN Curso c ON t.curso_id = c.id
            LEFT JOIN Ambiente amb ON a.ambiente_id = amb.id
            WHERE a.docente_id = $docente_id
            ORDER BY t.data_inicio ASC
        ");
        while ($row = mysqli_fetch_assoc($res))
            $agendas[] = $row;

        // Get reservation-only entries (turma_id IS NULL, status = RESERVADO)
        $res_reserv = mysqli_query($conn, "
            SELECT a.id, a.dia_semana, a.periodo, a.horario_inicio, a.horario_fim,
                   a.data AS agenda_data, a.status,
                   'Reservado' AS curso_nome, a.data AS data_inicio, a.data AS data_fim, NULL AS turma_id,
                   amb.nome AS ambiente_nome
            FROM Agenda a
            LEFT JOIN Ambiente amb ON a.ambiente_id = amb.id
            WHERE a.docente_id = $docente_id AND a.turma_id IS NULL AND a.status = 'RESERVADO'
            ORDER BY a.data ASC
        ");
        while ($row = mysqli_fetch_assoc($res_reserv))
            $agendas[] = $row;

        $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Docente WHERE id = $docente_id"));

        $meses_ocupados = [];
        $ultima_aula = null;
        foreach ($agendas as $ag) {
            $iter = new DateTime($ag['data_inicio']);
            $fim = new DateTime($ag['data_fim']);
            while ($iter <= $fim) {
                $meses_ocupados[$iter->format('Y-m')] = true;
                $iter->modify('first day of next month');
            }
            if (!$ultima_aula || $ag['data_fim'] > $ultima_aula)
                $ultima_aula = $ag['data_fim'];
        }

        echo json_encode(['docente' => $doc, 'agendas' => $agendas, 'meses_ocupados' => array_keys($meses_ocupados), 'ultima_aula' => $ultima_aula], JSON_UNESCAPED_UNICODE);
        break;

    case 'remove_reservation':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem remover reservas.']);
            exit;
        }

        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $data = mysqli_real_escape_string($conn, $_POST['data'] ?? '');
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? '');

        if (!$docente_id || !$data) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos para remoção de reserva.']);
            exit;
        }

        $query = "DELETE FROM Agenda 
                  WHERE docente_id = $docente_id 
                    AND data = '$data' 
                    AND status = 'RESERVADO' 
                    AND turma_id IS NULL";
        if ($periodo) {
            $query .= " AND periodo = '$periodo'";
        }

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Reserva removida com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover reserva: ' . mysqli_error($conn)]);
        }
        exit;

    case 'remove_reservations_batch':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $dates = $_POST['dates'] ?? [];
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? '');

        if (!$docente_id || empty($dates)) {
            echo json_encode(['success' => false, 'message' => 'Docente e datas são obrigatórios.']);
            exit;
        }

        $date_list = [];
        foreach ($dates as $d) {
            $date_list[] = "'" . mysqli_real_escape_string($conn, $d) . "'";
        }
        $date_str = implode(',', $date_list);

        $query = "DELETE FROM Agenda WHERE docente_id = $docente_id AND data IN ($date_str) AND status = 'RESERVADO' AND turma_id IS NULL";
        if ($periodo) {
            $query .= " AND periodo = '$periodo'";
        }

        if (mysqli_query($conn, $query)) {
            $count = mysqli_affected_rows($conn);
            echo json_encode(['success' => true, 'message' => "$count reserva(s) removida(s)."]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;

    case 'save_reservations':
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $dates = $_POST['dates'] ?? [];
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? 'Manhã');

        if (!$docente_id || empty($dates)) {
            echo json_encode(['success' => false, 'message' => 'Docente e datas são obrigatórios.']);
            exit;
        }

        $saved = 0;
        $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

        foreach ($dates as $date) {
            $date_esc = mysqli_real_escape_string($conn, $date);
            $w = (int) date('w', strtotime($date));
            $dia_semana = $daysMap[$w];

            // Check if reservation already exists for this day/period
            $existing = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT id FROM Agenda 
                WHERE docente_id = $docente_id AND data = '$date_esc' AND status = 'RESERVADO' AND turma_id IS NULL AND periodo = '$periodo'
            "));

            if (!$existing) {
                mysqli_query($conn, "
                    INSERT INTO Agenda (docente_id, dia_semana, data, status, turma_id, periodo)
                    VALUES ($docente_id, '$dia_semana', '$date_esc', 'RESERVADO', NULL, '$periodo')
                ");
                $saved++;
            }
        }

        echo json_encode(['success' => true, 'message' => "$saved dia(s) reservado(s) com sucesso."], JSON_UNESCAPED_UNICODE);
        break;

    case 'salvar_horario':
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $ambiente_id = (int) ($_POST['ambiente_id'] ?? 0);
        $dias_semana = $_POST['dias_semana'] ?? [];
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? 'Manhã');
        $h_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '');
        $h_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '');
        $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio'] ?? '');
        $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim'] ?? '');

        // New Turma metadata fields
        $sigla = mysqli_real_escape_string($conn, $_POST['sigla'] ?? '');
        $vagas = (int) ($_POST['vagas'] ?? 32);
        $local = mysqli_real_escape_string($conn, $_POST['local'] ?? 'Sede');
        $tipo = mysqli_real_escape_string($conn, $_POST['tipo'] ?? 'Presencial');
        $docente_id2 = (int) ($_POST['docente_id2'] ?? 0);
        $docente_id3 = (int) ($_POST['docente_id3'] ?? 0);
        $docente_id4 = (int) ($_POST['docente_id4'] ?? 0);

        if (!$docente_id || !$curso_id || !$ambiente_id || empty($dias_semana) || !$periodo || !$data_inicio || !$data_fim) {
            echo json_encode(['success' => false, 'message' => 'Campos obrigatórios ausentes. Preencha todos os campos.']);
            exit;
        }

        // Auto-set horarios from period if not provided
        $periodTimes = [
            'Manhã' => ['07:30', '13:30'],
            'Tarde' => ['13:30', '17:30'],
            'Integral' => ['07:30', '17:30'],
            'Noite' => ['19:30', '23:30']
        ];
        if (!$h_inicio || !$h_fim) {
            $times = $periodTimes[$periodo] ?? ['07:30', '13:30'];
            $h_inicio = $h_inicio ?: $times[0];
            $h_fim = $h_fim ?: $times[1];
        }

        // Build list of ALL docentes involved
        $all_docente_ids = [$docente_id];
        if ($docente_id2)
            $all_docente_ids[] = $docente_id2;
        if ($docente_id3)
            $all_docente_ids[] = $docente_id3;
        if ($docente_id4)
            $all_docente_ids[] = $docente_id4;

        // Conflict checking — check ALL docentes
        $conflitos = [];
        foreach ($dias_semana as $dia) {
            $dia_esc = mysqli_real_escape_string($conn, $dia);

            $base_q = "SELECT a.id, c.nome AS curso, t.data_inicio AS t_start, t.data_fim AS t_end, d.nome AS docente_nome
                  FROM Agenda a 
                  JOIN Turma t ON a.turma_id = t.id 
                  JOIN Curso c ON t.curso_id = c.id 
                  JOIN Docente d ON a.docente_id = d.id
                  WHERE a.dia_semana = '$dia_esc' 
                  AND a.horario_inicio < '$h_fim' AND a.horario_fim > '$h_inicio'
                  AND t.data_inicio <= '$data_fim' AND t.data_fim >= '$data_inicio' ";

            $getFirstConflictDate = function ($conn, $query, $target_start, $target_end, $dia_nome) {
                $res = mysqli_query($conn, $query);
                if ($r = mysqli_fetch_assoc($res)) {
                    $start = max($target_start, $r['t_start']);
                    $end = min($target_end, $r['t_end']);
                    $it = new DateTime($start);
                    $itEnd = new DateTime($end);
                    $daysMap = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                    while ($it <= $itEnd) {
                        if ($daysMap[(int) $it->format('w')] === $dia_nome) {
                            return ['curso' => $r['curso'], 'date' => $it->format('d/m/Y'), 'docente' => $r['docente_nome'] ?? ''];
                        }
                        $it->modify('+1 day');
                    }
                }
                return null;
            };

            // Check conflicts for EACH docente
            foreach ($all_docente_ids as $did) {
                $conf_d = $getFirstConflictDate($conn, $base_q . " AND a.docente_id = '$did'", $data_inicio, $data_fim, $dia);
                if ($conf_d) {
                    $docNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Docente WHERE id = $did"))['nome'] ?? "Docente #$did";
                    $conflitos[] = "Conflito de Docente ($docNome) em {$conf_d['date']}: curso '{$conf_d['curso']}'.";
                }
            }

            // Check ambiente conflict
            $conf_a = $getFirstConflictDate($conn, $base_q . " AND a.ambiente_id = '$ambiente_id'", $data_inicio, $data_fim, $dia);
            if ($conf_a)
                $conflitos[] = "Conflito de Ambiente em {$conf_a['date']}: sala ocupada por '{$conf_a['curso']}'.";
        }

        if (!empty($conflitos)) {
            echo json_encode(['success' => false, 'message' => implode(' | ', $conflitos)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_POST['is_simulation']) && $_POST['is_simulation'] == '1') {
            echo json_encode(['success' => true, 'message' => 'Simulação concluída com sucesso. Nenhum conflito de horário encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Build Turma with all metadata
        $dias_str = mysqli_real_escape_string($conn, implode(',', $dias_semana));
        if (!$sigla) {
            $curso_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Curso WHERE id = $curso_id"));
            $sigla = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $curso_row['nome'] ?? 'CRS'), 0, 4)) . '-' . date('His');
        }

        $d2_val = $docente_id2 ? $docente_id2 : 'NULL';
        $d3_val = $docente_id3 ? $docente_id3 : 'NULL';
        $d4_val = $docente_id4 ? $docente_id4 : 'NULL';

        $insert_turma = "INSERT INTO Turma (curso_id, tipo, sigla, vagas, periodo, data_inicio, data_fim, dias_semana, ambiente_id, docente_id1, docente_id2, docente_id3, docente_id4, local) 
                         VALUES ($curso_id, '$tipo', '$sigla', $vagas, '$periodo', '$data_inicio', '$data_fim', '$dias_str', $ambiente_id, $docente_id, $d2_val, $d3_val, $d4_val, '$local')";
        if (!mysqli_query($conn, $insert_turma)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar turma: ' . mysqli_error($conn)]);
            exit;
        }
        $turma_id = mysqli_insert_id($conn);

        // Create agenda entries for EACH docente and EACH selected day
        foreach ($dias_semana as $dia) {
            $dia_esc = mysqli_real_escape_string($conn, $dia);
            foreach ($all_docente_ids as $did) {
                mysqli_query($conn, "INSERT INTO Agenda (docente_id, ambiente_id, turma_id, dia_semana, periodo, horario_inicio, horario_fim)
                                     VALUES ('$did', '$ambiente_id', '$turma_id', '$dia_esc', '$periodo', '$h_inicio', '$h_fim')");
            }
        }

        // Clean up any RESERVADO entries for the involved docentes in the date range that overlap with the new period
        $p_list = "('$periodo')";
        if ($periodo === 'Manhã' || $periodo === 'Integral') {
            $p_list = "('Manhã', 'Integral')";
        }
        if ($periodo === 'Tarde') {
            $p_list = "('Tarde', 'Integral')";
        }
        if ($periodo === 'Integral') {
            $p_list = "('Manhã', 'Tarde', 'Integral')";
        }

        foreach ($all_docente_ids as $did) {
            mysqli_query($conn, "
                DELETE FROM Agenda 
                WHERE docente_id = $did 
                  AND turma_id IS NULL 
                  AND status = 'RESERVADO' 
                  AND periodo IN $p_list
                  AND data >= '$data_inicio' 
                  AND data <= '$data_fim'
            ");
        }

        echo json_encode(['success' => true, 'message' => 'Turma criada e horário agendado com sucesso!'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Ação inválida']);
        break;
}
?>