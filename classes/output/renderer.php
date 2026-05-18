<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Server-side renderer for block_grade_me. Exposes SVG atoms used by the
 * dashboard / report pages (the block uses the AMD-side atoms instead).
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\output;

defined('MOODLE_INTERNAL') || die();

use block_grade_me\local\sla\bucket;
use block_grade_me\local\sla\rule_resolver;
use plugin_renderer_base;

/**
 * Each atom mirrors the math in claude-design-block_grade_me/project/src/atoms.jsx
 * and the AMD-side counterparts in amd/src/*. Methods return self-contained
 * SVG/HTML strings that are dropped into Mustache via {{{key}}}.
 */
class renderer extends plugin_renderer_base {

    /**
     * SVG sparkline. Ported from atoms.jsx:6-23.
     *
     * @param float[] $series
     * @param array $opts width, height, color, fill, stroke_width, dot
     * @return string
     */
    public function render_sparkline(array $series, array $opts = []): string {
        $width = $opts['width'] ?? 80;
        $height = $opts['height'] ?? 22;
        $color = $opts['color'] ?? '#10b981';
        $fill = $opts['fill'] ?? 'rgba(16,185,129,0.14)';
        $strokewidth = $opts['stroke_width'] ?? 1.5;
        $dot = $opts['dot'] ?? true;

        if (empty($series)) {
            return '';
        }
        $series = array_values($series);
        $min = min($series);
        $max = max($series);
        $range = ($max - $min) !== 0.0 ? ($max - $min) : 1.0;
        $stepx = count($series) > 1 ? $width / (count($series) - 1) : 0;
        $points = [];
        foreach ($series as $i => $v) {
            $x = $i * $stepx;
            $y = $height - (($v - $min) / $range) * ($height - 4) - 2;
            $points[] = [$x, $y];
        }
        $pathparts = [];
        foreach ($points as $i => [$x, $y]) {
            $pathparts[] = ($i === 0 ? 'M' : 'L') . self::fmt($x) . ',' . self::fmt($y);
        }
        $path = implode(' ', $pathparts);
        $area = $path . ' L' . self::fmt($width) . ',' . self::fmt($height) . ' L0,' . self::fmt($height) . ' Z';
        $last = $points[count($points) - 1];

        $svg = '<svg width="' . (int) $width . '" height="' . (int) $height . '" '
            . 'style="display:block" aria-hidden="true">';
        $svg .= '<path d="' . $area . '" fill="' . self::esc($fill) . '"></path>';
        $svg .= '<path d="' . $path . '" fill="none" stroke="' . self::esc($color) . '" '
            . 'stroke-width="' . self::fmt($strokewidth) . '" '
            . 'stroke-linejoin="round" stroke-linecap="round"></path>';
        if ($dot) {
            $svg .= '<circle cx="' . self::fmt($last[0]) . '" cy="' . self::fmt($last[1]) . '" '
                . 'r="2" fill="' . self::esc($color) . '"></circle>';
        }
        $svg .= '</svg>';
        return $svg;
    }

