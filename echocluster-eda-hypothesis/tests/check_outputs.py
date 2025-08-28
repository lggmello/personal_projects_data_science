import os, csv, sys

must_exist = [
    "outputs/clusters_summary.csv",
    "outputs/hypothesis_results.md",
    "outputs/silhouette_score.txt",
    "figures/pca_scatter.png",
    "figures/silhouette_k.png",
]
missing = [p for p in must_exist if not os.path.exists(p)]
if missing:
    raise SystemExit(f"Arquivos ausentes: {missing}")
# Checar colunas do summary
import pandas as pd
df = pd.read_csv("outputs/clusters_summary.csv")
expected = {"cluster","n","recency_days_avg","sessions_30d_avg","minutes_30d_avg","arpu_90d","desconto_pct_avg","cancel_rate"}
if not expected.issubset(df.columns):
    raise SystemExit(f"Colunas esperadas não encontradas em clusters_summary.csv: {expected - set(df.columns)}")
print("Smoke tests OK.")
