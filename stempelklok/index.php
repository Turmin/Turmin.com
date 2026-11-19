<?php
/**
 * Stempelklok — turmin.com/stempelklok/
 *
 * Werktijden-rekenaar: hoe laat mag ik naar huis, en hoeveel heb ik gewerkt?
 * Werkt zonder JavaScript (het formulier rekent op de server uit);
 * met JavaScript rekent alles direct tijdens het typen.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Amsterdam');

const DAY = 1440;

/** "8:43", "08.43", "843" -> minuten sinds middernacht, of null. */
function parse_time(?string $s): ?int
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

/** "37", "0:37", "1:05", "-15", "+1.30" -> minuten (mag negatief), of null. */
function parse_duration(?string $s): ?int
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

/** Minuten -> "16:36" (met dag-aanduiding als het over middernacht gaat). */
function fmt_time(int $min): string
{
    $days = intdiv($min - (($min % DAY + DAY) % DAY), DAY);
    $t = ($min % DAY + DAY) % DAY;
    $out = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    if ($days > 0) {
        $out .= ' <small>(+' . $days . ' dag)</small>';
    } elseif ($days < 0) {
        $out .= ' <small>(' . $days . ' dag)</small>';
    }
    return $out;
}

/** Minuten -> "1:05" (zonder teken). */
function fmt_duration(int $min): string
{
    $min = abs($min);
    return sprintf('%d:%02d', intdiv($min, 60), $min % 60);
}

/** Minuten -> "1 u 5 min" / "37 min". */
function human_duration(int $min): string
{
    $min = abs($min);
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h && $m) {
        return "$h u $m min";
    }
    return $h ? "$h u" : "$m min";
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------- invoer

$q = static fn (string $k, string $default = ''): string =>
    isset($_GET[$k]) && is_string($_GET[$k]) ? substr($_GET[$k], 0, 12) : $default;

$in = [
    'begin'   => $q('begin'),
    'werkdag' => $q('werkdag', '8:00'),
    'pauze'   => $q('pauze', '0:30'),
    'saldo'   => $q('saldo'),
    'b2'      => $q('b2'),
    'e2'      => $q('e2'),
    'p2'      => $q('p2', '0:30'),
    'w2'      => $q('w2', '8:00'),
];

// ------------------------------------------- 1. Hoe laat mag ik naar huis?

$home = null;
$homeError = null;
if ($in['begin'] !== '') {
    $begin   = parse_time($in['begin']);
    $werkdag = parse_duration($in['werkdag']);
    $pauze   = parse_duration($in['pauze']);
    $saldo   = parse_duration($in['saldo']);
    if ($begin === null) {
        $homeError = 'Die begintijd snap ik niet. Probeer iets als 8:43.';
    } elseif ($werkdag === null || $pauze === null || $saldo === null) {
        $homeError = 'Gebruik minuten (37) of uren:minuten (1:05). Een min ervoor (-15) kan ook.';
    } else {
        $normaal = $begin + $werkdag + $pauze;
        $home = ['normaal' => $normaal, 'weg' => $normaal - $saldo, 'saldo' => $saldo];
    }
}

// -------------------------------------------- 2. Hoeveel heb ik gewerkt?

$worked = null;
$workedError = null;
if ($in['b2'] !== '' || $in['e2'] !== '') {
    $b2 = parse_time($in['b2']);
    $e2 = parse_time($in['e2']);
    $p2 = parse_duration($in['p2']);
    $w2 = parse_duration($in['w2']);
    if ($b2 === null || $e2 === null) {
        $workedError = 'Vul een begin- én eindtijd in, zoals 8:43 en 17:20.';
    } elseif ($p2 === null || $w2 === null) {
        $workedError = 'Gebruik minuten (30) of uren:minuten (0:30).';
    } else {
        $span = $e2 - $b2;
        if ($span < 0) {
            $span += DAY; // nachtdienst
        }
        $gewerkt = $span - $p2;
        $worked = ['gewerkt' => $gewerkt, 'verschil' => $gewerkt - $w2];
    }
}

function saldo_text(int $saldo): string
{
    if ($saldo > 0) {
        return human_duration($saldo) . ' eerder weg';
    }
    if ($saldo < 0) {
        return human_duration($saldo) . ' langer blijven';
    }
    return 'Precies op tijd';
}

