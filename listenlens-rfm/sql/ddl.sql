## Projeto #1: **BookWave Royalties**

### 1) DDL de tabelas (cole em `sql/ddl.sql`)
```sql
CREATE TABLE d_parceiros (
  parceiro_id INT PRIMARY KEY,
  nome VARCHAR(120)
);

CREATE TABLE d_titulos (
  titulo_id INT PRIMARY KEY,
  sku VARCHAR(64) UNIQUE,
  titulo VARCHAR(255),
  produtora VARCHAR(120),
  autor VARCHAR(120),
  moeda_padrao CHAR(3) DEFAULT 'BRL'
);

CREATE TABLE f_vendas (
  venda_id BIGINT PRIMARY KEY,
  dt_venda DATE,
  parceiro_id INT,
  titulo_id INT,
  qtd INT,
  preco_unit_moeda_orig DECIMAL(10,2),
  moeda_original CHAR(3),
  cotacao_conversao DECIMAL(10,4),
  receita_brl DECIMAL(12,2),
  royalties_moeda_orig DECIMAL(12,2),
  royalties_final_brl DECIMAL(12,2),
  FOREIGN KEY (parceiro_id) REFERENCES d_parceiros(parceiro_id),
  FOREIGN KEY (titulo_id) REFERENCES d_titulos(titulo_id)
);
