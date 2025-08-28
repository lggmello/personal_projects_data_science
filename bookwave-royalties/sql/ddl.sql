## Projeto #1: **BookWave Royalties**

-- Receita total por parceiro
SELECT p.nome, ROUND(SUM(v.receita_brl),2) AS receita
FROM f_vendas v
JOIN d_parceiros p ON p.parceiro_id = v.parceiro_id
GROUP BY p.nome
ORDER BY receita DESC;

-- Royalties total por título
SELECT t.titulo, ROUND(SUM(v.royalties_final_brl),2) AS royalties
FROM f_vendas v
JOIN d_titulos t ON t.titulo_id = v.titulo_id
GROUP BY t.titulo
ORDER BY royalties DESC;