    /**
     * SLA bar. Ported from atoms.jsx:46-87.
     *
     * @param float|null $hours
     * @param array $opts max, height, show_goal, goal_at, thresholds
     * @return string
     */
    public function render_sla_bar(?float $hours, array $opts = []): string {
        $h = $hours ?? 0.0;
        $max = $opts['max'] ?? 168;
        $height = $opts['height'] ?? 8;
        $showgoal = $opts['show_goal'] ?? true;
        $goalat = $opts['goal_at'] ?? 24;
        $thresholds = $opts['thresholds'] ?? bucket::default_thresholds();

        $b = bucket::bucket_for($h, $thresholds);
        $bcolor = bucket::bucket_color($b);
        $clamped = min($h, $max);
        $pos = ($clamped / $max) * 100;
        $goalpos = ($goalat / $max) * 100;
        $radius = $height / 2;

        $stops = [
            [0,                          ($thresholds[0] / $max) * 100, '#10b981'],
            [($thresholds[0] / $max) * 100, ($thresholds[1] / $max) * 100, '#f59e0b'],
            [($thresholds[1] / $max) * 100, ($thresholds[2] / $max) * 100, '#f97316'],
            [($thresholds[2] / $max) * 100, 100,                            '#ef4444'],
        ];
        $zones = '';
        foreach ($stops as [$from, $to, $color]) {
            $zones .= '<div style="width:' . self::fmt($to - $from) . '%;background:' . $color . ';opacity:0.22"></div>';
        }

        $goal = $showgoal
            ? '<div style="position:absolute;left:' . self::fmt($goalpos) . '%;top:-2px;bottom:-2px;'
                . 'width:0;border-left:1.5px dashed rgba(0,0,0,0.35)"></div>'
            : '';

        return '<div style="position:relative;width:100%;height:' . (int) $height . 'px;'
            . 'border-radius:' . self::fmt($radius) . 'px;overflow:visible">'
            . '<div style="position:absolute;inset:0;border-radius:' . self::fmt($radius) . 'px;'
            . 'overflow:hidden;display:flex">' . $zones . '</div>'
            . '<div style="position:absolute;left:0;top:0;height:100%;width:' . self::fmt($pos) . '%;'
            . 'background:' . $bcolor . ';border-radius:' . self::fmt($radius) . 'px;'
            . 'transition:width .3s"></div>'
            . $goal
            . '<div style="position:absolute;left:calc(' . self::fmt($pos) . '% - 6px);top:-2px;'
            . 'width:12px;height:' . (int) ($height + 4) . 'px;border-radius:6px;background:#fff;'
            . 'border:2px solid ' . $bcolor . ';box-shadow:0 1px 3px rgba(0,0,0,.15)"></div>'
            . '</div>';
    }

    /**
     * SLA dots. Ported from atoms.jsx:89-104.
     *
     * @param float|null $hours
     * @param array $opts size, gap
     * @return string
     */
    public function render_sla_dots(?float $hours, array $opts = []): string {
        $size = $opts['size'] ?? 6;
        $gap = $opts['gap'] ?? 2;
        $b = bucket::bucket_for($hours);
        $order = [bucket::EXCELLENT, bucket::GOOD, bucket::REGULAR, bucket::CRITICAL];
        $idx = array_search($b, $order, true);

        $dots = '';
        foreach ($order as $i => $bid) {
            $bg = $i <= $idx ? bucket::bucket_color($bid) : '#e7e5e4';
            $dots .= '<span style="width:' . (int) $size . 'px;height:' . (int) $size . 'px;'
                . 'border-radius:' . (int) $size . 'px;background:' . $bg . '"></span>';
        }
        return '<span style="display:inline-flex;gap:' . (int) $gap . 'px;align-items:center">'
            . $dots . '</span>';
    }

    /**
     * Timeline bar. Ported from atoms.jsx:172-215.
     *
     * @param int|null $opens unix timestamp
     * @param int|null $closes unix timestamp
     * @param string $urgency on-track|soon|urgent|overdue|none
     * @param array $opts has_rule, no_rule_label, today
     * @return string
     */
    public function render_timeline_bar(?int $opens, ?int $closes, string $urgency, array $opts = []): string {
        $hasrule = $opts['has_rule'] ?? true;
        $norulelabel = $opts['no_rule_label'] ?? get_string('no_rule', 'block_grade_me');
        $today = $opts['today'] ?? time();

        if (!$hasrule || !$opens || !$closes) {
            return '<div style="display:inline-flex;align-items:center;gap:4px;padding:1px 5px;'
                . 'border-radius:3px;background:#f5f5f4;color:#a8a29e;font-size:9.5px;'
                . 'font-weight:600;font-family:\'JetBrains Mono\',monospace;letter-spacing:0.2px;'
                . 'text-transform:uppercase">'
                . '<span style="width:5px;height:5px;border-radius:5px;background:#d6d3d1"></span>'
                . self::esc($norulelabel)
                . '</div>';
        }

        $colors = [
            'on-track' => ['fill' => '#10b981', 'track' => '#d1fae5'],
            'soon'     => ['fill' => '#f59e0b', 'track' => '#fef3c7'],
            'urgent'   => ['fill' => '#f97316', 'track' => '#ffedd5'],
            'overdue'  => ['fill' => '#ef4444', 'track' => '#fee2e2'],
        ];
        $c = $colors[$urgency] ?? $colors['on-track'];

        $total = $closes - $opens;
        $elapsed = max(0, min($total, $today - $opens));
        $pct = $total > 0 ? ($elapsed / $total) * 100 : 0;
        $clamped = max(0, min(100, $pct));
        $isoverdue = $urgency === rule_resolver::URGENCY_OVERDUE;

        $fmt = static function (int $ts): string {
            return date('d/m', $ts);
        };

        return '<div style="display:flex;align-items:center;gap:6px;'
            . 'font-family:\'JetBrains Mono\',monospace;font-size:9.5px;color:#57534e">'
            . '<span>' . self::esc($fmt($opens)) . '</span>'
            . '<div style="flex:1;position:relative;height:4px;background:' . $c['track']
            . ';border-radius:2px;overflow:visible">'
            . '<div style="position:absolute;left:0;top:0;bottom:0;width:' . self::fmt($clamped) . '%;'
            . 'background:' . $c['fill'] . ';border-radius:2px"></div>'
            . '<div style="position:absolute;left:calc(' . self::fmt($clamped) . '% - 3px);top:-2px;'
            . 'width:6px;height:8px;border-radius:2px;background:' . $c['fill']
            . ';box-shadow:0 0 0 1.5px #fff"></div>'
            . '</div>'
            . '<span style="color:' . ($isoverdue ? '#b91c1c' : '#57534e')
            . ';font-weight:' . ($isoverdue ? '700' : '400') . '">'
            . self::esc($fmt($closes)) . '</span>'
            . '</div>';
    }

