<link rel="stylesheet" href="../../css/agenda_professores.css">

<?php
$selected_prof_id = isset($_GET['docente_id']) ? $_GET['docente_id'] : '';
$selected_prof_nome = '';

/* Fallback: If no docente_id is provided but we only have 1 professor (e.g. from a name search)
if (empty($selected_prof_id) && !empty($professores) && count($professores) === 1) {
    $selected_prof_id = $professores[0]['id'];
    $selected_prof_nome = $professores[0]['nome'];
} else */ if (empty($selected_prof_id) && !empty($_GET['search'])) {
    // Second fallback: Try to match by name if search is present
    foreach ($docentes as $d) {
        if (trim(strtolower($d['nome'])) === trim(strtolower(trim($_GET['search'])))) {
            $selected_prof_id = $d['id'];
            $selected_prof_nome = $d['nome'];
            break;
        }
    }
} else if ($selected_prof_id) {
    // Standard lookup
    foreach ($docentes as $d) {
        if ($d['id'] == $selected_prof_id) {
            $selected_prof_nome = $d['nome'];
            break;
        }
    }
}
?>

<?php
$docente_param = $selected_prof_id ? '&docente_id=' . $selected_prof_id : '';
if (empty($selected_prof_id) && !empty($_GET['search'])) {
    $docente_param .= '&search=' . urlencode($_GET['search']);
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-calendar-check"></i> Agenda de Professores</h2>
        <div style="display: flex; align-items: center; gap: 12px; margin-top: 10px;">
            <div class="view-selector">
                <a href="?view_mode=timeline&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>"><i class="fas fa-grip-lines"></i> Timeline</a>
                <a href="?view_mode=blocks&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'blocks' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Blocos</a>
                <a href="?view_mode=calendar&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'calendar' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Calendário</a>
                <a href="?view_mode=semestral&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'semestral' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> Semestral</a>
            </div>
        </div>
    </div>
</div>

<!-- Unified Selection and Month Navigation Section -->
<div class="card" style="margin-bottom: 20px; background: var(--card-bg); border: 1px solid var(--border-color);">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap;">
        <div style="display: flex; flex-direction: column; gap: 10px; flex: 1; min-width: 250px;">
            <label style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Professor Selecionado</label>
            <?php if (!isProfessor()): ?>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="button" class="btn btn-primary" id="btn-selecionar-professor"
                        style="background: <?= $selected_prof_id ? '#2e7d32' : '#ed1c16' ?>; border-color: <?= $selected_prof_id ? '#1b5e20' : '#ed1c16' ?>; padding: 10px 24px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-plus"></i>
                        <span id="btn-prof-label"><?= $selected_prof_id ? htmlspecialchars($selected_prof_nome) : 'Selecionar Professor' ?></span>
                    </button>
                </div>
            <?php else: ?>
                <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-color);">
                    <?= htmlspecialchars(getUserName()) ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
            <label style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Navegação por Mês</label>
            <div style="display: flex; align-items: center; gap: 10px; background: var(--bg-color); padding: 5px; border-radius: 10px; border: 1px solid var(--border-color);">
                <a href="?view_mode=<?php echo $view_mode; ?>&month=<?php echo $prev_month; ?>&page=<?php echo $page; ?><?php echo $docente_param; ?>" class="month-btn" style="width:34px;height:34px;text-decoration:none;color:var(--text-color); display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 6px;"><i class="fas fa-chevron-left" style="font-size:0.75rem;"></i></a>
                <span style="font-weight: 800; font-size: 0.95rem; min-width: <?php echo $view_mode == 'semestral' ? '220px' : '140px'; ?>; text-align: center; text-transform: capitalize; color: var(--text-color);"><?php echo $month_label; ?></span>
                <a href="?view_mode=<?php echo $view_mode; ?>&month=<?php echo $next_month; ?>&page=<?php echo $page; ?><?php echo $docente_param; ?>" class="month-btn" style="width:34px;height:34px;text-decoration:none;color:var(--text-color); display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 6px;"><i class="fas fa-chevron-right" style="font-size:0.75rem;"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="availability-section" id="availability-section" style="margin-bottom: 20px; background: var(--card-bg); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px; display: block;">
    <div class="period-selector-container" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Pré-definir Período:</span>
            <div class="period-btns" style="display: flex; gap: 8px;">
                <button type="button" class="period-btn period-btn-all" data-periodo="" id="btn-todos" style="padding: 8px 16px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-th" style="font-size: 0.9rem;"></i> Todos
                </button>
                <button type="button" class="period-btn" data-periodo="Manhã" data-inicio="07:30" data-fim="13:30" id="btn-manha" style="padding: 8px 16px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-sun" style="font-size: 0.9rem;"></i> Manhã
                </button>
                <button type="button" class="period-btn" data-periodo="Tarde" data-inicio="13:30" data-fim="17:30" id="btn-tarde" style="padding: 8px 16px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-cloud-sun" style="font-size: 0.9rem;"></i> Tarde
                </button>
                <button type="button" class="period-btn" data-periodo="Noite" data-inicio="19:30" data-fim="23:30" id="btn-noite" style="padding: 8px 16px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-moon" style="font-size: 0.9rem;"></i> Noite
                </button>
                <button type="button" class="period-btn" data-periodo="Integral" data-inicio="07:30" data-fim="17:30" id="btn-integral" style="padding: 8px 16px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-circle" style="font-size: 0.9rem;"></i> Integral
                </button>
            </div>
        </div>
        <div class="avail-legend" style="display: flex; gap: 15px; font-size: 0.75rem; color: var(--text-muted);">
            <span style="display: flex; align-items: center; gap: 5px;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #4caf50;"></span> Disponível</span>
            <span style="display: flex; align-items: center; gap: 5px;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #f44336;"></span> Ocupado</span>
            <span style="display: flex; align-items: center; gap: 5px;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #ffb300;"></span> Reservado</span>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
        <button type="button" class="btn btn-primary" id="btn-modo-reserva-unificado" style="background: #ff8f00; border-color: #e65100; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="handleModoReservaClick()">
            <i class="fas fa-bookmark" style="margin-right: 8px;"></i> Modo Reserva
        </button>
        <button type="button" class="btn btn-primary" id="btn-confirmar-reserva" style="display: none; background: #2e7d32; border-color: #1b5e20; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="confirmReservations()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Confirmar Reserva
        </button>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-secondary" id="btn-remover-selecionados" style="display: none; background: #d32f2f; border-color: #c62828; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="batchRemoveReservations()">
                <i class="fas fa-trash-alt" style="margin-right: 8px;"></i> Remover Selecionados
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-back" id="btn-cancelar-reserva" style="display: none; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="handleCancelarReservaClick()">
            <i class="fas fa-times" style="margin-right: 8px;"></i> Cancelar
        </button>
    </div>

    <div id="availability-bar" class="avail-bar-container" style="margin-top: 20px; height: 24px; background: var(--bg-color); border-radius: 12px; overflow: hidden; position: relative; border: 1px solid var(--border-color);">
        <div class="avail-bar-track" style="height: 100%; display: flex;">
            <div class="avail-bar-free" style="width: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: var(--text-color);">Selecione um professor</div>
        </div>
    </div>

    <div class="avail-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
        <div id="avail-status-text" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"></div>
        <button class="btn btn-agendar-bar" id="btn-agendar-bar" onclick="openCalendarScheduleModal()" style="display: none; background: #2196f3; border-color: #1976d2; color: #fff; padding: 8px 20px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; align-items: center; gap: 8px;">
            <i class="fas fa-plus-circle"></i> Cadastrar Horário
        </button>
    </div>
