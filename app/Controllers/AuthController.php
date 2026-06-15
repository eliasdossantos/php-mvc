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
 * Toda validação e sanitização foi centralizada nos FormRequests.
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
     * Rate limit aplicado na rota: RateLimitMiddleware:login
     */
    public function login(): void
    {
        $request = new LoginRequest();

        if ($request->fails()) {
            Session::flash('error', $request->firstError());
            Session::flashInput(['email' => $request->old('email')]);
            $this->redirect('auth/login');
        }

        $data     = $request->validated();
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        $result   = $this->authService->login($data['email'], $data['password'], $remember);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Session::flashInput(['email' => $data['email']]);
            $this->redirect('auth/login');
        }

        $this->redirect('dashboard');
    }

    /**
     * POST /auth/register
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
     * Rate limit aplicado na rota: RateLimitMiddleware:forgot
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

        // Envia e-mail apenas se existir — sempre exibe a mesma mensagem (anti user-enumeration)
        if ($user) {
            $token = (new PasswordReset())->createToken($email);
            $link  = url('auth/reset-password?token=' . urlencode($token));

            (new Mailer())->send(
                to: $email,
                subject: 'Redefinição de senha',
                body: "<p>Olá, {$user->name}!</p>"
                    . "<p>Clique no link abaixo para redefinir sua senha:</p>"
                    . "<p><a href=\"{$link}\">{$link}</a></p>"
                    . "<p>O link expira em 1 hora.</p>",
                toName: $user->name
            );
        }

        Session::flash('success', 'Se o e-mail estiver cadastrado, você receberá as instruções em breve.');
        $this->redirect('auth/forgot-password');
    }

    /**
     * POST /auth/reset-password
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
