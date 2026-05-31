<?php

namespace App\Requests\Users;

use App\Requests\FormRequest;
use Core\Auth;
use Core\Session;

/**
 * UpdateUserRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * Valida a atualização de dados de um usuário.
 *
 * Campos validados:
 *   - name     → obrigatório, 2–100 chars
 *   - email    → obrigatório, válido, único (ignorando o próprio ID do usuário)
 *   - password → opcional (nullable); se informado, mínimo 6 + confirmado
 *   - role     → obrigatório apenas para admins
 *
 * Regra de autorização:
 *   - Admin pode editar qualquer usuário
 *   - Usuário comum pode editar apenas a si próprio
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!Auth::check()) return false;

        // Admin pode editar qualquer um
        if (Auth::is('admin')) return true;

        // Usuário comum só pode editar a si próprio
        // O ID do usuário sendo editado deve ser passado via input hidden 'user_id'
        $targetId = (int) ($this->input['user_id'] ?? 0);
        return $targetId === Auth::id();
    }

    public function rules(): array
    {
        // unique ignora o registro do próprio usuário
        $userId = (int) ($this->input['user_id'] ?? Auth::id() ?? 0);

        $rules = [
            'name'  => 'required|min:2|max:100',
            'email' => "required|email|unique:users,email,{$userId}",
        ];

        // Senha é opcional na atualização
        if (!empty(trim($this->input['password'] ?? ''))) {
            $rules['password'] = 'required|min:6|confirmed';
        }

        // Role só é validada se o admin enviar o campo
        if (Auth::is('admin') && isset($this->input['role'])) {
            $rules['role'] = 'required|in:admin,editor,member';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'O nome é obrigatório.',
            'name.min'       => 'O nome deve ter pelo menos 2 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email'    => 'Informe um e-mail válido.',
            'email.unique'   => 'Este e-mail já está em uso por outro usuário.',
            'password.min'   => 'A nova senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'role.in'        => 'Perfil inválido.',
        ];
    }

    public function sanitize(): array
    {
        $data = [
            'user_id' => (int) ($this->input['user_id'] ?? 0),
            'name'    => ucwords(mb_strtolower(trim($this->input['name']  ?? ''), 'UTF-8')),
            'email'   => strtolower(trim($this->input['email'] ?? '')),
        ];

        // Inclui senha apenas se foi preenchida
        if (!empty(trim($this->input['password'] ?? ''))) {
            $data['password']              = trim($this->input['password']              ?? '');
            $data['password_confirmation'] = trim($this->input['password_confirmation'] ?? '');
        }

        if (isset($this->input['role'])) {
            $data['role'] = trim($this->input['role'] ?? 'member');
        }

        return $data;
    }
}
