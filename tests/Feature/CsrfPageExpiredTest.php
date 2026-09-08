<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class CsrfPageExpiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_expired_session_message(): void
    {
        $message = 'Sua sessão expirou. Atualize a página e tente de novo.';

        $this->withSession(['message' => $message])
            ->get(route('login'))
            ->assertOk()
            ->assertSee($message, false);
    }

    public function test_csrf_mismatch_redirects_back_with_expired_message(): void
    {
        $this->get(route('login'))->assertOk();

        $request = Request::create('/login', 'POST', server: [
            'HTTP_REFERER' => route('login'),
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);

        $rendered = $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException);
        $response = $this->createTestResponse($rendered, $request);

        $response->assertRedirectBack();
        $response->assertSessionHas('message', 'Sua sessão expirou. Atualize a página e tente de novo.');
    }

    public function test_csrf_mismatch_json_request_returns_419_message(): void
    {
        $request = Request::create('/aluno/avisos/ler', 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->headers->set('Accept', 'application/json');
        $this->app->instance('request', $request);

        $rendered = $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException);
        $response = $this->createTestResponse($rendered, $request);

        $response->assertStatus(419);
        $response->assertJsonPath('message', 'Sua sessão expirou. Atualize a página e tente de novo.');
    }
}
