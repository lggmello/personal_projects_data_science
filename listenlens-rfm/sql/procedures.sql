DELIMITER $$
CREATE PROCEDURE sp_atualiza_valores_brl()
BEGIN
  UPDATE f_vendas
  SET receita_brl =
    CASE
      WHEN moeda_original = 'BRL' THEN preco_unit_moeda_orig * qtd
      ELSE preco_unit_moeda_orig * qtd * cotacao_conversao
    END,
      royalties_final_brl =
    CASE
      WHEN moeda_original = 'BRL' THEN royalties_moeda_orig
      ELSE royalties_moeda_orig * cotacao_conversao
    END;
END $$
DELIMITER ;
