<?php
require_once '../configs/db.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'excel';
$is_powerbi = ($tipo === 'powerbi');

// ─────────────────────────────────────────────
// DATA QUERIES
// ─────────────────────────────────────────────

// 1. Turmas Query
$query_turmas = "
    SELECT
        t.id                AS id,
        c.nome              AS curso,
        t.tipo              AS tipo_turma,
        t.periodo           AS periodo,
        amb.cidade          AS cidade,
        t.data_inicio       AS data_inicio,
        t.data_fim          AS data_fim,
        (SELECT COUNT(*) FROM Agenda a WHERE a.turma_id = t.id) AS total_dias_semana
    FROM Turma t
    JOIN Curso c ON t.curso_id = c.id
    JOIN Ambiente amb ON t.ambiente_id = amb.id
    ORDER BY amb.cidade ASC, t.data_inicio DESC
";
$res_turmas = mysqli_query($conn, $query_turmas);
$data_turmas = mysqli_fetch_all($res_turmas, MYSQLI_ASSOC);

// 2. Agenda Query
$query_agenda = "
    SELECT
        a.dia_semana        AS dia,
        a.horario_inicio    AS hora_inicio,
        a.horario_fim       AS hora_fim,
        c.nome              AS curso,
        d.nome              AS docente,
        amb.nome            AS ambiente,
        amb.cidade          AS cidade
    FROM Agenda a
    JOIN Turma t ON a.turma_id = t.id
    JOIN Curso c ON t.curso_id = c.id
    JOIN Docente d ON a.docente_id = d.id
    JOIN Ambiente amb ON a.ambiente_id = amb.id
    ORDER BY a.dia_semana, a.horario_inicio ASC
";
$res_agenda = mysqli_query($conn, $query_agenda);
$data_agenda = mysqli_fetch_all($res_agenda, MYSQLI_ASSOC);

if (empty($data_turmas) && empty($data_agenda)) {
  header("Location: ../views/dados_exportacao.php?msg=nodata");
  exit;
}

// ─────────────────────────────────────────────
// EXPORT LOGIC
// ─────────────────────────────────────────────

if ($is_powerbi) {
  // For Power BI, we'll export Turmas as the primary data or a simplified CSV.
  // Since Power BI usually expects one table per file, we'll keep it simple or use a combined format if possible.
  // However, the user asked for "apenas um exportar". To satisfy Power BI, we might need a ZIP or just one robust file.
  // Let's go with a robust Turmas file for PowerBI as it's the more "structural" data.

  $filename = 'SENAI_Relatorio_PowerBI_' . date('Y-m-d') . '.csv';
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');

  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM

  // Header for combined data (Turmas + some Agenda info)
  $header = ['ID', 'Curso', 'Tipo', 'Periodo', 'Cidade', 'Data Inicio', 'Data Fim', 'Aulas/Semana'];
  fputcsv($out, $header, ';');

  foreach ($data_turmas as $row) {
    fputcsv($out, [
      $row['id'],
      $row['curso'],
      $row['tipo_turma'],
      $row['periodo'],
      $row['cidade'],
      date('Y-m-d', strtotime($row['data_inicio'])),
      date('Y-m-d', strtotime($row['data_fim'])),
      $row['total_dias_semana']
    ], ';');
  }
  fclose($out);
  exit;
}

// ── Excel: SpreadsheetML XML with Two Worksheets ──
$filename = 'SENAI_Relatorio_Completo_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

function xe($v)
{
  return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">

  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title>Relatório Integrado SENAI</Title>
    <Author>SENAI Gestão Escolar</Author>
    <Created><?= date('Y-m-d\TH:i:s\Z') ?></Created>
  </DocumentProperties>

  <Styles>
    <Style ss:ID="sHeader">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#004A8D" /></Borders><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11" ss:FontName="Segoe UI" /><Interior ss:Color="#0056A4" ss:Pattern="Solid" />
    </Style>
    <Style ss:ID="sEven">
      <Alignment ss:Vertical="Center" /><Font ss:Size="10" ss:FontName="Segoe UI" /><Interior ss:Color="#F5F7FA" ss:Pattern="Solid" />
    </Style>
    <Style ss:ID="sOdd">
      <Alignment ss:Vertical="Center" /><Font ss:Size="10" ss:FontName="Segoe UI" /><Interior ss:Color="#FFFFFF" ss:Pattern="Solid" />
    </Style>
    <Style ss:ID="sNum">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Font ss:Size="10" ss:FontName="Segoe UI" /><Interior ss:Color="#F5F7FA" ss:Pattern="Solid" />
    </Style>
  </Styles>

  <!-- WORKSHEET 1: TURMAS -->
  <Worksheet ss:Name="Listagem de Turmas">
    <Table>
      <Column ss:Width="40" />
      <Column ss:Width="200" />
      <Column ss:Width="100" />
      <Column ss:Width="100" />
      <Column ss:Width="100" />
      <Column ss:Width="90" />
      <Column ss:Width="90" />
      <Column ss:Width="80" />
      <Row ss:Height="24">
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">ID</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Curso</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Tipo</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Período</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Cidade</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Início</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Fim</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Dias/Semana</Data></Cell>
      </Row>
      <?php $i = 0;
      foreach ($data_turmas as $r):
        $st = ($i++ % 2 == 0) ? 'sEven' : 'sOdd'; ?>
        <Row>
          <Cell ss:StyleID="sNum"><Data ss:Type="Number"><?= $r['id'] ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['curso']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['tipo_turma']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['periodo']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['cidade']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= date('d/m/Y', strtotime($r['data_inicio'])) ?></Data>
          </Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= date('d/m/Y', strtotime($r['data_fim'])) ?></Data>
          </Cell>
          <Cell ss:StyleID="sNum"><Data ss:Type="Number"><?= $r['total_dias_semana'] ?></Data></Cell>
        </Row>
      <?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- WORKSHEET 2: AGENDA -->
  <Worksheet ss:Name="Agenda de Aulas">
    <Table>
      <Column ss:Width="100" />
      <Column ss:Width="80" />
      <Column ss:Width="80" />
      <Column ss:Width="200" />
      <Column ss:Width="150" />
      <Column ss:Width="150" />
      <Column ss:Width="100" />
      <Row ss:Height="24">
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Dia</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Início</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Fim</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Curso</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Docente</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Ambiente</Data></Cell>
        <Cell ss:StyleID="sHeader"><Data ss:Type="String">Cidade</Data></Cell>
      </Row>
      <?php $i = 0;
      foreach ($data_agenda as $r):
        $st = ($i++ % 2 == 0) ? 'sEven' : 'sOdd'; ?>
        <Row>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['dia']) ?></Data></Cell>
          <Cell ss:StyleID="sNum"><Data ss:Type="String"><?= substr($r['hora_inicio'], 0, 5) ?></Data></Cell>
          <Cell ss:StyleID="sNum"><Data ss:Type="String"><?= substr($r['hora_fim'], 0, 5) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['curso']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['docente']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['ambiente']) ?></Data></Cell>
          <Cell ss:StyleID="<?= $st ?>"><Data ss:Type="String"><?= xe($r['cidade']) ?></Data></Cell>
        </Row>
      <?php endforeach; ?>
    </Table>
  </Worksheet>

</Workbook>
<?php exit; ?>