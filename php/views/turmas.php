<?php
require_once '../configs/db.php';
include '../components/header.php';

$turmas = mysqli_fetch_all(mysqli_query($conn, "
    SELECT t.*, c.nome AS curso_nome, amb.nome AS ambiente_nome 
    FROM Turma t 
    JOIN Curso c ON t.curso_id = c.id
    JOIN Ambiente amb ON t.ambiente_id = amb.id
    ORDER BY t.data_inicio DESC
"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Turmas</h2>
    <a href="turmas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Turma</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>CURSO / SIGLA</th>
                <th>Período</th>
                <th>Início / Fim</th>
                <th>Local / Ambiente</th>
                <th>Vagas</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($turmas)): ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhuma turma cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($turmas as $t): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($t['curso_nome']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($t['sigla'] ?: '-') ?></small>
                        </td>
                        <td><?= htmlspecialchars($t['periodo'] ?: '-') ?></td>
                        <td>
                            <small>I: <?= date('d/m/Y', strtotime($t['data_inicio'])) ?></small><br>
                            <small>F: <?= date('d/m/Y', strtotime($t['data_fim'])) ?></small>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($t['local'] ?: '-') ?></small><br>
                            <strong><?= htmlspecialchars($t['ambiente_nome']) ?></strong>
                        </td>
                        <td><?= $t['vagas'] ?></td>
                        <td>
                            <a href="turmas_form.php?id=<?= $t['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="../controllers/turmas_process.php?action=delete&id=<?= $t['id'] ?>" class="btn btn-delete"
                                onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../components/footer.php'; ?>