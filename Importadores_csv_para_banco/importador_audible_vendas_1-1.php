<?php
/******************************************************
 * Importador Audible -> fi_distribuicao_venda (preview)
 * - Lê audible_vendas.csv (delimitador ;)
 * - Mostra na tela os INSERTs (não executa)
 * - Para executar de verdade: descomente as linhas marcadas
 ******************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ====== CONFIG BANCO ====== */
$DB_HOST = 'xxxdb.c3uprzzalu4x.us-east-1.rds.amazonaws.com';
$DB_USER = 'root';
$DB_PASS = 'xxx';
$DB_NAME = 'xxx';

/* ====== ARQUIVO CSV ====== */
$ARQUIVO_CSV = __DIR__ . "/audible_vendas.csv";
$DELIM       = ';';

/* ====== CONEXÃO ====== */
$db = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($db->connect_errno) {
    die("Falha na conexão MySQL: {$db->connect_errno} - {$db->connect_error}");
}
$db->set_charset("utf8mb4");

/* ====== HELPERS ====== */
function esc(mysqli $db, ?string $v): string {
    if ($v === null) return '';
    return $db->real_escape_string($v);
}
function nfloat($v): float {
    if ($v === null) return 0.0;
    // remove % e espaços; troca , por .
    $v = trim(str_replace(['%',' '], '', (string)$v));
    $v = str_replace(['.', ','], ['', '.'], $v); // 1.234,56 -> 1234.56
    if ($v === '' || !is_numeric($v)) return 0.0;
    return (float)$v;
}
function nf($v, $dec=2): string {
    // string numérica no formato SQL com ponto
    return number_format((float)$v, $dec, '.', '');
}

/* >>> NOVO: normaliza ISBN (tira tudo que não é dígito/X) */
function norm_isbn(?string $s): string {
    $s = strtoupper((string)$s);
    return preg_replace('/[^0-9X]/', '', $s);
}

/* >>> NOVO: resolve o SKU consultando a tabela produto
   1) por ISBN (isbn, isbn_livro, isbn_audiolivro, isbn_ebook)
   2) fallback por Title + Author (produto.nome + produto.autor)
   Retorna [$sku, $porque]  */
function resolveSku(mysqli $db, ?string $isbn, ?string $title, ?string $author): array {
    static $cache = [];
    $key = 'I:'.norm_isbn($isbn).'|T:'.mb_strtolower(trim((string)$title)).'|A:'.mb_strtolower(trim((string)$author));
    if (isset($cache[$key])) return $cache[$key];

    $sku = '';
    $why = '';

    // 1) tentativa por ISBN
    $n = norm_isbn($isbn);
    if ($n !== '') {
        $sqlIsbn = "
            SELECT sku
            FROM produto
            WHERE
                REPLACE(REPLACE(REPLACE(UPPER(COALESCE(isbn,'')),'-',''),' ',''),'.','') = ?
             OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(isbn_livro,'')),'-',''),' ',''),'.','') = ?
             OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(isbn_audiolivro,'')),'-',''),' ',''),'.','') = ?
             OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(isbn_ebook,'')),'-',''),' ',''),'.','') = ?
            LIMIT 1";
        if ($stmt = $db->prepare($sqlIsbn)) {
            $stmt->bind_param('ssss', $n, $n, $n, $n);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $sku = (string)$row['sku'];
                    $why = 'isbn';
                }
            }
            $stmt->close();
        }
    }

    // 2) fallback por Title + Author
    if ($sku === '' && trim((string)$title) !== '' && trim((string)$author) !== '') {
        $titleLike  = '%'.trim($title).'%';
        $authorLike = '%'.trim($author).'%';
        $sqlTA = "
            SELECT sku
            FROM produto
            WHERE nome LIKE ? AND autor LIKE ?
            LIMIT 1";
        if ($stmt2 = $db->prepare($sqlTA)) {
            $stmt2->bind_param('ss', $titleLike, $authorLike);
            if ($stmt2->execute()) {
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    $sku = (string)$row2['sku'];
                    $why = 'title+author';
                }
            }
            $stmt2->close();
        }
    }

    $cache[$key] = [$sku, $why];
    return [$sku, $why];
}



/* ====== ABERTURA DO CSV ====== */
$h = fopen($ARQUIVO_CSV, 'r');
if (!$h) die("Não consegui abrir o arquivo: $ARQUIVO_CSV");

