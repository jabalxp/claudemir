<?php
/**
 * Página de Gerenciamento de Usuários (ATUALIZADO)
 * 
 * ALTERAÇÕES APLICADAS:
 * - Adicionada role 'CRI' no modal de criação (visível apenas para admin)
 * - Adicionada role 'CRI' no modal de edição
 * - Badge de role agora exibe ícone fa-id-badge para CRI
 * - Bloqueio de acesso para CRI (isCri()) adicionado junto com isProfessor()
 */
require_once '../configs/db.php';
require_once '../configs/auth.php';
requireAuth();

/* MODIFICADO: CRI também não pode acessar esta página */
if (isProfessor() || isCri()) {
    header('Location: ../../index.php');
    exit;
}

$can_edit = isAdmin();
$can_delete = isAdmin();
$can_reset = isAdmin();
$can_create = isAdmin() || isGestor();

$usuarios = mysqli_fetch_all(mysqli_query($conn, "
    SELECT u.id, u.nome, u.email, u.role, u.obrigar_troca_senha, u.created_at, u.docente_id, d.nome as docente_nome 
    FROM Usuario u 
    LEFT JOIN Docente d ON u.docente_id = d.id 
    ORDER BY u.created_at DESC
"), MYSQLI_ASSOC);

$docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM Docente ORDER BY nome ASC"), MYSQLI_ASSOC);

$error = $_SESSION['usuarios_error'] ?? '';
$success = $_SESSION['usuarios_success'] ?? '';
unset($_SESSION['usuarios_error'], $_SESSION['usuarios_success']);

include '../components/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-users-cog" style="color: var(--primary-red);"></i> Gerenciamento de Usuários</h2>
    <?php if ($can_create): ?>
        <button class="btn btn-primary" onclick="document.getElementById('modal-user-create').style.display='flex'">
            <i class="fas fa-plus"></i> Novo Usuário
        </button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papel</th>
                <th>Vínculo Docente</th>
                <th>Troca Senha?</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhum usuário cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <!-- MODIFICADO: Ícone fa-id-badge para CRI -->
                            <span class="role-badge role-<?= $u['role'] ?>">
                                <i
                                    class="fas <?= $u['role'] === 'admin' ? 'fa-shield-alt' : ($u['role'] === 'gestor' ? 'fa-user-tie' : ($u['role'] === 'cri' ? 'fa-id-badge' : 'fa-chalkboard-teacher')) ?>"></i>
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td><?= $u['docente_nome'] ? htmlspecialchars($u['docente_nome']) : '<span style="color:var(--text-muted); font-size:0.8rem;">Nenhum</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <?= $u['obrigar_troca_senha'] ? '<span style="color: #ff8f00;"><i class="fas fa-exclamation-triangle"></i> Sim</span>' : '<span style="color: #2e7d32;"><i class="fas fa-check"></i> Não</span>' ?>
                        </td>
                        <td>
                            <?php if ($can_edit): ?>
                                <button class="btn btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($can_reset && $u['id'] != $_SESSION['user_id']): ?>
                                <a href="../controllers/usuarios_process.php?action=reset_password&id=<?= $u['id'] ?>"
                                    class="btn btn-edit" title="Redefinir senha padrão"
                                    onclick="return confirm('Redefinir a senha deste usuário para senaisp?')">
                                    <i class="fas fa-key"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete && $u['id'] != $_SESSION['user_id']): ?>
                                <a href="../controllers/usuarios_process.php?action=delete&id=<?= $u['id'] ?>"
                                    class="btn btn-delete" onclick="return confirm('Tem certeza que deseja remover este usuário?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Criar Usuário -->
<?php if ($can_create): ?>
    <div id="modal-user-create" class="cpw-overlay" style="display: none;">
        <div class="cpw-card" style="max-width: 500px;">
            <h3><i class="fas fa-user-plus"></i> Novo Usuário</h3>
            <p>A senha padrão será <strong>senaisp</strong>.</p>
            <form action="../controllers/usuarios_process.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="login-field">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>E-mail</label>
                    <input type="email" name="email" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>Papel</label>
                    <select name="role" class="login-input" id="create-role-select" onchange="toggleDocenteField('create')">
                        <option value="professor">Professor</option>
                        <?php if (isAdmin()): ?>
                            <!-- NOVO: Opção CRI no modal de criação -->
                            <option value="cri">CRI</option>
                            <option value="gestor">Gestor</option>
                            <option value="admin">Administrador</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="login-field" id="create-docente-group">
                    <label>Vincular a Professor (Docente)</label>
                    <select name="docente_id" class="login-input">
                        <option value="">Nenhum</option>
                        <?php foreach ($docentes as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="login-btn" style="flex: 1;"><i class="fas fa-save"></i> Criar</button>
                    <button type="button" class="login-btn"
                        style="flex: 1; background: var(--bg-color, #334155); color: var(--text-color);"
                        onclick="document.getElementById('modal-user-create').style.display='none'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Editar Usuário (Admin only) -->
<?php if ($can_edit): ?>
    <div id="modal-user-edit" class="cpw-overlay" style="display: none;">
        <div class="cpw-card" style="max-width: 500px;">
            <h3><i class="fas fa-user-edit"></i> Editar Usuário</h3>
            <form action="../controllers/usuarios_process.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-user-id">
                <div class="login-field">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" id="edit-user-nome" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>E-mail</label>
                    <input type="email" name="email" id="edit-user-email" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>Papel</label>
                    <select name="role" id="edit-user-role" class="login-input" onchange="toggleDocenteField('edit')">
                        <option value="professor">Professor</option>
                        <!-- NOVO: Opção CRI no modal de edição -->
                        <option value="cri">CRI</option>
                        <option value="gestor">Gestor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="login-field" id="edit-docente-group">
                    <label>Vincular a Professor (Docente)</label>
                    <select name="docente_id" id="edit-user-docente-id" class="login-input">
                        <option value="">Nenhum</option>
                        <?php foreach ($docentes as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="login-btn" style="flex: 1;"><i class="fas fa-save"></i> Salvar</button>
                    <button type="button" class="login-btn"
                        style="flex: 1; background: var(--bg-color, #334155); color: var(--text-color);"
                        onclick="document.getElementById('modal-user-edit').style.display='none'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openEditModal(user) {
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-user-nome').value = user.nome;
            document.getElementById('edit-user-email').value = user.email;
            document.getElementById('edit-user-role').value = user.role;
            document.getElementById('edit-user-docente-id').value = user.docente_id || "";
            toggleDocenteField('edit');
            document.getElementById('modal-user-edit').style.display = 'flex';
        }

        function toggleDocenteField(prefix) {
            const role = document.getElementById(prefix + '-role-select' + (prefix === 'edit' ? '' : '')) || document.getElementById(prefix + '-user-role');
            const group = document.getElementById(prefix + '-docente-group');
            if (role.value === 'professor') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        }
        toggleDocenteField('create');
    </script>
<?php endif; ?>

<?php include '../components/footer.php'; ?>
