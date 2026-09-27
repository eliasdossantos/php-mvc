<?php

namespace App\Controllers;

use Framework\Controller;
use Framework\Upload;

abstract class BaseController extends Controller
{
    /** Erros do último upload processado */
    protected array $uploadErrors = [];

    /**
     * Processa um upload novo.
     *
     * @param int|string|null $entityId Informe para agrupar por dono
     *                                  (ex: ID do usuário → user/{id}/).
     *                                  Omita para uploads sem dono (ex: banner).
     * @return string|null Nome do arquivo salvo, ou null se falhou
     */
    protected function saveUploadedFile(
        array $file,
        string $entity,
        string $type = 'image',
        int $maxMb = 5,
        int|string|null $entityId = null
    ): ?string {
        $this->uploadErrors = [];

        $upload = new Upload($file);

        $type === 'document'
            ? $upload->forDocuments($maxMb, $entity, $entityId)
            : $upload->forImages($maxMb, $entity, $entityId);

        if (!$upload->process()) {
            $this->uploadErrors = $upload->getErrors();
            return null;
        }

        return $upload->getFilename();
    }

    /**
     * Substitui um arquivo existente. Se o novo upload falhar, o antigo
     * NÃO é apagado.
     *
     * @param string|null $oldFilename Nome do arquivo antigo salvo no banco
     * @return string|null Nome do novo arquivo, ou null se falhou
     */
    protected function replaceUploadedFile(
        array $file,
        string $entity,
        ?string $oldFilename,
        string $type = 'image',
        int $maxMb = 5,
        int|string|null $entityId = null
    ): ?string {
        $novoNome = $this->saveUploadedFile($file, $entity, $type, $maxMb, $entityId);

        if ($novoNome === null) {
            return null;
        }

        if ($oldFilename) {
            $this->deleteUploadedFile($entity, $oldFilename, $entityId);
        }

        return $novoNome;
    }

    /** Remove um arquivo físico a partir de entity (+ entityId) + nome salvo no banco */
    protected function deleteUploadedFile(string $entity, string $filename, int|string|null $entityId = null): void
    {
        $relative = Upload::resolvePath($entity, $filename, $entityId);
        $fullPath = STORAGE_PATH . '/uploads/' . $relative;

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}