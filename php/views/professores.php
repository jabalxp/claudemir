<?php
require_once '../configs/db.php';
include '../components/header.php';

$professores = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM Docente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Professores</h2>
    <a href="professores_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Professor</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Área de Conhecimento</th>
                <th>Carga Horária</th>
                <th>Disponibilidade</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($professores)): ?>
                <tr>
                    <td colspan="5" class="text-center">Nenhum docente cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($professores as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nome']) ?></td>
                        <td><?= htmlspecialchars($p['area_conhecimento']) ?></td>
                        <td><?= $p['carga_horaria_contratual'] ?>h</td>
                        <td><?= htmlspecialchars($p['disponibilidade_semanal']) ?></td>
                        <td>
                            <a href="professores_form.php?id=<?= $p['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="../controllers/professores_process.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-delete" onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../components/footer.php'; ?>