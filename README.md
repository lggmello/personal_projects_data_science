# Portfólio de Projetos — Análise de Dados

Seja muito Bem-vindo(a)! Aqui concentro alguns projetos que desenvolvi para praticar e demonstrar habilidades em ETL, EDA, estatística, machine learning, SQL e dashboards.
Todos os projetos são reprodutíveis e usam dados sintéticos(ou públicos), podendo ser adaptados para dados reais.

## [BookWave Royalties](./bookwave-royalties)
Tema: Audiobooks & editoras 
- Este projeto simula o processo de calcular royalties e receita de editoras a partir de dados de vendas em diferentes moedas.
    A ideia é praticar ETL (Extract, Transform, Load) e a criação de KPIs financeiros usados em editoras e plataformas de audiobooks.
- ETL em Python + MySQL para calcular receita e royalties multi-moeda.
- KPIs: receita por parceiro, royalties por título, % vendas em USD vs BRL.
- Saídas: CSVs de KPIs + dashboards simples.

## [ListenLens RFM](./listenlens-rfm)
Tema: Segmentação de usuários de streaming
- Este projeto mostra como classificar usuários de um serviço de streaming/leitura usando o modelo RFM (Recência, Frequência, Monetário).
    A ideia é identificar quem está engajado, quem tem potencial e quem corre risco de churn.
- Implementa RFM (Recência, Frequência, Monetário).
- Classifica clientes em segmentos (“Campeões”, “Em risco”, “Leais”).
- Gera gráfico de barras dos segmentos + CSV com scores.

## [EchoCluster EDA](./echocluster-eda-hypothesis)
Tema: Audiobooks & retenção  
- Descobre grupos de usuários parecidos a partir do comportamento deles e verificar, com estatística, se esses grupos realmente são diferentes em pontos importantes do negócio.
- EDA não supervisionada -> padronização + PCA + K-Means.
- Escolha de `k` via silhouette.
- Testes de hipótese:
    - ANOVA/Kruskal —> Será que os grupos gastam valores diferentes?
    - Qui-quadrado —> Será que a taxa de cancelamento varia entre grupos?
- Outputs: resumo por cluster, gráficos PCA, relatórios de hipóteses.

## [BR Transparência](./br-transparencia-despesas-eda)
Tema: Despesas públicas brasileiras (estilo Portal da Transparência)
- Este projeto usa dados sintéticos inspirados no Portal da Transparência para mostrar como explorar e analisar despesas públicas de forma prática.
    A ideia é aprender a entender padrões de gasto, comparar estados e verificar se existem diferenças estatísticas reais.
- EDA: evolução de gastos por função (Saúde, Educação, Segurança).
- Clusters de UFs: gasto per capita por função (PCA + KMeans).
- Testes de hipótese:
  - t de Welch -> O gasto mensal em 2022 (ano eleitoral) foi diferente dos outros anos?
  - Qui-quadrado -> Existe relação entre a função de governo e a presença de licitação?
- Outputs: CSVs, gráficos (barras, PCA), relatório com p-valores.

## Stack utilizada
- Python: pandas, numpy, matplotlib, scikit-learn, scipy
- SQL (SQLite/MySQL nos exemplos)
- GitHub Actions para CI + smoke tests
- Todos os projetos têm `requirements.txt` e scripts em aprimoramento, mas com versões já funcionais.