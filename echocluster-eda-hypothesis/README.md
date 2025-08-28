# EchoCluster EDA — Análise Não Supervisionada + Testes de Hipótese (Audiobooks)

Projeto com **clusterização (K-Means)** + **PCA** para exploração de perfis de usuários e
**testes de hipótese** (ANOVA/Kruskal e Qui-quadrado) para validar diferenças entre segmentos.

## Stack
- Python: pandas, numpy, scikit-learn, scipy, matplotlib
- GitHub Actions (CI simples)

## Dados
- `data/raw/usuarios.csv` (sintético): métricas de recência, sessões, minutos, valor pago, desconto, cancelamento, plano, canal.

## Como rodar
```bash
pip install -r requirements.txt
python src/unsup_ht.py
```
Saídas geradas:
- `outputs/clusters_summary.csv` (perfil de cada cluster)
- `outputs/hypothesis_results.md` (testes de hipótese e p-valores)
- `outputs/silhouette_score.txt`
- `figures/pca_scatter.png`
- `figures/silhouette_k.png`

## Hipóteses de exemplo
1) **H0**: médias de **valor pago (90d)** são iguais entre clusters.
   **H1**: pelo menos um cluster difere.
   *Teste usado*: ANOVA ou Kruskal (fallback).

2) **H0**: a **proporção de cancelamento** é igual entre clusters.
   **H1**: há associação entre cluster e cancelamento.
   *Teste usado*: Qui-quadrado de independência.

## Estrutura
```
echocluster-eda-hypothesis/
├─ README.md
├─ requirements.txt
├─ data/
│  └─ raw/
│     └─ usuarios.csv
├─ src/
│  └─ unsup_ht.py
├─ outputs/             # gerado pelo script
├─ figures/             # gerado pelo script
├─ tests/
│  └─ check_outputs.py
└─ .github/workflows/
   └─ ci.yaml
```

## Roadmap curto
- Comparações múltiplas (pós-hoc) com correção de Bonferroni
- Clustering hierárquico para comparação
- Exportar segmentos para CRM/BI
