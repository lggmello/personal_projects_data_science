import os, pandas as pd
must = [
    "outputs/summary_func_ano.csv",
    "outputs/uf_percapita.csv",
    "outputs/hypothesis_results.md",
    "outputs/silhouette.txt",
    "figures/func_ano_barras.png",
    "figures/pca_ufs.png",
]
missing = [p for p in must if not os.path.exists(p)]
if missing:
    raise SystemExit(f"Arquivos ausentes: {missing}")
df = pd.read_csv("outputs/summary_func_ano.csv")
assert {"funcao","ano","valor_pago"}.issubset(df.columns)
print("Smoke tests OK.")
