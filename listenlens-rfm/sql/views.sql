-- Receita BRL sempre = preco_unit_moeda_orig * qtd * cotacao (se moeda != BRL)
CREATE OR REPLACE VIEW v_receita_brl AS
SELECT
  dt_venda,
  parceiro_id,
  titulo_id,
  SUM(
    CASE
      WHEN moeda_original = 'BRL' THEN preco_unit_moeda_orig * qtd
      ELSE preco_unit_moeda_orig * qtd * cotacao_conversao
    END
  ) AS receita_brl
FROM f_vendas
GROUP BY 1,2,3;

-- Royalties final em BRL
CREATE OR REPLACE VIEW v_royalties AS
SELECT
  dt_venda, parceiro_id, titulo_id,
  SUM(
    CASE
      WHEN moeda_original = 'BRL' THEN royalties_moeda_orig
      ELSE royalties_moeda_orig * cotacao_conversao
    END
  ) AS royalties_final_brl
FROM f_vendas
GROUP BY 1,2,3;

-- Top títulos por receita
CREATE OR REPLACE VIEW v_top_titulos AS
SELECT t.titulo, p.nome AS parceiro, r.receita_brl
FROM v_receita_brl r
JOIN d_titulos t USING(titulo_id)
JOIN d_parceiros p USING(parceiro_id)
ORDER BY r.receita_brl DESC
LIMIT 10;
