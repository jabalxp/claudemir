<?php
/**
 * Header Component (CORRIGIDO)
 * 
 * CORREÇÕES APLICADAS:
 * - Query de contagem de notificações: tabela 'Notificacao' corrigida para 'notificacoes'
 * - Sidebar já inclui menu CRI com submenu Planejamento
 * - Badge de role no topo exibe 'CRI' corretamente
 */
require_once __DIR__ . '/../configs/auth.php';
requireAuth();
checkForcePasswordChange();

$current_page = basename($_SERVER['PHP_SELF']);
$theme = $_COOKIE['theme'] ?? 'light';

$path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
$is_in_subdir = !empty(array_intersect(['views', 'controllers', 'components', 'configs'], $path_parts));
$prefix = $is_in_subdir ? '../../' : '';
?>
<!DOCTYPE html>
<html lang="pt-br" data-tema="claro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Escolar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $prefix ?>css/nav.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/style.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/login.css">
    <meta name="base-url" content="<?= $prefix ?>">
</head>

<body class="<?= ($_COOKIE['sidebar'] ?? '') == 'closed' ? 'sidebar-closed' : '' ?>">

    <nav class="sidebar">
        <div class="div-img">
            <img src="<?= $prefix ?>assets/images/senailogo.png" id="senai-logo" alt="SENAI">
        </div>
        <div class="botao-fechar" id="fechar-nav">
            <button>
                <i
                    class="bi <?= ($_COOKIE['sidebar'] ?? '') == 'closed' ? 'bi-chevron-right' : 'bi-chevron-left' ?>"></i>
            </button>
        </div>
        <div class="div-links">
            <a href="<?= $prefix ?>index.php" class="links <?= $current_page == 'index.php' ? 'ativo' : '' ?>">
                <i class="bi bi-house-door-fill" style="margin-right: 10px;"></i> Dashboard
            </a>

            <?php if (isAdmin() || isGestor()): ?>
                <a href="<?= $prefix ?>php/views/professores.php"
                    class="links <?= $current_page == 'professores.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-person-workspace" style="margin-right: 10px;"></i> Docentes
                </a>
                <a href="<?= $prefix ?>php/views/salas.php"
                    class="links <?= str_contains($_SERVER['PHP_SELF'], 'salas') ? 'ativo' : '' ?>">
                    <i class="bi bi-building" style="margin-right: 10px;"></i> Ambientes
                </a>
                <a href="<?= $prefix ?>php/views/cursos.php"
                    class="links <?= str_contains($_SERVER['PHP_SELF'], 'cursos') ? 'ativo' : '' ?>">
                    <i class="bi bi-journal-bookmark-fill" style="margin-right: 10px;"></i> Cursos
                </a>
                <a href="<?= $prefix ?>php/views/turmas.php"
                    class="links <?= str_contains($_SERVER['PHP_SELF'], 'turmas') ? 'ativo' : '' ?>">
                    <i class="bi bi-people-fill" style="margin-right: 10px;"></i> Turmas
                </a>
            <?php endif; ?>

            <!-- ========== MENU CRI (NOVO) ========== -->
            <?php if (isCri()): ?>
                <?php
                $cri_planejamento_pages = ['planejamento.php', 'gerenciar_reservas.php'];
                $is_cri_plan_active = in_array($current_page, $cri_planejamento_pages);
                ?>
                <div class="menu-manutencao <?= $is_cri_plan_active ? 'aberto' : '' ?>">
                    <a href="javascript:void(0)"
                        class="links manutencao-btn <?= $is_cri_plan_active ? 'ativo' : '' ?>">
                        <i class="bi bi-calendar-check" style="margin-right: 10px;"></i> Planejamento <i
                            class="bi bi-caret-down-fill seta"></i>
                    </a>
                    <div class="submenu <?= $is_cri_plan_active ? 'aberto' : '' ?>" id="submenu-manutencao">
                        <a href="<?= $prefix ?>php/views/planejamento.php"
                            class="links-sub <?= $current_page == 'planejamento.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-eye" style="margin-right: 8px;"></i> Ver Planejamento
                        </a>
                        <a href="<?= $prefix ?>php/views/gerenciar_reservas.php"
                            class="links-sub <?= $current_page == 'gerenciar_reservas.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-bookmark-star" style="margin-right: 8px;"></i> Reservar Datas
                        </a>
                    </div>
                </div>
            <!-- ========== FIM MENU CRI ========== -->

            <?php elseif (isProfessor()): ?>
                <a href="<?= $prefix ?>php/views/planejamento.php"
                    class="links <?= $current_page == 'planejamento.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-calendar-week" style="margin-right: 10px;"></i> Calendário
                </a>
            <?php else: ?>
                <?php
                $planejamento_pages = ['planejamento.php', 'tabela_professores.php', 'agenda_professores.php', 'agenda_salas.php', 'gerenciar_reservas.php'];
                $is_planejamento_active = in_array($current_page, $planejamento_pages);
                ?>
                <div class="menu-manutencao <?= $is_planejamento_active ? 'aberto' : '' ?>">
                    <a href="<?= $prefix ?>php/views/planejamento.php"
                        class="links manutencao-btn <?= $is_planejamento_active ? 'ativo' : '' ?>">
                        <i class="bi bi-tools" style="margin-right: 10px;"></i> Planejamento <i
                            class="bi bi-caret-down-fill seta"></i>
                    </a>
                    <div class="submenu <?= $is_planejamento_active ? 'aberto' : '' ?>" id="submenu-manutencao">
                        <a href="<?= $prefix ?>php/views/tabela_professores.php"
                            class="links-sub <?= $current_page == 'tabela_professores.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-table" style="margin-right: 8px;"></i> Tabela Professores
                        </a>
                        <a href="<?= $prefix ?>php/views/agenda_professores.php"
                            class="links-sub <?= $current_page == 'agenda_professores.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-calendar-check" style="margin-right: 8px;"></i> Agenda Professores
                        </a>
                        <a href="<?= $prefix ?>php/views/agenda_salas.php"
                            class="links-sub <?= $current_page == 'agenda_salas.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-building-check" style="margin-right: 8px;"></i> Agenda Salas
                        </a>
                        <a href="<?= $prefix ?>php/views/gerenciar_reservas.php"
                            class="links-sub <?= $current_page == 'gerenciar_reservas.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-bookmark-star" style="margin-right: 8px;"></i> Reservas
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isAdmin() || isGestor()): ?>
                <a href="<?= $prefix ?>php/views/usuarios.php"
                    class="links <?= $current_page == 'usuarios.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-shield-lock-fill" style="margin-right: 10px;"></i> Usuários
                </a>
                <?php
                $exportacao_pages = ['dados_exportacao.php', 'import_excel.php'];
                $is_exportacao_active = in_array($current_page, $exportacao_pages);
                ?>
                <div class="menu-manutencao <?= $is_exportacao_active ? 'aberto' : '' ?>">
                    <a href="<?= $prefix ?>php/views/dados_exportacao.php"
                        class="links manutencao-btn <?= $is_exportacao_active ? 'ativo' : '' ?>">
                        <i class="bi bi-cloud-download-fill" style="margin-right: 10px;"></i> Exportar/Importar <i
                            class="bi bi-caret-down-fill seta"></i>
                    </a>
                    <div class="submenu <?= $is_exportacao_active ? 'aberto' : '' ?>">
                        <a href="<?= $prefix ?>php/views/dados_exportacao.php"
                            class="links-sub <?= $current_page == 'dados_exportacao.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-cloud-download-fill" style="margin-right: 8px;"></i> Exportar
                        </a>
                        <a href="<?= $prefix ?>php/views/import_excel.php"
                            class="links-sub <?= $current_page == 'import_excel.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-file-earmark-spreadsheet" style="margin-right: 8px;"></i> Importar
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="div-configs">
            <button id="tema" onclick="changeTheme()"><i class="bi bi-brightness-high-fill"></i></button>
            <a href="<?= $prefix ?>php/controllers/logout.php" class="sair" title="Sair do sistema">
                Sair <i class="bi bi-door-closed-fill"></i>
            </a>
        </div>
    </nav>

    <div class="main-content">
        <header class="top-bar">
            <div class="top-bar-left">
                <div class="avatar"><i class="fas fa-user-circle"></i></div>
                <div>
                    <span class="user-greeting">Bem-vindo,</span>
                    <span class="user-name"><?= htmlspecialchars(getUserName()) ?></span>
                    <?php
                    /* MODIFICADO: Adicionado 'cri' => 'CRI' no array de labels e classe role-cri */
                    $roleLabels = ['admin' => 'Admin', 'gestor' => 'Coordenador', 'professor' => 'Professor', 'cri' => 'CRI'];
                    $roleClass = getUserRole() === 'cri' ? 'role-cri' : (getUserRole() === 'professor' ? 'role-prof' : 'role-coord');
                    ?>
                    <span class="user-role-badge <?= $roleClass ?>"><?= $roleLabels[getUserRole()] ?? getUserRole() ?></span>
                </div>
            </div>
            <div class="top-bar-right">
                <!-- ========== SISTEMA DE NOTIFICAÇÕES (NOVO) ========== -->
                <?php
                require_once __DIR__ . '/../configs/db.php';
                $notif_uid = (int) ($_SESSION['user_id'] ?? 0);
                $notif_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = $notif_uid AND lida = 0");
                $notif_count = 0;
                if ($notif_count_res) {
                    $notif_row = mysqli_fetch_assoc($notif_count_res);
                    $notif_count = (int) $notif_row['total'];
                }
                ?>
                <div class="notif-container">
                    <button class="notif-bell" id="notif-bell" onclick="toggleNotifPanel()" title="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notif-badge" style="<?= $notif_count > 0 ? '' : 'display:none;' ?>">
                            <?= $notif_count > 99 ? '99+' : $notif_count ?>
                        </span>
                    </button>
                    <div class="notif-panel" id="notif-panel" style="display:none;">
                        <div class="notif-header">
                            <h4><i class="fas fa-bell"></i> Notificações</h4>
                        </div>
                        <div class="notif-tabs">
                            <button class="notif-tab active" data-tab="nao_lidas" onclick="switchNotifTab('nao_lidas', this)">Não Lido</button>
                            <button class="notif-tab" data-tab="lidas" onclick="switchNotifTab('lidas', this)">Lido</button>
                            <button class="notif-tab" data-tab="todas" onclick="switchNotifTab('todas', this)">Todas</button>
                        </div>
                        <div class="notif-filter">
                            <select id="notif-tipo-filter" onchange="loadNotificacoes()">
                                <option value="">Todos os tipos</option>
                                <option value="reserva">Reserva</option>
                                <option value="registro_horario">Registro de Horário</option>
                                <option value="registro_turma">Registro de Turma</option>
                                <option value="edicao_turma">Edição de Turma</option>
                                <option value="exclusao_turma">Exclusão de Turma</option>
                                <option value="registro_docente">Registro de Docente</option>
                                <option value="edicao_docente">Edição de Docente</option>
                                <option value="exclusao_docente">Exclusão de Docente</option>
                                <option value="registro_ambiente">Registro de Ambiente</option>
                                <option value="edicao_ambiente">Edição de Ambiente</option>
                                <option value="exclusao_ambiente">Exclusão de Ambiente</option>
                                <option value="registro_curso">Registro de Curso</option>
                                <option value="edicao_curso">Edição de Curso</option>
                                <option value="exclusao_curso">Exclusão de Curso</option>
                            </select>
                        </div>
                        <div class="notif-actions" id="notif-actions"></div>
                        <div class="notif-list" id="notif-list">
                            <p class="notif-empty"><i class="fas fa-bell-slash"></i> Nenhuma notificação</p>
                        </div>
                    </div>
                </div>
                <!-- ========== FIM NOTIFICAÇÕES ========== -->
                <div class="status-box">
                    <span class="status-label">Status do Sistema</span>
                    <span class="status-online"><i class="fas fa-circle status-dot"></i> Online</span>
                </div>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?>
                </div>
            </div>
        </header>
        <div class="content-wrapper">
