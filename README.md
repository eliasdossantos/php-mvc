# PHP MVC Boilerplate

> Boilerplate profissional em PHP puro com arquitetura MVC moderna, escalável e reutilizável.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--3.0-red)](LICENSE)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue)](#)

---

## Sobre o Projeto

O **PHP MVC Boilerplate** é uma base reutilizável para desenvolvimento de aplicações web em PHP puro, construída com foco em organização, escalabilidade, segurança e boas práticas de desenvolvimento.

Inspirado nos conceitos utilizados por frameworks modernos, o projeto fornece uma estrutura sólida baseada no padrão **Model-View-Controller (MVC)**, permitindo que o desenvolvedor foque nas regras de negócio sem precisar recriar a infraestrutura básica a cada novo sistema.

O objetivo é oferecer uma alternativa leve e flexível para quem deseja utilizar PHP puro sem abrir mão de recursos essenciais encontrados nos principais frameworks do mercado.

---

## Principais Recursos

### Arquitetura MVC

- Estrutura MVC completa
- Separação clara de responsabilidades
- Organização modular e escalável
- Fácil manutenção e evolução

### Sistema de Rotas

- Rotas nomeadas
- Parâmetros dinâmicos
- Grupos de rotas
- Middlewares por rota
- Redirecionamentos

### Autenticação

- Login
- Logout
- Registro de usuários
- Recuperação de senha
- Controle de acesso por perfis
- Proteção de rotas

### Validação

- Mais de 20 regras de validação
- Mensagens personalizadas
- Form Requests
- Redirecionamento automático
- Preservação de dados enviados

### Banco de Dados

- PDO
- Query Builder
- Prepared Statements
- Transações
- Migrations (schema definido em PHP, com rollback)
- Seeders

### Upload de Arquivos

- Upload seguro
- Validação por MIME real
- Renomeação automática
- Organização por diretórios

### E-mails

- Integração com PHPMailer
- Templates reutilizáveis
- Configuração via ambiente

### Logs

- Logs estruturados
- Compatível com conceitos PSR-3
- Registro de erros e eventos

### CLI

Comandos para geração rápida de código:

- Controllers
- Models
- Requests
- Services
- Repositories
- Views
- Migrations
- Seeders
- Key generation (`php mvc key:generate`)

---

## Segurança

O projeto possui mecanismos nativos para mitigação das vulnerabilidades mais comuns:

- Proteção CSRF, com validação reforçada de token (rejeita token malformado antes de chegar à sessão)
- Prevenção contra SQL Injection
- Escape automático contra XSS
- Sessões seguras
- Security Headers
- Rate Limiting (proteção contra força bruta), com trava contra condição de corrida em requisições concorrentes
- Prepared Statements obrigatórios
- Controle de acesso por middleware

---

## Documentação

A documentação completa está disponível em:

```text
docs/index.html
```

`docs/index.html` é a fonte central da documentação, incluindo a referência completa da API v1. O antigo `docs/API.md` foi mantido temporariamente durante a migração e não deve ser usado como fonte independente.

Abra o arquivo em seu navegador para acessar:

- Guias de instalação
- Exemplos práticos
- Referência da API
- Estrutura do framework
- Criação de módulos

---

## Instalação

### Via Composer

```bash
composer create-project elias-antonio/php-mvc
```

### Instalação Manual

```bash
git clone https://github.com/eliasdossantos/php-mvc.git

cd php-mvc

composer install
```

---

## Configuração

Copie o arquivo de ambiente:

```bash
cp .env.example .env
```

Configure:

```env
APP_NAME=PHP MVC
APP_ENV=local

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=php_mvc
DB_USERNAME=root
DB_PASSWORD=
```

---

## Banco de Dados — Migrations (Criar e Aplicar)

Migrations são classes PHP versionadas, ficam em `database/migrations/` e definem o schema do banco de forma fluente, através de `Schema` e `Blueprint` — nada de SQL cru para criar tabelas.

Crie uma nova migration com o comando generator:

```bash
php mvc make:migration create_posts_table
php mvc make:migration add_email_to_users
```

O comando gera um arquivo com timestamp no nome (ex.: `2026_09_21_100000_create_posts_table.php`), já com a classe base pronta para você implementar `up()` (o que a migration faz) e `down()` (como desfazer):

```php
<?php

use App\Core\Migration;
use App\Core\Schema;
use App\Core\Blueprint;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('conteudo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('posts');
    }
}
```

Depois de editar sua migration, aplique as pendentes com:

```bash
php mvc migrate
```

Para desfazer o último lote de migrations aplicado (chama `down()` na ordem inversa):

```bash
php mvc migrate:rollback
```

Se quiser recriar tudo do zero (desfaz todas as migrations e aplica novamente):

```bash
php mvc migrate --fresh
```

Dica rápida:

- Os arquivos de migration ficam em `database/migrations/` e são executados em ordem crescente pelo timestamp do nome.
- Use `Schema::table('nome', function (Blueprint $table) { ... })` para alterar uma tabela existente, em vez de `Schema::create()`.
- Prefira sempre implementar `down()` corretamente — é o que garante que `migrate:rollback` e `migrate --fresh` funcionem de verdade.

---

## Banco de Dados — Seeders

Os Seeders permitem popular o banco de dados com dados iniciais ou de teste de forma automatizada.

São úteis para criar usuários padrão, permissões, configurações iniciais e dados necessários para o funcionamento da aplicação.

### Executar todos os Seeders

Para executar todos os seeders registrados no projeto:

```bash
php mvc seed:run
```

O framework localizará e executará automaticamente todos os seeders disponíveis.

### Executar um Seeder Específico

Caso deseje executar apenas um seeder específico:

```bash
php mvc seed:run UserSeeder
```

Neste caso, somente o seeder informado será executado.

### Dicas Rápidas

- Os seeders ficam localizados em `database/seeds/`.
- Utilize seeders para criar dados iniciais da aplicação.
- É possível executar todos os seeders ou apenas um seeder específico.
- Os seeders são úteis para ambientes de desenvolvimento, testes e homologação.
- Recomenda-se executar as migrations antes dos seeders.

Fluxo recomendado:

```bash
php mvc migrate
php mvc seed:run
```

Ou para recriar completamente o banco de dados:

```bash
php mvc migrate --fresh
php mvc seed:run
```

---

## Executando o Projeto

Servidor local:

```bash
php mvc serve
```

Servidor em porta específica:

```bash
php mvc serve --port=8080
```

---

## Comandos Disponíveis

### Geradores

```bash
php mvc make:controller UserController

php mvc make:model User

php mvc make:request StoreUserRequest

php mvc make:service UserService

php mvc make:repository UserRepository

php mvc make:migration create_posts_table

php mvc make:seed UserSeeder

php mvc make:view users
```

### Banco de Dados

```bash
php mvc migrate

php mvc migrate:rollback

php mvc migrate --fresh
```

### Configuração

```bash
php mvc key:generate
```

Gera ou atualiza a variável `APP_KEY` no arquivo `.env` com uma chave segura de 32 caracteres.

### Informações

```bash
php mvc list

php mvc help
```

---

## Estrutura do Projeto

```text
php-mvc/
├── .github/
│   └── workflows/
│       └── ci.yml
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── BaseController.php
│   │   ├── DashboardController.php
│   │   └── HomeController.php
│   ├── Core/
│   │   ├── Interfaces/
│   │   │   └── RepositoryInterface.php
│   │   ├── Application.php
│   │   ├── Auth.php
│   │   ├── Blueprint.php
│   │   ├── Column.php
│   │   ├── Controller.php
│   │   ├── Database.php
│   │   ├── Logger.php
│   │   ├── Migration.php
│   │   ├── Model.php
│   │   ├── Repository.php
│   │   ├── Request.php
│   │   ├── Router.php
│   │   ├── Schema.php
│   │   ├── Service.php
│   │   ├── Session.php
│   │   ├── Upload.php
│   │   ├── Validator.php
│   │   └── View.php
│   ├── Helpers/
│   │   ├── Mailer.php
│   │   ├── SecurityHelper.php
│   │   ├── ViewHelper.php
│   │   └── functions.php
│   ├── Middlewares/
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── DevelopmentMiddleware.php
│   │   ├── GuestMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   └── SecurityHeadersMiddleware.php
│   ├── Models/
│   │   ├── RedefinicaoSenha.php
│   │   └── User.php
│   ├── Repositories/
│   │   └── UserRepository.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── RegisterRequest.php
│   │   │   └── ResetPasswordRequest.php
│   │   ├── Users/
│   │   │   ├── StoreUserRequest.php
│   │   │   └── UpdateUserRequest.php
│   │   └── FormRequest.php
│   ├── Services/
│   │   └── AuthService.php
│   └── Views/
│       ├── auth/
│       │   ├── forgot.php
│       │   ├── login.php
│       │   ├── register.php
│       │   └── reset.php
│       ├── components/
│       │   ├── alerts.php
│       │   ├── footer.php
│       │   ├── pagination.php
│       │   ├── sidebar.php
│       │   └── topbar.php
│       ├── dashboard/
│       │   └── index.php
│       ├── errors/
│       │   ├── 404.php
│       │   ├── debug.php
│       │   └── generic.php
│       ├── home/
│       │   └── index.php
│       └── layouts/
│           ├── auth.php
│           ├── home.php
│           └── main.php
├── bootstrap/
│   └── app.php
├── cli/
│   ├── Commands/
│   │   ├── KeyGenerateCommand.php
│   │   ├── MakeControllerCommand.php
│   │   ├── MakeMigrationCommand.php
│   │   ├── MakeModelCommand.php
│   │   ├── MakeRepositoryCommand.php
│   │   ├── MakeRequestCommand.php
│   │   ├── MakeSeedCommand.php
│   │   ├── MakeServiceCommand.php
│   │   ├── MakeViewCommand.php
│   │   ├── MigrateCommand.php
│   │   ├── MigrateRollbackCommand.php
│   │   ├── SeedRunCommand.php
│   │   └── ServeCommand.php
│   ├── Stubs/
│   │   ├── controller.stub
│   │   ├── migration.stub
│   │   ├── model.stub
│   │   ├── repository.stub
│   │   ├── request.stub
│   │   ├── seed.stub
│   │   ├── service.stub
│   │   ├── view.create.stub
│   │   ├── view.edit.stub
│   │   ├── view.index.stub
│   │   └── view.show.stub
│   ├── Command.php
│   ├── Kernel.php
│   ├── Migrator.php
│   └── Output.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── mail.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_100000_create_users_table.php
│   │   ├── 0001_01_01_100001_create_redefinicoes_senha_table.php
│   │   ├── 0001_01_01_100002_add_remember_token_expires_at_to_users_table.php
│   │   └── 0001_01_01_100003_create_api_tokens_table.php
│   └── seeds/
│       └── UserSeeder.php
├── docs/
│   ├── assets/
│   │   ├── css/
│   │   │   └── styles.css
│   │   └── js/
│   │       └── app.js
│   └── index.html
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── app.css
│   │   └── js/
│   │       └── app.js
│   ├── .htaccess
│   └── index.php
├── routes/
│   └── web.php
├── storage/
│   ├── logs/
│   ├── sessions/
│   └── uploads/
├── tests/
│   ├── Feature/
│   │   └── .gitkeep
│   └── Unit/
│       └── .gitkeep
├── .env.example
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── composer.json
├── mvc
└── phpunit.xml
```

---

## Classes Principais

| Classe      | Responsabilidade                                 |
| ----------- | ------------------------------------------------ |
| Application | Inicialização da aplicação                       |
| Router      | Gerenciamento de rotas                           |
| Controller  | Classe base dos controllers                      |
| Model       | Classe base dos models                           |
| Database    | Conexão PDO                                      |
| Schema      | Ponto de entrada para criar/alterar tabelas      |
| Blueprint   | Definição fluente de colunas de uma tabela       |
| Migrator    | Execução, versionamento e rollback de migrations |
| Session     | Gerenciamento de sessões                         |
| Auth        | Autenticação                                     |
| Validator   | Validação de dados                               |
| Upload      | Upload de arquivos                               |
| Logger      | Sistema de logs                                  |
| Request     | Manipulação de requisições                       |
| Service     | Regras de negócio                                |
| Repository  | Camada de acesso a dados                         |

---

## Requisitos

- PHP 8.1 ou superior
- Composer 2+
- MySQL 5.7+ ou MariaDB 10+
- Extensão PDO
- Extensão OpenSSL
- Extensão Mbstring
- Extensão JSON

---

## Testes

Execute:

```bash
composer test
```

---

## Contribuições

Contribuições são bem-vindas.

Caso deseje contribuir:

1. Faça um Fork;
2. Crie uma branch;
3. Faça suas alterações;
4. Envie um Pull Request.

Leia também:

```text
CONTRIBUTING.md
```

Ao contribuir, você concorda que sua contribuição será licenciada sob os termos da GPL-3.0.

---

## Versões e Atualizações

> [!IMPORTANT]
> **Consulte as [Releases](https://github.com/eliasdossantos/php-mvc/releases) antes de atualizar.**
> Cada versão pode incluir correções de segurança, novas funcionalidades ou migrações de banco de dados que precisam ser executadas manualmente. Atualizar sem ler o release correspondente pode causar comportamentos inesperados.

As releases seguem o padrão [Semantic Versioning](https://semver.org/lang/pt-BR/) (`MAJOR.MINOR.PATCH`):

- **PATCH** — correções de bugs e ajustes internos sem impacto na API.
- **MINOR** — novas funcionalidades retrocompatíveis (ex.: novos middlewares, recursos de segurança).
- **MAJOR** — mudanças que quebram compatibilidade com versões anteriores.

Para o histórico detalhado de cada versão, consulte também:

```text
CHANGELOG.md
```

---

## Licença

Copyright (C) 2026 Elias dos Santos

Este projeto está licenciado sob os termos da **GNU General Public License v3.0 (GPL-3.0)**.

Você tem liberdade para:

- Utilizar o software;
- Estudar o código-fonte;
- Modificar o projeto;
- Distribuir cópias;
- Distribuir versões modificadas.

Desde que:

- Preserve os avisos de copyright;
- Mantenha a licença GPL-3.0;
- Disponibilize o código-fonte correspondente ao distribuir versões modificadas.

Este software é fornecido **"COMO ESTÁ"**, sem qualquer garantia expressa ou implícita.

Licença completa:

https://www.gnu.org/licenses/gpl-3.0.html

---

⭐ Se este projeto foi útil para você, considere deixar uma estrela no GitHub.

## Configuração de proxy e CORS

Em produção, preencha `CORS_ALLOWED_ORIGINS` com origens exatas e nunca use `*`. Se a aplicação estiver atrás de proxy reverso, informe os IPs desse proxy em `TRUSTED_PROXIES`; sem essa configuração, o rate limit usa somente `REMOTE_ADDR`.
