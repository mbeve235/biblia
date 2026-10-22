<?php
/** Planos de leitura pessoais. */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();

$selectedSlug = trim((string) ($_GET['plan'] ?? $_POST['plan'] ?? ''));
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $plan = db_one('SELECT * FROM reading_plans WHERE slug = ? AND active = 1', [$selectedSlug]);

    if ($plan && in_array($action, ['start', 'restart'], true)) {
        db_exec(
            'INSERT INTO user_reading_plans (user_id, plan_id, started_at, status)
             VALUES (?, ?, CURDATE(), "active")
             ON DUPLICATE KEY UPDATE started_at = CURDATE(), status = "active", completed_at = NULL',
            [$user['id'], $plan['id']]
        );
        $userPlan = db_one('SELECT id FROM user_reading_plans WHERE user_id = ? AND plan_id = ?', [$user['id'], $plan['id']]);
        if ($userPlan) db_exec('DELETE FROM reading_plan_progress WHERE user_plan_id = ?', [$userPlan['id']]);
        $message = $action === 'restart' ? 'Plano reiniciado.' : 'Plano iniciado. Boa leitura!';
    }

    if ($plan && $action === 'toggle') {
        $day = (int) ($_POST['day'] ?? 0);
        $userPlan = db_one('SELECT * FROM user_reading_plans WHERE user_id = ? AND plan_id = ?', [$user['id'], $plan['id']]);
        if ($userPlan && $day >= 1 && $day <= (int) $plan['duration_days']) {
            $done = db_one('SELECT id FROM reading_plan_progress WHERE user_plan_id = ? AND day_number = ?', [$userPlan['id'], $day]);
            if ($done) {
                db_exec('DELETE FROM reading_plan_progress WHERE id = ?', [$done['id']]);
                db_exec('UPDATE user_reading_plans SET status = "active", completed_at = NULL WHERE id = ?', [$userPlan['id']]);
            } else {
                db_exec('INSERT INTO reading_plan_progress (user_plan_id, day_number) VALUES (?, ?)', [$userPlan['id'], $day]);
                $count = (int) (db_one('SELECT COUNT(*) AS c FROM reading_plan_progress WHERE user_plan_id = ?', [$userPlan['id']])['c'] ?? 0);
                if ($count >= (int) $plan['duration_days']) {
                    db_exec('UPDATE user_reading_plans SET status = "completed", completed_at = NOW() WHERE id = ?', [$userPlan['id']]);
                }
            }
        }
    }
}

$plans = db_all(
    'SELECT p.*, up.id AS user_plan_id, up.status, up.started_at, up.completed_at,
            COUNT(rp.id) AS completed_days
     FROM reading_plans p
     LEFT JOIN user_reading_plans up ON up.plan_id = p.id AND up.user_id = ?
     LEFT JOIN reading_plan_progress rp ON rp.user_plan_id = up.id
     WHERE p.active = 1
     GROUP BY p.id, up.id, up.status, up.started_at, up.completed_at
     ORDER BY p.sort_order, p.id',
    [$user['id']]
);

$selectedPlan = null;
foreach ($plans as $candidate) {
    if ($candidate['slug'] === $selectedSlug) $selectedPlan = $candidate;
}
$schedule = $selectedPlan ? reading_plan_schedule($selectedPlan) : [];
$completedDays = [];
if ($selectedPlan && $selectedPlan['user_plan_id']) {
    foreach (db_all('SELECT day_number FROM reading_plan_progress WHERE user_plan_id = ?', [$selectedPlan['user_plan_id']]) as $row) {
        $completedDays[(int) $row['day_number']] = true;
    }
}

$activeAbbr = $_COOKIE['bw_translation'] ?? DEFAULT_TRANSLATION;
$activeTranslation = db_one('SELECT abbreviation FROM translations WHERE abbreviation = ? AND status = "active"', [$activeAbbr]);
if (!$activeTranslation) $activeTranslation = db_one('SELECT abbreviation FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
$activeAbbr = $activeTranslation['abbreviation'] ?? '';

$habitRows = db_all(
    'SELECT DATE(read_at) AS reading_date FROM reading_history
     WHERE user_id = ? AND read_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
     GROUP BY DATE(read_at) ORDER BY reading_date DESC',
    [$user['id']]
);
$readingStreak = reading_streak_days($habitRows);
$readDateSet = [];
foreach ($habitRows as $habitRow) $readDateSet[$habitRow['reading_date']] = true;
$weekDays = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
    $weekDays[] = ['date' => $date, 'label' => mb_substr(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)date('w', strtotime($date))], 0, 3), 'done' => isset($readDateSet[$date])];
}

$pageTitle = $selectedPlan ? $selectedPlan['title'] : 'Planos de leitura';
require_once __DIR__ . '/includes/header.php';
?>

<header class="page-header">
  <span class="page-kicker">A sua jornada</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-calendar-check"></i></span><div><h1><?= $selectedPlan ? e($selectedPlan['title']) : 'Planos de leitura' ?></h1><p><?= $selectedPlan ? e($selectedPlan['description']) : 'Crie um ritmo diário e acompanhe o seu progresso na Palavra.' ?></p></div></div>
</header>

<?php if ($message): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>

