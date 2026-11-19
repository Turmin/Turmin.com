<?php
/**
 * Clock Out — turmin.com/clockout/
 *
 * Working-hours calculator: when can I go home, and how long did I work?
 * Works without JavaScript (the form is calculated on the server);
 * with JavaScript everything updates live while you type.
 *
 * Compatible with PHP 7.0 and up (no arrow functions, nullable or strict types).
 */

date_default_timezone_set('Europe/Amsterdam');

const DAY = 1440;

/** "8:43", "08.43", "843" -> minutes since midnight, or null. */
function parse_time($s)
{
    $s = trim((string) $s);
    if (preg_match('/^(\d{1,2})[:.](\d{2})$/', $s, $m) || preg_match('/^(\d{1,2})(\d{2})$/', $s, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h < 24 && $i < 60) {
            return $h * 60 + $i;
        }
    }
    return null;
}

/** "37", "0:37", "1:05", "-15", "+1.30" -> minutes (may be negative), or null. */
function parse_duration($s)
{
    $s = str_replace([' ', '−'], ['', '-'], trim((string) $s));
    if ($s === '') {
        return 0;
    }
    if (!preg_match('/^([+-])?(?:(\d{1,2})[:.](\d{2})|(\d{1,4}))$/', $s, $m)) {
        return null;
    }
    $min = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : (int) $m[2] * 60 + (int) $m[3];
    if (isset($m[3]) && $m[3] !== '' && (int) $m[3] >= 60) {
        return null;
    }
    return ($m[1] ?? '') === '-' ? -$min : $min;
}

/** Minutes -> "16:36" (with a day marker when it crosses midnight). */
function fmt_time($min)
{
    $t = ($min % DAY + DAY) % DAY;
    $days = intdiv($min - $t, DAY);
    $out = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    if ($days !== 0) {
        $out .= ' <small>(' . ($days > 0 ? '+' : '') . $days . ' day)</small>';
    }
    return $out;
}

/** Minutes -> "1:05" (unsigned). */
function fmt_duration($min)
{
    $min = abs($min);
    return sprintf('%d:%02d', intdiv($min, 60), $min % 60);
}

/** Minutes -> "1 h 5 min" / "37 min". */
function human_duration($min)
{
    $min = abs($min);
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h && $m) {
        return "$h h $m min";
    }
    return $h ? "$h h" : "$m min";
}