$header = fgetcsv($h, 0, $DELIM);
if ($header === false) die("Não consegui ler o cabeçalho do CSV.");

if (isset($header[0])) {
    // remove BOM do primeiro cabeçalho
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}
$header = array_map(fn($h) => preg_replace('/\s+/', ' ', trim($h)), $header);

/* ====== MAPA DE COLUNAS DO CSV AUDIBLE ====== */
$idx = [
    'royalty_earner'        => array_search('Royalty Earner', $header),
    'product_id'            => array_search('Product ID', $header),
    'digital_isbn'          => array_search('Digital ISBN', $header),
    'transaction_type'      => array_search('Transaction Type', $header),
    'marketplace'           => array_search('Marketplace', $header),
    'purchase_type'         => array_search('Purchase Type', $header),
    'currency'              => array_search('Currency', $header),
    'royalty_rate'          => array_search('Royalty Rate', $header),
    'net_units'             => array_search('Net Units', $header),
    'net_sales'             => array_search('Net Sales', $header),
    'net_royalties_earned'  => array_search('Net Royalties Earned', $header),
/* >>> NOVO: precisamos de Author e Title para fallback */
    'author'                => array_search('Author', $header),   // >>> NOVO
    'title'                 => array_search('Title', $header),    // >>> NOVO
];
foreach ($idx as $k=>$v) {
    if ($v === false) die("Coluna não encontrada no CSV: $k");
}

/* ====== PREVIEW ====== */
echo "<pre>";
echo ">>> Modo PREVIEW (não executa INSERT). Para executar, descomente as linhas indicadas no código.\n\n";

/* ====== LOOP ====== */
$total = 0;
$mostrados = 0;
$ins = 0;

