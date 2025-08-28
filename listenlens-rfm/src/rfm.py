import pandas as pd
import matplotlib.pyplot as plt

df = pd.read_csv("data/raw/consumo.csv", parse_dates=["dt_evento"])

# 1) Base de referência (última data observada)
data_ref = df["dt_evento"].max()

# 2) Métricas por usuário
rfm = (
    df.groupby("user_id")
      .agg(
          recency_days = ("dt_evento", lambda s: (data_ref - s.max()).days),
          frequency    = ("dt_evento", "count"),
          monetary     = ("valor_pago", "sum")
      )
      .reset_index()
)

# 3) Scores por quintil (1=ruim ... 5=ótimo)
def score(series, reverse=False):
    q = series.quantile([.2,.4,.6,.8]).to_list()
    def s(x):
        v = 1 + sum(x > t for t in q)
        return 6 - v if reverse else v
    return series.apply(s)

rfm["R"] = score(rfm["recency_days"], reverse=True)  # menor recency = melhor
rfm["F"] = score(rfm["frequency"])
rfm["M"] = score(rfm["monetary"])

rfm["RFM_Score"] = rfm["R"].astype(str)+rfm["F"].astype(str)+rfm["M"].astype(str)

# 4) Regras simples de segmentação
def segment(row):
    if row.R >=4 and row.F >=4 and row.M >=4: return "Campeões"
    if row.R >=4 and row.F >=3: return "Leais"
    if row.R <=2 and row.F <=2: return "Em risco"
    return "Potenciais"

rfm["segmento"] = rfm.apply(segment, axis=1)

# 5) Salvar resultados e gráfico
rfm.to_csv("rfm_resultado.csv", index=False)

seg = rfm["segmento"].value_counts().sort_values(ascending=False)
plt.figure()
seg.plot(kind="bar")
plt.title("Distribuição de Segmentos (RFM)")
plt.xlabel("Segmento")
plt.ylabel("Usuários")
plt.tight_layout()
plt.savefig("rfm_segmentos.png", dpi=150)

print("Gerado:")
print(" - rfm_resultado.csv")
print(" - rfm_segmentos.png")
