<?php
/**
 * ÁRVORE → fi_distribuicao_venda (modo visual por padrão)
 * - Lê arvore_vendas.csv e gera os INSERTs
 * - NÃO insere nada no banco (dry-run). Os pontos de execução real estão comentados e marcados.
 */

set_time_limit(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ============== CONFIG ==============
$DB_HOST = 'xxxdb.c3uprzzalu4x.us-east-1.rds.amazonaws.com';
$DB_USER = 'root';
$DB_PASS = 'xxx';
$DB_NAME = 'xxx';

$CSV_ARQUIVO = __DIR__ . '/arvore_vendas.csv';
$DELIMITADOR = ';';            // Troque para ',' se seu CSV for separado por vírgula
$MAX_LINHAS_VISUAL = 5;     // Limite de linhas para visualização

$ID_PARCEIRO = 857;            // Árvore
$TIPO       = 'venda';
$PREORDER   = 'N';
$MOEDA      = 'BRL';
$PAIS       = 'BR';
$COTACAO    = 1.0;             // BRL → BRL
$ROYALTY_MODE = 'igual_preco'; // 'igual_preco' | 'percentual' | 'zero'
$PERCENTUAL_ROYALTIES = 0.00;  // use ex.: 0.52 se ROYALTY_MODE = 'percentual'

// ========= MODO VISUAL (dry-run) =========
// true  = só imprime SQL (recomendado para testes)
// false = (se descomentar os blocos marcados) executa de verdade
$DRY_RUN = true;

// ============== FUNÇÕES AUX ==============
function ptDecimal($v) {
    if ($v === '' || $v === null) return 0.0;
    if (is_numeric($v)) return (float)$v;
    $v = str_replace(['.', ' '], '', $v); // remove milhar
    $v = str_replace(',', '.', $v);       // vírgula → ponto
    return (float)$v;
}

function parseDataPtBr($raw) {
    if ($raw === null || $raw === '') return null;
	foreach (['Y-m-d H:i:s','Y-m-d H:i','Y-m-d'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, trim($raw));
        if ($dt) return $dt->format('Y-m-d H:i:s');
    }

    $s = mb_strtolower(trim($raw), 'UTF-8');
    // exemplos: "29 julho, 2022, 21:10" | "7 julho, 2022, 22:54"
    $meses = [
        'janeiro'=>1,'fevereiro'=>2,'março'=>3,'marco'=>3,'abril'=>4,'maio'=>5,'junho'=>6,
        'julho'=>7,'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12
    ];
    if (preg_match('/(\d{1,2})\s+([a-zçãéóôõ]+),?\s+(\d{4})(?:,\s*(\d{1,2}):(\d{2}))?/u', $s, $m)) {
        $d=(int)$m[1]; $mo=$meses[$m[2]]??null; $y=(int)$m[3]; $h=$m[4]??0; $i=$m[5]??0;
        if ($mo) {
            return (new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:00',$y,$mo,$d,$h,$i)))->format('Y-m-d H:i:s');
        }
    }
    return null;
}

// ============== CONEXÃO ==============
$db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$db->set_charset('utf8mb4');

// (opcional) prepareds para checagem de duplicidade
$stmtCheck = $db->prepare(
    "SELECT COUNT(*) FROM fi_distribuicao_venda
     WHERE id_parceiro_distribuidor=? AND ordem_venda=? AND tipo=?"
);

// (*** EXECUÇÃO REAL ***)
// >>>> DESCOMENTE para executar de verdade (e ajuste $DRY_RUN=false se quiser)
// $stmtIns = $db->prepare(
//     "INSERT INTO fi_distribuicao_venda
//     (id_parceiro_distribuidor, data_venda, ordem_venda, tipo, preorder, qtd, sku,
//      moeda_original, preco_original, moeda_original_lista,
//      preco_original_lista_com_imposto, preco_original_lista_sem_imposto,
//      pais_venda, persentual_royalties, royalties_moeda_orig, royalties_final,
//      cotacao_conversao, obs, baixa, created_at)
//     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
// );
// $db->begin_transaction();

header('Content-Type: text/plain; charset=utf-8');
echo "=== ÁRVORE (modo visual) ===\n\n";

if (!file_exists($CSV_ARQUIVO)) {
    exit("Arquivo não encontrado: $CSV_ARQUIVO\n");
}

$h = fopen($CSV_ARQUIVO, 'r');
if (!$h) exit("Erro ao abrir CSV.\n");

// Cabeçalho
$header = fgetcsv($h, 0, $DELIMITADOR);
if (!$header || count($header) < 7) {
    echo "Aviso: cabeçalho inesperado. Esperado: name,created_at,code,qty,audio_book_isbn,SKU,price\n";
}

// Índices esperados
list($IDX_NAME, $IDX_CREATED_AT, $IDX_CODE, $IDX_QTY, $IDX_ISBN, $IDX_SKU, $IDX_PRICE) = [0,1,2,3,4,5,6];

$total = 0; $novas = 0; $dup = 0; $invalidas = 0;

while (($row = fgetcsv($h, 0, $DELIMITADOR)) !== false) {
    $total++;
    if ($total > $MAX_LINHAS_VISUAL) break;

    $name   = trim($row[$IDX_NAME] ?? '');
    $cAtRaw = $row[$IDX_CREATED_AT] ?? '';
    $code   = trim($row[$IDX_CODE] ?? '');
    $qty    = (int)($row[$IDX_QTY] ?? 1); if ($qty <= 0) $qty = 1;
    $isbn   = preg_replace('/\D+/', '', (string)($row[$IDX_ISBN] ?? ''));
    $sku    = trim($row[$IDX_SKU] ?? '');
    $price  = ptDecimal($row[$IDX_PRICE] ?? 0);

    if ($isbn === '' || $code === '') { $invalidas++; continue; }

    $data_venda = parseDataPtBr($cAtRaw) ?? date('Y-m-d H:i:s');
    $baixa = date('Y-m-01 00:00:00', strtotime($data_venda . ' +1 month'));
    $ordem_venda = $isbn . '-' . $code;
    $obs = 'Árvore - ' . $name;

    // preços
    $preco_original     = $price;
    $preco_lista_com    = $price;
    $preco_lista_sem    = $price;

    // royalties
    if ($ROYALTY_MODE === 'percentual') {
        $persentual_royalties = $PERCENTUAL_ROYALTIES;
        $royalties_moeda      = $price * $qty * $PERCENTUAL_ROYALTIES;
    } elseif ($ROYALTY_MODE === 'igual_preco') {
        $persentual_royalties = 0.0;
        $royalties_moeda      = $price; // “carrega” o price para royalties
    } else { // zero
        $persentual_royalties = 0.0;
        $royalties_moeda      = 0.0;
    }
    $royalties_final = $royalties_moeda * $COTACAO;

    // dup?
    $stmtCheck->bind_param('iss', $ID_PARCEIRO, $ordem_venda, $TIPO);
    $stmtCheck->execute();
    $stmtCheck->bind_result($exists);
    $stmtCheck->fetch();
    $stmtCheck->free_result();

    if ($exists > 0) {
        $dup++;
        echo "🔁 DUP: $ordem_venda (já existe)\n";
        continue;
    }

    // Monta o SQL (apenas para visualização)
    $sql = sprintf(
        "INSERT INTO fi_distribuicao_venda
(id_parceiro_distribuidor, data_venda, ordem_venda, tipo, preorder, qtd, sku, moeda_original, preco_original, moeda_original_lista, preco_original_lista_sem_imposto, preco_original_lista_com_imposto, pais_venda, persentual_royalties, royalties_moeda_orig, royalties_final, cotacao_conversao, obs, baixa, created_at)
VALUES (%d, '%s', '%s', '%s', '%s', %d, '%s', '%s', %.2f, '%s', %.2f, %.2f, '%s', %.4f, %.2f, %.2f, %.4f, '%s', '%s', NOW());",
        $ID_PARCEIRO,
        $data_venda,
        $ordem_venda,
        $TIPO,
        $PREORDER,
        $qty,
        $db->real_escape_string($sku),
        $MOEDA,
        $preco_original,
        $MOEDA,
        $preco_lista_com,
        $preco_lista_sem,
        $PAIS,
        $persentual_royalties,
        $royalties_moeda,
        $royalties_final,
        $COTACAO,
        $db->real_escape_string($obs),
        $baixa
    );

    echo "➡️  $sql\n\n";
    $novas++;

    // (*** EXECUÇÃO REAL ***)
    // >>>> DESCOMENTE este bloco + o prepare + o begin_transaction acima, e defina $DRY_RUN=false
    // if (!$DRY_RUN) {
    //     $stmtIns->bind_param(
    //         "isssssissdsddsddddss",
    //         $ID_PARCEIRO, $data_venda, $ordem_venda, $TIPO, $PREORDER, $qty, $sku,
    //         $MOEDA, $preco_original, $MOEDA, $preco_lista_com, $preco_lista_sem,
    //         $PAIS, $persentual_royalties, $royalties_moeda, $royalties_final,
    //         $COTACAO, $obs, $baixa
    //     );
    //     $stmtIns->execute();
    // }
}

// (*** EXECUÇÃO REAL ***)
// >>>> DESCOMENTE para confirmar a transação
// if (!$DRY_RUN) {
//     $db->commit();
//     // opcional: rodar procedure pós-inserção
//     // $db->query("CALL rotinas_etapa1_financeiro()");
// }

fclose($h);

// Resumo
echo "\n===== RESUMO =====\n";
echo "Processadas (visual): ".$total-1;
echo "\n";
echo "Novas (sem duplicadas): $novas\n";
echo "Duplicadas: $dup\n";
echo "Inválidas (faltando ISBN/Code): $invalidas\n";
echo "================================\n";

// Dica para execução real
echo "\nPara EXECUTAR DE VERDADE:\n";
echo "1) Descomente os blocos marcados como (*** EXECUÇÃO REAL ***).\n";
echo "2) Opcional: troque \$DRY_RUN para false.\n";
echo "3) Recarregue o script.\n";