</div>

<div class="filter-header" style="justify-content: flex-end; margin-bottom: 15px;">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.7;">Exibindo <?php echo count($professores); ?> de <?php echo $total_count; ?> professores</div>
    </div>
</div>

<!-- Global scripts and modal for Selection -->
<script>
    window.__docentesData = <?= $docentes_json ?>;
    window.__isAdmin = <?= json_encode(isAdmin()) ?>;

    function handleModoReservaClick() {
        const viewMode = "<?php echo $view_mode; ?>";
        if (viewMode === 'calendar') {
            if (typeof window.toggleReservationMode === 'function') window.toggleReservationMode();
        } else {
            if (typeof window.toggleReservaMode === 'function') window.toggleReservaMode();
        }
    }

    function handleCancelarReservaClick() {
        const viewMode = "<?php echo $view_mode; ?>";
        if (viewMode === 'calendar') {
            if (typeof window.cancelReservationMode === 'function') window.cancelReservationMode();
        } else {
            if (typeof window.cancelReservaSelection === 'function') window.cancelReservaSelection();
            // Para o modo Parafal, também precisamos sair do modo reserva visualmente
            if (typeof window.toggleReservaMode === 'function' && window.reservaModeActive) window.toggleReservaMode();
        }
    }
</script>

<!-- Hidden select for JS compatibility -->
<select id="calendar-docente-select" style="display:none;">
    <option value="">Escolha um professor...</option>
    <?php foreach ($docentes as $p): ?>
        <option value="<?= $p['id'] ?>" <?= (isset($_GET['docente_id']) && $_GET['docente_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nome']) ?>
        </option>
    <?php endforeach; ?>
