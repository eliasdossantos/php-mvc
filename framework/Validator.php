<?php

namespace Framework;

/**
 * Validator — Sistema de Validação
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida arrays de dados (e arquivos enviados) contra um conjunto de regras.
 * 100% compatível com as rules() já existentes no projeto — nada muda para
 * quem já usa 'required|min:2|max:150', etc. As regras novas só entram em
 * ação quando você as usa.
 *
 * ── Regras de dados ──────────────────────────────────────────────────────────
 *   required                Campo obrigatório
 *   min:N / min_length:N    Mínimo de N caracteres (string) ou valor (número)
 *   max:N / max_length:N    Máximo de N caracteres (string) ou valor (número)
 *   between:min,max         Entre min e max (caracteres ou valor, como min/max)
 *   size:N                  Exatamente N caracteres (string) ou valor (número)
 *   email                   E-mail válido
 *   numeric                 Valor numérico
 *   integer                 Inteiro
 *   boolean                 Aceita true/false/1/0/'1'/'0' (qualquer representação comum)
 *   alpha                   Apenas letras
 *   alphanumeric            Letras e números
 *   url                     URL válida
 *   ip                      IP válido (v4 ou v6)
 *   uuid                    UUID válido
 *   json                    String JSON válida
 *   date                    Data válida
 *   time                    Hora válida (HH:MM ou HH:MM:SS)
 *   after:campo             Data/hora deve ser depois de outro campo
 *   before:campo            Data/hora deve ser antes de outro campo
 *   array                   Deve ser um array (ex: checkboxes múltiplos — servicos[])
 *   digits:N                Apenas dígitos, exatamente N
 *   digits_between:min,max  Apenas dígitos, entre min e max dígitos
 *   cep                     CEP brasileiro válido (com ou sem máscara)
 *   uf                      Sigla de estado brasileiro válida (SP, RJ, DF...)
 *   phone                   Telefone brasileiro válido (fixo ou celular, com ou sem máscara)
 *   confirmed                Campo deve ser igual a {campo}_confirmation
 *   same:outro_campo        Deve ser igual a outro campo
 *   different:outro         Deve ser diferente de outro campo
 *   starts_with:a,b         Deve começar com um dos valores
 *   ends_with:a,b           Deve terminar com um dos valores
 *   in:a,b,c                Deve estar na lista
 *   not_in:a,b,c            Não deve estar na lista
 *   regex:/pattern/         Corresponde à expressão regular
 *   required_if:campo,val   Obrigatório se outro campo === val
 *   required_unless:c,val   Obrigatório se outro campo !== val
 *   required_with:campo     Obrigatório se outro campo estiver presente/preenchido
 *   required_without:campo  Obrigatório se outro campo NÃO estiver presente
 *   unique:tabela,col       Valor único no banco (col default = field name)
 *   exists:tabela,col       Deve existir no banco
 *   nullable                Permite null/vazio (pula as demais validações)
 *
 * ── Regras de arquivo (estilo Laravel) ───────────────────────────────────────
 * Ativadas automaticamente quando o campo declara file/image/mimes — o
 * Validator passa a buscar o valor em $_FILES em vez de $_POST, e min/max
 * passam a significar KILOBYTES em vez de caracteres.
 *
 *   file                    Deve ser um arquivo enviado com sucesso (qualquer tipo)
 *   image                   Deve ser uma imagem (mime real verificado via finfo)
 *   mimes:jpg,png,webp      Extensão + mime real devem bater com a lista
 *   max:N                   Tamanho máximo em KB
 *   min:N                   Tamanho mínimo em KB
 *
 * Uso:
 *   $v = Validator::make($request->all(), $formRequest->rules(), $_FILES);
 *   if ($v->fails()) {
 *       return ['errors' => $v->errors()];
 *   }
 *
 * Exemplo de rules() já existente + upgrades opcionais:
 *   'imagem'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
 *   'cep'         => 'required|cep',
 *   'estado'      => 'required|uf',
 *   'telefone'    => 'required|phone',
 *   'hora_inicio' => 'required|time',
 *   'hora_fim'    => 'required|time|after:hora_inicio',
 *   'servicos'    => 'nullable|array',
 */