while (($ln = fgetcsv($h, 0, $DELIM)) !== false) {
    if ($ln === [null] || (count($ln) === 1 && trim($ln[0]) === '')) continue;

    $total++;

    $royaltyEarner = $ln[$idx['royalty_earner']] ?? '';
    $productId     = $ln[$idx['product_id']] ?? '';
    $isbn          = $ln[$idx['digital_isbn']] ?? '';
    $ttype         = $ln[$idx['transaction_type']] ?? '';
    $market        = $ln[$idx['marketplace']] ?? '';
    $purchaseType  = $ln[$idx['purchase_type']] ?? '';
    $currency      = $ln[$idx['currency']] ?? '';
    $royRate       = $ln[$idx['royalty_rate']] ?? '';
    $netUnits      = (int)($ln[$idx['net_units']] ?? 0);
    $netSales      = nfloat($ln[$idx['net_sales']] ?? null);
    $netRoyalties  = nfloat($ln[$idx['net_royalties_earned']] ?? null);
/* >>> NOVO: pegar Author e Title */
    $author        = $ln[$idx['author']] ?? '';      // >>> NOVO
    $title         = $ln[$idx['title']] ?? '';       // >>> NOVO

    // Só vendas
    if (strcasecmp($ttype, 'Sale') !== 0) continue;

    // data_venda a partir de "Royalty Earner" ("July 2023")
    if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/i', $royaltyEarner, $m)) {
        $monthNum   = date('m', strtotime($m[1].' 1'));
        $year       = (int)$m[2];
        $data_venda = sprintf('%04d-%02d-01', $year, $monthNum);
    } else {
        $data_venda = date('Y-m-01');
    }
    $baixa = date('Y-m-01', strtotime($data_venda.' +1 month'));

    // Campos do seu schema
    $id_parceiro_distribuidor = 1196;
    $tipo        = 'venda';
    $preorder    = 'N';
    #$qtd         = 1; // cada linha = 1 venda
	$qtd                  = $netUnits;
	
    /* >>> ALTERADO: resolver o SKU via tabela produto */
    list($skuResolvido, $motivoSku) = resolveSku($db, $isbn, $title, $author); // >>> NOVO
    $sku = $skuResolvido;                                                      // >>> NOVO
    if ($sku === '') {
        // Se não achou SKU, você pode decidir pular a linha ou inserir marcando como 'DESCONHECIDO'
        // Aqui, vamos marcar como 'DESCONHECIDO' para você ver no preview:
        $sku = 'DESCONHEC'; // 10 chars para caber
        $motivoSku = 'nao_encontrado';
    }
    // se sua coluna sku for exatamente varchar(10), garanta o tamanho:
    $sku = substr($sku, 0, 10);

    $moeda_original       = $currency ?: 'BRL';
    $preco_original       = $netSales; // não há list price; usando net sales
    $moeda_original_lista = $moeda_original;
    $preco_lista_com_imp  = $preco_original;
    $preco_lista_sem_imp  = $preco_original;
    $pais_venda           = $market ?: 'BR';
    $percentual_roy       = nfloat($royRate)/100;   // mantém sua escolha atual (fração). Se quiser armazenar 50.00, remova "/100".
    $royalties_moeda      = $netRoyalties;          // BRL
    $royalties_final      = $royalties_moeda;       // igual
    $cotacao              = ($moeda_original === 'BRL') ? 1.0 : 1.0; // ajuste se precisar
    #$obs                  = $purchaseType ?: 'Single Purchase';
    $obs = substr('2025-Q1 ' . ($purchaseType ?: 'Single Purchase'), 0, 50);

    // ordem_venda determinística (para PK composta ordem_venda+tipo)
    $ordem_venda = substr(hash('sha256', implode('|', [
        microtime(),$productId, $isbn, $market, $purchaseType, $ttype, $qtd,
        nf($preco_original, 2), $data_venda
    ])), 0, 40);

    // Monta SQL (preview) — com escaping e formatação numérica
    $sql = "INSERT IGNORE INTO fi_distribuicao_venda "
         . "(id_parceiro_distribuidor,data_venda,ordem_venda,tipo,preorder,qtd,sku,"
         . "moeda_original,preco_original,moeda_original_lista,preco_original_lista_com_imposto,"
         . "preco_original_lista_sem_imposto,pais_venda,persentual_royalties,royalties_moeda_orig,"
         . "royalties_final,cotacao_conversao,obs,baixa) VALUES ("
         . (int)$id_parceiro_distribuidor . ","
         . "'" . esc($db, $data_venda)     . "',"
         . "'" . esc($db, $ordem_venda)    . "',"
         . "'" . esc($db, $tipo)           . "',"
         . "'" . esc($db, $preorder)       . "',"
         . (int)$qtd                       . ","
         . "'" . esc($db, $sku)            . "',"
         . "'" . esc($db, $moeda_original) . "',"
         . nf($preco_original, 2)          . ","
         . "'" . esc($db, $moeda_original_lista) . "',"
         . nf($preco_lista_com_imp, 2)     . ","
         . nf($preco_lista_sem_imp, 2)     . ","
         . "'" . esc($db, $pais_venda)     . "',"
         . nf($percentual_roy, 4)          . ","   // 4 casas se armazenar fração
         . nf($royalties_moeda, 2)         . ","
         . nf($royalties_final, 2)         . ","
         . nf($cotacao, 4)                 . ","
         . "'" . esc($db, $obs)            . "',"
         . "'" . esc($db, $baixa)          . "'"
         . ");";

    // Mostra na tela a SQL que seria executada
    echo $sql . "\n";
    // Info extra de como o SKU foi resolvido (para sua auditoria visual)
    echo "-- sku_resolucao: {$motivoSku} | isbn={$isbn} | title=" . esc($db,$title) . " | author=" . esc($db,$author) . "\n\n";

    // ====== PARA EXECUTAR DE VERDADE, DESCOMENTE ABAIXO ======
     if (!$db->query($sql)) {
         echo "-- ERRO ao inserir: " . $db->error . "\n";
     } else {
         $ins += $db->affected_rows > 0 ? 1 : 0;
     }
    // =========================================================

    $mostrados++;
}

fclose($h);

echo "\nResumo:\n";
echo "📄 Linhas lidas no CSV: $total\n";
echo "👀 Inserts exibidos (preview): $mostrados\n";
// echo "🟢 Linhas efetivamente inseridas: $ins\n"; // habilite quando executar de fato
echo "</pre>";

/* ====== DICAS ======
1) Se os INSERTs estiverem OK, remova os 'echo $sql' se quiser, e descomente o bloco do $db->query($sql).
2) Se 'sku' for importante e não puder truncar, aumente a coluna:
   ALTER TABLE fi_distribuicao_venda MODIFY sku VARCHAR(32);
3) Opcional (performance): crie índices em produto(isbn, isbn_livro, isbn_audiolivro, isbn_ebook), produto(nome), produto(autor).
4) Se preferir armazenar percentual como 50.00 (e não 0.50), troque:
   $percentual_roy = nfloat($royRate);  // e no INSERT use nf(..., 2)
*/
