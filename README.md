# API de Controle de Estoque — Projeto 03

API REST em Laravel 12 com autenticação por tokens Laravel Sanctum. Implementa usuários, CRUD de produtos, entradas, saídas, saldo e histórico. Não depende de frontend ou Node.js.

## Instalação local

Requisitos: PHP 8.2 ou superior, Composer e as extensões exigidas pelo Composer, incluindo PDO SQLite. Ambiente validado: PHP 8.4.24. A implementação está na branch `develop`.

```sh
git clone https://github.com/M4theus1/Eletiva-III.git
cd Eletiva-III
git switch develop
composer run setup
php artisan serve
```

URL: `http://127.0.0.1:8000`.

O setup instala as dependências do lock, cria o `.env`, gera uma chave se estiver vazia, cria o banco SQLite e executa migrations. Preserva a chave existente e não apaga tabelas ou dados. Não é necessário executar npm.

Com dependências já instaladas, `php scripts/setup.php` prepara o ambiente. Banco padrão: `database/database.sqlite`. Banco e `.env` são ignorados pelo Git. O setup é destinado ao ambiente local: revise a conexão antes de aplicar migrations em outro ambiente.

O exemplo usa `APP_DEBUG=false`. Para MySQL, configure a conexão no `.env` antes das migrations. A validação automatizada usa SQLite.

## Autenticação

Envie `Content-Type: application/json` nos corpos JSON e `Authorization: Bearer <token>` nas rotas protegidas. `Accept: application/json` é recomendado; erros de `/api/*` também são JSON sem esse header.

| Método | Rota | Proteção | Descrição |
|---|---|---|---|
| POST | /api/register | Pública | Cadastro e emissão de token |
| POST | /api/login | Pública | Login e emissão de token |
| GET | /api/me | Sanctum | Usuário autenticado |
| POST | /api/logout | Sanctum | Revoga apenas o token usado |
| GET | /api/user | Sanctum | Alias de /api/me |

Cadastro:

```json
{
  "name": "Ana",
  "email": "ana@example.com",
  "password": "EstoqueTeste123!",
  "password_confirmation": "EstoqueTeste123!"
}
```

O login recebe apenas `email` e `password`. O cadastro retorna 201 e o login 200:

```json
{
  "data": {"id": 1, "name": "Ana", "email": "ana@example.com", "created_at": "..."},
  "token": "1|token-gerado-pelo-servidor",
  "token_type": "Bearer"
}
```

As senhas têm hash e nunca integram as respostas. Senha mínima: 8 caracteres. Credenciais incorretas retornam 401; campos inválidos, 422. Cadastro e login compartilham um limite de 10 requisições por minuto por IP; excesso retorna 429.

## Produtos

Os usuários autenticados compartilham o estoque da empresa. Não há segregação por empresa nem perfis de administrador.

| Método | Rota | Descrição |
|---|---|---|
| POST | /api/products | Cadastrar |
| GET | /api/products | Listar com paginação |
| GET | /api/products/{id} | Consultar |
| PUT ou PATCH | /api/products/{id} | Atualizar os campos informados |
| DELETE | /api/products/{id} | Excluir |

Exemplo de cadastro:

```json
{
  "name": "Teclado USB",
  "description": "ABNT2",
  "code": "TEC-001",
  "price": 49.90,
  "quantity": 10
}
```

| Campo | Validação |
|---|---|
| name | Obrigatório, até 255 caracteres |
| description | Opcional, até 5.000 caracteres |
| code | Opcional, único, até 100 caracteres |
| price | Obrigatório no cadastro, de 0 a 99.999.999,99, até duas casas decimais |
| quantity | Saldo inicial opcional, inteiro de 0 a 2.147.483.647; padrão 0 |

Use ponto decimal no JSON. O preço é retornado como string com duas casas, por exemplo `"49.90"`. Estoque inicial positivo gera uma entrada no histórico.

Atualizações preservam os campos omitidos. O nome não pode ficar vazio e o preço não pode ser negativo. Não envie `quantity` no PUT/PATCH: altere o saldo pelas rotas de entrada e saída.

A exclusão é lógica: o produto deixa de aparecer nas consultas e operações, mas o registro e seu histórico permanecem no banco. O código continua reservado. Não há endpoint de restauração nesta entrega.

Listagens aceitam `?page=1&per_page=15`, com limite de 100 itens por página. Respostas paginadas usam `data`, `links` e `meta`.

## Estoque

Todas as rotas abaixo exigem Sanctum.

| Método | Rota | Descrição |
|---|---|---|
| POST | /api/products/{id}/entries | Entrada |
| POST | /api/products/{id}/exits | Saída |
| GET | /api/products/{id}/stock | Saldo disponível |
| GET | /api/products/{id}/movements | Histórico paginado, mais recentes primeiro |

