<?php
/* MODIFICADO: Adicionado require notificacao_helper.php e chamadas criarNotificacao() */
require_once '../configs/db.php';
require_once '../configs/auth.php';
require_once '../configs/notificacao_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $cursoInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM Curso WHERE id = '$id'"));
    $descCurso = $cursoInfo ? $cursoInfo['nome'] : "Curso #$id";

    mysqli_query($conn, "DELETE FROM Curso WHERE id = '$id'");

    criarNotificacao($conn, 'exclusao_curso', "Curso \"$descCurso\" foi excluído.", $auth_user_id, (int) $id, 'Curso');

    header("Location: ../views/cursos.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $area = mysqli_real_escape_string($conn, $_POST['area']);
    $carga_horaria = mysqli_real_escape_string($conn, $_POST['carga_horaria_total']);
    $semestral = isset($_POST['semestral']) ? 1 : 0;

    if ($id) {
        $query = "UPDATE Curso SET tipo='$tipo', nome='$nome', area='$area', carga_horaria_total='$carga_horaria', semestral=$semestral WHERE id='$id'";
        mysqli_query($conn, $query);

        criarNotificacao($conn, 'edicao_curso', "Curso \"$nome\" foi editado.", $auth_user_id, (int) $id, 'Curso');
    } else {
        $query = "INSERT INTO Curso (tipo, nome, area, carga_horaria_total, semestral) VALUES ('$tipo', '$nome', '$area', '$carga_horaria', $semestral)";
        mysqli_query($conn, $query);
        $novo_id = mysqli_insert_id($conn);

        criarNotificacao($conn, 'registro_curso', "Novo curso \"$nome\" foi cadastrado.", $auth_user_id, (int) $novo_id, 'Curso');
    }
    header("Location: ../views/cursos.php?msg=success");
    exit;
}
?>
