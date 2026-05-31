<?php

namespace App\Requests;

use Core\Session;
use Core\Validator;

/**
 * FormRequest — Classe Base de Requisições
 * ─────────────────────────────────────────────────────────────────────────────
 * Inspirada no FormRequest do Laravel, adaptada para PHP puro.
 *
 * Centraliza em um único lugar:
 *   - Captura de dados da requisição ($_POST, $_FILES, JSON body)
 *   - Sanitização dos campos antes de validar
 *   - Validação por regras declarativas
 *   - Mensagens de erro customizadas por campo
 *   - Autorização da requisição
 *   - Retorno dos dados já validados e limpos
 *
 * Fluxo interno:
 *   new LoginRequest()
 *     → captura dados
 *     → verifica authorize()
 *     → sanitiza via sanitize()
 *     → valida contra rules() com messages() customizadas
 *     → expõe fails() / errors() / validated()
 *
 * Uso no Controller:
 *   $request = new LoginRequest();
 *   if ($request->fails()) {
 *       Session::flash('error', $request->firstError());
 *       $this->back();
 *   }
 *   $data = $request->validated(); // array limpo e validado
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */
abstract class FormRequest
{
    /** Dados brutos capturados da requisição */
    protected array $input = [];

    /** Dados após sanitização */
    protected array $sanitized = [];

    /** Erros de validação: ['campo' => ['mensagem', ...]] */
    protected array $errors = [];

    /** Dados que passaram em todas as regras */
    protected array $validatedData = [];

    /** Se a validação já foi executada */
    private bool $resolved = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos para implementar nas subclasses
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Define se a requisição está autorizada.
     * Retorne false para bloquear com HTTP 403.
     *
     * Exemplos de uso:
     *   return Auth::check();                    // apenas logados
     *   return Auth::is('admin');                // apenas admins
     *   return true;                             // sempre permitido
     */
    abstract public function authorize(): bool;

    /**
     * Regras de validação no formato do Core\Validator.
     *
     * Exemplo:
     *   return [
     *       'name'  => 'required|min:2|max:100',
     *       'email' => 'required|email|unique:users,email',
     *   ];
     */
    abstract public function rules(): array;

    /**
     * Mensagens customizadas por campo e regra.
     * Formato: 'campo.regra' => 'mensagem'
     *
     * Exemplo:
     *   return [
     *       'email.required' => 'Informe seu e-mail.',
     *       'email.email'    => 'O e-mail informado não é válido.',
     *       'name.min'       => 'O nome deve ter pelo menos 2 caracteres.',
     *   ];
     *
     * Retorne [] para usar as mensagens padrão do Validator.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Sanitiza os dados antes da validação.
     * Sobrescreva para aplicar transformações específicas.
     *
     * Exemplo:
     *   return array_merge($this->input, [
     *       'email' => strtolower(trim($this->input['email'] ?? '')),
     *       'name'  => ucwords(trim($this->input['name'] ?? '')),
     *   ]);
     *
     * O comportamento padrão aplica trim + strip_tags + htmlspecialchars
     * em todos os campos de texto.
     */
    public function sanitize(): array
    {
        return $this->sanitizeRecursive($this->input);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Constructor — captura e resolve na instanciação
    // ─────────────────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->input = $this->captureInput();
        $this->resolve();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API pública
    // ─────────────────────────────────────────────────────────────────────────

    /** Retorna true se a validação falhou */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** Retorna true se a validação passou */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Retorna todos os erros de validação.
     * Formato: ['campo' => ['mensagem1', 'mensagem2'], ...]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna o primeiro erro de um campo específico,
     * ou o primeiro erro global se nenhum campo for informado.
     */
    public function firstError(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }
        $first = reset($this->errors);
        return $first ? $first[0] : null;
    }

    /**
     * Retorna apenas os dados que passaram em todas as validações,
     * já sanitizados. Use este array no Service/Model — nunca $_POST diretamente.
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Retorna o valor de um campo validado (atalho para validated()['campo']).
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->validatedData[$field] ?? $default;
    }

    /**
     * Retorna o valor de um campo do input bruto (antes de validar),
     * útil para repopular formulários após erro.
     */
    public function old(string $field, mixed $default = ''): mixed
    {
        return $this->sanitized[$field] ?? $this->input[$field] ?? $default;
    }

