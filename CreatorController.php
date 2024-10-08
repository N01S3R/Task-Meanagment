<?php

namespace App\Controller;

use App\View;
use PDOException;

class CreatorController extends BaseController
{
    /**
     * Wyświetla pulpit twórcy z zadaniami.
     * Sprawdza uprawnienia użytkownika i wyświetla odpowiednią stronę.
     */
    public function displayDashboard(): void
    {
        if ($this->checkRole('creator')) {
            $userId = $this->userModel->getLoggedInUserId();

            // Przygotowanie danych dla pulpit twórcy
            $data = [
                'pageTitle' => 'Pulpit',
                'tasksCount' => count($this->creatorModel->getTasksByUserIdWithProjects($userId)),
                'tasksStart' => count($this->creatorModel->getTasksByProgress(1)),
                'tasksInProgress' => count($this->creatorModel->getTasksByProgress(2)),
                'tasksDone' => count($this->creatorModel->getTasksByProgress(3)),
            ];
            $this->view->render('creator/creator_dashboard', $data);
        } else {
            header('Location: /login');
            exit();
        }
    }

    /**
     * Wyświetla wszystkie zadania użytkownika.
     * Sprawdza czy użytkownik jest zalogowany przed wyświetleniem zadań.
     */
    public function displayAllTasks(): void
    {
        if ($this->checkRole('creator')) {
            $userId = $this->userModel->getLoggedInUserId();
            $tasks = $this->projectModel->getProjectsByUserIdWithTasks($userId);

            $data = [
                'pageTitle' => 'Wszystkie zadania',
                'tasks' => $tasks
            ];

            $this->view->render('creator/creator_all_tasks', $data);
        } else {
            header('Location: /login');
            exit();
        }
    }

    /**
     * Wyświetla zadania na podstawie identyfikatora postępu.
     * @param int $progressId Identyfikator postępu zadania.
     */
    public function displayTasksByProgress($progressId): void
    {
        // Sprawdzenie poprawności identyfikatora postępu
        if ($this->checkRole('creator') && in_array($progressId, [1, 2, 3])) {
            // Przekierowanie na stronę 404 jeśli identyfikator postępu nie jest 1, 2 lub 3


            // Pobranie zadań na podstawie identyfikatora postępu
            $tasks = $this->creatorModel->getTasksByProgress($progressId);

            if (!empty($tasks)) {
                // Definicja kolorów na podstawie identyfikatora postępu
                $colors = [1 => 'danger', 2 => 'warning', 3 => 'success'];

                // Inicjalizacja tablicy grupowanych zadań
                $groupedTasks = [];

                // Grupowanie zadań według nazwy projektu
                foreach ($tasks as $taskItem) {
                    $projectName = $taskItem['project_name'];
                    $projectId = $taskItem['project_id'];

                    if (!isset($groupedTasks[$projectName])) {
                        $groupedTasks[$projectName] = [];
                    }

                    // Dodanie szczegółów zadania do odpowiedniej grupy projektowej
                    $groupedTasks[$projectName][] = [
                        'task_name' => $taskItem['task_name'],
                        'task_description' => $taskItem['task_description'],
                        'project_id' => $projectId
                    ];
                }

                // Przygotowanie danych do renderowania
                $data = [
                    'pageTitle' => 'Zadania w postępie',
                    'groupedTasks' => $groupedTasks,
                    'color' => $colors[$progressId] // Zakładając, że $progressId odpowiada poziomowi postępu
                ];
            } else {
                // Obsługa przypadku braku zadań
                $data = [
                    'pageTitle' => 'Zadania w postępie',
                    'groupedTasks' => [],
                    'color' => ''
                ];
            }

            // Renderowanie widoku z przygotowanymi danymi
            $this->view->render('creator/creator_tasks_progress', $data);
        } else {
            header('Location: /login');
            exit();
        }
    }