<?php if (!$selectedPlan): ?>
  <section class="habit-overview" aria-label="Ritmo de leitura">
    <div class="habit-streak"><span><i class="fas fa-fire"></i></span><div><strong><?= $readingStreak ?></strong><small><?= $readingStreak === 1 ? 'dia seguido' : 'dias seguidos' ?></small></div></div>
    <div class="habit-week"><div><strong>O seu ritmo nesta semana</strong><small>Cada leitura conta — constância antes de quantidade.</small></div><div class="habit-days"><?php foreach ($weekDays as $weekDay): ?><span class="<?= $weekDay['done'] ? 'done' : '' ?>" title="<?= e($weekDay['date']) ?>"><i class="fas fa-<?= $weekDay['done'] ? 'check' : 'circle' ?>"></i><small><?= e($weekDay['label']) ?></small></span><?php endforeach; ?></div></div>
  </section>
  <?php $activePlans = array_filter($plans, fn($plan) => !empty($plan['user_plan_id']) && $plan['status'] !== 'completed'); ?>
  <?php if ($activePlans): ?>
  <section class="reading-plan-section">
    <div class="home-section-head"><div><span>Em andamento</span><h2>Continue a sua jornada</h2></div></div>
    <div class="reading-plan-grid">
      <?php foreach ($activePlans as $plan): $percent = (int) round(((int) $plan['completed_days'] / (int) $plan['duration_days']) * 100); ?>
      <a class="reading-plan-card active" href="<?= url('/planos.php?plan=' . urlencode($plan['slug'])) ?>">
        <span class="reading-plan-icon accent-<?= e($plan['accent']) ?>"><i class="fas fa-<?= e($plan['icon']) ?>"></i></span>
        <span class="reading-plan-content"><small><?= (int) $plan['completed_days'] ?> de <?= (int) $plan['duration_days'] ?> dias</small><strong><?= e($plan['title']) ?></strong><span class="plan-progress"><i style="width:<?= $percent ?>%"></i></span><em><?= $percent ?>% concluído</em></span>
        <i class="fas fa-arrow-right"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="reading-plan-section">
    <div class="home-section-head"><div><span>Descobrir</span><h2>Escolha um plano</h2></div></div>
    <div class="reading-plan-grid">
      <?php foreach ($plans as $plan): $percent = (int) round(((int) $plan['completed_days'] / (int) $plan['duration_days']) * 100); ?>
      <a class="reading-plan-card" href="<?= url('/planos.php?plan=' . urlencode($plan['slug'])) ?>">
        <span class="reading-plan-icon accent-<?= e($plan['accent']) ?>"><i class="fas fa-<?= e($plan['icon']) ?>"></i></span>
        <span class="reading-plan-content"><small><?= (int) $plan['duration_days'] ?> dias</small><strong><?= e($plan['title']) ?></strong><span><?= e($plan['description']) ?></span><?php if ($plan['user_plan_id']): ?><em><?= $percent ?>% concluído</em><?php endif; ?></span>
        <i class="fas fa-chevron-right"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <?php
    $doneCount = count($completedDays);
    $percent = (int) round(($doneCount / (int) $selectedPlan['duration_days']) * 100);
    $nextDay = 1;
    while ($nextDay <= (int) $selectedPlan['duration_days'] && isset($completedDays[$nextDay])) $nextDay++;
    $nextDay = min((int) $selectedPlan['duration_days'], $nextDay);
  ?>
  <section class="card plan-summary">
    <div><strong><?= $percent ?>%</strong><span><?= $doneCount ?> de <?= (int) $selectedPlan['duration_days'] ?> dias concluídos</span></div>
    <span class="plan-progress"><i style="width:<?= $percent ?>%"></i></span>
    <?php if (!$selectedPlan['user_plan_id']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($selectedPlan['slug']) ?>"><button class="btn btn-primary" name="action" value="start"><i class="fas fa-play"></i> Iniciar este plano</button></form>
    <?php else: ?>
      <a class="btn btn-primary" href="#dia-<?= $nextDay ?>"><i class="fas fa-location-arrow"></i> Continuar no dia <?= $nextDay ?></a>
    <?php endif; ?>
  </section>

  <div class="plan-days">
    <?php foreach ($schedule as $day => $chapters): $isDone = isset($completedDays[$day]); $isCurrent = !$isDone && $day === $nextDay; ?>
    <article class="plan-day <?= $isDone ? 'done' : '' ?> <?= $isCurrent ? 'current' : '' ?>" id="dia-<?= $day ?>">
      <div class="plan-day-number"><?= $isDone ? '<i class="fas fa-check"></i>' : $day ?></div>
      <div class="plan-day-content"><small>Dia <?= $day ?></small><h3><?= e(reading_plan_day_label($chapters)) ?></h3><div class="plan-day-links"><?php foreach ($chapters as $chapter): ?><a href="<?= url('/leitura.php?translation=' . urlencode($activeAbbr) . '&book=' . urlencode($chapter['slug']) . '&chapter=' . $chapter['chapter']) ?>"><?= e($chapter['book']) ?> <?= $chapter['chapter'] ?></a><?php endforeach; ?></div></div>
      <?php if ($selectedPlan['user_plan_id']): ?><form method="post" class="plan-day-action"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($selectedPlan['slug']) ?>"><input type="hidden" name="day" value="<?= $day ?>"><button class="btn btn-sm <?= $isDone ? 'btn-ghost' : 'btn-primary' ?>" name="action" value="toggle"><?= $isDone ? 'Desmarcar' : 'Concluir' ?></button></form><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if ($selectedPlan['user_plan_id']): ?><form method="post" class="plan-restart" onsubmit="return confirm('Reiniciar este plano e apagar o progresso actual?')"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($selectedPlan['slug']) ?>"><button class="btn btn-ghost" name="action" value="restart"><i class="fas fa-rotate-left"></i> Reiniciar plano</button></form><?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
