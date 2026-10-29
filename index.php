<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/actions.php';
require __DIR__ . '/app/layout.php';

handle_action($pdo);

$page = (string) ($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
$adminExists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
if (!$adminExists && !in_array($page, ['setup', 'track'], true)) {
    $page = 'setup';
}
if ($adminExists && $page === 'setup') {
    $page = current_user() ? 'dashboard' : 'login';
}

$user = current_user();
$schools = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

switch ($page) {
    case 'login':
        if ($user) {
            redirect_to('?page=dashboard');
        }
        render_start('Sign in', $page, null);
        ?>
        <div class="auth-wrap"><section class="card-panel auth-card">
          <div class="auth-brand"><span class="brand-mark"><i class="ti ti-leaf"></i></span> FCWM Support Centre</div>
          <span class="eyebrow">Welcome back</span><h2>Sign in to your account</h2>
          <p>Use your registered email to manage support requests and follow case updates.</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="login">
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" autocomplete="username" required></div>
            <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="btn btn-forest w-100 mt-2" type="submit">Sign in <i class="ti ti-arrow-right"></i></button>
          </form>
          <div class="d-flex justify-content-between align-items-center mt-4">
            <a class="helper-link" href="?page=register">Create a student account</a><a class="helper-link" href="?page=track">Track a request</a>
          </div>
        </section></div>
        <?php
        render_end();
        break;

    case 'register':
        if ($user) {
            redirect_to('?page=dashboard');
        }
        render_start('Student registration', $page, null);
        ?>
        <div class="auth-wrap"><section class="card-panel auth-card wide">
          <div class="auth-brand"><span class="brand-mark"><i class="ti ti-leaf"></i></span> FCWM Support Centre</div>
          <span class="eyebrow">Student access</span><h2>Create your account</h2>
          <p>Register with your college details to submit requests and follow their progress.</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="register">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" maxlength="150" autocomplete="name" required></div>
              <div class="col-md-6"><label class="form-label" for="matric_number">Matric number</label><input class="form-control" id="matric_number" name="matric_number" maxlength="50" required></div>
              <div class="col-md-6"><label class="form-label" for="email">Gmail address</label><input class="form-control" id="email" name="email" type="email" placeholder="name@gmail.com" autocomplete="email" required><div class="form-text">Use your Gmail address to register.</div></div>
              <div class="col-md-6"><label class="form-label" for="school_id">School</label><select class="form-select" id="school_id" name="school_id" required><option value="">Select your school</option><?php foreach ($schools as $school): ?><option value="<?= (int) $school['id'] ?>"><?= e($school['name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required><div class="form-text">At least 8 characters.</div></div>
            </div>
            <button class="btn btn-forest w-100 mt-4" type="submit">Create student account <i class="ti ti-arrow-right"></i></button>
          </form>
          <div class="mt-3 text-center"><a class="helper-link" href="?page=login">Already registered? Sign in</a></div>
        </section></div>
        <?php
        render_end();
        break;

    case 'setup':
        if ($adminExists) {
            redirect_to('?page=login');
        }
        render_start('Initial administrator setup', $page, null);
        ?>
        <div class="auth-wrap"><section class="card-panel auth-card">
          <div class="auth-brand"><span class="brand-mark"><i class="ti ti-leaf"></i></span> FCWM Support Centre</div>
          <span class="eyebrow">First-time setup</span><h2>Create the administrator account</h2>
          <p>This one-time step secures the system and enables department account creation and case routing.</p>
          <div class="setup-banner mb-4"><i class="ti ti-info-circle"></i> This setup form is disabled automatically as soon as the first administrator is created.</div>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="setup_admin">
            <div class="mb-3"><label class="form-label" for="full_name">Administrator name</label><input class="form-control" id="full_name" name="full_name" maxlength="150" required></div>
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" required></div>
            <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required><div class="form-text">Use at least 12 characters.</div></div>
            <button class="btn btn-forest w-100 mt-2" type="submit">Create administrator</button>
          </form>
        </section></div>
        <?php
        render_end();
        break;

    case 'dashboard':
        $user = require_user();
        $where = '';
        $parameters = [];
        if ($user['role'] === 'student') {
            $where = ' WHERE student_id = ?';
            $parameters[] = $user['id'];
        } elseif ($user['role'] === 'department') {
            $where = ' WHERE department_id = ?';
            $parameters[] = $user['department_id'];
        }
        $countQuery = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status IN ('Submitted', 'Under review', 'In progress')) AS active, SUM(status = 'Resolved') AS resolved FROM tickets" . $where);
        $countQuery->execute($parameters);
        $counts = $countQuery->fetch();
        $ticketSql = 'SELECT t.*, u.full_name AS student_name, u.matric_number, d.name AS department_name
                      FROM tickets t JOIN users u ON u.id = t.student_id
                      LEFT JOIN departments d ON d.id = t.department_id' . $where . ' ORDER BY t.updated_at DESC LIMIT 6';
        $recentQuery = $pdo->prepare($ticketSql);
        $recentQuery->execute($parameters);
        $recent = $recentQuery->fetchAll();
        $isStudent = $user['role'] === 'student';
        $isAdmin = $user['role'] === 'admin';
        render_start('Overview', $page, $user);
        page_heading($isAdmin ? 'System snapshot' : ($isStudent ? 'Your support space' : 'Department workspace'),
            'Good day, ' . explode(' ', $user['full_name'])[0],
            $isAdmin ? 'Monitor incoming cases and keep requests moving to the right teams.' : ($isStudent ? 'Submit a concern, or check the latest progress on your requests.' : 'Review and respond to cases routed to your department.'),
            $isStudent ? 'New request' : null, $isStudent ? '?page=new-ticket' : null);
        ?>
        <div class="row g-3 mb-4">
          <?php
          $metrics = [
              ['Total requests', (int) ($counts['total'] ?? 0), 'ti ti-ticket', '#eaf2ed', '#2d684b'],
              ['Active cases', (int) ($counts['active'] ?? 0), 'ti ti-clock-hour-4', '#fff4dc', '#a97925'],
              ['Resolved', (int) ($counts['resolved'] ?? 0), 'ti ti-circle-check', '#e7f3e9', '#438151'],
          ];
          if ($isAdmin) {
              $metrics[] = ['Departments', count($departments), 'ti ti-building-community', '#eaf0fb', '#526d9a'];
          }
          foreach ($metrics as [$label, $value, $icon, $tint, $color]): ?>
            <div class="col-sm-6 col-xl-3"><div class="metric-card" style="--metric-tint:<?= e($tint) ?>;--metric-color:<?= e($color) ?>">
              <div class="metric-top"><span><?= e($label) ?></span><span class="metric-icon"><i class="<?= e($icon) ?>"></i></span></div>
              <strong class="metric-value"><?= (int) $value ?></strong><span class="metric-caption"><?= $isStudent ? 'Across your account' : 'In your workspace' ?></span>
            </div></div>
          <?php endforeach; ?>
        </div>
        <?php if ($isStudent): ?>
          <div class="row g-3 mb-4">
            <div class="col-lg-7"><a class="card-panel d-flex align-items-center gap-3 p-4 h-100" href="?page=new-ticket">
              <span class="metric-icon"><i class="ti ti-message-plus"></i></span><span><strong class="d-block mb-1">Tell us what you need help with</strong><small class="text-muted">Create a confidential request for the relevant support team.</small></span><i class="ti ti-arrow-right ms-auto"></i>
            </a></div>
            <div class="col-lg-5"><a class="card-panel d-flex align-items-center gap-3 p-4 h-100" href="?page=track">
              <span class="metric-icon" style="--metric-tint:#fff4dc;--metric-color:#a97925"><i class="ti ti-search"></i></span><span><strong class="d-block mb-1">Have a reference number?</strong><small class="text-muted">Look up the latest case status.</small></span><i class="ti ti-arrow-right ms-auto"></i>
            </a></div>
          </div>
        <?php endif; ?>
        <section class="card-panel table-wrap">
          <div class="panel-heading"><div><h2><?= $isStudent ? 'Your recent requests' : ($isAdmin ? 'Recently updated cases' : 'Recently updated cases') ?></h2><p><?= $isStudent ? 'A quick view of your latest support requests.' : 'Follow up on work in your queue.' ?></p></div>
            <a class="helper-link" href="?page=tickets"><?= $isStudent ? 'View all requests' : 'View all cases' ?> <i class="ti ti-arrow-right"></i></a></div>
          <?php render_ticket_rows($recent, 'Nothing here yet', $isStudent ? 'Your submitted requests will appear here.' : 'Cases routed to your workspace will appear here.'); ?>
        </section>
        <?php
        render_end();
        break;

    case 'tickets':
        $user = require_user();
        $sql = 'SELECT t.*, u.full_name AS student_name, u.matric_number, d.name AS department_name
                FROM tickets t JOIN users u ON u.id = t.student_id LEFT JOIN departments d ON d.id = t.department_id';
        $parameters = [];
        if ($user['role'] === 'student') {
            $sql .= ' WHERE t.student_id = ?';
            $parameters[] = $user['id'];
        } elseif ($user['role'] === 'department') {
            $sql .= ' WHERE t.department_id = ?';
            $parameters[] = $user['department_id'];
        } elseif ($user['role'] !== 'admin') {
            http_response_code(403);
            exit('You do not have permission to view cases.');
        }
        $sql .= ' ORDER BY t.updated_at DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        $tickets = $statement->fetchAll();
        $student = $user['role'] === 'student';
        render_start($student ? 'My requests' : 'Cases', $page, $user);
        page_heading($student ? 'Your history' : ($user['role'] === 'admin' ? 'Case management' : 'Department queue'),
            $student ? 'My requests' : ($user['role'] === 'admin' ? 'All cases' : 'Assigned cases'),
            $student ? 'View the status and reference number for every request you have submitted.' : 'Open a case to review its details, update its status, or route it to a team.',
            $student ? 'New request' : null, $student ? '?page=new-ticket' : null);
        ?>
        <section class="card-panel table-wrap"><?php render_ticket_rows($tickets, 'No requests yet', $student ? 'Create a request to start a conversation with the college support team.' : 'There are no cases in this view yet.'); ?></section>
        <?php
        render_end();
        break;

    case 'new-ticket':
        $user = require_role(['student']);
        render_start('Create a support request', $page, $user);
        page_heading('Student support', 'Create a request', 'Choose the topic that best describes your concern. We will provide a reference number when you submit it.');
        ?>
        <div class="row g-4">
          <div class="col-lg-8"><section class="card-panel"><div class="card-body">
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="create_ticket">
              <div class="mb-3"><label class="form-label" for="topic">Topic</label><select class="form-select" id="topic" name="topic" required><option value="">Select a topic</option>
                <?php foreach (['Academic concern', 'Student welfare', 'Hostel and facilities', 'ICT and portal', 'Finance and fees', 'Safety and conduct', 'Other'] as $topic): ?><option value="<?= e($topic) ?>"><?= e($topic) ?></option><?php endforeach; ?>
              </select><div class="form-text">An administrator will route the request to the appropriate department.</div></div>
              <div class="mb-3"><label class="form-label" for="subject">Short subject</label><input class="form-control" id="subject" name="subject" maxlength="180" placeholder="A short summary of the issue" required></div>
              <div class="mb-3"><label class="form-label" for="description">Describe your request</label><textarea class="form-control" id="description" name="description" minlength="20" maxlength="10000" placeholder="Include relevant details so the team can help you..." required></textarea><div class="form-text">Please include at least 20 characters. Do not include passwords or sensitive account credentials.</div></div>
              <button class="btn btn-forest" type="submit"><i class="ti ti-send"></i> Submit request</button>
            </form>
          </div></section></div>
          <div class="col-lg-4"><section class="card-panel"><div class="card-body"><span class="metric-icon mb-3"><i class="ti ti-lock-check"></i></span><h2 class="h6 fw-semibold">What happens next?</h2>
            <div class="timeline mt-4">
              <div class="timeline-item"><span class="timeline-dot"><i class="ti ti-check"></i></span><div><strong>Reference number</strong><p>We create a unique reference as soon as you submit.</p></div></div>
              <div class="timeline-item"><span class="timeline-dot"><i class="ti ti-route"></i></span><div><strong>Department review</strong><p>An administrator sends your request to the team best placed to help.</p></div></div>
              <div class="timeline-item"><span class="timeline-dot"><i class="ti ti-message-check"></i></span><div><strong>Progress updates</strong><p>Sign in or track with your reference to see status updates.</p></div></div>
            </div>
          </div></section></div>
        </div>
        <?php
        render_end();
        break;

    case 'ticket':
        $user = require_user();
        $ticketId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $ticket = $ticketId ? ticket_for_user($pdo, $ticketId, $user) : null;
        if (!$ticket) {
            http_response_code(404);
            render_start('Request not found', $page, $user);
            echo '<div class="empty-state"><span class="empty-icon"><i class="ti ti-file-off"></i></span><strong>Request not found</strong><p>This request is unavailable or you do not have permission to view it.</p><a class="btn btn-forest mt-3" href="?page=tickets">Back to requests</a></div>';
            render_end();
            break;
        }
        $historyQuery = $pdo->prepare(
            'SELECT tu.*, u.full_name AS author_name FROM ticket_updates tu
             LEFT JOIN users u ON u.id = tu.author_id WHERE tu.ticket_id = ? ORDER BY tu.created_at DESC, tu.id DESC'
        );
        $historyQuery->execute([$ticketId]);
        $history = $historyQuery->fetchAll();
        render_start('Request ' . $ticket['reference_no'], $page, $user);
        page_heading('Case record', 'Request details', 'Track progress and see updates connected to this support request.');
        ?>
        <div class="row g-4">
          <div class="col-lg-8">
            <section class="card-panel mb-4"><div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div><span class="eyebrow">Reference number</span><h2 class="h5 mt-1 mb-0 reference"><?= e($ticket['reference_no']) ?></h2></div>
                <span class="status-pill <?= e(status_class($ticket['status'])) ?>"><?= e($ticket['status']) ?></span>
              </div>
              <h3 class="h5 fw-semibold"><?= e($ticket['subject']) ?></h3>
              <span class="badge rounded-pill bg-light text-secondary border"><?= e($ticket['topic']) ?></span>
              <hr class="my-4">
              <div class="detail-label mb-2">Request details</div><div class="description-text"><?= e($ticket['description']) ?></div>
              <div class="row g-3 mt-4">
                <?php if ($user['role'] !== 'student'): ?><div class="col-sm-6"><div class="detail-label">Student</div><div class="detail-value"><?= e($ticket['student_name']) ?></div><small class="text-muted"><?= e($ticket['matric_number']) ?> · <?= e($ticket['student_email']) ?></small></div><?php endif; ?>
                <div class="col-sm-6"><div class="detail-label">School</div><div class="detail-value"><?= e($ticket['school_name'] ?? 'Not provided') ?></div></div>
                <div class="col-sm-6"><div class="detail-label">Assigned department</div><div class="detail-value"><?= e($ticket['department_name'] ?? 'Awaiting assignment') ?></div></div>
                <div class="col-sm-6"><div class="detail-label">Submitted</div><div class="detail-value"><?= e(format_date($ticket['created_at'])) ?></div></div>
              </div>
            </div></section>
            <section class="card-panel"><div class="panel-heading"><div><h2>Updates</h2><p>Case activity and messages from the support team</p></div></div>
              <div class="card-body"><div class="timeline">
                <?php foreach ($history as $item): ?><div class="timeline-item"><span class="timeline-dot"><i class="ti ti-check"></i></span><div>
                  <strong><?= e($item['status']) ?><?= $item['author_name'] ? ' · ' . e($item['author_name']) : '' ?></strong>
                  <p><?= e($item['message']) ?></p><small><?= e(format_date($item['created_at'])) ?></small>
                </div></div><?php endforeach; ?>
              </div></div>
            </section>
          </div>
          <div class="col-lg-4">
            <?php if ($user['role'] === 'admin'): ?><section class="card-panel mb-4"><div class="card-body">
              <h2 class="h6 fw-semibold mb-1">Route to department</h2><p class="text-muted small mb-3">Assign this case to the team responsible for follow-up.</p>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="route_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                <label class="form-label" for="department_id">Department</label><select class="form-select mb-3" id="department_id" name="department_id" required><option value="">Choose a department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (int) $ticket['department_id'] === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-forest w-100" type="submit"><i class="ti ti-route"></i> Route case</button>
              </form>
            </div></section><?php endif; ?>
            <?php if (in_array($user['role'], ['admin', 'department'], true)): ?><section class="card-panel"><div class="card-body">
              <h2 class="h6 fw-semibold mb-1">Update case</h2><p class="text-muted small mb-3">Add a message that the student can see while tracking this request.</p>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                <div class="mb-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><?php foreach (['Submitted', 'Under review', 'In progress', 'Resolved', 'Closed'] as $status): ?><option <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label" for="message">Update message</label><textarea class="form-control" id="message" name="message" maxlength="3000" rows="4" placeholder="Share a concise progress update..." required></textarea></div>
                <button class="btn btn-forest w-100" type="submit"><i class="ti ti-message-plus"></i> Save update</button>
              </form>
            </div></section><?php endif; ?>
          </div>
        </div>
        <?php
        render_end();
        break;

    case 'track':
        $reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
        $ticket = null;
        $history = [];
        if ($reference !== '') {
            $query = $pdo->prepare(
                'SELECT t.*, d.name AS department_name FROM tickets t
                 LEFT JOIN departments d ON d.id = t.department_id WHERE t.reference_no = ?'
            );
            $query->execute([$reference]);
            $ticket = $query->fetch() ?: null;
            if ($ticket) {
                $historyQuery = $pdo->prepare('SELECT status, message, created_at FROM ticket_updates WHERE ticket_id = ? ORDER BY created_at DESC, id DESC');
                $historyQuery->execute([$ticket['id']]);
                $history = $historyQuery->fetchAll();
            }
        }
        render_start('Track a request', $page, $user);
        page_heading('Case tracking', 'Track your request', 'Enter the reference number you received after submission to view its latest status.');
        ?>
        <div class="row justify-content-center"><div class="col-xl-8">
          <section class="card-panel mb-4"><div class="card-body">
            <form method="get" class="row g-2 align-items-end"><input type="hidden" name="page" value="track">
              <div class="col-md-9"><label class="form-label" for="reference">Reference number</label><input class="form-control" id="reference" name="reference" value="<?= e($reference) ?>" placeholder="FCWM-2026-7F3A92B1C4D8E6A0B2C5" required></div>
              <div class="col-md-3"><button class="btn btn-forest w-100" type="submit"><i class="ti ti-search"></i> Track</button></div>
            </form>
          </div></section>
          <?php if ($reference !== '' && !$ticket): ?><div class="alert alert-warning">We couldn't find a request with that reference number. Check it and try again.</div><?php endif; ?>
          <?php if ($ticket): ?><section class="tracking-hero mb-4"><small><?= e($ticket['reference_no']) ?></small><h2><?= e($ticket['subject']) ?></h2><p>Submitted <?= e(format_date($ticket['created_at'])) ?><?php if ($ticket['department_name']): ?> · Assigned to <?= e($ticket['department_name']) ?><?php endif; ?></p><div class="mt-3"><span class="status-pill <?= e(status_class($ticket['status'])) ?>"><?= e($ticket['status']) ?></span></div></section>
            <section class="card-panel"><div class="panel-heading"><div><h2>Progress history</h2><p>Latest update first</p></div></div><div class="card-body"><div class="timeline">
              <?php foreach ($history as $item): ?><div class="timeline-item"><span class="timeline-dot"><i class="ti ti-check"></i></span><div><strong><?= e($item['status']) ?></strong><p><?= e($item['message']) ?></p><small><?= e(format_date($item['created_at'])) ?></small></div></div><?php endforeach; ?>
            </div></div></section>
          <?php endif; ?>
        </div></div>
        <?php
        render_end();
        break;

    case 'departments':
        $user = require_role(['admin']);
        $staff = $pdo->query(
            "SELECT u.full_name, u.email, d.name AS department_name
             FROM users u LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.role = 'department' ORDER BY d.name, u.full_name"
        )->fetchAll();
        render_start('Departments', $page, $user);
        page_heading('Routing & access', 'Departments', 'Manage the teams that receive cases and create secure sign-in accounts for department staff.');
        ?>
        <div class="row g-4">
          <div class="col-lg-7"><div class="row g-3">
            <?php foreach ($departments as $department): ?><div class="col-sm-6"><div class="department-card"><span class="department-icon"><i class="ti ti-building-community"></i></span><strong><?= e($department['name']) ?></strong>
              <?php $accountCount = 0; foreach ($staff as $member) { if ($member['department_name'] === $department['name']) $accountCount++; } ?>
              <p><?= $accountCount ?> department account<?= $accountCount === 1 ? '' : 's' ?></p>
            </div></div><?php endforeach; ?>
          </div>
          <section class="card-panel mt-4"><div class="panel-heading"><div><h2>Department accounts</h2><p>Staff access is scoped to the cases routed to their team.</p></div></div>
            <?php if (!$staff): ?><div class="empty-state"><strong>No department accounts</strong><p>Create an account to give a department access to its cases.</p></div><?php else: ?><div class="table-responsive"><table class="table ticket-table mb-0"><thead><tr><th>Name</th><th>Department</th><th>Email</th></tr></thead><tbody><?php foreach ($staff as $member): ?><tr><td><?= e($member['full_name']) ?></td><td><?= e($member['department_name'] ?? 'Not assigned') ?></td><td><?= e($member['email']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
          </section></div>
          <div class="col-lg-5"><section class="card-panel"><div class="panel-heading"><div><h2>Create department account</h2><p>Send sign-in credentials to the intended team owner.</p></div></div><div class="card-body">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create_department_user">
              <div class="mb-3"><label class="form-label" for="full_name">Staff member's full name</label><input class="form-control" id="full_name" name="full_name" maxlength="150" required></div>
              <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" required></div>
              <div class="mb-3"><label class="form-label" for="department_id">Department</label><select class="form-select" id="department_id" name="department_id" required><option value="">Choose a department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
              <div class="mb-3"><label class="form-label" for="password">Temporary password</label><input class="form-control" id="password" name="password" type="password" minlength="12" required><div class="form-text">Use a unique password with at least 12 characters.</div></div>
              <button class="btn btn-forest w-100" type="submit"><i class="ti ti-user-plus"></i> Create account</button>
            </form>
          </div></section></div>
        </div>
        <?php
        render_end();
        break;

    default:
        http_response_code(404);
        render_start('Page not found', '', $user);
        echo '<div class="empty-state"><span class="empty-icon"><i class="ti ti-file-alert"></i></span><strong>Page not found</strong><p>The page you requested does not exist.</p><a href="?page=' . ($user ? 'dashboard' : 'login') . '" class="btn btn-forest mt-3">Return to support centre</a></div>';
        render_end();
}