    /**
     * Wyświetla formularz do przypisania użytkownika do zadania.
     * Sprawdza czy użytkownik jest zalogowany i pobiera projekty oraz listę użytkowników.
     */
    public function displayDelegateForm(): void
    {
        if ($this->checkRole('creator')) {
            $loggedInUserId = $this->userModel->getLoggedInUserId();
            $projects = $this->projectModel->getProjectsByUserIdWithTasks($loggedInUserId);
            $users = $this->creatorModel->getAllUsersByToken();
            $data = [
                'pageTitle' => 'Przypisz użytkownika',
                'userProjects' => $projects,
                'users' => $users,
            ];
            $this->view->render('creator/creator_delegate', $data);
        } else {
            header('Location: /login');
            exit();
        }
    }

    /**
     * Przypisuje użytkownika do zadania na podstawie żądania AJAX.
     * Sprawdza poprawność żądania i przypisuje użytkownika do zadania.
     */
    public function assignUserToTask(): void
    {
        $responseData = [];

        // Sprawdzenie uprawnień użytkownika
        if (!$this->checkRole('creator')) {
            $responseData['error'] = 'Brak odpowiednich uprawnień do wykonania tej operacji.';
            echo json_encode($responseData);
            return;
        }

        // Przetworzenie danych żądania AJAX
        $requestData = json_decode(file_get_contents('php://input'), true);

        $taskId = $requestData['taskId'] ?? null;
        $userId = $requestData['userId'] ?? null;

        $task = $this->taskModel->getTasksById($taskId);

        if (!$task) {
            $responseData['error'] = 'Zadanie nie znalezione.';
            echo json_encode($responseData);
            return;
        }

        $user = $this->userModel->getUserById($userId);

        if (!$user) {
            $responseData['error'] = "Użytkownik o ID '{$userId}' nie znaleziony.";
            echo json_encode($responseData);
            return;
        }

        $isUserAssigned = $this->taskModel->isUserAssignedToTask($taskId, $userId);

        if ($isUserAssigned) {
            $responseData['error'] = "Użytkownik '{$user['user_login']}' jest już przypisany do zadania " . $task['task_name'];
            echo json_encode($responseData);
            return;
        }

        $assignedUsersCount = $this->creatorModel->getAssignedUsersCount($taskId);

        if ($assignedUsersCount >= 12) {
            $responseData['error'] = "Osiągnięto maksymalną liczbę przypisanych użytkowników do tego zadania.";
            echo json_encode($responseData);
            return;
        }

        // Przypisanie zadania do użytkownika
        $this->taskModel->assignTaskToUser($taskId, $userId);

        $responseData['success'] = 'Użytkownik "' . $user['user_login'] . '" został przypisany do zadania "' . $task['task_name'] . '"';
        $responseData['user'] = [
            'user_id' => $user['user_id'],
            'user_login' => $user['user_login'],
            'user_avatar' => $user['user_avatar']
        ];
        echo json_encode($responseData);
    }


    /**
     * Usuwa przypisanie użytkownika do zadania na podstawie żądania AJAX.
     * Sprawdza poprawność żądania i usuwa przypisanie użytkownika do zadania.
     */
    public function unassignUserFromTask(): void
    {
        $responseData = [];

        if (!$this->checkRole('creator')) {
            $responseData['error'] = 'Brak odpowiednich uprawnień do wykonania tej operacji.';
            echo json_encode($responseData);
            return;
        }

        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        $taskId = $data['taskId'] ?? null;
        $userId = $data['userId'] ?? null;

        $task = $this->taskModel->getTasksById($taskId);
        if (!$task) {
            $responseData['error'] = 'Zadanie nie znalezione.';
            echo json_encode($responseData);
            return;
        }

        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            $responseData['error'] = "Użytkownik o ID '{$userId}' nie znaleziony.";
            echo json_encode($responseData);
            return;
        }

        $this->taskModel->removeUserAssignmentToTask($taskId, $userId);

        $responseData['success'] = 'Przypisanie "' . $task['task_name'] . '" do "' . $user['user_login'] . '" zostało usunięte';

