<?php

namespace Framework\Support;

/**
 * Upload — Sistema de Upload de Arquivos Reutilizável
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida, processa e organiza arquivos dentro de storage/uploads.
 *
 * Estrutura gerada:
 *   Com dono (entity + entityId) — ex: avatar de usuário:
 *       uploads/{entity}/{entityId}/arquivo.jpg
 *       → Agrupa por dono. Importante pra achar rápido "todos os arquivos
 *         do usuário 42" quando o sistema crescer (muitos usuários, muitos
 *         tipos de arquivo por usuário).
 *
 *   Sem dono (só entity) — ex: banner avulso, sem usuário específico:
 *       uploads/{entity}/arquivo.jpg
 *
 * O nome do arquivo é sempre aleatório (32 caracteres hex), então mesmo
 * dentro da pasta do usuário não há risco de colisão. No banco, SALVE
 * SEMPRE SÓ O NOME (getFilename()) — nunca o caminho completo. O caminho é
 * sempre reconstruível a partir de entity (+ entityId, se houver), que a
 * aplicação já sabe de onde vem — ver Upload::resolvePath().
 *
 * Uso:
 *   // Com dono (ex: avatar do usuário logado)
 *   $upload = new Upload($_FILES['avatar']);
 *   $upload->forImages(2, 'user', $this->userId());  // uploads/user/{id}/
 *
 *   // Sem dono (ex: banner de página)
 *   $upload = new Upload($_FILES['banner']);
 *   $upload->forImages(3, 'banners');                // uploads/banners/
 *
 *   if ($upload->process()) {
 *       $nome = $upload->getFilename(); // salvar no banco
 *   } else {
 *       $erro = $upload->getFirstError();
 *   }
 *
 * Reconstruindo o caminho depois (exibir/apagar):
 *   Upload::resolvePath('user', $nome, $userId);   // "user/42/a1b2c3....jpg"
 *   Upload::resolvePath('banners', $nome);         // "banners/c3d4e5....jpg"
 */
class Upload
{
    protected array           $file;
    protected array           $errors            = [];
    protected string          $uploadDir         = '';
    protected array           $allowedTypes      = [];
    protected int             $maxSize           = 5 * 1024 * 1024; // 5MB default
    protected array           $allowedExtensions = [];
    protected bool            $randomName        = true;
    protected string          $prefix            = '';
    protected ?string         $filename          = null;
    protected string          $entity            = '';
    protected int|string|null $entityId          = null;

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

    /** Subpasta de categoria (ex: 'user', 'banners', 'documentos') */
    public function setEntity(string $entity): static
    {
        $this->entity = trim($entity, '/');
        return $this;
    }

    /** ID do dono do arquivo (ex: ID do usuário). Opcional — agrupa dentro de entity/{id}/ */
    public function setEntityId(int|string|null $id): static
    {
        $this->entityId = is_string($id) ? trim($id, '/') : $id;
        return $this;
    }

    // ── Presets rápidos ───────────────────────────────────────────────────────

    /**
     * @param int             $maxMb    Tamanho máximo em MB
     * @param string          $entity   Subpasta (ex: 'user', 'banners') — obrigatória
     * @param int|string|null $entityId ID do dono — informe para agrupar em
     *                                  entity/{entityId}/. Omita para uploads
     *                                  sem dono específico (entity/ direto).
     */
    public function forImages(int $maxMb, string $entity, int|string|null $entityId = null): static
    {
        $this->setEntity($entity)->setEntityId($entityId);

        return $this
            ->setAllowedTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])
            ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ->setMaxSize($maxMb * 1024 * 1024);
    }

    /** Configura para aceitar documentos (pdf, doc, docx, xls, xlsx). Mesmos parâmetros de forImages(). */
    public function forDocuments(int $maxMb, string $entity, int|string|null $entityId = null): static
    {
        $this->setEntity($entity)->setEntityId($entityId);

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

        if ($this->entity === '') {
            $this->errors[] = 'Upload mal configurado: entity é obrigatória.';
            return false;
        }

        if (!$this->validateUpload()) return false;

        $targetDir = rtrim($this->uploadDir, '/') . '/' . $this->buildSubPath();

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        $filename = $this->randomName
            ? $this->prefix . bin2hex(random_bytes(16)) . '.' . $ext
            : $this->sanitizeFilename(pathinfo($this->file['name'], PATHINFO_FILENAME)) . '.' . $ext;

        $destination = $targetDir . '/' . $filename;

        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            $this->errors[] = 'Falha ao mover o arquivo. Verifique as permissões do diretório.';
            return false;
        }

        $this->filename = $filename;
        return true;
    }

    /** entity/entityId (se houver dono) ou só entity (sem dono) */
    protected function buildSubPath(): string
    {
        return $this->entityId !== null && $this->entityId !== ''
            ? $this->entity . '/' . $this->entityId
            : $this->entity;
    }

    /**
     * Reconstrói o caminho relativo de um arquivo a partir do nome salvo no
     * banco. Use pra exibir (storageUrl/uploadUrl) ou apagar (unlink).
     */
    public static function resolvePath(string $entity, string $filename, int|string|null $entityId = null): string
    {
        $entity = trim($entity, '/');
        $path   = ($entityId !== null && $entityId !== '') ? "{$entity}/{$entityId}" : $entity;
        return $path . '/' . ltrim($filename, '/');
    }

    // ── Validação ─────────────────────────────────────────────────────────────

    protected function validateUpload(): bool
    {
        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($this->file['error']);
            return false;
        }

        if (!is_uploaded_file($this->file['tmp_name'])) {
            $this->errors[] = 'Arquivo inválido.';
            return false;
        }

        if ($this->file['size'] > $this->maxSize) {
            $max = round($this->maxSize / 1024 / 1024, 1);
            $this->errors[] = "O arquivo excede o tamanho máximo de {$max}MB.";
            return false;
        }

        if ($this->allowedTypes) {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo->file($this->file['tmp_name']);

            if (!in_array($mimeReal, $this->allowedTypes)) {
                $this->errors[] = 'Tipo de arquivo não permitido: ' . $mimeReal;
                return false;
            }
        }

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

    /** Só o nome do arquivo — é sempre isso que você salva no banco */
    public function getFilename(): ?string { return $this->filename; }

    public function getErrors(): array     { return $this->errors; }
    public function hasErrors(): bool      { return !empty($this->errors); }
    public function getFirstError(): ?string { return $this->errors[0] ?? null; }
}