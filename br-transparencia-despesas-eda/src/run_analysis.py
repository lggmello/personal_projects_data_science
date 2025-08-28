
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

df = pd.read_csv("data/raw/despesas.csv")
df["valor_pago"] = pd.to_numeric(df["valor_pago"], errors="coerce").fillna(0.0)

# EDA: função x ano
func_ano = df.groupby(["funcao","ano"], as_index=False)["valor_pago"].sum().sort_values(["funcao","ano"])
func_ano.to_csv("outputs/summary_func_ano.csv", index=False)

piv = func_ano.pivot(index="ano", columns="funcao", values="valor_pago").fillna(0)
plt.figure()
piv.plot(kind="bar")
plt.title("Despesas por Função e Ano")
plt.xlabel("Ano"); plt.ylabel("Valor Pago (R$)")
plt.tight_layout(); plt.savefig("figures/func_ano_barras.png", dpi=150); plt.close()

# Per capita por UF e clusters
pop = {"SP":46289333,"RJ":17366189,"MG":20869101,"BA":14930634,"PR":11597484,"RS":11329605,"SC":7338473,"PE":9674793,"CE":9187103,"PA":8777124}
df = df[df["uf"].isin(pop.keys())].copy()
df["pop"] = df["uf"].map(pop)
uf_func = df.groupby(["uf","funcao"], as_index=False)["valor_pago"].sum()
uf_pivot = uf_func.pivot(index="uf", columns="funcao", values="valor_pago").fillna(0.0)
uf_pivot = uf_pivot.div(pd.Series(pop), axis=0)
uf_pivot.to_csv("outputs/uf_percapita.csv")

Xs = StandardScaler().fit_transform(uf_pivot.values)

best_k, best_s = None, -1
ks, sils = [], []
for k in range(2, min(6, len(uf_pivot))+1):
    km = KMeans(n_clusters=k, n_init=10, random_state=42)
    labels = km.fit_predict(Xs)
    s = silhouette_score(Xs, labels)
    ks.append(k); sils.append(s)
    if s > best_s: best_s, best_k = s, k

with open("outputs/silhouette.txt","w",encoding="utf-8") as f:
    f.write(f"Melhor K: {best_k}\n")
    for k, s in zip(ks, sils):
        f.write(f"k={k} -> silhouette={s:.4f}\n")

pca = PCA(n_components=2, random_state=42)
Xp = pca.fit_transform(Xs); exp = pca.explained_variance_ratio_.sum()

km = KMeans(n_clusters=best_k, n_init=10, random_state=42)
labels = km.fit_predict(Xs)

plt.figure()
for c in sorted(set(labels)):
    idx = labels==c
    plt.scatter(Xp[idx,0], Xp[idx,1], label=f"Cluster {c}")
for i, uf in enumerate(uf_pivot.index):
    plt.annotate(uf, (Xp[i,0], Xp[i,1]))
plt.title(f"PCA UFs — gasto per capita por função (var.expl.: {exp:.2%})")
plt.xlabel("PC1"); plt.ylabel("PC2"); plt.legend()
plt.tight_layout(); plt.savefig("figures/pca_ufs.png", dpi=150); plt.close()

# Testes de hipótese
lines = ["# Resultados de Testes de Hipótese\n"]

# 1) Ano eleitoral (2022) vs outros anos — média mensal de gastos agregados
monthly = df.groupby(["ano","mes"], as_index=False)["valor_pago"].sum()
g2022 = monthly.loc[monthly["ano"]==2022, "valor_pago"].values
goutros = monthly.loc[monthly["ano"]!=2022, "valor_pago"].values
t, p = stats.ttest_ind(g2022, goutros, equal_var=False)
lines += ["## 1) Efeito de ano eleitoral\n",
          "- H0: média mensal de 2022 = demais anos\n",
          "- Teste: t de Welch\n",
          f"- p-valor: **{p:.4g}**\n",
          "> Conclusão: " + ("Rejeitamos H0 (diferença)." if p < 0.05 else "Não rejeitamos H0.") + "\n"]

# 2) Função x Licitação — associação?
cont = pd.crosstab(df["funcao"], df["licitacao"])
chi2, p2, dof, exp = stats.chi2_contingency(cont)
lines += ["\n## 2) Função x Licitação\n",
          "- H0: independência entre função e licitação\n",
          "- Teste: Qui-quadrado de independência\n",
          f"- p-valor: **{p2:.4g}**\n",
          "> Conclusão: " + ("Rejeitamos H0 (há associação)." if p2 < 0.05 else "Não rejeitamos H0.") + "\n"]

with open("outputs/hypothesis_results.md","w",encoding="utf-8") as f:
    f.write("".join(lines))

print("Análise concluída. Veja a pasta outputs/ e figures/.")
