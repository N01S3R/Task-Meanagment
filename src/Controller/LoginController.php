<?php

namespace App\Controller;

class LoginController extends BaseController
{
    /**
     * Wyświetla formularz logowania lub przekierowuje zalogowanych użytkowników.
     * 
     * @return void
     */
    public function index(): void
    {
        if ($this->auth->getUserId()) {
            // Sprawdzanie, czy użytkownik jest już zalogowany
            $userRole = $this->auth->getUserRole();
            $redirectUrl = getenv('BASE_URL') . $userRole . '/dashboard';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            // Renderowanie formularza logowania
            $this->render('login_form');
        }
    }

    /**
     * Przetwarza dane logowania użytkownika.
     * 
     * @return void
     */
    public function login(): void
    {
        if (!$this->auth->getUserId()) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $user = $this->auth->login($username, $password);

            if ($user) {
                $userRole = $user->getRole();
                $redirectUrl = getenv('BASE_URL') . $userRole . '/dashboard';
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                // Obsługa błędnego logowania
                $this->render('login_form', ['error' => 'Niepoprawne dane']);
            }
        } else {
            // Użytkownik już zalogowany, przekieruj na stronę główną
            $this->index();
        }
    }

    /**
     * Wylogowuje użytkownika i przekierowuje na stronę logowania.
     * 
     * @return void
     */
    public function logout(): void
    {
        $this->auth->logout();

        header('Location: /login');
        exit();
    }
}
