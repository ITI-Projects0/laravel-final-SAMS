<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Group;
use Carbon\Carbon;

class CenterAiService
{
    public function __construct(protected AiClient $ai) {}

    public function insights(?int $classId = null, ?int $groupId = null): string
    {
        $stats = $this->attendanceStats($classId, $groupId);

        $prompt = <<<TXT
اكتب ملخص قصير (بالعربية) لإدارة المركز في أقل من 6 أسطر:
- أقل المجموعات حضوراً: {$this->list($stats['low_groups'])}
- معدل الحضور العام: {$stats['avg_rate']}
- اليوم الأضعف: {$stats['weak_day']}
- اقترح إجراءات تشغيلية لتحسين الحضور والتواصل مع أولياء الأمور.
TXT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'أنت مساعد يقدم Insights تشغيلية لمركز تعليمي بالعربية، موجزة وواضحة.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    public function attendanceForecast(?int $classId = null, ?int $groupId = null): string
    {
        $history = $this->attendanceHistory($classId, $groupId);
        $serialized = json_encode($history);

        $prompt = <<<TXT
لديك بيانات حضور آخر 90 يوم (daily rate). قدم توقعاً بسيطاً للـ 14 يوم القادمة، وحدد مخاطر الانخفاض.
بيانات (JSON): {$serialized}
اكتب الرد بالعربية، في 5-6 أسطر، مع نصائح لتجنب الانخفاض.
TXT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'أنت مساعد يحلل حضور الطلاب ويتنبأ بالاتجاهات المستقبلية بالعربية.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    protected function attendanceStats(?int $classId, ?int $groupId): array
    {
        $query = Attendance::query();
        if ($groupId) {
            $query->where('group_id', $groupId);
        } elseif ($classId) {
            $query->where('group_id', $classId);
        }
        $records = $query->get(['date', 'status', 'group_id']);
        $total = $records->count();
        $present = $records->where('status', 'present')->count();
        $avg = $total > 0 ? round($present / $total, 3) : 0.0;

        $dayStats = [];
        foreach ($records as $rec) {
            $day = Carbon::parse($rec->date)->format('l');
            $dayStats[$day] = $dayStats[$day] ?? ['p' => 0, 't' => 0];
            $dayStats[$day]['t']++;
            if ($rec->status === 'present') {
                $dayStats[$day]['p']++;
            }
        }
        $weakDay = '';
        $weakRate = null;
        foreach ($dayStats as $d => $info) {
            $r = $info['t'] > 0 ? $info['p'] / $info['t'] : 0;
            if ($weakRate === null || $r < $weakRate) {
                $weakRate = $r;
                $weakDay = $d;
            }
        }

        $groupStats = [];
        foreach ($records as $rec) {
            if (!$rec->group_id) continue;
            $groupStats[$rec->group_id] = $groupStats[$rec->group_id] ?? ['p' => 0, 't' => 0];
            $groupStats[$rec->group_id]['t']++;
            if ($rec->status === 'present') $groupStats[$rec->group_id]['p']++;
        }
        $groupRates = collect($groupStats)->map(fn($v, $id) => [
            'group_id' => $id,
            'rate' => $v['t'] > 0 ? $v['p'] / $v['t'] : 0,
        ]);
        $names = Group::whereIn('id', $groupRates->pluck('group_id'))->pluck('name', 'id');
        $low = $groupRates->filter(fn($g) => $g['rate'] < 0.75)
            ->pluck('group_id')
            ->map(fn($id) => $names[$id] ?? "Group #{$id}")
            ->values()
            ->all();

        return [
            'avg_rate' => $avg,
            'weak_day' => $weakDay,
            'low_groups' => $low,
        ];
    }

    protected function attendanceHistory(?int $classId, ?int $groupId): array
    {
        $from = Carbon::now()->subDays(90)->startOfDay();
        $query = Attendance::query()->whereDate('date', '>=', $from);
        if ($groupId) {
            $query->where('group_id', $groupId);
        } elseif ($classId) {
            $query->where('group_id', $classId);
        }
        $records = $query->get(['date', 'status']);
        $daily = [];
        foreach ($records as $rec) {
            $d = Carbon::parse($rec->date)->toDateString();
            $daily[$d] = $daily[$d] ?? ['p' => 0, 't' => 0];
            $daily[$d]['t']++;
            if ($rec->status === 'present') $daily[$d]['p']++;
        }
        $series = [];
        foreach ($daily as $date => $data) {
            $rate = $data['t'] > 0 ? $data['p'] / $data['t'] : 0;
            $series[] = ['date' => $date, 'rate' => $rate];
        }
        return $series;
    }

    protected function list(array $items): string
    {
        return empty($items) ? 'لا يوجد' : implode(', ', $items);
    }
}
