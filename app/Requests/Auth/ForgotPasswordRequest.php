<?php

namespace App\Requests\Auth;

use App\Requests\FormRequest;

/**
 * ForgotPasswordRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida o formulário de solicitação de recuperação de senha.
 *
 * Campos validados:
 *   - email → obrigatório, formato válido
 *
 * Nota intencional: NÃO validamos se o e-mail existe no banco (exists:users,email).
 * Isso é deliberado para não revelar quais e-mails estão cadastrados no sistema
 * (prevenção de user enumeration). A verificação de existência é feita
 * silenciosamente no AuthController após esta validação passar.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Informe seu e-mail.',
            'email.email'    => 'O e-mail informado não é válido.',
        ];
    }

    public function sanitize(): array
    {
        return [
            'email' => strtolower(trim($this->input['email'] ?? '')),
        ];
    }
}
