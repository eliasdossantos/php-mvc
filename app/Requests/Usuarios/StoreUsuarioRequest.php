<?php

namespace App\Requests\Usuarios;

use App\Requests\FormRequest;
use Core\Auth;
use Core\Request;

/**
 * StoreUsuarioRequest
 * ─────────────────────────────────────────────────────────────────────────────
 * EXEMPLO DE REFERÊNCIA — não há um UserController neste boilerplate que use
 * esta classe. Ela demonstra o padrão de FormRequest para telas de gestão
 * (autorização restrita a admin, validação de perfil) — copie e adapte para
 * qualquer recurso do seu projeto que precise dessa mesma forma de validação.
 *
 * Valida a criação de um novo usuário (painel administrativo).
 *
 * Campos validados:
 *   - nome     → obrigatório, 2–100 chars
 *   - email    → obrigatório, formato válido, único na tabela usuarios
 *   - password → obrigatório, mínimo 6 chars, confirmado
 *   - perfil   → obrigatório, deve ser admin | editor | member
 */
class StoreUsuarioRequest extends FormRequest
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
            'nome'     => 'required|min:2|max:100',
            'email'    => 'required|email|unique:usuarios,email,{id}',
            'password' => 'required|min:6|confirmed',
            'perfil'   => 'required|in:admin,editor,member',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'   => 'O nome é obrigatório.',
            'nome.min'        => 'O nome deve ter pelo menos 2 caracteres.',
            'email.required'  => 'O e-mail é obrigatório.',
            'email.email'     => 'Informe um e-mail válido.',
            'email.unique'    => 'Este e-mail já está em uso.',
            'password.min'    => 'A senha deve ter pelo menos 6 caracteres.',
            'perfil.required' => 'Selecione um perfil para o usuário.',
            'perfil.in'       => 'Perfil inválido. Use: admin, editor ou member.',
        ];
    }

    public function sanitize(): array
    {
        return [
            'nome'                  => ucwords(mb_strtolower(Request::sanitizeValue($this->input['nome'] ?? ''), 'UTF-8')),
            'email'                 => strtolower(trim($this->input['email'] ?? '')),
            'password'              => trim($this->input['password']              ?? ''),
            'password_confirmation' => trim($this->input['password_confirmation'] ?? ''),
            'perfil'                => trim($this->input['perfil'] ?? 'member'),
        ];
    }
}
