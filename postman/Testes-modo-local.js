// Execute na aba Tests de GET http://127.0.0.1:8000/.
// Testes locais: cria dados ficticios, exclui o produto criado e revoga seus tokens.
// Nao requer conta Postman. Aguarde um minuto entre repeticoes frequentes.
const variables = {
  base_url: "http://127.0.0.1:8000",
  email: "postman.ui." + Date.now() + "." + Math.floor(Math.random()*100000) + "@example.com",
  password: "EstoqueTeste123!",
  token: "",
  product_id: ""
};
const cases = [
  {
    "name": "01 - Cadastrar usuario",
    "method": "POST",
    "url": "{{base_url}}/api/register",
    "body": "{\n  \"name\": \"Usuario demonstracao\",\n  \"email\": \"{{email}}\",\n  \"password\": \"{{password}}\",\n  \"password_confirmation\": \"{{password}}\"\n}",
    "auth": false,
    "status": 201,
    "saveToken": true
  },
  {
    "name": "02 - Consultar usuario autenticado",
    "method": "GET",
    "url": "{{base_url}}/api/me",
    "auth": true,
    "status": 200
  },
  {
    "name": "03 - Encerrar token do cadastro",
    "method": "POST",
    "url": "{{base_url}}/api/logout",
    "auth": true,
    "status": 200
  },
  {
    "name": "04 - Login",
    "method": "POST",
    "url": "{{base_url}}/api/login",
    "body": "{\n  \"email\": \"{{email}}\",\n  \"password\": \"{{password}}\"\n}",
    "auth": false,
    "status": 200,
    "saveToken": true
  },
  {
    "name": "05 - Criar produto",
    "method": "POST",
    "url": "{{base_url}}/api/products",
    "body": "{\n  \"name\": \"Teclado USB\",\n  \"description\": \"Produto de demonstracao\",\n  \"code\": \"DEMO-{{email}}\",\n  \"price\": 49.9,\n  \"quantity\": 10\n}",
    "auth": true,
    "status": 201,
    "check": [
      "data.quantity",
      10
    ],
    "saveProduct": true
  },
  {
    "name": "06 - Listar produtos",
    "method": "GET",
    "url": "{{base_url}}/api/products?per_page=15&page=1",
    "auth": true,
    "status": 200
  },
  {
    "name": "07 - Consultar produto",
    "method": "GET",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "auth": true,
    "status": 200
  },
  {
    "name": "08 - Atualizar produto PUT",
    "method": "PUT",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "body": "{\n  \"name\": \"Teclado atualizado\",\n  \"description\": \"USB ABNT2\",\n  \"price\": 59.9\n}",
    "auth": true,
    "status": 200
  },
  {
    "name": "09 - Atualizar produto PATCH",
    "method": "PATCH",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "body": "{\n  \"description\": \"Garantia de um ano\"\n}",
    "auth": true,
    "status": 200
  },
  {
    "name": "10 - Registrar entrada",
    "method": "POST",
    "url": "{{base_url}}/api/products/{{product_id}}/entries",
    "body": "{\n  \"quantity\": 5,\n  \"notes\": \"Compra do fornecedor\"\n}",
    "auth": true,
    "status": 201,
    "check": [
      "data.quantity_after",
      15
    ]
  },
  {
    "name": "11 - Registrar saida",
    "method": "POST",
    "url": "{{base_url}}/api/products/{{product_id}}/exits",
    "body": "{\n  \"quantity\": 3,\n  \"notes\": \"Venda\"\n}",
    "auth": true,
    "status": 201,
    "check": [
      "data.quantity_after",
      12
    ]
  },
  {
    "name": "12 - Consultar saldo",
    "method": "GET",
    "url": "{{base_url}}/api/products/{{product_id}}/stock",
    "auth": true,
    "status": 200,
    "check": [
      "data.quantity",
      12
    ]
  },
  {
    "name": "13 - Consultar historico",
    "method": "GET",
    "url": "{{base_url}}/api/products/{{product_id}}/movements",
    "auth": true,
    "status": 200,
    "check": [
      "meta.total",
      3
    ]
  },
  {
    "name": "14 - Rejeitar saida superior ao saldo",
    "method": "POST",
    "url": "{{base_url}}/api/products/{{product_id}}/exits",
    "body": "{\n  \"quantity\": 13\n}",
    "auth": true,
    "status": 422
  },
  {
    "name": "15 - Confirmar saldo preservado",
    "method": "GET",
    "url": "{{base_url}}/api/products/{{product_id}}/stock",
    "auth": true,
    "status": 200,
    "check": [
      "data.quantity",
      12
    ]
  },
  {
    "name": "16 - Rejeitar nome vazio",
    "method": "POST",
    "url": "{{base_url}}/api/products",
    "body": "{\n  \"name\": \"\",\n  \"price\": 10\n}",
    "auth": true,
    "status": 422
  },
  {
    "name": "17 - Rejeitar preco negativo",
    "method": "PATCH",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "body": "{\n  \"price\": -1\n}",
    "auth": true,
    "status": 422
  },
  {
    "name": "18 - Rejeitar quantidade zero",
    "method": "POST",
    "url": "{{base_url}}/api/products/{{product_id}}/entries",
    "body": "{\n  \"quantity\": 0\n}",
    "auth": true,
    "status": 422
  },
  {
    "name": "19 - Rejeitar alteracao direta do saldo",
    "method": "PATCH",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "body": "{\n  \"quantity\": 100\n}",
    "auth": true,
    "status": 422
  },
  {
    "name": "20 - Rejeitar acesso sem token",
    "method": "GET",
    "url": "{{base_url}}/api/products",
    "auth": false,
    "status": 401
  },
  {
    "name": "21 - Excluir produto",
    "method": "DELETE",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "auth": true,
    "status": 200
  },
  {
    "name": "22 - Confirmar produto excluido",
    "method": "GET",
    "url": "{{base_url}}/api/products/{{product_id}}",
    "auth": true,
    "status": 404
  },
  {
    "name": "23 - Logout",
    "method": "POST",
    "url": "{{base_url}}/api/logout",
    "auth": true,
    "status": 200
  },
  {
    "name": "24 - Confirmar revogacao do token",
    "method": "GET",
    "url": "{{base_url}}/api/me",
    "auth": true,
    "status": 401
  }
];
const expand = value => value.replace(/{{(\w+)}}/g, (_, key) => variables[key]);
const read = (data, path) => path.split(".").reduce((value, key) => value[key], data);
pm.test("API local responde HTTP 200", () => pm.response.to.have.status(200));
pm.test("API local responde JSON", () => pm.expect(pm.response.headers.get("Content-Type")).to.include("application/json"));
function run(index) {
  if (index === cases.length) {
    pm.test("Fluxo completo: 24 cenarios executados", () => pm.expect(index).to.eql(24));
    console.log("API de Estoque: 24 cenarios concluidos. Confira as 57 verificacoes em Test Results.");
    return;
  }
  const test = cases[index];
  const request = {method: test.method, url: expand(test.url), header: {"Accept": "application/json"}};
  if (test.auth) request.header.Authorization = "Bearer " + variables.token;
  if (test.body) {
    request.header["Content-Type"] = "application/json";
    request.body = {mode: "raw", raw: expand(test.body)};
  }
  pm.sendRequest(request, (error, response) => {
    pm.test(test.name + " | HTTP " + test.status, () => {
      pm.expect(error).to.equal(null);
      pm.expect(response.code).to.eql(test.status);
    });
    if (error || !response) return;
    let data;
    pm.test(test.name + " | JSON", () => {
      pm.expect(response.headers.get("Content-Type")).to.include("application/json");
      data = response.json();
    });
    if (response.code !== test.status || !data) return;
    if (test.saveToken) variables.token = data.token;
    if (test.saveProduct) variables.product_id = data.data.id;
    if (test.check) {
      pm.test(test.name + " | " + test.check[0] + " = " + test.check[1],
        () => pm.expect(read(data, test.check[0])).to.eql(test.check[1]));
    }
    run(index + 1);
  });
}
if (pm.response.code === 200) run(0);