</select>

<?php if ($view_mode == 'calendar'): ?>
<?php if (!defined('PLANEJAMENTO_EMBED')) define('PLANEJAMENTO_EMBED', true); ?>
<?php $_GET['start_view'] = 'calendar'; include __DIR__ . '/planejamento.php'; ?>
<?php else: ?>
<div class="table-container">
    <?php if (empty($professores)): ?>
        <p style="text-align:center; padding: 50px; opacity:0.5;">Nenhum professor encontrado.</p>
    <?php else: ?>
        <?php foreach ($professores as $p): ?>
            <?php
            // Common data for all views
            $p_esp = $p['area_conhecimento'] ?? '';
            $livres = 0;
            for ($d = 1; $d <= $days_in_month; $d++) {
                $dt = sprintf("%s-%02d", $current_month, $d);
                $dow = date('N', strtotime($dt));
                if ($dow < 7 && !isset($agenda_data[$p['id']][$dt])) $livres++;
            }
            ?>

            <?php if ($view_mode == 'semestral'): ?>
                <?php
                $cur_m = (int)date('m', strtotime($current_month . '-01'));
                $cur_y = (int)date('Y', strtotime($current_month . '-01'));
                $semester_start = ($cur_m <= 6) ? 1 : 7;
                $semester_end = $semester_start + 5;
                $months_pt_sem = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                $sem_first = sprintf("%04d-%02d-01", $cur_y, $semester_start);
                $sem_last = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $cur_y, $semester_end)));
                $stmt_sem = $mysqli->prepare("SELECT a.data, t.sigla as turma_nome, a.horario_inicio, a.horario_fim FROM agenda a JOIN turma t ON a.turma_id = t.id WHERE a.docente_id = ? AND a.data BETWEEN ? AND ?");
                $p_id_sem = $p['id'];
                $stmt_sem->bind_param('iss', $p_id_sem, $sem_first, $sem_last);
                $stmt_sem->execute();
                $sem_results = $stmt_sem->get_result()->fetch_all(MYSQLI_ASSOC);
                $sem_busy = []; $sem_turno = [];
                foreach ($sem_results as $row) {
                    $sem_busy[$row['data']] = $row['turma_nome'];
                    if (!isset($sem_turno[$row['data']])) $sem_turno[$row['data']] = ['M'=>false,'T'=>false,'N'=>false];
                    $hi=$row['horario_inicio']; $hf=$row['horario_fim'];
                    if ($hi < '12:00:00') $sem_turno[$row['data']]['M'] = true;
                    if ($hi < '18:00:00' && $hf > '12:00:00') $sem_turno[$row['data']]['T'] = true;
                    if ($hf > '18:00:00' || $hi >= '18:00:00') $sem_turno[$row['data']]['N'] = true;
                }
                $total_sem_free = 0; $total_sem_busy = 0;
                for ($m = $semester_start; $m <= $semester_end; $m++) {
                    $ms = sprintf("%04d-%02d", $cur_y, $m);
                    $dc = (int)date('t', strtotime($ms . '-01'));
                    for ($dd = 1; $dd <= $dc; $dd++) {
                        $ddt = sprintf("%s-%02d", $ms, $dd);
                        $ddow = date('N', strtotime($ddt));
                        if ($ddow < 7 && !isset($sem_busy[$ddt])) $total_sem_free++;
                        if (isset($sem_busy[$ddt])) $total_sem_busy++;
                    }
                }
                $perc_free = ($total_sem_free + $total_sem_busy > 0) ? round(($total_sem_free / ($total_sem_free + $total_sem_busy)) * 100) : 0;
                ?>
                <div class="prof-row" data-prof-id="<?php echo $p['id']; ?>" style="padding-bottom: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 10px;">
                        <div onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" style="cursor:pointer; display:flex; align-items:center; gap:10px; flex: 1;">
                            <div style="width:32px; height:32px; background: linear-gradient(135deg, #e53935, #c62828); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem;"><?php echo mb_substr($p['nome'], 0, 1); ?></div>
                            <div>
                                <div style="font-weight:800; font-size:0.95rem;"><?php echo htmlspecialchars($p['nome']); ?></div>
                                <div style="font-size:0.75rem; color: #9ba1b0;"><?php echo htmlspecialchars($p_esp); ?></div>
                            </div>
                        </div>
                        <div style="display:flex; gap:12px; font-size:0.75rem; font-weight:700;">
                            <span style="color:#2e7d32;"><i class="fas fa-check-circle"></i> <?php echo $total_sem_free; ?></span>
                            <span style="color:#d32f2f;"><i class="fas fa-times-circle"></i> <?php echo $total_sem_busy; ?></span>
                            <span style="color:var(--text-muted);"><?php echo $perc_free; ?>%</span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <?php for ($m = $semester_start; $m <= $semester_end; $m++):
                            $month_str = sprintf("%04d-%02d", $cur_y, $m);
                            $days_count = (int)date('t', strtotime($month_str . '-01'));
                            $first_dow = (int)date('w', strtotime($month_str . '-01'));
                            $m_free = 0; $m_busy = 0;
                            for ($dd = 1; $dd <= $days_count; $dd++) {
                                $ddt = sprintf("%s-%02d", $month_str, $dd);
                                $ddow = (int)date('N', strtotime($ddt));
                                if ($ddow < 7 && !isset($sem_busy[$ddt])) $m_free++;
                                if (isset($sem_busy[$ddt])) $m_busy++;
                            }
                        ?>
                        <div class="sem-month-box">
                            <div class="sem-month-header"><span><?php echo $months_pt_sem[$m]; ?></span><span class="sem-month-stats"><span style="color:#2e7d32;"><?php echo $m_free; ?></span>/<span style="color:#d32f2f;"><?php echo $m_busy; ?></span></span></div>
                            <div class="sem-day-headers"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>
                            <div class="sem-calendar-grid">
                                <?php for ($e = 0; $e < $first_dow; $e++): ?><div class="sem-day sem-day-empty"></div><?php endfor; ?>
                                <?php for ($dd = 1; $dd <= $days_count; $dd++):
                                    $ddt = sprintf("%s-%02d", $month_str, $dd);
                                    $ddow = (int)date('N', strtotime($ddt));
                                    $is_sunday = ($ddow == 7); $is_saturday = ($ddow == 6);
                                    $is_busy = isset($sem_busy[$ddt]);
                                    $is_reserved_s = isset($reserva_data[$p['id']][$ddt]) ? $reserva_data[$p['id']][$ddt] : false;
                                    $st2 = isset($sem_turno[$ddt]) ? $sem_turno[$ddt] : null;
                                    $s_full = $st2 && (((($st2['M']?1:0)+($st2['T']?1:0)+($st2['N']?1:0)) >= 2));
                                    $is_partial_sem = $is_busy && !$s_full;
                                    $cell_class = 'sem-day-free';
                                    if ($is_sunday) $cell_class = 'sem-day-sunday';
                                    elseif ($is_reserved_s && !$is_reserved_s['own']) $cell_class = 'sem-day-reserved';
                                    elseif ($is_reserved_s && $is_reserved_s['own']) $cell_class = 'sem-day-reserved-own';
                                    elseif ($is_busy && $is_partial_sem) $cell_class = 'sem-day-partial';
                                    elseif ($is_busy) $cell_class = 'sem-day-busy';
                                    elseif ($is_saturday) $cell_class = 'sem-day-weekend';
                                    $clickable = (!$is_sunday && (!$is_busy || $is_partial_sem) && !($is_reserved_s && !$is_reserved_s['own']));
                                ?>
                                    <div class="sem-day <?php echo $cell_class; ?>" title="<?php echo $dd . ' ' . $months_pt_sem[$m]; ?>"
                                         <?php if ($clickable): ?>onclick="handleBarClick(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $ddt; ?>', this)"<?php endif; ?>><?php echo $dd; ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- TIMELINE / BLOCKS VIEW -->
                <div class="prof-row" data-prof-id="<?php echo $p['id']; ?>">
                    <div class="prof-info-header">
                        <div class="prof-name" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')">
                            <?php echo htmlspecialchars($p['nome']); ?>
                            <span class="prof-spec"> &middot; <?php echo htmlspecialchars($p_esp); ?></span>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 700;">
                            <span style="color: #2e7d32;"><?php echo $livres; ?> dias livres</span>
                            <button class="btn-nav" style="width: 30px; height: 30px; display: inline-flex; margin-left:10px;" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" title="Ver Calendário"><i class="fas fa-calendar-alt" style="font-size: 0.9rem;"></i></button>
                        </div>
                    </div>

                    <?php if ($view_mode == 'blocks'): ?>
                        <?php
                        // Build continuous blocks
                        $blocks = []; $cur_block = null;
                        for ($i = 1; $i <= $days_in_month; $i++) {
                            $dt = sprintf("%s-%02d", $current_month, $i);
                            $dow = date('N', strtotime($dt));
                            $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                            $is_reserved_b = isset($reserva_data[$p['id']][$dt]) ? $reserva_data[$p['id']][$dt] : false;
                            if ($dow == 7) { $status = 'sunday'; $label = 'Bloqueado'; }
                            elseif ($is_reserved_b && !$is_reserved_b['own']) { $status = 'reserved'; $label = 'Reservado'; }
                            elseif ($is_reserved_b && $is_reserved_b['own']) { $status = 'reserved_own'; $label = 'Minha Reserva'; }
                            elseif ($is_busy) { $status = 'busy:' . $is_busy; $label = $is_busy; }
                            else { $status = 'free'; $label = 'Livre'; }
                            if ($cur_block && $cur_block['status'] === $status) { $cur_block['end'] = $i; $cur_block['count']++; }
                            else { if ($cur_block) $blocks[] = $cur_block; $cur_block = ['start' => $i, 'end' => $i, 'status' => $status, 'label' => $label, 'count' => 1]; }
                        }
                        if ($cur_block) $blocks[] = $cur_block;
                        ?>
                        <div class="blocks-bar-wrapper">
                            <?php foreach ($blocks as $block):
                                $range_text = ($block['start'] == $block['end']) ? 'Dia ' . str_pad($block['start'], 2, '0', STR_PAD_LEFT) : 'Dia ' . str_pad($block['start'], 2, '0', STR_PAD_LEFT) . ' &ndash; ' . str_pad($block['end'], 2, '0', STR_PAD_LEFT);
                                if (strpos($block['status'], 'busy:') === 0) $bclass = 'block-seg-busy';
                                elseif ($block['status'] === 'reserved') $bclass = 'block-seg-reserved';
                                elseif ($block['status'] === 'reserved_own') $bclass = 'block-seg-reserved-own';
                                elseif ($block['status'] === 'sunday') $bclass = 'block-seg-sunday';
                                else $bclass = 'block-seg-free';
                                $first_dt = sprintf("%s-%02d", $current_month, $block['start']);
                                $is_clickable = ($block['status'] === 'free' || $block['status'] === 'reserved_own');
                            ?>
                                <div class="block-seg <?php echo $bclass; ?>" style="flex: <?php echo $block['count']; ?>;" title="<?php echo $range_text; ?>: <?php echo htmlspecialchars($block['label']); ?>"
                                     <?php if ($is_clickable): ?>onclick="handleBarClick(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $first_dt; ?>', this)"<?php endif; ?>>
                                    <span class="block-range"><?php echo $range_text; ?></span>
                                    <span class="block-label"><?php echo htmlspecialchars($block['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- TIMELINE VIEW -->
                        <?php $dias_nomes_curtos = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom']; ?>
                        <div class="timeline-bar-wrapper" style="height: 52px;">
                            <?php for ($i = 1; $i <= $days_in_month; $i++):
                                $dt = sprintf("%s-%02d", $current_month, $i);
                                $dow = date('N', strtotime($dt));
                                $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                                $is_reserved = isset($reserva_data[$p['id']][$dt]) ? $reserva_data[$p['id']][$dt] : false;
                                $class = "bar-seg-free"; $title = "Livre: " . $i;
                                $p_name_js = addslashes($p['nome']);
                                $onclick = "onclick=\"handleBarClick({$p['id']}, '{$p_name_js}', '{$dt}', this)\"";
                                if ($dow == 7) { $class = "bar-seg-sunday"; $title = "DOMINGO: $i"; $onclick = ""; }
                                elseif ($is_reserved && !$is_reserved['own']) { $class = "bar-seg-reserved"; $title = "RESERVADO &mdash; Dia $i"; $onclick = ""; }
                                elseif ($is_reserved && $is_reserved['own']) { $class = "bar-seg-reserved-own"; $title = "MINHA RESERVA &mdash; Dia $i"; }
                                elseif ($is_busy) { $class = "bar-seg-busy"; $title = "OCUPADO: $is_busy"; $onclick = ""; }
                                elseif ($dow == 6) { $class = "bar-seg-weekend"; $title = "Sábado: $i"; }
                            ?>
                                <div class="bar-seg <?php echo $class; ?>" title="<?php echo $title; ?>" <?php echo $onclick; ?> data-date="<?php echo $dt; ?>" data-prof="<?php echo $p['id']; ?>" style="flex-direction: column; gap: 2px;">
                                    <div style="line-height: 1;">Dia <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></div>
                                    <div style="font-size: 0.55rem; opacity: 0.8; font-weight: 700;"><?php echo $dias_nomes_curtos[$dow]; ?></div>
                                    <?php if ($is_reserved): ?><span class="reserva-badge <?php echo $is_reserved['own'] ? 'reserva-badge-own' : 'reserva-badge-other'; ?>" style="position:absolute;bottom:1px;font-size:0.45rem;padding:0 3px;"><i class="fas fa-bookmark"></i></span><?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php $total_pages = ceil($total_count / $limit); if ($total_pages > 1): ?>
<div class="pagination">
    <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?><?php echo $docente_param; ?>" class="btn-nav <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
    <span style="font-weight: 700;">Página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?><?php echo $docente_param; ?>" class="btn-nav <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Floating Bar + MODAL 3: Reservation -->
<script src="../../js/agenda_professores.js"></script>
<script src="../../js/calendar.js"></script>
<script>
// Initialize inline calendar for Calendar view mode
document.addEventListener('DOMContentLoaded', () => {
    const viewMode = "<?php echo $view_mode; ?>";
    if (viewMode === 'calendar') {
        const profId = "<?php echo !empty($professores) ? $professores[0]['id'] : ''; ?>";
        const profNome = "<?php echo !empty($professores) ? addslashes($professores[0]['nome']) : ''; ?>";
        if (profId) {
            currentProfId = profId;
            currentProfNome = profNome;
            const busyData = <?php echo !empty($professores) ? json_encode($agenda_data[$professores[0]['id']] ?? []) : '{}'; ?>;
            const turnoData = <?php echo !empty($professores) ? json_encode($turno_detail[$professores[0]['id']] ?? []) : '{}'; ?>;
            const reservaDataInline = <?php echo !empty($professores) ? json_encode($reserva_data[$professores[0]['id']] ?? []) : '{}'; ?>;
            if (typeof renderCalendarView === 'function') {
                renderCalendarView(profId, profNome, currentMonthStart, busyData, 'inline_calendar_' + profId, turnoData, reservaDataInline);
            }
        }
    }
});
</script>

<?php include '../components/footer.php'; ?>
