<?php
require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $ambInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Ambiente WHERE id = '$id'"));
    $descAmb = $ambInfo ? $ambInfo['nome'] : "Ambiente #$id";

    mysqli_query($conn, "DELETE FROM Ambiente WHERE id = '$id'");

    criarNotificacao($conn, 'exclusao_ambiente', "Ambiente \"$descAmb\" foi excluído.", $auth_user_id, (int) $id, 'Ambiente');

    header("Location: ../views/salas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $area_vinculada = mysqli_real_escape_string($conn, $_POST['area_vinculada']);
    $cidade = mysqli_real_escape_string($conn, $_POST['cidade']);
    $capacidade = mysqli_real_escape_string($conn, $_POST['capacidade']);

    if ($id) {
        // Update
        $query = "UPDATE Ambiente SET
                  nome = '$nome',
                  tipo = '$tipo',
                  area_vinculada = '$area_vinculada',
                  cidade = '$cidade',
                  capacidade = '$capacidade'
                  WHERE id = '$id'";
        mysqli_query($conn, $query);

        criarNotificacao($conn, 'edicao_ambiente', "Ambiente \"$nome\" foi editado.", $auth_user_id, (int) $id, 'Ambiente');

        header("Location: ../views/salas.php?msg=updated");
    }
    else {
        // Insert
        $query = "INSERT INTO Ambiente (nome, tipo, area_vinculada, cidade, capacidade)
                  VALUES ('$nome', '$tipo', '$area_vinculada', '$cidade', '$capacidade')";
        mysqli_query($conn, $query);
        $novo_id = mysqli_insert_id($conn);

        criarNotificacao($conn, 'registro_ambiente', "Novo ambiente \"$nome\" foi cadastrado.", $auth_user_id, (int) $novo_id, 'Ambiente');

        header("Location: ../views/salas.php?msg=created");
    }
    exit;
}

header("Location: ../views/salas.php");
?>