/** URL of a file in this folder, with ?v=<mtime> so browsers and iOS pick up changes. */
function asset($path)
{
    return '/clockout/' . $path . '?v=' . (int) @filemtime(__DIR__ . '/' . $path);
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------- input

function q($k, $default = '')
{
    if (!isset($_GET[$k]) || !is_string($_GET[$k])) {
        return $default;
    }
    return (string) substr($_GET[$k], 0, 12);
}

$in = [
    'start'    => q('start'),
    'workday'  => q('workday', '8:00'),
    'break'    => q('break', '0:30'),
    'overtime' => q('overtime'),
    's2'       => q('s2'),
    'e2'       => q('e2'),
    'b2'       => q('b2', '0:30'),
    'w2'       => q('w2', '8:00'),
];

// ------------------------------------------------ 1. When can I leave?

$home = null;
$homeError = null;
$homeAsk = $in['start'] === '' ? 'Enter your start time' : (trim($in['workday']) === '' ? 'Enter your workday' : null);
if ($homeAsk === null) {
    $start    = parse_time($in['start']);
    $workday  = parse_duration($in['workday']);
    $break    = parse_duration($in['break']);
    $overtime = parse_duration($in['overtime']);
    if ($start === null) {
        $homeError = "That start time doesn't make sense. Try something like 8:43.";
    } elseif ($workday === null || $break === null || $overtime === null) {
        $homeError = 'Use minutes (37) or hours:minutes (1:05). A minus sign (-15) works too.';
    } else {
        $normal = $start + $workday + $break;
        $home = ['normal' => $normal, 'leave' => $normal - $overtime, 'overtime' => $overtime];
    }
}

// ------------------------------------------ 2. How long did I work?

$worked = null;
$workedError = null;
$workedAsk = ($in['s2'] === '' && $in['e2'] === '') ? 'Enter start and end' : (trim($in['w2']) === '' ? 'Enter your workday' : null);
if ($workedAsk === null) {
    $s2 = parse_time($in['s2']);
    $e2 = parse_time($in['e2']);
    $b2 = parse_duration($in['b2']);
    $w2 = parse_duration($in['w2']);
    if ($s2 === null || $e2 === null) {
        $workedError = 'Enter both a start and an end time, like 8:43 and 17:20.';
    } elseif ($b2 === null || $w2 === null) {
        $workedError = 'Use minutes (30) or hours:minutes (0:30).';
    } else {
        $span = $e2 - $s2;
        if ($span < 0) {
            $span += DAY; // night shift
        }
        $total = $span - $b2;
        $worked = ['total' => $total, 'diff' => $total - $w2];
    }
}

function overtime_text($ot)
{
    if ($ot > 0) {
        return human_duration($ot) . ' early';
    }
    if ($ot < 0) {
        return human_duration($ot) . ' late';
    }
    return 'Right on time';
}

function diff_text($v)
{
    if ($v > 0) {
        return '+' . fmt_duration($v) . ' overtime (' . human_duration($v) . ')';
    }
    if ($v < 0) {
        return '−' . fmt_duration($v) . ' short (' . human_duration($v) . ')';
    }
    return 'Exactly your hours. Neat.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Clock Out · Turmin</title>
  <meta name="description" content="Quickly work out when you can go home, overtime and breaks included.">
  <meta name="theme-color" content="#1E1A44">
  <link rel="canonical" href="https://turmin.com/clockout/">
  <meta name="apple-mobile-web-app-title" content="Clock Out">
  <meta name="application-name" content="Clock Out">
  <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
  <link rel="icon" href="<?= asset('img/icon-512.png') ?>" type="image/png" sizes="512x512">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('img/apple-touch-icon.png') ?>">
  <link rel="preload" href="/fonts/sora-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
  <main class="tool">
    <header class="tool-head">
      <a class="lockup lockup-sm" href="/" aria-label="Turmin home">
        <svg class="mark" viewBox="0 0 64 64" aria-hidden="true">
          <path fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" d="M47.5 11.2A26 26 0 1 1 32 6"/>
          <circle cx="44" cy="8.6" r="4.2" fill="#3DDC97"/>
          <path fill="currentColor" d="M18 20H46V28H36V46H28V28H18Z"/>
        </svg>
        <span class="wordmark">Turmin</span>
      </a>
      <h1 class="tool-title">Clock Out</h1>
      <p class="tool-intro">When can I go home? Start time in, overtime on top, done.</p>
    </header>

    <!-- 1. When can I leave? -->
    <section class="panel" aria-labelledby="h-home">
      <h2 id="h-home" class="panel-title">When can I leave?</h2>
      <form class="calc" id="form-home" method="get" action="#form-home">
        <div class="field">
          <div class="label-row">
            <label for="start">Started at</label>
            <button type="button" class="chip" data-now="start">Now</button>
          </div>
          <input id="start" name="start" inputmode="decimal" autocomplete="off" placeholder="e.g. 8:43" data-time value="<?= e($in['start']) ?>">
        </div>
        <div class="field">
          <label for="overtime">Overtime</label>
          <input id="overtime" name="overtime" inputmode="text" autocomplete="off" placeholder="e.g. 37 or 1:05" value="<?= e($in['overtime']) ?>">
          <span class="hint">Minutes or h:mm. Hours short? Add a minus: −15.</span>
        </div>
        <div class="field">
          <label for="workday">Workday</label>
          <input id="workday" name="workday" inputmode="text" autocomplete="off" placeholder="e.g. 8:00" value="<?= e($in['workday']) ?>" data-remember>
        </div>
        <div class="field">
          <label for="break">Break</label>
          <input id="break" name="break" inputmode="text" autocomplete="off" placeholder="e.g. 0:30" value="<?= e($in['break']) ?>" data-remember>
        </div>
        <button class="go" type="submit">Calculate</button>

        <output class="result" id="out-home" for="start overtime workday break" aria-live="polite">
<?php if ($homeError): ?>
          <p class="result-error"><?= e($homeError) ?></p>
<?php elseif ($home): ?>
          <p class="result-label">You can leave at</p>
          <p class="result-big"><?= fmt_time($home['leave']) ?></p>
          <p class="result-sub"><?= e(overtime_text($home['overtime'])) ?> · normally done at <?= fmt_time($home['normal']) ?></p>
<?php else: ?>
          <p class="result-label"><?= e($homeAsk) ?></p>
          <p class="result-big result-empty">00:00</p>
<?php endif; ?>
        </output>
      </form>
    </section>

    <!-- 2. How long did I work? -->
    <section class="panel" aria-labelledby="h-worked">
      <h2 id="h-worked" class="panel-title">How long did I work?</h2>
      <form class="calc" id="form-worked" method="get" action="#form-worked">
        <div class="field">
          <label for="s2">Started at</label>
          <input id="s2" name="s2" inputmode="decimal" autocomplete="off" placeholder="e.g. 8:43" data-time value="<?= e($in['s2']) ?>">
        </div>
        <div class="field">
          <div class="label-row">
            <label for="e2">Stopped at</label>
            <button type="button" class="chip" data-now="e2">Now</button>
          </div>
          <input id="e2" name="e2" inputmode="decimal" autocomplete="off" placeholder="e.g. 17:20" data-time value="<?= e($in['e2']) ?>">
        </div>
        <div class="field">
          <label for="w2">Workday</label>
          <input id="w2" name="w2" inputmode="text" autocomplete="off" placeholder="e.g. 8:00" value="<?= e($in['w2']) ?>" data-remember="workday">
        </div>
        <div class="field">
          <label for="b2">Break</label>
          <input id="b2" name="b2" inputmode="text" autocomplete="off" placeholder="e.g. 0:30" value="<?= e($in['b2']) ?>" data-remember="break">
        </div>
        <button class="go" type="submit">Calculate</button>

        <output class="result" id="out-worked" for="s2 e2 w2 b2" aria-live="polite">
<?php if ($workedError): ?>
          <p class="result-error"><?= e($workedError) ?></p>
<?php elseif ($worked): ?>
          <p class="result-label">Worked</p>
          <p class="result-big"><?= fmt_duration($worked['total']) ?></p>
          <p class="result-sub"><?= e(diff_text($worked['diff'])) ?></p>
<?php else: ?>
          <p class="result-label"><?= e($workedAsk) ?></p>
          <p class="result-big result-empty">0:00</p>
<?php endif; ?>
        </output>
        <button type="button" class="chip chip-wide" id="use-overtime" hidden>Use as overtime above ↑</button>
      </form>
    </section>

    <p class="tool-foot">EST. 2006 - The Netherlands</p>
  </main>

  <script>
  (function () {
    'use strict';
    var DAY = 1440;
    var $ = function (id) { return document.getElementById(id); };
    var store = {
      get: function (k) { try { return localStorage.getItem('clockout.' + k); } catch (e) { return null; } },
      set: function (k, v) { try { localStorage.setItem('clockout.' + k, v); } catch (e) {} }
    };

    function parseTime(s) {
      s = String(s || '').trim();
      var m = s.match(/^(\d{1,2})[:.](\d{2})$/) || s.match(/^(\d{1,2})(\d{2})$/);
      if (!m) return null;
      var h = +m[1], i = +m[2];
      return h < 24 && i < 60 ? h * 60 + i : null;
    }
    function parseDuration(s) {
      s = String(s || '').replace(/\s+/g, '').replace('−', '-');
      if (s === '') return 0;
      var m = s.match(/^([+-])?(?:(\d{1,2})[:.](\d{2})|(\d{1,4}))$/);
      if (!m) return null;
      if (m[3] !== undefined && +m[3] >= 60) return null;
      var min = m[4] !== undefined ? +m[4] : +m[2] * 60 + +m[3];
      return m[1] === '-' ? -min : min;
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmtTime(min) {
      var t = ((min % DAY) + DAY) % DAY, days = Math.round((min - t) / DAY);
      var out = pad(Math.floor(t / 60)) + ':' + pad(t % 60);
      if (days !== 0) out += ' <small>(' + (days > 0 ? '+' : '') + days + ' day)</small>';
      return out;
    }
    function fmtDuration(min) { min = Math.abs(min); return Math.floor(min / 60) + ':' + pad(min % 60); }
    function human(min) {
      min = Math.abs(min);
      var h = Math.floor(min / 60), m = min % 60;
      if (h && m) return h + ' h ' + m + ' min';
      return h ? h + ' h' : m + ' min';
    }
    function nowMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function render(out, label, big, sub, empty) {
      out.innerHTML =
        '<p class="result-label">' + esc(label) + '</p>' +
        '<p class="result-big' + (empty ? ' result-empty' : '') + '">' + big + '</p>' +
        (sub ? '<p class="result-sub">' + sub + '</p>' : '');
    }
    // A half-typed time ("8") isn't an error yet while you're still in the field.
    function typing(id) { return document.activeElement === $(id); }
    function error(out, msg) { out.innerHTML = '<p class="result-error">' + esc(msg) + '</p>'; }

    // Remember workday and break per browser (unless the URL set them).
    var fromUrl = new URLSearchParams(location.search);
    document.querySelectorAll('[data-remember]').forEach(function (el) {
      var key = el.getAttribute('data-remember') || el.name;
      var saved = store.get(key);
      if (saved !== null && !fromUrl.has(el.name)) el.value = saved;
      el.addEventListener('change', function () { store.set(key, el.value); });
    });

    // 1. When can I leave?
    function calcHome() {
      var out = $('out-home');
      if ($('start').value === '') { render(out, 'Enter your start time', '00:00', '', true); return; }
      if ($('workday').value.trim() === '') { render(out, 'Enter your workday', '00:00', '', true); return; }
      var start = parseTime($('start').value), work = parseDuration($('workday').value),
          brk = parseDuration($('break').value), ot = parseDuration($('overtime').value);
      if (start === null) return typing('start')
        ? render(out, 'Enter your start time', '00:00', '', true)
        : error(out, "That start time doesn't make sense. Try something like 8:43.");
      if (work === null || brk === null || ot === null)
        return error(out, 'Use minutes (37) or hours:minutes (1:05). A minus sign (-15) works too.');

      var normal = start + work + brk, leave = normal - ot;
      var sub = ot > 0 ? human(ot) + ' early' : ot < 0 ? human(ot) + ' late' : 'Right on time';
      sub = esc(sub) + ' · normally done at ' + fmtTime(normal);

      // Countdown, if you started today and aren't done yet.
      var now = nowMin();
      if (now >= start) {
        var left = leave - now;
        sub += '<br><span class="countdown">' + (left > 0
          ? esc(human(left)) + ' to go'
          : left === 0 ? 'Now! Grab your coat.' : 'Why are you still here? Free for ' + esc(human(left)) + ' already.') + '</span>';
      }
      render(out, 'You can leave at', fmtTime(leave), sub);
    }

    // 2. How long did I work?
    var lastDiff = null;
    function calcWorked() {
      var out = $('out-worked'), btn = $('use-overtime');
      btn.hidden = true; lastDiff = null;
      if ($('s2').value === '' && $('e2').value === '') { render(out, 'Enter start and end', '0:00', '', true); return; }
      if ($('w2').value.trim() === '') { render(out, 'Enter your workday', '0:00', '', true); return; }
      var s = parseTime($('s2').value), e = parseTime($('e2').value),
          b = parseDuration($('b2').value), w = parseDuration($('w2').value);
      if (s === null || e === null) return typing('s2') || typing('e2')
        ? render(out, 'Enter start and end', '0:00', '', true)
        : error(out, 'Enter both a start and an end time, like 8:43 and 17:20.');
      if (b === null || w === null) return error(out, 'Use minutes (30) or hours:minutes (0:30).');

      var span = e - s; if (span < 0) span += DAY;
      var total = span - b, v = total - w;
      var sub = v > 0 ? '+' + fmtDuration(v) + ' overtime (' + human(v) + ')'
              : v < 0 ? '−' + fmtDuration(v) + ' short (' + human(v) + ')'
              : 'Exactly your hours. Neat.';
      render(out, 'Worked', fmtDuration(total), esc(sub));
      if (v !== 0) { lastDiff = v; btn.hidden = false; }
    }

    $('use-overtime').addEventListener('click', function () {
      if (lastDiff === null) return;
      $('overtime').value = (lastDiff < 0 ? '-' : '') + fmtDuration(lastDiff);
      calcHome();
      $('form-home').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.querySelectorAll('[data-now]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var n = nowMin(), el = $(btn.getAttribute('data-now'));
        el.value = pad(Math.floor(n / 60)) + ':' + pad(n % 60);
        el.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    // Tidy typed times on blur: "843" or "8.43" becomes "08:43".
    document.querySelectorAll('[data-time]').forEach(function (el) {
      el.addEventListener('blur', function () {
        var t = parseTime(el.value);
        if (t !== null) el.value = pad(Math.floor(t / 60)) + ':' + pad(t % 60);
        el.closest('form').id === 'form-home' ? calcHome() : calcWorked();
      });
    });

    $('form-home').addEventListener('input', calcHome);
    $('form-worked').addEventListener('input', calcWorked);
    // With JS everything is live; submitting just updates the URL (handy to share/bookmark).
    ['form-home', 'form-worked'].forEach(function (id) {
      $(id).addEventListener('submit', function (ev) {
        ev.preventDefault();
        var params = new URLSearchParams(new FormData(ev.target));
        history.replaceState(null, '', '?' + params.toString() + '#' + id);
        id === 'form-home' ? calcHome() : calcWorked();
      });
    });

    document.documentElement.classList.add('js');
    calcHome(); calcWorked();
    setInterval(calcHome, 30000);
  })();
  </script>
</body>
</html>
