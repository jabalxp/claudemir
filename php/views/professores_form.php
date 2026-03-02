<?php
require_once '../configs/db.php';
include '../components/header.php';

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$prof = ['nome' => '', 'area_conhecimento' => '', 'cidade' => '', 'carga_horaria_contratual' => '', 'disponibilidade_semanal' => '', 'areas_atuacao' => '', 'cor_agenda' => '#ed1c24'];

if ($id) {
    $prof = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Docente WHERE id = '$id'"));
    if (!$prof) {
        header("Location: professores.php");
        exit;
    }
}
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Professor' : 'Novo Professor' ?></h2>
    <a href="professores.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="../controllers/professores_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label class="form-label">Nome Completo</label>
            <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($prof['nome']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Área de Conhecimento</label>
            <select name="area_conhecimento" class="form-input" required>
                <option value="">Selecione a área...</option>
                <?php
                $areas_padronizadas = ['TI / Software', 'Mecatrônica / Automação', 'Metalmecânica', 'Logística', 'Eletroeletrônica', 'Gestão / Qualidade', 'Alimentos'];
                foreach ($areas_padronizadas as $ap): ?>
                    <option value="<?= htmlspecialchars($ap) ?>" <?= $prof['area_conhecimento'] == $ap ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ap) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Cidade / Unidade</label>
            <input type="text" name="cidade" class="form-input" value="<?= htmlspecialchars($prof['cidade']) ?>"
                placeholder="Ex: São José dos Campos">
        </div>
        <div class="form-group">
            <label class="form-label">Carga Horária Contratual (horas)</label>
            <input type="number" name="carga_horaria_contratual" class="form-input"
                value="<?= htmlspecialchars($prof['carga_horaria_contratual']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Disponibilidade Semanal</label>
            <input type="text" name="disponibilidade_semanal" class="form-input"
                value="<?= htmlspecialchars($prof['disponibilidade_semanal']) ?>"
                placeholder="Ex: Segunda à Sexta, Manhã...">
        </div>
        <div class="form-group">
            <label class="form-label"><i class="fas fa-palette" style="margin-right: 5px;"></i>Cor na Agenda</label>
            <div style="display: flex; align-items: center; gap: 12px;">
                <input type="color" name="cor_agenda" value="<?= htmlspecialchars($prof['cor_agenda'] ?? '#ed1c24') ?>"
                    style="width: 50px; height: 38px; border: none; cursor: pointer; border-radius: 6px;">
                <span style="color: var(--text-muted); font-size: 0.85rem;">Cor usada para identificar este docente nas
                    agendas</span>
            </div>
        </div>
        <div class="form-group-last">
            <label class="form-label">Áreas de Atuação</label>
            <textarea name="areas_atuacao" class="form-input"
                style="height: 80px;"><?= htmlspecialchars($prof['areas_atuacao']) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php include '../components/footer.php'; ?>