class Validator
{
    /** Extensões conhecidas → mime types reais aceitos (whitelist de segurança) */
    private const MIME_MAP = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'svg'  => ['image/svg+xml'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv'  => ['text/csv', 'text/plain'],
        'txt'  => ['text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'mp4'  => ['video/mp4'],
        'mp3'  => ['audio/mpeg'],
    ];

    /** Siglas válidas de estado brasileiro (27 UFs) */
    private const UF_LIST = [
        'AC',
        'AL',
        'AP',
        'AM',
        'BA',
        'CE',
        'DF',
        'ES',
        'GO',
        'MA',
        'MT',
        'MS',
        'MG',
        'PA',
        'PB',
        'PR',
        'PE',
        'PI',
        'RJ',
        'RN',
        'RS',
        'RO',
        'RR',
        'SC',
        'SP',
        'SE',
        'TO',
    ];

    private array $errors    = [];
    private array $data      = [];
    private array $files     = []; // formato $_FILES: campo => ['name','type','tmp_name','error','size']
    private bool  $stopFirst = false; // Para no primeiro erro do campo

    public function __construct(array $data, array $files = [])
    {
        $this->data  = $data;
        $this->files = $files;
    }

    /** Permite injetar/trocar os arquivos após a construção (ex: dentro de um FormRequest) */
    public function setFiles(array $files): static
    {
        $this->files = $files;
        return $this;
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

    public function passes(): bool
    {
        return empty($this->errors);
    }
    public function fails(): bool
    {
        return !$this->passes();
    }
    public function errors(): array
    {
        return $this->errors;
    }
    public function firstError(?string $field = null): ?string
    {
        if ($field) return $this->errors[$field][0] ?? null;
        $first = reset($this->errors);
        return $first ? $first[0] : null;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function validateField(string $field, array $rules): void
    {
        $ruleNames = array_map(fn($r) => str_contains($r, ':') ? explode(':', $r, 2)[0] : $r, $rules);

        // Campo é tratado como "campo de arquivo" se declarar file/image/mimes.
        // Isso muda de onde o valor vem ($this->files em vez de $this->data)
        // e como min/max são interpretados (KB em vez de caracteres/valor).
        $isFileField = (bool) array_intersect(['file', 'image', 'mimes'], $ruleNames);

        $value    = $isFileField ? ($this->files[$field] ?? null) : ($this->data[$field] ?? null);
        $nullable = in_array('nullable', $rules);

        $isEmpty = $isFileField
            ? $this->fileIsEmpty($value)
            : ($value === null || $value === '' || $value === []);

        // required_if / required_unless / required_with / required_without
        // podem tornar um campo "nullable na prática" obrigatório — resolvido
        // antes do skip de nullable, senão a regra nunca dispararia.
        foreach ($rules as $rule) {
            [$name, $param] = str_contains($rule, ':') ? explode(':', $rule, 2) : [$rule, null];
            if (in_array($name, ['required_if', 'required_unless', 'required_with', 'required_without']) && $isEmpty) {
                $error = $this->applyConditionalRequired($name, $field, $param);
                if ($error) {
                    $this->errors[$field][] = $error;
                    if ($this->stopFirst) return;
                }
            }
        }

        // Se o campo for nullable e estiver vazio, skip nas demais regras
        if ($nullable && $isEmpty) return;

        $isNumericField = (bool) array_intersect(['numeric', 'integer'], $rules);

        foreach ($rules as $rule) {
            if ($rule === 'nullable') continue;
            if (
                str_starts_with($rule, 'required_if') || str_starts_with($rule, 'required_unless')
                || str_starts_with($rule, 'required_with') || str_starts_with($rule, 'required_without')
            ) {
                continue; // já tratado acima
            }

            [$name, $param] = str_contains($rule, ':')
                ? explode(':', $rule, 2)
                : [$rule, null];

            $error = $isFileField
                ? $this->applyFileRule($name, $field, $value, $param)
                : $this->applyRule($name, $field, $value, $param, $isNumericField);

            if ($error) {
                $this->errors[$field][] = $error;
                if ($this->stopFirst) break;
            }
        }
    }

    private function applyRule(string $rule, string $field, mixed $value, ?string $param, bool $isNumericField = false): ?string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required'     => ((empty($value) && $value !== '0') && $value !== [])
                ? "O campo {$label} é obrigatório." : null,

            'min', 'min_length' => (!empty($value) && $isNumericField
                ? (float)$value < (float)$param
                : mb_strlen((string)$value) < (int)$param)
                ? ($isNumericField
                    ? "O campo {$label} deve ser no mínimo {$param}."
                    : "O campo {$label} deve ter no mínimo {$param} caracteres.") : null,

            'max', 'max_length' => (!empty($value) && $isNumericField
                ? (float)$value > (float)$param
                : mb_strlen((string)$value) > (int)$param)
                ? ($isNumericField
                    ? "O campo {$label} deve ser no máximo {$param}."
                    : "O campo {$label} deve ter no máximo {$param} caracteres.") : null,

            'between'      => $this->validateBetween($field, $value, $param, $isNumericField),

            'size'         => (!empty($value) && ($isNumericField
                ? (float)$value != (float)$param
                : mb_strlen((string)$value) != (int)$param))
                ? ($isNumericField
                    ? "O campo {$label} deve ser exatamente {$param}."
                    : "O campo {$label} deve ter exatamente {$param} caracteres.") : null,

            'email'        => (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL))
                ? "O campo {$label} deve ser um e-mail válido." : null,

            'url'          => (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL))
                ? "O campo {$label} deve ser uma URL válida." : null,

            'ip'           => (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP))
                ? "O campo {$label} deve ser um IP válido." : null,

