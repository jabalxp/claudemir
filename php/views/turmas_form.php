<?php
require_once '../configs/db.php';
include '../components/header.php';

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$turma = ['curso_id' => '', 'ambiente_id' => '', 'periodo' => '', 'data_inicio' => '', 'data_fim' => '', 'tipo' => 'Presencial', 'sigla' => '', 'vagas' => '', 'docente_id1' => '', 'docente_id2' => '', 'docente_id3' => '', 'docente_id4' => '', 'local' => '', 'dias_semana' => ''];

if ($id) {
    $turma = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Turma WHERE id = '$id'"));
    if (!$turma) {
        header("Location: turmas.php");
        exit;
    }
}

$cursos = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, carga_horaria_total FROM Curso ORDER BY nome ASC"), MYSQLI_ASSOC);
$ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM Ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
$docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM Docente ORDER BY nome ASC"), MYSQLI_ASSOC);

$dias_selecionados = !empty($turma['dias_semana']) ? explode(',', $turma['dias_semana']) : [];
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Turma' : 'Nova Turma' ?></h2>
    <a href="turmas.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <form action="../controllers/turmas_process.php" method="POST" id="turma-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">CURSO</label>
                <select name="curso_id" class="form-input" required id="curso-select">
                    <option value="" data-ch="0">Selecione o curso...</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id'] ?>" data-ch="<?= $c['carga_horaria_total'] ?>" <?= $turma['curso_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= $c['carga_horaria_total'] ?>h)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">SIGLA DA TURMA</label>
                <input type="text" name="sigla" class="form-input" value="<?= htmlspecialchars($turma['sigla']) ?>"
                    placeholder="Ex: TI-2026-123">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Vagas</label>
                <input type="number" name="vagas" class="form-input" value="<?= $turma['vagas'] ?>"
                    placeholder="Ex: 32">
            </div>
            <div class="form-group">
                <label class="form-label">Local</label>
                <input type="text" name="local" class="form-input" value="<?= htmlspecialchars($turma['local']) ?>"
                    placeholder="Ex: Unidade SJC">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Ambiente</label>
            <select name="ambiente_id" class="form-input" required>
                <option value="">Selecione o ambiente...</option>
                <?php foreach ($ambientes as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $turma['ambiente_id'] == $a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label class="form-label">Docentes (Até 4)</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <select name="docente_id<?= $i ?>" class="form-input">
                        <option value="">Docente <?= $i ?>...</option>
                        <?php foreach ($docentes as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $turma["docente_id$i"] == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endfor; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Período</label>
            <select name="periodo" class="form-input" required id="periodo-select">
                <option value="">Selecione o período...</option>
                <option value="Manhã" <?= $turma['periodo'] == 'Manhã' ? 'selected' : '' ?>>Manhã (07:30 - 11:30)</option>
                <option value="Tarde" <?= $turma['periodo'] == 'Tarde' ? 'selected' : '' ?>>Tarde (13:30 - 17:30)</option>
                <option value="Noite" <?= $turma['periodo'] == 'Noite' ? 'selected' : '' ?>>Noite (19:30 - 23:30)</option>
                <option value="Integral" <?= $turma['periodo'] == 'Integral' ? 'selected' : '' ?>>Integral (07:30 - 17:30)</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-input">
                <option value="Presencial" <?= $turma['tipo'] == 'Presencial' ? 'selected' : '' ?>>Presencial</option>
                <option value="EAD" <?= $turma['tipo'] == 'EAD' ? 'selected' : '' ?>>EAD</option>
                <option value="Semipresencial" <?= $turma['tipo'] == 'Semipresencial' ? 'selected' : '' ?>>Semipresencial
                </option>
            </select>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label class="form-label">Dias da Semana</label>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php
                $dias_opcoes = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];
                foreach ($dias_opcoes as $dia): ?>
                    <label style="display: flex; align-items: center; gap: 5px; padding: 8px 12px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; font-weight: 600;">
                        <input type="checkbox" name="dias_semana[]" value="<?= $dia ?>" class="dia-check"
                            <?= in_array($dia, $dias_selecionados) ? 'checked' : '' ?>>
                        <span><?= substr($dia, 0, 3) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-grid" style="margin-top: 15px;">
            <div>
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-input" id="data-inicio"
                    value="<?= $turma['data_inicio'] ?>" required>
            </div>
            <div>
                <label class="form-label">Data Fim (Calculada automaticamente)</label>
                <input type="date" name="data_fim" class="form-input" id="data-fim"
                    value="<?= $turma['data_fim'] ?>" readonly
                    style="background: var(--bg-hover); cursor: not-allowed;">
            </div>
        </div>
        <div id="data-fim-info" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;"></div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar Turma</button>
        </div>
    </form>
</div>

<script>
// Auto-calculate data_fim based on carga_horaria_total, periodo, dias_semana, and data_inicio
const cursoSelect = document.getElementById('curso-select');
const periodoSelect = document.getElementById('periodo-select');
const dataInicio = document.getElementById('data-inicio');
const dataFim = document.getElementById('data-fim');
const infoEl = document.getElementById('data-fim-info');

function getHorasPorDia(periodo) {
    switch (periodo) {
        case 'Manhã': case 'Tarde': case 'Noite': return 4;
        case 'Integral': return 8;
        default: return 0;
    }
}

function getDiaSemanaIndex(nome) {
    const map = {'Domingo':0, 'Segunda-feira':1, 'Terça-feira':2, 'Quarta-feira':3, 'Quinta-feira':4, 'Sexta-feira':5, 'Sábado':6};
    return map[nome] ?? -1;
}

function calcularDataFim() {
    const opt = cursoSelect.options[cursoSelect.selectedIndex];
    const ch = parseInt(opt?.dataset.ch) || 0;
    const periodo = periodoSelect.value;
    const inicio = dataInicio.value;
    const diasChecked = Array.from(document.querySelectorAll('.dia-check:checked')).map(cb => cb.value);

    if (!ch || !periodo || !inicio || diasChecked.length === 0) {
        dataFim.value = '';
        infoEl.textContent = '';
        return;
    }

    const horasPorDia = getHorasPorDia(periodo);
    if (horasPorDia === 0) return;

    const totalDias = Math.ceil(ch / horasPorDia);
    const diasIndices = diasChecked.map(d => getDiaSemanaIndex(d)).filter(i => i >= 0);

    let date = new Date(inicio + 'T12:00:00');
    let count = 0;

    for (let safety = 0; safety < 1000 && count < totalDias; safety++) {
        const dow = date.getDay();
        if (diasIndices.includes(dow)) {
            count++;
            if (count >= totalDias) break;
        }
        date.setDate(date.getDate() + 1);
    }

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    dataFim.value = `${y}-${m}-${d}`;
    infoEl.innerHTML = `<i class="fas fa-info-circle"></i> ${ch}h ÷ ${horasPorDia}h/dia = <strong>${totalDias} dias de aula</strong> necessários.`;
}

cursoSelect.addEventListener('change', calcularDataFim);
periodoSelect.addEventListener('change', calcularDataFim);
dataInicio.addEventListener('change', calcularDataFim);
document.querySelectorAll('.dia-check').forEach(cb => cb.addEventListener('change', calcularDataFim));

// Run on load for edit mode
calcularDataFim();
</script>

<?php include '../components/footer.php'; ?>