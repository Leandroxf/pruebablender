<?php

class AuthController extends Controller
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 72;

    public function __construct(private Usuario $usuarios = new Usuario())
    {
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            $this->json(['error' => 'Método no permitido.'], 405);
            return;
        }

        try {
            if (Auth::isLoggedIn()) {
                $this->json(['error' => 'Ya hay una sesión activa. Cierra sesión antes de crear otra cuenta.'], 409);
                return;
            }
            if (!Auth::checkCsrf('register_csrf', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
                $this->json(['error' => 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'], 403);
                return;
            }

            try {
                $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new InvalidArgumentException('La solicitud de registro no contiene JSON válido.', 0, $error);
            }
            if (!is_array($data)) {
                throw new InvalidArgumentException('La solicitud de registro tiene un formato inválido.');
            }

            $nombre = trim((string)($data['name'] ?? ''));
            $correo = trim((string)($data['email'] ?? ''));
            $password = (string)($data['password'] ?? '');
            $this->validate($nombre, $correo, $password);

            if ($this->usuarios->emailExists($correo)) {
                throw new InvalidArgumentException('Ya existe una cuenta con ese correo electrónico. Probá iniciar sesión.');
            }
            if ($this->usuarios->nameExists($nombre)) {
                throw new InvalidArgumentException('Ya existe una cuenta con ese nombre de usuario. Elegí otro.');
            }

            $user = $this->usuarios->create($nombre, $correo, $password);
            Auth::loginUser($user['id'], $user['nombre'], $user['rol']);

            $this->json([
                'usuario' => ['id' => $user['id'], 'nombre' => $user['nombre'], 'correo' => $user['correo']],
                'mensaje' => '¡Cuenta creada! Bienvenido, ' . $user['nombre'] . '.',
            ], 201);
        } catch (InvalidArgumentException $error) {
            $this->json(['error' => $error->getMessage()], 422);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $this->json(['error' => 'No se pudo crear la cuenta. Inténtalo de nuevo más tarde.'], 500);
        }
    }

    private function validate(string $nombre, string $correo, string $password): void
    {
        $nameLength = preg_match_all('/./us', $nombre);
        if ($nameLength < 2 || $nameLength > 100) {
            throw new InvalidArgumentException('El nombre debe tener entre 2 y 100 caracteres.');
        }
        if (preg_match_all('/./us', $correo) > 150 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Ingresá un correo electrónico válido.');
        }
        $passwordLength = strlen($password);
        if ($passwordLength < self::MIN_PASSWORD_LENGTH || $passwordLength > self::MAX_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('La contraseña debe tener entre 8 y 72 caracteres.');
        }
    }
}
