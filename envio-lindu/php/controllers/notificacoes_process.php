<?php
/**
 * Notificações API Controller (CORRIGIDO)
 * 
 * CORREÇÕES APLICADAS:
 * - Tabela renomeada de 'Notificacao' para 'notificacoes' (nome correto no banco)
 * - Colunas corrigidas: criado_em → created_at, autor_id → criado_por, entidade_tipo → referencia_tipo
 * - Adicionada coluna 'titulo' no SELECT da listagem
 */
require_once '../configs/db.php';
require_once '../configs/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'count':
        $r = mysqli_fetch_row(mysqli_query($conn,
            "SELECT COUNT(*) FROM notificacoes WHERE usuario_id = $usuarioId AND lida = 0"));
        echo json_encode(['count' => (int) $r[0]]);
        break;

    case 'list':
        $filtro = $_GET['filtro'] ?? 'nao_lidas';
        $tipo = $_GET['tipo'] ?? '';

        $where = "WHERE n.usuario_id = $usuarioId";

        if ($filtro === 'lidas') {
            $where .= " AND n.lida = 1";
        } elseif ($filtro === 'nao_lidas') {
            $where .= " AND n.lida = 0";
        }

        if ($tipo !== '') {
            $tipoEsc = mysqli_real_escape_string($conn, $tipo);
            $where .= " AND n.tipo = '$tipoEsc'";
        }

        $result = mysqli_query($conn,
            "SELECT n.id, n.tipo, n.titulo, n.mensagem, n.lida, n.created_at, n.referencia_tipo,
                    COALESCE(u.nome, 'Sistema') AS autor_nome
             FROM notificacoes n
             LEFT JOIN Usuario u ON n.criado_por = u.id
             $where
             ORDER BY n.created_at DESC
             LIMIT 50");

        $notifs = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $notifs[] = $row;
            }
        }
        echo json_encode($notifs);
        break;

    case 'marcar_lida':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            mysqli_query($conn, "UPDATE notificacoes SET lida = 1 WHERE id = $id AND usuario_id = $usuarioId");
        }
        echo json_encode(['ok' => true]);
        break;

    case 'marcar_todas_lidas':
        mysqli_query($conn, "UPDATE notificacoes SET lida = 1 WHERE usuario_id = $usuarioId AND lida = 0");
        echo json_encode(['ok' => true]);
        break;

    case 'limpar_lidas':
        mysqli_query($conn, "DELETE FROM notificacoes WHERE usuario_id = $usuarioId AND lida = 1");
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
}
