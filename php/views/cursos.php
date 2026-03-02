<?php
require_once '../configs/db.php';
include '../components/header.php';

$cursos = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM Curso ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Cursos</h2>
    <a href="cursos_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Curso</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Área</th>
                <th>Carga Horária Total</th>
                <th>Semestral</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cursos)): ?>
                <tr><td colspan="6" class="text-center">Nenhum curso cadastrado.</td></tr>
            <?php else: ?>
                <?php foreach ($cursos as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nome']) ?></td>
                        <td><?= htmlspecialchars($c['tipo']) ?></td>
                        <td><?= htmlspecialchars($c['area']) ?></td>
                        <td><?= $c['carga_horaria_total'] ?>h</td>
                        <td><?= $c['semestral'] ? 'Sim' : 'Não' ?></td>
                        <td>
                            <a href="cursos_form.php?id=<?= $c['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="../controllers/cursos_process.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-delete" onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../components/footer.php'; ?>