    /**
     * Trend chip. Ported from atoms.jsx:25-44.
     *
     * @param float $pct
     * @param bool $inverse When true (default), a drop is good.
     * @return string
     */
    public function render_trend_chip(float $pct, bool $inverse = true): string {
        $isdown = $pct < 0;
        $isflat = abs($pct) < 2;
        $good = $inverse ? $isdown : !$isdown;

        $color = $isflat ? '#78716c' : ($good ? '#047857' : '#b91c1c');
        $bg = $isflat ? '#f5f5f4' : ($good ? '#d1fae5' : '#fee2e2');
        $arrow = $isflat ? '→' : ($isdown ? '↓' : '↑');

        return '<span style="display:inline-flex;align-items:center;gap:2px;'
            . 'font-family:\'JetBrains Mono\',monospace;font-size:10px;font-weight:600;'
            . 'color:' . $color . ';background:' . $bg . ';padding:2px 5px;'
            . 'border-radius:4px;line-height:1">'
            . '<span style="font-size:10px">' . $arrow . '</span>'
            . '<span>' . abs((int) round($pct)) . '%</span>'
            . '</span>';
    }

    /**
     * Coloured avatar with the user's initials. Ported from atoms.jsx:156-170.
     *
     * @param string $initials
     * @param array $opts size
     * @return string
     */
    public function render_avatar(string $initials, array $opts = []): string {
        $size = $opts['size'] ?? 26;
        $initials = mb_substr($initials, 0, 2);
        if ($initials === '') {
            $initials = '?';
        }
        $code = ord(substr($initials, 0, 1)) * 13 + ord(substr($initials, 1, 1) ?: substr($initials, 0, 1));
        $hue = $code % 360;
        $bg = 'oklch(0.85 0.08 ' . $hue . ')';
        $fg = 'oklch(0.32 0.08 ' . $hue . ')';
        return '<span style="display:inline-flex;align-items:center;justify-content:center;'
            . 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;'
            . 'border-radius:' . (int) $size . 'px;background:' . $bg . ';color:' . $fg . ';'
            . 'font-size:' . self::fmt($size * 0.42) . 'px;font-weight:700;'
            . 'font-family:Manrope,sans-serif;letter-spacing:-0.2px;flex:0 0 auto">'
            . self::esc(mb_strtoupper($initials)) . '</span>';
    }

    /**
     * Format a number for SVG attributes (no exponent notation, 4-decimal precision).
     *
     * @param float|int $n
     * @return string
     */
    private static function fmt($n): string {
        return rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    }

    /**
     * Escape a string for safe inclusion in HTML attributes / text.
     *
     * @param string $s
     * @return string
     */
    private static function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
