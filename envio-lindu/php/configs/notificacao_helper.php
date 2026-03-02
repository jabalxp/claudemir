<?php
/**
 * Helper centralizado para criação de notificações.
 * Insere uma notificação para todos os usuários ativos exceto o autor.
 */

/**
 * @param mysqli      $conn         Conexão com o banco
 * @param string      $tipo         Tipo ENUM da notificação
 * @param string      $mensagem     Texto descritivo da ação
 * @param int         $autorId      ID do usuário que executou a ação
 * @param int|null    $entidadeId   ID do registro afetado
 * @param string|null $entidadeTipo Nome da tabela afetada (Turma, Docente, etc.)
 */
function criarNotificacao(
    mysqli $conn,
    string $tipo,
    string $mensagem,
    int $autorId,
    ?int $entidadeId = null,
    ?string $entidadeTipo = null
): void {
    $result = mysqli_query($conn, "SELECT id FROM Usuario WHERE id != $autorId");

    if (!$result) {
        return;
    }

    $msgEsc = mysqli_real_escape_string($conn, $mensagem);
    $tipoEsc = mysqli_real_escape_string($conn, $tipo);
    $entIdSql = $entidadeId !== null ? (int) $entidadeId : 'NULL';
    $entTipoSql = $entidadeTipo !== null
        ? "'" . mysqli_real_escape_string($conn, $entidadeTipo) . "'"
        : 'NULL';

    while ($user = mysqli_fetch_assoc($result)) {
        $uid = (int) $user['id'];
        mysqli_query($conn, "INSERT INTO Notificacao (usuario_id, tipo, mensagem, autor_id, entidade_id, entidade_tipo)
                             VALUES ($uid, '$tipoEsc', '$msgEsc', $autorId, $entIdSql, $entTipoSql)");
    }
}
