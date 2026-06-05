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
- Migrations
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

- Proteção CSRF
- Prevenção contra SQL Injection
- Escape automático contra XSS
- Sessões seguras
- Security Headers
- Prepared Statements obrigatórios
- Controle de acesso por middleware

---

## Documentação

A documentação completa está disponível em:

```text
docs/index.html
```

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

## Banco de Dados Migrations (Criar e Aplicar)

Crie uma nova migration SQL com o comando generator. O arquivo será criado em `database/migrations/` com prefixo numérico sequencial (`001_`, `002_`, ...).

Exemplos:

```bash
php mvc make:migration CreatePostsTable
php mvc make:migration AddEmailToUsers
php mvc make:migration drop_comments_table
```

O comando converte o nome para snake_case e gera um arquivo como `002_create_posts_table.sql`. Dentro dele há um template comentado — descomente e ajuste o `CREATE TABLE` conforme sua necessidade. O nome da tabela gerado automaticamente é baseado no nome fornecido (`CreatePostsTable` → `posts`).

Depois de editar sua migration, aplique as migrations pendentes com:

```bash
php mvc migrate
```

Se quiser recriar tudo do zero (DROP + migrate):

```bash
php mvc migrate --fresh
```

Dica rápida:

- Os arquivos de migration ficam em `database/migrations/` e são executados em ordem crescente pelo prefixo numérico.
- Verifique se o `CREATE TABLE` está com o nome da tabela correto (ex.: `posts`, `users`) e com os campos desejados.

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

php mvc make:seed UserSeeder

php mvc make:view users
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
│
├── app/
│   ├── Core/
│   ├── Controllers/
│   ├── Models/
│   ├── Requests/
│   ├── Services/
│   ├── Repositories/
│   ├── Middlewares/
│   ├── Helpers/
│   └── Views/
│
├── bootstrap/
├── config/
├── database/
├── docs/
├── public/
├── routes/
├── storage/
│
├── composer.json
├── .env.example
└── README.md
```

---

## Classes Principais

| Classe      | Responsabilidade            |
| ----------- | --------------------------- |
| Application | Inicialização da aplicação  |
| Router      | Gerenciamento de rotas      |
| Controller  | Classe base dos controllers |
| Model       | Classe base dos models      |
| Database    | Conexão PDO                 |
| Session     | Gerenciamento de sessões    |
| Auth        | Autenticação                |
| Validator   | Validação de dados          |
| Upload      | Upload de arquivos          |
| Logger      | Sistema de logs             |
| Request     | Manipulação de requisições  |
| Service     | Regras de negócio           |
| Repository  | Camada de acesso a dados    |

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

## Changelog

Consulte:

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
