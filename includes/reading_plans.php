<?php
/** Utilitários para gerar os dias dos planos a partir do cânone da base de dados. */

declare(strict_types=1);

function reading_plan_schedule(array $plan): array
{
    $books = db_all(
        'SELECT id, name, slug, chapters_count FROM books
         WHERE book_order BETWEEN ? AND ? ORDER BY book_order',
        [(int) $plan['start_book_order'], (int) $plan['end_book_order']]
    );

    $chapters = [];
    foreach ($books as $book) {
        for ($chapter = 1; $chapter <= (int) $book['chapters_count']; $chapter++) {
            $chapters[] = [
                'book_id' => (int) $book['id'],
                'book' => $book['name'],
                'slug' => $book['slug'],
                'chapter' => $chapter,
            ];
        }
    }

    $days = [];
    $totalChapters = count($chapters);
    $duration = max(1, (int) $plan['duration_days']);
    for ($day = 1; $day <= $duration; $day++) {
        $start = (int) floor((($day - 1) * $totalChapters) / $duration);
        $end = (int) floor(($day * $totalChapters) / $duration);
        $days[$day] = array_slice($chapters, $start, max(1, $end - $start));
    }
    return $days;
}

function reading_plan_day_label(array $chapters): string
{
    if (!$chapters) return 'Leitura por definir';
    $groups = [];
    foreach ($chapters as $chapter) {
        $groups[$chapter['book']][] = (int) $chapter['chapter'];
    }
    $parts = [];
    foreach ($groups as $book => $numbers) {
        $first = min($numbers);
        $last = max($numbers);
        $parts[] = $book . ' ' . ($first === $last ? $first : $first . '–' . $last);
    }
    return implode(' · ', $parts);
}

/** Calcula a sequência de dias lidos, aceitando hoje ou ontem como ponto de partida. */
function reading_streak_days(array $dates, ?DateTimeImmutable $today = null): int
{
    $today ??= new DateTimeImmutable('today');
    $unique = [];
    foreach ($dates as $date) {
        $value = is_array($date) ? ($date['reading_date'] ?? '') : $date;
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) $unique[$value] = true;
    }
    if (!$unique) return 0;

    $cursor = $today;
    if (!isset($unique[$cursor->format('Y-m-d')])) $cursor = $cursor->modify('-1 day');

    $streak = 0;
    while (isset($unique[$cursor->format('Y-m-d')])) {
        $streak++;
        $cursor = $cursor->modify('-1 day');
    }
    return $streak;
}
