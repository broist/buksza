<?php

namespace App\Finance;

use DateTimeImmutable;

final class RecurringPlanner
{
    public function amountChanges(array $occurrences, array $transactions): array
    {
        $changes = [];
        foreach ($occurrences as $occurrence) {
            if (! $occurrence['paid']) {
                continue;
            }
            $current = collect($transactions)->first(fn ($tx) => $tx['id'] === $occurrence['transaction_id'] && ! ($tx['deleted_at'] ?? null));
            if (! $current || $current['type'] !== 'expense' || ($current['informational'] ?? false)) {
                continue;
            }
            $baseline = (int) $occurrence['payload']['amount'];
            $kind = 'plan';
            $referenceDate = $occurrence['due_date'];
            if ((int) $current['amount'] === $baseline) {
                $previous = array_values(array_filter($transactions, fn ($tx) => ($tx['template_id'] ?? null) == $occurrence['template_id'] && ($tx['occurrence_date'] ?? '') < $occurrence['due_date'] && $tx['date'] <= $current['date'] && $tx['type'] === 'expense' && ! ($tx['informational'] ?? false) && ! ($tx['deleted_at'] ?? null)));
                usort($previous, fn ($a, $b) => [$b['occurrence_date'], $b['id']] <=> [$a['occurrence_date'], $a['id']]);
                if (! $previous) {
                    continue;
                }
                $baseline = (int) $previous[0]['amount'];
                $referenceDate = $previous[0]['occurrence_date'];
                $kind = 'previous';
            }
            if ((int) $current['amount'] !== $baseline) {
                $changes[] = ['transaction_id' => $current['id'], 'template_id' => $occurrence['template_id'], 'name' => $occurrence['name'], 'due_date' => $occurrence['due_date'], 'record_date' => $current['date'], 'actual' => (int) $current['amount'], 'reference' => $baseline, 'reference_kind' => $kind, 'reference_date' => $referenceDate, 'difference' => (int) $current['amount'] - $baseline];
            }
        }

        return $changes;
    }

    public function month(array $templates, array $versions, array $transactions, array $loans, array $loanVersions, int $year, int $month): array
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        $rows = [];
        foreach ($templates as $template) {
            $history = array_values(array_filter($versions, fn ($v) => $v['template_id'] == $template['id']));
            usort($history, fn ($a, $b) => [$a['effective_date'], $a['id']] <=> [$b['effective_date'], $b['id']]);
            foreach ($history as $index => $version) {
                $data = $version['data'];
                if (! $data['active']) {
                    continue;
                }
                $next = $history[$index + 1]['effective_date'] ?? '9999-12-31';
                $dueDates = [];
                $payload = $data['payload'];
                if ($data['loan_terms']) {
                    $loan = array_values(array_filter($loans, fn ($l) => $l['id'] == $payload['loan_id']));
                    $report = (new Calculator)->loans($loan, $loanVersions, [], $year, $month)[0] ?? null;
                    if ($report && $report['expected'] > 0) {
                        $dueDates[] = $report['due_date'];
                        $payload['amount'] = $report['expected'];
                    }
                } elseif ($data['frequency'] === 'weekly') {
                    $anchor = new DateTimeImmutable($data['start_date']);
                    for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
                        $days = (int) $anchor->diff($day)->format('%r%a');
                        if ($days >= 0 && $days % (7 * $data['interval']) === 0) {
                            $dueDates[] = $day->format('Y-m-d');
                        }
                    }
                } else {
                    $start = new DateTimeImmutable($data['start_date']);
                    $distance = ($year - (int) $start->format('Y')) * 12 + $month - (int) $start->format('m');
                    $period = $data['frequency'] === 'yearly' ? 12 * $data['interval'] : $data['interval'];
                    $eligible = $distance >= 0 && ($data['frequency'] === 'yearly' ? $month === $data['due_month'] && ($year - (int) $start->format('Y')) % $data['interval'] === 0 : $distance % $period === 0);
                    if ($eligible) {
                        $dueDates[] = $first->setDate($year, $month, min($data['due_day'], (int) $last->format('d')))->format('Y-m-d');
                    }
                }
                foreach ($dueDates as $due) {
                    if ($due < $version['effective_date'] || $due >= $next || $due < $data['start_date'] || ($data['end_date'] && $due > $data['end_date'])) {
                        continue;
                    }
                    $record = array_values(array_filter($transactions, fn ($tx) => ($tx['template_id'] ?? null) == $template['id'] && ($tx['occurrence_date'] ?? null) === $due))[0] ?? null;
                    $rows[] = ['template_id' => $template['id'], 'name' => $data['name'], 'due_date' => $due, 'payload' => $payload, 'transaction_id' => $record['id'] ?? null, 'paid' => (bool) ($record && ! ($record['deleted_at'] ?? null)), 'record_date' => $record['date'] ?? null, 'record_amount' => $record['amount'] ?? null];
                }
            }
        }
        // A payment made early must remain visible even if a later template change cancels its due date.
        $known = array_map(fn ($row) => $row['template_id'].'/'.$row['due_date'], $rows);
        foreach ($transactions as $tx) {
            if (($tx['deleted_at'] ?? null) || ! str_starts_with($tx['occurrence_date'] ?? '', $first->format('Y-m-')) || in_array($tx['template_id'].'/'.$tx['occurrence_date'], $known)) {
                continue;
            }
            $template = array_values(array_filter($templates, fn ($t) => $t['id'] == $tx['template_id']))[0] ?? null;
            if ($template) {
                $rows[] = ['template_id' => $template['id'], 'name' => $template['name'], 'due_date' => $tx['occurrence_date'], 'payload' => $tx, 'transaction_id' => $tx['id'], 'paid' => true, 'record_date' => $tx['date'], 'record_amount' => $tx['amount']];
            }
        }
        usort($rows, fn ($a, $b) => [$a['due_date'], $a['template_id']] <=> [$b['due_date'], $b['template_id']]);

        return $rows;
    }
}
