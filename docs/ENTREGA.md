# Entrega — API de Controle de Estoque

Implementação local concluída em 11/11/2026, na branch develop, em C:\dev\Projetos\Eletiva-III.

Parecer: os requisitos funcionais e técnicos do Projeto 03 foram implementados e validados no ambiente local com SQLite. Esta conclusão substitui o parecer de incompletude referente ao commit original 58c68e2. As alterações ainda não foram publicadas no GitHub.

## Requisitos funcionais

| Requisito | Implementação |
|---|---|
| Cadastro e autenticação de usuários | POST /api/register, POST /api/login, GET /api/me, POST /api/logout |
| Cadastro de produtos | POST /api/products |
| Listagem dos produtos | GET /api/products |
| Consulta individual | GET /api/products/{id} |
| Atualização | PUT/PATCH /api/products/{id} |
| Exclusão | DELETE /api/products/{id}, com exclusão lógica |
| Entrada no estoque | POST /api/products/{id}/entries |
| Saída do estoque | POST /api/products/{id}/exits |
| Quantidade atual disponível | GET /api/products/{id}/stock; também no Resource do produto |

## Regras obrigatórias

- Nome obrigatório no cadastro; atualizações não podem esvaziá-lo.
- Preço nunca negativo; até duas casas decimais.
- Saída maior que o saldo retorna 422 e não grava movimentação.
- Entradas e saídas atualizam o saldo e registram responsável e quantidade resultante.
- Alteração do saldo e registro do histórico ocorrem na mesma transação.
- UPDATE condicional evita usar saldo desatualizado para autorizar uma saída.
- Form Requests rejeitam quantidades zero, negativas, fracionárias e acima do limite.
- Todas as operações de gerenciamento exigem token Sanctum.

## Entregáveis técnicos

| Entregável | Localização |
|---|---|
| Código-fonte da API | app, routes, bootstrap e config |
| Migrations | database/migrations, incluindo products e stock_movements |
| Models | User, Product, StockMovement |
| Controllers | AuthController, ProductController, StockController |
| Form Requests | app/Http/Requests |
| API Resources | app/Http/Resources |
| Autenticação Sanctum | User com HasApiTokens; rotas protegidas por auth:sanctum |
| Coleção Postman | postman/Controle-de-Estoque.postman_collection.json |
| Documentação e demonstração | README.md |
| Testes | tests/Feature/InventoryApiTest.php |

## Validação executada

- Composer: configuração válida e dependências do lock instaladas.
- Seis migrations aplicadas com sucesso no banco SQLite local.
- composer test: **47 testes aprovados e 281 asserções**.
- composer run lint: **aprovado** pelo Laravel Pint.
- git diff --check: sem erros.
- Coleção executada com Newman por HTTP real: **24 requisições, 54 asserções, zero falhas**.
- Verificados cadastro, login, usuário autenticado, revogação do token, CRUD completo, histórico, saldo, falhas de validação e acesso sem autenticação.
- A demonstração partiu de saldo 10, adicionou 5, retirou 3 e confirmou saldo 12. A tentativa de retirar 13 foi rejeitada e o saldo continuou 12.
- Teste de falha simulada ao inserir histórico confirmou rollback da alteração de estoque.
- Teste com duas instâncias desatualizadas confirmou que a segunda saída não consegue consumir estoque já utilizado.
- Erros 401, 404, 405 e 422 foram verificados como JSON, inclusive com Accept não JSON.

Os 47 testes incluem os dois testes de exemplo existentes. A coleção valida o fluxo completo adicionalmente pela rede local. Não foi realizado teste de carga concorrente nem validação em MySQL ou ambiente de produção.

## Uso local

O .env e o banco local foram preparados. O servidor iniciado durante a validação atende em http://127.0.0.1:8000, enquanto seu processo estiver ativo.

Para iniciar novamente:

```powershell
cd C:\dev\Projetos\Eletiva-III
php artisan serve
```

Em uma instalação nova, execute primeiro composer run setup. O setup preserva a chave existente e não apaga os dados.

Importe a coleção no Postman e execute na ordem indicada. Ela gera um usuário de demonstração, realiza as operações e termina excluindo logicamente o produto e revogando o token. O usuário e o histórico de teste permanecem no banco local.

## Decisões de escopo

- Estoque compartilhado por todos os usuários autenticados da pequena empresa.
- Produto excluído deixa de ser acessível pela API, mas mantém o histórico no banco e reserva seu código.
- Saldo inicial positivo registra uma entrada; alterações posteriores usam exclusivamente as rotas de movimentação.
- Quantidades inteiras; sem unidades fracionárias nesta proposta.
- Nenhuma interface gráfica é necessária.
- Código entregue localmente na develop. A publicação no GitHub e o merge na main não fazem parte desta entrega local.