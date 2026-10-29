<?php
declare(strict_types=1);

function render_start(string $title, string $activePage, ?array $user): void
{
    $role = $user['role'] ?? 'guest';
    $roleLabels = ['student' => 'Student portal', 'department' => 'Department desk', 'admin' => 'Administration', 'guest' => 'Student support'];
    $navByRole = [
        'student' => [
            ['dashboard', 'Overview', 'ti ti-layout-dashboard'],
            ['tickets', 'My requests', 'ti ti-ticket'],
            ['new-ticket', 'Create a request', 'ti ti-circle-plus'],
            ['track', 'Track a reference', 'ti ti-search'],
        ],
        'department' => [
            ['dashboard', 'Department overview', 'ti ti-layout-dashboard'],
            ['tickets', 'Assigned cases', 'ti ti-inbox'],
        ],
        'admin' => [
            ['dashboard', 'Overview', 'ti ti-layout-dashboard'],
            ['tickets', 'All cases', 'ti ti-ticket'],
            ['departments', 'Departments', 'ti ti-building-community'],
        ],
        'guest' => [
            ['login', 'Sign in', 'ti ti-login'],
            ['register', 'Student registration', 'ti ti-user-plus'],
            ['track', 'Track a request', 'ti ti-search'],
        ],
    ];
    $navItems = $navByRole[$role] ?? $navByRole['guest'];
    $flashMessage = take_flash();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#111827">
      <title><?= e($title) ?> · FCWM Support</title>
      <link rel="icon" href="dist/assets/images/favicon.svg" type="image/svg+xml">
      <link rel="stylesheet" href="dist/assets/css/plugins/bootstrap.min.css">
      <link rel="stylesheet" href="dist/assets/fonts/tabler-icons.min.css">
      <link rel="stylesheet" href="app/assets/css/system.css?v=5">
    </head>
    <body>
    <div class="app-shell">
      <aside class="sidebar sidebar-<?= e($role) ?>">
        <a class="brand" href="?page=<?= $user ? 'dashboard' : 'login' ?>">
          <span class="brand-mark"><i class="ti ti-leaf"></i></span>
          <span><strong>FCWM</strong><small>Support Centre</small></span>
        </a>
        <div class="sidebar-label"><?= e($roleLabels[$role] ?? 'Student support') ?></div>
        <nav class="side-nav" aria-label="Main navigation">
          <?php foreach ($navItems as [$page, $label, $icon]): ?>
            <a class="side-link <?= $activePage === $page ? 'active' : '' ?>" href="?page=<?= e($page) ?>" aria-label="<?= e($label) ?>">
              <i class="<?= e($icon) ?>"></i><span><?= e($label) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <?php if ($user): ?>
          <div class="sidebar-note">
            <span class="note-icon"><i class="ti ti-shield-check"></i></span>
            <strong>Your voice matters</strong>
            <p>Every request is tracked and directed to the right team.</p>
          </div>
        <?php else: ?>
          <div class="sidebar-note">
            <span class="note-icon"><i class="ti ti-clock"></i></span>
            <strong>Need help?</strong>
            <p>Submit a request or track a case using its reference number.</p>
          </div>
        <?php endif; ?>
        <div class="sidebar-bottom">
          <span class="online-dot"></span> FCWM Support System
          <small>Federal College of Wildlife Management</small>
        </div>
      </aside>
      <div class="main-area">
        <header class="topbar">
          <div class="topbar-heading">
            <span><?= e($roleLabels[$role] ?? 'Student support') ?></span>
            <strong><?= e($title) ?></strong>
          </div>
          <div class="topbar-user">
            <span class="avatar"><?= $user ? e(strtoupper(substr($user['full_name'], 0, 1))) : '<i class="ti ti-user"></i>' ?></span>
            <div class="user-details">
              <strong><?= $user ? e($user['full_name']) : 'Welcome' ?></strong>
              <small><?= $user ? e(ucfirst($role)) : 'Guest access' ?></small>
            </div>
            <?php if ($user): ?>
              <form method="post" class="logout-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="logout">
                <button class="icon-button" type="submit" aria-label="Sign out" title="Sign out"><i class="ti ti-logout"></i></button>
              </form>
            <?php else: ?>
              <a class="topbar-signin" href="?page=login">Sign in <i class="ti ti-arrow-up-right"></i></a>
            <?php endif; ?>
          </div>
        </header>
        <main class="content-area">
          <?php if ($flashMessage): ?>
            <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
              <?= e($flashMessage['message']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>
    <?php
}

function render_end(): void
{
    $user = current_user();
    $footerText = match ($user['role'] ?? 'guest') {
        'admin' => 'Administration · College-wide case oversight',
        'department' => 'Department workspace · Assigned case follow-up',
        'student' => 'Student portal · Your requests and case updates',
        default => 'Student access · Sign in or track a request',
    };
    ?>
        </main>
        <footer class="app-footer">
          <span>© <?= date('Y') ?> Federal College of Wildlife Management</span>
          <span><?= e($footerText) ?> <i class="ti ti-leaf"></i></span>
        </footer>
      </div>
    </div>
    <script src="dist/assets/js/plugins/bootstrap.min.js"></script>
    </body>
    </html>
    <?php
}

function page_heading(string $eyebrow, string $title, string $description, ?string $actionLabel = null, ?string $actionUrl = null): void
{
    ?>
    <div class="page-heading">
      <div><span class="eyebrow"><?= e($eyebrow) ?></span><h1><?= e($title) ?></h1><p><?= e($description) ?></p></div>
      <?php if ($actionLabel && $actionUrl): ?><a href="<?= e($actionUrl) ?>" class="btn btn-forest"><i class="ti ti-plus"></i> <?= e($actionLabel) ?></a><?php endif; ?>
    </div>
    <?php
}

function render_ticket_rows(array $tickets, string $emptyTitle, string $emptyText): void
{
    if (!$tickets) {
        echo '<div class="empty-state"><span class="empty-icon"><i class="ti ti-inbox"></i></span><strong>' . e($emptyTitle) . '</strong><p>' . e($emptyText) . '</p></div>';
        return;
    }
    ?>
    <div class="table-responsive">
      <table class="table ticket-table align-middle mb-0">
        <thead><tr><th>Reference</th><th>Request</th><th>Student</th><th>Department</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><span class="reference"><?= e($ticket['reference_no']) ?></span></td>
            <td><strong class="row-title"><?= e($ticket['subject']) ?></strong><small><?= e($ticket['topic']) ?></small></td>
            <td><?= e($ticket['student_name'] ?? 'You') ?><?php if (!empty($ticket['matric_number'])): ?><small><?= e($ticket['matric_number']) ?></small><?php endif; ?></td>
            <td><?= e($ticket['department_name'] ?? 'Awaiting assignment') ?></td>
            <td><span class="status-pill <?= e(status_class($ticket['status'])) ?>"><?= e($ticket['status']) ?></span></td>
            <td><?= e(date('M j, Y', strtotime($ticket['created_at']))) ?></td>
            <td><a class="table-action" href="?page=ticket&id=<?= (int) $ticket['id'] ?>" aria-label="Open ticket"><i class="ti ti-arrow-up-right"></i></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
