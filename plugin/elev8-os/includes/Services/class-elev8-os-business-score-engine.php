<?php
if (!defined('ABSPATH')) { exit; }

/** Reusable 0-100 business health score. */
final class Elev8_OS_Business_Score_Engine {
    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public static function calculate(array $signals): array {
        $weights = ['profile'=>20,'artwork'=>20,'classes'=>20,'sales'=>20,'engagement'=>10,'website'=>10];
        $parts = [];
        $total = 0;
        foreach ($weights as $key => $weight) {
            $raw = isset($signals[$key]) && is_numeric($signals[$key]) ? (float)$signals[$key] : null;
            if ($raw === null) { $parts[$key] = ['available'=>false,'score'=>null,'weight'=>$weight]; continue; }
            $score = max(0, min(100, (int)round($raw)));
            $parts[$key] = ['available'=>true,'score'=>$score,'weight'=>$weight];
            $total += ($score / 100) * $weight;
        }
        return ['available'=>true,'score'=>(int)round($total),'components'=>$parts,'label'=>self::label((int)round($total))];
    }
    private static function label(int $score): string {
        if ($score >= 85) return __('Excellent', 'elev8-os');
        if ($score >= 70) return __('Strong', 'elev8-os');
        if ($score >= 50) return __('Building', 'elev8-os');
        return __('Needs attention', 'elev8-os');
    }
}
