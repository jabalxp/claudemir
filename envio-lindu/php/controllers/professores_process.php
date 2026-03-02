<?php
/* MODIFICADO: Adicionado require notificacao_helper.php e chamadas criarNotificacao() */
require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $docInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Docente WHERE id = '$id'"));
    $descDoc = $docInfo ? $docInfo['nome'] : "Docente #$id";

    mysqli_query($conn, "DELETE FROM Docente WHERE id = '$id'");

    criarNotificacao($conn, 'exclusao_docente', "Docente \"$descDoc\" foi excluído.", $auth_user_id, (int) $id, 'Docente');

    header("Location: ../views/professores.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $area_conhecimento = mysqli_real_escape_string($conn, $_POST['area_conhecimento']);
    $cidade = mysqli_real_escape_string($conn, $_POST['cidade']);
    $carga_horaria_contratual = mysqli_real_escape_string($conn, $_POST['carga_horaria_contratual']);
    $disponibilidade_semanal = mysqli_real_escape_string($conn, $_POST['disponibilidade_semanal']);
    $areas_atuacao = mysqli_real_escape_string($conn, $_POST['areas_atuacao']);
    $cor_agenda = mysqli_real_escape_string($conn, $_POST['cor_agenda'] ?? '#ed1c24');

    if ($id) {
        $query = "UPDATE Docente SET
                  nome = '$nome',
                  area_conhecimento = '$area_conhecimento',
                  cidade = '$cidade',
                  carga_horaria_contratual = '$carga_horaria_contratual',
                  disponibilidade_semanal = '$disponibilidade_semanal',
                  areas_atuacao = '$areas_atuacao',
                  cor_agenda = '$cor_agenda'
                  WHERE id = '$id'";
        mysqli_query($conn, $query);

        criarNotificacao($conn, 'edicao_docente', "Docente \"$nome\" foi editado.", $auth_user_id, (int) $id, 'Docente');

        header("Location: ../views/professores.php?msg=updated");
    } else {
        $query = "INSERT INTO Docente (nome, area_conhecimento, cidade, carga_horaria_contratual, disponibilidade_semanal, areas_atuacao, cor_agenda)
                  VALUES ('$nome', '$area_conhecimento', '$cidade', '$carga_horaria_contratual', '$disponibilidade_semanal', '$areas_atuacao', '$cor_agenda')";
        mysqli_query($conn, $query);
        $novo_id = mysqli_insert_id($conn);

        criarNotificacao($conn, 'registro_docente', "Novo docente \"$nome\" foi cadastrado.", $auth_user_id, (int) $novo_id, 'Docente');

        header("Location: ../views/professores.php?msg=created");
    }
    exit;
}

header("Location: ../views/professores.php");
?>
