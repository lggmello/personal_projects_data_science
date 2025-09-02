<?php
/* 
   Autores: Luis Mello e Sandro 
   Versão: 1.4
   Data: 01/08/2025
   Descrição: Script de importação de GoogleSalesTransactionReport.csv para Servidor MySql xxx
*/

set_time_limit(false);
$db = mysqli_connect('xxxdb.c3uprzzalu4x.us-east-1.rds.amazonaws.com', 'root', 'xxx', 'xxx');

if ($db) {
    $handle = fopen("GoogleSalesTransactionReport.csv", "r");

    if ($handle) {
        stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
        fgetcsv($handle, 0, "\t"); // Pula cabeçalho

        $p = 1;
        $total_linhas = 0;
        $linhas_para_inserir = 0;

        echo "<pre>";

        while (($linha = fgetcsv($handle, 0, "\t")) !== false) {
            $total_linhas++;
			echo "linha # $p\r\n";

            list($TransactionDate,$Id,$Product,$Type,$Preorder,$Qty,$PrimaryISBN,$ImprintName,
                 $Title,$Author,$OriginalListPriceCurrency,$OriginalListPrice,$ListPriceCurrency,
                 $ListPriceTaxInclusive,$ListPriceTaxExclusive,$CountryOfSale,$PublisherRevenueP,
                 $PublisherRevenue,$PaymentCurrency,$PaymentAmount,$CurrencyConversionRate)
                 = $linha;

            if (empty($Id)) continue;

            $date = DateTime::createFromFormat('d/m/Y', $TransactionDate);
            $data_venda = $date->format('Y-m-d'); // Saída: 2028-08-06
            $baixa = date('Y-m-01 00:00:00', strtotime("$data_venda +1 month")); // Data de baixa = primeiro dia do mês seguinte
            $ordem_venda = $PrimaryISBN . '-' . $Id;
            $tipo = ($Type == "Sale") ? "venda" : "devolucao";
            $Preorder = ($Preorder == "None") ? "N" : "Y";
            $PaymentAmount = ($PaymentAmount == "") ? "0" : $PaymentAmount;
            $CurrencyConversionRate = ($CurrencyConversionRate == "") ? "1" : $CurrencyConversionRate;
            $sku_correto = substr($PrimaryISBN, 5);

            // Correções numéricas
            $OriginalListPrice = str_replace(',', '.', $OriginalListPrice);
            $ListPriceTaxInclusive = str_replace(',', '.', $ListPriceTaxInclusive);
            $ListPriceTaxExclusive = str_replace(',', '.', $ListPriceTaxExclusive);
            $PublisherRevenueP = str_replace('%', '', $PublisherRevenueP);
            $PublisherRevenueP = str_replace(',', '.', $PublisherRevenueP);
            $PublisherRevenueP = floatval($PublisherRevenueP) / 100;
            $PublisherRevenue = str_replace(',', '.', $PublisherRevenue);
            $PaymentAmount = str_replace(',', '.', $PaymentAmount);
            $CurrencyConversionRate = str_replace(',', '.', $CurrencyConversionRate);

            // Verificação da chave existente
            $chave = $ordem_venda . $tipo;
            $stmt_check = mysqli_prepare($db, "SELECT COUNT(*) FROM fi_distribuicao_venda WHERE CONCAT(ordem_venda, tipo) = ? AND data_venda >= DATE_SUB(NOW(), INTERVAL 3 MONTH)");
            mysqli_stmt_bind_param($stmt_check, "s", $chave);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $count);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($count > 0) {
                echo "🔁 Já existe: $chave \n";
            } else {

                // Monta SQL (mantém original)
                $sql = "INSERT IGNORE INTO fi_distribuicao_venda (id_parceiro_distribuidor,data_venda,ordem_venda,tipo,preorder,qtd,sku,moeda_original,preco_original,moeda_original_lista,preco_original_lista_com_imposto,preco_original_lista_sem_imposto,pais_venda,persentual_royalties,royalties_moeda_orig,royalties_final,cotacao_conversao,obs,baixa)
        VALUES(484,'$data_venda','$ordem_venda','$tipo','$Preorder',$Qty,'$sku_correto','$OriginalListPriceCurrency',$OriginalListPrice,'$ListPriceCurrency',$ListPriceTaxInclusive,$ListPriceTaxExclusive,'$CountryOfSale',$PublisherRevenueP,$PublisherRevenue,$PaymentAmount,$CurrencyConversionRate,'$Product','$baixa');";

                // Apenas imprime o SQL
                #echo "✅ Pronto para inserir: \n$sql\n\n";
                echo " $sql\n";
                $linhas_para_inserir++;

                // Para ativar a inserção no futuro:
                $result = mysqli_query($db, $sql);
                if ($result) {
                    echo "✅ Inserido com sucesso!\n";
                } else {
                    echo "❌ Erro ao inserir: " . mysqli_error($db) . "\n";
                }
            }
            // break após 5 para testes
            if ($p++ == 2000) break;
        }

        echo "\nResumo:\n";
        echo "📄 Total de linhas processadas: $total_linhas\n";
        echo "🟢 Linhas novas inseridas na tabela fi_distribuicao_venda: $linhas_para_inserir\n";

        fclose($handle);
        // ✅ Executa a procedure após todos os INSERTS
        $call = mysqli_query($db, "CALL rotinas_etapa1_financeiro()");
        if ($call) {
            echo "\n🚀 Procedure 'rotinas_etapa1_financeiro()' executada com sucesso!\n";
        } else {
            echo "\n❌ Erro ao executar a procedure: " . mysqli_error($db) . "\n";
        }
        echo "</pre>";
    } else {
        echo "Erro ao abrir o arquivo.";
    }
} else {
    echo "Erro na conexão com o banco.";
}
?>
