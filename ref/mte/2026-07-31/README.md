# Referência Documental Oficial MTE — 31/07/2026

## 1. Identificação e Metadados do Congelamento

- **Órgão Oficial:** Ministério do Trabalho e Emprego (MTE)
- **Assunto:** Regulamentação de Registro Eletrônico de Ponto (REP-P / PTRP) — Portaria MTP nº 671/2021
- **Data de Referência Informada na Página Oficial:** 31/07/2026 (Atualizado em 31/07/2026 às 13h38)
- **Data Real do Download dos Arquivos:** 09/10/2026 (22:33 BRT / 10/10/2026 01:33 UTC)
- **Commit Base do PontoFácil no Congelamento:** `0eaf9437df914ec1db78b0c38d90c55d47a1e739`
- **Finalidade:** Referência documental imutável dentro do repositório para evitar ambiguidades e garantir rastreabilidade regulatória e auditoria.

---

## 2. Fontes Oficiais de Origem

Os arquivos foram obtidos diretamente do portal oficial do Governo Federal (`gov.br`), sem intermediários ou cópias de terceiros:

1. **Página Oficial de Referência REP:**  
   `https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/fiscalizacao-do-trabalho/rep`
2. **Leiaute Oficial AFD (Arquivo Fonte de Dados):**  
   `https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/fiscalizacao-do-trabalho/leiaute-do-arquivo-fonte-de-dados-afd.pdf`
3. **Leiaute Oficial AEJ (Arquivo Eletrônico de Jornada):**  
   `https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/fiscalizacao-do-trabalho/leiaute-do-arquivo-eletronico-de-jornada-aej.pdf`

---

## 3. Arquivos Locais e Hashes Criptográficos (SHA-256)

Os hashes foram calculados diretamente a partir dos binários preservados neste diretório:

| Arquivo Local | Tamanho | SHA-256 Checksum |
| :--- | :--- | :--- |
| `afd.pdf` | 205.070 bytes | `0e7d6c5a967c8e7b211b17e5930a25f26db4db3155a7ed36eac94561bf537cbd` |
| `aej.pdf` | 181.044 bytes | `e1ff56a62f01411a91020633ecf0f68f746f61cbcab55dd9a7d2ec99c71af5dc` |

O arquivo [`SHA256SUMS.txt`](file:///ref/mte/2026-07-31/SHA256SUMS.txt) consolida esses mesmos hashes no formato padrão de verificação de integridade.

---

## 4. Regra de Imutabilidade e Política de Atualizações Futuras

> **Aviso Explícito:**  
> O fato de o gov.br futuramente disponibilizar conteúdo diferente na mesma URL não altera automaticamente a referência deste commit. Qualquer atualização de leiaute deverá ser tratada em novo diretório versionado e novo commit.

- O conteúdo deste diretório (`ref/mte/2026-07-31/`) é estritamente **imutável** e **permanente**.
- Caso o Ministério do Trabalho e Emprego publique revisões ou novas versões de leiautes, estas deverão ser congeladas em um novo diretório com base na data oficial de referência (por exemplo, `ref/mte/2027-XX-XX/`), preservando intacto todo o histórico deste diretório.

---

## 5. Registro de Divergência Documental Observada

Durante a revisão técnica dos materiais oficiais disponibilizados pelo MTE, registrou-se de forma neutra que houve divergência entre representações/extratos do conteúdo oficial servido pelo gov.br em relação aos campos de versão dos leiautes:

- No Anexo VI da Portaria MTP nº 671/2021 e em extratos correlatos da norma, a versão do leiaute do AEJ (campo `versaoAej` no Registro 01) foi originalmente introduzida como `001`.
- No PDF de leiaute disponibilizado na página oficial em 31/07/2026 (`leiaute-do-arquivo-eletronico-de-jornada-aej.pdf`), a especificação do campo 10 do Registro 01 traz a instrução: *"Versão do leiaute do AEJ. Preencher com '002'"*.
- No leiaute do AFD (`leiaute-do-arquivo-fonte-de-dados-afd.pdf`), o campo 11 do Registro 1 prescreve o preenchimento com `"004"`.

**Diretriz Aplicada:**  
Nenhuma nova versão foi arbitrariamente escolhida na aplicação por conta dessa divergência documental externa. O PontoFácil mantém inalterados:
- `AfdGenerator_2026_07_31::LAYOUT_VERSION` (`004`);
- `AejGenerator_2026_07_31::LAYOUT_VERSION` (`001`);
- Validadores estruturais (`AfdValidator`, `AejValidator`);
- Fixtures congeladas de Golden Test (`golden_afd_mte_2026.txt`, `golden_aej_mte_2026.txt`).

O objetivo deste diretório e deste commit é exclusivamente o congelamento fiel dos binários oficiais recebidos para fins de auditoria documental.

---

## 6. Desacoplamento de Runtime

Estes arquivos PDF possuem finalidade unicamente de referência normativa, auditoria e preservação histórica:
- **Não são carregados pela aplicação em produção.**
- **Não são utilizados para gerar arquivos fiscais em tempo de execução.**
- **Não há parsers de PDF incorporados ao runtime do sistema.**
- **Nenhum download a partir do portal gov.br é executado durante o ciclo de vida do PontoFácil.**

A integridade do congelamento é auditada de forma autônoma e offline pelo teste unitário `tests/Unit/MteReferenceDocumentsIntegrityTest.php`.
