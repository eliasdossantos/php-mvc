<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\User;
use App\Models\PasswordReset;
use App\Services\AuthService;
use App\Helpers\Mailer;
use App\Requests\Auth\LoginRequest;
use App\Requests\Auth\RegisterRequest;
use App\Requests\Auth\ForgotPasswordRequest;
use App\Requests\Auth\ResetPasswordRequest;

/**
 * AuthController
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsável apenas por:
 *   - Exibir os formulários de autenticação
 *   - Receber as requisições e delegar para AuthService
 *
 * Toda validação e sanitização foi removida daqui e centralizada
 * nos FormRequests em app/Requests/Auth/.
 *
 * Fluxo de cada action POST:
 *   1. Instancia o Request → valida automaticamente no construtor
 *   2. Se falhar: salva erros na sessão, redireciona de volta
 *   3. Se passar: usa $request->validated() para dados limpos
 */
class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    // ── Formulários ───────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        $this->view('auth.login', ['title' => 'Entrar'], 'auth');
    }

    public function registerForm(): void
    {
        $this->view('auth.register', ['title' => 'Criar Conta'], 'auth');
    }

    public function forgotForm(): void
    {
        $this->view('auth.forgot', ['title' => 'Recuperar Senha'], 'auth');
    }

    public function resetForm(): void
    {
        $token  = $_GET['token'] ?? '';
        $record = (new PasswordReset())->findValid($token);

        if (!$record) {
            Session::flash('error', 'Link inválido ou expirado.');
            $this->redirect('auth/forgot-password');
        }

        $this->view('auth.reset', ['title' => 'Nova Senha', 'token' => $token], 'auth');
    }

    // ── Actions POST ──────────────────────────────────────────────────────────

    /**
     * POST /auth/login
     * Validação: LoginRequest (email obrigatório+válido, password obrigatório)
     */
    public function login(): void
    {
        $request = new LoginRequest();

        if ($request->fails()) {
            Session::flash('error', $request->firstError());
            Session::flashInput(['email' => $request->old('email')]);
            $this->redirect('auth/login');
        }

        $data   = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Session::flashInput(['email' => $data['email']]);
            $this->redirect('auth/login');
        }

        $this->redirect('dashboard');
    }

    /**
     * POST /auth/register
     * Validação: RegisterRequest (name, email único, password confirmada)
     */
    public function register(): void
    {
        $request = new RegisterRequest();

        if ($request->fails()) {
            Session::flash('error', $request->firstError());
            Session::flashInput([
                'name'  => $request->old('name'),
                'email' => $request->old('email'),
            ]);
            $this->redirect('auth/register');
        }

        $result = $this->authService->register($request->validated());

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Session::flashInput([
                'name'  => $request->old('name'),
                'email' => $request->old('email'),
            ]);
            $this->redirect('auth/register');
        }

        Session::flash('success', 'Conta criada com sucesso! Faça o login.');
        $this->redirect('auth/login');
    }

    /**
     * GET /auth/logout
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('auth/login');
    }

    /**
     * POST /auth/forgot-password
     * Validação: ForgotPasswordRequest (email obrigatório+válido)
     */
    public function forgotSend(): void
    {
        $request = new ForgotPasswordRequest();

        if ($request->fails()) {
            Session::flash('error', $request->firstError());
            Session::flashInput(['email' => $request->old('email')]);
            $this->redirect('auth/forgot-password');
        }

        $email = $request->get('email');
        $user  = (new User())->findByEmail($email);

        // Envia e-mail apenas se o usuário existir —
        // mas sempre exibe a mesma mensagem (evita user enumeration)
        if ($user) {
            $token = (new PasswordReset())->createToken($email);
            $link  = url('auth/reset-password?token=' . urlencode($token));

            (new Mailer())->send(
                to:      $email,
                subject: 'Redefinição de senha',
                body:    "<p>Olá, {$user->name}!</p>"
                       . "<p>Clique no link abaixo para redefinir sua senha:</p>"
                       . "<p><a href=\"{$link}\">{$link}</a></p>"
                       . "<p>O link expira em 1 hora.</p>",
                toName:  $user->name
            );
        }

        Session::flash('success', 'Se o e-mail estiver cadastrado, você receberá as instruções em breve.');
        $this->redirect('auth/forgot-password');
    }

    /**
     * POST /auth/reset-password
     * Validação: ResetPasswordRequest (token válido, password+confirm)
     *
     * ── BUG CORRIGIDO #10 ────────────────────────────────────────────────────
     * Antes: o controller executava a query de UPDATE diretamente sobre o PDO,
     * fazendo password_hash() inline — duplicando a lógica que já existe em
     * User::updatePassword(). Isso viola o princípio DRY e significa que se
     * o custo do bcrypt for alterado em User::updatePassword(), o reset de senha
     * continuaria usando o valor antigo hardcoded aqui.
     *
     * Além disso, o campo 'confirm' do ResetPasswordRequest (diferente de
     * 'password_confirmation' do RegisterRequest) era usado em validated(), mas
     * o campo do formulário de reset usa 'confirm' como nome — o que estava
     * correto no Request, mas o controller antigo não documentava essa diferença,
     * tornando-o confuso.
     *
     * Solução: delegar a atualização de senha para User::updatePassword(),
     * centralizando a lógica de hashing em um único lugar.
     */
    public function resetSave(): void
    {
        $request = new ResetPasswordRequest();

        if ($request->fails()) {
            $token = $request->old('token');
            Session::flash('error', $request->firstError());
            $this->redirect('auth/reset-password?token=' . urlencode($token));
        }

        $data       = $request->validated();
        $resetModel = new PasswordReset();
        $record     = $resetModel->findValid($data['token']);

        // Busca o usuário pelo e-mail do token e atualiza a senha via model
        // (centraliza o hashing em User::updatePassword)
        $user = (new User())->findByEmail($record->email);

        if (!$user) {
            Session::flash('error', 'Usuário não encontrado. Solicite um novo link.');
            $this->redirect('auth/forgot-password');
        }

        (new User())->updatePassword((int) $user->id, $data['password']);

        $resetModel->consume($data['token']);

        Session::flash('success', 'Senha redefinida com sucesso! Faça o login.');
        $this->redirect('auth/login');
    }
}