    /**
     * Retorna todos os dados da entrada sanitizada (não necessariamente válidos).
     * Útil para repopular formulários.
     */
    public function all(): array
    {
        return $this->sanitized;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Orquestra o fluxo completo:
     * authorize → sanitize → validate → aplicar mensagens customizadas
     */
    private function resolve(): void
    {
        if ($this->resolved) return;
        $this->resolved = true;

        // 1. Verifica autorização
        if (!$this->authorize()) {
            http_response_code(403);
            $isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
                   || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Ação não autorizada.']);
            } else {
                Session::flash('error', 'Você não tem permissão para realizar esta ação.');
                $back = $_SERVER['HTTP_REFERER'] ?? (defined('APP_URL') ? APP_URL : '/');
                header("Location: {$back}");
            }
            exit;
        }

        // 2. Sanitiza
        $this->sanitized = $this->sanitize();

        // 3. Valida
        $validator = new Validator($this->sanitized);
        $validator->validate($this->rules());

        // 4. Aplica mensagens customizadas sobrescrevendo as geradas
        if ($validator->fails()) {
            $this->errors = $this->applyCustomMessages(
                $validator->errors(),
                $this->messages()
            );
            return;
        }

        // 5. Dados validados = sanitizados filtrados pelas chaves das rules
        $this->validatedData = array_intersect_key(
            $this->sanitized,
            $this->rules()
        );
    }

    /**
     * Captura dados da requisição em ordem de prioridade:
     * JSON body (APIs) → $_POST → campos individuais de $_FILES
     */
    private function captureInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // JSON body (para APIs REST)
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }

        // Form data padrão + nomes dos arquivos
        $data = $_POST;
        foreach ($_FILES as $key => $file) {
            // Adiciona o nome original do arquivo para validações de extensão
            if (!isset($data[$key])) {
                $data[$key] = $file['name'] ?? '';
            }
        }

        return $data;
    }

    /**
     * Sanitização recursiva padrão:
     * strings → trim + strip_tags + htmlspecialchars
     * arrays  → recursivo
     * outros  → sem alteração
     */
    private function sanitizeRecursive(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeRecursive'], $value);
        }

        if (is_string($value)) {
            return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    /**
     * Sobrescreve mensagens geradas pelo Validator com as mensagens customizadas
     * definidas em messages(). Formato da chave: 'campo.regra'
     *
     * Exemplo: ['email.required' => 'Informe seu e-mail.']
     */
    private function applyCustomMessages(array $errors, array $custom): array
    {
        if (empty($custom)) return $errors;

        foreach ($errors as $field => $messages) {
            foreach ($messages as $i => $message) {
                // Tenta identificar qual regra gerou esta mensagem
                // buscando no mapa 'campo.regra' => 'mensagem customizada'
                foreach ($custom as $key => $customMsg) {
                    [$customField, $customRule] = array_pad(explode('.', $key, 2), 2, '');
                    if ($customField === $field && $this->messageMatchesRule($message, $customRule)) {
                        $errors[$field][$i] = $customMsg;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Verifica se uma mensagem de erro corresponde a uma regra específica,
     * comparando palavras-chave da mensagem com o nome da regra.
     */
    private function messageMatchesRule(string $message, string $rule): bool
    {
        $ruleBase = explode(':', $rule)[0]; // 'min:3' → 'min'

        $keywords = [
            'required'     => ['obrigatório', 'obrigatorio'],
            'email'        => ['e-mail', 'email'],
            'min'          => ['mínimo', 'minimo', 'pelo menos'],
            'max'          => ['máximo', 'maximo'],
            'confirmed'    => ['confirmação', 'confirmacao', 'coincidir'],
            'numeric'      => ['numérico', 'numerico'],
            'integer'      => ['inteiro'],
            'url'          => ['url'],
            'unique'       => ['em uso', 'já está'],
            'exists'       => ['não foi encontrado', 'nao foi encontrado'],
            'alpha'        => ['letras'],
            'in'           => ['não é permitido', 'nao e permitido'],
            'same'         => ['igual'],
            'different'    => ['diferente'],
        ];

        if (!isset($keywords[$ruleBase])) return false;

        $msgLower = mb_strtolower($message, 'UTF-8');
        foreach ($keywords[$ruleBase] as $kw) {
            if (str_contains($msgLower, $kw)) return true;
        }

        return false;
    }
}
