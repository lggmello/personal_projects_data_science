import os
import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from scipy import stats

os.makedirs("outputs", exist_ok=True)
os.makedirs("figures", exist_ok=True)

df = pd.read_csv("data/raw/usuarios.csv", parse_dates=["dt_ultima_atividade"])

# ----------------------------
# 1) Seleção de features numéricas
# ----------------------------
feats = ["recency_days","sessions_30d","minutes_30d","valor_pago_90d","desconto_pct"]
X = df[feats].copy()
X["desconto_pct"] = X["desconto_pct"].astype(float)
X = X.fillna(0)

# ----------------------------
# 2) Padronização + PCA (2D para visualização)
# ----------------------------
scaler = StandardScaler()
Xs = scaler.fit_transform(X)

pca = PCA(n_components=2, random_state=42)
Xp = pca.fit_transform(Xs)
explained = pca.explained_variance_ratio_.sum()

# ----------------------------
# 3) Escolha de K por silhouette (k=2..6)
# ----------------------------
best_k = None
best_sil = -1
sil_scores = []
for k in range(2, 7):
    km = KMeans(n_clusters=k, n_init=10, random_state=42)
    labels = km.fit_predict(Xs)
    sil = silhouette_score(Xs, labels)
    sil_scores.append((k, sil))
    if sil > best_sil:
        best_sil = sil
        best_k = k

with open("outputs/silhouette_score.txt","w",encoding="utf-8") as f:
    f.write(f"Melhor k (silhouette): {best_k}\n")
    for k, s in sil_scores:
        f.write(f"k={k} -> silhouette={s:.4f}\n")

# ----------------------------
# 4) Fit final com best_k + rótulos
# ----------------------------
kmeans = KMeans(n_clusters=best_k, n_init=10, random_state=42)
df["cluster"] = kmeans.fit_predict(Xs)

# ----------------------------
# 5) Resumo por cluster
# ----------------------------
summary = (
    df.groupby("cluster")
      .agg(
          n=("user_id","count"),
          recency_days_avg=("recency_days","mean"),
          sessions_30d_avg=("sessions_30d","mean"),
          minutes_30d_avg=("minutes_30d","mean"),
          arpu_90d=("valor_pago_90d","mean"),
          desconto_pct_avg=("desconto_pct","mean"),
          cancel_rate=("cancelou","mean")
      )
      .reset_index()
)
summary.to_csv("outputs/clusters_summary.csv", index=False)

# ----------------------------
# 6) Visualização PCA (1 figura)
# ----------------------------
plt.figure()
for c in sorted(df["cluster"].unique()):
    idx = df["cluster"]==c
    plt.scatter(Xp[idx,0], Xp[idx,1], label=f"Cluster {c}")
plt.title(f"PCA (2D) — clusters; var.expl.: {explained:.2%}")
plt.xlabel("PC1")
plt.ylabel("PC2")
plt.legend()
plt.tight_layout()
plt.savefig("figures/pca_scatter.png", dpi=150)

# Silhouette plot simples (linha por k)
ks, sils = zip(*sil_scores)
plt.figure()
plt.plot(ks, sils, marker="o")
plt.title("Silhouette por K")
plt.xlabel("K")
plt.ylabel("Silhouette")
plt.xticks(ks)
plt.tight_layout()
plt.savefig("figures/silhouette_k.png", dpi=150)

# ----------------------------
# 7) Testes de hipótese
# ----------------------------
lines = []
lines.append("# Resultados de Testes de Hipótese\n")

# 7.1 Valor pago 90d ~ cluster (médias iguais?)
groups = [g["valor_pago_90d"].values for _, g in df.groupby("cluster")]
# Normalidade por cluster (Shapiro): amostra máx 500 por estabilidade
normals = []
for i, g in enumerate(groups):
    sample = g if len(g) <= 500 else np.random.default_rng(42).choice(g, 500, replace=False)
    w, p = stats.shapiro(sample) if len(sample) >= 3 else (np.nan, np.nan)
    normals.append(p if not np.isnan(p) else 1.0)
# Homogeneidade (Levene)
lev_w, lev_p = stats.levene(*groups) if all(len(g)>1 for g in groups) else (np.nan, np.nan)

if all(pv > 0.05 for pv in normals) and (not np.isnan(lev_p) and lev_p > 0.05):
    # ANOVA clássica
    f, p = stats.f_oneway(*groups)
    test_used = "ANOVA (one-way)"
else:
    # Kruskal (não-paramétrico)
    f, p = stats.kruskal(*groups)
    test_used = "Kruskal–Wallis"

lines.append("## 1) Valor pago (90d) por cluster\n")
lines.append(f"- Teste usado: **{test_used}**\n")
lines.append(f"- p-valor: **{p:.4g}**\n")
lines.append(f"- Shapiro por cluster (p-valores): {['{:.3f}'.format(x) for x in normals]}\n")
lines.append(f"- Levene (homogeneidade): p={lev_p:.3f}\n")
if p < 0.05:
    lines.append("> **Conclusão:** rejeitamos H0; há diferença entre clusters em valor pago.\n")
else:
    lines.append("> **Conclusão:** não rejeitamos H0; não há evidência de diferença nas médias.\n")

# 7.2 Cancelamento ~ cluster (proporções iguais?)
cont = pd.crosstab(df["cluster"], df["cancelou"])
chi2, chi_p, dof, expected = stats.chi2_contingency(cont)
lines.append("\n## 2) Cancelamento por cluster\n")
lines.append("- Teste usado: **Qui-quadrado de independência**\n")
lines.append(f"- p-valor: **{chi_p:.4g}**\n")
if chi_p < 0.05:
    lines.append("> **Conclusão:** rejeitamos H0; cancelamento depende do cluster.\n")
else:
    lines.append("> **Conclusão:** não rejeitamos H0; não há evidência de associação.\n")

# Salvar relatório
with open("outputs/hypothesis_results.md","w",encoding="utf-8") as f:
    f.write("\n".join(lines))

print("Pipeline concluído. Arquivos em 'outputs' e 'figures'.")
