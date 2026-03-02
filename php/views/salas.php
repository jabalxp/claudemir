<?php
require_once '../configs/db.php';
include '../components/header.php';

$salas = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM Ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Ambientes</h2>
    <a href="salas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Ambiente</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome do Ambiente</th>
                <th>Capacidade</th>
                <th>Tipo</th>
                <th>Cidade</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salas)): ?>
                <tr><td colspan="5" class="text-center">Nenhum ambiente cadastrado.</td></tr>
            <?php else: ?>
                <?php foreach ($salas as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nome']) ?></td>
                        <td><?= $s['capacidade'] ?> pessoas</td>
                        <td><?= htmlspecialchars($s['tipo']) ?></td>
                        <td><?= htmlspecialchars($s['cidade']) ?></td>
                        <td>
                            <a href="salas_form.php?id=<?= $s['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="../controllers/salas_process.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-delete" onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../components/footer.php'; ?>