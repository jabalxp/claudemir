<?php
require_once '../configs/db.php';
require_once '../configs/utils.php';

header('Content-Type: application/json');

$docente_id = isset($_GET['docente_id']) ? (int) $_GET['docente_id'] : 0;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

if (!$docente_id) {
    echo json_encode(['error' => 'Missing docente_id']);
    exit;
}

// Fetch all agendas for this teacher in the given range
$res = mysqli_query($conn, "
    SELECT a.horario_inicio, a.horario_fim, a.dia_semana, t.data_inicio, t.data_fim
    FROM Agenda a
    JOIN Turma t ON a.turma_id = t.id
    WHERE a.docente_id = $docente_id
      AND t.data_inicio <= '$data_fim'
      AND t.data_fim >= '$data_inicio'
");

$agendas = mysqli_fetch_all($res, MYSQLI_ASSOC);

function isBusy($agendas, $target_start, $target_end, $target_day = null)
{
    foreach ($agendas as $ag) {
        if ($target_day && $ag['dia_semana'] !== $target_day)
            continue;

        $s = $ag['horario_inicio'];
        $e = $ag['horario_fim'];
        // Check for time overlap
        if (!($target_end <= $s || $target_start >= $e)) {
            return true;
        }
    }
    return false;
}

$periods = [
    'Manhã' => ['start' => '07:30', 'end' => '11:30'],
    'Noite' => ['start' => '19:30', 'end' => '23:30'],
    'Integral' => ['start' => '07:30', 'end' => '17:30']
];

$results = [];
foreach ($periods as $name => $times) {
    // For general status, we check if they are busy in any of the requested range's days
    $is_busy_any_day = false;
    $itTemp = new DateTime($data_inicio);
    $itEndTemp = new DateTime($data_fim);
    while ($itTemp <= $itEndTemp) {
        if (isBusy($agendas, $times['start'], $times['end'], $daysMap[(int) $itTemp->format('w')])) {
            $is_busy_any_day = true;
            break;
        }
        $itTemp->modify('+1 day');
    }
    $results['periods'][$name] = $is_busy_any_day ? 'busy' : 'free';
}

// Return busy days for the SPECIFIC requested time slot
$h_start = isset($_GET['h_start']) ? $_GET['h_start'] : $periods['Manhã']['start'];
$h_end = isset($_GET['h_end']) ? $_GET['h_end'] : $periods['Manhã']['end'];

$busy_days = [];
$it = new DateTime($data_inicio);
$itEnd = new DateTime($data_fim);
while ($it <= $itEnd) {
    $w = (int) $it->format('w');
    if (isBusy($agendas, $h_start, $h_end, $daysMap[$w])) {
        $busy_days[$daysMap[$w]] = true;
    }
    $it->modify('+1 day');
}

$results['busy_days'] = array_keys($busy_days);

echo json_encode($results);
?>