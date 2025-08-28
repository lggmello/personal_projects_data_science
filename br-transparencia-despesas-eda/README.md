# BR Transparência — Projeto (EDA + Clusters + Testes de Hipótese)

> Tema: Despesas públicas brasileiras (inspirado no Portal da Transparência / SIAFI).
> Foco: Contar uma história com dados do insight exploratório à validação estatística.

## O que foi feito
Mapeei padrões de gasto por função(Saúde, Educação, Segurança), comparei UFs por gasto per capita e validei hipóteses sobre ano eleitoral e processos de compra (licitação) usando testes de hipótese.
O repositório mostra EDA (análise exploratória de dados) estruturada, clusterização (KMeans), PCA, e estatística inferencial (t de Welch e Qui‑quadrado).

## Hipóteses avaliadas
1. H0: A média mensal de gastos em 2022 (ano eleitoral) é igual à média dos demais anos.
   Teste: t de Welch.  
   Saída: `outputs/hypothesis_results.md`.

2. H0: A função de governo (Saúde/Educação/Segurança) é independente de haver licitação.
   Teste: Qui‑quadrado de independência.  
   Saída: `outputs/hypothesis_results.md`.

> As conclusões aparecem no arquivo de resultados com p‑valores e nota sobre rejeitar ou não H0.

## Histórias visuais (prints)
Despesas por função e ano
![Barras: função x ano](figures/func_ano_barras.png)

UFs por gasto per capita (PCA + clusters)
![PCA UFs](figures/pca_ufs.png)

Seleção do K (Silhouette)
![Silhouette K](figures/silhouette_k.png)

> As figuras são geradas ao rodar `python src/run_analysis.py`.

## Stack & arquitetura
- Python: `pandas`, `numpy`, `scipy`, `scikit-learn`, `matplotlib`
- Pipelines: script único reprodutível (`src/run_analysis.py`)
- DevOps: GitHub Actions (CI) com execução do script e teste de fumaça

## Estrutura resumida:
```
br-transparencia-despesas-eda/
├─ data/raw/despesas.csv        # sintético (substituível por dados reais do Portal)
├─ src/run_analysis.py          # EDA, clusters, PCA e testes de hipótese
├─ outputs/                     # CSVs + resultados dos testes
└─ figures/                     # gráficos gerados
```

## Como rodar localmente
```bash
pip install -r requirements.txt
python src/run_analysis.py
```
Serão gerados:
- `outputs/summary_func_ano.csv`, `outputs/uf_percapita.csv`, `outputs/hypothesis_results.md`, `outputs/silhouette.txt`
- `figures/func_ano_barras.png`, `figures/pca_ufs.png`, `figures/silhouette_k.png`

## Usando dados reais do Portal da Transparência
1. Baixe os CSVs desejados (despesas/pagamentos).
2. Renomeie/garanta as colunas:
    `ano, mes, orgao, funcao, uf, valor_pago, licitacao (SIM/NAO), fav_tipo (EMPRESA/PESSOA)`
3. Salve em `data/raw/despesas.csv` e rode o script novamente.

> Dica: manter os nomes de colunas permite reaproveitar todo o pipeline sem alterações.

## Próximos passos técnicos
- Séries temporais (Holt‑Winters/ARIMA) por função/UF.
- Regressão para determinantes de gasto (PIB, população, transferências).
- Exportar clusters/indicadores para BI (Power BI / Metabase).

## Mini‑roteiro para LinkedIn
> Publique junto com 2 prints (barras e PCA).

Título: “Entendendo padrões de gasto público com EDA, clusters e testes de hipótese (Projeto open‑source)”
Post (copiar/colar):
- Usei uma temática do Portal da Transparência para praticar EDA e estatística aplicada.
- Comparei funções (Saúde/Educação/Segurança) ao longo dos anos e clusterizei UFs por gasto per capita.
- Validei 2 hipóteses: (1) 2022 vs. outros anos (t de Welch) e (2) função x licitação (Qui‑quadrado).
- Repo tem CI no GitHub Actions e dados substituíveis por CSVs reais do Portal.
🔗 Código: https://github.com/lggmello/personal_projects_data_science