            'uuid'         => (!empty($value) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$value))
                ? "O campo {$label} deve ser um UUID válido." : null,

            'json'         => (!empty($value) && (!is_string($value) || json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE))
                ? "O campo {$label} deve ser um JSON válido." : null,

            'numeric'      => (!empty($value) && !is_numeric($value))
                ? "O campo {$label} deve ser numérico." : null,

            'integer'      => (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT))
                ? "O campo {$label} deve ser um número inteiro." : null,

            'boolean'      => (!($value === null || $value === '') && !in_array($value, [true, false, 0, 1, '0', '1'], true))
                ? "O campo {$label} deve ser verdadeiro ou falso." : null,

            'alpha'        => (!empty($value) && !ctype_alpha(str_replace(' ', '', (string)$value)))
                ? "O campo {$label} deve conter apenas letras." : null,

            'alphanumeric' => (!empty($value) && !ctype_alnum(str_replace([' ', '_', '-'], '', (string)$value)))
                ? "O campo {$label} deve conter apenas letras e números." : null,

            'digits'       => (!empty($value) && !(ctype_digit((string)$value) && mb_strlen((string)$value) === (int)$param))
                ? "O campo {$label} deve conter exatamente {$param} dígitos." : null,

            'digits_between' => $this->validateDigitsBetween($field, $value, $param),

            'array'        => (!($value === null || $value === '') && !is_array($value))
                ? "O campo {$label} deve ser uma lista de valores." : null,

            'date'         => (!empty($value) && strtotime((string)$value) === false)
                ? "O campo {$label} deve ser uma data válida." : null,

            'time'         => (!empty($value) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string)$value))
                ? "O campo {$label} deve ser um horário válido (HH:MM)." : null,

            'after'        => $this->validateAfterBefore($field, $value, $param, after: true),
            'before'       => $this->validateAfterBefore($field, $value, $param, after: false),

            'cep'          => (!empty($value) && !preg_match('/^\d{5}-?\d{3}$/', (string)$value))
                ? "O campo {$label} deve ser um CEP válido." : null,

            'uf'           => (!empty($value) && !in_array(strtoupper((string)$value), self::UF_LIST, true))
                ? "O campo {$label} deve ser uma UF válida." : null,

            'phone'        => (!empty($value) && !preg_match('/^\d{10,11}$/', preg_replace('/\D/', '', (string)$value)))
                ? "O campo {$label} deve ser um telefone válido." : null,

            'confirmed'    => (!empty($value) && $value !== ($this->data[$field . '_confirmation'] ?? null))
                ? "A confirmação do campo {$label} não confere." : null,

            'same'         => (!empty($value) && $value !== ($this->data[$param] ?? null))
                ? "O campo {$label} deve ser igual ao campo {$param}." : null,

            'different'    => (!empty($value) && $value === ($this->data[$param] ?? null))
                ? "O campo {$label} deve ser diferente do campo {$param}." : null,

            'starts_with'  => (!empty($value) && !$this->matchesAnyPrefixSuffix((string)$value, $param, true))
                ? "O campo {$label} tem formato inválido." : null,

            'ends_with'    => (!empty($value) && !$this->matchesAnyPrefixSuffix((string)$value, $param, false))
                ? "O campo {$label} tem formato inválido." : null,

            'in'           => (!empty($value) && !in_array($value, explode(',', $param ?? '')))
                ? "O valor do campo {$label} não é permitido." : null,

            'not_in'       => (!empty($value) && in_array($value, explode(',', $param ?? '')))
                ? "O valor do campo {$label} não é permitido." : null,

            'regex'        => (!empty($value) && !preg_match($param ?? '//', (string)$value))
                ? "O campo {$label} tem formato inválido." : null,

            'unique'       => $this->validateUnique($field, $value, $param),
            'exists'       => $this->validateExists($field, $value, $param),

            // 'file', 'image', 'mimes' nunca chegam aqui — roteadas para
            // applyFileRule() quando o campo é detectado como campo de arquivo.
            default        => null,
        };
    }

    /**
     * Regras específicas para campos de upload ($_FILES).
     * $value é o subarray individual do campo: ['name','type','tmp_name','error','size'].
     */
    private function applyFileRule(string $rule, string $field, mixed $value, ?string $param): ?string
    {
        $label   = ucfirst(str_replace('_', ' ', $field));
        $isEmpty = $this->fileIsEmpty($value);

        return match ($rule) {
            'required'     => $isEmpty ? "O campo {$label} é obrigatório." : null,

            'file'         => (!$isEmpty && !$this->uploadOk($value))
                ? "O envio do arquivo em {$label} falhou. Tente novamente." : null,

            'image'        => (!$isEmpty && $this->uploadOk($value) && !$this->realMimeStartsWith($value, 'image/'))
                ? "O campo {$label} deve ser uma imagem válida." : null,

            'mimes'        => (!$isEmpty && $this->uploadOk($value) && !$this->mimeAllowed($value, $param))
                ? "O campo {$label} deve ser um arquivo do tipo: {$param}." : null,

            // Aqui max/min são em KILOBYTES, seguindo a convenção do Laravel —
            // diferente do max/min usado em strings/números em applyRule().
            'max'          => (!$isEmpty && $this->uploadOk($value) && ($value['size'] / 1024) > (float)$param)
                ? "O campo {$label} não pode ser maior que {$param}KB." : null,

            'min'          => (!$isEmpty && $this->uploadOk($value) && ($value['size'] / 1024) < (float)$param)
                ? "O campo {$label} deve ter no mínimo {$param}KB." : null,

            default        => null,
        };
    }

    // ── Helpers de regras compostas ──────────────────────────────────────────

    private function validateBetween(string $field, mixed $value, ?string $param, bool $isNumericField): ?string
    {
        if (empty($value) || $param === null) return null;
        [$min, $max] = array_pad(explode(',', $param), 2, 0);
        $label = ucfirst(str_replace('_', ' ', $field));

        $size = $isNumericField ? (float)$value : mb_strlen((string)$value);
        $ok   = $size >= (float)$min && $size <= (float)$max;

        if ($ok) return null;

        return $isNumericField
            ? "O campo {$label} deve estar entre {$min} e {$max}."
            : "O campo {$label} deve ter entre {$min} e {$max} caracteres.";
    }

    private function validateDigitsBetween(string $field, mixed $value, ?string $param): ?string
    {
        if (empty($value) || $param === null) return null;
        [$min, $max] = array_pad(explode(',', $param), 2, 0);
        $label = ucfirst(str_replace('_', ' ', $field));
        $len   = mb_strlen((string)$value);

        if (ctype_digit((string)$value) && $len >= (int)$min && $len <= (int)$max) return null;

        return "O campo {$label} deve ter entre {$min} e {$max} dígitos.";
    }

    private function validateAfterBefore(string $field, mixed $value, ?string $param, bool $after): ?string
    {
        if (empty($value) || empty($param)) return null;

        $other = $this->data[$param] ?? null;
        if ($other === null || $other === '') return null; // nada pra comparar

        $valueTime = strtotime((string)$value);
        $otherTime = strtotime((string)$other);
        if ($valueTime === false || $otherTime === false) return null; // outra regra (date/time) já cobre formato inválido

        $label      = ucfirst(str_replace('_', ' ', $field));
        $otherLabel = ucfirst(str_replace('_', ' ', $param));

        if ($after && $valueTime <= $otherTime) {
            return "O campo {$label} deve ser depois de {$otherLabel}.";
        }
        if (!$after && $valueTime >= $otherTime) {
            return "O campo {$label} deve ser antes de {$otherLabel}.";
        }
        return null;
    }

    private function matchesAnyPrefixSuffix(string $value, ?string $param, bool $prefix): bool
    {
        if (empty($param)) return true;
        foreach (explode(',', $param) as $needle) {
            if ($prefix ? str_starts_with($value, $needle) : str_ends_with($value, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function applyConditionalRequired(string $rule, string $field, ?string $param): ?string
    {
        if ($param === null) return null;
        $label = ucfirst(str_replace('_', ' ', $field));
        [$otherField, $otherValue] = array_pad(explode(',', $param, 2), 2, null);

        $otherPresent = isset($this->data[$otherField]) && $this->data[$otherField] !== '' && $this->data[$otherField] !== null;
        $otherMatches = $otherPresent && (string)$this->data[$otherField] === (string)$otherValue;

        $mustBeRequired = match ($rule) {
            'required_if'       => $otherMatches,
            'required_unless'   => !$otherMatches,
            'required_with'     => $otherPresent,
            'required_without'  => !$otherPresent,
            default              => false,
        };

        return $mustBeRequired ? "O campo {$label} é obrigatório." : null;
    }

    /** Nenhum arquivo foi enviado para este campo (não confundir com erro de upload) */
    private function fileIsEmpty(mixed $value): bool
    {
        return !is_array($value)
            || !isset($value['error'])
            || $value['error'] === UPLOAD_ERR_NO_FILE;
    }

    /** Upload chegou sem erros do PHP (tamanho de POST excedido, parcial, etc.) */
    private function uploadOk(array $value): bool
    {
        return ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && is_uploaded_file($value['tmp_name'] ?? '');
    }

    /**
     * Mime real do arquivo, obtido lendo o conteúdo (via fileinfo) — nunca
     * confia em $value['type'], que vem do cliente e pode ser forjado.
     */
    private function realMime(array $value): ?string
    {
        if (!is_uploaded_file($value['tmp_name'] ?? '')) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $value['tmp_name']) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    private function realMimeStartsWith(array $value, string $prefix): bool
    {
        $mime = $this->realMime($value);
        return $mime !== null && str_starts_with($mime, $prefix);
    }

    /**
     * Verifica se o arquivo bate com a lista de extensões permitidas,
     * conferindo tanto a extensão do nome enviado quanto o mime real
     * (evita que um .php renomeado para .jpg passe na validação).
     */
    private function mimeAllowed(array $value, ?string $param): bool
    {
        if (empty($param)) return true;

        $allowedExtensions = array_map('trim', explode(',', strtolower($param)));

        $sentExtension = strtolower(pathinfo($value['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($sentExtension, $allowedExtensions, true)) return false;

        $realMime = $this->realMime($value);
        if ($realMime === null) return false;

        // Se a extensão enviada estiver mapeada, o mime real precisa bater
        // com um dos mimes esperados para ela. Extensões fora do mapa
        // (raras/custom) caem só na checagem de extensão acima.
        if (isset(self::MIME_MAP[$sentExtension])) {
            return in_array($realMime, self::MIME_MAP[$sentExtension], true);
        }

        return true;
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

        if ($ignore !== null && $ignore !== '' && ctype_digit($ignore) && (int)$ignore > 0) {
            $sql .= " AND id != :ign";
        } else {
            $ignore = null;
        }

        $stmt = $db->query($sql)->bind(':v', $value);
        if ($ignore !== null) $stmt->bind(':ign', (int)$ignore);
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

    public static function make(array $data, array $rules, array $files = []): static
    {
        return (new static($data, $files))->validate($rules);
    }
}
