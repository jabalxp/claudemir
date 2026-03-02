<?php
/**
 * Sistema de Importação Excel
 * Portado do Parafal, adaptado para schema gestao_escolar (Projeto do Miguel)
 * Tabelas remapeadas: professores→docente, salas→ambiente, cursos→curso, turmas→turma
 * Colunas remapeadas: especialidade→area_conhecimento, area→area_vinculada, nome(turma)→sigla, etc.
 */
require_once '../configs/db.php';
include '../components/header.php';

// ────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────
function parseExcelDate($v)
{
    if (!$v)
        return null;
    $v = trim((string) $v);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    if (is_numeric($v) && $v > 30000 && $v < 60000) {
        $unix = ($v - 25569) * 86400;
        return date('Y-m-d', (int) $unix);
    }
    return null;
}

function parseTime($v)
{
    if ($v === null || $v === '' || $v === false)
        return null;
    if (is_numeric($v) && (float) $v > 0 && (float) $v < 1) {
        $total_minutes = (int) round((float) $v * 24 * 60);
        $h = str_pad((int) floor($total_minutes / 60), 2, '0', STR_PAD_LEFT);
        $min = str_pad($total_minutes % 60, 2, '0', STR_PAD_LEFT);
        return "$h:$min";
    }
    $v = trim((string) $v);
    if ($v === '' || $v[0] === '#')
        return null;
    $v = preg_replace('/(\d{1,2})h(\d{2})?/i', '$1:$2', $v);
    $v = str_replace('::', ':', $v);
    if (preg_match('/(\d{1,2}):?(\d{2})?/', $v, $m)) {
        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $min = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
        return "$h:$min";
    }
    return null;
}

function deriveTurno($horario)
{
    if (preg_match('/(\d{1,2})/', $horario, $m)) {
        $h = (int) $m[1];
        if ($h >= 18)
            return 'Noturno';
        if ($h >= 12)
            return 'Vespertino';
        return 'Matutino';
    }
    return null;
}

function parseHorarioRange($horario)
{
    if (!$horario)
        return [null, null];
    if (preg_match('/(\d{1,2})[h:]?(\d{2})?\s*(?:às|a|-|–)\s*(\d{1,2})[h:]?(\d{2})?/i', $horario, $m)) {
        $h1 = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $min1 = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
        $h2 = str_pad($m[3], 2, '0', STR_PAD_LEFT);
        $min2 = isset($m[4]) && $m[4] !== '' ? $m[4] : '00';
        return ["$h1:$min1", "$h2:$min2"];
    }
    return [null, null];
}

function parseDiasSemana($dias_str)
{
    if (!$dias_str)
        return [];
    $map = [
        'seg' => 1,
        'segunda' => 1,
        'mon' => 1,
        'ter' => 2,
        'terça' => 2,
        'terca' => 2,
        'tue' => 2,
        'qua' => 3,
        'quarta' => 3,
        'wed' => 3,
        'qui' => 4,
        'quinta' => 4,
        'thu' => 4,
        'sex' => 5,
        'sexta' => 5,
        'fri' => 5,
        'sab' => 6,
        'sáb' => 6,
        'sábado' => 6,
        'sabado' => 6,
        'sat' => 6,
        'dom' => 7,
        'domingo' => 7,
        'sun' => 7,
    ];
    $result = [];
    $parts = preg_split('/[,;\/]+|\s+e\s+/', mb_strtolower(trim($dias_str), 'UTF-8'));
    foreach ($parts as $part) {
        $part = trim($part);
        if (isset($map[$part])) {
            $result[] = $map[$part];
        } else {
            foreach ($map as $key => $val) {
                if (mb_strpos($part, $key) !== false) {
                    $result[] = $val;
                    break;
                }
            }
        }
    }
    return array_unique($result);
}

