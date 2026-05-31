<?php

namespace App\Requests\Auth;

use App\Requests\FormRequest;

/**
 * LoginRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida e sanitiza os dados do formulário de login.
 *
 * Campos validados:
 *   - email    → obrigatório, formato de e-mail válido
 *   - password → obrigatório
 *
 * Sanitização customizada:
 *   - email    → lowercase + trim (normaliza antes de buscar no banco)
 *   - password → trim apenas (preserva caracteres especiais)
 */
class LoginRequest extends FormRequest
{
    /**
     * Login é sempre permitido para visitantes (o AuthMiddleware/GuestMiddleware
     * já cuida de bloquear usuários logados na rota).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Informe seu e-mail.',
            'email.email'       => 'O e-mail informado não é válido.',
            'password.required' => 'Informe sua senha.',
        ];
    }

    /**
     * Normaliza o e-mail para lowercase antes de validar.
     * A senha passa apenas por trim — nunca por strip_tags ou htmlspecialchars,
     * para não corromper senhas com caracteres especiais.
     */
    public function sanitize(): array
    {
        return [
            'email'    => strtolower(trim($this->input['email']    ?? '')),
            'password' => trim($this->input['password'] ?? ''),
        ];
    }
}
