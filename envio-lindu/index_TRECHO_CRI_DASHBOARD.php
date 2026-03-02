<?php
/**
 * TRECHO NOVO NO index.php — Dashboard CRI
 * 
 * Este bloco deve ser adicionado APÓS o "End Professor Dashboard"
 * e ANTES do dashboard padrão (admin/gestor).
 * 
 * Localização: logo após a linha "// ---- End Professor Dashboard ----"
 */

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
