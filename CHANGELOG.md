# Changelog

Todas as alterações relevantes deste projeto serão documentadas neste arquivo.

O formato segue o padrão [**Keep a Changelog**](https://keepachangelog.com/pt-BR/1.0.0/) e este projeto adota o [**Versionamento Semântico**](https://semver.org/lang/pt-BR/).

## [1.4.2] — 2026-09-26

### Alterado

- Migrados os namespaces do framework de Core para Framework, alinhando as classes em `framework/` ao PSR-4.

- Atualizadas referências em aplicação, bootstrap, rotas, configurações, CLI, stubs, migrations, seeders e documentação.

- Preservados os namespaces App, Cli e Tests e os caminhos físicos de logs.

- Corrigidos os comentários e rótulos documentais que descreviam a estrutura anterior.

### Compatibilidade

- Compatível para atualização com projetos das linhas v1.1.X, v1.2.X, v1.3.X e v1.4.X, observadas as instruções de migração do namespace Core para Framework.

- Não há alias para Core: extensões próprias que importem o namespace antigo precisam ser migradas.

- O schema não muda nesta versão. Em instalação inicial/banco vazio, execute `php mvc migrate` para criar `users` e as tabelas/colunas relacionadas; projetos já migrados não precisam de migration adicional para esta versão.

---

## [1.0.0] — 2026-05-31

### ✨ Adicionado

#### 🏗️ Core MVC

- Classe `Application` para inicialização e gerenciamento da aplicação.

- Sistema de roteamento através da classe `Router`.

- Classes base `Controller`, `Model`, `View`, `Request` e `Response`.

- Estrutura MVC organizada e reutilizável para projetos PHP puro.

#### 🗄️ Banco de Dados

- Classe `Database` utilizando PDO.

- Query Builder para consultas dinâmicas.

- Suporte a transações (`beginTransaction`, `commit`, `rollback`).

- Sistema de migrations.

- Sistema de seeders.

#### 🔒 Autenticação e Segurança

- Sistema completo de autenticação.

- Login e logout de usuários.

- Registro de usuários.

- Recuperação de senha.

- Gerenciamento seguro de sessões.

- Proteção CSRF.

- Controle de acesso baseado em permissões e papéis.

#### ✅ Validação

- Classe `Validator` com regras declarativas.

- Mensagens de erro personalizadas.

- Classe `FormRequest` para validação desacoplada dos controllers.

#### 🛡️ Middlewares

- `ApiAuthMiddleware`

- `AuthMiddleware`

- `CorsMiddleware`

- `CsrfMiddleware`

- `DevelopmentMiddleware`

- `GuestMiddleware`

- `RateLimitMiddleware`

- `RoleMiddleware`

- `SecurityHeadersMiddleware`

#### 📁 Upload de Arquivos

- Classe `Upload` para gerenciamento de arquivos enviados.

- Validação de tipos e extensões.

- Organização automática de diretórios.

#### 📋 Logs

- Sistema de logs inspirado no padrão PSR.

- Registro de eventos e exceções.

- Arquivos de log organizados por data e separados fisicamente por responsabilidade.

- `storage/logs/erros_aplicacao/app-YYYY-MM-DD.log`
- `storage/logs/requisicao/requests-YYYY-MM-DD.log`
- `storage/logs/erros_nativos/php_errors.log`

#### ⌨️ CLI

Comando `mvc` com suporte para:

- `make:controller`

- `make:model`

- `make:request`

- `make:service`

- `make:repository`

- `make:seed`

- `make:view`

- `migrate`

- `serve`

#### ⚙️ Geração de Código

- Stubs para geração automática de arquivos.

- Estrutura padronizada para novos componentes.

#### 📧 E-mail

- Integração com PHPMailer.

- Suporte ao envio de e-mails transacionais.

#### 🎨 Views e Layouts

- Layout padrão `main`.

- Layout de autenticação `auth`.

- Página de erro 404.

- Página de exceções.

- Tela de depuração para ambiente de desenvolvimento.

#### 📖 Documentação

- Documentação HTML disponível no diretório `docs/`.

- Guias de instalação e utilização.

- Exemplos de implementação.