// ────────────────────────────────────────────────────────
// IMPORT LOGIC — Todas as queries adaptadas para schema gestao_escolar
// ────────────────────────────────────────────────────────
$import_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_mode'])) {

    $mode = $_POST['import_mode'];
    $results = [];

    // ── MULTI-SHEET IMPORT ──
    if ($mode === 'multi') {
        $sheets_json = json_decode($_POST['sheets_json'] ?? '{}', true);
        $order = ['DOCENTES', 'AMBIENTES', 'CURSOS', 'TURMAS', 'AGENDA'];
        $agenda_cleared_turmas = [];

        foreach ($order as $sheet_name) {
            if (!isset($sheets_json[$sheet_name]) || empty($sheets_json[$sheet_name]))
                continue;
            $rows = $sheets_json[$sheet_name];
            $success = 0;
            $errors = [];

            foreach ($rows as $i => $row) {
                $r = [];
                foreach ($row as $k => $v) {
                    $r[mb_strtolower(trim($k), 'UTF-8')] = trim((string) $v);
                }

                try {
                    if ($sheet_name === 'DOCENTES') {
                        $nome = $r['nome'] ?? '';
                        $area = $r['área'] ?? $r['area'] ?? $r['especialidade'] ?? '';
                        $chmax = (int) ($r['carga horária máx'] ?? $r['carga horária max'] ?? $r['carga_horaria_contratual'] ?? $r['ch'] ?? 0);
                        $cidade = $r['cidade'] ?? '';

                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));

                        // Miguel: tabela docente, coluna area_conhecimento
                        $ck = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                        $ck->bind_param('s', $nome);
                        $ck->execute();
                        if ($ck->get_result()->fetch_row()) {
                            $stmt_upd = $mysqli->prepare("UPDATE docente SET area_conhecimento = COALESCE(NULLIF(?, ''), area_conhecimento), carga_horaria_contratual = ?, cidade = COALESCE(NULLIF(?, ''), cidade) WHERE nome = ?");
                            $stmt_upd->bind_param('siss', $area, $chmax, $cidade, $nome);
                            $stmt_upd->execute();
                        } else {
                            $cidade_val = $cidade ?: null;
                            $stmt_ins = $mysqli->prepare("INSERT INTO docente (nome, area_conhecimento, carga_horaria_contratual, cidade) VALUES (?, ?, ?, ?)");
                            $stmt_ins->bind_param('ssis', $nome, $area, $chmax, $cidade_val);
                            $stmt_ins->execute();
                        }
                    } elseif ($sheet_name === 'AMBIENTES') {
                        $nome = $r['nome'] ?? '';
                        $tipo = $r['tipo'] ?? 'Sala';
                        $area = $r['área'] ?? $r['area'] ?? '';
                        $cidade = $r['cidade'] ?? '';
                        $cap = (int) ($r['capacidade'] ?? 0);

                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));

                        // Miguel: tabela ambiente, coluna area_vinculada
                        $ck = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                        $ck->bind_param('s', $nome);
                        $ck->execute();
                        if ($ck->get_result()->fetch_row()) {
                            $stmt_upd = $mysqli->prepare("UPDATE ambiente SET tipo = ?, area_vinculada = COALESCE(NULLIF(?, ''), area_vinculada), cidade = COALESCE(NULLIF(?, ''), cidade), capacidade = ? WHERE nome = ?");
                            $stmt_upd->bind_param('sssis', $tipo, $area, $cidade, $cap, $nome);
                            $stmt_upd->execute();
                        } else {
                            $area_val = $area ?: null;
                            $cidade_val = $cidade ?: null;
                            $stmt_ins = $mysqli->prepare("INSERT INTO ambiente (nome, tipo, area_vinculada, cidade, capacidade) VALUES (?, ?, ?, ?, ?)");
                            $stmt_ins->bind_param('ssssi', $nome, $tipo, $area_val, $cidade_val, $cap);
                            $stmt_ins->execute();
                        }
                    } elseif ($sheet_name === 'CURSOS') {
                        $nome = $r['nome'] ?? '';
                        $tipo = $r['tipo'] ?? '';
                        $area = $r['área'] ?? $r['area'] ?? '';
                        $ch = (int) ($r['carga horária'] ?? $r['carga_horaria'] ?? 0);

                        if (!$nome)
                            throw new Exception("Nome vazio na linha " . ($i + 2));

                        // Miguel: tabela curso, coluna carga_horaria_total
                        $ck = $mysqli->prepare("SELECT id FROM curso WHERE nome = ?");
                        $ck->bind_param('s', $nome);
                        $ck->execute();
                        if ($ck->get_result()->fetch_row()) {
                            $stmt_upd = $mysqli->prepare("UPDATE curso SET tipo = COALESCE(NULLIF(?, ''), tipo), area = COALESCE(NULLIF(?, ''), area), carga_horaria_total = ? WHERE nome = ?");
                            $stmt_upd->bind_param('ssis', $tipo, $area, $ch, $nome);
                            $stmt_upd->execute();
                        } else {
                            $tipo_val = $tipo ?: null;
                            $area_val = $area ?: null;
                            $stmt_ins = $mysqli->prepare("INSERT INTO curso (nome, tipo, area, carga_horaria_total) VALUES (?, ?, ?, ?)");
                            $stmt_ins->bind_param('sssi', $nome, $tipo_val, $area_val, $ch);
                            $stmt_ins->execute();
                        }
                    } elseif ($sheet_name === 'TURMAS') {
                        $curso_nome = $r['curso'] ?? '';
                        $sigla = $r['sigla da turma'] ?? $r['sigla'] ?? $r['nome'] ?? '';
                        $vagas = (int) ($r['vagas'] ?? 0);
                        $di = parseExcelDate($r['data início'] ?? $r['data inicio'] ?? $r['data_inicio'] ?? '');
                        $df = parseExcelDate($r['data fim'] ?? $r['data_fim'] ?? '');
                        $horario = $r['horário'] ?? $r['horario'] ?? '';
                        $dias_semana = $r['dias semana'] ?? $r['dias_semana'] ?? '';
                        $doc1 = $r['docente 1'] ?? $r['docente1'] ?? '';
                        $doc2 = $r['docente 2'] ?? $r['docente2'] ?? '';
                        $doc3 = $r['docente 3'] ?? $r['docente3'] ?? '';
                        $doc4 = $r['docente 4'] ?? $r['docente4'] ?? '';
                        $ambiente = $r['ambiente'] ?? '';
                        $local = $r['local'] ?? $r['local_turma'] ?? '';
                        $periodo = deriveTurno($horario);

                        if (!$sigla)
                            throw new Exception("Sigla/nome vazio na linha " . ($i + 2));
                        if (!$curso_nome)
                            throw new Exception("Curso vazio na linha " . ($i + 2));

                        // Lookup curso — Miguel: tabela curso
                        $sc = $mysqli->prepare("SELECT id FROM curso WHERE nome = ?");
                        $sc->bind_param('s', $curso_nome);
                        $sc->execute();
                        $curso_id = $sc->get_result()->fetch_row()[0] ?? null;
                        if (!$curso_id)
                            throw new Exception("Curso \"$curso_nome\" não encontrado (Linha " . ($i + 2) . ")");

                        // Resolve docentes to IDs — Miguel: tabela docente
                        $resolve_doc_id = function ($nome) use ($mysqli) {
                            if (!$nome)
                                return null;
                            $s = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                            $nome_t = trim($nome);
                            $s->bind_param('s', $nome_t);
                            $s->execute();
                            $row = $s->get_result()->fetch_row();
                            return $row ? (int) $row[0] : null;
                        };
                        $did1 = $resolve_doc_id($doc1);
                        $did2 = $resolve_doc_id($doc2);
                        $did3 = $resolve_doc_id($doc3);
                        $did4 = $resolve_doc_id($doc4);

                        // Resolve ambiente to ID — Miguel: tabela ambiente
                        $amb_id = null;
                        if ($ambiente) {
                            $sa = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                            $amb_t = trim($ambiente);
                            $sa->bind_param('s', $amb_t);
                            $sa->execute();
                            $sa_row = $sa->get_result()->fetch_row();
                            $amb_id = $sa_row ? (int) $sa_row[0] : null;
                        }

                        // Miguel: tabela turma, colunas sigla, periodo, docente_id1-4 (INT), ambiente_id (INT), local
                        $ck = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                        $ck->bind_param('s', $sigla);
                        $ck->execute();
                        if ($ck->get_result()->fetch_row()) {
                            $stmt_upd = $mysqli->prepare("UPDATE turma SET curso_id = ?, vagas = ?, data_inicio = ?, data_fim = ?, periodo = ?, dias_semana = ?, docente_id1 = ?, docente_id2 = ?, docente_id3 = ?, docente_id4 = ?, ambiente_id = ?, local = ? WHERE sigla = ?");
                            $stmt_upd->bind_param('iissssiiiisis', $curso_id, $vagas, $di, $df, $periodo, $dias_semana, $did1, $did2, $did3, $did4, $amb_id, $local, $sigla);
                            $stmt_upd->execute();
                        } else {
                            $stmt_ins = $mysqli->prepare("INSERT INTO turma (sigla, curso_id, vagas, data_inicio, data_fim, periodo, dias_semana, docente_id1, docente_id2, docente_id3, docente_id4, ambiente_id, local) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ins->bind_param('siissssiiiiss', $sigla, $curso_id, $vagas, $di, $df, $periodo, $dias_semana, $did1, $did2, $did3, $did4, $amb_id, $local);
                            $stmt_ins->execute();
                        }

                        // ── AUTO-GENERATE AGENDA ENTRIES ──
                        $has_agenda_sheet = isset($sheets_json['AGENDA']) && !empty($sheets_json['AGENDA']);
                        if (!$has_agenda_sheet && $di && $df && $horario && $dias_semana && ($did1 || $did2 || $did3 || $did4)) {
                            list($hi_auto, $hf_auto) = parseHorarioRange($horario);
                            $weekdays_auto = parseDiasSemana($dias_semana);

                            if ($hi_auto && $hf_auto && !empty($weekdays_auto)) {
                                $st_tid = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                                $st_tid->bind_param('s', $sigla);
                                $st_tid->execute();
                                $turma_id_auto = $st_tid->get_result()->fetch_row()[0] ?? null;

                                if ($turma_id_auto && ($did1 || $did2 || $did3 || $did4)) {
                                    $stmt_del = $mysqli->prepare("DELETE FROM agenda WHERE turma_id = ?");
                                    $stmt_del->bind_param('i', $turma_id_auto);
                                    $stmt_del->execute();

                                    // Miguel agenda: docente_id, ambiente_id, horario_inicio, horario_fim, dia_semana, periodo, data
                                    $cur_d = new DateTime($di);
                                    $end_d = new DateTime($df);
                                    $end_d->modify('+1 day');
                                    $agenda_gen_count = 0;
                                    $daysMap = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];

                                    while ($cur_d < $end_d) {
                                        $dow_n = (int) $cur_d->format('N');
                                        if (in_array($dow_n, $weekdays_auto)) {
                                            $ag_data = $cur_d->format('Y-m-d');
                                            $ag_dia_semana = $daysMap[$dow_n] ?? '';
                                            $ag_periodo = $periodo ?: 'Manhã';
                                            $doc_id = $did1 ?: ($did2 ?: ($did3 ?: $did4));

                                            $stmt_ag = $mysqli->prepare("INSERT INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                            $stmt_ag->bind_param('iiisssss', $turma_id_auto, $doc_id, $amb_id, $ag_dia_semana, $ag_periodo, $hi_auto, $hf_auto, $ag_data);
                                            $stmt_ag->execute();
                                            $agenda_gen_count++;
                                        }
                                        $cur_d->modify('+1 day');
                                    }
                                    if ($agenda_gen_count > 0) {
                                        if (!isset($results['AGENDA_AUTO'])) {
                                            $results['AGENDA_AUTO'] = ['success' => 0, 'errors' => [], 'total' => 0];
                                        }
                                        $results['AGENDA_AUTO']['success'] += $agenda_gen_count;
                                        $results['AGENDA_AUTO']['total'] += $agenda_gen_count;
                                    }
                                }
                            }
                        }
                    } elseif ($sheet_name === 'AGENDA') {
                        $tname = $r['turma'] ?? '';
                        $pname1 = $r['docente 1'] ?? $r['docente1'] ?? $r['docente'] ?? $r['professor'] ?? '';
                        $sname = $r['ambiente'] ?? $r['sala'] ?? '';
                        $data_val = parseExcelDate($r['data'] ?? '');
                        $hi = parseTime($r['hora início'] ?? $r['hora inicio'] ?? $r['hora_inicio'] ?? '');
                        $hf = parseTime($r['hora fim'] ?? $r['hora_fim'] ?? '');

                        if (!$tname || !$pname1 || !$sname || !$data_val || !$hi || !$hf) {
                            throw new Exception("Dados incompletos na linha " . ($i + 2) . " (turma='$tname', doc='$pname1', amb='$sname', data='$data_val', hi='$hi', hf='$hf')");
                        }

                        // Miguel: turma.sigla, docente, ambiente
                        $st = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                        $st->bind_param('s', $tname);
                        $st->execute();
                        $turma_id = $st->get_result()->fetch_row()[0] ?? null;

                        $sp = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                        $sp->bind_param('s', $pname1);
                        $sp->execute();
                        $doc_id = $sp->get_result()->fetch_row()[0] ?? null;

                        $ss = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                        $ss->bind_param('s', $sname);
                        $ss->execute();
                        $amb_id_ag = $ss->get_result()->fetch_row()[0] ?? null;

                        if (!$turma_id)
                            throw new Exception("Turma \"$tname\" não existe (Linha " . ($i + 2) . ")");
                        if (!$doc_id)
                            throw new Exception("Docente \"$pname1\" não existe (Linha " . ($i + 2) . ")");
                        if (!$amb_id_ag)
                            throw new Exception("Ambiente \"$sname\" não existe (Linha " . ($i + 2) . ")");

                        if (!isset($agenda_cleared_turmas[$turma_id])) {
                            $stmt_del_ag = $mysqli->prepare("DELETE FROM agenda WHERE turma_id = ?");
                            $stmt_del_ag->bind_param('i', $turma_id);
                            $stmt_del_ag->execute();
                            $agenda_cleared_turmas[$turma_id] = true;
                        }

                        // Derive dia_semana and periodo from data and time
                        $daysMapAg = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
                        $dia_semana_val = $daysMapAg[(int) date('w', strtotime($data_val))] ?? '';
                        $periodo_val = 'Manhã';
                        if ($hi >= '12:00')
                            $periodo_val = 'Tarde';
                        if ($hi >= '18:00')
                            $periodo_val = 'Noite';

                        $stmt_ag_ins = $mysqli->prepare("INSERT INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_ag_ins->bind_param('iiisssss', $turma_id, $doc_id, $amb_id_ag, $dia_semana_val, $periodo_val, $hi, $hf, $data_val);
                        $stmt_ag_ins->execute();
                    }

                    $success++;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $results[$sheet_name] = ['success' => $success, 'errors' => $errors, 'total' => count($rows)];
        }

        $import_result = ['mode' => 'multi', 'results' => $results];
    }

    // ── SINGLE-SHEET IMPORT ──
    elseif ($mode === 'single') {
        $import_type = $_POST['import_type'];
        $json_data = json_decode($_POST['import_data'], true);
        $success = 0;
        $errors = [];

        foreach ($json_data as $i => $row) {
            $r = [];
            foreach ($row as $k => $v) {
                $r[mb_strtolower(trim($k), 'UTF-8')] = trim((string) $v);
            }

            try {
                if ($import_type === 'professores') {
                    $nome = $r['nome'] ?? $r['professor'] ?? '';
                    $esp = $r['especialidade'] ?? $r['área'] ?? $r['area'] ?? '';
                    $chc = (int) ($r['carga horária'] ?? $r['carga horária máx'] ?? $r['ch'] ?? $r['carga_horaria_contratual'] ?? 0);
                    $cidade = $r['cidade'] ?? '';
                    if (!$nome)
                        throw new Exception("Nome vazio na linha " . ($i + 2));
                    $cidade_val = $cidade ?: null;
                    $stmt_s = $mysqli->prepare("INSERT INTO docente (nome, area_conhecimento, carga_horaria_contratual, cidade) VALUES (?, ?, ?, ?)");
                    $stmt_s->bind_param('ssis', $nome, $esp, $chc, $cidade_val);
                    $stmt_s->execute();
                } elseif ($import_type === 'salas') {
                    $nome = $r['nome'] ?? $r['ambiente'] ?? $r['sala'] ?? '';
                    $tipo = $r['tipo'] ?? 'Sala';
                    $area = $r['área'] ?? $r['area'] ?? '';
                    $cidade = $r['cidade'] ?? '';
                    $cap = (int) ($r['capacidade'] ?? 0);
                    if (!$nome)
                        throw new Exception("Nome vazio na linha " . ($i + 2));
                    $area_val = $area ?: null;
                    $cidade_val = $cidade ?: null;
                    $stmt_s = $mysqli->prepare("INSERT INTO ambiente (nome, tipo, area_vinculada, cidade, capacidade) VALUES (?, ?, ?, ?, ?)");
                    $stmt_s->bind_param('ssssi', $nome, $tipo, $area_val, $cidade_val, $cap);
                    $stmt_s->execute();
                } elseif ($import_type === 'cursos') {
                    $nome = $r['nome'] ?? $r['curso'] ?? '';
                    $tipo = $r['tipo'] ?? '';
                    $area = $r['área'] ?? $r['area'] ?? '';
                    $ch = (int) ($r['carga horária'] ?? $r['carga_horaria'] ?? 0);
                    if (!$nome)
                        throw new Exception("Nome vazio na linha " . ($i + 2));
                    $tipo_val = $tipo ?: null;
                    $area_val = $area ?: null;
                    $stmt_s = $mysqli->prepare("INSERT INTO curso (nome, tipo, area, carga_horaria_total) VALUES (?, ?, ?, ?)");
                    $stmt_s->bind_param('sssi', $nome, $tipo_val, $area_val, $ch);
                    $stmt_s->execute();
                } elseif ($import_type === 'turmas') {
                    $sigla = $r['sigla da turma'] ?? $r['turma'] ?? $r['nome'] ?? '';
                    $cnome = $r['curso'] ?? '';
                    $periodo = $r['turno'] ?? '';
                    $horario = $r['horário'] ?? $r['horario'] ?? '';
                    $di = parseExcelDate($r['data início'] ?? $r['data inicio'] ?? $r['data_inicio'] ?? '');
                    $df = parseExcelDate($r['data fim'] ?? $r['data_fim'] ?? '');

                    if (!$periodo && $horario)
                        $periodo = deriveTurno($horario);
                    if (!$periodo)
                        $periodo = 'Manhã';

                    if (!$sigla)
                        throw new Exception("Turma não identificada na linha " . ($i + 2));
                    if (!$cnome)
                        throw new Exception("Curso não identificado na linha " . ($i + 2));
                    if (!$di || !$df)
                        throw new Exception("Datas inválidas na linha " . ($i + 2));

                    $sc = $mysqli->prepare("SELECT id FROM curso WHERE nome = ?");
                    $sc->bind_param('s', $cnome);
                    $sc->execute();
                    $curso_id = $sc->get_result()->fetch_row()[0] ?? null;
                    if (!$curso_id)
                        throw new Exception("Curso \"$cnome\" não encontrado (Linha " . ($i + 2) . ")");

                    $vagas = (int) ($r['vagas'] ?? 0);
                    $dias_semana = $r['dias semana'] ?? $r['dias_semana'] ?? '';

                    $dias_val = $dias_semana ?: null;
                    $stmt_s = $mysqli->prepare("INSERT INTO turma (sigla, curso_id, periodo, data_inicio, data_fim, vagas, dias_semana) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_s->bind_param('sisssds', $sigla, $curso_id, $periodo, $di, $df, $vagas, $dias_val);
                    $stmt_s->execute();
                } elseif ($import_type === 'agenda') {
                    $tname = $r['turma'] ?? '';
                    $pname = $r['docente'] ?? $r['professor'] ?? '';
                    $sname = $r['ambiente'] ?? $r['sala'] ?? '';
                    $data_val = parseExcelDate($r['data'] ?? '');
                    $hi = parseTime($r['hora início'] ?? $r['hora inicio'] ?? $r['hora_inicio'] ?? '');
                    $hf = parseTime($r['hora fim'] ?? $r['hora_fim'] ?? '');

                    if ($hi && strlen($hi) > 5)
                        $hi = substr($hi, 0, 5);
                    if ($hf && strlen($hf) > 5)
                        $hf = substr($hf, 0, 5);

                    if (!$tname || !$pname || !$sname || !$data_val || !$hi || !$hf) {
                        throw new Exception("Dados incompletos na linha " . ($i + 2));
                    }

                    $st = $mysqli->prepare("SELECT id FROM turma WHERE sigla = ?");
                    $st->bind_param('s', $tname);
                    $st->execute();
                    $turma_id = $st->get_result()->fetch_row()[0] ?? null;

                    $sp = $mysqli->prepare("SELECT id FROM docente WHERE nome = ?");
                    $sp->bind_param('s', $pname);
                    $sp->execute();
                    $doc_id = $sp->get_result()->fetch_row()[0] ?? null;

                    $ss = $mysqli->prepare("SELECT id FROM ambiente WHERE nome = ?");
                    $ss->bind_param('s', $sname);
                    $ss->execute();
                    $amb_id = $ss->get_result()->fetch_row()[0] ?? null;

                    if (!$turma_id)
                        throw new Exception("Turma \"$tname\" não existe (Linha " . ($i + 2) . ")");
                    if (!$doc_id)
                        throw new Exception("Docente \"$pname\" não existe (Linha " . ($i + 2) . ")");
                    if (!$amb_id)
                        throw new Exception("Ambiente \"$sname\" não existe (Linha " . ($i + 2) . ")");

                    $daysMapSingle = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
                    $dia_sem = $daysMapSingle[(int) date('w', strtotime($data_val))] ?? '';
                    $per = 'Manhã';
                    if ($hi >= '12:00')
                        $per = 'Tarde';
                    if ($hi >= '18:00')
                        $per = 'Noite';

                    $stmt_s = $mysqli->prepare("INSERT INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_s->bind_param('iiisssss', $turma_id, $doc_id, $amb_id, $dia_sem, $per, $hi, $hf, $data_val);
                    $stmt_s->execute();
                }
                $success++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $import_result = ['mode' => 'single', 'results' => [$import_type => ['success' => $success, 'errors' => $errors, 'total' => count($json_data)]]];
    }
}
?>

<div class="page-header"
    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-file-import"></i> Sistema de Importação</h2>
        <p>Arraste o arquivo <strong>Controle de Ocupação.xlsx</strong> ou qualquer planilha compatível.</p>
    </div>
</div>

<?php if ($import_result): ?>
    <div class="card" style="border-left: 5px solid <?php
    $has_err = false;
    foreach ($import_result['results'] as $sr) {
        if (!empty($sr['errors']))
            $has_err = true;
    }
    echo $has_err ? '#ffc107' : '#28a745';
    ?>; margin-bottom: 30px;">
        <h3><i class="fas fa-info-circle"></i> Resultado da Importação</h3>
        <?php
        $sheet_display_names = [
            'DOCENTES' => 'Docentes',
            'AMBIENTES' => 'Ambientes',
            'CURSOS' => 'Cursos',
            'TURMAS' => 'Turmas',
            'AGENDA' => 'Agenda',
            'AGENDA_AUTO' => 'Agenda (Auto-gerada das Turmas)'
        ];
        foreach ($import_result['results'] as $sheet_name => $sr): ?>
            <div style="margin: 10px 0; padding: 8px 12px; background: var(--bg-color); border-radius: 6px;">
                <strong>
                    <?php echo xe($sheet_display_names[$sheet_name] ?? ucfirst(strtolower($sheet_name))); ?>:
                </strong>
                <span style="color: #28a745;">✓
                    <?php echo (int) $sr['success']; ?>
                    <?php echo $sheet_name === 'AGENDA_AUTO' ? 'aulas geradas' : ''; ?>
                </span>
                <?php if (!empty($sr['errors'])): ?>
                    <span style="color: #dc3545;">✗
                        <?php echo count($sr['errors']); ?> erros
                    </span>
                    <div
                        style="max-height: 100px; overflow: auto; background: #fff3cd; color: #000; padding: 10px; margin-top: 6px; font-size: 0.78rem; border-radius: 4px; border: 1px solid #ffeeba;">
                        <?php foreach ($sr['errors'] as $err)
                            echo "<div>• " . xe($err) . "</div>"; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid var(--border-color);">
        <button class="tab-btn active" onclick="switchTab('multi')" id="tab_multi"
            style="flex:1; padding: 12px; border: none; background: none; cursor: pointer; font-weight: 700; font-size: 0.95rem; color: var(--primary-red, #e63946); border-bottom: 3px solid var(--primary-red, #e63946);">
            <i class="fas fa-layer-group"></i> Planilha Completa (Multi-aba)
        </button>
        <button class="tab-btn" onclick="switchTab('single')" id="tab_single"
            style="flex:1; padding: 12px; border: none; background: none; cursor: pointer; font-weight: 700; font-size: 0.95rem; color: var(--text-muted); border-bottom: 3px solid transparent;">
            <i class="fas fa-file-alt"></i> Aba Única (Legado)
        </button>
    </div>

    <div id="single_opts" style="display:none; margin-bottom: 20px;">
        <label style="font-weight: 700; display: block; margin-bottom: 8px;">Tipo de Dado</label>
        <select id="import_type" class="form-input">
            <option value="agenda">Agenda (Datas, Horários, Docentes, Ambientes)</option>
            <option value="turmas">Turmas (Sigla, Curso, Turno, etc)</option>
            <option value="professores">Docentes (Nome, Área, CH)</option>
            <option value="salas">Ambientes (Nome, Tipo, Capacidade)</option>
            <option value="cursos">Cursos (Nome, Tipo, CH)</option>
        </select>
    </div>

    <div id="drop_zone"
        style="border: 2px dashed var(--primary-red, #e63946); border-radius: 15px; padding: 50px 20px; text-align: center; cursor: pointer; background: rgba(237,28,36,0.01); transition: 0.3s;">
        <i class="fas fa-cloud-upload-alt"
            style="font-size: 3.5rem; color: var(--primary-red, #e63946); margin-bottom: 15px;"></i>
        <h3>Arraste o Excel ou CSV aqui</h3>
        <p style="color:var(--text-muted);">Formatos aceitos: .xlsx, .xls, .csv</p>
        <input type="file" id="file_input" accept=".xlsx,.xls,.csv" style="display: none;">
    </div>

    <div id="preview_multi" style="display: none; margin-top: 30px;">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-layer-group"></i> Abas Detectadas</h3>
        <div id="sheets_summary" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;"></div>
        <div id="sheets_previews"></div>
        <form method="POST" id="confirm_form_multi" style="margin-top: 25px; text-align: right;">
            <input type="hidden" name="import_mode" value="multi">
            <input type="hidden" name="sheets_json" id="form_sheets_json">
            <button type="button" class="btn" onclick="location.reload()"
                style="background:var(--bg-color); border: 1px solid var(--border-color); color: var(--corTxt3);">
                <i class="fas fa-redo"></i> Limpar
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px;">
                <i class="fas fa-database"></i> Confirmar Importação
            </button>
        </form>
    </div>

    <div id="preview_single" style="display: none; margin-top: 30px;">
        <h3 style="margin-bottom: 15px;">Prévia dos Dados (<span id="count_label">0</span>)</h3>
        <div style="max-height: 350px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px;">
            <table id="preview_table" style="font-size: 0.8rem; min-width: 100%;">
                <thead style="position: sticky; top:0; background: var(--bg-color); z-index: 5;">
                    <tr id="preview_header"></tr>
                </thead>
                <tbody id="preview_body"></tbody>
            </table>
        </div>
        <form method="POST" id="confirm_form_single" style="margin-top: 25px; text-align: right;">
            <input type="hidden" name="import_mode" value="single">
            <input type="hidden" name="import_type" id="form_import_type">
            <input type="hidden" name="import_data" id="form_import_data">
            <button type="button" class="btn" onclick="location.reload()"
                style="background:var(--bg-color); border: 1px solid var(--border-color); color: var(--corTxt3);">
                <i class="fas fa-redo"></i> Limpar
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px;">
                <i class="fas fa-database"></i> Confirmar Importação
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script>
    let currentMode = 'multi';
    const knownSheets = ['DOCENTES', 'AMBIENTES', 'CURSOS', 'TURMAS', 'AGENDA'];
    const sheetLabels = {
        DOCENTES: { icon: 'fa-chalkboard-teacher', color: '#0d6efd', label: 'Docentes' },
        AMBIENTES: { icon: 'fa-building', color: '#198754', label: 'Ambientes' },
        CURSOS: { icon: 'fa-graduation-cap', color: '#6f42c1', label: 'Cursos' },
        TURMAS: { icon: 'fa-users', color: '#fd7e14', label: 'Turmas' },
        AGENDA: { icon: 'fa-calendar-alt', color: '#dc3545', label: 'Agenda' }
    };

    function switchTab(mode) {
        currentMode = mode;
        document.getElementById('tab_multi').classList.toggle('active', mode === 'multi');
        document.getElementById('tab_single').classList.toggle('active', mode === 'single');
        document.getElementById('tab_multi').style.color = mode === 'multi' ? 'var(--primary-red, #e63946)' : 'var(--text-muted)';
        document.getElementById('tab_single').style.color = mode === 'single' ? 'var(--primary-red, #e63946)' : 'var(--text-muted)';
        document.getElementById('tab_multi').style.borderBottomColor = mode === 'multi' ? 'var(--primary-red, #e63946)' : 'transparent';
        document.getElementById('tab_single').style.borderBottomColor = mode === 'single' ? 'var(--primary-red, #e63946)' : 'transparent';
        document.getElementById('single_opts').style.display = mode === 'single' ? 'block' : 'none';
        document.getElementById('preview_multi').style.display = 'none';
        document.getElementById('preview_single').style.display = 'none';
    }

    const dropZone = document.getElementById('drop_zone');
    const fileInput = document.getElementById('file_input');

    dropZone.onclick = () => fileInput.click();
    dropZone.ondragover = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.08)'; };
    dropZone.ondragleave = () => dropZone.style.background = 'rgba(237,28,36,0.01)';
    dropZone.ondrop = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.01)'; handleFile(e.dataTransfer.files[0]); };
    fileInput.onchange = e => handleFile(e.target.files[0]);

    function handleFile(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: false });
                const detected = wb.SheetNames.filter(n => knownSheets.includes(n.toUpperCase()));
                if (detected.length >= 2 && currentMode === 'multi') {
                    handleMultiSheet(wb);
                } else if (currentMode === 'multi' && wb.SheetNames.length > 1) {
                    handleMultiSheet(wb);
                } else {
                    handleSingleSheet(wb);
                }
            } catch (err) {
                console.error(err);
                alert("Erro ao ler arquivo. Verifique o formato.");
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function handleMultiSheet(wb) {
        const summaryEl = document.getElementById('sheets_summary');
        const previewsEl = document.getElementById('sheets_previews');
        summaryEl.innerHTML = '';
        previewsEl.innerHTML = '';

        const sheetsData = {};
        let totalRows = 0;

        wb.SheetNames.forEach(name => {
            const upper = name.toUpperCase();
            if (!knownSheets.includes(upper)) return;
            const ws = wb.Sheets[name];
            const data = XLSX.utils.sheet_to_json(ws, { raw: true, dateNF: 'dd/mm/yyyy', defval: '' });
            if (!data.length) return;
            sheetsData[upper] = data;
            totalRows += data.length;
            const info = sheetLabels[upper] || { icon: 'fa-file', color: '#6c757d', label: name };

            summaryEl.innerHTML += '<div style="padding: 10px 16px; border-radius: 10px; background: ' + info.color + '15; border: 1px solid ' + info.color + '40; display: flex; align-items: center; gap: 8px;">' +
                '<i class="fas ' + info.icon + '" style="color:' + info.color + ';"></i>' +
                '<span style="font-weight: 700;">' + info.label + '</span>' +
                '<span style="background:' + info.color + '; color:#fff; border-radius: 12px; padding: 2px 10px; font-size: 0.78rem;">' + data.length + '</span>' +
                '</div>';

            const cols = Object.keys(data[0]);
            let tableHtml = '<div style="margin-bottom: 20px;">';
            tableHtml += '<h4 style="margin: 10px 0; color: ' + info.color + ';"><i class="fas ' + info.icon + '"></i> ' + info.label + ' (' + data.length + ' registros)</h4>';
            tableHtml += '<div style="max-height: 200px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px;">';
            tableHtml += '<table style="font-size: 0.78rem; min-width: 100%;"><thead style="position:sticky; top:0; background: var(--bg-color); z-index:3;"><tr>';
            cols.forEach(c => { tableHtml += '<th style="padding: 6px 10px; white-space: nowrap;">' + c + '</th>'; });
            tableHtml += '</tr></thead><tbody>';
            data.slice(0, 20).forEach(row => {
                tableHtml += '<tr>';
                cols.forEach(c => {
                    let v = row[c] !== undefined ? row[c] : '';
                    if (typeof v === 'number' && v > 30000 && v < 60000) {
                        const dt = new Date((v - 25569) * 86400 * 1000);
                        v = dt.toLocaleDateString('pt-BR');
                    }
                    tableHtml += '<td style="padding: 4px 10px; white-space: nowrap;">' + v + '</td>';
                });
                tableHtml += '</tr>';
            });
            if (data.length > 20) tableHtml += '<tr><td colspan="' + cols.length + '" style="text-align:center; color: var(--text-muted); padding: 8px;">... e mais ' + (data.length - 20) + ' registros</td></tr>';
            tableHtml += '</tbody></table></div></div>';
            previewsEl.innerHTML += tableHtml;
        });

        if (Object.keys(sheetsData).length === 0) {
            alert("Nenhuma aba conhecida encontrada (DOCENTES, AMBIENTES, CURSOS, TURMAS, AGENDA).");
            return;
        }

        document.getElementById('form_sheets_json').value = JSON.stringify(sheetsData);
        document.getElementById('preview_multi').style.display = 'block';

        dropZone.innerHTML = '<i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i>' +
            '<h3>Arquivo pronto!</h3>' +
            '<p>' + Object.keys(sheetsData).length + ' abas detectadas · ' + totalRows + ' registros no total</p>';
    }

    function handleSingleSheet(wb) {
        switchTab('single');
        const ws = wb.Sheets[wb.SheetNames[0]];
        const data = XLSX.utils.sheet_to_json(ws, { raw: false, dateNF: 'dd/mm/yyyy', defval: '' });
        if (!data.length) return alert("Arquivo sem dados!");

        const header = document.getElementById('preview_header');
        const body = document.getElementById('preview_body');
        const cols = Object.keys(data[0]);

        header.innerHTML = '';
        body.innerHTML = '';
        cols.forEach(c => { const th = document.createElement('th'); th.textContent = c; header.appendChild(th); });

        data.slice(0, 50).forEach(row => {
            const tr = document.createElement('tr');
            cols.forEach(c => { const td = document.createElement('td'); td.textContent = row[c] || ''; tr.appendChild(td); });
            body.appendChild(tr);
        });

        document.getElementById('count_label').textContent = data.length + " registros";
        document.getElementById('form_import_type').value = document.getElementById('import_type').value;
        document.getElementById('form_import_data').value = JSON.stringify(data);
        document.getElementById('preview_single').style.display = 'block';

        dropZone.innerHTML = '<i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i>' +
            '<h3>Arquivo pronto!</h3><p>' + data.length + ' registros detectados.</p>';
    }
</script>

<?php include '../components/footer.php'; ?>