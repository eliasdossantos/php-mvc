<?php

namespace App\Requests\Auth;

use App\Requests\FormRequest;

/**
 * RegisterRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida e sanitiza os dados do formulário de cadastro de usuário.
 *
 * Campos validados:
 *   - name                  → obrigatório, 2–100 chars
 *   - email                 → obrigatório, formato válido, único na tabela users
 *   - password              → obrigatório, mínimo 6 chars
 *   - password_confirmation → deve ser igual a password
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => 'required|min:2|max:100',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                  => 'Informe seu nome.',
            'name.min'                       => 'O nome deve ter pelo menos 2 caracteres.',
            'name.max'                       => 'O nome deve ter no máximo 100 caracteres.',
            'email.required'                 => 'Informe seu e-mail.',
            'email.email'                    => 'O e-mail informado não é válido.',
            'email.unique'                   => 'Este e-mail já está cadastrado.',
            'password.required'              => 'Informe uma senha.',
            'password.min'                   => 'A senha deve ter pelo menos 6 caracteres.',
            'password.confirmed'             => 'A confirmação de senha não confere.',
            'password_confirmation.required' => 'Confirme sua senha.',
        ];
    }

    /**
     * Sanitização:
     *   - name  → ucwords + trim
     *   - email → lowercase + trim
     *   - senhas → trim apenas (preserva caracteres especiais)
     */
    public function sanitize(): array
    {
        return [
            'name'                  => ucwords(mb_strtolower(trim($this->input['name']  ?? ''), 'UTF-8')),
            'email'                 => strtolower(trim($this->input['email'] ?? '')),
            'password'              => trim($this->input['password']              ?? ''),
            'password_confirmation' => trim($this->input['password_confirmation'] ?? ''),
        ];
    }
}
