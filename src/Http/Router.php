<?php

declare(strict_types=1);

namespace App\Http;

use App\Services\AuthService;

/**
 * Routage minimal : méthode + chemin, rôles autorisés, jeton CSRF.
 * Les contrôleurs ne contiennent ni SQL ni règle de routage.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:callable,roles:?array,csrf:bool}> */
    private array $routes = [];

    /** @var callable():AuthService */
    private $authFactory;

    private ?AuthService $auth = null;

    /** @param callable():AuthService $authFactory */
    public function __construct(callable $authFactory)
    {
        $this->authFactory = $authFactory;
    }

    private function auth(): AuthService
    {
        return $this->auth ??= ($this->authFactory)();
    }

    /** @param array<int,string>|null $roles null = public, [] = tout compte connecté */
    public function add(
        string $method,
        string $pattern,
        callable $handler,
        ?array $roles = null,
        bool $csrf = true
    ): void {
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>\d+)',
            $pattern
        );
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
            'roles'   => $roles,
            'csrf'    => $csrf,
        ];
    }

    public function dispatch(Request $request): never
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $user = null;
            if ($route['roles'] !== null) {
                /** @var array<string,mixed> $user */
                $user = $this->auth()->requireRole($route['roles']);
            }

            if ($route['csrf'] && in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
                $token = $request->header('X-CSRF-Token');
                if (!\App\Support\Csrf::verify($token)) {
                    throw new ApiException(403, 'Jeton CSRF invalide ou absent.');
                }
            }

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $request->setParam($key, $value);
                }
            }

            ($route['handler'])($request, $user);
            Response::error(500, 'Le contrôleur n\'a pas produit de réponse.');
        }

        throw new ApiException(404, 'Ressource introuvable.');
    }
}
