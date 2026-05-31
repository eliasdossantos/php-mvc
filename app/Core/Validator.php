<?php

namespace Core;

/**
 * Validator — Sistema de Validação
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida arrays de dados contra um conjunto de regras.
 *
 * Regras disponíveis:
 *   required            Campo obrigatório
 *   min:N               Mínimo de N caracteres (string) ou valor (número)
 *   max:N               Máximo de N caracteres (string) ou valor (número)
 *   email               E-mail válido
 *   numeric             Valor numérico
 *   integer             Inteiro
 *   alpha               Apenas letras
 *   alphanumeric        Letras e números
 *   url                 URL válida
 *   date                Data válida
 *   confirmed           Campo deve ser igual a {campo}_confirmation
 *   same:outro_campo    Deve ser igual a outro campo
 *   different:outro     Deve ser diferente de outro campo
 *   in:a,b,c            Deve estar na lista
 *   not_in:a,b,c        Não deve estar na lista
 *   regex:/pattern/     Corresponde à expressão regular
 *   unique:tabela,col   Valor único no banco (col default = field name)
 *   exists:tabela,col   Deve existir no banco
 *   min_length:N        Alias de min
 *   max_length:N        Alias de max
 *   nullable            Permite null/vazio (pula as demais validações)
 *
 * Uso:
 *   $v = new Validator($request->all());
 *   $v->validate([
 *       'name'  => 'required|min:2|max:100',
 *       'email' => 'required|email|unique:users,email',
 *   ]);
 *   if ($v->fails()) {
 *       return ['errors' => $v->errors()];
 *   }
 */
class Validator
{
    private array  $errors    = [];
    private array  $data      = [];
    private bool   $stopFirst = false; // Para no primeiro erro do campo

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** Executa validação — retorna $this para chaining */
    public function validate(array $rules): static
    {
        foreach ($rules as $field => $ruleString) {
            $this->validateField($field, explode('|', $ruleString));
        }
        return $this;
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    public function passes(): bool { return empty($this->errors); }
    public function fails():  bool { return !$this->passes(); }
    public function errors(): array { return $this->errors; }
    public function firstError(?string $field = null): ?string
    {
        if ($field) return $this->errors[$field][0] ?? null;
        $first = reset($this->errors);
        return $first ? $first[0] : null;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function validateField(string $field, array $rules): void
    {
        $value    = $this->data[$field] ?? null;
        $nullable = in_array('nullable', $rules);

        // Se o campo for nullable e estiver vazio, skip
        if ($nullable && ($value === null || $value === '')) return;

        foreach ($rules as $rule) {
            if ($rule === 'nullable') continue;

            [$name, $param] = str_contains($rule, ':')
                ? explode(':', $rule, 2)
                : [$rule, null];

            $error = $this->applyRule($name, $field, $value, $param);

            if ($error) {
                $this->errors[$field][] = $error;
                if ($this->stopFirst) break;
            }
        }
    }

    private function applyRule(string $rule, string $field, mixed $value, ?string $param): ?string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match($rule) {
            'required'     => (empty($value) && $value !== '0')
                                ? "O campo {$label} é obrigatório." : null,

            'min', 'min_length' => (!empty($value) && is_numeric($value)
                                ? (float)$value < (float)$param
                                : mb_strlen((string)$value) < (int)$param)
                                ? "O campo {$label} deve ter no mínimo {$param} caracteres." : null,

            'max', 'max_length' => (!empty($value) && is_numeric($value)
                                ? (float)$value > (float)$param
                                : mb_strlen((string)$value) > (int)$param)
                                ? "O campo {$label} deve ter no máximo {$param} caracteres." : null,

            'email'        => (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL))
                                ? "O campo {$label} deve ser um e-mail válido." : null,

            'url'          => (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL))
                                ? "O campo {$label} deve ser uma URL válida." : null,

            'numeric'      => (!empty($value) && !is_numeric($value))
                                ? "O campo {$label} deve ser numérico." : null,

            'integer'      => (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT))
                                ? "O campo {$label} deve ser um número inteiro." : null,

            'alpha'        => (!empty($value) && !ctype_alpha(str_replace(' ', '', (string)$value)))
                                ? "O campo {$label} deve conter apenas letras." : null,

            'alphanumeric' => (!empty($value) && !ctype_alnum(str_replace([' ', '_', '-'], '', (string)$value)))
                                ? "O campo {$label} deve conter apenas letras e números." : null,

            'date'         => (!empty($value) && strtotime((string)$value) === false)
                                ? "O campo {$label} deve ser uma data válida." : null,

            'confirmed'    => ($value !== ($this->data[$field . '_confirmation'] ?? null))
                                ? "A confirmação do campo {$label} não confere." : null,

            'same'         => (!empty($value) && $value !== ($this->data[$param] ?? null))
                                ? "O campo {$label} deve ser igual ao campo {$param}." : null,

            'different'    => (!empty($value) && $value === ($this->data[$param] ?? null))
                                ? "O campo {$label} deve ser diferente do campo {$param}." : null,

            'in'           => (!empty($value) && !in_array($value, explode(',', $param ?? '')))
                                ? "O valor do campo {$label} não é permitido." : null,

            'not_in'       => (!empty($value) && in_array($value, explode(',', $param ?? '')))
                                ? "O valor do campo {$label} não é permitido." : null,

            'regex'        => (!empty($value) && !preg_match($param ?? '//', (string)$value))
                                ? "O campo {$label} tem formato inválido." : null,

            'unique'       => $this->validateUnique($field, $value, $param),
            'exists'       => $this->validateExists($field, $value, $param),

            default        => null,
        };
    }

    private function validateUnique(string $field, mixed $value, ?string $param): ?string
    {
        if (empty($value) || empty($param)) return null;

        $parts  = explode(',', $param);
        $table  = $parts[0];
        $col    = $parts[1] ?? $field;
        $ignore = $parts[2] ?? null;

        $db  = Database::getInstance();
        $sql = "SELECT COUNT(*) as n FROM {$table} WHERE {$col} = :v";
        if ($ignore) $sql .= " AND id != :ign";

        $stmt = $db->query($sql)->bind(':v', $value);
        if ($ignore) $stmt->bind(':ign', (int)$ignore);
        $row = $stmt->fetch();

        $label = ucfirst(str_replace('_', ' ', $field));
        return ($row && $row->n > 0)
            ? "O valor informado para {$label} já está em uso."
            : null;
    }

    private function validateExists(string $field, mixed $value, ?string $param): ?string
    {
        if (empty($value) || empty($param)) return null;

        $parts = explode(',', $param);
        $table = $parts[0];
        $col   = $parts[1] ?? 'id';

        $row   = Database::getInstance()
            ->query("SELECT COUNT(*) as n FROM {$table} WHERE {$col} = :v")
            ->bind(':v', $value)
            ->fetch();

        $label = ucfirst(str_replace('_', ' ', $field));
        return (!$row || $row->n === 0)
            ? "O valor do campo {$label} não foi encontrado."
            : null;
    }

    // ── Factory estática ──────────────────────────────────────────────────────

    public static function make(array $data, array $rules): static
    {
        return (new static($data))->validate($rules);
    }
}
