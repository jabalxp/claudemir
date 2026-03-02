<?php
function calcularDiasOcupadosNoMes($conn, $did, $primeiro, $ultimo)
{
    // Fetch all agendas for this teacher that might occur in this month
    // Include both Turma-linked agendas and independent reservations
    $res = mysqli_query($conn, "
        SELECT a.dia_semana, a.data AS individual_date, t.data_inicio, t.data_fim, a.status
        FROM Agenda a
        LEFT JOIN Turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
          AND (
              (t.id IS NOT NULL AND t.data_inicio <= '$ultimo' AND t.data_fim >= '$primeiro')
              OR 
              (a.data IS NOT NULL AND a.data BETWEEN '$primeiro' AND '$ultimo')
          )
    ");

    $dias_contados = [];
    $total_ocupados = 0;
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($row = mysqli_fetch_assoc($res)) {
        // If it's an individual date (like a persistent reservation)
        if ($row['individual_date']) {
            $ds = $row['individual_date'];
            if (!isset($dias_contados[$ds])) {
                $dias_contados[$ds] = true;
                $total_ocupados++;
            }
            continue;
        }

        // If it's a recurrent turma-linked agenda
        if ($row['data_inicio'] && $row['data_fim']) {
            $dia_db = trim($row['dia_semana']);
            $it = new DateTime(max($primeiro, $row['data_inicio']));
            $itFim = new DateTime(min($ultimo, $row['data_fim']));

            while ($it <= $itFim) {
                $w = (int) $it->format('w');
                if (isset($daysMap[$w]) && $daysMap[$w] === $dia_db) {
                    $ds = $it->format('Y-m-d');
                    if (!isset($dias_contados[$ds])) {
                        $dias_contados[$ds] = true;
                        $total_ocupados++;
                    }
                }
                $it->modify('+1 day');
            }
        }
    }
    return $total_ocupados;
}

function contarDiasUteisNoMes($primeiro, $ultimo)
{
    $total = 0;
    $dt = new DateTime($primeiro);
    $dtFim = new DateTime($ultimo);
    while ($dt <= $dtFim) {
        if ($dt->format('w') != 0)
            $total++;
        $dt->modify('+1 day');
    }
    return $total;
}

function getProximoDiaLivre($conn, $did, $start_date = null)
{
    if (!$start_date)
        $start_date = date('Y-m-d');

    $it = new DateTime($start_date);
    $limit = clone $it;
    $limit->modify('+90 days');

    // Fetch all agendas for this teacher
    $res = mysqli_query($conn, "
        SELECT a.dia_semana, a.data AS individual_date, t.data_inicio, t.data_fim
        FROM Agenda a
        LEFT JOIN Turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
    ");
    $agendas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $agendas[] = $row;
    }

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($it <= $limit) {
        $w = (int) $it->format('w');
        if ($w == 0) { // Skip Sunday
            $it->modify('+1 day');
            continue;
        }

        $dia_str = $daysMap[$w];
        $current_date = $it->format('Y-m-d');
        $ocupado = false;

        foreach ($agendas as $ag) {
            if ($ag['individual_date'] === $current_date) {
                $ocupado = true;
                break;
            }
            if ($ag['dia_semana'] === $dia_str && $ag['data_inicio'] && $ag['data_fim']) {
                if ($current_date >= $ag['data_inicio'] && $current_date <= $ag['data_fim']) {
                    $ocupado = true;
                    break;
                }
            }
        }

        if (!$ocupado) {
            return $it->format('Y-m-d');
        }
        $it->modify('+1 day');
    }
    return 'N/A';
}
?>