function verschil_text(int $v): string
{
    if ($v > 0) {
        return '+' . fmt_duration($v) . ' overgewerkt (' . human_duration($v) . ')';
    }
    if ($v < 0) {
        return '−' . fmt_duration($v) . ' te kort (' . human_duration($v) . ')';
    }
    return 'Precies je uren, keurig.';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Stempelklok · Turmin</title>
  <meta name="description" content="Reken snel uit hoe laat je naar huis mag, met overuren, pauze en al.">
  <meta name="theme-color" content="#1E1A44">
  <link rel="canonical" href="https://turmin.com/stempelklok/">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
  <link rel="preload" href="/fonts/sora-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/css/style.css">
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
      <h1 class="tool-title">Stempelklok</h1>
      <p class="tool-intro">Hoe laat mag ik naar huis? Begintijd erin, overuren erbij, klaar.</p>
    </header>

    <!-- 1. Hoe laat mag ik naar huis? -->
    <section class="panel" aria-labelledby="h-home">
      <h2 id="h-home" class="panel-title">Wanneer mag ik weg?</h2>
      <form class="calc" id="form-home" method="get" action="#form-home">
        <div class="field">
          <div class="label-row">
            <label for="begin">Begonnen om</label>
            <button type="button" class="chip" data-now="begin">Nu</button>
          </div>
          <input id="begin" name="begin" type="time" required value="<?= e($in['begin']) ?>">
        </div>
        <div class="field">
          <label for="saldo">Overuren</label>
          <input id="saldo" name="saldo" inputmode="text" autocomplete="off" placeholder="bijv. 37 of 1:05" value="<?= e($in['saldo']) ?>">
          <span class="hint">Minuten of u:mm. Te kort? Zet er een min voor: −15.</span>
        </div>
        <div class="field">
          <label for="werkdag">Werkdag</label>
          <input id="werkdag" name="werkdag" inputmode="text" autocomplete="off" value="<?= e($in['werkdag']) ?>" data-remember>
        </div>
        <div class="field">
          <label for="pauze">Pauze</label>
          <input id="pauze" name="pauze" inputmode="text" autocomplete="off" value="<?= e($in['pauze']) ?>" data-remember>
        </div>
        <button class="go" type="submit">Reken uit</button>

        <output class="result" id="out-home" for="begin saldo werkdag pauze" aria-live="polite">
<?php if ($homeError): ?>
          <p class="result-error"><?= e($homeError) ?></p>
<?php elseif ($home): ?>
          <p class="result-label">Je mag weg om</p>
          <p class="result-big"><?= fmt_time($home['weg']) ?></p>
          <p class="result-sub"><?= e(saldo_text($home['saldo'])) ?> · normaal klaar om <?= fmt_time($home['normaal']) ?></p>
<?php else: ?>
          <p class="result-label">Vul je begintijd in</p>
          <p class="result-big result-empty">--:--</p>
<?php endif; ?>
        </output>
      </form>
    </section>

    <!-- 2. Hoeveel heb ik gewerkt? -->
    <section class="panel" aria-labelledby="h-worked">
      <h2 id="h-worked" class="panel-title">Hoeveel heb ik gewerkt?</h2>
      <form class="calc" id="form-worked" method="get" action="#form-worked">
        <div class="field">
          <label for="b2">Begonnen om</label>
          <input id="b2" name="b2" type="time" value="<?= e($in['b2']) ?>">
        </div>
        <div class="field">
          <div class="label-row">
            <label for="e2">Gestopt om</label>
            <button type="button" class="chip" data-now="e2">Nu</button>
          </div>
          <input id="e2" name="e2" type="time" value="<?= e($in['e2']) ?>">
        </div>
        <div class="field">
          <label for="w2">Werkdag</label>
          <input id="w2" name="w2" inputmode="text" autocomplete="off" value="<?= e($in['w2']) ?>" data-remember="werkdag">
        </div>
        <div class="field">
          <label for="p2">Pauze</label>
          <input id="p2" name="p2" inputmode="text" autocomplete="off" value="<?= e($in['p2']) ?>" data-remember="pauze">
        </div>
        <button class="go" type="submit">Reken uit</button>

        <output class="result" id="out-worked" for="b2 e2 w2 p2" aria-live="polite">
<?php if ($workedError): ?>
          <p class="result-error"><?= e($workedError) ?></p>
<?php elseif ($worked): ?>
          <p class="result-label">Gewerkt</p>
          <p class="result-big"><?= fmt_duration($worked['gewerkt']) ?></p>
          <p class="result-sub"><?= e(verschil_text($worked['verschil'])) ?></p>
<?php else: ?>
          <p class="result-label">Vul begin en eind in</p>
          <p class="result-big result-empty">-:--</p>
<?php endif; ?>
        </output>
        <button type="button" class="chip chip-wide" id="use-saldo" hidden>Gebruik als overuren hierboven ↑</button>
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
      get: function (k) { try { return localStorage.getItem('stempelklok.' + k); } catch (e) { return null; } },
      set: function (k, v) { try { localStorage.setItem('stempelklok.' + k, v); } catch (e) {} }
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
      if (days > 0) out += ' <small>(+' + days + ' dag)</small>';
      if (days < 0) out += ' <small>(' + days + ' dag)</small>';
      return out;
    }
    function fmtDuration(min) { min = Math.abs(min); return Math.floor(min / 60) + ':' + pad(min % 60); }
    function human(min) {
      min = Math.abs(min);
      var h = Math.floor(min / 60), m = min % 60;
      if (h && m) return h + ' u ' + m + ' min';
      return h ? h + ' u' : m + ' min';
    }
    function nowMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function render(out, label, big, sub, empty) {
      out.innerHTML =
        '<p class="result-label">' + esc(label) + '</p>' +
        '<p class="result-big' + (empty ? ' result-empty' : '') + '">' + big + '</p>' +
        (sub ? '<p class="result-sub">' + sub + '</p>' : '');
    }
    function error(out, msg) { out.innerHTML = '<p class="result-error">' + esc(msg) + '</p>'; }

    // Onthoud werkdag en pauze per browser (alleen als er geen waarde in de URL stond).
    var fromUrl = new URLSearchParams(location.search);
    document.querySelectorAll('[data-remember]').forEach(function (el) {
      var key = el.getAttribute('data-remember') || el.name;
      var saved = store.get(key);
      if (saved !== null && !fromUrl.has(el.name)) el.value = saved;
      el.addEventListener('change', function () { store.set(key, el.value); });
    });

    // 1. Wanneer mag ik weg?
    function calcHome() {
      var out = $('out-home');
      if ($('begin').value === '') { render(out, 'Vul je begintijd in', '--:--', '', true); return; }
      var begin = parseTime($('begin').value), werk = parseDuration($('werkdag').value),
          pauze = parseDuration($('pauze').value), saldo = parseDuration($('saldo').value);
      if (begin === null) return error(out, 'Die begintijd snap ik niet. Probeer iets als 8:43.');
      if (werk === null || pauze === null || saldo === null)
        return error(out, 'Gebruik minuten (37) of uren:minuten (1:05). Een min ervoor (-15) kan ook.');

      var normaal = begin + werk + pauze, weg = normaal - saldo;
      var sub = saldo > 0 ? human(saldo) + ' eerder weg' : saldo < 0 ? human(saldo) + ' langer blijven' : 'Precies op tijd';
      sub = esc(sub) + ' · normaal klaar om ' + fmtTime(normaal);

      // Aftellen, als je vandaag begonnen bent en nog niet klaar bent.
      var now = nowMin();
      if (now >= begin) {
        var left = weg - now;
        sub += '<br><span class="countdown">' + (left > 0
          ? 'Nog ' + esc(human(left)) + ' te gaan'
          : left === 0 ? 'Nu! Jas aan.' : 'Waarom ben je hier nog? Al ' + esc(human(left)) + ' vrij.') + '</span>';
      }
      render(out, 'Je mag weg om', fmtTime(weg), sub);
    }

    // 2. Hoeveel heb ik gewerkt?
    var lastVerschil = null;
    function calcWorked() {
      var out = $('out-worked'), btn = $('use-saldo');
      btn.hidden = true; lastVerschil = null;
      if ($('b2').value === '' && $('e2').value === '') { render(out, 'Vul begin en eind in', '-:--', '', true); return; }
      var b = parseTime($('b2').value), e = parseTime($('e2').value),
          p = parseDuration($('p2').value), w = parseDuration($('w2').value);
      if (b === null || e === null) return error(out, 'Vul een begin- én eindtijd in, zoals 8:43 en 17:20.');
      if (p === null || w === null) return error(out, 'Gebruik minuten (30) of uren:minuten (0:30).');

      var span = e - b; if (span < 0) span += DAY;
      var gewerkt = span - p, v = gewerkt - w;
      var sub = v > 0 ? '+' + fmtDuration(v) + ' overgewerkt (' + human(v) + ')'
              : v < 0 ? '−' + fmtDuration(v) + ' te kort (' + human(v) + ')'
              : 'Precies je uren, keurig.';
      render(out, 'Gewerkt', fmtDuration(gewerkt), esc(sub));
      if (v !== 0) { lastVerschil = v; btn.hidden = false; }
    }

    $('use-saldo').addEventListener('click', function () {
      if (lastVerschil === null) return;
      $('saldo').value = (lastVerschil < 0 ? '-' : '') + fmtDuration(lastVerschil);
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

    $('form-home').addEventListener('input', calcHome);
    $('form-worked').addEventListener('input', calcWorked);
    // Met JS rekent alles live; de knop houdt dan alleen de URL bij (handig om te delen/bookmarken).
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
