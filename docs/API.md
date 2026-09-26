# API REST — Guia Completo

Documentação da camada de API do PHP MVC: `/api/v1`, autenticação por Bearer
Token, consumo de APIs externas e integrações (pagamento, CEP, etc.).

A API é **stateless** (não usa sessão/cookie) e vive completamente separada
das rotas Web em `routes/web.php` — mesmos Services/Repositories/Models por
baixo, interface própria por cima.

---

## Índice

1. [Como criar uma API](#1-como-criar-uma-api)
2. [Como criar um Controller de API](#2-como-criar-um-controller-de-api)
3. [Como criar uma Resource](#3-como-criar-uma-resource)
4. [Como criar um Request de API](#4-como-criar-um-request-de-api)
5. [Como autenticar](#5-como-autenticar)
6. [Como utilizar Bearer Token](#6-como-utilizar-bearer-token)
7. [Como consumir a API própria](#7-como-consumir-a-api-própria)
8. [Como consumir APIs externas](#8-como-consumir-apis-externas)
9. [Como criar uma integração](#9-como-criar-uma-integração)
10. [Como criar um Gateway de pagamento](#10-como-criar-um-gateway-de-pagamento)
11. [Como configurar o `.env`](#11-como-configurar-o-env)
12. [Como tratar erros](#12-como-tratar-erros)
13. [Como utilizar versionamento](#13-como-utilizar-versionamento)
14. [Exemplos de requisições](#14-exemplos-de-requisições)
15. [Exemplos de respostas JSON](#15-exemplos-de-respostas-json)
16. [CORS](#16-cors)
17. [Rate limiting](#17-rate-limiting)
18. [Segurança](#18-segurança)

---

## 1. Como criar uma API

Toda rota de API vive em `routes/api.php`, dentro de um grupo versionado:

```php
$router->group(['prefix' => '/api/v1', 'middleware' => ['CorsMiddleware', 'RateLimitMiddleware:api']], function (Router $r) {
    $r->get('/produtos', [ProdutoApiController::class, 'index'], ['ApiAuthMiddleware']);
});
```

`Core\Application` carrega **automaticamente** qualquer arquivo
`routes/api*.php` — não é preciso tocar em `Core\Application` para adicionar
rotas novas, só editar `routes/api.php` (ou criar `routes/api_v2.php` no
futuro — ver [seção 13](#13-como-utilizar-versionamento)).

## 2. Como criar um Controller de API

```bash
php mvc make:api-controller ProdutoApiController
```

Gera `app/Api/Controllers/ProdutoApiController.php` já estendendo
`App\Api\Controllers\ApiController` (que por sua vez estende
`Core\Controller` — o mesmo Controller base do MVC Web) com `index`, `show`,
`store`, `update`, `destroy` prontos para conectar a um Repository existente.

Regra: Controllers de API são **finos**, igual aos da Web — nenhuma lógica de
negócio aqui. Delegue para Service/Repository.

## 3. Como criar uma Resource

Um Model nunca deve virar JSON diretamente (`json_encode($model)`) — isso
vazaria qualquer coluna nova do banco, incluindo campos sensíveis. Uma
Resource declara explicitamente o que é exposto:

```bash
php mvc make:api-resource ProdutoResource
```

```php
class ProdutoResource extends ApiResource
{
    public function toArray(): array
    {
        return [
            'id'    => (int) $this->resource->id,
            'nome'  => $this->resource->nome,
            'preco' => (float) $this->resource->preco,
        ];
    }
}
```

Uso no Controller:

```php
ProdutoResource::make($produto);        // um registro → array
ProdutoResource::collection($produtos); // vários → array de arrays
```

## 4. Como criar um Request de API

Se já existe um `FormRequest` equivalente em `app/Requests/` para o
formulário Web (mesmas regras de validação), **reaproveite-o** — é
exatamente o que `AuthApiController::login()` faz com
`App\Requests\Auth\LoginRequest`, sem duplicar nada. `FormRequest` já
detecta corpo JSON automaticamente (`Content-Type: application/json`).

Quando as regras realmente divergem da Web, gere um Request específico:

```bash
php mvc make:api-request StoreProdutoRequest
```

Isso cria a classe em `app/Api/Requests/` com `authorize()` já usando
`Core\Api\ApiAuthContext::check()` (Bearer Token) em vez de `Core\Auth`
(sessão).

No Controller, use o helper `validated()` de `ApiController` — ele já
responde 422 com o mapa de erros e encerra a requisição se a validação
falhar:

```php
$data = $this->validated(new StoreProdutoRequest());
```

## 5. Como autenticar

`POST /api/v1/auth/login` com `email` + `password` retorna um token de
acesso pessoal (hash SHA-256 persistido em `api_tokens`, valor em texto puro
devolvido **uma única vez**, no login).

```
POST /api/v1/auth/login
Content-Type: application/json

{"email": "user@example.com", "password": "senha123"}
```

`POST /api/v1/auth/logout` (autenticado) revoga o token atual.
`GET /api/v1/auth/me` (autenticado) retorna o usuário logado.

## 6. Como utilizar Bearer Token

Envie o token recebido no login em todas as requisições autenticadas:

```
Authorization: Bearer 8f2c9e1a4b7d...
```

`App\Middlewares\ApiAuthMiddleware` valida o token a cada requisição
(stateless — sem sessão/cookie) e popula `Core\Api\ApiAuthContext`:

```php
use Core\Api\ApiAuthContext;

ApiAuthContext::check(); // bool
ApiAuthContext::user();  // objeto do usuário autenticado
ApiAuthContext::id();    // int
```

## 7. Como consumir a API própria

```bash
curl -X GET https://seusite.com/api/v1/users \
  -H "Authorization: Bearer 8f2c9e1a4b7d..." \
  -H "Accept: application/json"
```

## 8. Como consumir APIs externas

Nunca use `curl_*()` direto num Service — use `Core\Api\ApiClient`:

```php
use Core\Api\ApiClient;

$client = new ApiClient('https://api.exemplo.com', timeout: 10);

$res = $client->get('/recurso/123', ['token' => $accessToken]);
if ($res['ok']) {
    $dados = $res['json'];
}

$res = $client->post('/recursos', ['json' => ['nome' => 'Teste'], 'token' => $accessToken]);
```

Por padrão, status HTTP de erro (4xx/5xx) ou falha de conexão lançam
`Core\Api\ApiException` — capture no Service, nunca deixe subir crua até o
Controller. Passe `'throw_on_error' => false` para tratar manualmente via
`$res['ok']`.

## 9. Como criar uma integração

Padrão de referência real, já implementado: `Services/Integrations/Cep/`.

```
Service  →  Integration (Gateway)  →  Core\Api\ApiClient  →  API externa
```

1. Defina o contrato: `app/Services/Integrations/Nome/NomeGatewayInterface.php`
2. Implemente com `ApiClient`: `app/Services/Integrations/Nome/Gateways/ProvedorGateway.php`
3. Crie a fachada: `app/Services/Integrations/Nome/NomeService.php`, que resolve
   o gateway configurado a partir de `config/api.php`.

Exemplo funcional (CEP via ViaCEP):

```php
use App\Services\Integrations\Cep\CepService;

$endereco = (new CepService())->buscar('01001-000');
// ['cep' => '01001-000', 'logradouro' => '...', 'bairro' => '...', 'cidade' => '...', 'uf' => 'SP']
// ou null se não encontrado
```

`Services/Integrations/Maps/` está preparado (diretório criado) para a
próxima integração seguindo o mesmo padrão.

## 10. Como criar um Gateway de pagamento

`app/Services/Integrations/Payment/PaymentGatewayInterface.php` define o
contrato (`createPayment`, `getPayment`, `cancelPayment`).
`NullPaymentGateway` é o padrão seguro quando `PAYMENT_PROVIDER=null` —
nenhuma chamada externa, nenhuma cobrança real por engano.

Para adicionar Mercado Pago/Asaas/Stripe/PagSeguro:

1. Crie `app/Services/Integrations/Payment/Gateways/MercadoPagoGateway.php`
   implementando `PaymentGatewayInterface`, usando `ApiClient` internamente.
2. Adicione o `case` em `PaymentService::resolveGateway()`.
3. Adicione as credenciais em `config/api.php` (`payment.mercadopago`) e no
   `.env`.

Uso (independente do gateway escolhido):

```php
use App\Services\Integrations\Payment\PaymentService;

$resultado = (new PaymentService())->charge(['amount' => 100.00, 'description' => 'Pedido #42']);
```

## 11. Como configurar o `.env`

```env
API_TIMEOUT=30
CORS_ALLOWED_ORIGINS=https://app.seusite.com,https://admin.seusite.com

CEP_PROVIDER=viacep

PAYMENT_PROVIDER=null
MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_PUBLIC_KEY=
ASAAS_API_KEY=
ASAAS_ENVIRONMENT=sandbox
```

Nunca commitar credenciais reais — apenas `.env.example` com os placeholders
vai para o Git (já garantido pelo `.gitignore` existente do projeto).

## 12. Como tratar erros

Todo erro da API responde no mesmo envelope (ver
[seção 15](#15-exemplos-de-respostas-json)), com o status HTTP correto:

| Status | Quando |
|--------|--------|
| 400 | Requisição malformada |
| 401 | Não autenticado / token inválido ou expirado |
| 403 | Autenticado mas sem permissão (`FormRequest::authorize()` retornou `false`) |
| 404 | Recurso não encontrado |
| 422 | Falha de validação (`ApiController::validated()`) |
| 429 | Rate limit excedido |
| 500 | Erro interno — nunca expõe stack trace em produção (`APP_DEBUG=false`) |

Em produção, mensagens internas nunca vazam para o cliente — o
`Core\Logger` registra o detalhe, a resposta ao cliente é sempre uma
mensagem segura e genérica quando aplicável.

## 13. Como utilizar versionamento

A v1 vive inteira em `routes/api.php`, sob o prefixo `/api/v1`. Quando for
necessária uma v2 **sem quebrar a v1**:

1. Crie `routes/api_v2.php` com seu próprio `$router->group(['prefix' => '/api/v2', ...], ...)`.
2. Nada mais — `Core\Application::run()` já carrega qualquer
   `routes/api*.php` automaticamente via `glob()`.

Controllers/Resources da v2 podem viver em `app/Api/V2/...` se divergirem
muito da v1, ou reaproveitar os mesmos da v1 quando o contrato não mudou.

## 14. Exemplos de requisições

**Login:**
```http
POST /api/v1/auth/login HTTP/1.1
Content-Type: application/json

{"email": "user@example.com", "password": "senha123"}
```

**Listar usuários (paginado, autenticado):**
```http
GET /api/v1/users?page=2&per_page=20 HTTP/1.1
Authorization: Bearer 8f2c9e1a4b7d...
```

**Logout:**
```http
POST /api/v1/auth/logout HTTP/1.1
Authorization: Bearer 8f2c9e1a4b7d...
```

## 15. Exemplos de respostas JSON

**Sucesso (registro único):**
```json
{
    "success": true,
    "message": "Login realizado com sucesso.",
    "data": {
        "token": "8f2c9e1a4b7d...",
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "nome": "Maria",
            "email": "maria@exemplo.com",
            "perfil": "admin",
            "ativo": true,
            "created_at": "2026-09-21 10:00:00"
        }
    }
}
```

**Sucesso (lista paginada):**
```json
{
    "success": true,
    "message": "Dados encontrados.",
    "data": [
        {"id": 1, "nome": "Maria", "email": "maria@exemplo.com", "perfil": "admin", "ativo": true, "created_at": "2026-09-21 10:00:00"}
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

**Erro de validação:**
```json
{
    "success": false,
    "message": "Os dados enviados são inválidos.",
    "errors": {
        "email": ["O campo Email deve ser um e-mail válido."]
    }
}
```

**Erro simples:**
```json
{
    "success": false,
    "message": "Token inválido ou expirado."
}
```

## 16. CORS

Configurado em `config/api.php` (lendo `CORS_ALLOWED_ORIGINS` do `.env`) e
aplicado por `App\Middlewares\CorsMiddleware` a todo o grupo `/api/v1`.
Requisições `OPTIONS` (preflight) são respondidas com `204` sem chegar ao
Controller. Use `*` apenas em desenvolvimento — em produção, liste as
origens explicitamente.

## 17. Rate limiting

`App\Middlewares\RateLimitMiddleware` (já existente no framework, reutilizado
sem duplicação) aplica dois perfis à API:

- `api` (grupo inteiro): 60 requisições/minuto por IP.
- `login` (`POST /api/v1/auth/login`): 5 tentativas/15 min — mesma proteção
  contra força bruta já usada no login Web.

## 18. Segurança

- Tokens de API nunca são armazenados em texto puro (hash SHA-256, mesma
  técnica do cookie "lembrar de mim" existente).
- `Authorization` e corpo de requisições externas nunca são logados pelo
  `ApiClient` (só método, URL, status e tempo de resposta).
- `Validator::MIME_MAP` já protege uploads contra extensão forjada
  (reutilizado sem alteração).
- CORS nunca libera `*` para endpoints autenticados por padrão — depende de
  configuração explícita.
- `CsrfMiddleware` (baseado em sessão) **não** se aplica à API — a proteção
  equivalente contra requisições forjadas é o próprio Bearer Token
  (stateless, não enviado automaticamente pelo navegador como um cookie
  seria).
- Erros internos nunca vazam detalhes em produção (`APP_DEBUG=false`
  força isso em toda a aplicação, Web e API).
