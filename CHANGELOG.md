# Changelog

Todas as alterações relevantes deste projeto serão documentadas neste arquivo.

O formato segue o padrão **Keep a Changelog** e este projeto adota o **Versionamento Semântico (SemVer)**.

## [1.0.0] - 2026-05-31

### Adicionado

#### Core MVC

- Classe `Application` para inicialização e gerenciamento da aplicação.
- Sistema de roteamento através da classe `Router`.
- Classes base `Controller`, `Model`, `View`, `Request` e `Response`.
- Estrutura MVC organizada e reutilizável para projetos PHP puro.

#### Banco de Dados

- Classe `Database` utilizando PDO.
- Query Builder para consultas dinâmicas.
- Suporte a transações (`beginTransaction`, `commit`, `rollback`).
- Sistema de migrations.
- Sistema de seeders.

#### Autenticação e Segurança

- Sistema completo de autenticação.
- Login e logout de usuários.
- Registro de usuários.
- Recuperação de senha.
- Gerenciamento seguro de sessões.
- Proteção CSRF.
- Controle de acesso baseado em permissões e papéis.

#### Validação

- Classe `Validator` com regras declarativas.
- Mensagens de erro personalizadas.
- Classe `FormRequest` para validação desacoplada dos controllers.

#### Middlewares

- `AuthMiddleware`
- `GuestMiddleware`
- `CsrfMiddleware`
- `RoleMiddleware`

#### Upload de Arquivos

- Classe `Upload` para gerenciamento de arquivos enviados.
- Validação de tipos e extensões.
- Organização automática de diretórios.

#### Logs

- Sistema de logs inspirado no padrão PSR.
- Registro de eventos e exceções.
- Arquivos de log organizados por data.

#### CLI

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

#### Geração de Código

- Stubs para geração automática de arquivos.
- Estrutura padronizada para novos componentes.

#### E-mail

- Integração com PHPMailer.
- Suporte ao envio de e-mails transacionais.

#### Views e Layouts

- Layout padrão `main`.
- Layout de autenticação `auth`.
- Página de erro 404.
- Página de exceções.
- Tela de depuração para ambiente de desenvolvimento.

#### Documentação

- Documentação HTML disponível no diretório `docs/`.
- Guias de instalação e utilização.
- Exemplos de implementação.
