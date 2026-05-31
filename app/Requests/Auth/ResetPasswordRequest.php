<?php

namespace App\Requests\Auth;

use App\Requests\FormRequest;
use App\Models\PasswordReset;

/**
 * ResetPasswordRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida o formulário de redefinição de senha.
 *
 * Campos validados:
 *   - token    → obrigatório e válido (não expirado, não usado)
 *   - password → obrigatório, mínimo 6 chars
 *   - confirm  → deve ser igual a password
 *
 * Regra de negócio: o token é validado contra o banco dentro de authorize(),
 * pois trata-se de uma verificação de permissão — não apenas de formato.
 * Se o token for inválido ou expirado, a requisição é bloqueada com 403.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Verifica se o token é válido antes mesmo de processar os outros campos.
     * Isso evita exibir erros de senha para tokens já expirados.
     */
    public function authorize(): bool
    {
        $token = trim($this->input['token'] ?? '');
        if ($token === '') return false;

        $record = (new PasswordReset())->findValid($token);
        return (bool) $record;
    }

    public function rules(): array
    {
        return [
            'token'    => 'required',
            'password' => 'required|min:6',
            'confirm'  => 'required|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'    => 'Token de redefinição não informado.',
            'password.required' => 'Informe a nova senha.',
            'password.min'      => 'A nova senha deve ter pelo menos 6 caracteres.',
            'confirm.required'  => 'Confirme a nova senha.',
            'confirm.same'      => 'A confirmação de senha não confere.',
        ];
    }

    public function sanitize(): array
    {
        return [
            'token'    => trim($this->input['token']    ?? ''),
            'password' => trim($this->input['password'] ?? ''),
            'confirm'  => trim($this->input['confirm']  ?? ''),
        ];
    }
}
