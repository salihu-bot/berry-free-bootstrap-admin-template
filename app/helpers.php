<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please sign in to continue.');
        redirect_to('?page=login');
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function ticket_for_user(PDO $pdo, int $ticketId, array $user): ?array
{
    $sql = 'SELECT t.*, u.full_name AS student_name, u.matric_number, u.email AS student_email,
                   s.name AS school_name, d.name AS department_name
            FROM tickets t
            JOIN users u ON u.id = t.student_id
            LEFT JOIN schools s ON s.id = u.school_id
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE t.id = ?';
    $parameters = [$ticketId];
    if ($user['role'] === 'student') {
        $sql .= ' AND t.student_id = ?';
        $parameters[] = $user['id'];
    } elseif ($user['role'] === 'department') {
        $sql .= ' AND t.department_id = ?';
        $parameters[] = $user['department_id'];
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetch() ?: null;
}

function status_class(string $status): string
{
    return match ($status) {
        'Submitted' => 'status-submitted',
        'Under review' => 'status-review',
        'In progress' => 'status-progress',
        'Resolved' => 'status-resolved',
        'Closed' => 'status-closed',
        default => 'status-submitted',
    };
}

function format_date(string $date): string
{
    return date('M j, Y · g:i a', strtotime($date));
}