Corpo da entrada ou saída:

```json
{"quantity": 3, "notes": "Venda"}
```

Quantidade inteira positiva; observação opcional, até 1.000 caracteres. Retorna 201:

```json
{
  "data": {
    "id": 2,
    "product_id": 1,
    "user_id": 1,
    "type": "exit",
    "quantity": 3,
    "quantity_before": 10,
    "quantity_after": 7,
    "notes": "Venda",
    "created_at": "..."
  }
}
```

Saída acima do saldo retorna 422 sem alterar estoque nem histórico. Saída exatamente igual ao saldo é permitida. Entradas que ultrapassariam o limite máximo são rejeitadas.

A verificação e alteração do saldo usam um UPDATE condicional no banco. Saldo e histórico são persistidos na mesma transação: falha no histórico desfaz a alteração do saldo. A operação considera o saldo atual do banco, mesmo com instância do produto desatualizada em memória.

Consulta de saldo:

```json
{"data": {"product_id": 1, "quantity": 7}}
```

## Respostas e erros

Resources formatam usuários, produtos, saldos e movimentações. Exclusão e logout retornam 200 com mensagem JSON.

| Status | Significado |
|---|---|
| 200 | Consulta, atualização, exclusão ou logout concluído |
| 201 | Usuário, produto ou movimentação criado |
| 401 | Token ausente/inválido/revogado ou credenciais incorretas |
| 404 | Produto inexistente/excluído ou rota inexistente |
| 405 | Método HTTP não permitido |
| 422 | Validação ou regra de estoque não atendida |
| 429 | Limite de tentativas atingido |

Exemplo:

```json
{
  "message": "Estoque insuficiente para esta saida.",
  "errors": {"quantity": ["Estoque insuficiente para esta saida."]}
}
```

## Demonstração no Postman

Importe [a coleção](postman/Controle-de-Estoque.postman_collection.json), com 24 requisições e verificações de status, formato JSON, saldo e revogação.

1. Inicie a API e confirme a variável `base_url`.
2. Execute a coleção na ordem indicada no Collection Runner.
3. O cadastro gera um email exclusivo; scripts armazenam `token` e `product_id`.
4. A sequência demonstra autenticação, CRUD, saldo inicial 10, entrada de 5, saída de 3 e saldo final 12.
5. Também demonstra rejeição de saldo insuficiente, preço negativo, nome vazio, quantidade zero e acesso sem token.
6. Ao final, exclui o produto de demonstração e revoga o token. Usuário e histórico permanecem no banco.

Ao repetir rapidamente, aguarde um minuto se receber 429. Trate tokens como senhas e não exporte uma coleção contendo tokens reais preenchidos.

## Testes e estilo

```sh
composer test
composer run lint
```

A suíte usa SQLite em memória e chave exclusiva de teste no phpunit.xml. Não depende do banco local e não deve ser apontada para um banco real.

Cobre cadastro, hash de senha, login, revogação do token atual, proteção de todas as rotas, CRUD, paginação, Resources, validações, exclusão lógica, estoque inicial, entradas/saídas, saldo insuficiente, limites numéricos, rollback quando o histórico falha e instâncias desatualizadas. Também verifica erros JSON para clientes que pedem HTML.

## Estrutura e requisitos acadêmicos

- Migrations: `database/migrations` — usuários, tokens, produtos e movimentações.
- Models: `User`, `Product` e `StockMovement`.
- Controllers: `AuthController`, `ProductController` e `StockController`.
- Form Requests: `app/Http/Requests`.
- API Resources: `app/Http/Resources`.
- Regras transacionais: `app/Services/StockService.php`.
- Rotas protegidas: `routes/api.php`.
- Exemplos: `postman/Controle-de-Estoque.postman_collection.json`.
- Testes funcionais: `tests/Feature/InventoryApiTest.php`.
## Atualização de instalações anteriores

Após atualizar a branch develop, execute `php artisan migrate`. A integração de 17/11/2026 preserva os bancos criados pelas duas versões anteriores do projeto: mantém produtos e movimentos, acrescenta exclusão lógica quando necessário, normaliza os tipos para `entry`/`exit` e preenche `quantity_before` a partir do histórico existente.

As migrations de criação de 12/11 permanecem como marcadores históricos para bancos que já as executaram. A criação das tabelas é centralizada nas migrations de 11/11; a migration `2026_11_17_000001_reconcile_inventory_schemas` concilia as estruturas. Essa migration de compatibilidade é de avanço: seu rollback não remove campos nem reinterpreta dados históricos. Faça backup antes de atualizar um banco com dados importantes.

A suíte inclui testes de atualização com os schemas originais das duas branches, além dos testes da API. No total: 49 testes e 313 asserções aprovados no SQLite.
