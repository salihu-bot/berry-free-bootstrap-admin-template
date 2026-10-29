<?php
declare(strict_types=1);

function handle_action(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals(csrf_token(), $submittedToken)) {
        http_response_code(419);
        exit('Your session token expired. Please go back, refresh, and try again.');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('danger', 'The email or password you entered is incorrect.');
            redirect_to('?page=login');
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'] === null ? null : (int) $user['department_id'],
        ];
        redirect_to('?page=dashboard');
    }

    if ($action === 'register') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $matric = strtoupper(trim((string) ($_POST['matric_number'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
        if ($fullName === '' || strlen($fullName) > 150 || $matric === '' || strlen($matric) > 50
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $email)
            || strlen($password) < 8 || !$schoolId) {
            flash('danger', 'Enter your full name, matric number, Gmail address, school and a password of at least 8 characters.');
            redirect_to('?page=register');
        }
        $schoolQuery = $pdo->prepare('SELECT id FROM schools WHERE id = ?');
        $schoolQuery->execute([$schoolId]);
        if (!$schoolQuery->fetchColumn()) {
            flash('danger', 'Select a valid school.');
            redirect_to('?page=register');
        }
        try {
            $statement = $pdo->prepare(
                "INSERT INTO users (full_name, email, matric_number, password_hash, role, school_id)
                 VALUES (?, ?, ?, ?, 'student', ?)"
            );
            $statement->execute([$fullName, $email, $matric, password_hash($password, PASSWORD_DEFAULT), $schoolId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('danger', 'That Gmail address or matric number is already registered.');
                redirect_to('?page=register');
            }
            throw $exception;
        }
        flash('success', 'Your student account is ready. Sign in to submit a support request.');
        redirect_to('?page=login');
    }

    if ($action === 'setup_admin') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount > 0) {
            flash('warning', 'The administrator account has already been created.');
            redirect_to('?page=login');
        }
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($fullName === '' || strlen($fullName) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            flash('danger', 'Enter a name, valid email address, and password with at least 12 characters.');
            redirect_to('?page=setup');
        }
        $statement = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
        );
        $statement->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Administrator created. You can now sign in.');
        redirect_to('?page=login');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'You have been signed out.');
        redirect_to('?page=login');
    }

    if ($action === 'create_ticket') {
        $user = require_role(['student']);
        $topic = trim((string) ($_POST['topic'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $topics = ['Academic concern', 'Student welfare', 'Hostel and facilities', 'ICT and portal', 'Finance and fees', 'Safety and conduct', 'Other'];
        if (!in_array($topic, $topics, true) || $subject === '' || strlen($subject) > 180
            || strlen($description) < 20 || strlen($description) > 10000) {
            flash('danger', 'Choose a topic, add a subject, and describe your concern in at least 20 characters.');
            redirect_to('?page=new-ticket');
        }
        $reference = 'FCWM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(10)));
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            "INSERT INTO tickets (reference_no, student_id, topic, subject, description, status)
             VALUES (?, ?, ?, ?, ?, 'Submitted')"
        );
        $statement->execute([$reference, $user['id'], $topic, $subject, $description]);
        $ticketId = (int) $pdo->lastInsertId();
        $update = $pdo->prepare(
            "INSERT INTO ticket_updates (ticket_id, author_id, status, message)
             VALUES (?, ?, 'Submitted', 'Your support request has been received.')"
        );
        $update->execute([$ticketId, $user['id']]);
        $pdo->commit();
        flash('success', 'Your request was submitted. Keep your reference number: ' . $reference);
        redirect_to('?page=ticket&id=' . $ticketId);
    }

    if ($action === 'route_ticket') {
        require_role(['admin']);
        $ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$ticketId || !$departmentId) {
            flash('danger', 'Choose a valid ticket and department.');
            redirect_to('?page=tickets');
        }
        $departmentQuery = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
        $departmentQuery->execute([$departmentId]);
        $departmentName = $departmentQuery->fetchColumn();
        if (!$departmentName) {
            flash('danger', 'The selected department does not exist.');
            redirect_to('?page=tickets');
        }
        $pdo->beginTransaction();
        $ticketQuery = $pdo->prepare('SELECT status FROM tickets WHERE id = ? FOR UPDATE');
        $ticketQuery->execute([$ticketId]);
        $ticket = $ticketQuery->fetch();
        if (!$ticket) {
            $pdo->rollBack();
            flash('danger', 'Ticket not found.');
            redirect_to('?page=tickets');
        }
        $updateTicket = $pdo->prepare('UPDATE tickets SET department_id = ? WHERE id = ?');
        $updateTicket->execute([$departmentId, $ticketId]);
        $history = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, author_id, status, message) VALUES (?, ?, ?, ?)');
        $history->execute([$ticketId, current_user()['id'], $ticket['status'], 'Assigned to ' . $departmentName . '.']);
        $pdo->commit();
        flash('success', 'Ticket routed to ' . $departmentName . '.');
        redirect_to('?page=ticket&id=' . $ticketId);
    }

    if ($action === 'update_ticket') {
        $user = require_role(['admin', 'department']);
        $ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
        $status = trim((string) ($_POST['status'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $statuses = ['Submitted', 'Under review', 'In progress', 'Resolved', 'Closed'];
        if (!$ticketId || !in_array($status, $statuses, true) || $message === '' || strlen($message) > 3000) {
            flash('danger', 'Select a valid status and provide an update (up to 3,000 characters).');
            redirect_to('?page=tickets');
        }
        $ticket = ticket_for_user($pdo, $ticketId, $user);
        if (!$ticket) {
            http_response_code(404);
            exit('Ticket not found.');
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare('UPDATE tickets SET status = ? WHERE id = ?');
        $statement->execute([$status, $ticketId]);
        $history = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, author_id, status, message) VALUES (?, ?, ?, ?)');
        $history->execute([$ticketId, $user['id'], $status, $message]);
        $pdo->commit();
        flash('success', 'The ticket has been updated.');
        redirect_to('?page=ticket&id=' . $ticketId);
    }

    if ($action === 'create_department_user') {
        require_role(['admin']);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        if ($fullName === '' || strlen($fullName) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12 || !$departmentId) {
            flash('danger', 'Enter a name, valid email, password of at least 12 characters, and department.');
            redirect_to('?page=departments');
        }
        $departmentQuery = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
        $departmentQuery->execute([$departmentId]);
        if (!$departmentQuery->fetchColumn()) {
            flash('danger', 'Select a valid department.');
            redirect_to('?page=departments');
        }
        try {
            $statement = $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, department_id)
                 VALUES (?, ?, ?, 'department', ?)"
            );
            $statement->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $departmentId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('danger', 'That email address is already in use.');
                redirect_to('?page=departments');
            }
            throw $exception;
        }
        flash('success', 'Department account created. Share the sign-in details securely with its owner.');
        redirect_to('?page=departments');
    }

    http_response_code(400);
    exit('Invalid request.');
}
