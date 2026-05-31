<?php

namespace App\Requests\Users;

use App\Requests\FormRequest;
use Core\Auth;

/**
 * StoreUserRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida a criação de um novo usuário (painel administrativo).
 *
 * Campos validados:
 *   - name     → obrigatório, 2–100 chars
 *   - email    → obrigatório, formato válido, único na tabela users
 *   - password → obrigatório, mínimo 6 chars, confirmado
 *   - role     → obrigatório, deve ser admin | editor | member
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Apenas administradores podem criar usuários pelo painel.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::is('admin');
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role'     => 'required|in:admin,editor,member',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'O nome é obrigatório.',
            'name.min'       => 'O nome deve ter pelo menos 2 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email'    => 'Informe um e-mail válido.',
            'email.unique'   => 'Este e-mail já está em uso.',
            'password.min'   => 'A senha deve ter pelo menos 6 caracteres.',
            'role.required'  => 'Selecione um perfil para o usuário.',
            'role.in'        => 'Perfil inválido. Use: admin, editor ou member.',
        ];
    }

    public function sanitize(): array
    {
        return [
            'name'                  => ucwords(mb_strtolower(trim($this->input['name']  ?? ''), 'UTF-8')),
            'email'                 => strtolower(trim($this->input['email'] ?? '')),
            'password'              => trim($this->input['password']              ?? ''),
            'password_confirmation' => trim($this->input['password_confirmation'] ?? ''),
            'role'                  => trim($this->input['role'] ?? 'member'),
        ];
    }
}
