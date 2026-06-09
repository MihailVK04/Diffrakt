<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\User;
use PDOException;

class AuthController {

    public function register(Request $request): void {
        $data = $request->body() ?? [];

        // if (($data['password'] ?? '') !== ($data['password_again'] ?? '')) {
        //     Response::unprocessable(['password_again' => 'Паролите трябва да съвпадат точно']); 
        // }

        $errors = Validator::validate($data, [
            'username' => ['required', 'min_length:3', 'max_length:40'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min_length:6']
        ]);

        if (!empty($errors)) {
            Response::unprocessable($errors);
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $userId = User::create([
                'username'      => $data['username'],
                'email'         => $data['email'],
                'password_hash' => $passwordHash
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $data['username'];
            $_SESSION['email'] = $data['email'];

            Response::json([
                'message' => 'You sign up successfully.',
                'user'    => ['id' => $userId, 'username' => $data['username'], 'email' => $data['email']]
            ], 201);

        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                Response::conflict('Email or username is already registered');
            }
            throw $e; 
        }
    }

    public function login(Request $request): void {
        $data = $request->body() ?? [];
        $errors = Validator::validate($data, ['email' => ['required', 'email'], 'password' => ['required']]);

        if (!empty($errors)) Response::unprocessable($errors);

        // ФИКС: Използваме новия, сигурен метод за извличане на потребител с парола
        $user = User::findForAuth($data['email']);

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            Response::unauthorized('Wrong email or password.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email']; 

        Response::json([
            'message' => 'Login successful.',
            'user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']]
        ]);
    }

    public function logout(Request $request): void {
        $_SESSION = [];
        session_destroy();

        // ФИКС: Връщаме правилното изчистване на бисквитката от браузъра
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        Response::json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): void {
        $user = User::findById($request->userId());
        if (!$user) Response::notFound('User not found.');
        Response::json(['user' => $user]);
    }
}