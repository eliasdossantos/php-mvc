<?php

namespace Core;

/**
 * Upload — Sistema de Upload de Arquivos Reutilizável
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida, processa e armazena arquivos enviados via formulário.
 *
 * Uso:
 *   $upload = new Upload($_FILES['avatar']);
 *   $upload->setAllowedTypes(['image/jpeg', 'image/png', 'image/webp'])
 *          ->setMaxSize(2 * 1024 * 1024)   // 2MB
 *          ->setUploadDir(STORAGE_PATH . '/uploads/avatars');
 *
 *   if ($upload->process()) {
 *       $filename = $upload->getFilename();
 *   } else {
 *       $errors = $upload->getErrors();
 *   }
 */
class Upload
{
    protected array  $file;
    protected array  $errors      = [];
    protected string $uploadDir   = '';
    protected array  $allowedTypes = [];
    protected int    $maxSize     = 5 * 1024 * 1024; // 5MB default
    protected array  $allowedExtensions = [];
    protected bool   $randomName  = true;
    protected string $prefix      = '';
    protected ?string $filename   = null;

    public function __construct(array $file)
    {
        $this->file      = $file;
        $this->uploadDir = STORAGE_PATH . '/uploads';
    }

    // ── Configuração fluente ──────────────────────────────────────────────────

    public function setAllowedTypes(array $mimeTypes): static
    {
        $this->allowedTypes = $mimeTypes;
        return $this;
    }

    public function setAllowedExtensions(array $exts): static
    {
        $this->allowedExtensions = array_map('strtolower', $exts);
        return $this;
    }

    public function setMaxSize(int $bytes): static
    {
        $this->maxSize = $bytes;
        return $this;
    }

    public function setUploadDir(string $dir): static
    {
        $this->uploadDir = $dir;
        return $this;
    }

    public function setRandomName(bool $random): static
    {
        $this->randomName = $random;
        return $this;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    // ── Presets rápidos ───────────────────────────────────────────────────────

    /** Configura para aceitar apenas imagens (jpg, png, gif, webp) */
    public function forImages(int $maxMb = 5): static
    {
        return $this
            ->setAllowedTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])
            ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ->setMaxSize($maxMb * 1024 * 1024);
    }

    /** Configura para aceitar documentos (pdf, doc, docx, xls, xlsx) */
    public function forDocuments(int $maxMb = 10): static
    {
        return $this
            ->setAllowedTypes(['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx'])
            ->setMaxSize($maxMb * 1024 * 1024);
    }

    // ── Processamento ─────────────────────────────────────────────────────────

    public function process(): bool
    {
        $this->errors = [];

        if (!$this->validateUpload()) return false;

        // Garante que o diretório existe
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        $filename = $this->randomName
            ? $this->prefix . bin2hex(random_bytes(16)) . '.' . $ext
            : $this->sanitizeFilename(pathinfo($this->file['name'], PATHINFO_FILENAME)) . '.' . $ext;

        $destination = $this->uploadDir . '/' . $filename;

        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            $this->errors[] = 'Falha ao mover o arquivo. Verifique as permissões do diretório.';
            return false;
        }

        $this->filename = $filename;
        return true;
    }

    // ── Validação ─────────────────────────────────────────────────────────────

    protected function validateUpload(): bool
    {
        // Erro de upload do PHP
        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($this->file['error']);
            return false;
        }

        // Verifica se é um upload legítimo
        if (!is_uploaded_file($this->file['tmp_name'])) {
            $this->errors[] = 'Arquivo inválido.';
            return false;
        }

        // Tamanho
        if ($this->file['size'] > $this->maxSize) {
            $max = round($this->maxSize / 1024 / 1024, 1);
            $this->errors[] = "O arquivo excede o tamanho máximo de {$max}MB.";
            return false;
        }

        // MIME type (usando finfo — mais seguro que o MIME declarado pelo cliente)
        if ($this->allowedTypes) {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo->file($this->file['tmp_name']);

            if (!in_array($mimeReal, $this->allowedTypes)) {
                $this->errors[] = 'Tipo de arquivo não permitido: ' . $mimeReal;
                return false;
            }
        }

        // Extensão
        if ($this->allowedExtensions) {
            $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions)) {
                $allowed = implode(', ', $this->allowedExtensions);
                $this->errors[] = "Extensão não permitida. Permitidas: {$allowed}.";
                return false;
            }
        }

        return true;
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/[^a-z0-9_-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-') ?: 'file';
    }

    protected function getUploadErrorMessage(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido.',
            UPLOAD_ERR_PARTIAL  => 'O upload foi interrompido.',
            UPLOAD_ERR_NO_FILE  => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo.',
            default             => 'Erro desconhecido no upload.',
        };
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getFilename(): ?string { return $this->filename; }
    public function getErrors(): array     { return $this->errors; }
    public function hasErrors(): bool      { return !empty($this->errors); }
    public function getFirstError(): ?string { return $this->errors[0] ?? null; }
}
