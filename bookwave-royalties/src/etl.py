import pandas as pd
from sqlalchemy import create_engine

# Banco local em arquivo (não precisa instalar nada): bookwave.db
engine = create_engine("sqlite:///bookwave.db")

# 1) Ler CSVs
parc = pd.read_csv("data/raw/parceiros.csv")
tit  = pd.read_csv("data/raw/titulos.csv")
vds  = pd.read_csv("data/raw/vendas.csv", parse_dates=["dt_venda"])

# 2) Regras de conversão
def receita_brl(row):
    base = row.preco_unit_moeda_orig * row.qtd
    if row.moeda_original == "BRL":
        return base
    return base * row.cotacao_conversao

def royalties_brl(row):
    if row.moeda_original == "BRL":
        return row.royalties_moeda_orig
    return row.royalties_moeda_orig * row.cotacao_conversao

vds["receita_brl"] = vds.apply(receita_brl, axis=1).round(2)
vds["royalties_final_brl"] = vds.apply(royalties_brl, axis=1).round(2)

# 3) Persistir tabelas
parc.to_sql("d_parceiros", engine, if_exists="replace", index=False)
tit.to_sql("d_titulos", engine, if_exists="replace", index=False)
vds.to_sql("f_vendas", engine, if_exists="replace", index=False)

# 4) KPIs rápidos (CSV para anexar no README e screenshot)
# Receita por parceiro e mês
vds["mes"] = vds["dt_venda"].dt.to_period("M").astype(str)
kpi_parc_mes = (
    vds.groupby(["mes","parceiro_id"], as_index=False)
       .agg(receita_brl=("receita_brl","sum"),
            royalties_final_brl=("royalties_final_brl","sum"))
)
kpi_parc_mes = kpi_parc_mes.merge(parc, on="parceiro_id")
kpi_parc_mes.to_csv("kpi_receita_royalties_por_parceiro_mes.csv", index=False)

# Top títulos por receita
top_titulos = (
    vds.groupby("titulo_id", as_index=False)["receita_brl"].sum()
       .merge(tit, on="titulo_id")
       .sort_values("receita_brl", ascending=False)
)
top_titulos.to_csv("kpi_top_titulos.csv", index=False)

print("OK! Gerei os arquivos:")
print(" - kpi_receita_royalties_por_parceiro_mes.csv")
print(" - kpi_top_titulos.csv")
