# Guia de Contribuição

Obrigado por considerar contribuir com o **PHP MVC Boilerplate**!

Este projeto tem como objetivo fornecer uma base sólida, organizada e reutilizável para aplicações desenvolvidas em PHP puro utilizando o padrão MVC.

## Código de Conduta

Ao participar deste projeto, esperamos que todos os colaboradores mantenham um ambiente respeitoso, profissional e colaborativo.

## Como Contribuir

### 1. Reportar Bugs

Encontrou um problema?

Abra uma issue em:

https://github.com/eliasdossantos/php-mvc/issues

Inclua as seguintes informações:

- Descrição clara do problema;
- Passos para reproduzir;
- Comportamento esperado;
- Comportamento atual;
- Versão do PHP;
- Sistema operacional;
- Versão do projeto.

### 2. Sugerir Melhorias

Antes de iniciar uma nova funcionalidade, abra uma issue descrevendo sua proposta.

Isso permite discutir a implementação e manter a consistência do projeto.

### 3. Enviar Pull Requests

```bash
# Faça um fork do projeto

# Clone seu fork
git clone https://github.com/SEU_USUARIO/php-mvc.git

# Entre no diretório
cd php-mvc

# Instale as dependências
composer install

# Crie uma branch para sua alteração
git checkout -b feat/minha-feature

# Faça suas alterações

# Commit
git commit -m "feat: adiciona nova funcionalidade"

# Envie para seu fork
git push origin feat/minha-feature
```

Após isso, abra um Pull Request para a branch `main`.

## Padrões de Código

Este projeto segue as seguintes convenções:

- PSR-12;
- Código limpo e legível;
- Separação adequada de responsabilidades;
- Uso de namespaces;
- DocBlocks em classes e métodos públicos;
- Comentários apenas quando realmente necessários.

## Estrutura do Projeto

Ao contribuir, procure manter a arquitetura existente:

- Controllers → regras de fluxo;
- Models → acesso a dados;
- Services → regras de negócio;
- Repositories → consultas complexas;
- Views → apresentação;
- Middlewares → controle de requisições.

## Testes

Sempre que possível:

- Adicione testes para novas funcionalidades;
- Certifique-se de que funcionalidades existentes continuam funcionando;
- Execute os testes antes de enviar o Pull Request.

```bash
composer test
```

## Commits

Utilize o padrão Conventional Commits:

| Prefixo   | Descrição                             |
| --------- | ------------------------------------- |
| feat:     | Nova funcionalidade                   |
| fix:      | Correção de bug                       |
| docs:     | Alterações na documentação            |
| refactor: | Refatoração sem alterar comportamento |
| test:     | Inclusão ou alteração de testes       |
| chore:    | Manutenção e tarefas internas         |
| perf:     | Melhorias de desempenho               |
| style:    | Ajustes de formatação                 |

Exemplos:

```bash
git commit -m "feat: adiciona sistema de cache"
git commit -m "fix: corrige validação de upload"
git commit -m "docs: atualiza README"
```

## Licença

Ao contribuir com este projeto, você concorda que suas contribuições serão licenciadas sob os termos da GNU General Public License v3.0 (GPL-3.0).

Licença completa:

https://www.gnu.org/licenses/gpl-3.0.html
