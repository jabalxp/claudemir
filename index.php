<?php
require_once 'php/configs/db.php';
require_once 'php/configs/utils.php';
include 'php/components/header.php';

// ---- Professor Dashboard ----
if (isProfessor() && getUserDocenteId()) {
    $did = (int) getUserDocenteId();
    $docente = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Docente WHERE id = $did"));
    $ch_contratual = (int) ($docente['carga_horaria_contratual'] ?? 0);

    $mes_sel = $_GET['mes_sel'] ?? date('Y-m');
    $primeiro_dia = date('Y-m-01', strtotime($mes_sel . '-01'));
    $ultimo_dia = date('Y-m-t', strtotime($mes_sel . '-01'));
    $nome_mes = strftime('%B %Y', strtotime($primeiro_dia));

    // Calculate worked hours this month
    $horas_trabalhadas = 0;
    $aulas_mes = 0;
    $res_ag = mysqli_query($conn, "
        SELECT a.dia_semana, a.horario_inicio, a.horario_fim, t.data_inicio, t.data_fim
        FROM Agenda a
        JOIN Turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
          AND t.data_inicio <= '$ultimo_dia'
          AND t.data_fim >= '$primeiro_dia'
    ");
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
    while ($ag = mysqli_fetch_assoc($res_ag)) {
        $it = new DateTime(max($primeiro_dia, $ag['data_inicio']));
        $itFim = new DateTime(min($ultimo_dia, $ag['data_fim']));
        while ($it <= $itFim) {
            $w = (int) $it->format('w');
            if (isset($daysMap[$w]) && $daysMap[$w] === trim($ag['dia_semana'])) {
                $inicio = new DateTime($ag['horario_inicio']);
                $fim = new DateTime($ag['horario_fim']);
                $diff = $fim->diff($inicio);
                $horas_trabalhadas += $diff->h + ($diff->i / 60);
                $aulas_mes++;
            }
            $it->modify('+1 day');
        }
    }
    $horas_restantes = max(0, $ch_contratual - $horas_trabalhadas);
    $pct_trabalhado = $ch_contratual > 0 ? round(($horas_trabalhadas / $ch_contratual) * 100) : 0;

    // Upcoming classes — fetch all active agendas for this professor
    $proximas_aulas_raw = mysqli_fetch_all(mysqli_query($conn, "
        SELECT c.nome AS curso_nome, t.data_inicio, t.data_fim, a.dia_semana, a.horario_inicio, a.horario_fim, a.periodo
        FROM Agenda a
        JOIN Turma t ON a.turma_id = t.id
        JOIN Curso c ON t.curso_id = c.id
        WHERE a.docente_id = $did AND t.data_fim >= '" . date('Y-m-d') . "'
        ORDER BY a.periodo ASC, t.data_inicio ASC
    "), MYSQLI_ASSOC);

    // Calculate the next actual class date for each agenda entry
    $diasMap = ['Domingo' => 0, 'Segunda-feira' => 1, 'Terça-feira' => 2, 'Quarta-feira' => 3, 'Quinta-feira' => 4, 'Sexta-feira' => 5, 'Sábado' => 6];
    $diasNomePt = ['Domingo' => 'domingo', 'Segunda-feira' => 'segunda feira', 'Terça-feira' => 'terça feira', 'Quarta-feira' => 'quarta feira', 'Quinta-feira' => 'quinta feira', 'Sexta-feira' => 'sexta feira', 'Sábado' => 'sábado'];
    $hoje = new DateTime(date('Y-m-d'));

    // Group by period
    $aulas_por_periodo = [];
    foreach ($proximas_aulas_raw as $pa) {
        $target_w = $diasMap[trim($pa['dia_semana'])] ?? -1;
        if ($target_w < 0)
            continue;

        $start = new DateTime(max($pa['data_inicio'], $hoje->format('Y-m-d')));
        $end = new DateTime($pa['data_fim']);
        $proximo_dia = null;
        $iter = clone $start;
        while ($iter <= $end) {
            if ((int) $iter->format('w') === $target_w) {
                $proximo_dia = clone $iter;
                break;
            }
            $iter->modify('+1 day');
        }
        if (!$proximo_dia)
            continue;

        $dia_nome = $diasNomePt[trim($pa['dia_semana'])] ?? $pa['dia_semana'];
        $dia_num = (int) $proximo_dia->format('d');
        $pa['proximo_dia_label'] = $dia_nome . ' dia ' . $dia_num;
        $pa['proximo_dia_date'] = $proximo_dia;

        $periodo = $pa['periodo'] ?: 'Outro';
        $aulas_por_periodo[$periodo][] = $pa;
    }

    // Keep at most 5 total entries
    $total_shown = 0;
    $max_show = 5;
    foreach ($aulas_por_periodo as &$lista) {
        $remaining = $max_show - $total_shown;
        if ($remaining <= 0) {
            $lista = [];
            continue;
        }
        $lista = array_slice($lista, 0, $remaining);
        $total_shown += count($lista);
    }
    unset($lista);
    ?>

    <div class="dashboard-home">
        <div class="welcome-banner">
            <div class="welcome-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></div><br>
            <h2><i class="bi bi-speedometer2"></i> Meu Painel — <?= htmlspecialchars($docente['nome'] ?? getUserName()) ?>
            </h2><br>
            <p>Área: <strong><?= htmlspecialchars($docente['area_conhecimento'] ?? 'N/A') ?></strong> · Carga Horária
                Contratual: <strong><?= $ch_contratual ?>h/mês</strong></p>
        </div>

        <div class="filter-bar" style="margin-top: 20px;">
            <form method="GET" action="index.php" class="filter-form">
                <label><i class="bi bi-calendar-event"></i> Mês:</label>
                <input type="month" name="mes_sel" value="<?= $mes_sel ?>" class="form-input" onchange="this.form.submit()">
            </form>
        </div>

        <div class="stats-grid" style="margin-top: 20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 125, 50, 0.1); color: #2e7d32;"><i
                        class="fas fa-clock"></i></div>
                <div class="stat-number"><?= round($horas_trabalhadas, 1) ?>h</div>
                <div class="stat-label">Horas Trabalhadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(237, 28, 36, 0.1); color: var(--primary-red);"><i
                        class="fas fa-hourglass-half"></i></div>
                <div class="stat-number"><?= round($horas_restantes, 1) ?>h</div>
                <div class="stat-label">Horas Restantes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: #1976d2;"><i
                        class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?= $aulas_mes ?></div>
                <div class="stat-label">Aulas no Mês</div>
            </div>
            <div class="stat-card" onclick="location.href='php/views/planejamento.php'" style="cursor: pointer;">
                <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;"><i
                        class="fas fa-calendar-check"></i></div>
                <div class="stat-number"><?= $pct_trabalhado ?>%</div>
                <div class="stat-label">Carga Cumprida</div>
                <a href="php/views/planejamento.php" class="stat-link" style="color: #9c27b0;">Ver Calendário <i
                        class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="dashboard-grid" style="margin-top: 25px;">
            <div class="dash-section" style="grid-column: 1 / -1;">
                <div class="dash-section-header">
                    <h3><i class="fas fa-chart-bar" style="color: var(--primary-red);"></i> Progresso Mensal</h3>
                </div>
                <div class="dash-section-body">
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px;">
                            <span><strong><?= round($horas_trabalhadas, 1) ?>h</strong> trabalhadas</span>
                            <span><strong><?= round($horas_restantes, 1) ?>h</strong> restantes de
                                <strong><?= $ch_contratual ?>h</strong></span>
                        </div>
                        <div class="mini-avail-bar" style="height: 28px; border-radius: 14px; overflow: hidden;">
                            <div class="mini-avail-busy"
                                style="width: <?= min($pct_trabalhado, 100) ?>%; background: linear-gradient(90deg, #2e7d32, #4caf50); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem;">
                                <?= $pct_trabalhado > 10 ? $pct_trabalhado . '%' : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-section" style="grid-column: 1 / -1;">
                <div class="dash-section-header">
                    <h3><i class="fas fa-rocket" style="color: #ff8f00;"></i> Próximas Aulas</h3>
                </div>
                <div class="dash-section-body">
                    <?php if (empty($aulas_por_periodo)): ?>
                        <p class="text-center" style="color: var(--text-muted);">Nenhuma aula futura agendada.</p>
                    <?php else: ?>
                        <?php foreach ($aulas_por_periodo as $periodo => $aulas_periodo): ?>
                            <?php if (empty($aulas_periodo))
                                continue; ?>
                            <div style="margin-bottom: 16px;">
                                <div
                                    style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 2px solid rgba(237, 28, 36, 0.15);">
                                    <i class="fas fa-sun" style="margin-right: 4px;"></i> <?= htmlspecialchars($periodo) ?>
                                </div>
                                <?php foreach ($aulas_periodo as $pa): ?>
                                    <div class="city-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                                        <div style="font-weight: 700; font-size: .9rem;"><?= htmlspecialchars($pa['curso_nome']) ?>
                                        </div>
                                        <div style="font-size: .8rem; color: var(--text-muted);">
                                            <?= $pa['dia_semana'] ?> · <?= substr($pa['horario_inicio'], 0, 5) ?> -
                                            <?= substr($pa['horario_fim'], 0, 5) ?>
                                        </div>
                                        <div style="font-size: .78rem; color: var(--primary-red); font-weight: 600;">
                                            <i class="fas fa-calendar"></i> <?= htmlspecialchars($pa['proximo_dia_label']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'php/components/footer.php'; ?>
    <?php
    exit; // Stop here for professors
}
// ---- End Professor Dashboard ----

// ---- CRI Dashboard ----
if (isCri()) {
    $count_aulas_cri = mysqli_fetch_row(mysqli_query($conn, "
        SELECT COUNT(DISTINCT a.id)
        FROM Agenda a
        JOIN Turma t ON a.turma_id = t.id
        WHERE t.data_inicio <= '" . date('Y-m-t') . "' AND t.data_fim >= '" . date('Y-m-01') . "'
    "))[0];

    $count_reservas_cri = mysqli_fetch_row(mysqli_query($conn, "
        SELECT COUNT(*) FROM reservas WHERE usuario_id = $auth_user_id AND status = 'ativo'
    "))[0];
    ?>

    <div class="dashboard-home">
        <div class="welcome-banner">
            <div class="welcome-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></div><br>
            <h2><i class="bi bi-speedometer2"></i> Painel CRI — <?= htmlspecialchars(getUserName()) ?></h2><br>
            <p>Visualize o planejamento e gerencie reservas de professores.</p>
        </div>

        <div class="stats-grid" style="margin-top: 20px;">
            <div class="stat-card" onclick="location.href='php/views/planejamento.php'" style="cursor: pointer;">
                <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number"><?= $count_aulas_cri ?></div>
                <div class="stat-label">Aulas Agendadas (Mês)</div>
                <a href="php/views/planejamento.php" class="stat-link" style="color: #9c27b0;">Ver Planejamento <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="stat-card" onclick="location.href='php/views/gerenciar_reservas.php'" style="cursor: pointer;">
                <div class="stat-icon" style="background: rgba(21, 101, 192, 0.1); color: #1565c0;"><i class="fas fa-bookmark"></i></div>
                <div class="stat-number"><?= $count_reservas_cri ?></div>
                <div class="stat-label">Minhas Reservas Ativas</div>
                <a href="php/views/gerenciar_reservas.php" class="stat-link" style="color: #1565c0;">Gerenciar Reservas <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <?php include 'php/components/footer.php'; ?>
    <?php
    exit; // Stop here for CRI
}
// ---- End CRI Dashboard ----
?>

<?php
$count_prof = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM Docente"))[0];
$count_salas = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM Ambiente"))[0];
$count_turmas = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM Turma"))[0];
$count_cursos = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM Curso"))[0];

$filtro_prof = $_GET['filtro_prof'] ?? 'mais_livre';
$filtro_area = $_GET['filtro_area'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';
$mes_sel = $_GET['mes_sel'] ?? date('Y-m');
$primeiro_dia_mes = date('Y-m-01', strtotime($mes_sel . '-01'));
$ultimo_dia_mes = date('Y-m-t', strtotime($mes_sel . '-01'));

// Count only classes in the selected month
$count_aulas = mysqli_fetch_row(mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.id) 
    FROM Agenda a 
    JOIN Turma t ON a.turma_id = t.id 
    WHERE t.data_inicio <= '$ultimo_dia_mes' AND t.data_fim >= '$primeiro_dia_mes'
"))[0];
$total_dias_uteis = contarDiasUteisNoMes($primeiro_dia_mes, $ultimo_dia_mes);

$where_clauses = [];
if (!empty($filtro_area)) {
    $safe_area = mysqli_real_escape_string($conn, $filtro_area);
    $where_clauses[] = "area_conhecimento = '$safe_area'";
}
if (!empty($filtro_nome)) {
    $safe_nome = mysqli_real_escape_string($conn, $filtro_nome);
    $where_clauses[] = "nome LIKE '%$safe_nome%'";
}

// Logic: Only show results if a filter is active
$is_filtered = !empty($where_clauses);
$where_sql = $is_filtered ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
$profs_query = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM Docente $where_sql");

$prof_resumo_temp = [];
if ($profs_query) {
    while ($d = mysqli_fetch_assoc($profs_query)) {
        $did_tmp = (int) $d['id'];
        $dias_ocup_mes_selecionado = calcularDiasOcupadosNoMes($conn, $did_tmp, $primeiro_dia_mes, $ultimo_dia_mes);
        $prof_resumo_temp[] = [
            'id' => $did_tmp,
            'nome' => $d['nome'],
            'area' => $d['area_conhecimento'],
            'dias_ocupados_mes' => $dias_ocup_mes_selecionado
        ];
    }
}

usort($prof_resumo_temp, function ($a, $b) use ($filtro_prof) {
    if ($filtro_prof === 'mais_ocupado') {
        if ($b['dias_ocupados_mes'] !== $a['dias_ocupados_mes']) {
            return $b['dias_ocupados_mes'] - $a['dias_ocupados_mes'];
        }
        return strcmp($a['nome'], $b['nome']);
    }
    if ($a['dias_ocupados_mes'] !== $b['dias_ocupados_mes']) {
        return $a['dias_ocupados_mes'] - $b['dias_ocupados_mes'];
    }
    return strcmp($a['nome'], $b['nome']);
});

$prof_resumo_final = [];
if (!$is_filtered) {
    // Default View: 5 professors, 1 per distinct area
    $areas_adicionadas = [];
    foreach ($prof_resumo_temp as $pr) {
        $area = trim((string) $pr['area']);
        if ($area === '')
            continue; // skip those without area

        if (!in_array($area, $areas_adicionadas)) {
            $prof_resumo_final[] = $pr;
            $areas_adicionadas[] = $area;
        }
        if (count($prof_resumo_final) >= 5)
            break;
    }
} else {
    // Filter View: just top 5
    $prof_resumo_final = array_slice($prof_resumo_temp, 0, 5);
}

$prof_resumo = [];
$ano_atual = date('Y', strtotime($mes_sel . '-01'));
$meses_nomes_curtos = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

foreach ($prof_resumo_final as $pr) {
    $did_tmp = $pr['id'];
    $meses_dados = [];
    $total_anual_pct = 0;

    for ($m = 1; $m <= 12; $m++) {
        $mes_str = str_pad($m, 2, '0', STR_PAD_LEFT);
        $primeiro_dia_m = "$ano_atual-$mes_str-01";
        $ultimo_dia_m = date('Y-m-t', strtotime($primeiro_dia_m));

        $dias_o = calcularDiasOcupadosNoMes($conn, $did_tmp, $primeiro_dia_m, $ultimo_dia_m);
        $dias_u = contarDiasUteisNoMes($primeiro_dia_m, $ultimo_dia_m);
        $pct = $dias_u > 0 ? round(($dias_o / $dias_u) * 100) : 0;

        $status_class = 'livre';
        $bg_color = '#388e3c';
        if ($pct > 70) {
            $status_class = 'alto';
            $bg_color = 'var(--primary-red)';
        } elseif ($pct > 30) {
            $status_class = 'medio';
            $bg_color = '#ff9800';
        }

        $meses_dados[] = [
            'nome' => $meses_nomes_curtos[$m - 1],
            'pct' => $pct,
            'status' => $status_class,
            'color' => $bg_color
        ];
        $total_anual_pct += $pct;
    }

    $media_anual = round($total_anual_pct / 12);

    $geral_status = 'Livre';
    $geral_color = '#388e3c';
    if ($media_anual > 70) {
        $geral_status = 'Alto';
        $geral_color = 'var(--primary-red)';
    } elseif ($media_anual > 30) {
        $geral_status = 'Médio';
        $geral_color = '#ff9800';
    }

    $prof_resumo[] = [
        'id' => $did_tmp,
        'nome' => $pr['nome'],
        'area' => $pr['area'],
        'meses' => $meses_dados,
        'media_anual' => $media_anual,
        'geral_status' => $geral_status,
        'geral_color' => $geral_color
    ];
}

$areas_query = mysqli_query($conn, "SELECT DISTINCT area_conhecimento FROM Docente WHERE area_conhecimento IS NOT NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC");
$areas_list = mysqli_fetch_all($areas_query, MYSQLI_ASSOC);

$turmas_cidade = mysqli_fetch_all(mysqli_query($conn, "
    SELECT COALESCE(amb.cidade, 'Sede') AS cidade, COUNT(t.id) AS total
    FROM Turma t JOIN Ambiente amb ON t.ambiente_id = amb.id
    GROUP BY COALESCE(amb.cidade, 'Sede') ORDER BY total DESC
"), MYSQLI_ASSOC);

$proximas = mysqli_fetch_all(mysqli_query($conn, "
    SELECT t.id, amb.cidade, c.nome AS curso_nome, t.data_inicio, t.tipo
    FROM Turma t JOIN Curso c ON t.curso_id = c.id JOIN Ambiente amb ON t.ambiente_id = amb.id
    WHERE t.data_inicio >= '" . date('Y-m-d') . "' ORDER BY t.data_inicio ASC LIMIT 5
"), MYSQLI_ASSOC);

$cores = ['#e53935', '#1976d2', '#388e3c', '#ff8f00', '#9c27b0', '#00838f', '#6d4c41'];
?>

<div class="dashboard-home dashboard-container">
    <div class="welcome-banner">
        <div class="welcome-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></div><br>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2><i class="bi bi-speedometer2"></i> Dashboard — Gestão Escolar SENAI</h2><br>
                <p>Visão geral do sistema com turmas, professores, ambientes e agenda.</p>
            </div>
            <button class="btn btn-primary" onclick="openSimulationModal()"
                style="background: #2e7d32; border-color: #1b5e20; box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);">
                <i class="fas fa-microscope"></i> Modo Simulação (Modo Seguro)
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <?php
        $stats = [
            ['Professores', $count_prof, 'fa-chalkboard-teacher', 'var(--primary-red)', 'php/views/professores.php', 'Gerenciar'],
            ['Ambientes', $count_salas, 'fa-door-open', '#1976d2', 'php/views/salas.php', 'Gerenciar'],
            ['Turmas', $count_turmas, 'fa-users', '#388e3c', 'php/views/turmas.php', 'Gerenciar'],
            ['Cursos', $count_cursos, 'fa-graduation-cap', '#ff8f00', 'php/views/cursos.php', 'Gerenciar'],
            ['Aulas Agendadas', $count_aulas, 'fa-calendar-check', '#9c27b0', 'php/views/planejamento.php', 'Planejamento'],
        ];
        foreach ($stats as $s): ?>
            <div class="stat-card" onclick="location.href='<?= $prefix . $s[4] ?>'" style="cursor: pointer;">
                <div class="stat-icon" style="background: <?= $s[3] ?>1a; color: <?= $s[3] ?>;">
                    <i class="fas <?= $s[2] ?>"></i>
                </div>
                <div class="stat-number"><?= $s[1] ?></div>
                <div class="stat-label"><?= $s[0] ?></div>
                <a href="<?= $prefix . $s[4] ?>" class="stat-link" style="color: <?= $s[3] ?>;"><?= $s[5] ?> <i
                        class="fas fa-arrow-right"></i></a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <label><i class="bi bi-funnel-fill"></i> Filtrar Professores:</label>
            <div class="form-group filter-group">
                <label class="filter-month-label"><i class="bi bi-calendar-event"></i> Mês:</label>
                <input type="month" name="mes_sel" value="<?= $mes_sel ?>" class="form-input"
                    onchange="this.form.submit()">
            </div>
            <input type="text" name="filtro_nome" value="<?= htmlspecialchars($filtro_nome) ?>" class="form-input"
                placeholder="Buscar professor pelo nome..." style="max-width: 220px;">
            <select name="filtro_area" onchange="this.form.submit()">
                <option value="">Todas as Áreas</option>
                <?php foreach ($areas_list as $al):
                    $ap = $al['area_conhecimento']; ?>
                    <option value="<?= htmlspecialchars($ap) ?>" <?= $filtro_area == $ap ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ap) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filtro_prof" onchange="this.form.submit()">
                <option value="mais_livre" <?= $filtro_prof == 'mais_livre' ? 'selected' : '' ?>>Mais disponíveis</option>
                <option value="mais_ocupado" <?= $filtro_prof == 'mais_ocupado' ? 'selected' : '' ?>>Menos disponíveis
                </option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;"><i
                    class="fas fa-search"></i></button>
            <?php if ($filtro_prof !== 'mais_livre' || !empty($filtro_area) || !empty($filtro_nome)): ?>
                <a href="index.php" class="btn-export btn-clear"><i class="fas fa-times"></i>
                    Limpar</a>
            <?php endif; ?>
        </form>
        <a href="php/views/tabela_professores.php" class="btn btn-primary btn-tabela">
            <i class="fas fa-table"></i> Tabela Professores
        </a>
    </div>

    <div class="dashboard-grid">
        <div class="dash-section">
            <div class="dash-section-header"
                style="justify-content: space-between; display: flex; align-items: center; padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 1.1rem;">
                    <i class="fas fa-users" style="color: var(--primary-red);"></i> Resumo Anual de Horários
                </h3>
                <div style="display: flex; gap: 15px; font-size: 0.8rem; font-weight: 600;">
                    <span style="display: flex; align-items: center; gap: 5px; color: #4caf50;"><span
                            style="width: 10px; height: 10px; background: #388e3c; display: inline-block;"></span>
                        Livre</span>
                    <span style="display: flex; align-items: center; gap: 5px; color: #ff9800;"><span
                            style="width: 10px; height: 10px; background: #ff9800; display: inline-block;"></span>
                        Médio</span>
                    <span style="display: flex; align-items: center; gap: 5px; color: var(--primary-red);"><span
                            style="width: 10px; height: 10px; background: var(--primary-red); display: inline-block;"></span>
                        Alto</span>
                </div>
            </div>
            <div class="dash-section-body" style="padding: 0;">
                <?php if (empty($prof_resumo)): ?>
                    <div class="td-empty" style="padding: 40px; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-search"
                            style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: .4;"></i>
                        <?php if (empty($where_clauses)): ?>
                            Use o filtro acima para pesquisar um professor e ver seu resumo anual.
                        <?php else: ?>
                            Nenhum professor encontrado com esses critérios.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="prof-anual-list" style="display: flex; flex-direction: column; gap: 28px;">
                        <?php foreach ($prof_resumo as $pr): ?>
                            <div class="prof-anual-item">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                                    <div style="font-weight: 800; font-size: 0.95rem; text-transform: uppercase;">
                                        <?= htmlspecialchars($pr['nome']) ?> <span
                                            style="color: #888; font-weight: normal; font-size: 0.8rem; text-transform: none;">·
                                            <?= htmlspecialchars($pr['area']) ?></span>
                                    </div>
                                    <div style="color: <?= $pr['geral_color'] ?>; font-weight: 800; font-size: 0.95rem;">
                                        <?= $pr['media_anual'] ?>% <?= $pr['geral_status'] ?>
                                    </div>
                                </div>

                                <div class="annual-bar"
                                    style="display: flex; height: 26px; border-radius: 6px; overflow: hidden; background: var(--bg-color); border: 1px solid var(--border-color); margin-bottom: 6px;">
                                    <?php foreach ($pr['meses'] as $m): ?>
                                        <div style="flex: 1; border-right: 1px solid var(--border-color); background: <?= $m['color'] ?>;"
                                            title="<?= $m['nome'] ?>: <?= $m['pct'] ?>%"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="annual-labels"
                                    style="display: flex; text-align: center; color: var(--text-muted); font-size: 0.75rem;">
                                    <?php foreach ($pr['meses'] as $m): ?>
                                        <div style="flex: 1;"><?= $m['nome'] ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="text-align: center; margin-top: 30px; margin-bottom: 10px;">
                        <a href="php/views/tabela_professores.php" class="btn btn-primary"
                            style="background: var(--primary-red); border-color: var(--primary-red); padding: 10px 24px; border-radius: 8px; font-weight: 700; display: inline-block; color: #fff; text-decoration: none;">Ver
                            Todos os Professores</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-column">
            <div class="dash-section">
                <div class="dash-section-header">
                    <h3><i class="fas fa-map-marked-alt" style="color: #1976d2;"></i> Turmas por Cidade</h3>
                </div>
                <div class="dash-section-body">
                    <?php if (empty($turmas_cidade)): ?>
                        <p class="text-center" style="color: var(--text-muted);">Nenhuma turma cadastrada.</p>
                    <?php else: ?>
                        <?php foreach ($turmas_cidade as $i => $tc):
                            $cor = $cores[$i % count($cores)]; ?>
                            <div class="city-list-item">
                                <div style="display: flex; align-items: center;">
                                    <span class="city-dot" style="background: <?= $cor ?>;"></span>
                                    <span style="font-weight: 600;"><?= htmlspecialchars($tc['cidade']) ?></span>
                                </div>
                                <span
                                    style="font-weight: 800; font-size: 1.1rem; color: <?= $cor ?>;"><?= $tc['total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dash-section">
                <div class="dash-section-header">
                    <h3><i class="fas fa-rocket" style="color: #ff8f00;"></i> Próximas Turmas</h3>
                </div>
                <div class="dash-section-body">
                    <?php if (empty($proximas)): ?>
                        <p class="text-center" style="color: var(--text-muted);">Nenhuma turma futura.</p>
                    <?php else: ?>
                        <?php foreach ($proximas as $pt): ?>
                            <div class="city-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                                <div style="font-weight: 700; font-size: .9rem;">Turma #<?= $pt['id'] ?></div>
                                <div style="font-size: .8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($pt['curso_nome']) ?> · <?= $pt['tipo'] ?>
                                    <?php if (!empty($pt['cidade'])): ?> · <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($pt['cidade']) ?>         <?php endif; ?>
                                </div>
                                <div style="font-size: .78rem; color: var(--primary-red); font-weight: 600;">
                                    <i class="fas fa-calendar"></i> Início: <?= date('d/m/Y', strtotime($pt['data_inicio'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboard_simulation.js"></script>
<?php include 'php/components/footer.php'; ?>