        echo json_encode($responseData);
    }

    /**
     * Wyświetla formularz generowania kodu rejestracyjnego dla użytkownika.
     * Pobiera zalogowanego użytkownika i generuje kod na podstawie jego tokena rejestracyjnego.
     */
    public function displayRegistrationCode(): void
    {
        if ($this->checkRole('creator')) {
            $user = $this->userModel->getLoginUser();
            $generatedToken = $user["registration_token"];
            $users = $this->creatorModel->getAllUsersByToken();
            $data = [
                'pageTitle' => 'Generuj kod użytkownikowi',
                'token' => $generatedToken,
                'users' => $users,
            ];
            $this->view->render('creator/creator_registration_code', $data);
        } else {
            header('Location: /login');
            exit();
        }
    }

    /**
     * Generuje nowy token rejestracyjny dla zalogowanego użytkownika na podstawie podanego tokenu.
     * Tworzy nowy token i zapisuje go w bazie danych użytkownika.
     */
    public function generateToken($token): void
    {
        // Sprawdzenie uprawnień użytkownika
        if (!$this->checkRole('creator')) {
            $response = [
                'success' => false,
                'message' => 'Brak odpowiednich uprawnień do wykonania tej operacji.'
            ];
            echo json_encode($response);
            return;
        }

        $userId = $_SESSION['user_id'];

        // Sprawdzenie liczby istniejących tokenów dla użytkownika
        $tokenCount = $this->creatorModel->getTokenCountByUserId($userId);

        // Jeśli użytkownik ma już 10 tokenów, zwróć odpowiedź JSON
        if ($tokenCount >= 10) {
            $response = [
                'success' => false,
                'message' => 'Użytkownik ma już maksymalną liczbę tokenów.'
            ];
            echo json_encode($response);
            return;
        }

        // Generowanie nowego tokenu (np. za pomocą md5 i microtime)
        $newToken = md5($token . microtime());

        // Zapisz nowy token za pomocą metody setToken z UserModel
        $token = $this->userModel->setToken($userId, $newToken);

        // Przygotowanie odpowiedzi JSON
        $response = [
            'success' => true,
            'token' => $token,
            'message' => 'Token został pomyślnie dodany'
        ];

        // Wyślij odpowiedź JSON
        echo json_encode($response);
    }

    /**
     * Usuwa token rejestracyjny na podstawie jego identyfikatora.
     * Pobiera token, sprawdza jego istnienie i usuwa go z bazy danych użytkownika.
     */
    public function deleteToken($tokenId): void
    {
        // Sprawdzenie uprawnień użytkownika
        if (!$this->checkRole('creator')) {
            $response = [
                'success' => false,
                'message' => 'Brak odpowiednich uprawnień do wykonania tej operacji.'
            ];
            echo json_encode($response);
            return;
        }

        // Pobranie tokena na podstawie jego identyfikatora
        $token = $this->userModel->getTokenById($tokenId);

        // Sprawdzenie czy token istnieje
        if (!$token) {
            // Jeśli token nie istnieje, zwróć odpowiedź JSON z kodem 404
            http_response_code(404);
            echo json_encode(['message' => 'Token nie został znaleziony']);
            return;
        }

        // Usunięcie tokenu z bazy danych
        $this->userModel->deleteToken($tokenId);

        // Zwrócenie odpowiedzi JSON potwierdzającej usunięcie tokenu
        echo json_encode(['message' => 'Token "' . $token['token'] . '" został pomyślnie usunięty']);
    }

    /**
     * Pobiera linki powiązane z zalogowanym użytkownikiem i zwraca je jako dane JSON.
     * Pobiera linki na podstawie id użytkownika z sesji.
     */
    public function getLinks(): void
    {
        // Sprawdzenie uprawnień użytkownika
        if (!$this->checkRole('creator')) {
            $response = [
                'success' => false,
                'message' => 'Brak odpowiednich uprawnień do wykonania tej operacji.'
            ];
            echo json_encode($response);
            return;
        }

        $userId = $_SESSION['user_id'];
        $links = $this->userModel->getLinks($userId);
        echo json_encode(['links' => $links]);
    }
}
